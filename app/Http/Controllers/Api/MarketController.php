<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class MarketController extends Controller
{
    public function futuresTickers()
    {
        $settings = null;
        try {
            $settings = \Illuminate\Support\Facades\DB::connection('mongodb')
                ->table('admin_settings')
                ->where('key', 'platform')
                ->first();
        } catch (\Throwable $e) {}

        $provider = strtolower($settings->active_exchange_provider ?? 'binance');

        if ($provider === 'bybit') {
            $bybitData = $this->fetchBybitTickers();
            if (! empty($bybitData)) {
                return response()->json([
                    'success' => true,
                    'source' => 'bybit',
                    'message' => 'Futures tickers fetched successfully from Bybit.',
                    'data' => $bybitData,
                ]);
            }
        }

        $binanceData = $this->fetchBinanceTickers();

        if (! empty($binanceData)) {
            return response()->json([
                'success' => true,
                'source' => 'binance',
                'message' => 'Futures tickers fetched successfully from Binance.',
                'data' => $binanceData,
            ]);
        }

        if ($provider !== 'bybit') {
            $bybitData = $this->fetchBybitTickers();
            if (! empty($bybitData)) {
                return response()->json([
                    'success' => true,
                    'source' => 'bybit',
                    'message' => 'Futures tickers fetched from Bybit backup.',
                    'data' => $bybitData,
                ]);
            }
        }

        $coinGeckoData = $this->fetchCoinGeckoTickers();

        if (! empty($coinGeckoData)) {
            return response()->json([
                'success' => true,
                'source' => 'coingecko',
                'message' => 'Live spot market data fetched from backup source.',
                'data' => $coinGeckoData,
            ]);
        }

        return response()->json([
            'success' => true,
            'source' => 'fallback',
            'message' => 'Live market APIs unavailable. Showing fallback data.',
            'data' => $this->fallbackTickers(),
        ]);
    }

    public function futuresDepth(string $symbol)
    {
        $symbol = $this->normalizeSymbol($symbol);

        try {
            $response = $this->marketHttp()->get('https://fapi.binance.com/fapi/v1/depth', [
                'symbol' => $symbol,
                'limit' => 20,
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => true,
                    'source' => 'fallback',
                    'message' => 'Order book unavailable. Showing fallback data.',
                    'data' => $this->fallbackDepth($symbol),
                ]);
            }

            $data = $response->json();

            return response()->json([
                'success' => true,
                'source' => 'binance',
                'message' => 'Order book fetched successfully.',
                'data' => [
                    'symbol' => $symbol,
                    'last_update_id' => $data['lastUpdateId'] ?? null,
                    'bids' => collect($data['bids'] ?? [])->take(12)->map(function ($row) {
                        return [
                            'price' => (float) ($row[0] ?? 0),
                            'quantity' => (float) ($row[1] ?? 0),
                        ];
                    })->values(),
                    'asks' => collect($data['asks'] ?? [])->take(12)->map(function ($row) {
                        return [
                            'price' => (float) ($row[0] ?? 0),
                            'quantity' => (float) ($row[1] ?? 0),
                        ];
                    })->values(),
                ],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => true,
                'source' => 'fallback',
                'message' => 'Unable to fetch order book. Showing fallback data.',
                'data' => $this->fallbackDepth($symbol),
            ]);
        }
    }

    public function futuresTrades(string $symbol)
    {
        $symbol = $this->normalizeSymbol($symbol);

        try {
            $response = $this->marketHttp()->get('https://fapi.binance.com/fapi/v1/trades', [
                'symbol' => $symbol,
                'limit' => 20,
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'success' => true,
                    'source' => 'fallback',
                    'message' => 'Recent trades unavailable. Showing fallback data.',
                    'data' => $this->fallbackTrades($symbol),
                ]);
            }

            return response()->json([
                'success' => true,
                'source' => 'binance',
                'message' => 'Recent trades fetched successfully.',
                'data' => [
                    'symbol' => $symbol,
                    'trades' => collect($response->json())->take(20)->map(function ($item) {
                        return [
                            'id' => $item['id'] ?? null,
                            'price' => (float) ($item['price'] ?? 0),
                            'quantity' => (float) ($item['qty'] ?? 0),
                            'time' => $item['time'] ?? null,
                            'is_buyer_maker' => (bool) ($item['isBuyerMaker'] ?? false),
                        ];
                    })->values(),
                ],
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => true,
                'source' => 'fallback',
                'message' => 'Unable to fetch recent trades. Showing fallback data.',
                'data' => $this->fallbackTrades($symbol),
            ]);
        }
    }

    public function futuresStats(string $symbol)
    {
        if (str_contains($symbol, '-')) {
            try {
                $response = \Illuminate\Support\Facades\Http::get('https://eapi.binance.com/eapi/v1/mark', [
                    'symbol' => strtoupper($symbol),
                ]);
                if ($response->successful()) {
                    $data = $response->json();
                    $item = is_array($data) && isset($data[0]) ? $data[0] : $data;
                    if (is_array($item) && isset($item['symbol'])) {
                        return response()->json([
                            'success' => true,
                            'source' => 'binance_options',
                            'message' => 'Options stats fetched successfully.',
                            'data' => [
                                'symbol' => $item['symbol'],
                                'mark_price' => (float) ($item['markPrice'] ?? 0),
                                'index_price' => (float) ($item['indexPrice'] ?? 0),
                                'funding_rate' => 0,
                                'next_funding_time' => null,
                                'open_interest' => 0,
                            ],
                        ]);
                    }
                }
            } catch (\Throwable $exception) {
                // fallback
            }
        }

        $symbol = $this->normalizeSymbol($symbol);

        $premium = null;
        $openInterest = null;

        try {
            $premiumResponse = $this->marketHttp()->get('https://fapi.binance.com/fapi/v1/premiumIndex', [
                'symbol' => $symbol,
            ]);

            if ($premiumResponse->successful()) {
                $premium = $premiumResponse->json();
            }
        } catch (\Throwable $exception) {
            $premium = null;
        }

        try {
            $openInterestResponse = $this->marketHttp()->get('https://fapi.binance.com/fapi/v1/openInterest', [
                'symbol' => $symbol,
            ]);

            if ($openInterestResponse->successful()) {
                $openInterest = $openInterestResponse->json();
            }
        } catch (\Throwable $exception) {
            $openInterest = null;
        }

        if (! $premium && ! $openInterest) {
            return response()->json([
                'success' => true,
                'source' => 'fallback',
                'message' => 'Futures stats unavailable.',
                'data' => [
                    'symbol' => $symbol,
                    'mark_price' => 0,
                    'index_price' => 0,
                    'funding_rate' => 0,
                    'next_funding_time' => null,
                    'open_interest' => 0,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'source' => 'binance',
            'message' => 'Futures stats fetched successfully.',
            'data' => [
                'symbol' => $symbol,
                'mark_price' => (float) data_get($premium, 'markPrice', 0),
                'index_price' => (float) data_get($premium, 'indexPrice', 0),
                'funding_rate' => (float) data_get($premium, 'lastFundingRate', 0),
                'next_funding_time' => data_get($premium, 'nextFundingTime'),
                'open_interest' => (float) data_get($openInterest, 'openInterest', 0),
            ],
        ]);
    }

    private function normalizeSymbol(string $symbol): string
    {
        $symbol = strtoupper($symbol);
        $symbol = preg_replace('/[^A-Z0-9]/', '', $symbol);

        return $symbol ?: 'BTCUSDT';
    }

    private function fallbackDepth(string $symbol): array
    {
        return [
            'symbol' => $symbol,
            'last_update_id' => null,
            'bids' => [],
            'asks' => [],
        ];
    }

    private function fallbackTrades(string $symbol): array
    {
        return [
            'symbol' => $symbol,
            'trades' => [],
        ];
    }

    private function fetchBinanceTickers(): array
    {
        try {
            $response = $this->marketHttp()->get('https://fapi.binance.com/fapi/v1/ticker/24hr');

            if (! $response->successful()) {
                return [];
            }

            $tickers = collect($response->json())
                ->filter(function ($item) {
                    $symbol = $item['symbol'] ?? '';
                    $allowedSymbols = config('kryvex_markets.futures_symbols', []);

                    return in_array($symbol, $allowedSymbols, true)
                        && (float) ($item['lastPrice'] ?? 0) > 0
                        && (float) ($item['quoteVolume'] ?? 0) > 0;
                })
                ->map(function ($item) {
                    $lastPrice = (float) ($item['lastPrice'] ?? 0);
                    $spreadPrices = \App\Helpers\SpreadHelper::calculatePrices($lastPrice);
                    return [
                        'symbol' => $item['symbol'] ?? '',
                        'last_price' => $lastPrice,
                        'buy_price' => $spreadPrices['buy_price'],
                        'sell_price' => $spreadPrices['sell_price'],
                        'spread_amount' => $spreadPrices['spread_amount'],
                        'price_change_percent' => (float) ($item['priceChangePercent'] ?? 0),
                        'high_price' => (float) ($item['highPrice'] ?? 0),
                        'low_price' => (float) ($item['lowPrice'] ?? 0),
                        'volume' => (float) ($item['volume'] ?? 0),
                        'quote_volume' => (float) ($item['quoteVolume'] ?? 0),
                        'leverage' => $this->maxLeverage($item['symbol'] ?? ''),
                    ];
                });

            // Ensure XAUUSDT is always available with gold volume
            if (! $tickers->contains('symbol', 'XAUUSDT')) {
                $paxg = $tickers->firstWhere('symbol', 'PAXGUSDT');
                if ($paxg) {
                    $xau = $paxg;
                    $xau['symbol'] = 'XAUUSDT';
                    $xau['quote_volume'] = 1200000000.0;
                    $xau['leverage'] = '100x';
                    $tickers->push($xau);
                }
            }

            return $tickers->sortByDesc('quote_volume')
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            return [];
        }
    }

    private function fetchBybitTickers(): array
    {
        try {
            $bybitService = app(\App\Services\BybitService::class);
            $list = $bybitService->getTickers('linear');

            if (empty($list)) {
                return [];
            }

            return collect($list)
                ->filter(function ($item) {
                    $symbol = $item['symbol'] ?? '';
                    $allowedSymbols = config('kryvex_markets.futures_symbols', []);

                    return in_array($symbol, $allowedSymbols, true)
                        && (float) ($item['lastPrice'] ?? 0) > 0
                        && (float) ($item['turnover24h'] ?? 0) > 0;
                })
                ->map(function ($item) {
                    $changeDecimal = (float) ($item['price24hPcnt'] ?? 0);

                    return [
                        'symbol' => $item['symbol'] ?? '',
                        'contract_type' => 'USDT Perpetual',
                        'last_price' => (float) ($item['lastPrice'] ?? 0),
                        'price_change_percent' => $changeDecimal * 100,
                        'high_price' => (float) ($item['highPrice24h'] ?? 0),
                        'low_price' => (float) ($item['lowPrice24h'] ?? 0),
                        'volume' => (float) ($item['volume24h'] ?? 0),
                        'quote_volume' => (float) ($item['turnover24h'] ?? 0),
                        'leverage' => $this->maxLeverage($item['symbol'] ?? ''),
                    ];
                })
                ->sortByDesc('quote_volume')
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            return [];
        }
    }

    private function fetchCoinGeckoTickers(): array
    {
        try {
            $coinMap = [
                'bitcoin' => 'BTCUSDT',
                'ethereum' => 'ETHUSDT',
                'solana' => 'SOLUSDT',
                'binancecoin' => 'BNBUSDT',
                'ripple' => 'XRPUSDT',
                'dogecoin' => 'DOGEUSDT',
                'cardano' => 'ADAUSDT',
                'avalanche-2' => 'AVAXUSDT',
            ];

            $response = $this->marketHttp()->get('https://api.coingecko.com/api/v3/coins/markets', [
                'vs_currency' => 'usd',
                'ids' => implode(',', array_keys($coinMap)),
                'order' => 'market_cap_desc',
                'per_page' => count($coinMap),
                'page' => 1,
                'sparkline' => 'false',
                'price_change_percentage' => '24h',
            ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json())
                ->map(function ($item) use ($coinMap) {
                    $symbol = $coinMap[$item['id'] ?? ''] ?? '';

                    return [
                        'symbol' => $symbol,
                        'contract_type' => 'USDT Perpetual',
                        'last_price' => (float) ($item['current_price'] ?? 0),
                        'price_change_percent' => (float) ($item['price_change_percentage_24h'] ?? 0),
                        'high_price' => (float) ($item['high_24h'] ?? 0),
                        'low_price' => (float) ($item['low_24h'] ?? 0),
                        'volume' => (float) ($item['total_volume'] ?? 0),
                        'quote_volume' => (float) ($item['total_volume'] ?? 0),
                        'leverage' => $this->maxLeverage($symbol),
                    ];
                })
                ->filter(fn($item) => ! empty($item['symbol']))
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            return [];
        }
    }

    private function marketHttp()
    {
        $verifySsl = filter_var(env('MARKET_API_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN);

        return Http::timeout(12)->withOptions([
            'verify' => $verifySsl,
        ]);
    }

    private function maxLeverage(string $symbol): string
    {
        return '1000x';
    }

    private function fallbackTickers(): array
    {
        return [
            [
                'symbol' => 'BTCUSDT',
                'contract_type' => 'USDT Perpetual',
                'last_price' => 0,
                'price_change_percent' => 0,
                'high_price' => 0,
                'low_price' => 0,
                'volume' => 0,
                'quote_volume' => 0,
                'leverage' => '100x',
            ],
            [
                'symbol' => 'ETHUSDT',
                'contract_type' => 'USDT Perpetual',
                'last_price' => 0,
                'price_change_percent' => 0,
                'high_price' => 0,
                'low_price' => 0,
                'volume' => 0,
                'quote_volume' => 0,
                'leverage' => '100x',
            ],
            [
                'symbol' => 'SOLUSDT',
                'contract_type' => 'USDT Perpetual',
                'last_price' => 0,
                'price_change_percent' => 0,
                'high_price' => 0,
                'low_price' => 0,
                'volume' => 0,
                'quote_volume' => 0,
                'leverage' => '75x',
            ],
        ];
    }

    public function fxempireNews(\Illuminate\Http\Request $request)
    {
        try {
            $limit = (int) $request->input('limit', 20);
            $limit = max(4, min(30, $limit));

            // 1. Fetch real FXEmpire direct articles for institutional Research & Forecasts
            $researchRaw = $this->fetchFxEmpireDirectArticles('/forecasts', 'res');
            if (empty($researchRaw)) {
                $researchRaw = $this->fetchFxEmpireRss('site:fxempire.com prediction OR forecast OR technical analysis OR target', $limit * 2, 'res');
            }
            usort($researchRaw, function($a, $b) {
                return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
            });

            // 2. Fetch multi-source live crypto & financial breaking news with real CDN images
            $ctNews = $this->fetchRssNewsSource('https://cointelegraph.com/rss', 'CoinTelegraph');
            $cdNews = $this->fetchRssNewsSource('https://www.coindesk.com/arc/outboundfeeds/rss/', 'CoinDesk');
            $dcNews = $this->fetchRssNewsSource('https://decrypt.co/feed', 'Decrypt');
            $fxNews = $this->fetchFxEmpireDirectArticles('/news', 'news');

            // Merge multi-source news items sorted chronologically
            $combinedNewsRaw = array_merge($ctNews, $cdNews, $dcNews, $fxNews);
            usort($combinedNewsRaw, function($a, $b) {
                return ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0);
            });

            $seenTitles = [];
            $seenLinks = [];

            // Deduplicate research articles
            $research = [];
            $resCount = 1;
            foreach ($researchRaw as $item) {
                $normTitle = mb_strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $item['title'])));
                $normLink = mb_strtolower(trim($item['link']));

                if (empty($normTitle) || isset($seenTitles[$normTitle]) || isset($seenLinks[$normLink])) {
                    continue;
                }

                $seenTitles[$normTitle] = true;
                $seenLinks[$normLink] = true;

                $item['id'] = 'res-' . $resCount;
                $research[] = $item;
                $resCount++;

                if (count($research) >= $limit) break;
            }

            // Deduplicate news articles and ensure ZERO overlap with research
            $news = [];
            $newsCount = 1;
            foreach ($combinedNewsRaw as $item) {
                $normTitle = mb_strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $item['title'])));
                $normLink = mb_strtolower(trim($item['link']));

                if (empty($normTitle) || isset($seenTitles[$normTitle]) || isset($seenLinks[$normLink])) {
                    continue;
                }

                $seenTitles[$normTitle] = true;
                $seenLinks[$normLink] = true;

                $item['id'] = 'news-' . $newsCount;
                $news[] = $item;
                $newsCount++;

                if (count($news) >= $limit) break;
            }

            return response()->json([
                'success' => true,
                'research' => $research,
                'news' => $news,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function fetchRssNewsSource($url, $sourceName)
    {
        try {
            $response = Http::timeout(5)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])->get($url);

            $articles = [];
            if (!$response->successful()) return $articles;

            $xml = @simplexml_load_string($response->body());
            if (!$xml || !isset($xml->channel->item)) return $articles;

            $count = 1;
            foreach ($xml->channel->item as $item) {
                $title = trim((string)$item->title);
                $link = trim((string)$item->link);
                if (empty($title) || empty($link)) continue;

                $pubDate = (string)$item->pubDate;
                $ts = $pubDate ? strtotime($pubDate) : time();
                $formattedDate = date('M j, Y', $ts);

                $desc = (string)$item->description;
                $contentEncoded = '';
                if (isset($item->children('content', true)->encoded)) {
                    $contentEncoded = (string)$item->children('content', true)->encoded;
                }

                // Extract REAL exact cover image from RSS feed (media:content, media:thumbnail, enclosure, or HTML <img>)
                $imageUrl = null;
                $media = $item->children('media', true);
                if (isset($media->content)) {
                    $attrs = $media->content->attributes();
                    if (isset($attrs['url'])) $imageUrl = (string)$attrs['url'];
                }
                if (!$imageUrl && isset($media->thumbnail)) {
                    $attrs = $media->thumbnail->attributes();
                    if (isset($attrs['url'])) $imageUrl = (string)$attrs['url'];
                }

                if (!$imageUrl && isset($item->enclosure)) {
                    $attrs = $item->enclosure->attributes();
                    if (isset($attrs['url'])) $imageUrl = (string)$attrs['url'];
                }

                if (!$imageUrl && !empty($desc)) {
                    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $desc, $m)) {
                        $imageUrl = $m[1];
                    }
                }
                if (!$imageUrl && !empty($contentEncoded)) {
                    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $contentEncoded, $m)) {
                        $imageUrl = $m[1];
                    }
                }

                if (!$imageUrl || filter_var($imageUrl, FILTER_VALIDATE_URL) === false) {
                    $t = mb_strtolower($title);
                    if (str_contains($t, 'gold') || str_contains($t, 'xau')) {
                        $imageUrl = 'https://responsive.fxempire.com/v7/_fxempire_/2026/10/gold-precious-metal-price-chart-19.jpg';
                    } elseif (str_contains($t, 'ethereum') || str_contains($t, 'eth')) {
                        $imageUrl = 'https://responsive.fxempire.com/v7/_fxempire_/2025/10/Ethereum-bull.jpg';
                    } elseif (str_contains($t, 'oil') || str_contains($t, 'crude')) {
                        $imageUrl = 'https://responsive.fxempire.com/v7/_fxempire_/2026/10/crude-oil-market-illustration-10.jpg';
                    } else {
                        $imageUrl = 'https://images.unsplash.com/photo-1621416894569-0f39ed31d247?w=800&auto=format&fit=crop&q=80';
                    }
                }

                $cleanDesc = strip_tags($desc);
                $cleanDesc = html_entity_decode($cleanDesc);
                if (mb_strlen($cleanDesc) > 150) {
                    $cleanDesc = mb_substr($cleanDesc, 0, 147) . '...';
                }

                $t = mb_strtolower($title);
                $category = 'Crypto';
                if (str_contains($t, 'sec') || str_contains($t, 'law') || str_contains($t, 'court') || str_contains($t, 'bill') || str_contains($t, 'fed') || str_contains($t, 'policy')) {
                    $category = 'Policy';
                } elseif (str_contains($t, 'gold') || str_contains($t, 'silver') || str_contains($t, 'oil')) {
                    $category = 'Commodities';
                } elseif (str_contains($t, 'stock') || str_contains($t, 'market') || str_contains($t, 'bank') || str_contains($t, 'inflation') || str_contains($t, 'dxy') || str_contains($t, 'forex')) {
                    $category = 'Markets';
                }

                $articles[] = [
                    'id' => 'news-' . $count,
                    'title' => $title,
                    'category' => $category,
                    'date' => $formattedDate,
                    'timestamp' => $ts,
                    'link' => $link,
                    'description' => $cleanDesc,
                    'full_description' => $cleanDesc,
                    'source' => $sourceName,
                    'image' => $imageUrl
                ];
                $count++;
            }

            return $articles;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchFxEmpireDirectArticles($urlPath = '/forecasts', $prefix = 'res')
    {
        try {
            $url = 'https://www.fxempire.com' . $urlPath;
            $response = Http::timeout(6)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            ])->get($url);

            $articles = [];
            if (!$response->successful()) return $articles;

            $html = $response->body();
            if (!preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $m)) {
                return $articles;
            }

            $json = json_decode($m[1], true);
            $queries = $json['props']['pageProps']['dehydratedState']['queries'] ?? [];

            $count = 1;
            foreach ($queries as $q) {
                $hash = json_encode($q['queryKey'] ?? '');
                if (str_contains($hash, 'articles')) {
                    $rawArticles = $q['state']['data']['data']['articles'] ?? $q['state']['data']['articles'] ?? [];
                    if (!is_array($rawArticles)) continue;

                    foreach ($rawArticles as $item) {
                        if (!is_array($item) || empty($item['title'])) continue;

                        $title = trim($item['title']);
                        $slug = $item['articleUrl'] ?? ('/forecasts/article/' . ($item['slug'] ?? ''));
                        $link = str_starts_with($slug, 'http') ? $slug : ('https://www.fxempire.com' . $slug);

                        // EXACT REAL FXEMPIRE FEATURED GRAPHIC IMAGE
                        $imageUrl = $item['imageUrl'] ?? null;
                        if (!$imageUrl && isset($item['media'])) {
                            $imageUrl = 'https://responsive.fxempire.com/v7/_fxempire_' . $item['media'];
                        }

                        if (!$imageUrl) {
                            $t = mb_strtolower($title);
                            if (str_contains($t, 'gold') || str_contains($t, 'xau')) {
                                $imageUrl = 'https://responsive.fxempire.com/v7/_fxempire_/2026/10/gold-precious-metal-price-chart-19.jpg';
                            } elseif (str_contains($t, 'ethereum') || str_contains($t, 'eth')) {
                                $imageUrl = 'https://responsive.fxempire.com/v7/_fxempire_/2025/10/Ethereum-bull.jpg';
                            } elseif (str_contains($t, 'oil') || str_contains($t, 'wti') || str_contains($t, 'crude')) {
                                $imageUrl = 'https://responsive.fxempire.com/v7/_fxempire_/2026/10/crude-oil-market-illustration-10.jpg';
                            } elseif (str_contains($t, 'silver') || str_contains($t, 'xag')) {
                                $imageUrl = 'https://responsive.fxempire.com/v7/_fxempire_/2026/09/silver-precious-metal-price-chart-02-5.jpg';
                            }
                        }

                        $dateTs = isset($item['date']) ? ($item['date'] / 1000) : (isset($item['timestamp']) ? ($item['timestamp'] / 1000) : time());
                        $formattedDate = date('M j, Y', $dateTs);

                        $authorName = $item['author']['name'] ?? 'FXEmpire Analyst';
                        $cleanDesc = $item['seoTitle'] ?? $title;

                        $t = mb_strtolower($title);
                        $category = 'Price Prediction';
                        if (str_contains($t, 'prediction') || str_contains($t, 'forecast') || str_contains($t, 'target')) {
                            $category = 'Price Prediction';
                        } elseif (str_contains($t, 'policy') || str_contains($t, 'sec') || str_contains($t, 'bill') || str_contains($t, 'fomc')) {
                            $category = 'Policy';
                        } elseif (str_contains($t, 'bitcoin') || str_contains($t, 'crypto') || str_contains($t, 'eth') || str_contains($t, 'ethereum')) {
                            $category = 'Crypto';
                        } elseif (str_contains($t, 'oil') || str_contains($t, 'gold') || str_contains($t, 'silver') || str_contains($t, 'gas')) {
                            $category = 'Commodities';
                        } elseif (str_contains($t, 'forex') || str_contains($t, 'dxy') || str_contains($t, 'eur') || str_contains($t, 'gbp')) {
                            $category = 'Forex';
                        } else {
                            $category = 'Markets';
                        }

                        $articles[] = [
                            'id' => $prefix . '-' . $count,
                            'title' => $title,
                            'category' => $category,
                            'date' => $formattedDate,
                            'timestamp' => $dateTs,
                            'link' => $link,
                            'description' => $cleanDesc,
                            'full_description' => $cleanDesc,
                            'source' => 'FXEmpire Research (' . $authorName . ')',
                            'image' => $imageUrl
                        ];
                        $count++;
                    }
                }
            }

            return $articles;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchFxEmpireRss($query, $limit = 30, $prefix = 'res')
    {
        $url = "https://news.google.com/rss/search?q=" . urlencode($query) . "&hl=en-US&gl=US&ceid=US:en";
        $response = Http::timeout(6)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ])->get($url);

        $articles = [];
        if (!$response->successful()) return $articles;

        $xml = @simplexml_load_string($response->body());
        if (!$xml || !isset($xml->channel->item)) return $articles;

        $count = 0;
        foreach ($xml->channel->item as $item) {
            if ($count >= $limit) break;

            $title = (string)$item->title;
            $title = preg_replace('/\s*-\s*FXEmpire.*$/i', '', $title);

            $link = (string)$item->link;
            $pubDate = (string)$item->pubDate;
            $timestamp = strtotime($pubDate);
            $formattedDate = $timestamp ? date('M j, Y', $timestamp) : date('M j, Y');

            $desc = (string)$item->description;

            // Extract image URL from enclosure or HTML description
            $itemImage = null;
            if (isset($item->enclosure) && isset($item->enclosure['url'])) {
                $itemImage = (string)$item->enclosure['url'];
            }
            if (!$itemImage && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $desc, $matches)) {
                $itemImage = $matches[1];
            }

            // Smart topic-based image assignment if no direct image in RSS
            if (!$itemImage || filter_var($itemImage, FILTER_VALIDATE_URL) === false) {
                $t = mb_strtolower($title);
                if (str_contains($t, 'ethereum') || str_contains($t, 'eth')) {
                    $itemImage = 'https://images.unsplash.com/photo-1622979135225-d2ba269bc1bd?w=800&auto=format&fit=crop&q=80';
                } elseif (str_contains($t, 'gold') || str_contains($t, 'xau')) {
                    $itemImage = 'https://images.unsplash.com/photo-1610375461246-83df859d849d?w=800&auto=format&fit=crop&q=80';
                } elseif (str_contains($t, 'silver') || str_contains($t, 'xag')) {
                    $itemImage = 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=800&auto=format&fit=crop&q=80';
                } elseif (str_contains($t, 'bitcoin') || str_contains($t, 'btc')) {
                    $itemImage = 'https://images.unsplash.com/photo-1621416894569-0f39ed31d247?w=800&auto=format&fit=crop&q=80';
                } elseif (str_contains($t, 'oil') || str_contains($t, 'wti') || str_contains($t, 'crude')) {
                    $itemImage = 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=800&auto=format&fit=crop&q=80';
                } elseif (str_contains($t, 'gas') || str_contains($t, 'energy')) {
                    $itemImage = 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800&auto=format&fit=crop&q=80';
                } elseif (str_contains($t, 's&p') || str_contains($t, 'stock') || str_contains($t, 'nasdaq') || str_contains($t, 'rally')) {
                    $itemImage = 'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?w=800&auto=format&fit=crop&q=80';
                } elseif (str_contains($t, 'dollar') || str_contains($t, 'dxy') || str_contains($t, 'fed') || str_contains($t, 'yield')) {
                    $itemImage = 'https://images.unsplash.com/photo-1580519542036-c47de6196ba5?w=800&auto=format&fit=crop&q=80';
                } else {
                    $itemImage = 'https://images.unsplash.com/photo-1642543492481-44e81e3914a7?w=800&auto=format&fit=crop&q=80';
                }
            }

            $cleanDesc = strip_tags($desc);
            $cleanDesc = html_entity_decode($cleanDesc);
            $cleanDesc = preg_replace('/\s*-\s*FXEmpire.*$/i', '', $cleanDesc);
            
            $shortDesc = $cleanDesc;
            if (mb_strlen($shortDesc) > 130) {
                $shortDesc = mb_substr($shortDesc, 0, 127) . '...';
            }

            $category = 'Price Prediction';
            $t = mb_strtolower($title);
            if (str_contains($t, 'prediction') || str_contains($t, 'forecast') || str_contains($t, 'target')) {
                $category = 'Price Prediction';
            } elseif (str_contains($t, 'policy') || str_contains($t, 'sec') || str_contains($t, 'bill') || str_contains($t, 'fomc')) {
                $category = 'Policy';
            } elseif (str_contains($t, 'bitcoin') || str_contains($t, 'crypto') || str_contains($t, 'eth')) {
                $category = 'Crypto';
            } elseif (str_contains($t, 'oil') || str_contains($t, 'gold') || str_contains($t, 'silver') || str_contains($t, 'gas')) {
                $category = 'Commodities';
            } elseif (str_contains($t, 'forex') || str_contains($t, 'dxy') || str_contains($t, 'eur') || str_contains($t, 'gbp')) {
                $category = 'Forex';
            } else {
                $category = 'Markets';
            }

            $articles[] = [
                'id' => $prefix . '-' . ($count + 1),
                'title' => $title,
                'category' => $category,
                'date' => $formattedDate,
                'link' => $link,
                'description' => $shortDesc,
                'full_description' => $cleanDesc,
                'source' => 'FXEmpire Financial Research',
                'image' => $itemImage
            ];
            $count++;
        }
        return $articles;
    }

    public function upcomingEvents()
    {
        try {
            $events = $this->fetchForexFactoryEvents();
            return response()->json([
                'success' => true,
                'source' => 'forexfactory',
                'data' => $events,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function fetchForexFactoryEvents()
    {
        $events = [];
        $now = time();
        try {
            $url = "https://news.google.com/rss/search?q=site:forexfactory.com+OR+%22economic+calendar%22+OR+FOMC+OR+CPI+OR+PMI&hl=en-US&gl=US&ceid=US:en";
            $response = Http::timeout(6)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ])->get($url);

            if ($response->successful()) {
                $xml = @simplexml_load_string($response->body());
                if ($xml && isset($xml->channel->item)) {
                    foreach ($xml->channel->item as $item) {
                        if (count($events) >= 15) break;
                        $title = (string)$item->title;
                        $title = preg_replace('/\s*-\s*Forex Factory.*$/i', '', $title);
                        $title = preg_replace('/\s*-\s*.*$/i', '', $title);
                        
                        $pubDate = (string)$item->pubDate;
                        $itemTime = strtotime($pubDate) ?: ($now + 3600);

                        if ($itemTime < ($now - 1800)) {
                            continue;
                        }

                        $dateStr = date('M j', $itemTime);
                        $timeStr = date('H:i', $itemTime);

                        $flag = '🇺🇸';
                        if (stripos($title, 'BOE') !== false || stripos($title, 'UK') !== false || stripos($title, 'Britain') !== false || stripos($title, 'GBP') !== false) {
                            $flag = '🇬🇧';
                        } elseif (stripos($title, 'ECB') !== false || stripos($title, 'Euro') !== false || stripos($title, 'Germany') !== false || stripos($title, 'EUR') !== false) {
                            $flag = '🇪🇺';
                        } elseif (stripos($title, 'Canada') !== false || stripos($title, 'CAD') !== false || stripos($title, 'Ivey') !== false) {
                            $flag = '🇨🇦';
                        } elseif (stripos($title, 'Japan') !== false || stripos($title, 'BOJ') !== false || stripos($title, 'Yen') !== false) {
                            $flag = '🇯🇵';
                        } elseif (stripos($title, 'Australia') !== false || stripos($title, 'RBA') !== false || stripos($title, 'AUD') !== false) {
                            $flag = '🇦🇺';
                        }

                        $impact = 'Medium';
                        $impactColor = '#f59e0b';
                        if (stripos($title, 'FOMC') !== false || stripos($title, 'Rate') !== false || stripos($title, 'Payrolls') !== false || stripos($title, 'CPI') !== false || stripos($title, 'NFP') !== false) {
                            $impact = 'High';
                            $impactColor = '#ef4444';
                        } elseif (stripos($title, 'Speech') !== false || stripos($title, 'Weekly') !== false) {
                            $impact = 'Low';
                            $impactColor = '#9ca3af';
                        }

                        $events[] = [
                            'timestamp' => $itemTime,
                            'date' => $dateStr,
                            'time' => $timeStr,
                            'flag' => $flag,
                            'title' => mb_strlen($title) > 34 ? mb_substr($title, 0, 31) . '...' : $title,
                            'impact' => $impact,
                            'impact_color' => $impactColor,
                            'link' => (string)$item->link ?: 'https://www.forexfactory.com/calendar'
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {}

        $defaultEvents = [
            // High Impact
            [
                'timestamp' => $now + 3600 * 2,
                'date' => date('M j', $now + 3600 * 2),
                'time' => date('H:i', $now + 3600 * 2),
                'flag' => '🇺🇸',
                'title' => 'FOMC Meeting Minutes',
                'impact' => 'High',
                'impact_color' => '#ef4444',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + 3600 * 6,
                'date' => date('M j', $now + 3600 * 6),
                'time' => date('H:i', $now + 3600 * 6),
                'flag' => '🇬🇧',
                'title' => 'BOE Gov Bailey Speaks',
                'impact' => 'High',
                'impact_color' => '#ef4444',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + (int)(86400 * 1.2),
                'date' => date('M j', $now + (int)(86400 * 1.2)),
                'time' => '13:30',
                'flag' => '🇺🇸',
                'title' => 'Core CPI (MoM)',
                'impact' => 'High',
                'impact_color' => '#ef4444',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + (int)(86400 * 1.8),
                'date' => date('M j', $now + (int)(86400 * 1.8)),
                'time' => '17:00',
                'flag' => '🇪🇺',
                'title' => 'ECB Press Conference',
                'impact' => 'High',
                'impact_color' => '#ef4444',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + (int)(86400 * 2.5),
                'date' => date('M j', $now + (int)(86400 * 2.5)),
                'time' => '18:30',
                'flag' => '🇺🇸',
                'title' => 'Non-Farm Employment Change',
                'impact' => 'High',
                'impact_color' => '#ef4444',
                'link' => 'https://www.forexfactory.com/calendar'
            ],

            // Medium Impact
            [
                'timestamp' => $now + 3600 * 4,
                'date' => date('M j', $now + 3600 * 4),
                'time' => date('H:i', $now + 3600 * 4),
                'flag' => '🇨🇦',
                'title' => 'Ivey PMI (Sep)',
                'impact' => 'Medium',
                'impact_color' => '#f59e0b',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + 3600 * 10,
                'date' => date('M j', $now + 3600 * 10),
                'time' => date('H:i', $now + 3600 * 10),
                'flag' => '🇺🇸',
                'title' => 'FOMC Member Waller Speaks',
                'impact' => 'Medium',
                'impact_color' => '#f59e0b',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + (int)(86400 * 1.1),
                'date' => date('M j', $now + (int)(86400 * 1.1)),
                'time' => '15:00',
                'flag' => '🇯🇵',
                'title' => 'BOJ Summary of Opinions',
                'impact' => 'Medium',
                'impact_color' => '#f59e0b',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + (int)(86400 * 1.6),
                'date' => date('M j', $now + (int)(86400 * 1.6)),
                'time' => '16:30',
                'flag' => '🇦🇺',
                'title' => 'RBA Rate Statement',
                'impact' => 'Medium',
                'impact_color' => '#f59e0b',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + (int)(86400 * 2.2),
                'date' => date('M j', $now + (int)(86400 * 2.2)),
                'time' => '19:00',
                'flag' => '🇬🇧',
                'title' => 'GDP (MoM)',
                'impact' => 'Medium',
                'impact_color' => '#f59e0b',
                'link' => 'https://www.forexfactory.com/calendar'
            ],

            // Low Impact
            [
                'timestamp' => $now + 3600 * 8,
                'date' => date('M j', $now + 3600 * 8),
                'time' => date('H:i', $now + 3600 * 8),
                'flag' => '🇺🇸',
                'title' => 'Unemployment Claims',
                'impact' => 'Low',
                'impact_color' => '#9ca3af',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + 3600 * 16,
                'date' => date('M j', $now + 3600 * 16),
                'time' => date('H:i', $now + 3600 * 16),
                'flag' => '🇺🇸',
                'title' => 'Crude Oil Inventories',
                'impact' => 'Low',
                'impact_color' => '#9ca3af',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + (int)(86400 * 1.4),
                'date' => date('M j', $now + (int)(86400 * 1.4)),
                'time' => '11:00',
                'flag' => '🇪🇺',
                'title' => 'German ZEW Economic Sentiment',
                'impact' => 'Low',
                'impact_color' => '#9ca3af',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + (int)(86400 * 1.9),
                'date' => date('M j', $now + (int)(86400 * 1.9)),
                'time' => '14:30',
                'flag' => '🇺🇸',
                'title' => 'Natural Gas Storage',
                'impact' => 'Low',
                'impact_color' => '#9ca3af',
                'link' => 'https://www.forexfactory.com/calendar'
            ],
            [
                'timestamp' => $now + (int)(86400 * 2.7),
                'date' => date('M j', $now + (int)(86400 * 2.7)),
                'time' => '20:00',
                'flag' => '🇺🇸',
                'title' => 'Federal Budget Balance',
                'impact' => 'Low',
                'impact_color' => '#9ca3af',
                'link' => 'https://www.forexfactory.com/calendar'
            ]
        ];

        foreach ($defaultEvents as $def) {
            // Avoid duplicate titles
            $exists = false;
            foreach ($events as $existing) {
                if (strtolower($existing['title']) === strtolower($def['title'])) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $events[] = $def;
            }
        }

        // Sort by timestamp ascending
        usort($events, function($a, $b) {
            return ($a['timestamp'] ?? 0) - ($b['timestamp'] ?? 0);
        });

        return $events;
    }
}
