<?php
if (!defined('AKKUAPPS_LOADED')) {
    exit('Direct access not allowed');
}

function marketplaceSlugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value);
    $value = trim((string) $value, '-');
    return $value !== '' ? $value : 'item';
}

function marketplaceGenerateUniqueSlug(string $table, string $column, string $source, ?int $excludeId = null, string $idColumn = 'id'): string
{
    global $pdo;
    $baseSlug = marketplaceSlugify($source);
    $candidate = $baseSlug;
    $counter = 1;

    while (true) {
        $sql = "SELECT {$idColumn} FROM {$table} WHERE {$column} = ?";
        $params = [$candidate];
        if ($excludeId !== null) {
            $sql .= " AND {$idColumn} <> ?";
            $params[] = $excludeId;
        }
        $sql .= " LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $candidate;
        }

        $counter++;
        $candidate = $baseSlug . '-' . $counter;
    }
}

function marketplaceGetNextNumericId(string $table, string $idColumn): int
{
    global $pdo;
    $stmt = $pdo->query("SELECT COALESCE(MAX({$idColumn}), 0) + 1 AS next_id FROM {$table}");
    return (int) $stmt->fetchColumn();
}

function marketplaceFetchScalarById(string $table, string $idColumn, int $id, string $valueColumn): string
{
    global $pdo;
    if ($id <= 0) {
        return '';
    }
    $stmt = $pdo->prepare("SELECT {$valueColumn} FROM {$table} WHERE {$idColumn} = ? LIMIT 1");
    $stmt->execute([$id]);
    $value = $stmt->fetchColumn();
    return is_string($value) ? $value : '';
}

function marketplaceGenerateSku(string $name, ?int $brandId = null, ?int $categoryId = null, ?int $excludeProductId = null): string
{
    global $pdo;
    $brandName = marketplaceFetchScalarById('cs_brands', 'brand_id', (int) $brandId, 'name');
    $categoryName = marketplaceFetchScalarById('cs_categories', 'category_id', (int) $categoryId, 'name');

    $brandCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $brandName), 0, 3));
    $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $categoryName), 0, 3));
    $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4));

    if ($brandCode === '') {
        $brandCode = 'GEN';
    }
    if ($categoryCode === '') {
        $categoryCode = 'CAT';
    }
    if ($nameCode === '') {
        $nameCode = 'ITEM';
    }

    $prefix = $brandCode . '-' . $categoryCode . '-' . $nameCode;
    $sql = "SELECT sku FROM cs_products WHERE sku LIKE ?";
    $params = [$prefix . '-%'];
    if ($excludeProductId !== null) {
        $sql .= " AND product_id <> ?";
        $params[] = $excludeProductId;
    }
    $sql .= " ORDER BY sku DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $lastSku = $stmt->fetchColumn();
    $nextNumber = 1;
    if ($lastSku && preg_match('/-(\d{3,})$/', (string) $lastSku, $matches)) {
        $nextNumber = ((int) $matches[1]) + 1;
    }

    return sprintf('%s-%03d', $prefix, $nextNumber);
}

function marketplaceNormalizeMediaUrls(string $raw): array
{
    $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
    $urls = [];
    foreach ($parts as $part) {
        $url = trim($part);
        if ($url === '') {
            continue;
        }
        if (!in_array($url, $urls, true)) {
            $urls[] = $url;
        }
    }
    return $urls;
}

function marketplaceExtractMediaFromSpecifications(array $specifications): array
{
    $media = [
        'video_url' => '',
        'gallery_urls' => [],
    ];
    if (!empty($specifications['__media']) && is_array($specifications['__media'])) {
        $media['video_url'] = trim((string) ($specifications['__media']['video_url'] ?? ''));
        $gallery = $specifications['__media']['gallery_urls'] ?? [];
        if (is_array($gallery)) {
            foreach ($gallery as $url) {
                $url = trim((string) $url);
                if ($url !== '' && !in_array($url, $media['gallery_urls'], true)) {
                    $media['gallery_urls'][] = $url;
                }
            }
        }
    }
    return $media;
}

function marketplaceGetVisibleSpecifications(array $specifications): array
{
    $visible = [];
    foreach ($specifications as $key => $value) {
        if (strpos((string) $key, '__') === 0) {
            continue;
        }
        $visible[$key] = $value;
    }
    return $visible;
}

function marketplaceSyncProductImages(int $productId, string $primaryImageUrl, array $galleryUrls, string $altText): void
{
    global $pdo;
    $pdo->prepare("DELETE FROM cs_product_images WHERE product_id = ?")->execute([$productId]);

    $orderedUrls = [];
    $primaryImageUrl = trim($primaryImageUrl);
    if ($primaryImageUrl !== '') {
        $orderedUrls[] = $primaryImageUrl;
    }
    foreach ($galleryUrls as $url) {
        $url = trim((string) $url);
        if ($url !== '' && !in_array($url, $orderedUrls, true)) {
            $orderedUrls[] = $url;
        }
    }

    foreach ($orderedUrls as $index => $url) {
        $imageId = marketplaceGetNextNumericId('cs_product_images', 'image_id');
        $pdo->prepare("
            INSERT INTO cs_product_images (image_id, product_id, image_url, thumbnail_url, alt_text, sort_order, is_primary, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $imageId,
            $productId,
            $url,
            $url,
            $altText,
            $index,
            $index === 0 ? 1 : 0,
        ]);
    }
}

function marketplaceGetVideoEmbedUrl(string $videoUrl): string
{
    $videoUrl = trim($videoUrl);
    if ($videoUrl === '') {
        return '';
    }

    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $videoUrl, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[1];
    }

    if (preg_match('~vimeo\.com/(\d+)~i', $videoUrl, $matches)) {
        return 'https://player.vimeo.com/video/' . $matches[1];
    }

    return '';
}

function marketplaceGenerateRunningCode(string $prefix, string $table, string $column): string
{
    global $pdo;
    $datePart = date('Ymd');
    $like = $prefix . $datePart . '-%';
    $stmt = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY {$column} DESC LIMIT 1");
    $stmt->execute([$like]);
    $lastCode = $stmt->fetchColumn();
    $nextNumber = 1;

    if ($lastCode && preg_match('/-(\d+)$/', (string) $lastCode, $matches)) {
        $nextNumber = ((int) $matches[1]) + 1;
    }

    return sprintf('%s%s-%04d', $prefix, $datePart, $nextNumber);
}

function marketplaceGenerateInvoiceNumber(): string
{
    return marketplaceGenerateRunningCode('INV', 'cs_invoices', 'invoice_number');
}

function marketplaceGeneratePlaceholderPhone(array $user): string
{
    global $pdo;
    $base = 'AUTO-' . strtoupper(substr(sha1((string) ($user['user_id'] ?? uniqid('', true))), 0, 12));
    $candidate = $base;
    $suffix = 1;

    while (true) {
        $stmt = $pdo->prepare("SELECT customer_id FROM cs_customers WHERE phone = ? LIMIT 1");
        $stmt->execute([$candidate]);
        if (!$stmt->fetch()) {
            return $candidate;
        }
        $suffix++;
        $candidate = substr($base, 0, 16) . '-' . $suffix;
    }
}

function marketplaceGenerateTicketNumber(): string
{
    return marketplaceGenerateRunningCode('SRV', 'cs_service_tickets', 'ticket_number');
}

function marketplaceGetBrands(bool $activeOnly = true): array
{
    global $pdo;
    $sql = "SELECT * FROM cs_brands";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name ASC";
    return $pdo->query($sql)->fetchAll();
}

function marketplaceGetCategories(bool $activeOnly = true): array
{
    global $pdo;
    $sql = "SELECT * FROM cs_categories";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC, name ASC";
    return $pdo->query($sql)->fetchAll();
}

function marketplaceGetProductById(int $productId): ?array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT
            p.*,
            b.name AS brand_name,
            c.name AS category_name,
            s.stock_quantity AS current_stock,
            s.stock_status,
            img.image_url AS primary_image,
            img.thumbnail_url AS primary_thumbnail
        FROM cs_products p
        LEFT JOIN cs_brands b ON p.brand_id = b.brand_id
        LEFT JOIN cs_categories c ON p.category_id = c.category_id
        LEFT JOIN cs_vw_product_stock s ON p.product_id = s.product_id
        LEFT JOIN cs_product_images img
            ON p.product_id = img.product_id
           AND img.is_primary = 1
        WHERE p.product_id = ?
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        return null;
    }

    $imagesStmt = $pdo->prepare("
        SELECT *
        FROM cs_product_images
        WHERE product_id = ?
        ORDER BY is_primary DESC, sort_order ASC, image_id ASC
    ");
    $imagesStmt->execute([$productId]);
    $product['images'] = $imagesStmt->fetchAll();
    $product['specifications_array'] = [];
    $product['visible_specifications'] = [];
    $product['media_video_url'] = '';
    $product['media_gallery_urls'] = [];
    if (!empty($product['specifications'])) {
        $decoded = json_decode((string) $product['specifications'], true);
        if (is_array($decoded)) {
            $product['specifications_array'] = $decoded;
            $product['visible_specifications'] = marketplaceGetVisibleSpecifications($decoded);
            $media = marketplaceExtractMediaFromSpecifications($decoded);
            $product['media_video_url'] = $media['video_url'];
            $product['media_gallery_urls'] = $media['gallery_urls'];
        }
    }
    if (empty($product['visible_specifications'])) {
        $product['visible_specifications'] = [];
    }

    return $product;
}

function marketplaceGetCatalogProducts(array $filters = [], int $limit = 0): array
{
    global $pdo;
    $sql = "
        SELECT
            p.*,
            b.name AS brand_name,
            c.name AS category_name,
            s.stock_quantity AS current_stock,
            s.stock_status,
            img.image_url AS primary_image
        FROM cs_products p
        LEFT JOIN cs_brands b ON p.brand_id = b.brand_id
        LEFT JOIN cs_categories c ON p.category_id = c.category_id
        LEFT JOIN cs_vw_product_stock s ON p.product_id = s.product_id
        LEFT JOIN cs_product_images img
            ON p.product_id = img.product_id
           AND img.is_primary = 1
        WHERE p.is_active = 1
    ";
    $params = [];

    if (!empty($filters['category_id'])) {
        $sql .= " AND p.category_id = ? ";
        $params[] = (int) $filters['category_id'];
    }

    if (!empty($filters['brand_id'])) {
        $sql .= " AND p.brand_id = ? ";
        $params[] = (int) $filters['brand_id'];
    }

    if (!empty($filters['featured'])) {
        $sql .= " AND p.is_featured = 1 ";
    }

    if (!empty($filters['stock'])) {
        if ($filters['stock'] === 'in_stock') {
            $sql .= " AND COALESCE(s.stock_quantity, p.stock_quantity, 0) > 0 ";
        } elseif ($filters['stock'] === 'low_stock') {
            $sql .= " AND COALESCE(s.stock_quantity, p.stock_quantity, 0) BETWEEN 1 AND COALESCE(p.low_stock_threshold, 5) ";
        } elseif ($filters['stock'] === 'out_of_stock') {
            $sql .= " AND COALESCE(s.stock_quantity, p.stock_quantity, 0) <= 0 ";
        }
    }

    if (!empty($filters['search'])) {
        $sql .= " AND (p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ? OR b.name LIKE ?) ";
        $search = '%' . trim((string) $filters['search']) . '%';
        array_push($params, $search, $search, $search, $search);
    }

    $sql .= " ORDER BY p.is_featured DESC, p.updated_at DESC, p.name ASC ";
    if ($limit > 0) {
        $sql .= " LIMIT " . (int) $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function marketplaceGetAdminStats(): array
{
    global $pdo;
    return [
        'active_products' => (int) $pdo->query("SELECT COUNT(*) FROM cs_products WHERE is_active = 1")->fetchColumn(),
        'featured_products' => (int) $pdo->query("SELECT COUNT(*) FROM cs_products WHERE is_active = 1 AND is_featured = 1")->fetchColumn(),
        'customers' => (int) $pdo->query("SELECT COUNT(*) FROM cs_customers WHERE is_active = 1")->fetchColumn(),
        'active_carts' => (int) $pdo->query("SELECT COUNT(*) FROM cs_carts WHERE status = 'active'")->fetchColumn(),
        'invoices' => (int) $pdo->query("SELECT COUNT(*) FROM cs_invoices")->fetchColumn(),
        'pending_invoices' => (int) $pdo->query("SELECT COUNT(*) FROM cs_invoices WHERE payment_status IN ('pending', 'partial', 'overdue')")->fetchColumn(),
        'service_tickets' => (int) $pdo->query("SELECT COUNT(*) FROM cs_service_tickets")->fetchColumn(),
        'low_stock' => (int) $pdo->query("SELECT COUNT(*) FROM cs_products WHERE is_active = 1 AND stock_quantity BETWEEN 1 AND COALESCE(low_stock_threshold, 5)")->fetchColumn(),
        'stock_value' => (float) $pdo->query("SELECT COALESCE(SUM(COALESCE(stock_quantity, 0) * COALESCE(cost_price, 0)), 0) FROM cs_products")->fetchColumn(),
    ];
}

function marketplaceEnsureCustomerForUser(array $user): int
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT customer_id FROM cs_customers WHERE user_id = ? LIMIT 1");
    $stmt->execute([$user['user_id']]);
    $customerId = $stmt->fetchColumn();
    if ($customerId) {
        return (int) $customerId;
    }

    if (!empty($user['email'])) {
        $stmt = $pdo->prepare("SELECT customer_id FROM cs_customers WHERE email = ? ORDER BY customer_id ASC LIMIT 1");
        $stmt->execute([trim((string) $user['email'])]);
        $customerId = $stmt->fetchColumn();
        if ($customerId) {
            $pdo->prepare("UPDATE cs_customers SET user_id = ?, updated_at = NOW() WHERE customer_id = ?")
                ->execute([$user['user_id'], $customerId]);
            return (int) $customerId;
        }
    }

    $customerId = marketplaceGetNextNumericId('cs_customers', 'customer_id');
    $stmt = $pdo->prepare("
        INSERT INTO cs_customers
        (customer_id, user_id, name, email, phone, billing_address, shipping_address, customer_type, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, '', '', 'regular', 1, NOW(), NOW())
    ");
    $stmt->execute([
        $customerId,
        $user['user_id'],
        trim((string) ($user['name'] ?? 'AkkuApps User')),
        trim((string) ($user['email'] ?? '')),
        marketplaceGeneratePlaceholderPhone($user)
    ]);

    return $customerId;
}

function marketplaceGetActiveCart(string $userId, ?int $customerId = null): ?array
{
    global $pdo;
    $params = [$userId];
    $sql = "SELECT * FROM cs_carts WHERE user_id = ? AND status = 'active'";
    if ($customerId !== null) {
        $sql .= " AND customer_id = ?";
        $params[] = $customerId;
    }
    $sql .= " ORDER BY updated_at DESC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cart = $stmt->fetch();
    return $cart ?: null;
}

function marketplaceCreateCart(string $userId, int $customerId): int
{
    global $pdo;
    $cartId = marketplaceGetNextNumericId('cs_carts', 'cart_id');
    $stmt = $pdo->prepare("
        INSERT INTO cs_carts (cart_id, customer_id, user_id, session_id, status, created_at, updated_at)
        VALUES (?, ?, ?, ?, 'active', NOW(), NOW())
    ");
    $stmt->execute([$cartId, $customerId, $userId, session_id()]);
    return $cartId;
}

function marketplaceGetOrCreateActiveCart(array $user): array
{
    $customerId = marketplaceEnsureCustomerForUser($user);
    $cart = marketplaceGetActiveCart($user['user_id'], $customerId);
    if ($cart) {
        return $cart;
    }
    $cartId = marketplaceCreateCart($user['user_id'], $customerId);
    return [
        'cart_id' => $cartId,
        'customer_id' => $customerId,
        'user_id' => $user['user_id'],
        'status' => 'active',
    ];
}

function marketplaceGetCartItems(int $cartId): array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT
            i.*,
            p.name AS product_name,
            p.sku,
            p.current_price,
            p.warranty_months,
            p.condition_type,
            p.is_active,
            c.name AS category_name,
            b.name AS brand_name,
            s.stock_quantity AS current_stock,
            s.stock_status,
            img.image_url AS product_image
        FROM cs_cart_items i
        INNER JOIN cs_products p ON i.product_id = p.product_id
        LEFT JOIN cs_categories c ON p.category_id = c.category_id
        LEFT JOIN cs_brands b ON p.brand_id = b.brand_id
        LEFT JOIN cs_vw_product_stock s ON p.product_id = s.product_id
        LEFT JOIN cs_product_images img
            ON p.product_id = img.product_id
           AND img.is_primary = 1
        WHERE i.cart_id = ?
        ORDER BY i.created_at ASC, i.item_id ASC
    ");
    $stmt->execute([$cartId]);
    return $stmt->fetchAll();
}

function marketplaceCalculateCartTotals(array $items): array
{
    $subtotal = 0.0;
    $discount = 0.0;
    foreach ($items as $item) {
        $lineSubtotal = ((float) $item['unit_price']) * ((int) $item['quantity']);
        $lineDiscount = (float) ($item['discount_amount'] ?? 0);
        $subtotal += $lineSubtotal;
        $discount += $lineDiscount;
    }
    $taxable = max(0, $subtotal - $discount);
    $taxRate = 18.0;
    $taxAmount = round(($taxable * $taxRate) / 100, 2);
    $grandTotal = round($taxable + $taxAmount, 2);

    return [
        'subtotal' => round($subtotal, 2),
        'discount_total' => round($discount, 2),
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'grand_total' => $grandTotal,
        'amount_due' => $grandTotal,
    ];
}

function marketplaceAddToCart(array $user, int $productId, int $quantity = 1): void
{
    global $pdo;
    $quantity = max(1, $quantity);
    $product = marketplaceGetProductById($productId);
    if (!$product || empty($product['is_active'])) {
        throw new RuntimeException('Selected product is not available.');
    }

    $stock = (int) ($product['current_stock'] ?? $product['stock_quantity'] ?? 0);
    if ($stock < $quantity) {
        throw new RuntimeException('Requested quantity is higher than current stock.');
    }

    $cart = marketplaceGetOrCreateActiveCart($user);
    $stmt = $pdo->prepare("SELECT * FROM cs_cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1");
    $stmt->execute([(int) $cart['cart_id'], $productId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $newQuantity = ((int) $existing['quantity']) + $quantity;
        if ($stock < $newQuantity) {
            throw new RuntimeException('Not enough stock to increase this cart line.');
        }
        $update = $pdo->prepare("
            UPDATE cs_cart_items
            SET quantity = ?, unit_price = ?, updated_at = NOW()
            WHERE item_id = ?
        ");
        $update->execute([$newQuantity, (float) $product['current_price'], $existing['item_id']]);
    } else {
        $itemId = marketplaceGetNextNumericId('cs_cart_items', 'item_id');
        $insert = $pdo->prepare("
            INSERT INTO cs_cart_items
            (item_id, cart_id, product_id, quantity, unit_price, discount_amount, notes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, 0.00, '', NOW(), NOW())
        ");
        $insert->execute([$itemId, (int) $cart['cart_id'], $productId, $quantity, (float) $product['current_price']]);
    }

    $pdo->prepare("UPDATE cs_carts SET updated_at = NOW() WHERE cart_id = ?")->execute([(int) $cart['cart_id']]);
}

function marketplaceUpdateCartItemQuantity(array $user, int $itemId, int $quantity): void
{
    global $pdo;
    $cart = marketplaceGetOrCreateActiveCart($user);
    $stmt = $pdo->prepare("
        SELECT i.*, p.stock_quantity
        FROM cs_cart_items i
        INNER JOIN cs_products p ON i.product_id = p.product_id
        WHERE i.item_id = ? AND i.cart_id = ?
        LIMIT 1
    ");
    $stmt->execute([$itemId, (int) $cart['cart_id']]);
    $item = $stmt->fetch();
    if (!$item) {
        throw new RuntimeException('Cart item not found.');
    }

    if ($quantity <= 0) {
        $pdo->prepare("DELETE FROM cs_cart_items WHERE item_id = ?")->execute([$itemId]);
    } else {
        $product = marketplaceGetProductById((int) $item['product_id']);
        $stock = (int) ($product['current_stock'] ?? $product['stock_quantity'] ?? 0);
        if ($quantity > $stock) {
            throw new RuntimeException('Requested quantity exceeds available stock.');
        }
        $pdo->prepare("UPDATE cs_cart_items SET quantity = ?, updated_at = NOW() WHERE item_id = ?")->execute([$quantity, $itemId]);
    }

    $pdo->prepare("UPDATE cs_carts SET updated_at = NOW() WHERE cart_id = ?")->execute([(int) $cart['cart_id']]);
}

function marketplaceListCustomers(int $limit = 100): array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM cs_customers ORDER BY updated_at DESC, customer_id DESC LIMIT " . (int) $limit);
    $stmt->execute();
    return $stmt->fetchAll();
}

function marketplaceListActiveCarts(int $limit = 50): array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT c.*, cu.name AS customer_name, cu.phone, u.name AS user_name
        FROM cs_carts c
        LEFT JOIN cs_customers cu ON c.customer_id = cu.customer_id
        LEFT JOIN users u ON c.user_id = u.user_id
        WHERE c.status = 'active'
        ORDER BY c.updated_at DESC, c.cart_id DESC
        LIMIT " . (int) $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function marketplaceListInvoices(int $limit = 50): array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT i.*, c.name AS customer_name, c.phone
        FROM cs_invoices i
        INNER JOIN cs_customers c ON i.customer_id = c.customer_id
        ORDER BY i.created_at DESC, i.invoice_id DESC
        LIMIT " . (int) $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function marketplaceListServiceTickets(int $limit = 50): array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT t.*, c.name AS customer_name, b.name AS brand_name
        FROM cs_service_tickets t
        INNER JOIN cs_customers c ON t.customer_id = c.customer_id
        LEFT JOIN cs_brands b ON t.brand_id = b.brand_id
        ORDER BY t.received_at DESC, t.ticket_id DESC
        LIMIT " . (int) $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function marketplaceListRecentMovements(int $limit = 100): array
{
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, p.name AS product_name, p.sku
        FROM cs_inventory_movements m
        INNER JOIN cs_products p ON m.product_id = p.product_id
        ORDER BY m.created_at DESC, m.movement_id DESC
        LIMIT " . (int) $limit
    );
    $stmt->execute();
    return $stmt->fetchAll();
}

function marketplaceCreateBrand(array $data): void
{
    global $pdo;
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Brand name is required.');
    }
    $brandId = marketplaceGetNextNumericId('cs_brands', 'brand_id');
    $slug = marketplaceGenerateUniqueSlug('cs_brands', 'slug', $name, null, 'brand_id');
    $stmt = $pdo->prepare("
        INSERT INTO cs_brands (brand_id, name, slug, description, website, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    try {
        $stmt->execute([
            $brandId,
            $name,
            $slug,
            trim((string) ($data['description'] ?? '')),
            trim((string) ($data['website'] ?? '')),
            empty($data['is_active']) ? 0 : 1,
        ]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000' && stripos($e->getMessage(), 'uk_cs_brands_name') !== false) {
            throw new RuntimeException('இந்த Brand பெயர் ஏற்கனவே database-ல் உள்ளது. புதிய பெயரை பயன்படுத்துங்கள்.');
        }
        if ((string) $e->getCode() === '23000' && stripos($e->getMessage(), 'slug') !== false) {
            throw new RuntimeException('இந்த Brand slug ஏற்கனவே உள்ளது. பெயரை சற்று மாற்றி மீண்டும் save செய்யுங்கள்.');
        }
        throw new RuntimeException('Unable to save brand. ' . $e->getMessage());
    }
}

function marketplaceCreateCategory(array $data): void
{
    global $pdo;
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Category name is required.');
    }
    $categoryId = marketplaceGetNextNumericId('cs_categories', 'category_id');
    $slug = marketplaceGenerateUniqueSlug('cs_categories', 'slug', $name, null, 'category_id');
    $stmt = $pdo->prepare("
        INSERT INTO cs_categories (category_id, parent_id, name, slug, description, sort_order, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");
    $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
    try {
        $stmt->execute([
            $categoryId,
            $parentId,
            $name,
            $slug,
            trim((string) ($data['description'] ?? '')),
            (int) ($data['sort_order'] ?? 0),
            empty($data['is_active']) ? 0 : 1,
        ]);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000' && (stripos($e->getMessage(), 'slug') !== false || stripos($e->getMessage(), 'name') !== false)) {
            throw new RuntimeException('இந்த Category ஏற்கனவே உள்ளது. பெயரை மாற்றி மீண்டும் save செய்யுங்கள்.');
        }
        throw new RuntimeException('Unable to save category. ' . $e->getMessage());
    }
}

function marketplaceCreateCustomer(array $data): void
{
    global $pdo;
    $name = trim((string) ($data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    if ($name === '' || $phone === '') {
        throw new RuntimeException('Customer name and phone are required.');
    }
    $customerId = marketplaceGetNextNumericId('cs_customers', 'customer_id');
    $stmt = $pdo->prepare("
        INSERT INTO cs_customers
        (customer_id, user_id, name, email, phone, gst_number, billing_address, shipping_address, city, state, pincode, country, customer_type, credit_limit, outstanding_balance, is_active, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.00, ?, NOW(), NOW())
    ");
    $stmt->execute([
        $customerId,
        trim((string) ($data['user_id'] ?? '')) ?: null,
        $name,
        trim((string) ($data['email'] ?? '')),
        $phone,
        trim((string) ($data['gst_number'] ?? '')),
        trim((string) ($data['billing_address'] ?? '')),
        trim((string) ($data['shipping_address'] ?? '')),
        trim((string) ($data['city'] ?? '')),
        trim((string) ($data['state'] ?? '')),
        trim((string) ($data['pincode'] ?? '')),
        trim((string) ($data['country'] ?? 'India')) ?: 'India',
        trim((string) ($data['customer_type'] ?? 'regular')) ?: 'regular',
        (float) ($data['credit_limit'] ?? 0),
        empty($data['is_active']) ? 0 : 1,
    ]);
}

function marketplaceCreateOrUpdateProduct(array $data, string $userId): int
{
    global $pdo;
    $productId = !empty($data['product_id']) ? (int) $data['product_id'] : 0;
    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Product name is required.');
    }
    $brandId = !empty($data['brand_id']) ? (int) $data['brand_id'] : null;
    $categoryId = !empty($data['category_id']) ? (int) $data['category_id'] : null;
    $sku = trim((string) ($data['sku'] ?? ''));
    if ($sku === '') {
        $sku = marketplaceGenerateSku($name, $brandId, $categoryId, $productId > 0 ? $productId : null);
    }

    $existingSkuStmt = $pdo->prepare("SELECT product_id FROM cs_products WHERE sku = ? AND product_id <> ? LIMIT 1");
    $existingSkuStmt->execute([$sku, $productId]);
    if ($existingSkuStmt->fetch()) {
        throw new RuntimeException('இந்த SKU ஏற்கனவே மற்றொரு product-க்கு பயன்படுத்தப்பட்டுள்ளது.');
    }

    $duplicateStmt = $pdo->prepare("
        SELECT product_id
        FROM cs_products
        WHERE name = ? AND COALESCE(brand_id, 0) = COALESCE(?, 0) AND COALESCE(category_id, 0) = COALESCE(?, 0) AND product_id <> ?
        LIMIT 1
    ");
    $duplicateStmt->execute([$name, $brandId, $categoryId, $productId]);
    if ($duplicateStmt->fetch()) {
        throw new RuntimeException('இந்த product name + brand + category combination ஏற்கனவே உள்ளது.');
    }

    $specificationsInput = trim((string) ($data['specifications'] ?? ''));
    $specificationsData = [];
    if ($specificationsInput !== '') {
        $decoded = json_decode($specificationsInput, true);
        if ($decoded === null && strtolower($specificationsInput) !== 'null') {
            throw new RuntimeException('Specifications must be valid JSON.');
        }
        if (is_array($decoded)) {
            $specificationsData = $decoded;
        }
    }

    $videoUrl = trim((string) ($data['video_url'] ?? ''));
    $galleryUrls = marketplaceNormalizeMediaUrls((string) ($data['gallery_urls'] ?? ''));
    $mediaBlock = [];
    if ($videoUrl !== '') {
        $mediaBlock['video_url'] = $videoUrl;
    }
    if (!empty($galleryUrls)) {
        $mediaBlock['gallery_urls'] = $galleryUrls;
    }
    if (!empty($mediaBlock)) {
        $specificationsData['__media'] = $mediaBlock;
    } else {
        unset($specificationsData['__media']);
    }

    $payload = [
        'sku' => $sku,
        'brand_id' => $brandId,
        'category_id' => $categoryId,
        'name' => $name,
        'slug' => marketplaceGenerateUniqueSlug('cs_products', 'slug', $name . '-' . $sku, $productId > 0 ? $productId : null, 'product_id'),
        'description' => trim((string) ($data['description'] ?? '')),
        'short_description' => trim((string) ($data['short_description'] ?? '')),
        'specifications' => !empty($specificationsData) ? json_encode($specificationsData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
        'mrp' => (float) ($data['mrp'] ?? 0),
        'current_price' => (float) ($data['current_price'] ?? 0),
        'cost_price' => (float) ($data['cost_price'] ?? 0),
        'stock_quantity' => (int) ($data['stock_quantity'] ?? 0),
        'low_stock_threshold' => max(1, (int) ($data['low_stock_threshold'] ?? 5)),
        'warranty_months' => max(0, (int) ($data['warranty_months'] ?? 12)),
        'condition_type' => trim((string) ($data['condition_type'] ?? 'new')) ?: 'new',
        'is_featured' => empty($data['is_featured']) ? 0 : 1,
        'is_active' => empty($data['is_active']) ? 0 : 1,
        'meta_title' => trim((string) ($data['meta_title'] ?? '')),
        'meta_description' => trim((string) ($data['meta_description'] ?? '')),
        'image_url' => trim((string) ($data['image_url'] ?? '')),
        'gallery_urls' => $galleryUrls,
        'video_url' => $videoUrl,
    ];

    if ($productId > 0) {
        $stmt = $pdo->prepare("
            UPDATE cs_products
            SET sku = ?, brand_id = ?, category_id = ?, name = ?, slug = ?, description = ?, short_description = ?, specifications = ?,
                mrp = ?, current_price = ?, cost_price = ?, stock_quantity = ?, low_stock_threshold = ?, warranty_months = ?,
                condition_type = ?, is_featured = ?, is_active = ?, meta_title = ?, meta_description = ?, updated_by = ?, updated_at = NOW()
            WHERE product_id = ?
        ");
        $stmt->execute([
            $payload['sku'], $payload['brand_id'], $payload['category_id'], $payload['name'], $payload['slug'],
            $payload['description'], $payload['short_description'], $payload['specifications'] !== '' ? $payload['specifications'] : null,
            $payload['mrp'], $payload['current_price'], $payload['cost_price'], $payload['stock_quantity'], $payload['low_stock_threshold'],
            $payload['warranty_months'], $payload['condition_type'], $payload['is_featured'], $payload['is_active'],
            $payload['meta_title'], $payload['meta_description'], $userId, $productId
        ]);
    } else {
        $productId = marketplaceGetNextNumericId('cs_products', 'product_id');
        $stmt = $pdo->prepare("
            INSERT INTO cs_products
            (product_id, sku, brand_id, category_id, name, slug, description, short_description, specifications, mrp, current_price, cost_price, stock_quantity, low_stock_threshold, warranty_months, condition_type, is_featured, is_active, meta_title, meta_description, created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $productId,
            $payload['sku'], $payload['brand_id'], $payload['category_id'], $payload['name'], $payload['slug'],
            $payload['description'], $payload['short_description'], $payload['specifications'] !== '' ? $payload['specifications'] : null,
            $payload['mrp'], $payload['current_price'], $payload['cost_price'], $payload['stock_quantity'], $payload['low_stock_threshold'],
            $payload['warranty_months'], $payload['condition_type'], $payload['is_featured'], $payload['is_active'],
            $payload['meta_title'], $payload['meta_description'], $userId, $userId
        ]);
    }

    marketplaceSyncProductImages($productId, $payload['image_url'], $payload['gallery_urls'], $payload['name']);

    return $productId;
}

function marketplaceRecordStockMovement(int $productId, string $movementType, string $referenceType, ?int $referenceId, int $quantity, float $unitCost, string $notes, string $userId): void
{
    global $pdo;
    $movementId = marketplaceGetNextNumericId('cs_inventory_movements', 'movement_id');
    $stmt = $pdo->prepare("
        INSERT INTO cs_inventory_movements
        (movement_id, product_id, movement_type, reference_type, reference_id, quantity, unit_cost, notes, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$movementId, $productId, $movementType, $referenceType, $referenceId, $quantity, $unitCost, $notes, $userId]);
}

function marketplaceRecordPurchase(array $data, string $userId): void
{
    global $pdo;
    $productId = (int) ($data['product_id'] ?? 0);
    $quantity = (int) ($data['quantity'] ?? 0);
    $unitCost = (float) ($data['unit_cost'] ?? 0);
    if ($productId <= 0 || $quantity <= 0) {
        throw new RuntimeException('Purchase product and quantity are required.');
    }

    $pdo->beginTransaction();
    try {
        $product = marketplaceGetProductById($productId);
        if (!$product) {
            throw new RuntimeException('Product not found for purchase entry.');
        }
        $newStock = ((int) ($product['stock_quantity'] ?? 0)) + $quantity;
        $stmt = $pdo->prepare("UPDATE cs_products SET stock_quantity = ?, cost_price = ?, updated_by = ?, updated_at = NOW() WHERE product_id = ?");
        $stmt->execute([$newStock, $unitCost > 0 ? $unitCost : (float) $product['cost_price'], $userId, $productId]);

        $notes = trim((string) ($data['notes'] ?? ''));
        $vendorName = trim((string) ($data['vendor_name'] ?? ''));
        if ($vendorName !== '') {
            $notes = 'Vendor: ' . $vendorName . ($notes !== '' ? ' | ' . $notes : '');
        }
        marketplaceRecordStockMovement($productId, 'purchase', 'manual', null, $quantity, $unitCost, $notes, $userId);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function marketplaceCreateInvoiceFromCart(int $cartId, string $adminUserId, array $meta = []): int
{
    global $pdo;
    $pdo->beginTransaction();
    try {
        $cartStmt = $pdo->prepare("SELECT * FROM cs_carts WHERE cart_id = ? LIMIT 1");
        $cartStmt->execute([$cartId]);
        $cart = $cartStmt->fetch();
        if (!$cart || $cart['status'] !== 'active') {
            throw new RuntimeException('Selected cart is not active.');
        }

        $items = marketplaceGetCartItems($cartId);
        if (empty($items)) {
            throw new RuntimeException('Cart does not contain any items.');
        }

        foreach ($items as $item) {
            $stock = (int) ($item['current_stock'] ?? $item['stock_quantity'] ?? 0);
            if ($stock < (int) $item['quantity']) {
                throw new RuntimeException('Insufficient stock for ' . $item['product_name'] . '.');
            }
        }

        $totals = marketplaceCalculateCartTotals($items);
        $invoiceNumber = marketplaceGenerateInvoiceNumber();
        $customerId = !empty($meta['customer_id']) ? (int) $meta['customer_id'] : (int) $cart['customer_id'];
        if ($customerId <= 0) {
            throw new RuntimeException('Cart is missing a customer record.');
        }

        $invoiceId = marketplaceGetNextNumericId('cs_invoices', 'invoice_id');
        $invoiceStmt = $pdo->prepare("
            INSERT INTO cs_invoices
            (invoice_id, invoice_number, customer_id, user_id, cart_id, invoice_date, due_date, subtotal, discount_total, tax_rate, tax_amount, shipping_cost, grand_total, amount_paid, amount_due, payment_status, payment_method, service_type, warranty_days, notes, terms_conditions, created_by, updated_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 0.00, ?, 0.00, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $dueDate = !empty($meta['due_date']) ? $meta['due_date'] : date('Y-m-d');
        $paymentMethod = trim((string) ($meta['payment_method'] ?? 'cash')) ?: 'cash';
        $serviceType = trim((string) ($meta['service_type'] ?? 'sales')) ?: 'sales';
        $notes = trim((string) ($meta['notes'] ?? ''));
        $terms = trim((string) ($meta['terms_conditions'] ?? ''));
        $warrantyDays = max(0, (int) ($meta['warranty_days'] ?? 0));
        $invoiceStmt->execute([
            $invoiceId,
            $invoiceNumber,
            $customerId,
            $cart['user_id'] ?: $adminUserId,
            $cartId,
            $dueDate,
            $totals['subtotal'],
            $totals['discount_total'],
            $totals['tax_rate'],
            $totals['tax_amount'],
            $totals['grand_total'],
            $totals['amount_due'],
            $paymentMethod,
            $serviceType,
            $warrantyDays,
            $notes,
            $terms,
            $adminUserId,
            $adminUserId
        ]);

        $itemStmt = $pdo->prepare("
            INSERT INTO cs_invoice_items
            (item_id, invoice_id, product_id, description, specifications, quantity, unit_price, discount_percent, discount_amount, tax_percent, tax_amount, total_amount, warranty_months, is_service, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?, ?, 0, ?)
        ");
        $productStockStmt = $pdo->prepare("UPDATE cs_products SET stock_quantity = stock_quantity - ?, updated_by = ?, updated_at = NOW() WHERE product_id = ?");
        $lineNo = 0;
        foreach ($items as $item) {
            $lineNo++;
            $itemId = marketplaceGetNextNumericId('cs_invoice_items', 'item_id');
            $quantity = (int) $item['quantity'];
            $discountAmount = (float) ($item['discount_amount'] ?? 0);
            $lineSubtotal = ((float) $item['unit_price']) * $quantity;
            $lineTaxable = max(0, $lineSubtotal - $discountAmount);
            $lineTax = round(($lineTaxable * $totals['tax_rate']) / 100, 2);
            $lineTotal = round($lineTaxable + $lineTax, 2);

            $itemStmt->execute([
                $itemId,
                $invoiceId,
                (int) $item['product_id'],
                $item['product_name'],
                null,
                $quantity,
                (float) $item['unit_price'],
                $discountAmount,
                $totals['tax_rate'],
                $lineTax,
                $lineTotal,
                (int) ($item['warranty_months'] ?? 0),
                $lineNo
            ]);

            $productStockStmt->execute([$quantity, $adminUserId, (int) $item['product_id']]);
            marketplaceRecordStockMovement((int) $item['product_id'], 'sale', 'invoice', $invoiceId, -1 * $quantity, (float) $item['unit_price'], 'Invoice ' . $invoiceNumber, $adminUserId);
        }

        $pdo->prepare("UPDATE cs_carts SET status = 'converted', updated_at = NOW() WHERE cart_id = ?")->execute([$cartId]);
        $pdo->commit();
        return $invoiceId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function marketplaceRecordPayment(array $data, string $userId): void
{
    global $pdo;
    $invoiceId = (int) ($data['invoice_id'] ?? 0);
    $amount = (float) ($data['amount'] ?? 0);
    if ($invoiceId <= 0 || $amount <= 0) {
        throw new RuntimeException('Invoice and payment amount are required.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM cs_invoices WHERE invoice_id = ? LIMIT 1");
        $stmt->execute([$invoiceId]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            throw new RuntimeException('Invoice not found.');
        }

        $paymentId = marketplaceGetNextNumericId('cs_payments', 'payment_id');
        $insert = $pdo->prepare("
            INSERT INTO cs_payments
            (payment_id, invoice_id, amount, payment_method, transaction_reference, upi_utr, notes, received_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $insert->execute([
            $paymentId,
            $invoiceId,
            $amount,
            trim((string) ($data['payment_method'] ?? 'cash')) ?: 'cash',
            trim((string) ($data['transaction_reference'] ?? '')),
            trim((string) ($data['upi_utr'] ?? '')),
            trim((string) ($data['notes'] ?? '')),
            $userId
        ]);

        $newPaid = round(((float) $invoice['amount_paid']) + $amount, 2);
        $amountDue = round(max(0, (float) $invoice['grand_total'] - $newPaid), 2);
        $status = 'pending';
        if ($amountDue <= 0) {
            $status = 'paid';
        } elseif ($newPaid > 0) {
            $status = 'partial';
        }

        $update = $pdo->prepare("
            UPDATE cs_invoices
            SET amount_paid = ?, amount_due = ?, payment_status = ?, updated_by = ?, updated_at = NOW()
            WHERE invoice_id = ?
        ");
        $update->execute([$newPaid, $amountDue, $status, $userId, $invoiceId]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function marketplaceRecordAdjustment(array $data, string $userId): void
{
    global $pdo;
    $productId = (int) ($data['product_id'] ?? 0);
    $quantity = (int) ($data['quantity'] ?? 0);
    $movementType = trim((string) ($data['movement_type'] ?? 'adjustment')) ?: 'adjustment';
    if ($productId <= 0 || $quantity === 0) {
        throw new RuntimeException('Adjustment product and quantity are required.');
    }
    if (!in_array($movementType, ['return', 'adjustment', 'transfer'], true)) {
        $movementType = 'adjustment';
    }

    $pdo->beginTransaction();
    try {
        $product = marketplaceGetProductById($productId);
        if (!$product) {
            throw new RuntimeException('Product not found.');
        }
        $newStock = ((int) ($product['stock_quantity'] ?? 0)) + $quantity;
        if ($newStock < 0) {
            throw new RuntimeException('Adjustment would reduce stock below zero.');
        }

        $pdo->prepare("UPDATE cs_products SET stock_quantity = ?, updated_by = ?, updated_at = NOW() WHERE product_id = ?")
            ->execute([$newStock, $userId, $productId]);

        $invoiceId = !empty($data['invoice_id']) ? (int) $data['invoice_id'] : null;
        $notes = trim((string) ($data['notes'] ?? ''));
        marketplaceRecordStockMovement($productId, $movementType, $invoiceId ? 'invoice' : 'manual', $invoiceId, $quantity, (float) ($product['cost_price'] ?? 0), $notes, $userId);

        if ($invoiceId) {
            $stmt = $pdo->prepare("SELECT * FROM cs_invoices WHERE invoice_id = ? LIMIT 1");
            $stmt->execute([$invoiceId]);
            $invoice = $stmt->fetch();
            if ($invoice) {
                $adjustmentAmount = abs($quantity) * (float) ($product['current_price'] ?? 0);
                $newGrand = max(0, round(((float) $invoice['grand_total']) - $adjustmentAmount, 2));
                $newDue = max(0, round($newGrand - (float) $invoice['amount_paid'], 2));
                $status = $newDue <= 0 ? 'paid' : (((float) $invoice['amount_paid']) > 0 ? 'partial' : 'pending');
                $pdo->prepare("
                    UPDATE cs_invoices
                    SET grand_total = ?, amount_due = ?, notes = CONCAT(COALESCE(notes, ''), ?), updated_by = ?, updated_at = NOW(), payment_status = ?
                    WHERE invoice_id = ?
                ")->execute([
                    $newGrand,
                    $newDue,
                    "\nAdjustment: " . ($notes !== '' ? $notes : ucfirst($movementType)),
                    $userId,
                    $status,
                    $invoiceId
                ]);
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function marketplaceCreateServiceTicket(array $data, string $userId): void
{
    global $pdo;
    $customerId = (int) ($data['customer_id'] ?? 0);
    $issueDescription = trim((string) ($data['issue_description'] ?? ''));
    if ($customerId <= 0 || $issueDescription === '') {
        throw new RuntimeException('Customer and issue description are required.');
    }
    $ticketId = marketplaceGetNextNumericId('cs_service_tickets', 'ticket_id');
    $stmt = $pdo->prepare("
        INSERT INTO cs_service_tickets
        (ticket_id, ticket_number, customer_id, invoice_id, device_type, brand_id, model, serial_number, issue_description, diagnosis, estimated_cost, final_cost, status, priority, technician_id, received_at, estimated_completion, warranty_claim, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?)
    ");
    $stmt->execute([
        $ticketId,
        marketplaceGenerateTicketNumber(),
        $customerId,
        !empty($data['invoice_id']) ? (int) $data['invoice_id'] : null,
        trim((string) ($data['device_type'] ?? 'other')) ?: 'other',
        !empty($data['brand_id']) ? (int) $data['brand_id'] : null,
        trim((string) ($data['model'] ?? '')),
        trim((string) ($data['serial_number'] ?? '')),
        $issueDescription,
        trim((string) ($data['diagnosis'] ?? '')),
        (float) ($data['estimated_cost'] ?? 0),
        (float) ($data['final_cost'] ?? 0),
        trim((string) ($data['status'] ?? 'received')) ?: 'received',
        trim((string) ($data['priority'] ?? 'normal')) ?: 'normal',
        trim((string) ($data['technician_id'] ?? '')) ?: null,
        !empty($data['estimated_completion']) ? $data['estimated_completion'] : null,
        empty($data['warranty_claim']) ? 0 : 1,
        trim((string) ($data['notes'] ?? '')),
        $userId
    ]);
}
