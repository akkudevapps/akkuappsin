<?php
/**
 * Ad System Engine
 * Core functions for ad management, display, and tracking
 */

/**
 * Get all active ads for a specific page and region
 */
function akkuAdGetActiveAds($pdo, $page = 'news_index', $region = 'general', $language = 'en', $limit = 5) {
    try {
        $sql = "
            SELECT a.*, ap.position, ap.priority
            FROM advertisements a
            JOIN ad_placements ap ON a.id = ap.ad_id
            WHERE ap.page = ?
            AND ap.is_active = 1
            AND a.status = 'active'
            AND a.start_date <= CURDATE()
            AND a.end_date >= CURDATE()
            AND (
                a.target_regions IS NULL 
                OR JSON_CONTAINS(a.target_regions, JSON_QUOTE(?), '$.regions')
                OR JSON_CONTAINS(a.target_regions, JSON_QUOTE(?), '$.cities')
            )
            AND (
                a.target_languages IS NULL 
                OR JSON_CONTAINS(a.target_languages, JSON_QUOTE(?), '$.languages')
            )
            ORDER BY ap.priority DESC, RAND()
            LIMIT ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$page, $region, $region, $language, $limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting active ads: " . $e->getMessage());
        return [];
    }
}

/**
 * Get a random ad for placement
 */
function akkuAdGetRandomAd($pdo, $page = 'news_index', $region = 'general', $language = 'en') {
    $ads = akkuAdGetActiveAds($pdo, $page, $region, $language, 1);
    return $ads[0] ?? null;
}

/**
 * Record ad impression (view)
 */
function akkuAdRecordImpression($pdo, $adId, $placement = 'unknown') {
    try {
        $today = date('Y-m-d');
        
        // Update ad_analytics table
        $sql = "
            INSERT INTO ad_analytics (id, ad_id, analytics_date, impressions)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE impressions = impressions + 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([generateUUID(), $adId, $today]);
        
        // Update main ad record
        $sql2 = "
            UPDATE advertisements 
            SET impressions = impressions + 1 
            WHERE id = ?
        ";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$adId]);
        
        // Update ad_placements impression count
        $sql3 = "
            UPDATE ad_placements 
            SET impression_count = impression_count + 1 
            WHERE ad_id = ? AND position = ?
        ";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute([$adId, $placement]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error recording impression: " . $e->getMessage());
        return false;
    }
}

/**
 * Record ad click
 */
function akkuAdRecordClick($pdo, $adId, $placement = 'unknown') {
    try {
        $today = date('Y-m-d');
        
        // Update ad_analytics table
        $sql = "
            INSERT INTO ad_analytics (id, ad_id, analytics_date, clicks)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE clicks = clicks + 1
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([generateUUID(), $adId, $today]);
        
        // Update main ad record
        $sql2 = "
            UPDATE advertisements 
            SET clicks = clicks + 1 
            WHERE id = ?
        ";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute([$adId]);
        
        // Update ad_placements click count
        $sql3 = "
            UPDATE ad_placements 
            SET click_count = click_count + 1 
            WHERE ad_id = ? AND position = ?
        ";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute([$adId, $placement]);
        
        // Update CTR
        akkuAdUpdateCTR($pdo, $adId);
        
        return true;
    } catch (Exception $e) {
        error_log("Error recording click: " . $e->getMessage());
        return false;
    }
}

/**
 * Update CTR (Click-through rate)
 */
function akkuAdUpdateCTR($pdo, $adId) {
    try {
        $sql = "
            UPDATE advertisements 
            SET ctr = CASE 
                WHEN impressions > 0 THEN (clicks / impressions) * 100 
                ELSE 0 
            END 
            WHERE id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$adId]);
    } catch (Exception $e) {
        error_log("Error updating CTR: " . $e->getMessage());
    }
}

/**
 * Get ad provider by user_id
 */
function akkuAdGetProviderByUser($pdo, $userId) {
    try {
        $sql = "SELECT * FROM ad_providers WHERE user_id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting provider: " . $e->getMessage());
        return null;
    }
}

/**
 * Get provider's active ads
 */
function akkuAdGetProviderAds($pdo, $providerId, $status = 'active') {
    try {
        $sql = "
            SELECT * FROM advertisements 
            WHERE provider_id = ?
        ";
        $params = [$providerId];
        
        if ($status !== 'all') {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting provider ads: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's location and language (based on IP or user profile)
 */
function akkuAdGetUserLocationAndLanguage($pdo, $userId = null) {
    $location = [
        'country' => 'IN',
        'state' => 'TamilNadu',
        'city' => 'Chennai',
        'region' => 'TamilNadu',
        'language' => 'ta'
    ];
    
    // If user is logged in, try to get their location from profile
    if ($userId) {
        try {
            $sql = "SELECT country, state, city FROM users WHERE id = ? LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                $location['country'] = $user['country'] ?? 'IN';
                $location['state'] = $user['state'] ?? 'TamilNadu';
                $location['city'] = $user['city'] ?? 'Chennai';
            }
        } catch (Exception $e) {
            // Use default
        }
    }
    
    // Map regions to languages
    $regionLanguageMap = [
        'TamilNadu' => 'ta',
        'Karnataka' => 'ka',
        'Kerala' => 'ml',
        'AndhraPradesh' => 'te',
        'Maharashtra' => 'mr',
        'Gujarat' => 'gu',
        'WestBengal' => 'bn',
        'Telangana' => 'te',
        'default' => 'en'
    ];
    
    $location['language'] = $regionLanguageMap[$location['state']] ?? $regionLanguageMap['default'];
    
    return $location;
}

/**
 * Deduct from provider wallet
 */
function akkuAdDeductWallet($pdo, $providerId, $amount, $description = '', $adId = null) {
    try {
        // Get current balance
        $sql = "SELECT wallet_balance FROM ad_providers WHERE id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch();
        
        if (!$provider || $provider['wallet_balance'] < $amount) {
            return ['success' => false, 'error' => 'Insufficient wallet balance'];
        }
        
        $newBalance = $provider['wallet_balance'] - $amount;
        
        // Deduct from wallet
        $sql = "UPDATE ad_providers SET wallet_balance = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newBalance, $providerId]);
        
        // Record transaction
        $sql = "
            INSERT INTO ad_wallet_transactions (id, provider_id, amount, transaction_type, status, description, balance_before, balance_after, related_ad_id)
            VALUES (?, ?, ?, 'charge', 'completed', ?, ?, ?, ?)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            generateUUID(),
            $providerId,
            $amount,
            $description,
            $provider['wallet_balance'],
            $newBalance,
            $adId
        ]);
        
        return ['success' => true, 'new_balance' => $newBalance];
    } catch (Exception $e) {
        error_log("Error deducting wallet: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Add to provider wallet
 */
function akkuAdAddWallet($pdo, $providerId, $amount, $description = '', $paymentMethod = 'manual', $gatewayId = null) {
    try {
        // Get current balance
        $sql = "SELECT wallet_balance FROM ad_providers WHERE id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$providerId]);
        $provider = $stmt->fetch();
        
        $currentBalance = $provider['wallet_balance'] ?? 0;
        $newBalance = $currentBalance + $amount;
        
        // Add to wallet
        $sql = "UPDATE ad_providers SET wallet_balance = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$newBalance, $providerId]);
        
        // Record transaction
        $sql = "
            INSERT INTO ad_wallet_transactions (id, provider_id, amount, transaction_type, status, payment_method, description, balance_before, balance_after, payment_gateway_id)
            VALUES (?, ?, ?, 'deposit', 'completed', ?, ?, ?, ?, ?)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            generateUUID(),
            $providerId,
            $amount,
            $paymentMethod,
            $description,
            $currentBalance,
            $newBalance,
            $gatewayId
        ]);
        
        return ['success' => true, 'new_balance' => $newBalance];
    } catch (Exception $e) {
        error_log("Error adding to wallet: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get ad analytics for provider
 */
function akkuAdGetProviderAnalytics($pdo, $providerId, $days = 30) {
    try {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $sql = "
            SELECT 
                SUM(a.impressions) as total_impressions,
                SUM(a.clicks) as total_clicks,
                SUM(a.conversions) as total_conversions,
                AVG(a.ctr) as avg_ctr,
                SUM(a.spend_today) as total_spend
            FROM ad_analytics a
            JOIN advertisements ad ON a.ad_id = ad.id
            WHERE ad.provider_id = ? AND a.analytics_date >= ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$providerId, $startDate]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Error getting provider analytics: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all pricing tiers
 */
function akkuAdGetPricingTiers($pdo) {
    try {
        $sql = "SELECT * FROM ad_pricing_tiers WHERE is_active = 1 ORDER BY width_px DESC, height_px DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting pricing tiers: " . $e->getMessage());
        return [];
    }
}

/**
 * Create ad ticket (Digital good for buying ad space)
 */
function akkuAdCreateTicket($pdo, $name, $pricingTierId, $durationDays, $price, $currency = 'USD') {
    try {
        $id = generateUUID();
        $sql = "
            INSERT INTO ad_tickets (id, ticket_name, pricing_tier_id, duration_days, price, currency, status)
            VALUES (?, ?, ?, ?, ?, ?, 'active')
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id, $name, $pricingTierId, $durationDays, $price, $currency]);
        return $id;
    } catch (Exception $e) {
        error_log("Error creating ad ticket: " . $e->getMessage());
        return null;
    }
}

/**
 * Get all active ad tickets
 */
function akkuAdGetTickets($pdo) {
    try {
        $sql = "
            SELECT t.*, pt.display_name, pt.width_px, pt.height_px
            FROM ad_tickets t
            LEFT JOIN ad_pricing_tiers pt ON t.pricing_tier_id = pt.id
            WHERE t.status = 'active'
            ORDER BY t.price ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error getting ad tickets: " . $e->getMessage());
        return [];
    }
}

/**
 * Format ad for display
 */
function akkuAdFormatForDisplay($ad) {
    return [
        'id' => $ad['id'],
        'title' => $ad['title'],
        'image_url' => $ad['image_url'],
        'click_url' => $ad['click_url'],
        'alt_text' => $ad['alt_text'],
        'type' => $ad['ad_type'],
        'size' => "{$ad['width_px']}x{$ad['height_px']}" ?? 'custom',
    ];
}
?>
