<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/marketplace.php';

$user = getCurrentUser();
if (!$user) {
    header('Location: /auth/login.php?redirect=' . urlencode('/user/submit-review.php'));
    exit;
}

$message = '';
$error = '';
try {
    global $pdo;
    $products = $pdo->query("
        SELECT p.product_id, p.name AS product_name, img.image_url AS product_image, c.name AS category
        FROM cs_products p
        LEFT JOIN cs_categories c ON p.category_id = c.category_id
        LEFT JOIN cs_product_images img ON p.product_id = img.product_id AND img.is_primary = 1
        WHERE p.is_active = 1
        ORDER BY p.name ASC
    ")->fetchAll();
} catch (Exception $e) {
    $products = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        global $pdo;
        $stmt = $pdo->prepare("
            INSERT INTO product_reviews
            (review_id, reviewer_id, product_id, rating, review_text, pros, cons, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())
        ");
        $stmt->execute([
            generateUUID(),
            $user['user_id'],
            $_POST['product_id'],
            (float)($_POST['rating'] ?? 0),
            trim($_POST['review_text'] ?? ''),
            trim($_POST['pros'] ?? ''),
            trim($_POST['cons'] ?? '')
        ]);
        $message = 'Review submitted for admin approval.';
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
    <title>Submit Review - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
<?php include '../components/header.php'; ?>
<div class="dashboard-container">
    <?php include '../components/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="welcome-banner"><h1>Submit Review</h1><p>Choose a product, rate it visually, and share balanced pros and cons.</p></div>
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <section class="chart-container content-narrow">
                <form method="POST">
                    <div class="form-group"><label class="form-label">Product</label><select class="form-control" name="product_id" required><?php foreach ($products as $product): ?><option value="<?= htmlspecialchars($product['product_id']) ?>"><?= htmlspecialchars($product['product_name']) ?> (<?= htmlspecialchars($product['category']) ?>)</option><?php endforeach; ?></select></div>
                    <div class="form-group"><label class="form-label">Rating</label><select class="form-control" name="rating" required><option value="5">5 Stars</option><option value="4">4 Stars</option><option value="3">3 Stars</option><option value="2">2 Stars</option><option value="1">1 Star</option></select></div>
                    <div class="form-group"><label class="form-label">Review</label><textarea class="form-control" name="review_text" rows="7" required></textarea></div>
                    <div class="form-grid">
                        <div class="form-group"><label class="form-label">Pros</label><textarea class="form-control" name="pros" rows="4"></textarea></div>
                        <div class="form-group"><label class="form-label">Cons</label><textarea class="form-control" name="cons" rows="4"></textarea></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </form>
            </section>
        </div>
    </main>
</div>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
