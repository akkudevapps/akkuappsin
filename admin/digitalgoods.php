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

function isValidPngAsset(string $value): bool {
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    return (bool)preg_match('/\.png(\?.*)?$/i', $value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $pdo;

    if (isset($_POST['add_badge'])) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $coinPrice = (float)($_POST['coin_price'] ?? 0);
        $iconUrl = trim($_POST['icon_url'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $error = 'Badge name is required.';
        } elseif (!isValidPngAsset($iconUrl)) {
            $error = 'Badge image must be a PNG URL or PNG asset path.';
        } else {
            try {
                $badgeId = generateUUID();
                $stmt = $pdo->prepare("
                    INSERT INTO badges
                    (badge_id, name, description, coin_price, icon_url, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$badgeId, $name, $description, $coinPrice, $iconUrl, $isActive]);
                $message = 'Badge added successfully.';
            } catch (Exception $e) {
                $error = 'Error adding badge: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['add_gift'])) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $coinValue = (float)($_POST['coin_value'] ?? 0);
        $imageUrl = trim($_POST['image_url'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            $error = 'Gift name is required.';
        } elseif (!isValidPngAsset($imageUrl)) {
            $error = 'Gift image must be a PNG URL or PNG asset path.';
        } else {
            try {
                $giftId = generateUUID();
                $stmt = $pdo->prepare("
                    INSERT INTO gifts
                    (gift_id, name, description, coin_value, image_url, is_active, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$giftId, $name, $description, $coinValue, $imageUrl, $isActive]);
                $message = 'Gift added successfully.';
            } catch (Exception $e) {
                $error = 'Error adding gift: ' . $e->getMessage();
            }
        }
    }
}

try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM badges ORDER BY created_at DESC");
    $stmt->execute();
    $badges = $stmt->fetchAll();
} catch (Exception $e) {
    $badges = [];
    $error = 'Error loading badges: ' . $e->getMessage();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM gifts ORDER BY created_at DESC");
    $stmt->execute();
    $gifts = $stmt->fetchAll();
} catch (Exception $e) {
    $gifts = [];
    $error = 'Error loading gifts: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Goods - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        @media (max-width: 768px) {
            .welcome-banner h1 { font-size: 1.3rem !important; }
            .welcome-banner p { font-size: 0.85rem !important; }
            .goods-form-grid { grid-template-columns: 1fr !important; }
            .chart-container { padding: 1rem !important; }
            .chart-container h2 { font-size: 1.1rem !important; }
            .page-shell { padding: 0.75rem !important; }
            .form-grid { grid-template-columns: 1fr !important; }
            .goods-preview { flex-direction: column !important; }
            .page-head { flex-direction: column !important; gap: 0.5rem !important; }
        }
        @media (max-width: 480px) {
            .welcome-banner h1 { font-size: 1.1rem !important; }
        }
    </style>
</head>
<body>
    <?php include '../components/admin-header.php'; ?>

    <div class="dashboard-container">
        <?php include '../components/admin-sidebar.php'; ?>

        <main class="main-content">
            <div class="page-shell">
                <div class="welcome-banner">
                    <h1>Digital Goods Management</h1>
                    <p>Every badge and gift now requires a PNG image so the admin catalog looks complete on both desktop and mobile.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <section class="surface-card">
                    <div class="page-head">
                        <div class="page-head-copy">
                            <h2>Catalog Rules</h2>
                            <p>Use PNG assets such as `/assets/images/goods/badge-gold.png` or a direct `.png` URL. Badge sales are platform-owned and treasury-facing, while gift commissions continue to flow into treasury on send/conversion actions.</p>
                        </div>
                        <span class="treasury-badge"><i class="fas fa-vault"></i> Treasury-aligned</span>
                    </div>
                </section>

                <section class="goods-form-grid animate-slideUp">
                    <div class="chart-container">
                        <h2>Add New Badge</h2>
                        <form method="POST" style="margin-top: 1rem;">
                            <input type="hidden" name="add_badge" value="1">

                            <div class="form-group">
                                <label class="form-label">Badge Name</label>
                                <input type="text" name="name" required class="form-control">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" rows="3" class="form-control"></textarea>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Price (Coins)</label>
                                    <input type="number" name="coin_price" step="0.01" min="0" value="0" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">PNG Image URL / Path</label>
                                    <input type="text" name="icon_url" placeholder="/assets/images/goods/badge-gold.png" required class="form-control">
                                </div>
                            </div>

                            <div class="goods-preview" style="margin-bottom: 1rem;">
                                <div class="img-preview-wrap" style="margin-bottom: 0;">
                                    <div class="img-preview-placeholder"><i class="fas fa-image"></i></div>
                                </div>
                                <div>
                                    <strong>PNG required</strong>
                                    <div class="muted-text">This image is shown in admin and user catalog cards.</div>
                                </div>
                            </div>

                            <label class="toolbar-row muted-text" style="margin-bottom: 1rem;">
                                <input type="checkbox" name="is_active" value="1" checked>
                                Active item
                            </label>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Badge
                            </button>
                        </form>
                    </div>

                    <div class="chart-container">
                        <h2>Add New Gift</h2>
                        <form method="POST" style="margin-top: 1rem;">
                            <input type="hidden" name="add_gift" value="1">

                            <div class="form-group">
                                <label class="form-label">Gift Name</label>
                                <input type="text" name="name" required class="form-control">
                            </div>

                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" rows="3" class="form-control"></textarea>
                            </div>

                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Coin Value</label>
                                    <input type="number" name="coin_value" step="0.01" min="0" value="0" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">PNG Image URL / Path</label>
                                    <input type="text" name="image_url" placeholder="/assets/images/goods/gift-rose.png" required class="form-control">
                                </div>
                            </div>

                            <div class="goods-preview" style="margin-bottom: 1rem;">
                                <div class="img-preview-wrap" style="margin-bottom: 0;">
                                    <div class="img-preview-placeholder"><i class="fas fa-gift"></i></div>
                                </div>
                                <div>
                                    <strong>Gift visuals matter</strong>
                                    <div class="muted-text">The send, inventory, and treasury views all benefit from consistent PNG artwork.</div>
                                </div>
                            </div>

                            <label class="toolbar-row muted-text" style="margin-bottom: 1rem;">
                                <input type="checkbox" name="is_active" value="1" checked>
                                Active item
                            </label>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Gift
                            </button>
                        </form>
                    </div>
                </section>

                <section class="chart-container animate-slideUp">
                    <h2>Badges</h2>
                    <?php if (empty($badges)): ?>
                        <div class="empty-state" style="margin-top: 1rem;">No badges found.</div>
                    <?php else: ?>
                        <div class="goods-grid">
                            <?php foreach ($badges as $badge): ?>
                                <article class="good-card">
                                    <div class="good-card-img">
                                        <?php if (!empty($badge['icon_url'])): ?>
                                            <img src="<?= htmlspecialchars($badge['icon_url']) ?>" alt="<?= htmlspecialchars($badge['name']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-award"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="good-card-body">
                                        <div class="toolbar-row">
                                            <div class="good-card-title"><?= htmlspecialchars($badge['name']) ?></div>
                                            <span class="good-card-status <?= $badge['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $badge['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                        <div class="good-card-desc"><?= htmlspecialchars($badge['description'] ?: 'No description') ?></div>
                                        <div class="good-card-price"><?= number_format((float)$badge['coin_price'], 2) ?> coins</div>
                                        <div class="muted-text" style="font-size: .8rem;">Treasury routing: full badge sale value</div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="chart-container animate-slideUp">
                    <h2>Gifts</h2>
                    <?php if (empty($gifts)): ?>
                        <div class="empty-state" style="margin-top: 1rem;">No gifts found.</div>
                    <?php else: ?>
                        <div class="goods-grid">
                            <?php foreach ($gifts as $gift): ?>
                                <article class="good-card">
                                    <div class="good-card-img">
                                        <?php if (!empty($gift['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($gift['image_url']) ?>" alt="<?= htmlspecialchars($gift['name']) ?>">
                                        <?php else: ?>
                                            <i class="fas fa-gift"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="good-card-body">
                                        <div class="toolbar-row">
                                            <div class="good-card-title"><?= htmlspecialchars($gift['name']) ?></div>
                                            <span class="good-card-status <?= $gift['is_active'] ? 'status-active' : 'status-inactive' ?>">
                                                <?= $gift['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </div>
                                        <div class="good-card-desc"><?= htmlspecialchars($gift['description'] ?: 'No description') ?></div>
                                        <div class="good-card-price"><?= number_format((float)$gift['coin_value'], 2) ?> coins</div>
                                        <div class="muted-text" style="font-size: .8rem;">Treasury routing: send fee and conversion commission</div>
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
