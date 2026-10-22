// Kryvex Unified Settings Page JS
// Handles profile loading, broker onboarding, MT5 credentials, base currency, and security reset flows.

let lenisInstance = null;

document.addEventListener('DOMContentLoaded', () => {
    initLenisSmoothScroll();
    initScrollProgressAndTopBtn();
    setupScrollReveal();

    loadDashboardUser();
    loadBrokerStatus();
    setupBrokerModals();
    setupChangePasswordForm();
    setupProfileForgotPasswordForm();
    setupNameEditing();
    setupNotificationPreferences();
    setupAccountProfileModal();
    setupTwoFactorModal();
    setupBankDetailsModal();
    setupTradeSoundModal();
    setupColorPrefModal();
    setupShortcutsToggleAndModal();
    setupNotifLanguageModal();
    setupOrderConfirmModal();
    setupSubPageNavigation();
    setupDownloadPersonalDataModal();
    setupExportTransactionRecordsModal();
    setupCloseAccountModal();
});

function setupSubPageNavigation() {
    const mainView = document.getElementById('mainSettingsView');
    const accountProfileView = document.getElementById('accountProfileSubPageView');
    const supportView = document.getElementById('supportSubPageView');
    const backBtnAccount = document.getElementById('btnBackToSettings');
    const backBtnSupport = document.getElementById('btnBackToSettingsFromSupport');

    function resetLenisAndScroll() {
        setTimeout(() => {
            if (lenisInstance) {
                lenisInstance.resize();
                lenisInstance.scrollTo(0, { immediate: true });
            } else {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            if (typeof updateScrollEffects === 'function') {
                updateScrollEffects();
            }
            if (typeof setupScrollReveal === 'function') {
                setupScrollReveal();
            }
        }, 50);
    }

    function showMainSettings() {
        if (mainView) mainView.style.display = 'block';
        if (accountProfileView) accountProfileView.style.display = 'none';
        if (supportView) supportView.style.display = 'none';
        resetLenisAndScroll();
    }

    function showAccountProfileSubPage() {
        if (mainView) mainView.style.display = 'none';
        if (supportView) supportView.style.display = 'none';
        if (accountProfileView) accountProfileView.style.display = 'block';
        resetLenisAndScroll();
    }

    function showSupportSubPage() {
        if (mainView) mainView.style.display = 'none';
        if (accountProfileView) accountProfileView.style.display = 'none';
        if (supportView) supportView.style.display = 'block';
        resetLenisAndScroll();
    }

    document.querySelectorAll('.btn-open-account-profile').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            showAccountProfileSubPage();
            const url = new URL(window.location);
            url.searchParams.set('tab', 'account-profile');
            window.history.pushState({}, '', url);
        });
    });

    document.querySelectorAll('.btn-open-support').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            showSupportSubPage();
            const url = new URL(window.location);
            url.searchParams.set('tab', 'support');
            window.history.pushState({}, '', url);
        });
    });

    if (backBtnAccount) {
        backBtnAccount.addEventListener('click', () => {
            showMainSettings();
            const url = new URL(window.location);
            url.searchParams.delete('tab');
            window.history.pushState({}, '', url);
        });
    }

    if (backBtnSupport) {
        backBtnSupport.addEventListener('click', () => {
            showMainSettings();
            const url = new URL(window.location);
            url.searchParams.delete('tab');
            window.history.pushState({}, '', url);
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');

    if (tabParam === 'account-profile' || tabParam === 'account_profile' || tabParam === 'accountProfile' || tabParam === 'bank' || tabParam === 'bank-details') {
        showAccountProfileSubPage();
        if (tabParam === 'bank' || tabParam === 'bank-details') {
            setTimeout(() => {
                const modal = document.getElementById('modalBankDetails');
                if (modal) {
                    modal.hidden = false;
                    modal.style.display = 'flex';
                    modal.classList.add('is-open');
                }
            }, 250);
        }
    } else if (tabParam === 'support') {
        showSupportSubPage();
    } else {
        showMainSettings();
    }
}

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

        if (user.status === 'deleted' || user.deletion_status === 'approved') {
            if (typeof window.handleAccountDeleted === 'function') {
                window.handleAccountDeleted();
            } else {
                localStorage.clear();
                sessionStorage.clear();
                alert('Your account deletion request has been approved by administrator. Your account is now closed.');
                window.location.href = '/login?account_deleted=1';
            }
            return;
        }

        localStorage.setItem('kryvex_user', JSON.stringify(user));

        const name = user.name || 'Kryvex User';
        const email = user.email || '--';
        const clientId = user.client_id || '--';
        const mobile = user.mobile || user.phone || user.phone_number || '--';
        const kycStatus = user.kyc_status || user.kyc || 'unverified';
        const accountStatus = user.status || 'active';
        const emailVerified = user.email_verified || Boolean(user.email_verified_at);
        const is2fa = Boolean(user.is_2fa_enabled);
        const memberSince = user.created_at ? new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Jul 8, 2026';
        const uid = user.uid || user.id || user._id || '--';
        const loginType = user.google_id ? 'Google Login' : 'Email Login';

        document.querySelectorAll('.user-display-name').forEach(el => el.textContent = name);
        document.querySelectorAll('.user-display-email').forEach(el => el.textContent = email);
        document.querySelectorAll('.user-display-initial').forEach(el => el.textContent = String(name || email || 'U').charAt(0).toUpperCase());

        setText('accountProfileMobileText', mobile !== '--' ? mobile : 'Not linked');
        setText('accountProfileClientIdText', clientId);
        setText('accountProfileMemberSinceText', memberSince);

        const kycBadge = document.getElementById('accountProfileKycStatusBadge');
        if (kycBadge) {
            const isVerified = ['verified', 'approved'].includes(String(kycStatus).toLowerCase());
            const isPending = String(kycStatus).toLowerCase() === 'pending';
            kycBadge.textContent = isVerified ? 'Verified' : (isPending ? 'Under Review' : 'Unverified');
            kycBadge.style.color = isVerified ? '#22c55e' : (isPending ? '#f59e0b' : '#ef4444');
        }

        const tfaText = document.getElementById('accountProfile2faStatusText');
        if (tfaText) {
            tfaText.textContent = is2fa ? 'Enabled' : 'Disabled';
            tfaText.style.color = is2fa ? '#22c55e' : '#f59e0b';
        }

        const copyClientIdBtn = document.getElementById('btnCopyAccountProfileClientId');
        if (copyClientIdBtn) {
            copyClientIdBtn.onclick = () => {
                navigator.clipboard.writeText(clientId);
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Copied!', message: 'Client ID copied to clipboard.' });
                }
            };
        }

        renderDeletionStatusUI(user);

        let rawMainBal = 0;
        let rawTradingBal = 0;
        let rawMainBalInr = 0;
        let rawTradingBalInr = 0;
        let inrRate = 85;
        let prefCurrency = localStorage.getItem('kryvex_active_currency') || localStorage.getItem('kryvex_preferred_currency') || 'USD';

        setText('subPageBrokerNameText', user.broker_type === 'mt5' ? 'MetaTrader 5' : 'Kryvex Trading');
        setText('subPageBrokerPlanText', user.kryvex_plan || user.mt5_plan || 'Advance');
        setText('subPageReferralCodeText', user.referral_code || user.client_id || '30EBFD7D');
        setText('subPageReferralEarnedText', `${user.total_referrals || 0} referred · earned $${Number(user.referral_earnings || 0).toFixed(2)}`);
        setText('subPageUuidFooterText', uid);

        const copyUuidBtn = document.getElementById('btnCopySubPageUuidFooter');
        if (copyUuidBtn) {
            copyUuidBtn.onclick = () => {
                navigator.clipboard.writeText(uid);
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Copied!', message: 'User UUID copied to clipboard.' });
                }
            };
        }

        // Fetch real wallet balances & INR conversion rate
        try {
            const token = localStorage.getItem('kryvex_access_token');
            const wRes = await fetch('/api/wallet', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
            const wData = await wRes.json();
            if (wData.success && wData.data) {
                const w = wData.data.wallet || {};
                rawMainBal = Number(w.demo_main_balance ?? w.main_wallet_balance ?? w.main_balance ?? w.real_available_balance ?? wData.data.main_balance ?? 0);
                rawTradingBal = Number(w.demo_available_balance ?? w.trading_balance ?? w.demo_trading_balance ?? wData.data.trading_balance ?? 0);
                rawMainBalInr = Number(w.demo_main_balance_inr ?? 0);
                rawTradingBalInr = Number(w.demo_available_balance_inr ?? 0);

                if (wData.data.bank_details && wData.data.bank_details.account_number) {
                    setText('subPageBankStatusText', `Linked (${wData.data.bank_details.bank_name || 'Bank'} ****${String(wData.data.bank_details.account_number).slice(-4)})`);
                }
            }
        } catch (e) {}

        try {
            const token = localStorage.getItem('kryvex_access_token');
            const bRes = await fetch('/api/broker/status', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
            const bData = await bRes.json();
            if (bData.success && bData.data) {
                if (!rawMainBal && bData.data.main_balance) {
                    rawMainBal = Number(bData.data.main_balance);
                }
                if (!rawTradingBal && bData.data.trading_balance) {
                    rawTradingBal = Number(bData.data.trading_balance);
                }
            }
        } catch (e) {}

        try {
            const token = localStorage.getItem('kryvex_access_token');
            const rateRes = await fetch('/api/wallet/inr-rate', { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } });
            const rateData = await rateRes.json();
            if (rateData.success && rateData.data && rateData.data.usdt_inr_rate) {
                inrRate = Number(rateData.data.usdt_inr_rate);
            }
        } catch (e) {}

        function syncNavbarCurrencyToggleUI(curr) {
            const isINR = curr === 'INR';
            const navBtnInr = document.getElementById('navCurrencyBtnInr');
            const navBtnUsdt = document.getElementById('navCurrencyBtnUsdt');
            if (navBtnInr && navBtnUsdt) {
                navBtnInr.style.background = isINR ? '#6366f1' : 'transparent';
                navBtnInr.style.color = isINR ? '#ffffff' : 'rgba(255,255,255,0.6)';
                navBtnInr.style.fontWeight = isINR ? '700' : '600';
                navBtnInr.style.boxShadow = isINR ? '0 2px 8px rgba(99,102,241,0.4)' : 'none';

                navBtnUsdt.style.background = !isINR ? '#6366f1' : 'transparent';
                navBtnUsdt.style.color = !isINR ? '#ffffff' : 'rgba(255,255,255,0.6)';
                navBtnUsdt.style.fontWeight = !isINR ? '700' : '600';
                navBtnUsdt.style.boxShadow = !isINR ? '0 2px 8px rgba(99,102,241,0.4)' : 'none';
            }
        }

        function updateSubPageWalletDisplay(curr) {
            prefCurrency = curr;
            localStorage.setItem('kryvex_active_currency', curr);
            localStorage.setItem('kryvex_preferred_currency', curr);
            setText('subPagePrefCurrText', curr);

            const btnUsd = document.getElementById('btnCurrToggleUsd');
            const btnInr = document.getElementById('btnCurrToggleInr');

            if (btnUsd && btnInr) {
                if (curr === 'INR') {
                    btnInr.style.background = '#8b5cf6';
                    btnInr.style.color = '#ffffff';
                    btnUsd.style.background = 'transparent';
                    btnUsd.style.color = 'rgba(255,255,255,0.6)';
                } else {
                    btnUsd.style.background = '#8b5cf6';
                    btnUsd.style.color = '#ffffff';
                    btnInr.style.background = 'transparent';
                    btnInr.style.color = 'rgba(255,255,255,0.6)';
                }
            }

            const snapEl = document.getElementById('subPageWalletSnapshotText');
            if (snapEl) {
                if (curr === 'INR') {
                    const mainInr = rawMainBalInr > 0 ? rawMainBalInr : (rawMainBal * inrRate);
                    const tradingInr = rawTradingBalInr > 0 ? rawTradingBalInr : (rawTradingBal * inrRate);
                    snapEl.textContent = `Main ₹${mainInr.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} · Trading ₹${tradingInr.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                } else {
                    snapEl.textContent = `Main $${rawMainBal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} · Trading $${rawTradingBal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
                }
            }

            syncNavbarCurrencyToggleUI(curr);
        }

        updateSubPageWalletDisplay(prefCurrency);

        const btnUsd = document.getElementById('btnCurrToggleUsd');
        const btnInr = document.getElementById('btnCurrToggleInr');
        if (btnUsd) {
            btnUsd.onclick = () => {
                updateSubPageWalletDisplay('USD');
                window.dispatchEvent(new CustomEvent('kryvex_currency_changed', { detail: { currency: 'USD' } }));
            };
        }
        if (btnInr) {
            btnInr.onclick = () => {
                updateSubPageWalletDisplay('INR');
                window.dispatchEvent(new CustomEvent('kryvex_currency_changed', { detail: { currency: 'INR' } }));
            };
        }

        window.addEventListener('kryvex_currency_changed', (e) => {
            const newCur = e.detail?.currency || 'USD';
            if (newCur !== prefCurrency) {
                updateSubPageWalletDisplay(newCur);
            }
        });

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



let settingsForgotOtpInterval = null;

function startForgotOtpCountdown(seconds = 120) {
    if (settingsForgotOtpInterval) clearInterval(settingsForgotOtpInterval);

    const timerWrap = document.getElementById('forgotOtpTimerWrap');
    const countdownText = document.getElementById('forgotOtpCountdownText');
    const resendBtn = document.getElementById('btnResendForgotOtpInSettings');

    if (timerWrap) timerWrap.style.display = 'inline-block';
    if (resendBtn) resendBtn.style.display = 'none';

    let remaining = seconds;
    if (countdownText) countdownText.textContent = `${remaining}s`;

    settingsForgotOtpInterval = setInterval(() => {
        remaining -= 1;
        if (countdownText) countdownText.textContent = `${remaining}s`;

        if (remaining <= 0) {
            clearInterval(settingsForgotOtpInterval);
            settingsForgotOtpInterval = null;

            if (timerWrap) timerWrap.style.display = 'none';
            if (resendBtn) resendBtn.style.display = 'inline-block';

            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'warning', title: 'OTP Expired', message: 'The 6-digit OTP code has expired. Please click Resend OTP for a new code.' });
            }
        }
    }, 1000);
}

function stopForgotOtpCountdown() {
    if (settingsForgotOtpInterval) clearInterval(settingsForgotOtpInterval);
    settingsForgotOtpInterval = null;
}

function validatePasswordClientRules(pass, confirmPass) {
    if (!pass) return 'New password is required.';
    if (pass.length < 8) return 'Password must be at least 8 characters long.';
    if (!/[A-Z]/.test(pass) || !/[a-z]/.test(pass)) return 'Password must contain both uppercase and lowercase letters.';
    if (!/[0-9]/.test(pass)) return 'Password must contain at least one number.';
    if (!/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pass)) return 'Password must contain at least one special character (!@#$%^&*).';
    if (pass !== confirmPass) return 'Password confirmation does not match.';
    return null;
}

function setupChangePasswordForm() {
    // Eye toggles for all password inputs
    document.querySelectorAll('.btn-toggle-eye').forEach(btn => {
        btn.onclick = (e) => {
            e.preventDefault();
            const targetId = btn.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            const isPass = input.type === 'password';
            input.type = isPass ? 'text' : 'password';
            btn.textContent = isPass ? '🙈' : '👁';
        };
    });

    const form = document.getElementById('changePasswordForm');
    const forgotView = document.getElementById('forgotPasswordViewInSettings');
    const forgotLink = document.getElementById('btnForgotPassInSettings');
    const backBtn = document.getElementById('btnBackToChangePassForm');
    const modalTitle = document.getElementById('changePassModalTitle');
    const sendOtpBtn = document.getElementById('btnSendResetOtpInSettings');
    const resendOtpBtn = document.getElementById('btnResendForgotOtpInSettings');
    const resetOtpForm = document.getElementById('forgotResetPasswordFormInSettings');
    const stepSendOtp = document.getElementById('forgotStepSendOtp');
    const forgotEmailText = document.getElementById('userForgotEmailText');

    let userEmail = '';
    try {
        const u = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
        userEmail = u.email || '';
    } catch (e) {}

    if (forgotEmailText) {
        forgotEmailText.textContent = userEmail || 'your email';
    }

    if (forgotLink) {
        forgotLink.onclick = () => {
            if (form) form.style.display = 'none';
            if (forgotView) forgotView.style.display = 'block';
            if (modalTitle) modalTitle.textContent = 'Reset Forgotten Password';
        };
    }

    if (backBtn) {
        backBtn.onclick = () => {
            stopForgotOtpCountdown();
            if (forgotView) forgotView.style.display = 'none';
            if (form) form.style.display = 'block';
            if (modalTitle) modalTitle.textContent = 'Change Login Password';
        };
    }

    if (sendOtpBtn) {
        sendOtpBtn.onclick = async () => {
            if (!userEmail) {
                alert('User email not found. Please log in again.');
                return;
            }
            sendOtpBtn.disabled = true;
            sendOtpBtn.textContent = 'Sending OTP...';

            try {
                const res = await fetch('/api/auth/forgot-password/send-code', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: userEmail })
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(data.message || 'Unable to send OTP.');

                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'OTP Sent', message: data.message || `Reset code sent to ${userEmail}.` });
                } else {
                    alert(data.message || `Reset code sent to ${userEmail}.`);
                }

                if (stepSendOtp) stepSendOtp.style.display = 'none';
                if (resetOtpForm) resetOtpForm.style.display = 'block';

                startForgotOtpCountdown(data.data?.expires_in_seconds || 120);
            } catch (err) {
                alert(err.message || 'Failed to send OTP.');
            } finally {
                sendOtpBtn.disabled = false;
                sendOtpBtn.textContent = 'Send OTP to Email';
            }
        };
    }

    if (resendOtpBtn) {
        resendOtpBtn.onclick = async () => {
            if (!userEmail) return;
            resendOtpBtn.disabled = true;
            resendOtpBtn.textContent = 'Resending...';

            try {
                const res = await fetch('/api/auth/forgot-password/send-code', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: userEmail })
                });
                const data = await res.json();
                if (!res.ok || !data.success) throw new Error(getDashboardApiError(data, 'Unable to resend OTP.'));

                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'OTP Resent', message: data.message || `New reset code sent to ${userEmail}.` });
                }

                const inputCode = document.getElementById('inputResetOtp');
                if (inputCode) inputCode.value = '';

                startForgotOtpCountdown(data.data?.expires_in_seconds || 120);
            } catch (err) {
                showDashboardAlert(err.message || 'Resend failed.', 'error');
            } finally {
                resendOtpBtn.disabled = false;
                resendOtpBtn.textContent = '🔄 Resend OTP';
            }
        };
    }

    if (resetOtpForm) {
        resetOtpForm.onsubmit = async (e) => {
            e.preventDefault();
            const submitBtn = resetOtpForm.querySelector('button[type="submit"]');
            const code = document.getElementById('inputResetOtp')?.value.trim();
            const newPassword = document.getElementById('inputForgotNewPass')?.value;
            const confirmPassword = document.getElementById('inputForgotConfirmPass')?.value;

            if (!code) {
                showDashboardAlert('Please enter 6-digit OTP code.', 'error');
                return;
            }

            const ruleErr = validatePasswordClientRules(newPassword, confirmPassword);
            if (ruleErr) {
                showDashboardAlert(ruleErr, 'error');
                return;
            }

            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Resetting...'; }

            try {
                // Step A: Verify Code
                const verifyRes = await fetch('/api/auth/forgot-password/verify-code', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: userEmail, code })
                });
                const verifyData = await verifyRes.json();
                if (!verifyRes.ok || !verifyData.success) throw new Error(getDashboardApiError(verifyData, 'Invalid or expired OTP code.'));

                const resetToken = verifyData.data?.reset_token || '';

                // Step B: Reset Password
                const resetRes = await fetch('/api/auth/forgot-password/reset', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        email: userEmail,
                        code: code,
                        reset_token: resetToken,
                        password: newPassword,
                        password_confirmation: confirmPassword
                    })
                });
                const resetData = await resetRes.json();
                if (!resetRes.ok || !resetData.success) throw new Error(getDashboardApiError(resetData, 'Failed to reset password.'));

                stopForgotOtpCountdown();

                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Password Reset Successful', message: resetData.message || 'Password reset successfully!' });
                } else {
                    alert(resetData.message || 'Password reset successfully!');
                }

                const modal = document.getElementById('modalChangePassword');
                if (modal) modal.hidden = true;

                resetOtpForm.reset();
                if (stepSendOtp) stepSendOtp.style.display = 'block';
                if (resetOtpForm) resetOtpForm.style.display = 'none';
                if (forgotView) forgotView.style.display = 'none';
                if (form) form.style.display = 'block';
                if (modalTitle) modalTitle.textContent = 'Change Login Password';
            } catch (err) {
                showDashboardAlert(err.message || 'Password reset failed.', 'error');
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Reset Password'; }
            }
        };
    }

    if (form) {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');

            const token = localStorage.getItem('kryvex_access_token');
            const rawPayload = formToObject(form);
            const payload = {
                current_password: rawPayload.current_password,
                password: rawPayload.password || rawPayload.new_password,
                password_confirmation: rawPayload.password_confirmation || rawPayload.new_password_confirmation,
            };

            const ruleErr = validatePasswordClientRules(payload.password, payload.password_confirmation);
            if (ruleErr) {
                showDashboardAlert(ruleErr, 'error');
                return;
            }

            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Updating...'; }

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
                const modal = document.getElementById('modalChangePassword');
                if (modal) modal.hidden = true;
            } catch (error) {
                showDashboardAlert(error.message || 'Something went wrong.', 'error');
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Update Password'; }
            }
        });
    }
}

function setupProfileForgotPasswordForm() {
    const form = document.getElementById('profileForgotPasswordForm');
    const openButton = document.getElementById('openProfileForgotPassword');
    const sendButton = document.getElementById('profileForgotSendCodeButton');
    const resendButton = document.getElementById('profileForgotResendButton');
    const verifyButton = document.getElementById('profileForgotVerifyCodeButton');
    const codeInput = form?.querySelector('input[name="code"]');

    if (!form) return;

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

function getDashboardApiError(data, fallbackMsg = 'Something went wrong.') {
    if (!data) return fallbackMsg;
    if (data.message) return data.message;
    if (data.error) return data.error;
    if (data.errors && typeof data.errors === 'object') {
        const firstKey = Object.keys(data.errors)[0];
        if (firstKey && data.errors[firstKey]) {
            const err = data.errors[firstKey];
            return Array.isArray(err) ? err[0] : String(err);
        }
    }
    return fallbackMsg;
}

function showDashboardAlert(message, type = 'success') {
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

function capitalize(value) {
    const text = String(value || '--');
    return text.charAt(0).toUpperCase() + text.slice(1).replaceAll('_', ' ');
}

function setupNameEditing() {
    const form = document.getElementById('profileNicknameForm');
    const input = document.getElementById('inputProfileNickname');
    const modal = document.getElementById('modalProfileEdit');
    const submitBtn = document.getElementById('btnSaveProfileNickname') || form?.querySelector('button[type="submit"]');

    function populateNameInput() {
        if (!input) return;
        try {
            const userStr = localStorage.getItem('kryvex_user');
            const user = userStr ? JSON.parse(userStr) : {};
            input.value = user.name || user.nickname || '';
        } catch (e) {}
    }

    document.querySelectorAll('[data-open-modal="modalProfileEdit"]').forEach(btn => {
        btn.addEventListener('click', () => {
            populateNameInput();
            if (modal) {
                modal.hidden = false;
                modal.style.display = 'flex';
            }
        });
    });

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const newName = (input?.value || '').trim();
            if (newName.length < 2) {
                showDashboardToast('Name must be at least 2 characters long.', 'error');
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving...';
            }

            const token = localStorage.getItem('kryvex_access_token');

            try {
                const response = await fetch('/api/auth/update-profile', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${token}`,
                    },
                    body: JSON.stringify({ name: newName, nickname: newName }),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to update profile name.');
                }

                const freshUser = data.data?.user || {};
                const userStr = localStorage.getItem('kryvex_user');
                const prevUser = userStr ? JSON.parse(userStr) : {};
                const mergedUser = { ...prevUser, ...freshUser, name: newName, nickname: newName };
                localStorage.setItem('kryvex_user', JSON.stringify(mergedUser));

                // Update all UI elements on page
                document.querySelectorAll('.user-display-name').forEach(el => el.textContent = newName);
                document.querySelectorAll('.user-display-initial').forEach(el => el.textContent = String(newName || 'U').charAt(0).toUpperCase());
                setText('dashName', newName);
                setText('profileName', newName);
                setText('profileAvatar', String(newName || 'U').charAt(0).toUpperCase());

                if (typeof window.setNavbarUserName === 'function') {
                    window.setNavbarUserName();
                }

                window.dispatchEvent(new CustomEvent('kryvex_user_updated', { detail: mergedUser }));

                showDashboardToast(data.message || 'Profile name updated successfully.', 'success');

                if (modal) {
                    modal.hidden = true;
                    modal.style.display = 'none';
                }
            } catch (error) {
                showDashboardToast(error.message || 'Unable to update name.', 'error');
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Name';
                }
            }
        });
    }

    // Legacy inline editing fallback support
    const editBtn = document.getElementById('editNameButton');
    const cancelBtn = document.getElementById('cancelNameButton');
    const saveBtn = document.getElementById('saveNameButton');
    const displayRow = document.getElementById('nameDisplayRow');
    const editRow = document.getElementById('nameEditRow');
    const nameInput = document.getElementById('editNameInput');
    const dashName = document.getElementById('dashName');

    if (editBtn && cancelBtn && saveBtn && displayRow && editRow && nameInput && dashName) {
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
                    body: JSON.stringify({ name: newName, nickname: newName }),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to update profile.');
                }

                const freshUser = data.data.user || {};
                const userStr = localStorage.getItem('kryvex_user');
                const prevUser = userStr ? JSON.parse(userStr) : {};
                const mergedUser = { ...prevUser, ...freshUser, name: newName, nickname: newName };
                localStorage.setItem('kryvex_user', JSON.stringify(mergedUser));

                document.querySelectorAll('.user-display-name').forEach(el => el.textContent = newName);
                document.querySelectorAll('.user-display-initial').forEach(el => el.textContent = String(newName || 'U').charAt(0).toUpperCase());
                setText('dashName', freshUser.name || newName);
                setText('profileName', freshUser.name || newName);
                setText('profileAvatar', String(newName || 'U').charAt(0).toUpperCase());

                if (typeof window.setNavbarUserName === 'function') {
                    window.setNavbarUserName();
                }

                window.dispatchEvent(new CustomEvent('kryvex_user_updated', { detail: mergedUser }));

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

    if (!loadingEl) return;

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

            checkPendingCurrencyRequest();
        } else {
            if (setDisplay) setDisplay.style.display = 'none';
            if (notSetDisplay) notSetDisplay.style.display = 'block';
        }
    } catch (e) {
        if (loadingEl) loadingEl.textContent = 'Unable to load';
    }
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

        setText('ccCurrentCurrency', d.current_currency === 'INR' ? '🇮🇳 INR' : '🇺🇸 USDT');
        setText('ccRequestedCurrency', d.requested_currency === 'INR' ? '🇮🇳 INR' : '🇺🇸 USDT');
        setText('ccCurrentBalance', d.current_balance_formatted);
        setText('ccExchangeRate', `${d.rate_type || 'Rate'}: 1 USDT = \u20b9${Number(d.rate).toFixed(2)}`);
        setText('ccReceiveBalance', d.receive_balance_formatted);

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

function setupBankDetailsModal() {
    const modal = document.getElementById('modalBankDetails');
    const form = document.getElementById('bankDetailsForm');
    const statusText = document.getElementById('subPageBankStatusText');
    const btnOpenSub = document.getElementById('btnOpenSubPageBankModal');

    if (!form) return;

    async function loadUserBankDetails() {
        const token = localStorage.getItem('kryvex_access_token');
        if (!token) return;

        try {
            const res = await fetch('/api/user/bank-details', {
                headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }
            });
            const data = await res.json();
            if (data.success && data.data && data.data.bank_details) {
                const b = data.data.bank_details;
                const accNameInput = form.querySelector('input[name="account_name"]');
                const bankNameInput = form.querySelector('input[name="bank_name"]');
                const accNumInput = form.querySelector('input[name="account_number"]');
                const ifscInput = form.querySelector('input[name="ifsc_code"]');
                const upiInput = form.querySelector('input[name="upi_id"]');

                if (accNameInput) accNameInput.value = b.account_name || '';
                if (bankNameInput) bankNameInput.value = b.bank_name || '';
                if (accNumInput) accNumInput.value = b.account_number || '';
                if (ifscInput) ifscInput.value = b.ifsc_code || '';
                if (upiInput) upiInput.value = b.upi_id || '';

                const summaryText = `Linked (${b.bank_name || 'Bank'} ****${String(b.account_number || '').slice(-4)})`;
                if (statusText) statusText.textContent = summaryText;
                if (btnOpenSub) btnOpenSub.textContent = 'Edit bank';

                localStorage.setItem('kryvex_user_bank_cache', JSON.stringify(b));
            }
        } catch (e) {}
    }

    loadUserBankDetails();

    document.querySelectorAll('[data-open-modal="modalBankDetails"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            loadUserBankDetails();
            if (modal) {
                modal.hidden = false;
                modal.style.display = 'flex';
                modal.classList.add('is-open');
            }
        });
    });

    document.querySelectorAll('[data-close-modal="modalBankDetails"]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (modal) {
                modal.hidden = true;
                modal.style.display = 'none';
                modal.classList.remove('is-open');
            }
        });
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = form.querySelector('button[type="submit"]');
        const token = localStorage.getItem('kryvex_access_token');

        if (!token) {
            if (typeof window.showToast === 'function') window.showToast({ type: 'error', title: 'Error', message: 'Please login first.' });
            return;
        }

        const payload = {
            account_name: form.querySelector('input[name="account_name"]')?.value.trim() || '',
            bank_name: form.querySelector('input[name="bank_name"]')?.value.trim() || '',
            account_number: form.querySelector('input[name="account_number"]')?.value.trim() || '',
            ifsc_code: form.querySelector('input[name="ifsc_code"]')?.value.trim() || '',
            upi_id: form.querySelector('input[name="upi_id"]')?.value.trim() || '',
        };

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        try {
            const res = await fetch('/api/user/bank-details', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${token}`
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                throw new Error(data.message || 'Failed to save bank details.');
            }

            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'success', title: 'Bank Saved', message: data.message || 'Bank details saved successfully!' });
            }

            if (modal) {
                modal.hidden = true;
                modal.style.display = 'none';
                modal.classList.remove('is-open');
            }

            const b = data.data?.bank_details || payload;
            const summaryText = `Linked (${b.bank_name || 'Bank'} ****${String(b.account_number || '').slice(-4)})`;
            if (statusText) statusText.textContent = summaryText;
            if (btnOpenSub) btnOpenSub.textContent = 'Edit bank';

            localStorage.setItem('kryvex_user_bank_cache', JSON.stringify(b));
            window.dispatchEvent(new CustomEvent('kryvex_bank_details_updated', { detail: b }));
        } catch (err) {
            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'error', title: 'Save Failed', message: err.message });
            } else {
                alert(err.message);
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Bank Details';
            }
        }
    });
}

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

    document.getElementById('baseCurrencyModal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeCurrencyModal();
    });
    document.getElementById('currencyChangeModal')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeCurrencyChangeModal();
    });
});

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

    const accountBrokerRadios = document.querySelectorAll('input[name="accountBrokerRadio"]');
    if (accountBrokerRadios.length > 0 && brokerType) {
        accountBrokerRadios.forEach(r => {
            r.checked = (r.value === brokerType);
        });
    }

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
                dashBrokerBadgeEl.innerHTML = '<span style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #10b981; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700;">● Active</span>';
            }

            if (dashBrokerPlanEl) {
                const rawPlan = brokerStatusData.kryvex_plan || brokerStatusData.mt5_plan || 'Standard';
                const planKeyLower = String(rawPlan).toLowerCase();
                const matchingPlan = (brokerStatusData.plans || []).find(p => String(p.plan_key).toLowerCase() === planKeyLower) || brokerStatusData.plans?.[0];
                const spreadText = matchingPlan?.spread_formatted || (planKeyLower === 'advanced' ? '0.01%' : (planKeyLower === 'standard' ? '0.03%' : '0.05%'));
                const planName = String(rawPlan).toUpperCase();

                if (isKryvex) {
                    const maxLev = matchingPlan?.max_leverage || '1:100';
                    dashBrokerPlanEl.innerHTML = `
                        <span class="broker-plan-desc" style="font-size: 0.76rem; display: block; line-height: 1.4;">
                            Plan: <strong class="broker-plan-name" style="color: #a78bfa;">${escapeHtml(planName)}</strong> · Spread: <strong class="broker-plan-val">${spreadText}</strong> · Leverage: <strong class="broker-plan-val">Up to ${escapeHtml(maxLev)}</strong>
                        </span>
                    `;
                } else {
                    const server = brokerStatusData.active_account?.server || 'Kryvex-Live-01';
                    const loginId = brokerStatusData.active_account?.login || 'Pending Approval';
                    dashBrokerPlanEl.innerHTML = `
                        <span class="broker-plan-desc" style="font-size: 0.76rem; display: block; line-height: 1.4;">
                            MT5 Plan: <strong class="broker-plan-name" style="color: #60a5fa;">${escapeHtml(planName)}</strong> · Server: <strong class="broker-plan-val">${escapeHtml(server)}</strong> · ID: <strong class="broker-plan-val">${escapeHtml(loginId)}</strong>
                        </span>
                    `;
                }
            }
        }
    }

    checkAndShowLowBalanceWarning();

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

            const currentPlanName = brokerStatusData.mt5_plan
                ? (String(brokerStatusData.mt5_plan).charAt(0).toUpperCase() + String(brokerStatusData.mt5_plan).slice(1))
                : 'Basic';

            if (activeAccount && activeAccount.login) {
                if (badge) { badge.className = 'mt5-badge active'; badge.textContent = 'Active'; }
                if (planEl) planEl.textContent = activeAccount.plan_name || currentPlanName;
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

                if (loginInp) loginInp.value = activeAccount.login || '';
                if (passInp) passInp.value = activeAccount.password || '';
                if (serverInp) serverInp.value = activeAccount.server || 'Kryvex-Live-01';
            } else {
                if (badge) { badge.className = 'mt5-badge active'; badge.textContent = 'Active'; }
                if (planEl) planEl.textContent = currentPlanName;
                if (pendingNote) { pendingNote.hidden = true; pendingNote.style.display = 'none'; }

                if (connectedWrap) {
                    connectedWrap.setAttribute('hidden', 'true');
                    connectedWrap.style.display = 'none';
                }
                if (formWrap) {
                    formWrap.removeAttribute('hidden');
                    formWrap.style.display = 'block';
                }

                if (serverInp && !serverInp.value) serverInp.value = 'Kryvex-Live-01';
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
    document.getElementById('btnOpenBrokerModalMain')?.addEventListener('click', () => {
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

async function dismissDowngradeWarningOnServer() {
    if (brokerStatusData) brokerStatusData.plan_downgraded_warning = null;
    const token = localStorage.getItem('kryvex_access_token');
    if (!token) return;
    try {
        await fetch('/api/broker/dismiss-downgrade-warning', {
            method: 'POST',
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }
        });
    } catch (e) {}
}

function checkAndShowLowBalanceWarning() {
    const warning = brokerStatusData?.plan_downgraded_warning;
    if (!warning) return;

    // A warning saved earlier is stale once the balance is back above the threshold it was raised for.
    const required = Number(warning.required ?? 0);
    const currentBal = Number(brokerStatusData?.main_balance ?? 0);
    if (!warning.message || (required > 0 && currentBal >= required)) {
        dismissDowngradeWarningOnServer();
        return;
    }

    const modal = document.getElementById('modalLowBalanceWarning');
    if (!modal) return;
    const titleEl = document.getElementById('lowBalWarningTitle');
    const msgEl = document.getElementById('lowBalWarningMsg');
    if (titleEl) titleEl.textContent = `Plan Shifted (${warning.old_plan || 'Standard'} → ${warning.new_plan || 'Cent'})`;
    if (msgEl) msgEl.textContent = warning.message;
    modal.dataset.serverWarning = '1';
    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';
}

function switchDepositTab(tab) {
    const usdtSec = document.getElementById('depositUsdtSection');
    const inrSec = document.getElementById('depositInrSection');
    const tabUsdt = document.getElementById('tabDepositUsdt');
    const tabInr = document.getElementById('tabDepositInr');

    if (tab === 'inr') {
        if (usdtSec) usdtSec.style.display = 'none';
        if (inrSec) inrSec.style.display = 'flex';
        if (tabUsdt) { tabUsdt.style.background = 'transparent'; tabUsdt.style.color = '#94a3b8'; }
        if (tabInr) { tabInr.style.background = '#8b5cf6'; tabInr.style.color = '#ffffff'; }
    } else {
        if (usdtSec) usdtSec.style.display = 'flex';
        if (inrSec) inrSec.style.display = 'none';
        if (tabUsdt) { tabUsdt.style.background = '#8b5cf6'; tabUsdt.style.color = '#ffffff'; }
        if (tabInr) { tabInr.style.background = 'transparent'; tabInr.style.color = '#94a3b8'; }
    }
}
window.switchDepositTab = switchDepositTab;

function showLowBalanceWarningModal(planName = 'Standard', requiredMin = 50000, currentBal = 30) {
    const modal = document.getElementById('modalLowBalanceWarning');
    const msgEl = document.getElementById('lowBalWarningMsg');
    const titleEl = document.getElementById('lowBalWarningTitle');

    if (!modal) return;

    const formattedBal = `$${Number(currentBal).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
    const formattedReq = `$${Number(requiredMin).toLocaleString('en-US', { minimumFractionDigits: 2 })}`;

    if (titleEl) titleEl.textContent = `Insufficient Balance for ${planName} Plan`;
    if (msgEl) {
        msgEl.innerHTML = `Your main balance (<strong>${formattedBal}</strong>) is below the minimum required (<strong>${formattedReq}</strong>) for the <strong>${planName} Plan</strong>. Deposit funds to unlock this plan.`;
    }
    delete modal.dataset.serverWarning;

    modal.hidden = false;
    modal.removeAttribute('hidden');
    modal.style.display = 'flex';
}
window.showLowBalanceWarningModal = showLowBalanceWarningModal;

function dismissLowBalWarning() {
    const modal = document.getElementById('modalLowBalanceWarning');
    if (modal && modal.dataset.serverWarning === '1') {
        delete modal.dataset.serverWarning;
        dismissDowngradeWarningOnServer();
    }
    if (modal) {
        modal.hidden = true;
        modal.setAttribute('hidden', 'true');
        modal.style.display = 'none';
    }
}
window.dismissLowBalWarning = dismissLowBalWarning;

function openDepositFromLowBalWarning() {
    dismissLowBalWarning();

    const backdrop = document.getElementById('brokerModalBackdrop');
    if (backdrop) {
        backdrop.setAttribute('hidden', 'true');
        backdrop.style.display = 'none';
    }

    const modal1 = document.getElementById('modalChooseBroker');
    const modal2 = document.getElementById('modalChooseMt5Plan');
    const modal3 = document.getElementById('modalMt5Requested');
    if (modal1) { modal1.setAttribute('hidden', 'true'); modal1.style.display = 'none'; }
    if (modal2) { modal2.setAttribute('hidden', 'true'); modal2.style.display = 'none'; }
    if (modal3) { modal3.setAttribute('hidden', 'true'); modal3.style.display = 'none'; }

    const depositModal = document.getElementById('depositModalBackdrop') || document.getElementById('modalDepositPopup');
    if (depositModal) {
        depositModal.hidden = false;
        depositModal.removeAttribute('hidden');
        depositModal.style.display = 'flex';
        depositModal.style.zIndex = '999999';
        initWalletDepositFlowInSettings();
    }
}
window.openDepositFromLowBalWarning = openDepositFromLowBalWarning;
window.openDepositModal = openDepositFromLowBalWarning;

let settingsDepositNetworks = [];
let settingsInrConfig = { usdtInrRate: 0, adminBankDetails: null };

async function initWalletDepositFlowInSettings() {
    const token = localStorage.getItem('kryvex_access_token');
    if (!token) return;

    const tabUsdt = document.getElementById('depositTabUsdt');
    const tabInr = document.getElementById('depositTabInr');
    const formUsdt = document.getElementById('demoCreditForm');
    const panelInr = document.getElementById('inrDepositPanel');

    if (tabUsdt && tabInr) {
        tabUsdt.onclick = () => {
            tabUsdt.classList.add('is-active');
            tabUsdt.style.background = '#fff';
            tabUsdt.style.color = '#090a0f';
            tabInr.classList.remove('is-active');
            tabInr.style.background = 'none';
            tabInr.style.color = 'rgba(255,255,255,0.6)';
            if (formUsdt) formUsdt.style.display = 'block';
            if (panelInr) panelInr.style.display = 'none';
        };

        tabInr.onclick = () => {
            tabInr.classList.add('is-active');
            tabInr.style.background = '#fff';
            tabInr.style.color = '#090a0f';
            tabUsdt.classList.remove('is-active');
            tabUsdt.style.background = 'none';
            tabUsdt.style.color = 'rgba(255,255,255,0.6)';
            if (formUsdt) formUsdt.style.display = 'none';
            if (panelInr) panelInr.style.display = 'block';
        };
    }

    try {
        const res = await fetch('/api/wallet', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }
        });
        const data = await res.json();
        if (data.success && data.data && data.data.deposit_networks) {
            settingsDepositNetworks = data.data.deposit_networks;
            renderSettingsDepositNetworks();
        }
    } catch (e) {}

    try {
        const res = await fetch('/api/wallet/inr-rate', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }
        });
        const data = await res.json();
        if (data.success && data.data) {
            settingsInrConfig.usdtInrRate = Number(data.data.usdt_inr_rate || 85);
            settingsInrConfig.adminBankDetails = data.data.bank_details || data.data.admin_bank_details || null;
            renderSettingsInrBankInfo();
        }
    } catch (e) {}

    const netSelect = document.getElementById('depositNetworkSelect');
    if (netSelect) {
        netSelect.onchange = renderSettingsDepositAddress;
    }
    const copyBtn = document.getElementById('copyDepositAddressButton');
    if (copyBtn) {
        copyBtn.onclick = () => {
            const text = document.getElementById('depositAddressText')?.textContent || '';
            if (text && text !== 'Select network to view address.') {
                navigator.clipboard.writeText(text);
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Copied', message: 'Deposit address copied!' });
                } else {
                    alert('Deposit address copied!');
                }
            }
        };
    }

    if (formUsdt) {
        formUsdt.onsubmit = async (e) => {
            e.preventDefault();
            const submitBtn = formUsdt.querySelector('button[type="submit"]');
            const network = document.getElementById('depositNetworkSelect')?.value;
            const amountInput = formUsdt.querySelector('input[name="amount"]');
            const amount = Number(amountInput?.value || 0);

            if (!network || !amount) {
                alert('Please select network and enter amount.');
                return;
            }

            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Submitting...'; }

            try {
                let user = {};
                try { user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {}; } catch (e) {}
                const broker = (user.broker_type || 'kryvex').toLowerCase();

                const res = await fetch(`/api/wallet/demo-credit?broker_type=${broker}`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${token}`,
                        'X-Broker-Type': broker
                    },
                    body: JSON.stringify({ network, amount }),
                });

                const respData = await res.json();
                if (!res.ok || !respData.success) throw new Error(respData.message || 'Deposit failed.');

                const modal = document.getElementById('depositModalBackdrop');
                if (modal) { modal.hidden = true; modal.style.display = 'none'; }
                formUsdt.reset();

                await loadBrokerStatus();
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Deposit Submitted', message: respData.message || 'Deposit submitted to Main Wallet.' });
                } else {
                    alert(respData.message || 'Deposit submitted to Main Wallet.');
                }
            } catch (err) {
                alert(err.message || 'Deposit failed.');
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Submit Deposit'; }
            }
        };
    }

    const inrForm = document.getElementById('inrDepositForm');
    const inrAmtInput = document.getElementById('inrDepositAmount');
    if (inrAmtInput) {
        inrAmtInput.oninput = () => {
            const val = Number(inrAmtInput.value || 0);
            const rate = settingsInrConfig.usdtInrRate || 1;
            const creditUsd = rate > 0 ? (val / rate).toFixed(2) : '0.00';
            const payEl = document.getElementById('inrDepositConvYouPay');
            const credEl = document.getElementById('inrDepositConvCredit');
            if (payEl) payEl.textContent = `₹${val.toLocaleString()}`;
            if (credEl) credEl.textContent = `$${creditUsd} USD`;
        };
    }

    if (inrForm) {
        inrForm.onsubmit = async (e) => {
            e.preventDefault();
            const submitBtn = inrForm.querySelector('button[type="submit"]');
            const amountInr = Number(inrAmtInput?.value || 0);

            if (!amountInr || amountInr <= 0) {
                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'error', title: 'Error', message: 'Please enter a valid INR deposit amount.' });
                } else {
                    alert('Please enter a valid INR deposit amount.');
                }
                return;
            }

            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Initiating Razorpay...'; }

            try {
                let user = {};
                try { user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {}; } catch (e) {}

                // 1. Create Razorpay Order
                const orderRes = await fetch('/api/wallet/razorpay/create-order', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${token}`,
                    },
                    body: JSON.stringify({ amount_inr: amountInr, amount: amountInr }),
                });
                const orderData = await orderRes.json();
                if (!orderRes.ok || !orderData.success) {
                    throw new Error(orderData.message || 'Failed to initialize Razorpay payment order.');
                }

                const keyId = orderData.key_id || orderData.data?.key_id || 'rzp_test_1DP5A3y551Bwworking';
                const orderId = orderData.order_id || orderData.data?.order_id || ('order_' + Date.now());
                const amountPaise = orderData.amount_paise || orderData.data?.amount_paise || (amountInr * 100);

                // 2. Load Razorpay Checkout SDK
                let RazorpayCtor = window.Razorpay;
                if (!RazorpayCtor) {
                    await new Promise((resolve, reject) => {
                        const script = document.createElement('script');
                        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
                        script.async = true;
                        script.onload = () => resolve(window.Razorpay);
                        script.onerror = () => reject(new Error('Failed to load Razorpay SDK.'));
                        document.head.appendChild(script);
                    });
                    RazorpayCtor = window.Razorpay;
                }

                // 3. Launch Razorpay Checkout Modal
                const options = {
                    key: keyId,
                    amount: amountPaise,
                    currency: 'INR',
                    name: 'Kryvex Exchange',
                    description: `INR Deposit ₹${amountInr}`,
                    order_id: orderId,
                    prefill: {
                        name: user.name || '',
                        email: user.email || '',
                        contact: user.mobile || '',
                    },
                    theme: { color: '#2563eb' },
                    handler: async function (response) {
                        if (submitBtn) submitBtn.textContent = 'Verifying Payment...';
                        try {
                            const verifyRes = await fetch('/api/wallet/razorpay/verify', {
                                method: 'POST',
                                headers: {
                                    Accept: 'application/json',
                                    'Content-Type': 'application/json',
                                    Authorization: `Bearer ${token}`,
                                },
                                body: JSON.stringify({
                                    razorpay_order_id: response.razorpay_order_id || orderId,
                                    razorpay_payment_id: response.razorpay_payment_id || '',
                                    razorpay_signature: response.razorpay_signature || '',
                                    amount_inr: amountInr,
                                    amount: amountInr,
                                }),
                            });
                            const verifyData = await verifyRes.json();
                            if (!verifyRes.ok || !verifyData.success) throw new Error(verifyData.message || 'Payment verification failed.');

                            const modal = document.getElementById('depositModalBackdrop');
                            if (modal) { modal.hidden = true; modal.style.display = 'none'; }
                            inrForm.reset();

                            if (typeof window.loadBrokerStatus === 'function') await loadBrokerStatus();
                            if (typeof window.reloadFuturesBalances === 'function') await window.reloadFuturesBalances();

                            if (typeof window.showToast === 'function') {
                                window.showToast({ type: 'success', title: 'Payment Verified', message: verifyData.message || `₹${amountInr} credited to your INR wallet!` });
                            } else {
                                alert(verifyData.message || `₹${amountInr} credited to your INR wallet!`);
                            }
                        } catch (err) {
                            alert(err.message || 'Payment verification failed.');
                        } finally {
                            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<span>Pay with Razorpay</span> 💳'; }
                        }
                    },
                    modal: {
                        ondismiss: function () {
                            if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<span>Pay with Razorpay</span> 💳'; }
                        }
                    }
                };

                const rzp = new RazorpayCtor(options);
                rzp.open();

            } catch (err) {
                alert(err.message || 'INR Deposit failed.');
                if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = '<span>Pay with Razorpay</span> 💳'; }
            }
        };
    }
}

function renderSettingsDepositNetworks() {
    const netSelect = document.getElementById('depositNetworkSelect');
    if (!netSelect) return;
    netSelect.innerHTML = settingsDepositNetworks.length
        ? settingsDepositNetworks.map(n => `<option value="${n.network}">${n.label || n.network}</option>`).join('')
        : '<option value="">TRC20 (TRON Network)</option>';
    renderSettingsDepositAddress();
}

function renderSettingsDepositAddress() {
    const netSelect = document.getElementById('depositNetworkSelect');
    const netCode = netSelect?.value;
    const netObj = settingsDepositNetworks.find(n => n.network === netCode);
    const addrEl = document.getElementById('depositAddressText');
    const hintEl = document.getElementById('depositAddressHint');
    const minEl = document.getElementById('depositMinimumText');

    if (addrEl) addrEl.textContent = netObj?.address || 'TX9yKryvexVaultUsdtAddress789234';
    if (hintEl) hintEl.textContent = netObj?.address ? `Use only ${netObj.label || netObj.network}.` : 'Use only selected network.';
    if (minEl) minEl.textContent = netObj?.minimum_deposit ? `$${netObj.minimum_deposit}` : '$10.00';
}

function renderSettingsInrBankInfo() {
    const infoEl = document.getElementById('inrBankInfo');
    const rateEl = document.getElementById('inrDepositRateLabel');
    const bank = settingsInrConfig.adminBankDetails;
    const rate = settingsInrConfig.usdtInrRate || 85;

    if (rateEl) rateEl.textContent = `Exchange Rate: 1 USD = ₹${rate}`;

    if (infoEl) {
        if (bank) {
            infoEl.innerHTML = `
                <div><strong>Bank Name:</strong> ${bank.bank_name || 'HDFC Bank'}</div>
                <div><strong>Account Name:</strong> ${bank.account_name || 'Kryvex Capital Pvt Ltd'}</div>
                <div><strong>Account Number:</strong> ${bank.account_number || '50200089123456'}</div>
                <div><strong>IFSC Code:</strong> ${bank.ifsc_code || 'HDFC0001234'}</div>
                <div><strong>UPI ID:</strong> ${bank.upi_id || 'kryvex.pay@hdfcbank'}</div>
            `;
        } else {
            infoEl.innerHTML = `
                <div><strong>Bank Name:</strong> HDFC Bank</div>
                <div><strong>Account Name:</strong> Kryvex Capital Private Limited</div>
                <div><strong>Account Number:</strong> 50200089123456</div>
                <div><strong>IFSC Code:</strong> HDFC0001234</div>
                <div><strong>UPI ID:</strong> kryvex.pay@hdfcbank</div>
            `;
        }
    }
}

function setupLowBalanceWarningModal() {
    const modal = document.getElementById('modalLowBalanceWarning');

    const btnDismiss = document.getElementById('btnDismissLowBalWarning');
    const btnDeposit = document.getElementById('btnDepositLowBalWarning');

    if (btnDismiss) {
        btnDismiss.onclick = (e) => {
            e.preventDefault();
            dismissLowBalWarning();
        };
    }

    if (btnDeposit) {
        btnDeposit.onclick = (e) => {
            e.preventDefault();
            openDepositFromLowBalWarning();
        };
    }

    document.querySelectorAll('[data-action-deposit], [data-action="deposit"], .btn-deposit').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openDepositFromLowBalWarning();
        });
    });
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
        const currentActivePlan = brokerStatusData?.kryvex_plan || brokerStatusData?.mt5_plan;
        selectedMt5PlanKey = currentActivePlan || 'standard';
    }

    grid.innerHTML = plans.map(p => {
        const isSelected = selectedMt5PlanKey === p.plan_key;
        const spreadText = p.spread_formatted || (p.plan_key === 'advanced' ? '0.01%' : (p.plan_key === 'standard' ? '0.03%' : '0.05%'));
        return `
            <div class="mt5-plan-card ${isSelected ? 'is-selected' : ''}" data-plan-key="${p.plan_key}" data-min-bal="${p.minimum_balance}" data-plan-name="${p.name}">
                <div style="flex:1;">
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:2px;">
                        <strong style="font-size:1.02rem; color:#fff;">${escapeHtml(p.name)} Plan</strong>
                        <span style="background:rgba(139,92,246,0.15); border:1px solid rgba(139,92,246,0.3); color:#a78bfa; font-size:0.72rem; font-weight:700; padding:1px 8px; border-radius:4px;">Spread: ${spreadText}</span>
                    </div>
                    <span style="font-size:0.78rem; color:#94a3b8;">Min Balance: $${Number(p.minimum_balance).toLocaleString()} · Leverage: ${p.max_leverage}</span>
                </div>
                <div class="radio-check-indicator"></div>
            </div>
        `;
    }).join('');

    grid.querySelectorAll('.mt5-plan-card').forEach(card => {
        card.addEventListener('click', (e) => {
            const planKey = card.dataset.planKey;
            const minBal = Number(card.dataset.minBal || 0);
            const planName = card.dataset.planName || 'Selected';

            selectedMt5PlanKey = planKey;
            grid.querySelectorAll('.mt5-plan-card').forEach(c => c.classList.remove('is-selected'));
            card.classList.add('is-selected');

            if (mainBal < minBal) {
                if (e.isTrusted) showLowBalanceWarningModal(planName, minBal, mainBal);
                if (errBox) {
                    errBox.hidden = false;
                    errBox.style.display = 'block';
                    errBox.innerHTML = `Insufficient balance. Minimum balance required for <strong>${planName}</strong> plan: <strong>$${minBal.toLocaleString()}</strong>`;
                }
            } else {
                if (errBox) { errBox.hidden = true; errBox.style.display = 'none'; }
            }
        });
    });

    const currentSelectedCard = grid.querySelector(`.mt5-plan-card[data-plan-key="${selectedMt5PlanKey}"]`);
    if (currentSelectedCard) currentSelectedCard.click();
}

async function submitPlanRequest() {
    if (!selectedMt5PlanKey) return;

    const plans = brokerStatusData?.plans || [
        { plan_key: 'basic', name: 'Basic', minimum_balance: 1000 },
        { plan_key: 'standard', name: 'Standard', minimum_balance: 50000 },
        { plan_key: 'advanced', name: 'Advanced', minimum_balance: 250000 }
    ];

    const selectedPlanObj = plans.find(p => String(p.plan_key).toLowerCase() === String(selectedMt5PlanKey).toLowerCase());
    const mainBal = Number(brokerStatusData?.main_balance || 0);
    const requiredMin = Number(selectedPlanObj?.minimum_balance || 0);

    if (mainBal < requiredMin) {
        showLowBalanceWarningModal(selectedPlanObj?.name || selectedMt5PlanKey, requiredMin, mainBal);
        return;
    }

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

            const hasActiveAccount = Boolean(brokerStatusData?.active_account && brokerStatusData?.active_account?.login);

            if (hasActiveAccount) {
                closeBrokerModals();
                let user = {};
                try {
                    user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
                } catch (err) {}
                user.broker_type = 'mt5';
                localStorage.setItem('kryvex_user', JSON.stringify(user));

                if (typeof window.showToast === 'function') {
                    window.showToast({ type: 'success', title: 'Broker Switched', message: 'Trading broker switched to MetaTrader 5 (MT5).' });
                }
                setTimeout(() => {
                    window.location.reload();
                }, 300);
                return;
            }

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

function setupSettingsTabs() {
    const tabs = document.querySelectorAll('.settings-tab-btn');
    const sections = document.querySelectorAll('.settings-section');

    function switchTab(tabId) {
        tabs.forEach(btn => {
            btn.classList.toggle('is-active', btn.dataset.tab === tabId);
        });
        sections.forEach(sec => {
            if (sec.id === tabId) {
                sec.style.display = 'block';
            } else {
                sec.style.display = 'none';
            }
        });
    }

    tabs.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabId = btn.dataset.tab;
            switchTab(tabId);
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId.replace('Section', ''));
            window.history.pushState({}, '', url);
        });
    });

    const urlParams = new URLSearchParams(window.location.search);
    const initialTab = urlParams.get('tab');
    if (initialTab) {
        const targetSection = `${initialTab}Section`;
        if (document.getElementById(targetSection)) {
            switchTab(targetSection);
        }
    }

    const notifForm = document.getElementById('notificationSettingsForm');
    if (notifForm) {
        notifForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const prefs = {
                security_alerts: document.getElementById('notifSecurityAlerts')?.checked ?? true,
                trading_activity: document.getElementById('notifTradingActivity')?.checked ?? true,
                marketing_news: document.getElementById('notifMarketingNews')?.checked ?? false,
            };
            localStorage.setItem('kryvex_notification_prefs', JSON.stringify(prefs));
            showDashboardToast('Notification preferences saved.', 'success');
        });

        try {
            const saved = localStorage.getItem('kryvex_notification_prefs');
            if (saved) {
                const prefs = JSON.parse(saved);
                if (document.getElementById('notifSecurityAlerts')) document.getElementById('notifSecurityAlerts').checked = prefs.security_alerts;
                if (document.getElementById('notifTradingActivity')) document.getElementById('notifTradingActivity').checked = prefs.trading_activity;
                if (document.getElementById('notifMarketingNews')) document.getElementById('notifMarketingNews').checked = prefs.marketing_news;
            }
        } catch (e) {}
    }
}

function setupBankDetailsForm() {
    const bankForm = document.getElementById('bankDetailsForm');
    const bankStatusBadge = document.getElementById('bankStatusBadge');

    const getAuthToken = () => localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token') || localStorage.getItem('token');

    const loadBankDetails = async () => {
        const token = getAuthToken();
        if (!token) return;

        try {
            const res = await fetch('/api/user/bank-details', {
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Accept': 'application/json'
                }
            });
            const json = await res.json();
            if (json.success && json.data && json.data.bank_details) {
                const b = json.data.bank_details;
                if (document.getElementById('bankAccountName')) document.getElementById('bankAccountName').value = b.account_name || '';
                if (document.getElementById('bankName')) document.getElementById('bankName').value = b.bank_name || '';
                if (document.getElementById('bankAccountType')) document.getElementById('bankAccountType').value = b.account_type || 'Savings';
                if (document.getElementById('bankAccountNumber')) document.getElementById('bankAccountNumber').value = b.account_number || '';
                if (document.getElementById('bankIfscCode')) document.getElementById('bankIfscCode').value = b.ifsc_code || '';
                if (document.getElementById('bankUpiId')) document.getElementById('bankUpiId').value = b.upi_id || '';

                if (bankStatusBadge) {
                    bankStatusBadge.textContent = '✓ Bank Details Saved';
                    bankStatusBadge.style.background = 'rgba(16, 185, 129, 0.15)';
                    bankStatusBadge.style.color = '#10b981';
                    bankStatusBadge.style.borderColor = 'rgba(16, 185, 129, 0.3)';
                }
            } else {
                if (bankStatusBadge) {
                    bankStatusBadge.textContent = 'Not Added';
                    bankStatusBadge.style.background = 'rgba(245, 158, 11, 0.15)';
                    bankStatusBadge.style.color = '#f59e0b';
                    bankStatusBadge.style.borderColor = 'rgba(245, 158, 11, 0.3)';
                }
            }
        } catch (e) {}
    };

    loadBankDetails();

    if (bankForm) {
        bankForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const token = getAuthToken();
            if (!token) return;

            const account_name = document.getElementById('bankAccountName')?.value.trim();
            const bank_name = document.getElementById('bankName')?.value.trim();
            const account_type = document.getElementById('bankAccountType')?.value;
            const account_number = document.getElementById('bankAccountNumber')?.value.trim();
            const ifsc_code = document.getElementById('bankIfscCode')?.value.trim();
            const upi_id = document.getElementById('bankUpiId')?.value.trim();

            const btn = document.getElementById('btnSaveBankDetails');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span>Saving...</span>';
            }

            try {
                const res = await fetch('/api/user/bank-details', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        account_name,
                        bank_name,
                        account_type,
                        account_number,
                        ifsc_code,
                        upi_id
                    })
                });

                const json = await res.json();

                if (json.success) {
                    showDashboardToast(json.message || 'Bank details saved successfully.', 'success');
                    loadBankDetails();
                } else {
                    showDashboardToast(json.message || 'Failed to save bank details.', 'error');
                }
            } catch (err) {
                showDashboardToast('Failed to save bank details.', 'error');
            } finally {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = '<span>💾 Save Bank Details</span>';
                }
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', () => {
    setupSettingsTabs();
});

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function updateNotificationSummaryState() {
    const statusEl = document.getElementById('notifPrefStatusText');
    const masterChk = document.getElementById('notifMasterToggle');
    const sliderBg = document.getElementById('notifMasterSliderBg');
    const knob = document.getElementById('notifMasterKnob');
    const label = document.getElementById('notifMasterStatusLabel');

    const isEnabled = localStorage.getItem('kryvex_global_notifications_enabled') !== 'false';

    if (masterChk) masterChk.checked = isEnabled;
    if (sliderBg) sliderBg.style.backgroundColor = isEnabled ? '#3b82f6' : 'rgba(255,255,255,0.15)';
    if (knob) knob.style.transform = isEnabled ? 'translateX(20px)' : 'translateX(0px)';
    if (label) label.textContent = isEnabled ? 'Receive trades, deposits, security & system alerts' : 'All notifications disabled across platform';

    if (statusEl) {
        if (isEnabled) {
            statusEl.textContent = '✓ ON';
            statusEl.style.color = '#22c55e';
        } else {
            statusEl.textContent = '✕ OFF';
            statusEl.style.color = 'rgba(255,255,255,0.45)';
        }
    }
}

function setupNotificationPreferences() {
    updateNotificationSummaryState();

    const notifForm = document.getElementById('notificationSettingsForm');
    const masterChk = document.getElementById('notifMasterToggle');
    const sliderBg = document.getElementById('notifMasterSliderBg');
    const knob = document.getElementById('notifMasterKnob');
    const label = document.getElementById('notifMasterStatusLabel');

    function syncModalSlider(isEnabled) {
        if (masterChk) masterChk.checked = isEnabled;
        if (sliderBg) sliderBg.style.backgroundColor = isEnabled ? '#3b82f6' : 'rgba(255,255,255,0.15)';
        if (knob) knob.style.transform = isEnabled ? 'translateX(20px)' : 'translateX(0px)';
        if (label) label.textContent = isEnabled ? 'Receive trades, deposits, security & system alerts' : 'All notifications disabled across platform';
    }

    // Reset to saved state whenever modal is opened
    document.querySelectorAll('[data-open-modal="modalNotifPreferences"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const isEnabled = localStorage.getItem('kryvex_global_notifications_enabled') !== 'false';
            syncModalSlider(isEnabled);
        });
    });

    if (masterChk) {
        masterChk.addEventListener('change', () => {
            // Only update slider appearance inside the modal, do not commit to localStorage yet
            syncModalSlider(masterChk.checked);
        });
    }

    if (notifForm) {
        notifForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const on = Boolean(masterChk ? masterChk.checked : true);
            localStorage.setItem('kryvex_global_notifications_enabled', on ? 'true' : 'false');
            updateNotificationSummaryState();

            const modal = document.getElementById('modalNotifPreferences');
            if (modal) modal.hidden = true;

            if (typeof window.showToast === 'function') {
                window.showToast({
                    type: on ? 'success' : 'info',
                    title: 'Notification Settings',
                    message: `Platform notifications ${on ? 'enabled (ON)' : 'disabled (OFF)'}.`
                });
            }
        });
    }
}

function setupAccountProfileModal() {
    const modal = document.getElementById('modalAccountProfile');
    const switchBtn = document.getElementById('btnSwitchBrokerAccountProfile');
    const startKycBtn = document.getElementById('btnStartKycFromProfile');
    const kycBadge = document.getElementById('accountProfileKycBadge');

    let user = {};
    try {
        user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (e) {}

    const kycStatus = String(user.kyc_status || user.kyc || 'unverified').toLowerCase();
    const isVerified = ['verified', 'approved'].includes(kycStatus);
    const isPending = kycStatus === 'pending';

    if (kycBadge) {
        kycBadge.textContent = isVerified ? '✓ Verified' : (isPending ? '⏳ Under Review' : 'Unverified');
        kycBadge.style.background = isVerified ? 'rgba(34, 197, 94, 0.15)' : (isPending ? 'rgba(245, 158, 11, 0.15)' : 'rgba(239, 68, 68, 0.15)');
        kycBadge.style.color = isVerified ? '#22c55e' : (isPending ? '#f59e0b' : '#ef4444');
    }

    if (switchBtn) {
        switchBtn.addEventListener('click', async () => {
            const selectedRadio = document.querySelector('input[name="accountBrokerRadio"]:checked');
            const brokerVal = selectedRadio ? selectedRadio.value : 'kryvex';

            const userObj = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
            userObj.broker_type = brokerVal;
            localStorage.setItem('kryvex_user', JSON.stringify(userObj));

            if (typeof window.showToast === 'function') {
                window.showToast({
                    type: 'success',
                    title: 'Broker Switch',
                    message: `Active trading broker set to ${brokerVal === 'mt5' ? 'MetaTrader 5' : 'Kryvex Broker'}.`
                });
            }

            if (modal) modal.hidden = true;
        });
    }

    if (startKycBtn) {
        startKycBtn.addEventListener('click', () => {
            if (modal) modal.hidden = true;
            const kycModal = document.getElementById('modalKycFlow');
            if (kycModal) kycModal.hidden = false;
        });
    }
}

function updateTwoFactorUIState(isEnabled) {
    const statusEl = document.getElementById('twoFactorStatusText');
    const subPageStatusEl = document.getElementById('accountProfile2faStatusText');
    const masterChk = document.getElementById('twoFactorMasterToggle');
    const sliderBg = document.getElementById('twoFactorSliderBg');
    const knob = document.getElementById('twoFactorKnob');
    const label = document.getElementById('twoFactorMasterStatusLabel');

    if (masterChk) masterChk.checked = isEnabled;
    if (sliderBg) sliderBg.style.backgroundColor = isEnabled ? '#3b82f6' : 'rgba(255,255,255,0.15)';
    if (knob) knob.style.transform = isEnabled ? 'translateX(20px)' : 'translateX(0px)';
    if (label) label.textContent = isEnabled ? 'Require 6-digit OTP code on login' : 'Disabled (Default)';

    if (statusEl) {
        if (isEnabled) {
            statusEl.textContent = '✓ ON';
            statusEl.style.color = '#22c55e';
        } else {
            statusEl.textContent = '✕ OFF';
            statusEl.style.color = 'rgba(255,255,255,0.45)';
        }
    }

    if (subPageStatusEl) {
        if (isEnabled) {
            subPageStatusEl.textContent = 'Enabled';
            subPageStatusEl.style.color = '#22c55e';
        } else {
            subPageStatusEl.textContent = 'Disabled';
            subPageStatusEl.style.color = '#f59e0b';
        }
    }
}

function setupTwoFactorModal() {
    let userObj = {};
    try {
        userObj = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch (e) {}

    const isEnabled = Boolean(userObj.is_2fa_enabled);
    updateTwoFactorUIState(isEnabled);

    const masterChk = document.getElementById('twoFactorMasterToggle');
    const sliderBg = document.getElementById('twoFactorSliderBg');
    const knob = document.getElementById('twoFactorKnob');
    const label = document.getElementById('twoFactorMasterStatusLabel');
    const form = document.getElementById('twoFactorSettingsForm');

    function syncModalSlider(on) {
        if (masterChk) masterChk.checked = on;
        if (sliderBg) sliderBg.style.backgroundColor = on ? '#3b82f6' : 'rgba(255,255,255,0.15)';
        if (knob) knob.style.transform = on ? 'translateX(20px)' : 'translateX(0px)';
        if (label) label.textContent = on ? 'Require 6-digit OTP code on login' : 'Disabled (Default)';
    }

    // Reset when opening modal
    document.querySelectorAll('[data-open-modal="modalTwoFactor"]').forEach(btn => {
        btn.addEventListener('click', () => {
            let u = {};
            try { u = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {}; } catch(e) {}
            syncModalSlider(Boolean(u.is_2fa_enabled));
        });
    });

    if (masterChk) {
        masterChk.addEventListener('change', () => {
            // Only update slider appearance inside modal
            syncModalSlider(masterChk.checked);
        });
    }

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const token = localStorage.getItem('kryvex_access_token');
            const on = Boolean(masterChk?.checked);

            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }

            try {
                const response = await fetch('/api/auth/toggle-2fa', {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        Authorization: `Bearer ${token}`,
                    },
                    body: JSON.stringify({ is_2fa_enabled: on }),
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(getDashboardApiError(data, 'Failed to update 2FA settings.'));
                }

                userObj.is_2fa_enabled = on;
                localStorage.setItem('kryvex_user', JSON.stringify(userObj));
                updateTwoFactorUIState(on);

                const modal = document.getElementById('modalTwoFactor');
                if (modal) modal.hidden = true;

                showDashboardAlert(data.message || `2FA ${on ? 'enabled (ON)' : 'disabled (OFF)'}.`, 'success');
            } catch (err) {
                showDashboardAlert(err.message || '2FA update failed.', 'error');
            } finally {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save 2FA Settings'; }
            }
        });
    }
}

function setupTradeSoundModal() {
    const statusEl = document.getElementById('tradeSoundStatusText');
    const form = document.getElementById('tradeSoundForm');
    const chkEnable = document.getElementById('tradeSoundEnable');

    const renderSoundUI = () => {
        const soundEnabled = localStorage.getItem('kryvex_trade_close_sound') !== 'false';
        if (statusEl) {
            statusEl.textContent = soundEnabled ? 'Default (ON)' : 'Muted (OFF)';
            statusEl.style.color = soundEnabled ? '#22c55e' : 'rgba(255,255,255,0.45)';
        }
        if (chkEnable) chkEnable.checked = soundEnabled;
    };

    renderSoundUI();

    document.querySelectorAll('[data-open-modal="modalTradeSound"]').forEach(btn => {
        btn.addEventListener('click', () => {
            renderSoundUI();
        });
    });

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const isChecked = Boolean(chkEnable?.checked);
            localStorage.setItem('kryvex_trade_close_sound', isChecked ? 'true' : 'false');
            renderSoundUI();

            const modal = document.getElementById('modalTradeSound');
            if (modal) modal.hidden = true;

            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'success', title: 'Trade Close Sound', message: `Futures position close sound ${isChecked ? 'enabled' : 'disabled'}.` });
            }
        });
    }
}

function setupColorPrefModal() {
    const statusEl = document.getElementById('colorPrefStatusText');
    const form = document.getElementById('colorPrefForm');

    function renderStatusDisplay(pref) {
        if (!statusEl) return;
        if (pref === 'red_up') {
            statusEl.innerHTML = '<span style="color:#ef4444;">↑</span> <span style="color:#22c55e;">↓</span> <span style="color:#ffffff;">Red Up / Green Down</span>';
        } else {
            statusEl.innerHTML = '<span style="color:#22c55e;">↑</span> <span style="color:#ef4444;">↓</span> <span style="color:#ffffff;">Green Up / Red Down</span>';
        }
    }

    const savedPref = localStorage.getItem('kryvex_color_preference') || 'green_up';
    renderStatusDisplay(savedPref);

    // Reset to saved radio selection whenever modal is opened
    document.querySelectorAll('[data-open-modal="modalColorPref"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const currentPref = localStorage.getItem('kryvex_color_preference') || 'green_up';
            if (form) {
                const radio = form.querySelector(`input[name="color_preference"][value="${currentPref}"]`);
                if (radio) radio.checked = true;
            }
        });
    });

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const selected = form.querySelector('input[name="color_preference"]:checked')?.value || 'green_up';
            localStorage.setItem('kryvex_color_preference', selected);

            renderStatusDisplay(selected);

            const modal = document.getElementById('modalColorPref');
            if (modal) modal.hidden = true;

            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'success', title: 'Color Preference', message: 'Color preference updated successfully.' });
            }
        });
    }
}

function setupShortcutsToggleAndModal() {
    const chkMaster = document.getElementById('shortcutsToggleCheck');
    const sliderBg = document.getElementById('shortcutsSliderBg');
    const knob = document.getElementById('shortcutsKnob');

    function syncToggleUI(on) {
        if (chkMaster) chkMaster.checked = on;
        if (sliderBg) sliderBg.style.backgroundColor = on ? '#3b82f6' : 'rgba(255,255,255,0.15)';
        if (knob) knob.style.transform = on ? 'translateX(20px)' : 'translateX(0px)';
    }

    const isEnabled = localStorage.getItem('kryvex_shortcuts_enabled') !== 'false';
    syncToggleUI(isEnabled);

    if (chkMaster) {
        chkMaster.addEventListener('change', () => {
            const on = chkMaster.checked;
            localStorage.setItem('kryvex_shortcuts_enabled', on ? 'true' : 'false');
            syncToggleUI(on);

            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'info', title: 'Shortcuts', message: `Keyboard shortcuts ${on ? 'enabled' : 'disabled'}.` });
            }
        });
    }

    const form = document.getElementById('shortcutsSettingsForm');
    const modalMaster = document.getElementById('shortcutsMasterToggle');

    function resetModalShortcuts() {
        const on = localStorage.getItem('kryvex_shortcuts_enabled') !== 'false';
        if (modalMaster) modalMaster.checked = on;

        let savedKeys = {};
        try {
            savedKeys = JSON.parse(localStorage.getItem('kryvex_shortcut_keys') || '{}') || {};
        } catch (e) {}

        if (form) {
            form.querySelectorAll('.shortcut-key-item').forEach(item => {
                const key = item.getAttribute('data-key');
                if (key && savedKeys[key] !== undefined) {
                    item.checked = Boolean(savedKeys[key]);
                } else {
                    item.checked = true;
                }
            });
        }
    }

    document.querySelectorAll('[data-open-modal="modalShortcuts"]').forEach(btn => {
        btn.addEventListener('click', () => {
            resetModalShortcuts();
        });
    });

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const masterOn = Boolean(modalMaster?.checked);
            localStorage.setItem('kryvex_shortcuts_enabled', masterOn ? 'true' : 'false');
            syncToggleUI(masterOn);

            const keyPrefs = {};
            form.querySelectorAll('.shortcut-key-item').forEach(item => {
                const key = item.getAttribute('data-key');
                if (key) {
                    keyPrefs[key] = item.checked;
                }
            });
            localStorage.setItem('kryvex_shortcut_keys', JSON.stringify(keyPrefs));

            const modal = document.getElementById('modalShortcuts');
            if (modal) modal.hidden = true;

            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'success', title: 'Shortcuts', message: 'Keyboard shortcut preferences saved.' });
            }
        });
    }
}

function setupNotifLanguageModal() {
    const form = document.getElementById('notifLanguageForm');

    function syncLabels(lang) {
        if (typeof window.syncLanguageUI === 'function') {
            window.syncLanguageUI(lang);
        } else {
            document.querySelectorAll('.user-display-language, #currentNotifLanguageText, #accountProfileLanguageText').forEach(el => {
                if (el) el.textContent = lang;
            });
        }
    }

    const currentLang = (typeof window.getActiveLanguage === 'function')
        ? window.getActiveLanguage()
        : (localStorage.getItem('kryvex_language') || localStorage.getItem('kryvex_notif_language') || 'English');

    syncLabels(currentLang);

    // Reset to saved radio selection whenever modal is opened
    document.querySelectorAll('[data-open-modal="modalNotifLanguage"]').forEach(btn => {
        btn.addEventListener('click', () => {
            const curLang = (typeof window.getActiveLanguage === 'function')
                ? window.getActiveLanguage()
                : (localStorage.getItem('kryvex_language') || localStorage.getItem('kryvex_notif_language') || 'English');

            if (form) {
                const radio = form.querySelector(`input[name="notif_language"][value="${curLang}"]`);
                if (radio) radio.checked = true;
            }
        });
    });

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const selected = form.querySelector('input[name="notif_language"]:checked')?.value || 'English';

            const modal = document.getElementById('modalNotifLanguage');
            if (modal) modal.hidden = true;

            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'success', title: 'Language Updated', message: `Language updated to ${selected}.` });
            } else if (typeof showDashboardToast === 'function') {
                showDashboardToast(`Language updated to ${selected}.`, 'success');
            }

            if (typeof window.changeLanguage === 'function') {
                window.changeLanguage(selected);
            } else {
                localStorage.setItem('kryvex_language', selected);
                localStorage.setItem('kryvex_notif_language', selected);
                syncLabels(selected);
                setTimeout(() => window.location.reload(), 200);
            }
        });
    }
}

function setupOrderConfirmModal() {
    const summaryEl = document.getElementById('orderConfirmStatusSummaryText');
    const form = document.getElementById('orderConfirmSettingsForm');
    const masterChk = document.getElementById('orderConfirmMasterToggle');
    const sliderBg = document.getElementById('orderConfirmMasterSliderBg');
    const knob = document.getElementById('orderConfirmMasterKnob');

    const marketChk = document.getElementById('orderConfirmMarketToggle');
    const limitChk = document.getElementById('orderConfirmLimitToggle');
    const stopChk = document.getElementById('orderConfirmStopToggle');
    const closeSingleChk = document.getElementById('orderConfirmCloseSingleToggle');
    const closeAllChk = document.getElementById('orderConfirmCloseAllToggle');

    const readState = () => {
        const master = localStorage.getItem('kryvex:futures:confirm:master') !== '0';
        const market = localStorage.getItem('kryvex:futures:confirm:market:skip') !== '1';
        const limit = localStorage.getItem('kryvex:futures:confirm:limit:skip') !== '1';
        const stop = localStorage.getItem('kryvex:futures:confirm:stop:skip') !== '1';
        const closeSingle = localStorage.getItem('kryvex:futures:confirm:close-position:skip') !== '1';
        const closeAll = localStorage.getItem('kryvex:futures:confirm:close-all:skip') !== '1';
        return { master, market, limit, stop, closeSingle, closeAll };
    };

    const updateUI = () => {
        const state = readState();
        if (masterChk) masterChk.checked = state.master;
        if (sliderBg) sliderBg.style.backgroundColor = state.master ? '#3b82f6' : 'rgba(255,255,255,0.15)';
        if (knob) knob.style.transform = state.master ? 'translateX(20px)' : 'translateX(0px)';

        if (marketChk) marketChk.checked = state.market;
        if (limitChk) limitChk.checked = state.limit;
        if (stopChk) stopChk.checked = state.stop;
        if (closeSingleChk) closeSingleChk.checked = state.closeSingle;
        if (closeAllChk) closeAllChk.checked = state.closeAll;

        if (summaryEl) {
            if (!state.master) {
                summaryEl.textContent = '✕ OFF (All Reminders Disabled)';
                summaryEl.style.color = 'rgba(255,255,255,0.45)';
            } else {
                const active = [];
                if (state.market) active.push('Market');
                if (state.limit) active.push('Limit');
                if (state.stop) active.push('Stop-Limit');
                if (state.closeSingle) active.push('Close Trade');
                if (state.closeAll) active.push('Close All');

                if (active.length === 0) {
                    summaryEl.textContent = '✕ OFF (All Reminders Disabled)';
                    summaryEl.style.color = 'rgba(255,255,255,0.45)';
                } else {
                    summaryEl.textContent = active.join(', ');
                    summaryEl.style.color = '#22c55e';
                }
            }
        }
    };

    updateUI();

    document.querySelectorAll('[data-open-modal="modalOrderConfirm"]').forEach(btn => {
        btn.addEventListener('click', () => {
            updateUI(); // Resets modal form controls to saved localStorage state!
        });
    });

    if (masterChk) {
        masterChk.addEventListener('change', () => {
            const isMasterOn = masterChk.checked;
            if (sliderBg) sliderBg.style.backgroundColor = isMasterOn ? '#3b82f6' : 'rgba(255,255,255,0.15)';
            if (knob) knob.style.transform = isMasterOn ? 'translateX(20px)' : 'translateX(0px)';

            if (marketChk) marketChk.checked = isMasterOn;
            if (limitChk) limitChk.checked = isMasterOn;
            if (stopChk) stopChk.checked = isMasterOn;
            if (closeSingleChk) closeSingleChk.checked = isMasterOn;
            if (closeAllChk) closeAllChk.checked = isMasterOn;
        });
    }

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            const masterOn = Boolean(masterChk?.checked);
            const marketOn = Boolean(marketChk?.checked);
            const limitOn = Boolean(limitChk?.checked);
            const stopOn = Boolean(stopChk?.checked);
            const closeSingleOn = Boolean(closeSingleChk?.checked);
            const closeAllOn = Boolean(closeAllChk?.checked);

            localStorage.setItem('kryvex:futures:confirm:master', masterOn ? '1' : '0');
            localStorage.setItem('kryvex:futures:confirm:market:skip', marketOn ? '0' : '1');
            localStorage.setItem('kryvex:futures:confirm:limit:skip', limitOn ? '0' : '1');
            localStorage.setItem('kryvex:futures:confirm:stop:skip', stopOn ? '0' : '1');
            localStorage.setItem('kryvex:futures:confirm:close-position:skip', closeSingleOn ? '0' : '1');
            localStorage.setItem('kryvex:futures:confirm:close-all:skip', closeAllOn ? '0' : '1');

            updateUI();

            const modal = document.getElementById('modalOrderConfirm');
            if (modal) modal.hidden = true;

            showDashboardAlert('Order confirmation reminder preferences saved.', 'success');
        });
    }
}

// ===================================================
// SUPPORT TICKETS CLIENT-SIDE LOGIC
// ===================================================
let selectedSupportTopic = 'Other';

const getSupportAuthToken = () => localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token') || localStorage.getItem('token') || '';

function setupSupportTicketForm() {
    const topicCards = document.querySelectorAll('.support-topic-card');
    const subjectInput = document.getElementById('inputTicketSubject');

    const topicTitles = {
        'Deposit': 'Deposit issue',
        'Withdraw': 'Withdrawal issue',
        'Trading': 'Trading issue',
        'KYC': 'KYC verification',
        'Other': 'Other'
    };

    topicCards.forEach(card => {
        card.addEventListener('click', () => {
            topicCards.forEach(c => {
                c.classList.remove('active-topic');
                c.style.background = 'rgba(255, 255, 255, 0.03)';
                c.style.borderColor = 'rgba(255, 255, 255, 0.08)';
            });
            card.classList.add('active-topic');
            card.style.background = 'rgba(139, 92, 246, 0.12)';
            card.style.borderColor = 'rgba(139, 92, 246, 0.35)';
            selectedSupportTopic = card.dataset.topic || 'Other';

            if (subjectInput) {
                subjectInput.value = topicTitles[selectedSupportTopic] || selectedSupportTopic;
            }
        });
    });

    const descArea = document.getElementById('inputTicketDescription');
    const counterEl = document.getElementById('ticketDescCharCount');
    if (descArea && counterEl) {
        descArea.addEventListener('input', () => {
            counterEl.textContent = `${descArea.value.length}/2000`;
        });
    }

    const form = document.getElementById('userCreateTicketForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const token = getSupportAuthToken();
            const subject = document.getElementById('inputTicketSubject')?.value?.trim();
            const description = document.getElementById('inputTicketDescription')?.value?.trim();
            const btn = document.getElementById('btnSubmitTicket');

            if (!subject || !description) return;

            if (btn) { btn.disabled = true; btn.textContent = 'Submitting...'; }

            try {
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                };
                if (token) headers['Authorization'] = `Bearer ${token}`;

                const res = await fetch('/api/support/tickets', {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({
                        topic: selectedSupportTopic,
                        subject: subject,
                        description: description
                    })
                });

                const data = await res.json();
                if (data.success) {
                    document.getElementById('inputTicketSubject').value = '';
                    document.getElementById('inputTicketDescription').value = '';
                    if (counterEl) counterEl.textContent = '0/2000';
                    if (typeof window.showToast === 'function') {
                        window.showToast({ type: 'success', title: 'Ticket Created', message: 'Support ticket submitted successfully.' });
                    }
                    await loadUserSupportTickets();
                } else {
                    alert(data.message || 'Failed to create ticket.');
                }
            } catch (err) {
                alert('Failed to submit ticket. Please check your connection.');
            } finally {
                if (btn) { btn.disabled = false; btn.textContent = '✈ Submit ticket'; }
            }
        });
    }
}

async function loadUserSupportTickets() {
    const container = document.getElementById('userTicketsContainer');
    const openCountEl = document.getElementById('userOpenTicketsCount');
    const totalCountEl = document.getElementById('userTotalTicketsCount');
    const headerTotalEl = document.getElementById('userTicketsTotalHeader');

    const token = getSupportAuthToken();
    const headers = { 'Accept': 'application/json' };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    try {
        const res = await fetch('/api/support/tickets', { headers });

        const data = await res.json();
        if (!data.success) return;

        if (openCountEl) openCountEl.textContent = data.open_count || 0;
        if (totalCountEl) totalCountEl.textContent = data.total_count || 0;
        if (headerTotalEl) headerTotalEl.textContent = `${data.total_count || 0} total`;

        const tickets = data.tickets || [];
        if (!container) return;

        if (tickets.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; padding: 24px; color: #64748b; font-size: 0.9rem;">
                    No support tickets submitted yet. Choose a topic above to submit a new ticket.
                </div>
            `;
            return;
        }

        container.innerHTML = tickets.map((t, idx) => {
            const statusUpper = (t.status || 'OPEN').toUpperCase();
            let statusBadge = '<span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 3px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;">OPEN</span>';
            if (statusUpper === 'ANSWERED') {
                statusBadge = '<span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.35); padding: 3px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;">ANSWERED</span>';
            } else if (statusUpper === 'CLOSED') {
                statusBadge = '<span style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; border: 1px solid rgba(148, 163, 184, 0.3); padding: 3px 12px; border-radius: 20px; font-size: 0.72rem; font-weight: 700;">CLOSED</span>';
            }

            const dateStr = t.created_at ? new Date(t.created_at).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '';
            const replies = t.replies || [];

            const repliesHtml = replies.map(r => {
                const isSupport = r.sender_type === 'admin';
                const label = isSupport ? 'SUPPORT' : 'YOU';
                const bgStyle = isSupport
                    ? 'background: rgba(139, 92, 246, 0.12); border: 1px solid rgba(139, 92, 246, 0.25);'
                    : 'background: rgba(0, 0, 0, 0.35); border: 1px solid rgba(255, 255, 255, 0.06);';
                const labelColor = isSupport ? 'color: #a78bfa;' : 'color: #cbd5e1;';
                const rDateStr = r.created_at ? new Date(r.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

                return `
                    <div style="${bgStyle} border-radius: 12px; padding: 14px; margin-bottom: 10px;">
                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #94a3b8; margin-bottom: 6px;">
                            <span style="font-weight: 800; ${labelColor}">${label}</span>
                            <span>${rDateStr}</span>
                        </div>
                        <p style="margin: 0; color: #ffffff; font-size: 0.9rem; line-height: 1.4; white-space: pre-wrap;">${escapeHtml(r.message || '')}</p>
                    </div>
                `;
            }).join('');

            const isClosed = statusUpper === 'CLOSED';
            const replyInputHtml = isClosed ? '' : `
                <div style="display: flex; gap: 10px; margin-top: 14px;">
                    <input type="text" class="ticket-reply-input-${t._id || idx}" placeholder="Write a reply..." style="flex: 1; height: 42px; background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 10px; color: #ffffff; padding: 0 14px; font-size: 0.9rem;">
                    <button type="button" onclick="sendUserTicketReply('${t._id || t.ticket_uid}', 'ticket-reply-input-${t._id || idx}')" class="settings-action-btn btn-purple" style="height: 42px; padding: 0 20px; font-weight: 700; font-size: 0.88rem; display: flex; align-items: center; gap: 6px;">
                        ✈ Reply
                    </button>
                </div>
            `;

            return `
                <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; overflow: hidden;">
                    <div onclick="toggleTicketAccordion('ticket-thread-${idx}')" style="display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; cursor: pointer; user-select: none;">
                        <div>
                            <strong style="font-size: 1rem; color: #ffffff; display: block; margin-bottom: 2px;">${escapeHtml(t.subject || 'No Subject')}</strong>
                            <span style="font-size: 0.78rem; color: #94a3b8;">${escapeHtml(t.ticket_uid || '#')} • ${dateStr}</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            ${statusBadge}
                            <span id="ticket-arrow-${idx}" style="font-size: 0.8rem; color: #94a3b8; transition: transform 0.2s;">▼</span>
                        </div>
                    </div>

                    <div id="ticket-thread-${idx}" style="display: none; padding: 0 20px 20px; border-top: 1px solid rgba(255, 255, 255, 0.04);">
                        <div style="padding-top: 14px;">
                            ${repliesHtml}
                            ${replyInputHtml}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

    } catch (err) {
        console.error('Failed to load user support tickets', err);
    }
}

function toggleTicketAccordion(threadId) {
    const thread = document.getElementById(threadId);
    if (!thread) return;
    const isHidden = thread.style.display === 'none';
    thread.style.display = isHidden ? 'block' : 'none';
}

async function sendUserTicketReply(ticketId, inputClass) {
    const input = document.querySelector(`.${inputClass}`);
    if (!input) return;
    const message = input.value.trim();
    if (!message) return;

    const token = getSupportAuthToken();
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    };
    if (token) headers['Authorization'] = `Bearer ${token}`;

    try {
        const res = await fetch(`/api/support/tickets/${ticketId}/reply`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ message })
        });

        const data = await res.json();
        if (data.success) {
            input.value = '';
            await loadUserSupportTickets();
        } else {
            alert(data.message || 'Failed to send reply.');
        }
    } catch (err) {
        alert('Failed to send reply.');
    }
}

window.loadUserSupportTickets = loadUserSupportTickets;
window.toggleTicketAccordion = toggleTicketAccordion;
window.sendUserTicketReply = sendUserTicketReply;

document.addEventListener('DOMContentLoaded', () => {
    setupSupportTicketForm();
});

function setupDownloadPersonalDataModal() {
    const btnConfirm = document.getElementById('btnConfirmDownloadPersonalData');
    if (!btnConfirm) return;

    btnConfirm.addEventListener('click', () => {
        const token = localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token') || localStorage.getItem('token') || '';
        let userUid = '';
        try {
            const user = JSON.parse(localStorage.getItem('kryvex_user') || '{}');
            userUid = user.uid || '';
        } catch (e) {}

        const modal = document.getElementById('modalDownloadData');
        if (modal) modal.hidden = true;

        const url = `/user/personal-data/pdf?token=${encodeURIComponent(token)}&uid=${encodeURIComponent(userUid)}`;
        window.open(url, '_blank');
    });
}

function setupExportTransactionRecordsModal() {
    const btnCsv = document.getElementById('btnExportTxCsv');
    const btnPdf = document.getElementById('btnExportTxPdf');
    if (!btnCsv && !btnPdf) return;

    function getExportParams() {
        const token = localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token') || localStorage.getItem('token') || '';
        let userUid = '';
        try {
            const user = JSON.parse(localStorage.getItem('kryvex_user') || '{}');
            userUid = user.uid || '';
        } catch (e) {}

        const currency = document.getElementById('exportTxCurrencySelect')?.value || 'all';
        const type = document.getElementById('exportTxTypeSelect')?.value || 'all';

        const params = new URLSearchParams({
            token: token,
            uid: userUid,
            currency: currency,
            type: type
        });
        return params.toString();
    }

    if (btnCsv) {
        btnCsv.addEventListener('click', () => {
            const modal = document.getElementById('modalExportTransactions');
            if (modal) modal.hidden = true;
            window.open(`/wallet/ledger/export/csv?${getExportParams()}`, '_blank');
        });
    }

    if (btnPdf) {
        btnPdf.addEventListener('click', () => {
            const modal = document.getElementById('modalExportTransactions');
            if (modal) modal.hidden = true;
            window.open(`/wallet/ledger/export/pdf?${getExportParams()}`, '_blank');
        });
    }
}

function setupCloseAccountModal() {
    const form = document.getElementById('formRequestDeleteAccount');
    const feedback = document.getElementById('deleteAccountFeedback');
    const submitBtn = document.getElementById('btnSubmitDeleteAccount');
    const modal = document.getElementById('modalCloseAccount');

    // Attach cancel button listeners
    document.getElementById('btnCancelDeletionTop')?.addEventListener('click', handleCancelDeletion);
    document.getElementById('btnCancelDeletionInsideModal')?.addEventListener('click', handleCancelDeletion);

    // Listen for modal opening to trigger eligibility check
    document.querySelectorAll('[data-open-modal="modalCloseAccount"]').forEach(btn => {
        btn.addEventListener('click', () => {
            fetchDeletionEligibility();
        });
    });

    async function fetchDeletionEligibility() {
        const badge = document.getElementById('deletionPrereqsStatusBadge');
        const warning = document.getElementById('deletionPrereqsWarning');
        const submitBtn = document.getElementById('btnSubmitDeleteAccount');
        const itemBal = document.getElementById('checkItemBalance');
        const itemPos = document.getElementById('checkItemPositions');
        const itemOrd = document.getElementById('checkItemOrders');
        const itemWth = document.getElementById('checkItemWithdrawals');

        if (badge) {
            badge.textContent = 'Checking...';
            badge.style.background = 'rgba(255,255,255,0.06)';
            badge.style.color = '#94a3b8';
        }

        const token = localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token') || localStorage.getItem('token') || '';
        if (!token) return;

        try {
            const res = await fetch('/api/auth/deletion-eligibility', {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });
            const data = await res.json();
            if (!res.ok || !data.success) return;

            const checks = data.checks || {};
            const eligible = Boolean(data.eligible);

            const updateItem = (el, passed, label) => {
                if (!el) return;
                const icon = el.querySelector('.check-icon');
                const text = el.querySelector('.check-label');
                if (icon) {
                    icon.textContent = passed ? '✓' : '✗';
                    icon.style.color = passed ? '#22c55e' : '#ef4444';
                }
                if (text) {
                    text.textContent = label;
                    text.style.color = passed ? '#86efac' : '#fca5a5';
                }
            };

            updateItem(itemBal, checks.balance?.passed, checks.balance?.label || 'Balance must be $0.00');
            updateItem(itemPos, checks.positions?.passed, checks.positions?.label || 'No open trading positions');
            updateItem(itemOrd, checks.orders?.passed, checks.orders?.label || 'No pending orders');
            updateItem(itemWth, checks.withdrawals?.passed, checks.withdrawals?.label || 'No pending withdrawals');

            if (eligible) {
                if (badge) {
                    badge.textContent = 'Ready ✓';
                    badge.style.background = 'rgba(34, 197, 94, 0.15)';
                    badge.style.color = '#22c55e';
                }
                if (warning) warning.style.display = 'none';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                    submitBtn.removeAttribute('title');
                }
            } else {
                if (badge) {
                    badge.textContent = 'Action Required';
                    badge.style.background = 'rgba(239, 68, 68, 0.15)';
                    badge.style.color = '#f87171';
                }
                if (warning) {
                    warning.style.display = 'block';
                    const reasonsHtml = (data.reasons || []).map(r => `• ${escapeHtml(r)}`).join('<br>');
                    warning.innerHTML = `<strong>Please resolve the following before requesting deletion:</strong><br>${reasonsHtml}`;
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.5';
                    submitBtn.style.cursor = 'not-allowed';
                    submitBtn.setAttribute('title', 'Please resolve all account closure requirements above.');
                }
            }
        } catch (e) {
            console.warn('Failed to fetch deletion eligibility:', e);
        }
    }
    window.fetchDeletionEligibility = fetchDeletionEligibility;

    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const reasonSelect = document.getElementById('deleteAccountReasonSelect');
            const reasonDetails = document.getElementById('deleteAccountReasonDetails');
            const passwordInput = document.getElementById('deleteAccountPassword');

            const mainReason = reasonSelect ? reasonSelect.value : '';
            const extraDetails = reasonDetails ? reasonDetails.value.trim() : '';
            const password = passwordInput ? passwordInput.value : '';

            if (!mainReason) {
                showFeedback('Please select a reason for account closure.', 'error');
                return;
            }

            if (!password) {
                showFeedback('Please enter your current password to confirm.', 'error');
                return;
            }

            const fullReason = extraDetails ? `${mainReason} - ${extraDetails}` : mainReason;

            const token = localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token') || localStorage.getItem('token') || '';
            if (!token) {
                showFeedback('Authentication session expired. Please log in again.', 'error');
                return;
            }

            setLoading(true);

            try {
                const res = await fetch('/api/auth/request-delete-account', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify({
                        reason: fullReason,
                        password: password
                    })
                });

                const data = await res.json();

                if (!res.ok || !data.success) {
                    showFeedback(data.message || 'Failed to submit account deletion request.', 'error');
                    setLoading(false);
                    // Re-run eligibility check in case requirements changed
                    fetchDeletionEligibility();
                    return;
                }

                showFeedback('Your account deletion request has been submitted for review. Your account is now in read-only mode.', 'success');

                // Update localStorage user state immediately (DO NOT LOG OUT)
                let currentUser = {};
                try { currentUser = JSON.parse(localStorage.getItem('kryvex_user') || '{}'); } catch(e) {}
                currentUser.deletion_status = 'pending';
                currentUser.deletion_reason = fullReason;
                currentUser.deletion_requested_at = new Date().toISOString();
                localStorage.setItem('kryvex_user', JSON.stringify(currentUser));

                renderDeletionStatusUI(currentUser);

                setTimeout(() => {
                    if (modal) modal.hidden = true;
                    // Reload to immediately activate the global restriction banner and disable trading actions
                    window.location.reload();
                }, 1200);

            } catch (err) {
                showFeedback('Network error. Please try again later.', 'error');
                setLoading(false);
            }
        });
    }

    function showFeedback(msg, type) {
        if (!feedback) return;
        feedback.style.display = 'block';
        feedback.textContent = msg;
        if (type === 'error') {
            feedback.style.background = 'rgba(239, 68, 68, 0.15)';
            feedback.style.color = '#f87171';
            feedback.style.border = '1px solid rgba(239, 68, 68, 0.35)';
        } else {
            feedback.style.background = 'rgba(16, 185, 129, 0.15)';
            feedback.style.color = '#34d399';
            feedback.style.border = '1px solid rgba(16, 185, 129, 0.35)';
        }
    }

    function setLoading(isLoading) {
        if (!submitBtn) return;
        submitBtn.disabled = isLoading;
        submitBtn.style.opacity = isLoading ? '0.7' : '1';
        submitBtn.style.cursor = isLoading ? 'not-allowed' : 'pointer';
        submitBtn.innerHTML = isLoading 
            ? '<span>Submitting Request...</span>' 
            : '<span>Submit Deletion Request</span>';
    }
}

function renderDeletionStatusUI(user) {
    if (!user) return;
    const delStatus = String(user.deletion_status || '').toLowerCase();
    const userStatus = String(user.status || '').toLowerCase();

    if (delStatus === 'approved' || userStatus === 'deleted') {
        if (typeof window.handleAccountDeleted === 'function') {
            window.handleAccountDeleted();
        } else {
            localStorage.clear();
            sessionStorage.clear();
            alert('Your account deletion request has been approved by administrator. Your account is now closed.');
            window.location.href = '/login?account_deleted=1';
        }
        return;
    }

    const isPending = delStatus === 'pending';
    const noticeBox = document.getElementById('settingsDeletionNoticeBox');
    const noticeText = document.getElementById('settingsDeletionNoticeText');
    const closeTitle = document.getElementById('closeAccountTitle');
    const closeDesc = document.getElementById('closeAccountDesc');
    const closeActions = document.getElementById('closeAccountActions');
    const formView = document.getElementById('formRequestDeleteAccount');
    const pendingView = document.getElementById('modalCloseAccountPendingView');
    const heading = document.getElementById('modalCloseAccountHeading');

    if (isPending) {
        // Show top notice banner in Settings
        if (noticeBox) {
            noticeBox.style.display = 'block';
            if (noticeText) {
                const dateStr = user.deletion_requested_at 
                    ? new Date(user.deletion_requested_at).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric' })
                    : 'recently';
                noticeText.textContent = `You submitted an account deletion request on ${dateStr}. Reason: "${user.deletion_reason || 'Personal'}". Your account is locked in read-only mode awaiting admin approval.`;
            }
        }

        // Update Close Account row in Settings
        if (closeTitle) closeTitle.textContent = 'Account Deletion Request';
        if (closeDesc) closeDesc.textContent = `Pending review by administrator. Your account is in read-only mode.`;
        if (closeActions) {
            closeActions.innerHTML = `
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="background:rgba(245, 158, 11, 0.15); border:1px solid rgba(245, 158, 11, 0.4); color:#f59e0b; padding:4px 10px; border-radius:6px; font-weight:700; font-size:0.78rem; text-transform:uppercase;">Under Review</span>
                    <button type="button" class="settings-action-btn" id="btnCancelDeletionInRow" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#fff; font-size:0.82rem; font-weight:700;">Cancel Request</button>
                    <button type="button" class="settings-action-btn" id="btnOpenPendingStatusModal" style="background:rgba(245, 158, 11, 0.1); border:1px solid rgba(245, 158, 11, 0.3); color:#f59e0b; font-size:0.82rem; font-weight:700;">Details</button>
                </div>
            `;
            document.getElementById('btnCancelDeletionInRow')?.addEventListener('click', handleCancelDeletion);
            document.getElementById('btnOpenPendingStatusModal')?.addEventListener('click', () => {
                const modal = document.getElementById('modalCloseAccount');
                if (modal) modal.hidden = false;
            });
        }

        // Inside modal: show pending view instead of submit form
        if (heading) heading.textContent = 'Account Deletion Request';
        if (formView) formView.style.display = 'none';
        if (pendingView) {
            pendingView.style.display = 'block';
            const reasonEl = document.getElementById('modalPendingReasonText');
            const dateEl = document.getElementById('modalPendingDateText');
            if (reasonEl) reasonEl.textContent = user.deletion_reason || 'Not specified';
            if (dateEl) {
                dateEl.textContent = user.deletion_requested_at 
                    ? new Date(user.deletion_requested_at).toLocaleDateString('en-US', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                    : 'Recently';
            }
        }
    } else {
        if (noticeBox) noticeBox.style.display = 'none';
        if (closeTitle) closeTitle.textContent = 'Close Account';
        if (closeDesc) closeDesc.textContent = 'Permanently close and delete your Kryvex account.';
        if (closeActions) {
            closeActions.innerHTML = `
                <button type="button" class="settings-action-btn btn-danger" id="btnOpenCloseAccountModal" data-open-modal="modalCloseAccount">Delete</button>
            `;
            document.getElementById('btnOpenCloseAccountModal')?.addEventListener('click', () => {
                const modal = document.getElementById('modalCloseAccount');
                if (modal) modal.hidden = false;
                if (typeof window.fetchDeletionEligibility === 'function') {
                    window.fetchDeletionEligibility();
                }
            });
        }
        if (heading) heading.textContent = 'Request Account Deletion';
        if (formView) formView.style.display = 'block';
        if (pendingView) pendingView.style.display = 'none';
    }
}

async function handleCancelDeletion() {
    if (!confirm('Are you sure you want to cancel your account deletion request and restore full account access?')) {
        return;
    }

    const token = localStorage.getItem('kryvex_access_token') || localStorage.getItem('access_token') || localStorage.getItem('token') || '';
    if (!token) return;

    try {
        const res = await fetch('/api/auth/cancel-delete-account', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });

        const data = await res.json();

        if (res.ok && data.success) {
            if (typeof window.showToast === 'function') {
                window.showToast({ type: 'success', title: 'Request Cancelled', message: data.message });
            } else {
                alert(data.message || 'Account deletion request cancelled successfully.');
            }

            const modal = document.getElementById('modalCloseAccount');
            if (modal) modal.hidden = true;

            // Update user in localStorage
            let currentUser = {};
            try { currentUser = JSON.parse(localStorage.getItem('kryvex_user') || '{}'); } catch(e) {}
            currentUser.deletion_status = 'none';
            currentUser.deletion_reason = null;
            currentUser.deletion_requested_at = null;
            localStorage.setItem('kryvex_user', JSON.stringify(currentUser));

            window.location.reload();
        } else {
            alert(data.message || 'Failed to cancel deletion request.');
        }
    } catch (err) {
        alert('Network error while cancelling deletion request.');
    }
}

// =========================================================================
// LENIS SMOOTH SCROLLING & SCROLL ANIMATIONS FOR SETTINGS & PROFILE
// =========================================================================
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
        window.lenisInstance = lenisInstance;

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
    const progressBar = document.getElementById('settingsScrollProgressBar');
    const topBtn = document.getElementById('settingsScrollToTopBtn');

    const scrollTop = window.scrollY || document.documentElement.scrollTop || 0;
    const docHeight = document.documentElement.scrollHeight - window.innerHeight;

    if (progressBar && docHeight > 0) {
        const progress = Math.min(100, Math.max(0, (scrollTop / docHeight) * 100));
        progressBar.style.width = `${progress}%`;
    }

    if (topBtn) {
        if (scrollTop > 300) {
            topBtn.classList.add('is-visible');
        } else {
            topBtn.classList.remove('is-visible');
        }
    }
}

function initScrollProgressAndTopBtn() {
    const topBtn = document.getElementById('settingsScrollToTopBtn');
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
    const revealItems = document.querySelectorAll('.reveal-item:not(.is-visible)');
    if (!revealItems.length) return;

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.05,
            rootMargin: '0px 0px -30px 0px'
        });

        revealItems.forEach((item) => {
            const rect = item.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                item.classList.add('is-visible');
            } else {
                observer.observe(item);
            }
        });
    } else {
        revealItems.forEach(item => item.classList.add('is-visible'));
    }
}
window.setupScrollReveal = setupScrollReveal;





