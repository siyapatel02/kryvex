<?php

namespace App\Services;

use App\Mail\Mt5AccountReadyMail;
use App\Models\Mt5Account;
use App\Models\Mt5Plan;
use App\Models\Mt5Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

class Mt5BrokerService
{
    public function ensureDefaultPlans(): void
    {
        $defaultPlans = [
            [
                'plan_key' => 'cent',
                'name' => 'Cent',
                'minimum_balance' => 1.0,
                'max_leverage' => '1:1000',
                'description' => 'Cent account for micro-lot trading (1 USD = 100 USC), ideal for beginners & EA testing.',
                'status' => 'active',
            ],
            [
                'plan_key' => 'basic',
                'name' => 'Basic',
                'minimum_balance' => 10.0,
                'max_leverage' => '1:100',
                'description' => 'Entry-level MT5 trading plan for balances starting at $1,000.',
                'status' => 'active',
            ],
            [
                'plan_key' => 'standard',
                'name' => 'Standard',
                'minimum_balance' => 50000.0,
                'max_leverage' => '1:300',
                'description' => 'Professional MT5 trading plan for balances starting at $50,000.',
                'status' => 'active',
            ],
            [
                'plan_key' => 'advanced',
                'name' => 'Advanced',
                'minimum_balance' => 250000.0,
                'max_leverage' => '1:500',
                'description' => 'VIP MT5 trading plan with maximum leverage for balances starting at $250,000.',
                'status' => 'active',
            ],
        ];

        foreach ($defaultPlans as $plan) {
            Mt5Plan::updateOrCreate(
                ['plan_key' => $plan['plan_key']],
                $plan
            );
        }
    }

    public function getPlans(): array
    {
        $this->ensureDefaultPlans();

        $plans = Mt5Plan::where('status', 'active')
            ->get()
            ->sortBy('minimum_balance')
            ->values()
            ->toArray();

        return array_map(function ($plan) {
            $planKey = strtolower($plan['plan_key'] ?? '');
            $spreadSettings = \App\Helpers\SpreadHelper::getSettings(null, $planKey);
            $plan['spread_enabled'] = $spreadSettings['enabled'];
            $plan['spread_type'] = $spreadSettings['type'];
            $plan['spread_value'] = $spreadSettings['value'];
            $plan['spread_formatted'] = $spreadSettings['type'] === 'percentage'
                ? ($spreadSettings['value'] . '%')
                : ('$' . $spreadSettings['value']);
            return $plan;
        }, $plans);
    }

    public function getUserBrokerStatus(string $userUid): array
    {
        $this->ensureDefaultPlans();

        $userRecord = DB::connection('mongodb')
            ->table('users')
            ->where('_id', $userUid)
            ->first();

        if (!$userRecord) {
            $userRecord = DB::connection('mongodb')
                ->table('users')
                ->where('uid', $userUid)
                ->first();
        }

        $userArr = (array) $userRecord;
        $brokerType = !empty($userArr['broker_type']) ? strtolower($userArr['broker_type']) : null;
        $brokerPopupSeen = (int) ($userArr['broker_popup_seen'] ?? 0);
        $appPopupSeen = (int) ($userArr['app_popup_seen'] ?? 0);
        $kryvexPlan = $userArr['kryvex_plan'] ?? ($userArr['mt5_plan'] ?? 'standard');
        $mt5Plan    = $userArr['mt5_plan'] ?? ($userArr['kryvex_plan'] ?? 'standard');

        $activeAccount = Mt5Account::where('user_uid', $userUid)
            ->where('status', 'active')
            ->first();

        $pendingRequest = Mt5Request::where('user_uid', $userUid)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->first();

        $mt5Status = $userArr['mt5_status'] ?? ($activeAccount ? 'approved' : ($pendingRequest ? 'pending' : null));

        $wallet = DB::connection('mongodb')
            ->table('demo_wallets')
            ->where('user_uid', $userUid)
            ->first();

        if (!$wallet) {
            $wallet = DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->first();
        }

        $walletArr = (array) $wallet;
        $mainBalance = (float) ($walletArr['demo_main_balance'] ?? ($walletArr['real_available_balance'] ?? 0));
        
        // Auto-check for low balance plan downgrade (INR/USDT aware)
        $downgradeResult = $this->checkAndSyncPlanDowngrade($userUid, $mainBalance);
        if ($downgradeResult['downgraded']) {
            $kryvexPlan = strtolower($downgradeResult['new_plan']);
            $mt5Plan    = strtolower($downgradeResult['new_plan']);
        }

        $plans = $this->getPlans();

        $eligiblePlans = array_filter($plans, fn($p) => $mainBalance >= (float) $p['minimum_balance']);
        $highestEligiblePlan = !empty($eligiblePlans) ? end($eligiblePlans) : null;

        $planWarning = $userArr['plan_downgraded_warning'] ?? ($downgradeResult['warning'] ?? null);

        return [
            'user_uid' => $userUid,
            'broker_type' => $brokerType,
            'broker_popup_seen' => $brokerPopupSeen,
            'app_popup_seen' => $appPopupSeen,
            'kryvex_plan' => $kryvexPlan,
            'mt5_plan' => $mt5Plan,
            'mt5_status' => $mt5Status,
            'main_balance' => $mainBalance,
            'active_account' => $activeAccount ? $activeAccount->toArray() : null,
            'pending_request' => $pendingRequest ? $pendingRequest->toArray() : null,
            'plans' => $plans,
            'highest_eligible_plan' => $highestEligiblePlan,
            'plan_downgraded_warning' => $planWarning,
        ];
    }

    public function checkAndSyncPlanDowngrade(string $userUid, ?float $currentBalance = null): array
    {
        $userRecord = DB::connection('mongodb')
            ->table('users')
            ->where('_id', $userUid)
            ->first();

        if (!$userRecord) {
            $userRecord = DB::connection('mongodb')
                ->table('users')
                ->where('uid', $userUid)
                ->first();
        }

        if (!$userRecord) {
            return ['downgraded' => false];
        }

        $userArr = (array) $userRecord;
        $baseCurrency = strtoupper((string) ($userArr['base_currency'] ?? 'USDT'));

        $settingDoc = DB::connection('mongodb')
            ->table('admin_settings')
            ->where('key', 'platform')
            ->first();
        $settingArr = $settingDoc ? (array) $settingDoc : [];
        $usdtInrRate = (float) ($settingArr['usdt_inr_rate'] ?? 85.0);
        if ($usdtInrRate <= 0) {
            $usdtInrRate = 85.0;
        }

        if ($currentBalance === null) {
            $wallet = DB::connection('mongodb')
                ->table('demo_wallets')
                ->where('user_uid', $userUid)
                ->first();

            if (!$wallet) {
                $wallet = DB::connection('mongodb')
                    ->table('wallets')
                    ->where('user_uid', $userUid)
                    ->first();
            }
            $walletArr = (array) $wallet;
            $currentBalance = (float) ($walletArr['demo_main_balance'] ?? ($walletArr['real_available_balance'] ?? 0));
        }

        $currentPlanKey = strtolower(trim($userArr['kryvex_plan'] ?? ($userArr['mt5_plan'] ?? 'standard')));
        $plans = $this->getPlans();

        $planMap = [];
        foreach ($plans as $p) {
            $pk = strtolower($p['plan_key']);
            $minUsdt = (float) $p['minimum_balance'];
            $minInr = round($minUsdt * $usdtInrRate, 2);
            $minRequired = ($baseCurrency === 'INR') ? $minInr : $minUsdt;
            $planMap[$pk] = [
                'name' => $p['name'],
                'min_usdt' => $minUsdt,
                'min_inr' => $minInr,
                'min_required' => $minRequired,
            ];
        }

        $requiredMin = $planMap[$currentPlanKey]['min_required'] ?? 0;

        // Auto Downgrade if balance falls below required minimum
        if ($currentBalance < $requiredMin) {
            $newPlanKey = 'cent';
            if ($currentBalance >= ($planMap['advanced']['min_required'] ?? 250000)) {
                $newPlanKey = 'advanced';
            } elseif ($currentBalance >= ($planMap['standard']['min_required'] ?? 50000)) {
                $newPlanKey = 'standard';
            } elseif ($currentBalance >= ($planMap['basic']['min_required'] ?? 10)) {
                $newPlanKey = 'basic';
            }

            if ($newPlanKey !== $currentPlanKey) {
                $oldPlanName = ucfirst($currentPlanKey);
                $newPlanName = ucfirst($newPlanKey);
                $symbolPrefix = ($baseCurrency === 'INR') ? '₹' : '$';
                $formattedBal = $symbolPrefix . number_format($currentBalance, 2);
                $formattedReq = $symbolPrefix . number_format($requiredMin, 2);

                $warningMsg = "Low Balance Alert: Your main balance ({$formattedBal}) has fallen below the required minimum ({$formattedReq}) for the {$oldPlanName} Plan. Your account has been shifted to the {$newPlanName} Plan.";

                $warningData = [
                    'old_plan' => $oldPlanName,
                    'new_plan' => $newPlanName,
                    'message' => $warningMsg,
                    'balance' => $currentBalance,
                    'required' => $requiredMin,
                    'currency' => $baseCurrency,
                    'downgraded_at' => (string) now(),
                ];

                $updateData = [
                    'kryvex_plan' => $newPlanKey,
                    'mt5_plan' => $newPlanKey,
                    'plan_downgraded_warning' => $warningData,
                    'updated_at' => now(),
                ];

                $this->updateUserRecord($userUid, $updateData);

                return [
                    'downgraded' => true,
                    'old_plan' => $oldPlanName,
                    'new_plan' => $newPlanName,
                    'message' => $warningMsg,
                    'warning' => $warningData,
                ];
            }
        }

        return ['downgraded' => false];
    }

    public function dismissDowngradeWarning(string $userUid): bool
    {
        return $this->updateUserRecord($userUid, [
            'plan_downgraded_warning' => null,
            'updated_at' => now(),
        ]);
    }

    private function updateUserRecord(string $userUid, array $updateData): bool
    {
        $updated = DB::connection('mongodb')
            ->table('users')
            ->where('_id', $userUid)
            ->update($updateData);

        if (!$updated && class_exists('\MongoDB\BSON\ObjectId') && strlen($userUid) === 24 && ctype_xdigit($userUid)) {
            try {
                $bsonClass = '\MongoDB\BSON\ObjectId';
                DB::connection('mongodb')
                    ->table('users')
                    ->where('_id', new $bsonClass($userUid))
                    ->update($updateData);
            } catch (\Throwable $e) {}
        }
        
        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update($updateData);
        
        return true;
    }

    public function dismissPopup(string $userUid): array
    {
        $this->updateUserRecord($userUid, ['broker_popup_seen' => 1, 'updated_at' => now()]);
        return $this->getUserBrokerStatus($userUid);
    }

    public function dismissAppPopup(string $userUid): array
    {
        $this->updateUserRecord($userUid, ['app_popup_seen' => 1, 'updated_at' => now()]);
        return $this->getUserBrokerStatus($userUid);
    }

    public function selectBroker(string $userUid, string $brokerType, ?string $planKey = null): array
    {
        $brokerType = strtolower(trim($brokerType));
        if (!in_array($brokerType, ['kryvex', 'mt5'], true)) {
            throw new RuntimeException('Invalid broker selected. Choose Kryvex or MetaTrader 5.');
        }

        $updateData = [
            'broker_type' => $brokerType,
            'broker_popup_seen' => 1,
            'updated_at' => now(),
        ];
        if (!empty($planKey)) {
            $updateData['kryvex_plan'] = strtolower(trim($planKey));
            $updateData['mt5_plan'] = strtolower(trim($planKey));
        }

        $this->updateUserRecord($userUid, $updateData);

        return $this->getUserBrokerStatus($userUid);
    }

    public function requestPlan(string $userUid, string $planKey): array
    {
        $this->ensureDefaultPlans();

        $plan = Mt5Plan::where('plan_key', strtolower($planKey))
            ->where('status', 'active')
            ->first();

        if (!$plan) {
            throw new RuntimeException('Selected MT5 plan does not exist or is inactive.');
        }

        $wallet = DB::connection('mongodb')
            ->table('demo_wallets')
            ->where('user_uid', $userUid)
            ->first();

        if (!$wallet) {
            $wallet = DB::connection('mongodb')
                ->table('wallets')
                ->where('user_uid', $userUid)
                ->first();
        }

        $walletArr = (array) $wallet;
        $mainBalance = (float) ($walletArr['demo_main_balance'] ?? ($walletArr['real_available_balance'] ?? 0));
        $requiredBalance = (float) $plan->minimum_balance;

        if ($mainBalance < $requiredBalance) {
            $formattedReq = number_format($requiredBalance, 2);
            throw new RuntimeException("Insufficient balance. Minimum balance required for {$plan->name} plan: $" . $formattedReq);
        }

        // Switch user broker_type to mt5 and update flags
        $this->updateUserRecord($userUid, [
            'broker_type' => 'mt5',
            'mt5_plan' => strtolower(trim($planKey)),
            'mt5_status' => 'pending',
            'broker_popup_seen' => 1,
            'updated_at' => now(),
        ]);

        // Cancel any previous pending requests
        Mt5Request::where('user_uid', $userUid)
            ->where('status', 'pending')
            ->update(['status' => 'superseded']);

        $request = Mt5Request::create([
            'request_uid' => 'mt5req_' . Str::lower(Str::random(12)),
            'user_uid' => $userUid,
            'plan_id' => (string) $plan->_id,
            'plan_name' => $plan->name,
            'minimum_balance' => $requiredBalance,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        return [
            'request' => $request->toArray(),
            'status' => $this->getUserBrokerStatus($userUid),
        ];
    }

    public function assignCredentials(
        string $userUid,
        string $login,
        string $password,
        string $server = 'Kryvex-Live-01',
        ?string $investorPassword = null,
        ?string $adminUid = null
    ): array {
        $login = trim($login);
        $password = trim($password);
        $server = trim($server ?: 'Kryvex-Live-01');

        if (empty($login) || empty($password)) {
            throw new RuntimeException('MT5 Login ID and Password are required.');
        }

        $pendingRequest = Mt5Request::where('user_uid', $userUid)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->first();

        $planName = $pendingRequest ? $pendingRequest->plan_name : 'Standard';
        $planId = $pendingRequest ? $pendingRequest->plan_id : '';
        $isCent = str_contains(strtolower($planName), 'cent');
        $accountCurrency = $isCent ? 'USC' : 'USDT';

        // Auto-provision on MetaApi if enabled
        $metaApiAccountId = null;
        $metaApiError = null;
        try {
            $metaApiService = app(\App\Services\MetaApiService::class);
            if ($metaApiService->isEnabled()) {
                $userRec = DB::connection('mongodb')->table('users')->where('_id', $userUid)->orWhere('uid', $userUid)->first();
                $userName = (string) (($userRec->name ?? $userRec->email ?? 'Trader') . " ({$login})");
                $provRes = $metaApiService->provisionAccount($userName, $login, $password, $server, 'mt5');
                if ($provRes['success'] ?? false) {
                    $metaApiAccountId = $provRes['account_id'] ?? null;
                } else {
                    $metaApiError = $provRes['message'] ?? 'Failed to authenticate to MT5 broker.';
                }
            }
        } catch (\Throwable $e) {
            $metaApiError = $e->getMessage();
            \Illuminate\Support\Facades\Log::warning('MetaApi provision error in assignCredentials: ' . $e->getMessage());
        }

        // Create or update active MT5 account
        $account = Mt5Account::updateOrCreate(
            ['user_uid' => $userUid],
            [
                'account_uid' => 'mt5acc_' . Str::lower(Str::random(12)),
                'plan_id' => $planId,
                'plan_name' => $planName,
                'is_cent' => $isCent,
                'login' => $login,
                'password' => $password,
                'investor_password' => $investorPassword ?: '',
                'server' => $server,
                'currency' => $accountCurrency,
                'metaapi_account_id' => $metaApiAccountId,
                'status' => 'active',
            ]
        );

        if ($pendingRequest) {
            $pendingRequest->update([
                'status' => 'approved',
                'approved_by' => $adminUid ?: 'admin',
                'approved_at' => now(),
            ]);
        }

        // Set user broker_type to mt5
        $updated = DB::connection('mongodb')
            ->table('users')
            ->where('_id', $userUid)
            ->update([
                'broker_type' => 'mt5',
                'updated_at' => now(),
            ]);

        if (!$updated) {
            DB::connection('mongodb')
                ->table('users')
                ->where('uid', $userUid)
                ->update([
                    'broker_type' => 'mt5',
                    'updated_at' => now(),
                ]);
        }

        // Dispatch Email Notification to user
        $userRecord = DB::connection('mongodb')
            ->table('users')
            ->where('_id', $userUid)
            ->first();

        if (!$userRecord) {
            $userRecord = DB::connection('mongodb')
                ->table('users')
                ->where('uid', $userUid)
                ->first();
        }

        $userArr = (array) $userRecord;
        $userEmail = $userArr['email'] ?? null;

        if ($userEmail) {
            try {
                Mail::to($userEmail)->send(new Mt5AccountReadyMail(
                    userName: (string) ($userArr['name'] ?? 'Trader'),
                    login: $login,
                    password: $password,
                    server: $server,
                    planName: $planName,
                    currency: 'USDT'
                ));
            } catch (\Throwable $e) {
                // Keep assignment successful even if email delivery fails locally
            }
        }

        return [
            'account' => $account->toArray(),
            'message' => 'MT5 credentials assigned successfully and email dispatched to user.',
        ];
    }

    public function saveUserCredentials(string $userUid, string $login, string $password, string $server = 'Kryvex-Live-01'): array
    {
        $login = trim($login);
        $password = trim($password);
        $server = trim($server ?: 'Kryvex-Live-01');

        if (empty($login) || empty($password)) {
            throw new RuntimeException('MT5 Login ID and Password are required.');
        }

        $pendingRequest = Mt5Request::where('user_uid', $userUid)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->first();

        $planName = $pendingRequest ? $pendingRequest->plan_name : 'Basic';
        $planId = $pendingRequest ? $pendingRequest->plan_id : '';
        $isCent = str_contains(strtolower($planName), 'cent');
        $accountCurrency = $isCent ? 'USC' : 'USDT';

        // Auto-provision on MetaApi if enabled
        $metaApiAccountId = null;
        $metaApiError = null;
        try {
            $metaApiService = app(\App\Services\MetaApiService::class);
            if ($metaApiService->isEnabled()) {
                $userRec = DB::connection('mongodb')->table('users')->where('_id', $userUid)->orWhere('uid', $userUid)->first();
                $userName = (string) (($userRec->name ?? $userRec->email ?? 'Trader') . " ({$login})");
                $provRes = $metaApiService->provisionAccount($userName, $login, $password, $server, 'mt5');
                if ($provRes['success'] ?? false) {
                    $metaApiAccountId = $provRes['account_id'] ?? null;
                } else {
                    $metaApiError = $provRes['message'] ?? 'MetaApi connection failed.';
                }
            }
        } catch (\Throwable $e) {
            $metaApiError = $e->getMessage();
            \Illuminate\Support\Facades\Log::warning('MetaApi provision error in saveUserCredentials: ' . $e->getMessage());
        }

        $account = Mt5Account::updateOrCreate(
            ['user_uid' => $userUid],
            [
                'account_uid' => 'mt5acc_' . Str::lower(Str::random(12)),
                'plan_id' => $planId,
                'plan_name' => $planName,
                'is_cent' => $isCent,
                'login' => $login,
                'password' => $password,
                'server' => $server,
                'currency' => $accountCurrency,
                'metaapi_account_id' => $metaApiAccountId,
                'status' => 'active',
            ]
        );

        if ($pendingRequest) {
            $pendingRequest->update([
                'status' => 'approved',
                'approved_by' => $userUid,
                'approved_at' => now(),
            ]);
        }

        // Set user broker_type to mt5
        $updated = DB::connection('mongodb')
            ->table('users')
            ->where('_id', $userUid)
            ->update([
                'broker_type' => 'mt5',
                'updated_at' => now(),
            ]);

        if (!$updated) {
            DB::connection('mongodb')
                ->table('users')
                ->where('uid', $userUid)
                ->update([
                    'broker_type' => 'mt5',
                    'updated_at' => now(),
                ]);
        }

        $status = $this->getUserBrokerStatus($userUid);
        if ($metaApiError && empty($metaApiAccountId)) {
            $status['metaapi_warning'] = $metaApiError;
        }

        return $status;
    }

    public function autoCheckPlanEligibility(string $userUid): void
    {
        $userRecord = DB::connection('mongodb')
            ->table('users')
            ->where('_id', $userUid)
            ->first();

        if (!$userRecord) {
            $userRecord = DB::connection('mongodb')
                ->table('users')
                ->where('uid', $userUid)
                ->first();
        }

        $userArr = (array) $userRecord;
        if (empty($userArr) || strtolower($userArr['broker_type'] ?? '') !== 'mt5') {
            return;
        }

        $account = Mt5Account::where('user_uid', $userUid)
            ->where('status', 'active')
            ->first();

        if (!$account) {
            return;
        }

        $wallet = DB::connection('mongodb')
            ->table('demo_wallets')
            ->where('user_uid', $userUid)
            ->first();

        $mainBalance = (float) ($wallet['demo_main_balance'] ?? 0);
        $currentPlanName = strtolower($account->plan_name ?? 'basic');

        $plans = Mt5Plan::where('status', 'active')
            ->get()
            ->sortByDesc('minimum_balance')
            ->values();

        // Check if balance dropped below current plan requirement
        $currentPlanModel = $plans->first(fn($p) => strtolower($p->name) === $currentPlanName);

        if ($currentPlanModel && $mainBalance < (float) $currentPlanModel->minimum_balance) {
            // Find highest qualifying plan
            $qualifyingPlan = $plans->first(fn($p) => $mainBalance >= (float) $p->minimum_balance) ?: $plans->last();

            if ($qualifyingPlan && strtolower($qualifyingPlan->name) !== $currentPlanName) {
                $account->update([
                    'plan_id' => (string) $qualifyingPlan->_id,
                    'plan_name' => $qualifyingPlan->name,
                ]);

                // Create notification for user
                DB::connection('mongodb')
                    ->table('notifications')
                    ->insert([
                        'notification_id' => 'notif_' . Str::lower(Str::random(12)),
                        'user_uid' => $userUid,
                        'title' => 'MT5 Plan Downgraded',
                        'message' => "Your MT5 Plan has been downgraded to {$qualifyingPlan->name} because your Main Wallet balance fell below the required minimum.",
                        'type' => 'warning',
                        'is_read' => false,
                        'created_at' => now(),
                    ]);
            }
        }
    }
}
