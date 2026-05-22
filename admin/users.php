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
        if ($_POST['user_id'] !== $user['user_id']) {
            $userId = $_POST['user_id'];
            $pdo->prepare("UPDATE users SET role = 'user' WHERE user_id = ?")->execute([$userId]);
        }
    } elseif (isset($_POST['add_coins_all'])) {
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount > 0) {
            $reason = trim($_POST['reason'] ?? '');
            $pdo->prepare("UPDATE users SET coin_balance = coin_balance + ?")->execute([$amount]);
            // Log individual transactions for each user
            $allUsers = $pdo->query("SELECT user_id, coin_balance FROM users")->fetchAll();
            foreach ($allUsers as $u) {
                $pdo->prepare(
                    "INSERT INTO coin_transactions (txn_id, user_id, reference_type, amount, balance_after, description, created_at) VALUES (?, ?, 'admin_bulk_add', ?, ?, ?, NOW())"
                )->execute([generateUUID(), $u['user_id'], $amount, (float)$u['coin_balance'], trim("Bulk add: {$reason}") ?: 'Admin bulk coin addition']);
            }
        }
    } elseif (isset($_POST['add_coins_user'])) {
        $targetId = $_POST['target_id'];
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount > 0 && $targetId) {
            $reason = trim($_POST['reason'] ?? '');
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
                $stmt->execute([$targetId]);
                $current = (float) $stmt->fetchColumn();
                $newBalance = $current + $amount;
                $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")->execute([$newBalance, $targetId]);
                $pdo->prepare(
                    "INSERT INTO coin_transactions (txn_id, user_id, reference_type, amount, balance_after, description, created_at) VALUES (?, ?, 'admin_add', ?, ?, ?, NOW())"
                )->execute([generateUUID(), $targetId, $amount, $newBalance, $reason ?: 'Admin coin addition']);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Add coins error: ' . $e->getMessage());
            }
        }
    } elseif (isset($_POST['add_coins_user_row'])) {
        $targetId = $_POST['target_id'];
        $amount = (float) ($_POST['amount'] ?? 0);
        if ($amount > 0 && $targetId) {
            $reason = trim($_POST['reason'] ?? 'Admin coin addition');
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
                $stmt->execute([$targetId]);
                $current = (float) $stmt->fetchColumn();
                $newBalance = $current + $amount;
                $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")->execute([$newBalance, $targetId]);
                $pdo->prepare(
                    "INSERT INTO coin_transactions (txn_id, user_id, reference_type, amount, balance_after, description, created_at) VALUES (?, ?, 'admin_add', ?, ?, ?, NOW())"
                )->execute([generateUUID(), $targetId, $amount, $newBalance, $reason]);
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log('Add coins error: ' . $e->getMessage());
            }
        }
    }
}

// Refresh user list after potential modifications
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

            <div class="chart-container" style="margin-bottom: 20px;">
                <h2>Coin Management</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; margin-top: 16px;">
                    <div style="background: var(--secondary-bg, #1e293b); padding: 20px; border-radius: 12px;">
                        <h3 style="margin: 0 0 12px 0; font-size: 1rem;"><i class="fas fa-coins"></i> Add Coins to All Users</h3>
                        <form method="POST" style="display: flex; flex-direction: column; gap: 10px;">
                            <input type="number" name="amount" step="0.01" min="0.01" placeholder="Amount" required style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                            <input type="text" name="reason" placeholder="Reason (optional)" style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                            <button type="submit" name="add_coins_all" style="background: #8b5cf6; color: white; border: none; padding: 10px; border-radius: 8px; cursor: pointer; font-weight: 600;"><i class="fas fa-coins"></i> Add to All Users</button>
                        </form>
                    </div>
                    <div style="background: var(--secondary-bg, #1e293b); padding: 20px; border-radius: 12px;">
                        <h3 style="margin: 0 0 12px 0; font-size: 1rem;"><i class="fas fa-user-plus"></i> Add Coins to Selected User</h3>
                        <form method="POST" style="display: flex; flex-direction: column; gap: 10px;">
                            <select name="target_id" required style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                                <option value="">Select User...</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="amount" step="0.01" min="0.01" placeholder="Amount" required style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                            <input type="text" name="reason" placeholder="Reason" required style="padding: 10px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary);">
                            <button type="submit" name="add_coins_user" style="background: #10b981; color: white; border: none; padding: 10px; border-radius: 8px; cursor: pointer; font-weight: 600;"><i class="fas fa-plus-circle"></i> Add Coins</button>
                        </form>
                    </div>
                </div>
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
                                        <button onclick="this.nextElementSibling.style.display='flex'" style="background: transparent; color: #10b981; border: 1px solid #10b981; padding: 5px 8px; border-radius: 5px; cursor: pointer; margin: 2px; font-size: 0.75rem;">
                                            <i class="fas fa-coins"></i> +Coins
                                        </button>
                                        <form method="POST" style="display: none; align-items: center; gap: 4px; margin-top: 4px;">
                                            <input type="hidden" name="target_id" value="<?= $u['user_id'] ?>">
                                            <input type="number" name="amount" step="0.01" min="0.01" placeholder="Amount" required style="width: 70px; padding: 4px 6px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.75rem;">
                                            <input type="text" name="reason" placeholder="Reason" required style="width: 100px; padding: 4px 6px; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-primary); font-size: 0.75rem;">
                                            <button type="submit" name="add_coins_user_row" style="background: #10b981; color: white; border: none; padding: 4px 8px; border-radius: 4px; cursor: pointer; font-size: 0.75rem;"><i class="fas fa-check"></i></button>
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
