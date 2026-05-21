<?php
// admin/usertypes.php - Admin user type management
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit;
}

$message = '';
$error = '';

// Handle user type updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user_type'])) {
    $userId = $_POST['user_id'];
    $userType = $_POST['user_type'];
    $isVerified = isset($_POST['is_verified']) ? 1 : 0;
    $verificationLevel = intval($_POST['verification_level'] ?? 0);
    
    try {
        global $pdo;
        $stmt = $pdo->prepare("
            UPDATE users 
            SET user_type = ?, is_verified = ?, verification_level = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$userType, $isVerified, $verificationLevel, $userId]);
        $message = "User type updated successfully";
    } catch (Exception $e) {
        $error = "Error updating user: " . $e->getMessage();
    }
}

// Get users with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

try {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT user_id, name, email, user_type, is_verified, verification_level, created_at, coin_balance
        FROM users
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $users = $stmt->fetchAll();
    
    // Get total count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $totalCount = $stmt->fetchColumn();
    $totalPages = ceil($totalCount / $limit);
    
} catch (Exception $e) {
    $users = [];
    $totalCount = 0;
    $totalPages = 0;
    $error = "Error loading users: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Types - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        @media (max-width: 768px) {
            .welcome-banner h1 { font-size: 1.3rem !important; }
            .welcome-banner p { font-size: 0.85rem !important; }
            .chart-container { padding: 1rem !important; }
            .chart-container h2 { font-size: 1.1rem !important; }
            .page-shell { padding: 0.75rem !important; }
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
                <h1>User Types Management</h1>
                <p>Manage user verification levels and account types</p>
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

            <div class="chart-container animate-slideUp">
                <h2>Users (<?= $totalCount ?>)</h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($users)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                            No users found.
                        </p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
                                <thead>
                                    <tr style="background: var(--secondary-bg);">
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">User</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Email</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Coins</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Type</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Verification</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: 15px; color: var(--text-primary);">
                                                <strong><?= htmlspecialchars($u['name']) ?></strong>
                                                <div style="color: var(--text-secondary); font-size: 0.9em;">
                                                    Joined: <?= date('M j, Y', strtotime($u['created_at'])) ?>
                                                </div>
                                            </td>
                                            <td style="padding: 15px; color: var(--text-primary);"><?= htmlspecialchars($u['email']) ?></td>
                                            <td style="padding: 15px; color: var(--text-primary);"><?= number_format($u['coin_balance'], 2) ?></td>
                                            <td style="padding: 15px; color: var(--text-primary);">
                                                <span style="background: 
                                                    <?php 
                                                    switch($u['user_type']) {
                                                        case 'premium': echo '#f59e0b'; break;
                                                        case 'gold': echo '#fbbf24'; break;
                                                        case 'platinum': echo '#e5e7eb'; break;
                                                        default: echo '#6b7280';
                                                    }
                                                    ?>; 
                                                    padding: 5px 10px; border-radius: 20px; font-size: 0.8em; color: 
                                                    <?php 
                                                    echo ($u['user_type'] === 'platinum') ? 'black' : 'white';
                                                    ?>">
                                                    <?= ucfirst($u['user_type']) ?>
                                                </span>
                                            </td>
                                            <td style="padding: 15px; color: var(--text-primary);">
                                                <?php if ($u['is_verified']): ?>
                                                    <span style="color: #10b981;">
                                                        <i class="fas fa-check-circle"></i> Level <?= $u['verification_level'] ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span style="color: #ef4444;">
                                                        <i class="fas fa-times-circle"></i> Not Verified
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 15px;">
                                                <button onclick="editUser('<?= $u['user_id'] ?>', '<?= htmlspecialchars($u['name']) ?>', '<?= $u['user_type'] ?>', <?= $u['is_verified'] ?>, <?= $u['verification_level'] ?>)" 
                                                        style="background: var(--accent-color); color: white; border: none; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 0.9em;">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div style="display: flex; justify-content: center; gap: 10px; margin: 30px 0;">
                                <?php if ($page > 1): ?>
                                    <a href="/admin/usertypes.php?page=<?= $page - 1 ?>" 
                                       style="padding: 10px 15px; border-radius: 6px; text-decoration: none; background: var(--secondary-bg); color: var(--text-primary);">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <a href="/admin/usertypes.php?page=<?= $i ?>" 
                                       style="padding: 10px 15px; border-radius: 6px; text-decoration: none; 
                                              <?= $i == $page ? 'background: var(--accent-color); color: white;' : 'background: var(--secondary-bg); color: var(--text-primary);' ?>">
                                        <?= $i ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="/admin/usertypes.php?page=<?= $page + 1 ?>" 
                                       style="padding: 10px 15px; border-radius: 6px; text-decoration: none; background: var(--secondary-bg); color: var(--text-primary);">
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

    <!-- Edit User Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; width: 90%; max-width: 500px; border: 1px solid var(--border-color);">
            <h2 style="margin-top: 0; color: var(--text-primary);">Edit User Type</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="update_user_type" value="1">
                <input type="hidden" name="user_id" id="editUserId">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">User</label>
                    <input type="text" id="editUserName" readonly
                           style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Account Type</label>
                    <select name="user_type" id="editUserType"
                            style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                        <option value="regular">Regular</option>
                        <option value="premium">Premium</option>
                        <option value="gold">Gold</option>
                        <option value="platinum">Platinum</option>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; color: var(--text-primary);">
                        <input type="checkbox" name="is_verified" id="editIsVerified" value="1" style="margin-right: 10px;">
                        Verified User
                    </label>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Verification Level (0-5)</label>
                    <input type="number" name="verification_level" id="editVerificationLevel" min="0" max="5" value="0"
                           style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" 
                            style="background: var(--accent-color); color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; flex: 1;">
                        Update User
                    </button>
                    <button type="button" onclick="closeModal()" 
                            style="background: #6b7280; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
    <script>
        function editUser(userId, userName, userType, isVerified, verificationLevel) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUserName').value = userName;
            document.getElementById('editUserType').value = userType;
            document.getElementById('editIsVerified').checked = isVerified == 1;
            document.getElementById('editVerificationLevel').value = verificationLevel;
            
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
