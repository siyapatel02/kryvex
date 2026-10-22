@extends('admin.layouts.app')

@section('title', 'Positions')

@section('content')
<div class="positions-page">

    <div class="admin-market-page-head">
        <div>
            <p class="eyebrow">Admin</p>
            <h1>Open Positions</h1>
            <span>Monitor futures/options positions with live market price and real-time P&L.</span>
        </div>

    </div>

    <div class="admin-market-stats-grid positions-stats-grid">
        <div class="admin-market-stat-card amber">
            <span>Open Positions</span>
            <strong>{{ number_format($positionStats['open'] ?? 0) }}</strong>
            <small>Currently active</small>
        </div>

        <div class="admin-market-stat-card violet">
            <span>Total Margin</span>
            <strong>${{ number_format((float)($positionStats['total_margin'] ?? 0), 2) }}</strong>
            <small>Locked in open positions</small>
        </div>

        <div class="admin-market-stat-card green">
            <span>Unrealized P&L</span>
            <strong class="{{ ($positionStats['unrealized_pnl'] ?? 0) >= 0 ? 'pnl-profit' : 'pnl-loss' }}">
                ${{ number_format((float)($positionStats['unrealized_pnl'] ?? 0), 2) }}
            </strong>
            <small>Live estimate for open positions</small>
        </div>

        <div class="admin-market-stat-card blue">
            <span>Long Positions</span>
            <strong>{{ number_format($positionStats['long'] ?? 0) }}</strong>
            <small>Buy / long / call side</small>
        </div>
    </div>



    <form class="admin-market-filter-strip" method="GET" id="adminFilterForm">
        <input type="hidden" name="search" value="{{ $search }}">
        <input type="hidden" name="side" id="filterSideInput" value="{{ $side }}">
        <input type="hidden" name="profit" id="filterProfitInput" value="{{ $profit }}">

        <div class="filter-chip-group">
            <button type="button" class="filter-chip {{ ($side ?? 'all') === 'all' ? 'active' : '' }}" onclick="submitWithFilter('side', 'all')">All</button>
            <button type="button" class="filter-chip buy {{ ($side ?? 'all') === 'long' ? 'active' : '' }}" onclick="submitWithFilter('side', 'long')">Long</button>
            <button type="button" class="filter-chip sell {{ ($side ?? 'all') === 'short' ? 'active' : '' }}" onclick="submitWithFilter('side', 'short')">Short</button>
        </div>

        <div class="filter-chip-group side-chips">
            <button type="button" class="filter-chip {{ ($profit ?? 'all') === 'all' ? 'active' : '' }}" onclick="submitWithFilter('profit', 'all')">All P&L</button>
            <button type="button" class="filter-chip buy {{ ($profit ?? 'all') === 'profit' ? 'active' : '' }}" onclick="submitWithFilter('profit', 'profit')">In Profit</button>
            <button type="button" class="filter-chip sell {{ ($profit ?? 'all') === 'loss' ? 'active' : '' }}" onclick="submitWithFilter('profit', 'loss')">In Loss</button>
        </div>

        <div class="compact-filter-actions">
            <input type="text" name="symbol" value="{{ $symbol }}" placeholder="BTCUSDT">
            <button type="submit" class="filter-btn">Apply</button>
            <a href="{{ request()->url() }}" class="reset-btn">Reset</a>
        </div>

        <script>
            function submitWithFilter(name, value) {
                if (name === 'side') document.getElementById('filterSideInput').value = value;
                if (name === 'profit') document.getElementById('filterProfitInput').value = value;
                document.getElementById('adminFilterForm').submit();
            }
        </script>
    </form>

    @if(session('success'))
        <div style="background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);color:#4ade80;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:0.88rem;display:flex;align-items:center;gap:8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div style="background:rgba(239,68,68,0.12);border:1px solid rgba(239,68,68,0.3);color:#fca5a5;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:0.88rem;display:flex;align-items:center;gap:8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('info'))
        <div style="background:rgba(59,130,246,0.12);border:1px solid rgba(59,130,246,0.3);color:#93c5fd;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:0.88rem;display:flex;align-items:center;gap:8px;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>{{ session('info') }}</span>
        </div>
    @endif

    <div class="wallet-table-card admin-market-table-card">
        <div class="table-header">
            <h2>{{ ucfirst($statusTab) }} Positions</h2>
            <span>Showing {{ $positions->total() }} records • Page {{ $positions->currentPage() }} / {{ $positions->lastPage() }}</span>
        </div>

        <div class="table-responsive">
            <table class="admin-table positions-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Market</th>
                        <th>Symbol / Contract</th>
                        <th>Qty</th>
                        <th>Entry</th>
                        <th>Live Price</th>
                        <th>Margin</th>
                        <th>Leverage</th>
                        <th>Fee</th>
                        <th>P&L</th>
                        <th>Opened</th>
                        <th style="text-align:center;">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($positions as $position)
                        @php
                            $entry = (float)($position->entry_price ?? 0);
                            $qty = (float)($position->quantity ?? 0);
                            $sideValue = strtolower((string)($position->side ?? 'buy'));
                            $statusValue = strtolower((string)($position->status ?? 'open'));
                            $currentPrice = (float)($position->current_market_price ?? $entry);
                            $pnl = (float)($position->admin_pnl ?? 0);
                            $posUid = (string)($position->position_uid ?? $position->_id ?? '');
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $position->display_user_name ?? 'Unknown User' }}</strong>
                                <small>{{ $position->display_user_email ?? '-' }}</small>
                            </td>
                            <td><span class="market-badge {{ $position->market_type ?? 'futures' }}">{{ ucfirst($position->market_type ?? 'futures') }}</span></td>
                            <td><strong>{{ strtoupper($position->display_symbol ?? $position->symbol ?? '-') }}</strong><small>{{ $position->mode ?? 'demo' }}</small></td>
                            <td>{{ $position->quantity ?? '-' }}</td>
                            <td>${{ number_format($entry, 4) }}</td>
                            <td class="live-price-cell" data-live-price-symbol="{{ $statusValue === 'open' ? strtoupper($position->live_price_symbol ?? '') : '' }}" data-market-type="{{ $position->market_type ?? 'futures' }}">
                                @if($statusValue === 'open')
                                    <span class="live-dot"></span><strong data-live-price-value>${{ number_format($currentPrice, 4) }}</strong>
                                @else
                                    -
                                  @endif
                            </td>
                            <td>${{ number_format((float)($position->margin ?? 0), 2) }}</td>
                            <td>{{ isset($position->leverage) ? $position->leverage . 'x' : '-' }}</td>
                            <td>${{ number_format((float)($position->fee_paid ?? 0), 2) }}</td>
                            <td>
                                <span class="{{ $pnl >= 0 ? 'pnl-profit' : 'pnl-loss' }}" data-live-pnl data-status="{{ $statusValue }}" data-entry="{{ $entry }}" data-qty="{{ $qty }}" data-side="{{ $sideValue }}">
                                    ${{ number_format($pnl, 4) }}
                                </span>
                            </td>
                            <td>{{ isset($position->opened_at) ? \Carbon\Carbon::parse($position->opened_at)->format('d M Y, h:i A') : (isset($position->created_at) ? \Carbon\Carbon::parse($position->created_at)->format('d M Y, h:i A') : '-') }}</td>
                            <td style="text-align:center;">
                                @if($statusValue === 'open')
                                    <form method="POST" action="{{ route('admin.positions.force_stop') }}" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to FORCE STOP this trade for {{ addslashes($position->display_user_name ?? 'User') }}?');">
                                        @csrf
                                        <input type="hidden" name="position_uid" value="{{ $posUid }}">
                                        <input type="hidden" name="market_type" value="{{ $position->market_type ?? 'futures' }}">
                                        <button type="submit" class="kv-btn-force-stop" title="Force Stop Trade">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                                <rect x="4" y="4" width="16" height="16" rx="2"/>
                                            </svg>
                                            <span>Force Stop</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="status-badge closed" style="font-size:0.75rem;padding:3px 8px;border-radius:6px;background:rgba(255,255,255,0.05);color:#94a3b8;">Closed</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12"><div class="empty-state">No positions found.</div></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.components.pagination', ['paginator' => $positions])

</div>
@endsection
