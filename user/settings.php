<?php
// user/settings.php - Fixed sidebar
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        global $pdo;
        
        if (isset($_POST['update_profile'])) {
            $name = trim($_POST['name'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            $avatar = trim($_POST['avatar'] ?? '');
            
            if (strlen($name) < 2) {
                $error = 'Name must be at least 2 characters long';
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET name = ?, bio = ?, avatar = ?, updated_at = NOW() 
                    WHERE user_id = ?
                ");
                $stmt->execute([$name, $bio, $avatar, $user['user_id']]);
                $message = 'Profile updated successfully';
                
                // Refresh user data
                $user = getCurrentUser();
            }
        }
    } catch (Exception $e) {
        $error = 'An error occurred. Please try again.';
        error_log("Settings error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div style="padding: 20px; max-width: 600px; margin: 0 auto; padding-top: 20px;">
                <h1>Settings</h1>
                
                <?php if ($message): ?>
                    <div style="background: #10b981; color: white; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div style="background: #ef4444; color: white; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <div style="background: var(--card-bg); border-radius: 12px; padding: 30px;">
                    <form method="POST">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" 
                                   style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Bio</label>
                            <textarea name="bio" rows="3" 
                                      style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Avatar URL</label>
                            <input type="url" name="avatar" value="<?= htmlspecialchars($user['avatar'] ?? '') ?>" 
                                   style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        
                        <button type="submit" name="update_profile" 
                                style="background: var(--accent-color); color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer;">
                            Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
