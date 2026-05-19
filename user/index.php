<?php
// user/index.php - Fixed version
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

// Check if user is logged in
$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

// Redirect to feed (this is the main user landing page)
header("Location: /user/feed.php");
exit;
?>
