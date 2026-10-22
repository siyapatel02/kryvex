<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DemoWalletService
{
    /**
     * Resolve user's active broker platform ('kryvex' or 'mt5').
     */
    public function getActiveBrokerType(string $userUid, ?string $brokerType = null): string
    {
        if (!empty($brokerType) && in_array(strtolower($brokerType), ['kryvex', 'mt5'], true)) {
            return strtolower($brokerType);
        }

        $userRecord = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->first();

        if (!$userRecord && class_exists('\MongoDB\BSON\ObjectId') && strlen($userUid) === 24 && ctype_xdigit($userUid)) {
            try {
                $bsonClass = '\MongoDB\BSON\ObjectId';
                $userRecord = DB::connection('mongodb')
                    ->table('users')
                    ->where('_id', new $bsonClass($userUid))
                    ->first();
            } catch (\Throwable $e) {}
        }

        if (!$userRecord) {
            $userRecord = DB::connection('mongodb')
                ->table('users')
                ->where('_id', $userUid)
                ->first();
        }

        $userArr = (array) $userRecord;
        $broker = strtolower($userArr['broker_type'] ?? 'kryvex');
        return in_array($broker, ['kryvex', 'mt5'], true) ? $broker : 'kryvex';
    }

    /**
     * Resolve account_uid to a valid trading account for this user and broker.
     */
    public function resolveAccountUid(string $userUid, ?string $accountUid = null, ?string $brokerType = null, bool $liveOnly = false, bool $demoOnly = false): string
    {
        $broker = $this->getActiveBrokerType($userUid, $brokerType);

        $tradingMode = null;
        if ($demoOnly) {
            $tradingMode = 'demo';
        } elseif (! $liveOnly) {
            try {
                if (request()) {
                    $headerMode = request()->header('X-Trading-Mode') ?: (request()->query('trading_mode') ?: request()->input('trading_mode'));
                    if (!empty($headerMode)) {
                        $tradingMode = strtolower(trim($headerMode));
                    }
                }
            } catch (\Throwable $e) {}
        }

        if (!empty($accountUid)) {
            $acc = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('account_uid', $accountUid)
                ->where('status', 'active')
                ->first();
            if ($acc) {
                $accArr = (array) $acc;
                $isDemo = (($accArr['account_type'] ?? '') === 'demo') || !empty($accArr['is_demo']);
                if ($demoOnly && $isDemo) {
                    return (string) $accArr['account_uid'];
                }
                if ($liveOnly && !$isDemo) {
                    return (string) $accArr['account_uid'];
                }
                if (!$liveOnly && !$demoOnly) {
                    if (!$isDemo || $tradingMode === 'demo') {
                        return (string) $accArr['account_uid'];
                    }
                }
            }
        }

        if ($tradingMode === 'demo') {
            $demo = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where(function ($q) {
                    $q->where('account_type', 'demo')->orWhere('is_demo', true);
                })
                ->first();
            if ($demo) {
                return (string) ((array) $demo)['account_uid'];
            }
            $app = app(\App\Http\Controllers\Api\TradingAccountController::class);
            $newDemo = $app->getOrCreateDemoAccount($userUid, $broker);
            return (string) $newDemo['account_uid'];
        }

        $default = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->where('is_default', true)
            ->first();

        if (!$default && $broker === 'kryvex') {
            $default = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('is_default', true)
                ->first();

            if ($default) {
                $defaultArr = (array) $default;
                if (empty($defaultArr['broker_type'])) {
                    DB::connection('mongodb')
                        ->table('trading_accounts')
                        ->where('user_uid', $userUid)
                        ->where('account_uid', $defaultArr['account_uid'])
                        ->update(['broker_type' => 'kryvex']);
                    $defaultArr['broker_type'] = 'kryvex';
                }
                return (string) $defaultArr['account_uid'];
            }
        }

        if ($default) {
            return (string) ((array) $default)['account_uid'];
        }

        // Auto-create default Main Account if missing for this broker
        $app = app(\App\Http\Controllers\Api\TradingAccountController::class);
        $newAcc = $app->getOrCreateDefaultAccount($userUid, $broker);
        return (string) $newAcc['account_uid'];
    }

    /**
     * Get user demo wallet or create a new one scoped by broker_type.
     * Integrates sub-trading accounts.
     */
    /**
     * Wallet resolved against a live (real) trading account only, ignoring demo mode and demo accounts.
     * Deposits and withdrawals must always use this.
     */
    public function getLiveWallet(string $userUid, ?string $accountUid = null, ?string $brokerType = null): array
    {
        return $this->getOrCreateWallet($userUid, $accountUid, $brokerType, true);
    }

    public function isDemoAccountUid(string $userUid, ?string $accountUid): bool
    {
        if (empty($accountUid)) {
            return false;
        }

        $acc = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('account_uid', $accountUid)
            ->first();

        if (! $acc) {
            return false;
        }

        $accArr = (array) $acc;
        return (($accArr['account_type'] ?? '') === 'demo') || !empty($accArr['is_demo']);
    }

    public function getOrCreateWallet(string $userUid, ?string $accountUid = null, ?string $brokerType = null, bool $liveOnly = false): array
    {
        $broker = $this->getActiveBrokerType($userUid, $brokerType);

        $wallet = DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->first();

        if (!$wallet && $broker === 'kryvex') {
            $wallet = DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->first();

            if ($wallet) {
                $walletArr = (array) $wallet;
                if (empty($walletArr['broker_type'])) {
                    $wUid = $walletArr['wallet_uid'] ?? (string) Str::uuid();
                    $walletArr['wallet_uid'] = $wUid;
                    $walletArr['broker_type'] = 'kryvex';
                    DB::connection('mongodb')
                        ->table('wallets')
                        ->where('user_uid', $userUid)
                        ->update([
                            'broker_type' => 'kryvex',
                            'wallet_uid' => $wUid,
                        ]);
                }
            }
        }

        if (!$wallet) {
            $newWallet = [
                'wallet_uid' => (string) Str::uuid(),
                'user_uid' => $userUid,
                'broker_type' => $broker,
                'currency' => 'USD',
                'real_main_balance' => 0.0,
                'real_main_balance_inr' => 0.0,
                'real_available_balance' => 0.0,
                'real_locked_balance' => 0.0,
                'real_available_balance_inr' => 0.0,
                'real_locked_balance_inr' => 0.0,
                'demo_main_balance' => 0.0,
                'demo_main_balance_inr' => 0.0,
                'demo_available_balance' => 0.0,
                'demo_locked_balance' => 0.0,
                'demo_withdrawal_locked_balance' => 0.0,
                'demo_withdrawal_locked_balance_inr' => 0.0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            try {
                DB::connection('mongodb')->table('wallets')->insert($newWallet);
                $walletArr = $newWallet;
            } catch (\Throwable $e) {
                $walletArr = (array) DB::connection('mongodb')
                    ->table('wallets')
                    ->where('user_uid', $userUid)
                    ->where('broker_type', $broker)
                    ->first();
            }
        } else {
            $walletArr = (array) $wallet;
        }

        if (empty($walletArr['wallet_uid'])) {
            $walletArr['wallet_uid'] = (string) Str::uuid();
            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->update(['wallet_uid' => $walletArr['wallet_uid']]);
        }

        // Auto-generate unique internal addresses per currency for user
        $allAssets = ['USD', 'INR', 'BTC', 'ETH', 'USDC', 'USDT', 'BNB', 'SOL', 'XRP', 'ADA'];
        $internalAddresses = (array) ($walletArr['internal_addresses'] ?? []);
        $needsAddressSave = false;

        foreach ($allAssets as $ast) {
            if (empty($internalAddresses[$ast])) {
                $hash = strtoupper(substr(md5($userUid . '_' . $ast . '_kryvex_internal_secret_v1'), 0, 10));
                $internalAddresses[$ast] = "KVX-{$ast}-{$hash}";
                $needsAddressSave = true;
            }
        }

        if ($needsAddressSave) {
            $walletArr['internal_addresses'] = $internalAddresses;
            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->update(['internal_addresses' => $internalAddresses]);
        }

        $resolvedAccountUid = $this->resolveAccountUid($userUid, $accountUid, $broker, $liveOnly);
        $tradingAccount = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->where('account_uid', $resolvedAccountUid)
            ->first();

        $isDemoAccount = false;
        if ($tradingAccount) {
            $trAccArr = (array) $tradingAccount;
            $isDemoAccount = (($trAccArr['account_type'] ?? '') === 'demo') || !empty($trAccArr['is_demo']);
            $walletArr['active_account_uid'] = $trAccArr['account_uid'];
            $walletArr['active_account_name'] = $trAccArr['account_name'];
            $walletArr['account_type'] = $isDemoAccount ? 'demo' : 'live';
            $walletArr['is_demo'] = $isDemoAccount;
            $walletArr['is_default_account'] = (bool) ($trAccArr['is_default'] ?? false);
            $walletArr['broker_type'] = $broker;

            // Fetch the user's dedicated demo account to ensure demo balances are always accurate
            $demoTradingAccount = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_type', 'demo')
                ->first();
            $demoAccArr = $demoTradingAccount ? (array) $demoTradingAccount : [];

            $trDemoAvail = (float) ($demoAccArr['demo_available_balance'] ?? ($isDemoAccount ? ($trAccArr['demo_available_balance'] ?? 0.0) : 0.0));
            $wDemoAvail = (float) ($walletArr['demo_available_balance'] ?? 0.0);
            $demoAvail = $trDemoAvail > 0 ? $trDemoAvail : ($wDemoAvail > 0 ? $wDemoAvail : 60000.0);
            $demoLocked = (float) ($demoAccArr['demo_locked_balance'] ?? ($isDemoAccount ? ($trAccArr['demo_locked_balance'] ?? 0.0) : 0.0));

            $trDemoAvailInr = (float) ($demoAccArr['demo_available_balance_inr'] ?? ($isDemoAccount ? ($trAccArr['demo_available_balance_inr'] ?? 0.0) : 0.0));
            $wDemoAvailInr = (float) ($walletArr['demo_available_balance_inr'] ?? 0.0);
            $demoAvailInr = $trDemoAvailInr > 0 ? $trDemoAvailInr : ($wDemoAvailInr > 0 ? $wDemoAvailInr : ($demoAvail * 95.0));
            $demoLockedInr = (float) ($demoAccArr['demo_locked_balance_inr'] ?? ($isDemoAccount ? ($trAccArr['demo_locked_balance_inr'] ?? 0.0) : 0.0));

            $walletArr['demo_available_balance'] = $demoAvail;
            $walletArr['demo_locked_balance'] = $demoLocked;
            $walletArr['demo_main_balance'] = $demoAvail;
            $walletArr['demo_available_balance_inr'] = $demoAvailInr;
            $walletArr['demo_locked_balance_inr'] = $demoLockedInr;
            $walletArr['demo_main_balance_inr'] = $demoAvailInr;

            if ($isDemoAccount) {
                // Demo Account is active
                $walletArr['real_available_balance'] = 0.0;
                $walletArr['real_locked_balance'] = 0.0;
                $walletArr['real_available_balance_inr'] = 0.0;
                $walletArr['real_locked_balance_inr'] = 0.0;
                $walletArr['real_main_balance'] = 0.0;
                $walletArr['real_main_balance_inr'] = 0.0;
                $walletArr['real_withdrawal_locked_balance'] = 0.0;
                $walletArr['real_withdrawal_locked_balance_inr'] = 0.0;
            } else {
                // Live Account is active (Main Account or Sub Account)
                if (!empty($trAccArr['is_default'])) {
                    // The wallet document is the Main Account's primary store; the account copy is a mirror.
                    $walletArr['real_main_balance'] = (float) ($walletArr['real_main_balance']
                        ?? ($trAccArr['real_main_balance'] ?? ($walletArr['crypto_balances']['USDT'] ?? ($walletArr['real_crypto_balances']['USDT'] ?? 0.0))));
                    $walletArr['real_main_balance_inr'] = (float) ($walletArr['real_main_balance_inr']
                        ?? ($trAccArr['real_main_balance_inr'] ?? 0.0));

                    $rawRealLocked = (float) ($walletArr['real_locked_balance'] ?? ($trAccArr['real_locked_balance'] ?? 0.0));
                    $rawRealAvail = (float) ($walletArr['real_available_balance'] ?? ($trAccArr['real_available_balance'] ?? 0.0));
                    $walletArr['real_locked_balance'] = $rawRealLocked;
                    $walletArr['real_available_balance'] = ($rawRealAvail > 0) ? $rawRealAvail : max(0, $walletArr['real_main_balance'] - $rawRealLocked);

                    $rawRealLockedInr = (float) ($walletArr['real_locked_balance_inr'] ?? ($trAccArr['real_locked_balance_inr'] ?? 0.0));
                    $rawRealAvailInr = (float) ($walletArr['real_available_balance_inr'] ?? ($trAccArr['real_available_balance_inr'] ?? 0.0));
                    $walletArr['real_locked_balance_inr'] = $rawRealLockedInr;
                    $walletArr['real_available_balance_inr'] = ($rawRealAvailInr > 0) ? $rawRealAvailInr : max(0, $walletArr['real_main_balance_inr'] - $rawRealLockedInr);
                } else {
                    $walletArr['real_main_balance'] = (float) ($trAccArr['real_main_balance'] ?? 0.0);
                    $walletArr['real_main_balance_inr'] = (float) ($trAccArr['real_main_balance_inr'] ?? 0.0);
                    $walletArr['real_withdrawal_locked_balance'] = (float) ($trAccArr['real_withdrawal_locked_balance'] ?? 0.0);
                    $walletArr['real_withdrawal_locked_balance_inr'] = (float) ($trAccArr['real_withdrawal_locked_balance_inr'] ?? 0.0);
                    $walletArr['real_locked_balance'] = (float) ($trAccArr['real_locked_balance'] ?? 0.0);
                    $walletArr['real_available_balance'] = (float) ($trAccArr['real_available_balance'] ?? max(0, $walletArr['real_main_balance'] - $walletArr['real_locked_balance']));
                    $walletArr['real_locked_balance_inr'] = (float) ($trAccArr['real_locked_balance_inr'] ?? 0.0);
                    $walletArr['real_available_balance_inr'] = (float) ($trAccArr['real_available_balance_inr'] ?? max(0, $walletArr['real_main_balance_inr'] - $walletArr['real_locked_balance_inr']));
                }
            }
        }

        $allCryptoAssets = ['BTC', 'ETH', 'SOL', 'USDT', 'USDC', 'XRP', 'ADA', 'BNB'];
        $emptyZeroes = array_fill_keys($allCryptoAssets, 0);

        $demoCryptoBals = array_merge($emptyZeroes, (array) ($walletArr['demo_crypto_balances'] ?? []));
        $demoCryptoPends = array_merge($emptyZeroes, (array) ($walletArr['demo_crypto_pending_balances'] ?? []));
        
        // Main Account coins live on the wallet document; every other live account keeps its own coins.
        $isDefaultLive = !empty($trAccArr['is_default']);
        if ($isDefaultLive || empty($trAccArr)) {
            $realCryptoBals = array_merge($emptyZeroes, (array) ($walletArr['crypto_balances'] ?? ($walletArr['real_crypto_balances'] ?? [])));
            $realCryptoPends = array_merge($emptyZeroes, (array) ($walletArr['crypto_pending_balances'] ?? ($walletArr['real_crypto_pending_balances'] ?? [])));
        } else {
            $realCryptoBals = array_merge($emptyZeroes, (array) ($trAccArr['real_crypto_balances'] ?? []));
            $realCryptoPends = array_merge($emptyZeroes, (array) ($trAccArr['real_crypto_pending_balances'] ?? []));
        }

        // Auto-sync USDT balance if real_main_balance exists
        if ((float)($walletArr['real_main_balance'] ?? 0) > 0 && (float)($realCryptoBals['USDT'] ?? 0) <= 0) {
            $realCryptoBals['USDT'] = (float) $walletArr['real_main_balance'];
        }

        $walletArr['demo_crypto_balances'] = $demoCryptoBals;
        $walletArr['demo_crypto_pending_balances'] = $demoCryptoPends;
        $walletArr['real_crypto_balances'] = $realCryptoBals;
        $walletArr['real_crypto_pending_balances'] = $realCryptoPends;

        if ($isDemoAccount) {
            $walletArr['crypto_balances'] = $demoCryptoBals;
            $walletArr['crypto_pending_balances'] = $demoCryptoPends;
        } else {
            // Live account returns real crypto balances
            $walletArr['crypto_balances'] = $realCryptoBals;
            $walletArr['crypto_pending_balances'] = $realCryptoPends;
        }

        $walletArr['real_main_balance'] = (float) ($walletArr['real_main_balance'] ?? 0.0);
        $walletArr['real_main_balance_inr'] = (float) ($walletArr['real_main_balance_inr'] ?? 0.0);
        $walletArr['demo_main_balance'] = (float) ($walletArr['demo_main_balance'] ?? 0.0);
        $walletArr['demo_main_balance_inr'] = (float) ($walletArr['demo_main_balance_inr'] ?? 0.0);

        return $walletArr;
    }

    //transfer separate balance from main to trading account and vice versa
    public function transferDemoBalance(
        string $userUid,
        string $direction,
        float $amount,
        ?string $note = null,
        string $currencyType = 'USDT',
        float $usdtInrRate = 1.0,
        ?string $accountUid = null,
        ?string $brokerType = null
    ): array {
        $amount = round($amount, 8);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => ['Amount must be greater than zero.']]);
        }

        $direction = $direction === 'trading_to_main' ? 'trading_to_main' : 'main_to_trading';
        $currencyType = strtoupper($currencyType) === 'INR' ? 'INR' : 'USDT';

        $doTransfer = function () use ($userUid, $direction, $amount, $note, $currencyType, $usdtInrRate, $accountUid, $brokerType) {
            $broker = $this->getActiveBrokerType($userUid, $brokerType);
            if ($accountUid) {
                $accCheck = DB::connection('mongodb')
                    ->table('trading_accounts')
                    ->where('user_uid', $userUid)
                    ->where('account_uid', $accountUid)
                    ->first();
                if ($accCheck) {
                    $accCheckArr = (array) $accCheck;
                    $broker = strtolower($accCheckArr['broker_type'] ?? $broker);
                }
            }
            $resolvedAccountUid = $this->resolveAccountUid($userUid, $accountUid, $broker);

            $wallet = $this->getOrCreateWallet($userUid, $resolvedAccountUid, $broker);
            $tradingAccount = (array) DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->first();

            $isDemo = (($tradingAccount['account_type'] ?? '') === 'demo') || !empty($tradingAccount['is_demo']);

            $primaryWallet = (array) (DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->where('broker_type', 'kryvex')
                ->first() ?: DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->first() ?: $wallet);

            if ($isDemo) {
                $mainUsdt = round((float) ($wallet['demo_main_balance'] ?? $primaryWallet['demo_main_balance'] ?? $tradingAccount['demo_available_balance'] ?? 0), 8);
                $mainInr = round((float) ($wallet['demo_main_balance_inr'] ?? $primaryWallet['demo_main_balance_inr'] ?? ($mainUsdt * 95.0)), 2);
                $tradingUsdt = round((float) ($tradingAccount['demo_available_balance'] ?? 0), 8);
                $tradingInrUsdt = round((float) ($tradingAccount['demo_available_balance_inr'] ?? ($tradingUsdt * 95.0)), 8);
            } else {
                $wUsdt = (float) ($wallet['real_main_balance'] ?? 0);
                $pUsdt = (float) ($primaryWallet['real_main_balance'] ?? 0);
                $mainUsdt = round(max($wUsdt, $pUsdt), 8);

                $wInr = (float) ($wallet['real_main_balance_inr'] ?? 0);
                $pInr = (float) ($primaryWallet['real_main_balance_inr'] ?? 0);
                $mainInr = round(max($wInr, $pInr), 2);

                $tradingUsdt = round((float) ($tradingAccount['real_available_balance'] ?? 0), 8);
                $tradingInrUsdt = round((float) ($tradingAccount['real_available_balance_inr'] ?? ($tradingUsdt * 95.0)), 8);
            }

            $adminSettingsDoc = (array) DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
            $baseRate = (float) ($adminSettingsDoc['usdt_inr_rate'] ?? 95.0);
            $buyRate = (float) ($adminSettingsDoc['usdt_inr_buy_rate'] ?? $baseRate);
            $sellRate = (float) ($adminSettingsDoc['usdt_inr_sell_rate'] ?? $baseRate);

            if ($buyRate <= 0) $buyRate = 95.0;
            if ($sellRate <= 0) $sellRate = 94.50;
            if ($baseRate <= 0) $baseRate = 95.0;

            $accountName = $tradingAccount['account_name'] ?? 'Trading Account';

            if ($currencyType === 'INR') {
                if ($direction === 'main_to_trading') {
                    if ($amount > ($mainInr + 0.01)) {
                        throw ValidationException::withMessages(['amount' => ['Insufficient INR main balance.']]);
                    }
                    if ($amount >= ($mainInr - 0.01)) {
                        $amount = $mainInr;
                    }
                    $newMainInr = max(0.0, round($mainInr - $amount, 2));
                    $usdtCredit = round($amount / $buyRate, 8);
                    $newTradingInrUsdt = round($tradingInrUsdt + $usdtCredit, 8);

                    $ledgerType = 'main_to_trading_transfer';
                    $ledgerNote = $note ?: "Main INR balance (₹{$amount}) transferred to '{$accountName}' INR trading balance (\${$usdtCredit} USDT) @ ₹{$buyRate}/USD.";
                    $balanceAfter = $newTradingInrUsdt;

                    if ($isDemo) {
                        DB::connection('mongodb')
                            ->table('wallets')
                            ->where('wallet_uid', $wallet['wallet_uid'])
                            ->update([
                                'demo_main_balance_inr' => $newMainInr,
                                'demo_available_balance_inr' => $newTradingInrUsdt,
                                'updated_at' => now(),
                            ]);

                        DB::connection('mongodb')
                            ->table('trading_accounts')
                            ->where('user_uid', $userUid)
                            ->where('broker_type', $broker)
                            ->where('account_uid', $resolvedAccountUid)
                            ->update([
                                'demo_main_balance_inr' => $newMainInr,
                                'demo_available_balance_inr' => $newTradingInrUsdt,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::connection('mongodb')
                            ->table('trading_accounts')
                            ->where('user_uid', $userUid)
                            ->where('broker_type', $broker)
                            ->where('account_uid', $resolvedAccountUid)
                            ->update([
                                'real_main_balance_inr' => $newMainInr,
                                'real_available_balance_inr' => $newTradingInrUsdt,
                                'updated_at' => now(),
                            ]);

                        if (!empty($tradingAccount['is_default'])) {
                            DB::connection('mongodb')->table('wallets')->where('wallet_uid', $wallet['wallet_uid'])->update([
                                'real_main_balance_inr' => $newMainInr,
                                'real_available_balance_inr' => $newTradingInrUsdt,
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $newMainUsdt = $mainUsdt;
                    $newTradingUsdt = $tradingUsdt;
                } else { // trading_to_main (INR mode: $amount is in INR)
                    $tradingInrEquiv = round($tradingInrUsdt * $sellRate, 2);
                    if ($amount > ($tradingInrEquiv + 0.01)) {
                        throw ValidationException::withMessages(['amount' => ['Insufficient trading balance.']]);
                    }
                    if ($amount >= ($tradingInrEquiv - 0.01)) {
                        $amount = $tradingInrEquiv;
                        $usdtDeduct = $tradingInrUsdt;
                    } else {
                        $usdtDeduct = round($amount / $sellRate, 8);
                    }

                    $newMainInr = round($mainInr + $amount, 2);
                    $newTradingInrUsdt = max(0.0, round($tradingInrUsdt - $usdtDeduct, 8));

                    $ledgerType = 'trading_to_main_transfer';
                    $ledgerNote = $note ?: "'{$accountName}' INR trading balance (\${$usdtDeduct} USDT) transferred to main INR balance (₹{$amount}) @ ₹{$sellRate}/USD.";
                    $balanceAfter = $newMainInr;

                    if ($isDemo) {
                        DB::connection('mongodb')
                            ->table('wallets')
                            ->where('wallet_uid', $wallet['wallet_uid'])
                            ->update([
                                'demo_main_balance_inr' => $newMainInr,
                                'demo_available_balance_inr' => $newTradingInrUsdt,
                                'updated_at' => now(),
                            ]);

                        DB::connection('mongodb')
                            ->table('trading_accounts')
                            ->where('user_uid', $userUid)
                            ->where('broker_type', $broker)
                            ->where('account_uid', $resolvedAccountUid)
                            ->update([
                                'demo_main_balance_inr' => $newMainInr,
                                'demo_available_balance_inr' => $newTradingInrUsdt,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::connection('mongodb')
                            ->table('trading_accounts')
                            ->where('user_uid', $userUid)
                            ->where('broker_type', $broker)
                            ->where('account_uid', $resolvedAccountUid)
                            ->update([
                                'real_main_balance_inr' => $newMainInr,
                                'real_available_balance_inr' => $newTradingInrUsdt,
                                'updated_at' => now(),
                            ]);

                        if (!empty($tradingAccount['is_default'])) {
                            DB::connection('mongodb')->table('wallets')->where('wallet_uid', $wallet['wallet_uid'])->update([
                                'real_main_balance_inr' => $newMainInr,
                                'real_available_balance_inr' => $newTradingInrUsdt,
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    $newMainUsdt = $mainUsdt;
                    $newTradingUsdt = $tradingUsdt;
                }
            } else { // USDT
                if ($direction === 'main_to_trading') {
                    if ($amount > ($mainUsdt + 0.00001)) {
                        throw ValidationException::withMessages(['amount' => ['Insufficient USDT main balance.']]);
                    }
                    if ($amount >= ($mainUsdt - 0.0001)) {
                        $amount = $mainUsdt;
                    }
                    $newMainUsdt = max(0.0, round($mainUsdt - $amount, 8));
                    $newTradingUsdt = round($tradingUsdt + $amount, 8);
                    $ledgerType = 'main_to_trading_transfer';
                    $ledgerNote = $note ?: "Main USDT balance transferred to '{$accountName}' USDT trading balance.";
                    $balanceAfter = $newTradingUsdt;

                    if ($isDemo) {
                        DB::connection('mongodb')
                            ->table('wallets')
                            ->where('wallet_uid', $wallet['wallet_uid'])
                            ->update([
                                'demo_main_balance' => $newMainUsdt,
                                'demo_available_balance' => $newTradingUsdt,
                                'updated_at' => now(),
                            ]);

                        DB::connection('mongodb')
                            ->table('trading_accounts')
                            ->where('user_uid', $userUid)
                            ->where('broker_type', $broker)
                            ->where('account_uid', $resolvedAccountUid)
                            ->update([
                                'demo_main_balance' => $newMainUsdt,
                                'demo_available_balance' => $newTradingUsdt,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::connection('mongodb')
                            ->table('trading_accounts')
                            ->where('user_uid', $userUid)
                            ->where('broker_type', $broker)
                            ->where('account_uid', $resolvedAccountUid)
                            ->update([
                                'real_main_balance' => $newMainUsdt,
                                'real_available_balance' => $newTradingUsdt,
                                'updated_at' => now(),
                            ]);

                        DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->update([
                            'real_main_balance' => $newMainUsdt,
                            'crypto_balances.USDT' => $newMainUsdt,
                            'real_crypto_balances.USDT' => $newMainUsdt,
                            'updated_at' => now(),
                        ]);
                    }

                    $newMainInr = $mainInr;
                    $newTradingInrUsdt = $tradingInrUsdt;
                } else { // trading_to_main
                    if ($amount > ($tradingUsdt + 0.00001)) {
                        throw ValidationException::withMessages(['amount' => ['Insufficient USDT trading balance.']]);
                    }
                    if ($amount >= ($tradingUsdt - 0.0001)) {
                        $amount = $tradingUsdt;
                    }
                    $newMainUsdt = round($mainUsdt + $amount, 8);
                    $newTradingUsdt = max(0.0, round($tradingUsdt - $amount, 8));
                    $ledgerType = 'trading_to_main_transfer';
                    $ledgerNote = $note ?: "'{$accountName}' USDT trading balance transferred to main USDT balance.";
                    $balanceAfter = $newMainUsdt;

                    if ($isDemo) {
                        DB::connection('mongodb')
                            ->table('wallets')
                            ->where('wallet_uid', $wallet['wallet_uid'])
                            ->update([
                                'demo_main_balance' => $newMainUsdt,
                                'demo_available_balance' => $newTradingUsdt,
                                'updated_at' => now(),
                            ]);

                        DB::connection('mongodb')
                            ->table('trading_accounts')
                            ->where('user_uid', $userUid)
                            ->where('broker_type', $broker)
                            ->where('account_uid', $resolvedAccountUid)
                            ->update([
                                'demo_main_balance' => $newMainUsdt,
                                'demo_available_balance' => $newTradingUsdt,
                                'updated_at' => now(),
                            ]);
                    } else {
                        DB::connection('mongodb')
                            ->table('trading_accounts')
                            ->where('user_uid', $userUid)
                            ->where('broker_type', $broker)
                            ->where('account_uid', $resolvedAccountUid)
                            ->update([
                                'real_main_balance' => $newMainUsdt,
                                'real_available_balance' => $newTradingUsdt,
                                'updated_at' => now(),
                            ]);

                        DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->update([
                            'real_main_balance' => $newMainUsdt,
                            'crypto_balances.USDT' => $newMainUsdt,
                            'real_crypto_balances.USDT' => $newMainUsdt,
                            'updated_at' => now(),
                        ]);
                    }

                    $newMainInr = $mainInr;
                    $newTradingInrUsdt = $tradingInrUsdt;
                }
            }

            $this->createLedger([
                'user_uid' => $userUid,
                'account_uid' => $resolvedAccountUid,
                'broker_type' => $broker,
                'type' => $ledgerType,
                'category' => 'wallet_transfer',
                'direction' => $direction === 'main_to_trading' ? 'debit' : 'credit',
                'amount' => $amount,
                'currency' => $currencyType === 'INR' ? 'INR' : 'USD',
                'balance_after' => $balanceAfter,
                'reference' => 'TRF-' . strtoupper(Str::random(10)),
                'note' => $ledgerNote,
                'meta' => [
                    'account_uid' => $resolvedAccountUid,
                    'account_name' => $accountName,
                    'transfer_direction' => $direction,
                    'currency_type' => $currencyType,
                    'main_balance_usdt_after' => $newMainUsdt,
                    'main_balance_inr_after' => $newMainInr,
                    'trading_balance_usdt_after' => $newTradingUsdt,
                    'trading_balance_inr_usdt_after' => $newTradingInrUsdt,
                ],
            ]);

            return $this->getOrCreateWallet($userUid, $resolvedAccountUid, $broker);
        };

        return $this->runTransaction($doTransfer);
    }

    /**
     * Lock margin and charge opening trading fee for specific account.
     */
    public function lockDemoMargin(
        string $userUid,
        float $margin,
        float $fee,
        string $orderUid,
        string $module = 'futures',
        ?string $accountUid = null,
        string $currency = 'USDT'
    ): array {
        $margin = round($margin, 8);
        $fee = round($fee, 8);
        $required = round($margin + $fee, 8);
        $module = $this->cleanModule($module);
        $currency = strtoupper($currency) === 'INR' ? 'INR' : 'USDT';

        $fieldAvailable = $currency === 'INR' ? 'demo_available_balance_inr' : 'demo_available_balance';
        $fieldLocked = $currency === 'INR' ? 'demo_locked_balance_inr' : 'demo_locked_balance';
        $symbolPrefix = '$';

        return $this->runTransaction(function () use ($userUid, $margin, $fee, $required, $orderUid, $module, $accountUid, $currency, $fieldAvailable, $fieldLocked, $symbolPrefix) {
            $broker = null;
            if (!empty($accountUid)) {
                $accCheck = DB::connection('mongodb')->table('trading_accounts')
                    ->where('user_uid', $userUid)
                    ->where('account_uid', $accountUid)
                    ->first();
                if ($accCheck && !empty($accCheck->broker_type)) {
                    $broker = strtolower($accCheck->broker_type);
                }
            }
            if (!$broker) {
                $broker = $this->getActiveBrokerType($userUid);
            }

            $resolvedAccountUid = $this->resolveAccountUid($userUid, $accountUid, $broker, false, true);

            $tradingAccount = (array) DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->first();

            $available = round((float) ($tradingAccount[$fieldAvailable] ?? 0), 8);
            $locked = round((float) ($tradingAccount[$fieldLocked] ?? 0), 8);
            $availableAfter = round($available - $required, 8);

            if ($availableAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        'Insufficient demo ' . $currency . ' balance. Required: ' . $symbolPrefix . number_format($required, 2) . '.',
                    ],
                ]);
            }

            $newAvailable = round($available - $required, 8);
            $newLocked = round($locked + $margin, 8);

            $updated = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->where($fieldAvailable, '>=', $required)
                ->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                    'updated_at' => now(),
                ]);

            if (!empty($tradingAccount['is_default']) || !empty($tradingAccount['is_demo']) || ($tradingAccount['account_type'] ?? '') === 'demo') {
                DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->where('broker_type', $broker)->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                ]);
            }

            if (!$updated) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient demo ' . $currency . ' balance or concurrent transaction occurred. Please try again.'],
                ]);
            }

            $balanceAfterMargin = round($available - $margin, 8);

            $this->createLedger([
                'user_uid' => $userUid,
                'account_uid' => $resolvedAccountUid,
                'broker_type' => $broker,
                'type' => $module . '_margin_locked',
                'category' => 'trading_margin',
                'direction' => 'debit',
                'amount' => $margin,
                'currency' => $currency,
                'balance_after' => $balanceAfterMargin,
                'reference' => $orderUid,
                'note' => 'Demo ' . $module . ' margin locked (' . $currency . ').',
            ]);

            if ($fee > 0) {
                $feeType = $module . '_trading_fee_open';

                $this->createLedger([
                    'user_uid' => $userUid,
                    'account_uid' => $resolvedAccountUid,
                    'broker_type' => $broker,
                    'type' => $feeType,
                    'category' => 'trading_fee',
                    'direction' => 'debit',
                    'amount' => $fee,
                    'currency' => $currency,
                    'balance_after' => $availableAfter,
                    'reference' => $orderUid,
                    'note' => 'Opening ' . $module . ' trading fee charged (' . $currency . ').',
                    'meta' => [
                        'module' => $module,
                        'fee_phase' => 'open',
                        'currency' => $currency,
                    ],
                ]);

                $this->recordPlatformRevenue([
                    'source' => 'trading_fee',
                    'type' => $feeType,
                    'market' => $module,
                    'user_uid' => $userUid,
                    'amount' => $fee,
                    'currency' => $currency,
                    'reference' => $orderUid,
                    'description' => 'Opening ' . $module . ' trading fee revenue (' . $currency . ').',
                ]);
            }

            return $this->getOrCreateWallet($userUid, $resolvedAccountUid, $broker);
        });
    }

    // Release margin and credit back to available balance for specific account.
    public function releaseDemoMargin(
        string $userUid,
        float $margin,
        string $reference,
        string $module = 'futures',
        ?string $accountUid = null,
        string $currency = 'USDT'
    ): array {
        $margin = round($margin, 8);
        $module = $this->cleanModule($module);
        $currency = strtoupper($currency) === 'INR' ? 'INR' : 'USDT';

        $fieldAvailable = $currency === 'INR' ? 'demo_available_balance_inr' : 'demo_available_balance';
        $fieldLocked = $currency === 'INR' ? 'demo_locked_balance_inr' : 'demo_locked_balance';

        return $this->runTransaction(function () use ($userUid, $margin, $reference, $module, $accountUid, $currency, $fieldAvailable, $fieldLocked) {
            $broker = $this->getActiveBrokerType($userUid);
            $resolvedAccountUid = $this->resolveAccountUid($userUid, $accountUid, $broker);

            $tradingAccount = (array) DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->first();

            $available = round((float) ($tradingAccount[$fieldAvailable] ?? 0), 8);
            $locked = round((float) ($tradingAccount[$fieldLocked] ?? 0), 8);
            $releaseAmount = min($margin, $locked);
            $newAvailable = round($available + $releaseAmount, 8);
            $newLocked = round($locked - $releaseAmount, 8);

            $updated = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                    'updated_at' => now(),
                ]);

            if (!empty($tradingAccount['is_default']) || !empty($tradingAccount['is_demo']) || ($tradingAccount['account_type'] ?? '') === 'demo') {
                DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->where('broker_type', $broker)->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                ]);
            }

            if (!$updated && $releaseAmount > 0) {
                throw new \Exception('Failed to release demo margin due to concurrent wallet updates.');
            }

            $this->createLedger([
                'user_uid' => $userUid,
                'account_uid' => $resolvedAccountUid,
                'broker_type' => $broker,
                'type' => $module . '_margin_released',
                'category' => 'trading_margin',
                'direction' => 'credit',
                'amount' => $releaseAmount,
                'currency' => $currency,
                'balance_after' => $newAvailable,
                'reference' => $reference,
                'note' => 'Demo ' . $module . ' margin released (' . $currency . ').',
            ]);

            return $this->getOrCreateWallet($userUid, $resolvedAccountUid, $broker);
        });
    }

    /**
     * Close a position: release margin, apply P&L and charge closing trading fee. for sepcific account.
     */
    public function settleClosedDemoPosition(
        string $userUid,
        float $margin,
        float $realizedPnl,
        float $closingFee,
        string $reference,
        string $module = 'futures',
        ?string $accountUid = null,
        string $currency = 'USDT'
    ): array {
        $margin = round($margin, 8);
        $realizedPnl = round($realizedPnl, 8);
        $closingFee = round($closingFee, 8);
        $module = $this->cleanModule($module);
        $currency = strtoupper($currency) === 'INR' ? 'INR' : 'USDT';

        $fieldAvailable = $currency === 'INR' ? 'demo_available_balance_inr' : 'demo_available_balance';
        $fieldLocked = $currency === 'INR' ? 'demo_locked_balance_inr' : 'demo_locked_balance';

        return $this->runTransaction(function () use ($userUid, $margin, $realizedPnl, $closingFee, $reference, $module, $accountUid, $currency, $fieldAvailable, $fieldLocked) {
            $broker = null;
            if (!empty($accountUid)) {
                $accCheck = DB::connection('mongodb')->table('trading_accounts')
                    ->where('user_uid', $userUid)
                    ->where('account_uid', $accountUid)
                    ->first();
                if ($accCheck && !empty($accCheck->broker_type)) {
                    $broker = strtolower($accCheck->broker_type);
                }
            }
            if (!$broker) {
                $broker = $this->getActiveBrokerType($userUid);
            }

            $resolvedAccountUid = $this->resolveAccountUid($userUid, $accountUid, $broker, false, true);

            $tradingAccount = (array) DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->first();

            $available = round((float) ($tradingAccount[$fieldAvailable] ?? 0), 8);
            $locked = round((float) ($tradingAccount[$fieldLocked] ?? 0), 8);
            $releaseMargin = min($margin, $locked);

            $balanceAfterMargin = round($available + $releaseMargin, 8);
            $balanceAfterPnl = round($balanceAfterMargin + $realizedPnl, 8);
            $newAvailable = round($balanceAfterPnl - $closingFee, 8);
            if ($newAvailable < 0) {
                $newAvailable = 0.0;
            }
            $newLocked = round($locked - $releaseMargin, 8);

            $adminSettings = DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
            $inrRate = (float) ($adminSettings->usdt_inr_rate ?? 90.0);
            if ($inrRate <= 0) { $inrRate = 90.0; }

            $pnlUsdToAdd = $currency === 'INR' ? ($realizedPnl / $inrRate) : $realizedPnl;
            $pnlInrToAdd = $currency === 'INR' ? $realizedPnl : ($realizedPnl * $inrRate);

            $currentRealizedUsd = (float) ($tradingAccount['demo_realized_pnl'] ?? 0);
            $currentRealizedInr = (float) ($tradingAccount['demo_realized_pnl_inr'] ?? 0);

            $newRealizedUsd = round($currentRealizedUsd + $pnlUsdToAdd, 4);
            $newRealizedInr = round($currentRealizedInr + $pnlInrToAdd, 4);

            $updated = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                    'demo_realized_pnl' => $newRealizedUsd,
                    'demo_realized_pnl_inr' => $newRealizedInr,
                    'updated_at' => now(),
                ]);

            if (!empty($tradingAccount['is_default']) || !empty($tradingAccount['is_demo']) || ($tradingAccount['account_type'] ?? '') === 'demo') {
                $walletRow = DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->where('broker_type', $broker)->first();
                $wRealizedUsd = (float) ($walletRow->demo_realized_pnl ?? 0);
                $wRealizedInr = (float) ($walletRow->demo_realized_pnl_inr ?? 0);

                DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->where('broker_type', $broker)->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                    'demo_realized_pnl' => round($wRealizedUsd + $pnlUsdToAdd, 4),
                    'demo_realized_pnl_inr' => round($wRealizedInr + $pnlInrToAdd, 4),
                ]);
            }

            if (!$updated) {
                throw new \Exception('Failed to settle closed position due to concurrent wallet updates.');
            }

            if ($releaseMargin > 0) {
                $this->createLedger([
                    'user_uid' => $userUid,
                    'account_uid' => $resolvedAccountUid,
                    'type' => $module . '_margin_released',
                    'category' => 'trading_margin',
                    'direction' => 'credit',
                    'amount' => $releaseMargin,
                    'currency' => $currency,
                    'balance_after' => $balanceAfterMargin,
                    'reference' => $reference,
                    'note' => 'Demo ' . $module . ' margin released on close (' . $currency . ').',
                ]);
            }

            if ($realizedPnl != 0.0) {
                $this->createLedger([
                    'user_uid' => $userUid,
                    'account_uid' => $resolvedAccountUid,
                    'type' => $module . '_realized_pnl',
                    'category' => 'trading_pnl',
                    'direction' => $realizedPnl >= 0 ? 'credit' : 'debit',
                    'amount' => abs($realizedPnl),
                    'currency' => $currency,
                    'balance_after' => $balanceAfterPnl,
                    'reference' => $reference,
                    'note' => 'Demo ' . $module . ' realized P&L on close (' . $currency . ').',
                ]);
            }

            if ($closingFee > 0) {
                $feeType = $module . '_trading_fee_close';

                $this->createLedger([
                    'user_uid' => $userUid,
                    'account_uid' => $resolvedAccountUid,
                    'type' => $feeType,
                    'category' => 'trading_fee',
                    'direction' => 'debit',
                    'amount' => $closingFee,
                    'currency' => $currency,
                    'balance_after' => $newAvailable,
                    'reference' => $reference,
                    'note' => 'Closing ' . $module . ' trading fee charged (' . $currency . ').',
                    'meta' => [
                        'module' => $module,
                        'fee_phase' => 'close',
                        'currency' => $currency,
                    ],
                ]);

                $this->recordPlatformRevenue([
                    'source' => 'trading_fee',
                    'type' => $feeType,
                    'market' => $module,
                    'user_uid' => $userUid,
                    'amount' => $closingFee,
                    'currency' => $currency,
                    'reference' => $reference,
                    'description' => 'Closing ' . $module . ' trading fee revenue (' . $currency . ').',
                ]);
            }

            return $this->getOrCreateWallet($userUid, $resolvedAccountUid);
        });
    }

    /**
     * Lock margin and charge opening trading fee for Live (Real) account.
     */
    public function lockRealMargin(
        string $userUid,
        float $margin,
        float $fee,
        string $orderUid,
        string $module = 'futures',
        ?string $accountUid = null,
        string $currency = 'USDT'
    ): array {
        $margin = round($margin, 8);
        $fee = round($fee, 8);
        $required = round($margin + $fee, 8);
        $module = $this->cleanModule($module);
        $currency = strtoupper($currency) === 'INR' ? 'INR' : 'USDT';

        $fieldAvailable = $currency === 'INR' ? 'real_available_balance_inr' : 'real_available_balance';
        $fieldLocked = $currency === 'INR' ? 'real_locked_balance_inr' : 'real_locked_balance';
        $symbolPrefix = $currency === 'INR' ? '₹' : '$';

        return $this->runTransaction(function () use ($userUid, $margin, $fee, $required, $orderUid, $module, $accountUid, $currency, $fieldAvailable, $fieldLocked, $symbolPrefix) {
            $broker = $this->getActiveBrokerType($userUid);
            $resolvedAccountUid = $this->resolveAccountUid($userUid, $accountUid, $broker);

            $tradingAccount = (array) DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->first();

            $available = round((float) ($tradingAccount[$fieldAvailable] ?? 0), 8);
            $locked = round((float) ($tradingAccount[$fieldLocked] ?? 0), 8);
            $availableAfter = round($available - $required, 8);

            if ($availableAfter < 0) {
                throw ValidationException::withMessages([
                    'quantity' => [
                        'Insufficient live ' . $currency . ' balance. Required: ' . $symbolPrefix . number_format($required, 2) . '. Please deposit funds into your Live wallet.',
                    ],
                ]);
            }

            $newAvailable = round($available - $required, 8);
            $newLocked = round($locked + $margin, 8);

            $updated = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->where($fieldAvailable, '>=', $required)
                ->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                    'updated_at' => now(),
                ]);

            if (!empty($tradingAccount['is_default'])) {
                DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->where('broker_type', $broker)->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                ]);
            }

            if (!$updated) {
                throw ValidationException::withMessages([
                    'quantity' => ['Insufficient live ' . $currency . ' balance or concurrent transaction occurred. Please try again.'],
                ]);
            }

            $balanceAfterMargin = round($available - $margin, 8);

            $this->createLedger([
                'user_uid' => $userUid,
                'account_uid' => $resolvedAccountUid,
                'broker_type' => $broker,
                'type' => $module . '_real_margin_locked',
                'category' => 'trading_margin',
                'direction' => 'debit',
                'amount' => $margin,
                'currency' => $currency,
                'balance_after' => $balanceAfterMargin,
                'reference' => $orderUid,
                'note' => 'Real ' . $module . ' margin locked (' . $currency . ').',
            ]);

            if ($fee > 0) {
                $feeType = $module . '_trading_fee_open';

                $this->createLedger([
                    'user_uid' => $userUid,
                    'account_uid' => $resolvedAccountUid,
                    'broker_type' => $broker,
                    'type' => $feeType,
                    'category' => 'trading_fee',
                    'direction' => 'debit',
                    'amount' => $fee,
                    'currency' => $currency,
                    'balance_after' => $availableAfter,
                    'reference' => $orderUid,
                    'note' => 'Opening real ' . $module . ' trading fee charged (' . $currency . ').',
                    'meta' => [
                        'module' => $module,
                        'fee_phase' => 'open',
                        'currency' => $currency,
                        'mode' => 'live',
                    ],
                ]);

                $this->recordPlatformRevenue([
                    'source' => 'trading_fee',
                    'type' => $feeType,
                    'market' => $module,
                    'user_uid' => $userUid,
                    'amount' => $fee,
                    'currency' => $currency,
                    'reference' => $orderUid,
                    'description' => 'Opening real ' . $module . ' trading fee revenue (' . $currency . ').',
                ]);
            }

            return $this->getOrCreateWallet($userUid, $resolvedAccountUid, $broker);
        });
    }

    /**
     * Release real margin and credit back to available balance for specific account.
     */
    public function releaseRealMargin(
        string $userUid,
        float $margin,
        string $reference,
        string $module = 'futures',
        ?string $accountUid = null,
        string $currency = 'USDT'
    ): array {
        $margin = round($margin, 8);
        $module = $this->cleanModule($module);
        $currency = strtoupper($currency) === 'INR' ? 'INR' : 'USDT';

        $fieldAvailable = $currency === 'INR' ? 'real_available_balance_inr' : 'real_available_balance';
        $fieldLocked = $currency === 'INR' ? 'real_locked_balance_inr' : 'real_locked_balance';

        return $this->runTransaction(function () use ($userUid, $margin, $reference, $module, $accountUid, $currency, $fieldAvailable, $fieldLocked) {
            $broker = $this->getActiveBrokerType($userUid);
            $resolvedAccountUid = $this->resolveAccountUid($userUid, $accountUid, $broker);

            $tradingAccount = (array) DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->first();

            $available = round((float) ($tradingAccount[$fieldAvailable] ?? 0), 8);
            $locked = round((float) ($tradingAccount[$fieldLocked] ?? 0), 8);
            $releaseAmount = min($margin, $locked);
            $newAvailable = round($available + $releaseAmount, 8);
            $newLocked = round($locked - $releaseAmount, 8);

            $updated = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                    'updated_at' => now(),
                ]);

            if (!empty($tradingAccount['is_default'])) {
                DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->where('broker_type', $broker)->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                ]);
            }

            if (!$updated && $releaseAmount > 0) {
                throw new \Exception('Failed to release real margin due to concurrent wallet updates.');
            }

            $this->createLedger([
                'user_uid' => $userUid,
                'account_uid' => $resolvedAccountUid,
                'broker_type' => $broker,
                'type' => $module . '_real_margin_released',
                'category' => 'trading_margin',
                'direction' => 'credit',
                'amount' => $releaseAmount,
                'currency' => $currency,
                'balance_after' => $newAvailable,
                'reference' => $reference,
                'note' => 'Real ' . $module . ' margin released (' . $currency . ').',
            ]);

            return $this->getOrCreateWallet($userUid, $resolvedAccountUid, $broker);
        });
    }

    /**
     * Close a real position: release margin, apply P&L and charge closing trading fee.
     */
    public function settleClosedRealPosition(
        string $userUid,
        float $margin,
        float $realizedPnl,
        float $closingFee,
        string $reference,
        string $module = 'futures',
        ?string $accountUid = null,
        string $currency = 'USDT'
    ): array {
        $margin = round($margin, 8);
        $realizedPnl = round($realizedPnl, 8);
        $closingFee = round($closingFee, 8);
        $module = $this->cleanModule($module);
        $currency = strtoupper($currency) === 'INR' ? 'INR' : 'USDT';

        $fieldAvailable = $currency === 'INR' ? 'real_available_balance_inr' : 'real_available_balance';
        $fieldLocked = $currency === 'INR' ? 'real_locked_balance_inr' : 'real_locked_balance';

        return $this->runTransaction(function () use ($userUid, $margin, $realizedPnl, $closingFee, $reference, $module, $accountUid, $currency, $fieldAvailable, $fieldLocked) {
            $broker = $this->getActiveBrokerType($userUid);
            $resolvedAccountUid = $this->resolveAccountUid($userUid, $accountUid, $broker);

            $tradingAccount = (array) DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->first();

            $available = round((float) ($tradingAccount[$fieldAvailable] ?? 0), 8);
            $locked = round((float) ($tradingAccount[$fieldLocked] ?? 0), 8);
            $releaseMargin = min($margin, $locked);

            $balanceAfterMargin = round($available + $releaseMargin, 8);
            $balanceAfterPnl = round($balanceAfterMargin + $realizedPnl, 8);
            $newAvailable = round($balanceAfterPnl - $closingFee, 8);
            if ($newAvailable < 0) {
                $newAvailable = 0.0;
            }
            $newLocked = round($locked - $releaseMargin, 8);

            $adminSettings = DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
            $inrRate = (float) ($adminSettings->usdt_inr_rate ?? 90.0);
            if ($inrRate <= 0) { $inrRate = 90.0; }

            $pnlUsdToAdd = $currency === 'INR' ? ($realizedPnl / $inrRate) : $realizedPnl;
            $pnlInrToAdd = $currency === 'INR' ? $realizedPnl : ($realizedPnl * $inrRate);

            $currentRealizedUsd = (float) ($tradingAccount['real_realized_pnl'] ?? 0);
            $currentRealizedInr = (float) ($tradingAccount['real_realized_pnl_inr'] ?? 0);

            $newRealizedUsd = round($currentRealizedUsd + $pnlUsdToAdd, 4);
            $newRealizedInr = round($currentRealizedInr + $pnlInrToAdd, 4);

            $updated = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $resolvedAccountUid)
                ->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                    'real_realized_pnl' => $newRealizedUsd,
                    'real_realized_pnl_inr' => $newRealizedInr,
                    'updated_at' => now(),
                ]);

            if (!empty($tradingAccount['is_default'])) {
                $walletRow = DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->where('broker_type', $broker)->first();
                $wRealizedUsd = (float) ($walletRow->real_realized_pnl ?? 0);
                $wRealizedInr = (float) ($walletRow->real_realized_pnl_inr ?? 0);

                DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->where('broker_type', $broker)->update([
                    $fieldAvailable => $newAvailable,
                    $fieldLocked => $newLocked,
                    'real_realized_pnl' => round($wRealizedUsd + $pnlUsdToAdd, 4),
                    'real_realized_pnl_inr' => round($wRealizedInr + $pnlInrToAdd, 4),
                ]);
            }

            if (!$updated) {
                throw new \Exception('Failed to settle closed real position due to concurrent wallet updates.');
            }

            if ($releaseMargin > 0) {
                $this->createLedger([
                    'user_uid' => $userUid,
                    'account_uid' => $resolvedAccountUid,
                    'type' => $module . '_real_margin_released',
                    'category' => 'trading_margin',
                    'direction' => 'credit',
                    'amount' => $releaseMargin,
                    'currency' => $currency,
                    'balance_after' => $balanceAfterMargin,
                    'reference' => $reference,
                    'note' => 'Real ' . $module . ' margin released on close (' . $currency . ').',
                ]);
            }

            if ($realizedPnl != 0.0) {
                $this->createLedger([
                    'user_uid' => $userUid,
                    'account_uid' => $resolvedAccountUid,
                    'type' => $module . '_real_realized_pnl',
                    'category' => 'trading_pnl',
                    'direction' => $realizedPnl >= 0 ? 'credit' : 'debit',
                    'amount' => abs($realizedPnl),
                    'currency' => $currency,
                    'balance_after' => $balanceAfterPnl,
                    'reference' => $reference,
                    'note' => 'Real ' . $module . ' realized P&L on close (' . $currency . ').',
                ]);
            }

            if ($closingFee > 0) {
                $feeType = $module . '_trading_fee_close';

                $this->createLedger([
                    'user_uid' => $userUid,
                    'account_uid' => $resolvedAccountUid,
                    'type' => $feeType,
                    'category' => 'trading_fee',
                    'direction' => 'debit',
                    'amount' => $closingFee,
                    'currency' => $currency,
                    'balance_after' => $newAvailable,
                    'reference' => $reference,
                    'note' => 'Closing real ' . $module . ' trading fee charged (' . $currency . ').',
                    'meta' => [
                        'module' => $module,
                        'fee_phase' => 'close',
                        'currency' => $currency,
                        'mode' => 'live',
                    ],
                ]);

                $this->recordPlatformRevenue([
                    'source' => 'trading_fee',
                    'type' => $feeType,
                    'market' => $module,
                    'user_uid' => $userUid,
                    'amount' => $closingFee,
                    'currency' => $currency,
                    'reference' => $reference,
                    'description' => 'Closing real ' . $module . ' trading fee revenue (' . $currency . ').',
                ]);
            }

            return $this->getOrCreateWallet($userUid, $resolvedAccountUid);
        });
    }

    public function syncLockedBalanceFromTradingState(string $userUid, ?string $accountUid = null): array
    {
        $resolvedAccountUid = $this->resolveAccountUid($userUid, $accountUid);

        // USDT locked margin
        $futuresOpenOrdersMarginUsdt = (float) DB::connection('mongodb')
            ->table('futures_orders')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->where('status', 'open')
            ->where('currency', '!=', 'INR')
            ->sum('margin');

        $futuresOpenPositionsMarginUsdt = (float) DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->where('status', 'open')
            ->where('currency', '!=', 'INR')
            ->sum('margin');

        $optionsOpenOrdersMarginUsdt = (float) DB::connection('mongodb')
            ->table('options_orders')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->where('status', 'open')
            ->where('currency', '!=', 'INR')
            ->sum('margin');

        $optionsOpenPositionsMarginUsdt = (float) DB::connection('mongodb')
            ->table('options_positions')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->where('status', 'open')
            ->where('currency', '!=', 'INR')
            ->sum('margin');

        // INR locked margin
        $futuresOpenOrdersMarginInr = (float) DB::connection('mongodb')
            ->table('futures_orders')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->where('status', 'open')
            ->where('currency', 'INR')
            ->sum('margin');

        $futuresOpenPositionsMarginInr = (float) DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->where('status', 'open')
            ->where('currency', 'INR')
            ->sum('margin');

        $optionsOpenOrdersMarginInr = (float) DB::connection('mongodb')
            ->table('options_orders')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->where('status', 'open')
            ->where('currency', 'INR')
            ->sum('margin');

        $optionsOpenPositionsMarginInr = (float) DB::connection('mongodb')
            ->table('options_positions')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->where('status', 'open')
            ->where('currency', 'INR')
            ->sum('margin');

        $expectedLockedUsdt = round(
            $futuresOpenOrdersMarginUsdt +
            $futuresOpenPositionsMarginUsdt +
            $optionsOpenOrdersMarginUsdt +
            $optionsOpenPositionsMarginUsdt,
            8
        );

        $expectedLockedInr = round(
            $futuresOpenOrdersMarginInr +
            $futuresOpenPositionsMarginInr +
            $optionsOpenOrdersMarginInr +
            $optionsOpenPositionsMarginInr,
            8
        );

        DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->update([
                'demo_locked_balance' => $expectedLockedUsdt,
                'demo_locked_balance_inr' => $expectedLockedInr,
                'updated_at' => now(),
            ]);

        $tradingAccount = (array) DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('account_uid', $resolvedAccountUid)
            ->first();

        if (!empty($tradingAccount['is_default'])) {
            DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->update([
                'demo_locked_balance' => $expectedLockedUsdt,
                'demo_locked_balance_inr' => $expectedLockedInr,
            ]);
        }

        return $this->getOrCreateWallet($userUid, $resolvedAccountUid);
    }

    public function withdrawalChargeSettings(): array
    {
        $settings = $this->platformSettings();

        // Fallbacks must match the defaults shown on the admin settings page.
        return [
            'withdrawal_fee_type' => $settings->withdrawal_fee_type ?? 'percentage',
            'withdrawal_fee_value' => isset($settings->withdrawal_fee_value) ? (float) $settings->withdrawal_fee_value : 1.0,
            'platform_charge_type' => $settings->platform_charge_type ?? 'fixed',
            'platform_charge_value' => isset($settings->platform_charge_value) ? (float) $settings->platform_charge_value : 2.0,
        ];
    }

    public function calculateInrWithdrawalCharges(float $amountInr, float $usdtInrRate = 1.0): array
    {
        $settings = $this->withdrawalChargeSettings();
        $rate = $usdtInrRate > 0 ? $usdtInrRate : 1.0;

        $withdrawalFeeInr = $settings['withdrawal_fee_type'] === 'percentage'
            ? round($amountInr * ($settings['withdrawal_fee_value'] / 100), 2)
            : round($settings['withdrawal_fee_value'] * $rate, 2);

        $platformChargeInr = $settings['platform_charge_type'] === 'percentage'
            ? round($amountInr * ($settings['platform_charge_value'] / 100), 2)
            : round($settings['platform_charge_value'] * $rate, 2);

        $totalChargeInr = round($withdrawalFeeInr + $platformChargeInr, 2);
        $netAmountInr = max(round($amountInr - $totalChargeInr, 2), 0);

        $amountUsdt = round($amountInr / $rate, 8);
        $withdrawFeeUsdt = round($withdrawalFeeInr / $rate, 8);
        $platformChargeUsdt = round($platformChargeInr / $rate, 8);
        $totalChargeUsdt = round($totalChargeInr / $rate, 8);
        $netAmountUsdt = round($netAmountInr / $rate, 8);

        return [
            'amount_inr' => round($amountInr, 2),
            'withdraw_fee_inr' => $withdrawalFeeInr,
            'platform_charge_inr' => $platformChargeInr,
            'total_charge_inr' => $totalChargeInr,
            'net_amount_inr' => $netAmountInr,

            'amount' => $amountUsdt,
            'withdraw_fee' => $withdrawFeeUsdt,
            'platform_charge' => $platformChargeUsdt,
            'total_charge' => $totalChargeUsdt,
            'net_amount' => $netAmountUsdt,

            'withdrawal_fee_type' => $settings['withdrawal_fee_type'],
            'withdrawal_fee_value' => $settings['withdrawal_fee_value'],
            'platform_charge_type' => $settings['platform_charge_type'],
            'platform_charge_value' => $settings['platform_charge_value'],
        ];
    }

    public function recordLedger(array $data): void
    {
        $this->createLedger($data);
    }

    public function recordPlatformRevenue(array $data): void
    {
        $amount = round((float) ($data['amount'] ?? 0), 8);

        if ($amount <= 0) {
            return;
        }

        DB::connection('mongodb')
            ->table('platform_revenue_ledgers')
            ->insert([
                'revenue_uid' => (string) Str::uuid(),
                'source' => $data['source'] ?? 'fee',
                'type' => $data['type'] ?? $data['source'] ?? 'fee_income',
                'market' => $data['market'] ?? null,
                'user_uid' => $data['user_uid'] ?? null,
                'account_uid' => $data['account_uid'] ?? null,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => $data['status'] ?? 'completed',
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        if (str_contains($data['type'] ?? '', 'trading_fee') || ($data['source'] ?? '') === 'trading_fee') {
            $this->distributeReferralCommission($data);
        }
    }

    public function distributeReferralCommission(array $data): void
    {
        $userUid = $data['user_uid'] ?? null;
        $feeAmount = round((float) ($data['amount'] ?? 0), 8);

        if (!$userUid || $feeAmount <= 0) {
            return;
        }

        try {
            $trader = (array) DB::connection('mongodb')->table('users')->where('uid', $userUid)->first();
            $referrerUid = $trader['referred_by'] ?? null;

            if (!$referrerUid) {
                return;
            }

            $referrer = (array) DB::connection('mongodb')->table('users')->where('uid', $referrerUid)->first();
            if (empty($referrer['uid'])) {
                return;
            }

            // Referrer's active friends determines tier rate
            $activeCount = DB::connection('mongodb')->table('users')
                ->where('referred_by', $referrerUid)
                ->where('status', 'active')
                ->count();

            // Tier rates: Bronze (20%), Silver (25%), Gold (30%), Platinum (40%)
            $rate = 0.20;
            if ($activeCount >= 50) {
                $rate = 0.40;
            } elseif ($activeCount >= 20) {
                $rate = 0.30;
            } elseif ($activeCount >= 5) {
                $rate = 0.25;
            }

            $commission = round($feeAmount * $rate, 4);
            if ($commission <= 0) {
                return;
            }

            $rewardUid = (string) Str::uuid();
            $friendName = !empty($trader['name']) ? $trader['name'] : (!empty($trader['email']) ? $trader['email'] : 'Referred Friend');
            $currency = strtoupper($data['currency'] ?? 'USDT');
            if ($currency === 'USD') {
                $currency = 'USDT';
            }

            // 1. Record in referral_rewards collection
            DB::connection('mongodb')->table('referral_rewards')->insert([
                'reward_uid' => $rewardUid,
                'referrer_uid' => $referrerUid,
                'referred_user_uid' => $userUid,
                'referred_user_name' => $trader['name'] ?? null,
                'referred_user_email' => $trader['email'] ?? null,
                'from' => $friendName,
                'type' => 'Trading Commission (' . ucfirst($data['market'] ?? 'futures') . ')',
                'market' => $data['market'] ?? 'futures',
                'fee_amount' => $feeAmount,
                'rate_percent' => ($rate * 100),
                'amount' => $commission,
                'currency' => $currency,
                'status' => 'paid',
                'reference' => $data['reference'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 2. Credit commission to referrer's wallet balance
            $refWallet = (array) DB::connection('mongodb')->table('wallets')->where('user_uid', $referrerUid)->first();
            if ($refWallet) {
                $currentDemo = (float) ($refWallet['demo_available_balance'] ?? 0);
                $newDemo = round($currentDemo + $commission, 8);
                $currentMain = (float) ($refWallet['demo_main_balance'] ?? 0);
                $newMain = round($currentMain + $commission, 8);

                DB::connection('mongodb')->table('wallets')
                    ->where('user_uid', $referrerUid)
                    ->update([
                        'demo_available_balance' => $newDemo,
                        'demo_main_balance' => $newMain,
                        'updated_at' => now(),
                    ]);
            }

            // 3. Create wallet ledger entry for referrer
            $this->createLedger([
                'user_uid' => $referrerUid,
                'account_uid' => $refWallet['account_uid'] ?? null,
                'broker_type' => $refWallet['broker_type'] ?? 'crypto',
                'type' => 'referral_commission',
                'category' => 'reward',
                'direction' => 'credit',
                'amount' => $commission,
                'currency' => $currency,
                'reference' => $data['reference'] ?? ('REF-' . strtoupper(Str::random(8))),
                'note' => 'Referral commission earned (' . ($rate * 100) . '%) from ' . $friendName . ' trade.',
                'meta' => [
                    'referred_user_uid' => $userUid,
                    'fee_amount' => $feeAmount,
                    'rate' => $rate,
                ],
            ]);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Referral commission distribution failed: ' . $e->getMessage());
        }
    }

    public function tradingFeeRate(string $market = 'futures'): float
    {
        $settings = $this->platformSettings();
        $cleanMarket = $this->cleanModule($market);

        $rawRate = null;

        if ($cleanMarket === 'options') {
            if ($settings) {
                if (isset($settings->options_trading_fee_rate) && is_numeric($settings->options_trading_fee_rate) && (float) $settings->options_trading_fee_rate > 0) {
                    $rawRate = (float) $settings->options_trading_fee_rate;
                } elseif (isset($settings->options_trading_fee) && is_numeric($settings->options_trading_fee) && (float) $settings->options_trading_fee > 0) {
                    $rawRate = (float) $settings->options_trading_fee;
                } elseif (isset($settings->futures_trading_fee_rate) && is_numeric($settings->futures_trading_fee_rate) && (float) $settings->futures_trading_fee_rate > 0) {
                    $rawRate = (float) $settings->futures_trading_fee_rate;
                } elseif (isset($settings->futures_trading_fee) && is_numeric($settings->futures_trading_fee) && (float) $settings->futures_trading_fee > 0) {
                    $rawRate = (float) $settings->futures_trading_fee;
                } elseif (isset($settings->taker_fee) && is_numeric($settings->taker_fee) && (float) $settings->taker_fee > 0) {
                    $rawRate = (float) $settings->taker_fee;
                }
            }

            if ($rawRate === null) {
                $rawRate = (float) config('kryvex_wallet.demo_options_trading_fee', 0.0005);
            }
        } else {
            if ($settings) {
                if (isset($settings->futures_trading_fee_rate) && is_numeric($settings->futures_trading_fee_rate) && (float) $settings->futures_trading_fee_rate > 0) {
                    $rawRate = (float) $settings->futures_trading_fee_rate;
                } elseif (isset($settings->futures_trading_fee) && is_numeric($settings->futures_trading_fee) && (float) $settings->futures_trading_fee > 0) {
                    $rawRate = (float) $settings->futures_trading_fee;
                } elseif (isset($settings->taker_fee) && is_numeric($settings->taker_fee) && (float) $settings->taker_fee > 0) {
                    $rawRate = (float) $settings->taker_fee;
                }
            }

            if ($rawRate === null) {
                $rawRate = (float) config('kryvex_wallet.demo_trading_fee', 0.0004);
            }
        }

        if ($rawRate > 0.01) {
            return round($rawRate / 100, 8);
        }

        return round($rawRate, 8);
    }

    //check that user has any open trading positions or orders in futures or options
    public function hasOpenTradingExposure(string $userUid): bool
    {
        $futuresCount = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $userUid)
            ->where('status', 'open')
            ->count();

        $openOptions = DB::connection('mongodb')
            ->table('options_positions')
            ->where('user_uid', $userUid)
            ->where('status', 'open')
            ->get();

        $activeOptionsCount = 0;
        foreach ($openOptions as $pos) {
            $pos = (array) $pos;
            $symbol = $pos['option_symbol'] ?? '';
            // Parse YYMMDD expiration date if present, e.g. BTC-260727-63000-P
            if (preg_match('/^[A-Z0-9]+-(\d{2})(\d{2})(\d{2})-/i', $symbol, $m)) {
                try {
                    $expiryDate = \Carbon\Carbon::createFromFormat('ymd', $m[1] . $m[2] . $m[3])->endOfDay();
                    if ($expiryDate->isPast()) {
                        DB::connection('mongodb')->table('options_positions')
                            ->where('position_uid', $pos['position_uid'] ?? '')
                            ->update([
                                'status' => 'closed',
                                'closed_at' => now(),
                                'updated_at' => now(),
                            ]);
                        continue;
                    }
                } catch (\Throwable $e) {
                    // ignore parse exception
                }
            }
            $activeOptionsCount++;
        }

        return ($futuresCount + $activeOptionsCount) > 0;
    }

    public function maxWithdrawable(string $userUid): float
    {
        $mainBalance = (float) ($this->withdrawableCryptoBalances($userUid)['USDT'] ?? 0.0);

        if ($this->hasOpenTradingExposure($userUid)) {
            $reserve = $this->minimumReserve();
            return max(0.0, round($mainBalance - $reserve, 8));
        }

        return round($mainBalance, 8);
    }

    /**
     * Assets that have a wallet balance and can be withdrawn, with the networks allowed for each.
     * Must stay in sync with STEP_DEPOSIT_COINS in resources/views/web/wallet.blade.php.
     */
    public const WITHDRAW_ASSET_NETWORKS = [
        'USDT' => ['TRC20', 'ERC20', 'BEP20', 'SOL', 'POLYGON'],
        'USDC' => ['ERC20', 'TRC20', 'BEP20', 'SOL'],
        'ETH' => ['ERC20', 'BEP20', 'ARB'],
        'BTC' => ['BTC', 'BEP20'],
        'SOL' => ['SOL'],
        'BNB' => ['BEP20'],
        'XRP' => ['XRP'],
        'ADA' => ['ADA'],
    ];

    private const WITHDRAW_ASSET_FALLBACK_PRICES = [
        'USDT' => 1.0, 'USDC' => 1.0, 'BTC' => 65000.0, 'ETH' => 3500.0,
        'SOL' => 150.0, 'BNB' => 600.0, 'XRP' => 0.55, 'ADA' => 0.45,
    ];

    /**
     * Resolve which wallet asset a withdrawal debits.
     * Returns null when the asset/network pair is not withdrawable.
     */
    public function resolveWithdrawAsset(string $network, ?string $currency): ?string
    {
        $network = strtoupper(trim($network));
        $currency = strtoupper(trim((string) $currency));

        if ($currency === '') {
            // Legacy clients that only send the network.
            if (in_array($network, ['TRC20', 'BEP20', 'ERC20', 'POLYGON', 'BSC', 'TRON'], true)) {
                return 'USDT';
            }
            return isset(self::WITHDRAW_ASSET_NETWORKS[$network]) ? $network : null;
        }

        $allowed = self::WITHDRAW_ASSET_NETWORKS[$currency] ?? null;
        if ($allowed === null || !in_array($network, $allowed, true)) {
            return null;
        }

        return $currency;
    }

    /**
     * USD price per unit for withdrawable assets (cached for 60 seconds).
     */
    public function withdrawAssetUsdPrices(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('withdraw_asset_usd_prices', 60, function () {
            $prices = self::WITHDRAW_ASSET_FALLBACK_PRICES;
            $symbols = [];
            foreach (array_keys($prices) as $asset) {
                if (!in_array($asset, ['USDT', 'USDC'], true)) {
                    $symbols[] = $asset . 'USDT';
                }
            }

            try {
                $response = \Illuminate\Support\Facades\Http::timeout(3)->get('https://api.binance.com/api/v3/ticker/price', [
                    'symbols' => json_encode($symbols),
                ]);
                if ($response->successful()) {
                    foreach ((array) $response->json() as $row) {
                        $asset = substr((string) ($row['symbol'] ?? ''), 0, -4);
                        $price = (float) ($row['price'] ?? 0);
                        if ($price > 0 && isset($prices[$asset])) {
                            $prices[$asset] = $price;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Keep fallback prices.
            }

            return $prices;
        });
    }

    public function withdrawAssetUsdPrice(string $asset): float
    {
        $prices = $this->withdrawAssetUsdPrices();
        return (float) ($prices[strtoupper($asset)] ?? 1.0);
    }

    /**
     * Per-asset balances available for crypto withdrawal.
     * Must resolve balances exactly like WalletController::withdraw().
     */
    public function withdrawableCryptoBalances(string $userUid, ?string $accountUid = null): array
    {
        $funds = $this->getLiveAccountFunds($userUid, $accountUid);

        $result = [];
        foreach (array_keys(self::WITHDRAW_ASSET_NETWORKS) as $asset) {
            $result[$asset] = max(0.0, round((float) ($funds['crypto'][$asset] ?? 0), 8));
        }

        return $result;
    }

    public function withdrawableInrBalance(string $userUid, ?string $accountUid = null): float
    {
        return max(0.0, round((float) $this->getLiveAccountFunds($userUid, $accountUid)['main_inr'], 2));
    }

    /**
     * Per live account: its own main wallet (USD, INR and coins) available for deposit/withdraw.
     */
    public function withdrawableByAccount(string $userUid): array
    {
        $broker = $this->getActiveBrokerType($userUid);
        $this->resolveAccountUid($userUid, null, $broker, true);

        $accounts = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->where('status', 'active')
            ->get();

        $result = [];
        foreach ($accounts as $acc) {
            $accArr = (array) $acc;
            if ((($accArr['account_type'] ?? '') === 'demo') || !empty($accArr['is_demo'])) {
                continue;
            }
            $funds = $this->getLiveAccountFunds($userUid, (string) $accArr['account_uid']);
            $crypto = [];
            foreach (array_keys(self::WITHDRAW_ASSET_NETWORKS) as $asset) {
                $crypto[$asset] = max(0.0, round((float) ($funds['crypto'][$asset] ?? 0), 8));
            }
            $result[$funds['account_uid']] = [
                'account_name' => $funds['account_name'],
                'is_default' => $funds['is_default'],
                'main_usd' => max(0.0, round($funds['main_usd'], 8)),
                'main_inr' => max(0.0, round($funds['main_inr'], 2)),
                'crypto' => $crypto,
            ];
        }

        return $result;
    }

    /**
     * Resolve a live account for deposit/withdraw. Empty / "main_wallet" means the default Main Account.
     */
    public function resolveLiveAccountUid(string $userUid, ?string $accountUid = null): string
    {
        $accountUid = trim((string) $accountUid);
        $broker = $this->getActiveBrokerType($userUid);

        if ($accountUid === '' || in_array($accountUid, ['main', 'main_wallet'], true)) {
            return $this->resolveAccountUid($userUid, null, $broker, true);
        }

        $acc = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->where('account_uid', $accountUid)
            ->where('status', 'active')
            ->first();

        if (! $acc) {
            throw ValidationException::withMessages(['account_uid' => 'Selected account was not found.']);
        }

        $accArr = (array) $acc;
        if ((($accArr['account_type'] ?? '') === 'demo') || !empty($accArr['is_demo'])) {
            throw ValidationException::withMessages(['account_uid' => 'Deposits and withdrawals are only allowed on live accounts.']);
        }

        return (string) $accArr['account_uid'];
    }

    /**
     * Real funds held in one live account's own main wallet.
     */
    public function getLiveAccountFunds(string $userUid, ?string $accountUid = null): array
    {
        $resolvedUid = $this->resolveLiveAccountUid($userUid, $accountUid);
        $wallet = $this->getLiveWallet($userUid, $resolvedUid);

        return [
            'account_uid' => (string) ($wallet['active_account_uid'] ?? $resolvedUid),
            'account_name' => (string) ($wallet['active_account_name'] ?? 'Main Account'),
            'is_default' => !empty($wallet['is_default_account']),
            'broker_type' => $wallet['broker_type'] ?? $this->getActiveBrokerType($userUid),
            'main_usd' => (float) ($wallet['real_main_balance'] ?? 0),
            'main_inr' => (float) ($wallet['real_main_balance_inr'] ?? 0),
            'crypto' => (array) ($wallet['real_crypto_balances'] ?? []),
            'crypto_pending' => (array) ($wallet['real_crypto_pending_balances'] ?? []),
            'locked_usd' => (float) ($wallet['real_withdrawal_locked_balance'] ?? 0),
            'locked_inr' => (float) ($wallet['real_withdrawal_locked_balance_inr'] ?? 0),
        ];
    }

    /**
     * Write absolute values into one live account's main wallet.
     * Keys: crypto, crypto_pending (asset => value), main_usd, main_inr, locked_usd, locked_inr.
     * USDT coin balance and main USD balance are the same pool and are kept in sync.
     */
    public function setLiveAccountFunds(string $userUid, string $accountUid, array $set): void
    {
        $broker = $this->getActiveBrokerType($userUid);
        $acc = (array) DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->where('account_uid', $accountUid)
            ->first();

        if (empty($acc) || (($acc['account_type'] ?? '') === 'demo') || !empty($acc['is_demo'])) {
            throw ValidationException::withMessages(['account_uid' => 'Live account not found.']);
        }

        $isDefault = !empty($acc['is_default']);
        $crypto = (array) ($set['crypto'] ?? []);
        $cryptoPending = (array) ($set['crypto_pending'] ?? []);

        if (array_key_exists('USDT', $crypto) && !array_key_exists('main_usd', $set)) {
            $set['main_usd'] = $crypto['USDT'];
        } elseif (array_key_exists('main_usd', $set) && !array_key_exists('USDT', $crypto)) {
            $crypto['USDT'] = $set['main_usd'];
        }
        if (array_key_exists('USDT', $cryptoPending) && !array_key_exists('locked_usd', $set)) {
            $set['locked_usd'] = $cryptoPending['USDT'];
        }

        $accUpdate = [];
        $walletUpdate = [];

        foreach ($crypto as $asset => $value) {
            $value = max(0.0, round((float) $value, 8));
            if ($isDefault) {
                $walletUpdate["crypto_balances.{$asset}"] = $value;
                $walletUpdate["real_crypto_balances.{$asset}"] = $value;
            } else {
                $accUpdate["real_crypto_balances.{$asset}"] = $value;
            }
        }

        foreach ($cryptoPending as $asset => $value) {
            $value = max(0.0, round((float) $value, 8));
            if ($isDefault) {
                $walletUpdate["crypto_pending_balances.{$asset}"] = $value;
                $walletUpdate["real_crypto_pending_balances.{$asset}"] = $value;
            } else {
                $accUpdate["real_crypto_pending_balances.{$asset}"] = $value;
            }
        }

        if (array_key_exists('main_usd', $set)) {
            $value = max(0.0, round((float) $set['main_usd'], 8));
            $accUpdate['real_main_balance'] = $value;
            if ($isDefault) {
                $walletUpdate['real_main_balance'] = $value;
            }
        }

        if (array_key_exists('main_inr', $set)) {
            $value = max(0.0, round((float) $set['main_inr'], 2));
            $accUpdate['real_main_balance_inr'] = $value;
            if ($isDefault) {
                $walletUpdate['real_main_balance_inr'] = $value;
            }
        }

        foreach (['locked_usd' => ['real_withdrawal_locked_balance', 8], 'locked_inr' => ['real_withdrawal_locked_balance_inr', 2]] as $key => [$field, $precision]) {
            if (array_key_exists($key, $set)) {
                $value = max(0.0, round((float) $set[$key], $precision));
                if ($isDefault) {
                    $walletUpdate[$field] = $value;
                } else {
                    $accUpdate[$field] = $value;
                }
            }
        }

        if (!empty($accUpdate)) {
            $accUpdate['updated_at'] = now();
            DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->where('account_uid', $accountUid)
                ->update($accUpdate);
        }

        if (!empty($walletUpdate)) {
            $walletUpdate['updated_at'] = now();
            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->update($walletUpdate);
        }
    }

    /**
     * Apply a delta to the live account's real INR main balance and the wallet's INR withdrawal lock.
     * Returns the resulting live account state.
     */
    public function adjustRealInrBalance(string $userUid, ?string $accountUid, float $deltaMain, float $deltaLocked = 0.0): array
    {
        $funds = $this->getLiveAccountFunds($userUid, $accountUid);

        $newMainInr = round($funds['main_inr'] + $deltaMain, 2);
        if ($newMainInr < 0) {
            throw ValidationException::withMessages([
                'amount_inr' => 'Insufficient INR balance in ' . $funds['account_name'] . '.',
            ]);
        }
        $newLockedInr = max(0.0, round($funds['locked_inr'] + $deltaLocked, 2));

        $this->setLiveAccountFunds($userUid, $funds['account_uid'], [
            'main_inr' => $newMainInr,
            'locked_inr' => $newLockedInr,
        ]);

        return [
            'broker_type' => $funds['broker_type'],
            'account_uid' => $funds['account_uid'],
            'account_name' => $funds['account_name'],
            'real_main_balance_inr' => $newMainInr,
            'real_withdrawal_locked_balance_inr' => $newLockedInr,
        ];
    }

    /**
     * Apply coin deltas (and optional pending deltas) to one live account's main wallet.
     */
    public function adjustLiveCrypto(string $userUid, ?string $accountUid, string $asset, float $delta, float $pendingDelta = 0.0): array
    {
        $funds = $this->getLiveAccountFunds($userUid, $accountUid);
        $asset = strtoupper($asset);

        $newBalance = round((float) ($funds['crypto'][$asset] ?? 0) + $delta, 8);
        if ($newBalance < -0.00000001) {
            throw ValidationException::withMessages([
                'amount' => "Insufficient {$asset} balance in {$funds['account_name']}.",
            ]);
        }

        $set = ['crypto' => [$asset => max(0.0, $newBalance)]];
        $newPending = null;
        if ($pendingDelta != 0.0) {
            $newPending = max(0.0, round((float) ($funds['crypto_pending'][$asset] ?? 0) + $pendingDelta, 8));
            $set['crypto_pending'] = [$asset => $newPending];
        }

        $this->setLiveAccountFunds($userUid, $funds['account_uid'], $set);

        return [
            'broker_type' => $funds['broker_type'],
            'account_uid' => $funds['account_uid'],
            'account_name' => $funds['account_name'],
            'balance' => max(0.0, $newBalance),
            'pending' => $newPending,
        ];
    }

    public function withdrawalEnabled(): bool
    {
        $settings = $this->platformSettings();
        return !isset($settings->withdrawal_enabled) || (bool) $settings->withdrawal_enabled;
    }

    public function withdrawalLimits(): array
    {
        $settings = $this->platformSettings();
        return [
            'minimum_withdraw_amount' => isset($settings->minimum_withdraw_amount) ? (float) $settings->minimum_withdraw_amount : 0.0,
            'maximum_withdraw_amount' => isset($settings->maximum_withdraw_amount) ? (float) $settings->maximum_withdraw_amount : 0.0,
        ];
    }

    /**
     * $amount is in asset units. Fixed charges are configured in USD and are
     * converted to asset units using $assetUsdPrice (1.0 for USD stablecoins).
     */
    public function calculateWithdrawalCharges(float $amount, float $assetUsdPrice = 1.0): array
    {
        $settings = $this->withdrawalChargeSettings();
        $price = $assetUsdPrice > 0 ? $assetUsdPrice : 1.0;

        $withdrawFee = $settings['withdrawal_fee_type'] === 'percentage'
            ? round($amount * ($settings['withdrawal_fee_value'] / 100), 8)
            : round($settings['withdrawal_fee_value'] / $price, 8);

        $platformCharge = $settings['platform_charge_type'] === 'percentage'
            ? round($amount * ($settings['platform_charge_value'] / 100), 8)
            : round($settings['platform_charge_value'] / $price, 8);

        $totalCharge = round($withdrawFee + $platformCharge, 8);
        $netAmount = max(round($amount - $totalCharge, 8), 0.0);

        return [
            'amount' => round($amount, 8),
            'withdraw_fee' => $withdrawFee,
            'platform_charge' => $platformCharge,
            'total_charge' => $totalCharge,
            'net_amount' => $netAmount,
            'withdrawal_fee_type' => $settings['withdrawal_fee_type'],
            'withdrawal_fee_value' => $settings['withdrawal_fee_value'],
            'platform_charge_type' => $settings['platform_charge_type'],
            'platform_charge_value' => $settings['platform_charge_value'],
        ];
    }

    public function minimumReserve(): float
    {
        $settings = $this->platformSettings();

        if ($settings && isset($settings->minimum_wallet_balance)) {
            return round((float) $settings->minimum_wallet_balance, 8);
        }

        return (float) config('kryvex_wallet.demo_min_available_balance', 2000);
    }

    private function platformSettings(): ?object
    {
        try {
            $settingsArray = \Illuminate\Support\Facades\Cache::remember('platform_settings', 300, function () {
                $settings = DB::connection('mongodb')
                    ->table('admin_settings')
                    ->where('key', 'platform')
                    ->first();
                return $settings ? json_decode(json_encode($settings), true) : null;
            });
            return $settingsArray ? (object) $settingsArray : null;
        } catch (\Throwable $exception) {
            return null;
        }
    }

    //Future, Futures, Option, Options, etc. -> convert to lowercase and return 'futures' or 'options', default to 'futures'
    private function cleanModule(string $module): string
    {
        $module = strtolower($module);
        return in_array($module, ['futures', 'options'], true) ? $module : 'futures';
    }

    private function createLedger(array $data): void
    {
        $currency = $data['wallet_currency'] ?? $data['currency'] ?? 'USDT';
        $broker = $data['broker_type'] ?? $this->getActiveBrokerType($data['user_uid'] ?? '');

        DB::connection('mongodb')
            ->table('wallet_ledgers')
            ->insert([
                'ledger_uid' => (string) Str::uuid(),
                'user_uid' => $data['user_uid'],
                'account_uid' => $data['account_uid'] ?? null,
                'broker_type' => $broker,
                'wallet_currency' => $currency,
                'currency' => $currency,
                'amount_inr' => $data['amount_inr'] ?? ($data['meta']['amount_inr'] ?? null),
                'type' => $data['type'],
                'category' => $data['category'] ?? null,
                'direction' => $data['direction'],
                'amount' => round((float) $data['amount'], 8),
                'balance_after' => round((float) $data['balance_after'], 8),
                'status' => $data['status'] ?? 'completed',
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'meta' => $data['meta'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Safely execute inside a MongoDB transaction if replica set is enabled,
     * or fallback to direct execution for standalone single-node MongoDB instances.
     */
    private function runTransaction(callable $callback)
    {
        try {
            return DB::connection('mongodb')->transaction($callback);
        } catch (\Throwable $e) {
            return $callback();
        }
    }
}
