<?php
// user/messages.php - Fixed version
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
$conversationId = $_GET['conversation_id'] ?? null;

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipientId = $_POST['recipient_id'] ?? null;
    $content = trim($_POST['content'] ?? '');
    
    if (!$recipientId) {
        $error = "Recipient not specified";
    } elseif (empty($content)) {
        $error = "Message cannot be empty";
    } else {
        // Check if users are connected (friends/following)
        try {
            global $pdo;
            
            // Check if recipient has accepted the sender (friendship system)
            $stmt = $pdo->prepare("
                SELECT id FROM user_follows 
                WHERE ((follower_id = ? AND following_id = ?) OR (follower_id = ? AND following_id = ?))
                AND status = 'accepted'
            ");
            $stmt->execute([$user['user_id'], $recipientId, $recipientId, $user['user_id']]);
            $connection = $stmt->fetch();
            
            if (!$connection) {
                $error = "You can only message users who have accepted your connection request.";
            } else {
                // Proceed with message sending
                // Check if conversation already exists
                $stmt = $pdo->prepare("
                    SELECT conversation_id FROM conversations 
                    WHERE (user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?)
                ");
                $stmt->execute([$user['user_id'], $recipientId, $recipientId, $user['user_id']]);
                $conversation = $stmt->fetch();
                
                if ($conversation) {
                    $conversationId = $conversation['conversation_id'];
                } else {
                    // Create new conversation
                    $conversationId = generateUUID();
                    $stmt = $pdo->prepare("
                        INSERT INTO conversations 
                        (conversation_id, user1_id, user2_id, created_at) 
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$conversationId, $user['user_id'], $recipientId]);
                }
                
                // Send message
                $messageId = generateUUID();
                $stmt = $pdo->prepare("
                    INSERT INTO messages 
                    (message_id, conversation_id, sender_id, content, created_at) 
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$messageId, $conversationId, $user['user_id'], $content]);
                
                // Update conversation last message
                $pdo->prepare("
                    UPDATE conversations 
                    SET last_message_id = ?, updated_at = NOW() 
                    WHERE conversation_id = ?
                ")->execute([$messageId, $conversationId]);
                
                $message = "Message sent successfully!";
                
                // Redirect to conversation
                header("Location: /user/messages.php?conversation_id=" . $conversationId);
                exit;
            }
        } catch (Exception $e) {
            $error = "Error sending message: " . $e->getMessage();
        }
    }
}

// Get conversations
try {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT c.*, 
               CASE 
                   WHEN c.user1_id = ? THEN u2.name 
                   ELSE u1.name 
               END as other_user_name,
               CASE 
                   WHEN c.user1_id = ? THEN u2.avatar 
                   ELSE u1.avatar 
               END as other_user_avatar,
               m.content as last_message,
               m.created_at as last_message_time
        FROM conversations c
        JOIN users u1 ON c.user1_id = u1.user_id
        JOIN users u2 ON c.user2_id = u2.user_id
        LEFT JOIN messages m ON c.last_message_id = m.message_id
        WHERE (c.user1_id = ? OR c.user2_id = ?)
        AND EXISTS (
            SELECT 1 FROM user_follows uf 
            WHERE ((uf.follower_id = ? AND uf.following_id = 
                   CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END)
                   OR (uf.follower_id = 
                       CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END 
                       AND uf.following_id = ?))
            AND uf.status = 'accepted'
        )
        ORDER BY c.updated_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user['user_id'], $user['user_id'], $user['user_id'], $user['user_id'], 
                    $user['user_id'], $user['user_id'], $user['user_id'], $user['user_id']]);
    $conversations = $stmt->fetchAll();
} catch (Exception $e) {
    $conversations = [];
    $error = "Error loading conversations: " . $e->getMessage();
}

// Get messages for selected conversation
$messages = [];
$otherUser = null;
if ($conversationId) {
    try {
        // Get conversation details
        $stmt = $pdo->prepare("
            SELECT c.*, 
                   CASE 
                       WHEN c.user1_id = ? THEN u2.user_id 
                       ELSE u1.user_id 
                   END as other_user_id,
                   CASE 
                       WHEN c.user1_id = ? THEN u2.name 
                       ELSE u1.name 
                   END as other_user_name,
                   CASE 
                       WHEN c.user1_id = ? THEN u2.avatar 
                       ELSE u1.avatar 
                   END as other_user_avatar
            FROM conversations c
            JOIN users u1 ON c.user1_id = u1.user_id
            JOIN users u2 ON c.user2_id = u2.user_id
            WHERE c.conversation_id = ? AND (c.user1_id = ? OR c.user2_id = ?)
            AND EXISTS (
                SELECT 1 FROM user_follows uf 
                WHERE ((uf.follower_id = ? AND uf.following_id = 
                       CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END)
                       OR (uf.follower_id = 
                           CASE WHEN c.user1_id = ? THEN c.user2_id ELSE c.user1_id END 
                           AND uf.following_id = ?))
                AND uf.status = 'accepted'
            )
        ");
        $stmt->execute([$user['user_id'], $user['user_id'], $user['user_id'], $conversationId, 
                        $user['user_id'], $user['user_id'], $user['user_id'], $user['user_id'], 
                        $user['user_id'], $user['user_id']]);
        $conversation = $stmt->fetch();
        
        if ($conversation) {
            $otherUser = [
                'user_id' => $conversation['other_user_id'],
                'name' => $conversation['other_user_name'],
                'avatar' => $conversation['other_user_avatar']
            ];
            
            // Get messages
            $stmt = $pdo->prepare("
                SELECT m.*, u.name as sender_name, u.avatar as sender_avatar
                FROM messages m
                JOIN users u ON m.sender_id = u.user_id
                WHERE m.conversation_id = ?
                ORDER BY m.created_at ASC
                LIMIT 50
            ");
            $stmt->execute([$conversationId]);
            $messages = $stmt->fetchAll();
            
            // Mark messages as read
            $pdo->prepare("
                UPDATE messages 
                SET is_read = 1 
                WHERE conversation_id = ? AND sender_id != ?
            ")->execute([$conversationId, $user['user_id']]);
        } else {
            $error = "Conversation not found or you don't have permission to view it.";
        }
    } catch (Exception $e) {
        $error = "Error loading messages: " . $e->getMessage();
    }
}

// Get users who have accepted connection requests
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.user_id, u.name, u.avatar
        FROM users u
        JOIN user_follows uf ON (
            (uf.follower_id = ? AND uf.following_id = u.user_id AND uf.status = 'accepted')
            OR (uf.follower_id = u.user_id AND uf.following_id = ? AND uf.status = 'accepted')
        )
        WHERE u.user_id != ?
        ORDER BY u.name ASC
        LIMIT 100
    ");
    $stmt->execute([$user['user_id'], $user['user_id'], $user['user_id']]);
    $users = $stmt->fetchAll();
} catch (Exception $e) {
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - AkkuApps</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css?v=<?= time() ?>">
    <style>
        .messages-container {
            display: flex;
            height: calc(100vh - 150px);
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            overflow: hidden;
        }
        .conversations-list {
            width: 300px;
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
        }
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s;
        }
        .conversation-item:hover {
            background: var(--secondary-bg);
        }
        .conversation-item.active {
            background: var(--accent-color);
            color: white;
        }
        .messages-area {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .messages-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
        }
        .messages-content {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 18px;
            margin-bottom: 15px;
            position: relative;
        }
        .message-sent {
            background: var(--accent-color);
            color: white;
            margin-left: auto;
        }
        .message-received {
            background: var(--secondary-bg);
            color: var(--text-primary);
            margin-right: auto;
        }
        .message-input {
            padding: 15px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 10px;
        }
        .message-input textarea {
            flex: 1;
            padding: 12px;
            background: var(--secondary-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            resize: none;
        }
        .btn {
            padding: 12px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-primary { background: var(--accent-color); color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include '../components/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="welcome-banner">
                <h1>Messages</h1>
                <p>Private conversations with connected users only</p>
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

            <div class="messages-container">
                <!-- Conversations List -->
                <div class="conversations-list">
                    <div style="padding: 15px; border-bottom: 1px solid var(--border-color); background: var(--secondary-bg);">
                        <h3 style="margin: 0; color: var(--text-primary);">Conversations</h3>
                        <?php if (empty($users)): ?>
                            <div style="color: var(--text-secondary); font-size: 0.9em; margin-top: 5px;">
                                Connect with users to message them
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (empty($conversations)): ?>
                        <div style="padding: 30px; text-align: center; color: var(--text-secondary);">
                            <p>No conversations yet.</p>
                            <?php if (!empty($users)): ?>
                                <p style="font-size: 0.9em;">Start a new conversation below.</p>
                            <?php else: ?>
                                <p style="font-size: 0.9em;">You need to connect with users first.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $conv): ?>
                            <div class="conversation-item <?= $conv['conversation_id'] === $conversationId ? 'active' : '' ?>" 
                                 onclick="window.location.href='/user/messages.php?conversation_id=<?= $conv['conversation_id'] ?>'">
                                <div style="display: flex; align-items: center;">
                                    <img src="<?= htmlspecialchars($conv['other_user_avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                         alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 10px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: bold;"><?= htmlspecialchars($conv['other_user_name']) ?></div>
                                        <?php if (!empty($conv['last_message'])): ?>
                                            <div style="font-size: 0.9em; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                                <?= htmlspecialchars(substr($conv['last_message'], 0, 30)) ?><?= strlen($conv['last_message']) > 30 ? '...' : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($conv['last_message_time'])): ?>
                                        <div style="font-size: 0.8em; color: var(--text-secondary);">
                                            <?= date('M j', strtotime($conv['last_message_time'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Messages Area -->
                <div class="messages-area">
                    <?php if ($conversationId && $otherUser): ?>
                        <div class="messages-header">
                            <img src="<?= htmlspecialchars($otherUser['avatar'] ?: '../assets/images/default-avatar.png') ?>" 
                                 alt="Avatar" style="width: 40px; height: 40px; border-radius: 50%; margin-right: 15px;">
                            <div>
                                <h3 style="margin: 0;"><?= htmlspecialchars($otherUser['name']) ?></h3>
                                <div style="font-size: 0.9em; color: var(--text-secondary);">Connected user</div>
                            </div>
                        </div>
                        
                        <div class="messages-content" id="messagesContent">
                            <?php if (empty($messages)): ?>
                                <div style="text-align: center; padding: 50px; color: var(--text-secondary);">
                                    <p>No messages yet.</p>
                                    <p>Start the conversation!</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($messages as $msg): ?>
                                    <div class="message-bubble <?= $msg['sender_id'] === $user['user_id'] ? 'message-sent' : 'message-received' ?>">
                                        <div><?= nl2br(htmlspecialchars($msg['content'])) ?></div>
                                        <div style="font-size: 0.7em; text-align: right; margin-top: 5px; opacity: 0.8;">
                                            <?= date('g:i A', strtotime($msg['created_at'])) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <form method="POST" class="message-input">
                            <input type="hidden" name="recipient_id" value="<?= $otherUser['user_id'] ?>">
                            <input type="hidden" name="send_message" value="1">
                            <textarea name="content" placeholder="Type your message..." rows="1" required></textarea>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-secondary);">
                            <div style="text-align: center;">
                                <div style="font-size: 4rem; margin-bottom: 20px;">
                                    <i class="fas fa-comments"></i>
                                </div>
                                <h3><?= empty($users) ? 'Connect with users to start messaging' : 'Select a conversation or start a new one' ?></h3>
                                <?php if (!empty($users)): ?>
                                    <button onclick="document.getElementById('newMessageModal').style.display='flex'" 
                                            class="btn btn-success" style="margin-top: 20px;">
                                        <i class="fas fa-plus"></i> New Message
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- New Message Modal -->
    <?php if (!empty($users)): ?>
    <div id="newMessageModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--card-bg); border-radius: 12px; padding: 30px; width: 90%; max-width: 500px; border: 1px solid var(--border-color);">
            <h2 style="margin-top: 0; color: var(--text-primary);">New Message</h2>
            <form method="POST">
                <input type="hidden" name="send_message" value="1">
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">To</label>
                    <select name="recipient_id" required 
                            style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);">
                        <option value="">Select a connected user</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['user_id'] ?>"><?= htmlspecialchars($u['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; color: var(--text-primary);">Message</label>
                    <textarea name="content" rows="4" placeholder="Type your message..." required
                              style="width: 100%; padding: 12px; background: var(--secondary-bg); border: 1px solid var(--border-color); border-radius: 6px; color: var(--text-primary);"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                    <button type="button" onclick="document.getElementById('newMessageModal').style.display='none'" 
                            class="btn btn-secondary" style="flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script src="../assets/js/theme-switcher.js?v=<?= time() ?>"></script>
    <script>
        // Auto-scroll to bottom of messages
        document.addEventListener('DOMContentLoaded', function() {
            const messagesContent = document.getElementById('messagesContent');
            if (messagesContent) {
                messagesContent.scrollTop = messagesContent.scrollHeight;
            }
        });
        
        // Auto-resize textarea
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
    </script>
</body>
</html>
