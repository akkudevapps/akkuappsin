<?php
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() ?? 0;
$totalPosts = $pdo->query("SELECT COUNT(*) FROM user_posts")->fetchColumn() ?? 0;
$todayLogins = $pdo->query("SELECT COUNT(*) FROM users WHERE last_login >= CURDATE()")->fetchColumn() ?? 0;
$pendingPayments = $pdo->query("SELECT COUNT(*) FROM upi_payments WHERE status = 'pending'")->fetchColumn() ?? 0;
$totalCoins = $pdo->query("SELECT COALESCE(SUM(coin_balance), 0) FROM users")->fetchColumn() ?? 0;

$marketplaceStats = [
    'products' => 0,
    'brands' => 0,
    'categories' => 0,
    'orders' => 0,
    'lowStock' => 0,
    'outOfStock' => 0,
    'revenue' => 0,
];

try {
    $marketplaceStats['products'] = $pdo->query("SELECT COUNT(*) FROM cs_products WHERE status = 'active' OR is_active = 1")->fetchColumn() ?? 0;
    $marketplaceStats['brands'] = $pdo->query("SELECT COUNT(*) FROM cs_brands")->fetchColumn() ?? 0;
    $marketplaceStats['categories'] = $pdo->query("SELECT COUNT(*) FROM cs_categories")->fetchColumn() ?? 0;
    $marketplaceStats['orders'] = $pdo->query("SELECT COUNT(*) FROM cs_invoices WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
    $marketplaceStats['lowStock'] = $pdo->query("SELECT COUNT(*) FROM cs_vw_product_stock WHERE current_stock <= reorder_level AND current_stock > 0")->fetchColumn() ?? 0;
    $marketplaceStats['outOfStock'] = $pdo->query("SELECT COUNT(*) FROM cs_vw_product_stock WHERE current_stock <= 0")->fetchColumn() ?? 0;
    $marketplaceStats['revenue'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM cs_invoices WHERE status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
} catch (Exception $e) {
    error_log('Admin dashboard marketplace stats error: ' . $e->getMessage());
}

$stats = getEconomyStats();
$collectionBox = new AkkuCollectionBox($pdo);
$boxBalance = $collectionBox->getBalance();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .admin-dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .admin-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: var(--space-lg);
            box-shadow: var(--shadow-sm);
        }

        .admin-card h3 {
            font-size: var(--font-lg);
            margin-bottom: var(--space-sm);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .admin-link-list {
            display: grid;
            gap: 6px;
        }

        .admin-link-list a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: var(--space-sm);
            padding: 8px 10px;
            border-radius: var(--border-radius-sm);
            text-decoration: none;
            color: var(--text-secondary);
            transition: all var(--transition-fast);
        }

        .admin-link-list a:hover {
            background: var(--bg-hover);
            color: var(--text-primary);
        }

        .dashboard-toolbar {
            display: flex;
            gap: var(--space-sm);
            flex-wrap: wrap;
            margin-top: var(--space-md);
        }

        .dashboard-toolbar .btn {
            text-decoration: none;
        }

        .admin-alert {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--border-radius);
            margin-bottom: var(--space-md);
            border: 1px solid transparent;
        }

        .admin-alert.warning {
            background: rgba(245, 158, 11, 0.08);
            border-color: rgba(245, 158, 11, 0.18);
            color: var(--warning);
        }

        .admin-alert.danger {
            background: rgba(239, 68, 68, 0.08);
            border-color: rgba(239, 68, 68, 0.18);
            color: var(--danger);
        }

        .admin-alert a {
            margin-left: auto;
            text-decoration: underline;
        }

        .compact-muted {
            color: var(--text-secondary);
            font-size: var(--font-sm);
        }

        @media (max-width: 768px) {
            .admin-alert {
                align-items: flex-start;
                flex-direction: column;
            }

            .admin-alert a {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include '../components/admin-header.php'; ?>

    <div class="dashboard-container">
        <?php include '../components/admin-sidebar.php'; ?>

        <main class="main-content">
            <section class="hero-panel anim-fade-in">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <?= htmlspecialchars($user['name']) ?>. Theme, navigation, marketplace, users, and content tools are now grouped into one cleaner control surface.</p>
                <div class="dashboard-toolbar">
                    <a href="/index.php" class="btn btn-outline btn-sm"><i class="fas fa-house"></i> Home</a>
                    <a href="/news/" class="btn btn-outline btn-sm"><i class="fas fa-newspaper"></i> News</a>
                    <a href="/news/?kind=blog" class="btn btn-outline btn-sm"><i class="fas fa-feather-pointed"></i> Blogs</a>
                    <a href="/admin/marketplace.php" class="btn btn-primary btn-sm"><i class="fas fa-store"></i> Marketplace</a>
                    <a href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><i class="fas fa-robot"></i> Chatbot</a>
                    <a href="/user/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-user"></i> User Dashboard</a>
                </div>
            </section>

            <?php if ($marketplaceStats['outOfStock'] > 0): ?>
                <div class="admin-alert danger">
                    <i class="fas fa-triangle-exclamation"></i>
                    <strong><?= $marketplaceStats['outOfStock'] ?> products are out of stock.</strong>
                    <a href="/admin/marketplace.php?tab=stock&filter=out">View stock</a>
                </div>
            <?php endif; ?>

            <?php if ($marketplaceStats['lowStock'] > 0): ?>
                <div class="admin-alert warning">
                    <i class="fas fa-bolt"></i>
                    <strong><?= $marketplaceStats['lowStock'] ?> products are running low.</strong>
                    <a href="/admin/marketplace.php?tab=stock&filter=low">Review low stock</a>
                </div>
            <?php endif; ?>

            <div class="stats-grid anim-slide-right">
                <div class="stat-card primary">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value" data-count="<?= (int) $totalUsers ?>" data-duration="1200">0</div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-pen-to-square"></i></div>
                    <div class="stat-value" data-count="<?= (int) $totalPosts ?>" data-duration="1200">0</div>
                    <div class="stat-label">Total Posts</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-coins"></i></div>
                    <div class="stat-value" data-count="<?= (int) $totalCoins ?>" data-duration="1400">0</div>
                    <div class="stat-label">Total Coins</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon"><i class="fas fa-piggy-bank"></i></div>
                    <div class="stat-value" data-count="<?= (int) $boxBalance ?>" data-duration="1400">0</div>
                    <div class="stat-label">Collection Box</div>
                </div>
            </div>

            <div class="stats-grid anim-slide-right">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-box-open"></i></div>
                    <div class="stat-value" data-count="<?= (int) $marketplaceStats['products'] ?>" data-duration="1000">0</div>
                    <div class="stat-label">Active Products</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-indian-rupee-sign"></i></div>
                    <div class="stat-value" data-count="<?= (int) $marketplaceStats['revenue'] ?>" data-prefix="₹" data-duration="1500">0</div>
                    <div class="stat-label">30-Day Revenue</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-cart-shopping"></i></div>
                    <div class="stat-value" data-count="<?= (int) $marketplaceStats['orders'] ?>" data-duration="1000">0</div>
                    <div class="stat-label">Recent Orders</div>
                </div>
                <div class="stat-card danger">
                    <div class="stat-icon"><i class="fas fa-triangle-exclamation"></i></div>
                    <div class="stat-value" data-count="<?= (int) ($marketplaceStats['lowStock'] + $marketplaceStats['outOfStock']) ?>" data-duration="1000">0</div>
                    <div class="stat-label">Stock Alerts</div>
                </div>
            </div>

            <div class="admin-dashboard-grid">
                <section class="admin-card">
                    <h3><i class="fas fa-store"></i> Marketplace</h3>
                    <p class="compact-muted">Products, orders, stock, customers, and import shortcuts.</p>
                    <div class="admin-link-list">
                        <a href="/admin/marketplace.php"><span><i class="fas fa-table-cells-large"></i> Control Center</span><span><?= (int) $marketplaceStats['products'] ?> products</span></a>
                        <a href="/admin/marketplace.php?tab=orders"><span><i class="fas fa-cart-shopping"></i> Orders</span><span><?= (int) $marketplaceStats['orders'] ?> recent</span></a>
                        <a href="/admin/marketplace.php?tab=stock"><span><i class="fas fa-boxes-stacked"></i> Stock</span><span><?= (int) ($marketplaceStats['lowStock'] + $marketplaceStats['outOfStock']) ?> alerts</span></a>
                        <a href="/admin/marketplace.php?tab=customers"><span><i class="fas fa-users"></i> Customers</span><span>Manage</span></a>
                    </div>
                </section>

                <section class="admin-card">
                    <h3><i class="fas fa-newspaper"></i> Content & Community</h3>
                    <p class="compact-muted">Public site entry points and moderation tools in one place.</p>
                    <div class="admin-link-list">
                        <a href="/news/"><span><i class="fas fa-newspaper"></i> Public News</span><span>Open</span></a>
                        <a href="/news/?kind=blog"><span><i class="fas fa-feather-pointed"></i> Public Blogs</span><span>Open</span></a>
                        <a href="/admin/news.php"><span><i class="fas fa-pen-to-square"></i> News Management</span><span>Edit</span></a>
                        <a href="/admin/reviews.php"><span><i class="fas fa-star"></i> Reviews</span><span>Moderate</span></a>
                    </div>
                </section>

                <section class="admin-card">
                    <h3><i class="fas fa-users-gear"></i> User Ops</h3>
                    <p class="compact-muted">Account oversight, user dashboard access, and payment review.</p>
                    <div class="admin-link-list">
                        <a href="/admin/users.php"><span><i class="fas fa-users"></i> User Management</span><span><?= (int) $totalUsers ?> users</span></a>
                        <a href="/user/dashboard.php"><span><i class="fas fa-user"></i> User Dashboard</span><span>Open</span></a>
                        <a href="/admin/payments.php"><span><i class="fas fa-credit-card"></i> UPI Payments</span><span><?= (int) $pendingPayments ?> pending</span></a>
                        <a href="/admin/content.php"><span><i class="fas fa-shield-halved"></i> Content Moderation</span><span>Review</span></a>
                    </div>
                </section>

                <section class="admin-card">
                    <h3><i class="fas fa-screwdriver-wrench"></i> Platform Links</h3>
                    <p class="compact-muted">Frequently used public routes and assistant entry points.</p>
                    <div class="admin-link-list">
                        <a href="/"><span><i class="fas fa-globe"></i> Public Site</span><span>Visit</span></a>
                        <a href="/services/"><span><i class="fas fa-screwdriver-wrench"></i> Services</span><span>Open</span></a>
                        <a href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener"><span><i class="fas fa-robot"></i> Chatbot</span><span>Launch</span></a>
                        <a href="/admin/analytics.php"><span><i class="fas fa-chart-line"></i> Analytics</span><span><?= (int) $todayLogins ?> logins today</span></a>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
    <script src="../assets/js/animations.js?v=2"></script>
</body>
</html>
