<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Market\OptionMarketService;
use Illuminate\Http\Request;

class OptionMarketController extends Controller
{
    public function contracts(string $underlying, OptionMarketService $service)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Options contracts fetched.',
                'data' => $service->contracts($underlying),
            ]);
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function chain(string $underlying, Request $request, OptionMarketService $service)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Options chain fetched.',
                'data' => $service->chain(
                    $underlying,
                    $request->query('expiry')
                ),
            ]);
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function depth(string $symbol, Request $request, OptionMarketService $service)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Options order book fetched.',
                'data' => $service->depth(
                    $symbol,
                    (int) $request->query('limit', 10)
                ),
            ]);
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    public function trades(string $symbol, Request $request, OptionMarketService $service)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Options recent trades fetched.',
                'data' => $service->trades(
                    $symbol,
                    (int) $request->query('limit', 12)
                ),
            ]);
        } catch (\Throwable $exception) {
            return $this->error($exception);
        }
    }

    private function error(\Throwable $exception)
    {
        return response()->json([
            'success' => false,
            'message' => app()->environment('local')
                ? $exception->getMessage()
                : 'Unable to fetch options market data.',
        ], 422);
    }
}