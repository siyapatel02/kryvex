@extends('layouts.app')

@section('title', 'Settings - Kryvex')

@push('styles')
    @vite('resources/css/pages/settings.css')
    <style>
        .kryvex-settings-page {
            padding: 32px 0 90px;
            color: #ffffff;
        }

        .settings-header-label {
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #8b5cf6;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .settings-main-title {
            font-size: 2rem;
            font-weight: 800;
            margin: 0 0 28px;
            letter-spacing: -0.02em;
        }

        /* Settings Card Block */
        .settings-card {
            background: #121318;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 24px 28px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
        }

        .settings-card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #ffffff;
            margin: 0 0 18px;
        }

        /* Settings Item Row */
        .settings-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            gap: 16px;
        }

        .settings-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .settings-row:first-of-type {
            padding-top: 0;
        }

        .settings-row-left {
            max-width: 65%;
        }

        .settings-item-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 3px;
        }

        .settings-item-desc {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.45);
            line-height: 1.45;
            margin: 0;
        }

        .settings-row-right {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-shrink: 0;
        }

        .settings-status-text {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        .settings-action-btn {
            background: transparent !important;
            color: #8b5cf6 !important;
            font-weight: 700 !important;
            font-size: 0.88rem !important;
            padding: 4px 2px !important;
            border-radius: 0 !important;
            border: none !important;
            box-shadow: none !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .settings-action-btn:hover {
            background: transparent !important;
            border: none !important;
            color: #a78bfa !important;
            text-decoration: underline !important;
        }

        .settings-action-btn.btn-purple {
            background: transparent !important;
            color: #8b5cf6 !important;
            border: none !important;
        }

        .settings-action-btn.btn-purple:hover {
            background: transparent !important;
            color: #a78bfa !important;
            text-decoration: underline !important;
        }

        .settings-action-btn.btn-danger {
            background: transparent !important;
            color: #ef4444 !important;
            border: none !important;
        }

        .settings-action-btn.btn-danger:hover {
            background: transparent !important;
            color: #f87171 !important;
            text-decoration: underline !important;
        }

        /* User Avatar Icon Pill in Profile Row */
        .settings-avatar-icon {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #8b5cf6;
            color: #ffffff;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        /* Modal Backdrop Overlays */
        .settings-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.82);
            backdrop-filter: blur(12px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .settings-modal-card {
            background: #121318;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            width: 100%;
            max-width: 520px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }

        .settings-modal-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }

        .settings-modal-head h3 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .settings-modal-close {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.5);
            font-size: 1.4rem;
            cursor: pointer;
            padding: 4px;
        }

        .settings-modal-close:hover {
            color: #ffffff;
        }

        /* KYC Wizard Styles */
        .kyc-step-indicator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin: 20px 0 32px;
            padding: 0 10px;
        }

        .kyc-step-line {
            position: absolute;
            top: 18px;
            left: 40px;
            right: 40px;
            height: 3px;
            background: rgba(255, 255, 255, 0.1);
            z-index: 1;
        }

        .kyc-step-progress-line {
            height: 100%;
            background: var(--kv-yellow, #facc15);
            width: 0%;
            transition: width 0.3s ease;
        }

        .kyc-step-item {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
        }

        .kyc-step-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1c1f2e;
            color: #64748b;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .kyc-step-label {
            font-size: 0.78rem;
            color: #64748b;
            font-weight: 600;
        }

        /* ============================================================ */
        /* COMPREHENSIVE RESPONSIVE STYLES FOR ALL SCREEN SIZES */
        /* ============================================================ */
        @media (max-width: 768px) {
            .kryvex-settings-page {
                padding: 16px 12px 70px !important;
            }

            .settings-main-title {
                font-size: 1.5rem !important;
                margin: 0 0 20px !important;
            }

            .settings-card {
                padding: 18px 16px !important;
                border-radius: 16px !important;
                margin-bottom: 16px !important;
            }

            .settings-card-title {
                font-size: 1.05rem !important;
                margin: 0 0 14px !important;
            }

            .settings-row {
                padding: 14px 0 !important;
                gap: 12px !important;
            }

            .settings-item-title {
                font-size: 0.9rem !important;
            }

            .settings-item-desc {
                font-size: 0.76rem !important;
            }

            .settings-action-btn {
                padding: 4px 2px !important;
                font-size: 0.88rem !important;
                background: transparent !important;
                border: none !important;
                margin-left: auto !important;
            }

            /* Sub-Page Titles */
            #accountProfileSubPageView h1,
            #supportSubPageView h1 {
                font-size: 1.4rem !important;
                margin: 0 0 18px !important;
            }

            /* Support Header Banner Card */
            #supportSubPageView .settings-card:first-of-type {
                padding: 18px 16px !important;
                gap: 14px !important;
            }

            /* Support Topic Cards Grid */
            #supportSubPageView .support-topic-card {
                padding: 12px 10px !important;
            }

            /* Modals Card */
            .settings-modal-backdrop {
                padding: 12px !important;
            }

            .settings-modal-card {
                padding: 20px 16px !important;
                border-radius: 18px !important;
                max-height: 90vh !important;
            }
        }

        @media (max-width: 580px) {
            .kryvex-settings-page {
                padding: 12px 8px 60px !important;
                overflow-x: hidden !important;
                max-width: 100vw !important;
                box-sizing: border-box !important;
            }

            .settings-main-title {
                font-size: 1.35rem !important;
                margin: 0 0 16px !important;
            }

            .settings-card {
                padding: 14px 12px !important;
                border-radius: 14px !important;
                margin-bottom: 12px !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            /* Settings Rows: Title + Controls on top, full width description underneath */
            .settings-row {
                display: grid !important;
                grid-template-columns: minmax(0, 1fr) minmax(0, auto) !important;
                grid-template-rows: auto auto !important;
                grid-template-areas:
                    "title right"
                    "desc  desc" !important;
                align-items: center !important;
                row-gap: 6px !important;
                column-gap: 8px !important;
                padding: 12px 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-sizing: border-box !important;
                overflow: hidden !important;
            }

            .settings-row-left {
                display: contents !important;
            }

            .settings-item-title {
                grid-area: title !important;
                font-size: 0.88rem !important;
                font-weight: 700 !important;
                color: #ffffff !important;
                margin: 0 !important;
                line-height: 1.3 !important;
                min-width: 0 !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }

            .settings-item-desc {
                grid-area: desc !important;
                grid-column: 1 / -1 !important;
                font-size: 0.75rem !important;
                color: rgba(255, 255, 255, 0.45) !important;
                line-height: 1.4 !important;
                margin: 2px 0 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                word-break: break-word !important;
                box-sizing: border-box !important;
            }

            .settings-row-right {
                grid-area: right !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: flex-end !important;
                gap: 6px !important;
                margin-left: auto !important;
                text-align: right !important;
                min-width: 0 !important;
                max-width: 100% !important;
                flex-shrink: 0 !important;
            }

            .settings-status-text {
                font-size: 0.78rem !important;
                color: rgba(255, 255, 255, 0.7) !important;
                max-width: 110px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
                display: inline-block !important;
                text-align: right !important;
                min-width: 0 !important;
            }

            .user-display-name,
            .user-display-email {
                max-width: 100px !important;
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
                display: inline-block !important;
                min-width: 0 !important;
            }

            .settings-avatar-icon {
                width: 24px !important;
                height: 24px !important;
                font-size: 0.75rem !important;
                flex-shrink: 0 !important;
            }

            .settings-row-right > .settings-action-btn,
            .settings-row-right > a.settings-action-btn,
            .settings-row-right > button.settings-action-btn,
            .settings-action-btn {
                margin-left: auto !important;
                flex-shrink: 0 !important;
                white-space: nowrap !important;
                font-size: 0.84rem !important;
                background: transparent !important;
                border: none !important;
                padding: 2px 2px !important;
            }

            /* Sub-Page Profile Item Rows: Buttons always stay on the RIGHT */
            #accountProfileSubPageView .settings-card > div[style*="display: flex"] {
                display: flex !important;
                flex-direction: row !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 8px !important;
                padding: 12px 10px !important;
                flex-wrap: nowrap !important;
                overflow: hidden !important;
                box-sizing: border-box !important;
            }

            #accountProfileSubPageView .settings-card > div[style*="display: flex"] > div:first-child {
                flex: 1 1 auto !important;
                min-width: 0 !important;
                overflow: hidden !important;
            }

            #accountProfileSubPageView .settings-card > div[style*="display: flex"] > div:first-child strong {
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
                max-width: 100% !important;
            }

            #accountProfileSubPageView .settings-card > div[style*="display: flex"] > .settings-action-btn,
            #accountProfileSubPageView .settings-card > div[style*="display: flex"] > a.settings-action-btn,
            #accountProfileSubPageView .settings-card > div[style*="display: flex"] > button.settings-action-btn,
            #accountProfileSubPageView .settings-card > div[style*="display: flex"] > div:last-child:not(:first-child) {
                margin-left: auto !important; /* Pushes button/controls to the RIGHT */
                flex-shrink: 0 !important;
                display: inline-flex !important;
                align-items: center !important;
                justify-content: flex-end !important;
                white-space: nowrap !important;
            }

            /* Support Sub-page stats on mobile */
            #supportSubPageView .settings-card:first-of-type > div:last-child {
                width: 100% !important;
                display: flex !important;
                gap: 8px !important;
            }

            #supportSubPageView .settings-card:first-of-type > div:last-child > div {
                flex: 1 !important;
                min-width: 0 !important;
                padding: 10px 12px !important;
            }

            /* Support Topic Selection 2 columns */
            #supportSubPageView div[style*="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr))"] {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 8px !important;
            }

            /* MT5 Connected Credentials Card */
            #mt5ConnectedViewWrap > div:last-child {
                display: grid !important;
                grid-template-columns: 1fr !important;
                gap: 12px !important;
                padding: 12px !important;
            }

            /* MT5 Edit Form Grid */
            #userMt5CredentialsForm > div:first-child {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }

            /* Modals Form Layout */
            .settings-modal-backdrop {
                padding: 6px !important;
                align-items: center !important;
            }

            .settings-modal-card {
                padding: 16px 12px !important;
                border-radius: 16px !important;
                max-height: 92vh !important;
                width: 100% !important;
            }

            .settings-modal-head h3 {
                font-size: 1.02rem !important;
            }
        }

        @media (max-width: 400px) {
            .kryvex-settings-page {
                padding: 10px 6px 50px !important;
            }

            .settings-card {
                padding: 12px 10px !important;
                border-radius: 12px !important;
            }

            .settings-item-title {
                font-size: 0.84rem !important;
            }

            .settings-status-text {
                font-size: 0.74rem !important;
                max-width: 80px !important;
            }

            .user-display-name,
            .user-display-email {
                max-width: 70px !important;
            }

            .settings-action-btn {
                font-size: 0.8rem !important;
            }

            .settings-avatar-icon {
                width: 22px !important;
                height: 22px !important;
                font-size: 0.7rem !important;
            }
        }

        @media (max-width: 340px) {
            .settings-status-text {
                max-width: 60px !important;
            }

            .user-display-name,
            .user-display-email {
                max-width: 52px !important;
            }
        }

        /* ─── Clean Compact Profile Rows (No sub-cards, same line, no ellipsis) ─── */
        .profile-clean-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 13px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            width: 100%;
            box-sizing: border-box;
        }

        .profile-clean-row:last-child {
            border-bottom: none;
            padding-bottom: 2px;
        }

        .profile-clean-row:first-of-type {
            padding-top: 4px;
        }

        .profile-clean-left {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            flex: 1 1 auto;
        }

        .profile-clean-icon {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            color: #a78bfa;
            flex-shrink: 0;
        }

        .profile-clean-meta {
            min-width: 0;
            flex: 1 1 auto;
        }

        .profile-clean-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.45);
            font-weight: 600;
            display: block;
            margin-bottom: 2px;
        }

        .profile-clean-value {
            font-size: 0.95rem;
            color: #ffffff;
            font-weight: 700;
            display: block;
            line-height: 1.35;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .profile-clean-desc {
            font-size: 0.74rem;
            color: rgba(255, 255, 255, 0.4);
            display: block;
            margin-top: 2px;
            line-height: 1.3;
            word-break: break-word;
        }

        .profile-clean-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
            margin-left: auto;
            white-space: nowrap;
        }

        /* Crucial: Override any global text-overflow: ellipsis / max-width for account profile */
        #accountProfileSubPageView .user-display-name,
        #accountProfileSubPageView .user-display-email,
        #accountProfileSubPageView .profile-clean-value {
            max-width: none !important;
            overflow: visible !important;
            text-overflow: clip !important;
            white-space: normal !important;
            display: block !important;
        }

        #accountProfileSubPageView .settings-action-btn {
            white-space: nowrap !important;
            flex-shrink: 0 !important;
        }

        @media (max-width: 580px) {
            #accountProfileSubPageView {
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .profile-clean-row {
                padding: 10px 0 !important;
                gap: 8px !important;
            }

            .profile-clean-left {
                gap: 8px !important;
            }

            .profile-clean-icon {
                width: 28px !important;
                height: 28px !important;
                font-size: 0.9rem !important;
            }

            .profile-clean-label {
                font-size: 0.7rem !important;
            }

            .profile-clean-value {
                font-size: 0.88rem !important;
            }

            .profile-clean-desc {
                font-size: 0.68rem !important;
            }

            .profile-clean-right .settings-action-btn {
                font-size: 0.8rem !important;
                padding: 0 !important;
                background: transparent !important;
                border: none !important;
            }
        }

        /* Light Theme Overrides - Text only for Edit, Manage, Contact Support, etc. */
        html[data-theme="light"] .settings-action-btn,
        html[data-theme="light"] a.settings-action-btn,
        html[data-theme="light"] button.settings-action-btn,
        html[data-theme="light"] .profile-clean-right .settings-action-btn,
        html[data-theme="light"] #accountProfileSubPageView .settings-action-btn,
        html.theme-light .settings-action-btn,
        html.theme-light a.settings-action-btn,
        html.theme-light button.settings-action-btn,
        html.theme-light .profile-clean-right .settings-action-btn,
        html.theme-light #accountProfileSubPageView .settings-action-btn {
            background: transparent !important;
            background-color: transparent !important;
            border: none !important;
            box-shadow: none !important;
            color: #7c3aed !important;
            font-weight: 700 !important;
            padding: 0 !important;
            text-decoration: none !important;
        }

        html[data-theme="light"] .settings-action-btn:hover,
        html[data-theme="light"] a.settings-action-btn:hover,
        html[data-theme="light"] button.settings-action-btn:hover,
        html.theme-light .settings-action-btn:hover,
        html.theme-light a.settings-action-btn:hover,
        html.theme-light button.settings-action-btn:hover {
            background: transparent !important;
            background-color: transparent !important;
            color: #6d28d9 !important;
            text-decoration: underline !important;
        }

        html[data-theme="light"] .settings-action-btn.btn-danger,
        html.theme-light .settings-action-btn.btn-danger {
            color: #ef4444 !important;
        }

        html[data-theme="light"] .settings-action-btn.btn-danger:hover,
        html.theme-light .settings-action-btn.btn-danger:hover {
            color: #dc2626 !important;
            text-decoration: underline !important;
        }

        /* ==========================================================================
           LIGHT THEME OVERRIDES FOR ACCOUNT PROFILE SUB-PAGE
           ========================================================================== */
        html[data-theme="light"] #accountProfileSubPageView .profile-clean-row,
        html.theme-light #accountProfileSubPageView .profile-clean-row {
            border-bottom: 1px solid #e2e8f0 !important;
        }

        html[data-theme="light"] #accountProfileSubPageView .profile-clean-label,
        html.theme-light #accountProfileSubPageView .profile-clean-label {
            color: #64748b !important;
            font-weight: 600 !important;
        }

        html[data-theme="light"] #accountProfileSubPageView .profile-clean-value,
        html[data-theme="light"] #accountProfileSubPageView .user-display-name,
        html[data-theme="light"] #accountProfileSubPageView .user-display-email,
        html[data-theme="light"] #accountProfileSubPageView #accountProfileClientIdText,
        html[data-theme="light"] #accountProfileSubPageView #accountProfileMemberSinceText,
        html[data-theme="light"] #accountProfileSubPageView #dashBrokerType,
        html[data-theme="light"] #accountProfileSubPageView #subPageWalletSnapshotText,
        html[data-theme="light"] #accountProfileSubPageView #subPagePrefCurrText,
        html[data-theme="light"] #accountProfileSubPageView #subPageReferralCodeText,
        html.theme-light #accountProfileSubPageView .profile-clean-value,
        html.theme-light #accountProfileSubPageView .user-display-name,
        html.theme-light #accountProfileSubPageView .user-display-email,
        html.theme-light #accountProfileSubPageView #accountProfileClientIdText,
        html.theme-light #accountProfileSubPageView #accountProfileMemberSinceText,
        html.theme-light #accountProfileSubPageView #dashBrokerType,
        html.theme-light #accountProfileSubPageView #subPageWalletSnapshotText,
        html.theme-light #accountProfileSubPageView #subPagePrefCurrText,
        html.theme-light #accountProfileSubPageView #subPageReferralCodeText {
            color: #0f172a !important;
            font-weight: 700 !important;
        }

        html[data-theme="light"] #accountProfileSubPageView .profile-clean-desc,
        html[data-theme="light"] #accountProfileSubPageView #subPageReferralEarnedText,
        html.theme-light #accountProfileSubPageView .profile-clean-desc,
        html.theme-light #accountProfileSubPageView #subPageReferralEarnedText {
            color: #64748b !important;
        }

        html[data-theme="light"] #accountProfileSubPageView .profile-clean-desc strong,
        html.theme-light #accountProfileSubPageView .profile-clean-desc strong {
            color: #7c3aed !important;
        }

        html[data-theme="light"] #accountProfileSubPageView .profile-clean-icon,
        html.theme-light #accountProfileSubPageView .profile-clean-icon {
            background: #f1f5f9 !important;
            border: 1px solid #e2e8f0 !important;
        }

        html[data-theme="light"] #accountProfileSubPageView .settings-card-title,
        html.theme-light #accountProfileSubPageView .settings-card-title {
            color: #7c3aed !important;
            font-weight: 800 !important;
        }

        html[data-theme="light"] #accountProfileSubPageView #accountProfileMobileText,
        html.theme-light #accountProfileSubPageView #accountProfileMobileText {
            color: #0284c7 !important;
            font-weight: 700 !important;
        }

        html[data-theme="light"] #accountProfileSubPageView #subPageBankStatusText,
        html.theme-light #accountProfileSubPageView #subPageBankStatusText {
            color: #64748b !important;
            font-weight: 700 !important;
        }

        /* Currency Toggle Box on Account Profile in Light Mode */
        html[data-theme="light"] #accountProfileSubPageView .profile-clean-right > div[style*="background: rgba(0, 0, 0"],
        html.theme-light #accountProfileSubPageView .profile-clean-right > div[style*="background: rgba(0, 0, 0"] {
            background: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
        }

        html[data-theme="light"] #accountProfileSubPageView .curr-toggle-btn,
        html.theme-light #accountProfileSubPageView .curr-toggle-btn {
            color: #64748b !important;
            background: transparent !important;
        }

        html[data-theme="light"] #accountProfileSubPageView .curr-toggle-btn.active,
        html.theme-light #accountProfileSubPageView .curr-toggle-btn.active {
            background: #7c3aed !important;
            color: #ffffff !important;
        }

        /* MT5 Status Card Light Mode */
        html[data-theme="light"] #mt5StatusCard,
        html.theme-light #mt5StatusCard {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05) !important;
        }
        html[data-theme="light"] #mt5StatusCard h2,
        html[data-theme="light"] #mt5StatusCard h4,
        html[data-theme="light"] #mt5StatusCard strong,
        html.theme-light #mt5StatusCard h2,
        html.theme-light #mt5StatusCard h4,
        html.theme-light #mt5StatusCard strong {
            color: #0f172a !important;
        }
        html[data-theme="light"] #mt5StatusCard span,
        html.theme-light #mt5StatusCard span {
            color: #64748b !important;
        }

        /* Broker Plan & Active Badge in Light Mode */
        html[data-theme="light"] .broker-plan-desc,
        html[data-theme="light"] #dashBrokerPlan,
        html[data-theme="light"] #dashBrokerPlan span,
        html.theme-light .broker-plan-desc,
        html.theme-light #dashBrokerPlan,
        html.theme-light #dashBrokerPlan span {
            color: #64748b !important;
        }

        html[data-theme="light"] .broker-plan-name,
        html.theme-light .broker-plan-name {
            color: #7c3aed !important;
            font-weight: 800 !important;
        }

        html[data-theme="light"] .broker-plan-val,
        html[data-theme="light"] #dashBrokerPlan strong,
        html.theme-light .broker-plan-val,
        html.theme-light #dashBrokerPlan strong {
            color: #0f172a !important;
            font-weight: 700 !important;
        }

        html[data-theme="light"] #dashBrokerBadge span,
        html.theme-light #dashBrokerBadge span {
            background: #dcfce7 !important;
            border: 1px solid #86efac !important;
            color: #15803d !important;
            padding: 2px 8px !important;
            font-weight: 700 !important;
            display: inline-block !important;
        }

        /* =========================================================================
           MAIN SETTINGS PAGE (CARDS, ROWS, PREFERENCES, SECURITY, WITHDRAWAL)
           ========================================================================= */
        html[data-theme="light"] .kryvex-settings-page,
        html.theme-light .kryvex-settings-page {
            color: #0f172a !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-main-title,
        html.theme-light .kryvex-settings-page .settings-main-title {
            color: #0f172a !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-header-label,
        html.theme-light .kryvex-settings-page .settings-header-label {
            color: #7c3aed !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-card,
        html.theme-light .kryvex-settings-page .settings-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05) !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-card-title,
        html.theme-light .kryvex-settings-page .settings-card-title {
            color: #0f172a !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-row,
        html.theme-light .kryvex-settings-page .settings-row {
            border-bottom: 1px solid #f1f5f9 !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-item-title,
        html.theme-light .kryvex-settings-page .settings-item-title {
            color: #0f172a !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-item-desc,
        html.theme-light .kryvex-settings-page .settings-item-desc {
            color: #64748b !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-status-text,
        html.theme-light .kryvex-settings-page .settings-status-text {
            color: #475569 !important;
        }

        html[data-theme="light"] .kryvex-settings-page #colorPrefStatusText span:last-child,
        html.theme-light .kryvex-settings-page #colorPrefStatusText span:last-child {
            color: #0f172a !important;
        }

        html[data-theme="light"] .kryvex-settings-page #themeStatusLabel,
        html.theme-light .kryvex-settings-page #themeStatusLabel {
            color: #0f172a !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-row-right > div[style*="background: rgba(255,255,255"],
        html.theme-light .kryvex-settings-page .settings-row-right > div[style*="background: rgba(255,255,255"] {
            background: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-action-btn,
        html.theme-light .kryvex-settings-page .settings-action-btn {
            color: #7c3aed !important;
        }

        html[data-theme="light"] .kryvex-settings-page .settings-action-btn:hover,
        html.theme-light .kryvex-settings-page .settings-action-btn:hover {
            color: #6d28d9 !important;
        }

        /* Settings Modals Light Theme */
        html[data-theme="light"] .settings-modal,
        html.theme-light .settings-modal {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            color: #0f172a !important;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.15) !important;
        }

        html[data-theme="light"] .settings-modal h2,
        html[data-theme="light"] .settings-modal h3,
        html[data-theme="light"] .settings-modal h4,
        html.theme-light .settings-modal h2,
        html.theme-light .settings-modal h3,
        html.theme-light .settings-modal h4 {
            color: #0f172a !important;
        }

        html[data-theme="light"] .settings-modal p,
        html[data-theme="light"] .settings-modal label,
        html[data-theme="light"] .settings-modal span,
        html.theme-light .settings-modal p,
        html.theme-light .settings-modal label,
        html.theme-light .settings-modal span {
            color: #475569 !important;
        }

        html[data-theme="light"] .settings-modal input,
        html[data-theme="light"] .settings-modal select,
        html[data-theme="light"] .settings-modal textarea,
        html.theme-light .settings-modal input,
        html.theme-light .settings-modal select,
        html.theme-light .settings-modal textarea {
            background: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            color: #0f172a !important;
        }

        html[data-theme="light"] .settings-modal-close,
        html.theme-light .settings-modal-close {
            color: #64748b !important;
        }

        html[data-theme="light"] .settings-modal-close:hover,
        html.theme-light .settings-modal-close:hover {
            color: #0f172a !important;
        }

        #accountProfileSubPageView .profile-clean-meta {
            overflow: visible !important;
        }
        #accountProfileSubPageView .profile-clean-row {
            overflow: visible !important;
        }
        #accountProfileSubPageView .profile-clean-label {
            line-height: 1.4 !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://unpkg.com/lenis@1.1.18/dist/lenis.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tracking.js/1.1.3/tracking-min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tracking.js/1.1.3/data/face-min.js"></script>
    @vite('resources/js/pages/kyc.js')
    @vite('resources/js/pages/settings.js')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Populate user profile info from localStorage / backend state
            let user = {};
            try {
                user = JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
            } catch (e) {}

            const displayName = user.name || user.email || 'Patel Yavan Bharatbhai';
            const email = user.email || 'ybh3112@gmail.com';
            const initChar = String(displayName).trim().charAt(0).toUpperCase() || 'P';

            document.querySelectorAll('.user-display-name').forEach(el => el.textContent = displayName);
            document.querySelectorAll('.user-display-email').forEach(el => el.textContent = email);
            document.querySelectorAll('.user-display-initial').forEach(el => el.textContent = initChar);

            // Sub-page View Switcher Logic (Main Settings vs Account Profile vs Support Sub-Page)
            const mainSettingsView = document.getElementById('mainSettingsView');
            const accountProfileSubPageView = document.getElementById('accountProfileSubPageView');
            const supportSubPageView = document.getElementById('supportSubPageView');

            const btnBackToSettings = document.getElementById('btnBackToSettings');
            const btnBackToSettingsFromSupport = document.getElementById('btnBackToSettingsFromSupport');

            function hideAllViews() {
                if (mainSettingsView) mainSettingsView.style.display = 'none';
                if (accountProfileSubPageView) accountProfileSubPageView.style.display = 'none';
                if (supportSubPageView) supportSubPageView.style.display = 'none';
            }

            function showSubPage() {
                hideAllViews();
                if (accountProfileSubPageView) accountProfileSubPageView.style.display = 'block';
                if (typeof window.checkKycStatus === 'function') {
                    window.checkKycStatus();
                }
                if (window.lenisInstance) {
                    window.lenisInstance.resize();
                    window.lenisInstance.scrollTo(0, { immediate: true });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                if (typeof window.setupScrollReveal === 'function') {
                    window.setupScrollReveal();
                }
            }

            function showSupportSubPage() {
                hideAllViews();
                if (supportSubPageView) supportSubPageView.style.display = 'block';
                if (typeof window.loadUserSupportTickets === 'function') {
                    window.loadUserSupportTickets();
                }
                if (window.lenisInstance) {
                    window.lenisInstance.resize();
                    window.lenisInstance.scrollTo(0, { immediate: true });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                if (typeof window.setupScrollReveal === 'function') {
                    window.setupScrollReveal();
                }
            }

            function showMainSettings() {
                hideAllViews();
                if (mainSettingsView) mainSettingsView.style.display = 'block';
                if (window.lenisInstance) {
                    window.lenisInstance.resize();
                    window.lenisInstance.scrollTo(0, { immediate: true });
                } else {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                if (typeof window.setupScrollReveal === 'function') {
                    window.setupScrollReveal();
                }
            }

            document.querySelectorAll('.btn-open-account-profile').forEach(btn => {
                btn.addEventListener('click', () => {
                    showSubPage();
                    const url = new URL(window.location);
                    url.searchParams.set('tab', 'account-profile');
                    window.history.pushState({}, '', url);
                });
            });

            document.querySelectorAll('.btn-open-support').forEach(btn => {
                btn.addEventListener('click', () => {
                    showSupportSubPage();
                    const url = new URL(window.location);
                    url.searchParams.set('tab', 'support');
                    window.history.pushState({}, '', url);
                });
            });

            if (btnBackToSettings) {
                btnBackToSettings.addEventListener('click', () => {
                    showMainSettings();
                    const url = new URL(window.location);
                    url.searchParams.set('tab', 'profile');
                    window.history.pushState({}, '', url);
                });
            }

            if (btnBackToSettingsFromSupport) {
                btnBackToSettingsFromSupport.addEventListener('click', () => {
                    showMainSettings();
                    const url = new URL(window.location);
                    url.searchParams.set('tab', 'profile');
                    window.history.pushState({}, '', url);
                });
            }

            // Check URL query param tab=account-profile or tab=support or tab=profile
            const params = new URLSearchParams(window.location.search);
            const tabVal = (params.get('tab') || '').toLowerCase();
            if (tabVal === 'support' || window.location.hash === '#support') {
                showSupportSubPage();
            } else if (['account-profile', 'account_profile'].includes(tabVal) || window.location.hash === '#account-profile') {
                showSubPage();
            }

            // Generic Modal Trigger Handlers
            const openModal = (id) => {
                const modal = document.getElementById(id);
                if (modal) modal.hidden = false;
            };

            const closeModal = (id) => {
                const modal = document.getElementById(id);
                if (modal) modal.hidden = true;
            };

            document.querySelectorAll('[data-open-modal]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const modalId = btn.getAttribute('data-open-modal');
                    if (modalId) openModal(modalId);
                });
            });

            document.querySelectorAll('[data-close-modal]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const modalId = btn.getAttribute('data-close-modal');
                    if (modalId) closeModal(modalId);
                });
            });
        });
    </script>
@endpush

@section('content')
    <!-- TOP SCROLL PROGRESS BAR -->
    <div id="settingsScrollProgressBar" style="width: 0%;"></div>

    <div class="kryvex-settings-page">
        <div class="kv-container" style="max-width: 920px;">
            
            <!-- =================================================== -->
            <!-- 1. MAIN SETTINGS VIEW (8 CARDS GRID) -->
            <!-- =================================================== -->
            <div id="mainSettingsView">
                <div class="settings-header-label reveal-item">ACCOUNT</div>
                <h1 class="settings-main-title reveal-item">Settings</h1>

                <!-- ACCOUNT DELETION NOTICE BANNER (SHOWN WHEN PENDING REVIEW) -->
                <div id="settingsDeletionNoticeBox" style="display:none; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 14px; padding: 18px 20px; margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div style="width: 44px; height: 44px; border-radius: 12px; background: rgba(245, 158, 11, 0.2); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;">⚠️</div>
                            <div>
                                <strong style="color: #f59e0b; font-size: 15px; display: block; margin-bottom: 3px;">Account Deletion Request Under Review</strong>
                                <span id="settingsDeletionNoticeText" style="color: #cbd5e1; font-size: 13px; line-height: 1.4; display: block;">Your request to delete this account is pending administrator approval. During this time, your account is in read-only mode and trading/wallet actions are disabled.</span>
                            </div>
                        </div>
                        <button type="button" id="btnCancelDeletionTop" class="settings-action-btn" style="background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; font-weight: 700; font-size: 13px; padding: 8px 16px;">Cancel Request</button>
                    </div>
                </div>



                <!-- CARD 1: PROFILE -->
                <div class="settings-card reveal-item">
                    <h2 class="settings-card-title">Profile</h2>

                    <!-- Nickname & Avatar -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Nickname &amp; Avatar</div>
                            <p class="settings-item-desc">Set up an avatar and nickname. Avoid using your real name or social account name as a nickname.</p>
                        </div>
                        <div class="settings-row-right">
                            <div class="settings-avatar-icon user-display-initial">P</div>
                            <span class="settings-status-text user-display-name">Patel Yavan Bharatbhai</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalProfileEdit">Edit</button>
                        </div>
                    </div>

                    <!-- Account Profile (Navigates to Account Profile & KYC Sub-page) -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Account Profile</div>
                            <p class="settings-item-desc">View email, broker, KYC status, and manage your trading account details.</p>
                        </div>
                        <div class="settings-row-right">
                            <div class="settings-avatar-icon user-display-initial" style="background: rgba(139, 92, 246, 0.3); color: #a78bfa;">P</div>
                            <span class="settings-status-text user-display-email">ybh3112@gmail.com</span>
                            <button type="button" class="settings-action-btn btn-open-account-profile" id="btnOpenAccountProfileSubPage">Edit</button>
                        </div>
                    </div>

                    <!-- Support & Helpdesk -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Support &amp; Helpdesk</div>
                            <p class="settings-item-desc">Submit a ticket or query and get direct assistance from our support team.</p>
                        </div>
                        <div class="settings-row-right">
                            <button type="button" class="settings-action-btn btn-open-support">Contact Support 💬</button>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: NOTIFICATIONS -->
                <div class="settings-card reveal-item">
                    <h2 class="settings-card-title">Notifications</h2>

                    <!-- Notification Language -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Notification Language</div>
                            <p class="settings-item-desc">This will affect the language of email and in-app notifications.</p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text user-display-language" id="currentNotifLanguageText">English</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalNotifLanguage">Edit</button>
                        </div>
                    </div>

                    <!-- Notification Preferences -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Notification Preferences</div>
                            <p class="settings-item-desc">Once configured, you will receive relevant on-site inbox notifications within the app and website.</p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text" id="notifPrefStatusText" style="color: #22c55e;">✓ ON</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalNotifPreferences">Manage</button>
                        </div>
                    </div>
                </div>

                <!-- CARD 3: PREFERENCES -->
                <div class="settings-card reveal-item">
                    <h2 class="settings-card-title">Preferences</h2>

                    <!-- Color Preference -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Color Preference</div>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text" id="colorPrefStatusText"><span style="color: #22c55e;">↑</span> <span style="color: #ef4444;">↓</span> <span style="color: #ffffff;">Green Up / Red Down</span></span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalColorPref">Edit</button>
                        </div>
                    </div>

                    <!-- Style Settings -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Style Settings</div>
                        </div>
                        <div class="settings-row-right">
                            <span style="display: inline-flex; gap: 4px; align-items: center;">
                                <span style="width: 12px; height: 12px; border-radius: 3px; background: #22c55e;"></span>
                                <span style="width: 12px; height: 12px; border-radius: 3px; background: #ef4444; margin-right: 6px;"></span>
                                <span class="settings-status-text">Fresh</span>
                            </span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalStyleSettings">Edit</button>
                        </div>
                    </div>

                    <!-- UTC Time Zone -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">UTC Time Zone</div>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text">Last 24 hours</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalTimeZone">Edit</button>
                        </div>
                    </div>

                    <!-- Shortcuts -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Shortcuts</div>
                        </div>
                        <div class="settings-row-right">
                            <label class="shortcuts-toggle-wrapper" style="position: relative; display: inline-flex; align-items: center; cursor: pointer; width: 44px; height: 24px; margin-right: 6px;">
                                <input type="checkbox" id="shortcutsToggleCheck" checked style="opacity: 0; width: 0; height: 0;">
                                <span class="shortcuts-slider" id="shortcutsSliderBg" style="position: absolute; inset: 0; background-color: #3b82f6; border-radius: 20px; transition: 0.3s;"></span>
                                <span class="shortcuts-knob" id="shortcutsKnob" style="position: absolute; left: 3px; top: 3px; width: 18px; height: 18px; background-color: #ffffff; border-radius: 50%; transition: 0.3s; transform: translateX(20px);"></span>
                            </label>
                            <button type="button" class="settings-action-btn" data-open-modal="modalShortcuts">Edit</button>
                        </div>
                    </div>

                    <!-- Theme -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Theme</div>
                            <p class="settings-item-desc">Choose between Light and Dark interface appearance across the platform.</p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text" id="themeStatusLabel">Dark</span>
                            <div style="display: inline-flex; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 2px;">
                                <button type="button" id="btnThemeLight" onclick="window.setKryvexTheme('light')" style="background: none; border: none; color: rgba(255,255,255,0.6); padding: 4px 10px; border-radius: 14px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s ease;" title="Switch to Light Theme">☀️ Light</button>
                                <button type="button" id="btnThemeDark" onclick="window.setKryvexTheme('dark')" style="background: #3b82f6; border: none; color: #fff; padding: 4px 10px; border-radius: 14px; font-size: 0.8rem; cursor: pointer; transition: all 0.2s ease;" title="Switch to Dark Theme">🌙 Dark</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CARD 4: SECURITY -->
                <div class="settings-card reveal-item">
                    <h2 class="settings-card-title">Security</h2>

                    <!-- Login Password -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Login Password</div>
                            <p class="settings-item-desc">Change your password regularly to keep your account secure.</p>
                        </div>
                        <div class="settings-row-right">
                            <button type="button" class="settings-action-btn" data-open-modal="modalChangePassword">Edit</button>
                        </div>
                    </div>

                    <!-- Two-Factor Authentication -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Two-Factor Authentication</div>
                            <p class="settings-item-desc">Require a 6-digit email OTP verification code every time you log in.</p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text" id="twoFactorStatusText" style="color: rgba(255,255,255,0.45);">✕ OFF</span>
                            <button type="button" class="settings-action-btn btn-purple" data-open-modal="modalTwoFactor">Manage</button>
                        </div>
                    </div>
                </div>

                <!-- CARD 5: WITHDRAWAL -->
                <div class="settings-card reveal-item">
                    <h2 class="settings-card-title">Withdrawal</h2>

                    <!-- Withdrawal Whitelist -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Withdrawal Whitelist</div>
                            <p class="settings-item-desc">Once enabled, your account will only be able to withdraw to addresses on your whitelist. <a href="{{ url('/wallet/withdraw') }}" style="color: #f59e0b; text-decoration: none; font-weight: 600;">Address Management</a></p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text" style="color: rgba(255,255,255,0.45);">✕ OFF</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalWhitelist">Enable</button>
                        </div>
                    </div>

                    <!-- One-step Withdrawal -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">One-step Withdrawal</div>
                            <p class="settings-item-desc">When enabled, you can withdraw small amounts to whitelisted addresses without 2FA each time.</p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text" style="color: rgba(255,255,255,0.45);">✕ OFF</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalOneStepWithdraw">Enable</button>
                        </div>
                    </div>

                    <!-- Withdraw Setting (Bank Details & UPI) -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Withdraw Setting</div>
                            <p class="settings-item-desc">Choose on-chain or bank/off-chain transfer for applicable withdrawals.</p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text">Off-Chain / Bank Details</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalBankDetails">Edit</button>
                        </div>
                    </div>
                </div>

                <!-- CARD 6: TRADE -->
                <div class="settings-card reveal-item">
                    <h2 class="settings-card-title">Trade</h2>

                    <!-- Order Confirmation Reminders -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Order Confirmation Reminders</div>
                            <p class="settings-item-desc">If enabled, you will need to reconfirm every time an order is submitted.</p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text" id="orderConfirmStatusSummaryText">Market, Limit, Stop-Limit</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalOrderConfirm">Manage</button>
                        </div>
                    </div>

                    <!-- Close All Sound -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Close All Sound</div>
                            <p class="settings-item-desc">Sound played when you close all positions from Futures.</p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text" id="tradeSoundStatusText">Default (ON)</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalTradeSound">Manage</button>
                        </div>
                    </div>

                    <!-- Fee Deduction -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Fee Deduction</div>
                            <p class="settings-item-desc">How trading fees are settled on your account.</p>
                        </div>
                        <div class="settings-row-right">
                            <span class="settings-status-text">Spot Fees</span>
                            <button type="button" class="settings-action-btn" data-open-modal="modalFeeDeduction">Manage</button>
                        </div>
                    </div>
                </div>

                <!-- CARD 7: PRIVACY -->
                <div class="settings-card reveal-item">
                    <h2 class="settings-card-title">Privacy</h2>

                    <!-- Download Personal Data -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Download Personal Data</div>
                            <p class="settings-item-desc">Includes profile, withdrawal, deposit, and trading history. For KYC documents, contact <a href="{{ url('/support') }}" style="color: #f59e0b; text-decoration: none; font-weight: 600;">Support</a>.</p>
                        </div>
                        <div class="settings-row-right">
                            <button type="button" class="settings-action-btn" data-open-modal="modalDownloadData">Download</button>
                        </div>
                    </div>

                    <!-- Export Transaction Records -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Export Transaction Records</div>
                            <p class="settings-item-desc">Export complete ledger transaction history.</p>
                        </div>
                        <div class="settings-row-right">
                            <button type="button" class="settings-action-btn" data-open-modal="modalExportTransactions">Export</button>
                        </div>
                    </div>

                    <!-- Close Account -->
                    <div class="settings-row" id="closeAccountRow">
                        <div class="settings-row-left">
                            <div class="settings-item-title" id="closeAccountTitle">Close Account</div>
                            <p class="settings-item-desc" id="closeAccountDesc">Permanently close and delete your Kryvex account.</p>
                        </div>
                        <div class="settings-row-right" id="closeAccountActions">
                            <button type="button" class="settings-action-btn btn-danger" id="btnOpenCloseAccountModal" data-open-modal="modalCloseAccount">Delete</button>
                        </div>
                    </div>

                    <!-- Privacy Portal -->
                    <div class="settings-row">
                        <div class="settings-row-left">
                            <div class="settings-item-title">Privacy Portal</div>
                            <p class="settings-item-desc">Review your privacy rights and data management policies.</p>
                        </div>
                        <div class="settings-row-right">
                            <button type="button" class="settings-action-btn" data-open-modal="modalPrivacyPortal">Enter</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- =================================================== -->
            <!-- 2. ACCOUNT PROFILE & VERIFICATION DEDICATED SUB-PAGE VIEW -->
            <!-- =================================================== -->
            <div id="accountProfileSubPageView" style="display: none; max-width: 920px; margin: 0 auto;">
                <div style="margin-bottom: 20px;">
                    <button type="button" id="btnBackToSettings" class="settings-action-btn" style="font-size: 0.92rem; font-weight: 700; gap: 6px; padding: 4px 8px;">
                        ← Back to Settings
                    </button>
                </div>

                <div class="settings-header-label reveal-item">ACCOUNT PROFILE</div>
                <h1 class="settings-main-title reveal-item" style="margin: 0 0 20px;">Account Details &amp; Verification</h1>

                <!-- CARD 1: IDENTITY -->
                <div class="settings-card reveal-item" style="margin-bottom: 20px;">
                    <div class="settings-card-title" style="color: #8b5cf6; font-size: 0.78rem; letter-spacing: 0.08em; margin-bottom: 12px;">IDENTITY</div>

                    <!-- 1. Nickname & Avatar -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon">👤</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Nickname &amp; Avatar</span>
                                <strong class="profile-clean-value user-display-name">Patel Yavan Bharatbhai</strong>
                                <span class="profile-clean-desc">Shown in your account menu</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <div class="settings-avatar-icon user-display-initial" style="width: 32px; height: 32px; font-size: 0.88rem; background: #8b5cf6; color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800;">P</div>
                            <button type="button" class="settings-action-btn" data-open-modal="modalProfileEdit" style="font-size: 0.85rem; gap: 4px;"> Edit</button>
                        </div>
                    </div>

                    <!-- 2. Full Name -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon">👤</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Full Name</span>
                                <strong class="profile-clean-value user-display-name">Patel Yavan Bharatbhai</strong>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <button type="button" class="settings-action-btn" data-open-modal="modalProfileEdit" style="font-size: 0.85rem; gap: 4px;"> Edit</button>
                        </div>
                    </div>

                    <!-- 3. Email -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon">✉️</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Email</span>
                                <strong class="profile-clean-value user-display-email">ybh3112@gmail.com</strong>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Mobile -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon">📞</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Mobile</span>
                                <strong class="profile-clean-value" id="accountProfileMobileText" style="color: #38bdf8;">+91 98765 43210</strong>
                                <span class="profile-clean-desc">Verified via KYC flow when linked</span>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Client ID -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon">#</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Client ID</span>
                                <strong class="profile-clean-value" id="accountProfileClientIdText" style="font-family: monospace; font-weight: 800;">7840129</strong>
                                <span class="profile-clean-desc">Use this ID when contacting support</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <button type="button" class="settings-action-btn" id="btnCopyAccountProfileClientId" style="font-size: 0.85rem; gap: 4px;"> Copy</button>
                        </div>
                    </div>

                    <!-- 6. Member since -->
                    <div class="profile-clean-row" style="border-bottom: none;">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon">📅</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Member since</span>
                                <strong class="profile-clean-value" id="accountProfileMemberSinceText">June 2024</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: VERIFICATION & SECURITY -->
                <div class="settings-card reveal-item" style="margin-bottom: 20px;">
                    <div class="settings-card-title" style="color: #8b5cf6; font-size: 0.78rem; letter-spacing: 0.08em; margin-bottom: 12px;">VERIFICATION &amp; SECURITY</div>

                    <!-- 1. KYC status -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon" style="background: rgba(34, 197, 94, 0.12); color: #22c55e;">🛡️</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">KYC status</span>
                                <strong class="profile-clean-value" id="accountProfileKycStatusBadge" style="color: #22c55e;">Verified</strong>
                                <span class="profile-clean-desc">Required for higher limits and withdrawals</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <a href="{{ url('/kyc') }}" class="settings-action-btn btn-purple" id="btnAccountProfileKyc" style="font-size: 0.85rem;">View</a>
                        </div>
                    </div>

                    <!-- 2. Two-factor login -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">🔒</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Two-factor login</span>
                                <strong class="profile-clean-value" id="accountProfile2faStatusText" style="color: #f59e0b;">Disabled</strong>
                                <span class="profile-clean-desc">Email OTP on every sign-in</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <button type="button" class="settings-action-btn btn-purple" data-open-modal="modalTwoFactor" style="font-size: 0.85rem;">Manage</button>
                        </div>
                    </div>

                    <!-- 3. Password -->
                    <div class="profile-clean-row" style="border-bottom: none;">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon" style="background: rgba(239, 68, 68, 0.12); color: #ef4444;">🔑</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Password</span>
                                <strong class="profile-clean-value" style="font-family: monospace; letter-spacing: 2px;">••••••••</strong>
                                <span class="profile-clean-desc">Change from Settings → Security</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <button type="button" class="settings-action-btn btn-purple" data-open-modal="modalChangePassword" style="font-size: 0.85rem;">Change</button>
                        </div>
                    </div>
                </div>

                <!-- CARD 3: ACCOUNT -->
                <div class="settings-card reveal-item" style="margin-bottom: 20px;">
                    <div class="settings-card-title" style="color: #8b5cf6; font-size: 0.78rem; letter-spacing: 0.08em; margin-bottom: 12px;">ACCOUNT</div>

                    <!-- 1. Trading broker -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left" style="align-items: flex-start;">
                            <div class="profile-clean-icon" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b; margin-top: 2px;">🔄</div>
                            <div class="profile-clean-meta">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 2px; flex-wrap: wrap;">
                                    <span class="profile-clean-label" style="margin-bottom: 0;">Trading broker</span>
                                    <div id="dashBrokerBadge" style="display: inline-block;">
                                        <span style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #10b981; padding: 1px 6px; border-radius: 12px; font-size: 0.68rem; font-weight: 700;">● Active</span>
                                    </div>
                                </div>
                                <strong class="profile-clean-value" id="dashBrokerType">Kryvex Exchange</strong>
                                <div id="dashBrokerPlan" style="margin-top: 2px;">
                                    <span class="profile-clean-desc">Plan: <strong style="color: #a78bfa;">Basic</strong></span>
                                </div>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <button type="button" class="settings-action-btn btn-purple" id="btnOpenBrokerModalMain" onclick="if(typeof openBrokerModal==='function'){openBrokerModal();}else if(window.openBrokerModal){window.openBrokerModal();}" style="font-size: 0.85rem; flex-shrink: 0;">Change broker ⚙</button>
                        </div>
                    </div>

                    <!-- 2. Trading mode -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon" style="background: rgba(34, 197, 94, 0.12); color: #22c55e;">💲</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Trading mode</span>
                                <strong class="profile-clean-value" style="color: #22c55e;">Live</strong>
                                <span class="profile-clean-desc">Real funds</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <a href="{{ url('/futures') }}" class="settings-action-btn" style="font-size: 0.85rem;">Switch in Futures</a>
                        </div>
                    </div>

                    <!-- 3. Wallet snapshot -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon" style="background: rgba(56, 189, 248, 0.12); color: #38bdf8;">👛</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Wallet snapshot</span>
                                <strong class="profile-clean-value" id="subPageWalletSnapshotText">Main $0.00 · Trading $0.00</strong>
                                <span class="profile-clean-desc">Balances update when you deposit or trade</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <a href="{{ url('/wallet') }}" class="settings-action-btn" style="font-size: 0.85rem; color: #22c55e;">Open wallet</a>
                        </div>
                    </div>

                    <!-- 4. Preferred currency -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon" style="background: rgba(139, 92, 246, 0.12); color: #a78bfa;">💲</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Preferred currency</span>
                                <strong class="profile-clean-value" id="subPagePrefCurrText">USD</strong>
                                <span class="profile-clean-desc">Display currency across the app</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <div style="display: inline-flex; background: rgba(0, 0, 0, 0.4); padding: 2px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.1);">
                                <button type="button" id="btnCurrToggleUsd" class="curr-toggle-btn active" style="padding: 4px 10px; font-size: 0.74rem; font-weight: 800; border-radius: 6px; border: none; background: #8b5cf6; color: #fff; cursor: pointer;">USD</button>
                                <button type="button" id="btnCurrToggleInr" class="curr-toggle-btn" style="padding: 4px 10px; font-size: 0.74rem; font-weight: 800; border-radius: 6px; border: none; background: transparent; color: rgba(255,255,255,0.6); cursor: pointer;">INR</button>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Bank account -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">🏦</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Bank account</span>
                                <strong class="profile-clean-value" id="subPageBankStatusText">Not linked</strong>
                                <span class="profile-clean-desc">For INR deposits and withdrawals</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <button type="button" class="settings-action-btn btn-purple" id="btnOpenSubPageBankModal" data-open-modal="modalBankDetails" style="font-size: 0.85rem;">Add bank</button>
                        </div>
                    </div>

                    <!-- 7. Referral code -->
                    <div class="profile-clean-row">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon" style="background: rgba(59, 130, 246, 0.12); color: #60a5fa;">👥</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">Referral code</span>
                                <strong class="profile-clean-value" id="subPageReferralCodeText" style="font-family: monospace; font-weight: 800;">30EBFD7D</strong>
                                <span class="profile-clean-desc" id="subPageReferralEarnedText">0 referred · earned $0.00</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <button type="button" class="settings-action-btn" id="btnCopyReferralCode" style="font-size: 0.85rem; gap: 4px;"> Copy</button>
                            <a href="{{ url('/wallet') }}" class="settings-action-btn btn-purple" style="font-size: 0.85rem;">Details</a>
                        </div>
                    </div>

                    <!-- 8. KRYVEX Mobile App -->
                    <div class="profile-clean-row" style="border-bottom: none;">
                        <div class="profile-clean-left">
                            <div class="profile-clean-icon" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">📱</div>
                            <div class="profile-clean-meta">
                                <span class="profile-clean-label">KRYVEX Mobile App</span>
                                <strong class="profile-clean-value">Install on your phone</strong>
                                <span class="profile-clean-desc">Google Play or App Store</span>
                            </div>
                        </div>
                        <div class="profile-clean-right">
                            <button type="button" class="settings-action-btn" onclick="if(typeof openInstallAppModal==='function'){openInstallAppModal();}else if(window.openInstallAppModal){window.openInstallAppModal();}" style="font-size: 0.85rem; color: #10b981; font-weight: 700;">Install app</button>
                        </div>
                    </div>
                </div>

                <!-- METATRADER 5 STATUS CARD -->
                <div class="settings-card reveal-item" id="mt5StatusCard" hidden style="display: none; background: #0c1017; border: 1px solid rgba(255, 255, 255, 0.1); margin-bottom: 20px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                        <h2 style="font-size: 1.1rem; font-weight: 700; color: #ffffff; margin: 0;">MetaTrader 5 Status</h2>
                        <span class="mt5-badge active" id="mt5StatusBadge" style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35); color: #10b981; padding: 3px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 700;">ACTIVE</span>
                    </div>

                    <!-- Plan & Currency Summary -->
                    <div style="display: flex; gap: 30px; margin-bottom: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.06); padding-bottom: 14px; flex-wrap: wrap;">
                        <div>
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 2px;">Plan</span>
                            <strong id="mt5StatusPlan" style="font-size: 1rem; color: #ffffff; font-weight: 700;">Basic</strong>
                        </div>
                        <div>
                            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 2px;">Currency</span>
                            <strong style="font-size: 1rem; color: #ffffff; font-weight: 700;">USDT</strong>
                        </div>
                    </div>

                    <div id="mt5PendingNote" hidden style="display: none; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); color: #fef08a; padding: 10px 14px; border-radius: 10px; font-size: 0.82rem; margin-bottom: 14px;">
                        ⏳ MetaTrader 5 account requested. Your login credentials will be emailed to you or you can enter them below.
                    </div>

                    <!-- Connected View Wrap -->
                    <div id="mt5ConnectedViewWrap">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 6px; color: #22c55e; font-weight: 700; font-size: 0.88rem;">
                                <span style="width: 7px; height: 7px; border-radius: 50%; background: #22c55e;"></span>
                                <span>✓ MT5 Account Connected</span>
                            </div>
                            <button type="button" id="btnEditMt5Credentials" class="settings-action-btn" style="font-size: 0.85rem;">
                                 Edit Credentials
                            </button>
                        </div>

                        <div style="background: rgba(0, 0, 0, 0.35); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 12px; padding: 14px; display: flex; gap: 18px; flex-wrap: wrap; align-items: center;">
                            <div style="min-width: 120px; flex: 1 1 auto;">
                                <span style="font-size: 0.72rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 3px;">MT5 Login (ID)</span>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <strong id="viewMt5Login" style="font-size: 0.95rem; color: #ffffff; font-family: monospace;">--</strong>
                                    <button type="button" id="btnCopyMt5Login" style="background: none; border: none; cursor: pointer; font-size: 0.82rem;" title="Copy ID">📋</button>
                                </div>
                            </div>

                            <div style="min-width: 120px; flex: 1 1 auto;">
                                <span style="font-size: 0.72rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 3px;">MT5 Password</span>
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    <strong id="viewMt5Password" style="font-size: 0.95rem; color: #ffffff; font-family: monospace;">••••••••</strong>
                                    <button type="button" id="btnToggleViewPassword" style="background: none; border: none; cursor: pointer; font-size: 0.82rem;" title="Show Password">👁</button>
                                </div>
                            </div>

                            <div style="min-width: 120px; flex: 1 1 auto;">
                                <span style="font-size: 0.72rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 3px;">MT5 Server</span>
                                <strong id="viewMt5Server" style="font-size: 0.95rem; color: #ffffff; font-weight: 600;">Kryvex-Live-01</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Edit / Save Credentials Form Wrap -->
                    <div id="mt5CredentialsFormWrap" hidden style="display: none; margin-top: 14px; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 16px;">
                        <h4 style="font-size: 0.92rem; color: #ffffff; margin: 0 0 12px; font-weight: 700;">Enter or Update MT5 Account Credentials</h4>
                        
                        <form id="userMt5CredentialsForm">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 14px;">
                                <div>
                                    <label style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 5px;">MT5 Login (ID)</label>
                                    <input type="text" id="userMt5Login" placeholder="e.g. 070405" required style="width: 100%; height: 38px; background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 8px; color: #ffffff; padding: 0 10px; font-family: monospace; font-size: 0.9rem; box-sizing: border-box;">
                                </div>

                                <div>
                                    <label style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 5px;">MT5 Password</label>
                                    <div style="position: relative;">
                                        <input type="password" id="userMt5Password" placeholder="Enter password" required style="width: 100%; height: 38px; background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 8px; color: #ffffff; padding: 0 32px 0 10px; font-family: monospace; font-size: 0.9rem; box-sizing: border-box;">
                                        <button type="button" id="toggleUserMt5Password" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #94a3b8; cursor: pointer;">👁</button>
                                    </div>
                                </div>

                                <div>
                                    <label style="font-size: 0.75rem; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 5px;">MT5 Server</label>
                                    <input type="text" id="userMt5Server" value="Kryvex-Live-01" required style="width: 100%; height: 38px; background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 8px; color: #ffffff; padding: 0 10px; font-size: 0.9rem; box-sizing: border-box;">
                                </div>
                            </div>

                            <div style="display: flex; gap: 12px; justify-content: flex-end; align-items: center;">
                                <button type="button" id="btnCancelEditMt5" class="settings-action-btn" style="color: #94a3b8; font-size: 0.85rem;">Cancel</button>
                                <button type="submit" id="btnSaveUserMt5Credentials" class="settings-action-btn btn-purple" style="font-weight: 700; font-size: 0.85rem;">Save Credentials</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- =================================================== -->
            <!-- 3. SUPPORT & HELP DEDICATED SUB-PAGE VIEW -->
            <!-- =================================================== -->
            <div id="supportSubPageView" style="display: none; max-width: 920px; margin: 0 auto;">
                <div style="margin-bottom: 24px;">
                    <button type="button" id="btnBackToSettingsFromSupport" class="settings-action-btn" style="font-size: 0.95rem; font-weight: 700; gap: 6px;">
                        ← Back to Settings
                    </button>
                </div>

                <div class="settings-header-label reveal-item">ACCOUNT</div>
                <h1 class="reveal-item" style="font-size: 2rem; font-weight: 800; color: #fff; margin: 0 0 24px;">Support</h1>

                <!-- How can we help? Header Banner Card -->
                <div class="settings-card support-header-banner reveal-item" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.12), rgba(15, 23, 42, 0.6)); border: 1px solid rgba(139, 92, 246, 0.25); border-radius: 20px; padding: 24px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                    <div style="display: flex; align-items: center; gap: 16px; max-width: 600px;">
                        <div class="support-icon-wrap" style="width: 48px; height: 48px; border-radius: 14px; background: rgba(139, 92, 246, 0.2); border: 1px solid rgba(139, 92, 246, 0.4); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 class="support-banner-title" style="font-size: 1.25rem; font-weight: 800; color: #ffffff; margin: 0 0 4px;">How can we help?</h2>
                            <p class="support-banner-desc" style="font-size: 0.88rem; color: #94a3b8; margin: 0; line-height: 1.4;">Submit a ticket and our team will reply here. Average response is usually within a few hours during business days.</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 14px;">
                        <div class="support-stat-box" style="background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 12px 20px; text-align: center; min-width: 70px;">
                            <span class="support-stat-label" style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px;">OPEN</span>
                            <strong id="userOpenTicketsCount" class="support-stat-val" style="font-size: 1.3rem; font-weight: 800; color: #ffffff;">0</strong>
                        </div>
                        <div class="support-stat-box" style="background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 12px 20px; text-align: center; min-width: 70px;">
                            <span class="support-stat-label" style="font-size: 0.7rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 2px;">TOTAL</span>
                            <strong id="userTotalTicketsCount" class="support-stat-val" style="font-size: 1.3rem; font-weight: 800; color: #ffffff;">0</strong>
                        </div>
                    </div>
                </div>

                <!-- TOPIC Selection Grid -->
                <div style="margin-bottom: 24px;">
                    <span style="font-size: 0.72rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 12px;">TOPIC</span>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px;">
                        <div class="support-topic-card" data-topic="Deposit" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.06); display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 5v14M5 12l7 7 7-7"/>
                                </svg>
                            </div>
                            <strong style="font-size: 0.95rem; font-weight: 700; color: #ffffff; display: block; margin-bottom: 4px;">Deposit</strong>
                            <span style="font-size: 0.78rem; color: #64748b; line-height: 1.3; display: block;">INR / crypto deposit not credited</span>
                        </div>

                        <div class="support-topic-card" data-topic="Withdraw" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.06); display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
                                </svg>
                            </div>
                            <strong style="font-size: 0.95rem; font-weight: 700; color: #ffffff; display: block; margin-bottom: 4px;">Withdraw</strong>
                            <span style="font-size: 0.78rem; color: #64748b; line-height: 1.3; display: block;">Pending or failed withdrawal</span>
                        </div>

                        <div class="support-topic-card" data-topic="Trading" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.06); display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>
                                </svg>
                            </div>
                            <strong style="font-size: 0.95rem; font-weight: 700; color: #ffffff; display: block; margin-bottom: 4px;">Trading</strong>
                            <span style="font-size: 0.78rem; color: #64748b; line-height: 1.3; display: block;">Orders, positions, fees</span>
                        </div>

                        <div class="support-topic-card" data-topic="KYC" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(255, 255, 255, 0.06); display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                </svg>
                            </div>
                            <strong style="font-size: 0.95rem; font-weight: 700; color: #ffffff; display: block; margin-bottom: 4px;">KYC</strong>
                            <span style="font-size: 0.78rem; color: #64748b; line-height: 1.3; display: block;">Identity verification help</span>
                        </div>

                        <div class="support-topic-card active-topic" data-topic="Other" style="background: rgba(139, 92, 246, 0.12); border: 1px solid rgba(139, 92, 246, 0.35); border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s;">
                            <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(139, 92, 246, 0.2); display: flex; align-items: center; justify-content: center; margin-bottom: 10px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                            </div>
                            <strong style="font-size: 0.95rem; font-weight: 700; color: #ffffff; display: block; margin-bottom: 4px;">Other</strong>
                            <span style="font-size: 0.78rem; color: #94a3b8; line-height: 1.3; display: block;">Account, settings, other</span>
                        </div>
                    </div>
                </div>

                <!-- New Ticket Form Card -->
                <div class="settings-card reveal-item" style="margin-bottom: 24px;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 18px;">
                        {{-- <span style="font-size: 1.1rem;">💬</span> --}}
                        <h3 style="font-size: 1.1rem; font-weight: 800; color: #ffffff; margin: 0;">New ticket</h3>
                    </div>

                    <form id="userCreateTicketForm">
                        <div style="margin-bottom: 16px;">
                            <label style="font-size: 0.8rem; font-weight: 600; color: #94a3b8; display: block; margin-bottom: 6px;">Subject</label>
                            <input type="text" id="inputTicketSubject" placeholder="Short summary of your issue" required style="width: 100%; height: 44px; background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 10px; color: #ffffff; padding: 0 14px; font-size: 0.95rem; box-sizing: border-box;">
                        </div>

                        <div style="margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                <label style="font-size: 0.8rem; font-weight: 600; color: #94a3b8;">Describe your Issue</label>
                                <span id="ticketDescCharCount" style="font-size: 0.75rem; color: #64748b;">0/2000</span>
                            </div>
                            <textarea id="inputTicketDescription" placeholder="Include order IDs, amounts, networks, or screenshots details so we can help faster..." rows="4" maxlength="2000" required style="width: 100%; background: rgba(0, 0, 0, 0.4); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 10px; color: #ffffff; padding: 12px 14px; font-size: 0.92rem; font-family: inherit; resize: vertical; box-sizing: border-box;"></textarea>
                        </div>

                        <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                            <button type="submit" id="btnSubmitTicket" class="settings-action-btn btn-purple" style="height: 44px; padding: 0 24px; font-weight: 700; font-size: 0.92rem; display: inline-flex; align-items: center; gap: 8px;">
                                ✈ Submit ticket
                            </button>
                            <span style="font-size: 0.8rem; color: #64748b;">You'll get updates in Notifications when we reply.</span>
                        </div>
                    </form>
                </div>

                <!-- Your Tickets Card -->
                <div class="settings-card reveal-item">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            {{-- <span style="font-size: 1.1rem;">🕒</span> --}}
                            <h3 style="font-size: 1.1rem; font-weight: 800; color: #ffffff; margin: 0;">Your tickets</h3>
                        </div>
                        <span id="userTicketsTotalHeader" style="font-size: 0.8rem; color: #94a3b8;">0 total</span>
                    </div>

                    <div id="userTicketsContainer" style="display: flex; flex-direction: column; gap: 14px;">
                        <!-- Rendered by JS -->
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- =================================================== -->
    <!-- INTERACTIVE MODALS FOR SETTINGS -->
    <!-- =================================================== -->

    <!-- 1. CHANGE PASSWORD MODAL -->
    <div class="settings-modal-backdrop" id="modalChangePassword" hidden>
        <div class="settings-modal-card" style="max-width: 480px;">
            <div class="settings-modal-head">
                <h3 id="changePassModalTitle">Change Login Password</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalChangePassword">&times;</button>
            </div>

            <!-- Standard Change Password Form -->
            <form id="changePasswordForm">
                <label style="display:block;margin-bottom:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                        <span style="font-size:0.83rem;color:rgba(255,255,255,0.7);">Current Password</span>
                        <button type="button" id="btnForgotPassInSettings" style="background:none;border:none;color:#8b5cf6;font-size:0.78rem;font-weight:700;cursor:pointer;padding:0;">Forgot Password?</button>
                    </div>
                    <div style="position:relative;">
                        <input type="password" name="current_password" id="inputCurrentPassword" required style="width:100%;height:42px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 38px 0 14px;box-sizing:border-box;">
                        <button type="button" class="btn-toggle-eye" data-target="inputCurrentPassword" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:0.95rem;">👁</button>
                    </div>
                </label>

                <label style="display:block;margin-bottom:14px;">
                    <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:6px;">New Password</span>
                    <div style="position:relative;">
                        <input type="password" name="new_password" id="inputNewPassword" required style="width:100%;height:42px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 38px 0 14px;box-sizing:border-box;">
                        <button type="button" class="btn-toggle-eye" data-target="inputNewPassword" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:0.95rem;">👁</button>
                    </div>
                    <span style="font-size:0.74rem;color:rgba(255,255,255,0.4);display:block;margin-top:4px;">Min 8 chars, uppercase, lowercase, number &amp; symbol (!@#$%^&amp;*)</span>
                </label>

                <label style="display:block;margin-bottom:20px;">
                    <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:6px;">Confirm New Password</span>
                    <div style="position:relative;">
                        <input type="password" name="new_password_confirmation" id="inputConfirmPassword" required style="width:100%;height:42px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 38px 0 14px;box-sizing:border-box;">
                        <button type="button" class="btn-toggle-eye" data-target="inputConfirmPassword" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:0.95rem;">👁</button>
                    </div>
                </label>

                <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Update Password</button>
            </form>

            <!-- Forgot Password View inside Modal (Hidden by default) -->
            <div id="forgotPasswordViewInSettings" style="display: none; margin-top: 10px;">
                <p style="font-size: 0.84rem; color: rgba(255,255,255,0.6); margin-bottom: 14px;">An OTP will be sent to your registered email address (<span id="userForgotEmailText" style="color: #ffffff; font-weight: 600;">your email</span>) to reset your password.</p>
                
                <!-- Step 1: Send OTP -->
                <div id="forgotStepSendOtp">
                    <button type="button" id="btnSendResetOtpInSettings" class="settings-action-btn btn-purple" style="width: 100%; height: 44px; font-size: 0.95rem;">
                        Send OTP to Email
                    </button>
                </div>

                <!-- Step 2: Enter OTP & Reset Password -->
                <form id="forgotResetPasswordFormInSettings" style="display: none; margin-top: 12px;">
                    <label style="display:block;margin-bottom:14px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="font-size:0.83rem;color:rgba(255,255,255,0.7);">6-Digit OTP Code</span>
                            <div>
                                <span id="forgotOtpTimerWrap" style="font-size:0.78rem;color:#f59e0b;font-weight:700;">
                                    ⏳ Code expires in: <span id="forgotOtpCountdownText">120s</span>
                                </span>
                                <button type="button" id="btnResendForgotOtpInSettings" style="display:none;background:none;border:none;color:#8b5cf6;font-size:0.78rem;font-weight:700;cursor:pointer;padding:0;text-decoration:underline;">
                                    🔄 Resend OTP
                                </button>
                            </div>
                        </div>
                        <input type="text" id="inputResetOtp" maxlength="6" placeholder="Enter 6-digit OTP" required style="width:100%;height:42px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 14px;font-family:monospace;font-size:1.1rem;letter-spacing:2px;text-align:center;box-sizing:border-box;">
                    </label>

                    <label style="display:block;margin-bottom:14px;">
                        <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:6px;">New Password</span>
                        <div style="position:relative;">
                            <input type="password" id="inputForgotNewPass" required style="width:100%;height:42px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 38px 0 14px;box-sizing:border-box;">
                            <button type="button" class="btn-toggle-eye" data-target="inputForgotNewPass" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:0.95rem;">👁</button>
                        </div>
                        <span style="font-size:0.74rem;color:rgba(255,255,255,0.4);display:block;margin-top:4px;">Min 8 chars, uppercase, lowercase, number &amp; symbol (!@#$%^&amp;*)</span>
                    </label>

                    <label style="display:block;margin-bottom:20px;">
                        <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:6px;">Confirm New Password</span>
                        <div style="position:relative;">
                            <input type="password" id="inputForgotConfirmPass" required style="width:100%;height:42px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 38px 0 14px;box-sizing:border-box;">
                            <button type="button" class="btn-toggle-eye" data-target="inputForgotConfirmPass" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,0.5);cursor:pointer;font-size:0.95rem;">👁</button>
                        </div>
                    </label>

                    <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Reset Password</button>
                </form>

                <button type="button" id="btnBackToChangePassForm" style="display:block;margin-top:14px;background:none;border:none;color:rgba(255,255,255,0.6);font-size:0.83rem;cursor:pointer;width:100%;text-align:center;">
                    ← Back to Change Password
                </button>
            </div>
        </div>
    </div>

    <!-- 2. BANK DETAILS & UPI MODAL -->
    <div class="settings-modal-backdrop" id="modalBankDetails" hidden>
        <div class="settings-modal-card">
            <div class="settings-modal-head">
                <h3>Bank Account &amp; UPI Details</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalBankDetails">&times;</button>
            </div>
            <form id="bankDetailsForm">
                <label style="display:block;margin-bottom:12px;">
                    <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:4px;">Account Holder Name</span>
                    <input type="text" name="account_name" placeholder="e.g. Rahul Sharma" required style="width:100%;height:40px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 12px;">
                </label>
                <label style="display:block;margin-bottom:12px;">
                    <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:4px;">Bank Name</span>
                    <input type="text" name="bank_name" placeholder="e.g. HDFC Bank" required style="width:100%;height:40px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 12px;">
                </label>
                <label style="display:block;margin-bottom:12px;">
                    <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:4px;">Account Number</span>
                    <input type="text" name="account_number" placeholder="Enter bank account number" required style="width:100%;height:40px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 12px;">
                </label>
                <label style="display:block;margin-bottom:12px;">
                    <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:4px;">IFSC Code</span>
                    <input type="text" name="ifsc_code" placeholder="e.g. HDFC0001234" required style="width:100%;height:40px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 12px;">
                </label>
                <label style="display:block;margin-bottom:20px;">
                    <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:4px;">UPI ID (Optional)</span>
                    <input type="text" name="upi_id" placeholder="e.g. username@upi" style="width:100%;height:40px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 12px;">
                </label>
                <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Save Bank Details</button>
            </form>
        </div>
    </div>

    <!-- 3. TWO-FACTOR AUTHENTICATION MODAL -->
    <div class="settings-modal-backdrop" id="modalTwoFactor" hidden>
        <div class="settings-modal-card" style="max-width: 440px;">
            <div class="settings-modal-head">
                <h3>Two-Factor Authentication (2FA)</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalTwoFactor">&times;</button>
            </div>
            <form id="twoFactorSettingsForm">
                <p style="font-size:0.84rem; color:rgba(255,255,255,0.6); margin-bottom:18px;">When enabled, a 6-digit email verification OTP code will be required every time you log in to secure your account.</p>
                
                <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:16px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <strong style="display:block; font-size:0.95rem; color:#fff;">Email Login 2FA</strong>
                        <span style="font-size:0.78rem; color:rgba(255,255,255,0.45);" id="twoFactorMasterStatusLabel">Disabled (Default)</span>
                    </div>
                    <label class="shortcuts-toggle-wrapper" style="position: relative; display: inline-flex; align-items: center; cursor: pointer; width: 46px; height: 26px;">
                        <input type="checkbox" id="twoFactorMasterToggle" style="opacity: 0; width: 0; height: 0;">
                        <span class="shortcuts-slider" id="twoFactorSliderBg" style="position: absolute; inset: 0; background-color: rgba(255,255,255,0.15); border-radius: 20px; transition: 0.3s;"></span>
                        <span class="shortcuts-knob" id="twoFactorKnob" style="position: absolute; left: 3px; top: 3px; width: 20px; height: 20px; background-color: #ffffff; border-radius: 50%; transition: 0.3s; transform: translateX(0px);"></span>
                    </label>
                </div>

                <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Save 2FA Settings</button>
            </form>
        </div>
    </div>

    <!-- 4. NOTIFICATION PREFERENCES MODAL -->
    <div class="settings-modal-backdrop" id="modalNotifPreferences" hidden>
        <div class="settings-modal-card" style="max-width: 440px;">
            <div class="settings-modal-head">
                <h3>Notification Preferences</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalNotifPreferences">&times;</button>
            </div>
            <form id="notificationSettingsForm">
                <p style="font-size:0.84rem; color:rgba(255,255,255,0.6); margin-bottom:18px;">Enable or disable all platform notifications across app and website.</p>
                
                <div style="background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:16px; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <strong style="display:block; font-size:0.95rem; color:#fff;">Allow Notifications</strong>
                        <span style="font-size:0.78rem; color:rgba(255,255,255,0.45);" id="notifMasterStatusLabel">Receive trades, deposits, security &amp; system alerts</span>
                    </div>
                    <label class="shortcuts-toggle-wrapper" style="position: relative; display: inline-flex; align-items: center; cursor: pointer; width: 46px; height: 26px;">
                        <input type="checkbox" id="notifMasterToggle" checked style="opacity: 0; width: 0; height: 0;">
                        <span class="shortcuts-slider" id="notifMasterSliderBg" style="position: absolute; inset: 0; background-color: #3b82f6; border-radius: 20px; transition: 0.3s;"></span>
                        <span class="shortcuts-knob" id="notifMasterKnob" style="position: absolute; left: 3px; top: 3px; width: 20px; height: 20px; background-color: #ffffff; border-radius: 50%; transition: 0.3s; transform: translateX(20px);"></span>
                    </label>
                </div>

                <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Save Notification Settings</button>
            </form>
        </div>
    </div>

    <!-- 6. CLOSE ALL SOUND MODAL -->
    <div class="settings-modal-backdrop" id="modalTradeSound" hidden>
        <div class="settings-modal-card">
            <div class="settings-modal-head">
                <h3>Futures Position Close Sound</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalTradeSound">&times;</button>
            </div>
            <form id="tradeSoundForm">
                <label style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;margin-bottom:20px;">
                    <div>
                        <strong style="display:block;font-size:0.9rem;color:#fff;">Position Close Sound Effect</strong>
                        <span style="font-size:0.76rem;color:rgba(255,255,255,0.45);">Play sound effect when closing any position in Futures</span>
                    </div>
                    <input type="checkbox" id="tradeSoundEnable" checked>
                </label>
                <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Save Sound Settings</button>
            </form>
        </div>
    </div>

    <!-- ORDER CONFIRMATION REMINDERS MODAL -->
    <div class="settings-modal-backdrop" id="modalOrderConfirm" hidden>
        <div class="settings-modal-card" style="max-width: 480px;">
            <div class="settings-modal-head">
                <h3>Order Confirmation Reminders</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalOrderConfirm">&times;</button>
            </div>
            <form id="orderConfirmSettingsForm">
                <p style="font-size:0.84rem; color:rgba(255,255,255,0.6); margin-bottom:16px;">If enabled, you will need to reconfirm every time an order is submitted or closed.</p>
                
                <!-- Master Toggle -->
                <div style="background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.25); border-radius:12px; padding:14px 16px; margin-bottom:18px; display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <strong style="display:block; font-size:0.95rem; color:#fff;">Master Reminders Switch</strong>
                        <span style="font-size:0.78rem; color:rgba(255,255,255,0.5);" id="orderConfirmMasterStatusLabel">Enable or disable all confirmation popups at once</span>
                    </div>
                    <label class="shortcuts-toggle-wrapper" style="position: relative; display: inline-flex; align-items: center; cursor: pointer; width: 46px; height: 26px;">
                        <input type="checkbox" id="orderConfirmMasterToggle" checked style="opacity: 0; width: 0; height: 0;">
                        <span class="shortcuts-slider" id="orderConfirmMasterSliderBg" style="position: absolute; inset: 0; background-color: #3b82f6; border-radius: 20px; transition: 0.3s;"></span>
                        <span class="shortcuts-knob" id="orderConfirmMasterKnob" style="position: absolute; left: 3px; top: 3px; width: 20px; height: 20px; background-color: #ffffff; border-radius: 50%; transition: 0.3s; transform: translateX(20px);"></span>
                    </label>
                </div>

                <!-- Individual Toggles Grid -->
                <div id="orderConfirmIndividualList" style="display:flex; flex-direction:column; gap:10px; margin-bottom:22px;">
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div>
                            <strong style="display:block; font-size:0.9rem; color:#fff;">Market Order Confirmation</strong>
                            <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">Show confirmation popup when submitting Market orders</span>
                        </div>
                        <input type="checkbox" id="orderConfirmMarketToggle" checked style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>

                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div>
                            <strong style="display:block; font-size:0.9rem; color:#fff;">Limit Order Confirmation</strong>
                            <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">Show confirmation popup when submitting Limit orders</span>
                        </div>
                        <input type="checkbox" id="orderConfirmLimitToggle" checked style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>

                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div>
                            <strong style="display:block; font-size:0.9rem; color:#fff;">Stop / Stop-Limit Order Confirmation</strong>
                            <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">Show confirmation popup when submitting Stop orders</span>
                        </div>
                        <input type="checkbox" id="orderConfirmStopToggle" checked style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>

                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div>
                            <strong style="display:block; font-size:0.9rem; color:#fff;">Close Single Position Confirmation</strong>
                            <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">Show confirmation popup when closing an open position</span>
                        </div>
                        <input type="checkbox" id="orderConfirmCloseSingleToggle" checked style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>

                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div>
                            <strong style="display:block; font-size:0.9rem; color:#fff;">Close All Positions Confirmation</strong>
                            <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">Show confirmation popup when closing all open positions</span>
                        </div>
                        <input type="checkbox" id="orderConfirmCloseAllToggle" checked style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                </div>

                <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Save Reminders Preference</button>
            </form>
        </div>
    </div>

    <!-- 7. EDIT NICKNAME MODAL -->
    <div class="settings-modal-backdrop" id="modalProfileEdit" hidden>
        <div class="settings-modal-card" style="max-width: 460px;">
            <div class="settings-modal-head">
                <h3>Edit Profile Name</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalProfileEdit">&times;</button>
            </div>
            <form id="profileNicknameForm">
                <label style="display:block;margin-bottom:16px;">
                    <span style="display:block;font-size:0.83rem;color:rgba(255,255,255,0.7);margin-bottom:6px;">Full Name / Nickname</span>
                    <input type="text" id="inputProfileNickname" name="name" placeholder="Enter your full name" required style="width:100%;height:42px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:10px;color:#fff;padding:0 14px;box-sizing:border-box;font-size:0.95rem;">
                </label>
                <button type="submit" id="btnSaveProfileNickname" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Save Name</button>
            </form>
        </div>
    </div>

    <!-- 8. COLOR PREFERENCE MODAL -->
    <div class="settings-modal-backdrop" id="modalColorPref" hidden>
        <div class="settings-modal-card" style="max-width: 460px;">
            <div class="settings-modal-head">
                <h3>Color Preference</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalColorPref">&times;</button>
            </div>
            <form id="colorPrefForm">
                <p style="font-size:0.83rem; color:rgba(255,255,255,0.6); margin-bottom:16px;">Choose how price movement colors are displayed across trading charts and tickers.</p>
                <div style="display:flex; flex-direction:column; gap:12px; margin-bottom:22px;">
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:14px 16px; cursor:pointer;">
                        <div>
                            <strong style="display:block; font-size:0.92rem; color:#fff;">Green Up / Red Down</strong>
                            <span style="font-size:0.78rem; color:#22c55e;">▲ Bullish Green &nbsp;|&nbsp; <span style="color:#ef4444;">▼ Bearish Red</span></span>
                        </div>
                        <input type="radio" name="color_preference" value="green_up" checked style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:14px 16px; cursor:pointer;">
                        <div>
                            <strong style="display:block; font-size:0.92rem; color:#fff;">Red Up / Green Down</strong>
                            <span style="font-size:0.78rem; color:#ef4444;">▲ Bullish Red &nbsp;|&nbsp; <span style="color:#22c55e;">▼ Bearish Green</span></span>
                        </div>
                        <input type="radio" name="color_preference" value="red_up" style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                </div>
                <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Save Color Preference</button>
            </form>
        </div>
    </div>

    <!-- 9. KEYBOARD SHORTCUTS MODAL -->
    <div class="settings-modal-backdrop" id="modalShortcuts" hidden>
        <div class="settings-modal-card" style="max-width: 520px;">
            <div class="settings-modal-head">
                <h3>Keyboard Shortcuts Settings</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalShortcuts">&times;</button>
            </div>
            <form id="shortcutsSettingsForm">
                <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.25); border-radius:12px; padding:12px 16px; margin-bottom:18px;">
                    <div>
                        <strong style="display:block; font-size:0.92rem; color:#fff;">Master Keyboard Shortcuts Switch</strong>
                        <span style="font-size:0.76rem; color:rgba(255,255,255,0.5);">Enable or disable all hotkeys globally</span>
                    </div>
                    <input type="checkbox" id="shortcutsMasterToggle" checked style="accent-color:#3b82f6; width:20px; height:20px;">
                </div>

                <p style="font-size:0.8rem; color:rgba(255,255,255,0.5); margin-bottom:12px;">Configure individual shortcut hotkeys and toggles:</p>

                <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px; max-height:280px; overflow-y:auto; padding-right:4px;">
                    <!-- Shortcut Item 1 -->
                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:10px 14px;">
                        <div>
                            <strong style="display:block; font-size:0.88rem; color:#fff;">Place Order</strong>
                            <span style="font-size:0.75rem; color:#f59e0b; font-family:monospace;">Shift + Enter</span>
                        </div>
                        <input type="checkbox" class="shortcut-key-item" data-key="place_order" checked style="accent-color:#3b82f6; width:18px; height:18px;">
                    </div>

                    <!-- Shortcut Item 2 -->
                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:10px 14px;">
                        <div>
                            <strong style="display:block; font-size:0.88rem; color:#fff;">Cancel All Open Orders</strong>
                            <span style="font-size:0.75rem; color:#f59e0b; font-family:monospace;">Alt + C</span>
                        </div>
                        <input type="checkbox" class="shortcut-key-item" data-key="cancel_orders" checked style="accent-color:#3b82f6; width:18px; height:18px;">
                    </div>

                    <!-- Shortcut Item 3 -->
                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:10px 14px;">
                        <div>
                            <strong style="display:block; font-size:0.88rem; color:#fff;">Toggle Fullscreen Chart</strong>
                            <span style="font-size:0.75rem; color:#f59e0b; font-family:monospace;">Alt + T</span>
                        </div>
                        <input type="checkbox" class="shortcut-key-item" data-key="chart_view" checked style="accent-color:#3b82f6; width:18px; height:18px;">
                    </div>

                    <!-- Shortcut Item 5 -->
                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:10px 14px;">
                        <div>
                            <strong style="display:block; font-size:0.88rem; color:#fff;">Quick Buy / Long</strong>
                            <span style="font-size:0.75rem; color:#f59e0b; font-family:monospace;">B</span>
                        </div>
                        <input type="checkbox" class="shortcut-key-item" data-key="quick_buy" checked style="accent-color:#3b82f6; width:18px; height:18px;">
                    </div>

                    <!-- Shortcut Item 6 -->
                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:10px 14px;">
                        <div>
                            <strong style="display:block; font-size:0.88rem; color:#fff;">Quick Sell / Short</strong>
                            <span style="font-size:0.75rem; color:#f59e0b; font-family:monospace;">S</span>
                        </div>
                        <input type="checkbox" class="shortcut-key-item" data-key="quick_sell" checked style="accent-color:#3b82f6; width:18px; height:18px;">
                    </div>

                    <!-- Shortcut Item 7 -->
                    <div style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:10px; padding:10px 14px;">
                        <div>
                            <strong style="display:block; font-size:0.88rem; color:#fff;">Close All Positions</strong>
                            <span style="font-size:0.75rem; color:#f59e0b; font-family:monospace;">Shift + X</span>
                        </div>
                        <input type="checkbox" class="shortcut-key-item" data-key="close_all" checked style="accent-color:#3b82f6; width:18px; height:18px;">
                    </div>
                </div>

                <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Save Shortcuts</button>
            </form>
        </div>
    </div>

    <!-- 10. NOTIFICATION LANGUAGE MODAL -->
    <div class="settings-modal-backdrop" id="modalNotifLanguage" hidden>
        <div class="settings-modal-card" style="max-width: 480px;">
            <div class="settings-modal-head">
                <h3>Select Notification &amp; App Language</h3>
                <button type="button" class="settings-modal-close" data-close-modal="modalNotifLanguage">&times;</button>
            </div>
            <form id="notifLanguageForm">
                <p style="font-size:0.83rem; color:rgba(255,255,255,0.6); margin-bottom:16px;">Select your preferred language for in-app notifications, emails, and platform interface.</p>
                <div style="display:flex; flex-direction:column; gap:10px; margin-bottom:20px; max-height:280px; overflow-y:auto; padding-right:4px;">
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:1.4rem;">🇬🇧</span>
                            <div>
                                <strong style="display:block; font-size:0.92rem; color:#fff;">English</strong>
                                <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">Default</span>
                            </div>
                        </div>
                        <input type="radio" name="notif_language" value="English" checked style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:1.4rem;">🇮🇳</span>
                            <div>
                                <strong style="display:block; font-size:0.92rem; color:#fff;">ગુજરાતી (Gujarati)</strong>
                                <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">અધિકૃત ભાષા</span>
                            </div>
                        </div>
                        <input type="radio" name="notif_language" value="Gujarati" style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:1.4rem;">🇮🇳</span>
                            <div>
                                <strong style="display:block; font-size:0.92rem; color:#fff;">हिन्दी (Hindi)</strong>
                                <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">राजभाषा</span>
                            </div>
                        </div>
                        <input type="radio" name="notif_language" value="Hindi" style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:1.4rem;">🇪🇸</span>
                            <div>
                                <strong style="display:block; font-size:0.92rem; color:#fff;">Español (Spanish)</strong>
                                <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">Spanish</span>
                            </div>
                        </div>
                        <input type="radio" name="notif_language" value="Spanish" style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:1.4rem;">🇫🇷</span>
                            <div>
                                <strong style="display:block; font-size:0.92rem; color:#fff;">Français (French)</strong>
                                <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">French</span>
                            </div>
                        </div>
                        <input type="radio" name="notif_language" value="French" style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:1.4rem;">🇩🇪</span>
                            <div>
                                <strong style="display:block; font-size:0.92rem; color:#fff;">Deutsch (German)</strong>
                                <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">German</span>
                            </div>
                        </div>
                        <input type="radio" name="notif_language" value="German" style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                    <label style="display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:12px 16px; cursor:pointer;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            <span style="font-size:1.4rem;">🇨🇳</span>
                            <div>
                                <strong style="display:block; font-size:0.92rem; color:#fff;">简体中文 (Chinese)</strong>
                                <span style="font-size:0.75rem; color:rgba(255,255,255,0.45);">Simplified Chinese</span>
                            </div>
                        </div>
                        <input type="radio" name="notif_language" value="Chinese" style="accent-color: #3b82f6; width: 18px; height: 18px;">
                    </label>
                </div>
                <button type="submit" class="settings-action-btn btn-purple" style="width:100%;height:44px;font-size:0.95rem;">Save Language Preference</button>
            </form>
        </div>
    </div>

    <!-- 12. DOWNLOAD PERSONAL DATA MODAL -->
    <div class="settings-modal-backdrop" id="modalDownloadData" hidden>
        <div class="settings-modal-card" style="max-width: 500px;">
            <div class="settings-modal-head">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size: 1.3rem;">📁</span>
                    <h3 style="margin:0;">Download Personal Data</h3>
                </div>
                <button type="button" class="settings-modal-close" data-close-modal="modalDownloadData">&times;</button>
            </div>

            <div style="padding: 16px 0 6px;">
                <p style="font-size: 0.85rem; color: rgba(255,255,255,0.7); line-height: 1.5; margin-bottom: 14px;">
                    Your official Kryvex personal data archive contains your complete verified account records, including:
                </p>

                <div style="display:flex; flex-direction:column; gap:10px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); border-radius:14px; padding:16px; margin-bottom: 20px;">
                    <div style="display:flex; align-items:center; gap:10px; color:#fff; font-size: 0.84rem; font-weight:600;">
                        <span style="color:#22c55e;">✓</span> <span>Account Profile &amp; KYC Verification Status</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; color:#fff; font-size: 0.84rem; font-weight:600;">
                        <span style="color:#22c55e;">✓</span> <span>Linked Bank Account &amp; Settlement Details</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; color:#fff; font-size: 0.84rem; font-weight:600;">
                        <span style="color:#22c55e;">✓</span> <span>Main Wallet &amp; Trading Sub-Account Balances</span>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px; color:#fff; font-size: 0.84rem; font-weight:600;">
                        <span style="color:#22c55e;">✓</span> <span>Ledger Transactions &amp; Withdrawal History</span>
                    </div>
                </div>

                <div style="display:flex; gap:12px;">
                    <button type="button" class="settings-action-btn" data-close-modal="modalDownloadData" style="flex:1; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.1); color:#fff; height:44px;">Cancel</button>
                    <button type="button" id="btnConfirmDownloadPersonalData" class="settings-action-btn btn-purple" style="flex:2; height:44px; font-weight:800; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <span>📄 Download Official PDF</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 13. EXPORT TRANSACTION RECORDS MODAL -->
    <div class="settings-modal-backdrop" id="modalExportTransactions" hidden>
        <div class="settings-modal-card" style="max-width: 500px;">
            <div class="settings-modal-head">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size: 1.3rem;">📊</span>
                    <h3 style="margin:0;">Export Transaction Records</h3>
                </div>
                <button type="button" class="settings-modal-close" data-close-modal="modalExportTransactions">&times;</button>
            </div>

            <div style="padding: 16px 0 6px;">
                <p style="font-size: 0.85rem; color: rgba(255,255,255,0.7); line-height: 1.5; margin-bottom: 14px;">
                    Export your complete financial transaction ledger, deposits, withdrawals, trading fees, and realized PnL records.
                </p>

                <!-- Filter Controls -->
                <div style="display:flex; flex-direction:column; gap:12px; margin-bottom: 20px;">
                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:700; color:rgba(255,255,255,0.7); margin-bottom:6px;">Currency</label>
                        <select id="exportTxCurrencySelect" style="width:100%; height:40px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:10px; color:#fff; padding:0 12px; font-size:0.85rem;">
                            <option value="all" style="background:#0f172a; color:#fff;">All Currencies (INR &amp; USDT)</option>
                            <option value="INR" style="background:#0f172a; color:#fff;">INR Only (₹)</option>
                            <option value="USDT" style="background:#0f172a; color:#fff;">USDT Only ($)</option>
                        </select>
                    </div>

                    <div>
                        <label style="display:block; font-size:0.8rem; font-weight:700; color:rgba(255,255,255,0.7); margin-bottom:6px;">Transaction Type</label>
                        <select id="exportTxTypeSelect" style="width:100%; height:40px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:10px; color:#fff; padding:0 12px; font-size:0.85rem;">
                            <option value="all" style="background:#0f172a; color:#fff;">All Transaction Types</option>
                            <option value="deposit" style="background:#0f172a; color:#fff;">Deposits</option>
                            <option value="withdrawal" style="background:#0f172a; color:#fff;">Withdrawals</option>
                            <option value="trade" style="background:#0f172a; color:#fff;">Trading PnL &amp; Fees</option>
                            <option value="transfer" style="background:#0f172a; color:#fff;">Internal Transfers</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="button" id="btnExportTxCsv" class="settings-action-btn" style="flex:1; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); color:#fff; height:44px; font-weight:700; display:flex; align-items:center; justify-content:center; gap:6px;">
                        <span>📥 Download CSV</span>
                    </button>
                    <button type="button" id="btnExportTxPdf" class="settings-action-btn btn-purple" style="flex:1; height:44px; font-weight:800; display:flex; align-items:center; justify-content:center; gap:6px;">
                        <span>📑 Download PDF</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 14. CLOSE / DELETE ACCOUNT MODAL -->
    <div class="settings-modal-backdrop" id="modalCloseAccount" hidden>
        <div class="settings-modal-card" style="max-width: 520px; border-color: rgba(239, 68, 68, 0.35);">
            <div class="settings-modal-head" style="border-bottom: 1px solid rgba(239, 68, 68, 0.2); padding-bottom: 14px;">
                <div style="display:flex; align-items:center; gap:10px;">
                    <span style="font-size: 1.4rem;">⚠️</span>
                    <h3 style="margin:0; color:#ef4444;" id="modalCloseAccountHeading">Request Account Deletion</h3>
                </div>
                <button type="button" class="settings-modal-close" data-close-modal="modalCloseAccount">&times;</button>
            </div>

            <!-- VIEW 1: REQUEST FORM VIEW -->
            <form id="formRequestDeleteAccount" style="padding: 16px 0 6px;">
                <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; padding: 14px 16px; margin-bottom: 18px;">
                    <strong style="display:block; color:#f87171; font-size: 0.9rem; margin-bottom: 4px;">Important Notice:</strong>
                    <p style="font-size: 0.82rem; color: rgba(255,255,255,0.75); line-height: 1.5; margin: 0;">
                        Submitting this request will send your account to the administration for review. Once approved, your login access will be permanently disabled. Please make sure all funds are withdrawn prior to deletion.
                    </p>
                </div>

                <!-- REAL-TIME PREREQUISITES CHECKLIST -->
                <div id="deletionPrereqsContainer" style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 14px 16px; margin-bottom: 18px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 10px;">
                        <strong style="color:#cbd5e1; font-size: 0.85rem;">Account Closure Requirements:</strong>
                        <span id="deletionPrereqsStatusBadge" style="font-size:0.75rem; font-weight:700; padding:2px 8px; border-radius:4px; background:rgba(255,255,255,0.06); color:#94a3b8;">Checking...</span>
                    </div>
                    
                    <div style="display:flex; flex-direction:column; gap:8px; font-size:0.82rem;" id="deletionChecksList">
                        <div id="checkItemBalance" style="display:flex; align-items:center; gap:8px; color:#94a3b8;">
                            <span class="check-icon" style="font-weight:700;">⏳</span> <span class="check-label">All wallet &amp; trading account balances must be $0.00</span>
                        </div>
                        <div id="checkItemPositions" style="display:flex; align-items:center; gap:8px; color:#94a3b8;">
                            <span class="check-icon" style="font-weight:700;">⏳</span> <span class="check-label">No open trading positions (Futures &amp; Options)</span>
                        </div>
                        <div id="checkItemOrders" style="display:flex; align-items:center; gap:8px; color:#94a3b8;">
                            <span class="check-icon" style="font-weight:700;">⏳</span> <span class="check-label">No active or pending orders</span>
                        </div>
                        <div id="checkItemWithdrawals" style="display:flex; align-items:center; gap:8px; color:#94a3b8;">
                            <span class="check-icon" style="font-weight:700;">⏳</span> <span class="check-label">No pending withdrawal requests in progress</span>
                        </div>
                    </div>

                    <div id="deletionPrereqsWarning" style="display:none; margin-top:12px; padding:10px 12px; border-radius:8px; background:rgba(239, 68, 68, 0.15); border:1px solid rgba(239, 68, 68, 0.35); color:#fca5a5; font-size:0.8rem; line-height:1.45;">
                    </div>
                </div>

                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-size:0.82rem; font-weight:700; color:#cbd5e1; margin-bottom:6px;">
                        Reason for closure <span style="color:#ef4444;">*</span>
                    </label>
                    <select id="deleteAccountReasonSelect" style="width:100%; height:42px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:10px; color:#fff; padding:0 12px; font-size:0.85rem; margin-bottom:8px;" required>
                        <option value="" disabled selected style="background:#0f172a; color:#fff;">Select a reason...</option>
                        <option value="I want to take a break from trading" style="background:#0f172a; color:#fff;">I want to take a break from trading</option>
                        <option value="Switching to another platform" style="background:#0f172a; color:#fff;">Switching to another platform</option>
                        <option value="Too complex / not satisfied with features" style="background:#0f172a; color:#fff;">Too complex / not satisfied with features</option>
                        <option value="Security or privacy concerns" style="background:#0f172a; color:#fff;">Security or privacy concerns</option>
                        <option value="Other reason" style="background:#0f172a; color:#fff;">Other reason</option>
                    </select>
                    <textarea id="deleteAccountReasonDetails" placeholder="Additional details (optional)..." rows="2" style="width:100%; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:10px; color:#fff; padding:10px 12px; font-size:0.85rem; resize:none;"></textarea>
                </div>

                <div style="margin-bottom: 20px;">
                    <label style="display:block; font-size:0.82rem; font-weight:700; color:#cbd5e1; margin-bottom:6px;">
                        Enter your current password to confirm <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="password" id="deleteAccountPassword" placeholder="Your password" style="width:100%; height:42px; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12); border-radius:10px; color:#fff; padding:0 12px; font-size:0.85rem;" required autocomplete="current-password">
                </div>

                <div id="deleteAccountFeedback" style="display:none; font-size:0.82rem; margin-bottom:14px; padding:10px 14px; border-radius:8px;"></div>

                <div style="display:flex; gap:12px;">
                    <button type="button" class="settings-action-btn" data-close-modal="modalCloseAccount" style="flex:1; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.1); color:#fff; height:44px;">Cancel</button>
                    <button type="submit" id="btnSubmitDeleteAccount" class="settings-action-btn btn-danger" style="flex:2; height:44px; font-weight:800; background:#dc2626; color:#fff; border:none; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <span>Submit Deletion Request</span>
                    </button>
                </div>
            </form>

            <!-- VIEW 2: ALREADY PENDING VIEW -->
            <div id="modalCloseAccountPendingView" style="display:none; padding: 16px 0 6px;">
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.35); border-radius: 12px; padding: 16px; margin-bottom: 18px;">
                    <strong style="display:block; color:#f59e0b; font-size: 0.95rem; margin-bottom: 6px;">Deletion Request Already Submitted</strong>
                    <p style="font-size: 0.84rem; color: rgba(255,255,255,0.8); line-height: 1.5; margin: 0 0 10px 0;">
                        Your account deletion request is currently waiting for administrator review and approval.
                    </p>
                    <div style="font-size: 0.82rem; color: #94a3b8; display:flex; flex-direction:column; gap:4px;">
                        <div>Reason: <strong id="modalPendingReasonText" style="color: #fff;">--</strong></div>
                        <div>Date: <strong id="modalPendingDateText" style="color: #fff;">--</strong></div>
                        <div>Account State: <span style="background: rgba(245, 158, 11, 0.2); color: #f59e0b; padding: 2px 8px; border-radius: 4px; font-weight: 700;">Read-Only Mode</span></div>
                    </div>
                </div>

                <p style="font-size: 0.82rem; color: #cbd5e1; margin-bottom: 18px; line-height: 1.45;">
                    While your request is under review, all wallet transactions, trading, and deposit/withdrawal actions are disabled. If you want to keep your account, you can cancel this request below.
                </p>

                <div id="deleteAccountPendingFeedback" style="display:none; font-size:0.82rem; margin-bottom:14px; padding:10px 14px; border-radius:8px;"></div>

                <div style="display:flex; gap:12px;">
                    <button type="button" class="settings-action-btn" data-close-modal="modalCloseAccount" style="flex:1; background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.1); color:#fff; height:44px;">Close</button>
                    <button type="button" id="btnCancelDeletionInsideModal" class="settings-action-btn btn-purple" style="flex:2; height:44px; font-weight:800; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <span>Cancel Deletion Request</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FLOATING SCROLL TO TOP BUTTON -->
    <button type="button" id="settingsScrollToTopBtn" aria-label="Scroll to top" title="Scroll to top">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 15l-6-6-6 6"/>
        </svg>
    </button>
@endsection
