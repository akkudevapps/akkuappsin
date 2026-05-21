<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/ad-engine.php';
require_once '../includes/payment-engine.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

global $pdo;
$message = '';
$error = '';
$viewId = trim((string) ($_GET['view'] ?? ''));
$editId = trim((string) ($_GET['edit'] ?? ''));

function adFieldValue(array $source, string $field, $default = '')
{
    return array_key_exists($field, $source) ? $source[$field] : $default;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_ad'])) {
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
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

            if ($title === '' || $providerId === '' || $adSizeId === '') {
                throw new Exception('Title, Provider, and Ad Size are required.');
            }

            $targetRegions = $_POST['target_regions'] ?? [];
            $targetLanguages = $_POST['target_languages'] ?? [];
            $targetCountries = $_POST['target_countries'] ?? [];

            $sql = "
                INSERT INTO advertisements (
                    id, provider_id, title, description, ad_size_id, ad_type,
                    category, image_url, click_url, start_date, end_date,
                    total_budget, pricing_model, status, target_regions,
                    target_languages, target_countries, is_featured, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
                json_encode(['regions' => $targetRegions]),
                json_encode(['languages' => $targetLanguages]),
                json_encode(['countries' => $targetCountries]),
                $isFeatured
            ]);

            $message = 'Advertisement created successfully.';
        }

        if (isset($_POST['update_ad'])) {
            $adId = trim($_POST['ad_id'] ?? '');
            if ($adId === '') throw new Exception('Missing ad identifier.');

            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $adType = trim($_POST['ad_type'] ?? 'image');
            $category = trim($_POST['category'] ?? 'general');
            $imageUrl = trim($_POST['image_url'] ?? '');
            $clickUrl = trim($_POST['click_url'] ?? '');
            $startDate = trim($_POST['start_date'] ?? date('Y-m-d'));
            $endDate = trim($_POST['end_date'] ?? date('Y-m-d', strtotime('+30 days')));
            $totalBudget = floatval($_POST['total_budget'] ?? 0);
            $pricingModel = trim($_POST['pricing_model'] ?? 'fixed');
            $status = trim($_POST['status'] ?? 'draft');
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;

            if ($title === '') throw new Exception('Title is required.');

            $targetRegions = $_POST['target_regions'] ?? [];
            $targetLanguages = $_POST['target_languages'] ?? [];
            $targetCountries = $_POST['target_countries'] ?? [];

            $sql = "UPDATE advertisements SET 
                    title = ?, description = ?, ad_type = ?, category = ?, 
                    image_url = ?, click_url = ?, start_date = ?, end_date = ?,
                    total_budget = ?, pricing_model = ?, status = ?, is_featured = ?,
                    target_regions = ?, target_languages = ?, target_countries = ?,
                    updated_at = NOW()
                    WHERE id = ?";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $title, $description, $adType, $category,
                $imageUrl, $clickUrl, $startDate, $endDate,
                $totalBudget, $pricingModel, $status, $isFeatured,
                json_encode(['regions' => $targetRegions]),
                json_encode(['languages' => $targetLanguages]),
                json_encode(['countries' => $targetCountries]),
                $adId
            ]);

            $message = 'Advertisement updated successfully.';
            $editId = $adId;
            $viewId = $adId;
        }

        if (isset($_POST['update_status'])) {
            $adId = trim($_POST['ad_id'] ?? '');
            $status = trim($_POST['status'] ?? 'draft');
            if ($adId !== '') {
                $sql = "UPDATE advertisements SET status = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$status, $adId]);
                $message = 'Ad status updated.';
            }
        }

        if (isset($_POST['toggle_featured'])) {
            $adId = trim($_POST['ad_id'] ?? '');
            if ($adId !== '') {
                $sql = "UPDATE advertisements SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END, updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$adId]);
                $message = 'Featured flag updated.';
            }
        }

        if (isset($_POST['approve_ad'])) {
            $adId = trim($_POST['ad_id'] ?? '');
            $notes = trim($_POST['approval_notes'] ?? '');
            if ($adId !== '') {
                $sql = "UPDATE advertisements SET status = 'approved', approved_by = ?, approved_at = NOW(), approval_notes = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user['id'] ?? $user['user_id'], $notes, $adId]);
                $message = 'Advertisement approved.';
            }
        }

        if (isset($_POST['reject_ad'])) {
            $adId = trim($_POST['ad_id'] ?? '');
            $notes = trim($_POST['approval_notes'] ?? '');
            if ($adId !== '') {
                $sql = "UPDATE advertisements SET status = 'archived', approved_by = ?, approved_at = NOW(), approval_notes = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$user['id'] ?? $user['user_id'], $notes, $adId]);
                $message = 'Advertisement rejected.';
            }
        }

        if (isset($_POST['delete_ad'])) {
            $adId = trim($_POST['ad_id'] ?? '');
            if ($adId !== '') {
                $sql = "DELETE FROM advertisements WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$adId]);
                $message = 'Advertisement deleted.';
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$ads = [];
$selectedAd = null;
$stats = [
    'total' => 0,
    'active' => 0,
    'draft' => 0,
    'pending' => 0,
    'featured' => 0,
    'revenue' => 0,
];

try {
    $sql = "
        SELECT a.*, p.company_name as provider_name, pt.display_name as size_name,
               (SELECT COUNT(*) FROM ad_impressions WHERE ad_id = a.id) as impression_count,
               (SELECT COUNT(*) FROM ad_clicks WHERE ad_id = a.id) as click_count
        FROM advertisements a
        LEFT JOIN ad_providers p ON a.provider_id = p.id
        LEFT JOIN ad_pricing_tiers pt ON a.ad_size_id = pt.id
        ORDER BY a.created_at DESC
    ";

    $ads = $pdo->query($sql)->fetchAll();

    foreach ($ads as $ad) {
        $stats['total']++;
        if (($ad['status'] ?? '') === 'active') $stats['active']++;
        if (($ad['status'] ?? '') === 'draft') $stats['draft']++;
        if (($ad['status'] ?? '') === 'pending_approval') $stats['pending']++;
        if (!empty($ad['is_featured'])) $stats['featured']++;
        $stats['revenue'] += floatval($ad['budget_spent'] ?? 0);
    }
} catch (Exception $e) {
    $ads = [];
    $error = 'Unable to load advertisements: ' . $e->getMessage();
}

if ($viewId !== '' || $editId !== '') {
    $lookupId = $viewId !== '' ? $viewId : $editId;
    foreach ($ads as $ad) {
        if ((string) ($ad['id'] ?? '') === $lookupId) {
            $selectedAd = $ad;
            break;
        }
    }
}

$regions = ['TamilNadu', 'Karnataka', 'Kerala', 'Telangana', 'Maharashtra', 'Gujarat', 'WestBengal', 'AndhraPradesh'];
$languages = ['ta' => 'Tamil', 'ka' => 'Kannada', 'ml' => 'Malayalam', 'te' => 'Telugu', 'en' => 'English', 'mr' => 'Marathi', 'gu' => 'Gujarati', 'bn' => 'Bengali'];
$countries = ['IN' => 'India', 'US' => 'USA', 'UK' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia'];

$providers = [];
$pricingTiers = [];
try {
    $providers = $pdo->query("SELECT id, company_name FROM ad_providers WHERE status = 'approved' ORDER BY company_name")->fetchAll();
    $pricingTiers = $pdo->query("SELECT id, display_name, size_type FROM ad_pricing_tiers WHERE is_active = 1 ORDER BY display_name")->fetchAll();
} catch (Exception $e) {}

$editorAd = $selectedAd;
$editorType = $selectedAd ? ($selectedAd['ad_type'] ?? 'image') : 'image';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advertising Management - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .ad-admin-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, .9fr); gap: 1.5rem; }
        .metric-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .metric-card { padding: 1.25rem; border-radius: 20px; background: var(--card-bg); border: 1px solid var(--border-color); position: relative; overflow: hidden; transition: all 0.3s ease; }
        .metric-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .metric-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .metric-card:nth-child(1)::before { background: linear-gradient(90deg, var(--primary), var(--primary-light)); }
        .metric-card:nth-child(2)::before { background: linear-gradient(90deg, var(--secondary), #34d399); }
        .metric-card:nth-child(3)::before { background: linear-gradient(90deg, var(--warning), #fbbf24); }
        .metric-card:nth-child(4)::before { background: linear-gradient(90deg, var(--purple), #c084fc); }
        .metric-card:nth-child(5)::before { background: linear-gradient(90deg, var(--info), #38bdf8); }
        .metric-card:nth-child(6)::before { background: linear-gradient(90deg, var(--pink), #f472b6); }
        .metric-card h3 { font-size: .85rem; color: var(--text-secondary); margin-bottom: .45rem; display: flex; align-items: center; gap: .5rem; }
        .metric-card strong { font-size: 1.8rem; color: var(--text-primary); }
        .metric-icon { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: .85rem; }
        .metric-card:nth-child(1) .metric-icon { background: rgba(99,102,241,.12); color: var(--primary-light); }
        .metric-card:nth-child(2) .metric-icon { background: rgba(16,185,129,.12); color: var(--secondary); }
        .metric-card:nth-child(3) .metric-icon { background: rgba(245,158,11,.12); color: var(--warning); }
        .metric-card:nth-child(4) .metric-icon { background: rgba(168,85,247,.12); color: var(--purple); }
        .metric-card:nth-child(5) .metric-icon { background: rgba(56,189,248,.12); color: var(--info); }
        .metric-card:nth-child(6) .metric-icon { background: rgba(244,114,182,.12); color: var(--pink); }
        .ad-note { margin-top: .75rem; padding: 1rem 1.1rem; border-radius: 16px; background: rgba(59,130,246,.08); color: var(--text-secondary); border: 1px solid rgba(59,130,246,.18); }
        .engine-label { display: inline-flex; align-items: center; gap: .45rem; border-radius: 999px; padding: .45rem .8rem; background: rgba(245,158,11,.12); color: #fbbf24; font-size: .82rem; margin-bottom: .8rem; }
        .asset-links { display: grid; gap: .85rem; }
        .asset-link-card { padding: 1rem; border-radius: 16px; border: 1px solid var(--border-color); background: var(--card-bg); }
        .asset-link-card a { word-break: break-word; }
        .editor-actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1rem; }
        .table-title { display: grid; gap: .15rem; }
        .table-title a { color: var(--text-primary); font-weight: 600; }

        .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
        .status-badge.active { background: rgba(16,185,129,.12); color: #34d399; }
        .status-badge.draft { background: rgba(245,158,11,.12); color: #fbbf24; }
        .status-badge.pending_approval { background: rgba(239,68,68,.12); color: #f87171; }
        .status-badge.approved { background: rgba(56,189,248,.12); color: #38bdf8; }
        .status-badge.paused { background: rgba(107,114,128,.12); color: #9ca3af; }
        .status-badge.archived { background: rgba(107,114,128,.12); color: #6b7280; }
        .status-badge.completed { background: rgba(168,85,247,.12); color: #c084fc; }

        .search-filter-bar { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; align-items: center; }
        .search-input-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-input-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: .8rem; }
        .search-input-wrap input { width: 100%; padding: 8px 10px 8px 32px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 10px; color: var(--text-primary); font-size: .85rem; }
        .search-input-wrap input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(99,102,241,.15); }
        .filter-select { padding: 8px 10px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 10px; color: var(--text-primary); font-size: .85rem; cursor: pointer; }

        .action-dropdown { position: relative; display: inline-block; }
        .action-dropdown-menu { position: absolute; top: 100%; right: 0; min-width: 160px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; box-shadow: var(--shadow-lg); z-index: 100; display: none; overflow: hidden; }
        .action-dropdown.open .action-dropdown-menu { display: block; }
        .action-dropdown-item { display: flex; align-items: center; gap: .5rem; padding: 8px 12px; font-size: .8rem; color: var(--text-secondary); border: none; background: none; width: 100%; text-align: left; cursor: pointer; text-decoration: none; }
        .action-dropdown-item:hover { background: var(--bg-hover); color: var(--text-primary); }

        .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 4000; display: flex; flex-direction: column; gap: .5rem; }
        .toast { padding: .75rem 1rem; border-radius: 12px; background: var(--bg-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: .5rem; font-size: .85rem; animation: slideInRight .3s ease; min-width: 250px; }
        .toast.success { border-left: 3px solid var(--secondary); }
        .toast.error { border-left: 3px solid var(--danger); }
        .toast.info { border-left: 3px solid var(--info); }

        .workspace-tabs { display: flex; gap: 2px; border-bottom: 1px solid var(--border-color); margin-bottom: 1rem; }
        .workspace-tab { padding: 8px 14px; font-size: .8rem; color: var(--text-muted); background: none; border: none; cursor: pointer; border-bottom: 2px solid transparent; transition: all .15s; }
        .workspace-tab:hover { color: var(--text-secondary); }
        .workspace-tab.active { color: var(--primary-light); border-bottom-color: var(--primary); }
        .workspace-panel { display: none; }
        .workspace-panel.active { display: block; animation: fadeIn .2s ease; }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        
        .create-ad-form { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius-lg); padding: 1.5rem; }
        .create-ad-form .form-section { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .create-ad-form .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .form-section-title { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-section-title i { font-size: 0.8rem; color: var(--warning); }
        .form-group small { font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem; }
        .form-control::placeholder { color: var(--text-muted); opacity: 0.7; }
        
        .editor-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); backdrop-filter: blur(4px); z-index: 3000; display: none; align-items: center; justify-content: center; padding: 1rem; }
        .editor-modal-overlay.active { display: flex; }
        .editor-modal { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius-lg); width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-lg); }
        .editor-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--border-color); }
        .editor-modal-header h3 { font-size: 1rem; color: var(--text-primary); }
        .editor-modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer; padding: 4px; border-radius: 6px; }
        .editor-modal-close:hover { background: var(--bg-hover); color: var(--text-primary); }
        .editor-modal-body { padding: 1rem; }
        .editor-modal-footer { display: flex; justify-content: flex-end; gap: .5rem; padding: 1rem; border-top: 1px solid var(--border-color); }

        .ad-type-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 6px; font-size: .7rem; font-weight: 600; }
        .ad-type-badge.image { background: rgba(16,185,129,.12); color: #34d399; }
        .ad-type-badge.text { background: rgba(99,102,241,.12); color: #818cf8; }
        .ad-type-badge.video { background: rgba(239,68,68,.12); color: #f87171; }
        .ad-type-badge.custom { background: rgba(168,85,247,.12); color: #c084fc; }

        .checkbox-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 0.5rem; }
        .checkbox-item { display: flex; align-items: center; gap: 0.4rem; cursor: pointer; font-size: 0.8rem; color: var(--text-secondary); }
        .checkbox-item input[type="checkbox"] { accent-color: var(--primary); }

        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

        @media (max-width: 1100px) {
            .ad-admin-grid { grid-template-columns: 1fr; }
            .metric-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .metric-grid { grid-template-columns: repeat(2, 1fr); gap: .5rem; }
            .metric-card { padding: .85rem; border-radius: 12px; }
            .metric-card strong { font-size: 1.4rem; }
            .metric-card h3 { font-size: 0.75rem; }
            .metric-icon { width: 28px !important; height: 28px !important; font-size: 0.7rem !important; }
            .search-filter-bar { flex-direction: column; }
            .search-input-wrap { min-width: 100%; }
            .filter-select { width: 100%; }
            .editor-actions { flex-direction: column; }
            .editor-actions .btn { width: 100%; justify-content: center; }
            .form-grid { grid-template-columns: 1fr; }
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; left: -9999px; top: -9999px; }
            tr { border: 1px solid var(--border-color); margin-bottom: .75rem; border-radius: 12px; overflow: hidden; }
            td { display: flex; justify-content: space-between; align-items: center; padding: .6rem 1rem; border-bottom: 1px solid var(--border-color); }
            td:last-child { border-bottom: none; }
            td::before { content: attr(data-label); font-weight: 600; color: var(--text-secondary); font-size: .75rem; text-transform: uppercase; }
            .toolbar-row { flex-direction: column; gap: .4rem; }
            .toolbar-row .btn, .toolbar-row form { width: 100%; }
            .workspace-tabs { overflow-x: auto; }
            .create-ad-form { padding: 1rem; border-radius: 8px; }
            .create-ad-form .form-section { margin-bottom: 1.5rem; padding-bottom: 1rem; }
            .form-section-title { font-size: 0.75rem; margin-bottom: 0.75rem; }
            .editor-modal { max-width: 90vw !important; }
            .editor-modal-body { max-height: calc(80vh - 120px); overflow-y: auto; padding: 0.75rem; }
            .page-shell { padding: 0.75rem !important; }
            .welcome-banner h1 { font-size: 1.3rem !important; }
            .welcome-banner p { font-size: 0.85rem !important; }
            .chart-container { padding: 1rem !important; }
            .chart-container h2 { font-size: 1.1rem !important; }
        }
        @media (max-width: 480px) {
            .metric-grid { grid-template-columns: 1fr 1fr; }
            .create-ad-form { padding: 0.75rem; }
            .ad-note { padding: 0.6rem; font-size: 0.75rem; margin-bottom: 0.75rem; }
            .editor-modal { max-width: 95vw !important; }
            .welcome-banner h1 { font-size: 1.1rem !important; }
            .form-section-title { font-size: 0.7rem; }
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
                <span class="engine-label"><i class="fas fa-ad"></i> Advertising Management Hub</span>
                <h1>Ad Campaign Control Center</h1>
                <p>Manage all advertisements, track performance, approve campaigns, and optimize ad placements from one centralized dashboard.</p>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="metric-grid">
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-layer-group"></i></span> Total Ads</h3><strong><?= (int) $stats['total'] ?></strong></div>
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-play-circle"></i></span> Active</h3><strong><?= (int) $stats['active'] ?></strong></div>
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-clock"></i></span> Pending</h3><strong><?= (int) $stats['pending'] ?></strong></div>
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-pen-nib"></i></span> Drafts</h3><strong><?= (int) $stats['draft'] ?></strong></div>
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-star"></i></span> Featured</h3><strong><?= (int) $stats['featured'] ?></strong></div>
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-dollar-sign"></i></span> Revenue</h3><strong>$<?= number_format($stats['revenue'], 2) ?></strong></div>
            </div>

            <div class="ad-admin-grid">
                <section class="chart-container">
                    <h2><?= $editorAd ? 'Edit Advertisement' : 'Create Advertisement' ?></h2>
                    <div class="ad-note">
                        <i class="fas fa-lightbulb"></i> <strong>Tip:</strong> `Image` ads work best for visual campaigns. `Text` ads are lightweight and fast. `Video` ads engage users longer. `Custom HTML` allows full creative control.
                    </div>
                    <form method="POST" class="create-ad-form">
                        <?php if ($editorAd): ?>
                            <input type="hidden" name="update_ad" value="1">
                            <input type="hidden" name="ad_id" value="<?= htmlspecialchars((string) ($editorAd['id'] ?? '')) ?>">
                        <?php else: ?>
                            <input type="hidden" name="create_ad" value="1">
                        <?php endif; ?>

                        <div class="form-section">
                            <div class="form-section-title"><i class="fas fa-bullhorn"></i> Basic Information</div>
                            <div class="form-grid">
                                <div class="form-group" style="grid-column: 1/-1;">
                                    <label class="form-label">Ad Title <span style="color: var(--danger);">*</span></label>
                                    <input class="form-control" type="text" name="title" required value="<?= htmlspecialchars((string) adFieldValue($editorAd ?? [], 'title')) ?>" placeholder="Enter a compelling ad headline...">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Provider <span style="color: var(--danger);">*</span></label>
                                    <select class="form-control" name="provider_id" required>
                                        <option value="">Select Provider</option>
                                        <?php foreach ($providers as $provider): ?>
                                            <option value="<?= htmlspecialchars($provider['id']) ?>" <?= ($editorAd['provider_id'] ?? '') === $provider['id'] ? 'selected' : '' ?>><?= htmlspecialchars($provider['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Ad Size <span style="color: var(--danger);">*</span></label>
                                    <select class="form-control" name="ad_size_id" required>
                                        <option value="">Select Size</option>
                                        <?php foreach ($pricingTiers as $tier): ?>
                                            <option value="<?= htmlspecialchars($tier['id']) ?>" <?= ($editorAd['ad_size_id'] ?? '') === $tier['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tier['display_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Ad Type</label>
                                    <select class="form-control" name="ad_type">
                                        <option value="image" <?= $editorType === 'image' ? 'selected' : '' ?>>🖼️ Image</option>
                                        <option value="text" <?= $editorType === 'text' ? 'selected' : '' ?>>📝 Text</option>
                                        <option value="video" <?= $editorType === 'video' ? 'selected' : '' ?>>🎬 Video</option>
                                        <option value="custom" <?= $editorType === 'custom' ? 'selected' : '' ?>>⚙️ Custom HTML</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Status</label>
                                    <select class="form-control" name="status">
                                        <option value="draft" <?= adFieldValue($editorAd ?? [], 'status', 'draft') === 'draft' ? 'selected' : '' ?>>📝 Draft</option>
                                        <option value="pending_approval" <?= adFieldValue($editorAd ?? [], 'status') === 'pending_approval' ? 'selected' : '' ?>>⏳ Pending Approval</option>
                                        <option value="approved" <?= adFieldValue($editorAd ?? [], 'status') === 'approved' ? 'selected' : '' ?>>✅ Approved</option>
                                        <option value="active" <?= adFieldValue($editorAd ?? [], 'status') === 'active' ? 'selected' : '' ?>>🚀 Active</option>
                                        <option value="paused" <?= adFieldValue($editorAd ?? [], 'status') === 'paused' ? 'selected' : '' ?>>⏸️ Paused</option>
                                        <option value="archived" <?= adFieldValue($editorAd ?? [], 'status') === 'archived' ? 'selected' : '' ?>>📦 Archived</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Category</label>
                                    <input class="form-control" type="text" name="category" value="<?= htmlspecialchars((string) adFieldValue($editorAd ?? [], 'category', 'general')) ?>" placeholder="e.g., Tech, Deals, Gaming">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title"><i class="fas fa-file-alt"></i> Ad Content</div>
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" placeholder="Brief ad description or copy..."><?= htmlspecialchars((string) adFieldValue($editorAd ?? [], 'description')) ?></textarea>
                                <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Displayed alongside the ad creative</small>
                            </div>

                            <div class="form-grid" style="margin-top: 1rem;">
                                <div class="form-group">
                                    <label class="form-label">Image URL</label>
                                    <input class="form-control" type="text" name="image_url" value="<?= htmlspecialchars((string) adFieldValue($editorAd ?? [], 'image_url')) ?>" placeholder="/uploads/ads/campaign/banner.png">
                                    <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">URL or path to ad creative</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Click URL</label>
                                    <input class="form-control" type="text" name="click_url" value="<?= htmlspecialchars((string) adFieldValue($editorAd ?? [], 'click_url')) ?>" placeholder="https://landing-page.com">
                                    <small style="color: var(--text-muted); display: block; margin-top: 0.25rem;">Destination URL when ad is clicked</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title"><i class="fas fa-calendar"></i> Schedule & Budget</div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Start Date</label>
                                    <input class="form-control" type="date" name="start_date" value="<?= htmlspecialchars((string) adFieldValue($editorAd ?? [], 'start_date', date('Y-m-d'))) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">End Date</label>
                                    <input class="form-control" type="date" name="end_date" value="<?= htmlspecialchars((string) adFieldValue($editorAd ?? [], 'end_date', date('Y-m-d', strtotime('+30 days')))) ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Total Budget</label>
                                    <input class="form-control" type="number" name="total_budget" step="0.01" value="<?= htmlspecialchars((string) adFieldValue($editorAd ?? [], 'total_budget', '0')) ?>" placeholder="0.00">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pricing Model</label>
                                    <select class="form-control" name="pricing_model">
                                        <option value="fixed" <?= adFieldValue($editorAd ?? [], 'pricing_model', 'fixed') === 'fixed' ? 'selected' : '' ?>>Fixed Monthly</option>
                                        <option value="cpm" <?= adFieldValue($editorAd ?? [], 'pricing_model') === 'cpm' ? 'selected' : '' ?>>CPM (per 1000 impressions)</option>
                                        <option value="cpc" <?= adFieldValue($editorAd ?? [], 'pricing_model') === 'cpc' ? 'selected' : '' ?>>CPC (per click)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title"><i class="fas fa-globe"></i> Targeting</div>
                            <div class="form-group">
                                <label class="form-label">Target Languages</label>
                                <div class="checkbox-grid">
                                    <?php foreach ($languages as $code => $name): ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="target_languages[]" value="<?= $code ?>" <?= in_array($code, json_decode($editorAd['target_languages'] ?? '[]', true)['languages'] ?? []) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($name) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top: 1rem;">
                                <label class="form-label">Target Regions</label>
                                <div class="checkbox-grid">
                                    <?php foreach ($regions as $region): ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="target_regions[]" value="<?= $region ?>" <?= in_array($region, json_decode($editorAd['target_regions'] ?? '[]', true)['regions'] ?? []) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($region) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-group" style="margin-top: 1rem;">
                                <label class="form-label">Target Countries</label>
                                <div class="checkbox-grid">
                                    <?php foreach ($countries as $code => $name): ?>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="target_countries[]" value="<?= $code ?>" <?= in_array($code, json_decode($editorAd['target_countries'] ?? '[]', true)['countries'] ?? []) ? 'checked' : '' ?>>
                                            <?= htmlspecialchars($name) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="form-section-title"><i class="fas fa-gear"></i> Publishing Options</div>
                            <label class="toolbar-row muted-text" style="margin-bottom:0;">
                                <input type="checkbox" name="is_featured" value="1" <?= !empty($editorAd['is_featured']) ? 'checked' : '' ?>>
                                <span>Show in homepage featured ad section</span>
                            </label>
                        </div>

                        <div class="editor-actions" style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i>
                                <?= $editorAd ? 'Update Advertisement' : 'Create Advertisement' ?>
                            </button>
                            <?php if ($editorAd): ?>
                                <a class="btn btn-outline" href="/admin/advertising.php"><i class="fas fa-plus"></i> New Ad</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>

                <section class="chart-container">
                    <h2>Ad Workspace</h2>
                    <?php if ($selectedAd): ?>
                        <div class="workspace-tabs">
                            <button class="workspace-tab active" data-panel="overview">Overview</button>
                            <button class="workspace-tab" data-panel="performance">Performance</button>
                            <button class="workspace-tab" data-panel="targeting">Targeting</button>
                        </div>
                        <div class="workspace-panel active" id="panel-overview">
                            <div class="activity-list" style="margin-top:.5rem;">
                                <div class="activity-item">
                                    <div class="activity-copy">
                                        <strong><?= htmlspecialchars((string) ($selectedAd['title'] ?? 'Untitled')) ?></strong>
                                        <small>
                                            <span class="ad-type-badge <?= $selectedAd['ad_type'] ?? 'image' ?>"><?= ucfirst($selectedAd['ad_type'] ?? 'image') ?></span>
                                            • <span class="status-badge <?= $selectedAd['status'] ?? 'draft' ?>"><?= htmlspecialchars((string) ($selectedAd['status'] ?? 'draft')) ?></span>
                                            • <?= htmlspecialchars((string) ($selectedAd['category'] ?? 'general')) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="info-note">
                                    <?= nl2br(htmlspecialchars((string) ($selectedAd['description'] ?: 'No description added yet.'))) ?>
                                </div>
                                <div class="surface-card">
                                    <strong>Provider</strong>
                                    <p class="muted-text" style="margin-top:.55rem;">
                                        <?= htmlspecialchars((string) ($selectedAd['provider_name'] ?? 'N/A')) ?>
                                    </p>
                                </div>
                                <div class="surface-card">
                                    <strong>Ad Size</strong>
                                    <p class="muted-text" style="margin-top:.55rem;">
                                        <?= htmlspecialchars((string) ($selectedAd['size_name'] ?? 'N/A')) ?>
                                    </p>
                                </div>
                                <div class="surface-card">
                                    <strong>Budget</strong>
                                    <p class="muted-text" style="margin-top:.55rem;">
                                        $<?= number_format($selectedAd['total_budget'] ?? 0, 2) ?>
                                        <?php if ($selectedAd['budget_spent'] ?? 0 > 0): ?>
                                            • Spent: $<?= number_format($selectedAd['budget_spent'], 2) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="surface-card">
                                    <strong>Schedule</strong>
                                    <p class="muted-text" style="margin-top:.55rem;">
                                        <?= date('M j, Y', strtotime($selectedAd['start_date'] ?? date('Y-m-d'))) ?> → <?= date('M j, Y', strtotime($selectedAd['end_date'] ?? date('Y-m-d', strtotime('+30 days')))) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="workspace-panel" id="panel-performance">
                            <div style="margin-top:.5rem;">
                                <div class="surface-card">
                                    <strong>Impressions</strong>
                                    <p class="muted-text" style="margin-top:.55rem; font-size: 1.5rem; font-weight: 700; color: var(--primary);">
                                        <?= number_format($selectedAd['impression_count'] ?? 0) ?>
                                    </p>
                                </div>
                                <div class="surface-card" style="margin-top: 0.75rem;">
                                    <strong>Clicks</strong>
                                    <p class="muted-text" style="margin-top:.55rem; font-size: 1.5rem; font-weight: 700; color: var(--secondary);">
                                        <?= number_format($selectedAd['click_count'] ?? 0) ?>
                                    </p>
                                </div>
                                <div class="surface-card" style="margin-top: 0.75rem;">
                                    <strong>CTR (Click-Through Rate)</strong>
                                    <p class="muted-text" style="margin-top:.55rem; font-size: 1.5rem; font-weight: 700; color: var(--warning);">
                                        <?php 
                                            $impressions = $selectedAd['impression_count'] ?? 0;
                                            $clicks = $selectedAd['click_count'] ?? 0;
                                            echo $impressions > 0 ? number_format(($clicks / $impressions) * 100, 2) . '%' : '0.00%';
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="workspace-panel" id="panel-targeting">
                            <div style="margin-top:.5rem;">
                                <div class="surface-card">
                                    <strong>Target Languages</strong>
                                    <p class="muted-text" style="margin-top:.55rem;">
                                        <?php 
                                            $langs = json_decode($selectedAd['target_languages'] ?? '{}', true)['languages'] ?? [];
                                            echo !empty($langs) ? implode(', ', array_map(function($l) use ($languages) { return $languages[$l] ?? $l; }, $langs)) : 'All languages';
                                        ?>
                                    </p>
                                </div>
                                <div class="surface-card" style="margin-top: 0.75rem;">
                                    <strong>Target Regions</strong>
                                    <p class="muted-text" style="margin-top:.55rem;">
                                        <?php 
                                            $regs = json_decode($selectedAd['target_regions'] ?? '{}', true)['regions'] ?? [];
                                            echo !empty($regs) ? implode(', ', $regs) : 'All regions';
                                        ?>
                                    </p>
                                </div>
                                <div class="surface-card" style="margin-top: 0.75rem;">
                                    <strong>Target Countries</strong>
                                    <p class="muted-text" style="margin-top:.55rem;">
                                        <?php 
                                            $cnts = json_decode($selectedAd['target_countries'] ?? '{}', true)['countries'] ?? [];
                                            echo !empty($cnts) ? implode(', ', array_map(function($c) use ($countries) { return $countries[$c] ?? $c; }, $cnts)) : 'All countries';
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="margin-top:1rem;">Select an advertisement from the table to view its workspace, performance metrics, and targeting details.</div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="chart-container" style="margin-top:1.5rem;">
                <h2>All Advertisements</h2>
                <?php if (empty($ads)): ?>
                    <div class="empty-state" style="margin-top:1rem;">No advertisements found yet.</div>
                <?php else: ?>
                    <div class="search-filter-bar" style="margin-top:1rem;">
                        <div class="search-input-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="adSearch" placeholder="Search ads by title, provider, or category...">
                        </div>
                        <select class="filter-select" id="filterType">
                            <option value="">All Types</option>
                            <option value="image">Image</option>
                            <option value="text">Text</option>
                            <option value="video">Video</option>
                            <option value="custom">Custom HTML</option>
                        </select>
                        <select class="filter-select" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="draft">Draft</option>
                            <option value="pending_approval">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="paused">Paused</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
                    <div class="table-responsive" style="margin-top:1rem;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Ad</th>
                                    <th>Type</th>
                                    <th>Provider</th>
                                    <th>Status</th>
                                    <th>Budget</th>
                                    <th>Impressions</th>
                                    <th>Clicks</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="adsTableBody">
                            <?php foreach ($ads as $ad): ?>
                                <?php $adId = (string) ($ad['id'] ?? ''); ?>
                                <?php $adType = $ad['ad_type'] ?? 'image'; ?>
                                <?php $adStatus = $ad['status'] ?? 'draft'; ?>
                                <tr data-type="<?= strtolower($adType) ?>" data-status="<?= $adStatus ?>" data-search="<?= strtolower(htmlspecialchars(($ad['title'] ?? '') . ' ' . ($ad['provider_name'] ?? '') . ' ' . ($ad['category'] ?? ''))) ?>">
                                    <td data-label="Ad">
                                        <div class="table-title">
                                            <a href="/admin/advertising.php?view=<?= urlencode($adId) ?>"><?= htmlspecialchars((string) ($ad['title'] ?? 'Untitled')) ?></a>
                                            <span class="muted-text"><?= htmlspecialchars((string) ($ad['provider_name'] ?? 'N/A')) ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Type"><span class="ad-type-badge <?= $adType ?>"><?= ucfirst($adType) ?></span></td>
                                    <td data-label="Provider"><?= htmlspecialchars((string) ($ad['provider_name'] ?? '-')) ?></td>
                                    <td data-label="Status"><span class="status-badge <?= $adStatus ?>"><?= htmlspecialchars(str_replace('_', ' ', $adStatus)) ?></span></td>
                                    <td data-label="Budget">
                                        <strong>$<?= number_format($ad['total_budget'] ?? 0, 2) ?></strong>
                                        <?php if ($ad['budget_spent'] ?? 0 > 0): ?>
                                            <br><small class="muted-text">Spent: $<?= number_format($ad['budget_spent'], 2) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Impressions"><?= number_format($ad['impression_count'] ?? 0) ?></td>
                                    <td data-label="Clicks"><?= number_format($ad['click_count'] ?? 0) ?></td>
                                    <td data-label="Actions">
                                        <div class="action-dropdown">
                                            <button class="btn btn-secondary btn-sm" data-toggle-dropdown><i class="fas fa-ellipsis-v"></i> Actions</button>
                                            <div class="action-dropdown-menu">
                                                <a class="action-dropdown-item" href="/admin/advertising.php?edit=<?= urlencode($adId) ?>"><i class="fas fa-pen"></i> Edit</a>
                                                <a class="action-dropdown-item" href="/admin/advertising.php?view=<?= urlencode($adId) ?>"><i class="fas fa-eye"></i> View</a>
                                                <?php if ($adStatus === 'pending_approval'): ?>
                                                    <form method="POST" style="margin:0;">
                                                        <input type="hidden" name="ad_id" value="<?= htmlspecialchars($adId) ?>">
                                                        <button class="action-dropdown-item" type="submit" name="approve_ad"><i class="fas fa-check"></i> Approve</button>
                                                    </form>
                                                    <form method="POST" style="margin:0;">
                                                        <input type="hidden" name="ad_id" value="<?= htmlspecialchars($adId) ?>">
                                                        <button class="action-dropdown-item" type="submit" name="reject_ad"><i class="fas fa-times"></i> Reject</button>
                                                    </form>
                                                <?php elseif ($adStatus === 'active'): ?>
                                                    <form method="POST" style="margin:0;">
                                                        <input type="hidden" name="ad_id" value="<?= htmlspecialchars($adId) ?>">
                                                        <input type="hidden" name="status" value="paused">
                                                        <button class="action-dropdown-item" type="submit" name="update_status"><i class="fas fa-pause"></i> Pause</button>
                                                    </form>
                                                <?php elseif ($adStatus === 'paused'): ?>
                                                    <form method="POST" style="margin:0;">
                                                        <input type="hidden" name="ad_id" value="<?= htmlspecialchars($adId) ?>">
                                                        <input type="hidden" name="status" value="active">
                                                        <button class="action-dropdown-item" type="submit" name="update_status"><i class="fas fa-play"></i> Resume</button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="ad_id" value="<?= htmlspecialchars($adId) ?>">
                                                    <button class="action-dropdown-item" type="submit" name="toggle_featured"><i class="fas fa-star"></i> Toggle Featured</button>
                                                </form>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="ad_id" value="<?= htmlspecialchars($adId) ?>">
                                                    <button class="action-dropdown-item" type="submit" name="delete_ad" onclick="return confirm('Delete this advertisement? This cannot be undone.')"><i class="fas fa-trash"></i> Delete</button>
                                                </form>
                                            </div>
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

        <div class="toast-container" id="toastContainer"></div>
    </main>
</div>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
<script>
(function() {
    const searchInput = document.getElementById('adSearch');
    const filterType = document.getElementById('filterType');
    const filterStatus = document.getElementById('filterStatus');
    function filterAds() {
        const q = (searchInput?.value || '').toLowerCase();
        const ft = filterType?.value || '';
        const fs = filterStatus?.value || '';
        document.querySelectorAll('#adsTableBody tr').forEach(function(tr) {
            const match = (!q || tr.dataset.search.includes(q)) && (!ft || tr.dataset.type === ft) && (!fs || tr.dataset.status === fs);
            tr.style.display = match ? '' : 'none';
        });
    }
    if (searchInput) searchInput.addEventListener('input', filterAds);
    if (filterType) filterType.addEventListener('change', filterAds);
    if (filterStatus) filterStatus.addEventListener('change', filterAds);

    document.addEventListener('click', function(e) {
        const dd = e.target.closest('[data-toggle-dropdown]');
        if (dd) { dd.closest('.action-dropdown').classList.toggle('open'); return; }
        if (!e.target.closest('.action-dropdown')) document.querySelectorAll('.action-dropdown.open').forEach(function(d) { d.classList.remove('open'); });
    });

    document.querySelectorAll('.workspace-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            const panelId = 'panel-' + this.dataset.panel;
            this.closest('.chart-container').querySelectorAll('.workspace-tab').forEach(function(t) { t.classList.remove('active'); });
            this.closest('.chart-container').querySelectorAll('.workspace-panel').forEach(function(p) { p.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById(panelId).classList.add('active');
        });
    });
})();
</script>
</body>
</html>
