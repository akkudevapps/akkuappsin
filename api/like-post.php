<?php
// api/like-post.php - Fixed version
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/economy.php';

header('Content-Type: application/json');

$user = getCurrentUser();
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$postId = $_POST['post_id'] ?? null;
$action = $_POST['action'] ?? 'like'; // like or unlike

if (!$postId) {
    echo json_encode(['success' => false, 'error' => 'Post ID required']);
    exit;
}

try {
    if ($action === 'like') {
        // Like post (costs 2 coins, gives 1 to creator, 1 to platform)
        $result = likePost($postId, $user['user_id']);
        if ($result) {
            // Refresh user data to get updated balance
            $updatedUser = getCurrentUser();
            echo json_encode([
                'success' => true, 
                'message' => 'Liked! 2 coins spent, creator rewarded 1 coin.',
                'new_balance' => $updatedUser['coin_balance']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to like post']);
        }
    } elseif ($action === 'unlike') {
        // Unlike post
        $result = unlikePost($user['user_id'], $postId);
        if ($result['success']) {
            echo json_encode(['success' => true, 'message' => $result['message']]);
        } else {
            echo json_encode(['success' => false, 'error' => $result['error']]);
        }
    }
} catch (Exception $e) {
    error_log("Like error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
?>
