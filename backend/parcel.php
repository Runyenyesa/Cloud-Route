<?php
/**
 * Parcel Management Endpoint
 * Global Coaches Limited — CloudRoute
 */
require_once 'classes/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db = Database::getInstance();
$conn = $db->getConnection();
$action = $_GET['action'] ?? '';

switch ($action) {

    case 'book':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required']); break;
        }
        $parcelId   = 'PCL-' . strtoupper(substr(uniqid(), -6));
        $collectCode = strtoupper(substr(md5(uniqid()), 0, 6));

        $senderName    = $db->sanitize($_POST['sender_name'] ?? '');
        $senderPhone   = $db->sanitize($_POST['sender_phone'] ?? '');
        $recipientName = $db->sanitize($_POST['recipient_name'] ?? '');
        $recipientPhone= $db->sanitize($_POST['recipient_phone'] ?? '');
        $route         = $db->sanitize($_POST['route'] ?? '');
        $busNumber     = $db->sanitize($_POST['bus_number'] ?? '');
        $date          = $db->sanitize($_POST['date'] ?? '');
        $description   = $db->sanitize($_POST['description'] ?? '');
        $weight        = (float)($_POST['weight_kg'] ?? 0);
        $payMethod     = $db->sanitize($_POST['payment_method'] ?? 'cash');

        // Price: 2000 UGX per kg, minimum 3000
        $price = max(3000, $weight * 2000);

        if (!$senderName || !$recipientName || !$route || !$date || !$description) {
            echo json_encode(['success' => false, 'message' => 'All required fields must be filled']); break;
        }

        $stmt = $conn->prepare(
            "INSERT INTO parcels (parcel_id, sender_name, sender_phone, recipient_name,
             recipient_phone, route, bus_number, date, description, weight_kg, price,
             collection_code, payment_method, status, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'booked',NOW())"
        );
        $stmt->bind_param("sssssssssddss",
            $parcelId, $senderName, $senderPhone, $recipientName,
            $recipientPhone, $route, $busNumber, $date, $description,
            $weight, $price, $collectCode, $payMethod
        );
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Parcel booked successfully',
                'parcel' => [
                    'id'               => $parcelId,
                    'collection_code'  => $collectCode,
                    'price'            => number_format($price, 0) . ' UGX',
                    'recipient'        => $recipientName,
                    'route'            => $route,
                    'date'             => $date,
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to book parcel']);
        }
        $stmt->close();
        break;

    case 'track':
        $parcelId = $db->sanitize($_GET['parcel_id'] ?? '');
        if (!$parcelId) { echo json_encode(['success'=>false,'message'=>'Parcel ID required']); break; }
        $stmt = $conn->prepare("SELECT * FROM parcels WHERE parcel_id = ?");
        $stmt->bind_param("s", $parcelId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $parcel = $result->fetch_assoc();
            unset($parcel['collection_code']); // don't expose to tracker
            echo json_encode(['success' => true, 'parcel' => $parcel]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Parcel not found']);
        }
        $stmt->close();
        break;

    case 'verify_collection':
        $parcelId    = $db->sanitize($_POST['parcel_id'] ?? '');
        $collectCode = strtoupper($db->sanitize($_POST['collection_code'] ?? ''));
        if (!$parcelId || !$collectCode) {
            echo json_encode(['success'=>false,'message'=>'Parcel ID and collection code required']); break;
        }
        $stmt = $conn->prepare("SELECT * FROM parcels WHERE parcel_id = ? AND collection_code = ?");
        $stmt->bind_param("ss", $parcelId, $collectCode);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $parcel = $result->fetch_assoc();
            if ($parcel['status'] === 'collected') {
                echo json_encode(['success'=>false,'message'=>'Parcel already collected']);
            } else {
                $upd = $conn->prepare("UPDATE parcels SET status='collected', collected_at=NOW() WHERE parcel_id=?");
                $upd->bind_param("s", $parcelId);
                $upd->execute();
                $upd->close();
                echo json_encode(['success'=>true,'message'=>'Parcel collected successfully','parcel'=>$parcel]);
            }
        } else {
            echo json_encode(['success'=>false,'message'=>'Invalid parcel ID or collection code']);
        }
        $stmt->close();
        break;

    case 'list':
        $status = $db->sanitize($_GET['status'] ?? '');
        $sql = "SELECT * FROM parcels";
        if ($status) $sql .= " WHERE status = '$status'";
        $sql .= " ORDER BY created_at DESC";
        $result = $conn->query($sql);
        $parcels = [];
        while ($row = $result->fetch_assoc()) $parcels[] = $row;
        echo json_encode(['success' => true, 'parcels' => $parcels]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>
