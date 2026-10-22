@extends('admin.layouts.app')

@section('title', 'Account Deletion Requests | Kryvex Admin')

@section('content')
<section class="page-head users-page-head users-hero-head">
    <div>
        <h1>Account Deletion Requests</h1>
        <p>Review and process user requests for account closure. Approving deactivates access while preserving full financial and trade records in the database for compliance.</p>
    </div>
</section>

<!-- STATS CARDS -->
<section class="users-stats-grid users-stats-grid-v2" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
    <div class="user-stat-card" style="border-color: rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.04);">
        <span style="color: #f59e0b;">Pending Requests</span>
        <h3 style="color: #f59e0b;">{{ $stats['pending'] }}</h3>
        <small>Awaiting admin review &amp; approval</small>
    </div>

    <div class="user-stat-card" style="border-color: rgba(239, 68, 68, 0.4); background: rgba(239, 68, 68, 0.04);">
        <span style="color: #ef4444;">Approved / Deleted</span>
        <h3 style="color: #ef4444;">{{ $stats['approved'] }}</h3>
        <small>Accounts closed &amp; login restricted</small>
    </div>

    <div class="user-stat-card">
        <span style="color: #60a5fa;">Rejected Requests</span>
        <h3 style="color: #60a5fa;">{{ $stats['rejected'] }}</h3>
        <small>Requests declined by admin</small>
    </div>

    <div class="user-stat-card">
        <span>Total Records</span>
        <h3>{{ $stats['total'] }}</h3>
        <small>Total deletion history</small>
    </div>
</section>

<!-- MAIN CONTENT PANEL -->
<section class="admin-panel users-panel users-panel-v2 hover-panel">
    <div class="panel-top users-panel-top-v2" style="padding: 18px 24px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px;">
        <!-- TAB FILTERS -->
        <div style="display: flex; gap: 8px; align-items: center;">
            <a href="{{ route('admin.users.deletion-requests', ['filter' => 'pending', 'search' => $search]) }}"
               style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; transition: all 0.2s; {{ $filter === 'pending' ? 'background: #f59e0b; color: #000;' : 'background: rgba(255,255,255,0.06); color: #cbd5e1;' }}">
                Pending ({{ $stats['pending'] }})
            </a>
            <a href="{{ route('admin.users.deletion-requests', ['filter' => 'approved', 'search' => $search]) }}"
               style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; transition: all 0.2s; {{ $filter === 'approved' ? 'background: #ef4444; color: #fff;' : 'background: rgba(255,255,255,0.06); color: #cbd5e1;' }}">
                Deleted / Approved ({{ $stats['approved'] }})
            </a>
            <a href="{{ route('admin.users.deletion-requests', ['filter' => 'rejected', 'search' => $search]) }}"
               style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; transition: all 0.2s; {{ $filter === 'rejected' ? 'background: #3b82f6; color: #fff;' : 'background: rgba(255,255,255,0.06); color: #cbd5e1;' }}">
                Rejected ({{ $stats['rejected'] }})
            </a>
            <a href="{{ route('admin.users.deletion-requests', ['filter' => 'all', 'search' => $search]) }}"
               style="padding: 8px 16px; border-radius: 8px; font-size: 13px; font-weight: 700; text-decoration: none; transition: all 0.2s; {{ $filter === 'all' ? 'background: rgba(255,255,255,0.2); color: #fff;' : 'background: rgba(255,255,255,0.06); color: #cbd5e1;' }}">
                All Records
            </a>
        </div>
    </div>

    <!-- FILTER & SEARCH FORM -->
    <form method="GET" action="{{ route('admin.users.deletion-requests') }}" class="users-filter-form users-filter-form-v2" style="padding: 16px 24px; border-bottom: 1px solid rgba(255,255,255,0.06); display: flex; gap: 12px; align-items: center;">
        <input type="hidden" name="filter" value="{{ $filter }}">
        <div class="users-search-shell" style="flex: 1; position: relative;">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, email or client ID..." style="width: 100%; padding: 10px 14px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: #fff; outline: none; font-size: 13px;">
        </div>
        <button type="submit" class="primary-btn hover-lift" style="padding: 10px 18px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Search</button>
        <a class="soft-btn filter-reset hover-lift" href="{{ route('admin.users.deletion-requests', ['filter' => $filter]) }}" style="padding: 10px 18px; background: rgba(255,255,255,0.08); color: #fff; border-radius: 8px; text-decoration: none; font-size: 13px;">Clear</a>
    </form>

    <!-- USERS TABLE -->
    <div class="users-table-wrap" style="overflow-x: auto;">
        <table class="admin-users-table admin-users-table-v2" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 1px solid rgba(255,255,255,0.08); color: #94a3b8; font-size: 12px; text-transform: uppercase;">
                    <th style="padding: 14px 20px;">User Details</th>
                    <th style="padding: 14px 16px;">Client ID</th>
                    <th style="padding: 14px 16px;">Remaining Balance</th>
                    <th style="padding: 14px 16px;">Reason</th>
                    <th style="padding: 14px 16px;">Request Date</th>
                    <th style="padding: 14px 16px;">Status</th>
                    <th style="padding: 14px 20px; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($paginatedUsers as $row)
                    @php
                        $isPending = strtolower($row->deletion_status) === 'pending';
                        $isApproved = strtolower($row->deletion_status) === 'approved' || strtolower($row->status) === 'deleted';
                        $isRejected = strtolower($row->deletion_status) === 'rejected';
                    @endphp
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 13px;">
                        <td style="padding: 14px 20px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: {{ $isApproved ? '#ef4444' : ($isPending ? '#f59e0b' : '#3b82f6') }}; color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">
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
                            @if ($row->real_balance > 0.0001)
                                <span style="color: #ef4444; font-weight: 700; background: rgba(239, 68, 68, 0.15); padding: 3px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                                    ⚠️ ${{ number_format($row->real_balance, 2) }}
                                </span>
                            @else
                                <span style="color: #10b981; font-weight: 600;">$0.00</span>
                            @endif
                        </td>
                        <td style="padding: 14px 16px; max-width: 220px; color: #cbd5e1;" title="{{ $row->deletion_reason }}">
                            <div style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                {{ $row->deletion_reason }}
                            </div>
                        </td>
                        <td style="padding: 14px 16px; color: #94a3b8; font-size: 12px;">
                            {{ $row->deletion_requested_at ? \Carbon\Carbon::parse($row->deletion_requested_at)->format('d M Y, h:i A') : '-' }}
                        </td>
                        <td style="padding: 14px 16px;">
                            @if ($isPending)
                                <span style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.4); color: #f59e0b; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                    Pending Review
                                </span>
                            @elseif ($isApproved)
                                <span style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                    Deleted / Closed
                                </span>
                            @elseif ($isRejected)
                                <span style="background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.4); color: #60a5fa; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase;">
                                    Rejected
                                </span>
                            @endif
                        </td>
                        <td style="padding: 14px 20px; text-align: right;">
                            <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                <!-- VIEW USER RECORD BUTTON -->
                                <a href="{{ route('admin.users.show', $row->id) }}"
                                   title="View Complete User Record"
                                   style="padding: 6px 14px; font-size: 12px; font-weight: 600; color: #fff; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.14); border-radius: 6px; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 4px;">
                                    👁️ View
                                </a>

                                @if ($isPending)
                                    <!-- APPROVE DELETION BUTTON -->
                                    <form method="POST" action="{{ route('admin.users.approve-deletion', $row->id) }}"
                                          onsubmit="return confirm('Are you sure you want to approve deletion for {{ addslashes($row->name) }} ({{ addslashes($row->email) }})? The account will be closed immediately. Full DB records will remain preserved.');"
                                          style="margin: 0;">
                                        @csrf
                                        <button type="submit"
                                                style="padding: 6px 14px; font-size: 12px; font-weight: 700; color: #ffffff; background: #dc2626; border: none; border-radius: 6px; cursor: pointer; white-space: nowrap;">
                                            ✓ Approve
                                        </button>
                                    </form>

                                    <!-- REJECT DELETION BUTTON -->
                                    <form method="POST" action="{{ route('admin.users.reject-deletion', $row->id) }}"
                                          onsubmit="event.preventDefault(); const reason = prompt('Please enter the reason for rejecting this deletion request (optional):', 'Requirements not met or verification needed'); if (reason !== null) { document.getElementById('rejectReasonInput{{ $row->id }}').value = reason; this.submit(); }"
                                          style="margin: 0;">
                                        @csrf
                                        <input type="hidden" name="reject_reason" id="rejectReasonInput{{ $row->id }}" value="">
                                        <button type="submit"
                                                style="padding: 6px 14px; font-size: 12px; font-weight: 600; color: #cbd5e1; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; cursor: pointer; white-space: nowrap;">
                                            ✕ Reject
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="padding: 48px 20px; text-align: center;">
                            <div class="empty-state" style="color: #94a3b8;">
                                <div style="font-size: 32px; margin-bottom: 8px;">📋</div>
                                <strong style="display: block; color: #fff; font-size: 16px; margin-bottom: 4px;">No Deletion Requests Found</strong>
                                <span>There are currently no accounts matching the "{{ $filter }}" filter.</span>
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