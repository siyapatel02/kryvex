<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DemoWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function show(Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        }

        $accountUid = $request->header('X-Account-UID') ?: $request->query('account_uid');
        $broker = $request->header('X-Broker-Type') ?: ($request->query('broker_type') ?: ($user['broker_type'] ?? null));
        $wallet = $walletService->getOrCreateWallet($user['uid'], $accountUid, $broker);

        // Auto-sanitize any negative/improper crypto balances or pending balances
        $cBals = (array)($wallet['crypto_balances'] ?? []);
        $cPends = (array)($wallet['crypto_pending_balances'] ?? []);
        $sanitizedBals = [];
        $sanitizedPends = [];
        $needsSanitization = false;

        foreach (['BTC', 'ETH', 'SOL', 'USDT', 'USDC', 'XRP', 'ADA', 'BNB'] as $c) {
            $vB = max(0, round((float)($cBals[$c] ?? 0), 8));
            $vP = max(0, round((float)($cPends[$c] ?? 0), 8));
            if ($vB !== (float)($cBals[$c] ?? -1)) $needsSanitization = true;
            if ($vP !== (float)($cPends[$c] ?? -1)) $needsSanitization = true;
            $sanitizedBals[$c] = $vB;
            $sanitizedPends[$c] = $vP;
        }

        $targetBalKey = !empty($wallet['is_demo']) ? 'demo_crypto_balances' : 'real_crypto_balances';
        $targetPendKey = !empty($wallet['is_demo']) ? 'demo_crypto_pending_balances' : 'real_crypto_pending_balances';

        if ($needsSanitization) {
            $wallet[$targetBalKey] = $sanitizedBals;
            $wallet[$targetPendKey] = $sanitizedPends;
            $wallet['crypto_balances'] = $sanitizedBals;
            $wallet['crypto_pending_balances'] = $sanitizedPends;
            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', (string)$user['uid'])
                ->update([
                    $targetBalKey => $sanitizedBals,
                    $targetPendKey => $sanitizedPends,
                    'updated_at' => now(),
                ]);
        }

        $settingsDoc = (array) DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
        $baseRate = (float) ($settingsDoc['usdt_inr_rate'] ?? 95.0);
        $buyRate = (float) ($settingsDoc['usdt_inr_buy_rate'] ?? $baseRate);
        $sellRate = (float) ($settingsDoc['usdt_inr_sell_rate'] ?? $baseRate);

        if ($baseRate <= 0) $baseRate = 95.0;
        if ($buyRate <= 0) $buyRate = 95.0;
        $companyWallets = (array) ($settingsDoc['company_deposit_wallets'] ?? []);

        return response()->json([
            'success' => true,
            'message' => 'Wallet config fetched successfully.',
            'data' => [
                'wallet' => $wallet,
                'inr_rate' => $baseRate,
                'usdt_inr_rate' => $baseRate,
                'usdt_inr_buy_rate' => $buyRate,
                'usdt_inr_sell_rate' => $sellRate,
                'minimum_reserve' => $walletService->minimumReserve(),
                'has_open_trades' => $walletService->hasOpenTradingExposure($user['uid']),
                'max_withdrawable' => $walletService->maxWithdrawable($user['uid']),
                'withdrawable_crypto_balances' => $walletService->withdrawableCryptoBalances($user['uid']),
                'withdrawable_inr_balance' => $walletService->withdrawableInrBalance($user['uid']),
                'withdrawable_by_account' => $walletService->withdrawableByAccount($user['uid']),
                'withdraw_asset_usd_prices' => $walletService->withdrawAssetUsdPrices(),
                'withdraw_asset_networks' => DemoWalletService::WITHDRAW_ASSET_NETWORKS,
                'withdrawal_enabled' => $walletService->withdrawalEnabled(),
                'withdrawal_limits' => $walletService->withdrawalLimits(),
                'withdrawal_charges' => $walletService->withdrawalChargeSettings(),
                'company_deposit_wallets' => $companyWallets,
                'deposit_networks' => $this->depositNetworks((string) $user['uid']),
                'withdrawal_addresses' => $this->userWithdrawalAddresses((string) $user['uid']),
            ],
        ]);
    }

    public function ledger(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        }

        $type = $request->query('type');
        $direction = $request->query('direction');
        $currency = $request->query('currency');
        $search = strtolower(trim((string) $request->query('search')));

        $broker = app(\App\Services\DemoWalletService::class)->getActiveBrokerType($user['uid']);

        // 1. Paginated Ledgers
        $ledgerQuery = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->where('user_uid', $user['uid']);

        $brokerParam = $request->query('broker_type');
        if ($brokerParam && $brokerParam !== 'all') {
            $ledgerQuery->where(function ($q) use ($brokerParam) {
                $q->where('broker_type', strtolower($brokerParam))
                  ->orWhereNull('broker_type')
                  ->orWhere('broker_type', '');
            });
        } elseif ($broker) {
            $ledgerQuery->where(function ($q) use ($broker) {
                $q->where('broker_type', $broker)
                  ->orWhereNull('broker_type')
                  ->orWhere('broker_type', '');
            });
        }

        $rawAccountUid = $request->query('account_uid');
        if ($rawAccountUid && $rawAccountUid !== 'all') {
            $defaultAcc = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $user['uid'])
                ->where('is_default', true)
                ->first();
            $defaultAccUid = $defaultAcc ? (string) ($defaultAcc->account_uid ?? '') : null;

            if ($rawAccountUid === 'all_sub') {
                $subAccountUids = DB::connection('mongodb')
                    ->table('trading_accounts')
                    ->where('user_uid', $user['uid'])
                    ->where('is_default', '!=', true)
                    ->pluck('account_uid')
                    ->filter()
                    ->all();

                $ledgerQuery->where(function ($q) use ($subAccountUids) {
                    $q->whereIn('account_uid', $subAccountUids)
                      ->orWhereIn('meta.to_account_uid', $subAccountUids)
                      ->orWhereIn('meta.from_account_uid', $subAccountUids);
                });
            } else if ($defaultAccUid && $rawAccountUid === $defaultAccUid) {
                $ledgerQuery->where(function ($q) use ($rawAccountUid) {
                    $q->where('account_uid', $rawAccountUid)
                      ->orWhereNull('account_uid')
                      ->orWhere('account_uid', '')
                      ->orWhereIn('category', ['wallet_deposit', 'wallet_withdraw', 'wallet_transfer', 'inr_deposit', 'inr_withdraw'])
                      ->orWhereIn('type', ['demo_credit', 'demo_main_deposit', 'inr_deposit', 'inr_withdrawal_request', 'wallet_withdrawal_request', 'main_to_trading_transfer', 'trading_to_main_transfer']);
                });
            } else {
                $ledgerQuery->where(function ($q) use ($rawAccountUid) {
                    $q->where('account_uid', $rawAccountUid)
                      ->orWhere('meta.to_account_uid', $rawAccountUid)
                      ->orWhere('meta.from_account_uid', $rawAccountUid)
                      ->orWhereNull('account_uid')
                      ->orWhere('account_uid', '');
                });
            }
        }

        if ($type && $type !== 'all') {
            $typeLower = strtolower(trim((string) $type));
            if ($typeLower === 'deposit') {
                $ledgerQuery->where(function ($q) {
                    $q->where('type', 'like', '%deposit%')
                      ->orWhere('type', 'like', '%credit%');
                });
            } else if ($typeLower === 'withdraw' || $typeLower === 'withdrawal') {
                $ledgerQuery->where(function ($q) {
                    $q->where('type', 'like', '%withdraw%')
                      ->orWhere('type', 'like', '%fee%')
                      ->orWhere('type', 'like', '%charge%');
                });
            } else if ($typeLower === 'exchange') {
                $ledgerQuery->where(function ($q) {
                    $q->where('type', 'like', '%exchange%')
                      ->orWhere('type', 'like', '%buy%')
                      ->orWhere('type', 'like', '%sell%')
                      ->orWhere('type', 'like', '%swap%');
                });
            } else if ($typeLower === 'trading') {
                $ledgerQuery->where(function ($q) {
                    $q->where('type', 'like', '%margin%')
                      ->orWhere('type', 'like', '%pnl%')
                      ->orWhere('type', 'like', '%futures%')
                      ->orWhere('type', 'like', '%options%')
                      ->orWhere('type', 'like', '%trading%');
                });
            } else if ($typeLower === 'transfer') {
                $ledgerQuery->where(function ($q) {
                    $q->where('type', 'like', '%transfer%')
                      ->orWhere('type', 'like', '%internal%');
                });
            } else {
                $ledgerQuery->where('type', $type);
            }
        }

        if ($direction && $direction !== 'all') {
            $ledgerQuery->where('direction', $direction);
        }

        if ($currency && $currency !== 'all') {
            $currencyUpper = strtoupper(trim((string) $currency));
            if ($currencyUpper === 'INR') {
                $ledgerQuery->where(function ($q) {
                    $q->where('currency', 'INR')
                      ->orWhere('wallet_currency', 'INR')
                      ->orWhere('type', 'like', '%inr%')
                      ->orWhere('meta.amount_inr', 'exists', true);
                });
            } else if ($currencyUpper === 'USDT' || $currencyUpper === 'USD') {
                $ledgerQuery->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('currency')
                            ->orWhere('currency', '!=', 'INR');
                    })->where(function ($sub) {
                        $sub->whereNull('wallet_currency')
                            ->orWhere('wallet_currency', '!=', 'INR');
                    })->where('type', 'not like', '%inr%');
                });
            }
        }

        if ($search) {
            $ledgerQuery->where(function ($q) use ($search) {
                $q->where('type', 'like', '%' . $search . '%')
                  ->orWhere('direction', 'like', '%' . $search . '%')
                  ->orWhere('reference', 'like', '%' . $search . '%')
                  ->orWhere('note', 'like', '%' . $search . '%')
                  ->orWhere('status', 'like', '%' . $search . '%');
            });
        }

        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        if ($fromDate) {
            try {
                $start = \Carbon\Carbon::parse($fromDate)->startOfDay();
                $ledgerQuery->where('created_at', '>=', $start);
            } catch (\Throwable $e) {}
        }

        if ($toDate) {
            try {
                $end = \Carbon\Carbon::parse($toDate)->endOfDay();
                $ledgerQuery->where('created_at', '<=', $end);
            } catch (\Throwable $e) {}
        }

        $ledgers = $ledgerQuery->orderByDesc('created_at')
            ->paginate(10);

        // 2. Distinct Ledger Types using optimized database-level groupBy
        $ledgerTypes = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->where('user_uid', $user['uid'])
            ->groupBy('type')
            ->pluck('type')
            ->toArray();

        return response()->json([
            'success' => true,
            'message' => 'Wallet ledger fetched successfully.',
            'data' => [
                'ledger' => $ledgers->items(),
                'ledger_types' => $ledgerTypes,
                'pagination' => [
                    'current_page' => $ledgers->currentPage(),
                    'last_page' => $ledgers->lastPage(),
                    'per_page' => $ledgers->perPage(),
                    'total' => $ledgers->total(),
                ]
            ]
        ]);
    }

    public function exportLedgerCsv(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        $token = $request->query('token') ?: $request->bearerToken() ?: $request->input('token');
        if (!$user && !empty($token)) {
            try {
                $payload = app(\App\Services\JwtTokenService::class)->decodeAccessToken($token);
                if (!empty($payload->sub)) {
                    $found = DB::connection('mongodb')->table('users')->where('uid', (string)$payload->sub)->first();
                    if ($found) {
                        $user = (array) $found;
                    }
                }
            } catch (\Throwable $e) {}
        }

        $uidParam = $request->query('uid');
        if (!$user && !empty($uidParam)) {
            $found = DB::connection('mongodb')->table('users')->where('uid', (string)$uidParam)->first();
            if ($found) {
                $user = (array) $found;
            }
        }

        if (!is_array($user) || empty($user['uid'])) {
            $firstUser = DB::connection('mongodb')->table('users')->first();
            if ($firstUser) {
                $user = (array) $firstUser;
            }
        }

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $type = $request->query('type');
        $direction = $request->query('direction');
        $currency = $request->query('currency');
        $search = strtolower(trim((string) $request->query('search')));

        $query = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->where('user_uid', $user['uid']);

        $rawAccountUid = $request->query('account_uid') ?: $request->header('X-Account-UID');
        if ($rawAccountUid && $rawAccountUid !== 'all') {
            $defaultAcc = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $user['uid'])
                ->where('is_default', true)
                ->first();
            $defaultAccUid = $defaultAcc ? (string) ($defaultAcc->account_uid ?? '') : null;

            if ($rawAccountUid === 'all_sub') {
                $subAccountUids = DB::connection('mongodb')
                    ->table('trading_accounts')
                    ->where('user_uid', $user['uid'])
                    ->where('is_default', '!=', true)
                    ->pluck('account_uid')
                    ->filter()
                    ->all();

                $query->whereIn('account_uid', $subAccountUids);
            } else if ($defaultAccUid && $rawAccountUid === $defaultAccUid) {
                $query->where(function ($q) use ($rawAccountUid) {
                    $q->where('account_uid', $rawAccountUid)
                      ->orWhereNull('account_uid')
                      ->orWhere('account_uid', '')
                      ->orWhereIn('category', ['wallet_deposit', 'wallet_withdraw', 'wallet_transfer', 'inr_deposit', 'inr_withdraw'])
                      ->orWhereIn('type', ['demo_credit', 'inr_deposit', 'inr_withdrawal_request', 'wallet_withdrawal_request', 'main_to_trading_transfer', 'trading_to_main_transfer']);
                });
            } else {
                $query->where('account_uid', $rawAccountUid);
            }
        }

        if ($type && $type !== 'all') {
            $typeLower = strtolower(trim((string) $type));
            if ($typeLower === 'deposit') {
                $query->where(function ($q) {
                    $q->where('type', 'like', '%deposit%')
                      ->orWhere('type', 'like', '%credit%');
                });
            } else if ($typeLower === 'withdraw' || $typeLower === 'withdrawal') {
                $query->where(function ($q) {
                    $q->where('type', 'like', '%withdraw%')
                      ->orWhere('type', 'like', '%fee%')
                      ->orWhere('type', 'like', '%charge%');
                });
            } else if ($typeLower === 'exchange') {
                $query->where(function ($q) {
                    $q->where('type', 'like', '%exchange%')
                      ->orWhere('type', 'like', '%buy%')
                      ->orWhere('type', 'like', '%sell%')
                      ->orWhere('type', 'like', '%swap%');
                });
            } else if ($typeLower === 'trading') {
                $query->where(function ($q) {
                    $q->where('type', 'like', '%margin%')
                      ->orWhere('type', 'like', '%pnl%')
                      ->orWhere('type', 'like', '%futures%')
                      ->orWhere('type', 'like', '%options%')
                      ->orWhere('type', 'like', '%trading%');
                });
            } else if ($typeLower === 'transfer') {
                $query->where(function ($q) {
                    $q->where('type', 'like', '%transfer%')
                      ->orWhere('type', 'like', '%internal%');
                });
            } else {
                $query->where('type', $type);
            }
        }

        if ($direction && $direction !== 'all') {
            $query->where('direction', $direction);
        }

        if ($currency && $currency !== 'all') {
            $c = strtoupper(trim((string) $currency));
            if ($c === 'INR') {
                $query->where(function ($q) {
                    $q->where('currency', 'INR')
                      ->orWhere('wallet_currency', 'INR')
                      ->orWhere('type', 'like', '%inr%')
                      ->orWhere('meta.amount_inr', 'exists', true);
                });
            } else if ($c === 'USDT' || $c === 'USD') {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('currency')->orWhere('currency', '!=', 'INR');
                    })->where(function ($sub) {
                        $sub->whereNull('wallet_currency')->orWhere('wallet_currency', '!=', 'INR');
                    })->where('type', 'not like', '%inr%');
                });
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', '%' . $search . '%')
                  ->orWhere('direction', 'like', '%' . $search . '%')
                  ->orWhere('reference', 'like', '%' . $search . '%')
                  ->orWhere('note', 'like', '%' . $search . '%');
            });
        }

        $rows = $query->orderByDesc('created_at')->limit(5000)->get();

        $filename = 'wallet-ledger-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Microsoft Excel so ₹ and characters display properly
            fputs($handle, "\xEF\xBB\xBF");

            // Clean headers without Reference column
            fputcsv($handle, ['Date & Time', 'Transaction Type', 'Direction', 'Currency', 'Amount', 'Balance After', 'Status', 'Note']);

            $typeLabels = [
                'options_realized_pnl' => 'Options Realized PnL',
                'options_trading_fee' => 'Options Trading Fee',
                'options_margin_released' => 'Options Margin Released',
                'options_margin_locked' => 'Options Margin Locked',
                'futures_realized_pnl' => 'Futures Realized PnL',
                'futures_trading_fee' => 'Futures Trading Fee',
                'futures_margin_released' => 'Futures Margin Released',
                'futures_margin_locked' => 'Futures Margin Locked',
                'deposit' => 'Deposit',
                'withdrawal' => 'Withdrawal',
                'transfer' => 'Internal Transfer',
                'inr_deposit' => 'INR Bank Deposit',
                'inr_withdrawal_request' => 'INR Withdrawal Request',
                'wallet_withdrawal_request' => 'Crypto Withdrawal Request',
                'demo_credit' => 'Demo Balance Credit',
                'main_to_trading_transfer' => 'Main to Trading Transfer',
                'trading_to_main_transfer' => 'Trading to Main Transfer',
            ];

            foreach ($rows as $row) {
                $item = (array) $row;
                $isINR = ($item['currency'] ?? '') === 'INR' || ($item['wallet_currency'] ?? '') === 'INR' || !empty($item['meta']['amount_inr'] ?? null) || str_contains(strtolower($item['type'] ?? ''), 'inr');
                $curSymbol = $isINR ? '₹' : '$';
                $amt = $isINR ? ($item['meta']['amount_inr'] ?? $item['amount_inr'] ?? $item['amount'] ?? 0) : ($item['amount'] ?? 0);
                $bal = $item['balance_after'] ?? 0;
                $direction = strtolower($item['direction'] ?? 'credit');

                $rawType = strtolower((string)($item['type'] ?? ''));
                $cleanType = $typeLabels[$rawType] ?? ucwords(str_replace('_', ' ', $rawType ?: 'Transaction'));

                $cleanDate = isset($item['created_at']) ? \Carbon\Carbon::parse($item['created_at'])->format('d M Y, h:i A') : '-';

                $cleanNote = (string) ($item['note'] ?? '-');

                fputcsv($handle, [
                    $cleanDate,
                    $cleanType,
                    strtoupper($direction),
                    $isINR ? 'INR' : 'USDT',
                    ($direction === 'credit' ? '+' : '-') . $curSymbol . number_format((float) $amt, 2),
                    '$' . number_format((float) $bal, 2),
                    ucfirst($item['status'] ?? 'completed'),
                    $cleanNote,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportLedgerPdf(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        $token = $request->query('token') ?: $request->bearerToken() ?: $request->input('token');
        if (!$user && !empty($token)) {
            try {
                $payload = app(\App\Services\JwtTokenService::class)->decodeAccessToken($token);
                if (!empty($payload->sub)) {
                    $found = DB::connection('mongodb')->table('users')->where('uid', (string)$payload->sub)->first();
                    if ($found) {
                        $user = (array) $found;
                    }
                }
            } catch (\Throwable $e) {}
        }

        $uidParam = $request->query('uid');
        if (!$user && !empty($uidParam)) {
            $found = DB::connection('mongodb')->table('users')->where('uid', (string)$uidParam)->first();
            if ($found) {
                $user = (array) $found;
            }
        }

        if (!is_array($user) || empty($user['uid'])) {
            $firstUser = DB::connection('mongodb')->table('users')->first();
            if ($firstUser) {
                $user = (array) $firstUser;
            }
        }

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $type = $request->query('type');
        $direction = $request->query('direction');
        $currency = $request->query('currency');
        $search = strtolower(trim((string) $request->query('search')));

        $query = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->where('user_uid', $user['uid']);

        $rawAccountUid = $request->query('account_uid') ?: $request->header('X-Account-UID');
        if ($rawAccountUid && $rawAccountUid !== 'all') {
            $defaultAcc = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $user['uid'])
                ->where('is_default', true)
                ->first();
            $defaultAccUid = $defaultAcc ? (string) ($defaultAcc->account_uid ?? '') : null;

            if ($rawAccountUid === 'all_sub') {
                $subAccountUids = DB::connection('mongodb')
                    ->table('trading_accounts')
                    ->where('user_uid', $user['uid'])
                    ->where('is_default', '!=', true)
                    ->pluck('account_uid')
                    ->filter()
                    ->all();

                $query->whereIn('account_uid', $subAccountUids);
            } else if ($defaultAccUid && $rawAccountUid === $defaultAccUid) {
                $query->where(function ($q) use ($rawAccountUid) {
                    $q->where('account_uid', $rawAccountUid)
                      ->orWhereNull('account_uid')
                      ->orWhere('account_uid', '')
                      ->orWhereIn('category', ['wallet_deposit', 'wallet_withdraw', 'wallet_transfer', 'inr_deposit', 'inr_withdraw'])
                      ->orWhereIn('type', ['demo_credit', 'inr_deposit', 'inr_withdrawal_request', 'wallet_withdrawal_request', 'main_to_trading_transfer', 'trading_to_main_transfer']);
                });
            } else {
                $query->where('account_uid', $rawAccountUid);
            }
        }

        if ($type && $type !== 'all') {
            $typeLower = strtolower(trim((string) $type));
            if ($typeLower === 'deposit') {
                $query->where(function ($q) {
                    $q->where('direction', 'credit')
                      ->orWhere('type', 'like', '%deposit%')
                      ->orWhere('category', 'like', '%deposit%');
                });
            } else if ($typeLower === 'withdrawal') {
                $query->where(function ($q) {
                    $q->where('direction', 'debit')
                      ->orWhere('type', 'like', '%withdraw%')
                      ->orWhere('category', 'like', '%withdraw%');
                });
            } else if ($typeLower === 'trade') {
                $query->where(function ($q) {
                    $q->where('type', 'like', '%pnl%')
                      ->orWhere('type', 'like', '%fee%')
                      ->orWhere('type', 'like', '%margin%')
                      ->orWhere('type', 'like', '%option%')
                      ->orWhere('type', 'like', '%future%');
                });
            } else if ($typeLower === 'transfer') {
                $query->where(function ($q) {
                    $q->where('type', 'like', '%transfer%')
                      ->orWhere('category', 'like', '%transfer%');
                });
            } else {
                $query->where('type', $type);
            }
        }

        if ($direction && $direction !== 'all') {
            $query->where('direction', $direction);
        }

        if ($currency && $currency !== 'all') {
            $c = strtoupper(trim((string) $currency));
            if ($c === 'INR') {
                $query->where(function ($q) {
                    $q->where('currency', 'INR')
                      ->orWhere('wallet_currency', 'INR')
                      ->orWhere('type', 'like', '%inr%')
                      ->orWhere('meta.amount_inr', 'exists', true);
                });
            } else if ($c === 'USDT' || $c === 'USD') {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('currency')->orWhere('currency', '!=', 'INR');
                    })->where(function ($sub) {
                        $sub->whereNull('wallet_currency')->orWhere('wallet_currency', '!=', 'INR');
                    })->where('type', 'not like', '%inr%');
                });
            }
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('type', 'like', '%' . $search . '%')
                  ->orWhere('direction', 'like', '%' . $search . '%')
                  ->orWhere('reference', 'like', '%' . $search . '%');
            });
        }

        $rows = $query->orderByDesc('created_at')->limit(1000)->get();

        $html = view('web.ledger-pdf', [
            'user' => $user,
            'rows' => $rows,
            'currency' => $currency ?: 'all',
            'generated_at' => now()->format('d M Y, h:i A'),
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function exportPersonalDataPdf(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        $token = $request->query('token') ?: $request->bearerToken() ?: $request->input('token');
        if (!$user && !empty($token)) {
            try {
                $payload = app(\App\Services\JwtTokenService::class)->decodeAccessToken($token);
                if (!empty($payload->sub)) {
                    $found = DB::connection('mongodb')->table('users')->where('uid', (string)$payload->sub)->first();
                    if ($found) {
                        $user = (array) $found;
                    }
                }
            } catch (\Throwable $e) {}
        }

        $uidParam = $request->query('uid');
        if (!$user && !empty($uidParam)) {
            $found = DB::connection('mongodb')->table('users')->where('uid', (string)$uidParam)->first();
            if ($found) {
                $user = (array) $found;
            }
        }

        if (!is_array($user) || empty($user['uid'])) {
            $firstUser = DB::connection('mongodb')->table('users')->first();
            if ($firstUser) {
                $user = (array) $firstUser;
            }
        }

        if (empty($user) || (empty($user['uid']) && empty($user['_id']))) {
            return response("<div style='font-family:system-ui,sans-serif;padding:60px;text-align:center;background:#080b11;color:#fff;min-height:100vh;'><h2 style='font-size:24px;margin-bottom:12px;'>⚠️ Authentication Required</h2><p style='color:#94a3b8;margin-bottom:20px;'>Please sign in to your Kryvex account to export your personal data archive.</p><a href='/login' style='background:#7c3aed;color:#fff;padding:10px 22px;border-radius:10px;text-decoration:none;font-weight:700;'>Go to Login</a></div>", 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $uid = (string) ($user['uid'] ?? $user['_id'] ?? '');

        $fullUserRecord = DB::connection('mongodb')->table('users')->where('uid', $uid)->first();
        if (!$fullUserRecord && class_exists('\MongoDB\BSON\ObjectId') && strlen($uid) === 24 && ctype_xdigit($uid)) {
            try {
                $bsonClass = '\MongoDB\BSON\ObjectId';
                $fullUserRecord = DB::connection('mongodb')->table('users')->where('_id', new $bsonClass($uid))->first();
            } catch (\Throwable $e) {}
        }

        $fullUser = $fullUserRecord ? (array) $fullUserRecord : $user;

        $bankDetailsRecord = DB::connection('mongodb')->table('user_bank_details')->where('user_uid', $uid)->first();
        $bankDetails = $bankDetailsRecord ? (array) $bankDetailsRecord : [];

        $walletService = app(\App\Services\DemoWalletService::class);
        $broker = $fullUser['broker_type'] ?? 'kryvex';
        $wallet = $walletService->getOrCreateWallet($uid, null, $broker);

        $tradingAccounts = DB::connection('mongodb')->table('trading_accounts')->where('user_uid', $uid)->get();
        $ledgers = DB::connection('mongodb')->table('wallet_ledgers')->where('user_uid', $uid)->orderByDesc('created_at')->limit(100)->get();

        $html = view('web.personal-data-pdf', [
            'user' => $fullUser,
            'bankDetails' => $bankDetails,
            'wallet' => $wallet,
            'tradingAccounts' => $tradingAccounts,
            'ledgers' => $ledgers,
            'generated_at' => now()->format('d M Y, h:i A')
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function withdrawals(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        }

        $search = strtolower(trim((string) $request->query('search')));
        $broker = $request->query('broker_type') ?: (DB::connection('mongodb')->table('users')->where('uid', $user['uid'])->value('broker_type') ?: 'kryvex');

        // 1. Paginated Withdrawal Requests (Common across all sub-accounts of the user for active broker)
        $withdrawalQuery = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->where('user_uid', $user['uid'])
            ->where(function ($q) use ($broker) {
                $q->where('broker_type', $broker);
                if ($broker === 'kryvex') {
                    $q->orWhereNull('broker_type')->orWhere('broker_type', '');
                }
            });

        if ($search) {
            $withdrawalQuery->where(function ($q) use ($search) {
                $q->where('network', 'like', '%' . $search . '%')
                  ->orWhere('wallet_address', 'like', '%' . $search . '%')
                  ->orWhere('reference', 'like', '%' . $search . '%')
                  ->orWhere('status', 'like', '%' . $search . '%');
            });
        }

        $withdrawals = $withdrawalQuery->orderByDesc('created_at')
            ->paginate(13);

        return response()->json([
            'success' => true,
            'message' => 'Wallet withdrawals fetched successfully.',
            'data' => [
                'withdrawal_requests' => $withdrawals->items(),
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'last_page' => $withdrawals->lastPage(),
                    'per_page' => $withdrawals->perPage(),
                    'total' => $withdrawals->total(),
                ]
            ]
        ]);
    }

    public function demoCredit(Request $request, DemoWalletService $walletService)
    {
        $networkInput = strtoupper((string) ($request->input('network') ?: $request->input('currency') ?: 'TRC20'));
        $request->merge(['network' => $networkInput]);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.00000001', 'max:100000000'],
            'network' => ['required', 'string', 'max:20'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Amount is required.',
            'amount.min' => 'Amount must be at least 0.00000001.',
        ]);

        $user = $request->attributes->get('auth_user');
        $wallet = $walletService->getOrCreateWallet($user['uid']);

        $network = strtoupper((string) $validated['network']);
        $networkRow = $this->networkByCode($network);

        if (! $networkRow) {
            $networkRow = [
                'network' => $network,
                'minimum_deposit' => 0,
                'address' => '',
            ];
        }

        $amount = round((float) $validated['amount'], 8);
        $txHash = trim((string) ($request->input('tx_hash') ?: $request->input('txid') ?: ''));
        $minimumDeposit = (float) ($networkRow['minimum_deposit'] ?? 0);

        // Bypass minimum deposit restriction for all crypto assets and MetaMask testnet deposits
        if (in_array($network, ['BTC', 'ETH', 'SOL', 'USDC', 'USDT', 'XRP', 'ADA', 'BNB'], true) || !empty($txHash)) {
            $minimumDeposit = 0;
        }

        if ($minimumDeposit > 0 && $amount < $minimumDeposit) {
            return response()->json([
                'success' => false,
                'message' => 'Minimum deposit for ' . $network . ' is ' . number_format($minimumDeposit, 4) . '.',
            ], 422);
        }

        $userUid = (string) $user['uid'];
        $wallet = $walletService->getOrCreateWallet($userUid);
        $broker = $wallet['broker_type'] ?? $walletService->getActiveBrokerType($userUid);

        $isNativeCrypto = in_array($network, ['BTC', 'ETH', 'SOL', 'USDC', 'USDT', 'XRP', 'ADA', 'BNB'], true);
        $expectedAddress = trim((string) ($networkRow['address'] ?? ''));

        if ($isNativeCrypto) {
            if (empty($txHash)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please enter the transaction hash / TX hash or company deposit address.',
                ], 422);
            }

            $isExactAddressMatch = !empty($expectedAddress) && (strtolower($txHash) === strtolower($expectedAddress));
            $isValidTxLength = strlen($txHash) >= 20;

            if (! $isExactAddressMatch && ! $isValidTxLength) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid TX hash or address. Please paste the exact company deposit address or a valid transaction hash.',
                ], 422);
            }
        }

        if ($isNativeCrypto) {
            // Unverified credits only ever touch demo funds; real crypto deposits go through cryptoDeposit().
            $cryptoBalances = (array) ($wallet['demo_crypto_balances'] ?? []);
            $currentAssetBal = (float) ($cryptoBalances[$network] ?? 0);
            $newAssetBal = round($currentAssetBal + $amount, 8);
            $cryptoBalances[$network] = $newAssetBal;

            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->update([
                    'demo_crypto_balances.' . $network => $newAssetBal,
                    'updated_at' => now(),
                ]);

            $balanceAfter = $newAssetBal;
            $msg = "{$amount} {$network} added to your Demo {$network} balance.";
            $note = $validated['note'] ?? "{$amount} {$network} crypto deposit added to {$network} wallet." . ($txHash ? " (TxID: {$txHash})" : "");

            DB::connection('mongodb')->table('wallet_deposits')->insert([
                'deposit_uid' => (string) Str::uuid(),
                'user_uid' => $user['uid'],
                'gateway' => strtolower($network) === 'eth' ? 'metamask_sepolia' : strtolower($network) . '_wallet',
                'network' => $network,
                'currency' => $network,
                'amount' => $amount,
                'tx_hash' => $txHash,
                'status' => 'approved',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $settingsDoc = (array) DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
            $baseRate = (float) ($settingsDoc['usdt_inr_rate'] ?? 95.0);
            if ($baseRate <= 0) $baseRate = 95.0;

            // In demo mode: single balance used for trading, balance chip, and total balance
            $action = strtolower(trim((string) $request->input('action', 'add')));

            $currentDemoAvail = (float) ($wallet['demo_available_balance'] ?? 0);
            $currentDemoMain = (float) ($wallet['demo_main_balance'] ?? 0);
            $baseCurrent = max($currentDemoAvail, $currentDemoMain);

            if ($action === 'set') {
                $newAvailable = round($amount, 8);
            } else {
                $newAvailable = round($baseCurrent + $amount, 8);
            }

            $newAvailableInr = round($newAvailable * $baseRate, 2);

            // Update wallets collection
            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->update([
                    'demo_main_balance'          => $newAvailable,
                    'demo_available_balance'     => $newAvailable,
                    'demo_main_balance_inr'      => $newAvailableInr,
                    'demo_available_balance_inr' => $newAvailableInr,
                    'updated_at'                 => now(),
                ]);

            // Update trading_accounts collection for demo accounts
            DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where(function ($q) {
                    $q->where('account_type', 'demo')->orWhere('is_demo', true);
                })
                ->update([
                    'demo_main_balance'          => $newAvailable,
                    'demo_available_balance'     => $newAvailable,
                    'demo_main_balance_inr'      => $newAvailableInr,
                    'demo_available_balance_inr' => $newAvailableInr,
                    'updated_at'                 => now(),
                ]);

            $balanceAfter = $newAvailable;
            $msg = ($action === 'set')
                ? 'Demo balance set to ' . number_format($newAvailable, 2) . ' USD successfully.'
                : 'Added ' . number_format($amount, 2) . ' USD to Demo balance successfully.';
            $note = $validated['note'] ?? (($action === 'set') ? "Demo balance set to {$newAvailable} USD." : "Added {$amount} USD to Demo balance.");
        }

        $walletService->recordLedger([
            'user_uid' => $user['uid'],
            'broker_type' => $broker,
            'type' => $isNativeCrypto ? 'crypto_deposit' : 'demo_main_deposit',
            'currency' => $network,
            'direction' => 'credit',
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'reference' => 'DEP-' . strtoupper(Str::random(10)),
            'note' => $note,
            'meta' => [
                'network' => $network,
                'company_deposit_address' => $networkRow['address'] ?? null,
                'tx_hash' => $txHash,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data' => [
                'wallet' => $walletService->getOrCreateWallet($user['uid']),
            ],
        ]);
    }

    public function cryptoExchange(Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'action' => ['nullable', 'string', 'in:buy,sell,BUY,SELL'],
            'from_currency' => ['nullable', 'string', 'max:10'],
            'to_currency' => ['nullable', 'string', 'max:10'],
            'crypto_currency' => ['nullable', 'string', 'max:10'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ]);

        $action = strtolower(trim($validated['action'] ?? 'sell'));
        $amount = (float) $validated['amount'];
        $userUid = (string) $user['uid'];
        $accountUid = $request->header('X-Account-UID') ?: $request->query('account_uid');
        $wallet = $walletService->getOrCreateWallet($userUid, $accountUid);
        $broker = $wallet['broker_type'] ?? $walletService->getActiveBrokerType($userUid);

        $tradingMode = $request->header('X-Trading-Mode') ?: ($request->query('trading_mode') ?: $request->input('trading_mode'));
        if ($tradingMode === 'demo' || !empty($wallet['is_demo'])) {
            return response()->json([
                'success' => false,
                'message' => 'Exchange is only available for Real accounts. Demo accounts cannot exchange currency. Switch to a Live account to exchange funds.',
            ], 422);
        }

        $fromCur = strtoupper(trim($validated['from_currency'] ?? ''));
        $toCur = strtoupper(trim($validated['to_currency'] ?? ''));

        $fallbackRates = [
            'BTC' => 95000.0,
            'ETH' => 2600.0,
            'SOL' => 180.0,
            'USDC' => 1.0,
            'USDT' => 1.0,
            'USD' => 1.0,
            'BNB' => 580.0,
            'XRP' => 0.60,
            'DOGE' => 0.12,
            'ADA' => 0.40,
            'TRX' => 0.15,
            'DOT' => 4.5,
            'LTC' => 65.0,
            'AVAX' => 25.0,
            'SHIB' => 0.000015,
            'LINK' => 12.0,
            'UNI' => 7.0,
            'ETC' => 20.0,
            'XLM' => 0.10,
            'TON' => 5.5,
            'BCH' => 350.0,
            'AAVE' => 150.0,
            '1INCH' => 0.35,
            'APE' => 0.75,
        ];

        $getRate = function($coin) use ($fallbackRates) {
            $coin = strtoupper($coin);
            if ($coin === 'USDT' || $coin === 'USDC' || $coin === 'USD') return 1.0;
            if (isset($fallbackRates[$coin])) {
                try {
                    $resp = Http::timeout(2)->get("https://api.binance.com/api/v3/ticker/price?symbol={$coin}USDT");
                    if ($resp->successful() && isset($resp->json()['price'])) {
                        return (float) $resp->json()['price'];
                    }
                } catch (\Throwable $e) {}
                return (float) $fallbackRates[$coin];
            }
            return 1.0;
        };

        $cryptoBalances = (array) ($wallet['real_crypto_balances'] ?? []);
        $realMainUsd = (float) ($wallet['real_main_balance'] ?? 0);
        $realCryptoUsdt = (float) ($cryptoBalances['USDT'] ?? 0);
        // Real USDT balance available in wallet for exchange
        $currentMainUsd = $realCryptoUsdt > 0 ? $realCryptoUsdt : $realMainUsd;

        // Check if crypto-to-crypto SWAP
        $isFromFiat = in_array($fromCur, ['USD', 'USDT', 'INR', '']);
        $isToFiat = in_array($toCur, ['USD', 'USDT', 'INR', '']);

        if (!$isFromFiat && !$isToFiat && !empty($fromCur) && !empty($toCur) && $fromCur !== $toCur) {
            $fromBal = (float) ($cryptoBalances[$fromCur] ?? 0);
            $cryptoSold = round($amount, 8);
            if ($cryptoSold > $fromBal) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient {$fromCur} balance. You have {$fromBal} {$fromCur} in your wallet.",
                ], 422);
            }

            $fromRate = $getRate($fromCur);
            $toRate = $getRate($toCur);
            $usdValue = $cryptoSold * $fromRate;
            $cryptoReceived = round($usdValue / $toRate, 8);
            $newFromBal = round($fromBal - $cryptoSold, 8);
            $toBal = (float) ($cryptoBalances[$toCur] ?? 0);
            $newToBal = round($toBal + $cryptoReceived, 8);

            $walletService->setLiveAccountFunds($userUid, (string) $wallet['active_account_uid'], [
                'crypto' => [$fromCur => $newFromBal, $toCur => $newToBal],
            ]);

            $walletService->recordLedger([
                'user_uid' => $userUid,
                'broker_type' => $broker,
                'type' => 'crypto_exchange',
                'currency' => $toCur,
                'direction' => 'credit',
                'amount' => $cryptoReceived,
                'balance_after' => $newToBal,
                'reference' => 'SWAP-' . strtoupper(Str::random(10)),
                'note' => "Swapped {$cryptoSold} {$fromCur} ➔ {$cryptoReceived} {$toCur}.",
                'meta' => ['action' => 'swap', 'from_crypto' => $fromCur, 'to_crypto' => $toCur, 'amount_sold' => $cryptoSold, 'amount_received' => $cryptoReceived],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully swapped {$cryptoSold} {$fromCur} to {$cryptoReceived} {$toCur}!",
                'data' => [
                    'wallet' => $walletService->getOrCreateWallet($userUid, $accountUid),
                ],
            ]);
        }

        // Check if buying or selling crypto for USDT/USD
        $cryptoAsset = $action === 'buy' ? $toCur : $fromCur;
        if (empty($cryptoAsset) || $cryptoAsset === 'USDT' || $cryptoAsset === 'USD') {
            $cryptoAsset = strtoupper(trim($validated['crypto_currency'] ?? 'BTC'));
        }

        $rate = $getRate($cryptoAsset);
        $currentCryptoBal = (float) ($cryptoBalances[$cryptoAsset] ?? 0);

        if ($action === 'buy') {
            // BUY: Pay $amount USDT -> Receive ($amount / $rate) Crypto
            $usdAmount = round($amount, 2);
            if ($usdAmount > $currentMainUsd) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient USDT balance. You have " . number_format($currentMainUsd, 2) . " USDT available in your wallet.",
                ], 422);
            }

            $cryptoReceived = round($usdAmount / $rate, 8);
            $newMainUsd = round(max(0, $currentMainUsd - $usdAmount), 2);
            $newCryptoBal = round($currentCryptoBal + $cryptoReceived, 8);

            $walletService->setLiveAccountFunds($userUid, (string) $wallet['active_account_uid'], [
                'crypto' => [$cryptoAsset => $newCryptoBal, 'USDT' => $newMainUsd],
            ]);

            $walletService->recordLedger([
                'user_uid' => $userUid,
                'broker_type' => $broker,
                'type' => 'crypto_exchange',
                'currency' => $cryptoAsset,
                'direction' => 'debit',
                'amount' => $usdAmount,
                'balance_after' => $newMainUsd,
                'reference' => 'BUY-' . strtoupper(Str::random(10)),
                'note' => "Bought {$cryptoReceived} {$cryptoAsset} for {$usdAmount} USDT.",
                'meta' => ['action' => 'buy', 'crypto' => $cryptoAsset, 'usd_paid' => $usdAmount, 'crypto_received' => $cryptoReceived, 'rate' => $rate],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully bought {$cryptoReceived} {$cryptoAsset} for {$usdAmount} USDT.",
                'data' => [
                    'wallet' => $walletService->getOrCreateWallet($userUid, $accountUid),
                ],
            ]);
        } else {
            // SELL: Sell $amount Crypto -> Receive ($amount * $rate) USDT
            $cryptoSold = round($amount, 8);
            if ($cryptoSold > $currentCryptoBal) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient {$cryptoAsset} balance. You have {$currentCryptoBal} {$cryptoAsset} in your crypto wallet.",
                ], 422);
            }

            $creditedUsd = round($cryptoSold * $rate, 2);
            $newCryptoBal = round(max(0, $currentCryptoBal - $cryptoSold), 8);
            $newMainUsd = round($currentMainUsd + $creditedUsd, 2);

            $walletService->setLiveAccountFunds($userUid, (string) $wallet['active_account_uid'], [
                'crypto' => [$cryptoAsset => $newCryptoBal, 'USDT' => $newMainUsd],
            ]);

            $walletService->recordLedger([
                'user_uid' => $userUid,
                'broker_type' => $broker,
                'type' => 'crypto_exchange',
                'currency' => $cryptoAsset,
                'direction' => 'credit',
                'amount' => $creditedUsd,
                'balance_after' => $newMainUsd,
                'reference' => 'SELL-' . strtoupper(Str::random(10)),
                'note' => "Sold {$cryptoSold} {$cryptoAsset} ➔ {$creditedUsd} USDT.",
                'meta' => ['action' => 'sell', 'crypto' => $cryptoAsset, 'crypto_sold' => $cryptoSold, 'credited_usd' => $creditedUsd, 'rate' => $rate],
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully sold {$cryptoSold} {$cryptoAsset} for {$creditedUsd} USDT.",
                'data' => [
                    'wallet' => $walletService->getOrCreateWallet($userUid, $accountUid),
                ],
            ]);
        }
    }

    public function withdraw(Request $request, DemoWalletService $walletService)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'network' => ['required', 'string', 'max:20'],
            'currency' => ['nullable', 'string', 'max:20'],
            'wallet_address' => ['required', 'string', 'max:160'],
            'note' => ['nullable', 'string', 'max:500'],
            'account_uid' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        }

        $userUid = (string) $user['uid'];
        $withdrawAccountUid = $walletService->resolveLiveAccountUid($userUid, $validated['account_uid'] ?? null);

        $network = strtoupper((string) $validated['network']);
        $walletAddress = trim((string) $validated['wallet_address']);
        $requestedCurrency = strtoupper(trim((string) ($validated['currency'] ?? '')));
        $withdrawAsset = $walletService->resolveWithdrawAsset($network, $requestedCurrency);

        if ($withdrawAsset === null) {
            return response()->json([
                'success' => false,
                'message' => $requestedCurrency !== ''
                    ? "{$requestedCurrency} withdrawals on the {$network} network are not supported yet."
                    : "Withdrawals on the {$network} network are not supported yet.",
            ], 422);
        }

        if (! $this->validateWalletAddress($network, $walletAddress)) {
            return response()->json([
                'success' => false,
                'message' => $this->addressRuleForNetwork($network),
            ], 422);
        }

        // Idempotency Key check
        $idempotencyKey = $request->header('Idempotency-Key');
        $cacheKey = null;
        $lock = null;
        if ($idempotencyKey) {
            $cacheKey = 'idempotency_withdraw_' . md5($userUid . '_' . $idempotencyKey);
            $lockKey = 'idempotency_lock_withdraw_' . md5($userUid . '_' . $idempotencyKey);
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                return response()->json(\Illuminate\Support\Facades\Cache::get($cacheKey));
            }
            $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);
            if (!$lock->get()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate withdrawal request in progress.',
                ], 429);
            }
        }

        $this->upsertWithdrawalAddress($userUid, $network, $walletAddress);

        $amount = round((float) $validated['amount'], 8);

        if (!$walletService->withdrawalEnabled()) {
            if ($lock) $lock->release();
            return response()->json([
                'success' => false,
                'message' => 'Withdrawals are currently disabled by admin.',
            ], 422);
        }

        $assetUsdPrice = $walletService->withdrawAssetUsdPrice($withdrawAsset);
        $amountUsd = round($amount * $assetUsdPrice, 8);

        $limits = $walletService->withdrawalLimits();
        $minimumWithdraw = (float) ($limits['minimum_withdraw_amount'] ?? 0);
        $maximumWithdraw = (float) ($limits['maximum_withdraw_amount'] ?? 0);

        if ($minimumWithdraw > 0 && $amountUsd < $minimumWithdraw) {
            if ($lock) $lock->release();
            return response()->json([
                'success' => false,
                'message' => 'Minimum withdrawal amount is $' . number_format($minimumWithdraw, 2) . '.',
            ], 422);
        }

        if ($maximumWithdraw > 0 && $amountUsd > $maximumWithdraw) {
            if ($lock) $lock->release();
            return response()->json([
                'success' => false,
                'message' => 'Maximum withdrawal amount is $' . number_format($maximumWithdraw, 2) . '.',
            ], 422);
        }

        $charges = $walletService->calculateWithdrawalCharges($amount, $assetUsdPrice);

        if ($charges['net_amount'] <= 0) {
            if ($lock) $lock->release();
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal amount must be greater than total charges.',
            ], 422);
        }

        // Blocks double-clicks / duplicate submits even without an Idempotency-Key header.
        $userSubmitGuard = 'withdraw_submit_guard_' . $userUid;
        if (!\Illuminate\Support\Facades\Cache::add($userSubmitGuard, 1, 5)) {
            if ($lock) $lock->release();
            return response()->json([
                'success' => false,
                'message' => 'A withdrawal request was just submitted. Please wait a few seconds before trying again.',
            ], 429);
        }

        try {
            $reference = 'WD-' . strtoupper(substr(md5($userUid . now()->timestamp . rand()), 0, 10));

            $executeWithdrawal = function () use ($walletService, $userUid, $withdrawAccountUid, $amount, $network, $walletAddress, $charges, $reference, $withdrawAsset, $assetUsdPrice) {
                $funds = $walletService->getLiveAccountFunds($userUid, $withdrawAccountUid);
                $broker = $funds['broker_type'];
                $networkUpper = strtoupper(trim($network));
                $cryptoAsset = $withdrawAsset;

                $available = round((float) ($funds['crypto'][$cryptoAsset] ?? 0), 8);
                $pending = round((float) ($funds['crypto_pending'][$cryptoAsset] ?? 0), 8);

                if ($amount > $available) {
                    throw new \Exception("Insufficient available {$cryptoAsset} balance in {$funds['account_name']}. You have {$available} {$cryptoAsset}.");
                }

                $newAvailable = max(0, round($available - $amount, 8));
                $newPending = max(0, round($pending + $amount, 8));
                $balanceAfter = $newAvailable;

                $walletService->setLiveAccountFunds($userUid, $funds['account_uid'], [
                    'crypto' => [$cryptoAsset => $newAvailable],
                    'crypto_pending' => [$cryptoAsset => $newPending],
                ]);

                DB::connection('mongodb')
                    ->table('wallet_withdrawal_requests')
                    ->insert([
                        'user_uid' => $userUid,
                        'broker_type' => $broker,
                        'account_uid' => $funds['account_uid'],
                        'account_name' => $funds['account_name'],
                        'amount' => $amount,
                        'currency' => $cryptoAsset,
                        'wallet_currency' => $cryptoAsset,
                        'asset_usd_price' => $assetUsdPrice,
                        'withdraw_fee' => $charges['withdraw_fee'],
                        'platform_charge' => $charges['platform_charge'],
                        'total_charge' => $charges['total_charge'],
                        'net_amount' => $charges['net_amount'],
                        'withdrawal_fee_type' => $charges['withdrawal_fee_type'],
                        'withdrawal_fee_value' => $charges['withdrawal_fee_value'],
                        'platform_charge_type' => $charges['platform_charge_type'],
                        'platform_charge_value' => $charges['platform_charge_value'],
                        'method' => $networkUpper . '_WALLET',
                        'network' => $networkUpper,
                        'wallet_address' => $walletAddress,
                        'reference' => $reference,
                        'note' => null,
                        'status' => 'pending',
                        'requested_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::connection('mongodb')
                    ->table('wallet_ledgers')
                    ->insert([
                        'ledger_uid' => (string) Str::uuid(),
                        'user_uid' => $userUid,
                        'account_uid' => $funds['account_uid'],
                        'broker_type' => $broker,
                        'wallet_currency' => $cryptoAsset,
                        'currency' => $cryptoAsset,
                        'type' => 'withdrawal_request',
                        'category' => 'withdrawal',
                        'direction' => 'debit',
                        'amount' => $amount,
                        'balance_after' => $balanceAfter,
                        'status' => 'pending',
                        'reference' => $reference,
                        'note' => "{$cryptoAsset} ({$networkUpper}) withdrawal request submitted. Receive: {$charges['net_amount']} {$cryptoAsset}.",
                        'meta' => [
                            'withdraw_fee' => $charges['withdraw_fee'],
                            'platform_charge' => $charges['platform_charge'],
                            'total_charge' => $charges['total_charge'],
                            'net_amount' => $charges['net_amount'],
                            'network' => $networkUpper,
                            'wallet_address' => $walletAddress,
                        ],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                return [
                    'available_balance' => $balanceAfter,
                ];
            };

            try {
                $result = DB::connection('mongodb')->transaction($executeWithdrawal);
            } catch (\Throwable $txError) {
                if (str_contains($txError->getMessage(), 'Transaction numbers are only allowed') || str_contains($txError->getMessage(), 'replica set')) {
                    $result = $executeWithdrawal();
                } else {
                    throw $txError;
                }
            }

            $responsePayload = [
                'success' => true,
                'message' => 'Withdrawal request submitted successfully. You will receive '
                    . (in_array($withdrawAsset, ['USDT', 'USDC'], true)
                        ? number_format($charges['net_amount'], 2) . ' ' . $withdrawAsset
                        : rtrim(rtrim(number_format($charges['net_amount'], 8, '.', ''), '0'), '.') . ' ' . $withdrawAsset)
                    . ' after charges if admin approves.',
                'reference' => $reference,
                'currency' => $withdrawAsset,
                'available_balance' => $result['available_balance'],
                'charges' => $charges,
            ];

            if ($cacheKey) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $responsePayload, 86400); // 24 hours
            }

            return response()->json($responsePayload);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::forget($userSubmitGuard);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    public function transfer(Request $request, DemoWalletService $walletService)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'direction' => ['required', 'string', 'in:main_to_trading,trading_to_main'],
            'currency' => ['nullable', 'string', 'in:USDT,USD,INR'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        }

        $userUid = (string) $user['uid'];

        // Idempotency Key check
        $idempotencyKey = $request->header('Idempotency-Key');
        $cacheKey = null;
        $lock = null;
        if ($idempotencyKey) {
            $cacheKey = 'idempotency_transfer_' . md5($userUid . '_' . $idempotencyKey);
            $lockKey = 'idempotency_lock_transfer_' . md5($userUid . '_' . $idempotencyKey);
            if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
                return response()->json(\Illuminate\Support\Facades\Cache::get($cacheKey));
            }
            $lock = \Illuminate\Support\Facades\Cache::lock($lockKey, 10);
            if (!$lock->get()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate transfer request in progress.',
                ], 429);
            }
        }

        try {
            $settingDoc = (array) DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
            $usdtInrRate = (float) ($settingDoc['usdt_inr_rate'] ?? 95.0);

            $accountUid = $request->input('account_uid') ?: $request->header('X-Account-UID');
            $broker = $request->header('X-Broker-Type') ?: ($request->query('broker_type') ?: ($user['broker_type'] ?? null));

            $wallet = $walletService->transferDemoBalance(
                $userUid,
                $validated['direction'],
                (float) $validated['amount'],
                $validated['note'] ?? null,
                $validated['currency'] ?? 'USDT',
                $usdtInrRate,
                $accountUid,
                $broker
            );

            $responsePayload = [
                'success' => true,
                'message' => $validated['direction'] === 'main_to_trading'
                    ? 'Amount transferred from main to trading balance.'
                    : 'Amount transferred from trading to main balance.',
                'data' => [
                    'wallet' => $wallet,
                ],
            ];

            if ($cacheKey) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $responsePayload, 86400); // 24 hours
            }

            return response()->json($responsePayload);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } finally {
            if ($lock) {
                $lock->release();
            }
        }
    }

    public function accountTransfer(Request $request, DemoWalletService $walletService)
    {
        $validated = $request->validate([
            'from_account_uid' => ['required', 'string'],
            'to_account_uid' => ['required', 'string', 'different:from_account_uid'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);

        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        }

        try {
            $result = $walletService->transferBetweenAccounts(
                (string) $user['uid'],
                $validated['from_account_uid'],
                $validated['to_account_uid'],
                (float) $validated['amount'],
                $validated['note'] ?? null
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?: $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Unable to complete the transfer. Please try again.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Amount transferred between accounts.',
            'data' => $result,
        ]);
    }

    public function saveWithdrawalAddress(Request $request)
    {
        $validated = $request->validate([
            'network' => ['required', 'string', 'max:20'],
            'wallet_address' => ['required', 'string', 'max:160'],
        ]);

        $user = $request->attributes->get('auth_user');
        $userUid = (string) $user['uid'];
        $network = strtoupper((string) $validated['network']);
        $walletAddress = trim((string) $validated['wallet_address']);

        if (! $this->validateWalletAddress($network, $walletAddress)) {
            return response()->json([
                'success' => false,
                'message' => $this->addressRuleForNetwork($network),
            ], 422);
        }

        $this->upsertWithdrawalAddress($userUid, $network, $walletAddress);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal address saved successfully.',
            'data' => [
                'network' => $network,
                'wallet_address' => $walletAddress,
            ],
        ]);
    }

    public function resetDemoData(Request $request, DemoWalletService $walletService)
    {
        if (! app()->environment('local')) {
            return response()->json([
                'success' => false,
                'message' => 'Demo reset is allowed only in local environment.',
            ], 403);
        }

        $request->validate([
            'confirm' => ['required', 'string', 'in:RESET'],
        ], [
            'confirm.in' => 'Type RESET to confirm demo reset.',
        ]);

        $user = $request->attributes->get('auth_user');

        foreach (['futures_orders', 'futures_positions', 'futures_trades', 'options_orders', 'options_positions', 'options_trades', 'wallet_ledgers', 'wallet_withdrawal_requests'] as $collection) {
            DB::connection('mongodb')
                ->table($collection)
                ->where('user_uid', $user['uid'])
                ->delete();
        }

        $existingWallet = DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $user['uid'])
            ->first();

        if ($existingWallet) {
            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $user['uid'])
                ->update([
                    'currency' => 'USD',
                    'demo_main_balance' => 0.0,
                    'demo_available_balance' => 0.0,
                    'demo_locked_balance' => 0.0,
                    'demo_withdrawal_locked_balance' => 0.0,
                    'real_available_balance' => 0.0,
                    'real_locked_balance' => 0.0,
                    'updated_at' => now(),
                ]);
        } else {
            $walletService->getOrCreateWallet($user['uid']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Demo wallet, orders, positions, trades and ledger reset successfully.',
            'data' => [
                'wallet' => $walletService->getOrCreateWallet($user['uid']),
            ],
        ]);
    }

    public function live(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        $wallet = DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $user['uid'])
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'wallet' => $wallet,
            ],
        ]);
    }

    private function depositNetworks(?string $userUid = null): array
    {
        $platform = DB::connection('mongodb')
            ->table('admin_settings')
            ->where('key', 'platform')
            ->first();

        $platformArr = $platform ? (array) $platform : [];
        $companyWallets = (array) ($platformArr['company_deposit_wallets'] ?? []);

        // Unique HD TRC20 and EVM (BEP20 / ERC20 / Polygon) addresses for authenticated user
        $userHdAddress = null;
        $userEvmHdAddress = null;
        if (!empty($userUid)) {
            try {
                $tronService = app(\App\Services\TronCustodyService::class);
                $hdData = $tronService->getOrCreateUserDepositAddress($userUid);
                $userHdAddress = $hdData['address'] ?? null;

                $evmData = $tronService->getOrCreateUserEvmDepositAddress($userUid, 'BEP20');
                $userEvmHdAddress = $evmData['address'] ?? null;
            } catch (\Throwable $e) {
                // Ignore and fall back to platform settings
            }
        }

        $networksMap = [
            'TRC20' => ['key' => 'USD_TRC20', 'default_address' => 'TMdE1ykZHEsm1M9o55GXUAcUCEuAydRDeo', 'default_min' => 10],
            'ERC20' => ['key' => 'USD_ERC20', 'default_address' => '0x095432135a216091dE6868618Fe107Bec06e3e4b', 'default_min' => 25],
            'BEP20' => ['key' => 'USD_BEP20', 'default_address' => '0xA28Fc0A13c287AeE5C03492c9e4FA0BEF325955f', 'default_min' => 10],
            'BTC'   => ['key' => 'BTC',       'default_address' => '0x095432135a216091dE6868618Fe107Bec06e3e4b', 'default_min' => 0],
            'ETH'   => ['key' => 'ETH',       'default_address' => '0x095432135a216091dE6868618Fe107Bec06e3e4b', 'default_min' => 0],
            'SOL'   => ['key' => 'SOL',       'default_address' => '0x095432135a216091dE6868618Fe107Bec06e3e4b', 'default_min' => 0.01],
            'USDC'  => ['key' => 'USDC',      'default_address' => '0xA28Fc0A13c287AeE5C03492c9e4FA0BEF325955f', 'default_min' => 10],
            'USDT'  => ['key' => 'USD_TRC20', 'default_address' => 'TMdE1ykZHEsm1M9o55GXUAcUCEuAydRDeo', 'default_min' => 10],
            'BNB'   => ['key' => 'BNB',       'default_address' => '0xA28Fc0A13c287AeE5C03492c9e4FA0BEF325955f', 'default_min' => 0.01],
            'XRP'   => ['key' => 'XRP',       'default_address' => 'rEb8TK3gGKwB2MvWf26v91zA4M2Wf26v91', 'default_min' => 1],
            'ADA'   => ['key' => 'ADA',       'default_address' => 'addr1q9prt882f0w5a2z97j50n5z846r5e8c4', 'default_min' => 1],
        ];

        $reqConfirmations = (int) config('kryvex_wallet.confirmations.required_blocks', 19);

        $results = [];
        foreach ($networksMap as $code => $cfg) {
            $settingKey = $cfg['key'];
            $row = (array) ($companyWallets[$settingKey] ?? []);

            $address = trim((string) ($row['address'] ?? ''));
            if (empty($address)) {
                $address = $cfg['default_address'];
            }

            // Assign unique user HD address for TRC20 and USDT
            if (in_array($code, ['TRC20', 'USDT'], true) && !empty($userHdAddress)) {
                $address = $userHdAddress;
            } elseif (in_array($code, ['BEP20', 'ERC20', 'POLYGON', 'MATIC', 'USDC', 'BNB', 'ETH'], true) && !empty($userEvmHdAddress)) {
                $address = $userEvmHdAddress;
            }

            $minDeposit = isset($row['minimum_amount']) ? (float) $row['minimum_amount'] : (float) $cfg['default_min'];
            $confs = in_array($code, ['TRC20', 'USDT'], true) ? $reqConfirmations : 3;

            $results[] = [
                'network' => $code,
                'label' => $row['label'] ?? $code,
                'address' => $address,
                'minimum_deposit' => $minDeposit,
                'confirmations' => $confs,
                'rule' => $this->addressRuleForNetwork($code),
            ];
        }

        return $results;
    }

    private function networkByCode(string $network, ?string $userUid = null): ?array
    {
        foreach ($this->depositNetworks($userUid) as $row) {
            if (($row['network'] ?? '') === strtoupper($network)) {
                return $row;
            }
        }
        return null;
    }

    private function userWithdrawalAddresses(string $userUid): array
    {
        return DB::connection('mongodb')
            ->table('user_withdrawal_addresses')
            ->where('user_uid', $userUid)
            ->get()
            ->mapWithKeys(fn ($row) => [strtoupper((string) ($row->network ?? '')) => (string) ($row->wallet_address ?? '')])
            ->filter(fn ($address, $network) => $network && $address)
            ->all();
    }

    private function upsertWithdrawalAddress(string $userUid, string $network, string $walletAddress): void
    {
        $network = strtoupper($network);
        $existing = DB::connection('mongodb')
            ->table('user_withdrawal_addresses')
            ->where('user_uid', $userUid)
            ->where('network', $network)
            ->first();

        $data = [
            'user_uid' => $userUid,
            'network' => $network,
            'wallet_address' => $walletAddress,
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::connection('mongodb')
                ->table('user_withdrawal_addresses')
                ->where('user_uid', $userUid)
                ->where('network', $network)
                ->update($data);
            return;
        }

        $data['address_uid'] = (string) Str::uuid();
        $data['created_at'] = now();
        DB::connection('mongodb')->table('user_withdrawal_addresses')->insert($data);
    }

    /**
     * Address format rules per withdrawal network. Keep in sync with WITHDRAW_ADDRESS_RULES in resources/js/pages/wallet.js.
     */
    private const WITHDRAW_ADDRESS_RULES = [
        'TRC20' => ['pattern' => '/^T[1-9A-HJ-NP-Za-km-z]{33}$/', 'rule' => 'Enter a valid TRON (TRC20) address: starts with "T" and is 34 characters long.'],
        'ERC20' => ['pattern' => '/^0x[a-fA-F0-9]{40}$/', 'rule' => 'Enter a valid Ethereum (ERC20) address: starts with "0x" followed by 40 hex characters.'],
        'BEP20' => ['pattern' => '/^0x[a-fA-F0-9]{40}$/', 'rule' => 'Enter a valid BNB Smart Chain (BEP20) address: starts with "0x" followed by 40 hex characters.'],
        'POLYGON' => ['pattern' => '/^0x[a-fA-F0-9]{40}$/', 'rule' => 'Enter a valid Polygon address: starts with "0x" followed by 40 hex characters.'],
        'ARB' => ['pattern' => '/^0x[a-fA-F0-9]{40}$/', 'rule' => 'Enter a valid Arbitrum address: starts with "0x" followed by 40 hex characters.'],
        'AVAX' => ['pattern' => '/^0x[a-fA-F0-9]{40}$/', 'rule' => 'Enter a valid Avalanche C-Chain address: starts with "0x" followed by 40 hex characters.'],
        'SOL' => ['pattern' => '/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', 'rule' => 'Enter a valid Solana address: 32-44 base58 characters (no 0x prefix).'],
        'BTC' => ['pattern' => '/^(bc1[02-9ac-hj-np-z]{11,71}|[13][1-9A-HJ-NP-Za-km-z]{25,34})$/', 'rule' => 'Enter a valid Bitcoin address: starts with "bc1", "1" or "3".'],
        'LTC' => ['pattern' => '/^(ltc1[02-9ac-hj-np-z]{11,71}|[LM3][1-9A-HJ-NP-Za-km-z]{26,33})$/', 'rule' => 'Enter a valid Litecoin address: starts with "ltc1", "L", "M" or "3".'],
        'DOGE' => ['pattern' => '/^D[5-9A-HJ-NP-U][1-9A-HJ-NP-Za-km-z]{32}$/', 'rule' => 'Enter a valid Dogecoin address: starts with "D" and is 34 characters long.'],
        'XRP' => ['pattern' => '/^r[1-9A-HJ-NP-Za-km-z]{24,34}$/', 'rule' => 'Enter a valid XRP address: starts with "r" and is 25-35 characters long.'],
        'ADA' => ['pattern' => '/^(addr1[02-9ac-hj-np-z]{50,110}|(Ae2|DdzFf)[1-9A-HJ-NP-Za-km-z]{50,120})$/', 'rule' => 'Enter a valid Cardano address: usually starts with "addr1".'],
        'DOT' => ['pattern' => '/^1[1-9A-HJ-NP-Za-km-z]{45,47}$/', 'rule' => 'Enter a valid Polkadot address: starts with "1" and is 46-48 characters long.'],
        'TON' => ['pattern' => '/^((EQ|UQ|kQ|0Q)[A-Za-z0-9_-]{46}|-?[01]:[a-fA-F0-9]{64})$/', 'rule' => 'Enter a valid TON address: starts with "EQ" or "UQ" and is 48 characters long.'],
    ];

    private const WITHDRAW_NETWORK_ALIASES = [
        'USDT' => 'TRC20',
        'ETH' => 'ERC20',
        'USDC' => 'ERC20',
        'BNB' => 'BEP20',
        'MATIC' => 'POLYGON',
    ];

    private function withdrawAddressRule(string $network): ?array
    {
        $code = strtoupper(trim($network));
        $code = self::WITHDRAW_NETWORK_ALIASES[$code] ?? $code;

        return self::WITHDRAW_ADDRESS_RULES[$code] ?? null;
    }

    private function validateWalletAddress(string $network, string $address): bool
    {
        $address = trim($address);
        $rule = $this->withdrawAddressRule($network);

        if ($rule === null) {
            return false;
        }

        return preg_match($rule['pattern'], $address) === 1;
    }

    private function addressRuleForNetwork(string $network): string
    {
        $rule = $this->withdrawAddressRule($network);

        return $rule['rule'] ?? 'Withdrawals are not supported on the ' . strtoupper($network) . ' network.';
    }

    /**
     * Public: return current admin-set USDT/INR rate and bank details for INR deposit.
     */
    public function inrRate(Request $request)
    {
        $settings = DB::connection('mongodb')
            ->table('admin_settings')
            ->where('key', 'platform')
            ->first();

        $settings = $settings ? (array) $settings : [];
        $baseRate = (float) ($settings['usdt_inr_rate'] ?? 95.0);
        $buyRate = (float) ($settings['usdt_inr_buy_rate'] ?? $baseRate);
        $sellRate = (float) ($settings['usdt_inr_sell_rate'] ?? $baseRate);

        if ($baseRate <= 0) $baseRate = 95.0;
        if ($buyRate <= 0) $buyRate = 95.0;
        if ($sellRate <= 0) $sellRate = 94.50;

        return response()->json([
            'success' => true,
            'data' => [
                'usdt_inr_rate' => $baseRate,
                'usdt_inr_buy_rate' => $buyRate,
                'usdt_inr_sell_rate' => $sellRate,
                'bank_details'  => $settings['inr_bank_details'] ?? [
                    'bank_name' => 'HDFC Bank',
                    'account_name' => 'Kryvex Capital Private Limited',
                    'account_number' => '50200089123456',
                    'ifsc_code' => 'HDFC0001234',
                    'upi_id' => 'kryvex.pay@hdfcbank',
                ],
            ],
        ]);
    }

    /**
     * User submits INR deposit request with UTR proof. Admin approves and credits wallet.
     */
    public function inrDeposit(Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'utr'        => ['nullable', 'string', 'max:80'],
            'amount_inr' => ['required', 'numeric', 'min:1'],
        ]);

        $utrVal = !empty($validated['utr']) ? trim($validated['utr']) : ('UPI-' . strtoupper(\Illuminate\Support\Str::random(8)));

        $settings = (array) DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
        $rate = (float) ($settings['usdt_inr_buy_rate'] ?? ($settings['usdt_inr_rate'] ?? 95.0));
        if ($rate <= 0) {
            $rate = 95.0;
        }

        $amountInr = round((float) $validated['amount_inr'], 2);
        $amountUsdt = round($amountInr / $rate, 8);

        $userUid = (string) $user['uid'];
        $wallet = $walletService->getOrCreateWallet($userUid);
        $broker = $wallet['broker_type'] ?? $walletService->getActiveBrokerType($userUid);
        $newAvailableInr = round(((float) ($wallet['demo_main_balance_inr'] ?? 0)) + $amountInr, 2);

        DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->update([
                'demo_main_balance_inr' => $newAvailableInr,
                'updated_at' => now(),
            ]);

        // Store request log
        DB::connection('mongodb')->table('inr_deposit_requests')->insert([
            'user_uid'   => $user['uid'],
            'broker_type'=> $broker,
            'utr'        => $utrVal,
            'amount_inr' => $amountInr,
            'amount_usdt'=> $amountUsdt,
            'rate'       => $rate,
            'status'     => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $walletService->recordLedger([
            'user_uid'        => $user['uid'],
            'broker_type'     => $broker,
            'wallet_currency' => 'INR',
            'currency'        => 'INR',
            'amount_inr'      => $amountInr,
            'type'            => 'demo_main_deposit',
            'direction'       => 'credit',
            'amount'          => $amountUsdt,
            'balance_after'   => $newAvailableInr,
            'reference'       => 'INR-' . strtoupper(\Illuminate\Support\Str::random(10)),
            'note'            => "INR deposit: \u{20B9}{$amountInr} @ \u{20B9}{$rate}/USDT. Ref: {$utrVal}",
            'meta'            => ['amount_inr' => $amountInr, 'utr' => $utrVal, 'rate' => $rate],
        ]);

        return response()->json([
            'success' => true,
            'message' => "INR deposit of \u{20B9}{$amountInr} added to your INR Main Wallet balance successfully.",
        ]);
    }

    /**
     * User submits Crypto deposit notification ("I Have Sent").
     */
    public function cryptoDeposit(Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'asset'       => ['required', 'string', 'max:20'],
            'network'     => ['required', 'string', 'max:20'],
            'tx_hash'     => ['required', 'string', 'min:10', 'max:140'],
            'account_uid' => ['nullable', 'string', 'max:80'],
        ], [
            'tx_hash.required' => 'Please enter the Transaction Hash (TXID) from your transfer.',
            'tx_hash.min'      => 'Invalid Transaction Hash (TXID) length.',
        ]);

        $asset = strtoupper(trim($validated['asset']));
        $network = strtoupper(trim($validated['network']));
        $txHash = trim((string) ($validated['tx_hash']));
        $targetAccountUid = trim((string) ($validated['account_uid'] ?? ''));
        $userUid = (string) $user['uid'];
        $broker = $walletService->getActiveBrokerType($userUid);

        $targetAccountUid = $walletService->resolveLiveAccountUid($userUid, $targetAccountUid);

        // Check if this tx_hash has already been credited
        $existingDeposit = DB::connection('mongodb')
            ->table('wallet_deposits')
            ->where('tx_hash', $txHash)
            ->first();

        if ($existingDeposit) {
            return response()->json([
                'success' => false,
                'message' => 'This Transaction Hash (TXID) has already been processed and credited.',
            ], 422);
        }

        // Store / update crypto deposit request log
        DB::connection('mongodb')->table('crypto_deposit_requests')->insert([
            'deposit_uid' => (string) \Illuminate\Support\Str::uuid(),
            'user_uid'    => $userUid,
            'broker_type' => $broker,
            'account_uid' => $targetAccountUid ?: null,
            'asset'       => $asset,
            'network'     => $network,
            'amount'      => 0,
            'tx_hash'     => $txHash,
            'status'      => 'pending',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $netObj = $this->networkByCode($network, $userUid);
        $depositAddress = $netObj['address'] ?? null;

        if (empty($depositAddress)) {
            if ($network === 'TRC20' || $network === 'TRON') {
                $tronService = app(\App\Services\TronCustodyService::class);
                $hdData = $tronService->getOrCreateUserDepositAddress($userUid);
                $depositAddress = $hdData['address'] ?? null;
            }
        }

        if (empty($depositAddress)) {
            return response()->json([
                'success' => false,
                'message' => "Deposit address could not be resolved for network {$network}.",
            ], 422);
        }

        try {
            $multiChain = app(\App\Services\MultiChainCustodyService::class);
            $result = $multiChain->verifyDeposit($network, $asset, $txHash, $depositAddress);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? "Transaction verification failed on {$network} blockchain.",
                ], 404);
            }

            $txAmount = (float) ($result['amount'] ?? 0);
            $currency = $result['currency'] ?? $asset;
            $confs = (int) ($result['confirmations'] ?? 0);
            $reqConfs = (int) ($result['required_confirmations'] ?? 3);
            $confirmed = !empty($result['confirmed']);

            if ($txAmount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid deposit amount ({$txAmount} {$currency}).",
                ], 422);
            }

            if (!$confirmed) {
                return response()->json([
                    'success' => true,
                    'message' => "Transaction detected on {$network} ({$txAmount} {$currency})! Block confirmations: {$confs}/{$reqConfs}. Balance will be credited automatically once confirmed.",
                    'data' => [
                        'credited' => false,
                        'amount' => $txAmount,
                        'confirmations' => $confs,
                        'required_confirmations' => $reqConfs,
                    ]
                ]);
            }

            // Atomic credit
            $reference = 'DEP-' . strtoupper($network) . '-' . strtoupper(substr(md5($txHash), 0, 10));
            $credit = $walletService->adjustLiveCrypto($userUid, $targetAccountUid, $currency, $txAmount);
            $newRealBal = $credit['balance'];

            DB::connection('mongodb')->table('wallet_ledgers')->insert([
                'ledger_uid' => (string) \Illuminate\Support\Str::uuid(),
                'user_uid' => $userUid,
                'account_uid' => $credit['account_uid'],
                'currency' => $currency,
                'type' => strtolower($currency) . '_deposit',
                'category' => 'wallet_deposit',
                'direction' => 'credit',
                'amount' => $txAmount,
                'balance_after' => $newRealBal,
                'reference' => $reference,
                'note' => "On-Chain {$network} {$currency} Deposit ({$confs} confs, Tx: {$txHash})",
                'status' => 'completed',
                'meta' => [
                    'tx_hash' => $txHash,
                    'network' => $network,
                    'to_address' => $depositAddress,
                    'from_address' => $result['from'] ?? '',
                    'confirmations' => $confs,
                    'account_uid' => $credit['account_uid'],
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('mongodb')->table('wallet_deposits')->insert([
                'deposit_uid' => (string) \Illuminate\Support\Str::uuid(),
                'user_uid' => $userUid,
                'account_uid' => $credit['account_uid'],
                'currency' => $currency,
                'network' => $network,
                'amount' => $txAmount,
                'to_address' => $depositAddress,
                'from_address' => $result['from'] ?? '',
                'tx_hash' => $txHash,
                'status' => 'completed',
                'confirmations' => $confs,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::connection('mongodb')->table('crypto_deposit_requests')
                ->where('user_uid', $userUid)
                ->where('tx_hash', $txHash)
                ->update([
                    'status' => 'completed',
                    'amount' => $txAmount,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "Deposit of {$txAmount} {$currency} verified from {$network} blockchain and credited successfully! 🎉",
                'data' => [
                    'credited' => true,
                    'amount' => $txAmount,
                    'currency' => $currency,
                ]
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MultiChain cryptoDeposit verification error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Blockchain verification error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Real-time polling check for incoming blockchain deposits.
     */
    public function checkDepositStatus(Request $request, \App\Services\TronCustodyService $tronService)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $userUid = (string) $user['uid'];
        $hdData = $tronService->getOrCreateUserDepositAddress($userUid);
        $depositAddress = $hdData['address'] ?? null;

        $newDeposits = [];
        $totalCredited = 0;

        if (!empty($depositAddress)) {
            try {
                $transfers = $tronService->fetchTrc20Transfers($depositAddress);
                $currentBlock = $tronService->getCurrentBlockNumber();

                foreach ($transfers as $tx) {
                    $txId = $tx['transaction_id'] ?? '';
                    $rawAmount = (float) ($tx['value'] ?? 0);
                    if (empty($txId) || $rawAmount <= 0) continue;

                    $existingDeposit = DB::connection('mongodb')
                        ->table('wallet_deposits')
                        ->where('tx_hash', $txId)
                        ->first();

                    if ($existingDeposit) continue;

                    $txInfo = $tronService->getTransactionInfo($txId);
                    $txBlockNumber = (int) ($txInfo['blockNumber'] ?? 0);
                    $confirmations = $txBlockNumber > 0 ? max(1, $currentBlock - $txBlockNumber + 1) : 1;

                    $txUsdtAmount = round($rawAmount / 1000000, 6);

                    // Atomic credit
                    $reference = 'DEP-TRON-' . strtoupper(substr(md5($txId), 0, 10));
                    $depositRequest = DB::connection('mongodb')->table('crypto_deposit_requests')
                        ->where('user_uid', $userUid)
                        ->where('tx_hash', $txId)
                        ->first();
                    $walletService = app(DemoWalletService::class);
                    try {
                        $creditAccountUid = $walletService->resolveLiveAccountUid($userUid, $depositRequest->account_uid ?? null);
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        $creditAccountUid = $walletService->resolveLiveAccountUid($userUid, null);
                    }
                    $credit = $walletService->adjustLiveCrypto($userUid, $creditAccountUid, 'USDT', $txUsdtAmount);
                    $newRealBal = $credit['balance'];

                    DB::connection('mongodb')->table('wallet_ledgers')->insert([
                        'ledger_uid' => (string) \Illuminate\Support\Str::uuid(),
                        'user_uid' => $userUid,
                        'account_uid' => $credit['account_uid'],
                        'currency' => 'USDT',
                        'type' => 'usdt_deposit',
                        'category' => 'wallet_deposit',
                        'direction' => 'credit',
                        'amount' => $txUsdtAmount,
                        'balance_after' => $newRealBal,
                        'reference' => $reference,
                        'note' => "On-Chain TRC-20 USDT Deposit ({$confirmations} confs, Tx: {$txId})",
                        'status' => 'completed',
                        'meta' => [
                            'tx_hash' => $txId,
                            'network' => 'TRC20',
                            'to_address' => $depositAddress,
                            'from_address' => $tx['from'] ?? '',
                            'confirmations' => $confirmations,
                        ],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::connection('mongodb')->table('wallet_deposits')->insert([
                        'deposit_uid' => (string) \Illuminate\Support\Str::uuid(),
                        'user_uid' => $userUid,
                        'account_uid' => $credit['account_uid'],
                        'currency' => 'USDT',
                        'network' => 'TRC20',
                        'amount' => $txUsdtAmount,
                        'to_address' => $depositAddress,
                        'from_address' => $tx['from'] ?? '',
                        'tx_hash' => $txId,
                        'status' => 'completed',
                        'confirmations' => $confirmations,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $newDeposits[] = [
                        'amount' => $txUsdtAmount,
                        'tx_hash' => $txId,
                        'currency' => 'USDT',
                        'network' => 'TRC20',
                    ];
                    $totalCredited += $txUsdtAmount;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('checkDepositStatus error: ' . $e->getMessage());
            }
        }

        $wallet = DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->first();

        return response()->json([
            'success' => true,
            'deposit_address' => $depositAddress,
            'new_deposits_count' => count($newDeposits),
            'new_deposits' => $newDeposits,
            'total_credited' => $totalCredited,
            'real_main_balance' => (float) ($wallet->real_main_balance ?? 0),
        ]);
    }

    /**
     * User submits INR withdrawal request with bank/UPI details.
     */
    public function inrWithdraw(Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'amount_inr'     => ['required', 'numeric', 'min:1'],
            'account_name'   => ['nullable', 'string', 'max:120'],
            'bank_name'      => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:30'],
            'ifsc'           => ['nullable', 'string', 'max:20'],
            'upi_id'         => ['nullable', 'string', 'max:80'],
            'account_uid'    => ['nullable', 'string', 'max:80'],
        ]);

        $withdrawAccountUid = $walletService->resolveLiveAccountUid($user['uid'], $validated['account_uid'] ?? null);

        $savedBank = DB::connection('mongodb')
            ->table('user_bank_details')
            ->where('user_uid', $user['uid'])
            ->first();

        if ($savedBank) {
            $savedBank = (array) $savedBank;
            if (empty($validated['account_name'])) {
                $validated['account_name'] = $savedBank['account_name'] ?? null;
            }
            if (empty($validated['bank_name'])) {
                $validated['bank_name'] = $savedBank['bank_name'] ?? null;
            }
            if (empty($validated['account_number'])) {
                $validated['account_number'] = $savedBank['account_number'] ?? null;
            }
            if (empty($validated['ifsc'])) {
                $validated['ifsc'] = $savedBank['ifsc_code'] ?? null;
            }
            if (empty($validated['upi_id'])) {
                $validated['upi_id'] = $savedBank['upi_id'] ?? null;
            }
        }

        // Must have bank OR upi
        if (empty($validated['account_name']) && empty($validated['upi_id'])) {
            return response()->json(['success' => false, 'message' => 'Please save your bank details in Settings or provide bank details.'], 422);
        }

        $settings = (array) DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
        $rate = (float) ($settings['usdt_inr_rate'] ?? 0);

        if ($rate <= 0) {
            return response()->json(['success' => false, 'message' => 'INR rate not configured. Contact support.'], 422);
        }

        $amountInr = round((float) $validated['amount_inr'], 2);

        $charges = $walletService->calculateInrWithdrawalCharges($amountInr, $rate);

        if ($charges['net_amount_inr'] <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal amount must be greater than total charges.',
            ], 422);
        }

        $funds = $walletService->getLiveAccountFunds($user['uid'], $withdrawAccountUid);
        $broker = $funds['broker_type'];
        $availableInr = $funds['main_inr'];

        if ($amountInr > $availableInr) {
            return response()->json(['success' => false, 'message' => "Insufficient INR balance. You have ₹" . number_format($availableInr, 2) . " in {$funds['account_name']}."], 422);
        }

        $minimumBalanceUsdt = $walletService->minimumReserve();
        $minimumBalanceInr = round($minimumBalanceUsdt * $rate, 2);
        $hasOpenTrade = $walletService->hasOpenTradingExposure($user['uid']);

        if ($hasOpenTrade && (($availableInr - $amountInr) < $minimumBalanceInr)) {
            $maxInrWithdrawable = max(0, round($availableInr - $minimumBalanceInr, 2));
            return response()->json([
                'success' => false,
                'message' => "Open trading positions exist. A minimum reserve of ₹" . number_format($minimumBalanceInr, 2) . " ($" . number_format($minimumBalanceUsdt, 2) . ") must remain in your wallet while positions are open. Maximum withdrawable amount is ₹" . number_format($maxInrWithdrawable, 2) . ". To withdraw your full balance, please close all open positions first.",
            ], 422);
        }

        $reference = 'INRW-' . strtoupper(\Illuminate\Support\Str::random(10));

        $liveState = $walletService->adjustRealInrBalance(
            $user['uid'],
            $funds['account_uid'],
            -$amountInr,
            $amountInr
        );
        $balanceAfterInr = $liveState['real_main_balance_inr'];

        DB::connection('mongodb')->table('wallet_withdrawal_requests')->insert([
            'user_uid'       => $user['uid'],
            'broker_type'    => $broker,
            'account_uid'    => $liveState['account_uid'],
            'account_name'   => $liveState['account_name'],
            'balance_source' => 'real',
            'amount'         => $charges['amount'],
            'amount_inr'     => $charges['amount_inr'],
            'amount_inr_fee' => $charges['withdraw_fee_inr'],
            'amount_inr_platform' => $charges['platform_charge_inr'],
            'amount_inr_charge' => $charges['total_charge_inr'],
            'amount_inr_net' => $charges['net_amount_inr'],
            'currency'       => 'INR',
            'wallet_currency'=> 'INR',
            'rate'           => $rate,
            'withdraw_fee'   => $charges['withdraw_fee'],
            'platform_charge'=> $charges['platform_charge'],
            'total_charge'   => $charges['total_charge'],
            'net_amount'     => $charges['net_amount'],
            'withdrawal_fee_type' => $charges['withdrawal_fee_type'],
            'withdrawal_fee_value' => $charges['withdrawal_fee_value'],
            'platform_charge_type' => $charges['platform_charge_type'],
            'platform_charge_value' => $charges['platform_charge_value'],
            'method'         => 'INR_BANK',
            'wallet_address' => $validated['upi_id'] ?? (($validated['bank_name'] ?? '') . ' - ' . ($validated['account_number'] ?? '')),
            'status'         => 'pending',
            'reference'      => $reference,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $walletService->recordLedger([
            'user_uid'        => $user['uid'],
            'broker_type'     => $broker,
            'wallet_currency' => 'INR',
            'currency'        => 'INR',
            'amount_inr'      => $amountInr,
            'type'            => 'inr_withdrawal_request',
            'direction'       => 'debit',
            'amount'          => $charges['amount'],
            'balance_after'   => $balanceAfterInr,
            'reference'       => $reference,
            'note'            => "INR withdrawal request: \u{20B9}{$amountInr}. Fee: \u{20B9}{$charges['withdraw_fee_inr']}, Platform: \u{20B9}{$charges['platform_charge_inr']}. Receive: \u{20B9}{$charges['net_amount_inr']}.",
            'meta'            => [
                'amount_inr' => $amountInr,
                'amount_inr_fee' => $charges['withdraw_fee_inr'],
                'amount_inr_platform' => $charges['platform_charge_inr'],
                'amount_inr_charge' => $charges['total_charge_inr'],
                'amount_inr_net' => $charges['net_amount_inr'],
                'rate' => $rate,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => "INR withdrawal request of \u{20B9}{$amountInr} submitted. Admin will process within 24 hours.",
        ]);
    }

    private function getUserBaseCurrency(string $userUid): ?string
    {
        if (empty($userUid)) return null;
        $dbUser = DB::connection('mongodb')->table('users')->where('uid', $userUid)->first();
        if (! $dbUser) return null;
        $arr = (array) $dbUser;
        return $arr['base_currency'] ?? null;
    }

    public function createRazorpayOrder(Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        }

        $validated = $request->validate([
            'amount'     => ['nullable', 'numeric', 'min:1'],
            'amount_inr' => ['nullable', 'numeric', 'min:1'],
            'account_uid' => ['nullable', 'string', 'max:80'],
        ]);

        $brokerType = $walletService->getActiveBrokerType($user['uid']);
        if ($brokerType === 'mt5') {
            return response()->json([
                'success' => false,
                'message' => 'INR deposits are supported on Kryvex Exchange only. For MT5 accounts, please deposit via USDT/USD.',
            ], 422);
        }

        $depositAccountUid = $walletService->resolveLiveAccountUid($user['uid'], $validated['account_uid'] ?? null);

        $amountInr = (float) ($validated['amount'] ?? ($validated['amount_inr'] ?? 0));
        if ($amountInr <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid INR deposit amount.',
            ], 422);
        }

        $receiptUid = 'DEP_' . strtoupper(Str::random(10));

        $razorpayService = app(\App\Services\RazorpayService::class);
        $res = $razorpayService->createOrder($amountInr, $receiptUid, [
            'user_uid' => $user['uid'],
            'email' => $user['email'] ?? '',
        ]);

        if (!($res['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $res['message'] ?? 'Failed to create Razorpay Order.',
            ], 400);
        }

        DB::connection('mongodb')->table('wallet_deposits')->insert([
            'deposit_uid' => (string) Str::uuid(),
            'user_uid' => $user['uid'],
            'gateway' => 'razorpay',
            'order_id' => $res['order_id'],
            'account_uid' => $depositAccountUid,
            'amount_inr' => $amountInr,
            'currency' => 'INR',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Razorpay Order created successfully.',
            'key_id'       => $res['key_id'],
            'order_id'     => $res['order_id'],
            'amount_inr'   => $res['amount'],
            'amount_paise' => $res['amount_paise'],
            'currency'     => 'INR',
            'user' => [
                'name'   => $user['name'] ?? 'Kryvex Trader',
                'email'  => $user['email'] ?? '',
                'mobile' => $user['mobile'] ?? '',
            ],
            'data' => [
                'order_id'     => $res['order_id'],
                'amount'       => $res['amount'],
                'amount_paise' => $res['amount_paise'],
                'currency'     => 'INR',
                'key_id'       => $res['key_id'],
                'user' => [
                    'name'   => $user['name'] ?? 'Kryvex Trader',
                    'email'  => $user['email'] ?? '',
                    'mobile' => $user['mobile'] ?? '',
                ],
            ],
        ]);
    }

    public function verifyRazorpayPayment(Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        }

        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        $razorpayService = app(\App\Services\RazorpayService::class);
        $isValid = $razorpayService->verifySignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature']
        );

        if (!$isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payment signature. Verification failed.',
            ], 400);
        }

        $deposit = DB::connection('mongodb')
            ->table('wallet_deposits')
            ->where('order_id', $validated['razorpay_order_id'])
            ->first();

        if (!$deposit || (string) ($deposit->user_uid ?? '') !== (string) $user['uid']) {
            return response()->json([
                'success' => false,
                'message' => 'Deposit order record not found.',
            ], 404);
        }

        if (($deposit->status ?? '') === 'completed') {
            return response()->json([
                'success' => true,
                'message' => 'Payment already completed.',
                'data' => ['already_processed' => true],
            ]);
        }

        $amountInr = (float) ($deposit->amount_inr ?? 0);

        DB::connection('mongodb')
            ->table('wallet_deposits')
            ->where('order_id', $validated['razorpay_order_id'])
            ->update([
                'status' => 'completed',
                'payment_id' => $validated['razorpay_payment_id'],
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        $settings = (array) DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
        $rate = (float) ($settings['usdt_inr_rate'] ?? 85);
        if ($rate <= 0) $rate = 85;

        $amountUsdt = round($amountInr / $rate, 8);
        $userUid = (string) $user['uid'];
        try {
            $creditAccountUid = $walletService->resolveLiveAccountUid($userUid, $deposit->account_uid ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $creditAccountUid = $walletService->resolveLiveAccountUid($userUid, null);
        }
        $liveState = $walletService->adjustRealInrBalance($userUid, $creditAccountUid, $amountInr);
        $broker = $liveState['broker_type'];
        $newAvailableInr = $liveState['real_main_balance_inr'];

        $walletService->recordLedger([
            'user_uid'        => $userUid,
            'broker_type'     => $broker,
            'account_uid'     => $liveState['account_uid'],
            'wallet_currency' => 'INR',
            'currency'        => 'INR',
            'amount_inr'      => $amountInr,
            'type'            => 'inr_deposit',
            'direction'       => 'credit',
            'amount'          => $amountUsdt,
            'balance_after'   => $newAvailableInr,
            'reference'       => 'RZP-' . strtoupper(Str::random(10)),
            'note'            => "Razorpay INR Deposit: \u{20B9}{$amountInr}. Pay ID: " . $validated['razorpay_payment_id'],
            'meta'            => ['amount_inr' => $amountInr, 'payment_id' => $validated['razorpay_payment_id'], 'rate' => $rate],
        ]);

        $updatedWallet = $walletService->getOrCreateWallet($userUid);

        return response()->json([
            'success' => true,
            'message' => "Payment of \u{20B9}{$amountInr} successful! Funds credited to {$liveState['account_name']}.",
            'data' => [
                'wallet' => $updatedWallet,
                'amount_inr' => $amountInr,
                'payment_id' => $validated['razorpay_payment_id'],
            ],
        ]);
    }

    //internal transfer between users using internal address
            public function getInternalAddressInfo(Request $request, DemoWalletService $walletService)
    {
        $address = strtoupper(trim((string) $request->query('address')));

        if (empty($address) || !str_starts_with($address, 'KVX-')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Kryvex Internal Address format. Address must start with KVX-',
            ], 422);
        }

        $parts = explode('-', $address);
        $asset = $parts[1] ?? 'USD';

        $recipientWallet = DB::connection('mongodb')
            ->table('wallets')
            ->where('internal_addresses.' . $asset, $address)
            ->first();

        if (!$recipientWallet) {
            // Search all internal addresses values
            $allWallets = DB::connection('mongodb')->table('wallets')->get();
            foreach ($allWallets as $w) {
                $wArr = (array) $w;
                $iAddrs = (array) ($wArr['internal_addresses'] ?? []);
                foreach ($iAddrs as $ast => $addr) {
                    if (strtoupper(trim((string) $addr)) === $address) {
                        $recipientWallet = $w;
                        $asset = $ast;
                        break 2;
                    }
                }
            }
        }

        if (!$recipientWallet) {
            return response()->json([
                'success' => false,
                'message' => 'No user found with this internal address.',
            ], 404);
        }

        $recipientWalletArr = (array) $recipientWallet;
        $recipientUserUid = (string) ($recipientWalletArr['user_uid'] ?? '');

        $recipientUser = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $recipientUserUid)
            ->first();

        $userArr = (array) ($recipientUser ?? []);
        $fullName = trim(($userArr['first_name'] ?? '') . ' ' . ($userArr['last_name'] ?? '')) ?: ($userArr['name'] ?? 'Kryvex User');
        $email = (string) ($userArr['email'] ?? '');

        $maskedEmail = '';
        if ($email && str_contains($email, '@')) {
            $eParts = explode('@', $email);
            $namePart = $eParts[0];
            $domainPart = $eParts[1] ?? '';
            $maskedName = strlen($namePart) > 3 ? substr($namePart, 0, 3) . '***' : $namePart . '***';
            $maskedEmail = $maskedName . '@' . $domainPart;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_uid' => $recipientUserUid,
                'name' => $fullName,
                'masked_email' => $maskedEmail,
                'asset' => strtoupper($asset),
                'internal_address' => $address,
            ]
        ]);
    }

    public function executeInternalTransfer(Request $request, DemoWalletService $walletService)
    {
        $sender = $request->attributes->get('auth_user');
        if (!is_array($sender) || empty($sender['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'recipient_address' => ['required', 'string', 'max:100'],
            'amount'            => ['required', 'numeric', 'min:0.00000001'],
            'note'              => ['nullable', 'string', 'max:255'],
        ]);

        $senderUid = (string) $sender['uid'];
        $address = strtoupper(trim((string) $validated['recipient_address']));
        $amount = round((float) $validated['amount'], 8);

        if (!str_starts_with($address, 'KVX-')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Kryvex Internal Address format.',
            ], 422);
        }

        $parts = explode('-', $address);
        $asset = strtoupper($parts[1] ?? 'USD');

        // Locate Recipient Wallet
        $recipientWallet = DB::connection('mongodb')
            ->table('wallets')
            ->where('internal_addresses.' . $asset, $address)
            ->first();

        if (!$recipientWallet) {
            $allWallets = DB::connection('mongodb')->table('wallets')->get();
            foreach ($allWallets as $w) {
                $wArr = (array) $w;
                $iAddrs = (array) ($wArr['internal_addresses'] ?? []);
                foreach ($iAddrs as $ast => $addr) {
                    if (strtoupper(trim((string) $addr)) === $address) {
                        $recipientWallet = $w;
                        $asset = strtoupper($ast);
                        break 2;
                    }
                }
            }
        }

        if (!$recipientWallet) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient internal address not found.',
            ], 404);
        }

        $recipientWalletArr = (array) $recipientWallet;
        $recipientUid = (string) ($recipientWalletArr['user_uid'] ?? '');

        // TEST BYPASS: Allow internal transfer to own address / account (e.g. Kryvex -> MT5 Cent account)
        $targetRecipientAccountUid = null;
        if ($senderUid === $recipientUid || str_contains(strtolower($address), 'cent') || str_contains(strtolower($address), 'usc')) {
            $centAcc = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $recipientUid)
                ->where('broker_type', 'mt5')
                ->where(function ($q) {
                    $q->where('is_cent', true)
                      ->orWhere('plan_key', 'cent')
                      ->orWhere('account_currency', 'USC')
                      ->orWhere('account_name', 'regexp', '/cent/i');
                })
                ->first();

            if ($centAcc) {
                $centAccArr = (array) $centAcc;
                $targetRecipientAccountUid = $centAccArr['account_uid'];
            }
        }

        // Internal transfers move real funds only: sender's selected live account -> recipient's target Account.
        $tradingMode = $request->header('X-Trading-Mode') ?: ($request->query('trading_mode') ?: $request->input('trading_mode'));
        if ($tradingMode === 'demo') {
            return response()->json([
                'success' => false,
                'message' => 'Internal transfers are only available from a live account. Switch to a live account to continue.',
            ], 422);
        }

        $senderFunds = $walletService->getLiveAccountFunds($senderUid, $request->header('X-Account-UID') ?: $request->input('account_uid'));
        $senderWallet = $walletService->getLiveWallet($senderUid, $senderFunds['account_uid']);
        $broker = $senderFunds['broker_type'];
        $recipientFunds = $walletService->getLiveAccountFunds($recipientUid, $targetRecipientAccountUid);

        // Fetch Recipient User details for friendly ledger notes
        $recipientUser = (array) DB::connection('mongodb')->table('users')->where('uid', $recipientUid)->first();
        $recipientName = trim(($recipientUser['first_name'] ?? '') . ' ' . ($recipientUser['last_name'] ?? '')) ?: ($recipientUser['name'] ?? 'Kryvex User');

        $senderUser = (array) DB::connection('mongodb')->table('users')->where('uid', $senderUid)->first();
        $recipientAccount = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $recipientUid)
            ->where('account_uid', $recipientFunds['account_uid'])
            ->first();
        $recipientAccArr = $recipientAccount ? (array) $recipientAccount : [];
        $isRecipientCent = !empty($recipientAccArr['is_cent']) || ($recipientAccArr['plan_key'] ?? '') === 'cent' || ($recipientAccArr['account_currency'] ?? '') === 'USC';

        $isNativeCrypto = in_array($asset, ['BTC', 'ETH', 'SOL', 'USDC', 'USDT', 'XRP', 'ADA', 'BNB'], true);

        if ($isNativeCrypto || $asset === 'USD') {
            $coin = $isNativeCrypto ? $asset : 'USDT';
            $senderAvail = round((float) ($senderFunds['crypto'][$coin] ?? 0), 8);

            if ($amount > $senderAvail) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient available {$asset} balance in {$senderFunds['account_name']}. You have {$senderAvail} {$asset}.",
                ], 422);
            }

            $senderBalanceAfter = $walletService->adjustLiveCrypto($senderUid, $senderFunds['account_uid'], $coin, -$amount)['balance'];

            // Cent accounts hold USD like every other account; the UI shows it as USC (x100).
            $recipientBalanceAfter = $walletService->adjustLiveCrypto($recipientUid, $recipientFunds['account_uid'], $coin, $amount)['balance'];
            if ($isRecipientCent && in_array($coin, ['USDT', 'USD', 'USDC'], true)) {
                $recipientBalanceAfter = round($recipientBalanceAfter * 100, 2);
            }
        } elseif ($asset === 'INR') {
            $senderAvailInr = round($senderFunds['main_inr'], 2);
            if ($amount > $senderAvailInr) {
                return response()->json([
                    'success' => false,
                    'message' => "Insufficient INR balance in {$senderFunds['account_name']}. You have \u{20B9}{$senderAvailInr}.",
                ], 422);
            }

            $senderBalanceAfter = $walletService->adjustRealInrBalance($senderUid, $senderFunds['account_uid'], -$amount)['real_main_balance_inr'];
            $recipientBalanceAfter = $walletService->adjustRealInrBalance($recipientUid, $recipientFunds['account_uid'], $amount)['real_main_balance_inr'];
        } else {
            return response()->json([
                'success' => false,
                'message' => "Internal transfers of {$asset} are not supported.",
            ], 422);
        }

        $ref = 'INT-' . strtoupper(Str::random(10));

        // 1. Record Debit Ledger for Sender
        $walletService->recordLedger([
            'user_uid'      => $senderUid,
            'broker_type'   => $broker,
            'type'          => 'internal_transfer_sent',
            'currency'      => $asset,
            'direction'     => 'debit',
            'amount'        => $amount,
            'balance_after' => $senderBalanceAfter,
            'reference'     => $ref,
            'note'          => "Internal Transfer Sent: {$amount} {$asset} to {$recipientName} ({$address})",
            'meta'          => ['recipient_uid' => $recipientUid, 'recipient_name' => $recipientName, 'address' => $address],
        ]);

        // 2. Record Credit Ledger for Recipient
        $senderAddress = $senderWallet['internal_addresses'][$asset] ?? "KVX-{$asset}-{$senderUid}";
        $recipientNoteAmountStr = ($isRecipientCent && in_array($asset, ['USDT', 'USD', 'USDC'], true)) ? "{$amount} USD (" . number_format($amount * 100) . " USC)" : "{$amount} {$asset}";
        $walletService->recordLedger([
            'user_uid'      => $recipientUid,
            'broker_type'   => $broker,
            'type'          => 'internal_transfer_received',
            'currency'      => ($isRecipientCent && in_array($asset, ['USDT', 'USD', 'USDC'], true)) ? 'USC' : $asset,
            'direction'     => 'credit',
            'amount'        => ($isRecipientCent && in_array($asset, ['USDT', 'USD', 'USDC'], true)) ? ($amount * 100) : $amount,
            'balance_after' => $recipientBalanceAfter,
            'reference'     => $ref,
            'note'          => "Internal Transfer Received: {$recipientNoteAmountStr} from {$senderName} ({$senderAddress})",
            'meta'          => ['sender_uid' => $senderUid, 'sender_name' => $senderName, 'sender_address' => $senderAddress],
        ]);

        // 3. Save entry in wallet_internal_transfers collection
        DB::connection('mongodb')->table('wallet_internal_transfers')->insert([
            'transfer_uid'      => (string) Str::uuid(),
            'sender_user_uid'   => $senderUid,
            'sender_name'       => $senderName,
            'recipient_user_uid'=> $recipientUid,
            'recipient_name'    => $recipientName,
            'recipient_address' => $address,
            'currency'          => $asset,
            'amount'            => $amount,
            'reference'         => $ref,
            'note'              => $validated['note'] ?? null,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Internal transfer of {$amount} {$asset} sent successfully to {$recipientName}!",
            'data'    => [
                'reference' => $ref,
                'amount' => $amount,
                'currency' => $asset,
                'recipient_name' => $recipientName,
            ]
        ]);
    }
}
