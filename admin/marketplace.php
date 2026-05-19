<?php
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/economy.php';
requireLogin();

$user = getCurrentUser();
if (empty($user) || $user['role'] !== 'admin') {
    header('Location: /user/dashboard.php?error=unauthorized');
    exit;
}

$tab = $_GET['tab'] ?? 'overview';
$action = $_GET['action'] ?? '';

// Handle Excel import
$importResult = null;
if ($action === 'import' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $importResult = handleExcelImport($pdo, $_FILES['excel_file']);
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && !empty($_POST['selected_ids'])) {
    handleBulkAction($pdo, $_POST);
}

$pageTitle = 'Marketplace Control Center';
include __DIR__ . '/../includes/header.php';

// Fetch data
$products = []; $brands = []; $categories = []; $orders = []; $customers = []; $stockData = [];

try { $brands = $pdo->query("SELECT * FROM cs_brands ORDER BY name")->fetchAll(); } catch (Exception $e) { $brands = []; }
try { $categories = $pdo->query("SELECT * FROM cs_categories ORDER BY name")->fetchAll(); } catch (Exception $e) { $categories = []; }
try {
    $products = $pdo->query("
        SELECT p.*, b.name as brand_name, c.name as category_name,
               COALESCE(vs.current_stock, 0) as current_stock,
               COALESCE(vs.reorder_level, 10) as reorder_level
        FROM cs_products p
        LEFT JOIN cs_brands b ON p.brand_id = b.id
        LEFT JOIN cs_categories c ON p.category_id = c.id
        LEFT JOIN cs_vw_product_stock vs ON p.id = vs.product_id
        ORDER BY p.created_at DESC LIMIT 50
    ")->fetchAll();
} catch (Exception $e) { $products = []; }
try {
    $orders = $pdo->query("
        SELECT i.*, c.name as customer_name, COUNT(ii.id) as item_count
        FROM cs_invoices i
        LEFT JOIN cs_customers c ON i.customer_id = c.id
        LEFT JOIN cs_invoice_items ii ON i.id = ii.invoice_id
        GROUP BY i.id ORDER BY i.created_at DESC LIMIT 30
    ")->fetchAll();
} catch (Exception $e) { $orders = []; }
try {
    $stockData = $pdo->query("
        SELECT p.id, p.name, p.sku, p.status,
               COALESCE(vs.current_stock, 0) as current_stock,
               COALESCE(vs.reorder_level, 10) as reorder_level,
               p.purchase_price, p.selling_price
        FROM cs_products p
        LEFT JOIN cs_vw_product_stock vs ON p.id = vs.product_id
        ORDER BY vs.current_stock ASC LIMIT 50
    ")->fetchAll();
} catch (Exception $e) { $stockData = []; }
try {
    $customers = $pdo->query("SELECT * FROM cs_customers ORDER BY created_at DESC LIMIT 30")->fetchAll();
} catch (Exception $e) { $customers = []; }

$stats = ['products'=>count($products),'brands'=>count($brands),'categories'=>count($categories),
          'orders'=>count($orders),'customers'=>count($customers),'low_stock'=>0,'out_of_stock'=>0,'revenue_30d'=>0];
try {
    $stats['low_stock'] = $pdo->query("SELECT COUNT(*) FROM cs_vw_product_stock WHERE current_stock <= reorder_level AND current_stock > 0")->fetchColumn() ?? 0;
    $stats['out_of_stock'] = $pdo->query("SELECT COUNT(*) FROM cs_vw_product_stock WHERE current_stock <= 0")->fetchColumn() ?? 0;
    $stats['revenue_30d'] = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM cs_invoices WHERE status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn() ?? 0;
} catch (Exception $e) {}

function handleExcelImport($pdo, $file) {
    $result = ['success'=>false,'message'=>'','imported'=>0,'errors'=>[]];
    if ($file['error'] !== UPLOAD_ERR_OK) { $result['message']='File upload failed'; return $result; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv','xlsx','xls'])) { $result['message']='Only CSV, XLSX, XLS files allowed'; return $result; }
    if ($ext === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) { $result['message']='Cannot read file'; return $result; }
        $headers = fgetcsv($handle);
        if (!$headers) { $result['message']='Empty file'; return $result; }
        $headers = array_map('strtolower', array_map('trim', $headers));
        $required = ['name','sku','selling_price'];
        $missing = array_diff($required, $headers);
        if (!empty($missing)) { $result['message']='Missing columns: '.implode(', ',$missing); return $result; }
        $rowNum = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) < count($headers)) continue;
            $data = array_combine($headers, $row);
            try {
                $nextId = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM cs_products")->fetchColumn();
                $brandId = null;
                if (!empty($data['brand'])) {
                    $stmt = $pdo->prepare("SELECT id FROM cs_brands WHERE name = ?");
                    $stmt->execute([trim($data['brand'])]);
                    $brandId = $stmt->fetchColumn();
                    if (!$brandId) {
                        $bId = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM cs_brands")->fetchColumn();
                        $pdo->prepare("INSERT INTO cs_brands (id, name, status) VALUES (?, ?, 'active')")->execute([$bId, trim($data['brand'])]);
                        $brandId = $bId;
                    }
                }
                $catId = null;
                if (!empty($data['category'])) {
                    $stmt = $pdo->prepare("SELECT id FROM cs_categories WHERE name = ?");
                    $stmt->execute([trim($data['category'])]);
                    $catId = $stmt->fetchColumn();
                    if (!$catId) {
                        $cId = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM cs_categories")->fetchColumn();
                        $pdo->prepare("INSERT INTO cs_categories (id, name, status) VALUES (?, ?, 'active')")->execute([$cId, trim($data['category'])]);
                        $catId = $cId;
                    }
                }
                $pdo->prepare("INSERT INTO cs_products (id, name, sku, description, brand_id, category_id, purchase_price, selling_price, mrp, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())")
                    ->execute([$nextId, trim($data['name']), trim($data['sku']), trim($data['description']??''), $brandId, $catId,
                               floatval($data['purchase_price']??0), floatval($data['selling_price']), floatval($data['mrp']??$data['selling_price'])]);
                if (!empty($data['initial_stock']) && floatval($data['initial_stock']) > 0) {
                    $sId = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM cs_inventory_movements")->fetchColumn();
                    $pdo->prepare("INSERT INTO cs_inventory_movements (id, product_id, movement_type, quantity, reference_type, reference_id, notes, created_at) VALUES (?, ?, 'in', ?, 'initial', ?, 'Excel import', NOW())")
                        ->execute([$sId, $nextId, floatval($data['initial_stock']), $nextId]);
                }
                $result['imported']++;
            } catch (Exception $e) {
                $result['errors'][] = "Row $rowNum: " . $e->getMessage();
            }
        }
        fclose($handle);
        $result['success'] = true;
        $result['message'] = "Imported {$result['imported']} products";
    } else {
        $result['message'] = 'XLSX requires PhpSpreadsheet. Convert to CSV or install library.';
    }
    return $result;
}

function handleBulkAction($pdo, $post) {
    $action = $post['bulk_action'];
    $ids = $post['selected_ids'];
    $ph = implode(',', array_fill(0, count($ids), '?'));
    switch ($action) {
        case 'activate': $pdo->prepare("UPDATE cs_products SET status='active' WHERE id IN ($ph)")->execute($ids); break;
        case 'deactivate': $pdo->prepare("UPDATE cs_products SET status='inactive' WHERE id IN ($ph)")->execute($ids); break;
        case 'delete': $pdo->prepare("DELETE FROM cs_products WHERE id IN ($ph)")->execute($ids); break;
    }
    header("Location: " . $_SERVER['REQUEST_URI']); exit;
}
?>
<link rel="stylesheet" href="/assets/css/themes.css?v=2">
<style>
.mp-layout{display:flex;min-height:calc(100vh - 41px);}
.mp-sidebar{width:210px;background:var(--bg-card);border-right:1px solid var(--border-color);padding:var(--space-sm);position:fixed;left:0;top:41px;bottom:0;overflow-y:auto;z-index:90;}
.mp-main{margin-left:210px;flex:1;padding:var(--space-md);min-height:calc(100vh - 41px);}
.mp-sidebar-link{display:flex;align-items:center;gap:var(--space-sm);padding:5px 10px;font-size:var(--font-sm);color:var(--text-secondary);text-decoration:none;border-radius:var(--border-radius-sm);transition:all var(--transition-fast);margin-bottom:1px;}
.mp-sidebar-link:hover,.mp-sidebar-link.active{background:var(--bg-hover);color:var(--text-primary);}
.mp-sidebar-link.active{background:rgba(99,102,241,0.1);color:var(--primary-light);border-left:2px solid var(--primary);}
.mp-sidebar-link .icon{font-size:var(--font-md);width:18px;text-align:center;}
.mp-sidebar-link .badge{margin-left:auto;background:var(--danger);color:white;font-size:9px;padding:0 4px;border-radius:6px;}
.mp-sidebar-section{margin-bottom:var(--space-sm);}
.mp-sidebar-title{font-size:var(--font-xs);font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.5px;padding:var(--space-sm) var(--space-md);}
.filters-bar{display:flex;gap:var(--space-sm);align-items:center;margin-bottom:var(--space-md);flex-wrap:wrap;padding:var(--space-sm);background:var(--bg-card);border-radius:var(--border-radius);border:1px solid var(--border-color);}
.filters-bar .form-input,.filters-bar .form-select{width:auto;min-width:140px;}
.import-zone{border:2px dashed var(--border-color);border-radius:var(--border-radius-lg);padding:var(--space-xl);text-align:center;margin-bottom:var(--space-md);transition:all var(--transition);cursor:pointer;}
.import-zone:hover,.import-zone.dragover{border-color:var(--primary);background:rgba(99,102,241,0.03);}
.import-zone .icon{font-size:var(--font-3xl);margin-bottom:var(--space-sm);color:var(--text-muted);}
.import-zone .title{font-size:var(--font-md);font-weight:600;color:var(--text-primary);margin-bottom:var(--space-xs);}
.import-zone .hint{font-size:var(--font-xs);color:var(--text-muted);}
.template-table{font-size:var(--font-xs);margin-bottom:var(--space-md);}
.template-table th{background:var(--bg-elevated);font-weight:600;}
.template-table td,.template-table th{padding:4px 8px;border:1px solid var(--border-color);}
.template-table .req{color:var(--danger);font-weight:600;}
.template-table .opt{color:var(--text-muted);}
.stock-bar{height:4px;background:var(--bg-hover);border-radius:2px;overflow:hidden;margin-top:2px;}
.stock-bar-fill{height:100%;border-radius:2px;transition:width 0.3s ease;}
.stock-bar-fill.good{background:var(--secondary);}
.stock-bar-fill.low{background:var(--warning);}
.stock-bar-fill.out{background:var(--danger);}
@media(max-width:768px){
  .mp-sidebar{transform:translateX(-100%);transition:transform var(--transition);width:240px;z-index:99;}
  .mp-sidebar.open{transform:translateX(0);}
  .mp-main{margin-left:0;}
  .filters-bar{flex-direction:column;align-items:stretch;}
  .filters-bar .form-input,.filters-bar .form-select{width:100%;}
}
</style>
<div class="mp-layout">
<aside class="mp-sidebar" id="mpSidebar">
<div class="mp-sidebar-section">
<div class="mp-sidebar-title">Marketplace</div>
<a href="/admin/marketplace.php?tab=overview" class="mp-sidebar-link <?= $tab==='overview'?'active':'' ?>"><span class="icon">📊</span> Overview</a>
<a href="/admin/marketplace.php?tab=products" class="mp-sidebar-link <?= $tab==='products'?'active':'' ?>"><span class="icon">📦</span> Products <span class="badge"><?= $stats['products'] ?></span></a>
<a href="/admin/marketplace.php?tab=brands" class="mp-sidebar-link <?= $tab==='brands'?'active':'' ?>"><span class="icon">🏷️</span> Brands</a>
<a href="/admin/marketplace.php?tab=categories" class="mp-sidebar-link <?= $tab==='categories'?'active':'' ?>"><span class="icon">📂</span> Categories</a>
<a href="/admin/marketplace.php?tab=orders" class="mp-sidebar-link <?= $tab==='orders'?'active':'' ?>"><span class="icon">🛒</span> Orders</a>
<a href="/admin/marketplace.php?tab=stock" class="mp-sidebar-link <?= $tab==='stock'?'active':'' ?>"><span class="icon">📊</span> Stock <?php if($stats['low_stock']+$stats['out_of_stock']>0): ?><span class="badge"><?= $stats['low_stock']+$stats['out_of_stock'] ?></span><?php endif; ?></a>
<a href="/admin/marketplace.php?tab=customers" class="mp-sidebar-link <?= $tab==='customers'?'active':'' ?>"><span class="icon">👥</span> Customers</a>
</div>
<div class="mp-sidebar-section">
<div class="mp-sidebar-title">Tools</div>
<a href="/admin/marketplace.php?action=import" class="mp-sidebar-link <?= $action==='import'?'active':'' ?>"><span class="icon">📥</span> Bulk Import</a>
<a href="/admin/marketplace.php?action=template" class="mp-sidebar-link <?= $action==='template'?'active':'' ?>"><span class="icon">📋</span> Excel Template</a>
<a href="/admin/dashboard.php" class="mp-sidebar-link"><span class="icon">←</span> Back to Dashboard</a>
</div>
</aside>

<main class="mp-main">
<div class="page-header">
<div>
<h1 class="page-title"><span class="icon">🏪</span> Marketplace Control Center</h1>
<p class="page-subtitle">Manage products, stock, orders & customers in one place</p>
</div>
<div style="display:flex;gap:8px;">
<a href="/admin/marketplace.php?action=import" class="btn btn-primary btn-sm">📥 Import Excel</a>
<a href="/admin/marketplace.php?action=add-product" class="btn btn-secondary btn-sm">➕ Add Product</a>
</div>
</div>

<?php if($importResult): ?>
<div class="alert alert-<?= $importResult['success']?'success':'danger' ?>" style="margin-bottom:var(--space-md);">
<span><?= $importResult['success']?'✓':'✕' ?></span> <?= htmlspecialchars($importResult['message']) ?>
<?php if($importResult['imported']>0): ?><br><small>Imported <?= $importResult['imported'] ?> products</small><?php endif; ?>
<?php if(!empty($importResult['errors'])): ?>
<details style="margin-top:8px;"><summary style="cursor:pointer;font-size:11px;">View errors (<?= count($importResult['errors']) ?>)</summary>
<ul style="font-size:11px;margin-top:4px;"><?php foreach(array_slice($importResult['errors'],0,10) as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
</details>
<?php endif; ?>
</div>
<?php endif; ?>

<?php if($tab==='overview' && $action!=='import' && $action!=='template' && $action!=='add-product'): ?>
<div class="grid grid-4" style="margin-bottom:var(--space-md);">
<div class="stat-card primary"><div class="stat-icon">📦</div><div class="stat-value" data-count="<?= $stats['products'] ?>" data-duration="1000">0</div><div class="stat-label">Products</div></div>
<div class="stat-card success"><div class="stat-icon">₹</div><div class="stat-value" data-count="<?= $stats['revenue_30d'] ?>" data-prefix="₹" data-duration="1500">0</div><div class="stat-label">30-Day Revenue</div></div>
<div class="stat-card warning"><div class="stat-icon">🛒</div><div class="stat-value" data-count="<?= $stats['orders'] ?>" data-duration="1000">0</div><div class="stat-label">Orders</div></div>
<div class="stat-card purple"><div class="stat-icon">👥</div><div class="stat-value" data-count="<?= $stats['customers'] ?>" data-duration="1000">0</div><div class="stat-label">Customers</div></div>
</div>

<div class="grid grid-2" style="margin-bottom:var(--space-md);">
<div class="card">
<div class="card-header"><div class="card-title"><span class="icon">📦</span> Recent Products</div><a href="/admin/marketplace.php?tab=products" class="btn btn-ghost btn-sm">View All →</a></div>
<div class="table-wrap">
<table class="table"><thead><tr><th>Name</th><th>SKU</th><th>Price</th><th>Stock</th></tr></thead>
<tbody>
<?php foreach(array_slice($products,0,5) as $p): ?>
<tr><td><?= htmlspecialchars($p['name']) ?></td><td><code style="font-size:10px;"><?= htmlspecialchars($p['sku']) ?></code></td><td>₹<?= number_format($p['selling_price']??0,2) ?></td><td><?php $st=intval($p['current_stock']??0);$rl=intval($p['reorder_level']??10);if($st<=0)echo'<span class="badge badge-danger">Out</span>';elseif($st<=$rl)echo'<span class="badge badge-warning">Low</span>';else echo'<span class="badge badge-success">'.$st.'</span>'; ?></td></tr>
<?php endforeach; ?>
<?php if(empty($products)): ?><tr><td colspan="4" class="empty-state" style="padding:20px;"><div class="icon">📦</div><div class="text">No products yet</div></td></tr><?php endif; ?>
</tbody></table>
</div></div>
<div class="card">
<div class="card-header"><div class="card-title"><span class="icon">🛒</span> Recent Orders</div><a href="/admin/marketplace.php?tab=orders" class="btn btn-ghost btn-sm">View All →</a></div>
<div class="table-wrap">
<table class="table"><thead><tr><th>Invoice</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
<tbody>
<?php foreach(array_slice($orders,0,5) as $o): ?>
<tr><td>#<?= $o['id'] ?></td><td><?= htmlspecialchars($o['customer_name']??'Guest') ?></td><td>₹<?= number_format($o['total_amount']??0,2) ?></td><td><?php $s=$o['status']??'pending';$bc=['paid'=>'badge-success','pending'=>'badge-warning','cancelled'=>'badge-danger'][$s]??'badge-muted';echo'<span class="badge '.$bc.'">'.ucfirst($s).'</span>'; ?></td></tr>
<?php endforeach; ?>
<?php if(empty($orders)): ?><tr><td colspan="4" class="empty-state" style="padding:20px;"><div class="icon">🛒</div><div class="text">No orders yet</div></td></tr><?php endif; ?>
</tbody></table>
</div></div>
</div>

<div class="card">
<div class="card-header"><div class="card-title"><span class="icon">📊</span> Stock Alerts</div><a href="/admin/marketplace.php?tab=stock" class="btn btn-ghost btn-sm">View All →</a></div>
<div class="table-wrap">
<table class="table"><thead><tr><th>Product</th><th>SKU</th><th>Current</th><th>Reorder</th><th>Status</th></tr></thead>
<tbody>
<?php $alerts=array_filter($stockData,function($s){return ($s['current_stock']??0)<=($s['reorder_level']??10);});foreach(array_slice($alerts,0,10) as $s): ?>
<tr><td><?= htmlspecialchars($s['name']) ?></td><td><code style="font-size:10px;"><?= htmlspecialchars($s['sku']) ?></code></td><td><?= intval($s['current_stock']) ?></td><td><?= intval($s['reorder_level']) ?></td><td><?php if(intval($s['current_stock'])<=0)echo'<span class="badge badge-danger">Out of Stock</span>';else echo'<span class="badge badge-warning">Low Stock</span>'; ?></td></tr>
<?php endforeach; ?>
<?php if(empty($alerts)): ?><tr><td colspan="5" class="empty-state" style="padding:20px;"><div class="icon">✅</div><div class="text">All stock levels healthy!</div></td></tr><?php endif; ?>
</tbody></table>
</div></div>
<?php endif; ?>

<?php if($tab==='products'): ?>
<div class="filters-bar">
<input type="text" class="form-input" placeholder="🔍 Search products..." id="productSearch" onkeyup="filterTable('productsTable',this.value)">
<select class="form-select" onchange="filterTableByColumn('productsTable',4,this.value)"><option value="">All Brands</option><?php foreach($brands as $b): ?><option value="<?= htmlspecialchars($b['name']) ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?></select>
<select class="form-select" onchange="filterTableByColumn('productsTable',5,this.value)"><option value="">All Categories</option><?php foreach($categories as $c): ?><option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
<select class="form-select" onchange="filterTableByColumn('productsTable',7,this.value)"><option value="">All Status</option><option value="active">Active</option><option value="inactive">Inactive</option></select>
<a href="/admin/marketplace.php?action=import" class="btn btn-primary btn-sm">📥 Import</a>
<a href="/admin/marketplace.php?action=add-product" class="btn btn-secondary btn-sm">➕ Add</a>
</div>
<form method="post" id="bulkForm">
<div style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
<select name="bulk_action" class="form-select" style="width:auto;min-width:140px;" onchange="if(this.value)document.getElementById('bulkForm').submit()">
<option value="">Bulk Actions</option><option value="activate">✅ Activate</option><option value="deactivate">⏸️ Deactivate</option><option value="delete">🗑️ Delete</option>
</select>
<span style="font-size:11px;color:var(--text-muted);">Select products below</span>
</div>
<div class="table-wrap">
<table class="table" id="productsTable">
<thead><tr><th style="width:30px;"><input type="checkbox" onclick="toggleAll(this,'product_check')"></th><th>ID</th><th>Product</th><th>SKU</th><th>Brand</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($products as $p): ?>
<tr>
<td><input type="checkbox" name="selected_ids[]" value="<?= $p['id'] ?>" class="product_check"></td>
<td><?= $p['id'] ?></td>
<td><div style="display:flex;align-items:center;gap:8px;"><?php if(!empty($p['image_url'])): ?><img src="<?= htmlspecialchars($p['image_url']) ?>" style="width:32px;height:32px;object-fit:cover;border-radius:4px;"><?php else: ?><div style="width:32px;height:32px;background:var(--bg-hover);border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:14px;">📦</div><?php endif; ?><span><?= htmlspecialchars($p['name']) ?></span></div></td>
<td><code style="font-size:10px;"><?= htmlspecialchars($p['sku']) ?></code></td>
<td><?= htmlspecialchars($p['brand_name']??'-') ?></td>
<td><?= htmlspecialchars($p['category_name']??'-') ?></td>
<td><div>₹<?= number_format($p['selling_price']??0,2) ?></div><?php if(!empty($p['mrp'])&&$p['mrp']>$p['selling_price']): ?><div style="font-size:10px;color:var(--text-muted);text-decoration:line-through;">₹<?= number_format($p['mrp'],2) ?></div><?php endif; ?></td>
<td><?php $st=intval($p['current_stock']??0);$rl=intval($p['reorder_level']??10);$mx=max($st,$rl*2,1);$pct=min(($st/$mx)*100,100);$bc=$st<=0?'out':($st<=$rl?'low':'good'); ?><div style="font-size:11px;font-weight:600;"><?= $st ?></div><div class="stock-bar"><div class="stock-bar-fill <?= $bc ?>" style="width:<?= $pct ?>%"></div></div></td>
<td><?php $s=$p['status']??'active';echo'<span class="badge '.($s==='active'?'badge-success':'badge-muted').'">'.ucfirst($s).'</span>'; ?></td>
<td><div style="display:flex;gap:4px;"><a href="/admin/marketplace.php?action=edit-product&id=<?= $p['id'] ?>" class="btn btn-ghost btn-icon" title="Edit">✏️</a><a href="/admin/marketplace.php?action=stock&id=<?= $p['id'] ?>" class="btn btn-ghost btn-icon" title="Stock">📊</a></div></td>
</tr>
<?php endforeach; ?>
<?php if(empty($products)): ?><tr><td colspan="10" class="empty-state" style="padding:40px;"><div class="icon" style="font-size:32px;">📦</div><div class="title">No products found</div><div class="text">Add products manually or import from Excel</div><div style="margin-top:12px;display:flex;gap:8px;justify-content:center;"><a href="/admin/marketplace.php?action=add-product" class="btn btn-primary btn-sm">➕ Add Product</a><a href="/admin/marketplace.php?action=import" class="btn btn-outline btn-sm">📥 Import Excel</a></div></td></tr><?php endif; ?>
</tbody>
</table>
</div>
</form>
<?php endif; ?>

<?php if($tab==='stock'): ?>
<div class="filters-bar">
<input type="text" class="form-input" placeholder="🔍 Search stock..." id="stockSearch" onkeyup="filterTable('stockTable',this.value)">
<select class="form-select" onchange="filterStock(this.value)"><option value="">All Stock</option><option value="low">⚠️ Low Stock</option><option value="out">❌ Out of Stock</option><option value="good">✅ Healthy</option></select>
<a href="/admin/marketplace.php?action=import" class="btn btn-primary btn-sm">📥 Import</a>
</div>
<div class="table-wrap">
<table class="table" id="stockTable">
<thead><tr><th>Product</th><th>SKU</th><th>Current</th><th>Reorder</th><th>Cost</th><th>Selling</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($stockData as $s): $st=intval($s['current_stock']??0);$rl=intval($s['reorder_level']??10);$status=$st<=0?'out':($st<=$rl?'low':'good'); ?>
<tr data-stock-status="<?= $status ?>">
<td><?= htmlspecialchars($s['name']) ?></td>
<td><code style="font-size:10px;"><?= htmlspecialchars($s['sku']) ?></code></td>
<td><span style="font-weight:600;color:<?= $status==='out'?'var(--danger)':($status==='low'?'var(--warning)':'var(--secondary)') ?>"><?= $st ?></span></td>
<td><?= $rl ?></td>
<td>₹<?= number_format($s['purchase_price']??0,2) ?></td>
<td>₹<?= number_format($s['selling_price']??0,2) ?></td>
<td><?php if($status==='out')echo'<span class="badge badge-danger">Out of Stock</span>';elseif($status==='low')echo'<span class="badge badge-warning">Low Stock</span>';else echo'<span class="badge badge-success">Healthy</span>'; ?></td>
<td><a href="/admin/marketplace.php?action=stock&id=<?= $s['id'] ?>" class="btn btn-ghost btn-sm">📊 Adjust</a></td>
</tr>
<?php endforeach; ?>
<?php if(empty($stockData)): ?><tr><td colspan="8" class="empty-state" style="padding:40px;"><div class="icon" style="font-size:32px;">📊</div><div class="title">No stock data</div><div class="text">Add products to see stock information</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php if($tab==='orders'): ?>
<div class="filters-bar">
<input type="text" class="form-input" placeholder="🔍 Search orders..." id="orderSearch" onkeyup="filterTable('ordersTable',this.value)">
<select class="form-select" onchange="filterTableByColumn('ordersTable',5,this.value)"><option value="">All Status</option><option value="paid">Paid</option><option value="pending">Pending</option><option value="cancelled">Cancelled</option></select>
</div>
<div class="table-wrap">
<table class="table" id="ordersTable">
<thead><tr><th>Invoice #</th><th>Date</th><th>Customer</th><th>Items</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($orders as $o): ?>
<tr><td><strong>#<?= $o['id'] ?></strong></td><td><?= date('d M Y',strtotime($o['created_at'])) ?></td><td><?= htmlspecialchars($o['customer_name']??'Guest') ?></td><td><?= $o['item_count']??0 ?></td><td>₹<?= number_format($o['total_amount']??0,2) ?></td><td><?php $s=$o['status']??'pending';$bc=['paid'=>'badge-success','pending'=>'badge-warning','cancelled'=>'badge-danger'][$s]??'badge-muted';echo'<span class="badge '.$bc.'">'.ucfirst($s).'</span>'; ?></td><td><a href="/admin/marketplace.php?action=view-invoice&id=<?= $o['id'] ?>" class="btn btn-ghost btn-sm">👁️ View</a></td></tr>
<?php endforeach; ?>
<?php if(empty($orders)): ?><tr><td colspan="7" class="empty-state" style="padding:40px;"><div class="icon" style="font-size:32px;">🛒</div><div class="title">No orders yet</div><div class="text">Orders will appear here when customers make purchases</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php if($tab==='brands'): ?>
<div class="filters-bar">
<input type="text" class="form-input" placeholder="🔍 Search brands..." onkeyup="filterTable('brandsTable',this.value)">
<a href="/admin/marketplace.php?action=add-brand" class="btn btn-primary btn-sm">➕ Add Brand</a>
</div>
<div class="table-wrap">
<table class="table" id="brandsTable">
<thead><tr><th>ID</th><th>Name</th><th>Status</th><th>Products</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($brands as $b): ?>
<tr><td><?= $b['id'] ?></td><td><?= htmlspecialchars($b['name']) ?></td><td><span class="badge badge-success"><?= ucfirst($b['status']??'active') ?></span></td><td><?php try{$stmt=$pdo->prepare("SELECT COUNT(*) FROM cs_products WHERE brand_id=?");$stmt->execute([$b['id']]);echo $stmt->fetchColumn();}catch(Exception $e){echo'-';} ?></td><td><a href="/admin/marketplace.php?action=edit-brand&id=<?= $b['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a></td></tr>
<?php endforeach; ?>
<?php if(empty($brands)): ?><tr><td colspan="5" class="empty-state" style="padding:40px;"><div class="icon" style="font-size:32px;">🏷️</div><div class="title">No brands yet</div><a href="/admin/marketplace.php?action=add-brand" class="btn btn-primary btn-sm" style="margin-top:8px;">➕ Add Brand</a></td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php if($tab==='categories'): ?>
<div class="filters-bar">
<input type="text" class="form-input" placeholder="🔍 Search categories..." onkeyup="filterTable('catsTable',this.value)">
<a href="/admin/marketplace.php?action=add-category" class="btn btn-primary btn-sm">➕ Add Category</a>
</div>
<div class="table-wrap">
<table class="table" id="catsTable">
<thead><tr><th>ID</th><th>Name</th><th>Parent</th><th>Status</th><th>Products</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($categories as $c): ?>
<tr><td><?= $c['id'] ?></td><td><?= htmlspecialchars($c['name']) ?></td><td><?= htmlspecialchars($c['parent_name']??'-') ?></td><td><span class="badge badge-success"><?= ucfirst($c['status']??'active') ?></span></td><td><?php try{$stmt=$pdo->prepare("SELECT COUNT(*) FROM cs_products WHERE category_id=?");$stmt->execute([$c['id']]);echo $stmt->fetchColumn();}catch(Exception $e){echo'-';} ?></td><td><a href="/admin/marketplace.php?action=edit-category&id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a></td></tr>
<?php endforeach; ?>
<?php if(empty($categories)): ?><tr><td colspan="6" class="empty-state" style="padding:40px;"><div class="icon" style="font-size:32px;">📂</div><div class="title">No categories yet</div><a href="/admin/marketplace.php?action=add-category" class="btn btn-primary btn-sm" style="margin-top:8px;">➕ Add Category</a></td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php if($tab==='customers'): ?>
<div class="filters-bar">
<input type="text" class="form-input" placeholder="🔍 Search customers..." onkeyup="filterTable('custTable',this.value)">
</div>
<div class="table-wrap">
<table class="table" id="custTable">
<thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Orders</th><th>Total Spent</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach($customers as $c): ?>
<tr><td><?= $c['id'] ?></td><td><?= htmlspecialchars($c['name']??'N/A') ?></td><td><?= htmlspecialchars($c['email']??'-') ?></td><td><?= htmlspecialchars($c['phone']??'-') ?></td><td><?php try{$stmt=$pdo->prepare("SELECT COUNT(*) FROM cs_invoices WHERE customer_id=?");$stmt->execute([$c['id']]);echo $stmt->fetchColumn();}catch(Exception $e){echo'-';} ?></td><td><?php try{$stmt=$pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM cs_invoices WHERE customer_id=? AND status='paid'");$stmt->execute([$c['id']]);echo '₹'.number_format($stmt->fetchColumn(),2);}catch(Exception $e){echo'-';} ?></td><td><a href="/admin/marketplace.php?action=view-customer&id=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">👁️ View</a></td></tr>
<?php endforeach; ?>
<?php if(empty($customers)): ?><tr><td colspan="7" class="empty-state" style="padding:40px;"><div class="icon" style="font-size:32px;">👥</div><div class="title">No customers yet</div></td></tr><?php endif; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<?php if($action==='import'): ?>
<div class="card" style="margin-bottom:var(--space-md);">
<div class="card-header"><div class="card-title"><span class="icon">📥</span> Bulk Import Products</div></div>
<div style="padding:var(--space-md);">
<p style="font-size:var(--font-sm);color:var(--text-secondary);margin-bottom:var(--space-md);">Upload a CSV or Excel file to bulk import products. Download the template for correct format.</p>
<form method="post" enctype="multipart/form-data" id="importForm">
<div class="import-zone" onclick="document.getElementById('excel_file').click()" id="dropZone">
<div class="icon">📁</div>
<div class="title">Drop Excel/CSV file here or click to browse</div>
<div class="hint">Supported: .csv, .xlsx, .xls (Max 5MB)</div>
<input type="file" name="excel_file" id="excel_file" accept=".csv,.xlsx,.xls" style="display:none;" onchange="handleFileSelect(this)">
</div>
<div id="fileInfo" style="display:none;margin-bottom:var(--space-md);padding:var(--space-sm);background:var(--bg-hover);border-radius:var(--border-radius);font-size:var(--font-sm);"></div>
<div style="display:flex;gap:8px;justify-content:center;">
<button type="submit" class="btn btn-primary" id="importBtn" disabled><span class="loading" style="display:none;width:12px;height:12px;border-width:1.5px;margin-right:6px;"></span>📥 Import Products</button>
<a href="/admin/marketplace.php?action=template" class="btn btn-outline">📋 Download Template</a>
</div>
</form>
</div>
</div>
<div class="card">
<div class="card-header"><div class="card-title"><span class="icon">📋</span> Import Format Guide</div></div>
<div style="padding:var(--space-md);overflow-x:auto;">
<table class="template-table">
<thead><tr><th>Column</th><th>Required</th><th>Example</th><th>Description</th></tr></thead>
<tbody>
<tr><td>name</td><td class="req">Required</td><td>Dell Inspiron 15</td><td>Product name</td></tr>
<tr><td>sku</td><td class="req">Required</td><td>DELL-INS-15-001</td><td>Unique stock keeping unit</td></tr>
<tr><td>selling_price</td><td class="req">Required</td><td>45000</td><td>Selling price in INR</td></tr>
<tr><td>description</td><td class="opt">Optional</td><td>15.6" FHD Laptop...</td><td>Product description</td></tr>
<tr><td>brand</td><td class="opt">Optional</td><td>Dell</td><td>Brand name (auto-creates if new)</td></tr>
<tr><td>category</td><td class="opt">Optional</td><td>Laptops</td><td>Category name (auto-creates if new)</td></tr>
<tr><td>purchase_price</td><td class="opt">Optional</td><td>38000</td><td>Cost price for profit calculation</td></tr>
<tr><td>mrp</td><td class="opt">Optional</td><td>50000</td><td>Maximum retail price</td></tr>
<tr><td>initial_stock</td><td class="opt">Optional</td><td>10</td><td>Opening stock quantity</td></tr>
</tbody>
</table>
<p style="font-size:var(--font-xs);color:var(--text-muted);margin-top:var(--space-sm);">💡 <strong>Tip:</strong> Brands and categories will be auto-created if they don't exist.</p>
</div>
</div>
<?php endif; ?>

<?php if($action==='template'): ?>
<div class="card">
<div class="card-header">
<div class="card-title"><span class="icon">📋</span> Excel Import Template</div>
<a href="data:text/csv;charset=utf-8,name,sku,description,brand,category,purchase_price,selling_price,mrp,initial_stock%0ADell%20Inspiron%2015,DELL-INS-15-001,15.6%22%20FHD%20Laptop%20with%20Intel%20i5,Dell,Laptops,38000,45000,50000,10%0AHP%20Pavilion%2014,HP-PAV-14-001,14%22%20FHD%20Laptop%20with%20AMD%20Ryzen%205,HP,Laptops,42000,48000,55000,8%0ALenovo%20ThinkPad%20E14,LEN-TP-E14-001,14%22%20Business%20Laptop%20with%20Intel%20i7,Lenovo,Laptops,52000,58000,65000,5%0ALogitech%20MX%20Master%203,LOG-MXM3-001,Wireless%20Mouse%20with%20MagSpeed,Logitech,Accessories,6500,8500,9999,20%0ASamsung%2024%22%20Monitor,SAM-MON-24-001,24%22%20FHD%20IPS%20Monitor,Samsung,Monitors,8500,11000,13000,12" download="akkuapps_product_template.csv" class="btn btn-primary btn-sm">⬇️ Download CSV Template</a>
</div>
<div style="padding:var(--space-md);">
<p style="font-size:var(--font-sm);color:var(--text-secondary);margin-bottom:var(--space-md);">Copy the data below into Excel or download the CSV file. Fill your product details and upload via Import page.</p>
<div class="table-wrap">
<table class="table template-table">
<thead><tr style="background:var(--primary);color:white;"><th>name</th><th>sku</th><th>description</th><th>brand</th><th>category</th><th>purchase_price</th><th>selling_price</th><th>mrp</th><th>initial_stock</th></tr></thead>
<tbody>
<tr><td>Dell Inspiron 15</td><td>DELL-INS-15-001</td><td>15.6" FHD Laptop with Intel i5</td><td>Dell</td><td>Laptops</td><td>38000</td><td>45000</td><td>50000</td><td>10</td></tr>
<tr><td>HP Pavilion 14</td><td>HP-PAV-14-001</td><td>14" FHD Laptop with AMD Ryzen 5</td><td>HP</td><td>Laptops</td><td>42000</td><td>48000</td><td>55000</td><td>8</td></tr>
<tr><td>Lenovo ThinkPad E14</td><td>LEN-TP-E14-001</td><td>14" Business Laptop with Intel i7</td><td>Lenovo</td><td>Laptops</td><td>52000</td><td>58000</td><td>65000</td><td>5</td></tr>
<tr><td>Logitech MX Master 3</td><td>LOG-MXM3-001</td><td>Wireless Mouse with MagSpeed</td><td>Logitech</td><td>Accessories</td><td>6500</td><td>8500</td><td>9999</td><td>20</td></tr>
<tr><td>Samsung 24" Monitor</td><td>SAM-MON-24-001</td><td>24" FHD IPS Monitor</td><td>Samsung</td><td>Monitors</td><td>8500</td><td>11000</td><td>13000</td><td>12</td></tr>
</tbody>
</table>
</div>
<div style="background:var(--bg-hover);padding:var(--space-md);border-radius:var(--border-radius);margin-top:var(--space-md);">
<h4 style="font-size:var(--font-sm);font-weight:600;margin-bottom:var(--space-sm);">📖 How to Import:</h4>
<ol style="font-size:var(--font-sm);color:var(--text-secondary);padding-left:var(--space-lg);line-height:1.8;">
<li>Download the template CSV file above</li><li>Open in Excel / Google Sheets / LibreOffice</li>
<li>Fill in your product data (keep header row exactly as-is)</li>
<li>Save as <strong>CSV (Comma delimited) (*.csv)</strong></li>
<li>Go to <strong>Marketplace → Bulk Import</strong></li>
<li>Upload your CSV file and click Import</li>
<li>Brands & Categories will be auto-created if they don't exist!</li>
</ol>
</div>
</div>
</div>
<?php endif; ?>

<?php if($action==='add-product'): ?>
<div class="card" style="max-width:600px;">
<div class="card-header"><div class="card-title"><span class="icon">➕</span> Add New Product</div></div>
<div style="padding:var(--space-md);">
<form method="post" action="/admin/marketplace.php?action=save-product">
<div class="form-group"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-input" required placeholder="e.g. Dell Inspiron 15"></div>
<div class="form-group"><label class="form-label">SKU *</label><input type="text" name="sku" class="form-input" required placeholder="e.g. DELL-INS-15-001"></div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-sm);">
<div class="form-group"><label class="form-label">Brand</label><select name="brand_id" class="form-select"><option value="">Select Brand</option><?php foreach($brands as $b): ?><option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option><?php endforeach; ?></select></div>
<div class="form-group"><label class="form-label">Category</label><select name="category_id" class="form-select"><option value="">Select Category</option><?php foreach($categories as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:var(--space-sm);">
<div class="form-group"><label class="form-label">Purchase Price</label><input type="number" name="purchase_price" class="form-input" step="0.01" placeholder="0.00"></div>
<div class="form-group"><label class="form-label">Selling Price *</label><input type="number" name="selling_price" class="form-input" step="0.01" required placeholder="0.00"></div>
<div class="form-group"><label class="form-label">MRP</label><input type="number" name="mrp" class="form-input" step="0.01" placeholder="0.00"></div>
</div>
<div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-textarea" rows="3" placeholder="Product description..."></textarea></div>
<div class="form-group"><label class="form-label">Image URL</label><input type="url" name="image_url" class="form-input" placeholder="https://..."></div>
<div style="display:flex;gap:var(--space-sm);justify-content:flex-end;"><a href="/admin/marketplace.php?tab=products" class="btn btn-ghost">Cancel</a><button type="submit" class="btn btn-primary">💾 Save Product</button></div>
</form>
</div>
</div>
<?php endif; ?>

</main>
</div>

<script>
function filterTable(tableId,query){
  const table=document.getElementById(tableId);if(!table)return;
  const rows=table.querySelectorAll('tbody tr');const q=query.toLowerCase();
  rows.forEach(row=>{row.style.display=row.textContent.toLowerCase().includes(q)?'':'none';});
}
function filterTableByColumn(tableId,colIndex,value){
  const table=document.getElementById(tableId);if(!table)return;
  const rows=table.querySelectorAll('tbody tr');
  rows.forEach(row=>{const cell=row.cells[colIndex];if(!cell)return;row.style.display=(!value||cell.textContent.trim().toLowerCase().includes(value.toLowerCase()))?'':'none';});
}
function filterStock(status){
  const table=document.getElementById('stockTable');if(!table)return;
  const rows=table.querySelectorAll('tbody tr[data-stock-status]');
  rows.forEach(row=>{row.style.display=(!status||row.dataset.stockStatus===status)?'':'none';});
}
function toggleAll(checkbox,className){
  document.querySelectorAll('.'+className).forEach(cb=>cb.checked=checkbox.checked);
}
function handleFileSelect(input){
  const file=input.files[0];const info=document.getElementById('fileInfo');const btn=document.getElementById('importBtn');
  if(file){info.style.display='block';info.innerHTML='<strong>📄 '+file.name+'</strong> <span style="color:var(--text-muted)">('+(file.size/1024).toFixed(1)+' KB)</span>';btn.disabled=false;}
}
document.addEventListener('DOMContentLoaded',function(){
  const dropZone=document.getElementById('dropZone');const fileInput=document.getElementById('excel_file');
  if(!dropZone||!fileInput)return;
  ['dragenter','dragover','dragleave','drop'].forEach(e=>dropZone.addEventListener(e,function(ev){ev.preventDefault();ev.stopPropagation();},false));
  ['dragenter','dragover'].forEach(e=>dropZone.addEventListener(e,function(){dropZone.classList.add('dragover');},false));
  ['dragleave','drop'].forEach(e=>dropZone.addEventListener(e,function(){dropZone.classList.remove('dragover');},false));
  dropZone.addEventListener('drop',function(e){fileInput.files=e.dataTransfer.files;handleFileSelect(fileInput);});
  const form=document.getElementById('importForm');
  if(form){form.addEventListener('submit',function(){const btn=document.getElementById('importBtn');const loader=btn.querySelector('.loading');if(loader)loader.style.display='inline-block';btn.disabled=true;});}
});
</script>
<script src="/assets/js/animations.js?v=2"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>