// User profile/dashboard logic.
// Handles profile loading, manual password change, and profile forgot-password reset.
// Manual password change never starts OTP timers. Forgot-password OTP must be verified before password fields appear.

document.addEventListener('DOMContentLoaded', () => {
    loadDashboardUser();
    loadBrokerStatus();
    setupBrokerModals();
    setupChangePasswordForm();
    setupProfileForgotPasswordForm();
    setupNameEditing();
});

let profileForgotOtpTimer = null;
let profileForgotResetToken = '';
let profileForgotCodeVerified = false;
let currentProfileForgotStep = 'email';

async function loadDashboardUser() {
    const token = localStorage.getItem('kryvex_access_token');

    if (!token) {
        const isProtectedPage = window.location.pathname.startsWith('/settings') || window.location.pathname.startsWith('/dashboard') || window.location.pathname.startsWith('/wallet');
        if (isProtectedPage) {
            window.location.href = '/login';
        }
        return;
    }

    try {
        const response = await fetch('/api/auth/me', {
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${token}`,
            },
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Unable to load user.');
        }

        const user = data.data.user || {};
        localStorage.setItem('kryvex_user', JSON.stringify(user));

        const name = user.name || 'Kryvex User';
        const email = user.email || '--';
        const clientId = user.client_id || '--';
        const kycStatus = user.kyc_status || user.kyc || 'pending';
        const accountStatus = user.status || 'active';
        const emailVerified = user.email_verified || Boolean(user.email_verified_at);
        const uid = user.uid || user.id || user._id || '--';
        const loginType = user.google_id ? 'Google Login' : 'Email Login';

        setText('profileName', name);
        setText('profileEmail', email);
        setText('profileClientId', clientId);
        setText('profileAvatar', String(name || email || 'U').charAt(0).toUpperCase());
        setText('profileStatusTag', `Status: ${capitalize(accountStatus)}`);
        setText('profileKycTag', `KYC: ${capitalize(kycStatus)}`);
        setText('profileEmailTag', `Email: ${emailVerified ? 'Verified' : 'Unverified'}`);

        setText('dashName', name);
        setText('dashEmail', email);
        setText('dashClientId', clientId);
        setText('dashKyc', capitalize(kycStatus));
        setText('dashEmailVerified', emailVerified ? 'Yes' : 'No');
        setText('dashUid', uid);
        setText('dashLoginType', loginType);
        setText('securityEmailStatus', emailVerified ? 'Verified' : 'Unverified');
        setText('securityKycStatus', capitalize(kycStatus));

        const profileForgotEmail = document.getElementById('profileForgotEmail');
        if (profileForgotEmail && email !== '--') {
            profileForgotEmail.value = email;
        }

        setTagState('profileStatusTag', accountStatus);
        setTagState('profileKycTag', kycStatus);
        setTagState('profileEmailTag', emailVerified ? 'verified' : 'unverified');

        // Load base currency for the profile card
        loadBaseCurrency();
    } catch (error) {
        localStorage.removeItem('kryvex_access_token');
        localStorage.removeItem('kryvex_refresh_token');
        localStorage.removeItem('kryvex_user');
        localStorage.removeItem('kryvex_last_activity');

        window.location.href = '/login';
    }
}



function setupChangePasswordForm() {
    const form = document.getElementById('changePasswordForm');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const token = localStorage.getItem('kryvex_access_token');
        const payload = formToObject(form);

        showDashboardAlert('Changing password...', 'success');

        try {
            const response = await fetch('/api/auth/change-password', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(getDashboardApiError(data, 'Unable to change password.'));
            }

            form.reset();
            showDashboardAlert(data.message || 'Password changed successfully.', 'success');
        } catch (error) {
            showDashboardAlert(error.message || 'Something went wrong.', 'error');
        }
    });
}

function setupProfileForgotPasswordForm() {
    const form = document.getElementById('profileForgotPasswordForm');
    const openButton = document.getElementById('openProfileForgotPassword');
    const sendButton = document.getElementById('profileForgotSendCodeButton');
    const resendButton = document.getElementById('profileForgotResendButton');
    const verifyButton = document.getElementById('profileForgotVerifyCodeButton');
    const codeInput = form?.querySelector('input[name="code"]');

    if (!form) return;

    // Custom step submit supports Enter key on email/code/password screens.
    form.setAttribute('novalidate', 'novalidate');
    showProfileForgotStep('email');

    if (openButton) {
        openButton.addEventListener('click', () => {
            const changePasswordForm = document.getElementById('changePasswordForm');
            const isHidden = form.style.display === 'none' || !form.style.display;

            form.style.display = isHidden ? 'grid' : 'none';
            if (changePasswordForm) changePasswordForm.style.display = isHidden ? 'none' : 'grid';

            if (isHidden) {
                resetProfileForgotUi(false);
                showProfileForgotStep('email');
                showDashboardAlert('Enter your email to receive a password reset code.', 'success');
            } else {
                stopProfileForgotOtpTimer();
            }
        });
    }

    if (codeInput) {
        codeInput.addEventListener('input', () => {
            codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    if (sendButton) {
        sendButton.addEventListener('click', () => sendProfileForgotCode(form));
    }

    if (resendButton) {
        resendButton.addEventListener('click', () => sendProfileForgotCode(form, true));
    }

    if (verifyButton) {
        verifyButton.addEventListener('click', () => verifyProfileForgotCode(form));
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (currentProfileForgotStep === 'email') {
            await sendProfileForgotCode(form);
            return;
        }

        if (currentProfileForgotStep === 'code') {
            await verifyProfileForgotCode(form);
            return;
        }

        await submitProfileForgotReset(form);
    });
}

function resetProfileForgotUi(clearEmail = true) {
    profileForgotResetToken = '';
    profileForgotCodeVerified = false;
    stopProfileForgotOtpTimer();

    const form = document.getElementById('profileForgotPasswordForm');
    const tokenInput = document.getElementById('profileForgotResetToken');

    if (tokenInput) tokenInput.value = '';
    if (!form) return;

    const emailInput = form.querySelector('input[name="email"]');
    const currentEmail = emailInput?.value || '';

    const codeInput = form.querySelector('input[name="code"]');
    const passwordInput = form.querySelector('input[name="password"]');
    const confirmInput = form.querySelector('input[name="password_confirmation"]');

    if (codeInput) codeInput.value = '';
    if (passwordInput) passwordInput.value = '';
    if (confirmInput) confirmInput.value = '';

    if (clearEmail && emailInput) {
        emailInput.value = '';
    } else if (emailInput) {
        emailInput.value = currentEmail;
    }

    showProfileForgotStep('email');
}

function showProfileForgotStep(step) {
    currentProfileForgotStep = step;

    const emailSection = document.getElementById('profileForgotEmailSection');
    const codeSection = document.getElementById('profileForgotCodeSection');
    const passwordSection = document.getElementById('profileForgotPasswordSection');
    const subtitle = document.getElementById('profileForgotStepSubtitle');

    const stepMap = {
        email: emailSection,
        code: codeSection,
        password: passwordSection,
    };

    Object.entries(stepMap).forEach(([name, section]) => {
        if (!section) return;

        const active = name === step;
        section.classList.toggle('is-active', active);
        section.hidden = !active;
        section.setAttribute('aria-hidden', active ? 'false' : 'true');

        // Dashboard forgot password uses the same real-app step flow:
        // Email -> OTP -> New password. Only one step can be visible.
        section.style.setProperty('display', active ? 'grid' : 'none', 'important');
    });

    const form = document.getElementById('profileForgotPasswordForm');
    if (form) {
        form.dataset.step = step;
    }

    if (subtitle) {
        subtitle.textContent = step === 'email'
            ? 'Enter your email to receive a 6 digit reset code.'
            : step === 'code'
                ? 'Enter the reset code within 120 seconds.'
                : 'Code verified. Set your new password.';
    }

    focusProfileForgotStepInput(step);
}

function focusProfileForgotStepInput(step) {
    const selectors = {
        email: '#profileForgotEmailSection input[name="email"]',
        code: '#profileForgotCodeSection input[name="code"]',
        password: '#profileForgotPasswordSection input[name="password"]',
    };

    const input = document.querySelector(selectors[step]);

    if (input) {
        setTimeout(() => input.focus(), 80);
    }
}

async function sendProfileForgotCode(form, isResend = false) {
    const email = String(form.querySelector('input[name="email"]')?.value || '').trim().toLowerCase();

    if (!email) {
        showDashboardAlert('Please enter your registered email.', 'error');
        return;
    }

    showDashboardAlert(isResend ? 'Resending reset code...' : 'Sending reset code...', 'success');

    try {
        const response = await fetch('/api/auth/forgot-password/send-code', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email }),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(getDashboardApiError(data, 'Unable to send reset code.'));
        }

        profileForgotResetToken = '';
        profileForgotCodeVerified = false;

        const tokenInput = document.getElementById('profileForgotResetToken');

        if (tokenInput) tokenInput.value = '';

        showProfileForgotStep('code');
        startProfileForgotOtpTimer(data.data?.expires_in_seconds || 120);

        showDashboardAlert(data.message || 'Reset code sent.', 'success');
    } catch (error) {
        showDashboardAlert(error.message || 'Something went wrong.', 'error');
    }
}

async function verifyProfileForgotCode(form) {
    const payload = {
        email: String(form.querySelector('input[name="email"]')?.value || '').trim().toLowerCase(),
        code: String(form.querySelector('input[name="code"]')?.value || '').trim(),
    };

    if (!payload.email || !payload.code) {
        showDashboardAlert('Please enter email and reset code.', 'error');
        return;
    }

    showDashboardAlert('Verifying reset code...', 'success');

    try {
        const response = await fetch('/api/auth/forgot-password/verify-code', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(getDashboardApiError(data, 'Unable to verify reset code.'));
        }

        profileForgotCodeVerified = true;
        profileForgotResetToken = data.data?.reset_token || '';
        stopProfileForgotOtpTimer();

        const tokenInput = document.getElementById('profileForgotResetToken');

        if (tokenInput) tokenInput.value = profileForgotResetToken;

        showProfileForgotStep('password');
        showDashboardAlert(data.message || 'Code verified. Set your new password.', 'success');
    } catch (error) {
        showDashboardAlert(error.message || 'Something went wrong.', 'error');
    }
}

async function submitProfileForgotReset(form) {
    const payload = formToObject(form);
    payload.reset_token = payload.reset_token || profileForgotResetToken;

    if (!profileForgotCodeVerified || !payload.reset_token) {
        showDashboardAlert('Please verify your reset code first.', 'error');
        return;
    }

    showDashboardAlert('Resetting password...', 'success');

    try {
        const response = await fetch('/api/auth/forgot-password/reset', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(getDashboardApiError(data, 'Unable to reset password.'));
        }

        const email = form.querySelector('input[name="email"]')?.value || '';
        form.reset();
        const emailInput = form.querySelector('input[name="email"]');
        if (emailInput) emailInput.value = email;
        resetProfileForgotUi(false);
        form.style.display = 'none';

        const changePasswordForm = document.getElementById('changePasswordForm');
        if (changePasswordForm) changePasswordForm.style.display = 'grid';

        showDashboardAlert(data.message || 'Password reset successfully.', 'success');
    } catch (error) {
        showDashboardAlert(error.message || 'Something went wrong.', 'error');
    }
}

function startProfileForgotOtpTimer(seconds) {
    const timer = document.getElementById('profileForgotOtpTimer');
    const verifyButton = document.getElementById('profileForgotVerifyCodeButton');
    if (!timer) return;

    if (profileForgotOtpTimer) clearInterval(profileForgotOtpTimer);
    let remaining = Number(seconds || 120);
    timer.textContent = `${remaining}s`;
    if (verifyButton) verifyButton.disabled = false;

    profileForgotOtpTimer = setInterval(() => {
        remaining -= 1;
        timer.textContent = `${Math.max(remaining, 0)}s`;

        if (remaining <= 0) {
            stopProfileForgotOtpTimer();
            if (verifyButton) verifyButton.disabled = true;
            showDashboardAlert('Password reset code expired. Please resend a new code.', 'error');
        }
    }, 1000);
}

function stopProfileForgotOtpTimer() {
    if (profileForgotOtpTimer) clearInterval(profileForgotOtpTimer);
    profileForgotOtpTimer = null;
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value;
    }
}

function setTagState(id, value) {
    const element = document.getElementById(id);
    if (!element) return;

    element.classList.remove('is-green', 'is-red', 'is-yellow');
    const normalized = String(value || '').toLowerCase();

    if (['active', 'verified', 'approved', 'completed'].includes(normalized)) {
        element.classList.add('is-green');
    } else if (['blocked', 'suspended', 'rejected', 'unverified'].includes(normalized)) {
        element.classList.add('is-red');
    } else {
        element.classList.add('is-yellow');
    }
}

function formToObject(form) {
    const formData = new FormData(form);
    const payload = {};

    formData.forEach((value, key) => {
        payload[key] = value;
    });

    return payload;
}

function showDashboardAlert(message, type = 'success') {
    const alert = document.getElementById('dashboardAlert');

    // Profile password actions use toast notifications so the page layout does not jump.
    if (alert) {
        alert.className = 'auth-alert';
        alert.textContent = '';
    }

    showDashboardToast(message, type);
}

function showDashboardToast(message, type = 'success') {
    if (typeof window.showToast === 'function') {
        window.showToast({
            type: type,
            title: type === 'success' ? 'Success' : type.charAt(0).toUpperCase() + type.slice(1),
            message: message
        });
    }
}

function escapeDashboardHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('\"', '&quot;')
        .replaceAll("'", '&#039;');
}

function getDashboardApiError(data, fallback) {
    if (data?.message) return data.message;

    if (data?.errors) {
        const firstKey = Object.keys(data.errors)[0];
        if (firstKey && Array.isArray(data.errors[firstKey])) {
            return data.errors[firstKey][0];
        }
    }

    return fallback;
}

function capitalize(value) {
    const text = String(value || '--');
    return text.charAt(0).toUpperCase() + text.slice(1).replaceAll('_', ' ');
}

function setupNameEditing() {
    const editBtn = document.getElementById('editNameButton');
    const cancelBtn = document.getElementById('cancelNameButton');
    const saveBtn = document.getElementById('saveNameButton');
    const displayRow = document.getElementById('nameDisplayRow');
    const editRow = document.getElementById('nameEditRow');
    const nameInput = document.getElementById('editNameInput');
    const dashName = document.getElementById('dashName');

    if (!editBtn || !cancelBtn || !saveBtn || !displayRow || !editRow || !nameInput || !dashName) {
        return;
    }

    editBtn.addEventListener('click', () => {
        nameInput.value = dashName.textContent === '--' ? '' : dashName.textContent;
        displayRow.style.display = 'none';
        editRow.style.display = 'flex';
        nameInput.focus();
    });

    cancelBtn.addEventListener('click', () => {
        displayRow.style.display = 'flex';
        editRow.style.display = 'none';
    });

    saveBtn.addEventListener('click', async () => {
        const newName = nameInput.value.trim();
        if (newName.length < 2) {
            showDashboardToast('Name must be at least 2 characters long.', 'error');
            return;
        }

        saveBtn.disabled = true;
        saveBtn.textContent = 'Saving...';

        const token = localStorage.getItem('kryvex_access_token');

        try {
            const response = await fetch('/api/auth/update-profile', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify({ name: newName }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Unable to update profile.');
            }

            const freshUser = data.data.user || {};
            localStorage.setItem('kryvex_user', JSON.stringify(freshUser));

            // Update UI elements
            setText('dashName', freshUser.name);
            setText('profileName', freshUser.name);
            setText('profileAvatar', String(freshUser.name || 'U').charAt(0).toUpperCase());

            // Update global navbar if function exists
            if (typeof window.setNavbarUserName === 'function') {
                window.setNavbarUserName();
            }

            showDashboardToast(data.message || 'Profile name updated.', 'success');

            displayRow.style.display = 'flex';
            editRow.style.display = 'none';
        } catch (error) {
            showDashboardToast(error.message || 'Unable to update name.', 'error');
        } finally {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save';
        }
    });
}

// ===== BASE CURRENCY FUNCTIONS =====

async function loadBaseCurrency() {
    const token = localStorage.getItem('kryvex_access_token');
    if (!token) return;

    const loadingEl = document.getElementById('baseCurrencyLoadingState');
    const setDisplay = document.getElementById('baseCurrencySetDisplay');
    const notSetDisplay = document.getElementById('baseCurrencyNotSetDisplay');
    const valueEl = document.getElementById('dashBaseCurrency');
    const blockedBanner = document.getElementById('currencyKycBlockedBanner');
    const baseCurrencyCard = document.getElementById('baseCurrencyCard');

    if (!loadingEl) return; // Not on settings page

    // Check user KYC status first
    try {
        const userStr = localStorage.getItem('kryvex_user');
        const user = userStr ? JSON.parse(userStr) : {};
        const kycStatus = (user.kyc_status || '').toLowerCase();
        const isKycVerified = ['verified', 'approved'].includes(kycStatus);

        if (!isKycVerified) {
            if (loadingEl) loadingEl.style.display = 'none';
            if (blockedBanner) blockedBanner.style.display = 'block';
            if (baseCurrencyCard) baseCurrencyCard.style.opacity = '0.5';
            if (setDisplay) setDisplay.style.display = 'none';
            if (notSetDisplay) notSetDisplay.style.display = 'none';
            return;
        } else {
            if (blockedBanner) blockedBanner.style.display = 'none';
            if (baseCurrencyCard) baseCurrencyCard.style.opacity = '1';
        }
    } catch (e) {}

    try {
        const response = await fetch('/api/auth/base-currency', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        });
        const data = await response.json();

        if (loadingEl) loadingEl.style.display = 'none';

        if (data.success && data.data.is_set) {
            const cur = data.data.base_currency;
            if (valueEl) valueEl.textContent = cur === 'INR' ? '🇮🇳 INR — Indian Rupee' : '🇺🇸 USDT — Tether Dollar';
            if (setDisplay) setDisplay.style.display = 'block';
            if (notSetDisplay) notSetDisplay.style.display = 'none';

            // Check if there is an active pending currency change request
            checkPendingCurrencyRequest();
        } else {
            if (setDisplay) setDisplay.style.display = 'none';
            if (notSetDisplay) notSetDisplay.style.display = 'block';
        }
    } catch (e) {
        if (loadingEl) loadingEl.textContent = 'Unable to load';
    }
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function checkPendingCurrencyRequest() {
    const token = localStorage.getItem('kryvex_access_token');
    const badge = document.getElementById('currencyPendingBadge');
    const btn = document.getElementById('openCurrencyChangeRequestBtn');

    if (!token) return;

    try {
        const response = await fetch('/api/auth/currency-change-status', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        });
        const data = await response.json();

        if (data.success && data.data.pending_request) {
            const req = data.data.pending_request;
            if (badge) {
                badge.style.display = 'block';
                badge.textContent = `⏳ Currency Change Requested (${req.current_currency} → ${req.requested_currency}) - Pending Admin Approval`;
            }
            if (btn) btn.style.display = 'none';
        } else {
            if (badge) badge.style.display = 'none';
            if (btn) btn.style.display = 'inline-block';
        }
    } catch (e) {}
}

async function openCurrencyChangeModal() {
    const modal = document.getElementById('currencyChangeModal');
    const alertEl = document.getElementById('currencyChangeAlert');
    const submitBtn = document.getElementById('submitCurrencyChangeBtn');

    if (alertEl) alertEl.style.display = 'none';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.4';
    }

    if (modal) modal.style.display = 'flex';
    await loadCurrencyChangeStatus();
}

function closeCurrencyChangeModal() {
    const modal = document.getElementById('currencyChangeModal');
    if (modal) modal.style.display = 'none';
}

async function loadCurrencyChangeStatus() {
    const token = localStorage.getItem('kryvex_access_token');
    const listEl = document.getElementById('currencyChangeChecklist');
    const submitBtn = document.getElementById('submitCurrencyChangeBtn');

    if (!token || !listEl) return;

    listEl.innerHTML = '<div style="color:var(--kv-muted);font-size:0.85rem;">Checking system status...</div>';

    try {
        const response = await fetch('/api/auth/currency-change-status', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        });
        const resData = await response.json();

        if (!resData.success) {
            listEl.innerHTML = `<div style="color:#ef4444;font-size:0.85rem;">${resData.message || 'Failed to load status.'}</div>`;
            return;
        }

        const d = resData.data;

        // Populate preview fields
        setText('ccCurrentCurrency', d.current_currency === 'INR' ? '🇮🇳 INR' : '🇺🇸 USDT');
        setText('ccRequestedCurrency', d.requested_currency === 'INR' ? '🇮🇳 INR' : '🇺🇸 USDT');
        setText('ccCurrentBalance', d.current_balance_formatted);
        setText('ccExchangeRate', `${d.rate_type || 'Rate'}: 1 USDT = \u20b9${Number(d.rate).toFixed(2)}`);
        setText('ccReceiveBalance', d.receive_balance_formatted);

        // Populate checklist items
        const checks = d.checks || [];
        let html = '';

        checks.forEach((item) => {
            const icon = item.passed ? '<span style="color:#22c55e;font-weight:700;">✔</span>' : '<span style="color:#ef4444;font-weight:700;">✖</span>';
            const color = item.passed ? 'rgba(255,255,255,0.85)' : '#ef4444';
            html += `
                <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:5px 0;border-bottom:1px solid rgba(255,255,255,0.04);font-size:0.82rem;">
                    <div style="display:flex;align-items:center;gap:8px;">
                        ${icon}
                        <span style="color:${color};">${escapeHtml(item.label)}</span>
                    </div>
                    <small style="color:${item.passed ? 'var(--kv-muted)' : '#ef4444'};">${escapeHtml(item.message)}</small>
                </div>
            `;
        });

        listEl.innerHTML = html;

        if (submitBtn) {
            submitBtn.disabled = !d.can_request;
            submitBtn.style.opacity = d.can_request ? '1' : '0.4';
        }
    } catch (e) {
        listEl.innerHTML = '<div style="color:#ef4444;font-size:0.85rem;">Error loading validation status.</div>';
    }
}

async function submitCurrencyChangeRequest() {
    const submitBtn = document.getElementById('submitCurrencyChangeBtn');
    const alertEl = document.getElementById('currencyChangeAlert');
    const token = localStorage.getItem('kryvex_access_token');

    if (!token || !submitBtn) return;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';

    if (alertEl) alertEl.style.display = 'none';

    try {
        const response = await fetch('/api/auth/request-currency-change', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${token}`,
            },
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to submit request.');
        }

        if (alertEl) {
            alertEl.style.display = 'block';
            alertEl.style.background = 'rgba(34,197,94,0.12)';
            alertEl.style.border = '1px solid rgba(34,197,94,0.4)';
            alertEl.style.color = '#22c55e';
            alertEl.textContent = data.message || 'Request submitted successfully!';
        }

        setTimeout(() => {
            closeCurrencyChangeModal();
            loadBaseCurrency();
        }, 1500);
    } catch (err) {
        if (alertEl) {
            alertEl.style.display = 'block';
            alertEl.style.background = 'rgba(239,68,68,0.12)';
            alertEl.style.border = '1px solid rgba(239,68,68,0.4)';
            alertEl.style.color = '#ef4444';
            alertEl.textContent = err.message || 'Submission failed.';
        }
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Request';
    }
}

function openCurrencyModal() {
    try {
        const userStr = localStorage.getItem('kryvex_user');
        const user = userStr ? JSON.parse(userStr) : {};
        const kycStatus = (user.kyc_status || '').toLowerCase();
        if (!['verified', 'approved'].includes(kycStatus)) {
            showDashboardToast('KYC verification is required before selecting your Base Currency. Please complete KYC verification first.', 'error');
            return;
        }
    } catch (e) {}

    const modal = document.getElementById('baseCurrencyModal');
    if (modal) {
        modal.style.display = 'flex';
        // Highlight radio options on change
        document.querySelectorAll('input[name="baseCurrencyChoice"]').forEach((radio) => {
            radio.addEventListener('change', () => {
                document.querySelectorAll('#currencyOptINR, #currencyOptUSDT').forEach(lbl => {
                    lbl.style.borderColor = 'rgba(255,255,255,0.1)';
                    lbl.style.background = 'transparent';
                });
                const selected = radio.closest('label');
                if (selected) {
                    selected.style.borderColor = 'var(--kv-yellow, #f6c93f)';
                    selected.style.background = 'rgba(246,201,63,0.06)';
                }
            });
        });
    }
}

function closeCurrencyModal() {
    const modal = document.getElementById('baseCurrencyModal');
    if (modal) modal.style.display = 'none';
}

async function confirmCurrencySelection() {
    const selected = document.querySelector('input[name="baseCurrencyChoice"]:checked');
    if (!selected) {
        alert('Please select a currency before confirming.');
        return;
    }

    const currency = selected.value;
    const confirmBtn = document.getElementById('confirmCurrencyBtn');
    if (confirmBtn) {
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Saving...';
    }

    const token = localStorage.getItem('kryvex_access_token');
    try {
        const response = await fetch('/api/auth/set-base-currency', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${token}`,
            },
            body: JSON.stringify({ base_currency: currency }),
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Failed to save.');
        }

        closeCurrencyModal();
        // Refresh card display
        loadBaseCurrency();
    } catch (error) {
        alert(error.message || 'Unable to save currency. Please try again.');
    } finally {
        if (confirmBtn) {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm Selection';
        }
    }
}

// Wire up modal buttons on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('openCurrencySelectBtn')?.addEventListener('click', openCurrencyModal);
    document.getElementById('closeCurrencyModalBtn')?.addEventListener('click', closeCurrencyModal);
    document.getElementById('skipCurrencyBtn')?.addEventListener('click', closeCurrencyModal);
    document.getElementById('confirmCurrencyBtn')?.addEventListener('click', confirmCurrencySelection);

    document.getElementById('openCurrencyChangeRequestBtn')?.addEventListener('click', openCurrencyChangeModal);
    document.getElementById('closeCurrencyChangeModalBtn')?.addEventListener('click', closeCurrencyChangeModal);
    document.getElementById('cancelCurrencyChangeBtn')?.addEventListener('click', closeCurrencyChangeModal);
    document.getElementById('submitCurrencyChangeBtn')?.addEventListener('click', submitCurrencyChangeRequest);

    document.getElementById('btnGoToKycFromCurrency')?.addEventListener('click', () => {
        const kycTabBtn = document.querySelector('[data-tab="kycSection"]');
        if (kycTabBtn) kycTabBtn.click();
    });

    // Close on backdrop click
    document.getElementById('baseCurrencyModal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeCurrencyModal();
    });
    document.getElementById('currencyChangeModal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeCurrencyChangeModal();
    });
});

// Expose so kyc.js and settings.blade.php can trigger it
window.openBaseCurrencyModal = openCurrencyModal;
if (!window.openBrokerModal) {
    window.openBrokerModal = openBrokerModal;
}

// ==========================================
// BROKER & METATRADER 5 (MT5) INTEGRATION
// ==========================================
let brokerStatusData = null;
let selectedMt5PlanKey = null;
let selectedBrokerChoice = 'kryvex';

async function loadBrokerStatus() {
    const token = localStorage.getItem('kryvex_access_token');
    if (!token) return;

    try {
        const response = await fetch('/api/broker/status', {
            headers: {
                Accept: 'application/json',
                Authorization: `Bearer ${token}`,
            },
        });
        const result = await response.json();
        if (!response.ok || !result.success) return;

        brokerStatusData = result.data || {};
        renderBrokerStatusUI();

        // Check onboarding flags from database and localStorage
        const brokerType = brokerStatusData.broker_type;
        const brokerPopupSeen = Number(brokerStatusData.broker_popup_seen || 0) === 1;
        const appPopupSeen = Number(brokerStatusData.app_popup_seen || 0) === 1;

        const isNewSignup = localStorage.getItem('kryvex_new_user_signup') === 'true';

        if (isNewSignup || (!brokerType && !brokerPopupSeen)) {
            localStorage.removeItem('kryvex_new_user_signup');
            isOnboardingSequenceActive = true;
            setTimeout(() => {
                openBrokerModal();
            }, 300);
        } else if (!appPopupSeen && isNewSignup) {
            localStorage.removeItem('kryvex_new_user_signup');
            setTimeout(() => {
                openInstallAppModal();
            }, 300);
        }
    } catch (e) {
        console.warn('Failed to load broker status:', e);
    }
}

function renderBrokerStatusUI() {
    if (!brokerStatusData) return;

    const brokerType = brokerStatusData.broker_type;
    const dashBrokerTypeEl = document.getElementById('dashBrokerType');
    const dashBrokerBadgeEl = document.getElementById('dashBrokerBadge');
    const dashBrokerPlanEl = document.getElementById('dashBrokerPlan');

    if (dashBrokerTypeEl) {
        if (!brokerType) {
            dashBrokerTypeEl.textContent = 'Not Selected';
            dashBrokerTypeEl.style.color = '#eab308';
            if (dashBrokerBadgeEl) {
                dashBrokerBadgeEl.innerHTML = '<span style="background: rgba(234, 179, 8, 0.15); border: 1px solid rgba(234, 179, 8, 0.35); color: #fef08a; padding: 2px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;">⚠️ ACTION REQUIRED</span>';
            }
            if (dashBrokerPlanEl) {
                dashBrokerPlanEl.innerHTML = '<div style="display: inline-flex; align-items: center; gap: 8px; background: rgba(234,179,8,0.08); border: 1px solid rgba(234,179,8,0.2); padding: 6px 12px; border-radius: 8px; font-size: 0.82rem; color: #fef08a;">Please select a broker to enable trading.</div>';
            }
        } else {
            const isKryvex = brokerType === 'kryvex';
            dashBrokerTypeEl.textContent = isKryvex ? 'Kryvex Exchange' : 'MetaTrader 5 (MT5)';
            dashBrokerTypeEl.style.color = '#ffffff';

            if (dashBrokerBadgeEl) {
                dashBrokerBadgeEl.innerHTML = '<span style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #10b981; padding: 2px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;">● ACTIVE BROKER</span>';
            }

            if (dashBrokerPlanEl) {
                const rawPlan = brokerStatusData.kryvex_plan || brokerStatusData.mt5_plan || brokerStatusData.highest_eligible_plan?.name || 'Standard';
                const planKeyLower = String(rawPlan).toLowerCase();
                const matchingPlan = (brokerStatusData.plans || []).find(p => String(p.plan_key).toLowerCase() === planKeyLower) || brokerStatusData.plans?.[0];
                const spreadText = matchingPlan?.spread_formatted || (planKeyLower === 'advanced' ? '0.01%' : (planKeyLower === 'standard' ? '0.03%' : '0.05%'));
                const planName = String(rawPlan).toUpperCase();

                if (isKryvex) {
                    const maxLev = matchingPlan?.max_leverage || brokerStatusData.highest_eligible_plan?.max_leverage || '1:100';
                    dashBrokerPlanEl.innerHTML = `
                        <div style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); padding: 6px 12px; border-radius: 8px; font-size: 0.82rem; color: #cbd5e1;">
                            <span style="background: rgba(244, 197, 66, 0.2); color: #f4c542; font-weight: 800; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem;">ACTIVE PLAN</span>
                            <strong style="color: #ffffff;">${escapeHtml(planName)} Plan</strong>
                            <span style="color: #94a3b8;">• Spread: <strong style="color: #f4c542;">${spreadText}</strong> • Up to ${escapeHtml(maxLev)} Leverage • Direct Kryvex Order Routing</span>
                        </div>
                    `;
                } else {
                    const server = brokerStatusData.active_account?.server || 'Kryvex-Live-01';
                    const loginId = brokerStatusData.active_account?.login || 'Pending Admin Approval';
                    dashBrokerPlanEl.innerHTML = `
                        <div style="display: inline-flex; align-items: center; gap: 8px; flex-wrap: wrap; background: rgba(59, 130, 246, 0.08); border: 1px solid rgba(59, 130, 246, 0.2); padding: 6px 12px; border-radius: 8px; font-size: 0.82rem; color: #cbd5e1;">
                            <span style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; font-weight: 800; padding: 2px 8px; border-radius: 4px; font-size: 0.72rem;">MT5 PLAN</span>
                            <strong style="color: #ffffff;">${escapeHtml(planName)} Account</strong>
                            <span style="color: #94a3b8;">• Spread: <strong style="color: #f4c542;">${spreadText}</strong> • Server: ${escapeHtml(server)} • ID: ${escapeHtml(loginId)}</span>
                        </div>
                    `;
                }
            }
        }
    }

    checkAndShowLowBalanceWarning();

    // Render reminder banner if broker_type is NULL
    let reminderBanner = document.getElementById('brokerReminderBanner');
    if (!brokerType) {
        if (!reminderBanner) {
            const settingsHeader = document.querySelector('.settings-section, .settings-page, main');
            if (settingsHeader) {
                reminderBanner = document.createElement('div');
                reminderBanner.id = 'brokerReminderBanner';
                reminderBanner.style.cssText = 'background: rgba(234, 179, 8, 0.12); border: 1px solid rgba(234, 179, 8, 0.3); padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; color: #fef08a;';
                reminderBanner.innerHTML = `
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 1.2rem;">⚠️</span>
                        <div>
                            <strong style="display: block; color: #fff; font-size: 0.95rem;">Trading Broker Not Selected</strong>
                            <span style="font-size: 0.85rem; opacity: 0.85;">Please choose Kryvex Exchange or MetaTrader 5 to enable demo trading.</span>
                        </div>
                    </div>
                    <button type="button" class="kv-btn kv-btn-primary" id="btnBannerChooseBroker" onclick="if(typeof openBrokerModal==='function'){openBrokerModal();}else if(window.openBrokerModal){window.openBrokerModal();}" style="height: 38px; font-weight: 700; font-size: 0.85rem; padding: 0 16px; cursor: pointer; position: relative; z-index: 10;">Choose Broker</button>
                `;
                settingsHeader.prepend(reminderBanner);
            }
            const btn = document.getElementById('btnBannerChooseBroker');
            if (btn) btn.onclick = openBrokerModal;
        } else {
            reminderBanner.style.display = 'flex';
        }
    } else if (reminderBanner) {
        reminderBanner.style.display = 'none';
    }

    const mt5Card = document.getElementById('mt5StatusCard');
    const activeAccount = brokerStatusData.active_account;
    const pendingRequest = brokerStatusData.pending_request;

    if (mt5Card) {
        if (brokerType === 'mt5') {
            mt5Card.hidden = false;
            mt5Card.removeAttribute('hidden');
            mt5Card.style.display = 'block';

            const badge = document.getElementById('mt5StatusBadge');
            const planEl = document.getElementById('mt5StatusPlan');
            const pendingNote = document.getElementById('mt5PendingNote');
            const connectedWrap = document.getElementById('mt5ConnectedViewWrap');
            const formWrap = document.getElementById('mt5CredentialsFormWrap');

            const viewLogin = document.getElementById('viewMt5Login');
            const viewPass = document.getElementById('viewMt5Password');
            const viewServer = document.getElementById('viewMt5Server');

            const loginInp = document.getElementById('userMt5Login');
            const passInp = document.getElementById('userMt5Password');
            const serverInp = document.getElementById('userMt5Server');

            if (activeAccount) {
                if (badge) { badge.className = 'mt5-badge active'; badge.textContent = 'Active'; }
                if (planEl) planEl.textContent = activeAccount.plan_name || 'Standard';
                if (pendingNote) { pendingNote.hidden = true; pendingNote.style.display = 'none'; }
                
                if (connectedWrap) {
                    connectedWrap.removeAttribute('hidden');
                    connectedWrap.style.display = 'block';
                }
                if (formWrap) {
                    formWrap.setAttribute('hidden', 'true');
                    formWrap.style.display = 'none';
                }

                if (viewLogin) viewLogin.textContent = activeAccount.login || '--';
                if (viewPass) {
                    viewPass.dataset.rawPass = activeAccount.password || '';
                    viewPass.textContent = '••••••••';
                }
                if (viewServer) viewServer.textContent = activeAccount.server || 'Kryvex-Live-01';

                if (loginInp && activeAccount.login) loginInp.value = activeAccount.login;
                if (passInp && activeAccount.password) passInp.value = activeAccount.password;
                if (serverInp && activeAccount.server) serverInp.value = activeAccount.server;
            } else if (pendingRequest) {
                if (badge) { badge.className = 'mt5-badge pending'; badge.textContent = 'Credentials pending'; }
                if (planEl) planEl.textContent = pendingRequest.plan_name || 'Standard';
                if (pendingNote) { pendingNote.removeAttribute('hidden'); pendingNote.style.display = 'block'; }
                if (connectedWrap) { connectedWrap.setAttribute('hidden', 'true'); connectedWrap.style.display = 'none'; }
                if (formWrap) { formWrap.setAttribute('hidden', 'true'); formWrap.style.display = 'none'; }
            } else {
                if (badge) { badge.className = 'mt5-badge pending'; badge.textContent = 'Plan Required'; }
                if (planEl) planEl.textContent = 'Select Plan';
                if (pendingNote) { pendingNote.removeAttribute('hidden'); pendingNote.style.display = 'block'; pendingNote.textContent = 'Please select an MT5 plan to request trading credentials.'; }
                if (connectedWrap) { connectedWrap.setAttribute('hidden', 'true'); connectedWrap.style.display = 'none'; }
                if (formWrap) { formWrap.setAttribute('hidden', 'true'); formWrap.style.display = 'none'; }
            }
        } else {
            mt5Card.setAttribute('hidden', 'true');
            mt5Card.style.display = 'none';
        }
    }
    setupUserMt5CredentialsForm();
}

let isOnboardingSequenceActive = false;

function setupBrokerModals() {
    document.getElementById('btnOpenBrokerModal')?.addEventListener('click', () => {
        if (typeof window.openBrokerModal === 'function') {
            window.openBrokerModal();
        }
    });

    document.querySelectorAll('[data-close-broker-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (typeof window.closeBrokerModals === 'function') {
                window.closeBrokerModals();
            }
        });
    });

    setupInstallAppModal();
}

function openPlanModal(choice = 'kryvex') {
    selectedBrokerChoice = choice;
    const modal1 = document.getElementById('modalChooseBroker');
    const modal2 = document.getElementById('modalChooseMt5Plan');
    const titleEl = document.getElementById('planModalTitle');
    const submitBtn = document.getElementById('btnSubmitPlanRequest');

    if (!modal2) return;

    if (modal1) { modal1.setAttribute('hidden', 'true'); modal1.style.display = 'none'; }
    modal2.removeAttribute('hidden');
    modal2.style.display = 'block';

    if (titleEl) {
        titleEl.textContent = choice === 'kryvex' ? 'Choose Kryvex Plan' : 'Choose MT5 Plan';
    }
    if (submitBtn) {
        submitBtn.textContent = choice === 'kryvex' ? 'Continue with Kryvex' : 'Request Account';
    }

    renderPlansList();
}

function checkAndShowLowBalanceWarning() {
    const warning = brokerStatusData?.plan_downgraded_warning;
    if (!warning) return;

    const modal = document.getElementById('modalLowBalanceWarning');
    const msgEl = document.getElementById('lowBalWarningMsg');
    const titleEl = document.getElementById('lowBalWarningTitle');
    const btnDismiss = document.getElementById('btnDismissLowBalWarning');
    const btnDeposit = document.getElementById('btnDepositLowBalWarning');

    if (!modal) return;

    if (titleEl) titleEl.textContent = `Plan Shifted (${warning.old_plan} → ${warning.new_plan})`;
    if (msgEl) msgEl.textContent = warning.message || `Your main balance fell below required threshold. Account shifted to ${warning.new_plan} plan.`;

    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';

    const handleDismiss = async (targetUrl = null) => {
        modal.setAttribute('hidden', 'true');
        modal.style.display = 'none';
        const token = localStorage.getItem('kryvex_access_token');
        if (token) {
            try {
                await fetch('/api/broker/dismiss-downgrade-warning', {
                    method: 'POST',
                    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }
                });
            } catch (e) {}
        }
        if (targetUrl) {
            window.location.href = targetUrl;
        }
    };

    if (btnDismiss) {
        btnDismiss.onclick = (e) => {
            e.preventDefault();
            handleDismiss(null);
        };
    }

    if (btnDeposit) {
        btnDeposit.onclick = (e) => {
            e.preventDefault();
            handleDismiss('/wallet');
        };
    }
}

function renderPlansList() {
    const grid = document.getElementById('mt5PlansGrid');
    const balEl = document.getElementById('mt5ModalMainBalance');
    const errBox = document.getElementById('mt5PlanErrorBox');

    if (!grid) return;

    const mainBal = Number(brokerStatusData?.main_balance || 0);
    if (balEl) balEl.textContent = `$${mainBal.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    if (errBox) { errBox.hidden = true; errBox.style.display = 'none'; }

    const plans = brokerStatusData?.plans || [
        { plan_key: 'basic', name: 'Basic', minimum_balance: 1000, max_leverage: '1:100' },
        { plan_key: 'standard', name: 'Standard', minimum_balance: 50000, max_leverage: '1:300' },
        { plan_key: 'advanced', name: 'Advanced', minimum_balance: 250000, max_leverage: '1:500' }
    ];

    if (!selectedMt5PlanKey) {
        const validPlan = plans.find(p => mainBal >= p.minimum_balance) || plans[0];
        selectedMt5PlanKey = validPlan ? validPlan.plan_key : 'basic';
    }

    grid.innerHTML = plans.map(p => {
        const isEligible = selectedBrokerChoice === 'kryvex' || mainBal >= Number(p.minimum_balance);
        const isSelected = selectedMt5PlanKey === p.plan_key;
        const spreadText = p.spread_formatted || (p.plan_key === 'advanced' ? '0.01%' : (p.plan_key === 'standard' ? '0.03%' : '0.05%'));
        return `
            <div class="mt5-plan-card ${isSelected ? 'is-selected' : ''} ${!isEligible ? 'is-disabled' : ''}" data-plan-key="${p.plan_key}" data-min-bal="${p.minimum_balance}" data-plan-name="${p.name}">
                <div>
                    <strong style="display:block; font-size:1.05rem; color:#fff;">${escapeHtml(p.name)} Plan</strong>
                    <span style="font-size:0.78rem; color:#94a3b8;">Min Balance: $${Number(p.minimum_balance).toLocaleString()} · Leverage: ${p.max_leverage} · <strong style="color:#f4c542;">Spread: ${spreadText}</strong></span>
                </div>
                <div class="radio-check-indicator"></div>
            </div>
        `;
    }).join('');

    grid.querySelectorAll('.mt5-plan-card').forEach(card => {
        card.addEventListener('click', () => {
            const planKey = card.dataset.planKey;
            const minBal = Number(card.dataset.minBal || 0);
            const planName = card.dataset.planName || 'Selected';

            selectedMt5PlanKey = planKey;
            grid.querySelectorAll('.mt5-plan-card').forEach(c => c.classList.remove('is-selected'));
            card.classList.add('is-selected');

            if (selectedBrokerChoice === 'mt5' && mainBal < minBal) {
                if (errBox) {
                    errBox.hidden = false;
                    errBox.style.display = 'block';
                    errBox.innerHTML = `Insufficient balance. Minimum balance required for <strong>${planName}</strong> plan: <strong>$${minBal.toLocaleString()}</strong>`;
                }
                const submitBtn = document.getElementById('btnSubmitPlanRequest');
                if (submitBtn) submitBtn.disabled = true;
            } else {
                if (errBox) { errBox.hidden = true; errBox.style.display = 'none'; }
                const submitBtn = document.getElementById('btnSubmitPlanRequest');
                if (submitBtn) submitBtn.disabled = false;
            }
        });
    });

    const currentSelectedCard = grid.querySelector(`.mt5-plan-card[data-plan-key="${selectedMt5PlanKey}"]`);
    if (currentSelectedCard) currentSelectedCard.click();
}

async function submitPlanRequest() {
    if (!selectedMt5PlanKey) return;
    const token = localStorage.getItem('kryvex_access_token');
    const submitBtn = document.getElementById('btnSubmitPlanRequest');

    if (selectedBrokerChoice === 'kryvex') {
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }
        try {
            const res = await fetch('/api/broker/select', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify({ broker_type: 'kryvex', plan_key: selectedMt5PlanKey }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to select broker.');

            closeBrokerModals();
            let user = {};
            try {
                user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
            } catch (err) {}
            user.broker_type = 'kryvex';
            localStorage.setItem('kryvex_user', JSON.stringify(user));

            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'success', title: 'Broker Updated', message: 'Trading broker set to Kryvex Exchange.' });
            }
            setTimeout(() => {
                window.location.reload();
            }, 300);
        } catch (err) {
            alert(err.message || 'Unable to update broker.');
        } finally {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Continue with Kryvex'; }
        }
    } else {
        // MT5 Flow
        if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Requesting...'; }
        try {
            const res = await fetch('/api/broker/mt5-request', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`,
                },
                body: JSON.stringify({ plan_key: selectedMt5PlanKey }),
            });

            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'Failed to request MT5 account.');

            const modal2 = document.getElementById('modalChooseMt5Plan');
            const modal3 = document.getElementById('modalMt5Requested');
            const reqPlanNameEl = document.getElementById('reqModalPlanName');

            const activePlan = (brokerStatusData?.plans || []).find(p => p.plan_key === selectedMt5PlanKey);
            if (reqPlanNameEl) reqPlanNameEl.textContent = activePlan ? activePlan.name : selectedMt5PlanKey;

            if (modal2) { modal2.setAttribute('hidden', 'true'); modal2.style.display = 'none'; }
            if (modal3) { modal3.removeAttribute('hidden'); modal3.style.display = 'block'; }

            await loadBrokerStatus();
        } catch (err) {
            const errBox = document.getElementById('mt5PlanErrorBox');
            if (errBox) {
                errBox.hidden = false;
                errBox.style.display = 'block';
                errBox.textContent = err.message || 'Unable to request MT5 account.';
            }
        } finally {
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Request Account'; }
        }
    }
}

function setupUserMt5CredentialsForm() {
    const editBtn = document.getElementById('btnEditMt5Credentials');
    const cancelBtn = document.getElementById('btnCancelEditMt5');
    const connectedWrap = document.getElementById('mt5ConnectedViewWrap');
    const formWrap = document.getElementById('mt5CredentialsFormWrap');

    if (editBtn) {
        editBtn.onclick = () => {
            if (connectedWrap) { connectedWrap.setAttribute('hidden', 'true'); connectedWrap.style.display = 'none'; }
            if (formWrap) { formWrap.removeAttribute('hidden'); formWrap.style.display = 'block'; }
        };
    }

    if (cancelBtn) {
        cancelBtn.onclick = () => {
            if (formWrap) { formWrap.setAttribute('hidden', 'true'); formWrap.style.display = 'none'; }
            if (connectedWrap) { connectedWrap.removeAttribute('hidden'); connectedWrap.style.display = 'block'; }
        };
    }

    const toggleViewPassBtn = document.getElementById('btnToggleViewPassword');
    if (toggleViewPassBtn) {
        toggleViewPassBtn.onclick = () => {
            const passEl = document.getElementById('viewMt5Password');
            if (!passEl) return;
            const raw = passEl.dataset.rawPass || '';
            const isMasked = passEl.textContent === '••••••••';
            passEl.textContent = isMasked ? (raw || '••••••••') : '••••••••';
            toggleViewPassBtn.textContent = isMasked ? '🙈' : '👁';
        };
    }

    const copyLoginBtn = document.getElementById('btnCopyMt5Login');
    if (copyLoginBtn) {
        copyLoginBtn.onclick = () => {
            const loginVal = document.getElementById('viewMt5Login')?.textContent || '';
            if (loginVal && loginVal !== '--') {
                navigator.clipboard.writeText(loginVal);
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Copied', message: 'MT5 Login ID copied to clipboard!' });
                } else {
                    alert('MT5 Login ID copied to clipboard!');
                }
            }
        };
    }

    const toggleFormPassBtn = document.getElementById('toggleUserMt5Password');
    if (toggleFormPassBtn) {
        toggleFormPassBtn.onclick = () => {
            const input = document.getElementById('userMt5Password');
            if (!input) return;
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            toggleFormPassBtn.textContent = isPass ? '🙈' : '👁';
        };
    }

    const form = document.getElementById('userMt5CredentialsForm');
    if (form) {
        form.onsubmit = async (e) => {
            e.preventDefault();
            const token = localStorage.getItem('kryvex_access_token');
            const login = document.getElementById('userMt5Login')?.value || '';
            const password = document.getElementById('userMt5Password')?.value || '';
            const server = document.getElementById('userMt5Server')?.value || 'Kryvex-Live-01';
            const submitBtn = document.getElementById('btnSaveUserMt5Credentials');

            if (!login || !password) {
                alert('Please enter both MT5 Login ID and Password.');
                return;
            }

            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Connecting...'; }

            try {
                const res = await fetch('/api/broker/mt5-credentials', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${token}`,
                    },
                    body: JSON.stringify({ login, password, server }),
                });

                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Failed to save MT5 credentials.');

                await loadBrokerStatus();
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'MT5 Connected', message: 'MT5 credentials saved and connected successfully!' });
                } else {
                    alert('MT5 credentials saved and connected successfully!');
                }
            } catch (err) {
                alert(err.message || 'Unable to connect MT5 credentials.');
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Connect & Save Credentials'; }
            }
        };
    }
}

function openInstallAppModal() {
    const backdrop = document.getElementById('installAppModalBackdrop');
    if (!backdrop) {
        setTimeout(openInstallAppModal, 150);
        return;
    }
    backdrop.removeAttribute('hidden');
    backdrop.style.display = 'flex';
    backdrop.style.zIndex = '999999';
}

function closeInstallAppModal() {
    const backdrop = document.getElementById('installAppModalBackdrop');
    if (!backdrop) return;
    backdrop.setAttribute('hidden', 'true');
    backdrop.style.display = 'none';

    const token = localStorage.getItem('kryvex_access_token');
    if (token) {
        fetch('/api/broker/dismiss-app-popup', {
            method: 'POST',
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }
        }).catch(() => {});
    }
}

function setupInstallAppModal() {
    const btnOpen = document.getElementById('btnOpenInstallAppModal');

    if (btnOpen) {
        btnOpen.addEventListener('click', openInstallAppModal);
    }

    document.querySelectorAll('[data-close-install-modal], .btn-maybe-later').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            closeInstallAppModal();
        });
    });

    // Global document event delegation safeguard
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-close-install-modal]') || e.target.closest('.btn-maybe-later')) {
            closeInstallAppModal();
        }
    });

    window.openInstallAppModal = openInstallAppModal;
    window.closeInstallAppModal = closeInstallAppModal;
}


