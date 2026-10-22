export function formatPrice(value) {
    const number = Number(value);

    if (!number) {
        return '$ --';
    }

    return `$${number.toLocaleString('en-US', {
        minimumFractionDigits: number >= 100 ? 2 : 4,
        maximumFractionDigits: number >= 100 ? 2 : 4,
    })}`;
}

export function formatPercent(value) {
    const number = Number(value);

    if (!number) {
        return '--%';
    }

    return `${number >= 0 ? '+' : ''}${number.toFixed(2)}%`;
}

export function formatCompact(value) {
    const number = Number(value);

    if (!number) {
        return '--';
    }

    return Intl.NumberFormat('en-US', {
        notation: 'compact',
        maximumFractionDigits: 2,
    }).format(number);
}

export function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}