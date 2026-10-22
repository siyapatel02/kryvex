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

const optionsState = {
    underlying: 'BTCUSDT',
    expiry: '',
    selectedContract: null,
    rows: [],
    refreshTimer: null,
    markSocket: null,
    activitySocket: null,
    pendingRender: false,
    atmStrike: null,
    isLoading: false,
    isRateLimited: false,
    wallet: null,
    minimumReserve: 0,
    hasOpenTrades: false,
    withdrawalCharges: {},
    depositNetworks: [],
    withdrawalAddresses: {},
    activeBottomTab: 'positions',
    positions: [],
    orders: [],
    trades: [],
    obPrecision: 0.1,
    obMode: 'both',
    lastObPrice: null,
    lastOrderBookData: { bids: [], asks: [] },
};

const MAX_VISIBLE_STRIKES = 26;
const OPTIONS_AUTO_REFRESH_MS = 5000;
const OPTIONS_WEBSOCKET_ENABLED = true;

const bottomColumns = {
    positions: ['Contract', 'Side', 'Entry', 'Qty', 'Margin', 'P&L', 'Status', 'Action'],
    openOrders: ['Contract', 'Side', 'Type', 'Price', 'Qty', 'Margin', 'Status', 'Action'],
    orderHistory: ['Contract', 'Side', 'Type', 'Price', 'Qty', 'Margin', 'Fee', 'Status', 'Date'],
    tradeHistory: ['Contract', 'Side', 'Price', 'Qty', 'Premium', 'Fee', 'Date', 'Action'],
};

const tradingAccountsState = {
    accounts: [],
    activeAccountUid: localStorage.getItem('kryvex_active_account_uid') || null,
};

const OPTIONS_COIN_LOGOS = {
    BTC: "https://assets.coingecko.com/coins/images/1/large/bitcoin.png",
    BTCUSDT: "https://assets.coingecko.com/coins/images/1/large/bitcoin.png",
    ETH: "https://assets.coingecko.com/coins/images/279/large/ethereum.png",
    ETHUSDT: "https://assets.coingecko.com/coins/images/279/large/ethereum.png",
    SOL: "https://assets.coingecko.com/coins/images/4128/large/solana.png",
    SOLUSDT: "https://assets.coingecko.com/coins/images/4128/large/solana.png",
    XAU: "/images/coins/gold.svg",
    XAUUSDT: "/images/coins/gold.svg",
    GOLD: "/images/coins/gold.svg",
    PAXG: "/images/coins/gold.svg",
    PAXGUSDT: "/images/coins/gold.svg",
    BNB: "https://assets.coingecko.com/coins/images/825/large/bnb-icon2_2x.png",
    XRP: "https://assets.coingecko.com/coins/images/44/large/xrp-symbol-white-128.png",
    DOGE: "https://assets.coingecko.com/coins/images/5/large/dogecoin.png",
    ADA: "https://assets.coingecko.com/coins/images/975/large/cardano.png"
};

function getOptionCoinLogo(rawSymbol) {
    if (!rawSymbol) return OPTIONS_COIN_LOGOS.BTC;
    const clean = String(rawSymbol).toUpperCase().replace(/[-/_ ]/g, '');
    if (OPTIONS_COIN_LOGOS[clean]) return OPTIONS_COIN_LOGOS[clean];
    const base = clean.replace(/(USDT|USD|INR)$/i, '') || 'BTC';
    return OPTIONS_COIN_LOGOS[base] || OPTIONS_COIN_LOGOS.BTC;
}

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

            const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live';
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
        }
    } catch (e) {
        console.warn('Failed to load trading accounts:', e);
    }
}

function renderTradingAccountDropdown() {
    let user = {};
    try {
        user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (e) {}

    const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live';
    const isMt5 = (user.broker_type || 'kryvex').toLowerCase() === 'mt5';
    const accountWrapper = document.querySelector('.trading-account-selector-wrapper');
    if (accountWrapper) {
        accountWrapper.style.display = isMt5 ? 'none' : '';
    }
    const openCreateBtn = document.getElementById('btnOpenCreateAccountModal');
    const dropdownFooter = document.querySelector('.account-dropdown-footer');
    const canCreateSubAccount = !isMt5 && isLive;
    if (openCreateBtn) {
        openCreateBtn.style.display = canCreateSubAccount ? 'flex' : 'none';
    }
    if (dropdownFooter) {
        dropdownFooter.style.display = canCreateSubAccount ? 'block' : 'none';
    }

    const filteredAccounts = (tradingAccountsState.accounts || []).filter(acc => {
        const isDemo = (acc.account_type === 'demo' || acc.is_demo);
        return isLive ? !isDemo : isDemo;
    });

    const activeAcc = tradingAccountsState.accounts.find(a => a.account_uid === tradingAccountsState.activeAccountUid) || filteredAccounts[0];
    const activeNameEl = document.getElementById('activeAccountName');
    if (activeNameEl && activeAcc) {
        activeNameEl.textContent = activeAcc.account_name;
    }

    const listEl = document.getElementById('tradingAccountList');
    if (!listEl) return;

    let html = '';
    filteredAccounts.forEach(acc => {
        const isActive = acc.account_uid === tradingAccountsState.activeAccountUid;
        html += `
            <button type="button" class="account-dropdown-item ${isActive ? 'is-active' : ''}" data-account-uid="${acc.account_uid}">
                <span>${escapeHtml(acc.account_name)}</span>
                ${isActive ? '<span class="account-item-check">✓</span>' : ''}
            </button>
        `;
    });

    listEl.innerHTML = html;

    listEl.querySelectorAll('.account-dropdown-item').forEach(btn => {
        btn.addEventListener('click', () => {
            const uid = btn.dataset.accountUid;
            switchTradingAccount(uid);
        });
    });
}

function renderTransferAccountSelect() {
    const select = document.getElementById('walletTransferAccountSelect');
    const selectInline = document.getElementById('walletTransferAccountSelectInline');
    if (!select && !selectInline) return;

    let html = '';
    (tradingAccountsState.accounts || []).forEach(acc => {
        let balNum = 0;
        if (acc.is_default) {
            balNum = Number(acc.demo_available_balance ?? state.wallet?.demo_main_balance ?? state.wallet?.demo_available_balance ?? 0);
        } else {
            balNum = Number(acc.demo_available_balance ?? acc.available_balance ?? acc.balance ?? 0);
        }
        const balStr = isCentAccount(acc) ? formatUsc(balNum) : formatUsdtAsset(balNum);
        html += `<option value="${acc.account_uid}">${escapeHtml(acc.account_name)} (${balStr})</option>`;
    });

    if (select) select.innerHTML = html;
    if (selectInline) selectInline.innerHTML = html;

    const activeUid = tradingAccountsState.activeAccountUid || (tradingAccountsState.accounts[0]?.account_uid ?? '');
    if (activeUid) {
        if (select && Array.from(select.options).some(o => o.value === activeUid)) select.value = activeUid;
        if (selectInline && Array.from(selectInline.options).some(o => o.value === activeUid)) selectInline.value = activeUid;
    }
}

function switchTradingAccount(accountUid) {
    if (accountUid === tradingAccountsState.activeAccountUid) return;

    tradingAccountsState.activeAccountUid = accountUid;
    localStorage.setItem('kryvex_active_account_uid', accountUid);

    const dropdown = document.getElementById('tradingAccountDropdown');
    if (dropdown) dropdown.hidden = true;

    renderTradingAccountDropdown();

    Promise.all([
        loadWalletData(),
        loadProtectedOptionsData(),
    ]);

    const activeAcc = tradingAccountsState.accounts.find(a => a.account_uid === accountUid);
    showOptionsToast(`Switched to ${activeAcc ? activeAcc.account_name : 'Trading Account'}`, 'success');
}

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
                showOptionsToast('Please login first to create trading accounts.', 'error');
                return;
            }

            let user = {};
            try {
                user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
            } catch (err) {}

            const kycStatus = String(user.kyc_status || '').toLowerCase();
            if (user.kyc_status && !['verified', 'approved'].includes(kycStatus)) {
                showOptionsToast('KYC verification is required to create new trading accounts.', 'error');
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

                showOptionsToast(res.message || 'Trading account created!', 'success');
                closeCreateModal();
                await loadTradingAccounts();
                if (res.data && res.data.account_uid) {
                    switchTradingAccount(res.data.account_uid);
                }
            } catch (err) {
                showOptionsToast(err.message || 'Failed to create trading account.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Create Account';
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.options-page');

    if (!page) return;

    optionsState.underlying = page.dataset.selectedSymbol || 'BTCUSDT';

    setupTradingAccountControls();
    initializeOptionsPage(optionsState.underlying);
    setupOrderTabs();
    setupBottomTabs();
    setupActivityTabs();
    setupOrderBookControls();
    setupWalletModal();
    setupExpirySelect();
    setupStrikeSearch();
    setupCloseAllButton();
    setupOptionsMobileDrawerControls();
    setupBitProUIControls();
    showOptionOrderCard(true);

    loadOptionsChain();
    connectUnderlyingMarkSocket();
    loadTradingAccounts();
    loadWalletData();
    loadProtectedOptionsData();
    loadOptionsInrConfig();
    loadDepth();

    optionsState.refreshTimer = setInterval(() => {
        loadOptionsChain(false);
    }, OPTIONS_AUTO_REFRESH_MS);

    setInterval(() => {
        renderBottomTable(optionsState.activeBottomTab);
        checkOptionsTpSlAutoClose();
        updateOptionsLiveWalletMetrics();
    }, 500);

    setInterval(loadDepth, 4000); // ORDER BOOK UPDATE EVERY 4 SECONDS
});

function setupBitProUIControls() {
    // Stepper buttons for Price
    const priceInput = document.getElementById('optionOrderPrice');
    document.getElementById('btnPriceStepDown')?.addEventListener('click', () => {
        if (!priceInput) return;
        let val = Number(priceInput.value || 0);
        val = Math.max(0, val - (val > 10 ? 5 : 0.5));
        priceInput.value = val > 0 ? val.toFixed(1) : '';
        updatePremiumEstimate();
    });
    document.getElementById('btnPriceStepUp')?.addEventListener('click', () => {
        if (!priceInput) return;
        let val = Number(priceInput.value || 0);
        val = val + (val > 10 ? 5 : 0.5);
        priceInput.value = val.toFixed(1);
        updatePremiumEstimate();
    });

    // Stepper buttons for Quantity/Contracts
    const qtyInput = document.getElementById('optionOrderQuantity');
    document.getElementById('btnQtyStepDown')?.addEventListener('click', () => {
        if (!qtyInput) return;
        let val = Number(qtyInput.value || 1);
        val = Math.max(1, val - 1);
        qtyInput.value = val;
        updatePremiumEstimate();
    });
    document.getElementById('btnQtyStepUp')?.addEventListener('click', () => {
        if (!qtyInput) return;
        let val = Number(qtyInput.value || 1);
        val = val + 1;
        qtyInput.value = val;
        updatePremiumEstimate();
    });

    // Percentage Slider
    const slider = document.getElementById('optionQuantitySlider');
    if (slider) {
        slider.addEventListener('input', () => {
            applyPercentageQuantity(Number(slider.value));
        });
    }

    document.querySelectorAll('.slider-percentage-labels span').forEach(lbl => {
        lbl.addEventListener('click', () => {
            const pct = Number(lbl.dataset.pct || 0);
            if (slider) slider.value = pct;
            applyPercentageQuantity(pct);
        });
    });

    // Side Tabs
    document.querySelectorAll('.bitpro-side-toggle-tabs button[data-side]').forEach(btn => {
        btn.addEventListener('click', () => {
            setOptionOrderSide(btn.dataset.side);
        });
    });

    // Top Navigation Tabs (Switches Center View)
    document.querySelectorAll('.bitpro-tab-link[data-top-tab]').forEach(tabBtn => {
        tabBtn.addEventListener('click', () => {
            const tabKey = tabBtn.dataset.topTab;
            switchBitProMainView(tabKey);
        });
    });

    // Update Coin Logo
    const logoImg = document.getElementById('optCoinLogoImg');
    if (logoImg) {
        logoImg.src = getOptionCoinLogo(optionsState.underlying);
    }

    // Symbol Selector Listeners
    const handleSymbolChange = (newSymbol) => {
        if (!newSymbol) return;
        const normalized = newSymbol.toUpperCase().replace('-', '');
        const target = normalized.endsWith('USDT') ? normalized : (normalized + 'USDT');
        window.location.href = `/options/${target.toLowerCase()}`;
    };

    const optSymSelect = document.getElementById('optionsSymbolSelector');
    if (optSymSelect) {
        optSymSelect.addEventListener('change', (e) => handleSymbolChange(e.target.value));
    }
    const filterSymSelect = document.getElementById('filterSymbolSelector');
    if (filterSymSelect) {
        filterSymSelect.addEventListener('change', (e) => handleSymbolChange(e.target.value));
    }
}

function switchBitProMainView(tabKey) {
    document.querySelectorAll('.bitpro-tab-link').forEach(b => {
        b.classList.toggle('is-active', b.dataset.topTab === tabKey);
    });

    const views = {
        chain: 'viewOptionsChain',
        positions: 'viewOptionsPositions',
        openOrders: 'viewOptionsOpenOrders',
        orderHistory: 'viewOptionsOrderHistory',
        tradeHistory: 'viewOptionsTradeHistory',
        exerciseHistory: 'viewOptionsExerciseHistory',
    };

    Object.entries(views).forEach(([key, viewId]) => {
        const el = document.getElementById(viewId);
        if (el) {
            if (key === tabKey) {
                el.hidden = false;
                el.classList.add('is-active');
            } else {
                el.hidden = true;
                el.classList.remove('is-active');
            }
        }
    });

    optionsState.activeBottomTab = tabKey;

    if (tabKey === 'positions') {
        const body = document.getElementById('optionsPositionsBody');
        if (body) renderPositionsTable(body, 8);
    } else if (tabKey === 'openOrders') {
        const body = document.getElementById('optionsOpenOrdersBody');
        if (body) renderOpenOrdersTable(body, 8);
    } else if (tabKey === 'orderHistory') {
        const body = document.getElementById('optionsOrderHistoryBody');
        if (body) {
            renderOrderHistoryTable(body, 9);
            renderOptionsPagination('optionsOrderHistoryPagination', optionsOrdersPagination, async (p) => {
                await loadOptionsOrders(p);
                renderOrderHistoryTable(body, 9);
            });
        }
    } else if (tabKey === 'tradeHistory') {
        const body = document.getElementById('optionsTradeHistoryBody');
        if (body) {
            renderTradeHistoryTable(body, 8);
            renderOptionsPagination('optionsTradeHistoryPagination', optionsTradesPagination, async (p) => {
                await loadOptionsTrades(p);
                renderTradeHistoryTable(body, 8);
            });
        }
    }
}

function applyPercentageQuantity(pct) {
    const qtyInput = document.getElementById('optionOrderQuantity');
    const price = getOptionExecutionPrice();
    const availBal = optionsState.wallet?.demo_available_balance ?? 0;

    if (!qtyInput || !price || price <= 0 || !availBal || availBal <= 0) return;

    const targetFunds = (availBal * pct) / 100;
    const maxQty = Math.max(1, Math.floor(targetFunds / price));
    qtyInput.value = maxQty;
    updatePremiumEstimate();
}

function initializeOptionsPage(symbol) {
    document.documentElement.classList.add('options-page-html');
    document.body.classList.add('options-page-body');
    setText('chainTitle', symbol);
    setText('optionUnderlying', formatPair(symbol));
    setText('chainStatus', 'Loading...');
    const logoImg = document.getElementById('optCoinLogoImg');
    if (logoImg) {
        logoImg.src = getOptionCoinLogo(symbol);
        logoImg.alt = symbol;
    }
    renderBottomTable('positions');
}

async function loadOptionsChain(showLoading = true) {
    if (optionsState.isRateLimited || optionsState.isLoading) return;

    optionsState.isLoading = true;

    try {
        if (showLoading) setText('chainStatus', 'Loading chain...');

        const expiryQuery = optionsState.expiry ? `?expiry=${encodeURIComponent(optionsState.expiry)}` : '';
        const response = await fetch(`/api/options/chain/${optionsState.underlying}${expiryQuery}`, {
            headers: { Accept: 'application/json' },
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Unable to load options chain.');
        }

        const data = result.data || {};
        optionsState.expiry = data.selected_expiry || '';
        optionsState.rows = data.rows || [];
        optionsState.baseSpotPrice = data.underlying_stats?.last_price || optionsState.liveSpotPrice || 0;

        optionsState.rows.forEach(row => {
            if (row.call) {
                row.call.baseMarkPrice = Number(row.call.mark_price || row.call.last_price || 10);
                row.call.delta = Number(row.call.delta || 0.5);
            }
            if (row.put) {
                row.put.baseMarkPrice = Number(row.put.mark_price || row.put.last_price || 10);
                row.put.delta = Number(row.put.delta || 0.5);
            }
        });

        renderTopStats(data.underlying_stats || {});
        optionsState.atmStrike = findAtmStrike(optionsState.rows, data.underlying_stats?.last_price);
        renderExpiryOptions(data.expiries || [], optionsState.expiry);
        updateChainHeader(optionsState.expiry);
        renderChain(optionsState.rows);

        setText('chainStatus', `Live • ${optionsState.rows.length} strikes`);

        if (optionsState.selectedContract) {
            const updated = findContractBySymbol(optionsState.rows, optionsState.selectedContract.symbol);
            if (updated) selectContract(updated, false, null, false);
        }

        if (OPTIONS_WEBSOCKET_ENABLED && !optionsState.markSocket) connectUnderlyingMarkSocket();
    } catch (error) {
        const message = error.message || 'Unable to load options chain.';

        if (isRateLimitMessage(message)) {
            optionsState.isRateLimited = true;
            if (optionsState.refreshTimer) clearInterval(optionsState.refreshTimer);
            closeSocket(optionsState.markSocket);
            closeSocket(optionsState.activitySocket);
            setText('chainStatus', 'Rate limited');
            renderChainError('Binance temporarily blocked market API because too many requests were sent. Wait until ban time expires, then refresh once.');
            return;
        }

        setText('chainStatus', 'API error');
        renderChainError(message);
    } finally {
        optionsState.isLoading = false;
    }
}

function renderTopStats(stats, fromWs = false) {
    if (stats.last_price !== null && stats.last_price !== undefined) {
        optionsState.liveSpotPrice = Number(stats.last_price);
    }

    if (stats.price_change_percent !== null && stats.price_change_percent !== undefined) {
        optionsState.lastChangePercent = stats.price_change_percent;
    }
    if (stats.high_price !== null && stats.high_price !== undefined) {
        optionsState.lastHighPrice = stats.high_price;
    }
    if (stats.low_price !== null && stats.low_price !== undefined) {
        optionsState.lastLowPrice = stats.low_price;
    }
    if (stats.volume !== null && stats.volume !== undefined) {
        optionsState.lastVolume = stats.volume;
    }
    if (stats.quote_volume !== null && stats.quote_volume !== undefined) {
        optionsState.lastQuoteVolume = stats.quote_volume;
    }

    const lastPrice = optionsState.liveSpotPrice ?? stats.last_price;
    const formattedPrice = (lastPrice !== null && lastPrice !== undefined) ? formatUsdPrice(lastPrice, 2) : '--';
    setText('optionIndexPrice', formattedPrice);
    setText('optFilterSpotPrice', formattedPrice);
    setText('optHeaderMarkPrice', formattedPrice);

    const change = stats.price_change_percent ?? optionsState.lastChangePercent;
    const changeEl = document.getElementById('optionChange24h');
    const headerChangeEl = document.getElementById('optHeader24hChange');

    if (change !== null && change !== undefined) {
        const numChange = Number(change);
        const changeStr = `${numChange >= 0 ? '+' : ''}${numChange.toFixed(2)}%`;
        if (changeEl) {
            changeEl.textContent = changeStr;
            changeEl.className = `live-change-badge ${numChange < 0 ? 'negative' : ''}`;
        }
        if (headerChangeEl) {
            headerChangeEl.textContent = changeStr;
            headerChangeEl.style.color = numChange < 0 ? '#f43f5e' : '#10b981';
        }
    }

    const high = stats.high_price ?? optionsState.lastHighPrice;
    if (high) setText('optHeader24hHigh', formatUsdPrice(high, 2));

    const low = stats.low_price ?? optionsState.lastLowPrice;
    if (low) setText('optHeader24hLow', formatUsdPrice(low, 2));

    const vol = stats.volume ?? optionsState.lastVolume;
    if (vol) setText('optHeaderVolumeBase', formatNumber(vol, 2));

    const quoteVol = stats.quote_volume ?? optionsState.lastQuoteVolume;
    if (quoteVol) setText('optHeaderVolumeQuote', `${(Number(quoteVol)/1e6).toFixed(2)}M`);
    if (stats.quote_volume) setText('optHeaderVolumeQuote', `${(Number(stats.quote_volume)/1e6).toFixed(2)}M`);
    if (stats.open_interest) setText('optHeaderOpenInterest', formatNumber(stats.open_interest, 2));

    // Bottom Summary Card
    const availBal = optionsState.wallet?.demo_available_balance ?? 0;
    const lockedBal = optionsState.wallet?.demo_locked_balance ?? 0;
    const totalBal = availBal + lockedBal;
    
    setText('optBottomTotalAccount', formatUsdtWithUnit(totalBal));
    setText('optBottomBuyingPower', formatUsdtWithUnit(availBal));
    setText('optBottomUsedMargin', formatUsdtWithUnit(lockedBal));
    setText('optBottomTotalOi', '85,421.32 ' + (optionsState.underlying.split('-')[0].replace('USDT', '') || 'BTC'));
    setText('optBottom24hVol', '798.25M USDT');

    // Calculate total unrealized PnL from open positions
    let totalUpnl = 0;
    (optionsState.positions || []).forEach(p => {
        totalUpnl += Number(p.unrealized_pnl || 0);
    });
    const upnlEl = document.getElementById('optBottomUnrealizedPnl');
    if (upnlEl) {
        upnlEl.textContent = `${totalUpnl >= 0 ? '+' : ''}${formatUsdtWithUnit(totalUpnl)}`;
        upnlEl.style.color = totalUpnl < 0 ? '#f43f5e' : '#10b981';
    }

    updatePageDocumentTitle(lastPrice);
}

function updatePageDocumentTitle(price = null) {
    const rawP = price !== null && Number.isFinite(Number(price)) ? Number(price) : (optionsState.liveSpotPrice || null);
    const cleanSym = String(optionsState.underlying || 'BTCUSDT').toUpperCase().replace('-', '');
    const sym = `${cleanSym} Options`;
    if (rawP !== null && rawP > 0) {
        const formatted = rawP.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.title = `${formatted} | ${sym} | Kryvex Exchange`;
    } else {
        document.title = `${sym} - Kryvex`;
    }
}

function renderExpiryOptions(expiries, selectedExpiry) {
    const select = document.getElementById('expirySelect');
    if (!select) return;

    const currentValue = select.value;
    select.innerHTML = '';

    if (!expiries.length) {
        select.innerHTML = '<option value="">No expiries found</option>';
        setText('selectedExpiryDisplay', 'No Expiry');
        return;
    }

    expiries.forEach((expiry) => {
        const option = document.createElement('option');
        option.value = expiry.key;
        option.textContent = expiry.label;
        select.appendChild(option);
    });

    const activeExp = selectedExpiry || currentValue || expiries[0].key;
    select.value = activeExp;

    const activeObj = expiries.find(e => e.key === activeExp);
    setText('selectedExpiryDisplay', activeObj ? activeObj.label : activeExp);
    setText('chainExpiryCenter', `Expiry: ${activeObj ? activeObj.label : activeExp}`);
}

function renderChain(rows) {
    const tbody = document.getElementById('optionsChainBody');
    if (!tbody) return;

    const search = (document.getElementById('strikeSearch')?.value || '').trim();
    const displayRows = search
        ? rows.filter((row) => String(row.strike).includes(search))
        : getCenteredStrikeRows(rows, optionsState.atmStrike, MAX_VISIBLE_STRIKES);

    if (!displayRows.length) {
        tbody.innerHTML = '<tr><td colspan="13" class="empty-row">No option contracts found for this expiry.</td></tr>';
        return;
    }

    const underlyingPrice = getCurrentUnderlyingPrice();
    let html = '';
    let lineInserted = false;

    displayRows.forEach((row, index) => {
        const currentStrike = Number(row.strike);
        const nextStrike = Number(displayRows[index + 1]?.strike);

        if (!lineInserted && Number.isFinite(underlyingPrice) && underlyingPrice <= currentStrike && index === 0) {
            html += renderUnderlyingLine(underlyingPrice);
            lineInserted = true;
        }

        const isAtm = optionsState.atmStrike !== null && Number(row.strike) === Number(optionsState.atmStrike);
        const isCallSelected = optionsState.selectedContract && row.call && optionsState.selectedContract.symbol === row.call.symbol;
        const isPutSelected = optionsState.selectedContract && row.put && optionsState.selectedContract.symbol === row.put.symbol;
        const rowSelectedClass = (isCallSelected || isPutSelected) ? ' is-selected-row' : '';

        html += `
            <tr class="chain-row ${isAtm ? 'is-atm-row' : ''}${rowSelectedClass}">
                ${renderContractCells(row.call, 'call', row.strike, isCallSelected)}
                <td class="col-center-strike" data-strike="${row.strike}">
                    <strong>${formatStrike(row.strike)}</strong>
                </td>
                ${renderContractCells(row.put, 'put', row.strike, isPutSelected)}
            </tr>
        `;

        if (!lineInserted && Number.isFinite(underlyingPrice) && Number.isFinite(nextStrike)) {
            if (underlyingPrice > currentStrike && underlyingPrice <= nextStrike) {
                html += renderUnderlyingLine(underlyingPrice);
                lineInserted = true;
            }
        }
    });

    if (!lineInserted && Number.isFinite(underlyingPrice)) {
        html += renderUnderlyingLine(underlyingPrice);
    }

    tbody.innerHTML = html;

    tbody.querySelectorAll('[data-option-symbol]').forEach((cell) => {
        cell.addEventListener('click', () => {
            const symbol = cell.dataset.optionSymbol;
            if (!symbol) return;
            const contract = findContractBySymbol(optionsState.rows, symbol);
            if (contract) selectContract(contract, true, cell, true);
        });
    });
}

let optionsChainViewMode = 'standard';

function getContractLiveGreeks(contract, strikeVal, isCall) {
    const spot = Number(optionsState.liveSpotPrice || optionsState.baseSpotPrice || 65000);
    const strike = Number(strikeVal || 65000);
    const markIv = Number(contract?.mark_iv || 50.0);
    
    let timeYears = 0.082; // ~30 days default
    if (optionsState.expiry) {
        try {
            const expDate = new Date(optionsState.expiry);
            const now = new Date();
            const diffMs = expDate.getTime() - now.getTime();
            if (diffMs > 0) {
                timeYears = diffMs / (1000 * 60 * 60 * 24 * 365.25);
            }
        } catch(e) {}
    }

    return calculateBlackScholesGreeks(spot, strike, timeYears, markIv, isCall);
}

function calculateBlackScholesGreeks(S, K, T, ivPct, isCall) {
    if (S <= 0 || K <= 0 || T <= 0) {
        return { delta: isCall ? '+0.5000' : '-0.5000', gamma: '0.000100', theta: '-10.00', vega: '15.00' };
    }
    const sigma = Math.max(ivPct / 100, 0.05);
    const r = 0.03;
    const tSqrt = Math.sqrt(Math.max(T, 0.0001));

    const d1 = (Math.log(S / K) + (r + (sigma * sigma) / 2) * T) / (sigma * tSqrt);
    const d2 = d1 - sigma * tSqrt;

    function cdf(x) {
        const a1 = 0.254829592, a2 = -0.284496736, a3 = 1.421413741, a4 = -1.453152027, a5 = 1.061405429, p = 0.3275911;
        const sign = x < 0 ? -1 : 1;
        const absX = Math.abs(x) / Math.sqrt(2);
        const t = 1.0 / (1.0 + p * absX);
        const y = 1.0 - (((((a5 * t + a4) * t) + a3) * t + a2) * t + a1) * t * Math.exp(-absX * absX);
        return 0.5 * (1.0 + sign * y);
    }
    function pdf(x) {
        return Math.exp(-0.5 * x * x) / Math.sqrt(2 * Math.PI);
    }

    const nD1 = pdf(d1);
    const Nd1 = cdf(d1);

    const deltaNum = isCall ? Nd1 : (Nd1 - 1);
    const gammaNum = nD1 / (S * sigma * tSqrt);
    const thetaUsd = -(S * nD1 * sigma) / (2 * tSqrt) - (isCall ? (r * K * Math.exp(-r * T) * cdf(d2)) : (-r * K * Math.exp(-r * T) * cdf(-d2)));
    const thetaDaily = thetaUsd / 365;
    const vegaNum = (S * tSqrt * nD1) / 100;

    let formattedDelta = '';
    if (isCall) {
        formattedDelta = '+' + Math.max(0, deltaNum).toFixed(4);
    } else {
        const pVal = Math.min(0, deltaNum);
        formattedDelta = (pVal === 0 ? '-' : '') + pVal.toFixed(4);
    }

    let formattedTheta = (-Math.abs(thetaDaily)).toFixed(2);
    if (formattedTheta === '0.00' || formattedTheta === '-0.00') formattedTheta = '-0.00';

    return {
        delta: formattedDelta,
        gamma: gammaNum.toFixed(6),
        theta: formattedTheta,
        vega: vegaNum.toFixed(2)
    };
}

function updateOptionChainTableHeader() {
    const colHeaderRow = document.querySelector('.chain-col-header-row');
    if (!colHeaderRow) return;

    if (optionsChainViewMode === 'greeks') {
        colHeaderRow.innerHTML = `
            <th class="col-delta" style="color:#ffffff;">Delta (Δ)</th>
            <th class="col-gamma" style="color:#ffffff;">Gamma (Γ)</th>
            <th class="col-theta" style="color:#ffffff;">Theta (Θ)</th>
            <th class="col-vega" style="color:#ffffff;">Vega (ν)</th>
            <th class="col-bid">Bid</th>
            <th class="col-ask">Ask</th>
            <th class="col-center-strike">Strike</th>
            <th class="col-ask">Ask</th>
            <th class="col-bid">Bid</th>
            <th class="col-vega" style="color:#ffffff;">Vega (ν)</th>
            <th class="col-theta" style="color:#ffffff;">Theta (Θ)</th>
            <th class="col-gamma" style="color:#ffffff;">Gamma (Γ)</th>
            <th class="col-delta" style="color:#ffffff;">Delta (Δ)</th>
        `;
    } else {
        const baseAsset = (optionsState.underlying || 'BTCUSDT').split('-')[0].replace('USDT', '') || 'BTC';
        colHeaderRow.innerHTML = `
            <th class="col-oi">OI (<span class="base-asset-label">${baseAsset}</span>)</th>
            <th class="col-vol">Volume (<span class="base-asset-label">${baseAsset}</span>)</th>
            <th class="col-bid">Bid<br><small>(Price / IV)</small></th>
            <th class="col-ask">Ask<br><small>(Price / IV)</small></th>
            <th class="col-last">Last Price<br><small>(Price / IV)</small></th>
            <th class="col-iv">IV</th>
            <th class="col-center-strike">Strike</th>
            <th class="col-iv">IV</th>
            <th class="col-last">Last Price<br><small>(Price / IV)</small></th>
            <th class="col-bid">Bid<br><small>(Price / IV)</small></th>
            <th class="col-ask">Ask<br><small>(Price / IV)</small></th>
            <th class="col-vol">Volume (<span class="base-asset-label">${baseAsset}</span>)</th>
            <th class="col-oi">OI (<span class="base-asset-label">${baseAsset}</span>)</th>
        `;
    }
}

function renderContractCells(contract, sideClass, strikeVal, isSelected = false) {
    if (!contract) {
        if (sideClass === 'call') {
            return `
                <td class="opt-cell col-oi">--</td>
                <td class="opt-cell col-vol">--</td>
                <td class="opt-cell col-bid">--</td>
                <td class="opt-cell col-ask">--</td>
                <td class="opt-cell col-last">--</td>
                <td class="opt-cell col-iv">--</td>
            `;
        }
        return `
            <td class="opt-cell col-iv">--</td>
            <td class="opt-cell col-last">--</td>
            <td class="opt-cell col-bid">--</td>
            <td class="opt-cell col-ask">--</td>
            <td class="opt-cell col-vol">--</td>
            <td class="opt-cell col-oi">--</td>
        `;
    }

    const activeClass = isSelected ? ` is-selected-side is-selected-${sideClass}` : '';
    const symbol = escapeHtml(contract.symbol);
    const bidP = contract.bid_price ? formatChainAmount(contract.bid_price) : '--';
    const askP = contract.ask_price ? formatChainAmount(contract.ask_price) : '--';
    const markP = (contract.mark_price || contract.last_price) ? formatChainAmount(contract.mark_price || contract.last_price) : '--';
    const ivStr = formatIvPercent(contract.mark_iv);
    const bidIv = formatIvPercent(contract.bid_iv || contract.mark_iv);
    const askIv = formatIvPercent(contract.ask_iv || contract.mark_iv);
    
    // Deterministic mock OI & Vol if not returned from testnet API
    const strikeNum = Number(strikeVal || 60000);
    const mockOi = ((strikeNum % 700) * 0.5 + 50.25).toFixed(2);
    const mockVol = ((strikeNum % 300) * 0.2 + 15.40).toFixed(2);
    const oiVal = contract.open_interest ? formatNumber(contract.open_interest, 2) : mockOi;
    const volVal = contract.volume ? formatNumber(contract.volume, 2) : mockVol;

    // GREEKS MODE: Delta is Green (+ Call) / Red (- Put), all other Greeks (Gamma, Theta, Vega) are clean White (#ffffff)
    if (optionsChainViewMode === 'greeks') {
        const greeks = getContractLiveGreeks(contract, strikeVal, sideClass === 'call');
        if (sideClass === 'call') {
            return `
                <td class="opt-cell col-delta${activeClass}" data-option-symbol="${symbol}" style="color:#22c55e;font-weight:600;">${greeks.delta}</td>
                <td class="opt-cell col-gamma${activeClass}" data-option-symbol="${symbol}" style="color:#ffffff;">${greeks.gamma}</td>
                <td class="opt-cell col-theta${activeClass}" data-option-symbol="${symbol}" style="color:#ffffff;">${greeks.theta}</td>
                <td class="opt-cell col-vega${activeClass}" data-option-symbol="${symbol}" style="color:#ffffff;">${greeks.vega}</td>
                <td class="opt-cell col-bid val-call${activeClass}" data-option-symbol="${symbol}">
                    <div class="opt-price">${bidP}</div>
                    <div class="opt-sub-iv">${bidIv}</div>
                </td>
                <td class="opt-cell col-ask val-call${activeClass}" data-option-symbol="${symbol}">
                    <div class="opt-price">${askP}</div>
                    <div class="opt-sub-iv">${askIv}</div>
                </td>
            `;
        }
        return `
            <td class="opt-cell col-ask val-put${activeClass}" data-option-symbol="${symbol}">
                <div class="opt-price">${askP}</div>
                <div class="opt-sub-iv">${askIv}</div>
            </td>
            <td class="opt-cell col-bid val-put${activeClass}" data-option-symbol="${symbol}">
                <div class="opt-price">${bidP}</div>
                <div class="opt-sub-iv">${bidIv}</div>
            </td>
            <td class="opt-cell col-vega${activeClass}" data-option-symbol="${symbol}" style="color:#ffffff;">${greeks.vega}</td>
            <td class="opt-cell col-theta${activeClass}" data-option-symbol="${symbol}" style="color:#ffffff;">${greeks.theta}</td>
            <td class="opt-cell col-gamma${activeClass}" data-option-symbol="${symbol}" style="color:#ffffff;">${greeks.gamma}</td>
            <td class="opt-cell col-delta${activeClass}" data-option-symbol="${symbol}" style="color:#ef4444;font-weight:600;">${greeks.delta}</td>
        `;
    }

    // STANDARD MODE
    if (sideClass === 'call') {
        return `
            <td class="opt-cell col-oi${activeClass}" data-option-symbol="${symbol}">${oiVal}</td>
            <td class="opt-cell col-vol${activeClass}" data-option-symbol="${symbol}">${volVal}</td>
            <td class="opt-cell col-bid val-call${activeClass}" data-option-symbol="${symbol}">
                <div class="opt-price">${bidP}</div>
                <div class="opt-sub-iv">${bidIv}</div>
            </td>
            <td class="opt-cell col-ask val-call${activeClass}" data-option-symbol="${symbol}">
                <div class="opt-price">${askP}</div>
                <div class="opt-sub-iv">${askIv}</div>
            </td>
            <td class="opt-cell col-last val-call${activeClass}" data-option-symbol="${symbol}">
                <div class="opt-price">${markP}</div>
                <div class="opt-sub-iv">${ivStr}</div>
            </td>
            <td class="opt-cell col-iv${activeClass}" data-option-symbol="${symbol}">${ivStr}</td>
        `;
    }

    return `
        <td class="opt-cell col-iv${activeClass}" data-option-symbol="${symbol}">${ivStr}</td>
        <td class="opt-cell col-last val-put${activeClass}" data-option-symbol="${symbol}">
            <div class="opt-price">${markP}</div>
            <div class="opt-sub-iv">${ivStr}</div>
        </td>
        <td class="opt-cell col-bid val-put${activeClass}" data-option-symbol="${symbol}">
            <div class="opt-price">${bidP}</div>
            <div class="opt-sub-iv">${bidIv}</div>
        </td>
        <td class="opt-cell col-ask val-put${activeClass}" data-option-symbol="${symbol}">
            <div class="opt-price">${askP}</div>
            <div class="opt-sub-iv">${askIv}</div>
        </td>
        <td class="opt-cell col-vol${activeClass}" data-option-symbol="${symbol}">${volVal}</td>
        <td class="opt-cell col-oi${activeClass}" data-option-symbol="${symbol}">${oiVal}</td>
    `;
}

function renderChainError(message) {
    const tbody = document.getElementById('optionsChainBody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="13" class="empty-row">${escapeHtml(message)}</td></tr>`;
}

function openOptionsMobileDrawer() {
    const orderCard = document.getElementById('optionOrderCard') || document.querySelector('.option-order-card');
    const backdrop = document.getElementById('optionsDrawerBackdrop');
    if (orderCard) {
        orderCard.classList.add('mobile-drawer-open');
        document.documentElement.classList.add('mobile-drawer-locked');
        document.body.classList.add('mobile-drawer-locked');
        orderCard.scrollTop = 0;

        // Reset tab according to screen size
        setDrawerActiveTab('ticket');
        renderOrderBook(optionsState.lastOrderBookData);
    }
    if (backdrop) {
        backdrop.hidden = false;
        backdrop.removeAttribute('hidden');
    }
}

function setDrawerActiveTab(tabName) {
    const isTablet = window.innerWidth >= 768 && window.innerWidth <= 1023;
    const ticketPane = document.getElementById('drawerTicketPane');
    const obPane = document.getElementById('drawerOrderbookPane');
    const categoryEl = document.getElementById('optionOrderTicketCategory');

    if (isTablet) {
        if (ticketPane) {
            ticketPane.hidden = false;
            ticketPane.removeAttribute('hidden');
        }
        if (obPane) {
            obPane.hidden = false;
            obPane.removeAttribute('hidden');
        }
        if (categoryEl) categoryEl.textContent = 'Order Ticket & Book';
        renderOrderBook(optionsState.lastOrderBookData);
        return;
    }

    document.querySelectorAll('.drawer-swap-tab').forEach(b => {
        b.classList.toggle('is-active', b.dataset.drawerTab === tabName);
    });

    if (tabName === 'orderbook') {
        if (ticketPane) ticketPane.hidden = true;
        if (obPane) {
            obPane.hidden = false;
            obPane.removeAttribute('hidden');
        }
        if (categoryEl) categoryEl.textContent = 'Order Book';
        renderOrderBook(optionsState.lastOrderBookData);
    } else {
        if (ticketPane) {
            ticketPane.hidden = false;
            ticketPane.removeAttribute('hidden');
        }
        if (obPane) obPane.hidden = true;
        if (categoryEl) categoryEl.textContent = 'Order Ticket';
    }
}

function setupOptionsDrawerSwapTabs() {
    document.querySelectorAll('.drawer-swap-tab').forEach(tabBtn => {
        tabBtn.addEventListener('click', () => {
            const targetTab = tabBtn.dataset.drawerTab || 'ticket';
            setDrawerActiveTab(targetTab);
        });
    });

    window.addEventListener('resize', () => {
        if (document.getElementById('optionOrderCard')?.classList.contains('mobile-drawer-open')) {
            setDrawerActiveTab('ticket');
        }
    });
}

function closeOptionsMobileDrawer() {
    const orderCard = document.getElementById('optionOrderCard') || document.querySelector('.option-order-card');
    const backdrop = document.getElementById('optionsDrawerBackdrop');
    if (orderCard) {
        orderCard.classList.remove('mobile-drawer-open');
        document.documentElement.classList.remove('mobile-drawer-locked');
        document.body.classList.remove('mobile-drawer-locked');
    }
    if (backdrop) {
        backdrop.hidden = true;
    }
    // Deselect contract and remove highlight border from cell
    optionsState.selectedContract = null;
    renderChain(optionsState.rows);
}

function setupOptionsMobileDrawerControls() {
    const btnClose = document.getElementById('btnCloseOptionsMobileOrderPanel');
    const handleBar = document.getElementById('optionsDrawerHandleBar');
    const backdrop = document.getElementById('optionsDrawerBackdrop');

    if (btnClose) {
        btnClose.addEventListener('click', closeOptionsMobileDrawer);
        btnClose.addEventListener('touchend', (e) => { e.preventDefault(); closeOptionsMobileDrawer(); });
    }
    if (handleBar) {
        handleBar.addEventListener('click', closeOptionsMobileDrawer);
        handleBar.addEventListener('touchend', (e) => { e.preventDefault(); closeOptionsMobileDrawer(); });
    }
    if (backdrop) {
        backdrop.addEventListener('click', closeOptionsMobileDrawer);
        backdrop.addEventListener('touchend', (e) => { e.preventDefault(); closeOptionsMobileDrawer(); });
    }

    setupOptionsDrawerSwapTabs();
}

function showOptionOrderCard(show = true) {
    const card = document.querySelector('.option-order-card');
    const contractCard = document.querySelector('.selected-contract-card');
    if (card) {
        card.hidden = false;
        card.style.display = 'block';
    }
    if (contractCard) {
        contractCard.hidden = false;
        contractCard.style.display = 'block';
    }
}

function setOptionOrderSide(side) {
    const sideInput = document.getElementById('optionOrderSide');
    if (sideInput) sideInput.value = side === 'sell' ? 'sell' : 'buy';

    document.querySelectorAll('.bitpro-side-toggle-tabs button[data-side], .option-side-tabs button[data-side]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.side === sideInput?.value);
    });

    const submit = document.getElementById('optionPlaceOrderButton');
    if (submit) {
        const contractType = optionsState.selectedContract ? (optionsState.selectedContract.side || inferContractType(optionsState.selectedContract.symbol) || 'Call') : 'Call';
        const typeLabel = String(contractType).toUpperCase().includes('PUT') ? 'Put' : 'Call';
        const isSell = sideInput?.value === 'sell';
        submit.textContent = isSell ? `Sell ${typeLabel}` : `Buy ${typeLabel}`;
        submit.className = `bitpro-action-submit-btn ${isSell ? 'sell' : 'buy'}`;
    }
}

function selectContract(contract, loadActivity = true, sourceCell = null, openDrawer = true) {
    optionsState.selectedContract = contract;
    showOptionOrderCard(true);

    const contractType = String(contract.side || inferContractType(contract.symbol) || 'Call').toUpperCase().includes('PUT') ? 'Put' : 'Call';
    const expiryKey = optionsState.expiry || '';
    const formattedInst = `${optionsState.underlying.replace('USDT', '-USD')} ${expiryKey} ${formatStrike(contract.strike_price || contract.strike)} ${contractType}`;

    setText('selectedInstrumentLabel', formattedInst);
    setText('selectedContractText', contract.symbol);
    setText('contractTitle', contract.symbol);
    setText('optionOrderTicketSymbol', `${contract.symbol}`);
    setText('contractMarkPrice', formatUsdPrice(contract.mark_price || contract.last_price, 4));
    setText('contractIv', formatGreek(contract.mark_iv));
    setText('contractDelta', formatGreek(contract.delta));
    setText('contractGamma', formatGreek(contract.gamma));
    setText('contractTheta', formatGreek(contract.theta));
    setText('contractVega', formatGreek(contract.vega));
    setText('optionsOrderBookSymbolTitle', `Order Book - ${contract.symbol}`);
    setText('mobileOptionsOrderBookSymbolTitle', `Order Book - ${contract.symbol}`);
    document.querySelectorAll('#futuresOrderbookPanel .base-asset-label, #drawerOrderbookPane .base-asset-label, .base-asset-label').forEach(el => {
        el.textContent = contract.symbol.split('-')[0] || 'BTC';
    });

    const side = contractType === 'Call' ? 'buy' : 'sell';
    setOptionOrderSide(side);

    const quantityInput = document.getElementById('optionOrderQuantity');
    if (quantityInput && !Number(quantityInput.value || 0)) {
        quantityInput.value = '1';
    }

    const selectedPrice = Number(
        sourceCell?.classList?.contains('bid') ? contract.bid_price :
        sourceCell?.classList?.contains('ask') ? contract.ask_price :
        contract.mark_price || contract.last_price || 0
    );

    const priceInput = document.getElementById('optionOrderPrice');
    if (priceInput && getOptionOrderType() === 'market') {
        priceInput.value = '';
    } else if (priceInput && selectedPrice) {
        priceInput.value = selectedPrice;
    }

    updateOptionOrderTypeFields();
    renderChain(optionsState.rows);
    updatePremiumEstimate();
    updatePageDocumentTitle();

    if (loadActivity) {
        loadDepth(contract.symbol);
        if (OPTIONS_WEBSOCKET_ENABLED) connectSelectedContractSocket(contract.symbol);
    }

    if (openDrawer && window.innerWidth <= 1024) {
        openOptionsMobileDrawer();
    }
}

function setupOrderBookControls() {
    document.querySelectorAll('#futuresOrderbookPanel .ob-mode-btn, #drawerOrderbookPane .ob-mode-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetMode = btn.dataset.obMode || 'both';
            document.querySelectorAll('#futuresOrderbookPanel .ob-mode-btn, #drawerOrderbookPane .ob-mode-btn').forEach(b => {
                b.classList.toggle('is-active', (b.dataset.obMode || 'both') === targetMode);
            });
            optionsState.obMode = targetMode;
            renderOrderBook(optionsState.lastOrderBookData);
        });
    });

    const precSelect = document.getElementById('obPrecisionSelect');
    const mobilePrecSelect = document.getElementById('mobileObPrecisionSelect');

    const handlePrecisionChange = (val) => {
        const p = Number(val) || 0.1;
        optionsState.obPrecision = p;
        if (precSelect) precSelect.value = String(p);
        if (mobilePrecSelect) mobilePrecSelect.value = String(p);
        renderOrderBook(optionsState.lastOrderBookData);
    };

    if (precSelect) {
        precSelect.addEventListener('change', () => handlePrecisionChange(precSelect.value));
    }
    if (mobilePrecSelect) {
        mobilePrecSelect.addEventListener('change', () => handlePrecisionChange(mobilePrecSelect.value));
    }
}

async function loadContractActivity(symbol) {
    await loadDepth(symbol);
}

async function loadDepth(symbol = null) {
    const activeSymbol = symbol || (optionsState.selectedContract ? optionsState.selectedContract.symbol : optionsState.underlying);
    if (!activeSymbol) return;

    // 1. Fetch & Render Order Book Depth
    try {
        let depthData = { asks: [], bids: [] };
        try {
            const depthRes = await fetch(`/api/options/symbol/${encodeURIComponent(activeSymbol)}/depth?limit=20`, { headers: { Accept: 'application/json' } });
            if (depthRes.ok) {
                const depthJson = await depthRes.json();
                if (depthJson.success && depthJson.data) {
                    depthData = depthJson.data;
                }
            }
        } catch (err) {}

        if ((!depthData.asks || !depthData.asks.length) && (!depthData.bids || !depthData.bids.length)) {
            try {
                const fallbackRes = await fetch(`/api/markets/futures/${encodeURIComponent(optionsState.underlying)}/depth`, { headers: { Accept: 'application/json' } });
                if (fallbackRes.ok) {
                    const fbJson = await fallbackRes.json();
                    if (fbJson.success && fbJson.data) {
                        depthData = fbJson.data;
                    }
                }
            } catch (err) {}
        }

        optionsState.lastOrderBookData = depthData;
        renderOrderBook(depthData);
    } catch (e) {
        console.warn('Failed to load options orderbook:', e);
    }

    // 2. Fetch & Render Recent Trades
    try {
        const extractTradesFromResponse = (json) => {
            if (!json || !json.data) return [];
            if (Array.isArray(json.data)) return json.data;
            if (Array.isArray(json.data.trades)) return json.data.trades;
            return [];
        };

        let tradesData = [];
        try {
            const tradesRes = await fetch(`/api/options/symbol/${encodeURIComponent(activeSymbol)}/trades?limit=18`, { headers: { Accept: 'application/json' } });
            if (tradesRes.ok) {
                const tradesJson = await tradesRes.json();
                tradesData = extractTradesFromResponse(tradesJson);
            }
        } catch (err) {}

        if (!tradesData || !tradesData.length) {
            try {
                const fallbackTradesRes = await fetch(`/api/markets/futures/${encodeURIComponent(optionsState.underlying)}/trades`, { headers: { Accept: 'application/json' } });
                if (fallbackTradesRes.ok) {
                    const fbTradesJson = await fallbackTradesRes.json();
                    tradesData = extractTradesFromResponse(fbTradesJson);
                }
            } catch (err) {}
        }

        const contractPrice = getSelectedContractMarketPrice() || Number(optionsState.lastObPrice || 0);
        if (contractPrice > 0 && tradesData.length && tradesData[0].price > (contractPrice * 5)) {
            const now = Date.now();
            tradesData = Array.from({ length: 12 }, (_, i) => {
                const isBuy = i % 2 === 0;
                const offset = (Math.random() - 0.5) * (contractPrice * 0.004);
                return {
                    price: Math.max(0.01, contractPrice + offset),
                    quantity: Math.max(0.001, Number((Math.random() * 1.5).toFixed(4))),
                    time: now - (i * 1200),
                    is_buyer_maker: !isBuy
                };
            });
        }

        renderRecentTrades(tradesData);
    } catch (e) {
        console.warn('Failed to load options trades:', e);
    }
}

function renderOrderBook(data = null) {
    const currentData = data || optionsState.lastOrderBookData || {};
    const asksWrap = document.getElementById('orderbookAsks');
    const bidsWrap = document.getElementById('orderbookBids');
    const midPriceEl = document.getElementById('obMidPrice');
    const markEl = document.getElementById('obMarkPrice');

    const mobileAsksWrap = document.getElementById('mobileOrderbookAsks');
    const mobileBidsWrap = document.getElementById('mobileOrderbookBids');
    const mobileMidPriceEl = document.getElementById('mobileObMidPrice');
    const mobileMarkEl = document.getElementById('mobileObMarkPrice');

    let asks = Array.from(currentData.asks || []).map(i => Array.isArray(i) ? { price: Number(i[0]), quantity: Number(i[1]) } : i);
    let bids = Array.from(currentData.bids || []).map(i => Array.isArray(i) ? { price: Number(i[0]), quantity: Number(i[1]) } : i);

    asks.sort((a, b) => a.price - b.price);
    bids.sort((a, b) => b.price - a.price);

    const mode = optionsState.obMode || 'both';
    const precision = Number(optionsState.obPrecision) || 0.1;

    let targetAsksCount = mode === 'asks' ? 10 : (mode === 'bids' ? 0 : 5);
    let targetBidsCount = mode === 'bids' ? 10 : (mode === 'asks' ? 0 : 5);

    if (precision > 0.1) {
        asks = aggregateDepthByPrecision(asks, precision, 'ask');
        bids = aggregateDepthByPrecision(bids, precision, 'bid');
    }

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

    const maxVolume = Math.max(...asksWithTotals.map(i => i.total), ...bidsWithTotals.map(i => i.total), 1);

    const asksHtml = asksWithTotals.slice().reverse().map(row => {
        const depthWidth = Math.min(100, Math.round((row.total / maxVolume) * 100));
        return `
            <div class="ob-row ask" data-price="${row.price}">
                <div class="ob-depth-bar" style="width: ${depthWidth}%;"></div>
                <span class="ob-price">${formatUsdPrice(row.price)}</span>
                <span class="ob-size">${formatNumber(row.quantity, 4)}</span>
                <span class="ob-total">${formatNumber(row.total, 4)}</span>
            </div>
        `;
    }).join('');

    const bidsHtml = bidsWithTotals.map(row => {
        const depthWidth = Math.min(100, Math.round((row.total / maxVolume) * 100));
        return `
            <div class="ob-row bid" data-price="${row.price}">
                <div class="ob-depth-bar" style="width: ${depthWidth}%;"></div>
                <span class="ob-price">${formatUsdPrice(row.price)}</span>
                <span class="ob-size">${formatNumber(row.quantity, 4)}</span>
                <span class="ob-total">${formatNumber(row.total, 4)}</span>
            </div>
        `;
    }).join('');

    // Desktop elements
    if (asksWrap && bidsWrap) {
        asksWrap.style.display = mode === 'bids' ? 'none' : 'flex';
        asksWrap.innerHTML = asksHtml;

        bidsWrap.style.display = mode === 'asks' ? 'none' : 'flex';
        bidsWrap.innerHTML = bidsHtml;
    }

    // Mobile drawer elements
    if (mobileAsksWrap && mobileBidsWrap) {
        mobileAsksWrap.style.display = mode === 'bids' ? 'none' : 'flex';
        mobileAsksWrap.innerHTML = asksHtml;

        mobileBidsWrap.style.display = mode === 'asks' ? 'none' : 'flex';
        mobileBidsWrap.innerHTML = bidsHtml;

        // Allow clicking row to set price & switch back to order ticket
        const setupRowClick = (wrap) => {
            wrap.querySelectorAll('.ob-row').forEach(row => {
                row.addEventListener('click', () => {
                    const p = row.dataset.price;
                    const priceInput = document.getElementById('optionOrderPrice');
                    if (priceInput && p && Number(p) > 0) {
                        priceInput.value = p;
                        updatePremiumEstimate();
                        setDrawerActiveTab('ticket');
                    }
                });
            });
        };
        setupRowClick(mobileAsksWrap);
        setupRowClick(mobileBidsWrap);
    }

    const activeContract = optionsState.selectedContract;
    const livePrice = Number(activeContract?.mark_price || activeContract?.last_price || asks[0]?.price || bids[0]?.price || 0);

    const updatePriceEl = (el, arrowEl) => {
        if (!el || !livePrice) return;
        el.textContent = formatUsdPrice(livePrice);
        if (optionsState.lastObPrice !== null) {
            el.classList.remove('up', 'down');
            if (arrowEl) arrowEl.classList.remove('up', 'down');

            if (livePrice > optionsState.lastObPrice) {
                el.classList.add('up');
                if (arrowEl) arrowEl.classList.add('up');
            } else if (livePrice < optionsState.lastObPrice) {
                el.classList.add('down');
                if (arrowEl) arrowEl.classList.add('down');
            }
        }
    };

    updatePriceEl(midPriceEl, document.getElementById('obMidArrow'));
    updatePriceEl(mobileMidPriceEl, document.getElementById('mobileObMidArrow'));

    if (livePrice) {
        optionsState.lastObPrice = livePrice;
    }

    if (markEl && livePrice) markEl.textContent = formatUsdPrice(livePrice);
    if (mobileMarkEl && livePrice) mobileMarkEl.textContent = formatUsdPrice(livePrice);
}

function aggregateDepthByPrecision(rows, precision, side) {
    const buckets = {};
    rows.forEach(item => {
        const price = item.price;
        const qty = item.quantity;
        let bPrice;
        if (side === 'ask') {
            bPrice = Math.ceil(price / precision) * precision;
        } else {
            bPrice = Math.floor(price / precision) * precision;
        }
        bPrice = Number(bPrice.toFixed(4));
        buckets[bPrice] = (buckets[bPrice] || 0) + qty;
    });

    return Object.keys(buckets).map(p => ({
        price: Number(p),
        quantity: buckets[p]
    }));
}

function padDepthRows(rows, count, side, precision) {
    if (!count) return [];
    return rows.slice(0, count);
}

function renderRecentTrades(trades) {
    const box = document.getElementById('recentTradesBody');
    if (!box) return;

    let tradesList = Array.isArray(trades) ? trades : [];

    if (!tradesList.length) {
        const basePrice = Number(optionsState.selectedContract ? (optionsState.selectedContract.mark_price || optionsState.selectedContract.last_price) : (optionsState.lastObPrice || 64300));
        const now = Date.now();
        tradesList = Array.from({ length: 12 }, (_, i) => {
            const isBuy = i % 2 === 0;
            const offset = (Math.random() - 0.5) * (basePrice * 0.002);
            return {
                price: Math.max(0.01, basePrice + offset),
                quantity: Math.max(0.001, Number((Math.random() * 0.3).toFixed(4))),
                time: now - (i * 1500),
                is_buyer_maker: !isBuy
            };
        });
    }

    box.innerHTML = tradesList.slice(0, 18).map(trade => {
        const isBuyerMaker = trade.isBuyerMaker !== undefined ? trade.isBuyerMaker : (trade.is_buyer_maker !== undefined ? trade.is_buyer_maker : (String(trade.side || trade.S || '').toLowerCase() === 'sell'));
        const tradeClass = isBuyerMaker ? 'sell' : 'buy';
        const price = Number(trade.price || trade.p || 0);
        const qty = Number(trade.qty !== undefined ? trade.qty : (trade.quantity !== undefined ? trade.quantity : (trade.q || 0)));
        const time = trade.time || trade.T || Date.now();

        return `
            <div class="rt-row ${tradeClass}">
                <span class="rt-price">${formatUsdPrice(price)}</span>
                <span class="rt-size">${formatNumber(qty, 4)}</span>
                <span class="rt-time">${formatTime(time)}</span>
            </div>
        `;
    }).join('');
}

function setupExpirySelect() {
    const select = document.getElementById('expirySelect');
    if (!select) return;

    select.addEventListener('change', () => {
        optionsState.expiry = select.value;
        optionsState.selectedContract = null;
        setText('selectedContractText', '--');
        showOptionOrderCard(false);
        loadOptionsChain();
    });
}

function setupStrikeSearch() {
    const input = document.getElementById('strikeSearch');
    if (input) input.addEventListener('input', () => renderChain(optionsState.rows));
}

function setupOrderTabs() {
    document.querySelectorAll('.order-tabs button').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.order-tabs button').forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            const sideInput = document.getElementById('optionOrderSide');
            if (sideInput) sideInput.value = button.dataset.side || 'buy';
            updateSubmitButtonSide();
            updatePremiumEstimate();
        });
    });

    const orderType = document.getElementById('optionOrderType');
    if (orderType) {
        orderType.addEventListener('change', () => {
            updateOptionOrderTypeFields();
            updatePremiumEstimate();
        });
    }

    const form = document.getElementById('optionOrderForm');
    if (form) {
        form.addEventListener('input', updatePremiumEstimate);
        form.addEventListener('submit', submitOptionOrder);
    }

    updateSubmitButtonSide();
    updateOptionOrderTypeFields();
    updatePremiumEstimate();
}

function updateSubmitButtonSide() {
    const side = document.getElementById('optionOrderSide')?.value || 'buy';
    const button = document.getElementById('optionPlaceOrderButton');
    if (!button) return;

    button.textContent = side === 'sell' ? 'Sell Option' : 'Buy Option';
    button.classList.toggle('is-sell', side === 'sell');
}

function getOptionOrderType() {
    return document.getElementById('optionOrderType')?.value || 'market';
}

function getSelectedContractMarketPrice() {
    return Number(optionsState.selectedContract?.mark_price || optionsState.selectedContract?.last_price || optionsState.selectedContract?.ask_price || optionsState.selectedContract?.bid_price || 0);
}

function getOptionExecutionPrice() {
    const orderType = getOptionOrderType();
    if (orderType === 'limit') {
        return Number(normalizeOptionNumberString(document.getElementById('optionOrderPrice')?.value || 0));
    }

    return getSelectedContractMarketPrice();
}

function updateOptionOrderTypeFields() {
    const isLimit = getOptionOrderType() === 'limit';
    const priceField = document.getElementById('optionPriceField');
    const priceInput = document.getElementById('optionOrderPrice');
    const hint = document.getElementById('optionOrderPriceHint');

    if (priceField) priceField.hidden = !isLimit;
    if (priceInput) priceInput.required = isLimit;
    if (hint) hint.textContent = isLimit ? 'Enter your limit price' : 'Market uses live mark price';
}

function updatePremiumEstimate() {
    const price = getOptionExecutionPrice();
    const quantity = Number(normalizeOptionNumberString(document.getElementById('optionOrderQuantity')?.value || 0));
    const premium = price * quantity;
    const formattedVal = premium > 0 ? `${formatUsdtWithUnit(premium)}` : '--';
    setText('optionPremiumEstimate', premium > 0 ? formatUsdt(premium) : '--');
    setText('optionOrderValueDisplay', formattedVal);
    setText('optionMaxCostDisplay', formattedVal);
}

async function submitOptionOrder(event) {
    event.preventDefault();

    if (!getAccessToken()) {
        setOrderMessage('Please login first to place demo order.', 'error');
        showOptionsToast('Please login first.', 'error');
        return;
    }

    if (!optionsState.selectedContract) {
        setOrderMessage('Select an option contract first.', 'error');
        return;
    }

    const orderType = getOptionOrderType();
    const price = getOptionExecutionPrice();
    const quantity = Number(normalizeOptionNumberString(document.getElementById('optionOrderQuantity')?.value || 0));

    if (!quantity || quantity <= 0) {
        setOrderMessage('Enter a valid quantity.', 'error');
        showOptionsToast('Enter a valid quantity.', 'error');
        return;
    }

    if (!price || price <= 0) {
        const message = orderType === 'limit'
            ? 'Enter a valid limit price.'
            : 'Live market price is not available for this contract. Select another contract or refresh.';
        setOrderMessage(message, 'error');
        showOptionsToast(message, 'error');
        return;
    }

    const payload = {
        underlying: optionsState.underlying,
        option_symbol: optionsState.selectedContract.symbol,
        contract_type: inferContractType(optionsState.selectedContract.symbol),
        side: document.getElementById('optionOrderSide')?.value || 'buy',
        order_type: orderType,
        price: orderType === 'limit' ? price : null,
        last_price: getSelectedContractMarketPrice() || price,
        quantity,
        take_profit: Number(normalizeOptionNumberString(document.getElementById('optionTakeProfit')?.value || 0)) || null,
        stop_loss: Number(normalizeOptionNumberString(document.getElementById('optionStopLoss')?.value || 0)) || null,
        client_order_id: `opt-${Date.now()}-${Math.random().toString(16).slice(2)}`,
    };

    const submit = document.getElementById('optionPlaceOrderButton');
    submit?.setAttribute('disabled', 'disabled');
    setOrderMessage('Placing option order...', 'info');

    try {
        const result = await authFetch('/api/options/demo-orders', {
            method: 'POST',
            body: JSON.stringify(payload),
        });

        const position = result.data?.position || null;
        const order = result.data?.order || null;

        if (position) {
            optionsState.positions = [position, ...optionsState.positions.filter((item) => item.position_uid !== position.position_uid)];
        }

        if (order) {
            optionsState.orders = [order, ...optionsState.orders.filter((item) => item.order_uid !== order.order_uid)];
        }

        resetOptionOrderForm();
        showOptionOrderCard(false);
        closeOptionsMobileDrawer();
        optionsState.selectedContract = null;
        setText('selectedContractText', '--');
        renderChain(optionsState.rows);
        renderBottomTable(optionsState.activeBottomTab);

        showOptionsToast(result.message || 'Option order placed successfully.', 'success');
        loadWalletData();
        loadProtectedOptionsData();
    } catch (error) {
        setOrderMessage(error.message || 'Unable to place option order.', 'error');
        showOptionsToast(error.message || 'Unable to place option order.', 'error');
    } finally {
        submit?.removeAttribute('disabled');
    }
}

function resetOptionOrderForm() {
    const quantityInput = document.getElementById('optionOrderQuantity');

    const priceInput = document.getElementById('optionOrderPrice');

    if (quantityInput) quantityInput.value = '';
    if (priceInput) priceInput.value = '';

    setText('optionPremiumEstimate', '--');

    setOrderMessage('Order placed successfully. Enter quantity for next order.', 'success');

    updatePremiumEstimate();
}

function setOrderMessage(message, type = 'info') {
    const box = document.getElementById('optionOrderMessage');
    if (!box) return;
    box.classList.remove('error', 'success');
    if (type === 'error') box.classList.add('error');
    if (type === 'success') box.classList.add('success');
    box.textContent = message;
}

function setupBottomTabs() {
    document.querySelectorAll('.bottom-tabs button').forEach((button) => {
        button.addEventListener('click', async () => {
            document.querySelectorAll('.bottom-tabs button').forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            optionsState.activeBottomTab = button.dataset.tab || 'positions';
            
            if (optionsState.activeBottomTab === 'orderHistory') {
                await loadOptionsOrders(optionsOrdersPage);
            } else if (optionsState.activeBottomTab === 'tradeHistory') {
                await loadOptionsTrades(optionsTradesPage);
            }
            
            renderBottomTable(optionsState.activeBottomTab);
        });
    });
}

function setupActivityTabs() {
    document.querySelectorAll('[data-activity-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-activity-tab]').forEach((item) => item.classList.remove('is-active'));
            button.classList.add('is-active');
            document.getElementById('optionOrderBookPanel')?.classList.toggle('is-active', button.dataset.activityTab === 'book');
            document.getElementById('optionRecentTradesPanel')?.classList.toggle('is-active', button.dataset.activityTab === 'trades');
        });
    });
}


function setupCloseAllButton() {
    const button = document.getElementById('optionsCloseAllButton');
    if (!button) return;

    button.addEventListener('click', closeAllOptionsPositions);
}

async function closeAllOptionsPositions() {
    if (!optionsState.positions.length) {
        showOptionsToast('No open option positions to close.', 'info');
        return;
    }

    const positionsToClose = [...optionsState.positions];
    optionsState.positions = [];
    renderBottomTable(optionsState.activeBottomTab);

    try {
        await authFetch('/api/options/positions/close-all', {
            method: 'POST',
            body: JSON.stringify({ positions: positionsToClose.map((position) => ({
                position_uid: position.position_uid,
                exit_price: getOptionPrice(position.option_symbol, position.entry_price),
            })) }),
        });
        showOptionsToast('All open option positions closed.', 'success');
        loadWalletData();
        loadProtectedOptionsData();
    } catch (error) {
        showOptionsToast(error.message || 'Unable to close all option positions.', 'error');
        optionsState.positions = positionsToClose;
        renderBottomTable(optionsState.activeBottomTab);
        loadProtectedOptionsData();
    }
}

let optionsOrdersPage = 1;
let optionsTradesPage = 1;
let optionsOrdersPagination = null;
let optionsTradesPagination = null;

async function loadProtectedOptionsData() {
    if (!getAccessToken()) {
        renderBottomTable(optionsState.activeBottomTab);
        return;
    }

    await Promise.all([
        loadOptionsPositions(),
        loadOptionsOrders(optionsOrdersPage),
        loadOptionsTrades(optionsTradesPage)
    ]);
    renderBottomTable(optionsState.activeBottomTab);
}

async function loadOptionsPositions() {
    try {
        const result = await authFetch('/api/options/positions');
        optionsState.positions = result.data?.positions || [];
    } catch (error) {
        optionsState.positions = [];
    }
}

async function loadOptionsOrders(page = 1) {
    optionsOrdersPage = page;
    try {
        const result = await authFetch(`/api/options/orders?paginate=true&page=${page}`);
        optionsState.orders = result.data?.orders || [];
        optionsOrdersPagination = result.data?.pagination || null;
    } catch (error) {
        optionsState.orders = [];
        optionsOrdersPagination = null;
    }
}

async function loadOptionsTrades(page = 1) {
    optionsTradesPage = page;
    try {
        const result = await authFetch(`/api/options/trades?paginate=true&page=${page}`);
        optionsState.trades = result.data?.trades || [];
        optionsTradesPagination = result.data?.pagination || null;
    } catch (error) {
        optionsState.trades = [];
        optionsTradesPagination = null;
    }
}

function renderBottomTable(tab) {
    const posBody = document.getElementById('optionsPositionsBody');
    if (posBody) renderPositionsTable(posBody, 8);

    const ordersBody = document.getElementById('optionsOpenOrdersBody');
    if (ordersBody) renderOpenOrdersTable(ordersBody, 8);

    const posCountEl = document.getElementById('optionsPosCount');
    const topPosBadge = document.getElementById('topPosBadge');
    if (posCountEl) posCountEl.textContent = optionsState.positions.length;
    if (topPosBadge) topPosBadge.textContent = optionsState.positions.length ? `(${optionsState.positions.length})` : '';

    const openCount = optionsState.orders.filter(o => o.status === 'open').length;
    const ordersCountEl = document.getElementById('optionsOpenOrdersCount');
    const topOrdersBadge = document.getElementById('topOrdersBadge');
    if (ordersCountEl) ordersCountEl.textContent = openCount;
    if (topOrdersBadge) topOrdersBadge.textContent = openCount ? `(${openCount})` : '';

    const closeAll = document.getElementById('optionsCloseAllButton');
    if (closeAll) closeAll.hidden = !optionsState.positions.length;
}

function getOptionCurrencyTagHtml(currency) {
    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_base_currency') || 'USD');
    let tagCur = String(currency || activeCur).toUpperCase();
    if (tagCur === 'USDT' || tagCur === 'USD') tagCur = isActiveCentAccount() ? 'USC' : 'USD';
    const isInr = tagCur === 'INR';
    const tagClass = isInr ? 'tag-inr' : 'tag-usd';
    return `<span class="currency-tag ${tagClass}">${tagCur}</span>`;
}

function renderPositionsTable(body, colspan) {
    const cardList = document.getElementById('optionsPositionsCardsList');
    const posCountEl = document.getElementById('optionsPosCount');
    if (posCountEl) {
        posCountEl.textContent = optionsState.positions.length ? `(${optionsState.positions.length})` : '';
    }

    if (!optionsState.positions.length) {
        body.innerHTML = `<tr><td colspan="${colspan}">No open option positions.</td></tr>`;
        if (cardList) {
            cardList.innerHTML = '<div class="futures-empty">No open option positions.</div>';
        }
        return;
    }

    body.innerHTML = optionsState.positions.map((position) => {
        const currentPrice = getOptionPrice(position.option_symbol, position.entry_price);
        const pnl = calculateOptionPnl(position, currentPrice);
        const pnlClass = pnl >= 0 ? 'market-positive' : 'market-negative';

        return `
            <tr>
                <td>${escapeHtml(position.option_symbol || '--')} ${getOptionCurrencyTagHtml(position.currency)}</td>
                <td class="${position.side === 'buy' ? 'market-positive' : 'market-negative'}">${escapeHtml((position.side || '--').toUpperCase())}</td>
                <td>${formatUsdPrice(position.entry_price, 4)}</td>
                <td>${formatQuantity(position.quantity)}</td>
                <td>${formatUsdt(position.margin)}</td>
                <td class="${pnlClass}">${formatUsdt(pnl)}</td>
                <td><span class="status-pill open">open</span></td>
                <td><button type="button" class="opt-table-danger-btn" data-close-option-position="${escapeHtml(position.position_uid)}">Close</button></td>
            </tr>
        `;
    }).join('');

    if (cardList) {
        cardList.innerHTML = optionsState.positions.map((position) => {
            const currentPrice = getOptionPrice(position.option_symbol, position.entry_price);
            const isBuy = String(position.side || 'buy').toLowerCase() === 'buy';
            const sideText = isBuy ? 'Buy' : 'Sell';
            const sideClass = isBuy ? 'market-positive' : 'market-negative';
            const pnl = calculateOptionPnl(position, currentPrice);
            const pnlClass = pnl >= 0 ? 'market-positive' : 'market-negative';
            const coinLogo = getOptionCoinLogo(position.option_symbol);
            const cur = String(position.currency || (window.getActiveCurrency ? window.getActiveCurrency() : 'USD')).toUpperCase();
            const isINR = cur === 'INR';
            const tagLabel = isINR ? 'INR' : (isActiveCentAccount() ? 'USC' : (cur === 'USDT' ? 'USD' : cur));
            const entryPriceText = formatUsdPrice(position.entry_price, 2);
            const markPriceText = formatUsdPrice(currentPrice, 2);
            const qtyText = formatQuantity(position.quantity);
            const pnlText = `${pnl >= 0 ? '+' : '-'}${formatUsdt(Math.abs(pnl))}`;

            return `
                <div class="opt-mobile-card" data-position-row="${escapeHtml(position.position_uid)}">
                    <div class="opt-compact-row-top">
                        <div class="opt-compact-left">
                            <img src="${coinLogo}" alt="" class="opt-coin-icon" onerror="this.onerror=null;this.src='https://assets.coingecko.com/coins/images/1/large/bitcoin.png';" />
                            <span class="opt-sym-text">${escapeHtml(position.option_symbol)}</span>
                            <span class="opt-badge cur">${escapeHtml(tagLabel)}</span>
                            <span class="opt-badge side ${sideClass}">${sideText} ${qtyText}</span>
                        </div>
                        <div class="opt-compact-right">
                            <strong class="opt-pnl-text ${pnlClass}">${pnlText}</strong>
                            <button type="button" class="opt-table-danger-btn" data-close-option-position="${escapeHtml(position.position_uid)}" title="Close Position">Close</button>
                        </div>
                    </div>
                    <div class="opt-compact-row-bottom">
                        <span>Entry: <strong>${entryPriceText}</strong></span>
                        <span>Mark: <strong>${markPriceText}</strong></span>
                    </div>
                </div>
            `;
        }).join('');
    }

    document.querySelectorAll('[data-close-option-position]').forEach((button) => {
        button.addEventListener('click', () => closeOptionPosition(button.dataset.closeOptionPosition));
    });

    updateOptionsLiveWalletMetrics();
}

function renderOpenOrdersTable(body, colspan) {
    const openOrders = optionsState.orders.filter((order) => order.status === 'open');
    const cardList = document.getElementById('optionsPositionsCardsList');

    if (!openOrders.length) {
        body.innerHTML = `<tr><td colspan="${colspan}">No open option orders.</td></tr>`;
        if (cardList) {
            cardList.innerHTML = '<div class="futures-empty">No open option orders.</div>';
        }
        return;
    }

    body.innerHTML = openOrders.map((order) => `
        <tr>
            <td>${escapeHtml(order.option_symbol || '--')} ${getOptionCurrencyTagHtml(order.currency)}</td>
            <td class="${order.side === 'buy' ? 'market-positive' : 'market-negative'}">${escapeHtml((order.side || '--').toUpperCase())}</td>
            <td>${escapeHtml(order.order_type || '--')}</td>
            <td>${formatUsdPrice(order.price || order.execution_price, 4)}</td>
            <td>${formatQuantity(order.quantity)}</td>
            <td>${formatUsdt(order.margin)}</td>
            <td><span class="status-pill open">open</span></td>
            <td><button type="button" class="opt-table-danger-btn" data-cancel-option-order="${escapeHtml(order.order_uid)}">Cancel</button></td>
        </tr>
    `).join('');

    if (cardList) {
        cardList.innerHTML = openOrders.map((order) => {
            const isBuy = String(order.side || 'buy').toLowerCase() === 'buy';
            const sideText = isBuy ? 'Buy' : 'Sell';
            const sideClass = isBuy ? 'market-positive' : 'market-negative';
            const coinLogo = getOptionCoinLogo(order.option_symbol);
            const cur = String(order.currency || (window.getActiveCurrency ? window.getActiveCurrency() : 'USD')).toUpperCase();
            const isINR = cur === 'INR';
            const tagLabel = isINR ? 'INR' : (isActiveCentAccount() ? 'USC' : (cur === 'USDT' ? 'USD' : cur));
            const orderType = String(order.order_type || 'Limit').toUpperCase();
            const priceVal = Number(order.price || order.execution_price || 0);
            const priceText = priceVal ? formatUsdPrice(priceVal, 2) : 'Market';
            const qtyText = formatQuantity(order.quantity);
            const marginText = formatUsdt(order.margin);

            return `
                <div class="opt-mobile-card">
                    <div class="opt-compact-row-top">
                        <div class="opt-compact-left">
                            <img src="${coinLogo}" alt="" class="opt-coin-icon" onerror="this.onerror=null;this.src='https://assets.coingecko.com/coins/images/1/large/bitcoin.png';" />
                            <span class="opt-sym-text">${escapeHtml(order.option_symbol || '--')}</span>
                            <span class="opt-badge cur">${escapeHtml(tagLabel)}</span>
                            <span class="opt-badge side ${sideClass}">${sideText} ${qtyText}</span>
                            <span class="opt-badge type">${escapeHtml(orderType)}</span>
                        </div>
                        <div class="opt-compact-right">
                            <span class="opt-status-pill open">OPEN</span>
                            <button type="button" class="opt-card-close-btn" data-cancel-option-order="${escapeHtml(order.order_uid)}" title="Cancel Order">✕</button>
                        </div>
                    </div>
                    <div class="opt-compact-row-bottom">
                        <span>Price: <strong>${priceText}</strong></span>
                        <span>Margin: <strong>${marginText}</strong></span>
                    </div>
                </div>
            `;
        }).join('');
    }

    document.querySelectorAll('[data-cancel-option-order]').forEach((button) => {
        button.addEventListener('click', () => cancelOptionOrder(button.dataset.cancelOptionOrder));
    });
}

function renderOrderHistoryTable(body, colspan) {
    const cardList = document.getElementById('optionsPositionsCardsList');

    if (!optionsState.orders.length) {
        body.innerHTML = `<tr><td colspan="${colspan}">No option order history.</td></tr>`;
        if (cardList) {
            cardList.innerHTML = '<div class="futures-empty">No option order history.</div>';
        }
        return;
    }

    body.innerHTML = optionsState.orders.map((order) => `
        <tr>
            <td>${escapeHtml(order.option_symbol || '--')} ${getOptionCurrencyTagHtml(order.currency)}</td>
            <td class="${order.side === 'buy' ? 'market-positive' : 'market-negative'}">${escapeHtml((order.side || '--').toUpperCase())}</td>
            <td>${escapeHtml(order.order_type || '--')}</td>
            <td>${formatUsdPrice(order.execution_price || order.price, 4)}</td>
            <td>${formatQuantity(order.quantity)}</td>
            <td>${formatUsdt(order.margin)}</td>
            <td>${formatUsdt(order.fee_charged || order.estimated_fee || 0)}</td>
            <td><span class="status-pill ${escapeHtml(order.status || '')}">${escapeHtml(order.status || '--')}</span></td>
            <td>${formatDate(order.created_at)}</td>
        </tr>
    `).join('');

    if (cardList) {
        cardList.innerHTML = optionsState.orders.map((order) => {
            const isBuy = String(order.side || 'buy').toLowerCase() === 'buy';
            const sideText = isBuy ? 'Buy' : 'Sell';
            const sideClass = isBuy ? 'market-positive' : 'market-negative';
            const coinLogo = getOptionCoinLogo(order.option_symbol);
            const cur = String(order.currency || (window.getActiveCurrency ? window.getActiveCurrency() : 'USD')).toUpperCase();
            const isINR = cur === 'INR';
            const tagLabel = isINR ? 'INR' : (isActiveCentAccount() ? 'USC' : (cur === 'USDT' ? 'USD' : cur));
            const orderType = String(order.order_type || 'Limit').toUpperCase();
            const rawStatus = String(order.status || 'filled').toUpperCase();
            const priceText = formatUsdPrice(order.execution_price || order.price, 2);
            const qtyText = formatQuantity(order.quantity);
            const feeText = formatUsdt(order.fee_charged || order.estimated_fee || 0);
            const marginText = formatUsdt(order.margin);
            const timeText = formatDate(order.created_at);

            return `
                <div class="opt-mobile-card">
                    <div class="opt-compact-row-top">
                        <div class="opt-compact-left">
                            <img src="${coinLogo}" alt="" class="opt-coin-icon" onerror="this.onerror=null;this.src='https://assets.coingecko.com/coins/images/1/large/bitcoin.png';" />
                            <span class="opt-sym-text">${escapeHtml(order.option_symbol || '--')}</span>
                            <span class="opt-badge cur">${escapeHtml(tagLabel)}</span>
                            <span class="opt-badge side ${sideClass}">${sideText} ${qtyText}</span>
                            <span class="opt-badge type">${escapeHtml(orderType)}</span>
                        </div>
                        <span class="opt-status-pill ${rawStatus.toLowerCase()}">${escapeHtml(rawStatus)}</span>
                    </div>
                    <div class="opt-compact-row-bottom">
                        <span>Price: <strong>${priceText}</strong></span>
                        <span>Fee: <strong>${feeText}</strong></span>
                        <span>Margin: <strong>${marginText}</strong></span>
                        <span class="opt-time-val">${escapeHtml(timeText)}</span>
                    </div>
                </div>
            `;
        }).join('');
    }
}

function renderTradeHistoryTable(body, colspan) {
    const cardList = document.getElementById('optionsPositionsCardsList');

    if (!optionsState.trades.length) {
        body.innerHTML = `<tr><td colspan="${colspan}">No option trade history.</td></tr>`;
        if (cardList) {
            cardList.innerHTML = '<div class="futures-empty">No option trade history.</div>';
        }
        return;
    }

    body.innerHTML = optionsState.trades.map((trade) => `
        <tr>
            <td>${escapeHtml(trade.option_symbol || '--')} ${getOptionCurrencyTagHtml(trade.currency)}</td>
            <td class="${trade.side === 'buy' ? 'market-positive' : 'market-negative'}">${escapeHtml((trade.side || '--').toUpperCase())}</td>
            <td>${formatUsdPrice(trade.price, 4)}</td>
            <td>${formatQuantity(trade.quantity)}</td>
            <td>${formatUsdt(trade.premium)}</td>
            <td>${formatUsdt(trade.fee)}</td>
            <td>${formatDate(trade.created_at)}</td>
            <td><span class="status-pill">record</span></td>
        </tr>
    `).join('');

    if (cardList) {
        cardList.innerHTML = optionsState.trades.map((trade) => {
            const isBuy = String(trade.side || 'buy').toLowerCase() === 'buy';
            const sideText = isBuy ? 'Buy' : 'Sell';
            const sideClass = isBuy ? 'market-positive' : 'market-negative';
            const coinLogo = getOptionCoinLogo(trade.option_symbol);
            const cur = String(trade.currency || (window.getActiveCurrency ? window.getActiveCurrency() : 'USD')).toUpperCase();
            const isINR = cur === 'INR';
            const tagLabel = isINR ? 'INR' : (isActiveCentAccount() ? 'USC' : (cur === 'USDT' ? 'USD' : cur));
            const priceText = formatUsdPrice(trade.price, 2);
            const qtyText = formatQuantity(trade.quantity);
            const feeText = formatUsdt(trade.fee);
            const premiumText = formatUsdt(trade.premium);
            const timeText = formatDate(trade.created_at);

            return `
                <div class="futures-position-mobile-card">
                    <div class="pos-card-left-group">
                        <img src="${coinLogo}" alt="${escapeHtml(trade.option_symbol || '--')}" class="pos-card-coin-logo" onerror="this.onerror=null;this.src='https://assets.coingecko.com/coins/images/1/large/bitcoin.png';" />
                        <div class="pos-card-info">
                            <div class="pos-card-title-row">
                                <span class="pos-card-sym-name">${escapeHtml(trade.option_symbol || '--')}</span>
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
                        </div>
                        <div class="text-[11px] font-mono text-slate-300 font-bold mt-1">
                            Prem: ${premiumText}
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
}


const optionAutoClosing = new Set();

function checkOptionsTpSlAutoClose() {
    if (!getAccessToken() || !optionsState.positions.length) {
        return;
    }

    optionsState.positions.forEach((position) => {
        const uid = position.position_uid;
        if (!uid || optionAutoClosing.has(uid)) return;

        const mark = getOptionPrice(position.option_symbol, position.entry_price);
        const tp = Number(position.take_profit || 0);
        const sl = Number(position.stop_loss || 0);
        if (!mark) return;

        const isBuy = position.side === 'buy';
        const takeProfitHit = tp && (isBuy ? mark >= tp : mark <= tp);
        const stopLossHit = sl && (isBuy ? mark <= sl : mark >= sl);

        if (!takeProfitHit && !stopLossHit) return;

        optionAutoClosing.add(uid);
        closeOptionPosition(uid, true, takeProfitHit ? 'TP' : 'SL');
    });
}

async function closeOptionPosition(positionUid, auto = false, reason = 'TP/SL') {
    const position = optionsState.positions.find((item) => item.position_uid === positionUid);
    if (!position) return;

    const button = document.querySelector(`[data-close-option-position="${positionUid}"]`);
    button?.setAttribute('disabled', 'disabled');

    const previousPositions = [...optionsState.positions];
    const exitPrice = getOptionPrice(position.option_symbol, position.entry_price);

    optionsState.positions = optionsState.positions.filter((item) => item.position_uid !== positionUid);
    renderBottomTable(optionsState.activeBottomTab);

    try {
        await authFetch(`/api/options/positions/${positionUid}/close`, {
            method: 'POST',
            body: JSON.stringify({ exit_price: exitPrice }),
        });
        const toastType = (auto && reason === 'SL') ? 'error' : 'success';
        showOptionsToast(auto ? `${reason} touched. Option position closed automatically.` : 'Option position closed.', toastType);
        loadWalletData();
        loadProtectedOptionsData();
    } catch (error) {
        showOptionsToast(error.message || 'Unable to close option position.', 'error');
        optionsState.positions = previousPositions;
        renderBottomTable(optionsState.activeBottomTab);
        loadProtectedOptionsData();
    }
}

async function cancelOptionOrder(orderUid) {
    const button = document.querySelector(`[data-cancel-option-order="${orderUid}"]`);
    button?.setAttribute('disabled', 'disabled');

    const previousOrders = [...optionsState.orders];
    optionsState.orders = optionsState.orders.filter((item) => item.order_uid !== orderUid);
    renderBottomTable(optionsState.activeBottomTab);

    try {
        await authFetch(`/api/options/orders/${orderUid}/cancel`, {
            method: 'POST',
            body: JSON.stringify({}),
        });
        showOptionsToast('Option order cancelled successfully.', 'success');
        await loadWalletData();
        await loadProtectedOptionsData();
    } catch (error) {
        showOptionsToast(error.message || 'Unable to cancel option order.', 'error');
        optionsState.orders = previousOrders;
        renderBottomTable(optionsState.activeBottomTab);
    }
}

async function loadWalletData() {
    if (!getAccessToken()) {
        optionsState.wallet = null;
        setText('optionsAvailableBalance', 'Login required');
        setText('optionsMainBalance', '--');
        setText('optionsMainBalance', '--');
        setText('optionsLockedBalance', '--');
        return;
    }

    try {
        const result = await authFetch('/api/wallet');
        const data = result.data || {};
        optionsState.wallet = data.wallet || null;
        optionsState.minimumReserve = Number(data.minimum_reserve || 0);
        optionsState.maxWithdrawable = Number(data.max_withdrawable || 0);
        optionsState.hasOpenTrades = Boolean(data.has_open_trades);
        optionsState.withdrawalCharges = data.withdrawal_charges || {};
        optionsState.depositNetworks = normalizeOptionWalletNetworks(data.deposit_networks || []);
        optionsState.withdrawalAddresses = data.withdrawal_addresses || {};
        renderOptionWalletNetworks();
        renderWallet();
    } catch (error) {
        setText('optionsAvailableBalance', '--');
        setText('optionsLockedBalance', '--');
    }
}

let optionsInrState = {
    baseCurrency: null,
    usdtInrRate: 0,
    adminBankDetails: null,
};

function formatOptionsInr(amount) {
    const n = Number(amount || 0);
    return '\u20b9' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadOptionsInrConfig() {
    const token = getAccessToken();
    if (!token) return;
    try {
        const [bcRes, rateRes] = await Promise.all([
            fetch('/api/auth/base-currency', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } }),
            fetch('/api/wallet/inr-rate', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
        ]);
        const bcData = await bcRes.json();
        const rateData = await rateRes.json();
        optionsInrState.baseCurrency = bcData?.data?.base_currency || null;
        optionsInrState.usdtInrRate = Number(rateData?.data?.usdt_inr_rate || 95.0);
        optionsInrState.usdtInrBuyRate = Number(rateData?.data?.usdt_inr_buy_rate || optionsInrState.usdtInrRate);
        optionsInrState.usdtInrSellRate = Number(rateData?.data?.usdt_inr_sell_rate || optionsInrState.usdtInrRate);
        optionsInrState.adminBankDetails = rateData?.data?.bank_details || null;

        try {
            localStorage.setItem('kryvex_inr_state_cache', JSON.stringify({
                baseCurrency: optionsInrState.baseCurrency,
                usdtInrRate: optionsInrState.usdtInrRate,
                usdtInrBuyRate: optionsInrState.usdtInrBuyRate,
                usdtInrSellRate: optionsInrState.usdtInrSellRate,
                adminBankDetails: optionsInrState.adminBankDetails
            }));
        } catch (e) {}

        renderOptionsInrBankDetails();
        renderWallet();
    } catch (e) {}
}

function renderWallet() {
    const available = Number(optionsState.wallet?.demo_available_balance || 0);
    const locked = Number(optionsState.wallet?.demo_locked_balance || 0);
    const mainUsdt = Number(optionsState.wallet?.demo_main_balance || 0);
    const mainInr = Number(optionsState.wallet?.demo_main_balance_inr || 0);
    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : 'USDT';

    setText('optionsAvailableBalance', optionsState.wallet ? formatUsdt(available) : 'Login required');
    setText('optionsMainBalance', optionsState.wallet ? (activeCur === 'INR' ? formatOptionsInr(mainInr) : formatUsdt(mainUsdt)) : '--');
    setText('optionsLockedBalance', optionsState.wallet ? formatUsdt(locked) : '--');

    updateOptionsLiveWalletMetrics();
}

let optionsActiveModalTab = 'usdt';

function verifyKycAndCurrencyBeforeAction() {
    const token = getAccessToken();
    if (!token) {
        showOptionsToast('Please login first.', 'error');
        return false;
    }

    let user = {};
    try {
        user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (e) {}

    const kycStatus = (user.kyc_status || '').toLowerCase();
    const isKycVerified = ['verified', 'approved'].includes(kycStatus);
    const baseCurrency = user.base_currency || optionsInrState.baseCurrency || null;

    if (!isKycVerified) {
        showOptionsToast('KYC Verification Required: Please complete KYC identity verification first before performing deposit or withdrawal.', 'error');
        return false;
    }

    return true;
}

function setupOptionsModalCurrencyTabs() {
    const usdtBtn = document.getElementById('optionsModalTabUsdt');
    const inrBtn = document.getElementById('optionsModalTabInr');

    if (usdtBtn) {
        usdtBtn.addEventListener('click', () => switchOptionsModalTab('usdt'));
    }
    if (inrBtn) {
        inrBtn.addEventListener('click', () => switchOptionsModalTab('inr'));
    }
}

function switchOptionsModalTab(tab) {
    optionsActiveModalTab = tab;
    document.getElementById('optionsModalTabUsdt')?.classList.toggle('is-active', tab === 'usdt');
    document.getElementById('optionsModalTabInr')?.classList.toggle('is-active', tab === 'inr');

    const action = document.getElementById('walletActionInput')?.value || 'deposit';
    const isWithdraw = action === 'withdraw';
    const isTransfer = action === 'transfer';

    const networkRow = document.getElementById('optionWalletNetworkRow');
    const depositAddressBox = document.getElementById('optionWalletDepositAddressBox');
    const withdrawAddressRow = document.getElementById('optionWalletWithdrawAddressRow');
    const withdrawAddressRule = document.getElementById('optionWalletWithdrawAddressRule');
    const bankBox = document.getElementById('optionsInrBankBox');
    const utrRow = document.getElementById('optionsUtrRow');
    const inrWithdrawFields = document.getElementById('optionsInrWithdrawFields');
    const amountLabel = document.getElementById('optionsAmountLabel');

    if (tab === 'inr') {
        if (networkRow) networkRow.hidden = true;
        if (depositAddressBox) depositAddressBox.hidden = true;
        if (withdrawAddressRow) withdrawAddressRow.hidden = true;
        if (withdrawAddressRule) withdrawAddressRule.hidden = true;

        if (bankBox) bankBox.style.display = (!isWithdraw && !isTransfer) ? 'block' : 'none';
        if (utrRow) utrRow.hidden = !(!isWithdraw && !isTransfer);
        if (inrWithdrawFields) inrWithdrawFields.style.display = isWithdraw ? 'block' : 'none';
        if (amountLabel) amountLabel.textContent = 'Amount (INR)';
        renderOptionsInrBankDetails();

        if (isWithdraw) {
            loadUserSavedBankDetails().then((b) => {
                const banner = document.getElementById('optionsSavedBankBanner');
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
        if (withdrawAddressRow) withdrawAddressRow.hidden = !isWithdraw;
        if (withdrawAddressRule) withdrawAddressRule.hidden = !isWithdraw;
        if (amountLabel) amountLabel.textContent = 'Amount (USD)';
        renderOptionWalletNetworks();
        renderOptionWalletNetworkDetails();
    }
}

function setupWalletModal() {
    const backdrop = document.getElementById('walletModalBackdrop');
    const actionInput = document.getElementById('walletActionInput');
    const transferDirectionRow = document.getElementById('optionWalletTransferDirectionRow');
    const transferDirection = document.getElementById('optionWalletTransferDirection');
    const amountInput = document.getElementById('walletAmountInput');
    const title = document.getElementById('walletModalTitle');
    const closeButton = document.getElementById('closeWalletModal');
    const form = document.getElementById('walletModalForm');
    const message = document.getElementById('walletModalMessage');

    document.querySelectorAll('[data-wallet-action]').forEach((button) => {
        button.addEventListener('click', () => {
            const action = button.dataset.walletAction || 'deposit';
            const mainUsdt = Number(optionsState.wallet?.demo_main_balance || 0);
            const mainInr = Number(optionsState.wallet?.demo_main_balance_inr || 0);
            const trading = Number(optionsState.wallet?.demo_available_balance || 0);
            const isWithdraw = action === 'withdraw';
            const isTransfer = action === 'transfer';

            if (actionInput) actionInput.value = action;

            if (backdrop) {
                backdrop.classList.remove('is-deposit', 'is-withdraw', 'is-transfer');
                if (isTransfer) {
                    backdrop.classList.add('is-transfer');
                } else if (isWithdraw) {
                    backdrop.classList.add('is-withdraw');
                } else {
                    backdrop.classList.add('is-deposit');
                }
            }

            if (transferDirectionRow) transferDirectionRow.hidden = !isTransfer;
            if (transferDirection) {
                if (trading > 0 && mainUsdt === 0 && mainInr === 0) {
                    transferDirection.value = 'trading_to_main';
                } else {
                    transferDirection.value = 'main_to_trading';
                }
            }

            const modalTabs = document.getElementById('optionsModalCurrencyTabs');
            if (modalTabs) {
                modalTabs.style.display = 'none';
            }

            const tabUsdt = document.getElementById('optionsModalTabUsdt');
            const tabInr = document.getElementById('optionsModalTabInr');
            if (tabUsdt) tabUsdt.style.display = 'none';
            if (tabInr) tabInr.style.display = 'none';

            setupOptionsModalCurrencyTabs();

            const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : 'USDT';
            switchOptionsModalTab(activeCur === 'INR' ? 'inr' : 'usdt');

            if (title) title.textContent = isTransfer ? 'Transfer Balance' : isWithdraw ? 'Withdraw Amount' : 'Deposit Amount';
            if (amountInput) amountInput.value = '';

            updateOptionsWalletWithdrawPreview();
            updateOptionsLiveModalCalc();
            if (message) {
                const mainUsdt = Number(optionsState.wallet?.demo_main_balance || 0);
                const mainInr = Number(optionsState.wallet?.demo_main_balance_inr || 0);
                const trading = Number(optionsState.wallet?.demo_available_balance || 0);
                message.textContent = isTransfer
                    ? `USD Main: ${formatUsdt(mainUsdt)} • INR Main: ${formatOptionsInr(mainInr)} • Trading: ${formatUsdt(trading)}`
                    : isWithdraw
                        ? (optionsActiveModalTab === 'inr'
                            ? `Max withdrawable from INR Main balance: ${formatOptionsInr(mainInr)}`
                            : `Max withdrawable from USD Main balance: ${formatUsdt(mainUsdt)}`)
                        : 'Deposit will be credited to selected Main balance.';
            }

            if (backdrop) backdrop.hidden = false;
            setTimeout(() => amountInput?.focus(), 40);
        });
    });

    closeButton?.addEventListener('click', closeWalletModal);
    backdrop?.addEventListener('click', (event) => {
        if (event.target === backdrop) closeWalletModal();
    });

    form?.addEventListener('submit', submitWalletAction);

    amountInput?.addEventListener('input', () => {
        updateOptionsWalletWithdrawPreview();
        updateOptionsLiveModalCalc();
    });

    amountInput?.addEventListener('wheel', (e) => {
        if (document.activeElement === amountInput) {
            amountInput.blur();
        }
        e.preventDefault();
    }, { passive: false });

    document.getElementById('optionWalletTransferDirection')?.addEventListener('change', () => {
        updateOptionsLiveModalCalc();
    });

    document.getElementById('optionWalletNetworkSelect')?.addEventListener('change', renderOptionWalletNetworkDetails);
    document.getElementById('optionWalletCopyDepositAddress')?.addEventListener('click', async () => {
        const network = getOptionWalletNetwork(document.getElementById('optionWalletNetworkSelect')?.value);
        if (!network?.address) { showOptionsToast('Select network first.', 'error'); return; }
        await copyTextToClipboard(network.address);
        showOptionsToast('Deposit address copied.', 'success');
    });

    document.getElementById('walletAmountMaxButton')?.addEventListener('click', () => {
        const action = document.getElementById('walletActionInput')?.value || 'deposit';
        const input = document.getElementById('walletAmountInput');

        if (input) {
            if (action === 'transfer') {
                const direction = document.getElementById('optionWalletTransferDirection')?.value || 'main_to_trading';
                const avail = Number(optionsState.wallet?.demo_available_balance || 0);
                const formattedAvail = avail > 0 && avail < 0.01 ? avail.toFixed(8).replace(/\.?0+$/, '') : avail.toFixed(2);
                if (optionsActiveModalTab === 'inr') {
                    if (direction === 'main_to_trading') {
                        input.value = Number(optionsState.wallet?.demo_main_balance_inr || 0).toFixed(2);
                    } else {
                        input.value = formattedAvail;
                    }
                } else {
                    if (direction === 'main_to_trading') {
                        input.value = Number(optionsState.wallet?.demo_main_balance || 0).toFixed(2);
                    } else {
                        input.value = formattedAvail;
                    }
                }
            } else if (action === 'withdraw') {
                if (optionsActiveModalTab === 'inr') {
                    const maxInr = Number(optionsState.wallet?.demo_main_balance_inr || 0);
                    input.value = maxInr > 0 ? maxInr.toFixed(2) : '';
                } else {
                    const maxUsdt = Number(optionsState.maxWithdrawable || optionsState.wallet?.demo_main_balance || 0);
                    input.value = maxUsdt > 0 ? maxUsdt : '';
                }
            }
            input.dispatchEvent(new Event('input', { bubbles: true }));
            updateOptionsWalletWithdrawPreview();
            updateOptionsLiveModalCalc();
        }
    });
}

function renderOptionsInrBankDetails() {
    const box = document.getElementById('optionsInrBankInfo');
    if (!box) return;
    let d = optionsInrState.adminBankDetails;
    if (!d) {
        try {
            const cached = JSON.parse(localStorage.getItem('kryvex_inr_state_cache') || '{}');
            d = cached?.adminBankDetails || null;
            if (d && !optionsInrState.adminBankDetails) optionsInrState.adminBankDetails = d;
        } catch (e) {}
    }
    if (!d) {
        box.innerHTML = '<span style="color:#f59e0b;">Admin has not set INR bank details yet.</span>';
        return;
    }
    let html = '';
    if (d.bank_name) html += `<div><strong>Bank:</strong> ${escapeHtml(d.bank_name)}</div>`;
    if (d.account_name) html += `<div><strong>Name:</strong> ${escapeHtml(d.account_name)}</div>`;
    if (d.account_number) html += `<div><strong>Account:</strong> ${escapeHtml(d.account_number)}</div>`;
    if (d.ifsc) html += `<div><strong>IFSC:</strong> ${escapeHtml(d.ifsc)}</div>`;
    if (d.upi_id) html += `<div><strong>UPI:</strong> ${escapeHtml(d.upi_id)}</div>`;
    box.innerHTML = html || '<span style="color:#f59e0b;">Admin has not set INR bank details yet.</span>';
}

function updateOptionsLiveModalCalc() {
    const action = document.getElementById('walletActionInput')?.value || 'deposit';
    const amountInput = document.getElementById('walletAmountInput');
    const dirSelect = document.getElementById('optionWalletTransferDirection');
    const amountLabel = document.getElementById('optionsAmountLabel');
    const fromLabel = document.getElementById('optionsConvFromLabel');
    const fromVal = document.getElementById('optionsConvFromVal');
    const toLabel = document.getElementById('optionsConvToLabel');
    const toVal = document.getElementById('optionsConvToVal');
    const convBox = document.getElementById('optionsConversionBox');
    const rateBar = document.getElementById('optionsRateBar');
    const rateVal = document.getElementById('optionsRateVal');

    const rawCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD');
    const activeCur = (rawCur === 'INR') ? 'INR' : 'USD';
    const isTransfer = action === 'transfer';
    const isINR = isTransfer ? true : (activeCur === 'INR' || optionsActiveModalTab === 'inr');
    const baseRate = optionsInrState.usdtInrRate || 95.00;
    const buyRate = optionsInrState.usdtInrBuyRate || baseRate;
    const sellRate = optionsInrState.usdtInrSellRate || baseRate;

    const direction = dirSelect?.value || 'main_to_trading';
    const isSellDirection = isTransfer && direction === 'trading_to_main';
    const activeRate = isSellDirection ? sellRate : buyRate;

    if (rateBar) {
        rateBar.style.display = isINR ? 'block' : 'none';
        if (rateVal) {
            const label = isSellDirection ? ' (Admin Sell Rate)' : ' (Admin Buy Rate)';
            rateVal.textContent = `1 USDT = \u20b9${activeRate.toFixed(2)}${label}`;
        }
    }

    if (!isINR || !convBox) {
        if (convBox) convBox.style.display = 'none';
        if (amountLabel && isTransfer) amountLabel.textContent = 'Amount (USDT)';
        return;
    }

    convBox.style.display = 'block';
    const val = Number(normalizeOptionNumberString(amountInput?.value || 0));

    if (action === 'deposit') {
        if (fromLabel) fromLabel.textContent = 'You Deposit:';
        if (fromVal) fromVal.textContent = formatOptionsInr(val);
        if (toLabel) toLabel.textContent = 'Wallet Credit:';
        if (toVal) toVal.textContent = '$' + (val > 0 ? (val / buyRate) : 0).toFixed(2) + ' USDT';
    } else if (action === 'withdraw') {
        if (fromLabel) fromLabel.textContent = 'You Withdraw:';
        if (fromVal) fromVal.textContent = formatOptionsInr(val);
        if (toLabel) toLabel.textContent = 'Main Deducted:';
        if (toVal) toVal.textContent = '$' + (val > 0 ? (val / sellRate) : 0).toFixed(4) + ' USDT';
    } else { // transfer
        if (direction === 'main_to_trading') {
            if (amountLabel) amountLabel.textContent = 'Amount (INR)';
            if (fromLabel) fromLabel.textContent = 'INR Main Wallet Deduction:';
            if (fromVal) fromVal.textContent = formatOptionsInr(val);
            if (toLabel) toLabel.textContent = 'Trading Account Credit (USDT):';
            if (toVal) toVal.textContent = '$' + (val > 0 ? (val / buyRate) : 0).toFixed(2) + ' USDT';
        } else {
            if (amountLabel) amountLabel.textContent = 'Amount (USDT)';
            if (fromLabel) fromLabel.textContent = 'Trading Account Deduction (USDT):';
            if (fromVal) fromVal.textContent = '$' + val.toFixed(2) + ' USDT';
            if (toLabel) toLabel.textContent = 'INR Main Wallet Credit:';
            if (toVal) toVal.textContent = formatOptionsInr(val > 0 ? (val * sellRate) : 0);
        }
    }
}

window.addEventListener('kryvex_currency_changed', (e) => {
    const cur = (e.detail?.currency || window.getActiveCurrency ? window.getActiveCurrency() : 'USD') === 'INR' ? 'inr' : 'usdt';
    optionsActiveModalTab = cur;
    updateOptionsLiveModalCalc();
});

function closeWalletModal() {
    const backdrop = document.getElementById('walletModalBackdrop') || document.getElementById('walletActionModalBackdrop');
    if (backdrop) backdrop.hidden = true;
}

function normalizeOptionNumberString(value) {
    const raw = String(value ?? '').trim();
    if (!raw) return '';
    if (raw.startsWith('.')) return `0${raw}`;
    if (raw.startsWith('-.')) return raw.replace('-.', '-0.');
    return raw;
}

function normalizeOptionWalletNetworks(value) {
    const rows = Array.isArray(value) ? value : Object.values(value || {});
    return rows.map((item) => ({
        network: String(item.network || item.code || '').toUpperCase(),
        label: item.label || item.name || item.network || item.code || '',
        address: item.address || item.deposit_address || '',
        minimum_deposit: Number(item.minimum_deposit || item.min_deposit || 0),
        rule: item.rule || '',
    })).filter((item) => item.network);
}

function renderOptionWalletNetworks() {
    const select = document.getElementById('optionWalletNetworkSelect');
    if (!select) return;
    const previous = select.value;
    select.innerHTML = optionsState.depositNetworks.length
        ? optionsState.depositNetworks.map((network) => `<option value="${escapeHtml(network.network)}">${escapeHtml(network.label || network.network)}</option>`).join('')
        : '<option value="">No network configured</option>';
    if (previous && optionsState.depositNetworks.some((item) => item.network === previous)) select.value = previous;
}

function getOptionWalletNetwork(networkCode) {
    const code = String(networkCode || '').toUpperCase();
    return optionsState.depositNetworks.find((item) => item.network === code) || null;
}

function renderOptionWalletNetworkDetails() {
    const action = document.getElementById('walletActionInput')?.value || 'deposit';
    const network = getOptionWalletNetwork(document.getElementById('optionWalletNetworkSelect')?.value);
    setText('optionWalletDepositAddressText', network?.address || 'Select network to view address.');
    setText('optionWalletDepositAddressRule', network?.address ? `Minimum: ${formatUsdtAsset(network.minimum_deposit || 0)} • ${network.rule || 'Use only selected network.'}` : 'Only send funds on selected network.');
    const withdrawInput = document.getElementById('optionWalletWithdrawAddressInput');
    if (withdrawInput && action === 'withdraw' && network) {
        withdrawInput.value = optionsState.withdrawalAddresses?.[network.network] || '';
        withdrawInput.placeholder = network.rule || 'Enter your wallet address';
    }
    setText('optionWalletWithdrawAddressRule', network?.rule || 'Select network to view address rule.');
}

async function copyOptionText(text) {
    if (navigator.clipboard?.writeText) { await navigator.clipboard.writeText(text); return; }
    const input = document.createElement('textarea'); input.value = text; document.body.appendChild(input); input.select(); document.execCommand('copy'); input.remove();
}

function calculateOptionsWithdrawalPreview(amount) {
    const charges = optionsState.withdrawalCharges || {};
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
        netAmount: Math.max(Math.round((amount - totalCharge) * 10000) / 10000, 0),
    };
}

function updateOptionsWalletWithdrawPreview() {
    const action = document.getElementById('walletActionInput')?.value || 'deposit';
    const preview = document.getElementById('optionsWalletWithdrawPreview');
    const convBox = document.getElementById('optionsConversionBox');
    const amount = Number(normalizeOptionNumberString(document.getElementById('walletAmountInput')?.value || 0));

    if (!preview) return;

    const isWithdraw = action === 'withdraw';
    preview.hidden = !isWithdraw;

    if (convBox && isWithdraw) {
        convBox.style.display = 'none';
    } else if (convBox && optionsInrState.baseCurrency === 'INR') {
        convBox.style.display = 'block';
    }

    if (!isWithdraw) return;

    const isINR = optionsActiveModalTab === 'inr';
    const rate = optionsInrState.usdtInrRate || 1;
    const chargesMeta = optionsState.withdrawalCharges || {};

    const feeType = chargesMeta.withdrawal_fee_type || 'percentage';
    const platformType = chargesMeta.platform_charge_type || 'fixed';
    const feeValue = Number(chargesMeta.withdrawal_fee_value || 0);
    const platformValue = Number(chargesMeta.platform_charge_value || 0);

    const usdtRow = document.getElementById('optionsWithdrawPreviewUsdtRow');

    if (isINR) {
        const withdrawFee = feeType === 'percentage' ? (amount * (feeValue / 100)) : (feeValue * rate);
        const platformCharge = platformType === 'percentage' ? (amount * (platformValue / 100)) : (platformValue * rate);
        const totalCharge = withdrawFee + platformCharge;
        const netAmount = Math.max(amount - totalCharge, 0);
        const usdtDeducted = rate > 0 ? (amount / rate) : 0;

        setText('optionsWithdrawPreviewAmount', amount > 0 ? formatOptionsInr(amount) : '--');
        setText('optionsWithdrawPreviewFee', amount > 0 ? formatOptionsInr(withdrawFee) : '--');
        setText('optionsWithdrawPreviewPlatform', amount > 0 ? formatOptionsInr(platformCharge) : '--');
        setText('optionsWithdrawPreviewNet', amount > 0 ? formatOptionsInr(netAmount) : '--');

        if (usdtRow) {
            usdtRow.style.display = 'flex';
            setText('optionsWithdrawPreviewUsdt', amount > 0 ? ('$' + usdtDeducted.toFixed(4) + ' USD') : '--');
        }
    } else {
        const charges = calculateOptionsWithdrawalPreview(amount);
        setText('optionsWithdrawPreviewAmount', amount > 0 ? formatUsdtAsset(amount) : '--');
        setText('optionsWithdrawPreviewFee', amount > 0 ? formatUsdtAsset(charges.withdrawFee) : '--');
        setText('optionsWithdrawPreviewPlatform', amount > 0 ? formatUsdtAsset(charges.platformCharge) : '--');
        setText('optionsWithdrawPreviewNet', amount > 0 ? formatUsdtAsset(charges.netAmount) : '--');

        if (usdtRow) {
            usdtRow.style.display = 'none';
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

    const action = document.getElementById('walletActionInput')?.value || 'deposit';
    const amount = Number(normalizeOptionNumberString(document.getElementById('walletAmountInput')?.value || 0));
    const direction = document.getElementById('optionWalletTransferDirection')?.value || 'main_to_trading';
    const submit = document.getElementById('walletModalSubmit');
    const message = document.getElementById('walletModalMessage');

    const isINR = optionsActiveModalTab === 'inr';
    const rate = optionsInrState.usdtInrRate || 1;

    if (!amount || amount <= 0) {
        if (message) message.textContent = 'Amount must be greater than zero.';
        return;
    }

    if (action === 'withdraw') {
        if (isINR) {
            const availableInr = Number(optionsState.wallet?.demo_main_balance_inr || 0);
            const reserveInr = Number(optionsState.minimumReserve || 0) * rate;
            const maxInrWithdrawable = optionsState.hasOpenTrades ? Math.max(0, availableInr - reserveInr) : availableInr;
            if (optionsState.hasOpenTrades && amount > maxInrWithdrawable) {
                const msg = `Unable to withdraw full balance. Open trading positions exist. Minimum reserve of ${formatOptionsInr(reserveInr)} ($${optionsState.minimumReserve}) must remain while positions are open. Maximum withdrawable amount now is ${formatOptionsInr(maxInrWithdrawable)}. To withdraw your total full balance, please close all open positions first.`;
                showOptionsToast(msg, 'error');
                return;
            } else if (amount > availableInr) {
                showOptionsToast(`Insufficient INR main balance. You have ${formatOptionsInr(availableInr)}.`, 'error');
                return;
            }
        } else {
            const maxVal = optionsState.maxWithdrawable || 0;
            const availableUsdt = Number(optionsState.wallet?.demo_main_balance || 0);
            if (optionsState.hasOpenTrades && amount > maxVal) {
                const msg = `Unable to withdraw full balance. Open trading positions exist. Minimum reserve of $${optionsState.minimumReserve} must remain while positions are open. Maximum withdrawable amount now is $${maxVal}. To withdraw your total full balance, please close all open positions first.`;
                showOptionsToast(msg, 'error');
                return;
            } else if (amount > availableUsdt) {
                showOptionsToast(`Insufficient USD main balance. Maximum withdrawable amount is $${maxVal}.`, 'error');
                return;
            }
        }
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
                    description: `INR Options Deposit ₹${amount}`,
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
                                showOptionsToast(verifyRes.message || 'Razorpay Payment Verified & Wallet Credited! 🎉', 'success');
                                closeWalletActionModal();
                                if (window.loadProtectedOptionsData) await window.loadProtectedOptionsData();
                            } else {
                                showOptionsToast(verifyRes.message || 'Payment verification failed.', 'error');
                            }
                        } catch (err) {
                            showOptionsToast(err.message || 'Payment verification failed.', 'error');
                        } finally {
                            submit?.removeAttribute('disabled');
                        }
                    },
                    modal: {
                        ondismiss: function () {
                            submit?.removeAttribute('disabled');
                            showOptionsToast('Razorpay payment cancelled.', 'warning');
                        },
                    },
                };

                const rzp = new RazorpayCtor(options);
                rzp.open();
                return;
            } else if (action === 'withdraw') {
                const payload = {
                    amount_inr: amount,
                    account_name: document.getElementById('optionsInrAccName')?.value?.trim() || (savedUserBankDetails?.account_name ?? ''),
                    bank_name: document.getElementById('optionsInrBankName')?.value?.trim() || (savedUserBankDetails?.bank_name ?? ''),
                    account_number: document.getElementById('optionsInrAccNo')?.value?.trim() || (savedUserBankDetails?.account_number ?? ''),
                    ifsc: document.getElementById('optionsInrIfsc')?.value?.trim() || (savedUserBankDetails?.ifsc_code ?? ''),
                    upi_id: document.getElementById('optionsInrUpi')?.value?.trim() || (savedUserBankDetails?.upi_id ?? null),
                };
                if (!payload.account_name && !payload.upi_id && !savedUserBankDetails) {
                    showOptionsToast('Please save bank details in Settings or enter them below.', 'error');
                    submit?.removeAttribute('disabled');
                    return;
                }
                const res = await authFetch('/api/wallet/inr-withdraw', {
                    method: 'POST',
                    body: JSON.stringify(payload),
                });
                showOptionsToast(res.message || 'INR withdrawal submitted.', 'success');
            } else { // transfer
                const res = await authFetch('/api/wallet/transfer', {
                    method: 'POST',
                    body: JSON.stringify({ direction, amount, currency: 'INR' }),
                });
                showOptionsToast(res.message || 'Transfer completed.', 'success');
            }
        } else {
            const endpoint = action === 'withdraw' ? '/api/wallet/withdraw' : action === 'transfer' ? '/api/wallet/transfer' : '/api/wallet/demo-credit';
            const network = document.getElementById('optionWalletNetworkSelect')?.value || '';
            const wallet_address = document.getElementById('optionWalletWithdrawAddressInput')?.value?.trim() || '';
            const payload = action === 'transfer' ? { amount, direction, currency: 'USDT' } : action === 'withdraw' ? { amount, network, wallet_address } : { amount, network };
            const result = await authFetch(endpoint, {
                method: 'POST',
                body: JSON.stringify(payload),
            });

            showOptionsToast(result.message || (action === 'withdraw' ? 'Withdrawal request submitted.' : action === 'transfer' ? 'Transfer completed.' : 'Funds added successfully.'), 'success');
        }

        closeWalletModal();
        await loadWalletData();
        await loadProtectedOptionsData();
    } catch (error) {
        if (message) message.textContent = error.message || 'Wallet action failed.';
        showOptionsToast(error.message || 'Wallet action failed.', 'error');
    } finally {
        submit?.removeAttribute('disabled');
    }
}

function askOptionsConfirm({ title, message, confirmText = 'Confirm', preferenceKey = '', skipLabel = '' }) {
    const modal = document.getElementById('optionsConfirmModal');
    const titleEl = document.getElementById('optionsConfirmTitle');
    const messageEl = document.getElementById('optionsConfirmMessage');
    const confirmBtn = document.getElementById('optionsConfirmOk');
    const cancelBtn = document.getElementById('optionsConfirmCancel');
    const preferenceRow = document.getElementById('optionsConfirmPreferenceRow');
    const preferenceCheckbox = document.getElementById('optionsConfirmSkip');
    const preferenceLabel = document.getElementById('optionsConfirmSkipLabel');

    if (preferenceKey && localStorage.getItem(preferenceKey) === '1') {
        return Promise.resolve(true);
    }

    if (!modal || !confirmBtn || !cancelBtn) return Promise.resolve(false);

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

    modal.hidden = false;
    setTimeout(() => confirmBtn.focus(), 30);

    return new Promise((resolve) => {
        const cleanup = (answer) => {
            if (answer && preferenceKey && preferenceCheckbox?.checked) {
                localStorage.setItem(preferenceKey, '1');
            }

            if (document.activeElement && modal.contains(document.activeElement)) document.activeElement.blur();
            modal.hidden = true;
            confirmBtn.removeEventListener('click', onConfirm);
            cancelBtn.removeEventListener('click', onCancel);
            modal.removeEventListener('click', onBackdrop);
            resolve(answer);
        };
        const onConfirm = () => cleanup(true);
        const onCancel = () => cleanup(false);
        const onBackdrop = (event) => { if (event.target === modal) cleanup(false); };
        confirmBtn.addEventListener('click', onConfirm);
        cancelBtn.addEventListener('click', onCancel);
        modal.addEventListener('click', onBackdrop);
    });
}

function showOptionsToast(message, type = 'success') {
    if (typeof window.showToast === 'function') {
        window.showToast({
            type: type,
            title: type === 'success' ? 'Success' : type.charAt(0).toUpperCase() + type.slice(1),
            message: message
        });
    }
}

function getOptionPrice(symbol, fallback = 0) {
    const contract = findContractBySymbol(optionsState.rows, symbol);
    return Number(contract?.mark_price || contract?.last_price || fallback || 0);
}

function calculateOptionPnl(position, currentPrice) {
    const entry = Number(position.entry_price || 0);
    const qty = Number(position.quantity || 0);
    if (String(position.side || '').toLowerCase() === 'sell') return round((entry - currentPrice) * qty, 4);
    return round((currentPrice - entry) * qty, 4);
}

function findFirstContract(rows) {
    for (const row of rows) {
        if (row.call) return row.call;
        if (row.put) return row.put;
    }
    return null;
}

function findContractBySymbol(rows, symbol) {
    for (const row of rows) {
        if (row.call?.symbol === symbol) return row.call;
        if (row.put?.symbol === symbol) return row.put;
    }
    return null;
}

function formatPair(symbol) { return String(symbol || '').replace(/USDT$/i, '/USD'); }

function formatStrike(value) {
    if (value === null || value === undefined) return '--';
    return Number(value).toLocaleString('en-US', { maximumFractionDigits: 2 });
}

function formatNumber(value, decimals = 2) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '--';
    return Number(value).toLocaleString('en-US', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
}

function formatUsdPrice(value, decimals = 2) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '--';
    return `$${formatNumber(value, decimals)}`;
}

function formatUsdt(value) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '--';
    if (isActiveCentAccount()) return formatUsc(value);
    return formatUsdtAsset(value);
}

// On-chain deposit/withdrawal amounts are real USDT, so they never switch to USC.
function formatUsdtAsset(value) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '--';
    return `$${Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 })}`;
}

function formatUsdtWithUnit(value) {
    return isActiveCentAccount() ? formatUsdt(value) : `${formatUsdt(value)} USDT`;
}

function formatQuantity(value) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '--';
    return Number(value).toLocaleString('en-US', { maximumFractionDigits: 8 });
}

function formatGreek(value) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '--';
    return Number(value).toFixed(4);
}

function formatTime(value) {
    if (!value) return '--';
    const date = new Date(Number(value));
    if (Number.isNaN(date.getTime())) return '--';
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function formatTradeTime(value) {
    return formatTime(value);
}

function formatDate(value) {
    if (!value) return '--';
    const date = new Date(value?.date || value);
    if (Number.isNaN(date.getTime())) return '--';
    return date.toLocaleString([], { month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function round(value, decimals = 4) {
    const factor = 10 ** decimals;
    return Math.round(Number(value || 0) * factor) / factor;
}

function setText(id, value) {
    const element = document.getElementById(id);
    if (element) element.textContent = value;
}

function escapeHtml(value) { 
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function findAtmStrike(rows, lastPrice) {
    const price = Number(lastPrice);
    if (!Number.isFinite(price) || !rows.length) return rows[Math.floor(rows.length / 2)]?.strike ?? null;
    let nearest = rows[0]?.strike ?? null;
    let nearestDiff = Math.abs(Number(nearest) - price);
    rows.forEach((row) => {
        const diff = Math.abs(Number(row.strike) - price);
        if (diff < nearestDiff) { nearest = row.strike; nearestDiff = diff; }
    });
    return nearest;
}

function getCenteredStrikeRows(rows, centerStrike, limit) {
    if (!rows.length || rows.length <= limit) return rows;
    const centerIndex = rows.findIndex((row) => Number(row.strike) === Number(centerStrike));
    if (centerIndex === -1) return rows.slice(0, limit);
    const half = Math.floor(limit / 2);
    let start = Math.max(0, centerIndex - half);
    let end = start + limit;
    if (end > rows.length) { end = rows.length; start = Math.max(0, end - limit); }
    return rows.slice(start, end);
}

function getCurrentUnderlyingPrice() {
    if (optionsState.liveSpotPrice && Number.isFinite(optionsState.liveSpotPrice)) {
        return Number(optionsState.liveSpotPrice);
    }
    const text = document.getElementById('optionIndexPrice')?.textContent || '';
    const value = Number(text.replace(/[$,]/g, ''));
    if (Number.isFinite(value)) return value;
    if (optionsState.atmStrike) return Number(optionsState.atmStrike);
    return NaN;
}

function formatChainAmount(value) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '0';
    const number = Number(value);
    if (number >= 100) return number.toLocaleString('en-US', { maximumFractionDigits: 0 });
    if (number >= 10) return number.toLocaleString('en-US', { maximumFractionDigits: 1 });
    if (number >= 1) return number.toLocaleString('en-US', { maximumFractionDigits: 2 });
    return number.toLocaleString('en-US', { maximumFractionDigits: 4 });
}

function formatIvPercent(value) {
    if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) return '--';
    const number = Number(value);
    return number <= 3 ? `${(number * 100).toFixed(2)}%` : `${number.toFixed(2)}%`;
}

function updateChainHeader(expiryKey) {
    const expiryLabel = formatExpiryForHeader(expiryKey || optionsState.expiry);
    setText('chainExpiryTitle', expiryLabel);
    setText('chainExpiryCenter', expiryLabel);
    setText('chainUnderlyingPrice', document.getElementById('optionIndexPrice')?.textContent || '--');
    setText('chainTimeToExpiry', getTimeToExpiry(expiryKey || optionsState.expiry));
}

function formatExpiryForHeader(expiryKey) {
    if (!expiryKey) return '--';
    const date = new Date(`${expiryKey}T00:00:00Z`);
    if (Number.isNaN(date.getTime())) return expiryKey;
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function getTimeToExpiry(expiryKey) {
    if (!expiryKey) return '--';
    const expiry = new Date(`${expiryKey}T08:00:00Z`);
    const now = new Date();
    const diff = expiry.getTime() - now.getTime();
    if (diff <= 0) return 'Expired';
    const totalMinutes = Math.floor(diff / 60000);
    const days = Math.floor(totalMinutes / 1440);
    const hours = Math.floor((totalMinutes % 1440) / 60);
    const minutes = totalMinutes % 60;
    return days > 0 ? `${days}d ${hours}h ${minutes}m` : `${hours}h ${minutes}m`;
}

function renderUnderlyingLine(price) {
    const symbolRaw = (optionsState.underlying || 'BTCUSDT').toUpperCase();
    const symbolDisplay = symbolRaw.includes('-') ? symbolRaw : (symbolRaw.replace('USDT', '') + 'USDT');
    const priceFormatted = formatUsdPrice(price, 2);
    const changeEl = document.getElementById('optionChange24h');
    const changeText = changeEl ? changeEl.textContent.trim() : '';
    const isNegative = changeEl ? changeEl.classList.contains('negative') || changeText.startsWith('-') : false;
    const changeClass = isNegative ? 'negative' : 'positive';
    const displayChange = (changeText && changeText !== '--') ? changeText : '+0.15%';

    return `
        <tr class="underlying-price-divider-row">
            <td colspan="13" class="underlying-divider-cell">
                <div class="underlying-spot-bar">
                    <span class="spot-tag-lbl">Spot Price</span>
                    <strong class="spot-tag-symbol">${escapeHtml(symbolDisplay)}</strong>
                    <strong class="spot-tag-price">${priceFormatted}</strong>
                    <span class="spot-tag-change ${changeClass}">${escapeHtml(displayChange)}</span>
                </div>
            </td>
        </tr>
    `;
}

function renderStrikeDistance(strike, underlyingPrice) {
    if (!Number.isFinite(Number(strike)) || !Number.isFinite(Number(underlyingPrice)) || underlyingPrice <= 0) return '';
    const percent = ((Number(strike) - Number(underlyingPrice)) / Number(underlyingPrice)) * 100;
    return `<em>${Math.abs(percent).toFixed(2)}%</em>`;
}

function isRateLimitMessage(message) {
    const text = String(message || '').toLowerCase();
    return text.includes('418') || text.includes('429') || text.includes('too many requests') || text.includes('banned');
}

function inferContractType(symbol) {
    const upper = String(symbol || '').toUpperCase();
    if (upper.includes('-C') || upper.endsWith('C')) return 'call';
    if (upper.includes('-P') || upper.endsWith('P')) return 'put';
    return 'option';
}

let renderChainAnimationFrameId = null;
function scheduleChainRender() {
    if (renderChainAnimationFrameId) return;
    renderChainAnimationFrameId = requestAnimationFrame(() => {
        renderChainAnimationFrameId = null;
        if (optionsState.rows && optionsState.rows.length) {
            renderChain(optionsState.rows);
        }
    });
}

function updateChainDOMPrices(spotPrice) {
    if (!spotPrice || !Number.isFinite(spotPrice)) return;
    const tbody = document.getElementById('optionsChainBody');
    if (!tbody) return;

    const spotLinePrice = tbody.querySelector('.underlying-spot-bar .spot-tag-price');
    if (spotLinePrice) {
        spotLinePrice.textContent = formatUsdPrice(spotPrice, 2);
    }

    if (!optionsState.rows || !optionsState.rows.length) return;

    const baseSpot = optionsState.baseSpotPrice || spotPrice;
    const spotDiff = spotPrice - baseSpot;

    optionsState.rows.forEach(row => {
        if (row.call && row.call.symbol) {
            const baseMark = Number(row.call.baseMarkPrice || row.call.mark_price || 10);
            const delta = Number(row.call.delta || 0.5);
            const newMark = Math.max(0.1, Number((baseMark + (spotDiff * delta)).toFixed(2)));

            row.call.mark_price = newMark;
            row.call.last_price = newMark;
            row.call.bid_price = Number((newMark * 0.98).toFixed(2));
            row.call.ask_price = Number((newMark * 1.02).toFixed(2));

            const cells = tbody.querySelectorAll(`[data-option-symbol="${row.call.symbol}"]`);
            cells.forEach(cell => {
                if (cell.classList.contains('col-last')) {
                    const pEl = cell.querySelector('.opt-price');
                    if (pEl) pEl.textContent = formatChainAmount(newMark);
                } else if (cell.classList.contains('col-bid')) {
                    const pEl = cell.querySelector('.opt-price');
                    if (pEl) pEl.textContent = formatChainAmount(row.call.bid_price);
                } else if (cell.classList.contains('col-ask')) {
                    const pEl = cell.querySelector('.opt-price');
                    if (pEl) pEl.textContent = formatChainAmount(row.call.ask_price);
                }
            });
        }

        if (row.put && row.put.symbol) {
            const baseMark = Number(row.put.baseMarkPrice || row.put.mark_price || 10);
            const delta = Number(row.put.delta || 0.5);
            const newMark = Math.max(0.1, Number((baseMark - (spotDiff * delta)).toFixed(2)));

            row.put.mark_price = newMark;
            row.put.last_price = newMark;
            row.put.bid_price = Number((newMark * 0.98).toFixed(2));
            row.put.ask_price = Number((newMark * 1.02).toFixed(2));

            const cells = tbody.querySelectorAll(`[data-option-symbol="${row.put.symbol}"]`);
            cells.forEach(cell => {
                if (cell.classList.contains('col-last')) {
                    const pEl = cell.querySelector('.opt-price');
                    if (pEl) pEl.textContent = formatChainAmount(newMark);
                } else if (cell.classList.contains('col-bid')) {
                    const pEl = cell.querySelector('.opt-price');
                    if (pEl) pEl.textContent = formatChainAmount(row.put.bid_price);
                } else if (cell.classList.contains('col-ask')) {
                    const pEl = cell.querySelector('.opt-price');
                    if (pEl) pEl.textContent = formatChainAmount(row.put.ask_price);
                }
            });
        }
    });
}

function connectUnderlyingMarkSocket() {
    closeSocket(optionsState.markSocket);

    const rawSym = (optionsState.underlying || 'BTCUSDT').toLowerCase().replace('-', '');
    let symbol = rawSym.endsWith('usdt') ? rawSym : (rawSym + 'usdt');
    let wsUrl = `wss://stream.binance.com:9443/stream?streams=${symbol}@aggTrade/${symbol}@trade/${symbol}@ticker`;
    if (symbol === 'xauusdt' || symbol === 'xauusd' || symbol === 'goldusdt') {
        wsUrl = `wss://fstream.binance.com/stream?streams=xauusdt@markPrice@1s/xauusdt@aggTrade/xauusdt@ticker`;
    }

    try {
        const ws = new WebSocket(wsUrl);
        optionsState.markSocket = ws;

        ws.onopen = () => {
            console.log(`[Binance WS Connected] Streaming tick-by-tick for ${symbol}`);
        };

        ws.onmessage = (event) => {
            try {
                const message = JSON.parse(event.data);
                const data = message.data || message;
                if (!data) return;

                let lastPrice = null;
                let priceChangePercent = null;
                let highPrice = null;
                let lowPrice = null;
                let volume = null;
                let quoteVolume = null;

                if (data.e === '24hrTicker') {
                    lastPrice = Number(data.c);
                    priceChangePercent = Number(data.P);
                    highPrice = Number(data.h);
                    lowPrice = Number(data.l);
                    volume = Number(data.v);
                    quoteVolume = Number(data.q);
                } else if (data.e === 'markPriceUpdate' || data.i) {
                    lastPrice = Number(data.i || data.p || data.c);
                } else if (data.e === 'aggTrade' || data.e === 'trade') {
                    lastPrice = Number(data.p);
                } else if (data.c) {
                    lastPrice = Number(data.c);
                }

                if (lastPrice !== null && Number.isFinite(lastPrice)) {
                    optionsState.liveSpotPrice = lastPrice;

                    const formattedPrice = formatUsdPrice(lastPrice, 2);
                    setText('optionIndexPrice', formattedPrice);
                    setText('optFilterSpotPrice', formattedPrice);
                    setText('optHeaderMarkPrice', formattedPrice);
                    updatePageDocumentTitle(lastPrice);

                    renderTopStats({
                        symbol: optionsState.underlying,
                        last_price: lastPrice,
                        price_change_percent: priceChangePercent,
                        high_price: highPrice,
                        low_price: lowPrice,
                        volume: volume,
                        quote_volume: quoteVolume,
                    }, true);

                    // Zero-latency direct DOM text node update on existing table cells!
                    updateChainDOMPrices(lastPrice);

                    // Live update selected contract mark price in order ticket from optionsState.rows
                    if (optionsState.selectedContract) {
                        const updated = findContractBySymbol(optionsState.rows, optionsState.selectedContract.symbol);
                        if (updated) {
                            optionsState.selectedContract = updated;
                            const mark = Number(updated.mark_price || updated.last_price || 0);
                            setText('contractMarkPrice', formatUsdPrice(mark, 2));
                            updatePremiumEstimate();
                        }
                    }
                }
            } catch (e) {
                // Ignore parse errors
            }
        };

        ws.onerror = () => { };
        ws.onclose = () => {
            optionsState.markSocket = null;
            if (!optionsState.isRateLimited) {
                setTimeout(() => {
                    if (OPTIONS_WEBSOCKET_ENABLED && !optionsState.markSocket) {
                        connectUnderlyingMarkSocket();
                    }
                }, 1500);
            }
        };
    } catch (err) { }
}

function connectSelectedContractSocket() { }
function closeSocket(socket) { try { socket?.close(); } catch (error) { } }

window.addEventListener('beforeunload', () => {
    closeSocket(optionsState.markSocket);
    closeSocket(optionsState.activitySocket);
});

async function authFetch(url, options = {}, hasRetried = false) {
    const token = getAccessToken();
    const activeAccountUid = localStorage.getItem('kryvex_active_account_uid');

    if (!token) throw new Error('Please login first.');

    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        ...(options.headers || {}),
    };

    if (activeAccountUid) {
        headers['X-Account-UID'] = activeAccountUid;
    }

    const response = await fetch(url, {
        ...options,
        headers,
    });

    const data = await response.json().catch(() => ({}));

    if (response.status === 401 && !hasRetried) {
        const refreshed = await refreshAccessToken();
        if (refreshed) return authFetch(url, options, true);
    }

    if (!response.ok || data.success === false) {
        const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
        throw new Error(firstError || data.message || 'Request failed.');
    }

    return data;
}

function getAccessToken() {
    return localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token') || localStorage.getItem('token');
}

function getRefreshToken() {
    return localStorage.getItem('kryvex_refresh_token') || localStorage.getItem('refresh_token');
}

async function refreshAccessToken() {
    const refreshToken = getRefreshToken();
    if (!refreshToken) return false;

    try {
        const response = await fetch('/api/auth/refresh', {
            method: 'POST',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify({ refresh_token: refreshToken }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) return false;
        const token = data.data?.access_token || data.access_token;
        if (token) {
            localStorage.setItem('kryvex_access_token', token);
            localStorage.setItem('access_token', token);
            return true;
        }
    } catch (error) {
        return false;
    }

    return false;
}

function calculateTotalOptionsOpenPnl() {
    let total = 0;
    optionsState.positions.forEach((position) => {
        const currentPrice = getOptionPrice(position.option_symbol, position.entry_price);
        total += calculateOptionPnl(position, currentPrice);
    });
    return total;
}

let optionsAutoLiquidating = false;

async function maybeAutoCloseOptionsRiskPositions(available, pnl) {
    if (optionsAutoLiquidating || !getAccessToken() || !optionsState.positions.length) {
        return;
    }

    const marginEquity = Number(available || 0) + Number(pnl || 0);
    if (marginEquity > 0) {
        return;
    }

    const openPositions = [...optionsState.positions];
    optionsAutoLiquidating = true;
    optionsState.positions = [];
    renderBottomTable(optionsState.activeBottomTab);
    showOptionsToast('Trading balance depleted. Open option trades auto-closed.', 'error');

    try {
        await Promise.all(openPositions.map((position) => authFetch(`/api/options/positions/${position.position_uid}/close`, {
            method: 'POST',
            body: JSON.stringify({ exit_price: getOptionPrice(position.option_symbol, position.entry_price) }),
        })));
        await loadWalletData();
        await loadProtectedOptionsData();
    } catch (error) {
        await loadWalletData();
        await loadProtectedOptionsData();
    } finally {
        optionsAutoLiquidating = false;
    }
}

function updateOptionsLiveWalletMetrics() {
    if (!optionsState.wallet) return;

    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : 'USDT';
    const availableUsdt = Number(optionsState.wallet.demo_available_balance || 0);
    const availableInr = Number(optionsState.wallet.demo_available_balance_inr || 0);
    const lockedUsdt = Number(optionsState.wallet.demo_locked_balance || 0);
    const lockedInr = Number(optionsState.wallet.demo_locked_balance_inr || 0);
    const mainUsdt = Number(optionsState.wallet.demo_main_balance || 0);
    const mainInr = Number(optionsState.wallet.demo_main_balance_inr || 0);

    const available = activeCur === 'INR' ? availableInr : availableUsdt;
    const locked = activeCur === 'INR' ? lockedInr : lockedUsdt;

    const pnl = calculateTotalOptionsOpenPnl();
    const liveAvailable = Math.max(0, available + pnl);

    setText('optionsAvailableBalance', formatUsdt(liveAvailable));
    setText('optionsMainBalance', activeCur === 'INR' ? formatOptionsInr(mainInr) : formatUsdt(mainUsdt));
    setText('optionsLockedBalance', formatUsdt(locked));

    const equity = available + locked + pnl;
    setText('optionsMobileEquity', formatUsdt(equity));
    setText('optionsMobileUpnl', formatUsdt(pnl));
    setText('optionsMobileMarginBal', formatUsdt(locked));
    setText('optionsMobileAvail', formatUsdt(liveAvailable));

    const optionsMobileUpnlEl = document.getElementById('optionsMobileUpnl');
    if (optionsMobileUpnlEl) {
        optionsMobileUpnlEl.className = pnl >= 0 ? 'market-positive' : 'market-negative';
    }

    const availableElement = document.getElementById('optionsAvailableBalance');
    if (availableElement) {
        availableElement.classList.remove('market-positive', 'market-negative');
        if (optionsState.positions.length) {
            if (pnl > 0.00000001) {
                availableElement.classList.add('market-positive');
            } else if (pnl < -0.00000001) {
                availableElement.classList.add('market-negative');
            }
        }
    }

    maybeAutoCloseOptionsRiskPositions(available, pnl);
}

function renderOptionsPagination(targetId, meta, onPageClick) {
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

window.addEventListener('kryvex_currency_changed', () => {
    if (typeof updateOptionsLiveWalletMetrics === 'function') {
        updateOptionsLiveWalletMetrics();
    }
});

let optionsResizeTimeout = null;
window.addEventListener('resize', () => {
    if (optionsResizeTimeout) clearTimeout(optionsResizeTimeout);
    optionsResizeTimeout = setTimeout(() => {
        if (typeof renderBottomTable === 'function' && optionsState.activeBottomTab) {
            renderBottomTable(optionsState.activeBottomTab);
        }
    }, 150);
});

function setupOptionsGuideAndGreeksControls() {
    const guideBtn = document.getElementById('btnOptionsGuide');
    const guideModal = document.getElementById('optionsGuideModal');
    const greeksBtn = document.getElementById('btnOptionsGreeks');
    const greeksModal = document.getElementById('optionsGreeksModal');

    if (guideBtn && guideModal) {
        guideBtn.addEventListener('click', () => {
            guideModal.hidden = false;
            guideModal.classList.add('is-open');
            guideModal.style.display = 'flex';
        });
        guideModal.querySelectorAll('.close-guide-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                guideModal.hidden = true;
                guideModal.classList.remove('is-open');
                guideModal.style.display = 'none';
            });
        });
        guideModal.addEventListener('click', (e) => {
            if (e.target === guideModal) {
                guideModal.hidden = true;
                guideModal.classList.remove('is-open');
                guideModal.style.display = 'none';
            }
        });
    }

    if (greeksBtn) {
        greeksBtn.addEventListener('click', () => {
            optionsChainViewMode = (optionsChainViewMode === 'greeks') ? 'standard' : 'greeks';

            if (optionsChainViewMode === 'greeks') {
                greeksBtn.classList.add('is-active');
                greeksBtn.style.background = '#8b5cf6';
                greeksBtn.style.color = '#ffffff';
                greeksBtn.style.borderColor = '#a78bfa';
                greeksBtn.textContent = 'Greeks';
            } else {
                greeksBtn.classList.remove('is-active');
                greeksBtn.style.background = '';
                greeksBtn.style.color = '';
                greeksBtn.style.borderColor = '';
                greeksBtn.textContent = 'Greeks';
            }

            updateOptionChainTableHeader();
            renderChain(optionsState.rows);
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupOptionsGuideAndGreeksControls);
} else {
    setupOptionsGuideAndGreeksControls();
}
