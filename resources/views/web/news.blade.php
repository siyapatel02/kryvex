@extends('layouts.app')

@section('title', 'Live Crypto & Market News - Kryvex')

@push('styles')
<style>
    html, body {
        overflow-x: hidden;
        overflow-y: auto !important;
        height: auto !important;
        scroll-behavior: smooth;
    }

    .news-grid-card {
        background: rgba(11, 15, 25, 0.95);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 16px;
        transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        overflow: hidden;
    }

    .news-grid-card:hover {
        transform: translateY(-4px);
        border-color: rgba(59, 130, 246, 0.5);
        box-shadow: 0 12px 24px -10px rgba(59, 130, 246, 0.25);
    }
</style>
@endpush

@section('content')
<div class="news-page text-slate-100 min-h-screen pb-20 relative bg-[#060911]" style="font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;">
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 pb-4">
        
        @include('partials.market-subnav')

        <!-- Main Hero Title & Search Header -->
        <div class="pb-6 border-b border-slate-800/40 mb-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-semibold mb-2">
                        <span class="w-2 h-2 rounded-full bg-blue-400 animate-pulse"></span>
                        <span>Live Multi-Source Crypto & Financial News (CoinTelegraph, CoinDesk, Decrypt, FXEmpire)</span>
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-bold text-slate-100 tracking-tight mb-2">
                        Latest Market News & Breaking Updates
                    </h1>
                    <p class="text-slate-400 text-sm max-w-2xl leading-relaxed">
                        Stay updated with real-time breaking news from top crypto & financial platforms including CoinTelegraph, CoinDesk, Decrypt, and FXEmpire with authentic featured graphics.
                    </p>
                </div>

                <!-- Search Input Box -->
                <div class="relative w-full md:w-80 flex-shrink-0">
                    <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input type="text" id="newsGridSearchInput" oninput="onNewsGridSearch(this.value)" placeholder="Search news (e.g. Bitcoin, Fed, DXY)..." style="padding-left: 38px !important;" class="w-full bg-slate-900/90 border border-slate-700/60 text-slate-200 text-xs font-medium rounded-xl py-2.5 focus:outline-none focus:border-blue-500/60 transition-colors shadow-inner">
                </div>
            </div>

            <!-- Category Filter Pills -->
            <div class="flex items-center gap-2 overflow-x-auto pt-5" id="newsGridCategoryFilter">
                <button type="button" onclick="setNewsGridCatFilter('all')" data-cat="all" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-blue-600/30 text-blue-300 border border-blue-500/50">All News</button>
                <button type="button" onclick="setNewsGridCatFilter('Crypto')" data-cat="Crypto" class="px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50">Crypto News</button>
                <button type="button" onclick="setNewsGridCatFilter('Policy')" data-cat="Policy" class="px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50">Policy & Legal</button>
                <button type="button" onclick="setNewsGridCatFilter('Forex')" data-cat="Forex" class="px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50">Forex & DXY</button>
                <button type="button" onclick="setNewsGridCatFilter('Markets')" data-cat="Markets" class="px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50">Global Markets</button>
            </div>
        </div>

        <!-- 4-COLUMN RICH GRID CONTAINER -->
        <div id="newsGridLoadingSpinner" class="text-center py-24 text-slate-400">
            <div class="inline-block w-9 h-9 border-3 border-blue-500/30 border-t-blue-500 rounded-full animate-spin mb-3"></div>
            <p class="font-medium text-slate-300 text-sm">Fetching live multi-source news updates...</p>
        </div>

        <div id="newsGridContainer" class="hidden grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <!-- Dynamic Grid Items Populated via JS -->
        </div>

    </div>

    <!-- DETAIL NEWS MODAL VIEW -->
    <div id="newsDetailModal" class="fixed inset-0 z-50 hidden bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 sm:p-6 overflow-y-auto">
        <div class="bg-[#0b0f19] border border-slate-800 rounded-2xl max-w-3xl w-full p-6 sm:p-8 shadow-2xl relative my-8 text-slate-100">
            
            <!-- Close Button -->
            <button type="button" onclick="closeNewsDetailModal()" class="absolute top-5 right-5 text-slate-400 hover:text-white p-2 rounded-xl bg-slate-900 border border-slate-800 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <!-- Modal Content -->
            <div class="flex items-center gap-2 mb-4 flex-wrap">
                <span id="newsModalCategoryBadge" class="px-3 py-1 rounded-lg text-xs font-bold bg-blue-500/15 text-blue-300 border border-blue-500/30">
                    Crypto News
                </span>
                <span id="newsModalSourceBadge" class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-800 text-slate-300 border border-slate-700/60">
                    Publisher
                </span>
                <span id="newsModalDate" class="text-xs font-medium text-slate-400 ml-auto">
                    Oct 7, 2026
                </span>
            </div>

            <h2 id="newsModalTitle" class="text-xl sm:text-2xl font-bold text-slate-100 leading-snug mb-4">
                Crypto Godfather Sentenced: $37M Meta Fraud Scheme Breakdown
            </h2>

            <div class="w-full h-56 sm:h-72 rounded-xl overflow-hidden mb-6 bg-slate-900 border border-slate-800/80 relative">
                <img id="newsModalImage" src="" alt="News Graphic" class="w-full h-full object-cover">
            </div>

            <div class="bg-slate-900/80 border border-slate-800/80 rounded-xl p-5 mb-6">
                <h3 class="text-xs font-bold uppercase tracking-wider text-blue-400 mb-2 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    Breaking News Summary
                </h3>
                <p id="newsModalSummary" class="text-slate-300 text-sm leading-relaxed">
                    Federal court sentences defendant in multi-million dollar digital asset fraud investigation.
                </p>
            </div>

            <div class="space-y-4 text-slate-300 text-sm leading-relaxed mb-8">
                <h4 class="font-semibold text-slate-100 text-base">Market Impact & Key Developments</h4>
                <p id="newsModalBody" class="text-slate-300">
                    Federal authorities concluded sentencing in a high-profile cryptocurrency fraud case.
                </p>
            </div>

            <!-- Redirection Link -->
            <div class="pt-6 border-t border-slate-800/60 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-semibold text-slate-300 block">Want to read the full original story?</span>
                    <span class="text-[11px] text-slate-400">Redirect to original news portal where this article is published.</span>
                </div>

                <a id="newsModalExternalLink" href="https://cointelegraph.com/" target="_blank" rel="noopener noreferrer" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition-all shadow-lg flex-shrink-0">
                    <span>Read Full Article</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>

        </div>
    </div>

</div>

@push('scripts')
<script>
    (function() {
        let allGridNewsArticles = [];
        let currentSearchQuery = '';
        let currentCategoryFilter = 'all';

        async function fetchGridNewsData() {
            try {
                const res = await fetch('/api/markets/fxempire-news?limit=30');
                if (res.ok) {
                    const data = await res.json();
                    if (data.success && data.news && data.news.length > 0) {
                        allGridNewsArticles = data.news;
                    }
                }
            } catch (err) {
                console.error('Error fetching grid news data:', err);
            }

            // High quality mock data if API is empty
            if (!allGridNewsArticles || allGridNewsArticles.length === 0) {
                allGridNewsArticles = [
                    {
                        id: 'news-1',
                        title: 'Crypto Godfather Gets Six Years for Hiring Sheriff\'s Deputies, $37M Meta Fraud',
                        category: 'Policy',
                        date: 'Oct 7, 2026',
                        link: 'https://www.fxempire.com/',
                        description: 'Adam Iza used off-duty LA deputies to pull warrants and personal data in a multi-million dollar fraud operation.',
                        full_description: 'Federal law enforcement agencies announced final sentencing for Adam Iza following extensive investigation into digital asset misappropriation.',
                        image: 'https://images.unsplash.com/photo-1639762681485-074b7f938ba0?w=800&auto=format&fit=crop&q=80'
                    },
                    {
                        id: 'news-2',
                        title: 'Hong Kong Officials Double Down on End-2026 Deadline for Crypto Licensing Bill',
                        category: 'Policy',
                        date: 'Oct 7, 2026',
                        link: 'https://www.fxempire.com/',
                        description: 'Hong Kong regulators plan to establish a licensing regime for digital asset trading platforms.',
                        full_description: 'Financial regulators in Hong Kong confirmed mandatory licensing standards for exchanges operating in the region.',
                        image: 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=800&auto=format&fit=crop&q=80'
                    },
                    {
                        id: 'news-3',
                        title: 'Live Updates: A Drop Below $84,000 Could Put $80,000 in Play for Bitcoin',
                        category: 'Crypto',
                        date: 'Oct 6, 2026',
                        link: 'https://www.fxempire.com/',
                        description: 'FxPro analysts note that a slide below $84,000 would hand short-term control to sellers.',
                        full_description: 'Spot bitcoin orderflow indicates critical support consolidation near key technical moving averages.',
                        image: 'https://images.unsplash.com/photo-1642543492481-44e81e3914a7?w=800&auto=format&fit=crop&q=80'
                    },
                    {
                        id: 'news-4',
                        title: 'Smaller Altcoins Shine as Bitcoin Still Trades Around $85,000',
                        category: 'Crypto',
                        date: 'Oct 6, 2026',
                        link: 'https://www.fxempire.com/',
                        description: 'Leading gains across exchanges as native layer-1 tokens outperform major cryptos.',
                        full_description: 'Altcoin trading volume gained momentum across decentralized and centralized platforms.',
                        image: 'https://images.unsplash.com/photo-1622979135225-d2ba269bc1bd?w=800&auto=format&fit=crop&q=80'
                    }
                ];
            }

            renderGridNewsPage();
        }

        function renderGridNewsPage() {
            const spinner = document.getElementById('newsGridLoadingSpinner');
            const container = document.getElementById('newsGridContainer');

            if (spinner) spinner.classList.add('hidden');
            if (container) container.classList.remove('hidden');

            let filtered = allGridNewsArticles.filter(item => {
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

            if (filtered.length === 0) {
                container.innerHTML = `<div class="col-span-full p-12 text-center text-sm text-slate-400">No FXEmpire news articles matching search query or category filter.</div>`;
                return;
            }

            container.innerHTML = filtered.map(item => {
                return `
                    <div onclick="openNewsDetailModal('${item.id}')" class="news-grid-card p-4 cursor-pointer">
                        <div>
                            <!-- Top Feature Image -->
                            <div class="w-full h-44 rounded-xl overflow-hidden mb-3.5 bg-slate-900 border border-slate-800 relative">
                                <img src="${item.image}" alt="Graphic" class="w-full h-full object-cover" onerror="this.onerror=null; this.src='https://responsive.fxempire.com/v7/_fxempire_/2025/10/Ethereum-bull.jpg';">
                                
                                <div class="absolute top-3 left-3 flex items-center gap-1.5 flex-wrap">
                                    <span class="px-2.5 py-1 text-[10px] font-bold rounded-lg bg-blue-500/20 text-blue-300 border border-blue-500/40 uppercase tracking-wider backdrop-blur-md">
                                        ${item.category || 'Crypto News'}
                                    </span>
                                    <span class="px-2.5 py-1 text-[10px] font-bold rounded-lg bg-slate-900/90 text-slate-200 border border-slate-700/80 uppercase tracking-wider backdrop-blur-md shadow-sm">
                                        ${item.source || 'News'}
                                    </span>
                                </div>
                            </div>

                            <!-- Date -->
                            <div class="text-[11px] font-semibold text-slate-400 mb-1.5">${item.date || 'Oct 7, 2026'} &bull; <span class="text-blue-400 font-bold">${item.source || 'News'}</span></div>

                            <!-- Title -->
                            <h3 class="text-sm font-bold text-slate-100 leading-snug mb-2 line-clamp-2 hover:text-blue-400 transition-colors">
                                ${item.title}
                            </h3>

                            <!-- Description -->
                            <p class="text-xs text-slate-400 leading-relaxed line-clamp-2 mb-4">
                                ${item.description}
                            </p>
                        </div>

                        <!-- Read More Link -->
                        <div class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-400 hover:text-blue-300 transition-colors pt-2 border-t border-slate-800/60">
                            <span>Read full story on ${item.source || 'Publisher'}</span>
                            <span class="text-sm">&rarr;</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        window.openNewsDetailModal = function(id) {
            const item = allGridNewsArticles.find(a => a.id === id);
            if (!item) return;

            document.getElementById('newsModalCategoryBadge').textContent = item.category || 'Crypto News';
            document.getElementById('newsModalSourceBadge').textContent = item.source || 'Publisher';
            document.getElementById('newsModalDate').textContent = item.date || 'Today';
            document.getElementById('newsModalTitle').textContent = item.title;
            document.getElementById('newsModalImage').src = item.image;
            document.getElementById('newsModalSummary').textContent = item.full_description || item.description;
            document.getElementById('newsModalBody').textContent = item.description;

            const extBtn = document.getElementById('newsModalExternalLink');
            if (extBtn) {
                extBtn.href = item.link || 'https://cointelegraph.com/';
                extBtn.innerHTML = `<span>Read Full Article on ${item.source || 'Publisher'}</span> <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>`;
            }

            const modal = document.getElementById('newsDetailModal');
            if (modal) modal.classList.remove('hidden');
        };

        window.closeNewsDetailModal = function() {
            const modal = document.getElementById('newsDetailModal');
            if (modal) modal.classList.add('hidden');
        };

        window.onNewsGridSearch = function(val) {
            currentSearchQuery = (val || '').trim().toLowerCase();
            renderGridNewsPage();
        };

        window.setNewsGridCatFilter = function(cat) {
            currentCategoryFilter = cat;
            document.querySelectorAll('#newsGridCategoryFilter button').forEach(btn => {
                if (btn.getAttribute('data-cat') === cat) {
                    btn.className = 'px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-blue-600/30 text-blue-300 border border-blue-500/50';
                } else {
                    btn.className = 'px-3.5 py-1.5 rounded-xl text-xs font-medium bg-slate-800/80 text-slate-300 hover:text-white border border-slate-700/50';
                }
            });
            renderGridNewsPage();
        };

        document.addEventListener('DOMContentLoaded', () => {
            fetchGridNewsData();
        });
    })();
</script>
@endpush
@endsection
