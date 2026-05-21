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

$message = '';
$error = '';
$statusFilter = $_GET['status'] ?? 'all';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id'], $_POST['status'])) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("UPDATE product_reviews SET status = ?, updated_at = NOW() WHERE review_id = ?");
        $stmt->execute([$_POST['status'], $_POST['review_id']]);
        $message = 'Review status updated.';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

try {
    global $pdo;
    $sql = "
        SELECT r.*, u.name AS reviewer_name, p.name AS product_name, img.image_url AS product_image, c.name AS category
        FROM product_reviews r
        LEFT JOIN users u ON r.reviewer_id = u.user_id
        LEFT JOIN cs_products p ON r.product_id = p.product_id
        LEFT JOIN cs_categories c ON p.category_id = c.category_id
        LEFT JOIN cs_product_images img ON p.product_id = img.product_id AND img.is_primary = 1
    ";
    $params = [];
    if ($statusFilter !== 'all') {
        $sql .= " WHERE r.status = ? ";
        $params[] = $statusFilter;
    }
    $sql .= " ORDER BY r.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
} catch (Exception $e) {
    $reviews = [];
    if ($error === '') {
        $error = 'Unable to load reviews: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Management - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .segment-links { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .segment-link { padding: 0.5rem 1rem; border-radius: 20px; background: var(--bg-input); color: var(--text-secondary); text-decoration: none; font-size: 0.85rem; }
        .segment-link.active { background: var(--primary); color: white; }
        @media (max-width: 768px) {
            .welcome-banner h1 { font-size: 1.3rem !important; }
            .welcome-banner p { font-size: 0.85rem !important; }
            .chart-container { padding: 1rem !important; }
            .chart-container h2 { font-size: 1.1rem !important; }
            .page-shell { padding: 0.75rem !important; }
            .toolbar-row { flex-direction: column !important; gap: 0.5rem !important; }
            .toolbar-row .btn { width: 100% !important; }
            .info-grid { grid-template-columns: 1fr !important; }
            .segment-links { flex-wrap: wrap; }
            .segment-link { font-size: 0.75rem; padding: 0.4rem 0.75rem; }
        }
        @media (max-width: 480px) {
            .welcome-banner h1 { font-size: 1.1rem !important; }
            .page-head-copy h3 { font-size: 1rem !important; }
        }
    </style>
</head>
<body>
<?php include '../components/admin-header.php'; ?>
<div class="dashboard-container">
    <?php include '../components/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="welcome-banner"><h1>Review Moderation</h1><p>Approve, hold, or reject community product reviews.</p></div>
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div class="segment-links">
                <a class="segment-link <?= $statusFilter === 'all' ? 'active' : '' ?>" href="/admin/reviews.php">All</a>
                <a class="segment-link <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="/admin/reviews.php?status=pending">Pending</a>
                <a class="segment-link <?= $statusFilter === 'approved' ? 'active' : '' ?>" href="/admin/reviews.php?status=approved">Approved</a>
                <a class="segment-link <?= $statusFilter === 'rejected' ? 'active' : '' ?>" href="/admin/reviews.php?status=rejected">Rejected</a>
            </div>
            <section class="chart-container">
                <h2>Review Queue</h2>
                <?php if (empty($reviews)): ?>
                    <div class="empty-state" style="margin-top:1rem;">No reviews matched this filter.</div>
                <?php else: ?>
                    <div class="activity-list" style="margin-top:1rem;">
                        <?php foreach ($reviews as $review): ?>
                            <div class="surface-card">
                                <div class="page-head">
                                    <div class="page-head-copy">
                                        <h3><?= htmlspecialchars($review['product_name'] ?? 'Unknown product') ?></h3>
                                        <p><?= htmlspecialchars($review['reviewer_name'] ?? 'Unknown reviewer') ?> • <?= htmlspecialchars($review['category'] ?? 'general') ?> • Rating <?= number_format((float)($review['rating'] ?? 0), 1) ?>/5</p>
                                    </div>
                                    <span class="treasury-badge"><?= htmlspecialchars($review['status'] ?? 'pending') ?></span>
                                </div>
                                <p class="page-intro" style="margin-top:1rem;"><?= nl2br(htmlspecialchars($review['review_text'] ?? '')) ?></p>
                                <div class="info-grid" style="margin-top:1rem;">
                                    <div class="surface-card"><strong>Pros</strong><p class="muted-text" style="margin-top:.5rem;"><?= htmlspecialchars($review['pros'] ?? 'Not provided') ?></p></div>
                                    <div class="surface-card"><strong>Cons</strong><p class="muted-text" style="margin-top:.5rem;"><?= htmlspecialchars($review['cons'] ?? 'Not provided') ?></p></div>
                                </div>
                                <div class="toolbar-row" style="margin-top:1rem;">
                                    <form method="POST"><input type="hidden" name="review_id" value="<?= htmlspecialchars($review['review_id']) ?>"><input type="hidden" name="status" value="approved"><button class="btn btn-success btn-sm" type="submit">Approve</button></form>
                                    <form method="POST"><input type="hidden" name="review_id" value="<?= htmlspecialchars($review['review_id']) ?>"><input type="hidden" name="status" value="pending"><button class="btn btn-secondary btn-sm" type="submit">Hold</button></form>
                                    <form method="POST"><input type="hidden" name="review_id" value="<?= htmlspecialchars($review['review_id']) ?>"><input type="hidden" name="status" value="rejected"><button class="btn btn-danger btn-sm" type="submit">Reject</button></form>
                                </div>
                            </div>
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
