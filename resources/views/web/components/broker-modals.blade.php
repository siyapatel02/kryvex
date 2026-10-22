<style>
/* ─── GLOBAL BROKER & INSTALL MODAL CSS ─── */
.kv-modal-backdrop {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(0, 0, 0, 0.85) !important;
    backdrop-filter: blur(8px) !important;
    z-index: 999999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 16px !important;
    box-sizing: border-box !important;
    overflow-y: auto !important;
    -webkit-overflow-scrolling: touch !important;
}
.kv-modal-backdrop[hidden],
.kv-modal-card[hidden] {
    display: none !important;
}
.kv-modal-backdrop.is-open {
    display: flex !important;
}
.kv-modal-card.is-open {
    display: block !important;
}
.kv-modal-card.broker-modal-card {
    background: #121824 !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 20px !important;
    width: 100% !important;
    max-width: 480px !important;
    max-height: min(90vh, calc(100dvh - 28px)) !important;
    overflow-y: auto !important;
    padding: 24px !important;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.85) !important;
    box-sizing: border-box !important;
    position: relative !important;
    margin: auto !important;
    z-index: 1000000 !important;
}
.modal-icon-badge {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(139, 92, 246, 0.15);
    border: 1px solid rgba(139, 92, 246, 0.35);
    color: #a78bfa;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 4px;
}
.kv-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.kv-modal-head h2 {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 800;
    color: #ffffff;
}
.kv-modal-close {
    background: transparent;
    border: 0;
    color: #94a3b8;
    font-size: 1.8rem;
    cursor: pointer;
    line-height: 1;
    padding: 0 4px;
}
.kv-modal-close:hover {
    color: #ffffff;
}
.modal-subtext {
    font-size: 0.88rem;
    color: #94a3b8;
    margin: 0 0 20px 0;
    line-height: 1.5;
}
.broker-options-list {
    display: grid;
    gap: 12px;
}
.broker-option-card {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.03);
    border: 2px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}
.broker-option-card:hover {
    border-color: rgba(139, 92, 246, 0.5);
    background: rgba(139, 92, 246, 0.06);
}
.broker-option-card.is-selected {
    border-color: #8b5cf6 !important;
    background: rgba(139, 92, 246, 0.14) !important;
}
.broker-option-card input[type="radio"] {
    display: none;
}
.broker-option-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.06);
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.broker-option-info {
    flex: 1;
}
.broker-option-info strong {
    display: block;
    font-size: 1rem;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 2px;
}
.broker-option-info span {
    font-size: 0.78rem;
    color: #94a3b8;
    line-height: 1.3;
    display: block;
}
.radio-check-indicator {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid #64748b;
    position: relative;
    flex-shrink: 0;
}
.broker-option-card.is-selected .radio-check-indicator {
    border-color: #8b5cf6;
    background: #8b5cf6;
}
.broker-option-card.is-selected .radio-check-indicator::after {
    content: '';
    position: absolute;
    top: 6px; left: 6px; width: 6px; height: 6px;
    border-radius: 50%;
    background: #ffffff;
}
.broker-submit-btn {
    width: 100%;
    margin-top: 14px;
    padding: 13px;
    border-radius: 12px;
    background: #8b5cf6 !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    font-size: 0.98rem !important;
    border: 0 !important;
    cursor: pointer !important;
    transition: opacity 0.2s ease;
}
.broker-submit-btn:hover {
    opacity: 0.9;
}
.mt5-plans-grid {
    display: grid;
    gap: 12px;
    margin-bottom: 16px;
}
.mt5-plan-card {
    padding: 14px 16px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.03);
    border: 2px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.2s ease;
}
.mt5-plan-card.is-selected {
    border-color: #8b5cf6 !important;
    background: rgba(139, 92, 246, 0.14) !important;
}
.mt5-plan-card.is-disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
.mt5-error-box {
    background: rgba(239, 68, 68, 0.12);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 16px;
}
.wallet-balance-indicator {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    padding: 10px 14px;
    border-radius: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
    font-size: 0.88rem;
    color: #94a3b8;
}
.wallet-balance-indicator strong {
    color: #a78bfa;
    font-size: 1rem;
    font-family: monospace;
}
.modal-actions-row {
    display: flex;
    gap: 12px;
    margin-top: 10px;
}
.mt5-requested-notice-card {
    background: #0d111a;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
    font-size: 0.9rem;
    color: #94a3b8;
    line-height: 1.6;
}
.mt5-requested-notice-card strong {
    color: #ffffff;
}

/* ─── ULTRA PREMIUM DOWNLOAD APP MODAL ─── */
.install-app-modal-card {
    background: linear-gradient(180deg, #111827 0%, #0b0f19 100%) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 28px !important;
    width: 100% !important;
    max-width: 440px !important;
    padding: 28px 24px !important;
    box-shadow: 0 35px 80px -15px rgba(0, 0, 0, 0.9), 0 0 40px rgba(168, 85, 247, 0.12) !important;
    box-sizing: border-box !important;
    position: relative !important;
    text-align: center;
    z-index: 1000000 !important;
}
.install-app-modal-card .kv-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.modal-head-title-wrap {
    display: flex;
    align-items: center;
    gap: 12px;
}
.modal-icon-badge.phone-badge {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: rgba(236, 186, 22, 0.15) !important;
    border: 1px solid rgba(247, 231, 85, 0.35) !important;
    color: #f3d583 !important;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin: 0 !important;
}
.phone-mockup-wrapper {
    margin: 12px auto 20px;
    display: flex;
    justify-content: center;
    position: relative;
    padding: 10px 0;
}
.phone-mockup-wrapper::before {
    content: '';
    position: absolute;
    width: 220px;
    height: 220px;
    background: radial-gradient(circle, rgba(168, 85, 247, 0.25) 0%, rgba(99, 102, 241, 0.15) 50%, rgba(0, 0, 0, 0) 75%);
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1;
    pointer-events: none;
    filter: blur(20px);
}
.phone-frame {
    width: 200px;
    height: 310px;
    background: #090d16;
    border: 4px solid rgba(255, 255, 255, 0.18);
    border-radius: 32px;
    padding: 12px 10px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.8), 0 0 25px rgba(168, 85, 247, 0.2);
    position: relative;
    box-sizing: border-box;
    overflow: hidden;
    text-align: left;
    z-index: 2;
}
.phone-notch {
    width: 60px;
    height: 14px;
    background: #0f172a;
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 10px;
    position: absolute;
    top: 0; left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
}
.phone-notch-camera {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #000;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
.phone-screen {
    height: 100%;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding-top: 8px;
}
.phone-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.65rem;
    color: #94a3b8;
    font-weight: 700;
}
.phone-header .brand {
    color: #f6c93f;
    font-weight: 900;
    letter-spacing: 1px;
    font-size: 0.7rem;
}
.phone-title {
    font-size: 0.88rem;
    font-weight: 800;
    color: #fff;
    margin-top: 4px;
}
.phone-balance-card {
    background: linear-gradient(135deg, rgba(124, 58, 237, 0.25) 0%, rgba(67, 56, 202, 0.2) 100%);
    border: 1px solid rgba(168, 85, 247, 0.35);
    border-radius: 12px;
    padding: 9px 10px;
    margin-top: 4px;
}
.phone-balance-card .bal-label {
    font-size: 0.6rem;
    color: #c084fc;
    font-weight: 600;
}
.phone-balance-card .bal-val {
    font-size: 1rem;
    font-weight: 900;
    color: #fff;
    margin: 2px 0 1px;
    letter-spacing: -0.3px;
}
.phone-balance-card .bal-change {
    font-size: 0.58rem;
    color: #34d399;
    font-weight: 700;
}
.phone-chart-area {
    height: 38px;
    margin: 4px 0;
    position: relative;
}
.phone-chart-area svg {
    width: 100%;
    height: 100%;
}
.phone-coin-row {
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.06);
    padding: 6px 8px;
    border-radius: 10px;
    font-size: 0.62rem;
}
.coin-badge {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    font-weight: 900;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.4);
}
.coin-info strong { display: block; color: #fff; line-height: 1.1; font-weight: 800; }
.coin-info span { font-size: 0.55rem; color: #94a3b8; }
.coin-gain { margin-left: auto; color: #10b981; font-weight: 800; font-size: 0.62rem; }
.phone-action-btns {
    display: flex;
    gap: 6px;
    margin-top: 6px;
}
.phone-btn {
    flex: 1;
    padding: 6px;
    border-radius: 8px;
    border: 0;
    font-size: 0.62rem;
    font-weight: 800;
    color: #fff;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.phone-btn.long { background: #10b981; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3); }
.phone-btn.short { background: #ef4444; box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }

.install-modal-text {
    font-size: 0.9rem;
    color: #94a3b8;
    margin: 0 0 16px 0;
    line-height: 1.5;
    font-weight: 500;
}
.install-instructions-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.04) 0%, rgba(255, 255, 255, 0.01) 100%);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 16px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    gap: 14px;
    text-align: left;
    margin-bottom: 18px;
}
.dl-icon-box {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(246, 201, 63, 0.15);
    border: 1px solid rgba(246, 201, 63, 0.3);
    color: #f6c93f;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.install-instructions-card strong {
    display: block;
    font-size: 0.92rem;
    color: #fff;
    margin-bottom: 2px;
    font-weight: 700;
}
.install-instructions-card span {
    font-size: 0.78rem;
    color: #94a3b8;
    line-height: 1.3;
}
.install-buttons-stack {
    display: grid;
    gap: 12px;
}
.btn-main-install {
    width: 100%;
    padding: 14px;
    border-radius: 14px;
    background: linear-gradient(135deg, #f1c73a 0%, #c48704 100%) !important;
    color: #000000 !important;
    font-weight: 800 !important;
    font-size: 1.02rem !important;
    border: 0 !important;
    cursor: pointer !important;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    box-shadow: 0 10px 25px -5px rgba(236, 186, 77, 0.5) !important;
    transition: all 0.25s ease !important;
}
.btn-main-install:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px -5px rgba(168, 85, 247, 0.65) !important;
}
.store-buttons-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.store-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #ffffff;
    font-size: 0.88rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
}
.store-btn:hover {
    background: rgba(255, 255, 255, 0.09);
    border-color: rgba(255, 255, 255, 0.25);
    transform: translateY(-1px);
}
.btn-maybe-later {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    padding: 6px;
    margin-top: 2px;
    transition: color 0.2s ease;
}
.btn-maybe-later:hover {
    color: #ffffff;
    text-decoration: underline;
}

/* Light Theme Overrides for Broker & App Modals */
html[data-theme="light"] .kv-modal-backdrop,
html.theme-light .kv-modal-backdrop {
    background: rgba(15, 23, 42, 0.5) !important;
}

html[data-theme="light"] .kv-modal-card,
html[data-theme="light"] .broker-modal-card,
html[data-theme="light"] .install-app-modal-card,
html.theme-light .kv-modal-card,
html.theme-light .broker-modal-card,
html.theme-light .install-app-modal-card {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
    box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.18) !important;
}

html[data-theme="light"] .kv-modal-head h2,
html[data-theme="light"] .kv-modal-head h3,
html.theme-light .kv-modal-head h2,
html.theme-light .kv-modal-head h3 {
    color: #0f172a !important;
}

html[data-theme="light"] .kv-modal-sub,
html[data-theme="light"] .kv-modal-card p,
html[data-theme="light"] .install-app-modal-card p,
html.theme-light .kv-modal-sub,
html.theme-light .kv-modal-card p,
html.theme-light .install-app-modal-card p {
    color: #475569 !important;
}

html[data-theme="light"] .broker-option-card,
html[data-theme="light"] .broker-plan-card,
html[data-theme="light"] .install-step-item,
html.theme-light .broker-option-card,
html.theme-light .broker-plan-card,
html.theme-light .install-step-item {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    color: #0f172a !important;
}

html[data-theme="light"] .broker-option-card:hover,
html[data-theme="light"] .broker-plan-card:hover,
html.theme-light .broker-option-card:hover,
html.theme-light .broker-plan-card:hover {
    background: #f1f5f9 !important;
    border-color: #cbd5e1 !important;
}

html[data-theme="light"] .broker-option-card.active,
html[data-theme="light"] .broker-plan-card.active,
html.theme-light .broker-option-card.active,
html.theme-light .broker-plan-card.active {
    background: #f5f3ff !important;
    border-color: #7c3aed !important;
}

html[data-theme="light"] .broker-card-title,
html.theme-light .broker-card-title {
    color: #0f172a !important;
}

html[data-theme="light"] .store-btn,
html.theme-light .store-btn {
    background: #f1f5f9 !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
}

html[data-theme="light"] .store-btn:hover,
html.theme-light .store-btn:hover {
    background: #e2e8f0 !important;
}

html[data-theme="light"] .btn-maybe-later,
html.theme-light .btn-maybe-later {
    color: #64748b !important;
}

html[data-theme="light"] .btn-maybe-later:hover,
html.theme-light .btn-maybe-later:hover {
    color: #0f172a !important;
}
</style>

<style>
/* ==========================================================================
   CREATE NEW TRADING ACCOUNT WIZARD MODAL (MATCHING REFERENCE IMAGE)
   ========================================================================== */
.create-acc-wizard-modal {
    background: #0d111b !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
    border-radius: 22px !important;
    width: 95% !important;
    max-width: 1040px !important;
    max-height: min(92vh, 880px) !important;
    box-shadow: 0 30px 80px -10px rgba(0, 0, 0, 0.9), 0 0 50px rgba(124, 58, 237, 0.1) !important;
    display: flex !important;
    flex-direction: row !important;
    overflow: hidden !important;
    position: relative !important;
    color: #ffffff !important;
    font-family: 'Inter', system-ui, -apple-system, sans-serif !important;
    box-sizing: border-box !important;
    margin: auto !important;
}

/* Left Sidebar */
.caw-sidebar {
    width: 240px;
    flex-shrink: 0;
    background: rgba(10, 14, 23, 0.7);
    border-right: 1px solid rgba(255, 255, 255, 0.06);
    padding: 26px 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-sizing: border-box;
}

.caw-stepper {
    display: flex;
    flex-direction: column;
}

.caw-step {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    user-select: none;
    padding: 6px 8px;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.caw-step:hover {
    background: rgba(255, 255, 255, 0.03);
}

.caw-step-circle {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    color: #94a3b8;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.caw-step.is-active .caw-step-circle {
    background: #8b5cf6 !important;
    border-color: #8b5cf6 !important;
    color: #ffffff !important;
    box-shadow: 0 0 16px rgba(139, 92, 246, 0.6) !important;
}

.caw-step.is-completed .caw-step-circle {
    background: #1e1b4b;
    border: 1.5px solid #8b5cf6;
    color: #c084fc;
    box-shadow: 0 0 10px rgba(139, 92, 246, 0.25);
}

.caw-step-info {
    display: flex;
    flex-direction: column;
}

.caw-step-title {
    font-size: 0.84rem;
    font-weight: 700;
    color: #94a3b8;
    line-height: 1.2;
    transition: color 0.2s ease;
}

.caw-step.is-active .caw-step-title {
    color: #c084fc !important;
    font-weight: 800;
}

.caw-step.is-completed .caw-step-title {
    color: #e2e8f0;
}

.caw-step-sub {
    font-size: 0.68rem;
    color: #64748b;
    margin-top: 2px;
}

.caw-step-line {
    width: 2px;
    height: 24px;
    background: rgba(255, 255, 255, 0.08);
    margin-left: 13px;
    margin-top: 3px;
    margin-bottom: 3px;
    transition: background 0.2s ease;
}

.caw-step-line.is-active {
    background: #8b5cf6;
}

/* Promo Card: Trade Smarter */
.caw-promo-card {
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 16px;
    padding: 16px;
    margin-top: 20px;
}

.caw-promo-graphic {
    width: 100%;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 6px;
}

.caw-promo-title {
    display: block;
    font-size: 0.92rem;
    font-weight: 800;
    color: #ffffff;
    margin-bottom: 4px;
}

.caw-promo-desc {
    font-size: 0.72rem;
    color: #94a3b8;
    line-height: 1.4;
    margin: 0 0 12px 0;
}

.caw-promo-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.caw-promo-list li {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.72rem;
    color: #cbd5e1;
    font-weight: 600;
}

.caw-check-icon {
    width: 15px;
    height: 15px;
    border-radius: 50%;
    background: rgba(139, 92, 246, 0.2);
    color: #c084fc;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.62rem;
    font-weight: 900;
    flex-shrink: 0;
}

/* Right Main Area */
.caw-main {
    flex: 1;
    min-width: 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    max-height: 100% !important;
    padding: 26px 30px;
    background: #0d111c;
    box-sizing: border-box;
    overscroll-behavior: contain !important;
    -webkit-overflow-scrolling: touch !important;
    scrollbar-width: thin;
    scrollbar-color: rgba(139, 92, 246, 0.45) rgba(255, 255, 255, 0.04);
}

.caw-main::-webkit-scrollbar {
    width: 6px;
}
.caw-main::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.04);
    border-radius: 4px;
}
.caw-main::-webkit-scrollbar-thumb {
    background: rgba(139, 92, 246, 0.45);
    border-radius: 4px;
}
.caw-main::-webkit-scrollbar-thumb:hover {
    background: rgba(139, 92, 246, 0.75);
}

/* Header */
.caw-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 20px;
}

.caw-title {
    font-size: 1.35rem;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 4px 0;
    letter-spacing: -0.3px;
}

.caw-subtitle {
    font-size: 0.82rem;
    color: #94a3b8;
    margin: 0;
}

.caw-close-btn {
    background: transparent;
    border: none;
    color: #64748b;
    font-size: 1.5rem;
    cursor: pointer;
    line-height: 1;
    padding: 4px;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.caw-close-btn:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.08);
}

/* Sections */
.caw-section {
    margin-bottom: 20px;
}

.caw-sec-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 11px;
}

.caw-sec-badge {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    background: #8b5cf6;
    color: #ffffff;
    font-size: 0.75rem;
    font-weight: 800;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.caw-sec-title {
    font-size: 0.95rem;
    font-weight: 750;
    color: #ffffff;
    display: block;
    line-height: 1.2;
}

.caw-sec-sub {
    font-size: 0.72rem;
    color: #64748b;
    display: block;
}

/* Grids */
.caw-cards-grid {
    display: grid;
    gap: 12px;
}

.caw-grid-2 {
    grid-template-columns: repeat(2, 1fr);
}

.caw-grid-3 {
    grid-template-columns: repeat(3, 1fr);
}

.caw-grid-4 {
    grid-template-columns: repeat(4, 1fr);
}

@media (max-width: 1100px) {
    .caw-grid-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Cards (Account Type & Broker) */
.caw-card {
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 15px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-sizing: border-box;
}

.caw-card:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.16);
    transform: translateY(-1px);
}

.caw-card.is-selected {
    background: rgba(139, 92, 246, 0.06);
    border: 1.5px solid #8b5cf6;
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.15);
}

.caw-card-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 8px;
}

.caw-card-icon-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.caw-card-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.caw-icon-purple {
    background: rgba(139, 92, 246, 0.16);
    color: #a78bfa;
    border: 1px solid rgba(139, 92, 246, 0.3);
}

.caw-icon-slate {
    background: rgba(148, 163, 184, 0.12);
    color: #94a3b8;
    border: 1px solid rgba(148, 163, 184, 0.25);
}

.caw-icon-kryvex {
    background: linear-gradient(135deg, #7c3aed 0%, #a855f7 100%);
    color: #ffffff;
    font-weight: 900;
    font-size: 1.2rem;
    box-shadow: 0 4px 12px rgba(124, 58, 237, 0.35);
}

.caw-icon-mt5 {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.caw-card-name {
    font-size: 0.95rem;
    font-weight: 750;
    color: #ffffff;
    line-height: 1.2;
}

.caw-card-subtext {
    font-size: 0.72rem;
    color: #94a3b8;
    display: block;
    margin-top: 3px;
    line-height: 1.35;
}

.caw-tag-rec {
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #34d399;
    font-size: 0.65rem;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 9999px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

/* Radio Indicator */
.caw-radio {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 1.5px solid rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.caw-card.is-selected .caw-radio,
.caw-plan-card.is-selected .caw-radio,
.caw-px-card.is-selected .caw-radio,
.caw-radio.is-checked {
    border-color: #8b5cf6 !important;
    background: #8b5cf6 !important;
    box-shadow: inset 0 0 0 3px #0d111c !important;
}

.caw-card-desc {
    font-size: 0.75rem;
    color: #94a3b8;
    line-height: 1.4;
    margin: 4px 0 10px 0;
}

.caw-card-features {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.caw-card-features li {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    color: #cbd5e1;
}

.caw-bullet-check {
    width: 13px;
    height: 13px;
    border-radius: 50%;
    background: rgba(139, 92, 246, 0.2);
    color: #c084fc;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
    font-weight: 900;
    flex-shrink: 0;
}

/* Pills row for broker */
.caw-pills-row {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 12px;
}

.caw-pill {
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 9999px;
    padding: 3px 9px;
    font-size: 0.68rem;
    color: #94a3b8;
    font-weight: 500;
}

/* Plan Cards */
.caw-plan-card {
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 14px 16px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-sizing: border-box;
}

.caw-plan-card:hover {
    background: rgba(255, 255, 255, 0.04);
    border-color: rgba(255, 255, 255, 0.16);
    transform: translateY(-1px);
}

.caw-plan-card.is-selected {
    background: rgba(139, 92, 246, 0.06);
    border: 1.5px solid #8b5cf6;
    box-shadow: 0 0 20px rgba(139, 92, 246, 0.15);
}

.caw-plan-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.caw-plan-name-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
}

.caw-plan-mini-icon {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #8b5cf6;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.6rem;
}

.caw-plan-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: #ffffff;
}

.caw-plan-min {
    margin-bottom: 10px;
}

.caw-plan-lbl {
    font-size: 0.68rem;
    color: #94a3b8;
    display: block;
    margin-bottom: 2px;
}

.caw-plan-val {
    font-size: 1.15rem;
    font-weight: 900;
    color: #ffffff;
    letter-spacing: -0.2px;
}

.caw-plan-stats {
    font-size: 0.72rem;
    color: #94a3b8;
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding-top: 8px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.caw-stat-green {
    color: #34d399;
    font-weight: 700;
}

.caw-stat-gold {
    color: #fbbf24;
    font-weight: 700;
}

/* ─── PRIMEXBT STYLE PLAN CARDS (Matching Reference Image) ─── */
.caw-px-card {
    background: #101422;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 16px 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    box-sizing: border-box;
    position: relative;
}
.caw-px-card:hover {
    background: #141a2e;
    border-color: rgba(139, 92, 246, 0.45);
    transform: translateY(-2px);
}
.caw-px-card.is-selected {
    background: rgba(139, 92, 246, 0.08) !important;
    border: 1.5px solid #8b5cf6 !important;
    box-shadow: 0 0 24px rgba(139, 92, 246, 0.2) !important;
}

.caw-px-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 4px;
}
.caw-px-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.2px;
    margin: 0;
}
.caw-px-desc {
    font-size: 0.72rem;
    color: #94a3b8;
    line-height: 1.4;
    margin: 0 0 12px 0;
}

/* Instruments & Max Leverage Table */
.caw-px-table {
    width: 100%;
    margin-bottom: 12px;
}
.caw-px-th-row {
    display: flex;
    justify-content: space-between;
    font-size: 0.68rem;
    color: #94a3b8;
    font-weight: 600;
    padding-bottom: 5px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.caw-px-tr {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}
.caw-px-tr:last-child {
    border-bottom: none;
}
.caw-px-inst {
    font-size: 0.74rem;
    color: #cbd5e1;
    font-weight: 500;
}
.caw-px-lev {
    font-size: 0.76rem;
    color: #ffffff;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* Features & Bonuses */
.caw-px-sec-title {
    font-size: 0.7rem;
    color: #94a3b8;
    font-weight: 600;
    margin: 10px 0 5px 0;
    display: block;
}
.caw-px-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.caw-px-list li {
    display: flex;
    align-items: flex-start;
    gap: 6px;
    font-size: 0.73rem;
    color: #e2e8f0;
    line-height: 1.35;
}
.caw-px-check {
    color: #10b981;
    font-size: 0.8rem;
    font-weight: 900;
    line-height: 1.1;
    flex-shrink: 0;
}
.caw-px-bonus-text {
    color: #fbbf24;
    font-weight: 600;
}

/* Min Deposit Footer */
.caw-px-foot {
    margin-top: 12px;
    padding-top: 10px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
}
.caw-px-min-deposit {
    font-size: 0.78rem;
    font-weight: 750;
    color: #94a3b8;
    display: block;
}

/* Footer */
.caw-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
    padding-top: 18px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
}

.caw-btn-cancel {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 10px;
    padding: 10px 22px;
    color: #ffffff;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.caw-btn-cancel:hover {
    background: rgba(255, 255, 255, 0.09);
    border-color: rgba(255, 255, 255, 0.2);
}

.caw-btn-next {
    background: linear-gradient(135deg, #7c3aed 0%, #9333ea 100%);
    border: none;
    border-radius: 10px;
    padding: 10px 24px;
    color: #ffffff;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 16px rgba(124, 58, 237, 0.4);
    transition: all 0.2s ease;
}

.caw-btn-next:hover {
    background: linear-gradient(135deg, #6d28d9 0%, #7e22ce 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(124, 58, 237, 0.55);
}

.caw-step-count {
    font-size: 0.78rem;
    color: #64748b;
    font-weight: 600;
}

/* Review View Elements */
.caw-review-card {
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 18px;
    margin-bottom: 20px;
}

.caw-review-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    font-size: 0.85rem;
}

.caw-review-item:last-child {
    border-bottom: none;
}

.caw-review-lbl {
    color: #94a3b8;
}

.caw-review-val {
    font-weight: 700;
    color: #ffffff;
}

/* Step 1 Environment Segmented Toggle */
.caw-env-toggle-wrap {
    display: flex;
    background: rgba(255, 255, 255, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 4px;
    gap: 4px;
    margin-bottom: 16px;
    width: 100%;
    box-sizing: border-box;
}

.caw-env-toggle-pill {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 16px;
    border-radius: 9px;
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 0.88rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
}

.caw-env-toggle-pill:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, 0.04);
}

.caw-env-toggle-pill.is-active {
    background: #8b5cf6 !important;
    color: #ffffff !important;
    box-shadow: 0 4px 14px rgba(139, 92, 246, 0.45) !important;
}

/* Plan Bonus Badge & Feature List */
.caw-plan-bonus-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: rgba(245, 158, 11, 0.14);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #fef08a;
    font-size: 0.72rem;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 6px;
    margin-bottom: 8px;
}

.caw-plan-features-list {
    list-style: none;
    padding: 0;
    margin: 10px 0 0 0;
    display: flex;
    flex-direction: column;
    gap: 5px;
    border-top: 1px solid rgba(255, 255, 255, 0.06);
    padding-top: 8px;
}

.caw-plan-features-list li {
    font-size: 0.72rem;
    color: #cbd5e1;
    display: flex;
    align-items: center;
    gap: 6px;
}

/* Light Theme Overrides */
html[data-theme="light"] .create-acc-wizard-modal,
html.theme-light .create-acc-wizard-modal {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    color: #0f172a !important;
    box-shadow: 0 25px 60px -10px rgba(15, 23, 42, 0.2) !important;
}

html[data-theme="light"] .caw-sidebar,
html.theme-light .caw-sidebar {
    background: #f8fafc;
    border-color: #e2e8f0;
}

html[data-theme="light"] .caw-main,
html.theme-light .caw-main {
    background: #ffffff;
}

html[data-theme="light"] .caw-title,
html[data-theme="light"] .caw-sec-title,
html[data-theme="light"] .caw-card-name,
html[data-theme="light"] .caw-plan-title,
html[data-theme="light"] .caw-plan-val,
html[data-theme="light"] .caw-promo-title,
html.theme-light .caw-title,
html.theme-light .caw-sec-title,
html.theme-light .caw-card-name,
html.theme-light .caw-plan-title,
html.theme-light .caw-plan-val,
html.theme-light .caw-promo-title {
    color: #0f172a !important;
}

html[data-theme="light"] .caw-card,
html[data-theme="light"] .caw-plan-card,
html[data-theme="light"] .caw-promo-card,
html.theme-light .caw-card,
html.theme-light .caw-plan-card,
html.theme-light .caw-promo-card {
    background: #f8fafc;
    border-color: #e2e8f0;
}

html[data-theme="light"] .caw-btn-cancel,
html.theme-light .caw-btn-cancel {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #334155;
}

html[data-theme="light"] .caw-env-toggle-wrap,
html.theme-light .caw-env-toggle-wrap {
    background: #f1f5f9;
    border-color: #cbd5e1;
}

html[data-theme="light"] .caw-env-toggle-pill,
html.theme-light .caw-env-toggle-pill {
    color: #475569;
}

html[data-theme="light"] .caw-env-toggle-pill.is-active,
html.theme-light .caw-env-toggle-pill.is-active {
    background: #7c3aed !important;
    color: #ffffff !important;
}

html[data-theme="light"] .caw-plan-bonus-pill,
html.theme-light .caw-plan-bonus-pill {
    background: #fef3c7;
    border-color: #fde68a;
    color: #92400e;
}

html[data-theme="light"] .caw-plan-features-list li,
html.theme-light .caw-plan-features-list li {
    color: #334155;
}

html[data-theme="light"] .caw-px-card,
html.theme-light .caw-px-card {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
}
html[data-theme="light"] .caw-px-card:hover,
html.theme-light .caw-px-card:hover {
    background: #f8fafc !important;
    border-color: #c084fc !important;
}
html[data-theme="light"] .caw-px-card.is-selected,
html.theme-light .caw-px-card.is-selected {
    background: #f5f3ff !important;
    border-color: #8b5cf6 !important;
}
html[data-theme="light"] .caw-px-title,
html.theme-light .caw-px-title {
    color: #0f172a !important;
}
html[data-theme="light"] .caw-px-desc,
html.theme-light .caw-px-desc {
    color: #64748b !important;
}
html[data-theme="light"] .caw-px-th-row,
html.theme-light .caw-px-th-row {
    color: #94a3b8 !important;
    border-bottom-color: #e2e8f0 !important;
}
html[data-theme="light"] .caw-px-tr,
html.theme-light .caw-px-tr {
    border-bottom-color: #f1f5f9 !important;
}
html[data-theme="light"] .caw-px-inst,
html.theme-light .caw-px-inst {
    color: #334155 !important;
}
html[data-theme="light"] .caw-px-lev,
html.theme-light .caw-px-lev {
    color: #0f172a !important;
}
html[data-theme="light"] .caw-px-sec-title,
html.theme-light .caw-px-sec-title {
    color: #64748b !important;
}
html[data-theme="light"] .caw-px-list li,
html.theme-light .caw-px-list li {
    color: #1e293b !important;
}
html[data-theme="light"] .caw-px-foot,
html.theme-light .caw-px-foot {
    border-top-color: #e2e8f0 !important;
}
html[data-theme="light"] .caw-px-min-deposit,
html.theme-light .caw-px-min-deposit {
    color: #64748b !important;
}

html[data-theme="light"] .caw-card.is-selected .caw-radio,
html[data-theme="light"] .caw-plan-card.is-selected .caw-radio,
html[data-theme="light"] .caw-px-card.is-selected .caw-radio,
html[data-theme="light"] .caw-radio.is-checked,
html.theme-light .caw-card.is-selected .caw-radio,
html.theme-light .caw-plan-card.is-selected .caw-radio,
html.theme-light .caw-px-card.is-selected .caw-radio,
html.theme-light .caw-radio.is-checked {
    border-color: #8b5cf6 !important;
    background: #8b5cf6 !important;
    box-shadow: inset 0 0 0 3px #ffffff !important;
}

/* ─── PRIMEXBT STYLE INFO BANNER (Matching Reference Image) ─── */
.caw-info-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #0d1726;
    border: 1px solid rgba(59, 130, 246, 0.35);
    border-radius: 12px;
    padding: 14px 18px;
    margin-top: 16px;
    box-sizing: border-box;
    width: 100%;
    animation: cawFadeIn 0.25s ease forwards;
}

@keyframes cawFadeIn {
    from { opacity: 0; transform: translateY(-4px); }
    to { opacity: 1; transform: translateY(0); }
}

.caw-info-banner-icon {
    width: 22px;
    height: 22px;
    color: #38bdf8;
    flex-shrink: 0;
}

.caw-info-banner-text {
    font-size: 0.9rem;
    font-weight: 600;
    color: #f1f5f9;
    line-height: 1.45;
}

html[data-theme="light"] .caw-info-banner,
html.theme-light .caw-info-banner {
    background: #eff6ff;
    border-color: #bfdbfe;
}

html[data-theme="light"] .caw-info-banner-text,
html.theme-light .caw-info-banner-text {
    color: #1e3a8a;
}

/* ─── STEP 2 MINI LIVE / DEMO SWITCH TOGGLE (Compact, No Extra Details) ─── */
.caw-mini-switch-wrap {
    display: inline-flex;
    align-items: center;
    background: #0d121f;
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 9999px;
    padding: 3px;
    gap: 3px;
    box-sizing: border-box;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.25);
}

.caw-mini-switch-btn {
    border: none;
    outline: none;
    background: transparent;
    color: #94a3b8;
    font-size: 0.76rem;
    font-weight: 750;
    padding: 5px 14px;
    border-radius: 9999px;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    line-height: 1.2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.caw-mini-switch-btn:hover {
    color: #ffffff;
}

.caw-mini-switch-btn.is-active[data-caw-mini-env="live"],
.caw-mini-switch-btn.is-active:first-child {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 10px rgba(16, 185, 129, 0.4) !important;
}

.caw-mini-switch-btn.is-active[data-caw-mini-env="demo"],
.caw-mini-switch-btn.is-active:last-child {
    background: linear-gradient(135deg, #7c3aed 0%, #9333ea 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 2px 10px rgba(124, 58, 237, 0.45) !important;
}

html[data-theme="light"] .caw-mini-switch-wrap,
html.theme-light .caw-mini-switch-wrap {
    background: #e2e8f0;
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
}

html[data-theme="light"] .caw-mini-switch-btn,
html.theme-light .caw-mini-switch-btn {
    color: #64748b;
}

html[data-theme="light"] .caw-mini-switch-btn:hover,
html.theme-light .caw-mini-switch-btn:hover {
    color: #0f172a;
}

/* Mobile Responsiveness */
@media (max-width: 860px) {
    .create-acc-wizard-modal {
        flex-direction: column !important;
        max-height: 94vh !important;
    }
    .caw-sidebar {
        width: 100%;
        border-right: none;
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        padding: 16px 20px;
    }
    .caw-stepper {
        flex-direction: row;
        justify-content: space-between;
    }
    .caw-step-line {
        display: none;
    }
    .caw-promo-card {
        display: none;
    }
    .caw-grid-2, .caw-grid-3, .caw-grid-4 {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Broker & MT5 Selection Modals -->
<div class="kv-modal-backdrop" id="brokerModalBackdrop" data-lenis-prevent="true" data-no-lenis="true" onclick="if(event.target===this && typeof window.closeBrokerModals==='function') window.closeBrokerModals();" hidden>
    
    <!-- Unified 4-Step Create New Trading Account Wizard Modal (Matching User Reference Image) -->
    <div class="create-acc-wizard-modal" id="modalChooseBroker" data-lenis-prevent="true" data-no-lenis="true" hidden>
        
        <!-- LEFT SIDEBAR -->
        <div class="caw-sidebar" data-lenis-prevent="true" data-no-lenis="true">
            <!-- Stepper -->
            <!-- Stepper (3 Steps: Account Type -> Broker & Plan -> Review) -->
            <div class="caw-stepper">
                <!-- Step 1: Account Type -->
                <div class="caw-step is-active" data-caw-step="1" id="cawStepNav1" onclick="if(typeof window.goToCawStep==='function') window.goToCawStep(1);">
                    <div class="caw-step-circle">1</div>
                    <div class="caw-step-info">
                        <span class="caw-step-title">Account Type</span>
                        <span class="caw-step-sub">Live or Demo</span>
                    </div>
                </div>
                <div class="caw-step-line is-active" id="cawStepLine1"></div>

                <!-- Step 2: Broker & Plan -->
                <div class="caw-step" data-caw-step="2" id="cawStepNav2" onclick="if(typeof window.goToCawStep==='function') window.goToCawStep(2);">
                    <div class="caw-step-circle">2</div>
                    <div class="caw-step-info">
                        <span class="caw-step-title">Broker & Plan</span>
                        <span class="caw-step-sub">Platform & features</span>
                    </div>
                </div>
                <div class="caw-step-line" id="cawStepLine2"></div>

                <!-- Step 3: Review -->
                <div class="caw-step" data-caw-step="3" id="cawStepNav3" onclick="if(typeof window.goToCawStep==='function') window.goToCawStep(3);">
                    <div class="caw-step-circle">3</div>
                    <div class="caw-step-info">
                        <span class="caw-step-title">Review</span>
                        <span class="caw-step-sub">Confirm details</span>
                    </div>
                </div>
            </div>

            <!-- Promo Card: Trade Smarter -->
            <div class="caw-promo-card">
                <div class="caw-promo-graphic">
                    <svg width="68" height="68" viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M36 8L60 22V50L36 64L12 50V22L36 8Z" fill="#131828" stroke="rgba(139, 92, 246, 0.4)" stroke-width="1.5"/>
                        <path d="M36 8L60 22L36 36L12 22L36 8Z" fill="rgba(139, 92, 246, 0.2)"/>
                        <path d="M36 36V64" stroke="rgba(139, 92, 246, 0.3)" stroke-width="1.5"/>
                        <rect x="22" y="28" width="4" height="14" rx="2" fill="#8b5cf6"/>
                        <rect x="30" y="22" width="4" height="20" rx="2" fill="#a855f7"/>
                        <rect x="38" y="18" width="4" height="24" rx="2" fill="#38bdf8"/>
                        <rect x="46" y="26" width="4" height="16" rx="2" fill="#34d399"/>
                        <path d="M24 30L32 24L40 20L48 28" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <strong class="caw-promo-title">Trade Smarter</strong>
                <p class="caw-promo-desc">Access global markets with powerful platforms and low spreads.</p>
                <ul class="caw-promo-list">
                    <li>
                        <span class="caw-check-icon">✓</span>
                        <span>Secure & Reliable</span>
                    </li>
                    <li>
                        <span class="caw-check-icon">✓</span>
                        <span>Ultra Low Spreads</span>
                    </li>
                    <li>
                        <span class="caw-check-icon">✓</span>
                        <span>24/7 Support</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- RIGHT MAIN CONTENT -->
        <div class="caw-main" data-lenis-prevent="true" data-no-lenis="true">
            <!-- Header -->
            <div class="caw-header">
                <div>
                    <h2 class="caw-title" id="cawModalTitle">Create New Trading Account</h2>
                    <p class="caw-subtitle" id="cawModalSubtitle">Choose your environment, broker and plan to get started.</p>
                </div>
                <button type="button" class="caw-close-btn" data-skip-broker-modal aria-label="Close" onclick="if(typeof window.closeBrokerModals==='function') window.closeBrokerModals();">&times;</button>
            </div>

            <!-- STEP 1: Account Type (Live / Demo Toggle & Cards) -->
            <div class="caw-view" id="cawStepView1">
                <div class="caw-section" id="cawSection1" style="margin-bottom: 0;">
                    <div class="caw-sec-head">
                        <span class="caw-sec-badge">1</span>
                        <div>
                            <strong class="caw-sec-title">Account Type</strong>
                            <span class="caw-sec-sub">Select whether you want to trade with real funds (Live) or virtual funds (Demo)</span>
                        </div>
                    </div>

                    <!-- Live / Demo Segmented Toggle Pill Bar (User Requested) -->
                    <div class="caw-env-toggle-wrap">
                        <button type="button" class="caw-env-toggle-pill is-active" id="btnToggleEnvLive" data-caw-env-btn="live" onclick="if(typeof window.setCawAccountType==='function') window.setCawAccountType('live');">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 21h18M3 10h18M5 10v11M9 10v11M15 10v11M19 10v11M12 3l9 7H3z"/>
                            </svg>
                            <span>Live Account</span>
                            <span class="caw-tag-rec" style="font-size: 0.65rem; padding: 2px 7px; margin-left: 4px;">Recommended</span>
                        </button>
                        <button type="button" class="caw-env-toggle-pill" id="btnToggleEnvDemo" data-caw-env-btn="demo" onclick="if(typeof window.setCawAccountType==='function') window.setCawAccountType('demo');">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M10 2v7.31L4.1 19.43A2 2 0 0 0 5.8 22h12.4a2 2 0 0 0 1.7-2.57L14 9.31V2M8.5 2h7M14 9H10"/>
                            </svg>
                            <span>Demo Account</span>
                        </button>
                    </div>

                    <!-- Live & Demo Cards -->
                    <div class="caw-cards-grid caw-grid-2">
                        <!-- Live Account -->
                        <div class="caw-card is-selected" data-caw-env="live" id="cawCardEnvLive" onclick="if(typeof window.setCawAccountType==='function') window.setCawAccountType('live');">
                            <div class="caw-card-top">
                                <div class="caw-card-icon-title">
                                    <div class="caw-card-icon caw-icon-purple">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M3 21h18M3 10h18M5 10v11M9 10v11M15 10v11M19 10v11M12 3l9 7H3z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <strong class="caw-card-name">Live Account</strong>
                                            <span class="caw-tag-rec">Real Trading</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="caw-radio is-checked"></div>
                            </div>
                            <p class="caw-card-desc">Trade with real funds, instant order execution and live order books.</p>
                            <ul class="caw-card-features">
                                <li><span class="caw-bullet-check">✓</span> Real market prices & live orders</li>
                                <li><span class="caw-bullet-check">✓</span> Real profits & instant withdrawals</li>
                                <li><span class="caw-bullet-check">✓</span> Full platform features & deposit bonus</li>
                            </ul>
                        </div>

                        <!-- Demo Account -->
                        <div class="caw-card" data-caw-env="demo" id="cawCardEnvDemo" onclick="if(typeof window.setCawAccountType==='function') window.setCawAccountType('demo');">
                            <div class="caw-card-top">
                                <div class="caw-card-icon-title">
                                    <div class="caw-card-icon caw-icon-slate">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M10 2v7.31L4.1 19.43A2 2 0 0 0 5.8 22h12.4a2 2 0 0 0 1.7-2.57L14 9.31V2M8.5 2h7M14 9H10"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <strong class="caw-card-name">Demo Account</strong>
                                    </div>
                                </div>
                                <div class="caw-radio"></div>
                            </div>
                            <p class="caw-card-desc">Practice with virtual funds in a risk-free simulator environment.</p>
                            <ul class="caw-card-features">
                                <li><span class="caw-bullet-check">✓</span> $50,000 virtual balance to practice</li>
                                <li><span class="caw-bullet-check">✓</span> Real-time market conditions</li>
                                <li><span class="caw-bullet-check">✓</span> Zero risk, perfect for beginners</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Select Broker & Choose Plan (Combined as in PrimeXBT) -->
            <div class="caw-view" id="cawStepView2" style="display: none;">
                <!-- Part 1: Select Platform / Broker -->
                <div class="caw-section" id="cawSection2" style="margin-bottom: 22px;">
                    <div class="caw-sec-head" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="caw-sec-badge">1</span>
                            <div>
                                <strong class="caw-sec-title">Select Broker</strong>
                                <span class="caw-sec-sub">Choose your preferred trading platform</span>
                            </div>
                        </div>

                        <!-- Mini Live / Demo switch toggle (Compact pill, no extra details) -->
                        <div class="caw-mini-switch-wrap">
                            <button type="button" class="caw-mini-switch-btn is-active" id="cawStep2SwitchLive" data-caw-mini-env="live" onclick="if(typeof window.setCawAccountType==='function') window.setCawAccountType('live');">
                                Live
                            </button>
                            <button type="button" class="caw-mini-switch-btn" id="cawStep2SwitchDemo" data-caw-mini-env="demo" onclick="if(typeof window.setCawAccountType==='function') window.setCawAccountType('demo');">
                                Demo
                            </button>
                        </div>
                    </div>

                    <div class="caw-cards-grid caw-grid-2">
                        <!-- Kryvex Exchange (Selected by default) -->
                        <div class="caw-card is-selected" data-caw-broker="kryvex" id="cawCardBrokerKryvex" onclick="if(typeof window.setCawBrokerChoice==='function') window.setCawBrokerChoice('kryvex');">
                            <div class="caw-card-top">
                                <div class="caw-card-icon-title">
                                    <div class="caw-card-icon caw-icon-kryvex">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                            <path d="M6 4v16M6 12l9-7M6 12l9 7" stroke="#ffffff" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <strong class="caw-card-name">Kryvex Exchange</strong>
                                        <span class="caw-card-subtext">Trade futures & options directly on Kryvex web and mobile.</span>
                                    </div>
                                </div>
                                <div class="caw-radio is-checked"></div>
                            </div>
                            <div class="caw-pills-row">
                                <span class="caw-pill">Low Fees</span>
                                <span class="caw-pill">High Liquidity</span>
                                <span class="caw-pill">Advanced Tools</span>
                            </div>
                        </div>

                        <!-- MetaTrader 5 -->
                        <div class="caw-card" data-caw-broker="mt5" id="cawCardBrokerMt5" onclick="if(typeof window.setCawBrokerChoice==='function') window.setCawBrokerChoice('mt5');">
                            <div class="caw-card-top">
                                <div class="caw-card-icon-title">
                                    <div class="caw-card-icon caw-icon-mt5">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                            <circle cx="12" cy="7.5" r="3.5" fill="#10b981"/>
                                            <circle cx="7.5" cy="15.5" r="3" fill="#3b82f6"/>
                                            <circle cx="16.5" cy="15.5" r="3" fill="#f59e0b"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <strong class="caw-card-name">MetaTrader 5</strong>
                                        <span class="caw-card-subtext">Connect and trade via the industry standard MetaTrader 5.</span>
                                    </div>
                                </div>
                                <div class="caw-radio"></div>
                            </div>
                            <div class="caw-pills-row">
                                <span class="caw-pill">Global Platform</span>
                                <span class="caw-pill">Wide Support</span>
                                <span class="caw-pill">Expert Advisors</span>
                            </div>
                        </div>
                    </div>

                    <!-- Demo Account Limit Reached Banner in Step 2 -->
                    <div class="caw-info-banner" id="cawDemoLimitBannerStep2" style="display: none;">
                        <svg class="caw-info-banner-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <span class="caw-info-banner-text">All available accounts have been created.</span>
                    </div>
                </div>

                <!-- Part 2: Choose Plan (Directly below Broker, dynamically updated based on selected broker) -->
                <div class="caw-section" id="cawSection3" style="margin-bottom: 0; padding-top: 18px; border-top: 1px solid rgba(255,255,255,0.06);">
                    <div class="caw-sec-head">
                        <span class="caw-sec-badge">2</span>
                        <div>
                            <strong class="caw-sec-title">Choose Plan</strong>
                            <span class="caw-sec-sub" id="cawPlanSubtitle">Select a plan that matches your trading needs</span>
                        </div>
                    </div>

                    <!-- Dynamic Plan Cards Container (Separate plans for Kryvex vs MT5) -->
                    <div class="caw-cards-grid caw-grid-3" id="cawPlansContainer">
                        <!-- Populated by JavaScript based on chosen broker -->
                    </div>
                </div>
            </div>

            <!-- STEP 3: Review & Confirm Details -->
            <div class="caw-view" id="cawStepView3" style="display: none;">
                <div class="caw-section" id="cawSection4" style="margin-bottom: 0;">
                    <div class="caw-sec-head">
                        <span class="caw-sec-badge">3</span>
                        <div>
                            <strong class="caw-sec-title">Review Details</strong>
                            <span class="caw-sec-sub">Confirm your account configuration and set a custom name</span>
                        </div>
                    </div>

                    <!-- Summary Card -->
                    <div class="caw-review-card">
                        <div class="caw-review-item">
                            <span class="caw-review-lbl">Account Environment</span>
                            <strong class="caw-review-val" id="cawReviewEnv">Live Account</strong>
                        </div>
                        <div class="caw-review-item">
                            <span class="caw-review-lbl">Trading Broker / Platform</span>
                            <strong class="caw-review-val" id="cawReviewBroker">Kryvex Exchange</strong>
                        </div>
                        <div class="caw-review-item">
                            <span class="caw-review-lbl">Selected Plan</span>
                            <strong class="caw-review-val" id="cawReviewPlan">Standard Plan</strong>
                        </div>
                        <div class="caw-review-item">
                            <span class="caw-review-lbl">Plan Specifications</span>
                            <strong class="caw-review-val" id="cawReviewSpecs" style="color: #34d399;">Min $50,000 · 1:300 Leverage · 0.03% Spread</strong>
                        </div>
                        <div class="caw-review-item">
                            <span class="caw-review-lbl">Deposit Bonus</span>
                            <strong class="caw-review-val" id="cawReviewBonus" style="color: #fbbf24;">+50% Bonus</strong>
                        </div>
                    </div>

                    <!-- Account Name Input -->
                    <div style="margin-top: 18px; margin-bottom: 8px;">
                        <label for="cawAccountNameInput" style="display: block; font-size: 0.85rem; color: #cbd5e1; font-weight: 700; margin-bottom: 8px;">
                            Account Name <span style="color: #64748b; font-weight: 400;">(Optional)</span>
                        </label>
                        <input type="text" id="cawAccountNameInput" class="form-control" placeholder="e.g. Scalping Acc, Main Trading, MT5 Swing" maxlength="40" style="width: 100%; height: 44px; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.15); border-radius: 12px; color: #ffffff; padding: 0 16px; font-size: 0.95rem; box-sizing: border-box; outline: none; transition: border-color 0.2s;">
                    </div>
                </div>
            </div>

            <!-- UNIFIED MODAL FOOTER -->
            <div class="caw-footer">
                <button type="button" class="caw-btn-cancel" id="cawBtnFooterBack" onclick="if(typeof window.handleCawFooterBack==='function'){window.handleCawFooterBack();}else if(typeof window.closeBrokerModals==='function'){window.closeBrokerModals();}">Cancel</button>
                <div style="display:flex;align-items:center;gap:16px;">
                    <button type="button" class="caw-btn-next" id="cawBtnFooterNext" onclick="if(typeof window.handleCawFooterNext==='function'){window.handleCawFooterNext();}else if(typeof window.goToCawStep==='function'){window.goToCawStep(2);}">
                        <span id="cawBtnNextLabel">Next Step</span>
                        <svg id="cawBtnNextIcon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </button>
                    <span class="caw-step-count" id="cawStepCountText">Step 1 of 3</span>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal: MetaTrader 5 Account Requested Confirmation -->
    <div class="kv-modal-card broker-modal-card" id="modalMt5Requested" hidden style="background: #0f131f; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); max-width: 480px; padding: 24px;">
        <div class="kv-modal-head" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
            <h2 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #ffffff;">MetaTrader 5 account requested</h2>
            <button type="button" class="kv-modal-close" data-close-broker-modal aria-label="Close" onclick="if(typeof window.closeBrokerModals==='function') window.closeBrokerModals();">&times;</button>
        </div>

        <div class="mt5-requested-notice-card" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; margin-bottom: 18px; font-size: 0.88rem; line-height: 1.5; color: #cbd5e1;">
            <p style="margin: 0 0 8px 0;">You selected <strong id="reqModalPlanName" style="color: #a855f7;">Standard</strong> (USD, Swap-free).</p>
            <p style="margin: 0 0 8px 0;">Your <strong>MetaTrader 5 ID</strong> and <strong>password</strong> will be sent to your registered email.</p>
            <p style="margin: 0;">Use that ID and password to log in to MetaTrader 5 and start trading.</p>
        </div>

        <button type="button" class="caw-btn-next" data-close-broker-modal onclick="if(typeof window.closeBrokerModals==='function') window.closeBrokerModals();" style="width: 100%; justify-content: center;">
            Got it
        </button>
    </div>
</div>

<!-- Modal 4: Download the Kryvex App (Ultra Premium UI) -->
<div class="kv-modal-backdrop" id="installAppModalBackdrop" hidden>
    <div class="kv-modal-card install-app-modal-card">
        <div class="kv-modal-head">
            <div class="modal-head-title-wrap">
                <div class="modal-icon-badge phone-badge">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="5" y="2" width="14" height="20" rx="3"/>
                        <path d="M12 18h.01"/>
                    </svg>
                </div>
                <div>
                    <span class="wizard-step-pill" style="font-size: 0.75rem; font-weight: 700; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 2px;">Step 3 / 3 · Mobile App</span>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <h2 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #fff;">Install Mobile App</h2>
                        <span style="background: rgba(234, 179, 8, 0.18); border: 1px solid rgba(234, 179, 8, 0.45); color: #fef08a; font-size: 0.68rem; font-weight: 850; padding: 2px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px;">Coming Soon</span>
                    </div>
                </div>
            </div>
            <button type="button" class="kv-modal-close" data-close-install-modal onclick="window.closeInstallAppModal && window.closeInstallAppModal(); return false;">&times;</button>
        </div>

        <!-- PHONE PREVIEW GRAPHIC (Ultra Sleek Mockup) -->
        <div class="phone-mockup-wrapper">
            <div class="phone-frame">
                <div class="phone-notch">
                    <div class="phone-notch-camera"></div>
                </div>
                <div class="phone-screen">
                    <div class="phone-header">
                        <span>9:41</span>
                        <span class="brand">KRYVEX</span>
                        <span class="bell">🔔</span>
                    </div>
                    <div class="phone-title">Portfolio</div>
                    <div class="phone-balance-card">
                        <div class="bal-label">Total Balance</div>
                        <div class="bal-val">$12,480.50</div>
                        <div class="bal-change">+ $842.30 (24h)</div>
                    </div>
                    
                    <div class="phone-chart-area">
                        <svg viewBox="0 0 160 36" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#10b981" stop-opacity="0.4"/>
                                    <stop offset="100%" stop-color="#10b981" stop-opacity="0"/>
                                </linearGradient>
                            </defs>
                            <path d="M0,28 Q30,10 60,20 T120,8 T160,4 L160,36 L0,36 Z" fill="url(#chartGrad)"/>
                            <path d="M0,28 Q30,10 60,20 T120,8 T160,4" fill="none" stroke="#10b981" stroke-width="2"/>
                        </svg>
                    </div>

                    <div class="phone-coin-row">
                        <div class="coin-badge">₿</div>
                        <div class="coin-info">
                            <strong>BTC/USDT</strong>
                            <span>Perpetual</span>
                        </div>
                        <span class="coin-gain">+2.41%</span>
                    </div>
                    <div class="phone-action-btns">
                        <button class="phone-btn long">Long</button>
                        <button class="phone-btn short">Short</button>
                    </div>
                </div>
            </div>
        </div>

        <p class="install-modal-text">
            Your broker setup is complete. Install the Kryvex app for faster trading on your phone.
        </p>

        <div class="install-instructions-card">
            <div class="dl-icon-box">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 6px;">
                    <strong style="font-size: 0.92rem; color: #fff;">Get Kryvex on your phone</strong>
                    <span style="background: #eab308; color: #000; font-size: 0.65rem; font-weight: 900; padding: 1px 6px; border-radius: 4px; text-transform: uppercase;">Coming Soon</span>
                </div>
                <span style="color: #cbd5e1; font-size: 0.8rem; display: block; margin-top: 2px;">Kryvex Android & iOS Mobile apps are launching soon!</span>
            </div>
        </div>

        <div class="install-buttons-stack">
            <button type="button" class="kv-btn btn-main-install" id="btnMainInstall" onclick="alert('Kryvex Mobile App is Coming Soon on Google Play & App Store!');">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Install (Coming Soon)
            </button>

            <div class="store-buttons-row">
                <a href="#" class="store-btn" onclick="alert('Google Play App is Coming Soon!'); return false;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M3.609 1.814L13.792 12 3.61 22.186a1.99 1.99 0 0 1-.61-1.428V3.242c0-.55.225-1.05.609-1.428zm11.604 8.765l2.457-1.423a1.5 1.5 0 0 1 0 2.588l-2.457-1.165z"/>
                    </svg>
                    Google Play
                </a>
                <a href="#" class="store-btn" onclick="alert('App Store App is Coming Soon!'); return false;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.32c.67-.82 1.13-1.96.99-3.1-.98.04-2.16.66-2.86 1.47-.62.72-1.17 1.88-1.02 3 .01 0 .03 0 .04 0 1.1 0 2.21-.6 2.85-1.37"/>
                    </svg>
                    App Store
                </a>
            </div>

            <button type="button" class="btn-maybe-later" data-close-install-modal onclick="window.closeInstallAppModal && window.closeInstallAppModal(); return false;">
                Maybe later
            </button>
        </div>
    </div>
</div>

<!-- Modal 5: Low Balance Plan Downgrade Warning -->
<div class="kv-modal-backdrop" id="modalLowBalanceWarning" hidden style="display: none; position: fixed; inset: 0; z-index: 10000; background: rgba(0, 0, 0, 0.75); backdrop-filter: blur(8px); padding: 20px;">
    <div class="kv-modal-card" style="max-width: 480px; width: 100%; background: #121824; border: 1px solid rgba(239, 68, 68, 0.35); border-radius: 16px; padding: 26px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); position: relative; color: #fff; margin: auto;">
        <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 16px;">
            <div style="width: 46px; height: 46px; border-radius: 12px; background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #ef4444; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 1.4rem;">
                ⚠️
            </div>
            <div>
                <span style="font-size: 0.75rem; font-weight: 800; color: #ef4444; text-transform: uppercase; letter-spacing: 0.5px;">LOW BALANCE WARNING</span>
                <h3 style="margin: 2px 0 0; font-size: 1.18rem; font-weight: 800; color: #fff;" id="lowBalWarningTitle">Plan Shifted</h3>
            </div>
        </div>

        <p style="font-size: 0.9rem; color: #cbd5e1; line-height: 1.6; margin: 0 0 20px; background: rgba(255,255,255,0.03); padding: 14px 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.06);" id="lowBalWarningMsg">
            Your main account balance has fallen below the required minimum threshold for your plan. Your account has been automatically shifted to the lower plan.
        </p>

        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 12px;">
            <button type="button" class="kv-btn is-outline" id="btnDismissLowBalWarning" onclick="if(window.dismissLowBalWarning){window.dismissLowBalWarning();}else{const m=document.getElementById('modalLowBalanceWarning'); if(m){m.hidden=true; m.style.display='none';}}" style="border: 1px solid rgba(255,255,255,0.2); color: #cbd5e1; background: transparent; padding: 10px 18px; border-radius: 8px; font-weight: 700; cursor: pointer;">
                Got It
            </button>
            <button type="button" class="kv-btn kv-btn-primary" id="btnDepositLowBalWarning" onclick="const b=document.getElementById('brokerModalBackdrop'); if(b){b.hidden=true; b.style.display='none';} window.location.href='/wallet?tab=deposit';" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); color: #ffffff; padding: 10px 20px; border-radius: 8px; font-weight: 800; border: none; cursor: pointer;">
                Deposit Funds 💳
            </button>
        </div>
    </div>
</div>

<!-- Modal 6: Deposit Funds Popup Modal (EXACT WALLET PAGE MODAL) -->
<div class="dharma-modal-backdrop" id="depositModalBackdrop" hidden style="display: none; position: fixed; inset: 0; z-index: 100001; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(8px); padding: 16px; overflow-y: auto; -webkit-overflow-scrolling: touch; box-sizing: border-box;">
    <div class="dharma-modal-card" style="background: #121318; border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 24px; width: 100%; max-width: 540px; max-height: min(90vh, calc(100dvh - 28px)); overflow-y: auto; padding: 28px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); color: #fff; margin: auto; box-sizing: border-box;">
        <div class="dharma-modal-head" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; padding-bottom: 14px; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
            <h3 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #ffffff;">Deposit Funds</h3>
            <button type="button" class="dharma-modal-close" id="closeDepositModalBtn" onclick="const m=document.getElementById('depositModalBackdrop'); if(m){m.hidden=true; m.style.display='none';}" style="background: none; border: none; color: rgba(255, 255, 255, 0.5); font-size: 1.4rem; cursor: pointer; padding: 4px;">&times;</button>
        </div>

        <div class="wallet-currency-tabs" id="depositCurrencyTabs" style="display:flex; gap:8px; margin-bottom:18px;">
            <button type="button" class="wallet-cur-tab is-active" data-tab="usdt" id="depositTabUsdt" style="flex:1; padding:8px 18px; border-radius:20px; font-size:0.85rem; font-weight:700; color:#090a0f; background:#fff; border:none; cursor:pointer;">$ USD</button>
            <button type="button" class="wallet-cur-tab" data-tab="inr" id="depositTabInr" style="flex:1; padding:8px 18px; border-radius:20px; font-size:0.85rem; font-weight:700; color:rgba(255,255,255,0.6); background:none; border:none; cursor:pointer;">₹ INR</button>
        </div>

        <!-- USD Deposit Form -->
        <form id="demoCreditForm" class="wallet-form wallet-flow-form" data-cur-panel="usdt">
            <label style="display:block;margin-bottom:12px;font-size:0.85rem;color:rgba(255,255,255,0.7);">
                Network
                <select name="network" id="depositNetworkSelect" class="wallet-select-input" required style="width:100%;height:42px;margin-top:6px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 12px;">
                    <option value="">Loading networks...</option>
                </select>
            </label>

            <div class="wallet-address-panel deposit-address-panel" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;margin-bottom:14px;">
                <div class="address-panel-head" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                    <span style="font-size:0.78rem;color:rgba(255,255,255,0.5);font-weight:700;text-transform:uppercase;">Company Deposit Address</span>
                    <button type="button" id="copyDepositAddressButton" style="background:rgba(16,185,129,0.15);color:#34d399;border:1px solid rgba(16,185,129,0.3);padding:4px 12px;border-radius:8px;font-size:0.78rem;font-weight:800;cursor:pointer;">Copy</button>
                </div>
                <strong id="depositAddressText" style="display:block;word-break:break-all;font-family:monospace;font-size:0.88rem;color:#34d399;font-weight:800;background:rgba(0,0,0,0.5);padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,0.08);text-align:center;letter-spacing:0.02em;">Select network to view address.</strong>
                <small id="depositAddressHint" style="display:block;color:rgba(255,255,255,0.4);font-size:0.75rem;margin-top:6px;">Use only selected network.</small>
            </div>

            <div class="wallet-info-row" style="display:flex;justify-content:space-between;font-size:0.83rem;color:rgba(255,255,255,0.6);margin-bottom:14px;">
                <span>Minimum deposit</span>
                <strong id="depositMinimumText">--</strong>
            </div>

            <label style="display:block;margin-bottom:16px;font-size:0.85rem;color:rgba(255,255,255,0.7);">
                Amount
                <input type="number" name="amount" min="0.00000001" step="any" placeholder="e.g. 10000" required style="width:100%;height:42px;margin-top:6px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 14px;">
            </label>

            <button type="submit" class="dharma-btn-primary" style="width:100%;height:44px;font-size:0.95rem;background:#3b82f6;color:#fff;border-radius:12px;border:none;font-weight:700;cursor:pointer;">
                Submit Deposit
            </button>
        </form>

        <!-- INR Deposit Form -->
        <div id="inrDepositPanel" data-cur-panel="inr" style="display:none;">
            <div id="inrBankDetailsBox" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;margin-bottom:14px;font-size:0.85rem;display:none;">
                <div style="color:rgba(255,255,255,0.5);margin-bottom:6px;font-size:0.78rem;text-transform:uppercase;letter-spacing:0.04em;">Transfer INR to this account</div>
                <div id="inrBankInfo" style="color:rgba(255,255,255,0.85);line-height:1.8;">Loading bank details...</div>
            </div>
            <form id="inrDepositForm" class="wallet-form wallet-flow-form">
                <label style="display:block;margin-bottom:12px;font-size:0.85rem;color:rgba(255,255,255,0.7);">
                    Amount (INR)
                    <input type="number" name="amount_inr" id="inrDepositAmount" min="1" step="any" placeholder="e.g. 10000" required style="width:100%;height:42px;margin-top:6px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 14px;">
                </label>
                <div class="wallet-info-row" id="inrDepositConversionRow" style="display:flex;justify-content:space-between;background:rgba(255,255,255,0.03);border-radius:8px;padding:10px;margin-bottom:6px;font-size:0.83rem;">
                    <span>You deposit</span><strong id="inrDepositConvYouPay">₹0</strong>
                </div>
                <div class="wallet-info-row" style="display:flex;justify-content:space-between;background:rgba(255,255,255,0.03);border-radius:8px;padding:10px;margin-bottom:10px;font-size:0.83rem;">
                    <span>Wallet credit</span><strong id="inrDepositConvCredit" style="color:#22c55e;">$0.00 USD</strong>
                </div>
                <small style="color:rgba(255,255,255,0.4);font-size:0.76rem;margin-bottom:14px;display:block;" id="inrDepositRateLabel">Loading rate...</small>
                <button type="submit" class="dharma-btn-primary" style="width:100%;height:44px;font-size:0.95rem;background:linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);color:#fff;border-radius:12px;border:none;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;">
                    <span>Pay with Razorpay</span> 💳
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        window.currentCawStep = window.currentCawStep || 1;

        // Ensure goToCawStep exists early if app.js is still pending
        if (typeof window.goToCawStep !== 'function') {
            window.goToCawStep = function(stepNumber) {
                if (stepNumber < 1) stepNumber = 1;
                if (stepNumber > 3) stepNumber = 3;
                window.currentCawStep = stepNumber;

                for (var i = 1; i <= 3; i++) {
                    var v = document.getElementById('cawStepView' + i);
                    if (v) v.style.display = (i === stepNumber) ? 'block' : 'none';
                    var s = document.getElementById('cawStepNav' + i);
                    if (s) {
                        s.classList.remove('is-active', 'is-completed');
                        if (i < stepNumber) s.classList.add('is-completed');
                        else if (i === stepNumber) s.classList.add('is-active');
                    }
                }
                var l1 = document.getElementById('cawStepLine1');
                var l2 = document.getElementById('cawStepLine2');
                if (l1) l1.classList.toggle('is-active', stepNumber >= 2);
                if (l2) l2.classList.toggle('is-active', stepNumber >= 3);

                var cnt = document.getElementById('cawStepCountText');
                if (cnt) cnt.textContent = 'Step ' + stepNumber + ' of 3';

                var b = document.getElementById('cawBtnFooterBack');
                if (b) b.textContent = (stepNumber === 1) ? 'Cancel' : '← Back';

                var nl = document.getElementById('cawBtnNextLabel');
                if (nl) nl.textContent = (stepNumber === 3) ? 'Create Account' : 'Next Step';

                var ni = document.getElementById('cawBtnNextIcon');
                if (ni) {
                    ni.innerHTML = (stepNumber === 3)
                        ? '<polyline points="20 6 9 17 4 12"></polyline>'
                        : '<line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline>';
                }
            };
        }

        // Direct fallback click listeners for Next and Back
        var btnNext = document.getElementById('cawBtnFooterNext');
        if (btnNext && !btnNext._cawBound) {
            btnNext._cawBound = true;
            btnNext.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof window.handleCawFooterNext === 'function') {
                    window.handleCawFooterNext();
                } else if (typeof window.goToCawStep === 'function') {
                    window.goToCawStep((window.currentCawStep || 1) + 1);
                }
            });
        }

        var btnBack = document.getElementById('cawBtnFooterBack');
        if (btnBack && !btnBack._cawBound) {
            btnBack._cawBound = true;
            btnBack.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof window.handleCawFooterBack === 'function') {
                    window.handleCawFooterBack();
                } else if (typeof window.closeBrokerModals === 'function') {
                    window.closeBrokerModals();
                }
            });
        }
    })();
</script>
