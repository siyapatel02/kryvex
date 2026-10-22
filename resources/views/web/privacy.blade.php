@extends("layouts.app")

@section("title", "Privacy Policy - Kryvex Exchange")

@push("styles")
<style>
    .privacy-page {
        background-color: var(--kv-bg, #080b11);
        color: var(--kv-text, #f8fafc);
        min-height: 100vh;
    }
    .privacy-card {
        background: linear-gradient(135deg, rgba(20, 21, 30, 0.95), rgba(13, 14, 20, 0.98));
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
        backdrop-filter: blur(12px);
    }
    .privacy-section {
        scroll-margin-top: 100px;
    }
    .privacy-toc-link {
        display: block;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 0.82rem;
        font-weight: 600;
        color: #94a3b8;
        transition: all 0.2s ease;
        border-left: 2px solid transparent;
    }
    .privacy-toc-link:hover, .privacy-toc-link.is-active {
        color: #ffffff;
        background: rgba(16, 185, 129, 0.12);
        border-left-color: #10b981;
    }
    .privacy-callout-shield {
        background: rgba(16, 185, 129, 0.08);
        border: 1px solid rgba(16, 185, 129, 0.25);
        border-radius: 14px;
        padding: 18px 20px;
    }
    .privacy-callout-info {
        background: rgba(59, 130, 246, 0.08);
        border: 1px solid rgba(59, 130, 246, 0.25);
        border-radius: 14px;
        padding: 18px 20px;
    }
    .privacy-table {
        width: 100%;
        border-collapse: collapse;
        margin: 16px 0;
        font-size: 0.85rem;
    }
    .privacy-table th, .privacy-table td {
        padding: 10px 14px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        text-align: left;
    }
    .privacy-table th {
        background: rgba(255, 255, 255, 0.04);
        color: #e2e8f0;
        font-weight: 700;
    }
    .privacy-table td {
        color: #94a3b8;
    }

    .privacy-header-badge {
        font-size: 0.75rem;
        font-weight: 700;
        color: #34d399;
        background: rgba(6, 78, 59, 0.6);
        border: 1px solid rgba(6, 95, 70, 0.5);
        padding: 2px 10px;
        border-radius: 9999px;
        display: inline-block;
    }

    /* ==========================================================================
       LIGHT THEME STYLES FOR PRIVACY POLICY
       ========================================================================== */
    html[data-theme="light"] .privacy-page,
    html.theme-light .privacy-page {
        background-color: #f8fafc !important;
        color: #0f172a !important;
    }
    html[data-theme="light"] .privacy-header-badge,
    html[data-theme="light"] .privacy-page .bg-emerald-950\/60,
    html.theme-light .privacy-header-badge,
    html.theme-light .privacy-page .bg-emerald-950\/60 {
        background: #dcfce7 !important;
        border: 1px solid #86efac !important;
        color: #15803d !important;
    }
    html[data-theme="light"] .privacy-page .text-slate-100,
    html[data-theme="light"] .privacy-page .text-slate-200,
    html[data-theme="light"] .privacy-page .text-slate-300,
    html.theme-light .privacy-page .text-slate-100,
    html.theme-light .privacy-page .text-slate-200,
    html.theme-light .privacy-page .text-slate-300 {
        color: #334155 !important;
    }
    html[data-theme="light"] .privacy-page .text-slate-400,
    html[data-theme="light"] .privacy-page .text-slate-500,
    html.theme-light .privacy-page .text-slate-400,
    html.theme-light .privacy-page .text-slate-500 {
        color: #64748b !important;
    }
    html[data-theme="light"] .privacy-page .text-white,
    html.theme-light .privacy-page .text-white {
        color: #0f172a !important;
    }
    html[data-theme="light"] .privacy-card,
    html.theme-light .privacy-card {
        background: #ffffff !important;
        border: 1px solid #e2e8f0 !important;
        box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05) !important;
        color: #0f172a !important;
    }
    html[data-theme="light"] .privacy-card .border-white\/10,
    html[data-theme="light"] .privacy-card .border-white\/5,
    html.theme-light .privacy-card .border-white\/10,
    html.theme-light .privacy-card .border-white\/5 {
        border-color: #e2e8f0 !important;
    }
    html[data-theme="light"] .privacy-card .bg-white\/5,
    html.theme-light .privacy-card .bg-white\/5 {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
    }
    html[data-theme="light"] .privacy-toc-link,
    html.theme-light .privacy-toc-link {
        color: #64748b !important;
    }
    html[data-theme="light"] .privacy-toc-link:hover,
    html[data-theme="light"] .privacy-toc-link.is-active,
    html.theme-light .privacy-toc-link:hover,
    html.theme-light .privacy-toc-link.is-active {
        color: #059669 !important;
        background: rgba(16, 185, 129, 0.1) !important;
        border-left-color: #059669 !important;
    }
    html[data-theme="light"] .privacy-callout-shield,
    html.theme-light .privacy-callout-shield {
        background: #ecfdf5 !important;
        border: 1px solid #a7f3d0 !important;
    }
    html[data-theme="light"] .privacy-callout-shield strong,
    html.theme-light .privacy-callout-shield strong {
        color: #047857 !important;
    }
    html[data-theme="light"] .privacy-callout-shield p,
    html.theme-light .privacy-callout-shield p {
        color: #065f46 !important;
    }
    html[data-theme="light"] .privacy-callout-info,
    html.theme-light .privacy-callout-info {
        background: #f0fdf4 !important;
        border: 1px solid #bbf7d0 !important;
    }
    html[data-theme="light"] .privacy-callout-info strong,
    html.theme-light .privacy-callout-info strong {
        color: #0f172a !important;
    }
    html[data-theme="light"] .privacy-callout-info span,
    html.theme-light .privacy-callout-info span {
        color: #64748b !important;
    }
    html[data-theme="light"] .privacy-table th,
    html.theme-light .privacy-table th {
        background: #f1f5f9 !important;
        color: #0f172a !important;
        border: 1px solid #cbd5e1 !important;
    }
    html[data-theme="light"] .privacy-table td,
    html.theme-light .privacy-table td {
        border: 1px solid #e2e8f0 !important;
        color: #334155 !important;
    }
    html[data-theme="light"] .privacy-table td.font-bold,
    html.theme-light .privacy-table td.font-bold {
        color: #0f172a !important;
    }
    html[data-theme="light"] #p-sec5,
    html.theme-light #p-sec5 {
        background: linear-gradient(135deg, #f0fdf4, #ffffff) !important;
        border-color: #86efac !important;
    }
    html[data-theme="light"] .privacy-page button[onclick="window.print()"],
    html.theme-light .privacy-page button[onclick="window.print()"] {
        background: rgba(16, 185, 129, 0.12) !important;
        color: #059669 !important;
        border: 1px solid rgba(16, 185, 129, 0.3) !important;
    }
    html[data-theme="light"] .privacy-page button[onclick="window.print()"]:hover,
    html.theme-light .privacy-page button[onclick="window.print()"]:hover {
        background: #059669 !important;
        color: #ffffff !important;
    }
    /* Contact Privacy Officer button pure white text in light theme */
    html[data-theme="light"] .privacy-page a.bg-emerald-600,
    html[data-theme="light"] .privacy-page a[href^="mailto"],
    html.theme-light .privacy-page a.bg-emerald-600,
    html.theme-light .privacy-page a[href^="mailto"] {
        background-color: #059669 !important;
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }
    html[data-theme="light"] .privacy-page a.bg-emerald-600:hover,
    html[data-theme="light"] .privacy-page a[href^="mailto"]:hover,
    html.theme-light .privacy-page a.bg-emerald-600:hover,
    html.theme-light .privacy-page a[href^="mailto"]:hover {
        background-color: #047857 !important;
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }
</style>
@endpush

@section("content")
<div class="privacy-page text-slate-100 min-h-screen py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        
        <!-- 1. HEADER BANNER -->
        <div class="privacy-card p-6 sm:p-8 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-96 h-96 bg-emerald-600/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 relative z-10">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <a href="{{ url('/') }}" class="text-xs text-slate-400 hover:text-emerald-400 font-semibold inline-flex items-center gap-1.5 transition-colors">
                            <span>←</span> <span>Back to Home</span>
                        </a>
                        <span class="text-slate-600">•</span>
                        <span class="privacy-header-badge">Data Protection</span>
                    </div>
                    <h1 class="text-2xl sm:text-4xl font-black text-white tracking-tight">Privacy Policy</h1>
                    <p class="text-xs sm:text-sm text-slate-400 max-w-2xl leading-relaxed">
                        At Kryvex, your privacy and financial data security are paramount. This policy outlines how we collect, protect, process, and handle your information.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <div class="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-xs">
                        <span class="text-slate-400 block text-[10px] uppercase font-bold tracking-wider">LAST UPDATED</span>
                        <strong class="text-white font-mono">August 25, 2026</strong>
                    </div>
                    <button type="button" onclick="window.print()" class="px-4 py-2.5 rounded-xl bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white border border-emerald-500/30 text-xs font-bold transition-all inline-flex items-center gap-1.5">
                        {{-- <span>🖨️</span>  --}}
                        <span>Print Policy</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- 2. MAIN LAYOUT: SIDEBAR TOC + CONTENT -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            
            <!-- LEFT: STICKY TABLE OF CONTENTS (4 Cols) -->
            <div class="lg:col-span-4 lg:sticky lg:top-24 space-y-4">
                <div class="privacy-card p-5 space-y-3">
                    <div class="flex items-center justify-between pb-3 border-b border-white/10">
                        <h3 class="text-sm font-black text-white uppercase tracking-wider">POLICY SECTIONS</h3>
                        <span class="text-[10px] text-slate-400 font-bold">12 Topics</span>
                    </div>
                    <nav class="space-y-1 max-h-[70vh] overflow-y-auto pr-1">
                        <a href="#p-sec1" class="privacy-toc-link">1. Commitment to Privacy</a>
                        <a href="#p-sec2" class="privacy-toc-link">2. Information We Collect</a>
                        <a href="#p-sec3" class="privacy-toc-link">3. Collection Methods</a>
                        <a href="#p-sec4" class="privacy-toc-link">4. Purpose of Data Processing</a>
                        <a href="#p-sec5" class="privacy-toc-link font-bold text-emerald-400">5. Strict Non-Sale Policy</a>
                        <a href="#p-sec6" class="privacy-toc-link">6. Third-Party Disclosures</a>
                        <a href="#p-sec7" class="privacy-toc-link">7. Data Security &amp; Encryption</a>
                        <a href="#p-sec8" class="privacy-toc-link">8. Cookies &amp; Tracking</a>
                        <a href="#p-sec9" class="privacy-toc-link">9. Your Privacy Rights</a>
                        <a href="#p-sec10" class="privacy-toc-link">10. Data Retention Policy</a>
                        <a href="#p-sec11" class="privacy-toc-link">11. Children's Privacy (18+)</a>
                        <a href="#p-sec12" class="privacy-toc-link">12. Contact &amp; DPO</a>
                    </nav>
                </div>

                <!-- Fast Guarantee Card -->
                <div class="privacy-card p-5 border-emerald-900/40 bg-gradient-to-br from-emerald-950/20 to-slate-900/60 space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">🛡️</span>
                        <strong class="text-xs font-black text-emerald-300 uppercase tracking-wide block">Our Privacy Guarantee</strong>
                    </div>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        We never sell, rent, or trade your personal or financial information to third-party marketing companies.
                    </p>
                    <div class="pt-1 flex items-center gap-2 text-[11px] text-emerald-400 font-bold">
                        <span>🔒 256-Bit Military Grade Encryption</span>
                    </div>
                </div>
            </div>

            <!-- RIGHT: DETAILED PRIVACY ARTICLES (8 Cols) -->
            <div class="lg:col-span-8 space-y-6 text-sm text-slate-300 leading-relaxed">
                
                <!-- SECTION 1 -->
                <article id="p-sec1" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">01</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Introduction &amp; Commitment to Privacy</h2>
                    </div>
                    <p>
                        Kryvex Exchange ("Kryvex", "we", "our", or "us") respects the privacy of our users ("you", "User") and is committed to protecting the personal, financial, and biometric data you share with us.
                    </p>
                    <p>
                        This Privacy Policy outlines the practices we adhere to regarding the collection, storage, utilization, transfer, and protection of your personal information across all Kryvex web domains, trading engines, mobile endpoints, and APIs.
                    </p>
                </article>

                <!-- SECTION 2 -->
                <article id="p-sec2" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">02</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Categories of Information We Collect</h2>
                    </div>
                    <p>To provide high-performance trading services and comply with Anti-Money Laundering (AML) standards, we collect the following categories of data:</p>
                    
                    <div class="overflow-x-auto">
                        <table class="privacy-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Data Types Collected</th>
                                    <th>Primary Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="font-bold text-white">Identity &amp; Profile</td>
                                    <td>Full legal name, email address, phone number, nickname, date of birth.</td>
                                    <td>Account registration, authentication, communication.</td>
                                </tr>
                                <tr>
                                    <td class="font-bold text-white">KYC &amp; Government ID</td>
                                    <td>Aadhaar/DigiLocker documents, PAN Card number, identity proofs.</td>
                                    <td>Mandatory statutory identity verification &amp; regulatory AML compliance.</td>
                                </tr>
                                <tr>
                                    <td class="font-bold text-white">Biometric / Liveness</td>
                                    <td>Live facial selfie photos, liveness metadata.</td>
                                    <td>Preventing Sybil attacks, impersonation, and fraudulent registrations.</td>
                                </tr>
                                <tr>
                                    <td class="font-bold text-white">Banking &amp; Financial</td>
                                    <td>Bank account numbers, IFSC codes, bank branch names, crypto wallet addresses.</td>
                                    <td>INR fiat deposits/withdrawals and blockchain ledger tracking.</td>
                                </tr>
                                <tr>
                                    <td class="font-bold text-white">Trading &amp; Activity</td>
                                    <td>Orders (Market/Limit), trades, leverage settings, liquidations, PnL, internal transfers.</td>
                                    <td>Trade execution, matching engine audits, and account history.</td>
                                </tr>
                                <tr>
                                    <td class="font-bold text-white">Technical &amp; Device</td>
                                    <td>IP addresses, device model, browser version, operating system, geolocation logs.</td>
                                    <td>Security monitoring, session protection, DDoS defense.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <!-- SECTION 3 -->
                <article id="p-sec3" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">03</span>
                        <h2 class="text-lg font-black text-white tracking-tight">How We Collect Your Information</h2>
                    </div>
                    <ul class="list-disc list-inside space-y-2 pl-2 text-slate-300">
                        <li><strong>Direct User Submissions:</strong> Information you provide when registering, submitting KYC forms, linking bank accounts, or opening customer support tickets.</li>
                        <li><strong>Automated Platform Tracking:</strong> Technical telemetry collected automatically as you browse, trade, toggle theme preferences, or interact with market order books.</li>
                        <li><strong>Third-Party Integration Services:</strong> Verification data fetched securely through official government gateways (DigiLocker / Digio API) and Google OAuth services upon your explicit consent.</li>
                    </ul>
                </article>

                <!-- SECTION 4 -->
                <article id="p-sec4" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">04</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Purpose &amp; Legal Basis for Processing</h2>
                    </div>
                    <p>We process your data strictly under recognized legal bases (Contractual Necessity, Legal Obligation, and Legitimate Business Interests):</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 my-2">
                        <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-1">
                            <strong class="text-xs text-white block">1. Service Delivery &amp; Orders</strong>
                            <p class="text-xs text-slate-400">Processing perpetual futures, options trades, deposit confirmations, and instant internal transfers.</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-1">
                            <strong class="text-xs text-white block">2. Statutory AML &amp; 1% TDS</strong>
                            <p class="text-xs text-slate-400">Complying with Anti-Money Laundering acts, tax deductions, and official government audits.</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-1">
                            <strong class="text-xs text-white block">3. Platform Security &amp; Anti-Fraud</strong>
                            <p class="text-xs text-slate-400">Detecting malicious bot logins, abnormal withdrawal velocity, and market manipulation attempts.</p>
                        </div>
                        <div class="p-4 rounded-xl bg-white/5 border border-white/10 space-y-1">
                            <strong class="text-xs text-white block">4. Critical User Notifications</strong>
                            <p class="text-xs text-slate-400">Sending liquidation alerts, login OTPs, withdrawal confirmations, and system updates.</p>
                        </div>
                    </div>
                </article>

                <!-- SECTION 5 (STRICT NON-SALE CALLOUT) -->
                <article id="p-sec5" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section border-emerald-500/30 bg-gradient-to-br from-emerald-950/15 via-slate-900 to-slate-900">
                    <div class="flex items-center gap-3 pb-3 border-b border-emerald-500/20">
                        <span class="w-8 h-8 rounded-lg bg-emerald-500/20 border border-emerald-500/40 text-emerald-400 font-black flex items-center justify-center text-xs">🔒</span>
                        <h2 class="text-lg font-black text-emerald-400 tracking-tight">Our Strict Non-Sale of Data Commitment</h2>
                    </div>
                    <div class="privacy-callout-shield space-y-2">
                        <strong class="text-emerald-300 font-bold block">ABSOLUTE USER DATA PRIVACY:</strong>
                        <p class="text-xs text-slate-200 leading-relaxed">
                            Kryvex will <strong>NEVER</strong> sell, rent, monetize, or disclose your personal records, trading behavior, phone numbers, or email addresses to third-party advertisers, data brokers, or marketing networks under any circumstances.
                        </p>
                    </div>
                    <p class="text-xs text-slate-300">
                        Your identity and financial holdings remain confidential and are strictly accessible only by authorized internal systems required for trade execution and legal compliance.
                    </p>
                </article>

                <!-- SECTION 6 -->
                <article id="p-sec6" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">06</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Third-Party Disclosures &amp; Service Providers</h2>
                    </div>
                    <p>We share minimal necessary data only with trusted, audited partners under strict confidentiality agreements:</p>
                    <ul class="list-disc list-inside space-y-2 pl-2 text-slate-300">
                        <li><strong>Identity Verification Partners:</strong> DigiLocker/Digio APIs for automated Aadhaar and PAN verification.</li>
                        <li><strong>Telecom &amp; SMS Gateways:</strong> Service providers delivering instant 2FA and mobile OTP authentication codes.</li>
                        <li><strong>Cloud &amp; DDoS Infrastructure:</strong> Encrypted hosting providers and Cloudflare edge protection to safeguard platform uptime.</li>
                        <li><strong>Law Enforcement &amp; Regulatory Agencies:</strong> Only when legally compelled by valid court orders, subpoenas, or statutory financial regulations.</li>
                    </ul>
                </article>

                <!-- SECTION 7 -->
                <article id="p-sec7" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">07</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Data Security &amp; Fund Protection Measures</h2>
                    </div>
                    <p>Kryvex employs institutional-grade security safeguards to prevent data breaches, unauthorized access, or loss:</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 my-2">
                        <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                            <strong class="text-xs text-white block mb-1">🔐 256-Bit SSL/TLS</strong>
                            <span class="text-[11px] text-slate-400">All data in transit is encrypted with end-to-end TLS 1.3 cryptographic protocols.</span>
                        </div>
                        <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                            <strong class="text-xs text-white block mb-1">🛡️ Cold Wallet Custody</strong>
                            <span class="text-[11px] text-slate-400">The majority of digital asset reserves are held offline in multi-signature cold storage vaults.</span>
                        </div>
                        <div class="p-3.5 rounded-xl bg-white/5 border border-white/10">
                            <strong class="text-xs text-white block mb-1">🔑 Salted Hash Passwords</strong>
                            <span class="text-[11px] text-slate-400">User passwords and secret keys are hashed using industry-standard Bcrypt cryptography.</span>
                        </div>
                    </div>
                </article>

                <!-- SECTION 8 -->
                <article id="p-sec8" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">08</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Cookies &amp; Local Storage</h2>
                    </div>
                    <p>
                        We use essential session cookies and HTML5 LocalStorage to maintain your authentication state, remember your active UI theme (Dark/Light mode), preserve base currency settings (INR/USD), and protect against cross-site request forgery (CSRF). You can clear cookies through your browser settings at any time.
                    </p>
                </article>

                <!-- SECTION 9 -->
                <article id="p-sec9" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">09</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Your Rights &amp; Data Control</h2>
                    </div>
                    <p>Depending on your jurisdiction, you retain fundamental rights regarding your personal data:</p>
                    <ul class="list-disc list-inside space-y-1.5 pl-2 text-slate-300">
                        <li><strong>Right to Access:</strong> Inspect the personal information and trading history associated with your UID.</li>
                        <li><strong>Right to Rectification:</strong> Update inaccurate phone numbers, nicknames, or passwords via Account Settings.</li>
                        <li><strong>Right to Account Closure:</strong> Request closure of your trading account after withdrawing your remaining wallet balance.</li>
                        <li><strong>Right to Data Portability:</strong> Export trade ledgers and transaction history in CSV/PDF format via the Wallet &amp; Reports portal.</li>
                    </ul>
                </article>

                <!-- SECTION 10 -->
                <article id="p-sec10" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">10</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Data Retention &amp; Archival Period</h2>
                    </div>
                    <p>
                        In compliance with statutory financial reporting, Prevention of Money Laundering Act (PMLA), and tax regulations, KYC verification documents and immutable transaction ledgers are retained in secure archives for a minimum of <strong>five (5) to seven (7) years</strong> following account termination.
                    </p>
                </article>

                <!-- SECTION 11 -->
                <article id="p-sec11" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">11</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Children's Privacy Protection (Strictly 18+)</h2>
                    </div>
                    <p>
                        Kryvex trading services are strictly restricted to individuals who are <strong>eighteen (18) years of age or older</strong>. We do not knowingly solicit or collect data from minors. Any account identified as belonging to an underage user will be terminated immediately.
                    </p>
                </article>

                <!-- SECTION 12 -->
                <article id="p-sec12" class="privacy-card p-6 sm:p-8 space-y-4 privacy-section">
                    <div class="flex items-center gap-3 pb-3 border-b border-white/10">
                        <span class="w-8 h-8 rounded-lg bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 font-bold flex items-center justify-center text-xs">12</span>
                        <h2 class="text-lg font-black text-white tracking-tight">Policy Updates &amp; Data Protection Officer Contact</h2>
                    </div>
                    <p>
                        We may periodically revise this Privacy Policy to reflect regulatory modifications or security enhancements. Material updates will be communicated through platform announcements or email alerts.
                    </p>
                    <div class="privacy-callout-info flex items-center justify-between flex-wrap gap-4 mt-3">
                        <div>
                            <strong class="text-white block font-bold">Have privacy questions or want to exercise your data rights?</strong>
                            <span class="text-xs text-slate-300">Our dedicated Data Protection Officer is ready to assist you.</span>
                        </div>
                        <a href="mailto:privacy@kryvex.com" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition-all shadow-md">
                            Contact Privacy Officer
                        </a>
                    </div>
                </article>

            </div>

        </div>

    </div>
</div>
@endsection
