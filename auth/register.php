<?php
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

// --------------------------------------------------------------------
// Build the clean Google‑OAuth URL (single line, no embedded new‑lines)
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => '/',           // after a successful sign‑up we send them home
]);

?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register – AkkuApps.in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
</head>
<body class="bg-gray-900 text-gray-200 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md text-center">
    <a href="/" class="text-2xl font-extrabold text-white">
        akkuapps<span class="text-indigo-400">.in</span>
    </a>

    <div class="bg-gray-800 rounded-2xl p-8 border border-gray-700 shadow-xl mt-6">
        <h2 class="text-xl font-bold mb-4">✨ Create Account</h2>
        <p class="text-gray-400 text-sm mb-6">Sign up with Google for instant access.</p>

        <a href="<?= htmlspecialchars($googleAuthUrl) ?>"
           class="w-full flex items-center justify-center gap-3 bg-white text-gray-800 font-medium py-3 px-4 rounded-xl hover:bg-gray-100 transition shadow-md">
            <svg class="w-5 h-5" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            Sign up with Google
        </a>

        <p class="text-xs text-gray-500 mt-6">
            By signing up, you agree to our
            <a href="/refund-policy.php" class="text-indigo-400 hover:underline">Terms</a>.
        </p>

        <p class="mt-4"><a href="/auth/login.php"
                           class="text-indigo-400 hover:underline text-sm">
            ← Already have an account? Login
        </a></p>
    </div>
</div>
</body>
</html>
