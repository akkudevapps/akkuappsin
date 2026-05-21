<?php
// api/add-comment.php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/economy.php';

// CORS headers for subdomain/local development
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? 'https://akkuapps.in'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$postId = $_POST['post_id'] ?? null;
$content = trim($_POST['content'] ?? '');

if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'Post ID required']);
    exit;
}

$result = commentOnPost($user['user_id'], $postId, $content);

if (!empty($result['success'])) {
    $updatedUser = getCurrentUser();
    echo json_encode([
        'success' => true,
        'message' => $result['message'] ?? 'Comment added.',
        'comment_id' => $result['comment_id'] ?? null,
        'new_balance' => $updatedUser['coin_balance'] ?? null
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'error' => $result['error'] ?? 'Unable to add comment'
]);
