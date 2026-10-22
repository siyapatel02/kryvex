<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\AuditLogger;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Services\AccountStatusService;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $status = $request->query('status', 'all') ?: 'all';

        $baseActiveQuery = fn ($q) => $q->where(function ($sub) {
            $sub->whereNull('status')->orWhere('status', '!=', 'deleted');
        })->where(function ($sub) {
            $sub->whereNull('deletion_status')->orWhereNotIn('deletion_status', ['approved', 'pending']);
        });

        $totalUsers = User::where($baseActiveQuery)->count();
        $activeUsers = User::where($baseActiveQuery)->where(function ($q) {
            $q->where('status', 'active')->orWhereNull('status');
        })->count();
        $suspendedUsers = User::where($baseActiveQuery)->where('status', 'suspended')->count();
        $blockedUsers = User::where($baseActiveQuery)->where('status', 'blocked')->count();

        $totalWalletBalance = round(
            (float) (
                DB::connection('mongodb')->table('wallets')->sum('demo_available_balance') +
                DB::connection('mongodb')->table('wallets')->sum('demo_locked_balance') +
                DB::connection('mongodb')->table('wallets')->sum('demo_withdrawal_locked_balance')
            ),
            2
        );

        $stats = [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'suspended' => $suspendedUsers,
            'blocked' => $blockedUsers,
            'total_balance' => $totalWalletBalance,
        ];

        $query = User::query()->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('client_id', 'like', "%{$search}%")
                    ->orWhere('uid', 'like', "%{$search}%");
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        } else {
            // Exclude deleted and pending-deletion users from the main "All Users" list
            $query->where($baseActiveQuery);
        }

        $users = $query->paginate(10)->withQueryString();

        $userUids = $users->getCollection()->pluck('uid')->filter()->map(fn ($id) => (string) $id)->all();
        $userObjectIds = $users->getCollection()->pluck('_id')->filter()->map(fn ($id) => (string) $id)->all();
        $userIds = $users->getCollection()->pluck('id')->filter()->map(fn ($id) => (string) $id)->all();
        $allSearchKeys = array_unique(array_merge($userUids, $userObjectIds, $userIds));

        $pageWallets = DB::connection('mongodb')
            ->table('wallets')
            ->whereIn('user_uid', $allSearchKeys)
            ->get();

        $walletBalanceMap = $this->walletBalanceMap($pageWallets);

        $users->getCollection()->transform(function ($user) use ($walletBalanceMap) {
            $keys = array_filter([
                isset($user->uid) ? (string) $user->uid : null,
                isset($user->_id) ? (string) $user->_id : null,
                isset($user->id) ? (string) $user->id : null,
            ]);

            $balance = 0.0;

            foreach ($keys as $key) {
                if ($walletBalanceMap->has($key)) {
                    $balance = (float) $walletBalanceMap->get($key);
                    break;
                }
            }

            $user->admin_wallet_balance = round($balance, 2);

            return $user;
        });

        $referrerUids = $users->getCollection()->pluck('referred_by')->filter()->unique()->values();
        $referrersMap = [];
        if ($referrerUids->isNotEmpty()) {
            $referrers = DB::connection('mongodb')->table('users')->whereIn('uid', $referrerUids->all())->get();
            foreach ($referrers as $r) {
                $rArr = (array) $r;
                $referrersMap[$rArr['uid']] = $rArr['client_id'] ?? $rArr['name'] ?? $rArr['email'];
            }
        }

        return view('admin.users.index', compact(
            'users',
            'stats',
            'search',
            'status',
            'referrersMap'
        ));
    }

    private function safeCollection(string $collection, int $limit = 5000)
    {
        try {
            return DB::connection('mongodb')
                ->table($collection)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        } catch (\Throwable $exception) {
            return collect();
        }
    }

    private function walletBalanceMap($wallets)
    {
        $map = collect();

        foreach ($wallets as $wallet) {
            $userUid = (string) ($wallet->user_uid ?? $wallet->uid ?? '');

            if ($userUid === '') {
                continue;
            }

            $map->put($userUid, $this->walletTotal($wallet));
        }

        return $map;
    }

    private function walletTotal($wallet): float
    {
        return round(
            (float) ($wallet->demo_available_balance ?? 0)
            + (float) ($wallet->demo_locked_balance ?? 0)
            + (float) ($wallet->demo_withdrawal_locked_balance ?? 0),
            2
        );
    }

    public function show(Request $request, string $id)
    {
        $user = User::where('_id', $id)->first() ?? User::where('uid', $id)->firstOrFail();

        $userId = $user->uid ?? (string)($user->_id ?? $id);
        $selectedAccountUid = $request->query('account_uid');

        $walletRecord = DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $userId)
            ->first();

        $ledgerQuery = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->where('user_uid', $userId);

        if (!empty($selectedAccountUid)) {
            $ledgerQuery->where('account_uid', $selectedAccountUid);
        }

        $ledgerAll = $ledgerQuery->orderByDesc('created_at')->get();

        $deposits = $ledgerAll->filter(function ($item) {
            $type = strtolower((string)($item->type ?? ''));
            return str_contains($type, 'deposit') ||
                str_contains($type, 'demo_credit') ||
                str_contains($type, 'fund');
        })->take(5)->values();

        $withdrawQuery = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->where(function ($q) use ($userId, $id) {
                $q->where('user_uid', $userId)
                  ->orWhere('user_uid', $id)
                  ->orWhere('user_id', $userId)
                  ->orWhere('user_id', $id);
            });

        if (!empty($selectedAccountUid)) {
            $withdrawQuery->where('account_uid', $selectedAccountUid);
        }

        $withdrawals = $withdrawQuery->orderByDesc('created_at')->limit(5)->get();

        $ordersQuery = DB::connection('mongodb')
            ->table('futures_orders')
            ->where('user_uid', $userId);

        if (!empty($selectedAccountUid)) {
            $ordersQuery->where('account_uid', $selectedAccountUid);
        }

        $orders = $ordersQuery->orderByDesc('created_at')->limit(5)->get();

        $orderUids = $orders->pluck('order_uid')->filter()->all();
        $ordersPositions = DB::connection('mongodb')
            ->table('futures_positions')
            ->whereIn('order_uid', $orderUids)
            ->get()
            ->keyBy('order_uid');

        $orders->transform(function ($order) use ($ordersPositions) {
            $position = $ordersPositions->get($order->order_uid);
            if ($position) {
                $positionStatus = strtolower((string)($position->status ?? 'open'));
                $order->display_status = $positionStatus === 'open' ? 'active' : 'filled';
            } else {
                $status = strtolower(trim($order->status ?? 'pending'));
                $order->display_status = $status === 'open' ? 'active' : ($status === 'closed' ? 'filled' : $status);
            }
            return $order;
        });

        $futuresPosQuery = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $userId)
            ->where('status', 'open');

        if (!empty($selectedAccountUid)) {
            $futuresPosQuery->where('account_uid', $selectedAccountUid);
        }

        $futuresPositions = $futuresPosQuery->get()->map(function ($p) {
            $p->market_type = 'futures';
            return $p;
        });

        $optionsPositions = collect();
        try {
            $optionsPosQuery = DB::connection('mongodb')
                ->table('options_positions')
                ->where('user_uid', $userId)
                ->where('status', 'open');

            if (!empty($selectedAccountUid)) {
                $optionsPosQuery->where('account_uid', $selectedAccountUid);
            }

            $optionsPositions = $optionsPosQuery->get()->map(function ($p) {
                $p->market_type = 'options';
                $p->symbol = $p->option_symbol ?? $p->contract_symbol ?? $p->symbol ?? $p->underlying ?? '-';
                return $p;
            });
        } catch (\Throwable $e) {}

        $positions = $futuresPositions->merge($optionsPositions)
            ->sortByDesc('created_at')
            ->take(5)
            ->values();

        $totalDeposit = (float) $ledgerAll->filter(function ($item) {
            $type = strtolower((string)($item->type ?? ''));

            return str_contains($type, 'deposit') ||
                str_contains($type, 'demo_credit') ||
                str_contains($type, 'fund');
        })->sum('amount');

        $totalWithdraw = (float) $ledgerAll->filter(function ($item) {
            $type = strtolower((string)($item->type ?? ''));

            return str_contains($type, 'withdraw');
        })->sum('amount');

        $wallet = [
            'available_balance' => (float)($walletRecord->demo_available_balance ?? 0),
            'locked_balance' => (float)($walletRecord->demo_locked_balance ?? 0),
            'total_deposit' => $totalDeposit,
            'total_withdraw' => $totalWithdraw,
            'total_withdrawal' => $totalWithdraw,
        ];

        $risk = [
            'kyc_status' => $user->kyc_status ?? 'pending',
            'last_login' => $user->last_login_at ?? null,
        ];

        $recentActivity = collect();

        foreach ($ledgerAll->take(5) as $item) {
            $recentActivity->push([
                'label' => ucfirst(str_replace('_', ' ', $item->type ?? 'Wallet Activity')),
                'type' => 'Wallet',
                'date' => $item->created_at ?? null,
                'status' => $item->status ?? 'completed',
            ]);
        }

        foreach ($orders as $item) {
            $recentActivity->push([
                'label' => strtoupper($item->symbol ?? 'Order') . ' Order',
                'type' => 'Order',
                'date' => $item->created_at ?? null,
                'status' => $item->status ?? 'pending',
            ]);
        }

        $recentActivity = $recentActivity
            ->sortByDesc(fn ($item) => strtotime((string)($item['date'] ?? now())))
            ->take(6)
            ->values();

        $currencyHistories = DB::connection('mongodb')
            ->table('wallet_currency_histories')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        $mainBal = (float)($walletRecord->demo_main_balance ?? 0);
        $tradingBal = (float)($walletRecord->demo_available_balance ?? 0);
        $lockedBal = (float)($walletRecord->demo_locked_balance ?? 0);

        $openPositionsCount = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $userId)->where('status', 'open')->count()
            + DB::connection('mongodb')
            ->table('options_positions')
            ->where('user_uid', $userId)->where('status', 'open')->count();

        $pendingOrdersCount = DB::connection('mongodb')
            ->table('futures_orders')
            ->where('user_uid', $userId)->whereIn('status', ['pending', 'open'])->count();

        $pendingWithdrawalsCount = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->where(function ($q) use ($userId, $id) {
                $q->where('user_uid', $userId)->orWhere('user_id', $userId);
            })->where('status', 'pending')->count();

        $pendingDepositsCount = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->where('user_uid', $userId)->where('status', 'pending')->count();

        $resetChecklist = [
            'main_balance_zero' => $mainBal == 0,
            'trading_balance_zero' => ($tradingBal == 0 && $lockedBal == 0),
            'no_open_positions' => $openPositionsCount == 0,
            'no_pending_orders' => $pendingOrdersCount == 0,
            'no_pending_withdrawals' => $pendingWithdrawalsCount == 0,
            'no_pending_deposits' => $pendingDepositsCount == 0,
            'can_reset' => ($mainBal == 0 && $tradingBal == 0 && $lockedBal == 0 && $openPositionsCount == 0 && $pendingOrdersCount == 0 && $pendingWithdrawalsCount == 0 && $pendingDepositsCount == 0),
            'main_bal' => $mainBal,
            'trading_bal' => $tradingBal + $lockedBal,
            'open_positions' => $openPositionsCount,
            'pending_orders' => $pendingOrdersCount,
            'pending_withdrawals' => $pendingWithdrawalsCount,
            'pending_deposits' => $pendingDepositsCount,
        ];

        $tradingAccounts = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userId)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        $bankDetails = DB::connection('mongodb')
            ->table('user_bank_details')
            ->where('user_uid', $userId)
            ->first();

        // Referral Details for this user
        $referrer = null;
        if (!empty($user->referred_by)) {
            $refUser = User::where('uid', $user->referred_by)->first();
            if (!$refUser) {
                $refUser = DB::connection('mongodb')->table('users')->where('uid', $user->referred_by)->first();
            }
            if ($refUser) {
                $refArr = (array) $refUser;
                $refId = (string)($refUser->_id ?? $refUser->id ?? $refArr['_id'] ?? $refArr['id'] ?? '');
                $referrer = [
                    'id' => $refId,
                    'uid' => $refUser->uid ?? $refArr['uid'] ?? null,
                    'name' => $refUser->name ?? $refArr['name'] ?? null,
                    'email' => $refUser->email ?? $refArr['email'] ?? null,
                    'client_id' => $refUser->client_id ?? $refArr['client_id'] ?? null,
                ];
            }
        }

        // Friends referred by this user
        $referredFriends = User::where('referred_by', $userId)
            ->orderByDesc('created_at')
            ->get();
        if ($referredFriends->isEmpty()) {
            $referredFriends = DB::connection('mongodb')->table('users')
                ->where('referred_by', $userId)
                ->orderByDesc('created_at')
                ->get();
        }

        // Commissions earned by this user
        $referralCommissions = DB::connection('mongodb')->table('referral_rewards')
            ->where('referrer_uid', $userId)
            ->orderByDesc('created_at')
            ->get();

        $totalReferralCommission = (float) $referralCommissions->sum('amount');

        return view('admin.users.show', [
            'user' => $user,
            'userId' => $userId,
            'tradingAccounts' => $tradingAccounts,
            'selectedAccountUid' => $selectedAccountUid,
            'initial' => strtoupper(substr($user->name ?? ($user->email ?? 'U'), 0, 1)),
            'statusValue' => $user->status ?? 'active',
            'kycStatus' => $user->kyc_status ?? 'pending',
            'wallet' => $wallet,
            'bankDetails' => $bankDetails,
            'referrer' => $referrer,
            'referredFriends' => $referredFriends,
            'referralCommissions' => $referralCommissions,
            'totalReferralCommission' => $totalReferralCommission,
            'ledger' => $ledgerAll->take(5)->values(),
            'deposits' => $deposits,
            'withdrawals' => $withdrawals,
            'orders' => $orders,
            'positions' => $positions,
            'risk' => $risk,
            'recentActivity' => $recentActivity,
            'riskScore' => 0,
            'riskLevel' => 'low',
            'currencyHistories' => $currencyHistories,
            'resetChecklist' => $resetChecklist,
        ]);
    }

    public function resetCurrency(Request $request, string $id)
    {
        $user = User::where('_id', $id)->firstOrFail();
        $userUid = (string)($user->uid ?? $user->_id ?? $id);

        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $walletRecord = DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $userUid)
            ->first();

        $mainBal = (float)($walletRecord->demo_main_balance ?? 0);
        $tradingBal = (float)($walletRecord->demo_available_balance ?? 0);
        $lockedBal = (float)($walletRecord->demo_locked_balance ?? 0);

        $openPositionsCount = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $userUid)->where('status', 'open')->count()
            + DB::connection('mongodb')
            ->table('options_positions')
            ->where('user_uid', $userUid)->where('status', 'open')->count();

        $pendingOrdersCount = DB::connection('mongodb')
            ->table('futures_orders')
            ->where('user_uid', $userUid)->whereIn('status', ['pending', 'open'])->count();

        $pendingWithdrawalsCount = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->where(function ($q) use ($userUid, $id) {
                $q->where('user_uid', $userUid)->orWhere('user_id', $userUid);
            })->where('status', 'pending')->count();

        $pendingDepositsCount = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->where('user_uid', $userUid)->where('status', 'pending')->count();

        $errors = [];
        if ($mainBal > 0) $errors[] = "Main Balance must be 0";
        if ($tradingBal > 0 || $lockedBal > 0) $errors[] = "Trading Balance must be 0";
        if ($openPositionsCount > 0) $errors[] = "User has {$openPositionsCount} open position(s)";
        if ($pendingOrdersCount > 0) $errors[] = "User has {$pendingOrdersCount} pending order(s)";
        if ($pendingWithdrawalsCount > 0) $errors[] = "User has {$pendingWithdrawalsCount} pending withdrawal(s)";
        if ($pendingDepositsCount > 0) $errors[] = "User has {$pendingDepositsCount} pending deposit(s)";

        if (!empty($errors)) {
            return redirect()->back()->with('error', 'Cannot reset currency. Requirements not met: ' . implode(' • ', $errors));
        }

        $oldCurrency = $user->base_currency ?: 'NULL';
        $user->base_currency = null;
        $user->save();

        DB::connection('mongodb')->table('wallet_currency_histories')->insert([
            'user_id' => $userUid,
            'action' => 'Reset',
            'old_currency' => $oldCurrency,
            'new_currency' => 'NULL',
            'performed_by' => session('admin_user.email') ?? 'Admin',
            'reason' => $request->input('reason'),
            'created_at' => now()->toDateTimeString(),
        ]);

        AuditLogger::log('currency_reset', "Reset currency for user {$user->email} to NULL", [
            'user_uid' => $userUid,
            'reason' => $request->input('reason'),
        ]);

        return redirect()->back()->with('success', "Currency for {$user->name} reset to NULL successfully.");
    }

    public function changeCurrency(Request $request, string $id)
    {
        $user = User::where('_id', $id)->firstOrFail();
        $userUid = (string)($user->uid ?? $user->_id ?? $id);

        $request->validate([
            'currency' => 'required|in:INR,USDT',
            'reason' => 'required|string|max:500',
        ]);

        $newCurrency = strtoupper($request->input('currency'));
        $oldCurrency = $user->base_currency ?: 'NULL';

        $user->base_currency = $newCurrency;
        $user->save();

        DB::connection('mongodb')->table('wallet_currency_histories')->insert([
            'user_id' => $userUid,
            'action' => 'Assigned',
            'old_currency' => $oldCurrency,
            'new_currency' => $newCurrency,
            'performed_by' => session('admin_user.email') ?? 'Admin',
            'reason' => $request->input('reason'),
            'created_at' => now()->toDateTimeString(),
        ]);

        AuditLogger::log('currency_change', "Assigned/Changed currency for user {$user->email} to {$newCurrency}", [
            'user_uid' => $userUid,
            'currency' => $newCurrency,
            'reason' => $request->input('reason'),
        ]);

        return redirect()->back()->with('success', "Currency for {$user->name} updated to {$newCurrency}.");
    }

    public function toggleCurrencyLock(Request $request, string $id)
    {
        $user = User::where('_id', $id)->firstOrFail();
        $userUid = (string)($user->uid ?? $user->_id ?? $id);

        $isLocked = !((bool)($user->currency_locked ?? false));
        $user->currency_locked = $isLocked;
        $user->save();

        $actionName = $isLocked ? 'Locked' : 'Unlocked';

        DB::connection('mongodb')->table('wallet_currency_histories')->insert([
            'user_id' => $userUid,
            'action' => $actionName,
            'old_currency' => $user->base_currency ?: 'NULL',
            'new_currency' => $user->base_currency ?: 'NULL',
            'performed_by' => session('admin_user.email') ?? 'Admin',
            'reason' => $request->input('reason', $actionName . ' by Admin'),
            'created_at' => now()->toDateTimeString(),
        ]);

        AuditLogger::log('currency_lock_toggle', "{$actionName} currency assignment for user {$user->email}", [
            'user_uid' => $userUid,
            'locked' => $isLocked,
        ]);

        return redirect()->back()->with('success', "Currency status for {$user->name} is now {$actionName}.");
    }



    public function exportCsv(string $id)
    {
        $report = $this->buildFullUserReport($id);
        $filename = 'kryvex-user-report-' . $report['safe_user_id'] . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [$report['title']]);
            fputcsv($handle, [$report['subtitle']]);
            fputcsv($handle, ['Generated', $report['generated']]);
            fputcsv($handle, []);

            foreach ($report['tables'] as $table) {
                fputcsv($handle, [$table['title']]);
                fputcsv($handle, $table['headers']);

                if (empty($table['rows'])) {
                    fputcsv($handle, ['—', 'No records']);
                } else {
                    foreach ($table['rows'] as $row) {
                        fputcsv($handle, $row);
                    }
                }

                fputcsv($handle, []);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(string $id)
    {
        $report = $this->buildFullUserReport($id);
        $filename = 'kryvex-user-report-' . $report['safe_user_id'] . '-' . now()->format('Ymd-His') . '.pdf';

        return response($this->makeStyledUserReportPdf($report), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function buildFullUserReport(string $id): array
    {
        $user = User::where('_id', $id)->firstOrFail();
        $userUid = (string) ($user->uid ?? $user->_id ?? $id);
        $generated = now()->format('d/m/Y, H:i:s');

        $wallet = $this->firstFromCollection('wallets', 'user_uid', $userUid);
        $ledger = $this->safeCollectionForUser('wallet_ledgers', $userUid, 1000);
        $withdrawals = $this->safeCollectionForUser('wallet_withdrawal_requests', $userUid, 500);
        $withdrawalAddresses = $this->safeCollectionForUser('user_withdrawal_addresses', $userUid, 100);
        $futuresOrders = $this->safeCollectionForUser('futures_orders', $userUid, 1000);
        $futuresPositions = $this->safeCollectionForUser('futures_positions', $userUid, 1000);
        $futuresTrades = $this->safeCollectionForUser('futures_trades', $userUid, 1000);
        $optionOrders = $this->safeCollectionForUser('option_orders', $userUid, 1000);
        $optionPositions = $this->safeCollectionForUser('option_positions', $userUid, 1000);
        $optionTrades = $this->safeCollectionForUser('option_trades', $userUid, 1000);
        $notifications = $this->safeCollectionForUser('notifications', $userUid, 500);

        $totalDeposited = $ledger->filter(function ($row) {
            $type = strtolower((string) ($row->type ?? ''));
            return str_contains($type, 'deposit') || str_contains($type, 'credit') || str_contains($type, 'fund');
        })->sum(fn ($row) => abs((float) ($row->amount ?? 0)));

        $totalWithdrawn = $withdrawals->filter(fn ($row) => strtolower((string) ($row->status ?? '')) === 'approved')
            ->sum(fn ($row) => (float) ($row->amount ?? 0));

        if ($totalWithdrawn <= 0) {
            $totalWithdrawn = $ledger->filter(function ($row) {
                return str_contains(strtolower((string) ($row->type ?? '')), 'withdraw');
            })->sum(fn ($row) => abs((float) ($row->amount ?? 0)));
        }

        $mainBalance = (float) ($wallet->main_balance ?? $wallet->demo_main_balance ?? 0);
        $tradingAvailable = (float) ($wallet->trading_available_balance ?? $wallet->demo_available_balance ?? 0);
        $tradingLocked = (float) ($wallet->trading_locked_balance ?? $wallet->demo_locked_balance ?? 0);
        $walletBalance = $mainBalance + $tradingAvailable + $tradingLocked + (float) ($wallet->demo_withdrawal_locked_balance ?? 0);

        $allOrders = $futuresOrders->concat($optionOrders)->values();
        $allTrades = $futuresTrades->concat($optionTrades)->values();
        $openPositions = $futuresPositions->concat($optionPositions)
            ->filter(fn ($row) => strtolower((string) ($row->status ?? '')) === 'open')
            ->values();

        $summaryRows = [
            ['User ID', $userUid],
            ['Full Name', (string) ($user->name ?? '-')],
            ['Email', (string) ($user->email ?? '-')],
            ['Status', strtoupper((string) ($user->status ?? 'ACTIVE'))],
            ['Role', strtoupper((string) ($user->role ?? 'USER'))],
            ['KYC Status', strtoupper((string) ($user->kyc_status ?? 'PENDING'))],
            ['Wallet Balance', $this->moneyValue($walletBalance)],
            ['Main Balance', $this->moneyValue($mainBalance)],
            ['Trading Available', $this->moneyValue($tradingAvailable)],
            ['Trading Locked', $this->moneyValue($tradingLocked)],
            ['Total Deposited', $this->moneyValue($totalDeposited)],
            ['Total Withdrawn', $this->moneyValue($totalWithdrawn)],
            ['Open Positions', (string) $openPositions->count()],
            ['Total Orders', (string) $allOrders->count()],
            ['Total Trades', (string) $allTrades->count()],
            ['Joined', $this->formatExportDate($user->created_at ?? null)],
            ['Last Login', $this->formatExportDate($user->last_login_at ?? null)],
            ['Report Generated', $generated],
        ];

        $tables = [
            [
                'title' => 'Account Summary',
                'headers' => ['Field', 'Value'],
                'rows' => $summaryRows,
                'widths' => [190, 345],
            ],
            [
                'title' => 'Deposits',
                'headers' => ['ID', 'Amount', 'Method', 'Status', 'Approved By', 'Created'],
                'rows' => $this->ledgerDepositRows($ledger),
                'widths' => [45, 75, 125, 85, 95, 110],
            ],
            [
                'title' => 'Withdrawals',
                'headers' => ['ID', 'Amount', 'Fees', 'User Receives', 'Network', 'Status', 'Requested', 'Processed'],
                'rows' => $this->withdrawalRows($withdrawals),
                'widths' => [42, 70, 55, 80, 60, 70, 80, 78],
            ],
            [
                'title' => 'Saved Withdrawal Addresses',
                'headers' => ['Network', 'Address', 'Updated'],
                'rows' => $this->addressRows($withdrawalAddresses),
                'widths' => [80, 325, 130],
            ],
            [
                'title' => 'Open Positions',
                'headers' => ['Symbol', 'Side', 'Qty', 'Entry', 'Leverage', 'Liq', 'Status'],
                'rows' => $this->positionRows($openPositions, true),
                'widths' => [90, 55, 80, 85, 70, 80, 75],
            ],
            [
                'title' => 'Order History',
                'headers' => ['ID', 'Symbol', 'Side', 'Type', 'Qty', 'Price', 'Status'],
                'rows' => $this->orderRows($allOrders),
                'widths' => [180, 80, 50, 65, 75, 80, 65],
            ],
            [
                'title' => 'Trade History',
                'headers' => ['Symbol', 'Side', 'Entry', 'Exit', 'PNL', 'Reason', 'Closed'],
                'rows' => $this->tradeRows($allTrades),
                'widths' => [90, 55, 80, 80, 80, 85, 65],
            ],
            [
                'title' => 'Trading & Operational Ledger',
                'headers' => ['Type', 'Amount', 'Balance After', 'Status', 'Description', 'Created'],
                'rows' => $this->ledgerRows($ledger),
                'widths' => [95, 70, 85, 70, 125, 90],
            ],
            [
                'title' => 'Notifications',
                'headers' => ['Type', 'Title', 'Message', 'Read', 'Created'],
                'rows' => $this->notificationRows($notifications),
                'widths' => [70, 125, 170, 55, 115],
            ],
        ];

        return [
            'title' => 'KRYVEX Exchange - User Report',
            'subtitle' => (string) ($user->name ?? 'Unnamed User') . ' | ' . (string) ($user->email ?? 'No email'),
            'generated' => $generated,
            'safe_user_id' => preg_replace('/[^A-Za-z0-9_-]/', '-', $userUid),
            'tables' => $tables,
        ];
    }

    private function ledgerDepositRows($ledger): array
    {
        return $ledger->filter(function ($row) {
            $type = strtolower((string) ($row->type ?? ''));
            return str_contains($type, 'deposit') || str_contains($type, 'credit') || str_contains($type, 'fund');
        })->take(50)->values()->map(function ($row, $index) {
            return [
                (string) ($row->id ?? $row->_id ?? $row->ledger_uid ?? $index + 1),
                $this->numberValue($row->amount ?? 0),
                (string) ($row->method ?? $row->network ?? $row->type ?? 'Wallet'),
                strtoupper((string) ($row->status ?? 'APPROVED')),
                (string) ($row->approved_by ?? $row->admin ?? 'AUTO'),
                $this->formatExportDate($row->created_at ?? null),
            ];
        })->all();
    }

    private function withdrawalRows($withdrawals): array
    {
        return $withdrawals->take(100)->values()->map(function ($row, $index) {
            return [
                (string) ($row->id ?? $row->_id ?? $row->request_uid ?? $index + 1),
                $this->numberValue($row->amount ?? 0),
                $this->numberValue(($row->fee ?? 0) + ($row->platform_fee ?? 0)),
                $this->numberValue($row->receive_amount ?? $row->net_amount ?? 0),
                (string) ($row->network ?? '—'),
                strtoupper((string) ($row->status ?? 'PENDING')),
                $this->formatExportDate($row->created_at ?? null),
                $this->formatExportDate($row->processed_at ?? $row->approved_at ?? $row->rejected_at ?? null),
            ];
        })->all();
    }

    private function addressRows($items): array
    {
        return $items->take(100)->values()->map(function ($row) {
            return [
                strtoupper((string) ($row->network ?? '-')),
                (string) ($row->address ?? $row->wallet_address ?? '-'),
                $this->formatExportDate($row->updated_at ?? $row->created_at ?? null),
            ];
        })->all();
    }

    private function orderRows($orders): array
    {
        return $orders->take(300)->values()->map(function ($row) {
            return [
                (string) ($row->order_uid ?? $row->_id ?? '-'),
                (string) ($row->symbol ?? $row->option_symbol ?? '-'),
                strtoupper((string) ($row->side ?? '-')),
                strtoupper((string) ($row->order_type ?? '-')),
                $this->numberValue($row->quantity ?? $row->estimated_quantity ?? 0, 8),
                $this->numberValue($row->execution_price ?? $row->price ?? 0),
                strtoupper((string) ($row->status ?? '-')),
            ];
        })->all();
    }

    private function positionRows($positions, bool $openOnly = false): array
    {
        return $positions->take(200)->values()->map(function ($row) {
            return [
                (string) ($row->symbol ?? $row->option_symbol ?? '-'),
                strtoupper((string) ($row->side ?? '-')),
                $this->numberValue($row->quantity ?? 0, 8),
                $this->numberValue($row->entry_price ?? 0),
                (string) ($row->leverage ?? '-'),
                $this->numberValue($row->liquidation_price ?? 0),
                strtoupper((string) ($row->status ?? '-')),
            ];
        })->all();
    }

    private function tradeRows($trades): array
    {
        return $trades->take(300)->values()->map(function ($row) {
            return [
                (string) ($row->symbol ?? $row->option_symbol ?? '-'),
                strtoupper((string) ($row->side ?? '-')),
                $this->numberValue($row->entry_price ?? $row->price ?? 0),
                $this->numberValue($row->exit_price ?? $row->close_price ?? 0),
                $this->numberValue($row->realized_pnl ?? $row->pnl ?? 0),
                strtoupper((string) ($row->close_reason ?? $row->reason ?? 'MANUAL')),
                $this->formatExportDate($row->closed_at ?? $row->created_at ?? null),
            ];
        })->all();
    }

    private function ledgerRows($ledger): array
    {
        return $ledger->filter(function ($row) {
            $type = strtolower((string) ($row->type ?? ''));
            return !str_contains($type, 'deposit') &&
                !str_contains($type, 'credit') &&
                !str_contains($type, 'fund') &&
                !str_contains($type, 'withdraw');
        })->take(200)->values()->map(function ($row) {
            return [
                (string) ($row->type ?? '-'),
                $this->numberValue($row->amount ?? 0),
                $this->numberValue($row->balance_after ?? 0),
                strtoupper((string) ($row->status ?? 'COMPLETED')),
                (string) ($row->description ?? $row->reference ?? '-'),
                $this->formatExportDate($row->created_at ?? null),
            ];
        })->all();
    }

    private function notificationRows($notifications): array
    {
        return $notifications->take(100)->values()->map(function ($row) {
            return [
                strtoupper((string) ($row->type ?? '-')),
                (string) ($row->title ?? '-'),
                (string) ($row->message ?? '-'),
                !empty($row->read_at) ? 'Yes' : 'No',
                $this->formatExportDate($row->created_at ?? null),
            ];
        })->all();
    }

    private function safeCollectionForUser(string $collection, string $userUid, int $limit = 300)
    {
        try {
            return DB::connection('mongodb')
                ->table($collection)
                ->where('user_uid', $userUid)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        } catch (\Throwable $exception) {
            return collect();
        }
    }

    private function firstFromCollection(string $collection, string $field, string $value)
    {
        try {
            return DB::connection('mongodb')
                ->table($collection)
                ->where($field, $value)
                ->first() ?: (object) [];
        } catch (\Throwable $exception) {
            return (object) [];
        }
    }

    private function formatExportDate($value): string
    {
        if (empty($value)) {
            return '-';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y, H:i:s');
        } catch (\Throwable $exception) {
            return (string) $value;
        }
    }

    private function moneyValue($value): string
    {
        return '$' . number_format((float) $value, 4);
    }

    private function numberValue($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return number_format((float) $value, $decimals, '.', '');
    }

    private function makeStyledUserReportPdf(array $report): string
    {
        $pages = [];
        $current = '';
        $y = 806;
        $left = 34;
        $pageWidth = 595;
        $rowHeight = 20;
        $fontSize = 8;

        $newPage = function () use (&$pages, &$current, &$y) {
            if ($current !== '') {
                $pages[] = $current;
            }
            $current = '';
            $y = 806;
        };

        $drawText = function (float $x, float $ty, string $text, int $size = 9, string $font = 'F1') use (&$current) {
            $text = mb_substr($text, 0, 110);
            $current .= "BT /{$font} {$size} Tf {$x} {$ty} Td (" . $this->escapePdfText($text) . ") Tj ET\n";
        };

        $drawRect = function (float $x, float $ty, float $w, float $h, string $rgb) use (&$current) {
            $current .= "q {$rgb} rg {$x} {$ty} {$w} {$h} re f Q\n";
        };

    
        $newPage();
        $drawText($left, $y, $report['title'], 18, 'F2');
        $y -= 24;
        $drawText($left, $y, $report['subtitle'], 10, 'F1');
        $y -= 17;
        $drawText($left, $y, 'Generated: ' . $report['generated'], 10, 'F1');
        $y -= 26;

        foreach ($report['tables'] as $table) {
            if ($y < 95) {
                $newPage();
            }

            $drawText($left, $y, $table['title'], 14, 'F2');
            $y -= 22;

            $headers = $table['headers'];
            $widths = $table['widths'] ?? array_fill(0, count($headers), floor(535 / max(1, count($headers))));
            $totalWidth = array_sum($widths);

            $drawRect($left, $y - 4, $totalWidth, $rowHeight, '0.10 0.12 0.14');
            $x = $left + 5;
            $current .= "1.00 1.00 1.00 rg\n";
            foreach ($headers as $i => $header) {
                $drawText($x, $y + 2, (string) $header, $fontSize, 'F2');
                $x += $widths[$i];
            }
            $current .= "0.00 0.00 0.00 rg\n";
            $y -= $rowHeight;

            $rows = $table['rows'];
            if (empty($rows)) {
                $rows = [array_pad(['-', 'No records'], count($headers), '')];
            }

            foreach ($rows as $rowIndex => $row) {
                if ($y < 45) {
                    $newPage();
                    $drawText($left, $y, $table['title'] . ' (continued)', 12, 'F2');
                    $y -= 20;
                    $drawRect($left, $y - 4, $totalWidth, $rowHeight, '0.10 0.12 0.14');
                    $x = $left + 5;
                    $current .= "1.00 1.00 1.00 rg\n";
                    foreach ($headers as $i => $header) {
                        $drawText($x, $y + 2, (string) $header, $fontSize, 'F2');
                        $x += $widths[$i];
                    }
                    $current .= "0.00 0.00 0.00 rg\n";
                    $y -= $rowHeight;
                }

                if ($rowIndex % 2 === 0) {
                    $drawRect($left, $y - 4, $totalWidth, $rowHeight, '0.94 0.94 0.94');
                } else {
                    $drawRect($left, $y - 4, $totalWidth, $rowHeight, '0.98 0.98 0.98');
                }

                $x = $left + 5;
                foreach ($headers as $i => $_header) {
                    $value = (string) ($row[$i] ?? '');
                    $maxChars = max(8, (int) floor(($widths[$i] - 8) / 4.5));
                    if (mb_strlen($value) > $maxChars) {
                        $value = mb_substr($value, 0, max(1, $maxChars - 1)) . '...';
                    }
                    $drawText($x, $y + 2, $value, $fontSize, 'F1');
                    $x += $widths[$i];
                }
                $y -= $rowHeight;
            }

            $y -= 18;
        }

        if ($current !== '') {
            $pages[] = $current;
        }

        return $this->buildPdfFromStreams($pages);
    }

    private function buildPdfFromStreams(array $streams): string
    {
        $objects = [];
        $catalogId = 1;
        $pagesId = 2;
        $fontId = 3;
        $boldFontId = 4;
        $nextId = 5;
        $pageIds = [];
        $contentIds = [];

        foreach ($streams as $_stream) {
            $pageIds[] = $nextId++;
            $contentIds[] = $nextId++;
        }

        $objects[$catalogId] = "<< /Type /Catalog /Pages {$pagesId} 0 R >>";
        $objects[$pagesId] = '<< /Type /Pages /Kids [' . implode(' ', array_map(fn ($id) => $id . ' 0 R', $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[$boldFontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        foreach ($pageIds as $i => $pageId) {
            $contentId = $contentIds[$i];
            $stream = $streams[$i] ?? '';
            $objects[$pageId] = "<< /Type /Page /Parent {$pagesId} 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 {$fontId} 0 R /F2 {$boldFontId} 0 R >> >> /Contents {$contentId} 0 R >>";
            $objects[$contentId] = "<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
        }

        ksort($objects);
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string) ($offsets[$i] ?? 0), 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root {$catalogId} 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $text) ?? $text;
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }


    public function updateStatus(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,suspended,blocked'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'delay_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'duration_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'block_delay_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $user = User::where('_id', $id)->firstOrFail();

        $userUid = $user->uid ?? (string) $user->_id;
        $newStatus = $validated['status'];

        if ($newStatus === 'active') {
            // Activate clears both scheduled suspension and scheduled block fields.
            AccountStatusService::clearSuspension($userUid);

            $user->status = 'active';
            $user->blocked_reason = null;
            $user->block_reason = null;
            $user->save();

            AuditLogger::log(
                'activate',
                'users',
                'User account activated.',
                [
                    'user_id' => $id,
                    'user_uid' => $userUid,
                    'email' => $user->email ?? null,
                ]
            );

            AdminNotificationController::createAndEmailForUser(
                $userUid,
                'account',
                'Account Activated',
                'Your Kryvex account has been activated. You can now access your account and trading features.',
                [
                    'source' => 'admin_user_status',
                    'status' => 'active',
                    'admin' => session('admin_email') ?? 'admin',
                ]
            );

            return back()->with('success', 'User activated successfully. Notification and email sent.');
        }

        if ($newStatus === 'blocked') {
            $reason = trim((string) ($validated['reason'] ?? ''));

            if ($reason === '') {
                return back()->with('error', 'Block reason is required.');
            }

            $delayMinutes = (int) ($validated['block_delay_minutes'] ?? $validated['delay_minutes'] ?? 10);
            $effectiveAt = now(config('app.timezone'))->addMinutes($delayMinutes);

            // Block is scheduled first so the user gets grace time and a warning banner.
            // Once the grace time ends, AccountStatusService turns the account into blocked.
            $user->status = 'active';
            $user->block_status = 'scheduled';
            $user->block_reason = $reason;
            $user->blocked_reason = $reason;
            $user->block_effective_at = $effectiveAt;
            $user->blocked_at = null;

            // A new block schedule clears any suspension schedule.
            $user->suspension_status = null;
            $user->suspension_reason = null;
            $user->suspension_effective_at = null;
            $user->suspension_until = null;
            $user->suspension_duration_minutes = null;
            $user->suspended_at = null;
            $user->save();

            AuditLogger::log(
                'schedule_block',
                'users',
                'User block scheduled.',
                [
                    'user_id' => $id,
                    'user_uid' => $userUid,
                    'email' => $user->email ?? null,
                    'reason' => $reason,
                    'effective_at' => $effectiveAt->toDateTimeString(),
                    'delay_minutes' => $delayMinutes,
                ]
            );

            AdminNotificationController::createAndEmailForUser(
                $userUid,
                'warning',
                'Account Block Scheduled',
                'Your Kryvex account is scheduled to be blocked after ' . $delayMinutes . ' minutes. Reason: ' . $reason,
                [
                    'source' => 'admin_user_status',
                    'status' => 'blocked',
                    'reason' => $reason,
                    'effective_at' => $effectiveAt->toIso8601String(),
                    'delay_minutes' => $delayMinutes,
                    'admin' => session('admin_email') ?? 'admin',
                ]
            );

            return back()->with(
                'success',
                'User block scheduled. Notification and email sent. It will start after ' . $delayMinutes . ' minutes.'
            );
        }

        // Suspend schedule
        $reason = trim((string) ($validated['reason'] ?? ''));

        if ($reason === '') {
            return back()->with('error', 'Suspension reason is required.');
        }

        $delayMinutes = (int) ($validated['delay_minutes'] ?? 10);
        $durationHours = (int) ($validated['duration_hours'] ?? 12);

        $effectiveAt = now(config('app.timezone'))->addMinutes($delayMinutes);
        $suspensionUntil = $effectiveAt->copy()->addHours($durationHours);

        $user->status = 'active'; // Grace period ma user active rehse.
        $user->suspension_status = 'scheduled';
        $user->suspension_reason = $reason;
        $user->suspension_effective_at = $effectiveAt;
        $user->suspension_until = $suspensionUntil;
        $user->suspension_duration_minutes = $durationHours * 60;
        $user->suspended_at = null;

        // A new suspension schedule clears any old block schedule.
        $user->block_status = null;
        $user->block_reason = null;
        $user->blocked_reason = null;
        $user->block_effective_at = null;
        $user->blocked_at = null;
        $user->save();

        AuditLogger::log(
            'schedule_suspend',
            'users',
            'User suspension scheduled.',
            [
                'user_id' => $id,
                'user_uid' => $userUid,
                'email' => $user->email ?? null,
                'reason' => $reason,
                'effective_at' => $effectiveAt->toDateTimeString(),
                'until' => $suspensionUntil->toDateTimeString(),
            ]
        );

        AdminNotificationController::createAndEmailForUser(
            $userUid,
            'warning',
            'Account Suspension Scheduled',
            'Your Kryvex account is scheduled for suspension after ' . $delayMinutes . ' minutes for ' . $durationHours . ' hour(s). Reason: ' . $reason,
            [
                'source' => 'admin_user_status',
                'status' => 'suspended',
                'reason' => $reason,
                'effective_at' => $effectiveAt->toIso8601String(),
                'until' => $suspensionUntil->toIso8601String(),
                'delay_minutes' => $delayMinutes,
                'duration_hours' => $durationHours,
                'admin' => session('admin_email') ?? 'admin',
            ]
        );

        return back()->with(
            'success',
            'User suspension scheduled. Notification and email sent. It will start after ' . $delayMinutes . ' minutes.'
        );
    }

    public function pendingKyc(Request $request)
    {
        $search = strtolower(trim((string) $request->query('search')));

        $allUsers = User::query()->orderByDesc('created_at')->get();

        // Filter users whose KYC status is not verified or approved
        $unverifiedUsers = $allUsers->filter(function ($user) {
            $status = strtolower((string) ($user->kyc_status ?? 'unverified'));
            return !in_array($status, ['verified', 'approved'], true);
        });

        $verifiedCount = $allUsers->count() - $unverifiedUsers->count();
        $kycRate = $allUsers->count() > 0 
            ? round(($verifiedCount / $allUsers->count()) * 100, 1) 
            : 0;

        $stats = [
            'total_users' => $allUsers->count(),
            'pending_kyc' => $unverifiedUsers->count(),
            'verified' => $verifiedCount,
            'kyc_rate' => $kycRate,
        ];

        $filtered = $unverifiedUsers->filter(function ($user) use ($search) {
            if ($search === '') return true;

            $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');

            return str_contains(strtolower(implode(' ', [
                $user->name ?? '',
                $user->email ?? '',
                $user->client_id ?? '',
                $userUid,
            ])), $search);
        })->values();

        $rows = $filtered->map(function ($user) {
            $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');
            $joinedAt = $user->created_at ?? null;
            $daysUnverified = $joinedAt ? Carbon::parse($joinedAt)->diffInDays(now()) : 0;

            return (object) [
                'id' => (string) ($user->_id ?? $user->id ?? $userUid),
                'user_uid' => $userUid,
                'client_id' => $user->client_id ?? '-',
                'name' => $user->name ?? 'Unnamed User',
                'email' => $user->email ?? '-',
                'kyc_status' => $user->kyc_status ?? 'unverified',
                'joined_at' => $joinedAt,
                'days_unverified' => $daysUnverified,
            ];
        });

        $paginatedUsers = \App\Support\AdminPaginator::paginate($rows, $request, 10);

        return view('admin.users.pending_kyc', compact('paginatedUsers', 'stats', 'search'));
    }

    public function pendingDeposits(Request $request)
    {
        $search = strtolower(trim((string) $request->query('search')));

        $allUsers = User::query()->orderByDesc('created_at')->get();
        $kycVerifications = DB::connection('mongodb')->table('user_kyc_verifications')->get()->keyBy('user_uid');

        $verifiedUsers = $allUsers->filter(function ($user) {
            $status = strtolower((string) ($user->kyc_status ?? 'unverified'));
            return in_array($status, ['verified', 'approved'], true);
        });

        // Filter users who have 0 successful deposits
        $pendingDepositUsers = $verifiedUsers->filter(function ($user) {
            $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');
            return $this->getUserTotalDepositsCount($userUid) === 0;
        });

        $convertedCount = $verifiedUsers->count() - $pendingDepositUsers->count();
        $conversionRate = $verifiedUsers->count() > 0 
            ? round(($convertedCount / $verifiedUsers->count()) * 100, 1) 
            : 0;

        $stats = [
            'total_verified' => $verifiedUsers->count(),
            'pending_deposits' => $pendingDepositUsers->count(),
            'converted' => $convertedCount,
            'conversion_rate' => $conversionRate,
        ];

        $filtered = $pendingDepositUsers->filter(function ($user) use ($search, $kycVerifications) {
            if ($search === '') return true;

            $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');
            $kyc = $kycVerifications->get($userUid);

            return str_contains(strtolower(implode(' ', [
                $user->name ?? '',
                $user->email ?? '',
                $user->client_id ?? '',
                $userUid,
                $kyc->aadhaar_name ?? '',
                $kyc->pan_name ?? '',
            ])), $search);
        })->values();

        $rows = $filtered->map(function ($user) use ($kycVerifications) {
            $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');
            $kyc = $kycVerifications->get($userUid);

            $kycDate = $user->kyc_verified_at 
                ?? $user->updated_at 
                ?? ($kyc->verified_at ?? ($kyc->updated_at ?? null));

            $daysSinceKyc = $kycDate ? Carbon::parse($kycDate)->diffInDays(now()) : 0;

            return (object) [
                'id' => (string) ($user->_id ?? $user->id ?? $userUid),
                'user_uid' => $userUid,
                'client_id' => $user->client_id ?? '-',
                'name' => $user->name ?? 'Unnamed User',
                'email' => $user->email ?? '-',
                'joined_at' => $user->created_at ?? null,
                'kyc_verified_at' => $kycDate,
                'days_since_kyc' => $daysSinceKyc,
                'aadhaar_name' => $kyc->aadhaar_name ?? '-',
                'pan_number' => 'Verified',
            ];
        });

        $paginatedUsers = \App\Support\AdminPaginator::paginate($rows, $request, 10);

        return view('admin.users.pending_deposits', compact('paginatedUsers', 'stats', 'search'));
    }

    private function getUserTotalDepositsCount(string $userUid): int
    {
        $ledgerCount = DB::connection('mongodb')->table('wallet_ledger')
            ->where(function ($q) use ($userUid) {
                $q->where('user_uid', $userUid)->orWhere('user_id', $userUid);
            })
            ->whereIn('type', ['deposit', 'admin_deposit', 'credit', 'inr_deposit', 'usdt_deposit'])
            ->count();

        if ($ledgerCount > 0) return $ledgerCount;

        $approvedReqs = DB::connection('mongodb')->table('inr_deposit_requests')
            ->where(function ($q) use ($userUid) {
                $q->where('user_uid', $userUid)->orWhere('user_id', $userUid);
            })
            ->where('status', 'approved')
            ->count();

        if ($approvedReqs > 0) return $approvedReqs;

        $wallet = DB::connection('mongodb')->table('wallets')
            ->where(function ($q) use ($userUid) {
                $q->where('user_uid', $userUid)->orWhere('user_id', $userUid);
            })
            ->first();

        if ($wallet) {
            $mainBal = (float) ($wallet->main_balance ?? 0) + (float) ($wallet->main_balance_inr ?? 0);
            $deposited = (float) ($wallet->total_deposited ?? 0) + (float) ($wallet->deposited_amount ?? 0);
            if ($mainBal > 0 || $deposited > 0) return 1;
        }

        return 0;
    }

    public function sendDepositReminder(Request $request, ?string $userUid = null)
    {
        if ($userUid && $userUid !== 'all') {
            $user = User::where('uid', $userUid)->orWhere('_id', $userUid)->orWhere('id', $userUid)->first();
            if (!$user) {
                return back()->with('error', 'User not found.');
            }
            $targetUsers = collect([$user]);
        } else {
            $allUsers = User::query()->get();
            $targetUsers = $allUsers->filter(function ($u) {
                $status = strtolower((string) ($u->kyc_status ?? 'unverified'));
                $userUid = (string) ($u->uid ?? $u->_id ?? $u->id ?? '');
                return in_array($status, ['verified', 'approved'], true) && $this->getUserTotalDepositsCount($userUid) === 0;
            });
        }

        $sentCount = 0;
        foreach ($targetUsers as $targetUser) {
            $email = (string) ($targetUser->email ?? '');
            if (empty($email)) continue;

            $this->sendFirstDepositEmail($targetUser);
            $sentCount++;
        }

        return back()->with('success', "First deposit reminder email sent to {$sentCount} user(s) successfully!");
    }

    public function sendKycReminder(Request $request, ?string $userUid = null)
    {
        if ($userUid && $userUid !== 'all') {
            $user = User::where('uid', $userUid)->orWhere('_id', $userUid)->orWhere('id', $userUid)->first();
            if (!$user) {
                return back()->with('error', 'User not found.');
            }
            $targetUsers = collect([$user]);
        } else {
            $allUsers = User::query()->get();
            $targetUsers = $allUsers->filter(function ($u) {
                $status = strtolower((string) ($u->kyc_status ?? 'unverified'));
                return !in_array($status, ['verified', 'approved'], true);
            });
        }

        $sentCount = 0;
        foreach ($targetUsers as $targetUser) {
            $email = (string) ($targetUser->email ?? '');
            if (empty($email)) continue;

            $this->sendKycCompletionEmail($targetUser);
            $sentCount++;
        }

        return back()->with('success', "KYC verification reminder email sent to {$sentCount} user(s) successfully!");
    }

    private function sendFirstDepositEmail(User $user): void
    {
        $userEmail = (string) ($user->email ?? '');
        $userName = (string) ($user->name ?? 'Trader');
        if (empty($userEmail)) return;

        $subject = 'Kryvex - Make Your First Deposit & Start Trading!';
        $depositUrl = url('/wallet');

        $html = '
        <div style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; background-color: #0b0e14; margin: 0; padding: 40px 10px;">
            <div style="max-width: 580px; margin: 0 auto; background: #161b22; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                <div style="background: linear-gradient(135deg, #1f293d 0%, #11151c 100%); padding: 32px 28px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <div style="display: inline-block; background: #f4c542; color: #0b0e14; font-weight: 900; font-size: 22px; width: 44px; height: 44px; line-height: 44px; border-radius: 10px; margin-bottom: 12px;">K</div>
                    <h1 style="color: #ffffff; font-size: 22px; font-weight: 800; margin: 0; letter-spacing: -0.5px;">Kryvex Exchange</h1>
                    <p style="color: #9ca3af; font-size: 13px; margin: 4px 0 0 0;">First Deposit & Live Trading Reminder</p>
                </div>
                
                <div style="padding: 32px 28px;">
                    <div style="display: inline-block; background: rgba(244, 197, 66, 0.15); border: 1px solid rgba(244, 197, 66, 0.4); color: #f4c542; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px;">
                        🚀 Account Ready For Deposit
                    </div>
                    
                    <h2 style="color: #ffffff; font-size: 18px; font-weight: 700; margin: 0 0 14px 0;">Hello ' . e($userName) . ',</h2>
                    <p style="color: #cbd5e1; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
                        Your Kryvex identity verification (KYC) is <strong style="color: #4ade80;">Fully Verified</strong>, but you haven\'t added funds to your trading wallet yet!
                    </p>
                    <p style="color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                        Deposit funds to your Kryvex account today via Instant UPI / INR or USDT Crypto and start trading Futures & Options with up to 100x leverage and high liquidity.
                    </p>
                    
                    <div style="background: rgba(255, 255, 255, 0.04); border-radius: 12px; padding: 20px; margin-bottom: 28px; border: 1px solid rgba(255, 255, 255, 0.06);">
                        <div style="color: #ffffff; font-weight: 700; font-size: 14px; margin-bottom: 12px;">⚡ Why deposit on Kryvex today?</div>
                        <div style="font-size: 13px; color: #cbd5e1; margin-bottom: 8px;">✓ <strong>Instant INR Funding:</strong> Fast & secure UPI / Netbanking</div>
                        <div style="font-size: 13px; color: #cbd5e1; margin-bottom: 8px;">✓ <strong>USDT Crypto Wallet:</strong> Zero-fee crypto transfers</div>
                        <div style="font-size: 13px; color: #cbd5e1;">✓ <strong>Live Trading:</strong> Futures, Options & Spot markets</div>
                    </div>
                    
                    <div style="text-align: center;">
                        <a href="' . $depositUrl . '" style="display: inline-block; background: #f4c542; color: #0b0e14; font-weight: 800; font-size: 14px; text-decoration: none; padding: 14px 36px; border-radius: 8px; box-shadow: 0 4px 16px rgba(244, 197, 66, 0.35);">
                            Make Your First Deposit Now
                        </a>
                    </div>
                </div>
                
                <div style="background: #0f131a; padding: 20px 28px; text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.06);">
                    <p style="color: #64748b; font-size: 12px; margin: 0 0 6px 0;">Need assistance? Contact Kryvex 24/7 Support Team.</p>
                    <p style="color: #475569; font-size: 11px; margin: 0;">&copy; ' . date('Y') . ' Kryvex. All rights reserved.</p>
                </div>
            </div>
        </div>';

        try {
            Mail::html($html, function ($message) use ($userEmail, $subject) {
                $message->to($userEmail)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send First Deposit reminder email: ' . $e->getMessage());
        }
    }

    private function sendKycCompletionEmail(User $user): void
    {
        $userEmail = (string) ($user->email ?? '');
        $userName = (string) ($user->name ?? 'User');
        if (empty($userEmail)) return;

        $subject = 'Kryvex - Complete Your Identity Verification (KYC)';
        $kycUrl = url('/settings?tab=kycSection');

        $html = '
        <div style="font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; background-color: #0b0e14; margin: 0; padding: 40px 10px;">
            <div style="max-width: 580px; margin: 0 auto; background: #161b22; border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
                <div style="background: linear-gradient(135deg, #1f293d 0%, #11151c 100%); padding: 32px 28px; text-align: center; border-bottom: 1px solid rgba(255, 255, 255, 0.08);">
                    <div style="display: inline-block; background: #f4c542; color: #0b0e14; font-weight: 900; font-size: 22px; width: 44px; height: 44px; line-height: 44px; border-radius: 10px; margin-bottom: 12px;">K</div>
                    <h1 style="color: #ffffff; font-size: 22px; font-weight: 800; margin: 0; letter-spacing: -0.5px;">Kryvex Exchange</h1>
                    <p style="color: #9ca3af; font-size: 13px; margin: 4px 0 0 0;">Identity Verification Reminder</p>
                </div>
                
                <div style="padding: 32px 28px;">
                    <div style="display: inline-block; background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.4); color: #60a5fa; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 20px;">
                        ⚠️ Action Required: Complete KYC
                    </div>
                    
                    <h2 style="color: #ffffff; font-size: 18px; font-weight: 700; margin: 0 0 14px 0;">Hello ' . e($userName) . ',</h2>
                    <p style="color: #cbd5e1; font-size: 14px; line-height: 1.6; margin: 0 0 20px 0;">
                        Thank you for joining Kryvex Exchange! To start trading live, deposit funds, and make withdrawals, please complete your quick identity verification (KYC).
                    </p>
                    <p style="color: #94a3b8; font-size: 14px; line-height: 1.6; margin: 0 0 24px 0;">
                        Verification takes less than 2 minutes via DigiLocker. Secure your account today to unlock full trading access.
                    </p>
                    
                    <div style="text-align: center;">
                        <a href="' . $kycUrl . '" style="display: inline-block; background: #f4c542; color: #0b0e14; font-weight: 800; font-size: 14px; text-decoration: none; padding: 14px 36px; border-radius: 8px; box-shadow: 0 4px 16px rgba(244, 197, 66, 0.35);">
                            Complete KYC Verification Now
                        </a>
                    </div>
                </div>
                
                <div style="background: #0f131a; padding: 20px 28px; text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.06);">
                    <p style="color: #64748b; font-size: 12px; margin: 0 0 6px 0;">Questions? Contact Kryvex Support team.</p>
                    <p style="color: #475569; font-size: 11px; margin: 0;">&copy; ' . date('Y') . ' Kryvex. All rights reserved.</p>
                </div>
            </div>
        </div>';

        try {
            Mail::html($html, function ($message) use ($userEmail, $subject) {
                $message->to($userEmail)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Failed to send KYC completion reminder email: ' . $e->getMessage());
        }
    }

    /**
     * Account Deletion Requests & Deleted Users dedicated page
     */
    public function deletionRequests(Request $request)
    {
        $search = strtolower(trim((string) $request->query('search')));
        $filter = strtolower(trim((string) $request->query('filter', 'pending'))); // 'pending', 'approved', 'rejected', 'all'

        $allUsers = User::query()->orderByDesc('deletion_requested_at')->orderByDesc('updated_at')->get();

        // Target users with deletion activity
        $deletionUsers = $allUsers->filter(function ($user) {
            $delStatus = strtolower((string) ($user->deletion_status ?? ''));
            $userStatus = strtolower((string) ($user->status ?? ''));
            return in_array($delStatus, ['pending', 'approved', 'rejected'], true) || $userStatus === 'deleted';
        });

        $pendingCount = $deletionUsers->filter(fn ($u) => strtolower((string) ($u->deletion_status ?? '')) === 'pending')->count();
        $approvedCount = $deletionUsers->filter(fn ($u) => strtolower((string) ($u->deletion_status ?? '')) === 'approved' || strtolower((string) ($u->status ?? '')) === 'deleted')->count();
        $rejectedCount = $deletionUsers->filter(fn ($u) => strtolower((string) ($u->deletion_status ?? '')) === 'rejected')->count();
        $totalCount = $deletionUsers->count();

        $stats = [
            'pending'  => $pendingCount,
            'approved' => $approvedCount,
            'rejected' => $rejectedCount,
            'total'    => $totalCount,
        ];

        // Apply tab filter
        if ($filter === 'pending') {
            $deletionUsers = $deletionUsers->filter(fn ($u) => strtolower((string) ($u->deletion_status ?? '')) === 'pending');
        } elseif ($filter === 'approved') {
            $deletionUsers = $deletionUsers->filter(fn ($u) => strtolower((string) ($u->deletion_status ?? '')) === 'approved' || strtolower((string) ($u->status ?? '')) === 'deleted');
        } elseif ($filter === 'rejected') {
            $deletionUsers = $deletionUsers->filter(fn ($u) => strtolower((string) ($u->deletion_status ?? '')) === 'rejected');
        }

        // Apply search filter
        if ($search !== '') {
            $deletionUsers = $deletionUsers->filter(function ($user) use ($search) {
                $name = strtolower((string) ($user->name ?? ''));
                $email = strtolower((string) ($user->email ?? ''));
                $clientId = strtolower((string) ($user->client_id ?? ''));
                $uid = strtolower((string) ($user->uid ?? ''));
                return str_contains($name, $search) || str_contains($email, $search) || str_contains($clientId, $search) || str_contains($uid, $search);
            });
        }

        // Build row items
        $rows = $deletionUsers->map(function ($user) {
            $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');

            // Get user's wallet balances
            $wallets = DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->get();
            $realBal = 0.0;
            $demoBal = 0.0;
            foreach ($wallets as $w) {
                $wArr = (array) $w;
                $realBal += (float) ($wArr['real_available_balance'] ?? 0.0) + (float) ($wArr['real_locked_balance'] ?? 0.0);
                $demoBal += (float) ($wArr['demo_available_balance'] ?? 0.0) + (float) ($wArr['demo_locked_balance'] ?? 0.0);
            }

            $delStatus = strtolower((string) ($user->deletion_status ?? ''));
            if ($delStatus === '' && strtolower((string) ($user->status ?? '')) === 'deleted') {
                $delStatus = 'approved';
            }

            return (object) [
                'id'                    => (string) ($user->_id ?? $user->id ?? $userUid),
                'user_uid'              => $userUid,
                'client_id'             => $user->client_id ?? '-',
                'name'                  => $user->name ?? 'Unnamed User',
                'email'                 => $user->email ?? '-',
                'avatar'                => $user->avatar ?? null,
                'status'                => $user->status ?? 'active',
                'deletion_status'       => $delStatus ?: 'pending',
                'deletion_reason'       => $user->deletion_reason ?? 'User requested account closure.',
                'deletion_requested_at' => $user->deletion_requested_at ?? $user->updated_at ?? null,
                'deletion_approved_at'  => $user->deletion_approved_at ?? $user->deleted_at ?? null,
                'real_balance'          => $realBal,
                'demo_balance'          => $demoBal,
            ];
        });

        $paginatedUsers = \App\Support\AdminPaginator::paginate($rows, $request, 10);

        return view('admin.users.deletion_requests', compact('paginatedUsers', 'stats', 'search', 'filter'));
    }

    /**
     * Approve account deletion for user
     */
    public function approveDeletion(Request $request, string $id)
    {
        $user = User::where('_id', $id)->orWhere('id', $id)->orWhere('uid', $id)->first();
        if (!$user) {
            return redirect()->back()->with('error', 'User not found.');
        }

        $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');

        // Mark as deleted in users collection without hard-deleting the row
        $user->status = 'deleted';
        $user->deletion_status = 'approved';
        $user->deletion_approved_at = now();
        $user->deleted_at = now();
        $user->save();

        DB::connection('mongodb')->table('users')->where('uid', $userUid)->update([
            'status'               => 'deleted',
            'deletion_status'      => 'approved',
            'deletion_approved_at' => now(),
            'deleted_at'           => now(),
            'updated_at'           => now(),
        ]);

        // Create audit log
        DB::connection('mongodb')->table('audit_logs')->insert([
            'user_uid'   => $userUid,
            'action'     => 'admin_approved_account_deletion',
            'details'    => "Admin approved account deletion for user: {$user->email} ({$userUid})",
            'admin_id'   => session('admin_id') ?? 'admin',
            'created_at' => now(),
        ]);

        // Send Email to User
        if (!empty($user->email)) {
            try {
                Mail::to($user->email)->send(new \App\Mail\AccountDeletionStatusMail(
                    action: 'approved',
                    userName: (string) ($user->name ?? 'User'),
                    userEmail: (string) $user->email,
                    clientId: (string) ($user->client_id ?? ''),
                    reason: (string) ($user->deletion_reason ?? ''),
                    processedAt: now()->format('M d, Y h:i A T')
                ));
            } catch (\Throwable $e) {
                Log::error("Failed to send account deletion approval email to {$user->email}: " . $e->getMessage());
            }
        }

        // Send In-App Notification
        try {
            DB::connection('mongodb')->table('user_notifications')->insert([
                'user_uid'   => $userUid,
                'title'      => 'Account Deletion Request Approved',
                'message'    => 'Your account deletion request has been approved and processed by administration.',
                'type'       => 'account_deleted',
                'read_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to insert user notification for approved deletion: " . $e->getMessage());
        }

        return redirect()->back()->with('success', "Account deletion approved. User {$user->name} ({$user->email}) is now marked as deleted and a confirmation email has been sent.");
    }

    /**
     * Reject account deletion for user
     */
    public function rejectDeletion(Request $request, string $id)
    {
        $user = User::where('_id', $id)->orWhere('id', $id)->orWhere('uid', $id)->first();
        if (!$user) {
            return redirect()->back()->with('error', 'User not found.');
        }

        $userUid = (string) ($user->uid ?? $user->_id ?? $user->id ?? '');
        $rejectReason = trim((string) $request->input('reject_reason', 'Rejected by administrator'));

        $user->deletion_status = 'rejected';
        $user->deletion_rejected_at = now();
        $user->deletion_reject_reason = $rejectReason;
        if ($user->status === 'deleted' || $user->status === 'suspended') {
            $user->status = 'active';
        }
        $user->save();

        // Create audit log
        DB::connection('mongodb')->table('audit_logs')->insert([
            'user_uid'   => $userUid,
            'action'     => 'admin_rejected_account_deletion',
            'details'    => "Admin rejected account deletion for user: {$user->email}. Reason: {$rejectReason}",
            'admin_id'   => session('admin_id') ?? 'admin',
            'created_at' => now(),
        ]);

        // Send Email to User
        if (!empty($user->email)) {
            try {
                Mail::to($user->email)->send(new \App\Mail\AccountDeletionStatusMail(
                    action: 'rejected',
                    userName: (string) ($user->name ?? 'User'),
                    userEmail: (string) $user->email,
                    clientId: (string) ($user->client_id ?? ''),
                    reason: $rejectReason,
                    processedAt: now()->format('M d, Y h:i A T')
                ));
            } catch (\Throwable $e) {
                Log::error("Failed to send account deletion rejection email to {$user->email}: " . $e->getMessage());
            }
        }

        // Send In-App Notification
        try {
            DB::connection('mongodb')->table('user_notifications')->insert([
                'user_uid'   => $userUid,
                'title'      => 'Account Deletion Request Update',
                'message'    => 'Your account deletion request was rejected: ' . $rejectReason . '. Your account remains active.',
                'type'       => 'account_deletion_rejected',
                'read_at'    => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Failed to insert user notification for rejected deletion: " . $e->getMessage());
        }

        return redirect()->back()->with('success', "Account deletion request rejected. User remains active and an update email has been sent.");
    }
}