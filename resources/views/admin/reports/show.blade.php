@extends('admin.layouts.app')

@section('title', $title)

@section('content')
<div class="reports-page">

    <div class="page-header report-detail-header">
        <div>
            <h1>{{ $title }}</h1>
            <p>Filter report data and export CSV.</p>
        </div>

        <div class="report-actions">
            <a href="{{ route('admin.reports.index') }}" class="reset-btn">Back</a>
            <a href="{{ route('admin.reports.export.csv', array_merge(['type' => $type], request()->query())) }}" class="filter-btn">
                Export CSV
            </a>
        </div>
    </div>

    <form class="wallet-filter-card" method="GET">
        <div class="filter-group">
            <label>Search</label>
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search user, email, symbol, status..."
            >
        </div>

        @if(!empty($filters['status']))
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="all" {{ request('status', 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="filled" {{ request('status') === 'filled' ? 'selected' : '' }}>Filled</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
        @endif

        @if(!empty($filters['side']))
            <div class="filter-group">
                <label>Side</label>
                <select name="side">
                    <option value="all" {{ request('side', 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="buy" {{ request('side') === 'buy' ? 'selected' : '' }}>Buy</option>
                    <option value="sell" {{ request('side') === 'sell' ? 'selected' : '' }}>Sell</option>
                </select>
            </div>
        @endif

        @if(!empty($filters['role']))
            <div class="filter-group">
                <label>Role</label>
                <select name="role">
                    <option value="all" {{ request('role', 'all') === 'all' ? 'selected' : '' }}>All</option>
                    <option value="user" {{ request('role') === 'user' ? 'selected' : '' }}>User</option>
                    <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
            </div>
        @endif

        <button class="filter-btn">Apply Filter</button>
        <a href="{{ route('admin.reports.show', $type) }}" class="reset-btn">Reset</a>
    </form>

    <div class="wallet-table-card">
        <div class="table-header">
            <h2>{{ $title }}</h2>
            <span>{{ $rows->total() }} records • Page {{ $rows->currentPage() }} / {{ $rows->lastPage() }}</span>
        </div>

        <div class="table-responsive">
            <table class="admin-table reports-detail-table">
                <thead>
                    <tr>
                        @foreach($columns as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            @foreach(array_keys($columns) as $key)
                                <td>
                                    @if(str_contains($key, 'amount') || str_contains($key, 'fee') || str_contains($key, 'pnl') || str_contains($key, 'price') || in_array($key, ['available','locked','total','margin']))
                                        ${{ is_numeric($row[$key] ?? null) ? number_format((float)$row[$key], 2) : ($row[$key] ?? '-') }}
                                    @else
                                        {{ $row[$key] ?? '-' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) }}">
                                <div class="empty-state">No data found.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>


    @include('admin.components.pagination', ['paginator' => $rows])

</div>
@endsection