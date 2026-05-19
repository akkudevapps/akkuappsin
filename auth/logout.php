<?php
// auth/logout.php
define('AKKUAPPS_LOADED', true);
require_once '../includes/functions.php';

// Clear session
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"] ?? false, $params["httponly"] ?? false);
}
session_destroy();

// Clear remember cookie
if (isset($_COOKIE['akku_remember'])) {
    setcookie('akku_remember', '', time() - 42000, '/');
}

header("Location: /");
exit;
?>