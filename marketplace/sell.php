<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/marketplace.php';

requireLogin('/marketplace/sell.php');
$user = getCurrentUser();
if (!$user) {
    header('Location: /auth/login.php?redirect=' . urlencode('/marketplace/sell.php'));
    exit;
}
if (($user['role'] ?? 'user') === 'admin') {
    header('Location: /admin/marketplace.php?section=orders');
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
        if (isset($_POST['update_cart_item'])) {
            marketplaceUpdateCartItemQuantity($user, (int) ($_POST['item_id'] ?? 0), (int) ($_POST['quantity'] ?? 0));
            $message = 'Cart updated.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$cart = marketplaceGetOrCreateActiveCart($user);
$cartItems = marketplaceGetCartItems((int) $cart['cart_id']);
$cartTotals = marketplaceCalculateCartTotals($cartItems);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart & Order Request - AkkuApps</title>
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
                <h1>Your Cart & Order Request</h1>
                <p>This replaces the old peer-to-peer sell screen. Add products from the catalog and the admin team can convert your cart into an invoice.</p>
            </div>
            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="charts-section">
                <section class="chart-container">
                    <div class="page-head">
                        <div class="page-head-copy">
                            <h2>Cart Items</h2>
                            <p>Update quantities here. Setting quantity to `0` removes the line item.</p>
                        </div>
                        <a href="/marketplace/" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Continue Browsing</a>
                    </div>

                    <?php if (empty($cartItems)): ?>
                        <div class="empty-state" style="margin-top:1rem;">Your cart is empty right now.</div>
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
                                        <span class="treasury-badge"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string) ($item['stock_status'] ?? 'in_stock')))) ?></span>
                                        <span class="muted-text">Available: <?= number_format((int) ($item['current_stock'] ?? 0)) ?></span>
                                    </div>
                                    <form method="POST" style="margin-top:1rem; display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="item_id" value="<?= (int) $item['item_id'] ?>">
                                        <div class="form-group" style="max-width:140px;">
                                            <label class="form-label">Quantity</label>
                                            <input type="number" class="form-control" name="quantity" min="0" max="<?= max(1, (int) ($item['current_stock'] ?? 0)) ?>" value="<?= (int) $item['quantity'] ?>">
                                        </div>
                                        <button type="submit" class="btn btn-primary" name="update_cart_item">Update Cart</button>
                                        <a class="btn btn-secondary" href="/marketplace/product.php?id=<?= (int) $item['product_id'] ?>">View Product</a>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="chart-container">
                    <h2>Order Summary</h2>
                    <div class="info-grid" style="margin-top:1rem;">
                        <div class="surface-card"><strong>Subtotal</strong><p class="muted-text" style="margin-top:.5rem;">Rs <?= number_format($cartTotals['subtotal'], 2) ?></p></div>
                        <div class="surface-card"><strong>Tax</strong><p class="muted-text" style="margin-top:.5rem;">Rs <?= number_format($cartTotals['tax_amount'], 2) ?></p></div>
                        <div class="surface-card"><strong>Grand Total</strong><p class="muted-text" style="margin-top:.5rem;">Rs <?= number_format($cartTotals['grand_total'], 2) ?></p></div>
                    </div>
                    <div class="surface-card" style="margin-top:1rem;">
                        <strong>What happens next?</strong>
                        <p class="page-intro" style="margin-top:.75rem;">
                            Your active cart is visible inside the admin marketplace control center. The shop team can convert it into an invoice, collect payment, and create any linked service ticket if the order includes repair or upgrade work.
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </main>
</div>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
