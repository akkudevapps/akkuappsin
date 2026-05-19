<header class="app-header">
    <div class="header-left">
        <button class="menu-toggle" id="menuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="logo">
            <h2>akku<span>apps</span> <span style="font-size: 0.8em; color: var(--accent-color);">ADMIN</span></h2>
        </div>
    </div>

    <div class="header-center">
        <nav class="nav-links">
            <a href="/index.php" class="nav-link"><i class="fas fa-house"></i> Home</a>
            <a href="/" class="nav-link"><i class="fas fa-globe"></i> Public Site</a>
            <a href="/news/" class="nav-link"><i class="fas fa-newspaper"></i> News</a>
            <a href="/news/?kind=blog" class="nav-link"><i class="fas fa-feather-pointed"></i> Blogs</a>
            <a href="https://chatbot.akkuapps.in/" target="_blank" rel="noopener" class="nav-link"><i class="fas fa-robot"></i> Chatbot</a>
        </nav>
    </div>
    
    <div class="header-right">
        <div class="theme-switcher">
            <button id="themeToggle" class="theme-btn" data-theme-toggle="true" aria-label="Toggle theme">
                <i class="fas fa-moon"></i>
            </button>
        </div>
        
        <div style="color: var(--text-primary); margin-right: 15px;">
            <i class="fas fa-coins"></i> Admin Mode
        </div>
        
        <div class="user-menu">
            <div class="user-avatar">
                <img src="<?= $user['avatar'] ?: '../assets/images/default-avatar.png' ?>" alt="Avatar">
            </div>
            <div class="user-dropdown">
                <div class="dropdown-header">
                    <div class="user-info">
                        <strong><?= htmlspecialchars($user['name']) ?></strong>
                        <small>Administrator</small>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="/user/dashboard.php" class="dropdown-item">
                    <i class="fas fa-user"></i> User Dashboard
                </a>
                <a href="/admin/settings.php" class="dropdown-item">
                    <i class="fas fa-cog"></i> Admin Settings
                </a>
                <a href="/auth/logout.php" class="dropdown-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>
