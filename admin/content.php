<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit;
}

// Handle moderation actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_post'])) {
        $postId = $_POST['post_id'];
        $pdo->prepare("UPDATE user_posts SET status = 'deleted' WHERE post_id = ?")->execute([$postId]);
    } elseif (isset($_POST['restore_post'])) {
        $postId = $_POST['post_id'];
        $pdo->prepare("UPDATE user_posts SET status = 'active' WHERE post_id = ?")->execute([$postId]);
    } elseif (isset($_POST['flag_post'])) {
        $postId = $_POST['post_id'];
        $reason = $_POST['reason'] ?? 'Inappropriate content';
        // Add to flagged posts table (you may need to create this)
        $stmt = $pdo->prepare("
            INSERT INTO flagged_content (content_id, content_type, reason, flagged_by, created_at) 
            VALUES (?, 'post', ?, ?, NOW())
            ON DUPLICATE KEY UPDATE reason = VALUES(reason), flagged_at = NOW()
        ");
        $stmt->execute([$postId, $reason, $user['user_id']]);
    }
}

// Get flagged content
$flaggedContent = [];
try {
    $stmt = $pdo->prepare("
        SELECT fc.*, p.content as post_content, u.name as author_name, u.user_id as author_id
        FROM flagged_content fc
        JOIN user_posts p ON fc.content_id = p.post_id
        JOIN users u ON p.user_id = u.user_id
        WHERE fc.content_type = 'post'
        ORDER BY fc.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $flaggedContent = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist, create it or handle gracefully
    $flaggedContent = [];
}

// Get recent posts for general review
$stmt = $pdo->prepare("
    SELECT p.*, u.name as author_name, u.avatar as author_avatar,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count
    FROM user_posts p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.status = 'active'
    ORDER BY p.created_at DESC
    LIMIT 20
");
$stmt->execute();
$recentPosts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Moderation - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
    <?php include '../components/admin-header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/admin-sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Content Moderation</h1>
                <p>Review and manage platform content</p>
            </div>

            <!-- Flagged Content Section -->
            <?php if (!empty($flaggedContent)): ?>
            <div class="chart-container animate-slideUp">
                <h2>Flagged Content <span style="font-size: 0.8em; color: var(--accent-color);">(<?= count($flaggedContent) ?> items)</span></h2>
                <div style="margin-top: 20px;">
                    <?php foreach ($flaggedContent as $content): ?>
                        <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                <div style="display: flex; align-items: center;">
                                    <img src="<?= htmlspecialchars($content['author_avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                         alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                    <div>
                                        <strong style="color: var(--text-primary);"><?= htmlspecialchars($content['author_name']) ?></strong>
                                        <div style="font-size: 0.8em; color: var(--text-secondary);">
                                            Flagged: <?= date('M j, Y g:i A', strtotime($content['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="background: #f59e0b; color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.8em;">
                                    <?= htmlspecialchars($content['reason']) ?>
                                </div>
                            </div>
                            
                            <div style="margin: 15px 0; color: var(--text-primary); line-height: 1.6;">
                                <?= nl2br(htmlspecialchars($content['post_content'])) ?>
                            </div>
                            
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="post_id" value="<?= $content['content_id'] ?>">
                                    <button type="submit" name="delete_post" 
                                            style="background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="post_id" value="<?= $content['content_id'] ?>">
                                    <button type="submit" name="restore_post" 
                                            style="background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                        <i class="fas fa-undo"></i> Restore
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Posts for Review -->
            <div class="chart-container animate-slideUp">
                <h2>Recent Posts for Review</h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($recentPosts)): ?>
                        <p style="color: var(--text-secondary); text-align: center;">No recent posts</p>
                    <?php else: ?>
                        <?php foreach ($recentPosts as $post): ?>
                            <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <div style="display: flex; align-items: center;">
                                        <img src="<?= htmlspecialchars($post['author_avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                             alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                        <div>
                                            <strong style="color: var(--text-primary);"><?= htmlspecialchars($post['author_name']) ?></strong>
                                            <div style="font-size: 0.8em; color: var(--text-secondary);">
                                                <?= date('M j, Y g:i A', strtotime($post['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="color: var(--text-secondary);">
                                        <i class="fas fa-heart"></i> <?= $post['like_count'] ?>
                                    </div>
                                </div>
                                
                                <div style="margin: 15px 0; color: var(--text-primary); line-height: 1.6;">
                                    <?= nl2br(htmlspecialchars($post['content'])) ?>
                                </div>
                                
                                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                        <input type="hidden" name="reason" value="Inappropriate content">
                                        <button type="submit" name="flag_post" 
                                                style="background: #f59e0b; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-flag"></i> Flag
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                        <button type="submit" name="delete_post" 
                                                style="background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 5px;">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </form>
                                </div>
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
