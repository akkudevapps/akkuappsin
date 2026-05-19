<?php
// user/create-post.php - FIXED VERSION
// ERROR REPORTING FIRST - Before ANY output
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

// NO output before this point - no echo, no whitespace, nothing
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

// IMPORTANT FIX: Only load economy.php OR notifications.php, not both
// Since economy.php already has createNotification(), we don't need notifications.php
require_once '../includes/economy.php';

$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    
    if (empty($content)) {
        $error = "Post content cannot be empty";
    } elseif ($user['coin_balance'] < 2) {
        $error = "Insufficient coins. You need 2 coins to create a post. Your balance: " . number_format($user['coin_balance'], 2) . " coins.";
    } else {
        $result = createPost($user['user_id'], $content);
        if (!empty($result['success'])) {
            $message = "Post created successfully! {$result['cost']} coins were routed to the treasury.";
            $user = getCurrentUser();
        } else {
            $error = "Error creating post: " . ($result['error'] ?? 'Unknown error');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Post - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Create New Post</h1>
                <p>Share your thoughts with the community (Costs 2 coins)</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    <div style="margin-top: 10px; font-size: 0.9em;">
                        Your new balance: <strong><?= number_format($user['coin_balance'], 2) ?> coins</strong>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="chart-container animate-slideUp">
                <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 30px;">
                    <form method="POST">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Your Post</label>
                            <textarea name="content" rows="6" placeholder="What's on your mind?" required
                                      style="width: 100%; padding: 15px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 8px; color: var(--text-primary); font-size: 16px; resize: vertical;"></textarea>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div style="color: var(--text-secondary);">
                                <i class="fas fa-info-circle"></i> Creating a post costs 2 coins
                            </div>
                            <div style="color: var(--accent-color); font-weight: bold;">
                                Balance: <?= number_format($user['coin_balance'], 2) ?> coins
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" 
                                    <?= $user['coin_balance'] < 2 ? 'disabled' : '' ?>
                                    style="background: <?= $user['coin_balance'] < 2 ? '#6b7280' : 'var(--accent-color)' ?>; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: <?= $user['coin_balance'] < 2 ? 'not-allowed' : 'pointer' ?>; flex: 1; font-size: 16px;">
                                <i class="fas fa-paper-plane"></i> Publish Post (2 coins)
                            </button>
                            <a href="/user/feed.php" 
                               style="background: var(--secondary-bg); color: var(--text-primary); padding: 12px 24px; border: 1px solid var(--border-color); border-radius: 6px; text-decoration: none; display: inline-block; text-align: center;">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>