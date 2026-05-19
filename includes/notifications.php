<?php
if (!defined('AKKUAPPS_LOADED')) { exit('Direct access not allowed'); }

global $pdo;
if (!$pdo) { require_once __DIR__ . '/config.php'; }

if (!function_exists('createNotification')) {
    function createNotification($userId, $type, $title, $message = null, $referenceId = null, $referenceType = null) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO notifications 
                (notification_id, user_id, type, title, message, reference_id, reference_type, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                generateUUID(),
                $userId,
                $type,
                $title,
                $message,
                $referenceId,
                $referenceType
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Notification creation error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getUserNotifications')) {
    function getUserNotifications($userId, $limit = 20) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM notifications 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ");
            $stmt->execute([$userId, $limit]);
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('markNotificationAsRead')) {
    function markNotificationAsRead($notificationId, $userId) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE notification_id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $userId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Mark notification read error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('markAllNotificationsAsRead')) {
    function markAllNotificationsAsRead($userId) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);
            return true;
        } catch (Exception $e) {
            error_log("Mark all notifications read error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('getUnreadNotificationsCount')) {
    function getUnreadNotificationsCount($userId) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Get unread count error: " . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('notifyPostLiked')) {
    function notifyPostLiked($postId, $likerId, $postOwnerId) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $stmt->execute([$likerId]);
        $likerName = $stmt->fetchColumn();
        
        createNotification(
            $postOwnerId,
            'like',
            'New Like',
            $likerName . ' liked your post',
            $postId,
            'post'
        );
    }
}

if (!function_exists('notifyPostCommented')) {
    function notifyPostCommented($postId, $commenterId, $postOwnerId) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $stmt->execute([$commenterId]);
        $commenterName = $stmt->fetchColumn();
        
        createNotification(
            $postOwnerId,
            'comment',
            'New Comment',
            $commenterName . ' commented on your post',
            $postId,
            'post'
        );
    }
}

if (!function_exists('notifyUserFollowed')) {
    function notifyUserFollowed($followerId, $followedId) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $stmt->execute([$followerId]);
        $followerName = $stmt->fetchColumn();
        
        createNotification(
            $followedId,
            'follow',
            'New Follower',
            $followerName . ' started following you',
            $followerId,
            'user'
        );
    }
}
?>
