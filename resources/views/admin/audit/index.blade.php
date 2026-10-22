@extends('admin.layouts.app')

@section('title', 'Audit Logs')

@section('content')
<div class="audit-page">

    <div class="page-header report-detail-header">
        <div>
            <h1>Audit Logs</h1>
            <p>Track admin actions with metadata, IP address and exportable logs.</p>
        </div>

        <div class="report-actions">
            <a
                href="{{ route('admin.audit.export.csv', request()->query()) }}"
                class="filter-btn"
            >
                Export CSV
            </a>
        </div>
    </div>

    <form class="wallet-filter-card" method="GET">
        <div class="filter-group">
            <label>Search</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="Admin, action, message, IP...">
        </div>

        <div class="filter-group">
            <label>Module</label>
            <select name="module">
                <option value="all" {{ $module === 'all' ? 'selected' : '' }}>All</option>
                <option value="auth" {{ $module === 'auth' ? 'selected' : '' }}>Auth</option>
                <option value="users" {{ $module === 'users' ? 'selected' : '' }}>Users</option>
                <option value="wallet" {{ $module === 'wallet' ? 'selected' : '' }}>Wallet</option>
                <option value="markets" {{ $module === 'markets' ? 'selected' : '' }}>Markets</option>
                <option value="settings" {{ $module === 'settings' ? 'selected' : '' }}>Settings</option>
                <option value="risk" {{ $module === 'risk' ? 'selected' : '' }}>Risk</option>
            </select>
        </div>

        <div class="filter-group">
            <label>Action</label>
            <select name="action">
                <option value="all" {{ $action === 'all' ? 'selected' : '' }}>All</option>
                <option value="login" {{ $action === 'login' ? 'selected' : '' }}>Login</option>
                <option value="logout" {{ $action === 'logout' ? 'selected' : '' }}>Logout</option>
                <option value="create" {{ $action === 'create' ? 'selected' : '' }}>Create</option>
                <option value="update" {{ $action === 'update' ? 'selected' : '' }}>Update</option>
                <option value="approve" {{ $action === 'approve' ? 'selected' : '' }}>Approve</option>
                <option value="reject" {{ $action === 'reject' ? 'selected' : '' }}>Reject</option>
                <option value="block" {{ $action === 'block' ? 'selected' : '' }}>Block</option>
                <option value="suspend" {{ $action === 'suspend' ? 'selected' : '' }}>Suspend</option>
            </select>
        </div>

        <button class="filter-btn">Filter</button>
        <a href="{{ route('admin.audit.index') }}" class="reset-btn">Reset</a>
    </form>

    <div class="wallet-table-card">
        <div class="table-header">
            <h2>Admin Activity</h2>
            <span>{{ $logs->total() }} records • Page {{ $logs->currentPage() }} / {{ $logs->lastPage() }}</span>
        </div>

        <div class="table-responsive">
            <table class="admin-table audit-table">
                <thead>
                    <tr>
                        <th>Admin</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Message</th>
                        <th>IP</th>
                        <th>Date</th>
                        <th>Details</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($logs as $log)
                        @php
                            $metaJson = json_encode($log->meta ?? [], JSON_PRETTY_PRINT);
                        @endphp

                        <tr>
                            <td>
                                <strong>{{ $log->admin_email ?? '-' }}</strong>
                                <small>{{ $log->admin_id ?? 'system' }}</small>
                            </td>

                            <td>
                                <span class="status-badge approved">
                                    {{ ucfirst($log->module ?? '-') }}
                                </span>
                            </td>

                            <td>
                                <span class="audit-action {{ $log->action ?? 'update' }}">
                                    {{ ucfirst($log->action ?? '-') }}
                                </span>
                            </td>

                            <td>{{ $log->message ?? '-' }}</td>

                            <td>{{ $log->ip_address ?? '-' }}</td>

                            <td>
                                {{ isset($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A') : '-' }}
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="audit-detail-btn"
                                    data-admin="{{ $log->admin_email ?? '-' }}"
                                    data-module="{{ $log->module ?? '-' }}"
                                    data-action="{{ $log->action ?? '-' }}"
                                    data-message="{{ $log->message ?? '-' }}"
                                    data-ip="{{ $log->ip_address ?? '-' }}"
                                    data-agent="{{ $log->user_agent ?? '-' }}"
                                    data-date="{{ isset($log->created_at) ? \Carbon\Carbon::parse($log->created_at)->format('d M Y, h:i A') : '-' }}"
                                    data-meta="{{ e($metaJson) }}"
                                >
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">No audit logs found yet.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

    @include('admin.components.pagination', ['paginator' => $logs])

</div>

<div class="audit-modal-overlay" id="auditModalOverlay">
    <div class="audit-modal">
        <div class="audit-modal-header">
            <div>
                <h2>Audit Details</h2>
                <p>Full admin action information.</p>
            </div>
            <button type="button" id="closeAuditModal">×</button>
        </div>

        <div class="audit-modal-body">
            <div class="audit-detail-grid">
                <div>
                    <span>Admin</span>
                    <strong id="auditAdmin">-</strong>
                </div>

                <div>
                    <span>Module</span>
                    <strong id="auditModule">-</strong>
                </div>

                <div>
                    <span>Action</span>
                    <strong id="auditAction">-</strong>
                </div>

                <div>
                    <span>IP Address</span>
                    <strong id="auditIp">-</strong>
                </div>

                <div>
                    <span>Date</span>
                    <strong id="auditDate">-</strong>
                </div>
            </div>

            <div class="audit-detail-section">
                <span>Message</span>
                <p id="auditMessage">-</p>
            </div>

            <div class="audit-detail-section">
                <span>User Agent</span>
                <p id="auditAgent">-</p>
            </div>

            <div class="audit-detail-section">
                <span>Meta Data</span>
                <pre id="auditMeta">{}</pre>
            </div>
        </div>
    </div>

</div>
@endsection