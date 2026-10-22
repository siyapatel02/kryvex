@extends('admin.layouts.app')

@section('title', 'Markets')

@section('content')
<div class="markets-page">

    <div class="page-header market-header" data-markets-page>
        <div>
            <h1>Markets</h1>
            <p>Control trading pairs, leverage, fees and maintenance mode.</p>
        </div>

        <button class="market-add-btn" type="button" id="openAddMarketModal">
            + Add Market
        </button>
    </div>
<form class="wallet-filter-card" method="GET">
        <div class="filter-group">
            <label>Search</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="BTCUSDT, ETH...">
        </div>

        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All</option>
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
        </div>

        <button class="filter-btn">Filter</button>
        <a href="{{ route('admin.markets.index') }}" class="reset-btn">Reset</a>
    </form>

    <div class="wallet-table-card">
        <div class="table-header">
            <h2>Trading Pairs</h2>
            <span>{{ $markets->total() }} records • Page {{ $markets->currentPage() }} / {{ $markets->lastPage() }}</span>
        </div>

        <div class="table-responsive">
            <table class="admin-table markets-table">
                <thead>
                    <tr>
                        <th>Symbol</th>
                        <th>Assets</th>
                        <th>Status</th>
                        <th>Futures</th>
                        <th>Options</th>
                        <th>Maintenance</th>
                        <th>Min Qty</th>
                        <th>Max Lev.</th>
                        <th>Maker Fee</th>
                        <th>Taker Fee</th>
                        <th>Order</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($markets as $market)
                        <tr>
                            <td>
                                <strong>{{ $market->symbol ?? '-' }}</strong>
                            </td>

                            <td>{{ $market->base_asset ?? '-' }}/{{ $market->quote_asset ?? '-' }}</td>

                            <td>
                                <span class="status-badge {{ $market->status ?? 'active' }}">
                                    {{ ucfirst($market->status ?? 'active') }}
                                </span>
                            </td>

                            <td>{{ !empty($market->futures_enabled) ? 'Enabled' : 'Disabled' }}</td>

                            <td>{{ !empty($market->options_enabled) ? 'Enabled' : 'Disabled' }}</td>

                            <td>
                                @if(!empty($market->maintenance_mode))
                                    <span class="status-badge pending">On</span>
                                @else
                                    <span class="status-badge approved">Off</span>
                                @endif
                            </td>

                            <td>{{ $market->min_qty ?? 0 }}</td>

                            <td>{{ $market->max_leverage ?? 1 }}x</td>

                            <td>{{ $market->maker_fee ?? 0 }}</td>

                            <td>{{ $market->taker_fee ?? 0 }}</td>

                            <td>{{ $market->display_order ?? 99 }}</td>

                            <td>
                                <button
                                    type="button"
                                    class="market-edit-btn"
                                    data-id="{{ $market->market_id }}"
                                    data-update-url="{{ route('admin.markets.update', $market->market_id) }}"
                                    data-symbol="{{ $market->symbol ?? '' }}"
                                    data-base="{{ $market->base_asset ?? '' }}"
                                    data-quote="{{ $market->quote_asset ?? '' }}"
                                    data-status="{{ $market->status ?? 'active' }}"
                                    data-futures="{{ !empty($market->futures_enabled) ? '1' : '0' }}"
                                    data-options="{{ !empty($market->options_enabled) ? '1' : '0' }}"
                                    data-maintenance="{{ !empty($market->maintenance_mode) ? '1' : '0' }}"
                                    data-minqty="{{ $market->min_qty ?? 0 }}"
                                    data-leverage="{{ $market->max_leverage ?? 1 }}"
                                    data-maker="{{ $market->maker_fee ?? 0 }}"
                                    data-taker="{{ $market->taker_fee ?? 0 }}"
                                    data-order="{{ $market->display_order ?? 99 }}"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12">
                                <div class="empty-state">No markets found. Add BTCUSDT or ETHUSDT first.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

    @include('admin.components.pagination', ['paginator' => $markets])

</div>

{{-- Market Modal --}}
<div class="market-modal-overlay" id="marketModalOverlay">
    <div class="market-modal">
        <div class="market-modal-header">
            <div>
                <h2 id="marketModalTitle">Add Market</h2>
                <p id="marketModalSubtitle">Create a new trading pair setting.</p>
            </div>

            <button type="button" class="market-modal-close" id="closeMarketModal">×</button>
        </div>

        <form method="POST" action="{{ route('admin.markets.store') }}" id="marketForm">
            @csrf
            <input type="hidden" name="_method" id="marketFormMethod" value="POST">

            <div class="market-form-grid">
                <div class="form-group">
                    <label>Symbol</label>
                    <input name="symbol" id="marketSymbol" placeholder="BTCUSDT" required>
                </div>

                <div class="form-group">
                    <label>Base Asset</label>
                    <input name="base_asset" id="marketBase" placeholder="BTC" required>
                </div>

                <div class="form-group">
                    <label>Quote Asset</label>
                    <input name="quote_asset" id="marketQuote" placeholder="USDT" required>
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="marketStatus">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Min Qty</label>
                    <input name="min_qty" id="marketMinQty" type="number" step="0.000001" placeholder="0.001" required>
                </div>

                <div class="form-group">
                    <label>Max Leverage</label>
                    <input name="max_leverage" id="marketLeverage" type="number" placeholder="100" required>
                </div>

                <div class="form-group">
                    <label>Maker Fee</label>
                    <input name="maker_fee" id="marketMakerFee" type="number" step="0.000001" placeholder="0.0002" required>
                </div>

                <div class="form-group">
                    <label>Taker Fee</label>
                    <input name="taker_fee" id="marketTakerFee" type="number" step="0.000001" placeholder="0.0005" required>
                </div>

                <div class="form-group">
                    <label>Display Order</label>
                    <input name="display_order" id="marketOrder" type="number" placeholder="1">
                </div>
            </div>

            <div class="market-switch-row">
                <label>
                    <input type="checkbox" name="futures_enabled" id="marketFutures" checked>
                    Futures Enabled
                </label>

                <label>
                    <input type="checkbox" name="options_enabled" id="marketOptions" checked>
                    Options Enabled
                </label>

                <label>
                    <input type="checkbox" name="maintenance_mode" id="marketMaintenance">
                    Maintenance Mode
                </label>
            </div>

            <div class="market-modal-actions">
                <button type="button" class="modal-cancel-btn" id="cancelMarketModal">Cancel</button>
                <button type="submit" class="modal-save-btn" id="marketSubmitBtn">Save Market</button>
            </div>
        </form>
    </div>

</div>
@endsection