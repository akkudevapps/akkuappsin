<?php
// user/payment.php
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

$paymentId = $_GET['payment_id'] ?? $_SESSION['payment_id'] ?? null;

if (!$paymentId) {
    header("Location: /user/wallet.php");
    exit;
}

// Get payment details
try {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT up.*, cp.name as package_name, cp.coin_amount, cp.bonus_coins
        FROM upi_payments up
        JOIN coin_packages cp ON up.package_id = cp.package_id
        WHERE up.payment_id = ? AND up.user_id = ? AND up.status = 'pending'
    ");
    $stmt->execute([$paymentId, $user['user_id']]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        $error = "Payment not found or already processed";
    }
} catch (Exception $e) {
    $error = "Error retrieving payment details";
    error_log("Payment page error: " . $e->getMessage());
}

// Handle UTR submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_utr'])) {
    $utr = trim($_POST['utr'] ?? '');
    
    if (empty($utr)) {
        $error = "Please enter the UTR number";
    } else if (strlen($utr) < 5) {
        $error = "Invalid UTR number";
    } else {
        // In a real implementation, admin would verify the payment
        // For now, we'll simulate successful verification
        $result = verifyPayment($paymentId, 'admin', $utr, 'User submitted UTR');
        
        if ($result['success']) {
            $message = "Payment verified successfully! " . $result['message'];
            $success = true;
            // Refresh user data
            $user = getCurrentUser();
        } else {
            $error = $result['error'];
        }
    }
}

$upiUrl = $_SESSION['upi_url'] ?? ($payment ? "upi://pay?pa=" . UPI_ID . "&pn=AkkuApps&am=" . $payment['price_inr'] . "&cu=INR&tn=Coins" : '');
$amountInr = $_SESSION['amount_inr'] ?? ($payment ? $payment['price_inr'] : 0);
$coinsToAdd = $_SESSION['coins_to_add'] ?? ($payment ? ($payment['coin_amount'] + $payment['bonus_coins']) : 0);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Complete Payment</h1>
                <p>Pay via UPI to add coins to your wallet</p>
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

            <?php if ($success): ?>
                <div style="text-align: center; padding: 40px;">
                    <div style="font-size: 4rem; color: #10b981; margin-bottom: 20px;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h2 style="color: var(--text-primary); margin-bottom: 20px;">Payment Successful!</h2>
                    <p style="color: var(--text-secondary); margin-bottom: 30px;">
                        <?= number_format($coinsToAdd) ?> coins have been added to your wallet.
                    </p>
                    <a href="/user/wallet.php" 
                       style="background: var(--accent-color); color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: inline-block;">
                        <i class="fas fa-wallet"></i> View Wallet
                    </a>
                </div>
            <?php else: ?>
                <div class="chart-container animate-slideUp">
                    <h2>Payment Details</h2>
                    <div style="margin-top: 20px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; padding: 30px; text-align: center;">
                        <?php if ($payment): ?>
                            <h3 style="color: var(--text-primary); margin-top: 0;"><?= htmlspecialchars($payment['package_name']) ?></h3>
                            
                            <div style="display: flex; justify-content: center; margin: 30px 0;">
                                <div style="text-align: center; margin: 0 30px;">
                                    <div style="font-size: 2rem; color: var(--accent-color);">₹<?= number_format($payment['price_inr'], 2) ?></div>
                                    <div style="color: var(--text-secondary);">Amount</div>
                                </div>
                                <div style="text-align: center; margin: 0 30px;">
                                    <div style="font-size: 2rem; color: var(--accent-color);">
                                        <i class="fas fa-coins"></i> <?= number_format($payment['coin_amount'] + $payment['bonus_coins']) ?>
                                    </div>
                                    <div style="color: var(--text-secondary);">Coins</div>
                                </div>
                            </div>
                            
                            <?php if ($payment['bonus_coins'] > 0): ?>
                                <div style="background: #10b981; color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; margin-bottom: 20px;">
                                    +<?= number_format($payment['bonus_coins']) ?> Bonus Coins!
                                </div>
                            <?php endif; ?>
                            
                            <div style="margin: 30px 0;">
                                <h4 style="color: var(--text-primary); margin-bottom: 15px;">How to Pay</h4>
                                <ol style="text-align: left; color: var(--text-secondary); max-width: 400px; margin: 0 auto;">
                                    <li>Click the "Pay with UPI" button below</li>
                                    <li>Complete the payment in your UPI app</li>
                                    <li>After payment, enter the UTR number below</li>
                                    <li>Admin will verify and add coins to your wallet</li>
                                </ol>
                            </div>
                            
                            <div style="margin: 30px 0;">
                                <a href="<?= htmlspecialchars($upiUrl) ?>" 
                                   style="background: #10b981; color: white; padding: 15px 30px; border-radius: 6px; text-decoration: none; display: inline-block; margin-bottom: 20px;">
                                    <i class="fas fa-qrcode"></i> Pay with UPI
                                </a>
                            </div>
                            
                            <div style="border-top: 1px solid var(--border-color); padding-top: 30px; margin-top: 30px;">
                                <h4 style="color: var(--text-primary); margin-bottom: 15px;">Already Paid?</h4>
                                <form method="POST">
                                    <div style="margin-bottom: 20px;">
                                        <input type="text" name="utr" placeholder="Enter UTR Number" 
                                               style="width: 100%; max-width: 300px; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                                    </div>
                                    <button type="submit" name="submit_utr" 
                                            style="background: var(--accent-color); color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer;">
                                        <i class="fas fa-check"></i> Submit UTR
                                    </button>
                                </form>
                            </div>
                        <?php else: ?>
                            <p style="color: var(--text-secondary);">Payment details not found.</p>
                            <a href="/user/wallet.php" 
                               style="background: var(--accent-color); color: white; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: inline-block; margin-top: 20px;">
                                <i class="fas fa-arrow-left"></i> Back to Wallet
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
