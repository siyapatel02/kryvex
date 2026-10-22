<aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">K</div>
        <div>
            <strong>Kryvex</strong>
            <span>Admin Console</span>
        </div>
    </div>

    {{-- Admin sidebar icons use inline Lucide-style SVGs.
         Reference: https://lucide.dev/icons/
         Icons are inline to avoid external CDN dependency and keep all icons same color/style. --}}
    <nav class="sidebar-nav">
        <a class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
            href="{{ route('admin.dashboard') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <rect x="3" y="3" width="7" height="9" rx="1.5"></rect>
                <rect x="14" y="3" width="7" height="5" rx="1.5"></rect>
                <rect x="14" y="12" width="7" height="9" rx="1.5"></rect>
                <rect x="3" y="16" width="7" height="5" rx="1.5"></rect>
            </svg>
            <span class="nav-label">Dashboard</span>
        </a>

        <div class="nav-section">Management</div>

        <a class="nav-item has-submenu {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
            href="{{ route('admin.users.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                <circle cx="9" cy="7" r="4"></circle>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
            </svg>
            <span class="nav-label">Users</span>
            <svg class="nav-arrow {{ request()->routeIs('admin.users.*') ? 'open' : '' }}" viewBox="0 0 24 24" aria-hidden="true">
                <path d="m6 9 6 6 6-6"></path>
            </svg>
        </a>

        @if (request()->routeIs('admin.users.*'))
            <div class="nav-submenu">
                <a class="{{ request()->routeIs('admin.users.index') ? 'active' : '' }}"
                    href="{{ route('admin.users.index') }}">
                    All Users
                </a>

                <a class="{{ request()->routeIs('admin.users.pending-kyc') ? 'active' : '' }}"
                    href="{{ route('admin.users.pending-kyc') }}">
                    Pending KYC
                </a>

                <a class="{{ request()->routeIs('admin.users.pending-deposits') ? 'active' : '' }}"
                    href="{{ route('admin.users.pending-deposits') }}">
                    Pending Deposits
                </a>

                <a class="{{ request()->routeIs('admin.users.deletion-requests') ? 'active' : '' }}"
                    href="{{ route('admin.users.deletion-requests') }}">
                    Deletion Requests
                </a>
            </div>
        @endif

        <a class="nav-item {{ request()->routeIs('admin.kyc.*') ? 'active' : '' }}" href="{{ route('admin.kyc.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.68 0C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.5 3.8 17 5 19 5a1 1 0 0 1 1 1z"></path>
                <path d="m9 12 2 2 4-4"></path>
            </svg>
            <span class="nav-label">KYC</span>
        </a>

        <a class="nav-item {{ request()->routeIs('admin.currency_requests.*') ? 'active' : '' }}" href="{{ route('admin.currency_requests.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M17 5H7a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z"></path>
                <path d="M12 8v8"></path>
                <path d="m9 11 3-3 3 3"></path>
            </svg>
            <span class="nav-label">Currency Requests</span>
        </a>

        <a class="nav-item has-submenu {{ request()->routeIs('admin.wallet.*') ? 'active' : '' }}"
            href="{{ route('admin.wallet.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M19 7V6a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h14a2 2 0 0 1 2 2v3"></path>
                <path d="M3 6v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3"></path>
                <path d="M18 12h.01"></path>
                <path d="M16 10h5v6h-5a3 3 0 0 1 0-6Z"></path>
            </svg>
            <span class="nav-label">Wallet</span>
            <svg class="nav-arrow {{ request()->routeIs('admin.wallet.*') ? 'open' : '' }}" viewBox="0 0 24 24" aria-hidden="true">
                <path d="m6 9 6 6 6-6"></path>
            </svg>
        </a>

        @if (request()->routeIs('admin.wallet.*'))
            <div class="nav-submenu">
                <a class="{{ request()->routeIs('admin.wallet.index') ? 'active' : '' }}"
                    href="{{ route('admin.wallet.index') }}">
                    Overview
                </a>

                <a class="{{ request()->routeIs('admin.wallet.deposits') ? 'active' : '' }}"
                    href="{{ route('admin.wallet.deposits') }}">
                    Deposits
                </a>

                <a class="{{ request()->routeIs('admin.wallet.withdrawals') ? 'active' : '' }}"
                    href="{{ route('admin.wallet.withdrawals') }}">
                    Withdrawals
                </a>

                <a class="{{ request()->routeIs('admin.wallet.internal_transfers') ? 'active' : '' }}"
                    href="{{ route('admin.wallet.internal_transfers') }}">
                    Internal Transfers
                </a>

                <a class="{{ request()->routeIs('admin.wallet.ledger') ? 'active' : '' }}"
                    href="{{ route('admin.wallet.ledger') }}">
                    Ledger
                </a>
            </div>
        @endif

        <a class="nav-item {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}"
            href="{{ route('admin.orders.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                <path d="M10 9H8"></path>
                <path d="M16 13H8"></path>
                <path d="M16 17H8"></path>
            </svg>
            <span class="nav-label">Orders</span>
        </a>

        <a class="nav-item {{ request()->routeIs('admin.positions.*') ? 'active' : '' }}"
            href="{{ route('admin.positions.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 17v5"></path>
                <path d="M9 10.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24V16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V7a3 3 0 0 0-6 0z"></path>
                <path d="M9 7H5"></path>
                <path d="M19 7h-4"></path>
            </svg>
            <span class="nav-label">Positions</span>
        </a>

        <a class="nav-item {{ request()->routeIs('admin.support.*') ? 'active' : '' }}"
            href="{{ route('admin.support.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
            <span class="nav-label">Support</span>
        </a>

        <div class="nav-section">Business</div>

        <a class="nav-item {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
            href="{{ route('admin.reports.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 3v18h18"></path>
                <path d="M18 17V9"></path>
                <path d="M13 17V5"></path>
                <path d="M8 17v-3"></path>
            </svg>
            <span class="nav-label">Reports</span>
        </a>

        <a class="nav-item {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
            href="{{ route('admin.settings.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.52a2 2 0 0 1-1 1.72l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.09a2 2 0 0 1-1-1.72v-.52a2 2 0 0 1 1-1.72l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            <span class="nav-label">Settings</span>
        </a>

        <a class="nav-item {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}"
            href="{{ route('admin.notifications.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            <span class="nav-label">Notifications</span>
        </a>

        <a class="nav-item {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}"
            href="{{ route('admin.audit.index') }}">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 11h6"></path>
                <path d="M9 15h6"></path>
                <path d="M9 7h6"></path>
                <path d="M5 3h14a2 2 0 0 1 2 2v13a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V5a2 2 0 0 1 2-2Z"></path>
            </svg>
            <span class="nav-label">Audit Logs</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-status-box">
            <div class="sidebar-status-header">
                <div class="sidebar-status-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                        <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                        <line x1="6" y1="6" x2="6.01" y2="6"></line>
                        <line x1="6" y1="18" x2="6.01" y2="18"></line>
                    </svg>
                    <span>System Status</span>
                </div>
                <span class="status-badge-live">Operational</span>
            </div>
            <p class="sidebar-status-desc">Platform is running smoothly<br>All systems functional</p>
        </div>

        <form method="POST" action="{{ route('admin.logout') }}" class="sidebar-logout-form" data-confirm data-confirm-title="Logout?" data-confirm-message="Are you sure you want to logout from Kryvex Admin?" data-confirm-button="Logout">
            @csrf
            <button class="sidebar-logout-btn" type="submit">
                <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout</span>
            </button>
        </form>
    </div>
</aside>
