<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/news-engine.php';
require_once '../includes/ad-engine.php';

$id = trim((string) ($_GET['id'] ?? ''));
$slug = trim((string) ($_GET['slug'] ?? ''));
$article = null;
$relatedArticles = [];
$popularArticles = [];
$categoryArticles = [];
$promoBlocks = akkuNewsPromoBlocks();

// Get user location and language for ad targeting
$userLocation = akkuAdGetUserLocationAndLanguage($pdo);
$userRegion = $userLocation['region'] ?? null;
$userLanguage = $userLocation['language'] ?? 'en';

function displayArticleAd($adSizeId = null, $userRegion = null, $userLanguage = 'en')
{
    global $pdo;
    $ad = akkuAdGetActiveAds($pdo, null, $adSizeId, $userRegion, $userLanguage);
    if (empty($ad)) {
        return '<div class="news-empty-ad"><i class="fas fa-megaphone"></i> <p style="margin: 0;">Advertisement</p></div>';
    }
    $ad = $ad[0];
    ob_start();
    ?>
    <div class="news-ad-wrapper" data-ad-id="<?= htmlspecialchars($ad['id']) ?>" style="position: relative;">
        <?php if ($ad['ad_type'] === 'image' && !empty($ad['image_url'])): ?>
            <a href="<?= htmlspecialchars($ad['click_url'] ?? '#') ?>" target="_blank" rel="noopener" onclick="trackAdClick('<?= $ad['id'] ?>')">
                <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>" style="width:100%;height:auto;display:block;" loading="lazy">
            </a>
        <?php elseif ($ad['ad_type'] === 'text'): ?>
            <div style="padding:1rem;">
                <h4 style="margin:0 0 .5rem 0;color:var(--text-primary);"><?= htmlspecialchars($ad['title']) ?></h4>
                <p style="margin:0 0 .75rem 0;color:var(--text-secondary);font-size:.9rem;"><?= htmlspecialchars($ad['description']) ?></p>
                <a href="<?= htmlspecialchars($ad['click_url'] ?? '#') ?>" onclick="trackAdClick('<?= $ad['id'] ?>')" class="btn btn-primary btn-sm" target="_blank" rel="noopener">Learn More</a>
            </div>
        <?php else: ?>
            <div style="padding:1rem;text-align:center;color:var(--text-secondary);"><?= htmlspecialchars($ad['title']) ?></div>
        <?php endif; ?>
        <div class="ad-meta" style="padding:.5rem;background:var(--bg-input);font-size:.75rem;color:var(--text-muted);border-top:1px solid var(--border-color);text-align:center;">Advertisement</div>
    </div>
    <?php
    static $trackedAds = [];
    if (!in_array($ad['id'], $trackedAds)) {
        echo '<script>(function(){ setTimeout(function(){ fetch("/api/track-ad-impression.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ ad_id: "' . $ad['id'] . '", user_region: "' . ($userRegion ?? '') . '", user_language: "' . $userLanguage . '" }) }); }, 1500); })();</script>';
        $trackedAds[] = $ad['id'];
    }
    return ob_get_clean();
}

function trackAdClickJs(): string
{
    return '<script>function trackAdClick(adId){ fetch("/api/track-ad-click.php", { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ ad_id: adId }) }).catch(e=>console.error("Click tracking failed:",e)); }</script>';
}

try {
    global $pdo;
    $newsColumns = akkuNewsColumns($pdo);
    $dateSelect = akkuNewsDateSelect($pdo, 'b');
    $newsIdColumn = akkuNewsIdColumn($pdo);
    $statusClause = akkuHasColumn($newsColumns, 'status') ? " AND b.status = 'published'" : '';
    $authorJoin = akkuNewsAuthorJoin($pdo, 'b', 'u');
    $lookupColumn = $slug !== '' ? 'slug' : $newsIdColumn;

    if ($slug !== '') {
        $stmt = $pdo->prepare("
            SELECT b.*, {$dateSelect}, u.name AS author_name
            FROM news_blogs b
            LEFT JOIN users u ON {$authorJoin}
            WHERE b.slug = ?{$statusClause}
            LIMIT 1
        ");
        $stmt->execute([$slug]);
        $article = $stmt->fetch();
    } elseif ($id !== '' && $newsIdColumn) {
        $stmt = $pdo->prepare("
            SELECT b.*, {$dateSelect}, u.name AS author_name
            FROM news_blogs b
            LEFT JOIN users u ON {$authorJoin}
            WHERE b.{$newsIdColumn} = ?{$statusClause}
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $article = $stmt->fetch();
    }

    if ($article) {
        akkuNewsIncrementViewCount($pdo, $article);
        $viewColumn = akkuNewsViewCountColumn($pdo);
        if ($viewColumn) {
            $article[$viewColumn] = akkuNewsViewCount($article) + 1;
        }

        $orderBy = akkuNewsOrderBy($pdo, 'b');
        $relatedWhere = '1 = 1';
        $relatedParams = [];

        if (akkuHasColumn($newsColumns, 'status')) {
            $relatedWhere .= " AND b.status = 'published'";
        }
        if (!empty($article['category']) && akkuHasColumn($newsColumns, 'category')) {
            $relatedWhere .= " AND b.category = ?";
            $relatedParams[] = $article['category'];
        }
        if ($lookupColumn && isset($article[$lookupColumn])) {
            $relatedWhere .= " AND b.{$lookupColumn} <> ?";
            $relatedParams[] = $article[$lookupColumn];
        }

        $relatedStmt = $pdo->prepare("
            SELECT b.*, {$dateSelect}, u.name AS author_name
            FROM news_blogs b
            LEFT JOIN users u ON {$authorJoin}
            WHERE {$relatedWhere}
            ORDER BY {$orderBy}
            LIMIT 4
        ");
        $relatedStmt->execute($relatedParams);
        $relatedArticles = $relatedStmt->fetchAll();

        $popularOrder = akkuNewsViewCountColumn($pdo)
            ? 'COALESCE(b.' . akkuNewsViewCountColumn($pdo) . ', 0) DESC, ' . $orderBy
            : $orderBy;

        $popularWhere = '1 = 1';
        $popularParams = [];
        if (akkuHasColumn($newsColumns, 'status')) {
            $popularWhere .= " AND b.status = 'published'";
        }
        if ($lookupColumn && isset($article[$lookupColumn])) {
            $popularWhere .= " AND b.{$lookupColumn} <> ?";
            $popularParams[] = $article[$lookupColumn];
        }

        $popularStmt = $pdo->prepare("
            SELECT b.*, {$dateSelect}, u.name AS author_name
            FROM news_blogs b
            LEFT JOIN users u ON {$authorJoin}
            WHERE {$popularWhere}
            ORDER BY {$popularOrder}
            LIMIT 5
        ");
        $popularStmt->execute($popularParams);
        $popularArticles = $popularStmt->fetchAll();

        if (!empty($article['category']) && akkuHasColumn($newsColumns, 'category')) {
            $categoryStmt = $pdo->prepare("
                SELECT b.category, COUNT(*) AS total_articles
                FROM news_blogs b
                WHERE b.category = ?" . (akkuHasColumn($newsColumns, 'status') ? " AND b.status = 'published'" : '') . "
                GROUP BY b.category
                LIMIT 1
            ");
            $categoryStmt->execute([$article['category']]);
            $categoryArticles = $categoryStmt->fetchAll();
        }
    }
} catch (Exception $e) {
    $article = null;
}

if (!$article) {
    header('Location: /news/');
    exit;
}

$articleDate = akkuNewsArticleDate($article);
$articleType = ucfirst(akkuNewsArticleType($article));
$readingMinutes = akkuNewsReadingMinutes((string) ($article['content'] ?? ''));

$allowedHtmlTags = '<p><h1><h2><h3><h4><h5><h6><strong><b><em><i><u><s><br><hr><ul><ol><li><blockquote><pre><code><a><img><table><thead><tbody><tr><th><td><div><span><details><summary><abbr><sub><sup><ins><del><mark>';
$rawContent = (string) ($article['content'] ?? '');
$sanitizedContent = akkuNewsSanitizeHTML($rawContent);

$tocItems = [];
preg_match_all('/<h([1-4])[^>]*>(.*?)<\/h\1>/', $sanitizedContent, $headingMatches, PREG_SET_ORDER);
foreach ($headingMatches as $i => $match) {
    $level = (int) $match[1];
    $text = strip_tags($match[2]);
    $id = 'section-' . akkuNewsSlugify($text) . ($i > 0 ? '-' . $i : '');
    $tocItems[] = ['level' => $level, 'text' => $text, 'id' => $id];
    $sanitizedContent = preg_replace(
        '/<h' . $level . '([^>]*)>' . preg_quote($match[2], '/') . '<\/h' . $level . '>/',
        '<h' . $level . ' id="' . $id . '"$1>' . $match[2] . '</h' . $level . '>',
        $sanitizedContent,
        1
    );
}

$sanitizedContent = preg_replace_callback(
    '/<pre\s+class="code-block"\s+data-lang="([^"]*)">\s*<code>([\s\S]*?)<\/code>\s*<\/pre>/',
    function($m) { return '<pre class="code-block" data-lang="' . $m[1] . '"><code>' . akkuNewsHighlightCode($m[2], $m[1]) . '</code></pre>'; },
    $sanitizedContent
);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars((string) ($article['seo_title'] ?: $article['title'])) ?> - AkkuApps</title>
    <meta name="description" content="<?= htmlspecialchars((string) ($article['seo_description'] ?: $article['excerpt'] ?: '')) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .article-shell {
            display: grid;
            grid-template-columns: minmax(0, 1.65fr) minmax(280px, .8fr);
            gap: 1.25rem;
            align-items: start;
        }
        .article-stage,
        .article-rail-card,
        .article-inline-promo,
        .article-list-card {
            border-radius: 22px;
            border: 1px solid var(--border-color);
            background: var(--card-bg);
            box-shadow: var(--shadow);
        }
        .article-stage {
            overflow: hidden;
        }
        .article-cover {
            aspect-ratio: 16 / 8;
            background: var(--secondary-bg);
            overflow: hidden;
        }
        .article-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .article-header {
            padding: 1.4rem 1.5rem 1rem;
            display: grid;
            gap: .9rem;
        }
        .article-title {
            font-size: clamp(1.8rem, 3vw, 2.8rem);
            line-height: 1.08;
            color: var(--text-primary);
        }
        .article-copy {
            padding: 0 1.5rem 1.5rem;
            display: grid;
            gap: 1rem;
        }
        .article-paragraph {
            color: var(--text-primary);
            line-height: 1.85;
            font-size: 1rem;
        }
        .article-meta,
        .article-badges {
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
            background: rgba(99, 102, 241, .12);
            color: var(--text-primary);
            border: 1px solid rgba(99, 102, 241, .18);
            font-size: .78rem;
            font-weight: 600;
        }
        .news-eyebrow {
            color: var(--accent-color);
            font-size: .75rem;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-weight: 700;
        }
        .article-inline-promo {
            padding: 1rem 1.05rem;
            background:
                linear-gradient(135deg, rgba(99, 102, 241, .12), rgba(16, 185, 129, .10)),
                var(--card-bg);
        }
        .article-inline-promo h3,
        .article-rail-card h3 {
            font-size: 1.05rem;
            color: var(--text-primary);
            margin-bottom: .45rem;
        }
        .article-inline-promo p,
        .article-rail-card p {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: .92rem;
        }
        .article-rail {
            display: grid;
            gap: 1rem;
            position: sticky;
            top: calc(var(--header-h) + 1rem);
        }
        .article-rail-card {
            padding: 1rem 1.05rem;
        }
        .article-list-card {
            padding: .95rem 0;
        }
        .article-list-item {
            display: grid;
            gap: .4rem;
            padding: .85rem 1rem;
            border-top: 1px solid var(--border-color);
        }
        .article-list-item:first-child {
            border-top: none;
        }
        .article-list-item a {
            color: var(--text-primary);
            font-weight: 600;
            line-height: 1.35;
        }
        .article-reference-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            padding: 0 1.5rem 1.5rem;
        }
        .article-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            padding: 0 1.5rem 1.5rem;
        }
        .article-breadcrumb {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
            align-items: center;
            color: var(--text-secondary);
        }
        @media (max-width: 1100px) {
            .article-shell {
                grid-template-columns: 1fr;
            }
            .article-rail {
                position: static;
            }
        }
        @media (max-width: 720px) {
            .article-header,
            .article-copy,
            .article-reference-grid,
            .article-actions {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
        @media (max-width: 1100px) {
            .article-shell { grid-template-columns: 1fr; }
            .article-rail { position: static; }
        }
        @media (max-width: 768px) {
            .article-shell { grid-template-columns: 1fr; }
            .article-rail { position: static; }
            .article-header, .article-copy, .article-reference-grid, .article-actions { padding-left: 1rem; padding-right: 1rem; }
            .article-title { font-size: clamp(1.4rem, 5vw, 2rem); }
            .article-toc { position: static; margin-bottom: 1rem; }
            .article-body pre.code-block { font-size: .78rem; padding: .75rem; }
            .article-body pre.code-block::before { font-size: .6rem; }
            .article-body h1 { font-size: 1.5rem; }
            .article-body h2 { font-size: 1.3rem; }
            .article-body h3 { font-size: 1.15rem; }
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
        <div class="page-shell">
            <div class="article-breadcrumb">
                <a href="/news/" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back to News</a>
                <span><?= htmlspecialchars((string) ($article['category'] ?? 'general')) ?></span>
                <span>&middot;</span>
                <span><?= htmlspecialchars($articleType) ?></span>
            </div>

            <div class="article-shell">
                <article class="article-stage">
                    <?php if (!empty($article['featured_image'])): ?>
                        <div class="article-cover">
                            <img src="<?= htmlspecialchars((string) $article['featured_image']) ?>" alt="<?= htmlspecialchars((string) $article['title']) ?>">
                        </div>
                    <?php endif; ?>

                    <div class="article-header">
                        <div class="article-badges">
                            <span class="treasury-badge"><?= htmlspecialchars($articleType) ?></span>
                            <span class="treasury-badge"><?= htmlspecialchars((string) ($article['category'] ?? 'general')) ?></span>
                            <?php if (!empty($article['is_featured'])): ?><span class="good-card-status status-active">Featured</span><?php endif; ?>
                        </div>
                        <h1 class="article-title"><?= htmlspecialchars((string) $article['title']) ?></h1>
                        <?php if (!empty($article['excerpt'])): ?><p class="page-intro"><?= htmlspecialchars((string) $article['excerpt']) ?></p><?php endif; ?>
                        <div class="article-meta">
                            <span class="news-chip"><?= htmlspecialchars((string) ($article['author_name'] ?? 'AkkuApps Desk')) ?></span>
                            <?php if ($articleDate): ?><span class="news-chip"><?= date('M j, Y g:i A', strtotime($articleDate)) ?></span><?php endif; ?>
                            <span class="news-chip"><?= $readingMinutes ?> min read</span>
                            <span class="news-chip"><?= number_format(akkuNewsViewCount($article)) ?> views</span>
                        </div>
                    </div>

                    <div class="article-copy article-body">
                        <?= $sanitizedContent ?>

                        <!-- Ad Placement: Leaderboard between article content -->
                        <div style="margin: 1.5rem 0;">
                            <?= displayArticleAd('tier-leaderboard', $userRegion, $userLanguage) ?>
                        </div>

                        <?php if (isset($promoBlocks[0])): ?>
                            <div class="article-inline-promo">
                                <span class="news-eyebrow"><?= htmlspecialchars($promoBlocks[0]['eyebrow']) ?></span>
                                <h3><?= htmlspecialchars($promoBlocks[0]['title']) ?></h3>
                                <p><?= htmlspecialchars($promoBlocks[0]['copy']) ?></p>
                                <div style="margin-top:.8rem;"><a class="btn btn-secondary btn-sm" href="<?= htmlspecialchars($promoBlocks[0]['cta_href']) ?>"><?= htmlspecialchars($promoBlocks[0]['cta_label']) ?></a></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="article-reference-grid">
                        <div class="surface-card">
                            <strong>Document</strong>
                            <p class="muted-text" style="margin-top:.5rem;">
                                <?php if (!empty($article['document_url'])): ?>
                                    <a href="<?= htmlspecialchars((string) $article['document_url']) ?>" target="_blank"><?= htmlspecialchars((string) $article['document_url']) ?></a>
                                <?php else: ?>
                                    No document attached
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="surface-card">
                            <strong>Reference Link</strong>
                            <p class="muted-text" style="margin-top:.5rem;">
                                <?php if (!empty($article['reference_link'])): ?>
                                    <a href="<?= htmlspecialchars((string) $article['reference_link']) ?>" target="_blank"><?= htmlspecialchars((string) $article['reference_link']) ?></a>
                                <?php else: ?>
                                    No reference link attached
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="surface-card">
                            <strong>Audience Signal</strong>
                            <p class="muted-text" style="margin-top:.5rem;">
                                <?= htmlspecialchars($articleType) ?> article in <strong><?= htmlspecialchars((string) ($article['category'] ?? 'general')) ?></strong>
                                <?php if (!empty($categoryArticles[0]['total_articles'])): ?>
                                    with <?= (int) $categoryArticles[0]['total_articles'] ?> story<?= (int) $categoryArticles[0]['total_articles'] === 1 ? '' : 'ies' ?> in this category.
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="article-actions">
                        <a class="btn btn-primary" href="/news/">More News & Blogs</a>
                        <a class="btn btn-secondary" href="/auth/register.php">Join AkkuApps</a>
                    </div>
                </article>

                <aside class="article-rail">
                    <?php if (!empty($tocItems)): ?>
                        <nav class="article-toc">
                            <h4><i class="fas fa-list"></i> Table of Contents</h4>
                            <ul>
                                <?php foreach ($tocItems as $toc): ?>
                                    <li style="<?= $toc['level'] > 1 ? 'margin-left:' . (($toc['level']-1)*12) . 'px;' : '' ?>">
                                        <a href="#<?= $toc['id'] ?>"><?= htmlspecialchars($toc['text']) ?></a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>

                    <?php if (!empty($popularArticles)): ?>
                        <div class="article-list-card">
                            <div class="article-rail-card" style="border:none; box-shadow:none; padding-bottom:.4rem;">
                                <span class="news-eyebrow">Trending Articles</span>
                                <h3>What readers are opening now</h3>
                            </div>
                            <?php foreach ($popularArticles as $popularArticle): ?>
                                <?php $popularDate = akkuNewsArticleDate($popularArticle); ?>
                                <div class="article-list-item">
                                    <a href="<?= htmlspecialchars(akkuNewsPublicUrl($popularArticle)) ?>"><?= htmlspecialchars((string) $popularArticle['title']) ?></a>
                                    <div class="muted-text">
                                        <?= htmlspecialchars((string) ($popularArticle['category'] ?? 'general')) ?>
                                        <?php if ($popularDate): ?> &middot; <?= date('M j', strtotime($popularDate)) ?><?php endif; ?>
                                        &middot; <?= number_format(akkuNewsViewCount($popularArticle)) ?> views
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($promoBlocks[2])): ?>
                        <div class="article-rail-card">
                            <span class="news-eyebrow"><?= htmlspecialchars($promoBlocks[2]['eyebrow']) ?></span>
                            <h3><?= htmlspecialchars($promoBlocks[2]['title']) ?></h3>
                            <p><?= htmlspecialchars($promoBlocks[2]['copy']) ?></p>
                            <div style="margin-top:.85rem;"><a class="btn btn-primary btn-sm" href="<?= htmlspecialchars($promoBlocks[2]['cta_href']) ?>"><?= htmlspecialchars($promoBlocks[2]['cta_label']) ?></a></div>
                        </div>
                    <?php endif; ?>

                    <!-- Ad Placement: Sidebar skyscraper -->
                    <div style="margin-top:1rem;">
                        <?= displayArticleAd('tier-skyscraper', $userRegion, $userLanguage) ?>
                    </div>

                    <?php if (!empty($relatedArticles)): ?>
                        <div class="article-list-card">
                            <div class="article-rail-card" style="border:none; box-shadow:none; padding-bottom:.4rem;">
                                <span class="news-eyebrow">More Like This</span>
                                <h3>Related reads</h3>
                            </div>
                            <?php foreach ($relatedArticles as $relatedArticle): ?>
                                <?php $relatedDate = akkuNewsArticleDate($relatedArticle); ?>
                                <div class="article-list-item">
                                    <a href="<?= htmlspecialchars(akkuNewsPublicUrl($relatedArticle)) ?>"><?= htmlspecialchars((string) $relatedArticle['title']) ?></a>
                                    <div class="muted-text">
                                        <?= htmlspecialchars(ucfirst(akkuNewsArticleType($relatedArticle))) ?>
                                        <?php if ($relatedDate): ?> &middot; <?= date('M j, Y', strtotime($relatedDate)) ?><?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    </main>
<?php if ($user): ?>
</div>
<?php endif; ?>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
<?= trackAdClickJs() ?>
</body>
</html>
