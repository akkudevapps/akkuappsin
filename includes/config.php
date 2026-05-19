<?php
// ---------------------------------------------------------------------------
// includes/config.php – Central configuration for AkkuApps
// FIX: Removed duplicate function definitions that conflicted with security.php
// FIX: Added AKKUAPPS_LOADED guard
// ---------------------------------------------------------------------------
if (!defined('AKKUAPPS_LOADED')) {
    exit('Direct access not allowed');
}

// ---------------------------------------------------
// Site URL
if (!defined('SITE_URL')) {
    define('SITE_URL', 'https://akkuapps.in');
}

// ---------------------------------------------------
// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'akkuapps_maui');
define('DB_USER', 'akkuapps_maui');
define('DB_PASS', 'akkuapps_maui');

// ---------------------------------------------------
// PDO connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    exit('Database connection failed – please contact support.');
}

// ---------------------------------------------------
// Google OAuth credentials
define('GOOGLE_CLIENT_ID',     '53669726947-nrechs9q0vr4s1onqbuaijt5851qbd79.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI',   SITE_URL . '/auth/google-callback.php');

// ---------------------------------------------------
// Miscellaneous constants
define('UPI_ID',          '8778217176@ptaxis');
define('ENCRYPTION_SALT', 'akkuapps.in');   // keep secret

// ---------------------------------------------------
// Helper functions (defined ONCE here, NOT duplicated in security.php)

if (!function_exists('hashEmail')) {
    function hashEmail(string $email): string {
        return hash('sha256', strtolower(trim($email)));
    }
}

if (!function_exists('hashPassword')) {
    function hashPassword(string $password): string {
        return hash('sha256', $password . ENCRYPTION_SALT);
    }
}

if (!function_exists('generateUUID')) {
    function generateUUID(): string {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}