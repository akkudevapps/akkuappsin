<?php
// components/header.php - Enhanced with navigation links
// Check if user is set (this should be passed from the calling page)
global $user;
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<header class="app-header">
    <div class="header-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="logo">
            <h2>akku<span>apps</span></h2>
        </div>
    </div>
    
    <div class="header-center">
        <nav class="nav-links">
            <a href="/index.php" class="nav-link <?= $requestPath === '/' || $requestPath === '/index.php' ? 'active' : '' ?>">
                <i class="fas fa-house"></i> Home
            </a>
            <a href="/news/" class="nav-link <?= strpos($requestPath, '/news/') === 0 && strpos($requestUri, 'kind=blog') === false ? 'active' : '' ?>">
                <i class="fas fa-newspaper"></i> News
            </a>
            <a href="/news/?kind=blog" class="nav-link <?= strpos($requestUri, 'kind=blog') !== false ? 'active' : '' ?>">
                <i class="fas fa-feather-pointed"></i> Blogs
            </a>
            <a href="/marketplace/" class="nav-link <?= strpos($requestPath, '/marketplace/') === 0 ? 'active' : '' ?>">
                <i class="fas fa-store"></i> Marketplace
            </a>
            <a href="/services/" class="nav-link <?= strpos($requestPath, '/services/') === 0 ? 'active' : '' ?>">
                <i class="fas fa-screwdriver-wrench"></i> Services
            </a>
            <a href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener" class="nav-link">
                <i class="fas fa-robot"></i> Chatbot
            </a>
        </nav>
    </div>
    
    <div class="header-right">
        <!-- PWA Support -->
        <link rel="manifest" href="/manifest.json">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="AkkuApps">
        <meta name="theme-color" content="#6366f1">
        <link rel="apple-touch-icon" href="/assets/images/icon-192.png">
        
        <div class="theme-switcher">
            <button id="themeToggle" class="theme-btn" data-theme-toggle="true" aria-label="Toggle theme">
                <i class="fas fa-moon"></i>
            </button>
        </div>
        
        <?php if (isset($user) && $user): ?>
            <?php
            // Include notifications if not already included
            if (!function_exists('getUnreadNotificationsCount')) {
                require_once '../includes/notifications.php';
            }
            $unreadCount = getUnreadNotificationsCount($user['user_id']);
            ?>
            
            <div class="notification-bell" style="position: relative; margin-right: 15px;">
                <button id="notificationBell" style="background: none; border: none; color: var(--text-primary); font-size: 1.2rem; cursor: pointer; position: relative;">
                    <i class="fas fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <span style="position: absolute; top: -5px; right: -5px; background: var(--accent-color); color: white; font-size: 0.7rem; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <?= min(9, $unreadCount) ?><?= $unreadCount > 9 ? '+' : '' ?>
                        </span>
                    <?php endif; ?>
                </button>
                
                <div id="notificationDropdown" style="position: absolute; top: 100%; right: 0; width: 300px; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 12px; box-shadow: var(--shadow); padding: 15px; display: none; z-index: 1000;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid var(--border-color);">
                        <h4 style="margin: 0; color: var(--text-primary);">Notifications</h4>
                        <?php if ($unreadCount > 0): ?>
                            <button onclick="markAllRead()" style="background: var(--accent-color); color: white; border: none; padding: 5px 10px; border-radius: 20px; font-size: 0.8em; cursor: pointer;">
                                Mark all as read
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div id="notificationsList" style="max-height: 300px; overflow-y: auto;">
                        <?php
                        $notifications = getUserNotifications($user['user_id'], 10);
                        if (empty($notifications)):
                        ?>
                            <p style="color: var(--text-secondary); text-align: center; margin: 20px 0;">No notifications</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div style="padding: 10px; border-radius: 8px; margin-bottom: 8px; <?= $notification['is_read'] ? '' : 'background: var(--secondary-bg);' ?>">
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <div>
                                            <?php
                                            $icon = '';
                                            switch ($notification['type']) {
                                                case 'like': $icon = 'fas fa-heart'; break;
                                                case 'comment': $icon = 'fas fa-comment'; break;
                                                case 'follow': $icon = 'fas fa-user-plus'; break;
                                                case 'gift': $icon = 'fas fa-gift'; break;
                                                case 'subscription': $icon = 'fas fa-crown'; break;
                                                default: $icon = 'fas fa-bell';
                                            }
                                            ?>
                                            <i class="<?= $icon ?>" style="color: var(--accent-color);"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: <?= $notification['is_read'] ? 'normal' : 'bold' ?>; color: var(--text-primary);">
                                                <?= htmlspecialchars($notification['title']) ?>
                                            </div>
                                            <?php if ($notification['message']): ?>
                                                <div style="font-size: 0.9em; color: var(--text-secondary); margin: 3px 0;">
                                                    <?= htmlspecialchars($notification['message']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="font-size: 0.8em; color: var(--text-secondary);">
                                                <?= date('M j, g:i A', strtotime($notification['created_at'])) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="user-menu">
                <div class="user-avatar">
                    <img src="<?= $user['avatar'] ?: '/assets/images/default-avatar.png' ?>" alt="Avatar">
                </div>
                <div class="user-dropdown">
                    <div class="dropdown-header">
                        <div class="user-info">
                            <strong><?= htmlspecialchars($user['name']) ?></strong>
                            <small><?= htmlspecialchars($user['email']) ?></small>
                        </div>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="/user/profile.php" class="dropdown-item">
                        <i class="fas fa-user"></i> Profile
                    </a>
                    <a href="/user/settings.php" class="dropdown-item">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                    <a href="/auth/logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>

<script>
// Notification dropdown toggle
if (document.getElementById('notificationBell')) {
    document.getElementById('notificationBell').addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdown = document.getElementById('notificationDropdown');
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('notificationDropdown');
        const bell = document.getElementById('notificationBell');
        if (dropdown && !dropdown.contains(e.target) && e.target !== bell) {
            dropdown.style.display = 'none';
        }
    });
}

// Mark all as read function
function markAllRead() {
    fetch('/api/mark-all-notifications-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const badge = document.querySelector('.notification-bell span');
            if (badge) badge.style.display = 'none';
            document.querySelectorAll('#notificationsList > div').forEach(el => {
                el.style.background = 'transparent';
                const title = el.querySelector('div[style*="font-weight"]');
                if (title) title.style.fontWeight = 'normal';
            });
        }
    })
    .catch(error => console.error('Error:', error));
}
</script>
