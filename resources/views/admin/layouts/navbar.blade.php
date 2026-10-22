<header class="admin-navbar">
    <div class="navbar-left">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">☰</button>
        
        <div class="admin-search-wrap">
            <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" id="adminGlobalSearch" placeholder="Search anything..." autocomplete="off">
            <kbd class="search-kbd">⌘K</kbd>
        </div>
    </div>

    <div class="navbar-actions">
        <button class="admin-theme-toggle" id="adminThemeToggle" type="button" aria-label="Toggle admin theme" title="Toggle light/dark theme">
            <span class="theme-icon-moon">🌙</span>
            <span class="theme-icon-sun" style="display: none;">☀️</span>
            <span class="theme-toggle-text">Dark</span>
            <span class="theme-toggle-arrow">▾</span>
        </button>

        <div class="system-pill">
            <span class="status-dot"></span>
            <span>System Online</span>
        </div>

        <div class="admin-profile">
            <div class="profile-avatar">A</div>
            <div class="profile-info">
                <strong>{{ session('admin_name', 'Admin User') }}</strong>
                <span>{{ session('admin_email', 'admin@kryvex.com') }}</span>
            </div>
        </div>
    </div>
</header>
