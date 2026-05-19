<?php
// user/subscription.php - Fixed version
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/economy.php';

$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$message = '';
$error = '';
$success = false;

// Get creators (users with posts)
try {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.name, u.avatar, u.bio,
               COUNT(up.post_id) as post_count,
               (SELECT COUNT(*) FROM creator_subscriptions WHERE creator_id = u.user_id AND status = 'active') as subscriber_count
        FROM users u
        LEFT JOIN user_posts up ON u.user_id = up.user_id
        WHERE u.user_id != ? AND u.role != 'admin'
        GROUP BY u.user_id
        HAVING post_count > 0
        ORDER BY subscriber_count DESC, post_count DESC
        LIMIT 50
    ");
    $stmt->execute([$user['user_id']]);
    $creators = $stmt->fetchAll();
} catch (Exception $e) {
    $creators = [];
    error_log("Subscription page error: " . $e->getMessage());
}

// Handle subscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subscribe'])) {
    $creatorId = $_POST['creator_id'];
    $subscriptionPrice = 50.00; // Fixed price for now
    
    // Validate creator
    $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE user_id = ? AND role != 'admin'");
    $stmt->execute([$creatorId]);
    $creator = $stmt->fetch();
    
    if (!$creator) {
        $error = "Creator not found";
    } else if ($creatorId === $user['user_id']) {
        $error = "You cannot subscribe to yourself";
    } else if ($user['coin_balance'] < $subscriptionPrice) {
        $error = "Insufficient coins. You need {$subscriptionPrice} coins. Your balance: {$user['coin_balance']} coins.";
    } else {
        // Process subscription
        $result = subscribeToCreator($user['user_id'], $creatorId, $subscriptionPrice);
        if (isset($result['success']) && $result['success']) {
            $message = $result['message'];
            $success = true;
            // Refresh user balance
            $user = getCurrentUser();
        } else {
            $error = $result['error'] ?? 'Subscription failed';
        }
    }
}

// Get user's current subscriptions
try {
    $stmt = $pdo->prepare("
        SELECT cs.*, u.name as creator_name, u.avatar as creator_avatar
        FROM creator_subscriptions cs
        JOIN users u ON cs.creator_id = u.user_id
        WHERE cs.subscriber_id = ? AND cs.status = 'active' AND cs.expires_at > NOW()
        ORDER BY cs.created_at DESC
    ");
    $stmt->execute([$user['user_id']]);
    $subscriptions = $stmt->fetchAll();
} catch (Exception $e) {
    $subscriptions = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscriptions - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Creator Subscriptions</h1>
                <p>Support your favorite creators and get exclusive content (50 coins/month)</p>
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

            <!-- User's Subscriptions -->
            <?php if (!empty($subscriptions)): ?>
            <div class="chart-container animate-slideUp">
                <h2>Your Subscriptions</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php foreach ($subscriptions as $sub): ?>
                        <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
                            <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                <img src="<?= htmlspecialchars($sub['creator_avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                     alt="Avatar" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px;">
                                <div>
                                    <h4 style="color: var(--text-primary); margin: 0;"><?= htmlspecialchars($sub['creator_name']) ?></h4>
                                    <div style="color: var(--text-secondary); font-size: 0.9em;">
                                        Subscribed: <?= date('M j, Y', strtotime($sub['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="color: var(--text-secondary);">
                                    Expires: <?= date('M j, Y', strtotime($sub['expires_at'])) ?>
                                </div>
                                <div style="color: var(--accent-color);">
                                    50 <i class="fas fa-coins"></i>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Available Creators -->
            <div class="chart-container animate-slideUp">
                <h2>Support Creators <span style="font-size: 0.7em; color: var(--text-secondary);">(50 <i class="fas fa-coins"></i> per month)</span></h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($creators)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                            No creators available at the moment.
                        </p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                            <?php foreach ($creators as $creator): ?>
                                <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
                                    <div style="display: flex; align-items: center; margin-bottom: 15px;">
                                        <img src="<?= htmlspecialchars($creator['avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                             alt="Avatar" style="width: 50px; height: 50px; border-radius: 50%; margin-right: 15px;">
                                        <div>
                                            <h4 style="color: var(--text-primary); margin: 0;"><?= htmlspecialchars($creator['name']) ?></h4>
                                            <div style="color: var(--text-secondary); font-size: 0.9em;">
                                                <?= $creator['post_count'] ?> posts • <?= $creator['subscriber_count'] ?> subscribers
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($creator['bio'])): ?>
                                        <p style="color: var(--text-secondary); font-size: 0.9em; margin: 15px 0;">
                                            <?= htmlspecialchars(substr($creator['bio'], 0, 100)) ?><?= strlen($creator['bio']) > 100 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                        <div style="color: var(--text-secondary); font-size: 0.9em;">
                                            Cost: 50 coins/month
                                        </div>
                                        <div style="color: var(--accent-color); font-weight: bold;">
                                            Your balance: <?= number_format($user['coin_balance'], 2) ?>
                                        </div>
                                    </div>
                                    
                                    <form method="POST" style="margin-top: 15px;">
                                        <input type="hidden" name="creator_id" value="<?= $creator['user_id'] ?>">
                                        <button type="submit" name="subscribe" 
                                                <?= $user['coin_balance'] < 50 ? 'disabled' : '' ?>
                                                style="width: 100%; background: <?= $user['coin_balance'] < 50 ? '#6b7280' : 'var(--accent-color)' ?>; color: white; border: none; padding: 10px; border-radius: 6px; cursor: <?= $user['coin_balance'] < 50 ? 'not-allowed' : 'pointer' ?>; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                            <i class="fas fa-crown"></i> Subscribe for 50 <i class="fas fa-coins"></i>
                                        </button>
                                        <?php if ($user['coin_balance'] < 50): ?>
                                            <div style="text-align: center; color: #ef4444; font-size: 0.8em; margin-top: 5px;">
                                                Insufficient coins
                                            </div>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Subscription Benefits -->
            <div class="chart-container animate-slideUp">
                <h2>Subscription Benefits</h2>
                <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 15px;">
                            <i class="fas fa-star"></i>
                        </div>
                        <h4 style="color: var(--text-primary); margin: 0 0 10px 0;">Exclusive Content</h4>
                        <p style="color: var(--text-secondary); font-size: 0.9em;">
                            Access special posts and content from your favorite creators
                        </p>
                    </div>
                    
                    <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 15px;">
                            <i class="fas fa-badge-percent"></i>
                        </div>
                        <h4 style="color: var(--text-primary); margin: 0 0 10px 0;">Early Access</h4>
                        <p style="color: var(--text-secondary); font-size: 0.9em;">
                            Get early access to new content and features
                        </p>
                    </div>
                    
                    <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 15px;">
                            <i class="fas fa-medal"></i>
                        </div>
                        <h4 style="color: var(--text-primary); margin: 0 0 10px 0;">Support Creators</h4>
                        <p style="color: var(--text-secondary); font-size: 0.9em;">
                            Help creators continue making amazing content
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
