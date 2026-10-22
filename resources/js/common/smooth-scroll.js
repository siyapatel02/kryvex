import Lenis from 'lenis';
import 'lenis/dist/lenis.css';

let lenisInstance = null;

export function initSmoothScroll() {
    if (typeof window === 'undefined') return null;
    if (lenisInstance) return lenisInstance;

    // Check if user prefers reduced motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (prefersReducedMotion) {
        document.documentElement.style.scrollBehavior = 'smooth';
        return null;
    }

    try {
        lenisInstance = new Lenis({
            duration: 1.15,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)), // ultra smooth ease-out
            orientation: 'vertical',
            gestureOrientation: 'vertical',
            smoothWheel: true,
            wheelMultiplier: 0.95,
            touchMultiplier: 1.5,
            infinite: false,
            autoRaf: false,
        });

        // Continuous requestAnimationFrame loop
        function raf(time) {
            if (lenisInstance) {
                lenisInstance.raf(time);
            }
            requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);

        // Expose globally for pages to use: window.lenis or window.smoothScrollTo
        window.lenis = lenisInstance;
        window.smoothScrollTo = (target, options = {}) => {
            if (lenisInstance) {
                lenisInstance.scrollTo(target, { offset: options.offset || 0, duration: options.duration || 1.2, ...options });
            } else {
                const el = typeof target === 'string' ? document.querySelector(target) : target;
                if (el) el.scrollIntoView({ behavior: 'smooth' });
            }
        };

        // Prevent Lenis on interactive components that need native gestures (TradingView chart, modals, order books, iframes)
        const preventSelectors = [
            '#tradingViewChart',
            '#spotTradingViewWidget',
            '.spot-chart-container',
            '.tradingview-widget-container',
            'iframe',
            '.modal-box',
            '.modal-overlay',
            '.kv-modal',
            '[data-no-lenis]',
            '.orderbook-container',
            '.order-book-table-wrap',
            '.futures-record-table-wrap',
            '.wallet-ledger-table-wrap',
            '.dharma-acc-menu',
            '.trading-dropdown-menu',
            '.kvx-currency-dropdown',
            '.kvx-currency-list',
            '.contract-dropdown',
            '.contract-dropdown-list',
            '.contract-dropdown-backdrop',
            '.contract-row',
            '.contract-search',
            '.contract-filter-row',
            '.contract-list-head',
            '.futures-market-panel',
            '.futures-order-panel',
            '.futures-positions-cards-list',
            '.kv-nav-accounts-dropdown',
            '.kv-accounts-drop-list'
        ];

        function applyPreventAttributes() {
            preventSelectors.forEach(selector => {
                document.querySelectorAll(selector).forEach(el => {
                    if (!el.hasAttribute('data-lenis-prevent')) {
                        el.setAttribute('data-lenis-prevent', '');
                    }
                });
            });
        }

        applyPreventAttributes();

        // Observe DOM for dynamically rendered charts and modals
        const observer = new MutationObserver(() => {
            applyPreventAttributes();
        });
        observer.observe(document.body, { childList: true, subtree: true });

        // Smooth scroll for internal anchor links (e.g. href="#faq")
        document.addEventListener('click', (e) => {
            const anchor = e.target.closest('a[href^="#"]');
            if (!anchor) return;
            const href = anchor.getAttribute('href');
            if (href && href.length > 1) {
                const target = document.querySelector(href);
                if (target && lenisInstance) {
                    e.preventDefault();
                    lenisInstance.scrollTo(target, { offset: -70, duration: 1.2 });
                }
            }
        });

        return lenisInstance;
    } catch (err) {
        console.warn('Smooth scroll initialized fallback:', err);
        document.documentElement.style.scrollBehavior = 'smooth';
        return null;
    }
}

export default initSmoothScroll;
