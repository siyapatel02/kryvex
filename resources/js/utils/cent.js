// MT5 Cent accounts store balances in USD like every other account; they are displayed in US cents (1 USD = 100 USC).

export function isCentAccount(acc) {
    if (!acc || String(acc.broker_type || '').toLowerCase() !== 'mt5') return false;
    if (acc.account_type === 'demo' || acc.is_demo) return false;
    return Boolean(acc.is_cent || acc.plan_key === 'cent' || acc.account_currency === 'USC');
}

function cachedTradingAccounts() {
    if (Array.isArray(window.__cachedTradingAccounts)) return window.__cachedTradingAccounts;
    try {
        const parsed = JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}

export function getActiveTradingAccount() {
    const uid = localStorage.getItem('kryvex_active_account_uid');
    if (!uid) return null;
    return cachedTradingAccounts().find(a => String(a.account_uid) === String(uid)) || null;
}

export function isActiveCentAccount() {
    if (localStorage.getItem('kryvex_trading_mode') !== 'live') return false;
    return isCentAccount(getActiveTradingAccount());
}

export function formatUsc(usdValue, { signed = false } = {}) {
    const cents = Number(usdValue || 0) * 100;
    const sign = cents < 0 ? '-' : (signed && cents > 0 ? '+' : '');
    return `${sign}${Math.abs(cents).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} USC`;
}
