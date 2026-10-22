<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BybitService
{
    protected string $apiKey;
    protected string $apiSecret;
    protected string $environment;
    protected bool $enabled;
    protected string $baseUrl;

    //Auto setup for  Bybit API Key, Secret Key, Environment (Testnet or Mainnet)and  Bybit Status (Enabled/Disabled)
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

        $this->apiKey = $settings->bybit_api_key ?? config('services.bybit.api_key', env('BYBIT_API_KEY', ''));
        $this->apiSecret = $settings->bybit_api_secret ?? config('services.bybit.api_secret', env('BYBIT_API_SECRET', ''));
        $this->environment = strtolower($settings->bybit_environment ?? config('services.bybit.environment', env('BYBIT_ENV', 'mainnet')));
        $this->enabled = (bool) ($settings->bybit_enabled ?? env('BYBIT_ENABLED', true));

        $this->baseUrl = $this->environment === 'testnet'
            ? 'https://api-testnet.bybit.com'
            : 'https://api.bybit.com';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get 24hr Ticker data from Bybit v5 Market API.
     */
    public function getTickers(string $category = 'linear', ?string $symbol = null): array
    {
        try {
            $params = ['category' => $category];
            if ($symbol) {
                $params['symbol'] = strtoupper($symbol);
            }

            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/v5/market/tickers", $params);

            if ($response->successful()) {
                $json = $response->json();
                if (($json['retCode'] ?? -1) === 0) {
                    return $json['result']['list'] ?? [];
                }
            }
        } catch (\Throwable $e) {
            Log::error('BybitService getTickers Exception: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get Order Book Depth from Bybit v5 Market API.
     */
    public function getOrderBook(string $symbol, string $category = 'linear', int $limit = 20): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/v5/market/orderbook", [
                    'category' => $category,
                    'symbol' => strtoupper($symbol),
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                $json = $response->json();
                if (($json['retCode'] ?? -1) === 0) {
                    return $json['result'] ?? [];
                }
            }
        } catch (\Throwable $e) {
            Log::error('BybitService getOrderBook Exception: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get Kline / Candlestick data for TradingView charts.(1m, 5m, 1h) 
     */
    public function getKline(string $symbol, string $interval = '1', string $category = 'linear', int $limit = 200): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/v5/market/kline", [
                    'category' => $category,
                    'symbol' => strtoupper($symbol),
                    'interval' => $interval,
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                $json = $response->json();
                if (($json['retCode'] ?? -1) === 0) {
                    return $json['result']['list'] ?? [];
                }
            }
        } catch (\Throwable $e) {
            Log::error('BybitService getKline Exception: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Place an Order on Bybit v5 Trade API (Requires API Key & Secret).
     */
    public function placeOrder(array $params): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [
                'success' => false,
                'message' => 'Bybit API credentials not configured in Admin Settings.',
            ];
        }

        try {
            $timestamp = (string) round(microtime(true) * 1000);
            $recvWindow = '5000';

            $defaultParams = [
                'category' => 'linear',
                'orderType' => 'Market',
                'timeInForce' => 'GTC',
            ];
            $bodyParams = array_merge($defaultParams, $params);
            $jsonPayload = json_encode($bodyParams);

            $signature = $this->generateSignature($timestamp, $recvWindow, $jsonPayload);

            $response = Http::timeout(8)
                ->withHeaders([
                    'X-BAPI-API-KEY' => $this->apiKey,
                    'X-BAPI-SIGN' => $signature,// HMAC SHA256
                    'X-BAPI-TIMESTAMP' => $timestamp,
                    'X-BAPI-RECV-WINDOW' => $recvWindow,
                    'Content-Type' => 'application/json',
                ])
                ->post("{$this->baseUrl}/v5/order/create", $bodyParams);

            $json = $response->json();

            if ($response->successful() && ($json['retCode'] ?? -1) === 0) {
                return [
                    'success' => true,
                    'data' => $json['result'] ?? [],
                    'message' => 'Order placed on Bybit successfully.',
                ];
            }

            return [
                'success' => false,
                'message' => $json['retMsg'] ?? 'Failed to place order on Bybit.',
                'raw' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('BybitService placeOrder Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate HMAC SHA256 Signature for Bybit v5 API Requests.
     */
    protected function generateSignature(string $timestamp, string $recvWindow, string $queryStringOrBody): string
    {
        $signatureOrigin = $timestamp . $this->apiKey . $recvWindow . $queryStringOrBody;
        return hash_hmac('sha256', $signatureOrigin, $this->apiSecret);
    }
}
