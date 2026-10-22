const TIERS = [
    { name: 'Bronze', min: 0, rate: 20 },
    { name: 'Silver', min: 5, rate: 25 },
    { name: 'Gold', min: 20, rate: 30 },
    { name: 'Platinum', min: 50, rate: 40 },
];

const state = {
    code: '',
    link: '',
    referrals: [],
    rewards: [],
    activeTab: 'referrals',
};

const $ = (id) => document.getElementById(id);

const escapeHtml = (str) => String(str ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

const formatUsd = (v) => `$${Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const formatDate = (v) => {
    if (!v) return '--';
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? '--' : d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
};

const maskEmail = (email) => {
    const [name, domain] = String(email || '').split('@');
    if (!domain) return email || '--';
    return `${name.slice(0, 2)}***@${domain}`;
};

function toast(type, title, message) {
    if (typeof window.showToast === 'function') {
        window.showToast({ type, title, message });
    }
}

function getStoredUser() {
    try {
        return JSON.parse(localStorage.getItem('kryvex_user') || '{}') || {};
    } catch {
        return {};
    }
}

function applyUser(user) {
    const code = String(user.referral_code || user.client_id || '').trim();
    state.code = code || '--------';
    state.link = code ? `${window.location.origin}/register?ref=${encodeURIComponent(code)}` : `${window.location.origin}/register`;

    $('invReferralCode').textContent = state.code;
    $('invModalCode').textContent = state.code;
    $('invReferralLink').textContent = state.link;
    $('invModalLink').textContent = state.link;

    const referrals = Array.isArray(user.referrals) ? user.referrals : [];
    const rewards = Array.isArray(user.referral_rewards) ? user.referral_rewards : [];
    state.referrals = referrals;
    state.rewards = rewards;

    const invited = Number(user.total_referrals ?? referrals.length) || 0;
    const verified = Number(user.verified_referrals ?? referrals.filter(r => ['verified', 'approved'].includes(String(r.kyc_status).toLowerCase())).length) || 0;
    const active = Number(user.active_referrals ?? referrals.filter(r => r.is_active || r.status === 'active').length) || 0;
    const earned = Number(user.referral_earnings ?? rewards.reduce((s, r) => s + Number(r.amount || 0), 0)) || 0;

    $('invStatInvited').textContent = invited.toLocaleString('en-US');
    $('invStatVerified').textContent = verified.toLocaleString('en-US');
    $('invStatTraders').textContent = active.toLocaleString('en-US');
    $('invStatEarned').textContent = formatUsd(earned);

    renderTier(active);
    renderTables();
}

function renderTier(active) {
    let idx = 0;
    TIERS.forEach((t, i) => { if (active >= t.min) idx = i; });
    const current = TIERS[idx];
    const next = TIERS[idx + 1];

    $('invCurrentTierBadge').textContent = `${current.name} · ${current.rate}%`;

    document.querySelectorAll('.inv-tier-row').forEach(row => {
        row.classList.toggle('is-current', Number(row.dataset.tier) === idx);
    });

    if (next) {
        const span = next.min - current.min;
        const pct = Math.min(100, Math.max(0, ((active - current.min) / span) * 100));
        $('invTierProgressBar').style.width = `${pct}%`;
        $('invTierProgressText').textContent = `${active} / ${next.min} active friends to ${next.name}`;
    } else {
        $('invTierProgressBar').style.width = '100%';
        $('invTierProgressText').textContent = 'Highest tier reached';
    }
}

function emptyRow(cols, title, text) {
    return `<tr><td colspan="${cols}" class="inv-empty">
        <div class="inv-empty-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
        </div>
        <div class="inv-empty-title">${title}</div>
        <div class="inv-empty-text">${text}</div>
    </td></tr>`;
}

function kycPill(status) {
    const s = String(status || '').toLowerCase();
    if (s === 'verified' || s === 'approved') return '<span class="inv-pill is-green">Verified</span>';
    if (s === 'pending') return '<span class="inv-pill is-amber">Pending</span>';
    return '<span class="inv-pill is-gray">Unverified</span>';
}

function renderTables() {
    const q = ($('invSearchInput').value || '').trim().toLowerCase();
    const match = (...fields) => !q || fields.some(f => String(f || '').toLowerCase().includes(q));

    const referrals = state.referrals.filter(r => match(r.email, r.name, r.client_id));
    $('invReferralsBody').innerHTML = referrals.length
        ? referrals.map(r => {
            const isActive = r.is_active || r.status === 'active';
            return `<tr>
                <td>${escapeHtml(r.name || maskEmail(r.email))}</td>
                <td>${formatDate(r.created_at || r.joined_at)}</td>
                <td>${kycPill(r.kyc_status)}</td>
                <td>${isActive ? '<span class="inv-pill is-green">Active</span>' : '<span class="inv-pill is-gray">Inactive</span>'}</td>
                <td class="is-right">${formatUsd(r.commission ?? r.earned)}</td>
            </tr>`;
        }).join('')
        : emptyRow(5, q ? 'No matching friends' : 'No referrals yet', q ? 'Try a different search.' : 'Share your link to invite your first friend and start earning.');

    const rewards = state.rewards.filter(r => match(r.from, r.email, r.type));
    $('invRewardsBody').innerHTML = rewards.length
        ? rewards.map(r => `<tr>
                <td>${formatDate(r.created_at || r.date)}</td>
                <td>${escapeHtml(r.from || maskEmail(r.email))}</td>
                <td>${escapeHtml(r.type || 'Commission')}</td>
                <td>${String(r.status || 'paid').toLowerCase() === 'paid' ? '<span class="inv-pill is-green">Paid</span>' : '<span class="inv-pill is-amber">Pending</span>'}</td>
                <td class="is-right" style="color:#4ade80;font-weight:700;">+${formatUsd(r.amount)}</td>
            </tr>`).join('')
        : emptyRow(5, q ? 'No matching rewards' : 'No rewards yet', q ? 'Try a different search.' : 'Commissions from your friends\' trades will appear here.');
}

async function copyText(text, btn) {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
    }
    if (btn) {
        const original = btn.innerHTML;
        btn.classList.add('is-copied');
        btn.textContent = 'Copied';
        setTimeout(() => {
            btn.classList.remove('is-copied');
            btn.innerHTML = original;
        }, 1500);
    }
    toast('success', 'Copied!', 'Copied to clipboard.');
}

function share(channel) {
    const message = `Join me on Kryvex and get up to $100 welcome bonus! Use my referral code ${state.code}: ${state.link}`;
    const enc = encodeURIComponent;
    const urls = {
        whatsapp: `https://wa.me/?text=${enc(message)}`,
        telegram: `https://t.me/share/url?url=${enc(state.link)}&text=${enc(message)}`,
        x: `https://twitter.com/intent/tweet?text=${enc(message)}`,
        facebook: `https://www.facebook.com/sharer/sharer.php?u=${enc(state.link)}`,
        email: `mailto:?subject=${enc('Join me on Kryvex')}&body=${enc(message)}`,
    };
    const url = urls[channel];
    if (!url) return;
    if (channel === 'email') {
        window.location.href = url;
    } else {
        window.open(url, '_blank', 'noopener,width=640,height=560');
    }
}

function openInviteModal() {
    if (navigator.share && window.matchMedia('(max-width: 768px)').matches) {
        navigator.share({ title: 'Join me on Kryvex', text: `Use my referral code ${state.code}`, url: state.link }).catch(() => {});
        return;
    }
    $('invInviteModal').hidden = false;
}

function closeInviteModal() {
    $('invInviteModal').hidden = true;
}

function bindEvents() {
    document.addEventListener('click', (e) => {
        const copyBtn = e.target.closest('[data-inv-copy]');
        if (copyBtn) {
            copyText(copyBtn.dataset.invCopy === 'code' ? state.code : state.link, copyBtn);
            return;
        }

        const shareBtn = e.target.closest('[data-inv-share]');
        if (shareBtn) {
            share(shareBtn.dataset.invShare);
            return;
        }

        const tabBtn = e.target.closest('[data-inv-tab]');
        if (tabBtn) {
            state.activeTab = tabBtn.dataset.invTab;
            document.querySelectorAll('[data-inv-tab]').forEach(b => b.classList.toggle('is-active', b === tabBtn));
            document.querySelectorAll('[data-inv-panel]').forEach(p => { p.hidden = p.dataset.invPanel !== state.activeTab; });
            return;
        }

        if (e.target.closest('#invBtnInviteNow')) {
            openInviteModal();
            return;
        }

        if (e.target.closest('[data-inv-close]') || e.target.id === 'invInviteModal') {
            closeInviteModal();
        }
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeInviteModal();
    });

    $('invSearchInput').addEventListener('input', renderTables);
}

async function loadUser() {
    applyUser(getStoredUser());

    const token = localStorage.getItem('kryvex_access_token');
    if (!token) {
        window.location.href = '/login';
        return;
    }

    try {
        const res = await fetch('/api/auth/me', {
            headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
        });
        if (res.status === 401) {
            window.location.href = '/login';
            return;
        }
        const data = await res.json();
        if (res.ok && data.success) {
            const user = data.data?.user || {};
            localStorage.setItem('kryvex_user', JSON.stringify(user));
            applyUser(user);
        }
    } catch (err) {
        console.error('Failed to load referral data', err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    bindEvents();
    loadUser();
});
