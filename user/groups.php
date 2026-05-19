<?php
// user/groups.php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$message = '';
$error = '';

// Handle group actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $pdo;
    
    if (isset($_POST['create_group'])) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isPrivate = isset($_POST['is_private']) ? 1 : 0;
        
        if (empty($name) || strlen($name) < 3) {
            $error = "Group name must be at least 3 characters";
        } else {
            try {
                $groupId = generateUUID();
                $stmt = $pdo->prepare("
                    INSERT INTO groups 
                    (group_id, name, description, created_by, is_private, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$groupId, $name, $description, $user['user_id'], $isPrivate]);
                
                // Add creator as admin
                $stmt = $pdo->prepare("
                    INSERT INTO group_members 
                    (group_id, user_id, role, joined_at) 
                    VALUES (?, ?, 'admin', NOW())
                ");
                $stmt->execute([$groupId, $user['user_id']]);
                
                $message = "Group created successfully!";
            } catch (Exception $e) {
                $error = "Error creating group: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['join_group'])) {
        $groupId = $_POST['group_id'];
        
        try {
            // Check if already member
            $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
            $stmt->execute([$groupId, $user['user_id']]);
            if ($stmt->fetch()) {
                $error = "You are already a member of this group";
            } else {
                // Check if group is private
                $stmt = $pdo->prepare("SELECT is_private FROM groups WHERE group_id = ?");
                $stmt->execute([$groupId]);
                $group = $stmt->fetch();
                
                if ($group && $group['is_private']) {
                    // For private groups, send join request
                    $stmt = $pdo->prepare("
                        INSERT INTO group_join_requests 
                        (group_id, user_id, requested_at) 
                        VALUES (?, ?, NOW())
                    ");
                    $stmt->execute([$groupId, $user['user_id']]);
                    $message = "Join request sent. Waiting for approval.";
                } else {
                    // For public groups, join immediately
                    $stmt = $pdo->prepare("
                        INSERT INTO group_members 
                        (group_id, user_id, role, joined_at) 
                        VALUES (?, ?, 'member', NOW())
                    ");
                    $stmt->execute([$groupId, $user['user_id']]);
                    $message = "Joined group successfully!";
                }
            }
        } catch (Exception $e) {
            $error = "Error joining group: " . $e->getMessage();
        }
    } elseif (isset($_POST['leave_group'])) {
        $groupId = $_POST['group_id'];
        
        try {
            // Check if admin (can't leave if only admin)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as admin_count FROM group_members 
                WHERE group_id = ? AND role = 'admin' AND user_id != ?
            ");
            $stmt->execute([$groupId, $user['user_id']]);
            $adminCount = $stmt->fetchColumn();
            
            if ($adminCount == 0) {
                $error = "You cannot leave as you are the only admin. Please assign another admin first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
                $stmt->execute([$groupId, $user['user_id']]);
                $message = "Left group successfully!";
            }
        } catch (Exception $e) {
            $error = "Error leaving group: " . $e->getMessage();
        }
    }
}

// Get user's groups
try {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT g.*, gm.role as user_role,
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id) as member_count
        FROM groups g
        JOIN group_members gm ON g.group_id = gm.group_id
        WHERE gm.user_id = ?
        ORDER BY g.created_at DESC
    ");
    $stmt->execute([$user['user_id']]);
    $userGroups = $stmt->fetchAll();
} catch (Exception $e) {
    $userGroups = [];
}

// Get public groups
try {
    $stmt = $pdo->prepare("
        SELECT g.*, 
               (SELECT COUNT(*) FROM group_members WHERE group_id = g.group_id) as member_count,
               (SELECT COUNT(*) FROM group_posts WHERE group_id = g.group_id) as post_count
        FROM groups g
        WHERE g.is_private = 0
        ORDER BY g.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $publicGroups = $stmt->fetchAll();
} catch (Exception $e) {
    $publicGroups = [];
}

// Get pending join requests (for group admins)
$pendingRequests = [];
if (!empty($userGroups)) {
    $adminGroupIds = [];
    foreach ($userGroups as $group) {
        if ($group['user_role'] === 'admin') {
            $adminGroupIds[] = $group['group_id'];
        }
    }
    
    if (!empty($adminGroupIds)) {
        $placeholders = str_repeat('?,', count($adminGroupIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT gjr.*, u.name as user_name, u.avatar as user_avatar, g.name as group_name
            FROM group_join_requests gjr
            JOIN users u ON gjr.user_id = u.user_id
            JOIN groups g ON gjr.group_id = g.group_id
            WHERE gjr.group_id IN ($placeholders)
            ORDER BY gjr.requested_at DESC
        ");
        $stmt->execute($adminGroupIds);
        $pendingRequests = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Groups - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .group-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .group-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .group-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-right: 15px;
        }
        .group-info {
            flex: 1;
        }
        .group-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }
        .btn {
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
        .btn-warning { background: #f59e0b; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Groups & Communities</h1>
                <p>Join or create groups to connect with like-minded people</p>
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

            <!-- Create Group Form -->
            <div class="chart-container animate-slideUp">
                <h2>Create New Group</h2>
                <form method="POST" style="margin-top: 20px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
                    <input type="hidden" name="create_group" value="1">
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Group Name</label>
                        <input type="text" name="name" required maxlength="50"
                               style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Description</label>
                        <textarea name="description" rows="3" maxlength="500"
                                  style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);"></textarea>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; color: var(--text-primary);">
                            <input type="checkbox" name="is_private" value="1" style="margin-right: 10px;">
                            Private Group (requires approval to join)
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Group
                    </button>
                </form>
            </div>

            <!-- Pending Requests (for admins) -->
            <?php if (!empty($pendingRequests)): ?>
            <div class="chart-container animate-slideUp">
                <h2>Pending Join Requests</h2>
                <div style="margin-top: 20px;">
                    <?php foreach ($pendingRequests as $request): ?>
                        <div class="group-card">
                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                <img src="<?= htmlspecialchars($request['user_avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                     alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 15px;">
                                <div>
                                    <strong style="color: var(--text-primary);"><?= htmlspecialchars($request['user_name']) ?></strong>
                                    <div style="color: var(--text-secondary); font-size: 0.9em;">
                                        Requested to join <?= htmlspecialchars($request['group_name']) ?>
                                    </div>
                                </div>
                                <div style="color: var(--text-secondary); margin-left: auto; font-size: 0.9em;">
                                    <?= date('M j, Y g:i A', strtotime($request['requested_at'])) ?>
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                    <input type="hidden" name="user_id" value="<?= $request['user_id'] ?>">
                                    <input type="hidden" name="group_id" value="<?= $request['group_id'] ?>">
                                    <button type="submit" name="approve_request" class="btn btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="request_id" value="<?= $request['request_id'] ?>">
                                    <button type="submit" name="reject_request" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- My Groups -->
            <?php if (!empty($userGroups)): ?>
            <div class="chart-container animate-slideUp">
                <h2>My Groups</h2>
                <div style="margin-top: 20px;">
                    <?php foreach ($userGroups as $group): ?>
                        <div class="group-card">
                            <div class="group-header">
                                <div class="group-avatar">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="group-info">
                                    <h3 style="color: var(--text-primary); margin: 0 0 5px 0;">
                                        <?= htmlspecialchars($group['name']) ?>
                                        <?php if ($group['is_private']): ?>
                                            <span style="background: #f59e0b; color: white; padding: 3px 8px; border-radius: 20px; font-size: 0.7em; margin-left: 10px;">
                                                Private
                                            </span>
                                        <?php endif; ?>
                                    </h3>
                                    <div style="color: var(--text-secondary); font-size: 0.9em;">
                                        <?= $group['member_count'] ?> members • 
                                        <?= $group['user_role'] === 'admin' ? 'Admin' : 'Member' ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($group['description'])): ?>
                                <p style="color: var(--text-primary); margin: 15px 0;">
                                    <?= htmlspecialchars($group['description']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="group-actions">
                                <a href="/user/group.php?group_id=<?= $group['group_id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-eye"></i> View Group
                                </a>
                                <?php if ($group['user_role'] === 'admin'): ?>
                                    <a href="/user/group-settings.php?group_id=<?= $group['group_id'] ?>" class="btn btn-warning">
                                        <i class="fas fa-cog"></i> Settings
                                    </a>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="group_id" value="<?= $group['group_id'] ?>">
                                    <button type="submit" name="leave_group" class="btn btn-danger"
                                            onclick="return confirm('Are you sure you want to leave this group?')">
                                        <i class="fas fa-sign-out-alt"></i> Leave Group
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Public Groups -->
            <div class="chart-container animate-slideUp">
                <h2>Public Groups</h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($publicGroups)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                            No public groups available.
                        </p>
                    <?php else: ?>
                        <?php foreach ($publicGroups as $group): ?>
                            <div class="group-card">
                                <div class="group-header">
                                    <div class="group-avatar">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="group-info">
                                        <h3 style="color: var(--text-primary); margin: 0 0 5px 0;">
                                            <?= htmlspecialchars($group['name']) ?>
                                        </h3>
                                        <div style="color: var(--text-secondary); font-size: 0.9em;">
                                            <?= $group['member_count'] ?> members • <?= $group['post_count'] ?> posts
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if (!empty($group['description'])): ?>
                                    <p style="color: var(--text-primary); margin: 15px 0;">
                                        <?= htmlspecialchars(substr($group['description'], 0, 150)) ?><?= strlen($group['description']) > 150 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="group-actions">
                                    <a href="/user/group.php?group_id=<?= $group['group_id'] ?>" class="btn btn-primary">
                                        <i class="fas fa-eye"></i> View Group
                                    </a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="group_id" value="<?= $group['group_id'] ?>">
                                        <button type="submit" name="join_group" class="btn btn-success">
                                            <i class="fas fa-user-plus"></i> Join Group
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
