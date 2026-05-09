<?php
/**
 * Delay Notification Endpoint
 * Global Coaches Limited — CloudRoute
 */
require_once 'classes/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db   = Database::getInstance();
$conn = $db->getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'report':
        // Admin reports a bus is delayed
        $busNumber    = $db->sanitize($_POST['bus_number'] ?? '');
        $route        = $db->sanitize($_POST['route'] ?? '');
        $delayMinutes = (int)($_POST['delay_minutes'] ?? 0);
        $reason       = $db->sanitize($_POST['reason'] ?? 'Unspecified delay');

        if (!$busNumber || !$route || $delayMinutes <= 0) {
            echo json_encode(['success'=>false,'message'=>'Bus number, route and delay minutes required']); break;
        }

        // Insert delay alert
        $stmt = $conn->prepare(
            "INSERT INTO delay_alerts (bus_number, route, delay_minutes, reason, created_at)
             VALUES (?,?,?,?,NOW())"
        );
        $stmt->bind_param("ssis", $busNumber, $route, $delayMinutes, $reason);
        $stmt->execute();
        $alertId = $conn->insert_id;
        $stmt->close();

        // Notify all passengers on this bus today
        $today = date('Y-m-d');
        $passengersResult = $conn->query(
            "SELECT DISTINCT u.id, u.name FROM bookings bk
             JOIN users u ON bk.user_id = u.id
             WHERE bk.bus_number = '$busNumber'
             AND bk.date = '$today'
             AND bk.status = 'confirmed'
             AND bk.delay_notified = 0"
        );

        $notified = 0;
        $message = "Your bus {$busNumber} on route {$route} is delayed by {$delayMinutes} minutes. Reason: {$reason}";

        while ($passenger = $passengersResult->fetch_assoc()) {
            $title = "Bus Delay Alert";
            $notifStmt = $conn->prepare(
                "INSERT INTO notifications (user_id, title, message, created_at) VALUES (?,?,?,NOW())"
            );
            $notifStmt->bind_param("iss", $passenger['id'], $title, $message);
            $notifStmt->execute();
            $notifStmt->close();
            $notified++;
        }

        // Mark bookings as notified
        $conn->query(
            "UPDATE bookings SET delay_notified=1
             WHERE bus_number='$busNumber' AND date='$today' AND status='confirmed'"
        );

        // Update delay alert with notified count
        $conn->query("UPDATE delay_alerts SET notified_count=$notified WHERE id=$alertId");

        echo json_encode([
            'success'         => true,
            'message'         => "Delay reported. {$notified} passengers notified.",
            'alert_id'        => $alertId,
            'passengers_notified' => $notified
        ]);
        break;

    case 'resolve':
        $alertId = (int)($_GET['id'] ?? 0);
        if (!$alertId) { echo json_encode(['success'=>false,'message'=>'Alert ID required']); break; }
        $conn->query("UPDATE delay_alerts SET resolved=1, resolved_at=NOW() WHERE id=$alertId");
        echo json_encode(['success'=>true,'message'=>'Delay alert resolved']);
        break;

    case 'active':
        $result = $conn->query(
            "SELECT * FROM delay_alerts WHERE resolved=0 ORDER BY created_at DESC"
        );
        $alerts = [];
        while ($row = $result->fetch_assoc()) $alerts[] = $row;
        echo json_encode(['success'=>true,'alerts'=>$alerts,'count'=>count($alerts)]);
        break;

    case 'my_notifications':
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) { echo json_encode(['success'=>false,'message'=>'User ID required']); break; }
        $result = $conn->query(
            "SELECT * FROM notifications WHERE user_id=$userId ORDER BY created_at DESC LIMIT 20"
        );
        $notifications = [];
        while ($row = $result->fetch_assoc()) $notifications[] = $row;
        // Mark as read
        $conn->query("UPDATE notifications SET is_read=1 WHERE user_id=$userId");
        echo json_encode(['success'=>true,'notifications'=>$notifications]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>
