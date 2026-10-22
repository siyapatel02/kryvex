@extends('admin.layouts.app')

@section('title', 'Deposits')

@section('content')
<div class="wallet-page">
    <div class="wallet-hero deposit-hero" style="display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div>
            <span class="wallet-kicker">Admin Deposits</span>
            <h1>Deposits</h1>
            <p>Instant wallet credits with a cleaner history and audit-focused deposit table.</p>
        </div>
        
        <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <!-- INR Card -->
            <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 14px; padding: 14px 20px; text-align: left; min-width: 170px; backdrop-filter: blur(8px);">
                <span style="font-size: 11px; text-transform: uppercase; color: #f59e0b; font-weight: 700; letter-spacing: 0.05em; display: block;">🇮🇳 INR Deposited</span>
                <strong style="font-size: 20px; color: #f59e0b; font-weight: 900; font-family: 'JetBrains Mono', monospace; margin-top: 3px; display: block;">₹{{ number_format((float)($stats['inr_total_deposit_amount'] ?? 0), 2) }}</strong>
                <small style="font-size: 11px; color: #8b8f9b; display: block; margin-top: 3px;">{{ number_format((int)($stats['inr_total_deposit_count'] ?? 0)) }} records</small>
            </div>

            <!-- USDT Card -->
            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 14px; padding: 14px 20px; text-align: left; min-width: 170px; backdrop-filter: blur(8px);">
                <span style="font-size: 11px; text-transform: uppercase; color: #10b981; font-weight: 700; letter-spacing: 0.05em; display: block;">🇺🇸 USDT Deposited</span>
                <strong style="font-size: 20px; color: #10b981; font-weight: 900; font-family: 'JetBrains Mono', monospace; margin-top: 3px; display: block;">${{ number_format((float)($stats['usdt_total_deposit_amount'] ?? 0), 2) }}</strong>
                <small style="font-size: 11px; color: #8b8f9b; display: block; margin-top: 3px;">{{ number_format((int)($stats['usdt_total_deposit_count'] ?? 0)) }} records</small>
            </div>
        </div>
    </div>

    <div class="wallet-tabs">
        <a href="{{ route('admin.wallet.index') }}" class="wallet-tab">Overview</a>
        <a href="{{ route('admin.wallet.deposits') }}" class="wallet-tab active">Deposits</a>
        <a href="{{ route('admin.wallet.withdrawals') }}" class="wallet-tab">Withdrawals</a>
        <a href="{{ route('admin.wallet.internal_transfers') }}" class="wallet-tab">Internal Transfers</a>
        <a href="{{ route('admin.wallet.ledger') }}" class="wallet-tab">Ledger</a>
    </div>

    <form class="wallet-filter-card compact-search" method="GET" style="display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;">
        <div class="filter-group search-wide" style="flex: 1; min-width: 250px;">
            <label>Search deposits</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search user, email, type or amount...">
        </div>

        <div class="filter-group" style="min-width: 160px;">
            <label>Currency</label>
            <select name="currency" onchange="this.form.submit()" style="width: 100%; min-height: 42px; border-radius: 10px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); color: #fff; padding: 0 12px; outline: none; font-size: 13px; font-weight: 600; cursor: pointer;">
                <option value="ALL" {{ ($currency ?? 'ALL') === 'ALL' ? 'selected' : '' }}>ALL Currencies</option>
                <option value="INR" {{ ($currency ?? '') === 'INR' ? 'selected' : '' }}>🇮🇳 INR Deposits</option>
                <option value="USDT" {{ ($currency ?? '') === 'USDT' ? 'selected' : '' }}>🇺🇸 USDT Deposits</option>
            </select>
        </div>

        <button type="submit" class="filter-btn" style="min-height: 42px; padding: 0 20px; border-radius: 10px; font-weight: 700;">Filter</button>

        @if($search !== '' || ($currency ?? 'ALL') !== 'ALL')
            <a href="{{ route('admin.wallet.deposits') }}" class="reset-btn" style="min-height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 16px;">Reset</a>
        @endif
    </form>

    <div class="wallet-table-card polished-table">
        <div class="table-header">
            <div>
                <h2>Deposit Records</h2>
                <p>Showing credits only — no direction, status, reference or note columns.</p>
            </div>
            <span>{{ $deposits->total() }} records • Page {{ $deposits->currentPage() }} / {{ $deposits->lastPage() }}</span>
        </div>

        <div class="table-responsive">
            <table class="admin-table compact-table deposit-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Type</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($deposits as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->display_user_name ?? 'Unknown User' }}</strong>
                                <small>{{ $item->display_user_email ?? '-' }}</small>
                            </td>

                            <td class="amount positive">+{{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)($item->display_amount ?? $item->amount ?? 0), 2) }}</td>

                            <td>{{ ucfirst(str_replace('_', ' ', $item->type ?? 'Deposit')) }}</td>

                            <td>{{ isset($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('d M Y, h:i A') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">No deposit records found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.components.pagination', ['paginator' => $deposits])
</div>
@endsection
