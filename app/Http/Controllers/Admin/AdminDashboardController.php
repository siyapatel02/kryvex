<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = Cache::remember('admin_dashboard_stats', 30, function () {
            return [
                'total_users' => $this->countCollection('users'),
                'active_users' => $this->countWhere('users', 'status', 'active'),
                'pending_kyc' => $this->countWhere('users', 'kyc_status', 'pending'),
                'verified_users' => $this->countWhere('users', 'email_verified', true),
                'futures_orders' => $this->countCollection('futures_orders'),
                'options_orders' => $this->countCollection('option_orders'),
                'wallets' => $this->countCollection('wallets'),
                'withdrawals' => $this->countCollection('withdrawal_requests'),
            ];
        });

        $recentUsers = DB::connection('mongodb')
            ->table('users')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $auditLogs = DB::connection('mongodb')
            ->table('admin_audit_logs')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'auditLogs' => $auditLogs,
        ]);
    }

    private function countCollection(string $collection): int
    {
        try {
            return DB::connection('mongodb')->table($collection)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function countWhere(string $collection, string $field, mixed $value): int
    {
        try {
            return DB::connection('mongodb')
                ->table($collection)
                ->where($field, $value)
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}