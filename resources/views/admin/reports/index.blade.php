@extends('admin.layouts.app')

@section('title', 'Reports')

@section('content')
<div class="reports-page">

    <div class="page-header">
        <div>
            <h1>Reports</h1>
            <p>Click any report card to view details and export CSV.</p>
        </div>
    </div>

    <div class="wallet-stats reports-stats">

        <a class="report-card-link wallet-card" href="{{ route('admin.reports.show', 'users') }}">
            <span>Total Users</span>
            <h3>{{ $stats['total_users'] }}</h3>
            <p>View user report</p>
        </a>

        <a class="report-card-link wallet-card" href="{{ route('admin.reports.show', 'wallet') }}">
            <span>Wallet Total</span>
            <h3>₹{{ number_format($stats['wallet_total'], 2) }}</h3>
            <p>View wallet report</p>
        </a>

        <a class="report-card-link wallet-card" href="{{ route('admin.reports.show', 'deposits') }}">
            <span>Total Deposits</span>
            <h3>₹{{ number_format($stats['total_deposits'], 2) }}</h3>
            <p>View deposits report</p>
        </a>

        <a class="report-card-link wallet-card danger" href="{{ route('admin.reports.show', 'withdrawals') }}">
            <span>Withdrawals</span>
            <h3>{{ $stats['withdrawal_requests'] }}</h3>
            <p>View withdrawal report</p>
        </a>

        <a class="report-card-link wallet-card" href="{{ route('admin.reports.show', 'orders') }}">
            <span>Total Orders</span>
            <h3>{{ $stats['total_orders'] }}</h3>
            <p>View orders report</p>
        </a>

        <a class="report-card-link wallet-card" href="{{ route('admin.reports.show', 'positions') }}">
            <span>Positions</span>
            <h3>{{ $stats['open_positions'] + $stats['closed_positions'] }}</h3>
            <p>View positions report</p>
        </a>

        <a class="report-card-link wallet-card" href="{{ route('admin.reports.show', 'fees') }}">
            <span>Fees Revenue</span>
            <h3>₹{{ number_format($stats['fees_revenue'], 2) }}</h3>
            <p>View fees report</p>
        </a>

        <a class="report-card-link wallet-card" href="{{ route('admin.reports.show', 'pnl') }}">
            <span>Realized P&L</span>
            <h3 class="{{ $stats['realized_pnl'] >= 0 ? 'pnl-profit' : 'pnl-loss' }}">
                ₹{{ number_format($stats['realized_pnl'], 2) }}
            </h3>
            <p>View P&L report</p>
        </a>

    </div>

</div>
@endsection