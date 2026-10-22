@extends('admin.layouts.app')

@section('title', 'Orders')

@section('content')
    <div class="orders-page">

        <div class="admin-market-page-head">
            <div>
                <p class="eyebrow">Admin</p>
                <h1>Orders</h1>
                <span>Monitor futures and options orders with clean filters, lifecycle status and fees.</span>
            </div>

        </div>

        <div class="admin-market-stats-grid">
            <div class="admin-market-stat-card violet">
                <span>Total Orders</span>
                <strong>{{ number_format($orderStats['total'] ?? 0) }}</strong>
                <small>All matching records</small>
            </div>

            <div class="admin-market-stat-card amber">
                <span>Pending</span>
                <strong>{{ number_format($orderStats['pending'] ?? 0) }}</strong>
                <small>Awaiting execution</small>
            </div>

            <div class="admin-market-stat-card green">
                <span>Open</span>
                <strong>{{ number_format($orderStats['open'] ?? 0) }}</strong>
                <small>Active position rows</small>
            </div>

            <div class="admin-market-stat-card blue">
                <span>Buy Orders</span>
                <strong>{{ number_format($orderStats['buy'] ?? 0) }}</strong>
                <small>Buy / long / call side</small>
            </div>
        </div>

        <div class="admin-market-tabs-row">
            <div class="wallet-tabs market-tabs-main">
                <a href="{{ route('admin.orders.index', request()->except('page')) }}" class="wallet-tab {{ $market === 'all' ? 'active' : '' }}">All Orders</a>
                <a href="{{ route('admin.orders.futures', request()->except('page')) }}" class="wallet-tab {{ $market === 'futures' ? 'active' : '' }}">Futures</a>
                <a href="{{ route('admin.orders.options', request()->except('page')) }}" class="wallet-tab {{ $market === 'options' ? 'active' : '' }}">Options</a>
            </div>
        </div>

        <form class="admin-market-filter-strip" method="GET">
            <input type="hidden" name="search" value="{{ $search }}">
            <input type="hidden" name="symbol" value="{{ $symbol }}">
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="filter-chip-group">
                @foreach([
                    'all' => 'All',
                    'pending' => 'Pending',
                    'active' => 'Open',
                    'filled' => 'Closed',
                    'cancelled' => 'Canceled',
                ] as $value => $label)
                    <button name="status" value="{{ $value }}" class="filter-chip {{ $status === $value ? 'active' : '' }}" type="submit">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="filter-chip-group side-chips">
                <button name="side" value="all" class="filter-chip {{ ($side ?? 'all') === 'all' ? 'active' : '' }}" type="submit">All Sides</button>
                <button name="side" value="buy" class="filter-chip buy {{ ($side ?? 'all') === 'buy' ? 'active' : '' }}" type="submit">Buy</button>
                <button name="side" value="sell" class="filter-chip sell {{ ($side ?? 'all') === 'sell' ? 'active' : '' }}" type="submit">Sell</button>
            </div>

            <div class="compact-filter-actions">
                <input type="text" name="symbol" value="{{ $symbol }}" placeholder="BTCUSDT">
                <select name="type">
                    <option value="all" {{ $type === 'all' ? 'selected' : '' }}>All Types</option>
                    <option value="market" {{ $type === 'market' ? 'selected' : '' }}>Market</option>
                    <option value="limit" {{ $type === 'limit' ? 'selected' : '' }}>Limit</option>
                </select>
                <button class="filter-btn">Apply</button>
                <a href="{{ request()->url() }}" class="reset-btn">Reset</a>
            </div>
        </form>

        <div class="wallet-table-card admin-market-table-card">
            <div class="table-header">
                <h2>{{ ucfirst($market) }} Orders</h2>
                <span>Showing {{ $orders->total() }} records • Page {{ $orders->currentPage() }} / {{ $orders->lastPage() }}</span>
            </div>

            <div class="table-responsive">
                <table class="admin-table orders-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Market</th>
                            <th>Symbol / Contract</th>
                            <th>Side</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Margin</th>
                            <th>Leverage</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td>
                                    <strong>{{ $order->display_user_name ?? 'Unknown User' }}</strong>
                                    <small>{{ $order->display_user_email ?? '-' }}</small>
                                    @if(!empty($order->user_db_id))
                                        <a href="{{ route('admin.users.show', $order->user_db_id) }}" style="font-size: 11px; color: #3b82f6; text-decoration: none; display: inline-block; margin-top: 4px;">[View User]</a>
                                    @endif
                                </td>

                                <td>
                                    <span class="market-badge {{ $order->market_type ?? 'futures' }}">
                                        {{ ucfirst($order->market_type ?? '-') }}
                                    </span>
                                </td>

                                <td>
                                    <strong>{{ strtoupper($order->display_symbol ?? $order->symbol ?? '-') }}</strong>
                                    <small>{{ $order->mode ?? 'demo' }}</small>
                                </td>

                                <td>
                                    <span class="side-badge {{ strtolower($order->side ?? $order->contract_type ?? 'buy') }}">
                                        {{ strtoupper($order->side ?? $order->contract_type ?? '-') }}
                                    </span>
                                </td>

                                <td>{{ ucfirst($order->order_type ?? ($order->type ?? '-')) }}</td>
                                <td>{{ $order->display_qty ?? '-' }}</td>
                                <td>${{ number_format((float) ($order->display_price ?? 0), 4) }}</td>
                                <td>${{ number_format((float) ($order->display_margin ?? 0), 2) }}</td>
                                <td>{{ isset($order->leverage) ? $order->leverage . 'x' : '-' }}</td>
                                <td>${{ number_format((float) ($order->display_fee ?? 0), 2) }}</td>
                                <td>
                                    <span class="status-badge {{ strtolower($order->display_status ?? ($order->status ?? 'pending')) }}">
                                        {{ ucfirst($order->display_status ?? ($order->status ?? 'pending')) }}
                                    </span>
                                </td>
                                <td>{{ isset($order->display_date) ? \Carbon\Carbon::parse($order->display_date)->format('d M Y, h:i A') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12">
                                    <div class="empty-state">No orders found.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.components.pagination', ['paginator' => $orders])

    </div>
@endsection
