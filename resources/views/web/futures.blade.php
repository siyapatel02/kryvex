@extends('layouts.app')

@section('title', $symbol . ' Futures - Kryvex')

@push('styles')
    @vite('resources/css/pages/futures.css')
@endpush

@push('scripts')
    <script>
        window.KRYVEX_FUTURES_SYMBOL = @json($symbol);
    </script>
    @vite('resources/js/pages/futures.js')
@endpush

@section('content')
    @php
        $displaySymbol = strtoupper($symbol);
        $baseCoin = strtoupper(str_replace(['USDT', 'USD', 'INR', 'BUSD'], '', $symbol));
        $coinLogos = [
            'BTC' => 'https://assets.coingecko.com/coins/images/1/large/bitcoin.png',
            'ETH' => 'https://assets.coingecko.com/coins/images/279/large/ethereum.png',
            'USDT' => 'https://assets.coingecko.com/coins/images/325/large/Tether.png',
            'BNB' => 'https://assets.coingecko.com/coins/images/825/large/bnb-icon2_2x.png',
            'SOL' => 'https://assets.coingecko.com/coins/images/4128/large/solana.png',
            'XRP' => 'https://assets.coingecko.com/coins/images/44/large/xrp-symbol-white-128.png',
            'USDC' => 'https://assets.coingecko.com/coins/images/6319/large/USD_Coin_icon.png',
            'ADA' => 'https://assets.coingecko.com/coins/images/975/large/cardano.png',
            'DOGE' => 'https://assets.coingecko.com/coins/images/5/large/dogecoin.png',
            'TRX' => 'https://assets.coingecko.com/coins/images/1094/large/tron-logo.png',
            'AVAX' => 'https://assets.coingecko.com/coins/images/12559/large/Avalanche_Circle_RedWhite_Trans.png',
            'DOT' => 'https://assets.coingecko.com/coins/images/12171/large/polkadot.png',
            'MATIC' => 'https://assets.coingecko.com/coins/images/4713/large/polygon.png',
            'POL' => 'https://assets.coingecko.com/coins/images/4713/large/polygon.png',
            'LINK' => 'https://assets.coingecko.com/coins/images/877/large/chainlink-new-logo.png',
            'LTC' => 'https://assets.coingecko.com/coins/images/2/large/litecoin.png',
            'NEAR' => 'https://assets.coingecko.com/coins/images/10365/large/near.png',
            'SUI' => 'https://assets.coingecko.com/coins/images/26375/large/sui-ocean-square.png',
            'APT' => 'https://assets.coingecko.com/coins/images/26455/large/aptos_round.png',
            'AR' => 'https://assets.coingecko.com/coins/images/4343/large/arweave.png',

            'ARB' => 'https://assets.coingecko.com/coins/images/16547/large/arbitrum-shield.png',
            'ATOM' => 'https://assets.coingecko.com/coins/images/1481/large/cosmos_hub.png',
            'ENA' => 'https://assets.coingecko.com/coins/images/36530/large/ethena.png',
            'BCH' => 'https://assets.coingecko.com/coins/images/780/large/bitcoin-cash-circle.png',
            'OP' => 'https://assets.coingecko.com/coins/images/25244/large/Optimism.png',
            'FIL' => 'https://assets.coingecko.com/coins/images/12817/large/filecoin.png',
            'WIF' => 'https://assets.coingecko.com/coins/images/33566/large/dogwifhat.jpg',
            'SHIB' => 'https://assets.coingecko.com/coins/images/11939/large/shiba.png',
            'PEPE' => 'https://assets.coingecko.com/coins/images/29850/large/pepe-token.png',
            '1000PEPE' => 'https://assets.coingecko.com/coins/images/29850/large/pepe-token.png',
            'TON' => 'https://assets.coingecko.com/coins/images/17980/large/ton_symbol.png',
            'XLM' => 'https://assets.coingecko.com/coins/images/100/large/Stellar_symbol_black_RGB.png',
            'UNI' => 'https://assets.coingecko.com/coins/images/12504/large/uniswap-uni.png',
            'ETC' => 'https://assets.coingecko.com/coins/images/453/large/ethereum-classic-logo.png',
            'XAU' => '/images/coins/gold.svg',
            'PAXG' => '/images/coins/gold.svg',
            'GOLD' => '/images/coins/gold.svg',
            'XAUT' => '/images/coins/gold.svg',
        ];

        $symbolLotSpecs = [
            'BTCUSDT' => ['min' => '0.0007', 'step' => '0.0001', 'base' => 'BTC'],
            'ETHUSDT' => ['min' => '0.0089', 'step' => '0.0001', 'base' => 'ETH'],
            'SOLUSDT' => ['min' => '0.05', 'step' => '0.01', 'base' => 'SOL'],
            'XAUUSDT' => ['min' => '0.002', 'step' => '0.001', 'base' => 'XAU'],
            'GOLDUSDT' => ['min' => '0.002', 'step' => '0.001', 'base' => 'GOLD'],
            'BNBUSDT' => ['min' => '0.01', 'step' => '0.01', 'base' => 'BNB'],
            'XRPUSDT' => ['min' => '3.5', 'step' => '0.1', 'base' => 'XRP'],
            'ADAUSDT' => ['min' => '23', 'step' => '1', 'base' => 'ADA'],
            'AVAXUSDT' => ['min' => '1', 'step' => '0.1', 'base' => 'AVAX'],
            'LINKUSDT' => ['min' => '0.41', 'step' => '0.01', 'base' => 'LINK'],
            'DOTUSDT' => ['min' => '4.9', 'step' => '0.1', 'base' => 'DOT'],
            'POLUSDT' => ['min' => '50', 'step' => '1', 'base' => 'POL'],
            'MATICUSDT' => ['min' => '50', 'step' => '1', 'base' => 'MATIC'],
            'LTCUSDT' => ['min' => '0.092', 'step' => '0.001', 'base' => 'LTC'],
            'ATOMUSDT' => ['min' => '1', 'step' => '0.1', 'base' => 'ATOM'],
            'ATEMUSDT' => ['min' => '1', 'step' => '0.1', 'base' => 'ATOM'],
            'UNIUSDT' => ['min' => '1', 'step' => '0.1', 'base' => 'UNI'],
            'APTUSDT' => ['min' => '7.5', 'step' => '0.1', 'base' => 'APT'],
            'ARBUSDT' => ['min' => '22.7', 'step' => '0.1', 'base' => 'ARB'],
            'OPUSDT' => ['min' => '44.6', 'step' => '0.1', 'base' => 'OP'],
            'SUIUSDT' => ['min' => '6.4', 'step' => '0.1', 'base' => 'SUI'],
            'NEARUSDT' => ['min' => '2', 'step' => '0.1', 'base' => 'NEAR'],
            'DOGEUSDT' => ['min' => '1', 'step' => '1', 'base' => 'DOGE'],
            'GODDOGTUSDT' => ['min' => '1', 'step' => '1', 'base' => 'DOGE'],
            'WIFUSDT' => ['min' => '26.1', 'step' => '0.1', 'base' => 'WIF'],
        ];

        $currentSpec = $symbolLotSpecs[$displaySymbol] ?? [
            'min' => '0.001',
            'step' => '0.001',
            'base' => $baseCoin,
        ];
        $initialMinLot = $currentSpec['min'];
        $initialStep = $currentSpec['step'];
        $initialBaseAsset = $currentSpec['base'] ?? $baseCoin;
        $isGoldSym = in_array($baseCoin, ['XAU', 'GOLD', 'PAXG', 'XAUT']) || str_contains($displaySymbol, 'XAU');
        $initialLogoUrl = $isGoldSym ? '/images/coins/gold.svg' : ($coinLogos[$baseCoin] ?? ($coinLogos[$initialBaseAsset] ?? ('https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/' . strtolower($baseCoin) . '.png')));
    @endphp


    <div class="futures-page">
        <!-- TOP RUNNING CRYPTO TICKER BAR (INFINITE MARQUEE ANIMATION) -->
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

        <section class="futures-shell">
            <div class="futures-market-strip kv-card">
                <div class="futures-symbol-area">
                    <div class="futures-symbol-display">
                        <span class="symbol-coin" id="futuresSymbolIcon">
                            <img id="futuresSymbolIconImg"
                                src="{{ $initialLogoUrl }}"
                                alt="{{ $symbol }}"
                                data-sym="{{ $symbol }}"
                                style="width:100%; height:100%; border-radius:50%; object-fit:cover; display:block;"
                                onerror="this.onerror=null;this.src='{{ $isGoldSym ? '/images/coins/gold.svg' : 'https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/' . strtolower($baseCoin) . '.png' }}';">
                        </span>
                        <button type="button" class="futures-symbol-title-btn" id="contractDropdownToggle" title="Click to change contract">
                            <strong id="futuresSymbolTitle">{{ $displaySymbol }}</strong>
                            <span class="symbol-caret">▾</span>
                        </button>
                    </div>

                    <div class="contract-dropdown-backdrop" id="contractDropdownBackdrop"></div>
                    <div class="contract-dropdown" id="contractDropdown" data-lenis-prevent="true">
                        <div class="contract-dropdown-tabs">
                            <button type="button" class="is-active">USD-M</button>
                        </div>

                        <div class="contract-search">
                            <input type="text" id="contractSearchInput" placeholder="Search contract">
                        </div>

                        <div class="contract-filter-row" id="contractFilterRow">
                            <button type="button" class="is-active" data-category="all">All</button>
                            <button type="button" data-category="wishlist" id="wishlistFilterTab">Wishlist (0)</button>
                            <button type="button" data-category="meme">Meme</button>
                            <button type="button" data-category="layer1">Layer 1</button>
                            <button type="button" data-category="ai">AI</button>
                        </div>

                        <div class="contract-list-head">
                            <span></span>
                            <span>Contract</span>
                            <span>Leverage</span>
                            <span>Last Price</span>
                            <span>24H Change</span>
                        </div>

                        <div id="contractDropdownList" class="contract-dropdown-list" data-lenis-prevent="true">
                            <div class="futures-loading">Loading contracts...</div>
                        </div>
                    </div>
                </div>

                <div class="futures-strip-metrics">
                    <div class="metric-primary">
                        <span>Mark Price</span>
                        <strong id="futuresMarkPrice">--</strong>
                    </div>
                    <div class="metric-primary">
                        <span>Funding / Countdown</span>
                        <strong><span id="futuresFundingRate">--</span> / <span id="futuresNextFunding">--</span></strong>
                    </div>
                    <div class="metric-primary">
                        <span>24H Change</span>
                        <strong id="futuresChange">--</strong>
                    </div>
                    <div class="metric-secondary">
                        <span>24H High</span>
                        <strong id="futuresHigh">--</strong>
                    </div>
                    <div class="metric-secondary">
                        <span>24H Low</span>
                        <strong id="futuresLow">--</strong>
                    </div>
                    <div class="metric-primary">
                        <span>24H Volume</span>
                        <strong id="futuresVolume">--</strong>
                    </div>
                </div>

                <div class="futures-strip-account-group" id="futuresStripAccountGroup">
                    <script>
                        (function() {
                            try {
                                var m = localStorage.getItem('kryvex_trading_mode');
                                var isLive = (m === 'live');
                                var el = document.getElementById('futuresStripAccountGroup');
                                if (el && !isLive) el.classList.add('is-demo-mode');
                                var idEl = document.getElementById('futuresStripAccId');
                                var curEl = document.getElementById('futuresStripAccCur');
                                var modeEl = document.getElementById('futuresStripAccMode');
                                var labelEl = document.getElementById('futuresTradingBalLabel');
                                var balEl = document.getElementById('orderWalletAvailable');
                                var cur = localStorage.getItem('kryvex_active_currency');
                                if (curEl) curEl.textContent = isLive && (cur === 'INR') ? 'INR' : 'USDT';
                                if (modeEl) {
                                    modeEl.textContent = isLive ? 'LIVE' : 'DEMO';
                                    modeEl.className = 'futures-acc-badge-mode is-' + (isLive ? 'live' : 'demo');
                                }
                                if (labelEl) {
                                    labelEl.textContent = isLive ? 'Trading' : 'Balance';
                                }
                                var accs = JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]');
                                if (isLive) {
                                    var uid = localStorage.getItem('kryvex_active_account_uid');
                                    var la = accs.find(function(a) { return a.account_uid === uid && !a.is_demo && a.account_type !== 'demo'; }) || accs.find(function(a) { return !a.is_demo && a.account_type !== 'demo'; });
                                    if (idEl) idEl.textContent = 'L' + (la && la.account_number ? la.account_number : '1000001');
                                    if (balEl && la) {
                                        var b = Number(la.real_available_balance || la.balance || 0);
                                        balEl.textContent = '$' + b.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                    var mainEl = document.getElementById('orderEstimatedEquity');
                                    if (mainEl && la) {
                                        var mb = Number(la.real_main_balance !== undefined ? la.real_main_balance : (la.is_default ? (la.real_main_balance || la.balance || 0) : 0));
                                        mainEl.textContent = '$' + mb.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                } else {
                                    var da = accs.find(function(a) { return a.is_demo || a.account_type === 'demo'; });
                                    if (idEl) idEl.textContent = 'D' + (da && da.account_number ? da.account_number : '1000000');
                                    if (balEl && da) {
                                        var db = Number(da.demo_available_balance || da.balance || 60000.0);
                                        balEl.textContent = '$' + db.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                                    }
                                    var mainEl = document.getElementById('orderEstimatedEquity');
                                    if (mainEl) {
                                        mainEl.textContent = '$0.00';
                                    }
                                }
                            } catch(e) {}
                        })();
                    </script>

                    <!-- 1. Balance Chip (First as requested) -->
                    <div class="wallet-balance-chip trading" id="futuresTradingBalChip">
                        <span id="futuresTradingBalLabel">Trading</span>
                        <strong id="orderWalletAvailable" data-sensitive-balance>Login required</strong>
                    </div>
                    <div class="wallet-balance-chip main" id="futuresMainBalChip">
                        <span>Main</span>
                        <strong id="orderEstimatedEquity" data-sensitive-balance>--</strong>
                    </div>

                    <!-- 2. Blue Quick Deposit Button (Then Deposit) -->
                    <button type="button" class="futures-strip-deposit-btn" id="futuresStripDepositBtn" title="Deposit / Add Funds">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 7V5a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h8"></path>
                            <path d="M2 8.5h15a2 2 0 0 1 2 2V12"></path>
                            <line x1="18.5" y1="15.5" x2="18.5" y2="21.5"></line>
                            <line x1="15.5" y1="18.5" x2="21.5" y2="18.5"></line>
                        </svg>
                    </button>

                    <!-- 3. Account ID & Badges Tag (Then ID & Badges) -->
                    <div class="futures-strip-acc-tag" id="futuresStripAccTag">
                        <div class="futures-strip-acc-id" id="futuresStripAccId">D1000000</div>
                        <div class="futures-strip-acc-badges">
                            <span class="futures-acc-badge-cur" id="futuresStripAccCur">USDT</span>
                            <span class="futures-acc-badge-mode is-demo" id="futuresStripAccMode">DEMO</span>
                        </div>
                    </div>
                    <div class="wallet-quick-actions" id="futuresTransferActionWrap">
                        <button type="button" class="wallet-action-btn transfer" id="futuresTransferBtn" data-wallet-modal="transfer" data-account-action>
                            Transfer
                        </button>
                        <div class="futures-account-currency-row">
                            <div class="mobile-page-currency-toggle">
                                <div class="kv-currency-toggle-wrap">
                                    <button type="button" class="kv-currency-btn" data-currency-toggle="INR">₹ INR</button>
                                    <button type="button" class="kv-currency-btn is-active" data-currency-toggle="USDT">$ USD</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wallet-hidden-metrics" hidden>
                    <strong id="orderWalletLocked">--</strong>
                    <strong id="orderLivePnl">--</strong>
                </div>
            </div>

            <div class="wallet-hidden-metrics" hidden>
                <strong id="orderWalletLocked">--</strong>
                <strong id="orderLivePnl">--</strong>
            </div>

            <div class="futures-mobile-nav-tabs" id="futuresMobileNavTabs">
                <button type="button" class="mobile-nav-tab is-active" data-mobile-tab="trade">Trade</button>
                <button type="button" class="mobile-nav-tab" data-mobile-tab="positions">Positions <span id="mobilePosBadge">(0)</span></button>
                <button type="button" class="mobile-nav-tab" data-mobile-tab="orders">Orders <span id="mobileOrdersBadge">(0)</span></button>
                <button type="button" class="mobile-nav-tab" data-mobile-tab="history">History</button>
            </div>

            <div class="futures-terminal-grid futures-terminal-grid--single-order" id="futuresTerminalGrid">
                <main class="futures-main">

                    <div class="futures-chart-card kv-card">
                        <div class="futures-chart-footer-stats" id="futuresMobileStatsCard">
                            <div><span>Total Balance</span><strong id="chartTotalBalance">--</strong></div>
                            <div><span id="chartLivePnlTitle">Unrealized PnL (USD)</span><strong id="orderLivePnlFooter">--</strong></div>
                            <div><span>Overall PnL (All Trades)</span><strong id="orderTotalOverallPnlFooter">--</strong></div>
                            <div><span>Margin Balance</span><strong id="chartMarginBalance">--</strong></div>
                            <div><span>Margin Used</span><strong id="chartMarginUsed">--</strong></div>
                            <div><span>Margin Ratio</span><strong id="chartMarginRatio">--</strong></div>
                        </div>

                        <div id="tradingViewChart" class="futures-chart-box">
                            <div class="futures-loading">Loading TradingView chart...</div>
                        </div>
                    </div>
                </main>

                <div class="futures-bottom kv-card">
                    <div class="futures-mobile-stats-strip">
                        <div class="mobile-stat-item"><span>Equity</span><strong id="mobileEquity">--</strong></div>
                        <div class="mobile-stat-item"><span>UPNL</span><strong id="mobileUpnl">--</strong></div>
                        <div class="mobile-stat-item"><span>Margin Bal</span><strong id="mobileMarginBal">--</strong></div>
                        <div class="mobile-stat-item"><span>Avail</span><strong id="mobileAvail">--</strong></div>
                    </div>

                    <div class="futures-tabs-wrapper">
                        <div class="futures-tabs">
                            <button type="button" class="is-active" data-tab-target="positionsPanel">Pos <span id="posCount">(0)</span></button>
                            <button type="button" data-tab-target="openOrdersPanel">  Open Orders <span id="ordersCount">(0)</span></button>
                            <button type="button" data-tab-target="orderHistoryPanel">History</button>
                            <button type="button" data-tab-target="tradeHistoryPanel">Trades</button>
                        </div>
                        <button type="button" class="mobile-close-all-btn" id="mobileCloseAllBtn" data-account-action style="display: none;">Close All</button>
                    </div>

                    <div class="futures-tab-panel is-active" id="positionsPanel">
                        <div class="futures-table-wrap">
                            <table class="futures-record-table futures-positions-table">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Side</th>
                                        <th>Entry</th>
                                        <th>Mark</th>
                                        <th>Qty</th>
                                        <th>Margin</th>
                                        <th>Unrealized PnL</th>
                                        <th>TP</th>
                                        <th>SL</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="positionsTableBody">
                                    <tr>
                                        <td colspan="10" class="futures-empty">Login to view positions.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="futures-positions-cards-list" id="positionsCardsList"></div>
                    </div>

                    <div class="futures-tab-panel" id="openOrdersPanel">
                        <div class="futures-table-wrap">
                            <table class="futures-record-table">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Side</th>
                                        <th>Type</th>
                                        <th>Price</th>
                                        <th>Margin</th>
                                        <th>Leverage</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="openOrdersTableBody">
                                    <tr>
                                        <td colspan="8" class="futures-empty">Login to view open orders.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="futures-positions-cards-list" id="openOrdersCardsList"></div>
                    </div>

                    <div class="futures-tab-panel" id="orderHistoryPanel">
                        <div class="futures-table-wrap">
                            <table class="futures-record-table">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Side</th>
                                        <th>Type</th>
                                        <th>Price</th>
                                        <th>Margin</th>
                                        <th>Fee</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody id="orderHistoryTableBody">
                                    <tr>
                                        <td colspan="8" class="futures-empty">Login to view order history.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="futures-positions-cards-list" id="orderHistoryCardsList"></div>
                        <div class="wallet-pagination-wrap" id="futuresOrdersPagination" hidden></div>
                    </div>

                    <div class="futures-tab-panel" id="tradeHistoryPanel">
                        <div class="futures-table-wrap">
                            <table class="futures-record-table">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Side</th>
                                        <th>Price</th>
                                        <th>Qty</th>
                                        <th>Value</th>
                                        <th>Fee</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody id="tradeHistoryTableBody">
                                    <tr>
                                        <td colspan="8" class="futures-empty">Login to view trade history.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="futures-positions-cards-list" id="tradeHistoryCardsList"></div>
                        <div class="wallet-pagination-wrap" id="futuresTradesPagination" hidden></div>
                    </div>

                    <div class="futures-tab-panel" id="txnsPanel">
                        <div class="futures-empty">No transactions found.</div>
                    </div>
                </div>

                <aside class="futures-orderbook-panel kv-card" id="futuresOrderbookPanel">
                    <div class="orderbook-header">
                        <div class="orderbook-title-wrap">
                            <span>Order Book</span>
                        </div>
                        <div class="orderbook-controls">
                            <div class="orderbook-mode-toggles">
                                <button type="button" class="ob-mode-btn is-active" data-ob-mode="both" title="Both Asks and Bids">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                        <rect y="1" width="14" height="2.5" rx="1" fill="#ef4444"/>
                                        <rect y="5.5" width="14" height="2.5" rx="1" fill="#10b981"/>
                                        <rect y="10" width="14" height="2.5" rx="1" fill="#10b981"/>
                                    </svg>
                                </button>
                                <button type="button" class="ob-mode-btn" data-ob-mode="asks" title="Asks Only">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                        <rect y="1" width="14" height="2.5" rx="1" fill="#ef4444"/>
                                        <rect y="5.5" width="14" height="2.5" rx="1" fill="#ef4444"/>
                                        <rect y="10" width="14" height="2.5" rx="1" fill="#ef4444"/>
                                    </svg>
                                </button>
                                <button type="button" class="ob-mode-btn" data-ob-mode="bids" title="Bids Only">
                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                        <rect y="1" width="14" height="2.5" rx="1" fill="#10b981"/>
                                        <rect y="5.5" width="14" height="2.5" rx="1" fill="#10b981"/>
                                        <rect y="10" width="14" height="2.5" rx="1" fill="#10b981"/>
                                    </svg>
                                </button>
                            </div>
                            <select id="obPrecisionSelect" class="ob-precision-select">
                                <option value="0.1" selected>0.1</option>
                                <option value="0.5">0.5</option>
                                <option value="1">1</option>
                                <option value="5">5</option>
                            </select>
                        </div>
                    </div>

                    <div class="orderbook-table-header">
                        <span class="ob-col-price">Price (USD)</span>
                        <span class="ob-col-size">Size (<span class="base-asset-label">BTC</span>)</span>
                        <span class="ob-col-total">Total (<span class="base-asset-label">BTC</span>)</span>
                    </div>

                    <div class="orderbook-body" id="orderbookBody">
                        <div class="orderbook-asks-wrap" id="orderbookAsks">
                            <div class="orderbook-loading">Loading order book...</div>
                        </div>

                        <div class="orderbook-ticker-bar" id="orderbookTickerBar">
                            <span class="ob-mid-price" id="obMidPrice">--</span>
                            <span class="ob-mid-arrow" id="obMidArrow"></span>
                            <span class="ob-mark-price" id="obMarkPrice">--</span>
                        </div>

                        <div class="orderbook-bids-wrap" id="orderbookBids">
                            <div class="orderbook-loading">Loading order book...</div>
                        </div>
                    </div>

                    <div class="recent-trades-section">
                        <div class="recent-trades-head">
                            <span>Recent Trades</span>
                        </div>
                        <div class="recent-trades-table-header">
                            <span class="rt-col-price">Price (USD)</span>
                            <span class="rt-col-size">Size (<span class="base-asset-label">BTC</span>)</span>
                            <span class="rt-col-time">Time</span>
                        </div>
                        <div class="recent-trades-body" id="recentTradesBody">
                            <div class="recent-trades-loading">Loading trades...</div>
                        </div>
                    </div>
                </aside>

                <aside class="futures-order-panel kv-card" id="futuresOrderPanel">
                    <div class="mobile-drawer-handle-bar-wrap" id="mobileDrawerHandleBar" title="Close Ticket" aria-label="Close">
                        <div class="mobile-drawer-handle-bar"></div>
                    </div>
                    <div class="futures-panel-head futures-panel-head--trade-card">
                        <div class="order-ticket-title-group" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                            <strong style="color:#fff;font-size:15px;">Order Ticket</strong>
                            <span class="order-ticket-symbol-badge" style="font-size:11px;color:var(--kv-yellow);background:rgba(250,204,21,0.12);padding:2px 6px;border-radius:4px;">{{ $displaySymbol }}</span>
                        </div>
                        <button type="button" class="mobile-order-panel-close" id="btnCloseMobileOrderPanel" title="Close Ticket" aria-label="Close">&times;</button>
                    </div>

                    <form id="futuresOrderPreviewForm">
                        <input type="hidden" name="symbol" id="orderSymbol" value="{{ $symbol }}">
                        <input type="hidden" name="last_price" id="orderLastPrice">
                        <input type="hidden" name="side" id="orderSide" value="buy">

                        <div class="sr-only order-hidden-inputs">
                            <label>
                                <span>Order Type</span>
                                <select name="order_type" id="orderType">
                                    <option value="market" selected>Market</option>
                                    <option value="limit">Limit</option>
                                    <option value="stop_limit">Stop Limit</option>
                                    <option value="stop_market">Stop Market</option>
                                </select>
                            </label>
                        </div>

                        <div class="order-type-tabs">
                            <button type="button" class="is-active" data-order-type="market">Market</button>
                            <button type="button" data-order-type="limit">Limit</button>
                            <button type="button" data-order-type="stop_limit">Stop-Limit</button>
                        </div>

                        <p id="marginModeInfoText" class="margin-mode-info-text" style="font-size: 0.72rem; color: #94a3b8; margin: 10px 0 6px; line-height: 1.35;">
                            Isolated: only this trade's margin can be liquidated.
                        </p>

                        <div class="order-settings-row order-settings-row--margin" style="display: flex; gap: 10px; align-items: center; margin-bottom: 14px;">
                            <div class="margin-mode-toggle-field" style="flex: 1.3;">
                                <input type="hidden" name="margin_mode" id="orderMarginMode" value="isolated">
                                <button type="button" id="btnToggleMarginMode" class="margin-mode-btn" style="width: 100%; height: 40px; background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 8px; color: #94a3b8; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s ease; outline: none;">
                                    <span id="marginModeBtnText" style="color: #94a3b8;">Isolated Margin</span>
                                </button>
                            </div>
                            <div class="leverage-dropdown-field" style="flex: 0.7;">
                                <input type="hidden" name="leverage" id="orderLeverage" value="100">
                                <button type="button" id="btnOpenLeverageModal" class="leverage-modal-trigger-btn" title="Set Leverage">
                                    <span id="leverageBtnBadge">100x</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="6 9 12 15 18 9"></polyline>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="order-side-tabs">
                            <button type="button" class="is-active" data-side="buy">Buy / Long</button>
                            <button type="button" data-side="sell">Sell / Short</button>
                        </div>

                        <div id="futuresOrderTopNotice" class="futures-order-top-notice hidden">
                            <strong id="futuresOrderTopNoticeTitle">Notice</strong>
                            <span id="futuresOrderTopNoticeText"></span>
                        </div>

                        <label class="order-price-field" id="orderPriceField">
                            <span>Price</span>
                            <div class="order-input-wrap">
                                <input type="number" step="any" name="price" id="orderPrice" placeholder="Limit price">
                                <span class="order-input-suffix">USDT</span>
                            </div>
                        </label>

                        <label class="order-price-field" id="orderStopPriceField">
                            <span>Stop Price</span>
                            <div class="order-input-wrap">
                                <input type="number" step="any" name="stop_price" id="orderStopPrice" placeholder="Stop trigger price">
                                <span class="order-input-suffix">USDT</span>
                            </div>
                        </label>

                        <label class="order-qty-field" id="orderQtyField">
                            <span>Quantity</span>
                            <div class="order-input-wrap">
                                <input type="number" step="{{ $initialStep }}" name="quantity" id="orderQuantity" placeholder="{{ $initialMinLot }}" min="{{ $initialMinLot }}">
                                <span class="order-input-suffix base-asset-label">{{ $initialBaseAsset }}</span>
                            </div>
                        </label>

                        <div class="order-percent-row order-share-row">
                            <button type="button" data-share="0.25">25%</button>
                            <button type="button" data-share="0.50">50%</button>
                            <button type="button" data-share="0.75">75%</button>
                            <button type="button" data-share="1">100%</button>
                        </div>

                        <div class="tp-sl-row" id="tpSlFieldsRow">
                            <div>
                                <label>Take Profit</label>
                                <input type="number" name="take_profit" placeholder="Optional" step="0.0001">
                            </div>

                            <div>
                                <label>Stop Loss</label>
                                <input type="number" name="stop_loss" placeholder="Optional" step="0.0001">
                            </div>
                        </div>

                        <div class="order-estimate-grid">
                            <div class="estimate-item">
                                <span id="orderPanelAvailableLabel">Available</span>
                                <strong id="orderPanelAvailable">--</strong>
                            </div>
                            <div class="estimate-item">
                                <span>Est. Margin</span>
                                <strong id="orderEstimatedMargin">--</strong>
                            </div>
                            <div class="estimate-item">
                                <span>Mark Price</span>
                                <strong id="orderPanelMarkPrice">--</strong>
                            </div>
                        </div>

                        <div class="order-action-stack">
                            <button type="button" id="placeDemoOrderButton" class="order-place-submit" data-account-action>
                                Buy / Long
                            </button>
                        </div>

                        <div class="order-margin-ratio-card-modern">
                            <div class="margin-ratio-head">
                                <span class="margin-ratio-title">Margin Ratio</span>
                                <div class="margin-ratio-symbol">
                                    <span class="symbol-coin-mini" id="marginRatioCoinIcon">
                                        <img src="{{ $initialLogoUrl }}" alt="{{ $symbol }}" onerror="this.onerror=null;this.src='https://assets.coincap.io/assets/icons/{{ strtolower($baseCoin) }}@2x.png';" style="width:100%; height:100%; border-radius:50%; object-fit:cover; display:block;">
                                    </span>

                                    <span id="orderMarginRatioSymbol">{{ $displaySymbol }}</span>
                                </div>
                            </div>

                            <div class="margin-ratio-main-val">
                                <strong id="orderMarginRatio">0.00%</strong>
                                <span class="info-icon" title="Margin ratio = Maintenance Margin / Margin Balance">ⓘ</span>
                            </div>

                            <div class="margin-ratio-bar">
                                <span id="orderMarginRatioBar" style="width: 0%;"></span>
                            </div>

                            <div class="margin-ratio-sub-metrics">
                                <div>
                                    <span>Maintenance Margin</span>
                                    <strong id="orderMaintenanceMargin">0.0000 USDT</strong>
                                </div>
                                <div class="text-right">
                                    <span>Margin Balance</span>
                                    <strong id="orderMarginBalance">0.0000 USDT</strong>
                                </div>
                            </div>
                        </div>

                        <div id="orderPreviewResult" class="order-preview-result" hidden></div>
                    </form>
                </aside>
            </div>
        </section>
    </div>

    <!-- Mobile Drawer Backdrop Overlay -->
    <div class="mobile-drawer-backdrop" id="mobileDrawerBackdrop" hidden></div>

    <!-- Mobile Sticky Bottom Action Bar -->
    <div class="futures-mobile-bottom-bar" id="futuresMobileBottomBar">
        <button type="button" class="mobile-trade-action-btn btn-sell" id="mobileBtnSell">
            <span>Sell</span>
            <strong id="mobileSellPrice">--</strong>
        </button>
        <button type="button" class="mobile-trade-action-btn btn-buy" id="mobileBtnBuy">
            <span>Buy</span>
            <strong id="mobileBuyPrice">--</strong>
        </button>
    </div>

    <!-- ========================================================================== -->
    <!-- DEMO DEPOSIT OPTIONS MODAL (FUTURES PAGE DEMO MODE - MATCHING IMAGE 2) -->
    <!-- ========================================================================== -->
    <div class="kv-fund-modal-backdrop" id="futuresDemoDepositModal" hidden style="display: none;">
        <div class="kv-fund-modal-card" role="dialog" aria-modal="true" aria-labelledby="futuresDemoDepositTitle" style="max-width: 440px;">
            <!-- Header -->
            <div class="kv-fund-modal-header">
                <h3 class="kv-fund-modal-title" id="futuresDemoDepositTitle">Deposit</h3>
                <button type="button" class="kv-fund-modal-close" id="closeFuturesDemoDepositModal" aria-label="Close modal">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Options matching Image 2 -->
            <div class="kv-fund-section" style="margin-top: 4px; gap: 10px;">
                <!-- Option 1: Set demo balance -->
                <div class="kv-fund-option-card" id="btnOptSetDemoBalance" role="button" tabindex="0">
                    <div class="kv-fund-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="2" x2="12" y2="22"></line>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            <path d="M19 10l.6 1.8 1.8.6-1.8.6-.6 1.8-.6-1.8-1.8-.6 1.8-.6z" fill="currentColor" stroke="none"></path>
                        </svg>
                    </div>
                    <div class="kv-fund-info">
                        <strong class="kv-fund-item-title">Set demo balance</strong>
                        <span class="kv-fund-item-sub">Top up or adjust your demo balance</span>
                    </div>
                    <svg class="kv-fund-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>

                <!-- Option 2: Deposit real funds -->
                <div class="kv-fund-option-card" id="btnOptDepositRealFunds" role="button" tabindex="0">
                    <div class="kv-fund-icon-box">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </div>
                    <div class="kv-fund-info">
                        <strong class="kv-fund-item-title">Deposit real funds</strong>
                        <span class="kv-fund-item-sub">Ready for live trading?</span>
                    </div>
                    <svg class="kv-fund-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================== -->
    <!-- SET DEMO BALANCE MODAL -->
    <!-- ========================================================================== -->
    <div class="kv-fund-modal-backdrop" id="futuresSetDemoBalanceModal" hidden style="display: none;">
        <div class="kv-fund-modal-card" role="dialog" aria-modal="true" aria-labelledby="futuresSetDemoTitle" style="max-width: 440px;">
            <!-- Header -->
            <div class="kv-fund-modal-header">
                <div style="display:flex; align-items:center; gap:8px;">
                    <button type="button" class="kv-fund-modal-close" id="backFuturesSetDemoModal" aria-label="Back" title="Back" style="margin-right:2px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <h3 class="kv-fund-modal-title" id="futuresSetDemoTitle">Set demo balance</h3>
                </div>
                <button type="button" class="kv-fund-modal-close" id="closeFuturesSetDemoBalanceModal" aria-label="Close modal">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <p style="font-size: 13px; color: #94a3b8; margin: 0 0 4px 0;">Top up or adjust your demo account with virtual practice funds.</p>

            <!-- Preset Quick Chips -->
            <div class="kv-demo-preset-grid">
                <button type="button" class="demo-preset-chip" data-amt="10000">$10,000</button>
                <button type="button" class="demo-preset-chip" data-amt="50000">$50,000</button>
                <button type="button" class="demo-preset-chip" data-amt="60000">$60,000</button>
                <button type="button" class="demo-preset-chip" data-amt="100000">$100,000</button>
            </div>

            <!-- Custom Input -->
            <div style="display:flex; flex-direction:column; gap:6px;">
                <label for="futuresDemoBalanceInput" style="font-size: 12px; font-weight: 600; color: #94a3b8;">Custom Amount (USD)</label>
                <div class="kv-demo-input-box">
                    <span class="kv-demo-input-prefix">$</span>
                    <input type="number" id="futuresDemoBalanceInput" min="1" max="10000000" step="any" placeholder="e.g. 10000" class="kv-demo-input">
                </div>
            </div>

            <!-- Submit Button -->
            <button type="button" id="btnConfirmSetDemoBalance" class="kv-demo-submit-btn">
                Add to Balance
            </button>
        </div>
    </div>

    <div class="futures-modal-backdrop" id="futuresWalletModal" aria-hidden="true">
        <div class="futures-modal-card">
            <button type="button" class="futures-modal-close" data-close-wallet-modal>×</button>

            <!-- Standard Deposit / Withdraw Header (Hidden in Transfer mode) -->
            <div class="std-modal-header" id="stdModalHeader">
                <div class="futures-modal-icon" id="walletModalIcon">$</div>
                <h3 id="walletModalTitle">Add Funds</h3>
                <p id="walletModalSubtitle">Add demo funds to your wallet ledger.</p>
            </div>

            <form id="futuresWalletActionForm">
                <input type="hidden" id="walletActionType" value="deposit">

                <!-- Split Currency Tab Toggle Pill -->
                <div class="wallet-currency-tabs" id="futuresModalCurrencyTabs" style="display:none !important;">
                    <button type="button" class="wallet-cur-tab is-active" data-tab="usdt" id="futuresModalTabUsdt">$ USD</button>
                    <button type="button" class="wallet-cur-tab" data-tab="inr" id="futuresModalTabInr">₹ INR</button>
                </div>

                <!-- Standard USDT Deposit Network rows (Hidden, managed via custom Deposit UI) -->
                <label id="walletNetworkRow" style="display:none !important;" hidden>
                    Network
                    <select id="walletNetworkSelect">
                        <option value="">Loading networks...</option>
                    </select>
                </label>
                <div class="wallet-modal-address-box" id="walletDepositAddressBox" style="display:none !important;" hidden>
                    <div><span>Deposit Address</span><button type="button" id="walletCopyDepositAddress">Copy</button></div>
                    <strong id="walletDepositAddressText">Select network to view address.</strong>
                    <small id="walletDepositAddressRule">Only send funds on selected network.</small>
                </div>

                <!-- Standard USDT Withdraw Address row (Hidden for JS state syncing) -->
                <div id="walletWithdrawAddressRow" style="display:none !important;" hidden>
                    <input type="hidden" id="walletWithdrawAddressInput">
                </div>
                <small class="wallet-modal-rule" id="walletWithdrawAddressRule" style="display:none !important;" hidden></small>

                <!-- INR Withdraw Fields Card -->
                <div id="futuresInrWithdrawFields" style="display:none;margin-bottom:12px;">
                    <div id="futuresSavedBankBanner"></div>
                </div>

                <!-- Hidden Controls preserved for JS state management -->
                <label id="walletTransferAccountRow" hidden style="display:none !important;">
                    Trading Account
                    <select id="walletTransferAccountSelect">
                        <!-- Populated via JS -->
                    </select>
                </label>

                <label id="walletTransferDirectionRow" hidden style="display:none !important;">
                    Transfer Direction
                    <select id="walletTransferDirection">
                        <option value="main_to_trading">Main balance to Trading balance</option>
                        <option value="trading_to_main">Trading balance to Main balance</option>
                    </select>
                </label>

                <!-- ========================================== -->
                <!-- DEDICATED DEPOSIT VIEW (NEW MODERN GREEN BUTTON DEPOSIT MODAL) -->
                <!-- ========================================== -->
                <div class="deposit-ui-container space-y-4" id="futuresDepositUiContainer" hidden>
                    <!-- Deposit Header with Green Icon Circle & Close Button Info -->
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center text-emerald-400 font-black text-lg flex-shrink-0" id="dpHeadIconCircle">
                                ₮
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-black text-white leading-tight" id="dpHeadTitle">Deposit USD (USDT)</h3>
                                <p class="text-xs text-slate-400 mt-0.5" id="dpHeadSub">Deposit USDT to your account using the details below.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Account Section (Main Account Only) -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Account</label>
                        <div class="p-3 bg-slate-900/90 border border-slate-800 rounded-2xl flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-purple-600/15 border border-purple-500/30 flex items-center justify-center text-purple-400 flex-shrink-0">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <strong class="text-xs font-bold text-white block">Main Account</strong>
                                <span class="text-[11px] text-slate-400 block">Available Balance: <strong id="depositMainWalletBalance" class="text-white font-mono font-bold">$ 0.00</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- ==================================================== -->
                    <!-- USD CRYPTO DEPOSIT SECTION (Shown when active currency is USD) -->
                    <!-- ==================================================== -->
                    <div id="dpUsdSection" class="space-y-3">
                        <!-- Select Network Section -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Select Network</label>
                            <div class="deposit-network-grid grid grid-cols-3 gap-2" id="depositNetworkGrid">
                                <!-- Populated via JS -->
                            </div>
                        </div>

                        <!-- Info Banner -->
                        <div class="p-2.5 bg-blue-500/10 border border-blue-500/20 rounded-xl flex items-start gap-2.5 text-xs text-blue-300">
                            <svg class="w-4 h-4 text-blue-400 flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span class="leading-tight">Make sure to select the same network on the platform you are withdrawing from. Deposits with the wrong network may be lost.</span>
                        </div>

                        <!-- Deposit Address Box + Direct QR Code View -->
                        <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-3 shadow-inner">
                            <!-- Top Row: USDT Icon + Title -->
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400 font-extrabold text-xs flex-shrink-0">
                                        ₮
                                    </div>
                                    <span class="text-xs sm:text-sm font-extrabold text-white" id="depositAddressTitleLabel">USDT Deposit Address</span>
                                </div>
                            </div>

                            <!-- Main Input + Direct QR Code Row -->
                            <div class="flex items-center gap-2.5">
                                <!-- Left: Address Input Container with Inline Copy Button -->
                                <div class="flex-1 min-w-0 bg-slate-950/90 border border-slate-800/90 rounded-xl p-2.5 flex items-center justify-between gap-2 shadow-sm focus-within:border-emerald-500/50 transition-all">
                                    <strong id="dpDepositAddressText" class="font-mono text-xs sm:text-sm text-emerald-300 font-extrabold break-all select-all truncate">Loading address...</strong>
                                    <button type="button" class="p-1.5 rounded-lg bg-slate-800/60 hover:bg-slate-700/80 text-slate-300 hover:text-emerald-400 transition-all cursor-pointer flex-shrink-0" id="dpCopyAddressInlineBtn" title="Copy Address">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                    </button>
                                </div>

                                <!-- Vertical Divider -->
                                <div class="w-px h-10 bg-slate-800 flex-shrink-0"></div>

                                <!-- Right: Direct QR Code Container -->
                                <div class="w-12 h-12 sm:w-14 sm:h-14 p-1 bg-white rounded-xl shadow-md flex items-center justify-center flex-shrink-0 border border-slate-700/50" title="Deposit QR Code">
                                    <img id="dpQrImage" src="" alt="Deposit QR Code" class="w-full h-full object-contain block rounded-lg">
                                </div>
                            </div>
                        </div>

                        <!-- USD Deposit Amount Input -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Deposit Amount (USD)</label>
                            <div class="relative">
                                <input type="number" id="dpUsdAmountInput" placeholder="Enter amount to deposit (e.g. 100)" step="any" min="1" class="w-full h-11 bg-slate-900/90 border border-slate-800 rounded-xl text-white pl-3 pr-14 text-sm font-mono font-extrabold outline-none focus:border-emerald-500 transition-all">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">USDT</span>
                            </div>
                        </div>

                        <!-- GREEN DEPOSIT SUBMIT BUTTON -->
                        <button type="button" id="dpUsdSubmitBtn" class="w-full h-11 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-black text-sm rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-emerald-950/40 active:scale-[0.99] mt-2">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            <span>Deposit Funds</span>
                        </button>

                        <!-- Footer Shield Box -->
                        <div class="p-2.5 bg-slate-900/60 border border-slate-800/60 rounded-xl flex items-center gap-2 text-xs text-slate-400">
                            <svg class="w-4 h-4 text-emerald-400 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
                            <span id="dpFooterShieldText" class="text-[11px]">Send only USDT to this address via TRC20 network.</span>
                        </div>
                    </div>

                    <!-- ==================================================== -->
                    <!-- INR BANK DEPOSIT SECTION (Shown when active currency is INR) -->
                    <!-- ==================================================== -->
                    <div id="dpInrSection" class="space-y-3" style="display:none;">
                        <!-- Current Rate Indicator Bar integrated neatly inside INR Section -->
                        <div id="futuresRateBar" class="p-2.5 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-between text-xs" style="display:none;">
                            <span class="text-slate-400 font-bold">Current Rate:</span>
                            <strong id="futuresRateVal" class="text-emerald-400 font-mono font-bold">1 USD = ₹-- (Admin Buy Rate)</strong>
                        </div>

                        <!-- Bank Deposit Account Details Box (Centered) -->
                        <div class="p-3.5 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-2.5 text-xs" id="futuresInrBankBox">
                            <div class="flex items-center justify-between border-b border-slate-800/80 pb-2">
                                <span class="text-xs font-bold text-white flex items-center gap-1.5">
                                    <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>
                                    Bank Deposit Account Details
                                </span>
                                <span class="px-2 py-0.5 bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 rounded-full text-[10px] font-bold">Verified Bank</span>
                            </div>
                            <div id="futuresInrBankInfo" class="text-slate-200 leading-relaxed font-mono">Loading bank details...</div>
                        </div>

                        <!-- INR Deposit Amount Field -->
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Deposit Amount (₹ INR)</label>
                            <div class="relative">
                                <input type="number" id="dpInrAmountInput" placeholder="Enter amount in ₹ (e.g. 5000)" step="any" min="1" class="w-full h-11 bg-slate-900/90 border border-slate-800 rounded-xl text-white pl-3 pr-10 text-sm font-mono font-extrabold outline-none focus:border-emerald-500 transition-all">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-xs">₹</span>
                            </div>
                        </div>

                        <!-- GREEN SUBMIT INR DEPOSIT BUTTON -->
                        <button type="button" id="dpInrSubmitBtn" class="w-full h-11 bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-black text-sm rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-emerald-950/40 active:scale-[0.99] mt-2">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            <span>Submit INR Deposit Request</span>
                        </button>
                    </div>

                    <!-- Important Notes Block -->
                    <div class="p-3 bg-slate-900/60 border border-slate-800/60 rounded-2xl space-y-1.5 text-xs">
                        <div class="flex items-center gap-1.5 text-emerald-400 font-bold">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                            <span>Important Notes</span>
                        </div>
                        <ul class="text-[11px] text-slate-400 space-y-1 leading-relaxed pl-1" id="dpNotesList">
                            <li id="dpNoteMinDeposit">• Minimum deposit: $100 (or equivalent)</li>
                            <li>• Send only USDT to this TRC20 address.</li>
                            <li>• Ensure the network matches (TRC20).</li>
                            <li>• Deposits will be credited after network confirmation.</li>
                        </ul>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- DEDICATED TRANSFER VIEW (NEW MODERN DUAL-WALLET TRANSFER) -->
                <!-- ========================================== -->
                <div class="transfer-ui-container space-y-4" id="futuresTransferUiContainer" hidden>
                    <!-- Modal Header Subtitle / Info -->
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800/80">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-600/15 border border-purple-500/30 flex items-center justify-center text-purple-400 font-black text-lg flex-shrink-0">
                                ⇄
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-black text-white leading-tight">Transfer Balance</h3>
                                <p class="text-xs text-slate-400 mt-0.5">Move funds between your wallets instantly.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Currency Selector Tabs (Clean INR / USD) -->
                    <div class="grid grid-cols-2 gap-2 p-1 bg-slate-900/90 border border-slate-800 rounded-2xl" id="transferCurrencyToggleWrap">
                        <button type="button" class="transfer-cur-btn active py-2.5 px-3 rounded-xl border text-xs font-black transition-all flex items-center justify-center cursor-pointer bg-purple-600/20 border-purple-500/30 text-purple-300 shadow-sm" data-currency="INR" id="transferCurInrBtn">
                            INR
                        </button>
                        <button type="button" class="transfer-cur-btn py-2.5 px-3 rounded-xl border text-xs font-bold transition-all flex items-center justify-center cursor-pointer bg-transparent border-transparent text-slate-400 hover:text-white" data-currency="USDT" id="transferCurUsdtBtn">
                            USD
                        </button>
                    </div>

                    <!-- Trading Account Selector Dropdown -->
                    <div class="space-y-1.5" id="transferTradingAccountWrap">
                        <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block">Trading Account</label>
                        <div class="relative">
                            <select id="transferTradingAccountSelect" class="w-full h-10 bg-slate-900/90 border border-slate-800 rounded-xl text-white px-3 pr-8 text-xs font-bold outline-none cursor-pointer appearance-none focus:border-purple-500 transition-all">
                            </select>
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs">▼</span>
                        </div>
                    </div>

                    <!-- Visual Wallet Flow Cards (From -> Swap Button -> To) -->
                    <div class="grid grid-cols-11 gap-2 items-center py-1">
                        <!-- From Card (5 cols) -->
                        <div class="col-span-5 p-2.5 bg-slate-900/90 border border-slate-800 rounded-2xl flex items-center gap-2.5 min-w-0">
                            <div id="transferFromIconBox" class="w-8 h-8 rounded-xl bg-purple-600/15 border border-purple-500/30 flex items-center justify-center text-purple-400 flex-shrink-0">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
                            </div>
                            <div class="min-w-0 overflow-hidden">
                                <span class="text-[10px] font-bold text-slate-400 block truncate" id="transferFromTitle">Main Wallet</span>
                                <strong class="text-xs font-mono font-extrabold text-white block truncate" id="transferFromBalance">₹0.00</strong>
                            </div>
                        </div>

                        <!-- Swap Direction Button (1 col) -->
                        <div class="col-span-1 flex justify-center items-center">
                            <button type="button" id="transferSwapDirectionBtn" class="w-8 h-8 rounded-xl bg-purple-600/20 hover:bg-purple-600/40 border border-purple-500/30 text-purple-300 hover:text-white flex items-center justify-center text-sm font-extrabold transition-all cursor-pointer shadow-sm active:scale-95" title="Swap Transfer Direction">
                                ⇄
                            </button>
                        </div>

                        <!-- To Card (5 cols) -->
                        <div class="col-span-5 p-2.5 bg-slate-900/90 border border-slate-800 rounded-2xl flex items-center gap-2.5 min-w-0">
                            <div id="transferToIconBox" class="w-8 h-8 rounded-xl bg-cyan-600/15 border border-cyan-500/30 flex items-center justify-center text-cyan-400 flex-shrink-0">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                            </div>
                            <div class="min-w-0 overflow-hidden">
                                <span class="text-[10px] font-bold text-slate-400 block truncate" id="transferToTitle">Trading Wallet</span>
                                <strong class="text-xs font-mono font-extrabold text-white block truncate" id="transferToBalance">$0.00</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Amount Input & Quick Percent Selector -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-400 uppercase tracking-wider block" id="transferAmountLabel">Amount (INR)</label>
                            <span class="text-[11px] text-slate-400 font-medium">Max Transfer: <strong id="transferMaxAvailableVal" class="text-purple-300 font-mono font-bold">₹0.00</strong></span>
                        </div>
                        <div class="relative">
                            <input type="number" id="transferAmountInput" min="0.00000001" step="any" placeholder="0.00" class="w-full h-11 bg-slate-900/90 border border-slate-800 rounded-xl text-white pl-3 pr-16 text-sm font-mono font-extrabold outline-none focus:border-purple-500 transition-all">
                            <button type="button" id="transferMaxButton" class="absolute right-2 top-1/2 -translate-y-1/2 bg-purple-600/20 hover:bg-purple-600/40 text-purple-300 font-extrabold text-xs px-3 py-1 rounded-lg border border-purple-500/30 transition-all cursor-pointer">Max</button>
                        </div>

                        <!-- Quick Percentage Buttons -->
                        <div class="grid grid-cols-4 gap-2 pt-1">
                            <button type="button" class="transfer-pct-btn py-1.5 rounded-lg bg-slate-900/80 hover:bg-slate-800 border border-slate-800 hover:border-purple-500/40 text-xs font-bold text-slate-300 hover:text-purple-300 transition-all text-center cursor-pointer" data-pct="25">25%</button>
                            <button type="button" class="transfer-pct-btn py-1.5 rounded-lg bg-slate-900/80 hover:bg-slate-800 border border-slate-800 hover:border-purple-500/40 text-xs font-bold text-slate-300 hover:text-purple-300 transition-all text-center cursor-pointer" data-pct="50">50%</button>
                            <button type="button" class="transfer-pct-btn py-1.5 rounded-lg bg-slate-900/80 hover:bg-slate-800 border border-slate-800 hover:border-purple-500/40 text-xs font-bold text-slate-300 hover:text-purple-300 transition-all text-center cursor-pointer" data-pct="75">75%</button>
                            <button type="button" class="transfer-pct-btn py-1.5 rounded-lg bg-slate-900/80 hover:bg-slate-800 border border-slate-800 hover:border-purple-500/40 text-xs font-bold text-slate-300 hover:text-purple-300 transition-all text-center cursor-pointer" data-pct="100">100%</button>
                        </div>
                    </div>

                    <!-- Admin Exchange Rate Display Bar -->
                    <div id="transferInrRateBar" class="p-2.5 bg-amber-500/10 border border-amber-500/20 rounded-xl flex items-center justify-between text-xs" style="display: none;">
                        <span class="text-slate-400 font-bold">Admin Exchange Rate:</span>
                        <strong id="transferInrRateVal" class="text-amber-400 font-mono font-bold">1 USD = ₹--</strong>
                    </div>

                    <!-- Conversion Deduction & Credit Card -->
                    <div id="transferConversionBox" class="p-3 bg-slate-900/90 border border-slate-800 rounded-2xl space-y-2 text-xs" style="display: none;">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-medium" id="transferConvFromLabel">INR Main Wallet Deduction:</span>
                            <strong class="text-white font-mono font-bold" id="transferConvFromVal">₹0.00</strong>
                        </div>
                        <div class="flex items-center justify-between pt-2 border-t border-dashed border-white/10">
                            <span class="text-slate-400 font-medium" id="transferConvToLabel">Trading Account Credit (USD):</span>
                            <strong class="text-emerald-400 font-mono font-extrabold" id="transferConvToVal">$0.00 USD</strong>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" id="tfSubmitBtn" class="w-full h-11 bg-purple-600/20 hover:bg-purple-600/40 text-purple-300 hover:text-white border border-purple-500/30 font-black text-sm rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer mt-2">
                        <span>Transfer Balance</span>
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- DEDICATED WITHDRAW VIEW (MATCHES SAMPLE IMAGE) -->
                <!-- ========================================== -->
                <div class="withdraw-ui-container" id="futuresWithdrawUiContainer" hidden>
                    <!-- Withdraw Header -->
                    <div class="withdraw-head-block">
                        <div class="withdraw-head-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="12" y1="19" x2="12" y2="5"></line>
                                <polyline points="5 12 12 5 19 12"></polyline>
                            </svg>
                        </div>
                        <div class="withdraw-head-text">
                            <h3 class="withdraw-head-title">Withdraw</h3>
                            <p class="withdraw-head-sub">Withdraw funds from your Main Wallet</p>
                        </div>
                    </div>

                    <!-- Available Balance Card -->
                    <div class="withdraw-balance-card">
                        <div class="withdraw-balance-info">
                            <span class="withdraw-balance-label">Available Balance</span>
                            <div class="withdraw-balance-val" id="wdAvailableBalance">$0.00</div>
                            <span class="withdraw-balance-sub" id="wdBalSublabel">Main Wallet · USD</span>
                        </div>
                        <button type="button" class="withdraw-transfer-btn" data-wallet-modal="transfer">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="17 1 21 5 17 9"></polyline>
                                <path d="M3 11V9a4 4 0 0 1 4-4h14"></path>
                                <polyline points="7 23 3 19 7 15"></polyline>
                                <path d="M21 13v2a4 4 0 0 1-4 4H3"></path>
                            </svg>
                            <span>Transfer</span>
                        </button>
                    </div>

                    <!-- Rate Indicator Bar (Shown Below Available Balance Card in INR mode) -->
                    <div id="wdRateBar" style="display:none;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:0.84rem;color:rgba(255,255,255,0.9);">
                        <span>Current Rate: </span><strong id="wdRateVal" style="color:#22c55e;">1 USD = ₹95.00 (Admin Buy Rate)</strong>
                    </div>

                    <!-- Currency Section -->
                    <div class="withdraw-field-group" id="wdCurrencyFieldGroup">
                        <label class="withdraw-field-label">Currency</label>
                        <div class="withdraw-select-box" style="cursor:default;">
                            <div class="withdraw-select-content">
                                <span class="withdraw-coin-icon usdt-icon" id="wdCurrencyIcon">₮</span>
                                <span class="withdraw-select-text" id="wdCurrencyLabel">USDT (Tether USD)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Network Section -->
                    <div class="withdraw-field-group" id="wdNetworkFieldGroup">
                        <label class="withdraw-field-label">Network</label>
                        <div class="withdraw-select-box" id="wdNetworkSelectBox">
                            <div class="withdraw-select-content">
                                <span class="withdraw-coin-icon tron-icon" id="wdNetworkIcon">T</span>
                                <span class="withdraw-select-text" id="wdSelectedNetworkText">TRC20 (Tron)</span>
                            </div>
                            <svg class="withdraw-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            <select id="wdNetworkSelectCustom" class="withdraw-hidden-select">
                                <option value="TRC20">TRC20 (Tron)</option>
                                <option value="ERC20">ERC20 (Ethereum)</option>
                                <option value="BEP20">BEP20 (BNB Smart Chain)</option>
                            </select>
                        </div>
                        <div class="withdraw-field-note">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                            <span>Ensure the network is the same as the withdrawal address, or assets may be lost.</span>
                        </div>
                    </div>

                    <!-- INR Bank Details Group (Shown in INR mode) -->
                    <div class="withdraw-field-group" id="wdInrBankGroup" style="display:none;">
                        <label class="withdraw-field-label">Withdrawal Bank Account</label>
                        <div id="wdSavedBankBanner"></div>
                    </div>

                    <!-- Amount Section -->
                    <div class="withdraw-field-group">
                        <div class="withdraw-field-header">
                            <label class="withdraw-field-label">Amount</label>
                        </div>
                        <div class="withdraw-input-box">
                            <input type="number" step="any" min="0" id="wdAmountInput" placeholder="0.00">
                            <div class="withdraw-input-suffix-group">
                                <span class="withdraw-input-unit" id="wdUnitSuffix">USDT</span>
                                <button type="button" class="withdraw-max-btn" id="wdMaxBtn">Max</button>
                            </div>
                        </div>
                        <div class="withdraw-conversion-text" id="wdConversionText">≈ $0.00</div>
                    </div>

                    <!-- Withdrawal Address Section -->
                    <div class="withdraw-field-group" id="wdAddressFieldGroup">
                        <div class="withdraw-field-header">
                            <label class="withdraw-field-label">Withdrawal Address</label>
                        </div>
                        <div class="withdraw-input-box">
                            <input type="text" id="wdAddressInput" placeholder="Paste or enter USDT address">
                            <button type="button" class="withdraw-qr-icon-btn" title="QR Code">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="3" width="7" height="7"/>
                                    <rect x="14" y="3" width="7" height="7"/>
                                    <rect x="14" y="14" width="7" height="7"/>
                                    <rect x="3" y="14" width="7" height="7"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Breakdown / Receipt Card (MATCHES IMAGE 2) -->
                    <div class="withdraw-breakdown-card">
                        <div class="withdraw-breakdown-row">
                            <span>Requested Amount</span>
                            <span id="wdBreakdownAmount">$0.00</span>
                        </div>
                        <div class="withdraw-breakdown-row">
                            <span>Withdrawal Charge</span>
                            <span id="wdBreakdownFee">$0.00</span>
                        </div>
                        <div class="withdraw-breakdown-row">
                            <span>Platform Fee</span>
                            <span id="wdBreakdownPlatform">$0.00</span>
                        </div>
                        <div class="withdraw-breakdown-divider"></div>
                        <div class="withdraw-breakdown-main-row">
                            <span style="color:#f43f5e;font-weight:700;">You Receive</span>
                            <strong class="withdraw-receive-val" id="wdReceiveVal" style="color:#f43f5e;font-size:1.15rem;font-weight:800;">$0.00</strong>
                        </div>
                    </div>

                    <!-- Warning Alert Banner -->
                    <div class="withdraw-warning-banner">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <span id="wdWarningText">Make sure the address and network are correct. Transactions cannot be canceled.</span>
                    </div>

                    <!-- Modal Action Buttons -->
                    <div class="withdraw-actions-row">
                        <button type="button" class="withdraw-btn-cancel" data-close-wallet-modal>Cancel</button>
                        <button type="button" class="withdraw-btn-submit" id="wdSubmitBtn">Continue</button>
                    </div>
                </div>

                <!-- Standard Amount field (Hidden in Transfer mode) -->
                <label id="stdAmountRow">
                    <span id="futuresAmountLabel">Amount</span>
                    <div class="wallet-input-with-button">
                        <input type="number" step="any" min="0.00000001" id="walletActionAmount" placeholder="e.g. 500">
                        <button type="button" class="wallet-input-max-btn" id="walletActionMaxButton">Max</button>
                    </div>
                </label>

                <!-- Live Conversion Box for Deposit/Withdraw -->
                <div id="futuresConversionBox" style="display:none;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:10px;margin-bottom:12px;font-size:0.83rem;">
                    <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                        <span style="color:var(--kv-muted);" id="futuresConvFromLabel">From:</span>
                        <strong id="futuresConvFromVal" style="color:#fff;">--</strong>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--kv-muted);" id="futuresConvToLabel">To:</span>
                        <strong id="futuresConvToVal" style="color:#22c55e;">--</strong>
                    </div>
                </div>

                <div class="wallet-modal-help" id="walletModalHelp">Available balance will update instantly.</div>
                <div class="wallet-withdraw-preview mini-withdraw-preview" id="futuresWalletWithdrawPreview" hidden>
                    <div><span>Requested Amount</span><strong id="futuresWithdrawPreviewAmount">--</strong></div>
                    <div><span>Withdrawal Charge</span><strong id="futuresWithdrawPreviewFee">--</strong></div>
                    <div><span>Platform Fee</span><strong id="futuresWithdrawPreviewPlatform">--</strong></div>
                    <div class="receive-row"><span>You Receive</span><strong id="futuresWithdrawPreviewNet">--</strong></div>
                    <div id="futuresWithdrawPreviewUsdtRow" style="display:flex; justify-content:space-between; margin-top:6px; padding-top:6px; border-top:1px dashed rgba(255,255,255,0.1); font-size:0.8rem;">
                        <span style="color:var(--kv-muted);">USD Equivalent Deducted</span>
                        <strong id="futuresWithdrawPreviewUsdt" style="color:#f59e0b;">--</strong>
                    </div>
                </div>
                <div class="wallet-modal-actions" id="stdModalActions">
                    <button type="button" class="modal-cancel-btn" data-close-wallet-modal>Cancel</button>
                    <button type="submit" class="modal-submit-btn" id="walletModalSubmit" data-account-action>
                        Add Funds
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="futures-modal-backdrop" id="futuresConfirmModal" aria-hidden="true">
        <div class="futures-modal-card confirm-card">
            <div class="futures-modal-icon warning">!</div>
            <h3 id="futuresConfirmTitle">Confirm action?</h3>
            <p id="futuresConfirmMessage">Please confirm this action.</p>
            <label class="futures-confirm-preference" id="futuresConfirmPreferenceRow" hidden>
                <input type="checkbox" id="futuresConfirmSkip">
                <span id="futuresConfirmSkipLabel">Do not show this confirmation again for this action.</span>
            </label>
            <div class="wallet-modal-actions">
                <button type="button" class="modal-cancel-btn" id="futuresConfirmCancel">Cancel</button>
                <button type="button" class="modal-submit-btn" id="futuresConfirmOk">Confirm</button>
            </div>
        </div>
    </div>

    <!-- Create Trading Account Modal -->
    <div class="futures-modal-backdrop" id="createAccountModalBackdrop" hidden>
        <div class="futures-modal-card create-account-card">
            <div class="futures-modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
                <h3 style="margin:0;font-size:1.1rem;color:#fff;">Create Trading Account</h3>
                <button type="button" class="modal-cancel-btn" id="btnCloseCreateAccountModal" style="padding:2px 8px;font-size:1.1rem;background:none;border:none;color:var(--kv-muted);cursor:pointer;">&times;</button>
            </div>
            <form id="createAccountForm">
                <label style="display:block;margin-bottom:14px;">
                    <span style="display:block;font-size:0.83rem;color:var(--kv-muted);margin-bottom:6px;">Account Name</span>
                    <input type="text" id="newAccountName" class="form-control" placeholder="e.g. Scalping, Swing, Bot" maxlength="40" required style="width:100%;padding:10px 12px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-size:0.9rem;">
                </label>
                <div class="wallet-modal-actions" style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="modal-cancel-btn" id="btnCancelCreateAccount">Cancel</button>
                    <button type="submit" class="modal-submit-btn" id="btnSubmitCreateAccount" style="background:#22c55e;color:#000;font-weight:600;">Create Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Set Leverage Popup Modal (Matches reference image with Purple theme & up to 1000x) -->
    <div class="futures-modal-backdrop" id="setLeverageModalBackdrop" aria-hidden="true" style="display: none;">
        <div class="set-leverage-modal-card" role="dialog" aria-labelledby="setLeverageTitle">
            <!-- Header -->
            <div class="set-leverage-header">
                <div class="set-leverage-header-left">
                    <h3 id="setLeverageTitle" class="set-leverage-title">Set Leverage</h3>
                    <p class="set-leverage-subtitle">Adjust the leverage for your position. Higher leverage increases both potential profit and risk.</p>
                </div>
                <button type="button" class="set-leverage-close-btn" id="btnCloseLeverageModal" aria-label="Close modal">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <!-- Big Display Box -->
            <div class="set-leverage-display-box">
                <div class="set-leverage-big-val" id="modalLeverageDisplayVal">
                    <span id="modalLeverageValNum">100</span><span class="set-leverage-unit">x</span>
                </div>
                <div class="set-leverage-display-label">Current Leverage</div>
            </div>

            <!-- Slider Section -->
            <div class="set-leverage-slider-container">
                <div class="set-leverage-slider-track" id="modalLeverageTrack">
                    <!-- Tooltip floating over thumb -->
                    <div class="set-leverage-tooltip" id="modalLeverageTooltip" style="left: 16.666%;">
                        <span id="modalLeverageTooltipVal">100x</span>
                    </div>

                    <!-- Active Fill Bar -->
                    <div class="set-leverage-fill" id="modalLeverageFill" style="width: 16.666%;"></div>

                    <!-- Diamond Thumb -->
                    <div class="set-leverage-thumb" id="modalLeverageThumb" tabindex="0" role="slider" aria-valuemin="1" aria-valuemax="1000" aria-valuenow="100" style="left: 16.666%;"></div>

                    <!-- Ticks along rail -->
                    <div class="set-leverage-ticks">
                        <div class="set-leverage-tick is-active" data-tick-val="1" style="left: 0%;">
                            <span class="tick-diamond"></span>
                            <span class="tick-label">1x</span>
                        </div>
                        <div class="set-leverage-tick is-active" data-tick-val="100" style="left: 16.666%;">
                            <span class="tick-diamond"></span>
                            <span class="tick-label">100x</span>
                        </div>
                        <div class="set-leverage-tick" data-tick-val="200" style="left: 33.333%;">
                            <span class="tick-diamond"></span>
                            <span class="tick-label">200x</span>
                        </div>
                        <div class="set-leverage-tick" data-tick-val="300" style="left: 50%;">
                            <span class="tick-diamond"></span>
                            <span class="tick-label">300x</span>
                        </div>
                        <div class="set-leverage-tick" data-tick-val="500" style="left: 66.666%;">
                            <span class="tick-diamond"></span>
                            <span class="tick-label">500x</span>
                        </div>
                        <div class="set-leverage-tick" data-tick-val="750" style="left: 83.333%;">
                            <span class="tick-diamond"></span>
                            <span class="tick-label">750x</span>
                        </div>
                        <div class="set-leverage-tick" data-tick-val="1000" style="left: 100%;">
                            <span class="tick-diamond"></span>
                            <span class="tick-label">1000x</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preset Buttons (9 buttons matching image) -->
            <div class="set-leverage-presets-row">
                <button type="button" class="set-leverage-preset-btn" data-lev="1">1x</button>
                <button type="button" class="set-leverage-preset-btn" data-lev="5">5x</button>
                <button type="button" class="set-leverage-preset-btn" data-lev="10">10x</button>
                <button type="button" class="set-leverage-preset-btn" data-lev="20">20x</button>
                <button type="button" class="set-leverage-preset-btn" data-lev="50">50x</button>
                <button type="button" class="set-leverage-preset-btn is-active" data-lev="100">100x</button>
                <button type="button" class="set-leverage-preset-btn" data-lev="200">200x</button>
                <button type="button" class="set-leverage-preset-btn" data-lev="500">500x</button>
                <button type="button" class="set-leverage-preset-btn" data-lev="1000">1000x</button>
            </div>

            <!-- Info / Notice Box -->
            <div class="set-leverage-notice">
                <div class="set-leverage-notice-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                </div>
                <div class="set-leverage-notice-text">
                    Higher leverage can amplify your profits, but it also increases the risk of liquidation. Please trade responsibly.
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="set-leverage-actions">
                <button type="button" class="set-leverage-cancel-btn" id="btnCancelLeverageModal">Cancel</button>
                <button type="button" class="set-leverage-confirm-btn" id="btnConfirmLeverageModal">Confirm</button>
            </div>
        </div>
    </div>

    <div id="futuresToastHost" class="futures-toast-host"></div>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
@endsection
