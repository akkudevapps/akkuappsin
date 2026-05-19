<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$type = $_GET['type'] ?? '';
$services = [];
try {
    global $pdo;
    $sql = "SELECT * FROM service_categories WHERE is_active = 1";
    $params = [];
    if ($type !== '') {
        $sql .= " AND category_name LIKE ? ";
        $params[] = '%' . $type . '%';
    }
    $sql .= " ORDER BY sort_order ASC, created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC Services - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
<?php $user = getCurrentUser(); if ($user) { include '../components/header.php'; } ?>
<main class="main-content" style="<?= $user ? '' : 'padding-top:2rem;' ?>">
    <div class="page-shell">
        <div class="welcome-banner"><h1>PC Services</h1><p>Book repair, upgrade, cleaning, software, and consultation services using your AkkuApps account.</p></div>
        <?php if ($user): ?><a class="btn btn-primary" href="/user/book-service.php"><i class="fas fa-calendar-check"></i> Book a Service</a><?php endif; ?>
        <?php if (empty($services)): ?>
            <div class="info-grid">
                <div class="surface-card"><strong>PC Build</strong><p class="muted-text" style="margin-top:.5rem;">From 500 coins</p></div>
                <div class="surface-card"><strong>Repair</strong><p class="muted-text" style="margin-top:.5rem;">From 300 coins</p></div>
                <div class="surface-card"><strong>Upgrade</strong><p class="muted-text" style="margin-top:.5rem;">From 200 coins</p></div>
                <div class="surface-card"><strong>Cleaning</strong><p class="muted-text" style="margin-top:.5rem;">From 150 coins</p></div>
            </div>
        <?php else: ?>
            <div class="goods-grid">
                <?php foreach ($services as $service): ?>
                    <article class="good-card">
                        <div class="good-card-img"><i class="fas <?= htmlspecialchars($service['icon'] ?: 'fa-tools') ?>"></i></div>
                        <div class="good-card-body">
                            <div class="good-card-title"><?= htmlspecialchars($service['category_name']) ?></div>
                            <div class="good-card-desc"><?= htmlspecialchars($service['description'] ?: 'No description provided.') ?></div>
                            <div class="good-card-price">From <?= number_format((float)($service['base_price'] ?? 0), 2) ?> coins</div>
                            <a class="btn btn-primary btn-sm" href="/services/book.php?category=<?= urlencode($service['category_id']) ?>">Book Service</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
