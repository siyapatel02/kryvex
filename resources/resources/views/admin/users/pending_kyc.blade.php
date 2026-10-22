@extends('admin.layouts.app')

@section('title', 'Pending KYC Users | Kryvex Admin')

@section('content')
<section class="page-head users-page-head users-hero-head">
    <div>
        <h1>Pending KYC Verification Users</h1>
        <p>List of registered users who have not completed identity verification (KYC) yet. Send reminder emails to complete KYC.</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <form method="POST" action="{{ route('admin.users.send-kyc-reminder', 'all') }}" onsubmit="return confirm('Send KYC completion reminder email to ALL {{ $stats['pending_kyc'] }} unverified users?');">
            @csrf
            <button class="primary-btn hover-lift" type="submit" style="background: #3b82f6; color: #ffffff; font-weight: 800; padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                ✉️ Send KYC Reminder Email to All ({{ $stats['pending_kyc'] }})
            </button>
        </form>
    </div>
</section>

<!-- STATS CARDS -->
<section class="users-stats-grid users-stats-grid-v2" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
    <div class="user-stat-card">
        <span>Total Registered Users</span>
        <h3>{{ $stats['total_users'] }}</h3>
        <small>Total platform registrations</small>
    </div>

    <div class="user-stat-card" style="border-color: rgba(59, 130, 246, 0.4); background: rgba(59, 130, 246, 0.04);">
        <span style="color: #60a5fa;">Pending KYC Verification</span>
        <h3 style="color: #60a5fa;">{{ $stats['pending_kyc'] }}</h3>
        <small>Registered but KYC not approved</small>
    </div>

    <div class="user-stat-card">
        <span>Verified Accounts</span>
        <h3 style="color: #10b981;">{{ $stats['verified'] }}</h3>
        <small>Users with approved KYC</small>
    </div>

    <div class="user-stat-card">
        <span>KYC Completion Rate</span>
        <h3>{{ $stats['kyc_rate'] }}%</h3>
        <small>Percentage of registered users verified</small>
    </div>
</section>

<!-- MAIN CONTENT PANEL -->
<section class="admin-panel users-panel users-panel-v2 hover-panel">
    <div class="panel-top users-panel-top-v2" style="padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between;">
        <div>
            <h2 style="margin: 0; color: #fff; font-size: 18px; font-weight: 700;">Registered Users Awaiting KYC</h2>
            <p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 13px;">Unverified users who need to complete DigiLocker / Selfie verification.</p>
        </div>
    </div>

    <!-- FILTER & SEARCH FORM -->
    <form method="GET" action="{{ route('admin.users.pending-kyc') }}" class="users-filter-form users-filter-form-v2" style="padding: 16px 24px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; gap: 12px; align-items: center;">
        <div class="users-search-shell" style="flex: 1; position: relative;">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, email or client ID..." style="width: 100%; padding: 10px 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; outline: none; font-size: 13px;">
        </div>
        <button type="submit" class="primary-btn hover-lift" style="padding: 10px 18px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Search</button>
        <a class="soft-btn filter-reset hover-lift" href="{{ route('admin.users.pending-kyc') }}" style="padding: 10px 18px; background: rgba(255,255,255,0.08); color: #fff; border-radius: 8px; text-decoration: none; font-size: 13px;">Clear</a>
    </form>

    <!-- USERS TABLE -->
    <div class="users-table-wrap" style="overflow-x: auto;">
        <table class="admin-users-table admin-users-table-v2" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.08); color: #94a3b8; font-size: 12px; text-transform: uppercase;">
                    <th style="padding: 14px 20px;">User Details</th>
                    <th style="padding: 14px 16px;">Client ID</th>
                    <th style="padding: 14px 16px;">KYC Status</th>
                    <th style="padding: 14px 16px;">Registered Date</th>
                    <th style="padding: 14px 16px;">Time Pending</th>
                    <th style="padding: 14px 20px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($paginatedUsers as $row)
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 13px;">
                        <td style="padding: 14px 20px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
                                    {{ strtoupper(substr($row->name, 0, 1)) }}
                                </div>
                                <div>
                                    <strong style="color: #fff; display: block;">{{ $row->name }}</strong>
                                    <small style="color: #94a3b8;">{{ $row->email }}</small>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 14px 16px; color: #cbd5e1; font-family: monospace;">
                            {{ $row->client_id }}
                        </td>
                        <td style="padding: 14px 16px;">
                            <span style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); color: #f59e0b; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                {{ str_replace('_', ' ', $row->kyc_status) }}
                            </span>
                        </td>
                        <td style="padding: 14px 16px; color: #cbd5e1;">
                            {{ $row->joined_at ? \Carbon\Carbon::parse($row->joined_at)->format('d M Y, h:i A') : '-' }}
                        </td>
                        <td style="padding: 14px 16px;">
                            <span style="color: #60a5fa; font-weight: 600;">
                                {{ $row->days_unverified }} day{{ $row->days_unverified == 1 ? '' : 's' }} ago
                            </span>
                        </td>
                        <td style="padding: 14px 20px; text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                                <form method="POST" action="{{ route('admin.users.send-kyc-reminder', $row->user_uid) }}" style="margin: 0;">
                                    @csrf
                                    <button type="submit" style="padding: 6px 14px; font-size: 12px; font-weight: 700; color: #ffffff; background: #3b82f6; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                                        ✉️ Send KYC Mail
                                    </button>
                                </form>
                                <a href="{{ route('admin.users.show', $row->id) }}" style="padding: 6px 14px; font-size: 12px; font-weight: 600; color: #fff; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.12); border-radius: 6px; text-decoration: none; white-space: nowrap;">
                                    View User
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="padding: 40px; text-align: center;">
                            <div class="empty-state" style="color: #94a3b8;">
                                <strong style="display: block; color: #fff; font-size: 16px; margin-bottom: 4px;">No Pending KYC Users Found</strong>
                                <span>All registered users have either completed KYC or no users match your search criteria.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination-wrap" style="padding: 16px 24px; border-top: 1px solid rgba(255,255,255,0.08);">
        @include('admin.components.pagination', ['paginator' => $paginatedUsers])
    </div>
</section>
@endsection
