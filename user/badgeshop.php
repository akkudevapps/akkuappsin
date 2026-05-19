<?php
// user/badgeshop.php - Fixed version
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

// Get available badges
try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM badges WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC");
    $stmt->execute();
    $badges = $stmt->fetchAll();
} catch (Exception $e) {
    $badges = [];
    error_log("Badge shop error: " . $e->getMessage());
}

// Get user's badges
try {
    $stmt = $pdo->prepare("
        SELECT b.*, ub.acquired_at 
        FROM user_badges ub
        JOIN badges b ON ub.badge_id = b.badge_id
        WHERE ub.user_id = ?
        ORDER BY ub.acquired_at DESC
    ");
    $stmt->execute([$user['user_id']]);
    $userBadges = $stmt->fetchAll();
} catch (Exception $e) {
    $userBadges = [];
}

// Handle badge purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_badge'])) {
    $badgeId = $_POST['badge_id'];
    $result = buyBadge($user['user_id'], $badgeId);

    if (!empty($result['success'])) {
        $message = $result['message'];
        $success = true;
        $user = getCurrentUser();

        foreach ($badges as $badge) {
            if ($badge['badge_id'] === $badgeId) {
                $badge['acquired_at'] = date('Y-m-d H:i:s');
                $userBadges[] = $badge;
                break;
            }
        }
    } else {
        $error = $result['error'] ?? 'Unable to purchase badge.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Badge Shop - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Badge Shop</h1>
                <p>Purchase premium badges to showcase your status</p>
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

            <!-- User's Badges -->
            <?php if (!empty($userBadges)): ?>
            <div class="chart-container animate-slideUp">
                <h2>Your Badges</h2>
                <div style="margin-top: 20px; display: flex; flex-wrap: wrap; gap: 15px;">
                    <?php foreach ($userBadges as $badge): ?>
                        <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; text-align: center; min-width: 150px;">
                            <?php if (!empty($badge['icon_url'])): ?>
                                <img src="<?= htmlspecialchars($badge['icon_url']) ?>" alt="<?= htmlspecialchars($badge['name']) ?>" 
                                     style="width: 60px; height: 60px; margin-bottom: 10px;">
                            <?php elseif (!empty($badge['icon'])): ?>
                                <div style="font-size: 3rem; margin-bottom: 10px;">
                                    <?= $badge['icon'] ?>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 3rem; margin-bottom: 10px;">
                                    <i class="fas fa-award"></i>
                                </div>
                            <?php endif; ?>
                            <h4 style="color: var(--text-primary); margin: 0 0 5px 0;"><?= htmlspecialchars($badge['name']) ?></h4>
                            <div style="color: var(--text-secondary); font-size: 0.8em;">
                                <?= date('M j, Y', strtotime($badge['acquired_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Available Badges -->
            <div class="chart-container animate-slideUp">
                <h2>Available Badges</h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($badges)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                            No badges available at the moment.
                        </p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <?php foreach ($badges as $badge): ?>
                                <?php 
                                // Check if user already owns this badge
                                $owned = false;
                                foreach ($userBadges as $userBadge) {
                                    if ($userBadge['badge_id'] === $badge['badge_id']) {
                                        $owned = true;
                                        break;
                                    }
                                }
                                ?>
                                <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; text-align: center; position: relative;">
                                    <?php if ($owned): ?>
                                        <div style="position: absolute; top: 10px; right: 10px; background: #10b981; color: white; padding: 3px 8px; border-radius: 20px; font-size: 0.7em;">
                                            Owned
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($badge['icon_url'])): ?>
                                        <img src="<?= htmlspecialchars($badge['icon_url']) ?>" alt="<?= htmlspecialchars($badge['name']) ?>" 
                                             style="width: 80px; height: 80px; margin-bottom: 15px;">
                                    <?php elseif (!empty($badge['icon'])): ?>
                                        <div style="font-size: 4rem; margin-bottom: 15px;">
                                            <?= $badge['icon'] ?>
                                        </div>
                                    <?php else: ?>
                                        <div style="font-size: 4rem; margin-bottom: 15px;">
                                            <i class="fas fa-award"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <h3 style="color: var(--text-primary); margin: 0 0 10px 0;"><?= htmlspecialchars($badge['name']) ?></h3>
                                    <p style="color: var(--text-secondary); font-size: 0.9em; margin: 0 0 15px 0;">
                                        <?= htmlspecialchars($badge['description']) ?>
                                    </p>
                                    
                                    <div style="margin: 15px 0;">
                                        <?php if ($badge['coin_price'] > 0): ?>
                                            <div style="font-size: 1.2rem; color: var(--accent-color);">
                                                <?= number_format($badge['coin_price'], 2) ?> <i class="fas fa-coins"></i>
                                            </div>
                                        <?php else: ?>
                                            <div style="font-size: 1.2rem; color: #10b981;">
                                                FREE
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($owned): ?>
                                        <button disabled 
                                                style="width: 100%; background: #6b7280; color: white; border: none; padding: 10px; border-radius: 6px; cursor: not-allowed;">
                                            Already Owned
                                        </button>
                                    <?php else: ?>
                                        <form method="POST">
                                            <input type="hidden" name="badge_id" value="<?= $badge['badge_id'] ?>">
                                            <button type="submit" name="buy_badge" 
                                                    <?= ($badge['coin_price'] > 0 && $user['coin_balance'] < $badge['coin_price']) ? 'disabled' : '' ?>
                                                    style="width: 100%; background: <?= ($badge['coin_price'] > 0 && $user['coin_balance'] < $badge['coin_price']) ? '#6b7280' : ($badge['coin_price'] > 0 ? 'var(--accent-color)' : '#10b981') ?>; color: white; border: none; padding: 10px; border-radius: 6px; cursor: <?= ($badge['coin_price'] > 0 && $user['coin_balance'] < $badge['coin_price']) ? 'not-allowed' : 'pointer' ?>;">
                                                <?= $badge['coin_price'] > 0 ? 'Buy for ' . number_format($badge['coin_price'], 2) . ' coins' : 'Get Free Badge' ?>
                                            </button>
                                            <?php if ($badge['coin_price'] > 0 && $user['coin_balance'] < $badge['coin_price']): ?>
                                                <div style="text-align: center; color: #ef4444; font-size: 0.8em; margin-top: 5px;">
                                                    Insufficient coins
                                                </div>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Badge Benefits -->
            <div class="chart-container animate-slideUp">
                <h2>Badge Benefits</h2>
                <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                    <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 15px;">
                            <i class="fas fa-crown"></i>
                        </div>
                        <h4 style="color: var(--text-primary); margin: 0 0 10px 0;">Premium Status</h4>
                        <p style="color: var(--text-secondary); font-size: 0.9em;">
                            Show off your premium status with exclusive badges
                        </p>
                    </div>
                    
                    <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 15px;">
                            <i class="fas fa-star"></i>
                        </div>
                        <h4 style="color: var(--text-primary); margin: 0 0 10px 0;">Profile Highlight</h4>
                        <p style="color: var(--text-secondary); font-size: 0.9em;">
                            Your profile gets special highlighting with premium badges
                        </p>
                    </div>
                    
                    <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center;">
                        <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 15px;">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h4 style="color: var(--text-primary); margin: 0 0 10px 0;">Early Access</h4>
                        <p style="color: var(--text-secondary); font-size: 0.9em;">
                            Get early access to new features and content
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
