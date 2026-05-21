<?php
/**
 * Admin Advertisement Management Page
 * Manage all advertisements: create, edit, approve, pause, delete
 * URL: /admin/advertisements.php
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

// Handle actions (create, update, approve, delete)
$message = '';
$error = '';
$action = trim($_GET['action'] ?? '');

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $formAction = trim($_POST['form_action'] ?? '');
        
        if ($formAction === 'create_ad') {
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $providerId = trim($_POST['provider_id'] ?? '');
            $adSizeId = trim($_POST['ad_size_id'] ?? '');
            $adType = trim($_POST['ad_type'] ?? 'image');
            $category = trim($_POST['category'] ?? 'general');
            $imageUrl = trim($_POST['image_url'] ?? '');
            $clickUrl = trim($_POST['click_url'] ?? '');
            $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
            $endDate = trim($_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days')));
            $totalBudget = floatval($_POST['total_budget'] ?? 0);
            $pricingModel = trim($_POST['pricing_model'] ?? 'fixed');
            $status = trim($_POST['status'] ?? 'draft');
            
            // Target regions (JSON)
            $targetRegions = $_POST['target_regions'] ?? [];
            $targetLanguages = $_POST['target_languages'] ?? [];
            $targetCountries = $_POST['target_countries'] ?? [];
            
            if (empty($title) || empty($providerId) || empty($adSizeId)) {
                throw new Exception('Title, Provider, and Ad Size are required');
            }
            
            // Insert ad
            $sql = "
                INSERT INTO advertisements (
                    id, provider_id, title, description, ad_size_id, ad_type,
                    category, image_url, click_url, start_date, end_date,
                    total_budget, pricing_model, status, target_regions,
                    target_languages, target_countries, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                generateUUID(),
                $providerId,
                $title,
                $description,
                $adSizeId,
                $adType,
                $category,
                $imageUrl,
                $clickUrl,
                $startDate,
                $endDate,
                $totalBudget,
                $pricingModel,
                $status,
                json_encode(['regions' => $targetRegions, 'languages' => $targetLanguages]),
                json_encode(['languages' => $targetLanguages]),
                json_encode(['countries' => $targetCountries])
            ]);
            
            $message = 'Advertisement created successfully!';
        } elseif ($formAction === 'approve_ad') {
            $adId = trim($_POST['ad_id'] ?? '');
            $notes = trim($_POST['approval_notes'] ?? '');
            
            if (empty($adId)) throw new Exception('Ad ID required');
            
            $sql = "
                UPDATE advertisements
                SET status = 'approved', approved_by = ?, approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user['id'] ?? $user['user_id'], $notes, $adId]);
            
            $message = 'Advertisement approved!';
        } elseif ($formAction === 'reject_ad') {
            $adId = trim($_POST['ad_id'] ?? '');
            $notes = trim($_POST['approval_notes'] ?? '');
            
            if (empty($adId)) throw new Exception('Ad ID required');
            
            $sql = "
                UPDATE advertisements
                SET status = 'archived', approved_by = ?, approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user['id'] ?? $user['user_id'], $notes, $adId]);
            
            $message = 'Advertisement rejected!';
        } elseif ($formAction === 'pause_ad') {
            $adId = trim($_POST['ad_id'] ?? '');
            if (empty($adId)) throw new Exception('Ad ID required');
            
            $sql = "UPDATE advertisements SET status = 'paused' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$adId]);
            
            $message = 'Advertisement paused!';
        } elseif ($formAction === 'resume_ad') {
            $adId = trim($_POST['ad_id'] ?? '');
            if (empty($adId)) throw new Exception('Ad ID required');
            
            $sql = "UPDATE advertisements SET status = 'active' WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$adId]);
            
            $message = 'Advertisement resumed!';
        } elseif ($formAction === 'delete_ad') {
            $adId = trim($_POST['ad_id'] ?? '');
            if (empty($adId)) throw new Exception('Ad ID required');
            
            $sql = "DELETE FROM advertisements WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$adId]);
            
            $message = 'Advertisement deleted!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get filters
$filterStatus = trim($_GET['status'] ?? 'all');
$filterProvider = trim($_GET['provider'] ?? '');
$searchQuery = trim($_GET['search'] ?? '');

// Prepare query
$whereConditions = [];
$params = [];

if ($filterStatus !== 'all') {
    $whereConditions[] = "a.status = ?";
    $params[] = $filterStatus;
}

if (!empty($filterProvider)) {
    $whereConditions[] = "a.provider_id = ?";
    $params[] = $filterProvider;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(a.title LIKE ? OR a.description LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get advertisements
$sql = "
    SELECT a.*, p.company_name, pt.display_name as size_name
    FROM advertisements a
    LEFT JOIN ad_providers p ON a.provider_id = p.id
    LEFT JOIN ad_pricing_tiers pt ON a.ad_size_id = pt.id
    {$whereClause}
    ORDER BY a.created_at DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$advertisements = $stmt->fetchAll();

// Get providers for dropdown
$sql = "SELECT id, company_name FROM ad_providers WHERE status = 'approved' ORDER BY company_name";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$providers = $stmt->fetchAll();

// Get pricing tiers
$sql = "SELECT id, display_name, size_type FROM ad_pricing_tiers WHERE is_active = 1 ORDER BY display_name";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$pricingTiers = $stmt->fetchAll();

// Get regions and languages
$regions = ['TamilNadu', 'Karnataka', 'Kerala', 'Telangana', 'Maharashtra', 'Gujarat', 'WestBengal', 'AndhraPradesh'];
$languages = ['ta' => 'Tamil', 'ka' => 'Kannada', 'ml' => 'Malayalam', 'te' => 'Telugu', 'en' => 'English', 'mr' => 'Marathi', 'gu' => 'Gujarati', 'bn' => 'Bengali'];
$countries = ['IN' => 'India', 'US' => 'USA', 'UK' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia'];
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Management - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .admin-container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-header h1 { font-size: 2rem; color: var(--text-primary); }
        .btn-group { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn { padding: 0.65rem 1.2rem; border-radius: 8px; border: none; cursor: pointer; font-weight: 600; transition: all 0.3s; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-2px); }
        .btn-secondary { background: var(--border-color); color: var(--text-primary); }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #000; }
        
        .filters-section { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; border: 1px solid var(--border-color); }
        .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-weight: 600; margin-bottom: 0.5rem; color: var(--text-primary); }
        .form-group input, .form-group select { padding: 0.65rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-primary); font-size: 0.95rem; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1); }
        
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 2000; display: none; align-items: center; justify-content: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: var(--bg-card); border-radius: 12px; padding: 2rem; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; }
        .modal-header h2 { font-size: 1.5rem; color: var(--text-primary); }
        .modal-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); }
        .modal-close:hover { color: var(--text-primary); }
        
        .ads-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .ads-table thead { background: var(--bg-hover); border: 1px solid var(--border-color); }
        .ads-table th { padding: 1rem; text-align: left; font-weight: 600; color: var(--text-primary); }
        .ads-table td { padding: 0.875rem 1rem; border-bottom: 1px solid var(--border-color); }
        .ads-table tbody tr:hover { background: var(--bg-hover); }
        
        .status-badge { display: inline-block; padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-draft { background: #e0e0e0; color: #333; }
        .status-pending_approval { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-active { background: #d1ecf1; color: #0c5460; }
        .status-paused { background: #f8d7da; color: #721c24; }
        .status-completed { background: #d3d3d3; color: #333; }
        .status-archived { background: #c3e6cb; color: #155724; }
        
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .action-buttons button { padding: 0.4rem 0.8rem; font-size: 0.85rem; }
        
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: var(--bg-card); border-radius: 12px; padding: 1.5rem; border-left: 4px solid var(--primary); }
        .stat-value { font-size: 2rem; font-weight: 700; color: var(--primary); }
        .stat-label { font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem; }
        
        .empty-state { text-align: center; padding: 3rem 1rem; }
        .empty-state-icon { font-size: 3rem; color: var(--text-muted); margin-bottom: 1rem; }
        .empty-state-text { color: var(--text-muted); font-size: 1.1rem; }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .filter-row { grid-template-columns: 1fr; }
            .ads-table { font-size: 0.9rem; }
            .ads-table th, .ads-table td { padding: 0.5rem; }
            .action-buttons { flex-direction: column; }
            .action-buttons button { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-ad"></i> Advertisement Management</h1>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Manage all ads, approvals, and performance</p>
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i> Create New Ad
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
                <div class="stat-value"><?= count(array_filter($advertisements, fn($a) => $a['status'] === 'pending_approval')) ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count(array_filter($advertisements, fn($a) => $a['status'] === 'active')) ?></div>
                <div class="stat-label">Active Ads</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($advertisements) ?></div>
                <div class="stat-label">Total Ads</div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" onchange="this.form.submit()">
                            <option value="all">All Statuses</option>
                            <option value="draft" <?= $filterStatus === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="pending_approval" <?= $filterStatus === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                            <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="paused" <?= $filterStatus === 'paused' ? 'selected' : '' ?>>Paused</option>
                            <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="archived" <?= $filterStatus === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Provider</label>
                        <select name="provider" onchange="this.form.submit()">
                            <option value="">All Providers</option>
                            <?php foreach ($providers as $provider): ?>
                                <option value="<?= $provider['id'] ?>" <?= $filterProvider === $provider['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($provider['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Search Title</label>
                        <input type="text" name="search" placeholder="Search ads..." value="<?= htmlspecialchars($searchQuery) ?>" onkeyup="if(event.key==='Enter') this.form.submit()">
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Advertisements Table -->
        <?php if (!empty($advertisements)): ?>
            <div style="overflow-x: auto;">
                <table class="ads-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Provider</th>
                            <th>Size</th>
                            <th>Budget</th>
                            <th>Status</th>
                            <th>Impressions</th>
                            <th>Clicks</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($advertisements as $ad): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars(substr($ad['title'], 0, 30)) ?></strong>
                                    <?php if (strlen($ad['title']) > 30): ?><span>...</span><?php endif; ?>
                                    <br>
                                    <small style="color: var(--text-muted);">Started: <?= date('M d', strtotime($ad['start_date'])) ?></small>
                                </td>
                                <td><?= htmlspecialchars($ad['company_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($ad['size_name'] ?? 'N/A') ?></td>
                                <td>
                                    <strong>$<?= number_format($ad['total_budget'], 2) ?></strong>
                                    <br>
                                    <small>Spent: $<?= number_format($ad['budget_spent'] ?? 0, 2) ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= $ad['status'] ?>">
                                        <?= ucfirst(str_replace('_', ' ', $ad['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= number_format($ad['impressions'] ?? 0) ?></strong>
                                    <br>
                                    <small style="color: var(--text-muted);">CTR: <?= number_format($ad['ctr'] ?? 0, 2) ?>%</small>
                                </td>
                                <td><strong><?= number_format($ad['clicks'] ?? 0) ?></strong></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-secondary" onclick="openViewModal('<?= $ad['id'] ?>')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <?php if ($ad['status'] === 'pending_approval'): ?>
                                            <button class="btn btn-success" onclick="approveAd('<?= $ad['id'] ?>')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger" onclick="rejectAd('<?= $ad['id'] ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php elseif ($ad['status'] === 'active'): ?>
                                            <button class="btn btn-warning" onclick="pauseAd('<?= $ad['id'] ?>')">
                                                <i class="fas fa-pause"></i> Pause
                                            </button>
                                        <?php elseif ($ad['status'] === 'paused'): ?>
                                            <button class="btn btn-primary" onclick="resumeAd('<?= $ad['id'] ?>')">
                                                <i class="fas fa-play"></i> Resume
                                            </button>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-danger" onclick="deleteAd('<?= $ad['id'] ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <div class="empty-state-text">No advertisements found</div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Create/Edit Modal -->
    <div class="modal-overlay" id="createModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Create New Advertisement</h2>
                <button class="modal-close" onclick="closeCreateModal()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="form_action" value="create_ad">
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Title *</label>
                    <input type="text" name="title" required placeholder="Ad title...">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Ad description..." style="padding: 0.65rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-primary);"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label>Provider *</label>
                        <select name="provider_id" required>
                            <option value="">Select Provider</option>
                            <?php foreach ($providers as $provider): ?>
                                <option value="<?= $provider['id'] ?>">
                                    <?= htmlspecialchars($provider['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Ad Size *</label>
                        <select name="ad_size_id" required>
                            <option value="">Select Size</option>
                            <?php foreach ($pricingTiers as $tier): ?>
                                <option value="<?= $tier['id'] ?>">
                                    <?= htmlspecialchars($tier['display_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label>Ad Type</label>
                        <select name="ad_type">
                            <option value="image">Image</option>
                            <option value="text">Text</option>
                            <option value="video">Video</option>
                            <option value="custom">Custom HTML</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Category</label>
                        <input type="text" name="category" placeholder="e.g., Tech, Deals, Guides">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Image URL</label>
                    <input type="url" name="image_url" placeholder="https://...">
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Click URL</label>
                    <input type="url" name="click_url" placeholder="https://...">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>End Date</label>
                        <input type="date" name="end_date" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label>Total Budget</label>
                        <input type="number" name="total_budget" step="0.01" value="0" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Pricing Model</label>
                        <select name="pricing_model">
                            <option value="fixed">Fixed Monthly</option>
                            <option value="cpm">CPM (per 1000 impressions)</option>
                            <option value="cpc">CPC (per click)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Target Languages</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.5rem;">
                        <?php foreach ($languages as $code => $name): ?>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="target_languages[]" value="<?= $code ?>" style="margin-right: 0.5rem;">
                                <?= htmlspecialchars($name) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label>Target Regions</label>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.5rem;">
                        <?php foreach ($regions as $region): ?>
                            <label style="display: flex; align-items: center; cursor: pointer;">
                                <input type="checkbox" name="target_regions[]" value="<?= $region ?>" style="margin-right: 0.5rem;">
                                <?= htmlspecialchars($region) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Create Advertisement
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeCreateModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Approval Modal -->
    <div class="modal-overlay" id="approvalModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Approval Action</h2>
                <button class="modal-close" onclick="closeApprovalModal()">&times;</button>
            </div>
            
            <form method="POST" action="" id="approvalForm">
                <input type="hidden" name="ad_id" id="approvalAdId">
                <input type="hidden" name="form_action" id="approvalFormAction" value="approve_ad">
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label>Notes (Optional)</label>
                    <textarea name="approval_notes" rows="4" placeholder="Add any notes..." style="padding: 0.65rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-primary);"></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-check"></i> Confirm
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeApprovalModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openCreateModal() {
            document.getElementById('createModal').classList.add('active');
        }
        
        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
        }
        
        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.remove('active');
        }
        
        function approveAd(adId) {
            document.getElementById('approvalAdId').value = adId;
            document.getElementById('approvalFormAction').value = 'approve_ad';
            document.querySelector('#approvalModal .modal-header h2').textContent = 'Approve Advertisement';
            document.getElementById('approvalModal').classList.add('active');
        }
        
        function rejectAd(adId) {
            document.getElementById('approvalAdId').value = adId;
            document.getElementById('approvalFormAction').value = 'reject_ad';
            document.querySelector('#approvalModal .modal-header h2').textContent = 'Reject Advertisement';
            document.getElementById('approvalModal').classList.add('active');
        }
        
        function pauseAd(adId) {
            if (confirm('Pause this advertisement?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="form_action" value="pause_ad"><input type="hidden" name="ad_id" value="${adId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function resumeAd(adId) {
            if (confirm('Resume this advertisement?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="form_action" value="resume_ad"><input type="hidden" name="ad_id" value="${adId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function deleteAd(adId) {
            if (confirm('Delete this advertisement? This cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="form_action" value="delete_ad"><input type="hidden" name="ad_id" value="${adId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function openViewModal(adId) {
            alert('View modal for ad: ' + adId + ' (To be implemented)');
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeCreateModal();
                closeApprovalModal();
            }
        });
    </script>
</body>
</html>
