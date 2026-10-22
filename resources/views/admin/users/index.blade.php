@extends('admin.layouts.app')

@section('title', 'Users Management | Kryvex Admin')

@section('content')
@php
    $activeStatus = $status ?? 'all';
@endphp

<section class="page-head users-page-head users-hero-head">
    <div class="users-hero-content">
        <p class="eyebrow">User Management</p>
        <h1>Users</h1>
        <span class="users-hero-sub">Monitor user accounts, balances, active status and account actions from one place.</span>
    </div>
    <div class="page-actions users-hero-actions">
        <a class="soft-btn hover-lift {{ request()->query() ? '' : 'is-active' }}" href="{{ route('admin.users.index') }}">Reset</a>
        <button class="primary-btn hover-lift" type="button" disabled title="Export will be connected in Reports module">Export Users</button>
    </div>
</section>

<section class="users-stats-grid users-stats-grid-v2">
    <!-- 1. Total Users -->
    <div class="user-stat-card stat-total hover-card">
        <div class="user-stat-card-left">
            <div class="stat-icon-badge icon-purple">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Users</span>
                <strong class="stat-value">{{ number_format($stats['total'] ?? 0) }}</strong>
                <small class="stat-desc">All registered accounts</small>
            </div>
        </div>
        <div class="stat-sparkline-wrap">
            <svg class="stat-sparkline" width="44" height="20" viewBox="0 0 44 20" fill="none">
                <path d="M1 18L10 14L20 16L32 6L43 2" stroke="#8b5cf6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>

    <!-- 2. Active -->
    <div class="user-stat-card stat-active hover-card">
        <div class="user-stat-card-left">
            <div class="stat-icon-badge icon-green">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Active</span>
                <strong class="stat-value">{{ number_format($stats['active'] ?? 0) }}</strong>
                <small class="stat-desc">Currently allowed users</small>
            </div>
        </div>
        <div class="stat-sparkline-wrap">
            <svg class="stat-sparkline" width="44" height="20" viewBox="0 0 44 20" fill="none">
                <path d="M1 16L11 12L21 15L31 7L43 2" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>

    <!-- 3. Suspended -->
    <div class="user-stat-card stat-suspended hover-card">
        <div class="user-stat-card-left">
            <div class="stat-icon-badge icon-amber">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Suspended</span>
                <strong class="stat-value">{{ number_format($stats['suspended'] ?? 0) }}</strong>
                <small class="stat-desc">Restricted temporarily</small>
            </div>
        </div>
        <div class="stat-sparkline-wrap">
            <svg class="stat-sparkline" width="44" height="20" viewBox="0 0 44 20" fill="none">
                <path d="M1 18L12 16L24 17L34 10L43 6" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>

    <!-- 4. Total Balance -->
    <div class="user-stat-card stat-balance hover-card">
        <div class="user-stat-card-left">
            <div class="stat-icon-badge icon-blue">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Balance</span>
                <strong class="stat-value">${{ number_format((float)($stats['total_balance'] ?? 0), 2) }}</strong>
                <small class="stat-desc">Available + locked wallets</small>
            </div>
        </div>
        <div class="stat-sparkline-wrap">
            <svg class="stat-sparkline" width="44" height="20" viewBox="0 0 44 20" fill="none">
                <path d="M1 14L10 16L21 12L32 15L43 4" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </div>
    </div>
</section>

<section class="admin-panel users-panel users-panel-v2 hover-panel">
    <div class="panel-top users-panel-top-v2">
        <div>
            <h2>All Users</h2>
            <p>Search users, filter by status, and take quick account actions.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.users.index') }}" class="users-filter-form users-filter-form-v2" id="usersFilterForm">
        <div class="users-search-shell">
            <svg class="users-search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
            <input type="text" name="search" id="usersLiveSearch" value="{{ $search }}" placeholder="Search by Client ID, name or email">
            @if($activeStatus !== 'all')
                <input type="hidden" name="status" value="{{ $activeStatus }}">
            @endif
        </div>

        <div class="filter-actions users-search-actions">
            <button class="primary-btn filter-btn hover-lift" type="submit">Search</button>
            <a class="soft-btn filter-reset hover-lift" href="{{ route('admin.users.index') }}">Clear</a>
        </div>
    </form>

    <div class="users-filter-tabs" aria-label="User status filters">
        <a class="users-filter-tab {{ $activeStatus === 'all' ? 'is-active' : '' }}" href="{{ route('admin.users.index', array_filter(['search' => $search])) }}">
            All
        </a>
        <a class="users-filter-tab active {{ $activeStatus === 'active' ? 'is-active' : '' }}" href="{{ route('admin.users.index', array_filter(['search' => $search, 'status' => 'active'])) }}">
            Active
        </a>
        <a class="users-filter-tab suspended {{ $activeStatus === 'suspended' ? 'is-active' : '' }}" href="{{ route('admin.users.index', array_filter(['search' => $search, 'status' => 'suspended'])) }}">
            Suspended
        </a>
        <a class="users-filter-tab blocked {{ $activeStatus === 'blocked' ? 'is-active' : '' }}" href="{{ route('admin.users.index', array_filter(['search' => $search, 'status' => 'blocked'])) }}">
            Blocked
        </a>
    </div>

    <div class="users-table-wrap">
        <table class="admin-users-table admin-users-table-v2">
            <thead>
                <tr>
                    <th>USER</th>
                    <th>CLIENT ID</th>
                    <th>EMAIL</th>
                    <th>REFERRED BY</th>
                    <th>BALANCE</th>
                    <th>STATUS</th>
                    <th>JOINED</th>
                    <th class="text-right">ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $index => $user)
                    @php
                        $statusValue = strtolower($user->status ?? 'active');
                        $currentUserId = (string) ($user->_id ?? $user->id ?? $user->uid ?? '');
                        $rawName = trim($user->name ?? '');
                        $rawEmail = trim($user->email ?? '');
                        $displayName = !empty($rawName) ? $rawName : (!empty($rawEmail) ? explode('@', $rawEmail)[0] : 'User #' . substr($currentUserId, -4));
                        $initial = strtoupper(substr($displayName, 0, 1));
                        $statusUrl = route('admin.users.status', $currentUserId);
                        $avatarThemes = ['avatar-purple', 'avatar-blue', 'avatar-teal', 'avatar-gold'];
                        $avatarClass = $avatarThemes[$index % count($avatarThemes)];
                    @endphp
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar {{ $avatarClass }}">{{ $initial }}</div>
                                <div class="user-info-meta">
                                    <strong class="user-name-title">{{ $displayName }}</strong>
                                    <span class="user-id-sub">ID: {{ $currentUserId }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="user-client-id">
                                {{ $user->client_id ?? '--' }}
                            </span>
                        </td>
                        <td>
                            <span class="user-email-text">{{ !empty($rawEmail) ? $rawEmail : '—' }}</span>
                        </td>
                        <td>
                            @if(!empty($user->referred_by) && isset($referrersMap[$user->referred_by]))
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 6px; background: rgba(168, 85, 247, 0.12); color: #c084fc; font-weight: 600; font-size: 0.8rem;">
                                    {{ $referrersMap[$user->referred_by] }}
                                </span>
                            @elseif(!empty($user->referred_by))
                                <span style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 6px; background: rgba(168, 85, 247, 0.12); color: #c084fc; font-weight: 600; font-size: 0.8rem;">
                                    {{ substr((string)$user->referred_by, 0, 8) }}
                                </span>
                            @else
                                <span style="color: #64748b; font-size: 0.82rem;">Direct</span>
                            @endif
                        </td>
                        <td>
                            <strong class="user-balance-text">${{ number_format((float)($user->admin_wallet_balance ?? 0), 2) }}</strong>
                        </td>
                        <td>
                            <span class="status-indicator-pill {{ $statusValue }}">
                                <span class="status-dot-sm"></span>
                                {{ ucfirst($statusValue) }}
                            </span>
                        </td>
                        <td>
                            <span class="user-joined-date">{{ optional($user->created_at)->format('d M Y') ?? '—' }}</span>
                        </td>
                        <td class="text-right">
                            <div class="row-actions user-row-actions-v2">
                                <a href="{{ route('admin.users.show', $currentUserId) }}" class="table-action view-action" title="View Details">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                    View
                                </a>
                                <button type="button"
                                    class="table-action suspend-inline-action"
                                    data-open-user-suspend
                                    data-status-url="{{ $statusUrl }}"
                                    data-user-name="{{ $displayName }}"
                                    data-user-email="{{ $user->email ?? '-' }}"
                                    title="Suspend User">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                    Suspend
                                </button>
                                <button type="button"
                                    class="table-action block-inline-action"
                                    data-open-user-block
                                    data-status-url="{{ $statusUrl }}"
                                    data-user-name="{{ $displayName }}"
                                    data-user-email="{{ $user->email ?? '-' }}"
                                    title="Block User">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                                    Block
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <div class="empty-state">
                                <strong>No users found</strong>
                                <span>Try changing your search or filters.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap">
        @include('admin.components.pagination', ['paginator' => $users])
    </div>
</section>

<div id="indexSuspendUserModal" class="admin-suspend-modal" aria-hidden="true">
    <div class="admin-suspend-backdrop" data-close-index-suspend-modal></div>

    <div class="admin-suspend-box" role="dialog" aria-modal="true" aria-labelledby="indexSuspendUserTitle">
        <div class="admin-suspend-icon">!</div>

        <div class="admin-suspend-head">
            <h2 id="indexSuspendUserTitle">Schedule User Suspension</h2>
            <p>
                <span data-index-suspend-user-name>User</span> will receive a warning first. After grace time ends, wallet and trading access will be suspended.
            </p>
        </div>

        <form method="POST" action="#" class="admin-suspend-form" id="indexSuspendUserForm">
            @csrf
            @method('PATCH')

            <input type="hidden" name="status" value="suspended">

            <label>
                <span>Suspension reason</span>
                <textarea name="reason" required rows="4" placeholder="Example: Suspicious activity, policy violation, risk review..."></textarea>
            </label>

            <div class="admin-suspend-grid">
                <label>
                    <span>Suspend after</span>
                    <div class="admin-suspend-input-wrap">
                        <input type="number" name="delay_minutes" min="1" max="1440" step="1" value="10" required inputmode="numeric" placeholder="Enter minutes">
                        <em>minutes</em>
                    </div>
                    <small class="admin-suspend-help">User remains active for this grace time.</small>
                </label>

                <label>
                    <span>Suspension duration</span>
                    <div class="admin-suspend-input-wrap">
                        <input type="number" name="duration_hours" min="1" max="720" step="1" value="12" required inputmode="numeric" placeholder="Enter hours">
                        <em>hours</em>
                    </div>
                    <small class="admin-suspend-help">Account auto-activates after this duration.</small>
                </label>
            </div>

            <div class="admin-suspend-warning">
                During grace time, user can close open trades. After suspension starts, wallet and trading actions will be blocked.
            </div>

            <div class="admin-suspend-actions">
                <button type="button" class="admin-suspend-cancel" data-close-index-suspend-modal>Cancel</button>
                <button type="submit" class="admin-suspend-submit">Schedule Suspension</button>
            </div>
        </form>
    </div>
</div>

<div id="indexBlockUserModal" class="admin-suspend-modal" aria-hidden="true">
    <div class="admin-suspend-backdrop" data-close-index-block-modal></div>

    <div class="admin-suspend-box admin-block-box" role="dialog" aria-modal="true" aria-labelledby="indexBlockUserTitle">
        <div class="admin-suspend-icon admin-block-icon">×</div>

        <div class="admin-suspend-head">
            <h2 id="indexBlockUserTitle">Schedule User Block</h2>
            <p>
                <span data-index-block-user-name>User</span> will receive a final warning first. After grace time ends, the account will no longer be accessible.
            </p>
        </div>

        <form method="POST" action="#" class="admin-suspend-form" id="indexBlockUserForm">
            @csrf
            @method('PATCH')

            <input type="hidden" name="status" value="blocked">

            <label>
                <span>Block reason</span>
                <textarea name="reason" required rows="4" placeholder="Example: Security risk, compliance issue, repeated policy violation..."></textarea>
            </label>

            <div class="admin-suspend-grid single">
                <label>
                    <span>Block after</span>
                    <div class="admin-suspend-input-wrap danger">
                        <input type="number" name="block_delay_minutes" min="1" max="1440" step="1" value="10" required inputmode="numeric" placeholder="Enter minutes">
                        <em>minutes</em>
                    </div>
                    <small class="admin-suspend-help">User can use this grace time to make final account changes before access is blocked.</small>
                </label>
            </div>

            <div class="admin-suspend-warning admin-block-warning">
                After this grace time ends, the user will not be able to access trading or wallet actions until admin activates the account.
            </div>

            <div class="admin-suspend-actions">
                <button type="button" class="admin-suspend-cancel" data-close-index-block-modal>Cancel</button>
                <button type="submit" class="admin-suspend-submit admin-block-submit">Schedule Block</button>
            </div>
        </form>
    </div>
</div>
@endsection
