@extends('layouts.app')

@section('title', strtoupper($selectedSymbol) . ' Options - Kryvex')

@push('styles')
    @vite(['resources/css/pages/futures.css', 'resources/css/pages/options.css'])
@endpush

@push('scripts')
    @vite('resources/js/pages/options.js')
@endpush

@section('content')
    <div class="options-page bitpro-theme" data-selected-symbol="{{ $selectedSymbol }}">
        <!-- Top Asset Bar / BitPro Header Strip -->
        <section class="bitpro-options-header kv-card">
            <div class="header-left-symbol-group">
                <div class="symbol-badge-icon" id="optHeaderCoinLogo">
                    @php
                        $coinLogos = [
                            'BTCUSDT' => 'https://assets.coingecko.com/coins/images/1/large/bitcoin.png',
                            'ETHUSDT' => 'https://assets.coingecko.com/coins/images/279/large/ethereum.png',
                            'SOLUSDT' => 'https://assets.coingecko.com/coins/images/4128/large/solana.png',
                            'XAUUSDT' => '/images/coins/gold.svg',
                        ];
                        $cleanSym = strtoupper($selectedSymbol);
                        $currentLogo = $coinLogos[$cleanSym] ?? 'https://assets.coingecko.com/coins/images/1/large/bitcoin.png';
                        $currentBase = str_replace(['USDT', '-USD', 'USD'], '', $cleanSym);
                    @endphp
                    <img src="{{ $currentLogo }}" alt="{{ $currentBase }}" id="optCoinLogoImg">
                </div>
                <div class="symbol-dropdown-select-wrap">
                    <div class="symbol-title-row">
                        <select id="optionsSymbolSelector" class="opt-symbol-select" onchange="window.location.href='/options/' + this.value">
                            @foreach ($allowedSymbols as $symbol)
                                <option value="{{ strtolower($symbol) }}" {{ $selectedSymbol === $symbol ? 'selected' : '' }}>
                                    {{ str_replace('USDT', '-USD', $symbol) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <span class="symbol-sub-label">Options</span>
                </div>
                <div class="price-block">
                    <strong class="live-big-price" id="optionIndexPrice">--</strong>
                    <span class="live-change-badge" id="optionChange24h">--</span>
                </div>
            </div>

            <div class="header-stats-strip">
                <div class="stat-col">
                    <span class="stat-lbl">Mark Price</span>
                    <strong class="stat-val" id="optHeaderMarkPrice">--</strong>
                </div>
                <div class="stat-col">
                    <span class="stat-lbl">24h Change</span>
                    <strong class="stat-val" id="optHeader24hChange">--</strong>
                </div>
                <div class="stat-col">
                    <span class="stat-lbl">24h High</span>
                    <strong class="stat-val" id="optHeader24hHigh">--</strong>
                </div>
                <div class="stat-col">
                    <span class="stat-lbl">24h Low</span>
                    <strong class="stat-val" id="optHeader24hLow">--</strong>
                </div>
                <div class="stat-col">
                    <span class="stat-lbl">24h Volume (<span class="base-asset-label">BTC</span>)</span>
                    <strong class="stat-val" id="optHeaderVolumeBase">--</strong>
                </div>
                <div class="stat-col">
                    <span class="stat-lbl">24h Volume (USD)</span>
                    <strong class="stat-val" id="optHeaderVolumeQuote">--</strong>
                </div>
                <div class="stat-col">
                    <span class="stat-lbl">Open Interest (<span class="base-asset-label">BTC</span>)</span>
                    <strong class="stat-val" id="optHeaderOpenInterest">--</strong>
                </div>
            </div>

            <div class="header-right-actions">
                <button type="button" class="btn-guide-pill" id="btnOptionsGuide">
                    Options Guide
                </button>
                <button type="button" class="btn-greeks-pill" id="btnOptionsGreeks">
                    Greeks
                </button>
                


                <div class="mobile-page-currency-toggle">
                    <div class="kv-currency-toggle-wrap">
                        <button type="button" class="kv-currency-btn" data-currency-toggle="INR">₹ INR</button>
                        <button type="button" class="kv-currency-btn is-active" data-currency-toggle="USDT">$ USD</button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Top Navigation Tabs Bar -->
        <div class="bitpro-nav-tabs-bar">
            <div class="nav-tab-links">
                <button type="button" class="bitpro-tab-link is-active" data-top-tab="chain">Options Chain</button>
                <button type="button" class="bitpro-tab-link" data-top-tab="positions">Positions <span id="topPosBadge"></span></button>
                <button type="button" class="bitpro-tab-link" data-top-tab="openOrders">Open Orders <span id="topOrdersBadge"></span></button>
                <button type="button" class="bitpro-tab-link" data-top-tab="orderHistory">Order History</button>
                <button type="button" class="bitpro-tab-link" data-top-tab="tradeHistory">Trade History</button>
            </div>
        </div>

        <!-- Main Options Layout (Left: Swappable Views, Right: Order Ticket) -->
        <section class="bitpro-main-layout">
            <!-- Left Side: Main View Panels (Swappable by Top Tabs) -->
            <div class="bitpro-main-view-container">
                <!-- VIEW 1: OPTIONS CHAIN -->
                <div class="bitpro-view-pane is-active" id="viewOptionsChain">
                    <!-- Options Chain Controls Filter Bar -->
                    <div class="bitpro-chain-filter-bar">
                        <div class="filter-left-group">
                            <div class="filter-symbol-select-wrap">
                                <select id="filterSymbolSelector" class="bitpro-symbol-filter-select" onchange="window.location.href='/options/' + this.value">
                                    @foreach ($allowedSymbols as $symbol)
                                        <option value="{{ strtolower($symbol) }}" {{ $selectedSymbol === $symbol ? 'selected' : '' }}>
                                            {{ str_replace('USDT', '-USD', $symbol) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-expiry-picker-btn" id="btnExpiryDatePicker">
                                <span class="cal-icon">📅</span>
                                <span id="selectedExpiryDisplay">Select Expiry</span>
                            </div>
                            <div class="filter-expiry-select-wrap">
                                <select id="expirySelect" class="bitpro-expiry-select">
                                    <option value="">Loading expiries...</option>
                                </select>
                            </div>
                            <div class="spot-price-badge">
                                <span class="spot-lbl">Spot Price</span>
                                <strong class="spot-val" id="optFilterSpotPrice">--</strong>
                            </div>
                        </div>

                        {{-- <div class="filter-right-group">
                            <div class="view-mode-toggle-group">
                                <button type="button" class="view-mode-btn is-active" data-view-mode="classic">Classic</button>
                                <button type="button" class="view-mode-btn" data-view-mode="analytics">Analytics</button>
                            </div>
                            <button type="button" class="filter-settings-btn" id="btnOptionsSettings" title="Options Settings">
                                ⚙
                            </button>
                        </div> --}}
                    </div>

                    <!-- Options Chain Table -->
                    <div class="bitpro-chain-panel kv-card">
                        <div class="bitpro-chain-table-container">
                            <table class="bitpro-options-chain-table">
                                <thead>
                                    <!-- Dual Header Banner: Calls (Green) | Expiry/Strike | Puts (Red) -->
                                    <tr class="dual-banner-row">
                                        <th colspan="6" class="banner-calls-head">Calls</th>
                                        <th class="banner-strike-head" id="chainExpiryTitle">
                                            <span class="strk-title">Strike</span>
                                            <span class="strk-exp-sub" id="chainExpiryCenter">Expiry: --</span>
                                        </th>
                                        <th colspan="6" class="banner-puts-head">Puts</th>
                                    </tr>
                                    <!-- Column Names -->
                                    <tr class="chain-col-header-row">
                                        <th class="col-oi">OI (<span class="base-asset-label">BTC</span>)</th>
                                        <th class="col-vol">Volume (<span class="base-asset-label">BTC</span>)</th>
                                        <th class="col-bid">Bid<br><small>(Price / IV)</small></th>
                                        <th class="col-ask">Ask<br><small>(Price / IV)</small></th>
                                        <th class="col-last">Last Price<br><small>(Price / IV)</small></th>
                                        <th class="col-iv">IV</th>
                                        <th class="col-center-strike">Strike</th>
                                        <th class="col-iv">IV</th>
                                        <th class="col-last">Last Price<br><small>(Price / IV)</small></th>
                                        <th class="col-bid">Bid<br><small>(Price / IV)</small></th>
                                        <th class="col-ask">Ask<br><small>(Price / IV)</small></th>
                                        <th class="col-vol">Volume (<span class="base-asset-label">BTC</span>)</th>
                                        <th class="col-oi">OI (<span class="base-asset-label">BTC</span>)</th>
                                    </tr>
                                </thead>
                                <tbody id="optionsChainBody">
                                    <tr>
                                        <td colspan="13" class="empty-row">Loading options chain...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="chain-underlying-footer-note">
                            <span>Underlying: <strong id="optionUnderlying">{{ str_replace('USDT', '-USD', $selectedSymbol) }} Index</strong> <span class="info-icon">ⓘ</span></span>
                        </div>
                    </div>
                </div>

                <!-- VIEW 2: POSITIONS -->
                <div class="bitpro-view-pane kv-card" id="viewOptionsPositions" hidden>
                    <div class="panel-section-head">
                        <h3>Open Positions (<span id="optionsPosCount">0</span>)</h3>
                        <button type="button" id="optionsCloseAllButton" class="close-all-options-btn" data-account-action>
                            Close All
                        </button>
                    </div>
                    <div class="options-table-wrap">
                        <table class="options-data-table">
                            <thead>
                                <tr>
                                    <th>Contract</th>
                                    <th>Side</th>
                                    <th>Entry Price</th>
                                    <th>Qty</th>
                                    <th>Margin</th>
                                    <th>P&amp;L</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="optionsPositionsBody">
                                <tr><td colspan="8" class="empty-row">No open option positions.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- VIEW 3: OPEN ORDERS -->
                <div class="bitpro-view-pane kv-card" id="viewOptionsOpenOrders" hidden>
                    <div class="panel-section-head">
                        <h3>Open Orders (<span id="optionsOpenOrdersCount">0</span>)</h3>
                    </div>
                    <div class="options-table-wrap">
                        <table class="options-data-table">
                            <thead>
                                <tr>
                                    <th>Contract</th>
                                    <th>Side</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Margin</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="optionsOpenOrdersBody">
                                <tr><td colspan="8" class="empty-row">No open orders.</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- VIEW 4: ORDER HISTORY -->
                <div class="bitpro-view-pane kv-card" id="viewOptionsOrderHistory" hidden>
                    <div class="panel-section-head">
                        <h3>Order History</h3>
                    </div>
                    <div class="options-table-wrap">
                        <table class="options-data-table">
                            <thead>
                                <tr>
                                    <th>Contract</th>
                                    <th>Side</th>
                                    <th>Type</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Margin</th>
                                    <th>Fee</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody id="optionsOrderHistoryBody">
                                <tr><td colspan="9" class="empty-row">No order history found.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="wallet-pagination-wrap" id="optionsOrderHistoryPagination" hidden></div>
                </div>

                <!-- VIEW 5: TRADE HISTORY -->
                <div class="bitpro-view-pane kv-card" id="viewOptionsTradeHistory" hidden>
                    <div class="panel-section-head">
                        <h3>Trade History</h3>
                    </div>
                    <div class="options-table-wrap">
                        <table class="options-data-table">
                            <thead>
                                <tr>
                                    <th>Contract</th>
                                    <th>Side</th>
                                    <th>Price</th>
                                    <th>Qty</th>
                                    <th>Premium</th>
                                    <th>Fee</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="optionsTradeHistoryBody">
                                <tr><td colspan="8" class="empty-row">No trade history found.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="wallet-pagination-wrap" id="optionsTradeHistoryPagination" hidden></div>
                </div>
            </div>

            <!-- Right Side: Order Ticket Panel -->
            <aside class="bitpro-order-panel kv-card" id="optionOrderCard">
                <!-- Top Buy / Sell Toggle Tabs -->
                <div class="bitpro-side-toggle-tabs">
                    <button type="button" class="side-tab-btn buy-tab is-active" data-side="buy">Buy</button>
                    <button type="button" class="side-tab-btn sell-tab" data-side="sell">Sell</button>
                </div>

                {{-- <!-- Sub Type Tabs: Options | Strategy Builder -->
                <div class="bitpro-sub-ticket-tabs">
                    <button type="button" class="sub-tab-btn is-active">Options</button>
                    <button type="button" class="sub-tab-btn">Strategy Builder <span class="badge-new">New</span></button>
                </div> --}}

                <!-- Order Form -->
                <form id="optionOrderForm" class="bitpro-order-form" novalidate>
                    <input type="hidden" name="side" id="optionOrderSide" value="buy">

                    <!-- Instrument Select Box -->
                    <div class="form-group">
                        <label class="form-label">Instrument</label>
                        <div class="instrument-select-display" id="selectedInstrumentBox">
                            <span id="selectedInstrumentLabel">{{ str_replace('USDT', '-USD', $selectedSymbol) }} Option</span>
                        </div>
                    </div>

                    <!-- Order Type -->
                    <div class="form-group">
                        <label class="form-label">Order Type</label>
                        <select name="order_type" id="optionOrderType" class="bitpro-input-select">
                            <option value="limit" selected>Limit</option>
                            <option value="market">Market</option>
                        </select>
                    </div>

                    <!-- Price (USD) -->
                    <div class="form-group" id="optionPriceField">
                        <label class="form-label">Price (USD)</label>
                        <div class="stepper-input-group">
                            <button type="button" class="stepper-btn btn-step-down" id="btnPriceStepDown">−</button>
                            <input type="number" name="price" id="optionOrderPrice" placeholder="0.0" inputmode="decimal" step="any" min="0.00000001" class="stepper-input">
                            <button type="button" class="stepper-btn btn-step-up" id="btnPriceStepUp">+</button>
                        </div>
                    </div>

                    <!-- Contracts -->
                    <div class="form-group">
                        <label class="form-label">Contracts</label>
                        <div class="stepper-input-group">
                            <button type="button" class="stepper-btn btn-step-down" id="btnQtyStepDown">−</button>
                            <input type="number" name="quantity" id="optionOrderQuantity" placeholder="1" inputmode="decimal" step="any" min="0.00000001" value="1" class="stepper-input">
                            <button type="button" class="stepper-btn btn-step-up" id="btnQtyStepUp">+</button>
                        </div>
                    </div>

                    <!-- Percentage Slider -->
                    <div class="slider-group">
                        <input type="range" class="bitpro-range-slider" id="optionQuantitySlider" min="0" max="100" step="1" value="0">
                        <div class="slider-percentage-labels">
                            <span data-pct="0">0%</span>
                            <span data-pct="25">25%</span>
                            <span data-pct="50">50%</span>
                            <span data-pct="75">75%</span>
                            <span data-pct="100">100%</span>
                        </div>
                    </div>

                    <!-- Order Value display -->
                    <div class="order-metric-row">
                        <span class="metric-lbl">Order Value</span>
                        <strong class="metric-val" id="optionOrderValueDisplay">--</strong>
                    </div>

                    <!-- Checkboxes: Post Only, Reduce Only -->
                    {{-- <div class="options-checkboxes-row">
                        <label class="bitpro-checkbox-label">
                            <input type="checkbox" id="chkPostOnly" name="post_only">
                            <span>Post Only</span>
                        </label>
                        <label class="bitpro-checkbox-label">
                            <input type="checkbox" id="chkReduceOnly" name="reduce_only">
                            <span>Reduce Only</span>
                        </label>
                    </div> --}}

                    <!-- Submit Button -->
                    <button type="submit" class="bitpro-action-submit-btn buy" id="optionPlaceOrderButton" data-account-action>
                        Buy Call
                    </button>

                    <!-- Max Cost & Available Balance Strip -->
                    <div class="ticket-footer-metrics">
                        <div class="metric-pair">
                            <span class="lbl">Max Cost</span>
                            <strong class="val" id="optionMaxCostDisplay">--</strong>
                        </div>
                        <div class="metric-pair">
                            <span class="lbl">Available Balance</span>
                            <strong class="val" id="optionsAvailableBalance" data-sensitive-balance>Login required</strong>
                        </div>
                    </div>

                    <p class="option-order-message" id="optionOrderMessage">Select a contract from chain to trade.</p>
                </form>

                <!-- Hidden inputs/elements for JS state binding -->
                <div style="display:none;">
                    <span id="chainTitle">{{ $selectedSymbol }}</span>
                    <span id="chainStatus">Page ready</span>
                    <strong id="chainUnderlyingPrice">--</strong>
                    <strong id="chainTimeToExpiry">--</strong>
                    <strong id="optionPremiumEstimate">--</strong>
                    <strong id="optionsMainBalance">--</strong>
                    <strong id="optionsLockedBalance">--</strong>
                    <strong id="optionsMobileEquity">--</strong>
                    <strong id="optionsMobileUpnl">--</strong>
                    <strong id="optionsMobileMarginBal">--</strong>
                    <strong id="optionsMobileAvail">--</strong>
                    <h2 id="contractTitle">No contract selected</h2>
                    <strong id="contractMarkPrice">--</strong>
                    <strong id="contractIv">--</strong>
                    <strong id="contractDelta">--</strong>
                    <strong id="contractGamma">--</strong>
                    <strong id="contractTheta">--</strong>
                    <strong id="contractVega">--</strong>
                    <p id="optionOrderTicketCategory">Order Ticket</p>
                    <h2 id="optionOrderTicketSymbol">Buy / Sell Option</h2>
                    <small id="optionOrderPriceHint">Market uses live mark price</small>
                    <button type="button" id="btnCloseOptionsMobileOrderPanel">&times;</button>
                    <div id="optionsDrawerHandleBar"></div>
                    <!-- Legacy table placeholders to avoid null JS errors -->
                    <table style="display:none;">
                        <thead id="optionsBottomHead"></thead>
                        <tbody id="optionsBottomBody"></tbody>
                    </table>
                    <div id="optionsPositionsCardsList"></div>
                    <div id="optionsPagination"></div>
                </div>
            </aside>
        </section>

        <!-- Bottom Summary Stats Bar -->
        <section class="bitpro-bottom-summary-card kv-card">
            <div class="summary-stat-item">
                <span class="sum-lbl">Total Account Value</span>
                <strong class="sum-val" id="optBottomTotalAccount">--</strong>
            </div>
            <div class="summary-stat-item">
                <span class="sum-lbl">Unrealized P&L</span>
                <strong class="sum-val" id="optBottomUnrealizedPnl">--</strong>
            </div>
            <div class="summary-stat-item">
                <span class="sum-lbl">Options Buying Power</span>
                <strong class="sum-val" id="optBottomBuyingPower">--</strong>
            </div>
            <div class="summary-stat-item">
                <span class="sum-lbl">Used Margin</span>
                <strong class="sum-val" id="optBottomUsedMargin">--</strong>
            </div>
            <div class="summary-stat-item">
                <span class="sum-lbl">Open Interest (All)</span>
                <strong class="sum-val" id="optBottomTotalOi">--</strong>
            </div>
            <div class="summary-stat-item">
                <span class="sum-lbl">24h Volume</span>
                <strong class="sum-val" id="optBottom24hVol">--</strong>
            </div>
        </section>

        <!-- Wallet Modal Backdrop -->
        <div class="wallet-modal-backdrop" id="walletModalBackdrop" hidden>
            <div class="wallet-modal" role="dialog" aria-modal="true" aria-labelledby="walletModalTitle">
                <div class="wallet-modal-head">
                    <div>
                        <p>Options Wallet</p>
                        <h2 id="walletModalTitle">Add Amount</h2>
                    </div>
                    <button type="button" id="closeWalletModal">×</button>
                </div>

                <form id="walletModalForm">
                    <input type="hidden" id="walletActionInput" value="deposit">

                    <div class="wallet-currency-tabs" id="optionsModalCurrencyTabs" style="display:none !important;">
                        <button type="button" class="wallet-cur-tab is-active" data-tab="usdt" id="optionsModalTabUsdt">$ USD</button>
                        <button type="button" class="wallet-cur-tab" data-tab="inr" id="optionsModalTabInr">₹ INR</button>
                    </div>

                    <div id="optionsRateBar" style="display:none;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:0.82rem;color:rgba(255,255,255,0.85);">
                        <span>Current Rate: </span><strong id="optionsRateVal" style="color:#22c55e;">1 USD = ₹85.00</strong>
                    </div>

                    <div id="optionsInrBankBox" style="display:none;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:12px;margin-bottom:12px;font-size:0.82rem;">
                        <div style="color:var(--kv-muted);margin-bottom:4px;font-size:0.75rem;text-transform:uppercase;">Bank Deposit Account</div>
                        <div id="optionsInrBankInfo" style="color:rgba(255,255,255,0.85);line-height:1.6;">Loading bank details...</div>
                    </div>

                    <label id="optionWalletNetworkRow">
                        Network
                        <select id="optionWalletNetworkSelect">
                            <option value="">Loading networks...</option>
                        </select>
                    </label>
                    <div class="wallet-modal-address-box" id="optionWalletDepositAddressBox">
                        <div><span>Deposit Address</span><button type="button" id="optionWalletCopyDepositAddress">Copy</button></div>
                        <strong id="optionWalletDepositAddressText">Select network to view address.</strong>
                        <small id="optionWalletDepositAddressRule">Only send funds on selected network.</small>
                    </div>

                    <label id="optionWalletWithdrawAddressRow" hidden>
                        Your Withdrawal Address
                        <input type="text" id="optionWalletWithdrawAddressInput" placeholder="Select network to view address rule">
                    </label>
                    <small class="wallet-modal-rule" id="optionWalletWithdrawAddressRule" hidden></small>

                    <div id="optionsInrWithdrawFields" style="display:none;margin-bottom:12px;">
                        <div id="optionsSavedBankBanner"></div>
                    </div>

                    <label id="walletTransferAccountRow" hidden>
                        Trading Account
                        <select id="walletTransferAccountSelect"></select>
                    </label>

                    <label id="optionWalletTransferDirectionRow" hidden>
                        Transfer Direction
                        <select id="optionWalletTransferDirection">
                            <option value="main_to_trading">Main balance to Trading balance</option>
                            <option value="trading_to_main">Trading balance to Main balance</option>
                        </select>
                    </label>

                    <label>
                        <span id="optionsAmountLabel">Amount</span>
                        <div class="wallet-input-with-button">
                            <input type="number" id="walletAmountInput" placeholder="Enter amount" min="0.00000001" step="any" required>
                            <button type="button" class="wallet-input-max-btn" id="walletAmountMaxButton">Max</button>
                        </div>
                    </label>

                    <div id="optionsConversionBox" style="display:none;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:10px;margin-bottom:12px;font-size:0.83rem;">
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                            <span style="color:var(--kv-muted);" id="optionsConvFromLabel">From:</span>
                            <strong id="optionsConvFromVal" style="color:#fff;">--</strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;">
                            <span style="color:var(--kv-muted);" id="optionsConvToLabel">To:</span>
                            <strong id="optionsConvToVal" style="color:#22c55e;">--</strong>
                        </div>
                    </div>

                    <p id="walletModalMessage">Add funds instantly or submit withdrawal request for admin approval.</p>
                    <div class="wallet-withdraw-preview mini-withdraw-preview" id="optionsWalletWithdrawPreview" hidden>
                        <div><span>Requested Amount</span><strong id="optionsWithdrawPreviewAmount">--</strong></div>
                        <div><span>Withdrawal Charge</span><strong id="optionsWithdrawPreviewFee">--</strong></div>
                        <div><span>Platform Charge</span><strong id="optionsWithdrawPreviewPlatform">--</strong></div>
                        <div class="receive-row"><span>You Receive</span><strong id="optionsWithdrawPreviewNet">--</strong></div>
                        <div id="optionsWithdrawPreviewUsdtRow" style="display:flex; justify-content:space-between; margin-top:6px; padding-top:6px; border-top:1px dashed rgba(255,255,255,0.1); font-size:0.8rem;">
                            <span style="color:var(--kv-muted);">USD Equivalent Deducted</span>
                            <strong id="optionsWithdrawPreviewUsdt" style="color:#f59e0b;">--</strong>
                        </div>
                    </div>
                    <button type="submit" id="walletModalSubmit" class="kv-btn option-submit-btn" data-account-action>
                        Submit
                    </button>
                </form>
            </div>
        </div>

        <!-- Options Confirm Modal -->
        <div class="options-modal-backdrop" id="optionsConfirmModal" hidden>
            <div class="options-confirm-box" role="dialog" aria-modal="true" aria-labelledby="optionsConfirmTitle">
                <div class="confirm-icon">!</div>
                <h2 id="optionsConfirmTitle">Confirm action?</h2>
                <p id="optionsConfirmMessage">Please confirm this action.</p>
                <label class="options-confirm-preference" id="optionsConfirmPreferenceRow" hidden>
                    <input type="checkbox" id="optionsConfirmSkip">
                    <span id="optionsConfirmSkipLabel">Do not show this confirmation again for this action.</span>
                </label>
                <div class="confirm-actions">
                    <button type="button" id="optionsConfirmCancel" class="confirm-cancel">Cancel</button>
                    <button type="button" id="optionsConfirmOk" class="confirm-ok">Confirm</button>
                </div>
            </div>
        </div>

        <!-- Create Trading Account Modal -->
        <div class="options-modal-backdrop" id="createAccountModalBackdrop" hidden>
            <div class="options-modal-card create-account-card" style="background:#121621;border:1px solid rgba(255,255,255,0.1);border-radius:12px;padding:20px;max-width:400px;width:100%;margin:0 auto;color:#fff;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                    <h3 style="margin:0;font-size:1.1rem;color:#fff;">Create Trading Account</h3>
                    <button type="button" id="btnCloseCreateAccountModal" style="padding:2px 8px;font-size:1.1rem;background:none;border:none;color:var(--kv-muted);cursor:pointer;">&times;</button>
                </div>
                <form id="createAccountForm">
                    <label style="display:block;margin-bottom:14px;">
                        <span style="display:block;font-size:0.83rem;color:var(--kv-muted);margin-bottom:6px;">Account Name</span>
                        <input type="text" id="newAccountName" class="form-control" placeholder="e.g. Scalping, Swing, Bot" maxlength="40" required style="width:100%;padding:10px 12px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-size:0.9rem;">
                    </label>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button type="button" class="kv-btn" id="btnCancelCreateAccount" style="background:rgba(255,255,255,0.1);color:#fff;">Cancel</button>
                        <button type="submit" class="kv-btn" id="btnSubmitCreateAccount" style="background:#22c55e;color:#000;font-weight:600;">Create Account</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Options Guide Modal -->
        <div class="options-modal-backdrop" id="optionsGuideModal" hidden>
            <div class="options-modal-card" style="background:#121621;border:1px solid rgba(255,255,255,0.12);border-radius:16px;padding:24px;max-width:620px;width:92%;margin:auto;color:#fff;box-shadow:0 20px 50px rgba(0,0,0,0.8);">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:14px;border-bottom:1px solid rgba(255,255,255,0.1);">
                    <h3 style="margin:0;font-size:1.2rem;color:#f59e0b;display:flex;align-items:center;gap:8px;font-weight:700;">
                        <span>📚</span> Options Trading Guide
                    </h3>
                    <button type="button" class="close-guide-modal" style="padding:4px 10px;font-size:1.4rem;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#94a3b8;cursor:pointer;line-height:1;">&times;</button>
                </div>

                <div style="font-size:0.88rem;color:#cbd5e1;line-height:1.6;display:flex;flex-direction:column;gap:14px;max-height:65vh;overflow-y:auto;padding-right:4px;">
                    <!-- Section 1: Call vs Put -->
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.25);padding:12px;border-radius:10px;">
                            <strong style="color:#22c55e;display:flex;align-items:center;gap:6px;font-size:0.95rem;margin-bottom:4px;">📈 Call Option (Bullish)</strong>
                            <p style="margin:0;font-size:0.82rem;color:#cbd5e1;">Buy Call if you predict the crypto price will <strong>RISE</strong>. You earn profit when market price exceeds the Strike Price before expiry.</p>
                        </div>
                        <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);padding:12px;border-radius:10px;">
                            <strong style="color:#ef4444;display:flex;align-items:center;gap:6px;font-size:0.95rem;margin-bottom:4px;">📉 Put Option (Bearish)</strong>
                            <p style="margin:0;font-size:0.82rem;color:#cbd5e1;">Buy Put if you predict the crypto price will <strong>FALL</strong>. You earn profit when market price drops below the Strike Price before expiry.</p>
                        </div>
                    </div>

                    <!-- Section 2: Key Options Concepts -->
                    <div style="background:rgba(255,255,255,0.03);padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);">
                        <strong style="color:#f59e0b;display:block;margin-bottom:6px;font-size:0.92rem;">🎯 Key Terminology</strong>
                        <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:6px;font-size:0.84rem;color:#e2e8f0;">
                            <li><strong>Strike Price:</strong> The target price set for the contract execution.</li>
                            <li><strong>Expiry Date:</strong> The fixed settlement time for the option contract.</li>
                            <li><strong>Premium (Option Price):</strong> The price paid to purchase an option contract.</li>
                            <li><strong>Options Buying Power:</strong> Available funds in your wallet to place option trades.</li>
                        </ul>
                    </div>

                    <!-- Section 3: How Trading Works on this Page -->
                    <div style="background:rgba(255,255,255,0.03);padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);">
                        <strong style="color:#60a5fa;display:block;margin-bottom:6px;font-size:0.92rem;">⚡ How to Place an Option Trade</strong>
                        <ol style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:6px;font-size:0.84rem;color:#e2e8f0;">
                            <li><strong>Select Expiration Date:</strong> Choose date tab at top (e.g. 27 SEP 2026).</li>
                            <li><strong>Choose Call or Put:</strong> Select strike price row from orderbook chain.</li>
                            <li><strong>Enter Quantity & Price:</strong> Set Limit/Market order in trading panel.</li>
                            <li><strong>Submit Order:</strong> Track active trades under <strong>Positions</strong> tab!</li>
                        </ol>
                    </div>

                    <!-- Section 4: Moneyness Terms -->
                    <div style="background:rgba(255,255,255,0.03);padding:12px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);">
                        <strong style="color:#a78bfa;display:block;margin-bottom:6px;font-size:0.92rem;">💡 Moneyness & Auto Settlement</strong>
                        <ul style="margin:0;padding-left:18px;display:flex;flex-direction:column;gap:6px;font-size:0.84rem;color:#e2e8f0;">
                            <li><strong>ITM (In-The-Money):</strong> Contract is currently in profit!</li>
                            <li><strong>ATM (At-The-Money):</strong> Strike price matches current market price.</li>
                            <li><strong>OTM (Out-of-The-Money):</strong> Strike price hasn't been reached yet.</li>
                            <li><strong>Auto-Settlement:</strong> All open contracts automatically settle to your wallet balance at expiry time!</li>
                        </ul>
                    </div>
                </div>

                <div style="margin-top:18px;text-align:right;">
                    <button type="button" class="kv-btn close-guide-modal" style="background:#f59e0b;color:#000;font-weight:700;padding:10px 24px;border-radius:8px;cursor:pointer;border:none;">Got It</button>
                </div>
            </div>
        </div>

        <!-- Options Greeks Analytics Modal -->
        <div class="options-modal-backdrop" id="optionsGreeksModal" hidden>
            <div class="options-modal-card" style="background:#121621;border:1px solid rgba(255,255,255,0.1);border-radius:14px;padding:24px;max-width:560px;width:95%;margin:0 auto;color:#fff;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,0.1);">
                    <h3 style="margin:0;font-size:1.15rem;color:#a78bfa;display:flex;align-items:center;gap:8px;">
                        <span>⚡</span> Options Greeks & Risk Metrics
                    </h3>
                    <button type="button" class="close-greeks-modal" style="padding:2px 8px;font-size:1.3rem;background:none;border:none;color:#94a3b8;cursor:pointer;">&times;</button>
                </div>
                <div style="font-size:0.88rem;color:#cbd5e1;line-height:1.6;display:flex;flex-direction:column;gap:10px;max-height:70vh;overflow-y:auto;">
                    <p style="margin:0 0 4px 0;color:#94a3b8;">Options Greeks measure contract price sensitivity and risk exposure:</p>
                    
                    <div style="background:rgba(139,92,246,0.08);border:1px solid rgba(139,92,246,0.2);padding:10px 12px;border-radius:8px;">
                        <strong style="color:#a78bfa;">Δ Delta:</strong> Price sensitivity to $1 move in underlying asset. (Calls: 0 to +1, Puts: -1 to 0)
                    </div>
                    <div style="background:rgba(59,130,246,0.08);border:1px solid rgba(59,130,246,0.2);padding:10px 12px;border-radius:8px;">
                        <strong style="color:#60a5fa;">Γ Gamma:</strong> Rate of change of Delta per $1 price move. Higher Gamma means Delta changes rapidly.
                    </div>
                    <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.2);padding:10px 12px;border-radius:8px;">
                        <strong style="color:#fbbf24;">Θ Theta:</strong> Time decay rate. Measures daily drop in option value as expiry approaches.
                    </div>
                    <div style="background:rgba(236,72,153,0.08);border:1px solid rgba(236,72,153,0.2);padding:10px 12px;border-radius:8px;">
                        <strong style="color:#f472b6;">ν Vega:</strong> Volatility sensitivity. Price change per 1% change in Implied Volatility (IV).
                    </div>
                    <div style="background:rgba(16,185,129,0.08);border:1px solid rgba(16,185,129,0.2);padding:10px 12px;border-radius:8px;">
                        <strong style="color:#34d399;">ρ Rho:</strong> Interest rate sensitivity. Price change per 1% change in risk-free interest rate.
                    </div>
                </div>
                <div style="margin-top:18px;text-align:right;">
                    <button type="button" class="kv-btn close-greeks-modal" style="background:#a78bfa;color:#000;font-weight:700;padding:8px 20px;border-radius:8px;cursor:pointer;">Close</button>
                </div>
            </div>
        </div>

        <div class="mobile-drawer-backdrop" id="optionsDrawerBackdrop" hidden></div>
        <div class="options-toast-host" id="optionsToastHost"></div>
    </div>
@endsection
