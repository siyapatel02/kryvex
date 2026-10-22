import jsQR from 'jsqr';
import { isActiveCentAccount, isCentAccount, formatUsc } from '../utils/cent';

let ledgerFilterState = {
    search: '',
    type: 'all',
    direction: 'all',
    currency: 'all',
    account_uid: 'all',
    from_date: '',
    to_date: '',
};

let walletRuleState = {
    minimumReserve: 0,
    hasOpenTrades: false,
    maxWithdrawable: 0,
    withdrawableCrypto: {},
    withdrawAssetPrices: {},
    withdrawAssetNetworks: {},
    withdrawalEnabled: true,
    minimumWithdrawAmount: 0,
    maximumWithdrawAmount: 0,
    withdrawalFeeType: 'percentage',
    withdrawalFeeValue: 0,
    platformChargeType: 'fixed',
    platformChargeValue: 0,
    demoMainBalance: 0,
    demoAvailableBalance: 0,
};

let walletDataState = {
    depositNetworks: [],
    withdrawalAddresses: {},
};

let inrState = {
    baseCurrency: null,   // 'INR' | 'USDT' | null
    usdtInrRate: 0,       // admin set rate e.g. 85
    adminBankDetails: null,
};

let tradingAccountsState = [];
try {
    tradingAccountsState = JSON.parse(localStorage.getItem('kryvex_trading_accounts_cache') || '[]') || [];
} catch (e) {}
let activeTradingAccountUid = localStorage.getItem('kryvex_active_account_uid') || null;
let tradingAccountsEnvFilter = localStorage.getItem('kryvex_trading_accounts_env') || null;

function formatNum(num, minDec = 2, maxDec = 2) {
    const val = Number(num || 0);
    if (isNaN(val)) return '0.00';
    return val.toLocaleString('en-US', {
        minimumFractionDigits: minDec,
        maximumFractionDigits: maxDec
    });
}

function isDemoTradingAccount(acc) {
    if (!acc) return false;
    return Boolean(
        acc.is_demo || 
        acc.account_type === 'demo' || 
        acc.account_name === 'Demo Account' || 
        String(acc.account_name || '').toLowerCase().startsWith('demo')
    );
}

function isCurrentAccountLive() {
    const activeAcc = (tradingAccountsState || []).find(a => a.account_uid === activeTradingAccountUid);
    if (activeAcc) {
        return !isDemoTradingAccount(activeAcc);
    }
    const mode = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo'));
    return mode === 'live';
}
window.isCurrentAccountLive = isCurrentAccountLive;

async function loadTradingAccounts() {
    const token = localStorage.getItem('kryvex_access_token');
    if (!token) return;

    let user = {};
    try {
        user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (e) {}
    const broker = (user.broker_type || 'kryvex').toLowerCase();

    try {
        const res = await authFetch(`/api/trading-accounts?broker_type=all`, {
            headers: { 'X-Broker-Type': 'all' }
        });
        tradingAccountsState = res.data || [];
        window.__cachedTradingAccounts = tradingAccountsState;
        try {
            localStorage.setItem('kryvex_trading_accounts_cache', JSON.stringify(tradingAccountsState));
            localStorage.setItem('kryvex_cached_trading_accounts', JSON.stringify(tradingAccountsState));
        } catch (e) {}

        const currentValid = tradingAccountsState.find(a => a.account_uid === activeTradingAccountUid);
        if (!currentValid && tradingAccountsState.length > 0) {
            const defAcc = tradingAccountsState.find(a => a.is_default) || tradingAccountsState[0];
            activeTradingAccountUid = defAcc.account_uid;
            localStorage.setItem('kryvex_active_account_uid', activeTradingAccountUid);
        }

        renderTradingAccountDropdown();
        renderOverviewAccountFunds();
        if (typeof window.renderDepositToAccounts === 'function') {
            window.renderDepositToAccounts();
        }
        window.dispatchEvent(new CustomEvent('kryvex_trading_accounts_loaded', { detail: tradingAccountsState }));
    } catch (err) {
        console.error('Failed to load trading accounts:', err);
    }
}

// Overview "Total funds" always reflects the account chosen in the navbar accounts dropdown.
function renderOverviewAccountFunds() {
    const valueEl = document.getElementById('overviewMainBalance');
    if (!valueEl) return;

    let accounts = tradingAccountsState && tradingAccountsState.length ? tradingAccountsState : [];
    if (!accounts.length) {
        try { accounts = JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]') || []; } catch (e) { accounts = []; }
    }
    const activeUid = activeTradingAccountUid || localStorage.getItem('kryvex_active_account_uid');
    const acc = accounts.find(a => a.account_uid === activeUid) || accounts.find(a => a.is_default) || accounts[0];
    if (!acc) return;

    const isDemo = isDemoTradingAccount(acc);
    const isCent = isCentAccount(acc);
    const broker = String(acc.broker_type || 'kryvex').toLowerCase();
    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USDT');
    const isINR = broker === 'kryvex' && !isDemo && String(activeCur).toUpperCase() === 'INR';
    const rate = inrState.usdtInrRate || 1;

    const mainUsd = Number((isDemo ? 0 : acc.real_main_balance) ?? 0);
    const tradingUsd = Number(acc.balance ?? (isDemo ? acc.demo_available_balance : acc.real_available_balance) ?? 0);
    const lockedUsd = Number(acc.margin_used ?? (isDemo ? acc.demo_locked_balance : acc.real_locked_balance) ?? 0);
    const pnlUsd = Number(acc.unrealized_pnl ?? 0);
    const totalUsd = mainUsd + tradingUsd + lockedUsd + pnlUsd;

    const unit = isINR ? 'INR' : (isCent ? 'USC' : 'USD');
    const fmtValue = (usd) => isINR ? formatInr(usd * rate) : (isCent ? formatNum(usd * 100) : `$${formatNum(usd)}`);
    const fmtFull = (usd, signed = false) => {
        const sign = signed ? (usd >= 0 ? '+' : '-') : '';
        const v = Math.abs(usd);
        return sign + (isINR ? formatInr(v * rate) : (isCent ? `${formatNum(v * 100)} USC` : `$${formatNum(v)}`));
    };

    const setSensitive = (el, text) => {
        if (!el) return;
        el.dataset.realValue = text;
        el.textContent = window.isWalletBalanceHidden ? '••••••••' : text;
    };
    setSensitive(valueEl, fmtValue(totalUsd));
    setSensitive(document.getElementById('ovTooltipWalletVal'), fmtFull(mainUsd));
    setSensitive(document.getElementById('ovTooltipTradingVal'), fmtFull(tradingUsd + lockedUsd));
    const pnlEl = document.getElementById('ovTooltipPnlVal');
    setSensitive(pnlEl, fmtFull(pnlUsd, true));
    if (pnlEl) pnlEl.style.color = pnlUsd > 0 ? '#10b981' : (pnlUsd < 0 ? '#ef4444' : '');

    const unitEl = document.getElementById('overviewMainBalanceUnit');
    if (unitEl) unitEl.textContent = unit;

    const labelEl = document.getElementById('overviewActiveAccountLabel');
    if (labelEl) {
        const platform = broker === 'mt5' ? (isCent ? 'MT5 Cent' : 'MT5') : 'Kryvex';
        const number = acc.account_number ? ` · #${isDemo ? 'D' : 'L'}${acc.account_number}` : '';
        labelEl.textContent = `${acc.account_name || 'Trading Account'} · ${platform} ${isDemo ? 'Demo' : 'Live'}${number}`;
    }
}
window.renderOverviewAccountFunds = renderOverviewAccountFunds;
window.addEventListener('kryvex_currency_changed', () => setTimeout(renderOverviewAccountFunds, 0));
document.addEventListener('DOMContentLoaded', () => renderOverviewAccountFunds());

function renderTradingAccountDropdown() {
    const activeBtnLabel = document.getElementById('activeAccountName');
    const listEl = document.getElementById('tradingAccountList');

    let user = {};
    try {
        user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (e) {}

    const isMt5 = (user.broker_type || 'kryvex').toLowerCase() === 'mt5';
    const accountWrapper = document.querySelector('.trading-account-selector-wrapper');
    if (accountWrapper) {
        accountWrapper.style.display = isMt5 ? 'none' : '';
    }
    const openCreateBtn = document.getElementById('btnOpenCreateAccountModal');
    const dropdownFooter = document.querySelector('.account-dropdown-footer');
    if (openCreateBtn) {
        openCreateBtn.style.display = isMt5 ? 'none' : 'flex';
    }
    if (dropdownFooter) {
        dropdownFooter.style.display = isMt5 ? 'none' : 'block';
    }

    const activeAcc = tradingAccountsState.find(a => a.account_uid === activeTradingAccountUid);
    if (activeBtnLabel) {
        activeBtnLabel.textContent = activeAcc ? activeAcc.account_name : 'Main Account';
    }

    if (listEl) {
        listEl.innerHTML = tradingAccountsState.map(acc => `
            <button type="button" class="account-dropdown-item ${acc.account_uid === activeTradingAccountUid ? 'is-active' : ''}" data-account-uid="${acc.account_uid}">
                <span>${escapeHtml(acc.account_name)}</span>
                ${acc.account_uid === activeTradingAccountUid ? '<span class="account-item-check">✓</span>' : ''}
            </button>
        `).join('');

        listEl.querySelectorAll('.account-dropdown-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const uid = btn.dataset.accountUid;
                if (uid && uid !== activeTradingAccountUid) {
                    switchTradingAccount(uid);
                }
            });
        });
    }

    renderTradingAccountsPageList();
    renderLedgerAccountFilter();
    updateTransferLiveCalculation();
}

function getLiveTradingAccounts() {
    return (tradingAccountsState || []).filter(acc => !isDemoTradingAccount(acc));
}

const TRANSFER_MAIN_WALLET = '__main_wallet__';
const transferState = {
    from: TRANSFER_MAIN_WALLET,
    to: null,
    currency: 'USD', // 'USD' | 'INR' (INR only applies when the Main Wallet is one side)
};

function getTransferRate() {
    return (inrState && inrState.usdtInrRate > 0) ? inrState.usdtInrRate : 95.0;
}

function getTransferEndpoints() {
    const rate = getTransferRate();
    const walletObj = walletDataState.wallet || (window.walletDataState && window.walletDataState.wallet) || {};
    const mainUsd = Number(walletObj.real_main_balance ?? 0);
    const mainInr = Number(walletObj.real_main_balance_inr ?? 0);

    const endpoints = [{
        key: TRANSFER_MAIN_WALLET,
        name: 'Main Wallet',
        isMain: true,
        isCent: false,
        balanceUsd: mainUsd,
        balanceInr: mainInr,
        account: null,
    }];

    getLiveTradingAccounts()
        .filter(acc => String(acc.status || 'active').toLowerCase() === 'active')
        .forEach(acc => {
            const usd = Number(acc.real_available_balance ?? 0);
            let inr = Number(acc.real_available_balance_inr ?? 0);
            if (inr <= 0 && usd > 0) inr = usd * rate;
            endpoints.push({
                key: acc.account_uid,
                name: acc.account_name || 'Trading Account',
                isMain: false,
                isCent: isCentAccount(acc),
                balanceUsd: usd,
                balanceInr: inr,
                account: acc,
            });
        });

    return endpoints;
}

function formatTransferEndpointBalance(ep, currency = transferState.currency) {
    if (!ep) return '--';
    if (currency === 'INR') return formatInr(ep.balanceInr);
    return ep.isCent ? formatUsc(ep.balanceUsd) : formatUsd(ep.balanceUsd);
}

function transferEndpointLabel(ep) {
    if (ep.isMain) return `${ep.name} · ${formatTransferEndpointBalance(ep)}`;
    const acc = ep.account || {};
    const number = acc.account_number ? ` #${acc.account_number}` : '';
    const broker = String(acc.broker_type || '').toLowerCase() === 'mt5' ? 'MT5' : 'Kryvex';
    const kind = ep.isCent ? `${broker} Cent` : broker;
    const target = ep.asMainWallet ? ' → Main Wallet' : '';
    return `${ep.name}${number} (${kind})${target} · ${formatTransferEndpointBalance(ep)}`;
}

function renderTransferAccountSelects() {
    const fromSel = document.getElementById('transferFromAccountSelect');
    const toSel = document.getElementById('transferToAccountSelect');
    const endpoints = getTransferEndpoints();

    if (!endpoints.some(ep => ep.key === transferState.from)) {
        transferState.from = TRANSFER_MAIN_WALLET;
    }
    const fromIsAccount = transferState.from !== TRANSFER_MAIN_WALLET;
    // Account-to-account transfers land in the destination account's Main Wallet, so show that balance.
    const toOptions = endpoints
        .filter(ep => ep.key !== transferState.from)
        .map(ep => (fromIsAccount && !ep.isMain)
            ? {
                ...ep,
                asMainWallet: true,
                balanceUsd: Number(ep.account?.real_main_balance ?? 0),
                balanceInr: Number(ep.account?.real_main_balance_inr ?? 0),
            }
            : ep);
    if (!toOptions.some(ep => ep.key === transferState.to)) {
        transferState.to = (toOptions.find(ep => !ep.isMain) || toOptions[0] || {}).key || null;
    }

    // Every live account has its own Main Wallet, so "Main Wallet" means the main wallet of the account on the other side.
    const mainEp = endpoints.find(ep => ep.isMain);
    const counterpart = endpoints.find(ep => !ep.isMain && (ep.key === transferState.from || ep.key === transferState.to));
    if (mainEp && counterpart) {
        const acc = counterpart.account || {};
        mainEp.balanceUsd = Number(acc.real_main_balance ?? 0);
        mainEp.balanceInr = Number(acc.real_main_balance_inr ?? 0);
        mainEp.isCent = counterpart.isCent;
        mainEp.name = `${counterpart.name} Main Wallet`;
    }

    const optionsHtml = (list, selectedKey) => list.map(ep =>
        `<option value="${escapeHtml(ep.key)}" ${ep.key === selectedKey ? 'selected' : ''}>${escapeHtml(transferEndpointLabel(ep))}</option>`
    ).join('');

    if (fromSel) {
        const html = optionsHtml(endpoints, transferState.from);
        if (fromSel.innerHTML !== html) fromSel.innerHTML = html;
        fromSel.value = transferState.from;
    }
    if (toSel) {
        const html = toOptions.length ? optionsHtml(toOptions, transferState.to) : '<option value="">No other account available</option>';
        if (toSel.innerHTML !== html) toSel.innerHTML = html;
        toSel.value = transferState.to || '';
    }

    return {
        from: endpoints.find(ep => ep.key === transferState.from) || null,
        to: toOptions.find(ep => ep.key === transferState.to) || null,
    };
}

function openAccountTransferModal(fromKey = TRANSFER_MAIN_WALLET, toKey = null, currency = 'USD') {
    transferState.from = fromKey || TRANSFER_MAIN_WALLET;
    transferState.to = toKey;
    transferState.currency = String(currency || '').toUpperCase() === 'INR' ? 'INR' : 'USD';
    const amountInput = document.getElementById('transferAmountInput');
    if (amountInput) amountInput.value = '';
    const modal = document.getElementById('transferModalBackdrop');
    if (modal) modal.hidden = false;
    updateTransferLiveCalculation();
    Promise.all([loadTradingAccounts(), loadWalletConfig()])
        .then(() => updateTransferLiveCalculation())
        .catch(() => {});
}
window.openAccountTransferModal = openAccountTransferModal;

function renderLedgerAccountFilter() {
    const filterEl = document.getElementById('ledgerAccountFilter');
    if (!filterEl) return;

    filterEl.disabled = false;
    filterEl.style.opacity = '1';
    filterEl.style.cursor = 'pointer';

    const mainAcc = tradingAccountsState.find(a => a.is_default || a.account_name === 'Main Account');
    const subAccs = tradingAccountsState.filter(a => !(a.is_default || a.account_name === 'Main Account'));

    let html = `<option value="all">All Accounts</option>`;
    if (mainAcc) {
        const isMainActive = mainAcc.account_uid === activeTradingAccountUid;
        html += `<option value="${escapeHtml(mainAcc.account_uid)}">Main Account${isMainActive ? ' (Active)' : ''}</option>`;
    }
    subAccs.forEach(acc => {
        const isActive = acc.account_uid === activeTradingAccountUid;
        html += `<option value="${escapeHtml(acc.account_uid)}">${escapeHtml(acc.account_name || 'Account')}${isActive ? ' (Active)' : ''}</option>`;
    });

    const prevVal = ledgerFilterState.account_uid || 'all';
    filterEl.innerHTML = html;

    if (Array.from(filterEl.options).some(opt => opt.value === prevVal)) {
        filterEl.value = prevVal;
        ledgerFilterState.account_uid = prevVal;
    } else {
        filterEl.value = 'all';
        ledgerFilterState.account_uid = 'all';
    }
}

async function switchTradingAccount(accountUid) {
    activeTradingAccountUid = accountUid;
    localStorage.setItem('kryvex_active_account_uid', accountUid);

    const activeAcc = tradingAccountsState.find(a => a.account_uid === activeTradingAccountUid);
    if (activeAcc) {
        const isLiveMode = !isDemoTradingAccount(activeAcc);
        tradingAccountsEnvFilter = isLiveMode ? 'live' : 'demo';
        try {
            localStorage.setItem('kryvex_trading_accounts_env', tradingAccountsEnvFilter);
            localStorage.setItem('kryvex_trading_mode', isLiveMode ? 'live' : 'demo');
            window.dispatchEvent(new CustomEvent('kryvex_trading_mode_changed', {
                detail: { mode: isLiveMode ? 'live' : 'demo' }
            }));
            window.dispatchEvent(new CustomEvent('tradingAccountChanged', {
                detail: { accountUid, mode: isLiveMode ? 'live' : 'demo', account: activeAcc }
            }));
        } catch (e) {}
    }

    renderTradingAccountDropdown();
    renderTradingAccountsPageList();
    renderLedgerAccountFilter();

    const dropdown = document.getElementById('tradingAccountDropdown');
    if (dropdown) dropdown.hidden = true;

    showWalletToast(`Switched to ${activeAcc ? activeAcc.account_name : 'Trading Account'}`, 'success');

    try {
        const cacheKey = 'kryvex_wallet_cache_' + accountUid;
        const cachedWallet = localStorage.getItem(cacheKey);
        if (cachedWallet) {
            applyWalletConfigPayload(JSON.parse(cachedWallet), true);
        }
        const ledgerKey = 'kryvex_ledger_cache_' + accountUid;
        const cachedLedger = localStorage.getItem(ledgerKey);
        if (cachedLedger) {
            const p = JSON.parse(cachedLedger);
            renderLedgerTypes(p.ledger_types || []);
            renderLedger(p.ledger, p.pagination);
        }
    } catch (e) {}

    await loadWallet();
    await loadLedgerHistory(1);
}

function setTradingAccountsEnvFilter(env) {
    tradingAccountsEnvFilter = (env === 'demo') ? 'demo' : 'live';
    try {
        localStorage.setItem('kryvex_trading_accounts_env', tradingAccountsEnvFilter);
    } catch (e) {}
    renderTradingAccountsPageList();
}

function setupTradingAccountsEnvToggle() {
    const btnLive = document.getElementById('tabTradingEnvLive');
    const btnDemo = document.getElementById('tabTradingEnvDemo');

    if (btnLive) {
        btnLive.addEventListener('click', (e) => {
            e.preventDefault();
            setTradingAccountsEnvFilter('live');
        });
    }

    if (btnDemo) {
        btnDemo.addEventListener('click', (e) => {
            e.preventDefault();
            setTradingAccountsEnvFilter('demo');
        });
    }
}

function setupTradingAccountControls() {
    setupTradingAccountsEnvToggle();

    const searchInput = document.getElementById('tradingAccountsSearchInput');
    if (searchInput && !searchInput.dataset.wired) {
        searchInput.dataset.wired = 'true';
        searchInput.addEventListener('input', () => {
            renderTradingAccountsPageList();
        });
    }

    const sortSelect = document.getElementById('tradingAccountsSortSelect');
    if (sortSelect && !sortSelect.dataset.wired) {
        sortSelect.dataset.wired = 'true';
        sortSelect.addEventListener('change', () => {
            renderTradingAccountsPageList();
        });
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.dharma-btn-more') && !e.target.closest('.dharma-acc-menu')) {
            document.querySelectorAll('.dharma-acc-menu').forEach(m => m.style.display = 'none');
        }
    });

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
            const token = localStorage.getItem('kryvex_access_token');
            if (!token) {
                showWalletToast('Please login first to create trading accounts.', 'error');
                return;
            }

            let user = {};
            try {
                user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
            } catch (err) {}

            const kycStatus = String(user.kyc_status || '').toLowerCase();
            if (user.kyc_status && !['verified', 'approved'].includes(kycStatus)) {
                showWalletToast('KYC verification is required to create new trading accounts.', 'error');
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

                showWalletToast(res.message || 'Trading account created!', 'success');
                closeCreateModal();
                await loadTradingAccounts();
                if (res.data && res.data.account_uid) {
                    switchTradingAccount(res.data.account_uid);
                }
            } catch (err) {
                showWalletToast(err.message || 'Failed to create trading account.', 'error');
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
    guardAuth();
    loadCachedWalletData();
    setupQuickAmounts();
    setupDepositNetworkControls();
    setupWithdrawAddressControls();
    setupDemoCreditForm();
    setupTransferForm();
    setupWithdrawForm();
    setupLedgerFilters();
    setupInrDepositForm();
    setupInrWithdrawForm();
    setupTradingAccountControls();
    loadTradingAccounts();
    loadWallet();
    loadInrConfig();  // detect base currency + rate

    window.addEventListener('kryvex_currency_changed', () => {
        loadWalletConfig();
    });

    window.addEventListener('tradingModeChanged', () => {
        loadTradingAccounts();
        loadWallet();
    });

    window.addEventListener('kryvex_trading_mode_changed', () => {
        loadTradingAccounts();
        loadWallet();
    });

    window.addEventListener('tradingAccountChanged', (e) => {
        if (e?.detail?.accountUid) {
            activeTradingAccountUid = e.detail.accountUid;
            try {
                localStorage.setItem('kryvex_active_account_uid', activeTradingAccountUid);
            } catch (err) {}
        }
        loadTradingAccounts();
        loadWallet();
    });

    window.addEventListener('kryvex_bank_details_updated', () => {
        loadWallet();
    });
});

function guardAuth() {
    const token = localStorage.getItem('kryvex_access_token');

    if (!token) {
        window.location.href = '/login';
    }
}

function setupQuickAmounts() {
    const amountInput = document.querySelector('#demoCreditForm input[name="amount"]');

    document.querySelectorAll('.wallet-quick-row button[data-amount]').forEach((button) => {
        button.addEventListener('click', () => {
            if (amountInput) {
                amountInput.value = button.dataset.amount || '';
            }
        });
    });
}


function setupDepositNetworkControls() {
    document.getElementById('depositNetworkSelect')?.addEventListener('change', updateWalletDepositAddressDetails);
    
    const depositGrid = document.getElementById('depositNetworkGrid');
    depositGrid?.addEventListener('click', (e) => {
        const netCard = e.target.closest('.deposit-net-card');
        if (!netCard) return;
        const netCode = netCard.getAttribute('data-network');
        const stdSelect = document.getElementById('depositNetworkSelect') || document.getElementById('walletNetworkSelect');
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

        updateWalletDepositAddressDetails();
    });

    const triggerCopyAddr = async () => {
        const addr = document.getElementById('dpDepositAddressText')?.textContent?.trim() || document.getElementById('depositAddressText')?.textContent?.trim();
        if (!addr || addr === 'Loading address...' || addr.includes('Select network')) {
            showWalletToast('No address to copy.', 'error');
            return;
        }
        await copyText(addr);
        showWalletToast('Deposit address copied.', 'success');
    };

    document.getElementById('dpCopyAddressInlineBtn')?.addEventListener('click', triggerCopyAddr);
    document.getElementById('walletCopyAddressInlineBtn')?.addEventListener('click', triggerCopyAddr);
    document.getElementById('copyDepositAddressButton')?.addEventListener('click', triggerCopyAddr);
}

function setupWithdrawAddressControls() {
    const networkSelect = document.getElementById('withdrawNetworkSelect');
    const addressInput = document.getElementById('withdrawWalletAddress');
    const saveButton = document.getElementById('saveWithdrawAddressButton');

    networkSelect?.addEventListener('change', renderWithdrawAddressPanel);
    addressInput?.addEventListener('input', () => {
        const code = normalizeWithdrawNetworkCode(networkSelect?.value);
        const rule = getWithdrawAddressRule(code);
        const value = addressInput.value.trim();
        if (!rule) {
            setWithdrawAddressRuleState('invalid', `Withdrawals are not supported on the ${code} network.`);
            return;
        }
        if (!value) {
            setWithdrawAddressRuleState('idle', rule.rule);
            return;
        }
        if (validateAddressForNetwork(code, value)) {
            setWithdrawAddressRuleState('valid', `Valid ${code} address.`);
        } else {
            setWithdrawAddressRuleState('invalid', `This is not a valid ${code} address. ${rule.rule}`);
        }
    });

    saveButton?.addEventListener('click', saveWithdrawalAddress);
}

async function saveWithdrawalAddress() {
    const network = normalizeWithdrawNetworkCode(document.getElementById('withdrawNetworkSelect')?.value);
    const address = document.getElementById('withdrawWalletAddress')?.value?.trim() || '';

    if (!network || !address) {
        showWalletToast('Select network and enter address.', 'error');
        return;
    }

    if (!validateAddressForNetwork(network, address)) {
        showWalletToast(`This is not a valid ${network} address. ${window.getWithdrawAddressRuleText(network)}`, 'error');
        return;
    }

    try {
        const result = await authFetch('/api/wallet/withdrawal-address', {
            method: 'POST',
            body: JSON.stringify({ network, wallet_address: address }),
        });
        walletDataState.withdrawalAddresses = walletDataState.withdrawalAddresses || {};
        walletDataState.withdrawalAddresses[network] = address;
        setWithdrawAddressRuleState('valid', `Address saved for ${network}. It will only be used on the ${network} network.`);
        showWalletToast(result.message || 'Withdrawal address saved.', 'success');
    } catch (error) {
        showWalletToast(error.message || 'Unable to save address.', 'error');
    }
}

function normalizeNetworks(value) {
    const rows = Array.isArray(value) ? value : Object.values(value || {});
    return rows.map((item) => ({
        network: String(item.network || item.code || '').toUpperCase(),
        label: item.label || item.name || item.network || item.code || '',
        address: item.address || item.deposit_address || '',
        minimum_deposit: Number(item.minimum_deposit || item.min_deposit || 0),
        confirmations: item.confirmations || item.network_confirmations || '--',
        rule: item.rule || item.address_rule || '',
        pattern: item.pattern || '',
    })).filter((item) => item.network);
}

let lastRenderedNetworksJson = null;

function renderNetworkSelects() {
    const currentJson = JSON.stringify(walletDataState.depositNetworks);
    if (lastRenderedNetworksJson === currentJson) {
        return;
    }
    lastRenderedNetworksJson = currentJson;

    const html = walletDataState.depositNetworks.length
        ? walletDataState.depositNetworks.map((network) => `<option value="${escapeHtml(network.network)}">${escapeHtml(network.label || network.network)}</option>`).join('')
        : '<option value="">No network configured</option>';

    ['depositNetworkSelect', 'withdrawNetworkSelect'].forEach((id) => {
        const select = document.getElementById(id);
        if (!select) return;
        const previous = select.value;
        select.innerHTML = html;
        if (previous && walletDataState.depositNetworks.some((item) => item.network === previous)) {
            select.value = previous;
        }
    });
}

function getSelectedNetwork(networkCode) {
    const code = String(networkCode || '').toUpperCase();
    return walletDataState.depositNetworks.find((item) => item.network === code) || null;
}

function updateWalletDepositAddressDetails() {
    const selectStd = document.getElementById('depositNetworkSelect') || document.getElementById('walletNetworkSelect');
    const netCode = selectStd?.value || walletDataState.depositNetworks[0]?.network || 'TRC20';
    const network = getSelectedNetwork(netCode);

    const addrTitle = document.getElementById('depositAddressTitleLabel') || document.getElementById('walletDepositAddressTitleLabel');
    const addrText = document.getElementById('dpDepositAddressText') || document.getElementById('depositAddressText');
    const qrImg = document.getElementById('dpQrImage') || document.getElementById('walletQrImage');
    const noteMin = document.getElementById('dpNoteMinDeposit') || document.getElementById('depositMinimumText');
    const shieldText = document.getElementById('dpFooterShieldText');

    const address = network?.address || 'No deposit address available.';
    const netLabel = network?.network || 'TRC20';
    const minDep = network?.minimum_deposit || 100;

    if (addrTitle) addrTitle.textContent = `USDT Deposit Address (${netLabel})`;
    if (addrText) addrText.textContent = address;
    if (noteMin) noteMin.textContent = `• Minimum deposit: $${minDep} (or equivalent)`;
    if (shieldText) shieldText.textContent = `Send only USDT to this address via ${netLabel} network.`;

    const dpInrSec = document.getElementById('dpInrSection');
    const isInrVisible = dpInrSec && dpInrSec.style.display !== 'none';
    if (!isInrVisible) {
        const notesList = document.getElementById('dpNotesList') || document.getElementById('walletDpNotesList');
        if (notesList) {
            notesList.innerHTML = `
                <li>• Minimum deposit: $${minDep} (or equivalent)</li>
                <li>• Send only USDT to this ${escapeHtml(netLabel)} address.</li>
                <li>• Ensure the network matches (${escapeHtml(netLabel)}).</li>
                <li>• Deposits will be credited after network confirmation.</li>
            `;
        }
    }

    if (qrImg && address && address !== 'No deposit address available.' && !address.includes('Select network') && !address.includes('Loading address')) {
        qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${encodeURIComponent(address)}`;
    }
}
window.updateWalletDepositAddressDetails = updateWalletDepositAddressDetails;

function renderDepositAddressPanel() {
    updateWalletDepositAddressDetails();
}

function renderWalletDepositInrBankDetails() {
    const container = document.getElementById('futuresInrBankInfo') || document.getElementById('inrBankInfo');
    if (!container) return;

    let d = inrState.adminBankDetails;
    if (!d) {
        try {
            const cached = JSON.parse(localStorage.getItem('kryvex_inr_state_cache') || '{}');
            d = cached?.adminBankDetails || null;
            if (d && !inrState.adminBankDetails) inrState.adminBankDetails = d;
        } catch (e) {}
    }

    if (!d) {
        container.innerHTML = '<div class="p-3 text-center text-amber-400 text-xs font-bold">Admin has not set INR bank details yet.</div>';
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
                <button type="button" onclick="navigator.clipboard?.writeText('${escapeHtml(d.account_number)}');showWalletToast('Account Number copied.','success');" class="px-2.5 py-1 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/30 text-emerald-400 text-[11px] font-bold transition-all border border-emerald-500/20 cursor-pointer flex-shrink-0">
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
                    <button type="button" onclick="navigator.clipboard?.writeText('${escapeHtml(d.ifsc)}');showWalletToast('IFSC Code copied.','success');" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold transition-all flex-shrink-0 cursor-pointer">
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
                    <button type="button" onclick="navigator.clipboard?.writeText('${escapeHtml(d.upi_id)}');showWalletToast('UPI ID copied.','success');" class="px-2 py-0.5 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 text-[10px] font-bold transition-all flex-shrink-0 cursor-pointer">
                        Copy
                    </button>
                </div>
                ` : ''}
            </div>
        </div>
    `;

    container.innerHTML = html;
}
window.renderWalletDepositInrBankDetails = renderWalletDepositInrBankDetails;

function updateWalletDepositInrCalc() {
    const dpInrInput = document.getElementById('dpInrAmountInput');
    const youPayEl = document.getElementById('inrDepositConvYouPay');
    const creditEl = document.getElementById('inrDepositConvCredit');
    const buyRate = inrState.usdtInrBuyRate || inrState.usdtInrRate || 95.00;

    const val = Number(dpInrInput?.value || 0);
    const creditUsd = buyRate > 0 ? (val / buyRate) : 0;

    if (youPayEl) {
        youPayEl.textContent = formatInr(val);
    }
    if (creditEl) {
        creditEl.textContent = '$' + creditUsd.toFixed(2) + ' USD';
    }
}
window.updateWalletDepositInrCalc = updateWalletDepositInrCalc;

function renderWalletDepositUi(targetCurrency) {
    const rawCur = targetCurrency ? targetCurrency.toUpperCase() : (window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD'));
    const isINR = (rawCur === 'INR');

    const dpHeadTitle = document.getElementById('dpHeadTitle');
    const dpHeadSub = document.getElementById('dpHeadSub');
    const dpHeadIconCircle = document.getElementById('dpHeadIconCircle');
    const dpUsdSection = document.getElementById('dpUsdSection');
    const dpInrSection = document.getElementById('dpInrSection');
    const convBox = document.getElementById('walletDepositInrConversionBox');
    const buyRate = inrState.usdtInrBuyRate || inrState.usdtInrRate || 95.00;

    const mainAccBalEl = document.getElementById('depositMainWalletBalance');
    const mainBalUsdt = Number(walletRuleState.demoMainBalance || 0);
    const mainBalInr = Number(walletRuleState.demoMainBalanceInr || (mainBalUsdt * buyRate));

    if (mainAccBalEl) {
        mainAccBalEl.textContent = isINR ? formatInr(mainBalInr) : formatUsd(mainBalUsdt);
    }

    const rateBar = document.getElementById('futuresRateBar');
    const rateVal = document.getElementById('futuresRateVal');
    if (rateVal) rateVal.textContent = `1 USDT = ₹${buyRate.toFixed(2)} (Admin Buy Rate)`;
    if (rateBar) rateBar.style.display = isINR ? 'flex' : 'none';

    if (isINR) {
        if (dpHeadTitle) dpHeadTitle.textContent = 'Deposit INR (₹)';
        if (dpHeadSub) dpHeadSub.textContent = 'Transfer INR via UPI / NetBanking & submit UTR / Ref No.';
        if (dpHeadIconCircle) dpHeadIconCircle.textContent = '₹';

        if (dpUsdSection) dpUsdSection.style.display = 'none';
        if (dpInrSection) dpInrSection.style.display = 'block';
        if (convBox) convBox.style.display = 'block';

        renderWalletDepositInrBankDetails();

        const minInr = inrState.minDepositInr || 100;
        const notesList = document.getElementById('dpNotesList');
        if (notesList) {
            notesList.innerHTML = `
                <li>• Minimum deposit: ₹ ${minInr} INR</li>
                <li>• Transfer funds via UPI/IMPS/NEFT.</li>
                <li>• Enter your 12-digit UTR/Transaction Reference No. accurately for fast approval.</li>
            `;
        }
        updateWalletDepositInrCalc();
    } else {
        if (dpHeadTitle) dpHeadTitle.textContent = 'Deposit USD (USDT)';
        if (dpHeadSub) dpHeadSub.textContent = 'Deposit USDT to your account using the details below.';
        if (dpHeadIconCircle) dpHeadIconCircle.textContent = '₮';

        if (dpUsdSection) dpUsdSection.style.display = 'block';
        if (dpInrSection) dpInrSection.style.display = 'none';
        if (convBox) convBox.style.display = 'none';

        const grid = document.getElementById('depositNetworkGrid');
        const selectStd = document.getElementById('depositNetworkSelect') || document.getElementById('walletNetworkSelect');

        if (grid) {
            const allowedNetworks = ['TRC20', 'ERC20', 'BEP20'];
            let displayNetworks = (walletDataState.depositNetworks || []).filter(net => allowedNetworks.includes(String(net.network || '').toUpperCase()));

            allowedNetworks.forEach(netCode => {
                if (!displayNetworks.some(n => String(n.network || '').toUpperCase() === netCode)) {
                    const matchedObj = (walletDataState.depositNetworks || []).find(n => String(n.network || '').toUpperCase() === netCode);
                    displayNetworks.push({
                        network: netCode,
                        label: netCode === 'TRC20' ? 'Tron Network' : netCode === 'ERC20' ? 'Ethereum Network' : 'BNB Smart Chain',
                        address: matchedObj?.address || walletDataState.depositNetworks?.[0]?.address || 'TQ9a1xE7g2Y4k8UD3fJ6w5a9sZb8nm3h7qR',
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
        updateWalletDepositAddressDetails();
    }
}
window.renderWalletDepositUi = renderWalletDepositUi;

function openWalletDepositModal(targetCurrency = 'USDT') {
    const modal = document.getElementById('depositModalBackdrop');
    if (modal) {
        modal.hidden = true;
        modal.style.display = 'none';
    }
    if (typeof window.switchMainTab === 'function') {
        window.switchMainTab('wallet-ops', true);
    }
    if (typeof window.switchWalletInnerNav === 'function') {
        window.switchWalletInnerNav('deposit');
    }
    const cur = String(targetCurrency || 'USDT').toUpperCase();
    if (cur === 'INR' || cur === 'MONEY') {
        if (typeof window.setStepDepositMode === 'function') {
            window.setStepDepositMode('money');
        }
    } else {
        if (typeof window.setStepDepositMode === 'function') {
            window.setStepDepositMode('crypto');
        }
        if (typeof window.selectStepDepositCurrency === 'function') {
            window.selectStepDepositCurrency(cur);
        }
    }
    setTimeout(() => {
        const el = document.getElementById('innerPanelDeposit') || document.getElementById('viewSectionWalletOps');
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 60);
}
window.openWalletDepositModal = openWalletDepositModal;
window.openDepositModal = openWalletDepositModal;

// Keep in sync with WITHDRAW_ADDRESS_RULES in app/Http/Controllers/Api/WalletController.php.
const WITHDRAW_ADDRESS_RULES = {
    TRC20: { pattern: /^T[1-9A-HJ-NP-Za-km-z]{33}$/, placeholder: 'Paste your TRC20 address (starts with T)', rule: 'Enter a valid TRON (TRC20) address: starts with "T" and is 34 characters long.' },
    ERC20: { pattern: /^0x[a-fA-F0-9]{40}$/, placeholder: 'Paste your ERC20 address (starts with 0x)', rule: 'Enter a valid Ethereum (ERC20) address: starts with "0x" followed by 40 hex characters.' },
    BEP20: { pattern: /^0x[a-fA-F0-9]{40}$/, placeholder: 'Paste your BEP20 address (starts with 0x)', rule: 'Enter a valid BNB Smart Chain (BEP20) address: starts with "0x" followed by 40 hex characters.' },
    POLYGON: { pattern: /^0x[a-fA-F0-9]{40}$/, placeholder: 'Paste your Polygon address (starts with 0x)', rule: 'Enter a valid Polygon address: starts with "0x" followed by 40 hex characters.' },
    ARB: { pattern: /^0x[a-fA-F0-9]{40}$/, placeholder: 'Paste your Arbitrum address (starts with 0x)', rule: 'Enter a valid Arbitrum address: starts with "0x" followed by 40 hex characters.' },
    AVAX: { pattern: /^0x[a-fA-F0-9]{40}$/, placeholder: 'Paste your Avalanche C-Chain address (starts with 0x)', rule: 'Enter a valid Avalanche C-Chain address: starts with "0x" followed by 40 hex characters.' },
    SOL: { pattern: /^[1-9A-HJ-NP-Za-km-z]{32,44}$/, placeholder: 'Paste your Solana address', rule: 'Enter a valid Solana address: 32-44 base58 characters (no 0x prefix).' },
    BTC: { pattern: /^(bc1[02-9ac-hj-np-z]{11,71}|[13][1-9A-HJ-NP-Za-km-z]{25,34})$/, placeholder: 'Paste your Bitcoin address (bc1, 1 or 3)', rule: 'Enter a valid Bitcoin address: starts with "bc1", "1" or "3".' },
    LTC: { pattern: /^(ltc1[02-9ac-hj-np-z]{11,71}|[LM3][1-9A-HJ-NP-Za-km-z]{26,33})$/, placeholder: 'Paste your Litecoin address (ltc1, L, M or 3)', rule: 'Enter a valid Litecoin address: starts with "ltc1", "L", "M" or "3".' },
    DOGE: { pattern: /^D[5-9A-HJ-NP-U][1-9A-HJ-NP-Za-km-z]{32}$/, placeholder: 'Paste your Dogecoin address (starts with D)', rule: 'Enter a valid Dogecoin address: starts with "D" and is 34 characters long.' },
    XRP: { pattern: /^r[1-9A-HJ-NP-Za-km-z]{24,34}$/, placeholder: 'Paste your XRP address (starts with r)', rule: 'Enter a valid XRP address: starts with "r" and is 25-35 characters long.' },
    ADA: { pattern: /^(addr1[02-9ac-hj-np-z]{50,110}|(Ae2|DdzFf)[1-9A-HJ-NP-Za-km-z]{50,120})$/, placeholder: 'Paste your Cardano address (starts with addr1)', rule: 'Enter a valid Cardano address: usually starts with "addr1".' },
    DOT: { pattern: /^1[1-9A-HJ-NP-Za-km-z]{45,47}$/, placeholder: 'Paste your Polkadot address (starts with 1)', rule: 'Enter a valid Polkadot address: starts with "1" and is 46-48 characters long.' },
    TON: { pattern: /^((EQ|UQ|kQ|0Q)[A-Za-z0-9_-]{46}|-?[01]:[a-fA-F0-9]{64})$/, placeholder: 'Paste your TON address (starts with EQ or UQ)', rule: 'Enter a valid TON address: starts with "EQ" or "UQ" and is 48 characters long.' },
};

const WITHDRAW_NETWORK_ALIASES = { USDT: 'TRC20', ETH: 'ERC20', USDC: 'ERC20', BNB: 'BEP20', MATIC: 'POLYGON' };

function normalizeWithdrawNetworkCode(network) {
    const raw = typeof network === 'object' && network !== null ? network.network : network;
    const code = String(raw || '').trim().toUpperCase();
    return WITHDRAW_NETWORK_ALIASES[code] || code;
}

function getWithdrawAddressRule(network) {
    return WITHDRAW_ADDRESS_RULES[normalizeWithdrawNetworkCode(network)] || null;
}

function getSavedWithdrawalAddress(network) {
    const code = normalizeWithdrawNetworkCode(network);
    const saved = walletDataState.withdrawalAddresses || {};
    return saved[code] || '';
}

function setWithdrawAddressRuleState(state, text) {
    const rule = document.getElementById('withdrawAddressRuleText');
    const input = document.getElementById('withdrawWalletAddress');
    if (rule) {
        if (rule.dataset.idleColor === undefined) rule.dataset.idleColor = rule.style.color;
        rule.textContent = text;
        rule.style.color = state === 'valid' ? '#10b981' : (state === 'invalid' ? '#f87171' : rule.dataset.idleColor);
    }
    if (input) {
        input.style.borderColor = state === 'valid' ? 'rgba(16, 185, 129, 0.6)' : (state === 'invalid' ? 'rgba(248, 113, 113, 0.7)' : '');
    }
}

let lastRenderedWithdrawNetwork = null;

function renderWithdrawAddressPanel() {
    const select = document.getElementById('withdrawNetworkSelect');
    const input = document.getElementById('withdrawWalletAddress');
    const code = normalizeWithdrawNetworkCode(select?.value);
    const rule = getWithdrawAddressRule(code);
    const networkChanged = code !== lastRenderedWithdrawNetwork;
    lastRenderedWithdrawNetwork = code;

    if (input) {
        // Keep unsaved typing when the wallet config reloads on the same network.
        if (networkChanged || !input.value.trim()) {
            input.value = getSavedWithdrawalAddress(code);
        }
        input.placeholder = rule?.placeholder || 'Paste your receiving wallet address';
    }

    const value = input?.value.trim() || '';
    if (!rule) {
        setWithdrawAddressRuleState('invalid', code ? `Withdrawals are not supported on the ${code} network.` : 'Select a network to view the address rule.');
    } else if (!value) {
        setWithdrawAddressRuleState('idle', rule.rule);
    } else if (!validateAddressForNetwork(code, value)) {
        setWithdrawAddressRuleState('invalid', `This is not a valid ${code} address. ${rule.rule}`);
    } else if (value === getSavedWithdrawalAddress(code)) {
        setWithdrawAddressRuleState('valid', `Saved ${code} address loaded.`);
    } else {
        setWithdrawAddressRuleState('valid', `Valid ${code} address.`);
    }
}

function validateAddressForNetwork(network, address) {
    const value = String(address || '').trim();
    const rule = getWithdrawAddressRule(network);
    return Boolean(rule && value && rule.pattern.test(value));
}

window.renderWithdrawAddressPanel = renderWithdrawAddressPanel;
window.validateWithdrawAddress = validateAddressForNetwork;
window.getWithdrawAddressRuleText = (network) => getWithdrawAddressRule(network)?.rule || `Withdrawals are not supported on the ${normalizeWithdrawNetworkCode(network)} network.`;

async function copyText(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }
    const input = document.createElement('textarea');
    input.value = text;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    input.remove();
}

function setupDemoCreditForm() {
    // Live INR calculation
    document.getElementById('dpInrAmountInput')?.addEventListener('input', () => {
        updateWalletDepositInrCalc();
    });

    // USD Deposit Submit Button (Modern UI)
    document.getElementById('dpUsdSubmitBtn')?.addEventListener('click', async () => {
        const amtInput = document.getElementById('dpUsdAmountInput');
        const amount = Number(amtInput?.value || 0);

        if (!amount || amount <= 0) {
            showWalletToast('Please enter a valid deposit amount in USD.', 'error');
            return;
        }

        const selectStd = document.getElementById('depositNetworkSelect') || document.getElementById('walletNetworkSelect');
        const network = selectStd?.value || 'TRC20';

        let user = {};
        try {
            user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
        } catch (e) {}
        const broker = (user.broker_type || 'kryvex').toLowerCase();

        const btn = document.getElementById('dpUsdSubmitBtn');
        if (btn) btn.setAttribute('disabled', 'disabled');

        try {
            const data = await authFetch(`/api/wallet/demo-credit?broker_type=${broker}`, {
                method: 'POST',
                headers: { 'X-Broker-Type': broker },
                body: JSON.stringify({ amount, network }),
            });

            showWalletToast(data.message || 'Deposit successful! Funds credited to wallet.', 'success');
            if (amtInput) amtInput.value = '';
            const depositModal = document.getElementById('depositModalBackdrop');
            if (depositModal) {
                depositModal.hidden = true;
                depositModal.style.display = 'none';
            }
            await loadWalletConfig();
            await loadLedgerHistory(1);
        } catch (error) {
            showWalletToast(error.message || 'Unable to submit deposit.', 'error');
        } finally {
            if (btn) btn.removeAttribute('disabled');
        }
    });

    // INR Deposit Submit Button (Modern UI)
    document.getElementById('dpInrSubmitBtn')?.addEventListener('click', async () => {
        if (!verifyKycAndCurrencyBeforeAction()) return;
        const amtInput = document.getElementById('dpInrAmountInput');
        const amountInr = Number(amtInput?.value || 0);

        if (!amountInr || amountInr <= 0) {
            showWalletToast('Please enter a valid deposit amount in INR.', 'error');
            return;
        }

        const btn = document.getElementById('dpInrSubmitBtn');
        if (btn) {
            btn.setAttribute('disabled', 'disabled');
            btn.textContent = 'Initiating Razorpay...';
        }

        let user = {};
        try {
            user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
        } catch (err) {}

        try {
            const orderRes = await authFetch('/api/wallet/razorpay/create-order', {
                method: 'POST',
                body: JSON.stringify({ amount_inr: amountInr, account_uid: selectedDepositAccountUid() }),
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
                description: `INR Wallet Deposit ₹${amountInr}`,
                order_id: orderRes.order_id,
                prefill: {
                    name: orderRes.user?.name || user.name || '',
                    email: orderRes.user?.email || user.email || '',
                    contact: orderRes.user?.mobile || user.mobile || '',
                },
                theme: {
                    color: '#2563eb',
                },
                handler: async function (response) {
                    if (btn) btn.textContent = 'Verifying Payment...';
                    try {
                        const verifyRes = await authFetch('/api/wallet/razorpay/verify', {
                            method: 'POST',
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id || orderRes.order_id,
                                razorpay_payment_id: response.razorpay_payment_id || '',
                                razorpay_signature: response.razorpay_signature || '',
                                amount_inr: amountInr,
                            }),
                        });

                        showWalletToast(verifyRes.message || 'Razorpay Payment Verified & Wallet Credited! 🎉', 'success');
                        if (amtInput) amtInput.value = '';
                        const depositModal = document.getElementById('depositModalBackdrop');
                        if (depositModal) {
                            depositModal.hidden = true;
                            depositModal.style.display = 'none';
                        }
                        if (window.loadWalletConfig) await window.loadWalletConfig();
                        if (window.loadLedgerHistory) await window.loadLedgerHistory(1);
                        if (window.reloadFuturesBalances) await window.reloadFuturesBalances();
                    } catch (err) {
                        showWalletToast(err.message || 'Payment verification failed.', 'error');
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
                        showWalletToast('Razorpay payment cancelled.', 'warning');
                    }
                }
            };

            const rzp = new RazorpayCtor(options);
            rzp.open();
        } catch (error) {
            showWalletToast(error.message || 'Unable to initiate Razorpay payment.', 'error');
            if (btn) {
                btn.removeAttribute('disabled');
                btn.textContent = 'Submit INR Deposit Request';
            }
        }
    });

    // Close Modal Button
    document.getElementById('closeDepositModalBtn')?.addEventListener('click', () => {
        const depositModal = document.getElementById('depositModalBackdrop');
        if (depositModal) {
            depositModal.hidden = true;
            depositModal.style.display = 'none';
        }
    });
}


function setupTransferForm() {
    const form = document.getElementById('walletTransferForm');
    if (!form) return;

    const amountInput = document.getElementById('transferAmountInput');
    const maxBtn = document.getElementById('transferMaxButton');
    const curInrBtn = document.getElementById('transferCurInrBtn');
    const curUsdtBtn = document.getElementById('transferCurUsdtBtn');
    const swapBtn = document.getElementById('transferSwapDirectionBtn');
    const fromSel = document.getElementById('transferFromAccountSelect');
    const toSel = document.getElementById('transferToAccountSelect');

    fromSel?.addEventListener('change', () => {
        transferState.from = fromSel.value;
        if (amountInput) amountInput.value = '';
        updateTransferLiveCalculation();
    });

    toSel?.addEventListener('change', () => {
        transferState.to = toSel.value || null;
        updateTransferLiveCalculation();
    });

    swapBtn?.addEventListener('click', () => {
        if (!transferState.to) return;
        [transferState.from, transferState.to] = [transferState.to, transferState.from];
        if (amountInput) amountInput.value = '';
        updateTransferLiveCalculation();
    });

    curInrBtn?.addEventListener('click', () => {
        transferState.currency = 'INR';
        if (amountInput) amountInput.value = '';
        updateTransferLiveCalculation();
    });
    curUsdtBtn?.addEventListener('click', () => {
        transferState.currency = 'USD';
        if (amountInput) amountInput.value = '';
        updateTransferLiveCalculation();
    });

    const fillAmount = (pct) => {
        const { maxInInputUnit } = getTransferContext();
        if (!amountInput || maxInInputUnit <= 0) return;
        const value = maxInInputUnit * (pct / 100);
        amountInput.value = value >= 1 ? (Math.floor(value * 100) / 100).toFixed(2) : value.toFixed(6);
        amountInput.dispatchEvent(new Event('input', { bubbles: true }));
    };

    document.querySelectorAll('.transfer-pct-btn').forEach((btn) => {
        btn.addEventListener('click', () => fillAmount(Number(btn.getAttribute('data-pct') || 100)));
    });
    maxBtn?.addEventListener('click', () => fillAmount(100));

    amountInput?.addEventListener('input', updateTransferLiveCalculation);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const ctx = getTransferContext();
        const { from, to, isINR, inputUnit } = ctx;
        const rawAmount = Number(amountInput?.value || 0);

        if (!from || !to) {
            showWalletToast('Please choose the account to transfer to.', 'error');
            return;
        }
        if (!rawAmount || rawAmount <= 0) {
            showWalletToast('Transfer amount must be greater than zero.', 'error');
            return;
        }
        if (rawAmount > ctx.maxInInputUnit + 1e-8) {
            showWalletToast(`Insufficient balance in ${from.name}.`, 'error');
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Transferring...';
        }

        // Cent accounts are entered in USC; the backend always works in USD.
        const amountUsd = inputUnit === 'USC' ? rawAmount / 100 : rawAmount;

        let url;
        let payload;
        let broker = 'kryvex';
        if (!from.isMain && !to.isMain) {
            url = '/api/wallet/account-transfer';
            payload = { from_account_uid: from.key, to_account_uid: to.key, amount: amountUsd };
        } else {
            const account = from.isMain ? to : from;
            broker = String(account.account?.broker_type || 'kryvex').toLowerCase();
            url = `/api/wallet/transfer?broker_type=${broker}`;
            payload = {
                account_uid: account.key,
                direction: from.isMain ? 'main_to_trading' : 'trading_to_main',
                amount: isINR ? rawAmount : amountUsd,
                currency: isINR ? 'INR' : 'USDT',
            };
        }

        try {
            const data = await authFetch(url, {
                method: 'POST',
                headers: { 'X-Broker-Type': broker },
                body: JSON.stringify(payload),
            });

            showWalletToast(data.message || 'Transfer completed successfully.', 'success');
            if (amountInput) amountInput.value = '';
            const transferModal = document.getElementById('transferModalBackdrop');
            if (transferModal) transferModal.hidden = true;
            await Promise.all([loadTradingAccounts(), loadWalletConfig()]);
            loadLedgerHistory(1);
            if (typeof window.loadAndRenderAllWalletBalances === 'function') window.loadAndRenderAllWalletBalances();
            window.dispatchEvent(new CustomEvent('kryvex_wallet_updated'));
        } catch (error) {
            showWalletToast(error.message || 'Unable to transfer balance.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            }
        }
    });
}

function getTransferContext() {
    const { from, to } = renderTransferAccountSelects();
    const mainInvolved = Boolean(from?.isMain || to?.isMain);
    if (!mainInvolved) transferState.currency = 'USD';
    const isINR = transferState.currency === 'INR';
    const inputUnit = isINR ? 'INR' : (from?.isCent ? 'USC' : 'USD');

    let maxInInputUnit = 0;
    if (from) {
        if (isINR) maxInInputUnit = from.balanceInr;
        else maxInInputUnit = from.isCent ? from.balanceUsd * 100 : from.balanceUsd;
    }

    return { from, to, mainInvolved, isINR, inputUnit, maxInInputUnit: Math.max(0, maxInInputUnit) };
}

function updateTransferLiveCalculation() {
    const amountInput = document.getElementById('transferAmountInput');
    if (!amountInput || !document.getElementById('transferFromAccountSelect')) return;

    const { from, to, mainInvolved, isINR, inputUnit, maxInInputUnit } = getTransferContext();
    const rate = getTransferRate();

    const curWrap = document.getElementById('transferCurrencyToggleWrap');
    const curInrBtn = document.getElementById('transferCurInrBtn');
    const curUsdtBtn = document.getElementById('transferCurUsdtBtn');
    if (curWrap) curWrap.style.display = mainInvolved ? '' : 'none';
    const activeCls = 'transfer-cur-btn active py-2.5 px-3 rounded-xl border text-xs font-black transition-all flex items-center justify-center cursor-pointer bg-purple-600/20 border-purple-500/30 text-purple-300 shadow-sm';
    const idleCls = 'transfer-cur-btn py-2.5 px-3 rounded-xl border text-xs font-bold transition-all flex items-center justify-center cursor-pointer bg-transparent border-transparent text-slate-400 hover:text-white';
    if (curInrBtn) curInrBtn.className = isINR ? activeCls : idleCls;
    if (curUsdtBtn) curUsdtBtn.className = isINR ? idleCls : activeCls;

    const fromBal = document.getElementById('transferFromBalance');
    const toBal = document.getElementById('transferToBalance');
    if (fromBal) fromBal.textContent = formatTransferEndpointBalance(from);
    if (toBal) toBal.textContent = to ? formatTransferEndpointBalance(to) : '--';

    const formatInUnit = (value, unit) => {
        if (unit === 'INR') return formatInr(value);
        if (unit === 'USC') return formatUsc(value / 100);
        return formatUsd(value);
    };

    const maxAvailEl = document.getElementById('transferMaxAvailableVal');
    if (maxAvailEl) maxAvailEl.textContent = formatInUnit(maxInInputUnit, inputUnit);

    const labelEl = document.getElementById('transferAmountLabel');
    if (labelEl) labelEl.textContent = `Amount (${inputUnit === 'USD' ? 'USDT' : inputUnit})`;
    amountInput.placeholder = inputUnit === 'USC' ? 'e.g. 1000 USC' : (inputUnit === 'INR' ? 'e.g. 10000 (INR)' : 'e.g. 100');

    const rateBar = document.getElementById('transferInrRateBar');
    const rateVal = document.getElementById('transferInrRateVal');
    if (rateBar) rateBar.style.display = isINR ? 'flex' : 'none';
    if (rateVal) rateVal.textContent = `1 USDT = ₹${rate}`;

    // "You send / They receive" preview, which is where USD <-> USC conversion becomes visible.
    const calcBox = document.getElementById('transferConversionBox');
    const fromLabel = document.getElementById('transferConvFromLabel');
    const fromVal = document.getElementById('transferConvFromVal');
    const toLabel = document.getElementById('transferConvToLabel');
    const toVal = document.getElementById('transferConvToVal');

    const val = Number(amountInput.value || 0);
    const showPreview = Boolean(from && to) && (val > 0 || isINR || from.isCent || to.isCent);
    if (calcBox) calcBox.style.display = showPreview ? 'block' : 'none';
    if (!showPreview) return;

    let receiveText;
    if (isINR) {
        const usd = rate > 0 ? val / rate : 0;
        receiveText = from.isMain
            ? (to.isCent ? formatUsc(usd) : `${formatUsd(usd)} USDT`)
            : formatInr(val);
    } else {
        const usd = inputUnit === 'USC' ? val / 100 : val;
        receiveText = to.isCent ? formatUsc(usd) : formatUsd(usd);
    }

    if (fromLabel) fromLabel.textContent = `You send from ${from.name}:`;
    if (fromVal) fromVal.textContent = formatInUnit(val, inputUnit);
    if (toLabel) toLabel.textContent = `${to.name}${to.asMainWallet ? ' Main Wallet' : ''} receives:`;
    if (toVal) toVal.textContent = receiveText;
}
window.updateTransferLiveCalculation = updateTransferLiveCalculation;

window.addEventListener('kryvex_currency_changed', () => {
    updateTransferLiveCalculation();
});

function setupWithdrawForm() {
    const form = document.getElementById('withdrawForm');

    if (!form) {
        return;
    }

    form.querySelector('input[name="amount"]')?.addEventListener('input', updateWithdrawPreview);

    const fillWithdrawAmount = (pct) => {
        const input = form.querySelector('input[name="amount"]');
        if (!input) return;

        const { asset, network, amount: available, price, supported, isUsd } = getCryptoWithdrawable();
        if (!supported) {
            showWalletToast(`${asset} withdrawals on the ${network} network are not supported yet.`, 'error');
            return;
        }

        let maxVal = available;
        const adminMaxUsd = Number(walletRuleState.maximumWithdrawAmount || 0);
        if (adminMaxUsd > 0 && price > 0) maxVal = Math.min(maxVal, adminMaxUsd / price);

        if (maxVal <= 0) {
            showWalletToast(`Your available ${asset} balance is 0. Please deposit ${asset} first.`, 'error');
            return;
        }

        // Floor (not round) so MAX never exceeds the withdrawable balance.
        const decimals = isUsd ? 2 : 8;
        const factor = 10 ** decimals;
        const target = Math.floor(maxVal * (pct / 100) * factor) / factor;
        input.value = target.toFixed(decimals);
        input.dispatchEvent(new Event('input', { bubbles: true }));
        updateWithdrawPreview();
        input.focus();
    };

    document.querySelectorAll('.withdraw-pct-btn[data-pct]').forEach((btn) => {
        btn.addEventListener('click', () => fillWithdrawAmount(Number(btn.getAttribute('data-pct') || 100)));
    });

    document.getElementById('withdrawMaxButton')?.addEventListener('click', () => fillWithdrawAmount(100));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (!verifyKycAndCurrencyBeforeAction()) {
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.originalHtml = submitBtn.innerHTML;
            submitBtn.innerHTML = 'Requesting...';
        }

        const payload = formToObject(form);
        payload.amount = Number(payload.amount || 0);
        payload.wallet_address = String(payload.wallet_address || '').trim();

        if (!payload.network) {
            showWalletToast('Select withdrawal network.', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            }
            return;
        }

        if (!payload.wallet_address) {
            showWalletToast('Enter your withdrawal address.', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            }
            return;
        }

        const selectedNetwork = getSelectedNetwork(payload.network);
        if (selectedNetwork && !validateAddressForNetwork(selectedNetwork, payload.wallet_address)) {
            showWalletToast(selectedNetwork.rule || 'Wallet address does not match selected network rule.', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            }
            return;
        }

        if (!payload.amount || payload.amount <= 0) {
            showWalletToast('Enter a valid withdrawal amount.', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            }
            return;
        }

        if (!walletRuleState.withdrawalEnabled) {
            showWalletToast('Withdrawals are currently disabled by admin.', 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            }
            return;
        }

        const withdrawable = getCryptoWithdrawable();
        payload.currency = withdrawable.asset;
        const amountUsd = payload.amount * (withdrawable.price || 0);

        const failValidation = (message) => {
            showWalletToast(message, 'error');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            }
        };

        if (!withdrawable.supported) {
            failValidation(`${withdrawable.asset} withdrawals on the ${withdrawable.network} network are not supported yet.`);
            return;
        }

        if (withdrawable.price > 0 && walletRuleState.minimumWithdrawAmount > 0 && amountUsd < walletRuleState.minimumWithdrawAmount) {
            failValidation(`Minimum withdrawal amount is ${formatUsd(walletRuleState.minimumWithdrawAmount)}${withdrawable.isUsd ? '' : ` (≈ ${formatWithdrawAssetAmount(walletRuleState.minimumWithdrawAmount / withdrawable.price, withdrawable.asset)})`}.`);
            return;
        }

        if (withdrawable.price > 0 && walletRuleState.maximumWithdrawAmount > 0 && amountUsd > walletRuleState.maximumWithdrawAmount) {
            failValidation(`Maximum withdrawal amount is ${formatUsd(walletRuleState.maximumWithdrawAmount)}${withdrawable.isUsd ? '' : ` (≈ ${formatWithdrawAssetAmount(walletRuleState.maximumWithdrawAmount / withdrawable.price, withdrawable.asset)})`}.`);
            return;
        }

        if (payload.amount > withdrawable.amount) {
            failValidation(`Insufficient ${withdrawable.asset} balance. Available to withdraw: ${formatWithdrawAssetAmount(withdrawable.amount, withdrawable.asset)}.`);
            return;
        }

        payload.account_uid = selectedWithdrawAccountUid();

        try {
            const data = await authFetch('/api/wallet/withdraw', {
                method: 'POST',
                headers: {
                    'Idempotency-Key': (window.crypto && typeof window.crypto.randomUUID === 'function')
                        ? window.crypto.randomUUID()
                        : `${Date.now()}-${Math.random().toString(36).slice(2)}`,
                },
                body: JSON.stringify(payload),
            });

            showWalletToast(data.message || 'Withdrawal request submitted. Admin approval pending.', 'success');
            const amountInput = form.querySelector('input[name="amount"]');
            if (amountInput) amountInput.value = '';
            updateWithdrawPreview();
            await loadWalletConfig();
            await loadWithdrawalHistory(1);
        } catch (error) {
            showWalletToast(error.message || 'Unable to submit withdrawal request.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalHtml;
            }
        }
    });
}function setupLedgerFilters() {
    const searchInput = document.getElementById('ledgerSearchInput');
    const accountFilter = document.getElementById('ledgerAccountFilter');
    const currencyFilter = document.getElementById('ledgerCurrencyFilter');
    const typeFilter = document.getElementById('ledgerTypeFilter');
    const directionFilter = document.getElementById('ledgerDirectionFilter');
    const startDateInput = document.getElementById('ledgerStartDate');
    const endDateInput = document.getElementById('ledgerEndDate');
    const exportCsvBtn = document.getElementById('exportLedgerCsvBtn');
    const exportPdfBtn = document.getElementById('exportLedgerPdfBtn');

    if (searchInput) {
        let searchTimer = null;

        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                ledgerFilterState.search = searchInput.value.trim();
                loadLedgerHistory(1);
            }, 250);
        });
    }

    if (accountFilter) {
        accountFilter.addEventListener('change', () => {
            ledgerFilterState.account_uid = accountFilter.value;
            loadLedgerHistory(1);
        });
    }

    if (currencyFilter) {
        currencyFilter.addEventListener('change', () => {
            ledgerFilterState.currency = currencyFilter.value;
            loadLedgerHistory(1);
        });
    }

    if (typeFilter) {
        typeFilter.addEventListener('change', () => {
            ledgerFilterState.type = typeFilter.value;
            loadLedgerHistory(1);
        });
    }

    if (directionFilter) {
        directionFilter.addEventListener('change', () => {
            ledgerFilterState.direction = directionFilter.value;
            loadLedgerHistory(1);
        });
    }

    if (startDateInput) {
        startDateInput.addEventListener('change', () => {
            ledgerFilterState.from_date = startDateInput.value;
            loadLedgerHistory(1);
        });
    }

    if (endDateInput) {
        endDateInput.addEventListener('change', () => {
            ledgerFilterState.to_date = endDateInput.value;
            loadLedgerHistory(1);
        });
    }

    if (exportCsvBtn) {
        exportCsvBtn.addEventListener('click', () => {
            const token = localStorage.getItem('kryvex_access_token');
            const params = new URLSearchParams({
                search: ledgerFilterState.search || '',
                type: ledgerFilterState.type || 'all',
                direction: ledgerFilterState.direction || 'all',
                currency: ledgerFilterState.currency || 'all',
                account_uid: ledgerFilterState.account_uid || 'all',
                from_date: ledgerFilterState.from_date || '',
                to_date: ledgerFilterState.to_date || '',
            });
            window.open(`/api/wallet/ledger/export/csv?${params.toString()}&token=${encodeURIComponent(token || '')}`, '_blank');
        });
    }

    if (exportPdfBtn) {
        exportPdfBtn.addEventListener('click', () => {
            const token = localStorage.getItem('kryvex_access_token');
            const params = new URLSearchParams({
                search: ledgerFilterState.search || '',
                type: ledgerFilterState.type || 'all',
                direction: ledgerFilterState.direction || 'all',
                currency: ledgerFilterState.currency || 'all',
                account_uid: ledgerFilterState.account_uid || 'all',
                from_date: ledgerFilterState.from_date || '',
                to_date: ledgerFilterState.to_date || '',
            });
            window.open(`/api/wallet/ledger/export/pdf?${params.toString()}&token=${encodeURIComponent(token || '')}`, '_blank');
        });
    }
}

let activeLedgerSearchController = null;
let activeWithdrawalSearchController = null;
let currentLedgerPage = 1;
let currentWithdrawalPage = 1;

function loadCachedWalletData() {
    try {
        const cachedAccounts = localStorage.getItem('kryvex_trading_accounts_cache');
        if (cachedAccounts) {
            tradingAccountsState = JSON.parse(cachedAccounts);
            if (!activeTradingAccountUid && tradingAccountsState.length > 0) {
                const defAcc = tradingAccountsState.find(a => a.is_default) || tradingAccountsState[0];
                activeTradingAccountUid = defAcc.account_uid;
                localStorage.setItem('kryvex_active_account_uid', activeTradingAccountUid);
            }
            renderTradingAccountDropdown();
        }
    } catch (e) {}

    try {
        const cachedInr = localStorage.getItem('kryvex_inr_state_cache');
        if (cachedInr) {
            const parsed = JSON.parse(cachedInr);
            inrState.baseCurrency = parsed.baseCurrency || null;
            inrState.usdtInrRate = parsed.usdtInrRate || 0;
            inrState.adminBankDetails = parsed.adminBankDetails || null;
        }
    } catch (e) {}

    try {
        const cacheKey = 'kryvex_wallet_cache_' + (activeTradingAccountUid || 'default');
        const cachedWallet = localStorage.getItem(cacheKey) || localStorage.getItem('kryvex_wallet_cache');
        if (cachedWallet) {
            const payload = JSON.parse(cachedWallet);
            if (payload) {
                applyWalletConfigPayload(payload, true);
            }
        }
    } catch (e) {}

    try {
        const cacheKey = 'kryvex_ledger_cache_' + (activeTradingAccountUid || 'default');
        const cachedLedger = localStorage.getItem(cacheKey) || localStorage.getItem('kryvex_ledger_cache');
        if (cachedLedger) {
            const payload = JSON.parse(cachedLedger);
            if (payload && payload.ledger) {
                renderLedgerTypes(payload.ledger_types || []);
                renderLedger(payload.ledger, payload.pagination);
            }
        }
    } catch (e) {}

    try {
        const cachedWithdrawals = localStorage.getItem('kryvex_withdrawals_cache');
        if (cachedWithdrawals) {
            const payload = JSON.parse(cachedWithdrawals);
            if (payload && payload.withdrawal_requests) {
                renderWithdrawalRequests(payload.withdrawal_requests, payload.pagination);
            }
        }
    } catch (e) {}
}

function applyWalletConfigPayload(payload, isFromCache = false) {
    const wallet = payload.wallet || {};

    walletDataState.depositNetworks = normalizeNetworks(payload.deposit_networks || []);
    try {
        localStorage.setItem('kryvex_deposit_networks_cache', JSON.stringify(payload.deposit_networks || []));
    } catch(e) {}
    walletDataState.company_deposit_wallets = payload.company_deposit_wallets || {};
    walletDataState.withdrawalAddresses = payload.withdrawal_addresses || {};
    walletDataState.internal_addresses = wallet.internal_addresses || {};
    walletDataState.wallet = wallet;
    window.walletDataState = walletDataState;
    window.dispatchEvent(new CustomEvent('kryvex_wallet_config_updated', { detail: payload }));
    renderNetworkSelects();
    renderDepositAddressPanel();
    renderWithdrawAddressPanel();

    walletRuleState.minimumReserve = Number(payload.minimum_reserve || 0);
    walletRuleState.hasOpenTrades = Boolean(payload.has_open_trades);
    walletRuleState.maxWithdrawable = Number(payload.max_withdrawable || 0);
    walletRuleState.withdrawableCrypto = payload.withdrawable_crypto_balances || {};
    walletRuleState.withdrawableInr = Number(payload.withdrawable_inr_balance || 0);
    walletRuleState.withdrawableByAccount = payload.withdrawable_by_account || {};
    walletRuleState.withdrawAssetPrices = payload.withdraw_asset_usd_prices || walletRuleState.withdrawAssetPrices || {};
    walletRuleState.withdrawAssetNetworks = payload.withdraw_asset_networks || walletRuleState.withdrawAssetNetworks || {};
    walletRuleState.withdrawalEnabled = payload.withdrawal_enabled !== false;
    walletRuleState.minimumWithdrawAmount = Number(payload.withdrawal_limits?.minimum_withdraw_amount || 0);
    walletRuleState.maximumWithdrawAmount = Number(payload.withdrawal_limits?.maximum_withdraw_amount || 0);
    walletRuleState.withdrawalFeeType = payload.withdrawal_charges?.withdrawal_fee_type || 'percentage';
    walletRuleState.withdrawalFeeValue = Number(payload.withdrawal_charges?.withdrawal_fee_value || 0);
    walletRuleState.platformChargeType = payload.withdrawal_charges?.platform_charge_type || 'fixed';
    walletRuleState.platformChargeValue = Number(payload.withdrawal_charges?.platform_charge_value || 0);
    if (payload.withdrawal_charges) {
        walletDataState.withdrawal_charges = payload.withdrawal_charges;
    }
    if (typeof window.updateWithdrawCalculation === 'function') {
        window.updateWithdrawCalculation();
    }
    window.renderWithdrawFromAccounts?.();
    window.renderDepositToAccounts?.();
    const isLive = isCurrentAccountLive();
    if (isLive) {
        walletRuleState.demoMainBalance = Number(wallet.real_main_balance || 0);
        walletRuleState.demoMainBalanceInr = Number(wallet.real_main_balance_inr || 0);
        walletRuleState.demoAvailableBalance = Number(wallet.real_available_balance || 0);
        walletRuleState.demoAvailableBalanceInr = Number(wallet.real_available_balance_inr || 0);
        walletRuleState.demoLockedBalance = Number(wallet.real_locked_balance || 0);
        walletRuleState.demoLockedBalanceInr = Number(wallet.real_locked_balance_inr || 0);
    } else {
        walletRuleState.demoMainBalance = Number(wallet.demo_main_balance || 0);
        walletRuleState.demoMainBalanceInr = Number(wallet.demo_main_balance_inr || 0);
        walletRuleState.demoAvailableBalance = Number(wallet.demo_available_balance || 0);
        walletRuleState.demoAvailableBalanceInr = Number(wallet.demo_available_balance_inr || 0);
        walletRuleState.demoLockedBalance = Number(wallet.demo_locked_balance || 0);
        walletRuleState.demoLockedBalanceInr = Number(wallet.demo_locked_balance_inr || 0);
    }

    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : 'USDT';
    const activeAcc = tradingAccountsState.find(a => a.account_uid === activeTradingAccountUid);
    const accName = activeAcc ? activeAcc.account_name : 'Main Account';
    const rate = inrState.usdtInrRate || 1;
    const modeBadge = isLive ? 'Live' : 'Demo';

    if (activeCur === 'INR') {
        setText('mainWalletTitle', `Main Wallet (INR - ${modeBadge})`);
        setText('demoMainBalance', formatInr(walletRuleState.demoMainBalanceInr));

        setText('activeTradingTitle', `${accName} (INR Trading - ${modeBadge})`);
        setText('demoAvailable', formatUsd(walletRuleState.demoAvailableBalanceInr));
        setText('tradingAvailableLine', formatUsd(walletRuleState.demoAvailableBalanceInr));
        setText('demoLocked', formatUsd(walletRuleState.demoLockedBalanceInr));

        setText('tooltipAvailableVal', formatInr(walletRuleState.demoAvailableBalanceInr));
        setText('tooltipLockedVal', formatInr(walletRuleState.demoLockedBalanceInr));
    } else {
        setText('mainWalletTitle', `Main Wallet (USD - ${modeBadge})`);
        setText('demoMainBalance', formatUsd(walletRuleState.demoMainBalance));

        setText('activeTradingTitle', `${accName} (USD Trading - ${modeBadge})`);
        setText('demoAvailable', formatUsd(walletRuleState.demoAvailableBalance));
        setText('tradingAvailableLine', formatUsd(walletRuleState.demoAvailableBalance));
        setText('demoLocked', formatUsd(walletRuleState.demoLockedBalance));

        setText('tooltipAvailableVal', formatUsd(walletRuleState.demoAvailableBalance));
        setText('tooltipLockedVal', formatUsd(walletRuleState.demoLockedBalance));
    }

    setText('minimumReserve', activeCur === 'INR' ? formatInr(walletRuleState.minimumReserve * rate) : formatUsd(walletRuleState.minimumReserve));
    setText('maxWithdrawAmount', activeCur === 'INR' ? formatInr(walletRuleState.maxWithdrawable * rate) : formatUsd(walletRuleState.maxWithdrawable));

    const withdrawAvailEl = document.getElementById('withdrawMainAvailBalanceText');
    const withdrawAssetPillEl = document.getElementById('withdrawAssetPill');
    if (withdrawAvailEl) {
        withdrawAvailEl.textContent = activeCur === 'INR'
            ? formatInr(walletRuleState.maxWithdrawable * rate)
            : formatUsd(walletRuleState.maxWithdrawable);
    }
    if (withdrawAssetPillEl) {
        if (activeCur === 'INR') {
            withdrawAssetPillEl.textContent = 'Indian Rupee (INR)';
        } else if (!withdrawAssetPillEl.textContent || withdrawAssetPillEl.textContent.includes('INR') || withdrawAssetPillEl.textContent === 'USD' || withdrawAssetPillEl.textContent === 'USDT') {
            withdrawAssetPillEl.textContent = 'Tether (USDT)';
        }
    }

    // Separate Crypto Wallet Row Balances Update
    const realCrypto = wallet.real_crypto_balances || {};
    const demoCrypto = wallet.demo_crypto_balances || wallet.crypto_balances || {};
    const cryptoBalances = isLive ? realCrypto : demoCrypto;

    const realCryptoPending = wallet.real_crypto_pending_balances || {};
    const demoCryptoPending = wallet.demo_crypto_pending_balances || wallet.crypto_pending_balances || {};
    const cryptoPendingBalances = isLive ? realCryptoPending : demoCryptoPending;

    const btcBal = Number(cryptoBalances.BTC || 0);
    const ethBal = Number(cryptoBalances.ETH || 0);
    const usdcBal = Number(cryptoBalances.USDC || 0);
    const usdtBal = Number(cryptoBalances.USDT !== undefined ? cryptoBalances.USDT : (isLive ? (wallet.real_main_balance || 0) : (wallet.demo_main_balance || 0)));
    const solBal = Number(cryptoBalances.SOL || 0);
    const xrpBal = Number(cryptoBalances.XRP || 0);
    const adaBal = Number(cryptoBalances.ADA || 0);
    const bnbBal = Number(cryptoBalances.BNB || 0);

    const realUsd = Number(wallet.real_main_balance || 0);
    const realUsdt = Number(realCrypto.USDT !== undefined ? realCrypto.USDT : realUsd);

    // Save latest wallet balances for Convert modal and Swap (ALWAYS REAL FOR CRYPTO & EXCHANGE)
    window.latestWalletBalances = {
        is_live: isLive,
        usd: isLive ? realUsd : Number(wallet.demo_main_balance || 0),
        usdt: isLive ? realUsdt : Number(demoCrypto.USDT !== undefined ? demoCrypto.USDT : (wallet.demo_main_balance || 0)),
        real_main_balance: realUsd,
        real_main_balance_inr: Number(wallet.real_main_balance_inr || 0),
        demo_main_balance: Number(wallet.demo_main_balance || 0),
        demo_main_balance_inr: Number(wallet.demo_main_balance_inr || 0),
        real_crypto: {
            BTC: Number(realCrypto.BTC || 0),
            ETH: Number(realCrypto.ETH || 0),
            SOL: Number(realCrypto.SOL || 0),
            USDT: realUsdt,
            USDC: Number(realCrypto.USDC || 0),
            XRP: Number(realCrypto.XRP || 0),
            ADA: Number(realCrypto.ADA || 0),
            BNB: Number(realCrypto.BNB || 0),
            ...realCrypto
        },
        demo_crypto: {
            BTC: Number(demoCrypto.BTC || 0),
            ETH: Number(demoCrypto.ETH || 0),
            SOL: Number(demoCrypto.SOL || 0),
            USDT: Number(demoCrypto.USDT !== undefined ? demoCrypto.USDT : (wallet.demo_main_balance || 0)),
            USDC: Number(demoCrypto.USDC || 0),
            XRP: Number(demoCrypto.XRP || 0),
            ADA: Number(demoCrypto.ADA || 0),
            BNB: Number(demoCrypto.BNB || 0),
            ...demoCrypto
        },
        crypto: {
            BTC: btcBal,
            ETH: ethBal,
            SOL: solBal,
            USDT: usdtBal,
            USDC: usdcBal,
            XRP: xrpBal,
            ADA: adaBal,
            BNB: bnbBal,
            ...cryptoBalances
        }
    };
    window.kryvexRealWallet = wallet;

    const formatCryptoAmount = (v) => {
        const n = Math.max(0, Math.round(Number(v || 0) * 100000000) / 100000000);
        if (n === 0) return '0';
        if (n < 0.0001) return n.toFixed(8);
        return parseFloat(n.toFixed(8)).toString();
    };

    const updateRowBal = (selector, asset) => {
        const row = document.querySelector(selector);
        if (row) {
            const cells = row.querySelectorAll('td');
            let val = Number(cryptoBalances[asset] || 0);
            if (asset === 'USDT' && !cryptoBalances[asset]) {
                val = isLive ? Number(wallet.real_main_balance || 0) : Number(wallet.demo_main_balance || 0);
            }
            const pend = Number(cryptoPendingBalances[asset] || 0);

            row.setAttribute('data-asset-balance', String(val));

            if (cells[1]) {
                const formattedVal = formatCryptoAmount(val);
                cells[1].innerHTML = `<strong id="${asset.toLowerCase()}BalanceDisplay" data-sensitive-balance>${window.isWalletBalanceHidden ? '••••••••' : escapeHtml(formattedVal)}</strong>`;
                const strong = cells[1].querySelector('strong');
                if (strong) strong.dataset.realValue = formattedVal;
            }
            if (cells[2]) {
                const formattedPend = formatCryptoAmount(pend);
                cells[2].innerHTML = `<span id="pending${asset.charAt(0).toUpperCase() + asset.slice(1).toLowerCase()}WithdrawalBalance" data-sensitive-balance>${window.isWalletBalanceHidden ? '••••••••' : escapeHtml(formattedPend)}</span>`;
                const span = cells[2].querySelector('span');
                if (span) span.dataset.realValue = formattedPend;
            }
        }
    };

    updateRowBal('[data-asset-name*="btc"]', 'BTC');
    updateRowBal('[data-asset-name*="eth"]', 'ETH');
    updateRowBal('[data-asset-name*="usdc"]', 'USDC');
    updateRowBal('[data-asset-name*="usdt"]', 'USDT');
    updateRowBal('[data-asset-name*="bnb"]', 'BNB');
    updateRowBal('[data-asset-name*="sol"]', 'SOL');
    updateRowBal('[data-asset-name*="xrp"]', 'XRP');
    updateRowBal('[data-asset-name*="ada"]', 'ADA');

    const inrRow = document.querySelector('[data-asset-name*="inr"]');
    if (inrRow) {
        const inrVal = Number(isLive ? (wallet.real_main_balance_inr || 0) : (wallet.demo_main_balance_inr || 0));
        const inrPending = Number(isLive ? (wallet.real_withdrawal_locked_balance_inr || 0) : (wallet.demo_withdrawal_locked_balance_inr || 0));
        inrRow.setAttribute('data-asset-balance', String(inrVal));
        setText('inrBalanceDisplay', formatInr(inrVal));
        setText('pendingInrWithdrawalBalance', formatInr(inrPending));
    }
    const usdRow = document.querySelector('[data-asset-name*="usd"]');
    if (usdRow) {
        const usdVal = Number(isLive ? (wallet.real_main_balance || 0) : (wallet.demo_main_balance || 0));
        const usdPending = Number(isLive ? (wallet.real_withdrawal_locked_balance || 0) : (wallet.demo_withdrawal_locked_balance || 0));
        usdRow.setAttribute('data-asset-balance', String(usdVal));
        setText('usdBalanceDisplay', (isLive && isActiveCentAccount()) ? formatUsc(usdVal) : formatUsd(usdVal));
        setText('pendingWithdrawalBalance', (isLive && isActiveCentAccount()) ? formatUsc(usdPending) : formatUsd(usdPending));
    }

    if (window.triggerWalletTableFilter) {
        window.triggerWalletTableFilter();
    }

    if (window.applyBalanceMasking) {
        window.applyBalanceMasking();
    }

    updateRuleText();
    updateWithdrawPreview();
    renderTradingAccountsPageList();
    if (typeof window.initBuyExchangeView === 'function' && document.getElementById('viewSectionExchange')?.style?.display !== 'none') {
        window.initBuyExchangeView();
    }

    if (!isFromCache) {
        try {
            const cacheKey = 'kryvex_wallet_cache_' + (activeTradingAccountUid || 'default');
            localStorage.setItem(cacheKey, JSON.stringify(payload));
            localStorage.setItem('kryvex_wallet_cache', JSON.stringify(payload));
        } catch (e) {}
    }
}

function renderTradingAccountsPageList() {
    const formatUsd = (value) => `$${formatNum(value)}`;
    const container = document.getElementById('tradingAccountsPageList');
    const splitMain = document.getElementById('splitMainWalletBalance');
    const splitActive = document.getElementById('splitActiveTradingBalance');

    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_currency') || 'USDT');
    const isINR = String(activeCur).toUpperCase() === 'INR';
    const rate = inrState.usdtInrRate || 1;

    // Determine initial filter if not set
    if (!tradingAccountsEnvFilter) {
        const activeAcc = (tradingAccountsState || []).find(a => a.account_uid === activeTradingAccountUid);
        if (activeAcc) {
            tradingAccountsEnvFilter = isDemoTradingAccount(activeAcc) ? 'demo' : 'live';
        } else {
            tradingAccountsEnvFilter = 'live';
        }
    }

    const liveAccounts = (tradingAccountsState || []).filter(a => !isDemoTradingAccount(a));
    const demoAccounts = (tradingAccountsState || []).filter(a => isDemoTradingAccount(a));

    const liveCountBadge = document.getElementById('liveAccountsCountLabel');
    const demoCountBadge = document.getElementById('demoAccountsCountLabel');
    if (liveCountBadge) liveCountBadge.textContent = liveAccounts.length;
    if (demoCountBadge) demoCountBadge.textContent = demoAccounts.length;

    const isLiveFilter = tradingAccountsEnvFilter !== 'demo';
    const targetAccounts = isLiveFilter ? liveAccounts : demoAccounts;

    const btnLive = document.getElementById('tabTradingEnvLive');
    const btnDemo = document.getElementById('tabTradingEnvDemo');
    if (btnLive && btnDemo) {
        if (isLiveFilter) {
            btnLive.classList.add('is-active');
            btnDemo.classList.remove('is-active');
        } else {
            btnDemo.classList.add('is-active');
            btnLive.classList.remove('is-active');
        }
    }

    const titleEl = document.getElementById('tradingListEnvTitle');
    const subEl = document.getElementById('tradingListEnvSub');
    if (titleEl) titleEl.textContent = isLiveFilter ? 'Live Trading Accounts' : 'Demo Trading Accounts';
    if (subEl) subEl.textContent = isLiveFilter 
        ? 'Real accounts with actual funds. Trade with real market conditions.' 
        : 'Virtual accounts with simulated funds. Practice trading risk-free.';

    // Calculate Summary Stats from Real Account Data
    let totalBalUsd = 0;
    let totalBalInr = 0;
    let totalPnlUsd = 0;
    let totalPnlInr = 0;
    let totalMarginUsd = 0;
    let totalMarginInr = 0;
    let totalMainUsd = 0;
    let totalMainInr = 0;

    targetAccounts.forEach(acc => {
        const isDemo = isDemoTradingAccount(acc);
        const balUsd = Number(acc.balance ?? (isDemo ? acc.demo_available_balance : acc.real_available_balance) ?? 0);
        const balInr = Number(acc.balance_inr ?? (isDemo ? acc.demo_available_balance_inr : acc.real_available_balance_inr) ?? (balUsd * rate));

        totalBalUsd += balUsd;
        totalBalInr += balInr;

        const pnlUsd = Number(acc.unrealized_pnl ?? 0);
        const pnlInr = Number(acc.unrealized_pnl_inr ?? (pnlUsd * rate));
        totalPnlUsd += pnlUsd;
        totalPnlInr += pnlInr;

        const lockedUsd = Number(acc.margin_used ?? (isDemo ? acc.demo_locked_balance : acc.real_locked_balance) ?? 0);
        const lockedInr = Number(acc.margin_used_inr ?? (isDemo ? acc.demo_locked_balance_inr : acc.real_locked_balance_inr) ?? (lockedUsd * rate));
        totalMarginUsd += lockedUsd;
        totalMarginInr += lockedInr;

        const mainUsd = Number((isDemo ? 0 : acc.real_main_balance) ?? 0);
        const mainInrRaw = Number((isDemo ? 0 : acc.real_main_balance_inr) || 0);
        totalMainUsd += mainUsd;
        totalMainInr += mainInrRaw > 0 ? mainInrRaw : mainUsd * rate;
    });

    const fmtFunds = (usd, inr) => isINR ? formatInr(inr) : formatUsd(usd);
    const setFundsText = (id, text) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.dataset.realValue = text;
        el.textContent = window.isWalletBalanceHidden ? '••••••••' : text;
    };
    setFundsText('tradingMainBalance', fmtFunds(totalMainUsd + totalBalUsd + totalMarginUsd, totalMainInr + totalBalInr + totalMarginInr));
    setFundsText('tradingTooltipMainVal', fmtFunds(totalMainUsd, totalMainInr));
    const tooltipMainRow = document.getElementById('tradingTooltipMainVal')?.closest('.tooltip-row');
    if (tooltipMainRow) tooltipMainRow.style.display = isLiveFilter ? '' : 'none';
    setFundsText('tradingTooltipTradingVal', fmtFunds(totalBalUsd, totalBalInr));
    setFundsText('tradingTooltipActiveVal', fmtFunds(totalMarginUsd, totalMarginInr));

    const statAccounts = document.getElementById('summaryTotalAccounts');
    const statBalance = document.getElementById('summaryTotalBalance');
    const statPnl = document.getElementById('summaryTotalPnl');
    const statMargin = document.getElementById('summaryTotalMargin');

    if (statAccounts) statAccounts.textContent = targetAccounts.length;
    if (statBalance) statBalance.textContent = isINR ? formatInr(totalBalInr) : formatUsd(totalBalUsd);
    if (statPnl) {
        const pnlVal = isINR ? totalPnlInr : totalPnlUsd;
        const formattedPnl = isINR ? formatInr(Math.abs(pnlVal)) : formatUsd(Math.abs(pnlVal));
        if (pnlVal >= 0) {
            statPnl.textContent = `+${formattedPnl}`;
            statPnl.style.color = '#10b981';
        } else {
            statPnl.textContent = `-${formattedPnl}`;
            statPnl.style.color = '#ef4444';
        }
    }
    if (statMargin) statMargin.textContent = isINR ? formatInr(totalMarginInr) : formatUsd(totalMarginUsd);

    // Sidebar split balances
    if (splitMain) {
        const isActDemo = isLiveFilter === false;
        const mainUsd = Number(isActDemo ? (walletRuleState.demoMainBalance || 0) : (walletRuleState.mainWalletBalance || (window.latestWalletBalances ? window.latestWalletBalances.real_main_balance : 0)));
        const mainInr = isActDemo 
            ? (walletRuleState.demoMainBalanceInr !== undefined && walletRuleState.demoMainBalanceInr > 0 ? Number(walletRuleState.demoMainBalanceInr) : (mainUsd * rate))
            : (walletRuleState.mainWalletBalanceInr !== undefined && walletRuleState.mainWalletBalanceInr > 0 ? Number(walletRuleState.mainWalletBalanceInr) : (mainUsd * rate));
        splitMain.textContent = isINR ? formatInr(mainInr) : ((!isActDemo && isActiveCentAccount()) ? formatUsc(mainUsd) : formatUsd(mainUsd));
    }
    if (splitActive) {
        const activeAcc = (tradingAccountsState || []).find(a => a.account_uid === activeTradingAccountUid);
        const isActDemo = activeAcc ? isDemoTradingAccount(activeAcc) : !isLiveFilter;
        const actBalUsd = Number(activeAcc ? (activeAcc.balance ?? (isActDemo ? activeAcc.demo_available_balance : activeAcc.real_available_balance) ?? 0) : 0);
        const actBalInr = Number(activeAcc ? (activeAcc.balance_inr ?? (isActDemo ? activeAcc.demo_available_balance_inr : activeAcc.real_available_balance_inr) ?? (actBalUsd * rate)) : 0);
        splitActive.textContent = isINR ? formatInr(actBalInr) : (isCentAccount(activeAcc) ? formatUsc(actBalUsd) : formatUsd(actBalUsd));
    }

    if (!container) return;

    // Search and Sort
    let displayAccounts = [...targetAccounts];
    const searchInput = document.getElementById('tradingAccountsSearchInput');
    const term = (searchInput ? searchInput.value.trim() : '').toLowerCase();
    if (term) {
        displayAccounts = displayAccounts.filter(acc => 
            String(acc.account_name || '').toLowerCase().includes(term) ||
            String(acc.account_uid || '').toLowerCase().includes(term)
        );
    }

    const sortSelect = document.getElementById('tradingAccountsSortSelect');
    const sortBy = sortSelect ? sortSelect.value : 'name';
    if (sortBy === 'name') {
        displayAccounts.sort((a, b) => String(a.account_name || '').localeCompare(String(b.account_name || '')));
    } else if (sortBy === 'balance_desc') {
        displayAccounts.sort((a, b) => Number(b.balance ?? 0) - Number(a.balance ?? 0));
    } else if (sortBy === 'balance_asc') {
        displayAccounts.sort((a, b) => Number(a.balance ?? 0) - Number(b.balance ?? 0));
    } else if (sortBy === 'date') {
        displayAccounts.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
    }

    const paginationInfo = document.getElementById('tradingPaginationInfo');
    if (paginationInfo) {
        paginationInfo.textContent = `Showing ${displayAccounts.length} of ${targetAccounts.length} accounts`;
    }

    if (displayAccounts.length === 0) {
        container.innerHTML = `<div style="color:rgba(255,255,255,0.45);padding:32px 16px;text-align:center;font-size:0.88rem;">No ${isLiveFilter ? 'live' : 'demo'} trading accounts found.</div>`;
        return;
    }

    const kryvexAccs = displayAccounts.filter(acc => (acc.broker_type || 'kryvex').toLowerCase() === 'kryvex');
    const mt5Accs = displayAccounts.filter(acc => (acc.broker_type || '').toLowerCase() === 'mt5');

    const renderSingleAccountRow = (acc) => {
        const isActive = acc.account_uid === activeTradingAccountUid;
        const isDefault = acc.is_default || acc.account_name === 'Main Account';
        const isDemo = isDemoTradingAccount(acc);
        const brokerType = (acc.broker_type || 'kryvex').toLowerCase();
        const isCent = isCentAccount(acc);

        const rawBalUsd = Number(acc.balance ?? (isDemo ? acc.demo_available_balance : acc.real_available_balance) ?? 0);
        const rawBalInr = Number(acc.balance_inr ?? (isDemo ? acc.demo_available_balance_inr : acc.real_available_balance_inr) ?? (rawBalUsd * rate));

        let formattedBal = isINR ? formatInr(rawBalInr) : formatUsd(rawBalUsd);
        let badgeText = 'Kryvex';
        let badgeBg = 'rgba(16,185,129,0.2)';
        let badgeColor = '#34d399';
        let badgeBorder = 'rgba(16,185,129,0.4)';

        if (brokerType === 'mt5') {
            if (isCent) {
                formattedBal = formatUsc(rawBalUsd);
                badgeText = 'MT5 Cent (USC)';
                badgeBg = 'rgba(168,85,247,0.2)';
                badgeColor = '#c084fc';
                badgeBorder = 'rgba(168,85,247,0.4)';
            } else {
                formattedBal = `$${formatNum(rawBalUsd)} USD`;
                const planStr = (acc.plan_key && acc.plan_key !== 'basic') ? acc.plan_key.toUpperCase() : 'STANDARD';
                badgeText = `MT5 ${planStr}`;
                badgeBg = 'rgba(59,130,246,0.2)';
                badgeColor = '#60a5fa';
                badgeBorder = 'rgba(59,130,246,0.4)';
            }
        }

        const mainUsd = Number((isDemo ? 0 : acc.real_main_balance) ?? 0);
        const mainInrRaw = Number((isDemo ? 0 : acc.real_main_balance_inr) || 0);
        const mainInr = mainInrRaw > 0 ? mainInrRaw : mainUsd * rate;
        let formattedMain = isINR ? formatInr(mainInr) : formatUsd(mainUsd);
        if (isCent) {
            formattedMain = formatUsc(mainUsd);
        } else if (brokerType === 'mt5') {
            formattedMain = `$${formatNum(mainUsd)} USD`;
        }

        // P&L
        const pnlUsd = Number(acc.unrealized_pnl ?? 0);
        const pnlInr = Number(acc.unrealized_pnl_inr ?? (pnlUsd * rate));
        const rawPnl = isINR ? pnlInr : pnlUsd;
        const pnlSign = rawPnl >= 0 ? '+' : '-';
        let formattedPnl = `${pnlSign}${isINR ? formatInr(Math.abs(rawPnl)) : formatUsd(Math.abs(rawPnl))}`;
        if (isCent) {
            formattedPnl = formatUsc(pnlUsd, { signed: true });
        }
        const pnlColor = rawPnl >= 0 ? '#10b981' : '#ef4444';

        // Equity
        const equityUsd = Number(acc.equity ?? Math.max(0, rawBalUsd + pnlUsd));
        const equityInr = Number(acc.equity_inr ?? Math.max(0, rawBalInr + pnlInr));
        let formattedEquity = isINR ? formatInr(equityInr) : formatUsd(equityUsd);
        if (isCent) {
            formattedEquity = formatUsc(equityUsd);
        } else if (brokerType === 'mt5') {
            formattedEquity = `$${formatNum(equityUsd)} USD`;
        }

        // Margin Used
        const marginUsd = Number(acc.margin_used ?? (isDemo ? acc.demo_locked_balance : acc.real_locked_balance) ?? 0);
        const marginInr = Number(acc.margin_used_inr ?? (isDemo ? acc.demo_locked_balance_inr : acc.real_locked_balance_inr) ?? (marginUsd * rate));
        let formattedMargin = isINR ? formatInr(marginInr) : formatUsd(marginUsd);
        if (isCent) {
            formattedMargin = formatUsc(marginUsd);
        } else if (brokerType === 'mt5') {
            formattedMargin = `$${formatNum(marginUsd)} USD`;
        }

        const totalAccountFunds = rawBalUsd + marginUsd;
        const marginPct = totalAccountFunds > 0 ? Math.min(100, Math.round((marginUsd / totalAccountFunds) * 1000) / 10) : 0.0;

        const initial = (acc.account_name || 'T').trim().charAt(0).toUpperCase();

        let dateStr = '';
        if (acc.created_at) {
            try {
                dateStr = 'Created ' + new Date(acc.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
            } catch (e) {}
        }
        const modeSubtext = dateStr || 'Active';

        return `
            <div class="dharma-account-row ${isActive ? 'is-active' : ''}" data-account-uid="${escapeHtml(acc.account_uid)}">
                <div style="display:flex;align-items:center;gap:14px;min-width:0;">
                    <div class="dharma-acc-avatar">${escapeHtml(initial)}</div>
                    <div class="dharma-acc-info">
                        <div class="dharma-acc-title-row">
                            ${isDefault ? `
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5">
                                    <polygon points="12 2 15 9 22 9 17 14 19 21 12 17 5 21 7 14 2 9 9 9 12 2"></polygon>
                                </svg>
                            ` : `
                                <span style="width:7px;height:7px;border-radius:50%;background:#10b981;box-shadow:0 0 6px rgba(16,185,129,0.5);"></span>
                            `}
                            <strong class="dharma-acc-name">${escapeHtml(acc.account_name)}</strong>
                            <span class="dharma-acc-badge-default" style="background:${badgeBg};border-color:${badgeBorder};color:${badgeColor};font-weight:700;">${escapeHtml(badgeText)}</span>
                            ${isDefault ? `<span class="dharma-acc-badge-default">Default</span>` : ''}
                            ${isActive ? `<span class="dharma-acc-badge-default" style="background:rgba(16,185,129,0.2);border-color:rgba(16,185,129,0.4);color:#34d399;">Active</span>` : ''}
                        </div>
                        <div class="dharma-acc-subtext">${escapeHtml(modeSubtext)}</div>
                    </div>
                </div>

                <div class="dharma-acc-metrics ${isDemo ? 'is-demo' : ''}">
                    ${isDemo ? '' : `
                    <div class="dharma-acc-metric-col is-main">
                        <span class="dharma-acc-metric-label">Main Wallet</span>
                        <strong class="dharma-acc-metric-val" data-sensitive-balance>${formattedMain}</strong>
                    </div>`}
                    <div class="dharma-acc-metric-col">
                        <span class="dharma-acc-metric-label">Trading</span>
                        <strong class="dharma-acc-metric-val" data-sensitive-balance>${formattedBal}</strong>
                    </div>
                    <div class="dharma-acc-metric-col">
                        <span class="dharma-acc-metric-label">Equity</span>
                        <strong class="dharma-acc-metric-val" data-sensitive-balance>${formattedEquity}</strong>
                    </div>
                    <div class="dharma-acc-metric-col">
                        <span class="dharma-acc-metric-label">P&L</span>
                        <strong class="dharma-acc-metric-val" style="color: ${pnlColor};" data-sensitive-balance>${formattedPnl}</strong>
                    </div>
                    <div class="dharma-acc-metric-col">
                        <span class="dharma-acc-metric-label">Margin Used</span>
                        <div style="display:flex;align-items:baseline;gap:5px;min-width:0;">
                            <strong class="dharma-acc-metric-val" data-sensitive-balance>${formattedMargin}</strong>
                            <span style="font-size:0.72rem;color:#94a3b8;font-weight:600;">${marginPct}%</span>
                        </div>
                        <div class="dharma-margin-track">
                            <div class="dharma-margin-fill" style="width:${Math.min(100, Math.max(marginPct, marginPct > 0 ? 5 : 0))}%;"></div>
                        </div>
                    </div>
                </div>

                <div class="dharma-acc-actions" style="display:flex;align-items:center;gap:8px;">
                    <button type="button" class="dharma-btn-account-transfer" data-action-account-transfer="${escapeHtml(acc.account_uid)}">Transfer</button>
                    <button type="button" class="dharma-btn-trade" data-action-trade="${escapeHtml(acc.account_uid)}">Trade</button>
                </div>
            </div>
        `;
    };

    let html = '';
    if (kryvexAccs.length > 0) {
        html += `<div style="margin-bottom:8px;font-size:12px;font-weight:700;color:#10b981;text-transform:uppercase;letter-spacing:0.5px;">Kryvex Exchange Accounts</div>`;
        html += kryvexAccs.map(renderSingleAccountRow).join('');
    }
    if (mt5Accs.length > 0) {
        html += `<div style="margin-top:20px;margin-bottom:8px;font-size:12px;font-weight:700;color:#a855f7;text-transform:uppercase;letter-spacing:0.5px;">MetaTrader 5 Accounts</div>`;
        html += mt5Accs.map(renderSingleAccountRow).join('');
    }

    container.innerHTML = html;

    // A click switches the account and re-renders the list, which breaks native dblclick; detect it on the container instead.
    if (!container.dataset.detailsBound) {
        container.dataset.detailsBound = '1';
        let lastClick = { uid: null, time: 0 };
        container.addEventListener('click', (e) => {
            const row = e.target.closest('.dharma-account-row');
            if (!row || e.target.closest('.dharma-acc-actions')) return;
            const uid = row.dataset.accountUid;
            const now = Date.now();
            if (uid && lastClick.uid === uid && now - lastClick.time < 400) {
                lastClick = { uid: null, time: 0 };
                openAccountDetailsModal(uid);
                return;
            }
            lastClick = { uid, time: now };
            if (uid && uid !== activeTradingAccountUid) {
                switchTradingAccount(uid);
            }
        });
    }

    container.querySelectorAll('[data-action-account-transfer]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const uid = btn.dataset.actionAccountTransfer;
            if (uid) {
                openAccountTransferModal(uid);
            }
        });
    });

    container.querySelectorAll('[data-action-trade]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const uid = btn.dataset.actionTrade;
            if (uid && uid !== activeTradingAccountUid) {
                switchTradingAccount(uid);
            }
            window.location.href = '/futures';
        });
    });
}
window.renderTradingAccountsPageList = renderTradingAccountsPageList;

function openAccountDetailsModal(accountUid) {
    const acc = (tradingAccountsState || []).find(a => a.account_uid === accountUid);
    if (!acc) return;

    document.getElementById('accountDetailsModal')?.remove();

    const isDemo = isDemoTradingAccount(acc);
    const isCent = isCentAccount(acc);
    const broker = String(acc.broker_type || 'kryvex').toLowerCase();
    const rate = inrState.usdtInrRate || 1;

    const mainUsd = Number((isDemo ? 0 : acc.real_main_balance) ?? 0);
    const balUsd = Number(acc.balance ?? (isDemo ? acc.demo_available_balance : acc.real_available_balance) ?? 0);
    const marginUsd = Number(acc.margin_used ?? (isDemo ? acc.demo_locked_balance : acc.real_locked_balance) ?? 0);
    const pnlUsd = Number(acc.unrealized_pnl ?? 0);
    const equityUsd = Number(acc.equity ?? Math.max(0, balUsd + marginUsd + pnlUsd));
    const fundsUsd = mainUsd + balUsd + marginUsd;
    const freePct = (balUsd + marginUsd) > 0 ? (balUsd / (balUsd + marginUsd)) * 100 : 100;

    const primary = (usd, signed = false) => {
        if (isCent) return formatUsc(usd, { signed });
        const sign = signed ? (usd >= 0 ? '+' : '-') : (usd < 0 ? '-' : '');
        return `${sign}${formatNum(Math.abs(usd))} ${broker === 'mt5' ? 'USD' : 'USDT'}`;
    };
    const secondary = (usd) => isCent ? `≈ $${formatNum(usd)} USD` : `≈ ${formatInr(usd * rate)}`;

    const platform = broker === 'mt5'
        ? (isCent ? 'MetaTrader 5 · Cent' : `MetaTrader 5 · ${(acc.plan_key && acc.plan_key !== 'basic') ? String(acc.plan_key).toUpperCase() : 'Standard'}`)
        : 'Kryvex Exchange';
    const accountId = `${isDemo ? 'D' : 'L'}${acc.account_number || ''}`;
    let created = '--';
    if (acc.created_at) {
        try { created = new Date(acc.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }); } catch (e) {}
    }

    const moneyRow = (label, usd, opts = {}) => `
        <div class="acd-row">
            <span class="acd-label">${label}</span>
            <span class="acd-value">
                <strong style="${opts.color ? `color:${opts.color};` : ''}">${primary(usd, opts.signed)}</strong>
                <small>${secondary(usd)}</small>
            </span>
        </div>`;
    const textRow = (label, value) => `
        <div class="acd-row">
            <span class="acd-label">${label}</span>
            <span class="acd-value"><strong>${value}</strong></span>
        </div>`;

    const copyRow = (label, value) => `
        <div class="acd-row">
            <span class="acd-label">${label}</span>
            <span class="acd-value" style="flex-direction:row;align-items:center;">
                <strong>${escapeHtml(value)}</strong>
                <button type="button" class="acd-copy" data-acd-copy="${escapeHtml(value)}" title="Copy ${label}">⧉</button>
            </span>
        </div>`;
    const marginPctRow = `
        <div class="acd-row">
            <span class="acd-label">Available margin, %</span>
            <span class="acd-bar">
                <span class="acd-bar-track"><span class="acd-bar-fill" style="width:${Math.max(0, Math.min(100, freePct)).toFixed(2)}%;"></span></span>
                <strong style="font-size:0.86rem;">${freePct.toFixed(2)}%</strong>
            </span>
        </div>`;

    const isMt5 = broker === 'mt5';
    const planKey = String(acc.plan_key || 'standard').toLowerCase();
    const planLeverage = { cent: '1:1000', basic: '1:100', standard: '1:300', advanced: '1:500' };
    const leverage = acc.leverage || planLeverage[planKey] || '1:100';
    const accountTypeLabel = isCent ? 'Cent' : (planKey === 'basic' ? 'Standard' : planKey.charAt(0).toUpperCase() + planKey.slice(1));
    const loginId = String(acc.account_number || accountId);
    const mt5Server = acc.server || (isDemo ? 'Kryvex-Demo-01' : 'Kryvex-Live-01');

    const modal = document.createElement('div');
    modal.id = 'accountDetailsModal';
    modal.className = 'acd-backdrop';
    modal.innerHTML = `
        <style>
            .acd-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.65); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; z-index: 100000; padding: 16px; }
            .acd-card { width: 100%; max-width: 440px; max-height: calc(100vh - 32px); overflow-y: auto; background: #12141c; border: 1px solid rgba(255,255,255,0.08); border-radius: 16px; padding: 22px 22px 18px; box-shadow: 0 24px 60px rgba(0,0,0,0.55); color: #fff; }
            .acd-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
            .acd-head h3 { margin: 0; font-size: 1.08rem; font-weight: 800; }
            .acd-head .acd-sub { font-size: 0.74rem; color: #94a3b8; margin-top: 3px; }
            .acd-close { background: none; border: none; color: #94a3b8; font-size: 22px; line-height: 1; cursor: pointer; padding: 4px; }
            .acd-close:hover { color: #fff; }
            .acd-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid rgba(255,255,255,0.06); }
            .acd-label { font-size: 0.82rem; color: #cbd5e1; }
            .acd-value { display: flex; flex-direction: column; align-items: flex-end; text-align: right; }
            .acd-value strong { font-size: 0.86rem; font-weight: 700; font-variant-numeric: tabular-nums; }
            .acd-value small { font-size: 0.7rem; color: #64748b; margin-top: 1px; }
            .acd-bar { display: flex; align-items: center; gap: 10px; }
            .acd-bar-track { width: 90px; height: 5px; border-radius: 3px; background: rgba(255,255,255,0.1); overflow: hidden; }
            .acd-bar-fill { height: 100%; background: #10b981; border-radius: 3px; }
            .acd-copy { background: none; border: none; color: #94a3b8; cursor: pointer; padding: 0 0 0 6px; font-size: 0.8rem; }
            .acd-copy:hover { color: #a78bfa; }
            .acd-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 18px; }
            .acd-actions button { padding: 10px; border-radius: 9px; font-size: 0.84rem; font-weight: 700; cursor: pointer; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.06); color: #fff; transition: all 0.15s ease; }
            .acd-actions button:hover { background: rgba(255,255,255,0.12); }
            .acd-actions .acd-primary { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); border-color: transparent; }
            .acd-actions .acd-primary:hover { filter: brightness(1.1); }
            .acd-tabs { display: flex; gap: 22px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 6px; }
            .acd-tabs button { background: none; border: none; color: #94a3b8; font-size: 0.84rem; font-weight: 600; padding: 8px 0; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; }
            .acd-tabs button.is-active { color: #fff; border-bottom-color: #fff; }
            .acd-badge { font-size: 0.62rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; letter-spacing: 0.04em; }
            .acd-badge.is-demo { background: rgba(249,115,22,0.18); color: #fb923c; }
            .acd-badge.is-live { background: rgba(16,185,129,0.18); color: #34d399; }
            .acd-settings { margin-top: 14px; background: rgba(255,255,255,0.04); border-radius: 10px; padding: 4px 14px; }
            .acd-setting { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; font-size: 0.82rem; color: #e2e8f0; }
            .acd-setting + .acd-setting { border-top: 1px solid rgba(255,255,255,0.06); }
            .acd-setting strong { color: #94a3b8; font-weight: 600; }
            .acd-note { font-size: 0.74rem; color: #64748b; margin-top: 12px; line-height: 1.45; }
            .acd-wide-actions { display: flex; flex-direction: column; gap: 8px; margin-top: 18px; }
            .acd-wide-actions .acd-primary { padding: 11px; border-radius: 9px; font-size: 0.86rem; font-weight: 700; cursor: pointer; border: none; color: #fff; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
            .acd-wide-actions .acd-primary:hover { filter: brightness(1.1); }
        </style>
        <div class="acd-card" role="dialog" aria-modal="true">
            <div class="acd-head">
                <div>
                    <h3>${isMt5 ? 'MetaTrader 5 account details' : `${escapeHtml(acc.account_name || 'Trading Account')} details`}</h3>
                    <div class="acd-sub">${escapeHtml(acc.account_name || '')} · ${escapeHtml(platform)} · ${isDemo ? 'Demo' : 'Live'}</div>
                </div>
                <button type="button" class="acd-close" data-acd-close aria-label="Close">×</button>
            </div>
            ${isMt5 ? `
                <div class="acd-tabs">
                    <button type="button" data-acd-tab="mt5">MT5 details</button>
                    <button type="button" class="is-active" data-acd-tab="account">Account</button>
                </div>
                <div data-acd-panel="mt5" hidden>
                    ${copyRow('Login', loginId)}
                    ${copyRow('Server', mt5Server)}
                    ${textRow('Platform', 'MetaTrader 5')}
                    ${textRow('Account type', escapeHtml(accountTypeLabel))}
                    ${textRow('Leverage', escapeHtml(leverage))}
                    ${textRow('Currency', isCent ? 'USC' : 'USD')}
                    ${textRow('Created', escapeHtml(created))}
                    <div class="acd-note">Use your Login and Server to sign in to the MetaTrader 5 desktop or mobile app.</div>
                </div>
                <div data-acd-panel="account">
                    ${moneyRow('Equity', equityUsd)}
                    ${moneyRow('P/L', pnlUsd, { signed: true, color: pnlUsd >= 0 ? '#10b981' : '#ef4444' })}
                    ${moneyRow('Available margin', balUsd)}
                    ${marginPctRow}
                    ${moneyRow('Balance', balUsd + marginUsd)}
                    ${isDemo ? '' : moneyRow('Main Wallet', mainUsd)}
                    ${moneyRow('Used margin', marginUsd)}
                    ${textRow('Account type', escapeHtml(accountTypeLabel))}
                    <div class="acd-row">
                        <span class="acd-label">Account ID</span>
                        <span class="acd-value" style="flex-direction:row;align-items:center;gap:6px;">
                            <span class="acd-badge ${isDemo ? 'is-demo' : 'is-live'}">${isDemo ? 'DEMO' : 'LIVE'}</span>
                            <strong>#${escapeHtml(accountId)}</strong>
                            <button type="button" class="acd-copy" data-acd-copy="${escapeHtml(accountId)}" title="Copy">⧉</button>
                        </span>
                    </div>
                    <div class="acd-settings">
                        <div class="acd-setting"><span>⇅ Account leverage</span><strong>${escapeHtml(leverage)}</strong></div>
                        <div class="acd-setting"><span>⟳ Swap-free</span><strong>${acc.swap_free ? 'Enabled' : 'Disabled'}</strong></div>
                    </div>
                </div>
                <div class="acd-wide-actions">
                    <button type="button" class="acd-primary" data-acd-action="trade">Launch MT5 WebTrader</button>
                    <div class="acd-actions" style="margin-top:0;grid-template-columns:repeat(2,1fr);">
                        <button type="button" data-acd-action="deposit">Deposit</button>
                        <button type="button" data-acd-action="transfer">Transfer</button>
                    </div>
                </div>
            ` : `
                ${moneyRow('Equity', equityUsd)}
                ${moneyRow('P/L', pnlUsd, { signed: true, color: pnlUsd >= 0 ? '#10b981' : '#ef4444' })}
                ${moneyRow('Your funds', fundsUsd)}
                ${isDemo ? '' : moneyRow('Main Wallet', mainUsd)}
                ${moneyRow(isDemo ? 'Balance' : 'Trading Balance', balUsd)}
                ${moneyRow('Used margin', marginUsd)}
                ${marginPctRow}
                ${acc.leverage ? textRow('Leverage', escapeHtml(acc.leverage)) : ''}
                ${textRow('Currency', 'USDT')}
                ${copyRow('Account ID', accountId)}
                ${textRow('Created', escapeHtml(created))}
                <div class="acd-actions">
                    <button type="button" data-acd-action="deposit">Deposit</button>
                    <button type="button" data-acd-action="transfer">Transfer</button>
                    <button type="button" class="acd-primary" data-acd-action="trade">Trade</button>
                </div>
            `}
        </div>
    `;

    const close = () => {
        modal.remove();
        document.removeEventListener('keydown', onKey);
    };
    const onKey = (e) => { if (e.key === 'Escape') close(); };
    document.addEventListener('keydown', onKey);

    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target.closest('[data-acd-close]')) {
            close();
            return;
        }
        const copyBtn = e.target.closest('[data-acd-copy]');
        if (copyBtn) {
            copyText(copyBtn.dataset.acdCopy || accountId);
            showWalletToast('Copied to clipboard', 'success');
            return;
        }
        const tabBtn = e.target.closest('[data-acd-tab]');
        if (tabBtn) {
            const tab = tabBtn.dataset.acdTab;
            modal.querySelectorAll('[data-acd-tab]').forEach(b => b.classList.toggle('is-active', b === tabBtn));
            modal.querySelectorAll('[data-acd-panel]').forEach(p => { p.hidden = p.dataset.acdPanel !== tab; });
            return;
        }
        const action = e.target.closest('[data-acd-action]')?.dataset.acdAction;
        if (!action) return;
        close();
        if (action === 'transfer') {
            openAccountTransferModal(acc.account_uid);
        } else if (action === 'trade') {
            if (acc.account_uid !== activeTradingAccountUid) switchTradingAccount(acc.account_uid);
            window.location.href = '/futures';
        } else if (action === 'deposit') {
            if (acc.account_uid !== activeTradingAccountUid) switchTradingAccount(acc.account_uid);
            openWalletDepositModal('USDT');
        }
    });

    document.body.appendChild(modal);
}
window.openAccountDetailsModal = openAccountDetailsModal;

async function loadWallet() {
    return Promise.all([
        loadWalletConfig(),
        loadLedgerHistory(1),
        loadWithdrawalHistory(1),
        loadTradingAccounts()
    ]);
}
window.loadWallet = loadWallet;
window.loadWalletConfig = loadWalletConfig;
window.loadTradingAccounts = loadTradingAccounts;

async function loadWalletConfig() {
    try {
        let user = {};
        try {
            user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
        } catch (e) {}
        const broker = (user.broker_type || 'kryvex').toLowerCase();
        const data = await authFetch(`/api/wallet?broker_type=${broker}`, {
            headers: {
                'X-Broker-Type': broker
            }
        });
        const payload = data.data || {};
        applyWalletConfigPayload(payload, false);
    } catch (error) {
        showWalletToast(error.message || 'Unable to load wallet data.', 'error');
    }
}

async function loadLedgerHistory(page = 1) {
    currentLedgerPage = page;
    if (activeLedgerSearchController) {
        activeLedgerSearchController.abort();
    }
    activeLedgerSearchController = new AbortController();

    try {
        const body = document.getElementById('walletLedgerBody');
        const hasRows = body && body.children.length > 0 && !body.querySelector('.wallet-skeleton');
        const isDefaultQuery = page === 1 && !ledgerFilterState.search && ledgerFilterState.type === 'all' && ledgerFilterState.direction === 'all' && ledgerFilterState.currency === 'all' && ledgerFilterState.account_uid === 'all' && !ledgerFilterState.from_date && !ledgerFilterState.to_date;

        if (!hasRows) {
            showLedgerSkeleton();
        }

        let user = {};
        try {
            user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
        } catch (e) {}
        const broker = (user.broker_type || 'kryvex').toLowerCase();

        const params = new URLSearchParams({
            page: page,
            search: ledgerFilterState.search || '',
            type: ledgerFilterState.type || 'all',
            direction: ledgerFilterState.direction || 'all',
            currency: ledgerFilterState.currency || 'all',
            account_uid: ledgerFilterState.account_uid || 'all',
            from_date: ledgerFilterState.from_date || '',
            to_date: ledgerFilterState.to_date || '',
            broker_type: broker,
        });

        const data = await authFetch(`/api/wallet/ledger?${params.toString()}`, {
            signal: activeLedgerSearchController.signal
        });

        const payload = data.data || {};
        const ledger = payload.ledger || [];
        const ledgerTypes = payload.ledger_types || [];

        renderLedgerTypes(ledgerTypes);
        renderLedger(ledger, payload.pagination);

        if (isDefaultQuery) {
            try {
                const cacheKey = 'kryvex_ledger_cache_' + (activeTradingAccountUid || 'default');
                localStorage.setItem(cacheKey, JSON.stringify(payload));
                localStorage.setItem('kryvex_ledger_cache', JSON.stringify(payload));
            } catch (e) {}
        }
    } catch (error) {
        if (error.name === 'AbortError') return;
        showWalletToast(error.message || 'Unable to load ledger.', 'error');
        clearLedgerTable();
    } finally {
        activeLedgerSearchController = null;
    }
}
window.loadLedgerHistory = loadLedgerHistory;

async function loadWithdrawalHistory(page = 1) {
    currentWithdrawalPage = page;
    if (activeWithdrawalSearchController) {
        activeWithdrawalSearchController.abort();
    }
    activeWithdrawalSearchController = new AbortController();

    try {
        const list = document.getElementById('withdrawalRequestsList');
        const hasItems = list && list.children.length > 0 && !list.querySelector('.wallet-skeleton');

        if (!hasItems) {
            showWithdrawalSkeleton();
        }

        let user = {};
        try {
            user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
        } catch (e) {}
        const broker = (user.broker_type || 'kryvex').toLowerCase();

        const params = new URLSearchParams({
            page: page,
            broker_type: broker,
        });

        const data = await authFetch(`/api/wallet/withdrawals?${params.toString()}`, {
            signal: activeWithdrawalSearchController.signal
        });

        const payload = data.data || {};
        const withdrawals = payload.withdrawal_requests || [];

        renderWithdrawalRequests(withdrawals, payload.pagination);

        if (page === 1) {
            try {
                localStorage.setItem('kryvex_withdrawals_cache', JSON.stringify(payload));
            } catch (e) {}
        }
    } catch (error) {
        if (error.name === 'AbortError') return;
        showWalletToast(error.message || 'Unable to load withdrawals.', 'error');
        clearWithdrawalTable();
    } finally {
        activeWithdrawalSearchController = null;
    }
}
window.loadWithdrawalHistory = loadWithdrawalHistory;

const USD_STABLE_ASSETS = ['USDT', 'USDC'];

// Live account picked in the deposit / withdraw "account" dropdowns (null = server default Main Account).
function selectedWithdrawAccountUid() {
    return typeof window.getSelectedWithdrawAccountUid === 'function' ? window.getSelectedWithdrawAccountUid() : null;
}

function selectedDepositAccountUid() {
    return typeof window.getSelectedDepositAccountUid === 'function' ? window.getSelectedDepositAccountUid() : null;
}

window.getWithdrawableByAccount = () => walletRuleState.withdrawableByAccount || {};

function getSelectedAccountFunds() {
    const byAccount = walletRuleState.withdrawableByAccount || {};
    const uid = selectedWithdrawAccountUid();
    if (uid && byAccount[uid]) return byAccount[uid];
    return Object.values(byAccount).find(f => f.is_default) || null;
}

// Mirrors DemoWalletService::resolveWithdrawAsset().
function getCryptoWithdrawable() {
    const network = String(document.getElementById('withdrawNetworkSelect')?.value || 'TRC20').toUpperCase();
    const asset = String(document.getElementById('withdrawCurrencyInput')?.value || 'USDT').toUpperCase();
    const allowedNetworks = (walletRuleState.withdrawAssetNetworks || {})[asset];
    const supported = Array.isArray(allowedNetworks) && allowedNetworks.includes(network);
    const funds = getSelectedAccountFunds();
    const balances = funds ? funds.crypto : (walletRuleState.withdrawableCrypto || {});
    const amount = supported ? Number((balances || {})[asset] || 0) : 0;
    const price = USD_STABLE_ASSETS.includes(asset) ? 1 : Number((walletRuleState.withdrawAssetPrices || {})[asset] || 0);
    return { network, asset, amount, price, supported, isUsd: USD_STABLE_ASSETS.includes(asset) };
}
window.getCryptoWithdrawable = getCryptoWithdrawable;

// Selected live account's INR main wallet; reserve applies while positions are open.
function getInrWithdrawBalance() {
    const funds = getSelectedAccountFunds();
    return Number(funds ? funds.main_inr : (walletRuleState.withdrawableInr || 0));
}

function getInrWithdrawable(rate) {
    const balance = getInrWithdrawBalance();
    if (!walletRuleState.hasOpenTrades) return balance;
    const reserveInr = Number(walletRuleState.minimumReserve || 0) * (rate || inrState.usdtInrRate || 1);
    return Math.max(0, balance - reserveInr);
}
window.getInrWithdrawable = getInrWithdrawable;
window.getInrWithdrawBalance = getInrWithdrawBalance;

function formatWithdrawAssetAmount(value, asset) {
    const num = Number(value || 0);
    if (USD_STABLE_ASSETS.includes(asset)) {
        return `$${num.toFixed(2)}`;
    }
    return `${num.toFixed(8).replace(/\.?0+$/, '')} ${asset}`;
}
window.formatWithdrawAssetAmount = formatWithdrawAssetAmount;

function calculateCharge(amount, type, value) {
    amount = Number(amount || 0);
    value = Number(value || 0);

    if (type === 'percentage') {
        return roundMoney(amount * (value / 100));
    }

    return roundMoney(value);
}

function roundMoney(value) {
    return Math.round(Number(value || 0) * 10000) / 10000;
}

function updateWithdrawPreview() {
    const form = document.getElementById('withdrawForm');

    if (!form) {
        return;
    }

    if (typeof window.updateWithdrawCalculation === 'function') {
        window.updateWithdrawCalculation();
        return;
    }

    const amount = Number(form.querySelector('input[name="amount"]')?.value || 0);
    const withdrawalFee = calculateCharge(amount, walletRuleState.withdrawalFeeType, walletRuleState.withdrawalFeeValue);
    const platformCharge = calculateCharge(amount, walletRuleState.platformChargeType, walletRuleState.platformChargeValue);
    const totalCharge = roundMoney(withdrawalFee + platformCharge);
    const netAmount = Math.max(roundMoney(amount - totalCharge), 0);

    setText('withdrawPreviewAmount', amount > 0 ? formatUsd(amount) : '--');
    setText('withdrawPreviewFee', amount > 0 ? formatUsd(withdrawalFee) : '--');
    setText('withdrawPreviewPlatformCharge', amount > 0 ? formatUsd(platformCharge) : '--');
    setText('withdrawPreviewNet', amount > 0 ? formatUsd(netAmount) : '--');
}

function updateFaqDynamicText() {
    const feeEl = document.getElementById('faqWithdrawFeeText');
    const minEl = document.getElementById('faqMinWithdrawLimit');
    const maxEl = document.getElementById('faqMaxWithdrawLimit');

    const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_currency') || 'USDT');
    const isINR = String(activeCur).toUpperCase() === 'INR';
    const rate = inrState.usdtInrRate || 1;

    // 1. Withdrawal Fee & Platform Charge from admin config
    if (feeEl) {
        let parts = [];
        const feeVal = Number(walletRuleState.withdrawalFeeValue || 0);
        const feeType = walletRuleState.withdrawalFeeType || 'percentage';
        const platVal = Number(walletRuleState.platformChargeValue || 0);
        const platType = walletRuleState.platformChargeType || 'fixed';

        if (feeVal > 0) {
            if (feeType === 'percentage') {
                parts.push(`${feeVal}% withdrawal fee`);
            } else {
                const formattedFixedFee = isINR ? formatInr(feeVal * rate) : formatUsd(feeVal);
                parts.push(`${formattedFixedFee} fixed fee`);
            }
        }

        if (platVal > 0) {
            if (platType === 'percentage') {
                parts.push(`${platVal}% platform charge`);
            } else {
                const formattedFixedPlat = isINR ? formatInr(platVal * rate) : formatUsd(platVal);
                parts.push(`${formattedFixedPlat} platform charge`);
            }
        }

        if (parts.length > 0) {
            feeEl.textContent = parts.join(' + ');
        } else {
            feeEl.textContent = '0% platform fee';
        }
    }

    // 2. Minimum Limit from admin config
    if (minEl) {
        const minVal = Number(walletRuleState.minimumWithdrawAmount || 0);
        if (minVal > 0) {
            minEl.textContent = isINR ? formatInr(minVal * rate) : formatUsd(minVal);
        } else {
            minEl.textContent = isINR ? '₹100 (INR) or $10 (Crypto)' : '$10 (Crypto) or ₹100 (INR)';
        }
    }

    // 3. Maximum Limit from admin config
    if (maxEl) {
        const maxVal = Number(walletRuleState.maximumWithdrawAmount || 0);
        if (maxVal > 0) {
            maxEl.textContent = isINR ? `${formatInr(maxVal * rate)} / day` : `${formatUsd(maxVal)} / day`;
        } else {
            maxEl.textContent = isINR ? 'Up to ₹5,00,000 / day depending on KYC status' : 'Up to $50,000 / day depending on KYC status';
        }
    }
}

function updateRuleText() {
    updateFaqDynamicText();

    const reserveLabel = document.getElementById('withdrawReserveLabel');
    const ruleText = document.getElementById('withdrawRuleText');
    const reserveRuleText = document.getElementById('reserveRuleText');

    if (!walletRuleState.withdrawalEnabled) {
        setText('withdrawReserveLabel', 'Disabled by admin');
        setText('withdrawRuleText', 'Withdrawals are currently disabled by admin. You can still view wallet history.');
        setText('reserveRuleText', 'Withdrawals disabled');
        return;
    }

    if (walletRuleState.hasOpenTrades) {
        const message = `You have open trades. Minimum balance ${formatUsd(walletRuleState.minimumReserve)} must remain available. Maximum withdrawal now is ${formatUsd(walletRuleState.maxWithdrawable)}.`;
        if (reserveLabel) reserveLabel.textContent = `${formatUsd(walletRuleState.minimumReserve)} reserve while trades are open`;
        if (ruleText) ruleText.textContent = message;
        if (reserveRuleText) reserveRuleText.textContent = 'Active because trades are open';
        return;
    }

    if (reserveLabel) reserveLabel.textContent = 'Full available balance allowed';
    if (ruleText) ruleText.textContent = `No open trades found. You can request withdrawal up to ${formatUsd(walletRuleState.maxWithdrawable)}.`;
    if (reserveRuleText) reserveRuleText.textContent = 'Not applied when all trades are closed';
}

function renderLedgerTypes(types) {
    const typeFilter = document.getElementById('ledgerTypeFilter');

    if (!typeFilter) {
        return;
    }

    const categories = [
        { value: 'all', label: 'All Types' },
        { value: 'deposit', label: 'Deposit' },
        { value: 'withdraw', label: 'Withdraw' },
        { value: 'exchange', label: 'Exchange' },
        { value: 'trading', label: 'Trading' },
        { value: 'transfer', label: 'Transfer' }
    ];

    const currentValue = typeFilter.value || 'all';

    typeFilter.innerHTML = categories.map((cat) => `<option value="${cat.value}">${cat.label}</option>`).join('');

    typeFilter.value = currentValue;
}



function renderWithdrawalRequests(requests, pagination) {
    const list = document.getElementById('withdrawalRequestsList');

    if (!list) {
        return;
    }

    if (!requests.length) {
        list.innerHTML = '<div class="wallet-empty" style="padding: 24px; text-align: center; color: rgba(255,255,255,0.4);">No withdrawal requests found.</div>';
        renderPagination('withdrawalPagination', null);
        return;
    }

    list.innerHTML = requests.map((request) => {
        const statusRaw = String(request.status || 'pending').toLowerCase();
        const isINR = request.currency === 'INR' || request.wallet_currency === 'INR' || inrState.baseCurrency === 'INR';
        const netCode = (request.network || request.currency || (isINR ? 'INR' : 'USD')).toUpperCase();
        const isCrypto = ['BTC', 'ETH', 'SOL', 'USDT', 'USDC', 'XRP', 'ADA', 'BNB'].includes(netCode);
        const rate = inrState.usdtInrRate || 1;

        let displayAmt = '';
        let displayCharges = '';
        let displayNet = '';

        if (isCrypto) {
            const usdtCharges = Number(request.withdraw_fee || 0) + Number(request.platform_charge || 0);
            displayAmt = `${request.amount} ${netCode}`;
            displayCharges = `${usdtCharges} ${netCode}`;
            displayNet = `${request.net_amount || (request.amount - usdtCharges)} ${netCode}`;
        } else if (isINR) {
            const inrAmt = Number(request.amount_inr || (request.amount * rate));
            const inrCharges = Number(request.amount_inr_charge !== undefined ? request.amount_inr_charge : ((Number(request.withdraw_fee || 0) + Number(request.platform_charge || 0)) * rate));
            const inrNet = Number(request.amount_inr_net !== undefined ? request.amount_inr_net : (inrAmt - inrCharges));
            displayAmt = formatInr(inrAmt);
            displayCharges = formatInr(inrCharges);
            displayNet = formatInr(inrNet);
        } else {
            const usdtCharges = Number(request.withdraw_fee || 0) + Number(request.platform_charge || 0);
            displayAmt = formatUsd(request.amount);
            displayCharges = formatUsd(usdtCharges);
            displayNet = formatUsd(request.net_amount || (request.amount - usdtCharges));
        }

        let statusBadgeClass = 'is-pending';
        let statusLabel = 'Pending';
        if (statusRaw === 'approved' || statusRaw === 'completed' || statusRaw === 'accepted' || statusRaw === 'success') {
            statusBadgeClass = 'is-accepted';
            statusLabel = 'Accepted';
        } else if (statusRaw === 'rejected' || statusRaw === 'declined' || statusRaw === 'cancelled') {
            statusBadgeClass = 'is-rejected';
            statusLabel = 'Rejected';
        }

        const dateStr = formatDate(request.created_at);
        const noteStr = request.admin_note || request.note ? `Note: ${escapeHtml(request.admin_note || request.note)}` : '';

        return `
            <!-- Desktop Grid Item (Hidden on screens <= 1024px) -->
            <div class="withdrawal-item">
                <div>
                    <span>Amount</span>
                    <strong>${displayAmt}</strong>
                </div>
                <div>
                    <span>Charges</span>
                    <strong>${displayCharges}</strong>
                </div>
                <div>
                    <span>You receive</span>
                    <strong>${displayNet}</strong>
                </div>
                <div>
                    <span>Status</span>
                    <strong class="wallet-status-badge ${statusRaw}">${escapeHtml(statusLabel)}</strong>
                </div>
                <div>
                    <span>Admin note</span>
                    <p>${escapeHtml(request.admin_note || request.note || '--')}</p>
                </div>
                <div>
                    <span>Requested</span>
                    <strong>${dateStr}</strong>
                </div>
            </div>

            <!-- Mobile Card Item (Shown on screens <= 1024px) -->
            <div class="wallet-history-row-item">
                <div class="wh-card-top-row">
                    <div class="wh-card-title-wrap">
                        <div class="wh-dir-icon is-debit">↑</div>
                        <div class="wh-title-group">
                            <div class="wh-title-line">
                                <span class="wh-title">Withdrawal</span>
                                <span class="wh-cur-badge">${escapeHtml(netCode)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="wh-amount-wrap text-emerald">
                        ${displayNet}
                    </div>
                </div>
                <div class="wh-card-bottom-row">
                    <div class="wh-time-text">
                        <span>${dateStr}</span>
                        ${noteStr ? `<span class="wh-note-text">${noteStr}</span>` : ''}
                    </div>
                    <div class="wh-status-bal-wrap">
                        <span class="wh-status-badge ${statusBadgeClass}">${statusLabel}</span>
                        <span class="wh-fee-text">Fee: <strong>${displayCharges}</strong></span>
                    </div>
                </div>
            </div>
        `;
    }).join('');

    renderPagination('withdrawalPagination', pagination, (p) => {
        currentWithdrawalPage = p;
        loadWithdrawalHistory(p);
    });
}

function renderLedger(ledger, pagination) {
    const body = document.getElementById('walletLedgerBody');
    const cardList = document.getElementById('walletLedgerCardsList');

    if (!body && !cardList) {
        return;
    }

    if (!ledger.length) {
        if (body) {
            body.innerHTML = `
                <tr>
                    <td colspan="5" class="wallet-empty" style="padding: 24px; text-align: center; color: rgba(255,255,255,0.4);">No ledger records found.</td>
                </tr>
            `;
        }
        if (cardList) {
            cardList.innerHTML = '<div class="wallet-empty" style="padding: 24px; text-align: center; color: rgba(255,255,255,0.4);">No ledger records found.</div>';
        }
        renderPagination('ledgerPagination', null);
        return;
    }

    if (body) {
        body.innerHTML = ledger.map((row) => {
            const direction = row.direction || '--';
            const directionClass = direction === 'credit' ? 'wallet-credit' : 'wallet-debit';
            const status = row.status || '--';

            const rowCur = (row.wallet_currency || row.currency || '').toUpperCase();
            const isINR = rowCur === 'INR' || !!row.meta?.amount_inr || !!row.amount_inr || (row.type && row.type.toLowerCase().includes('inr'));

            const rawAmt = isINR ? (row.meta?.amount_inr || row.amount_inr || row.amount || 0) : (row.amount || 0);
            const amountDisplay = isINR ? formatInr(rawAmt) : formatUsd(rawAmt);

            return `
                <tr>
                    <td data-label="Type">${formatType(row.type || '--')}</td>
                    <td data-label="Direction" class="${directionClass}">${escapeHtml(direction)}</td>
                    <td data-label="Amount">${amountDisplay}</td>
                    <td data-label="Status"><span class="wallet-status-badge ${escapeHtml(status)}">${escapeHtml(status)}</span></td>
                    <td data-label="Time">${formatDate(row.created_at)}</td>
                </tr>
            `;
        }).join('');
    }

    if (cardList) {
        cardList.innerHTML = ledger.map((row) => {
            const direction = (row.direction || '--').toLowerCase();
            const isCredit = direction === 'credit';
            const statusRaw = String(row.status || 'completed').toLowerCase();
            const typeStr = formatType(row.type || '--');
            const rowCur = (row.wallet_currency || row.currency || (row.type && row.type.toLowerCase().includes('inr') ? 'INR' : 'USD')).toUpperCase();
            const isINR = rowCur === 'INR' || !!row.meta?.amount_inr || !!row.amount_inr || (row.type && row.type.toLowerCase().includes('inr'));

            const rawAmt = isINR ? (row.meta?.amount_inr || row.amount_inr || row.amount || 0) : (row.amount || 0);
            const amountDisplay = isINR ? formatInr(rawAmt) : formatUsd(rawAmt);
            const timeStr = formatDate(row.created_at);

            let statusBadgeClass = 'is-completed';
            let statusLabel = 'Completed';
            if (statusRaw.includes('fail') || statusRaw.includes('reject')) {
                statusBadgeClass = 'is-failed';
                statusLabel = 'Failed';
            } else if (statusRaw.includes('pend')) {
                statusBadgeClass = 'is-pending';
                statusLabel = 'Pending';
            }

            return `
                <div class="wallet-history-row-item">
                    <!-- Top Row: Direction + Title + Currency on Left, Amount on Right -->
                    <div class="wh-card-top-row">
                        <div class="wh-card-title-wrap">
                            <div class="wh-dir-icon ${isCredit ? 'is-credit' : 'is-debit'}">
                                ${isCredit ? '↓' : '↑'}
                            </div>
                            <div class="wh-title-group">
                                <div class="wh-title-line">
                                    <span class="wh-title">${escapeHtml(typeStr)}</span>
                                    <span class="wh-cur-badge">${escapeHtml(rowCur)}</span>
                                </div>
                            </div>
                        </div>
                        <div class="wh-amount-wrap ${isCredit ? 'text-emerald' : 'text-rose'}">
                            ${isCredit ? '+' : '-'}${amountDisplay}
                        </div>
                    </div>

                    <!-- Bottom Row: Date & Time on Left, Status Badge on Right -->
                    <div class="wh-card-bottom-row">
                        <div class="wh-time-text">
                            <span>${timeStr}</span>
                        </div>
                        <div class="wh-status-bal-wrap">
                            <span class="wh-status-badge ${statusBadgeClass}">${statusLabel}</span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    renderPagination('ledgerPagination', pagination, (p) => {
        currentLedgerPage = p;
        loadLedgerHistory(p);
    });
}

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
    buttonsHtml += `<button type="button" ${current === 1 ? 'disabled' : ''} data-page="${current - 1}">Prev</button>`;
    
    for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= current - 2 && i <= current + 2)) {
            buttonsHtml += `<button type="button" class="${i === current ? 'active is-active' : ''}" data-page="${i}">${i}</button>`;
        } else if (i === current - 3 || i === current + 3) {
            buttonsHtml += `<span>...</span>`;
        }
    }

    buttonsHtml += `<button type="button" ${current === last ? 'disabled' : ''} data-page="${current + 1}">Next</button>`;
    
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

function showLedgerSkeleton() {
    const body = document.getElementById('walletLedgerBody');
    const cardList = document.getElementById('walletLedgerCardsList');

    if (body) {
        body.innerHTML = Array(5).fill(0).map(() => `
            <tr>
                <td><div class="wallet-skeleton"></div></td>
                <td><div class="wallet-skeleton"></div></td>
                <td><div class="wallet-skeleton"></div></td>
                <td><div class="wallet-skeleton badge"></div></td>
                <td><div class="wallet-skeleton"></div></td>
            </tr>
        `).join('');
    }

    if (cardList) {
        cardList.innerHTML = Array(4).fill(0).map(() => `
            <div class="wallet-history-row-item" style="opacity:0.6;">
                <div class="wh-card-top-row">
                    <div class="wh-card-title-wrap">
                        <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
                        <div style="width:100px;height:14px;background:rgba(255,255,255,0.08);border-radius:4px;"></div>
                    </div>
                    <div style="width:60px;height:16px;background:rgba(255,255,255,0.08);border-radius:4px;"></div>
                </div>
                <div class="wh-card-bottom-row" style="margin-top:6px;">
                    <div style="width:110px;height:10px;background:rgba(255,255,255,0.05);border-radius:4px;"></div>
                    <div style="width:70px;height:14px;background:rgba(255,255,255,0.08);border-radius:9999px;"></div>
                </div>
            </div>
        `).join('');
    }
}

function showWithdrawalSkeleton() {
    const list = document.getElementById('withdrawalRequestsList');
    if (!list) return;
    list.innerHTML = Array(3).fill(0).map(() => `
        <div class="withdrawal-item">
            <div><span>Amount</span><div class="wallet-skeleton"></div></div>
            <div><span>Charges</span><div class="wallet-skeleton"></div></div>
            <div><span>You receive</span><div class="wallet-skeleton"></div></div>
            <div><span>Status</span><div class="wallet-skeleton badge"></div></div>
            <div><span>Admin note</span><div class="wallet-skeleton"></div></div>
            <div><span>Requested</span><div class="wallet-skeleton"></div></div>
        </div>
        <div class="wallet-history-row-item" style="opacity:0.6;">
            <div class="wh-card-top-row">
                <div class="wh-card-title-wrap">
                    <div style="width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,0.06);"></div>
                    <div style="width:100px;height:14px;background:rgba(255,255,255,0.08);border-radius:4px;"></div>
                </div>
                <div style="width:60px;height:16px;background:rgba(255,255,255,0.08);border-radius:4px;"></div>
            </div>
            <div class="wh-card-bottom-row" style="margin-top:6px;">
                <div style="width:110px;height:10px;background:rgba(255,255,255,0.05);border-radius:4px;"></div>
                <div style="width:70px;height:14px;background:rgba(255,255,255,0.08);border-radius:9999px;"></div>
            </div>
        </div>
    `).join('');
}

function clearLedgerTable() {
    const body = document.getElementById('walletLedgerBody');
    const cardList = document.getElementById('walletLedgerCardsList');
    if (body) body.innerHTML = '<tr><td colspan="5" class="wallet-empty">No ledger records found.</td></tr>';
    if (cardList) cardList.innerHTML = '<div class="wallet-empty">No ledger records found.</div>';
}

function clearWithdrawalTable() {
    const list = document.getElementById('withdrawalRequestsList');
    if (list) list.innerHTML = '<div class="wallet-empty">No withdrawal requests found.</div>';
}

async function authFetch(url, options = {}, hasRetried = false, networkRetries = 0) {
    const token = localStorage.getItem('kryvex_access_token');
    const activeAccountUid = localStorage.getItem('kryvex_active_account_uid');
    const activeTradingMode = isCurrentAccountLive() ? 'live' : 'demo';

    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        'X-Trading-Mode': activeTradingMode,
        ...(options.headers || {}),
    };

    if (activeAccountUid) {
        headers['X-Account-UID'] = activeAccountUid;
    }

    let response;
    try {
        response = await fetch(url, {
            ...options,
            headers,
        });
    } catch (error) {
        if (networkRetries < 2) {
            return authFetch(url, options, hasRetried, networkRetries + 1);
        }
        throw error;
    }

    const data = await parseJsonResponse(response);

    if (response.status === 401 && !hasRetried) {
        const refreshed = await refreshAccessToken();

        if (refreshed) {
            return authFetch(url, options, true);
        }

        clearAuthSession();
        const returnUrl = window.location.pathname + window.location.search + window.location.hash;
        window.location.href = `/login?redirect=${encodeURIComponent(returnUrl)}`;
        throw new Error('Session expired. Please login again.');
    }

    if (response.status === 429) {
        throw new Error('Too many attempts in a short time. Please wait a few seconds and try again.');
    }

    if (!response.ok || !data.success) {
        throw new Error(data.message || 'Request failed.');
    }

    return data;
}

let refreshPromise = null;

async function refreshAccessToken() {
    if (refreshPromise) {
        return refreshPromise;
    }

    refreshPromise = (async () => {
        const refreshToken = localStorage.getItem('kryvex_refresh_token');

        if (!refreshToken) {
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
                return false;
            }

            localStorage.setItem('kryvex_access_token', data.data.access_token);

            return true;
        } catch (error) {
            return false;
        } finally {
            refreshPromise = null;
        }
    })();

    return refreshPromise;
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
    localStorage.removeItem('kryvex_wallet_cache');
    localStorage.removeItem('kryvex_trading_accounts_cache');
    localStorage.removeItem('kryvex_ledger_cache');
    localStorage.removeItem('kryvex_withdrawals_cache');
    localStorage.removeItem('kryvex_inr_state_cache');
}

function formToObject(form) {
    const formData = new FormData(form);
    const payload = {};

    formData.forEach((value, key) => {
        payload[key] = value;
    });

    return payload;
}

function showWalletToast(message, type = 'success') {
    if (typeof window.showToast === 'function') {
        window.showToast({
            type: type,
            title: type === 'success' ? 'Success' : type.charAt(0).toUpperCase() + type.slice(1),
            message: message
        });
    }
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.dataset.realValue = value;
        if (window.isWalletBalanceHidden && element.hasAttribute('data-sensitive-balance')) {
            element.textContent = '••••••••';
        } else {
            element.textContent = value;
        }
    }
}

function formatUsd(value) {
    const number = Number(value || 0);
    const maxDigits = (Math.abs(number) > 0 && Math.abs(number) < 0.01) ? 6 : (number % 1 !== 0 && (number.toString().split('.')[1] || '').length > 2 ? 4 : 2);

    return `$${number.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: maxDigits,
    })}`;
}

function formatDate(value) {
    if (!value) {
        return '--';
    }

    let parsedValue = value;

    if (typeof value === 'object') {
        parsedValue = value.date || value.$date || '';
    }

    const d = new Date(parsedValue);
    if (isNaN(d.getTime())) return String(parsedValue);

    const datePart = d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    const timePart = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    return `${datePart}, ${timePart}`;
}

function formatType(value) {
    const raw = String(value || '--').toLowerCase().replaceAll('_', ' ').trim();
    const typeMap = {
        'deposit': 'Deposit',
        'withdraw': 'Withdrawal',
        'withdrawal': 'Withdrawal',
        'transfer': 'Transfer',
        'exchange': 'Exchange',
        'trading': 'Trading',
        'futures trading fee open': 'Futures Fee (Open)',
        'futures trading fee close': 'Futures Fee (Close)',
        'futures margin locked': 'Futures Margin Locked',
        'futures margin released': 'Futures Margin Released',
        'futures realized pnl': 'Futures Realized PnL',
        'futures unrealized pnl': 'Futures Unrealized PnL',
        'options trading fee open': 'Options Fee (Open)',
        'options trading fee close': 'Options Fee (Close)',
        'options margin locked': 'Options Margin Locked',
        'options margin released': 'Options Margin Released',
        'options realized pnl': 'Options Realized PnL',
    };

    if (typeMap[raw]) {
        return typeMap[raw];
    }

    return raw.split(' ').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

// ==============================
// INR CURRENCY SUPPORT
// ==============================

function formatInr(amount) {
    const n = Number(amount || 0);
    return '\u20b9' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadInrConfig() {
    const token = localStorage.getItem('kryvex_access_token');
    if (!token) return;

    try {
        // 1. Check user base currency
        const bcRes = await fetch('/api/auth/base-currency', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        });
        const bcData = await bcRes.json();
        inrState.baseCurrency = bcData?.data?.base_currency || null;

        // 2. Load USD/INR rate from wallet config
        const rateRes = await fetch('/api/wallet/inr-rate', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        });
        const rateData = await rateRes.json();
        inrState.usdtInrRate = Number(rateData?.data?.usdt_inr_rate || 0);
        inrState.adminBankDetails = rateData?.data?.bank_details || null;

        try {
            localStorage.setItem('kryvex_inr_state_cache', JSON.stringify({
                baseCurrency: inrState.baseCurrency,
                usdtInrRate: inrState.usdtInrRate,
                adminBankDetails: inrState.adminBankDetails
            }));
        } catch (e) {}

        applyCurrencyRestrictions();
    } catch (e) {
        // Silent — wallet works normally without INR config
    }
}

function verifyKycAndCurrencyBeforeAction() {
    const token = localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token');
    if (!token) {
        showWalletToast('Please login first.', 'error');
        return false;
    }

    let user = {};
    try {
        user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (e) {}

    const kycStatus = (user.kyc_status || '').toLowerCase();
    const isKycVerified = ['verified', 'approved'].includes(kycStatus);
    const baseCurrency = user.base_currency || inrState.baseCurrency || null;

    if (!isKycVerified) {
        showWalletToast('KYC Verification Required: Please complete KYC identity verification first before performing deposit or withdrawal.', 'error');
        return false;
    }

    return true;
}

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

function applyCurrencyRestrictions() {
    let user = {};
    try { user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {}; } catch (e) {}
    const isMt5 = (user.broker_type || 'kryvex').toLowerCase() === 'mt5';

    const depTabs = document.getElementById('depositCurrencyTabs');
    const witTabs = document.getElementById('withdrawCurrencyTabs');
    const depTabInr = document.getElementById('depositTabInr');
    const witTabInr = document.getElementById('withdrawTabInr');
    const inrPanel = document.getElementById('inrDepositPanel');
    const inrBankBox = document.getElementById('inrBankDetailsBox');
    const inrBankInfo = document.getElementById('inrBankInfo');

    if (isMt5) {
        if (depTabs) depTabs.style.display = 'none';
        if (witTabs) witTabs.style.display = 'none';
        if (depTabInr) depTabInr.style.display = 'none';
        if (witTabInr) witTabInr.style.display = 'none';
        if (inrPanel) inrPanel.style.display = 'none';
        if (inrBankBox) inrBankBox.style.display = 'none';
        if (inrBankInfo) inrBankInfo.style.display = 'none';

        const usdtForm = document.getElementById('demoCreditForm');
        if (usdtForm) usdtForm.style.display = 'block';

        switchCurrencyTab('deposit', 'usdt');
        switchCurrencyTab('withdraw', 'usdt');
    } else {
        if (depTabs) { depTabs.style.display = 'flex'; depTabs.style.gap = '8px'; depTabs.style.marginBottom = '18px'; }
        if (witTabs) { witTabs.style.display = 'none'; }

        if (document.getElementById('depositTabUsdt')?.style) document.getElementById('depositTabUsdt').style.display = 'block';
        if (document.getElementById('depositTabInr')?.style) document.getElementById('depositTabInr').style.display = 'block';
        if (document.getElementById('withdrawTabUsdt')?.style) document.getElementById('withdrawTabUsdt').style.display = 'block';
        if (document.getElementById('withdrawTabInr')?.style) document.getElementById('withdrawTabInr').style.display = 'block';

        setupCurrencyTabSwitcher('deposit');
        setupCurrencyTabSwitcher('withdraw');
        renderInrBankDetails();
        updateInrRateLabels();

        const activeCur = window.getActiveCurrency ? window.getActiveCurrency() : 'INR';
        switchCurrencyTab('deposit', activeCur === 'INR' ? 'inr' : 'usdt');
        switchCurrencyTab('withdraw', window.getDefaultWithdrawTab ? window.getDefaultWithdrawTab() : (activeCur === 'INR' ? 'inr' : 'usdt'));
    }
}

function renderInrBankDetails() {
    const box = document.getElementById('inrBankInfo') || document.getElementById('futuresInrBankInfo');
    if (!box) return;
    const d = inrState.adminBankDetails;
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

function updateInrRateLabels() {
    const rate = inrState.usdtInrRate;
    const rateLabel = document.getElementById('inrDepositRateLabel');
    if (rateLabel) {
        rateLabel.textContent = rate
            ? `Min $10 / \u20b9${(10 * rate).toFixed(0)} \u00b7 $1 = \u20b9${rate}`
            : 'Rate not set by admin yet.';
    }
}

function updateMainBalanceInr() {
    // If INR user — show main balance strictly in INR
    if (inrState.baseCurrency !== 'INR' || !inrState.usdtInrRate) return;
    const mainUsdt = walletRuleState.demoMainBalance || 0;
    const inr = mainUsdt * inrState.usdtInrRate;
    const el = document.getElementById('demoMainBalance');
    if (el) el.textContent = formatInr(inr);
}

function setupCurrencyTabSwitcher(section) {
    const usdtBtn = document.getElementById(`${section}TabUsdt`);
    const inrBtn = document.getElementById(`${section}TabInr`);
    if (usdtBtn) usdtBtn.addEventListener('click', () => switchCurrencyTab(section, 'usdt'));
    if (inrBtn) inrBtn.addEventListener('click', () => switchCurrencyTab(section, 'inr'));
}

function switchCurrencyTab(section, tab) {
    const usdtBtn = document.getElementById(`${section}TabUsdt`) || document.getElementById(`btn${section.charAt(0).toUpperCase() + section.slice(1)}UsdPill`);
    const inrBtn = document.getElementById(`${section}TabInr`) || document.getElementById(`btn${section.charAt(0).toUpperCase() + section.slice(1)}InrPill`);

    if (usdtBtn) usdtBtn.classList.toggle('is-active', tab === 'usdt');
    if (inrBtn) inrBtn.classList.toggle('is-active', tab === 'inr');

    if (section === 'deposit') {
        const usdtForm = document.getElementById('demoCreditForm');
        const inrPanel = document.getElementById('inrDepositPanel');
        if (usdtForm) usdtForm.style.display = tab === 'usdt' ? 'block' : 'none';
        if (inrPanel) inrPanel.style.display = tab === 'inr' ? 'block' : 'none';

        const notesList = document.getElementById('walletDpNotesList') || document.getElementById('dpNotesList');
        if (notesList) {
            if (tab === 'inr') {
                notesList.innerHTML = `
                    <li>• Minimum deposit: ₹ 100 INR</li>
                    <li>• Transfer funds via UPI/IMPS/NEFT.</li>
                    <li>• Enter your 12-digit UTR/Transaction Reference No. accurately for fast approval.</li>
                `;
            } else {
                const network = getSelectedNetwork(document.getElementById('depositNetworkSelect')?.value);
                const netLabel = network?.network || 'TRC20';
                const minDep = network?.minimum_deposit || 100;
                notesList.innerHTML = `
                    <li>• Minimum deposit: $${minDep} (or equivalent)</li>
                    <li>• Send only USDT to this ${escapeHtml(netLabel)} address.</li>
                    <li>• Ensure the network matches (${escapeHtml(netLabel)}).</li>
                    <li>• Deposits will be credited after network confirmation.</li>
                `;
            }
        }
    } else {
        if (typeof window.setStepWithdrawMode === 'function') {
            window.setStepWithdrawMode(tab === 'inr' ? 'money' : 'crypto');
        } else {
            const cryptoContainer = document.getElementById('withdrawCryptoStepsContainer');
            const moneyContainer = document.getElementById('withdrawMoneyStepsContainer');
            const usdtForm = document.getElementById('withdrawForm');
            const inrForm = document.getElementById('inrWithdrawForm');

            if (tab === 'inr') {
                if (cryptoContainer) cryptoContainer.style.display = 'none';
                if (moneyContainer) moneyContainer.style.display = 'block';
                if (inrForm) inrForm.style.display = 'flex';
            } else {
                if (moneyContainer) moneyContainer.style.display = 'none';
                if (cryptoContainer) cryptoContainer.style.display = 'block';
                if (usdtForm) usdtForm.style.display = 'flex';
            }
        }
    }
}
window.switchCurrencyTab = switchCurrencyTab;

function setupInrDepositForm() {
    const amountInput = document.getElementById('inrDepositAmount');
    amountInput?.addEventListener('input', () => {
        const inr = Number(amountInput.value || 0);
        const rate = inrState.usdtInrRate || 1;
        const usdt = rate > 0 ? (inr / rate) : 0;
        const youPayEl = document.getElementById('inrDepositConvYouPay');
        const creditEl = document.getElementById('inrDepositConvCredit');
        if (youPayEl) youPayEl.textContent = formatInr(inr);
        if (creditEl) creditEl.textContent = '$' + usdt.toFixed(2) + ' USD';
    });

    const form = document.getElementById('inrDepositForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!verifyKycAndCurrencyBeforeAction()) return;
        const submitBtn = form.querySelector('button[type="submit"]');

        const amountInr = Number(document.getElementById('inrDepositAmount')?.value || 0);
        if (!amountInr || amountInr <= 0) {
            showWalletToast('Please enter a valid INR deposit amount.', 'error');
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Initiating Razorpay...';
        }

        let user = {};
        try {
            user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
        } catch (err) {}

        try {
            // 1. Create Razorpay Order
            const orderRes = await authFetch('/api/wallet/razorpay/create-order', {
                method: 'POST',
                body: JSON.stringify({ amount_inr: amountInr, account_uid: selectedDepositAccountUid() }),
            });

            if (!orderRes || !orderRes.success) {
                throw new Error(orderRes?.message || 'Failed to initialize Razorpay payment order.');
            }

            // 2. Load Razorpay Checkout SDK
            const RazorpayCtor = await loadRazorpaySdk();

            // 3. Configure Razorpay Checkout Options
            const options = {
                key: orderRes.key_id,
                amount: orderRes.amount_paise,
                currency: orderRes.currency || 'INR',
                name: 'Kryvex Exchange',
                description: `INR Wallet Deposit ₹${amountInr}`,
                order_id: orderRes.order_id,
                prefill: {
                    name: orderRes.user?.name || user.name || '',
                    email: orderRes.user?.email || user.email || '',
                    contact: orderRes.user?.mobile || user.mobile || '',
                },
                theme: {
                    color: '#2563eb',
                },
                handler: async function (response) {
                    if (submitBtn) submitBtn.textContent = 'Verifying Payment...';
                    try {
                        const verifyRes = await authFetch('/api/wallet/razorpay/verify', {
                            method: 'POST',
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id || orderRes.order_id,
                                razorpay_payment_id: response.razorpay_payment_id || '',
                                razorpay_signature: response.razorpay_signature || '',
                                amount_inr: amountInr,
                            }),
                        });

                        showWalletToast(verifyRes.message || 'Razorpay Payment Verified & Wallet Credited! 🎉', 'success');
                        form.reset();
                        const youPayEl = document.getElementById('inrDepositConvYouPay');
                        const creditEl = document.getElementById('inrDepositConvCredit');
                        if (youPayEl) youPayEl.textContent = '₹0';
                        if (creditEl) creditEl.textContent = '$0.00 USD';

                        const depositModal = document.getElementById('depositModalBackdrop');
                        if (depositModal) {
                            depositModal.hidden = true;
                            depositModal.style.display = 'none';
                        }
                        if (window.loadWalletConfig) await window.loadWalletConfig();
                        if (window.loadLedgerHistory) await window.loadLedgerHistory(1);
                        if (window.reloadFuturesBalances) await window.reloadFuturesBalances();
                    } catch (err) {
                        showWalletToast(err.message || 'Payment verification failed.', 'error');
                    } finally {
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<span>Pay with Razorpay</span> 💳'; }
                    }
                },
                modal: {
                    ondismiss: function () {
                        if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<span>Pay with Razorpay</span> 💳'; }
                        showWalletToast('Razorpay payment cancelled.', 'warning');
                    }
                }
            };

            const rzp = new RazorpayCtor(options);
            rzp.open();

        } catch (error) {
            showWalletToast(error.message || 'Unable to initiate Razorpay payment.', 'error');
            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<span>Pay with Razorpay</span> 💳'; }
        }
    });
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

function setupInrWithdrawForm() {
    const amountInput = document.getElementById('inrWithdrawAmount');

    const updateInrWithdrawPreview = () => {
        if (typeof window.updateWithdrawCalculation === 'function') {
            window.updateWithdrawCalculation();
            return;
        }

        const amountInr = Number(amountInput?.value || 0);
        const rate = inrState.usdtInrRate || 1;
        const amountUsdt = rate > 0 ? (amountInr / rate) : 0;

        const withdrawalFeeUsdt = calculateCharge(amountUsdt, walletRuleState.withdrawalFeeType, walletRuleState.withdrawalFeeValue);
        const platformChargeUsdt = calculateCharge(amountUsdt, walletRuleState.platformChargeType, walletRuleState.platformChargeValue);

        const withdrawalFeeInr = roundMoney(withdrawalFeeUsdt * rate);
        const platformChargeInr = roundMoney(platformChargeUsdt * rate);
        const totalChargeInr = roundMoney(withdrawalFeeInr + platformChargeInr);
        const netAmountInr = Math.max(roundMoney(amountInr - totalChargeInr), 0);

        const convEl = document.getElementById('inrWithdrawConvUsdt');
        if (convEl) convEl.textContent = amountInr > 0 ? ('$' + amountUsdt.toFixed(4) + ' USD') : '--';

        setText('inrWithdrawPreviewAmount', amountInr > 0 ? formatInr(amountInr) : '--');
        setText('inrWithdrawPreviewFee', amountInr > 0 ? formatInr(withdrawalFeeInr) : '--');
        setText('inrWithdrawPreviewPlatformCharge', amountInr > 0 ? formatInr(platformChargeInr) : '--');
        setText('inrWithdrawPreviewNet', amountInr > 0 ? formatInr(netAmountInr) : '--');
    };

    amountInput?.addEventListener('input', updateInrWithdrawPreview);

    // Load saved bank details banner
    loadUserSavedBankDetails().then((b) => {
        const banner = document.getElementById('savedBankDetailsBanner');

        if (b) {
            if (banner) {
                banner.style.display = 'block';
                banner.innerHTML = `
                    <div class="wallet-bank-saved-banner">
                        <div class="bank-saved-header">
                            <span class="bank-saved-title">
                                <span>🏛️</span> Withdrawal Bank Account
                            </span>
                            <a href="/settings?tab=bank" class="bank-saved-edit-btn">Edit ⚙️</a>
                        </div>
                        <div class="bank-saved-account-name">${escapeHtml(b.account_name || '')} • ${escapeHtml(b.bank_name || '')} <span class="bank-saved-type">(${escapeHtml(b.account_type || 'Savings')})</span></div>
                        <div class="bank-saved-details-row">
                            <span>A/C: <strong class="bank-saved-val">${escapeHtml(b.account_number || '')}</strong></span>
                            <span>|</span>
                            <span>IFSC: <strong class="bank-saved-val">${escapeHtml(b.ifsc_code || '')}</strong></span>
                        </div>
                        ${b.upi_id ? `<div class="bank-saved-upi">UPI: ${escapeHtml(b.upi_id)}</div>` : ''}
                    </div>
                `;
            }
        } else {
            if (banner) {
                banner.style.display = 'block';
                banner.innerHTML = `
                    <div class="wallet-bank-missing-banner">
                        <div>
                            <strong class="bank-missing-title">⚠️ Bank Details Missing</strong>
                            <span class="bank-missing-sub">Please save your bank account details first in Settings to withdraw.</span>
                        </div>
                        <a href="/settings?tab=bank" class="bank-missing-btn">
                            Add Bank Details ➔
                        </a>
                    </div>
                `;
            }
        }
    });

    document.querySelectorAll('.withdraw-pct-btn[data-inr-pct]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const pct = Number(btn.getAttribute('data-inr-pct') || 100);
            const rate = inrState.usdtInrRate || 1;
            const maxInr = getInrWithdrawable(rate);
            const input = document.getElementById('inrWithdrawAmount');
            if (input && maxInr > 0) {
                const targetVal = maxInr * (pct / 100);
                input.value = targetVal.toFixed(2);
                input.dispatchEvent(new Event('input', { bubbles: true }));
                updateInrWithdrawPreview();
            }
        });
    });

    document.getElementById('inrWithdrawMaxButton')?.addEventListener('click', () => {
        const rate = inrState.usdtInrRate || 1;
        const maxInr = getInrWithdrawable(rate);
        const mainBalInr = getInrWithdrawBalance();

        if (amountInput) {
            amountInput.value = maxInr > 0 ? maxInr.toFixed(2) : '';
            updateInrWithdrawPreview();

            if (walletRuleState.hasOpenTrades && mainBalInr > maxInr) {
                showWalletToast(`Note: Open trading positions exist. A minimum reserve of ${formatInr(walletRuleState.minimumReserve * rate)} (${formatUsd(walletRuleState.minimumReserve)}) must remain in your account while positions are open. Maximum withdrawable now is ${formatInr(maxInr)}. To withdraw your total full balance (${formatInr(mainBalInr)}), please close all open positions first.`, 'info');
            }
        }
    });

    const form = document.getElementById('inrWithdrawForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!verifyKycAndCurrencyBeforeAction()) return;
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting...'; }

        const amountInr = Number(document.getElementById('inrWithdrawAmount')?.value || 0);
        const payload = {
            amount_inr: amountInr,
            account_name: form.querySelector('[name="account_name"]')?.value?.trim() || (savedUserBankDetails?.account_name ?? ''),
            bank_name: form.querySelector('[name="bank_name"]')?.value?.trim() || (savedUserBankDetails?.bank_name ?? ''),
            account_number: form.querySelector('[name="account_number"]')?.value?.trim() || (savedUserBankDetails?.account_number ?? ''),
            ifsc: form.querySelector('[name="ifsc"]')?.value?.trim() || (savedUserBankDetails?.ifsc_code ?? ''),
            upi_id: form.querySelector('[name="upi_id"]')?.value?.trim() || (savedUserBankDetails?.upi_id ?? null),
            account_uid: selectedWithdrawAccountUid(),
        };

        if (!amountInr || amountInr <= 0) { showWalletToast('Enter withdrawal amount.', 'error'); if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Withdrawal Request'; } return; }
        if (!payload.account_name && !payload.upi_id && !savedUserBankDetails) { showWalletToast('Please save bank details in Settings or enter them below.', 'error'); if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Withdrawal Request'; } return; }

        const rate = inrState.usdtInrRate || 1;
        const maxInrWithdrawable = getInrWithdrawable(rate);
        if (amountInr > maxInrWithdrawable) {
            if (walletRuleState.hasOpenTrades) {
                showWalletToast(`Unable to withdraw full balance. Open trading positions exist. Minimum reserve of ${formatInr(walletRuleState.minimumReserve * rate)} (${formatUsd(walletRuleState.minimumReserve)}) must remain while positions are open. Maximum withdrawable amount now is ${formatInr(maxInrWithdrawable)}. To withdraw your total full balance, please close all open positions first.`, 'error');
            } else {
                showWalletToast(`Maximum withdrawable amount is ${formatInr(maxInrWithdrawable)}.`, 'error');
            }
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Withdrawal Request'; }
            return;
        }

        try {
            const data = await authFetch('/api/wallet/inr-withdraw', {
                method: 'POST',
                body: JSON.stringify(payload),
            });
            showWalletToast(data.message || 'INR withdrawal request submitted.', 'success');
            form.reset();
            updateInrWithdrawPreview();
            await loadWalletConfig();
            await loadWithdrawalHistory(1);
        } catch (error) {
            showWalletToast(error.message || 'Unable to submit INR withdrawal.', 'error');
        } finally {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Withdrawal Request'; }
        }
    });
}

window.addEventListener('kryvex_currency_changed', () => {
    loadWalletConfig();
    applyCurrencyRestrictions();
});

// Asset-specific Crypto Deposit Modal Logic (Matching User's Image 2)
const cryptoAssetModal = document.getElementById('cryptoAssetDepositModalBackdrop');
const closeCryptoAssetBtn = document.getElementById('closeCryptoAssetDepositModalBtn');
const copyCryptoAssetBtn = document.getElementById('copyCryptoAssetAddressBtn');
const cryptoAssetForm = document.getElementById('cryptoAssetDepositForm');

const assetDetailsMap = {
    'BTC':  { name: 'Bitcoin', icon: 'B', color: '#f7931a', defaultNetwork: 'BTC', notice: 'SEND ONLY BTC ON BTC NETWORK' },
    'ETH':  { name: 'Ethereum', icon: 'E', color: '#627eea', defaultNetwork: 'ETH', notice: 'SEND ONLY ETH ON ETH NETWORK' },
    'USDC': { name: 'USD Coin', icon: 'U', color: '#2775ca', defaultNetwork: 'USDC', notice: 'SEND ONLY USDC ON ERC20 NETWORK' },
    'USDT': { name: 'Tether USD', icon: 'U', color: '#26a17b', defaultNetwork: 'USDT', notice: 'SEND ONLY USDT ON TRC20 / ERC20' },
    'BNB':  { name: 'Binance Coin', icon: 'B', color: '#f3ba2f', defaultNetwork: 'BNB', notice: 'SEND ONLY BNB ON BEP20 / BNB NETWORK' },
    'SOL':  { name: 'Solana', icon: 'S', color: '#9945ff', defaultNetwork: 'SOL', notice: 'SEND ONLY SOL ON SOL NETWORK' },
    'XRP':  { name: 'Ripple', icon: 'X', color: '#23292f', defaultNetwork: 'XRP', notice: 'SEND ONLY XRP ON RIPPLE NETWORK' },
    'ADA':  { name: 'Cardano', icon: 'A', color: '#0033ad', defaultNetwork: 'ADA', notice: 'SEND ONLY ADA ON CARDANO NETWORK' }
};

document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action-deposit-asset], [data-action-deposit-crypto]');
    if (btn) {
        e.preventDefault();
        const asset = (btn.getAttribute('data-action-deposit-asset') || btn.getAttribute('data-action-deposit-crypto') || 'USDT').toUpperCase();

        const opsSection = document.getElementById('viewSectionWalletOps');
        const isOpsVisible = opsSection && opsSection.style.display !== 'none';
        const innerPanelDeposit = document.getElementById('innerPanelDeposit');
        const isDepositPanelVisible = isOpsVisible && innerPanelDeposit && innerPanelDeposit.style.display !== 'none';

        if (isDepositPanelVisible) {
            if (typeof window.selectStepDepositCurrency === 'function') {
                window.selectStepDepositCurrency(asset);
            }
            document.getElementById('innerPanelDeposit')?.scrollIntoView({ behavior: 'smooth' });
        } else {
            if (typeof window.openAddFundsModal === 'function') {
                window.openAddFundsModal();
            } else if (typeof window.openDepositModalPopup === 'function') {
                window.openDepositModalPopup(asset);
            }
        }
        return;
    }

    const withdrawBtn = e.target.closest('[data-action-withdraw-asset], [data-action-withdraw-crypto]');
    if (withdrawBtn) {
        e.preventDefault();
        const asset = (withdrawBtn.getAttribute('data-action-withdraw-asset') || withdrawBtn.getAttribute('data-action-withdraw-crypto') || 'ETH').toUpperCase();

        // 1. Switch to Wallet Tab
        const btnWallet = document.getElementById('tabNavWallet');
        if (btnWallet) btnWallet.click();

        // 2. Switch inner nav to Withdraw
        const innerNavWithdraw = document.getElementById('innerNavWithdraw');
        if (innerNavWithdraw) innerNavWithdraw.click();

        // 3. Force activate INR card for INR, or Crypto/USD card for all other currencies
        if (asset === 'INR') {
            if (window.activateWithdrawTab) window.activateWithdrawTab('inr');
        } else {
            if (window.activateWithdrawTab) window.activateWithdrawTab('usdt');
        }

        // 4. Set Network Dropdown & Asset Badge if crypto asset
        if (asset !== 'INR') {
            const netSelect = document.getElementById('withdrawNetworkSelect');
            const assetPill = document.getElementById('withdrawAssetPill');
            const config = assetDetailsMap[asset] || { name: asset };

            if (assetPill) {
                assetPill.textContent = `${config.name || asset} (${asset})`;
            }

            if (netSelect) {
                const opt = Array.from(netSelect.options).find(o => 
                    o.value.toUpperCase() === asset || o.textContent.toUpperCase().includes(asset)
                );
                if (opt) {
                    netSelect.value = opt.value;
                } else {
                    netSelect.value = asset;
                }
                netSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        // Scroll smoothly to withdraw form card
        document.getElementById('innerPanelWithdraw')?.scrollIntoView({ behavior: 'smooth' });
    }
});

if (closeCryptoAssetBtn) {
    closeCryptoAssetBtn.addEventListener('click', () => {
        if (cryptoAssetModal) cryptoAssetModal.hidden = true;
    });
}

function openCryptoAssetDepositModal(asset) {
    const config = assetDetailsMap[asset] || assetDetailsMap['SOL'];

    const iconEl = document.getElementById('cryptoAssetDepositIcon');
    const titleEl = document.getElementById('cryptoAssetDepositTitle');
    const subEl = document.getElementById('cryptoAssetDepositSub');
    const noticeEl = document.getElementById('cryptoAssetNoticeText');
    const amountLabelEl = document.getElementById('cryptoAssetAmountLabel');
    const warningBox = document.getElementById('cryptoAssetWarningBox');
    const depositBox = document.getElementById('cryptoAssetDepositBox');
    const warningNameEl = document.getElementById('cryptoAssetWarningAssetName');
    const ethMetamaskBox = document.getElementById('ethMetamaskBox');
    const metamaskStatusMsg = document.getElementById('metamaskStatusMsg');

    if (metamaskStatusMsg) metamaskStatusMsg.textContent = '';

    if (iconEl) {
        iconEl.textContent = config.icon;
        iconEl.style.background = config.color;
    }
    if (titleEl) titleEl.textContent = `Deposit ${asset}`;
    if (subEl) subEl.textContent = config.name;
    if (noticeEl) noticeEl.textContent = config.notice;

    let address = '';
    let minDeposit = 0;

    if (walletDataState.depositNetworks && walletDataState.depositNetworks.length > 0) {
        const netRow = walletDataState.depositNetworks.find(n => 
            (n.network || '').toUpperCase() === asset || 
            (n.network || '').toUpperCase() === config.defaultNetwork || 
            (n.label || '').toUpperCase().includes(asset)
        );
        if (netRow) {
            address = (netRow.address || '').trim();
            minDeposit = Number(netRow.minimum_deposit || 0);
        }
    }

    if (amountLabelEl) {
        amountLabelEl.textContent = minDeposit > 0 ? `Amount (${asset}) - Min ${minDeposit}` : `Amount (${asset})`;
    }

    // Update amount suffix span
    const amountSuffix = document.getElementById('cryptoAssetAmountSuffix');
    if (amountSuffix) amountSuffix.textContent = asset;

    const addrDisplay = document.getElementById('cryptoAssetAddressDisplay');
    const qrImg = document.getElementById('cryptoAssetQrImg');

    // Show / Hide MetaMask Box for ALL Crypto Assets
    const isMetamaskSupported = true;
    const contractWrap = document.getElementById('metamaskContractWrap');
    const contractInput = document.getElementById('metamaskTokenContractInput');
    const contractLabel = document.querySelector('#metamaskContractWrap label');

    if (ethMetamaskBox) ethMetamaskBox.style.display = 'block';

    const headerEl = document.getElementById('metamaskBoxHeader');
    const descEl = document.getElementById('metamaskBoxDesc');
    const btnTextEl = document.getElementById('metamaskBtnText');

    if (headerEl) headerEl.textContent = `MetaMask (${asset} Sepolia Testnet)`;
    if (descEl) descEl.textContent = `Deposit ${asset} directly using your connected MetaMask wallet. Send only ${asset} on ${config.name}. Other assets may be lost.`;
    if (btnTextEl) btnTextEl.textContent = `Pay with MetaMask (${asset})`;

    if (asset !== 'ETH') {
        if (contractWrap) contractWrap.classList.remove('hidden');
        if (contractLabel) contractLabel.textContent = `${asset} Token Contract Address (Sepolia ERC-20)`;
        if (contractInput) contractInput.value = ''; // Clear by default to prevent broken token amounts
    } else {
        if (contractWrap) contractWrap.classList.add('hidden');
        if (contractInput) contractInput.value = '';
    }

    if (!address) {
        address = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';
    }
    updateMetamaskConnectionUI();

    // Update notice label
    const noticeText = document.getElementById('cryptoAssetNoticeText');
    if (noticeText) noticeText.textContent = `${asset} Token Contract Address (${config.name})`;

    // If Admin has NOT set address yet -> Show Warning Box (Matching User Image)
    if (!address) {
        if (warningBox) {
            warningBox.style.display = 'block';
            if (warningNameEl) warningNameEl.textContent = `${asset} (${config.name})`;
        }
        if (depositBox) depositBox.style.display = 'none';
        if (addrDisplay) addrDisplay.textContent = '';
    } else {
        if (warningBox) warningBox.style.display = 'none';
        if (depositBox) depositBox.style.display = 'block';

        // Full address display
        if (addrDisplay) addrDisplay.textContent = address;

        // Short address for QR panel
        const shortEl = document.getElementById('cryptoAssetAddressShort');
        if (shortEl) shortEl.textContent = address.length > 20 ? `${address.substring(0, 14)}...${address.substring(address.length - 8)}` : address;

        // Full address for copy panel
        const fullEl = document.getElementById('cryptoAddrCopyFull');
        if (fullEl) fullEl.textContent = address;

        if (qrImg) {
            qrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(address)}`;
        }
    }

    // Update important notes list
    const notesList = document.getElementById('cryptoAssetNotesList');
    const minDep = minDeposit > 0 ? minDeposit : 0.01;
    if (notesList) {
        notesList.innerHTML = `
            <li>• Minimum deposit: ${minDep} ${asset}</li>
            <li>• Send only ${asset} on ${config.name}.</li>
            <li>• Deposits will be credited after admin verification.</li>
            <li>• Do not send from an exchange that doesn't support ${config.name} (${asset}).</li>
        `;
    }

    // Reset tabs to QR tab active
    const tabQr = document.getElementById('tabScanQr');
    const tabCopy = document.getElementById('tabCopyAddr');
    const qrPanel = document.getElementById('cryptoDepositQrPanel');
    const copyPanel = document.getElementById('cryptoDepositCopyPanel');
    if (tabQr) {
        tabQr.className = 'crypto-dep-tab active py-2 text-xs font-bold flex items-center justify-center gap-1.5 transition-all border-b-2 border-violet-500 text-violet-300 bg-violet-500/10 cursor-pointer';
        tabCopy.className = 'crypto-dep-tab py-2 text-xs font-bold flex items-center justify-center gap-1.5 transition-all border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer';
    }
    if (qrPanel) qrPanel.classList.remove('hidden');
    if (copyPanel) copyPanel.classList.add('hidden');

    if (cryptoAssetModal) cryptoAssetModal.hidden = false;
}

// Tab switching for crypto deposit modal
document.addEventListener('click', function(e) {
    const tabQr = document.getElementById('tabScanQr');
    const tabCopy = document.getElementById('tabCopyAddr');
    const qrPanel = document.getElementById('cryptoDepositQrPanel');
    const copyPanel = document.getElementById('cryptoDepositCopyPanel');

    if (e.target === tabQr || tabQr?.contains(e.target)) {
        if (tabQr) tabQr.className = 'crypto-dep-tab active py-2 text-xs font-bold flex items-center justify-center gap-1.5 transition-all border-b-2 border-violet-500 text-violet-300 bg-violet-500/10 cursor-pointer';
        if (tabCopy) tabCopy.className = 'crypto-dep-tab py-2 text-xs font-bold flex items-center justify-center gap-1.5 transition-all border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer';
        if (qrPanel) qrPanel.classList.remove('hidden');
        if (copyPanel) copyPanel.classList.add('hidden');
    }

    if (e.target === tabCopy || tabCopy?.contains(e.target)) {
        if (tabCopy) tabCopy.className = 'crypto-dep-tab active py-2 text-xs font-bold flex items-center justify-center gap-1.5 transition-all border-b-2 border-violet-500 text-violet-300 bg-violet-500/10 cursor-pointer';
        if (tabQr) tabQr.className = 'crypto-dep-tab py-2 text-xs font-bold flex items-center justify-center gap-1.5 transition-all border-b-2 border-transparent text-slate-400 hover:text-white cursor-pointer';
        if (copyPanel) copyPanel.classList.remove('hidden');
        if (qrPanel) qrPanel.classList.add('hidden');
    }

    // QR panel copy button
    const qrCopyBtn = document.getElementById('copyQrPanelAddrBtn');
    if (e.target === qrCopyBtn || qrCopyBtn?.contains(e.target)) {
        const addr = document.getElementById('cryptoAssetAddressDisplay')?.textContent || '';
        if (addr) {
            navigator.clipboard?.writeText(addr).then(() => showWalletToast('Address copied!', 'success')).catch(() => {});
        }
    }

    // Copy panel copy button
    const copyPanelBtn = document.getElementById('copyAddrPanelBtn');
    if (e.target === copyPanelBtn || copyPanelBtn?.contains(e.target)) {
        const addr = document.getElementById('cryptoAssetAddressDisplay')?.textContent || '';
        if (addr) {
            navigator.clipboard?.writeText(addr).then(() => showWalletToast('Address copied!', 'success')).catch(() => {});
        }
    }

    // Paste TX hash button
    const pasteTxBtn = document.getElementById('pasteTxHashBtn');
    if (e.target === pasteTxBtn || pasteTxBtn?.contains(e.target)) {
        navigator.clipboard?.readText().then(text => {
            const inp = document.getElementById('cryptoAssetTxHashInput');
            if (inp && text) { inp.value = text.trim(); }
        }).catch(() => showWalletToast('Unable to paste. Please paste manually.', 'info'));
    }
});



// Helper to convert float amount to exact Wei hex string safely
function toWeiHex(amount, decimals = 18) {
    const str = (amount || 0).toString();
    const parts = str.split('.');
    let integerPart = parts[0] || '0';
    let decimalPart = parts[1] || '';
    if (decimalPart.length > decimals) {
        decimalPart = decimalPart.substring(0, decimals);
    } else {
        decimalPart = decimalPart.padEnd(decimals, '0');
    }
    const rawStr = (integerPart + decimalPart).replace(/^0+/, '') || '0';
    const bigVal = BigInt(rawStr);
    return '0x' + bigVal.toString(16);
}

// Helper to encode ERC-20 transfer(address,uint256) data for custom tokens
function encodeERC20TransferData(recipientAddress, amountTokens, decimals = 18) {
    const methodId = 'a9059cbb';
    const cleanAddr = (recipientAddress || '').replace(/^0x/i, '').toLowerCase();
    const paddedAddr = cleanAddr.padStart(64, '0');

    const str = (amountTokens || 0).toString();
    const parts = str.split('.');
    let integerPart = parts[0] || '0';
    let decimalPart = parts[1] || '';
    if (decimalPart.length > decimals) {
        decimalPart = decimalPart.substring(0, decimals);
    } else {
        decimalPart = decimalPart.padEnd(decimals, '0');
    }
    const rawStr = (integerPart + decimalPart).replace(/^0+/, '') || '0';
    const bigVal = BigInt(rawStr);
    const paddedAmount = bigVal.toString(16).padStart(64, '0');

    return '0x' + methodId + paddedAddr + paddedAmount;
}

// Update MetaMask Connection Pill UI
async function updateMetamaskConnectionUI() {
    const btnConnect = document.getElementById('btnConnectMetamask');
    const connectedInfo = document.getElementById('metamaskConnectedInfo');
    const connectedAddrEl = document.getElementById('metamaskConnectedAddr');

    if (typeof window.ethereum !== 'undefined') {
        try {
            const accounts = await window.ethereum.request({ method: 'eth_accounts' });
            if (accounts && accounts.length > 0) {
                const addr = accounts[0];
                if (btnConnect) btnConnect.style.display = 'none';
                if (connectedInfo) {
                    connectedInfo.style.display = 'inline-flex';
                    if (connectedAddrEl) connectedAddrEl.textContent = `${addr.substring(0, 6)}...${addr.substring(addr.length - 4)}`;
                }
                return;
            }
        } catch (e) {}
    }

    if (btnConnect) btnConnect.style.display = 'inline-flex';
    if (connectedInfo) connectedInfo.style.display = 'none';
}

// MetaMask Connect Button Handler
const btnConnectMetamask = document.getElementById('btnConnectMetamask');
if (btnConnectMetamask) {
    btnConnectMetamask.addEventListener('click', async () => {
        if (typeof window.ethereum === 'undefined') {
            showWalletToast('MetaMask extension is not installed.', 'error');
            return;
        }
        try {
            const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
            if (accounts && accounts.length) {
                showWalletToast('MetaMask wallet connected successfully!', 'success');
                updateMetamaskConnectionUI();
            }
        } catch (err) {
            showWalletToast(err.message || 'MetaMask connection canceled.', 'error');
        }
    });
}

// MetaMask Deposit Handler (Support for ALL Crypto Assets)
const btnPayMetamask = document.getElementById('btnPayWithMetamask');
if (btnPayMetamask) {
    btnPayMetamask.addEventListener('click', async () => {
        const statusMsg = document.getElementById('metamaskStatusMsg');
        const amountInput = document.getElementById('cryptoAssetAmountInput');
        const txHashInput = document.getElementById('cryptoAssetTxHashInput');
        const addrDisplay = document.getElementById('cryptoAssetAddressDisplay');

        const titleText = document.getElementById('cryptoAssetDepositTitle')?.textContent || 'Deposit';
        const currentAsset = titleText.replace('Deposit', '').trim().toUpperCase() || 'ETH';

        if (typeof window.ethereum === 'undefined') {
            if (statusMsg) {
                statusMsg.style.color = '#ef4444';
                statusMsg.innerHTML = 'MetaMask is not installed. <a href="https://metamask.io/download/" target="_blank" style="color: #38bdf8; text-decoration: underline;">Install MetaMask</a>';
            }
            showWalletToast('MetaMask extension is not installed in your browser.', 'error');
            return;
        }

        const amountVal = parseFloat(amountInput?.value || '0');
        if (!amountVal || amountVal <= 0) {
            showWalletToast(`Please enter ${currentAsset} deposit amount first.`, 'error');
            if (amountInput) amountInput.focus();
            return;
        }

        try {
            if (statusMsg) {
                statusMsg.style.color = '#f6851b';
                statusMsg.textContent = 'Connecting to MetaMask...';
            }

            // 1. Connect MetaMask Account
            const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
            if (!accounts || !accounts.length) {
                showWalletToast('No MetaMask account selected.', 'error');
                return;
            }
            const fromAccount = accounts[0];
            updateMetamaskConnectionUI();

            // 2. Switch/Add Sepolia Testnet (Chain ID 0xaa36a7 = 11155111)
            if (statusMsg) statusMsg.textContent = 'Switching to Sepolia Testnet...';
            try {
                await window.ethereum.request({
                    method: 'wallet_switchEthereumChain',
                    params: [{ chainId: '0xaa36a7' }],
                });
            } catch (switchError) {
                if (switchError.code === 4902) {
                    await window.ethereum.request({
                        method: 'wallet_addEthereumChain',
                        params: [{
                            chainId: '0xaa36a7',
                            chainName: 'Sepolia Test Network',
                            nativeCurrency: { name: 'Sepolia ETH', symbol: 'SEP', decimals: 18 },
                            rpcUrls: ['https://rpc.ankr.com/eth_sepolia', 'https://sepolia.drpc.org', 'https://rpc.sepolia.org'],
                            blockExplorerUrls: ['https://sepolia.etherscan.io']
                        }],
                    });
                } else {
                    throw switchError;
                }
            }

            // 3. Send Transaction
            if (statusMsg) statusMsg.textContent = 'Please confirm transaction in MetaMask window...';

            let rawAddr = (addrDisplay?.textContent || '').trim();
            let targetEvmAddr = rawAddr;

            // Ensure valid 0x EVM address format for recipient
            if (!targetEvmAddr || !targetEvmAddr.startsWith('0x') || targetEvmAddr.length !== 42 || targetEvmAddr.includes('Loading')) {
                targetEvmAddr = '0x71C7656EC7ab88b098defB751B7401B5f6d8976F';
            }

            let txHash;
            if (currentAsset === 'ETH') {
                // Native SepoliaETH Transfer for ETH
                const weiHex = toWeiHex(amountVal, 18);
                txHash = await window.ethereum.request({
                    method: 'eth_sendTransaction',
                    params: [{
                        from: fromAccount,
                        to: targetEvmAddr,
                        value: weiHex
                    }],
                });
            } else {
                // For custom ERC-20 crypto tokens (BTC, USDC, USDT, SOL, XRP, ADA, BNB)
                const contractInput = document.getElementById('metamaskTokenContractInput');
                let tokenContractAddr = (contractInput?.value || '').trim();

                let contractSuccess = false;
                if (tokenContractAddr && tokenContractAddr.startsWith('0x') && tokenContractAddr.length === 42) {
                    try {
                        // Remix ERC-20 tokens default to 18 decimals
                        const transferData = encodeERC20TransferData(targetEvmAddr, amountVal, 18);
                        txHash = await window.ethereum.request({
                            method: 'eth_sendTransaction',
                            params: [{
                                from: fromAccount,
                                to: tokenContractAddr,
                                value: '0x0',
                                data: transferData
                            }],
                        });
                        contractSuccess = true;
                    } catch (cErr) {
                        console.warn('Custom ERC-20 contract call failed, falling back to direct Sepolia transaction:', cErr);
                    }
                }

                if (!contractSuccess) {
                    // Send native Sepolia testnet transaction to deposit address
                    const weiHex = toWeiHex(amountVal, 18);
                    txHash = await window.ethereum.request({
                        method: 'eth_sendTransaction',
                        params: [{
                            from: fromAccount,
                            to: targetEvmAddr,
                            value: weiHex
                        }],
                    });
                }
            }

            if (statusMsg) {
                statusMsg.style.color = '#22c55e';
                statusMsg.textContent = `Transaction submitted! TxHash: ${txHash.substring(0, 12)}...`;
            }

            if (txHashInput) txHashInput.value = txHash;
            if (addrDisplay && (!addrDisplay.textContent || addrDisplay.textContent.includes('No '))) {
                addrDisplay.textContent = targetEvmAddr;
            }

            showWalletToast(`MetaMask ${currentAsset} Transaction Sent! TxID: ${txHash}`, 'success');

            // Auto-submit deposit form
            setTimeout(() => {
                if (cryptoAssetForm) cryptoAssetForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
            }, 600);

        } catch (err) {
            console.error('MetaMask Error:', err);
            if (statusMsg) {
                statusMsg.style.color = '#ef4444';
                statusMsg.textContent = err.message || 'MetaMask transaction canceled.';
            }
            showWalletToast(err.message || 'MetaMask transaction failed or canceled.', 'error');
        }
    });
}

if (copyCryptoAssetBtn) {
    copyCryptoAssetBtn.addEventListener('click', () => {
        const addrDisplay = document.getElementById('cryptoAssetAddressDisplay');
        const text = addrDisplay ? addrDisplay.textContent : '';
        if (text && text !== 'Loading address...') {
            navigator.clipboard.writeText(text).then(() => {
                showWalletToast('Deposit address copied to clipboard!', 'success');
            }).catch(() => {
                showWalletToast('Copied to clipboard!', 'success');
            });
        }
    });
}

if (cryptoAssetForm) {
    cryptoAssetForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = cryptoAssetForm.querySelector('button[type="submit"]');

        const titleText = document.getElementById('cryptoAssetDepositTitle')?.textContent || 'Deposit';
        const asset = titleText.replace('Deposit', '').trim().toUpperCase() || 'ETH';
        const amountVal = cryptoAssetForm.querySelector('input[name="amount"]')?.value || '0';
        const txHash = (cryptoAssetForm.querySelector('input[name="tx_hash"]')?.value || '').trim();
        const displayedAddress = (document.getElementById('cryptoAssetAddressDisplay')?.textContent || '').trim();

        if (!displayedAddress) {
            showWalletToast(`Deposit disabled. No ${asset} deposit address configured by admin.`, 'error');
            return;
        }

        if (!txHash) {
            showWalletToast('Please paste the transaction hash or company deposit address.', 'error');
            return;
        }

        if (displayedAddress && displayedAddress !== 'Loading address...') {
            const isMatch = txHash.toLowerCase() === displayedAddress.toLowerCase();
            const isValidTxLength = txHash.length >= 20;

            if (!isMatch && !isValidTxLength) {
                showWalletToast(`Invalid TX Hash or Address! Please paste the company deposit address (${displayedAddress.substring(0, 8)}...) or a valid TxID.`, 'error');
                return;
            }
        }

        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting...'; }

        try {
            const res = await authFetch('/api/wallet/demo-credit', {
                method: 'POST',
                body: JSON.stringify({
                    amount: amountVal,
                    network: asset,
                    currency: asset,
                    tx_hash: txHash,
                    note: `${asset} Crypto Deposit Submission: ${amountVal} ${asset}. TxID: ${txHash}`
                })
            });
            showWalletToast(res.message || `${asset} deposit submitted for admin approval!`, 'success');
            cryptoAssetForm.reset();
            if (cryptoAssetModal) cryptoAssetModal.hidden = true;
            await loadWalletConfig();
            if (typeof loadLedgerHistory === 'function') {
                await loadLedgerHistory(1);
            }
        } catch (err) {
            showWalletToast(err.message || `Deposit submission received. Waiting for admin approval.`, 'info');
            cryptoAssetForm.reset();
            if (cryptoAssetModal) cryptoAssetModal.hidden = true;
        } finally {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit for approval'; }
        }
    });
}

// ==========================================
// UPGRADED CONVERT / EXCHANGE MODAL LOGIC (BUY & SELL)
// ==========================================
let convertModalState = {
    action: 'buy', // 'buy' | 'sell'
    cryptoAsset: 'BTC',
    rates: {
        BTC: 63628.00,
        ETH: 2640.00,
        SOL: 185.00,
        USDT: 1.00,
        USDC: 1.00,
    }
};

const convertModalBackdrop = document.getElementById('exchangeModalBackdrop');
const convertForm = document.getElementById('exchangeForm');
const closeConvertBtn = document.getElementById('closeExchangeModalBtn');
const tabBuyBtn = document.getElementById('tabExchangeBuy');
const tabSellBtn = document.getElementById('tabExchangeSell');
const swapDirectionBtn = document.getElementById('exchangeSwapDirectionBtn');

const fromAmountInput = document.getElementById('exchangeFromAmount');
const toAmountInput = document.getElementById('exchangeToAmount');
const fromUsdBadge = document.getElementById('exchangeFromUsdBadge');
const fromCryptoSelect = document.getElementById('exchangeFromCryptoSelect');
const toUsdBadge = document.getElementById('exchangeToUsdBadge');
const toCryptoSelect = document.getElementById('exchangeToCryptoSelect');

const fromAvailVal = document.getElementById('exchangeFromAvailVal');
const liveRateSubheader = document.getElementById('exchangeLiveRateSubheader');
const summaryRateDisplay = document.getElementById('summaryRateDisplay');
const summaryPaySellLabel = document.getElementById('summaryPaySellLabel');
const summaryPaySellVal = document.getElementById('summaryPaySellVal');
const summaryReceiveVal = document.getElementById('summaryReceiveVal');
const summaryPairVal = document.getElementById('summaryPairVal');
const convertSubmitBtn = document.getElementById('exchangeSubmitBtn');

async function fetchConvertLiveRates() {
    // Uses real-time websocket & REST stream from liveCryptoPricesUsd
    updateConvertUI();
}

function updateConvertUI() {
    const isBuy = convertModalState.action === 'buy';
    const coin = (convertModalState.cryptoAsset || 'BTC').toUpperCase();
    
    const cur = (window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD')).toUpperCase();
    const isINR = (cur === 'INR');
    const usdInrRate = (typeof inrState !== 'undefined' && inrState.usdtInrRate > 0) ? inrState.usdtInrRate : (window.KRYVEX_USD_INR_RATE || 85.25);

    const usdRate = (typeof liveCryptoPricesUsd !== 'undefined' && liveCryptoPricesUsd[coin]) ? liveCryptoPricesUsd[coin] : (convertModalState.rates[coin] || 1);
    const rate = isINR ? (usdRate * usdInrRate) : usdRate;
    const currSymbol = isINR ? '₹' : '$';
    const currCode = isINR ? 'INR' : 'USD';

    const userUsd = (window.latestWalletBalances && window.latestWalletBalances.usd) || walletRuleState.demoMainBalance || 0;
    const userFiatAvail = isINR ? (userUsd * usdInrRate) : userUsd;
    const userCrypto = (window.latestWalletBalances && window.latestWalletBalances.crypto && window.latestWalletBalances.crypto[coin]) || 0;

    // Update Tab Styles via class names
    if (tabBuyBtn) {
        tabBuyBtn.className = `convert-tab-btn ${isBuy ? 'is-buy' : 'is-inactive'}`;
    }
    if (tabSellBtn) {
        tabSellBtn.className = `convert-tab-btn ${isBuy ? 'is-inactive' : 'is-sell'}`;
    }

    // Hidden input
    const actionInput = document.getElementById('exchangeActionInput');
    if (actionInput) actionInput.value = convertModalState.action;

    // Update Selectors & Badges
    if (fromUsdBadge) fromUsdBadge.textContent = currCode;
    if (toUsdBadge) toUsdBadge.textContent = currCode;

    if (isBuy) {
        if (fromUsdBadge) fromUsdBadge.style.display = 'inline-block';
        if (fromCryptoSelect) fromCryptoSelect.style.display = 'none';

        if (toCryptoSelect) {
            toCryptoSelect.style.display = 'inline-block';
            toCryptoSelect.value = coin;
        }
        if (toUsdBadge) toUsdBadge.style.display = 'none';

        if (fromAvailVal) fromAvailVal.textContent = `${currSymbol}${userFiatAvail.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        if (summaryPaySellLabel) summaryPaySellLabel.textContent = 'You pay';
        if (summaryPairVal) summaryPairVal.textContent = `${currCode} → ${coin}`;

        if (convertSubmitBtn) {
            convertSubmitBtn.textContent = `Buy ${coin}`;
            convertSubmitBtn.className = 'convert-submit-btn btn-buy';
        }
    } else {
        if (fromCryptoSelect) {
            fromCryptoSelect.style.display = 'inline-block';
            fromCryptoSelect.value = coin;
        }
        if (fromUsdBadge) fromUsdBadge.style.display = 'none';

        if (toUsdBadge) toUsdBadge.style.display = 'inline-block';
        if (toCryptoSelect) toCryptoSelect.style.display = 'none';

        if (fromAvailVal) fromAvailVal.textContent = `${userCrypto} ${coin}`;
        if (summaryPaySellLabel) summaryPaySellLabel.textContent = 'You sell';
        if (summaryPairVal) summaryPairVal.textContent = `${coin} → ${currCode}`;

        if (convertSubmitBtn) {
            convertSubmitBtn.textContent = `Sell ${coin}`;
            convertSubmitBtn.className = 'convert-submit-btn btn-sell';
        }
    }

    // Rate Subheader & Summary Rate
    let formattedRate = '';
    if (isINR) {
        formattedRate = rate >= 100 
            ? '₹' + rate.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '₹' + rate.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
    } else {
        formattedRate = rate >= 100 
            ? '$' + rate.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
            : '$' + rate.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
    }
    if (liveRateSubheader) liveRateSubheader.textContent = `1 ${coin} ≈ ${formattedRate}`;
    if (summaryRateDisplay) summaryRateDisplay.textContent = `1 ${coin} = ${formattedRate}`;

    // Recalculate Amounts
    calculateConvertAmounts();
}

function calculateConvertAmounts(source = 'from') {
    const isBuy = convertModalState.action === 'buy';
    const coin = (convertModalState.cryptoAsset || 'BTC').toUpperCase();
    
    const cur = (window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD')).toUpperCase();
    const isINR = (cur === 'INR');
    const usdInrRate = (typeof inrState !== 'undefined' && inrState.usdtInrRate > 0) ? inrState.usdtInrRate : (window.KRYVEX_USD_INR_RATE || 85.25);

    const usdRate = (typeof liveCryptoPricesUsd !== 'undefined' && liveCryptoPricesUsd[coin]) ? liveCryptoPricesUsd[coin] : (convertModalState.rates[coin] || 1);
    const rate = isINR ? (usdRate * usdInrRate) : usdRate;
    const currSymbol = isINR ? '₹' : '$';

    if (source === 'from') {
        const fromVal = parseFloat(fromAmountInput?.value || '0');
        if (!fromVal || fromVal <= 0) {
            if (toAmountInput) toAmountInput.value = '';
            if (summaryPaySellVal) summaryPaySellVal.textContent = isBuy ? `${currSymbol}0.00` : `0 ${coin}`;
            if (summaryReceiveVal) summaryReceiveVal.textContent = isBuy ? `0 ${coin}` : `${currSymbol}0.00`;
            return;
        }

        if (isBuy) {
            // From is Fiat (USD/INR), To is Crypto
            const cryptoAmt = fromVal / rate;
            if (toAmountInput) toAmountInput.value = cryptoAmt < 0.0001 ? cryptoAmt.toFixed(8) : cryptoAmt.toFixed(6);
            if (summaryPaySellVal) summaryPaySellVal.textContent = `${currSymbol}${fromVal.toFixed(2)}`;
            if (summaryReceiveVal) summaryReceiveVal.textContent = `${cryptoAmt < 0.0001 ? cryptoAmt.toFixed(8) : cryptoAmt.toFixed(6)} ${coin}`;
        } else {
            // From is Crypto, To is Fiat (USD/INR)
            const fiatAmt = fromVal * rate;
            if (toAmountInput) toAmountInput.value = fiatAmt.toFixed(2);
            if (summaryPaySellVal) summaryPaySellVal.textContent = `${fromVal} ${coin}`;
            if (summaryReceiveVal) summaryReceiveVal.textContent = `${currSymbol}${fiatAmt.toFixed(2)}`;
        }
    } else {
        const toVal = parseFloat(toAmountInput?.value || '0');
        if (!toVal || toVal <= 0) {
            if (fromAmountInput) fromAmountInput.value = '';
            if (summaryPaySellVal) summaryPaySellVal.textContent = isBuy ? `${currSymbol}0.00` : `0 ${coin}`;
            if (summaryReceiveVal) summaryReceiveVal.textContent = isBuy ? `0 ${coin}` : `${currSymbol}0.00`;
            return;
        }

        if (isBuy) {
            // To is Crypto, calculate Fiat needed
            const fiatAmt = toVal * rate;
            if (fromAmountInput) fromAmountInput.value = fiatAmt.toFixed(2);
            if (summaryPaySellVal) summaryPaySellVal.textContent = `${currSymbol}${fiatAmt.toFixed(2)}`;
            if (summaryReceiveVal) summaryReceiveVal.textContent = `${toVal} ${coin}`;
        } else {
            // To is Fiat, calculate Crypto needed
            const cryptoAmt = toVal / rate;
            if (fromAmountInput) fromAmountInput.value = cryptoAmt < 0.0001 ? cryptoAmt.toFixed(8) : cryptoAmt.toFixed(6);
            if (summaryPaySellVal) summaryPaySellVal.textContent = `${cryptoAmt < 0.0001 ? cryptoAmt.toFixed(8) : cryptoAmt.toFixed(6)} ${coin}`;
            if (summaryReceiveVal) summaryReceiveVal.textContent = `${currSymbol}${toVal.toFixed(2)}`;
        }
    }
}

// Bind Open Exchange Buttons
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action-exchange], [data-action-exchange-asset], [data-action-exchange-crypto]');
    if (btn) {
        e.preventDefault();
        e.stopPropagation();
        const rawSymbol = btn.getAttribute('data-action-exchange-asset') ||
                          btn.getAttribute('data-action-exchange-crypto') ||
                          (btn.closest('tr') ? btn.closest('tr').querySelector('.dharma-asset-info strong')?.textContent?.trim() : null);

        if (convertModalBackdrop) {
            convertModalBackdrop.hidden = true;
            convertModalBackdrop.style.display = 'none';
        }

        if (typeof window.switchMainTab === 'function') {
            window.switchMainTab('exchange', true);
        }

        if (rawSymbol && rawSymbol.toUpperCase() !== 'USDT' && rawSymbol.toUpperCase() !== 'USD') {
            if (typeof window.setSwapFromAsset === 'function') {
                window.setSwapFromAsset(rawSymbol);
            }
        } else {
            if (typeof window.resetSwapToDirectMode === 'function') {
                window.resetSwapToDirectMode();
            }
        }
    }
});

if (closeConvertBtn) {
    closeConvertBtn.addEventListener('click', () => {
        if (convertModalBackdrop) convertModalBackdrop.hidden = true;
    });
}

if (tabBuyBtn) {
    tabBuyBtn.addEventListener('click', () => {
        convertModalState.action = 'buy';
        updateConvertUI();
    });
}

if (tabSellBtn) {
    tabSellBtn.addEventListener('click', () => {
        convertModalState.action = 'sell';
        updateConvertUI();
    });
}

if (swapDirectionBtn) {
    swapDirectionBtn.addEventListener('click', () => {
        convertModalState.action = convertModalState.action === 'buy' ? 'sell' : 'buy';
        updateConvertUI();
    });
}

if (fromCryptoSelect) {
    fromCryptoSelect.addEventListener('change', async (e) => {
        convertModalState.cryptoAsset = e.target.value.toUpperCase();
        await fetchConvertLiveRates();
        updateConvertUI();
    });
}

if (toCryptoSelect) {
    toCryptoSelect.addEventListener('change', async (e) => {
        convertModalState.cryptoAsset = e.target.value.toUpperCase();
        await fetchConvertLiveRates();
        updateConvertUI();
    });
}

if (fromAmountInput) {
    fromAmountInput.addEventListener('input', () => calculateConvertAmounts('from'));
}

if (toAmountInput) {
    toAmountInput.addEventListener('input', () => calculateConvertAmounts('to'));
}

if (convertForm) {
    convertForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = convertForm.querySelector('button[type="submit"]');
        const action = convertModalState.action;
        const coin = convertModalState.cryptoAsset;
        const amountVal = fromAmountInput?.value || '0';

        if (!parseFloat(amountVal) || parseFloat(amountVal) <= 0) {
            showWalletToast('Please enter a valid amount.', 'error');
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        }

        try {
            const res = await authFetch('/api/wallet/crypto-exchange', {
                method: 'POST',
                body: JSON.stringify({
                    action: action,
                    crypto_currency: coin,
                    amount: amountVal,
                })
            });

            showWalletToast(res.message || `Exchange completed successfully!`, 'success');
            convertForm.reset();
            if (convertModalBackdrop) convertModalBackdrop.hidden = true;
            await loadWalletConfig();
            await loadLedgerHistory(1);
        } catch (err) {
            showWalletToast(err.message || 'Exchange failed. Please try again.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = action === 'buy' ? `Buy ${coin}` : `Sell ${coin}`;
            }
        }
    });
}

// ==========================================
// USER TO USER INTERNAL FUND TRANSFER LOGIC
// ==========================================
let currentInternalAsset = 'BTC';
let cameraStream = null;
let recipientLookupTimer = null;

const internalModalBackdrop = document.getElementById('internalTransferModalBackdrop');
const closeInternalModalBtn = document.getElementById('closeInternalTransferModalBtn');
const internalTabReceiveBtn = document.getElementById('internalTabReceiveBtn');
const internalTabSendBtn = document.getElementById('internalTabSendBtn');
const internalPanelReceive = document.getElementById('internalPanelReceive');
const internalPanelSend = document.getElementById('internalPanelSend');

const internalMyAddressDisplay = document.getElementById('internalMyAddressDisplay');
const internalMyQrImg = document.getElementById('internalMyQrImg');
const copyInternalMyAddressBtn = document.getElementById('copyInternalMyAddressBtn');
const shareInternalMyAddressBtn = document.getElementById('shareInternalMyAddressBtn');

const internalRecipientInput = document.getElementById('internalRecipientAddressInput');
const internalRecipientVerifyCard = document.getElementById('internalRecipientVerifyCard');
const internalRecipientNameVal = document.getElementById('internalRecipientNameVal');
const internalRecipientEmailVal = document.getElementById('internalRecipientEmailVal');
const internalSendAmountInput = document.getElementById('internalSendAmountInput');
const internalSendMaxBtn = document.getElementById('internalSendMaxBtn');
const internalSendNoteInput = document.getElementById('internalSendNoteInput');
const internalTransferForm = document.getElementById('internalTransferForm');

const qrScannerModalBackdrop = document.getElementById('qrScannerModalBackdrop');
const closeQrScannerModalBtn = document.getElementById('closeQrScannerModalBtn');
const btnTriggerCameraScan = document.getElementById('btnTriggerCameraScan');
const qrScannerVideo = document.getElementById('qrScannerVideo');

// 1. Listen for click on any data-action-internal-asset or data-action-internal button
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action-internal-asset], [data-action-internal]');
    if (btn) {
        e.preventDefault();
        const asset = (btn.getAttribute('data-action-internal-asset') || btn.getAttribute('data-currency') || 'BTC').toUpperCase();
        openInternalTransferModal(asset, 'receive');
    }
});

function resetInternalTransferForm() {
    if (internalTransferForm) {
        internalTransferForm.reset();
    }
    if (internalRecipientInput) {
        internalRecipientInput.value = '';
    }
    if (internalSendAmountInput) {
        internalSendAmountInput.value = '';
    }
    if (internalSendNoteInput) {
        internalSendNoteInput.value = '';
    }
    if (internalRecipientVerifyCard) {
        internalRecipientVerifyCard.style.display = 'none';
    }
    const errCard = document.getElementById('internalAddressErrorCard');
    if (errCard) {
        errCard.style.display = 'none';
        errCard.textContent = '';
    }
    if (internalRecipientNameVal) {
        internalRecipientNameVal.textContent = '-';
    }
    if (internalRecipientEmailVal) {
        internalRecipientEmailVal.textContent = '-';
    }
}

function validateAddressCurrencyMatch(addr) {
    const errCard = document.getElementById('internalAddressErrorCard');
    if (!addr || !addr.startsWith('KVX-')) {
        if (errCard) { errCard.style.display = 'none'; errCard.textContent = ''; }
        return true;
    }

    const parts = addr.split('-');
    const addrCurrency = (parts.length >= 2 ? parts[1] : '').toUpperCase();

    if (addrCurrency && addrCurrency !== currentInternalAsset) {
        if (internalRecipientVerifyCard) internalRecipientVerifyCard.style.display = 'none';
        const msg = `⚠️ Currency Mismatch: You are in ${currentInternalAsset} transfer modal, but entered a ${addrCurrency} address! Please enter a valid ${currentInternalAsset} internal address.`;
        if (errCard) {
            errCard.textContent = msg;
            errCard.style.display = 'block';
        }
        showWalletToast(`Invalid Address: This transfer is for ${currentInternalAsset}, but the address is for ${addrCurrency}!`, 'error');
        return false;
    }

    if (errCard) { errCard.style.display = 'none'; errCard.textContent = ''; }
    return true;
}

// 2. Open Internal Transfer Modal
async function openInternalTransferModal(asset = 'BTC', defaultTab = 'receive', prefillAddress = '') {
    currentInternalAsset = asset.toUpperCase();

    // Reset inputs & verified card from previous sessions
    resetInternalTransferForm();

    const titleEl = document.getElementById('internalAssetModalTitle');
    const subEl = document.getElementById('internalAssetModalSub');
    const iconEl = document.getElementById('internalAssetModalIcon');
    const amountLabel = document.getElementById('internalSendAmountLabel');

    if (titleEl) titleEl.textContent = `Internal Transfer — ${currentInternalAsset}`;
    if (subEl) subEl.textContent = `User to User (${currentInternalAsset}) • 0.5% Fee`;
    if (amountLabel) amountLabel.textContent = `Amount (${currentInternalAsset})`;
    if (iconEl) iconEl.textContent = currentInternalAsset.charAt(0);

    // Get user's unique address for this asset
    let myAddresses = (walletDataState && walletDataState.internal_addresses) ? walletDataState.internal_addresses : {};
    let myAddr = myAddresses[currentInternalAsset];

    if (!myAddr || myAddr.includes('PENDING')) {
        try {
            const res = await authFetch('/api/wallet');
            if (res && res.wallet) {
                applyWalletConfigPayload(res);
                myAddresses = res.wallet.internal_addresses || {};
                myAddr = myAddresses[currentInternalAsset];
            }
        } catch (e) {}
    }

    if (!myAddr) {
        myAddr = `KVX-${currentInternalAsset}-PENDING`;
    }

    if (internalMyAddressDisplay) internalMyAddressDisplay.textContent = myAddr;

    // Generate scannable QR Code URL containing deep link
    if (internalMyQrImg) {
        const payUrl = `${window.location.origin}/wallet?internal_pay=${encodeURIComponent(myAddr)}`;
        internalMyQrImg.src = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(payUrl)}`;
    }

    if (prefillAddress && internalRecipientInput) {
        internalRecipientInput.value = prefillAddress;
        lookupRecipientAddress(prefillAddress);
    }

    switchInternalTab(defaultTab);
    if (internalModalBackdrop) internalModalBackdrop.hidden = false;
}
window.openInternalTransferModal = openInternalTransferModal;

// 3. Tab Switcher Logic
function switchInternalTab(tab = 'receive') {
    if (tab === 'receive') {
        if (internalPanelReceive) internalPanelReceive.style.display = 'block';
        if (internalPanelSend) internalPanelSend.style.display = 'none';
        if (internalTabReceiveBtn) {
            internalTabReceiveBtn.style.background = '#a855f7';
            internalTabReceiveBtn.style.color = '#ffffff';
        }
        if (internalTabSendBtn) {
            internalTabSendBtn.style.background = 'transparent';
            internalTabSendBtn.style.color = 'rgba(255, 255, 255, 0.6)';
        }
    } else {
        if (internalPanelReceive) internalPanelReceive.style.display = 'none';
        if (internalPanelSend) internalPanelSend.style.display = 'block';
        if (internalTabSendBtn) {
            internalTabSendBtn.style.background = '#a855f7';
            internalTabSendBtn.style.color = '#ffffff';
        }
        if (internalTabReceiveBtn) {
            internalTabReceiveBtn.style.background = 'transparent';
            internalTabReceiveBtn.style.color = 'rgba(255, 255, 255, 0.6)';
        }
    }
}

if (internalTabReceiveBtn) internalTabReceiveBtn.addEventListener('click', () => switchInternalTab('receive'));
if (internalTabSendBtn) internalTabSendBtn.addEventListener('click', () => switchInternalTab('send'));
if (closeInternalModalBtn) closeInternalModalBtn.addEventListener('click', () => {
    resetInternalTransferForm();
    if (internalModalBackdrop) internalModalBackdrop.hidden = true;
});

if (internalModalBackdrop) {
    internalModalBackdrop.addEventListener('click', (e) => {
        if (e.target === internalModalBackdrop) {
            resetInternalTransferForm();
            internalModalBackdrop.hidden = true;
        }
    });
}

// 4. Copy & Share Address
if (copyInternalMyAddressBtn) {
    copyInternalMyAddressBtn.addEventListener('click', () => {
        const text = internalMyAddressDisplay?.textContent || '';
        if (text && navigator.clipboard) {
            navigator.clipboard.writeText(text);
            showWalletToast('Address copied to clipboard!', 'success');
        }
    });
}

if (shareInternalMyAddressBtn) {
    shareInternalMyAddressBtn.addEventListener('click', () => {
        const text = internalMyAddressDisplay?.textContent || '';
        const payUrl = `${window.location.origin}/wallet?internal_pay=${encodeURIComponent(text)}`;
        if (navigator.share) {
            navigator.share({
                title: `Kryvex Internal Address (${currentInternalAsset})`,
                text: `Send ${currentInternalAsset} to my Kryvex address: ${text}`,
                url: payUrl,
            }).catch(() => {});
        } else if (navigator.clipboard) {
            navigator.clipboard.writeText(payUrl);
            showWalletToast('Payment link copied to clipboard!', 'success');
        }
    });
}

// 5. Live Recipient Address Verification Lookup
if (internalRecipientInput) {
    internalRecipientInput.addEventListener('input', (e) => {
        const val = e.target.value.trim().toUpperCase();
        clearTimeout(recipientLookupTimer);
        if (val.length >= 6) {
            recipientLookupTimer = setTimeout(() => lookupRecipientAddress(val), 350);
        } else {
            validateAddressCurrencyMatch(val);
            if (internalRecipientVerifyCard) internalRecipientVerifyCard.style.display = 'none';
        }
    });
}

async function lookupRecipientAddress(addr) {
    if (!addr || !addr.startsWith('KVX-')) return;
    if (!validateAddressCurrencyMatch(addr)) {
        return;
    }

    try {
        const res = await authFetch(`/api/wallet/internal-address-info?address=${encodeURIComponent(addr)}`);
        if (res.success && res.data) {
            const apiAsset = (res.data.asset || '').toUpperCase();
            if (apiAsset && apiAsset !== currentInternalAsset) {
                if (internalRecipientVerifyCard) internalRecipientVerifyCard.style.display = 'none';
                const errCard = document.getElementById('internalAddressErrorCard');
                const msg = `⚠️ Currency Mismatch: Recipient address belongs to ${apiAsset}, but this transfer modal is for ${currentInternalAsset}!`;
                if (errCard) {
                    errCard.textContent = msg;
                    errCard.style.display = 'block';
                }
                showWalletToast(`Invalid Address: Address is for ${apiAsset}!`, 'error');
                return;
            }

            if (internalRecipientNameVal) internalRecipientNameVal.textContent = res.data.name;
            if (internalRecipientEmailVal) internalRecipientEmailVal.textContent = `${res.data.masked_email || ''} • Asset: ${res.data.asset}`;
            if (internalRecipientVerifyCard) internalRecipientVerifyCard.style.display = 'block';
        } else {
            if (internalRecipientVerifyCard) internalRecipientVerifyCard.style.display = 'none';
        }
    } catch (err) {
        if (internalRecipientVerifyCard) internalRecipientVerifyCard.style.display = 'none';
    }
}

// 6. Max Amount Helper
if (internalSendMaxBtn) {
    internalSendMaxBtn.addEventListener('click', () => {
        if (!walletDataState) return;
        let maxVal = 0;
        const isNativeCrypto = ['BTC', 'ETH', 'SOL', 'USDC', 'USDT', 'XRP', 'ADA', 'BNB'].includes(currentInternalAsset);
        if (isNativeCrypto) {
            maxVal = walletDataState.crypto_balances?.[currentInternalAsset] || 0;
        } else if (currentInternalAsset === 'INR') {
            const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live';
            maxVal = isLive ? (walletDataState.real_main_balance_inr || 0) : (walletDataState.demo_main_balance_inr || 0);
        } else {
            const isLive = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live';
            maxVal = isLive ? (walletDataState.real_main_balance || 0) : (walletDataState.demo_main_balance || 0);
        }
        if (internalSendAmountInput) internalSendAmountInput.value = maxVal;
    });
}

// 7. Submit Internal Transfer Form
if (internalTransferForm) {
    internalTransferForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('btnSubmitInternalSend');
        const recipientAddr = internalRecipientInput?.value?.trim().toUpperCase();
        const amountVal = internalSendAmountInput?.value?.trim();
        const noteVal = internalSendNoteInput?.value?.trim();

        if (!validateAddressCurrencyMatch(recipientAddr)) {
            return;
        }

        if (!recipientAddr || !amountVal || parseFloat(amountVal) <= 0) {
            showWalletToast('Please enter a valid recipient address and amount.', 'error');
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing Transfer...';
        }

        try {
            const res = await authFetch('/api/wallet/internal-transfer', {
                method: 'POST',
                body: JSON.stringify({
                    recipient_address: recipientAddr,
                    amount: amountVal,
                    note: noteVal,
                })
            });

            showWalletToast(res.message || 'Internal transfer successful!', 'success');
            resetInternalTransferForm();
            if (internalModalBackdrop) internalModalBackdrop.hidden = true;

            await loadWalletConfig();
            await loadLedgerHistory(1);
        } catch (err) {
            showWalletToast(err.message || 'Internal transfer failed.', 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Internal Transfer';
            }
        }
    });
}

// 8. Camera WebCam QR Scanner Trigger & Real-time Decoding Loop
let qrScanAnimationId = null;

function startQrScanningLoop() {
    if (qrScanAnimationId) cancelAnimationFrame(qrScanAnimationId);

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });

    function tick() {
        if (!qrScannerVideo || qrScannerVideo.readyState !== qrScannerVideo.HAVE_ENOUGH_DATA) {
            qrScanAnimationId = requestAnimationFrame(tick);
            return;
        }

        canvas.height = qrScannerVideo.videoHeight;
        canvas.width = qrScannerVideo.videoWidth;
        ctx.drawImage(qrScannerVideo, 0, 0, canvas.width, canvas.height);

        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: "dontInvert",
        });

        if (code && code.data) {
            let scannedText = code.data.trim();

            if (scannedText.includes('internal_pay=')) {
                try {
                    const parsedUrl = new URL(scannedText);
                    const payParam = parsedUrl.searchParams.get('internal_pay');
                    if (payParam) scannedText = payParam;
                } catch (e) {
                    const match = scannedText.match(/KVX-[A-Z0-9-]+/i);
                    if (match) scannedText = match[0];
                }
            } else if (!scannedText.startsWith('KVX-')) {
                const match = scannedText.match(/KVX-[A-Z0-9-]+/i);
                if (match) scannedText = match[0];
            }

            if (scannedText.startsWith('KVX-')) {
                if (internalRecipientInput) {
                    internalRecipientInput.value = scannedText;
                    internalRecipientInput.dispatchEvent(new Event('input'));
                }
                showWalletToast(`QR Code Scanned: ${scannedText}`, 'success');
                stopCameraScanner();
                return;
            }
        }

        qrScanAnimationId = requestAnimationFrame(tick);
    }

    qrScanAnimationId = requestAnimationFrame(tick);
}

if (btnTriggerCameraScan) {
    btnTriggerCameraScan.addEventListener('click', async () => {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showWalletToast('Camera access is not supported on this browser.', 'error');
            return;
        }
        try {
            cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            if (qrScannerVideo) {
                qrScannerVideo.srcObject = cameraStream;
                qrScannerVideo.play().catch(() => {});
            }
            if (qrScannerModalBackdrop) qrScannerModalBackdrop.hidden = false;

            startQrScanningLoop();
        } catch (err) {
            showWalletToast('Unable to access camera. Please allow camera permissions.', 'error');
        }
    });
}

function stopCameraScanner() {
    if (qrScanAnimationId) {
        cancelAnimationFrame(qrScanAnimationId);
        qrScanAnimationId = null;
    }
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
    if (qrScannerModalBackdrop) qrScannerModalBackdrop.hidden = true;
}

if (closeQrScannerModalBtn) closeQrScannerModalBtn.addEventListener('click', stopCameraScanner);

// 9. Deep-link check on URL load: ?internal_pay=KVX-...
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const payAddr = urlParams.get('internal_pay');
    if (payAddr && payAddr.startsWith('KVX-')) {
        const parts = payAddr.split('-');
        const asset = parts[1] || 'BTC';
        setTimeout(() => {
            openInternalTransferModal(asset, 'send', payAddr);
        }, 600);
    }
});

// =========================================================================
// LIVE CRYPTO ASSET PRICES IN WALLET ASSETS TABLE (USD / INR TOGGLE SUPPORT)
// =========================================================================
let liveCryptoPricesUsd = {
    BTC: 79489.71,
    ETH: 2493.86,
    USDT: 1.0000,
    USDC: 1.0000,
    SOL: 104.90,
    BNB: 745.28,
    XRP: 1.4051,
    DOGE: 0.0899,
    ADA: 0.2193,
    TRX: 0.3368,
    DOT: 0.9810,
    LTC: 55.8100,
    AVAX: 7.9410,
    SHIB: 0.000015,
    LINK: 13.3930,
    UNI: 6.9890,
    ETC: 7.7900,
    XLM: 0.1906,
    TON: 1.6000,
    BCH: 256.20,
};

function formatAssetLivePrice(val, isINR) {
    if (isINR) {
        if (val >= 100000) {
            return '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else if (val >= 100) {
            return '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else if (val < 0.01) {
            return '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 4, maximumFractionDigits: 6 });
        } else {
            return '₹' + val.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        }
    } else {
        if (val >= 100) {
            return '$' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        } else if (val < 0.001) {
            return '$' + val.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 6 });
        } else {
            return '$' + val.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
        }
    }
}

function updateWalletLiveAssetPrices() {
    const cur = (window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USD')).toUpperCase();
    const isINR = (cur === 'INR');

    let rate = 85.25;
    if (typeof inrState !== 'undefined' && inrState.usdtInrRate > 0) {
        rate = inrState.usdtInrRate;
    } else if (window.KRYVEX_USD_INR_RATE && window.KRYVEX_USD_INR_RATE > 0) {
        rate = window.KRYVEX_USD_INR_RATE;
    }

    document.querySelectorAll('.dharma-asset-live-price[data-asset-symbol]').forEach((el) => {
        const symbol = el.getAttribute('data-asset-symbol')?.toUpperCase();
        if (!symbol) return;

        const usdPrice = liveCryptoPricesUsd[symbol] ?? 0;
        if (usdPrice <= 0) return;

        const finalVal = isINR ? (usdPrice * rate) : usdPrice;
        const formatted = formatAssetLivePrice(finalVal, isINR);
        el.textContent = ` · ${formatted}`;
    });

    if (typeof updateConvertUI === 'function') {
        updateConvertUI();
    }
}

let walletPriceSocket = null;
function initWalletPriceSocket() {
    // Initial fetch via Binance REST API for immediate price accuracy
    fetch('https://api.binance.com/api/v3/ticker/price')
        .then(res => res.json())
        .then(data => {
            if (Array.isArray(data)) {
                data.forEach(item => {
                    const raw = item.symbol || '';
                    if (raw.endsWith('USDT')) {
                        const sym = raw.replace('USDT', '');
                        if (liveCryptoPricesUsd.hasOwnProperty(sym)) {
                            liveCryptoPricesUsd[sym] = parseFloat(item.price);
                        }
                    }
                });
                updateWalletLiveAssetPrices();
            }
        })
        .catch(() => {
            fetch('https://fapi.binance.com/fapi/v1/ticker/price')
                .then(r => r.json())
                .then(data => {
                    if (Array.isArray(data)) {
                        data.forEach(item => {
                            const raw = item.symbol || '';
                            if (raw.endsWith('USDT')) {
                                const sym = raw.replace('USDT', '');
                                if (liveCryptoPricesUsd.hasOwnProperty(sym)) {
                                    liveCryptoPricesUsd[sym] = parseFloat(item.price);
                                }
                            }
                        });
                        updateWalletLiveAssetPrices();
                    }
                }).catch(() => {});
        });

    // Real-time WebSocket connection
    try {
        if (walletPriceSocket) walletPriceSocket.close();
        walletPriceSocket = new WebSocket('wss://fstream.binance.com/market/ws/!ticker@arr');
        let updateTimer = null;

        walletPriceSocket.onmessage = (evt) => {
            try {
                const arr = JSON.parse(evt.data);
                if (Array.isArray(arr)) {
                    let updated = false;
                    arr.forEach(item => {
                        const rawSym = (item.s || '').toUpperCase();
                        if (rawSym.endsWith('USDT')) {
                            const sym = rawSym.replace('USDT', '');
                            if (liveCryptoPricesUsd.hasOwnProperty(sym)) {
                                const px = parseFloat(item.c);
                                if (px > 0) {
                                    liveCryptoPricesUsd[sym] = px;
                                    updated = true;
                                }
                            }
                        }
                    });

                    if (updated && !updateTimer) {
                        updateTimer = setTimeout(() => {
                            updateTimer = null;
                            updateWalletLiveAssetPrices();
                        }, 500);
                    }
                }
            } catch (e) {}
        };

        walletPriceSocket.onclose = () => {
            setTimeout(initWalletPriceSocket, 4000);
        };
    } catch (e) {}
}

function initFaqAccordion() {
    const faqQuestions = document.querySelectorAll('.dharma-faq-question');
    faqQuestions.forEach(btn => {
        if (btn.dataset.faqInitialized) return;
        btn.dataset.faqInitialized = 'true';

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const parentItem = btn.closest('.dharma-faq-item');
            if (!parentItem) return;

            const isAlreadyActive = parentItem.classList.contains('active');

            // Close all open items for clean accordion behavior
            document.querySelectorAll('.dharma-faq-item').forEach(item => {
                item.classList.remove('active');
            });

            // Toggle clicked item
            if (!isAlreadyActive) {
                parentItem.classList.add('active');
            }
        });
    });
}

// Global click event delegation for FAQ accordion
document.addEventListener('click', (e) => {
    const questionBtn = e.target.closest('.dharma-faq-question');
    if (!questionBtn) return;

    e.preventDefault();
    const parentItem = questionBtn.closest('.dharma-faq-item');
    if (!parentItem) return;

    const isAlreadyActive = parentItem.classList.contains('active');
    document.querySelectorAll('.dharma-faq-item').forEach(item => {
        item.classList.remove('active');
    });

    if (!isAlreadyActive) {
        parentItem.classList.add('active');
    }
});

function syncWithdrawPillWithCurrency() {
    const activeCur = (window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_currency') || 'USDT')).toUpperCase();
    const tab = window.getDefaultWithdrawTab ? window.getDefaultWithdrawTab() : (activeCur === 'INR' ? 'inr' : 'usdt');
    const assetPill = document.getElementById('withdrawAssetPill');
    if (tab === 'inr') {
        if (window.activateWithdrawTab) window.activateWithdrawTab('inr');
        if (assetPill) assetPill.textContent = 'Indian Rupee (INR)';
    } else {
        if (window.activateWithdrawTab) window.activateWithdrawTab('usdt');
        if (assetPill) assetPill.textContent = 'Tether (USDT)';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initWalletPriceSocket();
    updateWalletLiveAssetPrices();
    initFaqAccordion();
    syncWithdrawPillWithCurrency();
    setupTradingAccountControls();
    loadWallet();
});
window.addEventListener('kryvex_currency_changed', () => {
    updateWalletLiveAssetPrices();
    updateFaqDynamicText();
    syncWithdrawPillWithCurrency();
});
window.addEventListener('currencyChanged', () => {
    updateWalletLiveAssetPrices();
    updateFaqDynamicText();
    syncWithdrawPillWithCurrency();
});
window.addEventListener('storage', (e) => {
    if (e.key === 'kryvex_active_currency') {
        updateWalletLiveAssetPrices();
        updateFaqDynamicText();
        syncWithdrawPillWithCurrency();
    }
});
