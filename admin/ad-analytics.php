<?php
/**
 * Admin Ad Analytics Dashboard Page
 * Real-time performance tracking with interactive charts
 * URL: /admin/ad-analytics.php
 */

define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ad-engine.php';
require_once '../includes/payment-engine.php';

// Verify admin access
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

global $pdo;

// Fetch filters
$filterProvider = trim($_GET['provider_id'] ?? '');
$filterAd = trim($_GET['ad_id'] ?? '');
$filterRange = trim($_GET['range'] ?? '30'); // Default last 30 days
$startDate = trim($_GET['start_date'] ?? '');
$endDate = trim($_GET['end_date'] ?? '');

// Calculate date range
if ($filterRange === 'custom' && !empty($startDate) && !empty($endDate)) {
    $dateStart = $startDate;
    $dateEnd = $endDate;
} else {
    $days = intval($filterRange) ?: 30;
    $dateEnd = date('Y-m-d');
    $dateStart = date('Y-m-d', strtotime("-$days days"));
}

// Build query conditions
$whereConditions = ["aa.analytics_date BETWEEN ? AND ?"];
$params = [$dateStart, $dateEnd];

if (!empty($filterProvider)) {
    $whereConditions[] = "a.provider_id = ?";
    $params[] = $filterProvider;
}

if (!empty($filterAd)) {
    $whereConditions[] = "aa.ad_id = ?";
    $params[] = $filterAd;
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// 1. Fetch Summary Metrics
$sqlSummary = "
    SELECT 
        SUM(COALESCE(aa.impressions, 0)) as total_impressions,
        SUM(COALESCE(aa.clicks, 0)) as total_clicks,
        SUM(COALESCE(aa.spend_today, 0)) as total_spend,
        CASE 
            WHEN SUM(COALESCE(aa.impressions, 0)) > 0 
            THEN (SUM(COALESCE(aa.clicks, 0)) / SUM(COALESCE(aa.impressions, 0))) * 100 
            ELSE 0 
        END as avg_ctr
    FROM ad_analytics aa
    LEFT JOIN advertisements a ON aa.ad_id = a.id
    {$whereClause}
";
$stmtSummary = $pdo->prepare($sqlSummary);
$stmtSummary->execute($params);
$summary = $stmtSummary->fetch();

// 2. Fetch Daily Trend for Chart
$sqlDaily = "
    SELECT 
        aa.analytics_date,
        SUM(COALESCE(aa.impressions, 0)) as impressions,
        SUM(COALESCE(aa.clicks, 0)) as clicks,
        SUM(COALESCE(aa.spend_today, 0)) as spend,
        CASE 
            WHEN SUM(COALESCE(aa.impressions, 0)) > 0 
            THEN (SUM(COALESCE(aa.clicks, 0)) / SUM(COALESCE(aa.impressions, 0))) * 100 
            ELSE 0 
        END as ctr
    FROM ad_analytics aa
    LEFT JOIN advertisements a ON aa.ad_id = a.id
    {$whereClause}
    GROUP BY aa.analytics_date
    ORDER BY aa.analytics_date ASC
";
$stmtDaily = $pdo->prepare($sqlDaily);
$stmtDaily->execute($params);
$dailyData = $stmtDaily->fetchAll();

// 3. Fetch Top Performing Ads
$sqlTopAds = "
    SELECT 
        a.id,
        a.title,
        p.company_name,
        pt.display_name as size_name,
        SUM(COALESCE(aa.impressions, 0)) as impressions,
        SUM(COALESCE(aa.clicks, 0)) as clicks,
        SUM(COALESCE(aa.spend_today, 0)) as spend,
        CASE 
            WHEN SUM(COALESCE(aa.impressions, 0)) > 0 
            THEN (SUM(COALESCE(aa.clicks, 0)) / SUM(COALESCE(aa.impressions, 0))) * 100 
            ELSE 0 
        END as ctr
    FROM advertisements a
    LEFT JOIN ad_analytics aa ON a.id = aa.ad_id AND aa.analytics_date BETWEEN ? AND ?
    LEFT JOIN ad_providers p ON a.provider_id = p.id
    LEFT JOIN ad_pricing_tiers pt ON a.ad_size_id = pt.id
    " . (!empty($filterProvider) ? "WHERE a.provider_id = ?" : "") . "
    GROUP BY a.id, a.title, p.company_name, pt.display_name
    ORDER BY impressions DESC
    LIMIT 10
";
$topAdsParams = [$dateStart, $dateEnd];
if (!empty($filterProvider)) {
    $topAdsParams[] = $filterProvider;
}
$stmtTopAds = $pdo->prepare($sqlTopAds);
$stmtTopAds->execute($topAdsParams);
$topAds = $stmtTopAds->fetchAll();

// 4. Fetch Providers and Ads for Dropdowns
$providers = $pdo->query("SELECT id, company_name FROM ad_providers WHERE status = 'approved' ORDER BY company_name")->fetchAll();
$ads = $pdo->query("SELECT id, title FROM advertisements ORDER BY title")->fetchAll();

// Format data for chart JSON
$chartLabels = [];
$chartImpressions = [];
$chartClicks = [];
$chartCtr = [];
$chartSpend = [];

foreach ($dailyData as $row) {
    $chartLabels[] = date('M d', strtotime($row['analytics_date']));
    $chartImpressions[] = intval($row['impressions']);
    $chartClicks[] = intval($row['clicks']);
    $chartCtr[] = round(floatval($row['ctr']), 2);
    $chartSpend[] = round(floatval($row['spend']), 2);
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Real-time Analytics - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 2rem; color: var(--text-primary); }
        
        .filters-section { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid var(--border-color); }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; align-items: flex-end; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary); font-size: 0.9rem; }
        .form-group input, .form-group select { padding: 0.65rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-primary); font-size: 0.95rem; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); }
        .btn { padding: 0.65rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { opacity: 0.9; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem; }
        .stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; background: rgba(99, 102, 241, 0.1); color: var(--primary); }
        .stat-icon.clicks { background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .stat-icon.ctr { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
        .stat-icon.spend { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
        .stat-details { display: flex; flex-direction: column; }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--text-primary); }
        .stat-label { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }
        
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        .chart-card { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); min-height: 400px; }
        .chart-card h3 { font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.5rem; font-weight: 600; }
        
        .table-section { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 2rem; }
        .table-section h3 { font-size: 1.1rem; color: var(--text-primary); margin-bottom: 1.2rem; font-weight: 600; }
        .analytics-table { width: 100%; border-collapse: collapse; }
        .analytics-table th { padding: 1rem; text-align: left; font-weight: 600; color: var(--text-primary); border-bottom: 2px solid var(--border-color); background: var(--bg-hover); }
        .analytics-table td { padding: 0.875rem 1rem; border-bottom: 1px solid var(--border-color); color: var(--text-primary); }
        .analytics-table tbody tr:hover { background: var(--bg-hover); }
        
        .custom-dates { display: none; }
        .custom-dates.active { display: flex; gap: 1rem; }
        
        @media (max-width: 992px) {
            .charts-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .filter-row { grid-template-columns: 1fr; }
            .analytics-table { font-size: 0.9rem; }
            .analytics-table th, .analytics-table td { padding: 0.5rem; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-chart-line"></i> Ad Real-time Analytics</h1>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Track impressions, clicks, CTR and daily expenditure</p>
            </div>
            <div>
                <a href="/admin/advertisements.php" class="btn btn-secondary">
                    <i class="fas fa-ad"></i> Manage Ads
                </a>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="form-group">
                        <label>Date Range</label>
                        <select name="range" id="rangeSelect" onchange="toggleCustomDates(this.value)">
                            <option value="7" <?= $filterRange === '7' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="30" <?= $filterRange === '30' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="90" <?= $filterRange === '90' ? 'selected' : '' ?>>Last 90 Days</option>
                            <option value="custom" <?= $filterRange === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                        </select>
                    </div>
                    
                    <div class="form-group custom-dates <?= $filterRange === 'custom' ? 'active' : '' ?>" id="customDates">
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Provider</label>
                        <select name="provider_id">
                            <option value="">All Providers</option>
                            <?php foreach ($providers as $prov): ?>
                                <option value="<?= $prov['id'] ?>" <?= $filterProvider === $prov['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($prov['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Specific Ad</label>
                        <select name="ad_id">
                            <option value="">All Advertisements</option>
                            <?php foreach ($ads as $adItem): ?>
                                <option value="<?= $adItem['id'] ?>" <?= $filterAd === $adItem['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($adItem['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-eye"></i></div>
                <div class="stat-details">
                    <div class="stat-value"><?= number_format($summary['total_impressions'] ?? 0) ?></div>
                    <div class="stat-label">Total Impressions</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon clicks"><i class="fas fa-mouse-pointer"></i></div>
                <div class="stat-details">
                    <div class="stat-value"><?= number_format($summary['total_clicks'] ?? 0) ?></div>
                    <div class="stat-label">Total Clicks</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon ctr"><i class="fas fa-percent"></i></div>
                <div class="stat-details">
                    <div class="stat-value"><?= number_format($summary['avg_ctr'] ?? 0, 2) ?>%</div>
                    <div class="stat-label">Average CTR</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon spend"><i class="fas fa-wallet"></i></div>
                <div class="stat-details">
                    <div class="stat-value">$<?= number_format($summary['total_spend'] ?? 0, 2) ?></div>
                    <div class="stat-label">Total Spend (USD)</div>
                </div>
            </div>
        </div>
        
        <!-- Charts Grid -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3>Impressions vs Clicks Trend</h3>
                <canvas id="impressionsClicksChart"></canvas>
            </div>
            
            <div class="chart-card">
                <h3>Click-Through Rate (CTR) Trend</h3>
                <canvas id="ctrChart"></canvas>
            </div>
        </div>
        
        <!-- Table Section -->
        <div class="table-section">
            <h3>Top Performing Advertisements</h3>
            <div style="overflow-x: auto;">
                <table class="analytics-table">
                    <thead>
                        <tr>
                            <th>Ad Title</th>
                            <th>Provider</th>
                            <th>Ad Size</th>
                            <th>Impressions</th>
                            <th>Clicks</th>
                            <th>CTR</th>
                            <th>Spend (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($topAds)): ?>
                            <?php foreach ($topAds as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['company_name'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($row['size_name'] ?? 'N/A') ?></td>
                                    <td><?= number_format($row['impressions']) ?></td>
                                    <td><?= number_format($row['clicks']) ?></td>
                                    <td><strong><?= number_format($row['ctr'], 2) ?>%</strong></td>
                                    <td>$<?= number_format($row['spend'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted);">No analytics data found for the selected filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function toggleCustomDates(val) {
            const container = document.getElementById('customDates');
            if (val === 'custom') {
                container.classList.add('active');
            } else {
                container.classList.remove('active');
            }
        }
        
        // Render Chart.js
        const labels = <?= json_encode($chartLabels) ?>;
        const impressionsData = <?= json_encode($chartImpressions) ?>;
        const clicksData = <?= json_encode($chartClicks) ?>;
        const ctrData = <?= json_encode($chartCtr) ?>;
        const spendData = <?= json_encode($chartSpend) ?>;
        
        // 1. Impressions vs Clicks Line Chart
        const ctxImpClicks = document.getElementById('impressionsClicksChart').getContext('2d');
        new Chart(ctxImpClicks, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Impressions',
                        data: impressionsData,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Clicks',
                        data: clicksData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: { color: '#94a3b8' },
                        grid: { color: 'rgba(255,255,255,0.05)' }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        ticks: { color: '#94a3b8' },
                        grid: { drawOnChartArea: false } // Avoid overlapping grid lines
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: '#f8fafc' }
                    }
                }
            }
        });
        
        // 2. CTR Line Chart
        const ctxCtr = document.getElementById('ctrChart').getContext('2d');
        new Chart(ctxCtr, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'CTR %',
                        data: ctrData,
                        borderColor: '#ffc107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        borderWidth: 3,
                        tension: 0.3,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    },
                    y: {
                        ticks: {
                            color: '#94a3b8',
                            callback: function(value) { return value + '%'; }
                        },
                        grid: { color: 'rgba(255,255,255,0.05)' }
                    }
                },
                plugins: {
                    legend: {
                        labels: { color: '#f8fafc' }
                    }
                }
            }
        });
    </script>
</body>
</html>
