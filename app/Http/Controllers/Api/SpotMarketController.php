<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SpotTradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpotMarketController extends Controller
{
    protected function marketHttp()
    {
        $verifySsl = filter_var(env('MARKET_API_SSL_VERIFY', false), FILTER_VALIDATE_BOOLEAN);
        return Http::timeout(6)->withOptions(['verify' => $verifySsl]);
    }

    public function pairs()
    {
        return response()->json([
            'success' => true,
            'data' => SpotTradingService::SUPPORTED_PAIRS,
        ]);
    }

    public function tickers(Request $request)
    {
        try {
            $symbols = array_keys(SpotTradingService::SUPPORTED_PAIRS);
            $requestedSymbol = $request->query('symbol');
            if ($requestedSymbol) {
                $requestedSymbol = strtoupper(str_replace(['/', '-', '_', ' '], '', $requestedSymbol));
                if (!in_array($requestedSymbol, $symbols, true)) {
                    $symbols[] = $requestedSymbol;
                }
            }
            $symbolsParam = json_encode(array_values($symbols));

            $response = $this->marketHttp()->get('https://api.binance.com/api/v3/ticker/24hr', [
                'symbols' => $symbolsParam,
            ]);

            if ($response->successful()) {
                $raw = $response->json();
                $data = [];
                $service = app(SpotTradingService::class);
                foreach ($raw as $item) {
                    $sym = $item['symbol'] ?? '';
                    $config = $service->getPairConfig($sym);
                    $lastPrice = (float) ($item['lastPrice'] ?? 0);
                    $prevClose = (float) ($item['prevClosePrice'] ?? $lastPrice);
                    $change = (float) ($item['priceChangePercent'] ?? 0);
                    $high = (float) ($item['highPrice'] ?? $lastPrice);
                    $low = (float) ($item['lowPrice'] ?? $lastPrice);
                    $volume = (float) ($item['volume'] ?? 0);
                    $quoteVolume = (float) ($item['quoteVolume'] ?? 0);

                    $data[$sym] = [
                        'symbol' => $sym,
                        'name' => $config['name'] ?? $sym,
                        'base' => $config['base'] ?? $sym,
                        'quote' => $config['quote'] ?? 'USDT',
                        'price' => $lastPrice,
                        'change' => $change,
                        'high' => $high,
                        'low' => $low,
                        'volume' => $volume,
                        'quote_volume' => $quoteVolume,
                    ];
                }

                if (!empty($data)) {
                    return response()->json([
                        'success' => true,
                        'source' => 'binance',
                        'data' => $data,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SpotMarketController tickers error: ' . $e->getMessage());
        }

        // Fallback default mock data
        return response()->json([
            'success' => true,
            'source' => 'fallback',
            'data' => $this->fallbackTickers($request->query('symbol')),
        ]);
    }

    public function depth(string $symbol)
    {
        $sym = strtoupper(str_replace(['/', '-', '_', ' '], '', $symbol));
        if (!isset(SpotTradingService::SUPPORTED_PAIRS[$sym])) {
            if (!str_ends_with($sym, 'USDT') || strlen($sym) <= 4) {
                $sym = 'BTCUSDT';
            }
        }

        try {
            $response = $this->marketHttp()->get('https://api.binance.com/api/v3/depth', [
                'symbol' => $sym,
                'limit' => 20,
            ]);

            if ($response->successful()) {
                $raw = $response->json();
                return response()->json([
                    'success' => true,
                    'symbol' => $sym,
                    'bids' => array_slice($raw['bids'] ?? [], 0, 15),
                    'asks' => array_slice($raw['asks'] ?? [], 0, 15),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SpotMarketController depth error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'symbol' => $sym,
            'bids' => $this->fallbackDepth($sym)['bids'],
            'asks' => $this->fallbackDepth($sym)['asks'],
        ]);
    }

    public function trades(string $symbol)
    {
        $sym = strtoupper(str_replace(['/', '-', '_', ' '], '', $symbol));
        if (!isset(SpotTradingService::SUPPORTED_PAIRS[$sym])) {
            if (!str_ends_with($sym, 'USDT') || strlen($sym) <= 4) {
                $sym = 'BTCUSDT';
            }
        }

        try {
            $response = $this->marketHttp()->get('https://api.binance.com/api/v3/trades', [
                'symbol' => $sym,
                'limit' => 25,
            ]);

            if ($response->successful()) {
                $raw = $response->json();
                $formatted = array_map(function ($t) {
                    return [
                        'id' => (string) ($t['id'] ?? ''),
                        'price' => (float) ($t['price'] ?? 0),
                        'amount' => (float) ($t['qty'] ?? 0),
                        'side' => ($t['isBuyerMaker'] ?? false) ? 'sell' : 'buy',
                        'time' => date('H:i:s', (int) (($t['time'] ?? now()->timestamp * 1000) / 1000)),
                    ];
                }, is_array($raw) ? $raw : []);

                return response()->json([
                    'success' => true,
                    'symbol' => $sym,
                    'data' => $formatted,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('SpotMarketController trades error: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'symbol' => $sym,
            'data' => $this->fallbackTrades($sym),
        ]);
    }

    protected function fallbackTickers(?string $requestedSymbol = null): array
    {
        $basePrices = [
            'BTCUSDT' => 64500.0,
            'ETHUSDT' => 3450.0,
            'SOLUSDT' => 145.0,
            'BNBUSDT' => 580.0,
            'XRPUSDT' => 0.58,
            'DOGEUSDT' => 0.125,
            'ADAUSDT' => 0.45,
            'AVAXUSDT' => 28.5,
        ];

        $pairs = SpotTradingService::SUPPORTED_PAIRS;
        if ($requestedSymbol) {
            $req = strtoupper(str_replace(['/', '-', '_', ' '], '', $requestedSymbol));
            if (!isset($pairs[$req]) && str_ends_with($req, 'USDT')) {
                $service = app(SpotTradingService::class);
                $pairs[$req] = $service->getPairConfig($req);
            }
        }

        $res = [];
        foreach ($pairs as $sym => $cfg) {
            $price = $basePrices[$sym] ?? 10.0;
            $res[$sym] = [
                'symbol' => $sym,
                'name' => $cfg['name'] ?? $sym,
                'base' => $cfg['base'] ?? $sym,
                'quote' => $cfg['quote'] ?? 'USDT',
                'price' => $price,
                'change' => 1.25,
                'high' => round($price * 1.03, 2),
                'low' => round($price * 0.97, 2),
                'volume' => 1250000.0,
                'quote_volume' => round($price * 1250000.0, 2),
            ];
        }
        return $res;
    }

    protected function fallbackDepth(string $symbol): array
    {
        $price = 64500.0;
        if ($symbol === 'ETHUSDT') $price = 3450.0;
        elseif ($symbol === 'SOLUSDT') $price = 145.0;

        $bids = [];
        $asks = [];
        for ($i = 1; $i <= 10; $i++) {
            $bids[] = [(string) round($price * (1 - ($i * 0.0005)), 2), (string) round(rand(5, 50) / 10, 4)];
            $asks[] = [(string) round($price * (1 + ($i * 0.0005)), 2), (string) round(rand(5, 50) / 10, 4)];
        }
        return ['bids' => $bids, 'asks' => $asks];
    }

    protected function fallbackTrades(string $symbol): array
    {
        $price = 64500.0;
        if ($symbol === 'ETHUSDT') $price = 3450.0;
        elseif ($symbol === 'SOLUSDT') $price = 145.0;

        $trades = [];
        for ($i = 0; $i < 10; $i++) {
            $trades[] = [
                'id' => (string) (1000000 + $i),
                'price' => round($price + (rand(-10, 10) * 0.5), 2),
                'amount' => round(rand(1, 20) / 10, 4),
                'side' => rand(0, 1) ? 'buy' : 'sell',
                'time' => date('H:i:s', time() - ($i * 3)),
            ];
        }
        return $trades;
    }
}

