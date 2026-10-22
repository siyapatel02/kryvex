// User auth page logic for login, registration OTP, registration resend and forgot password.
// Registration OTP is valid for 120 seconds. Registration resend allows 2 instant resends, then 10 minute cooldown.
// Forgot password OTP has no resend count limit, but every code expires in 120 seconds and password fields open only after OTP verification.

function checkAccountDeletedRedirectMessage() {
    const urlParams = new URLSearchParams(window.location.search);
    const msg = localStorage.getItem('kryvex_account_deleted_message') || sessionStorage.getItem('kryvex_account_deleted_message');
    if (urlParams.has('account_deleted') || msg) {
        showAlert(msg || 'Your account deletion request has been approved by administrator. Your account has been permanently closed.', 'error');
        try {
            localStorage.removeItem('kryvex_account_deleted_message');
            sessionStorage.removeItem('kryvex_account_deleted_message');
        } catch (e) {}
    }
}

function initAuthPage() {
    setupPhoneRestriction();
    setupRegisterForm();
    setupVerifyRegisterForm();
    setupRegisterResend();
    setupLoginForm();
    setupLogin2FaForm();
    setupForgotPasswordFlow();
    consumePasswordResetRedirectMessage();
    checkAccountDeletedRedirectMessage();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuthPage);
} else {
    initAuthPage();
}

let registerOtpTimer = null;
let registerResendCooldownTimer = null;
let forgotOtpTimer = null;
let login2FaOtpTimer = null;
let lastForgotEmail = '';
let forgotResetToken = '';
let forgotCodeVerified = false;
let currentForgotStep = 'email';

function setupPhoneRestriction() {
    const phoneInput = document.querySelector('input[name="phone"]');

    if (!phoneInput) {
        return;
    }

    phoneInput.addEventListener('input', () => {
        phoneInput.value = phoneInput.value.replace(/\D/g, '').slice(0, 10);
    });
}



function setupRegisterForm() {
    const form = document.getElementById('registerForm');

    if (!form) {
        return;
    }

    const refInput = form.querySelector('input[name="referral_code"]');
    if (refInput) {
        const urlParams = new URLSearchParams(window.location.search);
        const refParam = urlParams.get('ref') || urlParams.get('referral_code');
        if (refParam) {
            refInput.value = refParam.trim();
            sessionStorage.setItem('kryvex_ref_code', refParam.trim());
        } else {
            const cachedRef = sessionStorage.getItem('kryvex_ref_code');
            if (cachedRef && !refInput.value) {
                refInput.value = cachedRef.trim();
            }
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            if (submitBtn.disabled) return;
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
            submitBtn.style.cursor = 'not-allowed';
            if (!submitBtn.dataset.originalText) {
                submitBtn.dataset.originalText = submitBtn.innerHTML;
            }
            submitBtn.innerHTML = 'Sending Code...';
        }

        const payload = formToObject(form);
        payload.terms = Boolean(form.querySelector('input[name="terms"]')?.checked);

        try {
            const response = await fetch('/api/auth/register', {
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
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '';
                submitBtn.style.cursor = '';
                submitBtn.innerHTML = submitBtn.dataset.originalText || 'Send Verification Code';
            }
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
            const registerSubmitBtn = registerForm.querySelector('button[type="submit"]');
            if (registerSubmitBtn) {
                registerSubmitBtn.disabled = false;
                registerSubmitBtn.style.opacity = '';
                registerSubmitBtn.style.cursor = '';
                if (registerSubmitBtn.dataset.originalText) {
                    registerSubmitBtn.innerHTML = registerSubmitBtn.dataset.originalText;
                }
            }
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
            const response = await fetch('/api/auth/register/verify', {
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
            localStorage.setItem('kryvex_new_user_signup', 'true');
            stopRegisterTimers();

            showAlert(data.message || 'Account created successfully.', 'success');

            setTimeout(() => {
                window.location.replace('/');
            }, 500);
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
            const response = await fetch('/api/auth/register/resend', {
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

        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            if (submitBtn.disabled) return;
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';
            submitBtn.style.cursor = 'not-allowed';
            if (!submitBtn.dataset.originalText) {
                submitBtn.dataset.originalText = submitBtn.innerHTML;
            }
            submitBtn.innerHTML = 'Verifying...';
        }

        const payload = formToObject(form);

        try {
            await submitAuthForm('/api/auth/login', payload);
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '';
                submitBtn.style.cursor = '';
                submitBtn.innerHTML = submitBtn.dataset.originalText || 'Login';
            }
        }
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

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            resetForgotUi(forgotForm);
            if (loginForm) loginForm.style.display = 'none';
            if (registerForm) registerForm.style.display = 'none';
            if (verifyForm) verifyForm.style.display = 'none';
            forgotForm.style.display = 'grid';
            focusForgotStepInput('email');
            showAlert('Enter your registered email to receive a password reset code.', 'success');
        });
    });

    if (backToLoginButton && loginForm) {
        backToLoginButton.addEventListener('click', () => {
            stopForgotOtpTimer();
            resetForgotUi(forgotForm);
            forgotForm.style.display = 'none';
            loginForm.style.display = 'grid';
        });
    }

    if (backToRegisterButton && registerForm) {
        backToRegisterButton.addEventListener('click', () => {
            stopForgotOtpTimer();
            resetForgotUi(forgotForm);
            forgotForm.style.display = 'none';
            registerForm.style.display = 'grid';
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
    const sendBtn = document.getElementById(isResend ? 'forgotResendButton' : 'forgotSendCodeButton');
    if (sendBtn) {
        if (sendBtn.disabled) return;
        sendBtn.disabled = true;
        sendBtn.style.opacity = '0.7';
        sendBtn.style.cursor = 'not-allowed';
        if (!sendBtn.dataset.originalText) {
            sendBtn.dataset.originalText = sendBtn.innerHTML;
        }
        sendBtn.innerHTML = isResend ? 'Resending...' : 'Sending Code...';
    }

    const emailInput = form.querySelector('input[name="email"]');
    const email = String(emailInput?.value || lastForgotEmail || '').trim().toLowerCase();

    if (!email) {
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.style.opacity = '';
            sendBtn.style.cursor = '';
            sendBtn.innerHTML = sendBtn.dataset.originalText || (isResend ? 'Resend code' : 'Send Reset Code');
        }
        showAlert('Please enter your registered email.', 'error');
        return;
    }

    showAlert(isResend ? 'Resending reset code...' : 'Sending reset code...', 'success');

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
    } finally {
        if (sendBtn) {
            sendBtn.disabled = false;
            sendBtn.style.opacity = '';
            sendBtn.style.cursor = '';
            sendBtn.innerHTML = sendBtn.dataset.originalText || (isResend ? 'Resend code' : 'Send Reset Code');
        }
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
            throw new Error(getApiErrorMessage(data, 'Unable to reset password.'));
        }

        stopForgotOtpTimer();
        form.reset();
        resetForgotUi(form);

        const successMessage = data.message || 'Password updated successfully. You can login with your new password.';

        // Real-app flow: after reset, return to the login screen instead of staying on reset steps.
        localStorage.setItem('kryvex_password_reset_message', successMessage);
        window.location.replace('/login');
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

        if (data.requires_2fa) {
            const loginForm = document.getElementById('loginForm');
            const login2FaForm = document.getElementById('login2FaForm');
            const emailText = document.getElementById('login2FaEmailText');
            const tempTokenInput = document.getElementById('login2FaTempToken');
            const emailInput = document.getElementById('login2FaEmail');

            if (loginForm) loginForm.style.setProperty('display', 'none', 'important');
            if (login2FaForm) login2FaForm.style.setProperty('display', 'grid', 'important');

            if (emailText) emailText.textContent = data.data?.email || '';
            if (tempTokenInput) tempTokenInput.value = data.data?.temp_token || '';
            if (emailInput) emailInput.value = data.data?.email || '';

            startLogin2FaOtpTimer(data.data?.expires_in_seconds || 120);
            showAlert(data.message || 'Two-Factor Authentication code sent to your email.', 'success');
            return;
        }

        saveAuthSession(data.data);

        showAlert(data.message || 'Login successful.', 'success');

        const urlParams = new URLSearchParams(window.location.search);
        let redirectUrl = urlParams.get('redirect');
        const internalPay = urlParams.get('internal_pay');

        if (!redirectUrl && internalPay) {
            redirectUrl = `/wallet?internal_pay=${encodeURIComponent(internalPay)}`;
        }

        setTimeout(() => {
            if (redirectUrl && (redirectUrl.startsWith('/') || redirectUrl.startsWith(window.location.origin))) {
                window.location.href = redirectUrl;
            } else {
                window.location.replace('/');
            }
        }, 500);
    } catch (error) {
        showAlert(error.message || 'Something went wrong.', 'error');
    }
}

window.login2FaOtpTimer = null;

function startLogin2FaOtpTimer(seconds = 120) {
    if (window.login2FaOtpTimer) {
        clearInterval(window.login2FaOtpTimer);
        window.login2FaOtpTimer = null;
    }

    const timerWrap = document.getElementById('login2FaTimerWrap');
    const countdownText = document.getElementById('login2FaTimer');
    const resendBtn = document.getElementById('login2FaResendBtn');
    const submitBtn = document.getElementById('login2FaForm')?.querySelector('button[type="submit"]');

    if (timerWrap) timerWrap.style.setProperty('display', 'inline-flex', 'important');
    if (resendBtn) resendBtn.style.setProperty('display', 'none', 'important');
    if (submitBtn) submitBtn.disabled = false;

    let remaining = Number(seconds) > 0 ? Number(seconds) : 120;

    const render = () => {
        const textNode = document.getElementById('login2FaTimer');
        if (textNode) {
            textNode.textContent = `${Math.max(remaining, 0)}s`;
        }
    };

    render();

    window.login2FaOtpTimer = setInterval(() => {
        remaining -= 1;
        render();

        if (remaining <= 0) {
            clearInterval(window.login2FaOtpTimer);
            window.login2FaOtpTimer = null;

            if (timerWrap) timerWrap.style.setProperty('display', 'none', 'important');
            if (resendBtn) resendBtn.style.setProperty('display', 'inline-block', 'important');
            if (submitBtn) submitBtn.disabled = true;
            showAlert('2FA verification code expired. Please click Resend OTP.', 'error');
        }
    }, 1000);
}

function stopLogin2FaOtpTimer() {
    if (window.login2FaOtpTimer) {
        clearInterval(window.login2FaOtpTimer);
        window.login2FaOtpTimer = null;
    }
}
window.startLogin2FaOtpTimer = startLogin2FaOtpTimer;
window.stopLogin2FaOtpTimer = stopLogin2FaOtpTimer;

function setupLogin2FaForm() {
    const loginForm = document.getElementById('loginForm');
    const login2FaForm = document.getElementById('login2FaForm');
    const backBtn = document.getElementById('login2FaBackToLogin');
    const resendBtn = document.getElementById('login2FaResendBtn');
    const codeInput = document.getElementById('login2FaCode');

    if (codeInput) {
        codeInput.addEventListener('input', () => {
            codeInput.value = codeInput.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    if (backBtn && loginForm && login2FaForm) {
        backBtn.addEventListener('click', (e) => {
            if (e) e.preventDefault();
            stopLogin2FaOtpTimer();

            login2FaForm.style.setProperty('display', 'none', 'important');
            loginForm.style.setProperty('display', 'grid', 'important');

            const loginSubmitBtn = loginForm.querySelector('button[type="submit"]');
            if (loginSubmitBtn) {
                loginSubmitBtn.disabled = false;
                loginSubmitBtn.style.opacity = '';
                loginSubmitBtn.style.cursor = '';
                loginSubmitBtn.innerHTML = loginSubmitBtn.dataset.originalText || 'Login';
            }

            if (codeInput) codeInput.value = '';
            showAlert('Enter email and password to log in.', 'success');
        });
    }

    if (resendBtn) {
        resendBtn.addEventListener('click', async () => {
            const email = document.getElementById('login2FaEmail')?.value;
            const tempToken = document.getElementById('login2FaTempToken')?.value;

            if (!email || !tempToken) return;

            resendBtn.disabled = true;
            resendBtn.textContent = 'Resending...';

            try {
                const response = await fetch('/api/auth/login/resend-2fa', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, temp_token: tempToken }),
                });

                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Resend failed.');

                if (codeInput) codeInput.value = '';
                startLogin2FaOtpTimer(data.data?.expires_in_seconds || 120);
                showAlert(data.message || 'New 2FA code sent to your email.', 'success');
            } catch (err) {
                showAlert(err.message || 'Resend failed.', 'error');
            } finally {
                resendBtn.disabled = false;
                resendBtn.textContent = '🔄 Resend OTP';
            }
        });
    }

    if (login2FaForm) {
        login2FaForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = login2FaForm.querySelector('button[type="submit"]');
            const email = document.getElementById('login2FaEmail')?.value;
            const tempToken = document.getElementById('login2FaTempToken')?.value;
            const code = codeInput?.value.trim();

            if (!code || code.length !== 6) {
                showAlert('Please enter a valid 6-digit OTP code.', 'error');
                return;
            }

            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Verifying...'; }

            try {
                const response = await fetch('/api/auth/login/verify-2fa', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, temp_token: tempToken, code }),
                });

                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Verification failed.');

                stopLogin2FaOtpTimer();
                saveAuthSession(data.data);
                showAlert(data.message || 'Login successful.', 'success');

                const urlParams = new URLSearchParams(window.location.search);
                let redirectUrl = urlParams.get('redirect');
                const internalPay = urlParams.get('internal_pay');

                if (!redirectUrl && internalPay) {
                    redirectUrl = `/wallet?internal_pay=${encodeURIComponent(internalPay)}`;
                }

                setTimeout(() => {
                    if (redirectUrl && (redirectUrl.startsWith('/') || redirectUrl.startsWith(window.location.origin))) {
                        window.location.href = redirectUrl;
                    } else {
                        window.location.replace('/');
                    }
                }, 500);
            } catch (err) {
                showAlert(err.message || '2FA verification failed.', 'error');
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Verify & Login'; }
            }
        });
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
    const message = localStorage.getItem('kryvex_password_reset_message');

    if (!message) {
        return;
    }

    localStorage.removeItem('kryvex_password_reset_message');

    const loginForm = document.getElementById('loginForm');
    const forgotForm = document.getElementById('forgotPasswordForm');

    if (forgotForm) forgotForm.style.setProperty('display', 'none', 'important');
    if (loginForm) loginForm.style.setProperty('display', 'grid', 'important');

    showAlert(message, 'success');
}

function saveAuthSession(data) {
    const tokens = data.tokens || {};
    const user = data.user || {};

    // Clear previous user cached data
    localStorage.removeItem('kryvex_trading_accounts_cache');
    localStorage.removeItem('kryvex_cached_trading_accounts');
    localStorage.removeItem('kryvex_active_account_uid');
    localStorage.removeItem('kryvex_wallet_data');
    localStorage.removeItem('kryvex_saved_bank_details');
    try { window.__cachedTradingAccounts = null; } catch(e) {}

    localStorage.setItem('kryvex_access_token', tokens.access_token || '');
    localStorage.setItem('kryvex_refresh_token', tokens.refresh_token || '');
    localStorage.setItem('kryvex_user', JSON.stringify(user));
    localStorage.setItem('kryvex_last_activity', String(Date.now()));
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

    if (alert) {
        alert.className = 'auth-alert';
        alert.textContent = '';
    }

    showAuthToast(message, type);
}

function showAuthToast(message, type = 'success') {
    if (!message) return;

    if (typeof window.showToast === 'function') {
        window.showToast({
            type: type === 'error' ? 'error' : 'success',
            title: type === 'error' ? 'Error' : 'Success',
            message: message,
            duration: 3500
        });
        return;
    }

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
