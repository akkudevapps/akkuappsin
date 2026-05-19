<?php
// user/dashboard.php - Updated with News, Sales, Services sections
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

// Get user stats
try {
    global $pdo;
    
    // Posts count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_posts WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user['user_id']]);
    $postsCount = $stmt->fetchColumn();
    
    // Followers count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE following_id = ? AND status = 'accepted'");
    $stmt->execute([$user['user_id']]);
    $followersCount = $stmt->fetchColumn();
    
    // Following count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_follows WHERE follower_id = ? AND status = 'accepted'");
    $stmt->execute([$user['user_id']]);
    $followingCount = $stmt->fetchColumn();
    
    // Recent transactions
    $stmt = $pdo->prepare("
        SELECT * FROM coin_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user['user_id']]);
    $recentTransactions = $stmt->fetchAll();
    
    // NEW: Get unread notifications count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user['user_id']]);
    $unreadNotifications = $stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $postsCount = 0;
    $followersCount = 0;
    $followingCount = 0;
    $recentTransactions = [];
    $unreadNotifications = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner animate-fadeIn">
                <h1>Welcome back, <span class="highlight"><?= htmlspecialchars($user['name']) ?></span></h1>
                <p>Your personalized dashboard overview</p>
                <?php if ($unreadNotifications > 0): ?>
                <div style="margin-top: 10px;">
                    <span style="background: #ef4444; color: white; padding: 4px 12px; border-radius: 12px; font-size: 0.85rem;">
                        <i class="fas fa-bell"></i> <?= $unreadNotifications ?> new notification<?= $unreadNotifications > 1 ? 's' : '' ?>
                    </span>
                </div>
                <?php endif; ?>
                <div style="margin-top: 15px; display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener" class="btn btn-primary btn-sm"><i class="fas fa-robot"></i> Open Chatbot</a>
                    <a href="/marketplace/sell.php" class="btn btn-secondary btn-sm"><i class="fas fa-shopping-basket"></i> View Cart</a>
                </div>
            </div>

            <!-- User Stats Cards -->
            <div class="stats-grid animate-slideUp">
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($user['coin_balance'], 2) ?></h3>
                        <p>Your Coins</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-green">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $postsCount ?></h3>
                        <p>Your Posts</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-purple">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $followersCount ?></h3>
                        <p>Followers</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-orange">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= $followingCount ?></h3>
                        <p>Following</p>
                    </div>
                </div>
            </div>

            <!-- NEW: Platform Features Grid -->
            <div class="charts-section animate-slideUp">
                <!-- News & Blog -->
                <div class="chart-container">
                    <h2><i class="fas fa-newspaper" style="color: var(--accent-color);"></i> News & Blog</h2>
                    <p style="color: var(--text-secondary); font-size: 0.9em; margin-bottom: 15px;">Latest tech news and updates</p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                        <a href="/news/" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-newspaper" style="font-size: 2rem; color: #3b82f6; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Latest News</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Tech updates</p>
                            </div>
                        </a>
                        
                        <a href="/news/?category=hardware" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-microchip" style="font-size: 2rem; color: #8b5cf6; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Hardware</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Component news</p>
                            </div>
                        </a>
                        
                        <a href="/news/?category=deals" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-tags" style="font-size: 2rem; color: #10b981; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Deals</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Best offers</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Blogs & Guides -->
                <div class="chart-container">
                    <h2><i class="fas fa-star" style="color: #fbbf24;"></i> Blogs & Guides</h2>
                    <p style="color: var(--text-secondary); font-size: 0.9em; margin-bottom: 15px;">Creator blogs, public stories, and practical guides</p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                        <a href="/news/?kind=blog&category=hardware" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-microchip" style="font-size: 2rem; color: #3b82f6; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Hardware</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">CPU, GPU, RAM insights</p>
                            </div>
                        </a>
                        
                        <a href="/news/?kind=blog&category=guides" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-headphones" style="font-size: 2rem; color: #8b5cf6; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Guides</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Buying and setup help</p>
                            </div>
                        </a>
                        
                        <a href="/news/?kind=blog&category=tech" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-laptop" style="font-size: 2rem; color: #f59e0b; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Tech Blogs</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Creator picks and notes</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Marketplace / Sales -->
                <div class="chart-container">
                    <h2><i class="fas fa-shopping-cart" style="color: #10b981;"></i> Marketplace</h2>
                    <p style="color: var(--text-secondary); font-size: 0.9em; margin-bottom: 15px;">Buy and sell hardware</p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                        <a href="/marketplace/" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-store" style="font-size: 2rem; color: #10b981; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Browse</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">All listings</p>
                            </div>
                        </a>
                        
                        <a href="/marketplace/?type=components" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-hdd" style="font-size: 2rem; color: #3b82f6; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Components</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">PC parts</p>
                            </div>
                        </a>
                        
                        <a href="/marketplace/sell.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-shopping-basket" style="font-size: 2rem; color: #f59e0b; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Cart & Orders</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Review cart</p>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="chart-container">
                    <h2><i class="fas fa-robot" style="color: #10b981;"></i> Smart Assistant</h2>
                    <p style="color: var(--text-secondary); font-size: 0.9em; margin-bottom: 15px;">Launch the AkkuApps chatbot for product, service, and account guidance</p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                        <a href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-robot" style="font-size: 2rem; color: #10b981; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Open Chatbot</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Ask instantly</p>
                            </div>
                        </a>
                        
                        <a href="/services/book.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-headset" style="font-size: 2rem; color: #3b82f6; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Book Support</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Service after chat</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- PC Services -->
                <div class="chart-container">
                    <h2><i class="fas fa-tools" style="color: #ef4444;"></i> PC Services</h2>
                    <p style="color: var(--text-secondary); font-size: 0.9em; margin-bottom: 15px;">Professional computer services</p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                        <a href="/services/?type=build" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-desktop" style="font-size: 2rem; color: #3b82f6; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">PC Build</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Custom assembly</p>
                            </div>
                        </a>
                        
                        <a href="/services/?type=repair" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-wrench" style="font-size: 2rem; color: #ef4444; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Repair</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Fix issues</p>
                            </div>
                        </a>
                        
                        <a href="/services/?type=upgrade" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-arrow-up" style="font-size: 2rem; color: #10b981; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Upgrade</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Boost performance</p>
                            </div>
                        </a>
                        
                        <a href="/services/book.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-calendar-check" style="font-size: 2rem; color: #8b5cf6; margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Book Service</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Schedule now</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Content Creation & Community (Moved below) -->
            <div class="charts-section animate-slideUp">
                <!-- Content Creation -->
                <div class="chart-container">
                    <h2><i class="fas fa-edit"></i> Content Creation</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                        <a href="/user/create-post.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-plus-circle" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">New Post</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Create content</p>
                            </div>
                        </a>
                        
                        <a href="/user/feed.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-newspaper" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">View Feed</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Browse posts</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Monetization -->
                <div class="chart-container">
                    <h2><i class="fas fa-money-bill-wave"></i> Monetization</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                        <a href="/user/wallet.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-wallet" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Wallet</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Manage coins</p>
                            </div>
                        </a>
                        
                        <a href="/user/subscription.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-crown" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Subscriptions</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Earn from content</p>
                            </div>
                        </a>
                        
                        <a href="/user/badgeshop.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-award" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Badge Shop</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Premium badges</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Community -->
                <div class="chart-container">
                    <h2><i class="fas fa-users"></i> Community</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                        <a href="/user/followers.php?type=following" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-user-friends" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Following</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Manage follows</p>
                            </div>
                        </a>
                        
                        <a href="/user/followers.php?type=followers" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-users" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Followers</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">View followers</p>
                            </div>
                        </a>
                        
                        <a href="/user/messages.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-comments" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Messages</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Chat with users</p>
                            </div>
                        </a>
                        
                        <a href="/user/groups.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-layer-group" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Groups</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Join communities</p>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Account Management -->
                <div class="chart-container">
                    <h2><i class="fas fa-user-cog"></i> Account</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 20px;">
                        <a href="/user/profile.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-user" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Profile</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">View profile</p>
                            </div>
                        </a>
                        
                        <a href="/user/settings.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-cog" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Settings</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Account settings</p>
                            </div>
                        </a>
                        
                        <a href="/user/invites.php" style="text-decoration: none;">
                            <div style="background: var(--secondary-bg); padding: 20px; border-radius: 12px; text-align: center; transition: all 0.3s ease; border: 1px solid var(--border-color);">
                                <i class="fas fa-paper-plane" style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;"></i>
                                <h4 style="color: var(--text-primary); margin: 10px 0;">Invites</h4>
                                <p style="color: var(--text-secondary); font-size: 0.8em;">Invite friends</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="chart-container animate-slideUp">
                <h2><i class="fas fa-history"></i> Recent Activity</h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($recentTransactions)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 30px;">
                            No recent activity
                        </p>
                    <?php else: ?>
                        <?php foreach ($recentTransactions as $txn): ?>
                            <div style="display: flex; align-items: center; padding: 12px; border-bottom: 1px solid var(--border-color);">
                                <?php 
                                $icon = '';
                                $color = '';
                                switch ($txn['reference_type']) {
                                    case 'post':
                                        $icon = 'fas fa-plus-circle';
                                        $color = 'var(--accent-color)';
                                        break;
                                    case 'like_given':
                                        $icon = 'fas fa-heart';
                                        $color = '#ef4444';
                                        break;
                                    case 'like_received':
                                        $icon = 'fas fa-heart';
                                        $color = '#10b981';
                                        break;
                                    case 'comment_given':
                                        $icon = 'fas fa-comment';
                                        $color = '#f59e0b';
                                        break;
                                    case 'comment_received':
                                        $icon = 'fas fa-comment';
                                        $color = '#10b981';
                                        break;
                                    case 'purchase':
                                        $icon = 'fas fa-shopping-cart';
                                        $color = '#3b82f6';
                                        break;
                                    case 'subscription':
                                        $icon = 'fas fa-crown';
                                        $color = '#8b5cf6';
                                        break;
                                    default:
                                        $icon = 'fas fa-coins';
                                        $color = 'var(--text-secondary)';
                                }
                                ?>
                                <i class="<?= $icon ?>" style="color: <?= $color ?>; margin-right: 12px; width: 20px;"></i>
                                <div style="flex: 1;">
                                    <div style="color: var(--text-primary); font-size: 0.95em;"><?= htmlspecialchars($txn['description']) ?></div>
                                    <div style="color: var(--text-secondary); font-size: 0.8em;">
                                        <?= date('M j, Y g:i A', strtotime($txn['created_at'])) ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: <?= $txn['amount'] >= 0 ? '#10b981' : '#ef4444' ?>; font-weight: bold; font-size: 0.95em;">
                                        <?= $txn['amount'] >= 0 ? '+' : '' ?><?= number_format($txn['amount'], 2) ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.8em;">
                                        Balance: <?= number_format($txn['balance_after'], 2) ?>
                                    </div>
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
