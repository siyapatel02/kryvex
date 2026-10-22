<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminSettingController extends Controller
{
    public function index()
    {
        $settings = DB::connection('mongodb')
            ->table('admin_settings')
            ->where('key', 'platform')
            ->first();

        if (!$settings) {
            $settings = (object) $this->defaultSettings();
        }

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            // General / brand details.
            'exchange_name' => ['nullable', 'string', 'max:120'],
            'support_email' => ['nullable', 'email', 'max:150'],
            'support_phone' => ['nullable', 'string', 'max:40'],

            // Wallet and withdrawal rules.
            'minimum_wallet_balance' => ['required', 'numeric', 'min:0'],
            'minimum_withdraw_amount' => ['nullable', 'numeric', 'min:0'],
            'maximum_withdraw_amount' => ['nullable', 'numeric', 'min:0'],
            'withdrawal_fee_type' => ['nullable', 'in:fixed,percentage'],
            'withdrawal_fee_value' => ['nullable', 'numeric', 'min:0'],
            'platform_charge_type' => ['nullable', 'in:fixed,percentage'],
            'platform_charge_value' => ['nullable', 'numeric', 'min:0'],
            'usdt_inr_rate' => ['nullable', 'numeric', 'min:0'],
            'usdt_inr_buy_rate' => ['nullable', 'numeric', 'min:0'],
            'usdt_inr_sell_rate' => ['nullable', 'numeric', 'min:0'],
            'inr_bank_details' => ['nullable', 'array'],
            'inr_bank_details.account_name' => ['nullable', 'string', 'max:120'],
            'inr_bank_details.bank_name' => ['nullable', 'string', 'max:120'],
            'inr_bank_details.account_number' => ['nullable', 'string', 'max:50'],
            'inr_bank_details.ifsc' => ['nullable', 'string', 'max:30'],
            'inr_bank_details.upi_id' => ['nullable', 'string', 'max:100'],
            'company_wallets' => ['nullable', 'array'],
            'company_wallets.*.address' => ['nullable', 'string', 'max:140'],
            'company_wallets.*.minimum_amount' => ['nullable', 'numeric', 'min:0'],

            // Trading rules.
            'maker_fee' => ['nullable', 'numeric', 'min:0'],
            'taker_fee' => ['nullable', 'numeric', 'min:0'],
            'futures_trading_fee_rate' => ['required', 'numeric', 'min:0'],
            'options_trading_fee_rate' => ['required', 'numeric', 'min:0'],
            'max_leverage' => ['required', 'integer', 'min:1', 'max:200'],

            // Plan-Based Futures Spread Engine settings.
            'futures_spread_enabled' => ['nullable'],
            'futures_spread_type' => ['nullable', 'in:percentage,fixed'],
            'futures_spread_value' => ['nullable', 'numeric', 'min:0'],

            'plan_spread_cent_enabled' => ['nullable'],
            'plan_spread_cent_type' => ['nullable', 'in:percentage,fixed'],
            'plan_spread_cent_value' => ['nullable', 'numeric', 'min:0'],

            'plan_spread_basic_enabled' => ['nullable'],
            'plan_spread_basic_type' => ['nullable', 'in:percentage,fixed'],
            'plan_spread_basic_value' => ['nullable', 'numeric', 'min:0'],

            'plan_spread_standard_enabled' => ['nullable'],
            'plan_spread_standard_type' => ['nullable', 'in:percentage,fixed'],
            'plan_spread_standard_value' => ['nullable', 'numeric', 'min:0'],

            'plan_spread_advanced_enabled' => ['nullable'],
            'plan_spread_advanced_type' => ['nullable', 'in:percentage,fixed'],
            'plan_spread_advanced_value' => ['nullable', 'numeric', 'min:0'],

            // Exchange Provider Selection & Binance API Settings.
            'active_exchange_provider' => ['nullable', 'in:binance,bybit'],
            'binance_api_key' => ['nullable', 'string', 'max:255'],
            'binance_api_secret' => ['nullable', 'string', 'max:255'],
            'binance_environment' => ['nullable', 'in:testnet,mainnet'],
            'binance_enabled' => ['nullable'],

            // Razorpay Payment Gateway Settings.
            'razorpay_key_id' => ['nullable', 'string', 'max:255'],
            'razorpay_key_secret' => ['nullable', 'string', 'max:255'],
            'razorpay_environment' => ['nullable', 'in:test,live'],
            'razorpay_enabled' => ['nullable'],

            // Bybit Exchange API Settings.
            'bybit_api_key' => ['nullable', 'string', 'max:255'],
            'bybit_api_secret' => ['nullable', 'string', 'max:255'],
            'bybit_environment' => ['nullable', 'in:testnet,mainnet'],
            'bybit_enabled' => ['nullable'],

            // MetaApi MT5 Cloud API Settings.
            'metaapi_token' => ['nullable', 'string'],
            'metaapi_account_id' => ['nullable', 'string'],
            'metaapi_enabled' => ['nullable'],

            // Feature toggles.
            'withdrawal_enabled' => ['nullable'],
            'trading_enabled' => ['nullable'],
            'futures_enabled' => ['nullable'],
            'options_enabled' => ['nullable'],
            'google_login_enabled' => ['nullable'],
            'email_otp_enabled' => ['nullable'],
            'maintenance_mode' => ['nullable'],
        ]);

        $minimumWithdraw = (float) ($validated['minimum_withdraw_amount'] ?? 10);
        $maximumWithdraw = (float) ($validated['maximum_withdraw_amount'] ?? 100000);

        if ($maximumWithdraw > 0 && $maximumWithdraw < $minimumWithdraw) {
            return back()
                ->withInput()
                ->with('error', 'Maximum withdraw amount must be greater than or equal to minimum withdraw amount.');
        }

        if ($request->boolean('razorpay_enabled')) {
            $rzpKeyId = trim((string) ($validated['razorpay_key_id'] ?? ''));
            $rzpSecret = trim((string) ($validated['razorpay_key_secret'] ?? ''));
            $rzpEnv = $validated['razorpay_environment'] ?? 'test';

            if ($rzpKeyId === '' || $rzpSecret === '') {
                return back()
                    ->withInput()
                    ->with('error', 'Razorpay is enabled but the Key ID or Key Secret is empty. Enter both keys from Razorpay Dashboard → Account & Settings → API Keys.');
            }
            if (! str_starts_with($rzpKeyId, $rzpEnv === 'live' ? 'rzp_live_' : 'rzp_test_')) {
                return back()
                    ->withInput()
                    ->with('error', $rzpEnv === 'live'
                        ? 'Razorpay Live Mode needs a Key ID starting with rzp_live_.'
                        : 'Razorpay Test Mode needs a Key ID starting with rzp_test_.');
            }
        }

        $payload = [
            'key' => 'platform',

            // General details displayed in the settings preview and future templates.
            'exchange_name' => $validated['exchange_name'] ?? 'Kryvex Exchange',
            'support_email' => $validated['support_email'] ?? 'support@kryvex.com',
            'support_phone' => $validated['support_phone'] ?? null,

            // Wallet reserve and withdrawal charge settings.
            'minimum_wallet_balance' => (float) $validated['minimum_wallet_balance'],
            'minimum_withdraw_amount' => $minimumWithdraw,
            'maximum_withdraw_amount' => $maximumWithdraw,
            'withdrawal_fee_type' => $validated['withdrawal_fee_type'] ?? 'percentage',
            'withdrawal_fee_value' => (float) ($validated['withdrawal_fee_value'] ?? 1),
            'platform_charge_type' => $validated['platform_charge_type'] ?? 'fixed',
            'platform_charge_value' => (float) ($validated['platform_charge_value'] ?? 2),
            'usdt_inr_rate' => (float) ($validated['usdt_inr_rate'] ?? 95.0),
            'usdt_inr_buy_rate' => (float) ($validated['usdt_inr_buy_rate'] ?? ($validated['usdt_inr_rate'] ?? 95.0)),
            'usdt_inr_sell_rate' => (float) ($validated['usdt_inr_sell_rate'] ?? ($validated['usdt_inr_rate'] ?? 94.50)),
            'inr_bank_details' => $validated['inr_bank_details'] ?? [],
            'company_deposit_wallets' => $this->companyWalletPayload($request->input('company_wallets', [])),

            // Trading settings.
            'maker_fee' => (float) ($request->input('maker_fee') ?? ($existing->maker_fee ?? 0.0002)),
            'taker_fee' => (float) ($request->input('taker_fee') ?? ($existing->taker_fee ?? 0.0005)),
            'max_leverage' => (int) $validated['max_leverage'],
            'futures_trading_fee_rate' => (float) $validated['futures_trading_fee_rate'],
            'options_trading_fee_rate' => (float) $validated['options_trading_fee_rate'],

            // Plan-Based Futures Spread Engine settings.
            'futures_spread_enabled' => $request->boolean('futures_spread_enabled'),
            'futures_spread_type' => $validated['futures_spread_type'] ?? 'percentage',
            'futures_spread_value' => (float) ($validated['futures_spread_value'] ?? 0.05),

            'plan_spread_cent_enabled' => $request->boolean('plan_spread_cent_enabled'),
            'plan_spread_cent_type' => $validated['plan_spread_cent_type'] ?? 'percentage',
            'plan_spread_cent_value' => (float) ($validated['plan_spread_cent_value'] ?? 0.08),

            'plan_spread_basic_enabled' => $request->boolean('plan_spread_basic_enabled'),
            'plan_spread_basic_type' => $validated['plan_spread_basic_type'] ?? 'percentage',
            'plan_spread_basic_value' => (float) ($validated['plan_spread_basic_value'] ?? 0.05),

            'plan_spread_standard_enabled' => $request->boolean('plan_spread_standard_enabled'),
            'plan_spread_standard_type' => $validated['plan_spread_standard_type'] ?? 'percentage',
            'plan_spread_standard_value' => (float) ($validated['plan_spread_standard_value'] ?? 0.03),

            'plan_spread_advanced_enabled' => $request->boolean('plan_spread_advanced_enabled'),
            'plan_spread_advanced_type' => $validated['plan_spread_advanced_type'] ?? 'percentage',
            'plan_spread_advanced_value' => (float) ($validated['plan_spread_advanced_value'] ?? 0.01),

            // Feature toggles & Exchange API settings.
            'active_exchange_provider' => $validated['active_exchange_provider'] ?? 'binance',
            'binance_api_key' => isset($validated['binance_api_key']) ? trim($validated['binance_api_key']) : null,
            'binance_api_secret' => isset($validated['binance_api_secret']) ? trim($validated['binance_api_secret']) : null,
            'binance_environment' => $validated['binance_environment'] ?? 'testnet',
            'binance_enabled' => $request->boolean('binance_enabled'),

            'bybit_api_key' => isset($validated['bybit_api_key']) ? trim($validated['bybit_api_key']) : null,
            'bybit_api_secret' => isset($validated['bybit_api_secret']) ? trim($validated['bybit_api_secret']) : null,
            'bybit_environment' => $validated['bybit_environment'] ?? 'mainnet',
            'bybit_enabled' => $request->boolean('bybit_enabled'),

            'razorpay_key_id' => isset($validated['razorpay_key_id']) ? trim($validated['razorpay_key_id']) : null,
            'razorpay_key_secret' => isset($validated['razorpay_key_secret']) ? trim($validated['razorpay_key_secret']) : null,
            'razorpay_environment' => $validated['razorpay_environment'] ?? 'test',
            'razorpay_enabled' => $request->boolean('razorpay_enabled'),

            'metaapi_token' => isset($validated['metaapi_token']) ? trim($validated['metaapi_token']) : null,
            'metaapi_account_id' => isset($validated['metaapi_account_id']) ? trim($validated['metaapi_account_id']) : null,
            'metaapi_enabled' => $request->boolean('metaapi_enabled'),

            'withdrawal_enabled' => $request->boolean('withdrawal_enabled'),
            'trading_enabled' => $request->boolean('trading_enabled'),
            'futures_enabled' => $request->boolean('futures_enabled'),
            'options_enabled' => $request->boolean('options_enabled'),
            'google_login_enabled' => $request->boolean('google_login_enabled'),
            'email_otp_enabled' => $request->boolean('email_otp_enabled'),
            'maintenance_mode' => $request->boolean('maintenance_mode'),

            'updated_at' => now(),
        ];

        $existing = DB::connection('mongodb')
            ->table('admin_settings')
            ->where('key', 'platform')
            ->first();

        if ($existing) {
            DB::connection('mongodb')
                ->table('admin_settings')
                ->where('key', 'platform')
                ->update($payload);
        } else {
            $payload['created_at'] = now();

            DB::connection('mongodb')
                ->table('admin_settings')
                ->insert($payload);
        }

        Cache::forget('platform_settings');

        AuditLogger::log(
            'update',
            'settings',
            'Platform settings updated.',
            [
                'minimum_wallet_balance' => $payload['minimum_wallet_balance'],
                'minimum_withdraw_amount' => $payload['minimum_withdraw_amount'],
                'maximum_withdraw_amount' => $payload['maximum_withdraw_amount'],
                'withdrawal_fee_type' => $payload['withdrawal_fee_type'],
                'withdrawal_fee_value' => $payload['withdrawal_fee_value'],
                'platform_charge_type' => $payload['platform_charge_type'],
                'platform_charge_value' => $payload['platform_charge_value'],
                'company_deposit_wallets' => $payload['company_deposit_wallets'],
                'maker_fee' => $payload['maker_fee'],
                'taker_fee' => $payload['taker_fee'],
                'max_leverage' => $payload['max_leverage'],
                'futures_trading_fee_rate' => $payload['futures_trading_fee_rate'],
                'options_trading_fee_rate' => $payload['options_trading_fee_rate'],
                'withdrawal_enabled' => $payload['withdrawal_enabled'],
                'trading_enabled' => $payload['trading_enabled'],
                'futures_enabled' => $payload['futures_enabled'],
                'options_enabled' => $payload['options_enabled'],
                'google_login_enabled' => $payload['google_login_enabled'],
                'email_otp_enabled' => $payload['email_otp_enabled'],
                'maintenance_mode' => $payload['maintenance_mode'],
            ]
        );

        $notificationResult = $this->notifyUsersAboutSettingChanges($existing, $payload);
        $successMessage = 'Settings updated successfully.';

        if ($notificationResult['sent']) {
            $successMessage .= ' User notification and email sent.';
        }

        return back()->with('success', $successMessage);
    }

    private function notifyUsersAboutSettingChanges($oldSettings, array $payload): array
    {
        if (! $oldSettings) {
            return ['sent' => false];
        }

        $changes = $this->userAffectingSettingChanges($oldSettings, $payload);

        if (empty($changes)) {
            return ['sent' => false];
        }

        $maintenanceChanged = array_key_exists('maintenance_mode', $changes);

        if ($maintenanceChanged) {
            $enabled = (bool) $payload['maintenance_mode'];
            $title = $enabled ? 'Platform Maintenance Started' : 'Platform Maintenance Ended';
            $message = $enabled
                ? 'Kryvex is currently in maintenance mode. Some actions may be temporarily unavailable.'
                : 'Kryvex maintenance mode has ended. Platform services are available again.';
            $type = 'maintenance';
        } else {
            $title = 'Platform Settings Updated';
            $message = 'Kryvex platform settings were updated. Please review wallet, withdrawal or trading availability before placing new actions.';
            $type = 'update';
        }

        $result = AdminNotificationController::createForAllUsers(
            $type,
            $title,
            $message,
            [
                'source' => 'admin_settings',
                'changes' => $changes,
                'admin' => session('admin_email') ?? 'admin',
            ],
            false
        );

        return ['sent' => true] + $result;
    }

    private function userAffectingSettingChanges($oldSettings, array $payload): array
    {
        $keys = [
            'maintenance_mode',
            'withdrawal_enabled',
            'trading_enabled',
            'futures_enabled',
            'options_enabled',
            'google_login_enabled',
            'email_otp_enabled',
            'minimum_wallet_balance',
            'minimum_withdraw_amount',
            'maximum_withdraw_amount',
            'withdrawal_fee_type',
            'withdrawal_fee_value',
            'platform_charge_type',
            'platform_charge_value',
            'company_deposit_wallets',
            'maker_fee',
            'taker_fee',
            'futures_trading_fee_rate',
            'options_trading_fee_rate',
            'max_leverage',
        ];

        $changes = [];

        foreach ($keys as $key) {
            $oldValue = $oldSettings->{$key} ?? null;
            $newValue = $payload[$key] ?? null;

            if ($this->normalizeSettingValue($oldValue) !== $this->normalizeSettingValue($newValue)) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    private function normalizeSettingValue($value): string
    {
        if (is_array($value)) {
            return json_encode($value);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) round((float) $value, 8);
        }

        return (string) $value;
    }


    private function companyWalletPayload(array $input): array
    {
        $rules = $this->walletAddressRules();
        $payload = [];

        foreach ($rules as $network => $rule) {
            $row = (array) ($input[$network] ?? []);
            $address = trim((string) ($row['address'] ?? ''));
            $minimum = (float) ($row['minimum_amount'] ?? 0);

            if ($address !== '' && ! $this->isValidWalletAddress($network, $address)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'company_wallets.' . $network . '.address' => $rule['label'] . ' address must start with ' . $rule['starts_with'] . ' and contain ' . $rule['min'] . ($rule['min'] === $rule['max'] ? '' : '-' . $rule['max']) . ' characters.',
                ]);
            }

            $payload[$network] = [
                'network' => $network,
                'label' => $rule['label'],
                'address' => $address,
                'minimum_amount' => max($minimum, 0),
                'starts_with' => $rule['starts_with'],
            ];
        }

        return $payload;
    }

    private function walletAddressRules(): array
    {
        return [
            'USD_TRC20' => ['label' => 'USD TRC20', 'starts_with' => 'T', 'min' => 34, 'max' => 34],
            'USD_ERC20' => ['label' => 'USD ERC20', 'starts_with' => '0x', 'min' => 42, 'max' => 42],
            'USD_BEP20' => ['label' => 'USD BEP20', 'starts_with' => '0x', 'min' => 42, 'max' => 42],
            'BTC' => ['label' => 'Bitcoin', 'starts_with' => '1 / 3 / bc1', 'min' => 26, 'max' => 62],
            'ETH' => ['label' => 'Ethereum', 'starts_with' => '0x', 'min' => 42, 'max' => 42],
            'SOL' => ['label' => 'Solana', 'starts_with' => 'Base58', 'min' => 30, 'max' => 44],
            'USDC' => ['label' => 'USDC ERC20', 'starts_with' => '0x', 'min' => 42, 'max' => 42],
        ];
    }

    private function defaultCompanyWallets(): array
    {
        return [
            'USD_TRC20' => ['network' => 'USD_TRC20', 'label' => 'USD TRC20', 'address' => 'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE', 'minimum_amount' => 10, 'starts_with' => 'T'],
            'USD_ERC20' => ['network' => 'USD_ERC20', 'label' => 'USD ERC20', 'address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'minimum_amount' => 25, 'starts_with' => '0x'],
            'USD_BEP20' => ['network' => 'USD_BEP20', 'label' => 'USD BEP20', 'address' => '0x8ba1f109551bD432803012645Ac136ddd64DBA72', 'minimum_amount' => 10, 'starts_with' => '0x'],
            'BTC' => ['network' => 'BTC', 'label' => 'Bitcoin', 'address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0w1h', 'minimum_amount' => 10, 'starts_with' => '1 / 3 / bc1'],
            'ETH' => ['network' => 'ETH', 'label' => 'Ethereum', 'address' => '0x742d35Cc6634C0532925a3b844Bc454e4438f44e', 'minimum_amount' => 25, 'starts_with' => '0x'],
            'SOL' => ['network' => 'SOL', 'label' => 'Solana', 'address' => '7EqQdEULxWcraVx3mXKK2TbPgYfvKwC5s1vZMjTLxK3', 'minimum_amount' => 0.01, 'starts_with' => 'Base58'],
            'USDC' => ['network' => 'USDC', 'label' => 'USDC', 'address' => '0x8ba1f109551bD432803012645Ac136ddd64DBA72', 'minimum_amount' => 10, 'starts_with' => '0x'],
        ];
    }

    private function isValidWalletAddress(string $network, string $address): bool
    {
        $address = trim($address);
        return match (strtoupper($network)) {
            'BTC' => (preg_match('/^(1|3|bc1)[A-Za-z0-9]{23,59}$/', $address) === 1 || preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1),
            'ETH', 'USD_ERC20', 'USD_BEP20', 'USDC' => preg_match('/^0x[a-fA-F0-9]{40}$/', $address) === 1,
            'USD_TRC20' => preg_match('/^T[A-Za-z0-9]{33}$/', $address) === 1,
            default => strlen($address) >= 15,
        };
    }

    private function defaultSettings(): array
    {
        return [
            'key' => 'platform',

            'exchange_name' => 'Kryvex Exchange',
            'support_email' => 'support@kryvex.com',
            'support_phone' => null,

            'minimum_wallet_balance' => 2000,
            'minimum_withdraw_amount' => 10,
            'maximum_withdraw_amount' => 100000,
            'withdrawal_fee_type' => 'percentage',
            'withdrawal_fee_value' => 1,
            'platform_charge_type' => 'fixed',
            'platform_charge_value' => 2,
            'usdt_inr_rate' => 95.0,
            'usdt_inr_buy_rate' => 95.0,
            'usdt_inr_sell_rate' => 94.50,
            'inr_bank_details' => [
                'bank_name' => '',
                'account_name' => '',
                'account_number' => '',
                'ifsc' => '',
                'upi_id' => '',
            ],
            'company_deposit_wallets' => $this->defaultCompanyWallets(),

            'maker_fee' => 0.0002,
            'taker_fee' => 0.0005,
            'max_leverage' => 100,
            'futures_trading_fee_rate' => 0.0005,
            'options_trading_fee_rate' => 0.0005,

            'withdrawal_enabled' => true,
            'trading_enabled' => true,
            'futures_enabled' => true,
            'options_enabled' => true,
            'google_login_enabled' => true,
            'email_otp_enabled' => true,
            'maintenance_mode' => false,
            'metaapi_enabled' => true,
            'metaapi_token' => null,
            'metaapi_account_id' => null,
        ];
    }

    public function testMetaApiConnection(Request $request)
    {
        $metaApiService = app(\App\Services\MetaApiService::class);
        $result = $metaApiService->testConnection($request->input('account_id'));
        return response()->json($result);
    }
}

