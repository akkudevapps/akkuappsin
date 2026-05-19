<?php
// user/profile.php - Enhanced with relationships
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$profileUserId = $_GET['user_id'] ?? $user['user_id'];
$isOwnProfile = $profileUserId === $user['user_id'];

try {
    global $pdo;
    
    // Get user profile with enhanced info
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM user_posts WHERE user_id = u.user_id AND status = 'active') as post_count,
               (SELECT COUNT(*) FROM user_follows WHERE following_id = u.user_id AND status = 'accepted') as followers_count,
               (SELECT COUNT(*) FROM user_follows WHERE follower_id = u.user_id AND status = 'accepted') as following_count,
               (SELECT COUNT(*) FROM user_follows WHERE (follower_id = ? AND following_id = u.user_id) OR (follower_id = u.user_id AND following_id = ?) AND status = 'accepted') as is_friend
        FROM users u 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user['user_id'], $user['user_id'], $profileUserId]);
    $profileUser = $stmt->fetch();

    if (!$profileUser) {
        header("Location: /");
        exit;
    }

    // Get user's posts
    $stmt = $pdo->prepare("
        SELECT * FROM user_posts 
        WHERE user_id = ? AND status = 'active' 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$profileUserId]);
    $userPosts = $stmt->fetchAll();

    // Get relationship status
    $relationshipStatus = 'none';
    $friendshipId = null;
    if (!$isOwnProfile) {
        $stmt = $pdo->prepare("
            SELECT id, status, relationship_type FROM user_follows 
            WHERE (follower_id = ? AND following_id = ?) 
            OR (follower_id = ? AND following_id = ?)
        ");
        $stmt->execute([$user['user_id'], $profileUserId, $profileUserId, $user['user_id']]);
        $relationship = $stmt->fetch();
        if ($relationship) {
            $relationshipStatus = $relationship['status'];
            $friendshipId = $relationship['id'];
        }
    }

    // Handle relationship actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isOwnProfile) {
        if (isset($_POST['follow'])) {
            // Check if relationship already exists
            $stmt = $pdo->prepare("
                SELECT id FROM user_follows 
                WHERE follower_id = ? AND following_id = ?
            ");
            $stmt->execute([$user['user_id'], $profileUserId]);
            
            if (!$stmt->fetch()) {
                // Create new follow request
                $stmt = $pdo->prepare("
                    INSERT INTO user_follows (follower_id, following_id, status, relationship_type, created_at)
                    VALUES (?, ?, 'accepted', 'follower', NOW())
                ");
                $stmt->execute([$user['user_id'], $profileUserId]);
            }
        } elseif (isset($_POST['add_friend'])) {
            // Check if request already exists
            $stmt = $pdo->prepare("
                SELECT id FROM user_follows 
                WHERE follower_id = ? AND following_id = ?
            ");
            $stmt->execute([$user['user_id'], $profileUserId]);
            
            if (!$stmt->fetch()) {
                // Create new friend request
                $stmt = $pdo->prepare("
                    INSERT INTO user_follows (follower_id, following_id, status, relationship_type, created_at)
                    VALUES (?, ?, 'pending', 'friend', NOW())
                ");
                $stmt->execute([$user['user_id'], $profileUserId]);
            }
        } elseif (isset($_POST['accept_friend'])) {
            // Accept friend request
            $stmt = $pdo->prepare("
                UPDATE user_follows 
                SET status = 'accepted', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_POST['friendship_id']]);
        } elseif (isset($_POST['reject_friend'])) {
            // Reject friend request
            $stmt = $pdo->prepare("
                DELETE FROM user_follows 
                WHERE id = ?
            ");
            $stmt->execute([$_POST['friendship_id']]);
        } elseif (isset($_POST['block_user'])) {
            // Block user
            $stmt = $pdo->prepare("
                INSERT INTO user_follows (follower_id, following_id, status, relationship_type, created_at)
                VALUES (?, ?, 'blocked', 'friend', NOW())
                ON DUPLICATE KEY UPDATE status = 'blocked', updated_at = NOW()
            ");
            $stmt->execute([$user['user_id'], $profileUserId]);
        } elseif (isset($_POST['unblock_user'])) {
            // Unblock user
            $stmt = $pdo->prepare("
                DELETE FROM user_follows 
                WHERE follower_id = ? AND following_id = ? AND status = 'blocked'
            ");
            $stmt->execute([$user['user_id'], $profileUserId]);
        } elseif (isset($_POST['remove_friend'])) {
            // Remove friend
            $stmt = $pdo->prepare("
                DELETE FROM user_follows 
                WHERE id = ?
            ");
            $stmt->execute([$_POST['friendship_id']]);
        } elseif (isset($_POST['unfollow'])) {
            // Unfollow user
            $stmt = $pdo->prepare("
                DELETE FROM user_follows 
                WHERE follower_id = ? AND following_id = ? AND relationship_type = 'follower'
            ");
            $stmt->execute([$user['user_id'], $profileUserId]);
        }
        
        // Refresh relationship status
        $stmt = $pdo->prepare("
            SELECT id, status, relationship_type FROM user_follows 
            WHERE (follower_id = ? AND following_id = ?) 
            OR (follower_id = ? AND following_id = ?)
        ");
        $stmt->execute([$user['user_id'], $profileUserId, $profileUserId, $user['user_id']]);
        $relationship = $stmt->fetch();
        if ($relationship) {
            $relationshipStatus = $relationship['status'];
            $friendshipId = $relationship['id'];
        } else {
            $relationshipStatus = 'none';
            $friendshipId = null;
        }
    }

    // Generate invite code if own profile and doesn't exist
    if ($isOwnProfile && empty($profileUser['invite_code'])) {
        $inviteCode = 'INV' . strtoupper(substr(md5($user['user_id'] . time()), 0, 10));
        $pdo->prepare("UPDATE users SET invite_code = ? WHERE user_id = ?")
            ->execute([$inviteCode, $user['user_id']]);
        $profileUser['invite_code'] = $inviteCode;
    }

} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $profileUser = $user;
    $userPosts = [];
    $isOwnProfile = true;
    $relationshipStatus = 'none';
    $friendshipId = null;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profileUser['name']) ?>'s Profile - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .user-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.8em;
            margin: 0 5px;
            font-weight: bold;
        }
        .verified-badge {
            background: #3b82f6;
            color: white;
        }
        .premium-badge {
            background: linear-gradient(45deg, #f59e0b, #f97316);
            color: white;
        }
        .gold-badge {
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            color: black;
        }
        .platinum-badge {
            background: linear-gradient(45deg, #e5e7eb, #9ca3af);
            color: black;
        }
        .relationship-btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            margin: 5px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>

    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-shell">
                <div class="content-narrow">
        <!-- Profile Header -->
        <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; margin-bottom: 20px;">
            <div style="display: flex; align-items: flex-start; margin-bottom: 20px;">
                <img src="<?= htmlspecialchars($profileUser['avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                     alt="Avatar" style="width: 100px; height: 100px; border-radius: 50%; margin-right: 20px;">
                
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; flex-wrap: wrap;">
                        <h2 style="margin: 0 10px 0 0;"><?= htmlspecialchars($profileUser['name']) ?></h2>
                        
                        <!-- User Type Badges -->
                        <?php if ($profileUser['is_verified']): ?>
                            <span class="user-badge verified-badge">
                                <i class="fas fa-check"></i> Verified
                            </span>
                        <?php endif; ?>
                        
                        <?php if ($profileUser['user_type'] === 'premium'): ?>
                            <span class="user-badge premium-badge">
                                <i class="fas fa-crown"></i> Premium
                            </span>
                        <?php elseif ($profileUser['user_type'] === 'gold'): ?>
                            <span class="user-badge gold-badge">
                                <i class="fas fa-medal"></i> Gold
                            </span>
                        <?php elseif ($profileUser['user_type'] === 'platinum'): ?>
                            <span class="user-badge platinum-badge">
                                <i class="fas fa-gem"></i> Platinum
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <p style="color: var(--text-secondary); margin: 10px 0;">
                        <?= htmlspecialchars($profileUser['bio'] ?? 'No bio yet') ?>
                    </p>
                    
                    <div style="display: flex; flex-wrap: wrap; gap: 20px; margin: 20px 0;">
                        <div style="text-align: center;">
                            <strong style="font-size: 1.2em;"><?= $profileUser['post_count'] ?></strong>
                            <div style="color: var(--text-secondary); font-size: 0.9em;">Posts</div>
                        </div>
                        <div style="text-align: center;">
                            <strong style="font-size: 1.2em;"><?= $profileUser['followers_count'] ?></strong>
                            <div style="color: var(--text-secondary); font-size: 0.9em;">Followers</div>
                        </div>
                        <div style="text-align: center;">
                            <strong style="font-size: 1.2em;"><?= $profileUser['following_count'] ?></strong>
                            <div style="color: var(--text-secondary); font-size: 0.9em;">Following</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Relationship Actions -->
            <?php if (!$isOwnProfile): ?>
                <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 20px;">
                    <?php if ($relationshipStatus === 'none'): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="follow" class="relationship-btn btn-primary">
                                <i class="fas fa-user-plus"></i> Follow
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="add_friend" class="relationship-btn btn-success">
                                <i class="fas fa-user-friends"></i> Add Friend
                            </button>
                        </form>
                    <?php elseif ($relationshipStatus === 'pending'): ?>
                        <button disabled class="relationship-btn btn-secondary">
                            <i class="fas fa-clock"></i> Request Sent
                        </button>
                        <?php if ($friendshipId): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="friendship_id" value="<?= $friendshipId ?>">
                                <button type="submit" name="reject_friend" class="relationship-btn btn-danger"
                                        onclick="return confirm('Reject friend request?')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php elseif ($relationshipStatus === 'accepted'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="friendship_id" value="<?= $friendshipId ?>">
                            <button type="submit" name="remove_friend" class="relationship-btn btn-danger"
                                    onclick="return confirm('Remove friend?')">
                                <i class="fas fa-user-minus"></i> Remove Friend
                            </button>
                        </form>
                        <a href="/user/messages.php?user_id=<?= $profileUserId ?>" class="relationship-btn btn-success">
                            <i class="fas fa-comment"></i> Message
                        </a>
                    <?php elseif ($relationshipStatus === 'blocked'): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="unblock_user" class="relationship-btn btn-warning">
                                <i class="fas fa-unlock"></i> Unblock
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="unfollow" class="relationship-btn btn-secondary">
                                <i class="fas fa-user-times"></i> Unfollow
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($relationshipStatus !== 'blocked' && !$isOwnProfile): ?>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="block_user" class="relationship-btn btn-danger"
                                    onclick="return confirm('Block this user? They won\'t be able to interact with you.')">
                                <i class="fas fa-ban"></i> Block
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- Own Profile Actions -->
                <div style="margin-top: 20px;">
                    <a href="/user/settings.php" class="relationship-btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    
                    <?php if (!empty($profileUser['invite_code'])): ?>
                        <div style="margin-top: 15px; padding: 15px; background: var(--secondary-bg); border-radius: 8px;">
                            <h4 style="margin: 0 0 10px 0; color: var(--text-primary);">Invite Friends</h4>
                            <p style="color: var(--text-secondary); margin: 5px 0;">
                                Share your invite code: <strong><?= htmlspecialchars($profileUser['invite_code']) ?></strong>
                            </p>
                            <p style="color: var(--text-secondary); margin: 5px 0; font-size: 0.9em;">
                                Or share this link: <a href="<?= SITE_URL ?>/auth/register.php?invite=<?= htmlspecialchars($profileUser['invite_code']) ?>" 
                                                       style="color: var(--accent-color);" target="_blank">
                                    <?= SITE_URL ?>/auth/register.php?invite=<?= htmlspecialchars($profileUser['invite_code']) ?>
                                </a>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- User Posts -->
        <h3 style="margin-bottom: 15px;">Posts</h3>
        
        <?php if (empty($userPosts)): ?>
            <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; text-align: center; color: var(--text-secondary);">
                <p>No posts yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($userPosts as $post): ?>
                <div style="background: var(--card-bg); border-radius: 12px; padding: 20px; margin-bottom: 15px;">
                    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                    <small style="color: var(--text-secondary);">
                        <?= date('M j, Y', strtotime($post['created_at'])) ?>
                    </small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
