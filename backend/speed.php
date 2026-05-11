<?php
/**
 * Speed Monitoring Endpoint
 * Global Coaches Limited — CloudRoute
 */
require_once 'classes/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db   = Database::getInstance();
$conn = $db->getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'update':
        // Called by driver app every few seconds
        $busId    = (int)($_POST['bus_id'] ?? 0);
        $speed    = (float)($_POST['speed'] ?? 0);
        $lat      = (float)($_POST['lat'] ?? 0);
        $lng      = (float)($_POST['lng'] ?? 0);

        if (!$busId) { echo json_encode(['success'=>false,'message'=>'Bus ID required']); break; }

        // Get speed limit for this bus
        $limitResult = $conn->query("SELECT speed_limit FROM buses WHERE id=$busId");
        $speedLimit  = $limitResult ? $limitResult->fetch_assoc()['speed_limit'] ?? 80 : 80;

        $isAlert = $speed > $speedLimit ? 1 : 0;
        $alertAt = $isAlert ? "NOW()" : "NULL";

        $stmt = $conn->prepare(
            "UPDATE buses SET current_speed=?, current_lat=?, current_lng=?,
             speed_alert=?, speed_alert_at=IF(?=1,NOW(),NULL), last_update=NOW() WHERE id=?"
        );
        $stmt->bind_param("dddiii", $speed, $lat, $lng, $isAlert, $isAlert, $busId);
        $stmt->execute();
        $stmt->close();

        $response = ['success'=>true,'speed'=>$speed,'limit'=>$speedLimit,'alert'=>(bool)$isAlert];
        if ($isAlert) {
            $response['message'] = "⚠️ SPEED ALERT: Bus {$busId} is doing {$speed} km/h (limit: {$speedLimit} km/h)";
            // Log notification for admin
            $msg = "Speed alert: Bus ID {$busId} exceeded limit ({$speed} km/h > {$speedLimit} km/h)";
            $conn->query("INSERT INTO notifications (user_id, title, message) SELECT id, 'Speed Alert', '$msg' FROM users WHERE user_type='admin' LIMIT 1");
        }
        echo json_encode($response);
        break;

    case 'alerts':
        // Get all buses currently over speed limit
        $result = $conn->query(
            "SELECT b.id, b.bus_number, b.current_speed, b.speed_limit, b.speed_alert_at,
             d.name as driver_name, r.route_name
             FROM buses b
             LEFT JOIN drivers d ON b.driver_id = d.id
             LEFT JOIN routes r ON b.route_id = r.id
             WHERE b.speed_alert = 1 AND b.status = 'active'
             ORDER BY b.current_speed DESC"
        );
        $alerts = [];
        while ($row = $result->fetch_assoc()) $alerts[] = $row;
        echo json_encode(['success'=>true,'alerts'=>$alerts,'count'=>count($alerts)]);
        break;

    case 'history':
        // Get speed history for a bus (from notifications)
        $busNumber = $db->sanitize($_GET['bus_number'] ?? '');
        $result = $conn->query(
            "SELECT * FROM notifications WHERE message LIKE '%{$busNumber}%' AND title='Speed Alert' ORDER BY created_at DESC LIMIT 50"
        );
        $history = [];
        while ($row = $result->fetch_assoc()) $history[] = $row;
        echo json_encode(['success'=>true,'history'=>$history]);
        break;

    case 'set_limit':
        $busId = (int)($_POST['bus_id'] ?? 0);
        $limit = (float)($_POST['speed_limit'] ?? 80);
        if (!$busId) { echo json_encode(['success'=>false,'message'=>'Bus ID required']); break; }
        $conn->query("UPDATE buses SET speed_limit=$limit WHERE id=$busId");
        echo json_encode(['success'=>true,'message'=>"Speed limit set to {$limit} km/h"]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>
