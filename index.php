<?php
// index.php - Updated with News, Reviews, Sales, Services sections
define('AKKUAPPS_LOADED', true);
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user is logged in
$user = getCurrentUser();

// If not logged in, show enhanced public homepage
if (!$user) {
    include 'public-index.php';
    exit;
}

// Redirect to appropriate dashboard based on role
if ($user['role'] === 'admin') {
    header("Location: /admin/dashboard.php");
} else {
    header("Location: /user/dashboard.php");
}
exit;
