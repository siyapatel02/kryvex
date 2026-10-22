@extends('admin.layouts.app')

@section('title', 'Admin Dashboard | Kryvex')
@section('page_title', 'Dashboard')
@section('page_subtitle', 'Monitor users, wallet activity, trades and system health.')

@section('content')
<section class="stats-grid">
    <div class="stat-card">
        <span>Total Users</span>
        <strong>0</strong>
        <small>Live database binding next</small>
    </div>
    <div class="stat-card">
        <span>Active Users</span>
        <strong>0</strong>
        <small>Verified + active accounts</small>
    </div>
    <div class="stat-card success">
        <span>Deposits Today</span>
        <strong>₹0</strong>
        <small>Approved deposits</small>
    </div>
    <div class="stat-card warning">
        <span>Withdrawals Today</span>
        <strong>₹0</strong>
        <small>Pending + completed</small>
    </div>
</section>

<section class="dashboard-grid">
    <div class="panel large-panel">
        <div class="panel-header">
            <div>
                <h2>Platform Activity</h2>
                <p>Revenue, volume and wallet movement overview.</p>
            </div>
            <button>Today</button>
        </div>

        <div class="chart-placeholder">
            <div class="chart-line"></div>
            <p>Chart integration will be added after admin APIs.</p>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>System Status</h2>
                <p>Core services health.</p>
            </div>
        </div>

        <div class="status-list">
            <div><span>MongoDB Atlas</span><strong>Online</strong></div>
            <div><span>Market Data</span><strong>Online</strong></div>
            <div><span>Wallet Engine</span><strong>Ready</strong></div>
            <div><span>Admin Guard</span><strong>Active</strong></div>
        </div>
    </div>
</section>

<section class="dashboard-grid bottom-grid">
    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>Recent Deposits</h2>
                <p>Latest wallet funding requests.</p>
            </div>
        </div>
        <div class="empty-state">No admin deposit API connected yet.</div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <div>
                <h2>Recent Trades</h2>
                <p>Latest futures/options executions.</p>
            </div>
        </div>
        <div class="empty-state">Trade feed will connect in upcoming step.</div>
    </div>
</section>
@endsection
