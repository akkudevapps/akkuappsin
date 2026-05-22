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

// Additional stats
$newsPublished = 0; $newsDraft = 0; $pendingReviews = 0; $pendingBookings = 0; $newUsers7d = 0; $pendingAmount = 0;
$registrations = []; $recentUsers = []; $recentOrders = [];
try {
    $newsPublished = $pdo->query("SELECT COUNT(*) FROM news_blogs WHERE status = 'published'")->fetchColumn() ?? 0;
    $newsDraft = $pdo->query("SELECT COUNT(*) FROM news_blogs WHERE status = 'draft' OR status IS NULL")->fetchColumn() ?? 0;
    $pendingReviews = $pdo->query("SELECT COUNT(*) FROM reviews WHERE status = 'pending' OR moderated = 0")->fetchColumn() ?? 0;
    $pendingBookings = $pdo->query("SELECT COUNT(*) FROM service_bookings WHERE status = 'pending'")->fetchColumn() ?? 0;
    $newUsers7d = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn() ?? 0;
    $pendingAmount = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM upi_payments WHERE status = 'pending'")->fetchColumn() ?? 0;
    $registrations = $pdo->query("SELECT DATE(created_at) AS d, COUNT(*) AS c FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
    $recentUsers = $pdo->query("SELECT user_id, name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5")->fetchAll();
    $recentOrders = $pdo->query("SELECT id, total_amount, status, created_at FROM cs_invoices ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    error_log('Admin dashboard extra stats error: ' . $e->getMessage());
}

$regChartLabels = []; $regChartData = [];
foreach ($registrations as $r) {
    $regChartLabels[] = $r['d'];
    $regChartData[] = (int) $r['c'];
}
if (empty($regChartLabels)) {
    $regChartLabels[] = date('Y-m-d'); $regChartData[] = 0;
}
$regChartLabelsJson = json_encode($regChartLabels);
$regChartDataJson = json_encode($regChartData);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
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

        .admin-alert.info {
            background: rgba(59, 130, 246, 0.08);
            border-color: rgba(59, 130, 246, 0.18);
            color: var(--info);
        }

        .admin-alert a {
            margin-left: auto;
            text-decoration: underline;
        }

        .compact-muted {
            color: var(--text-secondary);
            font-size: var(--font-sm);
        }

        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-lg);
        }

        .chart-container {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            padding: var(--space-lg);
        }

        .chart-container h2 {
            font-size: var(--font-lg);
            margin-bottom: var(--space-sm);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .chart-container canvas {
            margin-top: var(--space-md);
            max-height: 250px;
        }

        .activity-feed {
            display: grid;
            gap: 2px;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: 10px 12px;
            border-radius: var(--border-radius-sm);
            transition: background var(--transition-fast);
        }

        .activity-item:hover {
            background: var(--bg-hover);
        }

        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .activity-icon i { font-size: .85rem; }
        .activity-info { flex: 1; min-width: 0; }
        .activity-info strong { display: block; font-size: .85rem; color: var(--text-primary); }
        .activity-info span { font-size: .75rem; color: var(--text-muted); }
        .activity-right { text-align: right; flex-shrink: 0; }
        .activity-right .badge { font-size: .7rem; padding: 2px 8px; border-radius: 999px; }

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
            <div class="welcome-banner animate-fadeIn">
                <h1>Admin Dashboard</h1>
                <p>Welcome back, <span class="highlight"><?= htmlspecialchars($user['name']) ?></span>. Full platform oversight at a glance.</p>
                <?php if ($pendingReviews > 0 || $pendingPayments > 0 || $pendingBookings > 0): ?>
                <div style="margin-top: 10px; display:flex; gap:8px; flex-wrap:wrap;">
                    <?php if ($pendingReviews > 0): ?>
                    <span style="background: #f59e0b; color: #000; padding: 4px 12px; border-radius: 12px; font-size: .8rem;">
                        <i class="fas fa-star"></i> <?= (int) $pendingReviews ?> pending review<?= $pendingReviews > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($pendingPayments > 0): ?>
                    <span style="background: #ef4444; color: #fff; padding: 4px 12px; border-radius: 12px; font-size: .8rem;">
                        <i class="fas fa-credit-card"></i> <?= (int) $pendingPayments ?> pending payment<?= $pendingPayments > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($pendingBookings > 0): ?>
                    <span style="background: #3b82f6; color: #fff; padding: 4px 12px; border-radius: 12px; font-size: .8rem;">
                        <i class="fas fa-calendar"></i> <?= (int) $pendingBookings ?> booking<?= $pendingBookings > 1 ? 's' : '' ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <div style="margin-top: 15px; display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="/admin/news.php" class="btn btn-primary btn-sm"><i class="fas fa-pen-to-square"></i> Newsroom</a>
                    <a href="/admin/marketplace.php" class="btn btn-secondary btn-sm"><i class="fas fa-store"></i> Marketplace</a>
                    <a href="/user/dashboard.php" class="btn btn-ghost btn-sm"><i class="fas fa-user"></i> User Dashboard</a>
                </div>
            </div>

            <?php if ($marketplaceStats['outOfStock'] > 0): ?>
                <div class="admin-alert danger animate-slideUp">
                    <i class="fas fa-triangle-exclamation"></i>
                    <strong><?= $marketplaceStats['outOfStock'] ?> products are out of stock.</strong>
                    <a href="/admin/marketplace.php?tab=stock&filter=out">View stock</a>
                </div>
            <?php endif; ?>

            <?php if ($marketplaceStats['lowStock'] > 0): ?>
                <div class="admin-alert warning animate-slideUp">
                    <i class="fas fa-bolt"></i>
                    <strong><?= $marketplaceStats['lowStock'] ?> products are running low.</strong>
                    <a href="/admin/marketplace.php?tab=stock&filter=low">Review low stock</a>
                </div>
            <?php endif; ?>

            <?php if ($pendingPayments > 0 && $pendingAmount > 0): ?>
                <div class="admin-alert info animate-slideUp">
                    <i class="fas fa-credit-card"></i>
                    <strong>₹<?= number_format((float) $pendingAmount) ?> in <?= (int) $pendingPayments ?> pending UPI payments.</strong>
                    <a href="/admin/payments.php">Review payments</a>
                </div>
            <?php endif; ?>

            <div class="stats-grid animate-slideUp">
                <div class="stat-card primary">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value" data-count="<?= (int) $totalUsers ?>" data-duration="1200">0</div>
                    <div class="stat-label">Total Users</div>
                </div>
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-user-plus"></i></div>
                    <div class="stat-value" data-count="<?= (int) $newUsers7d ?>" data-duration="1000">0</div>
                    <div class="stat-label">New Users (7d)</div>
                </div>
                <div class="stat-card warning">
                    <div class="stat-icon"><i class="fas fa-newspaper"></i></div>
                    <div class="stat-value" data-count="<?= (int) $newsPublished ?>" data-duration="1000">0</div>
                    <div class="stat-label">Published News</div>
                </div>
                <div class="stat-card purple">
                    <div class="stat-icon"><i class="fas fa-pen-to-square"></i></div>
                    <div class="stat-value" data-count="<?= (int) $totalPosts ?>" data-duration="1200">0</div>
                    <div class="stat-label">User Posts</div>
                </div>
            </div>

            <div class="stats-grid animate-slideUp">
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
                <div class="stat-card">
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

            <!-- Charts Section -->
            <div class="charts-section animate-slideUp">
                <div class="chart-container">
                    <h2><i class="fas fa-chart-line" style="color: var(--accent-color);"></i> User Registrations (30 days)</h2>
                    <canvas id="regChart"></canvas>
                </div>
                <div class="chart-container">
                    <h2><i class="fas fa-history" style="color: var(--text-secondary);"></i> Recent Activity</h2>
                    <div class="activity-feed">
                        <?php foreach ($recentUsers as $ru): ?>
                        <div class="activity-item">
                            <div class="activity-icon" style="background: rgba(16,185,129,.12); color: #34d399;">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="activity-info">
                                <strong><?= htmlspecialchars($ru['name']) ?></strong>
                                <span><?= htmlspecialchars($ru['email']) ?> &middot; <?= date('M j', strtotime($ru['created_at'])) ?></span>
                            </div>
                            <div class="activity-right">
                                <span class="badge" style="background: rgba(16,185,129,.12); color: #34d399;">New user</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($recentUsers)): ?>
                        <div style="text-align:center; padding:1rem; color: var(--text-muted); font-size:.85rem;">No recent users</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="admin-dashboard-grid">
                <section class="admin-card">
                    <h3><i class="fas fa-store"></i> Marketplace</h3>
                    <p class="compact-muted">Products, orders, stock, customers.</p>
                    <div class="admin-link-list">
                        <a href="/admin/marketplace.php"><span><i class="fas fa-table-cells-large"></i> Control Center</span><span><?= (int) $marketplaceStats['products'] ?> products</span></a>
                        <a href="/admin/marketplace.php?tab=orders"><span><i class="fas fa-cart-shopping"></i> Orders</span><span><?= (int) $marketplaceStats['orders'] ?> recent</span></a>
                        <a href="/admin/marketplace.php?tab=stock"><span><i class="fas fa-boxes-stacked"></i> Stock</span><span><?= (int) ($marketplaceStats['lowStock'] + $marketplaceStats['outOfStock']) ?> alerts</span></a>
                        <a href="/admin/marketplace-categories.php"><span><i class="fas fa-tags"></i> Categories</span><span><?= (int) $marketplaceStats['categories'] ?> cats</span></a>
                    </div>
                </section>

                <section class="admin-card">
                    <h3><i class="fas fa-newspaper"></i> Content & Community</h3>
                    <p class="compact-muted">News, blogs, reviews, and moderation.</p>
                    <div class="admin-link-list">
                        <a href="/admin/news.php"><span><i class="fas fa-pen-to-square"></i> Newsroom Engine</span><span><?= (int) $newsPublished ?> pub / <?= (int) $newsDraft ?> draft</span></a>
                        <a href="/admin/reviews.php"><span><i class="fas fa-star"></i> Reviews</span><span><?= (int) $pendingReviews ?> pending</span></a>
                        <a href="/admin/content.php"><span><i class="fas fa-shield-halved"></i> Content Moderation</span><span>Review</span></a>
                        <a href="/news/"><span><i class="fas fa-newspaper"></i> Public News</span><span>Open</span></a>
                    </div>
                </section>

                <section class="admin-card">
                    <h3><i class="fas fa-users-gear"></i> User Ops</h3>
                    <p class="compact-muted">Accounts, payments, types, and user dashboard.</p>
                    <div class="admin-link-list">
                        <a href="/admin/users.php"><span><i class="fas fa-users"></i> User Management</span><span><?= (int) $totalUsers ?> users</span></a>
                        <a href="/admin/payments.php"><span><i class="fas fa-credit-card"></i> UPI Payments</span><span><?= (int) $pendingPayments ?> pending</span></a>
                        <a href="/admin/usertypes.php"><span><i class="fas fa-tag"></i> User Types</span><span>Manage</span></a>
                        <a href="/user/dashboard.php"><span><i class="fas fa-user"></i> User Dashboard</span><span>Open</span></a>
                    </div>
                </section>

                <section class="admin-card">
                    <h3><i class="fas fa-screwdriver-wrench"></i> Services & Bookings</h3>
                    <p class="compact-muted">PC services, bookings, and scheduling.</p>
                    <div class="admin-link-list">
                        <a href="/admin/services.php"><span><i class="fas fa-tools"></i> Manage Services</span><span>Edit</span></a>
                        <a href="/admin/service-bookings.php"><span><i class="fas fa-calendar-check"></i> Service Bookings</span><span><?= (int) $pendingBookings ?> pending</span></a>
                        <a href="/services/"><span><i class="fas fa-globe"></i> Public Services</span><span>Open</span></a>
                    </div>
                </section>

                <section class="admin-card">
                    <h3><i class="fas fa-coins"></i> Coin Economy</h3>
                    <p class="compact-muted">Coin packages, collection box, digital goods.</p>
                    <div class="admin-link-list">
                        <a href="/admin/coinpackages.php"><span><i class="fas fa-box"></i> Coin Packages</span><span>Manage</span></a>
                        <a href="/admin/collectionbox.php"><span><i class="fas fa-piggy-bank"></i> Collection Box</span><span>Manage</span></a>
                        <a href="/admin/digitalgoods.php"><span><i class="fas fa-download"></i> Digital Goods</span><span>Manage</span></a>
                    </div>
                </section>

                <section class="admin-card">
                    <h3><i class="fas fa-bullhorn"></i> Platform Tools</h3>
                    <p class="compact-muted">Advertising, analytics, and quick public links.</p>
                    <div class="admin-link-list">
                        <a href="/admin/advertising.php"><span><i class="fas fa-ad"></i> Advertising</span><span>Manage</span></a>
                        <a href="/admin/analytics.php"><span><i class="fas fa-chart-line"></i> Analytics</span><span><?= (int) $todayLogins ?> logins today</span></a>
                        <a href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener"><span><i class="fas fa-robot"></i> Chatbot</span><span>Launch</span></a>
                        <a href="/"><span><i class="fas fa-globe"></i> Public Site</span><span>Visit</span></a>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Animate stat counters
        document.querySelectorAll('.stat-value[data-count]').forEach(function(el) {
            var target = parseInt(el.dataset.count);
            var duration = parseInt(el.dataset.duration) || 1000;
            var prefix = el.dataset.prefix || '';
            var start = 0;
            var step = Math.max(1, Math.floor(target / (duration / 16)));
            var timer = setInterval(function() {
                start += step;
                if (start >= target) { start = target; clearInterval(timer); }
                el.textContent = prefix + start.toLocaleString();
            }, 16);
        });

        // Registration chart
        var ctx = document.getElementById('regChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= $regChartLabelsJson ?>,
                    datasets: [{
                        label: 'Registrations',
                        data: <?= $regChartDataJson ?>,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99,102,241,0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3,
                        pointBackgroundColor: '#6366f1',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: '#6b7280', maxTicksLimit: 10, font: { size: 10 } }, grid: { color: 'rgba(107,114,128,0.1)' } },
                        y: { beginAtZero: true, ticks: { color: '#6b7280', font: { size: 10 }, stepSize: 1 }, grid: { color: 'rgba(107,114,128,0.1)' } }
                    }
                }
            });
        }

        // Slide-up animation for elements
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll('.animate-slideUp').forEach(function(el) {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            observer.observe(el);
        });
    });
    </script>
</body>
</html>
