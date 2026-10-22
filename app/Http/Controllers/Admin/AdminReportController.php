<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminReportController extends Controller
{
    public function index(Request $request)
    {
        $stats = $this->reportStats();

        return view('admin.reports.index', compact('stats'));
    }

    public function show(string $type, Request $request)
    {
        [$title, $rows, $columns, $filters] = $this->reportData($type, $request);
        $rows = AdminPaginator::paginate($rows, $request, 10);

        return view('admin.reports.show', compact(
            'type',
            'title',
            'rows',
            'columns',
            'filters'
        ));
    }

    public function exportCsv(string $type, Request $request): StreamedResponse
    {
        [$title, $rows, $columns] = $this->reportData($type, $request);

        $filename = strtolower(str_replace(' ', '_', $title)) . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_values($columns));

            foreach ($rows as $row) {
                $line = [];

                foreach (array_keys($columns) as $key) {
                    $line[] = $row[$key] ?? '';
                }

                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    private function reportStats(): array
    {
        $users = DB::connection('mongodb')->table('users')->get();
        $wallets = DB::connection('mongodb')->table('wallets')->get();
        $ledger = DB::connection('mongodb')->table('wallet_ledgers')->get();
        $withdrawals = DB::connection('mongodb')->table('wallet_withdrawal_requests')->get();
        $orders = DB::connection('mongodb')->table('futures_orders')->get();
        $positions = DB::connection('mongodb')->table('futures_positions')->get();

        $totalAvailable = $wallets->sum(fn ($w) => (float)($w->demo_available_balance ?? 0));
        $totalLocked = $wallets->sum(fn ($w) => (float)($w->demo_locked_balance ?? 0));

        $deposits = $ledger->filter(function ($item) {
            $type = strtolower((string)($item->type ?? ''));
            return str_contains($type, 'deposit') ||
                str_contains($type, 'demo_credit') ||
                str_contains($type, 'fund');
        });

        $spreadRevenues = DB::connection('mongodb')->table('admin_revenues')->get();
        $totalSpreadRevenue = $spreadRevenues->sum(fn ($sr) => (float)($sr->amount ?? 0));

        $fees = $orders->sum(fn ($o) => (float)($o->fee_charged ?? $o->estimated_fee ?? 0))
            + $positions->sum(fn ($p) => (float)($p->closing_fee ?? 0));

        $realizedPnl = $positions->sum(fn ($p) => (float)($p->realized_pnl ?? 0));

        return [
            'total_users' => $users->count(),
            'wallet_total' => round($totalAvailable + $totalLocked, 2),
            'total_deposits' => round($deposits->sum(fn ($d) => (float)($d->amount ?? 0)), 2),
            'withdrawal_requests' => $withdrawals->count(),
            'total_orders' => $orders->count(),
            'open_positions' => $positions->where('status', 'open')->count(),
            'closed_positions' => $positions->where('status', 'closed')->count(),
            'fees_revenue' => round($fees, 2),
            'spread_revenue' => round($totalSpreadRevenue, 2),
            'total_revenue' => round($fees + $totalSpreadRevenue, 2),
            'realized_pnl' => round($realizedPnl, 2),
        ];
    }

    private function reportData(string $type, Request $request): array
    {
        return match ($type) {
            'users' => $this->usersReport($request),
            'wallet' => $this->walletReport($request),
            'deposits' => $this->depositsReport($request),
            'withdrawals' => $this->withdrawalsReport($request),
            'orders' => $this->ordersReport($request),
            'positions' => $this->positionsReport($request),
            'fees' => $this->feesReport($request),
            'pnl' => $this->pnlReport($request),
            default => abort(404),
        };
    }

    private function usersMap()
    {
        return DB::connection('mongodb')
            ->table('users')
            ->get()
            ->keyBy(function ($user) {
                return (string)($user->uid ?? $user->_id ?? '');
            });
    }

    private function userName($uid, $users): string
    {
        $user = $users->get((string)$uid);
        return $user->name ?? 'Unknown User';
    }

    private function userEmail($uid, $users): string
    {
        $user = $users->get((string)$uid);
        return $user->email ?? '-';
    }

    private function searchMatch(array $row, string $search): bool
    {
        if ($search === '') {
            return true;
        }

        return str_contains(strtolower(implode(' ', $row)), strtolower($search));
    }

    private function date($date): string
    {
        if (!$date) {
            return '-';
        }

        return \Carbon\Carbon::parse($date)->format('d M Y, h:i A');
    }

        private function usersReport(Request $request): array
    {
        $search = trim((string)$request->query('search'));
        $status = $request->query('status', 'all');
        $role = $request->query('role', 'all');

        $rows = DB::connection('mongodb')->table('users')->get()
            ->map(fn ($u) => [
                'name' => $u->name ?? 'Unknown',
                'email' => $u->email ?? '-',
                'status' => $u->status ?? 'active',
                'role' => $u->role ?? 'user',
                'created_at' => $this->date($u->created_at ?? null),
            ])
            ->filter(function ($row) use ($search, $status, $role) {
                if ($status !== 'all' && $row['status'] !== $status) return false;
                if ($role !== 'all' && $row['role'] !== $role) return false;
                return $this->searchMatch($row, $search);
            })
            ->values();

        return ['Users Report', $rows, [
            'name' => 'User Name',
            'email' => 'Email',
            'status' => 'Status',
            'role' => 'Role',
            'created_at' => 'Created At',
        ], ['status' => true, 'role' => true]];
    }

    private function walletReport(Request $request): array
    {
        $search = trim((string)$request->query('search'));
        $users = $this->usersMap();

        $rows = DB::connection('mongodb')->table('wallets')->get()
            ->map(fn ($w) => [
                'name' => $this->userName($w->user_uid ?? '', $users),
                'email' => $this->userEmail($w->user_uid ?? '', $users),
                'available' => $w->demo_available_balance ?? 0,
                'locked' => $w->demo_locked_balance ?? 0,
                'total' => (float)($w->demo_available_balance ?? 0) + (float)($w->demo_locked_balance ?? 0),
                'updated_at' => $this->date($w->updated_at ?? null),
            ])
            ->filter(fn ($row) => $this->searchMatch($row, $search))
            ->values();

        return ['Wallet Report', $rows, [
            'name' => 'User Name',
            'email' => 'Email',
            'available' => 'Available',
            'locked' => 'Locked',
            'total' => 'Total',
            'updated_at' => 'Updated At',
        ], []];
    }

    private function depositsReport(Request $request): array
    {
        $search = trim((string)$request->query('search'));
        $status = $request->query('status', 'all');
        $users = $this->usersMap();

        $rows = DB::connection('mongodb')->table('wallet_ledgers')->get()
            ->filter(function ($l) {
                $type = strtolower((string)($l->type ?? ''));
                return str_contains($type, 'deposit') ||
                    str_contains($type, 'demo_credit') ||
                    str_contains($type, 'fund');
            })
            ->map(fn ($l) => [
                'name' => $this->userName($l->user_uid ?? '', $users),
                'email' => $this->userEmail($l->user_uid ?? '', $users),
                'type' => $l->type ?? '-',
                'amount' => $l->amount ?? 0,
                'direction' => $l->direction ?? 'credit',
                'status' => $l->status ?? 'completed',
                'reference' => $l->reference ?? '-',
                'created_at' => $this->date($l->created_at ?? null),
            ])
            ->filter(function ($row) use ($search, $status) {
                if ($status !== 'all' && $row['status'] !== $status) return false;
                return $this->searchMatch($row, $search);
            })
            ->values();

        return ['Deposits Report', $rows, [
            'name' => 'User Name',
            'email' => 'Email',
            'type' => 'Type',
            'amount' => 'Amount',
            'direction' => 'Direction',
            'status' => 'Status',
            'reference' => 'Reference',
            'created_at' => 'Created At',
        ], ['status' => true]];
    }

    private function withdrawalsReport(Request $request): array
    {
        $search = trim((string)$request->query('search'));
        $status = $request->query('status', 'all');
        $users = $this->usersMap();

        $rows = DB::connection('mongodb')->table('wallet_withdrawal_requests')->get()
            ->map(fn ($w) => [
                'name' => $this->userName($w->user_uid ?? '', $users),
                'email' => $this->userEmail($w->user_uid ?? '', $users),
                'amount' => $w->amount ?? 0,
                'method' => $w->method ?? '-',
                'status' => $w->status ?? '-',
                'reference' => $w->reference ?? '-',
                'admin_note' => $w->admin_note ?? '-',
                'created_at' => $this->date($w->created_at ?? null),
            ])
            ->filter(function ($row) use ($search, $status) {
                if ($status !== 'all' && $row['status'] !== $status) return false;
                return $this->searchMatch($row, $search);
            })
            ->values();

        return ['Withdrawals Report', $rows, [
            'name' => 'User Name',
            'email' => 'Email',
            'amount' => 'Amount',
            'method' => 'Method',
            'status' => 'Status',
            'reference' => 'Reference',
            'admin_note' => 'Admin Note',
            'created_at' => 'Created At',
        ], ['status' => true]];
    }

        private function ordersReport(Request $request): array
    {
        $search = trim((string)$request->query('search'));
        $status = $request->query('status', 'all');
        $side = $request->query('side', 'all');
        $users = $this->usersMap();

        $rows = DB::connection('mongodb')->table('futures_orders')->get()
            ->map(fn ($o) => [
                'name' => $this->userName($o->user_uid ?? '', $users),
                'email' => $this->userEmail($o->user_uid ?? '', $users),
                'symbol' => $o->symbol ?? '-',
                'side' => $o->side ?? '-',
                'order_type' => $o->order_type ?? '-',
                'qty' => $o->estimated_quantity ?? '-',
                'amount' => $o->notional_value ?? 0,
                'fee' => $o->fee_charged ?? 0,
                'status' => $o->status ?? '-',
                'created_at' => $this->date($o->created_at ?? null),
            ])
            ->filter(function ($row) use ($search, $status, $side) {
                if ($status !== 'all' && $row['status'] !== $status) return false;
                if ($side !== 'all' && $row['side'] !== $side) return false;
                return $this->searchMatch($row, $search);
            })
            ->values();

        return ['Orders Report', $rows, [
            'name' => 'User Name',
            'email' => 'Email',
            'symbol' => 'Symbol',
            'side' => 'Side',
            'order_type' => 'Type',
            'qty' => 'Qty',
            'amount' => 'Amount',
            'fee' => 'Fee',
            'status' => 'Status',
            'created_at' => 'Created At',
        ], ['status' => true, 'side' => true]];
    }

    private function positionsReport(Request $request): array
    {
        $search = trim((string)$request->query('search'));
        $status = $request->query('status', 'all');
        $side = $request->query('side', 'all');
        $users = $this->usersMap();

        $rows = DB::connection('mongodb')->table('futures_positions')->get()
            ->map(fn ($p) => [
                'name' => $this->userName($p->user_uid ?? '', $users),
                'email' => $this->userEmail($p->user_uid ?? '', $users),
                'symbol' => $p->symbol ?? '-',
                'side' => $p->side ?? '-',
                'qty' => $p->quantity ?? '-',
                'margin' => $p->margin ?? 0,
                'pnl' => $p->realized_pnl ?? 0,
                'status' => $p->status ?? '-',
                'opened_at' => $this->date($p->opened_at ?? null),
                'closed_at' => $this->date($p->closed_at ?? null),
            ])
            ->filter(function ($row) use ($search, $status, $side) {
                if ($status !== 'all' && $row['status'] !== $status) return false;
                if ($side !== 'all' && $row['side'] !== $side) return false;
                return $this->searchMatch($row, $search);
            })
            ->values();

        return ['Positions Report', $rows, [
            'name' => 'User Name',
            'email' => 'Email',
            'symbol' => 'Symbol',
            'side' => 'Side',
            'qty' => 'Qty',
            'margin' => 'Margin',
            'pnl' => 'P&L',
            'status' => 'Status',
            'opened_at' => 'Opened At',
            'closed_at' => 'Closed At',
        ], ['status' => true, 'side' => true]];
    }

    private function feesReport(Request $request): array
    {
        $search = trim((string)$request->query('search'));
        $users = $this->usersMap();

        $orders = DB::connection('mongodb')->table('futures_orders')->get()
            ->map(fn ($o) => [
                'name' => $this->userName($o->user_uid ?? '', $users),
                'email' => $this->userEmail($o->user_uid ?? '', $users),
                'source' => 'Order Fee',
                'symbol' => $o->symbol ?? '-',
                'fee' => $o->fee_charged ?? $o->estimated_fee ?? 0,
                'created_at' => $this->date($o->created_at ?? null),
            ]);

        $positions = DB::connection('mongodb')->table('futures_positions')->get()
            ->map(fn ($p) => [
                'name' => $this->userName($p->user_uid ?? '', $users),
                'email' => $this->userEmail($p->user_uid ?? '', $users),
                'source' => 'Closing Fee',
                'symbol' => $p->symbol ?? '-',
                'fee' => $p->closing_fee ?? 0,
                'created_at' => $this->date($p->closed_at ?? $p->created_at ?? null),
            ]);

        $rows = $orders->merge($positions)
            ->filter(fn ($row) => $this->searchMatch($row, $search))
            ->values();

        return ['Fees Revenue Report', $rows, [
            'name' => 'User Name',
            'email' => 'Email',
            'source' => 'Source',
            'symbol' => 'Symbol',
            'fee' => 'Fee',
            'created_at' => 'Date',
        ], []];
    }

    private function pnlReport(Request $request): array
    {
        $search = trim((string)$request->query('search'));
        $status = $request->query('status', 'all');
        $side = $request->query('side', 'all');
        $users = $this->usersMap();

        $rows = DB::connection('mongodb')->table('futures_positions')->get()
            ->map(fn ($p) => [
                'name' => $this->userName($p->user_uid ?? '', $users),
                'email' => $this->userEmail($p->user_uid ?? '', $users),
                'symbol' => $p->symbol ?? '-',
                'side' => $p->side ?? '-',
                'entry_price' => $p->entry_price ?? 0,
                'exit_price' => $p->exit_price ?? 0,
                'realized_pnl' => $p->realized_pnl ?? 0,
                'status' => $p->status ?? '-',
                'closed_at' => $this->date($p->closed_at ?? null),
            ])
            ->filter(function ($row) use ($search, $status, $side) {
                if ($status !== 'all' && $row['status'] !== $status) return false;
                if ($side !== 'all' && $row['side'] !== $side) return false;
                return $this->searchMatch($row, $search);
            })
            ->values();

        return ['P&L Report', $rows, [
            'name' => 'User Name',
            'email' => 'Email',
            'symbol' => 'Symbol',
            'side' => 'Side',
            'entry_price' => 'Entry Price',
            'exit_price' => 'Exit Price',
            'realized_pnl' => 'Realized P&L',
            'status' => 'Status',
            'closed_at' => 'Closed At',
        ], ['status' => true, 'side' => true]];
    }
}