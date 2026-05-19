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
$statusFilter = $_GET['status'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'], $_POST['status'])) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE service_bookings SET status = ?, updated_at = NOW() WHERE booking_id = ?");
        $stmt->execute([$_POST['status'], $_POST['booking_id']]);
        $message = 'Booking status updated.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    global $pdo;
    $sql = "
        SELECT b.*, u.name AS customer_name, s.category_name
        FROM service_bookings b
        LEFT JOIN users u ON b.user_id = u.user_id
        LEFT JOIN service_categories s ON b.category_id = s.category_id
    ";
    $params = [];
    if ($statusFilter !== 'all') {
        $sql .= " WHERE b.status = ? ";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY b.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $bookings = [];
    if ($error === '') {
        $error = 'Unable to load service bookings: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Bookings - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
<?php include '../components/admin-header.php'; ?>
<div class="dashboard-container">
    <?php include '../components/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="welcome-banner"><h1>Service Bookings</h1><p>Track pending, in-progress, and completed service requests from one place.</p></div>
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="segment-links">
                <a class="segment-link <?= $statusFilter === 'all' ? 'active' : '' ?>" href="/admin/service-bookings.php">All</a>
                <a class="segment-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="/admin/service-bookings.php?status=pending">Pending</a>
                <a class="segment-link <?= $statusFilter === 'confirmed' ? 'active' : '' ?>" href="/admin/service-bookings.php?status=confirmed">Confirmed</a>
                <a class="segment-link <?= $statusFilter === 'completed' ? 'active' : '' ?>" href="/admin/service-bookings.php?status=completed">Completed</a>
            </div>
            <section class="chart-container">
                <h2>Bookings</h2>
                <?php if (empty($bookings)): ?><div class="empty-state" style="margin-top:1rem;">No bookings found.</div><?php else: ?>
                <div class="table-responsive" style="margin-top:1rem;">
                    <table>
                        <thead><tr><th>Customer</th><th>Service</th><th>Date</th><th>Price</th><th>Status</th><th>Notes</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?= htmlspecialchars($booking['customer_name'] ?? 'Unknown') ?></td>
                                <td><?= htmlspecialchars($booking['category_name'] ?? ($booking['service_name'] ?? 'Service')) ?></td>
                                <td><?= htmlspecialchars($booking['preferred_date'] ?? $booking['booking_date'] ?? '-') ?></td>
                                <td><?= number_format((float)($booking['total_price'] ?? $booking['base_price'] ?? 0), 2) ?></td>
                                <td><?= htmlspecialchars($booking['status'] ?? 'pending') ?></td>
                                <td><?= htmlspecialchars(substr($booking['notes'] ?? $booking['requirements'] ?? '', 0, 80)) ?></td>
                                <td>
                                    <div class="toolbar-row">
                                        <form method="POST"><input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['booking_id']) ?>"><input type="hidden" name="status" value="confirmed"><button class="btn btn-secondary btn-sm" type="submit">Confirm</button></form>
                                        <form method="POST"><input type="hidden" name="booking_id" value="<?= htmlspecialchars($booking['booking_id']) ?>"><input type="hidden" name="status" value="completed"><button class="btn btn-success btn-sm" type="submit">Complete</button></form>
                                    </div>
                                </td>
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
