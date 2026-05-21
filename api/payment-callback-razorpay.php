<?php
/**
 * Razorpay Payment Callback Handler
 * Webhook for Razorpay payment confirmations
 */

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/payment-engine.php';

header('Content-Type: application/json');

try {
    global $pdo;
    
    // Get webhook data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['payment']['id']) || !isset($data['payment']['order_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }
    
    // Log webhook for debugging
    error_log("Razorpay webhook received: " . json_encode($data));
    
    if ($data['event'] === 'payment.authorized') {
        // Payment successful
        $paymentData = [
            'order_id' => $data['payment']['order_id'],
            'payment_id' => $data['payment']['id'],
            'signature' => $data['payload']['payment']['signature'] ?? $data['payment']['signature'] ?? null
        ];
        
        $result = akkuAdProcessRazorpayCallback($pdo, $paymentData);
        
        if ($result['success']) {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Payment processed']);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
    } else {
        // Other webhook events (payment.failed, payment.captured, etc)
        error_log("Razorpay webhook event: " . $data['event']);
        echo json_encode(['success' => true, 'message' => 'Event acknowledged']);
    }
    
} catch (Exception $e) {
    error_log("Razorpay callback error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
