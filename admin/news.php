<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/news-engine.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit;
}

$message = '';
$error = '';
$viewId = trim((string) ($_GET['view'] ?? ''));
$editId = trim((string) ($_GET['edit'] ?? ''));

global $pdo;
$newsColumns = akkuNewsColumns($pdo);
$userIdColumn = akkuUsersIdColumn($pdo);
$newsIdColumn = akkuNewsIdColumn($pdo);
$newsLookupColumn = akkuNewsLookupColumn($pdo);
$newsDateColumn = akkuNewsDateColumn($pdo);
$newsTypeColumn = akkuNewsTypeColumn($pdo);
$newsFolderColumn = akkuNewsFolderColumn($pdo);
$authorJoin = akkuNewsAuthorJoin($pdo, 'b', 'u');

if (empty($newsColumns)) {
    $error = 'The `news_blogs` table is missing or inaccessible.';
}

function newsFieldValue(array $source, string $field, $default = '')
{
    return array_key_exists($field, $source) ? $source[$field] : $default;
}

function newsBuildPayload(array $columns, array $user, string $folderColumn, string $typeColumn, bool $isCreate): array
{
    $title = trim((string) ($_POST['title'] ?? ''));
    $slug = trim((string) ($_POST['slug'] ?? ''));
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $content = trim((string) ($_POST['content'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? 'general'));
    $articleType = trim((string) ($_POST['article_type'] ?? 'news'));
    $featuredImage = trim((string) ($_POST['featured_image'] ?? ''));
    $documentUrl = trim((string) ($_POST['document_url'] ?? ''));
    $referenceLink = trim((string) ($_POST['reference_link'] ?? ''));
    $seoTitle = trim((string) ($_POST['seo_title'] ?? ''));
    $seoDescription = trim((string) ($_POST['seo_description'] ?? ''));
    $status = trim((string) ($_POST['status'] ?? 'draft'));
    $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
    $existingFolder = trim((string) ($_POST['existing_folder'] ?? ''));

    if ($title === '' || $content === '') {
        throw new Exception('Title and content are required.');
    }

    if ($slug === '') {
        $slug = akkuNewsSlugify($title);
    } else {
        $slug = akkuNewsSlugify($slug);
    }

    $folder = akkuNewsEnsureStoragePath($slug, $existingFolder);
    $payload = [];

    if ($isCreate && akkuHasColumn($columns, 'blog_id')) {
        $payload['blog_id'] = generateUUID();
    }
    if ($isCreate && akkuHasColumn($columns, 'author_id')) {
        $payload['author_id'] = $user['user_id'] ?? $user['id'] ?? null;
    }
    if (akkuHasColumn($columns, 'title')) {
        $payload['title'] = $title;
    }
    if (akkuHasColumn($columns, 'slug')) {
        $payload['slug'] = $slug;
    }
    if (akkuHasColumn($columns, 'excerpt')) {
        $payload['excerpt'] = $excerpt;
    }
    if (akkuHasColumn($columns, 'content')) {
        $payload['content'] = $content;
    }
    if (akkuHasColumn($columns, 'category')) {
        $payload['category'] = $category;
    }
    if ($typeColumn && akkuHasColumn($columns, $typeColumn)) {
        $payload[$typeColumn] = $articleType;
    }
    if (akkuHasColumn($columns, 'featured_image')) {
        $payload['featured_image'] = $featuredImage;
    }
    if (akkuHasColumn($columns, 'document_url')) {
        $payload['document_url'] = $documentUrl;
    }
    if (akkuHasColumn($columns, 'reference_link')) {
        $payload['reference_link'] = $referenceLink;
    }
    if (akkuHasColumn($columns, 'seo_title')) {
        $payload['seo_title'] = $seoTitle;
    }
    if (akkuHasColumn($columns, 'seo_description')) {
        $payload['seo_description'] = $seoDescription;
    }
    if (akkuHasColumn($columns, 'is_featured')) {
        $payload['is_featured'] = $isFeatured;
    }
    if (akkuHasColumn($columns, 'status')) {
        $payload['status'] = $status;
    }
    if ($folderColumn && akkuHasColumn($columns, $folderColumn)) {
        $payload[$folderColumn] = $folder;
    }

    return [
        'db' => $payload,
        'meta' => [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'content' => $content,
            'category' => $category,
            'article_type' => $articleType,
            'featured_image' => $featuredImage,
            'document_url' => $documentUrl,
            'reference_link' => $referenceLink,
            'seo_title' => $seoTitle,
            'seo_description' => $seoDescription,
            'status' => $status,
            'is_featured' => $isFeatured,
            'folder' => $folder,
        ],
    ];
}

function newsInsertArticle(PDO $pdo, array $columns, array $payload, string $dateColumn): void
{
    $insertPayload = $payload;
    $nowFields = [];

    if (akkuHasColumn($columns, 'created_at')) {
        $nowFields['created_at'] = 'NOW()';
    }
    if (akkuHasColumn($columns, 'updated_at')) {
        $nowFields['updated_at'] = 'NOW()';
    }
    if (
        $dateColumn === 'published_at' &&
        akkuHasColumn($columns, 'published_at') &&
        (($insertPayload['status'] ?? 'draft') === 'published')
    ) {
        $insertPayload['published_at'] = date('Y-m-d H:i:s');
    }

    $fields = array_keys($insertPayload);
    $placeholders = [];
    $values = [];

    foreach ($fields as $field) {
        $placeholders[] = '?';
        $values[] = $insertPayload[$field];
    }

    foreach ($nowFields as $field => $expression) {
        $fields[] = $field;
        $placeholders[] = $expression;
    }

    $sql = sprintf(
        'INSERT INTO news_blogs (%s) VALUES (%s)',
        implode(', ', $fields),
        implode(', ', $placeholders)
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function newsUpdateArticle(PDO $pdo, array $columns, array $payload, string $lookupColumn, string $articleKey): void
{
    $assignments = [];
    $values = [];

    foreach ($payload as $field => $value) {
        $assignments[] = "{$field} = ?";
        $values[] = $value;
    }

    if (akkuHasColumn($columns, 'updated_at')) {
        $assignments[] = 'updated_at = NOW()';
    }
    if (
        akkuHasColumn($columns, 'published_at') &&
        array_key_exists('status', $payload) &&
        $payload['status'] === 'published'
    ) {
        $assignments[] = 'published_at = COALESCE(published_at, NOW())';
    }

    $values[] = $articleKey;
    $stmt = $pdo->prepare("UPDATE news_blogs SET " . implode(', ', $assignments) . " WHERE {$lookupColumn} = ?");
    $stmt->execute($values);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    try {
        if (isset($_POST['create_article'])) {
            $articlePayload = newsBuildPayload($newsColumns, $user, (string) $newsFolderColumn, (string) $newsTypeColumn, true);
            newsInsertArticle($pdo, $newsColumns, $articlePayload['db'], (string) $newsDateColumn);
            akkuNewsPersistMeta($articlePayload['meta']['folder'], $articlePayload['meta']);
            $message = 'Article created successfully.';
        }

        if (isset($_POST['update_article'])) {
            $articleKey = trim((string) ($_POST['article_key'] ?? ''));
            if ($articleKey === '' || !$newsLookupColumn) {
                throw new Exception('Missing article identifier for update.');
            }

            $articlePayload = newsBuildPayload($newsColumns, $user, (string) $newsFolderColumn, (string) $newsTypeColumn, false);
            newsUpdateArticle($pdo, $newsColumns, $articlePayload['db'], $newsLookupColumn, $articleKey);
            akkuNewsPersistMeta($articlePayload['meta']['folder'], $articlePayload['meta']);
            $message = 'Article updated successfully.';
            $editId = $articleKey;
            $viewId = $articleKey;
        }

        if (isset($_POST['update_status'])) {
            $articleKey = trim((string) ($_POST['article_key'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? 'draft'));
            if ($articleKey !== '' && $newsLookupColumn && akkuHasColumn($newsColumns, 'status')) {
                $assignments = ['status = ?'];
                if (akkuHasColumn($newsColumns, 'updated_at')) {
                    $assignments[] = 'updated_at = NOW()';
                }
                if ($status === 'published' && akkuHasColumn($newsColumns, 'published_at')) {
                    $assignments[] = 'published_at = COALESCE(published_at, NOW())';
                }
                $stmt = $pdo->prepare("UPDATE news_blogs SET " . implode(', ', $assignments) . " WHERE {$newsLookupColumn} = ?");
                $stmt->execute([$status, $articleKey]);
                $message = 'Article status updated.';
            }
        }

        if (isset($_POST['toggle_featured']) && akkuHasColumn($newsColumns, 'is_featured')) {
            $articleKey = trim((string) ($_POST['article_key'] ?? ''));
            if ($articleKey !== '' && $newsLookupColumn) {
                $assignments = ['is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END'];
                if (akkuHasColumn($newsColumns, 'updated_at')) {
                    $assignments[] = 'updated_at = NOW()';
                }
                $stmt = $pdo->prepare("UPDATE news_blogs SET " . implode(', ', $assignments) . " WHERE {$newsLookupColumn} = ?");
                $stmt->execute([$articleKey]);
                $message = 'Featured flag updated.';
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$articles = [];
$selectedArticle = null;
$stats = [
    'total' => 0,
    'published' => 0,
    'draft' => 0,
    'featured' => 0,
];

if (empty($error)) {
    try {
        $dateSelect = akkuNewsDateSelect($pdo, 'b');
        $orderBy = akkuNewsOrderBy($pdo, 'b');
        $typeSelect = $newsTypeColumn ? "b.{$newsTypeColumn} AS article_type_value," : '';
        $folderSelect = $newsFolderColumn ? "b.{$newsFolderColumn} AS article_folder," : "'' AS article_folder,";

        $sql = "
            SELECT b.*, {$typeSelect} {$folderSelect} {$dateSelect}, u.name AS author_name
            FROM news_blogs b
            LEFT JOIN users u ON {$authorJoin}
        ";
        if ($orderBy !== '') {
            $sql .= " ORDER BY {$orderBy}";
        }

        $articles = $pdo->query($sql)->fetchAll();

        foreach ($articles as $article) {
            $stats['total']++;
            if (($article['status'] ?? '') === 'published') {
                $stats['published']++;
            } else {
                $stats['draft']++;
            }
            if (!empty($article['is_featured'])) {
                $stats['featured']++;
            }
        }
    } catch (Exception $e) {
        $articles = [];
        $error = 'Unable to load articles: ' . $e->getMessage();
    }
}

if ($viewId !== '' || $editId !== '') {
    $lookupId = $viewId !== '' ? $viewId : $editId;
    foreach ($articles as $article) {
        $candidateId = (string) ($newsLookupColumn && isset($article[$newsLookupColumn]) ? $article[$newsLookupColumn] : '');
        if ($candidateId === $lookupId) {
            $selectedArticle = $article;
            break;
        }
    }
}

$editorArticle = $selectedArticle;
$editorFolder = $editorArticle['article_folder'] ?? '';
$editorType = $selectedArticle ? akkuNewsArticleType($selectedArticle) : 'news';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News Engine - AkkuApps Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .news-admin-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.4fr) minmax(320px, .9fr);
            gap: 1.5rem;
        }
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .metric-card {
            padding: 1.25rem;
            border-radius: 20px;
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
        }
        .metric-card h3 {
            font-size: .85rem;
            color: var(--text-secondary);
            margin-bottom: .45rem;
        }
        .metric-card strong {
            font-size: 1.8rem;
            color: var(--text-primary);
        }
        .news-note {
            margin-top: .75rem;
            padding: 1rem 1.1rem;
            border-radius: 16px;
            background: rgba(59, 130, 246, .08);
            color: var(--text-secondary);
            border: 1px solid rgba(59, 130, 246, .18);
        }
        .engine-label {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border-radius: 999px;
            padding: .45rem .8rem;
            background: rgba(16, 185, 129, .12);
            color: #9af2c8;
            font-size: .82rem;
            margin-bottom: .8rem;
        }
        .asset-links {
            display: grid;
            gap: .85rem;
        }
        .asset-link-card {
            padding: 1rem;
            border-radius: 16px;
            border: 1px solid var(--border-color);
            background: var(--surface-bg);
        }
        .asset-link-card a {
            word-break: break-word;
        }
        .editor-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-top: 1rem;
        }
        .table-title {
            display: grid;
            gap: .15rem;
        }
        .table-title a {
            color: var(--text-primary);
            font-weight: 600;
        }
        @media (max-width: 1100px) {
            .news-admin-grid,
            .metric-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<?php include '../components/admin-header.php'; ?>
<div class="dashboard-container">
    <?php include '../components/admin-sidebar.php'; ?>
    <main class="main-content">
        <div class="page-shell">
            <div class="welcome-banner">
                <span class="engine-label"><i class="fas fa-newspaper"></i> Core Blog & News Generator</span>
                <h1>Newsroom Engine Dashboard</h1>
                <p>Run blogs and news from one admin hub with separate article space for image links, document references, source links, SEO, and a dedicated folder for each post.</p>
            </div>

            <?php if ($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>

            <div class="metric-grid">
                <div class="metric-card"><h3>Total Articles</h3><strong><?= (int) $stats['total'] ?></strong></div>
                <div class="metric-card"><h3>Published</h3><strong><?= (int) $stats['published'] ?></strong></div>
                <div class="metric-card"><h3>Drafts</h3><strong><?= (int) $stats['draft'] ?></strong></div>
                <div class="metric-card"><h3>Featured</h3><strong><?= (int) $stats['featured'] ?></strong></div>
            </div>

            <div class="news-admin-grid">
                <section class="chart-container">
                    <h2><?= $editorArticle ? 'Edit Article Engine' : 'Create Article' ?></h2>
                    <div class="news-note">
                        `Blog` is best for guides, opinions, and interactive content. `News` is for reporting, announcements, and current updates.
                    </div>
                    <form method="POST" style="margin-top:1rem;">
                        <?php if ($editorArticle): ?>
                            <input type="hidden" name="update_article" value="1">
                            <input type="hidden" name="article_key" value="<?= htmlspecialchars((string) ($newsLookupColumn && isset($editorArticle[$newsLookupColumn]) ? $editorArticle[$newsLookupColumn] : '')) ?>">
                            <input type="hidden" name="existing_folder" value="<?= htmlspecialchars((string) $editorFolder) ?>">
                        <?php else: ?>
                            <input type="hidden" name="create_article" value="1">
                        <?php endif; ?>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Title</label>
                                <input class="form-control" type="text" name="title" required value="<?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'title')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">URL Slug</label>
                                <input class="form-control" type="text" name="slug" value="<?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'slug')) ?>" placeholder="auto-from-title">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Article Type</label>
                                <select class="form-control" name="article_type">
                                    <option value="news" <?= $editorType === 'news' ? 'selected' : '' ?>>News</option>
                                    <option value="blog" <?= $editorType === 'blog' ? 'selected' : '' ?>>Blog</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Status</label>
                                <select class="form-control" name="status">
                                    <option value="draft" <?= newsFieldValue($editorArticle ?? [], 'status', 'draft') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="published" <?= newsFieldValue($editorArticle ?? [], 'status') === 'published' ? 'selected' : '' ?>>Published</option>
                                    <option value="archived" <?= newsFieldValue($editorArticle ?? [], 'status') === 'archived' ? 'selected' : '' ?>>Archived</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <input class="form-control" type="text" name="category" value="<?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'category', 'general')) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Article Folder</label>
                                <input class="form-control" type="text" value="<?= htmlspecialchars((string) ($editorFolder ?: 'Will be created automatically')) ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Excerpt / Summary</label>
                            <textarea class="form-control" name="excerpt" rows="3"><?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'excerpt')) ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Main Content</label>
                            <textarea class="form-control" name="content" rows="12" required><?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'content')) ?></textarea>
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Featured Image</label>
                                <input class="form-control" type="text" name="featured_image" value="<?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'featured_image')) ?>" placeholder="/uploads/newsroom/article/cover.png">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Document URL</label>
                                <input class="form-control" type="text" name="document_url" value="<?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'document_url')) ?>" placeholder="PDF, Drive, Docs link">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Reference Link</label>
                                <input class="form-control" type="text" name="reference_link" value="<?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'reference_link')) ?>" placeholder="source or citation URL">
                            </div>
                            <div class="form-group">
                                <label class="form-label">SEO Title</label>
                                <input class="form-control" type="text" name="seo_title" value="<?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'seo_title')) ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">SEO Description</label>
                            <textarea class="form-control" name="seo_description" rows="3"><?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'seo_description')) ?></textarea>
                        </div>

                        <label class="toolbar-row muted-text" style="margin-bottom:1rem;">
                            <input type="checkbox" name="is_featured" value="1" <?= !empty($editorArticle['is_featured']) ? 'checked' : '' ?>>
                            Show in homepage featured section
                        </label>

                        <div class="editor-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                <?= $editorArticle ? 'Update Article' : 'Create Article' ?>
                            </button>
                            <?php if ($editorArticle): ?>
                                <a class="btn btn-secondary" href="/admin/news.php"><i class="fas fa-plus"></i> New Article</a>
                                <a class="btn btn-secondary" href="<?= htmlspecialchars(akkuNewsPublicUrl($editorArticle)) ?>" target="_blank"><i class="fas fa-up-right-from-square"></i> Open Public View</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>

                <section class="chart-container">
                    <h2>Article Workspace</h2>
                    <?php if ($selectedArticle): ?>
                        <?php $articleDate = akkuNewsArticleDate($selectedArticle); ?>
                        <div class="activity-list" style="margin-top:1rem;">
                            <div class="activity-item">
                                <div class="activity-copy">
                                    <strong><?= htmlspecialchars((string) ($selectedArticle['title'] ?? 'Untitled')) ?></strong>
                                    <small>
                                        <?= htmlspecialchars(ucfirst(akkuNewsArticleType($selectedArticle))) ?>
                                        • <?= htmlspecialchars((string) ($selectedArticle['status'] ?? 'draft')) ?>
                                        • <?= htmlspecialchars((string) ($selectedArticle['category'] ?? 'general')) ?>
                                    </small>
                                </div>
                            </div>
                            <div class="info-note">
                                <?= nl2br(htmlspecialchars((string) ($selectedArticle['excerpt'] ?: 'No summary added yet.'))) ?>
                            </div>
                            <div class="surface-card">
                                <strong>Article Folder</strong>
                                <p class="muted-text" style="margin-top:.55rem;">
                                    <?= htmlspecialchars((string) (($selectedArticle['article_folder'] ?? '') !== '' ? '/uploads/newsroom/' . $selectedArticle['article_folder'] . '/' : 'Folder will be created on save.')) ?>
                                </p>
                                <p class="muted-text" style="margin-top:.4rem;">
                                    JSON meta file: <?= htmlspecialchars((string) (($selectedArticle['article_folder'] ?? '') !== '' ? '/uploads/newsroom/' . $selectedArticle['article_folder'] . '/article.json' : 'Not available yet')) ?>
                                </p>
                            </div>
                            <div class="surface-card">
                                <strong>Publication</strong>
                                <p class="muted-text" style="margin-top:.55rem;">
                                    <?= htmlspecialchars((string) ($selectedArticle['author_name'] ?? 'Admin')) ?>
                                    <?php if ($articleDate): ?> • <?= date('M j, Y g:i A', strtotime($articleDate)) ?><?php endif; ?>
                                </p>
                            </div>
                            <div class="surface-card">
                                <strong>Body</strong>
                                <p style="margin-top:.75rem; color:var(--text-secondary); line-height:1.7;"><?= nl2br(htmlspecialchars((string) ($selectedArticle['content'] ?? ''))) ?></p>
                            </div>
                            <div class="asset-links">
                                <div class="asset-link-card">
                                    <strong>Featured Image</strong>
                                    <p class="muted-text" style="margin-top:.5rem;">
                                        <?php if (!empty($selectedArticle['featured_image'])): ?>
                                            <a href="<?= htmlspecialchars((string) $selectedArticle['featured_image']) ?>" target="_blank"><?= htmlspecialchars((string) $selectedArticle['featured_image']) ?></a>
                                        <?php else: ?>
                                            Not set
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="asset-link-card">
                                    <strong>Document Reference</strong>
                                    <p class="muted-text" style="margin-top:.5rem;">
                                        <?php if (!empty($selectedArticle['document_url'])): ?>
                                            <a href="<?= htmlspecialchars((string) $selectedArticle['document_url']) ?>" target="_blank"><?= htmlspecialchars((string) $selectedArticle['document_url']) ?></a>
                                        <?php else: ?>
                                            Not set
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="asset-link-card">
                                    <strong>Source Link</strong>
                                    <p class="muted-text" style="margin-top:.5rem;">
                                        <?php if (!empty($selectedArticle['reference_link'])): ?>
                                            <a href="<?= htmlspecialchars((string) $selectedArticle['reference_link']) ?>" target="_blank"><?= htmlspecialchars((string) $selectedArticle['reference_link']) ?></a>
                                        <?php else: ?>
                                            Not set
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="margin-top:1rem;">Choose an article from the table to inspect its full workspace, folder path, and attached references.</div>
                    <?php endif; ?>
                </section>
            </div>

            <section class="chart-container" style="margin-top:1.5rem;">
                <h2>All Blog & News Articles</h2>
                <?php if (empty($articles)): ?>
                    <div class="empty-state" style="margin-top:1rem;">No articles found yet.</div>
                <?php else: ?>
                    <div class="table-responsive" style="margin-top:1rem;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Article</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Featured</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($articles as $article): ?>
                                <?php $articleId = (string) ($newsLookupColumn && isset($article[$newsLookupColumn]) ? $article[$newsLookupColumn] : ''); ?>
                                <?php $articleDate = akkuNewsArticleDate($article); ?>
                                <tr>
                                    <td>
                                        <div class="table-title">
                                            <a href="/admin/news.php?view=<?= urlencode($articleId) ?>"><?= htmlspecialchars((string) ($article['title'] ?? 'Untitled')) ?></a>
                                            <span class="muted-text"><?= htmlspecialchars((string) ($article['author_name'] ?? 'Admin')) ?></span>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars(ucfirst(akkuNewsArticleType($article))) ?></td>
                                    <td><?= htmlspecialchars((string) ($article['category'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string) ($article['status'] ?? 'draft')) ?></td>
                                    <td><?= !empty($article['is_featured']) ? 'Yes' : 'No' ?></td>
                                    <td><?= $articleDate ? date('M j, Y', strtotime($articleDate)) : '-' ?></td>
                                    <td>
                                        <div class="toolbar-row">
                                            <a class="btn btn-secondary btn-sm" href="/admin/news.php?edit=<?= urlencode($articleId) ?>"><i class="fas fa-pen"></i> Edit</a>
                                            <form method="POST">
                                                <input type="hidden" name="article_key" value="<?= htmlspecialchars($articleId) ?>">
                                                <input type="hidden" name="status" value="<?= ($article['status'] ?? '') === 'published' ? 'draft' : 'published' ?>">
                                                <button class="btn btn-secondary btn-sm" type="submit" name="update_status">Toggle Status</button>
                                            </form>
                                            <?php if (akkuHasColumn($newsColumns, 'is_featured')): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="article_key" value="<?= htmlspecialchars($articleId) ?>">
                                                    <button class="btn btn-secondary btn-sm" type="submit" name="toggle_featured">Toggle Featured</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
