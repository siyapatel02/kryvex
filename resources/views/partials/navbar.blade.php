<header class="kv-header">
    <nav class="kv-navbar">
        <a href="{{ url('/') }}" class="kv-brand">
            <span class="kv-brand-mark">K</span>
            <span>Kryvex</span>
        </a>

        <div class="kv-nav-links" id="mainNavLinks">
            <a href="{{ url('/markets') }}" class="{{ request()->is('markets') ? 'active' : '' }}">Markets</a>
            <a href="{{ url('/trade') }}" class="{{ request()->is('trade*') || request()->is('spot*') ? 'active' : '' }}">Trade</a>
            <div class="kv-nav-dropdown-wrap" id="navFuturesDropdownWrap">
                <a href="{{ url('/futures') }}" class="{{ request()->is('futures*') ? 'active' : '' }} kv-nav-link-dropdown" id="navFuturesLink">
                    Futures
                    <svg class="kv-nav-caret" width="9" height="5" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 1L5 5L9 1"/>
                    </svg>
                </a>
                <div class="kv-nav-accounts-dropdown" id="navFuturesAccountsDropdown" role="menu" aria-label="Trading Accounts" data-lenis-prevent>
                    <div class="kv-accounts-drop-caret"></div>
                    <div class="kv-accounts-drop-filter">
                        <label class="kv-accounts-checkbox-label">
                            <input type="checkbox" id="navHideEmptyAccounts" class="kv-accounts-checkbox">
                            <span class="kv-accounts-checkbox-box">
                                <svg class="kv-accounts-check-icon" width="10" height="8" viewBox="0 0 10 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="1 4 3.5 6.5 9 1"></polyline>
                                </svg>
                            </span>
                            <span class="kv-accounts-checkbox-text">Hide empty accounts</span>
                        </label>
                    </div>
                    <div class="kv-accounts-drop-divider"></div>
                    <div class="kv-accounts-drop-section-title">Kryvex Accounts</div>
                    <div class="kv-accounts-drop-list" id="navFuturesAccountsList" data-lenis-prevent>
                        <div class="kv-accounts-drop-loading">Loading accounts...</div>
                    </div>
                    <div class="kv-accounts-drop-divider"></div>
                    <div class="kv-accounts-drop-footer">
                        <a href="{{ url('/wallet?tab=trading') }}" id="navManageAccountsBtn" class="kv-manage-accounts-btn">
                            <span>Manage accounts</span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
            <a href="{{ url('/options') }}" class="{{ request()->is('options*') ? 'active' : '' }}">Options</a>
            <a href="{{ url('/portfolio') }}" class="{{ request()->is('portfolio*') ? 'active' : '' }}">Portfolio</a>
            <a href="{{ url('/learn') }}" class="{{ request()->is('learn*') ? 'active' : '' }}">Learn</a>
            <a href="{{ url('/wallet?tab=overview') }}" id="navWalletLink" class="{{ request()->is('wallet*') ? 'active' : '' }}"
                style="display: none;">Wallet</a>
        </div>

        <div class="kv-nav-actions">
            <div id="guestNavActions" class="kv-nav-actions">
                <button type="button" class="kv-theme-toggle-btn" aria-label="Toggle theme" title="Toggle Light / Dark Theme" onclick="window.toggleKryvexTheme()">
                    <span class="theme-icon-sun" hidden>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                    </span>
                    <span class="theme-icon-moon" hidden>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </span>
                </button>
                <a href="{{ url('/login') }}" class="kv-btn kv-btn-outline">Login</a>
                <a href="{{ url('/register') }}" class="kv-btn kv-btn-primary" style="color: #ffffff !important;">Register</a>
            </div>

            <div id="userNavActions" class="kv-nav-actions" style="display: none; align-items: center; gap: 10px;">

                <!-- Deposit Button -->
                @php
                    $isNavDepositPage = request()->is('wallet*') && (request('tab') === 'deposit' || request('action') === 'deposit' || request('subtab') === 'deposit');
                @endphp
                <a href="{{ url('/wallet?tab=deposit') }}" id="navDepositBtn" class="kv-nav-deposit-btn" title="Deposit Funds" style="text-decoration: none; {{ $isNavDepositPage ? 'display: none !important;' : 'display: inline-flex;' }} align-items: center; justify-content: center;">
                    Deposit
                </a>

                <!-- Amount / Balance Tab Chip & Dropdown -->
                <div class="kv-nav-balance-wrap" id="navBalanceWrap" style="position: relative;">
                    <button type="button" class="kv-nav-balance-chip" id="navBalanceChip" aria-haspopup="true" aria-expanded="false" title="Account Balance">
                        <svg class="kv-nav-balance-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                            <line x1="2" y1="10" x2="22" y2="10"></line>
                        </svg>
                        <span id="navUserMainBalance" class="kv-nav-balance-text">0.00 USDT</span>
                        <svg class="kv-nav-balance-caret" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>

                    <!-- Menu Balance Dropdown (Matching Reference Image) -->
                    <div id="navBalanceDropdown" class="kv-nav-balance-dropdown">
                        <!-- Group 1: Accounts -->
                        <div class="kv-nav-drop-section">
                            <div class="kv-nav-drop-heading">Accounts</div>

                            <!-- Overview -->
                            <a href="{{ url('/wallet?tab=overview') }}" class="kv-nav-drop-item" id="navDropOverviewBtn">
                                <svg class="kv-nav-drop-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path>
                                    <path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                                </svg>
                                <span>Overview</span>
                            </a>

                            <!-- Trading accounts -->
                            <a href="{{ url('/wallet?tab=trading') }}" class="kv-nav-drop-item" id="navDropTradingAccountsBtn">
                                <svg class="kv-nav-drop-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                                    <line x1="9" y1="4" x2="9" y2="20"></line>
                                    <path d="M14 9h3M14 13h3"></path>
                                </svg>
                                <span>Trading accounts</span>
                            </a>

                            <!-- Wallet -->
                            <a href="{{ url('/wallet?tab=wallet') }}" class="kv-nav-drop-item" id="navDropWalletBtn">
                                <svg class="kv-nav-drop-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 7V5a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h15a2 2 0 0 0 2-2v-2"></path>
                                    <path d="M16 12h5a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1h-5a1 1 0 0 1-1-1v-2a1 1 0 0 1 1-1z"></path>
                                </svg>
                                <span>Wallet</span>
                            </a>

                            <!-- + Create new account -->
                            <button type="button" class="kv-nav-drop-create-btn" id="navDropCreateAccountBtn" onclick="const w=document.getElementById('navBalanceWrap'); if(w) w.classList.remove('is-open'); const d=document.getElementById('navBalanceDropdown'); if(d) d.hidden=true; if(window.openBrokerModal){window.openBrokerModal({isCreateAccount:true});}else if(typeof openBrokerModal==='function'){openBrokerModal({isCreateAccount:true});}">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="12" y2="12"></line>
                                </svg>
                                <span>Create new account</span>
                            </button>
                        </div>

                        <div class="kv-nav-drop-divider"></div>

                        <!-- Group 2: Transactions -->
                        <div class="kv-nav-drop-section">
                            <div class="kv-nav-drop-heading">Transactions</div>

                            <!-- Deposit -->
                            <a href="{{ url('/wallet?tab=deposit') }}" class="kv-nav-drop-item" id="navDropDepositBtn">
                                <svg class="kv-nav-drop-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="5" y1="19" x2="19" y2="19"></line>
                                </svg>
                                <span>Deposit</span>
                            </a>

                            <!-- Withdraw -->
                            <a href="{{ url('/wallet?tab=withdraw') }}" class="kv-nav-drop-item" id="navDropWithdrawBtn">
                                <svg class="kv-nav-drop-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="17" x2="12" y2="5"></line>
                                    <polyline points="7 10 12 5 17 10"></polyline>
                                    <line x1="5" y1="21" x2="19" y2="21"></line>
                                </svg>
                                <span>Withdraw</span>
                            </a>

                            <!-- Transfer -->
                            <a href="{{ url('/wallet?tab=transfer') }}" class="kv-nav-drop-item" id="navDropTransferBtn">
                                <svg class="kv-nav-drop-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="17 1 21 5 17 9"></polyline>
                                    <path d="M3 5h18"></path>
                                    <polyline points="7 23 3 19 7 15"></polyline>
                                    <path d="M21 19H3"></path>
                                </svg>
                                <span>Transfer</span>
                            </a>

                            <!-- Exchange -->
                            <a href="{{ url('/wallet?tab=exchange') }}" class="kv-nav-drop-item" id="navDropExchangeBtn">
                                <svg class="kv-nav-drop-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
                                </svg>
                                <span>Exchange</span>
                            </a>

                            <!-- Transfer history -->
                            <a href="{{ url('/wallet?tab=history') }}" class="kv-nav-drop-item" id="navDropHistoryBtn">
                                <svg class="kv-nav-drop-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <circle cx="12" cy="14" r="3"></circle>
                                    <polyline points="12 12 12 14 13.5 14"></polyline>
                                </svg>
                                <span>Transfer history</span>
                            </a>
                        </div>
                    </div>
                </div>
                <script>
                    (function() {
                        try {
                            var m = localStorage.getItem('kryvex_trading_mode') || 'live';
                            var u = localStorage.getItem('kryvex_active_account_uid');
                            var usr = JSON.parse(localStorage.getItem('kryvex_user') || '{}');
                            var accs = JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || localStorage.getItem('kryvex_trading_accounts_cache') || '[]');
                            var curAcc = accs.find(function(a) { return String(a.account_uid) === String(u); });
                            if (!curAcc && accs.length > 0) {
                                curAcc = (m === 'demo') ? accs.find(function(a) { return a.account_type === 'demo' || a.is_demo; }) : (accs.find(function(a) { return a.is_default && !a.is_demo; }) || accs[0]);
                            }

                            var balEl = document.getElementById('navUserMainBalance');
                            if (balEl && curAcc) {
                                var isCurDemo = Boolean(curAcc.account_type === 'demo' || curAcc.is_demo || String(curAcc.account_name || '').toLowerCase().indexOf('demo') === 0);
                                var curBroker = String(curAcc.broker_type || usr.broker_type || 'kryvex').toLowerCase();
                                var isCurMt5 = (curBroker === 'mt5');
                                var cur = (localStorage.getItem('kryvex_active_currency') || 'USDT').toUpperCase();
                                var isInr = (cur === 'INR') && !isCurDemo && !isCurMt5;
                                var fmt = function(v) { return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
                                var fmtInr = function(v) { return Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

                                if (isCurDemo) {
                                    var db = Number(curAcc.main_balance || curAcc.demo_main_balance || curAcc.demo_available_balance || curAcc.balance || 60000);
                                    balEl.textContent = fmt(db) + ' USDT';
                                } else if (isCurMt5 && (curAcc.is_cent || curAcc.plan_key === 'cent')) {
                                    var mb = Number(curAcc.main_balance || curAcc.real_main_balance || 0);
                                    balEl.textContent = Math.round(mb * 100).toLocaleString('en-US') + ' USC';
                                } else if (isCurMt5) {
                                    var mb = Number(curAcc.main_balance || curAcc.real_main_balance || 0);
                                    balEl.textContent = '$' + fmt(mb) + ' USD';
                                } else if (isInr) {
                                    var mbInr = Number(curAcc.main_balance_inr || curAcc.real_main_balance_inr || (Number(curAcc.main_balance || curAcc.real_main_balance || 0) * 85));
                                    balEl.textContent = fmtInr(mbInr) + ' INR';
                                } else {
                                    var mb = Number(curAcc.main_balance || curAcc.real_main_balance || 0);
                                    balEl.textContent = fmt(mb) + ' USDT';
                                }
                            }
                        } catch(e) {}
                    })();
                </script>

                <!-- Currency Toggle Switcher (INR / USD) - Hidden on Home and Markets pages -->
                @if (!request()->is('/') && !request()->is('markets*'))
                    <div class="kv-currency-toggle-wrap" id="navCurrencyToggleWrap"
                        style="display: inline-flex; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 2px; height: 36px; align-items: center;">
                        <button type="button" id="navCurrencyBtnInr" class="kv-currency-btn" data-currency-toggle="INR"
                            style="background: transparent; border: none; color: rgba(255,255,255,0.6); border-radius: 16px; padding: 4px 12px; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">₹
                            INR</button>
                        <button type="button" id="navCurrencyBtnUsd" class="kv-currency-btn is-active"
                            data-currency-toggle="USD"
                            style="background: #7c3aed; border: none; color: #ffffff; border-radius: 16px; padding: 4px 12px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(124,58,237,0.4);">$
                            USD</button>
                    </div>
                    <script>
                        (function() {
                            try {
                                var m = localStorage.getItem('kryvex_trading_mode') || 'live';
                                var u = localStorage.getItem('kryvex_active_account_uid');
                                var usr = JSON.parse(localStorage.getItem('kryvex_user') || '{}');
                                var accs = JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || localStorage.getItem('kryvex_trading_accounts_cache') || '[]');
                                var curAcc = accs.find(function(a) { return String(a.account_uid) === String(u); });

                                var broker = curAcc ? String(curAcc.broker_type || usr.broker_type || 'kryvex').toLowerCase() : String(usr.broker_type || 'kryvex').toLowerCase();
                                var isMt5 = (broker === 'mt5');
                                var isDemo = curAcc ? Boolean(curAcc.account_type === 'demo' || curAcc.is_demo || String(curAcc.account_name || '').toLowerCase().indexOf('demo') === 0) : (m === 'demo');

                                var el = document.getElementById('navCurrencyToggleWrap');
                                if (el) {
                                    if (isMt5 || isDemo) {
                                        el.classList.add('is-demo-hidden');
                                        el.style.setProperty('display', 'none', 'important');
                                    } else {
                                        el.classList.remove('is-demo-hidden');
                                        el.style.setProperty('display', 'inline-flex', 'important');
                                    }
                                }
                            } catch(e) {}
                        })();
                    </script>
                @endif


                <!-- Notification Bell Drawer Button -->
                <div class="kv-notification-wrap" style="position: relative;">
                    <button type="button" id="navNotificationButton" class="kv-notification-btn"
                        aria-label="Notifications"
                        style="width: 38px; height: 38px; padding: 0; display: flex; align-items: center; justify-content: center; border-radius: 12px; background: rgba(18, 20, 29, 0.85); border: 1px solid rgba(255, 255, 255, 0.12); color: #cbd5e1; cursor: pointer; transition: all 0.2s ease; position: relative;">
                        <svg class="kv-notification-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #cbd5e1;">
                            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path>
                            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path>
                        </svg>
                        <span id="navNotificationCount" class="kv-notification-count" hidden>0</span>
                    </button>
                </div>

                <!-- USER PROFILE BUTTON & DROPDOWN (MATCHING SCREENSHOT) -->
                <div class="kv-profile-dropdown-wrap" style="position: relative !important;">
                    <button type="button" id="navAvatarBtn" class="kv-nav-profile-btn" aria-label="User Profile">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span class="kv-profile-dot" id="navProfileKycDot" style="display: none;"></span>
                        <span id="navUserInitial" style="display: none;">P</span>
                    </button>

                    <!-- Profile Dropdown Card -->
                    <div id="navAvatarDropdown" class="kv-avatar-dropdown-card kv-profile-menu-card" hidden>
                        <!-- Header User Section -->
                        <a href="{{ url('/settings?tab=account-profile') }}" class="kv-profile-user-card" id="dropdownProfileHeaderCard">
                            <div class="kv-profile-user-info">
                                <div class="kv-profile-user-top">
                                    <span id="dropdownUserEmailText" class="kv-profile-user-email">patelsiya0745@gmail.com</span>
                                    <span id="dropdownKycBadge" class="kv-profile-badge kv-badge-unverified">Unverified</span>
                                </div>
                                <div class="kv-profile-user-bottom">
                                    <span class="kv-profile-user-id">ID: <span id="dropdownUserUidText">2620109</span></span>
                                    <button type="button" id="btnCopyDropdownUid" class="kv-profile-copy-btn" title="Copy Client ID">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="kv-profile-user-arrow">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </div>
                        </a>

                        <!-- Verify Account Banner Card -->
                        <div id="dropdownVerifyCard" class="kv-profile-verify-card">
                            <div class="kv-verify-card-header">
                                <div class="kv-verify-card-icon">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                        <line x1="12" y1="8" x2="12" y2="12"></line>
                                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                    </svg>
                                </div>
                                <div class="kv-verify-card-title">Verify account</div>
                            </div>
                            <div class="kv-verify-card-desc">Verify your account to enable deposits and access all services.</div>
                            <a href="{{ url('/kyc') }}" class="kv-verify-card-btn">Verify</a>
                        </div>

                        <!-- Menu Items -->
                        <div class="kv-profile-menu-list">
                            <!-- Group 1 -->
                            <a href="{{ url('/invite') }}" class="kv-pmenu-link">
                                <div class="kv-pmenu-left">
                                    <svg class="kv-pmenu-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="8.5" cy="7" r="4"></circle>
                                        <line x1="20" y1="8" x2="20" y2="14"></line>
                                        <line x1="23" y1="11" x2="17" y2="11"></line>
                                    </svg>
                                    <span class="kv-pmenu-label">Invite &amp; Earn</span>
                                    <span class="kv-pmenu-tag-dollar">$</span>
                                </div>
                            </a>

                            <a href="{{ url('/support') }}" class="kv-pmenu-link">
                                <div class="kv-pmenu-left">
                                    <svg class="kv-pmenu-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                                        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                                    </svg>
                                    <span class="kv-pmenu-label">Support 24/7</span>
                                </div>
                            </a>

                            <a href="{{ url('/support') }}" class="kv-pmenu-link">
                                <div class="kv-pmenu-left">
                                    <svg class="kv-pmenu-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="9" y1="18" x2="15" y2="18"></line>
                                        <line x1="10" y1="22" x2="14" y2="22"></line>
                                        <path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14"></path>
                                    </svg>
                                    <span class="kv-pmenu-label">Feedback</span>
                                </div>
                            </a>

                            <div class="kv-profile-divider"></div>

                            <!-- Group 2 -->
                            <a href="{{ url('/wallet?tab=history') }}" class="kv-pmenu-link">
                                <div class="kv-pmenu-left">
                                    <svg class="kv-pmenu-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    <span class="kv-pmenu-label">History</span>
                                </div>
                            </a>

                            <a href="{{ url('/settings') }}" class="kv-pmenu-link">
                                <div class="kv-pmenu-left">
                                    <svg class="kv-pmenu-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="3"></circle>
                                        <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                                    </svg>
                                    <span class="kv-pmenu-label">Settings</span>
                                </div>
                            </a>

                            <div class="kv-pmenu-row kv-pmenu-theme-row">
                                <div class="kv-pmenu-left">
                                    <svg class="kv-pmenu-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="5"></circle>
                                        <line x1="12" y1="1" x2="12" y2="3"></line>
                                        <line x1="12" y1="21" x2="12" y2="23"></line>
                                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                        <line x1="1" y1="12" x2="3" y2="12"></line>
                                        <line x1="21" y1="12" x2="23" y2="12"></line>
                                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                                    </svg>
                                    <span class="kv-pmenu-label">Theme</span>
                                </div>
                                <div class="kv-pmenu-theme-switch">
                                    <button type="button" class="kv-theme-pill-btn" data-theme-mode="light" title="Light Theme">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <circle cx="12" cy="12" r="5"></circle>
                                            <line x1="12" y1="1" x2="12" y2="3"></line>
                                            <line x1="12" y1="21" x2="12" y2="23"></line>
                                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                            <line x1="1" y1="12" x2="3" y2="12"></line>
                                            <line x1="21" y1="12" x2="23" y2="12"></line>
                                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                                        </svg>
                                    </button>
                                    <button type="button" class="kv-theme-pill-btn is-active" data-theme-mode="dark" title="Dark Theme">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="kv-profile-divider"></div>

                            <!-- Group 3 -->
                            <button type="button" id="dropdownLogoutBtn" class="kv-pmenu-link kv-pmenu-logout">
                                <div class="kv-pmenu-left">
                                    <svg class="kv-pmenu-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                    <span class="kv-pmenu-label">Log out</span>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <button class="kv-mobile-toggle" type="button" id="mobileMenuToggle">
                ☰
            </button>
        </div>
    </nav>
</header>

<!-- Notification Drawer Backdrop Overlay (Full Viewport) -->
<div id="navNotificationBackdrop" class="kv-notification-backdrop" hidden></div>

<!-- Notification Off-canvas Right Drawer (Docked to Viewport Right) -->
<div id="navNotificationDropdown" class="kv-notification-drawer" hidden>
    <div class="kv-notification-head">
        <div class="kv-notif-head-title">
            <strong>Notifications</strong>
        </div>
        <div class="kv-notif-head-actions">
            <button type="button" id="markNotificationsRead">Mark read</button>
            <button type="button" id="closeNotificationDrawer" class="kv-notif-close-btn"
                aria-label="Close Notifications">&times;</button>
        </div>
    </div>
    <div id="navNotificationList" class="kv-notification-list">
        <p class="kv-notification-empty">No notifications yet.</p>
    </div>
</div>

<!-- GLOBAL HOW WOULD YOU LIKE TO ADD FUNDS MODAL (OPENS FROM NAVBAR ON ANY PAGE) -->
<div class="kv-fund-modal-backdrop" id="globalAddFundsModal" hidden style="display: none;">
    <div class="kv-fund-modal-card" role="dialog" aria-modal="true" aria-labelledby="globalAddFundsTitle">
        <!-- Header -->
        <div class="kv-fund-modal-header">
            <h3 class="kv-fund-modal-title" id="globalAddFundsTitle">How would you like to add funds?</h3>
            <button type="button" class="kv-fund-modal-close" id="closeGlobalAddFundsModal" aria-label="Close modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <!-- Section 1: Deposit Options -->
        <div class="kv-fund-section">
            <span class="kv-fund-section-title">Deposit options</span>
            
            <!-- Option 1: Money deposit -->
            <div class="kv-fund-option-card" id="btnFundMoneyDeposit" role="button" tabindex="0">
                <div class="kv-fund-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="6" width="20" height="12" rx="2"></rect>
                        <circle cx="12" cy="12" r="2.5"></circle>
                        <path d="M6 12h.01M18 12h.01"></path>
                    </svg>
                </div>
                <div class="kv-fund-info">
                    <strong class="kv-fund-item-title">Money deposit</strong>
                    <span class="kv-fund-item-sub">Visa, Mastercard, bank transfers, e-wallets, online payments</span>
                </div>
                <svg class="kv-fund-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </div>

            <!-- Option 2: Crypto deposit -->
            <div class="kv-fund-option-card" id="btnFundCryptoDeposit" role="button" tabindex="0">
                <div class="kv-fund-icon-box">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M11.767 19.089c4.924.868 6.14-6.025 1.216-6.894m-1.216 6.894L5.86 18.047m5.907 1.042l-.347 1.97m.347-1.97l.868-4.925m-1.216-1.969c4.137.73 5.353-5.067 1.216-5.797m-1.216 5.797L6.727 13.12m5.04-1.042l.868-4.925m-.868 4.925l-5.04-1.042m5.04-1.042L11.42 4.07m.347 1.97l-4.172-.736m4.172.736l-.868 4.925"></path>
                    </svg>
                </div>
                <div class="kv-fund-info">
                    <strong class="kv-fund-item-title">Crypto deposit</strong>
                    <span class="kv-fund-item-sub">Bitcoin, USDT, Ethereum and more</span>
                </div>
                <svg class="kv-fund-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </div>
        </div>

        <!-- Section 2: Want to buy crypto? -->
        <div class="kv-fund-section">
            <span class="kv-fund-section-title">Want to buy crypto?</span>

            <!-- Option 3: Buy crypto -->
            <div class="kv-fund-option-card" id="btnFundBuyCrypto" role="button" tabindex="0">
                <div class="kv-fund-icon-box">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 3h5v5"></path>
                        <path d="M4 20L21 3"></path>
                        <path d="M21 16v5h-5"></path>
                        <path d="M15 15l6 6"></path>
                        <path d="M4 4l5 5"></path>
                    </svg>
                </div>
                <div class="kv-fund-info">
                    <strong class="kv-fund-item-title">Buy crypto</strong>
                </div>
                <svg class="kv-fund-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </div>
        </div>

        <!-- Security Footer Note -->
        <div class="kv-fund-modal-footer">
            <svg class="kv-fund-shield-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                <path d="m9 12 2 2 4-4"></path>
            </svg>
            <span>Your funds are stored securely, and your payment data is protected.</span>
        </div>
    </div>
</div>

