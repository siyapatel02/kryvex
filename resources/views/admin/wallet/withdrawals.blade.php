@extends('admin.layouts.app')

@section('title', 'Withdrawal Requests')

@section('content')
<div class="wallet-page">
    <div class="wallet-hero withdraw-hero" style="display: flex; justify-content: space-between; align-items: center; gap: 20px; flex-wrap: wrap;">
        <div>
            <span class="wallet-kicker">Admin Withdrawals</span>
            <h1>Withdrawal Requests</h1>
            <p>Review payouts, handle approval queue and monitor processed withdrawal history.</p>
        </div>

        <div style="display: flex; gap: 14px; align-items: center; flex-wrap: wrap;">
            <!-- INR Card -->
            <div style="background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 14px; padding: 14px 20px; text-align: left; min-width: 190px; backdrop-filter: blur(8px);">
                <span style="font-size: 11px; text-transform: uppercase; color: #f59e0b; font-weight: 700; letter-spacing: 0.05em; display: block;">🇮🇳 INR Withdrawals</span>
                <div style="display: flex; gap: 14px; margin-top: 6px;">
                    <div>
                        <span style="font-size: 10px; color: #8b8f9b; display: block;">Pending</span>
                        <strong style="font-size: 14px; color: #f59e0b; font-weight: 800; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_pending_withdrawal_amount'] ?? 0), 2) }}</strong>
                    </div>
                    <div>
                        <span style="font-size: 10px; color: #8b8f9b; display: block;">Approved</span>
                        <strong style="font-size: 14px; color: #10b981; font-weight: 800; font-family: 'JetBrains Mono', monospace;">₹{{ number_format((float)($stats['inr_approved_withdrawal_amount'] ?? 0), 2) }}</strong>
                    </div>
                </div>
            </div>

            <!-- USDT Card -->
            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 14px; padding: 14px 20px; text-align: left; min-width: 190px; backdrop-filter: blur(8px);">
                <span style="font-size: 11px; text-transform: uppercase; color: #10b981; font-weight: 700; letter-spacing: 0.05em; display: block;">🇺🇸 USDT Withdrawals</span>
                <div style="display: flex; gap: 14px; margin-top: 6px;">
                    <div>
                        <span style="font-size: 10px; color: #8b8f9b; display: block;">Pending</span>
                        <strong style="font-size: 14px; color: #f59e0b; font-weight: 800; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_pending_withdrawal_amount'] ?? 0), 2) }}</strong>
                    </div>
                    <div>
                        <span style="font-size: 10px; color: #8b8f9b; display: block;">Approved</span>
                        <strong style="font-size: 14px; color: #10b981; font-weight: 800; font-family: 'JetBrains Mono', monospace;">${{ number_format((float)($stats['usdt_approved_withdrawal_amount'] ?? 0), 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="wallet-tabs">
        <a href="{{ route('admin.wallet.index') }}" class="wallet-tab">Overview</a>
        <a href="{{ route('admin.wallet.deposits') }}" class="wallet-tab">Deposits</a>
        <a href="{{ route('admin.wallet.withdrawals') }}" class="wallet-tab active">Withdrawals</a>
        <a href="{{ route('admin.wallet.internal_transfers') }}" class="wallet-tab">Internal Transfers</a>
        <a href="{{ route('admin.wallet.ledger') }}" class="wallet-tab">Ledger</a>
    </div>

    <div class="approval-panel">
        <div class="panel-title-row">
            <div>
                <span>Needs action</span>
                <h2>Approval Queue</h2>
            </div>
            <a href="{{ route('admin.wallet.withdrawals', ['status' => 'pending']) }}" class="view-all-link">View Pending</a>
        </div>

        <div class="approval-list">
            @forelse($pendingQueue as $item)
                <div class="approval-card">
                    <div class="record-user">
                        <span class="record-avatar warning">{{ strtoupper(substr($item->display_user_name ?? 'U', 0, 1)) }}</span>
                        <div>
                            <strong>{{ $item->display_user_name ?? $item->user_name ?? 'Unknown User' }}</strong>
                            <small>{{ $item->display_user_email ?? $item->user_email ?? '-' }}</small>
                        </div>
                    </div>
                    <div class="approval-metric">
                        <span>Request</span>
                        <strong>{{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)($item->display_amount ?? $item->amount ?? 0), $item->display_decimals ?? 2) }}</strong>
                    </div>
                    <div class="approval-metric">
                        <span>Fees</span>
                        <strong>{{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)(($item->withdraw_fee ?? 0) + ($item->platform_charge ?? 0)), $item->display_decimals ?? 2) }}</strong>
                    </div>
                    <div class="approval-metric">
                        <span>User receives</span>
                        <strong>{{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)($item->display_net_amount ?? $item->net_amount ?? $item->amount ?? 0), $item->display_decimals ?? 2) }}</strong>
                    </div>
                    <div class="approval-metric compact-network-metric">
                        <span>Network</span>
                        <strong>{{ $item->network ?? '-' }}</strong>
                    </div>
                    <div class="approval-metric">
                        <span>Requested</span>
                        <strong>{{ isset($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->diffForHumans() : '-' }}</strong>
                    </div>
                    <div class="record-actions right-actions">
                        @php
                            $withdrawalKey = (string)($item->withdrawal_id ?? $item->_id ?? '');
                            $approveFormId = 'approve-withdrawal-' . preg_replace('/[^A-Za-z0-9_-]/', '', $withdrawalKey);
                            $userAddress = trim((string)($item->wallet_address ?? $item->withdrawal_address ?? $item->user_wallet_address ?? $item->address ?? ''));
                            $curSym = $item->display_currency_symbol ?? '$';
                        @endphp

                        <form id="{{ $approveFormId }}" method="POST" action="{{ route('admin.wallet.withdrawals.approve', $withdrawalKey) }}" class="withdraw-approve-hidden-form">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="confirmed_wallet_address" value="">
                        </form>

                        <button class="approve-btn" type="button"
                            data-open-withdraw-approve-modal
                            data-form-id="{{ $approveFormId }}"
                            data-user-name="{{ $item->display_user_name ?? $item->user_name ?? 'Unknown User' }}"
                            data-user-email="{{ $item->display_user_email ?? $item->user_email ?? '-' }}"
                            data-amount="{{ $curSym }}{{ number_format((float)($item->display_amount ?? $item->amount ?? 0), max(4, $item->display_decimals ?? 2)) }}"
                            data-fees="{{ $curSym }}{{ number_format((float)(($item->withdraw_fee ?? 0) + ($item->platform_charge ?? 0)), max(4, $item->display_decimals ?? 2)) }}"
                            data-net="{{ $curSym }}{{ number_format((float)($item->display_net_amount ?? $item->net_amount ?? $item->amount ?? 0), max(4, $item->display_decimals ?? 2)) }}"
                            data-raw-amount="{{ (float)($item->display_net_amount ?? $item->net_amount ?? $item->amount ?? 0) }}"
                            data-network="{{ $item->network ?? '-' }}"
                            data-address="{{ $userAddress }}">
                            Approve
                        </button>
                        <form method="POST" action="{{ route('admin.wallet.withdrawals.reject', $withdrawalKey) }}" data-confirm data-confirm-title="Reject withdrawal?" data-confirm-message="Enter rejection reason. Amount will be released back to available balance." data-confirm-button="Reject" data-confirm-danger="true" data-requires-note="true" data-note-input="admin_note" data-default-note="Rejected by admin">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="admin_note" value="Rejected by admin">
                            <button class="reject-btn" type="submit">Reject</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="empty-state success-empty">All caught up. No pending withdrawal requests right now.</div>
            @endforelse
        </div>
    </div>

    <form class="wallet-filter-card withdrawal-filter" method="GET" style="display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;">
        <div class="filter-group search-wide" style="flex: 1; min-width: 240px;">
            <label>Search history</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Search user, email, amount or status...">
        </div>

        <div class="filter-group" style="min-width: 150px;">
            <label>Currency</label>
            <select name="currency" onchange="this.form.submit()" style="width: 100%; min-height: 42px; border-radius: 10px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.08); color: #fff; padding: 0 12px; outline: none; font-size: 13px; font-weight: 600; cursor: pointer;">
                <option value="ALL" {{ ($currency ?? 'ALL') === 'ALL' ? 'selected' : '' }}>ALL Currencies</option>
                <option value="INR" {{ ($currency ?? '') === 'INR' ? 'selected' : '' }}>🇮🇳 INR Withdrawals</option>
                <option value="USDT" {{ ($currency ?? '') === 'USDT' ? 'selected' : '' }}>🇺🇸 USDT Withdrawals</option>
            </select>
        </div>

        <div class="filter-group" style="min-width: 150px;">
            <label>Status</label>
            <select name="status" onchange="this.form.submit()">
                <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All processed</option>
                <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </div>

        <button type="submit" class="filter-btn" style="min-height: 42px; padding: 0 20px; border-radius: 10px; font-weight: 700;">Apply</button>
        <a href="{{ route('admin.wallet.withdrawals') }}" class="reset-btn" style="min-height: 42px; display: inline-flex; align-items: center; justify-content: center; padding: 0 16px;">Reset</a>
    </form>

    <div class="wallet-table-card polished-table">
        <div class="table-header">
            <div>
                <h2>Processed History</h2>
                <p>Method, reference, admin note and action columns removed for cleaner review.</p>
            </div>
            <span>{{ $withdrawals->total() }} records • Page {{ $withdrawals->currentPage() }} / {{ $withdrawals->lastPage() }}</span>
        </div>

        <div class="table-responsive">
            <table class="admin-table compact-table withdrawal-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Amount</th>
                        <th>Fees</th>
                        <th>User Receives</th>
                        <th>Status</th>
                        <th>Requested At</th>
                        <th>Processed At</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($withdrawals as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->display_user_name ?? $item->user_name ?? 'Unknown User' }}</strong>
                                <small>{{ $item->display_user_email ?? $item->user_email ?? '-' }}</small>
                            </td>

                            <td class="amount negative">-{{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)($item->display_amount ?? $item->amount ?? 0), $item->display_decimals ?? 2) }}</td>

                            <td>
                                <strong>{{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)(($item->withdraw_fee ?? 0) + ($item->platform_charge ?? 0)), $item->display_decimals ?? 2) }}</strong>
                                <small>Fee {{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)($item->withdraw_fee ?? 0), $item->display_decimals ?? 2) }} • Platform {{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)($item->platform_charge ?? 0), $item->display_decimals ?? 2) }}</small>
                            </td>

                            <td class="amount">{{ $item->display_currency_symbol ?? '$' }}{{ number_format((float)($item->display_net_amount ?? $item->net_amount ?? $item->amount ?? 0), $item->display_decimals ?? 2) }}</td>

                            <td>
                                <span class="status-badge {{ $item->status ?? 'pending' }}">
                                    {{ ucfirst($item->status ?? 'pending') }}
                                </span>
                            </td>

                            <td>{{ isset($item->created_at) ? \Carbon\Carbon::parse($item->created_at)->format('d M Y, h:i A') : '-' }}</td>
                            <td>{{ isset($item->processed_at) ? \Carbon\Carbon::parse($item->processed_at)->format('d M Y, h:i A') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">No processed withdrawal records found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.components.pagination', ['paginator' => $withdrawals])

    <div class="admin-modal-backdrop withdraw-approve-modal" id="withdrawApproveModal" aria-hidden="true">
        <div class="admin-modal-card withdraw-approve-card" role="dialog" aria-modal="true" aria-labelledby="withdrawApproveTitle">
            <button type="button" class="admin-modal-close" id="withdrawApproveClose">×</button>

            <div class="withdraw-modal-icon">✓</div>
            <h3 id="withdrawApproveTitle">Approve Withdrawal</h3>
            <p>Copy the user's withdrawal address and paste it below. Approval will work only when the address matches exactly.</p>

            <div class="withdraw-compact-address-card">
                <div class="withdraw-compact-row">
                    <span>Network</span>
                    <strong id="withdrawModalNetwork">--</strong>
                </div>
                <div class="withdraw-compact-row" style="margin-top: 6px;">
                    <span>Requested Amount</span>
                    <strong id="withdrawModalAmount" style="color: #e2e8f0;">--</strong>
                </div>
                <div class="withdraw-compact-row" style="margin-top: 6px;">
                    <span>Fees Deducted</span>
                    <strong id="withdrawModalFees" style="color: #f59e0b;">--</strong>
                </div>
                <div class="withdraw-compact-row" style="margin-top: 6px;">
                    <span>Net Amount User Receives</span>
                    <strong id="withdrawModalNet" style="color: #22c55e;">--</strong>
                </div>
                <div class="withdraw-address-copy-box" style="margin-top: 10px;">
                    <span>User Withdrawal Address</span>
                    <code id="withdrawModalAddress">--</code>
                    <button type="button" id="withdrawCopyAddressButton">Copy</button>
                </div>
            </div>


            <!-- Auto On-Chain Vault Broadcaster Info -->
            <div style="margin-bottom: 14px; background: rgba(34, 197, 94, 0.08); border: 1px solid rgba(34, 197, 94, 0.25); border-radius: 12px; padding: 12px; text-align: left;">
                <div style="font-size: 0.82rem; color: #22c55e; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                    <span>⚡ Automated Multi-Chain Broadcaster Active</span>
                </div>
                <small style="color: rgba(255,255,255,0.6); font-size: 0.76rem; display: block; margin-top: 4px;">
                    Approving will automatically broadcast the payment from Admin Vault on blockchain and save the TXID.
                </small>
            </div>

            <label class="withdraw-confirm-label">
                Paste exact user address
                <input type="text" id="withdrawConfirmAddressInput" placeholder="Paste exact user address here">
            </label>

            <div class="withdraw-confirm-help" id="withdrawConfirmHelp">Address must match exactly before approval.</div>

            <div class="withdraw-modal-actions">
                <button type="button" class="modal-cancel-btn" id="withdrawApproveCancel">Cancel</button>
                <button type="button" class="approve-btn" id="withdrawApproveSubmit" disabled>Approve Withdrawal</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnMetamaskPayout = document.getElementById('btnAdminPayMetamask');
    const statusMsg = document.getElementById('adminMetamaskPayoutStatus');

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-open-withdraw-approve-modal]');
        if (btn) {
            const rawAmt    = btn.getAttribute('data-raw-amount') || '0';
            const netText   = btn.getAttribute('data-net') || '';          // formatted net e.g. "$0.0095"
            const amtText   = btn.getAttribute('data-amount') || '';       // formatted gross e.g. "$0.0100"
            const feeText   = btn.getAttribute('data-fees') || '';         // formatted fees
            const netCode   = (btn.getAttribute('data-network') || 'ETH').toUpperCase();
            const address   = btn.getAttribute('data-address') || '';
            const userName  = btn.getAttribute('data-user-name') || '';
            const formId    = btn.getAttribute('data-form-id') || '';

            // Populate modal fields
            const networkEl = document.getElementById('withdrawModalNetwork');
            const netDisp   = document.getElementById('withdrawModalNet');
            const addrEl    = document.getElementById('withdrawModalAddress');
            const btnPay    = document.getElementById('btnAdminPayMetamask');
            const btcWrap   = document.getElementById('adminBtcContractWrap');
            const confirmInput = document.getElementById('withdrawConfirmAddressInput');
            const submitBtn = document.getElementById('withdrawApproveSubmit');
            const copyBtn   = document.getElementById('withdrawCopyAddressButton');

            if (networkEl) networkEl.textContent = netCode;
            if (netDisp)   netDisp.textContent = netText || rawAmt;
            if (addrEl)    addrEl.textContent = address;

            // Also show gross amount & fees if rows exist
            const amtRow  = document.getElementById('withdrawModalAmount');
            const feeRow  = document.getElementById('withdrawModalFees');
            if (amtRow)  amtRow.textContent = amtText;
            if (feeRow)  feeRow.textContent = feeText;

            // Wire MetaMask pay button
            if (btnPay) {
                btnPay.setAttribute('data-num-amount', rawAmt);
                btnPay.setAttribute('data-network-code', netCode);
                btnPay.setAttribute('data-recipient-address', address);
            }

            // Show/hide BTC contract address field
            if (btcWrap) {
                btcWrap.style.display = netCode.includes('BTC') ? 'block' : 'none';
            }

            // Wire approve form
            if (formId) {
                if (submitBtn) submitBtn.setAttribute('data-form-id', formId);
            }

            // Reset confirm input & submit button
            if (confirmInput) confirmInput.value = '';
            if (submitBtn) submitBtn.disabled = true;

            // Show modal
            const modal = document.getElementById('withdrawApproveModal');
            if (modal) {
                modal.classList.add('active');
                modal.removeAttribute('aria-hidden');
            }
        }

        // Close modal
        const closeBtn = e.target.closest('#withdrawApproveClose, #withdrawApproveCancel');
        if (closeBtn) {
            const modal = document.getElementById('withdrawApproveModal');
            if (modal) { modal.classList.remove('active'); modal.setAttribute('aria-hidden', 'true'); }
        }

        // Copy address button & auto-fill confirm input
        if (e.target.closest('#withdrawCopyAddressButton')) {
            const addr = document.getElementById('withdrawModalAddress')?.textContent?.trim() || '';
            if (addr) {
                const confirmInput = document.getElementById('withdrawConfirmAddressInput');
                if (confirmInput) {
                    confirmInput.value = addr;
                    confirmInput.dispatchEvent(new Event('input'));
                }
                navigator.clipboard?.writeText(addr).then(() => {
                    const copyBtn = document.getElementById('withdrawCopyAddressButton');
                    if (copyBtn) { const orig = copyBtn.textContent; copyBtn.textContent = 'Copied & Pasted!'; setTimeout(() => { copyBtn.textContent = orig; }, 1500); }
                });
            }
        }
    });

    // Confirm address validation → enable approve button
    const confirmInput = document.getElementById('withdrawConfirmAddressInput');
    if (confirmInput) {
        confirmInput.addEventListener('input', () => {
            const addrEl   = document.getElementById('withdrawModalAddress');
            const submitBtn = document.getElementById('withdrawApproveSubmit');
            const expected = (addrEl?.textContent || '').trim().toLowerCase();
            const entered  = (confirmInput.value || '').trim().toLowerCase();
            const helpEl   = document.getElementById('withdrawConfirmHelp');
            const match    = expected && entered && expected === entered;
            if (submitBtn) submitBtn.disabled = !match;
            if (helpEl) {
                helpEl.textContent = match ? '✓ Address matches. Ready to approve.' : 'Address must match exactly before approval.';
                helpEl.style.color = match ? '#22c55e' : '';
            }
        });
    }

    // Approve withdrawal submit
    const approveSubmit = document.getElementById('withdrawApproveSubmit');
    if (approveSubmit) {
        approveSubmit.addEventListener('click', () => {
            const formId = approveSubmit.getAttribute('data-form-id');
            const confirmInput = document.getElementById('withdrawConfirmAddressInput');
            const form   = formId ? document.getElementById(formId) : null;
            if (!form) return;
            const addrField = form.querySelector('input[name="confirmed_wallet_address"]');
            if (addrField) addrField.value = (confirmInput?.value || '').trim();
            form.submit();
        });
    }



    function toWeiHex(amount, decimals = 18) {
        const str = (amount || 0).toString();
        const parts = str.split('.');
        let integerPart = parts[0] || '0';
        let decimalPart = parts[1] || '';
        if (decimalPart.length > decimals) {
            decimalPart = decimalPart.substring(0, decimals);
        } else {
            decimalPart = decimalPart.padEnd(decimals, '0');
        }
        const rawStr = (integerPart + decimalPart).replace(/^0+/, '') || '0';
        const bigVal = BigInt(rawStr);
        return '0x' + bigVal.toString(16);
    }

    function encodeERC20TransferData(recipientAddress, amountTokens, decimals = 18) {
        const methodId = 'a9059cbb';
        const cleanAddr = (recipientAddress || '').replace(/^0x/i, '').toLowerCase().padStart(64, '0');
        const str = (amountTokens || 0).toString();
        const parts = str.split('.');
        let integerPart = parts[0] || '0';
        let decimalPart = parts[1] || '';
        if (decimalPart.length > decimals) {
            decimalPart = decimalPart.substring(0, decimals);
        } else {
            decimalPart = decimalPart.padEnd(decimals, '0');
        }
        const rawStr = (integerPart + decimalPart).replace(/^0+/, '') || '0';
        const bigVal = BigInt(rawStr);
        const paddedAmount = bigVal.toString(16).padStart(64, '0');
        return '0x' + methodId + cleanAddr + paddedAmount;
    }

    if (btnMetamaskPayout) {
        btnMetamaskPayout.addEventListener('click', async () => {
            if (typeof window.ethereum === 'undefined') {
                alert('MetaMask extension is not installed in your browser.');
                return;
            }

            const modalNetwork = (btnMetamaskPayout.getAttribute('data-network-code') || document.getElementById('withdrawModalNetwork')?.textContent || '').trim().toUpperCase();
            const modalAddress = (document.getElementById('withdrawModalAddress')?.textContent || '').trim();
            const confirmInput = document.getElementById('withdrawConfirmAddressInput');

            let userRecipientAddr = confirmInput?.value?.trim() || modalAddress;

            if (!userRecipientAddr || !userRecipientAddr.startsWith('0x') || userRecipientAddr.length !== 42) {
                alert('Please ensure a valid user 0x EVM withdrawal address is provided.');
                return;
            }

            const rawVal = btnMetamaskPayout.getAttribute('data-num-amount') || '0';
            const numAmount = parseFloat(rawVal) || 0;

            try {
                if (statusMsg) { statusMsg.style.color = '#f6851b'; statusMsg.textContent = 'Connecting Admin MetaMask...'; }

                const accounts = await window.ethereum.request({ method: 'eth_requestAccounts' });
                if (!accounts || !accounts.length) { alert('No MetaMask account selected.'); return; }
                const adminAccount = accounts[0];

                if (statusMsg) statusMsg.textContent = 'Switching to Sepolia Testnet...';
                try {
                    await window.ethereum.request({ method: 'wallet_switchEthereumChain', params: [{ chainId: '0xaa36a7' }] });
                } catch (e) {}

                if (statusMsg) statusMsg.textContent = 'Confirm payout transaction in MetaMask window...';

                let txHash;
                if (modalNetwork.includes('BTC')) {
                    const btcContractAddr = document.getElementById('adminBtcContractInput')?.value?.trim() || '0x50148000000000000000000000000000000e66Ab';
                    const transferData = encodeERC20TransferData(userRecipientAddr, numAmount, 18);
                    txHash = await window.ethereum.request({
                        method: 'eth_sendTransaction',
                        params: [{
                            from: adminAccount,
                            to: btcContractAddr,
                            value: '0x0',
                            data: transferData
                        }],
                    });
                } else {
                    const weiHex = toWeiHex(numAmount, 18);
                    txHash = await window.ethereum.request({
                        method: 'eth_sendTransaction',
                        params: [{
                            from: adminAccount,
                            to: userRecipientAddr,
                            value: weiHex
                        }],
                    });
                }

                if (statusMsg) {
                    statusMsg.style.color = '#22c55e';
                    statusMsg.textContent = `Payout Sent! TxID: ${txHash.substring(0, 12)}...`;
                }

                if (confirmInput) {
                    confirmInput.value = userRecipientAddr;
                    confirmInput.dispatchEvent(new Event('input', { bubbles: true }));
                }

                setTimeout(() => {
                    const submitBtn = document.getElementById('withdrawApproveSubmit');
                    if (submitBtn) submitBtn.click();
                }, 500);

            } catch (err) {
                console.error(err);
                if (statusMsg) { statusMsg.style.color = '#ef4444'; statusMsg.textContent = err.message || 'Payout canceled.'; }
            }
        });
    }
});
</script>
@endpush
