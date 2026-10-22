<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SpotTradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpotOrderController extends Controller
{
    public function balances(Request $request, SpotTradingService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        try {
            $balances = $service->getUserBalances((string) $user['uid']);
            return response()->json([
                'success' => true,
                'data' => $balances,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function place(Request $request, SpotTradingService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $payload = $request->validate([
            'pair' => 'nullable|string',
            'symbol' => 'nullable|string',
            'side' => 'required|in:buy,sell,BUY,SELL',
            'type' => 'required|in:market,limit,MARKET,LIMIT',
            'price' => 'nullable|numeric|min:0',
            'amount' => 'required|numeric|gt:0',
            'market_price' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $service->placeOrder((string) $user['uid'], $payload);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function openOrders(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $symbol = $request->input('symbol');

        $query = DB::connection('mongodb')
            ->table('spot_orders')
            ->where('user_uid', (string) $user['uid'])
            ->where('status', 'open');

        if ($symbol) {
            $query->where('symbol', strtoupper(trim($symbol)));
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function orderHistory(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $symbol = $request->input('symbol');

        $query = DB::connection('mongodb')
            ->table('spot_orders')
            ->where('user_uid', (string) $user['uid'])
            ->where('status', '!=', 'open');

        if ($symbol) {
            $query->where('symbol', strtoupper(trim($symbol)));
        }

        $orders = $query->orderBy('created_at', 'desc')->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function tradeHistory(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $symbol = $request->input('symbol');

        $query = DB::connection('mongodb')
            ->table('spot_trades')
            ->where('user_uid', (string) $user['uid']);

        if ($symbol) {
            $query->where('symbol', strtoupper(trim($symbol)));
        }

        $trades = $query->orderBy('executed_at', 'desc')->limit(50)->get();

        return response()->json([
            'success' => true,
            'data' => $trades,
        ]);
    }

    public function cancel(Request $request, string $orderUid, SpotTradingService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        try {
            $result = $service->cancelOrder((string) $user['uid'], $orderUid);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function processFills(Request $request, SpotTradingService $service)
    {
        $user = $request->attributes->get('auth_user');
        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $prices = $request->input('prices', []);
        if (!is_array($prices)) {
            $prices = [];
        }

        try {
            $result = $service->processLimitFills((string) $user['uid'], $prices);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}

