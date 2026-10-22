<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DemoWalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OptionOrderController extends Controller
{
    protected function getActiveAccountUid(Request $request, string $userUid): string
    {
        $headerUid = $request->header('X-Account-UID') ?: $request->input('account_uid');
        $walletService = app(DemoWalletService::class);
        return $walletService->resolveAccountUid($userUid, $headerUid);
    }

    public function placeDemo(Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid']);
        $validated = $this->validateOrderPayload($request);
        $calculation = $this->calculateOrder($validated);

        $orderUid = (string) Str::uuid();
        $status = $validated['order_type'] === 'market' ? 'filled' : 'open';
        $feeToChargeNow = (float) $calculation['estimated_fee'];
        $currency = strtoupper($validated['currency'] ?? $request->input('currency', 'USDT'));
        if ($currency === 'USD') {
            $currency = 'USDT';
        }
        if ($currency !== 'INR') {
            $currency = 'USDT';
        }

        $wallet = $walletService->lockDemoMargin(
            $user['uid'],
            (float) $calculation['margin'],
            $feeToChargeNow,
            $orderUid,
            'options',
            $accountUid,
            $currency
        );

        $order = [
            'order_uid' => $orderUid,
            'client_order_id' => $validated['client_order_id'] ?? null,
            'user_uid' => $user['uid'],
            'account_uid' => $accountUid,
            'mode' => 'demo',
            'underlying' => $calculation['underlying'],
            'option_symbol' => $calculation['option_symbol'],
            'contract_type' => $calculation['contract_type'],
            'side' => $calculation['side'],
            'order_type' => $calculation['order_type'],
            'price' => $validated['price'] ?? null,
            'execution_price' => $calculation['execution_price'],
            'quantity' => $calculation['quantity'],
            'margin' => $calculation['margin'],
            'premium' => $calculation['premium'],
            'estimated_fee_rate' => $calculation['estimated_fee_rate'],
            'estimated_fee' => $calculation['estimated_fee'],
            'fee_charged' => $feeToChargeNow,
            'currency' => $currency,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')
            ->table('options_orders')
            ->insert($order);

        $trade = null;
        $position = null;

        if ($status === 'filled') {
            $trade = $this->createDemoTrade($order);
            $position = $this->createDemoPosition($order, $trade);
        }

        return response()->json([
            'success' => true,
            'message' => $status === 'filled'
                ? 'Demo option order filled successfully.'
                : 'Demo option order placed successfully and margin locked.',
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

        $query = DB::connection('mongodb')
            ->table('options_orders')
            ->where('user_uid', $user['uid'])
            ->where('account_uid', $accountUid);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($request->has('page') || $request->query('paginate') === 'true') {
            $orders = $query->orderByDesc('created_at')->paginate(10);
            return response()->json([
                'success' => true,
                'message' => 'Options orders fetched successfully.',
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

        return response()->json([
            'success' => true,
            'message' => 'Options orders fetched successfully.',
            'data' => [
                'orders' => $query->orderByDesc('created_at')->limit(100)->get(),
            ],
        ]);
    }

    public function positions(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid']);

        return response()->json([
            'success' => true,
            'message' => 'Options positions fetched successfully.',
            'data' => [
                'positions' => DB::connection('mongodb')
                    ->table('options_positions')
                    ->where('user_uid', $user['uid'])
                    ->where('account_uid', $accountUid)
                    ->where('status', 'open')
                    ->orderByDesc('created_at')
                    ->limit(100)
                    ->get(),
            ],
        ]);
    }

    public function trades(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid']);

        $query = DB::connection('mongodb')
            ->table('options_trades')
            ->where('user_uid', $user['uid'])
            ->where('account_uid', $accountUid);

        if ($request->has('page') || $request->query('paginate') === 'true') {
            $trades = $query->orderByDesc('created_at')->paginate(10);
            return response()->json([
                'success' => true,
                'message' => 'Options trades fetched successfully.',
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

        return response()->json([
            'success' => true,
            'message' => 'Options trades fetched successfully.',
            'data' => [
                'trades' => $query->orderByDesc('created_at')->limit(100)->get(),
            ],
        ]);
    }

    public function cancelDemoOrder(string $orderUid, Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid']);

        $order = DB::connection('mongodb')
            ->table('options_orders')
            ->where('user_uid', $user['uid'])
            ->where('order_uid', $orderUid)
            ->first();

        if (! $order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        if (($order->status ?? null) !== 'open') {
            return response()->json(['success' => false, 'message' => 'Only open orders can be cancelled.'], 422);
        }

        DB::connection('mongodb')
            ->table('options_orders')
            ->where('order_uid', $orderUid)
            ->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

        $currency = strtoupper($order->currency ?? 'USDT');
        if ($currency === 'USD') {
            $currency = 'USDT';
        }

        $wallet = $walletService->releaseDemoMargin(
            $user['uid'],
            (float) ($order->margin ?? 0),
            $orderUid,
            'options',
            $order->account_uid ?? $accountUid,
            $currency
        );

        return response()->json([
            'success' => true,
            'message' => 'Demo option order cancelled and margin released.',
            'data' => ['order_uid' => $orderUid, 'wallet' => $wallet],
        ]);
    }

    public function closeDemoPosition(string $positionUid, Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');

        $claimed = DB::connection('mongodb')
            ->table('options_positions')
            ->where('user_uid', $user['uid'])
            ->where('position_uid', $positionUid)
            ->where('status', 'open')
            ->update([
                'status' => 'closing',
                'closing_started_at' => now(),
                'updated_at' => now(),
            ]);

        if (! $claimed) {
            $existing = DB::connection('mongodb')
                ->table('options_positions')
                ->where('user_uid', $user['uid'])
                ->where('position_uid', $positionUid)
                ->first();

            if ($existing && in_array((string) ($existing->status ?? ''), ['closing', 'closed'], true)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Option position is already closing or closed.',
                    'data' => ['position_uid' => $positionUid, 'status' => $existing->status ?? 'closed'],
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Open option position not found.'], 404);
        }

        $position = DB::connection('mongodb')
            ->table('options_positions')
            ->where('user_uid', $user['uid'])
            ->where('position_uid', $positionUid)
            ->first();

        if (! $position) {
            return response()->json(['success' => false, 'message' => 'Open option position not found.'], 404);
        }

        $positionArray = (array) $position;
        $entryPrice = (float) ($positionArray['entry_price'] ?? 0);
        $reqExit = $request->input('exit_price');
        $exitPrice = ($reqExit && (float) $reqExit > 0)
            ? round((float) $reqExit, 8)
            : round((float) ($positionArray['current_price'] ?? $positionArray['entry_price'] ?? $positionArray['price'] ?? 100), 8);
        $quantity = (float) ($positionArray['quantity'] ?? 0);
        $margin = (float) ($positionArray['margin'] ?? 0);
        $side = strtolower((string) ($positionArray['side'] ?? 'buy'));

        $realizedPnl = $side === 'buy'
            ? ($exitPrice - $entryPrice) * $quantity
            : ($entryPrice - $exitPrice) * $quantity;
        $realizedPnl = round($realizedPnl, 4);

        $closePremium = round($exitPrice * $quantity, 4);
        $closeFeeRate = $walletService->tradingFeeRate('options');
        $closeFee = round($closePremium * $closeFeeRate, 8);
        $closeOrderUid = (string) Str::uuid();
        $closeTradeUid = (string) Str::uuid();
        $closingSide = $side === 'buy' ? 'sell' : 'buy';

        $accountUid = $positionArray['account_uid'] ?? null;
        $currency = strtoupper($positionArray['currency'] ?? 'USDT');
        if ($currency === 'USD') {
            $currency = 'USDT';
        }

        $wallet = $walletService->settleClosedDemoPosition(
            $user['uid'],
            $margin,
            $realizedPnl,
            $closeFee,
            $closeOrderUid,
            'options',
            $accountUid,
            $currency
        );

        $closeOrder = [
            'order_uid' => $closeOrderUid,
            'user_uid' => $user['uid'],
            'account_uid' => $accountUid,
            'mode' => 'demo',
            'underlying' => $positionArray['underlying'] ?? null,
            'option_symbol' => $positionArray['option_symbol'],
            'contract_type' => $positionArray['contract_type'] ?? null,
            'side' => $closingSide,
            'order_type' => 'market',
            'execution_price' => $exitPrice,
            'quantity' => $quantity,
            'margin' => 0.0,
            'premium' => $closePremium,
            'estimated_fee_rate' => $closeFeeRate,
            'estimated_fee' => $closeFee,
            'fee_charged' => $closeFee,
            'reduce_only' => true,
            'status' => 'filled',
            'linked_position_uid' => $positionUid,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')->table('options_orders')->insert($closeOrder);

        $closeTrade = [
            'trade_uid' => $closeTradeUid,
            'order_uid' => $closeOrderUid,
            'user_uid' => $user['uid'],
            'account_uid' => $accountUid,
            'mode' => 'demo',
            'underlying' => $positionArray['underlying'] ?? null,
            'option_symbol' => $positionArray['option_symbol'],
            'contract_type' => $positionArray['contract_type'] ?? null,
            'side' => $closingSide,
            'price' => $exitPrice,
            'quantity' => $quantity,
            'premium' => $closePremium,
            'fee' => $closeFee,
            'liquidity' => 'taker',
            'reduce_only' => true,
            'realized_pnl' => $realizedPnl,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')->table('options_trades')->insert($closeTrade);

        DB::connection('mongodb')
            ->table('options_positions')
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

        return response()->json([
            'success' => true,
            'message' => 'Demo option position closed successfully.',
            'data' => [
                'position_uid' => $positionUid,
                'close_order' => $closeOrder,
                'close_trade' => $closeTrade,
                'realized_pnl' => $realizedPnl,
                'closing_fee' => $closeFee,
                'wallet' => $wallet,
            ],
        ]);
    }

    public function closeAllDemoPositions(Request $request, DemoWalletService $walletService)
    {
        $user = $request->attributes->get('auth_user');
        $accountUid = $this->getActiveAccountUid($request, $user['uid']);
        $exitPrice = (float) $request->input('exit_price', 0);
        $positionPrices = collect($request->input('positions', []))
            ->filter(fn ($item) => is_array($item) && ! empty($item['position_uid']))
            ->mapWithKeys(fn ($item) => [
                (string) $item['position_uid'] => (float) ($item['exit_price'] ?? 0),
            ]);

        $positions = DB::connection('mongodb')
            ->table('options_positions')
            ->where('user_uid', $user['uid'])
            ->where('account_uid', $accountUid)
            ->where('status', 'open')
            ->get();

        $closed = 0;

        foreach ($positions as $position) {
            $positionUid = (string) $position->position_uid;
            $price = (float) ($positionPrices[$positionUid] ?? $exitPrice ?: ($position->entry_price ?? 0));

            if ($price <= 0) {
                continue;
            }

            $fakeRequest = Request::create('', 'POST', ['exit_price' => $price]);
            $fakeRequest->attributes->set('auth_user', $user);
            $this->closeDemoPosition($positionUid, $fakeRequest, $walletService);
            $closed++;
        }

        return response()->json([
            'success' => true,
            'message' => $closed . ' option positions closed.',
            'data' => ['closed' => $closed],
        ]);
    }

    private function validateOrderPayload(Request $request): array
    {
        $validated = $request->validate([
            'underlying' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/'],
            'option_symbol' => ['required', 'string', 'max:80'],
            'contract_type' => ['nullable', 'string', 'max:10'],
            'side' => ['required', Rule::in(['buy', 'sell'])],
            'order_type' => ['required', Rule::in(['market', 'limit'])],
            'quantity' => ['required', 'numeric', 'min:0.00000001', 'max:100000000'],
            'price' => ['nullable', 'numeric', 'min:0.00000001', 'required_if:order_type,limit'],
            'last_price' => ['nullable', 'numeric', 'min:0.00000001'],
            'take_profit' => ['nullable', 'numeric', 'min:0.00000001'],
            'stop_loss' => ['nullable', 'numeric', 'min:0.00000001'],
            'client_order_id' => ['nullable', 'string', 'max:120'],
        ]);

        $validated['underlying'] = strtoupper($validated['underlying']);
        $validated['option_symbol'] = strtoupper($validated['option_symbol']);
        $validated['side'] = strtolower($validated['side']);
        $validated['order_type'] = strtolower($validated['order_type']);
        $validated['contract_type'] = strtolower((string) ($validated['contract_type'] ?? $this->inferContractType($validated['option_symbol'])));

        return $validated;
    }

    private function calculateOrder(array $validated): array
    {
        $orderType = strtolower((string) ($validated['order_type'] ?? 'market'));
        $price = $orderType === 'limit'
            ? (float) ($validated['price'] ?? 0)
            : (float) ($validated['last_price'] ?? $validated['price'] ?? 0);

        if ($price <= 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'price' => ['Valid option price is required to place this order.'],
            ]);
        }

        $price = round($price, 8);
        $quantity = round((float) $validated['quantity'], 8);
        $premium = round($price * $quantity, 4);
        $margin = $premium;
        $feeRate = app(\App\Services\DemoWalletService::class)->tradingFeeRate('options');
        $fee = round($premium * $feeRate, 8);

        return [
            'underlying' => $validated['underlying'],
            'option_symbol' => $validated['option_symbol'],
            'contract_type' => $validated['contract_type'],
            'side' => $validated['side'],
            'order_type' => $validated['order_type'],
            'execution_price' => $price,
            'quantity' => $quantity,
            'premium' => $premium,
            'margin' => $margin,
            'estimated_fee_rate' => $feeRate,
            'estimated_fee' => $fee,
            'take_profit' => isset($validated['take_profit']) ? (float) $validated['take_profit'] : null,
            'stop_loss' => isset($validated['stop_loss']) ? (float) $validated['stop_loss'] : null,
        ];
    }

    private function createDemoTrade(array $order): array
    {
        $trade = [
            'trade_uid' => (string) Str::uuid(),
            'order_uid' => $order['order_uid'],
            'user_uid' => $order['user_uid'],
            'account_uid' => $order['account_uid'] ?? null,
            'mode' => 'demo',
            'underlying' => $order['underlying'],
            'option_symbol' => $order['option_symbol'],
            'contract_type' => $order['contract_type'],
            'side' => $order['side'],
            'price' => $order['execution_price'],
            'quantity' => $order['quantity'],
            'premium' => $order['premium'],
            'fee' => $order['fee_charged'],
            'liquidity' => 'taker',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')->table('options_trades')->insert($trade);

        return $trade;
    }

    private function createDemoPosition(array $order, array $trade): array
    {
        $position = [
            'position_uid' => (string) Str::uuid(),
            'order_uid' => $order['order_uid'],
            'trade_uid' => $trade['trade_uid'],
            'user_uid' => $order['user_uid'],
            'account_uid' => $order['account_uid'] ?? null,
            'mode' => 'demo',
            'underlying' => $order['underlying'],
            'option_symbol' => $order['option_symbol'],
            'contract_type' => $order['contract_type'],
            'side' => $order['side'],
            'entry_price' => $order['execution_price'],
            'quantity' => $order['quantity'],
            'margin' => $order['margin'],
            'premium' => $order['premium'],
            'fee_paid' => $order['fee_charged'],
            'take_profit' => $order['take_profit'] ?? null,
            'stop_loss' => $order['stop_loss'] ?? null,
            'status' => 'open',
            'opened_at' => now(),
            'closed_at' => null,
            'realized_pnl' => 0.0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')->table('options_positions')->insert($position);

        return $position;
    }

    private function inferContractType(string $symbol): string
    {
        $upper = strtoupper($symbol);

        if (str_contains($upper, '-C') || str_ends_with($upper, 'C')) {
            return 'call';
        }

        if (str_contains($upper, '-P') || str_ends_with($upper, 'P')) {
            return 'put';
        }

        return 'option';
    }
}
