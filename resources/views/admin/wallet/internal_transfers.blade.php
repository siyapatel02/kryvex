@extends('admin.layouts.app')

@section('title', 'Internal Transfers')

@section('content')
<div class="wallet-page">
    <div class="wallet-hero withdraw-hero" style="display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div>
            <span class="wallet-kicker">Admin Wallet</span>
            <h1>User-to-User Internal Transfers</h1>
            <p>Monitor real-time zero-fee internal fund transfers between exchange users.</p>
        </div>

        <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <!-- Total Transfers Stat Card -->
            <div style="background: rgba(168, 85, 247, 0.08); border: 1px solid rgba(168, 85, 247, 0.25); border-radius: 14px; padding: 14px 22px; text-align: left; min-width: 200px; backdrop-filter: blur(8px);">
                <span style="font-size: 11px; text-transform: uppercase; color: #c084fc; font-weight: 700; letter-spacing: 0.05em; display: block;">⚡ Internal Volume</span>
                <strong style="font-size: 22px; color: #c084fc; font-weight: 900; font-family: 'JetBrains Mono', monospace; margin-top: 3px; display: block;">{{ number_format((float)$totalCount) }}</strong>
                <small style="font-size: 11px; color: #8b8f9b; display: block; margin-top: 2px;">Total transfers completed</small>
            </div>
        </div>
    </div>

    <div class="wallet-tabs">
        <a href="{{ route('admin.wallet.index') }}" class="wallet-tab">Overview</a>
        <a href="{{ route('admin.wallet.deposits') }}" class="wallet-tab">Deposits</a>
        <a href="{{ route('admin.wallet.withdrawals') }}" class="wallet-tab">Withdrawals</a>
        <a href="{{ route('admin.wallet.internal_transfers') }}" class="wallet-tab active">Internal Transfers</a>
        <a href="{{ route('admin.wallet.ledger') }}" class="wallet-tab">Ledger</a>
    </div>

    <!-- FILTER FORM -->
    <form class="wallet-filter-card compact-search" method="GET" action="{{ route('admin.wallet.internal_transfers') }}" style="display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap; margin-bottom: 24px;">
        <div class="filter-group search-wide" style="flex: 1; min-width: 260px;">
            <label>Search Transfers</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by Sender, Recipient, Address or Reference..." style="width: 100%; height: 42px; background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; color: #fff; padding: 0 14px; font-size: 0.88rem;">
        </div>

        <div class="filter-group" style="min-width: 160px;">
            <label>Currency</label>
            <select name="currency" onchange="this.form.submit()" style="width: 100%; height: 42px; background: rgba(0, 0, 0, 0.5); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; color: #fff; padding: 0 14px; font-size: 0.88rem; font-weight: 600; cursor: pointer;">
                <option value="ALL" {{ $currency === 'ALL' || empty($currency) ? 'selected' : '' }}>ALL Currencies</option>
                <option value="USD" {{ $currency === 'USD' ? 'selected' : '' }}>USD</option>
                <option value="INR" {{ $currency === 'INR' ? 'selected' : '' }}>INR</option>
                <option value="BTC" {{ $currency === 'BTC' ? 'selected' : '' }}>BTC</option>
                <option value="ETH" {{ $currency === 'ETH' ? 'selected' : '' }}>ETH</option>
                <option value="USDC" {{ $currency === 'USDC' ? 'selected' : '' }}>USDC</option>
                <option value="BNB" {{ $currency === 'BNB' ? 'selected' : '' }}>BNB</option>
                <option value="SOL" {{ $currency === 'SOL' ? 'selected' : '' }}>SOL</option>
                <option value="XRP" {{ $currency === 'XRP' ? 'selected' : '' }}>XRP</option>
                <option value="ADA" {{ $currency === 'ADA' ? 'selected' : '' }}>ADA</option>
            </select>
        </div>

        <button type="submit" class="filter-btn" style="min-height: 42px; padding: 0 22px; border-radius: 10px; font-weight: 700; background: #a855f7; border: none; color: #fff; cursor: pointer;">Filter</button>

        @if(!empty($search) || (!empty($currency) && $currency !== 'ALL'))
            <a href="{{ route('admin.wallet.internal_transfers') }}" class="reset-btn" style="min-height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 16px; color: rgba(255, 255, 255, 0.5); text-decoration: underline;">Reset</a>
        @endif
    </form>

    <!-- INTERNAL TRANSFERS POLISHED TABLE -->
    <div class="wallet-table-card polished-table">
        <div class="table-header" style="padding: 18px 24px; border-bottom: 1px solid rgba(255, 255, 255, 0.08); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #fff;">Internal Transfer Records</h2>
                <p style="margin: 4px 0 0 0; font-size: 0.8rem; color: rgba(255, 255, 255, 0.45);">Showing instant user-to-user transfers within Kryvex exchange.</p>
            </div>
            <span style="font-size: 0.82rem; color: rgba(255, 255, 255, 0.5); font-weight: 600;">{{ $transfers->total() }} records • Page {{ $transfers->currentPage() }} / {{ $transfers->lastPage() }}</span>
        </div>

        <div class="table-responsive" style="overflow-x: auto;">
            <table class="admin-table" style="width: 100%; min-width: 1100px; border-collapse: separate; border-spacing: 0;">
                <thead>
                    <tr>
                        <th style="padding: 14px 18px; font-size: 0.78rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.45); font-weight: 700; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: left;">Sender User</th>
                        <th style="padding: 14px 18px; font-size: 0.78rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.45); font-weight: 700; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: left;">Recipient User</th>
                        <th style="padding: 14px 18px; font-size: 0.78rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.45); font-weight: 700; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: left;">Recipient Address</th>
                        <th style="padding: 14px 18px; font-size: 0.78rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.45); font-weight: 700; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: left;">Amount & Currency</th>
                        <th style="padding: 14px 18px; font-size: 0.78rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.45); font-weight: 700; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: left;">Reference Code</th>
                        <th style="padding: 14px 18px; font-size: 0.78rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.45); font-weight: 700; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: left;">Date & Time</th>
                        <th style="padding: 14px 18px; font-size: 0.78rem; text-transform: uppercase; color: rgba(255, 255, 255, 0.45); font-weight: 700; letter-spacing: 0.05em; border-bottom: 1px solid rgba(255, 255, 255, 0.08); text-align: center;">Status</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($transfers as $item)
                        <tr style="border-bottom: 1px solid rgba(255, 255, 255, 0.04);">
                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <strong style="display: block; color: #fff; font-size: 0.88rem;">{{ $item->sender_name ?? 'Unknown' }}</strong>
                                <small style="display: block; color: rgba(255, 255, 255, 0.45); font-size: 0.76rem; margin-top: 2px;">{{ $item->sender_email ?? '-' }}</small>
                            </td>

                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <strong style="display: block; color: #fff; font-size: 0.88rem;">{{ $item->recipient_name ?? 'Unknown' }}</strong>
                                <small style="display: block; color: rgba(255, 255, 255, 0.45); font-size: 0.76rem; margin-top: 2px;">{{ $item->recipient_email ?? '-' }}</small>
                            </td>

                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <code style="background: rgba(0, 0, 0, 0.45); border: 1px solid rgba(168, 85, 247, 0.3); padding: 4px 10px; border-radius: 6px; color: #c084fc; font-family: 'JetBrains Mono', monospace; font-size: 0.78rem;">{{ $item->recipient_address ?? '-' }}</code>
                            </td>

                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <strong style="color: #22c55e; font-size: 0.95rem; font-family: 'JetBrains Mono', monospace;">
                                    +{{ number_format((float)($item->amount ?? 0), 4) }} {{ $item->currency ?? 'USD' }}
                                </strong>
                            </td>

                            <td style="padding: 14px 18px; vertical-align: middle;">
                                <small style="font-family: monospace; color: rgba(255, 255, 255, 0.7); font-size: 0.82rem;">{{ $item->reference ?? '-' }}</small>
                            </td>

                            <td style="padding: 14px 18px; vertical-align: middle; color: rgba(255, 255, 255, 0.7); font-size: 0.83rem;">
                                {{ isset($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('d M Y, h:i A') : '-' }}
                            </td>

                            <td style="padding: 14px 18px; vertical-align: middle; text-align: center;">
                                <span style="background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 0.76rem; display: inline-block;">
                                    Instant
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: rgba(255, 255, 255, 0.4);">
                                <div class="empty-state">No internal transfer records found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.components.pagination', ['paginator' => $transfers])
</div>
@endsection
