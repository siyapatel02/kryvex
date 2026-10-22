@extends('layouts.app')

@section('title', 'KYC Verification - Kryvex')

@push('scripts')
    <script src="https://app.digio.in/sdk/v1.0/digio.js"></script>
    @vite('resources/js/pages/kyc.js')
@endpush

@section('content')
<section class="kyc-page">
    <div class="kyc-shell">
        
        <!-- 3-STEP WIZARD PROGRESS INDICATOR -->
        <div class="kyc-steps-indicator" id="kycStepsIndicator">
            <div class="step-progress-bg-line"></div>
            <div id="stepProgressLine" class="step-progress-active-line"></div>
            
            <div class="step-node active" id="node1">
                <span class="step-num" id="num1">1</span>
                <span class="step-lbl" id="lbl1">Mobile</span>
            </div>
            
            <div class="step-node" id="node2">
                <span class="step-num" id="num2">2</span>
                <span class="step-lbl" id="lbl2">DigiLocker</span>
            </div>

            <div class="step-node" id="node3">
                <span class="step-num" id="num3">3</span>
                <span class="step-lbl" id="lbl3">Selfie</span>
            </div>
        </div>

        <!-- MAIN WIZARD CARD -->
        <div class="kv-card kyc-card">
            
            <!-- STEP 1: MOBILE VERIFICATION -->
            <div id="step1Container">
                <h2 class="kyc-card-title">Step 1: Mobile Phone Verification</h2>
                <p class="kyc-card-desc">
                    Verify your phone number with an SMS OTP. You can enter any active mobile number with country code.
                </p>

                <!-- Send OTP Form -->
                <div id="phoneInputSection">
                    <div class="kyc-field-group">
                        <label class="kyc-field-label">Mobile Number</label>
                        <div class="kyc-phone-row">
                            <select id="countryCode" class="kyc-country-select">
                                <option value="+91">+91 (IN)</option>
                                <option value="+1">+1 (US)</option>
                                <option value="+44">+44 (UK)</option>
                                <option value="+971">+971 (AE)</option>
                                <option value="+65">+65 (SG)</option>
                            </select>
                            <input type="tel" id="mobileNumber" class="kyc-mobile-input" placeholder="Enter local mobile number">
                        </div>
                    </div>
                    
                    <button id="btnSendOtp" class="kv-btn kv-btn-primary kyc-action-btn">
                        Send Verification Code
                    </button>
                </div>

                <!-- Verify OTP Form -->
                <div id="otpInputSection" style="display: none;">
                    <div class="otp-target-bar">
                        <span id="targetPhoneLabel" class="otp-target-phone">+91 XXXXX XXXXX</span>
                        <button id="btnEditPhone" type="button" class="otp-edit-phone-btn">Change</button>
                    </div>

                    <div class="otp-center-box">
                        <label class="kyc-field-label">Enter 6-Digit OTP</label>
                        
                        <div class="otp-inputs-wrapper">
                            <input type="text" class="otp-digit" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="one-time-code">
                            <input type="text" class="otp-digit" maxlength="1" pattern="\d" inputmode="numeric">
                            <input type="text" class="otp-digit" maxlength="1" pattern="\d" inputmode="numeric">
                            <input type="text" class="otp-digit" maxlength="1" pattern="\d" inputmode="numeric">
                            <input type="text" class="otp-digit" maxlength="1" pattern="\d" inputmode="numeric">
                            <input type="text" class="otp-digit" maxlength="1" pattern="\d" inputmode="numeric">
                        </div>

                        <div class="otp-resend-row">
                            Didn't receive code? <button id="btnResendOtp" type="button" class="otp-resend-btn" disabled>Resend in 60s</button>
                        </div>
                    </div>

                    <button id="btnVerifyOtp" class="kv-btn kv-btn-primary kyc-action-btn">
                        Verify & Continue
                    </button>
                </div>
            </div>

            <!-- STEP 2: DIGILOCKER (AADHAAR + PAN) VERIFICATION VIA DIGIO -->
            <div id="step2Container" style="display: none;">
                <div class="kyc-info-banner">
                    <div class="kyc-info-banner-header">
                        <div class="kyc-info-banner-icon">🛡️</div>
                        <div class="kyc-info-banner-text">
                            <h3 class="kyc-info-banner-title">Identity Verification</h3>
                            <span class="kyc-info-banner-sub">Verify Aadhaar & PAN via DigiLocker — OTP goes to your Aadhaar-linked mobile.</span>
                        </div>
                    </div>
                </div>

                <div class="kyc-steps-box">
                    <h4 class="kyc-steps-title">What happens next:</h4>
                    <ol class="kyc-steps-list">
                        <li>DigiLocker gateway window opens.</li>
                        <li>Enter Aadhaar number and captcha.</li>
                        <li>Enter OTP received on Aadhaar-linked mobile & DigiLocker Security PIN.</li>
                        <li>Select <strong>Aadhaar Card</strong> & <strong>PAN Verification Record</strong> to grant consent.</li>
                        <li>After success, Aadhaar & PAN details are verified automatically.</li>
                    </ol>
                </div>

                <button id="btnOpenDigiLocker" class="kv-btn kv-btn-primary kyc-action-btn">
                    Open DigiLocker &nbsp; ❯
                </button>
            </div>

            <!-- STEP 3: SELFIE / FACE VERIFICATION -->
            <div id="step3Container" style="display: none;">
                <h2 class="kyc-card-title">Step 3: Selfie Verification</h2>
                <p class="kyc-card-desc">
                    Capture a live selfie or scan the QR code to take a photo on your mobile phone.
                </p>

                <!-- Method Switcher Pills -->
                <div class="selfie-switcher">
                    <button id="tabSelfieQr" type="button" class="selfie-switcher-btn is-active">
                        <span>📱</span> <span>Scan with Mobile QR</span>
                    </button>
                    <button id="tabSelfieDirect" type="button" class="selfie-switcher-btn">
                        <span>📷</span> <span>Web Camera / Upload</span>
                    </button>
                </div>

                <!-- SECTION 1: MOBILE QR SCANNER SECTION (DEFAULT) -->
                <div id="selfieQrSection" class="selfie-qr-card">
                    <div class="selfie-qr-header">
                        <h3 class="selfie-qr-title">Scan with your Phone Camera</h3>
                        <p class="selfie-qr-desc">
                            Scan this QR code with your mobile camera. Take a live selfie on your phone and it will automatically attach right here!
                        </p>
                    </div>

                    <!-- QR Code Card Box -->
                    <div class="selfie-qr-box">
                        <div id="qrLoader" class="selfie-qr-loader">
                            Generating QR Code...
                        </div>
                        <img id="imgQrCode" src="" alt="Mobile Selfie QR" class="selfie-qr-img" style="display: none;">
                    </div>

                    <!-- Live Sync Status Banner -->
                    <div class="selfie-sync-banner">
                        <span class="selfie-pulse-dot"></span>
                        <span id="qrStatusText">Waiting for mobile selfie upload...</span>
                        <span class="selfie-sync-sep">•</span>
                        <span id="qrTimer" class="selfie-timer">05:00</span>
                    </div>

                    <!-- Quick Actions Links -->
                    <div class="selfie-actions-row">
                        <button type="button" id="btnCopyMobileLink" class="selfie-action-link">Copy Mobile Link 📋</button>
                        <span class="selfie-action-sep">|</span>
                        <button type="button" id="btnRefreshQr" class="selfie-action-link text-muted">Regenerate QR 🔄</button>
                    </div>
                </div>

                <!-- SECTION 2: DIRECT WEB CAMERA SECTION -->
                <div id="selfieDirectSection" class="selfie-direct-card" style="display: none;">
                    <div class="selfie-direct-btns" style="display: flex; flex-direction: column; align-items: center; gap: 8px; width: 100%;">
                        <button id="btnStartCamera" type="button" class="selfie-opt-btn" style="width: 100%; max-width: 320px; font-weight: 700; background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: #fff; padding: 12px 20px; border-radius: 12px;">Start Web Camera 📷</button>
                        <span style="font-size: 11px; color: #94a3b8; text-align: center; margin-top: 4px;">🔒 Live camera capture is mandatory. Static file uploads from computer disk are disabled.</span>
                    </div>

                    <!-- Live Camera Box with Face Oval Overlay & Real-Time Shutter Lock -->
                    <div id="cameraBox" class="selfie-camera-box" style="display: none; position: relative; width: 100%; max-width: 420px; height: 380px; border-radius: 24px; overflow: hidden; background: #000; margin: 16px auto; flex-direction: column; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 20px 40px rgba(0,0,0,0.8);">
                        <video id="webcamPreview" autoplay playsinline class="selfie-video-preview" style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                        
                        <!-- Face Oval Overlay & Corner Framing Brackets -->
                        <div class="face-oval-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                            <svg width="100%" height="100%" viewBox="0 0 320 320" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
                                <defs>
                                    <mask id="webOvalMask">
                                        <rect width="100%" height="100%" fill="white" />
                                        <ellipse cx="160" cy="145" rx="85" ry="110" fill="black" />
                                    </mask>
                                </defs>
                                <rect width="100%" height="100%" fill="rgba(0, 0, 0, 0.55)" mask="url(#webOvalMask)" />
                                <!-- Oval Stroke (Turns Green when face detected) -->
                                <ellipse id="webOvalStroke" cx="160" cy="145" rx="85" ry="110" fill="none" stroke="rgba(255,255,255,0.6)" stroke-width="2.5" style="transition: all 0.3s ease;" />
                                <!-- 4 Corner Framing Brackets [ ] -->
                                <path id="webCornerTL" d="M 40 55 L 40 32 L 65 32" stroke="#38bdf8" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                                <path id="webCornerTR" d="M 280 55 L 280 32 L 255 32" stroke="#38bdf8" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                                <path id="webCornerBL" d="M 40 235 L 40 258 L 65 258" stroke="#38bdf8" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                                <path id="webCornerBR" d="M 280 235 L 280 258 L 255 258" stroke="#38bdf8" stroke-width="3.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <!-- Status Pill at Bottom of Video Frame -->
                            <div id="webFaceStatusPill" style="position: absolute; bottom: 20px; background: rgba(217, 119, 6, 0.25); border: 1px solid rgba(251, 191, 36, 0.4); backdrop-filter: blur(10px); padding: 8px 18px; border-radius: 24px; color: #fef08a; font-size: 12px; font-weight: 600; text-align: center; z-index: 10; transition: all 0.3s ease; display: flex; align-items: center; gap: 6px;">
                                <span>Keep your full face inside the circle</span>
                            </div>
                        </div>
                    </div>

                    <!-- Shutter Lock Instruction & Shutter Button -->
                    <div id="shutterControlRow" style="display: none; flex-direction: column; align-items: center; justify-content: center; margin-top: 12px; gap: 8px;">
                        <span id="shutterHintText" style="font-size: 12px; color: #94a3b8; font-weight: 500;">Shutter unlocks when face is detected</span>
                        
                        <!-- Shutter Button Ring (Disabled until face detected) -->
                        <button id="btnCaptureSelfie" type="button" disabled style="width: 68px; height: 68px; border-radius: 50%; border: 4px solid #475569; background: transparent; display: flex; align-items: center; justify-content: center; cursor: not-allowed; opacity: 0.4; transition: all 0.3s ease; outline: none; padding: 0;">
                            <span id="shutterInnerCircle" style="width: 52px; height: 52px; border-radius: 50%; background: #334155; display: block; transition: all 0.3s ease;"></span>
                        </button>
                    </div>
                </div>

                <!-- SECTION 3: SELFIE PREVIEW BOX (WHEN ATTACHED) -->
                <div id="selfiePreviewBox" class="selfie-preview-box" style="display: none;">
                    <div class="selfie-success-badge">
                        <span>✅</span> <span>Selfie Photo Attached Successfully!</span>
                    </div>
                    <img id="imgSelfiePreview" src="" alt="Selfie Preview" class="selfie-preview-img">
                    <button id="btnRetakeSelfie" type="button" class="selfie-retake-btn">Retake / Choose Another Method</button>
                </div>

                <button id="btnSubmitSelfie" class="kv-btn kv-btn-primary kyc-action-btn">
                    Submit Verification Request ❯
                </button>
            </div>

            <!-- STATE: PENDING REVIEW -->
            <div id="pendingReviewContainer" class="kyc-state-container" style="display: none;">
                <div class="kyc-state-icon">⏳</div>
                <h2 class="kyc-state-title">Verification Under Review</h2>
                <p class="kyc-state-desc">
                    Thank you! Your Aadhaar, PAN, and selfie details have been submitted successfully. Our compliance team will review and approve your account within 24 hours.
                </p>
                <div class="kyc-state-badge badge-pending">
                    Status: Pending Admin Approval
                </div>
            </div>

            <!-- STATE: VERIFIED -->
            <div id="verifiedContainer" class="kyc-state-container" style="display: none;">
                <div class="kyc-state-icon">✅</div>
                <h2 class="kyc-state-title">KYC Verified Successfully</h2>
                <p class="kyc-state-desc">
                    Your identity has been fully verified. You have full access to trading, deposits, and withdrawal features.
                </p>
                <a href="/wallet" class="kv-btn kv-btn-primary kyc-state-btn">
                    Go to Wallet
                </a>
            </div>

            <!-- STATE: REJECTED -->
            <div id="rejectedContainer" class="kyc-state-container" style="display: none;">
                <div class="kyc-state-icon">❌</div>
                <h2 class="kyc-state-title">Verification Rejected</h2>
                <p class="kyc-state-desc text-danger">
                    Reason: <span id="rejectionReasonText">Document details mismatched.</span>
                </p>
                <p class="kyc-state-subdesc">
                    Please click the button below to re-submit your verification details.
                </p>
                <button id="btnResetKyc" class="kv-btn kv-btn-primary kyc-state-btn">
                    Try Again
                </button>
            </div>

        </div>
    </div>
</section>
@endsection

@push('styles')
<style>
/* =========================================================================
   KYC PAGE RESPONSIVE STYLES
   ========================================================================= */
.kyc-page {
    padding: 32px 0 60px;
    min-height: calc(100vh - 140px);
    width: 100%;
    box-sizing: border-box;
}

.kyc-shell {
    max-width: 680px;
    margin: 0 auto;
    padding: 0 20px;
    width: 100%;
    box-sizing: border-box;
}

/* WIZARD STEPS INDICATOR */
.kyc-steps-indicator {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 30px;
    position: relative;
    width: 100%;
    box-sizing: border-box;
}

.step-progress-bg-line {
    position: absolute;
    top: 20px;
    left: 20px;
    right: 20px;
    height: 2px;
    background: rgba(255, 255, 255, 0.08);
    z-index: 1;
}

.step-progress-active-line {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 0%;
    height: 2px;
    background: var(--kv-yellow, #facc15);
    z-index: 1;
    transition: width 0.3s ease;
}

.step-node {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    z-index: 2;
    flex: 1;
    min-width: 0;
}

.step-num {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #1c1f2e;
    color: var(--kv-muted, #64748b);
    font-weight: 700;
    font-size: 15px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 3px solid #000;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

.step-node.active .step-num {
    background: var(--kv-yellow, #facc15);
    color: #000;
}

.step-lbl {
    margin-top: 8px;
    font-size: 12px;
    font-weight: 600;
    color: var(--kv-muted, #64748b);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    transition: color 0.3s ease;
}

.step-node.active .step-lbl {
    color: #ffffff;
}

/* MAIN CARD */
.kyc-card {
    padding: 32px;
    border-radius: 16px;
    border: 1px solid var(--kv-line, rgba(255, 255, 255, 0.08));
    background: var(--kv-surface, #0b0d16);
    position: relative;
    box-sizing: border-box;
    width: 100%;
}

.kyc-card-title {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 6px;
    color: #ffffff;
    line-height: 1.35;
}

.kyc-card-desc {
    color: var(--kv-muted, #64748b);
    font-size: 14px;
    margin-bottom: 24px;
    line-height: 1.5;
}

.kyc-field-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 20px;
    width: 100%;
}

.kyc-field-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--kv-muted, #64748b);
}

.kyc-phone-row {
    display: flex;
    gap: 10px;
    width: 100%;
    box-sizing: border-box;
}

.kyc-country-select {
    width: 105px;
    padding: 10px 12px;
    border-radius: 8px;
    background: var(--kv-surface-2, #141724);
    border: 1px solid var(--kv-line, rgba(255, 255, 255, 0.08));
    color: #ffffff;
    outline: none;
    font-size: 14px;
    flex-shrink: 0;
    box-sizing: border-box;
}

.kyc-mobile-input {
    flex: 1;
    min-width: 0;
    padding: 10px 14px;
    border-radius: 8px;
    background: var(--kv-surface-2, #141724);
    border: 1px solid var(--kv-line, rgba(255, 255, 255, 0.08));
    color: #ffffff;
    outline: none;
    font-size: 15px;
    box-sizing: border-box;
}

.kyc-action-btn {
    width: 100%;
    height: 46px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    border-radius: 10px;
    font-size: 15px;
    box-sizing: border-box;
}

/* OTP SECTION */
.otp-target-bar {
    background: rgba(255, 255, 255, 0.02);
    border: 1px dashed rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    padding: 12px 14px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    width: 100%;
    box-sizing: border-box;
}

.otp-target-phone {
    font-size: 14px;
    color: #ffffff;
    font-weight: 600;
}

.otp-edit-phone-btn {
    background: none;
    border: none;
    color: var(--kv-yellow, #facc15);
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    text-decoration: underline;
    padding: 0;
}

.otp-center-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
    width: 100%;
    box-sizing: border-box;
}

.otp-inputs-wrapper {
    display: flex;
    gap: 8px;
    justify-content: center;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
}

.otp-digit {
    width: 48px;
    height: 52px;
    border-radius: 8px;
    border: 1px solid var(--kv-line, rgba(255, 255, 255, 0.08));
    background: var(--kv-surface-2, #141724);
    text-align: center;
    font-size: 22px;
    font-weight: 700;
    color: #ffffff;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    box-sizing: border-box;
    flex-shrink: 1;
    min-width: 0;
}

.otp-digit:focus {
    border-color: var(--kv-yellow, #facc15) !important;
    box-shadow: 0 0 0 2px rgba(250, 204, 21, 0.15) !important;
}

.otp-resend-row {
    font-size: 13px;
    color: var(--kv-muted, #64748b);
    text-align: center;
}

.otp-resend-btn {
    background: none;
    border: none;
    color: var(--kv-yellow, #facc15);
    cursor: pointer;
    font-weight: 600;
    text-decoration: underline;
    padding: 0;
}

.otp-resend-btn:disabled {
    color: var(--kv-muted, #64748b);
    cursor: not-allowed;
    text-decoration: none;
}

/* DIGILOCKER STEP */
.kyc-info-banner {
    background: rgba(250, 204, 21, 0.05);
    border: 1px solid rgba(250, 204, 21, 0.2);
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 20px;
    box-sizing: border-box;
}

.kyc-info-banner-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.kyc-info-banner-icon {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    background: rgba(250, 204, 21, 0.15);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.kyc-info-banner-text {
    flex: 1;
    min-width: 0;
}

.kyc-info-banner-title {
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 2px;
}

.kyc-info-banner-sub {
    font-size: 13px;
    color: var(--kv-muted, #94a3b8);
    display: block;
    line-height: 1.4;
}

.kyc-steps-box {
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 24px;
    box-sizing: border-box;
}

.kyc-steps-title {
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 12px;
}

.kyc-steps-list {
    margin: 0;
    padding-left: 20px;
    color: var(--kv-muted, #94a3b8);
    font-size: 13px;
    line-height: 1.8;
}

/* SELFIE STEP */
.selfie-switcher {
    display: flex;
    gap: 8px;
    justify-content: center;
    margin-bottom: 22px;
    flex-wrap: wrap;
    width: 100%;
}

.selfie-switcher-btn {
    background: rgba(255, 255, 255, 0.06);
    color: #94a3b8;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid rgba(255, 255, 255, 0.06);
    cursor: pointer;
    transition: all 0.2s ease;
    flex: 1 1 auto;
    max-width: 240px;
    box-sizing: border-box;
    text-align: center;
}

.selfie-switcher-btn.is-active {
    background: #2563eb;
    color: #ffffff;
    border-color: rgba(255, 255, 255, 0.1);
}

.selfie-qr-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding: 22px 16px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid rgba(255, 255, 255, 0.06);
    box-sizing: border-box;
    width: 100%;
}

.selfie-qr-header {
    text-align: center;
    max-width: 440px;
    width: 100%;
}

.selfie-qr-title {
    font-size: 15px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 4px;
}

.selfie-qr-desc {
    font-size: 13px;
    color: var(--kv-muted, #94a3b8);
    line-height: 1.45;
    margin: 0;
}

.selfie-qr-box {
    position: relative;
    padding: 12px;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 210px;
    height: 210px;
    max-width: 100%;
    box-sizing: border-box;
}

.selfie-qr-loader {
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
}

.selfie-qr-img {
    width: 186px;
    height: 186px;
    object-fit: contain;
    border-radius: 8px;
    max-width: 100%;
}

.selfie-sync-banner {
    display: flex;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 30px;
    background: rgba(56, 189, 248, 0.1);
    border: 1px solid rgba(56, 189, 248, 0.3);
    color: #38bdf8;
    font-size: 12px;
    font-weight: 600;
    max-width: 100%;
    text-align: center;
    box-sizing: border-box;
}

.selfie-pulse-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #38bdf8;
    animation: pulse 1.5s infinite;
}

.selfie-timer {
    font-family: monospace;
    font-weight: 700;
}

.selfie-actions-row {
    display: flex;
    gap: 12px;
    align-items: center;
    justify-content: center;
    flex-wrap: wrap;
}

.selfie-action-link {
    background: none;
    border: none;
    color: var(--kv-yellow, #facc15);
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    text-decoration: underline;
    padding: 0;
}

.selfie-action-link.text-muted {
    color: #94a3b8;
    text-decoration: none;
}

.selfie-action-sep {
    color: rgba(255, 255, 255, 0.2);
}

.selfie-direct-card {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 24px;
    width: 100%;
    box-sizing: border-box;
}

.selfie-direct-btns {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
    width: 100%;
}

.selfie-opt-btn {
    background: rgba(255, 255, 255, 0.06);
    color: #ffffff;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    border: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    box-sizing: border-box;
}

.selfie-camera-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    width: 100%;
}

.selfie-video-preview {
    width: 100%;
    max-width: 360px;
    height: auto;
    aspect-ratio: 4/3;
    border-radius: 12px;
    border: 1px solid var(--kv-yellow, #facc15);
    object-fit: cover;
    background: #000000;
}

.selfie-capture-btn {
    padding: 8px 24px;
    border-radius: 8px;
    font-weight: 600;
}

.selfie-preview-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    margin-bottom: 24px;
    padding: 20px;
    border-radius: 14px;
    background: rgba(34, 197, 94, 0.05);
    border: 1px solid rgba(34, 197, 94, 0.2);
    box-sizing: border-box;
    width: 100%;
}

.selfie-success-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #4ade80;
    font-size: 13px;
    font-weight: 700;
    text-align: center;
}

.selfie-preview-img {
    width: 100%;
    max-width: 320px;
    height: auto;
    aspect-ratio: 4/3;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid #22c55e;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
}

.selfie-retake-btn {
    background: none;
    border: none;
    color: var(--kv-yellow, #facc15);
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    text-decoration: underline;
    padding: 0;
}

/* STATE CONTAINERS */
.kyc-state-container {
    text-align: center;
    padding: 30px 10px;
    width: 100%;
    box-sizing: border-box;
}

.kyc-state-icon {
    font-size: 54px;
    margin-bottom: 16px;
}

.kyc-state-title {
    font-size: 22px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 8px;
}

.kyc-state-desc {
    color: var(--kv-muted, #64748b);
    font-size: 14px;
    max-width: 480px;
    margin: 0 auto 24px;
    line-height: 1.6;
}

.kyc-state-desc.text-danger {
    color: #ef4444;
    margin-bottom: 16px;
}

.kyc-state-subdesc {
    color: var(--kv-muted, #64748b);
    font-size: 13px;
    margin-bottom: 24px;
}

.kyc-state-badge {
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    display: inline-block;
}

.kyc-state-badge.badge-pending {
    background: rgba(250, 204, 21, 0.08);
    border: 1px solid rgba(250, 204, 21, 0.2);
    color: var(--kv-yellow, #facc15);
}

.kyc-state-btn {
    padding: 10px 24px;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-block;
}

/* DIGIO MODAL STYLES - PURE FLEXBOX CENTERING */
html body #digio-modal-container,
html body #digio_gateway,
html body #kryvexDigioModal,
html body div[id*="digio_gateway"] {
    pointer-events: auto !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    position: fixed !important;
    inset: 0 !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    height: 100dvh !important;
    margin: 0 !important;
    padding: 6px !important;
    box-sizing: border-box !important;
    z-index: 2147483640 !important;
    background: rgba(0, 0, 0, 0.88) !important;
    backdrop-filter: blur(8px) !important;
    -webkit-backdrop-filter: blur(8px) !important;
    overflow: hidden !important;
}

html body #digio_gateway *,
html body #digio-modal-container *,
html body #kryvexDigioModal *,
html body div[id*="digio_gateway"] * {
    box-sizing: border-box !important;
}

/* Intermediate wrappers reset */
html body #digio_gateway div,
html body #digio-modal-container div,
html body #kryvexDigioModal div,
html body div[id*="digio_gateway"] div {
    position: relative !important;
    top: auto !important;
    left: auto !important;
    right: auto !important;
    bottom: auto !important;
    transform: none !important;
    -webkit-transform: none !important;
    margin: auto !important;
}

/* Remove extra blank space/header padding inside Digio modal */
html body #digio_gateway [class*="header"],
html body #digio_gateway [id*="header"],
html body #digio_gateway [class*="logo"],
html body #digio_gateway [id*="logo"],
html body #digio-modal-container [class*="header"],
html body #digio-modal-container [id*="header"],
html body #digio-modal-container [class*="logo"],
html body #digio-modal-container [id*="logo"],
html body div[id*="digio_gateway"] [class*="header"],
html body div[id*="digio_gateway"] [class*="logo"],
html body #digio_gateway > div > div:not(:has(iframe)),
html body #digio-modal-container > div > div:not(:has(iframe)),
html body #digio_gateway div:not(:has(iframe)):not(iframe),
html body #digio-modal-container div:not(:has(iframe)):not(iframe) {
    height: auto !important;
    min-height: 0 !important;
    max-height: 36px !important;
    padding: 6px 12px !important;
    margin: 0 auto !important;
    overflow: hidden !important;
    flex: 0 0 auto !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
}

html body #digio_gateway a[href*="digio"],
html body #digio-modal-container a[href*="digio"] {
    line-height: 1.2 !important;
    padding: 0 !important;
    margin: 0 auto !important;
    font-size: 12px !important;
    font-weight: 500 !important;
}

html body #digio_gateway div:has(> iframe),
html body #digio-modal-container div:has(> iframe),
html body div[id*="digio_gateway"] div:has(> iframe) {
    flex: 1 1 auto !important;
    height: 100% !important;
    min-height: 0 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
}

html body #digio_gateway [class*="logo"]:empty,
html body #digio_gateway [id*="logo"]:empty,
html body #digio-modal-container [class*="logo"]:empty,
html body #digio-modal-container [id*="logo"]:empty {
    display: none !important;
}

/* Modal Card on Desktop & Tablet (> 640px) */
html body #digio_gateway > div,
html body #digio_gateway > div > div,
html body #digio-modal-container > div,
html body #digio-modal-container > div > div,
html body #kryvexDigioModal > div,
html body div[id*="digio_gateway"] > div {
    position: fixed !important;
    top: 50% !important;
    left: 50% !important;
    right: auto !important;
    bottom: auto !important;
    transform: translate(-50%, -50%) !important;
    -webkit-transform: translate(-50%, -50%) !important;
    width: 560px !important;
    max-width: calc(100vw - 24px) !important;
    height: 720px !important;
    max-height: calc(100vh - 24px) !important;
    max-height: calc(100dvh - 24px) !important;
    border-radius: 16px !important;
    background: #ffffff !important;
    box-shadow: 0 30px 90px rgba(0, 0, 0, 0.95) !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
    margin: 0 !important;
    z-index: 2147483645 !important;
}

/* Modal Card on Mobile (<= 640px) - Perfectly Dead-Center */
@media (max-width: 640px) {
    html body #digio_gateway > div,
    html body #digio_gateway > div > div,
    html body #digio-modal-container > div,
    html body #digio-modal-container > div > div,
    html body #kryvexDigioModal > div,
    html body div[id*="digio_gateway"] > div {
        position: fixed !important;
        top: 50% !important;
        left: 50% !important;
        right: auto !important;
        bottom: auto !important;
        transform: translate(-50%, -50%) !important;
        -webkit-transform: translate(-50%, -50%) !important;
        width: calc(100vw - 24px) !important;
        max-width: 440px !important;
        height: calc(100vh - 24px) !important;
        max-height: calc(100dvh - 24px) !important;
        border-radius: 16px !important;
        background: #ffffff !important;
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.9) !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        margin: 0 !important;
        z-index: 2147483645 !important;
    }
}

/* Digio Iframe Base Styles */
html body #digio_gateway iframe,
html body #digio-modal-container iframe,
html body #kryvexDigioModal iframe,
html body div[id*="digio_gateway"] iframe,
html body iframe[src*="digio"],
html body iframe[id*="digio"] {
    position: relative !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    height: 100% !important;
    flex: 1 1 auto !important;
    min-height: 0 !important;
    border: none !important;
    background: #ffffff !important;
    display: block !important;
    transform: none !important;
    -webkit-transform: none !important;
}

/* =========================================================================
   MEDIA QUERIES FOR TABLETS AND MOBILES (DOWN TO 320PX)
   ========================================================================= */
@media (max-width: 640px) {
    .kyc-page {
        padding: 16px 0 40px;
    }

    .kyc-shell {
        padding: 0 12px;
    }

    .kyc-card {
        padding: 20px 14px;
        border-radius: 14px;
    }

    .kyc-card-title {
        font-size: 18px;
    }

    .kyc-card-desc {
        font-size: 13px;
        margin-bottom: 18px;
    }

    .kyc-steps-indicator {
        margin-bottom: 22px;
    }

    .step-num {
        width: 34px;
        height: 34px;
        font-size: 13px;
        border-width: 2px;
    }

    .step-progress-bg-line,
    .step-progress-active-line {
        top: 17px;
        left: 14px;
        right: 14px;
    }

    .step-lbl {
        font-size: 11px;
        margin-top: 6px;
    }

    .otp-inputs-wrapper {
        gap: 6px;
    }

    .otp-digit {
        width: 40px;
        height: 46px;
        font-size: 18px;
    }

    .selfie-switcher-btn {
        max-width: 100%;
        width: 100%;
        padding: 8px 12px;
        font-size: 12px;
    }

    .kyc-state-title {
        font-size: 19px;
    }

    .kyc-state-desc {
        font-size: 13px;
    }
}

/* Extra Small Screens (320px - 380px) */
@media (max-width: 380px) {
    .kyc-shell {
        padding: 0 8px;
    }

    .kyc-card {
        padding: 16px 10px;
        border-radius: 12px;
    }

    .kyc-card-title {
        font-size: 16px;
    }

    .kyc-card-desc {
        font-size: 12px;
        margin-bottom: 16px;
    }

    .step-num {
        width: 30px;
        height: 30px;
        font-size: 12px;
    }

    .step-progress-bg-line,
    .step-progress-active-line {
        top: 15px;
        left: 10px;
        right: 10px;
    }

    .step-lbl {
        font-size: 10px;
    }

    .kyc-country-select {
        width: 82px;
        padding: 8px 4px;
        font-size: 12px;
    }

    .kyc-mobile-input {
        padding: 8px 10px;
        font-size: 13px;
    }

    .otp-inputs-wrapper {
        gap: 4px;
    }

    .otp-digit {
        width: 34px;
        height: 42px;
        font-size: 16px;
        border-radius: 6px;
    }

    .otp-target-bar {
        padding: 10px;
    }

    .otp-target-phone {
        font-size: 13px;
    }

    .kyc-info-banner,
    .kyc-steps-box {
        padding: 12px 10px;
    }

    .kyc-info-banner-icon {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }

    .kyc-info-banner-title {
        font-size: 14px;
    }

    .kyc-info-banner-sub {
        font-size: 12px;
    }

    .kyc-steps-list {
        padding-left: 16px;
        font-size: 12px;
    }

    .selfie-qr-card {
        padding: 14px 10px;
    }

    .selfie-qr-box {
        width: 175px;
        height: 175px;
        padding: 8px;
    }

    .selfie-qr-img {
        width: 155px;
        height: 155px;
    }

    .selfie-sync-banner {
        font-size: 11px;
        padding: 6px 10px;
    }

    .selfie-preview-box {
        padding: 12px 10px;
    }

    .kyc-action-btn {
        height: 42px;
        font-size: 14px;
    }
}

/* =========================================================================
   KYC PAGE LIGHT THEME OVERRIDES
   ========================================================================= */
html[data-theme="light"] .kyc-page,
html.theme-light .kyc-page {
    background-color: #f8fafc !important;
    color: #0f172a !important;
}

/* Steps Indicator in Light Theme */
html[data-theme="light"] .step-progress-bg-line,
html.theme-light .step-progress-bg-line {
    background: #e2e8f0 !important;
}

html[data-theme="light"] .step-progress-active-line,
html.theme-light .step-progress-active-line {
    background: #7c3aed !important;
}

html[data-theme="light"] .step-num,
html.theme-light .step-num {
    background: #f1f5f9 !important;
    color: #64748b !important;
    border: 3px solid #ffffff !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08) !important;
}

html[data-theme="light"] .step-node.active .step-num,
html.theme-light .step-node.active .step-num {
    background: #7c3aed !important;
    color: #ffffff !important;
    border-color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.4) !important;
}

html[data-theme="light"] .step-lbl,
html.theme-light .step-lbl {
    color: #64748b !important;
    font-weight: 600 !important;
}

html[data-theme="light"] .step-node.active .step-lbl,
html.theme-light .step-node.active .step-lbl {
    color: #7c3aed !important;
    font-weight: 700 !important;
}

/* Main Wizard Card in Light Theme */
html[data-theme="light"] .kyc-card,
html.theme-light .kyc-card {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06) !important;
    color: #0f172a !important;
}

html[data-theme="light"] .kyc-card-title,
html.theme-light .kyc-card-title {
    color: #0f172a !important;
}

html[data-theme="light"] .kyc-card-desc,
html.theme-light .kyc-card-desc {
    color: #475569 !important;
}

/* Step 1 Inputs & OTP */
html[data-theme="light"] .kyc-field-label,
html.theme-light .kyc-field-label {
    color: #475569 !important;
}

html[data-theme="light"] .kyc-country-select,
html[data-theme="light"] .kyc-mobile-input,
html[data-theme="light"] .otp-digit,
html.theme-light .kyc-country-select,
html.theme-light .kyc-mobile-input,
html.theme-light .otp-digit {
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
}

html[data-theme="light"] .kyc-country-select:focus,
html[data-theme="light"] .kyc-mobile-input:focus,
html[data-theme="light"] .otp-digit:focus,
html.theme-light .kyc-country-select:focus,
html.theme-light .kyc-mobile-input:focus,
html.theme-light .otp-digit:focus {
    border-color: #7c3aed !important;
    box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.15) !important;
}

html[data-theme="light"] .otp-target-bar,
html.theme-light .otp-target-bar {
    background: #f8fafc !important;
    border: 1px dashed #cbd5e1 !important;
}

html[data-theme="light"] .otp-target-phone,
html.theme-light .otp-target-phone {
    color: #0f172a !important;
}

html[data-theme="light"] .otp-edit-phone-btn,
html[data-theme="light"] .otp-resend-btn,
html.theme-light .otp-edit-phone-btn,
html.theme-light .otp-resend-btn {
    color: #7c3aed !important;
}

html[data-theme="light"] .otp-resend-row,
html.theme-light .otp-resend-row {
    color: #64748b !important;
}

/* Step 2 DigiLocker Banner & Steps */
html[data-theme="light"] .kyc-info-banner,
html.theme-light .kyc-info-banner {
    background: #f5f3ff !important;
    border: 1px solid #ddd6fe !important;
}

html[data-theme="light"] .kyc-info-banner-icon,
html.theme-light .kyc-info-banner-icon {
    background: #ede9fe !important;
    color: #7c3aed !important;
}

html[data-theme="light"] .kyc-info-banner-title,
html.theme-light .kyc-info-banner-title {
    color: #0f172a !important;
}

html[data-theme="light"] .kyc-info-banner-sub,
html.theme-light .kyc-info-banner-sub {
    color: #475569 !important;
}

html[data-theme="light"] .kyc-steps-box,
html.theme-light .kyc-steps-box {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
}

html[data-theme="light"] .kyc-steps-title,
html.theme-light .kyc-steps-title {
    color: #0f172a !important;
}

html[data-theme="light"] .kyc-steps-list,
html[data-theme="light"] .kyc-steps-list li,
html.theme-light .kyc-steps-list,
html.theme-light .kyc-steps-list li {
    color: #334155 !important;
}

html[data-theme="light"] .kyc-steps-list strong,
html.theme-light .kyc-steps-list strong {
    color: #0f172a !important;
}

/* Action Buttons */
html[data-theme="light"] .kyc-action-btn,
html[data-theme="light"] #btnOpenDigiLocker,
html[data-theme="light"] #btnSendOtp,
html[data-theme="light"] #btnVerifyOtp,
html[data-theme="light"] #btnSubmitSelfie,
html.theme-light .kyc-action-btn,
html.theme-light #btnOpenDigiLocker,
html.theme-light #btnSendOtp,
html.theme-light #btnVerifyOtp,
html.theme-light #btnSubmitSelfie {
    background: #7c3aed !important;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(124, 58, 237, 0.3) !important;
}

html[data-theme="light"] .kyc-action-btn:hover,
html[data-theme="light"] #btnOpenDigiLocker:hover,
html[data-theme="light"] #btnSendOtp:hover,
html[data-theme="light"] #btnVerifyOtp:hover,
html[data-theme="light"] #btnSubmitSelfie:hover,
html.theme-light .kyc-action-btn:hover,
html.theme-light #btnOpenDigiLocker:hover,
html.theme-light #btnSendOtp:hover,
html.theme-light #btnVerifyOtp:hover,
html.theme-light #btnSubmitSelfie:hover {
    background: #6d28d9 !important;
    color: #ffffff !important;
}

/* Step 3 Selfie & Camera */
html[data-theme="light"] .selfie-switcher-btn,
html.theme-light .selfie-switcher-btn {
    background: #f1f5f9 !important;
    border: 1px solid #e2e8f0 !important;
    color: #64748b !important;
}

html[data-theme="light"] .selfie-switcher-btn.is-active,
html.theme-light .selfie-switcher-btn.is-active {
    background: #7c3aed !important;
    color: #ffffff !important;
    border-color: #7c3aed !important;
}

html[data-theme="light"] .selfie-qr-card,
html.theme-light .selfie-qr-card {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
}

html[data-theme="light"] .selfie-qr-title,
html.theme-light .selfie-qr-title {
    color: #0f172a !important;
}

html[data-theme="light"] .selfie-qr-desc,
html.theme-light .selfie-qr-desc {
    color: #475569 !important;
}

html[data-theme="light"] .selfie-qr-box,
html.theme-light .selfie-qr-box {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06) !important;
}

html[data-theme="light"] .selfie-sync-banner,
html.theme-light .selfie-sync-banner {
    background: #f0fdf4 !important;
    border: 1px solid #bbf7d0 !important;
    color: #16a34a !important;
}

html[data-theme="light"] .selfie-pulse-dot,
html.theme-light .selfie-pulse-dot {
    background: #16a34a !important;
}

html[data-theme="light"] .selfie-action-link,
html.theme-light .selfie-action-link {
    color: #7c3aed !important;
}

html[data-theme="light"] .selfie-action-link.text-muted,
html.theme-light .selfie-action-link.text-muted {
    color: #64748b !important;
}

html[data-theme="light"] .selfie-action-sep,
html.theme-light .selfie-action-sep {
    color: #cbd5e1 !important;
}

html[data-theme="light"] .selfie-opt-btn,
html.theme-light .selfie-opt-btn {
    background: #f1f5f9 !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
}

html[data-theme="light"] .selfie-preview-box,
html.theme-light .selfie-preview-box {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
}

html[data-theme="light"] .selfie-success-badge,
html.theme-light .selfie-success-badge {
    background: #dcfce7 !important;
    border: 1px solid #86efac !important;
    color: #15803d !important;
}

html[data-theme="light"] .selfie-retake-btn,
html.theme-light .selfie-retake-btn {
    color: #ef4444 !important;
}

/* Status States */
html[data-theme="light"] .kyc-state-container,
html.theme-light .kyc-state-container {
    background: #ffffff !important;
    color: #0f172a !important;
}

html[data-theme="light"] .kyc-state-title,
html.theme-light .kyc-state-title {
    color: #0f172a !important;
}

html[data-theme="light"] .kyc-state-desc,
html.theme-light .kyc-state-desc {
    color: #475569 !important;
}

html[data-theme="light"] .kyc-state-badge.badge-pending,
html.theme-light .kyc-state-badge.badge-pending {
    background: #fef3c7 !important;
    border: 1px solid #fde68a !important;
    color: #b45309 !important;
}

/* =========================================================================
   DIGIO KYC MODAL SHIFTED UPWARD FOR CLEAN BOTTOM SPACING
   ========================================================================= */
#digio_gateway,
iframe#digio_gateway,
iframe[src*="digio"],
iframe[id*="digio"],
div[id*="digio"],
#digio-modal-container,
#kryvexDigioModal {
    position: fixed !important;
    inset: 0 !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    max-width: 100vw !important;
    max-height: 100vh !important;
    min-width: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
    transform: none !important;
    -webkit-transform: none !important;
    z-index: 2147483647 !important;
    border: none !important;
    box-sizing: border-box !important;
    background: rgba(0, 0, 0, 0.75) !important;
}

#digio_gateway > div,
#digio-modal-container > div,
div[id*="digio_gateway"] > div {
    position: relative !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    margin: auto !important;
    transform: none !important;
    -webkit-transform: none !important;
    width: 100% !important;
    max-width: 480px !important;
    height: min(90vh, 700px) !important;
    max-height: calc(100dvh - 40px) !important;
    background: #ffffff !important;
    border-radius: 16px !important;
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
}

#digio_gateway iframe,
#digio-modal-container iframe,
div[id*="digio_gateway"] iframe {
    width: 100% !important;
    max-width: 100% !important;
    height: 100% !important;
    border: none !important;
    background: #ffffff !important;
    margin: 0 !important;
    left: 0 !important;
    top: 0 !important;
}

@media (max-width: 640px) {
    #digio_gateway,
    iframe#digio_gateway,
    iframe[src*="digio"],
    iframe[id*="digio"],
    div[id*="digio"],
    #digio-modal-container,
    #kryvexDigioModal {
        position: fixed !important;
        inset: 0 !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        width: 100vw !important;
        height: 100vh !important;
        max-width: 100vw !important;
        max-height: 100dvh !important;
        padding-top: 6px !important;
        padding-bottom: 75px !important;
        box-sizing: border-box !important;
    }

    #digio_gateway > div,
    #digio-modal-container > div,
    div[id*="digio_gateway"] > div {
        position: fixed !important;
        top: 6px !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 75px !important;
        width: 100vw !important;
        max-width: 100vw !important;
        height: calc(100dvh - 82px) !important;
        max-height: calc(100dvh - 82px) !important;
        margin: 0 auto !important;
        border-radius: 14px 14px 0 0 !important;
        transform: none !important;
        -webkit-transform: none !important;
        box-shadow: 0 -6px 25px rgba(0,0,0,0.3) !important;
    }

    #digio_gateway iframe,
    #digio-modal-container iframe,
    div[id*="digio_gateway"] iframe {
        width: 100vw !important;
        max-width: 100vw !important;
        height: calc(100dvh - 82px) !important;
        max-height: calc(100dvh - 82px) !important;
        margin: 0 !important;
        left: 0 !important;
        top: 0 !important;
    }
}
</style>
@endpush
