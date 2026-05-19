<?php
// auth/login.php
// FIX: Added config.php include BEFORE functions.php (functions needs $pdo from config)
// FIX: Added proper error display for debugging
define('AKKUAPPS_LOADED', true);

require_once __DIR__ . '/../includes/config.php';   // ← MUST BE FIRST
require_once __DIR__ . '/../includes/functions.php';  // Needs $pdo from config

// Already logged in → redirect
if (isLoggedIn()) {
    $redirect = $_GET['redirect'] ?? '/';
    if (strpos($redirect, '/') !== 0 && strpos($redirect, 'akkuapps.in') === false) {
        $redirect = '/';
    }
    header("Location: " . $redirect);
    exit;
}

// ----------------------------------------------------------------
$error      = '';
$csrf_token = generateCSRFToken();
$redirect   = htmlspecialchars($_GET['redirect'] ?? '/', ENT_QUOTES, 'UTF-8');

// Google OAuth URL
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $_GET['redirect'] ?? '/',
]);

// ----------------------------------------------------------------
// Manual login POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manual_login'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request (CSRF token mismatch).";
    } else {
        $email    = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'] ?? '';

        if ($email && $password) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT user_id, email, password_hash, role,
                            is_verified, is_banned, name, avatar, coin_balance
                     FROM users
                     WHERE email = ? LIMIT 1"
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && hash('sha256', $password . ENCRYPTION_SALT) === $user['password_hash']) {
                    if (!empty($user['is_banned'])) {
                        $error = "Your account has been suspended. Contact support.";
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id']   = $user['user_id'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['logged_in_at'] = time();

                        // Update last login
                        try {
                            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?")
                                ->execute([$user['user_id']]);
                        } catch (PDOException $e) { /* column optional */ }

                        $dest = $_POST['redirect'] ?? '/';
                        if (strpos($dest, '/') !== 0 && strpos($dest, 'akkuapps.in') === false) {
                            $dest = '/';
                        }
                        header("Location: " . $dest);
                        exit;
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                error_log("Login DB error: " . $e->getMessage());
                $error = "A database error occurred. Please try again.";
            }
        } else {
            $error = "Please fill in both fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login – AkkuApps.in</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <style>
        :root {
            --bg: #08080c;
            --card: #0f0f14;
            --border: #1a1a22;
            --text: #a1a1aa;
            --text-bright: #ffffff;
            --accent: #6366f1;
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md">
    <div class="text-center mb-8">
        <a href="/" class="text-2xl font-extrabold text-white">
            akkuapps<span style="color:var(--accent)">.in</span>
        </a>
        <p class="text-sm mt-1" style="color:var(--text)">🔐 Secure Login</p>
    </div>

    <div class="rounded-2xl p-6 border shadow-xl" style="background:var(--card); border-color:var(--border);">
        <?php if ($error): ?>
        <div class="mb-4 p-3 bg-red-900/50 border border-red-700 rounded text-sm text-red-200">
            ⚠️ <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
<div class="mb-4 p-3 bg-yellow-900/50 border border-yellow-700 rounded text-sm text-yellow-200">
    ⚠️ <?php
        $msgs = [
            'no_code'      => 'Google login was cancelled.',
            'token_failed' => 'Google token exchange failed: ' . ($_GET['msg'] ?? 'Please try again.'),
            'no_email'     => 'Could not get your email from Google.',
            'banned'       => 'Your account has been suspended.',
        ];
        echo htmlspecialchars($msgs[$_GET['error']] ?? 'An error occurred: ' . ($_GET['msg'] ?? 'Please try again.'));
    ?>
</div>
<?php endif; ?>

        <!-- Google OAuth button -->
        <a href="<?= htmlspecialchars($googleAuthUrl) ?>"
           class="w-full flex items-center justify-center gap-3 bg-white text-gray-800 font-medium py-3 px-4 rounded-xl hover:bg-gray-100 transition shadow-md mb-4">
            <svg class="w-5 h-5" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            Continue with Google
        </a>

        <!-- OR separator -->
        <div class="relative my-4">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t" style="border-color:var(--border)"></div>
            </div>
            <div class="relative flex justify-center text-xs">
                <span class="px-2" style="background:var(--card); color:var(--text)">OR</span>
            </div>
        </div>

        <!-- Manual login form -->
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token"   value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="redirect"      value="<?= $redirect ?>">
            <input type="hidden" name="manual_login" value="1">

            <div>
                <label class="block text-xs mb-1" style="color:var(--text)">Email address</label>
                <input type="email" name="email" required autocomplete="email"
                       class="w-full rounded-lg px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       style="background:#1a1a22; border:1px solid var(--border);"
                       placeholder="you@example.com">
            </div>

            <div>
                <label class="block text-xs mb-1" style="color:var(--text)">Password</label>
                <input type="password" name="password" required autocomplete="current-password"
                       class="w-full rounded-lg px-4 py-2.5 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                       style="background:#1a1a22; border:1px solid var(--border);"
                       placeholder="••••••••">
            </div>

            <button type="submit"
                    class="w-full font-medium py-2.5 rounded-lg transition text-white"
                    style="background:var(--accent);">
                Sign In
            </button>
        </form>

        <p class="text-center text-xs mt-4" style="color:var(--text)">
            No account?
            <a href="/auth/register.php" style="color:var(--accent)">Create one</a>
        </p>
    </div>
</div>
</body>
</html>