<footer class="kv-footer border-t border-slate-800/80 bg-[#060911] relative overflow-hidden">
    
    <!-- Ambient Glow Aura -->
    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-3/4 h-24 bg-purple-600/10 blur-3xl pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 lg:py-16 space-y-12">
        
        <!-- Top Row: Brand Info & Live System Status -->
        <div class="flex flex-col md:flex-row items-center md:items-center justify-between gap-6 pb-8 border-b border-slate-800/60 text-center md:text-left">
            <div class="space-y-2 flex flex-col items-center md:items-start">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2.5 group justify-center md:justify-start">
                    <span class="w-9 h-9 rounded-xl bg-purple-600 text-white font-black text-lg flex items-center justify-center shadow-lg shadow-purple-950/60 transition-transform group-hover:scale-105" style="color: #ffffff !important;">
                        K
                    </span>
                    <span class="font-black text-2xl text-white tracking-tight">Kryvex</span>
                </a>
                <p class="text-xs sm:text-sm text-slate-400 max-w-md leading-relaxed mx-auto md:mx-0">
                    Next-generation cryptocurrency perpetual futures and options trading platform with deep liquidity and advanced risk controls.
                </p>
            </div>

            <!-- Live Status & Stats Pill -->
            <div class="flex flex-wrap items-center justify-center md:justify-end gap-3">
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-emerald-950/40 border border-emerald-800/40 text-emerald-400 text-xs font-bold shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Systems Normal</span>
                </div>
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-950/40 border border-purple-800/40 text-purple-300 text-xs font-bold shadow-sm">
                    <span>⚡ 100x Max Leverage</span>
                </div>
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-900 border border-slate-800 text-slate-300 text-xs font-bold shadow-sm">
                    <span>🔒 256-Bit SSL Encrypted</span>
                </div>
            </div>
        </div>

        <!-- Main 5-Column Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-8 text-center sm:text-left">
            
            <!-- Col 1: Products (3 cols) -->
            <div class="sm:col-span-1 lg:col-span-3 space-y-3 flex flex-col items-center sm:items-start">
                <h4 class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest">PRODUCTS</h4>
                <ul class="space-y-2 text-xs font-semibold">
                    <li><a href="{{ url('/futures') }}" class="kv-footer-link {{ request()->is('futures') ? 'active' : '' }}">Perpetual Futures</a></li>
                    <li><a href="{{ url('/options') }}" class="kv-footer-link {{ request()->is('options*') ? 'active' : '' }}">Options Trading</a></li>
                    <li><a href="{{ url('/markets') }}" class="kv-footer-link {{ request()->is('markets*') ? 'active' : '' }}">Crypto Markets</a></li>
                    <li><a href="{{ url('/wallet') }}" class="kv-footer-link {{ request()->is('wallet*') ? 'active' : '' }}">Wallet &amp; Ledger</a></li>
                    <li><a href="{{ url('/kyc') }}" class="kv-footer-link {{ request()->is('kyc*') ? 'active' : '' }}">KYC Verification</a></li>
                </ul>
            </div>

            <!-- Col 2: Top Pairs (2 cols) -->
            <div class="sm:col-span-1 lg:col-span-2 space-y-3 flex flex-col items-center sm:items-start">
                <h4 class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest">TOP PAIRS</h4>
                <ul class="space-y-2 text-xs font-semibold">
                    <li><a href="{{ url('/futures/btcusdt') }}" class="kv-footer-link {{ request()->is('futures/btcusdt*') || request()->is('futures/BTCUSDT*') ? 'active' : '' }}">BTC/USDT Perp</a></li>
                    <li><a href="{{ url('/futures/ethusdt') }}" class="kv-footer-link {{ request()->is('futures/ethusdt*') || request()->is('futures/ETHUSDT*') ? 'active' : '' }}">ETH/USDT Perp</a></li>
                    <li><a href="{{ url('/futures/solusdt') }}" class="kv-footer-link {{ request()->is('futures/solusdt*') || request()->is('futures/SOLUSDT*') ? 'active' : '' }}">SOL/USDT Perp</a></li>
                    <li><a href="{{ url('/futures/xrpusdt') }}" class="kv-footer-link {{ request()->is('futures/xrpusdt*') || request()->is('futures/XRPUSDT*') ? 'active' : '' }}">XRP/USDT Perp</a></li>
                    <li><a href="{{ url('/futures/bnbusdt') }}" class="kv-footer-link {{ request()->is('futures/bnbusdt*') || request()->is('futures/BNBUSDT*') ? 'active' : '' }}">BNB/USDT Perp</a></li>
                </ul>
            </div>

            <!-- Col 3: Learn & Support (3 cols) -->
            <div class="sm:col-span-1 lg:col-span-3 space-y-3 flex flex-col items-center sm:items-start">
                <h4 class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest">LEARN &amp; SUPPORT</h4>
                <ul class="space-y-2 text-xs font-semibold">
                    <li><a href="{{ url('/learn') }}" class="kv-footer-link {{ request()->is('learn*') ? 'active' : '' }}">Trading Dictionary</a></li>
                    <li><a href="{{ url('/learn') }}" class="kv-footer-link {{ request()->is('learn*') ? 'active' : '' }}">Crypto Learn Hub</a></li>
                    <li><a href="{{ url('/#faq') }}" class="kv-footer-link">FAQ &amp; Help Center</a></li>
                    <li><a href="{{ url('/login') }}" class="kv-footer-link {{ request()->is('login*') ? 'active' : '' }}">User Sign In</a></li>
                    <li><a href="{{ url('/register') }}" class="kv-footer-link {{ request()->is('register*') ? 'active' : '' }}">Create Free Account</a></li>
                </ul>
            </div>

            <!-- Col 4: Mobile & App Card (4 cols) -->
            <div class="col-span-1 sm:col-span-2 lg:col-span-4 space-y-3 flex flex-col items-center sm:items-start w-full">
                <h4 class="text-[11px] font-extrabold text-slate-400 uppercase tracking-widest">MOBILE &amp; PLATFORM</h4>
                <div class="bg-[#0e1320]/90 border border-purple-900/40 rounded-2xl p-5 shadow-xl space-y-3 w-full text-center sm:text-left">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-2">
                        <strong class="block text-white text-sm font-black">Mobile App Coming Soon</strong>
                        <span class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 text-[10px] font-extrabold border border-purple-500/30">iOS &amp; Android</span>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Trade seamlessly in your browser today — optimized for desktop, tablet, and mobile devices.
                    </p>
                    <div class="flex items-center justify-center sm:justify-start gap-3 pt-1">
                        <a href="{{ url('/futures') }}" class="inline-flex items-center gap-1.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all shadow-md">
                            <span>Open Futures</span>
                            <span>➔</span>
                        </a>
                        <a href="{{ url('/options') }}" class="inline-flex items-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-all">
                            <span>Options</span>
                        </a>
                    </div>
                </div>
            </div>

        </div>

        <!-- Risk Warning Section -->
        <div class="p-4 rounded-2xl bg-[#0a0e17] border border-slate-800/80 text-xs text-slate-400 leading-relaxed text-center sm:text-left">
            <strong class="text-slate-300 font-bold">Risk Warning:</strong> Cryptocurrency derivatives trading involves significant financial risk. Perpetual futures and options contracts are complex instruments that carry a high risk of losing money rapidly due to leverage. Ensure you fully understand how derivatives work and whether you can afford to take the high risk of losing your capital.
        </div>

    </div>

    <!-- Bottom Copyright Line -->
    <div class="border-t border-slate-800/60 py-5 bg-[#04060a]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3 text-center sm:text-left">
            <span class="text-xs text-slate-400 font-medium">
                © {{ date('Y') }} Kryvex Exchange. All rights reserved.
            </span>
            <div class="flex flex-wrap justify-center items-center gap-4 sm:gap-6 text-xs text-slate-400">
                <a href="{{ url('/privacy') }}" class="kv-footer-bottom-link {{ request()->is('privacy*') ? 'active-emerald' : '' }}">Privacy Policy</a>
                <a href="{{ url('/terms') }}" class="kv-footer-bottom-link {{ request()->is('terms*') ? 'active-purple' : '' }}">Terms of Service</a>
                <a href="{{ url('/terms#sec8') }}" class="kv-footer-bottom-link">Risk Disclosure</a>
            </div>
        </div>
    </div>

</footer>