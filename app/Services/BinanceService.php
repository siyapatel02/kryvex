<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceService
{
    protected string $apiKey;
    protected string $apiSecret;
    protected string $environment;
    protected bool $enabled;
    protected string $baseUrl;
    protected static ?float $timeOffset = null;
    protected static ?float $timeOffsetFetchedAt = null;

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

        $this->apiKey = $settings->binance_api_key ?? config('services.binance.api_key', env('BINANCE_API_KEY', ''));
        $this->apiSecret = $settings->binance_api_secret ?? config('services.binance.api_secret', env('BINANCE_API_SECRET', ''));
        $this->environment = strtolower($settings->binance_environment ?? config('services.binance.environment', env('BINANCE_ENV', 'testnet')));
        $this->enabled = (bool) ($settings->binance_enabled ?? env('BINANCE_ENABLED', true));

        $this->baseUrl = $this->environment === 'testnet'
            ? 'https://testnet.binancefuture.com'
            : 'https://fapi.binance.com';
    }

    /**
     * Synchronize time with Binance Server to prevent timestamp skew errors (-1021).
     */
    public function getServerTimestamp(bool $forceRefresh = false): string
    {
        $now = microtime(true) * 1000;
        if (self::$timeOffset === null || $forceRefresh || (self::$timeOffsetFetchedAt && ($now - self::$timeOffsetFetchedAt) > 300000)) {
            try {
                $res = Http::timeout(3)->get("{$this->baseUrl}/fapi/v1/time");
                if ($res->successful() && isset($res['serverTime'])) {
                    $serverTime = (float) $res['serverTime'];
                    self::$timeOffset = $serverTime - $now;
                    self::$timeOffsetFetchedAt = $now;
                }
            } catch (\Throwable $e) {
                if (self::$timeOffset === null) {
                    self::$timeOffset = -1500;
                }
            }
        }

        $offset = self::$timeOffset ?? 0;
        return (string) round($now + $offset - 500);
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
     * Get 24hr Tickers from Binance Futures API.
     */
    public function getTickers(?string $symbol = null): array
    {
        try {
            $params = [];
            if ($symbol) {
                $params['symbol'] = strtoupper($symbol);
            }

            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/fapi/v1/ticker/24hr", $params);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Throwable $e) {
            Log::error('BinanceService getTickers Exception: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get Order Book Depth from Binance Futures API.
     */
    public function getOrderBook(string $symbol, int $limit = 20): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/fapi/v1/depth", [
                    'symbol' => strtoupper($symbol),
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Throwable $e) {
            Log::error('BinanceService getOrderBook Exception: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Get Kline / Candlestick data for TradingView charts.
     */
    public function getKline(string $symbol, string $interval = '1m', int $limit = 200): array
    {
        try {
            $response = Http::timeout(5)
                ->get("{$this->baseUrl}/fapi/v1/klines", [
                    'symbol' => strtoupper($symbol),
                    'interval' => $interval,
                    'limit' => $limit,
                ]);

            if ($response->successful()) {
                return $response->json() ?? [];
            }
        } catch (\Throwable $e) {
            Log::error('BinanceService getKline Exception: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Known Binance Futures specifications (precision, minQty, tick size, step size).
     */
    protected array $symbolSpecs = [
        'BTCUSDT' => ['symbol' => 'BTCUSDT', 'qty_prec' => 4, 'price_prec' => 2, 'tick_size' => 0.1, 'min_qty' => 0.0001, 'step_size' => 0.0001, 'min_notional' => 50.0],
        'ETHUSDT' => ['symbol' => 'ETHUSDT', 'qty_prec' => 3, 'price_prec' => 2, 'tick_size' => 0.01, 'min_qty' => 0.001, 'step_size' => 0.001, 'min_notional' => 20.0],
        'SOLUSDT' => ['symbol' => 'SOLUSDT', 'qty_prec' => 2, 'price_prec' => 4, 'tick_size' => 0.01, 'min_qty' => 0.01, 'step_size' => 0.01, 'min_notional' => 5.0],
        'BNBUSDT' => ['symbol' => 'BNBUSDT', 'qty_prec' => 2, 'price_prec' => 3, 'tick_size' => 0.01, 'min_qty' => 0.01, 'step_size' => 0.01, 'min_notional' => 5.0],
        'XRPUSDT' => ['symbol' => 'XRPUSDT', 'qty_prec' => 1, 'price_prec' => 4, 'tick_size' => 0.0001, 'min_qty' => 0.1, 'step_size' => 0.1, 'min_notional' => 5.0],
        'DOGEUSDT' => ['symbol' => 'DOGEUSDT', 'qty_prec' => 0, 'price_prec' => 6, 'tick_size' => 0.00001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
        'ADAUSDT' => ['symbol' => 'ADAUSDT', 'qty_prec' => 0, 'price_prec' => 5, 'tick_size' => 0.0001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
        'AVAXUSDT' => ['symbol' => 'AVAXUSDT', 'qty_prec' => 0, 'price_prec' => 4, 'tick_size' => 0.001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
        'LINKUSDT' => ['symbol' => 'LINKUSDT', 'qty_prec' => 2, 'price_prec' => 3, 'tick_size' => 0.001, 'min_qty' => 0.01, 'step_size' => 0.01, 'min_notional' => 5.0],
        'APTUSDT' => ['symbol' => 'APTUSDT', 'qty_prec' => 1, 'price_prec' => 5, 'tick_size' => 0.0001, 'min_qty' => 0.1, 'step_size' => 0.1, 'min_notional' => 5.0],
        'ARUSDT' => ['symbol' => 'ARUSDT', 'qty_prec' => 1, 'price_prec' => 3, 'tick_size' => 0.001, 'min_qty' => 0.1, 'step_size' => 0.1, 'min_notional' => 5.0],
        'ENAUSDT' => ['symbol' => 'ENAUSDT', 'qty_prec' => 0, 'price_prec' => 7, 'tick_size' => 0.0001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
        'XAUUSDT' => ['symbol' => 'XAUUSDT', 'qty_prec' => 3, 'price_prec' => 2, 'tick_size' => 0.01, 'min_qty' => 0.001, 'step_size' => 0.001, 'min_notional' => 5.0],
        'GOLDUSDT' => ['symbol' => 'XAUUSDT', 'qty_prec' => 3, 'price_prec' => 2, 'tick_size' => 0.01, 'min_qty' => 0.001, 'step_size' => 0.001, 'min_notional' => 5.0],
        'XAUUSD' => ['symbol' => 'XAUUSDT', 'qty_prec' => 3, 'price_prec' => 2, 'tick_size' => 0.01, 'min_qty' => 0.001, 'step_size' => 0.001, 'min_notional' => 5.0],
        'GOLD' => ['symbol' => 'XAUUSDT', 'qty_prec' => 3, 'price_prec' => 2, 'tick_size' => 0.01, 'min_qty' => 0.001, 'step_size' => 0.001, 'min_notional' => 5.0],
        'PAXGUSDT' => ['symbol' => 'PAXGUSDT', 'qty_prec' => 3, 'price_prec' => 4, 'tick_size' => 0.01, 'min_qty' => 0.001, 'step_size' => 0.001, 'min_notional' => 5.0],
        'XAUTUSDT' => ['symbol' => 'PAXGUSDT', 'qty_prec' => 3, 'price_prec' => 4, 'tick_size' => 0.01, 'min_qty' => 0.001, 'step_size' => 0.001, 'min_notional' => 5.0],
        'SUIUSDT' => ['symbol' => 'SUIUSDT', 'qty_prec' => 1, 'price_prec' => 6, 'tick_size' => 0.0001, 'min_qty' => 0.1, 'step_size' => 0.1, 'min_notional' => 5.0],
        'LTCUSDT' => ['symbol' => 'LTCUSDT', 'qty_prec' => 3, 'price_prec' => 2, 'tick_size' => 0.01, 'min_qty' => 0.001, 'step_size' => 0.001, 'min_notional' => 5.0],
        'BCHUSDT' => ['symbol' => 'BCHUSDT', 'qty_prec' => 3, 'price_prec' => 2, 'tick_size' => 0.01, 'min_qty' => 0.001, 'step_size' => 0.001, 'min_notional' => 5.0],
        'DOTUSDT' => ['symbol' => 'DOTUSDT', 'qty_prec' => 1, 'price_prec' => 6, 'tick_size' => 0.0001, 'min_qty' => 0.1, 'step_size' => 0.1, 'min_notional' => 5.0],
        'OPUSDT' => ['symbol' => 'OPUSDT', 'qty_prec' => 1, 'price_prec' => 7, 'tick_size' => 0.0001, 'min_qty' => 0.1, 'step_size' => 0.1, 'min_notional' => 5.0],
        'NEARUSDT' => ['symbol' => 'NEARUSDT', 'qty_prec' => 0, 'price_prec' => 4, 'tick_size' => 0.001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
        'FILUSDT' => ['symbol' => 'FILUSDT', 'qty_prec' => 1, 'price_prec' => 6, 'tick_size' => 0.0001, 'min_qty' => 0.1, 'step_size' => 0.1, 'min_notional' => 5.0],
        'PEPEUSDT' => ['symbol' => '1000PEPEUSDT', 'qty_prec' => 0, 'price_prec' => 7, 'tick_size' => 0.0000001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
        '1000PEPEUSDT' => ['symbol' => '1000PEPEUSDT', 'qty_prec' => 0, 'price_prec' => 7, 'tick_size' => 0.0000001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
        'SHIBUSDT' => ['symbol' => '1000SHIBUSDT', 'qty_prec' => 0, 'price_prec' => 6, 'tick_size' => 0.000001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
        '1000SHIBUSDT' => ['symbol' => '1000SHIBUSDT', 'qty_prec' => 0, 'price_prec' => 6, 'tick_size' => 0.000001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
        'WIFUSDT' => ['symbol' => 'WIFUSDT', 'qty_prec' => 1, 'price_prec' => 7, 'tick_size' => 0.0001, 'min_qty' => 0.1, 'step_size' => 0.1, 'min_notional' => 5.0],
        'TRXUSDT' => ['symbol' => 'TRXUSDT', 'qty_prec' => 0, 'price_prec' => 5, 'tick_size' => 0.00001, 'min_qty' => 1.0, 'step_size' => 1.0, 'min_notional' => 5.0],
    ];

    /**
     * Format an order payload to strictly match Binance Futures filter constraints.
     */
    public function formatOrderPayload(string $rawSymbol, string $side, string $type, float $quantity, ?float $limitPrice = null, ?float $estMarketPrice = null): array
    {
        $rawSymbol = strtoupper($rawSymbol);
        $spec = $this->symbolSpecs[$rawSymbol] ?? [
            'symbol' => $rawSymbol,
            'qty_prec' => 2,
            'price_prec' => 2,
            'tick_size' => 0.01,
            'min_qty' => 0.01,
            'step_size' => 0.01,
            'min_notional' => 5.0,
        ];

        $binanceSymbol = $spec['symbol'] ?? $rawSymbol;
        $qtyPrec = (int) ($spec['qty_prec'] ?? 2);
        $pricePrec = (int) ($spec['price_prec'] ?? 2);
        $tickSize = (float) ($spec['tick_size'] ?? 0.01);
        $minQty = (float) ($spec['min_qty'] ?? 0.001);
        $stepSize = (float) ($spec['step_size'] ?? 0.001);
        $minNotional = (float) ($spec['min_notional'] ?? 5.0);

        // Adjust quantity for 1000PEPEUSDT & 1000SHIBUSDT scaling if needed
        $calcQty = $quantity;
        if (($rawSymbol === 'PEPEUSDT' || $rawSymbol === '1000PEPEUSDT') && $binanceSymbol === '1000PEPEUSDT') {
            if ($rawSymbol === 'PEPEUSDT' && $calcQty >= 1000) {
                $calcQty = max(1.0, round($calcQty / 1000));
            }
        }
        if (($rawSymbol === 'SHIBUSDT' || $rawSymbol === '1000SHIBUSDT') && $binanceSymbol === '1000SHIBUSDT') {
            if ($rawSymbol === 'SHIBUSDT' && $calcQty >= 1000) {
                $calcQty = max(1.0, round($calcQty / 1000));
            }
        }

        // Ensure minimum notional value (min safe buffer on Binance)
        $refPrice = ($limitPrice && $limitPrice > 0) ? $limitPrice : (($estMarketPrice && $estMarketPrice > 0) ? $estMarketPrice : 0);
        if ($refPrice > 0 && $stepSize > 0) {
            $safeNotional = max(10.0, $minNotional * 1.1);
            $minNotionalQty = ceil(($safeNotional / $refPrice) / $stepSize) * $stepSize;
            if ($calcQty < $minNotionalQty) {
                $calcQty = $minNotionalQty;
            }
        }

        // Ensure minimum quantity
        if ($calcQty < $minQty) {
            $calcQty = $minQty;
        }

        // Step size adjustment
        if ($stepSize > 0) {
            $calcQty = round(round($calcQty / $stepSize) * $stepSize, $qtyPrec);
        }

        // Format quantity string
        $formattedQty = $qtyPrec > 0
            ? number_format($calcQty, $qtyPrec, '.', '')
            : (string) max(1, (int) round($calcQty));

        $orderType = strtoupper($type);
        $payload = [
            'symbol' => $binanceSymbol,
            'side' => strtoupper($side),
            'type' => $orderType,
            'quantity' => $formattedQty,
        ];

        if ($limitPrice && $limitPrice > 0) {
            // Strictly round limit price to tick_size
            $calcPrice = $limitPrice;
            if ($tickSize > 0) {
                $calcPrice = round(round($limitPrice / $tickSize) * $tickSize, $pricePrec);
            }
            $payload['price'] = number_format($calcPrice, $pricePrec, '.', '');
            $payload['timeInForce'] = 'GTC';
            $payload['type'] = 'LIMIT';
        }

        return $payload;
    }

    /**
     * Set Margin Type (ISOLATED or CROSSED) for a symbol on Binance.
     */
    public function setMarginType(string $rawSymbol, string $marginType = 'ISOLATED'): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return ['success' => false, 'message' => 'Binance API credentials missing.'];
        }

        $rawSymbol = strtoupper($rawSymbol);
        $spec = $this->symbolSpecs[$rawSymbol] ?? ['symbol' => $rawSymbol];
        $binanceSymbol = $spec['symbol'] ?? $rawSymbol;
        $type = (strtoupper($marginType) === 'CROSS' || strtoupper($marginType) === 'CROSSED') ? 'CROSSED' : 'ISOLATED';

        try {
            $params = [
                'symbol' => $binanceSymbol,
                'marginType' => $type,
                'timestamp' => $this->getServerTimestamp(),
                'recvWindow' => 10000,
            ];
            $queryString = http_build_query($params);
            $params['signature'] = $this->generateSignature($queryString);

            $res = Http::timeout(8)
                ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                ->asForm()
                ->post("{$this->baseUrl}/fapi/v1/marginType", $params);

            $json = $res->json();
            if ($res->successful() || ($json['code'] ?? 0) === -4046) {
                return ['success' => true, 'message' => 'Margin type set successfully.'];
            }

            return ['success' => false, 'message' => $json['msg'] ?? 'Failed to set margin type.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Set Leverage for a symbol on Binance.
     */
    public function setLeverage(string $rawSymbol, int $leverage = 10): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return ['success' => false, 'message' => 'Binance API credentials missing.'];
        }

        $rawSymbol = strtoupper($rawSymbol);
        $spec = $this->symbolSpecs[$rawSymbol] ?? ['symbol' => $rawSymbol];
        $binanceSymbol = $spec['symbol'] ?? $rawSymbol;

        try {
            $params = [
                'symbol' => $binanceSymbol,
                'leverage' => max(1, min(125, (int) $leverage)),
                'timestamp' => $this->getServerTimestamp(),
                'recvWindow' => 10000,
            ];
            $queryString = http_build_query($params);
            $params['signature'] = $this->generateSignature($queryString);

            $res = Http::timeout(8)
                ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                ->asForm()
                ->post("{$this->baseUrl}/fapi/v1/leverage", $params);

            return ['success' => $res->successful(), 'data' => $res->json()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sign the TradFi Perps Agreement on Binance.
     */
    public function signTradFiAgreement(): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return false;
        }
        try {
            $params = [
                'timestamp' => $this->getServerTimestamp(),
                'recvWindow' => 10000,
            ];
            $queryString = http_build_query($params);
            $params['signature'] = $this->generateSignature($queryString);

            $res = Http::timeout(8)
                ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                ->asForm()
                ->post("{$this->baseUrl}/fapi/v1/stock/contract", $params);

            return $res->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Place an Order on Binance Futures API (Requires API Key & Secret).
     */
    public function placeOrder(array $params): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [
                'success' => false,
                'message' => 'Binance API credentials not configured in Admin Settings.',
            ];
        }

        try {
            $timestamp = $this->getServerTimestamp();

            $defaultParams = [
                'timestamp' => $timestamp,
                'recvWindow' => 10000,
                'workingType' => 'CONTRACT_PRICE',
            ];

            if (isset($params['type']) && strtoupper($params['type']) === 'LIMIT' && !isset($params['timeInForce'])) {
                $defaultParams['timeInForce'] = 'GTC';
            }

            $bodyParams = array_filter(array_merge($defaultParams, $params), function ($val) {
                return $val !== null && $val !== '';
            });

            $queryString = http_build_query($bodyParams);
            $signature = $this->generateSignature($queryString);

            $bodyParams['signature'] = $signature;

            $response = Http::timeout(8)
                ->withHeaders([
                    'X-MBX-APIKEY' => $this->apiKey,
                ])
                ->asForm()
                ->post("{$this->baseUrl}/fapi/v1/order", $bodyParams);

            $json = $response->json();

            if ($response->successful() && isset($json['orderId'])) {
                Log::info("Binance Order placed successfully for {$bodyParams['symbol']}: OrderId " . ($json['orderId'] ?? ''));
                return [
                    'success' => true,
                    'data' => $json,
                    'message' => 'Order placed on Binance successfully.',
                ];
            }

            $errorMsg = $json['msg'] ?? 'Failed to place order on Binance.';
            Log::warning("Binance placeOrder rejected for {$bodyParams['symbol']}: {$errorMsg}", ['payload' => $bodyParams, 'response' => $json]);

            // If timestamp skew error (code -1021), force refresh server timestamp and retry
            if (($json['code'] ?? 0) === -1021) {
                Log::info("Binance timestamp error -1021, refreshing server time and retrying for {$bodyParams['symbol']}...");
                $bodyParams['timestamp'] = $this->getServerTimestamp(true);
                unset($bodyParams['signature']);
                $newQuery = http_build_query($bodyParams);
                $bodyParams['signature'] = $this->generateSignature($newQuery);

                $retryRes = Http::timeout(8)
                    ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                    ->asForm()
                    ->post("{$this->baseUrl}/fapi/v1/order", $bodyParams);

                $retryJson = $retryRes->json();
                if ($retryRes->successful() && isset($retryJson['orderId'])) {
                    return [
                        'success' => true,
                        'data' => $retryJson,
                        'message' => 'Order placed on Binance successfully.',
                    ];
                }
            }

            // If TradFi agreement is requested (code -4411), auto-sign and retry original symbol
            if (str_contains($errorMsg, 'TradFi') || ($json['code'] ?? 0) === -4411) {
                Log::info("Signing Binance TradFi agreement and retrying order for {$bodyParams['symbol']}...");
                $this->signTradFiAgreement();

                $bodyParams['timestamp'] = $this->getServerTimestamp(true);
                unset($bodyParams['signature']);
                $newQuery = http_build_query($bodyParams);
                $bodyParams['signature'] = $this->generateSignature($newQuery);

                $retryRes = Http::timeout(8)
                    ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                    ->asForm()
                    ->post("{$this->baseUrl}/fapi/v1/order", $bodyParams);

                $retryJson = $retryRes->json();
                if ($retryRes->successful() && isset($retryJson['orderId'])) {
                    Log::info("Binance direct Order placed after agreement: OrderId " . ($retryJson['orderId'] ?? ''));
                    return [
                        'success' => true,
                        'data' => $retryJson,
                        'message' => 'Order placed on Binance successfully.',
                    ];
                }
            }

            // If minimum notional rejected (code -4164), increase quantity to satisfy 10 USDT notional and retry
            if (($json['code'] ?? 0) === -4164 && !empty($bodyParams['quantity'])) {
                $origQty = (float) $bodyParams['quantity'];
                $spec = $this->symbolSpecs[$bodyParams['symbol']] ?? [];
                $qtyPrec = (int) ($spec['qty_prec'] ?? 2);
                $stepSize = (float) ($spec['step_size'] ?? 0.1);
                $newQty = max($origQty * 2.5, $origQty + ($stepSize * 20));

                $bodyParams['quantity'] = $qtyPrec > 0
                    ? number_format($newQty, $qtyPrec, '.', '')
                    : (string) max(1, (int) round($newQty));

                $bodyParams['timestamp'] = $this->getServerTimestamp();
                unset($bodyParams['signature']);
                $newQuery = http_build_query($bodyParams);
                $bodyParams['signature'] = $this->generateSignature($newQuery);

                $retryRes = Http::timeout(8)
                    ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                    ->asForm()
                    ->post("{$this->baseUrl}/fapi/v1/order", $bodyParams);

                $retryJson = $retryRes->json();
                if ($retryRes->successful() && isset($retryJson['orderId'])) {
                    Log::info("Binance order placed after notional retry for {$bodyParams['symbol']}: OrderId " . ($retryJson['orderId'] ?? ''));
                    return [
                        'success' => true,
                        'data' => $retryJson,
                        'message' => 'Order placed on Binance successfully.',
                    ];
                }
            }

            return [
                'success' => false,
                'message' => $errorMsg,
                'raw' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('BinanceService placeOrder Exception: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel an Order on Binance Futures API.
     */
    public function cancelOrder(string $symbol, string $orderId): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return ['success' => false, 'message' => 'Credentials missing.'];
        }

        try {
            $timestamp = $this->getServerTimestamp();
            $params = [
                'symbol' => strtoupper($symbol),
                'orderId' => $orderId,
                'timestamp' => $timestamp,
                'recvWindow' => 10000,
            ];

            $queryString = http_build_query($params);
            $signature = $this->generateSignature($queryString);
            $params['signature'] = $signature;

            $response = Http::timeout(8)
                ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                ->delete("{$this->baseUrl}/fapi/v1/order?" . http_build_query($params));

            $json = $response->json();
            if ($response->successful()) {
                return ['success' => true, 'data' => $json];
            }
            return ['success' => false, 'message' => $json['msg'] ?? 'Failed to cancel order on Binance.'];
        } catch (\Throwable $e) {
            Log::error('BinanceService cancelOrder Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cancel All Open Orders for a Symbol on Binance Futures API.
     */
    public function cancelAllOpenOrders(string $rawSymbol): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return ['success' => false, 'message' => 'Credentials missing.'];
        }

        $rawSymbol = strtoupper($rawSymbol);
        $spec = $this->symbolSpecs[$rawSymbol] ?? ['symbol' => $rawSymbol];
        $binanceSymbol = $spec['symbol'] ?? $rawSymbol;

        try {
            $timestamp = $this->getServerTimestamp();
            $params = [
                'symbol' => $binanceSymbol,
                'timestamp' => $timestamp,
                'recvWindow' => 10000,
            ];
            $queryString = http_build_query($params);
            $params['signature'] = $this->generateSignature($queryString);

            $response = Http::timeout(8)
                ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                ->delete("{$this->baseUrl}/fapi/v1/allOpenOrders?" . http_build_query($params));

            $json = $response->json();
            return ['success' => $response->successful(), 'data' => $response->json()];
        } catch (\Throwable $e) {
            Log::error('BinanceService cancelAllOpenOrders Exception: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get Position Risk for a Symbol (or all open positions) on Binance.
     */
    public function getPositionRisk(?string $rawSymbol = null): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return [];
        }

        try {
            $timestamp = $this->getServerTimestamp();
            $params = [
                'timestamp' => $timestamp,
                'recvWindow' => 10000,
            ];

            if ($rawSymbol) {
                $rawSymbol = strtoupper($rawSymbol);
                $spec = $this->symbolSpecs[$rawSymbol] ?? ['symbol' => $rawSymbol];
                $params['symbol'] = $spec['symbol'] ?? $rawSymbol;
            }

            $queryString = http_build_query($params);
            $params['signature'] = $this->generateSignature($queryString);

            $response = Http::timeout(8)
                ->withHeaders(['X-MBX-APIKEY' => $this->apiKey])
                ->get("{$this->baseUrl}/fapi/v2/positionRisk?" . http_build_query($params));

            $json = $response->json();
            return is_array($json) ? $json : [];
        } catch (\Throwable $e) {
            Log::error('BinanceService getPositionRisk Exception: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Close a Position on Binance Futures API using an immediate MARKET order.
     */
    public function closePosition(string $rawSymbol, string $closingSide = 'SELL', float $quantity = 0): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return ['success' => false, 'message' => 'Binance API credentials missing.'];
        }

        $rawSymbol = strtoupper($rawSymbol);
        $spec = $this->symbolSpecs[$rawSymbol] ?? [
            'symbol' => $rawSymbol,
            'qty_prec' => 2,
            'min_qty' => 0.001,
        ];

        $binanceSymbol = $spec['symbol'] ?? $rawSymbol;
        $qtyPrec = (int) ($spec['qty_prec'] ?? 2);

        // 1. Cancel any open pending limit/stop orders for this symbol first
        $this->cancelAllOpenOrders($binanceSymbol);

        // 2. Query actual open position size on Binance
        $actualAmt = 0.0;
        try {
            $risk = $this->getPositionRisk($binanceSymbol);
            foreach ($risk as $r) {
                if (($r['symbol'] ?? '') === $binanceSymbol) {
                    $actualAmt = (float) ($r['positionAmt'] ?? 0);
                    break;
                }
            }
        } catch (\Throwable $e) {
            $actualAmt = 0.0;
        }

        // If Binance has NO open position for this symbol, nothing to close!
        if (abs($actualAmt) < 0.000001) {
            return [
                'success' => true,
                'message' => "No open position for {$binanceSymbol} on Binance.",
            ];
        }

        // If Binance has an active open position for this symbol, close the EXACT Binance amount!
        $closeSide = $actualAmt > 0 ? 'SELL' : 'BUY';
        $calcQty = abs($actualAmt);

        $formattedQty = $qtyPrec > 0
            ? number_format($calcQty, $qtyPrec, '.', '')
            : (string) max(1, (int) round($calcQty));

        // 3. Place MARKET closing order
        $closePayload = [
            'symbol' => $binanceSymbol,
            'side' => $closeSide,
            'type' => 'MARKET',
            'quantity' => $formattedQty,
            'reduceOnly' => 'true',
        ];

        $res = $this->placeOrder($closePayload);
        if ($res['success'] ?? false) {
            return $res;
        }

        // If reduceOnly failed, retry without reduceOnly flag
        unset($closePayload['reduceOnly']);
        return $this->placeOrder($closePayload);
    }

    /**
     * Generate HMAC SHA256 Signature for Binance API Requests.
     */
    protected function generateSignature(string $queryString): string
    {
        return hash_hmac('sha256', $queryString, $this->apiSecret);
    }
}
