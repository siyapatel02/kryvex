@extends('layouts.app')

@section('title', 'Market Research & Financial Analysis - Kryvex')

@push('styles')
<style>
    html, body {
        overflow-x: hidden;
        overflow-y: auto !important;
        height: auto !important;
        scroll-behavior: smooth;
    }

    .research-grid-card {
        background: rgba(11, 15, 25, 0.95);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
    }

    .research-grid-card:hover {
        transform: translateY(-4px);
        border-color: rgba(168, 85, 247, 0.5);
        box-shadow: 0 12px 24px -10px rgba(168, 85, 247, 0.25);
    }
</style>
@endpush

@section('content')
<div class="research-page text-slate-100 min-h-screen pb-20 relative bg-[#060911]" style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;">
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-4">
        
        @include('partials.market-subnav')

        <!-- Main Hero Title & Search Header -->
        <div class="pb-6 border-b border-slate-800/40 mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/20 text-purple-400 text-xs font-semibold mb-2">
                        <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                        <span>Live FXEmpire Institutional Intelligence</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-100 tracking-tight mb-2">
                        Market Research & Deep-Dive Reports
                    </h1>
                    <p class="text-slate-400 text-sm max-w-2xl leading-relaxed">
                        Explore macroeconomic forecasts, price predictions, central bank policy breakdowns, and technical market insights directly from FXEmpire financial research.
                    </p>
                </div>

                <!-- Search Input Box -->
                <div class="relative w-full md:w-80 flex-shrink-0">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="researchGridSearchInput" oninput="onResearchGridSearch(this.value)" placeholder="Search research (e.g. Gold, ETH, Oil)..." style="padding-left: 38px !important;" class="w-full bg-slate-900/90 border border-slate-700/60 text-slate-200 text-xs font-medium rounded-xl py-2.5 focus:outline-none focus:border-purple-500/60 transition-colors shadow-inner">
                </div>
            </div>

            <!-- Category Filter Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pt-5" id="researchGridCategoryFilter">
                <button type="button" onclick="setResearchGridCatFilter('all')" data-cat="all" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-purple-600/30 text-purple-300 border border-purple-500/50">All Reports</button>
                <button type="button" onclick="setResearchGridCatFilter('Price Prediction')" data-cat="Price Prediction" class="px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50">Price Predictions</button>
                <button type="button" onclick="setResearchGridCatFilter('Crypto')" data-cat="Crypto" class="px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50">Crypto</button>
                <button type="button" onclick="setResearchGridCatFilter('Commodities')" data-cat="Commodities" class="px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50">Commodities & Gold</button>
                <button type="button" onclick="setResearchGridCatFilter('Forex')" data-cat="Forex" class="px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50">Forex & DXY</button>
                <button type="button" onclick="setResearchGridCatFilter('Markets')" data-cat="Markets" class="px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50">Global Markets</button>
            </div>
        </div>

        <!-- 4-COLUMN RICH GRID CONTAINER (Matching reference screenshot) -->
        <div id="researchGridLoadingSpinner" class="text-center py-24 text-slate-400">
            <div class="inline-block w-9 h-9 border-3 border-purple-500/30 border-t-purple-500 rounded-full animate-spin mb-3"></div>
            <p class="font-medium text-slate-300 text-sm">Fetching live FXEmpire research reports...</p>
        </div>

        <div id="researchGridContainer" class="hidden grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <!-- Dynamic Grid Items Populated via JS -->
        </div>

    </div>

    <!-- DETAIL REPORT MODAL VIEW -->
    <div id="researchDetailModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
        <div class="bg-[#0b0f19] border border-slate-800 rounded-2xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl relative my-8 text-slate-100">
            
            <!-- Close Button -->
            <button type="button" onclick="closeResearchDetailModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white p-2 rounded-xl bg-slate-900 border border-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <!-- Modal Content -->
            <div class="flex items-center gap-2 mb-4 flex-wrap">
                <span id="modalCategoryBadge" class="px-3 py-1 rounded-lg text-xs font-bold bg-teal-500/15 text-teal-300 border border-teal-500/30">
                    Price Prediction
                </span>
                <span id="modalSourceBadge" class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700/60">
                    FXEmpire Research
                </span>
                <span id="modalDate" class="text-xs font-medium text-slate-400 ml-auto">
                    Oct 7, 2026
                </span>
            </div>

            <h2 id="modalTitle" class="text-xl sm:text-2xl font-bold text-slate-100 leading-snug mb-4">
                Gold News: Yields Still Control as XAUUSD Bounces From Lows
            </h2>

            <div class="w-full h-56 sm:h-72 rounded-xl overflow-hidden mb-6 bg-slate-900 border border-slate-800/80 relative">
                <img id="modalImage" src="" alt="Research Graphic" class="w-full h-full object-cover">
            </div>

            <div class="bg-slate-900/80 border border-slate-800/80 rounded-xl p-5 mb-6">
                <h3 class="text-xs font-bold uppercase tracking-wider text-purple-400 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Executive Research Summary
                </h3>
                <p id="modalSummary" class="text-slate-300 text-sm leading-relaxed">
                    Spot gold bounced from key support levels as treasury yields stabilized.
                </p>
            </div>

            <div class="space-y-4 text-slate-300 text-sm leading-relaxed mb-8">
                <h4 class="font-semibold text-slate-100 text-base">Fundamental Breakdown & Analysis</h4>
                <p id="modalBody" class="text-slate-300">
                    Macroeconomic expectations continue to shift based on inflation metrics and yields.
                </p>
            </div>

            <!-- Redirection Link -->
            <div class="pt-6 border-t border-slate-800/60 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-semibold text-slate-300 block">Want full analyst report & live charts?</span>
                    <span class="text-[11px] text-slate-400">Redirect to original source report site.</span>
                </div>

                <a id="modalExternalLink" href="https://www.fxempire.com/" target="_blank" rel="noopener noreferrer" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition-all shadow-lg flex-shrink-0">
                    <span>Read Full Report on FXEmpire</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>

        </div>
    </div>

</div>

@push('scripts')
<script>
    (function() {
        let allGridResearchArticles = [];
        let currentSearchQuery = '';
        let currentCategoryFilter = 'all';

        async function fetchGridResearchData() {
            try {
                const res = await fetch('/api/markets/fxempire-news?limit=30');
                if (res.ok) {
                    const data = await res.json();
                    if (data.success && data.research && data.research.length > 0) {
                        allGridResearchArticles = data.research;
                    }
                }
            } catch (err) {
                console.error('Error fetching grid research data:', err);
            }

            // High quality mock data if API is empty
            if (!allGridResearchArticles || allGridResearchArticles.length === 0) {
                const nowSec = Math.floor(Date.now() / 1000);
                const mockResearch = [
                    { offset: 0, title: 'Gold Price Forecast: Final Pullback Before the Uptrend Resumes', category: 'Price Prediction', link: 'https://www.fxempire.com/', description: 'Gold price forecast indicates key pullback levels as long-term uptrend structure consolidates near support.', image: 'https://responsive.fxempire.com/v7/_fxempire_/2026/10/gold-precious-metal-price-chart-19.jpg' },
                    { offset: -3600 * 3, title: 'Ethereum Price Forecast: 3 Bullish ETH Signals Put $3K Back in Focus', category: 'Price Prediction', link: 'https://www.fxempire.com/', description: 'Ethereum price eyes a sharp rebound as oversold RSI, bull flag, and heavy short interest trigger potential breakout.', image: 'https://responsive.fxempire.com/v7/_fxempire_/2025/10/Ethereum-bull.jpg' },
                    { offset: -3600 * 6, title: 'First Light News: Stocks Rally as Bond Bears Dig In', category: 'Markets', link: 'https://www.fxempire.com/', description: 'US stocks hit fresh records as Asia lags. Treasury yields sit near 24-year highs.', image: 'https://responsive.fxempire.com/v7/_fxempire_/2026/10/sp500-index-stock-market-02.jpg' },
                    { offset: -3600 * 12, title: 'Gold vs Bitcoin: BTC Slips Ahead of FOMC Minutes', category: 'Price Prediction', link: 'https://www.fxempire.com/', description: 'Gold and Bitcoin face pressure ahead of FOMC minutes, while a Bitcoin breakout above $87,000 looms.', image: 'https://images.unsplash.com/photo-1621416894569-0f39ed31d247?w=800&auto=format&fit=crop&q=80' },
                    { offset: -86400 * 1, title: 'US Dollar Price Forecast: Fed Minutes Loom, Can GBP/USD and EUR/USD Recover?', category: 'Forex', link: 'https://www.fxempire.com/', description: 'DXY holds above 101.76 as traders await Fed minutes, while EUR/USD tests 1.1025 support.', image: 'https://images.unsplash.com/photo-1580519542036-c47de6196ba5?w=800&auto=format&fit=crop&q=80' },
                    { offset: -86400 * 1.5, title: 'Gold (XAU/USD) & Silver Price Forecast: China Adds Gold, Fed Minutes Loom', category: 'Commodities', link: 'https://www.fxempire.com/', description: 'China adds to gold reserves for a 22nd month as Fed minutes loom, while XAU/USD holds $4,112.', image: 'https://images.unsplash.com/photo-1610375461246-83df859d849d?w=800&auto=format&fit=crop&q=80' },
                    { offset: -86400 * 2, title: 'Natural Gas and Oil Forecast: Gulf Storm Threatens Supply, Can WTI Break $90.65?', category: 'Commodities', link: 'https://www.fxempire.com/', description: 'Gulf storm risks and Saudi-Houthi tensions lift oil supply concerns as WTI tests $88.64.', image: 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=800&auto=format&fit=crop&q=80' },
                    { offset: -86400 * 2.5, title: 'Gold Price Forecast: XAU/USD Eyes Fed Minutes for Fresh Impetus', category: 'Price Prediction', link: 'https://www.fxempire.com/', description: 'Gold is back in the red around $4,150 in Asian trades, reversing previous gains.', image: 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&auto=format&fit=crop&q=80' }
                ];
                allGridResearchArticles = mockResearch.map((item, idx) => {
                    const ts = nowSec + item.offset;
                    const d = new Date(ts * 1000);
                    return {
                        id: 'res-' + (idx + 1),
                        title: item.title,
                        category: item.category,
                        timestamp: ts,
                        date: d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }),
                        link: item.link,
                        description: item.description,
                        full_description: item.description,
                        image: item.image
                    };
                });
            }

            renderGridResearchPage();
        }

        function renderGridResearchPage() {
            const spinner = document.getElementById('researchGridLoadingSpinner');
            const container = document.getElementById('researchGridContainer');

            if (spinner) spinner.classList.add('hidden');
            if (container) container.classList.remove('hidden');

            let filtered = allGridResearchArticles.filter(item => {
                if (currentCategoryFilter !== 'all' && (item.category || '').toLowerCase() !== currentCategoryFilter.toLowerCase()) {
                    return false;
                }
                if (currentSearchQuery) {
                    const t = (item.title || '').toLowerCase();
                    const d = (item.description || '').toLowerCase();
                    if (!t.includes(currentSearchQuery) && !d.includes(currentSearchQuery)) return false;
                }
                return true;
            });

            // Frontend title deduplication pass
            const seenTitles = new Set();
            filtered = filtered.filter(item => {
                const norm = (item.title || '').trim().toLowerCase();
                if (seenTitles.has(norm)) return false;
                seenTitles.add(norm);
                return true;
            });

            // Sort chronologically descending so latest research articles are ALWAYS at top
            filtered.sort((a, b) => (Number(b.timestamp) || 0) - (Number(a.timestamp) || 0));

            if (filtered.length === 0) {
                container.innerHTML = `<div class="col-span-full p-12 text-center text-sm text-slate-400">No FXEmpire research reports matching search query or category filter.</div>`;
                return;
            }

            container.innerHTML = filtered.map(item => {
                return `
                    <div onclick="openResearchDetailModal('${item.id}')" class="research-grid-card p-4 cursor-pointer">
                        <div>
                            <!-- Top Feature Image -->
                            <div class="w-full h-44 rounded-xl overflow-hidden mb-3.5 bg-slate-900 border border-slate-800 relative">
                                <img src="${item.image}" alt="Graphic" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&auto=format&fit=crop&q=80';">
                                
                                <span class="absolute top-3 left-3 px-2.5 py-1 text-[10px] font-bold rounded-lg bg-teal-500/20 text-teal-300 border border-teal-500/40 uppercase tracking-wider backdrop-blur-md">
                                    ${item.category || 'Price Prediction'}
                                </span>
                            </div>

                            <!-- Date -->
                            <div class="text-[11px] font-semibold text-slate-400 mb-1.5">${item.date || 'Oct 7, 2026'}</div>

                            <!-- Title -->
                            <h3 class="text-sm font-bold text-slate-100 leading-snug mb-2 line-clamp-2 hover:text-purple-400 transition-colors">
                                ${item.title}
                            </h3>

                            <!-- Description -->
                            <p class="text-xs text-slate-400 leading-relaxed line-clamp-2 mb-4">
                                ${item.description}
                            </p>
                        </div>

                        <!-- Read More Link -->
                        <div class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-400 hover:text-blue-300 transition-colors pt-2 border-t border-slate-800/60">
                            <span>Read more</span>
                            <span class="text-sm">&rarr;</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        window.openResearchDetailModal = function(id) {
            const item = allGridResearchArticles.find(a => a.id === id);
            if (!item) return;

            document.getElementById('modalCategoryBadge').textContent = item.category || 'Price Prediction';
            document.getElementById('modalSourceBadge').textContent = item.source || 'FXEmpire Research';
            document.getElementById('modalDate').textContent = item.date || 'Today';
            document.getElementById('modalTitle').textContent = item.title;
            document.getElementById('modalImage').src = item.image;
            document.getElementById('modalSummary').textContent = item.full_description || item.description;
            document.getElementById('modalBody').textContent = item.description;

            const extBtn = document.getElementById('modalExternalLink');
            if (extBtn) {
                extBtn.href = item.link || 'https://www.fxempire.com/';
            }

            const modal = document.getElementById('researchDetailModal');
            if (modal) modal.classList.remove('hidden');
        };

        window.closeResearchDetailModal = function() {
            const modal = document.getElementById('researchDetailModal');
            if (modal) modal.classList.add('hidden');
        };

        window.onResearchGridSearch = function(val) {
            currentSearchQuery = (val || '').trim().toLowerCase();
            renderGridResearchPage();
        };

        window.setResearchGridCatFilter = function(cat) {
            currentCategoryFilter = cat;
            document.querySelectorAll('#researchGridCategoryFilter button').forEach(btn => {
                if (btn.getAttribute('data-cat') === cat) {
                    btn.className = 'px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-purple-600/30 text-purple-300 border border-purple-500/50';
                } else {
                    btn.className = 'px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50';
                }
            });
            renderGridResearchPage();
        };

        document.addEventListener('DOMContentLoaded', () => {
            fetchGridResearchData();
        });
    })();
</script>
@endpush
@endsection
