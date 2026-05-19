<?php
// user/wallet.php - Fixed version
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/economy.php';

$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$message = '';
$error = '';
$success = false;

// Get coin packages
try {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM coin_packages WHERE is_active = 1 ORDER BY price_inr ASC");
    $stmt->execute();
    $packages = $stmt->fetchAll();
} catch (Exception $e) {
    $packages = [];
    error_log("Wallet page error: " . $e->getMessage());
}

// Handle payment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy_coins'])) {
    $packageId = (int)$_POST['package_id'];
    
    // Validate package
    $stmt = $pdo->prepare("SELECT * FROM coin_packages WHERE package_id = ? AND is_active = 1");
    $stmt->execute([$packageId]);
    $package = $stmt->fetch();
    
    if (!$package) {
        $error = "Invalid package selected";
    } else {
        // Create payment request
        $result = createPaymentRequest($user['user_id'], $packageId);
        if ($result['success']) {
            $_SESSION['payment_id'] = $result['payment_id'];
            $_SESSION['upi_url'] = $result['upi_url'];
            $_SESSION['amount_inr'] = $result['amount_inr'];
            $_SESSION['coins_to_add'] = $result['total_coins'];
            
            // Redirect to payment page
            header("Location: /user/payment.php");
            exit;
        } else {
            $error = $result['error'] ?? 'Unknown error';
        }
    }
}

// Get recent transactions
try {
    $stmt = $pdo->prepare("
        SELECT * FROM coin_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user['user_id']]);
    $transactions = $stmt->fetchAll();
} catch (Exception $e) {
    $transactions = [];
}

// Get pending payments
try {
    $stmt = $pdo->prepare("
        SELECT * FROM upi_payments 
        WHERE user_id = ? AND status = 'pending' 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user['user_id']]);
    $pendingPayments = $stmt->fetchAll();
} catch (Exception $e) {
    $pendingPayments = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wallet - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Wallet</h1>
                <p>Manage your coins and transactions</p>
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

            <!-- Coin Balance -->
            <div class="stats-grid animate-slideUp">
                <div class="stat-card">
                    <div class="stat-icon bg-gold">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= number_format($user['coin_balance'], 2) ?></h3>
                        <p>Your Coin Balance</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?= count($transactions) ?></h3>
                        <p>Recent Transactions</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon bg-purple">
                        <i class="fas fa-rupee-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>₹<?= number_format($user['coin_balance'], 2) ?></h3>
                        <p>Approx. Value (1 coin = ₹1)</p>
                    </div>
                </div>
            </div>

            <!-- Buy Coins -->
            <div class="chart-container animate-slideUp">
                <h2>Buy Coins</h2>
                <div style="margin-top: 20px;">
                    <?php if (empty($packages)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                            No coin packages available at the moment.
                        </p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                            <?php foreach ($packages as $package): ?>
                                <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; text-align: center;">
                                    <h3 style="color: var(--text-primary); margin: 0 0 10px 0;"><?= htmlspecialchars($package['name']) ?></h3>
                                    <div style="font-size: 1.5rem; color: var(--accent-color); margin: 15px 0;">
                                        <i class="fas fa-coins"></i> <?= number_format($package['coin_amount']) ?>
                                    </div>
                                    <?php if ($package['bonus_coins'] > 0): ?>
                                        <div style="background: #10b981; color: white; padding: 3px 8px; border-radius: 20px; font-size: 0.8em; display: inline-block; margin: 10px 0;">
                                            +<?= number_format($package['bonus_coins']) ?> Bonus
                                        </div>
                                    <?php endif; ?>
                                    <div style="font-size: 1.2rem; color: var(--text-primary); margin: 15px 0;">
                                        ₹<?= number_format($package['price_inr'], 2) ?>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="package_id" value="<?= $package['package_id'] ?>">
                                        <button type="submit" name="buy_coins" 
                                                style="width: 100%; background: var(--accent-color); color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; margin-top: 15px;">
                                            Buy Now
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pending Payments -->
            <?php if (!empty($pendingPayments)): ?>
            <div class="chart-container animate-slideUp">
                <h2>Pending Payments</h2>
                <div style="margin-top: 20px;">
                    <?php foreach ($pendingPayments as $payment): ?>
                        <div style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <div>
                                    <h4 style="color: var(--text-primary); margin: 0;">Payment Request</h4>
                                    <div style="color: var(--text-secondary); font-size: 0.9em;">
                                        <?= date('M j, Y g:i A', strtotime($payment['created_at'])) ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 1.2rem; color: var(--text-primary);">
                                        ₹<?= number_format($payment['price_inr'], 2) ?>
                                    </div>
                                    <div style="color: var(--accent-color);">
                                        <?= number_format($payment['coin_amount'] + $payment['bonus_coins']) ?> coins
                                    </div>
                                </div>
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <a href="/user/payment.php?payment_id=<?= $payment['payment_id'] ?>" 
                                   style="background: var(--accent-color); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-block;">
                                    <i class="fas fa-credit-card"></i> Complete Payment
                                </a>
                                <button onclick="cancelPayment('<?= $payment['payment_id'] ?>')" 
                                        style="background: #ef4444; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Transaction History -->
            <div class="chart-container animate-slideUp">
                <h2>Transaction History</h2>
                <div style="margin-top: 20px; max-height: 400px; overflow-y: auto;">
                    <?php if (empty($transactions)): ?>
                        <p style="color: var(--text-secondary); text-align: center; padding: 40px;">
                            No transactions yet.
                        </p>
                    <?php else: ?>
                        <?php foreach ($transactions as $txn): ?>
                            <div style="display: flex; align-items: center; padding: 15px; border-bottom: 1px solid var(--border-color);">
                                <?php 
                                $icon = 'fas fa-coins';
                                $color = 'var(--text-secondary)';
                                if (strpos($txn['reference_type'], 'like') !== false) {
                                    $icon = 'fas fa-heart';
                                    $color = '#ef4444';
                                } elseif (strpos($txn['reference_type'], 'comment') !== false) {
                                    $icon = 'fas fa-comment';
                                    $color = '#10b981';
                                } elseif (strpos($txn['reference_type'], 'subscription') !== false) {
                                    $icon = 'fas fa-crown';
                                    $color = '#f59e0b';
                                } elseif (strpos($txn['reference_type'], 'purchase') !== false) {
                                    $icon = 'fas fa-shopping-cart';
                                    $color = '#8b5cf6';
                                } elseif (strpos($txn['reference_type'], 'upi') !== false) {
                                    $icon = 'fas fa-rupee-sign';
                                    $color = '#10b981';
                                }
                                ?>
                                <i class="<?= $icon ?>" style="color: <?= $color ?>; margin-right: 15px; width: 20px;"></i>
                                <div style="flex: 1;">
                                    <div style="color: var(--text-primary);"><?= htmlspecialchars($txn['description']) ?></div>
                                    <div style="color: var(--text-secondary); font-size: 0.8em;">
                                        <?= date('M j, Y g:i A', strtotime($txn['created_at'])) ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: <?= $txn['amount'] >= 0 ? '#10b981' : '#ef4444' ?>; font-weight: bold;">
                                        <?= $txn['amount'] >= 0 ? '+' : '' ?><?= number_format($txn['amount'], 2) ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.8em;">
                                        Balance: <?= number_format($txn['balance_after'], 2) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
    <script>
        function cancelPayment(paymentId) {
            if (confirm('Are you sure you want to cancel this payment?')) {
                // In a real implementation, you would make an AJAX call to cancel the payment
                alert('Payment cancelled. In a real implementation, this would be processed on the server.');
            }
        }
    </script>
</body>
</html>
