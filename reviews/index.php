<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$category = trim((string) ($_GET['cat'] ?? ''));
$map = [
    'components' => 'hardware',
    'accessories' => 'deals',
    'laptops' => 'tech',
    'peripherals' => 'guides',
];

$target = '/news/?kind=blog';
if ($category !== '' && isset($map[$category])) {
    $target = '/news/?' . http_build_query([
        'kind' => 'blog',
        'category' => $map[$category],
    ]);
}

header('Location: ' . $target, true, 302);
exit;
