<?php
// components/sidebar.php — Mobile-first sliding sidebar
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
?>
<!-- Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<nav class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <h2>akku<span>apps</span></h2>
    </div>

    <ul class="sidebar-menu">
        <li class="menu-divider">Main</li>
        <li><a href="/user/dashboard.php" class="<?= $requestPath === '/user/dashboard.php' ? 'active' : '' ?>"><i class="fas fa-gauge-high"></i> Dashboard</a></li>
        <li><a href="/index.php" class="<?= $requestPath === '/' || $requestPath === '/index.php' ? 'active' : '' ?>"><i class="fas fa-house"></i> Home</a></li>
        <li><a href="/news/" class="<?= strpos($requestPath, '/news/') === 0 && strpos($requestUri, 'kind=blog') === false ? 'active' : '' ?>"><i class="fas fa-newspaper"></i> News</a></li>
        <li><a href="/news/?kind=blog" class="<?= strpos($requestUri, 'kind=blog') !== false ? 'active' : '' ?>"><i class="fas fa-feather-pointed"></i> Blogs</a></li>
        <li><a href="/marketplace/" class="<?= strpos($requestPath, '/marketplace/') === 0 ? 'active' : '' ?>"><i class="fas fa-cart-shopping"></i> Marketplace</a></li>
        <li><a href="/services/" class="<?= strpos($requestPath, '/services/') === 0 ? 'active' : '' ?>"><i class="fas fa-screwdriver-wrench"></i> Services</a></li>
        <li><a href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener"><i class="fas fa-robot"></i> Chatbot</a></li>

        <li class="menu-divider">Social</li>
        <li>
            <a href="/user/feed.php" class="<?= basename($_SERVER['PHP_SELF']) === 'feed.php' ? 'active' : '' ?>">
                <i class="fas fa-rss"></i> Feed
            </a>
        </li>
        <li>
            <a href="/user/create-post.php" class="<?= basename($_SERVER['PHP_SELF']) === 'create-post.php' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle"></i> Create Post
            </a>
        </li>
        <li>
            <a href="/user/profile.php" class="<?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user"></i> Profile
            </a>
        </li>

        <li class="menu-divider">Monetization</li>
        <li>
            <a href="/user/wallet.php" class="<?= basename($_SERVER['PHP_SELF']) === 'wallet.php' ? 'active' : '' ?>">
                <i class="fas fa-wallet"></i> Wallet
            </a>
        </li>
        <li>
            <a href="/user/subscription.php" class="<?= basename($_SERVER['PHP_SELF']) === 'subscription.php' ? 'active' : '' ?>">
                <i class="fas fa-crown"></i> Subscriptions
            </a>
        </li>
        <li>
            <a href="/user/badgeshop.php" class="<?= basename($_SERVER['PHP_SELF']) === 'badgeshop.php' ? 'active' : '' ?>">
                <i class="fas fa-award"></i> Badge Shop
            </a>
        </li>

        <li class="menu-divider">Community</li>
        <li>
            <a href="/user/messages.php" class="<?= basename($_SERVER['PHP_SELF']) === 'messages.php' ? 'active' : '' ?>">
                <i class="fas fa-comments"></i> Messages
            </a>
        </li>
        <li>
            <a href="/user/followers.php?type=followers" class="<?= basename($_SERVER['PHP_SELF']) === 'followers.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Followers
            </a>
        </li>
        <li>
            <a href="/user/groups.php" class="<?= basename($_SERVER['PHP_SELF']) === 'groups.php' ? 'active' : '' ?>">
                <i class="fas fa-layer-group"></i> Groups
            </a>
        </li>
        <li>
            <a href="/user/events.php" class="<?= basename($_SERVER['PHP_SELF']) === 'events.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> Events
            </a>
        </li>
        <li>
            <a href="/user/invites.php" class="<?= basename($_SERVER['PHP_SELF']) === 'invites.php' ? 'active' : '' ?>">
                <i class="fas fa-paper-plane"></i> Invites
            </a>
        </li>

        <li class="menu-divider">Account</li>
        <li>
            <a href="/user/settings.php" class="<?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
        </li>
        <li>
            <a href="/auth/logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</nav>

<script>
// Sidebar toggle logic — shared across all pages
(function() {
    const sidebar  = document.getElementById('appSidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const toggle   = document.getElementById('menuToggle');

    function openSidebar()  { sidebar.classList.add('open'); overlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); document.body.style.overflow = ''; }

    if (toggle)  toggle.addEventListener('click', () => sidebar.classList.contains('open') ? closeSidebar() : openSidebar());
    if (overlay) overlay.addEventListener('click', closeSidebar);

    // Close on escape key
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSidebar(); });

    // Touch swipe to close (swipe left)
    let touchStartX = 0;
    sidebar.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
    sidebar.addEventListener('touchend',   e => {
        if (touchStartX - e.changedTouches[0].clientX > 60) closeSidebar();
    });

    // On resize: if desktop, remove overflow lock
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 1024) { document.body.style.overflow = ''; overlay.classList.remove('active'); }
    });
})();
</script>
