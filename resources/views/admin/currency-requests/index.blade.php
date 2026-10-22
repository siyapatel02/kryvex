@extends('admin.layouts.app')

@section('title', 'Currency Change Requests - Kryvex Admin')

@section('content')
<div class="admin-page-header">
    <div>
        <h1 class="admin-page-title">Currency Change Requests</h1>
        <p class="admin-page-subtitle">Review, approve, or reject user requests for changing base currency (INR ↔ USDT).</p>
    </div>
</div>

<!-- STATS CARDS -->
<div class="admin-stats-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px;">
    <div class="admin-stat-card kv-card">
        <span class="stat-label" style="color:var(--kv-muted);font-size:0.8rem;text-transform:uppercase;">Total Requests</span>
        <h3 class="stat-value" style="margin:4px 0 0;font-size:1.6rem;color:#fff;">{{ $stats->total }}</h3>
    </div>
    <div class="admin-stat-card kv-card" style="border-left:4px solid #f59e0b;">
        <span class="stat-label" style="color:#f59e0b;font-size:0.8rem;text-transform:uppercase;">Pending Review</span>
        <h3 class="stat-value" style="margin:4px 0 0;font-size:1.6rem;color:#f59e0b;">{{ $stats->pending }}</h3>
    </div>
    <div class="admin-stat-card kv-card" style="border-left:4px solid #22c55e;">
        <span class="stat-label" style="color:#22c55e;font-size:0.8rem;text-transform:uppercase;">Approved</span>
        <h3 class="stat-value" style="margin:4px 0 0;font-size:1.6rem;color:#22c55e;">{{ $stats->approved }}</h3>
    </div>
    <div class="admin-stat-card kv-card" style="border-left:4px solid #ef4444;">
        <span class="stat-label" style="color:#ef4444;font-size:0.8rem;text-transform:uppercase;">Rejected</span>
        <h3 class="stat-value" style="margin:4px 0 0;font-size:1.6rem;color:#ef4444;">{{ $stats->rejected }}</h3>
    </div>
</div>

<!-- FILTER TABS & SEARCH -->
<div class="admin-toolbar" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px;">
    <div class="admin-tabs" style="display:flex;gap:8px;">
        <a href="{{ route('admin.currency_requests.index', ['tab' => 'pending', 'search' => $search]) }}"
           class="admin-tab-btn {{ $tab === 'pending' ? 'active' : '' }}" style="padding:8px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.85rem;{{ $tab === 'pending' ? 'background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.4);' : 'background:rgba(255,255,255,0.04);color:var(--kv-muted);' }}">
           Pending ({{ $stats->pending }})
        </a>
        <a href="{{ route('admin.currency_requests.index', ['tab' => 'approved', 'search' => $search]) }}"
           class="admin-tab-btn {{ $tab === 'approved' ? 'active' : '' }}" style="padding:8px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.85rem;{{ $tab === 'approved' ? 'background:rgba(34,197,94,0.15);color:#22c55e;border:1px solid rgba(34,197,94,0.4);' : 'background:rgba(255,255,255,0.04);color:var(--kv-muted);' }}">
           Approved ({{ $stats->approved }})
        </a>
        <a href="{{ route('admin.currency_requests.index', ['tab' => 'rejected', 'search' => $search]) }}"
           class="admin-tab-btn {{ $tab === 'rejected' ? 'active' : '' }}" style="padding:8px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.85rem;{{ $tab === 'rejected' ? 'background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.4);' : 'background:rgba(255,255,255,0.04);color:var(--kv-muted);' }}">
           Rejected ({{ $stats->rejected }})
        </a>
        <a href="{{ route('admin.currency_requests.index', ['tab' => 'all', 'search' => $search]) }}"
           class="admin-tab-btn {{ $tab === 'all' ? 'active' : '' }}" style="padding:8px 16px;border-radius:8px;text-decoration:none;font-weight:600;font-size:0.85rem;{{ $tab === 'all' ? 'background:rgba(255,255,255,0.12);color:#fff;border:1px solid rgba(255,255,255,0.2);' : 'background:rgba(255,255,255,0.04);color:var(--kv-muted);' }}">
           All ({{ $stats->total }})
        </a>
    </div>

    <form method="GET" action="{{ route('admin.currency_requests.index') }}" style="display:flex;gap:8px;">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search User Name / Email / UID..." style="padding:8px 14px;background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.1);border-radius:8px;color:#fff;font-size:0.85rem;min-width:240px;">
        <button type="submit" class="admin-btn" style="padding:8px 16px;background:rgba(255,255,255,0.1);border:none;color:#fff;border-radius:8px;cursor:pointer;font-weight:600;font-size:0.85rem;">Search</button>
    </form>
</div>

<!-- REQUESTS TABLE -->
<div class="admin-card kv-card" style="padding:0;overflow:hidden;border-radius:12px;">
    <div style="overflow-x:auto;">
        <table class="admin-table" style="width:100%;border-collapse:collapse;text-align:left;font-size:0.88rem;">
            <thead>
                <tr style="background:rgba(255,255,255,0.03);border-bottom:1px solid rgba(255,255,255,0.08);color:var(--kv-muted);text-transform:uppercase;font-size:0.75rem;letter-spacing:0.05em;">
                    <th style="padding:14px 18px;">User</th>
                    <th style="padding:14px 18px;">Current</th>
                    <th style="padding:14px 18px;">Requested</th>
                    <th style="padding:14px 18px;">Current Balance</th>
                    <th style="padding:14px 18px;">You Will Receive</th>
                    <th style="padding:14px 18px;">Rate</th>
                    <th style="padding:14px 18px;">Status</th>
                    <th style="padding:14px 18px;">Requested At</th>
                    <th style="padding:14px 18px;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $req)
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.05);">
                        <td style="padding:14px 18px;">
                            <strong style="color:#fff;display:block;">{{ $req->user_name }}</strong>
                            <small style="color:var(--kv-muted);font-size:0.78rem;">{{ $req->user_email }}</small>
                        </td>
                        <td style="padding:14px 18px;">
                            <span class="badge" style="padding:4px 8px;border-radius:6px;background:rgba(255,255,255,0.08);color:#fff;font-weight:600;font-size:0.78rem;">{{ $req->current_currency }}</span>
                        </td>
                        <td style="padding:14px 18px;">
                            <span class="badge" style="padding:4px 8px;border-radius:6px;background:rgba(34,197,94,0.15);color:#22c55e;border:1px solid rgba(34,197,94,0.3);font-weight:600;font-size:0.78rem;">➜ {{ $req->requested_currency }}</span>
                        </td>
                        <td style="padding:14px 18px;font-weight:600;color:#fff;">
                            {{ $req->current_balance_formatted }}
                        </td>
                        <td style="padding:14px 18px;font-weight:700;color:#22c55e;">
                            {{ $req->receive_balance_formatted }}
                        </td>
                        <td style="padding:14px 18px;color:var(--kv-muted);">
                            1 USDT = ₹{{ number_format($req->exchange_rate, 2) }}
                        </td>
                        <td style="padding:14px 18px;">
                            @if ($req->status === 'pending')
                                <span class="badge" style="padding:4px 10px;border-radius:20px;background:rgba(245,158,11,0.15);color:#f59e0b;border:1px solid rgba(245,158,11,0.3);font-size:0.78rem;font-weight:600;">Pending Review</span>
                            @elseif ($req->status === 'approved')
                                <span class="badge" style="padding:4px 10px;border-radius:20px;background:rgba(34,197,94,0.15);color:#22c55e;border:1px solid rgba(34,197,94,0.3);font-size:0.78rem;font-weight:600;">Approved</span>
                            @else
                                <span class="badge" style="padding:4px 10px;border-radius:20px;background:rgba(239,68,68,0.15);color:#ef4444;border:1px solid rgba(239,68,68,0.3);font-size:0.78rem;font-weight:600;">Rejected</span>
                            @endif
                        </td>
                        <td style="padding:14px 18px;color:var(--kv-muted);font-size:0.8rem;">
                            {{ \Illuminate\Support\Carbon::parse($req->created_at)->format('M d, Y H:i') }}
                        </td>
                        <td style="padding:14px 18px;text-align:right;">
                            <button type="button" class="admin-btn review-btn"
                                    data-id="{{ $req->id }}"
                                    data-name="{{ $req->user_name }}"
                                    data-email="{{ $req->user_email }}"
                                    data-uid="{{ $req->user_uid }}"
                                    data-current="{{ $req->current_currency }}"
                                    data-requested="{{ $req->requested_currency }}"
                                    data-main="{{ $req->current_balance_formatted }}"
                                    data-receive="{{ $req->receive_balance_formatted }}"
                                    data-trading="{{ number_format($req->trading_balance_usdt, 2) }}"
                                    data-positions="{{ $req->open_positions_count }}"
                                    data-orders="{{ $req->open_orders_count }}"
                                    data-deposits="{{ $req->pending_deposits_count }}"
                                    data-withdrawals="{{ $req->pending_withdrawals_count }}"
                                    data-kyc="{{ $req->kyc_status }}"
                                    data-rate="{{ $req->exchange_rate }}"
                                    data-status="{{ $req->status }}"
                                    data-reason="{{ $req->rejection_reason ?? '' }}"
                                    style="padding:6px 14px;background:rgba(255,255,255,0.1);border:1px solid rgba(255,255,255,0.15);color:#fff;border-radius:6px;cursor:pointer;font-size:0.8rem;font-weight:600;">
                                Review Request
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="padding:40px;text-align:center;color:var(--kv-muted);">
                            No currency change requests found in this tab.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($requests->hasPages())
        <div style="padding:16px;border-top:1px solid rgba(255,255,255,0.06);">
            {{ $requests->links() }}
        </div>
    @endif
</div>

<!-- REVIEW REQUEST MODAL -->
<div id="reviewModal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.75);backdrop-filter:blur(6px);align-items:center;justify-content:center;">
    <div style="background:#13181f;border:1px solid rgba(255,255,255,0.1);border-radius:18px;padding:28px 26px 24px;max-width:560px;width:92%;box-shadow:0 24px 80px rgba(0,0,0,0.6);position:relative;">
        <button type="button" id="closeReviewModalBtn" style="position:absolute;top:16px;right:18px;background:none;border:none;color:rgba(255,255,255,0.4);font-size:1.4rem;cursor:pointer;line-height:1;">✕</button>

        <div style="display:flex;align-items:center;gap:12px;margin-bottom:18px;">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(34,197,94,0.12);border:1px solid rgba(34,197,94,0.3);display:flex;align-items:center;justify-content:center;font-size:1.4rem;">🔎</div>
            <div>
                <h3 style="margin:0;font-size:1.2rem;font-weight:700;color:#fff;">Review Currency Request</h3>
                <p style="margin:2px 0 0;font-size:0.82rem;color:var(--kv-muted);" id="mUserSub">User Details & Automated Checks</p>
            </div>
        </div>

        <input type="hidden" id="mReqId">

        <!-- USER BREAKDOWN GRID -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;background:rgba(0,0,0,0.25);padding:12px;border-radius:10px;border:1px solid rgba(255,255,255,0.06);font-size:0.83rem;">
            <div><span style="color:var(--kv-muted);">User:</span> <strong id="mUserName" style="color:#fff;">--</strong></div>
            <div><span style="color:var(--kv-muted);">KYC:</span> <strong id="mKyc" style="color:#22c55e;">--</strong></div>
            <div><span style="color:var(--kv-muted);">Current Currency:</span> <strong id="mCurrent" style="color:#fff;">--</strong></div>
            <div><span style="color:var(--kv-muted);">Requested:</span> <strong id="mRequested" style="color:#22c55e;">--</strong></div>
            <div><span style="color:var(--kv-muted);">Trading Wallet:</span> <strong id="mTrading" style="color:#fff;">$0.00 USDT</strong></div>
            <div><span style="color:var(--kv-muted);">Open Positions:</span> <strong id="mPositions" style="color:#fff;">0</strong></div>
            <div><span style="color:var(--kv-muted);">Open Orders:</span> <strong id="mOrders" style="color:#fff;">0</strong></div>
            <div><span style="color:var(--kv-muted);">Pending Deposit:</span> <strong id="mDeposits" style="color:#fff;">0</strong></div>
            <div><span style="color:var(--kv-muted);">Pending Withdraw:</span> <strong id="mWithdrawals" style="color:#fff;">0</strong></div>
        </div>

        <!-- CONVERSION PREVIEW -->
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:14px;margin-bottom:18px;font-size:0.85rem;">
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span style="color:var(--kv-muted);">Current Main Balance:</span>
                <strong id="mMainBal" style="color:#fff;">--</strong>
            </div>
            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span style="color:var(--kv-muted);">Conversion Rate:</span>
                <strong id="mRate" style="color:#22c55e;">1 USDT = ₹95.00</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:1px dashed rgba(255,255,255,0.1);align-items:center;">
                <span style="color:#fff;font-weight:600;">Converted Balance Result:</span>
                <strong id="mReceiveBal" style="color:#22c55e;font-size:1.05rem;">--</strong>
            </div>
        </div>

        <!-- REJECTION REASON BOX (Hidden unless rejecting) -->
        <div id="rejectionFormBox" style="display:none;margin-bottom:16px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.25);border-radius:10px;padding:12px;">
            <label style="display:block;color:#ef4444;font-size:0.8rem;font-weight:700;margin-bottom:6px;">Select Rejection Reason:</label>
            <select id="rejectReasonSelect" style="width:100%;padding:8px 12px;background:#0d1117;border:1px solid rgba(255,255,255,0.15);border-radius:6px;color:#fff;font-size:0.85rem;margin-bottom:8px;">
                <option value="Open Orders">Open Orders - Complete pending activities first</option>
                <option value="Compliance">Compliance - Identification update required</option>
                <option value="AML Review">AML Review - Risk verification required</option>
                <option value="Other">Other Reason</option>
            </select>
            <input type="text" id="rejectReasonCustom" placeholder="Enter custom rejection message..." style="width:100%;padding:8px 12px;background:#0d1117;border:1px solid rgba(255,255,255,0.15);border-radius:6px;color:#fff;font-size:0.85rem;display:none;">
        </div>

        <div id="mAlert" style="display:none;margin-bottom:14px;padding:10px 12px;border-radius:8px;font-size:0.82rem;"></div>

        <div id="modalActionButtons" style="display:flex;gap:10px;justify-content:flex-end;">
            <button type="button" id="rejectReqBtn" style="padding:9px 18px;background:rgba(239,68,68,0.15);border:1px solid rgba(239,68,68,0.4);color:#ef4444;border-radius:8px;cursor:pointer;font-weight:700;font-size:0.85rem;">Reject</button>
            <button type="button" id="approveReqBtn" style="padding:9px 22px;background:#22c55e;border:none;color:#000;border-radius:8px;cursor:pointer;font-weight:700;font-size:0.85rem;">Approve Request</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('reviewModal');
    const closeBtn = document.getElementById('closeReviewModalBtn');
    const approveBtn = document.getElementById('approveReqBtn');
    const rejectBtn = document.getElementById('rejectReqBtn');
    const rejectSelect = document.getElementById('rejectReasonSelect');
    const rejectCustom = document.getElementById('rejectReasonCustom');
    const rejBox = document.getElementById('rejectionFormBox');
    const alertEl = document.getElementById('mAlert');

    let isRejectMode = false;

    rejectSelect?.addEventListener('change', () => {
        if (rejectCustom) {
            rejectCustom.style.display = rejectSelect.value === 'Other' ? 'block' : 'none';
        }
    });

    document.querySelectorAll('.review-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
            isRejectMode = false;
            if (rejBox) rejBox.style.display = 'none';
            if (alertEl) alertEl.style.display = 'none';

            document.getElementById('mReqId').value = btn.dataset.id;
            document.getElementById('mUserSub').textContent = `${btn.dataset.name} (${btn.dataset.email})`;
            document.getElementById('mUserName').textContent = btn.dataset.name;
            document.getElementById('mKyc').textContent = btn.dataset.kyc;
            document.getElementById('mCurrent').textContent = btn.dataset.current;
            document.getElementById('mRequested').textContent = btn.dataset.requested;
            document.getElementById('mMainBal').textContent = btn.dataset.main;
            document.getElementById('mRate').textContent = `1 USDT = ₹${Number(btn.dataset.rate).toFixed(2)}`;
            document.getElementById('mReceiveBal').textContent = btn.dataset.receive;
            document.getElementById('mTrading').textContent = `$${btn.dataset.trading} USDT`;
            document.getElementById('mPositions').textContent = btn.dataset.positions;
            document.getElementById('mOrders').textContent = btn.dataset.orders;
            document.getElementById('mDeposits').textContent = btn.dataset.deposits;
            document.getElementById('mWithdrawals').textContent = btn.dataset.withdrawals;

            const isPending = btn.dataset.status === 'pending';
            const actionBtns = document.getElementById('modalActionButtons');
            if (actionBtns) actionBtns.style.display = isPending ? 'flex' : 'none';

            if (modal) modal.style.display = 'flex';
        });
    });

    closeBtn?.addEventListener('click', () => { if (modal) modal.style.display = 'none'; });
    modal?.addEventListener('click', (e) => { if (e.target === modal) modal.style.display = 'none'; });

    approveBtn?.addEventListener('click', async () => {
        const id = document.getElementById('mReqId').value;
        if (!id) {
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = 'rgba(239,68,68,0.12)';
                alertEl.style.color = '#ef4444';
                alertEl.textContent = 'Invalid request ID. Please refresh the page.';
            }
            return;
        }

        approveBtn.disabled = true;
        approveBtn.textContent = 'Approving...';

        try {
            const response = await fetch(`/admin/currency-requests/${id}/approve`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Approval failed.');
            }

            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = 'rgba(34,197,94,0.12)';
                alertEl.style.color = '#22c55e';
                alertEl.textContent = data.message;
            }

            setTimeout(() => window.location.reload(), 1200);
        } catch (err) {
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = 'rgba(239,68,68,0.12)';
                alertEl.style.color = '#ef4444';
                alertEl.textContent = err.message;
            }
            approveBtn.disabled = false;
            approveBtn.textContent = 'Approve Request';
        }
    });

    rejectBtn?.addEventListener('click', async () => {
        const id = document.getElementById('mReqId').value;
        if (!id) {
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = 'rgba(239,68,68,0.12)';
                alertEl.style.color = '#ef4444';
                alertEl.textContent = 'Invalid request ID. Please refresh the page.';
            }
            return;
        }

        if (!isRejectMode) {
            isRejectMode = true;
            if (rejBox) rejBox.style.display = 'block';
            rejectBtn.textContent = 'Confirm Rejection';
            return;
        }

        let reason = rejectSelect?.value || 'Open Orders';
        if (reason === 'Other') {
            reason = rejectCustom?.value?.trim() || 'Please complete all pending activities.';
        }

        rejectBtn.disabled = true;
        rejectBtn.textContent = 'Rejecting...';

        try {
            const response = await fetch(`/admin/currency-requests/${id}/reject`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ reason }),
            });
            const data = await response.json();

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Rejection failed.');
            }

            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = 'rgba(239,68,68,0.12)';
                alertEl.style.color = '#ef4444';
                alertEl.textContent = data.message;
            }

            setTimeout(() => window.location.reload(), 1200);
        } catch (err) {
            if (alertEl) {
                alertEl.style.display = 'block';
                alertEl.style.background = 'rgba(239,68,68,0.12)';
                alertEl.style.color = '#ef4444';
                alertEl.textContent = err.message;
            }
            rejectBtn.disabled = false;
            rejectBtn.textContent = 'Confirm Rejection';
        }
    });
});
</script>
@endpush
@endsection
