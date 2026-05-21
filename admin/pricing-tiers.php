<?php
/**
 * Admin Pricing Tiers Management Page
 * Manage ad sizes, pricing models, and tier configurations
 * URL: /admin/pricing-tiers.php
 */

define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ad-engine.php';
require_once '../includes/payment-engine.php';

// Verify admin access
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

global $pdo;

// Handle actions
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $formAction = trim($_POST['form_action'] ?? '');
        
        if ($formAction === 'create_tier') {
            $sizeType = trim($_POST['size_type'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $widthPx = intval($_POST['width_px'] ?? 0);
            $heightPx = intval($_POST['height_px'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $basePriceMonthly = floatval($_POST['base_price_monthly'] ?? 0);
            $priceCpm = floatval($_POST['price_cpm'] ?? 0);
            $priceCpc = floatval($_POST['price_cpc'] ?? 0);
            $currency = trim($_POST['currency'] ?? 'USD');
            $minDurationDays = intval($_POST['min_duration_days'] ?? 1);
            $maxAdsPerTier = intval($_POST['max_ads_per_tier'] ?? 5);
            $imageFormatAllowed = trim($_POST['image_format_allowed'] ?? 'jpg,jpeg,png,gif,webp');
            
            if (empty($sizeType) || empty($displayName) || $basePriceMonthly <= 0) {
                throw new Exception('Size Type, Display Name, and Base Price are required');
            }
            
            // Check if size_type already exists
            $checkSql = "SELECT id FROM ad_pricing_tiers WHERE size_type = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$sizeType]);
            if ($checkStmt->rowCount() > 0) {
                throw new Exception('Size Type already exists');
            }
            
            $sql = "
                INSERT INTO ad_pricing_tiers (
                    id, size_type, display_name, width_px, height_px, description,
                    base_price_monthly, price_cpm, price_cpc, currency, is_active,
                    min_duration_days, max_ads_per_tier, image_format_allowed, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                generateUUID(),
                $sizeType,
                $displayName,
                $widthPx ?: null,
                $heightPx ?: null,
                $description,
                $basePriceMonthly,
                $priceCpm ?: null,
                $priceCpc ?: null,
                $currency,
                1,
                $minDurationDays,
                $maxAdsPerTier,
                $imageFormatAllowed
            ]);
            
            $message = 'Pricing tier created successfully!';
        } elseif ($formAction === 'update_tier') {
            $tierId = trim($_POST['tier_id'] ?? '');
            $displayName = trim($_POST['display_name'] ?? '');
            $widthPx = intval($_POST['width_px'] ?? 0);
            $heightPx = intval($_POST['height_px'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $basePriceMonthly = floatval($_POST['base_price_monthly'] ?? 0);
            $priceCpm = floatval($_POST['price_cpm'] ?? 0);
            $priceCpc = floatval($_POST['price_cpc'] ?? 0);
            $minDurationDays = intval($_POST['min_duration_days'] ?? 1);
            $maxAdsPerTier = intval($_POST['max_ads_per_tier'] ?? 5);
            $imageFormatAllowed = trim($_POST['image_format_allowed'] ?? 'jpg,jpeg,png,gif,webp');
            
            if (empty($tierId) || empty($displayName) || $basePriceMonthly <= 0) {
                throw new Exception('Display Name and Base Price are required');
            }
            
            $sql = "
                UPDATE ad_pricing_tiers
                SET display_name = ?, width_px = ?, height_px = ?, description = ?,
                    base_price_monthly = ?, price_cpm = ?, price_cpc = ?,
                    min_duration_days = ?, max_ads_per_tier = ?, 
                    image_format_allowed = ?, updated_at = NOW()
                WHERE id = ?
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $displayName,
                $widthPx ?: null,
                $heightPx ?: null,
                $description,
                $basePriceMonthly,
                $priceCpm ?: null,
                $priceCpc ?: null,
                $minDurationDays,
                $maxAdsPerTier,
                $imageFormatAllowed,
                $tierId
            ]);
            
            $message = 'Pricing tier updated successfully!';
        } elseif ($formAction === 'toggle_tier') {
            $tierId = trim($_POST['tier_id'] ?? '');
            if (empty($tierId)) throw new Exception('Tier ID required');
            
            // Get current status
            $sql = "SELECT is_active FROM ad_pricing_tiers WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tierId]);
            $row = $stmt->fetch();
            
            $newStatus = !$row['is_active'] ? 1 : 0;
            
            $sql = "UPDATE ad_pricing_tiers SET is_active = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$newStatus, $tierId]);
            
            $message = 'Pricing tier ' . ($newStatus ? 'activated' : 'deactivated') . '!';
        } elseif ($formAction === 'delete_tier') {
            $tierId = trim($_POST['tier_id'] ?? '');
            if (empty($tierId)) throw new Exception('Tier ID required');
            
            // Check if tier is in use
            $checkSql = "SELECT COUNT(*) as count FROM advertisements WHERE ad_size_id = ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$tierId]);
            $count = $checkStmt->fetch()['count'];
            
            if ($count > 0) {
                throw new Exception('Cannot delete tier: ' . $count . ' ads are using this tier');
            }
            
            $sql = "DELETE FROM ad_pricing_tiers WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tierId]);
            
            $message = 'Pricing tier deleted!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all pricing tiers
$sql = "
    SELECT p.*, COUNT(a.id) as ad_count
    FROM ad_pricing_tiers p
    LEFT JOIN advertisements a ON p.id = a.ad_size_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$tiers = $stmt->fetchAll();

// Get tiers by status for stats
$activeTiers = array_filter($tiers, fn($t) => $t['is_active']);
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pricing Tiers Management - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 2rem; color: var(--text-primary); }
        .btn { padding: 0.65rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-secondary { background: var(--border-color); color: var(--text-primary); }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #000; }
        
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 2000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: var(--bg-card); border-radius: 12px; padding: 2rem; width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; }
        .modal-header h2 { font-size: 1.5rem; color: var(--text-primary); }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        .modal-close:hover { color: var(--text-primary); }
        
        .form-group { display: flex; flex-direction: column; margin-bottom: 1rem; }
        .form-group label { font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary); }
        .form-group input, .form-group textarea { padding: 0.65rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-primary); font-size: 0.95rem; }
        .form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; border-left: 4px solid var(--primary); }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem; }
        
        .tiers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
        .tier-card { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); transition: all 0.3s; }
        .tier-card:hover { border-color: var(--primary); }
        .tier-card.inactive { opacity: 0.6; }
        
        .tier-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
        .tier-title { font-size: 1.3rem; font-weight: 700; color: var(--text-primary); }
        .tier-badge { display: inline-block; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; background: #d4edda; color: #155724; }
        .tier-badge.inactive { background: #e2e3e5; color: #383d41; }
        
        .tier-details { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem; }
        .tier-detail { }
        .tier-detail-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        .tier-detail-value { font-size: 1rem; color: var(--text-primary); font-weight: 600; margin-top: 0.25rem; }
        
        .pricing-info { background: var(--bg-hover); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .pricing-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .pricing-row:last-child { margin-bottom: 0; }
        .pricing-label { font-size: 0.9rem; color: var(--text-muted); }
        .pricing-value { font-weight: 700; color: var(--primary); }
        
        .tier-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .tier-actions button { padding: 0.5rem 0.8rem; font-size: 0.85rem; flex: 1; min-width: 100px; }
        
        .empty-state { text-align: center; padding: 3rem 1rem; }
        .empty-state-icon { font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem; }
        .empty-state-text { color: var(--text-muted); font-size: 1.1rem; }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .tiers-grid { grid-template-columns: 1fr; }
            .tier-details { grid-template-columns: 1fr; }
            .tier-actions { flex-direction: column; }
            .tier-actions button { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-layer-group"></i> Pricing Tiers Management</h1>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Manage ad sizes, prices, and configurations</p>
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Add New Tier
            </button>
        </div>
        
        <!-- Alerts -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= count($activeTiers) ?></div>
                <div class="stat-label">Active Tiers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($tiers) ?></div>
                <div class="stat-label">Total Tiers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= array_sum(array_column($tiers, 'ad_count')) ?></div>
                <div class="stat-label">Total Ads Using Tiers</div>
            </div>
        </div>
        
        <!-- Tiers Grid -->
        <?php if (!empty($tiers)): ?>
            <div class="tiers-grid">
                <?php foreach ($tiers as $tier): ?>
                    <div class="tier-card <?= !$tier['is_active'] ? 'inactive' : '' ?>">
                        <div class="tier-header">
                            <div class="tier-title"><?= htmlspecialchars($tier['display_name']) ?></div>
                            <span class="tier-badge <?= !$tier['is_active'] ? 'inactive' : '' ?>">
                                <?= $tier['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        
                        <div class="tier-details">
                            <div class="tier-detail">
                                <div class="tier-detail-label">Size Type</div>
                                <div class="tier-detail-value"><?= htmlspecialchars($tier['size_type']) ?></div>
                            </div>
                            <div class="tier-detail">
                                <div class="tier-detail-label">Dimensions</div>
                                <div class="tier-detail-value">
                                    <?= $tier['width_px'] ?? '—' ?> x <?= $tier['height_px'] ?? '—' ?> px
                                </div>
                            </div>
                            <div class="tier-detail">
                                <div class="tier-detail-label">Using Ads</div>
                                <div class="tier-detail-value"><?= $tier['ad_count'] ?></div>
                            </div>
                            <div class="tier-detail">
                                <div class="tier-detail-label">Min Duration</div>
                                <div class="tier-detail-value"><?= $tier['min_duration_days'] ?> days</div>
                            </div>
                        </div>
                        
                        <?php if (!empty($tier['description'])): ?>
                            <div style="background: var(--bg-hover); border-radius: 8px; padding: 0.75rem; margin-bottom: 1rem; font-size: 0.9rem; color: var(--text-muted);">
                                <?= htmlspecialchars(substr($tier['description'], 0, 100)) ?><?= strlen($tier['description']) > 100 ? '...' : '' ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="pricing-info">
                            <div class="pricing-row">
                                <span class="pricing-label">Monthly Fixed</span>
                                <span class="pricing-value"><?= akkuAdFormatCurrency($tier['base_price_monthly'], $tier['currency']) ?></span>
                            </div>
                            <?php if ($tier['price_cpm']): ?>
                                <div class="pricing-row">
                                    <span class="pricing-label">CPM (1000 impressions)</span>
                                    <span class="pricing-value"><?= akkuAdFormatCurrency($tier['price_cpm'], $tier['currency']) ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($tier['price_cpc']): ?>
                                <div class="pricing-row">
                                    <span class="pricing-label">CPC (per click)</span>
                                    <span class="pricing-value"><?= akkuAdFormatCurrency($tier['price_cpc'], $tier['currency']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="tier-actions">
                            <button class="btn btn-secondary" onclick="openEditModal('<?= $tier['id'] ?>')">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-warning" onclick="toggleTier('<?= $tier['id'] ?>')">
                                <i class="fas fa-<?= $tier['is_active'] ? 'toggle-on' : 'toggle-off' ?>"></i> 
                                <?= $tier['is_active'] ? 'Disable' : 'Enable' ?>
                            </button>
                            <button class="btn btn-danger" onclick="deleteTier('<?= $tier['id'] ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <div class="empty-state-text">No pricing tiers yet</div>
                <button class="btn btn-primary" style="margin-top: 1.5rem;" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Create First Tier
                </button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Create/Edit Modal -->
    <div class="modal-overlay" id="tierModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create Pricing Tier</h2>
                <button class="modal-close" onclick="closeTierModal()">&times;</button>
            </div>
            
            <form method="POST" action="" id="tierForm">
                <input type="hidden" name="form_action" id="formAction" value="create_tier">
                <input type="hidden" name="tier_id" id="tierId">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Size Type (e.g., banner_728x90) *</label>
                        <input type="text" name="size_type" id="sizeType" placeholder="banner_728x90" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Display Name (e.g., Banner 728x90) *</label>
                        <input type="text" name="display_name" placeholder="Banner 728x90" required>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Width (px)</label>
                        <input type="number" name="width_px" placeholder="728">
                    </div>
                    
                    <div class="form-group">
                        <label>Height (px)</label>
                        <input type="number" name="height_px" placeholder="90">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="2" placeholder="Size recommendations, best practices..."></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Base Price Monthly *</label>
                        <input type="number" name="base_price_monthly" step="0.01" placeholder="100.00" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Currency</label>
                        <select name="currency">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="INR">INR</option>
                            <option value="AUD">AUD</option>
                            <option value="CAD">CAD</option>
                            <option value="JPY">JPY</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>CPM (Cost Per 1000 impressions)</label>
                        <input type="number" name="price_cpm" step="0.01" placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label>CPC (Cost Per Click)</label>
                        <input type="number" name="price_cpc" step="0.01" placeholder="0.00">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label>Minimum Duration (days)</label>
                        <input type="number" name="min_duration_days" value="1" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>Max Ads Per Provider</label>
                        <input type="number" name="max_ads_per_tier" value="5" min="1">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Allowed Image Formats (comma-separated)</label>
                    <input type="text" name="image_format_allowed" value="jpg,jpeg,png,gif,webp">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Save Tier
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeTierModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openCreateModal() {
            document.getElementById('formAction').value = 'create_tier';
            document.getElementById('tierId').value = '';
            document.getElementById('sizeType').disabled = false;
            document.getElementById('tierForm').reset();
            document.querySelector('#tierModal .modal-header h2').textContent = 'Create Pricing Tier';
            document.getElementById('tierModal').classList.add('active');
        }
        
        function closeTierModal() {
            document.getElementById('tierModal').classList.remove('active');
        }
        
        function openEditModal(tierId) {
            alert('Edit functionality for tier: ' + tierId + ' (To be implemented - requires AJAX)');
        }
        
        function toggleTier(tierId) {
            if (confirm('Toggle this tier\'s status?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="form_action" value="toggle_tier"><input type="hidden" name="tier_id" value="${tierId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteTier(tierId) {
            if (confirm('Delete this pricing tier? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="form_action" value="delete_tier"><input type="hidden" name="tier_id" value="${tierId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeTierModal();
            }
        });
    </script>
</body>
</html>
