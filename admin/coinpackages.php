<?php
// admin/coinpackages.php - Updated to enforce 1 coin = 1 INR rule
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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    global $pdo;
    
    if (isset($_POST['add_package'])) {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priceInr = floatval($_POST['price_inr'] ?? 0);
        $coinAmount = intval($_POST['coin_amount'] ?? 0);
        $bonusCoins = intval($_POST['bonus_coins'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // ENFORCE 1 coin = 1 INR rule
        if ($coinAmount != $priceInr) {
            $error = "Coin amount must equal price in INR (1 coin = ₹1 rule)";
        } elseif (empty($name)) {
            $error = "Package name is required";
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO coin_packages 
                    (name, description, price_inr, coin_amount, bonus_coins, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $description, $priceInr, $coinAmount, $bonusCoins, $isActive]);
                $message = "Package added successfully";
            } catch (Exception $e) {
                $error = "Error adding package: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_package'])) {
        $packageId = intval($_POST['package_id']);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $priceInr = floatval($_POST['price_inr'] ?? 0);
        $coinAmount = intval($_POST['coin_amount'] ?? 0);
        $bonusCoins = intval($_POST['bonus_coins'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        // ENFORCE 1 coin = 1 INR rule
        if ($coinAmount != $priceInr) {
            $error = "Coin amount must equal price in INR (1 coin = ₹1 rule)";
        } elseif (empty($name)) {
            $error = "Package name is required";
        } else {
            try {
                $stmt = $pdo->prepare("
                    UPDATE coin_packages 
                    SET name = ?, description = ?, price_inr = ?, coin_amount = ?, bonus_coins = ?, is_active = ?, updated_at = NOW()
                    WHERE package_id = ?
                ");
                $stmt->execute([$name, $description, $priceInr, $coinAmount, $bonusCoins, $isActive, $packageId]);
                $message = "Package updated successfully";
            } catch (Exception $e) {
                $error = "Error updating package: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_package'])) {
        $packageId = intval($_POST['package_id']);
        try {
            $stmt = $pdo->prepare("DELETE FROM coin_packages WHERE package_id = ?");
            $stmt->execute([$packageId]);
            $message = "Package deleted successfully";
        } catch (Exception $e) {
            $error = "Error deleting package: " . $e->getMessage();
        }
    }
}

// Get all packages
try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM coin_packages ORDER BY price_inr ASC");
    $stmt->execute();
    $packages = $stmt->fetchAll();
} catch (Exception $e) {
    $packages = [];
    $error = "Error loading packages: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coin Packages - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
    <?php include '../components/admin-header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/admin-sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Coin Packages</h1>
                <p>Manage coin packages for user purchases (1 coin = ₹1)</p>
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

            <!-- Add Package Form -->
            <div class="chart-container animate-slideUp">
                <h2>Add New Package</h2>
                <form method="POST" style="margin-top: 20px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px;">
                    <input type="hidden" name="add_package" value="1">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Package Name</label>
                            <input type="text" name="name" required 
                                   style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Price (₹) - Must equal coin amount</label>
                            <input type="number" name="price_inr" step="1" min="1" required 
                                   style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Coin Amount - Must equal price</label>
                            <input type="number" name="coin_amount" step="1" min="1" required 
                                   style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Bonus Coins</label>
                            <input type="number" name="bonus_coins" min="0" value="0" 
                                   style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Description</label>
                        <textarea name="description" rows="3" 
                                  style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);"></textarea>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; color: var(--text-primary);">
                            <input type="checkbox" name="is_active" value="1" checked style="margin-right: 10px;">
                            Active Package
                        </label>
                    </div>
                    
                    <button type="submit" 
                            style="background: var(--accent-color); color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer;">
                        <i class="fas fa-plus"></i> Add Package
                    </button>
                </form>
            </div>

            <!-- Existing Packages -->
            <div class="chart-container animate-slideUp">
                <h2>Existing Packages</h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($packages)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                            No packages found.
                        </p>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
                                <thead>
                                    <tr style="background: var(--secondary-bg);">
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Name</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Price</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Coins</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Bonus</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Status</th>
                                        <th style="padding: 15px; text-align: left; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($packages as $package): ?>
                                        <tr style="border-bottom: 1px solid var(--border-color);">
                                            <td style="padding: 15px; color: var(--text-primary);">
                                                <strong><?= htmlspecialchars($package['name']) ?></strong>
                                                <?php if (!empty($package['description'])): ?>
                                                    <div style="color: var(--text-secondary); font-size: 0.9em; margin-top: 5px;">
                                                        <?= htmlspecialchars($package['description']) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 15px; color: var(--text-primary);">₹<?= number_format($package['price_inr'], 2) ?></td>
                                            <td style="padding: 15px; color: var(--text-primary);"><?= number_format($package['coin_amount']) ?></td>
                                            <td style="padding: 15px; color: var(--text-primary);"><?= number_format($package['bonus_coins']) ?></td>
                                            <td style="padding: 15px; color: var(--text-primary);">
                                                <span style="background: <?= $package['is_active'] ? '#10b981' : '#ef4444' ?>; padding: 5px 10px; border-radius: 20px; font-size: 0.8em;">
                                                    <?= $package['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td style="padding: 15px;">
                                                <button onclick="editPackage(<?= $package['package_id'] ?>, '<?= htmlspecialchars($package['name']) ?>', <?= $package['price_inr'] ?>, <?= $package['coin_amount'] ?>, <?= $package['bonus_coins'] ?>, <?= $package['is_active'] ?>, '<?= htmlspecialchars($package['description']) ?>')" 
                                                        style="background: #f59e0b; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; margin: 2px;">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="package_id" value="<?= $package['package_id'] ?>">
                                                    <button type="submit" name="delete_package" 
                                                            style="background: #ef4444; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer; margin: 2px;"
                                                            onclick="return confirm('Are you sure you want to delete this package?')">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Package Modal -->
    <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; width: 90%; max-width: 500px; border: 1px solid var(--border-color);">
            <h2 style="margin-top: 0; color: var(--text-primary);">Edit Package</h2>
            <form method="POST" id="editForm">
                <input type="hidden" name="update_package" value="1">
                <input type="hidden" name="package_id" id="editPackageId">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Package Name</label>
                    <input type="text" name="name" id="editName" required 
                           style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Price (₹) - Must equal coin amount</label>
                        <input type="number" name="price_inr" id="editPrice" step="1" min="1" required 
                               style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Coin Amount - Must equal price</label>
                        <input type="number" name="coin_amount" id="editCoinAmount" step="1" min="1" required 
                               style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                    </div>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Bonus Coins</label>
                    <input type="number" name="bonus_coins" id="editBonusCoins" min="0" 
                           style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Description</label>
                    <textarea name="description" id="editDescription" rows="3" 
                              style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);"></textarea>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; color: var(--text-primary);">
                        <input type="checkbox" name="is_active" id="editIsActive" value="1" style="margin-right: 10px;">
                        Active Package
                    </label>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" 
                            style="background: var(--accent-color); color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; flex: 1;">
                        Update Package
                    </button>
                    <button type="button" onclick="closeModal()" 
                            style="background: #6b7280; color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
    <script>
        function editPackage(id, name, price, coinAmount, bonusCoins, isActive, description) {
            document.getElementById('editPackageId').value = id;
            document.getElementById('editName').value = name;
            document.getElementById('editPrice').value = price;
            document.getElementById('editCoinAmount').value = coinAmount;
            document.getElementById('editBonusCoins').value = bonusCoins;
            document.getElementById('editDescription').value = description;
            document.getElementById('editIsActive').checked = isActive == 1;
            
            document.getElementById('editModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }
        
        // Enforce 1 coin = 1 INR rule in edit form
        document.addEventListener('DOMContentLoaded', function() {
            const editPrice = document.getElementById('editPrice');
            const editCoinAmount = document.getElementById('editCoinAmount');
            
            if (editPrice && editCoinAmount) {
                editPrice.addEventListener('input', function() {
                    editCoinAmount.value = this.value;
                });
                
                editCoinAmount.addEventListener('input', function() {
                    editPrice.value = this.value;
                });
            }
        });
    </script>
</body>
</html>
