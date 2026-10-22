function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatShortToastMessage(msg, type = 'success', title = '') {
    let text = String(msg || title || '').trim();
    if (!text) return type === 'success' ? 'Success' : 'Notice';

    const lower = text.toLowerCase();

    // 1. Error message mappings
    if (type === 'error') {
        if (lower.includes('open trading position') || lower.includes('open position') || lower.includes('minimum reserve')) {
            return 'Open positions exist - reserve required';
        }
        if (lower.includes('insufficient inr') || (lower.includes('insufficient') && lower.includes('inr'))) {
            return 'Insufficient INR balance';
        }
        if (lower.includes('insufficient') && (lower.includes('usd') || lower.includes('usdt'))) {
            return 'Insufficient USD balance';
        }
        if (lower.includes('insufficient') || lower.includes('not enough')) {
            return 'Insufficient balance';
        }
        if (lower.includes('bank details') || lower.includes('save bank')) {
            return 'Bank details required';
        }
        if (lower.includes('kyc verification') || lower.includes('complete kyc')) {
            return 'KYC required';
        }
        if (lower.includes('unable to close') || lower.includes('close failed')) {
            return 'Close failed';
        }
        if (lower.includes('unable to cancel') || lower.includes('cancel failed')) {
            return 'Cancel failed';
        }
        if (lower.includes('deleted') || lower.includes('account has been closed') || lower.includes('account is closed')) {
            return 'Account deleted / closed';
        }
        if (lower.includes('already registered')) {
            return 'Email already registered';
        }
        if (lower.includes('login first') || lower.includes('please login') || lower.includes('unauthenticated')) {
            return 'Login required';
        }
    }

    // 2. Success / Exact Keyword mapping
    if (type === 'success') {
        if (lower.includes('all open trade') || lower.includes('all open position') || (lower.includes('all position') && (lower.includes('closed') || lower.includes('settled')))) {
            return 'All positions closed';
        }
        if (lower.includes('trade closed successfully') || lower.includes('position closed') || lower.includes('trade closed')) {
            if (lower.includes('auto') || lower.includes('touched')) return 'Trade auto-closed';
            return 'Position closed';
        }
        if (lower.includes('order placed') || lower.includes('order submitted') || lower.includes('demo order placed') || lower.includes('order created')) {
            return 'Order placed';
        }
        if (lower.includes('all order') && (lower.includes('cancel') || lower.includes('close'))) {
            return 'All orders cancelled';
        }
        if (lower.includes('order cancel') || lower.includes('cancelled and margin')) {
            return 'Order cancelled';
        }
        if (lower.includes('no open trade') || lower.includes('no open position') || lower.includes('no open order')) {
            return 'No open trades';
        }
        if (lower.includes('deposit credited') || lower.includes('funds added') || lower.includes('deposit success') || lower.includes('payment success')) {
            return 'Deposit completed';
        }
        if (lower.includes('withdrawal submitted') || lower.includes('withdrawal request') || lower.includes('withdraw success') || lower.includes('inr withdrawal submitted')) {
            return 'Withdrawal submitted';
        }
        if (lower.includes('transfer completed') || lower.includes('funds transfer')) {
            return 'Transfer completed';
        }
        if (lower.includes('copied') || lower.includes('clipboard')) {
            return 'Copied';
        }
        if (lower.includes('take profit') || lower.includes('stop loss') || lower.includes('tp/sl')) {
            return 'TP/SL updated';
        }
        if (lower.includes('bank details')) {
            return 'Bank details saved';
        }
        if (lower.includes('password update') || lower.includes('password change')) {
            return 'Password updated';
        }
        if (lower.includes('profile update') || lower.includes('settings save') || lower.includes('preferences save')) {
            return 'Settings saved';
        }
    }

    if (lower.includes('market price unavailable') || lower.includes('price is not available')) {
        return 'Price unavailable';
    }

    // 2. Generic Wordy Suffix Cleaning
    let clean = text
        .replace(/^(success|error|notice|warning|info)\s*[:\-–—]\s*/i, '')
        .replace(/\s*(successfully|was successful|has been completed|in system)\.?$/i, '')
        .replace(/\s*and margin released back to your available balance\.?$/i, '')
        .replace(/\s*and margin released\.?$/i, '')
        .trim();

    // If still long and has a colon (e.g., "Error: message"), take the relevant part
    if (clean.length > 35 && clean.includes(':')) {
        const parts = clean.split(':');
        clean = parts[1].trim() || parts[0].trim();
    }
    // If still long with period, take first sentence
    if (clean.length > 40 && clean.includes('.')) {
        clean = clean.split('.')[0].trim();
    }

    clean = clean.replace(/[.]+$/, '').trim();

    return clean || text;
}

class ToastManager {
    constructor() {
        this.toasts = [];
        this.host = null;
        this.maxToasts = {
            desktop: 3,
            tablet: 3,
            mobile: 2
        };
        this.defaultDurations = {
            success: 2800,
            info: 2400,
            warning: 4000,
            error: 4500,
            loading: 0 // Infinite
        };
        this.icons = {
            success: `<span class="toast-status-badge toast-status-success">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
            </span>`,
            error: `<span class="toast-status-badge toast-status-error">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </span>`,
            warning: `<span class="toast-status-badge toast-status-warning">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="8" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </span>`,
            info: `<span class="toast-status-badge toast-status-info">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </span>`,
            loading: `<div class="toast-spinner"></div>`
        };

        this.initHost();
        window.addEventListener('resize', () => this.updatePositioning());
    }

    initHost() {
        this.host = document.getElementById('globalToastHost');
        if (!this.host) {
            this.host = document.createElement('div');
            this.host.id = 'globalToastHost';
            this.host.className = 'global-toast-host';
            document.body.appendChild(this.host);
        }
        this.updatePositioning();
    }

    getScreenType() {
        const width = window.innerWidth;
        if (width <= 768) return 'mobile';
        if (width <= 1024) return 'tablet';
        return 'desktop';
    }

    updatePositioning() {
        if (!this.host) return;
        const screen = this.getScreenType();
        this.host.className = `global-toast-host is-${screen}`;
        this.processQueue();
    }

    show({ type = 'success', title = '', message = '', duration = null }) {
        const toastId = 'toast_' + Math.random().toString(36).substr(2, 9);
        const screen = this.getScreenType();

        const toastEl = document.createElement('div');
        toastEl.id = toastId;
        toastEl.className = `global-toast is-${type}`;
        
        // Render content with shortened text
        this.renderContent(toastEl, type, title, message);

        const toastObj = {
            id: toastId,
            element: toastEl,
            type,
            title,
            message,
            duration: duration !== null ? duration : this.defaultDurations[type],
            startTime: Date.now(),
            remainingTime: duration !== null ? duration : this.defaultDurations[type],
            timeoutId: null,
            paused: false,
            
            pause: () => {
                if (toastObj.type === 'loading' || toastObj.duration === 0) return;
                toastObj.paused = true;
                clearTimeout(toastObj.timeoutId);
                toastObj.remainingTime -= Date.now() - toastObj.startTime;
            },
            
            resume: () => {
                if (toastObj.type === 'loading' || toastObj.duration === 0) return;
                toastObj.paused = false;
                toastObj.startTime = Date.now();
                toastObj.timeoutId = setTimeout(() => this.close(toastId), Math.max(toastObj.remainingTime, 100));
            },

            update: (newParams) => {
                clearTimeout(toastObj.timeoutId);
                toastObj.type = newParams.type || toastObj.type;
                toastObj.title = newParams.title !== undefined ? newParams.title : toastObj.title;
                toastObj.message = newParams.message !== undefined ? newParams.message : toastObj.message;
                toastObj.duration = newParams.duration !== undefined ? newParams.duration : this.defaultDurations[toastObj.type];
                toastObj.remainingTime = toastObj.duration;
                toastObj.paused = false;

                // Re-render
                toastEl.className = `global-toast is-${toastObj.type}`;
                this.renderContent(toastEl, toastObj.type, toastObj.title, toastObj.message);

                if (toastObj.duration > 0) {
                    toastObj.startTime = Date.now();
                    toastObj.timeoutId = setTimeout(() => this.close(toastId), toastObj.duration);
                }
            },

            close: () => this.close(toastId)
        };

        // Hover event listeners for desktop/tablet
        if (screen !== 'mobile') {
            toastEl.addEventListener('mouseenter', () => toastObj.pause());
            toastEl.addEventListener('mouseleave', () => toastObj.resume());
        }

        this.toasts.push(toastObj);
        this.processQueue();

        return {
            update: (newParams) => toastObj.update(newParams),
            close: () => toastObj.close()
        };
    }

    renderContent(el, type, title, message) {
        const iconSvg = this.icons[type] || this.icons.info;
        const shortText = formatShortToastMessage(message, type, title);

        el.innerHTML = `
            <div class="toast-icon-wrap">${iconSvg}</div>
            <div class="toast-body">
                <span class="toast-desc-inline">${escapeHtml(shortText)}</span>
            </div>
            <button class="toast-close-btn" type="button" aria-label="Dismiss toast">✕</button>
        `;

        el.querySelector('.toast-close-btn').addEventListener('click', (e) => {
            e.stopPropagation();
            const id = el.id;
            this.close(id);
        });
    }

    close(id) {
        const index = this.toasts.findIndex(t => t.id === id);
        if (index === -1) return;

        const toastObj = this.toasts[index];
        clearTimeout(toastObj.timeoutId);
        
        // Remove from toasts queue immediately
        this.toasts.splice(index, 1);

        if (toastObj.element) {
            toastObj.element.classList.add('is-dismissing');
            setTimeout(() => {
                if (toastObj.element.parentNode) {
                    toastObj.element.parentNode.removeChild(toastObj.element);
                }
            }, 180);
        }

        this.processQueue();
    }

    processQueue() {
        if (!this.host) return;

        const screen = this.getScreenType();
        const max = this.maxToasts[screen];

        // 1. If currently total toasts exceed max (3), dismiss the oldest ones immediately
        while (this.toasts.length > max) {
            const oldest = this.toasts.shift();
            if (oldest) {
                clearTimeout(oldest.timeoutId);
                if (oldest.element) {
                    oldest.element.classList.add('is-dismissing');
                    const el = oldest.element;
                    setTimeout(() => {
                        if (el.parentNode) {
                            el.parentNode.removeChild(el);
                        }
                    }, 150);
                }
            }
        }

        // 2. Append new toasts up to max limit
        const activeToastsInDom = this.toasts.filter(t => t.element.parentNode);
        const pending = this.toasts.filter(t => !t.element.parentNode);

        const availableSlots = max - activeToastsInDom.length;
        for (let i = 0; i < Math.min(availableSlots, pending.length); i++) {
            const toastObj = pending[i];
            this.host.appendChild(toastObj.element);
            
            // Trigger animation frame to start transition
            requestAnimationFrame(() => {
                toastObj.element.classList.add('is-active');
            });

            if (toastObj.duration > 0) {
                toastObj.startTime = Date.now();
                toastObj.timeoutId = setTimeout(() => this.close(toastObj.id), toastObj.duration);
            }
        }
    }
}

// Instantiate and export globally
const manager = new ToastManager();
window.showToast = (params) => manager.show(params);
window.Toast = {
    show: (params) => manager.show(params),
    success: (message, title = 'Success') => manager.show({ type: 'success', title, message }),
    error: (message, title = 'Error') => manager.show({ type: 'error', title, message }),
    info: (message, title = 'Info') => manager.show({ type: 'info', title, message }),
    warning: (message, title = 'Warning') => manager.show({ type: 'warning', title, message }),
};
export default window.showToast;
