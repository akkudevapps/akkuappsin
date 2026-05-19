<?php
// user/followers.php - Followers/Following management
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$type = $_GET['type'] ?? 'followers'; // followers or following
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    global $pdo;
    
    if ($type === 'followers') {
        // Get followers
        $stmt = $pdo->prepare("
            SELECT u.*, uf.relationship_type, uf.status,
                   (SELECT COUNT(*) FROM user_follows uf2 WHERE uf2.follower_id = u.user_id AND uf2.following_id = ? AND uf2.status = 'accepted') as is_following_back
            FROM user_follows uf
            JOIN users u ON uf.follower_id = u.user_id
            WHERE uf.following_id = ? AND uf.status = 'accepted'
            ORDER BY uf.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user['user_id'], $user['user_id'], $limit, $offset]);
        $users = $stmt->fetchAll();
        
        // Get total count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE following_id = ? AND status = 'accepted'");
        $stmt->execute([$user['user_id']]);
        $totalCount = $stmt->fetchColumn();
    } else {
        // Get following
        $stmt = $pdo->prepare("
            SELECT u.*, uf.relationship_type, uf.status
            FROM user_follows uf
            JOIN users u ON uf.following_id = u.user_id
            WHERE uf.follower_id = ? AND uf.status = 'accepted'
            ORDER BY uf.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user['user_id'], $limit, $offset]);
        $users = $stmt->fetchAll();
        
        // Get total count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ? AND status = 'accepted'");
        $stmt->execute([$user['user_id']]);
        $totalCount = $stmt->fetchColumn();
    }
    
    $totalPages = ceil($totalCount / $limit);

} catch (Exception $e) {
    error_log("Followers error: " . $e->getMessage());
    $users = [];
    $totalCount = 0;
    $totalPages = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ucfirst($type) ?> - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .user-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin-right: 15px;
        }
        .user-info {
            flex: 1;
        }
        .user-name {
            color: var(--text-primary);
            font-weight: bold;
            margin: 0 0 5px 0;
        }
        .user-details {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        .user-actions {
            display: flex;
            gap: 10px;
        }
        .action-btn {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
        }
        .page-link {
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary);
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
        }
        .page-link.active {
            background: var(--accent-color);
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>

    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-shell">
                <div class="content-narrow">
                    <div class="page-head" style="margin-bottom: 1.5rem;">
                        <div class="page-head-copy">
                            <h1><?= ucfirst($type) ?> (<?= $totalCount ?>)</h1>
                            <p>Mobile-friendly connections management with the dashboard sidebar available on every screen.</p>
                        </div>
                        <div class="segment-links">
                            <a href="/user/followers.php?type=followers" class="segment-link <?= $type === 'followers' ? 'active' : '' ?>">Followers</a>
                            <a href="/user/followers.php?type=following" class="segment-link <?= $type === 'following' ? 'active' : '' ?>">Following</a>
                        </div>
                    </div>

                    <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <p>No <?= $type ?> yet.</p>
                            <?php if ($type === 'following'): ?>
                                <p><a href="/user/feed.php" style="color: var(--accent-color);">Discover users to follow</a></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <div class="user-card">
                                <img src="<?= htmlspecialchars($u['avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                     alt="Avatar" class="user-avatar">

                                <div class="user-info">
                                    <h3 class="user-name"><?= htmlspecialchars($u['name']) ?></h3>
                                    <div class="user-details">
                                        <?php if (!empty($u['bio'])): ?>
                                            <div><?= htmlspecialchars(substr($u['bio'], 0, 60)) ?><?= strlen($u['bio']) > 60 ? '...' : '' ?></div>
                                        <?php endif; ?>
                                        <div>
                                            <?php if ($type === 'followers' && $u['is_following_back']): ?>
                                                <span style="color: #10b981; margin-right: 10px;">
                                                    <i class="fas fa-user-friends"></i> Follows you
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($u['is_verified']): ?>
                                                <span style="color: #3b82f6;">
                                                    <i class="fas fa-check-circle"></i> Verified
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="user-actions">
                                    <a href="/user/profile.php?user_id=<?= $u['user_id'] ?>" class="action-btn btn-secondary">
                                        <i class="fas fa-user"></i> View
                                    </a>
                                    <?php if ($type === 'following'): ?>
                                        <form method="POST" action="/user/profile.php?user_id=<?= $u['user_id'] ?>" style="display: inline;">
                                            <button type="submit" name="unfollow" class="action-btn btn-danger">
                                                <i class="fas fa-user-times"></i> Unfollow
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <?php if (!$u['is_following_back']): ?>
                                            <form method="POST" action="/user/profile.php?user_id=<?= $u['user_id'] ?>" style="display: inline;">
                                                <button type="submit" name="follow" class="action-btn btn-primary">
                                                    <i class="fas fa-user-plus"></i> Follow Back
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="/user/followers.php?type=<?= $type ?>&page=<?= $page - 1 ?>" class="page-link">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="/user/followers.php?type=<?= $type ?>&page=<?= $i ?>" 
                                       class="page-link <?= $i == $page ? 'active' : '' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <a href="/user/followers.php?type=<?= $type ?>&page=<?= $page + 1 ?>" class="page-link">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
