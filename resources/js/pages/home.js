import { getJson } from '../utils/api';
import { escapeHtml, formatPercent, formatPrice } from '../utils/format';

let homeMarketSocket = null;
let isHomeFirstLoad = true;
let selectedHeroSymbol = 'BTCUSDT';
const homeTickersMap = new Map();
let lenisInstance = null;

if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}
window.scrollTo(0, 0);

window.addEventListener('beforeunload', () => {
    window.scrollTo(0, 0);
});

document.addEventListener('DOMContentLoaded', () => {
    window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
    setTimeout(() => {
        window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
    }, 10);

    initLenisSmoothScroll();
    initScrollProgressAndTopBtn();
    initCursorGrid();
    initSplitTextAnimation();
    initFadeContentCard();
    initAnimatedContentCards();
    initFadeContentScrollCards();
    loadCachedHomeMarkets();

    // Always ensure loading overlay disappears within 500ms
    setTimeout(hideHomeLoaderOverlay, 500);

    setupFaq();
    setupHeroTabs();
    setupPhoneMockupTabs();
    initHeroChartHover();
    loadHomeMarkets();
    setInterval(loadHomeMarkets, 5000);
    connectHomeTickerSocket();
    setupScrollReveal();
    initScrollRevealText();
    initTypewriterText();
    initPortfolioInteractiveHover();
});

function setupPhoneMockupTabs() {
    const tabBtns = document.querySelectorAll('.phone-tab-btn');
    const tabContents = document.querySelectorAll('.phone-tab-content');
    const headerTitle = document.getElementById('phoneAppHeaderTitle');
    const headerBadge = document.getElementById('phoneAppHeaderBadge');

    if (!tabBtns.length) return;

    const headerTitles = {
        home: 'Kryvex Mobile',
        futures: 'Futures Trading',
        wallet: 'Wallet Assets',
        profile: 'Account Profile'
    };

    const headerBadges = {
        home: 'LIVE',
        futures: '100x',
        wallet: 'DEMO',
        profile: 'VIP 1'
    };

    tabBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const targetTab = btn.dataset.phoneTab;
            
            tabBtns.forEach(b => {
                b.classList.remove('active', 'text-purple-400', 'font-extrabold', 'bg-purple-950/40');
                b.classList.add('text-slate-500', 'font-bold');
            });
            btn.classList.add('active', 'text-purple-400', 'font-extrabold', 'bg-purple-950/40');
            btn.classList.remove('text-slate-500');

            tabContents.forEach(content => {
                if (content.id === `phoneTabContent_${targetTab}`) {
                    content.classList.remove('hidden');
                    content.classList.add('block');
                } else {
                    content.classList.remove('block');
                    content.classList.add('hidden');
                }
            });

            if (headerTitle && headerTitles[targetTab]) {
                headerTitle.textContent = headerTitles[targetTab];
            }
            if (headerBadge && headerBadges[targetTab]) {
                headerBadge.textContent = headerBadges[targetTab];
            }
        });
    });
}

function setupHeroTabs() {
    const tabBtns = document.querySelectorAll('.hero-tab-btn');
    if (!tabBtns.length) return;

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const sym = btn.dataset.symbol;
            if (!sym) return;

            selectedHeroSymbol = sym;
            tabBtns.forEach(b => {
                b.classList.remove('active', 'bg-purple-600', 'text-white');
                b.classList.add('hover:text-white');
            });
            btn.classList.add('active', 'bg-purple-600', 'text-white');
            btn.classList.remove('hover:text-white');

            const ticker = homeTickersMap.get(sym);
            if (ticker) {
                updateHeroCardPreview(ticker);
            } else {
                updateHeroSymbolLabel(sym);
            }
        });
    });
}

function updateHeroSymbolLabel(symbol) {
    const labelEl = document.getElementById('heroSymbolLabel');
    if (labelEl) labelEl.textContent = symbol;
}

function updateHeroCardPreview(ticker) {
    if (!ticker || ticker.symbol !== selectedHeroSymbol) return;

    const labelEl = document.getElementById('heroSymbolLabel');
    const priceEl = document.getElementById('heroPricePreview');
    const changeEl = document.getElementById('heroChangePreview');
    const strokeEl = document.getElementById('heroChartStroke');
    const fillEl = document.getElementById('heroChartFill');

    if (labelEl) labelEl.textContent = ticker.symbol;
    if (priceEl) priceEl.textContent = formatPrice(ticker.last_price);

    const percent = Number(ticker.price_change_percent || 0);
    const isPositive = percent >= 0;

    if (changeEl) {
        changeEl.textContent = formatPercent(percent);
        changeEl.className = `text-xs font-bold font-mono ${isPositive ? 'text-emerald-400' : 'text-rose-400'}`;
    }

    if (strokeEl && fillEl) {
        if (isPositive) {
            strokeEl.setAttribute('stroke', '#10b981');
            fillEl.setAttribute('fill', 'url(#chartGradGreen)');
            strokeEl.setAttribute('d', 'M0,50 Q40,25 80,40 T160,20 T240,15 T300,5');
            fillEl.setAttribute('d', 'M0,50 Q40,25 80,40 T160,20 T240,15 T300,5 L300,80 L0,80 Z');
        } else {
            strokeEl.setAttribute('stroke', '#f43f5e');
            fillEl.setAttribute('fill', 'url(#chartGradRed)');
            strokeEl.setAttribute('d', 'M0,15 Q40,20 80,35 T160,45 T240,60 T300,75');
            fillEl.setAttribute('d', 'M0,15 Q40,20 80,35 T160,45 T240,60 T300,75 L300,80 L0,80 Z');
        }
    }
}

function loadCachedHomeMarkets() {
    try {
        const cached = localStorage.getItem('kryvex_cached_home_tickers');
        if (!cached) return false;

        const tickers = JSON.parse(cached);
        if (Array.isArray(tickers) && tickers.length > 0) {
            tickers.forEach(t => homeTickersMap.set(t.symbol, t));
            renderPopularHomeTickers(tickers);
            hideHomeLoaderOverlay();
            isHomeFirstLoad = false;
            return true;
        }
    } catch (e) {
        // Silent error fallback
    }
    return false;
}

function hideHomeLoaderOverlay() {
    const overlay = document.getElementById('homeLoadingOverlay');
    const content = document.getElementById('homeContent');
    if (overlay) overlay.style.display = 'none';
    if (content) content.style.opacity = '1';
}

function renderPopularHomeTickers(popularTickers) {
    const list = document.getElementById('homeMarketList');
    if (!list) return;

    const popularSymbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT'];

    const hasRows = list.querySelector('.home-market-link');
    if (!hasRows) {
        list.innerHTML = popularSymbols.map((symbol) => {
            return `
                <a href="/futures/${symbol.toLowerCase()}" class="flex items-center justify-between p-2.5 rounded-xl bg-slate-900/60 hover:bg-slate-800/60 transition-all border border-slate-800/40 home-market-link" id="marketRow_${symbol}">
                    <span class="font-extrabold text-white text-xs">${escapeHtml(symbol)}</span>
                    <strong class="font-mono text-xs font-bold text-white market-price-val">--</strong>
                    <em class="font-mono text-xs font-bold market-change-val">--</em>
                </a>
            `;
        }).join('');
    }

    popularTickers.forEach((ticker) => {
        homeTickersMap.set(ticker.symbol, ticker);

        const row = document.getElementById(`marketRow_${ticker.symbol}`);
        if (row) {
            const priceEl = row.querySelector('.market-price-val');
            const changeEl = row.querySelector('.market-change-val');

            if (priceEl) {
                priceEl.textContent = formatPrice(ticker.last_price);
            }

            if (changeEl) {
                const percentVal = Number(ticker.price_change_percent);
                changeEl.textContent = formatPercent(ticker.price_change_percent);
                changeEl.className = `font-mono text-xs font-bold not-italic market-change-val ${percentVal >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;
            }
        }
    });

    const currentSelectedTicker = homeTickersMap.get(selectedHeroSymbol) || popularTickers.find((t) => t.symbol === 'BTCUSDT');
    if (currentSelectedTicker) {
        updateHeroCardPreview(currentSelectedTicker);
    }

    const btcTicker = popularTickers.find((t) => t.symbol === 'BTCUSDT');
    if (btcTicker) updatePhonePreview(btcTicker);
}

function initFadeContentCard() {
    const card = document.querySelector('.fade-content-card');
    if (!card) return;

    // Trigger Fade Content animation after load
    setTimeout(() => {
        card.classList.add('is-visible');
    }, 150);
}

function initTypewriterText() {
    const heading = document.getElementById('heroTypedHeading');
    if (!heading) return;

    const part1 = "Trade digital markets with a ";
    const part2 = "simple & secure";
    const part3 = " platform.";

    heading.innerHTML = '';

    const textSpan1 = document.createElement('span');
    const purpleSpan = document.createElement('span');
    purpleSpan.className = 'text-purple-400';
    const textSpan3 = document.createElement('span');

    heading.appendChild(textSpan1);
    heading.appendChild(purpleSpan);
    heading.appendChild(textSpan3);

    let idx = 0;
    const typingSpeed = 50; // Requested typingSpeed: 50

    function typeStep() {
        if (idx < part1.length) {
            textSpan1.textContent += part1[idx];
            idx++;
            setTimeout(typeStep, typingSpeed);
        } else if (idx < part1.length + part2.length) {
            const p2Idx = idx - part1.length;
            purpleSpan.textContent += part2[p2Idx];
            idx++;
            setTimeout(typeStep, typingSpeed);
        } else if (idx < part1.length + part2.length + part3.length) {
            const p3Idx = idx - (part1.length + part2.length);
            textSpan3.textContent += part3[p3Idx];
            idx++;
            setTimeout(typeStep, typingSpeed);
        }
    }

    setTimeout(typeStep, 300);
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
        rootMargin: '0px 0px -50px 0px'
    });

    revealItems.forEach((item) => {
        observer.observe(item);
    });
}

function setupFaq() {
    const faqButtons = document.querySelectorAll('.home-faq-question');

    faqButtons.forEach((button) => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            const currentItem = button.closest('.home-faq-item');
            if (!currentItem) return;

            const isAlreadyOpen = currentItem.classList.contains('is-open');

            // Close ALL open FAQ items first for smooth single-open accordion behavior
            document.querySelectorAll('.home-faq-item').forEach((item) => {
                item.classList.remove('is-open');
            });

            // Toggle current item if it wasn't already open
            if (!isAlreadyOpen) {
                currentItem.classList.add('is-open');
            }
        });
    });
}

function updateSingleTopTicker(symbol, price, percent) {
    const priceEls = document.querySelectorAll(`[data-ticker-price="${symbol}"]`);
    const changeEls = document.querySelectorAll(`[data-ticker-change="${symbol}"]`);

    priceEls.forEach(el => {
        el.textContent = formatPrice(price);
    });

    changeEls.forEach(el => {
        const isPositive = percent >= 0;
        el.textContent = formatPercent(percent);
        el.className = `not-italic font-bold ${isPositive ? 'text-emerald-400' : 'text-rose-400'}`;
    });
}

async function loadHomeMarkets() {
    const list = document.getElementById('homeMarketList');
    const sourceLabel = document.getElementById('homeMarketSource');

    if (!list) {
        return;
    }

    const popularSymbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT'];

    try {
        const result = await getJson('/api/markets/futures/tickers');
        const tickers = result.data || [];

        // Update live prices on top Logo Loop marquee track
        tickers.forEach(t => {
            if (t.symbol && t.last_price !== undefined) {
                updateSingleTopTicker(t.symbol, Number(t.last_price), Number(t.price_change_percent || 0));
            }
        });

        if (sourceLabel) {
            if (result.source === 'binance') {
                sourceLabel.textContent = 'Live Futures API';
            } else if (result.source === 'bybit') {
                sourceLabel.textContent = 'Backup Futures API';
            } else if (result.source === 'coingecko') {
                sourceLabel.textContent = 'Spot Backup API';
            } else {
                sourceLabel.textContent = 'Fallback';
            }
        }

        const popularTickers = popularSymbols
            .map((symbol) => tickers.find((ticker) => ticker.symbol === symbol))
            .filter(Boolean);

        if (popularTickers.length) {
            renderPopularHomeTickers(popularTickers);

            try {
                localStorage.setItem('kryvex_cached_home_tickers', JSON.stringify(popularTickers));
            } catch (e) {}
        }

        if (isHomeFirstLoad) {
            isHomeFirstLoad = false;
            hideHomeLoaderOverlay();
        }
    } catch (error) {
        if (isHomeFirstLoad) {
            isHomeFirstLoad = false;
            hideHomeLoaderOverlay();
        }
        if (sourceLabel) {
            sourceLabel.textContent = 'Unavailable';
        }
    }
}

function connectHomeTickerSocket() {
    try {
        homeMarketSocket?.close?.();
        homeMarketSocket = new WebSocket('wss://fstream.binance.com/market/ws/!ticker@arr');

        homeMarketSocket.onmessage = (event) => {
            const rows = JSON.parse(event.data || '[]');
            if (!Array.isArray(rows)) return;

            rows.forEach((item) => {
                if (!item.s || !item.c) return;
                const lastPrice = Number(item.c || 0);
                const percent = Number(item.P || 0);

                // Update live prices on top Logo Loop marquee bar
                updateSingleTopTicker(item.s, lastPrice, percent);

                const popularSymbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'BNBUSDT'];
                if (popularSymbols.includes(item.s)) {
                    const updatedTicker = { symbol: item.s, last_price: lastPrice, price_change_percent: percent };
                    homeTickersMap.set(item.s, updatedTicker);

                    const row = document.getElementById(`marketRow_${item.s}`);
                    if (row) {
                        const priceEl = row.querySelector('.market-price-val');
                        const changeEl = row.querySelector('.market-change-val');

                        if (priceEl) priceEl.textContent = formatPrice(lastPrice);
                        if (changeEl) {
                            changeEl.textContent = formatPercent(percent);
                            changeEl.className = `font-mono text-xs font-bold not-italic market-change-val ${percent >= 0 ? 'text-emerald-400' : 'text-rose-400'}`;
                        }
                    }

                    if (item.s === selectedHeroSymbol) {
                        updateHeroCardPreview(updatedTicker);
                    }

                    if (item.s === 'BTCUSDT') {
                        updatePhonePreview({
                            last_price: lastPrice,
                            price_change_percent: percent,
                        });
                    }
                }
            });
        };

        homeMarketSocket.onclose = () => setTimeout(connectHomeTickerSocket, 3000);
    } catch (error) {
        // API refresh still keeps the home page updated.
    }
}

function updatePhonePreview(ticker) {
    if (!ticker) return;

    const price = document.getElementById('phoneBtcPreview');
    const change = document.getElementById('phoneBtcChangePreview');

    if (price) price.textContent = formatPrice(ticker.last_price);
    if (change) {
        change.textContent = formatPercent(ticker.price_change_percent);
        change.style.color = Number(ticker.price_change_percent || 0) >= 0 ? '#22c55e' : '#ef4444';
    }
}

function initScrollRevealText() {
    const heading = document.getElementById('scrollRevealHeading');
    if (!heading) return;

    const text = heading.textContent.trim();
    const words = text.split(/\s+/);
    
    heading.innerHTML = words.map((word, idx) => {
        return `<span class="scroll-reveal-word">${escapeHtml(word)}</span>`;
    }).join(' ');

    const wordsElements = heading.querySelectorAll('.scroll-reveal-word');

    function updateTextReveal() {
        const rect = heading.getBoundingClientRect();
        const viewportHeight = window.innerHeight;
        
        const start = viewportHeight * 0.95;
        const end = viewportHeight * 0.45;
        
        const progress = (start - rect.top) / (start - end);
        const clampedProgress = Math.max(0, Math.min(1, progress));
        
        const revealCount = Math.floor(clampedProgress * wordsElements.length);
        
        wordsElements.forEach((el, idx) => {
            if (idx <= revealCount) {
                el.classList.add('is-revealed');
            } else {
                el.classList.remove('is-revealed');
            }
        });
    }

    window.addEventListener('scroll', updateTextReveal);
    window.addEventListener('resize', updateTextReveal);
    updateTextReveal();
}

function initCursorGrid() {
    const canvas = document.getElementById('cursorGridCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    let width = 0;
    let height = 0;
    let mouseX = -1000;
    let mouseY = -1000;
    let targetMouseX = -1000;
    let targetMouseY = -1000;

    const gridSize = 45; // Grid cell size in px
    const radius = 200;   // Glow spotlight radius

    function resize() {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width;
        canvas.height = height;
    }

    resize();
    window.addEventListener('resize', resize);
    setTimeout(resize, 1000);
    setTimeout(resize, 2500);

    window.addEventListener('mousemove', (e) => {
        targetMouseX = e.clientX;
        targetMouseY = e.clientY;
    });

    document.addEventListener('mouseleave', () => {
        targetMouseX = -1000;
        targetMouseY = -1000;
    });

    function draw() {
        ctx.clearRect(0, 0, width, height);

        // Draw subtle base grid lines across the screen
        const isLight = document.documentElement.getAttribute('data-theme') === 'light' || document.documentElement.classList.contains('theme-light');
        ctx.strokeStyle = isLight ? 'rgba(15, 23, 42, 0.04)' : 'rgba(255, 255, 255, 0.035)';
        ctx.lineWidth = 1;
        ctx.beginPath();
        for (let x = 0; x <= width; x += gridSize) {
            ctx.moveTo(x, 0);
            ctx.lineTo(x, height);
        }
        for (let y = 0; y <= height; y += gridSize) {
            ctx.moveTo(0, y);
            ctx.lineTo(width, y);
        }
        ctx.stroke();

        requestAnimationFrame(draw);
    }

    requestAnimationFrame(draw);
}

function initSplitTextAnimation() {
    const headings = document.querySelectorAll('.home-page h2');
    if (!headings.length) return;

    headings.forEach((heading) => {
        heading.classList.add('split-text-heading');

        const nodes = Array.from(heading.childNodes);
        heading.innerHTML = '';

        let charCount = 0;

        nodes.forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                const text = node.textContent;
                const words = text.split(/(\s+)/);

                words.forEach((word) => {
                    if (/^\s+$/.test(word)) {
                        heading.appendChild(document.createTextNode(word));
                    } else {
                        const wordSpan = document.createElement('span');
                        wordSpan.className = 'split-word inline-block whitespace-nowrap';

                        for (let i = 0; i < word.length; i++) {
                            const charSpan = document.createElement('span');
                            charSpan.className = 'split-char';
                            charSpan.style.transitionDelay = `${charCount * 30}ms`;
                            charSpan.textContent = word[i];
                            wordSpan.appendChild(charSpan);
                            charCount++;
                        }
                        heading.appendChild(wordSpan);
                    }
                });
            } else if (node.nodeType === Node.ELEMENT_NODE) {
                const elementSpan = document.createElement('span');
                elementSpan.className = node.className || '';
                const text = node.textContent;

                for (let i = 0; i < text.length; i++) {
                    const charSpan = document.createElement('span');
                    charSpan.className = 'split-char';
                    charSpan.style.transitionDelay = `${charCount * 30}ms`;
                    charSpan.textContent = text[i];
                    elementSpan.appendChild(charSpan);
                    charCount++;
                }
                heading.appendChild(elementSpan);
            }
        });
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const chars = entry.target.querySelectorAll('.split-char');
                chars.forEach((char) => char.classList.add('is-animated'));
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.15
    });

    headings.forEach((heading) => observer.observe(heading));
}

function initAnimatedContentCards() {
    const cards = document.querySelectorAll('.animated-content-card');
    if (!cards.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const delay = parseInt(entry.target.dataset.delay || '0', 10);
                setTimeout(() => {
                    entry.target.classList.add('is-visible');
                }, delay);
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    cards.forEach((card) => observer.observe(card));
}

function initFadeContentScrollCards() {
    const cards = document.querySelectorAll('.fade-content-scroll-card');
    if (!cards.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                const delay = parseInt(entry.target.dataset.delay || '100', 10);
                setTimeout(() => {
                    entry.target.classList.add('is-visible');
                }, delay);
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    cards.forEach((card) => observer.observe(card));
}

function initHeroChartHover() {
    const container = document.getElementById('heroChartContainer');
    const strokeEl = document.getElementById('heroChartStroke');
    const hoverGroup = document.getElementById('heroChartHoverGroup');
    const crosshairLine = document.getElementById('heroChartCrosshairLine');
    const pulseCircle = document.getElementById('heroChartPulseCircle');
    const dotCircle = document.getElementById('heroChartDotCircle');
    const tooltip = document.getElementById('heroChartTooltip');
    const tooltipPrice = document.getElementById('heroChartTooltipPrice');
    const pricePreview = document.getElementById('heroPricePreview');

    if (!container || !strokeEl || !hoverGroup) return;

    function findSvgPointForX(targetX) {
        try {
            const totalLen = strokeEl.getTotalLength();
            if (!totalLen) return { x: targetX, y: 40 };

            let low = 0;
            let high = totalLen;
            let bestPoint = strokeEl.getPointAtLength(0);

            for (let i = 0; i < 20; i++) {
                const mid = (low + high) / 2;
                const p = strokeEl.getPointAtLength(mid);
                if (Math.abs(p.x - targetX) < 0.1) {
                    return p;
                }
                if (p.x < targetX) {
                    low = mid;
                } else {
                    high = mid;
                }
                bestPoint = p;
            }
            return bestPoint;
        } catch {
            return { x: targetX, y: 40 };
        }
    }

    container.addEventListener('mousemove', (e) => {
        const rect = container.getBoundingClientRect();
        if (rect.width <= 0) return;

        const mouseX = Math.max(0, Math.min(rect.width, e.clientX - rect.left));
        const svgX = (mouseX / rect.width) * 300;

        const pt = findSvgPointForX(svgX);
        const ptCanvasX = (pt.x / 300) * rect.width;
        const ptCanvasY = (pt.y / 80) * rect.height;

        const ticker = homeTickersMap.get(selectedHeroSymbol) || { last_price: 64307.1, price_change_percent: 0.31 };
        const lastPrice = Number(ticker.last_price || 0);
        const changePct = Number(ticker.price_change_percent || 0);
        const isPositive = changePct >= 0;

        const rangeRatio = Math.max(0, Math.min(1, (75 - pt.y) / 70));
        const priceDelta = lastPrice * (Math.max(0.01, Math.abs(changePct) / 100)) * 1.5;
        const minPrice = isPositive ? (lastPrice - priceDelta) : lastPrice;
        const maxPrice = isPositive ? lastPrice : (lastPrice + priceDelta);
        const hoverPrice = minPrice + (rangeRatio * (maxPrice - minPrice));

        const formattedPrice = formatPrice(hoverPrice);

        crosshairLine.setAttribute('x1', pt.x);
        crosshairLine.setAttribute('x2', pt.x);
        pulseCircle.setAttribute('cx', pt.x);
        pulseCircle.setAttribute('cy', pt.y);
        dotCircle.setAttribute('cx', pt.x);
        dotCircle.setAttribute('cy', pt.y);

        const themeColor = isPositive ? '#10b981' : '#f43f5e';
        const pulseColor = isPositive ? 'rgba(16, 185, 129, 0.25)' : 'rgba(244, 63, 94, 0.25)';
        pulseCircle.setAttribute('fill', pulseColor);
        dotCircle.setAttribute('fill', themeColor);

        tooltip.style.left = `${ptCanvasX}px`;
        tooltip.style.top = `${ptCanvasY}px`;
        if (tooltipPrice) tooltipPrice.textContent = formattedPrice;

        hoverGroup.classList.remove('opacity-0');
        hoverGroup.classList.add('opacity-100');
        tooltip.classList.remove('opacity-0');
        tooltip.classList.add('opacity-100');

        if (pricePreview) pricePreview.textContent = formattedPrice;
    });

    container.addEventListener('mouseleave', () => {
        hoverGroup.classList.remove('opacity-100');
        hoverGroup.classList.add('opacity-0');
        tooltip.classList.remove('opacity-100');
        tooltip.classList.add('opacity-0');

        const ticker = homeTickersMap.get(selectedHeroSymbol);
        if (ticker && pricePreview) {
            pricePreview.textContent = formatPrice(ticker.last_price);
        }
    });
}

function initPortfolioInteractiveHover() {
    const assetRows = document.querySelectorAll('.portfolio-asset-row');
    const donutSegments = document.querySelectorAll('.portfolio-donut-segment');
    const labelEl = document.getElementById('portfolioDonutLabel');
    const valueEl = document.getElementById('portfolioDonutValue');
    const subEl = document.getElementById('portfolioDonutSub');

    if (!assetRows.length || !donutSegments.length) return;

    function activateAsset(key) {
        const matchingRow = document.querySelector(`.portfolio-asset-row[data-portfolio-asset="${key}"]`);
        const matchingSegment = document.querySelector(`.portfolio-donut-segment[data-portfolio-segment="${key}"]`);

        assetRows.forEach((r) => r.classList.remove('is-active'));
        donutSegments.forEach((s) => s.classList.remove('is-highlighted'));

        if (matchingRow) matchingRow.classList.add('is-active');
        if (matchingSegment) matchingSegment.classList.add('is-highlighted');

        if (matchingSegment && labelEl && valueEl && subEl) {
            labelEl.textContent = matchingSegment.dataset.assetName || 'Asset';
            valueEl.textContent = matchingSegment.dataset.assetVal || '$0.00';
            subEl.textContent = matchingSegment.dataset.assetPct || '0%';
        }
    }

    function resetAsset() {
        assetRows.forEach((r) => r.classList.remove('is-active'));
        donutSegments.forEach((s) => s.classList.remove('is-highlighted'));

        if (labelEl && valueEl && subEl) {
            labelEl.textContent = 'Equity';
            valueEl.textContent = '$24.8k';
            subEl.textContent = '3 Assets';
        }
    }

    assetRows.forEach((row) => {
        const key = row.dataset.portfolioAsset;
        row.addEventListener('mouseenter', () => activateAsset(key));
        row.addEventListener('mouseleave', resetAsset);
    });

    donutSegments.forEach((segment) => {
        const key = segment.dataset.portfolioSegment;
        segment.addEventListener('mouseenter', () => activateAsset(key));
        segment.addEventListener('mouseleave', resetAsset);
    });
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
    const progressBar = document.getElementById('homeScrollProgressBar');
    const topBtn = document.getElementById('homeScrollToTopBtn');

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
    const topBtn = document.getElementById('homeScrollToTopBtn');
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

