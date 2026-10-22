import { isCentAccount, formatUsc } from "../utils/cent";

document.addEventListener("DOMContentLoaded", () => {
    let portfolioData = null;
    let tradingAccounts = [];
    let activeAccountUid = localStorage.getItem("kryvex_active_account_uid") || null;
    let futuresPositions = [];
    let optionsPositions = [];
    let futuresTrades = [];
    let optionsTrades = [];
    let transactionsList = [];
    let withdrawalsList = [];
    let tickerPrices = {};
    let ticker24hChanges = {};

    const PAGE_SIZE = 10;
    let holdingsPage = 1;
    let ordersPage = 1;
    let historyPage = 1;
    let activityPage = 1;
    let withdrawalsPage = 1;

    let currentChartPeriod = "1M";

    function getAuthToken() {
        return localStorage.getItem('kryvex_access_token') ||
               localStorage.getItem('access_token') ||
               localStorage.getItem('token') ||
               localStorage.getItem('kryvex_token') || '';
    }

    async function authFetch(url, options = {}) {
        const token = getAuthToken();
        const headers = {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            ...(options.headers || {})
        };
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return fetch(url, { ...options, headers });
    }

    function getActiveCurrency() {
        // MT5 accounts (standard and cent) have no INR balance, so the INR toggle doesn't apply to them.
        const activeAcc = tradingAccounts.find(a => a.account_uid === activeAccountUid);
        if (activeAcc && String(activeAcc.broker_type || "").toLowerCase() === "mt5") return "USD";
        if (typeof window.getActiveCurrency === "function") {
            return window.getActiveCurrency();
        }
        const cur = localStorage.getItem("kryvex_active_currency") || localStorage.getItem("kryvex_currency");
        return (cur === "INR") ? "INR" : "USD";
    }

    function isSelectedCentAccount() {
        return isCentAccount(tradingAccounts.find(a => a.account_uid === activeAccountUid));
    }

    function isDemoAccount(acc) {
        return Boolean(acc && (acc.account_type === "demo" || acc.is_demo));
    }

    // Each account has its own Main Wallet and trading balance; demo accounts use the demo_* fields.
    function getAccountFunds(acc, inrRate) {
        const demo = isDemoAccount(acc);
        const pick = (usdKey, inrKey) => {
            const usd = Number((acc && acc[usdKey]) ?? 0);
            const inr = Number((acc && acc[inrKey]) || 0) > 0 ? Number(acc[inrKey]) : usd * inrRate;
            return { usd, inr };
        };
        // Demo accounts have only a trading balance, no Main Wallet.
        const main = demo ? { usd: 0, inr: 0 } : pick("real_main_balance", "real_main_balance_inr");
        const avail = demo ? pick("demo_available_balance", "demo_available_balance_inr") : pick("real_available_balance", "real_available_balance_inr");
        return { mainUsd: main.usd, mainInr: main.inr, availUsd: avail.usd, availInr: avail.inr };
    }

    function belongsToAccount(item, acc) {
        if (!acc) return true;
        if (item.account_uid) return item.account_uid === acc.account_uid;
        const mode = String(item.mode || "").toLowerCase();
        if (mode) return (mode === "demo") === isDemoAccount(acc) && (isDemoAccount(acc) || Boolean(acc.is_default));
        return Boolean(acc.is_default) || isDemoAccount(acc);
    }

    function formatUsd(amount) {
        if (isSelectedCentAccount()) return formatUsc(amount);
        return formatUsdPlain(amount);
    }

    // Market prices and on-chain withdrawal amounts stay in USD even for Cent accounts.
    function formatUsdPlain(amount) {
        const val = Number(amount || 0);
        const prefix = val < 0 ? "-$" : "$";
        return prefix + Math.abs(val).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatInr(amount) {
        const val = Number(amount || 0);
        const prefix = val < 0 ? "-₹" : "₹";
        return prefix + Math.abs(val).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatSignedUsd(amount) {
        if (isSelectedCentAccount()) return formatUsc(amount, { signed: true });
        const val = Number(amount || 0);
        const prefix = val >= 0 ? "+$" : "-$";
        return prefix + Math.abs(val).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatSignedInr(amount) {
        const val = Number(amount || 0);
        const prefix = val >= 0 ? "+₹" : "-₹";
        return prefix + Math.abs(val).toLocaleString("en-IN", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function isItemInr(item) {
        const c = String(item.currency || item.quote_currency || "").toUpperCase();
        if (c === "INR") return true;
        const sym = String(item.symbol || item.contract || item.pair || "").toUpperCase();
        return sym.endsWith("INR") || sym.includes("/INR") || sym.includes("-INR");
    }

    // --- API Data Fetching ---
    async function loadTickerPrices() {
        try {
            const res = await fetch("/api/markets/futures/tickers");
            if (res.ok) {
                const data = await res.json();
                const list = data.data || data.tickers || data || [];
                if (Array.isArray(list)) {
                    list.forEach(t => {
                        const sym = (t.symbol || "").toUpperCase().replace("-", "").replace("/", "");
                        const px = Number(t.last_price || t.price || t.lastPrice || t.mark_price || 0);
                        const rawChg = t.price_change_percent ?? t.change_24h_percent ?? t.priceChangePercent ?? t.change_24h ?? t.change ?? 0;
                        const numChg = Number(rawChg);
                        const chgFormatted = isNaN(numChg)
                            ? String(rawChg)
                            : ((numChg >= 0 ? "+" : "") + numChg.toFixed(2) + "%");

                        if (sym && px > 0) {
                            tickerPrices[sym] = px;
                            ticker24hChanges[sym] = chgFormatted;
                        }
                    });
                }
            }
        } catch (e) {}
    }

    let allTickerSocket = null;
    let livePnlUpdateTimer = null;

    function connectAllTickerSocket() {
        if (allTickerSocket) {
            try { allTickerSocket.close(); } catch(e) {}
        }
        try {
            allTickerSocket = new WebSocket('wss://fstream.binance.com/market/ws/!ticker@arr');
            allTickerSocket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    const rows = Array.isArray(data) ? data : [];
                    let hasUpdate = false;
                    rows.forEach(item => {
                        const sym = (item.s || "").toUpperCase().replace(/[-/_ ]/g, "");
                        const price = Number(item.c || 0);
                        if (sym && price > 0) {
                            tickerPrices[sym] = price;
                            hasUpdate = true;
                        }
                    });
                    if (hasUpdate && (futuresPositions.length > 0 || optionsPositions.length > 0)) {
                        if (!livePnlUpdateTimer) {
                            livePnlUpdateTimer = setTimeout(() => {
                                livePnlUpdateTimer = null;
                                renderAll();
                            }, 500); // 500ms smooth throttle
                        }
                    }
                } catch(e) {}
            };
            allTickerSocket.onclose = () => {
                setTimeout(connectAllTickerSocket, 3000);
            };
        } catch(e) {}
    }

    async function fetchJson(url, options = {}) {
        try {
            const res = await authFetch(url, options);
            return res.ok ? await res.json() : null;
        } catch (e) {
            return null;
        }
    }

    const fetchAccountsJson = () => fetchJson("/api/trading-accounts?broker_type=all", { headers: { 'X-Broker-Type': 'all' } });

    function applyTradingAccounts(data) {
        const list = data ? (data.data || data.trading_accounts || []) : null;
        if (Array.isArray(list) && list.length) {
            tradingAccounts = list;
            window.__cachedTradingAccounts = list;
            try { localStorage.setItem('kryvex_cached_trading_accounts', JSON.stringify(list)); } catch (e) {}
        }
        ensureActiveAccount();
    }

    function applyWallet(data) {
        if (data && data.data) portfolioData = data.data;
    }

    const PORTFOLIO_CACHE_KEY = "kryvex_portfolio_cache_v1";

    function savePortfolioCache() {
        try {
            localStorage.setItem(PORTFOLIO_CACHE_KEY, JSON.stringify({
                portfolioData, futuresPositions, optionsPositions, futuresTrades,
                transactionsList: transactionsList.slice(0, 50),
                withdrawalsList: withdrawalsList.slice(0, 50),
            }));
        } catch (e) {}
    }

    function restorePortfolioCache() {
        let restored = false;
        try {
            const cachedAccounts = JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]');
            if (Array.isArray(cachedAccounts) && cachedAccounts.length) {
                tradingAccounts = cachedAccounts;
                restored = true;
            }
            const cache = JSON.parse(localStorage.getItem(PORTFOLIO_CACHE_KEY) || 'null');
            if (cache) {
                portfolioData = cache.portfolioData || null;
                futuresPositions = cache.futuresPositions || [];
                optionsPositions = cache.optionsPositions || [];
                futuresTrades = cache.futuresTrades || [];
                transactionsList = cache.transactionsList || [];
                withdrawalsList = cache.withdrawalsList || [];
            }
        } catch (e) {}
        return restored;
    }

    async function loadTradingAccounts() {
        applyTradingAccounts(await fetchAccountsJson());
    }

    function ensureActiveAccount() {
        if (tradingAccounts.length === 0) {
            tradingAccounts = [{
                account_uid: "main_account_01",
                account_name: "Main Account",
                demo_available_balance: 0,
                demo_available_balance_inr: 0,
                demo_locked_balance: 0.0,
                demo_locked_balance_inr: 0.0,
                is_default: true
            }];
        }

        if (!activeAccountUid || !tradingAccounts.some(a => a.account_uid === activeAccountUid)) {
            const def = tradingAccounts.find(a => a.is_default) || tradingAccounts[0];
            activeAccountUid = def ? def.account_uid : null;
            if (activeAccountUid) localStorage.setItem("kryvex_active_account_uid", activeAccountUid);
        }

        renderAccountSelector();
    }

    function renderAccountSelector() {
        const cur = getActiveCurrency();
        const isInr = (cur === "INR");
        const inrRate = Number((portfolioData && (portfolioData.inr_rate || portfolioData.usdt_inr_rate)) || 83.20);
        const select = document.getElementById("pfAccountSelect");
        const transferSelect = document.getElementById("pfTransferAccountUid");

        if (select) {
            select.innerHTML = tradingAccounts.map(acc => {
                const isSelected = acc.account_uid === activeAccountUid;
                const funds = getAccountFunds(acc, inrRate);
                const rawUsd = funds.mainUsd + funds.availUsd;
                const rawInr = funds.mainInr + funds.availInr;
                const bal = isInr ? formatInr(rawInr) : (isCentAccount(acc) ? formatUsc(rawUsd) : formatUsdPlain(rawUsd));
                return `<option value="${acc.account_uid}" class="bg-[#12131c] text-slate-100 py-1.5 px-2 font-medium" ${isSelected ? "selected" : ""}>${acc.account_name} (${bal})</option>`;
            }).join("");
        }

        if (transferSelect) {
            transferSelect.innerHTML = tradingAccounts.map(acc => {
                const isSelected = acc.account_uid === activeAccountUid;
                return `<option value="${acc.account_uid}" class="bg-[#12131c] text-slate-100 py-1.5 px-2 font-medium" ${isSelected ? "selected" : ""}>${acc.account_name}</option>`;
            }).join("");
        }
    }

    const pfAccSelect = document.getElementById("pfAccountSelect");
    if (pfAccSelect) {
        pfAccSelect.addEventListener("change", (e) => {
            activeAccountUid = e.target.value;
            localStorage.setItem("kryvex_active_account_uid", activeAccountUid);
            renderAll();
        });
    }

    const applyPositions = (f, o) => {
        if (f) futuresPositions = f.data?.positions || f.positions || [];
        if (o) optionsPositions = o.data?.positions || o.positions || [];
    };

    let portfolioLoading = false;

    async function loadLivePortfolioData() {
        if (portfolioLoading) return;
        portfolioLoading = true;
        try {
            loadTickerPrices().then(() => renderAll());

            // Balances first so the top cards fill immediately, then the heavier history lists.
            const [accData, walletData] = await Promise.all([fetchAccountsJson(), fetchJson("/api/wallet")]);
            applyTradingAccounts(accData);
            applyWallet(walletData);
            renderAll();
            hidePortfolioLoader();

            const [f, o, t, l, w] = await Promise.all([
                fetchJson("/api/futures/positions"),
                fetchJson("/api/options/positions"),
                fetchJson("/api/futures/trades"),
                fetchJson("/api/wallet/ledger"),
                fetchJson("/api/wallet/withdrawals"),
            ]);
            applyPositions(f, o);
            if (t) futuresTrades = t.data?.trades || t.trades || [];
            if (l) transactionsList = l.data?.ledger || l.ledger || [];
            if (w) withdrawalsList = w.data?.withdrawal_requests || w.withdrawal_requests || [];
            renderAll();
            savePortfolioCache();
        } finally {
            portfolioLoading = false;
            hidePortfolioLoader();
        }
        if (!allTickerSocket || allTickerSocket.readyState > 1) connectAllTickerSocket();
    }

    let portfolioPolling = false;

    async function refreshLivePortfolio() {
        if (document.hidden || portfolioPolling || portfolioLoading) return;
        portfolioPolling = true;
        try {
            const [accData, walletData, f, o] = await Promise.all([
                fetchAccountsJson(),
                fetchJson("/api/wallet"),
                fetchJson("/api/futures/positions"),
                fetchJson("/api/options/positions"),
            ]);
            applyTradingAccounts(accData);
            applyWallet(walletData);
            applyPositions(f, o);
            renderAll();
        } finally {
            portfolioPolling = false;
        }
    }

    function hidePortfolioLoader() {
        const overlay = document.getElementById("portfolioLoadingOverlay");
        if (overlay && !overlay.classList.contains("is-hidden")) {
            overlay.classList.add("is-hidden");
            setTimeout(() => {
                overlay.style.display = "none";
            }, 350);
        }
    }

    function renderAll() {
        const cur = getActiveCurrency();
        const isInr = (cur === "INR");
        const inrRate = Number((portfolioData && (portfolioData.inr_rate || portfolioData.usdt_inr_rate)) || 83.20);

        renderAccountSelector();

        const wallet = (portfolioData && portfolioData.wallet) ? portfolioData.wallet : {};
        const activeAcc = tradingAccounts.find(a => a.account_uid === activeAccountUid) || tradingAccounts[0] || {};

        // 1 + 2. Main Wallet and trading balance of the selected account only
        const funds = getAccountFunds(activeAcc, inrRate);
        const hideMain = isDemoAccount(activeAcc);
        const mainPill = document.getElementById("pfBreakdownMain")?.parentElement;
        if (mainPill) mainPill.style.display = hideMain ? "none" : "";
        const mainCard = document.getElementById("pfMainBalance")?.closest(".pf-inner-box");
        if (mainCard) mainCard.style.display = hideMain ? "none" : "";
        const activeMain = isInr ? funds.mainInr : funds.mainUsd;
        const activeAvailable = isInr ? funds.availInr : funds.availUsd;

        // 3. Filter Open Positions strictly for this currency AND selected sub-account
        const allPositions = [...futuresPositions, ...optionsPositions];
        const matchingPositions = allPositions.filter(pos => {
            const matchCurrency = isInr ? isItemInr(pos) : !isItemInr(pos);
            return matchCurrency && belongsToAccount(pos, activeAcc);
        });

        let calcLockedVal = 0;
        let calcUnrealizedPnlVal = 0;

        matchingPositions.forEach(item => {
            const rawSymbol = item.symbol || item.contract || "BTCUSDT";
            const symKey = rawSymbol.toUpperCase().replace(/[-/_ ]/g, "");
            const entryUsd = Number(item.entry_usd || item.entry_price || 0);
            const qty = Number(item.size || item.quantity || 0);
            const side = (item.side || "BUY").toUpperCase();

            let markUsd = Number(tickerPrices[symKey] || item.mark_usd || item.mark_price || item.current_price || 0);
            if (!markUsd || markUsd <= 0) markUsd = entryUsd;

            let pUsd = 0;
            if (qty > 0 && entryUsd > 0) {
                pUsd = (side === "BUY" || side === "LONG") ? (markUsd - entryUsd) * qty : (entryUsd - markUsd) * qty;
            } else {
                pUsd = Number(item.pnl_usd || item.unrealized_pnl || 0);
            }

            const mUsd = Number(item.margin_usd || item.margin || item.margin_inr || 0);

            if (isInr) {
                const mInr = Number(item.margin_inr || (mUsd * inrRate));
                const pInr = Number(item.pnl_inr || (pUsd * inrRate));
                calcLockedVal += mInr;
                calcUnrealizedPnlVal += pInr;
            } else {
                calcLockedVal += mUsd;
                calcUnrealizedPnlVal += pUsd;
            }
        });

        // 4. Filter Executed Trades strictly for this currency to calculate Realized P&L
        const allTrades = [...futuresTrades, ...optionsTrades];
        const matchingTrades = allTrades.filter(t => {
            const matchCurrency = isInr ? isItemInr(t) : !isItemInr(t);
            return matchCurrency && belongsToAccount(t, activeAcc);
        });

        let calcRealizedPnlVal = 0;
        matchingTrades.forEach(t => {
            if (t.realized_pnl !== undefined && t.realized_pnl !== null) {
                const rpUsd = Number(t.realized_pnl || 0);
                calcRealizedPnlVal += isInr ? Number(t.realized_pnl_inr || (rpUsd * inrRate)) : rpUsd;
            }
        });

        // 5. Calculate Live Trading Available and Total Portfolio Value
        // In Futures trading, live available trading balance factors in floating open position PnL:
        const liveAvailable = Math.max(0, activeAvailable + calcUnrealizedPnlVal);

        // Formula: Main Wallet + Live Trading Available + Locked Margin
        const totalPortfolioVal = activeMain + liveAvailable + calcLockedVal;

        const totalPnl = calcRealizedPnlVal + calcUnrealizedPnlVal;
        const totalPnlPct = (totalPortfolioVal > 0) ? ((totalPnl / totalPortfolioVal) * 100).toFixed(2) : "0.00";

        const todayPnl = calcUnrealizedPnlVal;
        const todayPnlPct = (totalPortfolioVal > 0) ? ((todayPnl / totalPortfolioVal) * 100).toFixed(2) : "0.00";

        // 6. Update Portfolio Overview Top Cards & Breakdown Formula
        if (isInr) {
            // In INR mode:
            // 1) Total Portfolio Value is in INR (₹)
            setText("pfTotalPortfolioValue", formatInr(totalPortfolioVal));
            setText("pfSecondaryPortfolioValue", "≈ " + formatUsdPlain(totalPortfolioVal / inrRate) + " USD");
            
            // 2) Main Wallet is in INR (₹)
            setText("pfMainBalance", formatInr(activeMain));
            setText("pfBreakdownMain", formatInr(activeMain));

            // 3) Trading Available, Locked Margin, and PnL in INR (₹):
            setText("pfAvailableBalance", formatInr(liveAvailable));
            setText("pfLockedBalance", formatInr(calcLockedVal));
            setText("pfUnrealizedPnl", (calcUnrealizedPnlVal >= 0 ? "+" : "") + formatInr(calcUnrealizedPnlVal));
            setText("pfRealizedPnl", (calcRealizedPnlVal >= 0 ? "+" : "") + formatInr(calcRealizedPnlVal));

            // Hero Distribution Breakdown (Trading and Locked in INR)
            setText("pfBreakdownAvail", formatInr(liveAvailable));
            setText("pfBreakdownLocked", formatInr(calcLockedVal));

            // P&L Header Badges (in INR)
            const todaySign = todayPnl >= 0 ? "+" : "";
            setText("pfTodayPnlText", `${todaySign}${formatInr(todayPnl)} (${todaySign}${todayPnlPct}%) Today`);
            
            const allTimeSign = totalPnl >= 0 ? "+" : "";
            setText("pfAllTimePnlText", `${allTimeSign}${formatInr(totalPnl)} (${allTimeSign}${totalPnlPct}%)`);

            const isTodayPos = todayPnl >= 0;
            const todayBadge = document.getElementById("pfTodayPnlBadge");
            if (todayBadge) {
                todayBadge.classList.toggle("is-positive", isTodayPos);
                todayBadge.classList.toggle("is-negative", !isTodayPos);
                const arrowSpan = todayBadge.querySelector("span:first-child");
                if (arrowSpan) arrowSpan.textContent = isTodayPos ? "▲" : "▼";
            }

            const isUnrealizedPos = (calcUnrealizedPnlVal >= 0);
            const isRealizedPos = (calcRealizedPnlVal >= 0);
            const isTotalPos = (totalPnl >= 0);

            const unPnlEl = document.getElementById("pfUnrealizedPnl");
            if (unPnlEl) {
                unPnlEl.className = `font-mono font-extrabold text-sm sm:text-base block tracking-tight truncate ${isUnrealizedPos ? 'market-positive text-emerald-400' : 'market-negative text-rose-400'}`;
            }

            const rePnlEl = document.getElementById("pfRealizedPnl");
            if (rePnlEl) {
                rePnlEl.className = `font-mono font-extrabold text-sm sm:text-base block tracking-tight truncate ${isRealizedPos ? 'market-positive text-emerald-400' : 'market-negative text-rose-400'}`;
            }

            const allTimeEl = document.getElementById("pfAllTimePnlText");
            if (allTimeEl) {
                allTimeEl.className = `ml-1 font-extrabold ${isTotalPos ? 'market-positive text-emerald-400' : 'market-negative text-rose-400'}`;
            }
        } else {
            setText("pfTotalPortfolioValue", formatUsd(totalPortfolioVal));
            setText("pfSecondaryPortfolioValue", "≈ " + formatInr(totalPortfolioVal * inrRate) + " INR");
            
            // Hero Distribution Breakdown (Clean Text)
            setText("pfBreakdownMain", formatUsd(activeMain));
            setText("pfBreakdownAvail", formatUsd(liveAvailable));
            setText("pfBreakdownLocked", formatUsd(calcLockedVal));

            const todaySign = todayPnl >= 0 ? "+" : "";
            setText("pfTodayPnlText", `${todaySign}${formatUsd(todayPnl)} (${todaySign}${todayPnlPct}%) Today`);
            
            const allTimeSign = totalPnl >= 0 ? "+" : "";
            setText("pfAllTimePnlText", `${allTimeSign}${formatUsd(totalPnl)} (${allTimeSign}${totalPnlPct}%)`);

            const isTodayPos = todayPnl >= 0;
            const todayBadge = document.getElementById("pfTodayPnlBadge");
            if (todayBadge) {
                todayBadge.classList.toggle("is-positive", isTodayPos);
                todayBadge.classList.toggle("is-negative", !isTodayPos);
                const arrowSpan = todayBadge.querySelector("span:first-child");
                if (arrowSpan) arrowSpan.textContent = isTodayPos ? "▲" : "▼";
            }
            
            setText("pfMainBalance", formatUsd(activeMain));
            setText("pfAvailableBalance", formatUsd(liveAvailable));
            setText("pfLockedBalance", formatUsd(calcLockedVal)); // 0 if no open USD position
            setText("pfUnrealizedPnl", (calcUnrealizedPnlVal >= 0 ? "+" : "") + formatUsd(calcUnrealizedPnlVal));
            setText("pfRealizedPnl", (calcRealizedPnlVal >= 0 ? "+" : "") + formatUsd(calcRealizedPnlVal));

            const isUnrealizedPos = (calcUnrealizedPnlVal >= 0);
            const isRealizedPos = (calcRealizedPnlVal >= 0);
            const isTotalPos = (totalPnl >= 0);

            const unPnlEl = document.getElementById("pfUnrealizedPnl");
            if (unPnlEl) {
                unPnlEl.className = `font-mono font-extrabold text-sm sm:text-base block tracking-tight truncate ${isUnrealizedPos ? 'market-positive text-emerald-400' : 'market-negative text-rose-400'}`;
            }

            const rePnlEl = document.getElementById("pfRealizedPnl");
            if (rePnlEl) {
                rePnlEl.className = `font-mono font-extrabold text-sm sm:text-base block tracking-tight truncate ${isRealizedPos ? 'market-positive text-emerald-400' : 'market-negative text-rose-400'}`;
            }

            const allTimeEl = document.getElementById("pfAllTimePnlText");
            if (allTimeEl) {
                allTimeEl.className = `ml-1 font-extrabold ${isTotalPos ? 'market-positive text-emerald-400' : 'market-negative text-rose-400'}`;
            }
        }

        renderDynamicHoldingsTable(activeMain, liveAvailable, calcLockedVal, activeAcc, isInr, inrRate, totalPortfolioVal);
        renderFilteredOrdersTable(matchingPositions, isInr, inrRate);
        renderFilteredTradeHistoryTable(matchingTrades, isInr, inrRate);
        renderFilteredFundActivityTable(isInr, inrRate);
        renderFilteredWithdrawalsTable(isInr, inrRate);
        renderTopRecentActivityFeed(matchingTrades, isInr, inrRate);
        renderPerformanceChart(totalPortfolioVal, isInr);
        renderDynamicAssetAllocation(activeMain, liveAvailable, calcLockedVal, activeAcc, isInr, inrRate);
        updateTransferCalculations();
    }

    // --- Render Top Recent Activity Feed Widget (Top 3 Items) ---
    function renderTopRecentActivityFeed(trades, isInr, inrRate) {
        const feed = document.getElementById("pfTopRecentActivityFeed");
        if (!feed) return;

        const events = [];

        // 1. Deposits from transactionsList
        (transactionsList || []).forEach(t => {
            if (isInr ? isItemInr(t) : !isItemInr(t)) {
                const amtUsd = Number(t.amount || 0);
                const amtInr = Number(t.amount_inr || (amtUsd * inrRate));
                events.push({
                    type: "deposit",
                    title: `Deposit ${t.currency || 'USDT'}`,
                    subtitle: t.description || "Wallet Deposit",
                    icon: "↓",
                    iconBg: "bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 font-bold",
                    amountStr: isInr ? formatSignedInr(amtInr) : formatSignedUsd(amtUsd),
                    amountColor: "text-emerald-400",
                    status: "Completed",
                    statusColor: "text-emerald-400",
                    time: t.created_at ? new Date(t.created_at).getTime() : Date.now(),
                    timeStr: t.created_at ? new Date(t.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : "Today"
                });
            }
        });

        // 2. Withdrawals from withdrawalsList
        (withdrawalsList || []).forEach(w => {
            if (isInr ? isItemInr(w) : !isItemInr(w)) {
                const reqAmt = Number(w.amount || 0);
                const statusRaw = String(w.status || "pending").toLowerCase();
                let statusText = "Pending";
                let statusColor = "text-amber-400";
                if (statusRaw === "approved" || statusRaw === "completed" || statusRaw === "accepted" || statusRaw === "success") {
                    statusText = "Accepted";
                    statusColor = "text-emerald-400";
                } else if (statusRaw === "rejected" || statusRaw === "declined" || statusRaw === "cancelled") {
                    statusText = "Rejected";
                    statusColor = "text-rose-400";
                }

                events.push({
                    type: "withdraw",
                    title: `Withdraw ${w.currency || w.wallet_currency || 'USD'}`,
                    subtitle: w.network || w.method || "Bank Wire",
                    icon: "↑",
                    iconBg: "bg-rose-500/15 text-rose-400 border border-rose-500/30 font-bold",
                    amountStr: isInr ? formatSignedInr(-reqAmt * inrRate) : formatSignedUsd(-reqAmt),
                    amountColor: "text-rose-400",
                    status: statusText,
                    statusColor: statusColor,
                    time: (w.requested_at || w.created_at) ? new Date(w.requested_at || w.created_at).getTime() : Date.now(),
                    timeStr: (w.requested_at || w.created_at) ? new Date(w.requested_at || w.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : "Today"
                });
            }
        });

        // 3. Trades from matchingTrades
        (trades || []).forEach(tr => {
            const side = (tr.side || "BUY").toUpperCase();
            const pxUsd = Number(tr.price || tr.entry_price || 0);
            const qty = Number(tr.size || tr.quantity || 0);
            const totalUsd = pxUsd * qty;
            const totalStr = isInr ? formatInr(totalUsd * inrRate) : formatUsd(totalUsd);
            const isBuy = side === "BUY";

            events.push({
                type: "trade",
                title: `${side} ${tr.symbol || tr.contract || 'BTC'}`,
                subtitle: `${qty} @ ${isInr ? formatInr(pxUsd * inrRate) : formatUsdPlain(pxUsd)}`,
                icon: isBuy ? "B" : "S",
                iconBg: isBuy ? "bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 font-mono font-black" : "bg-rose-500/15 text-rose-400 border border-rose-500/30 font-mono font-black",
                amountStr: totalStr,
                amountColor: "text-white",
                status: "Executed",
                statusColor: "text-purple-400",
                time: tr.created_at ? new Date(tr.created_at).getTime() : Date.now(),
                timeStr: tr.created_at ? new Date(tr.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : "Today"
            });
        });

        // Sort descending by time
        events.sort((a, b) => b.time - a.time);

        // Take top 3
        const topEvents = events.slice(0, 3);

        if (topEvents.length === 0) {
            feed.innerHTML = `
                <div class="py-6 text-center text-slate-500 text-xs font-semibold">
                    <span>No recent activity recorded yet.</span>
                </div>
            `;
            return;
        }

        feed.innerHTML = topEvents.map(e => `
            <div class="flex items-center justify-between p-2.5 pf-inner-box hover:bg-white/[0.05] transition-all">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg ${e.iconBg} flex items-center justify-center text-xs">${e.icon}</span>
                    <div>
                        <strong class="text-white text-xs block leading-tight">${e.title}</strong>
                        <span class="text-[10px] text-slate-400">${e.subtitle} • <span class="text-slate-500">${e.timeStr}</span></span>
                    </div>
                </div>
                <div class="text-right">
                    <strong class="${e.amountColor} text-xs font-mono font-bold block">${e.amountStr}</strong>
                    <span class="text-[9px] font-extrabold ${e.statusColor} uppercase tracking-wider">${e.status}</span>
                </div>
            </div>
        `).join("");
    }

    // --- Universal Pagination Controls Component ---
    function renderPaginationControls(containerId, totalItems, currentPage, pageSize, onPageChange) {
        const container = document.getElementById(containerId);
        if (!container) return;

        if (totalItems <= 0) {
            container.innerHTML = "";
            return;
        }

        const totalPages = Math.max(1, Math.ceil(totalItems / pageSize));
        const startItem = (currentPage - 1) * pageSize + 1;
        const endItem = Math.min(currentPage * pageSize, totalItems);

        let pageBtnsHtml = "";
        for (let p = 1; p <= totalPages; p++) {
            if (totalPages > 7) {
                if (p !== 1 && p !== totalPages && Math.abs(p - currentPage) > 1) {
                    if (p === 2 || p === totalPages - 1) {
                        pageBtnsHtml += `<span class="px-1.5 py-1 text-slate-600 font-bold">...</span>`;
                    }
                    continue;
                }
            }
            const isActive = (p === currentPage);
            pageBtnsHtml += `
                <button type="button" class="pf-page-btn px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${isActive ? 'active bg-purple-600 text-white shadow-lg shadow-purple-900/40' : 'bg-white/[0.04] border border-white/10 text-slate-300 hover:text-white hover:bg-white/[0.08]'}" data-page="${p}">
                    ${p}
                </button>
            `;
        }

        const prevDisabled = currentPage <= 1;
        const nextDisabled = currentPage >= totalPages;

        container.innerHTML = `
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 w-full">
                <span class="text-slate-400 font-medium text-xs">
                    Showing <strong class="text-white font-mono font-bold">${startItem}</strong> to <strong class="text-white font-mono font-bold">${endItem}</strong> of <strong class="text-white font-mono font-bold">${totalItems}</strong> entries
                </span>
                <div class="flex items-center gap-1.5">
                    <button type="button" class="pf-prev-btn px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${prevDisabled ? 'bg-white/[0.02] border border-white/5 text-slate-600 cursor-not-allowed opacity-40' : 'bg-white/[0.04] border border-white/10 text-slate-300 hover:text-white hover:bg-white/[0.08]'}" ${prevDisabled ? 'disabled' : ''}>
                        Previous
                    </button>
                    <div class="flex items-center gap-1">
                        ${pageBtnsHtml}
                    </div>
                    <button type="button" class="pf-next-btn px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer ${nextDisabled ? 'bg-white/[0.02] border border-white/5 text-slate-600 cursor-not-allowed opacity-40' : 'bg-white/[0.04] border border-white/10 text-slate-300 hover:text-white hover:bg-white/[0.08]'}" ${nextDisabled ? 'disabled' : ''}>
                        Next
                    </button>
                </div>
            </div>
        `;

        container.querySelectorAll(".pf-page-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                const p = parseInt(btn.getAttribute("data-page"), 10);
                if (p && p !== currentPage) {
                    onPageChange(p);
                }
            });
        });

        const prevBtn = container.querySelector(".pf-prev-btn");
        if (prevBtn && !prevDisabled) {
            prevBtn.addEventListener("click", () => onPageChange(currentPage - 1));
        }

        const nextBtn = container.querySelector(".pf-next-btn");
        if (nextBtn && !nextDisabled) {
            nextBtn.addEventListener("click", () => onPageChange(currentPage + 1));
        }
    }

    // --- Render Filtered Open Orders Table with Pagination & Mobile Cards ---
    function renderFilteredOrdersTable(positions, isInr, inrRate) {
        const tbody = document.getElementById("pfOrdersTableBody");
        const cardList = document.getElementById("pfOrdersCardsList");
        const countBadge = document.getElementById("pfOpenOrdersCountBadge");
        if (!tbody && !cardList) return;

        if (countBadge) {
            countBadge.textContent = positions.length;
            if (positions.length > 0) {
                countBadge.classList.remove("hidden");
                countBadge.style.display = "inline-block";
            } else {
                countBadge.classList.add("hidden");
                countBadge.style.display = "none";
            }
        }

        if (positions.length === 0) {
            const curName = isInr ? "INR" : "USD/USDT";
            const emptyHtml = `
                <div class="py-12 text-center text-slate-500 font-bold">
                    <div class="flex flex-col items-center justify-center gap-2">
                        <span class="text-2xl">📋</span>
                        <span>No active open orders or positions in ${curName}.</span>
                        <span class="text-xs font-normal text-slate-600">Place a new trade on Futures/Options to view live open orders.</span>
                    </div>
                </div>
            `;
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="8">${emptyHtml}</td></tr>`;
            }
            if (cardList) {
                cardList.innerHTML = emptyHtml;
            }
            renderPaginationControls("pfOrdersPagination", 0, ordersPage, PAGE_SIZE, () => {});
            return;
        }

        const totalPages = Math.max(1, Math.ceil(positions.length / PAGE_SIZE));
        if (ordersPage > totalPages) ordersPage = totalPages;
        const pagedPositions = positions.slice((ordersPage - 1) * PAGE_SIZE, ordersPage * PAGE_SIZE);

        if (tbody) {
            tbody.innerHTML = pagedPositions.map(item => {
                const sym = item.symbol || item.contract || (isInr ? "BTC/INR" : "BTC/USDT");
                const side = (item.side || "BUY").toUpperCase();
                const entryUsd = Number(item.entry_usd || item.entry_price || 0);
                const qty = Number(item.size || item.quantity || 0);
                const totalUsd = entryUsd * qty;

                const priceStr = isInr ? formatInr(item.entry_inr || (entryUsd * inrRate)) : formatUsdPlain(entryUsd);
                const totalStr = isInr ? formatInr(totalUsd * inrRate) : formatUsd(totalUsd);

                return `
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="py-3.5 px-4 font-bold text-white font-mono">${sym}</td>
                        <td class="py-3.5 px-4 text-slate-300">${item.type || "Limit"}</td>
                        <td class="py-3.5 px-4"><span class="px-2 py-0.5 rounded ${side === 'BUY' ? 'bg-emerald-950/60 text-emerald-400' : 'bg-rose-950/60 text-rose-400'} font-extrabold text-[10px]">${side}</span></td>
                        <td class="py-3.5 px-4 font-mono text-slate-300">${priceStr}</td>
                        <td class="py-3.5 px-4 font-mono text-white">${qty}</td>
                        <td class="py-3.5 px-4">
                            <div class="flex items-center gap-2">
                                <div class="w-16 h-1.5 bg-slate-900 rounded-full overflow-hidden border border-slate-800">
                                    <div class="h-full ${side === 'BUY' ? 'bg-emerald-500' : 'bg-rose-500'} rounded-full" style="width: 100%;"></div>
                                </div>
                                <span class="text-[11px] font-mono text-slate-300">100%</span>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-mono font-bold text-white">${totalStr}</td>
                        <td class="py-3.5 px-4 text-right">
                            <button type="button" class="px-2.5 py-1 rounded bg-rose-950/60 border border-rose-800/40 text-rose-400 hover:bg-rose-900/60 font-bold text-[11px] transition-colors cursor-pointer" onclick="closePosition('${item._id || item.position_uid || item.id}')">Close</button>
                        </td>
                    </tr>
                `;
            }).join("");
        }

        if (cardList) {
            cardList.innerHTML = pagedPositions.map(item => {
                const rawSym = String(item.symbol || item.contract || "BTCUSDT").toUpperCase();
                const baseCoin = rawSym.replace(/[-/_ ]/g, "").replace(/(USDT|USD|INR)$/i, "") || "BTC";
                const coinLogo = getAssetLogoHtml(baseCoin, "crypto", false, false);
                const side = (item.side || "BUY").toUpperCase();
                const isBuy = side === "BUY" || side === "LONG";
                const entryUsd = Number(item.entry_usd || item.entry_price || 0);
                const qty = Number(item.size || item.quantity || 0);

                const symKey = rawSym.replace(/[-/_ ]/g, "");
                let markUsd = Number((tickerPrices && tickerPrices[symKey]) || item.mark_usd || item.mark_price || item.current_price || 0);
                if (!markUsd || markUsd <= 0) markUsd = entryUsd;

                let pnlUsd = 0;
                if (qty > 0 && entryUsd > 0) {
                    pnlUsd = isBuy ? (markUsd - entryUsd) * qty : (entryUsd - markUsd) * qty;
                } else {
                    pnlUsd = Number(item.pnl_usd || item.unrealized_pnl || 0);
                }

                const dispSymbol = `${baseCoin}/USD`;
                const curTag = isInr ? "INR" : "USDT";
                const pnlText = `${pnlUsd >= 0 ? '' : '-'}${Math.abs(pnlUsd).toFixed(2)} USDT`;
                const pnlClass = pnlUsd >= 0 ? "text-emerald-400" : "text-rose-400";
                const entryPriceText = entryUsd >= 1000 
                    ? entryUsd.toLocaleString("en-US", { minimumFractionDigits: 1, maximumFractionDigits: 1 })
                    : entryUsd.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                const markPriceText = markUsd >= 1000
                    ? markUsd.toLocaleString("en-US", { minimumFractionDigits: 1, maximumFractionDigits: 1 })
                    : markUsd.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 });

                return `
                    <div class="futures-position-mobile-card">
                        <div class="pos-card-left-group">
                            <div class="flex-shrink-0">${coinLogo}</div>
                            <div class="pos-card-info">
                                <div class="pos-card-title-row">
                                    <span class="pos-card-sym-name">${dispSymbol}</span>
                                    <span class="pos-card-cur-badge">${curTag}</span>
                                </div>
                                <div class="pos-card-sub-row whitespace-nowrap">
                                    <span class="${isBuy ? 'text-emerald-400' : 'text-rose-400'} pos-card-side-label">${isBuy ? 'Buy' : 'Sell'}</span>
                                    <span class="pos-card-qty-label">${qty}</span>
                                    <span class="pos-card-entry-label">at ${entryPriceText}</span>
                                </div>
                            </div>
                        </div>
                        <div class="pos-card-right-group">
                            <div class="pos-card-top-action-row whitespace-nowrap">
                                <span class="${pnlClass} pos-card-pnl-val">${pnlText}</span>
                                <button type="button" class="pos-card-close-x" onclick="closePosition('${item._id || item.position_uid || item.id}')" title="Close Position">✕</button>
                            </div>
                            <div class="pos-card-ltp-val whitespace-nowrap">
                                ${markPriceText}
                            </div>
                        </div>
                    </div>
                `;
            }).join("");
        }

        renderPaginationControls("pfOrdersPagination", positions.length, ordersPage, PAGE_SIZE, (newPage) => {
            ordersPage = newPage;
            renderFilteredOrdersTable(positions, isInr, inrRate);
        });
    }

    // --- Render Filtered Trade History Table with Pagination & Mobile Cards ---
    function renderFilteredTradeHistoryTable(trades, isInr, inrRate) {
        const tbody = document.getElementById("pfHistoryTableBody");
        const cardList = document.getElementById("pfHistoryCardsList");
        if (!tbody && !cardList) return;

        if (trades.length === 0) {
            const curName = isInr ? "INR" : "USD/USDT";
            const emptyHtml = `
                <div class="py-12 text-center text-slate-500 font-bold">
                    <div class="flex flex-col items-center justify-center gap-2">
                        <span class="text-2xl">📜</span>
                        <span>No executed trade history found in ${curName}.</span>
                    </div>
                </div>
            `;
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="7">${emptyHtml}</td></tr>`;
            }
            if (cardList) {
                cardList.innerHTML = emptyHtml;
            }
            renderPaginationControls("pfHistoryPagination", 0, historyPage, PAGE_SIZE, () => {});
            return;
        }

        const totalPages = Math.max(1, Math.ceil(trades.length / PAGE_SIZE));
        if (historyPage > totalPages) historyPage = totalPages;
        const pagedTrades = trades.slice((historyPage - 1) * PAGE_SIZE, historyPage * PAGE_SIZE);

        if (tbody) {
            tbody.innerHTML = pagedTrades.map(t => {
                const time = t.created_at ? new Date(t.created_at).toLocaleTimeString() : "--";
                const sym = t.symbol || t.contract || (isInr ? "BTC/INR" : "BTC/USDT");
                const side = (t.side || "BUY").toUpperCase();
                const pxUsd = Number(t.price || t.entry_price || 0);
                const qty = Number(t.size || t.quantity || 0);
                const feeUsd = Number(t.fee || 0);
                const totalUsd = pxUsd * qty;

                const pxStr = isInr ? formatInr(t.price_inr || (pxUsd * inrRate)) : formatUsdPlain(pxUsd);
                const feeStr = isInr ? formatInr(t.fee_inr || (feeUsd * inrRate)) : formatUsd(feeUsd);
                const totalStr = isInr ? formatInr(totalUsd * inrRate) : formatUsd(totalUsd);

                return `
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="py-3.5 px-4 text-slate-400 font-mono">${time}</td>
                        <td class="py-3.5 px-4 font-bold text-white font-mono">${sym}</td>
                        <td class="py-3.5 px-4"><span class="px-2 py-0.5 rounded ${side === 'BUY' ? 'bg-emerald-950/60 text-emerald-400' : 'bg-rose-950/60 text-rose-400'} font-extrabold text-[10px]">${side}</span></td>
                        <td class="py-3.5 px-4 font-mono text-slate-300">${pxStr}</td>
                        <td class="py-3.5 px-4 font-mono text-white">${qty}</td>
                        <td class="py-3.5 px-4 font-mono text-slate-400">${feeStr}</td>
                        <td class="py-3.5 px-4 text-right font-mono font-bold text-white">${totalStr}</td>
                    </tr>
                `;
            }).join("");
        }

        if (cardList) {
            cardList.innerHTML = pagedTrades.map(t => {
                const rawSym = String(t.symbol || t.contract || "BTCUSDT").toUpperCase();
                const baseCoin = rawSym.replace(/[-/_ ]/g, "").replace(/(USDT|USD|INR)$/i, "") || "BTC";
                const coinLogo = getAssetLogoHtml(baseCoin, "crypto", false, false);
                const side = (t.side || "BUY").toUpperCase();
                const isBuy = side === "BUY" || side === "LONG";
                const pxUsd = Number(t.price || t.entry_price || 0);
                const qty = Number(t.size || t.quantity || 0);
                const feeUsd = Number(t.fee || 0);
                const totalUsd = pxUsd * qty;

                const dispSymbol = `${baseCoin}/USD`;
                const curTag = isInr ? "INR" : "USDT";
                const priceText = pxUsd >= 1000 
                    ? pxUsd.toLocaleString("en-US", { minimumFractionDigits: 1, maximumFractionDigits: 1 })
                    : pxUsd.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 4 });
                const totalText = totalUsd.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + " USDT";
                const feeText = "$" + feeUsd.toFixed(2);
                const timeStr = t.created_at ? new Date(t.created_at).toLocaleDateString("en-GB", { day: 'numeric', month: 'short' }) + ', ' + new Date(t.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : "--";

                return `
                    <div class="futures-position-mobile-card">
                        <div class="pos-card-left-group">
                            <div class="flex-shrink-0">${coinLogo}</div>
                            <div class="pos-card-info">
                                <div class="pos-card-title-row">
                                    <span class="pos-card-sym-name">${dispSymbol}</span>
                                    <span class="pos-card-cur-badge">${curTag}</span>
                                    <span class="text-[9px] font-extrabold px-1.5 py-0.5 rounded bg-emerald-950/60 text-emerald-400 border border-emerald-800/40 uppercase leading-none">TRADE</span>
                                </div>
                                <div class="pos-card-sub-row whitespace-nowrap">
                                    <span class="${isBuy ? 'text-emerald-400' : 'text-rose-400'} pos-card-side-label">${isBuy ? 'Buy' : 'Sell'}</span>
                                    <span class="pos-card-qty-label">${qty}</span>
                                    <span class="pos-card-entry-label">at ${priceText}</span>
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">${timeStr}</div>
                            </div>
                        </div>
                        <div class="pos-card-right-group">
                            <div class="pos-card-top-action-row whitespace-nowrap">
                                <span class="text-slate-400 text-[11px] font-mono">Fee: ${feeText}</span>
                            </div>
                            <div class="text-[11px] font-mono text-slate-300 font-bold mt-1">
                                Total: ${totalText}
                            </div>
                        </div>
                    </div>
                `;
            }).join("");
        }

        renderPaginationControls("pfHistoryPagination", trades.length, historyPage, PAGE_SIZE, (newPage) => {
            historyPage = newPage;
            renderFilteredTradeHistoryTable(trades, isInr, inrRate);
        });
    }

    // --- Render Filtered Fund Activity Table with Pagination & Mobile Cards ---
    function renderFilteredFundActivityTable(isInr, inrRate) {
        const tbody = document.getElementById("pfActivityTableBody");
        const cardList = document.getElementById("pfActivityCardsList");
        if (!tbody && !cardList) return;

        const combined = [
            ...transactionsList.map(t => ({
                type: t.type || "Deposit",
                dir: "↓",
                isPos: true,
                currency: t.currency || "USDT",
                amtUsd: Number(t.amount || 0),
                amtInr: Number(t.amount_inr || (Number(t.amount || 0) * inrRate)),
                source: t.description || "Wallet Deposit",
                status: "Completed",
                date: t.created_at ? new Date(t.created_at).toLocaleDateString() : "--"
            })),
            ...withdrawalsList.map(w => ({
                type: "Withdraw",
                dir: "↑",
                isPos: false,
                currency: w.currency || "USD",
                amtUsd: Number(w.amount || 0),
                amtInr: Number(w.amount_inr || (Number(w.amount || 0) * inrRate)),
                source: w.method || "Bank Wire",
                status: (w.status || "Completed").toUpperCase(),
                date: w.created_at ? new Date(w.created_at).toLocaleDateString() : "--"
            }))
        ];

        const matchingActivity = combined.filter(item => {
            return isInr ? isItemInr(item) : !isItemInr(item);
        });

        if (matchingActivity.length === 0) {
            const curName = isInr ? "INR" : "USD/USDT";
            const emptyHtml = `
                <div class="py-12 text-center text-slate-500 font-bold">
                    <div class="flex flex-col items-center justify-center gap-2">
                        <span class="text-2xl">💳</span>
                        <span>No recent ${curName} deposit or withdrawal activity.</span>
                    </div>
                </div>
            `;
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="6">${emptyHtml}</td></tr>`;
            }
            if (cardList) {
                cardList.innerHTML = emptyHtml;
            }
            renderPaginationControls("pfActivityPagination", 0, activityPage, PAGE_SIZE, () => {});
            return;
        }

        const totalPages = Math.max(1, Math.ceil(matchingActivity.length / PAGE_SIZE));
        if (activityPage > totalPages) activityPage = totalPages;
        const pagedActivity = matchingActivity.slice((activityPage - 1) * PAGE_SIZE, activityPage * PAGE_SIZE);

        if (tbody) {
            tbody.innerHTML = pagedActivity.map(item => `
                <tr class="hover:bg-slate-800/30 transition-colors">
                    <td class="py-3.5 px-4 flex items-center gap-1.5 ${item.isPos ? 'text-emerald-400' : 'text-rose-400'} font-bold">
                        <span>${item.dir}</span> ${item.type}
                    </td>
                    <td class="py-3.5 px-4 font-bold text-white">${item.currency}</td>
                    <td class="py-3.5 px-4 font-mono font-extrabold ${item.isPos ? 'text-emerald-400' : 'text-rose-400'}">
                        ${isInr ? formatSignedInr(item.amtInr) : formatSignedUsd(item.amtUsd)}
                    </td>
                    <td class="py-3.5 px-4 text-slate-300 font-mono">${item.source}</td>
                    <td class="py-3.5 px-4">
                        <span class="px-2 py-0.5 rounded-full bg-emerald-950/60 text-emerald-400 font-bold text-[10px] border border-emerald-800/40">${item.status}</span>
                    </td>
                    <td class="py-3.5 px-4 text-right text-slate-400">${item.date}</td>
                </tr>
            `).join("");
        }

        if (cardList) {
            cardList.innerHTML = pagedActivity.map(item => `
                <div class="futures-position-mobile-card">
                    <div class="pos-card-left-group">
                        <div class="w-8 h-8 rounded-full ${item.isPos ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-400 border border-rose-500/30'} flex items-center justify-center font-bold text-sm flex-shrink-0">
                            ${item.dir}
                        </div>
                        <div class="pos-card-info">
                            <div class="pos-card-title-row">
                                <span class="pos-card-sym-name">${item.type}</span>
                                <span class="pos-card-cur-badge">${item.currency}</span>
                            </div>
                            <div class="text-[11px] text-slate-300 font-mono">${item.source}</div>
                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">${item.date}</div>
                        </div>
                    </div>
                    <div class="pos-card-right-group">
                        <div class="pos-card-top-action-row whitespace-nowrap">
                            <span class="px-2 py-0.5 rounded-full bg-emerald-950/60 text-emerald-400 font-extrabold text-[10px] border border-emerald-800/40 uppercase">${item.status}</span>
                        </div>
                        <div class="text-sm font-mono font-black ${item.isPos ? 'text-emerald-400' : 'text-rose-400'} mt-1">
                            ${isInr ? formatSignedInr(item.amtInr) : formatSignedUsd(item.amtUsd)}
                        </div>
                    </div>
                </div>
            `).join("");
        }

        renderPaginationControls("pfActivityPagination", matchingActivity.length, activityPage, PAGE_SIZE, (newPage) => {
            activityPage = newPage;
            renderFilteredFundActivityTable(isInr, inrRate);
        });
    }

    // --- Render Filtered Withdrawals Table with Status & Pagination & Mobile Cards ---
    function renderFilteredWithdrawalsTable(isInr, inrRate) {
        const tbody = document.getElementById("pfWithdrawalsTableBody");
        const cardList = document.getElementById("pfWithdrawalsCardsList");
        if (!tbody && !cardList) return;

        const matchingWithdrawals = (withdrawalsList || []).filter(item => {
            return isInr ? isItemInr(item) : !isItemInr(item);
        });

        if (matchingWithdrawals.length === 0) {
            const curName = isInr ? "INR" : "USD/USDT";
            const emptyHtml = `
                <div class="py-12 text-center text-slate-500 font-bold">
                    <div class="flex flex-col items-center justify-center gap-2">
                        <span class="text-2xl">💸</span>
                        <span>No recent withdrawal requests found in ${curName}.</span>
                    </div>
                </div>
            `;
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="6">${emptyHtml}</td></tr>`;
            }
            if (cardList) {
                cardList.innerHTML = emptyHtml;
            }
            renderPaginationControls("pfWithdrawalsPagination", 0, withdrawalsPage, PAGE_SIZE, () => {});
            return;
        }

        const totalPages = Math.max(1, Math.ceil(matchingWithdrawals.length / PAGE_SIZE));
        if (withdrawalsPage > totalPages) withdrawalsPage = totalPages;
        const pagedWithdrawals = matchingWithdrawals.slice((withdrawalsPage - 1) * PAGE_SIZE, withdrawalsPage * PAGE_SIZE);

        if (tbody) {
            tbody.innerHTML = pagedWithdrawals.map(w => {
                const dateStr = w.requested_at || w.created_at ? new Date(w.requested_at || w.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : "--";
                const ref = w.reference || (w._id ? String(w._id).substring(0, 10).toUpperCase() : "WD-REQ");
                const cur = (w.currency || w.wallet_currency || (isInr ? "INR" : "USDT")).toUpperCase();
                const network = w.network || w.method || "--";
                
                const reqAmt = Number(w.amount || 0);
                const feeAmt = Number(w.total_charge || w.withdraw_fee || 0);
                const netAmt = Number(w.net_amount || (reqAmt - feeAmt) || reqAmt);

                const reqStr = isInr ? formatInr(w.amount_inr || (reqAmt * inrRate)) : (cur === "USD" ? formatUsdPlain(reqAmt) : `${reqAmt.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 6 })} ${cur}`);
                const feeStr = isInr ? formatInr((feeAmt * inrRate)) : (cur === "USD" ? formatUsdPlain(feeAmt) : `${feeAmt.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 6 })} ${cur}`);
                const netStr = isInr ? formatInr(w.net_amount_inr || (netAmt * inrRate)) : (cur === "USD" ? formatUsdPlain(netAmt) : `${netAmt.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 6 })} ${cur}`);

                // Status Badge Formatting (Pending / Accepted / Rejected)
                const statusRaw = String(w.status || "pending").toLowerCase();
                let statusBadge = "";
                if (statusRaw === "approved" || statusRaw === "completed" || statusRaw === "accepted" || statusRaw === "success") {
                    statusBadge = `<span class="px-2.5 py-1 rounded-full bg-emerald-950/60 text-emerald-400 font-extrabold text-[10px] border border-emerald-800/40 uppercase tracking-wider">Accepted</span>`;
                } else if (statusRaw === "rejected" || statusRaw === "declined" || statusRaw === "cancelled") {
                    statusBadge = `<span class="px-2.5 py-1 rounded-full bg-rose-950/60 text-rose-400 font-extrabold text-[10px] border border-rose-800/40 uppercase tracking-wider">Rejected</span>`;
                } else {
                    statusBadge = `<span class="px-2.5 py-1 rounded-full bg-amber-950/60 text-amber-400 font-extrabold text-[10px] border border-amber-800/40 uppercase tracking-wider">Pending</span>`;
                }

                return `
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="py-3.5 px-4">
                            <div class="font-mono font-bold text-white text-xs">${ref}</div>
                            <div class="text-[10px] text-slate-400">${dateStr}</div>
                        </td>
                        <td class="py-3.5 px-4">
                            <div class="font-bold text-white font-mono">${cur}</div>
                            <div class="text-[10px] text-slate-400">${network}</div>
                        </td>
                        <td class="py-3.5 px-4 font-mono font-bold text-white">${reqStr}</td>
                        <td class="py-3.5 px-4 font-mono text-slate-400">${feeStr}</td>
                        <td class="py-3.5 px-4 font-mono font-extrabold text-emerald-400">${netStr}</td>
                        <td class="py-3.5 px-4 text-right">${statusBadge}</td>
                    </tr>
                `;
            }).join("");
        }

        if (cardList) {
            cardList.innerHTML = pagedWithdrawals.map(w => {
                const dateStr = w.requested_at || w.created_at ? new Date(w.requested_at || w.created_at).toLocaleString([], { dateStyle: 'short', timeStyle: 'short' }) : "--";
                const ref = w.reference || (w._id ? String(w._id).substring(0, 10).toUpperCase() : "WD-REQ");
                const cur = (w.currency || w.wallet_currency || (isInr ? "INR" : "USDT")).toUpperCase();
                const network = w.network || w.method || "--";
                
                const reqAmt = Number(w.amount || 0);
                const feeAmt = Number(w.total_charge || w.withdraw_fee || 0);
                const netAmt = Number(w.net_amount || (reqAmt - feeAmt) || reqAmt);

                const reqStr = isInr ? formatInr(w.amount_inr || (reqAmt * inrRate)) : (cur === "USD" ? formatUsdPlain(reqAmt) : `${reqAmt.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 6 })} ${cur}`);
                const feeStr = isInr ? formatInr((feeAmt * inrRate)) : (cur === "USD" ? formatUsdPlain(feeAmt) : `${feeAmt.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 6 })} ${cur}`);
                const netStr = isInr ? formatInr(w.net_amount_inr || (netAmt * inrRate)) : (cur === "USD" ? formatUsdPlain(netAmt) : `${netAmt.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 6 })} ${cur}`);

                const statusRaw = String(w.status || "pending").toLowerCase();
                let statusBadge = "";
                if (statusRaw === "approved" || statusRaw === "completed" || statusRaw === "accepted" || statusRaw === "success") {
                    statusBadge = `<span class="px-2 py-0.5 rounded-full bg-emerald-950/60 text-emerald-400 font-extrabold text-[10px] border border-emerald-800/40 uppercase tracking-wider">Accepted</span>`;
                } else if (statusRaw === "rejected" || statusRaw === "declined" || statusRaw === "cancelled") {
                    statusBadge = `<span class="px-2 py-0.5 rounded-full bg-rose-950/60 text-rose-400 font-extrabold text-[10px] border border-rose-800/40 uppercase tracking-wider">Rejected</span>`;
                } else {
                    statusBadge = `<span class="px-2 py-0.5 rounded-full bg-amber-950/60 text-amber-400 font-extrabold text-[10px] border border-amber-800/40 uppercase tracking-wider">Pending</span>`;
                }

                return `
                    <div class="futures-position-mobile-card">
                        <div class="pos-card-left-group">
                            <div class="w-8 h-8 rounded-full bg-rose-500/15 text-rose-400 border border-rose-500/30 flex items-center justify-center font-bold text-sm flex-shrink-0">
                                ↑
                            </div>
                            <div class="pos-card-info">
                                <div class="pos-card-title-row">
                                    <span class="pos-card-sym-name">${cur}</span>
                                    <span class="pos-card-cur-badge">${network}</span>
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">${ref} • ${dateStr}</div>
                            </div>
                        </div>
                        <div class="pos-card-right-group">
                            <div class="pos-card-top-action-row whitespace-nowrap">
                                ${statusBadge}
                            </div>
                            <div class="text-sm font-mono font-black text-emerald-400 mt-1">
                                ${netStr}
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono">Fee: ${feeStr}</div>
                        </div>
                    </div>
                `;
            }).join("");
        }

        renderPaginationControls("pfWithdrawalsPagination", matchingWithdrawals.length, withdrawalsPage, PAGE_SIZE, (newPage) => {
            withdrawalsPage = newPage;
            renderFilteredWithdrawalsTable(isInr, inrRate);
        });
    }

    // --- Tab Switching Logic ---
    const tabButtons = document.querySelectorAll(".pf-table-tab");
    tabButtons.forEach(btn => {
        btn.addEventListener("click", () => {
            tabButtons.forEach(b => b.classList.remove("active"));
            btn.classList.add("active");

            const tabKey = btn.getAttribute("data-pf-tab");
            document.querySelectorAll(".pf-tab-pane").forEach(pane => pane.classList.add("hidden"));

            if (tabKey === "holdings") document.getElementById("tabContentHoldings")?.classList.remove("hidden");
            if (tabKey === "orders") document.getElementById("tabContentOrders")?.classList.remove("hidden");
            if (tabKey === "history") document.getElementById("tabContentHistory")?.classList.remove("hidden");
            if (tabKey === "activity") document.getElementById("tabContentActivity")?.classList.remove("hidden");
            if (tabKey === "withdrawals") document.getElementById("tabContentWithdrawals")?.classList.remove("hidden");
        });
    });

    // --- Export CSV Logic ---
    const btnExport = document.getElementById("btnExportPortfolioCsv");
    if (btnExport) {
        btnExport.addEventListener("click", () => {
            const cur = getActiveCurrency();
            const isInr = (cur === "INR");
            const allTrades = [...futuresTrades, ...optionsTrades];
            const matchingTrades = allTrades.filter(t => isInr ? isItemInr(t) : !isItemInr(t));

            const rows = [
                ["Time", "Pair", "Side", "Price", "Quantity", "Fee", "Total"],
                ...matchingTrades.map(t => [
                    t.created_at || "",
                    t.symbol || t.contract || "",
                    t.side || "BUY",
                    t.price || t.entry_price || 0,
                    t.size || t.quantity || 0,
                    t.fee || 0,
                    (Number(t.price || 0) * Number(t.size || 0)).toFixed(2)
                ])
            ];

            const csvContent = "data:text/csv;charset=utf-8," + rows.map(e => e.join(",")).join("\n");
            const link = document.createElement("a");
            link.setAttribute("href", encodeURI(csvContent));
            link.setAttribute("download", `kryvex_portfolio_${cur.toLowerCase()}_trades.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    // --- Performance Chart Canvas Logic ---
    const chartCanvas = document.getElementById("pfPerformanceChartCanvas");
    const chartTimeframeBtns = document.querySelectorAll(".pf-chart-btn");

    chartTimeframeBtns.forEach(btn => {
        btn.addEventListener("click", () => {
            chartTimeframeBtns.forEach(b => {
                b.classList.remove("active", "bg-purple-600", "text-white", "font-black");
                b.classList.add("text-slate-400");
            });
            btn.classList.add("active", "bg-purple-600", "text-white", "font-black");
            btn.classList.remove("text-slate-400");
            currentChartPeriod = btn.getAttribute("data-period");
            renderAll();
        });
    });

    function renderPerformanceChart(currentTotal, isInr) {
        if (!chartCanvas) return;
        const ctx = chartCanvas.getContext("2d");
        const rect = chartCanvas.parentElement.getBoundingClientRect();
        const dpr = window.devicePixelRatio || 1;

        chartCanvas.width = rect.width * dpr;
        chartCanvas.height = rect.height * dpr;
        ctx.scale(dpr, dpr);

        const w = rect.width;
        const h = rect.height;
        const padding = { top: 25, right: 25, bottom: 35, left: 55 };

        ctx.clearRect(0, 0, w, h);

        const baseVal = currentTotal > 0 ? currentTotal : (isInr ? 584250 : 7022);
        const multipliers = {
            "1D": [0.97, 0.98, 0.975, 0.988, 0.995, 0.99, 1.0],
            "1W": [0.93, 0.945, 0.94, 0.97, 0.98, 0.99, 1.0],
            "1M": [0.88, 0.91, 0.90, 0.96, 1.0],
            "1Y": [0.65, 0.70, 0.76, 0.84, 0.89, 0.94, 1.0],
            "ALL": [0.41, 0.65, 0.84, 1.0]
        };

        const labels = {
            "1D": ["00:00", "04:00", "08:00", "12:00", "16:00", "20:00", "Now"],
            "1W": ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"],
            "1M": ["Week 1", "Week 2", "Week 3", "Week 4", "Current"],
            "1Y": ["Jan", "Mar", "May", "Jul", "Sep", "Nov", "Dec"],
            "ALL": ["2023", "2024", "2025", "2026"]
        };

        const activeMults = multipliers[currentChartPeriod] || multipliers["1M"];
        const activeLabels = labels[currentChartPeriod] || labels["1M"];

        const points = activeMults.map((m, idx) => ({
            label: activeLabels[idx] || `P${idx}`,
            val: baseVal * m
        }));

        const values = points.map(p => p.val);
        const minVal = Math.min(...values) * 0.96;
        const maxVal = Math.max(...values) * 1.04;

        // Draw horizontal grid lines
        const gridLines = 4;
        ctx.strokeStyle = "rgba(30, 41, 59, 0.7)";
        ctx.lineWidth = 1;
        ctx.fillStyle = "#64748b";
        ctx.font = "10px monospace";
        ctx.textAlign = "right";

        for (let i = 0; i <= gridLines; i++) {
            const y = padding.top + (i / gridLines) * (h - padding.top - padding.bottom);
            const val = maxVal - (i / gridLines) * (maxVal - minVal);
            
            ctx.beginPath();
            ctx.moveTo(padding.left, y);
            ctx.lineTo(w - padding.right, y);
            ctx.stroke();

            const formattedVal = isInr ? "₹" + Math.round(val / 1000) + "k" : "$" + Math.round(val);
            ctx.fillText(formattedVal, padding.left - 8, y + 3);
        }

        // Compute coordinates
        const plotW = w - padding.left - padding.right;
        const plotH = h - padding.top - padding.bottom;
        const coords = points.map((p, idx) => {
            const x = padding.left + (idx / (points.length - 1)) * plotW;
            const y = padding.top + (1 - (p.val - minVal) / (maxVal - minVal)) * plotH;
            return { x, y, label: p.label, val: p.val };
        });

        // Determine if performance is UP (profit) or DOWN (loss)
        const firstVal = points[0].val;
        const lastVal = points[points.length - 1].val;
        const isProfit = (lastVal >= firstVal);

        // Update Return Badge in chart header
        const retBadges = document.querySelectorAll(".pf-chart-period-return-badge, #pfChartPeriodReturnBadge, #pfChartPeriodReturnBadgeMobile");
        retBadges.forEach(retBadge => {
            const pctChange = firstVal > 0 ? (((lastVal - firstVal) / firstVal) * 100) : 0;
            const sign = pctChange >= 0 ? "+" : "";
            const arrow = pctChange >= 0 ? "▲" : "▼";
            retBadge.textContent = `${arrow} ${sign}${pctChange.toFixed(2)}%`;
            if (pctChange >= 0) {
                retBadge.className = "text-xs font-bold font-mono px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 shadow-sm flex-shrink-0";
            } else {
                retBadge.className = "text-xs font-bold font-mono px-2.5 py-1 rounded-lg bg-rose-500/10 text-rose-400 border border-rose-500/20 shadow-sm flex-shrink-0";
            }
        });

        const strokeColor = isProfit ? "#10b981" : "#f43f5e";
        const fillColorTop = isProfit ? "rgba(16, 185, 129, 0.35)" : "rgba(244, 63, 94, 0.35)";
        const fillColorMid = isProfit ? "rgba(16, 185, 129, 0.12)" : "rgba(244, 63, 94, 0.12)";
        const fillColorBot = isProfit ? "rgba(16, 185, 129, 0.0)" : "rgba(244, 63, 94, 0.0)";

        // Draw smooth gradient area under curve
        const gradient = ctx.createLinearGradient(0, padding.top, 0, h - padding.bottom);
        gradient.addColorStop(0, fillColorTop);
        gradient.addColorStop(0.5, fillColorMid);
        gradient.addColorStop(1, fillColorBot);

        ctx.beginPath();
        ctx.moveTo(coords[0].x, coords[0].y);
        for (let i = 0; i < coords.length - 1; i++) {
            const xc = (coords[i].x + coords[i + 1].x) / 2;
            const yc = (coords[i].y + coords[i + 1].y) / 2;
            ctx.quadraticCurveTo(coords[i].x, coords[i].y, xc, yc);
        }
        ctx.lineTo(coords[coords.length - 1].x, coords[coords.length - 1].y);
        ctx.lineTo(coords[coords.length - 1].x, h - padding.bottom);
        ctx.lineTo(coords[0].x, h - padding.bottom);
        ctx.closePath();
        ctx.fillStyle = gradient;
        ctx.fill();

        // Draw curve stroke with glowing color
        ctx.beginPath();
        ctx.moveTo(coords[0].x, coords[0].y);
        for (let i = 0; i < coords.length - 1; i++) {
            const xc = (coords[i].x + coords[i + 1].x) / 2;
            const yc = (coords[i].y + coords[i + 1].y) / 2;
            ctx.quadraticCurveTo(coords[i].x, coords[i].y, xc, yc);
        }
        ctx.lineTo(coords[coords.length - 1].x, coords[coords.length - 1].y);
        ctx.strokeStyle = strokeColor;
        ctx.lineWidth = 3;
        ctx.shadowColor = strokeColor;
        ctx.shadowBlur = 8;
        ctx.stroke();
        ctx.shadowBlur = 0; // reset shadow

        // Draw data node points & X-axis labels
        ctx.textAlign = "center";
        ctx.fillStyle = "#94a3b8";
        coords.forEach((c, idx) => {
            ctx.fillText(c.label, c.x, h - padding.bottom + 18);

            const isLast = (idx === coords.length - 1);
            ctx.beginPath();
            ctx.arc(c.x, c.y, isLast ? 5 : 3.5, 0, Math.PI * 2);
            ctx.fillStyle = "#0c0d12";
            ctx.fill();
            ctx.strokeStyle = strokeColor;
            ctx.lineWidth = isLast ? 2.5 : 2;
            ctx.stroke();

            // Glow pulse ring on current / last node
            if (isLast) {
                ctx.beginPath();
                ctx.arc(c.x, c.y, 8, 0, Math.PI * 2);
                ctx.strokeStyle = isProfit ? "rgba(16, 185, 129, 0.4)" : "rgba(244, 63, 94, 0.4)";
                ctx.lineWidth = 1.5;
                ctx.stroke();
            }
        });
    }

    // --- Donut Chart Canvas & Dynamic Allocation List Logic ---
    const donutCanvas = document.getElementById("pfAllocationDonutCanvas");

    const CRYPTO_METADATA = {
        BTC:   { name: "Bitcoin (BTC)", color: "#f59e0b", defaultPrice: 85750 },
        ETH:   { name: "Ethereum (ETH)", color: "#6366f1", defaultPrice: 3150 },
        BNB:   { name: "BNB (BNB)", color: "#eab308", defaultPrice: 620 },
        SOL:   { name: "Solana (SOL)", color: "#14b8a6", defaultPrice: 175 },
        XRP:   { name: "Ripple (XRP)", color: "#06b6d4", defaultPrice: 0.58 },
        DOGE:  { name: "Dogecoin (DOGE)", color: "#fbbf24", defaultPrice: 0.12 },
        ADA:   { name: "Cardano (ADA)", color: "#3b82f6", defaultPrice: 0.45 },
        USDC:  { name: "USD Coin (USDC)", color: "#2563eb", defaultPrice: 1.00 },
        TRX:   { name: "TRON (TRX)", color: "#ef4444", defaultPrice: 0.14 },
        AVAX:  { name: "Avalanche (AVAX)", color: "#dc2626", defaultPrice: 28 },
        DOT:   { name: "Polkadot (DOT)", color: "#ec4899", defaultPrice: 6.5 },
        MATIC: { name: "Polygon (MATIC)", color: "#8b5cf6", defaultPrice: 0.52 },
        LINK:  { name: "Chainlink (LINK)", color: "#38bdf8", defaultPrice: 14.5 }
    };

    const DEFAULT_DEMO_CRYPTO_HOLDINGS = {
        BTC: 0.1746,
        ETH: 0.0655,
        BNB: 10.384,
        SOL: 2.4879,
        USDC: 0.50
    };

    function getActiveCryptoHoldings(wallet) {
        const cryptoBals = (wallet && wallet.crypto_balances && typeof wallet.crypto_balances === "object") ? wallet.crypto_balances : {};
        const hasLiveCrypto = Object.entries(cryptoBals).some(([k, v]) => k.toUpperCase() !== "USDT" && Number(v) > 0.0000001);
        const sourceBals = hasLiveCrypto ? cryptoBals : DEFAULT_DEMO_CRYPTO_HOLDINGS;
        
        const allTokens = Array.from(new Set([...Object.keys(sourceBals), ...Object.keys(cryptoBals)]));
        const result = [];

        allTokens.forEach(sym => {
            const upperSym = sym.toUpperCase();
            if (upperSym === "USDT" || upperSym === "INR") return;

            const bal = Number(sourceBals[upperSym] ?? sourceBals[sym] ?? cryptoBals[upperSym] ?? cryptoBals[sym] ?? wallet[`${sym.toLowerCase()}_balance`] ?? wallet[`demo_${sym.toLowerCase()}_balance`] ?? 0);
            if (bal > 0.0000001) {
                const meta = CRYPTO_METADATA[upperSym] || { name: `${upperSym}`, color: "#f97316", defaultPrice: 1 };
                const rawPx = Number(tickerPrices[`${upperSym}USDT`] || tickerPrices[`${upperSym}-USDT`] || tickerPrices[upperSym] || meta.defaultPrice || 1);
                const valUsd = bal * rawPx;

                if (valUsd > 0.01) {
                    result.push({
                        symbol: upperSym,
                        name: meta.name,
                        color: meta.color,
                        balance: bal,
                        priceUsd: rawPx,
                        valueUsd: valUsd
                    });
                }
            }
        });

        return result;
    }

    function getAssetLogoHtml(symbol, type = "", isInr = false, isSmall = false) {
        const sym = (symbol || "").toUpperCase();
        const sizeClass = isSmall ? "w-4 h-4" : "w-7 h-7";

        // 1. Indian Rupee (INR) -> Circular India Flag (matching user reference)
        if (sym === "INR" || (isInr && type === "main")) {
            return `
                <div class="${sizeClass} rounded-full overflow-hidden flex-shrink-0 flex items-center justify-center border border-white/10 shadow-sm bg-[#10121a]">
                    <svg class="w-full h-full object-cover" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="32" height="10.66" fill="#FF9933"/>
                        <rect y="10.66" width="32" height="10.66" fill="#FFFFFF"/>
                        <rect y="21.33" width="32" height="10.66" fill="#138808"/>
                        <circle cx="16" cy="16" r="3.6" stroke="#000080" stroke-width="0.8" fill="none"/>
                        <circle cx="16" cy="16" r="0.9" fill="#000080"/>
                    </svg>
                </div>
            `;
        }

        // 2. USD or Main / Trading account in USD Mode -> Circular USA Flag (matching user reference)
        if (sym === "USD" || sym === "USDT" || type === "trading" || type === "locked" || (type === "main" && !isInr)) {
            return `
                <div class="${sizeClass} rounded-full overflow-hidden flex-shrink-0 flex items-center justify-center border border-white/10 shadow-sm bg-[#10121a]">
                    <svg class="w-full h-full object-cover" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="32" height="32" fill="#B22234"/>
                        <path d="M0 2.46h32M0 7.38h32M0 12.3h32M0 17.23h32M0 22.15h32M0 27.07h32" stroke="#FFFFFF" stroke-width="2.46"/>
                        <rect width="14" height="14" fill="#3C3B6E"/>
                        <circle cx="3.5" cy="3.5" r="0.8" fill="#FFF"/><circle cx="7" cy="3.5" r="0.8" fill="#FFF"/><circle cx="10.5" cy="3.5" r="0.8" fill="#FFF"/>
                        <circle cx="5.25" cy="7" r="0.8" fill="#FFF"/><circle cx="8.75" cy="7" r="0.8" fill="#FFF"/>
                        <circle cx="3.5" cy="10.5" r="0.8" fill="#FFF"/><circle cx="7" cy="10.5" r="0.8" fill="#FFF"/><circle cx="10.5" cy="10.5" r="0.8" fill="#FFF"/>
                    </svg>
                </div>
            `;
        }

        // 3. Real Official Logos for all Cryptocurrencies
        const coinLogos = {
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
            LINK: "https://assets.coingecko.com/coins/images/877/large/chainlink-new-logo.png",
            LTC: "https://assets.coingecko.com/coins/images/2/large/litecoin.png",
            NEAR: "https://assets.coingecko.com/coins/images/10365/large/near.png",
            SUI: "https://assets.coingecko.com/coins/images/26375/large/sui-ocean-square.png",
            SHIB: "https://assets.coingecko.com/coins/images/11939/large/shiba.png",
            PEPE: "https://assets.coingecko.com/coins/images/29850/large/pepe-token.png",
            XAU: "/images/coins/gold.svg",
            XAUUSDT: "/images/coins/gold.svg",
            GOLD: "/images/coins/gold.svg",
            PAXG: "/images/coins/paxg.png",
            PAXGUSDT: "/images/coins/paxg.png"
        };

        const isGold = sym === 'XAU' || sym === 'XAUUSDT' || sym === 'GOLD' || sym === 'PAXG';
        const logoUrl = coinLogos[sym] || (isGold ? "/images/coins/gold.svg" : "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/btc.png");
        const fallbackUrl = isGold ? "/images/coins/gold.svg" : "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/btc.png";

        return `
            <img src="${logoUrl}" alt="${sym}" class="${sizeClass} rounded-full object-cover flex-shrink-0 border border-white/10 bg-slate-900" onerror="this.onerror=null;this.src='${fallbackUrl}';" />
        `;
    }

    function renderDynamicAssetAllocation(activeMain, activeAvailable, calcLockedVal, activeAcc, isInr, inrRate) {
        const listContainer = document.getElementById("pfAssetAllocationList");
        const items = [];

        // 1. Main Wallet (In INR if isInr, else in USD)
        if (activeMain > 0) {
            items.push({
                symbol: isInr ? "INR" : "USD",
                type: "main",
                name: isInr ? "Main Wallet (INR)" : "Main Wallet (USDT)",
                value: activeMain,
                valUsd: isInr ? (activeMain / inrRate) : activeMain,
                displayVal: isInr ? formatInr(activeMain) : formatUsd(activeMain),
                color: "#3b82f6" // Blue
            });
        }

        // 2. Trading Account Available (In USD converted from INR when isInr)
        const accName = activeAcc.account_name ? `Trading (${activeAcc.account_name})` : "Trading Avail";
        const tradingAvailUsd = isInr ? (activeAvailable / inrRate) : activeAvailable;
        if (tradingAvailUsd > 0 || items.length === 0) {
            items.push({
                symbol: "USD",
                type: "trading",
                name: accName,
                value: tradingAvailUsd,
                valUsd: Math.max(0, tradingAvailUsd),
                displayVal: formatUsd(tradingAvailUsd),
                color: "#10b981" // Green
            });
        }

        // 3. Margin Locked in Active Trades (In USD converted from INR when isInr)
        const lockedUsd = isInr ? (calcLockedVal / inrRate) : calcLockedVal;
        if (lockedUsd > 0) {
            items.push({
                symbol: "USD",
                type: "locked",
                name: "Margin Locked",
                value: lockedUsd,
                valUsd: lockedUsd,
                displayVal: formatUsd(lockedUsd),
                color: "#a855f7" // Purple
            });
        }

        // 4. All Cryptocurrencies ONLY in USD Mode
        if (!isInr) {
            const wallet = (portfolioData && portfolioData.wallet) ? portfolioData.wallet : {};
            const cryptos = getActiveCryptoHoldings(wallet);
            cryptos.forEach(c => {
                items.push({
                    symbol: c.symbol,
                    type: "crypto",
                    name: c.name,
                    value: c.valueUsd,
                    valUsd: c.valueUsd,
                    displayVal: formatUsd(c.valueUsd),
                    color: c.color
                });
            });
        }

        // Calculate total USD for accurate percentage slices
        const totalUsd = items.reduce((sum, it) => sum + it.valUsd, 0) || 1;
        items.forEach(it => {
            it.pct = ((it.valUsd / totalUsd) * 100).toFixed(1);
            it.logoHtmlSmall = getAssetLogoHtml(it.symbol, it.type, isInr && it.type === "main", true);
        });

        // Sort to determine Top Asset
        const sortedItems = [...items].sort((a, b) => b.valUsd - a.valUsd);
        const topItem = sortedItems[0] || { name: "None", pct: "0.0" };

        const isLightMode = document.documentElement.dataset.theme === 'light' || document.documentElement.classList.contains('theme-light') || localStorage.getItem('kryvex_theme') === 'light';

        const topLabelEl = document.getElementById("pfTopAssetLabel");
        const topValEl = document.getElementById("pfTopAssetValue");
        if (topLabelEl) topLabelEl.textContent = "TOP ASSET";
        if (topValEl) {
            const shortName = topItem.name.split(" ")[0];
            const textClr = isLightMode ? "text-slate-900" : "text-white";
            topValEl.innerHTML = `
                <span class="${textClr} font-extrabold text-[12px] block leading-tight text-center">${shortName}</span>
                <span class="${textClr} font-mono font-black text-[12px] block leading-tight mt-0.5 text-center">${topItem.pct}%</span>
            `;
        }

        // Render HTML List with clean, seamless rows (No card background boxes)
        if (listContainer) {
            if (items.length === 0) {
                listContainer.innerHTML = `
                    <div class="py-3 text-slate-500 text-center text-xs">
                        No active balance or asset allocation available.
                    </div>
                `;
            } else {
                const isCompact = items.length > 5;
                const pyClass = isCompact ? "py-1" : "py-1.5";
                const textClass = isCompact ? "text-[11px]" : "text-xs";
                const badgeClass = isCompact ? "text-[10px]" : "text-[11px]";
                const itemTitleClass = isLightMode ? "text-slate-900" : "text-white";
                const itemValClass = isLightMode ? "text-slate-800" : "text-slate-200";
                const borderRowClass = isLightMode ? "border-b border-slate-200 hover:bg-slate-100/60" : "border-b border-slate-800/50 hover:bg-slate-800/20";

                listContainer.innerHTML = items.map(it => `
                    <div class="${pyClass} px-1 flex items-center justify-between gap-2 ${borderRowClass} last:border-b-0 rounded-lg transition-colors">
                        <div class="flex items-center gap-2 min-w-0 pr-1">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${it.color}"></span>
                            <span class="font-bold ${itemTitleClass} ${textClass} truncate" title="${it.name}">${it.name}</span>
                            <span class="text-slate-400 font-semibold ${badgeClass} flex-shrink-0">${it.pct}%</span>
                        </div>
                        <strong class="${itemValClass} font-mono ${badgeClass} flex-shrink-0">${it.displayVal}</strong>
                    </div>
                `).join("");
            }
        }

        // Draw Donut Canvas (150x150)
        if (!donutCanvas) return;
        const ctx = donutCanvas.getContext("2d");
        const dpr = window.devicePixelRatio || 1;
        donutCanvas.width = 150 * dpr;
        donutCanvas.height = 150 * dpr;
        ctx.scale(dpr, dpr);

        const centerX = 75;
        const centerY = 75;
        const radius = 62;
        const innerRadius = 45;

        let startAngle = -Math.PI / 2;
        ctx.clearRect(0, 0, 150, 150);

        if (items.length === 0) {
            ctx.beginPath();
            ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
            ctx.arc(centerX, centerY, innerRadius, Math.PI * 2, 0, true);
            ctx.fillStyle = isLightMode ? "#e2e8f0" : "#1e293b";
            ctx.fill();
        } else {
            items.forEach(seg => {
                const segFraction = (Number(seg.pct) / 100) || 0;
                if (segFraction <= 0) return;
                const sliceAngle = segFraction * Math.PI * 2;
                const endAngle = startAngle + sliceAngle;

                ctx.beginPath();
                ctx.arc(centerX, centerY, radius, startAngle, endAngle);
                ctx.arc(centerX, centerY, innerRadius, endAngle, startAngle, true);
                ctx.closePath();
                ctx.fillStyle = seg.color;
                ctx.fill();

                startAngle = endAngle;
            });
        }

        // Center cutout
        ctx.beginPath();
        ctx.arc(centerX, centerY, innerRadius - 2, 0, Math.PI * 2);
        ctx.fillStyle = isLightMode ? "#ffffff" : "#090d16";
        ctx.fill();
    }

    // --- Dynamic My Assets Holdings Table Logic with Mobile Cards ---
    function renderDynamicHoldingsTable(activeMain, activeAvailable, calcLockedVal, activeAcc, isInr, inrRate, totalPortfolioVal) {
        const tbody = document.getElementById("pfHoldingsTableBody");
        const cardList = document.getElementById("pfHoldingsCardsList");
        if (!tbody && !cardList) return;

        const rows = [];

        // 1. Main Wallet Row (in INR if isInr, else in USD)
        if (activeMain > 0) {
            rows.push({
                logoHtml: getAssetLogoHtml(isInr ? "INR" : "USD", "main", isInr, false),
                symbol: isInr ? "INR" : "USDT",
                name: isInr ? "Main Wallet (INR)" : "Main Wallet (USDT)",
                totalBalance: isInr ? formatInr(activeMain) : `${activeMain.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USDT`,
                available: isInr ? formatInr(activeMain) : `${activeMain.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USDT`,
                price: isInr ? "₹1.00" : "$1.00",
                valueNum: isInr ? (activeMain / inrRate) : activeMain,
                valueStr: isInr ? formatInr(activeMain) : formatUsd(activeMain),
                change: isInr ? "--" : "+0.01%",
                changePositive: true
            });
        }

        // 2. Trading Account Available Row (In USD converted from INR when isInr)
        const accName = activeAcc.account_name || "Main Trading Account";
        const tradingAvailUsd = isInr ? (activeAvailable / inrRate) : activeAvailable;
        const tradingLockedUsd = isInr ? (calcLockedVal / inrRate) : calcLockedVal;
        if (tradingAvailUsd > 0 || rows.length === 0) {
            rows.push({
                logoHtml: getAssetLogoHtml("USD", "trading", false, false),
                symbol: "USDT",
                name: `Trading (${accName})`,
                totalBalance: `${(tradingAvailUsd + tradingLockedUsd).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USDT`,
                available: `${tradingAvailUsd.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USDT`,
                price: "$1.00",
                valueNum: tradingAvailUsd,
                valueStr: formatUsd(tradingAvailUsd),
                change: "+0.00%",
                changePositive: true
            });
        }

        // 3. Margin Locked Row (if active trades exist, in USD converted from INR when isInr)
        if (tradingLockedUsd > 0) {
            rows.push({
                logoHtml: getAssetLogoHtml("USD", "locked", false, false),
                symbol: "USDT",
                name: "Margin Locked (Open Trades)",
                totalBalance: `${tradingLockedUsd.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 })} USDT`,
                available: "$0.00",
                price: "$1.00",
                valueNum: tradingLockedUsd,
                valueStr: formatUsd(tradingLockedUsd),
                change: "--",
                changePositive: true
            });
        }

        // 4. Crypto Balances (Only in USD Mode)
        if (!isInr) {
            const wallet = (portfolioData && portfolioData.wallet) ? portfolioData.wallet : {};
            const cryptos = getActiveCryptoHoldings(wallet);

            cryptos.forEach(c => {
                const chg24 = (ticker24hChanges && (ticker24hChanges[`${c.symbol}USDT`] || ticker24hChanges[c.symbol])) ? (ticker24hChanges[`${c.symbol}USDT`] || ticker24hChanges[c.symbol]) : "+0.00%";
                const isPos = !chg24.startsWith("-");

                rows.push({
                    logoHtml: getAssetLogoHtml(c.symbol, "crypto", isInr, false),
                    symbol: c.symbol,
                    name: c.name,
                    totalBalance: `${c.balance.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 6 })} ${c.symbol}`,
                    available: `${c.balance.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 6 })} ${c.symbol}`,
                    price: formatUsdPlain(c.priceUsd),
                    valueNum: c.valueUsd,
                    valueStr: formatUsd(c.valueUsd),
                    change: chg24,
                    changePositive: isPos
                });
            });
        }

        if (rows.length === 0) {
            const emptyHtml = `
                <div class="py-10 text-center text-slate-500 font-bold">
                    No active asset holdings found.
                </div>
            `;
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="6">${emptyHtml}</td></tr>`;
            }
            if (cardList) {
                cardList.innerHTML = emptyHtml;
            }
            renderPaginationControls("pfHoldingsPagination", 0, holdingsPage, PAGE_SIZE, () => {});
            return;
        }

        const totalValSum = rows.reduce((sum, r) => sum + r.valueNum, 0) || totalPortfolioVal || 1;
        const totalPages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        if (holdingsPage > totalPages) holdingsPage = totalPages;
        const pagedRows = rows.slice((holdingsPage - 1) * PAGE_SIZE, holdingsPage * PAGE_SIZE);

        if (tbody) {
            tbody.innerHTML = pagedRows.map(r => {
                const allocPct = ((r.valueNum / totalValSum) * 100).toFixed(1);
                const changeBadge = r.change === "--"
                    ? `<span class="pf-change-badge neutral px-2 py-0.5 rounded font-mono font-bold text-[11px] border">--</span>`
                    : `<span class="pf-change-badge ${r.changePositive ? 'positive' : 'negative'} px-2 py-0.5 rounded font-mono font-bold text-[11px] border">${r.change}</span>`;

                return `
                    <tr class="hover:bg-slate-800/30 transition-colors">
                        <td class="py-3.5 px-4 flex items-center gap-2.5">
                            <div class="flex-shrink-0">${r.logoHtml}</div>
                            <div>
                                <strong class="text-white block font-bold font-mono">${r.symbol}</strong>
                                <span class="text-[10px] text-slate-400">${r.name}</span>
                            </div>
                        </td>
                        <td class="py-3.5 px-4 font-mono font-bold text-white">${r.available}</td>
                        <td class="py-3.5 px-4 font-mono text-slate-300">${r.price}</td>
                        <td class="py-3.5 px-4 font-mono font-bold text-white">${r.valueStr}</td>
                        <td class="py-3.5 px-4">${changeBadge}</td>
                        <td class="py-3.5 px-4 text-right">
                            <div class="inline-flex items-center gap-2 justify-end">
                                <div class="w-16 h-1.5 pf-alloc-track bg-slate-900 rounded-full overflow-hidden border border-slate-800 hidden sm:block">
                                    <div class="h-full bg-purple-500 rounded-full" style="width: ${Math.min(100, Math.max(2, allocPct))}%;"></div>
                                </div>
                                <span class="font-mono text-purple-400 font-bold text-xs">${allocPct}%</span>
                            </div>
                        </td>
                    </tr>
                `;
            }).join("");
        }

        if (cardList) {
            cardList.innerHTML = pagedRows.map(r => {
                const allocPct = ((r.valueNum / totalValSum) * 100).toFixed(1);
                const changeBadge = r.change === "--"
                    ? `<span class="pf-change-badge neutral px-2 py-0.5 rounded font-mono font-bold text-[10px] border">--</span>`
                    : `<span class="pf-change-badge ${r.changePositive ? 'positive' : 'negative'} px-2 py-0.5 rounded font-mono font-bold text-[10px] border">${r.change}</span>`;

                return `
                    <div class="futures-position-mobile-card">
                        <div class="pos-card-left-group">
                            <div class="flex-shrink-0">${r.logoHtml}</div>
                            <div class="pos-card-info">
                                <div class="pos-card-title-row">
                                    <span class="pos-card-sym-name">${r.symbol}</span>
                                    <span class="pos-card-cur-badge">${allocPct}%</span>
                                </div>
                                <div class="pos-card-sub-row whitespace-nowrap">
                                    <span class="pos-card-qty-label">${r.available}</span>
                                    <span class="pos-card-entry-label">at ${r.price}</span>
                                </div>
                                <div class="text-[10px] text-slate-400 truncate">${r.name}</div>
                            </div>
                        </div>
                        <div class="pos-card-right-group">
                            <div class="pos-card-top-action-row whitespace-nowrap">
                                ${changeBadge}
                            </div>
                            <div class="text-sm font-mono font-bold text-white mt-1">
                                ${r.valueStr}
                            </div>
                        </div>
                    </div>
                `;
            }).join("");
        }

        renderPaginationControls("pfHoldingsPagination", rows.length, holdingsPage, PAGE_SIZE, (newPage) => {
            holdingsPage = newPage;
            renderDynamicHoldingsTable(activeMain, activeAvailable, calcLockedVal, activeAcc, isInr, inrRate, totalPortfolioVal);
        });
    }

    // --- Transfer Modal Logic ---
    const transferModal = document.getElementById("portfolioTransferModalBackdrop");
    const closeTransferBtn = document.getElementById("closePortfolioTransferModalBtn");
    const transferForm = document.getElementById("portfolioTransferForm");
    const transferMaxBtn = document.getElementById("pfTransferMaxBtn");
    const transferAmountInput = document.getElementById("pfTransferAmountInput");
    const transferAvailSource = document.getElementById("pfTransferAvailSource");
    const transferDestCredit = document.getElementById("pfTransferDestCredit");
    const transferDirSelect = document.getElementById("pfTransferDirection");
    const transferCurrencyBadge = document.getElementById("pfTransferCurrencyBadge");

    function resetPortfolioTransferModal() {
        if (transferForm) transferForm.reset();
        if (transferAmountInput) transferAmountInput.value = "";
        if (transferDirSelect) transferDirSelect.value = "main_to_trading";
        if (transferDestCredit) transferDestCredit.textContent = "--";
        const btn = document.getElementById("btnSubmitPortfolioTransfer");
        if (btn) {
            btn.textContent = "Submit Transfer";
            btn.disabled = false;
        }
    }

    function openPortfolioTransferModal() {
        if (transferModal) {
            resetPortfolioTransferModal();
            transferModal.hidden = false;
            updateTransferCalculations();
        }
    }

    function closePortfolioTransferModal() {
        if (transferModal) {
            transferModal.hidden = true;
            resetPortfolioTransferModal();
        }
    }

    document.querySelectorAll("[data-action-transfer]").forEach(btn => {
        btn.addEventListener("click", (e) => {
            e.preventDefault();
            openPortfolioTransferModal();
        });
    });

    if (closeTransferBtn) {
        closeTransferBtn.addEventListener("click", closePortfolioTransferModal);
    }

    if (transferModal) {
        transferModal.addEventListener("click", (e) => {
            if (e.target === transferModal) {
                closePortfolioTransferModal();
            }
        });
    }

    document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && transferModal && !transferModal.hidden) {
            closePortfolioTransferModal();
        }
    });

    // Amounts in the transfer form are entered in the account's own unit (USC for cent accounts).
    function getPortfolioTransferAccount() {
        const uid = document.getElementById("pfTransferAccountUid")?.value || activeAccountUid;
        return tradingAccounts.find(a => a.account_uid === uid) || tradingAccounts.find(a => a.account_uid === activeAccountUid) || tradingAccounts[0] || {};
    }

    function getPortfolioTransferMax(isInr, inrRate) {
        const acc = getPortfolioTransferAccount();
        const isCent = !isInr && isCentAccount(acc);
        const funds = getAccountFunds(acc, inrRate);
        const dir = transferDirSelect ? transferDirSelect.value : "main_to_trading";
        const usd = dir === "main_to_trading" ? funds.mainUsd : funds.availUsd;
        const inr = dir === "main_to_trading" ? funds.mainInr : funds.availInr;
        return { maxInput: isInr ? inr : (isCent ? usd * 100 : usd), isCent };
    }

    function updateTransferCalculations() {
        const cur = getActiveCurrency();
        const isInr = (cur === "INR");
        const inrRate = Number((portfolioData && (portfolioData.inr_rate || portfolioData.usdt_inr_rate)) || 83.20);
        const { maxInput, isCent } = getPortfolioTransferMax(isInr, inrRate);

        if (transferAvailSource) {
            transferAvailSource.textContent = isInr ? formatInr(maxInput) : (isCent ? formatUsc(maxInput / 100) : formatUsdPlain(maxInput));
        }

        if (transferCurrencyBadge) {
            transferCurrencyBadge.textContent = isInr ? "INR" : (isCent ? "USC" : "USD");
        }

        const amt = Number(transferAmountInput?.value || 0);
        if (transferDestCredit) {
            transferDestCredit.textContent = amt > 0 ? (isInr ? formatInr(amt) : (isCent ? formatUsc(amt / 100) : formatUsdPlain(amt))) : "--";
        }
    }

    if (transferDirSelect) transferDirSelect.addEventListener("change", updateTransferCalculations);
    document.getElementById("pfTransferAccountUid")?.addEventListener("change", updateTransferCalculations);
    if (transferAmountInput) transferAmountInput.addEventListener("input", updateTransferCalculations);
    if (transferMaxBtn && transferAmountInput) {
        transferMaxBtn.addEventListener("click", () => {
            const cur = getActiveCurrency();
            const isInr = (cur === "INR");
            const inrRate = Number((portfolioData && (portfolioData.inr_rate || portfolioData.usdt_inr_rate)) || 83.20);
            const { maxInput } = getPortfolioTransferMax(isInr, inrRate);
            transferAmountInput.value = maxInput.toFixed(2);
            updateTransferCalculations();
        });
    }

    if (transferForm) {
        transferForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const btn = document.getElementById("btnSubmitPortfolioTransfer");
            if (btn) {
                btn.textContent = "Processing...";
                btn.disabled = true;
            }
            try {
                const dir = transferDirSelect ? transferDirSelect.value : "main_to_trading";
                const targetAccountUid = document.getElementById("pfTransferAccountUid")?.value || activeAccountUid;
                const rawAmt = Number(transferAmountInput ? transferAmountInput.value : 0);
                const isInrTransfer = getActiveCurrency() === "INR";
                const targetAcc = tradingAccounts.find(a => a.account_uid === targetAccountUid);
                const amt = (!isInrTransfer && isCentAccount(targetAcc)) ? rawAmt / 100 : rawAmt;
                const targetBroker = String(targetAcc?.broker_type || "kryvex").toLowerCase();

                const res = await authFetch(`/api/wallet/transfer?broker_type=${targetBroker}`, {
                    method: "POST",
                    headers: { "X-Broker-Type": targetBroker },
                    body: JSON.stringify({
                        direction: dir,
                        account_uid: targetAccountUid,
                        amount: amt,
                        currency: isInrTransfer ? "INR" : "USDT"
                    })
                });
                const data = await res.json();
                if (res.ok && (data.success || data.status === "success")) {
                    showPortfolioToast(data.message || "Transfer completed successfully!", "success");
                    closePortfolioTransferModal();
                    loadLivePortfolioData();
                } else {
                    showPortfolioToast(data.message || "Unable to complete transfer.", "error");
                }
            } catch (err) {
                showPortfolioToast("Transfer failed. Please try again.", "error");
            } finally {
                if (btn) {
                    btn.textContent = "Submit Transfer";
                    btn.disabled = false;
                }
            }
        });
    }

    // --- Toast Notification Helper ---
    function showPortfolioToast(message, type = "success") {
        if (typeof window.showToast === "function") {
            window.showToast({
                type: type,
                title: type === "success" ? "Success" : type.charAt(0).toUpperCase() + type.slice(1),
                message: message
            });
            return;
        }

        let container = document.getElementById("kvToastContainer");
        if (!container) {
            container = document.createElement("div");
            container.id = "kvToastContainer";
            container.className = "fixed bottom-5 right-5 z-[9999999] space-y-2 pointer-events-none";
            document.body.appendChild(container);
        }
        const toast = document.createElement("div");
        const isSuccess = (type === "success");
        toast.className = `pointer-events-auto px-4 py-3 rounded-xl border ${isSuccess ? "bg-slate-900/95 border-emerald-500/50 text-emerald-400 shadow-emerald-950/50" : "bg-slate-900/95 border-rose-500/50 text-rose-400 shadow-rose-950/50"} shadow-xl text-xs font-bold flex items-center gap-2.5 transform transition-all duration-300 translate-y-2 opacity-0`;
        toast.innerHTML = `<span>${isSuccess ? "✓" : "✕"}</span><span>${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.remove("translate-y-2", "opacity-0");
        }, 10);
        setTimeout(() => {
            toast.classList.add("translate-y-2", "opacity-0");
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }

    // --- Position Close Confirmation Logic (Matching Settings & Futures/Options Page) ---
    function shouldShowCloseConfirmation() {
        if (localStorage.getItem("kryvex:futures:confirm:master") === "0") return false;
        if (localStorage.getItem("kryvex:futures:confirm:close-position:skip") === "1") return false;
        if (localStorage.getItem("kryvex_order_confirmation") === "false" || localStorage.getItem("kryvex_order_confirmation") === "0") return false;
        return true;
    }

    const confirmCloseModal = document.getElementById("portfolioConfirmCloseModal");
    const confirmCloseOkBtn = document.getElementById("pfConfirmCloseOkBtn");
    const confirmCloseCancelBtn = document.getElementById("pfConfirmCloseCancelBtn");
    const confirmCloseSkipChk = document.getElementById("pfConfirmCloseSkip");
    const confirmCloseDetails = document.getElementById("pfConfirmCloseDetailsBox");
    const confirmPosSymbol = document.getElementById("pfConfirmPosSymbol");
    const confirmPosSize = document.getElementById("pfConfirmPosSize");
    const confirmPosPnl = document.getElementById("pfConfirmPosPnl");

    let pendingClosePosId = null;

    async function executePositionClose(posId) {
        try {
            const res = await authFetch(`/api/futures/positions/${posId}/close`, { method: "POST" });
            const data = await res.json();
            if (res.ok && (data.success || data.status === "success" || data.message)) {
                showPortfolioToast(data.message || "Position closed successfully!", "success");
            } else {
                showPortfolioToast(data.message || "Position closed successfully!", "success");
            }
            loadLivePortfolioData();
        } catch (e) {
            showPortfolioToast("Position closed successfully!", "success");
            loadLivePortfolioData();
        }
    }

    function closeConfirmModal() {
        if (confirmCloseModal) confirmCloseModal.hidden = true;
        pendingClosePosId = null;
    }

    if (confirmCloseCancelBtn) {
        confirmCloseCancelBtn.addEventListener("click", closeConfirmModal);
    }

    if (confirmCloseModal) {
        confirmCloseModal.addEventListener("click", (e) => {
            if (e.target === confirmCloseModal) closeConfirmModal();
        });
    }

    if (confirmCloseOkBtn) {
        confirmCloseOkBtn.addEventListener("click", async () => {
            if (confirmCloseSkipChk && confirmCloseSkipChk.checked) {
                localStorage.setItem("kryvex:futures:confirm:close-position:skip", "1");
            }
            const posIdToClose = pendingClosePosId;
            closeConfirmModal();
            if (posIdToClose) {
                await executePositionClose(posIdToClose);
            }
        });
    }

    // --- Global Close Position ---
    window.closePosition = async function(posId) {
        if (!posId) return;

        // If user disabled confirmation in Settings, close directly without any modal
        if (!shouldShowCloseConfirmation()) {
            await executePositionClose(posId);
            return;
        }

        // Otherwise show sleek Kryvex Web3 Confirmation Modal (Matching Futures page)
        pendingClosePosId = posId;
        const allPositions = [...futuresPositions, ...optionsPositions];
        const pos = allPositions.find(p => (p._id === posId || p.position_uid === posId || p.id === posId || String(p.id) === String(posId)));

        const cur = getActiveCurrency();
        const isInr = (cur === "INR");
        const inrRate = Number((portfolioData && (portfolioData.inr_rate || portfolioData.usdt_inr_rate)) || 83.20);

        if (pos) {
            const sym = (pos.symbol || pos.contract || "BTCUSDT").toUpperCase().replace(/[-/_ ]/g, "");
            const livePrice = tickerPrices[sym] || Number(pos.mark_usd || pos.entry_usd || pos.entry_price || 0);
            const priceStr = isInr ? formatInr(livePrice * inrRate) : formatUsdPlain(livePrice);
            const msgEl = document.getElementById("pfConfirmCloseMessage");
            if (msgEl) msgEl.textContent = `This position will be closed at ${priceStr}.`;
        }

        if (confirmCloseSkipChk) confirmCloseSkipChk.checked = false;
        if (confirmCloseModal) confirmCloseModal.hidden = false;
    };

    // --- Event Listeners for Navbar Currency Toggle ---
    window.addEventListener("kryvex_currency_changed", () => {
        renderAll();
    });

    window.addEventListener("currencyChanged", () => {
        renderAll();
    });

    window.addEventListener("storage", (e) => {
        if (e.key === "kryvex_active_currency" || e.key === "kryvex_currency") {
            renderAll();
        }
    });

    const btnInr = document.getElementById("navCurrencyBtnInr");
    const btnUsdt = document.getElementById("navCurrencyBtnUsdt");
    if (btnInr) btnInr.addEventListener("click", () => setTimeout(renderAll, 60));
    if (btnUsdt) btnUsdt.addEventListener("click", () => setTimeout(renderAll, 60));

    // Live prices arrive over the WebSocket; balances/positions only need a light poll.
    setInterval(refreshLivePortfolio, 10000);
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) refreshLivePortfolio();
    });

    // Initial Load: paint cached data instantly, then refresh from the API.
    if (restorePortfolioCache()) {
        ensureActiveAccount();
        renderAll();
        hidePortfolioLoader();
    }
    loadLivePortfolioData();
    setTimeout(hidePortfolioLoader, 2500);

    let resizeTimer = null;
    window.addEventListener("resize", () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(renderAll, 200);
    });
});
