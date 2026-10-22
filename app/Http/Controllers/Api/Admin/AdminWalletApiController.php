<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWalletApiController extends Controller
{
    public function overview()
    {
        $wallets = DB::connection('mongodb')->table('wallets')->get();

        $available = 0;
        $locked = 0;

        foreach ($wallets as $wallet) {
            $available += (float) ($wallet->demo_available_balance ?? 0);
            $locked += (float) ($wallet->demo_locked_balance ?? 0);
        }

        $pendingWithdrawals = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Admin wallet overview fetched.',
            'data' => [
                'total_balance' => round($available + $locked, 4),
                'available_balance' => round($available, 4),
                'locked_balance' => round($locked, 4),
                'pending_withdrawals' => $pendingWithdrawals,
            ],
        ]);
    }

    public function withdrawals(Request $request)
    {
        $status = $request->query('status', 'all');

        $query = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal requests fetched.',
            'data' => $query->limit(200)->get(),
        ]);
    }

    public function ledger(Request $request)
    {
        $query = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->orderByDesc('created_at');

        if ($request->query('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->query('direction')) {
            $query->where('direction', $request->query('direction'));
        }

        return response()->json([
            'success' => true,
            'message' => 'Wallet ledger fetched.',
            'data' => $query->limit(300)->get(),
        ]);
    }
}