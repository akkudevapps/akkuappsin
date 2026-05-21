<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit;
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ban_user'])) {
        $userId = $_POST['user_id'];
        $pdo->prepare("UPDATE users SET is_banned = 1 WHERE user_id = ?")->execute([$userId]);
    } elseif (isset($_POST['unban_user'])) {
        $userId = $_POST['user_id'];
        $pdo->prepare("UPDATE users SET is_banned = 0 WHERE user_id = ?")->execute([$userId]);
    } elseif (isset($_POST['make_admin'])) {
        $userId = $_POST['user_id'];
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE user_id = ?")->execute([$userId]);
    } elseif (isset($_POST['remove_admin'])) {
        // Don't allow removing own admin status
        if ($_POST['user_id'] !== $user['user_id']) {
            $userId = $_POST['user_id'];
            $pdo->prepare("UPDATE users SET role = 'user' WHERE user_id = ?")->execute([$userId]);
        }
    }
}

// Get all users
$stmt = $pdo->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        @media (max-width: 768px) {
            .welcome-banner h1 { font-size: 1.3rem !important; }
            .welcome-banner p { font-size: 0.85rem !important; }
            .chart-container { padding: 1rem !important; }
            .chart-container h2 { font-size: 1.1rem !important; }
            table { font-size: 0.85rem !important; }
            table th, table td { padding: 10px 8px !important; }
            .dashboard-container { flex-direction: column !important; }
            .main-content { padding: 0.75rem !important; }
        }
        @media (max-width: 480px) {
            .welcome-banner h1 { font-size: 1.1rem !important; }
            table { font-size: 0.75rem !important; }
            table th, table td { padding: 8px 5px !important; }
        }
    </style>
</head>
<body>
    <?php include '../components/admin-header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/admin-sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>User Management</h1>
                <p>Manage all users on the platform</p>
            </div>

            <div class="chart-container">
                <h2>All Users (<?= count($users) ?>)</h2>
                <div style="overflow-x: auto; margin-top: 20px;">
                    <table style="width: 100%; border-collapse: collapse; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
                        <thead>
                            <tr style="background: var(--secondary-bg);">
                                <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">User</th>
                                <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Email</th>
                                <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Coins</th>
                                <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Role</th>
                                <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Status</th>
                                <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td style="padding: 15px; color: var(--text-primary);">
                                        <div style="display: flex; align-items: center;">
                                            <img src="<?= htmlspecialchars($u['avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                                 alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                            <div>
                                                <strong><?= htmlspecialchars($u['name']) ?></strong>
                                                <div style="font-size: 0.8em; color: var(--text-secondary);">
                                                    Joined: <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 15px; color: var(--text-primary);"><?= htmlspecialchars($u['email']) ?></td>
                                    <td style="padding: 15px; color: var(--text-primary);"><?= number_format($u['coin_balance'], 2) ?></td>
                                    <td style="padding: 15px; color: var(--text-primary);">
                                        <span style="background: <?= $u['role'] === 'admin' ? '#ef4444' : '#10b981' ?>; padding: 5px 10px; border-radius: 20px; font-size: 0.8em;">
                                            <?= ucfirst($u['role']) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px; color: var(--text-primary);">
                                        <span style="background: <?= $u['is_banned'] ? '#ef4444' : '#10b981' ?>; padding: 5px 10px; border-radius: 20px; font-size: 0.8em;">
                                            <?= $u['is_banned'] ? 'Banned' : 'Active' ?>
                                        </span>
                                    </td>
                                    <td style="padding: 15px;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                            <?php if ($u['is_banned']): ?>
                                                <button type="submit" name="unban_user" style="background: #10b981; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; margin: 2px;">
                                                    <i class="fas fa-unlock"></i> Unban
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="ban_user" style="background: #ef4444; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; margin: 2px;">
                                                    <i class="fas fa-ban"></i> Ban
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($u['role'] === 'admin' && $u['user_id'] !== $user['user_id']): ?>
                                                <button type="submit" name="remove_admin" style="background: #f59e0b; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; margin: 2px;">
                                                    <i class="fas fa-user-times"></i> Remove Admin
                                                </button>
                                            <?php elseif ($u['role'] !== 'admin'): ?>
                                                <button type="submit" name="make_admin" style="background: #8b5cf6; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; margin: 2px;">
                                                    <i class="fas fa-user-shield"></i> Make Admin
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
