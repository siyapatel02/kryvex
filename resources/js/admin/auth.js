// User auth page logic for login, registration OTP, registration resend and forgot password.
// Registration OTP is valid for 120 seconds. Registration resend allows 2 instant resends, then 10 minute cooldown.
// Forgot password OTP has no resend count limit, but every code expires in 120 seconds and password fields open only after OTP verification.

function initAdminAuthPage() {
    setupPhoneRestriction();
    setupTabSwitcher();
    setupRegisterForm();
    setupVerifyRegisterForm();
    setupRegisterResend();
    setupLoginForm();
    setupForgotPasswordFlow();
    consumePasswordResetRedirectMessage();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdminAuthPage);
} else {
    initAdminAuthPage();
}

function setupPhoneRestriction() {
    const phoneInput = document.querySelector('input[name="phone"]');
    if (!phoneInput) return;

    phoneInput.addEventListener('input', () => {
        phoneInput.value = phoneInput.value.replace(/\D/g, '').slice(0, 10);
    });
}

function setupTabSwitcher() {
    const tabLoginBtn = document.getElementById('tabLoginBtn');
    const tabRegisterBtn = document.getElementById('tabRegisterBtn');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const verifyForm = document.getElementById('verifyRegisterForm');
    const forgotForm = document.getElementById('forgotPasswordForm');
    const tabHeader = document.getElementById('authTabHeader');

    if (!tabLoginBtn || !tabRegisterBtn) return;

    window.switchAuthTab = function(tab, updateUrl = true) {
        if (tab === 'login') {
            tabLoginBtn.className = 'flex-1 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all text-center bg-amber-400 text-zinc-950 shadow-md shadow-amber-400/20';
            tabRegisterBtn.className = 'flex-1 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all text-center text-zinc-400 hover:text-white';
            
            if (loginForm) loginForm.style.setProperty('display', 'flex', 'important');
            if (registerForm) registerForm.style.setProperty('display', 'none', 'important');
            if (verifyForm) verifyForm.style.setProperty('display', 'none', 'important');
            if (forgotForm) forgotForm.style.setProperty('display', 'none', 'important');
            if (tabHeader) tabHeader.style.setProperty('display', 'flex', 'important');

            if (updateUrl && window.history.pushState) {
                window.history.pushState({ tab: 'login' }, '', '/admin/login');
            }
        } else if (tab === 'register') {
            tabRegisterBtn.className = 'flex-1 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all text-center bg-amber-400 text-zinc-950 shadow-md shadow-amber-400/20';
            tabLoginBtn.className = 'flex-1 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all text-center text-zinc-400 hover:text-white';

            if (registerForm) registerForm.style.setProperty('display', 'flex', 'important');
            if (loginForm) loginForm.style.setProperty('display', 'none', 'important');
            if (verifyForm) verifyForm.style.setProperty('display', 'none', 'important');
            if (forgotForm) forgotForm.style.setProperty('display', 'none', 'important');
            if (tabHeader) tabHeader.style.setProperty('display', 'flex', 'important');

            if (updateUrl && window.history.pushState) {
                window.history.pushState({ tab: 'register' }, '', '/admin/register');
            }
        } else if (tab === 'forgot') {
            if (forgotForm) {
                resetForgotUi(forgotForm);
                if (loginForm) loginForm.style.setProperty('display', 'none', 'important');
                if (registerForm) registerForm.style.setProperty('display', 'none', 'important');
                if (verifyForm) verifyForm.style.setProperty('display', 'none', 'important');
                if (tabHeader) tabHeader.style.setProperty('display', 'none', 'important');
                forgotForm.style.setProperty('display', 'flex', 'important');
                focusForgotStepInput('email');
            }

            if (updateUrl && window.history.pushState) {
                window.history.pushState({ tab: 'forgot' }, '', '/admin/forgot-password');
            }
        }
    };

    tabLoginBtn.addEventListener('click', (e) => {
        e.preventDefault();
        window.switchAuthTab('login', true);
    });

    tabRegisterBtn.addEventListener('click', (e) => {
        e.preventDefault();
        window.switchAuthTab('register', true);
    });

    window.addEventListener('popstate', () => {
        if (window.location.pathname.endsWith('/forgot-password') || window.location.search.includes('tab=forgot')) {
            window.switchAuthTab('forgot', false);
        } else if (window.location.pathname.endsWith('/register') || window.location.search.includes('tab=register')) {
            window.switchAuthTab('register', false);
        } else {
            window.switchAuthTab('login', false);
        }
    });

    if (window.location.pathname.endsWith('/forgot-password') || window.location.search.includes('tab=forgot')) {
        window.switchAuthTab('forgot', false);
    } else if (window.location.pathname.endsWith('/register') || window.location.search.includes('tab=register')) {
        window.switchAuthTab('register', false);
    }
}

function setupRegisterForm() {
    const form = document.getElementById('registerForm');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = formToObject(form);
        payload.terms = Boolean(form.querySelector('input[name="terms"]')?.checked);

        showAlert('Sending verification code...', 'success');

        try {
            const response = await fetch('/admin/api/register', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(getApiErrorMessage(data, 'Unable to send verification code.'));
            }

            document.getElementById('verifyEmailInput').value = data.data.email;
            form.style.display = 'none';
            document.getElementById('verifyRegisterForm').style.display = 'grid';
            startRegisterOtpTimer(data.data.expires_in_seconds || 120);
            updateRegisterResendHelp('2 instant resends available. Code is valid for 120 seconds.');

            showAlert(data.message || 'Verification code sent.', 'success');
        } catch (error) {
            showAlert(error.message || 'Something went wrong.', 'error');
        }
    });
}

function setupVerifyRegisterForm() {
    const form = document.getElementById('verifyRegisterForm');
    const registerForm = document.getElementById('registerForm');
    const backButton = document.getElementById('backToRegisterButton');
    const codeInput = form?.querySelector('input[name="code"]');

    if (codeInput) {
        codeInput.addEventListener('input', () => {
            codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    if (backButton && registerForm && form) {
        backButton.addEventListener('click', () => {
            stopRegisterTimers();
            form.style.display = 'none';
            registerForm.style.display = 'grid';
            showAlert('You can edit details and send a new code.', 'success');
        });
    }

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = formToObject(form);

        showAlert('Verifying code...', 'success');

        try {
            const response = await fetch('/admin/api/register/verify', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Verification failed.');
            }

            saveAuthSession(data.data);
            stopRegisterTimers();

            showAlert(data.message || 'Account created successfully.', 'success');

            setTimeout(() => {
                window.location.replace('/admin/dashboard');
            }, 700);
        } catch (error) {
            showAlert(error.message || 'Something went wrong.', 'error');
        }
    });
}

function setupRegisterResend() {
    const button = document.getElementById('resendRegisterCodeButton');
    const emailInput = document.getElementById('verifyEmailInput');

    if (!button || !emailInput) {
        return;
    }

    button.addEventListener('click', async () => {
        const email = emailInput.value;

        if (!email) {
            showAlert('Email is missing. Please register again.', 'error');
            return;
        }

        button.disabled = true;
        showAlert('Sending new verification code...', 'success');

        try {
            const response = await fetch('/admin/api/register/resend', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email }),
            });

            const data = await response.json();

            if (!response.ok || !data.success) {
                const waitSeconds = data?.data?.cooldown_seconds;

                if (waitSeconds) {
                    startRegisterResendCooldown(waitSeconds);
                } else {
                    button.disabled = false;
                }

                throw new Error(data.message || 'Unable to resend verification code.');
            }

            startRegisterOtpTimer(data.data?.expires_in_seconds || 120);
            updateRegisterResendHelp(`${data.data?.resends_left ?? 0} instant resend(s) left before 10 minute cooldown.`);
            button.disabled = false;

            showAlert(data.message || 'New verification code sent.', 'success');
        } catch (error) {
            showAlert(error.message || 'Something went wrong.', 'error');
        }
    });
}

function setupLoginForm() {
    const form = document.getElementById('loginForm');

    if (!form) {
        return;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const payload = formToObject(form);

        await submitAuthForm('/admin/api/login', payload);
    });
}

function setupForgotPasswordFlow() {
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');
    const verifyForm = document.getElementById('verifyRegisterForm');
    const forgotForm = document.getElementById('forgotPasswordForm');
    const openButtons = document.querySelectorAll('[data-open-forgot-password]');
    const backToLoginButton = document.querySelector('[data-back-to-login]');
    const backToRegisterButton = document.querySelector('[data-back-to-register]');
    const sendButton = document.getElementById('forgotSendCodeButton');
    const resendButton = document.getElementById('forgotResendButton');
    const verifyButton = document.getElementById('forgotVerifyCodeButton');
    const codeInput = forgotForm?.querySelector('input[name="code"]');

    if (!forgotForm) {
        return;
    }

    // Custom step handling lets Enter key move to the next step.
    // The browser's built-in validation is disabled because hidden step fields should not block submit.
    forgotForm.setAttribute('novalidate', 'novalidate');
    showForgotStep('email');

    if (codeInput) {
        codeInput.addEventListener('input', () => {
            codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    const tabHeader = document.getElementById('authTabHeader');

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-open-forgot-password]');
        if (!btn) return;

        event.preventDefault();
        event.stopPropagation();

        const forgotForm = document.getElementById('forgotPasswordForm');
        const loginForm = document.getElementById('loginForm');
        const registerForm = document.getElementById('registerForm');
        const verifyForm = document.getElementById('verifyRegisterForm');
        const tabHeader = document.getElementById('authTabHeader');

        if (forgotForm) {
            resetForgotUi(forgotForm);
            if (loginForm) loginForm.style.setProperty('display', 'none', 'important');
            if (registerForm) registerForm.style.setProperty('display', 'none', 'important');
            if (verifyForm) verifyForm.style.setProperty('display', 'none', 'important');
            if (tabHeader) tabHeader.style.setProperty('display', 'none', 'important');
            forgotForm.style.setProperty('display', 'flex', 'important');
            focusForgotStepInput('email');
            showAlert('Enter your registered email to receive a password reset code.', 'success');
        }
    });

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            resetForgotUi(forgotForm);
            if (loginForm) loginForm.style.setProperty('display', 'none', 'important');
            if (registerForm) registerForm.style.setProperty('display', 'none', 'important');
            if (verifyForm) verifyForm.style.setProperty('display', 'none', 'important');
            if (tabHeader) tabHeader.style.setProperty('display', 'none', 'important');
            forgotForm.style.setProperty('display', 'flex', 'important');
            focusForgotStepInput('email');
            showAlert('Enter your registered email to receive a password reset code.', 'success');
        });
    });

    document.addEventListener('click', (event) => {
        const loginBtn = event.target.closest('[data-back-to-login]');
        if (loginBtn) {
            event.preventDefault();
            stopForgotOtpTimer();
            if (forgotForm) resetForgotUi(forgotForm);
            if (typeof window.switchAuthTab === 'function') {
                window.switchAuthTab('login', true);
            }
            return;
        }

        const registerBtn = event.target.closest('[data-back-to-register]');
        if (registerBtn) {
            event.preventDefault();
            stopForgotOtpTimer();
            if (forgotForm) resetForgotUi(forgotForm);
            if (typeof window.switchAuthTab === 'function') {
                window.switchAuthTab('register', true);
            }
            return;
        }
    });

    if (backToLoginButton) {
        backToLoginButton.addEventListener('click', (e) => {
            e.preventDefault();
            stopForgotOtpTimer();
            resetForgotUi(forgotForm);
            if (typeof window.switchAuthTab === 'function') {
                window.switchAuthTab('login', true);
            }
        });
    }

    if (backToRegisterButton) {
        backToRegisterButton.addEventListener('click', (e) => {
            e.preventDefault();
            stopForgotOtpTimer();
            resetForgotUi(forgotForm);
            if (typeof window.switchAuthTab === 'function') {
                window.switchAuthTab('register', true);
            }
        });
    }

    if (sendButton) {
        sendButton.addEventListener('click', () => sendForgotCode(forgotForm));
    }

    if (resendButton) {
        resendButton.addEventListener('click', () => sendForgotCode(forgotForm, true));
    }

    if (verifyButton) {
        verifyButton.addEventListener('click', () => verifyForgotCode(forgotForm));
    }

    forgotForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (currentForgotStep === 'email') {
            await sendForgotCode(forgotForm);
            return;
        }

        if (currentForgotStep === 'code') {
            await verifyForgotCode(forgotForm);
            return;
        }

        await submitForgotPasswordReset(forgotForm);
    });
}

function resetForgotUi(form) {
    forgotResetToken = '';
    forgotCodeVerified = false;
    stopForgotOtpTimer();

    const tokenInput = document.getElementById('forgotResetToken');
    if (tokenInput) tokenInput.value = '';

    const codeInput = form?.querySelector('input[name="code"]');
    const passwordInput = form?.querySelector('input[name="password"]');
    const confirmInput = form?.querySelector('input[name="password_confirmation"]');

    if (codeInput) codeInput.value = '';
    if (passwordInput) passwordInput.value = '';
    if (confirmInput) confirmInput.value = '';

    showForgotStep('email');
}

function showForgotStep(step) {
    currentForgotStep = step;

    const emailSection = document.getElementById('forgotEmailSection');
    const codeSection = document.getElementById('forgotCodeSection');
    const passwordSection = document.getElementById('forgotPasswordSection');
    const subtitle = document.getElementById('forgotStepSubtitle');

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

        // Force one-step-only UI. This prevents older CSS or browser cache from showing
        // email + code + password sections together.
        section.style.setProperty('display', active ? 'grid' : 'none', 'important');
    });

    const forgotForm = document.getElementById('forgotPasswordForm');
    if (forgotForm) {
        forgotForm.dataset.step = step;
    }

    if (subtitle) {
        subtitle.textContent = step === 'email'
            ? 'Enter your email to receive a 6 digit reset code.'
            : step === 'code'
                ? 'Enter the reset code within 120 seconds.'
                : 'Code verified. Set your new password.';
    }

    focusForgotStepInput(step);
}

function focusForgotStepInput(step) {
    const selectors = {
        email: '#forgotEmailSection input[name="email"]',
        code: '#forgotCodeSection input[name="code"]',
        password: '#forgotPasswordSection input[name="password"]',
    };

    const input = document.querySelector(selectors[step]);

    if (input) {
        setTimeout(() => input.focus(), 80);
    }
}

async function sendForgotCode(form, isResend = false) {
    const emailInput = form.querySelector('input[name="email"]');
    const email = String(emailInput?.value || lastForgotEmail || '').trim().toLowerCase();

    if (!email) {
        showAlert('Please enter your registered email.', 'error');
        return;
    }

    showAlert(isResend ? 'Resending reset code...' : 'Sending reset code...', 'success');

    try {
        const response = await fetch('/admin/api/forgot-password/send-code', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email }),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(getApiErrorMessage(data, 'Unable to send reset code.'));
        }

        lastForgotEmail = email;
        forgotResetToken = '';
        forgotCodeVerified = false;

        const tokenInput = document.getElementById('forgotResetToken');

        if (tokenInput) tokenInput.value = '';

        showForgotStep('code');
        startForgotOtpTimer(data.data?.expires_in_seconds || 120);

        showAlert(data.message || 'Reset code sent.', 'success');
    } catch (error) {
        showAlert(error.message || 'Something went wrong.', 'error');
    }
}

async function verifyForgotCode(form) {
    const payload = {
        email: String(form.querySelector('input[name="email"]')?.value || lastForgotEmail || '').trim().toLowerCase(),
        code: String(form.querySelector('input[name="code"]')?.value || '').trim(),
    };

    if (!payload.email || !payload.code) {
        showAlert('Please enter email and reset code.', 'error');
        return;
    }

    showAlert('Verifying reset code...', 'success');

    try {
        const response = await fetch('/admin/api/forgot-password/verify-code', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(getApiErrorMessage(data, 'Unable to verify reset code.'));
        }

        forgotCodeVerified = true;
        forgotResetToken = data.data?.reset_token || '';
        stopForgotOtpTimer();

        const tokenInput = document.getElementById('forgotResetToken');

        if (tokenInput) tokenInput.value = forgotResetToken;

        showForgotStep('password');
        showAlert(data.message || 'Code verified. Set your new password.', 'success');
    } catch (error) {
        showAlert(error.message || 'Something went wrong.', 'error');
    }
}

async function submitForgotPasswordReset(form) {
    const payload = formToObject(form);
    payload.email = String(payload.email || lastForgotEmail || '').trim().toLowerCase();
    payload.reset_token = payload.reset_token || forgotResetToken;

    if (!forgotCodeVerified || !payload.reset_token) {
        showAlert('Please verify your reset code first.', 'error');
        return;
    }

    showAlert('Resetting password...', 'success');

    try {
        const response = await fetch('/admin/api/forgot-password/reset', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(getApiErrorMessage(data, 'Unable to reset password.'));
        }

        stopForgotOtpTimer();
        form.reset();
        resetForgotUi(form);

        const successMessage = data.message || 'Password updated successfully. You can login with your new password.';

        // Real-app flow: after reset, return to the login screen instead of staying on reset steps.
        localStorage.setItem('kryvex_admin_password_reset_message', successMessage);
        window.location.replace('/admin/login');
    } catch (error) {
        showAlert(error.message || 'Something went wrong.', 'error');
    }
}

async function submitAuthForm(url, payload) {
    showAlert('Processing...', 'success');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Request failed.');
        }

        saveAuthSession(data.data);

        showAlert(data.message || 'Success.', 'success');

        setTimeout(() => {
            window.location.replace('/admin/dashboard');
        }, 600);
    } catch (error) {
        showAlert(error.message || 'Something went wrong.', 'error');
    }
}

function startRegisterOtpTimer(seconds) {
    const timer = document.getElementById('registerOtpTimer');
    if (!timer) return;

    if (registerOtpTimer) clearInterval(registerOtpTimer);
    let remaining = Number(seconds || 120);
    timer.textContent = `${remaining}s`;

    registerOtpTimer = setInterval(() => {
        remaining -= 1;
        timer.textContent = `${Math.max(remaining, 0)}s`;

        if (remaining <= 0) {
            clearInterval(registerOtpTimer);
            registerOtpTimer = null;
            showAlert('Verification code expired. Please resend or register again.', 'error');
        }
    }, 1000);
}

function startRegisterResendCooldown(seconds) {
    const button = document.getElementById('resendRegisterCodeButton');
    if (!button) return;

    if (registerResendCooldownTimer) clearInterval(registerResendCooldownTimer);
    let remaining = Number(seconds || 600);
    button.disabled = true;

    const update = () => {
        button.textContent = `Wait ${formatDuration(remaining)}`;
        updateRegisterResendHelp('Resend locked after 2 instant resends. Please wait for cooldown.');
    };

    update();

    registerResendCooldownTimer = setInterval(() => {
        remaining -= 1;
        update();

        if (remaining <= 0) {
            clearInterval(registerResendCooldownTimer);
            registerResendCooldownTimer = null;
            button.disabled = false;
            button.textContent = 'Resend code';
            updateRegisterResendHelp('Cooldown completed. You can request a new verification code.');
        }
    }, 1000);
}

function stopRegisterTimers() {
    if (registerOtpTimer) clearInterval(registerOtpTimer);
    if (registerResendCooldownTimer) clearInterval(registerResendCooldownTimer);
    registerOtpTimer = null;
    registerResendCooldownTimer = null;
}

function startForgotOtpTimer(seconds) {
    const timer = document.getElementById('forgotOtpTimer');
    const verifyButton = document.getElementById('forgotVerifyCodeButton');

    if (!timer) return;

    if (forgotOtpTimer) clearInterval(forgotOtpTimer);
    let remaining = Number(seconds || 120);
    timer.textContent = `${remaining}s`;

    if (verifyButton) verifyButton.disabled = false;

    forgotOtpTimer = setInterval(() => {
        remaining -= 1;
        timer.textContent = `${Math.max(remaining, 0)}s`;

        if (remaining <= 0) {
            clearInterval(forgotOtpTimer);
            forgotOtpTimer = null;
            if (verifyButton) verifyButton.disabled = true;
            showAlert('Password reset code expired. Please resend a new code.', 'error');
        }
    }, 1000);
}

function stopForgotOtpTimer() {
    if (forgotOtpTimer) clearInterval(forgotOtpTimer);
    forgotOtpTimer = null;
}

function updateRegisterResendHelp(message) {
    const help = document.getElementById('registerResendHelp');
    if (help) help.textContent = message;
}

function formatDuration(totalSeconds) {
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = Math.floor(totalSeconds % 60);
    return `${minutes}m ${seconds}s`;
}

function consumePasswordResetRedirectMessage() {
    const message = localStorage.getItem('kryvex_admin_password_reset_message');

    if (!message) {
        return;
    }

    localStorage.removeItem('kryvex_admin_password_reset_message');

    const loginForm = document.getElementById('loginForm');
    const forgotForm = document.getElementById('forgotPasswordForm');

    if (forgotForm) forgotForm.style.setProperty('display', 'none', 'important');
    if (loginForm) loginForm.style.setProperty('display', 'grid', 'important');

    showAlert(message, 'success');
}

function saveAuthSession(data) {
    const tokens = data.tokens || {};
    const user = data.user || {};

    localStorage.setItem('kryvex_admin_access_token', tokens.access_token || '');
    localStorage.setItem('kryvex_admin_refresh_token', tokens.refresh_token || '');
    localStorage.setItem('kryvex_admin_user', JSON.stringify(user));
}

function formToObject(form) {
    const formData = new FormData(form);
    const payload = {};

    formData.forEach((value, key) => {
        payload[key] = value;
    });

    return payload;
}

function showAlert(message, type = 'success') {
    const alert = document.getElementById('authAlert');

    // Auth pages now use toast notifications instead of permanent inline messages.
    // The inline alert box stays available in markup for fallback, but it is kept hidden.
    if (alert) {
        alert.className = 'auth-alert';
        alert.textContent = '';
    }

    showAuthToast(message, type);
}

function showAuthToast(message, type = 'success') {
    if (!message) return;

    const oldToast = document.querySelector('.auth-page-toast');
    if (oldToast) oldToast.remove();

    const toast = document.createElement('div');
    toast.className = `app-toast ${type === 'error' ? 'error' : 'success'} auth-page-toast`;
    toast.innerHTML = `
        <strong>${type === 'error' ? 'Error' : 'Success'}</strong>
        <p>${escapeHtml(message)}</p>
    `;

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(80px)';

        setTimeout(() => toast.remove(), 320);
    }, 3200);
}

function escapeHtml(value) {
    return String(value || '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('\"', '&quot;')
        .replaceAll("'", '&#039;');
}

function getApiErrorMessage(data, fallback = 'Request failed.') {
    if (data?.message) {
        return data.message;
    }

    if (data?.errors) {
        const firstKey = Object.keys(data.errors)[0];

        if (firstKey && Array.isArray(data.errors[firstKey])) {
            return data.errors[firstKey][0];
        }
    }

    return fallback;
}
