<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/news-engine.php';
require_once '../includes/ad-engine.php';

$category = trim((string) ($_GET['category'] ?? ''));
$kind = trim((string) ($_GET['kind'] ?? ''));
$articles = [];
$error = '';
$promoBlocks = akkuNewsPromoBlocks();

// Get user location and language for ad targeting
$userLocation = akkuAdGetUserLocationAndLanguage();
$userRegion = $userLocation['region'] ?? null;
$userLanguage = $userLocation['language'] ?? 'en';

try {
    global $pdo;
    $newsColumns = akkuNewsColumns($pdo);
    $typeColumn = akkuNewsTypeColumn($pdo);
    $dateSelect = akkuNewsDateSelect($pdo, 'b');
    $orderBy = akkuNewsOrderBy($pdo, 'b');
    $authorJoin = akkuNewsAuthorJoin($pdo, 'b', 'u');

    $sql = "
        SELECT b.*, {$dateSelect}, u.name AS author_name
        FROM news_blogs b
        LEFT JOIN users u ON {$authorJoin}
        WHERE 1 = 1
    ";
    $params = [];

    if (akkuHasColumn($newsColumns, 'status')) {
        $sql .= " AND b.status = 'published' ";
    }
    if ($category !== '') {
        $sql .= " AND b.category = ? ";
        $params[] = $category;
    }
    if ($kind !== '' && $typeColumn) {
        $sql .= " AND b.{$typeColumn} = ? ";
        $params[] = $kind;
    }
    if ($orderBy !== '') {
        $sql .= " ORDER BY {$orderBy}";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $articles = $stmt->fetchAll();

    if ($kind !== '' && !$typeColumn) {
        $articles = array_values(array_filter($articles, static function ($article) use ($kind) {
            return akkuNewsArticleType($article) === $kind;
        }));
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}

$totalArticles = count($articles);
$blogCount = count(array_filter($articles, static function ($article) {
    return akkuNewsArticleType($article) === 'blog';
}));
$newsCount = count(array_filter($articles, static function ($article) {
    return akkuNewsArticleType($article) === 'news';
}));
$featuredCount = count(array_filter($articles, static function ($article) {
    return !empty($article['is_featured']);
}));
$heroArticle = $articles[0] ?? null;
$secondaryArticles = array_slice($articles, 1, 3);
$gridArticles = array_slice($articles, 4);

function newsIndexUrl(string $category, string $kind): string
{
    $query = http_build_query(array_filter([
        'category' => $category,
        'kind' => $kind,
    ]));

    return '/news/' . ($query ? '?' . $query : '');
}

function displayNewsAd($adSizeId = null, $userRegion = null, $userLanguage = 'en'): string
{
    global $pdo;
    $ad = akkuAdGetActiveAds($pdo, null, $adSizeId, $userRegion, $userLanguage);
    if (empty($ad)) {
        return '<div class="news-empty-ad"><i class="fas fa-megaphone"></i> <p style="margin: 0;">Advertisement space</p></div>';
    }
    $ad = $ad[0];
    ob_start();
    ?>
    <div class="news-ad-wrapper" data-ad-id="<?= htmlspecialchars($ad['id']) ?>" style="position: relative; background: var(--bg-hover); border-radius: 12px; overflow: hidden;">
        <?php if ($ad['ad_type'] === 'image' && !empty($ad['image_url'])): ?>
            <a href="<?= htmlspecialchars($ad['click_url'] ?? '#') ?>" target="_blank" rel="noopener" onclick="trackAdClick('<?= $ad['id'] ?>')" style="display: block; text-decoration: none;">
                <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>" style="width: 100%; height: auto; display: block;" loading="lazy">
            </a>
        <?php elseif ($ad['ad_type'] === 'text'): ?>
            <div style="padding: 1rem; background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(16,185,129,0.05));">
                <h4 style="margin: 0 0 0.5rem 0; color: var(--text-primary);"><?= htmlspecialchars($ad['title']) ?></h4>
                <p style="margin: 0 0 0.75rem 0; color: var(--text-secondary); font-size: 0.9rem;"><?= htmlspecialchars($ad['description']) ?></p>
                <a href="<?= htmlspecialchars($ad['click_url'] ?? '#') ?>" onclick="trackAdClick('<?= $ad['id'] ?>')" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.5rem 1rem;" target="_blank" rel="noopener">Learn More</a>
            </div>
        <?php else: ?>
            <div style="padding: 1rem; text-align: center; color: var(--text-secondary);">
                <p style="margin: 0;"><?= htmlspecialchars($ad['title']) ?></p>
            </div>
        <?php endif; ?>
        <div class="ad-meta" style="padding: 0.5rem; background: var(--bg-input); font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid var(--border-color); text-align: center;">
            Advertisement • <span id="impressions-<?= $ad['id'] ?>"><?= $ad['impressions'] ?? 0 ?></span> impressions • CTR: <span id="ctr-<?= $ad['id'] ?>"><?= number_format($ad['ctr'] ?? 0, 2) ?>%</span>
        </div>
    </div>
    <?php
    // Track impression once per page load
    static $trackedAds = [];
    if (!in_array($ad['id'], $trackedAds)) {
        echo '<script>
        (function() {
            setTimeout(function() {
                fetch("/api/track-ad-impression.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ ad_id: "' . $ad['id'] . '", user_region: "' . ($userRegion ?? '') . '", user_language: "' . $userLanguage . '" })
                }).catch(e => console.error("Ad tracking failed:", e));
            }, 2000);
        })();
        </script>';
        $trackedAds[] = $ad['id'];
    }
    return ob_get_clean();
}

function trackAdClickJs(): string
{
    return '<script>
    function trackAdClick(adId) {
        fetch("/api/track-ad-click.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ad_id: adId })
        }).catch(e => console.error("Click tracking failed:", e));
    }
    </script>';
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Blog - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .news-hub {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .news-hub-hero {
            display: grid;
            grid-template-columns: minmax(0, 1.8fr) minmax(280px, .95fr);
            gap: 1rem;
        }
        .news-lead-card,
        .news-side-card,
        .news-promo-card,
        .news-grid-card {
            border-radius: 22px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            background: var(--card-bg);
            box-shadow: var(--shadow);
        }
        .news-lead-card {
            position: relative;
            min-height: 420px;
            display: flex;
            align-items: end;
            background:
                linear-gradient(180deg, rgba(7, 10, 21, .1), rgba(7, 10, 21, .92)),
                var(--secondary-bg);
        }
        .news-lead-card img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .news-lead-body {
            position: relative;
            z-index: 1;
            padding: 1.5rem;
            display: grid;
            gap: .85rem;
        }
        .news-lead-title {
            font-size: clamp(1.7rem, 3vw, 2.7rem);
            line-height: 1.05;
            color: #fff;
        }
        .news-kicker-row,
        .news-meta-row {
            display: flex;
            flex-wrap: wrap;
            gap: .65rem;
            align-items: center;
        }
        .news-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(255, 255, 255, .12);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, .16);
            font-size: .78rem;
            font-weight: 600;
        }
        .news-chip.kind-blog {
            background: rgba(16, 185, 129, .18);
        }
        .news-chip.kind-news {
            background: rgba(99, 102, 241, .22);
        }
        .news-chip.featured {
            background: rgba(245, 158, 11, .18);
        }
        .news-lead-copy {
            max-width: 60ch;
            color: rgba(255, 255, 255, .84);
            font-size: 1rem;
            line-height: 1.65;
        }
        .news-hub-rail {
            display: grid;
            gap: 1rem;
        }
        .news-side-card {
            padding: 1.05rem;
            display: grid;
            gap: .65rem;
        }
        .news-side-card h3 {
            color: var(--text-primary);
            font-size: 1.05rem;
            line-height: 1.35;
        }
        .news-side-card p {
            color: var(--text-secondary);
            line-height: 1.5;
            font-size: .9rem;
        }
        .news-stat-strip {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .85rem;
        }
        .news-stat-card {
            padding: 1rem 1.05rem;
            border-radius: 18px;
            border: 1px solid var(--border-color);
            background: linear-gradient(180deg, rgba(99, 102, 241, .08), rgba(12, 18, 32, .12));
        }
        .news-stat-card strong {
            display: block;
            font-size: 1.5rem;
            color: var(--text-primary);
            margin-bottom: .25rem;
        }
        .news-stat-card span {
            color: var(--text-secondary);
            font-size: .85rem;
        }
        .news-grid-mix {
            display: grid;
            grid-template-columns: repeat(12, minmax(0, 1fr));
            gap: 1rem;
        }
        .news-grid-card {
            grid-column: span 4;
            display: flex;
            flex-direction: column;
            min-height: 100%;
        }
        .news-grid-card.promoted {
            justify-content: space-between;
            padding: 1.2rem;
            background:
                radial-gradient(circle at top right, rgba(99, 102, 241, .22), transparent 35%),
                linear-gradient(180deg, rgba(255,255,255,.02), rgba(99,102,241,.05));
        }
        .news-grid-card.promoted h3 {
            font-size: 1.1rem;
            color: var(--text-primary);
            margin: .5rem 0;
        }
        .news-grid-card.promoted p {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: .92rem;
        }
        .news-grid-visual {
            aspect-ratio: 16 / 10;
            background: var(--primary-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 2.4rem;
        }
        .news-grid-visual img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .news-grid-body {
            padding: 1rem;
            display: grid;
            gap: .7rem;
            flex: 1;
        }
        .news-grid-title {
            font-size: 1.05rem;
            color: var(--text-primary);
            line-height: 1.35;
        }
        .news-grid-desc {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: .9rem;
        }
        .news-promo-card {
            padding: 1.15rem;
            display: grid;
            gap: .75rem;
            background:
                linear-gradient(135deg, rgba(16, 185, 129, .13), rgba(99, 102, 241, .14)),
                var(--card-bg);
        }
        .news-promo-card h3 {
            font-size: 1.05rem;
            color: var(--text-primary);
        }
        .news-promo-card p {
            color: var(--text-secondary);
            line-height: 1.55;
            font-size: .9rem;
        }
        .news-eyebrow {
            color: var(--accent-color);
            font-size: .75rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .news-empty-ad {
            padding: 1.1rem;
            border-radius: 18px;
            border: 1px dashed rgba(99, 102, 241, .28);
            background: rgba(99, 102, 241, .05);
            color: var(--text-secondary);
            text-align: center;
        }
        .news-ad-wrapper {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .news-ad-wrapper img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .news-ad-wrapper a {
            text-decoration: none;
        }
        .ad-meta {
            font-size: 0.7rem;
        }
        @media (max-width: 1100px) {
            .news-hub-hero,
            .news-stat-strip {
                grid-template-columns: 1fr;
            }
            .news-grid-card {
                grid-column: span 6;
            }
        }
        @media (max-width: 720px) {
            .news-grid-card {
                grid-column: span 12;
            }
            .news-lead-card {
                min-height: 340px;
            }
            .news-lead-body {
                padding: 1.1rem;
            }
        }
    </style>
</head>
<body>
<?php $user = getCurrentUser(); ?>
<?php if ($user) { include '../components/header.php'; } ?>
<?php if ($user): ?>
<div class="dashboard-container">
    <?php include '../components/sidebar.php'; ?>
    <main class="main-content">
<?php else: ?>
    <main class="main-content" style="padding-top:2rem;">
<?php endif; ?>
        <div class="page-shell news-hub">
            <div class="welcome-banner">
                <h1>Tech News & Blog</h1>
                <p>Browse timely news updates, practical guides, creator insights, and upcoming hardware marketplace announcements from AkkuApps.</p>
            </div>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="news-stat-strip">
                <div class="news-stat-card"><strong><?= $totalArticles ?></strong><span>Published stories</span></div>
                <div class="news-stat-card"><strong><?= $newsCount ?></strong><span>News updates</span></div>
                <div class="news-stat-card"><strong><?= $blogCount ?></strong><span>Blogs & guides</span></div>
                <div class="news-stat-card"><strong><?= $featuredCount ?></strong><span>Featured picks</span></div>
            </div>

            <div class="segment-links">
                <a class="segment-link <?= $category === '' ? 'active' : '' ?>" href="<?= htmlspecialchars(newsIndexUrl('', $kind)) ?>">All</a>
                <a class="segment-link <?= $category === 'tech' ? 'active' : '' ?>" href="<?= htmlspecialchars(newsIndexUrl('tech', $kind)) ?>">Tech</a>
                <a class="segment-link <?= $category === 'hardware' ? 'active' : '' ?>" href="<?= htmlspecialchars(newsIndexUrl('hardware', $kind)) ?>">Hardware</a>
                <a class="segment-link <?= $category === 'guides' ? 'active' : '' ?>" href="<?= htmlspecialchars(newsIndexUrl('guides', $kind)) ?>">Guides</a>
                <a class="segment-link <?= $category === 'deals' ? 'active' : '' ?>" href="<?= htmlspecialchars(newsIndexUrl('deals', $kind)) ?>">Deals</a>
            </div>

            <div class="segment-links" style="margin-top:-.4rem;">
                <a class="segment-link <?= $kind === '' ? 'active' : '' ?>" href="<?= htmlspecialchars(newsIndexUrl($category, '')) ?>">All Formats</a>
                <a class="segment-link <?= $kind === 'news' ? 'active' : '' ?>" href="<?= htmlspecialchars(newsIndexUrl($category, 'news')) ?>">News</a>
                <a class="segment-link <?= $kind === 'blog' ? 'active' : '' ?>" href="<?= htmlspecialchars(newsIndexUrl($category, 'blog')) ?>">Blogs</a>
            </div>

            <?php if (empty($articles)): ?>
                <div class="empty-state">No articles published yet.</div>
            <?php else: ?>
                <section class="news-hub-hero">
                    <?php if ($heroArticle): ?>
                        <?php $heroType = akkuNewsArticleType($heroArticle); ?>
                        <?php $heroDate = akkuNewsArticleDate($heroArticle); ?>
                        <article class="news-lead-card">
                            <?php if (!empty($heroArticle['featured_image'])): ?>
                                <img src="<?= htmlspecialchars((string) $heroArticle['featured_image']) ?>" alt="<?= htmlspecialchars((string) $heroArticle['title']) ?>">
                            <?php endif; ?>
                            <div class="news-lead-body">
                                <div class="news-kicker-row">
                                    <span class="news-chip kind-<?= htmlspecialchars($heroType) ?>"><?= htmlspecialchars(strtoupper($heroType)) ?></span>
                                    <?php if (!empty($heroArticle['is_featured'])): ?><span class="news-chip featured">Featured</span><?php endif; ?>
                                    <span class="news-chip"><?= htmlspecialchars((string) ($heroArticle['category'] ?? 'general')) ?></span>
                                </div>
                                <h2 class="news-lead-title"><?= htmlspecialchars((string) $heroArticle['title']) ?></h2>
                                <p class="news-lead-copy"><?= htmlspecialchars(akkuNewsExcerpt($heroArticle, 190)) ?></p>
                                <div class="news-meta-row">
                                    <span class="news-chip"><?= htmlspecialchars((string) ($heroArticle['author_name'] ?? 'AkkuApps Desk')) ?></span>
                                    <?php if ($heroDate): ?><span class="news-chip"><?= date('M j, Y', strtotime($heroDate)) ?></span><?php endif; ?>
                                    <span class="news-chip"><?= akkuNewsReadingMinutes((string) ($heroArticle['content'] ?? '')) ?> min read</span>
                                    <span class="news-chip"><?= number_format(akkuNewsViewCount($heroArticle)) ?> views</span>
                                </div>
                                <div>
                                    <a class="btn btn-primary" href="<?= htmlspecialchars(akkuNewsPublicUrl($heroArticle)) ?>">Read Lead Story</a>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>

                    <aside class="news-hub-rail">
                        <?php foreach ($secondaryArticles as $article): ?>
                            <?php $articleDate = akkuNewsArticleDate($article); ?>
                            <article class="news-side-card">
                                <div class="news-kicker-row">
                                    <span class="treasury-badge"><?= htmlspecialchars(ucfirst(akkuNewsArticleType($article))) ?></span>
                                    <span class="muted-text"><?= htmlspecialchars((string) ($article['category'] ?? 'general')) ?></span>
                                </div>
                                <h3><?= htmlspecialchars((string) $article['title']) ?></h3>
                                <p><?= htmlspecialchars(akkuNewsExcerpt($article, 110)) ?></p>
                                <div class="muted-text">
                                    <?= htmlspecialchars((string) ($article['author_name'] ?? 'AkkuApps Desk')) ?>
                                    <?php if ($articleDate): ?> &middot; <?= date('M j', strtotime($articleDate)) ?><?php endif; ?>
                                    &middot; <?= akkuNewsReadingMinutes((string) ($article['content'] ?? '')) ?> min
                                    &middot; <?= number_format(akkuNewsViewCount($article)) ?> views
                                </div>
                                <div><a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars(akkuNewsPublicUrl($article)) ?>">Open</a></div>
                            </article>
                        <?php endforeach; ?>

                        <?php if (!empty($promoBlocks[0])): ?>
                            <div class="news-promo-card">
                                <span class="news-eyebrow"><?= htmlspecialchars($promoBlocks[0]['eyebrow']) ?></span>
                                <h3><?= htmlspecialchars($promoBlocks[0]['title']) ?></h3>
                                <p><?= htmlspecialchars($promoBlocks[0]['copy']) ?></p>
                                <div><a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($promoBlocks[0]['cta_href']) ?>"><?= htmlspecialchars($promoBlocks[0]['cta_label']) ?></a></div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Ad Placement: Sidebar Ad (Leaderboard or Skyscraper) -->
                        <div style="margin-top: 1rem;">
                            <?= displayNewsAd('tier-skyscraper', $userRegion, $userLanguage) ?>
                        </div>
                    </aside>
                </section>

                <!-- Ad Placement: Between Hero and Grid (Leaderboard) -->
                <div style="margin: 2rem 0;">
                    <?= displayNewsAd('tier-leaderboard', $userRegion, $userLanguage) ?>
                </div>

                <section class="news-grid-mix">
                    <?php
                    $promoIndex = 1;
                    $adCounter = 0;
                    foreach ($gridArticles as $index => $article):
                        // Insert ad every 6 articles
                        if ($adCounter > 0 && $adCounter % 6 === 0):
                    ?>
                        <div class="news-grid-card" style="background: var(--bg-card); padding: 0; border-radius: 12px;">
                            <?= displayNewsAd('tier-banner', $userRegion, $userLanguage) ?>
                        </div>
                    <?php
                        endif;
                        if ($index > 0 && $index % 4 === 0 && isset($promoBlocks[$promoIndex])):
                    ?>
                        <article class="news-grid-card promoted">
                            <div>
                                <span class="news-eyebrow"><?= htmlspecialchars($promoBlocks[$promoIndex]['eyebrow']) ?></span>
                                <h3><?= htmlspecialchars($promoBlocks[$promoIndex]['title']) ?></h3>
                                <p><?= htmlspecialchars($promoBlocks[$promoIndex]['copy']) ?></p>
                            </div>
                            <div><a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($promoBlocks[$promoIndex]['cta_href']) ?>"><?= htmlspecialchars($promoBlocks[$promoIndex]['cta_label']) ?></a></div>
                        </article>
                    <?php
                            $promoIndex++;
                        endif;
                        $articleDate = akkuNewsArticleDate($article);
                    ?>
                        <article class="news-grid-card">
                            <div class="news-grid-visual">
                                <?php if (!empty($article['featured_image'])): ?>
                                    <img src="<?= htmlspecialchars((string) $article['featured_image']) ?>" alt="<?= htmlspecialchars((string) $article['title']) ?>">
                                <?php else: ?>
                                    <i class="fas fa-newspaper"></i>
                                <?php endif; ?>
                            </div>
                            <div class="news-grid-body">
                                <div class="toolbar-row">
                                    <span class="treasury-badge"><?= htmlspecialchars(ucfirst(akkuNewsArticleType($article))) ?></span>
                                    <?php if (!empty($article['is_featured'])): ?><span class="good-card-status status-active">Featured</span><?php endif; ?>
                                </div>
                                <a class="news-grid-title" href="<?= htmlspecialchars(akkuNewsPublicUrl($article)) ?>"><?= htmlspecialchars((string) $article['title']) ?></a>
                                <p class="news-grid-desc"><?= htmlspecialchars(akkuNewsExcerpt($article, 120)) ?></p>
                                <div class="muted-text">
                                    <?= htmlspecialchars((string) ($article['author_name'] ?? 'AkkuApps Desk')) ?>
                                    <?php if ($articleDate): ?> &middot; <?= date('M j, Y', strtotime($articleDate)) ?><?php endif; ?>
                                </div>
                                <div class="muted-text">
                                    <?= htmlspecialchars((string) ($article['category'] ?? 'general')) ?>
                                    &middot; <?= akkuNewsReadingMinutes((string) ($article['content'] ?? '')) ?> min read
                                    &middot; <?= number_format(akkuNewsViewCount($article)) ?> views
                                </div>
                                <div><a class="btn btn-primary btn-sm" href="<?= htmlspecialchars(akkuNewsPublicUrl($article)) ?>">Read Article</a></div>
                            </div>
                        </article>
                    <?php $adCounter++; endforeach; ?>

                    <?php if (empty($gridArticles)): ?>
                        <div class="news-empty-ad" style="grid-column: 1 / -1;">
                            More featured stories, creator blogs, and marketplace announcements will appear here as you publish them from the newsroom engine.
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>
<?php if ($user): ?>
</div>
<?php endif; ?>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
<?= trackAdClickJs() ?>
</body>
</html>
