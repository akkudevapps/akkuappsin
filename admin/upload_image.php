<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/news-engine.php';

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => ['message' => 'Unauthorized']]);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['upload'])) {
    echo json_encode(['error' => ['message' => 'No file uploaded.']]);
    exit;
}

$file = $_FILES['upload'];
$folder = trim((string) ($_GET['folder'] ?? 'temp-' . date('Ymd')));

try {
    $baseDir = akkuNewsStorageBasePath();
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0755, true);
    }

    $result = akkuNewsUploadFile($file, $folder, $baseDir);

    if (isset($result['error'])) {
        echo json_encode(['error' => ['message' => $result['error']]]);
        exit;
    }

    echo json_encode([
        'url' => $result['url'],
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => ['message' => $e->getMessage()]]);
}
