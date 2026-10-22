@extends('layouts.app')

@section('title', 'Kryvex Academy - Learn Crypto Futures & Options Trading')

@push('styles')
    @vite('resources/css/pages/learn.css')
@endpush

@push('scripts')
    @vite('resources/js/pages/learn.js')
@endpush

@section('content')
<div class="learn-page bg-[#080b11] text-slate-100 min-h-screen relative overflow-hidden">

    <!-- HERO SECTION -->
    <section class="pt-16 pb-20 relative overflow-hidden border-b border-slate-800/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-6">
            
            <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-purple-500/10 border border-purple-500/30 text-purple-300 text-xs font-bold tracking-wider uppercase shadow-[0_0_15px_rgba(168,85,247,0.2)]">
                <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                KRYVEX ACADEMY
            </div>

            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-black text-white tracking-tight leading-[1.15] max-w-3xl mx-auto">
                Master Crypto Futures & <span class="text-purple-400">Options Trading</span>
            </h1>

            <p class="text-slate-400 text-sm sm:text-base leading-relaxed max-w-2xl mx-auto">
                Everything you need to know about leverage, margin management, options contracts, risk controls, and executing trades with confidence.
            </p>

            <!-- Search Bar -->
            <div class="max-w-xl mx-auto pt-2">
                <div class="relative">
                    <input type="text" id="learnSearchInput" placeholder="Search guides, leverage terms, risk controls..." class="w-full bg-[#0e131f] border border-slate-700/80 rounded-2xl py-3.5 pl-12 pr-4 text-xs sm:text-sm text-white placeholder-slate-500 focus:outline-none focus:border-purple-500 shadow-xl transition-all">
                    <svg class="w-5 h-5 text-slate-500 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="flex flex-wrap items-center justify-center gap-2 pt-4">
                <button type="button" class="learn-tab-btn active" data-category="all">All Topics</button>
                <button type="button" class="learn-tab-btn" data-category="futures">Futures </button>
                <button type="button" class="learn-tab-btn" data-category="leverage">Leverage & Margin</button>
                <button type="button" class="learn-tab-btn" data-category="options">Options Basics</button>
                <button type="button" class="learn-tab-btn" data-category="safety">Wallet & Safety</button>
            </div>

        </div>
    </section>

    <!-- MAIN GUIDES GRID -->
    <section class="py-20 border-b border-slate-800/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-bold text-purple-400 tracking-wider uppercase mb-1">ESSENTIAL GUIDES</p>
                    <h2 class="text-2xl sm:text-3xl font-black text-white">Start with core trading fundamentals</h2>
                </div>
                <p class="text-xs sm:text-sm text-slate-400 max-w-md">
                    Comprehensive modules built for beginners and advanced perpetual traders alike.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="guidesContainer">
                
                <!-- Guide Card 1: Futures  -->
                <div class="learn-guide-card p-6 flex flex-col justify-between" data-category="futures">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-md bg-purple-950/80 border border-purple-800/50 text-purple-300 font-bold text-[10px] uppercase">Futures </span>
                            <span class="text-xs text-slate-500 font-semibold">5 min read</span>
                        </div>
                        <h3 class="font-extrabold text-white text-lg leading-snug">How Crypto Perpetual Futures Work</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Learn how Long (buy) and Short (sell) positions allow you to profit whether crypto markets rise or fall, without holding actual coins.
                        </p>
                        <ul class="space-y-2 text-xs text-slate-300 pt-2 font-medium">
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Long vs Short mechanics</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Market vs Limit orders</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Live P&L calculations</span></li>
                        </ul>
                    </div>
                    <div class="pt-6 border-t border-slate-800/60 mt-6 flex items-center justify-between">
                        <a href="{{ url('/futures') }}" class="inline-flex items-center gap-1.5 text-purple-400 hover:text-purple-300 font-bold text-xs">
                            <span>Open Futures Terminal</span>
                            <span>→</span>
                        </a>
                    </div>
                </div>

                <!-- Guide Card 2: Leverage & Margin -->
                <div class="learn-guide-card p-6 flex flex-col justify-between" data-category="leverage">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-md bg-purple-950/80 border border-purple-800/50 text-purple-300 font-bold text-[10px] uppercase">Leverage & Margin</span>
                            <span class="text-xs text-slate-500 font-semibold">6 min read</span>
                        </div>
                        <h3 class="font-extrabold text-white text-lg leading-snug">Understanding 1x to 100x Leverage</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Discover how margin borrowing amplifies buying power, the difference between Cross and Isolated margin, and how to set Take Profit & Stop Loss limits.
                        </p>
                        <ul class="space-y-2 text-xs text-slate-300 pt-2 font-medium">
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">100x max leverage explanation</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Liquidation price threshold</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Automated TP & SL protection</span></li>
                        </ul>
                    </div>
                    <div class="pt-6 border-t border-slate-800/60 mt-6 flex items-center justify-between">
                        <a href="{{ url('/futures') }}" class="inline-flex items-center gap-1.5 text-purple-400 hover:text-purple-300 font-bold text-xs">
                            <span>Practice Leverage</span>
                            <span>→</span>
                        </a>
                    </div>
                </div>

                <!-- Guide Card 3: Options Basics -->
                <div class="learn-guide-card p-6 flex flex-col justify-between" data-category="options">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-md bg-purple-950/80 border border-purple-800/50 text-purple-300 font-bold text-[10px] uppercase">Options Trading</span>
                            <span class="text-xs text-slate-500 font-semibold">7 min read</span>
                        </div>
                        <h3 class="font-extrabold text-white text-lg leading-snug">Calls, Puts & Expiry Options Chain</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Master how Call options (bullish) and Put options (bearish) work, reading strike prices, expiry dates, and option pricing tables.
                        </p>
                        <ul class="space-y-2 text-xs text-slate-300 pt-2 font-medium">
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Call (buy) vs Put (sell) contracts</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Strike price vs Spot price</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Option Greeks (Delta, Theta)</span></li>
                        </ul>
                    </div>
                    <div class="pt-6 border-t border-slate-800/60 mt-6 flex items-center justify-between">
                        <a href="{{ url('/options') }}" class="inline-flex items-center gap-1.5 text-purple-400 hover:text-purple-300 font-bold text-xs">
                            <span>View Options Chain</span>
                            <span>→</span>
                        </a>
                    </div>
                </div>

                <!-- Guide Card 4: Wallet Deposits & Payouts -->
                <div class="learn-guide-card p-6 flex flex-col justify-between" data-category="safety">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-md bg-purple-950/80 border border-purple-800/50 text-purple-300 font-bold text-[10px] uppercase">Wallet & Safety</span>
                            <span class="text-xs text-slate-500 font-semibold">4 min read</span>
                        </div>
                        <h3 class="font-extrabold text-white text-lg leading-snug">Instant Deposits & Ledger Balance</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Understand how demo deposits credit instantly, ledger balances track every trade movement, and admin-verified withdrawal requests work.
                        </p>
                        <ul class="space-y-2 text-xs text-slate-300 pt-2 font-medium">
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Instant demo deposit flow</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Transparent ledger record</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Secure withdrawal approval</span></li>
                        </ul>
                    </div>
                    <div class="pt-6 border-t border-slate-800/60 mt-6 flex items-center justify-between">
                        <a href="{{ url('/wallet') }}" class="inline-flex items-center gap-1.5 text-purple-400 hover:text-purple-300 font-bold text-xs">
                            <span>Open Wallet Ledger</span>
                            <span>→</span>
                        </a>
                    </div>
                </div>

                <!-- Guide Card 5: Account Security & KYC -->
                <div class="learn-guide-card p-6 flex flex-col justify-between" data-category="safety">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-md bg-purple-950/80 border border-purple-800/50 text-purple-300 font-bold text-[10px] uppercase">Wallet & Safety</span>
                            <span class="text-xs text-slate-500 font-semibold">5 min read</span>
                        </div>
                        <h3 class="font-extrabold text-white text-lg leading-snug">Identity Verification & JWT Sessions</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Learn how email OTP verification, JWT session tokens, and KYC document approvals protect your exchange account end-to-end.
                        </p>
                        <ul class="space-y-2 text-xs text-slate-300 pt-2 font-medium">
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">OTP Email Authentication</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Session control & active devices</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">KYC Verification Workflow</span></li>
                        </ul>
                    </div>
                    <div class="pt-6 border-t border-slate-800/60 mt-6 flex items-center justify-between">
                        <a href="{{ url('/settings') }}" class="inline-flex items-center gap-1.5 text-purple-400 hover:text-purple-300 font-bold text-xs">
                            <span>Security Settings</span>
                            <span>→</span>
                        </a>
                    </div>
                </div>

                <!-- Guide Card 6: Market Order Types -->
                <div class="learn-guide-card p-6 flex flex-col justify-between" data-category="futures">
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="px-2.5 py-1 rounded-md bg-purple-950/80 border border-purple-800/50 text-purple-300 font-bold text-[10px] uppercase">Futures </span>
                            <span class="text-xs text-slate-500 font-semibold">5 min read</span>
                        </div>
                        <h3 class="font-extrabold text-white text-lg leading-snug">Order Types: Market, Limit, TP & SL</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            A deep dive into executing instant Market orders versus setting precise Limit entry prices with automated risk control levels.
                        </p>
                        <ul class="space-y-2 text-xs text-slate-300 pt-2 font-medium">
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Instant Market execution</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Limit order entry targets</span></li>
                            <li class="flex items-center gap-2 text-emerald-400">✓ <span class="text-slate-300">Automated Risk Management</span></li>
                        </ul>
                    </div>
                    <div class="pt-6 border-t border-slate-800/60 mt-6 flex items-center justify-between">
                        <a href="{{ url('/futures') }}" class="inline-flex items-center gap-1.5 text-purple-400 hover:text-purple-300 font-bold text-xs">
                            <span>View Order Types</span>
                            <span>→</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- STEP-BY-STEP TRADING WALKTHROUGH -->
    <section class="py-20 border-b border-slate-800/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            
            <div class="text-center space-y-3">
                <p class="text-xs font-bold text-purple-400 tracking-wider uppercase">WALKTHROUGH</p>
                <h2 class="text-3xl sm:text-4xl font-black text-white">How to execute your first trade</h2>
                <p class="text-xs sm:text-sm text-slate-400 max-w-xl mx-auto">
                    Follow these 5 simple steps to open, manage, and close your perpetual contract position on Kryvex.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                
                <!-- Step 1 -->
                <div class="learn-step-card bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-5 space-y-3 flex flex-col justify-between transition-all hover:border-purple-500/40">
                    <div class="space-y-2">
                        <div class="step-number-badge">01</div>
                        <h4 class="font-bold text-white text-sm">Deposit USDT</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">Fund your Kryvex wallet instantly with demo USDT balance.</p>
                    </div>
                    <span class="text-[10px] text-purple-400 font-semibold block pt-2">Step 1 of 5</span>
                </div>

                <!-- Step 2 -->
                <div class="learn-step-card bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-5 space-y-3 flex flex-col justify-between transition-all hover:border-purple-500/40">
                    <div class="space-y-2">
                        <div class="step-number-badge">02</div>
                        <h4 class="font-bold text-white text-sm">Select Contract</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">Choose BTCUSDT, ETHUSDT, or SOLUSDT perpetual contract.</p>
                    </div>
                    <span class="text-[10px] text-purple-400 font-semibold block pt-2">Step 2 of 5</span>
                </div>

                <!-- Step 3 -->
                <div class="learn-step-card bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-5 space-y-3 flex flex-col justify-between transition-all hover:border-purple-500/40">
                    <div class="space-y-2">
                        <div class="step-number-badge">03</div>
                        <h4 class="font-bold text-white text-sm">Set Leverage</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">Select your desired leverage level (e.g. 10x, 25x, 50x).</p>
                    </div>
                    <span class="text-[10px] text-purple-400 font-semibold block pt-2">Step 3 of 5</span>
                </div>

                <!-- Step 4 -->
                <div class="learn-step-card bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-5 space-y-3 flex flex-col justify-between transition-all hover:border-purple-500/40">
                    <div class="space-y-2">
                        <div class="step-number-badge">04</div>
                        <h4 class="font-bold text-white text-sm">Set TP & SL</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">Specify Take Profit and Stop Loss limits to protect margin.</p>
                    </div>
                    <span class="text-[10px] text-purple-400 font-semibold block pt-2">Step 4 of 5</span>
                </div>

                <!-- Step 5 -->
                <div class="learn-step-card bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-5 space-y-3 flex flex-col justify-between transition-all hover:border-purple-500/40">
                    <div class="space-y-2">
                        <div class="step-number-badge">05</div>
                        <h4 class="font-bold text-white text-sm">Execute Trade</h4>
                        <p class="text-xs text-slate-400 leading-relaxed">Click Long or Short to open position and monitor live P&L.</p>
                    </div>
                    <span class="text-[10px] text-purple-400 font-semibold block pt-2">Step 5 of 5</span>
                </div>

            </div>

        </div>
    </section>

    <!-- RISK MANAGEMENT (4 GOLDEN RULES) -->
    <section class="py-20 border-b border-slate-800/40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-12">
            
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <p class="text-xs font-bold text-purple-400 tracking-wider uppercase mb-1">RISK CONTROL</p>
                    <h2 class="text-2xl sm:text-3xl font-black text-white">4 Golden Rules of Trading Security</h2>
                </div>
                <p class="text-xs sm:text-sm text-slate-400 max-w-md">
                    Protecting capital and maintaining disciplined risk controls are key to long-term trading success.
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                
                <!-- Rule 1 -->
                <div class="learn-rule-card bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-6 space-y-3 transition-all hover:border-purple-500/40">
                    <div class="learn-rule-badge w-10 h-10 rounded-xl bg-purple-950/60 border border-purple-800/50 text-purple-400 font-bold text-xs flex items-center justify-center">01</div>
                    <h3 class="font-bold text-white text-base">Always Use Stop Loss</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Never leave a position unhedged. A Stop Loss limits maximum potential loss if market direction turns unexpectedly.
                    </p>
                </div>

                <!-- Rule 2 -->
                <div class="learn-rule-card bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-6 space-y-3 transition-all hover:border-purple-500/40">
                    <div class="learn-rule-badge w-10 h-10 rounded-xl bg-purple-950/60 border border-purple-800/50 text-purple-400 font-bold text-xs flex items-center justify-center">02</div>
                    <h3 class="font-bold text-white text-base">Manage Position Sizing</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Risk no more than 1% to 3% of your total balance on any single trade to absorb short-term market volatility.
                    </p>
                </div>

                <!-- Rule 3 -->
                <div class="learn-rule-card bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-6 space-y-3 transition-all hover:border-purple-500/40">
                    <div class="learn-rule-badge w-10 h-10 rounded-xl bg-purple-950/60 border border-purple-800/50 text-purple-400 font-bold text-xs flex items-center justify-center">03</div>
                    <h3 class="font-bold text-white text-base">Avoid Over-Leveraging</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Higher leverage increases liquidation risk. Start with lower leverage (3x-10x) until mastering market entry timing.
                    </p>
                </div>

                <!-- Rule 4 -->
                <div class="learn-rule-card bg-[#0e131f]/90 border border-slate-800 rounded-2xl p-6 space-y-3 transition-all hover:border-purple-500/40">
                    <div class="learn-rule-badge w-10 h-10 rounded-xl bg-purple-950/60 border border-purple-800/50 text-purple-400 font-bold text-xs flex items-center justify-center">04</div>
                    <h3 class="font-bold text-white text-base">Secure Account Access</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Enable OTP email verification, monitor active sessions, and ensure strong passwords to keep wallet funds safe.
                    </p>
                </div>

            </div>

        </div>
    </section>

    <!-- CRYPTO TRADING GLOSSARY ACCORDION -->
    <section class="py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
                
                <!-- LEFT COLUMN: Info Text, Highlights & Visual Image Card (5 Cols) -->
                <div class="lg:col-span-5 space-y-6 sticky top-24">
                    <div class="space-y-3">
                        <p class="text-xs font-bold text-purple-400 tracking-wider uppercase">GLOSSARY & DICTIONARY</p>
                        <h2 class="text-3xl sm:text-4xl font-black text-white leading-tight">Crypto Trading Terms Dictionary</h2>
                        <p class="text-sm text-slate-400 leading-relaxed">
                            Master essential cryptocurrency perpetual futures, options, and risk management terminology with quick, beginner-friendly definitions.
                        </p>
                    </div>

                    <!-- Visual Image & Feature Highlight Card -->
                    <div class="learn-glossary-card bg-[#0e131f]/90 border border-purple-900/40 rounded-3xl p-6 relative overflow-hidden shadow-2xl space-y-5">
                        <div class="absolute -top-12 -left-12 w-40 h-40 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
                        
                        <div class="relative rounded-2xl overflow-hidden border border-slate-800 shadow-inner group">
                            <img src="/images/trading_chart_bg.jpg" alt="Crypto Trading Guide" class="w-full h-48 object-cover object-center transform group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0e131f] via-[#0e131f]/40 to-transparent"></div>
                            <div class="absolute bottom-3 left-3 right-3 flex items-center justify-between">
                            </div>
                        </div>

                        <div class="space-y-2.5 pt-1">
                            <div class="flex items-center gap-3 text-xs text-slate-300">
                                <span class="w-6 h-6 rounded-full bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold text-xs">✓</span>
                                <span>Perpetual Futures & Funding Rate Rules</span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-slate-300">
                                <span class="w-6 h-6 rounded-full bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold text-xs">✓</span>
                                <span>Option Greeks (Delta, Theta, Implied Volatility)</span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-slate-300">
                                <span class="w-6 h-6 rounded-full bg-purple-500/20 text-purple-400 flex items-center justify-center font-bold text-xs">✓</span>
                                <span>Cross vs Isolated Margin & Risk Controls</span>
                            </div>
                        </div>

                        <div class="learn-tip-box p-3 bg-purple-950/40 border border-purple-800/30 rounded-xl text-xs text-purple-300 flex items-center gap-2">
                            <span>💡</span>
                            <span>Click any term on the right to view its full definition.</span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Accordion Questions & Terms (7 Cols) -->
                <div class="lg:col-span-7 space-y-3" id="glossaryContainer">
                    
                    <!-- Term 1 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Perpetual Futures Contract</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            A crypto futures contract without an expiration date, allowing traders to hold leveraged positions indefinitely as long as maintenance margin requirements are met.
                        </div>
                    </div>

                    <!-- Term 2 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Option Greeks (Delta, Theta, Vega)</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            Key options risk parameters: Delta measures price sensitivity to coin movements, Theta measures daily time decay, and Vega tracks implied volatility changes.
                        </div>
                    </div>

                    <!-- Term 3 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Liquidation Price</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            The specific price threshold where a position is automatically closed by risk systems to prevent losses from exceeding allocated wallet margin.
                        </div>
                    </div>

                    <!-- Term 4 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Implied Volatility (IV)</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            The market's forecast of a likely price movement in the underlying cryptocurrency, directly influencing option premium pricing levels.
                        </div>
                    </div>

                    <!-- Term 5 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Funding Rate</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            Periodic payments exchanged between Long and Short traders to keep perpetual futures prices tightly anchored to spot market index prices.
                        </div>
                    </div>

                    <!-- Term 6 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Strike Price & Expiry Date</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            Strike Price is the predetermined target price level for Call/Put contracts, while Expiry Date is the exact timestamp when option contracts settle.
                        </div>
                    </div>

                    <!-- Term 7 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Cross vs Isolated Margin</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            Cross margin shares available wallet balance across all positions to avoid liquidation. Isolated margin restricts risk strictly to a single position's assigned margin.
                        </div>
                    </div>

                    <!-- Term 8 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Mark Price vs Last Price</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            Mark Price is an index-based fair value price used to trigger liquidations safely. Last Price is the most recent transaction price on the exchange.
                        </div>
                    </div>

                    <!-- Term 9 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Leverage & Maintenance Margin</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            Leverage multiplies trading exposure relative to initial capital. Maintenance Margin is the minimum account equity needed to keep leveraged positions active.
                        </div>
                    </div>

                    <!-- Term 10 -->
                    <div class="glossary-item">
                        <button type="button" class="glossary-header">
                            <span class="glossary-term text-white font-bold text-sm sm:text-base">Market Order vs Limit Order</span>
                            <span class="glossary-icon text-purple-400 font-bold text-lg">+</span>
                        </button>
                        <div class="glossary-content">
                            Market Orders execute immediately at best current prices. Limit Orders execute only when market reaches your specified price or better.
                        </div>
                    </div>

                </div>

            </div>

        </div>
    </section>

    <!-- READY TO TRADE CTA BOX -->
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="learn-cta-card bg-[#0e131f]/90 border border-purple-900/40 rounded-3xl p-8 sm:p-12 text-center space-y-6 shadow-2xl relative overflow-hidden">
                <h2 class="text-3xl sm:text-4xl font-black text-white">Ready to put your knowledge into practice?</h2>
                <p class="text-xs sm:text-sm text-slate-400 max-w-xl mx-auto">
                    Start trading live crypto perpetuals with up to 100x leverage and full risk management controls.
                </p>
                <div class="flex flex-wrap items-center justify-center gap-4 pt-2">
                    <a href="{{ url('/futures') }}" class="learn-btn-primary inline-flex items-center justify-center bg-[#7c3aed] hover:bg-[#6d28d9] text-white font-bold text-xs sm:text-sm px-7 py-3.5 rounded-xl transition-all shadow-lg shadow-purple-950/50 hover:scale-105">
                        Start Trading Futures
                    </a>
                    <a href="{{ url('/markets') }}" class="learn-btn-secondary inline-flex items-center justify-center bg-[#080b11] hover:bg-slate-800 border border-slate-800 text-white font-bold text-xs sm:text-sm px-7 py-3.5 rounded-xl transition-all hover:scale-105">
                        View Live Markets
                    </a>
                </div>
            </div>
        </div>
    </section>

</div>
@endsection
