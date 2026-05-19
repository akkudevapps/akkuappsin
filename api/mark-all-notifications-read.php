<?php
header('Content-Type: application/json');
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/notifications.php';

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

try {
    markAllNotificationsAsRead($user['user_id']);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log("Mark all read error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
?>
