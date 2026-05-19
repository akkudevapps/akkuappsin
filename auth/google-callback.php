<?php
define('AKKUAPPS_LOADED', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle Google OAuth code
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Exchange code for tokens
    $tokenResponse = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query([
                'code' => $code,
                'client_id' => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'redirect_uri' => GOOGLE_REDIRECT_URI,
                'grant_type' => 'authorization_code'
            ])
        ]
    ]));

    $tokens = json_decode($tokenResponse, true);
    if (!isset($tokens['access_token'])) {
        header("Location: /auth/login.php?error=Google+token+failed");
        exit;
    }

    // Get user info from Google
    $userInfo = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokens['access_token']), true);

    if (!isset($userInfo['email'])) {
        header("Location: /auth/login.php?error=Invalid+Google+response");
        exit;
    }

    global $pdo;

    // Find existing user by email
    $email = $userInfo['email'];
    $emailHash = hashEmail($email);
    $stmt = $pdo->prepare("SELECT user_id, is_banned FROM users WHERE email = ? OR email_hash = ? LIMIT 1");
    $stmt->execute([$email, $emailHash]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        // Check if banned
        if (!empty($existingUser['is_banned']) && $existingUser['is_banned'] == 1) {
            header("Location: /auth/login.php?error=Account+suspended");
            exit;
        }
        $userId = $existingUser['user_id'];

        // Update last_login and google_id
        $pdo->prepare("UPDATE users SET last_login = NOW(), google_id = ? WHERE user_id = ?")
            ->execute([$userInfo['id'] ?? null, $userId]);
    } else {
        // Create new user with proper UUID
        $userId = generateUUID();
        
        // Only give welcome bonus once
        $newUserCoins = 100.0000;

        $stmt = $pdo->prepare("
            INSERT INTO users 
            (user_id, google_id, email, email_hash, name, avatar, role, is_banned, coin_balance, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'user', 0, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $userInfo['id'] ?? null,
            $email,
            $emailHash,
            $userInfo['name'] ?? 'User',
            $userInfo['picture'] ?? null,
            $newUserCoins
        ]);

        // Record welcome bonus transaction
        $pdo->prepare("
            INSERT INTO coin_transactions 
            (txn_id, user_id, reference_type, amount, balance_after, description, created_at)
            VALUES (?, ?, 'new_user', ?, ?, 'Welcome bonus for new Google OAuth user', NOW())
        ")->execute([generateUUID(), $userId, $newUserCoins, $newUserCoins]);
    }

    // Create secure session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['logged_in_at'] = time();

    header("Location: /");
    exit;
}
