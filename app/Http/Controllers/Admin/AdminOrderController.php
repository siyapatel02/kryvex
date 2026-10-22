<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminPaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    public function index(Request $request)
    {
        return $this->ordersPage($request, 'all', 'admin.orders.index');
    }

    public function futures(Request $request)
    {
        return $this->ordersPage($request, 'futures', 'admin.orders.index');
    }

    public function options(Request $request)
    {
        return $this->ordersPage($request, 'options', 'admin.orders.index');
    }

    private function ordersPage(Request $request, string $market, string $view)
    {
        $status = $request->query('status', 'all');
        $type = $request->query('type', 'all');
        $side = $request->query('side', 'all');
        $symbol = strtoupper(trim((string) $request->query('symbol')));
        $search = strtolower(trim((string) $request->query('search')));

        $orders = collect();
        $positionsByMarket = [
            'futures' => $this->positionsByOrder('futures'),
            'options' => $this->positionsByOrder('options'),
        ];

        if ($market === 'all' || $market === 'futures') {
            $futuresOrders = DB::connection('mongodb')
                ->table('futures_orders')
                ->orderByDesc('created_at')
                ->limit(2000)
                ->get()
                ->map(function ($order) {
                    $order->market_type = 'futures';
                    $order->display_symbol = strtoupper($order->symbol ?? '-');
                    $order->display_qty = $order->estimated_quantity ?? $order->quantity ?? $order->qty ?? '-';
                    $order->display_price = $order->execution_price ?? $order->price ?? $order->entry_price ?? 0;
                    $order->display_margin = $order->margin ?? 0;
                    $order->display_fee = $order->fee_charged ?? $order->estimated_fee ?? 0;

                    return $order;
                });

            $orders = $orders->merge($futuresOrders);
        }

        if ($market === 'all' || $market === 'options') {
            $optionsOrders = collect();

            foreach (['options_orders', 'option_orders'] as $collection) {
                try {
                    $optionsOrders = $optionsOrders->merge(
                        DB::connection('mongodb')
                            ->table($collection)
                            ->orderByDesc('created_at')
                            ->limit(2000)
                            ->get()
                    );
                } catch (\Throwable $exception) {
                    // Keep admin orders page alive even if a legacy collection does not exist.
                }
            }

            $optionsOrders = $optionsOrders
                ->unique(fn ($order) => (string)($order->order_uid ?? $order->_id ?? spl_object_id($order)))
                ->values()
                ->map(function ($order) {
                    $order->market_type = 'options';
                    $order->symbol = $order->symbol ?? $order->underlying ?? '-';
                    $order->display_symbol = strtoupper($order->option_symbol ?? $order->contract_symbol ?? $order->symbol ?? $order->underlying ?? '-');
                    $order->display_qty = $order->estimated_quantity ?? $order->quantity ?? $order->qty ?? '-';
                    $order->display_price = $order->execution_price ?? $order->price ?? $order->entry_price ?? $order->premium ?? 0;
                    $order->display_margin = $order->margin ?? 0;
                    $order->display_fee = $order->fee_charged ?? $order->estimated_fee ?? 0;

                    return $order;
                });

            $orders = $orders->merge($optionsOrders);
        }

        $users = DB::connection('mongodb')
            ->table('users')
            ->get()
            ->keyBy(function ($user) {
                return (string)($user->uid ?? $user->_id ?? '');
            });

        $orders = $orders->map(function ($order) use ($users, $positionsByMarket) {
            $uid = (string)($order->user_uid ?? $order->uid ?? $order->user_id ?? '');
            $user = $users->get($uid);
            $marketType = (string)($order->market_type ?? 'futures');
            $orderUid = (string)($order->order_uid ?? '');

            $order->display_user_name = $order->user_name ?? $order->name ?? ($user->name ?? 'Unknown User');
            $order->display_user_email = $order->user_email ?? $order->email ?? ($user->email ?? '-');
            $order->user_db_id = $user ? (string)($user->_id ?? $user->id ?? '') : '';

            // Close orders are generated internally when a position is closed.
            // They should not create a second admin table row. The entry order row below
            // reads its linked position and shows Active while open, Filled after close.
            $order->admin_hide_order = $this->isCloseOrder($order);

            $position = $positionsByMarket[$marketType]->get($orderUid);

            if ($position) {
                $positionStatus = strtolower((string)($position->status ?? 'open'));

                $order->linked_position_uid = $position->position_uid ?? null;
                $order->display_status = $positionStatus === 'open' ? 'active' : 'filled';
                $order->display_qty = $position->quantity ?? $order->display_qty;
                $order->display_price = $position->entry_price ?? $order->display_price;
                $order->display_margin = $position->margin ?? $order->display_margin;
                $order->display_fee = round(
                    (float)($position->fee_paid ?? $order->display_fee ?? 0) +
                    (float)($position->closing_fee ?? 0),
                    8
                );
                $order->display_date = $positionStatus === 'open'
                    ? ($position->opened_at ?? $position->created_at ?? $order->created_at ?? null)
                    : ($position->closed_at ?? $position->updated_at ?? $order->updated_at ?? $order->created_at ?? null);
            } else {
                $order->display_status = $this->normalizeOrderStatus($order->status ?? 'pending');
                $order->display_date = $order->created_at ?? $order->updated_at ?? null;
            }

            $order->search_text = strtolower(implode(' ', [
                $order->display_user_name,
                $order->display_user_email,
                $order->user_uid ?? '',
                $order->order_uid ?? '',
                $order->linked_position_uid ?? '',
                $order->mode ?? '',
                $order->symbol ?? '',
                $order->display_symbol ?? '',
                $order->option_symbol ?? '',
                $order->underlying ?? '',
                $order->side ?? '',
                $order->contract_type ?? '',
                $order->type ?? '',
                $order->order_type ?? '',
                $order->display_status ?? '',
                $order->status ?? '',
                $order->market_type ?? '',
            ]));

            return $order;
        });

        // Summary cards use the selected market/search/symbol context,
        // but ignore status/side/type chips so admins can see the total picture.
        $summaryOrders = $orders->filter(function ($order) use ($symbol, $search) {
            if ($order->admin_hide_order ?? false) {
                return false;
            }

            $orderSymbol = strtoupper((string)($order->display_symbol ?? $order->symbol ?? $order->underlying ?? ''));

            if ($symbol !== '' && !str_contains($orderSymbol, $symbol)) {
                return false;
            }

            if ($search !== '' && !str_contains($order->search_text, $search)) {
                return false;
            }

            return true;
        })->values();

        $orderStats = [
            'total' => $summaryOrders->count(),
            'pending' => $summaryOrders->filter(fn ($order) => strtolower((string)($order->display_status ?? $order->status ?? '')) === 'pending')->count(),
            'open' => $summaryOrders->filter(fn ($order) => in_array(strtolower((string)($order->display_status ?? $order->status ?? '')), ['active', 'open'], true))->count(),
            'buy' => $summaryOrders->filter(fn ($order) => in_array(strtolower((string)($order->side ?? $order->contract_type ?? '')), ['buy', 'long', 'call'], true))->count(),
        ];

        $orders = $orders->filter(function ($order) use ($status, $type, $side, $symbol, $search) {
            if ($order->admin_hide_order ?? false) {
                return false;
            }

            $orderStatus = strtolower((string)($order->display_status ?? $order->status ?? ''));
            $orderType = strtolower((string)($order->type ?? $order->order_type ?? ''));
            $orderSymbol = strtoupper((string)($order->display_symbol ?? $order->symbol ?? $order->underlying ?? ''));

            if ($status !== 'all' && $orderStatus !== strtolower($status)) {
                return false;
            }

            if ($type !== 'all' && !str_contains($orderType, strtolower($type))) {
                return false;
            }

            if ($side !== 'all') {
                $orderSide = strtolower((string)($order->side ?? $order->contract_type ?? ''));
                if ($side === 'buy' && !in_array($orderSide, ['buy', 'long', 'call'], true)) {
                    return false;
                }

                if ($side === 'sell' && !in_array($orderSide, ['sell', 'short', 'put'], true)) {
                    return false;
                }
            }

            if ($symbol !== '' && !str_contains($orderSymbol, $symbol)) {
                return false;
            }

            if ($search !== '' && !str_contains($order->search_text, $search)) {
                return false;
            }

            return true;
        })->sortByDesc(function ($order) {
            return strtotime((string)($order->display_date ?? $order->created_at ?? $order->updated_at ?? now()));
        })->values();

        $orders = AdminPaginator::paginate($orders, $request, 10);

        return view($view, compact('orders', 'market', 'status', 'type', 'side', 'symbol', 'search', 'orderStats'));
    }

    private function positionsByOrder(string $marketType): Collection
    {
        $collection = $marketType === 'options' ? 'options_positions' : 'futures_positions';

        try {
            return DB::connection('mongodb')
                ->table($collection)
                ->orderByDesc('created_at')
                ->limit(5000)
                ->get()
                ->filter(fn ($position) => !empty($position->order_uid))
                ->keyBy(fn ($position) => (string) $position->order_uid);
        } catch (\Throwable $exception) {
            return collect();
        }
    }

    private function isCloseOrder(object $order): bool
    {
        $reduceOnly = filter_var($order->reduce_only ?? false, FILTER_VALIDATE_BOOLEAN);

        return $reduceOnly || !empty($order->linked_position_uid);
    }

    private function normalizeOrderStatus(string $status): string
    {
        $status = strtolower(trim($status));

        return match ($status) {
            'open' => 'active',
            'closed' => 'filled',
            default => $status ?: 'pending',
        };
    }
}
