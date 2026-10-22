<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Kryvex Tab Favicon Icon -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('logo.png') }}">

    <script>
        (function() {
            var savedTheme = localStorage.getItem('kryvex_theme') || 'dark';
            if (savedTheme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
                document.documentElement.classList.add('theme-light');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                document.documentElement.classList.remove('theme-light');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/css/common/theme-global.css', 'resources/css/common/toast.css', 'resources/js/app.js', 'resources/js/common/theme.js', 'resources/js/common/toast.js'])
    @stack('styles')
</head>

<body>

    @include('partials.navbar')

    {{-- GLOBAL ACCOUNT DELETION NOTICE BANNER (SHOWN ACROSS ALL PAGES WHEN PENDING REVIEW) --}}
    <!-- GLOBAL ACCOUNT DELETION NOTICE BANNER (SHOWN ON FUTURES, WALLET & OTHER PAGES WHEN PENDING REVIEW) -->
    <div id="globalDeletionNoticeBox" style="display:none; width: min(1740px, calc(100% - 24px)); margin: 12px auto 0; box-sizing: border-box; position: relative; z-index: 2;">
        <div style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 14px; padding: 18px 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                <div style="display: flex; align-items: center; gap: 14px; flex: 1; min-width: 260px;">
                    <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.2); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">⚠️</div>
                    <div>
                        <strong style="color: #f59e0b; font-size: 15px; display: block; margin-bottom: 3px; font-weight: 800;">Account Deletion Request Under Review</strong>
                        <span id="globalDeletionNoticeText" style="color: #cbd5e1; font-size: 13px; line-height: 1.4; display: block;">Your request to delete this account is pending administrator approval. During this time, your account is in read-only mode and trading/wallet actions are disabled.</span>
                    </div>
                </div>
                <button type="button" id="btnCancelDeletionGlobal" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; font-weight: 700; font-size: 13px; padding: 8px 18px; border-radius: 8px; cursor: pointer; transition: all 0.2s ease;">Cancel Request</button>
            </div>
        </div>
    </div>

    {{-- DELETION RESTRICTION ACTION POPUP MODAL --}}
    <div id="modalDeletionActionRestricted" style="display: none; position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(4px); z-index: 999999; align-items: center; justify-content: center; padding: 16px;">
        <div style="background: #11141c; border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 18px; max-width: 480px; width: 100%; padding: 24px; box-shadow: 0 25px 60px rgba(0,0,0,0.7); color: #fff; text-align: left;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.18); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">⚠️</div>
                <div>
                    <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: #f59e0b;">Action Not Allowed</h3>
                    <span style="font-size: 0.78rem; color: #94a3b8;">Account Deletion Under Review</span>
                </div>
            </div>
            <p id="modalDeletionRestrictedMessage" style="font-size: 0.88rem; color: rgba(255, 255, 255, 0.85); line-height: 1.5; margin: 0 0 20px 0;">
                You have submitted an account deletion request which is currently pending review by administrator. Deposits, withdrawals, and trading actions are disabled while your request is pending.
            </p>
            <div style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; padding: 12px 14px; margin-bottom: 20px; font-size: 0.82rem; color: #cbd5e1;">
                💡 <strong>Tip:</strong> If you want to deposit funds, withdraw, or open new trades, please cancel your account deletion request first.
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" id="btnCloseDeletionRestrictedModal" style="padding: 10px 18px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.15); background: rgba(255, 255, 255, 0.08); color: #fff; font-size: 0.85rem; font-weight: 600; cursor: pointer;">Close</button>
                <button type="button" id="btnCancelDeletionFromActionModal" style="padding: 10px 18px; border-radius: 8px; border: none; background: #9333ea; color: #fff; font-size: 0.85rem; font-weight: 700; cursor: pointer;">Cancel Deletion Request</button>
            </div>
        </div>
    </div>

    <main>
        @yield('content')
    </main>

    @if (request()->is('/') || request()->is('markets*') || request()->is('learn*') || request()->is('wallet*') || request()->is('portfolio*') || request()->is('terms*') || request()->is('privacy*') || request()->is('calendar*') || request()->is('economic-calendar*') || request()->is('research*') || request()->is('news*'))
        @include('partials.footer')
    @endif
    @include('web.components.broker-modals')

    @stack('scripts')

    {{-- GLOBAL TOAST --}}
    @if (session('success'))
        <div class="app-toast success">
            <strong>Success</strong>
            <p>{{ session('success') }}</p>
        </div>
    @endif

    @if (session('error'))
        <div class="app-toast error">
            <strong>Error</strong>
            <p>{{ session('error') }}</p>
        </div>
    @endif

    {{-- GLOBAL CONFIRM MODAL --}}
    <div id="confirmModal" class="modal-overlay hidden">
        <div class="modal-box">
            <h3 id="modalTitle">Are you sure?</h3>
            <p id="modalMessage">This action cannot be undone.</p>

            <div class="modal-actions">
                <button id="cancelBtn" class="btn-cancel">Cancel</button>
                <button id="confirmBtn" class="btn-confirm">Confirm</button>
            </div>
        </div>
    </div>

    {{-- GOOGLE TRANSLATE AUTO ENGINE --}}
    <div id="google_translate_element" style="display:none;"></div>
    <script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

</body>

</html>
