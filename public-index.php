<?php
// public-index.php - Enhanced Public Homepage with News, Reviews, Sales, Services
define('AKKUAPPS_LOADED', true);
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/news-engine.php';

// Get public data
global $pdo;
$featuredNews = [];
$recentNews = [];
$blogHighlights = [];
$guideHighlights = [];
$featuredProducts = [];
$serviceCategories = [];
$promoBlocks = akkuNewsPromoBlocks();
$totalUsers = 0;
$totalPosts = 0;
$totalProducts = 0;
$totalReviews = 0;

try {
    $newsColumns = akkuNewsColumns($pdo);
    $newsDateSelect = akkuNewsDateSelect($pdo, 'b');
    $newsOrderBy = akkuNewsOrderBy($pdo, 'b');
    $newsPublishedClause = akkuHasColumn($newsColumns, 'status') ? "b.status = 'published'" : '1 = 1';
    $newsFeaturedClause = akkuHasColumn($newsColumns, 'is_featured') ? ' AND b.is_featured = 1' : '';
    $newsAuthorJoin = akkuNewsAuthorJoin($pdo, 'b', 'u');

    $stmt = $pdo->prepare("
        SELECT b.*, {$newsDateSelect}, u.name as author_name, u.avatar as author_avatar
        FROM news_blogs b
        LEFT JOIN users u ON {$newsAuthorJoin}
        WHERE {$newsPublishedClause}{$newsFeaturedClause}
        ORDER BY {$newsOrderBy}
        LIMIT 3
    ");
    $stmt->execute();
    $featuredNews = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT b.*, {$newsDateSelect}, u.name as author_name, u.avatar as author_avatar
        FROM news_blogs b
        LEFT JOIN users u ON {$newsAuthorJoin}
        WHERE {$newsPublishedClause}
        ORDER BY {$newsOrderBy}
        LIMIT 8
    ");
    $stmt->execute();
    $recentNews = $stmt->fetchAll();

    $blogHighlights = array_values(array_filter($recentNews, static function ($article) {
        return akkuNewsArticleType($article) === 'blog';
    }));
    $guideHighlights = array_values(array_filter($recentNews, static function ($article) {
        return in_array(strtolower((string) ($article['category'] ?? '')), ['guides', 'guide', 'hardware', 'tech'], true);
    }));
    $totalReviews = count($blogHighlights);
} catch (Exception $e) {
    error_log("Public news error: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("
        SELECT
            p.product_id,
            p.name AS product_name,
            p.short_description,
            p.current_price,
            p.condition_type AS condition,
            c.name AS category,
            b.name AS seller_name,
            COALESCE(img.image_url, img.thumbnail_url) AS product_image
        FROM cs_products p
        LEFT JOIN cs_categories c ON p.category_id = c.category_id
        LEFT JOIN cs_brands b ON p.brand_id = b.brand_id
        LEFT JOIN cs_product_images img
            ON p.product_id = img.product_id
           AND img.is_primary = 1
        WHERE p.is_active = 1 AND p.is_featured = 1
        ORDER BY p.updated_at DESC
        LIMIT 4
    ");
    $stmt->execute();
    $featuredProducts = $stmt->fetchAll();
    $totalProducts = (int) $pdo->query("SELECT COUNT(*) FROM cs_products WHERE is_active = 1")->fetchColumn();
} catch (Exception $e) {
    $featuredProducts = [];
}

try {
    $stmt = $pdo->prepare("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY sort_order ASC");
    $stmt->execute();
    $serviceCategories = $stmt->fetchAll();
} catch (Exception $e) {
    $serviceCategories = [];
}

try {
    $totalUsers = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $totalUsers = 0;
}

try {
    $totalPosts = (int) $pdo->query("SELECT COUNT(*) FROM user_posts WHERE status = 'active'")->fetchColumn();
} catch (Exception $e) {
    $totalPosts = 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AkkuApps.in - Tech Community, Reviews & Services</title>
    <meta name="description" content="AkkuApps.in - Computer components reviews, tech news, sales and PC building services">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/themes.css?v=<?= time() ?>">
    <style>
        /* Public Page Specific Styles */
        .public-hero {
            background: linear-gradient(135deg, var(--primary-bg) 0%, var(--secondary-bg) 100%);
            padding: 60px 20px;
            text-align: center;
            border-bottom: 1px solid var(--border-color);
        }
        .public-hero h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            background: linear-gradient(135deg, var(--accent-color), #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .public-hero p {
            font-size: 1.1rem;
            color: var(--text-secondary);
            max-width: 600px;
            margin: 0 auto 30px;
        }
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .hero-stat {
            text-align: center;
        }
        .hero-stat h3 {
            font-size: 1.8rem;
            color: var(--accent-color);
        }
        .hero-stat p {
            font-size: 0.9rem;
            margin: 0;
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.4rem;
            color: var(--text-primary);
        }
        .section-title i {
            color: var(--accent-color);
        }
        .view-all-btn {
            background: var(--secondary-bg);
            color: var(--text-primary);
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            border: 1px solid var(--border-color);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        .view-all-btn:hover {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }

        /* News Cards */
        .news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .news-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .news-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border-color: var(--accent-color);
        }
        .news-card-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            background: var(--secondary-bg);
        }
        .news-card-body {
            padding: 20px;
        }
        .news-tag {
            display: inline-block;
            background: var(--accent-color);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            margin-bottom: 10px;
        }
        .news-card h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 1.1rem;
            line-height: 1.4;
        }
        .news-excerpt {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 15px;
        }
        .news-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }
        .news-author {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .news-author img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
        }

        /* Featured News Hero */
        .featured-news {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        .featured-main {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            min-height: 350px;
            background: var(--secondary-bg);
        }
        .featured-main img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .featured-main-content {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px;
            background: linear-gradient(transparent, rgba(0,0,0,0.9));
        }
        .featured-main-content h2 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
        .featured-main-content p {
            color: rgba(255,255,255,0.8);
            font-size: 0.95rem;
        }
        .featured-sidebar {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .featured-side-item {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .featured-side-item:hover {
            border-color: var(--accent-color);
        }
        .featured-side-item .tag {
            color: var(--accent-color);
            font-size: 0.75rem;
            margin-bottom: 8px;
        }
        .featured-side-item h4 {
            color: var(--text-primary);
            font-size: 1rem;
            line-height: 1.3;
        }

        /* Review Cards */
        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        .review-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        .review-card:hover {
            transform: translateY(-3px);
            border-color: var(--accent-color);
        }
        .review-product-img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
            background: var(--secondary-bg);
        }
        .review-category {
            color: var(--accent-color);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .review-card h4 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 1rem;
        }
        .review-rating {
            color: #fbbf24;
            margin-bottom: 10px;
        }
        .review-excerpt {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.4;
            margin-bottom: 15px;
        }
        .reviewer-info {
            display: flex;
            align-items: center;
            gap: 10px;
            border-top: 1px solid var(--border-color);
            padding-top: 12px;
        }
        .reviewer-info img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
        }
        .reviewer-info span {
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        /* Product Sales Cards */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }
        .product-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        .product-img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            background: var(--secondary-bg);
        }
        .product-body {
            padding: 15px;
        }
        .product-condition {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 8px;
            font-size: 0.7rem;
            margin-bottom: 8px;
        }
        .condition-new { background: #10b981; color: white; }
        .condition-used { background: #f59e0b; color: white; }
        .condition-refurb { background: #8b5cf6; color: white; }
        .product-card h4 {
            color: var(--text-primary);
            font-size: 0.95rem;
            margin-bottom: 8px;
        }
        .product-price {
            color: var(--accent-color);
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .product-price .currency {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .product-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        .btn-buy {
            display: block;
            text-align: center;
            background: var(--accent-color);
            color: white;
            padding: 10px;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-buy:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }

        /* Service Categories */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .service-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .service-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-color);
            background: var(--secondary-bg);
        }
        .service-icon {
            width: 60px;
            height: 60px;
            background: var(--secondary-bg);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
            color: var(--accent-color);
        }
        .service-card h4 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        .service-card p {
            color: var(--text-secondary);
            font-size: 0.85rem;
            line-height: 1.4;
        }
        .service-price {
            color: var(--accent-color);
            font-weight: bold;
            margin-top: 10px;
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(135deg, var(--accent-color), #8b5cf6);
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            margin: 40px 0;
        }
        .cta-section h2 {
            color: white;
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        .cta-section p {
            color: rgba(255,255,255,0.9);
            margin-bottom: 25px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        .cta-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .cta-btn {
            padding: 12px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .cta-btn-primary {
            background: white;
            color: var(--accent-color);
        }
        .cta-btn-secondary {
            background: transparent;
            color: white;
            border: 2px solid white;
        }
        .cta-btn:hover {
            transform: scale(1.05);
        }

        /* Quick Access Nav */
        .quick-nav {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        .quick-nav a {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quick-nav a:hover {
            background: var(--accent-color);
            color: white;
            border-color: var(--accent-color);
        }
        .quick-nav a i {
            font-size: 0.9rem;
        }
        .announce-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin: 28px auto 0;
            max-width: 1100px;
        }
        .announce-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 16px 18px;
            text-align: left;
        }
        .announce-card span {
            display: inline-block;
            color: var(--accent-color);
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .announce-card strong {
            display: block;
            color: var(--text-primary);
            margin-bottom: 6px;
        }
        .announce-card p {
            color: var(--text-secondary);
            font-size: 0.88rem;
            line-height: 1.5;
            margin: 0;
        }
        .teaser-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }
        .teaser-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 22px;
            display: grid;
            gap: 10px;
            min-height: 100%;
        }
        .teaser-card i {
            color: var(--accent-color);
            font-size: 1.4rem;
        }
        .teaser-card h4 {
            color: var(--text-primary);
            font-size: 1.05rem;
        }
        .teaser-card p {
            color: var(--text-secondary);
            line-height: 1.55;
            font-size: 0.9rem;
        }
        .teaser-card .mini-link {
            margin-top: auto;
            color: var(--accent-color);
            font-weight: 600;
        }
        .inline-promo-banner {
            background: linear-gradient(135deg, rgba(99,102,241,.18), rgba(16,185,129,.14));
            border: 1px solid rgba(99,102,241,.24);
            border-radius: 18px;
            padding: 22px;
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: center;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        .inline-promo-banner h3 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        .inline-promo-banner p {
            color: var(--text-secondary);
            max-width: 720px;
            margin: 0;
        }

        /* Public Footer */
        .public-footer {
            background: var(--secondary-bg);
            border-top: 1px solid var(--border-color);
            padding: 40px 20px 20px;
            margin-top: 60px;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto 30px;
        }
        .footer-section h4 {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-size: 1rem;
        }
        .footer-section a {
            display: block;
            color: var(--text-secondary);
            text-decoration: none;
            padding: 5px 0;
            font-size: 0.9rem;
            transition: color 0.3s;
        }
        .footer-section a:hover {
            color: var(--accent-color);
        }
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .public-hero h1 { font-size: 1.8rem; }
            .featured-news { grid-template-columns: 1fr; }
            .hero-stats { gap: 20px; }
            .section-header { flex-direction: column; align-items: flex-start; }
            .announce-strip { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Public Header -->
    <header style="background: var(--card-bg); border-bottom: 1px solid var(--border-color); padding: 15px 20px; position: sticky; top: 0; z-index: 100;">
        <div style="max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center;">
            <a href="/" style="text-decoration: none;">
                <h2 style="margin: 0; color: var(--text-primary);">akku<span style="color: var(--accent-color);">apps</span><span style="font-size: 0.7em; color: var(--text-secondary); margin-left: 5px;">.in</span></h2>
            </a>

            <div style="display: flex; align-items: center; gap: 15px;">
                <nav class="quick-nav" style="margin: 0;">
                    <a href="#news"><i class="fas fa-newspaper"></i> News</a>
                    <a href="#blogs"><i class="fas fa-star"></i> Blogs</a>
                    <a href="#sales"><i class="fas fa-shopping-cart"></i> Sales</a>
                    <a href="#services"><i class="fas fa-tools"></i> Services</a>
                </nav>
                <div style="display: flex; gap: 10px;">
                    <a href="/auth/login.php" style="color: var(--text-primary); text-decoration: none; padding: 8px 16px;">Login</a>
                    <a href="/auth/register.php" style="background: var(--accent-color); color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px;">Join</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="public-hero">
        <h1>AkkuApps is now your News, Creator, and Tech Utility Hub</h1>
        <p>Discover the latest tech news, read honest component reviews, buy/sell hardware, and book professional PC services — all in one place.</p>

        <div class="quick-nav">
            <a href="/user/feed.php"><i class="fas fa-users"></i> Community Feed</a>
            <a href="#news"><i class="fas fa-newspaper"></i> Latest News</a>
            <a href="#blogs"><i class="fas fa-feather-pointed"></i> Blogs & Guides</a>
            <a href="#sales"><i class="fas fa-tags"></i> Marketplace Preview</a>
            <a href="#services"><i class="fas fa-wrench"></i> PC Services</a>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <h3><?= number_format($totalUsers) ?>+</h3>
                <p>Community Members</p>
            </div>
            <div class="hero-stat">
                <h3><?= number_format($totalPosts) ?>+</h3>
                <p>Posts Shared</p>
            </div>
            <div class="hero-stat">
                <h3><?= number_format($totalProducts) ?>+</h3>
                <p>Catalog Items</p>
            </div>
            <div class="hero-stat">
                <h3><?= number_format($totalReviews) ?>+</h3>
                <p>Creator Blogs</p>
            </div>
        </div>
        <div class="announce-strip">
            <div class="announce-card">
                <span>New</span>
                <strong>Newsroom Publishing</strong>
                <p>Admins can publish public news and blog articles from one central newsroom engine.</p>
            </div>
            <div class="announce-card">
                <span>Community</span>
                <strong>Coins + Content</strong>
                <p>AkkuApps now mixes creator content, community interaction, and a coin-powered reward system.</p>
            </div>
            <div class="announce-card">
                <span>Preview</span>
                <strong>Marketplace Pipeline</strong>
                <p>Used desktops, laptops, motherboards, processors, RAM, and spare parts are the next public rollout.</p>
            </div>
            <div class="announce-card">
                <span>Booking</span>
                <strong>LEO Infotech Services</strong>
                <p>Repair, upgrades, custom builds, cleaning, and consultation are moving toward easy booking.</p>
            </div>
        </div>
    </section>

    <main style="max-width: 1200px; margin: 0 auto; padding: 40px 20px;">

        <!-- NEWS BLOG SECTION -->
        <section id="news" style="margin-bottom: 50px;">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-newspaper"></i> Tech News & Blog</h2>
                <a href="/news/" class="view-all-btn">View All Stories <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (!empty($featuredNews)): ?>
            <div class="featured-news">
                <a class="featured-main" href="<?= htmlspecialchars(akkuNewsPublicUrl($featuredNews[0])) ?>" style="text-decoration:none;">
                    <img src="<?= htmlspecialchars($featuredNews[0]['featured_image'] ?: 'assets/images/news-default.jpg') ?>" alt="<?= htmlspecialchars($featuredNews[0]['title']) ?>">
                    <div class="featured-main-content">
                        <span class="news-tag"><?= htmlspecialchars(ucfirst(akkuNewsArticleType($featuredNews[0]))) ?> &middot; <?= htmlspecialchars($featuredNews[0]['category']) ?></span>
                        <h2><?= htmlspecialchars($featuredNews[0]['title']) ?></h2>
                        <p><?= htmlspecialchars(akkuNewsExcerpt($featuredNews[0], 140)) ?></p>
                    </div>
                </a>
                <div class="featured-sidebar">
                    <?php for ($i = 1; $i < min(3, count($featuredNews)); $i++): ?>
                    <a class="featured-side-item" href="<?= htmlspecialchars(akkuNewsPublicUrl($featuredNews[$i])) ?>" style="text-decoration:none;">
                        <div class="tag"><?= htmlspecialchars($featuredNews[$i]['category']) ?></div>
                        <h4><?= htmlspecialchars($featuredNews[$i]['title']) ?></h4>
                        <div style="color: var(--text-secondary); font-size: 0.8rem; margin-top: 8px;">
                            <?php $featuredDate = akkuNewsArticleDate($featuredNews[$i]); ?>
                            <?= $featuredDate ? date('M j, Y', strtotime($featuredDate)) : '' ?> &middot; <?= number_format(akkuNewsViewCount($featuredNews[$i])) ?> views
                        </div>
                    </a>
                    <?php endfor; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="news-grid">
                <?php foreach ($recentNews as $news): ?>
                <a href="<?= htmlspecialchars(akkuNewsPublicUrl($news)) ?>" style="text-decoration:none; color:inherit;">
                <article class="news-card">
                    <img src="<?= htmlspecialchars($news['featured_image'] ?: 'assets/images/news-default.jpg') ?>" alt="" class="news-card-img">
                    <div class="news-card-body">
                        <span class="news-tag"><?= htmlspecialchars(ucfirst(akkuNewsArticleType($news))) ?> &middot; <?= htmlspecialchars($news['category']) ?></span>
                        <h3><?= htmlspecialchars($news['title']) ?></h3>
                        <p class="news-excerpt"><?= htmlspecialchars(akkuNewsExcerpt($news, 105)) ?></p>
                        <div class="news-meta">
                            <div class="news-author">
                                <img src="<?= htmlspecialchars($news['author_avatar'] ?: 'assets/images/default-avatar.png') ?>" alt="">
                                <span><?= htmlspecialchars($news['author_name']) ?></span>
                            </div>
                            <?php $recentDate = akkuNewsArticleDate($news); ?>
                            <span><i class="far fa-clock"></i> <?= $recentDate ? date('M j', strtotime($recentDate)) : '' ?> &middot; <?= number_format(akkuNewsViewCount($news)) ?> views</span>
                        </div>
                    </div>
                </article>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- BLOGS + GUIDES SECTION -->
        <section id="blogs" style="margin-bottom: 50px;">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-star"></i> Blogs, Guides & Creator Picks</h2>
                <a href="/news/?kind=blog" class="view-all-btn">Explore Blogs <i class="fas fa-arrow-right"></i></a>
            </div>

            <div class="teaser-grid">
                <?php foreach (array_slice($blogHighlights ?: $recentNews, 0, 4) as $article): ?>
                <?php $articleDate = akkuNewsArticleDate($article); ?>
                <div class="teaser-card">
                    <i class="fas fa-feather-pointed"></i>
                    <div class="review-category"><?= htmlspecialchars(ucfirst(akkuNewsArticleType($article))) ?> &middot; <?= htmlspecialchars((string) ($article['category'] ?? 'general')) ?></div>
                    <h4><?= htmlspecialchars((string) $article['title']) ?></h4>
                    <p><?= htmlspecialchars(akkuNewsExcerpt($article, 110)) ?></p>
                    <div class="muted-text">
                        <?= htmlspecialchars((string) ($article['author_name'] ?? 'AkkuApps Desk')) ?>
                        <?php if ($articleDate): ?> &middot; <?= date('M j, Y', strtotime($articleDate)) ?><?php endif; ?>
                        &middot; <?= akkuNewsReadingMinutes((string) ($article['content'] ?? '')) ?> min read
                    </div>
                    <a class="mini-link" href="<?= htmlspecialchars(akkuNewsPublicUrl($article)) ?>">Read article</a>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="inline-promo-banner">
                <div>
                    <h3>New public content system: one hub for News and Blogs</h3>
                    <p>Instead of a separate reviews section, AkkuApps now pushes public attention into a single stronger editorial flow with featured stories, creator blogs, and guide-driven discovery.</p>
                </div>
                <a href="/news/" class="btn btn-primary">Open News & Blogs</a>
            </div>
        </section>

        <section id="chatbot" style="margin-bottom: 50px;">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-robot"></i> AkkuApps Chatbot</h2>
                <a href="https://chatbot.akkuapps.in/" class="view-all-btn" target="_blank" rel="noopener">Open Chatbot <i class="fas fa-arrow-right"></i></a>
            </div>
            <div class="teaser-grid">
                <div class="teaser-card">
                    <i class="fas fa-comments"></i>
                    <h4>Instant Help Desk</h4>
                    <p>Launch the AkkuApps chatbot for quick answers about products, services, and platform support.</p>
                    <a class="mini-link" href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener">Launch chatbot</a>
                </div>
                <div class="teaser-card">
                    <i class="fas fa-headset"></i>
                    <h4>Pre-Sales Guidance</h4>
                    <p>Use the chatbot before browsing the marketplace to shortlist components, upgrades, and service options.</p>
                    <a class="mini-link" href="/marketplace/">Browse marketplace</a>
                </div>
                <div class="teaser-card">
                    <i class="fas fa-user-lock"></i>
                    <h4>Account-Aware Flow</h4>
                    <p>Logged-in users can jump from chatbot discovery into product details, carts, and service workflows.</p>
                    <a class="mini-link" href="/auth/login.php">Login to continue</a>
                </div>
            </div>
        </section>

        <!-- SALES SECTION -->
        <section id="sales" style="margin-bottom: 50px;">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-shopping-cart"></i> Marketplace</h2>
                <a href="/marketplace/" class="view-all-btn">Browse All <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (!empty($featuredProducts)): ?>
                <div class="products-grid">
                    <?php foreach ($featuredProducts as $product): ?>
                    <div class="product-card">
                        <img src="<?= htmlspecialchars($product['product_image'] ?: 'assets/images/product-default.jpg') ?>" alt="" class="product-img">
                        <div class="product-body">
                            <span class="product-condition condition-<?= htmlspecialchars((string) ($product['condition'] ?? 'used')) ?>"><?= ucfirst(str_replace('_', ' ', (string) ($product['condition'] ?? 'used'))) ?></span>
                            <h4><?= htmlspecialchars($product['product_name']) ?></h4>
                            <div class="product-price">
                                Rs <?= number_format((float) ($product['current_price'] ?? 0), 2) ?>
                            </div>
                            <div class="product-meta">
                                <span><i class="fas fa-tag"></i> <?= htmlspecialchars($product['seller_name'] ?: 'Shop Catalog') ?></span>
                                <span><i class="fas fa-layer-group"></i> <?= htmlspecialchars($product['category'] ?: 'Hardware') ?></span>
                            </div>
                            <a href="/marketplace/product.php?id=<?= $product['product_id'] ?>" class="btn-buy">View Details</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="teaser-grid">
                    <div class="teaser-card">
                        <i class="fas fa-microchip"></i>
                        <h4>Spare Parts Catalog</h4>
                        <p>Motherboards, processors, RAM, SSDs, GPUs, and accessories will be browsable from one catalog-first marketplace.</p>
                        <a class="mini-link" href="/news/">Follow launch updates</a>
                    </div>
                    <div class="teaser-card">
                        <i class="fas fa-laptop"></i>
                        <h4>Used Desktop / Laptop Sales</h4>
                        <p>Curated used systems, refurbished options, and device listings are part of the next sales rollout.</p>
                        <a class="mini-link" href="/news/?category=deals">See deal news</a>
                    </div>
                    <div class="teaser-card">
                        <i class="fas fa-clipboard-list"></i>
                        <h4>Catalog + Booking First</h4>
                        <p>Marketplace V1 will focus on browsing inventory and collecting enquiries before full vendor operations.</p>
                        <a class="mini-link" href="/auth/login.php">Login for future access</a>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <!-- SERVICES SECTION -->
        <section id="services" style="margin-bottom: 50px;">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-tools"></i> PC Services & Booking</h2>
                <a href="/services/" class="view-all-btn">Book Now <i class="fas fa-arrow-right"></i></a>
            </div>

            <div class="services-grid">
                <?php foreach ($serviceCategories as $service): ?>
                <div class="service-card" onclick="location.href='/services/book.php?category=<?= $service['category_id'] ?>'">
                    <div class="service-icon">
                        <i class="fas <?= htmlspecialchars($service['icon'] ?: 'fa-wrench') ?>"></i>
                    </div>
                    <h4><?= htmlspecialchars($service['category_name']) ?></h4>
                    <p><?= htmlspecialchars($service['description']) ?></p>
                    <div class="service-price">From <?= number_format($service['base_price'], 0) ?> coins</div>
                </div>
                <?php endforeach; ?>

                <!-- Default services if none in DB -->
                <?php if (empty($serviceCategories)): ?>
                <div class="service-card" onclick="location.href='/services/book.php?type=build'">
                    <div class="service-icon"><i class="fas fa-desktop"></i></div>
                    <h4>PC Building</h4>
                    <p>Custom PC assembly with component selection guidance</p>
                    <div class="service-price">From 500 coins</div>
                </div>
                <div class="service-card" onclick="location.href='/services/book.php?type=repair'">
                    <div class="service-icon"><i class="fas fa-tools"></i></div>
                    <h4>PC Repair</h4>
                    <p>Hardware diagnostics, troubleshooting and fixes</p>
                    <div class="service-price">From 300 coins</div>
                </div>
                <div class="service-card" onclick="location.href='/services/book.php?type=upgrade'">
                    <div class="service-icon"><i class="fas fa-arrow-up"></i></div>
                    <h4>Upgrades</h4>
                    <p>RAM, SSD, GPU upgrades and performance tuning</p>
                    <div class="service-price">From 200 coins</div>
                </div>
                <div class="service-card" onclick="location.href='/services/book.php?type=cleaning'">
                    <div class="service-icon"><i class="fas fa-broom"></i></div>
                    <h4>Cleaning</h4>
                    <p>Deep cleaning, thermal paste replacement, cable management</p>
                    <div class="service-price">From 150 coins</div>
                </div>
                <div class="service-card" onclick="location.href='/services/book.php?type=software'">
                    <div class="service-icon"><i class="fas fa-cogs"></i></div>
                    <h4>Software Setup</h4>
                    <p>OS installation, driver updates, software configuration</p>
                    <div class="service-price">From 250 coins</div>
                </div>
                <div class="service-card" onclick="location.href='/services/book.php?type=consult'">
                    <div class="service-icon"><i class="fas fa-comments"></i></div>
                    <h4>Consultation</h4>
                    <p>One-on-one tech advice and buying recommendations</p>
                    <div class="service-price">From 100 coins</div>
                </div>
                <?php endif; ?>
            </div>
            <div class="inline-promo-banner">
                <div>
                    <h3>Computer sales and service access is being shaped around booking-first flow</h3>
                    <p>Marketplace and service modules are moving toward login-based browsing, enquiries, and booking for LEO Infotech inventory and repair workflows.</p>
                </div>
                <a href="/auth/login.php" class="btn btn-primary">Login for Access</a>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <h2>Join Our Tech Community</h2>
            <p>Share your builds, write reviews, sell hardware, and connect with fellow enthusiasts. Earn coins for every contribution!</p>
            <div class="cta-buttons">
                <a href="/auth/register.php" class="cta-btn cta-btn-primary">Get Started Free</a>
                <a href="/user/feed.php" class="cta-btn cta-btn-secondary">Explore Feed</a>
            </div>
        </section>

    </main>

    <!-- Public Footer -->
    <footer class="public-footer">
        <div class="footer-grid">
            <div class="footer-section">
                <h4><i class="fas fa-newspaper"></i> News</h4>
                <a href="/news/?category=tech">Tech News</a>
                <a href="/news/?category=hardware">Hardware Updates</a>
                <a href="/news/?category=guides">Buying Guides</a>
                <a href="/news/?category=deals">Deal Alerts</a>
            </div>
            <div class="footer-section">
                <h4><i class="fas fa-star"></i> Blogs</h4>
                <a href="/news/?kind=blog">Creator Blogs</a>
                <a href="/news/?kind=blog&category=guides">Guides</a>
                <a href="/news/?kind=blog&category=hardware">Hardware Insights</a>
                <a href="/news/?kind=blog&category=tech">Community Picks</a>
            </div>
            <div class="footer-section">
                <h4><i class="fas fa-shopping-cart"></i> Marketplace</h4>
                <a href="/marketplace/?type=components">Components</a>
                <a href="/marketplace/?type=accessories">Accessories</a>
                <a href="/marketplace/?type=systems">Full Systems</a>
                <a href="/marketplace/sell.php">Sell Item</a>
            </div>
            <div class="footer-section">
                <h4><i class="fas fa-tools"></i> Services</h4>
                <a href="/services/?type=build">PC Building</a>
                <a href="/services/?type=repair">Repairs</a>
                <a href="/services/?type=upgrade">Upgrades</a>
                <a href="/services/?type=consult">Consultation</a>
            </div>
            <div class="footer-section">
                <h4><i class="fas fa-users"></i> Community</h4>
                <a href="/user/feed.php">Public Feed</a>
                <a href="/user/groups.php">Groups</a>
                <a href="/user/events.php">Events</a>
                <a href="/auth/register.php">Join Now</a>
            </div>
        </div>
        <div class="footer-bottom">
            <p> AkkuApps.in — Built by the community, for the community.</p>
        </div>
    </footer>

    <script src="assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
