@extends("layouts.app")

@section("title", "Portfolio - Kryvex")

@push("styles")
    @vite("resources/css/pages/portfolio.css")
@endpush

@push("scripts")
    @vite("resources/js/pages/portfolio.js")
@endpush

@section("content")
<!-- Portfolio Loading Animation Overlay -->
<div class="portfolio-loading-overlay" id="portfolioLoadingOverlay">
    <div class="portfolio-loader-spinner"></div>
    <div class="portfolio-loader-badge">
        <span class="portfolio-loader-text">Loading portfolio data...</span>
    </div>
</div>

<div class="portfolio-page text-slate-100 min-h-screen py-8">
    <div class="w-full mx-auto px-3 sm:px-5 lg:px-6 space-y-6" style="max-width: 1680px;">
        
        <!-- 1. PORTFOLIO PAGE HEADER & QUICK NAV -->
        <div class="pf-card p-4 sm:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="w-full md:w-auto">
                <div class="flex items-center justify-between gap-3 w-full mb-1.5">
                    <a href="{{ url('/') }}" class="text-xs text-slate-400 hover:text-purple-400 font-semibold inline-flex items-center gap-1.5 transition-colors">
                        <span>←</span> <span>Back to Home</span>
                    </a>
                    <div class="page-mobile-currency-toggle">
                        <div class="kv-currency-toggle-wrap" style="display: inline-flex; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12); border-radius: 20px; padding: 2px; height: 32px; align-items: center;">
                            <button type="button" class="kv-currency-btn" data-currency-toggle="INR" style="background: transparent; border: none; color: rgba(255,255,255,0.6); border-radius: 16px; padding: 3px 10px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all 0.2s ease;">₹ INR</button>
                            <button type="button" class="kv-currency-btn is-active" data-currency-toggle="USDT" style="background: #7c3aed; border: none; color: #ffffff; border-radius: 16px; padding: 3px 10px; font-size: 11px; font-weight: 700; cursor: pointer; transition: all 0.2s ease; box-shadow: 0 2px 8px rgba(124,58,237,0.4);">$ USD</button>
                        </div>
                    </div>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">Portfolio Overview</h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-0.5">Track consolidated balances, asset allocations, performance metrics & live orders.</p>
            </div>

            <!-- Navigation Links -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ url('/futures') }}" class="pf-btn-secondary px-3.5 sm:px-4 py-2 text-xs font-bold transition-all">Futures</a>
                <a href="{{ url('/options') }}" class="pf-btn-secondary px-3.5 sm:px-4 py-2 text-xs font-bold transition-all">Options</a>
                <a href="{{ url('/wallet') }}" class="pf-btn-secondary px-3.5 sm:px-4 py-2 text-xs font-bold transition-all">Wallet</a>
            </div>
        </div>

        <!-- 2. TOP SECTION: PORTFOLIO OVERVIEW CARD & QUICK ACTIONS + RECENT ACTIVITY -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-stretch">
            
            <!-- PORTFOLIO OVERVIEW CARD (8 Cols) -->
            <div class="lg:col-span-8 pf-card p-4 sm:p-6 flex flex-col justify-between gap-6">
                
                <!-- Card Header -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-white/10">
                    <div class="flex items-center gap-3">
                        <span class="w-10 h-10 rounded-xl bg-purple-500/15 border border-purple-500/30 text-purple-400 flex items-center justify-center font-bold shadow-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        </span>
                        <div>
                            <h2 class="text-sm font-black text-white uppercase tracking-wider">Consolidated Balance</h2>
                            <span class="text-xs text-slate-400">Total Net Worth Across All Accounts & Wallets</span>
                        </div>
                    </div>

                    <!-- Active Sub-Account Selector & Badges -->
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-2 pf-inner-box px-3 py-1.5">
                            <span class="text-[11px] font-bold text-slate-400">Account:</span>
                            <select id="pfAccountSelect" class="bg-transparent text-purple-300 font-extrabold text-xs focus:outline-none cursor-pointer">
                                <option value="">Loading accounts...</option>
                            </select>
                        </div>

                        <span class="px-3 py-1 rounded-xl is-positive font-extrabold text-xs flex items-center gap-1.5 shadow-sm" id="pfTodayPnlBadge">
                            <span>▲</span> <span id="pfTodayPnlText">+₹0.00 (+0.00%) Today</span>
                        </span>
                        <span class="px-3 py-1 rounded-xl bg-white/[0.04] border border-white/10 text-slate-300 font-bold text-xs" id="pfAllTimePnlBadge">
                            All Time: <strong class="text-emerald-400 ml-1" id="pfAllTimePnlText">+₹0.00 (+0.00%)</strong>
                        </span>
                    </div>
                </div>

                <!-- Hero Balance Section -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 py-1">
                    <div>
                        <div class="flex items-center gap-1.5 mb-1 text-slate-400 text-xs font-bold uppercase tracking-wider">
                            <span>Total Portfolio Value</span>
                            <span class="cursor-pointer text-slate-500 hover:text-purple-400 text-xs" title="Total Portfolio Value = Main Wallet + Trading Available + Locked Margin + Open P&L">ⓘ</span>
                        </div>
                        <div class="text-3xl sm:text-4xl lg:text-5xl font-black text-white tracking-tight font-mono" id="pfTotalPortfolioValue">
                            ₹0.00
                        </div>
                        <span class="text-xs sm:text-sm text-slate-400 font-mono mt-1 block font-medium" id="pfSecondaryPortfolioValue">
                            ≈ $0.00 USD
                        </span>
                    </div>

                    <!-- Clean Text Asset Distribution (No Boxes, Pure Text with Dot Indicators) -->
                    <div class="flex flex-col sm:items-end justify-center gap-1.5">
                        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Asset Distribution</span>
                        <div class="flex flex-wrap items-center sm:justify-end gap-x-4 gap-y-1.5 text-xs font-mono font-medium" id="pfHeroDistributionPills">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-sky-400 flex-shrink-0"></span>
                                <span class="text-slate-400">Main:</span>
                                <strong class="text-white font-bold" id="pfBreakdownMain">₹0.00</strong>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-purple-400 flex-shrink-0"></span>
                                <span class="text-slate-400">Trading:</span>
                                <strong class="text-white font-bold" id="pfBreakdownAvail">₹0.00</strong>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-400 flex-shrink-0"></span>
                                <span class="text-slate-400">Locked:</span>
                                <strong class="text-white font-bold" id="pfBreakdownLocked">₹0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5 Metric Stat Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2">
                    <div class="p-3.5 pf-inner-box space-y-1 hover:border-white/15 transition-all">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Main Wallet</span>
                        <strong class="text-white font-mono font-extrabold text-sm sm:text-base block tracking-tight truncate" id="pfMainBalance">₹0.00</strong>
                        <span class="text-[10px] text-slate-500 block">Available Cash</span>
                    </div>

                    <div class="p-3.5 pf-inner-box space-y-1 hover:border-white/15 transition-all">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Trading Avail</span>
                        <strong class="text-white font-mono font-extrabold text-sm sm:text-base block tracking-tight truncate" id="pfAvailableBalance">₹0.00</strong>
                        <span class="text-[10px] text-slate-500 block">Free Margin</span>
                    </div>

                    <div class="p-3.5 pf-inner-box space-y-1 hover:border-white/15 transition-all">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">In Orders</span>
                        <strong class="text-white font-mono font-extrabold text-sm sm:text-base block tracking-tight truncate" id="pfLockedBalance">₹0.00</strong>
                        <span class="text-[10px] text-slate-500 block">Position Margin</span>
                    </div>

                    <div class="p-3.5 pf-inner-box space-y-1 hover:border-white/15 transition-all">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Unrealized P&L</span>
                        <strong class="text-emerald-400 font-mono font-extrabold text-sm sm:text-base block tracking-tight truncate" id="pfUnrealizedPnl">+₹0.00</strong>
                        <span class="text-[10px] text-slate-500 block">Floating P&L</span>
                    </div>

                    <div class="p-3.5 pf-inner-box space-y-1 hover:border-white/15 transition-all col-span-2 sm:col-span-1">
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">Realized P&L</span>
                        <strong class="text-emerald-400 font-mono font-extrabold text-sm sm:text-base block tracking-tight truncate" id="pfRealizedPnl">+₹0.00</strong>
                        <span class="text-[10px] text-slate-500 block">Settled Trades</span>
                    </div>
                </div>

            </div>

            <!-- QUICK ACTIONS & RECENT ACTIVITY (4 Cols) -->
            <div class="lg:col-span-4 pf-card p-4 sm:p-6 flex flex-col justify-between gap-5">
                
                <!-- Quick Actions Section -->
                <div class="space-y-3.5">
                    <div class="flex items-center justify-between pb-2 border-b border-white/10">
                        <div class="flex items-center gap-2">
                            <span class="text-purple-400 font-bold">⚡</span>
                            <h3 class="text-xs font-black text-white uppercase tracking-wider">Quick Wallet Actions</h3>
                        </div>
                    </div>

                    <a href="{{ url('/wallet?tab=deposit') }}" class="w-full p-2.5 pf-inner-box hover:border-emerald-500/40 text-xs font-bold text-slate-200 hover:text-white transition-all text-center flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-5-5m5 5l5-5M4 20h16"></path></svg>
                        <span>Deposit</span>
                    </a>

                    <div class="grid grid-cols-2 gap-2.5">
                        <a href="{{ url('/futures') }}" class="py-2.5 px-3 pf-btn-primary font-bold text-xs text-center transition-all flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                            <span>Trade Futures</span>
                        </a>

                        <button type="button" data-action-transfer class="py-2.5 px-3 pf-btn-secondary text-purple-300 font-bold text-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                            <span>⇄ Transfer</span>
                        </button>
                    </div>
                </div>

                <!-- Recent Activity Feed Section -->
                <div class="space-y-3 pt-2 border-t border-white/10">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-purple-400 font-bold text-xs">⏱</span>
                            <h3 class="text-xs font-black text-white uppercase tracking-wider">Recent Activity</h3>
                        </div>
                        <span class="text-[10px] font-bold text-emerald-400 bg-emerald-950/50 px-2 py-0.5 rounded-full border border-emerald-800/40">Live</span>
                    </div>

                    <!-- Dynamic Recent Activity Items List -->
                    <div class="space-y-2" id="pfTopRecentActivityFeed">
                        <!-- Populated dynamically via JS -->
                        <div class="py-3 text-center text-slate-500 text-xs">Loading activity...</div>
                    </div>
                </div>

            </div>

        </div>

        <!-- 3. MIDDLE SECTION: PORTFOLIO PERFORMANCE GRAPH & ASSET ALLOCATION -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            
            <!-- LEFT (7 Cols): PORTFOLIO PERFORMANCE CHART -->
            <div class="lg:col-span-7 pf-card p-4 sm:p-6 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-white/10">
                    <div class="flex items-center justify-between sm:justify-start gap-3 w-full sm:w-auto">
                        <div>
                            <h3 class="text-base font-black text-white tracking-tight">Portfolio Performance</h3>
                            <p class="text-xs text-slate-400">Historical equity valuation and growth curve.</p>
                        </div>
                        <span class="text-xs font-bold font-mono px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm flex-shrink-0" id="pfChartPeriodReturnBadge">
                            ▲ +13.64%
                        </span>
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-2.5 w-full sm:w-auto">
                        <!-- Timeframe Selector Pills -->
                        <div class="flex items-center justify-between gap-1 pf-inner-box p-1 w-full sm:w-auto" id="pfChartTimeframeContainer">
                            <button type="button" class="pf-chart-btn flex-1 sm:flex-initial px-2 sm:px-2.5 py-1 rounded-lg text-xs font-bold text-slate-400 hover:text-white transition-all cursor-pointer text-center" data-period="1D">1D</button>
                            <button type="button" class="pf-chart-btn flex-1 sm:flex-initial px-2 sm:px-2.5 py-1 rounded-lg text-xs font-bold text-slate-400 hover:text-white transition-all cursor-pointer text-center" data-period="1W">1W</button>
                            <button type="button" class="pf-chart-btn active flex-1 sm:flex-initial px-2 sm:px-2.5 py-1 rounded-lg text-xs font-black bg-purple-600 text-white shadow-sm transition-all cursor-pointer text-center" data-period="1M">1M</button>
                            <button type="button" class="pf-chart-btn flex-1 sm:flex-initial px-2 sm:px-2.5 py-1 rounded-lg text-xs font-bold text-slate-400 hover:text-white transition-all cursor-pointer text-center" data-period="1Y">1Y</button>
                            <button type="button" class="pf-chart-btn flex-1 sm:flex-initial px-2 sm:px-2.5 py-1 rounded-lg text-xs font-bold text-slate-400 hover:text-white transition-all cursor-pointer text-center" data-period="ALL">ALL</button>
                        </div>
                    </div>
                </div>

                <!-- Chart Canvas Container -->
                <div class="relative w-full h-[250px] sm:h-[280px]">
                    <canvas id="pfPerformanceChartCanvas" class="w-full h-full block"></canvas>
                </div>
            </div>

            <!-- RIGHT (5 Cols): ASSET ALLOCATION DONUT & BREAKDOWN -->
            <div class="lg:col-span-5 pf-card p-4 sm:p-6 flex flex-col justify-between space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <h3 class="text-base font-black text-white tracking-tight">Asset Allocation</h3>
                        <p class="text-xs text-slate-400">Portfolio distribution by asset class & wallet holdings.</p>
                    </div>
                    <span class="text-[11px] font-extrabold text-emerald-400 bg-emerald-950/50 px-2.5 py-1 rounded-lg border border-emerald-800/40">Balanced</span>
                </div>

                <!-- Donut Chart & Dynamic Legend (Vertically Centered) -->
                <div class="flex-1 flex items-center">
                    <div class="w-full grid grid-cols-1 sm:grid-cols-12 gap-5 items-center my-auto">
                        
                        <!-- Donut Circle (Centered) -->
                        <div class="sm:col-span-5 flex justify-center items-center relative py-2">
                            <div class="relative w-[150px] h-[150px] flex items-center justify-center">
                                <canvas id="pfAllocationDonutCanvas" width="150" height="150" class="w-[150px] h-[150px] block"></canvas>
                                <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none text-center px-1">
                                    <span class="text-[8px] font-bold text-slate-400 uppercase tracking-wider block" id="pfTopAssetLabel">TOP ASSET</span>
                                    <div class="flex flex-col items-center justify-center leading-tight mt-0.5" id="pfTopAssetValue">
                                        <span class="text-white font-extrabold text-xs block leading-tight">Main</span>
                                        <span class="text-white font-mono font-black text-[11px] block leading-tight mt-0.5">100%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Asset Allocation List (No sub-cards, clean seamless rows) -->
                        <div class="sm:col-span-7 flex flex-col gap-1 justify-center w-full" id="pfAssetAllocationList">
                            <!-- Populated dynamically via JS -->
                            <div class="py-3 text-slate-500 text-center text-xs">
                                Loading allocations...
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>

        <!-- 4. BOTTOM SECTION: TABBED DATA CONTAINER (HOLDINGS, OPEN ORDERS, TRADE HISTORY, FUND ACTIVITY, WITHDRAWALS) -->
        <div class="pf-card p-4 sm:p-6 space-y-4 sm:space-y-5">
            
            <!-- Tabs Navigation Bar (Horizontally scrollable on mobile/tablet) -->
            <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-3 overflow-hidden">
                <div class="flex items-center gap-2 text-xs font-bold overflow-x-auto no-scrollbar w-full pb-1 -mb-1" id="pfMainTabContainer">
                    <button type="button" class="pf-table-tab active px-3.5 sm:px-4 py-2 rounded-xl transition-all cursor-pointer shadow-sm whitespace-nowrap flex-shrink-0" data-pf-tab="holdings">
                        My Assets Holdings
                    </button>
                    <button type="button" class="pf-table-tab px-3.5 sm:px-4 py-2 rounded-xl transition-all cursor-pointer whitespace-nowrap flex-shrink-0" data-pf-tab="orders">
                        Active Orders <span class="ml-1 px-1.5 py-0.5 rounded-full bg-purple-950 text-purple-300 text-[10px] font-black border border-purple-800/40 hidden" id="pfOpenOrdersCountBadge" style="display: none;">0</span>
                    </button>
                    <button type="button" class="pf-table-tab px-3.5 sm:px-4 py-2 rounded-xl transition-all cursor-pointer whitespace-nowrap flex-shrink-0" data-pf-tab="history">
                        Recent Trade History
                    </button>
                    <button type="button" class="pf-table-tab px-3.5 sm:px-4 py-2 rounded-xl transition-all cursor-pointer whitespace-nowrap flex-shrink-0" data-pf-tab="activity">
                        Fund Activity
                    </button>
                    <button type="button" class="pf-table-tab px-3.5 sm:px-4 py-2 rounded-xl transition-all cursor-pointer whitespace-nowrap flex-shrink-0" data-pf-tab="withdrawals">
                        Withdrawals
                    </button>
                </div>
            </div>

            <!-- TAB 1: MY ASSETS HOLDINGS -->
            <div id="tabContentHoldings" class="pf-tab-pane">
                <!-- Desktop Table View (Hidden on screens <= 1024px / 1020px) -->
                <div class="hidden lg:block overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                    <table class="pf-table w-full text-left border-collapse text-xs min-w-[580px]">
                        <thead>
                            <tr class="border-b border-white/10 text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Asset</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Available</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Price</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Value</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">24h Change</th>
                                <th class="py-3 px-3 sm:px-4 text-right whitespace-nowrap">Allocation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 font-semibold" id="pfHoldingsTableBody">
                            <!-- Populated dynamically via JS -->
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500 font-bold">Loading holdings...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Responsive Holdings Cards (Shown on screens <= 1024px / 1020px) -->
                <div class="lg:hidden space-y-2.5 py-1" id="pfHoldingsCardsList">
                    <div class="py-8 text-center text-slate-500 font-bold text-xs">Loading holdings...</div>
                </div>

                <div id="pfHoldingsPagination" class="px-2 sm:px-4 py-3 border-t border-white/10"></div>
            </div>

            <!-- TAB 2: ACTIVE TRADING ACTIVITY (OPEN ORDERS) -->
            <div id="tabContentOrders" class="pf-tab-pane hidden">
                <!-- Desktop Table View (Hidden on screens <= 1024px / 1020px) -->
                <div class="hidden lg:block overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                    <table class="pf-table w-full text-left border-collapse text-xs min-w-[680px]">
                        <thead>
                            <tr class="border-b border-white/10 text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Pair</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Type</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Side</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Order Price</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Amount</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Filled</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Total</th>
                                <th class="py-3 px-3 sm:px-4 text-right whitespace-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 font-semibold" id="pfOrdersTableBody">
                            <!-- Populated dynamically via JS -->
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-500 font-bold">Loading open orders...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Responsive Position Cards (Shown on screens <= 1024px / 1020px) -->
                <div class="lg:hidden space-y-2.5 py-1" id="pfOrdersCardsList">
                    <div class="py-8 text-center text-slate-500 font-bold text-xs">Loading open positions...</div>
                </div>

                <div id="pfOrdersPagination" class="px-2 sm:px-4 py-3 border-t border-white/10"></div>
            </div>

            <!-- TAB 3: RECENT TRADE HISTORY -->
            <div id="tabContentHistory" class="pf-tab-pane hidden">
                <!-- Desktop Table View (Hidden on screens <= 1024px / 1020px) -->
                <div class="hidden lg:block overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                    <table class="pf-table w-full text-left border-collapse text-xs min-w-[640px]">
                        <thead>
                            <tr class="border-b border-white/10 text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Time</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Pair</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Side</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Exec Price</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Filled Qty</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Fee</th>
                                <th class="py-3 px-3 sm:px-4 text-right whitespace-nowrap">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 font-semibold" id="pfHistoryTableBody">
                            <!-- Populated dynamically via JS -->
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-500 font-bold">Loading trade history...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Responsive Trade History Cards (Shown on screens <= 1024px / 1020px) -->
                <div class="lg:hidden space-y-2.5 py-1" id="pfHistoryCardsList">
                    <div class="py-8 text-center text-slate-500 font-bold text-xs">Loading trade history...</div>
                </div>

                <div id="pfHistoryPagination" class="px-2 sm:px-4 py-3 border-t border-white/10"></div>
            </div>

            <!-- TAB 4: FUND ACTIVITY (DEPOSITS & WITHDRAWALS) -->
            <div id="tabContentActivity" class="pf-tab-pane hidden">
                <!-- Desktop Table View (Hidden on screens <= 1024px / 1020px) -->
                <div class="hidden lg:block overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                    <table class="pf-table w-full text-left border-collapse text-xs min-w-[580px]">
                        <thead>
                            <tr class="border-b border-white/10 text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Type</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Asset</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Amount</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Target / Source</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Status</th>
                                <th class="py-3 px-3 sm:px-4 text-right whitespace-nowrap">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 font-semibold" id="pfActivityTableBody">
                            <!-- Populated dynamically via JS -->
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500 font-bold">Loading fund activity...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Responsive Activity Cards (Shown on screens <= 1024px / 1020px) -->
                <div class="lg:hidden space-y-2.5 py-1" id="pfActivityCardsList">
                    <div class="py-8 text-center text-slate-500 font-bold text-xs">Loading fund activity...</div>
                </div>

                <div id="pfActivityPagination" class="px-2 sm:px-4 py-3 border-t border-white/10"></div>
            </div>

            <!-- TAB 5: WITHDRAWALS -->
            <div id="tabContentWithdrawals" class="pf-tab-pane hidden">
                <!-- Desktop Table View (Hidden on screens <= 1024px / 1020px) -->
                <div class="hidden lg:block overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0">
                    <table class="pf-table w-full text-left border-collapse text-xs min-w-[640px]">
                        <thead>
                            <tr class="border-b border-white/10 text-slate-400 font-bold uppercase tracking-wider text-[11px]">
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Reference / Time</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Asset / Network</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Requested</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Fee</th>
                                <th class="py-3 px-3 sm:px-4 whitespace-nowrap">Net Amount</th>
                                <th class="py-3 px-3 sm:px-4 text-right whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5 font-semibold" id="pfWithdrawalsTableBody">
                            <!-- Populated dynamically via JS -->
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500 font-bold">Loading withdrawals...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Responsive Withdrawals Cards (Shown on screens <= 1024px / 1020px) -->
                <div class="lg:hidden space-y-2.5 py-1" id="pfWithdrawalsCardsList">
                    <div class="py-8 text-center text-slate-500 font-bold text-xs">Loading withdrawals...</div>
                </div>

                <div id="pfWithdrawalsPagination" class="px-2 sm:px-4 py-3 border-t border-white/10"></div>
            </div>

        </div>

    </div>
</div>

<!-- Portfolio Transfer Modal Popup -->
<div id="portfolioTransferModalBackdrop" class="fixed inset-0 z-[999999] bg-black/85 backdrop-blur-sm flex items-center justify-center p-4" hidden>
    <div class="pf-card p-6 w-full max-w-md shadow-2xl relative space-y-5">
        
        <div class="flex items-center justify-between border-b border-white/10 pb-3">
            <div class="flex items-center gap-2">
                <span class="text-purple-400 text-lg">⇄</span>
                <h3 class="text-base font-black text-white">Internal Transfer</h3>
            </div>
            <button type="button" id="closePortfolioTransferModalBtn" class="text-slate-400 hover:text-white text-2xl font-bold leading-none cursor-pointer">&times;</button>
        </div>

        <form id="portfolioTransferForm" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Transfer Direction</label>
                <select id="pfTransferDirection" name="direction" class="w-full pf-inner-box px-3.5 py-2.5 text-xs text-white font-bold focus:outline-none focus:border-purple-500">
                    <option value="main_to_trading">Main Wallet ➔ Trading Account</option>
                    <option value="trading_to_main">Trading Account ➔ Main Wallet</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Target Trading Account</label>
                <select id="pfTransferAccountUid" name="account_uid" class="w-full pf-inner-box px-3.5 py-2.5 text-xs text-purple-300 font-bold focus:outline-none focus:border-purple-500">
                    <option value="">Loading accounts...</option>
                </select>
            </div>

            <div>
                <div class="flex justify-between items-center mb-1.5">
                    <label class="text-xs font-bold text-slate-400 uppercase tracking-wider">Transfer Amount</label>
                    <button type="button" id="pfTransferMaxBtn" class="text-[11px] text-purple-400 hover:text-purple-300 font-bold cursor-pointer">Max</button>
                </div>
                <div class="relative">
                    <input type="number" id="pfTransferAmountInput" name="amount" min="0.00000001" step="any" placeholder="Enter amount" required class="w-full pf-inner-box px-3.5 py-2.5 text-sm text-white font-mono font-bold focus:outline-none focus:border-purple-500 pr-16" />
                    <span class="absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-extrabold text-slate-400" id="pfTransferCurrencyBadge">USD</span>
                </div>
            </div>

            <div class="p-3.5 pf-inner-box space-y-1.5 text-xs font-medium">
                <div class="flex justify-between">
                    <span class="text-slate-400">Available Source Balance:</span>
                    <span class="text-white font-mono font-bold" id="pfTransferAvailSource">--</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Estimated Destination Credit:</span>
                    <span class="text-emerald-400 font-mono font-bold" id="pfTransferDestCredit">--</span>
                </div>
            </div>

            <button type="submit" id="btnSubmitPortfolioTransfer" class="w-full py-3 pf-btn-primary text-white font-black text-xs uppercase tracking-wider transition-all cursor-pointer">
                Submit Transfer
            </button>
        </form>

    </div>
</div>

<!-- Position Close Confirmation Modal Popup (Matching Futures/Options page) -->
<!-- Position Close Confirmation Modal Popup (Exact match to Futures page UI) -->
<div id="portfolioConfirmCloseModal" class="fixed inset-0 z-[999999] bg-black/85 backdrop-blur-md flex items-center justify-center p-4" hidden>
    <div class="pf-card p-6 w-full max-w-[430px] shadow-2xl relative text-left space-y-4">
        
        <!-- Warning Badge Icon -->
        <div class="w-11 h-11 rounded-xl bg-amber-500/10 border border-amber-500/30 text-purple-300 flex items-center justify-center text-lg font-black shadow-inner">
            !
        </div>

        <!-- Title & Message -->
        <div class="space-y-1.5 pt-1">
            <h3 class="text-xl font-extrabold text-white tracking-tight" id="pfConfirmCloseTitle">Close this trade?</h3>
            <p class="text-xs font-medium text-slate-400" id="pfConfirmCloseMessage">This position will be closed at current market price.</p>
        </div>

        <!-- Preference Box (Yellow border rounded box matching screenshot) -->
        <label class="flex flex-col gap-2 p-3.5 rounded-xl border border-amber-500/25 bg-amber-500/[0.04] text-slate-300 text-xs cursor-pointer select-none transition-colors hover:bg-amber-500/[0.07]">
            <input type="checkbox" id="pfConfirmCloseSkip" class="w-4 h-4 rounded border-slate-600 bg-slate-900 text-purple-500 focus:ring-0 focus:ring-offset-0 cursor-pointer">
            <span class="text-slate-300 font-semibold text-[12px] leading-snug">Do not ask again when I close a single futures trade.</span>
        </label>

        <!-- Action Buttons (Right-aligned Cancel & Close Trade) -->
        <div class="flex items-center justify-end gap-3 pt-2">
            <button type="button" id="pfConfirmCloseCancelBtn" class="px-5 py-2.5 pf-btn-secondary text-xs font-bold text-slate-300 hover:text-white rounded-xl transition-all cursor-pointer">
                Cancel
            </button>
            <button type="button" id="pfConfirmCloseOkBtn" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-500 active:bg-purple-700 text-white text-xs font-extrabold rounded-xl shadow-lg shadow-purple-600/30 transition-all cursor-pointer">
                Close Trade
            </button>
        </div>

    </div>
</div>
@endsection
