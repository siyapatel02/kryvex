<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DemoWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class FuturesOrderController extends Controller
{
    public function preview(Request $request)
    {
        $validated = $this->validateOrderPayload($request);
        $calculation = $this->calculateOrder($validated);

        return response()->json([
            'success' => true,
            'message' => 'Order preview calculated successfully. Real execution is disabled.',
            'data' => [
                ...$calculation,
                'execution_enabled' => false,
                'warning' => 'This is only a preview. No real order is placed.',
            ],
        ]);
    }

    protected function getActiveAccountUid(Request $request, string $userUid, string $mode = 'demo'): string
    {
        $headerUid = $request->header('X-Account-UID') ?: $request->input('account_uid');
        $broker = $this->resolveBrokerType($request, $userUid, $headerUid);
        $walletService = app(DemoWalletService::class);
        return $walletService->resolveAccountUid($userUid, $headerUid, $broker, $mode === 'live', $mode === 'demo');
    }

    protected function resolveBrokerType(Request $request, string $userUid, ?string $accountUid = null): string
    {
        if (!empty($accountUid)) {
            $account = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('account_uid', $accountUid)
                ->first();
            if ($account && !empty($account->broker_type)) {
                $b = strtolower($account->broker_type);
                if (in_array($b, ['kryvex', 'mt5'], true)) {
                    return $b;
                }
            }
        }

        $headerBroker = $request->header('X-Broker-Type') ?: $request->input('broker_type');
        if (!empty($headerBroker) && in_array(strtolower($headerBroker), ['kryvex', 'mt5'], true)) {
            return strtolower($headerBroker);
        }

        $walletService = app(DemoWalletService::class);
        return $walletService->getActiveBrokerType($userUid);
    }

    protected function getBrokerTypeForOrderOrPosition($item, ?string $fallbackUserUid = null): string
    {
        $arr = is_array($item) ? $item : (array) $item;
        if (!empty($arr['broker_type']) && in_array(strtolower($arr['broker_type']), ['kryvex', 'mt5'], true)) {
            return strtolower($arr['broker_type']);
        }

        if (!empty($arr['account_uid'])) {
            $account = DB::connection('mongodb')
                ->table('trading_accounts')
                ->where('account_uid', $arr['account_uid'])
                ->first();
            if ($account && !empty($account->broker_type)) {
                $b = strtolower($account->broker_type);
                if (in_array($b, ['kryvex', 'mt5'], true)) {
                    return $b;
                }
            }
        }

        $uid = $arr['user_uid'] ?? $fallbackUserUid;
        if ($uid) {
            $walletService = app(DemoWalletService::class);
            return $walletService->getActiveBrokerType((string) $uid);
        }

        return 'kryvex';
    }

    protected function getMt5AccountId(string $userUid, ?string $userName = null): ?string
    {
        $metaApiService = app(\App\Services\MetaApiService::class);
        if (!$metaApiService->isEnabled()) {
            return null;
        }

        $mt5Account = \App\Models\Mt5Account::where('user_uid', $userUid)->where('status', 'active')->first();
        if (!$mt5Account) {
            return null;
        }

        $accountId = $mt5Account->metaapi_account_id ?? null;
        return $metaApiService->isValidAccountId($accountId) ? $accountId : null;
    }

    public function placeDemo(Request $request, DemoWalletService $walletService)
    {
        return $this->executeOrder($request, $walletService, 'demo');
    }

    public function placeLive(Request $request, DemoWalletService $walletService)
    {
        return $this->executeOrder($request, $walletService, 'live');
    }

    protected function executeOrder(Request $request, DemoWalletService $walletService, string $mode = 'demo')
    {
        $start = microtime(true);
        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid'], $mode);
        $brokerType = $this->resolveBrokerType($request, $user['uid'], $accountUid);

        Log::info("place{$mode} payload received", array_merge($request->all(), ['broker_type' => $brokerType, 'account_uid' => $accountUid]));

        $validated = $this->validateOrderPayload($request);
        $clientOrderId = $validated['client_order_id'] ?? null;

        if ($clientOrderId) {
            $existingOrder = DB::connection('mongodb')
                ->table('futures_orders')
                ->where('user_uid', $user['uid'])
                ->where('account_uid', $accountUid)
                ->where('mode', $mode)
                ->where('client_order_id', $clientOrderId)
                ->first();

            if ($existingOrder) {
                $existingTrade = DB::connection('mongodb')
                    ->table('futures_trades')
                    ->where('order_uid', $existingOrder->order_uid)
                    ->first();

                $existingPosition = DB::connection('mongodb')
                    ->table('futures_positions')
                    ->where('order_uid', $existingOrder->order_uid)
                    ->first();

                return response()->json([
                    'success' => true,
                    'message' => ucfirst($mode) . ' order already processed.',
                    'data' => [
                        'order' => (array) $existingOrder,
                        'trade' => $existingTrade ? (array) $existingTrade : null,
                        'position' => $existingPosition ? (array) $existingPosition : null,
                        'wallet' => $walletService->getOrCreateWallet($user['uid']),
                    ],
                ]);
            }
        }

        if ((bool) ($validated['reduce_only'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Reduce-only close order will be added in the next step.',
            ], 422);
        }

        $calculation = $this->calculateOrder($validated, $user['uid']);

        $orderUid = (string) Str::uuid();
        
        $status = 'open';
        if ($validated['order_type'] === 'market') {
            $status = 'filled';
        }

        $incomingSide = strtolower($calculation['side']);
        $oppositeSide = ($incomingSide === 'buy') ? 'sell' : 'buy';
        $orderQty = (float) $calculation['estimated_quantity'];
        $execPrice = (float) ($calculation['execution_price'] ?? $calculation['market_price'] ?? 0);

        $oppositePositions = collect();
        $totalOppositeQty = 0.0;
        if ($brokerType !== 'mt5') {
            $oppositePositions = DB::connection('mongodb')
                ->table('futures_positions')
                ->where('user_uid', $user['uid'])
                ->where('mode', $mode)
                ->where('symbol', $calculation['symbol'])
                ->where('side', $oppositeSide)
                ->where('status', 'open')
                ->where(function ($q) use ($accountUid) {
                    if (!empty($accountUid)) {
                        $q->where('account_uid', $accountUid)
                          ->orWhereNull('account_uid')
                          ->orWhere('account_uid', '');
                    }
                })
                ->get();
            $totalOppositeQty = (float) $oppositePositions->sum(fn ($p) => (float) ($p->quantity ?? 0));
        }

        $closePortionQty = min($orderQty, $totalOppositeQty);
        $newPortionQty = max(0.0, round($orderQty - $totalOppositeQty, 8));

        // Margin required: only lock margin for the portion of the order that opens/expands a position
        $marginToLock = ($orderQty > 0 && $newPortionQty > 0)
            ? round((float) $calculation['margin'] * ($newPortionQty / $orderQty), 8)
            : 0.0;

        $feeToChargeNow = (float) $calculation['estimated_fee'];
        $currency = strtoupper($validated['currency'] ?? $request->input('currency', 'USDT'));
        if ($currency === 'USD' || $currency !== 'INR') {
            $currency = 'USDT';
        }

        if ($marginToLock > 0 || $feeToChargeNow > 0) {
            if ($mode === 'live') {
                $wallet = $walletService->lockRealMargin(
                    $user['uid'],
                    $marginToLock,
                    $feeToChargeNow,
                    $orderUid,
                    'futures',
                    $accountUid,
                    $currency
                );
            } else {
                $wallet = $walletService->lockDemoMargin(
                    $user['uid'],
                    $marginToLock,
                    $feeToChargeNow,
                    $orderUid,
                    'futures',
                    $accountUid,
                    $currency
                );
            }
        } else {
            $wallet = $walletService->getOrCreateWallet($user['uid']);
        }

        $order = [
            'order_uid' => $orderUid,
            'user_uid' => $user['uid'],
            'account_uid' => $accountUid,
            'broker_type' => $brokerType,
            'mode' => $mode,
            'client_order_id' => $clientOrderId,
            'symbol' => $calculation['symbol'],
            'side' => $calculation['side'],
            'order_type' => $calculation['order_type'],
            'price' => $validated['price'] ?? null,
            'stop_price' => $validated['stop_price'] ?? null,
            'execution_price' => $calculation['execution_price'],
            'margin' => $calculation['margin'],
            'leverage' => $calculation['leverage'],
            'notional_value' => $calculation['notional_value'],
            'estimated_quantity' => $calculation['estimated_quantity'],
            'estimated_fee_rate' => $calculation['estimated_fee_rate'],
            'estimated_fee' => $calculation['estimated_fee'],
            'fee_charged' => $feeToChargeNow,
            'take_profit' => $calculation['take_profit'],
            'stop_loss' => $calculation['stop_loss'],
            'margin_mode' => strtolower($validated['margin_mode'] ?? $calculation['margin_mode'] ?? 'isolated'),
            'reduce_only' => false,
            'currency' => $currency,
            'status' => $status,
            'exchange_order_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')
            ->table('futures_orders')
            ->insert($order);

        try {
            $settings = DB::connection('mongodb')
                ->table('admin_settings')
                ->where('key', 'platform')
                ->first();

            $provider = strtolower($settings->active_exchange_provider ?? 'binance');

            if ($brokerType === 'mt5') {
                // ==========================================
                // STRICT MT5 BROKER EXECUTION ROUTE
                // (Never routes to Binance or Bybit)
                // ==========================================
                $metaApiService = app(\App\Services\MetaApiService::class);
                $accountId = $this->getMt5AccountId($user['uid']);

                if ($metaApiService->isEnabled() && !empty($accountId) && $metaApiService->isValidAccountId($accountId)) {
                    $isMarket = in_array(strtolower($validated['order_type'] ?? 'market'), ['market', 'stop_market'], true);
                    $side = strtolower($calculation['side']) === 'buy' ? 'BUY' : 'SELL';
                    $actionType = $isMarket ? "ORDER_TYPE_{$side}" : "ORDER_TYPE_{$side}_LIMIT";

                    $orderParams = [
                        'actionType' => $actionType,
                        'symbol' => (string) $calculation['symbol'],
                        'volume' => max(0.01, round((float) $calculation['estimated_quantity'], 2)),
                        'comment' => 'Kryvex ' . substr($orderUid, 0, 8),
                    ];

                    if (!$isMarket && !empty($validated['price'])) {
                        $orderParams['openPrice'] = (float) $validated['price'];
                    }
                    if (!empty($calculation['take_profit'])) {
                        $orderParams['takeProfit'] = (float) $calculation['take_profit'];
                    }
                    if (!empty($calculation['stop_loss'])) {
                        $orderParams['stopLoss'] = (float) $calculation['stop_loss'];
                    }

                    $mt5Res = $metaApiService->placeOrder($orderParams, $accountId);
                    Log::info("place{$mode} MetaApi MT5 Response", ['symbol' => $calculation['symbol'], 'accountId' => $accountId, 'res' => $mt5Res]);

                    if (!empty($mt5Res['orderId'])) {
                        $exchangeOrderId = (string) $mt5Res['orderId'];
                        DB::connection('mongodb')
                            ->table('futures_orders')
                            ->where('order_uid', $orderUid)
                            ->update(['exchange_order_id' => $exchangeOrderId]);
                        $order['exchange_order_id'] = $exchangeOrderId;
                    }
                } else {
                    Log::info("place{$mode} MT5 order recorded locally (No active MetaApi MT5 bridge connected yet).");
                }
            } else {
                // ==========================================
                // STRICT KRYVEX BROKER ROUTE (Exchange Provider)
                // (Routes only to Binance or Bybit, never MT5)
                // ==========================================
                if ($provider === 'binance') {
                    $binanceService = app(\App\Services\BinanceService::class);
                    if ($binanceService->isEnabled()) {
                        $marginMode = $validated['margin_mode'] ?? $calculation['margin_mode'] ?? 'isolated';
                        $leverage = (int) ($validated['leverage'] ?? $calculation['leverage'] ?? 10);

                        $binanceService->setMarginType((string) $calculation['symbol'], (string) $marginMode);
                        $binanceService->setLeverage((string) $calculation['symbol'], $leverage);

                        $isMarket = in_array(strtolower($validated['order_type'] ?? 'market'), ['market', 'stop_market'], true);
                        $orderPrice = $isMarket ? null : (!empty($validated['price']) ? (float) $validated['price'] : null);
                        $estPrice = (float) ($calculation['market_price'] ?? $calculation['execution_price'] ?? 0);
                        $binanceOrderParams = $binanceService->formatOrderPayload(
                            (string) $calculation['symbol'],
                            (string) $calculation['side'],
                            $isMarket ? 'MARKET' : 'LIMIT',
                            (float) $calculation['estimated_quantity'],
                            $orderPrice,
                            $estPrice
                        );

                        $binanceRes = $binanceService->placeOrder($binanceOrderParams);
                        Log::info("place{$mode} Binance Response", ['symbol' => $calculation['symbol'], 'res' => $binanceRes]);

                        if ($binanceRes['success'] ?? false) {
                            $exchangeOrderId = (string) ($binanceRes['data']['orderId'] ?? '');
                            if ($exchangeOrderId) {
                                DB::connection('mongodb')
                                    ->table('futures_orders')
                                    ->where('order_uid', $orderUid)
                                    ->update(['exchange_order_id' => $exchangeOrderId]);
                                $order['exchange_order_id'] = $exchangeOrderId;
                            }
                        }
                    }
                } else {
                    $bybitService = app(\App\Services\BybitService::class);
                    if ($bybitService->isEnabled()) {
                        $bybitRes = $bybitService->placeOrder([
                            'symbol' => $calculation['symbol'],
                            'side' => ucfirst(strtolower($calculation['side'])),
                            'orderType' => ucfirst(strtolower($calculation['order_type'])),
                            'qty' => (string) $calculation['estimated_quantity'],
                            'price' => !empty($validated['price']) ? (string) $validated['price'] : null,
                        ]);

                        if ($bybitRes['success'] ?? false) {
                            $exchangeOrderId = (string) ($bybitRes['data']['orderId'] ?? '');
                            if ($exchangeOrderId) {
                                DB::connection('mongodb')
                                    ->table('futures_orders')
                                    ->where('order_uid', $orderUid)
                                    ->update(['exchange_order_id' => $exchangeOrderId]);
                                $order['exchange_order_id'] = $exchangeOrderId;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $ex) {
            Log::warning('Exchange placeOrder dispatch error: ' . $ex->getMessage());
        }

        $trade = null;
        $position = null;

        if ($status === 'filled') {
            $trade = $this->createTrade($order, $mode);

            // One-Way Netting for Kryvex: reduce or close existing opposite position(s)
            if ($closePortionQty > 0 && $brokerType !== 'mt5') {
                $remainingToClose = $closePortionQty;
                foreach ($oppositePositions as $oppPos) {
                    $posArr = (array) $oppPos;
                    $pQty = (float) ($posArr['quantity'] ?? 0);
                    $pMargin = (float) ($posArr['margin'] ?? 0);
                    $pEntry = (float) ($posArr['entry_price'] ?? 0);
                    $reduceQty = min($remainingToClose, $pQty);
                    if ($reduceQty <= 0) continue;

                    // Realized PnL on the closed portion:
                    $realizedPnl = ($oppositeSide === 'buy')
                        ? ($execPrice - $pEntry) * $reduceQty  // had Buy, now Sold
                        : ($pEntry - $execPrice) * $reduceQty; // had Sell, now Bought
                    $realizedPnl = round($realizedPnl, 4);

                    $marginToRelease = ($pQty > 0) ? round($pMargin * ($reduceQty / $pQty), 8) : 0.0;
                    $closeFeeRate = $walletService->tradingFeeRate('futures');
                    $closeFee = round(($reduceQty * $execPrice) * $closeFeeRate, 4);

                    if ($mode === 'live') {
                        $wallet = $walletService->settleClosedRealPosition(
                            $user['uid'],
                            $marginToRelease,
                            $realizedPnl,
                            $closeFee,
                            $orderUid,
                            'futures',
                            $accountUid,
                            $currency
                        );
                    } else {
                        $wallet = $walletService->settleClosedDemoPosition(
                            $user['uid'],
                            $marginToRelease,
                            $realizedPnl,
                            $closeFee,
                            $orderUid,
                            'futures',
                            $accountUid,
                            $currency
                        );
                    }

                    if ($reduceQty >= $pQty - 0.00000001) {
                        DB::connection('mongodb')->table('futures_positions')
                            ->where('position_uid', $posArr['position_uid'])
                            ->update([
                                'status' => 'closed',
                                'exit_price' => $execPrice,
                                'realized_pnl' => round(((float) ($posArr['realized_pnl'] ?? 0)) + $realizedPnl, 4),
                                'closed_at' => now(),
                                'updated_at' => now(),
                            ]);
                    } else {
                        $newQty = round($pQty - $reduceQty, 8);
                        $newMargin = round($pMargin - $marginToRelease, 8);
                        DB::connection('mongodb')->table('futures_positions')
                            ->where('position_uid', $posArr['position_uid'])
                            ->update([
                                'quantity' => $newQty,
                                'margin' => $newMargin,
                                'notional_value' => round($newQty * $pEntry, 8),
                                'realized_pnl' => round(((float) ($posArr['realized_pnl'] ?? 0)) + $realizedPnl, 4),
                                'updated_at' => now(),
                            ]);
                    }

                    $remainingToClose -= $reduceQty;
                }
            }

            // Only create/expand position if there was no opposite position or leftover quantity ($newPortionQty > 0)
            if ($newPortionQty > 0 || $totalOppositeQty <= 0) {
                $orderForPos = $order;
                if ($totalOppositeQty > 0) {
                    $orderForPos['estimated_quantity'] = $newPortionQty;
                    $orderForPos['margin'] = $marginToLock;
                    $orderForPos['notional_value'] = round($newPortionQty * $execPrice, 8);
                }
                $position = $this->createPosition($orderForPos, $trade, $mode);
            }

            if (!empty($calculation['spread_amount']) && $calculation['spread_amount'] > 0) {
                \App\Helpers\SpreadHelper::recordSpreadRevenue(
                    $user['uid'],
                    $orderUid,
                    $calculation['symbol'],
                    $calculation['side'],
                    (float) $calculation['market_price'],
                    (float) $calculation['execution_price'],
                    (float) $calculation['spread_amount'],
                    (float) $calculation['quantity']
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => $status === 'filled'
                ? ucfirst($mode) . ' order filled successfully.'
                : ucfirst($mode) . ' order placed successfully.',
            'data' => [
                'order' => $order,
                'trade' => $trade,
                'position' => $position,
                'wallet' => $wallet,
            ],
        ], 201);
    }

    public function orders(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid']);
        $status = $request->query('status');

        Log::info('Futures orders request', [
            'user_uid' => $user['uid'] ?? null,
            'account_uid' => $accountUid,
            'status' => $status,
            'query_params' => $request->all(),
        ]);

        $userRecord = DB::connection('mongodb')->table('users')->where('_id', $user['uid'])->orWhere('uid', $user['uid'])->first();
        $isMt5 = strtolower($userRecord->broker_type ?? '') === 'mt5';

        $mode = strtolower((string) ($request->header('X-Trading-Mode') ?: ($request->query('mode') ?: $request->input('mode', 'demo'))));
        if (!in_array($mode, ['live', 'demo'], true)) {
            $mode = 'demo';
        }

        $query = DB::connection('mongodb')
            ->table('futures_orders')
            ->where('user_uid', $user['uid']);

        if ($mode === 'live') {
            $query->where('mode', 'live');
        } else {
            $query->where(function ($q) {
                $q->where('mode', 'demo')
                  ->orWhereNull('mode')
                  ->orWhere('mode', '');
            });
        }

        if (!$isMt5) {
            $query->where(function ($q) use ($accountUid, $user) {
                $q->where('account_uid', $accountUid)
                  ->orWhere('account_uid', $user['uid'])
                  ->orWhereNull('account_uid')
                  ->orWhere('account_uid', '');
            });
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->has('page') || $request->query('paginate') === 'true') {
            $orders = $query->orderByDesc('created_at')->paginate(10);
            return response()->json([
                'success' => true,
                'message' => 'Futures orders fetched successfully.',
                'data' => [
                    'orders' => $orders->items(),
                    'pagination' => [
                        'current_page' => $orders->currentPage(),
                        'last_page' => $orders->lastPage(),
                        'per_page' => $orders->perPage(),
                        'total' => $orders->total(),
                    ]
                ],
            ]);
        }

        $orders = $query
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Futures orders fetched successfully.',
            'data' => [
                'orders' => $orders,
            ],
        ]);
    }

    public function positions(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $reqAcc = $request->header('X-Account-UID') ?: $request->input('account_uid');
        $brokerType = $this->resolveBrokerType($request, $user['uid'], $reqAcc);
        $isMt5 = ($brokerType === 'mt5');

        $mode = strtolower((string) ($request->header('X-Trading-Mode') ?: ($request->query('mode') ?: $request->input('mode', 'demo'))));
        if (!in_array($mode, ['live', 'demo'], true)) {
            $mode = 'demo';
        }

        $query = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $user['uid'])
            ->where('status', 'open');

        if ($mode === 'live') {
            $query->where('mode', 'live');
        } else {
            $query->where(function ($q) {
                $q->where('mode', 'demo')
                  ->orWhereNull('mode')
                  ->orWhere('mode', '');
            });
        }

        if (!$isMt5) {
            if (!empty($reqAcc) && $reqAcc !== 'all') {
                $query->where(function ($q) use ($reqAcc, $user) {
                    $q->where('account_uid', $reqAcc)
                      ->orWhere('account_uid', $user['uid'])
                      ->orWhereNull('account_uid')
                      ->orWhere('account_uid', '');
                });
            }
        }

        $rawPositions = $query->orderByDesc('created_at')
            ->limit(100)
            ->get();

        if ($isMt5) {
            $positions = $rawPositions;
        } else {
            // Group positions by contract (symbol + currency) and net Buy vs Sell (Binance One-Way Mode)
            $symbolBuckets = [];
            foreach ($rawPositions as $pos) {
                $p = (array) $pos;
                $symbol = strtoupper((string) ($p['symbol'] ?? ''));
                $currency = strtoupper((string) ($p['currency'] ?? 'USDT'));
                $side = strtolower((string) ($p['side'] ?? 'buy'));
                $bucketKey = "{$symbol}_{$currency}";

                if (!isset($symbolBuckets[$bucketKey])) {
                    $symbolBuckets[$bucketKey] = ['buy' => [], 'sell' => []];
                }
                $symbolBuckets[$bucketKey][$side][] = $p;
            }

            $positions = [];
            foreach ($symbolBuckets as $bucketKey => $sides) {
                $buyList = $sides['buy'];
                $sellList = $sides['sell'];

                // Calculate total buy quantity and weighted average entry
                $buyQty = 0.0;
                $buyNotional = 0.0;
                $buyMargin = 0.0;
                $buyFee = 0.0;
                $buyUids = [];
                $lastBuy = null;
                foreach ($buyList as $b) {
                    $q = (float) ($b['quantity'] ?? 0);
                    $e = (float) ($b['entry_price'] ?? 0);
                    $buyQty += $q;
                    $buyNotional += ($q * $e);
                    $buyMargin += (float) ($b['margin'] ?? 0);
                    $buyFee += (float) ($b['fee_paid'] ?? 0);
                    $buyUids[] = $b['position_uid'];
                    $lastBuy = $b;
                }
                $buyAvgEntry = $buyQty > 0 ? round($buyNotional / $buyQty, 8) : 0.0;

                // Calculate total sell quantity and weighted average entry
                $sellQty = 0.0;
                $sellNotional = 0.0;
                $sellMargin = 0.0;
                $sellFee = 0.0;
                $sellUids = [];
                $lastSell = null;
                foreach ($sellList as $s) {
                    $q = (float) ($s['quantity'] ?? 0);
                    $e = (float) ($s['entry_price'] ?? 0);
                    $sellQty += $q;
                    $sellNotional += ($q * $e);
                    $sellMargin += (float) ($s['margin'] ?? 0);
                    $sellFee += (float) ($s['fee_paid'] ?? 0);
                    $sellUids[] = $s['position_uid'];
                    $lastSell = $s;
                }
                $sellAvgEntry = $sellQty > 0 ? round($sellNotional / $sellQty, 8) : 0.0;

                $netQty = round($buyQty - $sellQty, 8);
                $allUids = array_merge($buyUids, $sellUids);
                $fillCount = count($allUids);

                if ($netQty > 0.00000001 && $lastBuy) {
                    // Net LONG position
                    $ratio = $buyQty > 0 ? ($netQty / $buyQty) : 1.0;
                    $pos = $lastBuy;
                    $pos['side'] = 'buy';
                    $pos['quantity'] = $netQty;
                    $pos['entry_price'] = $buyAvgEntry;
                    $pos['margin'] = round($buyMargin * $ratio, 8);
                    $pos['notional_value'] = round($netQty * $buyAvgEntry, 8);
                    $pos['fee_paid'] = round($buyFee + $sellFee, 8);
                    $pos['sub_position_uids'] = $allUids;
                    $pos['fill_count'] = $fillCount;
                    $pos['is_aggregated'] = $fillCount > 1;
                    $positions[] = $pos;
                } elseif ($netQty < -0.00000001 && $lastSell) {
                    // Net SHORT position
                    $absNetQty = abs($netQty);
                    $ratio = $sellQty > 0 ? ($absNetQty / $sellQty) : 1.0;
                    $pos = $lastSell;
                    $pos['side'] = 'sell';
                    $pos['quantity'] = $absNetQty;
                    $pos['entry_price'] = $sellAvgEntry;
                    $pos['margin'] = round($sellMargin * $ratio, 8);
                    $pos['notional_value'] = round($absNetQty * $sellAvgEntry, 8);
                    $pos['fee_paid'] = round($buyFee + $sellFee, 8);
                    $pos['sub_position_uids'] = $allUids;
                    $pos['fill_count'] = $fillCount;
                    $pos['is_aggregated'] = $fillCount > 1;
                    $positions[] = $pos;
                }
                // If netQty == 0, both sides cancelled each other out, no open position!
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Futures positions fetched successfully.',
            'data' => [
                'positions' => $positions,
            ],
        ]);
    }

    public function realizedPnl(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $accountUid = (string) ($request->query('account_uid') ?: $request->header('X-Account-UID') ?: '');
        $days = max(1, min(365, (int) $request->query('days', 30)));
        $mongo = DB::connection('mongodb');

        $account = $accountUid !== ''
            ? $mongo->table('trading_accounts')->where('user_uid', $user['uid'])->where('account_uid', $accountUid)->first()
            : null;
        if (!$account) {
            return response()->json(['success' => false, 'message' => 'Account not found.'], 404);
        }

        $acc = (array) $account;
        $isDemo = ($acc['account_type'] ?? '') === 'demo' || !empty($acc['is_demo']);
        $isDefault = !empty($acc['is_default']);

        $query = $mongo->table('futures_positions')
            ->where('user_uid', $user['uid'])
            ->where('status', 'closed')
            ->where('closed_at', '>=', now()->subDays($days))
            ->where(function ($q) use ($accountUid, $isDefault, $isDemo) {
                $q->where('account_uid', $accountUid);
                // Positions opened before accounts were tracked belong to the default live account / the demo account.
                if ($isDefault || $isDemo) {
                    $q->orWhere(function ($legacy) use ($isDemo) {
                        $legacy->where(function ($n) {
                            $n->whereNull('account_uid')->orWhere('account_uid', '');
                        })->where('mode', $isDemo ? 'demo' : 'live');
                    });
                }
            });

        $positions = $query->get(['realized_pnl', 'pnl']);

        return response()->json([
            'success' => true,
            'data' => [
                'account_uid' => $accountUid,
                'days' => $days,
                'realized_pnl' => round((float) $positions->sum(fn ($p) => (float) ($p->realized_pnl ?? $p->pnl ?? 0)), 4),
                'closed_count' => $positions->count(),
            ],
        ]);
    }

    public function trades(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid']);

        $userRecord = DB::connection('mongodb')->table('users')->where('_id', $user['uid'])->orWhere('uid', $user['uid'])->first();
        $isMt5 = strtolower($userRecord->broker_type ?? '') === 'mt5';

        $mode = strtolower((string) ($request->header('X-Trading-Mode') ?: ($request->query('mode') ?: $request->input('mode', 'demo'))));
        if (!in_array($mode, ['live', 'demo'], true)) {
            $mode = 'demo';
        }

        $query = DB::connection('mongodb')
            ->table('futures_trades')
            ->where('user_uid', $user['uid']);

        if ($mode === 'live') {
            $query->where('mode', 'live');
        } else {
            $query->where(function ($q) {
                $q->where('mode', 'demo')
                  ->orWhereNull('mode')
                  ->orWhere('mode', '');
            });
        }

        if (!$isMt5) {
            $query->where(function ($q) use ($accountUid, $user) {
                $q->where('account_uid', $accountUid)
                  ->orWhere('account_uid', $user['uid'])
                  ->orWhereNull('account_uid')
                  ->orWhere('account_uid', '');
            });
        }

        if ($request->has('page') || $request->query('paginate') === 'true') {
            $trades = $query->orderByDesc('created_at')->paginate(10);
            return response()->json([
                'success' => true,
                'message' => 'Futures trades fetched successfully.',
                'data' => [
                    'trades' => $trades->items(),
                    'pagination' => [
                        'current_page' => $trades->currentPage(),
                        'last_page' => $trades->lastPage(),
                        'per_page' => $trades->perPage(),
                        'total' => $trades->total(),
                    ]
                ],
            ]);
        }

        $trades = $query
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Futures trades fetched successfully.',
            'data' => [
                'trades' => $trades,
            ],
        ]);
    }

    public function updatePositionTpSl(string $positionUid, Request $request)
    {
        $validated = $request->validate([
            'take_profit' => ['nullable', 'numeric', 'min:0.00000001'],
            'stop_loss' => ['nullable', 'numeric', 'min:0.00000001'],
        ]);

        $user = $request->attributes->get('auth_user');

        $position = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $user['uid'])
            ->where('position_uid', $positionUid)
            ->where('status', 'open')
            ->first();

        if (! $position) {
            return response()->json([
                'success' => false,
                'message' => 'Open position not found.',
            ], 404);
        }

        $updateData = [
            'take_profit' => isset($validated['take_profit']) ? (float) $validated['take_profit'] : null,
            'stop_loss' => isset($validated['stop_loss']) ? (float) $validated['stop_loss'] : null,
            'updated_at' => now(),
        ];

        DB::connection('mongodb')
            ->table('futures_positions')
            ->where('position_uid', $positionUid)
            ->update($updateData);

        $brokerType = $this->getBrokerTypeForOrderOrPosition($position, $user['uid']);
        if ($brokerType !== 'mt5') {
            DB::connection('mongodb')
                ->table('futures_positions')
                ->where('user_uid', $user['uid'])
                ->where('symbol', $position->symbol)
                ->where('side', $position->side)
                ->where('status', 'open')
                ->update($updateData);
        }

        return response()->json([
            'success' => true,
            'message' => 'TP / SL updated successfully.',
            'data' => [
                'position_uid' => $positionUid,
                'take_profit' => $updateData['take_profit'],
                'stop_loss' => $updateData['stop_loss'],
            ],
        ]);
    }

    public function processDemoFills(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'regex:/^[A-Z0-9]+$/'],
            'last_price' => ['required', 'numeric', 'min:0.00000001'],
        ]);

        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid']);

        $symbol = strtoupper($validated['symbol']);
        $lastPrice = (float) $validated['last_price'];

        $openOrders = DB::connection('mongodb')
            ->table('futures_orders')
            ->where('user_uid', $user['uid'])
            ->where('account_uid', $accountUid)
            ->where('symbol', $symbol)
            ->where('status', 'open')
            ->get();

        $filled = [];

        foreach ($openOrders as $openOrder) {
            $order = (array) $openOrder;

            if (! $this->shouldFillOrder($order, $lastPrice)) {
                continue;
            }

            $order['execution_price'] = round((float) ($order['price'] ?: $lastPrice), 8);
            $order['status'] = 'filled';
            $order['updated_at'] = now();

            DB::connection('mongodb')
                ->table('futures_orders')
                ->where('order_uid', $order['order_uid'])
                ->update([
                    'execution_price' => $order['execution_price'],
                    'status' => 'filled',
                    'filled_at' => now(),
                    'updated_at' => now(),
                ]);

            $trade = $this->createDemoTrade($order);
            $position = $this->createDemoPosition($order, $trade);

            // Mirror position opening according to broker
            try {
                $orderBroker = $this->getBrokerTypeForOrderOrPosition($order, $user['uid']);

                if ($orderBroker === 'mt5') {
                    // MT5 fill dispatch (Never Binance or Bybit)
                    $metaApiService = app(\App\Services\MetaApiService::class);
                    if ($metaApiService->isEnabled()) {
                        $accountId = $this->getMt5AccountId($user['uid']);
                        if ($accountId) {
                            if (!empty($order['exchange_order_id'])) {
                                $metaApiService->cancelOrder((string) $order['exchange_order_id'], $accountId);
                            }
                            $side = strtolower($order['side'] ?? 'buy') === 'buy' ? 'ORDER_TYPE_BUY' : 'ORDER_TYPE_SELL';
                            $mt5Res = $metaApiService->placeOrder([
                                'actionType' => $side,
                                'symbol' => (string) ($order['symbol'] ?? 'BTCUSDT'),
                                'volume' => max(0.01, round((float) ($order['estimated_quantity'] ?? $order['quantity'] ?? 0.01), 2)),
                                'comment' => 'Kryvex Fill ' . substr($order['order_uid'] ?? '', 0, 8),
                            ], $accountId);

                            if (!empty($mt5Res['orderId'])) {
                                $newExchangeOrderId = (string) $mt5Res['orderId'];
                                DB::connection('mongodb')
                                    ->table('futures_orders')
                                    ->where('order_uid', $order['order_uid'])
                                    ->update(['exchange_order_id' => $newExchangeOrderId]);
                                $order['exchange_order_id'] = $newExchangeOrderId;
                            }
                        }
                    }
                } else {
                    // Kryvex Broker dispatch (Binance or Bybit, Never MT5)
                    $settings = DB::connection('mongodb')
                        ->table('admin_settings')
                        ->where('key', 'platform')
                        ->first();
                    $provider = strtolower($settings->active_exchange_provider ?? 'binance');

                    if ($provider === 'binance') {
                        $binanceService = app(\App\Services\BinanceService::class);
                        if ($binanceService->isEnabled()) {
                            $orderSymbol = (string) ($order['symbol'] ?? 'BTCUSDT');
                            $orderSide = (string) ($order['side'] ?? 'buy');
                            $orderQty = (float) ($order['estimated_quantity'] ?? $order['quantity'] ?? 0);
                            $marginMode = (string) ($order['margin_mode'] ?? 'isolated');
                            $leverage = (int) ($order['leverage'] ?? 10);

                            // 1. Cancel previous pending limit order on Binance if it was open
                            if (!empty($order['exchange_order_id'])) {
                                $binanceService->cancelOrder($orderSymbol, (string) $order['exchange_order_id']);
                            }

                            // 2. Set Leverage & Margin Mode
                            $binanceService->setMarginType($orderSymbol, $marginMode);
                            $binanceService->setLeverage($orderSymbol, $leverage);

                            // 3. Immediately execute MARKET order on Binance so Binance opens the position
                            $marketPayload = $binanceService->formatOrderPayload(
                                $orderSymbol,
                                $orderSide,
                                'MARKET',
                                $orderQty,
                                null,
                                (float) ($order['execution_price'] ?? $lastPrice)
                            );
                            $binanceRes = $binanceService->placeOrder($marketPayload);
                            Log::info('processDemoFills Binance Market Fill Response', ['symbol' => $orderSymbol, 'res' => $binanceRes]);

                            if (!empty($binanceRes['data']['orderId'])) {
                                $newExchangeOrderId = (string) $binanceRes['data']['orderId'];
                                DB::connection('mongodb')
                                    ->table('futures_orders')
                                    ->where('order_uid', $order['order_uid'])
                                    ->update(['exchange_order_id' => $newExchangeOrderId]);
                                $order['exchange_order_id'] = $newExchangeOrderId;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('processDemoFills exchange fill dispatch error: ' . $e->getMessage());
            }

            $filled[] = [
                'order' => $order,
                'trade' => $trade,
                'position' => $position,
            ];
        }

        return response()->json([
            'success' => true,
            'message' => count($filled) . ' demo orders filled.',
            'data' => [
                'filled' => $filled,
            ],
        ]);
    }

    private function shouldFillOrder(array $order, float $lastPrice): bool
    {
        $side = $order['side'] ?? 'buy';
        $type = $order['order_type'] ?? 'limit';
        $price = (float) ($order['price'] ?? 0);
        $stopPrice = (float) ($order['stop_price'] ?? 0);

        if ($type === 'limit') {
            return $side === 'buy'
                ? $lastPrice <= $price
                : $lastPrice >= $price;
        }

        if ($type === 'stop_market') {
            return $side === 'buy'
                ? $lastPrice >= $stopPrice
                : $lastPrice <= $stopPrice;
        }

        if ($type === 'stop_limit') {
            $stopTriggered = $side === 'buy'
                ? $lastPrice >= $stopPrice
                : $lastPrice <= $stopPrice;

            if (! $stopTriggered) {
                return false;
            }

            return $side === 'buy'
                ? $lastPrice <= $price
                : $lastPrice >= $price;
        }

        return false;
    }

    public function cancelDemoOrder(string $orderUid, Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');

        $order = DB::connection('mongodb')
            ->table('futures_orders')
            ->where('user_uid', $user['uid'])
            ->where('order_uid', $orderUid)
            ->first();

        if (! $order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.',
            ], 404);
        }

        if (($order->status ?? null) !== 'open') {
            return response()->json([
                'success' => false,
                'message' => 'Only open orders can be cancelled.',
            ], 422);
        }

        DB::connection('mongodb')
            ->table('futures_orders')
            ->where('order_uid', $orderUid)
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

        try {
            $orderBroker = $this->getBrokerTypeForOrderOrPosition($order, $user['uid']);

            if ($orderBroker === 'mt5') {
                // MT5 cancel order (Never Binance or Bybit)
                $metaApiService = app(\App\Services\MetaApiService::class);
                $accountId = $this->getMt5AccountId($user['uid']);
                if ($metaApiService->isEnabled() && !empty($accountId) && !empty($order->exchange_order_id)) {
                    $metaApiService->cancelOrder((string) $order->exchange_order_id, $accountId);
                }
            } else {
                // Kryvex Broker cancel order (Binance or Bybit, Never MT5)
                $settings = DB::connection('mongodb')
                    ->table('admin_settings')
                    ->where('key', 'platform')
                    ->first();
                $provider = strtolower($settings->active_exchange_provider ?? 'binance');
                if ($provider === 'binance') {
                    $binanceService = app(\App\Services\BinanceService::class);
                    if (!empty($order->exchange_order_id)) {
                        $binanceService->cancelOrder($order->symbol ?? 'BTCUSDT', (string) $order->exchange_order_id);
                    } else {
                        $binanceService->cancelAllOpenOrders($order->symbol ?? 'BTCUSDT');
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Exchange cancel order error: ' . $e->getMessage());
        }

        $orderArray = (array) $order;
        $accountUid = $orderArray['account_uid'] ?? $this->getActiveAccountUid($request, $user['uid']);
        $currency = strtoupper($orderArray['currency'] ?? 'USDT');
        if ($currency === 'USD') {
            $currency = 'USDT';
        }

        $isLive = strtolower($orderArray['mode'] ?? '') === 'live';

        if ($isLive) {
            $wallet = $walletService->releaseRealMargin(
                $user['uid'],
                (float) ($orderArray['margin'] ?? 0),
                $orderUid,
                'futures',
                $accountUid,
                $currency
            );
        } else {
            $wallet = $walletService->releaseDemoMargin(
                $user['uid'],
                (float) ($orderArray['margin'] ?? 0),
                $orderUid,
                'futures',
                $accountUid,
                $currency
            );
        }

        return response()->json([
            'success' => true,
            'message' => ($isLive ? 'Live' : 'Demo') . ' order cancelled and margin released.',
            'data' => [
                'order_uid' => $orderUid,
                'wallet' => $wallet,
            ],
        ]);
    }

    public function closeDemoPosition(string $positionUid, Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');
        $positionArray = $this->claimOpenFuturesPosition($user['uid'], $positionUid);

        if (! $positionArray) {
            $existingPosition = DB::connection('mongodb')
                ->table('futures_positions')
                ->where('user_uid', $user['uid'])
                ->where('position_uid', $positionUid)
                ->first();

            $existingStatus = $existingPosition->status ?? null;

            if (in_array($existingStatus, ['closing', 'closed'], true)) {
                return response()->json([
                    'success' => true,
                    'message' => $existingStatus === 'closed'
                        ? 'Position already closed.'
                        : 'Position close is already processing.',
                    'data' => [
                        'position_uid' => $positionUid,
                        'status' => $existingStatus,
                        'already_processing' => $existingStatus === 'closing',
                        'already_closed' => $existingStatus === 'closed',
                        'wallet' => $walletService->getOrCreateWallet($user['uid']),
                    ],
                ], $existingStatus === 'closed' ? 200 : 202);
            }

            return response()->json([
                'success' => false,
                'message' => 'Open position not found.',
            ], 404);
        }

        $exitPrice = $request->input('exit_price');
        if (! $exitPrice || (float) $exitPrice <= 0) {
            $exitPrice = $positionArray['mark_price'] ?? $positionArray['entry_price'] ?? $positionArray['price'] ?? 100;
        }

        $closed = $this->settleClaimedFuturesPosition(
            $user['uid'],
            $positionUid,
            $positionArray,
            round((float) $exitPrice, 8),
            $walletService
        );

        $brokerType = $this->getBrokerTypeForOrderOrPosition($positionArray, $user['uid']);
        if ($brokerType !== 'mt5') {
            $otherPositions = DB::connection('mongodb')
                ->table('futures_positions')
                ->where('user_uid', $user['uid'])
                ->where('symbol', $positionArray['symbol'])
                ->where('side', $positionArray['side'])
                ->where('mode', $positionArray['mode'] ?? 'demo')
                ->where('position_uid', '!=', $positionUid)
                ->where('status', 'open')
                ->get();

            foreach ($otherPositions as $otherPos) {
                $otherArr = $this->claimOpenFuturesPosition($user['uid'], $otherPos->position_uid);
                if ($otherArr) {
                    $this->settleClaimedFuturesPosition(
                        $user['uid'],
                        $otherPos->position_uid,
                        $otherArr,
                        round((float) $exitPrice, 8),
                        $walletService
                    );
                }
            }
        }

        $isLive = strtolower($positionArray['mode'] ?? '') === 'live';

        return response()->json([
            'success' => true,
            'message' => ($isLive ? 'Live' : 'Demo') . ' position closed successfully.',
            'data' => $closed,
        ]);
    }

    public function closeAllDemoPositions(Request $request, DemoWalletService $walletService)
    {
        $validated = $request->validate([
            'exit_price' => ['nullable', 'numeric', 'min:0.00000001'],
            'exit_prices' => ['nullable', 'array'],
            'exit_prices.*' => ['numeric', 'min:0.00000001'],
        ]);

        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid']);
        $fallbackExitPrice = isset($validated['exit_price']) ? (float) $validated['exit_price'] : null;
        $exitPrices = $validated['exit_prices'] ?? [];

        $mode = strtolower((string) ($request->header('X-Trading-Mode') ?: ($request->input('mode') ?: ($request->query('mode') ?: 'demo'))));
        if (!in_array($mode, ['live', 'demo'], true)) {
            $mode = 'demo';
        }

        $query = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $user['uid'])
            ->where('status', 'open')
            ->where(function ($q) use ($accountUid, $user) {
                $q->where('account_uid', $accountUid)
                  ->orWhere('account_uid', $user['uid'])
                  ->orWhereNull('account_uid')
                  ->orWhere('account_uid', '');
            });

        if ($mode === 'live') {
            $query->where('mode', 'live');
        } else {
            $query->where(function ($q) {
                $q->where('mode', 'demo')
                  ->orWhereNull('mode')
                  ->orWhere('mode', '');
            });
        }

        $positions = $query->get();

        $closedPositions = [];
        $closeOrders = [];
        $closeTrades = [];
        $wallet = null;

        foreach ($positions as $position) {
            $uid = (string) ($position->position_uid ?? '');
            $exitPrice = (float) ($exitPrices[$uid] ?? $fallbackExitPrice ?? 0);

            if (! $uid || $exitPrice <= 0) {
                continue;
            }

            $positionArray = $this->claimOpenFuturesPosition($user['uid'], $uid);

            if (! $positionArray) {
                continue;
            }

            $closed = $this->settleClaimedFuturesPosition(
                $user['uid'],
                $uid,
                $positionArray,
                round($exitPrice, 8),
                $walletService
            );

            $closedPositions[] = $closed['position_uid'];
            $closeOrders[] = $closed['close_order'];
            $closeTrades[] = $closed['close_trade'];
            $wallet = $closed['wallet'];
        }

        return response()->json([
            'success' => true,
            'message' => count($closedPositions) . ($mode === 'live' ? ' live' : ' demo') . ' positions closed successfully.',
            'data' => [
                'closed' => count($closedPositions),
                'closed_positions' => $closedPositions,
                'close_orders' => $closeOrders,
                'close_trades' => $closeTrades,
                'wallet' => $wallet ?: $walletService->getOrCreateWallet($user['uid']),
            ],
        ]);
    }

    private function claimOpenFuturesPosition(string $userUid, string $positionUid): ?array
    {
        $claimed = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $userUid)
            ->where('position_uid', $positionUid)
            ->where('status', 'open')
            ->update([
                'status' => 'closing',
                'closing_started_at' => now(),
                'updated_at' => now(),
            ]);

        if (! $claimed) {
            return null;
        }

        $position = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('user_uid', $userUid)
            ->where('position_uid', $positionUid)
            ->where('status', 'closing')
            ->first();

        return $position ? (array) $position : null;
    }

    private function settleClaimedFuturesPosition(
        string $userUid,
        string $positionUid,
        array $positionArray,
        float $exitPriceRaw,
        DemoWalletService $walletService
    ): array {
        $entryPrice = (float) ($positionArray['entry_price'] ?? 0);
        $quantity = (float) ($positionArray['quantity'] ?? 0);
        $margin = (float) ($positionArray['margin'] ?? 0);
        $side = $positionArray['side'] ?? 'buy';

        $closingSide = $side === 'buy' ? 'sell' : 'buy';
        $exitPrice = round($exitPriceRaw, 8);

        $spreadPrices = \App\Helpers\SpreadHelper::calculatePrices($exitPriceRaw, $userUid);
        $spreadAmount = $spreadPrices['spread_amount'];
        $closingExecutionPrice = $closingSide === 'sell' ? $spreadPrices['sell_price'] : $spreadPrices['buy_price'];

        $realizedPnl = $side === 'buy'
            ? ($exitPrice - $entryPrice) * $quantity
            : ($entryPrice - $exitPrice) * $quantity;

        $realizedPnl = round($realizedPnl, 4);

        $closeNotional = round($exitPrice * $quantity, 4);
        $closeFeeRate = $walletService->tradingFeeRate('futures');
        $closeFee = round($closeNotional * $closeFeeRate, 4);

        $closeOrderUid = (string) Str::uuid();
        $closeTradeUid = (string) Str::uuid();

        try {
            $posBroker = $this->getBrokerTypeForOrderOrPosition($positionArray, $userUid);

            if ($posBroker === 'mt5') {
                // MT5 position close (Never Binance or Bybit)
                $metaApiService = app(\App\Services\MetaApiService::class);
                $accountId = $this->getMt5AccountId($userUid);
                if ($metaApiService->isEnabled() && !empty($accountId)) {
                    // Look up open MT5 positions for this symbol
                    $openPositions = $metaApiService->getPositions($accountId);
                    $targetSymbol = strtoupper(str_replace('/', '', $positionArray['symbol'] ?? 'EURUSD'));
                    foreach ($openPositions as $pos) {
                        if (strtoupper($pos['symbol'] ?? '') === $targetSymbol && !empty($pos['id'])) {
                            $metaApiService->closePosition((string) $pos['id'], $accountId);
                        }
                    }
                }
            } else {
                // Kryvex Broker position close (Binance or Bybit, Never MT5)
                $settings = DB::connection('mongodb')
                    ->table('admin_settings')
                    ->where('key', 'platform')
                    ->first();
                $provider = strtolower($settings->active_exchange_provider ?? 'binance');

                if ($provider === 'binance') {
                    $binanceService = app(\App\Services\BinanceService::class);
                    if ($binanceService->isEnabled()) {
                        $binanceService->closePosition(
                            (string) ($positionArray['symbol'] ?? 'BTCUSDT'),
                            (string) $closingSide,
                            (float) $quantity
                        );
                    }
                } else {
                    $bybitService = app(\App\Services\BybitService::class);
                    if ($bybitService->isEnabled()) {
                        $bybitService->placeOrder([
                            'symbol' => strtoupper($positionArray['symbol'] ?? 'BTCUSDT'),
                            'side' => ucfirst(strtolower($closingSide)),
                            'orderType' => 'Market',
                            'qty' => (string) $quantity,
                            'reduceOnly' => true,
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Exchange position close dispatch error: ' . $e->getMessage());
        }

        // Record Spread Revenue for position closure
        \App\Helpers\SpreadHelper::recordSpreadRevenue(
            $userUid,
            $closeOrderUid,
            $positionArray['symbol'] ?? 'BTCUSDT',
            $closingSide,
            $exitPriceRaw,
            $closingExecutionPrice,
            $spreadAmount,
            $quantity
        );

        $accountUid = $positionArray['account_uid'] ?? null;
        $currency = strtoupper($positionArray['currency'] ?? 'USDT');
        if ($currency === 'USD') {
            $currency = 'USDT';
        }

        $isLive = strtolower($positionArray['mode'] ?? '') === 'live';
        $positionMode = $isLive ? 'live' : 'demo';

        if ($isLive) {
            $wallet = $walletService->settleClosedRealPosition(
                $userUid,
                $margin,
                $realizedPnl,
                $closeFee,
                $closeOrderUid,
                'futures',
                $accountUid,
                $currency
            );
        } else {
            $wallet = $walletService->settleClosedDemoPosition(
                $userUid,
                $margin,
                $realizedPnl,
                $closeFee,
                $closeOrderUid,
                'futures',
                $accountUid,
                $currency
            );
        }

        $closeOrder = [
            'order_uid' => $closeOrderUid,
            'user_uid' => $userUid,
            'account_uid' => $accountUid,
            'mode' => $positionMode,
            'symbol' => $positionArray['symbol'],
            'side' => $closingSide,
            'order_type' => 'market',
            'price' => null,
            'stop_price' => null,
            'execution_price' => $exitPrice,
            'margin' => 0.0,
            'leverage' => (int) ($positionArray['leverage'] ?? 1),
            'notional_value' => $closeNotional,
            'estimated_quantity' => $quantity,
            'estimated_fee_rate' => $closeFeeRate,
            'estimated_fee' => $closeFee,
            'fee_charged' => $closeFee,
            'take_profit' => null,
            'stop_loss' => null,
            'reduce_only' => true,
            'currency' => $currency,
            'status' => 'filled',
            'linked_position_uid' => $positionUid,
            'exchange_order_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')
            ->table('futures_orders')
            ->insert($closeOrder);

        $closeTrade = [
            'trade_uid' => $closeTradeUid,
            'order_uid' => $closeOrderUid,
            'user_uid' => $userUid,
            'account_uid' => $accountUid,
            'mode' => $positionMode,
            'symbol' => $positionArray['symbol'],
            'side' => $closingSide,
            'price' => $exitPrice,
            'quantity' => $quantity,
            'notional_value' => $closeNotional,
            'fee' => $closeFee,
            'liquidity' => 'taker',
            'reduce_only' => true,
            'realized_pnl' => $realizedPnl,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')
            ->table('futures_trades')
            ->insert($closeTrade);

        DB::connection('mongodb')
            ->table('futures_positions')
            ->where('position_uid', $positionUid)
            ->where('status', 'closing')
            ->update([
                'status' => 'closed',
                'exit_price' => $exitPrice,
                'close_order_uid' => $closeOrderUid,
                'close_trade_uid' => $closeTradeUid,
                'closing_fee' => $closeFee,
                'realized_pnl' => $realizedPnl,
                'closed_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'position_uid' => $positionUid,
            'close_order' => $closeOrder,
            'close_trade' => $closeTrade,
            'realized_pnl' => $realizedPnl,
            'closing_fee' => $closeFee,
            'wallet' => $wallet,
        ];
    }

    private function validateOrderPayload(Request $request): array
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            'side' => ['required', Rule::in(['buy', 'sell'])],
            'order_type' => ['required', Rule::in(['market', 'limit', 'stop_limit', 'stop_market'])],
            'quantity' => ['required', 'numeric', 'min:0.00000001', 'max:100000000'],
            'leverage' => ['required', 'integer', 'min:1', 'max:1000'],
            'last_price' => ['required', 'numeric', 'min:0.00000001'],
            'price' => ['nullable', 'numeric', 'min:0.00000001'],
            'stop_price' => ['nullable', 'numeric', 'min:0.00000001'],
            'take_profit' => ['nullable', 'numeric', 'min:0.00000001'],
            'stop_loss' => ['nullable', 'numeric', 'min:0.00000001'],
            'reduce_only' => ['nullable', 'boolean'],
            'margin_mode' => ['nullable', 'string'],
            'currency' => ['nullable', 'string', Rule::in(['USDT', 'USD', 'INR'])],
            'client_order_id' => ['nullable', 'string', 'max:120'],
        ], [
            'symbol.required' => 'Symbol is required.',
            'side.required' => 'Order side is required.',
            'order_type.required' => 'Order type is required.',
            'quantity.required' => 'Quantity is required.',
            'quantity.min' => 'Quantity must be greater than 0.',
            'margin.required' => 'Margin amount is required.',
            'margin.min' => 'Margin must be at least 1 USDT.',
            'leverage.required' => 'Leverage is required.',
            'leverage.max' => 'Leverage cannot be more than 1000x.',
            'last_price.required' => 'Last price is required.',
        ]);

        if (in_array($validated['order_type'], ['limit', 'stop_limit'], true) && empty($validated['price'])) {
            abort(response()->json([
                'success' => false,
                'message' => 'Limit price is required for this order type.',
                'errors' => [
                    'price' => ['Limit price is required for this order type.'],
                ],
            ], 422));
        }

        if (in_array($validated['order_type'], ['stop_limit', 'stop_market'], true) && empty($validated['stop_price'])) {
            abort(response()->json([
                'success' => false,
                'message' => 'Stop price is required for this order type.',
                'errors' => [
                    'stop_price' => ['Stop price is required for this order type.'],
                ],
            ], 422));
        }

        $validated['symbol'] = strtoupper($validated['symbol']);

        $minLotSizes = [
            'BTCUSDT' => 0.0007,
            'ETHUSDT' => 0.0089,
            'SOLUSDT' => 0.05,
            'XAUUSDT' => 0.002,
            'GOLDUSDT' => 0.002,
            'BNBUSDT' => 0.01,
            'XRPUSDT' => 3.5,
            'ADAUSDT' => 23,
            'AVAXUSDT' => 1,
            'LINKUSDT' => 0.41,
            'DOTUSDT' => 4.9,
            'POLUSDT' => 50,
            'MATICUSDT' => 50,
            'LTCUSDT' => 0.092,
            'ATOMUSDT' => 1,
            'ATEMUSDT' => 1,
            'UNIUSDT' => 1,
            'APTUSDT' => 7.5,
            'ARBUSDT' => 22.7,
            'OPUSDT' => 44.6,
            'SUIUSDT' => 6.4,
            'NEARUSDT' => 2,
            'DOGEUSDT' => 1,
            'GODDOGTUSDT' => 1,
            'WIFUSDT' => 26.1,
        ];

        $minRequired = $minLotSizes[$validated['symbol']] ?? 0.001;
        if ((float) $validated['quantity'] < (float) $minRequired) {
            abort(response()->json([
                'success' => false,
                'message' => "Minimum order quantity for {$validated['symbol']} is {$minRequired}.",
                'errors' => [
                    'quantity' => ["Minimum order quantity for {$validated['symbol']} is {$minRequired}."],
                ],
            ], 422));
        }

        return $validated;
    }

    private function calculateOrder(array $validated, ?string $userUid = null): array
    {
        $lastPrice = (float) $validated['last_price'];
        $orderType = $validated['order_type'];
        $side = $validated['side'];
        $price = isset($validated['price']) ? (float) $validated['price'] : null;
        $currency = strtoupper($validated['currency'] ?? 'USDT');
        if ($currency === 'USD') {
            $currency = 'USDT';
        }

        $spreadPrices = \App\Helpers\SpreadHelper::calculatePrices($lastPrice, $userUid);
        $spreadAmount = $spreadPrices['spread_amount'];

        $executionPrice = $lastPrice;

        if ($orderType === 'market' || $orderType === 'stop_market') {
            $executionPrice = $side === 'buy' ? $spreadPrices['buy_price'] : $spreadPrices['sell_price'];
        } elseif (($orderType === 'limit' || $orderType === 'stop_limit') && $price > 0) {
            $executionPrice = $price;
        }

        $quantity = round((float) $validated['quantity'], 8);
        $leverage = (int) $validated['leverage'];

        $notionalUsd = round($quantity * $executionPrice, 4);
        $marginUsd = round($notionalUsd / $leverage, 4);

        $estimatedFeeRate = app(\App\Services\DemoWalletService::class)->tradingFeeRate('futures');

        $margin = $marginUsd;
        $estimatedFee = round($notionalUsd * $estimatedFeeRate, 4);
        $notional = $notionalUsd;

        return [
            'symbol' => strtoupper($validated['symbol']),
            'side' => $validated['side'],
            'order_type' => $validated['order_type'],
            'market_price' => $lastPrice,
            'spread_amount' => $spreadAmount,
            'execution_price' => round($executionPrice, 8),
            'quantity' => $quantity,
            'margin' => $margin,
            'leverage' => $leverage,
            'notional_value' => $notional,
            'estimated_quantity' => $quantity,
            'estimated_fee_rate' => $estimatedFeeRate,
            'estimated_fee' => $estimatedFee,
            'currency' => $currency,
            'margin_mode' => strtolower($validated['margin_mode'] ?? 'isolated'),
            'take_profit' => (!empty($validated['take_profit']) && (float)$validated['take_profit'] > 0) ? (float)$validated['take_profit'] : null,
            'stop_loss' => (!empty($validated['stop_loss']) && (float)$validated['stop_loss'] > 0) ? (float)$validated['stop_loss'] : null,
            'reduce_only' => (bool) ($validated['reduce_only'] ?? false),
        ];
    }

    private function createTrade(array $order, string $mode = 'demo'): array
    {
        $trade = [
            'trade_uid' => (string) Str::uuid(),
            'order_uid' => $order['order_uid'],
            'user_uid' => $order['user_uid'],
            'account_uid' => $order['account_uid'] ?? null,
            'broker_type' => $order['broker_type'] ?? $this->getBrokerTypeForOrderOrPosition($order, $order['user_uid'] ?? null),
            'mode' => $mode,
            'symbol' => $order['symbol'],
            'side' => $order['side'],
            'price' => $order['execution_price'],
            'quantity' => $order['estimated_quantity'],
            'notional_value' => $order['notional_value'],
            'fee' => $order['fee_charged'],
            'currency' => $order['currency'] ?? 'USDT',
            'liquidity' => 'taker',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')
            ->table('futures_trades')
            ->insert($trade);

        return $trade;
    }

    private function createDemoTrade(array $order): array
    {
        return $this->createTrade($order, $order['mode'] ?? 'demo');
    }

    private function createPosition(array $order, array $trade, string $mode = 'demo'): array
    {
        $brokerType = $order['broker_type'] ?? $this->getBrokerTypeForOrderOrPosition($order, $order['user_uid'] ?? null);

        if ($brokerType !== 'mt5') {
            // Kryvex broker: merge into existing open position if one exists for the same symbol & side (Binance style)
            $existing = DB::connection('mongodb')
                ->table('futures_positions')
                ->where('user_uid', $order['user_uid'])
                ->where('mode', $mode)
                ->where('symbol', $order['symbol'])
                ->where('side', $order['side'])
                ->where('status', 'open')
                ->where(function ($q) use ($order) {
                    if (!empty($order['account_uid'])) {
                        $q->where('account_uid', $order['account_uid'])
                          ->orWhereNull('account_uid')
                          ->orWhere('account_uid', '');
                    }
                })
                ->orderByDesc('created_at')
                ->first();

            if ($existing) {
                $existingArr = (array) $existing;
                $oldQty = (float) ($existingArr['quantity'] ?? 0);
                $oldEntry = (float) ($existingArr['entry_price'] ?? 0);
                $oldMargin = (float) ($existingArr['margin'] ?? 0);
                $oldNotional = (float) ($existingArr['notional_value'] ?? 0);
                $oldFee = (float) ($existingArr['fee_paid'] ?? 0);

                $newQty = (float) ($order['estimated_quantity'] ?? 0);
                $newEntry = (float) ($order['execution_price'] ?? 0);
                $newMargin = (float) ($order['margin'] ?? 0);
                $newNotional = (float) ($order['notional_value'] ?? 0);
                $newFee = (float) ($order['fee_charged'] ?? 0);

                $totalQty = $oldQty + $newQty;
                $avgEntry = $totalQty > 0 ? round((($oldQty * $oldEntry) + ($newQty * $newEntry)) / $totalQty, 8) : $newEntry;

                $orderUids = (array) ($existingArr['order_uids'] ?? [$existingArr['order_uid'] ?? '']);
                if (!in_array($order['order_uid'], $orderUids, true)) {
                    $orderUids[] = $order['order_uid'];
                }
                $tradeUids = (array) ($existingArr['trade_uids'] ?? [$existingArr['trade_uid'] ?? '']);
                if (!in_array($trade['trade_uid'], $tradeUids, true)) {
                    $tradeUids[] = $trade['trade_uid'];
                }

                $updateData = [
                    'order_uids'     => $orderUids,
                    'trade_uids'     => $tradeUids,
                    'quantity'       => $totalQty,
                    'entry_price'    => $avgEntry,
                    'margin'         => round($oldMargin + $newMargin, 8),
                    'notional_value' => round($oldNotional + $newNotional, 8),
                    'fee_paid'       => round($oldFee + $newFee, 8),
                    'take_profit'    => !empty($order['take_profit']) ? $order['take_profit'] : ($existingArr['take_profit'] ?? null),
                    'stop_loss'      => !empty($order['stop_loss']) ? $order['stop_loss'] : ($existingArr['stop_loss'] ?? null),
                    'updated_at'     => now(),
                ];

                DB::connection('mongodb')
                    ->table('futures_positions')
                    ->where('position_uid', $existingArr['position_uid'])
                    ->update($updateData);

                return array_merge($existingArr, $updateData);
            }
        }

        // For MT5 (or first position for Kryvex): create a separate position document
        $position = [
            'position_uid' => (string) Str::uuid(),
            'order_uid' => $order['order_uid'],
            'order_uids' => [$order['order_uid']],
            'trade_uid' => $trade['trade_uid'],
            'trade_uids' => [$trade['trade_uid']],
            'user_uid' => $order['user_uid'],
            'account_uid' => $order['account_uid'] ?? null,
            'broker_type' => $brokerType,
            'mode' => $mode,
            'symbol' => $order['symbol'],
            'side' => $order['side'],
            'entry_price' => $order['execution_price'],
            'quantity' => $order['estimated_quantity'],
            'margin' => $order['margin'],
            'leverage' => $order['leverage'],
            'margin_mode' => strtolower($order['margin_mode'] ?? 'isolated'),
            'notional_value' => $order['notional_value'],
            'fee_paid' => $order['fee_charged'],
            'take_profit' => $order['take_profit'],
            'stop_loss' => $order['stop_loss'],
            'currency' => $order['currency'] ?? 'USDT',
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
            'realized_pnl' => 0.0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')
            ->table('futures_positions')
            ->insert($position);

        return $position;
    }

    private function createDemoPosition(array $order, array $trade): array
    {
        return $this->createPosition($order, $trade, $order['mode'] ?? 'demo');
    }
}
