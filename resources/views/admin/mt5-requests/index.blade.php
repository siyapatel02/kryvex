@extends('admin.layouts.app')

@section('title', 'MT5 Requests - Kryvex Admin')

@section('content')
<div class="admin-page">
    <section class="page-head">
        <div>
            <p class="eyebrow">Broker Management</p>
            <h1>MetaTrader 5 Requests</h1>
            <span>Review pending MT5 plan requests and assign login credentials.</span>
        </div>
    </section>

    <div class="admin-panel hover-panel">
        <div style="overflow-x: auto;">
            <table class="admin-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.08); text-align: left; color: #64748b; font-size: 11px; text-transform: uppercase;">
                        <th style="padding: 12px;">User</th>
                        <th style="padding: 12px;">Requested Plan</th>
                        <th style="padding: 12px;">Req. Min Balance</th>
                        <th style="padding: 12px;">Status</th>
                        <th style="padding: 12px;">Requested At</th>
                        <th style="padding: 12px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($requests as $req)
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); font-size: 13px;">
                            <td style="padding: 12px;">
                                <strong style="color: #fff; display: block;">{{ $req['user_name'] }}</strong>
                                <span style="font-size: 11px; color: #64748b;">{{ $req['user_email'] }}</span>
                            </td>
                            <td style="padding: 12px;">
                                <span class="pill" style="background: rgba(246, 201, 63, 0.15); color: #f6c93f; padding: 2px 8px; border-radius: 6px; font-weight: 700;">{{ $req['plan_name'] }}</span>
                            </td>
                            <td style="padding: 12px; font-family: monospace;">
                                ${{ number_format((float) $req['minimum_balance'], 2) }}
                            </td>
                            <td style="padding: 12px;">
                                @if($req['status'] === 'approved')
                                    <span style="color: #22c55e; font-weight: 700;">Approved</span>
                                @elseif($req['status'] === 'pending')
                                    <span style="color: #facc15; font-weight: 700;">Pending</span>
                                @else
                                    <span style="color: #94a3b8;">{{ ucfirst($req['status']) }}</span>
                                @endif
                            </td>
                            <td style="padding: 12px; font-size: 12px; color: #94a3b8;">
                                {{ $req['requested_at'] ?? '—' }}
                            </td>
                            <td style="padding: 12px;">
                                <a href="{{ route('admin.users.show', $req['user_uid']) }}" class="soft-btn" style="padding: 4px 10px; font-size: 12px; font-weight: 700; background: rgba(139, 92, 246, 0.15); color: #a78bfa; border-radius: 6px; text-decoration: none;">
                                    Manage Credentials →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 24px; text-align: center; color: #64748b;">
                                No MT5 requests found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
