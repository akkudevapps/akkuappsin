<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit;
}

// Get analytics data
try {
    global $pdo;
    
    // User statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $newUsers30Days = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    $totalUsers = $stmt->fetchColumn();
    
    // Post statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_posts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $newPosts30Days = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_posts");
    $stmt->execute();
    $totalPosts = $stmt->fetchColumn();
    
    // Engagement statistics
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_likes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $likes30Days = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM post_comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $comments30Days = $stmt->fetchColumn();
    
    // Revenue statistics
    $stmt = $pdo->prepare("SELECT SUM(fee_amount) FROM akku_collection_box WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $revenue30Days = $stmt->fetchColumn() ?: 0;
    
    // Daily user growth data (last 30 days)
    $stmt = $pdo->prepare("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stmt->execute();
    $dailyUserGrowth = $stmt->fetchAll();
    
    // Top creators by engagement
    $stmt = $pdo->prepare("
        SELECT u.name, u.user_id, 
               COUNT(pl.like_id) as likes_received,
               COUNT(pc.comment_id) as comments_received
        FROM users u
        LEFT JOIN user_posts up ON u.user_id = up.user_id
        LEFT JOIN post_likes pl ON up.post_id = pl.post_id
        LEFT JOIN post_comments pc ON up.post_id = pc.post_id
        WHERE u.role != 'admin'
        GROUP BY u.user_id
        ORDER BY (COUNT(pl.like_id) + COUNT(pc.comment_id)) DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topCreators = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Analytics error: " . $e->getMessage());
    $newUsers30Days = 0;
    $totalUsers = 0;
    $newPosts30Days = 0;
    $totalPosts = 0;
    $likes30Days = 0;
    $comments30Days = 0;
    $revenue30Days = 0;
    $dailyUserGrowth = [];
    $topCreators = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @media (max-width: 768px) {
            .welcome-banner h1 { font-size: 1.3rem !important; }
            .welcome-banner p { font-size: 0.85rem !important; }
            .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.75rem !important; }
            .stat-card { padding: 1rem !important; }
            .stat-info h3 { font-size: 1.2rem !important; }
            .charts-section { grid-template-columns: 1fr !important; }
            .chart-container { padding: 1rem !important; }
            .chart-container h2 { font-size: 1.1rem !important; }
            .chart-container canvas { height: 200px !important; }
            div[style*="grid-template-columns: 1fr 1fr"] { grid-template-columns: 1fr !important; }
            .page-shell { padding: 0.75rem !important; }
        }
        @media (max-width: 480px) {
            .welcome-banner h1 { font-size: 1.1rem !important; }
            .stats-grid { grid-template-columns: 1fr 1fr !important; }
            .stat-info h3 { font-size: 1rem !important; }
        }
    </style>
</head>
<body>
    <?php include '../components/admin-header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/admin-sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Analytics Dashboard</h1>
                <p>Platform performance and growth metrics</p>
            </div>

            <!-- Summary Cards -->
            <div class="stats-grid animate-slideUp">
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($totalUsers) ?></h3>
                        <p>Total Users</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-green">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($newUsers30Days) ?></h3>
                        <p>New Users (30 days)</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-purple">
                        <i class="fas fa-newspaper"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($totalPosts) ?></h3>
                        <p>Total Posts</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-orange">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3>₹<?= number_format($revenue30Days, 2) ?></h3>
                        <p>Revenue (30 days)</p>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section animate-slideUp">
                <div class="chart-container">
                    <h2>User Growth (Last 30 Days)</h2>
                    <canvas id="userGrowthChart" style="height: 300px;"></canvas>
                </div>
                
                <div class="chart-container">
                    <h2>Engagement Metrics</h2>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                        <div style="text-align: center; padding: 20px; background: var(--secondary-bg); border-radius: 12px;">
                            <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;">
                                <i class="fas fa-heart"></i>
                            </div>
                            <h3><?= number_format($likes30Days) ?></h3>
                            <p>Likes in 30 days</p>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: var(--secondary-bg); border-radius: 12px;">
                            <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;">
                                <i class="fas fa-comment"></i>
                            </div>
                            <h3><?= number_format($comments30Days) ?></h3>
                            <p>Comments in 30 days</p>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: var(--secondary-bg); border-radius: 12px;">
                            <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <h3><?= number_format($newPosts30Days) ?></h3>
                            <p>New Posts in 30 days</p>
                        </div>
                        
                        <div style="text-align: center; padding: 20px; background: var(--secondary-bg); border-radius: 12px;">
                            <div style="font-size: 2rem; color: var(--accent-color); margin-bottom: 10px;">
                                <i class="fas fa-share"></i>
                            </div>
                            <h3><?= number_format($likes30Days + $comments30Days) ?></h3>
                            <p>Total Engagements</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Creators -->
            <div class="chart-container animate-slideUp">
                <h2>Top Creators by Engagement</h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($topCreators)): ?>
                        <p style="color: var(--text-secondary); text-align: center;">No creator data available</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                            <?php foreach ($topCreators as $creator): ?>
                                <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; text-align: center;">
                                    <h4 style="color: var(--text-primary); margin-bottom: 10px;"><?= htmlspecialchars($creator['name']) ?></h4>
                                    <div style="display: flex; justify-content: space-around; margin-top: 15px;">
                                        <div>
                                            <div style="font-size: 1.5rem; color: #ef4444;">
                                                <i class="fas fa-heart"></i>
                                            </div>
                                            <div style="color: var(--text-primary);"><?= number_format($creator['likes_received']) ?></div>
                                            <div style="font-size: 0.8em; color: var(--text-secondary);">Likes</div>
                                        </div>
                                        <div>
                                            <div style="font-size: 1.5rem; color: #10b981;">
                                                <i class="fas fa-comment"></i>
                                            </div>
                                            <div style="color: var(--text-primary);"><?= number_format($creator['comments_received']) ?></div>
                                            <div style="font-size: 0.8em; color: var(--text-secondary);">Comments</div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
    <script>
        // User Growth Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('userGrowthChart').getContext('2d');
            
            // Prepare data
            const dates = [
                <?php 
                $dateLabels = [];
                $userCounts = [];
                $currentDate = new DateTime();
                for ($i = 29; $i >= 0; $i--) {
                    $date = clone $currentDate;
                    $date->sub(new DateInterval("P{$i}D"));
                    $dateLabels[] = $date->format('M j');
                    $userCounts[] = 0;
                }
                
                // Fill in actual data
                foreach ($dailyUserGrowth as $data) {
                    $index = array_search(date('M j', strtotime($data['date'])), $dateLabels);
                    if ($index !== false) {
                        $userCounts[$index] = $data['count'];
                    }
                }
                
                echo '"' . implode('", "', $dateLabels) . '"';
                ?>
            ];
            
            const counts = [<?= implode(', ', $userCounts) ?>];
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets: [{
                        label: 'New Users',
                        data: counts,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        },
                        x: {
                            grid: { display: false }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
