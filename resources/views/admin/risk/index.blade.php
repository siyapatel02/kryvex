@extends('admin.layouts.app')

@section('title', 'Risk Management')

@section('content')
<div class="risk-page">

    <div class="page-header">
        <div>
            <h1>Risk Management</h1>
            <p>Monitor open positions, exposure, leverage and liquidation risk.</p>
        </div>
    </div>

    <div class="wallet-stats">
        <div class="wallet-card">
            <span>Open Positions</span>
            <h3>{{ $stats['open_positions'] }}</h3>
            <p>Currently active positions</p>
        </div>

        <div class="wallet-card danger">
            <span>High Risk</span>
            <h3>{{ $stats['high_risk'] }}</h3>
            <p>Need admin review</p>
        </div>

        <div class="wallet-card">
            <span>Medium Risk</span>
            <h3>{{ $stats['medium_risk'] }}</h3>
            <p>Watch carefully</p>
        </div>

        <div class="wallet-card">
            <span>Total Exposure</span>
            <h3>₹{{ number_format($stats['total_exposure'], 2) }}</h3>
            <p>Open notional value</p>
        </div>
    </div>

    <form class="wallet-filter-card" method="GET">
        <div class="filter-group">
            <label>Search</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="User, email, position id...">
        </div>

        <div class="filter-group">
            <label>Symbol</label>
            <input type="text" name="symbol" value="{{ $symbol }}" placeholder="BTCUSDT">
        </div>

        <div class="filter-group">
            <label>Risk Level</label>
            <select name="risk">
                <option value="all" {{ $risk === 'all' ? 'selected' : '' }}>All</option>
                <option value="high" {{ $risk === 'high' ? 'selected' : '' }}>High</option>
                <option value="medium" {{ $risk === 'medium' ? 'selected' : '' }}>Medium</option>
                <option value="low" {{ $risk === 'low' ? 'selected' : '' }}>Low</option>
            </select>
        </div>

        <button class="filter-btn">Filter</button>
        <a href="{{ route('admin.risk.index') }}" class="reset-btn">Reset</a>
    </form>

    <div class="wallet-table-card">
        <div class="table-header">
            <h2>Open Position Risk</h2>
            <span>{{ count($positions) }} records</span>
        </div>

        <div class="table-responsive">
            <table class="admin-table risk-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Symbol</th>
                        <th>Side</th>
                        <th>Qty</th>
                        <th>Entry</th>
                        <th>Notional</th>
                        <th>Margin</th>
                        <th>Leverage</th>
                        <th>Fee</th>
                        <th>Risk</th>
                        <th>Opened</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($positions as $position)
                        <tr>
                            <td>
                                <strong>{{ $position->display_user_name }}</strong>
                                <small>{{ $position->display_user_email }}</small>
                            </td>

                            <td>
                                <strong>{{ strtoupper($position->symbol ?? '-') }}</strong>
                                <small>{{ $position->mode ?? 'demo' }}</small>
                            </td>

                            <td>
                                <span class="side-badge {{ strtolower($position->side ?? 'buy') }}">
                                    {{ strtoupper($position->side ?? '-') }}
                                </span>
                            </td>

                            <td>{{ $position->quantity ?? '-' }}</td>

                            <td>₹{{ number_format((float)($position->entry_price ?? 0), 2) }}</td>

                            <td class="amount">₹{{ number_format((float)($position->notional_value ?? 0), 2) }}</td>

                            <td>₹{{ number_format((float)($position->margin ?? 0), 2) }}</td>

                            <td>{{ $position->leverage ?? '-' }}x</td>

                            <td>₹{{ number_format((float)($position->fee_paid ?? 0), 2) }}</td>

                            <td>
                                <span class="risk-badge {{ $position->risk_level }}">
                                    {{ ucfirst($position->risk_level) }} Risk
                                </span>
                            </td>

                            <td>
                                {{ isset($position->opened_at) ? \Carbon\Carbon::parse($position->opened_at)->format('d M Y, h:i A') : '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11">
                                <div class="empty-state">No open risk positions found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection