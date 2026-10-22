@extends("layouts.app")

@section("title", "Invite & Earn - Kryvex")

@push("styles")
    @vite("resources/css/pages/invite.css")
@endpush

@push("scripts")
    @vite("resources/js/pages/invite.js")
@endpush

@section("content")
<div class="inv-page">
    <div class="inv-container">

        <!-- HERO -->
        <section class="inv-hero">
            <div class="inv-hero-glow"></div>
            <div class="inv-hero-left">
                <a href="{{ url('/') }}" class="inv-back-link"><span>←</span> Back to Home</a>
                <span class="inv-hero-pill">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
                    Kryvex Referral Program
                </span>
                <h1 class="inv-hero-title">Invite friends.<br><span>Earn up to 40% commission.</span></h1>
                <p class="inv-hero-sub">Share your referral link and earn a lifetime share of trading fees from every friend who signs up and trades on Kryvex. Your friends get a welcome bonus too.</p>

                <div class="inv-hero-highlights">
                    <div class="inv-highlight">
                        <div class="inv-highlight-val">40%</div>
                        <div class="inv-highlight-label">Max commission</div>
                    </div>
                    <div class="inv-highlight">
                        <div class="inv-highlight-val">$100</div>
                        <div class="inv-highlight-label">Friend welcome bonus</div>
                    </div>
                    <div class="inv-highlight">
                        <div class="inv-highlight-val">Lifetime</div>
                        <div class="inv-highlight-label">Commission period</div>
                    </div>
                </div>
            </div>

            <!-- Referral share card -->
            <div class="inv-share-card">
                <div class="inv-share-head">
                    <div>
                        <div class="inv-share-title">Your referral details</div>
                        <div class="inv-share-sub">Share your code or link with friends</div>
                    </div>
                    <span class="inv-tier-badge" id="invCurrentTierBadge">Bronze · 20%</span>
                </div>

                <label class="inv-field-label">Referral code</label>
                <div class="inv-copy-field">
                    <span class="inv-copy-value inv-code" id="invReferralCode">--------</span>
                    <button type="button" class="inv-copy-btn" data-inv-copy="code">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        Copy
                    </button>
                </div>

                <label class="inv-field-label">Referral link</label>
                <div class="inv-copy-field">
                    <span class="inv-copy-value" id="invReferralLink">{{ url('/register') }}</span>
                    <button type="button" class="inv-copy-btn" data-inv-copy="link">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>
                        Copy
                    </button>
                </div>

                <button type="button" class="inv-primary-btn" id="invBtnInviteNow">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path><polyline points="16 6 12 2 8 6"></polyline><line x1="12" y1="2" x2="12" y2="15"></line></svg>
                    Invite friends
                </button>

                <div class="inv-share-label">Share via</div>
                <div class="inv-share-row">
                    <button type="button" class="inv-social" data-inv-share="whatsapp" title="WhatsApp">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.79-1.47-1.76-1.65-2.06-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.48.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.75-.72 2-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35zM12.04 21.5h-.01a9.45 9.45 0 0 1-4.82-1.32l-.35-.21-3.58.94.96-3.49-.23-.36A9.43 9.43 0 0 1 2.56 12C2.56 6.78 6.81 2.53 12.05 2.53c2.53 0 4.91.99 6.7 2.78a9.4 9.4 0 0 1 2.77 6.7c0 5.23-4.25 9.49-9.48 9.49zm8.06-17.55A11.3 11.3 0 0 0 12.04.67C5.76.67.65 5.78.65 12.06c0 2.01.52 3.97 1.52 5.69L.55 23.33l5.72-1.5a11.4 11.4 0 0 0 5.76 1.47h.01c6.28 0 11.39-5.11 11.39-11.39 0-3.04-1.18-5.9-3.33-8.05z"/></svg>
                    </button>
                    <button type="button" class="inv-social" data-inv-share="telegram" title="Telegram">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M9.78 15.27l-.4 5.6c.57 0 .82-.25 1.12-.54l2.68-2.56 5.56 4.07c1.02.57 1.74.27 2.02-.94l3.66-17.15c.33-1.51-.54-2.1-1.53-1.73L1.43 10.3C-.04 10.87-.02 11.69 1.18 12.06l5.51 1.72L19.5 5.77c.6-.4 1.15-.18.7.22L9.78 15.27z"/></svg>
                    </button>
                    <button type="button" class="inv-social" data-inv-share="x" title="X (Twitter)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M18.24 2.25h3.31l-7.23 8.26 8.5 11.24h-6.66l-5.21-6.82-5.97 6.82H1.67l7.73-8.84L1.25 2.25h6.83l4.71 6.23 5.45-6.23zm-1.16 17.52h1.83L7.08 4.13H5.12l11.96 15.64z"/></svg>
                    </button>
                    <button type="button" class="inv-social" data-inv-share="facebook" title="Facebook">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.07C24 5.41 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.04V9.41c0-3.02 1.8-4.7 4.54-4.7 1.31 0 2.68.24 2.68.24v2.97h-1.5c-1.5 0-1.96.93-1.96 1.89v2.26h3.33l-.53 3.49h-2.8V24C19.62 23.1 24 18.1 24 12.07z"/></svg>
                    </button>
                    <button type="button" class="inv-social" data-inv-share="email" title="Email">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                    </button>
                </div>
            </div>
        </section>

        <!-- STATS -->
        <section class="inv-stats-grid">
            <div class="inv-stat-card">
                <div class="inv-stat-icon is-purple">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </div>
                <div>
                    <div class="inv-stat-label">Friends invited</div>
                    <div class="inv-stat-value" id="invStatInvited">0</div>
                </div>
            </div>
            <div class="inv-stat-card">
                <div class="inv-stat-icon is-blue">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path><polyline points="9 12 11 14 15 10"></polyline></svg>
                </div>
                <div>
                    <div class="inv-stat-label">KYC verified</div>
                    <div class="inv-stat-value" id="invStatVerified">0</div>
                </div>
            </div>
            <div class="inv-stat-card">
                <div class="inv-stat-icon is-amber">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                </div>
                <div>
                    <div class="inv-stat-label">Active traders</div>
                    <div class="inv-stat-value" id="invStatTraders">0</div>
                </div>
            </div>
            <div class="inv-stat-card is-highlight">
                <div class="inv-stat-icon is-green">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                </div>
                <div>
                    <div class="inv-stat-label">Total earned</div>
                    <div class="inv-stat-value is-green" id="invStatEarned">$0.00</div>
                </div>
            </div>
        </section>

        <!-- HOW IT WORKS + TIER PROGRESS -->
        <section class="inv-two-col">
            <div class="inv-card">
                <div class="inv-card-head">
                    <h2 class="inv-card-title">How it works</h2>
                    <span class="inv-card-sub">3 simple steps</span>
                </div>
                <div class="inv-steps">
                    <div class="inv-step">
                        <div class="inv-step-num">1</div>
                        <div class="inv-step-body">
                            <div class="inv-step-title">Share your link</div>
                            <div class="inv-step-text">Send your unique referral link or code to friends through any channel.</div>
                        </div>
                    </div>
                    <div class="inv-step">
                        <div class="inv-step-num">2</div>
                        <div class="inv-step-body">
                            <div class="inv-step-title">Friends sign up &amp; trade</div>
                            <div class="inv-step-text">Your friends register with your code, complete KYC and start trading.</div>
                        </div>
                    </div>
                    <div class="inv-step">
                        <div class="inv-step-num">3</div>
                        <div class="inv-step-body">
                            <div class="inv-step-title">Earn commission</div>
                            <div class="inv-step-text">Get a share of their trading fees credited to your Main Wallet every day.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="inv-card">
                <div class="inv-card-head">
                    <h2 class="inv-card-title">Your tier progress</h2>
                    <span class="inv-card-sub" id="invTierProgressText">0 / 5 active friends to Silver</span>
                </div>
                <div class="inv-progress">
                    <div class="inv-progress-bar" id="invTierProgressBar" style="width: 0%;"></div>
                </div>
                <div class="inv-tier-list" id="invTierList">
                    <div class="inv-tier-row" data-tier="0">
                        <div class="inv-tier-name"><span class="inv-tier-dot is-bronze"></span>Bronze</div>
                        <div class="inv-tier-req">0+ active friends</div>
                        <div class="inv-tier-rate">20%</div>
                    </div>
                    <div class="inv-tier-row" data-tier="1">
                        <div class="inv-tier-name"><span class="inv-tier-dot is-silver"></span>Silver</div>
                        <div class="inv-tier-req">5+ active friends</div>
                        <div class="inv-tier-rate">25%</div>
                    </div>
                    <div class="inv-tier-row" data-tier="2">
                        <div class="inv-tier-name"><span class="inv-tier-dot is-gold"></span>Gold</div>
                        <div class="inv-tier-req">20+ active friends</div>
                        <div class="inv-tier-rate">30%</div>
                    </div>
                    <div class="inv-tier-row" data-tier="3">
                        <div class="inv-tier-name"><span class="inv-tier-dot is-platinum"></span>Platinum</div>
                        <div class="inv-tier-req">50+ active friends</div>
                        <div class="inv-tier-rate">40%</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- REFERRALS / REWARDS TABLE -->
        <section class="inv-card">
            <div class="inv-card-head inv-table-head">
                <div class="inv-tabs">
                    <button type="button" class="inv-tab is-active" data-inv-tab="referrals">My referrals</button>
                    <button type="button" class="inv-tab" data-inv-tab="rewards">Reward history</button>
                </div>
                <div class="inv-search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" id="invSearchInput" placeholder="Search">
                </div>
            </div>

            <div class="inv-table-wrap" data-inv-panel="referrals">
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Friend</th>
                            <th>Joined</th>
                            <th>KYC</th>
                            <th>Status</th>
                            <th class="is-right">Your commission</th>
                        </tr>
                    </thead>
                    <tbody id="invReferralsBody"></tbody>
                </table>
            </div>

            <div class="inv-table-wrap" data-inv-panel="rewards" hidden>
                <table class="inv-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>From</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="is-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="invRewardsBody"></tbody>
                </table>
            </div>
        </section>

        <!-- FAQ -->
        <section class="inv-card">
            <div class="inv-card-head">
                <h2 class="inv-card-title">Frequently asked questions</h2>
            </div>
            <div class="inv-faq">
                <details class="inv-faq-item" open>
                    <summary>How much can I earn?</summary>
                    <p>You earn 20% to 40% of the trading fees paid by every friend you refer, depending on your tier. There is no cap on how much you can earn.</p>
                </details>
                <details class="inv-faq-item">
                    <summary>When are commissions paid?</summary>
                    <p>Commissions are calculated daily and credited to your Main Wallet in USDT within 24 hours.</p>
                </details>
                <details class="inv-faq-item">
                    <summary>What does my friend get?</summary>
                    <p>Friends who sign up with your link receive a welcome bonus of up to $100 after completing KYC and making their first deposit.</p>
                </details>
                <details class="inv-faq-item">
                    <summary>Who counts as an active friend?</summary>
                    <p>A referred friend becomes active once they have completed KYC and placed at least one trade in the last 30 days.</p>
                </details>
                <details class="inv-faq-item">
                    <summary>Can I refer myself or create multiple accounts?</summary>
                    <p>No. Self-referrals and duplicate accounts are not allowed and may lead to forfeiture of rewards. See the <a href="{{ url('/terms') }}">Terms of Service</a>.</p>
                </details>
            </div>
        </section>

    </div>
</div>

<!-- Invite modal -->
<div class="inv-modal-backdrop" id="invInviteModal" hidden>
    <div class="inv-modal" role="dialog" aria-modal="true" aria-labelledby="invModalTitle">
        <button type="button" class="inv-modal-close" data-inv-close aria-label="Close">×</button>
        <div class="inv-modal-poster">
            <div class="inv-poster-brand">KRYVEX</div>
            <div class="inv-poster-title">Join me on Kryvex</div>
            <div class="inv-poster-sub">Trade crypto futures &amp; options and get up to <b>$100</b> welcome bonus</div>
            <div class="inv-poster-code-label">Referral code</div>
            <div class="inv-poster-code" id="invModalCode">--------</div>
        </div>
        <h3 class="inv-modal-title" id="invModalTitle">Invite friends</h3>
        <div class="inv-copy-field">
            <span class="inv-copy-value" id="invModalLink">{{ url('/register') }}</span>
            <button type="button" class="inv-copy-btn" data-inv-copy="link">Copy</button>
        </div>
        <div class="inv-share-row is-center">
            <button type="button" class="inv-social" data-inv-share="whatsapp" title="WhatsApp">WA</button>
            <button type="button" class="inv-social" data-inv-share="telegram" title="Telegram">TG</button>
            <button type="button" class="inv-social" data-inv-share="x" title="X">X</button>
            <button type="button" class="inv-social" data-inv-share="facebook" title="Facebook">FB</button>
            <button type="button" class="inv-social" data-inv-share="email" title="Email">@</button>
        </div>
    </div>
</div>
@endsection
