<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit;
}

try {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT
            transaction_type,
            COUNT(*) AS total_transactions,
            SUM(fee_amount) AS total_collected,
            AVG(fee_amount) AS avg_fee,
            MAX(created_at) AS last_transaction
        FROM akku_collection_box
        GROUP BY transaction_type
        ORDER BY total_collected DESC
    ");
    $stmt->execute();
    $treasurySummary = $stmt->fetchAll();
} catch (Exception $e) {
    $treasurySummary = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT c.*, u1.name AS source_name, u2.name AS target_name
        FROM akku_collection_box c
        LEFT JOIN users u1 ON c.source_user_id = u1.user_id
        LEFT JOIN users u2 ON c.target_user_id = u2.user_id
        ORDER BY c.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $recentTransactions = $stmt->fetchAll();
} catch (Exception $e) {
    $recentTransactions = [];
}

$totalCollected = array_sum(array_map(static function ($row) {
    return (float)($row['total_collected'] ?? 0);
}, $treasurySummary));

try {
    $stmt = $pdo->prepare("SELECT SUM(coin_balance) FROM users");
    $stmt->execute();
    $totalCoinsInCirculation = (float)($stmt->fetchColumn() ?: 0);
} catch (Exception $e) {
    $totalCoinsInCirculation = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treasury Dashboard - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        @media (max-width: 768px) {
            .welcome-banner h1 { font-size: 1.3rem !important; }
            .welcome-banner p { font-size: 0.85rem !important; }
            .stats-grid { grid-template-columns: repeat(2, 1fr) !important; gap: 0.75rem !important; }
            .stat-card { padding: 1rem !important; }
            .stat-info h3 { font-size: 1.2rem !important; }
            .surface-grid { grid-template-columns: 1fr !important; }
            .page-shell { padding: 0.75rem !important; }
            .info-grid { grid-template-columns: 1fr !important; }
            .page-head { flex-direction: column !important; gap: 0.5rem !important; }
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
            <div class="page-shell">
                <div class="welcome-banner animate-fadeIn">
                    <h1>Treasury Dashboard</h1>
                    <p>Track every platform fee, creator commission, and admin-side digital-goods sale in one place.</p>
                </div>

                <div class="stats-grid animate-slideUp">
                    <div class="stat-card">
                        <div class="stat-icon bg-green">
                            <i class="fas fa-vault"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= number_format($totalCollected, 2) ?></h3>
                            <p>Total Treasury Coins</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon bg-blue">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= number_format($totalCoinsInCirculation, 2) ?></h3>
                            <p>Coins in Circulation</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon bg-purple">
                            <i class="fas fa-sitemap"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= count($treasurySummary) ?></h3>
                            <p>Revenue Channels</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon bg-orange">
                            <i class="fas fa-clock-rotate-left"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?= count($recentTransactions) ?></h3>
                            <p>Recent Entries</p>
                        </div>
                    </div>
                </div>

                <div class="surface-grid animate-slideUp">
                    <section class="surface-card">
                        <div class="page-head">
                            <div class="page-head-copy">
                                <h2>Treasury Logic</h2>
                                <p>The current flow centralizes platform income from likes, comments, reposts, post creation, subscriptions, and digital-goods sales.</p>
                            </div>
                            <span class="treasury-badge"><i class="fas fa-shield-halved"></i> Centralized Collection</span>
                        </div>

                        <div class="info-grid" style="margin-top: 1rem;">
                            <div class="info-note">
                                <strong>Post actions</strong><br>
                                Like and comment costs split creator rewards from platform fees. Post creation routes the full creation fee into treasury.
                            </div>
                            <div class="info-note">
                                <strong>Creator monetization</strong><br>
                                Subscriptions keep the creator share with the creator and send the platform commission into treasury.
                            </div>
                            <div class="info-note">
                                <strong>Digital goods</strong><br>
                                Badge sales are treated as admin-owned inventory and route their sale value to treasury. Gift sending and conversion fees also accumulate here.
                            </div>
                        </div>
                    </section>

                    <section class="surface-card">
                        <h2>Top Revenue Sources</h2>
                        <?php if (empty($treasurySummary)): ?>
                            <div class="empty-state" style="margin-top: 1rem;">No treasury transactions recorded yet.</div>
                        <?php else: ?>
                            <div class="activity-list" style="margin-top: 1rem;">
                                <?php foreach ($treasurySummary as $summary): ?>
                                    <div class="activity-item">
                                        <div class="activity-main">
                                            <i class="fas fa-arrow-trend-up"></i>
                                            <div class="activity-copy">
                                                <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $summary['transaction_type']))) ?></strong>
                                                <small>
                                                    <?= number_format((int)$summary['total_transactions']) ?> entries
                                                    <?php if (!empty($summary['last_transaction'])): ?>
                                                        • Last: <?= date('M j, Y g:i A', strtotime($summary['last_transaction'])) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="activity-value">
                                            <strong><?= number_format((float)$summary['total_collected'], 2) ?></strong>
                                            <small>Avg <?= number_format((float)$summary['avg_fee'], 2) ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <section class="chart-container animate-slideUp">
                    <div class="page-head">
                        <div class="page-head-copy">
                            <h2>Recent Treasury Transactions</h2>
                            <p>UUID-backed joins now keep source and target users visible instead of collapsing them into unknown rows.</p>
                        </div>
                    </div>

                    <?php if (empty($recentTransactions)): ?>
                        <div class="empty-state" style="margin-top: 1rem;">No recent treasury activity.</div>
                    <?php else: ?>
                        <div class="table-responsive" style="margin-top: 1rem;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Gross</th>
                                        <th>Treasury</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>When</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $txn): ?>
                                        <tr>
                                            <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $txn['transaction_type']))) ?></td>
                                            <td><?= number_format((float)($txn['gross_amount'] ?? 0), 2) ?></td>
                                            <td><?= number_format((float)($txn['fee_amount'] ?? 0), 2) ?></td>
                                            <td><?= htmlspecialchars($txn['source_name'] ?? 'System') ?></td>
                                            <td><?= htmlspecialchars($txn['target_name'] ?? 'Treasury') ?></td>
                                            <td><?= !empty($txn['created_at']) ? date('M j, Y g:i A', strtotime($txn['created_at'])) : '-' ?></td>
                                            <td><?= htmlspecialchars($txn['description'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
