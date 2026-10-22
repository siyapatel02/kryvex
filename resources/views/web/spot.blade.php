@extends('layouts.app')

@section('title', $symbol . ' Spot Trading - Kryvex')

@push('styles')
<style>
    /* Spot Trading Page Styles */
    .spot-page {
        color: #cbd5e1;
        background: #0b0e14;
        min-height: calc(100vh - 70px);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    }
    .spot-ticker-bar {
        background: #0f141f;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        padding: 8px 16px;
        display: flex;
        align-items: center;
        gap: 24px;
        position: relative;
        z-index: 1000;
        overflow: visible;
    }
    .spot-pair-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 6px 14px;
        color: #ffffff;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .spot-pair-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: #6366f1;
    }
    .spot-stat-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    .spot-stat-label {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 600;
    }
    .spot-stat-val {
        font-size: 13px;
        font-weight: 600;
        color: #f1f5f9;
        font-family: monospace;
    }
    .spot-workspace-row {
        display: grid;
        grid-template-columns: 1fr 310px 330px;
        gap: 1px;
        background: rgba(255, 255, 255, 0.07);
        height: 560px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }
    @media (max-width: 1280px) {
        .spot-workspace-row {
            grid-template-columns: 1fr 330px;
            height: 560px;
        }
        .spot-orderbook-col {
            display: none !important;
        }
    }
    @media (max-width: 900px) {
        .spot-workspace-row {
            grid-template-columns: 1fr;
            height: auto;
        }
        .spot-chart-col {
            height: 440px !important;
        }
        .spot-orderbook-col {
            display: flex !important;
            height: 500px;
        }
        .spot-order-col {
            height: auto;
        }
    }
    .spot-panel {
        background: #0b0e14;
        display: flex;
        flex-direction: column;
        height: 100%;
        overflow: hidden;
    }
    .spot-chart-col {
        background: #080a0f;
    }
    .spot-chart-container {
        width: 100%;
        height: 100%;
        position: relative;
        background: #080a0f;
    }
    .spot-order-col {
        overflow-y: auto;
    }
    .spot-bottom-tabs-panel {
        width: 100%;
        background: #0b0e14;
        display: flex;
        flex-direction: column;
    }
    .spot-tabs-header {
        display: flex;
        align-items: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        background: #0f141f;
        padding: 0 16px;
        gap: 8px;
    }
    .spot-tab-btn {
        padding: 11px 18px;
        font-size: 13px;
        font-weight: 600;
        color: #94a3b8;
        background: transparent;
        border: none;
        border-bottom: 2px solid transparent;
        cursor: pointer;
        transition: all 0.2s;
    }
    .spot-tab-btn.active {
        color: #ffffff;
        border-bottom-color: #6366f1;
    }
    .spot-tabs-content {
        min-height: 240px;
        max-height: 380px;
        overflow-y: auto;
        background: #080a0f;
    }
    /* Orderbook */
    .ob-row {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        padding: 3px 12px;
        font-size: 12px;
        font-family: monospace;
        position: relative;
        cursor: pointer;
    }
    .ob-row:hover {
        background: rgba(255, 255, 255, 0.04);
    }
    .ob-bg {
        position: absolute;
        top: 0;
        bottom: 0;
        right: 0;
        opacity: 0.15;
        pointer-events: none;
        transition: width 0.15s;
    }
    .ob-bg-ask { background: #ef4444; }
    .ob-bg-bid { background: #10b981; }
    .text-ask { color: #f87171; }
    .text-bid { color: #34d399; }
    
    /* Order Form */
    .spot-order-panel {
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .spot-side-toggle {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        background: rgba(255, 255, 255, 0.04);
        padding: 4px;
        border-radius: 8px;
    }
    .spot-side-btn {
        padding: 8px 0;
        font-size: 14px;
        font-weight: 700;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        color: #94a3b8;
        background: transparent;
    }
    .spot-side-btn.buy.active {
        background: #10b981;
        color: #ffffff;
        box-shadow: 0 0 12px rgba(16, 185, 129, 0.4);
    }
    .spot-side-btn.sell.active {
        background: #ef4444;
        color: #ffffff;
        box-shadow: 0 0 12px rgba(239, 68, 68, 0.4);
    }
    .spot-type-toggle {
        display: flex;
        gap: 12px;
    }
    .spot-type-btn {
        background: transparent;
        border: none;
        color: #64748b;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        padding-bottom: 4px;
        border-bottom: 2px solid transparent;
    }
    .spot-type-btn.active {
        color: #ffffff;
        border-bottom-color: #6366f1;
    }
    .spot-field-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .spot-input-wrap {
        display: flex;
        align-items: center;
        background: #131926;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        padding: 8px 12px;
        gap: 8px;
    }
    .spot-input-wrap:focus-within {
        border-color: #6366f1;
    }
    .spot-input-label {
        font-size: 12px;
        color: #64748b;
        min-width: 50px;
    }
    .spot-input {
        flex: 1;
        background: transparent;
        border: none;
        color: #ffffff;
        font-size: 14px;
        font-weight: 600;
        font-family: monospace;
        outline: none;
    }
    .spot-input-suffix {
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
    }
    .spot-pct-btns {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 6px;
    }
    .spot-pct-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 4px;
        padding: 4px 0;
        color: #94a3b8;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }
    .spot-pct-btn:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
        border-color: #6366f1;
    }
    .spot-submit-btn {
        width: 100%;
        padding: 12px 0;
        font-size: 15px;
        font-weight: 700;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .spot-submit-btn.buy {
        background: #10b981;
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
    }
    .spot-submit-btn.buy:hover {
        background: #059669;
    }
    .spot-submit-btn.sell {
        background: #ef4444;
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
    }
    .spot-submit-btn.sell:hover {
        background: #dc2626;
    }
    /* Tables */
    .spot-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
        font-family: monospace;
    }
    .spot-table th {
        text-align: left;
        padding: 10px 14px;
        color: #64748b;
        font-weight: 600;
        border-bottom: 1px solid rgba(255, 255, 255, 0.07);
        background: #0c1017;
    }
    .spot-table td {
        padding: 10px 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04);
        color: #e2e8f0;
    }
    .spot-table tr:hover td {
        background: rgba(255, 255, 255, 0.02);
    }
    .badge-status {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-open { background: rgba(59, 130, 246, 0.2); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.4); }
    .badge-filled { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
    .badge-cancelled { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
    
    .btn-cancel-order {
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
        color: #f87171;
        border-radius: 4px;
        padding: 3px 8px;
        font-size: 11px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s;
    }
    .btn-cancel-order:hover {
        background: #ef4444;
        color: #ffffff;
    }
    /* Modal Dropdown */
    .spot-pair-selector-wrap {
        position: relative;
        z-index: 1050;
        flex-shrink: 0;
    }
    .spot-pair-dropdown {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        background: #111622;
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.85);
        width: 320px;
        z-index: 99999;
        display: none;
        max-height: 420px;
        overflow-y: auto;
    }
    .spot-pair-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        color: #cbd5e1;
        text-decoration: none;
        font-size: 13px;
        transition: background 0.15s;
    }
    .spot-pair-item:hover {
        background: rgba(99, 102, 241, 0.15);
        color: #ffffff;
    }
</style>
@endpush

@section('content')
<div class="spot-page" id="spotTradingApp">
    <!-- TOP TICKER & STATS BAR -->
    <div class="spot-ticker-bar">
        <div class="spot-pair-selector-wrap">
            <button type="button" class="spot-pair-btn" id="spotPairDropdownBtn">
                <span id="currentPairText">{{ $pairs[$symbol]['name'] ?? 'BTC/USDT' }}</span>
                <span class="badge-status" style="background: rgba(99,102,241,0.2); color: #818cf8; font-size: 10px;">SPOT</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </button>

            <!-- PAIR SELECTOR DROPDOWN -->
            <div class="spot-pair-dropdown" id="spotPairDropdown">
                <div style="padding: 8px 12px; border-bottom: 1px solid rgba(255,255,255,0.08);">
                    <input type="text" id="pairSearchInput" placeholder="Search pair..." style="width: 100%; background: #090c12; border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; padding: 6px 10px; color: #fff; font-size: 12px; outline: none;">
                </div>
                <div id="pairDropdownList">
                    @foreach($pairs as $pKey => $pCfg)
                        <a href="{{ url('/trade/' . $pKey) }}" class="spot-pair-item" data-symbol="{{ $pKey }}">
                            <div>
                                <strong style="color: #fff;">{{ $pCfg['name'] }}</strong>
                                <div style="font-size: 11px; color: #64748b;">{{ $pCfg['base'] }}</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-family: monospace; font-weight: 600;" id="pairPrice_{{ $pKey }}">--</div>
                                <div style="font-size: 11px; font-family: monospace;" id="pairChange_{{ $pKey }}">--</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- MAIN TICKER STATS -->
        <div class="spot-stat-item">
            <span class="spot-stat-label">Price</span>
            <span class="spot-stat-val text-bid" id="mainHeaderPrice" style="font-size: 16px;">$--</span>
        </div>

        <div class="spot-stat-item">
            <span class="spot-stat-label">24h Change</span>
            <span class="spot-stat-val" id="mainHeaderChange">--%</span>
        </div>

        <div class="spot-stat-item">
            <span class="spot-stat-label">24h High</span>
            <span class="spot-stat-val" id="mainHeaderHigh">$--</span>
        </div>

        <div class="spot-stat-item">
            <span class="spot-stat-label">24h Low</span>
            <span class="spot-stat-val" id="mainHeaderLow">$--</span>
        </div>

        <div class="spot-stat-item">
            <span class="spot-stat-label">24h Volume ({{ $pairs[$symbol]['base'] ?? 'BTC' }})</span>
            <span class="spot-stat-val" id="mainHeaderVolume">--</span>
        </div>

        <div class="spot-stat-item">
            <span class="spot-stat-label">24h Turnover (USDT)</span>
            <span class="spot-stat-val" id="mainHeaderTurnover">$--</span>
        </div>
    </div>

    <!-- MAIN SPOT TRADING WORKSPACE ROW (EQUAL HEIGHTS) -->
    <div class="spot-workspace-row">
        <!-- 1. LEFT COLUMN: TRADINGVIEW CANDLESTICK CHART -->
        <div class="spot-panel spot-chart-col">
            <div class="spot-chart-container" id="spotTradingViewWidget"></div>
        </div>

        <!-- 2. MIDDLE COLUMN: LIVE ORDER BOOK & RECENT TRADES -->
        <div class="spot-panel spot-orderbook-col">
            <div style="padding: 10px 14px; border-bottom: 1px solid rgba(255,255,255,0.07); font-size: 13px; font-weight: 700; color: #e2e8f0; display: flex; justify-content: space-between; flex-shrink: 0;">
                <span>Order Book</span>
                <span style="font-size: 11px; color: #64748b;">Live Depth</span>
            </div>

            <!-- ASKS (SELL ORDERS - RED) -->
            <div style="flex: 1; min-height: 130px; max-height: 175px; overflow: hidden; display: flex; flex-direction: column-reverse;" id="orderBookAsks">
                <!-- Dynamically populated -->
            </div>

            <!-- CURRENT PRICE SPREAD DIVIDER -->
            <div style="padding: 7px 12px; background: rgba(255,255,255,0.03); border-top: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: space-between; flex-shrink: 0;">
                <span id="obCurrentPrice" style="font-size: 15px; font-weight: 700; font-family: monospace;" class="text-bid">$--</span>
                <span style="font-size: 11px; color: #64748b;" id="obSpreadVal">Spread: 0.01</span>
            </div>

            <!-- BIDS (BUY ORDERS - GREEN) -->
            <div style="flex: 1; min-height: 130px; max-height: 175px; overflow: hidden;" id="orderBookBids">
                <!-- Dynamically populated -->
            </div>

            <!-- RECENT MARKET TRADES -->
            <div style="border-top: 1px solid rgba(255,255,255,0.07); flex: 1; min-height: 130px; display: flex; flex-direction: column; overflow: hidden;">
                <div style="padding: 7px 12px; font-size: 12px; font-weight: 700; color: #94a3b8; background: #0c1017; flex-shrink: 0;">Market Trades</div>
                <div style="flex: 1; overflow-y: auto;" id="marketTradesList">
                    <!-- Dynamically populated -->
                </div>
            </div>
        </div>

        <!-- 3. RIGHT COLUMN: BUY / SELL ORDER ENTRY FORM -->
        <div class="spot-panel spot-order-col">
            <div class="spot-order-panel">
                <!-- BUY / SELL SIDE TOGGLE -->
                <div class="spot-side-toggle">
                    <button type="button" class="spot-side-btn buy active" id="btnSideBuy">Buy {{ $pairs[$symbol]['base'] ?? 'BTC' }}</button>
                    <button type="button" class="spot-side-btn sell" id="btnSideSell">Sell {{ $pairs[$symbol]['base'] ?? 'BTC' }}</button>
                </div>

                <!-- ORDER TYPE (LIMIT / MARKET) -->
                <div class="spot-type-toggle">
                    <button type="button" class="spot-type-btn active" id="btnTypeLimit">Limit</button>
                    <button type="button" class="spot-type-btn" id="btnTypeMarket">Market</button>
                </div>

                <!-- AVAILABLE BALANCE DISPLAY -->
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #94a3b8;">
                    <span>Available:</span>
                    <span style="font-weight: 700; color: #f1f5f9; font-family: monospace;" id="availBalanceText">-- USDT</span>
                </div>

                <!-- LIMIT PRICE INPUT (HIDDEN IN MARKET MODE) -->
                <div class="spot-field-group" id="limitPriceGroup">
                    <div class="spot-input-wrap">
                        <span class="spot-input-label">Price</span>
                        <input type="number" step="any" id="orderPriceInput" class="spot-input" placeholder="0.00">
                        <span class="spot-input-suffix">USDT</span>
                    </div>
                </div>

                <!-- AMOUNT INPUT -->
                <div class="spot-field-group">
                    <div class="spot-input-wrap">
                        <span class="spot-input-label">Amount</span>
                        <input type="number" step="any" id="orderAmountInput" class="spot-input" placeholder="0.00">
                        <span class="spot-input-suffix">{{ $pairs[$symbol]['base'] ?? 'BTC' }}</span>
                    </div>
                </div>

                <!-- PERCENTAGE QUICK BUTTONS (25%, 50%, 75%, 100%) -->
                <div class="spot-pct-btns">
                    <button type="button" class="spot-pct-btn" data-pct="0.25">25%</button>
                    <button type="button" class="spot-pct-btn" data-pct="0.50">50%</button>
                    <button type="button" class="spot-pct-btn" data-pct="0.75">75%</button>
                    <button type="button" class="spot-pct-btn" data-pct="1.00">100%</button>
                </div>

                <!-- ORDER VALUE / COST SUMMARY -->
                <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 8px; font-size: 12px;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;">Order Total:</span>
                        <span style="font-weight: 700; color: #fff; font-family: monospace;" id="orderTotalCost">0.00 USDT</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #64748b;">Trading Fee (0.1%):</span>
                        <span style="color: #94a3b8; font-family: monospace;" id="orderEstFee">0.00 USDT</span>
                    </div>
                </div>

                <!-- SUBMIT BUTTON -->
                <button type="button" class="spot-submit-btn buy" id="btnSubmitOrder">
                    Buy {{ $pairs[$symbol]['base'] ?? 'BTC' }}
                </button>

                <!-- ZERO LEVERAGE BENEFIT NOTE -->
                <div style="display: flex; align-items: center; gap: 8px; font-size: 11px; color: #64748b; background: rgba(99,102,241,0.06); padding: 8px 10px; border-radius: 6px; border: 1px solid rgba(99,102,241,0.12);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#818cf8" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span>Spot Trading has <strong>Zero Leverage</strong> &amp; no liquidation risk. You own the underlying assets.</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. BOTTOM TABS: FULL WIDTH ACROSS THE ENTIRE SCREEN (IMAGE 1 SPEC) -->
    <div class="spot-bottom-tabs-panel">
        <div class="spot-tabs-header">
            <button type="button" class="spot-tab-btn active" data-tab="openOrders">Open Orders (<span id="openOrdersCount">0</span>)</button>
            <button type="button" class="spot-tab-btn" data-tab="orderHistory">Order History</button>
            <button type="button" class="spot-tab-btn" data-tab="tradeHistory">Trade History</button>
            <button type="button" class="spot-tab-btn" data-tab="spotAssets">Spot Assets</button>
        </div>

        <div class="spot-tabs-content">
            <!-- TAB 1: OPEN ORDERS -->
            <div id="tabContent_openOrders" class="tab-pane">
                <table class="spot-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Pair</th>
                            <th>Type</th>
                            <th>Side</th>
                            <th>Price</th>
                            <th>Amount</th>
                            <th>Filled</th>
                            <th>Total (USDT)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="openOrdersTableBody">
                        <tr><td colspan="9" style="text-align: center; color: #64748b; padding: 24px;">No open orders found.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- TAB 2: ORDER HISTORY -->
            <div id="tabContent_orderHistory" class="tab-pane" style="display: none;">
                <table class="spot-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Pair</th>
                            <th>Type</th>
                            <th>Side</th>
                            <th>Avg Price</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Fee</th>
                        </tr>
                    </thead>
                    <tbody id="orderHistoryTableBody">
                        <tr><td colspan="8" style="text-align: center; color: #64748b; padding: 24px;">No order history.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- TAB 3: TRADE HISTORY -->
            <div id="tabContent_tradeHistory" class="tab-pane" style="display: none;">
                <table class="spot-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Pair</th>
                            <th>Side</th>
                            <th>Executed Price</th>
                            <th>Executed Amount</th>
                            <th>Cost</th>
                            <th>Fee</th>
                        </tr>
                    </thead>
                    <tbody id="tradeHistoryTableBody">
                        <tr><td colspan="7" style="text-align: center; color: #64748b; padding: 24px;">No trades executed yet.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- TAB 4: SPOT ASSETS -->
            <div id="tabContent_spotAssets" class="tab-pane" style="display: none;">
                <table class="spot-table">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Free Balance</th>
                            <th>Locked in Orders</th>
                            <th>Total Balance</th>
                            <th>Est. Value (USDT)</th>
                        </tr>
                    </thead>
                    <tbody id="spotAssetsTableBody">
                        <tr><td colspan="5" style="text-align: center; color: #64748b; padding: 24px;">Loading balances...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- TradingView Widget Library -->
<script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const CURRENT_SYMBOL = @json($symbol);
    const PAIR_CONFIG = @json($pairConfig);
    const ALL_PAIRS = @json($pairs);

    let activeSide = 'buy';
    let activeType = 'limit';
    let currentPrice = 0;
    let userBalances = {};

    // 1. Initialize TradingView Chart Widget
    function initTradingViewChart() {
        const tvSymbol = 'BINANCE:' + CURRENT_SYMBOL;
        new TradingView.widget({
            "autosize": true,
            "symbol": tvSymbol,
            "interval": "15",
            "timezone": "Etc/UTC",
            "theme": "dark",
            "style": "1",
            "locale": "en",
            "toolbar_bg": "#0b0e14",
            "enable_publishing": false,
            "hide_top_toolbar": false,
            "save_image": false,
            "container_id": "spotTradingViewWidget"
        });
    }
    initTradingViewChart();

    // 2. Fetch Live Tickers and Stats
    async function fetchTickers() {
        try {
            const res = await fetch('/api/spot/tickers?symbol=' + encodeURIComponent(CURRENT_SYMBOL));
            const data = await res.json();
            if (data.success && data.data) {
                const currentData = data.data[CURRENT_SYMBOL];
                if (currentData) {
                    currentPrice = currentData.price;
                    document.getElementById('mainHeaderPrice').textContent = '$' + currentPrice.toLocaleString(undefined, {minimumFractionDigits: PAIR_CONFIG.price_precision, maximumFractionDigits: PAIR_CONFIG.price_precision});
                    
                    const changeEl = document.getElementById('mainHeaderChange');
                    const isPos = currentData.change >= 0;
                    changeEl.textContent = (isPos ? '+' : '') + currentData.change.toFixed(2) + '%';
                    changeEl.className = 'spot-stat-val ' + (isPos ? 'text-bid' : 'text-ask');

                    document.getElementById('mainHeaderHigh').textContent = '$' + currentData.high.toLocaleString(undefined, {minimumFractionDigits: PAIR_CONFIG.price_precision});
                    document.getElementById('mainHeaderLow').textContent = '$' + currentData.low.toLocaleString(undefined, {minimumFractionDigits: PAIR_CONFIG.price_precision});
                    document.getElementById('mainHeaderVolume').textContent = currentData.volume.toLocaleString(undefined, {maximumFractionDigits: 2});
                    document.getElementById('mainHeaderTurnover').textContent = '$' + currentData.quote_volume.toLocaleString(undefined, {maximumFractionDigits: 0});
                    document.getElementById('obCurrentPrice').textContent = '$' + currentPrice.toLocaleString(undefined, {minimumFractionDigits: PAIR_CONFIG.price_precision});

                    // Set default limit price if empty
                    const priceInput = document.getElementById('orderPriceInput');
                    if (!priceInput.value && currentPrice > 0) {
                        priceInput.value = currentPrice;
                    }
                }

                // Update dropdown prices
                for (const pKey in data.data) {
                    const pItem = data.data[pKey];
                    const priceEl = document.getElementById('pairPrice_' + pKey);
                    const chgEl = document.getElementById('pairChange_' + pKey);
                    if (priceEl && chgEl) {
                        priceEl.textContent = '$' + pItem.price.toLocaleString();
                        chgEl.textContent = (pItem.change >= 0 ? '+' : '') + pItem.change.toFixed(2) + '%';
                        chgEl.style.color = pItem.change >= 0 ? '#34d399' : '#f87171';
                    }
                }

                // Auto-process pending limit order fills
                processLimitFills();
            }
        } catch (e) {
            console.warn('Ticker fetch error:', e);
        }
    }
    fetchTickers();
    setInterval(fetchTickers, 4000);

    // 3. Fetch Live Order Book Depth
    async function fetchOrderBook() {
        try {
            const res = await fetch(`/api/spot/${CURRENT_SYMBOL}/depth`);
            const data = await res.json();
            if (data.success) {
                renderOrderBook(data.bids || [], data.asks || []);
            }
        } catch (e) {
            console.warn('Depth fetch error:', e);
        }
    }

    function renderOrderBook(bids, asks) {
        const asksContainer = document.getElementById('orderBookAsks');
        const bidsContainer = document.getElementById('orderBookBids');

        let maxQty = 1;
        [...bids, ...asks].forEach(row => {
            const q = parseFloat(row[1]) || 0;
            if (q > maxQty) maxQty = q;
        });

        // Render Asks (Sell)
        let asksHtml = '';
        asks.slice(0, 10).reverse().forEach(row => {
            const p = parseFloat(row[0]);
            const q = parseFloat(row[1]);
            const pct = Math.min(100, Math.round((q / maxQty) * 100));
            asksHtml += `
                <div class="ob-row" onclick="selectObPrice(${p})">
                    <span class="text-ask">${p.toFixed(PAIR_CONFIG.price_precision)}</span>
                    <span style="text-align: right; color: #cbd5e1;">${q.toFixed(PAIR_CONFIG.amount_precision)}</span>
                    <span style="text-align: right; color: #64748b;">${(p * q).toFixed(2)}</span>
                    <div class="ob-bg ob-bg-ask" style="width: ${pct}%;"></div>
                </div>
            `;
        });
        asksContainer.innerHTML = asksHtml;

        // Render Bids (Buy)
        let bidsHtml = '';
        bids.slice(0, 10).forEach(row => {
            const p = parseFloat(row[0]);
            const q = parseFloat(row[1]);
            const pct = Math.min(100, Math.round((q / maxQty) * 100));
            bidsHtml += `
                <div class="ob-row" onclick="selectObPrice(${p})">
                    <span class="text-bid">${p.toFixed(PAIR_CONFIG.price_precision)}</span>
                    <span style="text-align: right; color: #cbd5e1;">${q.toFixed(PAIR_CONFIG.amount_precision)}</span>
                    <span style="text-align: right; color: #64748b;">${(p * q).toFixed(2)}</span>
                    <div class="ob-bg ob-bg-bid" style="width: ${pct}%;"></div>
                </div>
            `;
        });
        bidsContainer.innerHTML = bidsHtml;
    }
    fetchOrderBook();
    setInterval(fetchOrderBook, 3000);

    window.selectObPrice = function(p) {
        if (activeType === 'limit') {
            document.getElementById('orderPriceInput').value = p;
            calcOrderTotal();
        }
    };

    // 4. Fetch Market Trades
    async function fetchTrades() {
        try {
            const res = await fetch(`/api/spot/${CURRENT_SYMBOL}/trades`);
            const data = await res.json();
            if (data.success && Array.isArray(data.data)) {
                const container = document.getElementById('marketTradesList');
                let html = '';
                data.data.slice(0, 15).forEach(t => {
                    const isBuy = t.side === 'buy';
                    html += `
                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; padding: 3px 12px; font-size: 11px; font-family: monospace;">
                            <span class="${isBuy ? 'text-bid' : 'text-ask'}">${t.price.toFixed(PAIR_CONFIG.price_precision)}</span>
                            <span style="text-align: right; color: #cbd5e1;">${t.amount.toFixed(PAIR_CONFIG.amount_precision)}</span>
                            <span style="text-align: right; color: #64748b;">${t.time}</span>
                        </div>
                    `;
                });
                container.innerHTML = html;
            }
        } catch (e) {
            console.warn('Trades fetch error:', e);
        }
    }
    fetchTrades();
    setInterval(fetchTrades, 4000);

    // Helper to get Kryvex JWT Auth Token from localStorage or sessionStorage
    function getAuthToken() {
        return localStorage.getItem('kryvex_access_token') 
            || localStorage.getItem('access_token') 
            || localStorage.getItem('token') 
            || sessionStorage.getItem('kryvex_access_token')
            || sessionStorage.getItem('access_token') 
            || sessionStorage.getItem('token') 
            || '';
    }

    // 5. User Spot Balances & Tables
    async function fetchBalances() {
        const token = getAuthToken();
        if (!token) return;

        try {
            const res = await fetch('/api/spot/balances', {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success && data.data) {
                userBalances = data.data;
                updateAvailableBalanceDisplay();
                renderAssetsTable(data.data);
            }
        } catch (e) {
            console.warn('Balances fetch error:', e);
        }
    }

    function updateAvailableBalanceDisplay() {
        const availEl = document.getElementById('availBalanceText');
        if (activeSide === 'buy') {
            const usdtFree = userBalances['USDT'] ? userBalances['USDT'].free : 0;
            availEl.textContent = usdtFree.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' USDT';
        } else {
            const base = PAIR_CONFIG.base;
            const cryptoFree = userBalances[base] ? userBalances[base].free : 0;
            availEl.textContent = cryptoFree.toLocaleString(undefined, {minimumFractionDigits: PAIR_CONFIG.amount_precision, maximumFractionDigits: PAIR_CONFIG.amount_precision}) + ' ' + base;
        }
    }

    function renderAssetsTable(balances) {
        const tbody = document.getElementById('spotAssetsTableBody');
        let html = '';
        for (const k in balances) {
            const item = balances[k];
            const estUsdt = k === 'USDT' ? item.total : (item.total * (currentPrice || 1));
            html += `
                <tr>
                    <td><strong style="color: #fff;">${item.asset}</strong></td>
                    <td style="color: #34d399;">${item.free.toLocaleString(undefined, {maximumFractionDigits: 6})}</td>
                    <td style="color: #f87171;">${item.locked.toLocaleString(undefined, {maximumFractionDigits: 6})}</td>
                    <td><strong>${item.total.toLocaleString(undefined, {maximumFractionDigits: 6})}</strong></td>
                    <td style="color: #94a3b8;">$${estUsdt.toFixed(2)}</td>
                </tr>
            `;
        }
        tbody.innerHTML = html || '<tr><td colspan="5" style="text-align: center;">No assets.</td></tr>';
    }

    // 6. User Orders & Trade History
    async function fetchOrders() {
        const token = getAuthToken();
        if (!token) return;

        try {
            // Open Orders
            const openRes = await fetch(`/api/spot/orders/open?symbol=${CURRENT_SYMBOL}`, {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            });
            const openData = await openRes.json();
            if (openData.success) {
                renderOpenOrders(openData.data || []);
            }

            // Order History
            const histRes = await fetch(`/api/spot/orders/history?symbol=${CURRENT_SYMBOL}`, {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            });
            const histData = await histRes.json();
            if (histData.success) {
                renderOrderHistory(histData.data || []);
            }

            // Trade History
            const trdRes = await fetch(`/api/spot/trades/history?symbol=${CURRENT_SYMBOL}`, {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            });
            const trdData = await trdRes.json();
            if (trdData.success) {
                renderTradeHistory(trdData.data || []);
            }
        } catch (e) {
            console.warn('Orders fetch error:', e);
        }
    }

    function renderOpenOrders(orders) {
        document.getElementById('openOrdersCount').textContent = orders.length;
        const tbody = document.getElementById('openOrdersTableBody');
        if (!orders.length) {
            tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; color: #64748b; padding: 24px;">No open orders found.</td></tr>';
            return;
        }

        let html = '';
        orders.forEach(o => {
            const isBuy = o.side === 'buy';
            const date = new Date(o.created_at).toLocaleTimeString();
            html += `
                <tr>
                    <td style="color: #64748b;">${date}</td>
                    <td><strong>${o.symbol}</strong></td>
                    <td style="text-transform: uppercase;">${o.type}</td>
                    <td class="${isBuy ? 'text-bid' : 'text-ask'}" style="font-weight: 700; text-transform: uppercase;">${o.side}</td>
                    <td>$${parseFloat(o.price).toFixed(PAIR_CONFIG.price_precision)}</td>
                    <td>${parseFloat(o.amount).toFixed(PAIR_CONFIG.amount_precision)}</td>
                    <td>${parseFloat(o.filled_amount || 0).toFixed(PAIR_CONFIG.amount_precision)}</td>
                    <td>$${parseFloat(o.cost).toFixed(2)}</td>
                    <td>
                        <button class="btn-cancel-order" onclick="cancelSpotOrder('${o.order_uid}')">Cancel</button>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function renderOrderHistory(orders) {
        const tbody = document.getElementById('orderHistoryTableBody');
        if (!orders.length) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #64748b; padding: 24px;">No order history.</td></tr>';
            return;
        }

        let html = '';
        orders.forEach(o => {
            const isBuy = o.side === 'buy';
            const date = new Date(o.created_at).toLocaleString();
            const badgeClass = o.status === 'filled' ? 'badge-filled' : (o.status === 'cancelled' ? 'badge-cancelled' : 'badge-open');
            html += `
                <tr>
                    <td style="color: #64748b;">${date}</td>
                    <td><strong>${o.symbol}</strong></td>
                    <td style="text-transform: uppercase;">${o.type}</td>
                    <td class="${isBuy ? 'text-bid' : 'text-ask'}" style="font-weight: 700; text-transform: uppercase;">${o.side}</td>
                    <td>$${parseFloat(o.price).toFixed(PAIR_CONFIG.price_precision)}</td>
                    <td>${parseFloat(o.amount).toFixed(PAIR_CONFIG.amount_precision)}</td>
                    <td><span class="badge-status ${badgeClass}">${o.status}</span></td>
                    <td>$${parseFloat(o.fee || 0).toFixed(4)}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    function renderTradeHistory(trades) {
        const tbody = document.getElementById('tradeHistoryTableBody');
        if (!trades.length) {
            tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #64748b; padding: 24px;">No trades executed yet.</td></tr>';
            return;
        }

        let html = '';
        trades.forEach(t => {
            const isBuy = t.side === 'buy';
            const date = new Date(t.executed_at).toLocaleString();
            html += `
                <tr>
                    <td style="color: #64748b;">${date}</td>
                    <td><strong>${t.symbol}</strong></td>
                    <td class="${isBuy ? 'text-bid' : 'text-ask'}" style="font-weight: 700; text-transform: uppercase;">${t.side}</td>
                    <td>$${parseFloat(t.price).toFixed(PAIR_CONFIG.price_precision)}</td>
                    <td>${parseFloat(t.amount).toFixed(PAIR_CONFIG.amount_precision)}</td>
                    <td>$${parseFloat(t.cost).toFixed(2)}</td>
                    <td style="color: #64748b;">$${parseFloat(t.fee || 0).toFixed(4)}</td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    window.cancelSpotOrder = async function(orderUid) {
        const token = getAuthToken();
        if (!token) return;

        try {
            const res = await fetch(`/api/spot/orders/${orderUid}/cancel`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                fetchBalances();
                fetchOrders();
            } else {
                alert(data.message || 'Failed to cancel order.');
            }
        } catch (e) {
            alert('Cancel error: ' + e.message);
        }
    };

    async function processLimitFills() {
        const token = getAuthToken();
        if (!token || currentPrice <= 0) return;

        const prices = {};
        prices[CURRENT_SYMBOL] = currentPrice;

        try {
            const res = await fetch('/api/spot/orders/process-fills', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ prices })
            });
            const data = await res.json();
            if (data.success && data.filled_count > 0) {
                fetchBalances();
                fetchOrders();
            }
        } catch (e) {}
    }

    fetchBalances();
    fetchOrders();

    // 7. Order Form Interactions
    const btnSideBuy = document.getElementById('btnSideBuy');
    const btnSideSell = document.getElementById('btnSideSell');
    const btnTypeLimit = document.getElementById('btnTypeLimit');
    const btnTypeMarket = document.getElementById('btnTypeMarket');
    const limitPriceGroup = document.getElementById('limitPriceGroup');
    const btnSubmitOrder = document.getElementById('btnSubmitOrder');
    const orderPriceInput = document.getElementById('orderPriceInput');
    const orderAmountInput = document.getElementById('orderAmountInput');

    function switchBottomTab(tabId) {
        document.querySelectorAll('.spot-tab-btn').forEach(b => {
            if (b.getAttribute('data-tab') === tabId) {
                b.classList.add('active');
            } else {
                b.classList.remove('active');
            }
        });
        document.querySelectorAll('.tab-pane').forEach(p => p.style.display = 'none');
        const targetPane = document.getElementById('tabContent_' + tabId);
        if (targetPane) targetPane.style.display = 'block';
    }

    btnSideBuy.addEventListener('click', () => {
        activeSide = 'buy';
        btnSideBuy.classList.add('active');
        btnSideSell.classList.remove('active');
        btnSubmitOrder.className = 'spot-submit-btn buy';
        btnSubmitOrder.textContent = `Buy ${PAIR_CONFIG.base}`;
        if (activeType === 'limit' && currentPrice > 0) {
            orderPriceInput.value = (currentPrice * 0.98).toFixed(PAIR_CONFIG.price_precision);
        }
        updateAvailableBalanceDisplay();
        calcOrderTotal();
    });

    btnSideSell.addEventListener('click', () => {
        activeSide = 'sell';
        btnSideSell.classList.add('active');
        btnSideBuy.classList.remove('active');
        btnSubmitOrder.className = 'spot-submit-btn sell';
        btnSubmitOrder.textContent = `Sell ${PAIR_CONFIG.base}`;
        if (activeType === 'limit' && currentPrice > 0) {
            orderPriceInput.value = (currentPrice * 1.02).toFixed(PAIR_CONFIG.price_precision);
        }
        updateAvailableBalanceDisplay();
        calcOrderTotal();
    });

    btnTypeLimit.addEventListener('click', () => {
        activeType = 'limit';
        btnTypeLimit.classList.add('active');
        btnTypeMarket.classList.remove('active');
        limitPriceGroup.style.display = 'flex';
        if (currentPrice > 0) {
            orderPriceInput.value = activeSide === 'buy' 
                ? (currentPrice * 0.98).toFixed(PAIR_CONFIG.price_precision)
                : (currentPrice * 1.02).toFixed(PAIR_CONFIG.price_precision);
        }
        calcOrderTotal();
    });

    btnTypeMarket.addEventListener('click', () => {
        activeType = 'market';
        btnTypeMarket.classList.add('active');
        btnTypeLimit.classList.remove('active');
        limitPriceGroup.style.display = 'none';
        calcOrderTotal();
    });

    function calcOrderTotal() {
        const price = activeType === 'market' ? currentPrice : (parseFloat(orderPriceInput.value) || 0);
        const amount = parseFloat(orderAmountInput.value) || 0;
        const total = price * amount;
        const fee = total * 0.001;

        document.getElementById('orderTotalCost').textContent = total.toFixed(2) + ' USDT';
        document.getElementById('orderEstFee').textContent = fee.toFixed(4) + ' USDT';
    }

    orderPriceInput.addEventListener('input', calcOrderTotal);
    orderAmountInput.addEventListener('input', calcOrderTotal);

    // Percentage buttons (25%, 50%, 75%, 100%)
    document.querySelectorAll('.spot-pct-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const pct = parseFloat(btn.getAttribute('data-pct')) || 0;
            const price = activeType === 'market' ? currentPrice : (parseFloat(orderPriceInput.value) || currentPrice);
            if (price <= 0) return;

            if (activeSide === 'buy') {
                const usdtFree = userBalances['USDT'] ? userBalances['USDT'].free : 0;
                const spendUsdt = usdtFree * pct * 0.999; // reserve for fee
                const amt = spendUsdt / price;
                orderAmountInput.value = amt > 0 ? amt.toFixed(PAIR_CONFIG.amount_precision) : 0;
            } else {
                const cryptoFree = userBalances[PAIR_CONFIG.base] ? userBalances[PAIR_CONFIG.base].free : 0;
                const amt = cryptoFree * pct;
                orderAmountInput.value = amt > 0 ? amt.toFixed(PAIR_CONFIG.amount_precision) : 0;
            }
            calcOrderTotal();
        });
    });

    // 8. Place Order Submit
    btnSubmitOrder.addEventListener('click', async () => {
        const token = getAuthToken();
        if (!token) {
            alert('Please login to place spot orders.');
            window.location.href = '/login';
            return;
        }

        const price = activeType === 'market' ? currentPrice : parseFloat(orderPriceInput.value);
        const amount = parseFloat(orderAmountInput.value);

        if (!amount || amount <= 0) {
            alert('Please enter a valid amount.');
            return;
        }

        if (activeType === 'limit' && (!price || price <= 0)) {
            alert('Please enter a valid limit price.');
            return;
        }

        btnSubmitOrder.disabled = true;
        btnSubmitOrder.textContent = 'Processing...';

        try {
            const res = await fetch('/api/spot/orders', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    symbol: CURRENT_SYMBOL,
                    side: activeSide,
                    type: activeType,
                    price: price,
                    amount: amount,
                    market_price: currentPrice
                })
            });

            const data = await res.json();
            if (data.success) {
                alert(data.message || 'Spot order placed successfully!');
                orderAmountInput.value = '';
                calcOrderTotal();
                await fetchBalances();
                await fetchOrders();

                // Automatically switch to appropriate tab
                if (activeType === 'limit') {
                    switchBottomTab('openOrders');
                } else {
                    switchBottomTab('tradeHistory');
                }
            } else {
                alert(data.message || 'Failed to place order.');
            }
        } catch (e) {
            alert('Order placement error: ' + e.message);
        } finally {
            btnSubmitOrder.disabled = false;
            btnSubmitOrder.textContent = `${activeSide === 'buy' ? 'Buy' : 'Sell'} ${PAIR_CONFIG.base}`;
        }
    });

    // 9. Bottom Tabs Switcher
    document.querySelectorAll('.spot-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.getAttribute('data-tab');
            switchBottomTab(tabId);
        });
    });

    // 10. Pair Dropdown Search & Toggle
    const dropdownBtn = document.getElementById('spotPairDropdownBtn');
    const dropdown = document.getElementById('spotPairDropdown');
    const searchInput = document.getElementById('pairSearchInput');

    dropdownBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    });

    document.addEventListener('click', () => {
        dropdown.style.display = 'none';
    });

    dropdown.addEventListener('click', (e) => {
        e.stopPropagation();
    });

    searchInput.addEventListener('input', () => {
        const q = searchInput.value.toUpperCase();
        document.querySelectorAll('.spot-pair-item').forEach(item => {
            const sym = item.getAttribute('data-symbol') || '';
            item.style.display = sym.includes(q) ? 'flex' : 'none';
        });
    });
});
</script>
@endpush

