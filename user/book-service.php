<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: /auth/login.php?redirect=' . urlencode('/user/book-service.php'));
    exit;
}

$message = '';
$error = '';
try {
    global $pdo;
    $services = $pdo->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC")->fetchAll();
} catch (Exception $e) {
    $services = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        global $pdo;
        $selected = null;
        foreach ($services as $service) {
            if (($service['category_id'] ?? null) === ($_POST['category_id'] ?? null)) {
                $selected = $service;
                break;
            }
        }
        $totalPrice = (float)($selected['base_price'] ?? 0);
        $stmt = $pdo->prepare("
            INSERT INTO service_bookings
            (booking_id, user_id, category_id, preferred_date, total_price, notes, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        $stmt->execute([
            generateUUID(),
            $user['user_id'],
            $_POST['category_id'],
            $_POST['preferred_date'],
            $totalPrice,
            trim($_POST['notes'] ?? '')
        ]);
        $message = 'Service booking submitted.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Service - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
<?php include '../components/header.php'; ?>
<div class="dashboard-container">
    <?php include '../components/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="welcome-banner"><h1>Book PC Service</h1><p>Select a service category, review the base pricing, and request your preferred appointment date.</p></div>
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="surface-grid">
                <section class="chart-container">
                    <h2>Available Services</h2>
                    <?php if (empty($services)): ?><div class="empty-state" style="margin-top:1rem;">No active services available right now.</div><?php else: ?>
                    <div class="goods-grid">
                        <?php foreach ($services as $service): ?>
                            <article class="good-card">
                                <div class="good-card-img"><i class="fas <?= htmlspecialchars($service['icon'] ?: 'fa-tools') ?>"></i></div>
                                <div class="good-card-body">
                                    <div class="good-card-title"><?= htmlspecialchars($service['category_name']) ?></div>
                                    <div class="good-card-desc"><?= htmlspecialchars($service['description'] ?: 'No description') ?></div>
                                    <div class="good-card-price">From <?= number_format((float)($service['base_price'] ?? 0), 2) ?> coins</div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
                <section class="chart-container">
                    <h2>Booking Form</h2>
                    <form method="POST" style="margin-top:1rem;">
                        <div class="form-group"><label class="form-label">Service Category</label><select class="form-control" name="category_id" required><?php foreach ($services as $service): ?><option value="<?= htmlspecialchars($service['category_id']) ?>"><?= htmlspecialchars($service['category_name']) ?> - <?= number_format((float)($service['base_price'] ?? 0), 2) ?> coins</option><?php endforeach; ?></select></div>
                        <div class="form-group"><label class="form-label">Preferred Date</label><input class="form-control" type="date" name="preferred_date" required></div>
                        <div class="form-group"><label class="form-label">Notes / Summary</label><textarea class="form-control" name="notes" rows="6" placeholder="Describe the issue, build goals, or upgrade request"></textarea></div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-check"></i> Book Service</button>
                    </form>
                </section>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
