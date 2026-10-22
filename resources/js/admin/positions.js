document.addEventListener('DOMContentLoaded', () => {
    const priceCells = Array.from(document.querySelectorAll('[data-live-price-symbol]'))
        .filter((cell) => (cell.dataset.livePriceSymbol || '').trim() !== '');

    if (!priceCells.length) return;

    const formatUsd = (value, decimals = 4) => {
        const number = Number(value || 0);
        return `$${number.toLocaleString(undefined, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        })}`;
    };

    const updatePnl = (row, livePrice) => {
        const pnlEl = row.querySelector('[data-live-pnl]');
        if (!pnlEl || pnlEl.dataset.status !== 'open') return;

        const entry = Number(pnlEl.dataset.entry || 0);
        const qty = Number(pnlEl.dataset.qty || 0);
        const side = (pnlEl.dataset.side || 'buy').toLowerCase();
        const pnl = side === 'sell' ? (entry - livePrice) * qty : (livePrice - entry) * qty;

        pnlEl.textContent = `$${pnl.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 4,
        })}`;
        pnlEl.classList.toggle('pnl-profit', pnl >= 0);
        pnlEl.classList.toggle('pnl-loss', pnl < 0);
    };

    const fetchPrice = async (symbol) => {
        const response = await fetch(`/api/markets/futures/${encodeURIComponent(symbol)}/stats`, {
            headers: { Accept: 'application/json' },
        });
        const payload = await response.json();
        return Number(payload?.data?.mark_price || payload?.data?.index_price || 0);
    };

    const refreshLivePrices = async () => {
        const symbols = [...new Set(priceCells.map((cell) => cell.dataset.livePriceSymbol).filter(Boolean))];

        await Promise.all(symbols.map(async (symbol) => {
            try {
                const livePrice = await fetchPrice(symbol);
                if (!livePrice) return;

                priceCells
                    .filter((cell) => cell.dataset.livePriceSymbol === symbol)
                    .forEach((cell) => {
                        const valueEl = cell.querySelector('[data-live-price-value]');
                        if (valueEl) valueEl.textContent = formatUsd(livePrice, 4);
                        updatePnl(cell.closest('tr'), livePrice);
                    });
            } catch (error) {
                console.warn('Admin live price update failed:', symbol, error);
            }
        }));
    };

    refreshLivePrices();
    setInterval(refreshLivePrices, 7000);
});
