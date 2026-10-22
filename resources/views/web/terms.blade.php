@extends("layouts.app")

@section("title", "Terms of Service - Kryvex Exchange")

@push("styles")
<style>
    .terms-page {
        background-color: var(--kv-bg, #080b11);
        color: var(--kv-text, #f8fafc);
        min-height: 100vh;
    }
    .terms-card {
        background: linear-gradient(135deg, rgba(20, 21, 30, 0.95), rgba(13, 14, 20, 0.98));
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        backdrop-filter: blur(12px);
    }
    .terms-section {
        scroll-margin-top: 100px;
    }
    .terms-toc-link {
        display: block;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #94a3b8;
        transition: all 0.2s ease;
        border-left: 2px solid transparent;
    }
    .terms-toc-link:hover, .terms-toc-link.is-active {
        color: #ffffff;
        background: rgba(139, 92, 246, 0.12);
        border-left-color: #8b5cf6;
    }
    .terms-callout-warning {
        background: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.25);
        border-radius: 14px;
        padding: 18px 20px;
    }
    .terms-callout-info {
        background: rgba(139, 92, 246, 0.08);
        border: 1px solid rgba(139, 92, 246, 0.25);
        border-radius: 14px;
        padding: 18px 20px;
    }
    .terms-table {
        width: 100%;
        border-collapse: collapse;
        margin: 16px 0;
        font-size: 0.85rem;
    }
    .terms-table th, .terms-table td {
        padding: 10px 14px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        text-align: left;
    }
    .terms-table th {
        background: rgba(255, 255, 255, 0.04);
        color: #e2e8f0;
        font-weight: 700;
    }
    .terms-table td {
        color: #94a3b8;
    }

    .terms-header-badge {
        font-size: 0.75rem;
        font-weight: 700;
        color: #c084fc;
        background: rgba(88, 28, 135, 0.6);
        border: 1px solid rgba(107, 33, 168, 0.5);
        padding: 2px 10px;
        border-radius: 9999px;
        display: inline-block;
    }

    /* ==========================================================================
       LIGHT THEME STYLES FOR TERMS OF SERVICE & RISK DISCLOSURE
       ========================================================================== */
    html[data-theme="light"] .terms-page,
    html.theme-light .terms-page {
        background-color: #f8fafc !important;
        color: #0f172a !important;
    }
    html[data-theme="light"] .terms-header-badge,
    html[data-theme="light"] .terms-page .bg-purple-950\/60,
    html.theme-light .terms-header-badge,
    html.theme-light .terms-page .bg-purple-950\/60 {
        background: #f3e8ff !important;
        border: 1px solid #d8b4fe !important;
        color: #7c3aed !important;
    }
    html[data-theme="light"] .terms-page .text-slate-100,
    html[data-theme="light"] .terms-page .text-slate-200,
    html[data-theme="light"] .terms-page .text-slate-300,
    html.theme-light .terms-page .text-slate-100,
    html.theme-light .terms-page .text-slate-200,
    html.theme-light .terms-page .text-slate-300 {
        color: #334155 !important;
    }
    html[data-theme="light"] .terms-page .text-slate-400,
    html[data-theme="light"] .terms-page .text-slate-500,
    html.theme-light .terms-page .text-slate-400,
    html.theme-light .terms-page .text-slate-500 {
        color: #64748b !important;
    }
    html[data-theme="light"] .terms-page .text-white,
    html.theme-light .terms-page .text-white {
        color: #0f172a !important;
    }
    html[data-theme="light"] .terms-card,
    html.theme-light .terms-card {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05) !important;
        color: #0f172a !important;
    }
    html[data-theme="light"] .terms-card .border-white\/10,
    html[data-theme="light"] .terms-card .border-white\/5,
    html.theme-light .terms-card .border-white\/10,
    html.theme-light .terms-card .border-white\/5 {
        border-color: #e2e8f0 !important;
    }
    html[data-theme="light"] .terms-card .bg-white\/5,
    html.theme-light .terms-card .bg-white\/5 {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
    }
    html[data-theme="light"] .terms-toc-link,
    html.theme-light .terms-toc-link {
        color: #64748b !important;
    }
    html[data-theme="light"] .terms-toc-link:hover,
    html.theme-light .terms-toc-link:hover,
    html[data-theme="light"] .terms-toc-link.is-active,
    html.theme-light .terms-toc-link.is-active {
        color: #7c3aed !important;
        background: rgba(124, 58, 237, 0.1) !important;
        border-left-color: #7c3aed !important;
    }
    html[data-theme="light"] .terms-toc-link[href="#sec8"],
    html.theme-light .terms-toc-link[href="#sec8"] {
        color: #d97706 !important;
    }
    html[data-theme="light"] .terms-callout-warning,
    html.theme-light .terms-callout-warning {
        background: #fef2f2 !important;
        border: 1px solid #fecaca !important;
    }
    html[data-theme="light"] .terms-callout-warning strong,
    html.theme-light .terms-callout-warning strong {
        color: #b91c1c !important;
    }
    html[data-theme="light"] .terms-callout-warning p,
    html.theme-light .terms-callout-warning p {
        color: #7f1d1d !important;
    }
    html[data-theme="light"] .terms-callout-info,
    html.theme-light .terms-callout-info {
        background: #faf5ff !important;
        border: 1px solid #e9d5ff !important;
    }
    html[data-theme="light"] .terms-callout-info strong,
    html.theme-light .terms-callout-info strong {
        color: #0f172a !important;
    }
    html[data-theme="light"] .terms-callout-info span,
    html.theme-light .terms-callout-info span {
        color: #64748b !important;
    }
    html[data-theme="light"] .terms-table th,
    html.theme-light .terms-table th {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border: 1px solid #cbd5e1 !important;
    }
    html[data-theme="light"] .terms-table td,
    html.theme-light .terms-table td {
        border: 1px solid #e2e8f0 !important;
        color: #334155 !important;
    }
    html[data-theme="light"] .terms-table td.font-bold,
    html.theme-light .terms-table td.font-bold {
        color: #0f172a !important;
    }
    
    /* Section 8: Risk Disclosure Card in Light Mode */
    html[data-theme="light"] #sec8,
    html.theme-light #sec8 {
        background: linear-gradient(135deg, #fffbeb, #ffffff) !important;
        border-color: #fde68a !important;
    }
    html[data-theme="light"] #sec8 h2,
    html.theme-light #sec8 h2 {
        color: #b45309 !important;
    }
    html[data-theme="light"] #sec8 span.border-amber-500\/40,
    html.theme-light #sec8 span.border-amber-500\/40 {
        background: rgba(217, 119, 6, 0.15) !important;
        border-color: rgba(217, 119, 6, 0.3) !important;
        color: #b45309 !important;
    }
    html[data-theme="light"] #sec8 ul,
    html[data-theme="light"] #sec8 li,
    html.theme-light #sec8 ul,
    html.theme-light #sec8 li {
        color: #78350f !important;
    }

    html[data-theme="light"] .terms-page button[onclick="window.print()"],
    html.theme-light .terms-page button[onclick="window.print()"] {
        background: rgba(124, 58, 237, 0.12) !important;
        color: #7c3aed !important;
        border: 1px solid rgba(124, 58, 237, 0.3) !important;
    }
    html[data-theme="light"] .terms-page button[onclick="window.print()"]:hover,
    html.theme-light .terms-page button[onclick="window.print()"]:hover {
        background: #7c3aed !important;
        color: #ffffff !important;
    }
    /* Contact Support button pure white text in light theme */
    html[data-theme="light"] .terms-page a.bg-purple-600,
    html[data-theme="light"] .terms-page a[href^="mailto"],
    html.theme-light .terms-page a.bg-purple-600,
    html.theme-light .terms-page a[href^="mailto"] {
        background-color: #7c3aed !important;
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }
    html[data-theme="light"] .terms-page a.bg-purple-600:hover,
    html[data-theme="light"] .terms-page a[href^="mailto"]:hover,
    html.theme-light .terms-page a.bg-purple-600:hover,
    html.theme-light .terms-page a[href^="mailto"]:hover {
        background-color: #6d28d9 !important;
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }
</style>
@endpush

@section("content")
<div class="terms-page text-slate-100 min-h-screen py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        
        <!-- 1. HEADER BANNER -->
        <div class="terms-card p-6 sm:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-96 h-96 bg-purple-600/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <a href="{{ url('/') }}" class="text-xs text-slate-400 hover:text-purple-400 font-semibold inline-flex items-center gap-1.5 transition-colors">
                            <span>←</span> <span>Back to Home</span>
                        </a>
                        <span class="text-slate-600">•</span>
                        <span class="terms-header-badge">Legal Document</span>
                    </div>
                    <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">Terms of Service</h1>
                    <p class="text-xs sm:text-sm text-slate-400 max-w-2xl leading-relaxed">
                        Please read these Terms of Service carefully before accessing or using the Kryvex cryptocurrency derivatives and spot trading platform.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-xs">
                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">LAST REVISED</span>
                        <strong class="text-white font-mono">August 25, 2026</strong>
                    </div>
                    <button type="button" onclick="window.print()" class="px-4 py-2.5 rounded-xl bg-purple-600/20 hover:bg-purple-600 text-purple-300 hover:text-white border border-purple-500/30 text-xs font-bold transition-all inline-flex items-center gap-1.5">
                        <span>Print Terms</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. MAIN LAYOUT: SIDEBAR TOC + CONTENT -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- LEFT: STICKY TABLE OF CONTENTS (4 Cols) -->
            <div class="lg:col-span-4 lg:sticky lg:top-24 space-y-4">
                <div class="terms-card p-5 space-y-3">
                    <div class="flex items-center justify-between pb-3 border-b border-white/10">
                        <h3 class="text-sm font-black text-white uppercase tracking-wider">TABLE OF CONTENTS</h3>
                        <span class="text-[10px] text-slate-400 font-bold">15 Sections</span>
                    </div>
                    <nav class="space-y-1 max-h-[70vh] overflow-y-auto pr-1">
                        <a href="#sec1" class="terms-toc-link">1. Acceptance of Terms</a>
                        <a href="#sec2" class="terms-toc-link">2. Eligibility & Legal Capacity</a>
                        <a href="#sec3" class="terms-toc-link">3. Account & Security</a>
                        <a href="#sec4" class="terms-toc-link">4. KYC & Identity Verification</a>
                        <a href="#sec5" class="terms-toc-link">5. Wallets & Balances (INR/USD)</a>
                        <a href="#sec6" class="terms-toc-link">6. Deposits & Withdrawals</a>
                        <a href="#sec7" class="terms-toc-link">7. Derivatives & Trading Rules</a>
                        <a href="#sec8" class="terms-toc-link text-amber-400 font-bold">8. Risk Disclosure & 100x Warning</a>
                        <a href="#sec9" class="terms-toc-link">9. Liquidation & Margin Policy</a>
                        <a href="#sec10" class="terms-toc-link">10. Fees, Taxes & 1% TDS</a>
                        <a href="#sec11" class="terms-toc-link">11. Prohibited Conduct & Fraud</a>
                        <a href="#sec12" class="terms-toc-link">12. Referral & Promotional Rules</a>
                        <a href="#sec13" class="terms-toc-link">13. Service Downtime & Forks</a>
                        <a href="#sec14" class="terms-toc-link">14. Limitation of Liability</a>
                        <a href="#sec15" class="terms-toc-link">15. Governing Law & Support</a>
                    </nav>
                </div>

                <!-- Fast Help Card -->
                <div class="terms-card p-5 border-purple-900/40 bg-gradient-to-br from-purple-950/20 to-slate-900/60 space-y-2">
                    <strong class="text-xs font-black text-purple-300 uppercase tracking-wide block">Need Legal Assistance?</strong>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        For institutional contracts, compliance questions, or legal notices, contact our compliance team.
                    </p>
                    <a href="mailto:support@kryvex.com" class="inline-flex items-center gap-1 text-xs font-bold text-white hover:text-purple-400 pt-1">
                        <span>support@kryvex.com</span> <span>→</span>
                    </a>
                </div>
            </div>

            <!-- RIGHT: DETAILED TERMS ARTICLES (8 Cols) -->
            <div class="lg:col-span-8 space-y-6 text-sm text-slate-300 leading-relaxed">
                
                <!-- SECTION 1 -->
                <article id="sec1" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">01</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Introduction &amp; Acceptance of Terms</h2>
                    </div>
                    <p>
                        Welcome to <strong>Kryvex</strong> ("Platform", "we", "our", or "us"). These Terms of Service ("Terms") constitute a legally binding agreement made between you, whether personally or on behalf of an entity ("you", "User"), and Kryvex Exchange, regarding your access to and use of our website, web application, trading engines, APIs, and affiliated services.
                    </p>
                    <p>
                        By creating an account, initiating a deposit, placing a trade, or otherwise utilizing any part of the Kryvex Platform, you expressly acknowledge that you have read, understood, and agreed to be bound by all of these Terms. <strong>If you do not agree with any part of these Terms, you must immediately discontinue your use of the Platform.</strong>
                    </p>
                </article>

                <!-- SECTION 2 -->
                <article id="sec2" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">02</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Eligibility &amp; Legal Capacity</h2>
                    </div>
                    <p>To register for an account and access trading services on Kryvex, you must satisfy the following conditions:</p>
                    <ul class="list-disc list-inside space-y-2 pl-2 text-slate-300">
                        <li>You are at least <strong>eighteen (18) years of age</strong> (or the legal age of majority in your jurisdiction).</li>
                        <li>You possess the legal authority and capacity to enter into binding agreements.</li>
                        <li>You are not a resident, citizen, or located in any jurisdiction where cryptocurrency trading, derivatives, or leverage instruments are prohibited by law or subject to international sanctions.</li>
                        <li>You have not been previously suspended or prohibited from using Kryvex.</li>
                    </ul>
                </article>

                <!-- SECTION 3 -->
                <article id="sec3" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">03</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Account Registration &amp; Security Obligations</h2>
                    </div>
                    <p>
                        When creating an account, you agree to provide authentic, accurate, and current information. You are solely responsible for safeguarding the credentials associated with your account, including:
                    </p>
                    <ul class="list-disc list-inside space-y-2 pl-2 text-slate-300">
                        <li><strong>Password Security:</strong> Maintaining a robust, unique password.</li>
                        <li><strong>Two-Factor Authentication (2FA):</strong> Enabling and safeguarding Email OTP / 2FA authenticators.</li>
                        <li><strong>Authorized Access:</strong> You are solely responsible for all trades, withdrawals, transfers, and activities executed under your account credentials. Kryvex is not liable for unauthorized access resulting from user negligence, phishing, or malware.</li>
                    </ul>
                </article>

                <!-- SECTION 4 -->
                <article id="sec4" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">04</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Identity Verification &amp; KYC / AML Compliance</h2>
                    </div>
                    <p>
                        To comply with international regulations, Anti-Money Laundering (AML), and Counter-Terrorism Financing (CTF) requirements, Kryvex requires all active trading accounts to complete multi-tiered identity verification:
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 my-2">
                        <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                            <strong class="text-xs text-white block mb-1">Step 1: Mobile OTP</strong>
                            <span class="text-[11px] text-slate-400">Instant SMS verification of user's active phone number.</span>
                        </div>
                        <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                            <strong class="text-xs text-white block mb-1">Step 2: DigiLocker &amp; PAN</strong>
                            <span class="text-[11px] text-slate-400">Official government ID validation via DigiLocker and PAN verification.</span>
                        </div>
                        <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                            <strong class="text-xs text-white block mb-1">Step 3: Live Selfie</strong>
                            <span class="text-[11px] text-slate-400">Facial liveness verification to eliminate identity fraud and duplicate accounts.</span>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">
                        Kryvex reserves the right to reject verification requests, freeze unverified accounts, or request supplementary documentation at its sole discretion.
                    </p>
                </article>

                <!-- SECTION 5 -->
                <article id="sec5" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">05</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Multi-Currency Wallets &amp; Balance Structure</h2>
                    </div>
                    <p>Kryvex operates a segregated multi-wallet architecture designed for maximum fund safety and transparent accounting:</p>
                    <ul class="list-disc list-inside space-y-2 pl-2 text-slate-300">
                        <li><strong>Main Wallet:</strong> Holds custody of deposited funds (INR and USDT) and non-margin crypto assets. Funds in Main Wallet cannot be liquidated during market downturns.</li>
                        <li><strong>Trading Sub-Accounts:</strong> Segregated balances allocated specifically for Futures, Options, and Broker trading. Internal transfers between Main Wallet and Trading accounts are instant and fee-free.</li>
                        <li><strong>Dual-Currency Support (INR &amp; USD):</strong> Users can view portfolio valuations and execute trades in both INR (Indian Rupee) and USDT (USD Tether) at real-time market exchange rates.</li>
                    </ul>
                </article>

                <!-- SECTION 6 -->
                <article id="sec6" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">06</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Deposits, Bank Accounts &amp; Withdrawals</h2>
                    </div>
                    <p>
                        <strong>Fiat Deposits &amp; Withdrawals (INR):</strong> INR transactions are processed exclusively through the verified bank account linked in your Account Profile. Deposits or withdrawals from/to unverified third-party bank accounts will be declined and flagged for review.
                    </p>
                    <p>
                        <strong>Crypto Deposits &amp; Withdrawals:</strong> You must verify the destination address and blockchain network (e.g. TRC20, ERC20, BEP20) prior to initiating a transaction. Deposits sent to incorrect addresses or on unsupported chains are permanently irrecoverable.
                    </p>
                    <p>
                        <strong>Processing Timelines:</strong> Withdrawal requests undergo automated fraud screening and are typically processed within standard operational windows (15 minutes to 24 hours).
                    </p>
                </article>

                <!-- SECTION 7 -->
                <article id="sec7" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">07</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Derivatives Trading Services (Futures &amp; Options)</h2>
                    </div>
                    <p>Kryvex provides digital perpetual futures and options trading under the following execution parameters:</p>
                    <div class="overflow-x-auto">
                        <table class="terms-table">
                            <thead>
                                <tr>
                                    <th>Feature</th>
                                    <th>Supported Parameters</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-bold text-white">Order Types</td>
                                    <td>Market, Limit, Stop-Limit, TP/SL</td>
                                    <td>Executed at best available order book price or predefined trigger limits.</td>
                                </tr>
                                <tr>
                                    <td class="font-bold text-white">Leverage Range</td>
                                    <td>1x to 100x</td>
                                    <td>Allows users to multiply exposure; higher leverage increases liquidation risk.</td>
                                </tr>
                                <tr>
                                    <td class="font-bold text-white">Margin Modes</td>
                                    <td>Cross Margin &amp; Isolated Margin</td>
                                    <td>Isolated confines risk to a single trade; Cross shares margin across sub-account.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <!-- SECTION 8 (HIGHLIGHTED RISK WARNING) -->
                <article id="sec8" class="terms-card p-6 sm:p-8 space-y-4 terms-section border-amber-500/30 bg-gradient-to-br from-amber-950/10 via-slate-900 to-slate-900">
                    <div class="flex items-center gap-3 pb-3 border-b border-amber-500/20">
                        <span class="w-8 h-8 rounded-lg bg-amber-500/20 border border-amber-500/40 text-amber-400 font-black flex items-center justify-center text-xs">⚠️</span>
                        <h2 class="text-lg font-black text-amber-400 tracking-tight">Risk Disclosure &amp; High Leverage Warning</h2>
                    </div>
                    <div class="terms-callout-warning space-y-2">
                        <strong class="text-amber-300 font-bold block">CRITICAL FINANCIAL NOTICE:</strong>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Cryptocurrency derivatives trading carries a substantial level of risk and may not be suitable for all investors. The high degree of leverage available on Kryvex (up to 100x) can work both against you and for you. <strong>You could sustain a total loss of your deposited margin funds rapidly.</strong>
                        </p>
                    </div>
                    <p>
                        You explicitly acknowledge that:
                    </p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-slate-300 text-xs">
                        <li>You are trading at your own sole financial risk and discretion.</li>
                        <li>Past market performance is not indicative of future results.</li>
                        <li>Kryvex does not provide investment, legal, tax, or financial advisory services.</li>
                    </ul>
                </article>

                <!-- SECTION 9 -->
                <article id="sec9" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">09</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Margin Requirements &amp; Forced Liquidation</h2>
                    </div>
                    <p>
                        <strong>Maintenance Margin:</strong> When market prices move adversely to an open position, the position's margin ratio will increase. When the margin ratio reaches 100% (or the maintenance margin threshold is breached), the position will undergo <strong>Forced Liquidation</strong>.
                    </p>
                    <p>
                        <strong>Automated Liquidation Engine:</strong> Liquidation is handled automatically by the matching engine at the current market liquidation price. A standard liquidation fee is deducted to replenish the Platform Insurance Fund.
                    </p>
                </article>

                <!-- SECTION 10 -->
                <article id="sec10" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">10</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Fees, Taxation &amp; 1% TDS Compliance</h2>
                    </div>
                    <p>
                        <strong>Trading Fees:</strong> Maker and Taker fee schedules are published transparently on the platform and deducted automatically upon order execution.
                    </p>
                    <p>
                        <strong>Tax Compliance &amp; 1% TDS:</strong> Under applicable statutory regulations (including Section 194S of the Indian Income Tax Act on Virtual Digital Assets), a 1% Tax Deducted at Source (TDS) will be deducted on eligible sell transactions and remitted to tax authorities in accordance with the law. Users are solely responsible for filing their annual income tax returns.
                    </p>
                </article>

                <!-- SECTION 11 -->
                <article id="sec11" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">11</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Prohibited Activities &amp; Anti-Fraud</h2>
                    </div>
                    <p>The following activities are strictly prohibited on the Platform:</p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-slate-300">
                        <li><strong>Market Manipulation:</strong> Wash trading, spoofing, front-running, layering, or pump-and-dump coordination.</li>
                        <li><strong>System Exploitation:</strong> Exploiting software bugs, DDoS attacks, or reverse-engineering proprietary trading engines.</li>
                        <li><strong>Financial Crimes:</strong> Money laundering, terrorism financing, or transacting with sanctioned entities.</li>
                        <li><strong>Multi-Account Sybil Abuse:</strong> Creating artificial duplicate accounts to harvest referral bonuses.</li>
                    </ul>
                </article>

                <!-- SECTION 12 -->
                <article id="sec12" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">12</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Referral Program &amp; Promotional Credits</h2>
                    </div>
                    <p>
                        Referral commissions and promotional rewards are subject to anti-abuse guidelines. Trading bonuses provided through promotional campaigns serve exclusively as trading margin credits and cannot be directly withdrawn. Kryvex reserves the right to revoke unearned rewards obtained via fraudulent referrals.
                    </p>
                </article>

                <!-- SECTION 13 -->
                <article id="sec13" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">13</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Service Downtime, Hard Forks &amp; Maintenance</h2>
                    </div>
                    <p>
                        Kryvex strives for 99.9% platform uptime. However, scheduled maintenance windows, urgent infrastructure upgrades, or external blockchain network forks may cause temporary disruptions. Kryvex is not liable for trade execution delays resulting from third-party network congestion or scheduled maintenance.
                    </p>
                </article>

                <!-- SECTION 14 -->
                <article id="sec14" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">14</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Limitation of Liability &amp; Disclaimers</h2>
                    </div>
                    <p>
                        To the maximum extent permitted by applicable law, Kryvex and its affiliates, directors, employees, and agents shall not be liable for any indirect, incidental, punitive, or consequential damages, loss of profits, loss of data, or capital loss resulting from market volatility, internet failures, or user error.
                    </p>
                </article>

                <!-- SECTION 15 -->
                <article id="sec15" class="terms-card p-6 sm:p-8 space-y-4 terms-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-purple-600/20 border border-purple-500/30 text-purple-400 font-bold flex items-center justify-center text-xs">15</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Governing Law, Dispute Resolution &amp; Support</h2>
                    </div>
                    <p>
                        These Terms shall be governed by and construed in accordance with applicable laws. In the event of any claim, dispute, or controversy, the parties agree to first seek informal resolution via Kryvex Customer Support.
                    </p>
                    <div class="terms-callout-info flex items-center justify-between flex-wrap gap-4 mt-3">
                        <div>
                            <strong class="text-white block font-bold">Have questions or inquiries about our Terms?</strong>
                            <span class="text-xs text-slate-300">Our compliance &amp; legal team is available 24/7.</span>
                        </div>
                        <a href="mailto:support@kryvex.com" class="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition-all shadow-md">
                            Contact Support
                        </a>
                    </div>
                </article>

            </div>

        </div>

    </div>
</div>
@endsection
