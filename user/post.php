<?php
// user/post.php - Single post view
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/economy.php';

// Check if user is logged in
$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$postId = $_GET['id'] ?? null;
if (!$postId) {
    header("Location: /user/feed.php");
    exit;
}

try {
    global $pdo;
    
    $postQuery = "
        SELECT p.*, u.name as author_name, u.avatar as author_avatar,
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
        WHERE p.post_id = ? AND p.status = 'active'
    ";
    $stmt = $pdo->prepare($postQuery);
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if (!$post) {
        header("Location: /user/feed.php");
        exit;
    }
    
    // Get comments
    $stmt = $pdo->prepare("
        SELECT pc.*, u.name as commenter_name, u.avatar as commenter_avatar
        FROM post_comments pc
        JOIN users u ON pc.user_id = u.user_id
        WHERE pc.post_id = ?
        ORDER BY pc.created_at ASC
    ");
    $stmt->execute([$postId]);
    $comments = $stmt->fetchAll();
    
    // Record view through the economy engine so rewards and treasury commissions stay in sync.
    recordPostView($postId, $user['user_id'], $_SERVER['REMOTE_ADDR'] ?? null);

    $stmt = $pdo->prepare($postQuery);
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

} catch (Exception $e) {
    error_log("Post view error: " . $e->getMessage());
    header("Location: /user/feed.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(substr($post['content'], 0, 50)) ?>... - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .post-card { border-radius: 12px; background: var(--card-bg); border: 1px solid var(--border-color); }
        .post-header { display: flex; align-items: center; padding: 15px; }
        .avatar { width: 40px; height: 40px; border-radius: 50%; margin-right: 10px; }
        .post-content { padding: 0 15px 15px 15px; }
        .post-stats { display: flex; justify-content: space-around; padding: 15px; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); background: var(--secondary-bg); }
        .stat-item { text-align: center; }
        .stat-number { font-weight: bold; color: var(--accent-color); }
        .stat-label { font-size: 0.8em; color: var(--text-secondary); }
        .post-actions { display: flex; gap: 20px; padding: 15px; }
        .action-btn { background: none; border: none; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; gap: 5px; padding: 8px 16px; border-radius: 6px; }
        .action-btn:hover { color: var(--accent-color); background: var(--secondary-bg); }
        .comment { background: var(--secondary-bg); border-radius: 8px; padding: 10px; margin-bottom: 10px; }
        .comment-header { display: flex; align-items: center; margin-bottom: 5px; }
        .comment-avatar { width: 30px; height: 30px; border-radius: 50%; margin-right: 8px; }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>

    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-shell">
                <div class="post-layout">
                    <a href="/user/feed.php" style="color: var(--accent-color); text-decoration: none; margin-bottom: 20px; display: inline-block;">
                        <i class="fas fa-arrow-left"></i> Back to Feed
                    </a>

                    <div class="post-card">
                        <div class="post-header">
                            <img src="<?= htmlspecialchars($post['author_avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                 alt="Avatar" class="avatar">
                            <div>
                                <strong style="color: var(--text-primary);"><?= htmlspecialchars($post['author_name']) ?></strong>
                                <div style="font-size: 0.85em; color: var(--text-secondary);">
                                    <?= date('M j, Y g:i A', strtotime($post['created_at'])) ?>
                                </div>
                            </div>
                        </div>

                        <div class="post-content">
                            <p style="color: var(--text-primary); line-height: 1.6;"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                        </div>

                        <div class="post-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?= $post['view_count'] ?></div>
                                <div class="stat-label">Views</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $post['like_count'] ?></div>
                                <div class="stat-label">Likes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $post['comment_count'] ?></div>
                                <div class="stat-label">Comments</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?= $post['repost_count'] ?></div>
                                <div class="stat-label">Reposts</div>
                            </div>
                        </div>

                        <div class="post-actions">
                            <button class="action-btn" onclick="likePost('<?= $post['post_id'] ?>')">
                                <i class="fas fa-heart"></i> Like
                            </button>
                            <button class="action-btn" onclick="scrollToComments()">
                                <i class="fas fa-comment"></i> Comment
                            </button>
                            <button class="action-btn" onclick="repostPost('<?= $post['post_id'] ?>')">
                                <i class="fas fa-retweet"></i> Repost
                            </button>
                            <button class="action-btn">
                                <i class="fas fa-share"></i> Share
                            </button>
                        </div>
                    </div>

                    <div id="comments" style="margin-top: 30px;">
                        <h3 style="color: var(--text-primary); margin-bottom: 20px;">Comments (<?= count($comments) ?>)</h3>

                        <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 20px;">
                            <form id="commentForm">
                                <input type="hidden" name="post_id" value="<?= $post['post_id'] ?>">
                                <textarea name="content" placeholder="Write a comment..." rows="3" 
                                          style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); margin-bottom: 10px;"></textarea>
                                <button type="submit" style="background: var(--accent-color); color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer;">
                                    <i class="fas fa-paper-plane"></i> Post Comment
                                </button>
                            </form>
                        </div>

                        <div id="commentsList">
                            <?php foreach ($comments as $comment): ?>
                                <div class="comment">
                                    <div class="comment-header">
                                        <img src="<?= htmlspecialchars($comment['commenter_avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                             alt="Avatar" class="comment-avatar">
                                        <div>
                                            <strong style="color: var(--text-primary);"><?= htmlspecialchars($comment['commenter_name']) ?></strong>
                                            <div style="font-size: 0.8em; color: var(--text-secondary);">
                                                <?= date('M j, Y g:i A', strtotime($comment['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="color: var(--text-primary); margin-left: 38px;">
                                        <?= nl2br(htmlspecialchars($comment['content'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
    <script>
        function likePost(postId) {
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
        
        function scrollToComments() {
            document.getElementById('comments').scrollIntoView({ behavior: 'smooth' });
            document.querySelector('textarea[name="content"]').focus();
        }
        
        function repostPost(postId) {
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
        
        // Handle comment form submission
        document.getElementById('commentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('/api/add-comment.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Comment added!');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        });
    </script>
</body>
</html>
