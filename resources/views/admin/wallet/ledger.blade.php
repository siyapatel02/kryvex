@extends('admin.layouts.app')

@section('title', 'Platform Revenue Ledger')

@section('content')
<div class="wallet-page">
    <div class="wallet-hero ledger-hero" style="display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div>
            <span class="wallet-kicker">Admin Revenue Ledger</span>
            <h1>Revenue Ledger</h1>
            <p>Monitor platform fee streams, trading fees, withdrawal charges, and admin revenue payouts.</p>
        </div>
        
        <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <!-- INR Revenue -->
            <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 14px; padding: 12px 18px; text-align: left; min-width: 160px; backdrop-filter: blur(8px);">
                <span style="font-size: 11px; text-transform: uppercase; color: #f59e0b; font-weight: 700; letter-spacing: 0.05em; display: block;">🇮🇳 INR Revenue</span>
                <strong style="font-size: 18px; color: #f59e0b; font-weight: 900; font-family: 'JetBrains Mono', monospace; margin-top: 2px; display: block;">₹{{ number_format((float)($stats['inr_platform_revenue'] ?? 0), 2) }}</strong>
            </div>

            <!-- USDT Revenue -->
            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 14px; padding: 12px 18px; text-align: left; min-width: 160px; backdrop-filter: blur(8px);">
                <span style="font-size: 11px; text-transform: uppercase; color: #10b981; font-weight: 700; letter-spacing: 0.05em; display: block;">🇺🇸 USDT Revenue</span>
                <strong style="font-size: 18px; color: #10b981; font-weight: 900; font-family: 'JetBrains Mono', monospace; margin-top: 2px; display: block;">${{ number_format((float)($stats['usdt_platform_revenue'] ?? 0), 2) }}</strong>
            </div>
        </div>
    </div>

    <div class="wallet-tabs">
        <a href="{{ route('admin.wallet.index') }}" class="wallet-tab">Overview</a>
        <a href="{{ route('admin.wallet.deposits') }}" class="wallet-tab">Deposits</a>
        <a href="{{ route('admin.wallet.withdrawals') }}" class="wallet-tab">Withdrawals</a>
        <a href="{{ route('admin.wallet.internal_transfers') }}" class="wallet-tab">Internal Transfers</a>
        <a href="{{ route('admin.wallet.ledger') }}" class="wallet-tab active">Revenue Ledger</a>
    </div>

    <div class="wallet-stats compact-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;">
        <!-- Card 1: Total Revenue -->
        <div class="wallet-card metric-card success-card" style="padding: 16px; min-height: auto;">
            <span style="font-size: 12px; font-weight: 800; color: #fff; display: block; margin-bottom: 10px;">Total Revenue</span>
            <div style="display: flex; flex-direction: column; gap: 6px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; color: #f59e0b; font-weight: 700;">🇮🇳 INR</span>
                    <strong style="font-size: 15px; color: #f59e0b; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_platform_revenue'] ?? 0), 2) }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; color: #10b981; font-weight: 700;">🇺🇸 USDT</span>
                    <strong style="font-size: 15px; color: #10b981; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_platform_revenue'] ?? 0), 2) }}</strong>
                </div>
            </div>
            <p style="font-size: 10px; color: #64748b; margin-top: 8px; margin-bottom: 0;">Net platform income</p>
        </div>

        <!-- Card 2: Trading Fees -->
        <div class="wallet-card metric-card" style="padding: 16px; min-height: auto;">
            <span style="font-size: 12px; font-weight: 800; color: #fff; display: block; margin-bottom: 10px;">Trading Fees</span>
            <div style="display: flex; flex-direction: column; gap: 6px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; color: #f59e0b; font-weight: 700;">🇮🇳 INR</span>
                    <strong style="font-size: 15px; color: #f59e0b; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_trading_fee_income'] ?? 0), 2) }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; color: #3b82f6; font-weight: 700;">🇺🇸 USDT</span>
                    <strong style="font-size: 15px; color: #3b82f6; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_trading_fee_income'] ?? 0), 2) }}</strong>
                </div>
            </div>
            <p style="font-size: 10px; color: #64748b; margin-top: 8px; margin-bottom: 0;">Futures/Options trading fees</p>
        </div>

        <!-- Card 3: Withdrawal Fees -->
        <div class="wallet-card metric-card violet-card" style="padding: 16px; min-height: auto;">
            <span style="font-size: 12px; font-weight: 800; color: #fff; display: block; margin-bottom: 10px;">Withdrawal Fees</span>
            <div style="display: flex; flex-direction: column; gap: 6px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; color: #f59e0b; font-weight: 700;">🇮🇳 INR</span>
                    <strong style="font-size: 15px; color: #f59e0b; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_withdrawal_fee_income'] ?? 0), 2) }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; color: #a855f7; font-weight: 700;">🇺🇸 USDT</span>
                    <strong style="font-size: 15px; color: #a855f7; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_withdrawal_fee_income'] ?? 0), 2) }}</strong>
                </div>
            </div>
            <p style="font-size: 10px; color: #64748b; margin-top: 8px; margin-bottom: 0;">Charges from user payouts</p>
        </div>

        <!-- Card 4: Platform Charges -->
        <div class="wallet-card metric-card success-card" style="padding: 16px; min-height: auto;">
            <span style="font-size: 12px; font-weight: 800; color: #fff; display: block; margin-bottom: 10px;">Platform Charges</span>
            <div style="display: flex; flex-direction: column; gap: 6px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; color: #f59e0b; font-weight: 700;">🇮🇳 INR</span>
                    <strong style="font-size: 15px; color: #f59e0b; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_platform_charge_income'] ?? 0), 2) }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 11px; color: #10b981; font-weight: 700;">🇺🇸 USDT</span>
                    <strong style="font-size: 15px; color: #10b981; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_platform_charge_income'] ?? 0), 2) }}</strong>
                </div>
            </div>
            <p style="font-size: 10px; color: #64748b; margin-top: 8px; margin-bottom: 0;">Additional fixed platform fees</p>
        </div>
    </div>

    <form class="wallet-filter-card ledger-filter" method="GET" style="display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;">
        <div class="filter-group search-wide" style="flex: 1; min-width: 240px;">
            <label>Search revenue</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search user, email, type, reference or amount...">
        </div>

        <div class="filter-group" style="min-width: 150px;">
            <label>Currency</label>
            <select name="currency" onchange="this.form.submit()" style="width: 100%; min-height: 42px; border-radius: 10px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); color: #fff; padding: 0 12px; outline: none; font-size: 13px; font-weight: 600; cursor: pointer;">
                <option value="ALL" {{ ($currency ?? 'ALL') === 'ALL' ? 'selected' : '' }}>ALL Currencies</option>
                <option value="INR" {{ ($currency ?? '') === 'INR' ? 'selected' : '' }}>🇮🇳 INR Revenue</option>
                <option value="USDT" {{ ($currency ?? '') === 'USDT' ? 'selected' : '' }}>🇺🇸 USDT Revenue</option>
            </select>
        </div>

        <div class="filter-group" style="min-width: 150px;">
            <label>Source</label>
            <select name="source" onchange="this.form.submit()">
                <option value="all" {{ $source === 'all' ? 'selected' : '' }}>All Sources</option>
                <option value="trading_fee" {{ $source === 'trading_fee' ? 'selected' : '' }}>Trading Fees</option>
                <option value="withdrawal_fee" {{ $source === 'withdrawal_fee' ? 'selected' : '' }}>Withdrawal Fees</option>
                <option value="platform_charge" {{ $source === 'platform_charge' ? 'selected' : '' }}>Platform Charges</option>
                <option value="admin_withdrawal" {{ $source === 'admin_withdrawal' ? 'selected' : '' }}>Admin Withdrawals</option>
            </select>
        </div>

        <button type="submit" class="filter-btn" style="min-height: 42px; padding: 0 20px; border-radius: 10px; font-weight: 700;">Apply</button>
        <a href="{{ route('admin.wallet.ledger') }}" class="reset-btn" style="min-height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 16px;">Reset</a>
    </form>

    <div class="wallet-table-card polished-table">
        <div class="table-header">
            <div>
                <h2>Revenue Transactions</h2>
                <p>Fee collections and admin payouts ledger records.</p>
            </div>
            <span>{{ $ledger->total() }} records • Page {{ $ledger->currentPage() }} / {{ $ledger->lastPage() }}</span>
        </div>

        <div class="table-responsive">
            <table class="admin-table compact-table ledger-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Source</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($ledger as $item)
                        @php
                            $isDebit = (float)($item->amount ?? 0) < 0;
                            $curSym = $item->display_currency_symbol ?? '$';
                            $displayAmt = $item->display_amount ?? $item->amount ?? 0;
                        @endphp
                        <tr>
                            <td>
                                @if(($item->user_uid ?? '') === 'admin')
                                    <strong>Admin</strong>
                                    <small>Platform Account</small>
                                @else
                                    <strong>{{ $item->display_user_name ?? 'Unknown User' }}</strong>
                                    <small>{{ $item->display_user_email ?? '-' }}</small>
                                @endif
                            </td>

                            <td>
                                <span class="status-badge {{ $isDebit ? 'pending' : 'completed' }}">
                                    {{ ucfirst(str_replace('_', ' ', $item->source ?? '-')) }}
                                </span>
                            </td>

                            <td>{{ ucfirst(str_replace('_', ' ', $item->type ?? '-')) }}</td>

                            <td><strong>{{ $item->reference ?? '-' }}</strong></td>

                            <td class="amount {{ $isDebit ? 'negative' : 'positive' }}">
                                {{ $isDebit ? '-' : '+' }}{{ $curSym }}{{ number_format(abs((float)$displayAmt), 2) }}
                            </td>

                            <td>{{ $item->description ?? '-' }}</td>

                            <td>{{ isset($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('d M Y, h:i A') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">No revenue ledger transactions found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.components.pagination', ['paginator' => $ledger])
</div>
@endsection
