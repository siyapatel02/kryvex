<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TradingAccountController extends Controller
{
    /**
     * Ensure user is authenticated.
     */
    private function getAuthUser(Request $request): ?array
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return ['error' => 'Unauthenticated.', 'code' => 401];
        }

        $dbUser = DB::connection('mongodb')->table('users')->where('uid', $user['uid'])->first();
        $dbUserArr = $dbUser ? (array) $dbUser : $user;

        return ['user' => $dbUserArr];
    }

    /**
     * Ensure user is authenticated and KYC verified.
     */
    private function verifyKyc(Request $request): ?array
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return ['error' => 'Unauthenticated.', 'code' => 401];
        }

        $dbUser = DB::connection('mongodb')->table('users')->where('uid', $user['uid'])->first();
        $dbUserArr = $dbUser ? (array) $dbUser : $user;

        $kycStatus = strtolower($dbUserArr['kyc_status'] ?? '');
        if (!in_array($kycStatus, ['verified', 'approved'], true)) {
            return ['error' => 'KYC verification required to access or create trading accounts.', 'code' => 403];
        }

        return ['user' => $dbUserArr];
    }

    /**
     * Get or auto-create default Live Main Account for user.
     */
    public function getOrCreateDefaultAccount(string $userUid, ?string $brokerType = null): array
    {
        $broker = app(\App\Services\DemoWalletService::class)->getActiveBrokerType($userUid, $brokerType);

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
                // Ensure default account is strictly LIVE and demo balance is 0.0
                DB::connection('mongodb')
                    ->table('trading_accounts')
                    ->where('user_uid', $userUid)
                    ->where('account_uid', $defaultArr['account_uid'])
                    ->update([
                        'account_type'           => 'live',
                        'is_demo'                => false,
                        'demo_available_balance' => 0.0,
                        'demo_locked_balance'    => 0.0,
                        'demo_available_balance_inr' => 0.0,
                        'demo_locked_balance_inr'    => 0.0,
                    ]);
                $defaultArr['account_type'] = 'live';
                $defaultArr['is_demo'] = false;
                $defaultArr['demo_available_balance'] = 0.0;
                $defaultArr['demo_locked_balance'] = 0.0;
                $defaultArr['demo_available_balance_inr'] = 0.0;
                $defaultArr['demo_locked_balance_inr'] = 0.0;
                return $defaultArr;
            }
        }

        if ($default) {
            $defaultArr = (array) $default;
            if (($defaultArr['account_type'] ?? '') !== 'live' || !empty($defaultArr['demo_available_balance'])) {
                DB::connection('mongodb')
                    ->table('trading_accounts')
                    ->where('user_uid', $userUid)
                    ->where('account_uid', $defaultArr['account_uid'])
                    ->update([
                        'account_type'           => 'live',
                        'is_demo'                => false,
                        'demo_available_balance' => 0.0,
                        'demo_locked_balance'    => 0.0,
                        'demo_available_balance_inr' => 0.0,
                        'demo_locked_balance_inr'    => 0.0,
                    ]);
                $defaultArr['account_type'] = 'live';
                $defaultArr['is_demo'] = false;
                $defaultArr['demo_available_balance'] = 0.0;
                $defaultArr['demo_locked_balance'] = 0.0;
                $defaultArr['demo_available_balance_inr'] = 0.0;
                $defaultArr['demo_locked_balance_inr'] = 0.0;
            }
            return $defaultArr;
        }

        $wallet = (array) DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->first();

        $accountUid = (string) Str::uuid();
        $accountNumber = (string) (1000000 + (abs(crc32($accountUid)) % 9000000));
        $newAccount = [
            'account_uid'                => $accountUid,
            'account_number'             => $accountNumber,
            'user_uid'                   => $userUid,
            'broker_type'                => $broker,
            'platform'                   => ($broker === 'mt5') ? 'MT5' : 'Kryvex',
            'account_name'               => 'Main Account',
            'account_type'               => 'live',
            'is_demo'                    => false,
            'is_default'                 => true,
            'demo_available_balance'     => 0.0,
            'demo_locked_balance'        => 0.0,
            'demo_available_balance_inr' => 0.0,
            'demo_locked_balance_inr'    => 0.0,
            'real_available_balance'     => (float) ($wallet['real_available_balance'] ?? 0.0),
            'real_locked_balance'        => (float) ($wallet['real_locked_balance'] ?? 0.0),
            'real_available_balance_inr' => (float) ($wallet['real_available_balance_inr'] ?? 0.0),
            'real_locked_balance_inr'    => (float) ($wallet['real_locked_balance_inr'] ?? 0.0),
            'status'                     => 'active',
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ];

        DB::connection('mongodb')->table('trading_accounts')->insert($newAccount);

        return $newAccount;
    }

    /**
     * Get or auto-create single dedicated Demo Account for user.
     */
    public function getOrCreateDemoAccount(string $userUid, ?string $brokerType = null): array
    {
        $broker = app(\App\Services\DemoWalletService::class)->getActiveBrokerType($userUid, $brokerType);

        $demo = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->where('account_type', 'demo')
            ->first();

        if ($demo) {
            return (array) $demo;
        }

        $demo = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->where('account_name', 'Demo Account')
            ->first();

        if ($demo) {
            $demoArr = (array) $demo;
            DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('account_uid', $demoArr['account_uid'])
                ->update([
                    'account_type' => 'demo',
                    'is_demo'      => true,
                    'is_default'   => false,
                ]);
            $demoArr['account_type'] = 'demo';
            $demoArr['is_demo'] = true;
            $demoArr['is_default'] = false;
            return $demoArr;
        }

        $wallet = (array) DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->first();

        $rawDemoBal = (float) ($wallet['demo_available_balance'] ?? 0.0);
        $demoBal = $rawDemoBal > 0 ? $rawDemoBal : 60000.0;
        $demoBalInr = (float) ($wallet['demo_available_balance_inr'] ?? ($demoBal * 95.0));

        if ($rawDemoBal <= 0) {
            DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->update([
                    'demo_available_balance'     => $demoBal,
                    'demo_main_balance'          => $demoBal,
                    'demo_available_balance_inr' => $demoBalInr,
                    'demo_main_balance_inr'      => $demoBalInr,
                ]);
        }

        $accountUid = (string) Str::uuid();
        $accountNumber = (string) (1000000 + (abs(crc32($accountUid . '_demo_kvx')) % 9000000));
        $newAccount = [
            'account_uid'                => $accountUid,
            'account_number'             => $accountNumber,
            'user_uid'                   => $userUid,
            'broker_type'                => $broker,
            'platform'                   => ($broker === 'mt5') ? 'MT5' : 'Kryvex',
            'account_name'               => 'Demo Account',
            'account_type'               => 'demo',
            'is_demo'                    => true,
            'is_default'                 => false,
            'demo_available_balance'     => $demoBal,
            'demo_locked_balance'        => (float) ($wallet['demo_locked_balance'] ?? 0.0),
            'demo_available_balance_inr' => $demoBalInr,
            'demo_locked_balance_inr'    => (float) ($wallet['demo_locked_balance_inr'] ?? 0.0),
            'real_available_balance'     => 0.0,
            'real_locked_balance'        => 0.0,
            'real_available_balance_inr' => 0.0,
            'real_locked_balance_inr'    => 0.0,
            'status'                     => 'active',
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ];

        DB::connection('mongodb')->table('trading_accounts')->insert($newAccount);

        return $newAccount;
    }

    /**
     * List user's trading accounts.
     */
    public function index(Request $request)
    {
        $auth = $this->getAuthUser($request);
        if (isset($auth['error'])) {
            return response()->json(['success' => false, 'message' => $auth['error']], $auth['code']);
        }

        $userUid = $auth['user']['uid'];
        $rawReqBroker = $request->header('X-Broker-Type') ?: ($request->query('broker_type') ?: null);
        $reqBroker = ($rawReqBroker === 'all') ? null : $rawReqBroker;
        $broker = app(\App\Services\DemoWalletService::class)->getActiveBrokerType($userUid, $reqBroker);

        $this->getOrCreateDefaultAccount($userUid, $broker);
        $this->getOrCreateDemoAccount($userUid, $broker);

        $allWallets = DB::connection('mongodb')
            ->table('wallets')
            ->where('user_uid', $userUid)
            ->get();
        $walletsByBroker = [];
        foreach ($allWallets as $w) {
            $wArr = (array) $w;
            $bType = strtolower($wArr['broker_type'] ?? 'kryvex');
            $walletsByBroker[$bType] = $wArr;
        }
        $primaryWallet = $walletsByBroker['kryvex'] ?? ($walletsByBroker[$broker] ?? ((array) ($allWallets->first() ?? [])));
        $wallet = $walletsByBroker[$broker] ?? $primaryWallet;

        $query = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('status', 'active');

        if (!empty($reqBroker) && in_array(strtolower($reqBroker), ['kryvex', 'mt5'], true)) {
            $query->where('broker_type', strtolower($reqBroker));
        }

        $accounts = $query->get();

        $adminSettings = (array) DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
        $rate = (float) ($adminSettings['usdt_inr_rate'] ?? 95.0);
        if ($rate <= 0) $rate = 95.0;

        // Fetch real open futures and option positions for live P&L and Margin
        $openFutures = DB::connection('mongodb')->table('futures_positions')
            ->where('user_uid', $userUid)
            ->where('status', 'open')
            ->get();

        $openOptions = DB::connection('mongodb')->table('option_positions')
            ->where('user_uid', $userUid)
            ->where('status', 'open')
            ->get();

        $list = [];
        $foundDemo = false;

        foreach ($accounts as $acc) {
            $arr = (array) $acc;

            $accNumber = $arr['account_number'] ?? null;
            if (empty($accNumber)) {
                $accNumber = (string) (1000000 + (abs(crc32($arr['account_uid'])) % 9000000));
                DB::connection('mongodb')->table('trading_accounts')
                    ->where('account_uid', $arr['account_uid'])
                    ->update(['account_number' => $accNumber]);
            }

            $isDemo = (($arr['account_type'] ?? '') === 'demo') 
                || !empty($arr['is_demo']) 
                || str_starts_with(strtolower(trim($arr['account_name'] ?? '')), 'demo');
            $isDefault = (bool) ($arr['is_default'] ?? false);
            $posMode = $isDemo ? 'demo' : 'live';

            // Real positions P&L and Margin for this account
            $unrealizedPnlUsd = 0.0;
            $marginUsedUsd = 0.0;

            foreach ($openFutures as $pos) {
                $p = (array) $pos;
                $pAcc = (string) ($p['account_uid'] ?? '');
                $pMode = strtolower((string) ($p['mode'] ?? 'demo'));
                if ($pMode === '') $pMode = 'demo';

                $modeMatches = ($posMode === 'live') ? ($pMode === 'live') : ($pMode === 'demo');
                if (!$modeMatches) continue;

                $accMatches = ($pAcc === $arr['account_uid']) || ($isDefault && ($pAcc === '' || $pAcc === $userUid));
                if ($accMatches) {
                    $unrealizedPnlUsd += (float) ($p['unrealized_pnl'] ?? $p['pnl'] ?? 0.0);
                    $marginUsedUsd += (float) ($p['margin'] ?? 0.0);
                }
            }

            foreach ($openOptions as $pos) {
                $p = (array) $pos;
                $pAcc = (string) ($p['account_uid'] ?? '');
                $pMode = strtolower((string) ($p['mode'] ?? 'demo'));
                if ($pMode === '') $pMode = 'demo';

                $modeMatches = ($posMode === 'live') ? ($pMode === 'live') : ($pMode === 'demo');
                if (!$modeMatches) continue;

                $accMatches = ($pAcc === $arr['account_uid']) || ($isDefault && ($pAcc === '' || $pAcc === $userUid));
                if ($accMatches) {
                    $unrealizedPnlUsd += (float) ($p['unrealized_pnl'] ?? $p['pnl'] ?? 0.0);
                    $marginUsedUsd += (float) ($p['margin'] ?? 0.0);
                }
            }

            $accBroker = strtolower($arr['broker_type'] ?? $broker);
            $accWallet = $walletsByBroker[$accBroker] ?? $primaryWallet;

            if ($isDemo) {
                // Ensure only ONE single demo account is returned
                if ($foundDemo) {
                    continue;
                }
                $foundDemo = true;
                $trDemoBal = isset($arr['demo_available_balance']) ? (float)$arr['demo_available_balance'] : (isset($arr['balance']) ? (float)$arr['balance'] : null);
                $wDemoBal = isset($accWallet['demo_available_balance']) ? (float)$accWallet['demo_available_balance'] : (isset($primaryWallet['demo_available_balance']) ? (float)$primaryWallet['demo_available_balance'] : null);

                $demoBal = ($trDemoBal !== null && $trDemoBal > 0.0) ? $trDemoBal : (($wDemoBal !== null && $wDemoBal > 0.0) ? $wDemoBal : 60000.0);

                $demoLocked = max((float) ($accWallet['demo_locked_balance'] ?? ($arr['demo_locked_balance'] ?? 0.0)), $marginUsedUsd);
                $demoBalInr = (float) ($accWallet['demo_available_balance_inr'] ?? ($arr['demo_available_balance_inr'] ?? ($demoBal * $rate)));
                $demoLockedInr = round($demoLocked * $rate, 2);

                $unrealizedPnlInr = round($unrealizedPnlUsd * $rate, 2);
                $equityUsd = max(0.0, $demoBal + $unrealizedPnlUsd);
                $equityInr = max(0.0, $demoBalInr + $unrealizedPnlInr);

                // For demo mode, there is only a single balance: main balance reflects dynamic demo equity
                $demoMainBal = $equityUsd;
                $demoMainBalInr = $equityInr;

                $planKey = strtolower((string) ($arr['plan_key'] ?? 'standard'));
                $isCent = !empty($arr['is_cent']) || ($planKey === 'cent') || (isset($arr['account_currency']) && $arr['account_currency'] === 'USC') || str_contains(strtolower($arr['account_name'] ?? ''), 'cent');
                $accountCurrency = $arr['account_currency'] ?? ($isCent ? 'USC' : 'USD');

                $list[] = [
                    'account_uid'                => $arr['account_uid'],
                    'account_number'             => $accNumber,
                    'account_name'               => $arr['account_name'] ?? 'Demo Account',
                    'account_type'               => 'demo',
                    'plan_key'                   => $planKey,
                    'is_cent'                    => (bool) $isCent,
                    'account_currency'           => $accountCurrency,
                    'is_demo'                    => true,
                    'is_default'                 => false,
                    'broker_type'                => $arr['broker_type'] ?? $broker,
                    'platform'                   => !empty($arr['platform']) ? $arr['platform'] : ((($arr['broker_type'] ?? $broker) === 'mt5') ? 'MT5' : 'Kryvex'),
                    'leverage'                   => $arr['leverage'] ?? null,
                    'server'                     => $arr['server'] ?? null,
                    'balance'                    => (float) $demoBal,
                    'balance_inr'                => (float) $demoBalInr,
                    'equity'                     => (float) $equityUsd,
                    'equity_inr'                 => (float) $equityInr,
                    'unrealized_pnl'             => (float) round($unrealizedPnlUsd, 4),
                    'unrealized_pnl_inr'         => (float) $unrealizedPnlInr,
                    'margin_used'                => (float) round($demoLocked, 4),
                    'margin_used_inr'            => (float) $demoLockedInr,
                    'demo_available_balance'     => (float) $demoBal,
                    'demo_locked_balance'        => (float) $demoLocked,
                    'demo_available_balance_inr' => (float) $demoBalInr,
                    'demo_locked_balance_inr'    => (float) $demoLockedInr,
                    'demo_main_balance'          => (float) $demoMainBal,
                    'demo_main_balance_inr'      => (float) $demoMainBalInr,
                    'main_balance'               => (float) $demoMainBal,
                    'main_balance_inr'           => (float) $demoMainBalInr,
                    'real_available_balance'     => 0.0,
                    'real_locked_balance'        => 0.0,
                    'real_available_balance_inr' => 0.0,
                    'real_locked_balance_inr'    => 0.0,
                    'real_main_balance'          => 0.0,
                    'real_main_balance_inr'      => 0.0,
                    'created_at'                 => $arr['created_at'] ?? null,
                ];
            } else {
                // Live Account (Main Account or Sub Account) - Strictly real balances only
                // Transfers write trading balances to the trading_accounts document; the wallet copy is only a legacy fallback for the default account.
                $walletFallback = fn (string $key) => $isDefault ? ($accWallet[$key] ?? ($primaryWallet[$key] ?? 0.0)) : 0.0;

                $realBal = (float) ($arr['real_available_balance'] ?? $walletFallback('real_available_balance'));
                $realLocked = max((float) ($arr['real_locked_balance'] ?? $walletFallback('real_locked_balance')), $marginUsedUsd);
                $realBalInr = (float) ($arr['real_available_balance_inr'] ?? $walletFallback('real_available_balance_inr'));
                $realLockedInr = round($realLocked * $rate, 2);

                if ($realBalInr <= 0 && $realBal > 0) {
                    $realBalInr = round($realBal * $rate, 2);
                }
                if ($realBal <= 0 && $realBalInr > 0) {
                    $realBal = round($realBalInr / $rate, 8);
                }

                // Resolve real main balance for the active account:
                // Check account's own real_main_balance first, then account broker's wallet, then primary wallet (including USDT crypto balance)
                $walletMainUsd = (float) ($accWallet['real_main_balance'] ?? 0.0);
                if ($walletMainUsd <= 0.0) {
                    $walletMainUsd = (float) ($accWallet['crypto_balances']['USDT'] ?? ($accWallet['real_crypto_balances']['USDT'] ?? 0.0));
                }
                if ($walletMainUsd <= 0.0) {
                    $walletMainUsd = (float) ($primaryWallet['real_main_balance'] ?? ($primaryWallet['crypto_balances']['USDT'] ?? ($primaryWallet['real_crypto_balances']['USDT'] ?? 0.0)));
                }

                $accMainUsd = (float) ($arr['real_main_balance'] ?? 0.0);
                $realMainBal = ($accMainUsd > 0.0) ? $accMainUsd : $walletMainUsd;

                $walletMainInr = (float) ($accWallet['real_main_balance_inr'] ?? ($primaryWallet['real_main_balance_inr'] ?? 0.0));
                if ($walletMainInr <= 0.0 && $realMainBal > 0.0) {
                    $walletMainInr = round($realMainBal * $rate, 2);
                }
                $accMainInr = (float) ($arr['real_main_balance_inr'] ?? 0.0);
                $realMainBalInr = ($accMainInr > 0.0) ? $accMainInr : $walletMainInr;

                $unrealizedPnlInr = round($unrealizedPnlUsd * $rate, 2);
                $equityUsd = max(0.0, $realBal + $unrealizedPnlUsd);
                $equityInr = max(0.0, $realBalInr + $unrealizedPnlInr);

                $planKey = strtolower((string) ($arr['plan_key'] ?? 'standard'));
                $isCent = !empty($arr['is_cent']) || ($planKey === 'cent') || (isset($arr['account_currency']) && $arr['account_currency'] === 'USC') || str_contains(strtolower($arr['account_name'] ?? ''), 'cent');
                $accountCurrency = $arr['account_currency'] ?? ($isCent ? 'USC' : 'USD');

                $list[] = [
                    'account_uid'                => $arr['account_uid'],
                    'account_number'             => $accNumber,
                    'account_name'               => $arr['account_name'] ?? 'Trading Account',
                    'account_type'               => 'live',
                    'plan_key'                   => $planKey,
                    'is_cent'                    => (bool) $isCent,
                    'account_currency'           => $accountCurrency,
                    'is_demo'                    => false,
                    'is_default'                 => $isDefault,
                    'broker_type'                => $arr['broker_type'] ?? $broker,
                    'platform'                   => !empty($arr['platform']) ? $arr['platform'] : ((($arr['broker_type'] ?? $broker) === 'mt5') ? 'MT5' : 'Kryvex'),
                    'leverage'                   => $arr['leverage'] ?? null,
                    'server'                     => $arr['server'] ?? null,
                    'balance'                    => (float) $realBal,
                    'balance_inr'                => (float) $realBalInr,
                    'equity'                     => (float) $equityUsd,
                    'equity_inr'                 => (float) $equityInr,
                    'unrealized_pnl'             => (float) round($unrealizedPnlUsd, 4),
                    'unrealized_pnl_inr'         => (float) $unrealizedPnlInr,
                    'margin_used'                => (float) round($realLocked, 4),
                    'margin_used_inr'            => (float) $realLockedInr,
                    'demo_available_balance'     => 0.0,
                    'demo_locked_balance'        => 0.0,
                    'demo_available_balance_inr' => 0.0,
                    'demo_locked_balance_inr'    => 0.0,
                    'demo_main_balance'          => 0.0,
                    'demo_main_balance_inr'      => 0.0,
                    'real_available_balance'     => (float) $realBal,
                    'real_locked_balance'        => (float) $realLocked,
                    'real_available_balance_inr' => (float) $realBalInr,
                    'real_locked_balance_inr'    => (float) $realLockedInr,
                    'real_main_balance'          => (float) $realMainBal,
                    'real_main_balance_inr'      => (float) $realMainBalInr,
                    'main_balance'               => (float) $realMainBal,
                    'main_balance_inr'           => (float) $realMainBalInr,
                    'created_at'                 => $arr['created_at'] ?? null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data'    => $list,
        ]);
    }

    /**
     * Create a new sub-trading account (Kryvex Exchange or MetaTrader 5).
     */
    public function store(Request $request)
    {
        $auth = $this->getAuthUser($request);
        if (isset($auth['error'])) {
            return response()->json(['success' => false, 'message' => $auth['error']], $auth['code']);
        }

        $userUid = $auth['user']['uid'];
        $brokerInput = strtolower(trim((string) $request->input('broker_type', 'kryvex')));
        $broker = in_array($brokerInput, ['kryvex', 'mt5'], true) ? $brokerInput : 'kryvex';

        $this->getOrCreateDefaultAccount($userUid, $broker);

        $validated = $request->validate([
            'account_name' => ['nullable', 'string', 'max:40', 'min:2'],
            'plan_key'     => ['nullable', 'string', 'max:30'],
            'broker_type'  => ['nullable', 'string', 'in:kryvex,mt5'],
            'account_type' => ['nullable', 'string', 'in:live,demo'],
        ]);

        $rawName = trim((string) ($validated['account_name'] ?? ''));
        $planKey = strtolower(trim((string) ($validated['plan_key'] ?? 'basic')));
        $accountType = strtolower(trim((string) ($validated['account_type'] ?? 'live')));
        $isDemo = ($accountType === 'demo');

        // Kryvex Broker allows maximum 1 Demo Account (PrimeXBT standard)
        if ($isDemo && $broker === 'kryvex') {
            $hasExistingDemo = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', 'kryvex')
                ->where(function ($q) {
                    $q->where('account_type', 'demo')
                      ->orWhere('is_demo', true);
                })
                ->exists();

            if ($hasExistingDemo) {
                return response()->json([
                    'success' => false,
                    'message' => 'All available accounts have been created.',
                ], 422);
            }

            // Kryvex Demo accounts have no tiered plans
            $planKey = 'demo';
        }

        // Live accounts strictly require KYC verification. Demo simulator accounts do not require KYC.
        if (!$isDemo) {
            $kycStatus = strtolower((string) ($auth['user']['kyc_status'] ?? 'unverified'));
            if (!in_array($kycStatus, ['verified', 'approved'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'KYC verification is required to create live trading accounts. Please complete KYC verification first.',
                ], 403);
            }
        }

        if (empty($rawName)) {
            $existingCount = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('user_uid', $userUid)
                ->where('broker_type', $broker)
                ->count();
            if ($isDemo) {
                $prefix = 'Demo Account ';
            } else {
                $prefix = $broker === 'mt5' ? 'MT5 Account ' : 'Trading Account ';
            }
            $name = $prefix . ($existingCount + 1);
        } else {
            $name = $rawName;
        }

        // Check duplicate name
        $existing = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('broker_type', $broker)
            ->where('account_name', 'regexp', '/^' . preg_quote($name, '/') . '$/i')
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => "An account named '{$name}' already exists."], 422);
        }

        // Limit max 20 sub accounts
        $count = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->count();

        if ($count >= 20) {
            return response()->json(['success' => false, 'message' => 'Maximum limit of 20 trading accounts reached.'], 422);
        }

        $accountUid = (string) Str::uuid();
        $accountNumber = (string) (1000000 + (abs(crc32($accountUid)) % 9000000));

        $planSpecs = [
            'cent'     => ['min_balance' => 1.0, 'leverage' => '1:1000', 'spread' => '0.08%', 'bonus' => '15%'],
            'basic'    => ['min_balance' => 10.0, 'leverage' => '1:100', 'spread' => '0.05%', 'bonus' => '20%'],
            'standard' => ['min_balance' => 50000.0, 'leverage' => '1:300', 'spread' => '0.03%', 'bonus' => '50%'],
            'advanced' => ['min_balance' => 250000.0, 'leverage' => '1:500', 'spread' => '0.01%', 'bonus' => '100%'],
        ];
        $spec = $planSpecs[$planKey] ?? $planSpecs['cent'];

        $demoInitialUsd = $isDemo ? 50000.0 : 0.0;
        $demoInitialInr = $isDemo ? 4250000.0 : 0.0;

        $newAccount = [
            'account_uid'                => $accountUid,
            'account_number'             => $accountNumber,
            'user_uid'                   => $userUid,
            'broker_type'                => $broker,
            'platform'                   => ($broker === 'mt5') ? 'MT5' : 'Kryvex',
            'account_name'               => $name,
            'account_type'               => $isDemo ? 'demo' : 'live',
            'plan_key'                   => $planKey,
            'is_cent'                    => ($planKey === 'cent'),
            'account_currency'           => ($planKey === 'cent' ? 'USC' : 'USD'),
            'min_balance'                => $spec['min_balance'],
            'leverage'                   => $spec['leverage'],
            'spread'                     => $spec['spread'],
            'bonus_percentage'           => $spec['bonus'],
            'is_demo'                    => $isDemo,
            'is_default'                 => false,
            'demo_available_balance'     => $demoInitialUsd,
            'demo_locked_balance'        => 0.0,
            'demo_available_balance_inr' => $demoInitialInr,
            'demo_locked_balance_inr'    => 0.0,
            'demo_main_balance'          => $demoInitialUsd,
            'demo_main_balance_inr'      => $demoInitialInr,
            'real_available_balance'     => 0.0,
            'real_locked_balance'        => 0.0,
            'real_available_balance_inr' => 0.0,
            'real_locked_balance_inr'    => 0.0,
            'real_main_balance'          => 0.0,
            'real_main_balance_inr'      => 0.0,
            'status'                     => 'active',
            'created_at'                 => now(),
            'updated_at'                 => now(),
        ];

        DB::connection('mongodb')->table('trading_accounts')->insert($newAccount);

        if ($broker === 'mt5') {
            try {
                app(\App\Services\Mt5BrokerService::class)->requestPlan($userUid, $planKey);
            } catch (\Throwable $e) {}
        }

        $brokerLabel = $broker === 'mt5' ? 'MetaTrader 5' : 'Kryvex Exchange';

        return response()->json([
            'success' => true,
            'message' => "Trading account '{$name}' ({$brokerLabel}) created successfully.",
            'data'    => [
                'account_uid'                => $accountUid,
                'account_number'             => $accountNumber,
                'account_name'               => $name,
                'account_type'               => $isDemo ? 'demo' : 'live',
                'plan_key'                   => $planKey,
                'broker_type'                => $broker,
                'platform'                   => ($broker === 'mt5') ? 'MT5' : 'Kryvex',
                'is_demo'                    => $isDemo,
                'is_default'                 => false,
                'demo_available_balance'     => $isDemo ? 50000.0 : 0.0,
                'demo_locked_balance'        => 0.0,
                'demo_available_balance_inr' => $isDemo ? 4250000.0 : 0.0,
                'demo_locked_balance_inr'    => 0.0,
                'demo_main_balance'          => $isDemo ? 50000.0 : 0.0,
                'demo_main_balance_inr'      => $isDemo ? 4250000.0 : 0.0,
                'real_available_balance'     => 0.0,
                'real_locked_balance'        => 0.0,
                'real_available_balance_inr' => 0.0,
                'real_locked_balance_inr'    => 0.0,
                'real_main_balance'          => 0.0,
                'real_main_balance_inr'      => 0.0,
                'main_balance'               => $isDemo ? 50000.0 : 0.0,
                'main_balance_inr'           => $isDemo ? 4250000.0 : 0.0,
            ],
        ]);
    }

    /**
     * Delete a sub-trading account (Only if balance is 0, no open positions, no pending orders).
     */
    public function destroy(Request $request, string $accountUid)
    {
        $auth = $this->verifyKyc($request);
        if (isset($auth['error'])) {
            return response()->json(['success' => false, 'message' => $auth['error']], $auth['code']);
        }

        $userUid = $auth['user']['uid'];

        $account = DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('account_uid', $accountUid)
            ->first();

        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Trading account not found.'], 404);
        }

        $accArr = (array) $account;

        if (!empty($accArr['is_default'])) {
            return response()->json(['success' => false, 'message' => 'Default Main Account cannot be deleted.'], 422);
        }

        if (($accArr['account_type'] ?? '') === 'demo' || !empty($accArr['is_demo'])) {
            return response()->json(['success' => false, 'message' => 'Demo Account cannot be deleted.'], 422);
        }

        $balance = (float) ($accArr['real_available_balance'] ?? 0.0) + (float) ($accArr['real_locked_balance'] ?? 0.0)
                 + (float) ($accArr['demo_available_balance'] ?? 0.0) + (float) ($accArr['demo_locked_balance'] ?? 0.0);
        if ($balance > 0.0001) {
            return response()->json(['success' => false, 'message' => 'Cannot delete account with active balance. Please transfer funds to Main Wallet first.'], 422);
        }

        // Check open futures/options positions
        $openPositions = DB::connection('mongodb')
            ->table('positions')
            ->where('user_uid', $userUid)
            ->where('account_uid', $accountUid)
            ->where('status', 'open')
            ->count();

        $openOptionPositions = DB::connection('mongodb')
            ->table('option_positions')
            ->where('user_uid', $userUid)
            ->where('account_uid', $accountUid)
            ->where('status', 'open')
            ->count();

        if ($openPositions > 0 || $openOptionPositions > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete account with open positions.'], 422);
        }

        // Check open futures/options orders
        $openOrders = DB::connection('mongodb')
            ->table('orders')
            ->where('user_uid', $userUid)
            ->where('account_uid', $accountUid)
            ->whereIn('status', ['open', 'pending'])
            ->count();

        $openOptionOrders = DB::connection('mongodb')
            ->table('option_orders')
            ->where('user_uid', $userUid)
            ->where('account_uid', $accountUid)
            ->whereIn('status', ['open', 'pending'])
            ->count();

        if ($openOrders > 0 || $openOptionOrders > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete account with active orders.'], 422);
        }

        DB::connection('mongodb')
            ->table('trading_accounts')
            ->where('user_uid', $userUid)
            ->where('account_uid', $accountUid)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "Trading account '{$accArr['account_name']}' deleted successfully.",
        ]);
    }
}
