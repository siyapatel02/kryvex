import { getJson } from '../utils/api';
import { escapeHtml, formatCompact, formatPercent } from '../utils/format';

const state = {
    tickers: [],
    filteredTickers: [],
};

let marketsSocket = null;
let lenisInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    initLenisSmoothScroll();
    initScrollProgressAndTopBtn();
    setupScrollReveal();

    loadCachedMarkets();
    fetchMarkets();
    setInterval(fetchMarkets, 5000);
    connectMarketsTickerSocket();

    const searchInput = document.getElementById('marketSearchInput');

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            filterMarkets(searchInput.value);
        });
    }

    const tableBody = document.getElementById('marketsTableBody');
    if (tableBody) {
        tableBody.addEventListener('click', (event) => {
            const row = event.target.closest('.market-row');
            if (row) {
                if (event.target.closest('a')) {
                    return;
                }
                const symbol = row.getAttribute('data-symbol');
                if (symbol) {
                    window.location.href = `/trade/${symbol}`;
                }
            }
        });
    }

    const cardsList = document.getElementById('marketsCardsList');
    if (cardsList) {
        cardsList.addEventListener('click', (event) => {
            const card = event.target.closest('.market-mobile-card');
            if (card) {
                if (event.target.closest('a')) {
                    return;
                }
                const symbol = card.getAttribute('data-symbol');
                if (symbol) {
                    window.location.href = `/trade/${symbol}`;
                }
            }
        });
    }

    // Carousel swipe, auto-scroll and dots logic
    const grid = document.querySelector('.markets-summary-grid');
    const dots = document.querySelectorAll('.carousel-indicators .dot');
    let currentSlide = 0;
    let autoScrollInterval = null;
    let autoResumeTimeout = null;

    if (grid && dots.length) {
        grid.addEventListener('scroll', () => {
            const scrollLeft = grid.scrollLeft;
            const width = grid.offsetWidth;
            if (width > 0) {
                const index = Math.min(dots.length - 1, Math.max(0, Math.round(scrollLeft / width)));
                currentSlide = index;
                dots.forEach((dot, idx) => {
                    if (idx === index) {
                        dot.classList.add('active');
                    } else {
                        dot.classList.remove('active');
                    }
                });
            }
        }, { passive: true });

        dots.forEach((dot) => {
            dot.addEventListener('click', () => {
                const slideIdx = parseInt(dot.getAttribute('data-slide') || '0', 10);
                const width = grid.offsetWidth;
                grid.scrollTo({
                    left: slideIdx * width,
                    behavior: 'smooth'
                });
                handleUserInteraction();
            });
        });

        function startAutoScroll() {
            stopAutoScroll();
            autoScrollInterval = setInterval(() => {
                if (window.innerWidth > 900) return;
                const width = grid.offsetWidth;
                if (width <= 0) return;
                currentSlide = (currentSlide + 1) % dots.length;
                grid.scrollTo({
                    left: currentSlide * width,
                    behavior: 'smooth'
                });
            }, 3500);
        }

        function stopAutoScroll() {
            if (autoScrollInterval) {
                clearInterval(autoScrollInterval);
                autoScrollInterval = null;
            }
            if (autoResumeTimeout) {
                clearTimeout(autoResumeTimeout);
                autoResumeTimeout = null;
            }
        }

        function handleUserInteraction() {
            stopAutoScroll();
            autoResumeTimeout = setTimeout(() => {
                startAutoScroll();
            }, 5000);
        }

        grid.addEventListener('touchstart', handleUserInteraction, { passive: true });
        grid.addEventListener('mousedown', handleUserInteraction);
        grid.addEventListener('touchend', handleUserInteraction, { passive: true });

        startAutoScroll();
    }

    const summaryGrid = document.querySelector('.markets-summary-grid');
    if (summaryGrid) {
        summaryGrid.addEventListener('click', (event) => {
            const row = event.target.closest('.rank-row');
            if (row) {
                const symbol = row.getAttribute('data-symbol');
                if (symbol) {
                    window.location.href = `/trade/${symbol}`;
                }
            }
        });
    }
});

let isFirstLoad = true;

function loadCachedMarkets() {
    try {
        const cached = localStorage.getItem('kryvex_cached_markets');
        if (!cached) return false;

        const data = JSON.parse(cached);
        if (Array.isArray(data) && data.length > 0) {
            state.tickers = data;
            state.filteredTickers = data;

            updateSummaryCards(data);
            renderMarketsTable(data);

            const overlay = document.getElementById('marketsLoadingOverlay');
            const content = document.getElementById('marketsContent');
            if (overlay) overlay.style.display = 'none';
            if (content) content.style.opacity = '1';
            setTimeout(setupScrollReveal, 50);

            isFirstLoad = false;
            return true;
        }
    } catch (e) {
        // Silent error
    }
    return false;
}

async function fetchMarkets() {
    const tableBody = document.getElementById('marketsTableBody');
    const overlay = document.getElementById('marketsLoadingOverlay');
    const content = document.getElementById('marketsContent');

    try {
        const result = await getJson('/api/markets/futures/tickers');

        if (Array.isArray(result.data) && result.data.length > 0) {
            state.tickers = result.data;
            const searchInput = document.getElementById('marketSearchInput');
            if (searchInput && searchInput.value.trim()) {
                filterMarkets(searchInput.value);
            } else {
                state.filteredTickers = state.tickers;
                renderMarketsTable(state.filteredTickers);
            }

            try {
                localStorage.setItem('kryvex_cached_markets', JSON.stringify(result.data));
            } catch (e) {}

            updateSourceLabel(result.source || 'unknown');
            updateSummaryCards(state.tickers);
        }

        if (isFirstLoad) {
            isFirstLoad = false;
            if (overlay) overlay.style.display = 'none';
            if (content) content.style.opacity = '1';
            setTimeout(setupScrollReveal, 50);
        }
    } catch (error) {
        if (isFirstLoad && (!state.tickers || state.tickers.length === 0)) {
            isFirstLoad = false;
            if (overlay) overlay.style.display = 'none';
            if (content) content.style.opacity = '1';
            setTimeout(setupScrollReveal, 50);
            const cardsList = document.getElementById('marketsCardsList');
            if (tableBody) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center py-10 text-slate-500">
                            Unable to load markets. Please try again.
                        </td>
                    </tr>
                `;
            }
            if (cardsList) {
                cardsList.innerHTML = `
                    <div class="text-center py-8 text-slate-500">
                        Unable to load markets. Please try again.
                    </div>
                `;
            }
        }
    }
}

function filterMarkets(searchValue) {
    const keyword = searchValue.trim().toUpperCase();

    state.filteredTickers = state.tickers.filter((ticker) => {
        return ticker.symbol.includes(keyword);
    });

    renderMarketsTable(state.filteredTickers);
}

function getSymbolColor(char) {
    const code = char.charCodeAt(0);
    const hues = [25, 145, 270, 320, 200, 45, 160, 290];
    const hue = hues[code % hues.length];
    return `hsl(${hue}, 70%, 40%)`;
}

function getBaseSymbol(symbol) {
    if (!symbol) return 'crypto';
    let sym = String(symbol).toUpperCase();
    if (sym.endsWith('USDT')) sym = sym.slice(0, -4);
    if (sym.endsWith('USD')) sym = sym.slice(0, -3);
    if (sym.endsWith('PERP')) sym = sym.slice(0, -4);
    if (sym.startsWith('1000')) sym = sym.slice(4);
    return sym;
}

function getCoinIconHtml(symbol, sizeClass = 'w-8 h-8') {
    const baseSym = getBaseSymbol(symbol);
    const coinLogos = {
        BTC: "/images/coins/btc.png",
        ETH: "/images/coins/eth.png",
        USDT: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/usdt.png",
        BNB: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/bnb.png",
        SOL: "/images/coins/sol.png",
        XRP: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/xrp.png",
        USDC: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/usdc.png",
        ADA: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/ada.png",
        DOGE: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/doge.png",
        TRX: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/trx.png",
        AVAX: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/avax.png",
        DOT: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/dot.png",
        MATIC: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/matic.png",
        LINK: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/link.png",
        LTC: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/ltc.png",
        NEAR: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/near.png",
        SUI: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/sui.png",
        SHIB: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/shib.png",
        PEPE: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/pepe.png",
        ZEC: "https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/zec.png",
        XAU: "/images/coins/gold.svg",
        GOLD: "/images/coins/gold.svg",
        PAXG: "/images/coins/paxg.png",
        PAXGUSDT: "/images/coins/paxg.png",
        XAUUSDT: "/images/coins/gold.svg"
    };

    const isGold = (baseSym === 'XAU' || baseSym === 'GOLD' || baseSym === 'PAXG' || baseSym === 'XAUT' || String(symbol || '').toUpperCase().includes('XAU'));
    const logoUrl = isGold ? "/images/coins/gold.svg" : (coinLogos[baseSym] || coinLogos[symbol.toUpperCase()] || `https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/${baseSym.toLowerCase()}.png`);
    const fallbackUrl = isGold
        ? "/images/coins/paxg.png"
        : `https://raw.githubusercontent.com/spothq/cryptocurrency-icons/master/128/color/${baseSym.toLowerCase()}.png`;

    return `
        <div class="coin-icon-container relative flex-shrink-0 ${sizeClass} rounded-full overflow-hidden flex items-center justify-center bg-slate-900 border border-white/10 shadow-sm">
            <img src="${logoUrl}" 
                 alt="${escapeHtml(baseSym)}" 
                 class="w-full h-full object-cover block" 
                 onerror="if(this.dataset.triedFallback !== 'true'){ this.dataset.triedFallback = 'true'; this.src = '${fallbackUrl}'; }" />
        </div>
    `;
}

function renderMarketsTable(tickers) {
    const tableBody = document.getElementById('marketsTableBody');
    const cardsList = document.getElementById('marketsCardsList');

    if (!tableBody) {
        return;
    }

    if (!tickers.length) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-10 text-slate-500">No contracts found.</td>
            </tr>
        `;
        if (cardsList) {
            cardsList.innerHTML = `<div class="text-center py-8 text-slate-500">No contracts found.</div>`;
        }
        return;
    }

    // Sort tickers by 24h Volume (descending) from highest to lowest
    const sortedTickers = [...tickers].sort((a, b) => {
        const volA = Number(a.quote_volume || a.volume || 0);
        const volB = Number(b.quote_volume || b.volume || 0);
        return volB - volA;
    });

    // Check if table rows already exist in DOM for smooth in-place update (prevents hover flickering/blinking)
    const existingRows = tableBody.querySelectorAll('.market-row');
    if (existingRows.length > 0 && existingRows.length === sortedTickers.length) {
        const rowMap = new Map();
        existingRows.forEach((row) => {
            const sym = row.getAttribute('data-symbol');
            if (sym) rowMap.set(sym.toLowerCase(), row);
        });

        const allMatch = sortedTickers.every((t) => rowMap.has(t.symbol.toLowerCase()));
        if (allMatch) {
            sortedTickers.forEach((ticker) => {
                const row = rowMap.get(ticker.symbol.toLowerCase());
                if (!row) return;

                const isPositive = Number(ticker.price_change_percent) >= 0;
                const changeTextColor = isPositive ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold';

                const priceEl = row.querySelector('.market-col-price');
                if (priceEl) priceEl.textContent = formatMarketPrice(ticker.last_price);

                const changeEl = row.querySelector('.market-col-change');
                if (changeEl) {
                    changeEl.textContent = formatPercent(ticker.price_change_percent);
                    changeEl.className = `market-col-change py-3.5 px-4 text-right w-[12%] font-mono text-xs ${changeTextColor}`;
                }

                const highEl = row.querySelector('.market-col-high');
                if (highEl) highEl.textContent = formatMarketPrice(ticker.high_price);

                const lowEl = row.querySelector('.market-col-low');
                if (lowEl) lowEl.textContent = formatMarketPrice(ticker.low_price);

                const volEl = row.querySelector('.market-col-vol');
                if (volEl) volEl.textContent = `${formatCompact(ticker.quote_volume || ticker.volume)} USDT`;
            });

            if (cardsList) {
                const cardMap = new Map();
                cardsList.querySelectorAll('.market-mobile-row').forEach((card) => {
                    const sym = card.getAttribute('data-symbol');
                    if (sym) cardMap.set(sym.toLowerCase(), card);
                });

                sortedTickers.forEach((ticker) => {
                    const card = cardMap.get(ticker.symbol.toLowerCase());
                    if (!card) return;

                    const isPositive = Number(ticker.price_change_percent) >= 0;
                    const changeColor = isPositive ? 'text-emerald-400' : 'text-rose-500';
                    const changePrefix = isPositive ? '+' : '';

                    const priceEl = card.querySelector('.market-col-price');
                    if (priceEl) {
                        priceEl.innerHTML = `${formatMarketPrice(ticker.last_price)} <span class="contract-subtitle font-normal text-[10px]">USDT</span>`;
                    }

                    const changeEl = card.querySelector('.market-col-change');
                    if (changeEl) {
                        changeEl.className = `market-col-change font-mono text-[11px] sm:text-xs font-bold ${changeColor} mt-0.5 leading-tight`;
                        changeEl.textContent = `${changePrefix}${formatPercent(ticker.price_change_percent)}`;
                    }
                });
            }

            return;
        }
    }

    // 1. Full Render desktop table rows (WITH clean interactive hover background & 1:1 column width alignment)
    tableBody.innerHTML = sortedTickers.map((ticker) => {
        const isPositive = Number(ticker.price_change_percent) >= 0;
        const changeTextColor = isPositive ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold';
        const iconHtml = getCoinIconHtml(ticker.symbol, 'w-6 h-6');

        return `
            <tr class="market-row group border-b border-slate-800/40 cursor-pointer" data-symbol="${escapeHtml(ticker.symbol.toLowerCase())}">
                <td class="py-3.5 px-4 text-left w-[23%]">
                    <div class="flex items-center gap-3">
                        ${iconHtml}
                        <div>
                            <span class="market-symbol-name font-extrabold text-sm block tracking-wide transition-colors duration-200 group-hover:text-purple-400">${escapeHtml(ticker.symbol.toUpperCase())}</span>
                            <span class="contract-subtitle text-[11px] font-medium block">${escapeHtml(ticker.contract_type || 'USDT Perpetual')}</span>
                        </div>
                    </div>
                </td>
                <td class="py-3.5 px-3 text-center w-[10%]">
                    <span class="leverage-badge bg-[#1e2330] border border-slate-700/60 text-slate-300 text-[11px] font-bold px-2 py-0.5 rounded-md inline-block transition-all duration-200 group-hover:border-purple-500/60 group-hover:bg-purple-950/40 group-hover:text-purple-200">
                        ${escapeHtml(ticker.leverage || '100x')}
                    </span>
                </td>
                <td class="market-col-price py-3.5 px-4 text-right w-[14%] font-mono text-sm font-bold transition-colors duration-200">${formatMarketPrice(ticker.last_price)}</td>
                <td class="market-col-change py-3.5 px-4 text-right w-[12%] font-mono text-xs ${changeTextColor}">
                    ${formatPercent(ticker.price_change_percent)}
                </td>
                <td class="market-col-high py-3.5 px-4 text-right w-[12%] font-mono text-xs font-bold hide-tablet transition-colors duration-200">${formatMarketPrice(ticker.high_price)}</td>
                <td class="market-col-low py-3.5 px-4 text-right w-[12%] font-mono text-xs font-bold hide-tablet transition-colors duration-200">${formatMarketPrice(ticker.low_price)}</td>
                <td class="market-col-vol py-3.5 px-4 text-right w-[12%] font-mono text-xs font-bold hide-tablet transition-colors duration-200">${formatCompact(ticker.quote_volume || ticker.volume)} USDT</td>
                <td class="py-3.5 px-4 text-right w-[10%] hide-mobile">
                    <a class="trade-btn inline-flex items-center justify-center bg-[#7c3aed] text-white font-bold text-xs px-4 py-1.5 rounded-lg shadow-md shadow-purple-950/40 transition-all duration-200 hover:bg-[#8b5cf6] hover:shadow-purple-500/40 active:scale-95 group-hover:bg-[#8b5cf6]" href="/trade/${ticker.symbol.toLowerCase()}">
                        Trade
                    </a>
                </td>
            </tr>
        `;
    }).join('');

    // 2. Render mobile responsive list rows
    if (cardsList) {
        cardsList.innerHTML = sortedTickers.map((ticker) => {
            const isPositive = Number(ticker.price_change_percent) >= 0;
            const changeColor = isPositive ? 'text-emerald-400' : 'text-rose-500';
            const changePrefix = isPositive ? '+' : '';
            const baseSym = getBaseSymbol(ticker.symbol);
            const iconHtml = getCoinIconHtml(ticker.symbol, 'w-8 h-8');
            const leverageText = ticker.leverage ? `${ticker.leverage}` : '100x';

            return `
                <div class="market-mobile-row flex items-center justify-between py-3 px-3 hover:bg-slate-800/40 transition-colors cursor-pointer group" data-symbol="${escapeHtml(ticker.symbol.toLowerCase())}">
                    <!-- Left: Logo + Base-Quote + Leverage -->
                    <div class="flex items-center gap-2.5 min-w-0 pr-2">
                        ${iconHtml}
                        <div class="min-w-0">
                            <div class="flex items-baseline gap-0.5">
                                <strong class="market-symbol-name font-extrabold text-[14px] leading-tight block group-hover:text-purple-400 transition-colors">${escapeHtml(baseSym)}</strong>
                                <span class="contract-subtitle font-bold text-xs leading-tight">-USDT</span>
                            </div>
                            <span class="text-[10px] text-slate-500 font-semibold block mt-0.5">${escapeHtml(leverageText)}</span>
                        </div>
                    </div>

                    <!-- Right: Price + 24H Change -->
                    <div class="flex items-center gap-2.5 flex-shrink-0 text-right">
                        <div>
                            <div class="market-col-price font-mono text-[13px] sm:text-sm font-bold tracking-tight leading-tight">
                                ${formatMarketPrice(ticker.last_price)} <span class="contract-subtitle font-normal text-[10px]">USDT</span>
                            </div>
                            <div class="market-col-change font-mono text-[11px] sm:text-xs font-bold ${changeColor} mt-0.5 leading-tight">
                                ${changePrefix}${formatPercent(ticker.price_change_percent)}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
}

function updateSourceLabel(source) {
    const label = document.getElementById('marketSourceLabel');

    if (!label) {
        return;
    }

    if (source === 'binance') {
        label.textContent = 'Live Futures API';
        return;
    }

    if (source === 'bybit') {
        label.textContent = 'Backup Futures API';
        return;
    }

    if (source === 'coingecko') {
        label.textContent = 'Spot Backup API';
        return;
    }

    label.textContent = 'Fallback';
}

let lastSummaryState = {
    gainers: '',
    losers: '',
    trending: ''
};

function updateSummaryCards(tickers) {
    const totalContracts = document.getElementById('totalContracts');
    const totalVolume = document.getElementById('totalVolume');

    if (!tickers || !tickers.length) {
        return;
    }

    // Sort for top 5 gainers (descending 24h change)
    const sortedByChangeDesc = [...tickers].sort((a, b) => {
        return Number(b.price_change_percent) - Number(a.price_change_percent);
    });

    // Sort for top 5 losers (ascending 24h change)
    const sortedByChangeAsc = [...tickers].sort((a, b) => {
        return Number(a.price_change_percent) - Number(b.price_change_percent);
    });

    // Sort for top 5 trending (descending volume)
    const sortedByVolume = [...tickers].sort((a, b) => {
        const volA = Number(a.quote_volume || a.volume || 0);
        const volB = Number(b.quote_volume || b.volume || 0);
        return volB - volA;
    });

    const gainers = sortedByChangeDesc.slice(0, 5);
    const losers = sortedByChangeAsc.slice(0, 5);
    const trending = sortedByVolume.slice(0, 5);

    const gainersKey = gainers.map((t) => t.symbol).join(',');
    const losersKey = losers.map((t) => t.symbol).join(',');
    const trendingKey = trending.map((t) => t.symbol).join(',');

    if (
        lastSummaryState.gainers === gainersKey &&
        lastSummaryState.losers === losersKey &&
        lastSummaryState.trending === trendingKey
    ) {
        // Ranking order unchanged: update price/change text without replacing image DOM elements
        updateRankRowsTextOnly('topGainersRows', gainers);
        updateRankRowsTextOnly('topLosersRows', losers);
        updateRankRowsTextOnly('trendingRows', trending);
    } else {
        lastSummaryState = { gainers: gainersKey, losers: losersKey, trending: trendingKey };
        renderRankRows('topGainersRows', gainers);
        renderRankRows('topLosersRows', losers);
        renderRankRows('trendingRows', trending);
    }

    const volumeTotal = tickers.reduce((sum, ticker) => {
        return sum + Number(ticker.quote_volume || ticker.volume || 0);
    }, 0);

    if (totalContracts) {
        totalContracts.textContent = tickers.length;
    }

    if (totalVolume) {
        totalVolume.textContent = `${formatCompact(volumeTotal)} USDT`;
    }
}

function updateRankRowsTextOnly(containerId, list) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const rows = container.querySelectorAll('.rank-row');
    list.forEach((ticker, index) => {
        const row = rows[index];
        if (!row) return;

        const isPositive = Number(ticker.price_change_percent) >= 0;
        const changeTextColor = isPositive ? 'text-emerald-400' : 'text-rose-400';

        const priceEls = row.querySelectorAll('.rank-price-val');
        const changeEl = row.querySelector('.rank-change-val');

        priceEls.forEach(priceEl => {
            priceEl.textContent = formatMarketPrice(ticker.last_price);
        });

        if (changeEl) {
            changeEl.textContent = formatPercent(ticker.price_change_percent);
            changeEl.className = `w-16 md:w-20 text-right font-mono text-xs font-bold ${changeTextColor} flex-shrink-0 rank-change-val`;
        }
    });
}

function renderRankRows(containerId, list) {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!list || !list.length) {
        container.innerHTML = `<div class="text-center py-4 text-xs text-slate-500">No data available</div>`;
        return;
    }

    container.innerHTML = list.map((ticker, index) => {
        const isPositive = Number(ticker.price_change_percent) >= 0;
        const changeTextColor = isPositive ? 'text-emerald-400' : 'text-rose-400';
        const iconHtml = getCoinIconHtml(ticker.symbol, 'w-7 h-7');

        return `
            <div class="rank-row group flex items-center justify-between py-2 px-2.5 rounded-xl transition-all duration-200 cursor-pointer border border-transparent hover:border-slate-700/60 hover:bg-slate-800/70 hover:translate-x-1 hover:shadow-md" data-symbol="${escapeHtml(ticker.symbol.toLowerCase())}">
                <div class="flex items-center gap-2.5 min-w-0 flex-1 pr-2">
                    <span class="text-xs font-extrabold text-slate-400 w-4 text-center flex-shrink-0">${index + 1}</span>
                    ${iconHtml}
                    <div class="min-w-0 flex-1">
                        <span class="rank-symbol-name font-extrabold text-xs tracking-wide transition-colors group-hover:text-purple-400 block truncate leading-tight">${escapeHtml(ticker.symbol.toUpperCase())}</span>
                        <span class="rank-price-val rank-price-mobile text-[11px] font-mono font-medium block md:hidden leading-tight mt-0.5">${formatMarketPrice(ticker.last_price)}</span>
                    </div>
                </div>
                <div class="rank-price-val rank-price-desktop hidden md:block font-mono text-xs font-semibold text-slate-200 text-right pr-3 flex-shrink-0">
                    ${formatMarketPrice(ticker.last_price)}
                </div>
                <div class="w-16 md:w-20 text-right font-mono text-xs font-bold ${changeTextColor} flex-shrink-0 rank-change-val">
                    ${formatPercent(ticker.price_change_percent)}
                </div>
            </div>
        `;
    }).join('');
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

function connectMarketsTickerSocket() {
    try {
        marketsSocket?.close?.();
        marketsSocket = new WebSocket('wss://fstream.binance.com/market/ws/!ticker@arr');

        marketsSocket.onmessage = (event) => {
            const rows = JSON.parse(event.data || '[]');
            if (!Array.isArray(rows) || !state.tickers.length) return;

            const bySymbol = new Map(rows.map((item) => [item.s, item]));

            state.tickers = state.tickers.map((ticker) => {
                const live = bySymbol.get(ticker.symbol);
                if (!live) return ticker;

                return {
                    ...ticker,
                    last_price: Number(live.c || ticker.last_price || 0),
                    price_change_percent: Number(live.P || ticker.price_change_percent || 0),
                    high_price: Number(live.h || ticker.high_price || 0),
                    low_price: Number(live.l || ticker.low_price || 0),
                    quote_volume: Number(live.q || ticker.quote_volume || ticker.volume || 0),
                };
            });

            const searchInput = document.getElementById('marketSearchInput');
            filterMarkets(searchInput?.value || '');
            updateSummaryCards(state.tickers);
            updateSourceLabel('binance');
        };

        marketsSocket.onclose = () => setTimeout(connectMarketsTickerSocket, 3000);
    } catch (error) {
        // API polling still updates the market page.
    }
}

function initLenisSmoothScroll() {
    if (typeof window.Lenis !== 'undefined') {
        lenisInstance = new window.Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            orientation: 'vertical',
            gestureOrientation: 'vertical',
            smoothWheel: true,
            wheelMultiplier: 1.0,
            touchMultiplier: 1.5,
            infinite: false,
        });

        function raf(time) {
            lenisInstance.raf(time);
            requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);

        lenisInstance.on('scroll', () => {
            updateScrollEffects();
        });
    } else {
        document.documentElement.style.scrollBehavior = 'smooth';
        window.addEventListener('scroll', updateScrollEffects, { passive: true });
    }
}

function updateScrollEffects() {
    const progressBar = document.getElementById('marketsScrollProgressBar');
    const topBtn = document.getElementById('marketsScrollToTopBtn');

    const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;

    if (progressBar && docHeight > 0) {
        const progress = Math.min(100, Math.max(0, (scrollTop / docHeight) * 100));
        progressBar.style.width = `${progress}%`;
    }

    if (topBtn) {
        if (scrollTop > 350) {
            topBtn.classList.add('is-visible');
        } else {
            topBtn.classList.remove('is-visible');
        }
    }
}

function initScrollProgressAndTopBtn() {
    const topBtn = document.getElementById('marketsScrollToTopBtn');
    if (topBtn) {
        topBtn.addEventListener('click', () => {
            if (lenisInstance) {
                lenisInstance.scrollTo(0, { duration: 1.2 });
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }

    window.addEventListener('scroll', updateScrollEffects, { passive: true });
    updateScrollEffects();
}

function setupScrollReveal() {
    const revealItems = document.querySelectorAll('.reveal-item');
    if (!revealItems.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.05,
        rootMargin: '0px 0px -40px 0px'
    });

    revealItems.forEach((item) => {
        // If element is already in viewport on load, make it visible directly
        const rect = item.getBoundingClientRect();
        if (rect.top < window.innerHeight && rect.bottom >= 0) {
            item.classList.add('is-visible');
        } else {
            observer.observe(item);
        }
    });
}

