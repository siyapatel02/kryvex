@extends('admin.layouts.app')

@section('title', 'User Detail | Kryvex Admin')

@push('styles')
    <style>
        .export-dropdown {
            position: relative;
            display: inline-block;
        }
        .export-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #0b0d13;
            min-width: 140px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.5);
            z-index: 100;
        }
        .export-dropdown-content a {
            color: #fff;
            padding: 10px 14px;
            text-decoration: none;
            display: block;
            font-size: 13px;
            transition: background 0.2s;
            text-align: left;
        }
        .export-dropdown-content a:hover {
            background-color: rgba(255, 255, 255, 0.05);
        }
        .export-dropdown:hover .export-dropdown-content {
            display: block;
        }
    </style>
@endpush

@section('content')
    @php
        $userId = $userId ?? ($user->uid ?? (string) ($user->_id ?? ''));
        $initial = $initial ?? strtoupper(substr($user->name ?? ($user->email ?? 'U'), 0, 1));

        $statusValue = $statusValue ?? ($user->status ?? 'active');
        $kycStatus = $kycStatus ?? ($user->kyc_status ?? 'pending');

        $wallet = $wallet ?? [];
        $wallet['available_balance'] = $wallet['available_balance'] ?? 0;
        $wallet['locked_balance'] = $wallet['locked_balance'] ?? 0;
        $wallet['total_deposit'] = $wallet['total_deposit'] ?? 0;
        $wallet['total_withdraw'] = $wallet['total_withdraw'] ?? ($wallet['total_withdrawal'] ?? 0);

        $ledger = $ledger ?? collect();
        $orders = $orders ?? collect();
        $positions = $positions ?? collect();

        $risk = $risk ?? [];
        $risk['kyc_status'] = $risk['kyc_status'] ?? $kycStatus;
        $risk['last_login'] = $risk['last_login'] ?? null;

        $recentActivity = $recentActivity ?? collect();
    @endphp

    <section class="page-head user-detail-head">
        <div>
            <p class="eyebrow">User Management</p>
            <h1>User Detail</h1>
            <span>Review profile, wallet balance, verification state and account controls.</span>
        </div>

        <div class="page-actions user-export-actions">
            <a class="soft-btn back-btn hover-lift" href="{{ route('admin.users.index') }}">← Back to Users</a>
            <div class="export-dropdown">
                <button class="primary-btn hover-lift" type="button">Export User ▾</button>
                <div class="export-dropdown-content">
                    <a href="{{ route('admin.users.export.csv', (string) ($user->_id ?? $userId)) }}">Download CSV</a>
                    <a href="{{ route('admin.users.export.pdf', (string) ($user->_id ?? $userId)) }}">Download PDF</a>
                </div>
            </div>
            <button class="primary-btn hover-lift" type="button" disabled>Wallet</button>
        </div>
    </section>

    @if(($user->deletion_status ?? '') === 'pending')
        <div style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 24px;">⚠️</span>
                <div>
                    <strong style="color: #f59e0b; font-size: 15px; display: block;">Account Deletion Request Pending</strong>
                    <span style="color: #cbd5e1; font-size: 13px;">User requested account closure on {{ isset($user->deletion_requested_at) ? \Carbon\Carbon::parse($user->deletion_requested_at)->format('d M Y, h:i A') : 'recently' }}. Reason: "{{ $user->deletion_reason ?? 'Not specified' }}"</span>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <form method="POST" action="{{ route('admin.users.approve-deletion', $userId) }}" onsubmit="return confirm('Approve account deletion for this user?');">
                    @csrf
                    <button type="submit" style="background: #dc2626; color: #fff; font-weight: 700; font-size: 12px; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer;">Approve Deletion</button>
                </form>
                <form method="POST" action="{{ route('admin.users.reject-deletion', $userId) }}" onsubmit="return confirm('Reject account deletion?');">
                    @csrf
                    <button type="submit" style="background: rgba(255,255,255,0.08); color: #fff; font-weight: 600; font-size: 12px; padding: 8px 16px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.15); cursor: pointer;">Reject</button>
                </form>
            </div>
        </div>
    @elseif(($user->status ?? '') === 'deleted' || ($user->deletion_status ?? '') === 'approved')
        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.4); border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 24px;">🚫</span>
            <div>
                <strong style="color: #ef4444; font-size: 15px; display: block;">Account Closed / Deleted</strong>
                <span style="color: #cbd5e1; font-size: 13px;">This user account has been terminated. Login access is blocked, but historical trades, orders, and ledger records remain preserved in the database.</span>
            </div>
        </div>
    @endif



    <section class="user-detail-grid">
        <div class="admin-panel user-profile-card hover-panel">
            <div class="profile-top">
                <div class="large-avatar">{{ $initial }}</div>
                <div>
                    <h2>{{ $user->name ?? 'Unnamed User' }}</h2>
                    <p>{{ $user->email ?? 'No email available' }}</p>
                    <div style="display: flex; align-items: center; gap: 8px; margin-top: 4px; flex-wrap: wrap;">
                        <span class="user-id" style="background: rgba(246, 201, 63, 0.15); color: #f59e0b; border: 1px solid rgba(246, 201, 63, 0.3); padding: 2px 8px; border-radius: 6px; font-weight: 800; font-family: monospace;">Client ID: {{ $user->client_id ?? '--' }}</span>
                        <span class="user-id">System ID: {{ $userId }}</span>
                    </div>
                </div>
            </div>

            <div class="profile-meta">
                <div class="hover-card mini-meta">
                    <span>Client ID</span>
                    <strong style="color: #f59e0b; font-family: monospace;">{{ $user->client_id ?? '--' }}</strong>
                </div>

                <div class="hover-card mini-meta">
                    <span>Joined</span>
                    <strong>{{ isset($user->created_at) ? \Carbon\Carbon::parse($user->created_at)->format('d M Y, h:i A') : '—' }}</strong>
                </div>

                <div class="hover-card mini-meta">
                    <span>Email</span>
                    @if (!empty($user->email_verified_at) || !empty($user->email_verified))
                        <strong class="green-text">Verified</strong>
                    @else
                        <strong class="yellow-text">Unverified</strong>
                    @endif
                </div>

                <div class="hover-card mini-meta">
                    <span>Status</span>
                    <strong class="status-text {{ $statusValue }}">{{ ucfirst($statusValue) }}</strong>
                </div>

                <div class="hover-card mini-meta">
                    <span>KYC</span>
                    <strong class="status-text {{ $kycStatus }}">{{ ucfirst($kycStatus) }}</strong>
                </div>
            </div>
        </div>

        <div class="admin-panel risk-card hover-panel admin-account-control-card" id="risk">
            <div class="card-title-row">
                <div>
                    <h2>Account Controls</h2>
                    <p>Schedule suspension, activate, or block this user account.</p>
                </div>
            </div>

            <div class="admin-user-actions">
                <form method="POST" action="{{ route('admin.users.status', (string) ($user->_id ?? $userId)) }}"
                    data-confirm data-confirm-title="Activate user?"
                    data-confirm-message="This will activate the user account and clear suspension."
                    data-confirm-button="Activate">
                    @csrf
                    @method('PATCH')

                    <input type="hidden" name="status" value="active">

                    <button type="submit" class="admin-user-action-btn activate">
                        Activate
                    </button>
                </form>

                <button type="button" id="openSuspendUserModal" class="admin-user-action-btn suspend">
                    Suspend
                </button>

                <button type="button" id="openBlockUserModal" class="admin-user-action-btn block">
                    Block
                </button>
            </div>

            <div class="risk-note">
                Suspension gives the user grace time to close trades before account actions are blocked.
            </div>
        </div>
    </section>

    <!-- Referral & Partner Program Section -->
    <section class="admin-panel hover-panel" style="margin-bottom: 24px; background: #121824; border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(168, 85, 247, 0.15); border: 1px solid rgba(168, 85, 247, 0.3); color: #c084fc; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                    👥
                </div>
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: #fff; margin: 0;">Referral & Partner Program</h2>
                    <p style="font-size: 13px; color: #94a3b8; margin: 2px 0 0;">Track friends referred by this user and commissions earned from trading fees.</p>
                </div>
            </div>
            <div>
                <span style="font-size: 0.82rem; padding: 6px 12px; border-radius: 20px; background: rgba(168, 85, 247, 0.15); color: #c084fc; border: 1px solid rgba(168, 85, 247, 0.3); font-weight: 700;">
                    Referral Code: {{ $user->client_id ?? 'N/A' }}
                </span>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 20px;">
            <div style="background: rgba(0,0,0,0.3); padding: 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.06);">
                <span style="display: block; font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Referred By</span>
                <strong style="font-size: 15px; color: #fff; margin-top: 4px; display: block;">
                    @php
                        $refTargetId = !empty($referrer['id']) ? (string)$referrer['id'] : (!empty($referrer['uid']) ? (string)$referrer['uid'] : '');
                    @endphp
                    @if(!empty($referrer) && !empty($refTargetId))
                        <a href="{{ route('admin.users.show', $refTargetId) }}" style="color: #a855f7; text-decoration: none;">
                            {{ $referrer['name'] ?: $referrer['email'] }} ({{ $referrer['client_id'] ?? 'User' }})
                        </a>
                    @elseif(!empty($referrer))
                        <span style="color: #a855f7;">
                            {{ $referrer['name'] ?: $referrer['email'] }} ({{ $referrer['client_id'] ?? 'User' }})
                        </span>
                    @else
                        <span style="color: #64748b;">Direct (No Referrer)</span>
                    @endif
                </strong>
            </div>

            <div style="background: rgba(0,0,0,0.3); padding: 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.06);">
                <span style="display: block; font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Friends Invited</span>
                <strong style="font-size: 18px; color: #38bdf8; margin-top: 4px; display: block;">
                    {{ count($referredFriends ?? []) }}
                </strong>
            </div>

            <div style="background: rgba(0,0,0,0.3); padding: 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.06);">
                <span style="display: block; font-size: 12px; color: #94a3b8; text-transform: uppercase; font-weight: 600;">Commission Earned</span>
                <strong style="font-size: 18px; color: #4ade80; margin-top: 4px; display: block;">
                    ${{ number_format((float)($totalReferralCommission ?? 0), 2) }} <span style="font-size: 12px; color: #94a3b8;">USDT</span>
                </strong>
            </div>
        </div>

        @if(count($referredFriends ?? []) > 0)
            <div style="margin-top: 10px;">
                <h3 style="font-size: 14px; font-weight: 700; color: #e2e8f0; margin-bottom: 10px;">Referred Users List</h3>
                <div style="overflow-x: auto;">
                    <table class="admin-users-table" style="width: 100%; font-size: 13px;">
                        <thead>
                            <tr style="background: rgba(255,255,255,0.03);">
                                <th style="padding: 8px 12px;">User</th>
                                <th style="padding: 8px 12px;">Client ID</th>
                                <th style="padding: 8px 12px;">Email</th>
                                <th style="padding: 8px 12px;">KYC</th>
                                <th style="padding: 8px 12px;">Status</th>
                                <th style="padding: 8px 12px;">Joined</th>
                                <th style="padding: 8px 12px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($referredFriends as $friend)
                                @php
                                     $friendId = (string)($friend->_id ?? $friend->id ?? $friend->uid ?? '');
                                     $fKyc = strtolower($friend->kyc_status ?? 'pending');
                                @endphp
                                <tr>
                                    <td style="padding: 8px 12px;">{{ $friend->name ?? 'User' }}</td>
                                    <td style="padding: 8px 12px;"><span class="user-client-id">{{ $friend->client_id ?? '--' }}</span></td>
                                    <td style="padding: 8px 12px;">{{ $friend->email ?? '--' }}</td>
                                    <td style="padding: 8px 12px;">
                                        <span class="status-indicator-pill {{ $fKyc }}">
                                            {{ ucfirst($fKyc) }}
                                        </span>
                                    </td>
                                    <td style="padding: 8px 12px;">
                                        <span class="status-indicator-pill {{ strtolower($friend->status ?? 'active') }}">
                                            {{ ucfirst($friend->status ?? 'active') }}
                                        </span>
                                    </td>
                                    <td style="padding: 8px 12px;">{{ optional($friend->created_at)->format('d M Y') ?? '—' }}</td>
                                    <td style="padding: 8px 12px; text-align: right;">
                                        @if(!empty($friendId))
                                            <a href="{{ route('admin.users.show', $friendId) }}" class="table-action view-action" style="padding: 2px 8px; font-size: 12px;">View</a>
                                        @else
                                            <span style="color: #64748b;">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div style="background: rgba(0,0,0,0.15); padding: 14px; border-radius: 8px; text-align: center; color: #64748b; font-size: 13px;">
                This user has not referred any friends yet.
            </div>
        @endif
    </section>

    <!-- MetaTrader 5 Credentials Management (Screenshot 4) -->
    @php
        $mt5Service = app(\App\Services\Mt5BrokerService::class);
        $mt5Status = $mt5Service->getUserBrokerStatus((string) ($user->_id ?? $userId));
        $mt5Acc = $mt5Status['active_account'];
        $mt5Req = $mt5Status['pending_request'];
    @endphp
    <section class="admin-panel hover-panel" style="margin-bottom: 24px; background: #121824; border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 20px;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(246, 201, 63, 0.15); border: 1px solid rgba(246, 201, 63, 0.3); color: #f6c93f; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                🔑
            </div>
            <div>
                <h2 style="font-size: 18px; font-weight: 800; color: #fff; margin: 0;">MetaTrader 5 credentials</h2>
                <p style="font-size: 13px; color: #94a3b8; margin: 2px 0 0;">Assign MT5 login & password — user receives them by email automatically.</p>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; background: rgba(0,0,0,0.25); padding: 14px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.06); margin-bottom: 16px;">
            <div>
                <span style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 2px;">Requested plan</span>
                <strong style="color: #fff; font-size: 15px;">{{ $mt5Req['plan_name'] ?? ($mt5Acc['plan_name'] ?? 'Standard') }}</strong>
            </div>
            <div>
                <span style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 2px;">Currency</span>
                <strong style="color: #fff; font-size: 15px;">USD / USDT</strong>
            </div>
            <div>
                <span style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: 700; display: block; margin-bottom: 2px;">Status</span>
                @if($mt5Acc)
                    <strong style="color: #22c55e; font-size: 14px;">Credentials sent (Active)</strong>
                @elseif($mt5Req)
                    <strong style="color: #facc15; font-size: 14px;">Credentials pending</strong>
                @else
                    <strong style="color: #94a3b8; font-size: 14px;">No Request</strong>
                @endif
            </div>
        </div>

        <form id="adminMt5Form" method="POST" action="{{ route('admin.users.assign-mt5', (string) ($user->_id ?? $userId)) }}" style="display: grid; gap: 14px;">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
                <div>
                    <label style="display: block; font-size: 12px; color: #94a3b8; font-weight: 600; margin-bottom: 4px;">MT5 Login (ID)</label>
                    <input type="text" name="login" value="{{ $mt5Acc['login'] ?? '' }}" placeholder="e.g. 070405" required style="width: 100%; height: 42px; background: #0b0e14; border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #fff; padding: 0 12px; font-size: 14px; font-family: monospace; outline: none;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; color: #94a3b8; font-weight: 600; margin-bottom: 4px;">MT5 Password</label>
                    <input type="text" name="password" value="{{ $mt5Acc['password'] ?? '' }}" placeholder="Enter Password and Send Email." required style="width: 100%; height: 42px; background: #0b0e14; border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #fff; padding: 0 12px; font-size: 14px; outline: none;">
                </div>
                <div>
                    <label style="display: block; font-size: 12px; color: #94a3b8; font-weight: 600; margin-bottom: 4px;">MT5 Server</label>
                    <input type="text" name="server" value="{{ $mt5Acc['server'] ?? 'Kryvex-Live-01' }}" placeholder="Kryvex-Live-01" required style="width: 100%; height: 42px; background: #0b0e14; border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; color: #fff; padding: 0 12px; font-size: 14px; outline: none;">
                </div>
            </div>

            <button type="submit" id="btnAssignMt5" style="width: 100%; height: 46px; background: #8b5cf6; color: #fff; font-size: 15px; font-weight: 800; border: 0; border-radius: 10px; cursor: pointer; transition: background 0.2s ease;">
                Assign & email credentials
            </button>
        </form>
    </section>

    <!-- Account Data Filter Bar -->
    <div class="account-filter-bar" style="margin-bottom: 24px; background: #0d0f17; border: 1px solid rgba(255,255,255,0.08); padding: 14px 20px; border-radius: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="color: #f59e0b; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                ⚡ Filter User Data by Trading Account:
            </span>
            <select onchange="location.href=this.value;" style="background: #141724; border: 1px solid rgba(255,255,255,0.15); color: #fff; padding: 8px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; outline: none; cursor: pointer;">
                <option value="{{ route('admin.users.show', (string)($user->_id ?? $userId)) }}" {{ empty($selectedAccountUid) ? 'selected' : '' }}>
                    🌐 All Accounts (Combined View)
                </option>
                @foreach($tradingAccounts ?? [] as $acc)
                    @php $accArr = (array) $acc; @endphp
                    <option value="{{ route('admin.users.show', (string)($user->_id ?? $userId)) }}?account_uid={{ $accArr['account_uid'] }}" {{ ($selectedAccountUid === $accArr['account_uid']) ? 'selected' : '' }}>
                        {{ $accArr['account_name'] }} {{ !empty($accArr['is_default']) ? '(Default Main)' : '' }} — Avail: ${{ number_format((float)($accArr['demo_available_balance'] ?? 0), 2) }} USDT
                    </option>
                @endforeach
            </select>
        </div>
        @if(!empty($selectedAccountUid))
            <a href="{{ route('admin.users.show', (string)($user->_id ?? $userId)) }}" style="color: #ef4444; font-size: 12px; font-weight: 700; text-decoration: none; padding: 4px 10px; background: rgba(239,68,68,0.12); border-radius: 6px; border: 1px solid rgba(239,68,68,0.25);">
                ✕ Clear Account Filter
            </a>
        @endif
    </div>

    <!-- Wallet Management Section -->
    <section class="admin-panel hover-panel" style="margin-bottom: 24px;" id="wallet-management">
        <div class="card-title-row" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 15px; margin-bottom: 15px;">
            <div>
                <h2 style="font-size: 18px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px;">
                    💳 Wallet & Base Currency Management
                </h2>
                <p style="font-size: 12px; color: #8b8f9b; margin-top: 2px;">Manage base currency assignment, reset currency, and lock currency actions.</p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="soft-btn hover-lift" id="openChangeCurrencyModal" style="font-size: 12px; padding: 6px 14px; background: rgba(59, 130, 246, 0.12); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.3);">
                    [ Change Currency ]
                </button>

                <button type="button" class="soft-btn hover-lift" id="openResetCurrencyModal" style="font-size: 12px; padding: 6px 14px; background: rgba(239, 68, 68, 0.12); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3);">
                    [ Reset Currency ]
                </button>

                <form method="POST" action="{{ route('admin.users.currency.lock', (string)($user->_id ?? $userId)) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="soft-btn hover-lift" style="font-size: 12px; padding: 6px 14px; background: {{ !empty($user->currency_locked) ? 'rgba(245, 158, 11, 0.12)' : 'rgba(255, 255, 255, 0.06)' }}; color: {{ !empty($user->currency_locked) ? '#fbbf24' : '#94a3b8' }}; border: 1px solid rgba(255,255,255,0.1);">
                        {{ !empty($user->currency_locked) ? '🔒 [ Unlock Currency ]' : '🔓 [ Lock Currency ]' }}
                    </button>
                </form>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px;">
            <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 14px; border-radius: 10px;">
                <span style="font-size: 11px; color: #8b8f9b; display: block;">Current Currency</span>
                <strong style="font-size: 16px; color: #fff; margin-top: 4px; display: inline-block;">
                    @if(strtoupper($user->base_currency ?? '') === 'INR')
                        🇮🇳 INR
                    @elseif(strtoupper($user->base_currency ?? '') === 'USDT')
                        🇺🇸 USDT
                    @else
                        <span style="color: #94a3b8; font-weight: 500;">Not Selected</span>
                    @endif
                </strong>
            </div>

            <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 14px; border-radius: 10px;">
                <span style="font-size: 11px; color: #8b8f9b; display: block;">Main Balance</span>
                <strong style="font-size: 16px; color: #10b981; margin-top: 4px; display: inline-block;">
                    @if(strtoupper($user->base_currency ?? '') === 'INR')
                        ₹{{ number_format((float)($wallet['available_balance'] ?? 0), 2) }}
                    @else
                        ${{ number_format((float)($wallet['available_balance'] ?? 0), 2) }}
                    @endif
                </strong>
            </div>

            <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 14px; border-radius: 10px;">
                <span style="font-size: 11px; color: #8b8f9b; display: block;">Trading Balance</span>
                <strong style="font-size: 16px; color: #3b82f6; margin-top: 4px; display: inline-block;">
                    ${{ number_format((float)($wallet['available_balance'] ?? 0), 2) }} USDT
                </strong>
            </div>

            <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 14px; border-radius: 10px;">
                <span style="font-size: 11px; color: #8b8f9b; display: block;">Currency Status</span>
                <strong style="font-size: 14px; margin-top: 4px; display: inline-block;">
                    @if(!empty($user->base_currency))
                        <span class="status-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 3px 8px; border-radius: 6px; font-size: 11px;">Assigned</span>
                    @else
                        <span class="status-badge" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 3px 8px; border-radius: 6px; font-size: 11px;">Not Selected</span>
                    @endif
                </strong>
            </div>

            <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 14px; border-radius: 10px;">
                <span style="font-size: 11px; color: #8b8f9b; display: block;">Lock Status</span>
                <strong style="font-size: 14px; margin-top: 4px; display: inline-block;">
                    @if(!empty($user->currency_locked))
                        <span class="status-badge" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; padding: 3px 8px; border-radius: 6px; font-size: 11px;">Locked 🔒</span>
                    @else
                        <span class="status-badge" style="background: rgba(16, 185, 129, 0.15); color: #10b981; padding: 3px 8px; border-radius: 6px; font-size: 11px;">Unlocked 🔓</span>
                    @endif
                </strong>
            </div>
        </div>

        <!-- User Bank Details Section -->
        <div style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 10px; margin-bottom: 15px;">
                <h3 style="font-size: 14px; font-weight: 700; color: #fff; margin: 0; display: flex; align-items: center; gap: 6px;">
                    🏦 Bank Account Details
                </h3>
                @if(!empty($bankDetails))
                    <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;">✓ Added</span>
                @else
                    <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.3); padding: 2px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;">Not Added</span>
                @endif
            </div>

            @if(!empty($bankDetails))
                @php $b = (object) $bankDetails; @endphp
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
                    <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 12px; border-radius: 8px;">
                        <span style="font-size: 11px; color: #8b8f9b; display: block;">Account Holder</span>
                        <strong style="font-size: 13px; color: #fff; margin-top: 3px; display: block;">{{ $b->account_name ?? '-' }}</strong>
                    </div>
                    <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 12px; border-radius: 8px;">
                        <span style="font-size: 11px; color: #8b8f9b; display: block;">Bank & Type</span>
                        <strong style="font-size: 13px; color: #f6c93f; margin-top: 3px; display: block;">{{ $b->bank_name ?? '-' }} ({{ $b->account_type ?? 'Savings' }})</strong>
                    </div>
                    <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 12px; border-radius: 8px;">
                        <span style="font-size: 11px; color: #8b8f9b; display: block;">Account Number</span>
                        <strong style="font-size: 13px; color: #fff; font-family: monospace; margin-top: 3px; display: block;">{{ $b->account_number ?? '-' }}</strong>
                    </div>
                    <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 12px; border-radius: 8px;">
                        <span style="font-size: 11px; color: #8b8f9b; display: block;">IFSC Code</span>
                        <strong style="font-size: 13px; color: #60a5fa; font-family: monospace; margin-top: 3px; display: block;">{{ $b->ifsc_code ?? '-' }}</strong>
                    </div>
                    @if(!empty($b->upi_id))
                        <div class="hover-card" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); padding: 12px; border-radius: 8px;">
                            <span style="font-size: 11px; color: #8b8f9b; display: block;">UPI ID</span>
                            <strong style="font-size: 13px; color: #a7f3d0; font-family: monospace; margin-top: 3px; display: block;">{{ $b->upi_id }}</strong>
                        </div>
                    @endif
                </div>
            @else
                <div style="text-align: center; padding: 15px 0; color: #64748b; font-size: 12px;">
                    User has not added bank account details yet.
                </div>
            @endif
        </div>
    </section>

        <!-- Currency Action History Log Table -->
        <div>
            <h3 style="font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 10px;">📜 Currency History Log</h3>
            <div class="table-responsive">
                <table class="admin-table mini-table" style="width: 100%; font-size: 12px;">
                    <thead>
                        <tr style="text-align: left; color: #8b8f9b;">
                            <th>Date & Time</th>
                            <th>Action</th>
                            <th>Change</th>
                            <th>Performed By</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($currencyHistories ?? [] as $hist)
                            <tr>
                                <td>{{ isset($hist->created_at) ? \Carbon\Carbon::parse($hist->created_at)->format('d M Y, h:i A') : '-' }}</td>
                                <td>
                                    @php
                                        $act = strtolower($hist->action ?? '');
                                        $badgeBg = $act === 'reset' ? 'rgba(239, 68, 68, 0.15)' : ($act === 'assigned' || $act === 'change' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(245, 158, 11, 0.15)');
                                        $badgeColor = $act === 'reset' ? '#ef4444' : ($act === 'assigned' || $act === 'change' ? '#10b981' : '#f59e0b');
                                    @endphp
                                    <span class="status-badge" style="background: {{ $badgeBg }}; color: {{ $badgeColor }}; padding: 2px 8px; border-radius: 6px; font-size: 11px;">
                                        {{ ucfirst($hist->action ?? '-') }}
                                    </span>
                                </td>
                                <td><strong>{{ $hist->old_currency ?? 'NULL' }}</strong> → <strong>{{ $hist->new_currency ?? 'NULL' }}</strong></td>
                                <td>{{ $hist->performed_by ?? 'Admin' }}</td>
                                <td style="color: #94a3b8; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $hist->reason ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state" style="text-align: center; padding: 14px; color: #8b8f9b;">No currency action history found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- User Trading Sub-Accounts Section -->
    <section class="admin-panel hover-panel" style="margin-bottom: 24px;" id="trading-accounts">
        <div class="card-title-row" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 15px; margin-bottom: 15px;">
            <div>
                <h2 style="font-size: 18px; font-weight: 800; color: #fff; display: flex; align-items: center; gap: 8px;">
                    ⚡ User Trading Accounts
                </h2>
                <p style="font-size: 12px; color: #8b8f9b; margin-top: 2px;">Sub-trading accounts created by this user and their isolated balances.</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="admin-table mini-table" style="width: 100%; font-size: 12px;">
                <thead>
                    <tr style="text-align: left; color: #8b8f9b;">
                        <th>Account Name</th>
                        <th>Account Type</th>
                        <th>Trading Available</th>
                        <th>Trading Locked</th>
                        <th>Created Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tradingAccounts ?? [] as $acc)
                        @php
                            $accArr = (array) $acc;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $accArr['account_name'] ?? 'Account' }}</strong>
                                @if(!empty($accArr['is_default']))
                                    <span class="status-badge" style="background: rgba(34, 197, 94, 0.15); color: #22c55e; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-left: 6px;">Default</span>
                                @endif
                            </td>
                            <td><span style="color: #94a3b8;">Trading Sub-Account</span></td>
                            <td><strong style="color: #22c55e;">${{ number_format((float) ($accArr['demo_available_balance'] ?? 0), 2) }} USDT</strong></td>
                            <td><strong style="color: #f59e0b;">${{ number_format((float) ($accArr['demo_locked_balance'] ?? 0), 2) }} USDT</strong></td>
                            <td>{{ isset($accArr['created_at']) ? \Carbon\Carbon::parse($accArr['created_at'])->format('d M Y, h:i A') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state" style="text-align: center; padding: 14px; color: #8b8f9b;">No trading sub-accounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="wallet-summary-grid" id="wallet">
        <div class="wallet-summary-card hover-card">
            <span>Available Balance</span>
            <strong>₹{{ number_format((float) $wallet['available_balance'], 2) }}</strong>
            <small>Current usable wallet balance</small>
        </div>

        <div class="wallet-summary-card hover-card">
            <span>Locked Balance</span>
            <strong>₹{{ number_format((float) $wallet['locked_balance'], 2) }}</strong>
            <small>Orders / withdrawals locked amount</small>
        </div>

        <div class="wallet-summary-card hover-card">
            <span>Total Deposits</span>
            <strong>₹{{ number_format((float) $wallet['total_deposit'], 2) }}</strong>
            <small>Ledger based deposit total</small>
        </div>

        <div class="wallet-summary-card hover-card">
            <span>Total Withdrawals</span>
            <strong>₹{{ number_format((float) $wallet['total_withdraw'], 2) }}</strong>
            <small>Ledger based withdrawal total</small>
        </div>
    </section>

    <section class="user-relations-grid">
        <!-- Deposits Card -->
        <div class="admin-panel hover-panel">
            <div class="card-title-row" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 15px; margin-bottom: 15px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: #fff;">Recent Deposits</h2>
                    <p style="font-size: 12px; color: #8b8f9b; margin-top: 2px;">Latest 5 completed deposit records.</p>
                </div>
                <a href="{{ route('admin.wallet.deposits', ['search' => $user->email ?? '']) }}" class="soft-btn hover-lift" style="font-size: 12px; padding: 5px 12px;">View All</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table mini-table" style="width: 100%; font-size: 13px;">
                    <thead>
                        <tr style="text-align: left; color: #8b8f9b;">
                            <th>Reference</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($deposits as $dep)
                            <tr>
                                <td><strong>{{ $dep->reference ?? '-' }}</strong><small style="display:block; color:#64748b;">{{ ucfirst(str_replace('_', ' ', $dep->type ?? 'deposit')) }}</small></td>
                                <td style="color: #10b981; font-weight: 750;">+${{ number_format(abs((float)($dep->amount ?? 0)), 2) }}</td>
                                <td>{{ isset($dep->created_at) ? \Carbon\Carbon::parse($dep->created_at)->format('d M Y') : '-' }}</td>
                                <td><span class="status-badge completed" style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 2px 8px; border-radius: 6px; font-size: 11px;">Completed</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state" style="text-align: center; padding: 20px; color: #8b8f9b;">No deposit records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Withdrawals Card -->
        <div class="admin-panel hover-panel">
            <div class="card-title-row" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 15px; margin-bottom: 15px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: #fff;">Recent Withdrawals</h2>
                    <p style="font-size: 12px; color: #8b8f9b; margin-top: 2px;">Latest 5 withdrawal request states.</p>
                </div>
                <a href="{{ route('admin.wallet.withdrawals', ['search' => $user->email ?? '']) }}" class="soft-btn hover-lift" style="font-size: 12px; padding: 5px 12px;">View All</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table mini-table" style="width: 100%; font-size: 13px;">
                    <thead>
                        <tr style="text-align: left; color: #8b8f9b;">
                            <th>Details</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($withdrawals as $wd)
                            @php
                                $status = strtolower($wd->status ?? 'pending');
                                $badgeClass = $status === 'approved' ? 'completed' : ($status === 'rejected' ? 'rejected' : 'pending');
                                $color = $status === 'approved' ? '#10b981' : ($status === 'rejected' ? '#ef4444' : '#f59e0b');
                                $bg = $status === 'approved' ? 'rgba(16, 185, 129, 0.1)' : ($status === 'rejected' ? 'rgba(239, 68, 68, 0.1)' : 'rgba(245, 158, 11, 0.1)');
                                
                                $withdrawalId = (string)($wd->_id ?? $wd->withdrawal_id ?? $wd->id ?? '');
                                $isINR = ($wd->currency ?? $wd->wallet_currency ?? '') === 'INR';
                                $curSym = $isINR ? '₹' : '$';

                                $amt = (float)($isINR ? ($wd->amount_inr ?? $wd->amount ?? 0) : ($wd->amount ?? 0));
                                $feeTotal = (float)($isINR ? ($wd->amount_inr_charge ?? (($wd->withdraw_fee ?? 0) + ($wd->platform_charge ?? 0))) : (($wd->withdraw_fee ?? 0) + ($wd->platform_charge ?? 0)));
                                $netAmt = (float)($isINR ? ($wd->amount_inr_net ?? $wd->net_amount ?? $amt) : ($wd->net_amount ?? $amt));

                                $userAddress = trim((string)($wd->wallet_address ?? $wd->reference ?? ''));
                                $approveFormId = 'approve-user-wd-' . preg_replace('/[^A-Za-z0-9_-]/', '', $withdrawalId);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $wd->network ?? $wd->method ?? ($isINR ? 'INR Bank' : '-') }}</strong>
                                    <small style="display:block; color:#64748b; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $userAddress }}">{{ $userAddress }}</small>
                                </td>
                                <td style="font-weight: 750;">
                                    {{ $curSym }}{{ number_format($amt, 2) }}
                                    @if($netAmt > 0 && $netAmt !== $amt)
                                        <small style="display:block; color:#10b981; font-weight: 600;">Recv: {{ $curSym }}{{ number_format($netAmt, 2) }}</small>
                                    @endif
                                </td>
                                <td>{{ isset($wd->created_at) ? \Carbon\Carbon::parse($wd->created_at)->format('d M Y') : '-' }}</td>
                                <td><span class="status-badge {{ $badgeClass }}" style="background: {{ $bg }}; color: {{ $color }}; padding: 2px 8px; border-radius: 6px; font-size: 11px;">{{ ucfirst($status) }}</span></td>
                                <td style="text-align: right;">
                                    @if($status === 'pending' && $withdrawalId !== '')
                                        <div style="display: flex; gap: 6px; align-items: center; justify-content: flex-end;">
                                            <form id="{{ $approveFormId }}" method="POST" action="{{ route('admin.wallet.withdrawals.approve', $withdrawalId) }}" class="withdraw-approve-hidden-form">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="confirmed_wallet_address" value="">
                                            </form>

                                            <button class="dash-approve-btn" type="button"
                                                style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; background: #22c55e; color: #000; border: none; cursor: pointer;"
                                                data-open-withdraw-approve-modal
                                                data-form-id="{{ $approveFormId }}"
                                                data-user-name="{{ $user->name ?? 'User' }}"
                                                data-user-email="{{ $user->email ?? '-' }}"
                                                data-amount="{{ $curSym }}{{ number_format($amt, 2) }}"
                                                data-fees="{{ $curSym }}{{ number_format($feeTotal, 2) }}"
                                                data-net="{{ $curSym }}{{ number_format($netAmt, 2) }}"
                                                data-network="{{ $wd->network ?? $wd->method ?? ($isINR ? 'INR' : '-') }}"
                                                data-address="{{ $userAddress }}">
                                                Approve
                                            </button>

                                            <form method="POST" action="{{ route('admin.wallet.withdrawals.reject', $withdrawalId) }}"
                                                  data-confirm
                                                  data-confirm-title="Reject withdrawal?"
                                                  data-confirm-message="Amount will be returned to user available balance."
                                                  data-confirm-button="Reject"
                                                  data-confirm-danger="true"
                                                  data-requires-note="true"
                                                  data-note-input="admin_note"
                                                  data-default-note="Rejected by admin">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="admin_note" value="Rejected by admin">
                                                <button type="submit" class="dash-reject-btn" style="padding: 4px 10px; font-size: 11px; font-weight: 700; border-radius: 6px; background: rgba(239,68,68,0.2); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); cursor: pointer;">Reject</button>
                                            </form>
                                        </div>
                                    @else
                                        <span style="color: #64748b; font-size: 11px;">Completed</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state" style="text-align: center; padding: 20px; color: #8b8f9b;">No withdrawal records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Trades/Orders Card -->
        <div class="admin-panel hover-panel">
            <div class="card-title-row" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 15px; margin-bottom: 15px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: #fff;">Recent Orders</h2>
                    <p style="font-size: 12px; color: #8b8f9b; margin-top: 2px;">Latest 5 trade orders in futures markets.</p>
                </div>
                <a href="{{ route('admin.orders.index', ['search' => $user->email ?? '']) }}" class="soft-btn hover-lift" style="font-size: 12px; padding: 5px 12px;">View All</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table mini-table" style="width: 100%; font-size: 13px;">
                    <thead>
                        <tr style="text-align: left; color: #8b8f9b;">
                            <th>Symbol</th>
                            <th>Side / Type</th>
                            <th>Qty / Price</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $ord)
                            @php
                                $side = strtolower($ord->side ?? 'buy');
                                $sideColor = $side === 'buy' || $side === 'long' ? '#10b981' : '#ef4444';
                                $status = strtolower($ord->display_status ?? $ord->status ?? 'pending');
                                $color = in_array($status, ['active', 'open']) ? '#10b981' : (in_array($status, ['cancelled', 'canceled', 'rejected']) ? '#ef4444' : '#f59e0b');
                                $bg = in_array($status, ['active', 'open']) ? 'rgba(16, 185, 129, 0.1)' : (in_array($status, ['cancelled', 'canceled', 'rejected']) ? 'rgba(239, 68, 68, 0.1)' : 'rgba(245, 158, 11, 0.1)');
                            @endphp
                            <tr>
                                <td><strong>{{ strtoupper($ord->symbol ?? '-') }}</strong><small style="display:block; color:#64748b;">{{ $ord->mode ?? 'demo' }}</small></td>
                                <td><span style="color: {{ $sideColor }}; font-weight: bold;">{{ strtoupper($side) }}</span><small style="display:block; color:#64748b;">{{ ucfirst($ord->type ?? 'market') }}</small></td>
                                <td><strong>{{ $ord->quantity ?? $ord->qty ?? $ord->estimated_quantity ?? '-' }}</strong><small style="display:block; color:#64748b;">@ ${{ number_format((float)($ord->execution_price ?? $ord->price ?? 0), 2) }}</small></td>
                                <td><span class="status-badge" style="background: {{ $bg }}; color: {{ $color }}; padding: 2px 8px; border-radius: 6px; font-size: 11px;">{{ ucfirst($status) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state" style="text-align: center; padding: 20px; color: #8b8f9b;">No order records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Positions Card -->
        <div class="admin-panel hover-panel">
            <div class="card-title-row" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 15px; margin-bottom: 15px;">
                <div>
                    <h2 style="font-size: 18px; font-weight: 800; color: #fff;">Open Positions</h2>
                    <p style="font-size: 12px; color: #8b8f9b; margin-top: 2px;">Latest 5 open position states.</p>
                </div>
                <a href="{{ route('admin.positions.index', ['search' => $user->email ?? '']) }}" class="soft-btn hover-lift" style="font-size: 12px; padding: 5px 12px;">View All</a>
            </div>
            <div class="table-responsive">
                <table class="admin-table mini-table" style="width: 100%; font-size: 13px;">
                    <thead>
                        <tr style="text-align: left; color: #8b8f9b;">
                            <th>Symbol</th>
                            <th>Market</th>
                            <th>Qty / Margin</th>
                            <th>Entry Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($positions as $pos)
                            @php
                                $side = strtolower($pos->side ?? $pos->contract_type ?? 'buy');
                                $sideColor = in_array($side, ['buy', 'long', 'call']) ? '#10b981' : '#ef4444';
                                $marketType = ucfirst($pos->market_type ?? 'futures');
                                $badgeColor = ($pos->market_type ?? 'futures') === 'options' ? '#3b82f6' : '#a855f7';
                                $badgeBg = ($pos->market_type ?? 'futures') === 'options' ? 'rgba(59, 130, 246, 0.15)' : 'rgba(168, 85, 247, 0.15)';
                            @endphp
                            <tr>
                                <td><strong>{{ strtoupper($pos->symbol ?? '-') }}</strong><span style="color: {{ $sideColor }}; font-weight: bold; font-size:11px; margin-left: 5px;">{{ strtoupper($side) }}</span></td>
                                <td><span class="status-badge" style="background: {{ $badgeBg }}; color: {{ $badgeColor }}; padding: 2px 8px; border-radius: 6px; font-size: 11px;">{{ $marketType }}</span></td>
                                <td><strong>{{ $pos->quantity ?? $pos->qty ?? '-' }}</strong><small style="display:block; color:#64748b;">Margin: ${{ number_format((float)($pos->margin ?? 0), 2) }}</small></td>
                                <td><strong>${{ number_format((float)($pos->entry_price ?? $pos->execution_price ?? 0), 4) }}</strong></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state" style="text-align: center; padding: 20px; color: #8b8f9b;">No open positions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="detail-bottom-grid">
        <div class="admin-panel detail-panel hover-panel">
            <div class="card-title-row">
                <div>
                    <h2>Account Overview</h2>
                    <p>Core user information stored in MongoDB.</p>
                </div>
            </div>

            <div class="info-list">
                <div><span>Name</span><strong>{{ $user->name ?? '—' }}</strong></div>
                <div><span>Email</span><strong>{{ $user->email ?? '—' }}</strong></div>
                <div><span>Google
                        Login</span><strong>{{ !empty($user->google_id) ? 'Connected' : 'Not Connected' }}</strong></div>
                <div>
                    <span>Last Login</span>
                    <strong>
                        {{ !empty($risk['last_login']) ? \Carbon\Carbon::parse($risk['last_login'])->format('d M Y, h:i A') : '—' }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="admin-panel detail-panel hover-panel">
            <div class="card-title-row">
                <div>
                    <h2>Recent Activity</h2>
                    <p>Latest wallet and order activity.</p>
                </div>
            </div>

            <div class="activity-list">
                @forelse ($recentActivity as $activity)
                    <div class="activity-item hover-card">
                        <div>
                            <strong>{{ $activity['label'] ?? 'Activity' }}</strong>
                            <span>
                                {{ $activity['type'] ?? '-' }}
                                •
                                {{ !empty($activity['date']) ? \Carbon\Carbon::parse($activity['date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <em class="activity-status {{ $activity['status'] ?? 'completed' }}">
                            {{ ucfirst($activity['status'] ?? 'completed') }}
                        </em>
                    </div>
                @empty
                    <div class="empty-state">No recent activity found.</div>
                @endforelse
            </div>
        </div>
    </section>

    {{--
        Admin account restriction modals.
        - Markup stays in Blade.
        - CSS lives in resources/css/admin/users.css.
        - Open/close JS lives in resources/js/admin/user-detail.js.
    --}}
    <div id="suspendUserModal" class="admin-suspend-modal" aria-hidden="true">
        <div class="admin-suspend-backdrop" data-close-suspend-modal></div>

        <div class="admin-suspend-box" role="dialog" aria-modal="true" aria-labelledby="suspendUserTitle">
            <div class="admin-suspend-icon">!</div>

            <div class="admin-suspend-head">
                <h2 id="suspendUserTitle">Schedule User Suspension</h2>
                <p>
                    User will receive a warning first. After grace time ends, wallet and trading access will be suspended.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.users.status', (string) ($user->_id ?? $userId)) }}"
                class="admin-suspend-form">
                @csrf
                @method('PATCH')

                <input type="hidden" name="status" value="suspended">

                <label>
                    <span>Suspension reason</span>
                    <textarea name="reason" required rows="4"
                        placeholder="Example: Suspicious activity, policy violation, risk review..."></textarea>
                </label>

                <div class="admin-suspend-grid">
                    <label>
                        <span>Suspend after</span>

                        <div class="admin-suspend-input-wrap">
                            <input type="number" name="delay_minutes" min="1" max="1440" step="1" value="10"
                                required inputmode="numeric" placeholder="Enter minutes">
                            <em>minutes</em>
                        </div>

                        <small class="admin-suspend-help">
                            User remains active for this grace time.
                        </small>
                    </label>

                    <label>
                        <span>Suspension duration</span>

                        <div class="admin-suspend-input-wrap">
                            <input type="number" name="duration_hours" min="1" max="720" step="1" value="12"
                                required inputmode="numeric" placeholder="Enter hours">
                            <em>hours</em>
                        </div>

                        <small class="admin-suspend-help">
                            Account auto-activates after this duration.
                        </small>
                    </label>
                </div>

                <div class="admin-suspend-warning">
                    During grace time, user can close open trades. After suspension starts, wallet and trading actions will be blocked.
                </div>

                <div class="admin-suspend-actions">
                    <button type="button" class="admin-suspend-cancel" data-close-suspend-modal>
                        Cancel
                    </button>

                    <button type="submit" class="admin-suspend-submit">
                        Schedule Suspension
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="blockUserModal" class="admin-suspend-modal" aria-hidden="true">
        <div class="admin-suspend-backdrop" data-close-block-modal></div>

        <div class="admin-suspend-box admin-block-box" role="dialog" aria-modal="true" aria-labelledby="blockUserTitle">
            <div class="admin-suspend-icon admin-block-icon">×</div>

            <div class="admin-suspend-head">
                <h2 id="blockUserTitle">Schedule User Block</h2>
                <p>
                    User will receive a final warning first. After grace time ends, the account will no longer be accessible.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.users.status', (string) ($user->_id ?? $userId)) }}"
                class="admin-suspend-form">
                @csrf
                @method('PATCH')

                <input type="hidden" name="status" value="blocked">

                <label>
                    <span>Block reason</span>
                    <textarea name="reason" required rows="4"
                        placeholder="Example: Security risk, compliance issue, repeated policy violation..."></textarea>
                </label>

                <div class="admin-suspend-grid single">
                    <label>
                        <span>Block after</span>

                        <div class="admin-suspend-input-wrap danger">
                            <input type="number" name="block_delay_minutes" min="1" max="1440" step="1" value="10"
                                required inputmode="numeric" placeholder="Enter minutes">
                            <em>minutes</em>
                        </div>

                        <small class="admin-suspend-help">
                            User can use this grace time to make final account changes before access is blocked.
                        </small>
                    </label>
                </div>

                <div class="admin-suspend-warning admin-block-warning">
                    After this grace time ends, the user will not be able to access trading or wallet actions until admin activates the account.
                </div>

                <div class="admin-suspend-actions">
                    <button type="button" class="admin-suspend-cancel" data-close-block-modal>
                        Cancel
                    </button>

                    <button type="submit" class="admin-suspend-submit admin-block-submit">
                        Schedule Block
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset User Currency Modal -->
    <div id="resetCurrencyModal" class="admin-suspend-modal" aria-hidden="true">
        <div class="admin-suspend-backdrop" data-close-reset-modal></div>

        <div class="admin-suspend-box admin-block-box" role="dialog" aria-modal="true" aria-labelledby="resetUserTitle" style="max-width: 520px;">
            <div class="admin-suspend-icon admin-block-icon" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3);">↺</div>

            <div class="admin-suspend-head">
                <h2 id="resetUserTitle" style="font-size: 20px; font-weight: 800;">Reset User Currency</h2>
                <p style="margin-top: 4px;">
                    Current Currency: <strong style="color:#fff;">{{ $user->base_currency ?: 'Not Selected' }}</strong>
                </p>
            </div>

            <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); color: #fca5a5; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;">
                ⚠ <strong>Warning:</strong> This will remove the current currency assignment (set <code>base_currency = NULL</code>). Next time user logs in, they will be prompted to choose wallet currency again.
            </div>

            <!-- Requirements Check List -->
            <div style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 10px; padding: 14px; margin-bottom: 16px;">
                <h4 style="font-size: 12px; font-weight: 700; color: #8b8f9b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">System Requirements Checklist</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 12px;">
                    <div style="color: {{ $resetChecklist['main_balance_zero'] ? '#10b981' : '#ef4444' }};">
                        {{ $resetChecklist['main_balance_zero'] ? '✔' : '✖' }} Main Balance = 0
                    </div>
                    <div style="color: {{ $resetChecklist['trading_balance_zero'] ? '#10b981' : '#ef4444' }};">
                        {{ $resetChecklist['trading_balance_zero'] ? '✔' : '✖' }} Trading Balance = 0
                    </div>
                    <div style="color: {{ $resetChecklist['no_open_positions'] ? '#10b981' : '#ef4444' }};">
                        {{ $resetChecklist['no_open_positions'] ? '✔' : '✖' }} No Open Positions ({{ $resetChecklist['open_positions'] }})
                    </div>
                    <div style="color: {{ $resetChecklist['no_pending_orders'] ? '#10b981' : '#ef4444' }};">
                        {{ $resetChecklist['no_pending_orders'] ? '✔' : '✖' }} No Pending Orders ({{ $resetChecklist['pending_orders'] }})
                    </div>
                    <div style="color: {{ $resetChecklist['no_pending_withdrawals'] ? '#10b981' : '#ef4444' }};">
                        {{ $resetChecklist['no_pending_withdrawals'] ? '✔' : '✖' }} No Pending Withdraw ({{ $resetChecklist['pending_withdrawals'] }})
                    </div>
                    <div style="color: {{ $resetChecklist['no_pending_deposits'] ? '#10b981' : '#ef4444' }};">
                        {{ $resetChecklist['no_pending_deposits'] ? '✔' : '✖' }} No Pending Deposit ({{ $resetChecklist['pending_deposits'] }})
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.users.currency.reset', (string) ($user->_id ?? $userId)) }}" class="admin-suspend-form">
                @csrf
                <label>
                    <span>Reason for reset</span>
                    <textarea name="reason" required rows="3" placeholder="Enter administrative reason for currency reset..."></textarea>
                </label>

                <div class="admin-suspend-actions" style="margin-top: 16px;">
                    <button type="button" class="admin-suspend-cancel" data-close-reset-modal>
                        Cancel
                    </button>

                    <button type="submit" class="admin-suspend-submit admin-block-submit">
                        Reset Currency
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change User Currency Modal -->
    <div id="changeCurrencyModal" class="admin-suspend-modal" aria-hidden="true">
        <div class="admin-suspend-backdrop" data-close-change-modal></div>

        <div class="admin-suspend-box" role="dialog" aria-modal="true" aria-labelledby="changeUserTitle" style="max-width: 480px;">
            <div class="admin-suspend-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.3);">⚙</div>

            <div class="admin-suspend-head">
                <h2 id="changeUserTitle" style="font-size: 20px; font-weight: 800;">Assign / Change User Currency</h2>
                <p style="margin-top: 4px;">
                    Select the new base currency to assign to this user.
                </p>
            </div>

            <form method="POST" action="{{ route('admin.users.currency.change', (string) ($user->_id ?? $userId)) }}" class="admin-suspend-form">
                @csrf
                <label>
                    <span>Select Base Currency</span>
                    <select name="currency" required style="width:100%; background: #0f111a; border: 1px solid rgba(255,255,255,0.1); color: #fff; padding: 10px; border-radius: 8px; font-size: 14px;">
                        <option value="INR" {{ strtoupper($user->base_currency ?? '') === 'INR' ? 'selected' : '' }}>🇮🇳 INR (Indian Rupee)</option>
                        <option value="USDT" {{ strtoupper($user->base_currency ?? '') === 'USDT' ? 'selected' : '' }}>🇺🇸 USDT (Tether)</option>
                    </select>
                </label>

                <label style="margin-top: 12px;">
                    <span>Reason for change</span>
                    <textarea name="reason" required rows="3" placeholder="Enter administrative reason for currency change..."></textarea>
                </label>

                <div class="admin-suspend-actions" style="margin-top: 16px;">
                    <button type="button" class="admin-suspend-cancel" data-close-change-modal>
                        Cancel
                    </button>

                    <button type="submit" class="admin-suspend-submit" style="background: #3b82f6; color: #fff;">
                        Save Currency
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Approve Withdrawal Address Confirmation Modal -->
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
                <div class="withdraw-address-copy-box">
                    <span>User Withdrawal Address</span>
                    <code id="withdrawModalAddress">--</code>
                    <button type="button" id="withdrawCopyAddressButton">Copy</button>
                </div>
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

@endsection
