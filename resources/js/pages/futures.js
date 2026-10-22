import { getJson } from '../utils/api';
import { escapeHtml, formatCompact, formatPercent } from '../utils/format';
import { isActiveCentAccount, isCentAccount, formatUsc } from '../utils/cent';

function loadRazorpaySdk() {
    return new Promise((resolve, reject) => {
        if (window.Razorpay) {
            resolve(window.Razorpay);
            return;
        }
        const existing = document.querySelector('script[src*="razorpay"]');
        if (existing) {
            existing.addEventListener('load', () => resolve(window.Razorpay));
            existing.addEventListener('error', () => reject(new Error('Razorpay SDK failed to load.')));
            return;
        }
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.async = true;
        script.onload = () => resolve(window.Razorpay);
        script.onerror = () => reject(new Error('Failed to load Razorpay SDK.'));
        document.head.appendChild(script);
    });
}

const state = {
    symbol: String(window.KRYVEX_FUTURES_SYMBOL || 'BTCUSDT').toUpperCase().replace(/[^A-Z0-9]/g, ''),
    tickers: [],
    positions: [],
    orders: [],
    wallet: null,
    wallet_meta: {},
    depositNetworks: [],
    withdrawalAddresses: {},
    interval: '1',
    nextFundingTime: null,
    priceMap: {},
    marketSocket: null,
    depthSocket: null,
    allTickerSocket: null,
    lastFillCheckAt: {},
    lastPnlPrices: {},
    autoLiquidating: false,

    // Orderbook state
    obPrecision: 0.1,
    obMode: 'both',
    lastObPrice: null,
    lastOrderBookData: { bids: [], asks: [] },

    // TP / SL Editing State
    isEditingPositionRisk: false,
    isPlacingOrder: false,
    isClosingAll: false,
    selectedCategory: 'all',
};
const ORDER_BOOK_VISIBLE_ROWS = 18;
const RECENT_TRADES_VISIBLE_ROWS = 18;
const RECONNECT_DELAY = 1500;
const TICKER_RECONNECT_DELAY = 2000;
const PROTECTED_REFRESH_INTERVAL = 60000; // Increased to 60s to reduce requests
const PNL_UPDATE_INTERVAL = 500; // 0.5 seconds (500ms) P&L update interval after chart movement



function throttle(func, limit) {
    let inThrottle;
    return function () {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

const throttledUpdateLiveWalletMetrics = throttle(updateLiveWalletMetrics, PNL_UPDATE_INTERVAL);
const throttledUpdateLivePositionPrices = throttle(updateLivePositionPrices, PNL_UPDATE_INTERVAL);
const throttledRenderOrderBook = throttle(renderOrderBook, 300);
const throttledUpdateOrderEstimate = throttle(updateOrderEstimate, 250);

let currentChartSymbol = '';
let currentChartInterval = '';
let currentChartTheme = '';


const tradeCloseSound = new Audio('/sounds/close.mp3');

tradeCloseSound.preload = 'auto';
tradeCloseSound.volume = 1;

function playTradeCloseSound() {
    try {
        tradeCloseSound.pause();
        tradeCloseSound.currentTime = 0;

        tradeCloseSound.play().catch((error) => {
            console.warn('Trade close sound blocked or failed:', error);
        });
    } catch (error) {
        console.warn('Trade close sound error:', error);
    }
}

const tradingAccountsState = {
    accounts: [],
    activeAccountUid: localStorage.getItem('kryvex_active_account_uid') || null,
};

let futuresInrState = {
    baseCurrency: null,
    usdtInrRate: 0,
    adminBankDetails: null,
};

let futuresActiveModalTab = 'usdt';

async function loadTradingAccounts() {
    const token = getAccessToken();
    if (!token) return;

    let user = {};
    try {
        user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (e) {}
    const broker = (user.broker_type || 'kryvex').toLowerCase();

    try {
        const data = await authFetch(`/api/trading-accounts?broker_type=all`, {
            headers: { 'X-Broker-Type': 'all' }
        });
        if (data.success && Array.isArray(data.data)) {
            tradingAccountsState.accounts = data.data;
            window.__cachedTradingAccounts = data.data;
            try {
                localStorage.setItem('kryvex_cached_trading_accounts', JSON.stringify(data.data));
            } catch (e) {}

            const isLive = getFuturesActiveMode() === 'live';
            const demoAcc = data.data.find(a => a.account_type === 'demo' || a.is_demo);
            const liveAcc = data.data.find(a => a.account_type !== 'demo' && !a.is_demo && a.is_default) || data.data.find(a => a.account_type !== 'demo' && !a.is_demo);
            const targetDefault = isLive ? liveAcc : (demoAcc || liveAcc);
            const currentValid = data.data.find(a => a.account_uid === tradingAccountsState.activeAccountUid);
            const isCurrentModeMismatch = isLive 
                ? (currentValid && (currentValid.account_type === 'demo' || currentValid.is_demo))
                : (currentValid && (currentValid.account_type !== 'demo' && !currentValid.is_demo));

            if ((!currentValid || isCurrentModeMismatch) && targetDefault) {
                tradingAccountsState.activeAccountUid = targetDefault.account_uid;
                localStorage.setItem('kryvex_active_account_uid', targetDefault.account_uid);
            }

            renderTradingAccountDropdown();
            renderTransferAccountSelect();
            updateLiveWalletMetrics();
        }
    } catch (e) {
        console.warn('Failed to load trading accounts:', e);
    }
}

function getFuturesActiveMode() {
    return (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live' ? 'live' : 'demo';
}

function getFuturesMainBalance(forAccount = null) {
    const isLive = getFuturesActiveMode() === 'live';
    const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
    const isINR = isLive && (rawCur === 'INR' || (typeof futuresActiveModalTab !== 'undefined' && futuresActiveModalTab === 'inr'));
    const buyRate = (typeof futuresInrState !== 'undefined' && (futuresInrState.usdtInrBuyRate || futuresInrState.usdtInrRate)) || 83.00;

    const accounts = (tradingAccountsState.accounts && tradingAccountsState.accounts.length > 0)
        ? tradingAccountsState.accounts
        : (window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]'));

    const activeUid = tradingAccountsState.activeAccountUid || localStorage.getItem('kryvex_active_account_uid');
    const curAcc = forAccount || accounts.find(a => String(a.account_uid) === String(activeUid)) || accounts.find(a => a.is_default && !a.is_demo) || accounts[0];

    let usdt = 0;
    let inr = 0;

    if (isLive) {
        if (curAcc) {
            const rawMb = Number(curAcc.real_main_balance !== undefined ? curAcc.real_main_balance : (curAcc.is_default ? (state.wallet?.real_main_balance ?? 0) : 0));
            usdt = rawMb;
            const rawMbInr = Number(curAcc.real_main_balance_inr !== undefined ? curAcc.real_main_balance_inr : (curAcc.is_default ? (state.wallet?.real_main_balance_inr ?? (rawMb * buyRate)) : (rawMb * buyRate)));
            inr = rawMbInr;
        } else {
            usdt = Number(state.wallet?.real_main_balance || 0);
            inr = Number(state.wallet?.real_main_balance_inr || (usdt * buyRate));
        }
    } else {
        usdt = Number(state.wallet?.demo_main_balance || 0);
        inr = Number(state.wallet?.demo_main_balance_inr || (usdt * buyRate));
    }

    return { usdt, inr, formatted: isINR ? formatInr(inr) : formatUsdt(usdt) };
}

function getFuturesTransferAccount() {
    const uid = document.getElementById('transferTradingAccountSelect')?.value
        || document.getElementById('walletTransferAccountSelectInline')?.value
        || document.getElementById('walletTransferAccountSelect')?.value
        || tradingAccountsState.activeAccountUid
        || localStorage.getItem('kryvex_active_account_uid');
    return (tradingAccountsState.accounts || []).find(a => a.account_uid === uid) || null;
}

function getFuturesAccountAvailable(acc) {
    const isLive = getFuturesActiveMode() === 'live';
    const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
    const isINR = isLive && (rawCur === 'INR' || (typeof futuresActiveModalTab !== 'undefined' && futuresActiveModalTab === 'inr'));
    const buyRate = (typeof futuresInrState !== 'undefined' && (futuresInrState.usdtInrBuyRate || futuresInrState.usdtInrRate)) || 83.00;

    let usdt = 0;
    let inr = 0;
    if (isLive) {
        usdt = Number(acc?.real_available_balance !== undefined ? acc.real_available_balance : (acc?.is_default ? (state.wallet?.real_available_balance ?? 0) : 0));
        inr = Number(acc?.real_available_balance_inr !== undefined ? acc.real_available_balance_inr : (acc?.is_default ? (state.wallet?.real_available_balance_inr ?? (usdt * buyRate)) : (usdt * buyRate)));
    } else {
        usdt = Number(acc?.demo_available_balance ?? (acc?.is_default ? (state.wallet?.demo_available_balance ?? 0) : 0));
        inr = Number(acc?.demo_available_balance_inr ?? (acc?.is_default ? (state.wallet?.demo_available_balance_inr ?? (usdt * buyRate)) : (usdt * buyRate)));
    }
    if (inr <= 0 && usdt > 0) inr = usdt * buyRate;
    if (usdt <= 0 && inr > 0) usdt = inr / buyRate;
    const formattedUsd = (isLive && isCentAccount(acc)) ? formatUsc(usdt) : formatUsdtAsset(usdt);
    return { usdt, inr, formatted: isINR ? formatInr(inr) : formattedUsd };
}

function renderTradingAccountDropdown() {
    const isLive = getFuturesActiveMode() === 'live';
    const filteredAccounts = (tradingAccountsState.accounts || []).filter(acc => {
        const isDemo = (acc.account_type === 'demo' || acc.is_demo);
        return isLive ? !isDemo : isDemo;
    });

    const activeAcc = tradingAccountsState.accounts.find(a => a.account_uid === tradingAccountsState.activeAccountUid) || filteredAccounts[0];
    const activeNameEl = document.getElementById('activeAccountName');
    if (activeNameEl && activeAcc) {
        activeNameEl.textContent = activeAcc.account_name;
    }

    const dropdownList = document.getElementById('tradingAccountDropdownList');
    if (dropdownList) {
        dropdownList.innerHTML = filteredAccounts.map(acc => {
            const isActive = acc.account_uid === (activeAcc?.account_uid);
            const bal = getFuturesAccountAvailable(acc);
            return `
                <button type="button" 
                    class="w-full text-left px-3 py-2 text-xs flex items-center justify-between hover:bg-slate-700/50 transition-colors ${isActive ? 'text-purple-400 font-bold bg-purple-500/10' : 'text-slate-300'}"
                    data-account-uid="${acc.account_uid}"
                    onclick="switchFuturesTradingAccount('${acc.account_uid}')">
                    <span class="truncate">${escapeHtml(acc.account_name)}</span>
                    <span class="text-[11px] font-mono opacity-75 ml-2 shrink-0">${bal.formatted}</span>
                </button>
            `;
        }).join('');
    }

    const canCreateSubAccount = isLive && (filteredAccounts.length < 5);
    const dropdownFooter = document.getElementById('tradingAccountDropdownFooter');
    if (dropdownFooter) {
        dropdownFooter.style.display = canCreateSubAccount ? 'block' : 'none';
    }
}

function renderTransferAccountSelect() {
    const select = document.getElementById('walletTransferAccountSelect');
    const selectInline = document.getElementById('walletTransferAccountSelectInline');
    const selectModern = document.getElementById('transferTradingAccountSelect');
    if (!select && !selectInline && !selectModern) return;

    const liveAccounts = (tradingAccountsState.accounts || []).filter(acc => 
        !acc.is_demo && 
        acc.account_type !== 'demo' && 
        acc.account_name !== 'Demo Account' && 
        !String(acc.account_name || '').toLowerCase().startsWith('demo')
    );

    // Read the user's selection before the options are rebuilt, otherwise it resets to the first account.
    const currentVal = selectModern?.value || select?.value || selectInline?.value;

    let html = '';
    liveAccounts.forEach(acc => {
        const bal = getFuturesAccountAvailable(acc);
        html += `<option value="${acc.account_uid}">${escapeHtml(acc.account_name)} (Trading: ${bal.formatted})</option>`;
    });

    [select, selectInline, selectModern].forEach(el => {
        if (el && el.innerHTML !== html) el.innerHTML = html;
    });

    const activeUid = (liveAccounts.some(a => a.account_uid === currentVal) ? currentVal : null)
        || (liveAccounts.some(a => a.account_uid === tradingAccountsState.activeAccountUid) ? tradingAccountsState.activeAccountUid : null)
        || (liveAccounts[0]?.account_uid ?? '');

    if (activeUid) {
        if (select && Array.from(select.options).some(o => o.value === activeUid)) select.value = activeUid;
        if (selectInline && Array.from(selectInline.options).some(o => o.value === activeUid)) selectInline.value = activeUid;
        if (selectModern && Array.from(selectModern.options).some(o => o.value === activeUid)) selectModern.value = activeUid;
    }
}

function switchTradingAccount(accountUid, silent = false) {
    const dropdown = document.getElementById('tradingAccountDropdown');
    if (dropdown) dropdown.hidden = true;

    if (accountUid === tradingAccountsState.activeAccountUid) return;

    tradingAccountsState.activeAccountUid = accountUid;
    localStorage.setItem('kryvex_active_account_uid', accountUid);

    const activeAcc = tradingAccountsState.accounts.find(a => a.account_uid === accountUid);
    try {
        sessionStorage.setItem('kryvex_pending_toast', JSON.stringify({
            type: 'success',
            title: 'Account Selected',
            message: `Switched to ${activeAcc ? activeAcc.account_name : 'Trading Account'}`
        }));
    } catch (e) {}

    window.location.reload();
}
window.switchFuturesTradingAccount = switchTradingAccount;

window.addEventListener('tradingAccountChanged', (e) => {
    const uid = e.detail?.accountUid;
    if (uid && uid !== tradingAccountsState.activeAccountUid) {
        tradingAccountsState.activeAccountUid = uid;
        localStorage.setItem('kryvex_active_account_uid', uid);
        renderTradingAccountDropdown();
        Promise.all([
            loadWalletBalance(),
            loadPositions(),
            loadOrders(),
            loadTradeHistory(),
        ]);
    }
});

function setupTradingAccountControls() {
    const dropdownBtn = document.getElementById('tradingAccountDropdownBtn');
    const dropdown = document.getElementById('tradingAccountDropdown');
    const openCreateBtn = document.getElementById('btnOpenCreateAccountModal');
    const createModalBackdrop = document.getElementById('createAccountModalBackdrop');
    const closeCreateBtn = document.getElementById('btnCloseCreateAccountModal');
    const cancelCreateBtn = document.getElementById('btnCancelCreateAccount');
    const createForm = document.getElementById('createAccountForm');

    if (dropdownBtn && dropdown) {
        dropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdown.hidden = !dropdown.hidden;
        });

        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && !dropdownBtn.contains(e.target)) {
                dropdown.hidden = true;
            }
        });
    }

    const closeCreateModal = () => {
        if (createModalBackdrop) {
            createModalBackdrop.hidden = true;
            createModalBackdrop.classList.remove('is-open');
            createModalBackdrop.style.display = 'none';
        }
    };

    if (createModalBackdrop) {
        createModalBackdrop.addEventListener('click', (e) => {
            if (e.target === createModalBackdrop) closeCreateModal();
        });
    }

    if (openCreateBtn) {
        openCreateBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (!getAccessToken()) {
                showFuturesToast('Please login first to create trading accounts.', 'error');
                return;
            }

            let user = {};
            try {
                user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
            } catch (err) {}

            if ((user.broker_type || 'kryvex').toLowerCase() === 'mt5') {
                showFuturesToast('Sub-accounts are not available on MetaTrader 5 (MT5). Only the Main Account is available.', 'error');
                return;
            }

            const kycStatus = String(user.kyc_status || '').toLowerCase();
            if (user.kyc_status && !['verified', 'approved'].includes(kycStatus)) {
                showFuturesToast('KYC verification is required to create new trading accounts.', 'error');
                return;
            }

            if (dropdown) dropdown.hidden = true;
            if (createModalBackdrop) {
                createModalBackdrop.hidden = false;
                createModalBackdrop.classList.add('is-open');
                createModalBackdrop.style.display = 'flex';
            }
            const input = document.getElementById('newAccountName');
            if (input) {
                input.value = '';
                setTimeout(() => input.focus(), 80);
            }
        });
    }

    if (closeCreateBtn) closeCreateBtn.addEventListener('click', closeCreateModal);
    if (cancelCreateBtn) cancelCreateBtn.addEventListener('click', closeCreateModal);

    if (createForm) {
        createForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = document.getElementById('newAccountName');
            const name = input ? input.value.trim() : '';

            if (!name) return;

            const submitBtn = document.getElementById('btnSubmitCreateAccount');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating...';
            }

            try {
                const res = await authFetch('/api/trading-accounts', {
                    method: 'POST',
                    body: JSON.stringify({ account_name: name }),
                });

                showFuturesToast(res.message || 'Trading account created!', 'success');
                closeCreateModal();
                await loadTradingAccounts();
                if (res.data && res.data.account_uid) {
                    switchTradingAccount(res.data.account_uid);
                }
            } catch (err) {
                showFuturesToast(err.message || 'Failed to create trading account.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Account';
                }
            }
        });
    }

    if (new URLSearchParams(window.location.search).get('manage') === '1') {
        setTimeout(() => {
            if (openCreateBtn) openCreateBtn.click();
        }, 200);
    }
}

async function bootFuturesPage() {
    updateOrderTicketSymbolSpec();
    // 1. Initialize UI Controls safely
    try { setupTradingAccountControls(); } catch (e) { console.warn('setupTradingAccountControls error:', e); }
    try { setupContractDropdown(); } catch (e) { console.warn('setupContractDropdown error:', e); }
    try { setupContractCategoryFilters(); } catch (e) { console.warn('setupContractCategoryFilters error:', e); }
    try { setupPanelToggles(); } catch (e) { console.warn('setupPanelToggles error:', e); }
    try { setupMarketPanelTabs(); } catch (e) { console.warn('setupMarketPanelTabs error:', e); }
    try { setupIntervalButtons(); } catch (e) { console.warn('setupIntervalButtons error:', e); }
    try { setupTabs(); } catch (e) { console.warn('setupTabs error:', e); }
    try { setupOrderForm(); } catch (e) { console.warn('setupOrderForm error:', e); }
    try { setupWalletActionModal(); } catch (e) { console.warn('setupWalletActionModal error:', e); }
    try { setupFuturesAddFundsModal(); } catch (e) { console.warn('setupFuturesAddFundsModal error:', e); }
    try { setupFuturesDemoDepositModals(); } catch (e) { console.warn('setupFuturesDemoDepositModals error:', e); }
    try { setupCloseAllPositionsButton(); } catch (e) { console.warn('setupCloseAllPositionsButton error:', e); }
    try { setupPositionsTableEvents(); } catch (e) { console.warn('setupPositionsTableEvents error:', e); }
    try { setupOrderbookControls(); } catch (e) { console.warn('setupOrderbookControls error:', e); }
    try { setupMobileResponsiveHandlers(); } catch (e) { console.warn('setupMobileResponsiveHandlers error:', e); }
    try { syncFuturesTradingModeUI(); } catch (e) { console.warn('syncFuturesTradingModeUI error:', e); }

    // 2. RENDER TRADINGVIEW CHART IMMEDIATELY (Zero delay)
    try {
        renderTradingViewChart(true);
    } catch (e) {
        console.warn('renderTradingViewChart error:', e);
    }

    // 3. LOAD ORDER BOOK & CONNECT WEBSOCKETS IMMEDIATELY (Zero delay)
    try {
        loadDepth();
    } catch (e) {
        console.warn('loadDepth error:', e);
    }
    try { connectDepthSocket(); } catch (e) { console.warn('connectDepthSocket error:', e); }
    try { connectMarketSocket(); } catch (e) { console.warn('connectMarketSocket error:', e); }
    try { connectAllTickerSocket(); } catch (e) { console.warn('connectAllTickerSocket error:', e); }

    // 4. LOAD MARKET TICKERS ASYNCHRONOUSLY (Non-blocking)
    loadTickers().catch((e) => {
        console.warn('loadTickers failed:', e);
        updateHeader();
    });

    // 5. LOAD STATS & USER PROTECTED DATA ASYNCHRONOUSLY
    try { loadStats(); } catch (e) { console.warn(e); }
    try { loadTradeHistory(); } catch (e) { console.warn(e); }
    try { await loadTradingAccounts(); } catch (e) { console.warn(e); }
    try { await loadProtectedFuturesData(); } catch (e) { console.warn(e); }
    try { await loadFuturesInrConfig(); } catch (e) { console.warn(e); }
    try { syncFuturesTradingModeUI(); } catch (e) { console.warn(e); }

    // 6. PERIODIC INTERVALS
    setInterval(refreshProtectedFuturesDataQuietly, PROTECTED_REFRESH_INTERVAL);
    setInterval(renderFundingCountdown, 1000);
    setInterval(loadDepth, 1500); // Polling backup for orderbook
}



async function loadTickers() {
    try {
        const result = await getJson('/api/markets/futures/tickers');

        state.tickers = result.data || [];

        state.tickers.forEach((ticker) => {
            state.priceMap[ticker.symbol] = Number(ticker.last_price || 0);
        });

        updateWishlistCount();
        filterAndRenderContracts();
        updateHeader();
    } catch (e) {
        console.warn('loadTickers error:', e);
        updateHeader();
    }
}



function connectMarketSocket() {
    closeSocket(state.marketSocket);

    const symbol = state.symbol.toLowerCase();

    state.marketSocket = new WebSocket(
        `wss://fstream.binance.com/market/stream?streams=${symbol}@aggTrade/${symbol}@markPrice@1s`
    );

    state.marketSocket.onmessage = (event) => {
        const payload = safeJson(event.data);
        const stream = payload.stream || '';
        const data = payload.data || {};

        if (stream.endsWith('@aggTrade')) {
            const price = Number(data.p || 0);
            const quantity = Number(data.q || 0);

            if (price) {
                applyLivePrice(state.symbol, price);
            }

            prependLiveTrade({
                price,
                quantity,
                time: data.T || Date.now(),
                is_buyer_maker: Boolean(data.m),
            });
        }

        if (stream.includes('@markPrice')) {
            state.nextFundingTime = data.T || state.nextFundingTime;

            if (Number(data.p || 0)) {
                // Keep card Mark Price equal to live chart/last price as requested.
                setText('futuresMarkPrice', formatUsdPrice(getLivePriceForSymbol(state.symbol) || data.p));
            }

            if (Number(data.i || 0)) {
                setText('futuresIndexPrice', formatUsdPrice(data.i));
            }

            setText('futuresFundingRate', formatFundingRate(data.r));
            renderFundingCountdown();
        }
    };

    state.marketSocket.onclose = () => {
        setTimeout(connectMarketSocket, RECONNECT_DELAY);
    };
}

// Live price change & Chart Sync
let latestSyncedPrice = null;

function updateChartSyncedPrices(symbol, price) {
    if (symbol !== state.symbol || !price) return;
    latestSyncedPrice = price;
    flushChartSyncedPrices();
}

function flushChartSyncedPrices() {
    if (!latestSyncedPrice) return;
    const formatted = formatUsdPrice(latestSyncedPrice);

    setText('orderPanelMarkPrice', formatted);
    setText('mobileSellPrice', formatted);
    setText('mobileBuyPrice', formatted);

    const midPriceEl = document.getElementById('obMidPrice') || document.getElementById('orderBookMid');
    const markEl = document.getElementById('obMarkPrice');
    if (midPriceEl) midPriceEl.textContent = formatted;
    if (markEl) markEl.textContent = formatted;
}

// Real-time WebSocket Depth Stream (Directly live with zero lag)
function connectDepthSocket() {
    closeSocket(state.depthSocket);

    const symbol = state.symbol.toLowerCase();
    state.depthSocket = new WebSocket(
        `wss://fstream.binance.com/ws/${symbol}@depth20@100ms`
    );

    state.depthSocket.onmessage = (event) => {
        const payload = safeJson(event.data);
        const data = payload.data || payload;

        const bids = data.b || data.bids || [];
        const asks = data.a || data.asks || [];

        if (bids.length || asks.length) {
            throttledRenderOrderBook({
                bids: bids.map((row) => ({
                    price: Number(row[0] || 0),
                    quantity: Number(row[1] || 0),
                })),
                asks: asks.map((row) => ({
                    price: Number(row[0] || 0),
                    quantity: Number(row[1] || 0),
                })),
            });
        }

        setText('depthSource', 'Live');
    };

    state.depthSocket.onerror = (err) => {
        console.warn('Depth socket error:', err);
    };

    state.depthSocket.onclose = () => {
        setTimeout(connectDepthSocket, RECONNECT_DELAY);
    };
}

function connectAllTickerSocket() {
    closeSocket(state.allTickerSocket);

    state.allTickerSocket = new WebSocket('wss://fstream.binance.com/market/ws/!ticker@arr');

    state.allTickerSocket.onmessage = (event) => {
        const data = safeJson(event.data);
        const allowedSymbols = new Set(state.tickers.map((ticker) => ticker.symbol));
        const rows = Array.isArray(data) ? data : [];

        rows.forEach((item) => {
            const symbol = item.s;

            if (!allowedSymbols.has(symbol)) {
                return;
            }

            const price = Number(item.c || 0);

            if (price) {
                applyLivePrice(symbol, price, {
                    price_change_percent: Number(item.P || 0),
                    high_price: Number(item.h || 0),
                    low_price: Number(item.l || 0),
                    quote_volume: Number(item.q || 0),
                    volume: Number(item.v || 0),
                });
            }
        });
    };

    state.allTickerSocket.onclose = () => {
        setTimeout(connectAllTickerSocket, TICKER_RECONNECT_DELAY);
    };
}

function updateTopMarqueeTicker(symbol, price, changePercent) {
    const priceEls = document.querySelectorAll(`[data-ticker-price="${symbol}"]`);
    const changeEls = document.querySelectorAll(`[data-ticker-change="${symbol}"]`);

    if (price && priceEls.length) {
        const formattedPrice = formatUsdPrice(price);
        priceEls.forEach((el) => {
            if (el.textContent !== formattedPrice) {
                el.textContent = formattedPrice;
            }
        });
    }

    if (changePercent !== undefined && changePercent !== null && changeEls.length) {
        const isPositive = Number(changePercent || 0) >= 0;
        const formattedChange = formatPercent(changePercent);
        const newClass = isPositive ? 'text-emerald-400 not-italic font-bold' : 'text-red-400 not-italic font-bold';

        changeEls.forEach((el) => {
            if (el.textContent !== formattedChange) {
                el.textContent = formattedChange;
            }
            if (el.className !== newClass) {
                el.className = newClass;
            }
        });
    }
}

function applyLivePrice(symbol, price, extra = {}) {
    const newPrice = Number(price || 0);
    const oldPrice = Number(state.priceMap[symbol] || 0);

    state.priceMap[symbol] = newPrice;

    state.tickers = state.tickers.map((ticker) => {
        if (ticker.symbol !== symbol) {
            return ticker;
        }

        return {
            ...ticker,
            last_price: Number(newPrice || ticker.last_price || 0),
            ...extra,
        };
    });

    updateTopMarqueeTicker(symbol, newPrice, extra.price_change_percent);

    if (symbol === state.symbol) {
        updateHeader();
        flushChartSyncedPrices();
    }

    const isPriceMoved = newPrice > 0 && oldPrice > 0 && newPrice !== oldPrice;

    if (isPriceMoved || !state.lastPnlPrices[symbol]) {
        state.lastPnlPrices[symbol] = newPrice;
        checkFuturesTpSlAutoClose(symbol, newPrice);

        if (!state.isEditingPositionRisk) {
            updateLivePositionPrices(); // Instant synchronous mark price & pnl update
        }

        throttledUpdateLiveWalletMetrics();
    }

    const openOrders = state.orders.filter((order) => order.status === 'open');
    renderOpenOrders(openOrders);

    updateOrderEstimate();
    processDemoFillsForSymbol(symbol, newPrice);
}

function prependLiveTrade(trade) {
    const box = document.getElementById('recentTradesBody') || document.getElementById('recentTradesList');

    if (!box || !trade.price) {
        return;
    }

    const tradeClass = trade.is_buyer_maker ? 'sell' : 'buy';

    const row = document.createElement('div');
    row.className = `rt-row ${tradeClass}`;
    row.innerHTML = `
        <span class="rt-price">${formatUsdPrice(trade.price)}</span>
        <span class="rt-size">${formatQuantity(trade.quantity)}</span>
        <span class="rt-time">${formatTradeTime(trade.time)}</span>
    `;

    box.prepend(row);

    while (box.children.length > RECENT_TRADES_VISIBLE_ROWS) {
        box.removeChild(box.lastElementChild);
    }
}
// old 
// function closeSocket(socket) {
//     if (socket && socket.readyState === WebSocket.OPEN) {
//         socket.close();
//     }
// }
function closeSocket(socket) {
    if (
        socket &&
        (
            socket.readyState === WebSocket.CONNECTING ||
            socket.readyState === WebSocket.OPEN
        )
    ) {
        socket.onclose = null;
        socket.close();
    }
}

function safeJson(value) {
    try {
        return JSON.parse(value);
    } catch (error) {
        return {};
    }
}


async function refreshProtectedFuturesDataQuietly() {
    if (!getAccessToken()) {
        return;
    }

    try {

        if (state.isEditingPositionRisk) {

            await Promise.all([
                loadWalletBalance(),
                loadOrders(),
                loadTradeHistory(),
            ]);

            updateLiveWalletMetrics();
            return;
        }

        await Promise.all([
            loadWalletBalance(),
            loadPositions(),
            loadOrders(),
            loadTradeHistory(),
        ]);

        updateLiveWalletMetrics();

    } catch (error) {
        // Silent
    }
}


function setupContractDropdown() {
    const toggle = document.getElementById('contractDropdownToggle');
    const dropdown = document.getElementById('contractDropdown');
    const backdrop = document.getElementById('contractDropdownBackdrop');
    const search = document.getElementById('contractSearchInput');

    const openDropdown = () => {
        if (!dropdown) return;
        dropdown.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-open');
        if (search) {
            setTimeout(() => search.focus(), 60);
        }
    };

    const closeDropdown = () => {
        if (!dropdown) return;
        dropdown.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-open');
    };

    if (toggle && dropdown) {
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            if (dropdown.classList.contains('is-open')) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        if (backdrop) {
            backdrop.addEventListener('click', (event) => {
                event.stopPropagation();
                closeDropdown();
            });
        }

        dropdown.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        const dropdownList = document.getElementById('contractDropdownList');
        dropdown.addEventListener('wheel', (event) => {
            event.stopPropagation();
            if (dropdownList) {
                dropdownList.scrollTop += event.deltaY;
            }
        }, { passive: true });

        document.addEventListener('click', (event) => {
            if (!toggle.contains(event.target) && !dropdown.contains(event.target)) {
                closeDropdown();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && dropdown.classList.contains('is-open')) {
                closeDropdown();
            }
        });
    }

    if (search) {
        search.addEventListener('input', () => {
            filterAndRenderContracts();
        });
    }
}

function getWishlistFromStorage() {
    try {
        const stored = localStorage.getItem('kryvex_futures_wishlist');
        return stored ? JSON.parse(stored) : [];
    } catch (e) {
        return [];
    }
}

function saveWishlistToStorage(wishlist) {
    localStorage.setItem('kryvex_futures_wishlist', JSON.stringify(wishlist));
}

function toggleWishlist(symbol) {
    let wishlist = getWishlistFromStorage();
    if (wishlist.includes(symbol)) {
        wishlist = wishlist.filter(s => s !== symbol);
    } else {
        wishlist.push(symbol);
    }
    saveWishlistToStorage(wishlist);
    updateWishlistCount();
    filterAndRenderContracts();
}

function updateWishlistCount() {
    const wishlist = getWishlistFromStorage();
    const tab = document.getElementById('wishlistFilterTab');
    if (tab) {
        tab.textContent = `Wishlist (${wishlist.length})`;
    }
}

function filterAndRenderContracts() {
    const searchInput = document.getElementById('contractSearchInput');
    const keyword = searchInput ? searchInput.value.trim().toUpperCase() : '';

    let filtered = state.tickers;

    if (keyword) {
        filtered = filtered.filter((ticker) => ticker.symbol.includes(keyword));
    }

    const category = state.selectedCategory || 'all';
    if (category !== 'all') {
        if (category === 'wishlist') {
            const wishlist = getWishlistFromStorage();
            filtered = filtered.filter(ticker => wishlist.includes(ticker.symbol));
        } else {
            filtered = filtered.filter(ticker => {
                const symbol = ticker.symbol.toUpperCase();
                if (category === 'meme') {
                    const memeCoins = ['PEPE', 'DOGE', 'SHIB', 'BONK', 'FLOKI', 'WIF', 'MEME'];
                    return memeCoins.some(coin => symbol.startsWith(coin));
                }
                if (category === 'layer1') {
                    const l1Coins = ['BTC', 'ETH', 'SOL', 'BNB', 'ADA', 'AVAX', 'XRP', 'DOT', 'NEAR', 'LTC', 'LINK'];
                    return l1Coins.some(coin => symbol.startsWith(coin));
                }
                if (category === 'ai') {
                    const aiCoins = ['NEAR', 'FET', 'RNDR', 'AGIX', 'TAO', 'OCEAN', 'WLD'];
                    return aiCoins.some(coin => symbol.startsWith(coin));
                }
                return false;
            });
        }
    }

    renderContractDropdown(filtered);
}

function setupContractCategoryFilters() {
    const buttons = document.querySelectorAll('#contractFilterRow button');
    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            state.selectedCategory = btn.dataset.category || 'all';
            filterAndRenderContracts();
        });
    });
}

function renderContractDropdown(tickers) {
    const list = document.getElementById('contractDropdownList');

    if (!list) {
        return;
    }

    if (!tickers.length) {
        list.innerHTML = '<div class="futures-loading">No contracts found.</div>';
        return;
    }

    const wishlist = getWishlistFromStorage();

    list.innerHTML = tickers.map((ticker) => {
        const activeClass = ticker.symbol === state.symbol ? 'is-active' : '';
        const changeClass = Number(ticker.price_change_percent) >= 0 ? 'market-positive' : 'market-negative';
        const isStarred = wishlist.includes(ticker.symbol);
        const coinCode = String(ticker.symbol || '').replace(/(USDT|USD|BUSD)$/i, '').toLowerCase();
        const cdnUrl = getFuturesCoinLogo(ticker.symbol);




        return `
            <button type="button" class="contract-row ${activeClass}" data-symbol="${escapeHtml(ticker.symbol)}">
                <span class="wishlist-star ${isStarred ? 'is-starred' : ''}" data-wishlist-symbol="${escapeHtml(ticker.symbol)}">
                    ${isStarred ? '★' : '☆'}
                </span>
                <span class="contract-name" style="display: flex; align-items: center; gap: 8px;">
                    <span class="contract-coin-icon" style="width:24px; height:24px; border-radius:50%; overflow:hidden; display:inline-flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.08); font-size:10px; font-weight:800; color:#fff; flex-shrink:0;">
                        <img src="${cdnUrl}" alt="${ticker.symbol}" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                        <span style="display:none; width:100%; height:100%; align-items:center; justify-content:center;">${coinCode.slice(0, 2).toUpperCase()}</span>
                    </span>
                    <div>
                        <strong style="display:block; font-size:13px; line-height:1.2;">${formatDisplaySymbol(ticker.symbol)}</strong>
                        <small style="color:var(--kv-muted); font-size:11px;">${escapeHtml(ticker.contract_type || 'USDT Perpetual')}</small>
                    </div>
                </span>
                <span class="contract-leverage">Upto ${escapeHtml(ticker.leverage || '--')}</span>
                <span>${formatUsdPrice(ticker.last_price)}</span>
                <span class="${changeClass}">${formatPercent(ticker.price_change_percent)}</span>
            </button>
        `;
    }).join('');

    document.querySelectorAll('.wishlist-star[data-wishlist-symbol]').forEach((star) => {
        star.addEventListener('click', (event) => {
            event.stopPropagation();
            const symbol = star.dataset.wishlistSymbol;
            toggleWishlist(symbol);
        });
    });

    document.querySelectorAll('.contract-row[data-symbol]').forEach((button) => {
        button.addEventListener('click', () => {
            const symbol = button.dataset.symbol;

            if (symbol) {
                window.location.href = `/futures/${symbol.toLowerCase()}`;
            }
        });
    });
}

//update browser tab title with live price and change

let lastTabPrice = null;

function updateBrowserTabTitle() {
    const ticker = state.tickers.find(item => item.symbol === state.symbol);

    if (!ticker) {
        document.title = `${formatDisplaySymbol(state.symbol)} Futures`;
        return;
    }

    const price = Number(ticker.last_price || 0);

    let arrow = "▲";

    if (lastTabPrice !== null) {
        arrow = price >= lastTabPrice ? "▲" : "▼";
    } else {
        const change = Number(ticker.price_change_percent || 0);
        arrow = change >= 0 ? "▲" : "▼";
    }

    lastTabPrice = price;

    document.title = `${arrow} ${formatUsdPrice(price)} ${formatDisplaySymbol(state.symbol)}`;
}

function updateHeader() {
    updateOrderTicketSymbolSpec();
    const ticker = state.tickers.find((item) => item.symbol === state.symbol);

    setText('futuresSymbolTitle', formatDisplaySymbol(state.symbol));
    setText('chartSymbolLabel', formatDisplaySymbol(state.symbol));

    const logoUrl = getFuturesCoinLogo(state.symbol);
    const baseCode = String(state.symbol || 'BTC').replace(/(USDT|USD|INR|BUSD)$/i, '').toLowerCase();

    const symbolIconEl = document.getElementById('futuresSymbolIcon');
    if (symbolIconEl) {
        const currentImg = symbolIconEl.querySelector('img');
        if (!currentImg) {
            symbolIconEl.innerHTML = `<img src="${logoUrl}" alt="${state.symbol}" data-sym="${state.symbol}" style="width:100%; height:100%; border-radius:50%; object-fit:cover; display:block;" onerror="this.onerror=null;this.src='https://assets.coincap.io/assets/icons/${baseCode}@2x.png';">`;
        } else if (currentImg.getAttribute('data-sym') !== state.symbol) {
            currentImg.setAttribute('data-sym', state.symbol);
            currentImg.alt = state.symbol;
            currentImg.onerror = function() {
                this.onerror = null;
                this.src = `https://assets.coincap.io/assets/icons/${baseCode}@2x.png`;
            };
            currentImg.src = logoUrl;
        }
    }

    const marginCoinIconEl = document.getElementById('marginRatioCoinIcon');
    if (marginCoinIconEl) {
        const currentMarginImg = marginCoinIconEl.querySelector('img');
        if (!currentMarginImg) {
            marginCoinIconEl.innerHTML = `<img src="${logoUrl}" alt="${state.symbol}" data-sym="${state.symbol}" style="width:100%; height:100%; border-radius:50%; object-fit:cover; display:block;" onerror="this.onerror=null;this.src='https://assets.coincap.io/assets/icons/${baseCode}@2x.png';">`;
        } else if (currentMarginImg.getAttribute('data-sym') !== state.symbol) {
            currentMarginImg.setAttribute('data-sym', state.symbol);
            currentMarginImg.alt = state.symbol;
            currentMarginImg.onerror = function() {
                this.onerror = null;
                this.src = `https://assets.coincap.io/assets/icons/${baseCode}@2x.png`;
            };
            currentMarginImg.src = logoUrl;
        }
    }

    syncMarginModeWithActivePosition();



    if (!ticker) {
        return;
    }

    setText('futuresLastPrice', formatUsdPrice(ticker.last_price));
    setText('futuresMarkPrice', formatUsdPrice(ticker.last_price));
    setText('futuresHeaderMarkPrice', formatUsdPrice(ticker.last_price));
    
    const headerChangePct = Number(ticker.price_change_percent || 0);
    const headerChangeEl = document.getElementById('futuresHeader24hChange');
    if (headerChangeEl) {
        headerChangeEl.textContent = (headerChangePct >= 0 ? '+' : '') + headerChangePct.toFixed(2) + '%';
        headerChangeEl.style.color = headerChangePct < 0 ? '#f43f5e' : '#10b981';
    }

    // Price in Order Ticket & Order Book is updated only AFTER TradingView chart moves!
    updateChartSyncedPrices(state.symbol, ticker.last_price);

    setText('futuresChange', formatPercent(ticker.price_change_percent));
    setText('futuresHigh', formatUsdPrice(ticker.high_price));
    setText('futuresLow', formatUsdPrice(ticker.low_price));
    setText('futuresVolume', `$${formatCompact(ticker.quote_volume || ticker.volume)}`);

    const change = document.getElementById('futuresChange');
    const priceElement = document.getElementById('futuresLastPrice');

    if (change) {
        change.classList.toggle('market-positive', Number(ticker.price_change_percent) >= 0);
        change.classList.toggle('market-negative', Number(ticker.price_change_percent) < 0);
    }

    if (priceElement) {
        priceElement.classList.toggle('market-positive', Number(ticker.price_change_percent) >= 0);
        priceElement.classList.toggle('market-negative', Number(ticker.price_change_percent) < 0);
    }

    const baseAsset = state.symbol.replace(/USDT$/i, '').replace(/USD$/i, '');
    document.querySelectorAll('.base-asset-label').forEach(el => {
        el.textContent = baseAsset;
    });

    updateLiveWalletMetrics();
    updateBrowserTabTitle();
}


async function loadStats() {
    try {
        const result = await getJson(`/api/markets/futures/${state.symbol}/stats`);
        const data = result.data || {};

        state.nextFundingTime = data.next_funding_time || null;

        // Mark price is synced to the same live price used by the chart.
        setText('futuresIndexPrice', formatUsdPrice(data.index_price));
        setText('futuresFundingRate', formatFundingRate(data.funding_rate));
        setText('futuresOpenInterest', formatCompact(data.open_interest));

        renderFundingCountdown();
    } catch (error) {
        setText('futuresFundingRate', '--');
    }
}

async function loadDepth() {
    try {
        const result = await getJson(`/api/markets/futures/${state.symbol}/depth`);

        setText('depthSource', result.source === 'binance' ? 'Live' : 'Fallback');

        renderOrderBook(result.data || {});
    } catch (error) {
        setText('depthSource', 'Error');
    }
}

function setupOrderbookControls() {
    document.querySelectorAll('.ob-mode-btn[data-ob-mode]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.ob-mode-btn').forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');
            state.obMode = btn.dataset.obMode || 'both';
            renderOrderBook(state.lastOrderBookData);
        });
    });

    const precSelect = document.getElementById('obPrecisionSelect');
    if (precSelect) {
        precSelect.addEventListener('change', () => {
            state.obPrecision = Number(precSelect.value) || 0.1;
            renderOrderBook(state.lastOrderBookData);
        });
    }
}

function aggregateDepthByPrecision(list, precision, side) {
    const map = new Map();
    list.forEach(item => {
        const roundedPrice = Math.floor(item.price / precision) * precision;
        map.set(roundedPrice, (map.get(roundedPrice) || 0) + item.quantity);
    });
    const result = [];
    map.forEach((qty, price) => {
        result.push({ price, quantity: qty });
    });
    return side === 'ask' ? result.sort((a, b) => a.price - b.price) : result.sort((a, b) => b.price - a.price);
}

function padDepthRows(rows, targetCount, side, precision = 0.1) {
    if (!rows || targetCount <= 0) return [];
    if (rows.length >= targetCount) return rows.slice(0, targetCount);

    const result = [...rows];
    const step = Number(precision) > 0 ? Number(precision) : 0.1;
    let lastItem = result.length ? result[result.length - 1] : { price: 100, quantity: 1 };

    while (result.length < targetCount && lastItem) {
        const nextPrice = side === 'ask'
            ? Number((lastItem.price + step).toFixed(2))
            : Number((lastItem.price - step).toFixed(2));
        if (nextPrice <= 0) break;

        const avgQty = result.reduce((sum, r) => sum + r.quantity, 0) / (result.length || 1);
        const pseudoQty = Number((avgQty * (0.3 + Math.random() * 0.7)).toFixed(3)) || 0.05;

        const newItem = { price: nextPrice, quantity: pseudoQty };
        result.push(newItem);
        lastItem = newItem;
    }

    return result;
}

function renderOrderBook(data) {
    if (data) {
        state.lastOrderBookData = data;
    }
    const currentData = state.lastOrderBookData || data || {};

    const asksWrap = document.getElementById('orderbookAsks') || document.getElementById('orderBookAsks');
    const bidsWrap = document.getElementById('orderbookBids') || document.getElementById('orderBookBids');
    const midPriceEl = document.getElementById('obMidPrice') || document.getElementById('orderBookMid');

    if (!asksWrap || !bidsWrap) {
        return;
    }

    let asks = Array.from(currentData.asks || []);
    let bids = Array.from(currentData.bids || []);

    // Aggregate Kryvex user open limit orders
    const userSymbolOrders = (state.orders || []).filter(o => o.symbol === state.symbol && o.status === 'open' && o.type === 'limit');
    userSymbolOrders.forEach(order => {
        const p = Number(order.price || 0);
        const q = Number(order.quantity || 0);
        if (p > 0 && q > 0) {
            if (order.side === 'sell') {
                const existing = asks.find(row => Math.abs(row.price - p) < 0.0001);
                if (existing) existing.quantity += q;
                else asks.push({ price: p, quantity: q });
            } else {
                const existing = bids.find(row => Math.abs(row.price - p) < 0.0001);
                if (existing) existing.quantity += q;
                else bids.push({ price: p, quantity: q });
            }
        }
    });

    asks.sort((a, b) => a.price - b.price);
    bids.sort((a, b) => b.price - a.price);

    const precision = Number(state.obPrecision) || 0.1;
    if (precision > 0.1) {
        asks = aggregateDepthByPrecision(asks, precision, 'ask');
        bids = aggregateDepthByPrecision(bids, precision, 'bid');
    }

    const mode = state.obMode || 'both';
    let targetAsksCount = mode === 'asks' ? 22 : (mode === 'bids' ? 0 : 10);
    let targetBidsCount = mode === 'bids' ? 22 : (mode === 'asks' ? 0 : 10);

    const visibleAsks = padDepthRows(asks, targetAsksCount, 'ask', precision);
    const visibleBids = padDepthRows(bids, targetBidsCount, 'bid', precision);

    let askCumulative = 0;
    const asksWithTotals = visibleAsks.map(item => {
        askCumulative += item.quantity;
        return { ...item, total: askCumulative };
    });

    let bidCumulative = 0;
    const bidsWithTotals = visibleBids.map(item => {
        bidCumulative += item.quantity;
        return { ...item, total: bidCumulative };
    });

    const maxVolume = Math.max(
        ...asksWithTotals.map(i => i.total),
        ...bidsWithTotals.map(i => i.total),
        1
    );

    asksWrap.style.display = mode === 'bids' ? 'none' : 'flex';
    asksWrap.innerHTML = asksWithTotals.slice().reverse().map(row => {
        const depthWidth = Math.min(100, Math.round((row.total / maxVolume) * 100));
        return `
            <div class="ob-row ask" data-price="${row.price}">
                <div class="ob-depth-bar" style="width: ${depthWidth}%;"></div>
                <span class="ob-price">${formatUsdPrice(row.price)}</span>
                <span class="ob-size">${formatQuantity(row.quantity)}</span>
                <span class="ob-total">${formatQuantity(row.total)}</span>
            </div>
        `;
    }).join('');

    bidsWrap.style.display = mode === 'asks' ? 'none' : 'flex';
    bidsWrap.innerHTML = bidsWithTotals.map(row => {
        const depthWidth = Math.min(100, Math.round((row.total / maxVolume) * 100));
        return `
            <div class="ob-row bid" data-price="${row.price}">
                <div class="ob-depth-bar" style="width: ${depthWidth}%;"></div>
                <span class="ob-price">${formatUsdPrice(row.price)}</span>
                <span class="ob-size">${formatQuantity(row.quantity)}</span>
                <span class="ob-total">${formatQuantity(row.total)}</span>
            </div>
        `;
    }).join('');

    const livePrice = getLivePriceForSymbol(state.symbol) || asks[0]?.price || bids[0]?.price || 0;
    if (midPriceEl && livePrice) {
        midPriceEl.textContent = formatUsdPrice(livePrice);
        if (state.lastObPrice !== null) {
            midPriceEl.classList.remove('up', 'down');
            const arrowEl = document.getElementById('obMidArrow');
            if (arrowEl) arrowEl.classList.remove('up', 'down');

            if (livePrice > state.lastObPrice) {
                midPriceEl.classList.add('up');
                if (arrowEl) arrowEl.classList.add('up');
            } else if (livePrice < state.lastObPrice) {
                midPriceEl.classList.add('down');
                if (arrowEl) arrowEl.classList.add('down');
            }
        }
        state.lastObPrice = livePrice;
    }

    const markEl = document.getElementById('obMarkPrice');
    if (markEl && livePrice) {
        markEl.textContent = formatUsdPrice(livePrice);
    }

    document.querySelectorAll('.ob-row[data-price]').forEach(row => {
        row.addEventListener('click', () => {
            const price = row.dataset.price;
            if (price) {
                const limitInput = document.getElementById('orderPrice');
                if (limitInput) {
                    limitInput.value = price;
                }
                const limitBtn = document.querySelector('button[data-order-type="limit"]');
                if (limitBtn) limitBtn.click();
            }
        });
    });
}

function renderTrades(trades) {
    const box = document.getElementById('recentTradesBody') || document.getElementById('recentTradesList');

    if (!box) {
        return;
    }

    if (!trades.length) {
        box.innerHTML = '<div class="recent-trades-loading">No recent trades.</div>';
        return;
    }

    box.innerHTML = trades.slice(0, RECENT_TRADES_VISIBLE_ROWS).map((trade) => {
        const tradeClass = trade.is_buyer_maker ? 'sell' : 'buy';

        return `
            <div class="rt-row ${tradeClass}">
                <span class="rt-price">${formatUsdPrice(trade.price)}</span>
                <span class="rt-size">${formatQuantity(trade.quantity)}</span>
                <span class="rt-time">${formatTradeTime(trade.time)}</span>
            </div>
        `;
    }).join('');
}

async function loadProtectedFuturesData() {
    if (!getAccessToken()) {
        setText('orderWalletAvailable', 'Login required');
        setText('orderWalletLocked', '--');
        setText('orderLivePnl', '--');
        setText('orderEstimatedEquity', '--');
        setText('orderPanelAvailable', '--');
        setText('orderPanelMarkPrice', '--');
        setText('chartTotalBalance', '--');
        setText('orderLivePnlFooter', '--');
        setText('chartMarginBalance', '--');
        setText('chartAvailableBalance', '--');
        setText('chartMarginUsed', '--');
        setText('chartMarginRatio', '--');
        setText('orderMarginRatio', '0.00%');
        updateMarginRatioBar(0);
        renderAuthRequiredTables();
        return;
    }

    // 🔥 User TP/SL edit કરી રહ્યો હોય ત્યારે Positions ફરી load નહીં થાય
    if (state.isEditingPositionRisk) {
        await Promise.all([
            loadTradingAccounts(),
            loadWalletBalance(),
            loadOrders(),
            loadTradeHistory(),
        ]);

        updateLiveWalletMetrics();
        return;
    }

    await Promise.all([
        loadTradingAccounts(),
        loadWalletBalance(),
        loadPositions(),
        loadOrders(),
        loadTradeHistory(),
    ]);

    updateLiveWalletMetrics();
}
async function loadWalletBalance() {
    try {
        const result = await authFetch('/api/wallet');
        state.wallet = result.data?.wallet || {};
        state.wallet_meta = {
            minimum_reserve: result.data?.minimum_reserve || 0,
            has_open_trades: Boolean(result.data?.has_open_trades),
            max_withdrawable: result.data?.max_withdrawable || 0,
            withdrawal_enabled: result.data?.withdrawal_enabled !== false,
            withdrawal_charges: result.data?.withdrawal_charges || {},
        };
        state.depositNetworks = normalizeWalletNetworks(result.data?.deposit_networks || []);
        state.withdrawalAddresses = result.data?.withdrawal_addresses || {};
        renderFuturesWalletNetworks();

        updateLiveWalletMetrics();
        updateOrderEstimate();
    } catch (error) {
        setText('orderWalletAvailable', 'Unable');
        setText('orderWalletLocked', 'Unable');
        setText('orderPanelAvailable', 'Unable');
    }
}

async function loadPositions(force = false) {
    try {
        const result = await authFetch('/api/futures/positions');

        console.log('Positions API Result', result);

        state.positions = result.data?.positions || [];
        renderPositions(state.positions, force);
        updateLiveWalletMetrics();
    } catch (error) {
        renderTableMessage('positionsTableBody', 11, error.message);
    }
}

let currentFuturesOrdersPage = 1;
let currentFuturesTradesPage = 1;

async function loadOpenOrders() {
    try {
        const result = await authFetch('/api/futures/orders?status=open');
        state.orders = result.data?.orders || [];
        renderOpenOrders(state.orders);
    } catch (error) {
        renderTableMessage('openOrdersTableBody', 8, error.message);
    }
}

async function loadOrderHistory(page = 1) {
    currentFuturesOrdersPage = page;
    try {
        const result = await authFetch(`/api/futures/orders?paginate=true&page=${page}`);
        const orders = result.data?.orders || [];
        renderOrderHistory(orders, result.data?.pagination);
    } catch (error) {
        renderTableMessage('orderHistoryTableBody', 8, error.message);
        renderPagination('futuresOrdersPagination', null);
    }
}

async function loadOrders() {
    await Promise.all([
        loadOpenOrders(),
        loadOrderHistory(currentFuturesOrdersPage)
    ]);
}

//display open pos 
async function loadTradeHistory(page = 1) {
    currentFuturesTradesPage = page;
    try {
        const result = await authFetch(`/api/futures/trades?paginate=true&page=${page}`);
        renderTradeHistory(result.data?.trades || [], result.data?.pagination);
    } catch (error) {
        renderTableMessage('tradeHistoryTableBody', 8, error.message);
        renderPagination('futuresTradesPagination', null);
    }
}

let lastPositionsSignatureJson = '';

const COIN_LOGOS_MAP = {
    BTC: "https://assets.coingecko.com/coins/images/1/large/bitcoin.png",
    ETH: "https://assets.coingecko.com/coins/images/279/large/ethereum.png",
    USDT: "https://assets.coingecko.com/coins/images/325/large/Tether.png",
    BNB: "https://assets.coingecko.com/coins/images/825/large/bnb-icon2_2x.png",
    SOL: "https://assets.coingecko.com/coins/images/4128/large/solana.png",
    XRP: "https://assets.coingecko.com/coins/images/44/large/xrp-symbol-white-128.png",
    USDC: "https://assets.coingecko.com/coins/images/6319/large/USD_Coin_icon.png",
    ADA: "https://assets.coingecko.com/coins/images/975/large/cardano.png",
    DOGE: "https://assets.coingecko.com/coins/images/5/large/dogecoin.png",
    TRX: "https://assets.coingecko.com/coins/images/1094/large/tron-logo.png",
    AVAX: "https://assets.coingecko.com/coins/images/12559/large/Avalanche_Circle_RedWhite_Trans.png",
    DOT: "https://assets.coingecko.com/coins/images/12171/large/polkadot.png",
    MATIC: "https://assets.coingecko.com/coins/images/4713/large/polygon.png",
    POL: "https://assets.coingecko.com/coins/images/4713/large/polygon.png",
    LINK: "https://assets.coingecko.com/coins/images/877/large/chainlink-new-logo.png",
    LTC: "https://assets.coingecko.com/coins/images/2/large/litecoin.png",
    NEAR: "https://assets.coingecko.com/coins/images/10365/large/near.png",
    SUI: "https://assets.coingecko.com/coins/images/26375/large/sui-ocean-square.png",
    APT: "https://assets.coingecko.com/coins/images/26455/large/aptos_round.png",
    AR: "https://assets.coingecko.com/coins/images/4343/large/arweave.png",
    ARB: "https://assets.coingecko.com/coins/images/16547/large/arbitrum-shield.png",
    ATOM: "https://assets.coingecko.com/coins/images/1481/large/cosmos_hub.png",
    ENA: "https://assets.coingecko.com/coins/images/36530/large/ethena.png",
    BCH: "https://assets.coingecko.com/coins/images/780/large/bitcoin-cash-circle.png",
    OP: "https://assets.coingecko.com/coins/images/25244/large/Optimism.png",
    FIL: "https://assets.coingecko.com/coins/images/12817/large/filecoin.png",
    WIF: "https://assets.coingecko.com/coins/images/33566/large/dogwifhat.jpg",
    SHIB: "https://assets.coingecko.com/coins/images/11939/large/shiba.png",
    PEPE: "https://assets.coingecko.com/coins/images/29850/large/pepe-token.png",
    "1000PEPE": "https://assets.coingecko.com/coins/images/29850/large/pepe-token.png",
    TON: "https://assets.coingecko.com/coins/images/17980/large/ton_symbol.png",
    XLM: "https://assets.coingecko.com/coins/images/100/large/Stellar_symbol_black_RGB.png",
    UNI: "https://assets.coingecko.com/coins/images/12504/large/uniswap-uni.png",
    ETC: "https://assets.coingecko.com/coins/images/453/large/ethereum-classic-logo.png",
    XAU: "/images/coins/gold.svg",
    PAXG: "/images/coins/gold.svg",
    GOLD: "/images/coins/gold.svg",
    XAUT: "/images/coins/gold.svg"
};

const FUTURES_SYMBOL_LOT_SPECS = {
    BTCUSDT: { min: 0.0007, step: 0.0001, prec: 4, base: 'BTC' },
    ETHUSDT: { min: 0.0089, step: 0.0001, prec: 4, base: 'ETH' },
    SOLUSDT: { min: 0.05, step: 0.01, prec: 2, base: 'SOL' },
    XAUUSDT: { min: 0.002, step: 0.001, prec: 3, base: 'XAU' },
    GOLDUSDT: { min: 0.002, step: 0.001, prec: 3, base: 'GOLD' },
    BNBUSDT: { min: 0.01, step: 0.01, prec: 2, base: 'BNB' },
    XRPUSDT: { min: 3.5, step: 0.1, prec: 1, base: 'XRP' },
    ADAUSDT: { min: 23, step: 1, prec: 0, base: 'ADA' },
    AVAXUSDT: { min: 1, step: 0.1, prec: 1, base: 'AVAX' },
    LINKUSDT: { min: 0.41, step: 0.01, prec: 2, base: 'LINK' },
    DOTUSDT: { min: 4.9, step: 0.1, prec: 1, base: 'DOT' },
    POLUSDT: { min: 50, step: 1, prec: 0, base: 'POL' },
    MATICUSDT: { min: 50, step: 1, prec: 0, base: 'MATIC' },
    LTCUSDT: { min: 0.092, step: 0.001, prec: 3, base: 'LTC' },
    ATOMUSDT: { min: 1, step: 0.1, prec: 1, base: 'ATOM' },
    ATEMUSDT: { min: 1, step: 0.1, prec: 1, base: 'ATOM' },
    UNIUSDT: { min: 1, step: 0.1, prec: 1, base: 'UNI' },
    APTUSDT: { min: 7.5, step: 0.1, prec: 1, base: 'APT' },
    ARBUSDT: { min: 22.7, step: 0.1, prec: 1, base: 'ARB' },
    OPUSDT: { min: 44.6, step: 0.1, prec: 1, base: 'OP' },
    SUIUSDT: { min: 6.4, step: 0.1, prec: 1, base: 'SUI' },
    NEARUSDT: { min: 2, step: 0.1, prec: 1, base: 'NEAR' },
    DOGEUSDT: { min: 1, step: 1, prec: 0, base: 'DOGE' },
    GODDOGTUSDT: { min: 1, step: 1, prec: 0, base: 'DOGE' },
    WIFUSDT: { min: 26.1, step: 0.1, prec: 1, base: 'WIF' },
    ARUSDT: { min: 1, step: 0.1, prec: 1, base: 'AR' },
    ENAUSDT: { min: 1, step: 1, prec: 0, base: 'ENA' },
    PAXGUSDT: { min: 0.002, step: 0.001, prec: 3, base: 'PAXG' },
    BCHUSDT: { min: 0.01, step: 0.001, prec: 3, base: 'BCH' },
    FILUSDT: { min: 1, step: 0.1, prec: 1, base: 'FIL' },
    PEPEUSDT: { min: 100000, step: 1, prec: 0, base: 'PEPE' },
    "1000PEPEUSDT": { min: 1, step: 1, prec: 0, base: '1000PEPE' },
    SHIBUSDT: { min: 1000, step: 1, prec: 0, base: 'SHIB' },
    TONUSDT: { min: 1, step: 0.1, prec: 1, base: 'TON' },
    XLMUSDT: { min: 10, step: 1, prec: 0, base: 'XLM' },
    ETCUSDT: { min: 0.1, step: 0.01, prec: 2, base: 'ETC' },
    TRXUSDT: { min: 10, step: 1, prec: 0, base: 'TRX' },
};

function getFuturesSymbolLotSpec(rawSymbol) {
    const sym = String(rawSymbol || 'BTCUSDT').toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (FUTURES_SYMBOL_LOT_SPECS[sym]) {
        return FUTURES_SYMBOL_LOT_SPECS[sym];
    }
    const base = sym.replace(/(USDT|USD|INR|BUSD)$/i, '');
    const usdtSym = base + 'USDT';
    if (FUTURES_SYMBOL_LOT_SPECS[usdtSym]) {
        return FUTURES_SYMBOL_LOT_SPECS[usdtSym];
    }
    return { min: 0.001, step: 0.001, prec: 3, base: base || 'BTC' };
}

function updateOrderTicketSymbolSpec() {
    const spec = getFuturesSymbolLotSpec(state.symbol);
    const qtyInput = document.getElementById('orderQuantity');
    if (qtyInput) {
        qtyInput.placeholder = String(spec.min);
        qtyInput.min = String(spec.min);
        qtyInput.step = String(spec.step || 'any');
    }
    const baseLabels = document.querySelectorAll('.base-asset-label');
    baseLabels.forEach((el) => {
        el.textContent = spec.base || String(state.symbol || 'BTC').replace(/(USDT|USD|INR|BUSD)$/i, '');
    });
}

function getFuturesCoinLogo(symbol) {
    const raw = String(symbol || "BTC").toUpperCase().replace(/[-/_ ]/g, "");
    const base = raw.replace(/(USDT|USD|INR|BUSD)$/i, "") || "BTC";
    return COIN_LOGOS_MAP[base] || `https://assets.coincap.io/assets/icons/${base.toLowerCase()}@2x.png`;
}


function updateLivePositionPrices() {
    state.positions.forEach((position) => {
        const rows = document.querySelectorAll(`[data-position-row="${position.position_uid}"]`);
        if (!rows.length) return;

        const currentPrice = getLivePriceForSymbol(position.symbol);

        rows.forEach((row) => {
            const markCell = row.querySelector('.pos-mark-price');
            const pnlCell = row.querySelector('.pos-pnl');

            if (markCell) {
                if (row.classList.contains('futures-position-mobile-card')) {
                    markCell.textContent = formatMarketPrice(currentPrice);
                } else {
                    markCell.textContent = formatUsdPrice(currentPrice);
                }
            }

            if (pnlCell) {
                const pnl = calculatePositionPnl(position, currentPrice);
                const pnlClass = pnl >= 0 ? 'market-positive' : 'market-negative';
                const pnlPercent = calculatePositionPnlPercent(position, pnl);

                if (row.classList.contains('futures-position-mobile-card')) {
                    pnlCell.className = `pos-pnl ${pnlClass}`;
                    const cur = String(position.currency || 'USD').toUpperCase();
                    const isINR = cur === 'INR';
                    const curSuffix = (isINR || isActiveCentAccount()) ? '' : ' USDT';
                    pnlCell.textContent = `${pnl >= 0 ? '+' : ''}${formatCurrencyVal(pnl, position.currency)}${curSuffix}`;
                } else {
                    pnlCell.className = `pos-pnl ${pnlClass}`;
                    pnlCell.textContent = `${pnl >= 0 ? '+' : ''}${formatCurrencyVal(pnl, position.currency)} (${pnlPercent})`;
                }
            }
        });
    });
}

function setupPositionsTableEvents() {
    const panel = document.getElementById('positionsPanel');
    if (!panel) return;

    panel.addEventListener('click', (e) => {
        const closeButton = e.target.closest('[data-close-position]');
        if (closeButton) {
            closeOpenPosition(closeButton.dataset.closePosition);
        }

        const tpSlBtn = e.target.closest('[data-edit-tpsl]');
        if (tpSlBtn) {
            const uid = tpSlBtn.dataset.editTpsl;
            const position = state.positions.find(p => p.position_uid === uid);
            if (position) {
                const currentTp = position.take_profit || '';
                const currentSl = position.stop_loss || '';
                const tp = prompt("Enter Take Profit (TP) Price:", currentTp);
                if (tp === null) return;
                const sl = prompt("Enter Stop Loss (SL) Price:", currentSl);
                if (sl === null) return;
                updatePositionRisk(uid, tp, sl);
            }
        }
    });

    panel.addEventListener('focusin', (e) => {
        if (e.target.classList.contains('position-risk-input')) {
            state.isEditingPositionRisk = true;
            e.target.dataset.originalValue = e.target.value;
        }
    });

    panel.addEventListener('focusout', (e) => {
        if (e.target.classList.contains('position-risk-input')) {
            setTimeout(() => {
                if (document.activeElement && document.activeElement.classList.contains('position-risk-input')) {
                    return; // Still editing
                }
                state.isEditingPositionRisk = false;
            }, 300);
        }
    });

    panel.addEventListener('keydown', (e) => {
        if (e.target.classList.contains('position-risk-input') && e.key === 'Enter') {
            e.preventDefault();
            const input = e.target;
            const originalValue = input.dataset.originalValue || '';

            if (input.value === originalValue) {
                input.blur();
                return;
            }

            const uid = input.dataset.positionTp || input.dataset.positionSl;
            const tp = panel.querySelector(`[data-position-tp="${uid}"]`)?.value || '';
            const sl = panel.querySelector(`[data-position-sl="${uid}"]`)?.value || '';

            updatePositionRisk(uid, tp, sl);
            input.blur();
        }
    });
}

function getCurrencyTagHtml(currency) {
    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_base_currency') || 'USD');
    let tagCur = String(currency || activeCur).toUpperCase();
    if (tagCur === 'USDT' || tagCur === 'USD') tagCur = isActiveCentAccount() ? 'USC' : 'USD';
    const isInr = tagCur === 'INR';
    const tagClass = isInr ? 'tag-inr' : 'tag-usd';
    return `<span class="currency-tag ${tagClass}">${tagCur}</span>`;
}

function renderPositions(positions, force = false) {
    syncMarginModeWithActivePosition();

    const body = document.getElementById('positionsTableBody');
    const posCountEl = document.getElementById('posCount');
    if (posCountEl) {
        posCountEl.textContent = `(${positions.length})`;
    }
    const mobilePosBadge = document.getElementById('mobilePosBadge');
    if (mobilePosBadge) {
        mobilePosBadge.textContent = `(${positions.length})`;
    }

    if (!body) {
        return;
    }

    const currentSignatureJson = JSON.stringify((positions || []).map(p => ({
        uid: p.position_uid,
        qty: Number(p.quantity || 0),
        entry: Number(p.entry_price || 0),
        margin: Number(p.margin || 0),
        side: p.side,
        is_agg: Boolean(p.is_aggregated),
        fill_count: p.fill_count,
        tp: p.take_profit,
        sl: p.stop_loss,
    })));
    const isDomMissingRows = positions.length > 0 && !body.querySelector('tr[data-position-row]');

    if (force === true || currentSignatureJson !== lastPositionsSignatureJson || isDomMissingRows) {
        lastPositionsSignatureJson = currentSignatureJson;

        if (!positions.length) {
            toggleCloseAllPositionsButton(false);
            renderTableMessage('positionsTableBody', 10, 'No open positions yet.');
            const cardList = document.getElementById('positionsCardsList');
            if (cardList) {
                cardList.innerHTML = '<div class="futures-empty">No open positions yet.</div>';
            }
            return;
        }

        toggleCloseAllPositionsButton(true);

        body.innerHTML = positions.map((position) => {
            const currentPrice = getLivePriceForSymbol(position.symbol);
            const sideClass = position.side === 'buy' ? 'market-positive' : 'market-negative';
            const pnl = calculatePositionPnl(position, currentPrice);
            const pnlClass = pnl >= 0 ? 'market-positive' : 'market-negative';
            const pnlPercent = calculatePositionPnlPercent(position, pnl);
            const tpValue = position.take_profit ? Number(position.take_profit) : '';
            const slValue = position.stop_loss ? Number(position.stop_loss) : '';
            const marginMode = position.margin_mode ? (position.margin_mode.charAt(0).toUpperCase() + position.margin_mode.slice(1).toLowerCase()) : 'Isolated';
            const leverageText = `${marginMode} · ${escapeHtml(position.leverage || '10')}x`;

            const isAggregated = Boolean(position.is_aggregated || (position.fill_count && position.fill_count > 1));
            const avgBadge = isAggregated
                ? `<span class="pos-avg-badge" title="Average entry price across ${position.fill_count || 2} orders" style="font-size:10px; color:#38bdf8; background:rgba(56,189,248,0.14); padding:1px 5px; border-radius:3px; margin-left:4px; font-weight:700;">Avg</span>`
                : '';

            return `
                <tr data-position-row="${escapeHtml(position.position_uid)}">
                    <td>
                        <div class="pos-symbol-cell">
                            <div class="pos-symbol-title">
                                <strong>${escapeHtml(formatDisplaySymbol(position.symbol))}</strong> ${getCurrencyTagHtml(position.currency)}
                            </div>
                            <small class="pos-symbol-leverage">${leverageText}</small>
                        </div>
                    </td>
                    <td class="${sideClass}">${escapeHtml(position.side?.toUpperCase() || '--')}</td>
                    <td>${formatUsdPrice(position.entry_price)} ${avgBadge}</td>
                    <td class="pos-mark-price">${formatUsdPrice(currentPrice)}</td>
                    <td>${formatQuantity(position.quantity)}</td>
                    <td>${formatCurrencyVal(position.margin, position.currency)}</td>
                    <td class="pos-pnl ${pnlClass}">${pnl >= 0 ? '+' : ''}${formatCurrencyVal(pnl, position.currency)} (${pnlPercent})</td>
                    <td>
                        <input type="number" class="position-risk-input" data-position-tp="${escapeHtml(position.position_uid)}" value="${tpValue}" placeholder="TP" step="0.0001">
                    </td>
                    <td>
                        <input type="number" class="position-risk-input" data-position-sl="${escapeHtml(position.position_uid)}" value="${slValue}" placeholder="SL" step="0.0001">
                    </td>
                    <td>
                        <div class="position-action-stack">
                            <button
                                type="button"
                                class="futures-close-btn"
                                data-close-position="${escapeHtml(position.position_uid)}">
                                Close
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        const cardList = document.getElementById('positionsCardsList');
        if (cardList) {
            cardList.innerHTML = positions.map((position) => {
                const currentPrice = getLivePriceForSymbol(position.symbol);
                const isBuy = String(position.side || 'buy').toLowerCase() === 'buy';
                const sideText = isBuy ? 'Buy' : 'Sell';
                const sideClass = isBuy ? 'market-positive' : 'market-negative';
                const pnl = calculatePositionPnl(position, currentPrice);
                const pnlClass = pnl >= 0 ? 'market-positive' : 'market-negative';
                const marginMode = position.margin_mode ? (position.margin_mode.charAt(0).toUpperCase() + position.margin_mode.slice(1).toLowerCase()) : 'Isolated';
                const coinLogo = getFuturesCoinLogo(position.symbol);
                const dispSymbol = formatDisplaySymbol(position.symbol);
                const cur = String(position.currency || (window.getActiveCurrency ? window.getActiveCurrency() : 'USD')).toUpperCase();
                const isINR = cur === 'INR';
                const tagLabel = isINR ? 'INR' : (isActiveCentAccount() ? 'USC' : (cur === 'USDT' ? 'USD' : cur));
                const curSuffix = (isINR || isActiveCentAccount()) ? '' : ' USDT';
                const pnlText = `${pnl >= 0 ? '+' : ''}${formatCurrencyVal(pnl, position.currency)}${curSuffix}`;
                const entryPriceText = formatMarketPrice(position.entry_price);
                const markPriceText = formatMarketPrice(currentPrice);
                const isAggregated = Boolean(position.is_aggregated || (position.fill_count && position.fill_count > 1));
                const mobileAvgBadge = isAggregated
                    ? `<span class="pos-avg-badge" title="Average entry price across ${position.fill_count || 2} orders" style="font-size:10px; color:#38bdf8; background:rgba(56,189,248,0.14); padding:1px 4px; border-radius:3px; margin-left:3px; font-weight:700;">Avg</span>`
                    : '';

                return `
                    <div class="futures-position-mobile-card" data-position-row="${escapeHtml(position.position_uid)}">
                        <div class="pos-card-left-group">
                            <img src="${coinLogo}" alt="${escapeHtml(dispSymbol)}" class="pos-card-coin-logo" onerror="this.onerror=null;this.src='https://assets.coingecko.com/coins/images/1/large/bitcoin.png';" />
                            <div class="pos-card-info">
                                <div class="pos-card-title-row">
                                    <span class="pos-card-sym-name">${escapeHtml(dispSymbol)}</span>
                                    <span class="pos-card-cur-badge">${escapeHtml(tagLabel)}</span>
                                    <span style="font-size:10px; color:#f6c93f; background:rgba(246,201,63,0.12); padding:1px 5px; border-radius:3px; font-weight:700;">${marginMode} ${position.leverage || '10'}x</span>
                                </div>
                                <div class="pos-card-sub-row">
                                    <span class="${sideClass} pos-card-side-label">${sideText}</span>
                                    <span class="pos-card-qty-label">${formatQuantity(position.quantity)}</span>
                                    <span class="pos-card-entry-label">at ${entryPriceText} ${mobileAvgBadge}</span>
                                </div>
                            </div>
                        </div>
                        <div class="pos-card-right-group">
                            <div class="pos-card-top-action-row">
                                <span class="pos-pnl ${pnlClass} pos-card-pnl-val">${pnlText}</span>
                                <button type="button" class="pos-card-close-x" data-close-position="${escapeHtml(position.position_uid)}" title="Close Position">✕</button>
                            </div>
                            <div class="pos-mark-price pos-card-ltp-val">
                                ${markPriceText}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }
    }

    throttledUpdateLivePositionPrices();
}

function renderOpenOrders(orders) {
    const body = document.getElementById('openOrdersTableBody');
    const cardList = document.getElementById('openOrdersCardsList');

    const ordersCountEl = document.getElementById('ordersCount');
    if (ordersCountEl) {
        ordersCountEl.textContent = `(${orders.length})`;
    }
    const mobileOrdersBadge = document.getElementById('mobileOrdersBadge');
    if (mobileOrdersBadge) {
        mobileOrdersBadge.textContent = `(${orders.length})`;
    }

    if (!body) {
        return;
    }

    if (!orders.length) {
        renderTableMessage('openOrdersTableBody', 8, 'No open orders.');
        if (cardList) {
            cardList.innerHTML = '<div class="futures-empty">No open orders.</div>';
        }
        return;
    }

    body.innerHTML = orders.map((order) => {
        const sideClass = order.side === 'buy' ? 'market-positive' : 'market-negative';

        return `
            <tr>
                <td>${escapeHtml(formatDisplaySymbol(order.symbol))} ${getCurrencyTagHtml(order.currency)}</td>
                <td class="${sideClass}">${escapeHtml(order.side?.toUpperCase() || '--')}</td>
                <td>${escapeHtml(order.order_type || '--')}</td>
                <td>${formatOptionalPrice(order.price || order.execution_price)}</td>
                <td>${formatUsdt(order.margin)}</td>
                <td>${escapeHtml(order.leverage || '--')}x</td>
                <td><span class="futures-status open">open</span></td>
                <td>
                    <button type="button" class="futures-cancel-btn" data-cancel-order="${escapeHtml(order.order_uid)}">
                        Cancel
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    if (cardList) {
        cardList.innerHTML = orders.map((order) => {
            const isBuy = String(order.side || 'buy').toLowerCase() === 'buy';
            const sideText = isBuy ? 'Buy' : 'Sell';
            const sideClass = isBuy ? 'market-positive' : 'market-negative';
            const coinLogo = getFuturesCoinLogo(order.symbol);
            const dispSymbol = formatDisplaySymbol(order.symbol);
            const cur = String(order.currency || (window.getActiveCurrency ? window.getActiveCurrency() : 'USD')).toUpperCase();
            const isINR = cur === 'INR';
            const tagLabel = isINR ? 'INR' : (isActiveCentAccount() ? 'USC' : (cur === 'USDT' ? 'USD' : cur));
            const orderType = String(order.order_type || 'Limit').toUpperCase();
            const priceVal = Number(order.price || order.execution_price || 0);
            const priceText = priceVal ? formatMarketPrice(priceVal) : 'Market';
            const qtyText = formatQuantity(order.estimated_quantity || order.quantity);
            const marginText = formatUsdt(order.margin);

            return `
                <div class="futures-position-mobile-card">
                    <div class="pos-card-left-group">
                        <img src="${coinLogo}" alt="${escapeHtml(dispSymbol)}" class="pos-card-coin-logo" onerror="this.onerror=null;this.src='https://assets.coingecko.com/coins/images/1/large/bitcoin.png';" />
                        <div class="pos-card-info">
                            <div class="pos-card-title-row">
                                <span class="pos-card-sym-name">${escapeHtml(dispSymbol)}</span>
                                <span class="pos-card-cur-badge">${escapeHtml(tagLabel)}</span>
                                <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-white/5 text-purple-300 border border-purple-500/20 uppercase leading-none">${escapeHtml(orderType)}</span>
                            </div>
                            <div class="pos-card-sub-row whitespace-nowrap">
                                <span class="${sideClass} pos-card-side-label">${sideText}</span>
                                <span class="pos-card-qty-label">${qtyText}</span>
                                <span class="pos-card-entry-label">at ${priceText}</span>
                            </div>
                        </div>
                    </div>
                    <div class="pos-card-right-group">
                        <div class="pos-card-top-action-row whitespace-nowrap">
                            <span class="text-slate-300 font-bold font-mono text-xs">${marginText}</span>
                            <button type="button" class="pos-card-close-x" data-cancel-order="${escapeHtml(order.order_uid)}" title="Cancel Order">✕</button>
                        </div>
                        <div class="pos-card-ltp-val text-amber-400 text-xs font-black uppercase tracking-wider">
                            OPEN
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    document.querySelectorAll('[data-cancel-order]').forEach((button) => {
        button.addEventListener('click', () => {
            cancelOpenOrder(button.dataset.cancelOrder);
        });
    });
}

function renderOrderHistory(orders, pagination = null) {
    const body = document.getElementById('orderHistoryTableBody');
    const cardList = document.getElementById('orderHistoryCardsList');

    if (!body) {
        return;
    }

    if (!orders.length) {
        renderTableMessage('orderHistoryTableBody', 8, 'No order history yet.');
        if (cardList) {
            cardList.innerHTML = '<div class="futures-empty">No order history yet.</div>';
        }
        renderPagination('futuresOrdersPagination', null);
        return;
    }

    body.innerHTML = orders.map((order) => {
        const sideClass = order.side === 'buy' ? 'market-positive' : 'market-negative';

        return `
            <tr>
                <td>${escapeHtml(formatDisplaySymbol(order.symbol))} ${getCurrencyTagHtml(order.currency)}</td>
                <td class="${sideClass}">${escapeHtml(order.side?.toUpperCase() || '--')}</td>
                <td>${escapeHtml(order.order_type || '--')}</td>
                <td>${formatUsdPrice(order.execution_price)}</td>
                <td>${formatUsdt(order.margin)}</td>
                <td>${formatUsdt(order.fee_charged || order.estimated_fee || 0)}</td>
                <td><span class="futures-status ${escapeHtml(order.status || '')}">${escapeHtml(order.status || '--')}</span></td>
                <td>${formatDate(order.created_at)}</td>
            </tr>
        `;
    }).join('');

    if (cardList) {
        cardList.innerHTML = orders.map((order) => {
            const isBuy = String(order.side || 'buy').toLowerCase() === 'buy';
            const sideText = isBuy ? 'Buy' : 'Sell';
            const sideClass = isBuy ? 'market-positive' : 'market-negative';
            const coinLogo = getFuturesCoinLogo(order.symbol);
            const dispSymbol = formatDisplaySymbol(order.symbol);
            const cur = String(order.currency || (window.getActiveCurrency ? window.getActiveCurrency() : 'USD')).toUpperCase();
            const isINR = cur === 'INR';
            const tagLabel = isINR ? 'INR' : (isActiveCentAccount() ? 'USC' : (cur === 'USDT' ? 'USD' : cur));
            const orderType = String(order.order_type || 'Limit').toUpperCase();
            const rawStatus = String(order.status || 'filled').toUpperCase();
            const statusClass = (rawStatus === 'FILLED' || rawStatus === 'COMPLETED' || rawStatus === 'EXECUTED')
                ? 'bg-emerald-950/60 text-emerald-400 border-emerald-800/40'
                : (rawStatus === 'CANCELLED' || rawStatus === 'CANCELED' || rawStatus === 'REJECTED')
                    ? 'bg-slate-900 text-slate-400 border-slate-700'
                    : 'bg-amber-950/60 text-amber-400 border-amber-800/40';
            const priceText = formatOptionalPrice(order.execution_price || order.price);
            const qtyText = formatQuantity(order.estimated_quantity || order.quantity);
            const feeText = formatUsdt(order.fee_charged || order.estimated_fee || 0);
            const marginText = formatUsdt(order.margin);
            const timeText = formatDate(order.created_at);

            return `
                <div class="futures-position-mobile-card">
                    <div class="pos-card-left-group">
                        <img src="${coinLogo}" alt="${escapeHtml(dispSymbol)}" class="pos-card-coin-logo" onerror="this.onerror=null;this.src='https://assets.coingecko.com/coins/images/1/large/bitcoin.png';" />
                        <div class="pos-card-info">
                            <div class="pos-card-title-row">
                                <span class="pos-card-sym-name">${escapeHtml(dispSymbol)}</span>
                                <span class="pos-card-cur-badge">${escapeHtml(tagLabel)}</span>
                                <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-white/5 text-purple-300 border border-purple-500/20 uppercase leading-none">${escapeHtml(orderType)}</span>
                            </div>
                            <div class="pos-card-sub-row whitespace-nowrap">
                                <span class="${sideClass} pos-card-side-label">${sideText}</span>
                                <span class="pos-card-qty-label">${qtyText}</span>
                                <span class="pos-card-entry-label">at ${priceText}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">${escapeHtml(timeText)}</div>
                        </div>
                    </div>
                    <div class="pos-card-right-group">
                        <div class="pos-card-top-action-row whitespace-nowrap">
                            <span class="text-slate-400 text-[11px] font-mono">Fee: ${feeText}</span>
                            <span class="px-1.5 py-0.5 rounded ${statusClass} text-[10px] font-extrabold uppercase border leading-none">${escapeHtml(rawStatus)}</span>
                        </div>
                        <div class="text-[11px] font-mono text-slate-300 font-bold mt-1">
                            Margin: ${marginText}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    renderPagination('futuresOrdersPagination', pagination, (p) => loadOrderHistory(p));
}

function renderTradeHistory(trades, pagination = null) {
    const body = document.getElementById('tradeHistoryTableBody');
    const cardList = document.getElementById('tradeHistoryCardsList');

    if (!body) {
        return;
    }

    if (!trades.length) {
        renderTableMessage('tradeHistoryTableBody', 8, 'No trade history yet.');
        if (cardList) {
            cardList.innerHTML = '<div class="futures-empty">No trade history yet.</div>';
        }
        renderPagination('futuresTradesPagination', null);
        return;
    }

    body.innerHTML = trades.map((trade) => {
        const sideClass = trade.side === 'buy' ? 'market-positive' : 'market-negative';

        return `
            <tr>
                <td>${escapeHtml(formatDisplaySymbol(trade.symbol))} ${getCurrencyTagHtml(trade.currency)}</td>
                <td class="${sideClass}">${escapeHtml(trade.side?.toUpperCase() || '--')}</td>
                <td>${formatUsdPrice(trade.price)}</td>
                <td>${formatQuantity(trade.quantity)}</td>
                <td>${formatUsdt(trade.notional_value)}</td>
                <td>${formatUsdt(trade.fee)}</td>
                <td>${formatDate(trade.created_at)}</td>
            </tr>
        `;
    }).join('');

    if (cardList) {
        cardList.innerHTML = trades.map((trade) => {
            const isBuy = String(trade.side || 'buy').toLowerCase() === 'buy';
            const sideText = isBuy ? 'Buy' : 'Sell';
            const sideClass = isBuy ? 'market-positive' : 'market-negative';
            const coinLogo = getFuturesCoinLogo(trade.symbol);
            const dispSymbol = formatDisplaySymbol(trade.symbol);
            const cur = String(trade.currency || (window.getActiveCurrency ? window.getActiveCurrency() : 'USD')).toUpperCase();
            const isINR = cur === 'INR';
            const tagLabel = isINR ? 'INR' : (isActiveCentAccount() ? 'USC' : (cur === 'USDT' ? 'USD' : cur));
            const priceText = formatUsdPrice(trade.price);
            const qtyText = formatQuantity(trade.quantity);
            const feeText = formatUsdt(trade.fee);
            const valText = formatUsdt(trade.notional_value);
            const timeText = formatDate(trade.created_at);
            const pnlVal = Number(trade.realized_pnl || 0);

            return `
                <div class="futures-position-mobile-card">
                    <div class="pos-card-left-group">
                        <img src="${coinLogo}" alt="${escapeHtml(dispSymbol)}" class="pos-card-coin-logo" onerror="this.onerror=null;this.src='https://assets.coingecko.com/coins/images/1/large/bitcoin.png';" />
                        <div class="pos-card-info">
                            <div class="pos-card-title-row">
                                <span class="pos-card-sym-name">${escapeHtml(dispSymbol)}</span>
                                <span class="pos-card-cur-badge">${escapeHtml(tagLabel)}</span>
                                <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-emerald-950/60 text-emerald-400 border border-emerald-800/40 uppercase leading-none">TRADE</span>
                            </div>
                            <div class="pos-card-sub-row whitespace-nowrap">
                                <span class="${sideClass} pos-card-side-label">${sideText}</span>
                                <span class="pos-card-qty-label">${qtyText}</span>
                                <span class="pos-card-entry-label">at ${priceText}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">${escapeHtml(timeText)}</div>
                        </div>
                    </div>
                    <div class="pos-card-right-group">
                        <div class="pos-card-top-action-row whitespace-nowrap">
                            <span class="text-slate-400 text-[11px] font-mono">Fee: ${feeText}</span>
                            <span class="${pnlVal >= 0 ? 'text-emerald-400' : 'text-rose-400'} font-mono font-bold text-xs">
                                ${pnlVal !== 0 ? (pnlVal > 0 ? '+' : '') + formatUsdt(pnlVal) : ''}
                            </span>
                        </div>
                        <div class="text-[11px] font-mono text-slate-300 font-bold mt-1">
                            Total: ${valText}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    renderPagination('futuresTradesPagination', pagination, (p) => loadTradeHistory(p));
}

async function cancelOpenOrder(orderUid) {
    if (!orderUid) {
        return;
    }

    const confirmed = await askFuturesConfirm({
        title: 'Cancel open order?',
        message: 'Locked margin will be released back to your available balance.',
        confirmText: 'Cancel Order',
    });

    if (!confirmed) {
        return;
    }

    try {
        await authFetch(`/api/futures/orders/${orderUid}/cancel`, {
            method: 'POST',
            body: JSON.stringify({}),
        });

        showFuturesToast('Open order cancelled and margin released.', 'success');
        await loadProtectedFuturesData();
    } catch (error) {
        showFuturesToast(error.message || 'Unable to cancel order.', 'error');
    }
}

async function closeOpenPosition(positionUid) {

    if (!positionUid) {
        return;
    }

    const button = document.querySelector(
        `[data-close-position="${positionUid}"]`
    );

    if (button?.disabled) {
        return;
    }

    const position = state.positions.find(
        (item) => item.position_uid === positionUid
    );

    let exitPrice = getLivePriceForSymbol(
        position?.symbol || state.symbol,
        position
    );

    if (!exitPrice || exitPrice <= 0) {
        exitPrice = Number(position?.entry_price || position?.mark_price || 0);
    }

    if (!exitPrice || exitPrice <= 0) {
        showFuturesToast(
            'Current market price is not available.',
            'error'
        );
        return;
    }

    const confirmed = await askFuturesConfirm({
        title: 'Close this trade?',
        message: `This position will be closed at ${formatUsdPrice(exitPrice)}.`,
        confirmText: 'Close Trade',
        preferenceKey: 'kryvex:futures:confirm:close-position:skip',
        skipLabel:
            'Do not ask again when I close a single futures trade.',
    });

    if (!confirmed) {
        return;
    }

    if (button) {
        button.disabled = true;
        button.textContent = 'Closing...';
    }

    try {

        await authFetch(
            `/api/futures/positions/${positionUid}/close`,
            {
                method: 'POST',
                body: JSON.stringify({
                    exit_price: exitPrice,
                }),
            }
        );

        playTradeCloseSound();

        // Remove instantly from UI
        state.positions = state.positions.filter(
            (p) => p.position_uid !== positionUid
        );

        renderPositions(state.positions, true);

        showFuturesToast(
            'Trade closed successfully.',
            'success'
        );

        // Background refresh
        await loadPositions(true);
        loadWalletBalance();
        loadOrders();
        loadTradeHistory();
        updateLiveWalletMetrics();

    } catch (error) {

        if (button) {
            button.disabled = false;
            button.textContent = 'Close';
        }

        showFuturesToast(
            error.message || 'Unable to close position.',
            'error'
        );

    }

}

function renderAuthRequiredTables() {
    toggleCloseAllPositionsButton(false);
    renderTableMessage('positionsTableBody', 11, 'Login to view positions.');
    renderTableMessage('openOrdersTableBody', 8, 'Login to view open orders.');
    renderTableMessage('orderHistoryTableBody', 8, 'Login to view order history.');
    renderTableMessage('tradeHistoryTableBody', 8, 'Login to view trade history.');
}

function renderTableMessage(id, colspan, message) {
    const body = document.getElementById(id);

    if (!body) {
        return;
    }

    body.innerHTML = `
        <tr>
            <td colspan="${colspan}" class="futures-empty">${escapeHtml(message)}</td>
        </tr>
    `;
}


function setupCloseAllPositionsButton() {
    const button = document.getElementById('closeAllPositionsButton');
    const mobileButton = document.getElementById('mobileCloseAllBtn');

    if (button) {
        button.addEventListener('click', closeAllOpenPositions);
    }
    if (mobileButton) {
        mobileButton.addEventListener('click', closeAllOpenPositions);
    }
    toggleCloseAllPositionsButton(false);
}

function toggleCloseAllPositionsButton(visible) {
    const button = document.getElementById('closeAllPositionsButton');
    const mobileButton = document.getElementById('mobileCloseAllBtn');

    if (button) {
        button.hidden = !visible;
    }
    if (mobileButton) {
        mobileButton.style.display = visible ? 'block' : 'none';
    }
}

async function closeAllOpenPositions() {
    if (!getAccessToken()) {
        showFuturesToast('Please login first.', 'error');
        return;
    }

    const openPositions = state.positions || [];

    if (!openPositions.length) {
        showFuturesToast('No open trades to close.', 'info');
        return;
    }

    const confirmed = await askFuturesConfirm({
        title: 'Close all open trades?',
        message: `${openPositions.length} open trade(s) will be closed at current market prices.`,
        confirmText: 'Close All',
        preferenceKey: 'kryvex:futures:confirm:close-all:skip',
        skipLabel: 'Do not ask again when I close all futures trades.',
    });

    if (!confirmed) {
        return;
    }

    const button = document.getElementById('closeAllPositionsButton');
    const mobileButton = document.getElementById('mobileCloseAllBtn');
    button?.setAttribute('disabled', 'disabled');
    mobileButton?.setAttribute('disabled', 'disabled');

    try {
        for (const position of openPositions) {
            let exitPrice = getLivePriceForSymbol(position.symbol, position);
            if (!exitPrice || exitPrice <= 0) {
                exitPrice = Number(position.entry_price || position.mark_price || 0);
            }

            await authFetch(`/api/futures/positions/${position.position_uid}/close`, {
                method: 'POST',
                body: JSON.stringify({
                    exit_price: exitPrice,
                }),
            });
        }

        // 🔥 Instantly remove all positions from UI
        state.positions = [];

        renderPositions([], true);

        playTradeCloseSound();
        showFuturesToast('All open trades closed successfully.', 'success');

        // Background refresh
        await Promise.all([
            loadWalletBalance(),
            loadPositions(true),
            loadOrders(),
            loadTradeHistory(),
        ]);
        updateLiveWalletMetrics();

    } catch (error) {

        showFuturesToast(
            error.message || 'Unable to close all trades.',
            'error'
        );

    } finally {

        button?.removeAttribute('disabled');
        mobileButton?.removeAttribute('disabled');

    }
}

async function updatePositionRisk(positionUid, takeProfit, stopLoss) {
    if (!positionUid) {
        return;
    }

    if (!getAccessToken()) {
        showFuturesToast('Please login first.', 'error');
        return;
    }

    try {

        state.isEditingPositionRisk = true;

        await authFetch(`/api/futures/positions/${positionUid}/tp-sl`, {
            method: 'POST',
            body: JSON.stringify({
                take_profit: takeProfit ? Number(takeProfit) : null,
                stop_loss: stopLoss ? Number(stopLoss) : null,
            }),
        });

        showFuturesToast('TP / SL updated successfully.', 'success');

        await loadPositions();

    } catch (error) {

        showFuturesToast(error.message || 'Unable to update TP / SL.', 'error');

    } finally {

        state.isEditingPositionRisk = false;

    }
}

function formatInr(amount) {
    const n = Number(amount || 0);
    return '\u20b9' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadFuturesInrConfig() {
    const token = getAccessToken();
    if (!token) return;
    try {
        const [bcRes, rateRes] = await Promise.all([
            fetch('/api/auth/base-currency', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }),
            fetch('/api/wallet/inr-rate', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
        ]);
        const bcData = await bcRes.json();
        const rateData = await rateRes.json();
        futuresInrState.baseCurrency = bcData?.data?.base_currency || null;
        futuresInrState.usdtInrRate = Number(rateData?.data?.usdt_inr_rate || 95.0);
        futuresInrState.usdtInrBuyRate = Number(rateData?.data?.usdt_inr_buy_rate || futuresInrState.usdtInrRate);
        futuresInrState.usdtInrSellRate = Number(rateData?.data?.usdt_inr_sell_rate || futuresInrState.usdtInrRate);
        futuresInrState.adminBankDetails = rateData?.data?.bank_details || null;

        try {
            localStorage.setItem('kryvex_inr_state_cache', JSON.stringify({
                baseCurrency: futuresInrState.baseCurrency,
                usdtInrRate: futuresInrState.usdtInrRate,
                usdtInrBuyRate: futuresInrState.usdtInrBuyRate,
                usdtInrSellRate: futuresInrState.usdtInrSellRate,
                adminBankDetails: futuresInrState.adminBankDetails
            }));
        } catch (e) {}

        renderFuturesInrBankDetails();
        updateLiveWalletMetrics();
    } catch (e) { }
}

function openFuturesAddFundsModal() {
    if (typeof window.openAddFundsModal === 'function') {
        window.openAddFundsModal();
        return;
    }
    const modal = document.getElementById('globalAddFundsModal') || document.getElementById('futuresAddFundsModal');
    if (!modal) return;
    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';
}
window.openFuturesAddFundsModal = openFuturesAddFundsModal;

function closeFuturesAddFundsModal() {
    if (typeof window.closeAddFundsModal === 'function') {
        window.closeAddFundsModal();
        return;
    }
    const modal = document.getElementById('globalAddFundsModal') || document.getElementById('futuresAddFundsModal');
    if (!modal) return;
    modal.hidden = true;
    modal.style.display = 'none';
}
window.closeFuturesAddFundsModal = closeFuturesAddFundsModal;

function setupFuturesAddFundsModal() {
    if (typeof window.setupGlobalAddFundsModal === 'function') {
        window.setupGlobalAddFundsModal();
        return;
    }
    const modal = document.getElementById('globalAddFundsModal') || document.getElementById('futuresAddFundsModal');
    const closeBtn = document.getElementById('closeGlobalAddFundsModal') || document.getElementById('closeFuturesAddFundsModal');
    const moneyBtn = document.getElementById('btnFundMoneyDeposit');
    const cryptoBtn = document.getElementById('btnFundCryptoDeposit');
    const buyCryptoBtn = document.getElementById('btnFundBuyCrypto');

    if (!modal) return;

    if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeFuturesAddFundsModal();
        });
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeFuturesAddFundsModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.hidden && modal.style.display !== 'none') {
            closeFuturesAddFundsModal();
        }
    });

    // 1. Money Deposit -> Open INR Bank / Money deposit directly on wallet page (NO POPUP)
    if (moneyBtn) {
        moneyBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeFuturesAddFundsModal();
            window.location.href = '/wallet?tab=deposit&mode=money';
        });
    }

    // 2. Crypto Deposit -> Open Crypto deposit directly on wallet page (NO POPUP)
    if (cryptoBtn) {
        cryptoBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeFuturesAddFundsModal();
            window.location.href = '/wallet?tab=deposit&mode=crypto';
        });
    }

    // 3. Buy Crypto -> Direct to markets
    if (buyCryptoBtn) {
        buyCryptoBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeFuturesAddFundsModal();
            window.location.href = '/markets?tab=crypto';
        });
    }
}

function openFuturesDemoDepositModal() {
    const modal = document.getElementById('futuresDemoDepositModal');
    if (!modal) return;
    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';
}
window.openFuturesDemoDepositModal = openFuturesDemoDepositModal;

function closeFuturesDemoDepositModal() {
    const modal = document.getElementById('futuresDemoDepositModal');
    if (!modal) return;
    modal.hidden = true;
    modal.style.display = 'none';
}
window.closeFuturesDemoDepositModal = closeFuturesDemoDepositModal;

function openFuturesSetDemoBalanceModal() {
    const modal = document.getElementById('futuresSetDemoBalanceModal');
    if (!modal) return;
    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';
}
window.openFuturesSetDemoBalanceModal = openFuturesSetDemoBalanceModal;

function closeFuturesSetDemoBalanceModal() {
    const modal = document.getElementById('futuresSetDemoBalanceModal');
    if (!modal) return;
    modal.hidden = true;
    modal.style.display = 'none';
}
window.closeFuturesSetDemoBalanceModal = closeFuturesSetDemoBalanceModal;

function setupFuturesDemoDepositModals() {
    const demoModal = document.getElementById('futuresDemoDepositModal');
    const closeDemoBtn = document.getElementById('closeFuturesDemoDepositModal');
    const optSetDemo = document.getElementById('btnOptSetDemoBalance');
    const optRealFunds = document.getElementById('btnOptDepositRealFunds');

    const setBalanceModal = document.getElementById('futuresSetDemoBalanceModal');
    const closeSetBalanceBtn = document.getElementById('closeFuturesSetDemoBalanceModal');
    const backSetBalanceBtn = document.getElementById('backFuturesSetDemoModal');
    const presetChips = document.querySelectorAll('.demo-preset-chip');
    const demoBalInput = document.getElementById('futuresDemoBalanceInput');
    const confirmSetBtn = document.getElementById('btnConfirmSetDemoBalance');

    // Close / Backdrop for Demo Deposit Options modal
    if (closeDemoBtn) {
        closeDemoBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeFuturesDemoDepositModal();
        });
    }
    if (demoModal) {
        demoModal.addEventListener('click', (e) => {
            if (e.target === demoModal) closeFuturesDemoDepositModal();
        });
    }

    // Option 1: Set demo balance -> Open set demo balance modal
    if (optSetDemo) {
        optSetDemo.addEventListener('click', (e) => {
            e.preventDefault();
            closeFuturesDemoDepositModal();
            openFuturesSetDemoBalanceModal();
        });
    }

    // Option 2: Deposit real funds -> Open "How would you like to add funds?" modal (Image 3)
    if (optRealFunds) {
        optRealFunds.addEventListener('click', (e) => {
            e.preventDefault();
            closeFuturesDemoDepositModal();
            openFuturesAddFundsModal();
        });
    }

    // Set Demo Balance Modal events
    if (closeSetBalanceBtn) {
        closeSetBalanceBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeFuturesSetDemoBalanceModal();
        });
    }
    if (backSetBalanceBtn) {
        backSetBalanceBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeFuturesSetDemoBalanceModal();
            openFuturesDemoDepositModal();
        });
    }
    if (setBalanceModal) {
        setBalanceModal.addEventListener('click', (e) => {
            if (e.target === setBalanceModal) closeFuturesSetDemoBalanceModal();
        });
    }

    // Preset chips
    presetChips.forEach((chip) => {
        chip.addEventListener('click', (e) => {
            e.preventDefault();
            presetChips.forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            const amt = chip.getAttribute('data-amt');
            if (demoBalInput) {
                demoBalInput.value = amt;
                demoBalInput.focus();
            }
        });
    });

    if (demoBalInput) {
        demoBalInput.addEventListener('input', () => {
            const val = demoBalInput.value;
            presetChips.forEach(c => {
                if (c.getAttribute('data-amt') === val) {
                    c.classList.add('active');
                } else {
                    c.classList.remove('active');
                }
            });
        });
    }

    // Submit Set Balance
    if (confirmSetBtn) {
        confirmSetBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            const amount = Number(demoBalInput?.value || 0);
            if (!amount || amount <= 0) {
                showFuturesToast('Please enter a valid balance amount.', 'error');
                return;
            }

            confirmSetBtn.setAttribute('disabled', 'disabled');
            const origText = confirmSetBtn.textContent;
            confirmSetBtn.textContent = 'Adding Balance...';

            try {
                const res = await authFetch('/api/wallet/demo-credit', {
                    method: 'POST',
                    body: JSON.stringify({ amount, action: 'add' }),
                });

                showFuturesToast(res.message || 'Demo balance added successfully!', 'success');
                if (demoBalInput) demoBalInput.value = '';
                presetChips.forEach(c => c.classList.remove('active'));
                closeFuturesSetDemoBalanceModal();

                const updatedWallet = res.data?.wallet;
                if (updatedWallet) {
                    state.wallet = updatedWallet;
                    const uid = localStorage.getItem('kryvex_active_account_uid');
                    localStorage.setItem('kryvex_wallet_cache', JSON.stringify({ wallet: updatedWallet }));
                    if (uid) {
                        localStorage.setItem('kryvex_wallet_cache_' + uid, JSON.stringify({ wallet: updatedWallet }));
                    }

                    // Sync trading accounts cache
                    let cachedAccounts = [];
                    try {
                        cachedAccounts = window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]');
                    } catch (e) {
                        cachedAccounts = [];
                    }
                    cachedAccounts.forEach(acc => {
                        if (acc.account_type === 'demo' || acc.is_demo) {
                            acc.demo_available_balance = Number(updatedWallet.demo_available_balance ?? acc.demo_available_balance);
                            acc.demo_main_balance = Number(updatedWallet.demo_main_balance ?? acc.demo_main_balance);
                            acc.demo_available_balance_inr = Number(updatedWallet.demo_available_balance_inr ?? (acc.demo_available_balance * 95));
                            acc.demo_main_balance_inr = Number(updatedWallet.demo_main_balance_inr ?? (acc.demo_main_balance * 95));
                        }
                    });
                    window.__cachedTradingAccounts = cachedAccounts;
                    localStorage.setItem('kryvex_cached_trading_accounts', JSON.stringify(cachedAccounts));
                }

                // Dispatch wallet and account update events
                window.dispatchEvent(new CustomEvent('kryvex_wallet_updated'));
                window.dispatchEvent(new CustomEvent('tradingAccountChanged'));
                window.dispatchEvent(new CustomEvent('kryvex_account_changed'));

                // Reload balances across page
                await loadWalletBalance();
                await loadProtectedFuturesData();
                updateLiveWalletMetrics();
                updateOrderEstimate();

                if (typeof window.reloadFuturesBalances === 'function') {
                    window.reloadFuturesBalances();
                }
                if (typeof window.loadNavbarTradingAccounts === 'function') {
                    await window.loadNavbarTradingAccounts(true);
                }
                if (typeof window.updateNavbarBalanceDisplay === 'function') {
                    window.updateNavbarBalanceDisplay();
                }
                if (typeof window.renderTradingAccountsSelector === 'function') {
                    window.renderTradingAccountsSelector();
                }
            } catch (err) {
                showFuturesToast(err.message || 'Failed to update demo balance.', 'error');
            } finally {
                confirmSetBtn.removeAttribute('disabled');
                confirmSetBtn.textContent = origText;
            }
        });
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            if (setBalanceModal && !setBalanceModal.hidden && setBalanceModal.style.display !== 'none') {
                closeFuturesSetDemoBalanceModal();
            } else if (demoModal && !demoModal.hidden && demoModal.style.display !== 'none') {
                closeFuturesDemoDepositModal();
            }
        }
    });
}

function setupWalletActionModal() {
    document.addEventListener('click', (event) => {
        const modalBtn = event.target.closest('[data-wallet-modal]');
        if (modalBtn) {
            event.preventDefault();
            const action = modalBtn.dataset.walletModal || 'deposit';
            if (action === 'deposit') {
                const activeMode = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live' ? 'live' : 'demo';
                if (activeMode === 'live') {
                    openFuturesAddFundsModal();
                } else {
                    openFuturesDemoDepositModal();
                }
            } else {
                openFuturesWalletModal(action);
            }
        }
        const closeBtn = event.target.closest('[data-close-wallet-modal]');
        if (closeBtn) {
            event.preventDefault();
            closeWalletActionModal();
        }
    });

    const modal = document.getElementById('futuresWalletModal');

    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeWalletActionModal();
            }
        });
    }

    const form = document.getElementById('futuresWalletActionForm');

    if (form) {
        form.addEventListener('submit', submitWalletAction);
    }

    const walletAmtInput = document.getElementById('walletActionAmount');
    walletAmtInput?.addEventListener('input', () => {
        updateFuturesWalletWithdrawPreview();
        updateFuturesLiveModalCalc();
    });
    walletAmtInput?.addEventListener('wheel', (e) => {
        if (document.activeElement === walletAmtInput) {
            walletAmtInput.blur();
        }
        e.preventDefault();
    }, { passive: false });

    // Deposit Popup UI Event Bindings
    const depositGrid = document.getElementById('depositNetworkGrid');
    depositGrid?.addEventListener('click', (e) => {
        const netCard = e.target.closest('.deposit-net-card');
        if (!netCard) return;
        const netCode = netCard.getAttribute('data-network');
        const stdSelect = document.getElementById('walletNetworkSelect');
        if (stdSelect) stdSelect.value = netCode;

        document.querySelectorAll('#depositNetworkGrid .deposit-net-card').forEach(c => {
            c.classList.remove('is-active');
            const check = c.querySelector('.deposit-net-card-check');
            if (check) check.remove();
        });

        netCard.classList.add('is-active');
        const checkDiv = document.createElement('div');
        checkDiv.className = 'deposit-net-card-check';
        checkDiv.textContent = '✓';
        netCard.appendChild(checkDiv);

        renderFuturesWalletNetworkDetails();
        updateFuturesDepositAddressDetails();
    });

    const triggerCopyAddr = async () => {
        const addr = document.getElementById('dpDepositAddressText')?.textContent?.trim();
        if (!addr || addr === 'Loading address...' || addr === 'No deposit address available.') {
            showFuturesToast('No address to copy.', 'error');
            return;
        }
        await copyTextToClipboard(addr);
        showFuturesToast('Deposit address copied.', 'success');
    };

    document.getElementById('dpCopyAddressInlineBtn')?.addEventListener('click', triggerCopyAddr);

    // Withdraw Popup UI Event Bindings
    const wdAmountInput = document.getElementById('wdAmountInput');
    const wdAddressInput = document.getElementById('wdAddressInput');
    const wdNetSelectCustom = document.getElementById('wdNetworkSelectCustom');
    const wdMaxBtn = document.getElementById('wdMaxBtn');
    const wdSubmitBtn = document.getElementById('wdSubmitBtn');

    wdAmountInput?.addEventListener('input', () => {
        const stdAmt = document.getElementById('walletActionAmount');
        if (stdAmt) stdAmt.value = wdAmountInput.value;
        updateFuturesWithdrawUiPreview();
    });

    wdAddressInput?.addEventListener('input', () => {
        const stdAddr = document.getElementById('walletWithdrawAddressInput');
        if (stdAddr) stdAddr.value = wdAddressInput.value;
        updateFuturesWithdrawUiPreview();
    });

    wdNetSelectCustom?.addEventListener('change', (e) => {
        const selectedNet = e.target.value;
        const stdSelect = document.getElementById('walletNetworkSelect');
        if (stdSelect) stdSelect.value = selectedNet;
        const textEl = document.getElementById('wdSelectedNetworkText');
        if (textEl && e.target.selectedOptions[0]) {
            textEl.textContent = e.target.selectedOptions[0].text;
        }
        const iconEl = document.getElementById('wdNetworkIcon');
        if (iconEl) {
            const netVal = (selectedNet || '').toUpperCase();
            if (netVal.includes('TRC') || netVal.includes('TRON')) iconEl.textContent = 'T';
            else if (netVal.includes('ERC') || netVal.includes('ETH')) iconEl.textContent = 'E';
            else if (netVal.includes('BEP') || netVal.includes('BSC') || netVal.includes('BNB')) iconEl.textContent = 'B';
            else iconEl.textContent = netVal.charAt(0) || '₮';
        }
        renderFuturesWalletNetworkDetails();
        populateSavedWithdrawalAddress(selectedNet);
        updateFuturesWithdrawUiPreview();
    });

    wdMaxBtn?.addEventListener('click', () => {
        const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
        const isINR = (rawCur === 'INR' || futuresActiveModalTab === 'inr');
        const mainBal = getFuturesMainBalance();
        if (isINR) {
            if (wdAmountInput) wdAmountInput.value = mainBal.inr > 0 ? mainBal.inr.toFixed(2) : '';
        } else {
            if (wdAmountInput) wdAmountInput.value = mainBal.usdt > 0 ? mainBal.usdt : '';
        }
        const stdAmt = document.getElementById('walletActionAmount');
        if (stdAmt && wdAmountInput) stdAmt.value = wdAmountInput.value;
        updateFuturesWithdrawUiPreview();
    });

    wdSubmitBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        const stdAmt = document.getElementById('walletActionAmount');
        if (stdAmt && wdAmountInput) stdAmt.value = wdAmountInput.value;

        const stdAddr = document.getElementById('walletWithdrawAddressInput');
        if (stdAddr && wdAddressInput) stdAddr.value = wdAddressInput.value;

        const stdSelect = document.getElementById('walletNetworkSelect');
        if (stdSelect && wdNetSelectCustom) stdSelect.value = wdNetSelectCustom.value;

        const walletForm = document.getElementById('futuresWalletActionForm');
        if (walletForm) {
            if (typeof walletForm.requestSubmit === 'function') {
                walletForm.requestSubmit();
            } else {
                walletForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }
        }
    });

    document.getElementById('depositAccountSelectInline')?.addEventListener('change', (e) => {
        const selectedUid = e.target.value;
        const subAccBal = document.getElementById('depositSubAccBalance');
        const selectedSubAcc = (tradingAccountsState.accounts || []).find(a => a.account_uid === selectedUid);
        const subBal = getFuturesAccountAvailable(selectedSubAcc);
        if (subAccBal) subAccBal.textContent = formatUsdt(subBal.usdt);
    });

    document.getElementById('dpInrAmountInput')?.addEventListener('input', () => updateFuturesLiveModalCalc());

    document.getElementById('dpInrSubmitBtn')?.addEventListener('click', async () => {
        const amtInput = document.getElementById('dpInrAmountInput');
        const amount = Number(amtInput?.value || 0);

        if (!amount || amount <= 0) {
            showFuturesToast('Please enter a valid deposit amount in ₹.', 'error');
            return;
        }

        const btn = document.getElementById('dpInrSubmitBtn');
        if (btn) {
            btn.setAttribute('disabled', 'disabled');
            btn.textContent = 'Initializing Razorpay...';
        }

        let user = {};
        try {
            user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
        } catch (e) {}

        try {
            const orderRes = await authFetch('/api/wallet/razorpay/create-order', {
                method: 'POST',
                body: JSON.stringify({ amount_inr: amount }),
            });

            if (!orderRes || !orderRes.success) {
                throw new Error(orderRes?.message || 'Failed to initialize Razorpay payment order.');
            }

            const RazorpayCtor = await loadRazorpaySdk();

            const options = {
                key: orderRes.key_id,
                amount: orderRes.amount_paise,
                currency: orderRes.currency || 'INR',
                name: 'Kryvex Exchange',
                description: `INR Futures Deposit ₹${amount}`,
                order_id: orderRes.order_id,
                prefill: {
                    name: orderRes.user?.name || user.name || '',
                    email: orderRes.user?.email || user.email || '',
                    contact: orderRes.user?.mobile || user.mobile || '',
                },
                theme: { color: '#2563eb' },
                handler: async function (response) {
                    if (btn) btn.textContent = 'Verifying Payment...';
                    try {
                        const verifyRes = await authFetch('/api/wallet/razorpay/verify', {
                            method: 'POST',
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id || orderRes.order_id,
                                razorpay_payment_id: response.razorpay_payment_id || '',
                                razorpay_signature: response.razorpay_signature || '',
                                amount_inr: amount,
                            }),
                        });

                        showFuturesToast(verifyRes.message || 'Razorpay Payment Verified & Wallet Credited! 🎉', 'success');
                        if (amtInput) amtInput.value = '';
                        closeWalletActionModal();
                        await loadProtectedFuturesData();
                    } catch (err) {
                        showFuturesToast(err.message || 'Payment verification failed.', 'error');
                    } finally {
                        if (btn) {
                            btn.removeAttribute('disabled');
                            btn.textContent = 'Submit INR Deposit Request';
                        }
                    }
                },
                modal: {
                    ondismiss: function () {
                        if (btn) {
                            btn.removeAttribute('disabled');
                            btn.textContent = 'Submit INR Deposit Request';
                        }
                        showFuturesToast('Razorpay payment cancelled.', 'warning');
                    }
                }
            };

            const rzp = new RazorpayCtor(options);
            rzp.open();
        } catch (err) {
            showFuturesToast(err.message || 'INR deposit failed.', 'error');
            if (btn) {
                btn.removeAttribute('disabled');
                btn.textContent = 'Submit INR Deposit Request';
            }
        }
    });

    document.getElementById('dpUsdSubmitBtn')?.addEventListener('click', async () => {
        const amtInput = document.getElementById('dpUsdAmountInput');
        const amount = Number(amtInput?.value || 0);

        if (!amount || amount <= 0) {
            showFuturesToast('Please enter a valid deposit amount in USD.', 'error');
            return;
        }

        try {
            const btn = document.getElementById('dpUsdSubmitBtn');
            if (btn) btn.setAttribute('disabled', 'disabled');

            const res = await authFetch('/api/wallet/demo-credit', {
                method: 'POST',
                body: JSON.stringify({ amount, action: 'add' }),
            });

            showFuturesToast(res.message || 'Deposit successful! Funds credited to wallet.', 'success');
            if (amtInput) amtInput.value = '';
            closeWalletActionModal();

            const updatedWallet = res.data?.wallet;
            if (updatedWallet) {
                state.wallet = updatedWallet;
                const uid = localStorage.getItem('kryvex_active_account_uid');
                localStorage.setItem('kryvex_wallet_cache', JSON.stringify({ wallet: updatedWallet }));
                if (uid) {
                    localStorage.setItem('kryvex_wallet_cache_' + uid, JSON.stringify({ wallet: updatedWallet }));
                }

                let cachedAccounts = [];
                try {
                    cachedAccounts = window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]');
                } catch (e) {
                    cachedAccounts = [];
                }
                cachedAccounts.forEach(acc => {
                    if (acc.account_type === 'demo' || acc.is_demo) {
                        acc.demo_available_balance = Number(updatedWallet.demo_available_balance ?? acc.demo_available_balance);
                        acc.demo_main_balance = Number(updatedWallet.demo_main_balance ?? acc.demo_main_balance);
                        acc.demo_available_balance_inr = Number(updatedWallet.demo_available_balance_inr ?? (acc.demo_available_balance * 95));
                        acc.demo_main_balance_inr = Number(updatedWallet.demo_main_balance_inr ?? (acc.demo_main_balance * 95));
                    }
                });
                window.__cachedTradingAccounts = cachedAccounts;
                localStorage.setItem('kryvex_cached_trading_accounts', JSON.stringify(cachedAccounts));
            }

            window.dispatchEvent(new CustomEvent('kryvex_wallet_updated'));
            window.dispatchEvent(new CustomEvent('tradingAccountChanged'));
            window.dispatchEvent(new CustomEvent('kryvex_account_changed'));

            await loadWalletBalance();
            await loadProtectedFuturesData();
            updateLiveWalletMetrics();
            updateOrderEstimate();

            if (typeof window.reloadFuturesBalances === 'function') {
                window.reloadFuturesBalances();
            }
            if (typeof window.loadNavbarTradingAccounts === 'function') {
                await window.loadNavbarTradingAccounts(true);
            }
            if (typeof window.updateNavbarBalanceDisplay === 'function') {
                window.updateNavbarBalanceDisplay();
            }
        } catch (err) {
            showFuturesToast(err.message || 'Deposit request failed.', 'error');
        } finally {
            const btn = document.getElementById('dpUsdSubmitBtn');
            if (btn) btn.removeAttribute('disabled');
        }
    });

    // Transfer Popup UI Event Bindings (Modern Dual-Wallet Modal)
    const tfSwapBtn = document.getElementById('transferSwapDirectionBtn') || document.getElementById('tfSwapBtn');
    const tfAmountInput = document.getElementById('transferAmountInput') || document.getElementById('tfAmountInput');
    const tfSelectInline = document.getElementById('walletTransferAccountSelectInline');
    const tfTradingAccSelect = document.getElementById('transferTradingAccountSelect');
    const curInrBtn = document.getElementById('transferCurInrBtn');
    const curUsdtBtn = document.getElementById('transferCurUsdtBtn');

    tfSelectInline?.addEventListener('change', (e) => {
        const stdSelect = document.getElementById('walletTransferAccountSelect');
        if (stdSelect) stdSelect.value = e.target.value;
        if (tfTradingAccSelect) tfTradingAccSelect.value = e.target.value;
        updateFuturesLiveModalCalc();
    });

    tfTradingAccSelect?.addEventListener('change', async (e) => {
        const stdSelect = document.getElementById('walletTransferAccountSelect');
        if (stdSelect) stdSelect.value = e.target.value;
        if (tfSelectInline) tfSelectInline.value = e.target.value;
        if (e.target.value && typeof switchTradingAccount === 'function') {
            await switchTradingAccount(e.target.value);
        }
        updateFuturesLiveModalCalc();
    });

    tfSwapBtn?.addEventListener('click', () => {
        const dirSelect = document.getElementById('walletTransferDirection');
        if (dirSelect) {
            dirSelect.value = (dirSelect.value === 'main_to_trading') ? 'trading_to_main' : 'main_to_trading';
            updateFuturesLiveModalCalc();
        }
    });

    tfAmountInput?.addEventListener('input', () => {
        const stdInput = document.getElementById('walletActionAmount');
        if (stdInput) stdInput.value = tfAmountInput.value;
        updateFuturesLiveModalCalc();
    });

    const switchTransferCurrency = (newCur) => {
        const normalized = newCur === 'INR' ? 'INR' : 'USD';
        localStorage.setItem('kryvex_active_currency', normalized);
        localStorage.setItem('kryvex_currency', normalized === 'INR' ? 'INR' : 'USDT');
        window.dispatchEvent(new CustomEvent('kryvex_currency_changed', { detail: { currency: normalized } }));
        updateFuturesLiveModalCalc();
    };

    curInrBtn?.addEventListener('click', () => switchTransferCurrency('INR'));
    curUsdtBtn?.addEventListener('click', () => switchTransferCurrency('USD'));

    document.addEventListener('click', (e) => {
        const pctBtn = e.target.closest('.transfer-pct-btn');
        if (pctBtn) {
            e.preventDefault();
            const pct = Number(pctBtn.getAttribute('data-pct') || 100);
            const dirSelect = document.getElementById('walletTransferDirection');
            const direction = dirSelect?.value || 'main_to_trading';
            const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
            const activeCur = (rawCur === 'INR') ? 'INR' : 'USD';
            const buyRate = futuresInrState.usdtInrBuyRate || futuresInrState.usdtInrRate || 83.00;

            const selectedSubUid = tfTradingAccSelect?.value || tradingAccountsState.activeAccountUid;
            const selectedSubAcc = (tradingAccountsState.accounts || []).find(a => a.account_uid === selectedSubUid);
            const mainBal = getFuturesMainBalance(selectedSubAcc);
            const mainBalUsdt = mainBal.usdt;
            const mainBalInr = mainBal.inr;
            const subBal = getFuturesAccountAvailable(selectedSubAcc);
            let tradingBalUsdt = subBal.usdt;
            let tradingBalInr = subBal.inr;

            let maxVal = 0;
            if (isCentAccount(selectedSubAcc)) {
                maxVal = (direction === 'main_to_trading' ? mainBalUsdt : tradingBalUsdt) * 100;
            } else if (direction === 'main_to_trading') {
                maxVal = activeCur === 'INR' ? (mainBalInr > 0 ? mainBalInr : mainBalUsdt * buyRate) : mainBalUsdt;
            } else {
                maxVal = activeCur === 'INR' ? (tradingBalInr > 0 ? tradingBalInr : tradingBalUsdt * buyRate) : tradingBalUsdt;
            }

            const tfInput = document.getElementById('transferAmountInput') || document.getElementById('tfAmountInput');
            const stdInput = document.getElementById('walletActionAmount');
            if (maxVal > 0) {
                const targetVal = maxVal * (pct / 100);
                const formatted = targetVal >= 1 ? targetVal.toFixed(2) : targetVal.toFixed(6);
                if (tfInput) tfInput.value = formatted;
                if (stdInput) stdInput.value = formatted;
            } else {
                if (tfInput) tfInput.value = '0';
                if (stdInput) stdInput.value = '0';
            }
            updateFuturesLiveModalCalc();
            return;
        }

        const maxBtn = e.target.closest('#transferMaxButton, #tfMaxBtn');
        if (maxBtn) {
            e.preventDefault();
            const dirSelect = document.getElementById('walletTransferDirection');
            const direction = dirSelect?.value || 'main_to_trading';
            const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
            const activeCur = (rawCur === 'INR') ? 'INR' : 'USD';
            const buyRate = futuresInrState.usdtInrBuyRate || futuresInrState.usdtInrRate || 83.00;

            const selectedSubUid = tfTradingAccSelect?.value || tradingAccountsState.activeAccountUid;
            const selectedSubAcc = (tradingAccountsState.accounts || []).find(a => a.account_uid === selectedSubUid);
            const mainBal = getFuturesMainBalance(selectedSubAcc);
            const mainBalUsdt = mainBal.usdt;
            const mainBalInr = mainBal.inr;
            const subBal = getFuturesAccountAvailable(selectedSubAcc);
            let tradingBalUsdt = subBal.usdt;
            let tradingBalInr = subBal.inr;

            let maxVal = 0;
            if (isCentAccount(selectedSubAcc)) {
                maxVal = (direction === 'main_to_trading' ? mainBalUsdt : tradingBalUsdt) * 100;
            } else if (direction === 'main_to_trading') {
                maxVal = activeCur === 'INR' ? (mainBalInr > 0 ? mainBalInr : mainBalUsdt * buyRate) : mainBalUsdt;
            } else {
                maxVal = activeCur === 'INR' ? (tradingBalInr > 0 ? tradingBalInr : tradingBalUsdt * buyRate) : tradingBalUsdt;
            }

            const tfInput = document.getElementById('transferAmountInput') || document.getElementById('tfAmountInput');
            const stdInput = document.getElementById('walletActionAmount');
            const formatted = maxVal > 0 ? (maxVal >= 1 ? maxVal.toFixed(2) : maxVal.toFixed(6)) : '0';
            if (tfInput) tfInput.value = formatted;
            if (stdInput) stdInput.value = formatted;
            updateFuturesLiveModalCalc();
        }
    });

    document.getElementById('walletTransferDirection')?.addEventListener('change', () => {
        updateFuturesLiveModalCalc();
    });

    document.getElementById('walletNetworkSelect')?.addEventListener('change', renderFuturesWalletNetworkDetails);
    document.getElementById('walletCopyDepositAddress')?.addEventListener('click', async () => {
        const network = getFuturesWalletNetwork(document.getElementById('walletNetworkSelect')?.value);
        if (!network?.address) { showFuturesToast('Select network first.', 'error'); return; }
        await copyTextToClipboard(network.address);
        showFuturesToast('Deposit address copied.', 'success');
    });

    document.getElementById('walletActionMaxButton')?.addEventListener('click', () => {
        const action = document.getElementById('walletActionType')?.value || 'deposit';
        const input = document.getElementById('walletActionAmount');
        const isINR = futuresInrState.baseCurrency === 'INR' && futuresInrState.usdtInrRate > 0;
        const rate = futuresInrState.usdtInrRate || 1;

        if (input) {
            if (action === 'transfer') {
                const direction = document.getElementById('walletTransferDirection')?.value || 'main_to_trading';
                const avail = Number(state.wallet?.demo_available_balance || 0);
                const formattedAvail = avail > 0 && avail < 0.01 ? avail.toFixed(8).replace(/\.?0+$/, '') : avail.toFixed(2);
                if (isINR) {
                    if (direction === 'main_to_trading') {
                        input.value = ((Number(state.wallet?.demo_main_balance || 0)) * rate).toFixed(2);
                    } else {
                        input.value = formattedAvail;
                    }
                } else {
                    input.value = direction === 'main_to_trading'
                        ? Number(state.wallet?.demo_main_balance || 0)
                        : formattedAvail;
                }
            } else if (action === 'withdraw') {
                if (futuresActiveModalTab === 'inr') {
                    const maxInr = Number(state.wallet?.demo_main_balance_inr || 0);
                    input.value = maxInr > 0 ? maxInr.toFixed(2) : '';
                } else {
                    const maxUsdt = Number(state.wallet_meta?.max_withdrawable || state.wallet?.demo_main_balance || 0);
                    input.value = maxUsdt > 0 ? maxUsdt : '';
                }
            }
            input.dispatchEvent(new Event('input', { bubbles: true }));
            updateFuturesWalletWithdrawPreview();
            updateFuturesLiveModalCalc();
        }
    });
}

function openWalletActionModal(type) {
    openFuturesWalletModal(type);
}

function verifyKycAndCurrencyBeforeAction() {
    const accessToken = localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token');
    if (!accessToken) {
        showFuturesToast('Please login first.', 'error');
        return false;
    }

    let user = {};
    try {
        user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (e) { }

    const kycStatus = (user.kyc_status || '').toLowerCase();
    const isKycVerified = ['verified', 'approved'].includes(kycStatus);
    if (!isKycVerified) {
        showFuturesToast('KYC Verification Required: Please complete KYC identity verification first before performing deposit or withdrawal.', 'error');
        return false;
    }

    return true;
}

function setupFuturesModalCurrencyTabs() {
    const usdtBtn = document.getElementById('futuresModalTabUsdt');
    const inrBtn = document.getElementById('futuresModalTabInr');

    if (usdtBtn) {
        usdtBtn.addEventListener('click', () => switchFuturesModalTab('usdt'));
    }
    if (inrBtn) {
        inrBtn.addEventListener('click', () => switchFuturesModalTab('inr'));
    }
}

function switchFuturesModalTab(tab) {
    futuresActiveModalTab = tab;
    document.getElementById('futuresModalTabUsdt')?.classList.toggle('is-active', tab === 'usdt');
    document.getElementById('futuresModalTabInr')?.classList.toggle('is-active', tab === 'inr');

    const type = document.getElementById('walletActionType')?.value || 'deposit';
    const isWithdraw = type === 'withdraw';
    const isTransfer = type === 'transfer';

    const networkRow = document.getElementById('walletNetworkRow');
    const depositAddressBox = document.getElementById('walletDepositAddressBox');
    const withdrawAddressRow = document.getElementById('walletWithdrawAddressRow');
    const withdrawAddressRule = document.getElementById('walletWithdrawAddressRule');
    const bankBox = document.getElementById('futuresInrBankBox');
    const utrRow = document.getElementById('futuresUtrRow');
    const inrWithdrawFields = document.getElementById('futuresInrWithdrawFields');
    const amountLabel = document.getElementById('futuresAmountLabel');

    if (tab === 'inr') {
        if (networkRow) networkRow.hidden = true;
        if (depositAddressBox) depositAddressBox.hidden = true;
        if (withdrawAddressRow) withdrawAddressRow.hidden = true;
        if (withdrawAddressRule) withdrawAddressRule.hidden = true;

        if (bankBox) bankBox.style.display = (!isWithdraw && !isTransfer) ? 'block' : 'none';
        if (utrRow) utrRow.hidden = !(!isWithdraw && !isTransfer);
        if (inrWithdrawFields) inrWithdrawFields.style.display = 'none';
        if (amountLabel) amountLabel.textContent = 'Amount (INR)';
        renderFuturesInrBankDetails();

        if (isWithdraw) {
            loadUserSavedBankDetails().then((b) => {
                const banner = document.getElementById('wdSavedBankBanner');
                if (!banner) return;
                if (b) {
                    banner.innerHTML = `
                        <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); padding: 12px 14px; border-radius: 10px; font-size: 0.84rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                                <span style="color: #10b981; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                                    🏦 Withdrawal Bank Account
                                </span>
                                <a href="/settings?tab=bank" style="color: #60a5fa; text-decoration: underline; font-size: 0.76rem; font-weight: 600;">Edit ⚙️</a>
                            </div>
                            <div style="color: #fff; font-weight: 600;">${escapeHtml(b.account_name || '')} • ${escapeHtml(b.bank_name || '')} (${escapeHtml(b.account_type || 'Savings')})</div>
                            <div style="color: var(--kv-muted); font-size: 0.8rem; margin-top: 2px; font-family: monospace;">A/C: ${escapeHtml(b.account_number || '')} | IFSC: ${escapeHtml(b.ifsc_code || '')}</div>
                            ${b.upi_id ? `<div style="color: #a7f3d0; font-size: 0.78rem; margin-top: 2px;">UPI: ${escapeHtml(b.upi_id)}</div>` : ''}
                        </div>
                    `;
                } else {
                    banner.innerHTML = `
                        <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); padding: 12px 14px; border-radius: 10px; font-size: 0.84rem; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                            <div>
                                <strong style="color: #ef4444; display: block;">⚠️ Bank Details Missing</strong>
                                <span style="color: #fca5a5; font-size: 0.78rem;">Please save your bank account details first in Settings to withdraw.</span>
                            </div>
                            <a href="/settings?tab=bank" class="kv-btn kv-btn-primary" style="padding: 6px 12px; font-size: 0.78rem; text-decoration: none; border-radius: 8px;">
                                Add Details ➔
                            </a>
                        </div>
                    `;
                }
            });
        }
    } else {
        if (bankBox) bankBox.style.display = 'none';
        if (utrRow) utrRow.hidden = true;
        if (inrWithdrawFields) inrWithdrawFields.style.display = 'none';

        if (networkRow) networkRow.hidden = isTransfer;
        if (depositAddressBox) depositAddressBox.hidden = isTransfer || isWithdraw;
        if (withdrawAddressRow) { withdrawAddressRow.hidden = true; withdrawAddressRow.style.display = 'none'; }
        if (withdrawAddressRule) { withdrawAddressRule.hidden = true; withdrawAddressRule.style.display = 'none'; }
        if (amountLabel) amountLabel.textContent = 'Amount (USD)';
        renderFuturesWalletNetworks();
    }
}

function resetAllFuturesModalInputFields() {
    const inputIds = [
        'dpUsdAmountInput', 'dpInrAmountInput', 'tfAmountInput', 'transferAmountInput',
        'walletActionAmount', 'wdAmountInput', 'wdAddressInput', 'walletWithdrawAddressInput',
        'walletUtrInput', 'inrWithdrawAmount', 'withdrawAddressInput', 'cryptoAssetAmountInput',
        'cryptoAssetTxHashInput', 'internalRecipientAddressInput', 'internalSendAmountInput',
        'internalSendNoteInput', 'exchangeFromAmount', 'exchangeToAmount', 'newAccountName'
    ];
    inputIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
}

function openFuturesWalletModal(type = 'deposit') {
    resetAllFuturesModalInputFields();
    const modal = document.getElementById('futuresWalletModal');
    const actionInput = document.getElementById('walletActionType');
    const transferDirectionRow = document.getElementById('walletTransferDirectionRow');
    const transferDirection = document.getElementById('walletTransferDirection');
    const amountInput = document.getElementById('walletActionAmount');
    const tfInput = document.getElementById('tfAmountInput');
    const title = document.getElementById('walletModalTitle');
    const subtitle = document.getElementById('walletModalSubtitle');
    const submit = document.getElementById('walletModalSubmit');
    const help = document.getElementById('walletModalHelp');
    const icon = document.getElementById('walletModalIcon');

    const depositUiContainer = document.getElementById('futuresDepositUiContainer');
    const transferUiContainer = document.getElementById('futuresTransferUiContainer');
    const withdrawUiContainer = document.getElementById('futuresWithdrawUiContainer');
    const stdHeader = document.getElementById('stdModalHeader');
    const stdAmountRow = document.getElementById('stdAmountRow');
    const stdModalActions = document.getElementById('stdModalActions');

    const rate = futuresInrState.usdtInrRate || 1;
    const rateBar = document.getElementById('futuresRateBar');
    const rateVal = document.getElementById('futuresRateVal');
    const convBox = document.getElementById('futuresConversionBox');

    if (!modal || !actionInput) return;

    const isWithdraw = type === 'withdraw';
    const isTransfer = type === 'transfer';
    const isDeposit = type === 'deposit';

    const mainBalanceUsdt = Number(state.wallet?.demo_main_balance || 0);
    const mainBalanceInr = Number(state.wallet?.demo_main_balance_inr || 0);
    const tradingBalance = Number(state.wallet?.demo_available_balance || 0);

    actionInput.value = isTransfer ? 'transfer' : isWithdraw ? 'withdraw' : 'deposit';

    modal.classList.remove('is-deposit', 'is-withdraw', 'is-transfer');
    if (isDeposit) {
        modal.classList.add('is-deposit');
        if (depositUiContainer) depositUiContainer.hidden = false;
        if (transferUiContainer) transferUiContainer.hidden = true;
        if (withdrawUiContainer) withdrawUiContainer.hidden = true;
        if (stdHeader) stdHeader.style.display = 'none';
        if (stdAmountRow) stdAmountRow.style.display = 'none';
        if (stdModalActions) stdModalActions.style.display = 'none';
        if (help) help.style.display = 'none';
        renderFuturesWalletNetworks();
        renderFuturesDepositUi();
    } else if (isTransfer) {
        modal.classList.add('is-transfer');
        if (depositUiContainer) depositUiContainer.hidden = true;
        if (transferUiContainer) transferUiContainer.hidden = false;
        if (withdrawUiContainer) withdrawUiContainer.hidden = true;
        if (stdHeader) stdHeader.style.display = 'none';
        if (stdAmountRow) stdAmountRow.style.display = 'none';
        if (stdModalActions) stdModalActions.style.display = 'none';
        if (help) help.style.display = 'none';
        if (tfInput) tfInput.value = '';
    } else if (isWithdraw) {
        modal.classList.add('is-withdraw');
        if (depositUiContainer) depositUiContainer.hidden = true;
        if (transferUiContainer) transferUiContainer.hidden = true;
        if (withdrawUiContainer) withdrawUiContainer.hidden = false;
        if (stdHeader) stdHeader.style.display = 'none';
        if (stdAmountRow) stdAmountRow.style.display = 'none';
        if (stdModalActions) stdModalActions.style.display = 'none';
        if (help) help.style.display = 'none';
        renderFuturesWalletNetworks();
        renderFuturesWithdrawUi();
    } else {
        if (depositUiContainer) depositUiContainer.hidden = true;
        if (transferUiContainer) transferUiContainer.hidden = true;
        if (withdrawUiContainer) withdrawUiContainer.hidden = true;
        if (stdHeader) stdHeader.style.display = '';
        if (stdAmountRow) stdAmountRow.style.display = '';
        if (stdModalActions) stdModalActions.style.display = '';
        if (help) help.style.display = '';
    }

    if (transferDirectionRow) transferDirectionRow.hidden = !isTransfer;
    if (transferDirection) {
        if (tradingBalance > 0 && mainBalanceUsdt === 0 && mainBalanceInr === 0) {
            transferDirection.value = 'trading_to_main';
        } else {
            transferDirection.value = 'main_to_trading';
        }
    }

    const modalTabs = document.getElementById('futuresModalCurrencyTabs');
    if (modalTabs) {
        modalTabs.style.display = 'none';
    }

    const tabUsdt = document.getElementById('futuresModalTabUsdt');
    const tabInr = document.getElementById('futuresModalTabInr');
    if (tabUsdt) tabUsdt.style.display = 'none';
    if (tabInr) tabInr.style.display = 'none';

    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : 'USDT';
    switchFuturesModalTab(activeCur === 'INR' ? 'inr' : 'usdt');

    if (title) title.textContent = isTransfer ? 'Transfer Balance' : isWithdraw ? 'Withdraw Request' : 'Deposit Funds';
    if (subtitle) subtitle.textContent = isTransfer
        ? 'Move funds between Main balance and Trading balance.'
        : isWithdraw
            ? 'Submit a withdrawal request from Main balance.'
            : 'Deposit funds into Main balance first.';
    if (submit) submit.textContent = isTransfer ? 'Transfer' : isWithdraw ? 'Submit Request' : 'Deposit';
    if (help) help.textContent = isTransfer
        ? `USD Main: ${formatUsdt(mainBalanceUsdt)} • INR Main: ${formatInr(mainBalanceInr)} • Trading: ${formatUsdt(tradingBalance)}`
        : isWithdraw
            ? 'Withdrawal request will be processed from your selected main balance.'
            : 'Deposit will be credited to your selected main balance.';
    if (icon) icon.textContent = isTransfer ? '↔' : isWithdraw ? '↗' : '$';
    if (amountInput) amountInput.value = '';

    renderTransferAccountSelect();
    updateFuturesWalletWithdrawPreview();
    updateFuturesLiveModalCalc();

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    setTimeout(() => {
        if (isTransfer && tfInput) tfInput.focus();
        else amountInput?.focus();
    }, 80);
}

function renderFuturesInrBankDetails() {
    const box = document.getElementById('futuresInrBankInfo');
    if (!box) return;
    let d = futuresInrState.adminBankDetails;
    if (!d) {
        try {
            const cached = JSON.parse(localStorage.getItem('kryvex_inr_state_cache') || '{}');
            d = cached?.adminBankDetails || null;
            if (d && !futuresInrState.adminBankDetails) futuresInrState.adminBankDetails = d;
        } catch (e) {}
    }
    if (!d) {
        box.innerHTML = '<div class="p-3 text-center text-amber-400 text-xs font-bold">Admin has not set INR bank details yet.</div>';
        return;
    }

    let html = `
        <div class="space-y-2 font-sans pt-1">
            <div class="grid grid-cols-2 gap-2 text-xs">
                ${d.bank_name ? `<div class="p-2.5 bg-slate-950/70 border border-slate-800/80 rounded-xl"><span class="text-[10px] text-slate-400 block uppercase font-bold">Bank Name</span><strong class="text-white font-bold block truncate">${escapeHtml(d.bank_name)}</strong></div>` : ''}
                ${d.account_name ? `<div class="p-2.5 bg-slate-950/70 border border-slate-800/80 rounded-xl"><span class="text-[10px] text-slate-400 block uppercase font-bold">Account Holder</span><strong class="text-white font-bold block truncate">${escapeHtml(d.account_name)}</strong></div>` : ''}
            </div>

            ${d.account_number ? `
            <div class="p-2.5 bg-slate-950/70 border border-slate-800/80 rounded-xl flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <span class="text-[10px] text-slate-400 block uppercase font-bold">Account Number</span>
                    <strong class="text-emerald-400 font-mono font-extrabold text-sm block truncate">${escapeHtml(d.account_number)}</strong>
                </div>
                <button type="button" onclick="navigator.clipboard?.writeText('${escapeHtml(d.account_number)}')" class="px-2.5 py-1 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/30 text-emerald-400 text-[11px] font-bold transition-all border border-emerald-500/20 cursor-pointer flex-shrink-0">
                    Copy
                </button>
            </div>
            ` : ''}

            <div class="grid grid-cols-2 gap-2 text-xs">
                ${d.ifsc ? `
                <div class="p-2.5 bg-slate-950/70 border border-slate-800/80 rounded-xl flex items-center justify-between gap-1 min-w-0">
                    <div class="min-w-0">
                        <span class="text-[10px] text-slate-400 block uppercase font-bold">IFSC Code</span>
                        <strong class="text-white font-mono font-bold block truncate text-xs">${escapeHtml(d.ifsc)}</strong>
                    </div>
                    <button type="button" onclick="navigator.clipboard?.writeText('${escapeHtml(d.ifsc)}')" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold transition-all flex-shrink-0 cursor-pointer">
                        Copy
                    </button>
                </div>
                ` : ''}

                ${d.upi_id ? `
                <div class="p-2.5 bg-slate-950/70 border border-slate-800/80 rounded-xl flex items-center justify-between gap-1 min-w-0">
                    <div class="min-w-0">
                        <span class="text-[10px] text-slate-400 block uppercase font-bold">UPI ID</span>
                        <strong class="text-purple-300 font-mono font-bold block truncate text-xs">${escapeHtml(d.upi_id)}</strong>
                    </div>
                    <button type="button" onclick="navigator.clipboard?.writeText('${escapeHtml(d.upi_id)}')" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold transition-all flex-shrink-0 cursor-pointer">
                        Copy
                    </button>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    box.innerHTML = html;
}

function updateFuturesLiveModalCalc() {
    const action = document.getElementById('walletActionType')?.value || 'deposit';
    const amountInput = document.getElementById('walletActionAmount');
    const tfInput = document.getElementById('transferAmountInput') || document.getElementById('tfAmountInput');
    const dirSelect = document.getElementById('walletTransferDirection');
    const amountLabel = document.getElementById('futuresAmountLabel');
    const fromLabel = document.getElementById('futuresConvFromLabel');
    const fromVal = document.getElementById('futuresConvFromVal');
    const toLabel = document.getElementById('futuresConvToLabel');
    const toVal = document.getElementById('futuresConvToVal');
    const convBox = document.getElementById('futuresConversionBox');
    const rateBar = document.getElementById('futuresRateBar');
    const rateVal = document.getElementById('futuresRateVal');

    // Modern Transfer UI Elements
    const curInrBtn = document.getElementById('transferCurInrBtn');
    const curUsdtBtn = document.getElementById('transferCurUsdtBtn');
    const tfTradingAccSelect = document.getElementById('transferTradingAccountSelect');
    const tfFromTitle = document.getElementById('transferFromTitle');
    const tfFromBal = document.getElementById('transferFromBalance');
    const tfFromIconBox = document.getElementById('transferFromIconBox');
    const tfToTitle = document.getElementById('transferToTitle');
    const tfToBal = document.getElementById('transferToBalance');
    const tfToIconBox = document.getElementById('transferToIconBox');
    const tfMaxAvailVal = document.getElementById('transferMaxAvailableVal');
    const tfAmountLabel = document.getElementById('transferAmountLabel');
    const tfInrRateBar = document.getElementById('transferInrRateBar');
    const tfInrRateVal = document.getElementById('transferInrRateVal');
    const tfConvBox = document.getElementById('transferConversionBox');
    const tfConvFromLabel = document.getElementById('transferConvFromLabel');
    const tfConvFromVal = document.getElementById('transferConvFromVal');
    const tfConvToLabel = document.getElementById('transferConvToLabel');
    const tfConvToVal = document.getElementById('transferConvToVal');

    const tfHeadSubText = document.getElementById('transferHeadSubText');
    const tfFromName = document.getElementById('tfFromName');
    const tfFromBadge = document.getElementById('tfFromBadge');
    const tfFromBalance = document.getElementById('tfFromBalance');
    const tfToName = document.getElementById('tfToName');
    const tfToBalance = document.getElementById('tfToBalance');
    const tfAmountSuffix = document.getElementById('tfAmountSuffix');
    const tfRateCalloutBox = document.getElementById('tfRateCalloutBox');
    const tfRateTitle = document.getElementById('tfRateTitle');
    const tfRateEq = document.getElementById('tfRateEq');
    const tfRateSub = document.getElementById('tfRateSub');
    const selectInline = document.getElementById('walletTransferAccountSelectInline');
    const selectStd = document.getElementById('walletTransferAccountSelect');

    const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
    const activeCur = (rawCur === 'INR') ? 'INR' : 'USD';
    const isTransfer = action === 'transfer';
    let isINR = isTransfer ? (activeCur === 'INR') : (activeCur === 'INR' || futuresActiveModalTab === 'inr');
    const baseRate = futuresInrState.usdtInrRate || 95.00;
    const buyRate = futuresInrState.usdtInrBuyRate || baseRate;
    const sellRate = futuresInrState.usdtInrSellRate || baseRate;

    const direction = dirSelect?.value || 'main_to_trading';
    const isSellDirection = isTransfer && direction === 'trading_to_main';
    const activeRate = isSellDirection ? sellRate : buyRate;

    renderTransferAccountSelect();

    // Update Currency Switcher buttons style
    if (curInrBtn && curUsdtBtn) {
        if (activeCur === 'INR') {
            curInrBtn.className = 'transfer-cur-btn active py-2.5 px-3 rounded-xl border text-xs font-black transition-all flex items-center justify-center cursor-pointer bg-purple-600/20 border-purple-500/30 text-purple-300 shadow-sm';
            curUsdtBtn.className = 'transfer-cur-btn py-2.5 px-3 rounded-xl border text-xs font-bold transition-all flex items-center justify-center cursor-pointer bg-transparent border-transparent text-slate-400 hover:text-white';
        } else {
            curUsdtBtn.className = 'transfer-cur-btn active py-2.5 px-3 rounded-xl border text-xs font-black transition-all flex items-center justify-center cursor-pointer bg-purple-600/20 border-purple-500/30 text-purple-300 shadow-sm';
            curInrBtn.className = 'transfer-cur-btn py-2.5 px-3 rounded-xl border text-xs font-bold transition-all flex items-center justify-center cursor-pointer bg-transparent border-transparent text-slate-400 hover:text-white';
        }
    }

    const selectedSubUid = tfTradingAccSelect?.value || selectInline?.value || selectStd?.value || tradingAccountsState.activeAccountUid;
    const selectedSubAcc = (tradingAccountsState.accounts || []).find(a => a.account_uid === selectedSubUid);

    // Each live account has its own main wallet, so show the one belonging to the selected account.
    const mainBal = getFuturesMainBalance(isTransfer ? selectedSubAcc : null);
    const mainBalanceUsdt = mainBal.usdt;
    const mainBalanceInr = mainBal.inr;
    const subBal = getFuturesAccountAvailable(selectedSubAcc);
    const subBalanceUsdt = subBal.usdt;
    const subBalanceInr = subBal.inr;

    // Cent accounts have no INR/USD choice: everything is shown and entered in USC.
    const isCentTransfer = isTransfer && isCentAccount(selectedSubAcc);
    if (isCentTransfer) isINR = false;
    const curToggleWrap = document.getElementById('transferCurrencyToggleWrap');
    if (curToggleWrap && isTransfer) curToggleWrap.style.display = isCentTransfer ? 'none' : '';
    const transferUnit = isCentTransfer ? 'USC' : (isINR ? 'INR' : 'USD');
    const formattedSubBalance = isCentTransfer ? formatUsc(subBalanceUsdt) : subBal.formatted;
    const mainBalFormatted = isCentTransfer ? formatUsc(mainBalanceUsdt) : mainBal.formatted;

    const walletSvg = `<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>`;
    const chartSvg = `<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>`;

    if (rateBar) {
        rateBar.style.display = (!isTransfer && isINR && action !== 'withdraw') ? 'block' : 'none';
        if (rateVal) {
            const label = isSellDirection ? ' (Admin Sell Rate)' : ' (Admin Buy Rate)';
            rateVal.textContent = `1 USD = ₹${activeRate.toFixed(2)}${label}`;
        }
    }

    if (isTransfer) {
        const val = Number(normalizeNumberString(tfInput?.value || amountInput?.value || 0));
        const tfDirectionBadge = document.getElementById('tfDirectionBadge');
        const tfFromTitleRow = document.getElementById('tfFromTitleRow');
        const tfToTitleRow = document.getElementById('tfToTitleRow');
        const tfToBadge = document.getElementById('tfToBadge');

        let maxValFormatted = '';
        if (direction === 'main_to_trading') {
            if (tfFromTitle) tfFromTitle.textContent = 'Main Wallet';
            if (tfFromBal) tfFromBal.textContent = mainBalFormatted;
            if (tfFromIconBox) {
                tfFromIconBox.className = 'w-8 h-8 rounded-xl bg-purple-600/15 border border-purple-500/30 flex items-center justify-center text-purple-400 flex-shrink-0';
                tfFromIconBox.innerHTML = walletSvg;
            }
            if (tfToTitle) tfToTitle.textContent = 'Trading Wallet';
            if (tfToBal) tfToBal.textContent = formattedSubBalance;
            if (tfToIconBox) {
                tfToIconBox.className = 'w-8 h-8 rounded-xl bg-cyan-600/15 border border-cyan-500/30 flex items-center justify-center text-cyan-400 flex-shrink-0';
                tfToIconBox.innerHTML = chartSvg;
            }
            maxValFormatted = mainBalFormatted;

            if (tfDirectionBadge) {
                tfDirectionBadge.textContent = 'Main → Trading';
                tfDirectionBadge.style.background = 'rgba(139,92,246,0.15)';
                tfDirectionBadge.style.color = '#a78bfa';
                tfDirectionBadge.style.borderColor = 'rgba(139,92,246,0.3)';
            }
            if (tfHeadSubText) {
                tfHeadSubText.textContent = `Transfer ${transferUnit} from Main Wallet to Trading Wallet.`;
            }

            if (tfFromName) { tfFromName.textContent = 'Main Wallet'; tfFromName.style.display = 'inline-block'; }
            if (tfFromBadge) { tfFromBadge.textContent = transferUnit; tfFromBadge.style.display = 'inline-block'; }
            if (tfFromBalance) tfFromBalance.textContent = mainBalFormatted;
            if (tfToName) tfToName.style.display = 'none';
            if (tfToBadge) tfToBadge.style.display = 'none';
            if (tfToBalance) tfToBalance.textContent = formattedSubBalance;

        } else { // trading_to_main
            if (tfFromTitle) tfFromTitle.textContent = 'Trading Wallet';
            if (tfFromBal) tfFromBal.textContent = formattedSubBalance;
            if (tfFromIconBox) {
                tfFromIconBox.className = 'w-8 h-8 rounded-xl bg-cyan-600/15 border border-cyan-500/30 flex items-center justify-center text-cyan-400 flex-shrink-0';
                tfFromIconBox.innerHTML = chartSvg;
            }
            if (tfToTitle) tfToTitle.textContent = 'Main Wallet';
            if (tfToBal) tfToBal.textContent = mainBalFormatted;
            if (tfToIconBox) {
                tfToIconBox.className = 'w-8 h-8 rounded-xl bg-purple-600/15 border border-purple-500/30 flex items-center justify-center text-purple-400 flex-shrink-0';
                tfToIconBox.innerHTML = walletSvg;
            }
            maxValFormatted = formattedSubBalance;

            if (tfDirectionBadge) {
                tfDirectionBadge.textContent = 'Trading → Main';
                tfDirectionBadge.style.background = 'rgba(245,158,11,0.15)';
                tfDirectionBadge.style.color = '#f59e0b';
                tfDirectionBadge.style.borderColor = 'rgba(245,158,11,0.3)';
            }
            if (tfHeadSubText) {
                tfHeadSubText.textContent = `Transfer ${transferUnit} from Trading Wallet to Main Wallet.`;
            }

            if (tfFromName) tfFromName.style.display = 'none';
            if (tfFromBadge) tfFromBadge.style.display = 'none';
            if (tfFromBalance) tfFromBalance.textContent = formattedSubBalance;
            if (tfToName) { tfToName.textContent = 'Main Wallet'; tfToName.style.display = 'inline-block'; }
            if (tfToBadge) { tfToBadge.textContent = transferUnit; tfToBadge.style.display = 'inline-block'; }
            if (tfToBalance) tfToBalance.textContent = mainBalFormatted;
        }

        if (tfMaxAvailVal) tfMaxAvailVal.textContent = maxValFormatted;
        if (tfAmountLabel) tfAmountLabel.textContent = isCentTransfer ? 'Amount (USC)' : (isINR ? 'Amount (INR)' : 'Amount (USDT)');
        if (tfAmountSuffix) tfAmountSuffix.textContent = isCentTransfer ? 'USC' : (isINR ? '₹' : '$');
        if (tfInput) tfInput.placeholder = isCentTransfer ? 'e.g. 1000 USC' : '0.00';

        if (isINR) {
            if (tfInrRateBar) tfInrRateBar.style.display = 'flex';
            if (tfInrRateVal) tfInrRateVal.textContent = `1 USDT = ₹${activeRate.toFixed(2)}`;
            if (tfConvBox) tfConvBox.style.display = 'block';

            if (direction === 'main_to_trading') {
                if (tfConvFromLabel) tfConvFromLabel.textContent = 'INR Main Wallet Deduction:';
                if (tfConvFromVal) tfConvFromVal.textContent = formatInr(val);
                if (tfConvToLabel) tfConvToLabel.textContent = 'Trading Account Credit (USDT):';
                const creditedUsd = val > 0 && activeRate > 0 ? (val / activeRate) : 0;
                if (tfConvToVal) tfConvToVal.textContent = '$' + creditedUsd.toFixed(2) + ' USDT';
            } else {
                if (tfConvFromLabel) tfConvFromLabel.textContent = 'Trading Account Deduction (USDT):';
                if (tfConvFromVal) tfConvFromVal.textContent = '$' + val.toFixed(2) + ' USDT';
                if (tfConvToLabel) tfConvToLabel.textContent = 'INR Main Wallet Credit:';
                const creditedInr = val > 0 ? (val * activeRate) : 0;
                if (tfConvToVal) tfConvToVal.textContent = formatInr(creditedInr);
            }

            if (tfRateCalloutBox) {
                tfRateCalloutBox.style.display = 'flex';
                if (tfRateTitle) tfRateTitle.textContent = 'Live Rate (Set by Admin)';
                if (tfRateEq) tfRateEq.textContent = `1 USDT = ₹ ${activeRate.toFixed(2)} INR`;
                if (tfRateSub) {
                    if (direction === 'main_to_trading') {
                        const creditedUsd = val > 0 ? (val / activeRate) : 0;
                        tfRateSub.textContent = `Trading Account Credit: $${creditedUsd.toFixed(2)} USDT`;
                    } else {
                        const inrCredit = val > 0 ? (val * activeRate) : 0;
                        tfRateSub.textContent = `INR Main Wallet Credit: ${formatInr(inrCredit)}`;
                    }
                }
            }
        } else {
            if (tfInrRateBar) tfInrRateBar.style.display = 'none';
            if (tfConvBox) tfConvBox.style.display = 'none';
            if (tfRateCalloutBox) tfRateCalloutBox.style.display = 'none';
        }
        return;
    }

    if (!isINR || !convBox || action === 'withdraw' || document.getElementById('futuresWithdrawUiContainer')?.hidden === false) {
        if (convBox) convBox.style.display = 'none';
        if (amountLabel && isTransfer) amountLabel.textContent = 'Amount (USDT)';
        return;
    }

    convBox.style.display = 'block';
    const dpInrInput = document.getElementById('dpInrAmountInput');
    const val = Number(normalizeNumberString(dpInrInput?.value || amountInput?.value || 0));

    if (action === 'deposit') {
        if (fromLabel) fromLabel.textContent = 'You Deposit:';
        if (fromVal) fromVal.textContent = formatInr(val);
        if (toLabel) toLabel.textContent = 'Wallet Credit:';
        if (toVal) toVal.textContent = '$' + (val > 0 ? (val / buyRate) : 0).toFixed(2) + ' USD';
    } else if (action === 'withdraw') {
        if (fromLabel) fromLabel.textContent = 'You Withdraw:';
        if (fromVal) fromVal.textContent = formatInr(val);
        if (toLabel) toLabel.textContent = 'Main Deducted:';
        if (toVal) toVal.textContent = '$' + (val > 0 ? (val / sellRate) : 0).toFixed(4) + ' USD';
    }
}

window.addEventListener('kryvex_currency_changed', (e) => {
    const cur = (e.detail?.currency || window.getActiveCurrency ? window.getActiveCurrency() : 'USD') === 'INR' ? 'inr' : 'usdt';
    futuresActiveModalTab = cur;
    updateFuturesLiveModalCalc();
});

function closeWalletActionModal() {
    const modal = document.getElementById('futuresWalletModal');

    if (!modal) {
        return;
    }

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
}

function normalizeWalletNetworks(value) {
    const rows = Array.isArray(value) ? value : Object.values(value || {});
    return rows.map((item) => ({
        network: String(item.network || item.code || '').toUpperCase(),
        label: item.label || item.name || item.network || item.code || '',
        address: item.address || item.deposit_address || '',
        minimum_deposit: Number(item.minimum_deposit || item.min_deposit || 0),
        rule: item.rule || '',
    })).filter((item) => item.network);
}

function renderFuturesWalletNetworks() {
    const select = document.getElementById('walletNetworkSelect');
    if (!select) return;
    const previous = select.value;
    select.innerHTML = state.depositNetworks.length
        ? state.depositNetworks.map((network) => `<option value="${escapeHtml(network.network)}">${escapeHtml(network.label || network.network)}</option>`).join('')
        : '<option value="">No network configured</option>';
    if (previous && state.depositNetworks.some((item) => item.network === previous)) select.value = previous;
}

function renderFuturesDepositUi() {
    const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
    const isINR = (rawCur === 'INR' || futuresActiveModalTab === 'inr');

    const dpHeadTitle = document.getElementById('dpHeadTitle');
    const dpHeadSub = document.getElementById('dpHeadSub');
    const dpCurIcon = document.getElementById('dpCurIcon');
    const dpCurName = document.getElementById('dpCurName');
    const dpCurBadge = document.getElementById('dpCurBadge');
    const dpUsdSection = document.getElementById('dpUsdSection');
    const dpInrSection = document.getElementById('dpInrSection');
    const inlineAccSelect = document.getElementById('depositAccountSelectInline');
    const subAccBal = document.getElementById('depositSubAccBalance');
    const buyRate = futuresInrState.usdtInrBuyRate || futuresInrState.usdtInrRate || 83.00;

    const mainAccBalEl = document.getElementById('depositMainWalletBalance');

    // Populate sub-account inline dropdown if present
    if (inlineAccSelect) {
        let html = '';
        const accounts = tradingAccountsState.accounts || [];

        if (accounts.length > 0) {
            accounts.forEach(acc => {
                html += `<option value="${acc.account_uid}">${escapeHtml(acc.account_name || 'Trading Account')}</option>`;
            });
        } else {
            html = '<option value="main">Main Account</option>';
        }
        inlineAccSelect.innerHTML = html;

        const activeUid = tradingAccountsState.activeAccountUid || accounts[0]?.account_uid || 'main';
        if (Array.from(inlineAccSelect.options).some(opt => opt.value === activeUid)) {
            inlineAccSelect.value = activeUid;
        } else if (inlineAccSelect.options.length > 0) {
            inlineAccSelect.value = inlineAccSelect.options[0].value;
        }

        const selectedUid = inlineAccSelect.value;
        const selectedSubAcc = accounts.find(a => a.account_uid === selectedUid);
        const subBal = getFuturesAccountAvailable(selectedSubAcc);
        if (subAccBal) subAccBal.textContent = formatUsdt(subBal.usdt);
    }

    const mainBal = getFuturesMainBalance();
    if (mainAccBalEl) {
        mainAccBalEl.textContent = mainBal.formatted;
    }

    if (isINR) {
        if (dpHeadTitle) dpHeadTitle.textContent = 'Deposit INR (₹)';
        if (dpHeadSub) dpHeadSub.textContent = 'Transfer INR via UPI / NetBanking & submit UTR / Ref No.';
        if (dpCurIcon) {
            dpCurIcon.innerHTML = `<div style="width:42px;height:42px;border-radius:12px;background:rgba(34,197,94,0.15);color:#22c55e;display:flex;align-items:center;justify-content:center;font-size:1.3rem;font-weight:800;">₹</div>`;
        }
        if (dpCurName) dpCurName.textContent = 'INR (Indian Rupee)';
        if (dpCurBadge) dpCurBadge.textContent = 'INR';

        if (dpUsdSection) dpUsdSection.style.display = 'none';
        if (dpInrSection) dpInrSection.style.display = 'block';

        renderFuturesDepositInrBankDetails();

        const notesList = document.getElementById('dpNotesList');
        if (notesList) {
            notesList.innerHTML = `
                <li>• Minimum deposit: ₹ 100 INR</li>
                <li>• Transfer funds via UPI/IMPS/NEFT.</li>
                <li>• Enter your 12-digit UTR/Transaction Reference No. accurately for fast approval.</li>
            `;
        }
    } else {
        if (dpHeadTitle) dpHeadTitle.textContent = 'Deposit USD';
        if (dpHeadSub) dpHeadSub.textContent = 'Deposit USDT to your account using the details below.';
        if (dpCurIcon) {
            dpCurIcon.innerHTML = `<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="12" fill="#26A17B"/><path d="M12.8 12.2v-.8h3.7V9.5H7.5v1.9h3.7v.8c-2.8.2-4.9.7-4.9 1.3 0 .7 2.1 1.2 4.9 1.3v3.4h1.6v-3.4c2.8-.1 4.9-.6 4.9-1.3 0-.6-2.1-1.1-4.9-1.3zm0 1.8c-2.1 0-3.9-.3-4.1-.7.2-.4 2-.7 4.1-.7s3.9.3 4.1.7c-.2.4-2 .7-4.1.7z" fill="#FFF"/></svg>`;
        }
        if (dpCurName) dpCurName.textContent = 'USDT (USD)';
        if (dpCurBadge) dpCurBadge.textContent = 'USD';

        if (dpUsdSection) dpUsdSection.style.display = 'block';
        if (dpInrSection) dpInrSection.style.display = 'none';

        const grid = document.getElementById('depositNetworkGrid');
        const selectStd = document.getElementById('walletNetworkSelect');

        if (grid) {
            const allowedNetworks = ['TRC20', 'ERC20', 'BEP20'];
            let displayNetworks = (state.depositNetworks || []).filter(net => allowedNetworks.includes(String(net.network || '').toUpperCase()));

            allowedNetworks.forEach(netCode => {
                if (!displayNetworks.some(n => String(n.network || '').toUpperCase() === netCode)) {
                    const matchedObj = (state.depositNetworks || []).find(n => String(n.network || '').toUpperCase() === netCode);
                    displayNetworks.push({
                        network: netCode,
                        label: netCode === 'TRC20' ? 'Tron Network' : netCode === 'ERC20' ? 'Ethereum Network' : 'BNB Smart Chain',
                        address: matchedObj?.address || state.depositNetworks?.[0]?.address || 'TQ9a1xE7g2Y4k8UD3fJ6w5a9sZb8nm3h7qR',
                        minimum_deposit: 1,
                        rule: 'Use only selected network.'
                    });
                }
            });

            displayNetworks.sort((a, b) => allowedNetworks.indexOf(String(a.network).toUpperCase()) - allowedNetworks.indexOf(String(b.network).toUpperCase()));

            const currentNet = selectStd?.value || displayNetworks[0]?.network;
            const networkIcons = {
                'TRC20': `<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="12" fill="#26A17B"/><path d="M12.8 12.2v-.8h3.7V9.5H7.5v1.9h3.7v.8c-2.8.2-4.9.7-4.9 1.3 0 .7 2.1 1.2 4.9 1.3v3.4h1.6v-3.4c2.8-.1 4.9-.6 4.9-1.3 0-.6-2.1-1.1-4.9-1.3zm0 1.8c-2.1 0-3.9-.3-4.1-.7.2-.4 2-.7 4.1-.7s3.9.3 4.1.7c-.2.4-2 .7-4.1.7z" fill="#FFF"/></svg>`,
                'ERC20': `<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><polygon points="12,2 4,14 12,18 20,14" fill="#627EEA"/><polygon points="12,22 4,14 12,18 20,14" fill="#3C3C3D"/></svg>`,
                'BEP20': `<svg width="22" height="22" viewBox="0 0 24 24" fill="none"><rect width="24" height="24" rx="6" fill="#F3BA2F"/><path d="M12 4.5l2.6 2.6-4.5 4.5-2.6-2.6L12 4.5zm0 15l-2.6-2.6 4.5-4.5 2.6 2.6L12 19.5zM4.5 12l2.6-2.6 4.5 4.5-2.6 2.6L4.5 12zm15 0l-2.6 2.6-4.5-4.5 2.6-2.6 4.5 4.5z" fill="#FFF"/></svg>`,
            };
            const subTitles = {
                'TRC20': 'Tron Network',
                'ERC20': 'Ethereum Network',
                'BEP20': 'BNB Smart Chain',
            };
            let gridHtml = '';
            displayNetworks.forEach(net => {
                const codeUpper = String(net.network || '').toUpperCase();
                const isAct = (codeUpper === String(currentNet || '').toUpperCase());
                const iconSvg = networkIcons[codeUpper] || `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#8B5CF6" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="4"/></svg>`;
                const sub = subTitles[codeUpper] || (net.label || 'Crypto Network');
                gridHtml += `
                    <div class="deposit-net-card ${isAct ? 'is-active' : ''}" data-network="${escapeHtml(codeUpper)}">
                        ${isAct ? '<div class="deposit-net-card-check">✓</div>' : ''}
                        <div class="deposit-net-icon">${iconSvg}</div>
                        <div class="deposit-net-info">
                            <strong>${escapeHtml(codeUpper)}</strong>
                            <span>${escapeHtml(sub)}</span>
                        </div>
                    </div>
                `;
            });
            grid.innerHTML = gridHtml;
        }
        updateFuturesDepositAddressDetails();
    }

    const dpRateBox = document.getElementById('dpRateBox');
    if (dpRateBox) {
        dpRateBox.style.display = isINR ? 'flex' : 'none';
    }

    const liveRateEq = document.getElementById('dpLiveRateEq');
    const liveRateTime = document.getElementById('dpLiveRateTime');
    if (liveRateEq) liveRateEq.textContent = `1 USD = ₹ ${buyRate.toFixed(2)} INR`;
    if (liveRateTime) {
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        liveRateTime.innerHTML = `${timeStr} <span class="dp-green-dot">●</span>`;
    }
}

function renderFuturesDepositInrBankDetails() {
    const container = document.getElementById('dpInrBankDetailsContainer');
    if (!container) return;

    let d = futuresInrState.adminBankDetails;
    if (!d) {
        try {
            const cached = JSON.parse(localStorage.getItem('kryvex_inr_state_cache') || '{}');
            d = cached?.adminBankDetails || null;
            if (d && !futuresInrState.adminBankDetails) futuresInrState.adminBankDetails = d;
        } catch (e) {}
    }

    if (!d) {
        container.innerHTML = '<span style="color:#f59e0b;">Admin bank details not configured yet.</span>';
        return;
    }

    let html = '';
    if (d.bank_name) {
        html += `<div class="dp-bank-row"><span class="dp-bank-label">Bank Name</span><span class="dp-bank-value">${escapeHtml(d.bank_name)}</span></div>`;
    }
    if (d.account_name) {
        html += `<div class="dp-bank-row"><span class="dp-bank-label">Account Holder</span><span class="dp-bank-value">${escapeHtml(d.account_name)}</span></div>`;
    }
    if (d.account_number) {
        html += `
            <div class="dp-bank-row">
                <span class="dp-bank-label">Account Number</span>
                <div class="dp-bank-val-wrap">
                    <span class="dp-bank-value">${escapeHtml(d.account_number)}</span>
                    <button type="button" class="dp-bank-copy-btn" onclick="copyTextToClipboard('${escapeHtml(d.account_number)}');showFuturesToast('Account Number copied.','success');">Copy</button>
                </div>
            </div>
        `;
    }
    if (d.ifsc) {
        html += `<div class="dp-bank-row"><span class="dp-bank-label">IFSC Code</span><span class="dp-bank-value">${escapeHtml(d.ifsc)}</span></div>`;
    }
    if (d.upi_id) {
        html += `
            <div class="dp-bank-row">
                <span class="dp-bank-label">UPI ID</span>
                <div class="dp-bank-val-wrap">
                    <span class="dp-bank-value" style="color:#a7f3d0;">${escapeHtml(d.upi_id)}</span>
                    <button type="button" class="dp-bank-copy-btn" onclick="copyTextToClipboard('${escapeHtml(d.upi_id)}');showFuturesToast('UPI ID copied.','success');">Copy</button>
                </div>
            </div>
        `;
    }

    container.innerHTML = html || '<span style="color:#f59e0b;">Admin bank details not configured yet.</span>';
}

function updateFuturesDepositAddressDetails() {
    const selectStd = document.getElementById('walletNetworkSelect');
    const netCode = selectStd?.value || state.depositNetworks[0]?.network || '';
    const network = getFuturesWalletNetwork(netCode);

    const addrTitle = document.getElementById('depositAddressTitleLabel');
    const addrText = document.getElementById('dpDepositAddressText');
    const qrImg = document.getElementById('dpQrImage');
    const explorerLink = document.getElementById('dpExplorerLink');
    const noteMin = document.getElementById('dpNoteMinDeposit');
    const shieldText = document.getElementById('dpFooterShieldText');
    const liveRateEq = document.getElementById('dpLiveRateEq');
    const liveRateTime = document.getElementById('dpLiveRateTime');

    const address = network?.address || 'No deposit address available.';
    const netLabel = network?.network || 'TRC20';
    const minDep = network?.minimum_deposit || 100;

    if (addrTitle) addrTitle.textContent = `USDT Deposit Address (${netLabel})`;
    if (addrText) addrText.textContent = address;
    if (noteMin) noteMin.textContent = `• Minimum deposit: $${minDep} (or equivalent)`;
    if (shieldText) shieldText.textContent = `Send only USDT to this address via ${netLabel} network.`;

    const notesList = document.getElementById('dpNotesList');
    if (notesList) {
        notesList.innerHTML = `
            <li>• Minimum deposit: $${minDep} (or equivalent)</li>
            <li>• Send only USDT to this ${escapeHtml(netLabel)} address.</li>
            <li>• Ensure the network matches (${escapeHtml(netLabel)}).</li>
            <li>• Deposits will be credited after network confirmation.</li>
        `;
    }

    if (qrImg && address && address !== 'No deposit address available.') {
        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${encodeURIComponent(address)}`;
    }

    if (explorerLink) {
        if (netLabel === 'TRC20') explorerLink.href = `https://tronscan.org/#/address/${address}`;
        else if (netLabel === 'ERC20') explorerLink.href = `https://etherscan.org/address/${address}`;
        else if (netLabel === 'BEP20') explorerLink.href = `https://bscscan.com/address/${address}`;
        else explorerLink.href = '#';
    }

    const buyRate = futuresInrState.usdtInrBuyRate || futuresInrState.usdtInrRate || 83.00;
    if (liveRateEq) liveRateEq.textContent = `1 USDT = ₹ ${buyRate.toFixed(2)} INR`;
    if (liveRateTime) {
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        liveRateTime.innerHTML = `${timeStr} <span class="dp-green-dot">●</span>`;
    }
}

function getFuturesWalletNetwork(networkCode) {
    const code = String(networkCode || '').toUpperCase();
    return state.depositNetworks.find((item) => item.network === code) || null;
}

function renderFuturesWalletNetworkDetails() {
    const action = document.getElementById('walletActionType')?.value || 'deposit';
    const network = getFuturesWalletNetwork(document.getElementById('walletNetworkSelect')?.value);
    setText('walletDepositAddressText', network?.address || 'Select network to view address.');
    setText('walletDepositAddressRule', network?.address ? `Minimum: ${formatUsdtAsset(network.minimum_deposit || 0)} • ${network.rule || 'Use only selected network.'}` : 'Only send funds on selected network.');
    const withdrawInput = document.getElementById('walletWithdrawAddressInput');
    if (withdrawInput && action === 'withdraw' && network) {
        withdrawInput.value = state.withdrawalAddresses?.[network.network] || '';
        withdrawInput.placeholder = network.rule || 'Enter your wallet address';
    }
    setText('walletWithdrawAddressRule', network?.rule || 'Select network to view address rule.');
}

async function copyTextToClipboard(text) {
    if (navigator.clipboard?.writeText) { await navigator.clipboard.writeText(text); return; }
    const input = document.createElement('textarea'); input.value = text; document.body.appendChild(input); input.select(); document.execCommand('copy'); input.remove();
}

function calculateFuturesWithdrawalPreview(amount) {
    const charges = state.wallet_meta?.withdrawal_charges || {};
    const feeType = charges.withdrawal_fee_type || 'percentage';
    const platformType = charges.platform_charge_type || 'fixed';
    const feeValue = Number(charges.withdrawal_fee_value || 0);
    const platformValue = Number(charges.platform_charge_value || 0);
    const withdrawFee = feeType === 'percentage' ? amount * (feeValue / 100) : feeValue;
    const platformCharge = platformType === 'percentage' ? amount * (platformValue / 100) : platformValue;
    const totalCharge = withdrawFee + platformCharge;

    return {
        withdrawFee: Math.round(withdrawFee * 10000) / 10000,
        platformCharge: Math.round(platformCharge * 10000) / 10000,
        totalCharge: Math.round(totalCharge * 10000) / 10000,
        netAmount: Math.max(Math.round((amount - totalCharge) * 10000) / 10000, 0),
    };
}

function updateFuturesWalletWithdrawPreview() {
    const action = document.getElementById('walletActionType')?.value || 'deposit';
    const preview = document.getElementById('futuresWalletWithdrawPreview');
    const convBox = document.getElementById('futuresConversionBox');
    const amount = Number(normalizeNumberString(document.getElementById('walletActionAmount')?.value || 0));

    if (preview) {
        preview.hidden = true;
        preview.style.display = 'none';
    }

    if (!preview) return;

    const isWithdraw = action === 'withdraw';

    if (convBox) {
        convBox.style.display = 'none';
    }

    if (!isWithdraw) return;

    const isINR = futuresActiveModalTab === 'inr';
    const rate = futuresInrState.usdtInrRate || 1;
    const chargesMeta = state.wallet_meta?.withdrawal_charges || {};

    const feeType = chargesMeta.withdrawal_fee_type || 'percentage';
    const platformType = chargesMeta.platform_charge_type || 'fixed';
    const feeValue = Number(chargesMeta.withdrawal_fee_value || 0);
    const platformValue = Number(chargesMeta.platform_charge_value || 0);

    const usdtRow = document.getElementById('futuresWithdrawPreviewUsdtRow');

    if (isINR) {
        const withdrawFee = feeType === 'percentage' ? (amount * (feeValue / 100)) : (feeValue * rate);
        const platformCharge = platformType === 'percentage' ? (amount * (platformValue / 100)) : (platformValue * rate);
        const totalCharge = withdrawFee + platformCharge;
        const netAmount = Math.max(amount - totalCharge, 0);
        const usdtDeducted = rate > 0 ? (amount / rate) : 0;

        setText('futuresWithdrawPreviewAmount', amount > 0 ? formatInr(amount) : '--');
        setText('futuresWithdrawPreviewFee', amount > 0 ? formatInr(withdrawFee) : '--');
        setText('futuresWithdrawPreviewPlatform', amount > 0 ? formatInr(platformCharge) : '--');
        setText('futuresWithdrawPreviewNet', amount > 0 ? formatInr(netAmount) : '--');

        if (usdtRow) {
            usdtRow.style.display = 'flex';
            setText('futuresWithdrawPreviewUsdt', amount > 0 ? ('$' + usdtDeducted.toFixed(4) + ' USD') : '--');
        }
    } else {
        const charges = calculateFuturesWithdrawalPreview(amount);
        setText('futuresWithdrawPreviewAmount', amount > 0 ? formatUsdtAsset(amount) : '--');
        setText('futuresWithdrawPreviewFee', amount > 0 ? formatUsdtAsset(charges.withdrawFee) : '--');
        setText('futuresWithdrawPreviewPlatform', amount > 0 ? formatUsdtAsset(charges.platformCharge) : '--');
        setText('futuresWithdrawPreviewNet', amount > 0 ? formatUsdtAsset(charges.netAmount) : '--');

        if (usdtRow) {
            usdtRow.style.display = 'none';
        }
    }
}

function populateSavedWithdrawalAddress(network) {
    const net = (network || '').toUpperCase();
    const wdAddressInput = document.getElementById('wdAddressInput');
    const stdAddressInput = document.getElementById('walletWithdrawAddressInput');
    if (!wdAddressInput) return;

    let addresses = state.withdrawalAddresses || {};
    let saved = addresses[net] || addresses[net.toLowerCase()] || '';

    if (!saved && state.wallet_meta && state.wallet_meta.withdrawal_addresses) {
        saved = state.wallet_meta.withdrawal_addresses[net] || state.wallet_meta.withdrawal_addresses[net.toLowerCase()] || '';
    }

    wdAddressInput.value = saved;
    if (stdAddressInput) {
        stdAddressInput.value = saved;
    }
}

function renderFuturesWithdrawUi() {
    const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
    const isINR = (rawCur === 'INR' || futuresActiveModalTab === 'inr');

    const wdAvailBalanceText = document.getElementById('wdAvailableBalance') || document.getElementById('wdAvailBalanceText');
    const wdBalSublabel = document.getElementById('wdBalSublabel');
    const wdCurrencyIcon = document.getElementById('wdCurrencyIcon');
    const wdCurrencyLabel = document.getElementById('wdCurrencyLabel');
    const wdUnitSuffix = document.getElementById('wdUnitSuffix');
    const wdAddressInput = document.getElementById('wdAddressInput');
    const wdNetSelectCustom = document.getElementById('wdNetworkSelectCustom');
    const wdInrBankGroup = document.getElementById('wdInrBankGroup');
    const wdAddressFieldGroup = document.getElementById('wdAddressFieldGroup');
    const wdNetworkFieldGroup = document.getElementById('wdNetworkFieldGroup');
    const wdAmountInput = document.getElementById('wdAmountInput');
    const wdWarningText = document.getElementById('wdWarningText');

    if (wdAmountInput) wdAmountInput.value = '';

    const mainBalUsdt = Number(state.wallet?.demo_main_balance ?? state.wallet?.main_balance ?? 0);
    const mainBalInr = Number(state.wallet?.demo_main_balance_inr ?? state.wallet?.main_balance_inr ?? 0);

    const wdRateBar = document.getElementById('wdRateBar');
    const wdRateVal = document.getElementById('wdRateVal');
    const topRateBar = document.getElementById('futuresRateBar');
    const baseRate = futuresInrState.usdtInrRate || 95.00;
    const sellRate = futuresInrState.usdtInrSellRate || baseRate;

    if (topRateBar) topRateBar.style.display = 'none';

    if (isINR) {
        if (wdRateBar) {
            wdRateBar.style.display = 'block';
            if (wdRateVal) wdRateVal.textContent = `1 USD = ₹${sellRate.toFixed(2)} (Admin Buy Rate)`;
        }
        if (wdAvailBalanceText) wdAvailBalanceText.textContent = formatInr(mainBalInr);
        if (wdBalSublabel) wdBalSublabel.textContent = 'Main Wallet · INR';
        if (wdCurrencyIcon) wdCurrencyIcon.textContent = '₹';
        if (wdCurrencyLabel) wdCurrencyLabel.textContent = 'INR (Indian Rupee)';
        if (wdUnitSuffix) wdUnitSuffix.textContent = 'INR';
        if (wdInrBankGroup) wdInrBankGroup.style.display = 'block';
        if (wdAddressFieldGroup) wdAddressFieldGroup.style.display = 'none';
        if (wdNetworkFieldGroup) wdNetworkFieldGroup.style.display = 'none';
        if (wdWarningText) wdWarningText.textContent = 'Withdrawal will be credited directly to your saved bank account.';

        loadUserSavedBankDetails().then((b) => {
            const banner = document.getElementById('wdSavedBankBanner');
            if (!banner) return;
            if (b) {
                banner.innerHTML = `
                    <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); padding: 12px 14px; border-radius: 10px; font-size: 0.84rem;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                            <span style="color: #10b981; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                                🏦 Withdrawal Bank Account
                            </span>
                            <a href="/settings?tab=bank" style="color: #60a5fa; text-decoration: underline; font-size: 0.76rem; font-weight: 600;">Edit ⚙️</a>
                        </div>
                        <div style="color: #fff; font-weight: 600;">${escapeHtml(b.account_name || '')} • ${escapeHtml(b.bank_name || '')} (${escapeHtml(b.account_type || 'Savings')})</div>
                        <div style="color: var(--kv-muted); font-size: 0.8rem; margin-top: 2px; font-family: monospace;">A/C: ${escapeHtml(b.account_number || '')} | IFSC: ${escapeHtml(b.ifsc_code || '')}</div>
                        ${b.upi_id ? `<div style="color: #a7f3d0; font-size: 0.78rem; margin-top: 2px;">UPI: ${escapeHtml(b.upi_id)}</div>` : ''}
                    </div>
                `;
            } else {
                banner.innerHTML = `
                    <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); padding: 12px 14px; border-radius: 10px; font-size: 0.84rem; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;">
                        <div>
                            <strong style="color: #ef4444; display: block;">⚠️ Bank Details Missing</strong>
                            <span style="color: #fca5a5; font-size: 0.78rem;">Please save your bank account details first in Settings to withdraw.</span>
                        </div>
                        <a href="/settings?tab=bank" class="kv-btn kv-btn-primary" style="padding: 6px 12px; font-size: 0.78rem; text-decoration: none; border-radius: 8px;">
                            Add Details ➔
                        </a>
                    </div>
                `;
            }
        });
    } else {
        if (wdRateBar) wdRateBar.style.display = 'none';
        if (wdAvailBalanceText) wdAvailBalanceText.textContent = formatUsdt(mainBalUsdt);
        if (wdBalSublabel) wdBalSublabel.textContent = 'Main Wallet · USD';
        if (wdCurrencyIcon) wdCurrencyIcon.textContent = '₮';
        if (wdCurrencyLabel) wdCurrencyLabel.textContent = 'USDT (Tether USD)';
        if (wdUnitSuffix) wdUnitSuffix.textContent = 'USDT';
        if (wdInrBankGroup) wdInrBankGroup.style.display = 'none';
        if (wdAddressFieldGroup) wdAddressFieldGroup.style.display = 'flex';
        if (wdNetworkFieldGroup) wdNetworkFieldGroup.style.display = 'flex';
        if (wdWarningText) wdWarningText.textContent = 'Make sure the address and network are correct. Transactions cannot be canceled.';

        if (wdNetSelectCustom) {
            const stdSelect = document.getElementById('walletNetworkSelect');
            renderFuturesWalletNetworks();
            if (stdSelect && stdSelect.options.length > 0) {
                wdNetSelectCustom.innerHTML = stdSelect.innerHTML;
                if (stdSelect.value) wdNetSelectCustom.value = stdSelect.value;
            }
            const textEl = document.getElementById('wdSelectedNetworkText');
            if (textEl && wdNetSelectCustom.selectedOptions[0]) {
                textEl.textContent = wdNetSelectCustom.selectedOptions[0].text;
            }
            const iconEl = document.getElementById('wdNetworkIcon');
            if (iconEl) {
                const netVal = (wdNetSelectCustom.value || '').toUpperCase();
                if (netVal.includes('TRC') || netVal.includes('TRON')) iconEl.textContent = 'T';
                else if (netVal.includes('ERC') || netVal.includes('ETH')) iconEl.textContent = 'E';
                else if (netVal.includes('BEP') || netVal.includes('BSC') || netVal.includes('BNB')) iconEl.textContent = 'B';
                else iconEl.textContent = netVal.charAt(0) || '₮';
            }
            populateSavedWithdrawalAddress(wdNetSelectCustom.value);
        }
    }

    updateFuturesWithdrawUiPreview();
}

function updateFuturesWithdrawUiPreview() {
    const rawVal = document.getElementById('wdAmountInput')?.value || '';
    const amount = Number(normalizeNumberString(rawVal));

    const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
    const isINR = (rawCur === 'INR' || futuresActiveModalTab === 'inr');
    const rate = futuresInrState.usdtInrRate || 1;

    const stdInput = document.getElementById('walletActionAmount');
    if (stdInput) stdInput.value = rawVal;

    const stdAddressInput = document.getElementById('walletWithdrawAddressInput');
    const wdAddressInput = document.getElementById('wdAddressInput');
    if (stdAddressInput && wdAddressInput) {
        stdAddressInput.value = wdAddressInput.value;
    }

    const recValEl = document.getElementById('wdReceiveVal');
    const breakdownAmtEl = document.getElementById('wdBreakdownAmount');
    const breakdownFeeEl = document.getElementById('wdBreakdownFee');
    const breakdownPlatformEl = document.getElementById('wdBreakdownPlatform');
    const convTextEl = document.getElementById('wdConversionText');

    if (isINR) {
        const chargesMeta = state.wallet_meta?.withdrawal_charges || {};
        const feeType = chargesMeta.withdrawal_fee_type || 'percentage';
        const platformType = chargesMeta.platform_charge_type || 'fixed';
        const feeValue = Number(chargesMeta.withdrawal_fee_value || 0);
        const platformValue = Number(chargesMeta.platform_charge_value || 0);

        const withdrawFee = feeType === 'percentage' ? (amount * (feeValue / 100)) : (feeValue * rate);
        const platformCharge = platformType === 'percentage' ? (amount * (platformValue / 100)) : (platformValue * rate);
        const totalCharge = withdrawFee + platformCharge;
        const netAmount = Math.max(amount - totalCharge, 0);

        if (amount > 0) {
            if (recValEl) recValEl.textContent = formatInr(netAmount);
            if (breakdownAmtEl) breakdownAmtEl.textContent = formatInr(amount);
            if (breakdownFeeEl) breakdownFeeEl.textContent = formatInr(withdrawFee);
            if (breakdownPlatformEl) breakdownPlatformEl.textContent = formatInr(platformCharge);
            if (convTextEl) {
                const usdtEq = rate > 0 ? (amount / rate) : 0;
                convTextEl.textContent = `≈ $${usdtEq.toFixed(2)} USD`;
            }
        } else {
            if (recValEl) recValEl.textContent = `₹0.00`;
            if (breakdownAmtEl) breakdownAmtEl.textContent = `₹0.00`;
            if (breakdownFeeEl) breakdownFeeEl.textContent = `₹0.00`;
            if (breakdownPlatformEl) breakdownPlatformEl.textContent = `₹0.00`;
            if (convTextEl) convTextEl.textContent = `≈ $0.00 USD`;
        }
    } else {
        const charges = calculateFuturesWithdrawalPreview(amount);

        if (amount > 0) {
            if (recValEl) recValEl.textContent = formatUsdtAsset(charges.netAmount);
            if (breakdownAmtEl) breakdownAmtEl.textContent = formatUsdtAsset(amount);
            if (breakdownFeeEl) breakdownFeeEl.textContent = formatUsdtAsset(charges.withdrawFee);
            if (breakdownPlatformEl) breakdownPlatformEl.textContent = formatUsdtAsset(charges.platformCharge);
            if (convTextEl) convTextEl.textContent = `≈ $${amount.toFixed(2)}`;
        } else {
            if (recValEl) recValEl.textContent = `$0.00`;
            if (breakdownAmtEl) breakdownAmtEl.textContent = `$0.00`;
            if (breakdownFeeEl) breakdownFeeEl.textContent = `$0.00`;
            if (breakdownPlatformEl) breakdownPlatformEl.textContent = `$0.00`;
            if (convTextEl) convTextEl.textContent = `≈ $0.00`;
        }
    }
}

let savedUserBankDetails = null;

async function loadUserSavedBankDetails() {
    const token = localStorage.getItem('kryvex_access_token');
    if (!token) return null;

    try {
        const res = await fetch('/api/user/bank-details', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        });
        const json = await res.json();
        if (json.success && json.data && json.data.bank_details) {
            savedUserBankDetails = json.data.bank_details;
            return savedUserBankDetails;
        }
    } catch (e) {
        console.warn('Failed to load user bank details:', e);
    }
    return null;
}

async function submitWalletAction(event) {
    event.preventDefault();

    if (!verifyKycAndCurrencyBeforeAction()) {
        return;
    }

    const action = document.getElementById('walletActionType')?.value || 'deposit';
    const amount = Number(normalizeNumberString(document.getElementById('walletActionAmount')?.value || 0));
    const direction = document.getElementById('walletTransferDirection')?.value || 'main_to_trading';
    const submit = document.getElementById('walletModalSubmit');
    const isCentTransfer = action === 'transfer' && isCentAccount(getFuturesTransferAccount());
    const isINR = futuresActiveModalTab === 'inr' && !isCentTransfer;
    const rate = futuresInrState.usdtInrRate || 1;

    if (!amount || amount <= 0) {
        showFuturesToast('Amount must be greater than zero.', 'error');
        return;
    }

    submit?.setAttribute('disabled', 'disabled');

    try {
        if (isINR) {
            if (action === 'deposit') {
                const orderRes = await authFetch('/api/wallet/razorpay/create-order', {
                    method: 'POST',
                    body: JSON.stringify({ amount_inr: amount }),
                });

                if (!orderRes || !orderRes.success) {
                    throw new Error(orderRes?.message || 'Failed to initialize Razorpay payment order.');
                }

                const RazorpayCtor = await loadRazorpaySdk();

                let user = {};
                try {
                    user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
                } catch (e) {}

                const options = {
                    key: orderRes.key_id,
                    amount: orderRes.amount_paise,
                    currency: orderRes.currency || 'INR',
                    name: 'Kryvex Exchange',
                    description: `INR Deposit ₹${amount}`,
                    order_id: orderRes.order_id,
                    prefill: {
                        name: orderRes.user?.name || user.name || '',
                        email: orderRes.user?.email || user.email || '',
                        contact: orderRes.user?.mobile || user.mobile || '',
                    },
                    theme: { color: '#2563eb' },
                    handler: async function (response) {
                        try {
                            const verifyRes = await authFetch('/api/wallet/razorpay/verify', {
                                method: 'POST',
                                body: JSON.stringify({
                                    razorpay_order_id: response.razorpay_order_id || orderRes.order_id,
                                    razorpay_payment_id: response.razorpay_payment_id || '',
                                    razorpay_signature: response.razorpay_signature || '',
                                    amount_inr: amount,
                                }),
                            });
                            if (verifyRes.success) {
                                showFuturesToast(verifyRes.message || 'Razorpay Payment Verified & Wallet Credited! 🎉', 'success');
                                closeWalletActionModal();
                                await loadProtectedFuturesData();
                            } else {
                                showFuturesToast(verifyRes.message || 'Payment verification failed.', 'error');
                            }
                        } catch (err) {
                            showFuturesToast(err.message || 'Payment verification failed.', 'error');
                        } finally {
                            submit?.removeAttribute('disabled');
                        }
                    },
                    modal: {
                        ondismiss: function () {
                            submit?.removeAttribute('disabled');
                            showFuturesToast('Razorpay payment cancelled.', 'warning');
                        },
                    },
                };

                const rzp = new RazorpayCtor(options);
                rzp.open();
                return;
            } else if (action === 'withdraw') {
                const payload = {
                    amount_inr: amount,
                    account_name: document.getElementById('futuresInrAccName')?.value?.trim() || (savedUserBankDetails?.account_name ?? ''),
                    bank_name: document.getElementById('futuresInrBankName')?.value?.trim() || (savedUserBankDetails?.bank_name ?? ''),
                    account_number: document.getElementById('futuresInrAccNo')?.value?.trim() || (savedUserBankDetails?.account_number ?? ''),
                    ifsc: document.getElementById('futuresInrIfsc')?.value?.trim() || (savedUserBankDetails?.ifsc_code ?? ''),
                    upi_id: document.getElementById('futuresInrUpi')?.value?.trim() || (savedUserBankDetails?.upi_id ?? null),
                };
                if (!payload.account_name && !payload.upi_id && !savedUserBankDetails) {
                    showFuturesToast('Please save bank details in Settings or enter them below.', 'error');
                    submit?.removeAttribute('disabled');
                    return;
                }
                const res = await authFetch('/api/wallet/inr-withdraw', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                showFuturesToast(res.message || 'INR withdrawal submitted.', 'success');
            } else { // transfer
                const selectedAccUid = document.getElementById('transferTradingAccountSelect')?.value 
                    || document.getElementById('walletTransferAccountSelectInline')?.value 
                    || document.getElementById('walletTransferAccountSelect')?.value 
                    || tradingAccountsState.activeAccountUid 
                    || localStorage.getItem('kryvex_active_account_uid');
                const selectedAcc = (tradingAccountsState.accounts || []).find(a => a.account_uid === selectedAccUid);
                const broker = selectedAcc?.broker_type || (window.getFuturesBrokerType ? window.getFuturesBrokerType() : 'kryvex');

                const res = await authFetch(`/api/wallet/transfer?broker_type=${broker}`, {
                    method: 'POST',
                    headers: { 'X-Broker-Type': broker, 'X-Account-UID': selectedAccUid },
                    body: JSON.stringify({ account_uid: selectedAccUid, direction, amount, currency: 'INR' }),
                });
                showFuturesToast(res.message || 'Transfer completed.', 'success');
            }
        } else {
            const endpoint = action === 'withdraw'
                ? '/api/wallet/withdraw'
                : action === 'transfer'
                    ? '/api/wallet/transfer'
                    : '/api/wallet/demo-credit';
            const network = document.getElementById('walletNetworkSelect')?.value || '';
            const wallet_address = document.getElementById('walletWithdrawAddressInput')?.value?.trim() || '';

            const selectedAccUid = document.getElementById('transferTradingAccountSelect')?.value 
                || document.getElementById('walletTransferAccountSelectInline')?.value 
                || document.getElementById('walletTransferAccountSelect')?.value 
                || tradingAccountsState.activeAccountUid 
                || localStorage.getItem('kryvex_active_account_uid');
            const selectedAcc = (tradingAccountsState.accounts || []).find(a => a.account_uid === selectedAccUid);
            const broker = selectedAcc?.broker_type || (window.getFuturesBrokerType ? window.getFuturesBrokerType() : 'kryvex');

            // Cent amounts are entered in USC; the backend stores cent balances in USD.
            const payload = action === 'transfer' 
                ? { account_uid: selectedAccUid, amount: isCentTransfer ? amount / 100 : amount, direction, currency: 'USDT' } 
                : action === 'withdraw' 
                    ? { amount, network, wallet_address } 
                    : { amount, network };

            const result = await authFetch(action === 'transfer' ? `/api/wallet/transfer?broker_type=${broker}` : endpoint, {
                method: 'POST',
                headers: action === 'transfer' ? { 'X-Broker-Type': broker, 'X-Account-UID': selectedAccUid } : {},
                body: JSON.stringify(payload),
            });
            showFuturesToast(result.message || (action === 'withdraw' ? 'Withdrawal request submitted.' : action === 'transfer' ? 'Transfer completed.' : 'Funds added successfully.'), 'success');
        }

        closeWalletActionModal();
        await loadTradingAccounts();
        await loadWalletBalance();
        await loadProtectedFuturesData();
        renderTradingAccountDropdown();
        renderTransferAccountSelect();
        updateLiveWalletMetrics();
        updateFuturesLiveModalCalc();
    } catch (error) {
        showFuturesToast(error.message || 'Wallet action failed.', 'error');
    } finally {
        submit?.removeAttribute('disabled');
    }
}

function askFuturesConfirm({ title, message, confirmText = 'Confirm', preferenceKey = '', skipLabel = '' }) {
    const modal = document.getElementById('futuresConfirmModal');
    const titleEl = document.getElementById('futuresConfirmTitle');
    const messageEl = document.getElementById('futuresConfirmMessage');
    const confirmBtn = document.getElementById('futuresConfirmOk');
    const cancelBtn = document.getElementById('futuresConfirmCancel');
    const preferenceRow = document.getElementById('futuresConfirmPreferenceRow');
    const preferenceCheckbox = document.getElementById('futuresConfirmSkip');
    const preferenceLabel = document.getElementById('futuresConfirmSkipLabel');

    if (localStorage.getItem('kryvex:futures:confirm:master') === '0') {
        return Promise.resolve(true);
    }

    if (preferenceKey && localStorage.getItem(preferenceKey) === '1') {
        return Promise.resolve(true);
    }

    if (!modal || !confirmBtn || !cancelBtn) {
        return Promise.resolve(false);
    }

    titleEl.textContent = title || 'Confirm action?';
    messageEl.textContent = message || 'Please confirm this action.';
    confirmBtn.textContent = confirmText;

    if (preferenceRow && preferenceCheckbox) {
        preferenceCheckbox.checked = false;
        preferenceRow.hidden = !preferenceKey;

        if (preferenceLabel) {
            preferenceLabel.textContent = skipLabel || 'Do not show this confirmation again for this action.';
        }
    }

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');

    return new Promise((resolve) => {
        const cleanup = (answer) => {
            if (answer && preferenceKey && preferenceCheckbox?.checked) {
                localStorage.setItem(preferenceKey, '1');
            }

            if (document.activeElement && modal.contains(document.activeElement)) {
                document.activeElement.blur();
            }

            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onBackdrop);
            resolve(answer);
        };

        const onConfirm = () => cleanup(true);
        const onCancel = () => cleanup(false);
        const onBackdrop = (event) => {
            if (event.target === modal) cleanup(false);
        };

        confirmBtn.addEventListener('click', onConfirm);
        cancelBtn.addEventListener('click', onCancel);
        modal.addEventListener('click', onBackdrop);
    });
}

function showFuturesToast(message, type = 'success') {
    if (typeof window.showToast === 'function') {
        window.showToast({
            type: type,
            title: type === 'success' ? 'Success' : type.charAt(0).toUpperCase() + type.slice(1),
            message: message
        });
    }
}

function setupPanelToggles() {
    const grid = document.getElementById('futuresTerminalGrid');
    const marketButton = document.getElementById('toggleMarketPanelButton');
    const orderButton = document.getElementById('toggleOrderPanelButton');

    if (!grid) {
        return;
    }

    if (marketButton) {
        marketButton.addEventListener('click', () => {
            grid.classList.toggle('is-market-hidden');
            marketButton.textContent = grid.classList.contains('is-market-hidden') ? 'Show Book' : 'Hide Book';
        });
    }

    if (orderButton) {
        orderButton.addEventListener('click', () => {
            grid.classList.toggle('is-order-hidden');
            orderButton.textContent = grid.classList.contains('is-order-hidden') ? 'Show Form' : 'Hide Form';
        });
    }
}

function setupMarketPanelTabs() {
    document.querySelectorAll('[data-market-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const target = button.dataset.marketTab;

            document.querySelectorAll('[data-market-tab]').forEach((item) => item.classList.remove('is-active'));
            document.querySelectorAll('.market-panel-section').forEach((panel) => panel.classList.remove('is-active'));

            button.classList.add('is-active');
            document.getElementById(target)?.classList.add('is-active');
        });
    });
}

function getTradingViewSymbol(symbol) {
    const sym = String(symbol || 'BTCUSDT').toUpperCase().replace(/[-/_ ]/g, '');
    if (sym === 'XAUUSDT' || sym === 'GOLDUSDT' || sym === 'XAUUSD' || sym === 'GOLD') {
        return 'BINANCE:XAUUSDT.P';
    }
    if (sym === 'PAXGUSDT' || sym === 'XAUTUSDT') {
        return 'BINANCE:PAXGUSDT.P';
    }
    if (sym === 'PEPEUSDT') {
        return 'BINANCE:1000PEPEUSDT.P';
    }
    return `BINANCE:${sym}.P`;
}

function renderTradingViewChart(force = false) {
    const container = document.getElementById('tradingViewChart');

    if (!container) {
        return;
    }

    const isLight = document.documentElement.getAttribute('data-theme') === 'light' || document.documentElement.classList.contains('theme-light');
    const chartTheme = isLight ? 'light' : 'dark';
    const chartBg = isLight ? '#ffffff' : '#070b11';
    const chartGrid = isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.06)';

    if (!force && currentChartSymbol === state.symbol && currentChartInterval === state.interval && currentChartTheme === chartTheme) {
        return;
    }

    currentChartSymbol = state.symbol;
    currentChartInterval = state.interval;
    currentChartTheme = chartTheme;

    container.innerHTML = `
        <div class="tradingview-widget-container" style="height:100%;width:100%">
            <div class="tradingview-widget-container__widget" style="height:100%;width:100%"></div>
        </div>
    `;

    const widgetContainer = container.querySelector('.tradingview-widget-container');

    const script = document.createElement('script');
    script.src = 'https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js';
    script.async = true;
    script.innerHTML = JSON.stringify({
        autosize: true,
        symbol: getTradingViewSymbol(state.symbol),
        interval: state.interval,
        timezone: 'Etc/UTC',
        theme: chartTheme,
        style: '1',
        locale: 'en',
        allow_symbol_change: false,
        calendar: false,
        support_host: 'https://www.tradingview.com',
        backgroundColor: chartBg,
        gridColor: chartGrid,
        hide_side_toolbar: false,
        hide_top_toolbar: false,
        hide_legend: false,
        save_image: false,
    });

    widgetContainer.appendChild(script);
}

window.addEventListener('kryvexThemeChanged', () => {
    renderTradingViewChart(true);
});

try {
    const themeMutationObserver = new MutationObserver(() => {
        const isLightNow = document.documentElement.getAttribute('data-theme') === 'light' || document.documentElement.classList.contains('theme-light');
        const themeStr = isLightNow ? 'light' : 'dark';
        if (themeStr !== currentChartTheme) {
            renderTradingViewChart(true);
        }
    });
    themeMutationObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'class'] });
} catch (e) {}

function setupIntervalButtons() {
    document.querySelectorAll('.futures-intervals button').forEach((button) => {
        button.addEventListener('click', () => {
            state.interval = button.dataset.interval || '1';

            document.querySelectorAll('.futures-intervals button').forEach((item) => {
                item.classList.remove('is-active');
            });

            button.classList.add('is-active');
            renderTradingViewChart();
        });
    });
}

function setupTabs() {
    document.querySelectorAll('.futures-tabs button').forEach((button) => {
        button.addEventListener('click', () => {
            const targetId = button.dataset.tabTarget;

            document.querySelectorAll('.futures-tabs button').forEach((item) => {
                item.classList.remove('is-active');
            });

            document.querySelectorAll('.futures-tab-panel').forEach((panel) => {
                panel.classList.remove('is-active');
            });

            button.classList.add('is-active');

            if (targetId) {
                document.getElementById(targetId)?.classList.add('is-active');
            }
        });
    });
}

function setupFuturesModeToggle() {
    document.querySelectorAll('#futuresOrderPanel [data-mode-toggle]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const mode = btn.getAttribute('data-mode-toggle');
            if (typeof window.setTradingMode === 'function') {
                window.setTradingMode(mode);
            } else {
                localStorage.setItem('kryvex_trading_mode', mode);
                window.dispatchEvent(new CustomEvent('tradingModeChanged', { detail: { mode } }));
            }
        });
    });
}

function setupOrderForm() {
    setupFuturesModeToggle();
    setupOrderSideTabs();
    setupOrderTypeTabs();
    setupOrderTypeFields();
    setupMarginButtons();
    setupLeverageModal();
    setupLeadingZeroNumberInputs();
    setupOrderPreviewSubmit();
    setupPlaceDemoOrder();
    setupFuturesEnterToPlaceOrder();
    syncOrderActionState();
}

function setupOrderTypeTabs() {
    const typeSelect = document.getElementById('orderType');

    document.querySelectorAll('.order-type-tabs button[data-order-type]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.order-type-tabs button[data-order-type]').forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            if (typeSelect) {
                typeSelect.value = button.dataset.orderType || 'market';
                typeSelect.dispatchEvent(new Event('change'));
            }
            updateOrderEstimate();
        });
    });
}


function setupTpSlToggle() {
    const toggle = document.getElementById('toggleTpSlFields');
    const row = document.getElementById('tpSlFieldsRow');

    if (!toggle || !row) return;

    const applyVisibility = () => {
        row.style.display = toggle.checked ? 'grid' : 'none';
    };

    toggle.addEventListener('change', applyVisibility);
    applyVisibility();
}

function setupFuturesEnterToPlaceOrder() {
    const form = document.getElementById('futuresOrderPreviewForm');
    const placeButton = document.getElementById('placeDemoOrderButton');

    if (!form || !placeButton) {
        return;
    }

    form.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') {
            return;
        }

        const target = event.target;

        if (target && target.tagName === 'TEXTAREA') {
            return;
        }

        event.preventDefault();
        target?.blur?.();
        placeButton.click();
    });
}

function setupOrderSideTabs() {
    const sideInput = document.getElementById('orderSide');

    document.querySelectorAll('.order-side-tabs button').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.order-side-tabs button').forEach((item) => {
                item.classList.remove('is-active');
            });

            button.classList.add('is-active');

            if (sideInput) {
                sideInput.value = button.dataset.side || 'buy';
            }
            syncOrderActionState();
        });
    });
}

function setupOrderTypeFields() {
    const typeSelect = document.getElementById('orderType');
    const priceField = document.getElementById('orderPriceField');
    const stopPriceField = document.getElementById('orderStopPriceField');

    if (!typeSelect || !priceField || !stopPriceField) {
        return;
    }

    const updateFields = () => {
        const type = typeSelect.value;

        priceField.style.display = ['limit', 'stop_limit'].includes(type) ? 'grid' : 'none';
        stopPriceField.style.display = ['stop_limit', 'stop_market'].includes(type) ? 'grid' : 'none';
    };

    typeSelect.addEventListener('change', updateFields);
    updateFields();
}

function setupMarginButtons() {
    const quantityInput = document.getElementById('orderQuantity');

    document.querySelectorAll('.order-percent-row button[data-qty]').forEach((button) => {
        button.addEventListener('click', () => {
            if (quantityInput) {
                quantityInput.value = button.dataset.qty || '';
                updateOrderEstimate();
            }
        });
    });

    document.querySelectorAll('.order-share-row button[data-share]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!quantityInput) return;

            const share = Number(button.dataset.share || 0);
            const leverage = Number(document.getElementById('orderLeverage')?.value || 1);
            const available = Number(state.wallet?.demo_available_balance || 0);
            const price = getCurrentLastPrice() || Number(document.getElementById('orderPrice')?.value || 0);

            if (!share || !available || !price) return;

            const spec = getFuturesSymbolLotSpec(state.symbol);
            const rawQty = (available * share * leverage) / price;
            if (rawQty > 0) {
                const prec = spec.prec !== undefined ? spec.prec : 4;
                const formatted = Number(rawQty.toFixed(prec));
                quantityInput.value = formatted >= spec.min ? formatted : spec.min;
            } else {
                quantityInput.value = '';
            }
            updateOrderEstimate();
        });
    });

    ['orderQuantity', 'orderLeverage', 'orderPrice', 'orderType', 'orderStopPrice', 'orderMarginMode'].forEach((id) => {
        const element = document.getElementById(id);

        if (element) {
            element.addEventListener('input', updateOrderEstimate);
            element.addEventListener('change', updateOrderEstimate);
        }
    });

    setupMarginModeToggle();
    updateOrderEstimate();
}



function syncMarginModeWithActivePosition() {
    const hiddenInput = document.getElementById('orderMarginMode');
    if (!hiddenInput) return;

    if (!state.symbolMarginModes) {
        try {
            state.symbolMarginModes = JSON.parse(localStorage.getItem('kryvex_symbol_margin_modes') || '{}') || {};
        } catch (e) {
            state.symbolMarginModes = {};
        }
    }

    const activeSymbolPos = (state.positions || []).find(p => p.symbol === state.symbol && p.status === 'open');
    if (activeSymbolPos) {
        const posMode = String(activeSymbolPos.margin_mode || 'isolated').toLowerCase();
        state.symbolMarginModes[state.symbol] = posMode;
        renderMarginMode(posMode, true);
    } else {
        const currentSavedMode = state.symbolMarginModes[state.symbol] || 'isolated';
        renderMarginMode(currentSavedMode, false);
    }
}

function renderMarginMode(mode, isLocked = false) {
    const btn = document.getElementById('btnToggleMarginMode');
    const hiddenInput = document.getElementById('orderMarginMode');
    const infoText = document.getElementById('marginModeInfoText');
    const btnText = document.getElementById('marginModeBtnText');

    if (!hiddenInput) return;

    const isCross = mode === 'cross';
    hiddenInput.value = isCross ? 'cross' : 'isolated';

    if (btnText) {
        btnText.innerHTML = isLocked
            ? `<span>${isCross ? 'Cross Margin' : 'Isolated Margin'}</span> <span style="font-size:11px; opacity:0.85; margin-left:5px;" title="Locked: Active position open in this contract">🔒</span>`
            : `<span>${isCross ? 'Cross Margin' : 'Isolated Margin'}</span>`;
        btnText.style.color = '#94a3b8';
    }
    if (btn) {
        btn.style.borderColor = 'rgba(255, 255, 255, 0.15)';
        btn.style.background = 'rgba(255, 255, 255, 0.04)';
        btn.setAttribute('data-locked', isLocked ? 'true' : 'false');
    }
    if (infoText) {
        if (isLocked) {
            infoText.innerHTML = `<span style="color:#94a3b8;">🔒 Locked to <strong>${isCross ? 'Cross' : 'Isolated'}</strong> margin because you have an active ${formatDisplaySymbol(state.symbol)} position. Close the position first to switch.</span>`;
        } else {
            infoText.textContent = isCross
                ? 'Cross: whole trading wallet backs Cross trades — higher risk.'
                : 'Isolated: only this trade\'s margin can be liquidated.';
            infoText.style.color = '#94a3b8';
        }
    }
}

function setupMarginModeToggle() {
    const btn = document.getElementById('btnToggleMarginMode');
    const hiddenInput = document.getElementById('orderMarginMode');

    if (!btn || !hiddenInput) return;

    try {
        state.symbolMarginModes = JSON.parse(localStorage.getItem('kryvex_symbol_margin_modes') || '{}') || {};
    } catch (e) {
        state.symbolMarginModes = {};
    }

    btn.addEventListener('click', () => {
        const activeSymbolPos = (state.positions || []).find(p => p.symbol === state.symbol && p.status === 'open');
        if (activeSymbolPos) {
            const posMode = String(activeSymbolPos.margin_mode || 'isolated').toUpperCase();
            showFuturesToast(`Cannot change margin mode while you have an active ${posMode} position `, 'warning');
            return;
        }

        const currentMode = String(hiddenInput.value || 'isolated').toLowerCase();
        const newMode = currentMode === 'isolated' ? 'cross' : 'isolated';
        state.symbolMarginModes[state.symbol] = newMode;
        try {
            localStorage.setItem('kryvex_symbol_margin_modes', JSON.stringify(state.symbolMarginModes));
        } catch (e) {}

        renderMarginMode(newMode, false);
        updateOrderEstimate();
    });

    syncMarginModeWithActivePosition();
}

function setupLeverageModal() {
    const triggerBtn = document.getElementById('btnOpenLeverageModal');
    const modal = document.getElementById('setLeverageModalBackdrop');
    const closeBtn = document.getElementById('btnCloseLeverageModal');
    const cancelBtn = document.getElementById('btnCancelLeverageModal');
    const confirmBtn = document.getElementById('btnConfirmLeverageModal');
    const hiddenInput = document.getElementById('orderLeverage');
    const badge = document.getElementById('leverageBtnBadge');
    const valNum = document.getElementById('modalLeverageValNum');
    const tooltip = document.getElementById('modalLeverageTooltip');
    const tooltipVal = document.getElementById('modalLeverageTooltipVal');
    const fill = document.getElementById('modalLeverageFill');
    const thumb = document.getElementById('modalLeverageThumb');
    const track = document.getElementById('modalLeverageTrack');
    const ticks = [1, 100, 200, 300, 500, 750, 1000];
    const stepPct = 100 / (ticks.length - 1);

    if (!triggerBtn || !modal) return;

    let activeModalLeverage = 100;
    let isDragging = false;

    function pctToLev(pct) {
        if (pct <= 0) return 1;
        if (pct >= 100) return 1000;
        const index = Math.min(ticks.length - 2, Math.floor(pct / stepPct));
        const subPct = (pct - index * stepPct) / stepPct;
        const startVal = ticks[index];
        const endVal = ticks[index + 1];
        return Math.max(1, Math.min(1000, Math.round(startVal + subPct * (endVal - startVal))));
    }

    function levToPct(lev) {
        lev = Math.max(1, Math.min(1000, Number(lev) || 1));
        for (let i = 0; i < ticks.length - 1; i++) {
            if (lev <= ticks[i + 1]) {
                const range = ticks[i + 1] - ticks[i];
                const subPct = (lev - ticks[i]) / range;
                return (i + subPct) * stepPct;
            }
        }
        return 100;
    }

    function renderModalState(val) {
        val = Math.max(1, Math.min(1000, Math.round(Number(val) || 1)));
        activeModalLeverage = val;

        if (valNum) valNum.textContent = String(val);
        if (tooltipVal) tooltipVal.textContent = `${val}x`;

        const pct = levToPct(val);
        if (fill) fill.style.width = `${pct}%`;
        if (thumb) {
            thumb.style.left = `${pct}%`;
            thumb.setAttribute('aria-valuenow', String(val));
        }
        if (tooltip) tooltip.style.left = `${pct}%`;

        // Update tick diamonds
        modal.querySelectorAll('.set-leverage-tick').forEach((tickEl) => {
            const tickVal = Number(tickEl.dataset.tickVal || 0);
            if (tickVal <= val) {
                tickEl.classList.add('is-active');
            } else {
                tickEl.classList.remove('is-active');
            }
        });

        // Update preset buttons
        modal.querySelectorAll('.set-leverage-preset-btn').forEach((btnEl) => {
            const btnLev = Number(btnEl.dataset.lev || 0);
            if (btnLev === val) {
                btnEl.classList.add('is-active');
            } else {
                btnEl.classList.remove('is-active');
            }
        });
    }

    function openModal() {
        const currentVal = Number(hiddenInput?.value || 100);
        renderModalState(currentVal);
        modal.style.display = 'flex';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function closeModal() {
        modal.style.display = 'none';
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function confirmLeverage() {
        if (hiddenInput) {
            hiddenInput.value = String(activeModalLeverage);
            hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
            hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (badge) {
            badge.textContent = `${activeModalLeverage}x`;
        }
        updateOrderEstimate();
        closeModal();
    }

    // Trigger open
    triggerBtn.addEventListener('click', (e) => {
        e.preventDefault();
        openModal();
    });

    // Close buttons
    closeBtn?.addEventListener('click', closeModal);
    cancelBtn?.addEventListener('click', closeModal);

    // Backdrop click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });

    // Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });

    // Confirm button
    confirmBtn?.addEventListener('click', (e) => {
        e.preventDefault();
        confirmLeverage();
    });

    // Preset buttons click
    modal.querySelectorAll('.set-leverage-preset-btn').forEach((btnEl) => {
        btnEl.addEventListener('click', (e) => {
            e.preventDefault();
            const lev = Number(btnEl.dataset.lev || 1);
            renderModalState(lev);
        });
    });

    // Tick click
    modal.querySelectorAll('.set-leverage-tick').forEach((tickEl) => {
        tickEl.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const lev = Number(tickEl.dataset.tickVal || 1);
            renderModalState(lev);
        });
    });

    // Slider pointer dragging on track
    if (track) {
        function updateFromPointer(e) {
            const rect = track.getBoundingClientRect();
            if (rect.width <= 0) return;
            const clientX = e.touches && e.touches.length > 0 ? e.touches[0].clientX : e.clientX;
            let pct = ((clientX - rect.left) / rect.width) * 100;
            pct = Math.max(0, Math.min(100, pct));
            const newLev = pctToLev(pct);
            renderModalState(newLev);
        }

        track.addEventListener('pointerdown', (e) => {
            isDragging = true;
            thumb?.classList.add('is-dragging');
            try { track.setPointerCapture(e.pointerId); } catch (err) {}
            updateFromPointer(e);
        });

        track.addEventListener('pointermove', (e) => {
            if (!isDragging) return;
            updateFromPointer(e);
        });

        const stopDrag = (e) => {
            if (isDragging) {
                isDragging = false;
                thumb?.classList.remove('is-dragging');
                try { track.releasePointerCapture(e.pointerId); } catch (err) {}
            }
        };

        track.addEventListener('pointerup', stopDrag);
        track.addEventListener('pointercancel', stopDrag);
    }

    // Keyboard support on thumb
    if (thumb) {
        thumb.addEventListener('keydown', (e) => {
            let delta = 0;
            if (e.key === 'ArrowRight' || e.key === 'ArrowUp') delta = 1;
            else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') delta = -1;
            else if (e.key === 'PageUp') delta = 10;
            else if (e.key === 'PageDown') delta = -10;
            else if (e.key === 'Home') { renderModalState(1); return; }
            else if (e.key === 'End') { renderModalState(1000); return; }

            if (delta !== 0) {
                e.preventDefault();
                renderModalState(activeModalLeverage + delta);
            }
        });
    }

    // Synchronize badge on initial load
    if (hiddenInput && badge) {
        badge.textContent = `${hiddenInput.value || 100}x`;
    }
}


function setupLeadingZeroNumberInputs() {
    document.querySelectorAll('#futuresOrderPreviewForm input[type="number"], #futuresWalletActionForm input[type="number"]').forEach((input) => {
        input.addEventListener('blur', () => {
            input.value = normalizeNumberString(input.value);
        });
    });
}

function normalizeNumberString(value) {
    let raw = String(value ?? '').trim().replace(/,/g, '');

    if (!raw) return '';
    if (raw.startsWith('.')) return `0${raw}`;
    if (raw.startsWith('-.')) return raw.replace('-.', '-0.');

    return raw;
}

function setupOrderPreviewSubmit() {
    const form = document.getElementById('futuresOrderPreviewForm');
    const placeButton = document.getElementById('placeDemoOrderButton');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        placeButton?.click();
    });
}

function setupPlaceDemoOrder() {

    const button = document.getElementById('placeDemoOrderButton');

    if (!button) return;

    button.addEventListener('click', async () => {

        if (state.isPlacingOrder) return;

        if (!getAccessToken()) {
            showOrderResult('Please login first.', true);
            showFuturesToast('Please login first.', 'error');
            return;
        }

        const quantityInput = document.getElementById('orderQuantity');
        const qtyVal = Number(quantityInput?.value || 0);
        const spec = getFuturesSymbolLotSpec(state.symbol);

        if (!qtyVal || qtyVal <= 0) {
            const emptyMsg = 'Please enter order quantity.';
            showOrderResult(emptyMsg, true);
            showFuturesToast(emptyMsg, 'warning');
            quantityInput?.focus();
            return;
        }

        if (qtyVal < spec.min) {
            const warnMsg = `Minimum order quantity for ${formatDisplaySymbol(state.symbol)} is ${spec.min} ${spec.base || ''}.`;
            showOrderResult(warnMsg, true);
            showFuturesToast(warnMsg, 'warning');
            quantityInput?.focus();
            return;
        }

        const typeSelect = document.getElementById('orderType');
        const orderType = (typeSelect?.value || 'market').toLowerCase();
        let prefKey = 'kryvex:futures:confirm:market:skip';
        let typeName = 'Market';
        if (['limit'].includes(orderType)) {
            prefKey = 'kryvex:futures:confirm:limit:skip';
            typeName = 'Limit';
        } else if (['stop_limit', 'stop_market'].includes(orderType)) {
            prefKey = 'kryvex:futures:confirm:stop:skip';
            typeName = 'Stop-Limit';
        }

        const confirmed = await askFuturesConfirm({
            title: `Place ${typeName} order?`,
            message: `Margin and fee will be deducted for this ${typeName} order.`,
            confirmText: 'Place Order',
            preferenceKey: prefKey,
            skipLabel: `Do not ask again for ${typeName} orders.`,
        });

        if (!confirmed) return;

        state.isPlacingOrder = true;

        button.disabled = true;
        button.textContent = 'Placing...';

        try {

            const form = document.getElementById('futuresOrderPreviewForm');

            setLastPriceInput();

            const payload = formToPayload(form);
            const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : localStorage.getItem('kryvex_trading_mode')) === 'live';
            const endpoint = isLive ? '/api/futures/orders' : '/api/futures/demo-orders';

            const data = await authFetch(endpoint, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            showFuturesToast(data.message, 'success');

            resetFuturesOrderForm();

            closeFuturesMobileOrderDrawer();

            // Refresh data immediately
            await Promise.all([
                loadWalletBalance(),
                loadPositions(true),
                loadOrders(),
                loadTradeHistory(),
            ]);
            updateLiveWalletMetrics();

        } catch (e) {

            showOrderResult(e.message, true);
            showFuturesToast(e.message || 'Failed to place order.', 'error');

        } finally {

            state.isPlacingOrder = false;

            button.disabled = false;

            syncOrderActionState();

        }

    });

}

async function calculateOrderPreview() {
    const form = document.getElementById('futuresOrderPreviewForm');

    if (!form) {
        return;
    }

    setLastPriceInput();
    showOrderResult('Calculating preview...', false);

    try {
        const payload = formToPayload(form);

        const response = await fetch('/api/futures/order-preview', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Preview failed.');
        }

        renderOrderPreview(data.data);
    } catch (error) {
        showOrderResult(error.message || 'Unable to calculate preview.', true);
    }
}

function setLastPriceInput() {
    const lastPriceInput = document.getElementById('orderLastPrice');

    if (lastPriceInput) {
        lastPriceInput.value = getCurrentLastPrice();
    }
}

function formToPayload(form) {
    const formData = new FormData(form);
    const payload = {};

    formData.forEach((value, key) => {
        payload[key] = value;
    });

    const tpSlToggle = document.getElementById('toggleTpSlFields');
    const isTpSlEnabled = tpSlToggle ? tpSlToggle.checked : true;

    const activeSymbol = String(payload.symbol || state.symbol || window.KRYVEX_FUTURES_SYMBOL || 'BTCUSDT').toUpperCase().replace(/[^A-Z0-9]/g, '');
    const marginModeInput = document.getElementById('orderMarginMode');
    const activeMarginMode = String(marginModeInput?.value || payload.margin_mode || 'isolated').toLowerCase();

    payload.symbol = activeSymbol;
    payload.currency = window.getActiveCurrency ? window.getActiveCurrency() : 'USDT';
    payload.margin_mode = activeMarginMode;
    payload.quantity = Number(normalizeNumberString(payload.quantity || 0));
    payload.leverage = Number(payload.leverage || 0);
    payload.last_price = Number(payload.last_price || getCurrentLastPrice() || 0);
    payload.price = payload.price ? Number(normalizeNumberString(payload.price)) : null;
    payload.stop_price = payload.stop_price ? Number(normalizeNumberString(payload.stop_price)) : null;
    payload.take_profit = isTpSlEnabled && payload.take_profit ? Number(normalizeNumberString(payload.take_profit)) : null;
    payload.stop_loss = isTpSlEnabled && payload.stop_loss ? Number(normalizeNumberString(payload.stop_loss)) : null;
    payload.reduce_only = payload.reduce_only === '1';

    return payload;
}

function renderOrderPreview(data) {
    const resultBox = document.getElementById('orderPreviewResult');

    if (!resultBox) {
        return;
    }

    resultBox.classList.remove('is-error');

    resultBox.innerHTML = `
        <div class="order-preview-grid compact-preview">
            <div><span>Symbol</span><strong>${escapeHtml(formatDisplaySymbol(data.symbol))}</strong></div>
            <div><span>Side</span><strong>${escapeHtml(data.side.toUpperCase())}</strong></div>
            <div><span>Order Type</span><strong>${escapeHtml(data.order_type)}</strong></div>
            <div><span>Execution Price</span><strong>${formatUsdPrice(data.execution_price)}</strong></div>
            <div><span>Quantity</span><strong>${data.estimated_quantity}</strong></div>
            <div><span>Estimated Margin</span><strong>${formatUsdt(data.margin)}</strong></div>
            <div><span>Leverage</span><strong>${data.leverage}x</strong></div>
            <div><span>Estimated Fee</span><strong>${formatUsdt(data.estimated_fee)}</strong></div>
        </div>
    `;
}

function resetFuturesOrderForm() {
    const form = document.getElementById('futuresOrderPreviewForm');

    if (!form) {
        return;
    }

    const quantityInput = document.getElementById('orderQuantity');
    const priceInput = document.getElementById('orderPrice');
    const stopPriceInput = document.getElementById('orderStopPrice');
    const takeProfitInput = form.querySelector('[name="take_profit"]');
    const stopLossInput = form.querySelector('[name="stop_loss"]');

    if (quantityInput) quantityInput.value = '';
    if (priceInput) priceInput.value = '';
    if (stopPriceInput) stopPriceInput.value = '';
    if (takeProfitInput) takeProfitInput.value = '';
    if (stopLossInput) stopLossInput.value = '';

    setText('orderEstimatedMargin', '--');

    const resultBox = document.getElementById('orderPreviewResult');

    if (resultBox) {
        resultBox.classList.remove('is-error');
        resultBox.textContent = '';
    }

    updateOrderEstimate();
    syncOrderActionState();
}

function updateOrderEstimate() {
    const quantity = Number(normalizeNumberString(document.getElementById('orderQuantity')?.value || 0));
    const leverage = Number(document.getElementById('orderLeverage')?.value || 1);
    const orderType = document.getElementById('orderType')?.value || 'market';
    const limitPrice = Number(normalizeNumberString(document.getElementById('orderPrice')?.value || 0));
    const liveMarkPrice = getCurrentLastPrice() || getLivePriceForSymbol(state.symbol);

    const price = ['limit', 'stop_limit'].includes(orderType) && limitPrice > 0
        ? limitPrice
        : liveMarkPrice;

    const marginInput = document.getElementById('orderIsolatedMargin');
    const manualMargin = Number(normalizeNumberString(marginInput?.value || 0));
    const calculatedQuantity = manualMargin && price && leverage ? (manualMargin * leverage) / price : 0;

    if (manualMargin && calculatedQuantity && document.activeElement === marginInput) {
        const quantityInput = document.getElementById('orderQuantity');
        if (quantityInput) quantityInput.value = calculatedQuantity.toFixed(6);
    }

    const activeQuantity = manualMargin && calculatedQuantity ? calculatedQuantity : quantity;
    const notional = activeQuantity * price;
    const margin = manualMargin || (leverage ? notional / leverage : 0);

    const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : localStorage.getItem('kryvex_trading_mode')) === 'live';
    const activeCur = (window.getActiveCurrency ? window.getActiveCurrency() : 'USD') === 'INR' ? 'INR' : 'USD';
    const available = isLive
        ? (activeCur === 'INR' ? Number(state.wallet?.real_available_balance_inr || 0) : Number(state.wallet?.real_available_balance || 0))
        : Number(state.wallet?.demo_available_balance || 0);

    const ratio = available > 0 ? Math.min((margin / available) * 100, 100) : 0;

    setText('orderEstimatedMargin', margin ? formatUsdt(margin) : '--');
    // Mark Price in order ticket preview card always displays live chart price!
    setText('orderPanelMarkPrice', liveMarkPrice ? formatUsdPrice(liveMarkPrice) : '--');
    setText('orderPanelAvailable', state.wallet ? (isLive && activeCur === 'INR' ? formatInr(available) : formatUsdt(available)) : getAccessToken() ? '--' : 'Login required');
    setText('orderMarginRatio', `${ratio.toFixed(2)}%`);
    updateMarginRatioBar(ratio);
}

function showOrderResult(message, isError = false) {
    if (isError) {
        showFuturesOrderTopNotice(message || 'Unable to place order.', 'error');
        return;
    }

    hideFuturesOrderTopNotice();
}

function syncOrderActionState() {
    const side = document.getElementById('orderSide')?.value || 'buy';
    const placeButton = document.getElementById('placeDemoOrderButton');
    if (!placeButton) return;

    const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : localStorage.getItem('kryvex_trading_mode')) === 'live';
    const modeSuffix = isLive ? ' (Live)' : ' (Demo)';

    if (side === 'sell') {
        placeButton.textContent = 'Sell / Short' + modeSuffix;
        placeButton.classList.add('is-sell');
    } else {
        placeButton.textContent = 'Buy / Long' + modeSuffix;
        placeButton.classList.remove('is-sell');
    }

    const availLabel = document.getElementById('orderPanelAvailableLabel');
    if (availLabel) {
        availLabel.textContent = isLive ? 'Available (Live)' : 'Available (Demo)';
    }
}

function updateMarginRatioBar(ratio) {
    const bar = document.getElementById('orderMarginRatioBar');
    if (!bar) return;
    bar.style.width = `${Math.max(0, Math.min(ratio, 100)).toFixed(2)}%`;
}

function formatCurrencyVal(val, currency) {
    return formatUsdt(val);
}

function syncFuturesStripAccountInfo(isLive, activeCur) {
    const stripAccId = document.getElementById('futuresStripAccId');
    const stripAccCur = document.getElementById('futuresStripAccCur');
    const stripAccMode = document.getElementById('futuresStripAccMode');
    const stripDepositBtn = document.getElementById('futuresStripDepositBtn');

    if (stripDepositBtn && !stripDepositBtn.dataset.depositAttached) {
        stripDepositBtn.dataset.depositAttached = 'true';
        stripDepositBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const activeMode = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live' ? 'live' : 'demo';
            if (activeMode === 'live') {
                if (typeof window.openFuturesAddFundsModal === 'function') {
                    window.openFuturesAddFundsModal();
                    return;
                }
                const addFundsModal = document.getElementById('globalAddFundsModal') || document.getElementById('futuresAddFundsModal');
                if (addFundsModal) {
                    addFundsModal.hidden = false;
                    addFundsModal.removeAttribute('hidden');
                    addFundsModal.style.display = 'flex';
                }
            } else {
                if (typeof window.openFuturesDemoDepositModal === 'function') {
                    window.openFuturesDemoDepositModal();
                    return;
                }
                const demoDepositModal = document.getElementById('futuresDemoDepositModal');
                if (demoDepositModal) {
                    demoDepositModal.hidden = false;
                    demoDepositModal.removeAttribute('hidden');
                    demoDepositModal.style.display = 'flex';
                }
            }
        });
    }

    let accounts = [];
    try {
        accounts = window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]');
    } catch (e) {
        accounts = [];
    }

    const activeUid = localStorage.getItem('kryvex_active_account_uid');
    let code = isLive ? 'L1000001' : 'D1000000';
    if (isLive) {
        const liveAccounts = accounts.filter(a => a.account_type !== 'demo' && !a.is_demo);
        const liveAcc = liveAccounts.find(a => a.account_uid === activeUid) || liveAccounts.find(a => a.is_default) || liveAccounts[0];
        const accNum = liveAcc?.account_number || '1000001';
        code = 'L' + accNum;
    } else {
        const demoAcc = accounts.find(a => a.account_type === 'demo' || a.is_demo);
        const accNum = demoAcc?.account_number || '1000000';
        code = 'D' + accNum;
    }

    if (stripAccId) stripAccId.textContent = code;
    if (stripAccCur) stripAccCur.textContent = isLive ? (isActiveCentAccount() ? 'USC' : ((activeCur === 'INR') ? 'INR' : 'USDT')) : 'USDT';
    if (stripAccMode) {
        stripAccMode.textContent = isLive ? 'LIVE' : 'DEMO';
        stripAccMode.className = `futures-acc-badge-mode is-${isLive ? 'live' : 'demo'}`;
    }
}

function updateLiveWalletMetrics() {
    const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : 'USD';
    const activeCur = rawCur === 'INR' ? 'INR' : 'USD';
    const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : localStorage.getItem('kryvex_trading_mode')) === 'live';

    syncFuturesStripAccountInfo(isLive, activeCur);

    let mainBalUsdt = 0;
    let mainBalInr = 0;
    let available = 0;
    let locked = 0;

    const accounts = (tradingAccountsState.accounts && tradingAccountsState.accounts.length > 0)
        ? tradingAccountsState.accounts
        : (window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]'));
    const demoAcc = accounts.find(a => a.account_type === 'demo' || a.is_demo);

    if (isLive) {
        const activeUid = tradingAccountsState.activeAccountUid || localStorage.getItem('kryvex_active_account_uid');
        const curAcc = accounts.find(a => String(a.account_uid) === String(activeUid)) || accounts.find(a => a.is_default && !a.is_demo) || accounts[0];
        if (curAcc) {
            mainBalUsdt = Number(curAcc.real_main_balance !== undefined ? curAcc.real_main_balance : (curAcc.is_default ? (state.wallet?.real_main_balance ?? 0) : 0));
            mainBalInr = Number(curAcc.real_main_balance_inr !== undefined ? curAcc.real_main_balance_inr : (curAcc.is_default ? (state.wallet?.real_main_balance_inr ?? 0) : 0));
            available = activeCur === 'INR' 
                ? Number(curAcc.real_available_balance_inr !== undefined ? curAcc.real_available_balance_inr : (state.wallet?.real_available_balance_inr ?? 0)) 
                : Number(curAcc.real_available_balance !== undefined ? curAcc.real_available_balance : (state.wallet?.real_available_balance ?? 0));
            locked = activeCur === 'INR' 
                ? Number(curAcc.real_locked_balance_inr !== undefined ? curAcc.real_locked_balance_inr : (state.wallet?.real_locked_balance_inr ?? 0)) 
                : Number(curAcc.real_locked_balance !== undefined ? curAcc.real_locked_balance : (state.wallet?.real_locked_balance ?? 0));
        } else {
            mainBalUsdt = Number(state.wallet?.real_main_balance || 0);
            mainBalInr = Number(state.wallet?.real_main_balance_inr || 0);
            available = activeCur === 'INR' 
                ? Number(state.wallet?.real_available_balance_inr || 0) 
                : Number(state.wallet?.real_available_balance || 0);
            locked = activeCur === 'INR' 
                ? Number(state.wallet?.real_locked_balance_inr || 0) 
                : Number(state.wallet?.real_locked_balance || 0);
        }
    } else {
        mainBalUsdt = Number(state.wallet?.demo_main_balance || 0);
        mainBalInr = 0;
        // In Demo mode: ALWAYS fixed in USD (USDT)! Never multiply by INR!
        available = Number(state.wallet?.demo_available_balance || 0);
        locked = Number(state.wallet?.demo_locked_balance || 0);

        if (available <= 0 && demoAcc) {
            const cachedBal = Number(demoAcc.demo_available_balance || demoAcc.balance || 0);
            if (cachedBal > 0) {
                available = cachedBal;
                if (mainBalUsdt <= 0) mainBalUsdt = cachedBal;
            }
        }
    }

    const pnl = calculateTotalOpenPnl(isLive && activeCur === 'INR' ? 'INR' : 'USDT');
    const liveAvailable = Math.max(0, available + pnl);
    const totalBalance = available + locked;
    const marginBalance = totalBalance + pnl;
    const marginRatio = marginBalance > 0 ? Math.min((locked / marginBalance) * 100, 100) : (locked > 0 ? 100 : 0);

    const isLoggedIn = Boolean(getAccessToken());

    if (isLoggedIn && !state.wallet) {
        loadWalletBalance();
    }

    const fmt = (val) => isLive && activeCur === 'INR' ? formatInr(val) : formatUsdt(val);

    const stripGroup = document.getElementById('futuresStripAccountGroup');
    if (stripGroup) {
        stripGroup.classList.toggle('is-demo-mode', !isLive);
    }

    // Transfer button on futures page: ONLY show in Live mode!
    const transferBtn = document.getElementById('futuresTransferBtn') || document.querySelector('.wallet-action-btn.transfer');
    if (transferBtn) {
        if (isLive) {
            transferBtn.style.removeProperty('display');
            transferBtn.removeAttribute('hidden');
            transferBtn.classList.remove('is-hidden');
        } else {
            transferBtn.style.setProperty('display', 'none', 'important');
            transferBtn.setAttribute('hidden', '');
            transferBtn.classList.add('is-hidden');
        }
    }

    // Main balance chip: ONLY show in Live mode! In Demo, single balance only!
    const mainBalChip = document.getElementById('futuresMainBalChip') || document.querySelector('.wallet-balance-chip.main');
    if (mainBalChip) {
        if (isLive) {
            mainBalChip.style.removeProperty('display');
            mainBalChip.removeAttribute('hidden');
            mainBalChip.classList.remove('is-hidden');
        } else {
            mainBalChip.style.setProperty('display', 'none', 'important');
            mainBalChip.setAttribute('hidden', '');
            mainBalChip.classList.add('is-hidden');
        }
    }

    // Balance label: "Trading" in Live mode, "Balance" in Demo mode
    const tradingBalLabel = document.getElementById('futuresTradingBalLabel') || document.querySelector('.wallet-balance-chip.trading span');
    if (tradingBalLabel) {
        tradingBalLabel.textContent = isLive ? 'Trading' : 'Balance';
    }

    setText('chartLivePnlTitle', `Unrealized PnL (${isLive && activeCur === 'INR' ? 'INR' : 'USD'})`);
    setText('orderWalletAvailable', isLoggedIn ? fmt(liveAvailable) : 'Login required');
    setText('orderWalletLocked', isLoggedIn ? fmt(locked) : '--');
    setText('orderLivePnl', fmt(pnl));
    setText('orderEstimatedEquity', isLoggedIn ? (activeCur === 'INR' ? formatInr(mainBalInr) : formatUsdt(mainBalUsdt)) : 'Login required');
    setText('orderPanelAvailable', isLoggedIn ? fmt(liveAvailable) : 'Login required');
    setText('chartTotalBalance', fmt(totalBalance));
    setText('orderLivePnlFooter', fmt(pnl));

    const livePnlEl = document.getElementById('orderLivePnl');
    if (livePnlEl) {
        livePnlEl.classList.toggle('market-positive', pnl >= 0);
        livePnlEl.classList.toggle('market-negative', pnl < 0);
    }

    const footerLivePnlEl = document.getElementById('orderLivePnlFooter');
    if (footerLivePnlEl) {
        footerLivePnlEl.classList.toggle('market-positive', pnl >= 0);
        footerLivePnlEl.classList.toggle('market-negative', pnl < 0);
    }

    // Overall PnL across all open trades (USD + INR combined into ONE single sum amount, ALWAYS in $ USD!)
    const totalCombinedUsdPnl = calculateOverallAllPnl();

    let overallPnlText = '--';
    if (!state.positions.length) {
        overallPnlText = '$0.00 USD';
    } else {
        overallPnlText = `${totalCombinedUsdPnl >= 0 ? '+' : ''}${formatUsdt(totalCombinedUsdPnl)}${isActiveCentAccount() ? '' : ' USD'}`;
    }

    setText('orderTotalOverallPnlFooter', overallPnlText);

    const overallEl = document.getElementById('orderTotalOverallPnlFooter');
    if (overallEl) {
        overallEl.classList.toggle('market-positive', totalCombinedUsdPnl >= 0);
        overallEl.classList.toggle('market-negative', totalCombinedUsdPnl < 0);
    }

    setText('chartMarginBalance', fmt(marginBalance));
    setText('chartMarginUsed', fmt(locked));
    setText('chartMarginRatio', `${marginRatio.toFixed(2)}%`);

    setText('orderMarginBalance', fmt(marginBalance));
    setText('orderMaintenanceMargin', fmt(locked));
    setText('orderMarginRatio', `${marginRatio.toFixed(2)}%`);
    setText('orderMarginRatioSymbol', formatDisplaySymbol(state.symbol));
    updateMarginRatioBar(marginRatio);

    setText('mobileEquity', isLoggedIn ? fmt(marginBalance) : '--');
    setText('mobileUpnl', isLoggedIn ? fmt(pnl) : '--');
    setText('mobileMarginBal', isLoggedIn ? fmt(locked) : '--');
    setText('mobileAvail', isLoggedIn ? fmt(liveAvailable) : '--');

    const mobileUpnlEl = document.getElementById('mobileUpnl');
    if (mobileUpnlEl && state.wallet) {
        mobileUpnlEl.className = pnl >= 0 ? 'market-positive' : 'market-negative';
    }

    const pnlElement = document.getElementById('orderLivePnl');
    const pnlFooterElement = document.getElementById('orderLivePnlFooter');
    const balanceElement = document.getElementById('orderEstimatedEquity');

    [pnlElement, pnlFooterElement].forEach((element) => {
        if (!element) return;
        element.classList.toggle('market-positive', pnl >= 0);
        element.classList.toggle('market-negative', pnl < 0);
    });

    if (balanceElement) {
        balanceElement.classList.remove('market-positive', 'market-negative');
    }

    const availableElements = [
        document.getElementById('orderWalletAvailable'),
        document.getElementById('orderPanelAvailable')
    ];
    availableElements.forEach((el) => {
        if (!el) return;
        el.classList.remove('market-positive', 'market-negative');
        if (state.positions.length && liveAvailable > 0) {
            if (pnl > 0.00000001) {
                el.classList.add('market-positive');
            } else if (pnl < -0.00000001) {
                el.classList.add('market-negative');
            }
        }
    });

    maybeAutoCloseRiskPositions(available, pnl);
    updateOrderEstimate();

    if (!isLive) {
        window.__activeFuturesLiveAvailable = liveAvailable;
    } else {
        window.__activeFuturesLiveAvailable = null;
    }
    if (typeof window.updateNavbarBalanceDisplay === 'function') {
        window.updateNavbarBalanceDisplay();
    }
}

async function maybeAutoCloseRiskPositions(available, pnl) {
    if (state.autoLiquidating || !getAccessToken() || !state.positions.length || !state.wallet) {
        return;
    }

    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : 'USDT';
    const locked = activeCur === 'INR'
        ? Number(state.wallet?.demo_locked_balance_inr || 0)
        : Number(state.wallet?.demo_locked_balance || 0);
    const totalEquity = Number(available || 0) + locked + Number(pnl || 0);

    // Liquidation only triggers if Total Equity (Available + Margin Used + PnL) <= 0
    if (totalEquity > 0) {
        return;
    }

    const openPositions = state.positions.filter((p) => ((p.currency === 'INR' ? 'INR' : 'USDT') === activeCur));
    if (!openPositions.length) {
        return;
    }

    const missingPrice = openPositions.find((position) => !getLivePriceForSymbol(position.symbol));

    if (missingPrice) {
        return;
    }

    state.autoLiquidating = true;
    showFuturesToast('Trading margin depleted. Open futures trades auto-closed.', 'error');

    try {
        await Promise.all(openPositions.map((position) => authFetch(`/api/futures/positions/${position.position_uid}/close`, {
            method: 'POST',
            body: JSON.stringify({ exit_price: getLivePriceForSymbol(position.symbol) }),
        })));
        await loadProtectedFuturesData();
    } catch (error) {
        await loadProtectedFuturesData();
    } finally {
        state.autoLiquidating = false;
    }
}

function calculateTotalOpenPnl(targetCurrency) {
    const activeCur = targetCurrency || (window.getActiveCurrency ? window.getActiveCurrency() : 'USDT');
    const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live';
    const activeMode = isLive ? 'live' : 'demo';

    return (state.positions || []).reduce((sum, position) => {
        const posMode = (position.mode || 'demo').toLowerCase();
        if (posMode !== activeMode) {
            return sum;
        }

        const pnl = calculatePositionPnl(position, getLivePriceForSymbol(position.symbol));
        position.livePnl = pnl;

        const posCur = (position.currency === 'INR') ? 'INR' : 'USDT';
        if (posCur !== activeCur) {
            return sum;
        }

        return sum + pnl;
    }, 0);
}

function calculateOverallAllPnl() {
    const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live';
    const activeMode = isLive ? 'live' : 'demo';

    return (state.positions || []).reduce((sum, position) => {
        const posMode = (position.mode || 'demo').toLowerCase();
        if (posMode !== activeMode) {
            return sum;
        }

        const pnl = calculatePositionPnl(position, getLivePriceForSymbol(position.symbol));
        return sum + Number(pnl || 0);
    }, 0);
}

function calculatePositionPnl(position, currentPrice) {
    const entryPrice = Number(position.entry_price || 0);
    const quantity = Number(position.quantity || 0);
    const markPrice = Number(currentPrice || 0);

    if (!entryPrice || !quantity || !markPrice) {
        return 0;
    }

    if (position.side === 'buy') {
        return (markPrice - entryPrice) * quantity;
    }

    return (entryPrice - markPrice) * quantity;
}

function calculatePositionPnlPercent(position, pnl) {
    const margin = Number(position.margin || 0);

    if (!margin) {
        return '--';
    }

    return `${((Number(pnl || 0) / margin) * 100).toFixed(2)}%`;
}

function getCurrentLastPrice() {
    return getLivePriceForSymbol(state.symbol);
}

function getLivePriceForSymbol(symbol, fallbackPosition = null) {
    if (!symbol) {
        if (fallbackPosition) {
            return Number(fallbackPosition.entry_price || fallbackPosition.mark_price || fallbackPosition.current_price || 0);
        }
        return 0;
    }

    const cleanSym = String(symbol).toUpperCase().replace(/[-/_ ]/g, '');

    const mappedPrice = Number(state.priceMap[cleanSym] || state.priceMap[symbol] || 0);
    if (mappedPrice > 0) {
        return mappedPrice;
    }

    const ticker = (state.tickers || []).find((item) => {
        const itemSym = String(item.symbol || '').toUpperCase().replace(/[-/_ ]/g, '');
        return itemSym === cleanSym || item.symbol === symbol;
    });

    if (ticker && Number(ticker.last_price || ticker.price || 0) > 0) {
        return Number(ticker.last_price || ticker.price);
    }

    // Gold fallback across aliases
    if (cleanSym === 'XAUUSDT' || cleanSym === 'GOLDUSDT' || cleanSym === 'XAUUSD' || cleanSym === 'PAXGUSDT' || cleanSym === 'XAUTUSDT') {
        const goldTicker = (state.tickers || []).find(item => {
            const sym = String(item.symbol || '').toUpperCase();
            return sym === 'XAUUSDT' || sym === 'PAXGUSDT' || sym === 'XAUTUSDT';
        });
        if (goldTicker && Number(goldTicker.last_price || 0) > 0) {
            return Number(goldTicker.last_price);
        }
    }

    if (fallbackPosition) {
        return Number(fallbackPosition.entry_price || fallbackPosition.mark_price || fallbackPosition.current_price || 0);
    }

    return 0;
}

async function authFetch(url, options = {}, hasRetried = false) {
    const token = getAccessToken();
    const activeTradingMode = (window.getActiveTradingMode ? window.getActiveTradingMode() : localStorage.getItem('kryvex_trading_mode')) === 'live' ? 'live' : 'demo';
    let activeAccountUid = localStorage.getItem('kryvex_active_account_uid');

    let accounts = [];
    try {
        accounts = window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]');
    } catch (e) {}

    if (activeTradingMode === 'demo') {
        const demoAcc = accounts.find(a => a.account_type === 'demo' || a.is_demo);
        if (demoAcc) {
            activeAccountUid = demoAcc.account_uid;
        }
    } else if (accounts.length > 0) {
        const liveAccs = accounts.filter(a => a.account_type !== 'demo' && !a.is_demo);
        const liveAcc = liveAccs.find(a => a.account_uid === activeAccountUid) || liveAccs.find(a => a.is_default) || liveAccs[0];
        if (liveAcc) {
            activeAccountUid = liveAcc.account_uid;
        }
    }

    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        'X-Trading-Mode': activeTradingMode,
        ...(options.headers || {}),
    };

    if (activeAccountUid) {
        headers['X-Account-UID'] = activeAccountUid;
        const activeAcc = accounts.find(a => String(a.account_uid) === String(activeAccountUid));
        if (activeAcc && activeAcc.broker_type) {
            headers['X-Broker-Type'] = activeAcc.broker_type;
        }
    }

    const response = await fetch(url, {
        ...options,
        headers,
    });

    const data = await parseJsonResponse(response);

    if (response.status === 401 && !hasRetried) {
        const refreshed = await refreshAccessToken();

        if (refreshed) {
            return authFetch(url, options, true);
        }

        clearAuthSession();
        throw new Error('Session expired. Please login again.');
    }

    if (!response.ok || data.success === false) {
        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
        throw new Error(firstError || data.message || 'Request failed.');
    }

    return data;
}

let isRefreshingToken = false;
let refreshTokenQueue = [];

async function refreshAccessToken() {
    if (isRefreshingToken) {
        return new Promise((resolve) => {
            refreshTokenQueue.push(resolve);
        });
    }

    isRefreshingToken = true;
    const refreshToken = localStorage.getItem('kryvex_refresh_token');

    if (!refreshToken) {
        isRefreshingToken = false;
        resolveRefreshTokenQueue(false);
        return false;
    }

    try {
        const response = await fetch('/api/auth/refresh', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                refresh_token: refreshToken,
            }),
        });

        const data = await parseJsonResponse(response);

        if (!response.ok || !data.success || !data.data?.access_token) {
            isRefreshingToken = false;
            resolveRefreshTokenQueue(false);
            return false;
        }

        localStorage.setItem('kryvex_access_token', data.data.access_token);
        isRefreshingToken = false;
        resolveRefreshTokenQueue(true);
        return true;
    } catch (error) {
        isRefreshingToken = false;
        resolveRefreshTokenQueue(false);
        return false;
    }
}

function resolveRefreshTokenQueue(status) {
    refreshTokenQueue.forEach((callback) => callback(status));
    refreshTokenQueue = [];
}

async function parseJsonResponse(response) {
    try {
        return await response.json();
    } catch (error) {
        return {};
    }
}

function clearAuthSession() {
    localStorage.removeItem('kryvex_access_token');
    localStorage.removeItem('kryvex_refresh_token');
    localStorage.removeItem('kryvex_user');
}


function getAccessToken() {
    return localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token') || localStorage.getItem('token');
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value;
    }
}

function formatDisplaySymbol(symbol) {
    return String(symbol || '').toUpperCase();
}

function formatMarketPrice(value) {
    const number = Number(value);

    if (!number) {
        return '--';
    }

    if (number >= 1000) {
        return number.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
    }

    if (number >= 1) {
        return number.toLocaleString('en-US', {
            minimumFractionDigits: 4,
            maximumFractionDigits: 4,
        });
    }

    return number.toLocaleString('en-US', {
        minimumFractionDigits: 6,
        maximumFractionDigits: 8,
    });
}

function formatOptionalPrice(value) {
    if (!value) {
        return '--';
    }

    return formatUsdPrice(value);
}

function formatQuantity(value) {
    const number = Number(value);

    if (!number) {
        return '--';
    }

    return number.toLocaleString('en-US', {
        maximumFractionDigits: 8,
    });
}

function formatUsdPrice(value) {
    const formatted = formatMarketPrice(value);

    return formatted === '--' ? '--' : `$${formatted}`;
}

function formatUsdt(value) {
    if (isActiveCentAccount()) return formatUsc(value);
    return formatUsdtAsset(value);
}

// On-chain deposit/withdrawal amounts are real USDT, so they never switch to USC.
function formatUsdtAsset(value) {
    const number = Number(value || 0);

    return `$${number.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}

function formatTradeTime(value) {
    if (!value) {
        return '--';
    }

    return new Date(Number(value)).toLocaleTimeString('en-IN', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function formatFundingRate(value) {
    const number = Number(value);

    if (!number) {
        return '--';
    }

    return `${(number * 100).toFixed(4)}%`;
}

function renderFundingCountdown() {
    const element = document.getElementById('futuresNextFunding');

    if (!element || !state.nextFundingTime) {
        return;
    }

    const diff = Number(state.nextFundingTime) - Date.now();

    if (diff <= 0) {
        element.textContent = '00:00:00';
        return;
    }

    const totalSeconds = Math.floor(diff / 1000);
    const hours = String(Math.floor(totalSeconds / 3600)).padStart(2, '0');
    const minutes = String(Math.floor((totalSeconds % 3600) / 60)).padStart(2, '0');
    const seconds = String(totalSeconds % 60).padStart(2, '0');

    element.textContent = `${hours}:${minutes}:${seconds}`;
}

function formatDate(value) {
    if (!value) {
        return '--';
    }

    let parsedValue = value;

    if (typeof value === 'object') {
        parsedValue = value.date || value.$date || '';
    }

    if (!parsedValue) {
        return '--';
    }

    return new Date(parsedValue).toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    });
}

async function processDemoFillsForSymbol(symbol, price) {
    if (!getAccessToken()) {
        return;
    }

    const hasOpenOrder = state.orders.some((order) => {
        return order.symbol === symbol && order.status === 'open';
    });

    if (!hasOpenOrder) {
        return;
    }

    const now = Date.now();
    const lastCheck = state.lastFillCheckAt[symbol] || 0;

    if (now - lastCheck < 250) {
        return;
    }

    state.lastFillCheckAt[symbol] = now;

    try {
        const result = await authFetch('/api/futures/orders/process-demo-fills', {
            method: 'POST',
            body: JSON.stringify({
                symbol,
                last_price: price,
            }),
        });

        const filled = result.data?.filled || [];

        if (filled.length) {
            await loadProtectedFuturesData();
        }
    } catch (error) {
        // Keep UI live even if fill processing fails.
    }
}

const futuresAutoClosing = new Set();

function checkFuturesTpSlAutoClose(symbol = null, livePrice = null) {
    if (!getAccessToken() || !state.positions.length) {
        return;
    }

    state.positions.forEach((position) => {
        if (symbol && position.symbol !== symbol) {
            return;
        }

        const uid = position.position_uid;
        if (!uid || futuresAutoClosing.has(uid)) {
            return;
        }

        const mark = Number(livePrice || getLivePriceForSymbol(position.symbol) || 0);
        const tp = Number(position.take_profit);
        const sl = Number(position.stop_loss);

        if (!mark) {
            return;
        }

        const isBuy = String(position.side).toLowerCase() === 'buy';
        const takeProfitHit = !isNaN(tp) && tp > 0 && (isBuy ? mark >= tp : mark <= tp);
        const stopLossHit = !isNaN(sl) && sl > 0 && (isBuy ? mark <= sl : mark >= sl);

        if (!takeProfitHit && !stopLossHit) {
            return;
        }

        futuresAutoClosing.add(uid);
        closeFuturesPositionSilently(uid, mark, takeProfitHit ? 'TP' : 'SL');
    });
}

async function closeFuturesPositionSilently(positionUid, exitPrice, reason = 'TP/SL') {
    const previousPositions = [...state.positions];
    state.positions = state.positions.filter((position) => position.position_uid !== positionUid);
    renderPositions(state.positions);

    try {
        await authFetch(`/api/futures/positions/${positionUid}/close`, {
            method: 'POST',
            body: JSON.stringify({ exit_price: exitPrice }),
        });
        showFuturesToast(`${reason} touched. Trade closed automatically.`, 'success');
        loadWalletBalance();
        loadOrders();
        loadTradeHistory();
    } catch (error) {
        state.positions = previousPositions;
        renderPositions(state.positions);
        showFuturesToast(error.message || 'Unable to auto-close trade.', 'error');
    } finally {
        futuresAutoClosing.delete(positionUid);
    }
}

let futuresOrderNoticeTimer = null;

function showFuturesOrderTopNotice(message, type = 'error') {
    const box = document.getElementById('futuresOrderTopNotice');
    const title = document.getElementById('futuresOrderTopNoticeTitle');
    const text = document.getElementById('futuresOrderTopNoticeText');

    if (!box || !title || !text) {
        return;
    }

    if (futuresOrderNoticeTimer) {
        clearTimeout(futuresOrderNoticeTimer);
    }

    box.classList.remove('hidden', 'error', 'success');
    box.classList.add(type);

    title.textContent = type === 'success' ? 'Order placed' : 'Order issue';
    text.textContent = message || 'Something went wrong.';

    futuresOrderNoticeTimer = setTimeout(() => {
        hideFuturesOrderTopNotice();
    }, type === 'success' ? 2500 : 5500);
}

function hideFuturesOrderTopNotice() {
    const box = document.getElementById('futuresOrderTopNotice');

    if (!box) {
        return;
    }

    if (futuresOrderNoticeTimer) {
        clearTimeout(futuresOrderNoticeTimer);
        futuresOrderNoticeTimer = null;
    }

    box.classList.add('hidden');
    box.classList.remove('error', 'success');
}

document.addEventListener('DOMContentLoaded', () => {
    const qtyInput = document.getElementById('orderQuantity');
    const priceInput = document.getElementById('orderPrice');

    if (qtyInput) {
        qtyInput.addEventListener('input', hideFuturesOrderTopNotice);
    }

    if (priceInput) {
        priceInput.addEventListener('input', hideFuturesOrderTopNotice);
    }
});

function renderPagination(targetId, meta, onPageClick) {
    const el = document.getElementById(targetId);
    if (!el) return;

    if (!meta || meta.last_page <= 1) {
        el.innerHTML = '';
        el.hidden = true;
        return;
    }

    el.hidden = false;
    const current = meta.current_page;
    const last = meta.last_page;

    let buttonsHtml = '';
    buttonsHtml += `<button type="button" class="kv-pagination-btn" ${current === 1 ? 'disabled' : ''} data-page="${current - 1}">Prev</button>`;

    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= current - 2 && i <= current + 2)) {
            buttonsHtml += `<button type="button" class="kv-pagination-btn ${i === current ? 'is-active' : ''}" data-page="${i}">${i}</button>`;
        } else if (i === current - 3 || i === current + 3) {
            buttonsHtml += `<span class="kv-pagination-ellipsis">...</span>`;
        }
    }

    buttonsHtml += `<button type="button" class="kv-pagination-btn" ${current === last ? 'disabled' : ''} data-page="${current + 1}">Next</button>`;

    el.innerHTML = buttonsHtml;

    el.querySelectorAll('button[data-page]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const p = Number(btn.dataset.page);
            if (p >= 1 && p <= last && p !== current) {
                onPageClick(p);
            }
        });
    });
}

function syncFuturesTradingModeUI(targetMode) {
    const activeMode = targetMode || (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo'));

    document.querySelectorAll('#futuresOrderPanel [data-mode-toggle]').forEach((btn) => {
        const btnMode = btn.getAttribute('data-mode-toggle');
        if (btnMode === activeMode) {
            btn.classList.add('is-active');
        } else {
            btn.classList.remove('is-active');
        }
    });

    let user = {};
    try { user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {}; } catch (e) {}
    const isMt5 = (user.broker_type || 'kryvex').toLowerCase() === 'mt5';

    const isLive = activeMode === 'live';
    const navToggle = document.getElementById('navCurrencyToggleWrap');
    if (navToggle) {
        navToggle.style.display = isLive ? 'inline-flex' : 'none';
    }
    const stripGroup = document.getElementById('futuresStripAccountGroup');
    if (stripGroup) {
        stripGroup.classList.toggle('is-demo-mode', !isLive);
    }
    const transferBtn = document.getElementById('futuresTransferBtn') || document.querySelector('.wallet-action-btn.transfer');
    if (transferBtn) {
        if (isLive) {
            transferBtn.style.removeProperty('display');
            transferBtn.removeAttribute('hidden');
            transferBtn.classList.remove('is-hidden');
        } else {
            transferBtn.style.setProperty('display', 'none', 'important');
            transferBtn.setAttribute('hidden', '');
            transferBtn.classList.add('is-hidden');
        }
    }
    const mainBalChip = document.getElementById('futuresMainBalChip') || document.querySelector('.wallet-balance-chip.main');
    if (mainBalChip) {
        if (isLive) {
            mainBalChip.style.removeProperty('display');
            mainBalChip.removeAttribute('hidden');
            mainBalChip.classList.remove('is-hidden');
        } else {
            mainBalChip.style.setProperty('display', 'none', 'important');
            mainBalChip.setAttribute('hidden', '');
            mainBalChip.classList.add('is-hidden');
        }
    }
    const tradingBalLabel = document.getElementById('futuresTradingBalLabel') || document.querySelector('.wallet-balance-chip.trading span');
    if (tradingBalLabel) {
        tradingBalLabel.textContent = isLive ? 'Trading' : 'Balance';
    }

    syncFuturesStripAccountInfo(isLive, (window.getActiveCurrency ? window.getActiveCurrency() : 'USD'));

    state.positions = [];
    if (typeof renderPositions === 'function') {
        renderPositions([]);
    }

    if (typeof syncOrderActionState === 'function') {
        syncOrderActionState();
    }
    if (typeof updateLiveWalletMetrics === 'function') {
        updateLiveWalletMetrics();
    }
    if (typeof updateOrderEstimate === 'function') {
        updateOrderEstimate();
    }
    if (typeof renderTradingAccountsSelector === 'function') {
        renderTradingAccountsSelector();
    }
    if (typeof updateFuturesLiveModalCalc === 'function') {
        updateFuturesLiveModalCalc();
    }

    if (typeof getAccessToken === 'function' && getAccessToken()) {
        if (typeof loadWalletBalance === 'function') loadWalletBalance();
        if (typeof loadTradingAccounts === 'function') loadTradingAccounts();
        if (typeof loadPositions === 'function') loadPositions();
        if (typeof loadOrders === 'function') loadOrders();
        if (typeof loadTradeHistory === 'function') loadTradeHistory();
    }
}

window.addEventListener('tradingModeChanged', (e) => {
    syncFuturesTradingModeUI(e?.detail?.mode);
});

window.addEventListener('kryvex_trading_mode_changed', (e) => {
    syncFuturesTradingModeUI(e?.detail?.mode);
});

window.addEventListener('kryvex_account_changed', (e) => {
    syncFuturesTradingModeUI(e?.detail?.mode);
});

window.addEventListener('kryvex_currency_changed', () => {
    if (typeof updateLiveWalletMetrics === 'function') {
        updateLiveWalletMetrics();
    }
});

function setupMobileResponsiveHandlers() {
    const mobileNavTabs = document.querySelectorAll('#futuresMobileNavTabs .mobile-nav-tab');
    const shell = document.querySelector('.futures-shell');
    const btnSell = document.getElementById('mobileBtnSell');
    const btnBuy = document.getElementById('mobileBtnBuy');
    const orderPanel = document.getElementById('futuresOrderPanel');
    const btnClosePanel = document.getElementById('btnCloseMobileOrderPanel');
    const backdrop = document.getElementById('mobileDrawerBackdrop');

    // Default mobile state: 'trade' view
    if (shell) {
        shell.classList.add('mobile-tab-view-trade');
        shell.classList.remove('mobile-tab-view-panel');
    }

    if (mobileNavTabs.length) {
        mobileNavTabs.forEach((tab) => {
            tab.addEventListener('click', () => {
                const target = tab.dataset.mobileTab;
                mobileNavTabs.forEach((t) => t.classList.remove('is-active'));
                tab.classList.add('is-active');

                if (target === 'trade') {
                    if (shell) {
                        shell.classList.add('mobile-tab-view-trade');
                        shell.classList.remove('mobile-tab-view-panel');
                    }
                } else {
                    if (shell) {
                        shell.classList.remove('mobile-tab-view-trade');
                        shell.classList.add('mobile-tab-view-panel');
                    }

                    const tabMap = {
                        positions: 'positionsPanel',
                        orders: 'openOrdersPanel',
                        history: 'orderHistoryPanel'
                    };
                    const targetPanelId = tabMap[target];
                    if (targetPanelId) {
                        const targetBtn = document.querySelector(`.futures-tabs button[data-tab-target="${targetPanelId}"]`);
                        if (targetBtn) targetBtn.click();
                    }
                }
            });
        });
    }

    const handleBar = document.getElementById('mobileDrawerHandleBar');
    const bottomBar = document.getElementById('futuresMobileBottomBar');

    const openOrderDrawer = (side) => {
        if (!orderPanel) return;
        const sideBtn = document.querySelector(`.order-side-tabs button[data-side="${side}"]`);
        if (sideBtn) sideBtn.click();

        orderPanel.classList.add('mobile-drawer-open');
        document.documentElement.classList.add('mobile-drawer-locked');
        document.body.classList.add('mobile-drawer-locked');
        if (bottomBar) bottomBar.style.setProperty('display', 'none', 'important');
        orderPanel.scrollTop = 0;
        const orderFormEl = document.getElementById('futuresOrderPreviewForm');
        if (orderFormEl) orderFormEl.scrollTop = 0;

        if (backdrop) {
            backdrop.hidden = false;
            backdrop.removeAttribute('hidden');
        }
    };

    const closeOrderDrawer = () => {
        closeFuturesMobileOrderDrawer();
    };

    if (btnSell) btnSell.addEventListener('click', () => openOrderDrawer('sell'));
    if (btnBuy) btnBuy.addEventListener('click', () => openOrderDrawer('buy'));
    if (btnClosePanel) {
        btnClosePanel.addEventListener('click', closeOrderDrawer);
        btnClosePanel.addEventListener('touchend', (e) => { e.preventDefault(); closeOrderDrawer(); });
    }
    if (handleBar) {
        handleBar.addEventListener('click', closeOrderDrawer);
        handleBar.addEventListener('touchend', (e) => { e.preventDefault(); closeOrderDrawer(); });
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeOrderDrawer);
        backdrop.addEventListener('touchend', (e) => { e.preventDefault(); closeOrderDrawer(); });
    }

    const orderForm = document.getElementById('futuresOrderPreviewForm');
    if (orderForm) {
        orderForm.addEventListener('submit', () => {
            setTimeout(closeOrderDrawer, 300);
        });
    }
}

function closeFuturesMobileOrderDrawer() {
    const orderPanel = document.getElementById('futuresOrderPanel');
    const bottomBar = document.getElementById('futuresMobileBottomBar');
    const backdrop = document.getElementById('mobileDrawerBackdrop');

    if (orderPanel) orderPanel.classList.remove('mobile-drawer-open');
    document.documentElement.classList.remove('mobile-drawer-locked');
    document.body.classList.remove('mobile-drawer-locked');
    if (bottomBar) bottomBar.style.removeProperty('display');
    if (backdrop) backdrop.hidden = true;
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        bootFuturesPage();
    });
} else {
    bootFuturesPage();
}

