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

if (isset($_GET['action']) && $_GET['action'] === 'upload_file') {
    header('Content-Type: application/json');
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'Upload error.']); exit;
    }
    $folder = trim((string) ($_GET['folder'] ?? ''));
    if ($folder === '') { echo json_encode(['error' => 'Folder required.']); exit; }
    echo json_encode(akkuNewsUploadFile($_FILES['file'], $folder, akkuNewsStorageBasePath()));
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
    $content = akkuNewsSanitizeHTML($content);
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
        .news-admin-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(320px, .9fr); gap: 1.5rem; }
        .metric-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .metric-card { padding: 1.25rem; border-radius: 20px; background: var(--card-bg); border: 1px solid var(--border-color); position: relative; overflow: hidden; }
        .metric-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
        .metric-card:nth-child(1)::before { background: linear-gradient(90deg, var(--primary), var(--primary-light)); }
        .metric-card:nth-child(2)::before { background: linear-gradient(90deg, var(--secondary), #34d399); }
        .metric-card:nth-child(3)::before { background: linear-gradient(90deg, var(--warning), #fbbf24); }
        .metric-card:nth-child(4)::before { background: linear-gradient(90deg, var(--purple), #c084fc); }
        .metric-card h3 { font-size: .85rem; color: var(--text-secondary); margin-bottom: .45rem; display: flex; align-items: center; gap: .5rem; }
        .metric-card strong { font-size: 1.8rem; color: var(--text-primary); }
        .metric-icon { width: 32px; height: 32px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: .85rem; }
        .metric-card:nth-child(1) .metric-icon { background: rgba(99,102,241,.12); color: var(--primary-light); }
        .metric-card:nth-child(2) .metric-icon { background: rgba(16,185,129,.12); color: var(--secondary); }
        .metric-card:nth-child(3) .metric-icon { background: rgba(245,158,11,.12); color: var(--warning); }
        .metric-card:nth-child(4) .metric-icon { background: rgba(168,85,247,.12); color: var(--purple); }
        .news-note { margin-top: .75rem; padding: 1rem 1.1rem; border-radius: 16px; background: rgba(59,130,246,.08); color: var(--text-secondary); border: 1px solid rgba(59,130,246,.18); }
        .engine-label { display: inline-flex; align-items: center; gap: .45rem; border-radius: 999px; padding: .45rem .8rem; background: rgba(16,185,129,.12); color: #9af2c8; font-size: .82rem; margin-bottom: .8rem; }
        .asset-links { display: grid; gap: .85rem; }
        .asset-link-card { padding: 1rem; border-radius: 16px; border: 1px solid var(--border-color); background: var(--card-bg); }
        .asset-link-card a { word-break: break-word; }
        .editor-actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 1rem; }
        .table-title { display: grid; gap: .15rem; }
        .table-title a { color: var(--text-primary); font-weight: 600; }
        .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 999px; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; }
        .status-badge.published { background: rgba(16,185,129,.12); color: #34d399; }
        .status-badge.draft { background: rgba(245,158,11,.12); color: #fbbf24; }
        .status-badge.archived { background: rgba(107,114,128,.12); color: #9ca3af; }
        .editor-wrapper { border: 1px solid var(--border-color); border-radius: var(--border-radius-lg); overflow: hidden; background: var(--bg-input); }
        .editor-wrapper:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,.1); }
        .editor-toolbar { display: flex; flex-wrap: wrap; gap: 2px; padding: 6px 8px; background: var(--bg-elevated); border-bottom: 1px solid var(--border-color); align-items: center; }
        .editor-toolbar-group { display: flex; gap: 1px; align-items: center; }
        .editor-toolbar-group + .editor-toolbar-group { margin-left: 4px; padding-left: 4px; border-left: 1px solid var(--border-color); }
        .editor-toolbar-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: none; background: transparent; color: var(--text-secondary); border-radius: 6px; cursor: pointer; font-size: .78rem; transition: all .15s; }
        .editor-toolbar-btn:hover { background: var(--bg-hover); color: var(--text-primary); }
        .editor-toolbar-btn.active { background: rgba(99,102,241,.15); color: var(--primary-light); }
        .editor-toolbar-btn i { font-size: .82rem; }
        .editor-toolbar-select { padding: 3px 6px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary); font-size: .78rem; cursor: pointer; height: 28px; }
        .editor-toolbar-select:focus { outline: none; border-color: var(--primary); }
        .editor-color-input { width: 26px; height: 26px; padding: 0; border: 1px solid var(--border-color); border-radius: 4px; cursor: pointer; background: none; }
        .editor-color-input::-webkit-color-swatch-wrapper { padding: 2px; }
        .editor-color-input::-webkit-color-swatch { border: none; border-radius: 3px; }
        .editor-body { min-height: 400px; max-height: 650px; padding: 1rem; overflow-y: auto; background: var(--bg-card); color: var(--text-primary); line-height: 1.7; font-size: .95rem; outline: none; }
        .editor-body:empty::before { content: attr(data-placeholder); color: var(--text-muted); pointer-events: none; }
        .editor-body h2 { font-size: 1.5em; font-weight: 700; margin: 1em 0 .5em; }
        .editor-body h3 { font-size: 1.25em; font-weight: 600; margin: .8em 0 .4em; }
        .editor-body h4 { font-size: 1.1em; font-weight: 600; margin: .6em 0 .3em; }
        .editor-body p { margin: .5em 0; }
        .editor-body blockquote { border-left: 4px solid var(--primary); padding-left: 1em; color: var(--text-secondary); font-style: italic; margin: 1em 0; }
        .editor-body pre { background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 8px; padding: 1em; overflow-x: auto; font-family: 'Cascadia Code','Fira Code','Consolas',monospace; font-size: .85rem; line-height: 1.5; }
        .editor-body pre code { background: none; padding: 0; font-size: inherit; color: inherit; }
        .editor-body code { background: rgba(99,102,241,.1); padding: 2px 6px; border-radius: 4px; font-family: 'Cascadia Code','Fira Code','Consolas',monospace; font-size: .85em; color: var(--primary-light); }
        .editor-body ul, .editor-body ol { padding-left: 1.5em; margin: .5em 0; }
        .editor-body ul { list-style: disc; }
        .editor-body ol { list-style: decimal; }
        .editor-body img { max-width: 100%; border-radius: 8px; margin: 1em 0; }
        .editor-body a { color: var(--primary-light); text-decoration: underline; }
        .editor-body table { border-collapse: collapse; width: 100%; margin: 1em 0; }
        .editor-body th, .editor-body td { border: 1px solid var(--border-color); padding: 8px 12px; text-align: left; }
        .editor-body th { background: var(--bg-elevated); font-weight: 600; }
        .editor-body hr { border: none; border-top: 1px solid var(--border-color); margin: 1.5em 0; }
        .editor-status-bar { display: flex; justify-content: space-between; align-items: center; padding: 4px 12px; background: var(--bg-elevated); border-top: 1px solid var(--border-color); font-size: .72rem; color: var(--text-muted); }
        .editor-status-bar .mode-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: .7rem; text-transform: uppercase; letter-spacing: .03em; }
        .mode-badge.visual { background: rgba(16,185,129,.12); color: #34d399; }
        .mode-badge.html { background: rgba(245,158,11,.12); color: #fbbf24; }
        .editor-html-source { width: 100%; min-height: 400px; max-height: 650px; padding: 1rem; background: #1a1b2e; color: #a8e6cf; border: none; font-family: 'Cascadia Code','Fira Code','Consolas',monospace; font-size: .85rem; line-height: 1.6; resize: vertical; outline: none; tab-size: 2; }
        .editor-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.6); backdrop-filter: blur(4px); z-index: 3000; display: none; align-items: center; justify-content: center; padding: 1rem; }
        .editor-modal-overlay.active { display: flex; }
        .editor-modal { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius-lg); width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-lg); }
        .editor-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--border-color); }
        .editor-modal-header h3 { font-size: 1rem; color: var(--text-primary); }
        .editor-modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer; padding: 4px; border-radius: 6px; }
        .editor-modal-close:hover { background: var(--bg-hover); color: var(--text-primary); }
        .editor-modal-body { padding: 1rem; }
        .editor-modal-footer { display: flex; justify-content: flex-end; gap: .5rem; padding: 1rem; border-top: 1px solid var(--border-color); }
        .file-drop-zone { border: 2px dashed var(--border-color); border-radius: 12px; padding: 1.5rem; text-align: center; transition: all .2s; cursor: pointer; }
        .file-drop-zone:hover, .file-drop-zone.dragover { border-color: var(--primary); background: rgba(99,102,241,.05); }
        .file-drop-zone i { font-size: 2rem; color: var(--text-muted); margin-bottom: .5rem; }
        .file-drop-zone p { color: var(--text-secondary); font-size: .85rem; }
        .file-drop-zone .hint { color: var(--text-muted); font-size: .75rem; margin-top: .25rem; }
        .uploaded-files-list { display: grid; gap: .5rem; margin-top: .75rem; max-height: 200px; overflow-y: auto; }
        .uploaded-file-item { display: flex; align-items: center; gap: .5rem; padding: .5rem; border-radius: 8px; background: var(--bg-elevated); font-size: .8rem; }
        .uploaded-file-item .file-icon { width: 28px; height: 28px; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: .7rem; flex-shrink: 0; }
        .uploaded-file-item .file-icon.images { background: rgba(16,185,129,.12); color: var(--secondary); }
        .uploaded-file-item .file-icon.documents { background: rgba(99,102,241,.12); color: var(--primary-light); }
        .uploaded-file-item .file-icon.audio { background: rgba(236,72,153,.12); color: var(--pink); }
        .uploaded-file-item .file-icon.videos { background: rgba(239,68,68,.12); color: var(--danger); }
        .uploaded-file-item .file-icon.files { background: rgba(245,158,11,.12); color: var(--warning); }
        .uploaded-file-item .file-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .uploaded-file-item .file-url { color: var(--text-muted); font-size: .7rem; }
        .uploaded-file-item .file-insert-btn { background: none; border: none; color: var(--primary-light); cursor: pointer; font-size: .75rem; padding: 2px 6px; border-radius: 4px; white-space: nowrap; }
        .uploaded-file-item .file-insert-btn:hover { background: rgba(99,102,241,.1); }
        .search-filter-bar { display: flex; flex-wrap: wrap; gap: .75rem; margin-bottom: 1rem; align-items: center; }
        .search-input-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-input-wrap i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: .8rem; }
        .search-input-wrap input { width: 100%; padding: 8px 10px 8px 32px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 10px; color: var(--text-primary); font-size: .85rem; }
        .search-input-wrap input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 2px rgba(99,102,241,.15); }
        .filter-select { padding: 8px 10px; background: var(--bg-input); border: 1px solid var(--border-color); border-radius: 10px; color: var(--text-primary); font-size: .85rem; cursor: pointer; }
        .action-dropdown { position: relative; display: inline-block; }
        .action-dropdown-menu { position: absolute; top: 100%; right: 0; min-width: 160px; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px; box-shadow: var(--shadow-lg); z-index: 100; display: none; overflow: hidden; }
        .action-dropdown.open .action-dropdown-menu { display: block; }
        .action-dropdown-item { display: flex; align-items: center; gap: .5rem; padding: 8px 12px; font-size: .8rem; color: var(--text-secondary); border: none; background: none; width: 100%; text-align: left; cursor: pointer; text-decoration: none; }
        .action-dropdown-item:hover { background: var(--bg-hover); color: var(--text-primary); }
        .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 4000; display: flex; flex-direction: column; gap: .5rem; }
        .toast { padding: .75rem 1rem; border-radius: 12px; background: var(--bg-card); border: 1px solid var(--border-color); box-shadow: var(--shadow-lg); display: flex; align-items: center; gap: .5rem; font-size: .85rem; animation: slideInRight .3s ease; min-width: 250px; }
        .toast.success { border-left: 3px solid var(--secondary); }
        .toast.error { border-left: 3px solid var(--danger); }
        .toast.info { border-left: 3px solid var(--info); }
        .workspace-tabs { display: flex; gap: 2px; border-bottom: 1px solid var(--border-color); margin-bottom: 1rem; }
        .workspace-tab { padding: 8px 14px; font-size: .8rem; color: var(--text-muted); background: none; border: none; cursor: pointer; border-bottom: 2px solid transparent; transition: all .15s; }
        .workspace-tab:hover { color: var(--text-secondary); }
        .workspace-tab.active { color: var(--primary-light); border-bottom-color: var(--primary); }
        .workspace-panel { display: none; }
        .workspace-panel.active { display: block; animation: fadeIn .2s ease; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .create-article-form { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius-lg); padding: 1.5rem; }
        .create-article-form .form-section { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
        .create-article-form .form-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .form-section-title { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .form-section-title i { font-size: 0.8rem; color: var(--primary-light); }
        .form-group small { font-size: 0.75rem; color: var(--text-muted); display: block; margin-top: 0.25rem; }
        .form-control::placeholder { color: var(--text-muted); opacity: 0.7; }
        .help-panel { position: fixed; right: -380px; top: 0; width: 380px; height: 100vh; background: var(--bg-card); border-left: 1px solid var(--border-color); overflow-y: auto; z-index: 500; transition: right 0.3s ease; box-shadow: -2px 0 8px rgba(0,0,0,0.1); }
        .help-panel.open { right: 0; }
        .help-panel-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; border-bottom: 1px solid var(--border-color); position: sticky; top: 0; background: var(--bg-card); z-index: 10; }
        .help-panel-header h3 { font-size: 1.1rem; color: var(--text-primary); margin: 0; }
        .help-panel-close { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); padding: 4px 8px; border-radius: 6px; }
        .help-panel-close:hover { background: var(--bg-hover); color: var(--text-primary); }
        .preview-body { padding: 1.5rem; line-height: 1.8; color: var(--text-primary); overflow-y: auto; max-height: 60vh; }
        .preview-body h2 { font-size: 1.5em; font-weight: 700; margin: 1em 0 .5em; }
        .preview-body h3 { font-size: 1.25em; font-weight: 600; margin: .8em 0 .4em; }
        .preview-body h4 { font-size: 1.1em; font-weight: 600; margin: .6em 0 .3em; }
        .preview-body p { margin: .5em 0; }
        .preview-body blockquote { border-left: 4px solid var(--primary); padding-left: 1em; color: var(--text-secondary); font-style: italic; margin: 1em 0; }
        .preview-body pre { background: var(--bg-elevated); border: 1px solid var(--border-color); border-radius: 8px; padding: 1em; overflow-x: auto; font-family: 'Cascadia Code','Fira Code','Consolas',monospace; font-size: .85rem; }
        .preview-body pre code { background: none; padding: 0; }
        .preview-body code { background: rgba(99,102,241,.1); padding: 2px 6px; border-radius: 4px; font-family: 'Cascadia Code','Fira Code','Consolas',monospace; font-size: .85em; color: var(--primary-light); }
        .preview-body img { max-width: 100%; border-radius: 8px; margin: 1em 0; }
        .preview-body a { color: var(--primary-light); text-decoration: underline; }
        .preview-body ul, .preview-body ol { padding-left: 1.5em; margin: .5em 0; }
        .preview-body table { border-collapse: collapse; width: 100%; margin: 1em 0; }
        .preview-body th, .preview-body td { border: 1px solid var(--border-color); padding: 8px 12px; text-align: left; }
        .preview-body th { background: var(--bg-elevated); font-weight: 600; }
        .preview-body hr { border: none; border-top: 1px solid var(--border-color); margin: 1.5em 0; }
        .preview-body .language-label { display: inline-block; font-size: .7rem; color: var(--text-muted); background: var(--bg-elevated); padding: 2px 8px; border-radius: 4px; margin-bottom: .5rem; font-family: monospace; }
        .help-panel-content { padding: 1.5rem; }
        .help-section { margin-bottom: 2rem; }
        .help-section h4 { font-size: 0.95rem; color: var(--text-primary); font-weight: 600; margin: 0 0 0.75rem 0; display: flex; align-items: center; gap: 0.5rem; }
        .help-section h4 i { color: var(--primary); font-size: 0.85rem; }
        .help-section p { font-size: 0.85rem; color: var(--text-muted); line-height: 1.6; margin: 0 0 0.5rem 0; }
        .help-section ul { font-size: 0.85rem; color: var(--text-muted); margin: 0; padding-left: 1.2rem; line-height: 1.6; }
        .help-section li { margin-bottom: 0.4rem; }
        .help-section code { background: var(--bg-hover); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; color: var(--primary-light); font-family: 'Courier New', monospace; }
        .help-toggle-btn { position: fixed; right: 20px; bottom: 20px; width: 56px; height: 56px; border-radius: 50%; background: var(--primary); color: white; border: none; cursor: pointer; font-size: 1.3rem; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(99,102,241,0.4); z-index: 499; transition: all 0.3s ease; }
        .help-toggle-btn:hover { transform: scale(1.1); box-shadow: 0 6px 16px rgba(99,102,241,0.5); }
        .help-toggle-btn.hidden { opacity: 0; pointer-events: none; }
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @media (max-width: 1100px) {
            .news-admin-grid { grid-template-columns: 1fr; }
            .metric-grid { grid-template-columns: repeat(2, 1fr); }
            .help-panel { width: 320px; right: -320px; }
        }
        @media (max-width: 768px) {
            .metric-grid { grid-template-columns: repeat(2, 1fr); gap: .5rem; }
            .metric-card { padding: .85rem; }
            .metric-card strong { font-size: 1.4rem; }
            .search-filter-bar { flex-direction: column; }
            .search-input-wrap { min-width: 100%; }
            .filter-select { width: 100%; }
            .editor-actions { flex-direction: column; }
            .editor-actions .btn { width: 100%; }
            .form-grid { grid-template-columns: 1fr; }
            table, thead, tbody, th, td, tr { display: block; }
            thead tr { position: absolute; left: -9999px; top: -9999px; }
            tr { border: 1px solid var(--border-color); margin-bottom: .75rem; border-radius: 12px; overflow: hidden; }
            td { display: flex; justify-content: space-between; align-items: center; padding: .6rem 1rem; border-bottom: 1px solid var(--border-color); }
            td:last-child { border-bottom: none; }
            td::before { content: attr(data-label); font-weight: 600; color: var(--text-secondary); font-size: .75rem; text-transform: uppercase; }
            .workspace-tabs { overflow-x: auto; }
            .create-article-form { padding: 1rem; }
            .help-panel { width: 100vw; right: -100vw; }
            .page-shell { padding: .75rem !important; }
            .welcome-banner h1 { font-size: 1.3rem !important; }
            .chart-container { padding: 1rem !important; }
            .chart-container h2 { font-size: 1.1rem !important; }
        }
        @media (max-width: 480px) {
            .metric-grid { grid-template-columns: 1fr 1fr; }
            .create-article-form { padding: .75rem; }
            .news-note { padding: .6rem; font-size: .75rem; }
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
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-layer-group"></i></span> Total Articles</h3><strong><?= (int) $stats['total'] ?></strong></div>
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-check-circle"></i></span> Published</h3><strong><?= (int) $stats['published'] ?></strong></div>
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-pen-nib"></i></span> Drafts</h3><strong><?= (int) $stats['draft'] ?></strong></div>
                <div class="metric-card"><h3><span class="metric-icon"><i class="fas fa-star"></i></span> Featured</h3><strong><?= (int) $stats['featured'] ?></strong></div>
            </div>

            <div class="news-admin-grid">
                <section class="chart-container">
                    <h2><?= $editorArticle ? 'Edit Article Engine' : 'Create Article' ?></h2>
                    <div class="news-note">
                        `Blog` is best for guides, opinions, and interactive content. `News` is for reporting, announcements, and current updates.
                    </div>
                    <form method="POST" class="create-article-form">
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
                            <div class="editor-wrapper">
                                <div class="editor-toolbar" id="editorToolbar">
                                    <div class="editor-toolbar-group">
                                        <select class="editor-toolbar-select" id="formatBlock" title="Format">
                                            <option value="">Format</option>
                                            <option value="p">Paragraph</option>
                                            <option value="h2">Heading 2</option>
                                            <option value="h3">Heading 3</option>
                                            <option value="h4">Heading 4</option>
                                            <option value="pre">Code Block</option>
                                            <option value="blockquote">Quote</option>
                                        </select>
                                    </div>
                                    <div class="editor-toolbar-group">
                                        <button class="editor-toolbar-btn" title="Bold" data-cmd="bold"><i class="fas fa-bold"></i></button>
                                        <button class="editor-toolbar-btn" title="Italic" data-cmd="italic"><i class="fas fa-italic"></i></button>
                                        <button class="editor-toolbar-btn" title="Underline" data-cmd="underline"><i class="fas fa-underline"></i></button>
                                        <button class="editor-toolbar-btn" title="Strikethrough" data-cmd="strikeThrough"><i class="fas fa-strikethrough"></i></button>
                                    </div>
                                    <div class="editor-toolbar-group">
                                        <select class="editor-toolbar-select" id="fontFamily" title="Font Family">
                                            <option value="">Font</option>
                                            <option value="Arial, sans-serif">Arial</option>
                                            <option value="Georgia, serif">Georgia</option>
                                            <option value="Tahoma, sans-serif">Tahoma</option>
                                            <option value="Times New Roman, serif">Times</option>
                                            <option value="Verdana, sans-serif">Verdana</option>
                                            <option value="Courier New, monospace">Courier</option>
                                            <option value="Noto Sans Tamil, sans-serif">Tamil</option>
                                        </select>
                                        <select class="editor-toolbar-select" id="fontSize" title="Font Size">
                                            <option value="">Size</option>
                                            <option value="1">XS</option>
                                            <option value="3">Small</option>
                                            <option value="5">Medium</option>
                                            <option value="7">Large</option>
                                            <option value="9">XL</option>
                                        </select>
                                        <button class="editor-toolbar-btn" title="Decrease Font Size" data-cmd="fontSizeDecrease"><i class="fas fa-minus"></i></button>
                                        <button class="editor-toolbar-btn" title="Increase Font Size" data-cmd="fontSizeIncrease"><i class="fas fa-plus"></i></button>
                                    </div>
                                    <div class="editor-toolbar-group">
                                        <label class="editor-toolbar-btn" title="Text Color" style="cursor:pointer;"><input type="color" id="foreColor" class="editor-color-input" value="#ffffff"></label>
                                        <label class="editor-toolbar-btn" title="Background Color" style="cursor:pointer;"><input type="color" id="backColor" class="editor-color-input" value="#6366f1"></label>
                                    </div>
                                    <div class="editor-toolbar-group">
                                        <button class="editor-toolbar-btn" title="Bulleted List" data-cmd="insertUnorderedList"><i class="fas fa-list-ul"></i></button>
                                        <button class="editor-toolbar-btn" title="Numbered List" data-cmd="insertOrderedList"><i class="fas fa-list-ol"></i></button>
                                        <button class="editor-toolbar-btn" title="Outdent" data-cmd="outdent"><i class="fas fa-outdent"></i></button>
                                        <button class="editor-toolbar-btn" title="Indent" data-cmd="indent"><i class="fas fa-indent"></i></button>
                                    </div>
                                    <div class="editor-toolbar-group">
                                        <button class="editor-toolbar-btn" title="Insert Image" id="editorInsertImage"><i class="fas fa-image"></i></button>
                                        <button class="editor-toolbar-btn" title="Insert Link" id="editorInsertLink"><i class="fas fa-link"></i></button>
                                        <button class="editor-toolbar-btn" title="Insert Code" id="editorInsertCode"><i class="fas fa-code"></i></button>
                                        <button class="editor-toolbar-btn" title="Horizontal Rule" data-cmd="insertHorizontalRule"><i class="fas fa-minus"></i></button>
                                    </div>
                                    <div class="editor-toolbar-group">
                                        <button class="editor-toolbar-btn" title="Undo" data-cmd="undo"><i class="fas fa-undo"></i></button>
                                        <button class="editor-toolbar-btn" title="Redo" data-cmd="redo"><i class="fas fa-redo"></i></button>
                                    </div>
                                    <div class="editor-toolbar-group" style="margin-left:auto;">
                                        <button class="editor-toolbar-btn" title="HTML Source" id="toggleHtmlMode"><i class="fas fa-code"></i></button>
                                        <button class="editor-toolbar-btn" title="Preview" id="previewBtn"><i class="fas fa-eye"></i></button>
                                    </div>
                                </div>
                                <div id="editorVisualWrap">
                                    <div id="editorBody" class="editor-body" contenteditable="true" data-placeholder="Write your article content here..."><?= newsFieldValue($editorArticle ?? [], 'content') ?></div>
                                </div>
                                <div id="editorHtmlWrap" style="display:none;">
                                    <textarea id="editorHtmlSource" class="editor-html-source" placeholder="<!-- HTML source -->"><?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'content')) ?></textarea>
                                </div>
                                <div class="editor-status-bar">
                                    <span>Words: <strong id="wordCount">0</strong></span>
                                    <span><span class="mode-badge visual" id="modeBadge">Visual</span></span>
                                    <span>Chars: <strong id="charCount">0</strong></span>
                                </div>
                                <textarea id="contentEditor" name="content" required style="display:none;"><?= htmlspecialchars((string) newsFieldValue($editorArticle ?? [], 'content')) ?></textarea>
                            </div>
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
                        <div class="workspace-tabs">
                            <button class="workspace-tab active" data-panel="overview">Overview</button>
                            <button class="workspace-tab" data-panel="assets">Assets</button>
                            <button class="workspace-tab" data-panel="meta">Meta</button>
                        </div>
                        <div class="workspace-panel active" id="panel-overview">
                            <div class="activity-list" style="margin-top:.5rem;">
                                <div class="activity-item">
                                    <div class="activity-copy">
                                        <strong><?= htmlspecialchars((string) ($selectedArticle['title'] ?? 'Untitled')) ?></strong>
                                        <small>
                                            <?= htmlspecialchars(ucfirst(akkuNewsArticleType($selectedArticle))) ?>
                                            ΓÇó <span class="status-badge <?= $selectedArticle['status'] ?? 'draft' ?>"><?= htmlspecialchars((string) ($selectedArticle['status'] ?? 'draft')) ?></span>
                                            ΓÇó <?= htmlspecialchars((string) ($selectedArticle['category'] ?? 'general')) ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="info-note">
                                    <?= nl2br(htmlspecialchars((string) ($selectedArticle['excerpt'] ?: 'No summary added yet.'))) ?>
                                </div>
                                <div class="surface-card">
                                    <strong>Publication</strong>
                                    <p class="muted-text" style="margin-top:.55rem;">
                                        <?= htmlspecialchars((string) ($selectedArticle['author_name'] ?? 'Admin')) ?>
                                        <?php if ($articleDate): ?> ΓÇó <?= date('M j, Y g:i A', strtotime($articleDate)) ?><?php endif; ?>
                                    </p>
                                </div>
                                <div class="surface-card">
                                    <strong>Body Preview</strong>
                                    <p style="margin-top:.75rem; color:var(--text-secondary); line-height:1.7;"><?= nl2br(htmlspecialchars(mb_substr(strip_tags((string) ($selectedArticle['content'] ?? '')), 0, 300))) ?><?= mb_strlen(strip_tags((string) ($selectedArticle['content'] ?? ''))) > 300 ? '...' : '' ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="workspace-panel" id="panel-assets">
                            <div class="asset-links" style="margin-top:.5rem;">
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
                        <div class="workspace-panel" id="panel-meta">
                            <div style="margin-top:.5rem;">
                                <div class="surface-card">
                                    <strong>Article Folder</strong>
                                    <p class="muted-text" style="margin-top:.55rem;">
                                        <?= htmlspecialchars((string) (($selectedArticle['article_folder'] ?? '') !== '' ? '/uploads/newsroom/' . $selectedArticle['article_folder'] . '/' : 'Folder will be created on save.')) ?>
                                    </p>
                                    <p class="muted-text" style="margin-top:.4rem;">
                                        JSON meta file: <?= htmlspecialchars((string) (($selectedArticle['article_folder'] ?? '') !== '' ? '/uploads/newsroom/' . $selectedArticle['article_folder'] . '/article.json' : 'Not available yet')) ?>
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
                    <div class="search-filter-bar" style="margin-top:1rem;">
                        <div class="search-input-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="articleSearch" placeholder="Search articles by title, author, or category...">
                        </div>
                        <select class="filter-select" id="filterType">
                            <option value="">All Types</option>
                            <option value="news">News</option>
                            <option value="blog">Blog</option>
                        </select>
                        <select class="filter-select" id="filterStatus">
                            <option value="">All Status</option>
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>
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
                            <tbody id="articlesTableBody">
                            <?php foreach ($articles as $article): ?>
                                <?php $articleId = (string) ($newsLookupColumn && isset($article[$newsLookupColumn]) ? $article[$newsLookupColumn] : ''); ?>
                                <?php $articleDate = akkuNewsArticleDate($article); ?>
                                <?php $artType = ucfirst(akkuNewsArticleType($article)); ?>
                                <?php $artStatus = $article['status'] ?? 'draft'; ?>
                                <tr data-type="<?= strtolower($artType) ?>" data-status="<?= $artStatus ?>" data-search="<?= strtolower(htmlspecialchars(($article['title'] ?? '') . ' ' . ($article['author_name'] ?? '') . ' ' . ($article['category'] ?? ''))) ?>">
                                    <td data-label="Article">
                                        <div class="table-title">
                                            <a href="/admin/news.php?view=<?= urlencode($articleId) ?>"><?= htmlspecialchars((string) ($article['title'] ?? 'Untitled')) ?></a>
                                            <span class="muted-text"><?= htmlspecialchars((string) ($article['author_name'] ?? 'Admin')) ?></span>
                                        </div>
                                    </td>
                                    <td data-label="Type"><?= htmlspecialchars($artType) ?></td>
                                    <td data-label="Category"><?= htmlspecialchars((string) ($article['category'] ?? '-')) ?></td>
                                    <td data-label="Status"><span class="status-badge <?= $artStatus ?>"><?= htmlspecialchars($artStatus) ?></span></td>
                                    <td data-label="Featured"><?= !empty($article['is_featured']) ? '<span class="status-badge published">Yes</span>' : '<span class="status-badge archived">No</span>' ?></td>
                                    <td data-label="Date"><?= $articleDate ? date('M j, Y', strtotime($articleDate)) : '-' ?></td>
                                    <td data-label="Actions">
                                        <div class="action-dropdown">
                                            <button class="btn btn-secondary btn-sm" data-toggle-dropdown><i class="fas fa-ellipsis-v"></i> Actions</button>
                                            <div class="action-dropdown-menu">
                                                <a class="action-dropdown-item" href="/admin/news.php?edit=<?= urlencode($articleId) ?>"><i class="fas fa-pen"></i> Edit</a>
                                                <a class="action-dropdown-item" href="/admin/news.php?view=<?= urlencode($articleId) ?>"><i class="fas fa-eye"></i> View</a>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="article_key" value="<?= htmlspecialchars($articleId) ?>">
                                                    <input type="hidden" name="status" value="<?= $artStatus === 'published' ? 'draft' : 'published' ?>">
                                                    <button class="action-dropdown-item" type="submit" name="update_status"><i class="fas fa-sync"></i> Toggle <?= $artStatus === 'published' ? 'Draft' : 'Publish' ?></button>
                                                </form>
                                                <?php if (akkuHasColumn($newsColumns, 'is_featured')): ?>
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="article_key" value="<?= htmlspecialchars($articleId) ?>">
                                                    <button class="action-dropdown-item" type="submit" name="toggle_featured"><i class="fas fa-star"></i> Toggle Featured</button>
                                                </form>
                                                <?php endif; ?>
                                                <a class="action-dropdown-item" href="<?= htmlspecialchars(akkuNewsPublicUrl($article)) ?>" target="_blank"><i class="fas fa-up-right-from-square"></i> Open Public</a>
                                            </div>
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

        <!-- Link Modal -->
        <div class="editor-modal-overlay" id="linkModal">
            <div class="editor-modal">
                <div class="editor-modal-header">
                    <h3><i class="fas fa-link"></i> Insert Link</h3>
                    <button class="editor-modal-close" data-close="linkModal">&times;</button>
                </div>
                <div class="editor-modal-body">
                    <div class="form-group">
                        <label class="form-label">URL</label>
                        <input class="form-control" type="url" id="linkUrl" placeholder="https://example.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Link Text</label>
                        <input class="form-control" type="text" id="linkText" placeholder="Display text (leave empty to use selected text)">
                    </div>
                </div>
                <div class="editor-modal-footer">
                    <button class="btn btn-ghost" data-close="linkModal">Cancel</button>
                    <button class="btn btn-primary" id="linkInsertBtn">Insert</button>
                </div>
            </div>
        </div>

        <!-- Code Block Modal -->
        <div class="editor-modal-overlay" id="codeModal">
            <div class="editor-modal" style="max-width:560px;">
                <div class="editor-modal-header">
                    <h3><i class="fas fa-code"></i> Insert Code Block</h3>
                    <button class="editor-modal-close" data-close="codeModal">&times;</button>
                </div>
                <div class="editor-modal-body">
                    <div class="form-group">
                        <label class="form-label">Language</label>
                        <select class="form-control" id="codeLang">
                            <option value="">Plain Text</option>
                            <option value="python">Python</option>
                            <option value="csharp">C#</option>
                            <option value="css">CSS</option>
                            <option value="php">PHP</option>
                            <option value="xaml">XAML</option>
                            <option value="cshtml">CSHTML</option>
                            <option value="js">JavaScript</option>
                            <option value="c">C</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Code</label>
                        <textarea class="form-control" id="codeContent" rows="10" style="font-family:'Cascadia Code','Fira Code','Consolas',monospace;font-size:.85rem;" placeholder="Paste your code here..."></textarea>
                    </div>
                </div>
                <div class="editor-modal-footer">
                    <button class="btn btn-ghost" data-close="codeModal">Cancel</button>
                    <button class="btn btn-primary" id="codeInsertBtn">Insert</button>
                </div>
            </div>
        </div>

        <!-- Preview Modal -->
        <div class="editor-modal-overlay" id="previewModal">
            <div class="editor-modal" style="max-width:720px;">
                <div class="editor-modal-header">
                    <h3><i class="fas fa-eye"></i> Article Preview</h3>
                    <button class="editor-modal-close" data-close="previewModal">&times;</button>
                </div>
                <div class="editor-modal-body" style="padding:0;">
                    <div class="preview-body" id="previewBody"></div>
                </div>
                <div class="editor-modal-footer">
                    <button class="btn btn-primary" data-close="previewModal">Close Preview</button>
                </div>
            </div>
        </div>

        <!-- File Upload Modal -->
        <div class="editor-modal-overlay" id="fileUploadModal">
            <div class="editor-modal" style="max-width:520px;">
                <div class="editor-modal-header">
                    <h3><i class="fas fa-paperclip"></i> Upload File</h3>
                    <button class="editor-modal-close" data-close="fileUploadModal">&times;</button>
                </div>
                <div class="editor-modal-body">
                    <div class="file-drop-zone" id="fileDropZone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop files here or click to browse</p>
                        <span class="hint">Max 10MB ┬╖ Images, Documents, Audio, Video, Archives</span>
                    </div>
                    <input type="file" id="fileInput" style="display:none;" multiple>
                    <div class="uploaded-files-list" id="uploadedFilesList"></div>
                </div>
                <div class="editor-modal-footer">
                    <button class="btn btn-ghost" data-close="fileUploadModal">Close</button>
                </div>
            </div>
        </div>

        <!-- Toast Container -->
        <div class="toast-container" id="toastContainer"></div>
    </main>
</div>
<script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
<script>
(function() {
    'use strict';

    // --- Custom Editor ---
    const editorBody = document.getElementById('editorBody');
    const htmlSource = document.getElementById('editorHtmlSource');
    const htmlWrap = document.getElementById('editorHtmlWrap');
    const visualWrap = document.getElementById('editorVisualWrap');
    const hiddenTextarea = document.getElementById('contentEditor');
    const toggleHtmlBtn = document.getElementById('toggleHtmlMode');
    const modeBadge = document.getElementById('modeBadge');
    const previewBtn = document.getElementById('previewBtn');
    const previewBody = document.getElementById('previewBody');
    let isHtmlMode = false;

    // Modal helpers
    function openModal(id) { document.getElementById(id).classList.add('active'); }
    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    // Insert HTML at cursor in contentEditable
    function insertHtml(html) {
        editorBody.focus();
        if (isHtmlMode) {
            htmlSource.value += html;
            return;
        }
        document.execCommand('insertHTML', false, html);
    }

    // Update word/char counts
    function updateCounts() {
        const text = isHtmlMode ? htmlSource.value : (editorBody.innerText || '');
        const html = isHtmlMode ? htmlSource.value : editorBody.innerHTML;
        document.getElementById('wordCount').textContent = text.trim() ? text.trim().split(/\s+/).length : 0;
        document.getElementById('charCount').textContent = text.length;
        hiddenTextarea.value = html;
    }

    // Format block handler
    document.getElementById('formatBlock').addEventListener('change', function() {
        const val = this.value;
        if (!val) return;
        if (val === 'pre') {
            document.execCommand('insertHTML', false, '<pre><code>' + (window.getSelection().toString() || '') + '</code></pre>');
        } else if (val === 'blockquote') {
            document.execCommand('formatBlock', false, 'blockquote');
        } else {
            document.execCommand('formatBlock', false, '<' + val + '>');
        }
        this.value = '';
        updateCounts();
    });

    // Toolbar command buttons
    document.querySelectorAll('.editor-toolbar-btn[data-cmd]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const cmd = this.dataset.cmd;
            if (isHtmlMode) return;
            if (cmd === 'fontSizeIncrease') {
                document.execCommand('increaseFontSize');
            } else if (cmd === 'fontSizeDecrease') {
                document.execCommand('decreaseFontSize');
            } else {
                document.execCommand(cmd);
            }
            editorBody.focus();
            updateCounts();
        });
    });

    // Font family
    document.getElementById('fontFamily').addEventListener('change', function() {
        if (isHtmlMode) return;
        document.execCommand('fontName', false, this.value);
        editorBody.focus();
        this.value = '';
    });

    // Font size
    document.getElementById('fontSize').addEventListener('change', function() {
        if (isHtmlMode) return;
        document.execCommand('fontSize', false, this.value);
        editorBody.focus();
        this.value = '';
    });

    // Text color
    document.getElementById('foreColor').addEventListener('input', function() {
        if (isHtmlMode) return;
        document.execCommand('foreColor', false, this.value);
        editorBody.focus();
    });

    // Background color
    document.getElementById('backColor').addEventListener('input', function() {
        if (isHtmlMode) return;
        document.execCommand('hiliteColor', false, this.value);
        editorBody.focus();
    });

    // Help panel
    window.toggleHelpPanel = function() {
        const panel = document.getElementById('helpPanel');
        const toggle = document.getElementById('helpToggle');
        panel.classList.toggle('open');
        toggle.classList.toggle('hidden');
    };

    // Mute non-editor keys in html mode
    editorBody.addEventListener('keyup', updateCounts);
    htmlSource.addEventListener('input', updateCounts);

    // Visual / HTML Source toggle
    toggleHtmlBtn.addEventListener('click', function() {
        isHtmlMode = !isHtmlMode;
        if (isHtmlMode) {
            htmlSource.value = editorBody.innerHTML;
            visualWrap.style.display = 'none';
            htmlWrap.style.display = '';
            modeBadge.textContent = 'HTML';
            modeBadge.className = 'mode-badge html';
        } else {
            editorBody.innerHTML = htmlSource.value;
            visualWrap.style.display = '';
            htmlWrap.style.display = 'none';
            modeBadge.textContent = 'Visual';
            modeBadge.className = 'mode-badge visual';
        }
        updateCounts();
    });

    // Insert Link handler
    document.getElementById('editorInsertLink').addEventListener('click', function() {
        if (isHtmlMode) return;
        const sel = window.getSelection().toString();
        document.getElementById('linkText').value = sel;
        document.getElementById('linkUrl').value = '';
        openModal('linkModal');
        setTimeout(function() { document.getElementById('linkUrl').focus(); }, 100);
    });

    document.getElementById('linkInsertBtn').addEventListener('click', function() {
        const url = document.getElementById('linkUrl').value.trim();
        const text = document.getElementById('linkText').value.trim();
        if (!url) { showToast('Please enter a URL.', 'error'); return; }
        if (text) {
            insertHtml('<a href="' + url.replace(/"/g,'&quot;') + '" target="_blank">' + text.replace(/</g,'&lt;') + '</a>');
        } else {
            document.execCommand('createLink', false, url);
        }
        closeModal('linkModal');
        updateCounts();
    });

    // Insert Code Block handler
    document.getElementById('editorInsertCode').addEventListener('click', function() {
        if (isHtmlMode) return;
        document.getElementById('codeContent').value = window.getSelection().toString();
        document.getElementById('codeLang').value = '';
        openModal('codeModal');
        setTimeout(function() { document.getElementById('codeContent').focus(); }, 100);
    });

    document.getElementById('codeInsertBtn').addEventListener('click', function() {
        const code = document.getElementById('codeContent').value;
        const lang = document.getElementById('codeLang').value;
        if (!code) { showToast('Please enter code.', 'error'); return; }
        const langAttr = lang ? ' class="language-' + lang + '"' : '';
        const label = lang ? '<div class="language-label">' + lang + '</div>' : '';
        insertHtml('<pre><code' + langAttr + '>' + code.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</code></pre>');
        closeModal('codeModal');
        updateCounts();
    });

    // Insert Image -> open file upload modal
    document.getElementById('editorInsertImage').addEventListener('click', function() {
        if (isHtmlMode) return;
        openModal('fileUploadModal');
    });

    // Preview handler
    previewBtn.addEventListener('click', function() {
        const html = isHtmlMode ? htmlSource.value : editorBody.innerHTML;
        previewBody.innerHTML = html;
        openModal('previewModal');
    });

    // Form submit sync
    const form = hiddenTextarea.closest('form');
    if (form) {
        form.addEventListener('submit', function() {
            if (isHtmlMode) {
                editorBody.innerHTML = htmlSource.value;
            }
            hiddenTextarea.value = isHtmlMode ? htmlSource.value : editorBody.innerHTML;
        });
    }

    // Initial count
    updateCounts();

    const folder = '<?= htmlspecialchars((string) ($editorFolder ?: ($editorArticle ? ($selectedArticle['article_folder'] ?? '') : ''))) ?>';

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const panel = document.getElementById('helpPanel');
            if (panel.classList.contains('open')) {
                window.toggleHelpPanel();
            }
        }
    });

    document.querySelectorAll('[data-close]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.getElementById(this.dataset.close).classList.remove('active');
        });
    });

    document.querySelectorAll('.editor-modal-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.classList.remove('active');
        });
    });

    const dropZone = document.getElementById('fileDropZone');
    const fileInput = document.getElementById('fileInput');
    const filesList = document.getElementById('uploadedFilesList');

    if (dropZone) {
        dropZone.addEventListener('click', function() { fileInput.click(); });
        dropZone.addEventListener('dragover', function(e) { e.preventDefault(); dropZone.classList.add('dragover'); });
        dropZone.addEventListener('dragleave', function() { dropZone.classList.remove('dragover'); });
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault(); dropZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });
        fileInput.addEventListener('change', function() { handleFiles(this.files); });
    }

    function handleFiles(files) {
        Array.from(files).forEach(function(file) {
            if (file.size > 10 * 1024 * 1024) { showToast(file.name + ' exceeds 10MB limit.', 'error'); return; }
            const fd = new FormData();
            fd.append('file', file);
            const item = document.createElement('div');
            item.className = 'uploaded-file-item';
            item.innerHTML = '<div class="file-icon files"><i class="fas fa-spinner fa-spin"></i></div><div class="file-name">' + file.name + '</div><div class="file-url">Uploading...</div>';
            filesList.prepend(item);
            fetch('?action=upload_file&folder=' + encodeURIComponent(folder), { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.error) { item.querySelector('.file-url').textContent = data.error; item.querySelector('.file-icon').innerHTML = '<i class="fas fa-exclamation-triangle"></i>'; return; }
                    const ext = data.url.split('.').pop().toLowerCase();
                    const typeMap = { jpg:'images',jpeg:'images',png:'images',gif:'images',webp:'images',svg:'images', pdf:'documents',doc:'documents',docx:'documents',txt:'documents', mp3:'audio',wav:'audio', mp4:'videos',webm:'videos', zip:'files',rar:'files' };
                    const ftype = typeMap[ext] || 'files';
                    const iconMap = { images:'fa-image', documents:'fa-file-alt', audio:'fa-music', videos:'fa-video', files:'fa-file-archive' };
                    item.querySelector('.file-icon').className = 'file-icon ' + ftype;
                    item.querySelector('.file-icon').innerHTML = '<i class="fas ' + (iconMap[ftype]||'fa-file') + '"></i>';
                    item.querySelector('.file-url').innerHTML = '<button class="file-insert-btn" data-url="' + data.url + '" data-name="' + data.filename + '" data-type="' + ftype + '">Insert</button>';
                    item.querySelector('.file-insert-btn').addEventListener('click', function() {
                        const u = this.dataset.url, n = this.dataset.name, t = this.dataset.type;
                        const editorBody = document.getElementById('editorBody');
                        if (editorBody) {
                            editorBody.focus();
                            if (t === 'images') {
                                document.execCommand('insertHTML', false, '<img src="' + u.replace(/"/g,'&quot;') + '" alt="' + n.replace(/"/g,'&quot;') + '" style="max-width:100%">');
                            } else {
                                document.execCommand('insertHTML', false, '<a href="' + u.replace(/"/g,'&quot;') + '" target="_blank">' + n.replace(/"/g,'&quot;') + '</a>');
                            }
                            updateCounts();
                        }
                        showToast('File inserted: ' + n, 'success');
                    });
                    showToast('Uploaded: ' + data.filename, 'success');
                })
                .catch(function() { item.querySelector('.file-url').textContent = 'Upload failed.'; });
        });
    }

    function showToast(msg, type) {
        const c = document.getElementById('toastContainer');
        const t = document.createElement('div');
        t.className = 'toast ' + (type || 'info');
        t.innerHTML = '<i class="fas fa-' + (type==='success'?'check-circle':type==='error'?'exclamation-circle':'info-circle') + '"></i> ' + msg;
        c.appendChild(t);
        setTimeout(function() { t.style.opacity = '0'; t.style.transform = 'translateX(100%)'; t.style.transition = 'all .3s'; setTimeout(function() { t.remove(); }, 300); }, 3000);
    }

    const searchInput = document.getElementById('articleSearch');
    const filterType = document.getElementById('filterType');
    const filterStatus = document.getElementById('filterStatus');
    function filterArticles() {
        const q = (searchInput?.value || '').toLowerCase();
        const ft = filterType?.value || '';
        const fs = filterStatus?.value || '';
        document.querySelectorAll('#articlesTableBody tr').forEach(function(tr) {
            const match = (!q || tr.dataset.search.includes(q)) && (!ft || tr.dataset.type === ft) && (!fs || tr.dataset.status === fs);
            tr.style.display = match ? '' : 'none';
        });
    }
    if (searchInput) searchInput.addEventListener('input', filterArticles);
    if (filterType) filterType.addEventListener('change', filterArticles);
    if (filterStatus) filterStatus.addEventListener('change', filterArticles);

    document.addEventListener('click', function(e) {
        const dd = e.target.closest('[data-toggle-dropdown]');
        if (dd) { dd.closest('.action-dropdown').classList.toggle('open'); return; }
        if (!e.target.closest('.action-dropdown')) document.querySelectorAll('.action-dropdown.open').forEach(function(d) { d.classList.remove('open'); });
    });

    document.querySelectorAll('.workspace-tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            const panelId = 'panel-' + this.dataset.panel;
            this.closest('.chart-container').querySelectorAll('.workspace-tab').forEach(function(t) { t.classList.remove('active'); });
            this.closest('.chart-container').querySelectorAll('.workspace-panel').forEach(function(p) { p.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById(panelId).classList.add('active');
        });
    });
})();
</script>
</body>
</html>
