<?php
/**
 * Ad Impression Tracking API
 * Tracks when ads are viewed by users
 */

define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/ad-engine.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    global $pdo;
    
    $adId = trim($_POST['ad_id'] ?? '');
    $placement = trim($_POST['placement'] ?? 'unknown');
    
    if (!$adId) {
        echo json_encode(['error' => 'Ad ID required']);
        exit;
    }
    
    // Record impression
    $result = akkuAdRecordImpression($pdo, $adId, $placement);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Impression recorded']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to record impression']);
    }
} catch (Exception $e) {
    error_log("Ad impression tracking error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
