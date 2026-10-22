<!DOCTYPE html>
<html lang="en" data-admin-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Kryvex Admin Tab Favicon Icon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('logo.png') }}">

    <script>
        // Apply saved admin theme before CSS renders to avoid flash.
        (function () {
            var theme = localStorage.getItem('kryvex_admin_theme') || 'dark';
            document.documentElement.setAttribute('data-admin-theme', theme === 'light' ? 'light' : 'dark');
        })();
    </script>

    <title>@yield('title', 'Kryvex Admin')</title>

    @vite([
        'resources/css/admin/layout.css',
        'resources/css/admin/dashboard.css',
        'resources/css/admin/users.css',
        'resources/css/admin/kyc.css',
        'resources/css/admin/user-detail.css',
        'resources/css/admin/wallet.css',
        'resources/css/admin/orders.css',
        'resources/css/admin/positions.css',
        'resources/css/admin/reports.css',
        'resources/css/admin/settings.css',
        'resources/css/admin/audit.css',
        'resources/css/common/toast.css',
        'resources/css/admin/theme-light-fix.css',
        'resources/js/admin/layout.js',
        'resources/js/admin/users.js',
        'resources/js/admin/kyc.js',
        'resources/js/admin/user-detail.js',
        'resources/js/admin/wallet.js',
        'resources/js/admin/positions.js',
        'resources/js/admin/settings.js',
        'resources/js/admin/audit.js',
        'resources/js/common/toast.js',
    ])

    @stack('styles')
</head>

<body>
    <div class="admin-shell">
        @include('admin.layouts.sidebar')

        <div class="admin-main">
            @include('admin.layouts.navbar')

            <main class="admin-content">
                @yield('content')
            </main>
        </div>
    </div>

    <div class="mobile-overlay" id="mobileOverlay"></div>

    @include('admin.components.flash')
    @include('admin.components.confirm-modal')

    @stack('scripts')
</body>

</html>
