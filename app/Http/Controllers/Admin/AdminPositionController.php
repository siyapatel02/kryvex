<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminPositionController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        return $this->positionsPage($request, $status);
    }

    public function open(Request $request)
    {
        return $this->positionsPage($request, 'open');
    }

    public function closed(Request $request)
    {
        return $this->positionsPage($request, 'closed');
    }

    private function positionsPage(Request $request, string $statusTab)
    {
        $search = strtolower(trim((string) $request->query('search')));
        $symbol = strtoupper(trim((string) $request->query('symbol')));
        $side = $request->query('side', 'all');
        $profit = $request->query('profit', 'all');

        $rawPositions = collect();

        $futuresPositions = DB::connection('mongodb')
            ->table('futures_positions')
            ->orderByDesc('created_at')
            ->limit(2000)
            ->get()
            ->map(function ($position) {
                $position->market_type = 'futures';
                $position->display_symbol = strtoupper($position->symbol ?? '-');
                $position->live_price_symbol = strtoupper($position->symbol ?? '');
                return $position;
            });

        $rawPositions = $rawPositions->merge($futuresPositions);

        try {
            $optionsPositions = DB::connection('mongodb')
                ->table('options_positions')
                ->orderByDesc('created_at')
                ->limit(2000)
                ->get()
                ->map(function ($position) {
                    $position->market_type = 'options';
                    $position->display_symbol = strtoupper($position->option_symbol ?? $position->contract_symbol ?? $position->symbol ?? $position->underlying ?? '-');
                    $position->live_price_symbol = strtoupper($position->option_symbol ?? $position->contract_symbol ?? $position->symbol ?? '');
                    return $position;
                });

            $rawPositions = $rawPositions->merge($optionsPositions);
        } catch (\Throwable $exception) {
            // Options module may not be installed on older local copies.
        }

        $livePrices = $this->getCachedLivePrices();

        $users = DB::connection('mongodb')
            ->table('users')
            ->get()
            ->keyBy(function ($user) {
                return (string)($user->uid ?? $user->_id ?? '');
            });

        $positions = $rawPositions->map(function ($position) use ($users, $livePrices) {
            $uid = (string)($position->user_uid ?? '');
            $user = $users->get($uid);

            $position->display_user_name = $position->user_name ?? ($user->name ?? 'Unknown User');
            $position->display_user_email = $position->user_email ?? ($user->email ?? '-');
            
            $symbolKey = strtoupper($position->live_price_symbol ?? $position->symbol ?? '');
            
            $position->entry_price = $position->entry_price ?? $position->execution_price ?? 0;
            $position->current_market_price = $livePrices[$symbolKey]
                ?? $position->current_price
                ?? $position->mark_price
                ?? $position->last_price
                ?? $position->exit_price
                ?? $position->entry_price
                ?? 0;

            $entry = (float)($position->entry_price ?? 0);
            $qty = (float)($position->quantity ?? 0);
            $sideValue = strtolower((string)($position->side ?? 'buy'));
            $statusValue = strtolower((string)($position->status ?? 'open'));
            $currentPrice = (float)($position->current_market_price ?? $entry);
            $position->admin_pnl = $statusValue === 'open'
                ? ($sideValue === 'sell' ? ($entry - $currentPrice) * $qty : ($currentPrice - $entry) * $qty)
                : (float)($position->realized_pnl ?? 0);

            $position->search_text = strtolower(implode(' ', [
                $position->display_user_name,
                $position->display_user_email,
                $position->user_uid ?? '',
                $position->position_uid ?? '',
                $position->order_uid ?? '',
                $position->trade_uid ?? '',
                $position->symbol ?? '',
                $position->display_symbol ?? '',
                $position->option_symbol ?? '',
                $position->underlying ?? '',
                $position->side ?? '',
                $position->contract_type ?? '',
                $position->status ?? '',
                $position->mode ?? '',
                $position->market_type ?? '',
            ]));

            return $position;
        });

        // Summary cards reflect the selected status tab/search/symbol,
        // but ignore side/profit chips for a stable overview.
        $summaryPositions = $positions->filter(function ($position) use ($statusTab, $search, $symbol) {
            $itemStatus = strtolower((string)($position->status ?? ''));
            $itemSymbol = strtoupper((string)($position->display_symbol ?? $position->symbol ?? $position->underlying ?? ''));

            if ($statusTab !== 'all' && $itemStatus !== $statusTab) {
                return false;
            }

            if ($symbol !== '' && !str_contains($itemSymbol, $symbol)) {
                return false;
            }

            if ($search !== '' && !str_contains($position->search_text, $search)) {
                return false;
            }

            return true;
        })->values();

        $positionStats = [
            'open' => $summaryPositions->filter(fn ($position) => strtolower((string)($position->status ?? '')) === 'open')->count(),
            'total_margin' => (float) $summaryPositions->filter(fn ($position) => strtolower((string)($position->status ?? '')) === 'open')->sum(fn ($position) => (float)($position->margin ?? 0)),
            'unrealized_pnl' => (float) $summaryPositions->filter(fn ($position) => strtolower((string)($position->status ?? '')) === 'open')->sum(fn ($position) => (float)($position->admin_pnl ?? 0)),
            'long' => $summaryPositions->filter(fn ($position) => in_array(strtolower((string)($position->side ?? '')), ['buy', 'long', 'call'], true))->count(),
        ];

        $positions = $positions->filter(function ($position) use ($statusTab, $search, $symbol, $side, $profit) {
            $itemStatus = strtolower((string)($position->status ?? ''));
            $itemSymbol = strtoupper((string)($position->display_symbol ?? $position->symbol ?? $position->underlying ?? ''));
            $itemSide = strtolower((string)($position->side ?? ''));

            if ($statusTab !== 'all' && $itemStatus !== $statusTab) {
                return false;
            }

            if ($side !== 'all') {
                if ($side === 'long' && !in_array($itemSide, ['buy', 'long', 'call'], true)) {
                    return false;
                }

                if ($side === 'short' && !in_array($itemSide, ['sell', 'short', 'put'], true)) {
                    return false;
                }
            }

            if ($profit === 'profit' && (float)($position->admin_pnl ?? 0) < 0) {
                return false;
            }

            if ($profit === 'loss' && (float)($position->admin_pnl ?? 0) >= 0) {
                return false;
            }

            if ($symbol !== '' && !str_contains($itemSymbol, $symbol)) {
                return false;
            }

            if ($search !== '' && !str_contains($position->search_text, $search)) {
                return false;
            }

            return true;
        })->sortByDesc(function ($position) {
            return strtotime((string)($position->opened_at ?? $position->created_at ?? $position->updated_at ?? now()));
        })->values();

        $positions = AdminPaginator::paginate($positions, $request, 10);

        return view('admin.positions.index', compact(
            'positions',
            'statusTab',
            'search',
            'symbol',
            'side',
            'profit',
            'positionStats'
        ));
    }

    private function getCachedLivePrices(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('admin_live_prices_all', 10, function () {
            $prices = [];

            // 1. Fetch all futures prices
            try {
                $response = \Illuminate\Support\Facades\Http::get('https://fapi.binance.com/fapi/v1/premiumIndex');
                if ($response->successful() && is_array($response->json())) {
                    foreach ($response->json() as $item) {
                        if (isset($item['symbol']) && isset($item['markPrice'])) {
                            $prices[strtoupper($item['symbol'])] = (float) $item['markPrice'];
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            // 2. Fetch all options prices
            try {
                $response = \Illuminate\Support\Facades\Http::get('https://eapi.binance.com/eapi/v1/mark');
                if ($response->successful() && is_array($response->json())) {
                    foreach ($response->json() as $item) {
                        if (isset($item['symbol']) && isset($item['markPrice'])) {
                            $prices[strtoupper($item['symbol'])] = (float) $item['markPrice'];
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore
            }

            return $prices;
        });
    }

    public function forceStop(Request $request)
    {
        $positionUid = (string) $request->input('position_uid');
        $marketType = strtolower((string) $request->input('market_type', 'futures'));

        if (!$positionUid) {
            return redirect()->back()->with('error', 'Position identifier is required.');
        }

        $collectionName = ($marketType === 'options') ? 'options_positions' : 'futures_positions';

        $position = DB::connection('mongodb')
            ->table($collectionName)
            ->where(function ($q) use ($positionUid) {
                $q->where('position_uid', $positionUid)
                  ->orWhere('_id', $positionUid);
            })
            ->first();

        if (!$position && $marketType === 'futures') {
            $position = DB::connection('mongodb')
                ->table('options_positions')
                ->where(function ($q) use ($positionUid) {
                    $q->where('position_uid', $positionUid)
                      ->orWhere('_id', $positionUid);
                })
                ->first();
            if ($position) {
                $collectionName = 'options_positions';
                $marketType = 'options';
            }
        }

        if (!$position) {
            return redirect()->back()->with('error', 'Open position not found.');
        }

        $positionArray = (array) $position;
        $status = strtolower((string) ($positionArray['status'] ?? 'open'));

        if ($status === 'closed') {
            return redirect()->back()->with('info', 'Position is already closed.');
        }

        $userUid = (string) ($positionArray['user_uid'] ?? '');
        $symbolKey = strtoupper($positionArray['symbol'] ?? $positionArray['option_symbol'] ?? $positionArray['contract_symbol'] ?? '');

        $livePrices = $this->getCachedLivePrices();
        $exitPrice = (float) ($livePrices[$symbolKey] ?? $positionArray['entry_price'] ?? 100);

        $entryPrice = (float) ($positionArray['entry_price'] ?? 0);
        $quantity = (float) ($positionArray['quantity'] ?? 0);
        $margin = (float) ($positionArray['margin'] ?? 0);
        $side = strtolower((string) ($positionArray['side'] ?? 'buy'));

        $realizedPnl = in_array($side, ['buy', 'long', 'call'], true)
            ? ($exitPrice - $entryPrice) * $quantity
            : ($entryPrice - $exitPrice) * $quantity;
        $realizedPnl = round($realizedPnl, 4);

        $accountUid = $positionArray['account_uid'] ?? null;
        $currency = strtoupper($positionArray['currency'] ?? 'USDT');
        if ($currency === 'USD') {
            $currency = 'USDT';
        }

        // Release margin & settle realized PnL back into user's wallet
        try {
            $walletService = app(\App\Services\DemoWalletService::class);
            $walletService->settleClosedDemoPosition(
                $userUid,
                $margin,
                $realizedPnl,
                0.0,
                (string) \Illuminate\Support\Str::uuid(),
                $marketType,
                $accountUid,
                $currency
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Admin forceStop wallet settlement warning: ' . $e->getMessage());
        }

        DB::connection('mongodb')
            ->table($collectionName)
            ->where(function ($q) use ($positionUid) {
                $q->where('position_uid', $positionUid)
                  ->orWhere('_id', $positionUid);
            })
            ->update([
                'status' => 'closed',
                'exit_price' => $exitPrice,
                'realized_pnl' => $realizedPnl,
                'close_type' => 'admin_force_stop',
                'closed_at' => now(),
                'updated_at' => now(),
            ]);

        $symbolName = strtoupper($positionArray['display_symbol'] ?? $symbolKey ?: 'position');
        return redirect()->back()->with('success', "Trade on {$symbolName} force stopped & closed successfully.");
    }
}
