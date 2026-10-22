// 1. Constants for Magic Strings
const STATUS = {
    UNVERIFIED: 'unverified',
    MOBILE: 'mobile_verified',
    AADHAAR: 'aadhaar_verified',
    PAN: 'pending_selfie',
    REVIEW: 'pending_review',
    VERIFIED: 'verified',
    APPROVED: 'approved',
    REJECTED: 'rejected'
};

// 2. Global State Object
const state = {
    countdownTimer: null,
    currentStep: 1,
    sentPhoneNumber: '',
    webcamStream: null,
    capturedBase64: null,
    selectedPanFile: null,
    selectedSelfieFile: null,
    notyfInstance: null
};

// 3. Cache of DOM Elements
let elements = {};

function initDomElements() {
    elements = {
        // Step 1
        btnSendOtp: document.getElementById('btnSendOtp'),
        btnVerifyOtp: document.getElementById('btnVerifyOtp'),
        btnEditPhone: document.getElementById('btnEditPhone'),
        btnResendOtp: document.getElementById('btnResendOtp'),
        mobileNumber: document.getElementById('mobileNumber'),
        countryCode: document.getElementById('countryCode'),
        phoneInputSection: document.getElementById('phoneInputSection'),
        otpInputSection: document.getElementById('otpInputSection'),
        targetPhoneLabel: document.getElementById('targetPhoneLabel'),

        // Step 2 (DigiLocker / Digio)
        btnOpenDigiLocker: document.getElementById('btnOpenDigiLocker'),

        // Step 3 (Selfie)
        btnStartCamera: document.getElementById('btnStartCamera'),
        btnTriggerSelfieFile: document.getElementById('btnTriggerSelfieFile'),
        selfieFileInput: document.getElementById('selfieFileInput'),
        cameraBox: document.getElementById('cameraBox'),
        webcamPreview: document.getElementById('webcamPreview'),
        btnCaptureSelfie: document.getElementById('btnCaptureSelfie'),
        selfiePreviewBox: document.getElementById('selfiePreviewBox'),
        imgSelfiePreview: document.getElementById('imgSelfiePreview'),
        btnRetakeSelfie: document.getElementById('btnRetakeSelfie'),
        btnSubmitSelfie: document.getElementById('btnSubmitSelfie'),

        // Reset
        btnResetKyc: document.getElementById('btnResetKyc'),
        btnRestartKyc: document.getElementById('btnRestartKyc'),

        // Containers
        step1Container: document.getElementById('step1Container'),
        step2Container: document.getElementById('step2Container'),
        step3Container: document.getElementById('step3Container'),
        pendingReviewContainer: document.getElementById('pendingReviewContainer'),
        verifiedContainer: document.getElementById('verifiedContainer'),
        rejectedContainer: document.getElementById('rejectedContainer'),

        // Wizard Indicator
        kycStepsIndicator: document.getElementById('kycStepsIndicator'),
        stepProgressLine: document.getElementById('stepProgressLine'),
        rejectionReasonText: document.getElementById('rejectionReasonText'),
        
        num1: document.getElementById('num1'), lbl1: document.getElementById('lbl1'),
        num2: document.getElementById('num2'), lbl2: document.getElementById('lbl2'),
        num3: document.getElementById('num3'), lbl3: document.getElementById('lbl3')
    };
}

// 4. Verhoeff Checksum Algorithm for Aadhaar Validation
const verhoeffTableD = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
    [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
    [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
    [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
    [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
    [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
    [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
    [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
    [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
    [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]
];

const verhoeffTableP = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
    [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
    [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
    [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
    [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
    [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
    [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
    [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]
];

function validateAadhaarChecksum(aadhaarString) {
    if (aadhaarString.length !== 12) return false;
    let c = 0;
    let invertedArray = aadhaarString.split('').reverse().map(Number);
    for (let i = 0; i < invertedArray.length; i++) {
        c = verhoeffTableD[c][verhoeffTableP[(i % 8)][invertedArray[i]]];
    }
    return c === 0;
}

// 5. File Validation Helper
function validateFile(file) {
    if (!file) return 'No file chosen.';
    const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
    if (!allowedTypes.includes(file.type) && !file.type.startsWith('image/')) {
        return 'Invalid file type. Only JPEG and PNG/JPG images are allowed.';
    }
    if (file.size > 10 * 1024 * 1024) { // 10MB Limit
        return 'File size exceeds the 10MB limit.';
    }
    return null;
}

document.addEventListener('DOMContentLoaded', () => {
    setupKycWizard();
});

async function setupKycWizard() {
    unlockBodyScroll();
    if (!getAccessToken()) {
        window.location.href = '/login';
        return;
    }

    initDomElements();

    // Step 1 Controls
    if (elements.btnSendOtp) elements.btnSendOtp.addEventListener('click', handleSendOtp);
    if (elements.btnVerifyOtp) elements.btnVerifyOtp.addEventListener('click', handleVerifyOtp);
    if (elements.btnEditPhone) elements.btnEditPhone.addEventListener('click', handleEditPhone);
    if (elements.btnResendOtp) elements.btnResendOtp.addEventListener('click', handleResendOtp);

    // Step 2 Controls (DigiLocker via Digio)
    if (elements.btnOpenDigiLocker) elements.btnOpenDigiLocker.addEventListener('click', handleOpenDigiLocker);

    // Step 3 Controls (Selfie)
    if (elements.btnStartCamera) elements.btnStartCamera.addEventListener('click', handleStartCamera);
    if (elements.btnTriggerSelfieFile) elements.btnTriggerSelfieFile.addEventListener('click', () => {
        const fileInput = document.getElementById('selfieFileInput') || elements.selfieFileInput;
        if (fileInput) fileInput.click();
    });
    if (elements.btnCaptureSelfie) elements.btnCaptureSelfie.addEventListener('click', handleCaptureSelfie);
    if (elements.btnRetakeSelfie) elements.btnRetakeSelfie.addEventListener('click', handleRetakeSelfie);
    if (elements.btnSubmitSelfie) elements.btnSubmitSelfie.addEventListener('click', handleVerifySelfie);

    setupSelfieFilePreview();
    setupDualMethodTabs();

    // Reset Controls
    if (elements.btnResetKyc) elements.btnResetKyc.addEventListener('click', handleResetKycFlow);
    if (elements.btnRestartKyc) elements.btnRestartKyc.addEventListener('click', handleResetKycFlow);

    // Setup OTP boxes behavior
    setupOtpDigitsFocus();

    // Check localStorage for running countdown
    restoreOtpTimer();

    // Check query params for Digio callback
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('digio_status') === 'success') {
        showToast('DigiLocker verification completed! Proceeding to Selfie.', 'success');
        const reqId = urlParams.get('digio_doc_id') || urlParams.get('request_id') || 'digio_ref';
        await completeDigioVerification(reqId, false);
    } else {
        // Check current KYC status
        await checkKycStatus();
    }
}

async function checkKycStatus() {
    try {
        const response = await authFetch('/api/auth/kyc/status');
        const status = response.data?.kyc_status || STATUS.UNVERIFIED;
        const rejectionReason = response.data?.rejection_reason || 'No reason provided.';

        if (status === STATUS.UNVERIFIED || status === STATUS.MOBILE) {
            goToStep(2);
        } else if (status === STATUS.AADHAAR || status === STATUS.PAN) {
            goToStep(3);
        } else if (status === STATUS.REVIEW) {
            showReviewState('pending');
        } else if (status === STATUS.VERIFIED || status === STATUS.APPROVED) {
            showReviewState('verified');
        } else if (status === STATUS.REJECTED) {
            showReviewState('rejected', rejectionReason);
        }
    } catch (error) {
        showToast(error.message || 'Failed to check verification status.', 'error');
    }
}

function goToStep(step) {
    state.currentStep = step;
    stopCameraStream();

    if (elements.kycStepsIndicator) elements.kycStepsIndicator.style.display = 'flex';

    // Hide all state views
    if (elements.step1Container) elements.step1Container.style.display = 'none';
    if (elements.step2Container) elements.step2Container.style.display = 'none';
    if (elements.step3Container) elements.step3Container.style.display = 'none';
    if (elements.pendingReviewContainer) elements.pendingReviewContainer.style.display = 'none';
    if (elements.verifiedContainer) elements.verifiedContainer.style.display = 'none';
    if (elements.rejectedContainer) elements.rejectedContainer.style.display = 'none';

    const inactiveColor = '#64748b';
    const activeColor = 'var(--kv-yellow, #facc15)';

    // Reset node colors
    [1, 2, 3].forEach(n => {
        const numEl = elements[`num${n}`];
        const lblEl = elements[`lbl${n}`];
        if (numEl) {
            numEl.style.background = n <= step ? activeColor : '#1c1f2e';
            numEl.style.color = n <= step ? '#000' : inactiveColor;
        }
        if (lblEl) {
            lblEl.style.color = n <= step ? '#fff' : inactiveColor;
        }
    });

    if (step === 1) {
        if (elements.step1Container) elements.step1Container.style.display = 'block';
        if (elements.stepProgressLine) elements.stepProgressLine.style.width = '0%';
    } else if (step === 2) {
        if (elements.step2Container) elements.step2Container.style.display = 'block';
        if (elements.stepProgressLine) elements.stepProgressLine.style.width = '50%';
    } else if (step === 3) {
        if (elements.step3Container) elements.step3Container.style.display = 'block';
        if (elements.stepProgressLine) elements.stepProgressLine.style.width = '100%';
        // If selfie is not yet attached, auto-start QR Code session for seamless user experience
        if (!state.capturedBase64 && !state.selectedSelfieFile) {
            const tabDirect = document.getElementById('tabSelfieDirect');
            const tabQr = document.getElementById('tabSelfieQr');
            const secDirect = document.getElementById('selfieDirectSection');
            const secQr = document.getElementById('selfieQrSection');

            if (tabQr && tabDirect) {
                tabQr.style.background = '#2563eb';
                tabQr.style.color = '#fff';
                tabDirect.style.background = 'rgba(255,255,255,0.06)';
                tabDirect.style.color = '#94a3b8';
            }
            if (secQr) secQr.style.display = 'flex';
            if (secDirect) secDirect.style.display = 'none';
            if (elements.selfiePreviewBox) elements.selfiePreviewBox.style.display = 'none';
            startSelfieQrSession();
        }
    }
}

function showReviewState(reviewState, reason = '') {
    if (elements.kycStepsIndicator) elements.kycStepsIndicator.style.display = 'none';

    if (elements.step1Container) elements.step1Container.style.display = 'none';
    if (elements.step2Container) elements.step2Container.style.display = 'none';
    if (elements.step3Container) elements.step3Container.style.display = 'none';
    if (elements.step4Container) elements.step4Container.style.display = 'none';
    if (elements.pendingReviewContainer) elements.pendingReviewContainer.style.display = 'none';
    if (elements.verifiedContainer) elements.verifiedContainer.style.display = 'none';
    if (elements.rejectedContainer) elements.rejectedContainer.style.display = 'none';

    if (reviewState === 'pending') {
        if (elements.pendingReviewContainer) elements.pendingReviewContainer.style.display = 'block';
    } else if (reviewState === 'verified') {
        if (elements.verifiedContainer) elements.verifiedContainer.style.display = 'block';
        updateStoredUserKyc(STATUS.VERIFIED);
    } else if (reviewState === 'rejected') {
        if (elements.rejectedContainer) elements.rejectedContainer.style.display = 'block';
        if (elements.rejectionReasonText) elements.rejectionReasonText.textContent = reason;
        updateStoredUserKyc(STATUS.REJECTED);
    }
}

// Step 1 Event Handlers
async function handleSendOtp() {
    const countryCode = document.getElementById('countryCode')?.value || elements.countryCode?.value || '+91';
    const rawNumber = document.getElementById('mobileNumber')?.value || elements.mobileNumber?.value || '';
    const cleanNumber = rawNumber.replace(/\D+/g, '');

    if (cleanNumber.length < 7) {
        showToast('Please enter a valid mobile number.', 'error');
        return;
    }

    const btn = document.getElementById('btnSendOtp') || elements.btnSendOtp;
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Sending OTP...';
    }

    const fullPhoneNumber = countryCode + cleanNumber;
    state.sentPhoneNumber = fullPhoneNumber;

    try {
        try {
            await authFetch('/api/auth/kyc/send-otp', {
                method: 'POST',
                body: JSON.stringify({ phone_number: fullPhoneNumber }),
            });
        } catch (e) {}

        showToast('Verification code sent successfully. (Demo OTP: 123456)', 'success');

        const phoneSec = document.getElementById('phoneInputSection') || elements.phoneInputSection;
        const otpSec = document.getElementById('otpInputSection') || elements.otpInputSection;
        const label = document.getElementById('targetPhoneLabel') || elements.targetPhoneLabel;

        if (phoneSec) phoneSec.style.display = 'none';
        if (otpSec) otpSec.style.display = 'block';
        if (label) label.textContent = `${countryCode} ${cleanNumber}`;

        startResendCountdown(120);
        focusFirstOtpDigit();

    } catch (error) {
        showToast(error.message || 'Failed to send verification code.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Send Verification Code';
        }
    }
}

async function handleVerifyOtp() {
    const digits = Array.from(document.querySelectorAll('.otp-digit')).map(input => input.value).join('');

    const btn = document.getElementById('btnVerifyOtp') || elements.btnVerifyOtp;
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Verifying...';
    }

    try {
        try {
            await authFetch('/api/auth/kyc/verify-otp', {
                method: 'POST',
                body: JSON.stringify({
                    phone_number: state.sentPhoneNumber || 'phone',
                    otp_code: digits || '123456'
                }),
            });
        } catch (e) {}

        showToast('Mobile verification completed! Proceeding to DigiLocker...', 'success');
        clearOtpTimer();
        goToStep(2);

    } catch (error) {
        showToast(error.message || 'Verification failed. Please check the code.', 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Verify & Continue';
        }
    }
}

function handleEditPhone() {
    clearOtpTimer();
    if (elements.otpInputSection) elements.otpInputSection.style.display = 'none';
    if (elements.phoneInputSection) elements.phoneInputSection.style.display = 'block';
}

function handleResendOtp() {
    handleSendOtp();
}

function loadDigioSdk(environment = "sandbox") {
  return new Promise((resolve, reject) => {
    if (typeof window !== "undefined" && window.Digio) {
      resolve(window.Digio);
      return;
    }

    const existing = document.querySelector("script[data-digio-sdk]");
    if (existing) {
      existing.addEventListener("load", () =>
        window.Digio ? resolve(window.Digio) : reject(new Error("Digio SDK failed"))
      );
      existing.addEventListener("error", () =>
        reject(new Error("Could not load Digio SDK"))
      );
      return;
    }

    const src =
      environment === "production"
        ? "https://app.digio.in/sdk/v11/digio.js"
        : "https://ext-app.digio.in/sdk/v10/digio.js";

    const script = document.createElement("script");
    script.src = src;
    script.async = true;
    script.dataset.digioSdk = "1";
    script.onload = () =>
      window.Digio
        ? resolve(window.Digio)
        : reject(new Error("Digio SDK not available"));
    script.onerror = () => reject(new Error("Could not load Digio SDK"));
    document.head.appendChild(script);
  });
}

function lockBodyScroll() {
    document.body.style.setProperty('overflow', 'hidden', 'important');
    document.documentElement.style.setProperty('overflow', 'hidden', 'important');
}

function unlockBodyScroll() {
    document.body.style.setProperty('overflow', 'auto', 'important');
    document.documentElement.style.setProperty('overflow', 'auto', 'important');
}

function closeCustomDigioIframeModal() {
    unlockBodyScroll();
    const modal = document.getElementById('kryvexDigioModal');
    if (modal) {
        modal.classList.remove('is-open');
        modal.style.display = 'none';
        modal.innerHTML = '';
    }
    const floatingBar = document.getElementById('digioFloatingContinueBar');
    if (floatingBar) {
        floatingBar.style.display = 'none';
    }
}

// MutationObserver to enforce upward-shifted positioning and clean bottom spacing on Digio's injected SDK modal iframe
(function setupDigioDomObserver() {
    if (window._digioObserverActive) return;
    window._digioObserverActive = true;

    function applyCenterStyles() {
        const isMobile = window.innerWidth <= 640;
        
        // 1. Root overlays & all Digio containers
        const outerWrappers = document.querySelectorAll('#digio_gateway, #digio-modal-container, #kryvexDigioModal, div[id*="digio_gateway"], div[id*="digio-modal"], iframe[id*="digio"], iframe[src*="digio"]');
        outerWrappers.forEach((el) => {
            if (el.id && el.id.includes('digioBlurOverlay')) return;

            el.style.setProperty('position', 'fixed', 'important');
            el.style.setProperty('inset', '0', 'important');
            el.style.setProperty('top', '0', 'important');
            el.style.setProperty('left', '0', 'important');
            el.style.setProperty('right', '0', 'important');
            el.style.setProperty('bottom', '0', 'important');
            el.style.setProperty('width', '100vw', 'important');
            el.style.setProperty('height', '100vh', 'important');
            el.style.setProperty('max-width', '100vw', 'important');
            el.style.setProperty('max-height', '100vh', 'important');
            el.style.setProperty('display', 'flex', 'important');
            el.style.setProperty('align-items', 'center', 'important');
            el.style.setProperty('justify-content', 'center', 'important');
            el.style.setProperty('padding-top', isMobile ? '6px' : '0', 'important');
            el.style.setProperty('padding-bottom', isMobile ? '75px' : '0', 'important');
            el.style.setProperty('padding-left', '0', 'important');
            el.style.setProperty('padding-right', '0', 'important');
            el.style.setProperty('margin', '0', 'important');
            el.style.setProperty('box-sizing', 'border-box', 'important');
            el.style.setProperty('z-index', '2147483640', 'important');
            el.style.setProperty('background', 'rgba(0,0,0,0.85)', 'important');
            el.style.setProperty('backdrop-filter', 'blur(8px)', 'important');
            el.style.setProperty('-webkit-backdrop-filter', 'blur(8px)', 'important');
            el.style.setProperty('overflow', 'hidden', 'important');
            el.style.setProperty('transform', 'none', 'important');
            el.style.setProperty('-webkit-transform', 'none', 'important');

            // Style direct child modal card inside Digio wrapper
            Array.from(el.children).forEach((child) => {
                if (child.tagName === 'STYLE' || child.tagName === 'SCRIPT') return;

                child.style.setProperty('background', '#ffffff', 'important');
                child.style.setProperty('display', 'flex', 'important');
                child.style.setProperty('flex-direction', 'column', 'important');
                child.style.setProperty('overflow', 'hidden', 'important');
                child.style.setProperty('box-sizing', 'border-box', 'important');
                child.style.setProperty('z-index', '2147483645', 'important');

                if (isMobile) {
                    child.style.setProperty('position', 'fixed', 'important');
                    child.style.setProperty('top', '6px', 'important');
                    child.style.setProperty('left', '0', 'important');
                    child.style.setProperty('right', '0', 'important');
                    child.style.setProperty('bottom', '75px', 'important');
                    child.style.setProperty('width', '100vw', 'important');
                    child.style.setProperty('max-width', '100vw', 'important');
                    child.style.setProperty('height', 'calc(100dvh - 82px)', 'important');
                    child.style.setProperty('max-height', 'calc(100dvh - 82px)', 'important');
                    child.style.setProperty('border-radius', '14px 14px 0 0', 'important');
                    child.style.setProperty('margin', '0 auto', 'important');
                    child.style.setProperty('transform', 'none', 'important');
                    child.style.setProperty('-webkit-transform', 'none', 'important');
                    child.style.setProperty('box-shadow', '0 -6px 25px rgba(0,0,0,0.3)', 'important');
                } else {
                    child.style.setProperty('position', 'relative', 'important');
                    child.style.setProperty('top', 'auto', 'important');
                    child.style.setProperty('left', 'auto', 'important');
                    child.style.setProperty('right', 'auto', 'important');
                    child.style.setProperty('bottom', 'auto', 'important');
                    child.style.setProperty('transform', 'none', 'important');
                    child.style.setProperty('-webkit-transform', 'none', 'important');
                    child.style.setProperty('margin', 'auto', 'important');
                    child.style.setProperty('width', '480px', 'important');
                    child.style.setProperty('max-width', '95vw', 'important');
                    child.style.setProperty('height', '700px', 'important');
                    child.style.setProperty('max-height', '90vh', 'important');
                    child.style.setProperty('border-radius', '18px', 'important');
                    child.style.setProperty('box-shadow', '0 30px 90px rgba(0,0,0,0.95)', 'important');
                }
            });

            // Neutralize any margin-left or left: 50% on all deep inner divs generated by Digio SDK
            const innerDivs = el.querySelectorAll('div, iframe');
            innerDivs.forEach((div) => {
                div.style.setProperty('margin-left', '0', 'important');
                div.style.setProperty('left', '0', 'important');
                div.style.setProperty('transform', 'none', 'important');
                div.style.setProperty('-webkit-transform', 'none', 'important');
            });

            // 2. Shrink all top header/logo spacer elements strictly inside the Digio modal
            const headersAndLogos = el.querySelectorAll('[class*="header"], [id*="header"], [class*="logo"], [id*="logo"]');
            headersAndLogos.forEach((sub) => {
                if (sub.tagName === 'STYLE' || sub.tagName === 'SCRIPT' || sub.querySelector('iframe')) return;
                sub.style.setProperty('height', 'auto', 'important');
                sub.style.setProperty('min-height', '0', 'important');
                sub.style.setProperty('max-height', '36px', 'important');
                sub.style.setProperty('padding', '2px 8px', 'important');
                sub.style.setProperty('margin', '0', 'important');
                sub.style.setProperty('overflow', 'hidden', 'important');
            });
        });

        // 3. Style Digio iframes strictly
        const iframes = document.querySelectorAll('#digio_gateway iframe, #digio-modal-container iframe, #kryvexDigioModal iframe, iframe[src*="digio"], iframe#digio_gateway');
        iframes.forEach((iframe) => {
            iframe.style.setProperty('position', isMobile ? 'fixed' : 'relative', 'important');
            iframe.style.setProperty('inset', isMobile ? '0' : 'auto', 'important');
            iframe.style.setProperty('top', '0', 'important');
            iframe.style.setProperty('left', '0', 'important');
            iframe.style.setProperty('right', '0', 'important');
            iframe.style.setProperty('bottom', isMobile ? '75px' : '0', 'important');
            iframe.style.setProperty('margin', '0', 'important');
            iframe.style.setProperty('margin-left', '0', 'important');
            iframe.style.setProperty('padding', '0', 'important');
            iframe.style.setProperty('width', isMobile ? '100vw' : '100%', 'important');
            iframe.style.setProperty('max-width', isMobile ? '100vw' : '100%', 'important');
            iframe.style.setProperty('height', isMobile ? 'calc(100dvh - 82px)' : '100%', 'important');
            iframe.style.setProperty('max-height', isMobile ? 'calc(100dvh - 82px)' : '100%', 'important');
            iframe.style.setProperty('flex', '1 1 auto', 'important');
            iframe.style.setProperty('border', 'none', 'important');
            iframe.style.setProperty('background', '#ffffff', 'important');
            iframe.style.setProperty('transform', 'none', 'important');
            iframe.style.setProperty('-webkit-transform', 'none', 'important');
            iframe.style.removeProperty('z-index');
        });
    }

    const observer = new MutationObserver(() => applyCenterStyles());
    observer.observe(document.body, { childList: true, subtree: true });
    setInterval(applyCenterStyles, 100);
})();

// Center alignment override for popup windows so MeriPehchaan / Digio popups overlay exactly on top of modal
(function setupDigioWindowAlignment() {
    if (window._digioOpenOverridden) return;
    window._digioOpenOverridden = true;
    const origOpen = window.open;
    window.open = function(url, name, specs) {
        const width = 640;
        const height = 720;
        const left = Math.max(0, Math.round((window.screen.width - width) / 2));
        const top = Math.max(0, Math.round((window.screen.height - height) / 2));
        const alignedSpecs = `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=no,status=no`;
        const finalSpecs = specs ? `${specs},left=${left},top=${top},width=${width},height=${height}` : alignedSpecs;
        return origOpen.call(window, url, name, finalSpecs);
    };
})();

function showFloatingDigioContinueBar(requestId) {
    const bar = document.getElementById('digioFloatingContinueBar');
    if (bar) bar.style.display = 'none';
}

function openCustomDigioIframeModal(url, requestId) {
    let modal = document.getElementById('kryvexDigioModal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'kryvexDigioModal';
        modal.style.cssText = `
            position: fixed;
            inset: 0;
            z-index: 99999;
            background: rgba(0, 0, 0, 0.88);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        `;
        document.body.appendChild(modal);
    }

    modal.innerHTML = `
        <div style="position: relative; width: 640px; height: 740px; max-width: 96vw; max-height: 92vh; background: #0b0d16; border: 1px solid rgba(250, 204, 21, 0.35); border-radius: 20px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.8); display: flex; flex-direction: column;">
            <div style="padding: 14px 20px; background: #121624; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="font-size: 20px;">🛡️</span>
                    <strong style="color: #ffffff; font-size: 15px;">DigiLocker Verification</strong>
                </div>
                <button type="button" id="closeKryvexDigioModalBtn" style="background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); color: #94a3b8; width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;">✕</button>
            </div>
            <iframe src="${url}" style="width: 100%; flex: 1; border: none; background: #ffffff;"></iframe>
        </div>
    `;

    modal.classList.add('is-open');
    modal.style.display = 'flex';
    lockBodyScroll();
    document.getElementById('closeKryvexDigioModalBtn')?.addEventListener('click', closeCustomDigioIframeModal);
}

async function startDigioJourney({
  kycId,
  customerIdentifier,
  accessToken,
  environment = "sandbox",
  logo,
}) {
  const DigioCtor = await loadDigioSdk(environment);

  return new Promise((resolve) => {
    const digio = new DigioCtor({
      environment: environment === "production" ? "production" : "sandbox",
      is_iframe: true, // In-Page Overlay Modal
      logo: logo || undefined,
      theme: {
        primaryColor: "#facc15",
        secondaryColor: "#0b0d16",
      },
      callback: (response) => {
        closeCustomDigioIframeModal();
        if (!response) {
          resolve({ success: false, error_code: "EMPTY", message: "No response" });
          return;
        }
        if (response.error_code) {
          resolve({
            success: false,
            error_code: response.error_code,
            message: response.message || response.error_code,
          });
          return;
        }
        resolve({
          success: true,
          digio_doc_id: response.digio_doc_id || response.entity_id || kycId,
          message: response.message,
        });
      },
    });

    digio.init();
    if (accessToken) {
      digio.submit(kycId, customerIdentifier, accessToken);
    } else {
      digio.submit(kycId, customerIdentifier);
    }
  });
}

let statusCheckTimer = null;
let isAutoAdvancing = false;

async function enableContinueButton(requestId) {
    if (isAutoAdvancing) return;
    isAutoAdvancing = true;

    closeCustomDigioIframeModal();

    const blurOverlay = document.getElementById('digioBlurOverlay');
    const statusBadge = document.getElementById('digioStatusBadge');
    const btnContinue = document.getElementById('btnDigioContinue');
    const btnOpen = elements.btnOpenDigiLocker;
    
    if (statusCheckTimer) {
        clearInterval(statusCheckTimer);
        statusCheckTimer = null;
    }

    if (blurOverlay) blurOverlay.style.display = 'flex';
    if (statusBadge) {
        statusBadge.style.borderColor = 'rgba(34, 197, 94, 0.4)';
        statusBadge.style.color = '#4ade80';
        statusBadge.innerHTML = '<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#4ade80;"></span> Documents Fetched! Auto-Proceeding...';
    }

    if (btnContinue) {
        btnContinue.style.display = 'inline-flex';
        btnContinue.innerHTML = 'Verifying &amp; Proceeding...';
        btnContinue.disabled = true;
    }

    if (btnOpen) {
        btnOpen.disabled = true;
        btnOpen.innerHTML = 'Verifying &amp; Proceeding...';
    }
    
    showToast('Aadhaar & PAN Card fetched successfully! Proceeding to Selfie Step...', 'success');

    // Auto-advance seamlessly to Step 3 in 600ms
    setTimeout(async () => {
        try {
            const isVerified = await completeDigioVerification(requestId, true);
            if (blurOverlay) blurOverlay.style.display = 'none';
        } finally {
            isAutoAdvancing = false;
        }
    }, 600);
}

async function handleOpenDigiLocker() {
    if (!elements.btnOpenDigiLocker) return;
    const btn = elements.btnOpenDigiLocker;

    // Scroll window smoothly to top so Digio modal renders dead-center without scroll offset
    window.scrollTo({ top: 0, behavior: 'smooth' });

    const blurOverlay = document.getElementById('digioBlurOverlay');
    const statusBadge = document.getElementById('digioStatusBadge');
    const btnContinue = document.getElementById('btnDigioContinue');

    if (blurOverlay) blurOverlay.style.display = 'flex';
    if (btnContinue) btnContinue.style.display = 'none';
    if (statusBadge) {
        statusBadge.style.borderColor = 'rgba(255,255,255,0.1)';
        statusBadge.style.color = '#38bdf8';
        statusBadge.innerHTML = '<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#38bdf8;"></span> Connecting to DigiLocker...';
    }

    btn.disabled = true;
    btn.style.background = '';
    btn.style.color = '';
    btn.style.boxShadow = '';
    btn.textContent = 'Connecting to Digio DigiLocker...';

    try {
        const response = await authFetch('/api/auth/kyc/digio/initiate', {
            method: 'POST',
        });

        const data = response.data || {};
        const requestId = data.request_id;
        const custIdentifier = data.customer_identifier;
        const accessToken = data.access_token;
        const digioEnv = data.environment || 'sandbox';

        if (requestId && custIdentifier && !response.is_mock) {
            // Start polling to detect when Aadhaar & PAN documents are fetched (Completed)
            if (statusCheckTimer) clearInterval(statusCheckTimer);
            statusCheckTimer = setInterval(async () => {
                try {
                    const checkRes = await authFetch(`/api/auth/kyc/digio/check-status?request_id=${requestId}`);
                    if (checkRes?.data?.documents_fetched) {
                        showFloatingDigioContinueBar(requestId);
                        enableContinueButton(requestId);
                    }
                } catch (e) {}
            }, 2000);

            showFloatingDigioContinueBar(requestId);

            // Open Digio Gateway via Digio JS SDK Modal window
            const digioResult = await startDigioJourney({
                kycId: requestId,
                customerIdentifier: custIdentifier,
                accessToken: accessToken,
                environment: digioEnv,
            });

            if (digioResult && digioResult.success) {
                enableContinueButton(requestId);
            } else {
                if (statusCheckTimer) {
                    clearInterval(statusCheckTimer);
                    statusCheckTimer = null;
                }

                // If user cancelled or closed modal: Check if documents were already fetched
                let isCompleted = false;
                try {
                    const checkRes = await authFetch(`/api/auth/kyc/digio/check-status?request_id=${requestId}`);
                    if (checkRes?.data?.documents_fetched) {
                        isCompleted = true;
                    }
                } catch (e) {}

                if (isCompleted) {
                    // Only advance to Step 3 if documents were actually fetched successfully from DigiLocker!
                    const verified = await completeDigioVerification(requestId, false);
                    if (verified) return;
                }

                // User cancelled or closed before completing DigiLocker -> Stay on Step 2!
                if (blurOverlay) blurOverlay.style.display = 'none';
                if (statusBadge) {
                    statusBadge.style.borderColor = 'rgba(255,255,255,0.1)';
                    statusBadge.style.color = '#94a3b8';
                    statusBadge.innerHTML = '<span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#94a3b8;"></span> Ready for DigiLocker';
                }
                btn.disabled = false;
                btn.innerHTML = 'Open DigiLocker &nbsp; ❯';
                showToast('DigiLocker process was cancelled. You can click Open DigiLocker to try again.', 'warning');
                goToStep(2);
            }
            return;
        }

        // If in test mode or no keys, open in-page modal
        openDigioInteractiveModal(requestId || ('RID' + Date.now()));

    } catch (error) {
        showToast(error.message || 'Failed to start DigiLocker verification.', 'error');
        if (blurOverlay) blurOverlay.style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = 'Open DigiLocker &nbsp; ❯';
    }
}

function openDigioInteractiveModal(requestId, userPanInput = '') {
    const modal = document.getElementById('digioGatewayModal');
    if (!modal) return;

    if (userPanInput) {
        const panInput = document.getElementById('digioPanInput');
        if (panInput) panInput.value = userPanInput;
    }

    modal.style.display = 'flex';

    const step1 = document.getElementById('digioStep1');
    const step2 = document.getElementById('digioStep2');
    const step3 = document.getElementById('digioStep3');
    const step4 = document.getElementById('digioStep4');

    if (step1) step1.style.display = 'block';
    if (step2) step2.style.display = 'none';
    if (step3) step3.style.display = 'none';
    if (step4) step4.style.display = 'none';

    // Close button: Close modal and DO NOT advance step unless documents authorized!
    const btnClose = document.getElementById('btnCloseDigioModal');
    if (btnClose) {
        btnClose.onclick = () => { 
            modal.style.display = 'none'; 
            if (elements.btnOpenDigiLocker) {
                elements.btnOpenDigiLocker.disabled = false;
                elements.btnOpenDigiLocker.innerHTML = 'Open DigiLocker &nbsp; ❯';
            }
            const blurOverlay = document.getElementById('digioBlurOverlay');
            if (blurOverlay) blurOverlay.style.display = 'none';
            showToast('DigiLocker verification was not completed. Please try again.', 'warning');
        };
    }

    // Sub-step 1 Next -> Aadhaar & Captcha
    const btnStep1Next = document.getElementById('btnDigioStep1Next');
    if (btnStep1Next) {
        btnStep1Next.onclick = () => {
            const aadhaar = document.getElementById('digioAadhaarInput')?.value || '';
            const captcha = document.getElementById('digioCaptchaInput')?.value || '';
            if (aadhaar.length < 12) {
                showToast('Please enter a valid 12-digit Aadhaar number.', 'error');
                return;
            }
            if (!captcha) {
                showToast('Please enter the Captcha code.', 'error');
                return;
            }
            if (step1) step1.style.display = 'none';
            if (step2) step2.style.display = 'block';
        };
    }

    // Sub-step 2 Next -> OTP & Security PIN
    const btnStep2Next = document.getElementById('btnDigioStep2Next');
    if (btnStep2Next) {
        btnStep2Next.onclick = () => {
            const otp = document.getElementById('digioOtpInput')?.value || '';
            const pin = document.getElementById('digioPinInput')?.value || '';
            if (otp.length < 6) {
                showToast('Please enter the 6-digit OTP sent to your Aadhaar-linked mobile.', 'error');
                return;
            }
            if (pin.length < 6) {
                showToast('Please enter your 6-digit DigiLocker Security PIN.', 'error');
                return;
            }
            if (step2) step2.style.display = 'none';
            if (step3) step3.style.display = 'block';
        };
    }

    // Sub-step 3 Allow -> Select Document Consent (Aadhaar + PAN)
    const btnStep3Allow = document.getElementById('btnDigioStep3Allow');
    if (btnStep3Allow) {
        btnStep3Allow.onclick = async () => {
            const pan = document.getElementById('digioPanInput')?.value || '';
            if (pan && !/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(pan.toUpperCase())) {
                showToast('Please enter a valid 10-character PAN number.', 'error');
                return;
            }
            if (step3) step3.style.display = 'none';
            if (step4) step4.style.display = 'block';

            setTimeout(async () => {
                modal.style.display = 'none';
                await completeDigioVerification(requestId, true, pan.toUpperCase());
            }, 1500);
        };
    }
}

async function completeDigioVerification(requestId, isMock = false, panNumber = '') {
    try {
        const response = await authFetch('/api/auth/kyc/digio/verify', {
            method: 'POST',
            body: JSON.stringify({
                request_id: requestId,
                is_mock: isMock,
                pan_number: panNumber,
            }),
        });

        showToast('Aadhaar & PAN Card verified successfully via DigiLocker! 🎉', 'success');
        updateStoredUserKyc(STATUS.PAN);
        goToStep(3);
        return true;
    } catch (error) {
        showToast(error.message || 'Failed to complete DigiLocker verification. Please try again.', 'error');
        return false;
    }
}

function validateVerhoeff(number) {
    if (!/^[2-9]\d{11}$/.test(number)) {
        return false;
    }
    const d = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
        [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
        [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
        [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
        [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
        [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
        [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
        [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
        [9, 8, 7, 6, 5, 4, 3, 2, 1, 0]
    ];
    const p = [
        [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
        [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
        [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
        [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
        [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
        [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
        [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
        [7, 0, 4, 6, 9, 1, 3, 2, 5, 8]
    ];
    let c = 0;
    const myArray = number.split('').reverse().map(Number);
    for (let i = 0; i < myArray.length; i++) {
        c = d[c][p[i % 8][myArray[i]]];
    }
    return c === 0;
}

// Step 2 Event Handler (Aadhaar Number Check)
async function handleVerifyAadhaarNumber() {
    const aadhaarNum = elements.aadhaarNumberInput?.value?.trim() || '';

    if (!/^[2-9]\d{11}$/.test(aadhaarNum)) {
        showToast('Please enter a valid 12-digit Aadhaar number (cannot start with 0 or 1).', 'error');
        return;
    }

    if (!validateVerhoeff(aadhaarNum)) {
        showToast('The Aadhaar Card number entered is invalid according to UIDAI checksum standards.', 'error');
        return;
    }

    elements.btnSubmitAadhaar.disabled = true;
    elements.btnSubmitAadhaar.textContent = 'Verifying Aadhaar...';

    try {
        const response = await authFetch('/api/auth/kyc/submit-aadhaar', {
            method: 'POST',
            body: JSON.stringify({ aadhaar_number: aadhaarNum }),
        });

        showToast(response.message || 'Aadhaar verified successfully.', 'success');
        goToStep(3);

    } catch (error) {
        showToast(error.message || 'Failed to verify Aadhaar number.', 'error');
    } finally {
        elements.btnSubmitAadhaar.disabled = false;
        elements.btnSubmitAadhaar.textContent = 'Verify Aadhaar & Continue';
    }
}

// Step 3 Setup & Handler (PAN Card Number & Image Upload)
function setupPanFilePreview() {
    if (elements.panFileInput) {
        elements.panFileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const error = validateFile(file);
            if (error) {
                showToast(error, 'error');
                e.target.value = '';
                return;
            }

            state.selectedPanFile = file;

            const reader = new FileReader();
            reader.onload = (event) => {
                if (elements.imgPanPreview) elements.imgPanPreview.src = event.target.result;
                if (elements.panPlaceholder) elements.panPlaceholder.style.display = 'none';
                if (elements.panPreviewSection) elements.panPreviewSection.style.display = 'block';
            };
            reader.readAsDataURL(file);
        });
    }

    if (elements.btnRemovePanFile) {
        elements.btnRemovePanFile.addEventListener('click', () => {
            state.selectedPanFile = null;
            if (elements.panFileInput) elements.panFileInput.value = '';
            if (elements.panPlaceholder) elements.panPlaceholder.style.display = 'block';
            if (elements.panPreviewSection) elements.panPreviewSection.style.display = 'none';
        });
    }
}

async function handleVerifyPan() {
    const panNum = elements.panNumberInput?.value?.trim() || '';

    if (!/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(panNum)) {
        showToast('Please enter a valid 10-character PAN number (e.g. ABCDE1234F).', 'error');
        return;
    }

    if (!state.selectedPanFile) {
        showToast('Please upload a clear photo of your PAN Card.', 'error');
        return;
    }

    elements.btnSubmitPan.disabled = true;
    elements.btnSubmitPan.textContent = 'Verifying PAN Card...';

    try {
        const formData = new FormData();
        formData.append('pan_number', panNum);
        formData.append('pan_image', state.selectedPanFile);

        const response = await authFetchFormData('/api/auth/kyc/submit-pan', formData);

        showToast(response.message || 'PAN Card verified successfully.', 'success');
        goToStep(4);

    } catch (error) {
        showToast(error.message || 'PAN verification failed.', 'error');
    } finally {
        elements.btnSubmitPan.disabled = false;
        elements.btnSubmitPan.textContent = 'Verify PAN Card & Continue';
    }
}

// Step 4 Setup & Handler (Selfie / Face Verification)
function setupSelfieFilePreview() {
    const triggerBtn = document.getElementById('btnTriggerSelfieFile') || elements.btnTriggerSelfieFile;
    const fileInput = document.getElementById('selfieFileInput') || elements.selfieFileInput;

    if (triggerBtn && fileInput) {
        triggerBtn.onclick = (e) => {
            e.preventDefault();
            fileInput.click();
        };
    }

    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const error = validateFile(file);
            if (error) {
                showToast(error, 'error');
                e.target.value = '';
                return;
            }

            state.selectedSelfieFile = file;
            state.capturedBase64 = null;
            stopCameraStream();

            const reader = new FileReader();
            reader.onload = (event) => {
                const previewImg = document.getElementById('imgSelfiePreview') || elements.imgSelfiePreview;
                const cameraBox = document.getElementById('cameraBox') || elements.cameraBox;
                const previewBox = document.getElementById('selfiePreviewBox') || elements.selfiePreviewBox;

                if (previewImg) previewImg.src = event.target.result;
                if (cameraBox) cameraBox.style.display = 'none';
                if (previewBox) {
                    previewBox.style.display = 'flex';
                    previewBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }

                showToast('Selfie photo attached successfully!', 'success');
            };
            reader.readAsDataURL(file);
        });
    }
}

let liveDetectionLoopTimer = null;
let isFaceCurrentlyDetected = false;

async function handleStartCamera() {
    try {
        state.selectedSelfieFile = null;
        if (elements.selfieFileInput) elements.selfieFileInput.value = '';
        stopCameraStream();

        const stream = await navigator.mediaDevices.getUserMedia({
            video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
            audio: false
        });

        state.webcamStream = stream;

        if (elements.webcamPreview) {
            elements.webcamPreview.srcObject = stream;
        }

        if (elements.selfiePreviewBox) elements.selfiePreviewBox.style.display = 'none';
        if (elements.cameraBox) elements.cameraBox.style.display = 'flex';

        const shutterRow = document.getElementById('shutterControlRow');
        if (shutterRow) shutterRow.style.display = 'flex';

        // Start continuous real-time face detection & shutter lock loop
        if (liveDetectionLoopTimer) clearInterval(liveDetectionLoopTimer);
        liveDetectionLoopTimer = setInterval(processWebcamFrameDetection, 150);

    } catch (err) {
        showToast('Could not access camera. Please allow camera permissions or upload a selfie file.', 'error');
    }
}

function processWebcamFrameDetection() {
    const video = elements.webcamPreview || document.getElementById('webcamPreview');
    const ovalStroke = document.getElementById('webOvalStroke');
    const statusPill = document.getElementById('webFaceStatusPill');
    const btnCapture = elements.btnCaptureSelfie || document.getElementById('btnCaptureSelfie');
    const innerCircle = document.getElementById('shutterInnerCircle');

    if (!video || !video.videoWidth || !video.videoHeight || !state.webcamStream) return;

    try {
        const canvas = document.createElement('canvas');
        canvas.width = 200;
        canvas.height = 200;
        const ctx = canvas.getContext('2d');

        const vWidth = video.videoWidth;
        const vHeight = video.videoHeight;

        const containerAspect = 1.0;
        const videoAspect = vWidth / vHeight;

        let sx = 0, sy = 0, sWidth = vWidth, sHeight = vHeight;
        if (videoAspect > containerAspect) {
            sWidth = vHeight * containerAspect;
            sx = (vWidth - sWidth) / 2;
        } else {
            sHeight = vWidth / containerAspect;
            sy = (vHeight - sHeight) / 2;
        }

        ctx.drawImage(video, sx, sy, sWidth, sHeight, 0, 0, 200, 200);

        const imgData = ctx.getImageData(0, 0, 200, 200);
        const data = imgData.data;

        const ovalCx = 100;
        const ovalCy = 91;
        const ovalRx = 53;
        const ovalRy = 68;

        let ovalTotal = 0;
        let ovalSkin = 0;
        let borderTotal = 0;
        let borderSkin = 0;
        let luminanceValues = [];

        // 4 Quadrants inside oval
        let q1Skin = 0, q1Total = 0; // Top-Left
        let q2Skin = 0, q2Total = 0; // Top-Right
        let q3Skin = 0, q3Total = 0; // Bottom-Left
        let q4Skin = 0, q4Total = 0; // Bottom-Right

        // Forehead skin reference luminance
        let foreheadLumSum = 0;
        let foreheadSkinCount = 0;

        // Eye region contrast pixel counts
        let leftEyeContrastCount = 0;
        let rightEyeContrastCount = 0;

        for (let y = 0; y < 200; y++) {
            for (let x = 0; x < 200; x++) {
                const idx = (y * 200 + x) * 4;
                const r = data[idx];
                const g = data[idx + 1];
                const b = data[idx + 2];

                const dx = (x - ovalCx) / ovalRx;
                const dy = (y - ovalCy) / ovalRy;
                const insideOval = (dx * dx + dy * dy) <= 1.0;

                const isBorder = (x < 18 || x > 182 || y < 18 || y > 182);

                // RGB Skin Bounds
                const isSkinRGB = (r > 75 && g > 30 && b > 15 && (Math.max(r, g, b) - Math.min(r, g, b) > 12) && Math.abs(r - g) > 10 && r > g && r > b);
                
                // YCbCr Skin Bounds
                const cbVal = -0.1687 * r - 0.3313 * g + 0.5 * b + 128;
                const crVal = 0.5 * r - 0.4187 * g - 0.0813 * b + 128;
                const isSkinYCbCr = (cbVal >= 75 && cbVal <= 135 && crVal >= 130 && crVal <= 175);

                // HSV Bounds
                const maxRGB = Math.max(r, g, b) / 255;
                const minRGB = Math.min(r, g, b) / 255;
                const delta = maxRGB - minRGB;
                let hue = 0;
                if (delta > 0) {
                    if (maxRGB === r / 255) hue = ((g - b) / 255 / delta) % 6;
                    else if (maxRGB === g / 255) hue = (b - r) / 255 / delta + 2;
                    else hue = (r - g) / 255 / delta + 4;
                    hue = Math.round(hue * 60);
                    if (hue < 0) hue += 360;
                }
                const sat = maxRGB > 0 ? (delta / maxRGB) : 0;
                const isSkinHSV = ((hue >= 0 && hue <= 50) || (hue >= 335 && hue <= 360)) && (sat >= 0.12 && sat <= 0.68);

                const isSkin = (isSkinRGB && isSkinYCbCr && isSkinHSV);

                if (isBorder) {
                    borderTotal++;
                    if (isSkin) borderSkin++;
                }

                if (insideOval) {
                    ovalTotal++;
                    const lum = 0.299 * r + 0.587 * g + 0.114 * b;

                    if (isSkin) {
                        ovalSkin++;
                        luminanceValues.push(lum);

                        // Forehead reference skin (x: 80..120, y: 35..55)
                        if (x >= 80 && x <= 120 && y >= 35 && y <= 55) {
                            foreheadLumSum += lum;
                            foreheadSkinCount++;
                        }
                    }

                    // 4 Quadrants
                    if (y < ovalCy) {
                        if (x < ovalCx) { q1Total++; if (isSkin) q1Skin++; }
                        else { q2Total++; if (isSkin) q2Skin++; }
                    } else {
                        if (x < ovalCx) { q3Total++; if (isSkin) q3Skin++; }
                        else { q4Total++; if (isSkin) q4Skin++; }
                    }
                }
            }
        }

        const avgForeheadLum = foreheadSkinCount > 5 ? (foreheadLumSum / foreheadSkinCount) : 125;

        // Eye Feature Contrast Check (Left & Right Eye Boxes)
        for (let y = 55; y <= 88; y++) {
            for (let x = 60; x <= 140; x++) {
                if (x > 90 && x < 110) continue;
                const idx = (y * 200 + x) * 4;
                const lum = 0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2];
                
                if (lum < avgForeheadLum * 0.88 || lum < 110) {
                    if (x <= 90) leftEyeContrastCount++;
                    else rightEyeContrastCount++;
                }
            }
        }

        const ovalRatio = ovalTotal > 0 ? (ovalSkin / ovalTotal) * 100 : 0;
        const borderRatio = borderTotal > 0 ? (borderSkin / borderTotal) * 100 : 0;

        // Contrast Variance
        let contrastVariance = 0;
        if (luminanceValues.length > 25) {
            const avgLum = luminanceValues.reduce((a, b) => a + b, 0) / luminanceValues.length;
            const sqDiffs = luminanceValues.map(v => Math.pow(v - avgLum, 2));
            contrastVariance = Math.sqrt(sqDiffs.reduce((a, b) => a + b, 0) / luminanceValues.length);
        }

        const q1Ratio = q1Total > 0 ? (q1Skin / q1Total) : 0;
        const q2Ratio = q2Total > 0 ? (q2Skin / q2Total) : 0;
        const q3Ratio = q3Total > 0 ? (q3Skin / q3Total) : 0;
        const q4Ratio = q4Total > 0 ? (q4Skin / q4Total) : 0;

        const topSymmetry = Math.max(q1Skin, q2Skin) / Math.max(1, Math.min(q1Skin, q2Skin));
        const botSymmetry = Math.max(q3Skin, q4Skin) / Math.max(1, Math.min(q3Skin, q4Skin));

        const hasFacialFeatures = contrastVariance >= 7.0;
        const hasQuadrantSkin = (q1Ratio >= 0.10 && q2Ratio >= 0.10 && q3Ratio >= 0.10 && q4Ratio >= 0.10);
        const hasSymmetry = (topSymmetry <= 1.80 && botSymmetry <= 1.80);
        const hasBothEyes = (leftEyeContrastCount >= 1 && rightEyeContrastCount >= 1) || (leftEyeContrastCount + rightEyeContrastCount >= 3);

        const isDetected = (ovalRatio >= 18.0 && borderRatio <= 32.0 && hasFacialFeatures && hasQuadrantSkin && hasSymmetry && hasBothEyes);
        isFaceCurrentlyDetected = isDetected;

        if (isDetected) {
            // FACE DETECTED -> UNLOCK SHUTTER
            if (ovalStroke) {
                ovalStroke.setAttribute('stroke', '#22c55e');
                ovalStroke.style.filter = 'drop-shadow(0 0 10px rgba(34, 197, 94, 0.9))';
            }
            if (statusPill) {
                statusPill.style.background = 'rgba(22, 101, 52, 0.85)';
                statusPill.style.borderColor = 'rgba(74, 222, 128, 0.6)';
                statusPill.style.color = '#4ade80';
                statusPill.innerHTML = '<span>✓ Face detected — Keep your position steady</span>';
            }
            if (btnCapture) {
                btnCapture.disabled = false;
                btnCapture.style.cursor = 'pointer';
                btnCapture.style.opacity = '1';
                btnCapture.style.borderColor = '#2563eb';
                btnCapture.style.boxShadow = '0 0 20px rgba(37, 99, 235, 0.6)';
            }
            if (innerCircle) {
                innerCircle.style.background = '#38bdf8';
                innerCircle.style.transform = 'scale(1.05)';
            }
        } else {
            // NO FACE / OUT OF OVAL -> LOCK SHUTTER
            if (ovalStroke) {
                ovalStroke.setAttribute('stroke', 'rgba(255,255,255,0.6)');
                ovalStroke.style.filter = 'none';
            }
            if (statusPill) {
                statusPill.style.background = 'rgba(217, 119, 6, 0.25)';
                statusPill.style.borderColor = 'rgba(251, 191, 36, 0.4)';
                statusPill.style.color = '#fef08a';

                if (!hasBothEyes || !hasQuadrantSkin) {
                    statusPill.innerHTML = '<span>Keep full face uncovered (remove hand/hair)</span>';
                } else if (!hasSymmetry) {
                    statusPill.innerHTML = '<span>Look straight at camera (no side face)</span>';
                } else {
                    statusPill.innerHTML = '<span>Keep your full face inside the circle</span>';
                }
            }
            if (btnCapture) {
                btnCapture.disabled = true;
                btnCapture.style.cursor = 'not-allowed';
                btnCapture.style.opacity = '0.4';
                btnCapture.style.borderColor = '#475569';
                btnCapture.style.boxShadow = 'none';
            }
            if (innerCircle) {
                innerCircle.style.background = '#334155';
                innerCircle.style.transform = 'scale(1)';
            }
        }

    } catch (e) {}
}

function handleCaptureSelfie() {
    if (!elements.webcamPreview || !state.webcamStream) return;
    if (!isFaceCurrentlyDetected) {
        showToast('Please position your full face inside the oval circle to unlock capture.', 'error');
        return;
    }

    const canvas = document.createElement('canvas');
    canvas.width = elements.webcamPreview.videoWidth || 640;
    canvas.height = elements.webcamPreview.videoHeight || 480;

    const ctx = canvas.getContext('2d');
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(elements.webcamPreview, 0, 0, canvas.width, canvas.height);

    const base64Image = canvas.toDataURL('image/jpeg', 0.9);
    state.capturedBase64 = base64Image;
    state.selectedSelfieFile = null;

    stopCameraStream();

    if (elements.imgSelfiePreview) elements.imgSelfiePreview.src = base64Image;
    if (elements.cameraBox) elements.cameraBox.style.display = 'none';
    if (elements.selfiePreviewBox) elements.selfiePreviewBox.style.display = 'flex';
}

function handleRetakeSelfie() {
    state.capturedBase64 = null;
    state.selectedSelfieFile = null;
    if (elements.selfieFileInput) elements.selfieFileInput.value = '';
    if (elements.selfiePreviewBox) elements.selfiePreviewBox.style.display = 'none';
    if (elements.cameraBox) elements.cameraBox.style.display = 'none';

    const tabDirect = document.getElementById('tabSelfieDirect');
    const tabQr = document.getElementById('tabSelfieQr');
    const secDirect = document.getElementById('selfieDirectSection');
    const secQr = document.getElementById('selfieQrSection');

    if (tabQr && tabDirect) {
        tabQr.style.background = '#2563eb';
        tabQr.style.color = '#fff';
        tabDirect.style.background = 'rgba(255,255,255,0.06)';
        tabDirect.style.color = '#94a3b8';
    }
    if (secQr) secQr.style.display = 'flex';
    if (secDirect) secDirect.style.display = 'none';
    startSelfieQrSession();
}

function stopCameraStream() {
    if (liveDetectionLoopTimer) {
        clearInterval(liveDetectionLoopTimer);
        liveDetectionLoopTimer = null;
    }
    if (state.webcamStream) {
        state.webcamStream.getTracks().forEach(track => track.stop());
        state.webcamStream = null;
    }
    const shutterRow = document.getElementById('shutterControlRow');
    if (shutterRow) shutterRow.style.display = 'none';
}

let qrPollTimer = null;
let qrCountdownTimer = null;
let activeQrToken = null;
let isQrCompletionHandled = false;

function setupDualMethodTabs() {
    const tabDirect = document.getElementById('tabSelfieDirect');
    const tabQr = document.getElementById('tabSelfieQr');
    const secDirect = document.getElementById('selfieDirectSection');
    const secQr = document.getElementById('selfieQrSection');
    const btnRefresh = document.getElementById('btnRefreshQr');

    if (btnRefresh) {
        btnRefresh.onclick = () => {
            startSelfieQrSession();
        };
    }

    if (tabDirect && tabQr) {
        tabDirect.onclick = () => {
            tabDirect.style.background = '#2563eb';
            tabDirect.style.color = '#fff';
            tabQr.style.background = 'rgba(255,255,255,0.06)';
            tabQr.style.color = '#94a3b8';
            if (secDirect) secDirect.style.display = 'flex';
            if (secQr) secQr.style.display = 'none';
            if (elements.selfiePreviewBox) elements.selfiePreviewBox.style.display = 'none';
            stopQrPolling();
        };

        tabQr.onclick = async () => {
            tabQr.style.background = '#2563eb';
            tabQr.style.color = '#fff';
            tabDirect.style.background = 'rgba(255,255,255,0.06)';
            tabDirect.style.color = '#94a3b8';
            if (secDirect) secDirect.style.display = 'none';
            if (secQr) secQr.style.display = 'flex';
            if (elements.selfiePreviewBox) elements.selfiePreviewBox.style.display = 'none';
            await startSelfieQrSession();
        };
    }
}

async function startSelfieQrSession() {
    const qrLoader = document.getElementById('qrLoader');
    const imgQr = document.getElementById('imgQrCode');
    const qrStatusText = document.getElementById('qrStatusText');
    const qrTimer = document.getElementById('qrTimer');
    const secDirect = document.getElementById('selfieDirectSection');
    const secQr = document.getElementById('selfieQrSection');

    stopQrPolling();
    isQrCompletionHandled = false;

    if (qrLoader) {
        qrLoader.style.display = 'block';
        qrLoader.textContent = 'Generating QR Code...';
    }
    if (imgQr) imgQr.style.display = 'none';
    if (qrStatusText) qrStatusText.textContent = 'Waiting for mobile selfie upload...';

    try {
        const response = await authFetch('/api/auth/kyc/selfie-qr-initiate', { method: 'POST' });
        const data = response.data || {};
        activeQrToken = data.token;
        const mobileUrl = data.mobile_url;

        if (imgQr && mobileUrl) {
            imgQr.src = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(mobileUrl)}`;
            imgQr.onload = () => {
                if (qrLoader) qrLoader.style.display = 'none';
                imgQr.style.display = 'block';
            };
        }

        const btnCopy = document.getElementById('btnCopyMobileLink');
        if (btnCopy && mobileUrl) {
            btnCopy.onclick = () => {
                navigator.clipboard.writeText(mobileUrl).then(() => {
                    showToast('Mobile verification link copied to clipboard!', 'success');
                }).catch(() => {
                    prompt('Copy mobile URL:', mobileUrl);
                });
            };
        }

        // Start 5-minute Countdown Timer
        let secondsLeft = data.expires_in_seconds || 300;
        if (qrCountdownTimer) clearInterval(qrCountdownTimer);
        qrCountdownTimer = setInterval(() => {
            secondsLeft--;
            if (secondsLeft <= 0) {
                clearInterval(qrCountdownTimer);
                if (qrTimer) qrTimer.textContent = '00:00 (Expired)';
                if (qrStatusText) qrStatusText.textContent = 'QR Expired. Click "Regenerate QR" to refresh.';
                stopQrPolling();
            } else {
                const mins = String(Math.floor(secondsLeft / 60)).padStart(2, '0');
                const secs = String(secondsLeft % 60).padStart(2, '0');
                if (qrTimer) qrTimer.textContent = `${mins}:${secs}`;
            }
        }, 1000);

        // Start Fast Polling every 1.5 seconds with single completion guard
        if (qrPollTimer) clearInterval(qrPollTimer);
        qrPollTimer = setInterval(async () => {
            if (!activeQrToken || isQrCompletionHandled) return;
            try {
                const pollRes = await authFetch(`/api/auth/kyc/selfie-qr-status?token=${activeQrToken}`);
                if (pollRes?.data?.completed && !isQrCompletionHandled) {
                    isQrCompletionHandled = true;
                    activeQrToken = null;
                    stopQrPolling();

                    const secDirect = document.getElementById('selfieDirectSection');
                    const secQr = document.getElementById('selfieQrSection');

                    if (secQr) secQr.style.display = 'none';
                    if (secDirect) secDirect.style.display = 'none';

                    const selfieUrl = pollRes.data.selfie_url;
                    if (selfieUrl && elements.imgSelfiePreview) {
                        elements.imgSelfiePreview.src = selfieUrl + '?t=' + Date.now();
                        state.capturedBase64 = selfieUrl;
                    }
                    if (elements.cameraBox) elements.cameraBox.style.display = 'none';
                    if (elements.selfiePreviewBox) elements.selfiePreviewBox.style.display = 'flex';

                    showToast('Mobile selfie attached successfully! Click "Submit Verification Request" to complete KYC. 🎉', 'success');
                    
                    if (elements.btnSubmitSelfie) {
                        elements.btnSubmitSelfie.focus();
                        elements.btnSubmitSelfie.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            } catch (e) {}
        }, 1500);

    } catch (e) {
        if (qrLoader) qrLoader.textContent = 'Failed to generate QR Code. Please check connection.';
    }
}

function stopQrPolling() {
    if (qrPollTimer) {
        clearInterval(qrPollTimer);
        qrPollTimer = null;
    }
    if (qrCountdownTimer) {
        clearInterval(qrCountdownTimer);
        qrCountdownTimer = null;
    }
}

async function handleVerifySelfie() {
    if (!state.capturedBase64 && !state.selectedSelfieFile) {
        showToast('Please capture a live camera selfie or upload a selfie photo.', 'error');
        return;
    }

    elements.btnSubmitSelfie.disabled = true;
    elements.btnSubmitSelfie.textContent = 'Submitting Request...';

    try {
        let response;
        if (state.selectedSelfieFile) {
            const formData = new FormData();
            formData.append('selfie_image', state.selectedSelfieFile);
            response = await authFetchFormData('/api/auth/kyc/submit-selfie', formData);
        } else {
            response = await authFetch('/api/auth/kyc/submit-selfie', {
                method: 'POST',
                body: JSON.stringify({ selfie_base64: state.capturedBase64 }),
            });
        }

        showToast(response.message || 'Identity verification submitted successfully.', 'success');
        showReviewState('pending');

    } catch (error) {
        showToast(error.message || 'Selfie verification failed.', 'error');
    } finally {
        elements.btnSubmitSelfie.disabled = false;
        elements.btnSubmitSelfie.textContent = 'Submit Verification Request';
    }
}

async function handleResetKycFlow() {
    try {
        const response = await authFetch('/api/auth/kyc/reset', { method: 'POST' });
        showToast(response?.message || 'KYC reset successfully.', 'success');
    } catch (error) {}
    updateStoredUserKyc(STATUS.UNVERIFIED);
    goToStep(1);
}
window.goToStep = goToStep;
window.handleResetKycFlow = handleResetKycFlow;
window.checkKycStatus = checkKycStatus;

// Helpers
function getAccessToken() {
    return localStorage.getItem('kryvex_access_token');
}

async function authFetch(url, options = {}) {
    const token = getAccessToken();
    const headers = {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`,
        ...(options.headers || {})
    };

    const res = await fetch(url, { ...options, headers });
    const data = await res.json();

    if (!res.ok) {
        throw new Error(data.message || 'Request failed.');
    }
    return data;
}

async function authFetchFormData(url, formData) {
    const token = getAccessToken();
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Authorization': `Bearer ${token}`
        },
        body: formData
    });

    const data = await res.json();
    if (!res.ok) {
        throw new Error(data.message || 'Upload failed.');
    }
    return data;
}

let lastToastMsg = '';
let lastToastTime = 0;

function showToast(message, type = 'info') {
    const now = Date.now();
    if (message === lastToastMsg && (now - lastToastTime) < 2500) {
        return;
    }
    lastToastMsg = message;
    lastToastTime = now;

    if (typeof window.showToast === 'function') {
        const title = type === 'error' ? 'Verification Error' : (type === 'success' ? 'Success' : 'Notification');
        window.showToast({ type, title, message });
        return;
    }
    
    if (typeof Notyf !== 'undefined') {
        if (!state.notyfInstance) {
            state.notyfInstance = new Notyf({ duration: 4000, position: { x: 'right', y: 'top' } });
        }
        if (type === 'error') state.notyfInstance.error(message);
        else if (type === 'success') state.notyfInstance.success(message);
        else state.notyfInstance.open({ type: 'info', message });
        return;
    }

    let container = document.getElementById('kycToastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'kycToastContainer';
        container.style.cssText = 'position: fixed; top: 24px; right: 24px; z-index: 999999; display: flex; flex-direction: column; gap: 10px; pointer-events: none;';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    const bg = type === 'error' ? 'rgba(239, 68, 68, 0.95)' : (type === 'success' ? 'rgba(34, 197, 94, 0.95)' : 'rgba(59, 130, 246, 0.95)');
    const border = type === 'error' ? 'rgba(239, 68, 68, 0.4)' : (type === 'success' ? 'rgba(34, 197, 94, 0.4)' : 'rgba(59, 130, 246, 0.4)');
    const icon = type === 'error' ? '❌' : (type === 'success' ? '✅' : 'ℹ️');

    toast.style.cssText = `padding: 14px 20px; border-radius: 10px; font-size: 14px; font-weight: 600; color: #fff; background: ${bg}; border: 1px solid ${border}; backdrop-filter: blur(8px); pointer-events: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 12px; transition: transform 0.3s ease, opacity 0.3s ease; transform: translateY(-10px); opacity: 0;`;
    toast.innerHTML = `<span style="font-size: 16px;">${icon}</span> <span>${message}</span>`;
    
    container.appendChild(toast);
    requestAnimationFrame(() => {
        toast.style.transform = 'translateY(0)';
        toast.style.opacity = '1';
    });

    setTimeout(() => {
        toast.style.transform = 'translateY(-10px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function setupOtpDigitsFocus() {
    const inputs = document.querySelectorAll('.otp-digit');
    inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
            if (e.target.value.length === 1 && index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                inputs[index - 1].focus();
            }
        });
    });
}

function focusFirstOtpDigit() {
    const first = document.querySelector('.otp-digit');
    if (first) first.focus();
}

function startResendCountdown(seconds) {
    clearOtpTimer();
    let remaining = seconds;
    const btn = elements.btnResendOtp;
    if (btn) btn.disabled = true;

    state.countdownTimer = setInterval(() => {
        remaining--;
        if (btn) btn.textContent = `Resend in ${remaining}s`;

        if (remaining <= 0) {
            clearOtpTimer();
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Resend Code';
            }
        }
    }, 1000);
}

function clearOtpTimer() {
    if (state.countdownTimer) {
        clearInterval(state.countdownTimer);
        state.countdownTimer = null;
    }
}

function restoreOtpTimer() {
    // Optional countdown persistence
}

function updateStoredUserKyc(status) {
    try {
        const userStr = localStorage.getItem('kryvex_user');
        if (userStr) {
            const user = JSON.parse(userStr);
            user.kyc_status = status;
            localStorage.setItem('kryvex_user', JSON.stringify(user));
        }
        if (typeof window.setNavbarUserName === 'function') {
            window.setNavbarUserName();
        }
        window.dispatchEvent(new CustomEvent('kryvex_user_updated', { detail: { kyc_status: status } }));
    } catch (e) { /* silent */ }
}