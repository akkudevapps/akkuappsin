<?php
/**
 * Admin Ad Provider Management Page
 * Manage advertising providers: approve, suspend, view wallet, transactions
 * URL: /admin/ad-providers.php
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
        
        if ($formAction === 'approve_provider') {
            $providerId = trim($_POST['provider_id'] ?? '');
            $notes = trim($_POST['approval_notes'] ?? '');
            
            if (empty($providerId)) throw new Exception('Provider ID required');
            
            $sql = "
                UPDATE ad_providers
                SET status = 'approved', approved_by = ?, approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user['id'] ?? $user['user_id'], $notes, $providerId]);
            
            $message = 'Provider approved successfully!';
        } elseif ($formAction === 'reject_provider') {
            $providerId = trim($_POST['provider_id'] ?? '');
            $notes = trim($_POST['approval_notes'] ?? '');
            
            if (empty($providerId)) throw new Exception('Provider ID required');
            
            $sql = "
                UPDATE ad_providers
                SET status = 'rejected', approved_by = ?, approved_at = NOW(),
                    approval_notes = ?
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user['id'] ?? $user['user_id'], $notes, $providerId]);
            
            $message = 'Provider rejected!';
        } elseif ($formAction === 'suspend_provider') {
            $providerId = trim($_POST['provider_id'] ?? '');
            $reason = trim($_POST['suspension_reason'] ?? '');
            
            if (empty($providerId)) throw new Exception('Provider ID required');
            
            $sql = "
                UPDATE ad_providers
                SET status = 'suspended', suspension_reason = ?, suspended_at = NOW(),
                    suspended_by = ?
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reason, $user['id'] ?? $user['user_id'], $providerId]);
            
            $message = 'Provider suspended!';
        } elseif ($formAction === 'reactivate_provider') {
            $providerId = trim($_POST['provider_id'] ?? '');
            
            if (empty($providerId)) throw new Exception('Provider ID required');
            
            $sql = "
                UPDATE ad_providers
                SET status = 'approved', suspension_reason = NULL, suspended_at = NULL,
                    suspended_by = NULL
                WHERE id = ?
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$providerId]);
            
            $message = 'Provider reactivated!';
        } elseif ($formAction === 'add_wallet_funds') {
            $providerId = trim($_POST['provider_id'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? 'Admin credit');
            
            if (empty($providerId) || $amount <= 0) throw new Exception('Provider ID and valid amount required');
            
            // Add wallet transaction
            $sql = "
                INSERT INTO ad_wallet_transactions (
                    id, provider_id, transaction_type, amount, description, 
                    reference_type, reference_id, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                generateUUID(),
                $providerId,
                'credit',
                $amount,
                $reason,
                'admin_credit',
                $user['id'] ?? $user['user_id']
            ]);
            
            // Update wallet balance
            $sql = "UPDATE ad_providers SET wallet_balance = wallet_balance + ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$amount, $providerId]);
            
            $message = 'Wallet credited successfully!';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get filters
$filterStatus = trim($_GET['status'] ?? 'all');
$searchQuery = trim($_GET['search'] ?? '');

// Prepare query
$whereConditions = [];
$params = [];

if ($filterStatus !== 'all') {
    $whereConditions[] = "p.status = ?";
    $params[] = $filterStatus;
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(p.company_name LIKE ? OR p.contact_email LIKE ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get providers
$sql = "
    SELECT p.*, 
           COUNT(a.id) as total_ads,
           SUM(CASE WHEN a.status = 'active' THEN 1 ELSE 0 END) as active_ads,
           SUM(CASE WHEN aa.impressions IS NOT NULL THEN aa.impressions ELSE 0 END) as total_impressions,
           SUM(CASE WHEN aa.clicks IS NOT NULL THEN aa.clicks ELSE 0 END) as total_clicks
    FROM ad_providers p
    LEFT JOIN advertisements a ON p.id = a.provider_id
    LEFT JOIN ad_analytics aa ON a.id = aa.ad_id
    {$whereClause}
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 100
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$providers = $stmt->fetchAll();

// Get provider statuses for stats
$statsQuery = "
    SELECT status, COUNT(*) as count
    FROM ad_providers
    GROUP BY status
";
$stmt = $pdo->prepare($statsQuery);
$stmt->execute();
$stats = [];
foreach ($stmt->fetchAll() as $row) {
    $stats[$row['status']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ad Provider Management - AkkuApps Admin</title>
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
        
        .providers-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .providers-table thead { background: var(--bg-hover); border: 1px solid var(--border-color); }
        .providers-table th { padding: 1rem; text-align: left; font-weight: 600; color: var(--text-primary); }
        .providers-table td { padding: 0.875rem 1rem; border-bottom: 1px solid var(--border-color); }
        .providers-table tbody tr:hover { background: var(--bg-hover); }
        
        .status-badge { display: inline-block; padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-suspended { background: #e2e3e5; color: #383d41; }
        
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
        
        .provider-details { background: var(--bg-hover); border-radius: 8px; padding: 1rem; margin-bottom: 1rem; }
        .detail-row { display: grid; grid-template-columns: 150px 1fr; margin-bottom: 0.75rem; gap: 1rem; }
        .detail-label { font-weight: 600; color: var(--text-muted); }
        .detail-value { color: var(--text-primary); }
        
        @media (max-width: 768px) {
            .page-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .filter-row { grid-template-columns: 1fr; }
            .providers-table { font-size: 0.9rem; }
            .providers-table th, .providers-table td { padding: 0.5rem; }
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
                <h1><i class="fas fa-building"></i> Ad Provider Management</h1>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Manage provider accounts, approvals, and wallets</p>
            </div>
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
                <div class="stat-value"><?= $stats['pending'] ?? 0 ?></div>
                <div class="stat-label">Pending Approval</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['approved'] ?? 0 ?></div>
                <div class="stat-label">Active Providers</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $stats['suspended'] ?? 0 ?></div>
                <div class="stat-label">Suspended</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($providers) ?></div>
                <div class="stat-label">Total Providers</div>
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
                            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            <option value="suspended" <?= $filterStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Search Company</label>
                        <input type="text" name="search" placeholder="Search by company name or email..." value="<?= htmlspecialchars($searchQuery) ?>" onkeyup="if(event.key==='Enter') this.form.submit()">
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Providers Table -->
        <?php if (!empty($providers)): ?>
            <div style="overflow-x: auto;">
                <table class="providers-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Wallet</th>
                            <th>Ads / Impressions</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($providers as $provider): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars(substr($provider['company_name'], 0, 30)) ?></strong>
                                    <br>
                                    <small style="color: var(--text-muted);">ID: <?= substr($provider['id'], 0, 8) ?>...</small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($provider['contact_person'] ?? 'N/A') ?>
                                    <br>
                                    <small style="color: var(--text-muted);"><?= htmlspecialchars($provider['contact_email']) ?></small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($provider['status']) ?>">
                                        <?= ucfirst($provider['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= akkuAdFormatCurrency($provider['wallet_balance'], $provider['currency_code'] ?? 'USD') ?></strong>
                                    <br>
                                    <small style="color: var(--text-muted);">Total Spent: <?= akkuAdFormatCurrency($provider['total_spent'] ?? 0, $provider['currency_code'] ?? 'USD') ?></small>
                                </td>
                                <td>
                                    <strong><?= $provider['total_ads'] ?? 0 ?></strong> ads
                                    <br>
                                    <small style="color: var(--text-muted);">
                                        <?= number_format($provider['total_impressions'] ?? 0) ?> impressions
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-secondary" onclick="viewProvider('<?= $provider['id'] ?>')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        
                                        <?php if ($provider['status'] === 'pending'): ?>
                                            <button class="btn btn-success" onclick="approveProvider('<?= $provider['id'] ?>')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="btn btn-danger" onclick="rejectProvider('<?= $provider['id'] ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php elseif ($provider['status'] === 'approved'): ?>
                                            <button class="btn btn-warning" onclick="suspendProvider('<?= $provider['id'] ?>')">
                                                <i class="fas fa-ban"></i> Suspend
                                            </button>
                                            <button class="btn btn-primary" onclick="addWalletFunds('<?= $provider['id'] ?>')">
                                                <i class="fas fa-wallet"></i> Add Funds
                                            </button>
                                        <?php elseif ($provider['status'] === 'suspended'): ?>
                                            <button class="btn btn-success" onclick="reactivateProvider('<?= $provider['id'] ?>')">
                                                <i class="fas fa-check"></i> Reactivate
                                            </button>
                                        <?php endif; ?>
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
                <div class="empty-state-text">No providers found</div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Approval Modal -->
    <div class="modal-overlay" id="approvalModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Approval Action</h2>
                <button class="modal-close" onclick="closeApprovalModal()">&times;</button>
            </div>
            
            <form method="POST" action="" id="approvalForm">
                <input type="hidden" name="provider_id" id="approvalProviderId">
                <input type="hidden" name="form_action" id="approvalFormAction" value="approve_provider">
                
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
    
    <!-- Suspension Modal -->
    <div class="modal-overlay" id="suspensionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Suspend Provider</h2>
                <button class="modal-close" onclick="closeSuspensionModal()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="form_action" value="suspend_provider">
                <input type="hidden" name="provider_id" id="suspensionProviderId">
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label>Suspension Reason *</label>
                    <textarea name="suspension_reason" rows="4" placeholder="Why is this provider being suspended?" style="padding: 0.65rem; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-input); color: var(--text-primary);" required></textarea>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-danger" style="flex: 1;">
                        <i class="fas fa-ban"></i> Suspend
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeSuspensionModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Wallet Funds Modal -->
    <div class="modal-overlay" id="walletModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add Wallet Funds</h2>
                <button class="modal-close" onclick="closeWalletModal()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="form_action" value="add_wallet_funds">
                <input type="hidden" name="provider_id" id="walletProviderId">
                
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label>Amount *</label>
                    <input type="number" name="amount" step="0.01" min="0.01" placeholder="0.00" required>
                </div>
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label>Reason</label>
                    <input type="text" name="reason" placeholder="Admin credit, refund, etc." value="Admin credit">
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-plus"></i> Add Funds
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeWalletModal()" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Provider Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Provider Details</h2>
                <button class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            
            <div id="viewContent" style="max-height: 60vh; overflow-y: auto;">
                <!-- Populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.remove('active');
        }
        
        function closeSuspensionModal() {
            document.getElementById('suspensionModal').classList.remove('active');
        }
        
        function closeWalletModal() {
            document.getElementById('walletModal').classList.remove('active');
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
        }
        
        function approveProvider(providerId) {
            document.getElementById('approvalProviderId').value = providerId;
            document.getElementById('approvalFormAction').value = 'approve_provider';
            document.querySelector('#approvalModal .modal-header h2').textContent = 'Approve Provider';
            document.getElementById('approvalModal').classList.add('active');
        }
        
        function rejectProvider(providerId) {
            document.getElementById('approvalProviderId').value = providerId;
            document.getElementById('approvalFormAction').value = 'reject_provider';
            document.querySelector('#approvalModal .modal-header h2').textContent = 'Reject Provider';
            document.getElementById('approvalModal').classList.add('active');
        }
        
        function suspendProvider(providerId) {
            document.getElementById('suspensionProviderId').value = providerId;
            document.getElementById('suspensionModal').classList.add('active');
        }
        
        function reactivateProvider(providerId) {
            if (confirm('Reactivate this provider?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `<input type="hidden" name="form_action" value="reactivate_provider"><input type="hidden" name="provider_id" value="${providerId}">`;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function addWalletFunds(providerId) {
            document.getElementById('walletProviderId').value = providerId;
            document.getElementById('walletModal').classList.add('active');
        }
        
        function viewProvider(providerId) {
            alert('View modal for provider: ' + providerId + ' (To be implemented)');
        }
        
        // Close modals on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeApprovalModal();
                closeSuspensionModal();
                closeWalletModal();
                closeViewModal();
            }
        });
    </script>
</body>
</html>
