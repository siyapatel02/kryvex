@extends('layouts.app')

@section('title', 'Markets - Kryvex')

@push('styles')
    @vite('resources/css/pages/markets.css')
@endpush

@push('scripts')
    <script src="https://unpkg.com/lenis@1.1.18/dist/lenis.min.js"></script>
    @vite('resources/js/pages/markets.js')
@endpush

@section('content')
<!-- TOP SCROLL PROGRESS BAR -->
<div id="marketsScrollProgressBar" style="width: 0%;"></div>

<div class="markets-page text-slate-100 min-h-screen pb-16 relative">
    <!-- Markets loading animation overlay -->
    <div class="markets-loading-overlay fixed inset-0 bg-[#060911]/95 backdrop-blur-md z-50 flex flex-col items-center justify-center gap-3" id="marketsLoadingOverlay">
        <div class="loader-spinner w-10 h-10 border-3 border-purple-500/20 border-t-purple-500 rounded-full animate-spin"></div>
        <p class="text-xs font-semibold text-slate-400">Syncing live exchange tickers...</p>
    </div>

    <div id="marketsContent" class="opacity-0 transition-opacity duration-300 relative z-10">
        
        <!-- HERO BANNER SECTION -->
        <section class="markets-hero pt-6 pb-6 reveal-item">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @include('partials.market-subnav')
                <div class="max-w-3xl">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/15 border border-purple-500/30 backdrop-blur-md text-purple-300 text-xs font-bold mb-3 shadow-lg shadow-purple-950/30">
                        <span class="w-2 h-2 rounded-full bg-purple-400 animate-ping"></span>
                        <span class="w-2 h-2 rounded-full bg-purple-400 -ml-4"></span>
                        <span>Live USDT Futures Market</span>
                    </div>

                    <h1 class="text-3xl sm:text-4xl md:text-5xl font-black text-white tracking-tight mb-2 transition-all duration-300 hover:text-purple-200">
                        Trade USDT futures contracts
                    </h1>
                    <p class="text-slate-400 text-sm md:text-base leading-relaxed">
                        Track leverage, LTP, 24H change, high, low and volume in one clean, real-time market table.
                    </p>
                </div>
            </div>
        </section>

        <!-- TOP RANK CARDS SECTION (Top Gainers, Top Losers, Trending) -->
        <section class="py-6">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="markets-summary-grid grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    
                    <!-- Top Gainers Card -->
                    <div class="markets-summary-card reveal-item delay-100 group bg-[#0e131f]/50 border border-slate-800/70 border-t-2 border-t-emerald-500 rounded-2xl p-5 shadow-xl backdrop-blur-md transition-all duration-300 hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-emerald-500/10 hover:border-slate-700/80" id="topGainersCard">
                        <div class="flex items-center gap-2.5 mb-4 pb-3 border-b border-slate-800/60">
                            <span class="text-xl transition-transform duration-300 group-hover:scale-125">📈</span>
                            <h3 class="text-base font-extrabold text-white group-hover:text-emerald-400 transition-colors">Top Gainers</h3>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5 px-3 py-1.5 bg-slate-950/40 rounded-lg border border-slate-800/30">
                                <span class="flex-1 text-left">PAIR</span>
                                <span class="rank-header-price hidden md:block text-right pr-3 font-bold text-slate-400">PRICE</span>
                                <span class="w-16 md:w-20 text-right">CHANGE</span>
                            </div>
                            <div class="rank-rows-container space-y-1.5" id="topGainersRows">
                                <div class="text-center py-6 text-xs text-slate-500">Loading gainers...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Losers Card -->
                    <div class="markets-summary-card reveal-item delay-200 group bg-[#0e131f]/50 border border-slate-800/70 border-t-2 border-t-rose-500 rounded-2xl p-5 shadow-xl backdrop-blur-md transition-all duration-300 hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-rose-500/10 hover:border-slate-700/80" id="topLosersCard">
                        <div class="flex items-center gap-2.5 mb-4 pb-3 border-b border-slate-800/60">
                            <span class="text-xl transition-transform duration-300 group-hover:scale-125">📉</span>
                            <h3 class="text-base font-extrabold text-white group-hover:text-rose-400 transition-colors">Top Losers</h3>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5 px-3 py-1.5 bg-slate-950/40 rounded-lg border border-slate-800/30">
                                <span class="flex-1 text-left">PAIR</span>
                                <span class="rank-header-price hidden md:block text-right pr-3 font-bold text-slate-400">PRICE</span>
                                <span class="w-16 md:w-20 text-right">CHANGE</span>
                            </div>
                            <div class="rank-rows-container space-y-1.5" id="topLosersRows">
                                <div class="text-center py-6 text-xs text-slate-500">Loading losers...</div>
                            </div>
                        </div>
                    </div>

                    <!-- Trending Card -->
                    <div class="markets-summary-card reveal-item delay-300 group bg-[#0e131f]/50 border border-slate-800/70 border-t-2 border-t-purple-500 rounded-2xl p-5 shadow-xl backdrop-blur-md transition-all duration-300 hover:-translate-y-1.5 hover:shadow-2xl hover:shadow-purple-500/10 hover:border-slate-700/80" id="trendingCard">
                        <div class="flex items-center gap-2.5 mb-4 pb-3 border-b border-slate-800/60">
                            <span class="text-xl transition-transform duration-300 group-hover:scale-125">🔥</span>
                            <h3 class="text-base font-extrabold text-white group-hover:text-purple-400 transition-colors">Trending</h3>
                        </div>
                        <div>
                            <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider mb-2.5 px-3 py-1.5 bg-slate-950/40 rounded-lg border border-slate-800/30">
                                <span class="flex-1 text-left">PAIR</span>
                                <span class="rank-header-price hidden md:block text-right pr-3 font-bold text-slate-400">PRICE</span>
                                <span class="w-16 md:w-20 text-right">CHANGE</span>
                            </div>
                            <div class="rank-rows-container space-y-1.5" id="trendingRows">
                                <div class="text-center py-6 text-xs text-slate-500">Loading trending...</div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Carousel Indicators for mobile -->
                <div class="carousel-indicators flex justify-center items-center gap-2 md:hidden mb-6" id="carouselIndicators">
                    <span class="dot active w-2 h-2 rounded-full bg-purple-500 transition-all" data-slide="0"></span>
                    <span class="dot w-2 h-2 rounded-full bg-slate-700 transition-all" data-slide="1"></span>
                    <span class="dot w-2 h-2 rounded-full bg-slate-700 transition-all" data-slide="2"></span>
                </div>

                <!-- MAIN USDT FUTURES SECTION -->
                <div class="markets-toolbar reveal-item flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight">USDT Futures</h2>
                        <p class="text-xs sm:text-sm text-slate-400 mt-0.5">Sorted by 24H volume. Scroll inside the table to view more contracts.</p>
                    </div>

                    <div class="markets-search-box relative w-full sm:w-72">
                        <input type="text" id="marketSearchInput" placeholder="Search contract e.g. BTC" class="w-full bg-[#0e131f]/50 border border-slate-800/80 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 hover:border-slate-700 transition-all shadow-sm backdrop-blur-md">
                    </div>
                </div>

                <!-- MARKET TABLE CONTAINER CARD -->
                <div class="markets-table-card reveal-item delay-100 bg-[#0e131f]/50 border border-slate-800/70 rounded-2xl overflow-hidden shadow-2xl backdrop-blur-md transition-all duration-300 hover:border-slate-700/80 hover:shadow-purple-950/20">
                    <div class="markets-table-wrap overflow-x-auto">
                        <table class="markets-table w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-950/40 border-b border-slate-800/70 text-[11px] font-extrabold text-slate-400 uppercase tracking-wider">
                                    <th class="py-3.5 px-4 text-left w-[23%]">CONTRACT</th>
                                    <th class="py-3.5 px-3 text-center w-[10%]">LEVERAGE</th>
                                    <th class="py-3.5 px-4 text-right w-[14%]">LTP</th>
                                    <th class="py-3.5 px-4 text-right w-[12%]">24H CHANGE</th>
                                    <th class="py-3.5 px-4 text-right w-[12%] hide-tablet">24H HIGH</th>
                                    <th class="py-3.5 px-4 text-right w-[12%] hide-tablet">24H LOW</th>
                                    <th class="py-3.5 px-4 text-right w-[12%] hide-tablet">VOLUME</th>
                                    <th class="py-3.5 px-4 text-right w-[10%] hide-mobile">TRADE</th>
                                </tr>
                            </thead>
                            <tbody id="marketsTableBody" class="divide-y divide-slate-800/40 text-xs sm:text-sm">
                                <tr>
                                    <td colspan="8" class="text-center py-10 text-slate-500">Loading markets...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile List View -->
                    <div class="markets-cards-list hidden divide-y divide-slate-800/40" id="marketsCardsList">
                        <div class="text-center py-8 text-xs text-slate-500">Loading markets...</div>
                    </div>
                </div>

            </div>
        </section>

    </div>

    <!-- FLOATING SCROLL TO TOP BUTTON -->
    <button type="button" id="marketsScrollToTopBtn" aria-label="Scroll to top" title="Scroll to top">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    </button>
</div>
@endsection