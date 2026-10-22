import './common/translator.js';
import { initSmoothScroll } from './common/smooth-scroll.js';
import { isCentAccount, formatUsc } from './utils/cent.js';

function initApp() {
    initSmoothScroll();
    setupSessionTimeout();
    setupMobileMenu();
    setupNavbarActiveShift();
    setupNavbarAuthState();
    setupNavbarTradingModeToggle();
    setupNavbarCurrencyToggle();
    setupNavbarAvatarDropdown();
    setupLogout();
    setupGlobalToasts();
    setupConfirmModal();
    setupNavbarNotifications();
    setupNavbarFuturesAccountsDropdown();
    setupNavbarBalanceAndDeposit();
    setupBackNavigationBlock();
    setupGlobalKeyboardShortcuts();
    setupNumberInputWheelBlock();
    setupGlobalBrokerModals();
    setupGlobalAddFundsModal();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

function setupMobileMenu() {
    const toggle = document.getElementById('mobileMenuToggle');
    const links = document.getElementById('mainNavLinks');

    if (!toggle || !links) return;

    toggle.addEventListener('click', (e) => {
        e.stopPropagation();
        links.classList.toggle('is-open');
    });

    const closeMobileMenu = (e) => {
        if (links.classList.contains('is-open') && !links.contains(e.target) && !toggle.contains(e.target)) {
            links.classList.remove('is-open');
        }
    };

    document.addEventListener('click', closeMobileMenu);
    document.addEventListener('touchstart', closeMobileMenu, { passive: true });

    links.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            links.classList.remove('is-open');
        });
    });
}

function setupNavbarActiveShift() {
    const navLinksContainer = document.getElementById('mainNavLinks');
    if (!navLinksContainer) return;

    navLinksContainer.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        if (link && navLinksContainer.contains(link)) {
            navLinksContainer.querySelectorAll('a').forEach((item) => {
                item.classList.remove('active');
            });
            link.classList.add('active');
        }
    });
}

function setupNavbarAuthState() {
    const guestActions = document.getElementById('guestNavActions');
    const userActions = document.getElementById('userNavActions');
    const navWalletLink = document.getElementById('navWalletLink');
    const accessToken = localStorage.getItem('kryvex_access_token');

    if (navWalletLink) {
        navWalletLink.style.display = accessToken ? 'inline-block' : 'none';
    }

    if (!guestActions || !userActions) return;

    guestActions.style.display = accessToken ? 'none' : 'flex';
    userActions.style.display = accessToken ? 'flex' : 'none';

    if (accessToken) {
        setNavbarUserName();
        if (typeof window.updateNavbarBalanceDisplay === 'function') {
            window.updateNavbarBalanceDisplay();
        }
    }
}

function setNavbarUserName() {
    const initial = document.getElementById('navUserInitial');
    const dropInitial = document.getElementById('dropdownAvatarInitial');
    const dropName = document.getElementById('dropdownUserNameText');
    const dropEmail = document.getElementById('dropdownUserEmailText');
    const dropUid = document.getElementById('dropdownUserUidText');
    const dropKyc = document.getElementById('dropdownKycBadge');
    const dropVerifyCard = document.getElementById('dropdownVerifyCard');

    let user = {};

    try {
        user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (error) {
        user = {};
    }

    const displayName = user.name || user.email || 'Profile';
    const email = user.email || displayName;
    const initChar = String(displayName).trim().charAt(0).toUpperCase() || 'U';

    if (initial) initial.textContent = initChar;
    if (dropInitial) dropInitial.textContent = initChar;
    if (dropName) dropName.textContent = displayName;
    if (dropEmail) dropEmail.textContent = email;

    // Display Client ID from Settings -> Profile Details
    const clientId = user.client_id || user.id || '2620109';
    if (dropUid) dropUid.textContent = clientId;

    const profileDot = document.getElementById('navProfileKycDot') || document.querySelector('#navAvatarBtn .kv-profile-dot') || document.querySelector('.kv-profile-dot');

    const kycStatus = String(user.kyc_status || user.kyc || '').toLowerCase();
    const isVerified = ['verified', 'approved'].includes(kycStatus) || user.is_kyc_verified === true || user.kyc_verified === true;
    const isPending = ['pending', 'pending_review', 'review'].includes(kycStatus);

    if (profileDot) {
        profileDot.style.display = isVerified ? 'none' : 'block';
    }

    if (dropKyc) {
        if (isVerified) {
            dropKyc.textContent = 'Verified';
            dropKyc.className = 'kv-profile-badge kv-badge-verified';
        } else if (isPending) {
            dropKyc.textContent = 'Pending';
            dropKyc.className = 'kv-profile-badge kv-badge-pending';
        } else {
            dropKyc.textContent = 'Unverified';
            dropKyc.className = 'kv-profile-badge kv-badge-unverified';
        }
    }

    if (dropVerifyCard) {
        dropVerifyCard.style.display = isVerified ? 'none' : 'flex';
    }

    // Refresh client_id from backend if not already saved in local storage
    const token = localStorage.getItem('kryvex_access_token');
    if (token && (!user.client_id || user.client_id === '----' || !window.__kryvex_profile_checked)) {
        window.__kryvex_profile_checked = true;
        fetch('/api/auth/me', {
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.success && data.data && data.data.user) {
                const freshUser = { ...user, ...data.data.user };
                localStorage.setItem('kryvex_user', JSON.stringify(freshUser));
                const freshClientId = freshUser.client_id || freshUser.id || '----';
                if (dropUid) {
                    dropUid.textContent = freshClientId;
                }
                if (dropName && freshUser.name) {
                    dropName.textContent = freshUser.name;
                }
                if (dropEmail && freshUser.email) {
                    dropEmail.textContent = freshUser.email;
                }
                const freshKyc = String(freshUser.kyc_status || freshUser.kyc || '').toLowerCase();
                const freshVerified = ['verified', 'approved'].includes(freshKyc) || freshUser.is_kyc_verified === true || freshUser.kyc_verified === true;
                const freshPending = ['pending', 'pending_review', 'review'].includes(freshKyc);
                if (profileDot) {
                    profileDot.style.display = freshVerified ? 'none' : 'block';
                }
                if (dropKyc) {
                    if (freshVerified) {
                        dropKyc.textContent = 'Verified';
                        dropKyc.className = 'kv-profile-badge kv-badge-verified';
                    } else if (freshPending) {
                        dropKyc.textContent = 'Pending';
                        dropKyc.className = 'kv-profile-badge kv-badge-pending';
                    } else {
                        dropKyc.textContent = 'Unverified';
                        dropKyc.className = 'kv-profile-badge kv-badge-unverified';
                    }
                }
                if (dropVerifyCard) {
                    dropVerifyCard.style.display = freshVerified ? 'none' : 'flex';
                }
            }
        })
        .catch(() => {});
    }
}
window.setNavbarUserName = setNavbarUserName;

window.addEventListener('storage', (e) => {
    if (e.key === 'kryvex_user') {
        setNavbarUserName();
    }
});
window.addEventListener('kryvex_user_updated', () => {
    setNavbarUserName();
});
window.addEventListener('kycStatusChanged', () => {
    setNavbarUserName();
});

function setupNavbarAvatarDropdown() {
    const avatarBtn = document.getElementById('navAvatarBtn');
    const dropdown = document.getElementById('navAvatarDropdown');
    const wrap = avatarBtn ? avatarBtn.closest('.kv-profile-dropdown-wrap') : null;
    const copyUidBtn = document.getElementById('btnCopyDropdownUid');
    const createAccBtn = document.getElementById('dropdownCreateAccountBtn');

    function syncThemePills() {
        const cur = localStorage.getItem('kryvex_theme') || 'dark';
        document.querySelectorAll('.kv-theme-pill-btn').forEach(btn => {
            const mode = btn.getAttribute('data-theme-mode');
            if (mode === cur) {
                btn.classList.add('is-active');
            } else {
                btn.classList.remove('is-active');
            }
        });
    }

    if (avatarBtn && dropdown) {
        let avatarCloseTimer = null;

        const openAvatarDrop = () => {
            if (avatarCloseTimer) {
                clearTimeout(avatarCloseTimer);
                avatarCloseTimer = null;
            }
            // Close balance dropdown if open
            const balanceWrap = document.getElementById('navBalanceWrap');
            const balanceDropdown = document.getElementById('navBalanceDropdown');
            if (balanceWrap) balanceWrap.classList.remove('is-open');
            if (balanceDropdown) balanceDropdown.hidden = true;

            dropdown.hidden = false;
            syncThemePills();
        };

        const scheduleCloseAvatar = () => {
            if (avatarCloseTimer) clearTimeout(avatarCloseTimer);
            avatarCloseTimer = setTimeout(() => {
                dropdown.hidden = true;
            }, 180);
        };

        const hoverTarget = wrap || avatarBtn;
        hoverTarget.addEventListener('mouseenter', openAvatarDrop);
        hoverTarget.addEventListener('mouseleave', scheduleCloseAvatar);
        dropdown.addEventListener('mouseenter', openAvatarDrop);
        dropdown.addEventListener('mouseleave', scheduleCloseAvatar);

        avatarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (dropdown.hidden) {
                openAvatarDrop();
                syncThemePills();
            } else {
                dropdown.hidden = true;
            }
        });

        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target) && !avatarBtn.contains(e.target)) {
                dropdown.hidden = true;
            }
        });
    }

    if (copyUidBtn) {
        copyUidBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            let user = {};
            try { user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {}; } catch(e) {}
            const clientId = user.client_id || user.id || '2620109';
            if (clientId && clientId !== '----') {
                navigator.clipboard.writeText(clientId);
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Copied!', message: `Client ID (${clientId}) copied to clipboard.` });
                }
            } else {
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'warning', title: 'Not Available', message: 'Client ID is not available yet.' });
                }
            }
        });
    }

    // Theme Pills Switcher inside dropdown
    document.querySelectorAll('.kv-theme-pill-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const mode = btn.getAttribute('data-theme-mode');
            if (typeof window.setKryvexTheme === 'function') {
                window.setKryvexTheme(mode === 'light' ? 'light' : 'dark');
            }
            syncThemePills();
        });
    });
    syncThemePills();

    window.addEventListener('kryvexThemeChanged', () => {
        syncThemePills();
    });

    if (createAccBtn) {
        createAccBtn.addEventListener('click', () => {
            if (dropdown) dropdown.hidden = true;
            const modal = document.getElementById('createAccountModalBackdrop');
            if (modal) {
                modal.hidden = false;
                modal.classList.add('is-open');
                modal.style.display = 'flex';
            } else {
                window.location.href = '/wallet?tab=trading';
            }
        });
    }
}

function setupLogout() {
    const logoutBtns = [document.getElementById('logoutButton'), document.getElementById('dropdownLogoutBtn')].filter(Boolean);
    if (logoutBtns.length === 0) return;

    logoutBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            openConfirm(
                'Logout?',
                'Are you sure you want to logout from Kryvex?',
                async () => {
                    const accessToken = localStorage.getItem('kryvex_access_token');
                    const refreshToken = localStorage.getItem('kryvex_refresh_token');

                    try {
                        if (accessToken) {
                            await fetch('/api/auth/logout', {
                                method: 'POST',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    Authorization: `Bearer ${accessToken}`,
                                },
                                body: JSON.stringify({ refresh_token: refreshToken }),
                            });
                        }
                    } catch (error) {}

                    localStorage.removeItem('kryvex_access_token');
                    localStorage.removeItem('kryvex_refresh_token');
                    localStorage.removeItem('kryvex_user');
                    localStorage.removeItem('kryvex_last_activity');
                    localStorage.removeItem('kryvex_trading_accounts_cache');
                    localStorage.removeItem('kryvex_cached_trading_accounts');
                    localStorage.removeItem('kryvex_active_account_uid');
                    localStorage.removeItem('kryvex_wallet_data');
                    localStorage.removeItem('kryvex_saved_bank_details');
                    try { window.__cachedTradingAccounts = null; } catch(e) {}
                    window.location.href = '/login';
                },
                'Logout'
            );
        });
    });
}

function setupSessionTimeout() {
    const token = localStorage.getItem('kryvex_access_token');
    if (!token) return;

    const oneHour = 60 * 60 * 1000; // 1 hour in milliseconds
   // const oneHour = 60 * 1000; // 1 minute in milliseconds
   // const oneHour = 10 * 1000; // 10 seconds in milliseconds
    const lastActivity = localStorage.getItem('kryvex_last_activity');
    const now = Date.now();

    if (lastActivity) {
        const timePassed = now - parseInt(lastActivity, 10);
        if (timePassed > oneHour) {
            localStorage.removeItem('kryvex_access_token');
            localStorage.removeItem('kryvex_refresh_token');
            localStorage.removeItem('kryvex_user');
            localStorage.removeItem('kryvex_last_activity');

            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
            return;
        }
    }

    localStorage.setItem('kryvex_last_activity', String(now));

    let lastUpdate = now;
    const updateActivity = () => {
        const current = Date.now();
        if (current - lastUpdate > 15000) {
            localStorage.setItem('kryvex_last_activity', String(current));
            lastUpdate = current;
        }
    };

    window.addEventListener('click', updateActivity);
    window.addEventListener('keypress', updateActivity);
    window.addEventListener('mousemove', updateActivity);
    window.addEventListener('scroll', updateActivity);
}

function setupGlobalToasts() {
    try {
        const pending = sessionStorage.getItem('kryvex_pending_toast');
        if (pending) {
            sessionStorage.removeItem('kryvex_pending_toast');
            const data = JSON.parse(pending);
            setTimeout(() => {
                if (typeof window.showToast === 'function') {
                    window.showToast(data);
                }
            }, 250);
        }
    } catch (e) {}

    document.querySelectorAll('.app-toast').forEach((toast) => {
        const type = toast.classList.contains('success') ? 'success' : 'error';
        const title = toast.querySelector('strong')?.textContent || '';
        const message = toast.querySelector('p')?.textContent || '';

        if (typeof window.showToast === 'function') {
            window.showToast({ type, title, message });
        }
        toast.remove();
    });
}

let confirmCallback = null;

function openConfirm(title, message, callback, buttonText = 'Confirm') {
    const modal = document.getElementById('confirmModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const confirmBtn = document.getElementById('confirmBtn');

    if (!modal || !modalTitle || !modalMessage || !confirmBtn) {
        if (window.confirm(message || title)) callback?.();
        return;
    }

    modalTitle.innerText = title;
    modalMessage.innerText = message;
    confirmBtn.innerText = buttonText;
    modal.classList.remove('hidden');
    confirmCallback = callback;
}

function setupConfirmModal() {
    const modal = document.getElementById('confirmModal');
    const cancelBtn = document.getElementById('cancelBtn');
    const confirmBtn = document.getElementById('confirmBtn');

    if (!modal || !cancelBtn || !confirmBtn) return;

    const close = () => {
        modal.classList.add('hidden');
        confirmCallback = null;
    };

    cancelBtn.addEventListener('click', close);

    modal.addEventListener('click', (event) => {
        if (event.target === modal) close();
    });

    confirmBtn.addEventListener('click', () => {
        const callback = confirmCallback;
        close();
        callback?.();
    });
}

// ===== KRYVEX ACCOUNT STATUS MONITOR =====
// This monitor keeps every user-side page aware of admin account actions.
// It shows suspension/block warning banners, updates countdown every second,
// masks balances after final restriction starts, and disables wallet/trading actions.
(function () {
    let latestAccountStatus = null;
    let statusCheckTimer = null;
    let maskTimer = null;
    let accountBannerTimer = null;

    function isJwt(value) {
        return typeof value === 'string' && value.split('.').length === 3;
    }

    function getAuthToken() {
        const directKeys = [
            'kryvex_access_token',
            'kryvex_token',
            'kryvex_auth_token',
            'auth_token',
            'access_token',
            'token',
            'jwt',
            'jwt_token',
        ];

        for (const key of directKeys) {
            const value = localStorage.getItem(key) || sessionStorage.getItem(key);

            if (isJwt(value)) {
                return value;
            }
        }

        // Some auth code stores token inside a JSON object; search those too.
        for (let index = 0; index < localStorage.length; index += 1) {
            const key = localStorage.key(index);
            const value = localStorage.getItem(key);

            if (isJwt(value)) {
                return value;
            }

            try {
                const parsed = JSON.parse(value);
                const possibleToken =
                    parsed?.token ||
                    parsed?.access_token ||
                    parsed?.auth_token ||
                    parsed?.jwt ||
                    parsed?.data?.token ||
                    parsed?.data?.access_token;

                if (isJwt(possibleToken)) {
                    return possibleToken;
                }
            } catch (error) {
                // Ignore non-JSON localStorage values.
            }
        }

        return '';
    }

    function formatKryvexDateTime(value) {
        if (!value) return '-';

        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
        });
    }

    function getSecondsUntil(dateValue) {
        if (!dateValue) {
            return 0;
        }

        const targetTime = new Date(dateValue).getTime();

        if (Number.isNaN(targetTime)) {
            return 0;
        }

        return Math.max(0, Math.floor((targetTime - Date.now()) / 1000));
    }

    function formatKryvexDuration(totalSeconds) {
        const safeSeconds = Math.max(0, Number(totalSeconds || 0));
        const days = Math.floor(safeSeconds / 86400);
        const hours = Math.floor((safeSeconds % 86400) / 3600);
        const minutes = Math.floor((safeSeconds % 3600) / 60);
        const seconds = Math.floor(safeSeconds % 60);

        if (days > 0) return `${days}d ${hours}h ${minutes}m`;
        if (hours > 0) return `${hours}h ${minutes}m ${seconds}s`;
        if (minutes > 0) return `${minutes}m ${seconds}s`;
        return `${seconds}s`;
    }

    function formatKryvexDurationFromMinutes(minutes) {
        const safeMinutes = Number(minutes || 0);

        if (safeMinutes >= 1440) {
            const days = Math.round(safeMinutes / 1440);
            return `${days} day${days > 1 ? 's' : ''}`;
        }

        if (safeMinutes >= 60) {
            const hours = Math.round(safeMinutes / 60);
            return `${hours} hour${hours > 1 ? 's' : ''}`;
        }

        return `${safeMinutes} minutes`;
    }

    function ensureBanner() {
        let banner = document.getElementById('kryvexAccountStatusBanner');

        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'kryvexAccountStatusBanner';
            document.body.appendChild(banner);
        }

        return banner;
    }

    function updateNavbarRestrictionLabel(status) {
        const nameText = document.getElementById('navUserNameText');
        const initial = document.getElementById('navUserInitial');
        const profileButton = document.getElementById('navUserProfileButton');

        if (!nameText && !initial && !profileButton) {
            return;
        }

        const state = status?.state || 'active';
        const suspendStates = ['suspension_scheduled', 'suspended'];
        const blockStates = ['block_scheduled', 'blocked'];

        profileButton?.classList.remove('account-suspended', 'account-blocked');
        initial?.classList.remove('account-suspended', 'account-blocked');

        if (suspendStates.includes(state)) {
            if (nameText) nameText.textContent = 'Suspended';
            if (initial) {
                initial.textContent = '!';
                initial.classList.add('account-suspended');
            }
            profileButton?.classList.add('account-suspended');
            return;
        }

        if (blockStates.includes(state)) {
            if (nameText) nameText.textContent = 'Blocked';
            if (initial) {
                initial.textContent = '×';
                initial.classList.add('account-blocked');
            }
            profileButton?.classList.add('account-blocked');
            return;
        }

        if (state === 'deletion_pending') {
            if (nameText) nameText.textContent = 'Pending Deletion';
            if (initial) {
                initial.textContent = '⚠️';
                initial.classList.add('account-blocked');
            }
            profileButton?.classList.add('account-blocked');
            return;
        }

        // Active state restores the normal profile name from localStorage.
        if (typeof setNavbarUserName === 'function') {
            setNavbarUserName();
        }
    }

    function renderAccountBanner(status) {
        const banner = ensureBanner();
        const globalBox = document.getElementById('globalDeletionNoticeBox');
        const settingsBox = document.getElementById('settingsDeletionNoticeBox');

        if (!status || status.state === 'active') {
            banner.className = 'kryvex-account-banner hidden';
            banner.innerHTML = '';
            if (globalBox) globalBox.style.display = 'none';
            updateNavbarRestrictionLabel(status);
            return;
        }

        if (status.state === 'deleted') {
            handleAccountDeleted(status.message);
            return;
        }

        if (status.state === 'deletion_pending') {
            // Show global amber notice box on all non-settings pages (Futures, Wallet, etc.)
            if (globalBox) {
                if (settingsBox) {
                    globalBox.style.display = 'none';
                } else {
                    globalBox.style.display = 'block';
                    const noticeText = document.getElementById('globalDeletionNoticeText');
                    if (noticeText) {
                        noticeText.textContent = status.reason 
                            ? `Your account deletion request is under review (Reason: "${status.reason}"). During this time, your account is in read-only mode and trading/wallet actions are disabled.`
                            : 'Your request to delete this account is pending administrator approval. During this time, your account is in read-only mode and trading/wallet actions are disabled.';
                    }
                    const cancelBtn = document.getElementById('btnCancelDeletionGlobal');
                    if (cancelBtn && !cancelBtn.dataset.bound) {
                        cancelBtn.dataset.bound = 'true';
                        cancelBtn.addEventListener('click', cancelAccountDeletion);
                    }
                }
            }
            // Keep default floating banner hidden since the amber banner handles it
            banner.className = 'kryvex-account-banner hidden';
            banner.innerHTML = '';
            updateNavbarRestrictionLabel(status);
            return;
        }

        // For non-deletion states, hide global deletion banner
        if (globalBox) globalBox.style.display = 'none';

        if (status.state === 'suspension_scheduled') {
            banner.className = 'kryvex-account-banner warning';
            banner.innerHTML = `
                <div class="kryvex-account-banner-icon">!</div>
                <div class="kryvex-account-banner-content">
                    <div class="kryvex-account-banner-top">
                        <strong>Account suspension scheduled</strong>
                        <span class="kryvex-account-pill warning-pill">
                            Grace time: <span id="kryvexGraceCountdown">${formatKryvexDuration(getSecondsUntil(status.effective_at))}</span>
                        </span>
                    </div>

                    <p class="kryvex-account-reason">${status.reason || 'No reason provided.'}</p>

                    <div class="kryvex-account-banner-grid">
                        <div>
                            <small>Suspension starts</small>
                            <b>${formatKryvexDateTime(status.effective_at)}</b>
                        </div>
                        <div>
                            <small>Suspension duration</small>
                            <b>${formatKryvexDurationFromMinutes(status.duration_minutes)}</b>
                        </div>
                        <div>
                            <small>Access returns</small>
                            <b>${formatKryvexDateTime(status.until)}</b>
                        </div>
                    </div>

                    <div class="kryvex-account-advice">
                        Close your open trades before grace time ends. After that, wallet and trading actions will be blocked.
                    </div>
                </div>
            `;
            updateNavbarRestrictionLabel(status);
            return;
        }

        if (status.state === 'block_scheduled') {
            banner.className = 'kryvex-account-banner danger';
            banner.innerHTML = `
                <div class="kryvex-account-banner-icon">×</div>
                <div class="kryvex-account-banner-content">
                    <div class="kryvex-account-banner-top">
                        <strong>Account block scheduled</strong>
                        <span class="kryvex-account-pill danger-pill">
                            Grace time: <span id="kryvexBlockGraceCountdown">${formatKryvexDuration(getSecondsUntil(status.effective_at))}</span>
                        </span>
                    </div>

                    <p class="kryvex-account-reason">${status.reason || 'Your account block has been scheduled by admin.'}</p>

                    <div class="kryvex-account-banner-grid two">
                        <div>
                            <small>Block starts</small>
                            <b>${formatKryvexDateTime(status.effective_at)}</b>
                        </div>
                        <div>
                            <small>After block starts</small>
                            <b>No account access</b>
                        </div>
                    </div>

                    <div class="kryvex-account-advice">
                        Make final account changes during grace time. After it ends, wallet, trading and account actions will be blocked until admin activates you again.
                    </div>
                </div>
            `;
            updateNavbarRestrictionLabel(status);
            return;
        }

        if (status.state === 'suspended') {
            banner.className = 'kryvex-account-banner danger';
            banner.innerHTML = `
                <div class="kryvex-account-banner-icon">!</div>
                <div class="kryvex-account-banner-content">
                    <div class="kryvex-account-banner-top">
                        <strong>Account suspended</strong>
                        <span class="kryvex-account-pill danger-pill">
                            Remaining: <span id="kryvexSuspendedCountdown">${formatKryvexDuration(getSecondsUntil(status.until))}</span>
                        </span>
                    </div>

                    <p class="kryvex-account-reason">${status.reason || 'Your account has been suspended.'}</p>

                    <div class="kryvex-account-banner-grid">
                        <div>
                            <small>Suspended from</small>
                            <b>${formatKryvexDateTime(status.effective_at)}</b>
                        </div>
                        <div>
                            <small>Suspension duration</small>
                            <b>${formatKryvexDurationFromMinutes(status.duration_minutes)}</b>
                        </div>
                        <div>
                            <small>Access returns</small>
                            <b>${formatKryvexDateTime(status.until)}</b>
                        </div>
                    </div>

                    <div class="kryvex-account-advice">
                        Trading, wallet and order actions are disabled until suspension ends.
                    </div>
                </div>
            `;
            updateNavbarRestrictionLabel(status);
            return;
        }

        if (status.state === 'blocked') {
            banner.className = 'kryvex-account-banner danger';
            banner.innerHTML = `
                <div class="kryvex-account-banner-icon">×</div>
                <div class="kryvex-account-banner-content">
                    <div class="kryvex-account-banner-top">
                        <strong>Account blocked</strong>
                        <span class="kryvex-account-pill danger-pill">No access</span>
                    </div>
                    <p class="kryvex-account-reason">${status.reason || 'Your account has been blocked by admin.'}</p>
                    <div class="kryvex-account-banner-grid two">
                        <div>
                            <small>Blocked from</small>
                            <b>${formatKryvexDateTime(status.effective_at)}</b>
                        </div>
                        <div>
                            <small>Access returns</small>
                            <b>After admin activation</b>
                        </div>
                    </div>
                    <div class="kryvex-account-advice">Trading, wallet and order actions are disabled.</div>
                </div>
            `;
            updateNavbarRestrictionLabel(status);
        }
    }

    function updateAccountBannerCountdown() {
        if (!latestAccountStatus) {
            return;
        }

        const graceEl = document.getElementById('kryvexGraceCountdown');
        const blockGraceEl = document.getElementById('kryvexBlockGraceCountdown');
        const suspendedEl = document.getElementById('kryvexSuspendedCountdown');

        if (latestAccountStatus.state === 'suspension_scheduled' && graceEl) {
            graceEl.textContent = formatKryvexDuration(getSecondsUntil(latestAccountStatus.effective_at));
        }

        if (latestAccountStatus.state === 'block_scheduled' && blockGraceEl) {
            blockGraceEl.textContent = formatKryvexDuration(getSecondsUntil(latestAccountStatus.effective_at));
        }

        if (latestAccountStatus.state === 'suspended' && suspendedEl) {
            suspendedEl.textContent = formatKryvexDuration(getSecondsUntil(latestAccountStatus.until));
        }
    }

    function startAccountBannerLiveClock() {
        if (accountBannerTimer) {
            clearInterval(accountBannerTimer);
        }

        updateAccountBannerCountdown();

        // Frontend countdown updates every second. API polling still runs every 10 seconds for fresh status changes.
        accountBannerTimer = setInterval(updateAccountBannerCountdown, 1000);
    }

    function maskBalancesAndDisableActions(status) {
        const blocked = status && ['suspended', 'blocked', 'deletion_pending'].includes(status.state);
        document.body.classList.toggle('kryvex-account-restricted', blocked);

        if (!blocked) {
            document.querySelectorAll('.kryvex-disabled-action').forEach((element) => {
                element.disabled = false;
                element.classList.remove('kryvex-disabled-action');
                element.removeAttribute('title');
            });
            return;
        }

        // Hide balances only after final suspension/block starts. Grace time keeps balances visible.
        const sensitiveSelectors = [
            '[data-sensitive-balance]',
            '#demoAvailable',
            '#demoLocked',
            '#pendingWithdrawalBalance',
            '#minimumReserve',
            '#maxWithdrawAmount',
            '#orderWalletAvailable',
            '#orderWalletLocked',
            '#orderLivePnl',
            '#orderEstimatedEquity',
            '#optionsAvailableBalance',
            '#optionsLockedBalance',
        ];

        document.querySelectorAll(sensitiveSelectors.join(',')).forEach((element) => {
            element.textContent = '--';
        });

        // Disable all wallet/trading actions on suspended/blocked/deletion_pending state.
        const actionSelectors = [
            '[data-account-action]',
            '#placeDemoOrderButton',
            '#optionPlaceOrderButton',
            '#closeAllPositionsButton',
            '#optionsCloseAllButton',
            '#walletModalSubmit',
            '#demoCreditForm button[type="submit"]',
            '#withdrawForm button[type="submit"]',
            '[data-wallet-modal]',
            '[data-wallet-action]',
        ];

        document.querySelectorAll(actionSelectors.join(',')).forEach((element) => {
            element.disabled = true;
            element.classList.add('kryvex-disabled-action');
            element.setAttribute(
                'title',
                status.state === 'deletion_pending'
                    ? 'Account deletion request pending review'
                    : (status.state === 'blocked' ? 'Account blocked by admin' : 'Account suspended by admin')
            );
        });
    }

    function handleAccountDeleted(message) {
        const msg = message || 'Your account deletion request has been approved by administration. Your account has been permanently closed.';

        const keysToRemove = [
            'kryvex_access_token',
            'kryvex_refresh_token',
            'kryvex_token',
            'kryvex_auth_token',
            'auth_token',
            'access_token',
            'token',
            'jwt',
            'jwt_token',
            'kryvex_user',
            'kryvex_account_status'
        ];

        for (const k of keysToRemove) {
            try {
                localStorage.removeItem(k);
                sessionStorage.removeItem(k);
            } catch(e) {}
        }

        try {
            localStorage.setItem('kryvex_account_deleted_message', msg);
            sessionStorage.setItem('kryvex_account_deleted_message', msg);
        } catch(e) {}

        // Prevent infinite loops if already on login page
        if (!window.location.pathname.includes('/login')) {
            alert(msg);
            window.location.href = '/login?account_deleted=1';
        }
    }
    window.handleAccountDeleted = handleAccountDeleted;

    // Global fetch interception for immediate logout if account is marked deleted by admin
    if (typeof window !== 'undefined' && window.fetch) {
        const _origFetch = window.fetch;
        window.fetch = async function (...args) {
            const res = await _origFetch.apply(this, args);
            if (res.status === 401 || res.status === 403) {
                try {
                    const clone = res.clone();
                    const body = await clone.json();
                    if (body && (body.account_deleted || String(body.message || '').toLowerCase().includes('account has been deleted') || String(body.message || '').toLowerCase().includes('account has been closed'))) {
                        handleAccountDeleted(body.message);
                    }
                } catch(e) {}
            }
            return res;
        };
    }

    function hydrateStatusFromLocalStorage() {
        try {
            const rawUser = localStorage.getItem('kryvex_user');
            if (rawUser) {
                const u = JSON.parse(rawUser);
                const delStatus = String(u.deletion_status || '').toLowerCase();
                const userStatus = String(u.status || '').toLowerCase();

                if (delStatus === 'approved' || userStatus === 'deleted') {
                    handleAccountDeleted();
                    return;
                }

                if (delStatus === 'pending') {
                    if (!latestAccountStatus || latestAccountStatus.state === 'active') {
                        latestAccountStatus = {
                            state: 'deletion_pending',
                            status: 'deletion_pending',
                            reason: u.deletion_reason || '',
                            effective_at: u.deletion_requested_at || ''
                        };
                        window.KRYVEX_ACCOUNT_STATUS = latestAccountStatus;
                        renderAccountBanner(latestAccountStatus);
                        maskBalancesAndDisableActions(latestAccountStatus);
                    }
                }
            }
        } catch (e) {}
    }

    async function checkAccountStatus() {
        hydrateStatusFromLocalStorage();

        const token = getAuthToken();

        if (!isJwt(token)) {
            return;
        }

        try {
            const response = await fetch('/api/account/status', {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${token}`,
                },
            });

            if (response.status === 401 || response.status === 403) {
                try {
                    const errData = await response.json();
                    if (errData && (errData.account_deleted || (errData.message && errData.message.toLowerCase().includes('deleted')))) {
                        handleAccountDeleted(errData.message);
                        return;
                    }
                } catch (e) {}
            }

            if (!response.ok) return;

            const data = await response.json();
            latestAccountStatus = data.account_status || null;
            window.KRYVEX_ACCOUNT_STATUS = latestAccountStatus;

            if (latestAccountStatus && (latestAccountStatus.state === 'deleted' || latestAccountStatus.status === 'deleted')) {
                handleAccountDeleted(latestAccountStatus.message);
                return;
            }

            // Keep localStorage in sync with server status
            try {
                const rawUser = localStorage.getItem('kryvex_user');
                if (rawUser) {
                    const u = JSON.parse(rawUser);
                    if (latestAccountStatus && latestAccountStatus.state === 'deletion_pending') {
                        u.deletion_status = 'pending';
                        u.deletion_reason = latestAccountStatus.reason || u.deletion_reason || '';
                        localStorage.setItem('kryvex_user', JSON.stringify(u));
                    } else if (u.deletion_status === 'pending' && latestAccountStatus && latestAccountStatus.state !== 'deletion_pending') {
                        u.deletion_status = latestAccountStatus.state;
                        localStorage.setItem('kryvex_user', JSON.stringify(u));
                    }
                }
            } catch (e) {}

            renderAccountBanner(latestAccountStatus);
            maskBalancesAndDisableActions(latestAccountStatus);
            startAccountBannerLiveClock();
        } catch (error) {
            console.warn('Account status check failed:', error);
        }
    }

    async function cancelAccountDeletion() {
        if (!confirm('Are you sure you want to cancel your account deletion request and restore full account access?')) {
            return;
        }

        const token = getAuthToken();
        if (!token) {
            alert('Please login to perform this action.');
            return;
        }

        try {
            const response = await fetch('/api/auth/cancel-delete-account', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Request Cancelled', message: data.message });
                } else {
                    alert(data.message || 'Account deletion request cancelled successfully.');
                }

                try {
                    let currentUser = JSON.parse(localStorage.getItem('kryvex_user') || '{}');
                    currentUser.deletion_status = 'none';
                    currentUser.deletion_reason = null;
                    currentUser.deletion_requested_at = null;
                    localStorage.setItem('kryvex_user', JSON.stringify(currentUser));
                } catch(e) {}

                window.location.reload();
            } else {
                alert(data.message || 'Failed to cancel deletion request.');
            }
        } catch (e) {
            alert('Network error while cancelling deletion request.');
        }
    }

    function showDeletionRestrictedActionModal(actionName = 'This action') {
        const modal = document.getElementById('modalDeletionActionRestricted');
        const msgEl = document.getElementById('modalDeletionRestrictedMessage');
        if (modal) {
            if (msgEl) {
                msgEl.textContent = `You have submitted an account deletion request which is currently pending review by administrator. ${actionName} and other trading/wallet actions are disabled while your request is pending.`;
            }
            modal.style.display = 'flex';
        } else {
            alert('Account deletion request is pending review. Trading, deposits, and withdrawals are disabled.');
        }
    }

    function hideDeletionRestrictedActionModal() {
        const modal = document.getElementById('modalDeletionActionRestricted');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    const RESTRICTED_ACTIONS_SELECTOR = [
        '[data-action-deposit]',
        '[data-deposit]',
        '#topBannerDepositBtn',
        '#btnStepDepositSwitchLive',
        '.open-deposit-modal',
        '#btnDeposit',
        '#openDepositBtn',
        'a[href*="/wallet/deposit"]',
        '[data-action-withdraw]',
        '[data-withdraw]',
        '#topBannerWithdrawBtn',
        '.withdraw-submit-btn',
        '#btnWithdraw',
        '#openWithdrawBtn',
        'a[href*="/wallet/withdraw"]',
        '#withdrawForm button[type="submit"]',
        '#placeFuturesOrderBtn',
        '#placeLiveOrderBtn',
        '#placeDemoOrderBtn',
        '#placeDemoOrderButton',
        '#optionPlaceOrderButton',
        '#spotPlaceOrderBtn',
        '#placeOrderBtn',
        '.order-place-submit',
        '.btn-place-order',
        '#closeAllPositionsButton',
        '#optionsCloseAllButton',
        '.position-close-btn',
        'form#futuresOrderPreviewForm button[type="submit"]',
        '#futuresOrderPreviewForm button'
    ].join(',');

    document.addEventListener('click', (event) => {
        if (!latestAccountStatus || latestAccountStatus.state !== 'deletion_pending') {
            return;
        }

        const target = event.target;
        if (!target) return;

        // Allow interacting with the modal buttons and cancel buttons
        if (target.closest('#btnCancelDeletionGlobal') || 
            target.closest('#btnCancelDeletionFromActionModal') || 
            target.closest('#btnCloseDeletionRestrictedModal') || 
            target.closest('#btnCancelDeletionTop') ||
            target.closest('#btnCancelDeletionInsideModal') ||
            target.closest('#btnCancelDeletionInRow')) {
            return;
        }

        // Close modal if clicking outside dialog content
        const modal = document.getElementById('modalDeletionActionRestricted');
        if (modal && target === modal) {
            hideDeletionRestrictedActionModal();
            return;
        }

        const actionEl = target.closest(RESTRICTED_ACTIONS_SELECTOR);
        if (actionEl) {
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            let actionName = 'This action';
            const text = (actionEl.textContent || actionEl.getAttribute('title') || actionEl.id || '').toLowerCase();
            if (text.includes('deposit')) actionName = 'Deposits';
            else if (text.includes('withdraw')) actionName = 'Withdrawals';
            else if (text.includes('order') || text.includes('buy') || text.includes('sell') || text.includes('trade') || text.includes('position')) actionName = 'Trading actions';

            showDeletionRestrictedActionModal(actionName);
        }
    }, true);

    document.addEventListener('submit', (event) => {
        if (!latestAccountStatus || latestAccountStatus.state !== 'deletion_pending') {
            return;
        }

        const form = event.target;
        if (form && (form.id === 'formRequestDeleteAccount' || form.id === 'formCloseAccount')) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        showDeletionRestrictedActionModal('Transactions and orders');
    }, true);

    function initAccountStatusMonitor() {
        document.getElementById('btnCancelDeletionGlobal')?.addEventListener('click', cancelAccountDeletion);
        document.getElementById('btnCancelDeletionFromActionModal')?.addEventListener('click', cancelAccountDeletion);
        document.getElementById('btnCloseDeletionRestrictedModal')?.addEventListener('click', hideDeletionRestrictedActionModal);

        hydrateStatusFromLocalStorage();
        checkAccountStatus();

        if (statusCheckTimer) clearInterval(statusCheckTimer);
        statusCheckTimer = setInterval(checkAccountStatus, 10000);

        // Some page scripts refresh balances every few seconds; keep masked state stable after final restriction starts.
        if (maskTimer) clearInterval(maskTimer);
        maskTimer = setInterval(() => {
            if (latestAccountStatus) {
                maskBalancesAndDisableActions(latestAccountStatus);
                updateNavbarRestrictionLabel(latestAccountStatus);
            }
        }, 1000);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccountStatusMonitor);
    } else {
        initAccountStatusMonitor();
    }
})();

// ===== KRYVEX MAINTENANCE MODE MONITOR =====
// Purpose:
// - Admin can enable maintenance mode from settings.
// - Users can still browse pages, but login/register/wallet/trading actions are blocked.
// - First popup appears 5 seconds after page visit; dismissed popup reappears when user tries a blocked action.
(function () {
    const nativeFetch = window.fetch.bind(window);
    let maintenanceState = {
        maintenance_mode: false,
        message: 'Kryvex is currently under maintenance.',
    };
    let maintenanceDismissed = false;
    let maintenanceStatusLoaded = false;

    function getMaintenanceMessage() {
        return maintenanceState?.message || 'Kryvex is currently under maintenance. Please try again later.';
    }

    function ensureMaintenanceModal() {
        let modal = document.getElementById('kryvexMaintenanceModal');

        if (modal) {
            return modal;
        }

        modal = document.createElement('div');
        modal.id = 'kryvexMaintenanceModal';
        modal.className = 'kryvex-maintenance-modal';
        modal.setAttribute('aria-hidden', 'true');

        modal.innerHTML = `
            <div class="kryvex-maintenance-backdrop"></div>
            <div class="kryvex-maintenance-card" role="dialog" aria-modal="true" aria-labelledby="kryvexMaintenanceTitle">
                <button type="button" class="kryvex-maintenance-close" aria-label="Close maintenance message">×</button>
                <div class="kryvex-maintenance-icon">!</div>
                <div>
                    <p class="kryvex-maintenance-eyebrow">System notice</p>
                    <h2 id="kryvexMaintenanceTitle">Maintenance mode is active</h2>
                    <p class="kryvex-maintenance-text"></p>
                    <div class="kryvex-maintenance-help">
                        You can browse pages in view-only mode. Login, wallet, buy/sell, close trade and password actions are disabled until admin turns maintenance off.
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        modal.querySelector('.kryvex-maintenance-close')?.addEventListener('click', () => {
            maintenanceDismissed = true;
            hideMaintenanceModal();
        });

        return modal;
    }

    function showMaintenanceModal(force = false) {
        if (!maintenanceState?.maintenance_mode) {
            return;
        }

        if (maintenanceDismissed && !force) {
            return;
        }

        const modal = ensureMaintenanceModal();
        const message = modal.querySelector('.kryvex-maintenance-text');

        if (message) {
            message.textContent = getMaintenanceMessage();
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
    }

    function hideMaintenanceModal() {
        const modal = document.getElementById('kryvexMaintenanceModal');

        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    async function refreshMaintenanceStatus() {
        try {
            const response = await nativeFetch('/api/platform/status', {
                method: 'GET',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return maintenanceState;
            }

            const data = await response.json();
            maintenanceState = {
                maintenance_mode: Boolean(data.maintenance_mode),
                message: data.message || maintenanceState.message,
            };
            maintenanceStatusLoaded = true;

            window.KRYVEX_MAINTENANCE_MODE = maintenanceState.maintenance_mode;

            if (!maintenanceState.maintenance_mode) {
                maintenanceDismissed = false;
                hideMaintenanceModal();
            }

            return maintenanceState;
        } catch (error) {
            console.warn('Maintenance status check failed:', error);
            return maintenanceState;
        }
    }

    function normalizeUrl(input) {
        if (typeof input === 'string') {
            return input;
        }

        if (input instanceof URL) {
            return input.toString();
        }

        if (input instanceof Request) {
            return input.url;
        }

        return String(input || '');
    }

    function normalizeMethod(input, init = {}) {
        return String(init.method || (input instanceof Request ? input.method : 'GET') || 'GET').toUpperCase();
    }

    function isMaintenanceProtectedRequest(url, method) {
        const absolute = new URL(url, window.location.origin);
        const path = absolute.pathname;

        if (path === '/api/platform/status') {
            return false;
        }

        if (path === '/api/auth/logout') {
            return false;
        }

        // User can view pages and GET market data, but write/action APIs are blocked.
        if (path.startsWith('/api/') && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
            return true;
        }

        return false;
    }

    function maintenanceResponse() {
        return new Response(JSON.stringify({
            success: false,
            maintenance_mode: true,
            message: getMaintenanceMessage(),
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' },
        });
    }

    function isMaintenanceActionClick(target) {
        const action = target.closest('[data-account-action], [data-wallet-modal], [data-wallet-action], #placeDemoOrderButton, #optionPlaceOrderButton, #closeAllPositionsButton, #optionsCloseAllButton');

        if (action) {
            return true;
        }

        const link = target.closest('a[href]');
        const href = link?.getAttribute('href') || '';

        return href.includes('/auth/google/redirect');
    }

    window.fetch = async function kryvexMaintenanceAwareFetch(input, init = {}) {
        const url = normalizeUrl(input);
        const method = normalizeMethod(input, init);

        if (isMaintenanceProtectedRequest(url, method)) {
            if (!maintenanceStatusLoaded) {
                await refreshMaintenanceStatus();
            }

            if (maintenanceState.maintenance_mode) {
                showMaintenanceModal(true);
                return maintenanceResponse();
            }
        }

        const response = await nativeFetch(input, init);

        // Backend middleware also returns 503. Show the same popup if the user bypassed client-side blocking.
        if (response.status === 503) {
            response.clone().json().then((data) => {
                if (data?.maintenance_mode) {
                    maintenanceState = {
                        maintenance_mode: true,
                        message: data.message || getMaintenanceMessage(),
                    };
                    showMaintenanceModal(true);
                }
            }).catch(() => {});
        }

        return response;
    };

    document.addEventListener('click', async (event) => {
        if (!isMaintenanceActionClick(event.target)) {
            return;
        }

        if (!maintenanceStatusLoaded) {
            await refreshMaintenanceStatus();
        }

        if (maintenanceState.maintenance_mode) {
            event.preventDefault();
            event.stopPropagation();
            showMaintenanceModal(true);
        }
    }, true);

    document.addEventListener('DOMContentLoaded', async () => {
        await refreshMaintenanceStatus();

        // First maintenance popup appears 5 seconds after user lands on the website.
        setTimeout(() => {
            showMaintenanceModal(false);
        }, 5000);

        // Keep checking so admin can turn maintenance off and site resumes automatically.
        setInterval(refreshMaintenanceStatus, 30000);
    });
})();


// ===== KRYVEX NAVBAR NOTIFICATIONS =====
function setupNavbarNotifications() {
    const button = document.getElementById('navNotificationButton');
    const dropdown = document.getElementById('navNotificationDropdown');
    const list = document.getElementById('navNotificationList');
    const count = document.getElementById('navNotificationCount');
    const markRead = document.getElementById('markNotificationsRead');

    if (!button || !dropdown || !list) {
        return;
    }

    const token = localStorage.getItem('kryvex_access_token');

    if (!token) {
        return;
    }

    const api = async (url, options = {}) => {
        const response = await fetch(url, {
            ...options,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${token}`,
                ...(options.headers || {}),
            },
        });

        return response.json();
    };

    const renderNotifications = (items = [], unread = 0) => {
        if (count) {
            count.hidden = unread <= 0;
            count.textContent = unread > 9 ? '9+' : String(unread);
        }

        if (!items.length) {
            list.innerHTML = '<p class="kv-notification-empty">No notifications yet.</p>';
            return;
        }

        list.innerHTML = items.map((item) => {
            const unreadClass = item.read_at ? '' : ' unread';
            const type = item.type || 'update';
            const title = escapeNotificationHtml(item.title || 'Notification');
            const message = escapeNotificationHtml(item.message || '');
            const time = formatNotificationTime(item.created_at);

            return `
                <div class="kv-notification-item${unreadClass}">
                    <span class="kv-notification-type ${escapeNotificationHtml(type)}">${escapeNotificationHtml(type)}</span>
                    <strong>${title}</strong>
                    <p>${message}</p>
                    <small>${time}</small>
                </div>
            `;
        }).join('');
    };

    const loadNotifications = async () => {
        try {
            const result = await api('/api/auth/notifications');
            const data = result.data || {};
            renderNotifications(data.notifications || [], Number(data.unread_count || 0));
        } catch (error) {
            // Keep navbar usable even if notification API is temporarily unavailable.
        }
    };

    const backdrop = document.getElementById('navNotificationBackdrop');
    const closeBtn = document.getElementById('closeNotificationDrawer');

    const openDrawer = async () => {
        if (backdrop) {
            backdrop.hidden = false;
            backdrop.classList.add('is-open');
        }
        dropdown.hidden = false;
        void dropdown.offsetWidth;
        dropdown.classList.add('is-open');
        await loadNotifications();
    };

    const closeDrawer = () => {
        dropdown.classList.remove('is-open');
        if (backdrop) {
            backdrop.classList.remove('is-open');
        }
        setTimeout(() => {
            if (!dropdown.classList.contains('is-open')) {
                dropdown.hidden = true;
                if (backdrop) backdrop.hidden = true;
            }
        }, 300);
    };

    button.addEventListener('click', (event) => {
        event.stopPropagation();
        if (dropdown.classList.contains('is-open')) {
            closeDrawer();
        } else {
            openDrawer();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            closeDrawer();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', (event) => {
            event.stopPropagation();
            closeDrawer();
        });
    }

    dropdown.addEventListener('click', (event) => event.stopPropagation());

    document.addEventListener('click', (event) => {
        if (dropdown.classList.contains('is-open') && !dropdown.contains(event.target) && !button.contains(event.target)) {
            closeDrawer();
        }
    });

    markRead?.addEventListener('click', async () => {
        try {
            await api('/api/auth/notifications/mark-read', { method: 'POST', body: JSON.stringify({}) });
            await loadNotifications();
        } catch (error) {
            // Ignore temporary read-state errors.
        }
    });

    loadNotifications();
    setInterval(loadNotifications, 30000);
}

function escapeNotificationHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatNotificationTime(value) {
    if (!value) return '';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString('en-IN', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
}

function setupBackNavigationBlock() {
    // Intentionally disabled: window.close() and about:blank redirect are
    // not supported on mobile browsers and caused the register page to appear
    // when navigating back to home on mobile devices.
}

function setupNavbarCurrencyToggle() {
    const isCurrentDemoActive = () => {
        const mode = (window.getActiveTradingMode ? window.getActiveTradingMode() : localStorage.getItem('kryvex_trading_mode')) || 'live';
        const activeUid = localStorage.getItem('kryvex_active_account_uid');
        let accounts = [];
        try {
            accounts = window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || localStorage.getItem('kryvex_trading_accounts_cache') || '[]');
        } catch (e) {}
        const currentAcc = accounts.find(a => String(a.account_uid) === String(activeUid));
        return currentAcc ? Boolean(currentAcc.account_type === 'demo' || currentAcc.is_demo || String(currentAcc.account_name || '').toLowerCase().startsWith('demo')) : (mode === 'demo');
    };

    const updateToggleUI = (cur) => {
        const isINR = cur === 'INR';
        const isLight = document.documentElement.getAttribute('data-theme') === 'light' || document.documentElement.classList.contains('theme-light');
        const activeBg = '#7c3aed';
        const activeShadow = '0 2px 8px rgba(124, 58, 237, 0.4)';
        const inactiveColor = isLight ? '#64748b' : 'rgba(255, 255, 255, 0.6)';

        const isDemo = isCurrentDemoActive();
        const activeUid = localStorage.getItem('kryvex_active_account_uid');
        let accounts = [];
        try {
            accounts = window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || localStorage.getItem('kryvex_trading_accounts_cache') || '[]');
        } catch (e) {}
        const currentAcc = accounts.find(a => String(a.account_uid) === String(activeUid));
        let user = {};
        try { user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {}; } catch (e) {}
        const isMt5 = (currentAcc && currentAcc.broker_type) ? (String(currentAcc.broker_type).toLowerCase() === 'mt5') : (String(user.broker_type || 'kryvex').toLowerCase() === 'mt5');

        const navToggle = document.getElementById('navCurrencyToggleWrap');
        if (navToggle) {
            if (isDemo || isMt5) {
                navToggle.classList.add('is-demo-hidden');
                navToggle.style.display = 'none';
            } else {
                navToggle.classList.remove('is-demo-hidden');
                navToggle.style.display = 'inline-flex';
            }
        }

        document.querySelectorAll('[data-currency-toggle="INR"]').forEach((btn) => {
            btn.classList.toggle('is-active', isINR);
            btn.style.background = isINR ? activeBg : 'transparent';
            btn.style.color = isINR ? '#ffffff' : inactiveColor;
            btn.style.fontWeight = isINR ? '700' : '600';
            btn.style.boxShadow = isINR ? activeShadow : 'none';
        });

        document.querySelectorAll('[data-currency-toggle="USDT"], [data-currency-toggle="USD"]').forEach((btn) => {
            btn.classList.toggle('is-active', !isINR);
            btn.style.background = !isINR ? activeBg : 'transparent';
            btn.style.color = !isINR ? '#ffffff' : inactiveColor;
            btn.style.fontWeight = !isINR ? '700' : '600';
            btn.style.boxShadow = !isINR ? activeShadow : 'none';
        });
    };

    window.updateNavbarCurrencyToggleVisibility = () => {
        const cur = (localStorage.getItem('kryvex_active_currency') === 'INR') ? 'INR' : 'USD';
        updateToggleUI(cur);
    };

    const currentCur = (localStorage.getItem('kryvex_active_currency') === 'INR') ? 'INR' : 'USD';
    updateToggleUI(currentCur);

    window.addEventListener('kryvexThemeChanged', () => {
        const cur = (localStorage.getItem('kryvex_active_currency') === 'INR') ? 'INR' : 'USD';
        updateToggleUI(cur);
    });

    window.addEventListener('kryvex_trading_mode_changed', () => {
        const cur = (localStorage.getItem('kryvex_active_currency') === 'INR') ? 'INR' : 'USD';
        updateToggleUI(cur);
    });
    window.addEventListener('tradingModeChanged', () => {
        const cur = (localStorage.getItem('kryvex_active_currency') === 'INR') ? 'INR' : 'USD';
        updateToggleUI(cur);
    });
    window.addEventListener('tradingAccountChanged', () => {
        const cur = (localStorage.getItem('kryvex_active_currency') === 'INR') ? 'INR' : 'USD';
        updateToggleUI(cur);
    });
    window.addEventListener('kryvex_account_changed', () => {
        const cur = (localStorage.getItem('kryvex_active_currency') === 'INR') ? 'INR' : 'USD';
        updateToggleUI(cur);
    });
    window.addEventListener('kryvex_wallet_updated', () => {
        const cur = (localStorage.getItem('kryvex_active_currency') === 'INR') ? 'INR' : 'USD';
        updateToggleUI(cur);
    });
    window.addEventListener('wallet_updated', () => {
        const cur = (localStorage.getItem('kryvex_active_currency') === 'INR') ? 'INR' : 'USD';
        updateToggleUI(cur);
    });

    const handleSwitch = (newCur) => {
        const normalized = (newCur === 'INR') ? 'INR' : 'USD';
        localStorage.setItem('kryvex_active_currency', normalized);
        updateToggleUI(normalized);
        window.dispatchEvent(new CustomEvent('kryvex_currency_changed', { detail: { currency: normalized } }));
    };

    document.querySelectorAll('[data-currency-toggle]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const cur = btn.getAttribute('data-currency-toggle');
            handleSwitch(cur);
        });
    });
}
window.getActiveCurrency = () => {
    const cur = localStorage.getItem('kryvex_active_currency');
    return (cur === 'INR') ? 'INR' : 'USD';
};

function setupNavbarTradingModeToggle() {
    const updateModeUI = (mode) => {
        const isLive = mode === 'live';
        const isLight = document.documentElement.getAttribute('data-theme') === 'light' || document.documentElement.classList.contains('theme-light');
        const inactiveColor = isLight ? '#64748b' : 'rgba(255, 255, 255, 0.6)';

        document.querySelectorAll('[data-mode-toggle="live"]').forEach((btn) => {
            btn.classList.toggle('is-active', isLive);
            btn.style.background = isLive ? '#10b981' : 'transparent';
            btn.style.color = isLive ? '#ffffff' : inactiveColor;
            btn.style.fontWeight = isLive ? '700' : '600';
            btn.style.boxShadow = isLive ? '0 2px 10px rgba(16, 185, 129, 0.4)' : 'none';
        });

        document.querySelectorAll('[data-mode-toggle="demo"]').forEach((btn) => {
            btn.classList.toggle('is-active', !isLive);
            btn.style.background = !isLive ? '#8b5cf6' : 'transparent';
            btn.style.color = !isLive ? '#ffffff' : inactiveColor;
            btn.style.fontWeight = !isLive ? '700' : '600';
            btn.style.boxShadow = !isLive ? '0 2px 10px rgba(139, 92, 246, 0.4)' : 'none';
        });
    };

    const currentMode = (localStorage.getItem('kryvex_trading_mode') === 'live') ? 'live' : 'demo';
    updateModeUI(currentMode);

    window.addEventListener('kryvexThemeChanged', () => {
        const mode = (localStorage.getItem('kryvex_trading_mode') === 'live') ? 'live' : 'demo';
        updateModeUI(mode);
    });

    const handleSwitch = (newMode, options = {}) => {
        const normalized = (newMode === 'live') ? 'live' : 'demo';
        localStorage.setItem('kryvex_trading_mode', normalized);

        let cached = [];
        try {
            cached = window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]') || [];
        } catch (e) {}

        if (options.accountUid) {
            const chosenAcc = cached.find(a => String(a.account_uid) === String(options.accountUid)) || null;
            localStorage.setItem('kryvex_active_account_uid', options.accountUid);
            window.dispatchEvent(new CustomEvent('tradingAccountChanged', { detail: { accountUid: options.accountUid, mode: normalized, account: chosenAcc } }));
        } else if (normalized === 'demo') {
            const demoAcc = cached.find(a => a.account_type === 'demo' || a.is_demo);
            if (demoAcc) {
                localStorage.setItem('kryvex_active_account_uid', demoAcc.account_uid);
                window.dispatchEvent(new CustomEvent('tradingAccountChanged', { detail: { accountUid: demoAcc.account_uid, mode: 'demo', account: demoAcc } }));
            }
        } else {
            const liveAcc = cached.find(a => a.account_type !== 'demo' && !a.is_demo && a.is_default) || cached.find(a => a.account_type !== 'demo' && !a.is_demo);
            if (liveAcc) {
                localStorage.setItem('kryvex_active_account_uid', liveAcc.account_uid);
                window.dispatchEvent(new CustomEvent('tradingAccountChanged', { detail: { accountUid: liveAcc.account_uid, mode: 'live', account: liveAcc } }));
            }
        }

        updateModeUI(normalized);
        window.dispatchEvent(new CustomEvent('kryvex_trading_mode_changed', { detail: { mode: normalized } }));
        window.dispatchEvent(new CustomEvent('tradingModeChanged', { detail: { mode: normalized } }));
        if (!options.silent && typeof window.showToast === 'function') {
            window.showToast({
                type: normalized === 'live' ? 'success' : 'info',
                title: normalized === 'live' ? 'Live Trading Active' : 'Demo Trading Active',
                message: normalized === 'live' ? 'Switched to Real Money trading account.' : 'Switched to Demo Paper trading account.'
            });
        }
    };

    document.querySelectorAll('[data-mode-toggle]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const mode = btn.getAttribute('data-mode-toggle');
            handleSwitch(mode);
        });
    });

    window.setTradingMode = handleSwitch;
}

window.getActiveTradingMode = () => {
    const mode = localStorage.getItem('kryvex_trading_mode');
    return (mode === 'live') ? 'live' : 'demo';
};

function setupNavbarFuturesAccountsDropdown() {
    const wrap = document.getElementById('navFuturesDropdownWrap');
    const dropdown = document.getElementById('navFuturesAccountsDropdown');
    const filterCheckbox = document.getElementById('navHideEmptyAccounts');
    const listContainer = document.getElementById('navFuturesAccountsList');
    const manageBtn = document.getElementById('navManageAccountsBtn');
    const triggerLink = document.getElementById('navFuturesLink');

    if (!wrap || !dropdown || !listContainer) return;

    let cachedAccounts = [];
    try {
        cachedAccounts = JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]');
    } catch (e) {
        cachedAccounts = [];
    }

    let hideEmpty = localStorage.getItem('kryvex_nav_hide_empty') === '1';
    if (filterCheckbox) {
        filterCheckbox.checked = hideEmpty;
        filterCheckbox.addEventListener('change', () => {
            hideEmpty = filterCheckbox.checked;
            localStorage.setItem('kryvex_nav_hide_empty', hideEmpty ? '1' : '0');
            renderAccounts(cachedAccounts);
        });
    }

    const formatNum = (num, minDec = 2, maxDec = 2) => {
        const val = Number(num || 0);
        if (isNaN(val)) return '0.00';
        return val.toLocaleString('en-US', {
            minimumFractionDigits: minDec,
            maximumFractionDigits: maxDec
        });
    };

    const escapeStr = (str) => {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    const renderAccounts = (accounts) => {
        const token = localStorage.getItem('kryvex_access_token');
        if (!token) {
            listContainer.innerHTML = `
                <div class="kv-accounts-drop-empty">
                    <div style="margin-bottom:8px;">Please login to view trading accounts</div>
                    <a href="/login" class="kv-btn kv-btn-primary" style="padding:4px 14px;font-size:12px;color:#fff!important;display:inline-block;">Login</a>
                </div>
            `;
            return;
        }

        if (!Array.isArray(accounts) || accounts.length === 0) {
            listContainer.innerHTML = `<div class="kv-accounts-drop-loading">Loading accounts...</div>`;
            return;
        }

        const liveAccounts = accounts.filter(a => a.account_type !== 'demo' && !a.is_demo);
        const demoAccount = accounts.find(a => a.account_type === 'demo' || a.is_demo);

        let activeUid = localStorage.getItem('kryvex_active_account_uid');
        let selectedAccount = accounts.find(a => a.account_uid === activeUid);

        if (!selectedAccount) {
            const isLivePref = (localStorage.getItem('kryvex_trading_mode') === 'live');
            selectedAccount = isLivePref
                ? (liveAccounts.find(a => a.is_default) || liveAccounts[0] || demoAccount)
                : (demoAccount || liveAccounts[0]);
            activeUid = selectedAccount ? selectedAccount.account_uid : null;
            if (activeUid) {
                localStorage.setItem('kryvex_active_account_uid', activeUid);
            }
        }

        const isSelectedDemo = selectedAccount ? (selectedAccount.is_demo || selectedAccount.account_type === 'demo') : false;
        const activeMode = isSelectedDemo ? 'demo' : 'live';
        try {
            localStorage.setItem('kryvex_trading_mode', activeMode);
            localStorage.setItem('kryvex_trading_accounts_env', activeMode);
        } catch (e) {}

        // Build list items (All Live accounts + EXACTLY ONE Demo account matching PrimeXBT layout)
        const items = [];

        // 1. All Live accounts (Main Account + Live Sub-accounts)
        liveAccounts.forEach(acc => {
            const accNum = String(acc.account_number || (1000000 + (Math.abs(acc.account_uid.split('').reduce((a, b) => { a = ((a << 5) - a) + b.charCodeAt(0); return a & a; }, 0)) % 9000000)));
            const realBal = Number((acc.balance > 0 ? acc.balance : 0) || acc.real_available_balance || acc.main_balance || acc.real_main_balance || 0);
            const realPnl = Number(acc.unrealized_pnl ?? acc.real_realized_pnl ?? 0);
            const isLiveActive = (activeMode === 'live') && (acc.account_uid === activeUid);
            const isLiveEmpty = (realBal === 0 && realPnl === 0);
            const brokerType = (acc.broker_type || 'kryvex').toLowerCase();
            const isCent = isCentAccount(acc);

            let balCur = 'USDT';
            let balDisplay = `${formatNum(realBal)} USDT`;
            let platformBadge = 'KRYVEX';
            if (brokerType === 'mt5') {
                if (isCent) {
                    balCur = 'USC';
                    balDisplay = formatUsc(realBal);
                    platformBadge = 'MT5 Cent';
                } else {
                    balCur = 'USD';
                    balDisplay = `$${formatNum(realBal)} USD`;
                    const planStr = (acc.plan_key && acc.plan_key !== 'basic') ? acc.plan_key.toUpperCase() : 'STANDARD';
                    platformBadge = `MT5 ${planStr}`;
                }
            }

            items.push({
                accountUid: acc.account_uid,
                accountName: acc.account_name,
                brokerType: brokerType,
                mode: 'live',
                code: `#L${accNum}`,
                isDemo: false,
                isCent: isCent,
                balance: realBal,
                balDisplay: balDisplay,
                balanceCurrency: balCur,
                platformBadge: platformBadge,
                approx: realBal,
                pnl: realPnl,
                pnlCurrency: balCur,
                isActive: isLiveActive,
                isEmpty: isLiveEmpty
            });
        });

        // 2. Dedicated Demo account
        if (demoAccount) {
            const accNum = String(demoAccount.account_number || (1000000 + (Math.abs(demoAccount.account_uid.split('').reduce((a, b) => { a = ((a << 5) - a) + b.charCodeAt(0); return a & a; }, 0)) % 9000000)));
            
            let demoBal = Number(demoAccount.main_balance || demoAccount.demo_main_balance || demoAccount.demo_available_balance || (demoAccount.balance > 0 ? demoAccount.balance : 0) || 0);
            if (demoBal <= 0) {
                try {
                    const wRaw = localStorage.getItem('kryvex_wallet_cache_' + demoAccount.account_uid) || localStorage.getItem('kryvex_wallet_cache');
                    if (wRaw) {
                        const parsed = JSON.parse(wRaw);
                        const wObj = parsed.wallet || parsed || {};
                        demoBal = Number(wObj.demo_main_balance ?? wObj.demo_available_balance ?? wObj.demo_crypto_balances?.USDT ?? 0);
                    }
                } catch (e) {}
            }
            if (demoBal <= 0 && window.futuresState && window.futuresState.wallet) {
                demoBal = Number(window.futuresState.wallet.demo_available_balance ?? window.futuresState.wallet.demo_main_balance ?? 0);
            }
            if (demoBal <= 0) {
                demoBal = 60000.0;
            }

            const demoPnl = Number(demoAccount.unrealized_pnl ?? demoAccount.demo_realized_pnl ?? 0);
            const isDemoActive = (activeMode === 'demo') && (demoAccount.account_uid === activeUid);
            const isDemoEmpty = (demoBal === 0 && demoPnl === 0);
            const brokerType = (demoAccount.broker_type || 'kryvex').toLowerCase();

            items.push({
                accountUid: demoAccount.account_uid,
                accountName: demoAccount.account_name || 'Demo Account',
                brokerType: brokerType,
                mode: 'demo',
                code: `#D${accNum}`,
                isDemo: true,
                isCent: false,
                balance: demoBal,
                balDisplay: `${formatNum(demoBal)} USDT`,
                balanceCurrency: 'USDT',
                platformBadge: brokerType === 'mt5' ? 'MT5 Demo' : 'DEMO',
                approx: demoBal,
                pnl: demoPnl,
                pnlCurrency: 'USDT',
                isActive: isDemoActive,
                isEmpty: isDemoEmpty
            });
        }

        const displayedItems = hideEmpty
            ? items.filter(it => !it.isEmpty || it.isActive)
            : items;

        if (displayedItems.length === 0) {
            listContainer.innerHTML = `<div class="kv-accounts-drop-empty">No accounts to display</div>`;
            return;
        }

        const kryvexItems = displayedItems.filter(it => it.brokerType === 'kryvex');
        const mt5Items = displayedItems.filter(it => it.brokerType === 'mt5');

        const renderSingleItemHtml = (it) => {
            const pnlClass = it.pnl > 0 ? 'is-profit' : (it.pnl < 0 ? 'is-loss' : '');
            const pnlPrefix = it.pnl > 0 ? '+' : '';
            const pnlFormatted = it.isCent
                ? formatUsc(it.pnl, { signed: true }).replace(/ USC$/, '')
                : (it.pnl === 0 ? '0' : `${pnlPrefix}${formatNum(it.pnl)}`);
            const badgeBg = it.brokerType === 'mt5' ? (it.isCent ? 'rgba(168,85,247,0.2)' : 'rgba(59,130,246,0.2)') : 'rgba(16,185,129,0.2)';
            const badgeColor = it.brokerType === 'mt5' ? (it.isCent ? '#c084fc' : '#60a5fa') : '#34d399';
            const badgeBorder = it.brokerType === 'mt5' ? (it.isCent ? 'rgba(168,85,247,0.4)' : 'rgba(59,130,246,0.4)') : 'rgba(16,185,129,0.4)';

            return `
                <div class="kv-accounts-drop-item ${it.isActive ? 'is-active' : ''}" data-account-uid="${escapeStr(it.accountUid)}" data-mode="${it.mode}">
                    <div class="kv-account-item-left">
                        <div class="kv-account-id-row">
                            <span class="kv-account-id-badge">${escapeStr(it.code)}</span>
                            <span class="kv-account-demo-badge" style="background:${badgeBg};color:${badgeColor};border:1px solid ${badgeBorder};">${escapeStr(it.platformBadge)}</span>
                        </div>
                        <div class="kv-account-pnl-text ${pnlClass}">
                            P/L ${pnlFormatted} ${it.pnlCurrency}
                        </div>
                    </div>
                    <div class="kv-account-item-right">
                        <div class="kv-account-balance-val">${escapeStr(it.balDisplay)}</div>
                    </div>
                </div>
            `;
        };

        let html = '';

        if (kryvexItems.length > 0) {
            html += `<div style="padding: 6px 12px 4px; font-size: 11px; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;">Kryvex Accounts</div>`;
            kryvexItems.forEach(it => {
                html += renderSingleItemHtml(it);
            });
        }

        if (mt5Items.length > 0) {
            html += `<div style="padding: 10px 12px 4px; font-size: 11px; font-weight: 700; color: #a855f7; text-transform: uppercase; letter-spacing: 0.5px; border-top: 1px solid rgba(255,255,255,0.08); margin-top: 6px;">MetaTrader 5 Accounts</div>`;
            mt5Items.forEach(it => {
                html += renderSingleItemHtml(it);
            });
        }

        listContainer.innerHTML = html;

        // Attach click listeners to account rows
        listContainer.querySelectorAll('.kv-accounts-drop-item').forEach(item => {
            item.addEventListener('click', (e) => {
                e.stopPropagation();
                const uid = item.getAttribute('data-account-uid');
                const targetMode = item.getAttribute('data-mode') || 'demo';
                if (!uid) return;

                // 1. Immediately visually close the dropdown
                dropdown.classList.add('is-closed');
                wrap.classList.add('is-closing');
                wrap.classList.remove('is-open');
                dropdown.style.display = 'none';

                const selectedAcc = cachedAccounts.find(a => a.account_uid === uid);
                const isDemo = selectedAcc ? (selectedAcc.is_demo || selectedAcc.account_type === 'demo') : (targetMode === 'demo');
                const actualMode = isDemo ? 'demo' : 'live';
                const brokerType = selectedAcc ? (selectedAcc.broker_type || 'kryvex').toLowerCase() : 'kryvex';

                // 2. Switch trading mode and active account
                localStorage.setItem('kryvex_active_account_uid', uid);
                localStorage.setItem('kryvex_trading_mode', actualMode);
                localStorage.setItem('kryvex_trading_accounts_env', actualMode);
                localStorage.setItem('kryvex_user_broker', brokerType);

                if (typeof window.setTradingMode === 'function') {
                    window.setTradingMode(actualMode, { silent: true, accountUid: uid });
                } else {
                    window.dispatchEvent(new CustomEvent('kryvex_trading_mode_changed', { detail: { mode: actualMode } }));
                }

                // 4. Save toast into sessionStorage so it shows after refresh
                try {
                    sessionStorage.setItem('kryvex_pending_toast', JSON.stringify({
                        type: 'success',
                        title: 'Account Selected',
                        message: `Switched to ${selectedAcc ? selectedAcc.account_name : 'Trading Account'} (${targetMode.toUpperCase()})`
                    }));
                } catch (err) {}

                // 5. Refresh / reload the page immediately
                window.location.reload();
            });
        });
    };

    const loadNavbarTradingAccounts = async (force = false) => {
        const token = localStorage.getItem('kryvex_access_token');
        if (!token) {
            renderAccounts([]);
            if (typeof window.updateNavbarBalanceDisplay === 'function') {
                window.updateNavbarBalanceDisplay();
            }
            return;
        }

        let user = {};
        try {
            user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
        } catch (e) {}
        const broker = (user.broker_type || 'kryvex').toLowerCase();

        try {
            const [accRes, wRes] = await Promise.all([
                fetch(`/api/trading-accounts?broker_type=all`, {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json',
                        'X-Broker-Type': 'all'
                    }
                }),
                fetch('/api/wallet', {
                    headers: {
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    }
                }).catch(() => null)
            ]);

            if (wRes && wRes.ok) {
                try {
                    const wJson = await wRes.json();
                    if (wJson && wJson.success && wJson.data && wJson.data.wallet) {
                        localStorage.setItem('kryvex_wallet_cache', JSON.stringify(wJson.data));
                        if (wJson.data.inr_rate || wJson.data.usdt_inr_rate) {
                            localStorage.setItem('kryvex_inr_state_cache', JSON.stringify({
                                usdtInrRate: Number(wJson.data.inr_rate || wJson.data.usdt_inr_rate || 95),
                                usdtInrBuyRate: Number(wJson.data.usdt_inr_buy_rate || 95),
                                usdtInrSellRate: Number(wJson.data.usdt_inr_sell_rate || 95)
                            }));
                        }
                    }
                } catch (e) {}
            }

            const json = await accRes.json();
            if (json.success && Array.isArray(json.data)) {
                cachedAccounts = json.data;
                window.__cachedTradingAccounts = cachedAccounts;
                localStorage.setItem('kryvex_cached_trading_accounts', JSON.stringify(cachedAccounts));
                renderAccounts(cachedAccounts);
                if (typeof window.updateNavbarCurrencyToggleVisibility === 'function') {
                    window.updateNavbarCurrencyToggleVisibility();
                }
                if (typeof window.updateNavbarBalanceDisplay === 'function') {
                    window.updateNavbarBalanceDisplay();
                }
            }
        } catch (err) {
            console.warn('Failed to load navbar trading accounts:', err);
        }
    };
    window.loadNavbarTradingAccounts = loadNavbarTradingAccounts;

    // Initial render with cached accounts for instant display
    if (cachedAccounts.length > 0) {
        renderAccounts(cachedAccounts);
        if (typeof window.updateNavbarCurrencyToggleVisibility === 'function') {
            window.updateNavbarCurrencyToggleVisibility();
        }
        if (typeof window.updateNavbarBalanceDisplay === 'function') {
            window.updateNavbarBalanceDisplay();
        }
    }
    loadNavbarTradingAccounts();

    // Hover listener on desktop: refresh accounts when hovering over Futures link
    let hoverThrottle = 0;
    wrap.addEventListener('mouseenter', () => {
        const now = Date.now();
        if (now - hoverThrottle > 6000) {
            hoverThrottle = now;
            loadNavbarTradingAccounts();
        } else {
            renderAccounts(cachedAccounts);
        }
    });

    wrap.addEventListener('mouseleave', () => {
        dropdown.classList.remove('is-closed');
        wrap.classList.remove('is-closing');
        dropdown.style.display = '';
    });

    // Mobile / touch support
    if (triggerLink) {
        triggerLink.addEventListener('click', (e) => {
            if (window.matchMedia('(hover: none)').matches || window.innerWidth <= 1024) {
                if (!wrap.classList.contains('is-open')) {
                    e.preventDefault();
                    wrap.classList.add('is-open');
                    loadNavbarTradingAccounts();
                }
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (!wrap.contains(e.target)) {
            wrap.classList.remove('is-open');
        }
    });

    // Manage accounts link -> Open Wallet Trading Accounts page
    if (manageBtn) {
        manageBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            wrap.classList.remove('is-open');
            if (window.location.pathname.startsWith('/wallet')) {
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('trading', true);
                    return;
                }
            }
            window.location.href = '/wallet?tab=trading';
        });
    }

    // Sync with external state changes
    window.addEventListener('kryvex_trading_mode_changed', () => renderAccounts(cachedAccounts));
    window.addEventListener('tradingModeChanged', () => renderAccounts(cachedAccounts));
    window.addEventListener('tradingAccountChanged', () => renderAccounts(cachedAccounts));
    window.addEventListener('kryvex_account_changed', () => renderAccounts(cachedAccounts));
    window.addEventListener('kryvex_wallet_updated', () => loadNavbarTradingAccounts(true));
    window.addEventListener('kryvex_account_created', () => loadNavbarTradingAccounts(true));

    // Ensure mouse wheel scrolling works inside navbar dropdown without being blocked by Lenis smooth scroll
    document.addEventListener('wheel', (e) => {
        const list = e.target.closest('.kv-accounts-drop-list, .kv-nav-accounts-dropdown');
        if (list) {
            const scrollable = list.classList.contains('kv-accounts-drop-list') ? list : list.querySelector('.kv-accounts-drop-list');
            if (scrollable) {
                scrollable.scrollTop += e.deltaY;
            }
        }
    }, { passive: true });

    window.loadNavbarTradingAccounts = loadNavbarTradingAccounts;
    window.refreshNavbarBalances = () => loadNavbarTradingAccounts(true);
}
window.setupNavbarFuturesAccountsDropdown = setupNavbarFuturesAccountsDropdown;

function setupNavbarBalanceAndDeposit() {
    const depositBtn = document.getElementById('navDepositBtn');
    const balanceWrap = document.getElementById('navBalanceWrap');
    const balanceChip = document.getElementById('navBalanceChip');
    const balanceText = document.getElementById('navUserMainBalance');
    const balanceDropdown = document.getElementById('navBalanceDropdown');
    const dropAccBadge = document.getElementById('navBalanceDropAccBadge');
    const dropAccName = document.getElementById('navBalanceDropAccName');
    const dropModeBadge = document.getElementById('navBalanceDropModeBadge');
    const dropMainVal = document.getElementById('navBalanceDropMainVal');
    const dropAvailVal = document.getElementById('navBalanceDropAvailVal');
    const dropLockedVal = document.getElementById('navBalanceDropLockedVal');
    const dropDepositBtn = document.getElementById('navBalanceDropDepositBtn');

    if (!balanceChip && !depositBtn) return;

    const formatNum = (num, minDec = 2, maxDec = 2) => {
        const val = Number(num || 0);
        if (isNaN(val)) return '0.00';
        return val.toLocaleString('en-US', {
            minimumFractionDigits: minDec,
            maximumFractionDigits: maxDec
        });
    };

    const closeBalanceDropImmediately = () => {
        if (balanceWrap) {
            balanceWrap.classList.remove('is-open');
            balanceWrap.classList.add('is-closed');
        }
        if (balanceDropdown) {
            balanceDropdown.classList.add('is-closed');
        }
        if (balanceChip) {
            balanceChip.setAttribute('aria-expanded', 'false');
        }
    };

    const triggerDeposit = (e) => {
        closeBalanceDropImmediately();
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        const clickedEl = e && e.target ? e.target.closest('[data-currency], [data-action-deposit-asset], [data-action-deposit-crypto]') : null;
        const targetCur = clickedEl ? (clickedEl.getAttribute('data-currency') || clickedEl.getAttribute('data-action-deposit-asset') || clickedEl.getAttribute('data-action-deposit-crypto') || 'USDT') : 'USDT';
        
        const activeMode = (window.getActiveTradingMode ? window.getActiveTradingMode() : (localStorage.getItem('kryvex_trading_mode') || 'demo')) === 'live' ? 'live' : 'demo';

        if (activeMode === 'demo') {
            if (typeof window.openFuturesDemoDepositModal === 'function') {
                window.openFuturesDemoDepositModal();
                return;
            }
            const demoDepositModal = document.getElementById('futuresDemoDepositModal');
            if (demoDepositModal) {
                demoDepositModal.hidden = false;
                demoDepositModal.removeAttribute('hidden');
                demoDepositModal.style.display = 'flex';
                return;
            }
        }

        if (typeof window.openAddFundsModal === 'function') {
            window.openAddFundsModal();
            return;
        }
        const addFundsModal = document.getElementById('globalAddFundsModal') || document.getElementById('futuresAddFundsModal');
        if (addFundsModal) {
            addFundsModal.hidden = false;
            addFundsModal.removeAttribute('hidden');
            addFundsModal.style.display = 'flex';
            addFundsModal.style.zIndex = '999999';
            return;
        }

        // Navigate directly to /wallet?tab=deposit if modal elements are not found
        let targetUrl = '/wallet?tab=deposit';
        if (targetCur && targetCur.toUpperCase() !== 'USDT') {
            targetUrl += '&currency=' + encodeURIComponent(targetCur.toUpperCase());
        }
        window.location.href = targetUrl;
    };

    if (depositBtn) {
        depositBtn.addEventListener('click', triggerDeposit);
    }
    const dropDepositEl = document.getElementById('navDropDepositBtn') || dropDepositBtn;
    if (dropDepositEl) {
        dropDepositEl.addEventListener('click', triggerDeposit);
    }

    // Global document click listener for all deposit buttons across navbar, sidebar, futures strip, cards
    document.addEventListener('click', (e) => {
        const depTrigger = e.target.closest('#navDepositBtn, #navDropDepositBtn, #futuresStripDepositBtn, .futures-strip-deposit-btn, [data-action-deposit], [data-action="deposit"], [data-action-deposit-crypto], [data-action-deposit-asset]');
        if (depTrigger) {
            triggerDeposit(e);
        }
    });

    const dropOverviewEl = document.getElementById('navDropOverviewBtn');
    if (dropOverviewEl) {
        dropOverviewEl.addEventListener('click', (e) => {
            if (balanceWrap) {
                balanceWrap.classList.remove('is-open');
                if (balanceDropdown) balanceDropdown.hidden = true;
            }
            if (window.location.pathname.startsWith('/wallet')) {
                e.preventDefault();
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('overview', true);
                } else {
                    const btn = document.getElementById('tabNavOverview');
                    if (btn) btn.click();
                }
            }
        });
    }

    const navWalletLink = document.getElementById('navWalletLink');
    if (navWalletLink) {
        navWalletLink.addEventListener('click', (e) => {
            if (window.location.pathname.startsWith('/wallet')) {
                e.preventDefault();
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('overview', true);
                } else {
                    const btn = document.getElementById('tabNavOverview');
                    if (btn) btn.click();
                }
            }
        });
    }

    const dropTradingAccountsEl = document.getElementById('navDropTradingAccountsBtn');
    if (dropTradingAccountsEl) {
        dropTradingAccountsEl.addEventListener('click', (e) => {
            closeBalanceDropImmediately();
            if (window.location.pathname.startsWith('/wallet')) {
                e.preventDefault();
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('trading', true);
                } else {
                    const btn = document.getElementById('tabNavTrading');
                    if (btn) btn.click();
                }
            }
        });
    }

    const dropWalletEl = document.getElementById('navDropWalletBtn');
    if (dropWalletEl) {
        dropWalletEl.addEventListener('click', (e) => {
            closeBalanceDropImmediately();
            if (window.location.pathname.startsWith('/wallet')) {
                e.preventDefault();
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('wallet', true);
                } else {
                    const btn = document.getElementById('tabNavWallet');
                    if (btn) btn.click();
                }
            }
        });
    }

    const dropTransferEl = document.getElementById('navDropTransferBtn');
    if (dropTransferEl) {
        dropTransferEl.addEventListener('click', (e) => {
            closeBalanceDropImmediately();
            if (window.location.pathname.includes('/futures')) {
                e.preventDefault();
                const tfBtn = document.getElementById('futuresTransferBtn');
                if (tfBtn) {
                    tfBtn.click();
                } else if (typeof window.openFuturesWalletModal === 'function') {
                    window.openFuturesWalletModal('transfer');
                } else {
                    window.location.href = '/wallet?tab=transfer';
                }
            } else if (window.location.pathname.startsWith('/wallet')) {
                e.preventDefault();
                const tfModal = document.getElementById('transferModalBackdrop');
                if (tfModal) {
                    tfModal.hidden = false;
                    tfModal.style.display = 'flex';
                    if (typeof window.updateTransferLiveCalculation === 'function') {
                        window.updateTransferLiveCalculation();
                    }
                }
            }
        });
    }

    const dropWithdrawEl = document.getElementById('navDropWithdrawBtn');
    if (dropWithdrawEl) {
        dropWithdrawEl.addEventListener('click', (e) => {
            closeBalanceDropImmediately();
            if (window.location.pathname.includes('/futures')) {
                e.preventDefault();
                if (typeof window.openFuturesWalletModal === 'function') {
                    window.openFuturesWalletModal('withdraw');
                } else {
                    window.location.href = '/wallet?tab=withdraw';
                }
            } else if (window.location.pathname.startsWith('/wallet')) {
                e.preventDefault();
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('wallet-ops', true);
                }
                if (typeof window.switchWalletInnerNav === 'function') {
                    window.switchWalletInnerNav('withdraw');
                }
                if (typeof window.setGeneralWithdrawDefault === 'function') {
                    window.setGeneralWithdrawDefault();
                }
                const panel = document.getElementById('innerPanelWithdraw') || document.getElementById('viewSectionWalletOps');
                if (panel) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    }

    const dropExchangeEl = document.getElementById('navDropExchangeBtn');
    if (dropExchangeEl) {
        dropExchangeEl.addEventListener('click', (e) => {
            closeBalanceDropImmediately();
            if (window.location.pathname.startsWith('/wallet')) {
                e.preventDefault();
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('exchange', true);
                }
                const panel = document.getElementById('viewSectionExchange');
                if (panel) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    }

    const dropHistoryEl = document.getElementById('navDropHistoryBtn');
    if (dropHistoryEl) {
        dropHistoryEl.addEventListener('click', (e) => {
            closeBalanceDropImmediately();
            if (window.location.pathname.startsWith('/wallet')) {
                e.preventDefault();
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('wallet-ops', true);
                }
                if (typeof window.switchWalletInnerNav === 'function') {
                    window.switchWalletInnerNav('history');
                }
                if (typeof window.loadLedgerHistory === 'function') {
                    window.loadLedgerHistory(1);
                }
                const panel = document.getElementById('innerPanelHistory') || document.getElementById('viewSectionWalletOps');
                if (panel) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    }

    const dropCreateEl = document.getElementById('navDropCreateAccountBtn');
    if (dropCreateEl) {
        dropCreateEl.addEventListener('click', (e) => {
            e.preventDefault();
            closeBalanceDropImmediately();
            if (typeof window.openBrokerModal === 'function') {
                window.openBrokerModal({ isCreateAccount: true });
            }
        });
    }

    if (balanceChip && balanceDropdown && balanceWrap) {
        let balanceCloseTimer = null;

        const openBalanceDrop = () => {
            if (balanceCloseTimer) {
                clearTimeout(balanceCloseTimer);
                balanceCloseTimer = null;
            }
            balanceWrap.classList.remove('is-closed');
            balanceDropdown.classList.remove('is-closed');
            balanceDropdown.removeAttribute('hidden');
            balanceDropdown.hidden = false;

            // Close avatar dropdown if open
            const avatarDrop = document.getElementById('navAvatarDropdown');
            if (avatarDrop) avatarDrop.hidden = true;

            balanceWrap.classList.add('is-open');
            balanceChip.setAttribute('aria-expanded', 'true');
            updateNavbarBalanceDisplay();
        };

        const scheduleCloseBalance = () => {
            if (balanceCloseTimer) clearTimeout(balanceCloseTimer);
            balanceCloseTimer = setTimeout(() => {
                balanceWrap.classList.remove('is-open');
                balanceChip.setAttribute('aria-expanded', 'false');
            }, 250);
        };

        // Hover events: open on enter
        balanceWrap.addEventListener('mouseenter', openBalanceDrop);
        balanceChip.addEventListener('mouseenter', openBalanceDrop);
        balanceDropdown.addEventListener('mouseenter', openBalanceDrop);

        // While mouse moves inside button or dropdown, ensure it remains open
        balanceWrap.addEventListener('mousemove', () => {
            if (balanceCloseTimer) {
                clearTimeout(balanceCloseTimer);
                balanceCloseTimer = null;
            }
            if (balanceWrap.classList.contains('is-closed') || !balanceWrap.classList.contains('is-open')) {
                openBalanceDrop();
            }
        });

        // Mouse leaves balance wrap: reset is-closed so next hover works instantly, then schedule close
        balanceWrap.addEventListener('mouseleave', () => {
            balanceWrap.classList.remove('is-closed');
            balanceDropdown.classList.remove('is-closed');
            scheduleCloseBalance();
        });

        balanceDropdown.addEventListener('mouseleave', scheduleCloseBalance);

        // Click event on balance button chip: ensure it opens and stays open
        balanceChip.addEventListener('click', (e) => {
            e.stopPropagation();
            openBalanceDrop();
        });

        // Document click outside closes it
        document.addEventListener('click', (e) => {
            if (!balanceWrap.contains(e.target)) {
                balanceWrap.classList.remove('is-open');
                balanceChip.setAttribute('aria-expanded', 'false');
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && balanceWrap.classList.contains('is-open')) {
                closeBalanceDropImmediately();
            }
        });

        window.closeNavbarBalanceDropdown = closeBalanceDropImmediately;
    }

    const updateNavbarBalanceDisplay = () => {
        const token = localStorage.getItem('kryvex_access_token');
        if (!token) {
            if (balanceText) balanceText.textContent = '0.00 USDT';
            return;
        }

        const activeMode = (window.getActiveTradingMode ? window.getActiveTradingMode() : localStorage.getItem('kryvex_trading_mode')) === 'live' ? 'live' : 'demo';
        const activeUid = localStorage.getItem('kryvex_active_account_uid');
        const activeCur = (window.getActiveCurrency ? window.getActiveCurrency() : (localStorage.getItem('kryvex_active_currency') || 'USDT')).toUpperCase();

        let accounts = [];
        try {
            accounts = window.__cachedTradingAccounts || JSON.parse(localStorage.getItem('kryvex_cached_trading_accounts') || '[]');
        } catch (e) {
            accounts = [];
        }

        let wallet = {};
        try {
            const wRaw = localStorage.getItem('kryvex_wallet_cache_' + (activeUid || 'default')) || localStorage.getItem('kryvex_wallet_cache');
            if (wRaw) {
                const parsed = JSON.parse(wRaw);
                wallet = parsed.wallet || parsed || {};
            }
        } catch (e) {
            wallet = {};
        }

        let inrRate = 85.0;
        try {
            const inrState = JSON.parse(localStorage.getItem('kryvex_inr_state_cache') || '{}');
            if (inrState && inrState.usdtInrRate > 0) inrRate = Number(inrState.usdtInrRate);
        } catch (e) {}

        // Find the currently open / selected account:
        let currentAcc = activeUid ? accounts.find(a => String(a.account_uid) === String(activeUid)) : null;
        if (!currentAcc && accounts.length > 0) {
            currentAcc = (activeMode === 'demo')
                ? accounts.find(a => a.account_type === 'demo' || a.is_demo)
                : (accounts.find(a => a.is_default && !a.is_demo) || accounts[0]);
        }

        const isDemo = currentAcc
            ? Boolean(currentAcc.account_type === 'demo' || currentAcc.is_demo || String(currentAcc.account_name || '').toLowerCase().startsWith('demo'))
            : (activeMode === 'demo');

        const isMt5 = String(currentAcc?.broker_type || '').toLowerCase() === 'mt5';
        const isCent = isCentAccount(currentAcc);
        const isInr = (activeCur === 'INR') && !isDemo && !isMt5;

        const navToggle = document.getElementById('navCurrencyToggleWrap');
        if (navToggle) {
            if (isDemo || isMt5) {
                navToggle.classList.add('is-demo-hidden');
                navToggle.style.display = 'none';
            } else {
                navToggle.classList.remove('is-demo-hidden');
                navToggle.style.display = 'inline-flex';
            }
        }

        let code = isDemo ? '#D1000000' : '#L1000001';
        let name = isDemo ? 'Demo Account' : 'Main Account';
        let mainBalUsd = 0;
        let mainBalInr = 0;
        let availBalUsd = 0;
        let availBalInr = 0;
        let lockedBalUsd = 0;
        let lockedBalInr = 0;

        if (isDemo) {
            const accNum = currentAcc?.account_number || '1000000';
            code = `#D${accNum}`;
            name = currentAcc?.account_name || 'Demo Account';

            const activeLiveAvail = (typeof window.__activeFuturesLiveAvailable === 'number' && !isNaN(window.__activeFuturesLiveAvailable))
                ? window.__activeFuturesLiveAvailable
                : null;

            const baseDemoBal = (activeLiveAvail !== null)
                ? activeLiveAvail
                : Number(
                    (currentAcc?.equity !== undefined && currentAcc?.equity !== null)
                        ? currentAcc.equity
                        : ((currentAcc?.demo_available_balance !== undefined && currentAcc?.demo_available_balance !== null)
                            ? currentAcc.demo_available_balance
                            : ((currentAcc?.balance !== undefined && currentAcc?.balance !== null)
                                ? currentAcc.balance
                                : (wallet.demo_available_balance || currentAcc?.main_balance || wallet.demo_main_balance || 60000)))
                );

            mainBalUsd = baseDemoBal;
            mainBalInr = mainBalUsd * inrRate;

            availBalUsd = baseDemoBal;
            availBalInr = availBalUsd * inrRate;
            lockedBalUsd = Number(currentAcc?.demo_locked_balance ?? wallet.demo_locked_balance ?? 0);
            lockedBalInr = lockedBalUsd * inrRate;
        } else {
            window.__activeFuturesLiveAvailable = null;
            const accNum = currentAcc?.account_number || '1000001';
            code = `#L${accNum}`;
            name = currentAcc?.account_name || 'Main Account';

            const fallbackWalletUsd = Number(wallet.real_main_balance ?? wallet.crypto_balances?.USDT ?? wallet.real_crypto_balances?.USDT ?? wallet.main_balance ?? 0);
            const fallbackWalletInr = Number(wallet.real_main_balance_inr ?? (fallbackWalletUsd * inrRate));

            mainBalUsd = Number(currentAcc?.main_balance || currentAcc?.real_main_balance || fallbackWalletUsd);
            mainBalInr = Number(currentAcc?.main_balance_inr || currentAcc?.real_main_balance_inr || fallbackWalletInr || (mainBalUsd * inrRate));

            availBalUsd = Number(currentAcc?.real_available_balance ?? wallet.real_available_balance ?? mainBalUsd);
            availBalInr = Number(currentAcc?.real_available_balance_inr ?? wallet.real_available_balance_inr ?? (availBalUsd * inrRate));
            lockedBalUsd = Number(currentAcc?.real_locked_balance ?? wallet.real_locked_balance ?? 0);
            lockedBalInr = Number(currentAcc?.real_locked_balance_inr ?? wallet.real_locked_balance_inr ?? (lockedBalUsd * inrRate));
        }

        // Top navbar chip shows the selected account's Main Balance!
        if (balanceText) {
            if (isDemo) {
                balanceText.textContent = `${formatNum(mainBalUsd)} USDT`;
            } else if (isMt5 && isCent) {
                balanceText.textContent = formatUsc(mainBalUsd);
            } else if (isMt5) {
                balanceText.textContent = `$${formatNum(mainBalUsd)} USD`;
            } else {
                const displayBal = isInr ? mainBalInr : mainBalUsd;
                const currencySuffix = isInr ? 'INR' : 'USDT';
                balanceText.textContent = `${formatNum(displayBal)} ${currencySuffix}`;
            }
        }

        const userAccIdEl = document.getElementById('navUserAccountId');
        if (userAccIdEl) {
            userAccIdEl.textContent = code.replace(/^#/, '');
        }

        const userCurBadgeEl = document.getElementById('navUserCurrencyBadge');
        if (userCurBadgeEl) {
            userCurBadgeEl.textContent = isDemo ? 'USDT' : (isInr ? 'INR' : 'USDT');
        }

        const userModeBadgeEl = document.getElementById('navUserModeBadge');
        if (userModeBadgeEl) {
            const displayMode = isDemo ? 'DEMO' : 'LIVE';
            userModeBadgeEl.textContent = displayMode;
            userModeBadgeEl.className = `kv-nav-badge-pill kv-nav-badge-mode is-${isDemo ? 'demo' : 'live'}`;
        }

        if (dropAccBadge) dropAccBadge.textContent = code;
        if (dropAccName) dropAccName.textContent = name;
        if (dropModeBadge) {
            dropModeBadge.textContent = isDemo ? 'DEMO' : 'LIVE';
            dropModeBadge.className = `kv-nav-drop-mode-badge is-${isDemo ? 'demo' : 'live'}`;
        }
        if (isDemo) {
            if (dropMainVal) dropMainVal.textContent = `${formatNum(mainBalUsd)} USDT`;
            if (dropAvailVal) dropAvailVal.textContent = `${formatNum(availBalUsd)} USDT`;
            if (dropLockedVal) dropLockedVal.textContent = `${formatNum(lockedBalUsd)} USDT`;
        } else if (isCent) {
            if (dropMainVal) dropMainVal.textContent = formatUsc(mainBalUsd);
            if (dropAvailVal) dropAvailVal.textContent = formatUsc(availBalUsd);
            if (dropLockedVal) dropLockedVal.textContent = formatUsc(lockedBalUsd);
        } else {
            if (dropMainVal) dropMainVal.textContent = isInr ? `${formatNum(mainBalInr)} INR` : `${formatNum(mainBalUsd)} USDT`;
            if (dropAvailVal) dropAvailVal.textContent = isInr ? `${formatNum(availBalInr)} INR` : `${formatNum(availBalUsd)} USDT`;
            if (dropLockedVal) dropLockedVal.textContent = isInr ? `${formatNum(lockedBalInr)} INR` : `${formatNum(lockedBalUsd)} USDT`;
        }

        // In Demo mode: single balance only, hide Main Balance row and Transfer button
        const dropMainRow = document.getElementById('navBalanceDropMainRow');
        const dropAvailLabel = document.getElementById('navBalanceDropAvailLabel');
        const dropTransferBtn = document.getElementById('navBalanceDropTransferBtn');

        if (isDemo) {
            if (dropMainRow) dropMainRow.style.display = 'none';
            if (dropAvailLabel) dropAvailLabel.textContent = 'Balance';
            if (dropTransferBtn) dropTransferBtn.style.display = 'none';
        } else {
            if (dropMainRow) dropMainRow.style.display = 'flex';
            if (dropAvailLabel) dropAvailLabel.textContent = 'Available Balance';
            if (dropTransferBtn) dropTransferBtn.style.display = 'inline-flex';
        }
    };

    window.updateNavbarBalanceDisplay = updateNavbarBalanceDisplay;

    updateNavbarBalanceDisplay();

    window.addEventListener('kryvex_currency_changed', updateNavbarBalanceDisplay);
    window.addEventListener('currency_changed', updateNavbarBalanceDisplay);
    const handleTradingModeChange = (e) => {
        if (e && e.detail && e.detail.mode === 'live') {
            window.__activeFuturesLiveAvailable = null;
        }
        updateNavbarBalanceDisplay();
    };

    window.addEventListener('tradingModeChanged', handleTradingModeChange);
    window.addEventListener('kryvex_trading_mode_changed', handleTradingModeChange);
    window.addEventListener('tradingAccountChanged', updateNavbarBalanceDisplay);
    window.addEventListener('kryvex_account_changed', updateNavbarBalanceDisplay);
    window.addEventListener('kryvex_wallet_updated', updateNavbarBalanceDisplay);
    window.addEventListener('wallet_updated', updateNavbarBalanceDisplay);
    window.addEventListener('kryvex_balances_updated', updateNavbarBalanceDisplay);
    window.addEventListener('futures_balance_updated', updateNavbarBalanceDisplay);
    window.addEventListener('storage', (e) => {
        if (e.key && e.key.includes('kryvex')) {
            updateNavbarBalanceDisplay();
        }
    });
}
window.setupNavbarBalanceAndDeposit = setupNavbarBalanceAndDeposit;

window.togglePasswordVisibility = function(button) {
    if (!button) return;
    const container = button.closest('.password-field') || button.parentElement;
    const input = container ? container.querySelector('input') : null;

    if (!input) return;

    const isShown = button.getAttribute('data-show') === 'true';

    if (isShown) {
        input.type = 'password';
        input.setAttribute('type', 'password');
        button.setAttribute('data-show', 'false');
    } else {
        input.type = 'text';
        input.setAttribute('type', 'text');
        button.setAttribute('data-show', 'true');
    }

    const eyeIcon = button.querySelector('.icon-eye');
    const eyeOffIcon = button.querySelector('.icon-eye-off');

    if (eyeIcon && eyeOffIcon) {
        if (isShown) {
            eyeIcon.classList.remove('hidden');
            eyeOffIcon.classList.add('hidden');
            eyeIcon.style.setProperty('display', 'block', 'important');
            eyeOffIcon.style.setProperty('display', 'none', 'important');
        } else {
            eyeIcon.classList.add('hidden');
            eyeOffIcon.classList.remove('hidden');
            eyeIcon.style.setProperty('display', 'none', 'important');
            eyeOffIcon.style.setProperty('display', 'block', 'important');
        }
    }
};

document.addEventListener('click', (event) => {
    const button = event.target.closest('.password-toggle, [data-password-toggle]');
    if (button) {
        event.preventDefault();
        window.togglePasswordVisibility(button);
    }
});

function setupGlobalKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // 1. Check if shortcuts are globally enabled
        const isMasterEnabled = localStorage.getItem('kryvex_shortcuts_enabled') !== 'false';
        if (!isMasterEnabled) return;

        // 2. Load individual key preferences
        let keyPrefs = {};
        try {
            keyPrefs = JSON.parse(localStorage.getItem('kryvex_shortcut_keys') || '{}') || {};
        } catch (err) {}

        const isKeyActive = (key) => keyPrefs[key] !== false; // Active by default unless set to false

        // 3. Check if user is typing inside an input/form element
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
        const isInputTarget = ['input', 'textarea', 'select'].includes(activeTag) || Boolean(document.activeElement?.isContentEditable);

        // Key identification
        const isShift = e.shiftKey;
        const isAlt = e.altKey;
        const isCtrl = e.ctrlKey || e.metaKey;
        const key = e.key ? e.key.toUpperCase() : '';

        // --- 1. Place Order (Shift + Enter) ---
        if (isShift && !isAlt && !isCtrl && e.key === 'Enter' && isKeyActive('place_order')) {
            const placeBtn = document.querySelector('#placeDemoOrderButton, #btnSubmitOrder, #btnPlaceOrder, #btnBuyLong, #btnSubmitBuy, #btnSubmitSell, .order-place-submit, .btn-place-order');
            if (placeBtn) {
                e.preventDefault();
                placeBtn.click();
            }
            return;
        }

        // --- 2. Cancel All Open Orders (Alt + C) ---
        if (isAlt && !isShift && !isCtrl && key === 'C' && isKeyActive('cancel_orders')) {
            e.preventDefault();
            const cancelButtons = document.querySelectorAll('.btn-cancel-futures-order, .btn-cancel-order, #btnCancelAllOrders, .btn-cancel-all');
            if (cancelButtons.length > 0) {
                cancelButtons.forEach(btn => btn.click());
            } else if (typeof window.cancelAllDemoOrders === 'function') {
                window.cancelAllDemoOrders();
            }
            return;
        }

        // --- 3. Toggle Fullscreen Chart (Alt + T) ---
        if (isAlt && !isShift && !isCtrl && key === 'T' && isKeyActive('chart_view')) {
            e.preventDefault();
            const chartElem = document.querySelector('#tradingViewChart, #tvChartContainer, .futures-chart-card, #tradingview_chart, .chart-wrapper-box');
            if (chartElem) {
                if (!document.fullscreenElement) {
                    if (chartElem.requestFullscreen) chartElem.requestFullscreen();
                    else if (chartElem.webkitRequestFullscreen) chartElem.webkitRequestFullscreen();
                } else {
                    if (document.exitFullscreen) document.exitFullscreen();
                    else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
                }
            }
            return;
        }

        // --- 4. Close All Positions (Shift + X) ---
        if (isShift && !isAlt && !isCtrl && key === 'X' && isKeyActive('close_all')) {
            e.preventDefault();
            const closeAllBtn = document.querySelector('#mobileCloseAllBtn, #btnCloseAllPositions, .btn-close-all-positions');
            const closePosBtns = document.querySelectorAll('.btn-close-position');
            if (closeAllBtn && getComputedStyle(closeAllBtn).display !== 'none') {
                closeAllBtn.click();
            } else if (closePosBtns.length > 0) {
                closePosBtns.forEach(btn => btn.click());
            } else if (typeof window.closeAllPositions === 'function') {
                window.closeAllPositions();
            }
            return;
        }

        // For single key shortcuts (B and S), skip if user is typing text in an input
        if (isInputTarget) return;

        // --- 5. Quick Buy / Long (B) ---
        if (!isShift && !isAlt && !isCtrl && key === 'B' && isKeyActive('quick_buy')) {
            e.preventDefault();
            const buyTab = document.querySelector('.order-side-tabs button[data-side="buy"], #tabSideBuy, #btnSideBuy, .btn-side-buy');
            if (buyTab) buyTab.click();
            const qtyInput = document.querySelector('#orderQuantity, #orderQuantityInput, #inputQuantity, input[name="quantity"]');
            if (qtyInput) qtyInput.focus();
            return;
        }

        // --- 6. Quick Sell / Short (S) ---
        if (!isShift && !isAlt && !isCtrl && key === 'S' && isKeyActive('quick_sell')) {
            e.preventDefault();
            const sellTab = document.querySelector('.order-side-tabs button[data-side="sell"], #tabSideSell, #btnSideSell, .btn-side-sell');
            if (sellTab) sellTab.click();
            const qtyInput = document.querySelector('#orderQuantity, #orderQuantityInput, #inputQuantity, input[name="quantity"]');
            if (qtyInput) qtyInput.focus();
            return;
        }
    });
}

function setupNumberInputWheelBlock() {
    // Disable accidental wheel increment/decrement on all number inputs globally
    document.addEventListener('wheel', function (e) {
        if (e.target && e.target.tagName === 'INPUT' && e.target.type === 'number') {
            if (document.activeElement === e.target) {
                e.target.blur();
            }
            e.preventDefault();
        }
    }, { passive: false });

    document.addEventListener('focusin', function (e) {
        if (e.target && e.target.tagName === 'INPUT' && e.target.type === 'number') {
            if (!e.target.dataset.noWheelAttached) {
                e.target.dataset.noWheelAttached = 'true';
                e.target.addEventListener('wheel', function (ev) {
                    ev.preventDefault();
                }, { passive: false });
            }
        }
    });
}
window.setupNumberInputWheelBlock = setupNumberInputWheelBlock;

// =========================================================================
// GLOBAL BROKER & MULTI-ACCOUNT CREATION SYSTEM (EXCHANGE & MT5 SEPARATION)
// =========================================================================
let globalBrokerStatusData = null;
let globalSelectedBrokerChoice = 'kryvex';
let globalSelectedPlanKey = 'basic';
let globalSelectedAccountType = 'live';
let globalIsCreateAccountMode = false;
let currentCawStep = 1;

const escapeBrokerHtml = (str) => String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const CAW_PLANS = {
    kryvex: [
        {
            key: 'basic',
            name: 'Kryvex Basic',
            badge: 'Starter',
            description: 'For traders who want to trade perpetual crypto futures and spot markets with standard conditions.',
            minBalance: '$10',
            leverage: '1:100',
            spread: '0.05%',
            bonus: '+20% Deposit Bonus',
            minDepositText: 'Min deposit: $10',
            instruments: [
                { name: 'Crypto futures', maxLev: '1:100' },
                { name: 'Spot Trading', maxLev: '1:1' },
                { name: 'Crypto Options', maxLev: '1:20' },
                { name: 'Indices', maxLev: '1:50' },
                { name: 'Average Spread', maxLev: '0.05%' }
            ],
            features: [
                'Cross & Isolated margin',
                'Customisable leverage',
                'Standard execution speed',
                'Order Book for crypto futures',
                'TradingView charts'
            ],
            bonuses: [
                'Cashback 5%',
                'Starter VIP tier',
                '+20% Welcome bonus'
            ]
        },
        {
            key: 'standard',
            name: 'Kryvex 2.0',
            badge: 'Most Popular',
            isPopular: true,
            description: 'For traders who want to trade perpetual crypto futures and CF markets from the same account',
            minBalance: '$50,000',
            leverage: '1:300',
            spread: '0.03%',
            bonus: '+50% Deposit Bonus',
            minDepositText: 'Min deposit: $50,000',
            instruments: [
                { name: 'Crypto futures', maxLev: '1:300' },
                { name: 'Forex', maxLev: '1:300' },
                { name: 'Commodities', maxLev: '1:300' },
                { name: 'Crypto', maxLev: '1:300' },
                { name: 'Indices', maxLev: '1:100' },
                { name: 'Average Spread', maxLev: '0.03%' }
            ],
            features: [
                'Cross & Isolated margin',
                'Customisable leverage',
                'Netting & Hedge modes',
                'Order Book for crypto futures',
                'TradingView charts'
            ],
            bonuses: [
                'Cashback 10%',
                'VIP tiers',
                '+50% Trading bonus'
            ]
        },
        {
            key: 'advanced',
            name: 'Kryvex Institutional',
            badge: 'VIP Pro',
            description: 'For high-volume & institutional traders requiring ultra-low latency, maximum leverage and lowest spreads',
            minBalance: '$250,000',
            leverage: '1:500',
            spread: '0.01%',
            bonus: '+100% Deposit Bonus',
            minDepositText: 'Min deposit: $250,000',
            instruments: [
                { name: 'Crypto futures', maxLev: '1:500' },
                { name: 'Forex', maxLev: '1:500' },
                { name: 'Commodities', maxLev: '1:500' },
                { name: 'Indices', maxLev: '1:200' },
                { name: 'Raw Spread', maxLev: '0.01%' }
            ],
            features: [
                'Cross & Isolated margin',
                'Customisable leverage up to 1:500',
                'Netting & Hedge modes',
                'Ultra-low latency VIP API bridge',
                'Dedicated account manager'
            ],
            bonuses: [
                'Cashback 20%',
                'VIP Institutional Tier',
                '+100% Trading bonus'
            ]
        }
    ],
    mt5: [
        {
            key: 'cent',
            name: 'MT5 Cent Account',
            badge: 'Micro Cent',
            description: 'Cent account in US Cents (1 USD = 100 USC), ideal for micro-lot trading, EA testing & risk management.',
            minBalance: '$1',
            leverage: '1:1000',
            spread: '0.08%',
            bonus: '+15% Welcome Bonus',
            minDepositText: 'Min deposit: $1',
            instruments: [
                { name: 'Forex Currencies', maxLev: '1:1000' },
                { name: 'Commodities (Gold/Oil)', maxLev: '1:500' },
                { name: 'Crypto CFDs', maxLev: '1:100' },
                { name: 'Indices', maxLev: '1:50' },
                { name: 'Average Spread', maxLev: '0.08%' }
            ],
            features: [
                'Balance in US Cents (1 USD = 100 USC)',
                'Micro-lot trading (0.01 cent lot)',
                'Full MT5 terminal & EA support',
                'Low risk, ideal for EA testing',
                'Standard global liquidity bridge'
            ],
            bonuses: [
                'Instant cent conversion (100 USC/$1)',
                'Zero deposit commission',
                '+15% Welcome bonus'
            ]
        },
        {
            key: 'basic',
            name: 'MT5 Standard',
            badge: 'Starter',
            description: 'For traders who want to trade Forex, Commodities, Crypto and Indices with standard spreads on MetaTrader 5',
            minBalance: '$10',
            leverage: '1:100',
            spread: '0.05%',
            bonus: '+20% Welcome Bonus',
            minDepositText: 'Min deposit: $10',
            instruments: [
                { name: 'Forex Currencies', maxLev: '1:100' },
                { name: 'Commodities (Gold/Oil)', maxLev: '1:100' },
                { name: 'Crypto CFDs', maxLev: '1:100' },
                { name: 'Indices', maxLev: '1:50' },
                { name: 'Average Spread', maxLev: '0.05%' }
            ],
            features: [
                'Full MT5 terminal (Desktop / Mobile / Web)',
                'Expert Advisors (EA) automated bots',
                'Hedging & Netting modes',
                '38+ Technical indicators & charting',
                'Standard global liquidity bridge'
            ],
            bonuses: [
                'Zero deposit commission',
                'Standard swap rates',
                '+20% Welcome bonus'
            ]
        },
        {
            key: 'standard',
            name: 'MT5 Raw Spread',
            badge: 'Most Popular',
            isPopular: true,
            description: 'Direct raw interbank ECN pricing with high leverage and ultra-fast sub-millisecond execution',
            minBalance: '$50,000',
            leverage: '1:300',
            spread: '0.03%',
            bonus: '+50% Welcome Bonus',
            minDepositText: 'Min deposit: $50,000',
            instruments: [
                { name: 'Forex Currencies', maxLev: '1:300' },
                { name: 'Commodities (Gold/Oil)', maxLev: '1:300' },
                { name: 'Crypto CFDs', maxLev: '1:300' },
                { name: 'Indices', maxLev: '1:100' },
                { name: 'Raw ECN Spread', maxLev: 'From 0.03%' }
            ],
            features: [
                'Direct ECN raw interbank feed',
                'Execution speed < 15ms via NY4 / LD4',
                'Customisable leverage up to 1:300',
                'Free VPS for 24/7 automated EAs',
                'Zero requotes & depth of market'
            ],
            bonuses: [
                'Cashback 10%',
                'Free VPS hosting included',
                '+50% Welcome bonus'
            ]
        },
        {
            key: 'advanced',
            name: 'MT5 Institutional Pro',
            badge: 'Institutional',
            description: 'Designed for hedge funds, prop traders and VIP clients seeking institutional liquidity conditions',
            minBalance: '$250,000',
            leverage: '1:500',
            spread: '0.01%',
            bonus: '+100% Welcome Bonus',
            minDepositText: 'Min deposit: $250,000',
            instruments: [
                { name: 'Forex Currencies', maxLev: '1:500' },
                { name: 'Commodities', maxLev: '1:500' },
                { name: 'Crypto CFDs', maxLev: '1:500' },
                { name: 'Indices', maxLev: '1:200' },
                { name: 'Institutional Raw', maxLev: 'From 0.01%' }
            ],
            features: [
                'Tier-1 bank direct institutional feed',
                'Customisable leverage up to 1:500',
                'Zero-slippage FIX API 4.4 connectivity',
                'Custom MT5 bridge & routing',
                'Dedicated 24/7 VIP manager'
            ],
            bonuses: [
                'Cashback 20%',
                'Zero swap fees on major pairs',
                '+100% Welcome bonus'
            ]
        }
    ]
};

async function loadGlobalBrokerStatus() {
    const token = localStorage.getItem('kryvex_access_token');
    if (!token) return null;

    try {
        const response = await fetch('/api/broker/status', {
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${token}`,
            },
        });
        const result = await response.json();
        if (response.ok && result.success) {
            globalBrokerStatusData = result.data || {};
            return globalBrokerStatusData;
        }
    } catch (e) {
        console.warn('Failed to load broker status:', e);
    }
    return null;
}

function updateCawStepper(stepNumber) {
    for (let i = 1; i <= 3; i++) {
        const stepEl = document.getElementById(`cawStepNav${i}`);
        if (!stepEl) continue;
        stepEl.classList.remove('is-active', 'is-completed');
        if (i < stepNumber) {
            stepEl.classList.add('is-completed');
        } else if (i === stepNumber) {
            stepEl.classList.add('is-active');
        }
    }

    const line1 = document.getElementById('cawStepLine1');
    const line2 = document.getElementById('cawStepLine2');
    if (line1) line1.classList.toggle('is-active', stepNumber >= 2);
    if (line2) line2.classList.toggle('is-active', stepNumber >= 3);

    const stepCountText = document.getElementById('cawStepCountText');
    if (stepCountText) {
        stepCountText.textContent = `Step ${stepNumber} of 3`;
    }
}

function hasKryvexDemoAccount() {
    let accounts = [];
    try {
        if (Array.isArray(window.__cachedTradingAccounts) && window.__cachedTradingAccounts.length > 0) {
            accounts = window.__cachedTradingAccounts;
        } else {
            const cached = localStorage.getItem('kryvex_cached_trading_accounts');
            if (cached) accounts = JSON.parse(cached);
        }
    } catch (e) {}

    return accounts.some(acc => {
        const broker = String(acc.broker_type || 'kryvex').toLowerCase();
        const isDemo = acc.account_type === 'demo' || acc.is_demo === true || String(acc.account_name || '').toLowerCase().includes('demo');
        return broker === 'kryvex' && isDemo;
    });
}
window.hasKryvexDemoAccount = hasKryvexDemoAccount;

function syncCawDemoRestrictions() {
    const isDemo = globalSelectedAccountType === 'demo';
    const isKryvex = globalSelectedBrokerChoice === 'kryvex';
    const hasDemo = hasKryvexDemoAccount();

    const banner1 = document.getElementById('cawDemoLimitBannerStep1');
    const banner2 = document.getElementById('cawDemoLimitBannerStep2');
    const planSection = document.getElementById('cawSection3');
    const nextBtn = document.getElementById('cawBtnFooterNext');

    const step = window.currentCawStep || currentCawStep || 1;

    // Step 1 banner is never shown (user selects broker on step 2)
    if (banner1) {
        banner1.style.display = 'none';
    }

    // Synchronize the Step 2 compact mini switch toggle
    document.querySelectorAll('[data-caw-mini-env]').forEach(btn => {
        btn.classList.toggle('is-active', btn.dataset.cawMiniEnv === globalSelectedAccountType);
    });

    // Step 1: NEVER block on Step 1 so user can proceed to Step 2 (Select Broker)
    if (step === 1) {
        if (nextBtn) {
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
            nextBtn.title = '';
        }
        return;
    }

    // Step 2 & 3: Apply restrictions based on chosen broker and environment
    if (isDemo && isKryvex) {
        // Kryvex Demo: Hide plans section completely
        if (planSection) {
            planSection.style.display = 'none';
        }

        if (hasDemo) {
            // Already has 1 Kryvex demo account -> Show PrimeXBT banner and disable Next
            if (banner2) banner2.style.display = 'flex';
            if (nextBtn) {
                nextBtn.disabled = true;
                nextBtn.style.opacity = '0.5';
                nextBtn.style.cursor = 'not-allowed';
                nextBtn.title = 'All available accounts have been created.';
            }
        } else {
            // Does not have demo account yet -> Can proceed
            if (banner2) banner2.style.display = 'none';
            if (nextBtn) {
                nextBtn.disabled = false;
                nextBtn.style.opacity = '1';
                nextBtn.style.cursor = 'pointer';
                nextBtn.title = '';
            }
        }
    } else {
        // Either Live Account OR MT5 Demo:
        // Hide limit banner
        if (banner2) banner2.style.display = 'none';

        // SHOW plans section (Live plans for Kryvex/MT5, or MT5 Demo plans)
        if (planSection) {
            planSection.style.display = 'block';
        }

        // Enable Next button
        if (nextBtn) {
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
            nextBtn.title = '';
        }
    }
}
window.syncCawDemoRestrictions = syncCawDemoRestrictions;

window.setCawAccountType = function(env) {
    globalSelectedAccountType = env || 'live';
    syncCawCardsSelection();
    syncCawDemoRestrictions();
};

window.setCawBrokerChoice = function(broker) {
    globalSelectedBrokerChoice = broker || 'kryvex';
    document.querySelectorAll('.caw-card[data-caw-broker]').forEach(c => {
        const isSel = c.dataset.cawBroker === globalSelectedBrokerChoice;
        c.classList.toggle('is-selected', isSel);
        const radio = c.querySelector('.caw-radio');
        if (radio) radio.classList.toggle('is-checked', isSel);
    });
    renderCawPlans();
    syncCawDemoRestrictions();
};

window.setCawPlanKey = function(planKey) {
    globalSelectedPlanKey = planKey || 'basic';
    const container = document.getElementById('cawPlansContainer');
    if (container) {
        container.querySelectorAll('.caw-px-card[data-caw-plan]').forEach(c => {
            const isMatch = c.dataset.cawPlan === globalSelectedPlanKey;
            c.classList.toggle('is-selected', isMatch);
            const radio = c.querySelector('.caw-radio');
            if (radio) radio.classList.toggle('is-checked', isMatch);
        });
    }
};

let lastCawBackClickTime = 0;
window.handleCawFooterBack = function() {
    const now = Date.now();
    if (now - lastCawBackClickTime < 200) return;
    lastCawBackClickTime = now;

    let step = window.currentCawStep || currentCawStep || 1;
    if (step <= 1) {
        closeBrokerModals();
    } else {
        goToCawStep(step - 1);
    }
};

let lastCawNextClickTime = 0;
window.handleCawFooterNext = function() {
    const now = Date.now();
    if (now - lastCawNextClickTime < 200) return;
    lastCawNextClickTime = now;

    let step = window.currentCawStep || currentCawStep || 1;

    // Block on Step 2 or 3 if Kryvex demo limit reached
    if (step >= 2 && globalSelectedAccountType === 'demo' && globalSelectedBrokerChoice === 'kryvex' && hasKryvexDemoAccount()) {
        if (typeof window.showToast === 'function') {
            window.showToast({
                type: 'error',
                title: 'Account Limit',
                message: 'All available accounts have been created.'
            });
        }
        return;
    }

    if (step < 3) {
        goToCawStep(step + 1);
    } else {
        submitGlobalPlanRequest();
    }
};

async function submitGlobalPlanRequest() {
    const nextBtn = document.getElementById('cawBtnFooterNext');
    const nextLabel = document.getElementById('cawBtnNextLabel');
    const nameInput = document.getElementById('cawAccountNameInput');

    const token = localStorage.getItem('kryvex_access_token');
    if (!token) {
        if (typeof window.showToast === 'function') {
            window.showToast({ type: 'error', title: 'Login Required', message: 'Please log in to create a trading account.' });
        } else {
            alert('Please log in to create a trading account.');
        }
        window.location.href = '/login';
        return;
    }

    const accountName = nameInput ? nameInput.value.trim() : '';
    const accountType = globalSelectedAccountType || 'live';
    const brokerType = globalSelectedBrokerChoice || 'kryvex';
    const planKey = (accountType === 'demo' && brokerType === 'kryvex') ? 'demo' : (globalSelectedPlanKey || 'basic');

    if (accountType === 'demo' && brokerType === 'kryvex' && hasKryvexDemoAccount()) {
        if (typeof window.showToast === 'function') {
            window.showToast({
                type: 'error',
                title: 'Account Limit',
                message: 'All available accounts have been created.'
            });
        }
        return;
    }

    if (nextBtn) {
        nextBtn.disabled = true;
        nextBtn.style.opacity = '0.7';
        nextBtn.style.cursor = 'not-allowed';
    }
    if (nextLabel) {
        nextLabel.textContent = 'Creating...';
    }

    try {
        const response = await fetch('/api/trading-accounts', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'X-Broker-Type': brokerType
            },
            body: JSON.stringify({
                account_name: accountName,
                broker_type: brokerType,
                plan_key: planKey,
                account_type: accountType
            })
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to create trading account.');
        }

        try {
            await fetch('/api/broker/select', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({
                    broker_type: brokerType,
                    plan_key: planKey
                })
            });
            let userObj = {};
            try { userObj = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {}; } catch(e){}
            userObj.broker_type = brokerType;
            localStorage.setItem('kryvex_user', JSON.stringify(userObj));
        } catch (e) {}

        const createdAccount = data.data || {};
        if (createdAccount.account_uid) {
            localStorage.setItem('kryvex_active_account_uid', createdAccount.account_uid);
        }

        if (typeof window.showToast === 'function') {
            window.showToast({
                type: 'success',
                title: 'Account Created',
                message: data.message || `Trading account '${createdAccount.account_name || accountName || 'New Account'}' created successfully.`
            });
        }

        window.dispatchEvent(new CustomEvent('tradingAccountCreated', { detail: createdAccount }));
        window.dispatchEvent(new CustomEvent('tradingAccountChanged', { detail: { accountUid: createdAccount.account_uid, account: createdAccount } }));
        window.dispatchEvent(new CustomEvent('kryvex_wallet_updated', { detail: { account: createdAccount } }));

        if (brokerType === 'mt5') {
            const modal1 = document.getElementById('modalChooseBroker');
            const modal3 = document.getElementById('modalMt5Requested');
            const reqPlanNameEl = document.getElementById('reqModalPlanName');

            const plans = CAW_PLANS.mt5 || [];
            const planObj = plans.find(p => p.key === planKey);
            if (reqPlanNameEl) {
                reqPlanNameEl.textContent = planObj ? planObj.name : planKey.toUpperCase();
            }

            if (modal1) {
                modal1.setAttribute('hidden', 'true');
                modal1.hidden = true;
                modal1.classList.remove('is-open');
                modal1.style.display = 'none';
            }
            if (modal3) {
                modal3.removeAttribute('hidden');
                modal3.hidden = false;
                modal3.classList.add('is-open');
                modal3.style.display = 'block';
            }
        } else {
            closeBrokerModals();
            if (window.location.pathname.startsWith('/wallet')) {
                setTimeout(() => {
                    window.location.reload();
                }, 400);
            }
        }
    } catch (err) {
        if (typeof window.showToast === 'function') {
            window.showToast({
                type: 'error',
                title: 'Creation Failed',
                message: err.message || 'Unable to create account. Please try again.'
            });
        } else {
            alert(err.message || 'Unable to create account.');
        }
    } finally {
        if (nextBtn) {
            nextBtn.disabled = false;
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        }
        if (nextLabel) {
            nextLabel.textContent = 'Create Account';
        }
    }
}

function renderCawPlans() {
    const container = document.getElementById('cawPlansContainer');
    if (!container) return;

    const broker = globalSelectedBrokerChoice === 'mt5' ? 'mt5' : 'kryvex';
    const plans = CAW_PLANS[broker] || CAW_PLANS.kryvex;
    container.className = `caw-cards-grid ${plans.length >= 4 ? 'caw-grid-4' : 'caw-grid-3'}`;

    const subtitle = document.getElementById('cawPlanSubtitle');
    if (subtitle) {
        subtitle.textContent = broker === 'mt5'
            ? 'Choose an MT5 account tier with leverage, features and bonuses'
            : 'Choose an exchange account tier with leverage, features and bonuses';
    }

    container.innerHTML = plans.map(p => {
        const isSel = p.key === globalSelectedPlanKey;
        const popularTag = p.isPopular ? '<span class="caw-tag-rec" style="font-size:0.62rem;padding:2px 7px;">Most Popular</span>' : '';

        const instRowsHtml = p.instruments.map(inst => `
            <div class="caw-px-tr">
                <span class="caw-px-inst">${inst.name}</span>
                <span class="caw-px-lev">${inst.maxLev}</span>
            </div>
        `).join('');

        const featuresHtml = p.features.map(f => `
            <li>
                <span class="caw-px-check">✓</span>
                <span>${f}</span>
            </li>
        `).join('');

        const bonusesHtml = p.bonuses.map(b => `
            <li>
                <span class="caw-px-check">✓</span>
                <span class="caw-px-bonus-text">${b}</span>
            </li>
        `).join('');

        return `
            <div class="caw-px-card ${isSel ? 'is-selected' : ''}" data-caw-plan="${p.key}" onclick="if(typeof window.setCawPlanKey==='function') window.setCawPlanKey('${p.key}');">
                <div>
                    <div class="caw-px-head">
                        <div>
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:3px;">
                                <h3 class="caw-px-title">${p.name}</h3>
                                ${popularTag}
                            </div>
                        </div>
                        <div class="caw-radio ${isSel ? 'is-checked' : ''}"></div>
                    </div>

                    <p class="caw-px-desc">${p.description}</p>

                    <div class="caw-px-table">
                        <div class="caw-px-th-row">
                            <span>Instruments</span>
                            <span>Max leverage</span>
                        </div>
                        ${instRowsHtml}
                    </div>

                    <span class="caw-px-sec-title">Features</span>
                    <ul class="caw-px-list">
                        ${featuresHtml}
                    </ul>

                    <span class="caw-px-sec-title" style="margin-top:12px;">Bonuses</span>
                    <ul class="caw-px-list">
                        ${bonusesHtml}
                    </ul>
                </div>

                <div class="caw-px-foot">
                    <span class="caw-px-min-deposit">${p.minDepositText}</span>
                </div>
            </div>
        `;
    }).join('');

    container.querySelectorAll('.caw-px-card[data-caw-plan]').forEach(card => {
        card.addEventListener('click', () => {
            const planKey = card.dataset.cawPlan;
            if (typeof window.setCawPlanKey === 'function') {
                window.setCawPlanKey(planKey);
            }
        });
    });
}

function updateCawReviewDetails() {
    const isDemo = globalSelectedAccountType === 'demo';
    const isKryvex = globalSelectedBrokerChoice === 'kryvex';
    const broker = globalSelectedBrokerChoice === 'mt5' ? 'mt5' : 'kryvex';
    const plans = CAW_PLANS[broker] || CAW_PLANS.kryvex;
    const plan = plans.find(p => p.key === globalSelectedPlanKey) || plans[0];

    const revEnv = document.getElementById('cawReviewEnv');
    if (revEnv) {
        revEnv.textContent = isDemo ? 'Demo Account (Virtual Funds)' : 'Live Account (Real Funds)';
        revEnv.style.color = isDemo ? '#38bdf8' : '#34d399';
    }

    const revBroker = document.getElementById('cawReviewBroker');
    if (revBroker) {
        revBroker.textContent = isKryvex ? 'Kryvex Exchange' : 'MetaTrader 5 (MT5)';
    }

    const revPlan = document.getElementById('cawReviewPlan');
    if (revPlan) {
        revPlan.textContent = (isDemo && isKryvex)
            ? 'Demo Simulator (No Tier Required)'
            : (plan ? `${plan.name} (${plan.badge})` : 'Standard Plan');
    }

    const revSpecs = document.getElementById('cawReviewSpecs');
    if (revSpecs) {
        revSpecs.textContent = (isDemo && isKryvex)
            ? '$50,000 Virtual Balance · Risk-Free Simulator'
            : (plan ? `Min ${plan.minBalance} · ${plan.leverage} Leverage · ${plan.spread} Spread` : '');
    }

    const revBonus = document.getElementById('cawReviewBonus');
    if (revBonus) {
        if (isDemo && isKryvex) {
            revBonus.textContent = 'None (Simulator)';
            revBonus.style.color = '#94a3b8';
        } else if (plan) {
            revBonus.textContent = plan.bonus;
            revBonus.style.color = '#fbbf24';
        }
    }

    const nameInput = document.getElementById('cawAccountNameInput');
    if (nameInput) {
        nameInput.placeholder = isDemo
            ? 'e.g. My Demo Account, Test Trading'
            : (isKryvex ? 'e.g. Scalping Acc, Main Trading, Crypto Spot' : 'e.g. MT5 Live, Standard Bot, Forex Scalper');
    }
}

function goToCawStep(stepNumber) {
    if (stepNumber < 1) stepNumber = 1;
    if (stepNumber > 3) stepNumber = 3;
    currentCawStep = stepNumber;
    window.currentCawStep = stepNumber;

    for (let i = 1; i <= 3; i++) {
        const viewEl = document.getElementById(`cawStepView${i}`);
        if (viewEl) {
            viewEl.style.display = i === stepNumber ? 'block' : 'none';
        }
    }

    updateCawStepper(stepNumber);

    if (stepNumber === 2) {
        renderCawPlans();
    }

    if (stepNumber === 3) {
        updateCawReviewDetails();
        const nameInput = document.getElementById('cawAccountNameInput');
        if (nameInput) setTimeout(() => nameInput.focus(), 80);
    }

    syncCawDemoRestrictions();

    const modalTitle = document.getElementById('cawModalTitle');
    const modalSubtitle = document.getElementById('cawModalSubtitle');
    if (modalTitle && modalSubtitle) {
        if (stepNumber === 1) {
            modalTitle.textContent = 'Create New Trading Account';
            modalSubtitle.textContent = 'Choose your environment, broker and plan to get started.';
        } else if (stepNumber === 2) {
            modalTitle.textContent = 'Select Broker & Plan';
            modalSubtitle.textContent = 'Choose your preferred broker platform and trading account tier.';
        } else if (stepNumber === 3) {
            modalTitle.textContent = 'Review & Confirm Account';
            modalSubtitle.textContent = 'Review your account details and configure an optional account name.';
        }
    }

    const btnBack = document.getElementById('cawBtnFooterBack');
    const nextLabel = document.getElementById('cawBtnNextLabel');
    const nextIcon = document.getElementById('cawBtnNextIcon');

    if (btnBack) {
        btnBack.textContent = stepNumber === 1 ? 'Cancel' : '← Back';
    }

    if (nextLabel) {
        nextLabel.textContent = stepNumber === 3 ? 'Create Account' : 'Next Step';
    }

    if (nextIcon) {
        if (stepNumber === 3) {
            nextIcon.innerHTML = '<polyline points="20 6 9 17 4 12"></polyline>';
        } else {
            nextIcon.innerHTML = '<line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>';
        }
    }

    const mainArea = document.querySelector('.caw-main');
    if (mainArea) {
        mainArea.scrollTop = 0;
    }
}
window.goToCawStep = goToCawStep;
window.currentCawStep = currentCawStep;

function syncCawCardsSelection() {
    // 1. Account Environment Toggle Buttons & Cards
    document.querySelectorAll('[data-caw-env-btn]').forEach(btn => {
        btn.classList.toggle('is-active', btn.dataset.cawEnvBtn === globalSelectedAccountType);
    });

    document.querySelectorAll('[data-caw-mini-env]').forEach(btn => {
        btn.classList.toggle('is-active', btn.dataset.cawMiniEnv === globalSelectedAccountType);
    });

    document.querySelectorAll('.caw-card[data-caw-env]').forEach(c => {
        const isSel = c.dataset.cawEnv === globalSelectedAccountType;
        c.classList.toggle('is-selected', isSel);
        const radio = c.querySelector('.caw-radio');
        if (radio) radio.classList.toggle('is-checked', isSel);
    });

    // 2. Broker Choice Cards
    document.querySelectorAll('.caw-card[data-caw-broker]').forEach(c => {
        const isSel = c.dataset.cawBroker === globalSelectedBrokerChoice;
        c.classList.toggle('is-selected', isSel);
        const radio = c.querySelector('.caw-radio');
        if (radio) radio.classList.toggle('is-checked', isSel);
    });

    // 3. Plan Choice Cards
    renderCawPlans();

    // 4. Demo Restrictions
    syncCawDemoRestrictions();
}
window.syncCawCardsSelection = syncCawCardsSelection;

function openBrokerModal(options = {}) {
    const backdrop = document.getElementById('brokerModalBackdrop');
    const modal1 = document.getElementById('modalChooseBroker');
    const modal3 = document.getElementById('modalMt5Requested');

    if (!backdrop || !modal1) {
        setTimeout(() => openBrokerModal(options), 120);
        return;
    }

    globalIsCreateAccountMode = options.isCreateAccount !== false;
    globalSelectedAccountType = options.defaultEnv || 'live';
    globalSelectedBrokerChoice = options.defaultBroker || 'kryvex';
    globalSelectedPlanKey = options.defaultPlan || 'basic';

    goToCawStep(1);
    syncCawCardsSelection();
    syncCawDemoRestrictions();

    if (typeof window.loadNavbarTradingAccounts === 'function') {
        try {
            window.loadNavbarTradingAccounts(true).then(() => {
                syncCawDemoRestrictions();
            }).catch(() => {});
        } catch (e) {}
    }

    const accNameInput = document.getElementById('cawAccountNameInput');
    if (accNameInput) accNameInput.value = '';

    backdrop.removeAttribute('hidden');
    backdrop.hidden = false;
    backdrop.classList.add('is-open');
    backdrop.style.display = 'flex';
    backdrop.style.zIndex = '9999999';

    modal1.removeAttribute('hidden');
    modal1.hidden = false;
    modal1.classList.add('is-open');
    backdrop.style.display = 'flex';
    backdrop.style.zIndex = '9999999';

    modal1.removeAttribute('hidden');
    modal1.hidden = false;
    modal1.classList.add('is-open');
    modal1.style.display = 'flex';

    const cawMain = modal1.querySelector('.caw-main');
    if (cawMain) {
        cawMain.setAttribute('data-lenis-prevent', 'true');
        cawMain.setAttribute('data-no-lenis', 'true');
    }

    if (modal3) {
        modal3.setAttribute('hidden', 'true');
        modal3.hidden = true;
        modal3.classList.remove('is-open');
        modal3.style.display = 'none';
    }

    if (!globalBrokerStatusData) {
        loadGlobalBrokerStatus();
    }
}
window.openBrokerModal = openBrokerModal;

function closeBrokerModals() {
    const backdrop = document.getElementById('brokerModalBackdrop');
    if (backdrop) {
        backdrop.setAttribute('hidden', 'true');
        backdrop.hidden = true;
        backdrop.classList.remove('is-open');
        backdrop.style.display = 'none';
    }
}
window.closeBrokerModals = closeBrokerModals;
window.submitGlobalPlanRequest = submitGlobalPlanRequest;

function setupGlobalBrokerModals() {
    if (window.__globalBrokerModalsBound) return;
    window.__globalBrokerModalsBound = true;

    // Document-level event listener for modal click delegates
    document.addEventListener('click', (e) => {
        // Open modal triggers
        const openTrigger = e.target.closest('#navDropCreateAccountBtn, #btnOpenCreateSubAccountModal, #navBtnOpenCreateAccountModal, #btnOpenCreateAccountModal, #btnOpenBrokerModal, #btnOpenBrokerModalMain, #btnBannerChooseBroker, [data-open-create-account], [data-open-broker-modal]');
        if (openTrigger) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.closeNavbarBalanceDropdown === 'function') {
                window.closeNavbarBalanceDropdown();
            }
            const accDrop = document.getElementById('navFuturesAccountsDropdown');
            if (accDrop) accDrop.classList.remove('is-open');

            const isCreate = openTrigger.dataset.openCreateAccount !== 'false';
            const defaultEnv = openTrigger.dataset.defaultEnv || 'live';
            const defaultBroker = openTrigger.dataset.defaultBroker || 'kryvex';
            const defaultPlan = openTrigger.dataset.defaultPlan || 'basic';

            openBrokerModal({
                isCreateAccount: isCreate,
                defaultEnv: defaultEnv,
                defaultBroker: defaultBroker,
                defaultPlan: defaultPlan
            });
            return;
        }

        // Close modal triggers
        const closeTrigger = e.target.closest('[data-close-broker-modal], [data-skip-broker-modal], .caw-close-btn');
        if (closeTrigger) {
            e.preventDefault();
            closeBrokerModals();
            return;
        }

        // Environment toggle pills
        const envPill = e.target.closest('[data-caw-env-btn]');
        if (envPill) {
            e.preventDefault();
            const env = envPill.dataset.cawEnvBtn || 'live';
            if (typeof window.setCawAccountType === 'function') {
                window.setCawAccountType(env);
            }
            return;
        }

        // Environment cards
        const envCard = e.target.closest('.caw-card[data-caw-env]');
        if (envCard) {
            e.preventDefault();
            const env = envCard.dataset.cawEnv || 'live';
            if (typeof window.setCawAccountType === 'function') {
                window.setCawAccountType(env);
            }
            return;
        }

        // Broker cards
        const brokerCard = e.target.closest('.caw-card[data-caw-broker]');
        if (brokerCard) {
            e.preventDefault();
            const broker = brokerCard.dataset.cawBroker || 'kryvex';
            if (typeof window.setCawBrokerChoice === 'function') {
                window.setCawBrokerChoice(broker);
            }
            return;
        }

        // Plan cards
        const planCard = e.target.closest('.caw-px-card[data-caw-plan]');
        if (planCard) {
            e.preventDefault();
            const planKey = planCard.dataset.cawPlan || 'basic';
            if (typeof window.setCawPlanKey === 'function') {
                window.setCawPlanKey(planKey);
            }
            return;
        }

        // Stepper tabs
        const stepNav = e.target.closest('.caw-step[data-caw-step]');
        if (stepNav) {
            e.preventDefault();
            const stepNum = parseInt(stepNav.dataset.cawStep, 10);
            if (stepNum >= 1 && stepNum <= 3 && typeof window.goToCawStep === 'function') {
                window.goToCawStep(stepNum);
            }
            return;
        }

        // Footer Back button
        const btnBack = e.target.closest('#cawBtnFooterBack');
        if (btnBack) {
            e.preventDefault();
            if (typeof window.handleCawFooterBack === 'function') {
                window.handleCawFooterBack();
            }
            return;
        }

        // Footer Next button
        const btnNext = e.target.closest('#cawBtnFooterNext, #cawBtnConfirmCreate');
        if (btnNext) {
            e.preventDefault();
            if (typeof window.handleCawFooterNext === 'function') {
                window.handleCawFooterNext();
            }
            return;
        }
    });
}
window.setupGlobalBrokerModals = setupGlobalBrokerModals;

function openAddFundsModal() {
    const modal = document.getElementById('globalAddFundsModal') || document.getElementById('futuresAddFundsModal');
    if (!modal) return;
    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';
    modal.style.zIndex = '999999';
}
window.openAddFundsModal = openAddFundsModal;
window.openFuturesAddFundsModal = openAddFundsModal;

function closeAddFundsModal() {
    const modals = [
        document.getElementById('globalAddFundsModal'),
        document.getElementById('futuresAddFundsModal'),
        document.getElementById('depositModalBackdrop'),
        document.getElementById('modalDepositPopup')
    ];
    modals.forEach(m => {
        if (m) {
            m.hidden = true;
            m.style.display = 'none';
        }
    });
}
window.closeAddFundsModal = closeAddFundsModal;
window.closeFuturesAddFundsModal = closeAddFundsModal;

function setupGlobalAddFundsModal() {
    const modal = document.getElementById('globalAddFundsModal') || document.getElementById('futuresAddFundsModal');
    if (!modal) return;

    const closeBtn = document.getElementById('closeGlobalAddFundsModal') || document.getElementById('closeFuturesAddFundsModal');
    const moneyBtn = document.getElementById('btnFundMoneyDeposit');
    const cryptoBtn = document.getElementById('btnFundCryptoDeposit');
    const buyCryptoBtn = document.getElementById('btnFundBuyCrypto');

    if (closeBtn && !closeBtn.dataset.boundAddFunds) {
        closeBtn.dataset.boundAddFunds = '1';
        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeAddFundsModal();
        });
    }

    if (!modal.dataset.boundAddFunds) {
        modal.dataset.boundAddFunds = '1';
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeAddFundsModal();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.hidden && modal.style.display !== 'none') {
                closeAddFundsModal();
            }
        });
    }

    // 1. Money Deposit -> Go directly to Step Deposit Page in Money Mode, NO POPUPS
    if (moneyBtn && !moneyBtn.dataset.boundAddFunds) {
        moneyBtn.dataset.boundAddFunds = '1';
        moneyBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeAddFundsModal();
            const isWalletPage = window.location.pathname.includes('/wallet');
            if (isWalletPage) {
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('wallet-ops', true);
                }
                if (typeof window.switchWalletInnerNav === 'function') {
                    window.switchWalletInnerNav('deposit');
                }
                if (typeof window.setStepDepositMode === 'function') {
                    window.setStepDepositMode('money');
                }
                document.getElementById('innerPanelDeposit')?.scrollIntoView({ behavior: 'smooth' });
                return;
            }
            window.location.href = '/wallet?tab=deposit&mode=money';
        });
    }

    // 2. Crypto Deposit -> Go directly to Step Deposit Page in Crypto Mode, NO POPUPS
    if (cryptoBtn && !cryptoBtn.dataset.boundAddFunds) {
        cryptoBtn.dataset.boundAddFunds = '1';
        cryptoBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeAddFundsModal();
            const isWalletPage = window.location.pathname.includes('/wallet');
            if (isWalletPage) {
                if (typeof window.switchMainTab === 'function') {
                    window.switchMainTab('wallet-ops', true);
                }
                if (typeof window.switchWalletInnerNav === 'function') {
                    window.switchWalletInnerNav('deposit');
                }
                if (typeof window.setStepDepositMode === 'function') {
                    window.setStepDepositMode('crypto');
                }
                document.getElementById('innerPanelDeposit')?.scrollIntoView({ behavior: 'smooth' });
                return;
            }
            window.location.href = '/wallet?tab=deposit&mode=crypto';
        });
    }

    // 3. Buy Crypto -> Direct to exchange / instant buy
    if (buyCryptoBtn && !buyCryptoBtn.dataset.boundAddFunds) {
        buyCryptoBtn.dataset.boundAddFunds = '1';
        buyCryptoBtn.addEventListener('click', (e) => {
            e.preventDefault();
            closeAddFundsModal();
            const isWalletPage = window.location.pathname.includes('/wallet');
            if (isWalletPage && typeof window.switchMainTab === 'function') {
                window.switchMainTab('exchange', true);
                if (typeof window.resetSwapToDirectMode === 'function') {
                    window.resetSwapToDirectMode();
                }
                return;
            }
            window.location.href = '/wallet/exchange';
        });
    }
}
window.setupGlobalAddFundsModal = setupGlobalAddFundsModal;
