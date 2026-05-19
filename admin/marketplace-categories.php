<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/marketplace.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit;
}

global $pdo;
$categories = [];
try {
    $categories = $pdo->query("
        SELECT c.name AS category, COUNT(p.product_id) AS items
        FROM cs_categories c
        LEFT JOIN cs_products p ON c.category_id = p.category_id AND p.is_active = 1
        GROUP BY c.category_id, c.name
        ORDER BY items DESC, c.name ASC
    ")->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Categories - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
<?php include '../components/admin-header.php'; ?>
<div class="dashboard-container">
    <?php include '../components/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="welcome-banner"><h1>Marketplace Categories</h1><p>Quick category breakdown for the new `cs_*` marketplace catalog.</p></div>
            <section class="chart-container">
                <h2>Category Totals</h2>
                <?php if (empty($categories)): ?><div class="empty-state" style="margin-top:1rem;">No product categories found.</div><?php else: ?>
                <div class="info-grid" style="margin-top:1rem;">
                    <?php foreach ($categories as $category): ?>
                        <div class="surface-card"><strong><?= htmlspecialchars($category['category'] ?: 'Uncategorized') ?></strong><p class="muted-text" style="margin-top:.5rem;"><?= number_format((int)$category['items']) ?> listing(s)</p></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
