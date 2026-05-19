<?php
$adminPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar-overlay" id="adminSidebarOverlay"></div>

<nav class="sidebar" id="adminSidebar">
    <div class="sidebar-header">
        <h2>akku<span>apps</span> Admin</h2>
    </div>
    <ul class="sidebar-menu">
        <li class="menu-divider">Overview</li>
        <li>
            <a href="/admin/dashboard.php" class="<?= $adminPage === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-gauge-high"></i> Dashboard
            </a>
        </li>
        <li><a href="/index.php"><i class="fas fa-house"></i> Home</a></li>
        <li><a href="/"><i class="fas fa-globe"></i> Public Site</a></li>

        <li class="menu-divider">Content</li>
        <li><a href="/news/"><i class="fas fa-newspaper"></i> News</a></li>
        <li><a href="/news/?kind=blog"><i class="fas fa-feather-pointed"></i> Blogs</a></li>
        <li><a href="/admin/news.php"><i class="fas fa-pen-to-square"></i> News Management</a></li>
        <li><a href="/admin/reviews.php"><i class="fas fa-star"></i> Reviews</a></li>

        <li class="menu-divider">Commerce</li>
        <li><a href="/admin/marketplace.php"><i class="fas fa-cart-shopping"></i> Marketplace</a></li>
        <li><a href="/admin/services.php"><i class="fas fa-screwdriver-wrench"></i> Services</a></li>
        <li><a href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener"><i class="fas fa-robot"></i> Chatbot</a></li>
        <li>
            <a href="/admin/users.php" class="<?= $adminPage === 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> User Management
            </a>
        </li>
        <li>
            <a href="/admin/content.php" class="<?= $adminPage === 'content.php' ? 'active' : '' ?>">
                <i class="fas fa-file-alt"></i> Content Moderation
            </a>
        </li>

        <li class="menu-divider">Treasury</li>
        <li>
            <a href="/admin/collectionbox.php" class="<?= $adminPage === 'collectionbox.php' ? 'active' : '' ?>">
                <i class="fas fa-piggy-bank"></i> Treasury
            </a>
        </li>
        <li>
            <a href="/admin/coinpackages.php" class="<?= $adminPage === 'coinpackages.php' ? 'active' : '' ?>">
                <i class="fas fa-coins"></i> Coin Packages
            </a>
        </li>
        <li>
            <a href="/admin/digitalgoods.php" class="<?= $adminPage === 'digitalgoods.php' ? 'active' : '' ?>">
                <i class="fas fa-gift"></i> Digital Goods
            </a>
        </li>
        <li>
            <a href="/admin/analytics.php" class="<?= $adminPage === 'analytics.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i> Analytics
            </a>
        </li>

        <li class="menu-divider">Access</li>
        <li>
            <a href="/user/dashboard.php">
                <i class="fas fa-user"></i> User Dashboard
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
(function() {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('adminSidebarOverlay');
    const toggle = document.getElementById('menuToggle');

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    if (toggle) {
        toggle.addEventListener('click', function() {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeSidebar();
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1024) {
            closeSidebar();
        }
    });
})();
</script>
