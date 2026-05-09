<?php
/**
 * Payment Endpoint — MTN MoMo & Airtel Money
 * Global Coaches Limited — CloudRoute
 */
require_once 'classes/Database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db   = Database::getInstance();
$conn = $db->getConnection();
$action = $_GET['action'] ?? '';

// Route prices (UGX)
$routePrices = [
    'mbarara-kampala' => 15000,
    'kampala-mbarara' => 15000,
    'mbarara-kabale'  => 8000,
    'kabale-mbarara'  => 8000,
    'mbarara-masaka'  => 10000,
    'masaka-mbarara'  => 10000,
];

switch ($action) {

    case 'get_price':
        $route = $_GET['route'] ?? '';
        $price = $routePrices[$route] ?? 5000;
        echo json_encode(['success' => true, 'price' => $price,
                          'formatted' => number_format($price, 0) . ' UGX']);
        break;

    case 'initiate':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'POST required']); break;
        }
        $referenceId   = $db->sanitize($_POST['reference_id'] ?? '');
        $referenceType = $db->sanitize($_POST['reference_type'] ?? 'booking');
        $method        = $db->sanitize($_POST['method'] ?? 'mtn_momo');
        $phone         = $db->sanitize($_POST['phone'] ?? '');
        $amount        = (float)($_POST['amount'] ?? 0);

        if (!$referenceId || !$phone || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Reference ID, phone and amount required']); break;
        }

        // Validate Uganda phone
        if (!preg_match('/^(07[0-9]{8}|256[0-9]{9})$/', $phone)) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid Uganda phone number (07XXXXXXXX)']); break;
        }

        $paymentId = 'PAY-' . strtoupper(substr(uniqid(), -8));

        // In production: call MTN MoMo API or Airtel Money API here
        // For now we simulate initiation and mark as pending
        $stmt = $conn->prepare(
            "INSERT INTO payments (payment_id, reference_id, reference_type, amount, method, phone_number, status, created_at)
             VALUES (?,?,?,?,?,?,'pending',NOW())"
        );
        $stmt->bind_param("sssds s", $paymentId, $referenceId, $referenceType, $amount, $method, $phone);
        $stmt->bind_param("sssdss", $paymentId, $referenceId, $referenceType, $amount, $method, $phone);

        if ($stmt->execute()) {
            $methodLabel = $method === 'mtn_momo' ? 'MTN Mobile Money' : 'Airtel Money';
            echo json_encode([
                'success'    => true,
                'message'    => "Payment request sent to {$phone} via {$methodLabel}. Please approve on your phone.",
                'payment_id' => $paymentId,
                'status'     => 'pending',
                'instructions' => "You will receive a prompt on {$phone}. Enter your {$methodLabel} PIN to complete payment of " . number_format($amount, 0) . " UGX."
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to initiate payment']);
        }
        $stmt->close();
        break;

    case 'confirm':
        // Called after user approves on phone — in production webhook from MoMo API
        $paymentId  = $db->sanitize($_POST['payment_id'] ?? '');
        $txCode     = $db->sanitize($_POST['transaction_code'] ?? 'TXN-' . strtoupper(substr(uniqid(), 0, 8)));

        if (!$paymentId) { echo json_encode(['success'=>false,'message'=>'Payment ID required']); break; }

        $stmt = $conn->prepare(
            "UPDATE payments SET status='completed', transaction_code=?, paid_at=NOW() WHERE payment_id=?"
        );
        $stmt->bind_param("ss", $txCode, $paymentId);
        $stmt->execute();

        // Also update booking/parcel payment status
        $pay = $conn->query("SELECT * FROM payments WHERE payment_id='$paymentId'")->fetch_assoc();
        if ($pay) {
            if ($pay['reference_type'] === 'booking') {
                $conn->query("UPDATE bookings SET payment_status='paid', payment_method='{$pay['method']}' WHERE booking_id='{$pay['reference_id']}'");
            } else {
                $conn->query("UPDATE parcels SET payment_status='paid', payment_method='{$pay['method']}' WHERE parcel_id='{$pay['reference_id']}'");
            }
        }
        echo json_encode(['success'=>true,'message'=>'Payment confirmed','transaction_code'=>$txCode]);
        $stmt->close();
        break;

    case 'status':
        $paymentId = $db->sanitize($_GET['payment_id'] ?? '');
        if (!$paymentId) { echo json_encode(['success'=>false,'message'=>'Payment ID required']); break; }
        $result = $conn->query("SELECT * FROM payments WHERE payment_id='$paymentId'");
        if ($result->num_rows > 0) {
            echo json_encode(['success'=>true,'payment'=>$result->fetch_assoc()]);
        } else {
            echo json_encode(['success'=>false,'message'=>'Payment not found']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>
