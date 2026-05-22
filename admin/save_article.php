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

global $pdo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: news.php');
    exit;
}

try {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $articleType = trim($_POST['article_type'] ?? 'news');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $subtitle = trim($_POST['subtitle'] ?? '');
    $status = trim($_POST['status'] ?? 'draft');

    if ($title === '' || $content === '') {
        throw new Exception('Title and content are required.');
    }

    $slug = akkuNewsSlugify($title);
    if ($slug === '') {
        $slug = 'article-' . date('Ymd-His');
    }

    $columns = akkuNewsColumns($pdo);
    $folderColumn = akkuNewsFolderColumn($pdo);

    $folder = '';
    $payload = [];

    if (akkuHasColumn($columns, 'blog_id')) {
        $payload['blog_id'] = generateUUID();
    }
    if (akkuHasColumn($columns, 'author_id')) {
        $payload['author_id'] = $user['user_id'] ?? $user['id'] ?? null;
    }
    if (akkuHasColumn($columns, 'title')) {
        $payload['title'] = $title;
    }
    if (akkuHasColumn($columns, 'slug')) {
        $payload['slug'] = $slug;
    }
    if (akkuHasColumn($columns, 'content')) {
        $payload['content'] = $content;
    }
    if (akkuHasColumn($columns, 'excerpt')) {
        $payload['excerpt'] = mb_substr(strip_tags($content), 0, 200);
    }
    if (akkuHasColumn($columns, 'category')) {
        $payload['category'] = $articleType === 'blog' ? 'blog' : 'news';
    }
    if (akkuHasColumn($columns, 'status')) {
        $payload['status'] = $status;
    }

    $typeColumn = akkuNewsTypeColumn($pdo);
    if ($typeColumn && akkuHasColumn($columns, $typeColumn)) {
        $payload[$typeColumn] = $articleType;
    }

    if ($folderColumn && akkuHasColumn($columns, $folderColumn)) {
        $folder = akkuNewsEnsureStoragePath($slug);
        $payload[$folderColumn] = $folder;
    }

    if (akkuHasColumn($columns, 'created_at')) {
        $payload['created_at'] = date('Y-m-d H:i:s');
    }
    if (akkuHasColumn($columns, 'updated_at')) {
        $payload['updated_at'] = date('Y-m-d H:i:s');
    }

    $dateColumn = akkuNewsDateColumn($pdo);
    if ($status === 'published' && $dateColumn && akkuHasColumn($columns, $dateColumn)) {
        $payload[$dateColumn] = date('Y-m-d H:i:s');
    }

    // Handle featured image upload
    $featuredImage = '';
    if (!empty($_FILES['featured_image']['name']) && $_FILES['featured_image']['error'] === UPLOAD_ERR_OK) {
        $baseDir = akkuNewsStorageBasePath();
        $folderForUpload = $folder ?: $slug;
        $result = akkuNewsUploadFile($_FILES['featured_image'], $folderForUpload, $baseDir);
        if (!isset($result['error'])) {
            $featuredImage = $result['url'];
        }
    }

    if (akkuHasColumn($columns, 'featured_image') && $featuredImage !== '') {
        $payload['featured_image'] = $featuredImage;
    }

    $fields = array_keys($payload);
    $placeholders = array_fill(0, count($fields), '?');
    $values = array_values($payload);

    $sql = sprintf(
        'INSERT INTO news_blogs (%s) VALUES (%s)',
        implode(', ', $fields),
        implode(', ', $placeholders)
    );

    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    // Persist meta JSON
    $metaPayload = [
        'title' => $title,
        'slug' => $slug,
        'content' => $content,
        'subtitle' => $subtitle,
        'excerpt' => mb_substr(strip_tags($content), 0, 200),
        'article_type' => $articleType,
        'featured_image' => $featuredImage,
        'meta_description' => $metaDescription,
        'tags' => $tags,
        'status' => $status,
        'folder' => $folder,
    ];
    if ($folder !== '') {
        akkuNewsPersistMeta($folder, $metaPayload);
    }

    $_SESSION['news_success'] = 'Article saved successfully.';
    header('Location: news.php');
    exit;
} catch (Exception $e) {
    $_SESSION['news_error'] = $e->getMessage();
    header('Location: news.php');
    exit;
}
