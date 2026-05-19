<?php
// user/group.php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$groupId = $_GET['group_id'] ?? null;
if (!$groupId) {
    header("Location: /user/groups.php");
    exit;
}

$message = '';
$error = '';

// Get group info
try {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT g.*, 
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id) as member_count,
               (SELECT COUNT(*) FROM group_posts WHERE group_id = g.group_id) as post_count,
               gm.role as user_role
        FROM groups g
        LEFT JOIN group_members gm ON g.group_id = gm.group_id AND gm.user_id = ?
        WHERE g.group_id = ?
    ");
    $stmt->execute([$user['user_id'], $groupId]);
    $group = $stmt->fetch();
    
    if (!$group) {
        $error = "Group not found";
    } else if ($group['is_private'] && !$group['user_role']) {
        $error = "This is a private group. You need to be a member to view it.";
    }
} catch (Exception $e) {
    $error = "Error loading group: " . $e->getMessage();
}

// Handle post creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_post'])) {
    if (!$group || ($group['is_private'] && !$group['user_role'])) {
        $error = "You don't have permission to post in this group";
    } else {
        $content = trim($_POST['content'] ?? '');
        
        if (empty($content)) {
            $error = "Post content cannot be empty";
        } else {
            try {
                $postId = generateUUID();
                $stmt = $pdo->prepare("
                    INSERT INTO group_posts 
                    (post_id, group_id, user_id, content, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$postId, $groupId, $user['user_id'], $content]);
                $message = "Post created successfully!";
                
                // Clear form
                $_POST['content'] = '';
            } catch (Exception $e) {
                $error = "Error creating post: " . $e->getMessage();
            }
        }
    }
}

// Get group posts
$posts = [];
if (!$error && $group) {
    try {
        $stmt = $pdo->prepare("
            SELECT gp.*, u.name as author_name, u.avatar as author_avatar
            FROM group_posts gp
            JOIN users u ON gp.user_id = u.user_id
            WHERE gp.group_id = ?
            ORDER BY gp.created_at DESC
            LIMIT 20
        ");
        $stmt->execute([$groupId]);
        $posts = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "Error loading posts: " . $e->getMessage();
    }
}

// Get group members
$members = [];
if (!$error && $group) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.name, u.avatar, gm.role, gm.joined_at
            FROM group_members gm
            JOIN users u ON gm.user_id = u.user_id
            WHERE gm.group_id = ?
            ORDER BY gm.role DESC, gm.joined_at ASC
        ");
        $stmt->execute([$groupId]);
        $members = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "Error loading members: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $group ? htmlspecialchars($group['name']) : 'Group' ?> - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .group-header {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            text-align: center;
        }
        .post-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .member-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
        }
        .member-card {
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
        }
        .member-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 10px auto;
        }
        .admin-badge {
            background: #f59e0b;
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7em;
            display: inline-block;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    <br><br>
                    <a href="/user/groups.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Groups
                    </a>
                </div>
            <?php elseif ($group): ?>
                <div class="welcome-banner">
                    <h1><?= htmlspecialchars($group['name']) ?></h1>
                    <p><?= $group['is_private'] ? 'Private Group' : 'Public Group' ?> • <?= $group['member_count'] ?> members</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <!-- Group Info -->
                <div class="group-header">
                    <?php if (!empty($group['description'])): ?>
                        <p style="color: var(--text-primary); font-size: 1.1em; max-width: 600px; margin: 0 auto;">
                            <?= htmlspecialchars($group['description']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <div style="margin-top: 20px; display: flex; justify-content: center; gap: 20px; flex-wrap: wrap;">
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; color: var(--accent-color);"><?= $group['member_count'] ?></div>
                            <div style="color: var(--text-secondary);">Members</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; color: var(--accent-color);"><?= $group['post_count'] ?></div>
                            <div style="color: var(--text-secondary);">Posts</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 1.5rem; color: var(--accent-color);">
                                <?= $group['user_role'] ? ucfirst($group['user_role']) : 'Not Member' ?>
                            </div>
                            <div style="color: var(--text-secondary);">Your Role</div>
                        </div>
                    </div>
                    
                    <?php if (!$group['user_role']): ?>
                        <form method="POST" style="margin-top: 20px;">
                            <input type="hidden" name="group_id" value="<?= $group['group_id'] ?>">
                            <button type="submit" name="join_group" class="btn btn-success">
                                <i class="fas fa-user-plus"></i> <?= $group['is_private'] ? 'Request to Join' : 'Join Group' ?>
                            </button>
                        </form>
                    <?php elseif ($group['user_role'] === 'admin'): ?>
                        <div style="margin-top: 20px;">
                            <a href="/user/group-settings.php?group_id=<?= $group['group_id'] ?>" class="btn btn-warning">
                                <i class="fas fa-cog"></i> Group Settings
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Create Post (for members) -->
                <?php if ($group['user_role']): ?>
                <div class="chart-container animate-slideUp">
                    <h2>Create Post</h2>
                    <form method="POST" style="margin-top: 20px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
                        <input type="hidden" name="create_post" value="1">
                        
                        <div style="margin-bottom: 20px;">
                            <textarea name="content" rows="4" placeholder="Share something with the group..." required
                                      style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);"></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Post to Group
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Group Posts -->
                <div class="chart-container animate-slideUp">
                    <h2>Group Posts</h2>
                    <div style="margin-top: 20px;">
                        <?php if (empty($posts)): ?>
                            <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                                No posts yet. Be the first to share something!
                            </p>
                        <?php else: ?>
                            <?php foreach ($posts as $post): ?>
                                <div class="post-card">
                                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                        <img src="<?= htmlspecialchars($post['author_avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                             alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 15px;">
                                        <div>
                                            <strong style="color: var(--text-primary);"><?= htmlspecialchars($post['author_name']) ?></strong>
                                            <div style="color: var(--text-secondary); font-size: 0.9em;">
                                                <?= date('M j, Y g:i A', strtotime($post['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div style="color: var(--text-primary); line-height: 1.6;">
                                        <?= nl2br(htmlspecialchars($post['content'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Group Members -->
                <div class="chart-container animate-slideUp">
                    <h2>Members (<?= count($members) ?>)</h2>
                    <div style="margin-top: 20px;">
                        <?php if (empty($members)): ?>
                            <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                                No members yet.
                            </p>
                        <?php else: ?>
                            <div class="member-list">
                                <?php foreach ($members as $member): ?>
                                    <div class="member-card">
                                        <img src="<?= htmlspecialchars($member['avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                             alt="Avatar" class="member-avatar">
                                        <div style="color: var(--text-primary); font-weight: bold;">
                                            <?= htmlspecialchars($member['name']) ?>
                                        </div>
                                        <?php if ($member['role'] === 'admin'): ?>
                                            <div class="admin-badge">Admin</div>
                                        <?php endif; ?>
                                        <div style="color: var(--text-secondary); font-size: 0.8em; margin-top: 5px;">
                                            Joined <?= date('M Y', strtotime($member['joined_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
