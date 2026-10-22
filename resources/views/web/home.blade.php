@extends('layouts.app')

@section('title', 'Kryvex - Crypto Futures & Options')

@push('styles')
    @vite('resources/css/pages/home.css')
@endpush

@push('scripts')
    <script src="https://unpkg.com/lenis@1.1.18/dist/lenis.min.js"></script>
    @vite('resources/js/pages/home.js')
@endpush

@section('content')
<!-- TOP SCROLL PROGRESS BAR -->
<div id="homeScrollProgressBar" style="width: 0%;"></div>

<div class="home-page bg-[#080b11] text-slate-100 min-h-screen relative overflow-hidden">

    <!-- FIXED HOMEPAGE AMBIENT GLOW & GRID LAYER (MATCHING UI SCREENSHOT) -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <!-- Top-Left Indigo & Purple Ambient Glow Aura -->
        <div class="absolute -top-40 -left-20 w-[800px] h-[800px] bg-gradient-to-br from-indigo-600/35 via-purple-700/20 to-transparent rounded-full blur-[140px] opacity-90"></div>
        <!-- Middle-Right Subtle Glow Aura -->
        <div class="absolute top-[35%] -right-20 w-[700px] h-[700px] bg-gradient-to-tl from-purple-600/25 via-indigo-900/20 to-transparent rounded-full blur-[160px] opacity-80"></div>
        <!-- Bottom Ambient Glow Aura -->
        <div class="absolute -bottom-40 left-[20%] w-[600px] h-[600px] bg-gradient-to-t from-purple-900/20 via-indigo-950/15 to-transparent rounded-full blur-[150px] opacity-70"></div>
        
        <!-- Fixed Grid Canvas Layer -->
        <canvas id="cursorGridCanvas" class="w-full h-full opacity-75"></canvas>
    </div>

    <!-- Home loading animation overlay -->
    <div class="home-loading-overlay fixed inset-0 bg-[#080b11]/90 backdrop-blur-md z-50 flex flex-col items-center justify-center gap-3" id="homeLoadingOverlay">
        <div class="loader-spinner w-10 h-10 border-3 border-purple-500/20 border-t-purple-500 rounded-full animate-spin"></div>
        <p class="text-xs font-semibold text-slate-400">Syncing live markets...</p>
    </div>

    <!-- TOP RUNNING CRYPTO TICKER BAR (REACTBITS LOGO LOOP INFINITE MARQUEE ANIMATION) -->
    <div class="ticker-loop-wrapper py-2.5 text-xs font-mono relative z-10">
        <div class="ticker-loop-track flex whitespace-nowrap">
            <!-- COPY 1 -->
            <div class="ticker-loop-content flex items-center gap-6 pr-6 text-slate-300">
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">BTC/USDT</strong> <span data-ticker-price="BTCUSDT">$66,254.40</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="BTCUSDT">+3.17%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">ETH/USDT</strong> <span data-ticker-price="ETHUSDT">$1,939.60</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="ETHUSDT">+4.02%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">BNB/USDT</strong> <span data-ticker-price="BNBUSDT">$579.59</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="BNBUSDT">+2.18%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">SOL/USDT</strong> <span data-ticker-price="SOLUSDT">$78.34</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="SOLUSDT">+2.23%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">XRP/USDT</strong> <span data-ticker-price="XRPUSDT">$1.1341</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="XRPUSDT">+3.95%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">ADA/USDT</strong> <span data-ticker-price="ADAUSDT">$0.1750</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="ADAUSDT">+7.56%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">DOGE/USDT</strong> <span data-ticker-price="DOGEUSDT">$0.0736</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="DOGEUSDT">+2.21%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">AVAX/USDT</strong> <span data-ticker-price="AVAXUSDT">$6.6470</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="AVAXUSDT">+1.54%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">LINK/USDT</strong> <span data-ticker-price="LINKUSDT">$8.7000</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="LINKUSDT">+3.82%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">DOT/USDT</strong> <span data-ticker-price="DOTUSDT">$0.8635</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="DOTUSDT">+7.40%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">LTC/USDT</strong> <span data-ticker-price="LTCUSDT">$47.6000</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="LTCUSDT">+1.91%</em></span>
                <span class="text-slate-700">•</span>
            </div>

            <!-- COPY 2 (SEAMLESS INFINITE LOOP) -->
            <div class="ticker-loop-content flex items-center gap-6 pr-6 text-slate-300" aria-hidden="true">
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">BTC/USDT</strong> <span data-ticker-price="BTCUSDT">$66,254.40</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="BTCUSDT">+3.17%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">ETH/USDT</strong> <span data-ticker-price="ETHUSDT">$1,939.60</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="ETHUSDT">+4.02%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">BNB/USDT</strong> <span data-ticker-price="BNBUSDT">$579.59</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="BNBUSDT">+2.18%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">SOL/USDT</strong> <span data-ticker-price="SOLUSDT">$78.34</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="SOLUSDT">+2.23%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">XRP/USDT</strong> <span data-ticker-price="XRPUSDT">$1.1341</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="XRPUSDT">+3.95%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">ADA/USDT</strong> <span data-ticker-price="ADAUSDT">$0.1750</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="ADAUSDT">+7.56%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">DOGE/USDT</strong> <span data-ticker-price="DOGEUSDT">$0.0736</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="DOGEUSDT">+2.21%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">AVAX/USDT</strong> <span data-ticker-price="AVAXUSDT">$6.6470</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="AVAXUSDT">+1.54%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">LINK/USDT</strong> <span data-ticker-price="LINKUSDT">$8.7000</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="LINKUSDT">+3.82%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">DOT/USDT</strong> <span data-ticker-price="DOTUSDT">$0.8635</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="DOTUSDT">+7.40%</em></span>
                <span class="text-slate-700">•</span>
                <span class="inline-flex items-center gap-1.5"><strong class="text-white">LTC/USDT</strong> <span data-ticker-price="LTCUSDT">$47.6000</span> <em class="text-emerald-400 not-italic font-bold" data-ticker-change="LTCUSDT">+1.91%</em></span>
                <span class="text-slate-700">•</span>
            </div>
        </div>
    </div>

    <div id="homeContent" class="opacity-0 transition-opacity duration-300 relative z-10">
        
        <!-- HERO SECTION -->
        <section class="home-hero pt-12 pb-16 sm:pt-16 sm:pb-20 relative overflow-hidden flex items-center">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 w-full">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                    
                    <!-- Left Hero Text & Call-to-actions -->
                    <div class="lg:col-span-7 space-y-6">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-purple-500/10 border border-purple-500/30 text-purple-300 text-xs font-bold tracking-wider uppercase shadow-[0_0_12px_rgba(168,85,247,0.15)]">
                            <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                            CRYPTO FUTURES & OPTIONS
                        </div>

                        <!-- REACTBITS TEXT-TYPE ANIMATION (typingSpeed: 90, cursorCharacter: ●) -->
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.1] min-h-[120px]" id="heroTypedHeading">
                            Trade digital markets with a <span class="text-purple-400">simple & secure</span> platform.
                        </h1>

                        <p class="text-slate-400 text-base sm:text-lg leading-relaxed max-w-xl">
                            Kryvex brings markets, futures, options, wallet, KYC and account security into one clean, professional trading experience.
                        </p>

                        <div class="flex flex-wrap items-center gap-4 pt-2">
                            <a href="{{ url('/register') }}" class="home-btn-primary inline-flex items-center justify-center bg-[#7c3aed] hover:bg-[#6d28d9] text-white font-bold text-sm px-7 py-3.5 rounded-xl transition-all shadow-lg shadow-purple-950/50 hover:scale-105">
                                Start Trading
                            </a>
                            <a href="{{ url('/markets') }}" class="inline-flex items-center justify-center bg-[#0e131f] hover:bg-slate-800 border border-slate-800 text-white font-bold text-sm px-7 py-3.5 rounded-xl transition-all hover:scale-105">
                                View Markets
                            </a>
                        </div>


                        <!-- 3 Stat Metrics Cards -->
                        <div class="grid grid-cols-3 gap-4 pt-4 max-w-md">
                            <div class="bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 hover:-translate-y-1">
                                <strong class="text-2xl font-black text-white block">100x</strong>
                                <span class="text-xs text-slate-500 block mt-0.5">Max leverage</span>
                            </div>
                            <div class="bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 hover:-translate-y-1">
                                <strong class="text-2xl font-black text-white block">24/7</strong>
                                <span class="text-xs text-slate-500 block mt-0.5">Market access</span>
                            </div>
                            <div class="bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 hover:-translate-y-1">
                                <strong class="text-2xl font-black text-white block">USDT</strong>
                                <span class="text-xs text-slate-500 block mt-0.5">Settlement</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Live Price Sparkline Preview Card (REACTBITS FADE CONTENT ANIMATION WITH 0.5s DELAY) -->
                    <div class="lg:col-span-5">
                        <div class="bg-[#0e131f]/95 border border-slate-800/90 rounded-2xl p-3.5 sm:p-5 shadow-2xl backdrop-blur-md space-y-5 fade-content-card overflow-hidden">
                            
                            <!-- Card Header & Symbol Tabs -->
                            <div class="hero-card-header flex flex-wrap xs:flex-nowrap items-center justify-between gap-2.5 sm:gap-3 pb-3 border-b border-slate-800/60">
                                <div class="min-w-0 flex-1">
                                    <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block" id="heroSymbolLabel">BTCUSDT</span>
                                    <div class="flex items-baseline gap-1.5 sm:gap-2 mt-1">
                                        <strong class="text-xl xs:text-2xl font-black text-white font-mono" id="heroPricePreview">$ --</strong>
                                        <span class="text-xs font-bold font-mono text-emerald-400 whitespace-nowrap" id="heroChangePreview">--%</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1 bg-[#1e2330] p-1 rounded-lg text-[10px] font-bold text-slate-400 flex-shrink-0" id="heroSymbolTabs">
                                    <button type="button" class="hero-tab-btn active bg-purple-600 text-white px-2 sm:px-2.5 py-1 rounded transition-all cursor-pointer" data-symbol="BTCUSDT">BTC</button>
                                    <button type="button" class="hero-tab-btn px-2 sm:px-2.5 py-1 rounded hover:text-white transition-all cursor-pointer" data-symbol="ETHUSDT">ETH</button>
                                    <button type="button" class="hero-tab-btn px-2 sm:px-2.5 py-1 rounded hover:text-white transition-all cursor-pointer" data-symbol="SOLUSDT">SOL</button>
                                    <button type="button" class="hero-tab-btn px-2 sm:px-2.5 py-1 rounded hover:text-white transition-all cursor-pointer" data-symbol="BNBUSDT">BNB</button>
                                </div>
                            </div>

                            <!-- Live SVG Sparkline Chart Graphic -->
                            <div class="h-28 w-full relative cursor-crosshair group" id="heroChartContainer">
                                <svg class="w-full h-full overflow-visible" id="heroChartSvg" viewBox="0 0 300 80" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="chartGradGreen" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.35"/>
                                            <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
                                        </linearGradient>
                                        <linearGradient id="chartGradRed" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#f43f5e" stop-opacity="0.35"/>
                                            <stop offset="100%" stop-color="#f43f5e" stop-opacity="0"/>
                                        </linearGradient>
                                    </defs>
                                    <path id="heroChartFill" d="M0,50 Q40,20 80,45 T160,30 T240,15 T300,5 L300,80 L0,80 Z" fill="url(#chartGradGreen)"/>
                                    <path id="heroChartStroke" d="M0,50 Q40,20 80,45 T160,30 T240,15 T300,5" fill="none" stroke="#10b981" stroke-width="2.5"/>
                                    
                                    <!-- Interactive Hover Crosshair Line & Point Marker -->
                                    <g id="heroChartHoverGroup" class="opacity-0 transition-opacity duration-150 pointer-events-none">
                                        <line id="heroChartCrosshairLine" x1="0" y1="0" x2="0" y2="80" stroke="rgba(255,255,255,0.3)" stroke-width="1.2" stroke-dasharray="3 3"/>
                                        <circle id="heroChartPulseCircle" cx="0" cy="0" r="8" fill="rgba(16,185,129,0.25)"/>
                                        <circle id="heroChartDotCircle" cx="0" cy="0" r="4" fill="#10b981" stroke="#ffffff" stroke-width="1.5"/>
                                    </g>
                                </svg>

                                <!-- Floating Price Tooltip Badge -->
                                <div id="heroChartTooltip" class="absolute pointer-events-none opacity-0 transition-opacity duration-150 bg-slate-900/90 border border-slate-700/80 text-white text-[11px] font-mono font-bold px-2 py-1 rounded-md shadow-xl -translate-x-1/2 -translate-y-full mb-2 z-20 whitespace-nowrap">
                                    <span id="heroChartTooltipPrice">$ --</span>
                                </div>
                            </div>

                            <!-- Popular Contracts Sub-Table -->
                            <div>
                                <div class="flex items-center justify-between text-xs font-bold text-slate-400 mb-3 px-1">
                                    <span class="text-slate-300">Popular contracts</span>
                                    <span class="text-[11px] text-emerald-400 flex items-center gap-1.5">
                                    </span>
                                </div>

                                <div id="homeMarketList" class="space-y-2">
                                    <a href="{{ url('/futures/btcusdt') }}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 hover:bg-slate-800/60 transition-all border border-slate-800/40" id="marketRow_BTCUSDT">
                                        <span class="font-extrabold text-white text-xs">BTCUSDT</span>
                                        <strong class="font-mono text-xs font-bold text-white market-price-val">$66,254.40</strong>
                                        <em class="font-mono text-xs font-bold text-emerald-400 not-italic market-change-val">+3.17%</em>
                                    </a>
                                    <a href="{{ url('/futures/ethusdt') }}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 hover:bg-slate-800/60 transition-all border border-slate-800/40" id="marketRow_ETHUSDT">
                                        <span class="font-extrabold text-white text-xs">ETHUSDT</span>
                                        <strong class="font-mono text-xs font-bold text-white market-price-val">$1,939.60</strong>
                                        <em class="font-mono text-xs font-bold text-emerald-400 not-italic market-change-val">+4.06%</em>
                                    </a>
                                    <a href="{{ url('/futures/solusdt') }}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 hover:bg-slate-800/60 transition-all border border-slate-800/40" id="marketRow_SOLUSDT">
                                        <span class="font-extrabold text-white text-xs">SOLUSDT</span>
                                        <strong class="font-mono text-xs font-bold text-white market-price-val">$78.3400</strong>
                                        <em class="font-mono text-xs font-bold text-emerald-400 not-italic market-change-val">+2.23%</em>
                                    </a>
                                    <a href="{{ url('/futures/bnbusdt') }}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 hover:bg-slate-800/60 transition-all border border-slate-800/40" id="marketRow_BNBUSDT">
                                        <span class="font-extrabold text-white text-xs">BNBUSDT</span>
                                        <strong class="font-mono text-xs font-bold text-white market-price-val">$579.59</strong>
                                        <em class="font-mono text-xs font-bold text-emerald-400 not-italic market-change-val">+2.18%</em>
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- "HOW IT WORKS" 4 STEPS SECTION -->
        <section class="py-16 sm:py-20 flex flex-col justify-center">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                
                <div class="mb-10 sm:mb-12">
                    <p class="text-xs font-bold text-purple-400 tracking-wider uppercase mb-1">HOW IT WORKS</p>
                    <h2 class="text-2xl sm:text-3xl font-black text-white">Start in four simple steps</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Step 01 -->
                    <div class="step-card-wrapper group cursor-pointer animated-content-card" data-delay="0">
                        <div class="step-card bg-[#0e131f]/90 border border-slate-800/90 rounded-2xl p-6 shadow-xl flex flex-col justify-between h-full transition-all duration-300 group-hover:-translate-y-2.5 group-hover:border-purple-500/70 group-hover:bg-[#121827] group-hover:shadow-2xl group-hover:shadow-purple-500/20">
                            <div class="flex items-center justify-between mb-6">
                                <span class="w-9 h-9 rounded-xl bg-slate-800/90 text-purple-400 font-extrabold text-xs flex items-center justify-center border border-slate-700/60 transition-all duration-300 group-hover:scale-110 group-hover:bg-purple-600 group-hover:text-white group-hover:border-purple-400/80 group-hover:shadow-md group-hover:shadow-purple-500/40">01</span>
                                <svg class="w-5 h-5 text-slate-500 transition-all duration-300 group-hover:text-purple-400 group-hover:scale-125 group-hover:rotate-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-white text-base mb-2 transition-colors duration-300 group-hover:text-purple-300">Create account</h3>
                                <p class="text-xs text-slate-400 leading-relaxed transition-colors duration-300 group-hover:text-slate-300">Register with email, mobile number and secure password.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 02 -->
                    <div class="step-card-wrapper group cursor-pointer animated-content-card" data-delay="150">
                        <div class="step-card bg-[#0e131f]/90 border border-slate-800/90 rounded-2xl p-6 shadow-xl flex flex-col justify-between h-full transition-all duration-300 group-hover:-translate-y-2.5 group-hover:border-purple-500/70 group-hover:bg-[#121827] group-hover:shadow-2xl group-hover:shadow-purple-500/20">
                            <div class="flex items-center justify-between mb-6">
                                <span class="w-9 h-9 rounded-xl bg-slate-800/90 text-purple-400 font-extrabold text-xs flex items-center justify-center border border-slate-700/60 transition-all duration-300 group-hover:scale-110 group-hover:bg-purple-600 group-hover:text-white group-hover:border-purple-400/80 group-hover:shadow-md group-hover:shadow-purple-500/40">02</span>
                                <svg class="w-5 h-5 text-slate-500 transition-all duration-300 group-hover:text-purple-400 group-hover:scale-125 group-hover:rotate-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-white text-base mb-2 transition-colors duration-300 group-hover:text-purple-300">Track markets</h3>
                                <p class="text-xs text-slate-400 leading-relaxed transition-colors duration-300 group-hover:text-slate-300">Select any USDT futures contract to view live price movements.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 03 -->
                    <div class="step-card-wrapper group cursor-pointer animated-content-card" data-delay="300">
                        <div class="step-card bg-[#0e131f]/90 border border-slate-800/90 rounded-2xl p-6 shadow-xl flex flex-col justify-between h-full transition-all duration-300 group-hover:-translate-y-2.5 group-hover:border-purple-500/70 group-hover:bg-[#121827] group-hover:shadow-2xl group-hover:shadow-purple-500/20">
                            <div class="flex items-center justify-between mb-6">
                                <span class="w-9 h-9 rounded-xl bg-slate-800/90 text-purple-400 font-extrabold text-xs flex items-center justify-center border border-slate-700/60 transition-all duration-300 group-hover:scale-110 group-hover:bg-purple-600 group-hover:text-white group-hover:border-purple-400/80 group-hover:shadow-md group-hover:shadow-purple-500/40">03</span>
                                <svg class="w-5 h-5 text-slate-500 transition-all duration-300 group-hover:text-purple-400 group-hover:scale-125 group-hover:rotate-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-white text-base mb-2 transition-colors duration-300 group-hover:text-purple-300">Open positions</h3>
                                <p class="text-xs text-slate-400 leading-relaxed transition-colors duration-300 group-hover:text-slate-300">Lock margin, choose leverage, and execute trades instantly.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 04 -->
                    <div class="step-card-wrapper group cursor-pointer animated-content-card" data-delay="450">
                        <div class="step-card bg-[#0e131f]/90 border border-slate-800/90 rounded-2xl p-6 shadow-xl flex flex-col justify-between h-full transition-all duration-300 group-hover:-translate-y-2.5 group-hover:border-purple-500/70 group-hover:bg-[#121827] group-hover:shadow-2xl group-hover:shadow-purple-500/20">
                            <div class="flex items-center justify-between mb-6">
                                <span class="w-9 h-9 rounded-xl bg-slate-800/90 text-purple-400 font-extrabold text-xs flex items-center justify-center border border-slate-700/60 transition-all duration-300 group-hover:scale-110 group-hover:bg-purple-600 group-hover:text-white group-hover:border-purple-400/80 group-hover:shadow-md group-hover:shadow-purple-500/40">04</span>
                                <svg class="w-5 h-5 text-slate-500 transition-all duration-300 group-hover:text-purple-400 group-hover:scale-125 group-hover:rotate-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h3 class="font-extrabold text-white text-base mb-2 transition-colors duration-300 group-hover:text-purple-300">Manage funds</h3>
                                <p class="text-xs text-slate-400 leading-relaxed transition-colors duration-300 group-hover:text-slate-300">Control demo wallet deposits, withdrawals, and ledger balances.</p>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- PLATFORM FEATURES GRID SECTION -->
        <section class="py-16 sm:py-20 flex flex-col justify-center">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10 sm:mb-12">
                    <div>
                        <p class="text-xs font-bold text-purple-400 tracking-wider uppercase mb-1">PLATFORM</p>
                        <h2 class="text-2xl sm:text-3xl font-black text-white">Built for a clean trading experience</h2>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-400 max-w-md">
                        Simple, reliable tools for market tracking, account safety and a structured trading workflow.
                    </p>
                </div>

                <!-- Main Platform Features Grid -->
                <div class="space-y-6">
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        
                        <!-- Left Feature Card: Live market view (FADE CONTENT delay=0.3s) -->
                        <a href="{{ url('/markets') }}" class="lg:col-span-5 bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-4 sm:p-6 flex flex-col justify-between min-h-[280px] sm:min-h-[300px] relative overflow-hidden transition-all hover:-translate-y-1 hover:border-purple-500/40 group fade-content-scroll-card" data-delay="300">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <span class="w-9 h-9 rounded-xl bg-purple-950/60 border border-purple-800/50 text-purple-400 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/></svg>
                                    </span>
                                    <span class="text-slate-500 font-bold text-sm group-hover:text-purple-400 transition-colors">↗</span>
                                </div>
                                <h3 class="font-extrabold text-white text-base sm:text-lg mb-2">Live market view</h3>
                                <p class="text-xs text-slate-400 leading-relaxed max-w-sm">
                                    Track top futures contracts, real-time price movement, 24h volume and overall market activity — all in one live view built for fast decisions.
                                </p>
                            </div>
                            
                            <!-- High-Tech Live Market Stream & Depth Visualizer -->
                            <div class="pt-4 sm:pt-6">
                                <div class="bg-[#090d16]/90 border border-slate-800/80 rounded-xl p-2.5 sm:p-3.5 space-y-2 sm:space-y-2.5 backdrop-blur-sm shadow-inner">
                                    <div class="flex items-center justify-between text-[10px] sm:text-[11px] font-bold text-slate-400 border-b border-slate-800/60 pb-2">
                                        <span class="flex items-center gap-1.5 text-slate-200">
                                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                            Live Market Feed
                                        </span>
                                        <span class="font-mono text-purple-400 text-[10px]">24h Vol $1.2B</span>
                                    </div>
                                    
                                    <!-- 2 Mini Pair Rows -->
                                    <div class="space-y-1.5">
                                        <div class="flex items-center justify-between gap-1 text-xs p-1.5 sm:p-2 rounded-lg bg-slate-900/80 border border-slate-800/50">
                                            <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                                                <span class="font-bold text-white text-[11px] sm:text-xs">BTC/USDT</span>
                                                <span class="text-[9px] sm:text-[10px] px-1 sm:px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-semibold">Perp</span>
                                            </div>
                                            <div class="flex items-center gap-1 sm:gap-2 font-mono text-[11px] sm:text-xs flex-shrink-0">
                                                <span class="text-white font-bold" data-ticker-price="BTCUSDT">$66,254.40</span>
                                                <span class="text-emerald-400 font-bold text-[10px] sm:text-xs whitespace-nowrap" data-ticker-change="BTCUSDT">+3.17%</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between gap-1 text-xs p-1.5 sm:p-2 rounded-lg bg-slate-900/80 border border-slate-800/50">
                                            <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                                                <span class="font-bold text-white text-[11px] sm:text-xs">ETH/USDT</span>
                                                <span class="text-[9px] sm:text-[10px] px-1 sm:px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 font-semibold">Perp</span>
                                            </div>
                                            <div class="flex items-center gap-1 sm:gap-2 font-mono text-[11px] sm:text-xs flex-shrink-0">
                                                <span class="text-white font-bold" data-ticker-price="ETHUSDT">$1,939.60</span>
                                                <span class="text-emerald-400 font-bold text-[10px] sm:text-xs whitespace-nowrap" data-ticker-change="ETHUSDT">+4.02%</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Glowing Depth Meter Bar -->
                                    <div class="pt-1">
                                        <div class="flex items-center justify-between text-[9.5px] sm:text-[10px] text-slate-400 font-bold mb-1">
                                            <span class="text-emerald-400">Buyers 64%</span>
                                            <span class="text-rose-400">Sellers 36%</span>
                                        </div>
                                        <div class="h-1.5 w-full bg-rose-500/30 rounded-full overflow-hidden flex">
                                            <div class="h-full bg-emerald-500 rounded-full shadow-[0_0_8px_rgba(16,185,129,0.5)]" style="width: 64%;"></div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </a>

                        <!-- Right 6 Features Grid (FADE CONTENT STAGGERED DELAYS 0.4s to 0.75s) -->
                        <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 [perspective:1000px]">
                            
                            <!-- Futures terminal -->
                            <a href="{{ url('/futures') }}" class="feature-rotate-card rotate-left-top bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-4 sm:p-6 flex flex-col justify-between min-h-[120px] sm:min-h-[140px] group fade-content-scroll-card" data-delay="400">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="icon-badge w-8 h-8 rounded-lg bg-slate-800 text-purple-400 flex items-center justify-center border border-slate-700/50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                                        </span>
                                        <span class="arrow-icon text-slate-500 font-bold text-xs inline-block">↗</span>
                                    </div>
                                    <h3 class="font-extrabold text-white text-sm mb-1 transition-colors group-hover:text-purple-300">Futures terminal</h3>
                                    <p class="text-xs text-slate-400 leading-relaxed transition-colors group-hover:text-slate-300">Use chart, order book, recent trades and order preview in one workspace.</p>
                                </div>
                            </a>

                            <!-- Options chain -->
                            <a href="{{ url('/options') }}" class="feature-rotate-card rotate-right-top bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-4 sm:p-6 flex flex-col justify-between min-h-[120px] sm:min-h-[140px] group fade-content-scroll-card" data-delay="450">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="icon-badge w-8 h-8 rounded-lg bg-slate-800 text-purple-400 flex items-center justify-center border border-slate-700/50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                                        </span>
                                        <span class="arrow-icon text-slate-500 font-bold text-xs inline-block">↗</span>
                                    </div>
                                    <h3 class="font-extrabold text-white text-sm mb-1 transition-colors group-hover:text-purple-300">Options chain</h3>
                                    <p class="text-xs text-slate-400 leading-relaxed transition-colors group-hover:text-slate-300">View calls, puts, strikes, expiry and option price data in a clean table.</p>
                                </div>
                            </a>

                            <!-- Portfolio & Analytics (NEW) -->
                            <a href="{{ url('/portfolio') }}" class="feature-rotate-card rotate-left-bottom bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-4 sm:p-6 flex flex-col justify-between min-h-[120px] sm:min-h-[140px] group fade-content-scroll-card" data-delay="500">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="icon-badge w-8 h-8 rounded-lg bg-slate-800 text-purple-400 flex items-center justify-center border border-slate-700/50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                                        </span>
                                        <span class="arrow-icon text-slate-500 font-bold text-xs inline-block">↗</span>
                                    </div>
                                    <h3 class="font-extrabold text-white text-sm mb-1 transition-colors group-hover:text-purple-300">Portfolio & Analytics</h3>
                                    <p class="text-xs text-slate-400 leading-relaxed transition-colors group-hover:text-slate-300">Track open position margins, real-time unrealized PnL, asset breakdown and trade history.</p>
                                </div>
                            </a>

                            <!-- Wallet ledger -->
                            <a href="{{ url('/wallet') }}" class="feature-rotate-card rotate-right-bottom bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-4 sm:p-6 flex flex-col justify-between min-h-[120px] sm:min-h-[140px] group fade-content-scroll-card" data-delay="550">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="icon-badge w-8 h-8 rounded-lg bg-slate-800 text-purple-400 flex items-center justify-center border border-slate-700/50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        </span>
                                        <span class="arrow-icon text-slate-500 font-bold text-xs inline-block">↗</span>
                                    </div>
                                    <h3 class="font-extrabold text-white text-sm mb-1 transition-colors group-hover:text-purple-300">Wallet ledger</h3>
                                    <p class="text-xs text-slate-400 leading-relaxed transition-colors group-hover:text-slate-300">Every wallet request and balance movement stays traceable through ledger records.</p>
                                </div>
                            </a>

                            <!-- KYC ready -->
                            <a href="{{ url('/settings?tab=kyc') }}" class="feature-rotate-card rotate-left-bottom bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-4 sm:p-6 flex flex-col justify-between min-h-[120px] sm:min-h-[140px] group fade-content-scroll-card" data-delay="600">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="icon-badge w-8 h-8 rounded-lg bg-slate-800 text-purple-400 flex items-center justify-center border border-slate-700/50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                        </span>
                                        <span class="arrow-icon text-slate-500 font-bold text-xs inline-block">↗</span>
                                    </div>
                                    <h3 class="font-extrabold text-white text-sm mb-1 transition-colors group-hover:text-purple-300">KYC ready</h3>
                                    <p class="text-xs text-slate-400 leading-relaxed transition-colors group-hover:text-slate-300">User identity verification flow keeps wallet and trading access controlled.</p>
                                </div>
                            </a>

                            <!-- Trading Academy & Learn (NEW) -->
                            <a href="{{ url('/learn') }}" class="feature-rotate-card rotate-right-bottom bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-4 sm:p-6 flex flex-col justify-between min-h-[120px] sm:min-h-[140px] group fade-content-scroll-card" data-delay="650">
                                <div>
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="icon-badge w-8 h-8 rounded-lg bg-slate-800 text-purple-400 flex items-center justify-center border border-slate-700/50">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                                        </span>
                                        <span class="arrow-icon text-slate-500 font-bold text-xs inline-block">↗</span>
                                    </div>
                                    <h3 class="font-extrabold text-white text-sm mb-1 transition-colors group-hover:text-purple-300">Trading Academy</h3>
                                    <p class="text-xs text-slate-400 leading-relaxed transition-colors group-hover:text-slate-300">Explore guides on futures, leverage, option payoff strategies and margin rules.</p>
                                </div>
                            </a>

                        </div>
                    </div>

                    <!-- Bottom Wide Card: Account security (Informational purpose, no action link) -->
                    <div class="bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-6 fade-content-scroll-card" data-delay="800">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                            <div class="flex items-center gap-4">
                                <span class="w-10 h-10 rounded-xl bg-slate-800 text-purple-400 flex items-center justify-center flex-shrink-0 border border-slate-700/60">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                </span>
                                <div>
                                    <h3 class="font-bold text-white text-base">Account security</h3>
                                    <div class="flex flex-wrap items-center gap-2 mt-2">
                                        <span class="px-2.5 py-0.5 rounded-full bg-slate-900 border border-slate-800 text-[11px] font-bold text-slate-300">Email verification</span>
                                        <span class="px-2.5 py-0.5 rounded-full bg-slate-900 border border-slate-800 text-[11px] font-bold text-slate-300">Password reset</span>
                                        <span class="px-2.5 py-0.5 rounded-full bg-slate-900 border border-slate-800 text-[11px] font-bold text-slate-300">Session control</span>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-400 max-w-sm text-left md:text-right">
                                Every account is protected end-to-end — from sign-in verification to full session control.
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <!-- PORTFOLIO & PERFORMANCE INTELLIGENCE SHOWCASE SECTION -->
        <section class="py-16 sm:py-20 flex flex-col justify-center relative overflow-hidden">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full relative z-10">
                
                <!-- Section Header -->
                <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-10 sm:mb-12">
                    <div>
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/30 text-purple-400 text-xs font-bold uppercase tracking-wider mb-2">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                            <span>PORTFOLIO INTELLIGENCE</span>
                        </div>
                        <h2 class="text-2xl sm:text-4xl font-black text-white">Track, analyze, and optimize your trading</h2>
                    </div>
                    <p class="text-xs sm:text-sm text-slate-400 max-w-md">
                        Real-time unrealized PnL, margin utilization, asset diversification, and trade history — unified in one clear dashboard.
                    </p>
                </div>

                <!-- 2-Column Showcase Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
                    
                    <!-- Left: Interactive Live Portfolio Card Visualizer -->
                    <div class="lg:col-span-6 bg-[#0e131f]/95 border border-purple-900/40 rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden backdrop-blur-md fade-content-scroll-card portfolio-interactive-card group" data-delay="100">
                        <!-- Top Ambient Glow -->
                        <div class="absolute -top-24 -left-24 w-64 h-64 bg-purple-600/15 rounded-full blur-3xl pointer-events-none transition-opacity duration-500 group-hover:opacity-100"></div>
                        <div class="absolute -bottom-24 -right-24 w-64 h-64 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none transition-opacity duration-500 group-hover:opacity-100"></div>

                        <!-- Card Header -->
                        <div class="border-b border-slate-800/80 pb-4 sm:pb-5 mb-5 sm:mb-6">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <span class="text-[10px] sm:text-[11px] font-bold text-slate-400 uppercase tracking-wider">Estimated Total Balance</span>
                               
                            </div>
                            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                                <span class="text-2xl sm:text-3xl font-black text-white tracking-tight">$24,850.00</span>
                                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[11px] sm:text-xs font-bold border border-emerald-500/30">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>
                                    +$1,420.50 (+18.4%)
                                </span>
                            </div>
                        </div>

                        <!-- Center: Donut Chart & Allocation Breakdown -->
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-5 sm:gap-6 items-center">
                            
                            <!-- SVG Donut Chart (5 Cols) -->
                            <div class="sm:col-span-5 flex flex-col items-center justify-center relative my-1 sm:my-0">
                                <div class="relative w-32 h-32 sm:w-40 sm:h-40">
                                    <svg id="homePortfolioDonutSvg" class="w-full h-full -rotate-90" viewBox="0 0 120 120">
                                        <!-- Background Circle -->
                                        <circle cx="60" cy="60" r="48" fill="none" stroke="#1e293b" stroke-width="14" />
                                        
                                        <!-- BTC 52% (Dasharray: 52% of 301.6 = 156.8, Offset: 0) -->
                                        <circle cx="60" cy="60" r="48" fill="none" stroke="#a855f7" stroke-width="14" 
                                                stroke-dasharray="156.8 301.6" stroke-dashoffset="0"
                                                data-portfolio-segment="btc"
                                                data-asset-name="BTC Perp"
                                                data-asset-val="$12,922"
                                                data-asset-pct="52%"
                                                class="portfolio-donut-segment text-[#a855f7]" />
                                        
                                        <!-- ETH 28% (Dasharray: 28% of 301.6 = 84.4, Offset: -156.8) -->
                                        <circle cx="60" cy="60" r="48" fill="none" stroke="#10b981" stroke-width="14" 
                                                stroke-dasharray="84.4 301.6" stroke-dashoffset="-156.8"
                                                data-portfolio-segment="eth"
                                                data-asset-name="ETH Perp"
                                                data-asset-val="$6,958"
                                                data-asset-pct="28%"
                                                class="portfolio-donut-segment text-[#10b981]" />
                                        
                                        <!-- USDT 20% (Dasharray: 20% of 301.6 = 60.3, Offset: -241.2) -->
                                        <circle cx="60" cy="60" r="48" fill="none" stroke="#06b6d4" stroke-width="14" 
                                                stroke-dasharray="60.3 301.6" stroke-dashoffset="-241.2"
                                                data-portfolio-segment="usdt"
                                                data-asset-name="USDT Margin"
                                                data-asset-val="$4,970"
                                                data-asset-pct="20%"
                                                class="portfolio-donut-segment text-[#06b6d4]" />
                                    </svg>
                                    
                                    <!-- Inside Donut Label -->
                                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none transition-all duration-300">
                                        <span id="portfolioDonutLabel" class="text-[9px] sm:text-[10px] uppercase font-bold text-slate-400">Equity</span>
                                        <span id="portfolioDonutValue" class="text-xs sm:text-sm font-black text-white tracking-tight transition-all duration-300">$24.8k</span>
                                        <span id="portfolioDonutSub" class="text-[9px] sm:text-[10px] font-bold text-emerald-400">3 Assets</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Asset Breakdown Bars (7 Cols) -->
                            <div class="sm:col-span-7 space-y-2.5 sm:space-y-3.5">
                                
                                <!-- BTC Row -->
                                <div class="portfolio-asset-row bg-slate-900/70 border border-slate-800/80 rounded-xl p-2 sm:p-2.5 space-y-1.5" data-portfolio-asset="btc">
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-1.5 sm:gap-2 font-bold text-white">
                                            <span class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-purple-500 shadow-[0_0_8px_rgba(168,85,247,0.8)]"></span>
                                            <span class="text-[11px] sm:text-xs">BTC Perp</span>
                                            <span class="text-[10px] font-medium text-slate-400">52%</span>
                                        </div>
                                        <span class="font-mono font-bold text-white text-[11px] sm:text-xs">$12,922.00</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-purple-500 rounded-full progress-fill text-purple-500" style="width: 52%;"></div>
                                    </div>
                                </div>

                                <!-- ETH Row -->
                                <div class="portfolio-asset-row bg-slate-900/70 border border-slate-800/80 rounded-xl p-2 sm:p-2.5 space-y-1.5" data-portfolio-asset="eth">
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-1.5 sm:gap-2 font-bold text-white">
                                            <span class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(16,185,129,0.8)]"></span>
                                            <span class="text-[11px] sm:text-xs">ETH Perp</span>
                                            <span class="text-[10px] font-medium text-slate-400">28%</span>
                                        </div>
                                        <span class="font-mono font-bold text-white text-[11px] sm:text-xs">$6,958.00</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-400 rounded-full progress-fill text-emerald-400" style="width: 28%;"></div>
                                    </div>
                                </div>

                                <!-- USDT Row -->
                                <div class="portfolio-asset-row bg-slate-900/70 border border-slate-800/80 rounded-xl p-2 sm:p-2.5 space-y-1.5" data-portfolio-asset="usdt">
                                    <div class="flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-1.5 sm:gap-2 font-bold text-white">
                                            <span class="w-2 sm:w-2.5 h-2 sm:h-2.5 rounded-full bg-cyan-400 shadow-[0_0_8px_rgba(6,182,212,0.8)]"></span>
                                            <span class="text-[11px] sm:text-xs">USDT Margin</span>
                                            <span class="text-[10px] font-medium text-slate-400">20%</span>
                                        </div>
                                        <span class="font-mono font-bold text-white text-[11px] sm:text-xs">$4,970.00</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
                                        <div class="h-full bg-cyan-400 rounded-full progress-fill text-cyan-400" style="width: 20%;"></div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- Bottom Quick Metrics Row -->
                        <div class="grid grid-cols-3 gap-2 sm:gap-3 mt-5 sm:mt-6 pt-4 sm:pt-5 border-t border-slate-800/80">
                            <div class="portfolio-metric-badge text-center p-2 sm:p-2.5 rounded-xl bg-slate-900/50 border border-slate-800/60 cursor-pointer">
                                <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase block mb-0.5 truncate">Margin Ratio</span>
                                <span class="text-[11px] sm:text-sm font-black text-emerald-400 whitespace-nowrap">22.4% (Safe)</span>
                            </div>
                            <div class="portfolio-metric-badge text-center p-2 sm:p-2.5 rounded-xl bg-slate-900/50 border border-slate-800/60 cursor-pointer">
                                <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase block mb-0.5 truncate">Win Rate</span>
                                <span class="text-[11px] sm:text-sm font-black text-purple-300 whitespace-nowrap">76.5%</span>
                            </div>
                            <div class="portfolio-metric-badge text-center p-2 sm:p-2.5 rounded-xl bg-slate-900/50 border border-slate-800/60 cursor-pointer">
                                <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase block mb-0.5 truncate">Active Trades</span>
                                <span class="text-[11px] sm:text-sm font-black text-white whitespace-nowrap">3 Positions</span>
                            </div>
                        </div>

                    </div>

                    <!-- Right: Features Description & Direct CTA -->
                    <div class="lg:col-span-6 space-y-8">
                        
                        <div class="space-y-4">
                            <h3 class="text-2xl sm:text-3xl font-black text-white leading-snug">
                                Complete clarity over your capital, risk & profitability
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-400 leading-relaxed">
                                No complicated spreadsheets or hidden math. The Kryvex Portfolio hub gives you real-time visibility into every dollar allocated across perpetual futures, option contracts, and liquid cash.
                            </p>
                        </div>

                        <!-- 3 Feature Points -->
                        <div class="space-y-4">
                            
                            <!-- Point 1 -->
                            <div class="portfolio-feature-item flex items-start gap-4 p-4 rounded-2xl bg-[#0e131f]/80 border border-slate-800/80 fade-content-scroll-card group" data-delay="200">
                                <span class="feature-icon-wrapper w-10 h-10 rounded-xl bg-purple-950/60 border border-purple-800/50 text-purple-400 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                </span>
                                <div>
                                    <h4 class="font-extrabold text-white text-sm mb-1 transition-colors group-hover:text-purple-300">Live Unrealized PnL & Margin Health</h4>
                                    <p class="text-xs text-slate-400 leading-relaxed transition-colors group-hover:text-slate-300">Instantly check your floating profit/loss, liquidation buffer, and margin health before market swings catch you off guard.</p>
                                </div>
                            </div>

                            <!-- Point 2 -->
                            <div class="portfolio-feature-item flex items-start gap-4 p-4 rounded-2xl bg-[#0e131f]/80 border border-slate-800/80 fade-content-scroll-card group" data-delay="300">
                                <span class="feature-icon-wrapper w-10 h-10 rounded-xl bg-emerald-950/60 border border-emerald-800/50 text-emerald-400 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"/></svg>
                                </span>
                                <div>
                                    <h4 class="font-extrabold text-white text-sm mb-1 transition-colors group-hover:text-emerald-300">Visual Asset Allocation & Rebalancing</h4>
                                    <p class="text-xs text-slate-400 leading-relaxed transition-colors group-hover:text-slate-300">See exact percentage distribution between Bitcoin, Ethereum, Altcoins and available stablecoin cash at a glance.</p>
                                </div>
                            </div>

                            <!-- Point 3 -->
                            <div class="portfolio-feature-item flex items-start gap-4 p-4 rounded-2xl bg-[#0e131f]/80 border border-slate-800/80 fade-content-scroll-card group" data-delay="400">
                                <span class="feature-icon-wrapper w-10 h-10 rounded-xl bg-cyan-950/60 border border-cyan-800/50 text-cyan-400 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </span>
                                <div>
                                    <h4 class="font-extrabold text-white text-sm mb-1 transition-colors group-hover:text-cyan-300">Detailed Trade Logs & Performance History</h4>
                                    <p class="text-xs text-slate-400 leading-relaxed transition-colors group-hover:text-slate-300">Audit past executed orders, realized profits, entry/exit prices and fee breakdowns in comprehensive ledger tables.</p>
                                </div>
                            </div>

                        </div>

                        <!-- Action Link Buttons -->
                        <div class="flex flex-wrap items-center gap-4 pt-2">
                            <a href="{{ url('/portfolio') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-purple-600 to-indigo-600 text-white font-bold text-xs shadow-lg shadow-purple-950/50 hover:scale-105 hover:shadow-purple-500/30 transition-all">
                                <span>View Portfolio </span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                            <a href="{{ url('/learn') }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-slate-900/90 border border-slate-800 text-slate-300 font-bold text-xs hover:border-purple-500/50 hover:text-white transition-all">
                                <span>Learn Risk & Margin Rules</span>
                                <span>→</span>
                            </a>
                        </div>

                    </div>

                </div>

            </div>
        </section>

        <!-- MOBILE APP SHOWCASE SECTION -->
        <section class="py-16 sm:py-20 flex flex-col justify-center">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                
                <div class="bg-[#0e131f]/90 border border-purple-900/30 rounded-3xl p-8 sm:p-12 shadow-2xl relative overflow-hidden grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                    
                    <!-- Left Details Content -->
                    <div class="lg:col-span-7 space-y-6">
                        <span class="inline-block px-3 py-1 rounded-full bg-purple-950/60 border border-purple-800/40 text-purple-400 text-xs font-semibold">
                            Mobile app · Coming soon
                        </span>
                        <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tight">
                            Trade on the go with <span class="text-purple-400">Kryvex Mobile</span>
                        </h2>
                        <p class="text-slate-400 text-sm leading-relaxed max-w-lg">
                            Lightning-fast trades, real-time alerts, advanced charting and portfolio management — all from your pocket.
                        </p>

                        <ul class="space-y-2.5 text-xs font-semibold text-slate-300">
                            <li class="flex items-center gap-2.5">
                                <span class="text-purple-400 font-bold">✓</span>
                                <span>Futures trading on mobile</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-purple-400 font-bold">✓</span>
                                <span>Live charts & price alerts</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-purple-400 font-bold">✓</span>
                                <span>Instant deposits & withdrawals</span>
                            </li>
                            <li class="flex items-center gap-2.5">
                                <span class="text-purple-400 font-bold">✓</span>
                                <span>Push notifications</span>
                            </li>
                        </ul>

                        <div class="flex items-center gap-4 pt-2">
                            <a href="{{ url('/futures') }}" class="inline-flex items-center justify-center bg-[#7c3aed] text-white font-bold text-xs px-5 py-2.5 rounded-xl shadow-lg shadow-purple-950/50 hover:scale-105 transition-all">
                                Open Futures
                            </a>
                            <a href="{{ url('/markets') }}" class="inline-flex items-center justify-center bg-[#080b11] border border-slate-800 text-white font-bold text-xs px-5 py-2.5 rounded-xl hover:scale-105 transition-all">
                                Live Markets
                            </a>
                        </div>
                    </div>

                    <!-- Right High-Tech Interactive Smartphone Device Mockup -->
                    <div class="lg:col-span-5 flex justify-center items-center py-2">
                        <div class="relative group max-w-[290px] sm:max-w-[310px] w-full">
                            
                            <!-- Ambient Purple/Blue Glowing Aura behind phone -->
                            <div class="absolute -inset-4 bg-gradient-to-tr from-purple-600/35 via-indigo-500/25 to-blue-500/35 rounded-[48px] blur-2xl opacity-70 group-hover:opacity-100 transition-all duration-500"></div>

                            <!-- Phone Device Frame -->
                            <div class="relative bg-[#070b13] border-4 border-slate-700/80 rounded-[42px] p-3.5 shadow-2xl shadow-purple-950/60 ring-1 ring-white/10 transition-all duration-500 group-hover:-translate-y-3 group-hover:border-purple-500/80 group-hover:shadow-purple-500/30">
                                
                                <!-- Phone Top Dynamic Island / Notch -->
                                <div class="w-24 h-4 bg-[#03060a] rounded-full mx-auto mb-3 flex items-center justify-center gap-2 border border-slate-800/80 shadow-inner">
                                    <span class="w-2.5 h-2.5 rounded-full bg-slate-900 border border-slate-700"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-purple-500/80 animate-pulse"></span>
                                </div>

                                <!-- Phone Screen Inner UI Container -->
                                <div class="bg-[#0b0f1a] border border-slate-800/80 rounded-[30px] p-3.5 space-y-3 shadow-inner relative overflow-hidden flex flex-col justify-between min-h-[400px]">
                                    
                                    <!-- Screen Header Status Bar -->
                                    <div class="flex items-center justify-between text-[11px] font-extrabold text-slate-300 border-b border-slate-800/60 pb-2 flex-shrink-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                                            <span class="text-white font-extrabold tracking-wide" id="phoneAppHeaderTitle">Kryvex Mobile</span>
                                        </div>
                                        <span class="text-[10px] font-mono text-purple-400 bg-purple-950/60 px-2 py-0.5 rounded-full border border-purple-800/40" id="phoneAppHeaderBadge">LIVE</span>
                                    </div>

                                    <!-- TAB 1: HOME SCREEN -->
                                    <div class="phone-tab-content block space-y-3 transition-all duration-300" id="phoneTabContent_home">
                                        <!-- Total Balance Card -->
                                        <div class="bg-gradient-to-br from-[#121828] to-[#0d121f] border border-slate-800/90 rounded-2xl p-3.5 space-y-1 shadow-md relative overflow-hidden">
                                            <div class="absolute -right-4 -bottom-4 w-20 h-20 bg-purple-500/10 rounded-full blur-xl pointer-events-none"></div>
                                            <span class="text-[9px] text-slate-400 uppercase tracking-wider font-bold block">Portfolio Balance</span>
                                            <strong class="font-mono text-xl font-black text-white block tracking-tight" id="phoneBalancePreview">$12,480.50</strong>
                                            <div class="flex items-center gap-2 pt-0.5">
                                                <span class="text-[10px] font-mono font-extrabold text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/20">+$642.20 (24h)</span>
                                                <span class="text-[9px] text-slate-400 font-bold">+5.43%</span>
                                            </div>
                                        </div>

                                        <!-- Live Ticker Row -->
                                        <div class="bg-[#121826]/90 border border-slate-800/90 rounded-xl p-2.5 flex items-center justify-between shadow-sm">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-400 font-extrabold text-[10px] flex items-center justify-center shadow-sm">
                                                    ₿
                                                </div>
                                                <div>
                                                    <span class="font-black text-white text-[11px] block leading-tight">BTCUSDT</span>
                                                    <span class="text-[9px] font-bold text-slate-400 block">USDT Perp</span>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <strong class="font-mono text-xs text-white font-bold block" id="phoneBtcPreview">$66,279.80</strong>
                                                <span class="font-mono text-[10px] font-extrabold text-emerald-400 block" id="phoneBtcChangePreview">+3.21%</span>
                                            </div>
                                        </div>

                                        <!-- Live Candlestick Wave SVG Chart -->
                                        <div class="bg-[#0e1320] border border-slate-800/80 rounded-xl p-2 h-14 w-full relative overflow-hidden flex items-center justify-center">
                                            <svg class="w-full h-full overflow-visible" viewBox="0 0 200 45" preserveAspectRatio="none">
                                                <defs>
                                                    <linearGradient id="phoneChartGrad" x1="0" y1="0" x2="0" y2="1">
                                                        <stop offset="0%" stop-color="#10b981" stop-opacity="0.4"/>
                                                        <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
                                                    </linearGradient>
                                                </defs>
                                                <path d="M0,35 Q25,12 50,28 T100,10 T150,22 T200,5 L200,45 L0,45 Z" fill="url(#phoneChartGrad)"/>
                                                <path d="M0,35 Q25,12 50,28 T100,10 T150,22 T200,5" fill="none" stroke="#10b981" stroke-width="2.5"/>
                                            </svg>
                                        </div>

                                        <!-- Action Buttons: Long & Short -->
                                        <div class="grid grid-cols-2 gap-2 pt-0.5">
                                            <button type="button" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs py-2 rounded-xl shadow-md shadow-emerald-500/20 cursor-pointer transition-all active:scale-95 text-center">
                                                LONG
                                            </button>
                                            <button type="button" class="bg-rose-500 hover:bg-rose-400 text-white font-black text-xs py-2 rounded-xl shadow-md shadow-rose-500/20 cursor-pointer transition-all active:scale-95 text-center">
                                                SHORT
                                            </button>
                                        </div>
                                    </div>

                                    <!-- TAB 2: FUTURES SCREEN -->
                                    <div class="phone-tab-content hidden space-y-2.5 transition-all duration-300" id="phoneTabContent_futures">
                                        <div class="flex items-center justify-between bg-[#121828] border border-slate-800 p-2 rounded-xl">
                                            <div class="flex items-center gap-1.5">
                                                <span class="font-extrabold text-white text-xs">BTCUSDT</span>
                                                <span class="text-[9px] px-1.5 py-0.2 rounded bg-purple-500/20 text-purple-300 font-extrabold">100x</span>
                                            </div>
                                            <span class="font-mono text-xs text-emerald-400 font-bold">$66,279.80</span>
                                        </div>

                                        <!-- Mini Orderbook Dummy Screen -->
                                        <div class="bg-[#0e1320] border border-slate-800/80 rounded-xl p-2.5 space-y-1.5 font-mono text-[10px]">
                                            <div class="flex justify-between text-slate-500 font-sans text-[9px] font-bold border-b border-slate-800/60 pb-1">
                                                <span>PRICE (USDT)</span>
                                                <span>QTY (BTC)</span>
                                            </div>
                                            <div class="flex justify-between text-rose-400 font-bold">
                                                <span>66,285.00</span>
                                                <span>1.240</span>
                                            </div>
                                            <div class="flex justify-between text-rose-400/80">
                                                <span>66,282.40</span>
                                                <span>0.850</span>
                                            </div>
                                            <div class="flex justify-between text-emerald-400 font-bold border-t border-slate-800/60 pt-1">
                                                <span>66,279.80</span>
                                                <span>2.100</span>
                                            </div>
                                            <div class="flex justify-between text-emerald-400/80">
                                                <span>66,275.50</span>
                                                <span>1.450</span>
                                            </div>
                                        </div>

                                        <div class="bg-[#121828] border border-slate-800/80 p-2 rounded-xl space-y-1.5">
                                            <div class="flex justify-between text-[10px] text-slate-400">
                                                <span>Leverage Margin:</span>
                                                <span class="text-white font-bold">$33.14 USDT</span>
                                            </div>
                                            <div class="grid grid-cols-2 gap-1.5 pt-0.5">
                                                <button type="button" class="bg-emerald-500/90 text-slate-950 font-black text-[10px] py-1.5 rounded-lg">Buy Long</button>
                                                <button type="button" class="bg-rose-500/90 text-white font-black text-[10px] py-1.5 rounded-lg">Sell Short</button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- TAB 3: WALLET SCREEN -->
                                    <div class="phone-tab-content hidden space-y-2.5 transition-all duration-300" id="phoneTabContent_wallet">
                                        <div class="bg-gradient-to-br from-[#131a2c] to-[#0e1423] border border-purple-900/40 p-3 rounded-2xl space-y-1">
                                            <span class="text-[9px] text-slate-400 uppercase font-bold block">Estimated Balance</span>
                                            <strong class="font-mono text-xl font-black text-white block">$18,920.00</strong>
                                            <span class="text-[9px] text-purple-400 font-bold block">Demo Wallet Account</span>
                                        </div>

                                        <div class="space-y-1.5">
                                            <div class="flex items-center justify-between p-2 rounded-xl bg-[#121826] border border-slate-800/60 text-xs">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-5 h-5 rounded-full bg-emerald-500/20 text-emerald-400 font-bold text-[9px] flex items-center justify-center">₮</span>
                                                    <span class="font-extrabold text-white text-[11px]">USDT</span>
                                                </div>
                                                <span class="font-mono text-xs text-white font-bold">$14,250.00</span>
                                            </div>
                                            <div class="flex items-center justify-between p-2 rounded-xl bg-[#121826] border border-slate-800/60 text-xs">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-5 h-5 rounded-full bg-amber-500/20 text-amber-400 font-bold text-[9px] flex items-center justify-center">₿</span>
                                                    <span class="font-extrabold text-white text-[11px]">BTC</span>
                                                </div>
                                                <span class="font-mono text-xs text-white font-bold">0.065 BTC</span>
                                            </div>
                                            <div class="flex items-center justify-between p-2 rounded-xl bg-[#121826] border border-slate-800/60 text-xs">
                                                <div class="flex items-center gap-2">
                                                    <span class="w-5 h-5 rounded-full bg-blue-500/20 text-blue-400 font-bold text-[9px] flex items-center justify-center">Ξ</span>
                                                    <span class="font-extrabold text-white text-[11px]">ETH</span>
                                                </div>
                                                <span class="font-mono text-xs text-white font-bold">0.180 ETH</span>
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between gap-1.5 pt-1">
                                            <button type="button" class="flex-1 bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/50 font-black text-[10px] py-1.5 rounded-xl text-center transition-all cursor-pointer" data-action-withdraw>Withdraw</button>
                                            <button type="button" class="flex-1 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/50 font-black text-[10px] py-1.5 rounded-xl text-center transition-all cursor-pointer" data-action-deposit>Deposit</button>
                                            <button type="button" class="flex-1 bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border border-amber-500/50 font-black text-[10px] py-1.5 rounded-xl text-center transition-all cursor-pointer" data-action-transfer>Transfer</button>
                                        </div>
                                    </div>

                                    <!-- TAB 4: PROFILE SCREEN -->
                                    <div class="phone-tab-content hidden space-y-2.5 transition-all duration-300" id="phoneTabContent_profile">
                                        <div class="bg-[#121826] border border-slate-800/80 p-3 rounded-2xl flex items-center gap-3">
                                            <div class="w-10 h-10 rounded-full bg-purple-600 text-white font-black text-sm flex items-center justify-center shadow-md">
                                                KV
                                            </div>
                                            <div>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-extrabold text-white text-xs">User #84920</span>
                                                    <span class="text-[9px] bg-emerald-500/20 text-emerald-400 px-1.5 py-0.2 rounded font-extrabold">VIP 1</span>
                                                </div>
                                                <span class="text-[9px] text-slate-400 block">Verified Account</span>
                                            </div>
                                        </div>

                                        <div class="bg-[#0e1320] border border-slate-800/80 rounded-xl p-2.5 space-y-2 text-[10px]">
                                            <div class="flex items-center justify-between border-b border-slate-800/60 pb-1.5">
                                                <span class="text-slate-400">KYC Verification</span>
                                                <span class="text-emerald-400 font-extrabold bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/20">Verified ✓</span>
                                            </div>
                                            <div class="flex items-center justify-between border-b border-slate-800/60 pb-1.5">
                                                <span class="text-slate-400">2FA Security</span>
                                                <span class="text-purple-400 font-extrabold">Enabled 🔒</span>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <span class="text-slate-400">Trading Fee Tier</span>
                                                <span class="text-white font-bold">0.02% Taker</span>
                                            </div>
                                        </div>

                                        <div class="bg-[#121828] border border-slate-800 p-2 rounded-xl flex items-center justify-between text-[10px]">
                                            <span class="text-slate-300 font-bold">Dark Theme Mode</span>
                                            <span class="w-7 h-4 rounded-full bg-purple-600 relative inline-flex items-center justify-end px-0.5">
                                                <span class="w-3 h-3 rounded-full bg-white"></span>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Phone Screen Bottom Navigation Bar (4 Options: Home, Futures, Wallet, Profile) -->
                                    <div class="grid grid-cols-4 gap-1 pt-2 border-t border-slate-800/80 mt-1 bg-[#080c16]/95 rounded-b-[24px] flex-shrink-0">
                                        <button type="button" class="phone-tab-btn active flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-purple-400 font-extrabold bg-purple-950/40 transition-all cursor-pointer hover:bg-slate-800/50" data-phone-tab="home">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                                            <span class="text-[9px] leading-none">Home</span>
                                        </button>
                                        <button type="button" class="phone-tab-btn flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-slate-500 font-bold transition-all cursor-pointer hover:bg-slate-800/50 hover:text-slate-300" data-phone-tab="futures">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                                            <span class="text-[9px] leading-none">Futures</span>
                                        </button>
                                        <button type="button" class="phone-tab-btn flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-slate-500 font-bold transition-all cursor-pointer hover:bg-slate-800/50 hover:text-slate-300" data-phone-tab="wallet">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            <span class="text-[9px] leading-none">Wallet</span>
                                        </button>
                                        <button type="button" class="phone-tab-btn flex flex-col items-center justify-center gap-1 py-1 px-1 rounded-xl text-slate-500 font-bold transition-all cursor-pointer hover:bg-slate-800/50 hover:text-slate-300" data-phone-tab="profile">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            <span class="text-[9px] leading-none">Profile</span>
                                        </button>
                                    </div>

                                </div>

                                <!-- Bottom Phone Bar -->
                                <div class="w-28 h-1 bg-slate-700/80 rounded-full mx-auto mt-2.5 mb-1"></div>

                            </div>

                        </div>
                    </div>

                </div>

            </div>
        </section>

        <!-- FAQ ACCORDIONS & STILL NEED HELP CTA SECTION -->
        <section class="py-16 sm:py-20 flex flex-col justify-center" id="faq">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12 w-full">
                
                <!-- FAQ Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
                    
                    <!-- Left FAQ Header & Still Need Help Box -->
                    <div class="lg:col-span-5 space-y-6">
                        <div>
                            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-purple-500/10 border border-purple-500/30 text-purple-400 text-xs font-bold uppercase tracking-wider mb-3">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>FAQ</span>
                            </div>
                            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-black text-white leading-tight">
                                Questions?<br>We've got answers.
                            </h2>
                            <p class="text-xs sm:text-sm text-slate-400 mt-3 leading-relaxed max-w-sm">
                                Everything you need to know about trading, wallets, and security on Kryvex.
                            </p>
                        </div>

                        <!-- 24/7 Support Box -->
                        <div class="bg-[#0e131f]/90 border border-purple-900/40 rounded-2xl p-6 shadow-xl space-y-3 transition-all hover:border-purple-500/50">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2 text-white font-bold text-sm">
                                    <span class="text-purple-400">💬</span>
                                    <span>24/7 Live Support</span>
                                </div>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold border border-emerald-500/30">Online</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed">
                                Need help with deposits, withdrawals, KYC or order execution? Our dedicated support team is available 24/7.
                            </p>
                            <a href="{{ url('/support') }}" class="inline-flex items-center gap-1.5 text-purple-400 hover:text-purple-300 font-bold text-xs pt-1 transition-colors group">
                                <span>Open Support Ticket</span>
                                <span class="transition-transform group-hover:translate-x-1">→</span>
                            </a>
                        </div>

                        <!-- Learn & Academy Box -->
                        <div class="bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-3 transition-all hover:border-purple-500/50">
                            <div class="flex items-center gap-2 text-white font-bold text-sm">
                                <span class="text-purple-400">📖</span>
                                <span>Guides & Academy</span>
                            </div>
                            <p class="text-xs text-slate-400 leading-relaxed">
                                New to crypto derivatives? Explore step-by-step documentation on leverage, margin calculations, and option payoffs.
                            </p>
                            <a href="{{ url('/learn') }}" class="inline-flex items-center gap-1.5 text-purple-400 hover:text-purple-300 font-bold text-xs pt-1 transition-colors group">
                                <span>Explore Learn Center</span>
                                <span class="transition-transform group-hover:translate-x-1">→</span>
                            </a>
                        </div>
                    </div>

                    <!-- Right 8 Accordion Questions -->
                    <div class="lg:col-span-7 space-y-3">
                        
                        <!-- 01 PLATFORM -->
                        <div class="home-faq-item bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 fade-content-scroll-card" data-delay="100">
                            <button type="button" class="home-faq-question w-full flex items-center justify-between text-left cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-800/90 border border-slate-700/60 text-purple-400 font-extrabold text-xs flex items-center justify-center flex-shrink-0">01</span>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase block">PLATFORM</span>
                                        <span class="faq-title text-white font-bold text-xs sm:text-sm block mt-0.5">What is Kryvex?</span>
                                    </div>
                                </div>
                                <span class="faq-icon text-purple-400 text-lg font-bold ml-2 transition-transform duration-300">+</span>
                            </button>
                            <div class="faq-answer-wrapper">
                                <div class="faq-answer-inner pt-3 pl-11 pr-2">
                                    Kryvex is a crypto futures trading platform where you can go long or short on BTC, ETH, SOL and more. You get a wallet, live Binance prices, perpetual contracts, portfolio tracking, and a full admin-backed exchange experience.
                                </div>
                            </div>
                        </div>

                        <!-- 02 WALLET -->
                        <div class="home-faq-item bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 fade-content-scroll-card" data-delay="150">
                            <button type="button" class="home-faq-question w-full flex items-center justify-between text-left cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-800/90 border border-slate-700/60 text-purple-400 font-extrabold text-xs flex items-center justify-center flex-shrink-0">02</span>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase block">WALLET</span>
                                        <span class="faq-title text-white font-bold text-xs sm:text-sm block mt-0.5">How do deposits work?</span>
                                    </div>
                                </div>
                                <span class="faq-icon text-purple-400 text-lg font-bold ml-2 transition-transform duration-300">+</span>
                            </button>
                            <div class="faq-answer-wrapper">
                                <div class="faq-answer-inner pt-3 pl-11 pr-2">
                                    Deposits are credited to your wallet instantly. Once you deposit, the funds appear in your balance right away and you can start trading immediately — no waiting for manual approval.
                                </div>
                            </div>
                        </div>

                        <!-- 03 WALLET -->
                        <div class="home-faq-item bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 fade-content-scroll-card" data-delay="200">
                            <button type="button" class="home-faq-question w-full flex items-center justify-between text-left cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-800/90 border border-slate-700/60 text-purple-400 font-extrabold text-xs flex items-center justify-center flex-shrink-0">03</span>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase block">WALLET</span>
                                        <span class="faq-title text-white font-bold text-xs sm:text-sm block mt-0.5">How do withdrawals work?</span>
                                    </div>
                                </div>
                                <span class="faq-icon text-purple-400 text-lg font-bold ml-2 transition-transform duration-300">+</span>
                            </button>
                            <div class="faq-answer-wrapper">
                                <div class="faq-answer-inner pt-3 pl-11 pr-2">
                                    When you request a withdrawal, it goes to our admin team for review. Once approved, the amount is deducted from your wallet. This keeps your account secure and prevents unauthorized payouts.
                                </div>
                            </div>
                        </div>

                        <!-- 04 TRADING -->
                        <div class="home-faq-item bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 fade-content-scroll-card" data-delay="250">
                            <button type="button" class="home-faq-question w-full flex items-center justify-between text-left cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-800/90 border border-slate-700/60 text-purple-400 font-extrabold text-xs flex items-center justify-center flex-shrink-0">04</span>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase block">TRADING</span>
                                        <span class="faq-title text-white font-bold text-xs sm:text-sm block mt-0.5">What leverage can I use?</span>
                                    </div>
                                </div>
                                <span class="faq-icon text-purple-400 text-lg font-bold ml-2 transition-transform duration-300">+</span>
                            </button>
                            <div class="faq-answer-wrapper">
                                <div class="faq-answer-inner pt-3 pl-11 pr-2">
                                    Kryvex supports leveraged perpetual futures with up to 100× leverage (configurable by the exchange). You can set take-profit and stop-loss on positions and monitor live P&L from the futures terminal.
                                </div>
                            </div>
                        </div>

                        <!-- 05 TRADING -->
                        <div class="home-faq-item bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 fade-content-scroll-card" data-delay="300">
                            <button type="button" class="home-faq-question w-full flex items-center justify-between text-left cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-800/90 border border-slate-700/60 text-purple-400 font-extrabold text-xs flex items-center justify-center flex-shrink-0">05</span>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase block">TRADING</span>
                                        <span class="faq-title text-white font-bold text-xs sm:text-sm block mt-0.5">What markets are available?</span>
                                    </div>
                                </div>
                                <span class="faq-icon text-purple-400 text-lg font-bold ml-2 transition-transform duration-300">+</span>
                            </button>
                            <div class="faq-answer-wrapper">
                                <div class="faq-answer-inner pt-3 pl-11 pr-2">
                                    Trade crypto perpetuals (BTC, ETH, SOL, BNB and more) on the Futures page. The Markets section also includes simulated stocks and gold for a broader trading experience.
                                </div>
                            </div>
                        </div>

                        <!-- 06 ACCOUNT -->
                        <div class="home-faq-item bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 fade-content-scroll-card" data-delay="350">
                            <button type="button" class="home-faq-question w-full flex items-center justify-between text-left cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-800/90 border border-slate-700/60 text-purple-400 font-extrabold text-xs flex items-center justify-center flex-shrink-0">06</span>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase block">ACCOUNT</span>
                                        <span class="faq-title text-white font-bold text-xs sm:text-sm block mt-0.5">How do I create an account?</span>
                                    </div>
                                </div>
                                <span class="faq-icon text-purple-400 text-lg font-bold ml-2 transition-transform duration-300">+</span>
                            </button>
                            <div class="faq-answer-wrapper">
                                <div class="faq-answer-inner pt-3 pl-11 pr-2">
                                    Sign up with your email and password. We send a one-time OTP to verify your email before your account is activated. After that, log in and fund your wallet to start trading.
                                </div>
                            </div>
                        </div>

                        <!-- 07 SECURITY -->
                        <div class="home-faq-item bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 fade-content-scroll-card" data-delay="400">
                            <button type="button" class="home-faq-question w-full flex items-center justify-between text-left cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-800/90 border border-slate-700/60 text-purple-400 font-extrabold text-xs flex items-center justify-center flex-shrink-0">07</span>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase block">SECURITY</span>
                                        <span class="faq-title text-white font-bold text-xs sm:text-sm block mt-0.5">How is my account protected?</span>
                                    </div>
                                </div>
                                <span class="faq-icon text-purple-400 text-lg font-bold ml-2 transition-transform duration-300">+</span>
                            </button>
                            <div class="faq-answer-wrapper">
                                <div class="faq-answer-inner pt-3 pl-11 pr-2">
                                    Accounts use JWT-secured sessions, OTP email verification on signup, and role-based admin controls. Trading and wallet actions require authentication — your session token protects every API request.
                                </div>
                            </div>
                        </div>

                        <!-- 08 MOBILE -->
                        <div class="home-faq-item bg-[#0e131f]/90 border border-slate-800/80 rounded-2xl p-4 transition-all hover:border-purple-500/40 fade-content-scroll-card" data-delay="450">
                            <button type="button" class="home-faq-question w-full flex items-center justify-between text-left cursor-pointer">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-800/90 border border-slate-700/60 text-purple-400 font-extrabold text-xs flex items-center justify-center flex-shrink-0">08</span>
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400 tracking-wider uppercase block">MOBILE</span>
                                        <span class="faq-title text-white font-bold text-xs sm:text-sm block mt-0.5">Is there a mobile app?</span>
                                    </div>
                                </div>
                                <span class="faq-icon text-purple-400 text-lg font-bold ml-2 transition-transform duration-300">+</span>
                            </button>
                            <div class="faq-answer-wrapper">
                                <div class="faq-answer-inner pt-3 pl-11 pr-2">
                                    The Kryvex mobile app is coming soon. You can trade fully in your browser today — the web platform works on desktop and mobile browsers at /futures.
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Ready to explore Kryvex markets CTA Card -->
                <div class="bg-[#0e131f]/90 border border-purple-900/40 rounded-2xl p-8 sm:p-10 flex flex-col sm:flex-row items-center justify-between gap-6 shadow-2xl transition-all hover:border-purple-500/60 fade-content-scroll-card" data-delay="500">
                    <div>
                        <p class="text-xs font-bold text-purple-400 tracking-wider uppercase mb-1">GET STARTED</p>
                        <h2 class="text-2xl sm:text-3xl font-black text-white mb-1">Ready to explore Kryvex markets?</h2>
                        <p class="text-xs sm:text-sm text-slate-400">Start with market data first, then futures, options, auth, wallet and KYC step-by-step.</p>
                    </div>

                    <a href="{{ url('/markets') }}" class="inline-flex items-center justify-center bg-[#7c3aed] text-white font-bold text-xs px-6 py-3 rounded-xl shadow-lg shadow-purple-950/50 whitespace-nowrap hover:scale-105 transition-all">
                        View Markets
                    </a>
                </div>

            </div>
        </section>

    </div>

    <!-- FLOATING SCROLL TO TOP BUTTON -->
    <button type="button" id="homeScrollToTopBtn" aria-label="Scroll to top" title="Scroll to top">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    </button>
</div>
@endsection
