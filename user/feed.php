<?php
// user/feed.php - Fixed sidebar
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

try {
    global $pdo;
    
    // Get posts with proper deduplication and statistics
    $stmt = $pdo->prepare("
        SELECT DISTINCT p.post_id, p.*, u.name as author_name, u.avatar as author_avatar,
               COALESCE(like_stats.like_count, 0) as like_count,
               COALESCE(comment_stats.comment_count, 0) as comment_count,
               COALESCE(repost_stats.repost_count, 0) as repost_count,
               COALESCE(view_stats.view_count, 0) as view_count
        FROM user_posts p
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN (
            SELECT post_id, COUNT(*) as like_count 
            FROM post_likes 
            GROUP BY post_id
        ) like_stats ON p.post_id = like_stats.post_id
        LEFT JOIN (
            SELECT post_id, COUNT(*) as comment_count 
            FROM post_comments 
            GROUP BY post_id
        ) comment_stats ON p.post_id = comment_stats.post_id
        LEFT JOIN (
            SELECT post_id, COUNT(*) as repost_count 
            FROM post_reposts 
            GROUP BY post_id
        ) repost_stats ON p.post_id = repost_stats.post_id
        LEFT JOIN (
            SELECT post_id, COUNT(*) as view_count 
            FROM post_views 
            GROUP BY post_id
        ) view_stats ON p.post_id = view_stats.post_id
        WHERE p.status = 'active' AND p.visibility = 'public'
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $posts = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Feed error: " . $e->getMessage());
    $posts = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .feed-post-card { margin-bottom: 20px; border-radius: 12px; background: var(--card-bg); border: 1px solid var(--border-color); overflow: hidden; }
        .feed-post-header { display: flex; align-items: center; padding: 15px; gap: 10px; }
        .feed-avatar { width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0; }
        .feed-post-content { padding: 0 15px 15px 15px; }
        .feed-post-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 8px;
            padding: 12px 15px;
            border-top: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
            background: var(--secondary-bg);
        }
        .feed-stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 4px;
            min-width: 0;
        }
        .feed-stat-number { font-weight: 700; color: var(--accent-color); font-size: 1rem; line-height: 1; }
        .feed-stat-label { font-size: 0.8em; color: var(--text-secondary); line-height: 1.1; }
        .feed-post-actions { display: flex; gap: 10px; padding: 15px; flex-wrap: wrap; }
        .feed-action-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.92rem;
            transition: all 0.25s ease;
        }
        .feed-action-btn:hover { color: var(--accent-color); background: var(--secondary-bg); }
        @media (max-width: 640px) {
            .feed-post-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .feed-post-actions { gap: 8px; }
            .feed-action-btn { flex: 1 1 calc(50% - 8px); justify-content: center; }
        }
        .create-post-btn {
            background: var(--accent-color);
            color: white;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }
        .create-post-btn:hover {
            background: #5856d6;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-shell">
                <div class="feed-container">
                    <div class="page-head" style="margin-bottom: 1rem;">
                        <div class="page-head-copy">
                            <h1 style="color: var(--text-primary);">Public Feed</h1>
                            <p>Browse community posts without the left and right spacing gaps that were squeezing the mobile view.</p>
                        </div>
                        <a href="/user/create-post.php" class="create-post-btn">
                            <i class="fas fa-plus"></i> Create New Post (2 coins)
                        </a>
                    </div>

                    <?php if (empty($posts)): ?>
                        <div class="empty-state">
                            <h3 style="color: var(--text-primary); margin-bottom: .35rem;">No posts yet</h3>
                            <p>Be the first to share something amazing!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_unique($posts, SORT_REGULAR) as $post): ?>
                            <div class="feed-post-card">
                                <div class="feed-post-header">
                                    <img src="<?= htmlspecialchars($post['author_avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                         alt="Avatar" class="feed-avatar">
                                    <div>
                                        <strong style="color: var(--text-primary);"><?= htmlspecialchars($post['author_name']) ?></strong>
                                        <div style="font-size: 0.85em; color: var(--text-secondary);">
                                            <?= date('M j, Y g:i A', strtotime($post['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="feed-post-content">
                                    <p style="color: var(--text-primary); line-height: 1.6;"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                                </div>

                                <div class="feed-post-stats">
                                    <div class="feed-stat-item">
                                        <div class="feed-stat-number"><?= (int) $post['view_count'] ?></div>
                                        <div class="feed-stat-label">Views</div>
                                    </div>
                                    <div class="feed-stat-item">
                                        <div class="feed-stat-number"><?= (int) $post['like_count'] ?></div>
                                        <div class="feed-stat-label">Likes</div>
                                    </div>
                                    <div class="feed-stat-item">
                                        <div class="feed-stat-number"><?= (int) $post['comment_count'] ?></div>
                                        <div class="feed-stat-label">Comments</div>
                                    </div>
                                    <div class="feed-stat-item">
                                        <div class="feed-stat-number"><?= (int) $post['repost_count'] ?></div>
                                        <div class="feed-stat-label">Reposts</div>
                                    </div>
                                </div>

                                <div class="feed-post-actions">
                                    
                                    <button class="feed-action-btn" onclick="likePost('<?= $post['post_id'] ?>')">
                                        <i class="fas fa-heart"></i> Like
                                    </button>
                                    <button class="feed-action-btn" onclick="showComments('<?= $post['post_id'] ?>')">
                                        <i class="fas fa-comment"></i> Comment
                                    </button>
                                    <button class="feed-action-btn" onclick="repostPost('<?= $post['post_id'] ?>')">
                                        <i class="fas fa-retweet"></i> Repost
                                    </button>
                                    <a href="/user/post.php?id=<?= $post['post_id'] ?>" class="feed-action-btn">
                                    <div class="feed-stat-number"><?= (int) $post['view_count'] ?></div>
                                    <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
    <script>
        function likePost(postId) {
            // Implement like functionality
            fetch('/api/like-post.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'post_id=' + postId + '&action=like'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Post liked!');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
        
        function showComments(postId) {
            // Implement comment functionality
            window.location.href = '/user/post.php?id=' + postId + '#comments';
        }
        
        function repostPost(postId) {
            // Implement repost functionality
            if (confirm('Repost this post?')) {
                fetch('/api/repost-post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'post_id=' + postId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Post reposted!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.error);
                    }
                });
            }
        }
    </script>
</body>
</html>
