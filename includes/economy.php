<?php
// includes/economy.php – Complete economy engine for AkkuApps.in
// All functions: inventory, shop, stats, commissions, UPI, treasury, likes, comments, badges, tokens, subscriptions
require_once __DIR__ . '/notifications.php';

if (!defined('AKKUAPPS_LOADED')) exit;

// ========== USER INVENTORY ==========

function getUserInventory(string $userId): array {
    global $pdo;
    $badges = [];
    $gifts = [];

    try {
        $stmt = $pdo->prepare("
            SELECT b.*, ub.acquired_at 
            FROM user_badges ub
            JOIN badges b ON ub.badge_id = b.badge_id
            WHERE ub.user_id = ?
            ORDER BY ub.acquired_at DESC
        ");
        $stmt->execute([$userId]);
        $badges = $stmt->fetchAll();
    } catch (Exception $e) {}

    try {
        $stmt = $pdo->prepare("
            SELECT g.*, ui.quantity, ui.acquired_at
            FROM user_inventory ui
            JOIN gifts g ON ui.item_id = g.gift_id
            WHERE ui.user_id = ? AND ui.item_type = 'gift'
            ORDER BY ui.acquired_at DESC
        ");
        $stmt->execute([$userId]);
        $gifts = $stmt->fetchAll();
    } catch (Exception $e) {}

    return ['badges' => $badges, 'gifts' => $gifts];
}

function getUserTokens(string $userId): array {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT t.*, ut.quantity, ut.acquired_at
            FROM user_tokens ut
            JOIN akku_tokens t ON ut.token_id = t.token_id
            WHERE ut.user_id = ?
            ORDER BY ut.acquired_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// ========== SHOP / CATALOG ==========

function getBadges(bool $activeOnly = true): array {
    global $pdo;
    $sql = "SELECT * FROM badges" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY created_at DESC";
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getGifts(bool $activeOnly = true): array {
    global $pdo;
    $sql = "SELECT * FROM gifts" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY created_at DESC";
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function getTokens(bool $activeOnly = true): array {
    global $pdo;
    $sql = "SELECT * FROM akku_tokens" . ($activeOnly ? " WHERE is_active = 1" : "") . " ORDER BY created_at DESC";
    try {
        return $pdo->query($sql)->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// ========== ECONOMY STATS ==========

function getEconomyStats(): array {
    global $pdo;
    $stats = [
        'total_coins_in_circulation' => 0,
        'total_transactions' => 0,
        'total_gifts_sent' => 0,
        'total_token_conversions' => 0,
        'total_game_commission' => 0,
        'pending_upi_payments' => 0
    ];

    try {
        $stats['total_coins_in_circulation'] = $pdo->query("SELECT SUM(coin_balance) FROM users")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
    try {
        $stats['total_transactions'] = $pdo->query("SELECT COUNT(*) FROM coin_transactions")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
    try {
        $stats['total_gifts_sent'] = $pdo->query("SELECT COUNT(*) FROM gift_transactions")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
    try {
        $stats['total_token_conversions'] = $pdo->query("SELECT COUNT(*) FROM token_conversions")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
    try {
        $stats['total_game_commission'] = $pdo->query("SELECT SUM(commission_amount) FROM game_sessions WHERE status='completed'")->fetchColumn() ?? 0;
    } catch (Exception $e) {}
    try {
        $stats['pending_upi_payments'] = $pdo->query("SELECT COUNT(*) FROM upi_payments WHERE status = 'pending'")->fetchColumn() ?? 0;
    } catch (Exception $e) {}

    return $stats;
}

// ========== COMMISSION SETTINGS ==========

function getCommissionSetting(string $key): float {
    global $pdo;
    try {
        // Try commission_settings table first
        $stmt = $pdo->prepare("SELECT setting_value FROM commission_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        if ($val !== false) return floatval($val);

        // Fallback to config table
        $stmt = $pdo->prepare("SELECT setting_value FROM config WHERE setting_key = ?");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? floatval($val) : getDefaultCommission($key);
    } catch (Exception $e) {
        return getDefaultCommission($key);
    }
}

function setCommissionSetting(string $key, float $value): void {
    global $pdo;
    try {
        $pdo->prepare("
            INSERT INTO commission_settings (setting_key, setting_value, updated_at) 
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ")->execute([$key, $value, $value]);
    } catch (Exception $e) {
        // Fallback to config table
        try {
            $pdo->prepare("
                INSERT INTO config (setting_key, setting_value, updated_at) 
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
            ")->execute([$key, $value, $value]);
        } catch (Exception $e2) {}
    }
}

function getDefaultCommission(string $key): float {
    $defaults = [
        'game_commission_rate' => 10,
        'token_conversion_commission' => 10,
        'gift_commission_rate' => 5,
        // FIX Priority 3: cost=2, reward=1 → platform fee = 1 coin (50%)
        'post_like_cost' => 2,
        'post_comment_cost' => 2,
        'post_like_reward' => 1,
        'post_comment_reward' => 1,
        'post_view_reward' => 0.1,
        'post_creation_cost' => 2,
        // NEW: Repost cost to prevent exploit
        'post_repost_cost' => 1,
        'post_repost_reward' => 0.5,
    ];
    return $defaults[$key] ?? 0;
}

function getTreasuryAdminId(): ?string {
    global $pdo;

    try {
        $stmt = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' ORDER BY created_at ASC LIMIT 1");
        $adminId = $stmt->fetchColumn();
        return $adminId !== false ? (string)$adminId : null;
    } catch (Exception $e) {
        return null;
    }
}

function collectToTreasury(
    string $type,
    $sourceUserId,
    float $feeAmount,
    ?string $entityType = null,
    $entityId = null,
    $targetUserId = null,
    float $grossAmount = 0.0,
    float $feeRate = 0.0,
    string $description = ''
): int {
    global $pdo;

    if ($feeAmount <= 0) {
        return 0;
    }

    $adminId = getTreasuryAdminId();
    if (!$adminId) {
        return 0;
    }

    $treasury = new AkkuCollectionBox($pdo, $adminId);

    return $treasury->collect(
        $type,
        $sourceUserId,
        $feeAmount,
        $entityType,
        $entityId,
        $targetUserId,
        $grossAmount,
        $feeRate,
        $description
    );
}

// ========== GIFT HISTORY ==========

function getGiftHistory(string $userId, string $type = 'all'): array {
    global $pdo;

    if ($type === 'sent') {
        $sql = "SELECT gt.*, g.name as gift_name, g.image_url as gift_image,
                s.name as sender_name, r.name as receiver_name
                FROM gift_transactions gt
                JOIN gifts g ON gt.gift_id = g.gift_id
                JOIN users s ON gt.sender_id = s.user_id
                JOIN users r ON gt.receiver_id = r.user_id
                WHERE gt.sender_id = ?
                ORDER BY gt.created_at DESC LIMIT 50";
        $params = [$userId];
    } elseif ($type === 'received') {
        $sql = "SELECT gt.*, g.name as gift_name, g.image_url as gift_image,
                s.name as sender_name, r.name as receiver_name
                FROM gift_transactions gt
                JOIN gifts g ON gt.gift_id = g.gift_id
                JOIN users s ON gt.sender_id = s.user_id
                JOIN users r ON gt.receiver_id = r.user_id
                WHERE gt.receiver_id = ?
                ORDER BY gt.created_at DESC LIMIT 50";
        $params = [$userId];
    } else {
        $sql = "SELECT gt.*, g.name as gift_name, g.image_url as gift_image,
                s.name as sender_name, r.name as receiver_name
                FROM gift_transactions gt
                JOIN gifts g ON gt.gift_id = g.gift_id
                JOIN users s ON gt.sender_id = s.user_id
                JOIN users r ON gt.receiver_id = r.user_id
                WHERE gt.sender_id = ? OR gt.receiver_id = ?
                ORDER BY gt.created_at DESC LIMIT 50";
        $params = [$userId, $userId];
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// ========== UPI PAYMENTS ==========

function verifyPayment(string $paymentId, string $adminId, string $utr, string $notes = ''): array {
    global $pdo;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT up.*, u.coin_balance, cp.coin_amount, cp.bonus_coins
            FROM upi_payments up
            JOIN users u ON up.user_id = u.user_id
            JOIN coin_packages cp ON up.package_id = cp.package_id
            WHERE up.payment_id = ? AND up.status = 'pending'
        ");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();

        if (!$payment) {
            return ['success' => false, 'error' => 'Payment not found or already processed'];
        }

        $totalCoins = $payment['coin_amount'] + $payment['bonus_coins'];
        $newBalance = $payment['coin_balance'] + $totalCoins;

        $pdo->prepare("
            UPDATE upi_payments 
            SET status = 'verified', utr_number = ?, notes = ?, verified_by = ?, verified_at = NOW()
            WHERE payment_id = ?
        ")->execute([$utr, $notes, $adminId, $paymentId]);

        $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
            ->execute([$newBalance, $payment['user_id']]);

        $pdo->prepare("
            INSERT INTO coin_transactions (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
            VALUES (?, ?, 'upi_deposit', ?, ?, ?, NOW())
        ")->execute([
            generateUUID(),
            $payment['user_id'],
            $totalCoins,
            $newBalance,
            "UPI payment verified. UTR: $utr. Package: {$payment['package_id']}"
        ]);

        $pdo->commit();
        return ['success' => true, 'message' => "Added $totalCoins coins to user"];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ========== NOTIFICATION SYSTEM ==========

// ========== LIKE SYSTEM ==========

// Fix Like System
function likePost($postId, $userId) {
    
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Get post owner
        $stmt = $pdo->prepare("SELECT user_id FROM user_posts WHERE post_id = ?");
        $stmt->execute([$postId]);
        $post = $stmt->fetch();
        
        if (!$post) {
            throw new Exception("Post not found");
        }
        
        // Prevent self-like farming
        if ($post['user_id'] === $userId) {
            throw new Exception("Cannot like own post");
        }
        
        // Check if already liked
        $stmt = $pdo->prepare("SELECT like_id FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);
        if ($stmt->fetch()) {
            throw new Exception("Already liked");
        }
        
        // Charge viewer 2 coins
        $stmt = $pdo->prepare("
            UPDATE users 
            SET coin_balance = coin_balance - 2.0000 
            WHERE user_id = ? AND coin_balance >= 2.0000
        ");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Insufficient coins");
        }
        
        // Reward owner 1 coin
        $stmt = $pdo->prepare("
            UPDATE users 
            SET coin_balance = coin_balance + 1.0000 
            WHERE user_id = ?
        ");
        $stmt->execute([$post['user_id']]);
        
        // Platform keeps 1 coin (fee)
        $platformFee = 1.0000;
        
        // Record like transaction for viewer
        $stmt = $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, related_user_id, collection_box_fee) 
            VALUES (?, ?, 'like_given', ?, -2.0000, 
                   (SELECT coin_balance FROM users WHERE user_id = ?), 
                   'Liked post', ?, ?)
        ");
        $stmt->execute([generateUUID(), $userId, $postId, $userId, $post['user_id'], $platformFee]);
        
        // Record like transaction for owner
        $stmt = $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, related_user_id) 
            VALUES (?, ?, 'like_received', ?, 1.0000, 
                   (SELECT coin_balance FROM users WHERE user_id = ?), 
                   'Received like', ?)
        ");
        $stmt->execute([generateUUID(), $post['user_id'], $postId, $post['user_id'], $userId]);
        
        // Record platform fee in collection box
        collectToTreasury(
            'post_like',
            $userId,
            $platformFee,
            'post',
            $postId,
            $post['user_id'],
            2.0000,
            50.00,
            "Like fee on post #{$postId}"
        );
        
        // Add like record
        $stmt = $pdo->prepare("
            INSERT INTO post_likes (like_id, post_id, user_id, coin_spent, coin_earned_by_owner, created_at) 
            VALUES (?, ?, ?, 2.0000, 1.0000, NOW())
        ");
        $stmt->execute([generateUUID(), $postId, $userId]);
        
        // Update post likes count
        $pdo->prepare("UPDATE user_posts SET likes_count = likes_count + 1 WHERE post_id = ?")
            ->execute([$postId]);
        
        $pdo->commit();
        return true;
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Like error: " . $e->getMessage());
        return false;
    }
}

// Collection Box Function
function recordCollectionBoxTransaction($type, $amount, $sourceUserId = null, $targetUserId = null, $entityType = null, $entityId = null) {
    return collectToTreasury(
        (string)$type,
        $sourceUserId,
        (float)$amount,
        $entityType,
        $entityId,
        $targetUserId,
        (float)$amount,
        100.00,
        ucfirst(str_replace('_', ' ', (string)$type))
    );
}


function unlikePost(string $userId, string $postId): array {
    global $pdo;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_id = ?");
        $stmt->execute([$postId, $userId]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return ['success' => false, 'error' => 'Like not found'];
        }

        // Decrement likes count
        $pdo->prepare("UPDATE user_posts SET likes_count = GREATEST(likes_count - 1, 0) WHERE post_id = ?")
            ->execute([$postId]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => 'Unliked',
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ========== COMMENT SYSTEM ==========

function commentOnPost(string $userId, string $postId, string $content): array {
    global $pdo;

    $cost = getCommissionSetting('post_comment_cost');
    $reward = getCommissionSetting('post_comment_reward');

    if (empty(trim($content))) {
        return ['success' => false, 'error' => 'Comment cannot be empty'];
    }

    try {
        $pdo->beginTransaction();

        // Get post owner
        $postStmt = $pdo->prepare("SELECT user_id FROM user_posts WHERE post_id = ?");
        $postStmt->execute([$postId]);
        $postOwner = $postStmt->fetchColumn();
        if (!$postOwner) {
            return ['success' => false, 'error' => 'Post not found'];
        }

        // Check commenter balance
        $viewerStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
        $viewerStmt->execute([$userId]);
        $viewerBalance = (float)$viewerStmt->fetchColumn();

        if ($viewerBalance < $cost) {
            return ['success' => false, 'error' => "Need {$cost} coins to comment. You have {$viewerBalance}"];
        }

        // Deduct from commenter
        $newViewerBalance = $viewerBalance - $cost;
        $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
            ->execute([$newViewerBalance, $userId]);

        // Record commenter transaction
        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
            VALUES (?, ?, 'comment_given', ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $userId, $postId, -$cost, $newViewerBalance,
            "Commented on post #{$postId}"
        ]);

        // Reward post owner
        $pdo->prepare("UPDATE users SET coin_balance = coin_balance + ? WHERE user_id = ?")
            ->execute([$reward, $postOwner]);

        // Record owner reward
        $ownerBalanceStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ?");
        $ownerBalanceStmt->execute([$postOwner]);
        $ownerNewBalance = (float)$ownerBalanceStmt->fetchColumn();

        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
            VALUES (?, ?, 'comment_received', ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $postOwner, $postId, $reward, $ownerNewBalance,
            "Comment reward from post #{$postId}"
        ]);

        // Insert comment
        $commentId = generateUUID();
        $pdo->prepare("
            INSERT INTO post_comments (comment_id, post_id, user_id, content, coin_spent, coin_earned_by_owner, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$commentId, $postId, $userId, $content, $cost, $reward]);

        // Update post comments_count
        $pdo->prepare("UPDATE user_posts SET comments_count = comments_count + 1 WHERE post_id = ?")
            ->execute([$postId]);

        // CollectionBox fee
        $fee = $cost - $reward;
        if ($fee > 0) {
            $feeRate = $cost > 0 ? round(($fee / $cost) * 100, 2) : 0;
            collectToTreasury(
                'post_comment',
                $userId,
                $fee,
                'post',
                $postId,
                $postOwner,
                $cost,
                $feeRate,
                "Comment fee on post #{$postId}: {$cost} cost - {$reward} reward = {$fee} fee"
            );
        }

        // Notify post owner
        createNotification($postOwner, 'comment', 'New Comment!', 'Someone commented on your post', $postId, 'post');

        $pdo->commit();

        return [
            'success' => true,
            'message' => "Commented! -{$cost} 🪙",
            'new_balance' => $newViewerBalance,
            'comment_id' => $commentId,
            'owner_rewarded' => $reward
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('commentOnPost error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Comment failed: ' . $e->getMessage()];
    }
}

// ========== REPOST SYSTEM ==========

function repostPost(string $userId, string $postId, string $comment = ''): array {
    global $pdo;

    // FIX Priority 5: Charge reposter to prevent free repost exploit
    $repostCost = getCommissionSetting('post_repost_cost');     // default: 1 coin
    $repostReward = getCommissionSetting('post_repost_reward'); // default: 0.5 coins

    try {
        $pdo->beginTransaction();

        // Verify original post exists
        $postStmt = $pdo->prepare("SELECT * FROM user_posts WHERE post_id = ? AND status = 'active'");
        $postStmt->execute([$postId]);
        $original = $postStmt->fetch();

        if (!$original) {
            return ['success' => false, 'error' => 'Post not found'];
        }

        // Check not reposting own post
        if ($original['user_id'] === $userId) {
            return ['success' => false, 'error' => 'Cannot repost your own post'];
        }

        // Check not already reposted
        $check = $pdo->prepare("SELECT repost_id FROM post_reposts WHERE post_id = ? AND user_id = ?");
        $check->execute([$postId, $userId]);
        if ($check->fetch()) {
            return ['success' => false, 'error' => 'Already reposted'];
        }

        // FIX: Check reposter has enough coins
        $balStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
        $balStmt->execute([$userId]);
        $reposterBalance = (float)$balStmt->fetchColumn();
        if ($reposterBalance < $repostCost) {
            return ['success' => false, 'error' => "Need {$repostCost} coins to repost. You have {$reposterBalance}"];
        }

        // Deduct repost cost from reposter
        $newReposterBalance = $reposterBalance - $repostCost;
        $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
            ->execute([$newReposterBalance, $userId]);

        // Record reposter transaction
        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
            VALUES (?, ?, 'repost_given', ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $userId, $postId, -$repostCost, $newReposterBalance,
            "Reposted post #{$postId} (cost: {$repostCost} coins)"
        ]);

        // Create repost record
        $repostId = generateUUID();
        $pdo->prepare("
            INSERT INTO post_reposts (repost_id, post_id, user_id, added_comment, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ")->execute([$repostId, $postId, $userId, $comment]);

        // Award coin bonus to original poster
        $pdo->prepare("UPDATE users SET coin_balance = coin_balance + ? WHERE user_id = ?")
            ->execute([$repostReward, $original['user_id']]);

        $ownerBalanceStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ?");
        $ownerBalanceStmt->execute([$original['user_id']]);
        $ownerNewBalance = (float)$ownerBalanceStmt->fetchColumn();

        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
            VALUES (?, ?, 'repost_received', ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $original['user_id'], $postId, $repostReward, $ownerNewBalance,
            "Repost reward for post #{$postId}"
        ]);

        // CollectionBox fee: repost_cost - repost_reward = platform keeps the difference
        $platformFee = $repostCost - $repostReward;
        if ($platformFee > 0) {
            $feeRate = $repostCost > 0 ? round(($platformFee / $repostCost) * 100, 2) : 0;
            collectToTreasury(
                'repost',
                $userId,
                $platformFee,
                'post',
                $postId,
                $original['user_id'],
                $repostCost,
                $feeRate,
                "Repost fee: {$repostCost} cost - {$repostReward} reward = {$platformFee} platform fee"
            );
        }

        // Notify original poster
        createNotification($original['user_id'], 'repost', 'Your post was reposted!', 'Someone reposted your content', $postId, 'post');

        $pdo->commit();

        return [
            'success' => true,
            'message' => "Reposted! -{$repostCost} 🪙",
            'repost_id' => $repostId,
            'owner_rewarded' => $repostReward,
            'new_balance' => $newReposterBalance
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('repostPost error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Repost failed: ' . $e->getMessage()];
    }
}

// ========== BADGE SHOP ==========

function buyBadge(string $userId, string $badgeId): array {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Get badge details
        $badgeStmt = $pdo->prepare("SELECT * FROM badges WHERE badge_id = ? AND is_active = 1");
        $badgeStmt->execute([$badgeId]);
        $badge = $badgeStmt->fetch();

        if (!$badge) {
            return ['success' => false, 'error' => 'Badge not found or inactive'];
        }

        $price = floatval($badge['coin_price']);

        // Check if already owned
        $ownedStmt = $pdo->prepare("SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?");
        $ownedStmt->execute([$userId, $badgeId]);
        if ($ownedStmt->fetch()) {
            return ['success' => false, 'error' => 'You already own this badge'];
        }

        // Check balance (if priced)
        if ($price > 0) {
            $balStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
            $balStmt->execute([$userId]);
            $balance = (float)$balStmt->fetchColumn();

            if ($balance < $price) {
                return ['success' => false, 'error' => "Need {$price} coins. You have {$balance}"];
            }

            // Deduct
            $newBalance = $balance - $price;
            $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
                ->execute([$newBalance, $userId]);

            // Record transaction
            $pdo->prepare("
                INSERT INTO coin_transactions 
                (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
                VALUES (?, ?, 'badge_purchase', ?, ?, ?, ?, NOW())
            ")->execute([
                generateUUID(), $userId, $badgeId, -$price, $newBalance,
                "Purchased badge: {$badge['name']}"
            ]);

            // Admin-created digital goods route the full sale value to treasury.
            collectToTreasury(
                'badge_sale',
                $userId,
                $price,
                'badge',
                $badgeId,
                null,
                $price,
                100,
                "Badge sale credited to treasury: {$badge['name']} ({$price} coins)"
            );
        }

        // Add badge to user
        $pdo->prepare("
            INSERT INTO user_badges (user_id, badge_id, acquired_at)
            VALUES (?, ?, NOW())
        ")->execute([$userId, $badgeId]);

        $pdo->commit();

        return [
            'success' => true,
            'message' => "Badge '{$badge['name']}' acquired!",
            'badge_name' => $badge['name'],
            'badge_icon' => $badge['icon_url'],
            'cost' => $price
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('buyBadge error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Purchase failed: ' . $e->getMessage()];
    }
}

// ========== TOKEN SHOP ==========

function buyToken(string $userId, string $tokenId, int $quantity = 1): array {
    global $pdo;

    if ($quantity < 1) $quantity = 1;

    try {
        $pdo->beginTransaction();

        // Get token details
        $tokenStmt = $pdo->prepare("SELECT * FROM akku_tokens WHERE token_id = ? AND is_active = 1");
        $tokenStmt->execute([$tokenId]);
        $token = $tokenStmt->fetch();

        if (!$token) {
            return ['success' => false, 'error' => 'Token not found or inactive'];
        }

        $unitPrice = floatval($token['coin_value']);
        $totalCost = $unitPrice * $quantity;

        // Check balance
        $balStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
        $balStmt->execute([$userId]);
        $balance = (float)$balStmt->fetchColumn();

        if ($balance < $totalCost) {
            return ['success' => false, 'error' => "Need {$totalCost} coins. You have {$balance}"];
        }

        // Deduct coins
        $newBalance = $balance - $totalCost;
        $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
            ->execute([$newBalance, $userId]);

        // Record transaction
        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
            VALUES (?, ?, 'purchase', ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $userId, $tokenId, -$totalCost, $newBalance,
            "Purchased {$quantity}x {$token['name']} ({$token['symbol']})"
        ]);

        // Add tokens to user inventory
        $pdo->prepare("
            INSERT INTO user_tokens (user_id, token_id, quantity, acquired_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE quantity = quantity + ?, acquired_at = NOW()
        ")->execute([$userId, $tokenId, $quantity, $quantity]);

        // CollectionBox fee (5%)
        $fee = round($totalCost * 0.05, 4);
        if ($fee > 0) {
            collectToTreasury(
                'token_purchase',
                $userId,
                $fee,
                'token',
                $tokenId,
                null,
                $totalCost,
                5,
                "Token purchase fee: {$quantity}x {$token['name']} ({$totalCost} coins)"
            );
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => "Bought {$quantity}x {$token['name']} ({$token['symbol']})!",
            'token_name' => $token['name'],
            'token_symbol' => $token['symbol'],
            'quantity' => $quantity,
            'total_cost' => $totalCost,
            'new_balance' => $newBalance
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('buyToken error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Purchase failed: ' . $e->getMessage()];
    }
}

// ========== POST CREATION ==========
// ========== POST CREATION (CHARGED, NOT REWARDED) ==========

// Change this function:
// Change this function:
function createPost($userId, $content, $mediaUrls = []) {
    global $pdo;

    $content = trim((string)$content);
    $postCost = getCommissionSetting('post_creation_cost');

    if ($content === '') {
        return ['success' => false, 'error' => 'Post content cannot be empty'];
    }
    
    try {
        $pdo->beginTransaction();
        
        // Charge the fixed creation cost up front.
        $stmt = $pdo->prepare("
            UPDATE users 
            SET coin_balance = coin_balance - ? 
            WHERE user_id = ? AND coin_balance >= ?
        ");
        $stmt->execute([$postCost, $userId, $postCost]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Insufficient coins");
        }
        
        // Insert post
        $postId = generateUUID();
        $stmt = $pdo->prepare("
            INSERT INTO user_posts (post_id, user_id, content, media_urls, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$postId, $userId, $content, json_encode($mediaUrls)]);
        
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, amount, balance_after, description, created_at) 
            VALUES (?, ?, 'post_creation', ?, 
                   (SELECT coin_balance FROM users WHERE user_id = ?), 
                   'Post creation fee', NOW())
        ");
        $stmt->execute([generateUUID(), $userId, -$postCost, $userId]);

        collectToTreasury(
            'post_creation',
            $userId,
            $postCost,
            'post',
            $postId,
            null,
            $postCost,
            100,
            "Post creation fee collected for post #{$postId}"
        );
        
        $pdo->commit();
        return [
            'success' => true,
            'post_id' => $postId,
            'cost' => $postCost
        ];
        
    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Post creation error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}


// ========== TOKEN CONVERSION ==========

function convertTokenToCoins(string $userId, string $tokenId, int $quantity, ?string $adminId = null): array {
    global $pdo;

    if ($quantity < 1) $quantity = 1;

    try {
        $pdo->beginTransaction();

        // Get token details
        $tokenStmt = $pdo->prepare("SELECT * FROM akku_tokens WHERE token_id = ? AND is_active = 1");
        $tokenStmt->execute([$tokenId]);
        $token = $tokenStmt->fetch();

        if (!$token) {
            return ['success' => false, 'error' => 'Token not found or inactive'];
        }

        $coinValue = floatval($token['coin_value']);
        $totalCoinValue = $coinValue * $quantity;

        // Check user has enough tokens
        $ownStmt = $pdo->prepare("SELECT quantity FROM user_tokens WHERE user_id = ? AND token_id = ?");
        $ownStmt->execute([$userId, $tokenId]);
        $ownedQty = (int)$ownStmt->fetchColumn();

        if ($ownedQty < $quantity) {
            return ['success' => false, 'error' => "You own {$ownedQty} tokens. Need {$quantity}"];
        }

        // Calculate commission
        $commissionRate = getCommissionSetting('token_conversion_commission');
        $commission = round($totalCoinValue * ($commissionRate / 100), 4);
        $netToUser = $totalCoinValue - $commission;

        // Deduct tokens from user
        if ($ownedQty == $quantity) {
            $pdo->prepare("DELETE FROM user_tokens WHERE user_id = ? AND token_id = ?")
                ->execute([$userId, $tokenId]);
        } else {
            $pdo->prepare("UPDATE user_tokens SET quantity = quantity - ? WHERE user_id = ? AND token_id = ?")
                ->execute([$quantity, $userId, $tokenId]);
        }

        // Credit net coins to user
        $pdo->prepare("UPDATE users SET coin_balance = coin_balance + ? WHERE user_id = ?")
            ->execute([$netToUser, $userId]);

        // Get new balance
        $balStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ?");
        $balStmt->execute([$userId]);
        $newBalance = (float)$balStmt->fetchColumn();

        // Record conversion transaction
        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
            VALUES (?, ?, 'token_conversion', ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $userId, $tokenId, $netToUser, $newBalance,
            "Converted {$quantity}x {$token['name']} to {$netToUser} coins ({$commissionRate}% fee: {$commission})"
        ]);

        // Record in token_conversions table
        $pdo->prepare("
            INSERT INTO token_conversions 
            (conversion_id, user_id, token_id, tokens_converted, coin_value, commission_rate, commission_coins, net_coins_received, admin_user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $userId, $tokenId, $quantity, $totalCoinValue,
            $commissionRate, $commission, $netToUser, $adminId
        ]);

        // Collect commission to CollectionBox
        if ($commission > 0) {
            collectToTreasury(
                'token_conversion',
                $userId,
                $commission,
                'token',
                $tokenId,
                null,
                $totalCoinValue,
                $commissionRate,
                "Token conversion commission: {$quantity}x {$token['name']} = {$totalCoinValue} coins"
            );
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => "Converted to {$netToUser} coins (fee: {$commission})",
            'gross_value' => $totalCoinValue,
            'commission' => $commission,
            'commission_rate' => $commissionRate,
            'net_to_user' => $netToUser,
            'new_balance' => $newBalance
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('convertTokenToCoins error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Conversion failed: ' . $e->getMessage()];
    }
}

// ========== UPI PAYMENT SYSTEM ==========

function createPaymentRequest(string $userId, int $packageId): array {
    global $pdo;

    try {
        // Get package details
        $pkgStmt = $pdo->prepare("SELECT * FROM coin_packages WHERE package_id = ? AND is_active = 1");
        $pkgStmt->execute([$packageId]);
        $package = $pkgStmt->fetch();

        if (!$package) {
            return ['success' => false, 'error' => 'Package not found or inactive'];
        }

        // Create payment record
        $paymentId = generateUUID();
        $pdo->prepare("
            INSERT INTO upi_payments 
            (payment_id, user_id, package_id, price_inr, coin_amount, bonus_coins, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ")->execute([
            $paymentId,
            $userId,
            $packageId,
            $package['price_inr'],
            $package['coin_amount'],
            $package['bonus_coins']
        ]);

        // Get admin UPI ID from config
        $upiStmt = $pdo->query("SELECT setting_value FROM config WHERE setting_key = 'admin_upi_id' LIMIT 1");
        $upiId = $upiStmt->fetchColumn() ?: 'admin@upi';

        // Generate UPI payment URL
        $amount = $package['price_inr'];
        $note = "AkkuApps Coins: {$package['name']}";
        $upiUrl = "upi://pay?pa={$upiId}&pn=AkkuApps&am={$amount}&cu=INR&tn=" . urlencode($note);

        return [
            'success' => true,
            'payment_id' => $paymentId,
            'upi_url' => $upiUrl,
            'upi_id' => $upiId,
            'amount_inr' => $amount,
            'coins' => $package['coin_amount'],
            'bonus' => $package['bonus_coins'],
            'total_coins' => $package['coin_amount'] + $package['bonus_coins'],
            'message' => "Pay ₹{$amount} to {$upiId} via any UPI app. Then return here and submit UTR."
        ];

    } catch (Exception $e) {
        error_log('createPaymentRequest error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Payment setup failed: ' . $e->getMessage()];
    }
}

// ========== CREATOR SUBSCRIPTION ==========

function subscribeToCreator(string $subscriberId, string $creatorId, float $price): array {
    global $pdo;

    if ($subscriberId === $creatorId) {
        return ['success' => false, 'error' => 'Cannot subscribe to yourself'];
    }

    try {
        $pdo->beginTransaction();

        // Verify creator exists
        $creatorStmt = $pdo->prepare("SELECT user_id, name FROM users WHERE user_id = ?");
        $creatorStmt->execute([$creatorId]);
        $creator = $creatorStmt->fetch();

        if (!$creator) {
            return ['success' => false, 'error' => 'Creator not found'];
        }

        // Check existing subscription
        $subStmt = $pdo->prepare("
            SELECT subscription_id FROM creator_subscriptions 
            WHERE subscriber_id = ? AND creator_id = ? AND status = 'active' AND expires_at > NOW()
        ");
        $subStmt->execute([$subscriberId, $creatorId]);
        if ($subStmt->fetch()) {
            return ['success' => false, 'error' => 'Already subscribed'];
        }

        // Check subscriber balance
        $balStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
        $balStmt->execute([$subscriberId]);
        $balance = (float)$balStmt->fetchColumn();

        if ($balance < $price) {
            return ['success' => false, 'error' => "Need {$price} coins. You have {$balance}"];
        }

        // Deduct from subscriber
        $newBalance = $balance - $price;
        $pdo->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?")
            ->execute([$newBalance, $subscriberId]);

        // Record subscriber transaction
        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
            VALUES (?, ?, 'subscription', ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $subscriberId, $creatorId, -$price, $newBalance,
            "Subscribed to {$creator['name']}"
        ]);

        // Credit creator (90%)
        $creatorShare = round($price * 0.90, 4);
        $platformFee = $price - $creatorShare;
        $reportedPlatformFee = $platformFee;

        if ($platformFee > 0) {
            collectToTreasury(
                'subscription',
                $subscriberId,
                $platformFee,
                'creator',
                $creatorId,
                $creatorId,
                $price,
                10,
                "Subscription platform fee: {$subscriberId} -> {$creator['name']} ({$price} coins)"
            );
            $platformFee = 0;
        }

        $pdo->prepare("UPDATE users SET coin_balance = coin_balance + ? WHERE user_id = ?")
            ->execute([$creatorShare, $creatorId]);

        // Record creator earnings
        $creatorBalStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ?");
        $creatorBalStmt->execute([$creatorId]);
        $creatorNewBalance = (float)$creatorBalStmt->fetchColumn();

        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
            VALUES (?, ?, 'subscription_earned', ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $creatorId, $subscriberId, $creatorShare, $creatorNewBalance,
            "Subscription earnings from subscriber #{$subscriberId}"
        ]);

        // Create subscription record (30 days)
        $subId = generateUUID();
        $pdo->prepare("
            INSERT INTO creator_subscriptions 
            (subscription_id, subscriber_id, creator_id, price_paid, status, expires_at, created_at)
            VALUES (?, ?, ?, ?, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
        ")->execute([$subId, $subscriberId, $creatorId, $price]);

        // CollectionBox platform fee
        if ($platformFee > 0) {
            $adminStmt = $pdo->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
            $adminId = (int)($adminStmt->fetchColumn() ?: 0);
            if ($adminId) {
                $treasury = new AkkuCollectionBox($pdo, $adminId);
                $treasury->collect(
                    'subscription',
                    (int)$subscriberId,
                    $platformFee,
                    'subscription',
                    (int)$subId,
                    (int)$creatorId,
                    $price,
                    10,
                    "Subscription platform fee: {$subscriberId} → {$creator['name']} ({$price} coins)"
                );
            }
        }

        // Notify creator
        createNotification($creatorId, 'subscription', 'New Subscriber!', "{$subscriberId} subscribed for 30 days", $subId, 'subscription');

        $pdo->commit();

        return [
            'success' => true,
            'message' => "Subscribed to {$creator['name']}!",
            'subscription_id' => $subId,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'creator_earned' => $creatorShare,
            'platform_fee' => $reportedPlatformFee,
            'new_balance' => $newBalance
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('subscribeToCreator error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Subscription failed: ' . $e->getMessage()];
    }
}

// ========== POST VIEW SYSTEM ==========

function recordPostView(string $postId, string $viewerId = null, string $viewerIp = null): array {
    global $pdo;

    $reward = getCommissionSetting('post_view_reward'); // default: 0.1 coins

    try {
        $pdo->beginTransaction();

        // Get post owner
        $postStmt = $pdo->prepare("SELECT user_id, view_count FROM user_posts WHERE post_id = ? AND status = 'active'");
        $postStmt->execute([$postId]);
        $post = $postStmt->fetch();

        if (!$post) {
            return ['success' => false, 'error' => 'Post not found'];
        }

        $postOwner = $post['user_id'];

        // FIX Priority 4: Skip reward if viewer is the post owner (self-view farming exploit)
        if ($viewerId && $viewerId === $postOwner) {
            // Still record the view count but don't give reward
            $pdo->prepare("
                INSERT INTO post_views (post_id, viewer_id, viewer_ip, created_at)
                VALUES (?, ?, ?, NOW())
            ")->execute([$postId, $viewerId, $viewerIp]);
            $pdo->prepare("UPDATE user_posts SET view_count = view_count + 1 WHERE post_id = ?")
                ->execute([$postId]);
            $pdo->commit();
            return ['success' => true, 'message' => 'View recorded (owner view - no reward)', 'rewarded' => false];
        }

        // Check for unique view (same viewer+ip within 24 hours)
        if ($viewerId || $viewerIp) {
            $checkStmt = $pdo->prepare("
                SELECT view_id FROM post_views 
                WHERE post_id = ? 
                AND (viewer_id = ? OR viewer_ip = ?)
                AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                LIMIT 1
            ");
            $checkStmt->execute([$postId, $viewerId ?: '', $viewerIp ?: '']);
            if ($checkStmt->fetch()) {
                // Not unique, just increment view count without reward
                $pdo->prepare("UPDATE user_posts SET view_count = view_count + 1 WHERE post_id = ?")
                    ->execute([$postId]);
                $pdo->commit();
                return ['success' => true, 'message' => 'View recorded (no reward)', 'rewarded' => false];
            }
        }

        // Record unique view
        $pdo->prepare("
            INSERT INTO post_views (post_id, viewer_id, viewer_ip, created_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([$postId, $viewerId, $viewerIp]);

        // Increment view count
        $pdo->prepare("UPDATE user_posts SET view_count = view_count + 1, coins_earned = coins_earned + ? WHERE post_id = ?")
            ->execute([$reward, $postId]);

        // Reward post owner
        $pdo->prepare("UPDATE users SET coin_balance = coin_balance + ? WHERE user_id = ?")
            ->execute([$reward, $postOwner]);

        // Record transaction
        $ownerBalStmt = $pdo->prepare("SELECT coin_balance FROM users WHERE user_id = ?");
        $ownerBalStmt->execute([$postOwner]);
        $ownerNewBalance = (float)$ownerBalStmt->fetchColumn();

        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, reference_id, amount, balance_after, description, created_at)
            VALUES (?, ?, 'post_view', ?, ?, ?, ?, NOW())
        ")->execute([
            generateUUID(), $postOwner, $postId, $reward, $ownerNewBalance,
            "View reward for post #{$postId}"
        ]);

        // CollectionBox fee on views (10%)
        $fee = round($reward * 0.10, 4);
        if ($fee > 0) {
            collectToTreasury(
                'post_view',
                $viewerId,
                $fee,
                'post',
                $postId,
                $postOwner,
                $reward,
                10,
                "View reward fee on post #{$postId}"
            );
        }

        $pdo->commit();

        return [
            'success' => true,
            'message' => "View rewarded! +{$reward} 🪙 to owner",
            'rewarded' => true,
            'owner_earned' => $reward,
            'view_count' => $post['view_count'] + 1
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log('recordPostView error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'View recording failed'];
    }

}

// ========== AKKUCOLLECTIONBOX TREASURY ==========

class AkkuCollectionBox {
    private PDO $db;
    private $adminId;

    private const GIFT_SEND_FEE = 10;
    private const CONVERSION_RATE = 10;

    public function __construct(PDO $db, $adminId = null) {
        $this->db = $db;
        $this->adminId = $adminId;
    }

    public function getBalance(): float {
        try {
            $stmt = $this->db->query("
                SELECT balance_after 
                FROM akku_collection_box 
                ORDER BY id DESC LIMIT 1
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (float)$row['balance_after'] : 0.00;
        } catch (Exception $e) {
            return 0.00;
        }
    }

    public function collect(
        string $type,
        $sourceUserId,
        float $feeAmount,
        string $entityType = null,
        $entityId = null,
        $targetUserId = null,
        float $grossAmount = 0,
        float $feeRate = 0,
        string $description = ''
    ): int {
        $currentBalance = $this->getBalance();
        $newBalance = $currentBalance + $feeAmount;

        try {
            $stmt = $this->db->prepare("
                INSERT INTO akku_collection_box 
                (transaction_type, source_user_id, target_user_id, source_entity_type, 
                 source_entity_id, gross_amount, fee_amount, fee_rate, balance_after, 
                 description, admin_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $type, $sourceUserId, $targetUserId, $entityType, $entityId,
                $grossAmount, $feeAmount, $feeRate, $newBalance, $description, $this->adminId
            ]);

            return (int)$this->db->lastInsertId();
        } catch (Exception $e) {
            error_log('CollectionBox collect error: ' . $e->getMessage());
            return 0;
        }
    }

    public function processGiftSend(
        int $senderId,
        int $receiverId,
        int $giftId,
        float $giftValue,
        string $giftName
    ): array {
        try {
            $this->db->beginTransaction();

            // Verify sender owns the gift
            $stmt = $this->db->prepare("
                SELECT inventory_id FROM user_inventory 
                WHERE user_id = ? AND item_id = ? AND item_type = 'gift' AND quantity > 0
                LIMIT 1
            ");
            $stmt->execute([$senderId, $giftId]);
            if (!$stmt->fetch()) {
                throw new Exception('Sender does not own this gift');
            }

            // Check sender has 10 coins for fee
            $stmt = $this->db->prepare("SELECT coin_balance FROM users WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$senderId]);
            $senderCoins = (float)$stmt->fetchColumn();

            if ($senderCoins < self::GIFT_SEND_FEE) {
                throw new Exception("Insufficient coins. Need 10 coins fee. You have: {$senderCoins}");
            }

            // Deduct gift from sender inventory
            $stmt = $this->db->prepare("
                UPDATE user_inventory 
                SET quantity = quantity - 1 
                WHERE user_id = ? AND item_id = ? AND item_type = 'gift'
            ");
            $stmt->execute([$senderId, $giftId]);

            // Add gift to receiver inventory
            $stmt = $this->db->prepare("
                INSERT INTO user_inventory (user_id, item_id, item_type, quantity, acquired_at)
                VALUES (?, ?, 'gift', 1, NOW())
                ON DUPLICATE KEY UPDATE quantity = quantity + 1, acquired_at = NOW()
            ");
            $stmt->execute([$receiverId, $giftId]);

            // Deduct 10 coins fee from sender wallet
            $newSenderBalance = $senderCoins - self::GIFT_SEND_FEE;
            $stmt = $this->db->prepare("UPDATE users SET coin_balance = ? WHERE user_id = ?");
            $stmt->execute([$newSenderBalance, $senderId]);

            // Record sender's coin transaction
            $stmt = $this->db->prepare("
                INSERT INTO coin_transactions 
                (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
                VALUES (?, ?, 'gift_send_fee', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                generateUUID(),
                $senderId,
                -self::GIFT_SEND_FEE,
                $newSenderBalance,
                "Fee for sending {$giftName} to User #{$receiverId}"
            ]);
            $senderTxnId = $this->db->lastInsertId();

            // Collect to AkkuCollectionBox
            $boxTxnId = $this->collect(
                'gift_sending',
                $senderId,
                self::GIFT_SEND_FEE,
                'gift',
                $giftId,
                $receiverId,
                $giftValue,
                0,
                "Gift send fee: {$giftName} (value {$giftValue}) from #{$senderId} to #{$receiverId}"
            );

            // Link CollectionBox txn to coin transaction (graceful fallback)
            if ($boxTxnId > 0) {
                try {
                    $stmt = $this->db->prepare("
                        UPDATE coin_transactions 
                        SET collection_box_fee = ?, collection_box_txn_id = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([self::GIFT_SEND_FEE, $boxTxnId, $senderTxnId]);
                } catch (Exception $e) {}
            }

            // Notify receiver
            createNotification((string)$receiverId, 'gift', 'New Gift!', "You received {$giftName}!", (string)$giftId, 'gift');

            $this->db->commit();

            return [
                'success' => true,
                'sender_cost' => self::GIFT_SEND_FEE,
                'gift_value_transferred' => $giftValue,
                'collection_box_txn_id' => $boxTxnId,
                'sender_new_balance' => $newSenderBalance
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function processGiftConversion(
        int $userId,
        int $giftId,
        float $giftValue,
        string $giftName
    ): array {
        try {
            $commission = round($giftValue * (self::CONVERSION_RATE / 100), 4);
            $netToUser = $giftValue - $commission;

            $this->db->beginTransaction();

            // Remove gift from inventory
            $stmt = $this->db->prepare("
                UPDATE user_inventory 
                SET quantity = quantity - 1 
                WHERE user_id = ? AND item_id = ? AND item_type = 'gift' AND quantity > 0
            ");
            $stmt->execute([$userId, $giftId]);
            if ($stmt->rowCount() === 0) {
                throw new Exception('Gift not found in inventory');
            }

            // Credit net coins to user
            $stmt = $this->db->prepare("UPDATE users SET coin_balance = coin_balance + ? WHERE user_id = ?");
            $stmt->execute([$netToUser, $userId]);

            // Record user coin transaction
            $stmt = $this->db->prepare("
                INSERT INTO coin_transactions 
                (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
                VALUES (?, ?, 'gift_conversion', ?, (SELECT coin_balance FROM users WHERE user_id = ?), ?, NOW())
            ");
            $stmt->execute([
                generateUUID(),
                $userId, $netToUser, $userId,
                "Converted {$giftName} to {$netToUser} coins (10% fee: {$commission})"
            ]);
            $userTxnId = $this->db->lastInsertId();

            // Collect commission to CollectionBox
            $boxTxnId = $this->collect(
                'gift_conversion',
                $userId,
                $commission,
                'gift',
                $giftId,
                null,
                $giftValue,
                self::CONVERSION_RATE,
                "Commission on {$giftName} conversion (value {$giftValue})"
            );

            // Link transactions
            if ($boxTxnId > 0) {
                try {
                    $stmt = $this->db->prepare("
                        UPDATE coin_transactions 
                        SET collection_box_fee = ?, collection_box_txn_id = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$commission, $boxTxnId, $userTxnId]);
                } catch (Exception $e) {}
            }

            $this->db->commit();

            return [
                'success' => true,
                'gross_value' => $giftValue,
                'commission' => $commission,
                'net_to_user' => $netToUser,
                'collection_box_txn_id' => $boxTxnId
            ];

        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getTreasuryReport(string $period = 'all'): array {
        $where = $period === 'all' ? '' : "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 {$period})";

        try {
            $stmt = $this->db->query("
                SELECT 
                    transaction_type,
                    COUNT(*) as count,
                    SUM(fee_amount) as total,
                    AVG(fee_amount) as average
                FROM akku_collection_box
                {$where}
                GROUP BY transaction_type
                ORDER BY total DESC
            ");

            $breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'current_balance' => $this->getBalance(),
                'period' => $period,
                'breakdown_by_type' => $breakdown,
                'total_transactions' => array_sum(array_column($breakdown, 'count')),
                'total_collected' => array_sum(array_column($breakdown, 'total'))
            ];
        } catch (Exception $e) {
            return [
                'current_balance' => 0,
                'period' => $period,
                'breakdown_by_type' => [],
                'total_transactions' => 0,
                'total_collected' => 0
            ];
        }
    }
}
