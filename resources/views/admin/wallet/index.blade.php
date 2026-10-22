@extends('admin.layouts.app')

@section('title', 'Wallet Management')

@section('content')
<div class="wallet-page">
    <div class="wallet-hero">
        <div>
            <span class="wallet-kicker">Admin Wallet Center</span>
            <h1>Wallet Overview</h1>
            <p>Monitor wallet transactions, deposits and withdrawal activity from one clean control panel.</p>
        </div>
        <div class="wallet-hero-pill" style="display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
            <div style="font-size: 11px; text-transform: uppercase; color: #8b8f9b; font-weight: 700; letter-spacing: 0.05em;">Total Balance Summary</div>
            <div style="display: flex; gap: 12px; font-size: 14px;">
                <span style="color: #f59e0b; font-weight: 800; font-family: 'JetBrains Mono', monospace;">🇮🇳 ₹{{ number_format((float)($stats['inr_total_balance'] ?? 0), 2) }}</span>
                <span style="color: #10b981; font-weight: 800; font-family: 'JetBrains Mono', monospace;">🇺🇸 ${{ number_format((float)($stats['usdt_total_balance'] ?? 0), 2) }}</span>
            </div>
        </div>
    </div>

    <div class="wallet-tabs">
        <a href="{{ route('admin.wallet.index') }}" class="wallet-tab active">Overview</a>
        <a href="{{ route('admin.wallet.deposits') }}" class="wallet-tab">Deposits</a>
        <a href="{{ route('admin.wallet.withdrawals') }}" class="wallet-tab">Withdrawals</a>
        <a href="{{ route('admin.wallet.internal_transfers') }}" class="wallet-tab">Internal Transfers</a>
        <a href="{{ route('admin.wallet.ledger') }}" class="wallet-tab">Ledger</a>
    </div>

    <div class="wallet-stats four-cards-grid" style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px;">
        <!-- Card 1: INR Total Balance -->
        <div class="wallet-card overview-stat-card" style="position: relative; overflow: hidden; background: linear-gradient(135deg, rgba(16, 20, 29, 0.96), rgba(9, 11, 16, 0.98)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 18px; padding: 18px;">
            <div class="card-inner-glow" style="background: rgba(245, 158, 11, 0.12); top: -60px; right: -60px; width: 180px; height: 180px; position: absolute; border-radius: 999px; filter: blur(50px); pointer-events: none;"></div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 8px; height: 8px; border-radius: 999px; background: #f59e0b; box-shadow: 0 0 10px rgba(245, 158, 11, 0.8);"></span>
                    <span style="font-weight: 800; color: #fff; font-size: 13px;">INR Total Balance</span>
                </div>
                <span style="background: rgba(245, 158, 11, 0.12); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.25); padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 800;">🇮🇳 INR</span>
            </div>
            
            <h3 style="color: #f59e0b; font-size: 24px; font-weight: 900; font-family: 'JetBrains Mono', monospace; margin: 0 0 4px 0; text-shadow: 0 0 15px rgba(245, 158, 11, 0.2);">₹{{ number_format((float)($stats['inr_total_balance'] ?? 0), 2) }}</h3>
            <p style="color: #64748b; font-size: 11px; margin: 0 0 12px 0;">Main balance net funds</p>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; border-top: 1px solid rgba(255, 255, 255, 0.06); padding-top: 10px;">
                <div>
                    <span style="font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">MAIN WALLET</span>
                    <strong style="color: #f59e0b; font-size: 12px; font-family: 'JetBrains Mono', monospace; font-weight: 700; margin-top: 2px; display: block;">₹{{ number_format((float)($stats['inr_main_balance'] ?? 0), 2) }}</strong>
                </div>
                <div>
                    <span style="font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">TRADING WALLET</span>
                    <strong style="color: #64748b; font-size: 11px; margin-top: 2px; display: block;" title="Trading balances in USDT">0 USDT</strong>
                </div>
            </div>
        </div>

        <!-- Card 2: USDT Total Balance -->
        <div class="wallet-card overview-stat-card" style="position: relative; overflow: hidden; background: linear-gradient(135deg, rgba(16, 20, 29, 0.96), rgba(9, 11, 16, 0.98)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 18px; padding: 18px;">
            <div class="card-inner-glow" style="background: rgba(16, 185, 129, 0.12); top: -60px; right: -60px; width: 180px; height: 180px; position: absolute; border-radius: 999px; filter: blur(50px); pointer-events: none;"></div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 8px; height: 8px; border-radius: 999px; background: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.8);"></span>
                    <span style="font-weight: 800; color: #fff; font-size: 13px;">USDT Total Balance</span>
                </div>
                <span style="background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.25); padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 800;">🇺🇸 USDT</span>
            </div>
            
            <h3 style="color: #10b981; font-size: 24px; font-weight: 900; font-family: 'JetBrains Mono', monospace; margin: 0 0 4px 0; text-shadow: 0 0 15px rgba(16, 185, 129, 0.2);">${{ number_format((float)($stats['usdt_total_balance'] ?? 0), 2) }}</h3>
            <p style="color: #64748b; font-size: 11px; margin: 0 0 12px 0;">Main + Trading balances</p>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 6px; border-top: 1px solid rgba(255, 255, 255, 0.06); padding-top: 10px;">
                <div>
                    <span style="font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">MAIN WALLET</span>
                    <strong style="color: #e2e8f0; font-size: 12px; font-family: 'JetBrains Mono', monospace; font-weight: 700; margin-top: 2px; display: block;">${{ number_format((float)($stats['usdt_main_balance'] ?? 0), 2) }}</strong>
                </div>
                <div>
                    <span style="font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">TRADING WALLET</span>
                    <strong style="color: #3b82f6; font-size: 12px; font-family: 'JetBrains Mono', monospace; font-weight: 700; margin-top: 2px; display: block;">${{ number_format((float)($stats['usdt_trading_balance'] ?? 0), 2) }}</strong>
                </div>
            </div>
        </div>

        <!-- Card 3: INR Platform Revenue -->
        <div class="wallet-card overview-stat-card" id="inrPlatformRevenueCard" style="position: relative; overflow: hidden; background: linear-gradient(135deg, rgba(16, 20, 29, 0.96), rgba(9, 11, 16, 0.98)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 18px; padding: 18px; cursor: pointer;">
            <div class="card-inner-glow" style="background: rgba(245, 158, 11, 0.12); top: -60px; right: -60px; width: 180px; height: 180px; position: absolute; border-radius: 999px; filter: blur(50px); pointer-events: none;"></div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 8px; height: 8px; border-radius: 999px; background: #f59e0b; box-shadow: 0 0 10px rgba(245, 158, 11, 0.8);"></span>
                    <span style="font-weight: 800; color: #fff; font-size: 13px;">INR Revenue</span>
                </div>
                <button type="button" id="openWithdrawInrRevenueButton" style="background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; border: none; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; cursor: pointer; box-shadow: 0 2px 10px rgba(245, 158, 11, 0.3);">
                    Withdraw
                </button>
            </div>
            
            <h3 style="color: #f59e0b; font-size: 24px; font-weight: 900; font-family: 'JetBrains Mono', monospace; margin: 0 0 4px 0; text-shadow: 0 0 15px rgba(245, 158, 11, 0.2);">₹{{ number_format((float)($stats['inr_platform_revenue'] ?? 0), 2) }}</h3>
            <p style="color: #64748b; font-size: 11px; margin: 0 0 12px 0;">Available INR profit</p>

            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; border-top: 1px solid rgba(255, 255, 255, 0.06); padding-top: 10px;">
                <div>
                    <span style="font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">WITHDRAW</span>
                    <strong style="color: #e2e8f0; font-size: 11px; font-family: 'JetBrains Mono', monospace; margin-top: 2px; display: block;">₹{{ number_format((float)($stats['inr_withdrawal_fee_income'] ?? 0), 2) }}</strong>
                </div>
                <div>
                    <span style="font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">PLATFORM</span>
                    <strong style="color: #e2e8f0; font-size: 11px; font-family: 'JetBrains Mono', monospace; margin-top: 2px; display: block;">₹{{ number_format((float)($stats['inr_platform_charge_income'] ?? 0), 2) }}</strong>
                </div>
                <div>
                    <span style="font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">TODAY</span>
                    <strong style="color: #10b981; font-size: 11px; font-family: 'JetBrains Mono', monospace; margin-top: 2px; display: block;">₹{{ number_format((float)($stats['inr_today_revenue'] ?? 0), 2) }}</strong>
                </div>
            </div>
        </div>

        <!-- Card 4: USDT Platform Revenue -->
        <div class="wallet-card overview-stat-card" id="usdtPlatformRevenueCard" style="position: relative; overflow: hidden; background: linear-gradient(135deg, rgba(16, 20, 29, 0.96), rgba(9, 11, 16, 0.98)); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 18px; padding: 18px; cursor: pointer;">
            <div class="card-inner-glow" style="background: rgba(16, 185, 129, 0.12); top: -60px; right: -60px; width: 180px; height: 180px; position: absolute; border-radius: 999px; filter: blur(50px); pointer-events: none;"></div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="width: 8px; height: 8px; border-radius: 999px; background: #10b981; box-shadow: 0 0 10px rgba(16, 185, 129, 0.8);"></span>
                    <span style="font-weight: 800; color: #fff; font-size: 13px;">USDT Revenue</span>
                </div>
                <button type="button" id="openWithdrawUsdtRevenueButton" style="background: linear-gradient(135deg, #10b981, #059669); color: #fff; border: none; padding: 4px 12px; border-radius: 8px; font-size: 11px; font-weight: 800; cursor: pointer; box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3);">
                    Withdraw
                </button>
            </div>
            
            <h3 style="color: #10b981; font-size: 24px; font-weight: 900; font-family: 'JetBrains Mono', monospace; margin: 0 0 4px 0; text-shadow: 0 0 15px rgba(16, 185, 129, 0.2);">${{ number_format((float)($stats['usdt_platform_revenue'] ?? 0), 2) }}</h3>
            <p style="color: #64748b; font-size: 11px; margin: 0 0 12px 0;">Available USDT profit</p>

            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 2px; border-top: 1px solid rgba(255, 255, 255, 0.06); padding-top: 10px;">
                <div>
                    <span style="font-size: 8px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">TRADING</span>
                    <strong style="color: #e2e8f0; font-size: 10px; font-family: 'JetBrains Mono', monospace; margin-top: 2px; display: block;">${{ number_format((float)($stats['usdt_trading_fee_income'] ?? 0), 2) }}</strong>
                </div>
                <div>
                    <span style="font-size: 8px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">WITHDRAW</span>
                    <strong style="color: #e2e8f0; font-size: 10px; font-family: 'JetBrains Mono', monospace; margin-top: 2px; display: block;">${{ number_format((float)($stats['usdt_withdrawal_fee_income'] ?? 0), 2) }}</strong>
                </div>
                <div>
                    <span style="font-size: 8px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">PLATFORM</span>
                    <strong style="color: #e2e8f0; font-size: 10px; font-family: 'JetBrains Mono', monospace; margin-top: 2px; display: block;">${{ number_format((float)($stats['usdt_platform_charge_income'] ?? 0), 2) }}</strong>
                </div>
                <div>
                    <span style="font-size: 8px; text-transform: uppercase; color: #64748b; font-weight: 700; display: block;">TODAY</span>
                    <strong style="color: #10b981; font-size: 10px; font-family: 'JetBrains Mono', monospace; margin-top: 2px; display: block;">${{ number_format((float)($stats['usdt_today_revenue'] ?? 0), 2) }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="wallet-grid wallet-overview-grid">
        <div class="wallet-panel wallet-record-panel">
            <div class="panel-title-row">
                <div>
                    <span>Latest activity</span>
                    <h2>Recent Deposits</h2>
                </div>
                <a href="{{ route('admin.wallet.deposits') }}" class="view-all-link">View All</a>
            </div>

            <div class="record-list">
                @forelse($recentDeposits as $item)
                    <div class="record-row deposit-row">
                        <div class="record-user">
                            <span class="record-avatar">{{ strtoupper(substr($item->display_user_name ?? 'U', 0, 1)) }}</span>
                            <div>
                                <strong>{{ $item->display_user_name ?? 'Unknown User' }}</strong>
                                <small>{{ $item->display_user_email ?? '-' }}</small>
                            </div>
                        </div>
                        <div class="record-amount positive">+{{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)($item->display_amount ?? $item->amount ?? 0), 2) }}</div>
                        <div class="record-meta">
                            <span>{{ ucfirst(str_replace('_', ' ', $item->type ?? 'Deposit')) }}</span>
                            <small>{{ isset($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('d M Y, h:i A') : '-' }}</small>
                        </div>
                    </div>
                @empty
                    <div class="empty-state">No recent deposit records found.</div>
                @endforelse
            </div>
        </div>

        <div class="wallet-panel wallet-record-panel">
            <div class="panel-title-row">
                <div>
                    <span>Approval tracking</span>
                    <h2>Recent Withdrawals</h2>
                </div>
                <a href="{{ route('admin.wallet.withdrawals') }}" class="view-all-link">View All</a>
            </div>

            <div class="record-list">
                @forelse($recentWithdrawals as $item)
                    <div class="record-row withdrawal-row">
                        <div class="record-user">
                            <span class="record-avatar warning">{{ strtoupper(substr($item->display_user_name ?? 'U', 0, 1)) }}</span>
                            <div>
                                <strong>{{ $item->display_user_name ?? $item->user_name ?? 'Unknown User' }}</strong>
                                <small>{{ $item->display_user_email ?? $item->user_email ?? '-' }}</small>
                            </div>
                        </div>
                        <div class="record-amount negative">-{{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)($item->display_amount ?? $item->amount ?? 0), 2) }}</div>
                        <div class="record-meta">
                            <span class="status-badge {{ $item->status ?? 'pending' }}">{{ ucfirst($item->status ?? 'pending') }}</span>
                            <small>{{ isset($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('d M Y, h:i A') : '-' }}</small>
                        </div>
                        @if(($item->status ?? 'pending') === 'pending')
                            <div class="record-actions">
                                @php
                                    $withdrawalKey = (string)($item->withdrawal_id ?? $item->_id ?? '');
                                    $approveFormId = 'approve-withdrawal-' . preg_replace('/[^A-Za-z0-9_-]/', '', $withdrawalKey);
                                    $userAddress = trim((string)($item->wallet_address ?? $item->withdrawal_address ?? $item->user_wallet_address ?? $item->address ?? ''));
                                    $feeTotal = (float)(($item->withdraw_fee ?? 0) + ($item->platform_charge ?? 0));
                                @endphp

                                <form id="{{ $approveFormId }}" method="POST" action="{{ route('admin.wallet.withdrawals.approve', $withdrawalKey) }}" class="withdraw-approve-hidden-form">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="confirmed_wallet_address" value="">
                                </form>

                                <button class="approve-btn mini" type="button"
                                    data-open-withdraw-approve-modal
                                    data-form-id="{{ $approveFormId }}"
                                    data-user-name="{{ $item->display_user_name ?? $item->user_name ?? 'Unknown User' }}"
                                    data-user-email="{{ $item->display_user_email ?? $item->user_email ?? '-' }}"
                                    data-amount="${{ number_format((float)($item->amount ?? 0), 2) }}"
                                    data-fees="${{ number_format($feeTotal, 2) }}"
                                    data-net="${{ number_format((float)($item->net_amount ?? $item->amount ?? 0), 2) }}"
                                    data-network="{{ $item->network ?? '-' }}"
                                    data-address="{{ $userAddress }}">
                                    Approve
                                </button>
                                <form method="POST" action="{{ route('admin.wallet.withdrawals.reject', $withdrawalKey) }}" data-confirm data-confirm-title="Reject withdrawal?" data-confirm-message="Enter rejection reason. Amount will be returned to user wallet." data-confirm-button="Reject" data-confirm-danger="true" data-requires-note="true" data-note-input="admin_note" data-default-note="Rejected by admin">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="admin_note" value="Rejected by admin">
                                    <button class="reject-btn mini" type="submit">Reject</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="empty-state">No recent withdrawal records found.</div>
                @endforelse
            </div>
        </div>
    </div>
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

<div class="admin-modal-backdrop withdraw-approve-modal" id="adminWithdrawRevenueModal" aria-hidden="true" style="display: none; align-items: center; justify-content: center; position: fixed; inset: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); z-index: 1050; padding: 20px; overflow-y: auto;">
    <div class="admin-modal-card withdraw-revenue-wide-card" role="dialog" aria-modal="true" aria-labelledby="withdrawRevenueTitle" style="max-width: 880px; width: 100%; background: #0b0d13; border: 1px solid rgba(255,255,255,0.08); border-radius: 20px; padding: 30px; position: relative; box-shadow: 0 20px 40px rgba(0,0,0,0.5);">
        <button type="button" class="admin-modal-close" id="withdrawRevenueClose" style="position: absolute; right: 20px; top: 20px; font-size: 24px; color: #8b8f9b; background: transparent; border: none; cursor: pointer; transition: 0.2s;">&times;</button>

        <!-- Top Header Row: Title & Currency Switcher Pill Container -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; gap: 16px; flex-wrap: wrap; padding-right: 30px;">
            <div>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <h3 id="withdrawRevenueTitle" style="font-size: 22px; font-weight: 800; color: #fff; margin: 0;">Admin Profit Wallet</h3>
                    <span id="revenueModalBadge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 800;">USDT Wallet ($)</span>
                </div>
                <p style="font-size: 13px; color: #8b8f9b; margin: 4px 0 0 0;">Manage and withdraw platform-wide accumulated revenue fees in USDT or INR.</p>
            </div>

            <!-- Currency Switcher Pill -->
            <div style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); padding: 4px; border-radius: 999px; display: inline-flex; gap: 4px;">
                <button type="button" id="tabSwitchUsdt" style="padding: 7px 16px; border-radius: 999px; font-size: 12px; font-weight: 800; cursor: pointer; border: none; transition: 0.2s; background: #10b981; color: #fff; display: flex; align-items: center; gap: 6px;">
                    <span>🟡 USDT Wallet ($)</span>
                </button>
                <button type="button" id="tabSwitchInr" style="padding: 7px 16px; border-radius: 999px; font-size: 12px; font-weight: 800; cursor: pointer; border: none; transition: 0.2s; background: transparent; color: #8b8f9b; display: flex; align-items: center; gap: 6px;">
                    <span>🇮🇳 INR Wallet (₹)</span>
                </button>
                <button type="button" id="tabSwitchConvert" style="padding: 7px 16px; border-radius: 999px; font-size: 12px; font-weight: 800; cursor: pointer; border: none; transition: 0.2s; background: transparent; color: #8b8f9b; display: flex; align-items: center; gap: 6px;">
                    <span>⇄ Transfer / Convert</span>
                </button>
            </div>
        </div>

        <!-- Section 1: USDT Wallet Content -->
        <div id="sectionRevenueUsdt">
            <div class="modal-stats-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 15px; border-radius: 14px; text-align: left;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #8b8f9b; font-weight: bold; letter-spacing: 0.05em; display: block;">Total Revenue</span>
                    <h4 style="font-size: 22px; font-weight: 900; color: #fff; margin: 6px 0 2px 0; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_total_revenue'] ?? 0), 2) }}</h4>
                    <small style="font-size: 10px; color: #64748b; display: block;">All trading & platform fees</small>
                </div>
                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 15px; border-radius: 14px; text-align: left;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #8b8f9b; font-weight: bold; letter-spacing: 0.05em; display: block;">Total Withdrawn</span>
                    <h4 style="font-size: 22px; font-weight: 900; color: #fff; margin: 6px 0 2px 0; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_total_withdrawn'] ?? 0), 2) }}</h4>
                    <small style="font-size: 10px; color: #64748b; display: block;">Transferred to admin wallets</small>
                </div>
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); padding: 15px; border-radius: 14px; text-align: left;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #10b981; font-weight: bold; letter-spacing: 0.05em; display: block;">Available Balance</span>
                    <h4 style="font-size: 22px; font-weight: 900; color: #10b981; margin: 6px 0 2px 0; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_platform_revenue'] ?? 0), 2) }}</h4>
                    <small style="font-size: 10px; color: #10b981; opacity: 0.8; display: block;">Available for withdrawal</small>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 30px;">
                <!-- Left: Request Form -->
                <div style="text-align: left;">
                    <h4 style="font-size: 14px; font-weight: 800; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 8px; margin: 0 0 15px 0;">Request Withdrawal (USDT)</h4>
                    <form method="POST" action="{{ route('admin.wallet.revenue.withdraw') }}">
                        @csrf
                        <input type="hidden" name="currency" value="USD">

                        <label style="display: block; margin-bottom: 15px; color: #8b8f9b; font-size: 12px; font-weight: bold;">
                            Select Network
                            <select name="network" required style="width: 100%; min-height: 40px; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; background: rgba(0,0,0,0.3); color: #fff; padding: 0 12px; outline: none; margin-top: 5px; font-size: 13px; cursor: pointer;">
                                <option value="USDT_TRC20">USDT - TRC20</option>
                                <option value="USDT_ERC20">USDT - ERC20</option>
                                <option value="USDT_BEP20">USDT - BEP20</option>
                            </select>
                        </label>

                        <label style="display: block; margin-bottom: 15px; color: #8b8f9b; font-size: 12px; font-weight: bold;">
                            Wallet Address
                            <input type="text" name="address" placeholder="Enter recipient wallet address" required style="width: 100%; min-height: 40px; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; background: rgba(0,0,0,0.3); color: #fff; padding: 0 12px; outline: none; margin-top: 5px; font-size: 13px;">
                        </label>

                        <div style="display: block; margin-bottom: 20px; position: relative;">
                            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                <span style="color: #8b8f9b; font-size: 12px; font-weight: bold;">Withdraw Amount ($)</span>
                                <span id="btnUseMaxUsdt" style="color: #3b82f6; cursor: pointer; font-size: 11px; font-weight: bold;">Use Max</span>
                            </div>
                            <input type="number" step="0.01" name="amount" id="inputUsdtAmount" min="0.01" max="{{ $stats['usdt_platform_revenue'] ?? 0 }}" placeholder="0.00" required style="width: 100%; min-height: 40px; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; background: rgba(0,0,0,0.3); color: #fff; padding: 0 12px; outline: none; margin-top: 5px; font-size: 13px; font-family: 'JetBrains Mono', monospace;">
                        </div>

                        <button type="submit" class="primary-btn" style="width: 100%; min-height: 44px; background: linear-gradient(135deg, #10b981, #3b82f6); border-radius: 12px; color: #fff !important; font-weight: bold; font-size: 14px; border: none; cursor: pointer; transition: 0.2s;">
                            Withdraw Revenue (USDT)
                        </button>
                    </form>
                </div>

                <!-- Right: Recent Withdrawals -->
                <div style="text-align: left;">
                    <h4 style="font-size: 14px; font-weight: 800; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 8px; margin: 0 0 15px 0;">Recent USDT Withdrawals</h4>
                    <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                            <thead>
                                <tr style="text-align: left; color: #64748b; border-bottom: 1px solid rgba(255,255,255,0.04);">
                                    <th style="padding-bottom: 8px;">Date</th>
                                    <th style="padding-bottom: 8px;">Network</th>
                                    <th style="padding-bottom: 8px;">Recipient</th>
                                    <th style="padding-bottom: 8px; text-align: right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($revenueWithdrawals->where('is_inr', false) as $revWd)
                                    @php
                                        $net = str_replace('USDT_', '', $revWd->network ?? 'TRC20');
                                        $address = '-';
                                        if (preg_match('/(address|account\/UPI)\s+([A-Za-z0-9]+)/', $revWd->description ?? '', $m)) {
                                            $address = strlen($m[2]) > 12 ? substr($m[2], 0, 6) . '...' . substr($m[2], -4) : $m[2];
                                        }
                                    @endphp
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); color: #e2e8f0;">
                                        <td style="padding: 10px 0;">{{ isset($revWd->created_at) ? \Carbon\Carbon::parse($revWd->created_at)->format('j/n/Y, g:i A') : '-' }}</td>
                                        <td style="padding: 10px 0;"><span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;">{{ $net }}</span></td>
                                        <td style="padding: 10px 0; font-family: 'JetBrains Mono', monospace;">{{ $address }}</td>
                                        <td style="padding: 10px 0; text-align: right; color: #ef4444; font-weight: bold; font-family: 'JetBrains Mono', monospace;">${{ number_format(abs((float)($revWd->amount ?? 0)), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #64748b; padding: 30px 0;">No USDT revenue withdrawals found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: INR Wallet Content -->
        <div id="sectionRevenueInr" style="display: none;">
            <div class="modal-stats-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 15px; border-radius: 14px; text-align: left;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #8b8f9b; font-weight: bold; letter-spacing: 0.05em; display: block;">Total Revenue</span>
                    <h4 style="font-size: 22px; font-weight: 900; color: #f59e0b; margin: 6px 0 2px 0; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_total_revenue'] ?? 0), 2) }}</h4>
                    <small style="font-size: 10px; color: #64748b; display: block;">All INR fees & charges</small>
                </div>
                <div style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 15px; border-radius: 14px; text-align: left;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #8b8f9b; font-weight: bold; letter-spacing: 0.05em; display: block;">Total Withdrawn</span>
                    <h4 style="font-size: 22px; font-weight: 900; color: #fff; margin: 6px 0 2px 0; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_total_withdrawn'] ?? 0), 2) }}</h4>
                    <small style="font-size: 10px; color: #64748b; display: block;">Transferred to admin accounts</small>
                </div>
                <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); padding: 15px; border-radius: 14px; text-align: left;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #f59e0b; font-weight: bold; letter-spacing: 0.05em; display: block;">Available Balance</span>
                    <h4 style="font-size: 22px; font-weight: 900; color: #f59e0b; margin: 6px 0 2px 0; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_platform_revenue'] ?? 0), 2) }}</h4>
                    <small style="font-size: 10px; color: #f59e0b; opacity: 0.8; display: block;">Available for withdrawal</small>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 30px;">
                <!-- Left: Request Form -->
                <div style="text-align: left;">
                    <h4 style="font-size: 14px; font-weight: 800; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 8px; margin: 0 0 15px 0;">Request Withdrawal (INR)</h4>
                    <form method="POST" action="{{ route('admin.wallet.revenue.withdraw') }}">
                        @csrf
                        <input type="hidden" name="currency" value="INR">

                        <label style="display: block; margin-bottom: 15px; color: #8b8f9b; font-size: 12px; font-weight: bold;">
                            Payout Method
                            <select name="network" required style="width: 100%; min-height: 40px; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; background: rgba(0,0,0,0.3); color: #fff; padding: 0 12px; outline: none; margin-top: 5px; font-size: 13px; cursor: pointer;">
                                <option value="INR_BANK">INR Bank Transfer</option>
                                <option value="UPI">UPI Transfer</option>
                            </select>
                        </label>

                        <label style="display: block; margin-bottom: 15px; color: #8b8f9b; font-size: 12px; font-weight: bold;">
                            Bank Account / UPI ID
                            <input type="text" name="address" placeholder="Enter Bank Account Number or UPI ID" required style="width: 100%; min-height: 40px; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; background: rgba(0,0,0,0.3); color: #fff; padding: 0 12px; outline: none; margin-top: 5px; font-size: 13px;">
                        </label>

                        <div style="display: block; margin-bottom: 20px; position: relative;">
                            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                <span style="color: #8b8f9b; font-size: 12px; font-weight: bold;">Withdraw Amount (₹)</span>
                                <span id="btnUseMaxInr" style="color: #f59e0b; cursor: pointer; font-size: 11px; font-weight: bold;">Use Max</span>
                            </div>
                            <input type="number" step="0.01" name="amount" id="inputInrAmount" min="1" max="{{ $stats['inr_platform_revenue'] ?? 0 }}" placeholder="0.00" required style="width: 100%; min-height: 40px; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; background: rgba(0,0,0,0.3); color: #fff; padding: 0 12px; outline: none; margin-top: 5px; font-size: 13px; font-family: 'JetBrains Mono', monospace;">
                        </div>

                        <button type="submit" class="primary-btn" style="width: 100%; min-height: 44px; background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 12px; color: #fff !important; font-weight: bold; font-size: 14px; border: none; cursor: pointer; transition: 0.2s;">
                            Withdraw Revenue (INR)
                        </button>
                    </form>
                </div>

                <!-- Right: Recent Withdrawals -->
                <div style="text-align: left;">
                    <h4 style="font-size: 14px; font-weight: 800; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 8px; margin: 0 0 15px 0;">Recent INR Withdrawals</h4>
                    <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                            <thead>
                                <tr style="text-align: left; color: #64748b; border-bottom: 1px solid rgba(255,255,255,0.04);">
                                    <th style="padding-bottom: 8px;">Date</th>
                                    <th style="padding-bottom: 8px;">Method</th>
                                    <th style="padding-bottom: 8px;">Recipient</th>
                                    <th style="padding-bottom: 8px; text-align: right;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($revenueWithdrawals->where('is_inr', true) as $revWd)
                                    @php
                                        $net = $revWd->network ?? 'INR';
                                        $address = '-';
                                        if (preg_match('/(address|account\/UPI)\s+([A-Za-z0-9@._]+)/', $revWd->description ?? '', $m)) {
                                            $address = strlen($m[2]) > 12 ? substr($m[2], 0, 6) . '...' . substr($m[2], -4) : $m[2];
                                        }
                                    @endphp
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); color: #e2e8f0;">
                                        <td style="padding: 10px 0;">{{ isset($revWd->created_at) ? \Carbon\Carbon::parse($revWd->created_at)->format('j/n/Y, g:i A') : '-' }}</td>
                                        <td style="padding: 10px 0;"><span style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;">{{ $net }}</span></td>
                                        <td style="padding: 10px 0; font-family: 'JetBrains Mono', monospace;">{{ $address }}</td>
                                        <td style="padding: 10px 0; text-align: right; color: #ef4444; font-weight: bold; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($revWd->display_amount ?? abs((float)($revWd->amount ?? 0))), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="text-align: center; color: #64748b; padding: 30px 0;">No INR revenue withdrawals found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Convert / Transfer Revenue Content -->
        <div id="sectionRevenueConvert" style="display: none;">
            <div class="modal-stats-row" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px;">
                <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); padding: 15px; border-radius: 14px; text-align: left;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #10b981; font-weight: bold; letter-spacing: 0.05em; display: block;">USDT Revenue Balance</span>
                    <h4 style="font-size: 22px; font-weight: 900; color: #10b981; margin: 6px 0 2px 0; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_platform_revenue'] ?? 0), 2) }}</h4>
                    <small style="font-size: 10px; color: #10b981; opacity: 0.8; display: block;">Available USDT</small>
                </div>
                <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.2); padding: 15px; border-radius: 14px; text-align: left;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #f59e0b; font-weight: bold; letter-spacing: 0.05em; display: block;">INR Revenue Balance</span>
                    <h4 style="font-size: 22px; font-weight: 900; color: #f59e0b; margin: 6px 0 2px 0; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_platform_revenue'] ?? 0), 2) }}</h4>
                    <small style="font-size: 10px; color: #f59e0b; opacity: 0.8; display: block;">Available INR</small>
                </div>
                <div style="background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); padding: 15px; border-radius: 14px; text-align: left;">
                    <span style="font-size: 11px; text-transform: uppercase; color: #3b82f6; font-weight: bold; letter-spacing: 0.05em; display: block;">Exchange Rate</span>
                    <h4 style="font-size: 22px; font-weight: 900; color: #3b82f6; margin: 6px 0 2px 0; font-family: 'JetBrains Mono', monospace;">1 USDT = ₹{{ number_format((float)($usdtInrRate ?? 85.0), 2) }}</h4>
                    <small style="font-size: 10px; color: #3b82f6; opacity: 0.8; display: block;">Admin Setting Rate</small>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1.15fr 1fr; gap: 30px;">
                <!-- Left: Transfer Form -->
                <div style="text-align: left;">
                    <h4 style="font-size: 14px; font-weight: 800; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 8px; margin: 0 0 15px 0;">Transfer & Convert Revenue</h4>
                    <form method="POST" action="{{ route('admin.wallet.revenue.transfer') }}" id="formConvertRevenue">
                        @csrf

                        <label style="display: block; margin-bottom: 15px; color: #8b8f9b; font-size: 12px; font-weight: bold;">
                            Transfer Direction
                            <select name="direction" id="selectConvertDirection" required style="width: 100%; min-height: 40px; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; background: rgba(0,0,0,0.3); color: #fff; padding: 0 12px; outline: none; margin-top: 5px; font-size: 13px; cursor: pointer;">
                                <option value="usdt_to_inr">USDT Revenue ➔ INR Revenue</option>
                                <option value="inr_to_usdt">INR Revenue ➔ USDT Revenue</option>
                            </select>
                        </label>

                        <div style="display: block; margin-bottom: 15px; position: relative;">
                            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                <span id="labelConvertAmount" style="color: #8b8f9b; font-size: 12px; font-weight: bold;">Amount to Convert ($ USDT)</span>
                                <span id="btnUseMaxConvert" style="color: #3b82f6; cursor: pointer; font-size: 11px; font-weight: bold;">Use Max</span>
                            </div>
                            <input type="number" step="0.01" name="amount" id="inputConvertAmount" min="0.01" placeholder="0.00" required style="width: 100%; min-height: 40px; border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; background: rgba(0,0,0,0.3); color: #fff; padding: 0 12px; outline: none; margin-top: 5px; font-size: 13px; font-family: 'JetBrains Mono', monospace;">
                        </div>

                        <!-- Live Conversion Result Box -->
                        <div id="convertPreviewBox" style="background: rgba(59, 130, 246, 0.1); border: 1px dashed rgba(59, 130, 246, 0.3); border-radius: 12px; padding: 12px 15px; margin-bottom: 20px;">
                            <span style="font-size: 11px; color: #8b8f9b; display: block; font-weight: bold;">Live Conversion Preview</span>
                            <div style="font-size: 15px; font-weight: 800; color: #3b82f6; font-family: 'JetBrains Mono', monospace; margin-top: 4px;" id="convertResultText">
                                Enter amount to preview output...
                            </div>
                        </div>

                        <button type="submit" class="primary-btn" id="btnSubmitConvert" style="width: 100%; min-height: 44px; background: linear-gradient(135deg, #3b82f6, #6366f1); border-radius: 12px; color: #fff !important; font-weight: bold; font-size: 14px; border: none; cursor: pointer; transition: 0.2s;">
                            Transfer & Convert Revenue
                        </button>
                    </form>
                </div>

                <!-- Right: Recent Conversions / Transfers -->
                <div style="text-align: left;">
                    <h4 style="font-size: 14px; font-weight: 800; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 8px; margin: 0 0 15px 0;">Recent Revenue Conversions</h4>
                    <div style="max-height: 250px; overflow-y: auto; padding-right: 5px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                            <thead>
                                <tr style="text-align: left; color: #64748b; border-bottom: 1px solid rgba(255,255,255,0.04);">
                                    <th style="padding-bottom: 8px;">Date</th>
                                    <th style="padding-bottom: 8px;">Direction</th>
                                    <th style="padding-bottom: 8px; text-align: right;">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($revenueTransfers as $revTrf)
                                    @php
                                        $type = $revTrf->type ?? '';
                                        $isUsdtOut = $type === 'admin_transfer_usdt_out';
                                        $isInrOut = $type === 'admin_transfer_inr_out';
                                    @endphp
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.02); color: #e2e8f0;">
                                        <td style="padding: 10px 0;">{{ isset($revTrf->created_at) ? \Carbon\Carbon::parse($revTrf->created_at)->format('j/n/Y, g:i A') : '-' }}</td>
                                        <td style="padding: 10px 0;">
                                            @if($isUsdtOut)
                                                <span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;">USDT ➔ INR</span>
                                            @elseif($isInrOut)
                                                <span style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;">INR ➔ USDT</span>
                                            @else
                                                <span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: bold;">Conversion</span>
                                            @endif
                                        </td>
                                        <td style="padding: 10px 0; text-align: right; color: #e2e8f0; font-size: 11px; font-family: 'JetBrains Mono', monospace;">{{ $revTrf->description ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" style="text-align: center; color: #64748b; padding: 30px 0;">No revenue conversions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const revModal = document.getElementById('adminWithdrawRevenueModal');
    const closeBtn = document.getElementById('withdrawRevenueClose');

    const btnUsdtCard = document.getElementById('openWithdrawUsdtRevenueButton');
    const cardUsdt = document.getElementById('usdtPlatformRevenueCard');

    const btnInrCard = document.getElementById('openWithdrawInrRevenueButton');
    const cardInr = document.getElementById('inrPlatformRevenueCard');

    const tabUsdt = document.getElementById('tabSwitchUsdt');
    const tabInr = document.getElementById('tabSwitchInr');
    const tabConvert = document.getElementById('tabSwitchConvert');

    const sectionUsdt = document.getElementById('sectionRevenueUsdt');
    const sectionInr = document.getElementById('sectionRevenueInr');
    const sectionConvert = document.getElementById('sectionRevenueConvert');

    const badge = document.getElementById('revenueModalBadge');

    const usdtInrRate = {{ (float)($usdtInrRate ?? 85.0) }};
    const maxUsdtRevenue = {{ (float)($stats['usdt_platform_revenue'] ?? 0) }};
    const maxInrRevenue = {{ (float)($stats['inr_platform_revenue'] ?? 0) }};

    const switchTab = (mode) => {
        if (mode === 'CONVERT') {
            if (tabConvert) {
                tabConvert.style.background = '#3b82f6';
                tabConvert.style.color = '#fff';
            }
            if (tabUsdt) {
                tabUsdt.style.background = 'transparent';
                tabUsdt.style.color = '#8b8f9b';
            }
            if (tabInr) {
                tabInr.style.background = 'transparent';
                tabInr.style.color = '#8b8f9b';
            }

            if (sectionConvert) sectionConvert.style.display = 'block';
            if (sectionUsdt) sectionUsdt.style.display = 'none';
            if (sectionInr) sectionInr.style.display = 'none';

            if (badge) {
                badge.innerText = 'Transfer / Convert';
                badge.style.background = 'rgba(59, 130, 246, 0.15)';
                badge.style.color = '#3b82f6';
                badge.style.borderColor = 'rgba(59, 130, 246, 0.3)';
            }
        } else if (mode === 'INR') {
            if (tabInr) {
                tabInr.style.background = '#f59e0b';
                tabInr.style.color = '#fff';
            }
            if (tabUsdt) {
                tabUsdt.style.background = 'transparent';
                tabUsdt.style.color = '#8b8f9b';
            }
            if (tabConvert) {
                tabConvert.style.background = 'transparent';
                tabConvert.style.color = '#8b8f9b';
            }

            if (sectionInr) sectionInr.style.display = 'block';
            if (sectionUsdt) sectionUsdt.style.display = 'none';
            if (sectionConvert) sectionConvert.style.display = 'none';

            if (badge) {
                badge.innerText = 'INR Wallet (₹)';
                badge.style.background = 'rgba(245, 158, 11, 0.15)';
                badge.style.color = '#f59e0b';
                badge.style.borderColor = 'rgba(245, 158, 11, 0.3)';
            }
        } else {
            if (tabUsdt) {
                tabUsdt.style.background = '#10b981';
                tabUsdt.style.color = '#fff';
            }
            if (tabInr) {
                tabInr.style.background = 'transparent';
                tabInr.style.color = '#8b8f9b';
            }
            if (tabConvert) {
                tabConvert.style.background = 'transparent';
                tabConvert.style.color = '#8b8f9b';
            }

            if (sectionUsdt) sectionUsdt.style.display = 'block';
            if (sectionInr) sectionInr.style.display = 'none';
            if (sectionConvert) sectionConvert.style.display = 'none';

            if (badge) {
                badge.innerText = 'USDT Wallet ($)';
                badge.style.background = 'rgba(16, 185, 129, 0.15)';
                badge.style.color = '#10b981';
                badge.style.borderColor = 'rgba(16, 185, 129, 0.3)';
            }
        }
    };

    const showModal = (mode = 'USDT') => {
        switchTab(mode);
        if (revModal) {
            revModal.style.display = 'flex';
            revModal.classList.add('is-open');
            revModal.setAttribute('aria-hidden', 'false');
        }
    };

    const hideModal = () => {
        if (revModal) {
            revModal.style.display = 'none';
            revModal.classList.remove('is-open');
            revModal.setAttribute('aria-hidden', 'true');
        }
    };

    if (revModal) {
        if (btnUsdtCard) {
            btnUsdtCard.addEventListener('click', (e) => {
                e.stopPropagation();
                showModal('USDT');
            });
        }

        if (cardUsdt) {
            cardUsdt.addEventListener('click', (e) => {
                if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A' && !e.target.closest('button')) {
                    showModal('USDT');
                }
            });
        }

        if (btnInrCard) {
            btnInrCard.addEventListener('click', (e) => {
                e.stopPropagation();
                showModal('INR');
            });
        }

        if (cardInr) {
            cardInr.addEventListener('click', (e) => {
                if (e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A' && !e.target.closest('button')) {
                    showModal('INR');
                }
            });
        }

        if (tabUsdt) tabUsdt.addEventListener('click', () => switchTab('USDT'));
        if (tabInr) tabInr.addEventListener('click', () => switchTab('INR'));
        if (tabConvert) tabConvert.addEventListener('click', () => switchTab('CONVERT'));

        if (closeBtn) closeBtn.addEventListener('click', hideModal);

        revModal.addEventListener('click', (event) => {
            if (event.target === revModal) hideModal();
        });

        // Use Max Buttons for Withdrawal Forms
        const btnUseMaxUsdt = document.getElementById('btnUseMaxUsdt');
        const inputUsdtAmount = document.getElementById('inputUsdtAmount');
        if (btnUseMaxUsdt && inputUsdtAmount) {
            btnUseMaxUsdt.addEventListener('click', () => {
                inputUsdtAmount.value = "{{ $stats['usdt_platform_revenue'] ?? 0 }}";
            });
        }

        const btnUseMaxInr = document.getElementById('btnUseMaxInr');
        const inputInrAmount = document.getElementById('inputInrAmount');
        if (btnUseMaxInr && inputInrAmount) {
            btnUseMaxInr.addEventListener('click', () => {
                inputInrAmount.value = "{{ $stats['inr_platform_revenue'] ?? 0 }}";
            });
        }

        // Live Conversion Calculation logic
        const selectDirection = document.getElementById('selectConvertDirection');
        const inputConvertAmount = document.getElementById('inputConvertAmount');
        const labelConvertAmount = document.getElementById('labelConvertAmount');
        const convertResultText = document.getElementById('convertResultText');
        const btnUseMaxConvert = document.getElementById('btnUseMaxConvert');

        const updateConvertPreview = () => {
            if (!selectDirection || !inputConvertAmount || !convertResultText) return;

            const dir = selectDirection.value;
            const amt = parseFloat(inputConvertAmount.value) || 0;

            if (dir === 'usdt_to_inr') {
                if (labelConvertAmount) labelConvertAmount.textContent = 'Amount to Convert ($ USDT)';
                inputConvertAmount.setAttribute('max', maxUsdtRevenue);

                if (amt <= 0) {
                    convertResultText.textContent = 'Enter amount to preview output...';
                } else {
                    const outputInr = (amt * usdtInrRate).toFixed(2);
                    convertResultText.textContent = `You enter: $${amt.toFixed(2)} USDT ➔ You receive: ₹${Number(outputInr).toLocaleString('en-IN', {minimumFractionDigits: 2})} INR`;
                }
            } else {
                if (labelConvertAmount) labelConvertAmount.textContent = 'Amount to Convert (₹ INR)';
                inputConvertAmount.setAttribute('max', maxInrRevenue);

                if (amt <= 0) {
                    convertResultText.textContent = 'Enter amount to preview output...';
                } else {
                    const outputUsdt = (amt / usdtInrRate).toFixed(4);
                    convertResultText.textContent = `You enter: ₹${amt.toLocaleString('en-IN', {minimumFractionDigits: 2})} INR ➔ You receive: $${Number(outputUsdt).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 4})} USDT`;
                }
            }
        };

        if (selectDirection) selectDirection.addEventListener('change', updateConvertPreview);
        if (inputConvertAmount) inputConvertAmount.addEventListener('input', updateConvertPreview);

        if (btnUseMaxConvert && inputConvertAmount && selectDirection) {
            btnUseMaxConvert.addEventListener('click', () => {
                const dir = selectDirection.value;
                inputConvertAmount.value = dir === 'usdt_to_inr' ? maxUsdtRevenue : maxInrRevenue;
                updateConvertPreview();
            });
        }
    }
});
</script>
@endsection
