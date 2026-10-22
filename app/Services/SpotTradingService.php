<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SpotTradingService
{
    /**
     * Supported Spot Trading Pairs configuration.
     */
    public const SUPPORTED_PAIRS = [
        'BTCUSDT' => ['base' => 'BTC', 'quote' => 'USDT', 'name' => 'BTC/USDT', 'price_precision' => 2, 'amount_precision' => 4, 'min_amount' => 0.0001, 'min_cost' => 5],
        'ETHUSDT' => ['base' => 'ETH', 'quote' => 'USDT', 'name' => 'ETH/USDT', 'price_precision' => 2, 'amount_precision' => 4, 'min_amount' => 0.001, 'min_cost' => 5],
        'SOLUSDT' => ['base' => 'SOL', 'quote' => 'USDT', 'name' => 'SOL/USDT', 'price_precision' => 2, 'amount_precision' => 2, 'min_amount' => 0.01, 'min_cost' => 5],
        'BNBUSDT' => ['base' => 'BNB', 'quote' => 'USDT', 'name' => 'BNB/USDT', 'price_precision' => 2, 'amount_precision' => 3, 'min_amount' => 0.005, 'min_cost' => 5],
        'XRPUSDT' => ['base' => 'XRP', 'quote' => 'USDT', 'name' => 'XRP/USDT', 'price_precision' => 4, 'amount_precision' => 1, 'min_amount' => 1.0, 'min_cost' => 5],
        'DOGEUSDT' => ['base' => 'DOGE', 'quote' => 'USDT', 'name' => 'DOGE/USDT', 'price_precision' => 5, 'amount_precision' => 1, 'min_amount' => 10.0, 'min_cost' => 5],
        'ADAUSDT' => ['base' => 'ADA', 'quote' => 'USDT', 'name' => 'ADA/USDT', 'price_precision' => 4, 'amount_precision' => 1, 'min_amount' => 1.0, 'min_cost' => 5],
        'AVAXUSDT' => ['base' => 'AVAX', 'quote' => 'USDT', 'name' => 'AVAX/USDT', 'price_precision' => 2, 'amount_precision' => 2, 'min_amount' => 0.1, 'min_cost' => 5],
        'LINKUSDT' => ['base' => 'LINK', 'quote' => 'USDT', 'name' => 'LINK/USDT', 'price_precision' => 3, 'amount_precision' => 2, 'min_amount' => 0.1, 'min_cost' => 5],
        'DOTUSDT' => ['base' => 'DOT', 'quote' => 'USDT', 'name' => 'DOT/USDT', 'price_precision' => 3, 'amount_precision' => 2, 'min_amount' => 0.1, 'min_cost' => 5],
        'LTCUSDT' => ['base' => 'LTC', 'quote' => 'USDT', 'name' => 'LTC/USDT', 'price_precision' => 2, 'amount_precision' => 3, 'min_amount' => 0.01, 'min_cost' => 5],
        'NEARUSDT' => ['base' => 'NEAR', 'quote' => 'USDT', 'name' => 'NEAR/USDT', 'price_precision' => 3, 'amount_precision' => 2, 'min_amount' => 0.1, 'min_cost' => 5],
        'SUIUSDT' => ['base' => 'SUI', 'quote' => 'USDT', 'name' => 'SUI/USDT', 'price_precision' => 4, 'amount_precision' => 2, 'min_amount' => 1.0, 'min_cost' => 5],
        'TRXUSDT' => ['base' => 'TRX', 'quote' => 'USDT', 'name' => 'TRX/USDT', 'price_precision' => 5, 'amount_precision' => 1, 'min_amount' => 10.0, 'min_cost' => 5],
        'SHIBUSDT' => ['base' => 'SHIB', 'quote' => 'USDT', 'name' => 'SHIB/USDT', 'price_precision' => 8, 'amount_precision' => 0, 'min_amount' => 10000.0, 'min_cost' => 5],
        'PEPEUSDT' => ['base' => 'PEPE', 'quote' => 'USDT', 'name' => 'PEPE/USDT', 'price_precision' => 8, 'amount_precision' => 0, 'min_amount' => 10000.0, 'min_cost' => 5],
        'MATICUSDT' => ['base' => 'MATIC', 'quote' => 'USDT', 'name' => 'MATIC/USDT', 'price_precision' => 4, 'amount_precision' => 1, 'min_amount' => 1.0, 'min_cost' => 5],
    ];

    public const DEFAULT_FEE_RATE = 0.001; // 0.1% Spot trading fee

    /**
     * Normalize pair symbol (e.g. BTC/USDT -> BTCUSDT).
     */
    public function normalizePair(string $pair): string
    {
        $clean = strtoupper(str_replace(['/', '-', '_', ' '], '', trim($pair)));
        if (!isset(self::SUPPORTED_PAIRS[$clean])) {
            if (str_ends_with($clean, 'USDT') && strlen($clean) > 4) {
                return $clean;
            }
            return 'BTCUSDT';
        }
        return $clean;
    }

    /**
     * Get pair metadata.
     */
    public function getPairConfig(string $pair): array
    {
        $symbol = $this->normalizePair($pair);
        if (isset(self::SUPPORTED_PAIRS[$symbol])) {
            return self::SUPPORTED_PAIRS[$symbol];
        }
        $base = str_ends_with($symbol, 'USDT') ? substr($symbol, 0, -4) : $symbol;
        return [
            'base' => $base,
            'quote' => 'USDT',
            'name' => $base . '/USDT',
            'price_precision' => 4,
            'amount_precision' => 2,
            'min_amount' => 0.01,
            'min_cost' => 5,
        ];
    }

    /**
     * Get or initialize Spot Wallet for a user.
     */
    public function getSpotWallet(string $userUid): array
    {
        $wallet = DB::connection('mongodb')
            ->table('spot_wallets')
            ->where('user_uid', $userUid)
            ->first();

        if ($wallet) {
            $data = (array) $wallet;
            if (!isset($data['usdt']) || !is_array($data['usdt'])) {
                $data['usdt'] = ['free' => 10000.0, 'locked' => 0.0];
            }
            if (!isset($data['assets']) || !is_array($data['assets'])) {
                $data['assets'] = [];
            }
            return $data;
        }

        // Check if demo wallet has initial balance to seed Spot USDT
        $demoWallet = DB::connection('mongodb')
            ->table('demo_wallets')
            ->where('user_uid', $userUid)
            ->first();

        $initialUsdt = $demoWallet ? (float) ($demoWallet->demo_main_balance ?? 10000.0) : 10000.0;
        if ($initialUsdt <= 0) {
            $initialUsdt = 10000.0;
        }

        $initialData = [
            'user_uid' => $userUid,
            'usdt' => [
                'free' => round($initialUsdt, 4),
                'locked' => 0.0,
            ],
            'assets' => [
                'BTC' => ['free' => 0.25, 'locked' => 0.0],
                'ETH' => ['free' => 2.50, 'locked' => 0.0],
                'SOL' => ['free' => 15.0, 'locked' => 0.0],
                'BNB' => ['free' => 5.0, 'locked' => 0.0],
                'XRP' => ['free' => 500.0, 'locked' => 0.0],
                'DOGE' => ['free' => 2000.0, 'locked' => 0.0],
                'ADA' => ['free' => 500.0, 'locked' => 0.0],
                'AVAX' => ['free' => 25.0, 'locked' => 0.0],
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')
            ->table('spot_wallets')
            ->insert($initialData);

        return $initialData;
    }

    /**
     * Update user's spot wallet.
     */
    public function updateSpotWallet(string $userUid, array $data): void
    {
        $data['updated_at'] = now();
        DB::connection('mongodb')
            ->table('spot_wallets')
            ->where('user_uid', $userUid)
            ->update($data);
    }

    /**
     * Get formatted balances for user.
     */
    public function getUserBalances(string $userUid): array
    {
        $wallet = $this->getSpotWallet($userUid);
        $usdt = $wallet['usdt'] ?? ['free' => 0.0, 'locked' => 0.0];
        $assets = $wallet['assets'] ?? [];

        $balances = [
            'USDT' => [
                'asset' => 'USDT',
                'name' => 'Tether USD',
                'free' => (float) ($usdt['free'] ?? 0),
                'locked' => (float) ($usdt['locked'] ?? 0),
                'total' => (float) (($usdt['free'] ?? 0) + ($usdt['locked'] ?? 0)),
            ]
        ];

        foreach (self::SUPPORTED_PAIRS as $cfg) {
            $base = $cfg['base'];
            $assetData = $assets[$base] ?? ['free' => 0.0, 'locked' => 0.0];
            $free = (float) ($assetData['free'] ?? 0);
            $locked = (float) ($assetData['locked'] ?? 0);
            $balances[$base] = [
                'asset' => $base,
                'name' => $base,
                'free' => $free,
                'locked' => $locked,
                'total' => round($free + $locked, 6),
            ];
        }

        return $balances;
    }

    /**
     * Place Spot Order (Market or Limit).
     */
    public function placeOrder(string $userUid, array $payload): array
    {
        $symbol = $this->normalizePair($payload['pair'] ?? $payload['symbol'] ?? 'BTCUSDT');
        $pairConfig = self::SUPPORTED_PAIRS[$symbol];
        $base = $pairConfig['base'];
        $quote = $pairConfig['quote'];

        $side = strtolower(trim($payload['side'] ?? ''));
        if (!in_array($side, ['buy', 'sell'], true)) {
            throw new InvalidArgumentException('Order side must be buy or sell.');
        }

        $type = strtolower(trim($payload['type'] ?? 'limit'));
        if (!in_array($type, ['limit', 'market'], true)) {
            throw new InvalidArgumentException('Order type must be limit or market.');
        }

        $amount = (float) ($payload['amount'] ?? 0);
        if ($amount < $pairConfig['min_amount']) {
            throw new InvalidArgumentException("Minimum order amount for {$symbol} is {$pairConfig['min_amount']} {$base}.");
        }

        $price = (float) ($payload['price'] ?? 0);
        $currentMarketPrice = (float) ($payload['market_price'] ?? 0);

        if ($type === 'market') {
            if ($currentMarketPrice <= 0) {
                throw new InvalidArgumentException('Current market price is required for market orders.');
            }
            $price = $currentMarketPrice;
        } else {
            if ($price <= 0) {
                throw new InvalidArgumentException('Valid limit price is required for limit orders.');
            }
        }

        $totalCost = round($amount * $price, 4);
        if ($totalCost < $pairConfig['min_cost']) {
            throw new InvalidArgumentException("Minimum order value is \${$pairConfig['min_cost']} USDT.");
        }

        $wallet = $this->getSpotWallet($userUid);
        $usdtFree = (float) ($wallet['usdt']['free'] ?? 0);
        $baseFree = (float) ($wallet['assets'][$base]['free'] ?? 0);

        $feeRate = self::DEFAULT_FEE_RATE;
        $orderUid = 'spord_' . Str::lower(Str::random(12));
        $now = now();

        if ($type === 'market') {
            // Instant Market Execution
            if ($side === 'buy') {
                $feeUsdt = round($totalCost * $feeRate, 4);
                $requiredUsdt = $totalCost + $feeUsdt;

                if ($usdtFree < $requiredUsdt) {
                    $shortage = number_format($requiredUsdt - $usdtFree, 2);
                    throw new RuntimeException("Insufficient USDT balance. Need \${$requiredUsdt} USDT (Short by \${$shortage} USDT).");
                }

                // Debit USDT, Credit Base Crypto
                $wallet['usdt']['free'] = round($usdtFree - $requiredUsdt, 4);
                if (!isset($wallet['assets'][$base])) {
                    $wallet['assets'][$base] = ['free' => 0.0, 'locked' => 0.0];
                }
                $wallet['assets'][$base]['free'] = round((float) ($wallet['assets'][$base]['free'] ?? 0) + $amount, 6);
                $this->updateSpotWallet($userUid, $wallet);

                $orderDoc = [
                    'order_uid' => $orderUid,
                    'user_uid' => $userUid,
                    'symbol' => $symbol,
                    'base_asset' => $base,
                    'quote_asset' => $quote,
                    'side' => 'buy',
                    'type' => 'market',
                    'price' => $price,
                    'amount' => $amount,
                    'filled_amount' => $amount,
                    'cost' => $totalCost,
                    'fee' => $feeUsdt,
                    'fee_asset' => 'USDT',
                    'status' => 'filled',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                DB::connection('mongodb')->table('spot_orders')->insert($orderDoc);

                $tradeDoc = [
                    'trade_uid' => 'sptrd_' . Str::lower(Str::random(12)),
                    'order_uid' => $orderUid,
                    'user_uid' => $userUid,
                    'symbol' => $symbol,
                    'side' => 'buy',
                    'price' => $price,
                    'amount' => $amount,
                    'cost' => $totalCost,
                    'fee' => $feeUsdt,
                    'fee_asset' => 'USDT',
                    'executed_at' => $now,
                ];
                DB::connection('mongodb')->table('spot_trades')->insert($tradeDoc);

                return [
                    'success' => true,
                    'order' => $orderDoc,
                    'trade' => $tradeDoc,
                    'balances' => $this->getUserBalances($userUid),
                    'message' => "Market Buy of {$amount} {$base} executed at \${$price} USDT.",
                ];
            } else {
                // Market Sell: Sell Base Crypto for USDT
                if ($baseFree < $amount) {
                    $shortage = number_format($amount - $baseFree, 4);
                    throw new RuntimeException("Insufficient {$base} balance. Need {$amount} {$base} (Short by {$shortage} {$base}).");
                }

                $grossUsdt = $totalCost;
                $feeUsdt = round($grossUsdt * $feeRate, 4);
                $netUsdt = round($grossUsdt - $feeUsdt, 4);

                // Debit Base Crypto, Credit USDT
                $wallet['assets'][$base]['free'] = round($baseFree - $amount, 6);
                $wallet['usdt']['free'] = round($usdtFree + $netUsdt, 4);
                $this->updateSpotWallet($userUid, $wallet);

                $orderDoc = [
                    'order_uid' => $orderUid,
                    'user_uid' => $userUid,
                    'symbol' => $symbol,
                    'base_asset' => $base,
                    'quote_asset' => $quote,
                    'side' => 'sell',
                    'type' => 'market',
                    'price' => $price,
                    'amount' => $amount,
                    'filled_amount' => $amount,
                    'cost' => $grossUsdt,
                    'fee' => $feeUsdt,
                    'fee_asset' => 'USDT',
                    'status' => 'filled',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                DB::connection('mongodb')->table('spot_orders')->insert($orderDoc);

                $tradeDoc = [
                    'trade_uid' => 'sptrd_' . Str::lower(Str::random(12)),
                    'order_uid' => $orderUid,
                    'user_uid' => $userUid,
                    'symbol' => $symbol,
                    'side' => 'sell',
                    'price' => $price,
                    'amount' => $amount,
                    'cost' => $grossUsdt,
                    'fee' => $feeUsdt,
                    'fee_asset' => 'USDT',
                    'executed_at' => $now,
                ];
                DB::connection('mongodb')->table('spot_trades')->insert($tradeDoc);

                return [
                    'success' => true,
                    'order' => $orderDoc,
                    'trade' => $tradeDoc,
                    'balances' => $this->getUserBalances($userUid),
                    'message' => "Market Sell of {$amount} {$base} executed at \${$price} USDT.",
                ];
            }
        } else {
            // Limit Order: Lock Funds in Wallet
            if ($side === 'buy') {
                $feeUsdt = round($totalCost * $feeRate, 4);
                $requiredUsdt = $totalCost + $feeUsdt;

                if ($usdtFree < $requiredUsdt) {
                    $shortage = number_format($requiredUsdt - $usdtFree, 2);
                    throw new RuntimeException("Insufficient USDT balance. Need \${$requiredUsdt} USDT (Short by \${$shortage} USDT).");
                }

                // Lock USDT
                $wallet['usdt']['free'] = round($usdtFree - $requiredUsdt, 4);
                $wallet['usdt']['locked'] = round((float) ($wallet['usdt']['locked'] ?? 0) + $requiredUsdt, 4);
                $this->updateSpotWallet($userUid, $wallet);

                $orderDoc = [
                    'order_uid' => $orderUid,
                    'user_uid' => $userUid,
                    'symbol' => $symbol,
                    'base_asset' => $base,
                    'quote_asset' => $quote,
                    'side' => 'buy',
                    'type' => 'limit',
                    'price' => $price,
                    'amount' => $amount,
                    'filled_amount' => 0.0,
                    'cost' => $totalCost,
                    'locked_amount' => $requiredUsdt,
                    'fee' => $feeUsdt,
                    'fee_asset' => 'USDT',
                    'status' => 'open',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                DB::connection('mongodb')->table('spot_orders')->insert($orderDoc);

                return [
                    'success' => true,
                    'order' => $orderDoc,
                    'balances' => $this->getUserBalances($userUid),
                    'message' => "Limit Buy order placed for {$amount} {$base} @ \${$price}.",
                ];
            } else {
                // Limit Sell: Lock Base Crypto
                if ($baseFree < $amount) {
                    $shortage = number_format($amount - $baseFree, 4);
                    throw new RuntimeException("Insufficient {$base} balance. Need {$amount} {$base} (Short by {$shortage} {$base}).");
                }

                $wallet['assets'][$base]['free'] = round($baseFree - $amount, 6);
                $wallet['assets'][$base]['locked'] = round((float) ($wallet['assets'][$base]['locked'] ?? 0) + $amount, 6);
                $this->updateSpotWallet($userUid, $wallet);

                $orderDoc = [
                    'order_uid' => $orderUid,
                    'user_uid' => $userUid,
                    'symbol' => $symbol,
                    'base_asset' => $base,
                    'quote_asset' => $quote,
                    'side' => 'sell',
                    'type' => 'limit',
                    'price' => $price,
                    'amount' => $amount,
                    'filled_amount' => 0.0,
                    'cost' => $totalCost,
                    'locked_amount' => $amount,
                    'fee' => round($totalCost * $feeRate, 4),
                    'fee_asset' => 'USDT',
                    'status' => 'open',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                DB::connection('mongodb')->table('spot_orders')->insert($orderDoc);

                return [
                    'success' => true,
                    'order' => $orderDoc,
                    'balances' => $this->getUserBalances($userUid),
                    'message' => "Limit Sell order placed for {$amount} {$base} @ \${$price}.",
                ];
            }
        }
    }

    /**
     * Cancel an Open Limit Order and unlock user funds.
     */
    public function cancelOrder(string $userUid, string $orderUid): array
    {
        $order = DB::connection('mongodb')
            ->table('spot_orders')
            ->where('user_uid', $userUid)
            ->where('order_uid', $orderUid)
            ->where('status', 'open')
            ->first();

        if (!$order) {
            throw new RuntimeException('Order not found or already executed/cancelled.');
        }

        $orderArr = (array) $order;
        $wallet = $this->getSpotWallet($userUid);
        $base = $orderArr['base_asset'];
        $locked = (float) ($orderArr['locked_amount'] ?? 0);

        if ($orderArr['side'] === 'buy') {
            // Unlock USDT
            $wallet['usdt']['free'] = round((float) ($wallet['usdt']['free'] ?? 0) + $locked, 4);
            $wallet['usdt']['locked'] = max(0, round((float) ($wallet['usdt']['locked'] ?? 0) - $locked, 4));
        } else {
            // Unlock Base Crypto
            $wallet['assets'][$base]['free'] = round((float) ($wallet['assets'][$base]['free'] ?? 0) + $locked, 6);
            $wallet['assets'][$base]['locked'] = max(0, round((float) ($wallet['assets'][$base]['locked'] ?? 0) - $locked, 6));
        }

        $this->updateSpotWallet($userUid, $wallet);

        DB::connection('mongodb')
            ->table('spot_orders')
            ->where('order_uid', $orderUid)
            ->update([
                'status' => 'cancelled',
                'updated_at' => now(),
            ]);

        return [
            'success' => true,
            'message' => 'Spot order cancelled successfully.',
            'balances' => $this->getUserBalances($userUid),
        ];
    }

    /**
     * Check and process fills for open limit orders against current tick prices.
     */
    public function processLimitFills(string $userUid, array $currentPrices): array
    {
        $openOrders = DB::connection('mongodb')
            ->table('spot_orders')
            ->where('user_uid', $userUid)
            ->where('status', 'open')
            ->get();

        $filledCount = 0;
        $now = now();

        foreach ($openOrders as $order) {
            $orderArr = (array) $order;
            $symbol = $orderArr['symbol'];
            $side = $orderArr['side'];
            $limitPrice = (float) $orderArr['price'];
            $amount = (float) $orderArr['amount'];
            $base = $orderArr['base_asset'];

            $currentPrice = (float) ($currentPrices[$symbol] ?? 0);
            if ($currentPrice <= 0) {
                continue;
            }

            // Only fill if order has been open for at least 8 seconds or strictly crossed
            $createdAt = isset($orderArr['created_at']) ? strtotime((string) $orderArr['created_at']) : 0;
            if ($createdAt > 0 && (time() - $createdAt) < 8) {
                continue;
            }

            $shouldFill = false;
            if ($side === 'buy' && $currentPrice <= $limitPrice) {
                $shouldFill = true;
            } elseif ($side === 'sell' && $currentPrice >= $limitPrice) {
                $shouldFill = true;
            }

            if ($shouldFill) {
                $wallet = $this->getSpotWallet($userUid);
                $locked = (float) ($orderArr['locked_amount'] ?? 0);
                $execCost = round($amount * $limitPrice, 4);
                $feeUsdt = round($execCost * self::DEFAULT_FEE_RATE, 4);

                if ($side === 'buy') {
                    // Release locked USDT and credit Base Crypto
                    $wallet['usdt']['locked'] = max(0, round((float) ($wallet['usdt']['locked'] ?? 0) - $locked, 4));
                    if (!isset($wallet['assets'][$base])) {
                        $wallet['assets'][$base] = ['free' => 0.0, 'locked' => 0.0];
                    }
                    $wallet['assets'][$base]['free'] = round((float) ($wallet['assets'][$base]['free'] ?? 0) + $amount, 6);
                } else {
                    // Release locked Base Crypto and credit USDT
                    $wallet['assets'][$base]['locked'] = max(0, round((float) ($wallet['assets'][$base]['locked'] ?? 0) - $locked, 6));
                    $netUsdt = round($execCost - $feeUsdt, 4);
                    $wallet['usdt']['free'] = round((float) ($wallet['usdt']['free'] ?? 0) + $netUsdt, 4);
                }

                $this->updateSpotWallet($userUid, $wallet);

                DB::connection('mongodb')
                    ->table('spot_orders')
                    ->where('order_uid', $orderArr['order_uid'])
                    ->update([
                        'status' => 'filled',
                        'filled_amount' => $amount,
                        'updated_at' => $now,
                    ]);

                DB::connection('mongodb')->table('spot_trades')->insert([
                    'trade_uid' => 'sptrd_' . Str::lower(Str::random(12)),
                    'order_uid' => $orderArr['order_uid'],
                    'user_uid' => $userUid,
                    'symbol' => $symbol,
                    'side' => $side,
                    'price' => $limitPrice,
                    'amount' => $amount,
                    'cost' => $execCost,
                    'fee' => $feeUsdt,
                    'fee_asset' => 'USDT',
                    'executed_at' => $now,
                ]);

                $filledCount++;
            }
        }

        return [
            'success' => true,
            'filled_count' => $filledCount,
            'balances' => $this->getUserBalances($userUid),
        ];
    }
}

