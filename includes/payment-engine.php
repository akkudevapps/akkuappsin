<?php
/**
 * Ad Payment Engine
 * Handles payments, wallet, and transactions
 */

/**
 * Create a Razorpay order for ad wallet deposit
 */
function akkuAdCreateRazorpayOrder($amount, $currency = 'INR', $providerId = null, $description = 'Ad Wallet Deposit') {
    try {
        $razorpayKeyId = getenv('RAZORPAY_KEY_ID') ?? $_ENV['RAZORPAY_KEY_ID'] ?? null;
        $razorpayKeySecret = getenv('RAZORPAY_KEY_SECRET') ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? null;
        
        if (!$razorpayKeyId || !$razorpayKeySecret) {
            return ['success' => false, 'error' => 'Razorpay credentials not configured'];
        }
        
        // Initialize Razorpay API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $razorpayKeyId . ':' . $razorpayKeySecret);
        
        $postData = [
            'amount' => $amount * 100, // Razorpay expects amount in paise
            'currency' => $currency,
            'receipt' => 'ad_wallet_' . uniqid(),
            'notes' => [
                'provider_id' => $providerId ?? 'anonymous',
                'type' => 'ad_wallet_deposit'
            ]
        ];
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $result = curl_exec($ch);
        curl_close($ch);
        
        $response = json_decode($result, true);
        
        if (isset($response['id'])) {
            return [
                'success' => true,
                'order_id' => $response['id'],
                'amount' => $amount,
                'currency' => $currency
            ];
        } else {
            return ['success' => false, 'error' => $response['error']['description'] ?? 'Failed to create order'];
        }
    } catch (Exception $e) {
        error_log("Error creating Razorpay order: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verify Razorpay payment signature
 */
function akkuAdVerifyRazorpaySignature($orderId, $paymentId, $signature, $keySecret) {
    try {
        $body = $orderId . '|' . $paymentId;
        $expectedSignature = hash_hmac('sha256', $body, $keySecret);
        
        if ($expectedSignature === $signature) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Invalid signature'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Process Razorpay payment callback
 */
function akkuAdProcessRazorpayCallback($pdo, $paymentData) {
    try {
        $razorpayKeySecret = getenv('RAZORPAY_KEY_SECRET') ?? $_ENV['RAZORPAY_KEY_SECRET'] ?? null;
        
        // Verify signature
        $verification = akkuAdVerifyRazorpaySignature(
            $paymentData['order_id'] ?? '',
            $paymentData['payment_id'] ?? '',
            $paymentData['signature'] ?? '',
            $razorpayKeySecret
        );
        
        if (!$verification['success']) {
            return $verification;
        }
        
        // Get order details from Razorpay
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.razorpay.com/v1/orders/' . $paymentData['order_id']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_USERPWD, (getenv('RAZORPAY_KEY_ID') ?? $_ENV['RAZORPAY_KEY_ID']) . ':' . $razorpayKeySecret);
        $result = curl_exec($ch);
        curl_close($ch);
        
        $orderDetails = json_decode($result, true);
        
        if (isset($orderDetails['notes']['provider_id']) && $orderDetails['notes']['provider_id'] !== 'anonymous') {
            $providerId = $orderDetails['notes']['provider_id'];
            $amount = $orderDetails['amount'] / 100; // Convert from paise
            $currency = $orderDetails['currency'];
            
            // Add to wallet
            return akkuAdAddWallet(
                $pdo,
                $providerId,
                $amount,
                'Razorpay deposit - Order: ' . $paymentData['order_id'],
                'razorpay',
                $paymentData['payment_id']
            );
        }
        
        return ['success' => true, 'message' => 'Payment processed'];
    } catch (Exception $e) {
        error_log("Error processing Razorpay callback: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Create manual invoice for provider
 */
function akkuAdCreateInvoice($pdo, $providerId, $adId, $amount, $currency = 'USD', $description = '') {
    try {
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . substr(uniqid(), -6);
        $dueDate = date('Y-m-d', strtotime('+7 days'));
        
        $sql = "
            INSERT INTO ad_transactions 
            (id, provider_id, ad_id, amount, currency, transaction_type, status, payment_method, invoice_number, invoice_date, due_date, description)
            VALUES (?, ?, ?, ?, ?, 'charge', 'pending', 'manual', ?, ?, ?, ?)
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            generateUUID(),
            $providerId,
            $adId,
            $amount,
            $currency,
            $invoiceNumber,
            date('Y-m-d'),
            $dueDate,
            $description
        ]);
        
        return [
            'success' => true,
            'invoice_number' => $invoiceNumber,
            'due_date' => $dueDate
        ];
    } catch (Exception $e) {
        error_log("Error creating invoice: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get provider transactions
 */
function akkuAdGetProviderTransactions($pdo, $providerId, $limit = 50) {
    try {
        $sql = "
            SELECT * FROM ad_transactions
            WHERE provider_id = ?
            ORDER BY created_at DESC
            LIMIT ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$providerId, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting provider transactions: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark transaction as paid
 */
function akkuAdMarkTransactionAsPaid($pdo, $transactionId, $paymentReference = null) {
    try {
        $sql = "
            UPDATE ad_transactions
            SET status = 'completed', paid_date = NOW(), external_transaction_id = ?
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$paymentReference, $transactionId]);
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Error marking transaction as paid: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Calculate ad cost based on pricing model
 */
function akkuAdCalculateCost($ad, $pricingTier, $impressions = 0, $clicks = 0) {
    $cost = 0;
    
    switch ($ad['pricing_model'] ?? 'fixed') {
        case 'fixed':
            // Monthly fixed price
            if (!empty($ad['start_date']) && !empty($ad['end_date'])) {
                $days = (strtotime($ad['end_date']) - strtotime($ad['start_date'])) / (60 * 60 * 24);
                $cost = ($pricingTier['base_price_monthly'] ?? 0) * ($days / 30);
            } else {
                $cost = $pricingTier['base_price_monthly'] ?? 0;
            }
            break;
            
        case 'cpm':
            // Cost per 1000 impressions
            $cost = ($impressions / 1000) * ($pricingTier['price_cpm'] ?? 0);
            break;
            
        case 'cpc':
            // Cost per click
            $cost = $clicks * ($pricingTier['price_cpc'] ?? 0);
            break;
    }
    
    return round($cost, 2);
}

/**
 * Update ad budget spent
 */
function akkuAdUpdateBudgetSpent($pdo, $adId, $amount) {
    try {
        $sql = "
            UPDATE advertisements
            SET budget_spent = COALESCE(budget_spent, 0) + ?
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$amount, $adId]);
        
        // Check if budget limit exceeded
        $sql2 = "
            SELECT daily_budget, budget_spent, total_budget
            FROM advertisements
            WHERE id = ?
        ";
        
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$adId]);
        $ad = $stmt2->fetch();
        
        if ($ad['budget_spent'] >= $ad['total_budget']) {
            // Pause ad if budget exhausted
            $sql3 = "UPDATE advertisements SET status = 'completed' WHERE id = ?";
            $stmt3 = $pdo->prepare($sql3);
            $stmt3->execute([$adId]);
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Error updating budget: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Charge provider for ad display (daily or on-demand)
 */
function akkuAdChargeForDisplay($pdo, $adId, $impressions = 0, $clicks = 0) {
    try {
        // Get ad and provider details
        $sql = "
            SELECT a.*, ap.wallet_balance
            FROM advertisements a
            JOIN ad_providers ap ON a.provider_id = ap.id
            WHERE a.id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$adId]);
        $ad = $stmt->fetch();
        
        if (!$ad) {
            return ['success' => false, 'error' => 'Ad not found'];
        }
        
        // Get pricing tier
        $sql2 = "SELECT * FROM ad_pricing_tiers WHERE id = ?";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$ad['ad_size_id']]);
        $pricingTier = $stmt2->fetch();
        
        // Calculate cost
        $cost = akkuAdCalculateCost($ad, $pricingTier, $impressions, $clicks);
        
        if ($cost > 0) {
            // Deduct from wallet
            return akkuAdDeductWallet($pdo, $ad['provider_id'], $cost, "Charge for ad display - {$impressions} impressions, {$clicks} clicks", $adId);
        }
        
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Error charging for display: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Format currency based on provider's preference
 */
function akkuAdFormatCurrency($amount, $currency = 'USD') {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'AUD' => 'A$',
        'CAD' => 'C$',
        'JPY' => '¥'
    ];
    
    $symbol = $symbols[$currency] ?? $currency;
    return $symbol . ' ' . number_format($amount, 2);
}
?>
