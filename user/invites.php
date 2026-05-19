<?php
// user/invites.php - Invite management system
define('AKKUAPPS_LOADED', true);
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user = getCurrentUser();
if (!$user) {
    header("Location: /auth/login.php");
    exit;
}

$message = '';
$error = '';

// Handle invite creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invite'])) {
    $inviteeEmail = trim($_POST['invitee_email'] ?? '');
    $inviteePhone = trim($_POST['invitee_phone'] ?? '');
    
    if (empty($inviteeEmail) && empty($inviteePhone)) {
        $error = "Please provide email or phone number";
    } else {
        try {
            global $pdo;
            
            // Check if invite already exists
            $stmt = $pdo->prepare("
                SELECT invite_id FROM user_invites 
                WHERE inviter_id = ? AND (invitee_email = ? OR invitee_phone = ?) AND status = 'pending'
            ");
            $stmt->execute([$user['user_id'], $inviteeEmail, $inviteePhone]);
            
            if ($stmt->fetch()) {
                $error = "Invite already sent to this person";
            } else {
                // Create invite
                $inviteId = generateUUID();
                $inviteCode = 'INV' . strtoupper(substr(md5($user['user_id'] . time() . $inviteeEmail . $inviteePhone), 0, 10));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $stmt = $pdo->prepare("
                    INSERT INTO user_invites 
                    (invite_id, inviter_id, invite_code, invitee_email, invitee_phone, expires_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$inviteId, $user['user_id'], $inviteCode, $inviteeEmail, $inviteePhone, $expiresAt]);
                
                $message = "Invite created successfully!";
            }
        } catch (Exception $e) {
            $error = "Error creating invite: " . $e->getMessage();
        }
    }
}

// Get user's invites
try {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT ui.*, u.name as inviter_name
        FROM user_invites ui
        LEFT JOIN users u ON ui.inviter_id = u.user_id
        WHERE ui.inviter_id = ?
        ORDER BY ui.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['user_id']]);
    $invites = $stmt->fetchAll();
} catch (Exception $e) {
    $invites = [];
    $error = "Error loading invites: " . $e->getMessage();
}

// Get user's invite stats
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_invites,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_invites,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_invites
        FROM user_invites
        WHERE inviter_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $inviteStats = $stmt->fetch();
} catch (Exception $e) {
    $inviteStats = ['total_invites' => 0, 'pending_invites' => 0, 'accepted_invites' => 0];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invites - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .stats-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--accent-color);
            margin: 10px 0;
        }
        .stats-label {
            color: var(--text-secondary);
            font-size: 0.9em;
        }
        .invite-form {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .invite-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .status-pending { color: #f59e0b; }
        .status-accepted { color: #10b981; }
        .status-expired { color: #ef4444; }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>

    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>

        <main class="main-content">
            <div class="page-shell">
                <div class="content-narrow">
                    <div class="page-head">
                        <div class="page-head-copy">
                            <h1>Invite Friends</h1>
                            <p>Share AkkuApps with your friends and keep invite tools accessible directly from the dashboard layout.</p>
                        </div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <div class="info-grid" style="margin-bottom: 1.5rem;">
                        <div class="stats-card">
                            <div class="stats-number"><?= $inviteStats['total_invites'] ?></div>
                            <div class="stats-label">Total Invites</div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-number"><?= $inviteStats['pending_invites'] ?></div>
                            <div class="stats-label">Pending</div>
                        </div>
                        <div class="stats-card">
                            <div class="stats-number"><?= $inviteStats['accepted_invites'] ?></div>
                            <div class="stats-label">Accepted</div>
                        </div>
                    </div>

                    <div class="invite-form">
                        <h3>Create New Invite</h3>
                        <form method="POST">
                            <input type="hidden" name="create_invite" value="1">

                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Friend's Email (Optional)</label>
                                <input type="email" name="invitee_email" placeholder="friend@example.com"
                                       style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                            </div>

                            <div style="margin-bottom: 20px;">
                                <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Friend's Phone (Optional)</label>
                                <input type="text" name="invitee_phone" placeholder="+91 98765 43210"
                                       style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                            </div>

                            <button type="submit" 
                                    style="background: var(--accent-color); color: white; padding: 12px 24px; border: none; border-radius: 6px; cursor: pointer; width: 100%;">
                                <i class="fas fa-paper-plane"></i> Send Invite
                            </button>
                        </form>

                        <div style="margin-top: 20px; padding: 15px; background: var(--secondary-bg); border-radius: 8px;">
                            <h4 style="margin: 0 0 10px 0; color: var(--text-primary);">Your Invite Code</h4>
                            <p style="color: var(--text-secondary); margin: 5px 0;">
                                <strong><?= htmlspecialchars($user['invite_code'] ?? 'Generate your first invite above') ?></strong>
                            </p>
                            <p style="color: var(--text-secondary); margin: 5px 0; font-size: 0.9em;">
                                Share this link: <a href="<?= SITE_URL ?>/auth/register.php?invite=<?= htmlspecialchars($user['invite_code'] ?? '') ?>" 
                                                   style="color: var(--accent-color);" target="_blank">
                                    <?= SITE_URL ?>/auth/register.php?invite=<?= htmlspecialchars($user['invite_code'] ?? '') ?>
                                </a>
                            </p>
                        </div>
                    </div>

                    <h3 style="margin-bottom: 1rem;">Sent Invites</h3>
                    <?php if (empty($invites)): ?>
                        <div class="empty-state">
                            <p>No invites sent yet.</p>
                            <p>Send your first invite above!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($invites as $invite): ?>
                            <div class="invite-card">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; gap: 1rem; flex-wrap: wrap;">
                                    <div>
                                        <strong style="color: var(--text-primary);">
                                            <?= htmlspecialchars($invite['invitee_email'] ?: $invite['invitee_phone'] ?: 'No contact info') ?>
                                        </strong>
                                        <div style="color: var(--text-secondary); font-size: 0.9em;">
                                            Invite code: <strong><?= htmlspecialchars($invite['invite_code']) ?></strong>
                                        </div>
                                    </div>
                                    <div>
                                        <?php if ($invite['status'] === 'pending'): ?>
                                            <span class="status-pending">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php elseif ($invite['status'] === 'accepted'): ?>
                                            <span class="status-accepted">
                                                <i class="fas fa-check"></i> Accepted
                                            </span>
                                        <?php else: ?>
                                            <span class="status-expired">
                                                <i class="fas fa-times"></i> Expired
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.9em; color: var(--text-secondary); gap: 1rem; flex-wrap: wrap;">
                                    <div>
                                        Sent: <?= date('M j, Y', strtotime($invite['created_at'])) ?>
                                    </div>
                                    <div>
                                        <?php if ($invite['expires_at']): ?>
                                            Expires: <?= date('M j, Y', strtotime($invite['expires_at'])) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid var(--border-color);">
                                    <input type="text" readonly value="<?= SITE_URL ?>/auth/register.php?invite=<?= htmlspecialchars($invite['invite_code']) ?>"
                                           style="width: 100%; padding: 8px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 4px; color: var(--text-primary); font-size: 0.9em;">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
</body>
</html>
