<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/marketplace.php';

requireLogin('/marketplace/product.php?id=' . urlencode((string) ($_GET['id'] ?? '')));
$user = getCurrentUser();
if (!$user) {
    header('Location: /auth/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
if (($user['role'] ?? 'user') === 'admin') {
    header('Location: /admin/marketplace.php');
    exit;
}

$productId = (int) ($_GET['id'] ?? 0);
$message = '';
$error = '';
$csrfToken = generateCSRFToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new RuntimeException('Security token mismatch. Please refresh and try again.');
        }
        if (isset($_POST['add_to_cart'])) {
            marketplaceAddToCart($user, (int) ($_POST['product_id'] ?? 0), (int) ($_POST['quantity'] ?? 1));
            $message = 'Product added to your cart.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$product = $productId > 0 ? marketplaceGetProductById($productId) : null;
if (!$product || empty($product['is_active'])) {
    header('Location: /marketplace/');
    exit;
}

$relatedProducts = marketplaceGetCatalogProducts([
    'category_id' => (int) ($product['category_id'] ?? 0),
], 4);
$relatedProducts = array_values(array_filter($relatedProducts, static function ($item) use ($productId) {
    return (int) $item['product_id'] !== $productId;
}));
$stock = (int) ($product['current_stock'] ?? $product['stock_quantity'] ?? 0);
$videoEmbedUrl = marketplaceGetVideoEmbedUrl((string) ($product['media_video_url'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> - Marketplace</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
<?php include '../components/header.php'; ?>
<div class="dashboard-container">
    <?php include '../components/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <a href="/marketplace/" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to Marketplace</a>
            <?php if ($message): ?><div class="alert alert-success" style="margin-top:1rem;"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error" style="margin-top:1rem;"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="charts-section" style="margin-top:1rem;">
                <section class="chart-container">
                    <div class="info-grid">
                        <div class="surface-card">
                            <?php if (!empty($product['images'])): ?>
                                <img src="<?= htmlspecialchars($product['images'][0]['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" style="width:100%; border-radius:12px; margin-bottom:1rem;">
                                <?php if (count($product['images']) > 1): ?>
                                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(90px, 1fr)); gap:10px;">
                                        <?php foreach ($product['images'] as $image): ?>
                                            <img src="<?= htmlspecialchars($image['thumbnail_url'] ?: $image['image_url']) ?>" alt="<?= htmlspecialchars($image['alt_text'] ?: $product['name']) ?>" style="width:100%; height:90px; object-fit:cover; border-radius:10px; border:1px solid var(--border-color);">
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="good-card-img" style="min-height:280px;"><i class="fas fa-box-open"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="surface-card">
                            <div class="toolbar-row">
                                <h1 style="margin:0;"><?= htmlspecialchars($product['name']) ?></h1>
                                <?php if (!empty($product['is_featured'])): ?><span class="good-card-status status-active">Featured</span><?php endif; ?>
                            </div>
                            <p class="page-intro" style="margin-top:1rem;"><?= htmlspecialchars($product['short_description'] ?: $product['description']) ?></p>
                            <div class="good-card-price">Rs <?= number_format((float) $product['current_price'], 2) ?></div>
                            <div class="toolbar-row" style="margin-top:1rem;">
                                <span class="treasury-badge"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($product['condition_type'] ?? 'new')))) ?></span>
                                <span class="good-card-status <?= $stock > 0 ? 'status-active' : 'status-inactive' ?>">
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($product['stock_status'] ?? ($stock > 0 ? 'in_stock' : 'out_of_stock'))))) ?>
                                </span>
                            </div>
                            <div class="info-grid" style="margin-top:1rem;">
                                <div class="surface-card"><strong>SKU</strong><p class="muted-text" style="margin-top:.5rem;"><?= htmlspecialchars($product['sku']) ?></p></div>
                                <div class="surface-card"><strong>Brand</strong><p class="muted-text" style="margin-top:.5rem;"><?= htmlspecialchars($product['brand_name'] ?: 'Generic') ?></p></div>
                                <div class="surface-card"><strong>Category</strong><p class="muted-text" style="margin-top:.5rem;"><?= htmlspecialchars($product['category_name'] ?: 'Uncategorized') ?></p></div>
                                <div class="surface-card"><strong>Warranty</strong><p class="muted-text" style="margin-top:.5rem;"><?= (int) ($product['warranty_months'] ?? 0) ?> months</p></div>
                                <div class="surface-card"><strong>MRP</strong><p class="muted-text" style="margin-top:.5rem;">Rs <?= number_format((float) $product['mrp'], 2) ?></p></div>
                                <div class="surface-card"><strong>Available Stock</strong><p class="muted-text" style="margin-top:.5rem;"><?= number_format($stock) ?></p></div>
                            </div>
                            <form method="POST" style="margin-top:1rem; display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                                <div class="form-group" style="max-width:120px;">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" class="form-control" name="quantity" min="1" max="<?= max(1, $stock) ?>" value="1">
                                </div>
                                <button type="submit" class="btn btn-primary" name="add_to_cart" <?= $stock <= 0 ? 'disabled' : '' ?>>
                                    <i class="fas fa-cart-plus"></i> Add To Cart
                                </button>
                                <a href="/marketplace/sell.php" class="btn btn-secondary"><i class="fas fa-shopping-basket"></i> Open Cart</a>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="chart-container">
                    <h2>Description</h2>
                    <p class="page-intro" style="margin-top:1rem;"><?= nl2br(htmlspecialchars($product['description'] ?: 'No detailed description available.')) ?></p>
                </section>

                <?php if (!empty($product['media_video_url'])): ?>
                <section class="chart-container">
                    <h2>Product Media</h2>
                    <?php if ($videoEmbedUrl !== ''): ?>
                        <div style="margin-top:1rem; position:relative; width:100%; padding-top:56.25%; border-radius:12px; overflow:hidden; border:1px solid var(--border-color);">
                            <iframe src="<?= htmlspecialchars($videoEmbedUrl) ?>" title="Product video" allowfullscreen style="position:absolute; inset:0; width:100%; height:100%; border:0;"></iframe>
                        </div>
                    <?php elseif (preg_match('/\.(mp4|webm|ogg)$/i', (string) $product['media_video_url'])): ?>
                        <video controls style="width:100%; margin-top:1rem; border-radius:12px; border:1px solid var(--border-color);">
                            <source src="<?= htmlspecialchars($product['media_video_url']) ?>">
                        </video>
                    <?php else: ?>
                        <div class="surface-card" style="margin-top:1rem;">
                            <strong>Video Link</strong>
                            <p class="page-intro" style="margin-top:.75rem;"><a href="<?= htmlspecialchars($product['media_video_url']) ?>" target="_blank" rel="noopener">Open product video</a></p>
                        </div>
                    <?php endif; ?>
                </section>
                <?php endif; ?>

                <section class="chart-container">
                    <h2>Specifications</h2>
                    <?php if (empty($product['visible_specifications'])): ?>
                        <div class="empty-state" style="margin-top:1rem;">No structured specifications added for this product yet.</div>
                    <?php else: ?>
                        <div class="info-grid" style="margin-top:1rem;">
                            <?php foreach ($product['visible_specifications'] as $specKey => $specValue): ?>
                                <div class="surface-card">
                                    <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) $specKey))) ?></strong>
                                    <p class="muted-text" style="margin-top:.5rem;"><?= htmlspecialchars(is_scalar($specValue) ? (string) $specValue : json_encode($specValue)) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="chart-container">
                    <h2>Related Products</h2>
                    <?php if (empty($relatedProducts)): ?>
                        <div class="empty-state" style="margin-top:1rem;">No related products found.</div>
                    <?php else: ?>
                        <div class="goods-grid" style="margin-top:1rem;">
                            <?php foreach (array_slice($relatedProducts, 0, 3) as $related): ?>
                                <article class="good-card">
                                    <div class="good-card-img">
                                        <?php if (!empty($related['primary_image'])): ?>
                                            <img src="<?= htmlspecialchars($related['primary_image']) ?>" alt="<?= htmlspecialchars($related['name']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-microchip"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="good-card-body">
                                        <div class="good-card-title"><?= htmlspecialchars($related['name']) ?></div>
                                        <div class="good-card-desc"><?= htmlspecialchars($related['short_description'] ?: $related['description']) ?></div>
                                        <div class="good-card-price">Rs <?= number_format((float) $related['current_price'], 2) ?></div>
                                        <a class="btn btn-secondary btn-sm" href="/marketplace/product.php?id=<?= (int) $related['product_id'] ?>">View</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
