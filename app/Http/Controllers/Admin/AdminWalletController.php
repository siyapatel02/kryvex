<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\AuditLogger;
use App\Support\AdminPaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Admin\AdminNotificationController;

class AdminWalletController extends Controller
{
    /**
     * Returns [isCryptoAsset, cryptoAsset] for a withdrawal request.
     * Prefers the stored currency; falls back to the network for legacy rows.
     */
    private function withdrawalCryptoAsset(object $withdrawal): array
    {
        $storedAsset = strtoupper(trim((string) ($withdrawal->currency ?? '')));
        if (isset(\App\Services\DemoWalletService::WITHDRAW_ASSET_NETWORKS[$storedAsset])) {
            return [true, $storedAsset];
        }

        $networkUpper = strtoupper(trim((string) ($withdrawal->network ?? $withdrawal->currency ?? '')));
        $isCryptoAsset = in_array($networkUpper, ['BTC', 'ETH', 'SOL', 'USDT', 'USDC', 'XRP', 'ADA', 'BNB', 'TRC20', 'BEP20', 'ERC20', 'POLYGON', 'BSC', 'TRON'], true);
        $cryptoAsset = in_array($networkUpper, ['TRC20', 'BEP20', 'ERC20', 'POLYGON', 'BSC', 'TRON'], true) ? 'USDT' : $networkUpper;

        return [$isCryptoAsset, $cryptoAsset];
    }

    public function index(Request $request)
    {
        $stats = $this->walletStats();
        $users = $this->usersMap();

        $recentDeposits = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->filter(function ($item) {
                return $this->isDepositLedger($item);
            })
            ->take(5)
            ->map(function ($item) use ($users) {
                $this->attachUser($item, $users);
                return $item;
            })
            ->values();

        $recentWithdrawals = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->take(5)
            ->map(function ($item) use ($users) {
                $item->withdrawal_id = (string)($item->_id ?? $item->id ?? '');
                $this->attachUser($item, $users);
                return $item;
            })
            ->values();

        $revenueWithdrawals = DB::connection('mongodb')
            ->table('platform_revenue_ledgers')
            ->where(function ($q) {
                $q->where('source', 'admin_withdrawal')
                  ->orWhere('type', 'admin_withdrawal')
                  ->orWhere('type', 'admin_withdrawal_inr');
            })
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                $isINR = ($item->currency ?? '') === 'INR' || 
                         ($item->wallet_currency ?? '') === 'INR' || 
                         !empty($item->meta['amount_inr'] ?? null) || 
                         !empty($item->amount_inr ?? null) || 
                         str_contains(strtolower((string)($item->type ?? '')), 'inr');

                $item->is_inr = $isINR;
                $item->display_currency_symbol = $isINR ? '₹' : '$';
                $item->display_amount = $isINR ? (float)($item->meta['amount_inr'] ?? $item->amount_inr ?? abs((float)($item->amount ?? 0))) : abs((float)($item->amount ?? 0));
                return $item;
            });

        $adminSetting = DB::connection('mongodb')->table('admin_settings')->first();
        $usdtInrRate = (float)($adminSetting->usdt_inr_rate ?? 85.0);
        if ($usdtInrRate <= 0) {
            $usdtInrRate = 85.0;
        }

        $revenueTransfers = DB::connection('mongodb')
            ->table('platform_revenue_ledgers')
            ->where('source', 'admin_transfer')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('admin.wallet.index', compact(
            'stats',
            'recentDeposits',
            'recentWithdrawals',
            'revenueWithdrawals',
            'usdtInrRate',
            'revenueTransfers'
        ));
    }

    public function deposits(Request $request)
    {
        $stats = $this->walletStats();
        $search = strtolower(trim((string) $request->query('search')));
        $currency = strtoupper(trim((string) $request->query('currency', 'all')));

        $rawDeposits = DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get();

        $users = $this->usersMap();

        $deposits = $rawDeposits->map(function ($item) use ($users) {
            $this->attachUser($item, $users);

            $item->search_text = strtolower(implode(' ', [
                $item->display_user_name ?? '',
                $item->display_user_email ?? '',
                $item->type ?? '',
                $item->direction ?? '',
                $item->reference ?? '',
                $item->note ?? '',
                $item->status ?? '',
                $item->amount ?? '',
            ]));

            return $item;
        })->filter(function ($item) use ($search, $currency) {
            if (!$this->isDepositLedger($item)) {
                return false;
            }

            if ($currency === 'INR' && !$item->is_inr) {
                return false;
            }

            if ($currency === 'USDT' && $item->is_inr) {
                return false;
            }

            if ($search !== '' && !str_contains($item->search_text, $search)) {
                return false;
            }

            return true;
        })->values();

        $deposits = AdminPaginator::paginate($deposits, $request, 10);

        return view('admin.wallet.deposits', compact('deposits', 'search', 'currency', 'stats'));
    }

    public function withdrawals(Request $request)
    {
        $stats = $this->walletStats();
        $status = $request->query('status', 'all');
        $currency = strtoupper(trim((string) $request->query('currency', 'all')));
        $search = strtolower(trim((string) $request->query('search')));

        $users = $this->usersMap();

        $allWithdrawals = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->map(function ($item) use ($users) {
                $item->withdrawal_id = (string)($item->_id ?? $item->id ?? '');
                $this->attachUser($item, $users);

                $item->search_text = strtolower(implode(' ', [
                    $item->display_user_name ?? '',
                    $item->display_user_email ?? '',
                    $item->reference ?? '',
                    $item->method ?? '',
                    $item->status ?? '',
                    $item->amount ?? '',
                    $item->withdraw_fee ?? '',
                    $item->platform_charge ?? '',
                    $item->net_amount ?? '',
                ]));

                return $item;
            });

        $pendingQueue = $allWithdrawals
            ->filter(function ($item) use ($search, $currency) {
                if (($item->status ?? 'pending') !== 'pending') {
                    return false;
                }

                if ($currency === 'INR' && !$item->is_inr) {
                    return false;
                }

                if ($currency === 'USDT' && $item->is_inr) {
                    return false;
                }

                if ($search !== '' && !str_contains($item->search_text, $search)) {
                    return false;
                }

                return true;
            })
            ->take(10)
            ->values();

        $withdrawals = $allWithdrawals
            ->filter(function ($item) use ($status, $currency, $search) {
                $itemStatus = (string)($item->status ?? 'pending');

                // Pending requests are managed in the approval queue above.
                if ($itemStatus === 'pending') {
                    return false;
                }

                if ($status !== 'all' && $itemStatus !== $status) {
                    return false;
                }

                if ($currency === 'INR' && !$item->is_inr) {
                    return false;
                }

                if ($currency === 'USDT' && $item->is_inr) {
                    return false;
                }

                if ($search !== '' && !str_contains($item->search_text, $search)) {
                    return false;
                }

                return true;
            })
            ->values();

        $withdrawals = AdminPaginator::paginate($withdrawals, $request, 10);

        return view('admin.wallet.withdrawals', compact('withdrawals', 'pendingQueue', 'status', 'currency', 'search', 'stats'));
    }

    public function ledger(Request $request)
    {
        $stats = $this->walletStats();
        $source = $request->query('source', 'all');
        $currency = strtoupper(trim((string) $request->query('currency', 'all')));
        $search = strtolower(trim((string) $request->query('search')));

        $query = DB::connection('mongodb')
            ->table('platform_revenue_ledgers')
            ->orderByDesc('created_at');

        if ($source !== 'all') {
            $query->where('source', $source);
        }

        $rawLedger = $query->limit(2000)->get();
        $users = $this->usersMap();

        $ledger = $rawLedger->map(function ($item) use ($users) {
            $this->attachUser($item, $users);

            $item->search_text = strtolower(implode(' ', [
                $item->display_user_name ?? '',
                $item->display_user_email ?? '',
                $item->source ?? '',
                $item->type ?? '',
                $item->reference ?? '',
                $item->description ?? '',
                $item->amount ?? '',
            ]));

            return $item;
        })->filter(function ($item) use ($search, $currency) {
            if ($currency === 'INR' && !$item->is_inr) {
                return false;
            }

            if ($currency === 'USDT' && $item->is_inr) {
                return false;
            }

            if ($search !== '' && !str_contains($item->search_text, $search)) {
                return false;
            }
            return true;
        })->values();

        $ledger = AdminPaginator::paginate($ledger, $request, 10);

        return view('admin.wallet.ledger', compact('ledger', 'source', 'currency', 'search', 'stats'));
    }

    public function withdrawRevenue(Request $request)
    {
        $currency = strtoupper(trim((string)$request->input('currency', 'USD')));
        $isINR = $currency === 'INR';

        if ($isINR) {
            $validated = $request->validate([
                'currency' => ['nullable', 'string'],
                'amount'   => ['required', 'numeric', 'min:1'],
                'address'  => ['required', 'string', 'min:3', 'max:150'],
                'network'  => ['required', 'string', 'max:50'],
            ]);

            $stats = $this->walletStats();
            $availableRevenue = (float)($stats['inr_platform_revenue'] ?? 0);
            $amount = (float)$validated['amount'];

            if ($amount > $availableRevenue) {
                return back()->with('error', 'Insufficient INR platform revenue. Maximum withdrawable amount is ₹' . number_format($availableRevenue, 2));
            }

            DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                'revenue_uid'     => (string) \Illuminate\Support\Str::uuid(),
                'source'          => 'admin_withdrawal',
                'type'            => 'admin_withdrawal_inr',
                'market'          => 'wallet',
                'user_uid'        => 'admin',
                'amount'          => -abs($amount),
                'amount_inr'      => abs($amount),
                'currency'        => 'INR',
                'wallet_currency' => 'INR',
                'meta'            => ['amount_inr' => abs($amount)],
                'status'          => 'completed',
                'reference'       => 'REV-WD-INR-' . strtoupper(\Illuminate\Support\Str::random(8)),
                'description'     => 'Admin withdrew INR platform revenue to account/UPI ' . $validated['address'] . ' via ' . $validated['network'],
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            if (class_exists(\App\Helpers\AuditLogger::class)) {
                \App\Helpers\AuditLogger::log(
                    'revenue_withdrawal_inr',
                    'wallet',
                    'Admin withdrew INR platform revenue.',
                    [
                        'amount'  => $amount,
                        'address' => $validated['address'],
                        'network' => $validated['network'],
                    ]
                );
            }

            return back()->with('success', 'INR Revenue withdrawal of ₹' . number_format($amount, 2) . ' registered successfully.');
        } else {
            $validated = $request->validate([
                'currency' => ['nullable', 'string'],
                'amount'   => ['required', 'numeric', 'min:0.01'],
                'address'  => ['required', 'string', 'min:10', 'max:150'],
                'network'  => ['required', 'string', 'max:50'],
            ]);

            $stats = $this->walletStats();
            $availableRevenue = (float)($stats['usdt_platform_revenue'] ?? 0);
            $amount = (float)$validated['amount'];

            if ($amount > $availableRevenue) {
                return back()->with('error', 'Insufficient USDT platform revenue. Maximum withdrawable amount is $' . number_format($availableRevenue, 2));
            }

            DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                'revenue_uid'     => (string) \Illuminate\Support\Str::uuid(),
                'source'          => 'admin_withdrawal',
                'type'            => 'admin_withdrawal',
                'market'          => 'wallet',
                'user_uid'        => 'admin',
                'amount'          => -abs($amount),
                'currency'        => 'USD',
                'status'          => 'completed',
                'reference'       => 'REV-WD-' . strtoupper(\Illuminate\Support\Str::random(10)),
                'description'     => 'Admin withdrew USDT platform revenue to address ' . $validated['address'] . ' on network ' . $validated['network'],
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            if (class_exists(\App\Helpers\AuditLogger::class)) {
                \App\Helpers\AuditLogger::log(
                    'revenue_withdrawal',
                    'wallet',
                    'Admin withdrew USDT platform revenue.',
                    [
                        'amount'  => $amount,
                        'address' => $validated['address'],
                        'network' => $validated['network'],
                    ]
                );
            }

            return back()->with('success', 'USDT Revenue withdrawal of $' . number_format($amount, 2) . ' registered successfully.');
        }
    }

    public function transferRevenue(Request $request)
    {
        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:usdt_to_inr,inr_to_usdt'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        $direction = $validated['direction'];
        $amount = (float) $validated['amount'];

        $adminSetting = DB::connection('mongodb')->table('admin_settings')->first();
        $rate = (float)($adminSetting->usdt_inr_rate ?? 85.0);
        if ($rate <= 0) {
            $rate = 85.0;
        }

        $stats = $this->walletStats();
        $usdtRevenue = (float) ($stats['usdt_platform_revenue'] ?? 0);
        $inrRevenue = (float) ($stats['inr_platform_revenue'] ?? 0);

        if ($direction === 'usdt_to_inr') {
            if ($amount > $usdtRevenue) {
                return back()->with('error', 'Insufficient USDT platform revenue. Available: $' . number_format($usdtRevenue, 2));
            }

            $convertedInr = round($amount * $rate, 2);
            $ref = 'TRF-USD-INR-' . strtoupper(\Illuminate\Support\Str::random(8));

            // 1. Debit USDT Revenue
            DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                'revenue_uid' => (string) \Illuminate\Support\Str::uuid(),
                'source' => 'admin_transfer',
                'type' => 'admin_transfer_usdt_out',
                'market' => 'wallet',
                'user_uid' => 'admin',
                'amount' => -abs($amount),
                'currency' => 'USD',
                'wallet_currency' => 'USD',
                'status' => 'completed',
                'reference' => $ref,
                'description' => 'Converted $' . number_format($amount, 2) . ' USDT revenue to ₹' . number_format($convertedInr, 2) . ' INR revenue (Rate: ₹' . $rate . ')',
                'meta' => ['direction' => 'usdt_to_inr', 'rate' => $rate, 'converted_amount_inr' => $convertedInr],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Credit INR Revenue
            DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                'revenue_uid' => (string) \Illuminate\Support\Str::uuid(),
                'source' => 'admin_transfer',
                'type' => 'admin_transfer_inr_in',
                'market' => 'wallet',
                'user_uid' => 'admin',
                'amount' => abs($convertedInr),
                'amount_inr' => abs($convertedInr),
                'currency' => 'INR',
                'wallet_currency' => 'INR',
                'status' => 'completed',
                'reference' => $ref,
                'description' => 'Received ₹' . number_format($convertedInr, 2) . ' INR revenue from $' . number_format($amount, 2) . ' USDT conversion',
                'meta' => ['direction' => 'usdt_to_inr', 'rate' => $rate, 'from_amount_usdt' => $amount],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (class_exists(\App\Helpers\AuditLogger::class)) {
                \App\Helpers\AuditLogger::log(
                    'revenue_transfer',
                    'wallet',
                    "Converted \${$amount} USDT platform revenue to ₹{$convertedInr} INR platform revenue.",
                    ['amount_usdt' => $amount, 'amount_inr' => $convertedInr, 'rate' => $rate]
                );
            }

            return back()->with('success', 'Successfully converted $' . number_format($amount, 2) . ' USDT revenue to ₹' . number_format($convertedInr, 2) . ' INR revenue.');
        } else {
            if ($amount > $inrRevenue) {
                return back()->with('error', 'Insufficient INR platform revenue. Available: ₹' . number_format($inrRevenue, 2));
            }

            $convertedUsdt = round($amount / $rate, 4);
            $ref = 'TRF-INR-USD-' . strtoupper(\Illuminate\Support\Str::random(8));

            // 1. Debit INR Revenue
            DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                'revenue_uid' => (string) \Illuminate\Support\Str::uuid(),
                'source' => 'admin_transfer',
                'type' => 'admin_transfer_inr_out',
                'market' => 'wallet',
                'user_uid' => 'admin',
                'amount' => -abs($convertedUsdt),
                'amount_inr' => -abs($amount),
                'currency' => 'INR',
                'wallet_currency' => 'INR',
                'meta' => ['amount_inr' => -abs($amount), 'converted_amount_usdt' => $convertedUsdt, 'rate' => $rate],
                'status' => 'completed',
                'reference' => $ref,
                'description' => 'Converted ₹' . number_format($amount, 2) . ' INR revenue to $' . number_format($convertedUsdt, 2) . ' USDT revenue (Rate: ₹' . $rate . ')',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Credit USDT Revenue
            DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                'revenue_uid' => (string) \Illuminate\Support\Str::uuid(),
                'source' => 'admin_transfer',
                'type' => 'admin_transfer_usdt_in',
                'market' => 'wallet',
                'user_uid' => 'admin',
                'amount' => abs($convertedUsdt),
                'currency' => 'USD',
                'wallet_currency' => 'USD',
                'status' => 'completed',
                'reference' => $ref,
                'description' => 'Received $' . number_format($convertedUsdt, 2) . ' USDT revenue from ₹' . number_format($amount, 2) . ' INR conversion',
                'meta' => ['direction' => 'inr_to_usdt', 'rate' => $rate, 'from_amount_inr' => $amount],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (class_exists(\App\Helpers\AuditLogger::class)) {
                \App\Helpers\AuditLogger::log(
                    'revenue_transfer',
                    'wallet',
                    "Converted ₹{$amount} INR platform revenue to \${$convertedUsdt} USDT platform revenue.",
                    ['amount_inr' => $amount, 'amount_usdt' => $convertedUsdt, 'rate' => $rate]
                );
            }

            return back()->with('success', 'Successfully converted ₹' . number_format($amount, 2) . ' INR revenue to $' . number_format($convertedUsdt, 2) . ' USDT revenue.');
        }
    }

    public function approveWithdrawal(Request $request, string $id)
    {
        $withdrawal = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->where('_id', $id)
            ->first();

        if (!$withdrawal) {
            return back()->with('error', 'Withdrawal request not found.');
        }

        if (($withdrawal->status ?? 'pending') !== 'pending') {
            return back()->with('error', 'This withdrawal request is already processed.');
        }

        $expectedAddress = trim((string) ($withdrawal->wallet_address ?? $withdrawal->withdrawal_address ?? $withdrawal->user_wallet_address ?? $withdrawal->address ?? ''));
        $confirmedAddress = trim((string) $request->input('confirmed_wallet_address', ''));

        if ($expectedAddress === '') {
            return back()->with('error', 'User withdrawal address is missing. Ask user to save/select withdrawal address again.');
        }

        if ($confirmedAddress === '' || $confirmedAddress !== $expectedAddress) {
            return back()->with('error', 'Exact user withdrawal address confirmation is required before approval.');
        }

        $userUid = (string) ($withdrawal->user_uid ?? '');
        $amount = round((float) ($withdrawal->amount ?? 0), 8);
        $withdrawFee = round((float) ($withdrawal->withdraw_fee ?? 0), 8);
        $platformCharge = round((float) ($withdrawal->platform_charge ?? 0), 8);
        $totalCharge = round((float) ($withdrawal->total_charge ?? ($withdrawFee + $platformCharge)), 8);
        $netAmount = round((float) ($withdrawal->net_amount ?? ($amount - $totalCharge)), 8);
        $reference = $withdrawal->reference ?? ('WD-' . strtoupper(substr(md5($id), 0, 10)));

        $wallet = DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $userUid)
            ->first();

        // Current balances for balance_after ledger tracking
        $available    = (float) ($wallet->demo_main_balance ?? $wallet->demo_available_balance ?? 0);
        $availableInr = (float) ($wallet->demo_main_balance_inr ?? ($available * (float)($withdrawal->rate ?? 1.0)));

        $networkUpper = strtoupper(trim((string)($withdrawal->network ?? $withdrawal->currency ?? '')));
        [$isCryptoAsset, $cryptoAsset] = $this->withdrawalCryptoAsset($withdrawal);
        $isINR = ($withdrawal->currency ?? '') === 'INR' || ($withdrawal->wallet_currency ?? '') === 'INR' || ($withdrawal->method ?? '') === 'INR_BANK' || !empty($withdrawal->amount_inr);
        $amountInr = (float) ($withdrawal->amount_inr ?? $withdrawal->amount ?? 0);

        if ($isCryptoAsset) {
            app(\App\Services\DemoWalletService::class)->adjustLiveCrypto(
                $userUid,
                $this->withdrawalLiveAccountUid($userUid, $withdrawal),
                $cryptoAsset,
                0.0,
                -$amount
            );
        } elseif ($isINR && ($withdrawal->balance_source ?? '') === 'real') {
            app(\App\Services\DemoWalletService::class)->adjustRealInrBalance(
                $userUid,
                $this->withdrawalLiveAccountUid($userUid, $withdrawal),
                0.0,
                -$amountInr
            );
        } elseif ($isINR) {
            $currInrPending = (float) ($wallet->demo_withdrawal_locked_balance_inr ?? 0);
            $newInrPending = max(0, round($currInrPending - $amountInr, 2));

            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->update([
                    'demo_withdrawal_locked_balance_inr' => $newInrPending,
                    'updated_at' => now(),
                ]);
        } else {
            $pendingWithdrawal = max(round((float) ($wallet->demo_withdrawal_locked_balance ?? 0) - $amount, 8), 0);

            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->update([
                    'demo_withdrawal_locked_balance' => $pendingWithdrawal,
                    'updated_at' => now(),
                ]);
        }

        $onChainTxHash = null;
        $onChainStatus = 'manual';

        // On-chain broadcast from the Admin Vault only supports the USDT token contract;
        // every other asset is paid out manually by the admin.
        if (config('kryvex_wallet.switches.withdraw_onchain_enabled', false) && $isCryptoAsset && $cryptoAsset === 'USDT') {
            if (in_array($networkUpper, ['TRC20', 'TRON'], true) || (str_starts_with($confirmedAddress, 'T') && strlen($confirmedAddress) === 34)) {
                // TRON (TRC-20)
                $tronService = app(\App\Services\TronCustodyService::class);
                $broadcastRes = $tronService->broadcastTrc20Withdrawal($confirmedAddress, $netAmount);

                if (!$broadcastRes['success']) {
                    return back()->with('error', 'On-chain TRC-20 broadcast failed: ' . ($broadcastRes['message'] ?? 'Check Vault TRX gas and USDT balance'));
                }

                $onChainTxHash = $broadcastRes['tx_hash'] ?? null;
                $onChainStatus = 'broadcasted';
            } elseif (in_array($networkUpper, ['BEP20', 'BSC', 'BNB', 'ERC20', 'ETH', 'POLYGON', 'MATIC', 'USDT', 'USDC'], true) || str_starts_with($confirmedAddress, '0x')) {
                // EVM (BEP-20 BSC / ERC-20 ETH / Polygon)
                $multiChainService = app(\App\Services\MultiChainCustodyService::class);
                $broadcastRes = $multiChainService->broadcastEvmWithdrawal($networkUpper, $confirmedAddress, $netAmount);

                if (!$broadcastRes['success']) {
                    return back()->with('error', 'On-chain ' . ($networkUpper ?: 'EVM') . ' broadcast failed: ' . ($broadcastRes['message'] ?? 'Check Vault BNB gas and USDT balance'));
                }

                $onChainTxHash = $broadcastRes['tx_hash'] ?? null;
                $onChainStatus = 'broadcasted';
            }
        }

        DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->where('_id', $id)
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
                'processed_at' => now(),
                'processed_by' => session('admin_email') ?? 'admin',
                'approved_wallet_address' => $confirmedAddress,
                'on_chain_tx_hash' => $onChainTxHash,
                'on_chain_status' => $onChainStatus,
                'updated_at' => now(),
            ]);

        $isINR = ($withdrawal->currency ?? '') === 'INR' || ($withdrawal->wallet_currency ?? '') === 'INR';
        $rate = (float)($withdrawal->rate ?? 1.0);
        if ($rate <= 0) $rate = 1.0;

        if ($isINR) {
            $amountInr = (float)($withdrawal->amount_inr ?? ($amount * $rate));
            $withdrawFeeInr = (float)($withdrawal->amount_inr_fee ?? (($withdrawal->withdraw_fee ?? 0) * $rate));
            $platformChargeInr = (float)($withdrawal->amount_inr_platform ?? (($withdrawal->platform_charge ?? 0) * $rate));
            $totalChargeInr = (float)($withdrawal->amount_inr_charge ?? ($withdrawFeeInr + $platformChargeInr));
            $netAmountInr = (float)($withdrawal->amount_inr_net ?? ($amountInr - $totalChargeInr));
            $netLabel = '₹' . number_format($netAmountInr, 2);

            DB::connection('mongodb')
                ->table('wallet_ledgers')
                ->where('user_uid', $userUid)
                ->where('reference', $reference)
                ->whereIn('type', ['demo_withdrawal_request', 'inr_withdrawal_request'])
                ->update([
                    'status' => 'completed',
                    'note' => 'INR Withdrawal approved by admin. Net receive: ₹' . number_format($netAmountInr, 2) . '.',
                    'updated_at' => now(),
                ]);

            if ($withdrawFeeInr > 0) {
                DB::connection('mongodb')->table('wallet_ledgers')->insert([
                    'ledger_uid' => (string) \Illuminate\Support\Str::uuid(),
                    'user_uid' => $userUid,
                    'wallet_currency' => 'INR',
                    'currency' => 'INR',
                    'type' => 'withdrawal_fee_charged',
                    'category' => 'withdrawal_fee',
                    'direction' => 'debit',
                    'amount_inr' => $withdrawFeeInr,
                    'amount' => $withdrawFee,
                    'balance_after' => round($availableInr, 2),
                    'status' => 'completed',
                    'reference' => $reference,
                    'note' => 'Withdrawal fee charged after admin approval: ₹' . number_format($withdrawFeeInr, 2) . '.',
                    'meta' => ['amount_inr' => $withdrawFeeInr, 'rate' => $rate],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);


                DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                    'revenue_uid' => (string) \Illuminate\Support\Str::uuid(),
                    'source' => 'withdrawal_fee',
                    'type' => 'withdrawal_fee_income',
                    'market' => 'wallet',
                    'user_uid' => $userUid,
                    'amount_inr' => $withdrawFeeInr,
                    'amount' => $withdrawFee,
                    'currency' => 'INR',
                    'wallet_currency' => 'INR',
                    'status' => 'completed',
                    'reference' => $reference,
                    'description' => 'INR Withdrawal fee revenue after approval.',
                    'meta' => ['amount_inr' => $withdrawFeeInr, 'rate' => $rate],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($platformChargeInr > 0) {
                DB::connection('mongodb')->table('wallet_ledgers')->insert([
                    'ledger_uid' => (string) \Illuminate\Support\Str::uuid(),
                    'user_uid' => $userUid,
                    'wallet_currency' => 'INR',
                    'currency' => 'INR',
                    'type' => 'platform_charge_charged',
                    'category' => 'platform_charge',
                    'direction' => 'debit',
                    'amount_inr' => $platformChargeInr,
                    'amount' => $platformCharge,
                    'balance_after' => round($availableInr, 2),

                    'status' => 'completed',
                    'reference' => $reference,
                    'note' => 'Platform charge applied after withdrawal approval: ₹' . number_format($platformChargeInr, 2) . '.',
                    'meta' => ['amount_inr' => $platformChargeInr, 'rate' => $rate],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                    'revenue_uid' => (string) \Illuminate\Support\Str::uuid(),
                    'source' => 'platform_charge',
                    'type' => 'platform_charge_income',
                    'market' => 'wallet',
                    'user_uid' => $userUid,
                    'amount_inr' => $platformChargeInr,
                    'amount' => $platformCharge,
                    'currency' => 'INR',
                    'wallet_currency' => 'INR',
                    'status' => 'completed',
                    'reference' => $reference,
                    'description' => 'INR Platform charge revenue after withdrawal approval.',
                    'meta' => ['amount_inr' => $platformChargeInr, 'rate' => $rate],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } else {
            // Fees are held in the withdrawn asset; platform revenue is always booked in USD.
            $feeAsset = $isCryptoAsset ? $cryptoAsset : 'USD';
            $isUsdAsset = in_array($feeAsset, ['USD', 'USDT', 'USDC'], true);
            $assetUsdPrice = $isUsdAsset
                ? 1.0
                : (float) ($withdrawal->asset_usd_price ?? app(\App\Services\DemoWalletService::class)->withdrawAssetUsdPrice($feeAsset));
            $formatAsset = fn (float $value) => $isUsdAsset
                ? '$' . number_format($value, 2)
                : rtrim(rtrim(number_format($value, 8, '.', ''), '0'), '.') . ' ' . $feeAsset;
            $netLabel = $formatAsset($netAmount);

            DB::connection('mongodb')
                ->table('wallet_ledgers')
                ->where('user_uid', $userUid)
                ->where('reference', $reference)
                ->whereIn('type', ['demo_withdrawal_request', 'withdrawal_request'])
                ->update([
                    'status' => 'completed',
                    'note' => 'Withdrawal approved by admin. Net amount: ' . $formatAsset($netAmount) . '.',
                    'updated_at' => now(),
                ]);

            if ($withdrawFee > 0) {
                DB::connection('mongodb')->table('wallet_ledgers')->insert([
                    'ledger_uid' => (string) \Illuminate\Support\Str::uuid(),
                    'user_uid' => $userUid,
                    'wallet_currency' => $feeAsset,
                    'currency' => $feeAsset,
                    'type' => 'withdrawal_fee_charged',
                    'category' => 'withdrawal_fee',
                    'direction' => 'debit',
                    'amount' => $withdrawFee,
                    'balance_after' => round($available, 8),
                    'status' => 'completed',
                    'reference' => $reference,
                    'note' => 'Withdrawal fee charged after admin approval: ' . $formatAsset($withdrawFee) . '.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                    'revenue_uid' => (string) \Illuminate\Support\Str::uuid(),
                    'source' => 'withdrawal_fee',
                    'type' => 'withdrawal_fee_income',
                    'market' => 'wallet',
                    'user_uid' => $userUid,
                    'amount' => round($withdrawFee * $assetUsdPrice, 8),
                    'currency' => 'USD',
                    'status' => 'completed',
                    'reference' => $reference,
                    'description' => 'Withdrawal fee revenue after approval.',
                    'meta' => ['asset' => $feeAsset, 'asset_amount' => $withdrawFee, 'asset_usd_price' => $assetUsdPrice],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($platformCharge > 0) {
                DB::connection('mongodb')->table('wallet_ledgers')->insert([
                    'ledger_uid' => (string) \Illuminate\Support\Str::uuid(),
                    'user_uid' => $userUid,
                    'wallet_currency' => $feeAsset,
                    'currency' => $feeAsset,
                    'type' => 'platform_charge_charged',
                    'category' => 'platform_charge',
                    'direction' => 'debit',
                    'amount' => $platformCharge,
                    'balance_after' => round($available, 8),
                    'status' => 'completed',
                    'reference' => $reference,
                    'note' => 'Platform charge applied after withdrawal approval: ' . $formatAsset($platformCharge) . '.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::connection('mongodb')->table('platform_revenue_ledgers')->insert([
                    'revenue_uid' => (string) \Illuminate\Support\Str::uuid(),
                    'source' => 'platform_charge',
                    'type' => 'platform_charge_income',
                    'market' => 'wallet',
                    'user_uid' => $userUid,
                    'amount' => round($platformCharge * $assetUsdPrice, 8),
                    'currency' => 'USD',
                    'status' => 'completed',
                    'reference' => $reference,
                    'description' => 'Platform charge revenue after withdrawal approval.',
                    'meta' => ['asset' => $feeAsset, 'asset_amount' => $platformCharge, 'asset_usd_price' => $assetUsdPrice],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (class_exists(\App\Helpers\AuditLogger::class)) {
            \App\Helpers\AuditLogger::log(
                'approve',
                'wallet',
                'Withdrawal request approved.',
                [
                    'withdrawal_id' => $id,
                    'user_uid' => $userUid,
                    'amount' => $amount,
                    'withdraw_fee' => $withdrawFee,
                    'platform_charge' => $platformCharge,
                    'net_amount' => $netAmount,
                    'wallet_address' => $confirmedAddress,
                ]
            );
        }

        AdminNotificationController::createAndEmailForUser(
            $userUid,
            'approval',
            'Withdrawal Approved',
            'Your withdrawal request has been approved. You will receive ' . $netLabel . ' after charges.',
            ['withdrawal_id' => $id, 'net_amount' => $netAmount]
        );

        return back()->with('success', 'Withdrawal approved successfully. Notification and email sent. User receives ' . $netLabel . ' after charges.');
    }

    public function rejectWithdrawal(Request $request, string $id)
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $withdrawal = DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->where('_id', $id)
            ->first();

        if (!$withdrawal) {
            return back()->with('error', 'Withdrawal request not found.');
        }

        if (($withdrawal->status ?? 'pending') !== 'pending') {
            return back()->with('error', 'This withdrawal request is already processed.');
        }

        $userUid = (string) ($withdrawal->user_uid ?? '');
        $amount = (float) ($withdrawal->amount ?? 0);
        $reference = $withdrawal->reference ?? null;

        $wallet = DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $userUid)
            ->first();

        if (!$wallet) {
            return back()->with('error', 'User wallet not found.');
        }

        $networkUpper = strtoupper(trim((string)($withdrawal->network ?? $withdrawal->currency ?? '')));
        [$isCryptoAsset, $cryptoAsset] = $this->withdrawalCryptoAsset($withdrawal);
        $isINR = ($withdrawal->currency ?? '') === 'INR' || ($withdrawal->wallet_currency ?? '') === 'INR' || ($withdrawal->method ?? '') === 'INR_BANK' || !empty($withdrawal->amount_inr);
        $amountInr = (float) ($withdrawal->amount_inr ?? $withdrawal->amount ?? 0);

        if ($isCryptoAsset) {
            $refund = app(\App\Services\DemoWalletService::class)->adjustLiveCrypto(
                $userUid,
                $this->withdrawalLiveAccountUid($userUid, $withdrawal),
                $cryptoAsset,
                $amount,
                -$amount
            );
            $newAvailable = $refund['balance'];
        } elseif ($isINR && ($withdrawal->balance_source ?? '') === 'real') {
            $liveState = app(\App\Services\DemoWalletService::class)->adjustRealInrBalance(
                $userUid,
                $this->withdrawalLiveAccountUid($userUid, $withdrawal),
                $amountInr,
                -$amountInr
            );
            $newAvailable = $liveState['real_main_balance_inr'];
        } elseif ($isINR) {
            $currInrMain = (float) ($wallet->demo_main_balance_inr ?? 0);
            $currInrPending = (float) ($wallet->demo_withdrawal_locked_balance_inr ?? 0);

            $newInrMain = round($currInrMain + $amountInr, 2);
            $newAvailable = $newInrMain;
            $newInrPending = max(0, round($currInrPending - $amountInr, 2));

            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->update([
                    'demo_main_balance_inr' => $newInrMain,
                    'demo_withdrawal_locked_balance_inr' => $newInrPending,
                    'updated_at' => now(),
                ]);
        } else {
            $available = (float) ($wallet->demo_main_balance ?? $wallet->demo_available_balance ?? 0);
            $newAvailable = round($available + $amount, 8);
            $pendingWithdrawal = max(round((float) ($wallet->demo_withdrawal_locked_balance ?? 0) - $amount, 8), 0);

            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->update([
                    'demo_main_balance' => $newAvailable,
                    'demo_withdrawal_locked_balance' => $pendingWithdrawal,
                    'updated_at' => now(),
                ]);
        }

        DB::connection('mongodb')
            ->table('wallet_withdrawal_requests')
            ->where('_id', $id)
            ->update([
                'status' => 'rejected',
                'admin_note' => $validated['admin_note'] ?? null,
                'rejected_at' => now(),
                'processed_at' => now(),
                'processed_by' => session('admin_email') ?? 'admin',
                'updated_at' => now(),
            ]);

        // Mark original pending debit row as rejected so user ledger status matches the request status.
        DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->where('user_uid', $userUid)
            ->where('reference', $reference)
            ->whereIn('type', ['demo_withdrawal_request', 'inr_withdrawal_request'])
            ->update([
                'status' => 'rejected',
                'note' => 'Withdrawal request rejected by admin. Full amount refunded.',
                'updated_at' => now(),
            ]);

        DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->insert([
                'ledger_uid' => (string) \Illuminate\Support\Str::uuid(),
                'user_uid' => $userUid,
                'wallet_currency' => $isCryptoAsset ? $networkUpper : 'USD',
                'currency' => $isCryptoAsset ? $networkUpper : 'USD',
                'type' => 'demo_withdrawal_rejected_refund',
                'category' => 'withdrawal',
                'direction' => 'credit',
                'amount' => $amount,
                'balance_after' => $newAvailable,
                'status' => 'completed',
                'reference' => $reference,
                'description' => 'Withdrawal rejected. Amount returned to available balance.',
                'note' => 'Withdrawal rejected by admin. Amount returned to available balance.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if (class_exists(\App\Helpers\AuditLogger::class)) {
            \App\Helpers\AuditLogger::log(
                'reject',
                'wallet',
                'Withdrawal request rejected and amount returned.',
                [
                    'withdrawal_id' => $id,
                    'user_uid' => $userUid,
                    'amount' => $amount,
                ]
            );
        }

        AdminNotificationController::createAndEmailForUser(
            (string) $userUid,
            'rejection',
            'Withdrawal Rejected',
            'Your withdrawal request was rejected. The amount has been returned to your wallet.' . (!empty($validated['admin_note']) ? ' Note: ' . $validated['admin_note'] : ''),
            ['withdrawal_id' => $id]
        );

        return back()->with('success', 'Withdrawal rejected and amount returned to user balance. Notification and email sent.');
    }


    /**
     * Live account a withdrawal was taken from; older requests without one belong to the Main Account.
     */
    private function withdrawalLiveAccountUid(string $userUid, object $withdrawal): string
    {
        $walletService = app(\App\Services\DemoWalletService::class);
        try {
            return $walletService->resolveLiveAccountUid($userUid, $withdrawal->account_uid ?? null);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $walletService->resolveLiveAccountUid($userUid, null);
        }
    }

    private function walletStats(): array
    {
        $wallets = DB::connection('mongodb')->table('wallets')->get();
        $ledger = DB::connection('mongodb')->table('wallet_ledgers')->limit(10000)->get();
        $withdrawals = DB::connection('mongodb')->table('wallet_withdrawal_requests')->limit(10000)->get();
        $revenueLedgers = DB::connection('mongodb')->table('platform_revenue_ledgers')->get();

        $users = DB::connection('mongodb')->table('users')->get()->keyBy(function ($u) {
            return (string)($u->uid ?? $u->_id ?? '');
        });

        $inrMainBalance = 0;
        $usdtMainBalance = 0;
        $usdtAvailableBalance = 0;
        $usdtLockedBalance = 0;

        foreach ($wallets as $wallet) {
            $userUid = (string)($wallet->user_uid ?? $wallet->uid ?? '');
            $user = $users->get($userUid);
            $baseCurrency = strtoupper((string)($user->base_currency ?? 'USDT'));

            $mainUsdt = (float) ($wallet->demo_main_balance ?? 0);
            $mainInr = (float) ($wallet->demo_main_balance_inr ?? 0);
            $available = (float) ($wallet->demo_available_balance ?? 0);
            $locked = (float) ($wallet->demo_locked_balance ?? 0);

            $inrMainBalance += $mainInr;
            $usdtMainBalance += $mainUsdt;

            // Trading balances are always in USDT
            $usdtAvailableBalance += $available;
            $usdtLockedBalance += $locked;
        }

        $usdtTotalBalance = $usdtMainBalance + $usdtAvailableBalance + $usdtLockedBalance;

        // Platform Revenues Breakdown
        $usdtTradingFee = 0;
        $usdtWithdrawalFee = 0;
        $usdtPlatformCharge = 0;
        $usdtTodayRevenue = 0;
        $usdtTotalWithdrawn = 0;

        $inrTradingFee = 0;
        $inrWithdrawalFee = 0;
        $inrPlatformCharge = 0;
        $inrTodayRevenue = 0;
        $inrTotalWithdrawn = 0;

        foreach ($revenueLedgers as $row) {
            $status = strtolower((string)($row->status ?? 'completed'));
            if ($status !== 'completed' && $status !== '') {
                continue;
            }

            $userUid = (string)($row->user_uid ?? '');
            $user = $users->get($userUid);

            $rowCurrency = strtoupper((string)($row->currency ?? $row->wallet_currency ?? ''));
            $rowType = strtolower((string)($row->type ?? ''));
            $rowSource = strtolower((string)($row->source ?? ''));

            // Trading fees are strictly USDT revenue regardless of user's base currency
            $isTradingFee = str_contains($rowSource, 'trading_fee') || str_contains($rowType, 'trading_fee');

            $isINR = false;
            if (!$isTradingFee) {
                if ($rowCurrency === 'INR' || str_contains($rowType, 'inr') || str_contains($rowSource, 'inr')) {
                    $isINR = true;
                }
            }

            $amount = (float)($row->amount ?? 0);
            $isToday = !empty($row->created_at) && \Carbon\Carbon::parse($row->created_at)->isToday();

            // Check if this entry is an admin withdrawal
            $isAdminWithdrawal = ($rowSource === 'admin_withdrawal' || $rowType === 'admin_withdrawal' || $rowType === 'admin_withdrawal_inr');

            if ($isAdminWithdrawal) {
                $wdAmt = abs((float)($row->meta['amount_inr'] ?? $row->amount_inr ?? $amount));
                if ($isINR) {
                    $inrTotalWithdrawn += $wdAmt;
                } else {
                    $usdtTotalWithdrawn += abs($amount);
                }
                continue;
            }

            $isAdminTransfer = ($rowSource === 'admin_transfer' || str_contains($rowType, 'admin_transfer'));

            if ($isAdminTransfer) {
                if ($isINR) {
                    $amtInr = (float)($row->amount_inr ?? $row->meta['amount_inr'] ?? $amount);
                    $inrPlatformCharge += $amtInr;
                } else {
                    $usdtPlatformCharge += $amount;
                }
                continue;
            }

            if ($isINR) {
                $amtInr = (float)($row->meta['amount_inr'] ?? $row->amount_inr ?? $amount);

                if (str_contains($rowSource, 'withdrawal_fee') || str_contains($rowType, 'withdrawal_fee')) {
                    $inrWithdrawalFee += $amtInr;
                } elseif (str_contains($rowSource, 'platform_charge') || str_contains($rowType, 'platform_charge')) {
                    $inrPlatformCharge += $amtInr;
                } else {
                    $inrPlatformCharge += $amtInr;
                }

                if ($isToday && $amtInr > 0) {
                    $inrTodayRevenue += $amtInr;
                }
            } else {
                if (str_contains($rowSource, 'withdrawal_fee') || str_contains($rowType, 'withdrawal_fee')) {
                    $usdtWithdrawalFee += $amount;
                } elseif (str_contains($rowSource, 'platform_charge') || str_contains($rowType, 'platform_charge')) {
                    $usdtPlatformCharge += $amount;
                } else {
                    $usdtTradingFee += $amount;
                }

                if ($isToday && $amount > 0) {
                    $usdtTodayRevenue += $amount;
                }
            }
        }

        $inrGrossRevenue = max(0, $inrWithdrawalFee + $inrPlatformCharge);
        $usdtGrossRevenue = max(0, $usdtTradingFee + $usdtWithdrawalFee + $usdtPlatformCharge);

        $inrTotalRevenue = $inrGrossRevenue;
        $usdtTotalRevenue = $usdtGrossRevenue;

        $inrAvailableRevenue = max(0, $inrTotalRevenue - $inrTotalWithdrawn);
        $usdtAvailableRevenue = max(0, $usdtTotalRevenue - $usdtTotalWithdrawn);

        if ($revenueLedgers->isEmpty()) {
            $revenueFromWalletLedger = $ledger->filter(fn ($row) => $this->isRevenueLedger($row));
            foreach ($revenueFromWalletLedger as $row) {
                $userUid = (string)($row->user_uid ?? '');
                $user = $users->get($userUid);
                $isINR = ($row->currency ?? '') === 'INR' || ($row->wallet_currency ?? '') === 'INR' || str_contains(strtolower((string)($row->type ?? '')), 'inr') || (($user->base_currency ?? '') === 'INR');
                $amt = (float)($row->amount ?? 0);
                $amtInr = (float)($row->meta['amount_inr'] ?? $row->amount_inr ?? $amt);
                $isToday = !empty($row->created_at) && \Carbon\Carbon::parse($row->created_at)->isToday();
                $type = strtolower((string)($row->type ?? ''));

                if ($isINR) {
                    if (str_contains($type, 'withdrawal_fee')) {
                        $inrWithdrawalFee += $amtInr;
                    } elseif (str_contains($type, 'platform_charge')) {
                        $inrPlatformCharge += $amtInr;
                    } else {
                        $inrTradingFee += $amtInr;
                    }
                    if ($isToday && $amtInr > 0) $inrTodayRevenue += $amtInr;
                } else {
                    if (str_contains($type, 'withdrawal_fee')) {
                        $usdtWithdrawalFee += $amt;
                    } elseif (str_contains($type, 'platform_charge')) {
                        $usdtPlatformCharge += $amt;
                    } else {
                        $usdtTradingFee += $amt;
                    }
                    if ($isToday && $amt > 0) $usdtTodayRevenue += $amt;
                }
            }
            $usdtTotalRevenue = $usdtTradingFee + $usdtWithdrawalFee + $usdtPlatformCharge;
            $inrTotalRevenue = $inrTradingFee + $inrWithdrawalFee + $inrPlatformCharge;
            $usdtAvailableRevenue = $usdtTotalRevenue;
        }

        $depositLedger = $ledger->filter(function ($item) {
            return $this->isDepositLedger($item);
        });

        $withdrawalLedger = $ledger->filter(function ($item) {
            return $this->isWithdrawalLedger($item);
        });

        // Deposit Stats Split
        $inrDepositAmount = 0;
        $inrDepositCount = 0;
        $usdtDepositAmount = 0;
        $usdtDepositCount = 0;

        foreach ($depositLedger as $item) {
            $userUid = (string)($item->user_uid ?? '');
            $user = $users->get($userUid);
            $isINR = ($item->currency ?? '') === 'INR' || 
                     ($item->wallet_currency ?? '') === 'INR' || 
                     str_contains(strtolower((string)($item->type ?? '')), 'inr') ||
                     (($user->base_currency ?? '') === 'INR');

            if ($isINR) {
                $amtInr = (float)($item->meta['amount_inr'] ?? $item->amount_inr ?? $item->amount ?? 0);
                $inrDepositAmount += $amtInr;
                $inrDepositCount++;
            } else {
                $usdtDepositAmount += (float)($item->amount ?? 0);
                $usdtDepositCount++;
            }
        }

        // Withdrawal Requests Stats Split
        $inrPendingWdAmount = 0;
        $inrPendingWdCount = 0;
        $inrApprovedWdAmount = 0;
        $inrApprovedWdCount = 0;
        $inrRejectedWdCount = 0;

        $usdtPendingWdAmount = 0;
        $usdtPendingWdCount = 0;
        $usdtApprovedWdAmount = 0;
        $usdtApprovedWdCount = 0;
        $usdtRejectedWdCount = 0;

        foreach ($withdrawals as $item) {
            $userUid = (string)($item->user_uid ?? '');
            $user = $users->get($userUid);
            $isINR = ($item->currency ?? '') === 'INR' || 
                     ($item->method ?? '') === 'INR_BANK' || 
                     !empty($item->amount_inr) ||
                     str_contains(strtolower((string)($item->type ?? '')), 'inr') ||
                     (($user->base_currency ?? '') === 'INR');

            $status = (string)($item->status ?? 'pending');
            $amtInr = (float)($item->amount_inr ?? $item->amount ?? 0);
            // Non-USD crypto withdrawals are totalled by their USD value at request time.
            $amtUsdt = (float)($item->amount ?? 0) * (float)($item->asset_usd_price ?? 1);

            if ($isINR) {
                if ($status === 'pending') {
                    $inrPendingWdAmount += $amtInr;
                    $inrPendingWdCount++;
                } elseif ($status === 'approved') {
                    $inrApprovedWdAmount += $amtInr;
                    $inrApprovedWdCount++;
                } elseif ($status === 'rejected') {
                    $inrRejectedWdCount++;
                }
            } else {
                if ($status === 'pending') {
                    $usdtPendingWdAmount += $amtUsdt;
                    $usdtPendingWdCount++;
                } elseif ($status === 'approved') {
                    $usdtApprovedWdAmount += $amtUsdt;
                    $usdtApprovedWdCount++;
                } elseif ($status === 'rejected') {
                    $usdtRejectedWdCount++;
                }
            }
        }

        $pendingWithdrawals = $withdrawals->filter(function ($item) {
            return ($item->status ?? 'pending') === 'pending';
        });

        $approvedWithdrawals = $withdrawals->filter(function ($item) {
            return ($item->status ?? '') === 'approved';
        });

        $rejectedWithdrawals = $withdrawals->filter(function ($item) {
            return ($item->status ?? '') === 'rejected';
        });

        return [
            // INR Specific stats
            'inr_total_balance' => round($inrMainBalance, 2),
            'inr_main_balance' => round($inrMainBalance, 2),
            'inr_platform_revenue' => round($inrAvailableRevenue, 2),
            'inr_total_revenue' => round($inrTotalRevenue, 2),
            'inr_total_withdrawn' => round($inrTotalWithdrawn, 2),
            'inr_trading_fee_income' => round($inrTradingFee, 2),
            'inr_withdrawal_fee_income' => round($inrWithdrawalFee, 2),
            'inr_platform_charge_income' => round($inrPlatformCharge, 2),
            'inr_today_revenue' => round($inrTodayRevenue, 2),
            'inr_total_deposit_amount' => round($inrDepositAmount, 2),
            'inr_total_deposit_count' => $inrDepositCount,
            'inr_pending_withdrawal_amount' => round($inrPendingWdAmount, 2),
            'inr_pending_withdrawals' => $inrPendingWdCount,
            'inr_approved_withdrawal_amount' => round($inrApprovedWdAmount, 2),
            'inr_approved_withdrawals' => $inrApprovedWdCount,
            'inr_rejected_withdrawals' => $inrRejectedWdCount,

            // USDT Specific stats
            'usdt_total_balance' => round($usdtTotalBalance, 2),
            'usdt_main_balance' => round($usdtMainBalance, 2),
            'usdt_trading_balance' => round($usdtAvailableBalance + $usdtLockedBalance, 2),
            'usdt_available_balance' => round($usdtAvailableBalance, 2),
            'usdt_locked_balance' => round($usdtLockedBalance, 2),
            'usdt_platform_revenue' => round($usdtAvailableRevenue, 2),
            'usdt_total_revenue' => round($usdtTotalRevenue, 2),
            'usdt_total_withdrawn' => round($usdtTotalWithdrawn, 2),
            'usdt_trading_fee_income' => round($usdtTradingFee, 2),
            'usdt_withdrawal_fee_income' => round($usdtWithdrawalFee, 2),
            'usdt_platform_charge_income' => round($usdtPlatformCharge, 2),
            'usdt_today_revenue' => round($usdtTodayRevenue, 2),
            'usdt_total_deposit_amount' => round($usdtDepositAmount, 2),
            'usdt_total_deposit_count' => $usdtDepositCount,
            'usdt_pending_withdrawal_amount' => round($usdtPendingWdAmount, 2),
            'usdt_pending_withdrawals' => $usdtPendingWdCount,
            'usdt_approved_withdrawal_amount' => round($usdtApprovedWdAmount, 2),
            'usdt_approved_withdrawals' => $usdtApprovedWdCount,
            'usdt_rejected_withdrawals' => $usdtRejectedWdCount,
            'usdt_today_revenue' => round($usdtTodayRevenue, 2),

            // Backwards compatibility / General stats
            'total_balance' => round($usdtTotalBalance, 2),
            'main_balance' => round($usdtMainBalance + $inrMainBalance, 2),
            'available_balance' => round($usdtAvailableBalance, 2),
            'locked_balance' => round($usdtLockedBalance, 2),
            'withdrawal_locked_balance' => round($wallets->sum(fn ($w) => (float)($w->demo_withdrawal_locked_balance ?? 0)), 2),
            'total_transactions' => $ledger->count(),
            'total_deposit_amount' => round($depositLedger->sum(function ($item) {
                return (float) ($item->amount ?? 0);
            }), 2),
            'total_deposit_count' => $depositLedger->count(),
            'total_withdrawal_amount' => round($approvedWithdrawals->sum(function ($item) {
                return (float) ($item->amount ?? 0);
            }), 2),
            'total_withdrawal_count' => $approvedWithdrawals->count(),
            'withdrawal_ledger_count' => $withdrawalLedger->count(),
            'pending_withdrawals' => $pendingWithdrawals->count(),
            'pending_withdrawal_amount' => round($pendingWithdrawals->sum(function ($item) {
                return (float) ($item->amount ?? 0);
            }), 2),
            'approved_withdrawals' => $approvedWithdrawals->count(),
            'approved_withdrawal_amount' => round($approvedWithdrawals->sum(function ($item) {
                return (float) ($item->amount ?? 0);
            }), 2),
            'rejected_withdrawals' => $rejectedWithdrawals->count(),
            'platform_revenue' => round($usdtAvailableRevenue, 2),
            'trading_fee_income' => round($usdtTradingFee, 2),
            'withdrawal_fee_income' => round($usdtWithdrawalFee, 2),
            'platform_charge_income' => round($usdtPlatformCharge, 2),
            'platform_total_revenue' => round($usdtTotalRevenue, 2),
            'platform_total_withdrawn' => round($usdtTotalWithdrawn, 2),
            'platform_today_revenue' => round($usdtTodayRevenue, 2),
        ];
    }

    private function isRevenueLedger(object $row): bool
    {
        $type = strtolower((string) ($row->type ?? ''));
        $category = strtolower((string) ($row->category ?? ''));
        $status = strtolower((string) ($row->status ?? 'completed'));

        if ($status !== 'completed') {
            return false;
        }

        return str_contains($type, 'trading_fee')
            || str_contains($type, 'withdrawal_fee')
            || str_contains($type, 'platform_charge')
            || str_contains($category, 'revenue')
            || str_contains($category, 'fee');
    }

    private function isDepositLedger(object $item): bool
    {
        $type = strtolower((string)($item->type ?? ''));
        $category = strtolower((string)($item->category ?? ''));
        $direction = strtolower((string)($item->direction ?? ''));

        return str_contains($type, 'deposit') ||
            str_contains($type, 'demo_credit') ||
            str_contains($type, 'fund') ||
            str_contains($category, 'deposit') ||
            ($direction === 'credit' && str_contains($type, 'wallet'));
    }

    private function isWithdrawalLedger(object $item): bool
    {
        $type = strtolower((string)($item->type ?? ''));
        $category = strtolower((string)($item->category ?? ''));

        return str_contains($type, 'withdrawal') ||
            str_contains($type, 'withdraw') ||
            str_contains($category, 'withdrawal');
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

    private function attachUser(object $item, $users): void
    {
        $uid = (string)($item->user_uid ?? $item->uid ?? '');
        $user = $users->get($uid);

        $item->display_user_name = $item->user_name
            ?? $item->name
            ?? ($user->name ?? 'Unknown User');

        $item->display_user_email = $item->user_email
            ?? $item->email
            ?? ($user->email ?? '-');

        $item->display_user_base_currency = $user->base_currency ?? null;

        $source = strtolower((string)($item->source ?? ''));
        $type = strtolower((string)($item->type ?? ''));
        $isTradingFee = str_contains($source, 'trading_fee') || str_contains($type, 'trading_fee');

        $isINR = false;
        if (!$isTradingFee) {
            $isINR = ($item->currency ?? '') === 'INR' || 
                     ($item->wallet_currency ?? '') === 'INR' || 
                     ($item->method ?? '') === 'INR_BANK' ||
                     !empty($item->meta['amount_inr'] ?? null) ||
                     !empty($item->amount_inr ?? null) ||
                     str_contains($type, 'inr') ||
                     str_contains($source, 'inr');
        }

        $cryptoCurrency = strtoupper((string) ($item->currency ?? ''));
        $isNonUsdCrypto = !$isINR
            && isset(\App\Services\DemoWalletService::WITHDRAW_ASSET_NETWORKS[$cryptoCurrency])
            && !in_array($cryptoCurrency, ['USDT', 'USDC'], true);

        $item->is_inr = $isINR;
        $item->display_currency_symbol = $isINR ? '₹' : ($isNonUsdCrypto ? $cryptoCurrency . ' ' : '$');
        $item->display_decimals = $isNonUsdCrypto ? 8 : 2;
        $item->display_amount = $isINR ? (float)($item->meta['amount_inr'] ?? $item->amount_inr ?? $item->amount ?? 0) : (float)($item->amount ?? 0);
        $item->display_net_amount = $isINR ? (float)($item->meta['amount_inr'] ?? $item->amount_inr ?? $item->net_amount ?? $item->amount ?? 0) : (float)($item->net_amount ?? $item->amount ?? 0);
    }

    public function internalTransfers(Request $request)
    {
        $stats = $this->walletStats();
        $users = $this->usersMap();

        $search = trim((string)$request->query('search', ''));
        $currency = strtoupper(trim((string)$request->query('currency', '')));

        $query = DB::connection('mongodb')
            ->table('wallet_internal_transfers')
            ->orderByDesc('created_at');

        if (!empty($currency) && $currency !== 'ALL') {
            $query->where('currency', $currency);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sender_name', 'like', "%{$search}%")
                  ->orWhere('recipient_name', 'like', "%{$search}%")
                  ->orWhere('recipient_address', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('currency', 'like', "%{$search}%")
                  ->orWhere('note', 'like', "%{$search}%");
            });
        }

        $allRecords = $query->get();
        $totalCount = $allRecords->count();
        $totalAmount = $allRecords->sum(function ($item) {
            return (float) ($item->amount ?? 0);
        });

        $mappedRecords = $allRecords->map(function ($item) use ($users) {
            $item->id_str = (string)($item->_id ?? $item->id ?? '');
            
            $senderUid = (string)($item->sender_user_uid ?? '');
            $sender = $users->get($senderUid);
            $item->sender_email = $sender->email ?? '-';

            $recipientUid = (string)($item->recipient_user_uid ?? '');
            $recipient = $users->get($recipientUid);
            $item->recipient_email = $recipient->email ?? '-';

            return $item;
        });

        $transfers = AdminPaginator::paginate($mappedRecords, $request, 15);

        return view('admin.wallet.internal_transfers', compact('stats', 'transfers', 'search', 'currency', 'totalCount', 'totalAmount'));
    }
}
