@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $formatDate = function ($value) {
        if (empty($value)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d M Y, h:i A');
        } catch (\Throwable $exception) {
            return '-';
        }
    };
@endphp

<div class="dashboard-page admin-dashboard-v2">

    <!-- Hero Banner Card -->
    <section class="dashboard-hero-card">
        <div class="dashboard-hero-content">
            <p class="dashboard-eyebrow">KRYVEX ADMIN</p>
            <h1>Dashboard</h1>
            <span class="dashboard-subtitle">Monitor users, wallets, trades, revenue and pending withdrawal actions.</span>
        </div>
        <div class="dashboard-hero-meta">
            <svg class="meta-calendar-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                <line x1="16" y1="2" x2="16" y2="6"></line>
                <line x1="8" y1="2" x2="8" y2="6"></line>
                <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <div class="meta-datetime">
                <strong>{{ now()->format('d M Y') }}</strong>
                <small>{{ now()->format('h:i A') }}</small>
            </div>
        </div>
    </section>

    <!-- 8 Key Metrics Stats Grid (4x2) -->
    <section class="dashboard-stats-grid">
        <!-- 1. Total Users -->
        <a class="dash-stat-card theme-blue" href="{{ route('admin.users.index') }}">
            <div class="dash-stat-top">
                <div class="dash-stat-icon-wrap icon-blue">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="dash-stat-trend positive">
                    <span>+12.5%</span>
                    <svg class="trend-sparkline" width="36" height="16" viewBox="0 0 36 16" fill="none">
                        <path d="M1 13L8 9L15 11L24 5L35 2" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="dash-stat-body">
                <span class="dash-stat-label">TOTAL USERS</span>
                <strong class="dash-stat-value">{{ number_format($stats['total_users'] ?? 0) }}</strong>
                <small class="dash-stat-desc">Registered accounts</small>
            </div>
        </a>

        <!-- 2. Wallet Balance -->
        <a class="dash-stat-card theme-purple" href="{{ route('admin.wallet.index') }}">
            <div class="dash-stat-top">
                <div class="dash-stat-icon-wrap icon-purple">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 7V6a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h14a2 2 0 0 1 2 2v3"></path>
                        <path d="M3 6v12a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-3"></path>
                        <path d="M16 10h5v6h-5a3 3 0 0 1 0-6Z"></path>
                    </svg>
                </div>
                <div class="dash-stat-trend positive">
                    <span>+8.2%</span>
                    <svg class="trend-sparkline" width="36" height="16" viewBox="0 0 36 16" fill="none">
                        <path d="M1 14L8 10L16 12L24 6L35 3" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="dash-stat-body">
                <span class="dash-stat-label">WALLET BALANCE</span>
                <strong class="dash-stat-value">${{ number_format((float)($stats['wallet_balance'] ?? 0), 2) }}</strong>
                <small class="dash-stat-desc">Available &amp; trading locked</small>
            </div>
        </a>

        <!-- 3. Open Positions -->
        <a class="dash-stat-card theme-indigo" href="{{ route('admin.positions.open') }}">
            <div class="dash-stat-top">
                <div class="dash-stat-icon-wrap icon-indigo">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 3v18h18"></path>
                        <path d="m19 9-5 5-4-4-3 3"></path>
                    </svg>
                </div>
                <div class="dash-stat-trend positive">
                    <span>+5.7%</span>
                    <svg class="trend-sparkline" width="36" height="16" viewBox="0 0 36 16" fill="none">
                        <path d="M1 12L9 8L17 11L25 4L35 2" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="dash-stat-body">
                <span class="dash-stat-label">OPEN POSITIONS</span>
                <strong class="dash-stat-value">{{ number_format($stats['open_positions'] ?? 0) }}</strong>
                <small class="dash-stat-desc">Futures + options active trades</small>
            </div>
        </a>

        <!-- 4. Total Orders -->
        <a class="dash-stat-card theme-purple-alt" href="{{ route('admin.orders.index') }}">
            <div class="dash-stat-top">
                <div class="dash-stat-icon-wrap icon-purple-alt">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                        <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                        <path d="M10 9H8"></path>
                        <path d="M16 13H8"></path>
                        <path d="M16 17H8"></path>
                    </svg>
                </div>
                <div class="dash-stat-trend positive">
                    <span>+10.1%</span>
                    <svg class="trend-sparkline" width="36" height="16" viewBox="0 0 36 16" fill="none">
                        <path d="M1 13L9 11L16 13L26 5L35 2" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="dash-stat-body">
                <span class="dash-stat-label">TOTAL ORDERS</span>
                <strong class="dash-stat-value">{{ number_format($stats['total_orders'] ?? 0) }}</strong>
                <small class="dash-stat-desc">Futures + options orders</small>
            </div>
        </a>

        <!-- 5. Total Deposits -->
        <a class="dash-stat-card theme-green" href="{{ route('admin.wallet.deposits') }}">
            <div class="dash-stat-top">
                <div class="dash-stat-icon-wrap icon-green">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="8 12 12 16 16 12"></polyline>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                    </svg>
                </div>
                <div class="dash-stat-trend positive">
                    <span>+15.3%</span>
                    <svg class="trend-sparkline" width="36" height="16" viewBox="0 0 36 16" fill="none">
                        <path d="M1 14L8 10L17 11L25 5L35 2" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="dash-stat-body">
                <span class="dash-stat-label">TOTAL DEPOSITS</span>
                <strong class="dash-stat-value val-green">${{ number_format((float)($stats['total_deposits_amount'] ?? 0), 2) }}</strong>
                <small class="dash-stat-desc">{{ number_format($stats['total_deposits_count'] ?? 0) }} records</small>
            </div>
        </a>

        <!-- 6. Total Withdraws -->
        <a class="dash-stat-card theme-red" href="{{ route('admin.wallet.withdrawals') }}">
            <div class="dash-stat-top">
                <div class="dash-stat-icon-wrap icon-red">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="16 12 12 8 8 12"></polyline>
                        <line x1="12" y1="16" x2="12" y2="8"></line>
                    </svg>
                </div>
                <div class="dash-stat-trend negative">
                    <span>-3.2%</span>
                    <svg class="trend-sparkline" width="36" height="16" viewBox="0 0 36 16" fill="none">
                        <path d="M1 4L9 6L17 5L26 11L35 14" stroke="#ef4444" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="dash-stat-body">
                <span class="dash-stat-label">TOTAL WITHDRAWS</span>
                <strong class="dash-stat-value val-red">${{ number_format((float)($stats['total_withdrawals_amount'] ?? 0), 2) }}</strong>
                <small class="dash-stat-desc">{{ number_format($stats['pending_withdrawals'] ?? 0) }} pending requests</small>
            </div>
        </a>

        <!-- 7. Total Trades -->
        <a class="dash-stat-card theme-teal" href="{{ route('admin.reports.show', 'positions') }}">
            <div class="dash-stat-top">
                <div class="dash-stat-icon-wrap icon-teal">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="17 1 21 5 17 9"></polyline>
                        <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                        <polyline points="7 23 3 19 7 15"></polyline>
                        <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                    </svg>
                </div>
                <div class="dash-stat-trend positive">
                    <span>+7.8%</span>
                    <svg class="trend-sparkline" width="36" height="16" viewBox="0 0 36 16" fill="none">
                        <path d="M1 13L9 9L17 12L25 6L35 3" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="dash-stat-body">
                <span class="dash-stat-label">TOTAL TRADES</span>
                <strong class="dash-stat-value">{{ number_format($stats['total_trades'] ?? 0) }}</strong>
                <small class="dash-stat-desc">Executed trade records</small>
            </div>
        </a>

        <!-- 8. Revenue -->
        <a class="dash-stat-card theme-gold" href="{{ route('admin.wallet.ledger', ['type' => 'fee']) }}">
            <div class="dash-stat-top">
                <div class="dash-stat-icon-wrap icon-gold">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <path d="M12 6v12"></path>
                        <path d="M15 9.5a2.5 2.5 0 0 0-2.5-2.5h-1a2.5 2.5 0 0 0 0 5h1a2.5 2.5 0 0 1 0 5h-1a2.5 2.5 0 0 1-2.5-2.5"></path>
                    </svg>
                </div>
                <div class="dash-stat-trend positive">
                    <span>+11.6%</span>
                    <svg class="trend-sparkline" width="36" height="16" viewBox="0 0 36 16" fill="none">
                        <path d="M1 14L8 10L16 11L25 4L35 2" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            </div>
            <div class="dash-stat-body">
                <span class="dash-stat-label">REVENUE</span>
                <strong class="dash-stat-value val-gold">${{ number_format((float)($stats['platform_revenue'] ?? 0), 2) }}</strong>
                <small class="dash-stat-desc">Trading + approved charges</small>
            </div>
        </a>
    </section>

    <!-- Quick Actions Card -->
    <section class="dashboard-actions-card">
        <div class="dashboard-section-head compact">
            <div>
                <h2>Quick Actions</h2>
                <p>Open frequently used admin modules.</p>
            </div>
        </div>

        <div class="dashboard-action-grid">
            <a href="{{ route('admin.users.index') }}" class="dash-action-btn">
                <div class="dash-action-icon icon-blue">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div class="dash-action-text">
                    <strong>Manage Users</strong>
                    <small>View all accounts</small>
                </div>
            </a>

            <a href="{{ route('admin.wallet.withdrawals') }}" class="dash-action-btn">
                <div class="dash-action-icon icon-red">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 8 16 12 12 16"></polyline>
                        <line x1="8" y1="12" x2="16" y2="12"></line>
                    </svg>
                </div>
                <div class="dash-action-text">
                    <strong>Withdrawals</strong>
                    <small>Approve / reject</small>
                </div>
            </a>

            <a href="{{ route('admin.wallet.deposits') }}" class="dash-action-btn">
                <div class="dash-action-icon icon-green">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="8 12 12 16 16 12"></polyline>
                        <line x1="12" y1="8" x2="12" y2="16"></line>
                    </svg>
                </div>
                <div class="dash-action-text">
                    <strong>Deposits</strong>
                    <small>Funding history</small>
                </div>
            </a>

            <a href="{{ route('admin.positions.open') }}" class="dash-action-btn">
                <div class="dash-action-icon icon-gold">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                        <line x1="3" y1="9" x2="21" y2="9"></line>
                        <line x1="9" y1="21" x2="9" y2="9"></line>
                    </svg>
                </div>
                <div class="dash-action-text">
                    <strong>Open Positions</strong>
                    <small>Active exposure</small>
                </div>
            </a>
        </div>
    </section>

    <!-- 2-Column Records Grid (Recent Users & Recent Deposits) -->
    <section class="dashboard-record-grid">
        <!-- Recent Users Card -->
        <div class="dashboard-record-card">
            <div class="dashboard-section-head">
                <div>
                    <h2>Recent Users</h2>
                    <p>Latest 5 registered users.</p>
                </div>
                <a class="dash-view-all-btn" href="{{ route('admin.users.index') }}">View All</a>
            </div>

            <div class="dashboard-list">
                @forelse(($recentUsers ?? collect()) as $user)
                    @php
                        $statusValue = strtolower($user->status ?? 'active');
                        $initial = strtoupper(substr($user->name ?? $user->email ?? 'U', 0, 2));
                        if(strlen($initial) < 2) $initial = strtoupper(substr($user->name ?? 'US', 0, 2));
                        $userId = (string)($user->_id ?? $user->id ?? $user->uid ?? '');
                        $createdTime = !empty($user->created_at) ? \Carbon\Carbon::parse($user->created_at) : null;
                    @endphp
                    <div class="dashboard-list-row user-row">
                        <div class="dash-user-cell">
                            <div class="dash-avatar">{{ $initial }}</div>
                            <div class="dash-user-meta">
                                <strong>{{ $user->name ?? 'Unnamed User' }}</strong>
                                <small>{{ $user->email ?? '-' }}</small>
                            </div>
                        </div>
                        <div class="dash-row-right">
                            @if($createdTime)
                                <div class="dash-time-meta">
                                    <span>{{ $createdTime->format('d M Y') }}</span>
                                    <small>{{ $createdTime->format('h:i A') }}</small>
                                </div>
                            @endif
                            <span class="status-badge {{ $statusValue }}">{{ ucfirst($statusValue) }}</span>
                        </div>
                    </div>
                @empty
                    <div class="dashboard-empty">No recent users found.</div>
                @endforelse
            </div>
        </div>

        <!-- Recent Deposits Card -->
        <div class="dashboard-record-card">
            <div class="dashboard-section-head">
                <div>
                    <h2>Recent Deposits</h2>
                    <p>Latest 5 wallet funding records.</p>
                </div>
                <a class="dash-view-all-btn" href="{{ route('admin.wallet.deposits') }}">View All</a>
            </div>

            <div class="dashboard-list">
                @forelse(($recentDeposits ?? collect()) as $deposit)
                    @php
                        $depStatus = strtolower($deposit->status ?? 'completed');
                        $depInitial = strtoupper(substr($deposit->display_user_name ?? $deposit->display_user_email ?? 'DP', 0, 2));
                        $depTime = !empty($deposit->created_at) ? \Carbon\Carbon::parse($deposit->created_at) : null;
                    @endphp
                    <div class="dashboard-list-row deposit-row">
                        <div class="dash-user-cell">
                            <div class="dash-avatar deposit-avatar">{{ $depInitial }}</div>
                            <div class="dash-user-meta">
                                <strong>{{ $deposit->display_user_name ?? 'Unknown User' }}</strong>
                                <small>{{ $deposit->display_user_email ?? '-' }}</small>
                            </div>
                        </div>
                        <div class="dash-row-right">
                            <div class="dash-amount-cell">
                                <strong>${{ number_format((float)($deposit->amount ?? 0), 2) }}</strong>
                                @if($depTime)
                                    <small>{{ $depTime->format('d M Y, h:i A') }}</small>
                                @endif
                            </div>
                            <span class="status-badge {{ $depStatus }}">
                                {{ ucfirst($deposit->status ?? 'completed') }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="dashboard-empty">No recent deposits found.</div>
                @endforelse
            </div>
        </div>

        <!-- Withdrawals Handling Card -->
        <div class="dashboard-record-card dashboard-withdrawals-card">
            <div class="dashboard-section-head">
                <div>
                    <h2>Withdrawals</h2>
                    <p>Latest 5 requests. Pending records can be handled here.</p>
                </div>
                <a class="dash-view-all-btn" href="{{ route('admin.wallet.withdrawals') }}">View All</a>
            </div>

            <div class="dashboard-list withdrawals-list">
                @forelse(($recentWithdrawals ?? collect()) as $item)
                    @php
                        $statusValue = strtolower($item->status ?? 'pending');
                        $withdrawalId = (string)($item->withdrawal_id ?? $item->_id ?? $item->id ?? '');
                        $feeTotal = (float)(($item->withdraw_fee ?? 0) + ($item->platform_charge ?? 0));
                    @endphp
                    <div class="dashboard-withdrawal-row">
                        <div class="withdrawal-main-info">
                            <strong>{{ $item->display_user_name ?? 'Unknown User' }}</strong>
                            <small>{{ $item->display_user_email ?? '-' }}</small>
                        </div>

                        <div class="withdrawal-money-info">
                            <span>Amount</span>
                            <strong>${{ number_format((float)($item->amount ?? 0), 2) }}</strong>
                        </div>

                        <div class="withdrawal-money-info">
                            <span>Receives</span>
                            <strong>${{ number_format((float)($item->net_amount ?? $item->amount ?? 0), 2) }}</strong>
                            <small>Fees ${{ number_format($feeTotal, 2) }}</small>
                        </div>

                        <span class="status-badge {{ $statusValue }}">{{ ucfirst($statusValue) }}</span>

                        <div class="dashboard-withdrawal-actions">
                            @if($statusValue === 'pending' && $withdrawalId !== '')
                                @php
                                    $userAddress = trim((string)($item->wallet_address ?? $item->withdrawal_address ?? $item->user_wallet_address ?? $item->address ?? ''));
                                    $approveFormId = 'approve-withdrawal-' . preg_replace('/[^A-Za-z0-9_-]/', '', $withdrawalId);
                                @endphp

                                <form id="{{ $approveFormId }}" method="POST" action="{{ route('admin.wallet.withdrawals.approve', $withdrawalId) }}" class="withdraw-approve-hidden-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="confirmed_wallet_address" value="">
                                </form>

                                <button class="dash-approve-btn" type="button"
                                    data-open-withdraw-approve-modal
                                    data-form-id="{{ $approveFormId }}"
                                    data-user-name="{{ $item->display_user_name ?? 'Unknown User' }}"
                                    data-user-email="{{ $item->display_user_email ?? '-' }}"
                                    data-amount="${{ number_format((float)($item->amount ?? 0), 2) }}"
                                    data-fees="${{ number_format($feeTotal, 2) }}"
                                    data-net="${{ number_format((float)($item->net_amount ?? $item->amount ?? 0), 2) }}"
                                    data-network="{{ $item->network ?? '-' }}"
                                    data-address="{{ $userAddress }}">
                                    Approve
                                </button>

                                <form method="POST"
                                      action="{{ route('admin.wallet.withdrawals.reject', $withdrawalId) }}"
                                      data-confirm
                                      data-confirm-title="Reject withdrawal?"
                                      data-confirm-message="Amount will be returned to user available balance."
                                      data-confirm-button="Reject"
                                      data-confirm-danger="true"
                                      data-requires-note="true"
                                      data-note-input="admin_note"
                                      data-default-note="Rejected by admin">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="admin_note" value="Rejected by admin">
                                    <button type="submit" class="dash-reject-btn">Reject</button>
                                </form>
                            @else
                                <span class="dashboard-completed-text">Completed</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="dashboard-empty">No withdrawal requests found.</div>
                @endforelse
            </div>
        </div>
    </section>

</div>

<div class="admin-modal-backdrop withdraw-approve-modal" id="withdrawApproveModal" aria-hidden="true">
    <div class="admin-modal-card withdraw-approve-card" role="dialog" aria-modal="true" aria-labelledby="withdrawApproveTitle">
        <button type="button" class="admin-modal-close" id="withdrawApproveClose">×</button>

        <div class="withdraw-modal-icon">✓</div>
        <h3 id="withdrawApproveTitle">Approve Withdrawal</h3>
        <p>Copy the user's withdrawal address and paste it below. Approval will work only when the address matches exactly.</p>

        <div class="withdraw-compact-address-card">
            <div class="withdraw-compact-row">
                <span>Network</span>
                <strong id="withdrawModalNetwork">--</strong>
            </div>
            <div class="withdraw-address-copy-box">
                <span>User Withdrawal Address</span>
                <code id="withdrawModalAddress">--</code>
                <button type="button" id="withdrawCopyAddressButton">Copy</button>
            </div>
        </div>

        <label class="withdraw-confirm-label">
            Paste exact user address
            <input type="text" id="withdrawConfirmAddressInput" placeholder="Paste exact user address here">
        </label>

        <div class="withdraw-confirm-help" id="withdrawConfirmHelp">Address must match exactly before approval.</div>

        <div class="withdraw-modal-actions">
            <button type="button" class="modal-cancel-btn" id="withdrawApproveCancel">Cancel</button>
            <button type="button" class="approve-btn" id="withdrawApproveSubmit" disabled>Approve Withdrawal</button>
        </div>
    </div>
</div>
@endsection
