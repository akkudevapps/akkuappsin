<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        global $pdo;
        if (isset($_POST['create_service'])) {
            $stmt = $pdo->prepare("
                INSERT INTO service_categories
                (category_id, category_name, description, icon, base_price, sort_order, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                generateUUID(),
                trim($_POST['category_name']),
                trim($_POST['description']),
                trim($_POST['icon'] ?: 'fa-tools'),
                (float)($_POST['base_price'] ?? 0),
                (int)($_POST['sort_order'] ?? 0),
                isset($_POST['is_active']) ? 1 : 0
            ]);
            $message = 'Service category created.';
        } elseif (isset($_POST['toggle_active'])) {
            $stmt = $pdo->prepare("UPDATE service_categories SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE category_id = ?");
            $stmt->execute([$_POST['category_id']]);
            $message = 'Service status updated.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    global $pdo;
    $services = $pdo->query("SELECT * FROM service_categories ORDER BY sort_order ASC, created_at DESC")->fetchAll();
    $bookingStats = $pdo->query("SELECT status, COUNT(*) AS total FROM service_bookings GROUP BY status")->fetchAll();
} catch (Exception $e) {
    $services = [];
    $bookingStats = [];
    if ($error === '') {
        $error = 'Unable to load services: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services Management - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
<?php include '../components/admin-header.php'; ?>
<div class="dashboard-container">
    <?php include '../components/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="welcome-banner"><h1>Service Management</h1><p>Manage visible PC service cards and keep booking categories aligned with the public service pages.</p></div>
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="surface-grid">
                <section class="chart-container">
                    <h2>Create Service Category</h2>
                    <form method="POST" style="margin-top:1rem;">
                        <input type="hidden" name="create_service" value="1">
                        <div class="form-grid">
                            <div class="form-group"><label class="form-label">Category Name</label><input class="form-control" type="text" name="category_name" required></div>
                            <div class="form-group"><label class="form-label">Icon</label><input class="form-control" type="text" name="icon" value="fa-tools"></div>
                            <div class="form-group"><label class="form-label">Base Price</label><input class="form-control" type="number" name="base_price" min="0" step="0.01"></div>
                            <div class="form-group"><label class="form-label">Sort Order</label><input class="form-control" type="number" name="sort_order" value="0"></div>
                        </div>
                        <div class="form-group"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="4"></textarea></div>
                        <label class="toolbar-row muted-text" style="margin-bottom:1rem;"><input type="checkbox" name="is_active" value="1" checked> Active on public site</label>
                        <button type="submit" class="btn btn-primary">Add Service</button>
                    </form>
                </section>
                <section class="chart-container">
                    <h2>Booking Snapshot</h2>
                    <?php if (empty($bookingStats)): ?><div class="empty-state" style="margin-top:1rem;">No booking stats available.</div><?php else: ?>
                        <div class="info-grid" style="margin-top:1rem;">
                            <?php foreach ($bookingStats as $row): ?>
                                <div class="surface-card"><strong><?= htmlspecialchars(ucfirst($row['status'])) ?></strong><p class="muted-text" style="margin-top:.5rem;"><?= number_format((int)$row['total']) ?> booking(s)</p></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
            <section class="chart-container">
                <h2>Service Categories</h2>
                <?php if (empty($services)): ?><div class="empty-state" style="margin-top:1rem;">No services found.</div><?php else: ?>
                    <div class="goods-grid">
                        <?php foreach ($services as $service): ?>
                            <article class="good-card">
                                <div class="good-card-img"><i class="fas <?= htmlspecialchars($service['icon'] ?: 'fa-tools') ?>"></i></div>
                                <div class="good-card-body">
                                    <div class="good-card-title"><?= htmlspecialchars($service['category_name']) ?></div>
                                    <div class="good-card-desc"><?= htmlspecialchars($service['description'] ?: 'No description') ?></div>
                                    <div class="good-card-price">From <?= number_format((float)($service['base_price'] ?? 0), 2) ?> coins</div>
                                    <div class="toolbar-row">
                                        <span class="good-card-status <?= !empty($service['is_active']) ? 'status-active' : 'status-inactive' ?>"><?= !empty($service['is_active']) ? 'Active' : 'Inactive' ?></span>
                                        <form method="POST"><input type="hidden" name="category_id" value="<?= htmlspecialchars($service['category_id']) ?>"><button class="btn btn-secondary btn-sm" type="submit" name="toggle_active">Toggle</button></form>
                                    </div>
                                </div>
                            </article>
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
