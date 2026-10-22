<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AuditLogger;
use App\Http\Controllers\Controller;
use App\Support\AdminPaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class AdminCurrencyChangeController extends Controller
{
    public function index(Request $request)
    {
        $tab = strtolower(trim((string) $request->query('tab', 'pending')));
        if (!in_array($tab, ['pending', 'approved', 'rejected', 'all'], true)) {
            $tab = 'pending';
        }
        $search = strtolower(trim((string) $request->query('search')));

        $query = DB::connection('mongodb')->table('currency_change_requests');

        if ($tab !== 'all') {
            $query->where('status', $tab);
        }

        $allRequests = $query->orderByDesc('created_at')->get();

        $rows = collect($allRequests)->map(function ($rawItem) {
            $reqId = '';
            if (is_object($rawItem)) {
                $reqId = (string) ($rawItem->_id ?? $rawItem->id ?? '');
            } elseif (is_array($rawItem)) {
                $reqId = (string) ($rawItem['_id'] ?? $rawItem['id'] ?? '');
            }

            $item = (array) $rawItem;
            $userUid = (string) ($item['user_uid'] ?? '');

            // Fetch live user info
            $user = DB::connection('mongodb')->table('users')->where('uid', $userUid)->first();
            $user = (array) ($user ?? []);

            $wallet = DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->first();
            $wallet = (array) ($wallet ?? []);

            // Check positions, orders, deposits, withdraws
            $futuresPositions = DB::connection('mongodb')->table('futures_positions')->where('user_uid', $userUid)->where('status', 'OPEN')->count();
            $optionPositions = DB::connection('mongodb')->table('option_positions')->where('user_uid', $userUid)->where('status', 'OPEN')->count();
            
            $futuresOrders = DB::connection('mongodb')->table('futures_orders')->where('user_uid', $userUid)->whereIn('status', ['NEW', 'PARTIALLY_FILLED', 'OPEN'])->count();
            $optionOrders = DB::connection('mongodb')->table('option_orders')->where('user_uid', $userUid)->whereIn('status', ['NEW', 'PARTIALLY_FILLED', 'OPEN'])->count();

            $pendingDeposits = DB::connection('mongodb')->table('inr_deposit_requests')->where('user_uid', $userUid)->where('status', 'pending')->count();
            $pendingWithdrawals = DB::connection('mongodb')->table('wallet_withdrawal_requests')->where('user_uid', $userUid)->where('status', 'pending')->count();

            return (object) [
                'id' => $reqId,
                'user_uid' => $userUid,
                'user_name' => $item['user_name'] ?? ($user['name'] ?? 'User'),
                'user_email' => $item['user_email'] ?? ($user['email'] ?? ''),
                'current_currency' => $item['current_currency'] ?? 'INR',
                'requested_currency' => $item['requested_currency'] ?? 'USDT',
                'main_balance_usdt' => (float) ($item['main_balance_usdt'] ?? 0),
                'trading_balance_usdt' => (float) ($wallet['demo_available_balance'] ?? 0),
                'exchange_rate' => (float) ($item['exchange_rate'] ?? 95.00),
                'current_balance_formatted' => $item['current_balance_formatted'] ?? '',
                'receive_balance_formatted' => $item['receive_balance_formatted'] ?? '',
                'status' => $item['status'] ?? 'pending',
                'rejection_reason' => $item['rejection_reason'] ?? null,
                'created_at' => $item['created_at'] ?? null,
                'open_positions_count' => $futuresPositions + $optionPositions,
                'open_orders_count' => $futuresOrders + $optionOrders,
                'pending_deposits_count' => $pendingDeposits,
                'pending_withdrawals_count' => $pendingWithdrawals,
                'kyc_status' => strtoupper((string) ($user['kyc_status'] ?? 'UNVERIFIED')),
            ];
        });

        if ($search !== '') {
            $rows = $rows->filter(function ($row) use ($search) {
                return str_contains(strtolower($row->user_name), $search)
                    || str_contains(strtolower($row->user_email), $search)
                    || str_contains(strtolower($row->user_uid), $search);
            });
        }

        // Stats
        $allTotal = DB::connection('mongodb')->table('currency_change_requests')->get();
        $stats = (object) [
            'total' => count($allTotal),
            'pending' => collect($allTotal)->where('status', 'pending')->count(),
            'approved' => collect($allTotal)->where('status', 'approved')->count(),
            'rejected' => collect($allTotal)->where('status', 'rejected')->count(),
        ];

        $paginator = AdminPaginator::paginate($rows, $request, 15);

        return view('admin.currency-requests.index', [
            'requests' => $paginator,
            'tab' => $tab,
            'search' => $search,
            'stats' => $stats,
        ]);
    }

    public function approve(Request $request, string $id)
    {
        $admin = session('admin_user');

        $objectId = null;
        if (class_exists('\MongoDB\BSON\ObjectId') && strlen($id) === 24 && ctype_xdigit($id)) {
            try {
                $objectId = new \MongoDB\BSON\ObjectId($id);
            } catch (\Throwable $e) {
                $objectId = null;
            }
        }

        $changeReq = DB::connection('mongodb')->table('currency_change_requests')
            ->where(function($q) use ($id, $objectId) {
                $q->where('_id', $id);
                if ($objectId) {
                    $q->orWhere('_id', $objectId);
                }
            })->first();

        if (!$changeReq) {
            return response()->json(['success' => false, 'message' => 'Currency request not found.'], 404);
        }

        $changeReq = (array) $changeReq;
        if (($changeReq['status'] ?? '') !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Request is no longer pending.'], 422);
        }

        $userUid = (string) ($changeReq['user_uid'] ?? '');
        $newCurrency = strtoupper((string) ($changeReq['requested_currency'] ?? 'USDT'));

        // Convert Wallet Balance if needed
        $wallet = DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->first();
        if ($wallet) {
            $wallet = (array) $wallet;
            $currentMainUsdt = (float) ($wallet['demo_main_balance'] ?? 0);

            $settingDoc = DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
            $settingArr = (array) ($settingDoc ?? []);
            $baseRate = (float) ($settingArr['usdt_inr_rate'] ?? 95.00);
            $buyRate = (float) ($settingArr['usdt_inr_buy_rate'] ?? $baseRate);

            if ($newCurrency === 'USDT' && $buyRate > 0) {
                // User converted INR balance into USDT at Buy Rate
                $inrValue = $currentMainUsdt * $baseRate;
                $newMainUsdt = round($inrValue / $buyRate, 8);
                DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->update([
                    'demo_main_balance' => $newMainUsdt,
                    'updated_at' => now(),
                ]);
            }
        }

        // DB Update User Base Currency
        DB::connection('mongodb')->table('users')->where('uid', $userUid)->update([
            'base_currency' => $newCurrency,
            'base_currency_updated_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection('mongodb')->table('currency_change_requests')
            ->where(function($q) use ($id, $objectId) {
                $q->where('_id', $id);
                if ($objectId) {
                    $q->orWhere('_id', $objectId);
                }
            })->update([
                'status' => 'approved',
                'approved_by' => $admin['email'] ?? 'admin',
                'approved_at' => now(),
                'updated_at' => now(),
            ]);

        // Create notification for user
        DB::connection('mongodb')->table('user_notifications')->insert([
            'user_uid' => $userUid,
            'title' => 'Currency Change Approved',
            'message' => "Your request to change base currency to {$newCurrency} has been approved by admin.",
            'type' => 'success',
            'is_read' => false,
            'created_at' => now(),
        ]);

        AuditLogger::log('approve', 'currency_change', "Approved base currency change for user $userUid to $newCurrency");

        return response()->json([
            'success' => true,
            'message' => "Currency change approved successfully. User base currency updated to {$newCurrency}.",
        ]);
    }

    public function reject(Request $request, string $id)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $admin = session('admin_user');

        $objectId = null;
        if (class_exists('\MongoDB\BSON\ObjectId') && strlen($id) === 24 && ctype_xdigit($id)) {
            try {
                $objectId = new \MongoDB\BSON\ObjectId($id);
            } catch (\Throwable $e) {
                $objectId = null;
            }
        }

        $changeReq = DB::connection('mongodb')->table('currency_change_requests')
            ->where(function($q) use ($id, $objectId) {
                $q->where('_id', $id);
                if ($objectId) {
                    $q->orWhere('_id', $objectId);
                }
            })->first();

        if (!$changeReq) {
            return response()->json(['success' => false, 'message' => 'Currency request not found.'], 404);
        }

        $changeReq = (array) $changeReq;
        if (($changeReq['status'] ?? '') !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Request is no longer pending.'], 422);
        }

        $userUid = (string) ($changeReq['user_uid'] ?? '');
        $reason = $validated['reason'];

        DB::connection('mongodb')->table('currency_change_requests')
            ->where(function($q) use ($id, $objectId) {
                $q->where('_id', $id);
                if ($objectId) {
                    $q->orWhere('_id', $objectId);
                }
            })->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'rejected_by' => $admin['email'] ?? 'admin',
                'rejected_at' => now(),
                'updated_at' => now(),
            ]);

        // Create notification for user
        DB::connection('mongodb')->table('user_notifications')->insert([
            'user_uid' => $userUid,
            'title' => 'Currency Change Rejected',
            'message' => "Your request to change base currency was rejected. Reason: {$reason}",
            'type' => 'error',
            'is_read' => false,
            'created_at' => now(),
        ]);

        AuditLogger::log('reject', 'currency_change', "Rejected base currency change for user $userUid. Reason: $reason");

        return response()->json([
            'success' => true,
            'message' => "Currency change request rejected.",
        ]);
    }
}
