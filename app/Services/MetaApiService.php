<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaApiService
{
    protected string $token;
    protected string $defaultAccountId;
    protected bool $enabled;

    protected string $clientApiBaseUrl = 'https://mt-client-api-v1.agiliumtrade.ai';
    protected string $provisioningApiBaseUrl = 'https://mt-provisioning-api-v1.agiliumtrade.ai';

    public function __construct()
    {
        $settings = null;
        try {
            $settings = DB::connection('mongodb')
                ->table('admin_settings')
                ->where('key', 'platform')
                ->first();
        } catch (\Throwable $e) {
            $settings = null;
        }

        $this->token = $settings->metaapi_token ?? config('services.metaapi.token', env('METAAPI_TOKEN', ''));
        $this->defaultAccountId = $settings->metaapi_account_id ?? config('services.metaapi.account_id', env('METAAPI_ACCOUNT_ID', ''));
        $this->enabled = (bool) ($settings->metaapi_enabled ?? env('METAAPI_ENABLED', true));
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->token);
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getDefaultAccountId(): string
    {
        return $this->defaultAccountId;
    }

    /**
     * Check if an account ID is a valid non-placeholder MetaApi account ID.
     */
    public function isValidAccountId(?string $accountId): bool
    {
        if (empty($accountId)) {
            return false;
        }
        $acc = trim($accountId);
        if (in_array(strtolower($acc), ['', 'your_deployed_mt5_account_id', 'null', 'none', 'placeholder'], true)) {
            return false;
        }
        if (str_starts_with(strtolower($acc), 'your_')) {
            return false;
        }
        return true;
    }

    /**
     * Resolve account ID (falls back to default account ID only if valid).
     */
    public function resolveAccountId(?string $accountId = null): ?string
    {
        $id = trim($accountId ?: $this->defaultAccountId);
        return $this->isValidAccountId($id) ? $id : null;
    }

    /**
     * Test connection to MetaApi using the configured account.
     */
    public function testConnection(?string $accountId = null): array
    {
        if (empty($this->token)) {
            return [
                'success' => false,
                'message' => 'MetaApi Token is not configured in Admin Settings or .env.',
            ];
        }

        $accId = $this->resolveAccountId($accountId);
        if (empty($accId)) {
            return [
                'success' => false,
                'message' => 'MetaApi Account ID is missing.',
            ];
        }

        return $this->getAccountInformation($accId);
    }

    /**
     * Get Client API Base URL for an account (resolved by region).
     */
    public function getClientBaseUrl(string $accountId): string
    {
        $region = cache()->remember("metaapi_region_{$accountId}", 86400, function () use ($accountId) {
            try {
                $res = Http::withOptions(['verify' => false])->timeout(10)->withHeaders([
                    'auth-token' => $this->token,
                    'Accept' => 'application/json',
                ])->get("{$this->provisioningApiBaseUrl}/users/current/accounts/{$accountId}");
                if ($res->successful()) {
                    return $res->json()['region'] ?? 'london';
                }
            } catch (\Throwable $e) {}
            return 'london';
        });

        return "https://mt-client-api-v1.{$region}.agiliumtrade.ai";
    }

    /**
     * Get MT5 Account Information (Balance, Equity, Margin, Leverage, etc.).
     */
    public function getAccountInformation(?string $accountId = null): array
    {
        $accId = $this->resolveAccountId($accountId);
        if (empty($this->token) || empty($accId)) {
            return ['success' => false, 'message' => 'MetaApi token or account ID missing.'];
        }

        try {
            $baseUrl = $this->getClientBaseUrl($accId);
            $response = Http::withOptions(['verify' => false])
                ->timeout(15)
                ->withHeaders([
                    'auth-token' => $this->token,
                    'Accept' => 'application/json',
                ])
                ->get("{$baseUrl}/users/current/accounts/{$accId}/accountInformation");

            $json = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $json,
                    'message' => 'MT5 Account information fetched successfully.',
                ];
            }

            return [
                'success' => false,
                'message' => $json['message'] ?? 'Failed to fetch MT5 account information.',
                'raw' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('MetaApiService getAccountInformation Error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Map symbol to MT5 standard format (e.g. BTCUSDT -> BTCUSD or keep as is).
     */
    public function normalizeSymbol(string $symbol): string
    {
        $clean = strtoupper(str_replace(['/', '-', '_', ' '], '', trim($symbol)));
        if (str_ends_with($clean, 'USDT')) {
            // Check if common crypto suffix should be mapped
            $base = substr($clean, 0, -4);
            return $base . 'USD';
        }
        return $clean;
    }

    /**
     * Place an Order on MetaTrader 5 via MetaApi (MARKET or LIMIT).
     *
     * @param array $params [
     *   'actionType' => 'ORDER_TYPE_BUY' | 'ORDER_TYPE_SELL' | 'ORDER_TYPE_BUY_LIMIT' | 'ORDER_TYPE_SELL_LIMIT',
     *   'symbol'     => 'EURUSD' | 'BTCUSD' | 'XAUUSD',
     *   'volume'     => 0.01 (lot size),
     *   'openPrice'  => 1.08500 (optional for limit orders),
     *   'stopLoss'   => 1.08000 (optional),
     *   'takeProfit' => 1.09500 (optional),
     *   'comment'    => 'Kryvex Order' (optional),
     * ]
     */
    public function placeOrder(array $params, ?string $accountId = null): array
    {
        $accId = $this->resolveAccountId($accountId);
        if (empty($this->token) || empty($accId)) {
            return ['success' => false, 'message' => 'MetaApi token or account ID missing.'];
        }

        try {
            $symbol = $this->normalizeSymbol((string) ($params['symbol'] ?? 'EURUSD'));
            $tradePayload = [
                'actionType' => strtoupper($params['actionType'] ?? 'ORDER_TYPE_BUY'),
                'symbol'     => $symbol,
                'volume'     => max(0.01, (float) ($params['volume'] ?? 0.01)),
            ];

            if (!empty($params['openPrice']) && (float) $params['openPrice'] > 0) {
                $tradePayload['openPrice'] = (float) $params['openPrice'];
            }
            if (!empty($params['stopLoss']) && (float) $params['stopLoss'] > 0) {
                $tradePayload['stopLoss'] = (float) $params['stopLoss'];
            }
            if (!empty($params['takeProfit']) && (float) $params['takeProfit'] > 0) {
                $tradePayload['takeProfit'] = (float) $params['takeProfit'];
            }
            if (!empty($params['comment'])) {
                $tradePayload['comment'] = substr((string) $params['comment'], 0, 31);
            }

            $baseUrl = $this->getClientBaseUrl($accId);
            $response = Http::withOptions(['verify' => false])
                ->timeout(25)
                ->withHeaders([
                    'auth-token' => $this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/users/current/accounts/{$accId}/trade", $tradePayload);

            $json = $response->json();

            if ($response->successful() && (($json['numericCode'] ?? 0) === 10009 || isset($json['orderId']) || isset($json['positionId']))) {
                Log::info("MetaApi Order Placed: Symbol {$symbol}, OrderId: " . ($json['orderId'] ?? $json['positionId'] ?? ''));
                return [
                    'success' => true,
                    'data' => $json,
                    'orderId' => (string) ($json['orderId'] ?? $json['positionId'] ?? ''),
                    'message' => 'Order placed on MetaTrader 5 successfully.',
                ];
            }

            $errorMsg = $json['message'] ?? $json['stringCode'] ?? 'Failed to place MT5 order.';
            Log::warning("MetaApi placeOrder rejected: {$errorMsg}", ['payload' => $tradePayload, 'response' => $json]);

            return [
                'success' => false,
                'message' => $errorMsg,
                'raw' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('MetaApiService placeOrder Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get All Open Positions on MT5 for an account.
     */
    public function getPositions(?string $accountId = null): array
    {
        $accId = $this->resolveAccountId($accountId);
        if (empty($this->token) || empty($accId)) {
            return [];
        }

        try {
            $baseUrl = $this->getClientBaseUrl($accId);
            $response = Http::withOptions(['verify' => false])
                ->timeout(15)
                ->withHeaders([
                    'auth-token' => $this->token,
                    'Accept' => 'application/json',
                ])
                ->get("{$baseUrl}/users/current/accounts/{$accId}/positions");

            $json = $response->json();
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            Log::error('MetaApiService getPositions Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Close an Open Position on MT5 by Position ID.
     */
    public function closePosition(string $positionId, ?string $accountId = null): array
    {
        $accId = $this->resolveAccountId($accountId);
        if (empty($this->token) || empty($accId)) {
            return ['success' => false, 'message' => 'MetaApi credentials missing.'];
        }

        try {
            $payload = [
                'actionType' => 'POSITION_CLOSE_ID',
                'positionId' => (string) $positionId,
            ];

            $baseUrl = $this->getClientBaseUrl($accId);
            $response = Http::withOptions(['verify' => false])
                ->timeout(20)
                ->withHeaders([
                    'auth-token' => $this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/users/current/accounts/{$accId}/trade", $payload);

            $json = $response->json();

            if ($response->successful() && (($json['numericCode'] ?? 0) === 10009 || isset($json['orderId']))) {
                return ['success' => true, 'data' => $json, 'message' => 'MT5 Position closed successfully.'];
            }

            return ['success' => false, 'message' => $json['message'] ?? 'Failed to close MT5 position.'];
        } catch (\Throwable $e) {
            Log::error('MetaApiService closePosition Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cancel a Pending Order on MT5 by Order ID.
     */
    public function cancelOrder(string $orderId, ?string $accountId = null): array
    {
        $accId = $this->resolveAccountId($accountId);
        if (empty($this->token) || empty($accId)) {
            return ['success' => false, 'message' => 'MetaApi credentials missing.'];
        }

        try {
            $payload = [
                'actionType' => 'ORDER_CANCEL',
                'orderId' => (string) $orderId,
            ];

            $baseUrl = $this->getClientBaseUrl($accId);
            $response = Http::withOptions(['verify' => false])
                ->timeout(20)
                ->withHeaders([
                    'auth-token' => $this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$baseUrl}/users/current/accounts/{$accId}/trade", $payload);

            $json = $response->json();

            if ($response->successful() && (($json['numericCode'] ?? 0) === 10009 || isset($json['orderId']))) {
                return ['success' => true, 'data' => $json, 'message' => 'MT5 Pending order cancelled successfully.'];
            }

            return ['success' => false, 'message' => $json['message'] ?? 'Failed to cancel MT5 order.'];
        } catch (\Throwable $e) {
            Log::error('MetaApiService cancelOrder Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Provision (Register & Deploy) a new MT5 account on MetaApi automatically.
     * This is used when a new user in Kryvex gets their MT5 credentials.
     */
    public function provisionAccount(string $name, string $login, string $password, string $server, string $platform = 'mt5'): array
    {
        @set_time_limit(90);

        if (empty($this->token)) {
            return ['success' => false, 'message' => 'MetaApi token not configured in Admin Settings.'];
        }

        try {
            $accountPayload = [
                'name' => $name,
                'type' => 'cloud',
                'login' => trim($login),
                'password' => trim($password),
                'server' => trim($server),
                'platform' => strtolower($platform),
                'magic' => 10001,
            ];

            // 1. Create account on MetaApi
            $res = Http::withOptions(['verify' => false])
                ->timeout(35)
                ->withHeaders([
                    'auth-token' => $this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post("{$this->provisioningApiBaseUrl}/users/current/accounts", $accountPayload);

            $json = $res->json();

            // Check if resourceSlots needed (e.g. CXMDirect requires resourceSlots: 2)
            if (!$res->successful() && !empty($json['details']['recommendedResourceSlots'])) {
                $accountPayload['resourceSlots'] = (int) $json['details']['recommendedResourceSlots'];
                $res = Http::withOptions(['verify' => false])
                    ->timeout(35)
                    ->withHeaders([
                        'auth-token' => $this->token,
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ])
                    ->post("{$this->provisioningApiBaseUrl}/users/current/accounts", $accountPayload);
                $json = $res->json();
            }

            if (!$res->successful() || empty($json['id']) || isset($json['error'])) {
                $rawMsg = $json['message'] ?? 'Failed to authenticate and connect to MT5 broker.';
                
                // Format user-friendly error message
                $cleanMsg = $rawMsg;
                if (str_contains($rawMsg, 'failed to authenticate') || ($json['details'] ?? '') === 'E_AUTH') {
                    $cleanMsg = "Invalid MT5 Credentials: Broker rejected Login ID, Password, or Server Name. Please verify your exact MT5 Login ID, Master Password, and Server Name.";
                } elseif (str_contains($rawMsg, 'rejected too many times') || ($json['error'] ?? '') === 'TooManyRequestsError') {
                    $cleanMsg = "Connection rate limit: These MT5 credentials were rejected by the broker. Please verify your credentials in your MT5 app and try again in a few minutes.";
                } elseif (str_contains($rawMsg, '.dat file for server') || str_contains($rawMsg, 'server not found')) {
                    $cleanMsg = "Invalid Server Name: The server '{$server}' was not recognized. Please check the exact server name in your MT5 app (e.g., MetaQuotes-Demo).";
                } elseif (str_contains($rawMsg, 'resource slots') || ($json['details']['code'] ?? '') === 'E_RESOURCE_SLOTS') {
                    $cleanMsg = "Account configuration requires higher resource slots.";
                }

                return [
                    'success' => false,
                    'message' => $cleanMsg,
                    'raw' => $json,
                ];
            }

            $accountId = (string) $json['id'];

            // 2. Automatically Deploy the account on MetaApi
            try {
                $deployRes = Http::withOptions(['verify' => false])
                    ->timeout(20)
                    ->withHeaders([
                        'auth-token' => $this->token,
                        'Accept' => 'application/json',
                    ])
                    ->post("{$this->provisioningApiBaseUrl}/users/current/accounts/{$accountId}/deploy");

                if (!$deployRes->successful()) {
                    $deployJson = $deployRes->json();
                    if (($deployJson['error'] ?? '') === 'ForbiddenError' || str_contains($deployJson['message'] ?? '', 'top up')) {
                        return [
                            'success' => false,
                            'message' => 'MetaApi Account Top-Up Required: Your MetaApi account has $0 balance. Please top up credits on your MetaApi dashboard (https://app.metaapi.cloud/) to deploy this cloud MT5 connection.',
                            'raw' => $deployJson,
                        ];
                    }
                }
            } catch (\Throwable $de) {
                Log::warning('MetaApi deploy warning: ' . $de->getMessage());
            }

            return [
                'success' => true,
                'account_id' => $accountId,
                'message' => 'Account connected and deployed on MetaApi MT5 successfully.',
            ];
        } catch (\Throwable $e) {
            Log::error('MetaApiService provisionAccount Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}



