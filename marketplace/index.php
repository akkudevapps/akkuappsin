<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/marketplace.php';

requireLogin('/marketplace/');
$user = getCurrentUser();
if (!$user) {
    header('Location: /auth/login.php?redirect=' . urlencode('/marketplace/'));
    exit;
}
if (($user['role'] ?? 'user') === 'admin') {
    header('Location: /admin/marketplace.php');
    exit;
}

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
        } elseif (isset($_POST['update_cart_item'])) {
            marketplaceUpdateCartItemQuantity($user, (int) ($_POST['item_id'] ?? 0), (int) ($_POST['quantity'] ?? 0));
            $message = 'Cart updated.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$filters = [
    'category_id' => (int) ($_GET['category_id'] ?? 0),
    'brand_id' => (int) ($_GET['brand_id'] ?? 0),
    'stock' => trim((string) ($_GET['stock'] ?? '')),
    'search' => trim((string) ($_GET['search'] ?? '')),
    'featured' => !empty($_GET['featured']) ? 1 : 0,
];

try {
    $products = marketplaceGetCatalogProducts($filters);
    $categories = marketplaceGetCategories();
    $brands = marketplaceGetBrands();
    $cart = marketplaceGetOrCreateActiveCart($user);
    $cartItems = marketplaceGetCartItems((int) $cart['cart_id']);
    $cartTotals = marketplaceCalculateCartTotals($cartItems);
} catch (Throwable $e) {
    $products = [];
    $categories = [];
    $brands = [];
    $cart = null;
    $cartItems = [];
    $cartTotals = [
        'subtotal' => 0,
        'discount_total' => 0,
        'tax_rate' => 18,
        'tax_amount' => 0,
        'grand_total' => 0,
        'amount_due' => 0,
    ];
    if ($error === '') {
        $error = 'Unable to load marketplace data: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
<?php include '../components/header.php'; ?>
<div class="dashboard-container">
    <?php include '../components/sidebar.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="welcome-banner">
                <h1>AkkuApps Marketplace</h1>
                <p>Browse computer components, systems, accessories, and service-ready hardware from the logged-in shop catalog.</p>
                <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="/marketplace/?featured=1" class="btn btn-primary btn-sm"><i class="fas fa-star"></i> Featured Products</a>
                    <a href="/marketplace/sell.php" class="btn btn-secondary btn-sm"><i class="fas fa-shopping-basket"></i> View Cart / Order Request</a>
                </div>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <section class="chart-container">
                <div class="page-head">
                    <div class="page-head-copy">
                        <h2>Filter Catalog</h2>
                        <p>Search by SKU, brand, or category and monitor stock before adding items to the cart.</p>
                    </div>
                    <div class="treasury-badge"><?= number_format(count($products)) ?> product(s)</div>
                </div>
                <form method="GET" class="form-grid" style="margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <input class="form-control" type="text" name="search" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="CPU, SSD, RTX, SKU...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="category_id">
                            <option value="0">All categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['category_id'] ?>" <?= $filters['category_id'] === (int) $category['category_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Brand</label>
                        <select class="form-control" name="brand_id">
                            <option value="0">All brands</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= (int) $brand['brand_id'] ?>" <?= $filters['brand_id'] === (int) $brand['brand_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($brand['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock</label>
                        <select class="form-control" name="stock">
                            <option value="">All stock states</option>
                            <option value="in_stock" <?= $filters['stock'] === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                            <option value="low_stock" <?= $filters['stock'] === 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out_of_stock" <?= $filters['stock'] === 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex; align-items:flex-end; gap:12px;">
                        <label style="display:flex; align-items:center; gap:8px; color: var(--text-secondary);">
                            <input type="checkbox" name="featured" value="1" <?= !empty($filters['featured']) ? 'checked' : '' ?>>
                            Featured only
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    </div>
                </form>
            </section>

            <div class="charts-section">
                <section class="chart-container">
                    <div class="page-head">
                        <div class="page-head-copy">
                            <h2>Product Catalog</h2>
                            <p>Only active `cs_products` are shown here, with pricing and stock from the new shop model.</p>
                        </div>
                    </div>
                    <?php if (empty($products)): ?>
                        <div class="empty-state" style="margin-top: 1rem;">No products matched this filter.</div>
                    <?php else: ?>
                        <div class="goods-grid" style="margin-top: 1rem;">
                            <?php foreach ($products as $product): ?>
                                <?php $stock = (int) ($product['current_stock'] ?? $product['stock_quantity'] ?? 0); ?>
                                <article class="good-card">
                                    <div class="good-card-img">
                                        <?php if (!empty($product['primary_image'])): ?>
                                            <img src="<?= htmlspecialchars($product['primary_image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-box-open"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="good-card-body">
                                        <div class="toolbar-row">
                                            <div class="good-card-title"><?= htmlspecialchars($product['name']) ?></div>
                                            <?php if (!empty($product['is_featured'])): ?>
                                                <span class="good-card-status status-active">Featured</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="good-card-desc"><?= htmlspecialchars($product['short_description'] ?: $product['description']) ?></div>
                                        <div class="muted-text" style="font-size:.82rem;">SKU: <?= htmlspecialchars($product['sku']) ?></div>
                                        <div class="muted-text" style="font-size:.82rem;">
                                            <?= htmlspecialchars($product['brand_name'] ?: 'Generic') ?> | <?= htmlspecialchars($product['category_name'] ?: 'Uncategorized') ?>
                                        </div>
                                        <div class="toolbar-row" style="margin-top:.5rem;">
                                            <span class="treasury-badge"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($product['condition_type'] ?? 'new')))) ?></span>
                                            <span class="good-card-status <?= $stock > 0 ? 'status-active' : 'status-inactive' ?>">
                                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($product['stock_status'] ?? ($stock > 0 ? 'in_stock' : 'out_of_stock'))))) ?>
                                            </span>
                                        </div>
                                        <div class="good-card-price">Rs <?= number_format((float) $product['current_price'], 2) ?></div>
                                        <div class="muted-text" style="font-size:.82rem;">Stock: <?= number_format($stock) ?> | Warranty: <?= (int) ($product['warranty_months'] ?? 0) ?> months</div>
                                        <div class="toolbar-row" style="margin-top:.85rem;">
                                            <a class="btn btn-primary btn-sm" href="/marketplace/product.php?id=<?= (int) $product['product_id'] ?>">View Details</a>
                                            <form method="POST" style="display:flex; gap:8px; align-items:center;">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
                                                <input type="number" class="form-control" name="quantity" min="1" max="<?= max(1, $stock) ?>" value="1" style="width:74px; padding:.55rem;">
                                                <button type="submit" class="btn btn-secondary btn-sm" name="add_to_cart" <?= $stock <= 0 ? 'disabled' : '' ?>>
                                                    <i class="fas fa-cart-plus"></i> Add
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="chart-container">
                    <div class="page-head">
                        <div class="page-head-copy">
                            <h2>Your Cart</h2>
                            <p>Logged-in users can collect products here; admin converts this cart into an invoice or order request workflow.</p>
                        </div>
                        <a class="btn btn-secondary btn-sm" href="/marketplace/sell.php"><i class="fas fa-arrow-right"></i> Open Cart Page</a>
                    </div>
                    <?php if (empty($cartItems)): ?>
                        <div class="empty-state" style="margin-top:1rem;">Your cart is empty.</div>
                    <?php else: ?>
                        <div class="activity-list" style="margin-top:1rem;">
                            <?php foreach ($cartItems as $item): ?>
                                <div class="surface-card">
                                    <div class="page-head">
                                        <div class="page-head-copy">
                                            <h3><?= htmlspecialchars($item['product_name']) ?></h3>
                                            <p><?= htmlspecialchars($item['brand_name'] ?: 'Generic') ?> | SKU <?= htmlspecialchars($item['sku']) ?></p>
                                        </div>
                                        <div class="good-card-price">Rs <?= number_format(((float) $item['unit_price']) * ((int) $item['quantity']), 2) ?></div>
                                    </div>
                                    <div class="toolbar-row" style="margin-top:1rem;">
                                        <span class="treasury-badge">Stock <?= number_format((int) ($item['current_stock'] ?? 0)) ?></span>
                                        <form method="POST" style="display:flex; gap:8px; align-items:center;">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                            <input type="number" class="form-control" name="quantity" min="0" max="<?= max(1, (int) ($item['current_stock'] ?? 0)) ?>" value="<?= (int) $item['quantity'] ?>" style="width:84px; padding:.55rem;">
                                            <button type="submit" class="btn btn-secondary btn-sm" name="update_cart_item">Update</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="info-grid" style="margin-top:1rem;">
                            <div class="surface-card"><strong>Subtotal</strong><p class="muted-text" style="margin-top:.5rem;">Rs <?= number_format($cartTotals['subtotal'], 2) ?></p></div>
                            <div class="surface-card"><strong>Tax</strong><p class="muted-text" style="margin-top:.5rem;">Rs <?= number_format($cartTotals['tax_amount'], 2) ?> @ <?= number_format($cartTotals['tax_rate'], 2) ?>%</p></div>
                            <div class="surface-card"><strong>Grand Total</strong><p class="muted-text" style="margin-top:.5rem;">Rs <?= number_format($cartTotals['grand_total'], 2) ?></p></div>
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
