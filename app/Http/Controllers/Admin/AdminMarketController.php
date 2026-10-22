<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Helpers\AuditLogger;
use App\Support\AdminPaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminMarketController extends Controller
{
    public function index(Request $request)
    {
        $search = strtoupper(trim((string) $request->query('search')));
        $status = $request->query('status', 'all');

        $markets = DB::connection('mongodb')
            ->table('admin_markets')
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($market) {
                $market->market_id = (string)($market->_id ?? $market->id ?? '');
                return $market;
            })
            ->filter(function ($market) use ($search, $status) {
                if ($status !== 'all' && ($market->status ?? '') !== $status) {
                    return false;
                }

                if ($search !== '') {
                    $text = strtoupper(implode(' ', [
                        $market->symbol ?? '',
                        $market->base_asset ?? '',
                        $market->quote_asset ?? '',
                        $market->status ?? '',
                    ]));

                    if (!str_contains($text, $search)) {
                        return false;
                    }
                }

                return true;
            })
            ->values();

        $markets = AdminPaginator::paginate($markets, $request, 10);

        return view('admin.markets.index', compact('markets', 'search', 'status'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', 'max:30'],
            'base_asset' => ['required', 'string', 'max:20'],
            'quote_asset' => ['required', 'string', 'max:20'],
            'min_qty' => ['required', 'numeric', 'min:0'],
            'max_leverage' => ['required', 'integer', 'min:1', 'max:200'],
            'maker_fee' => ['required', 'numeric', 'min:0'],
            'taker_fee' => ['required', 'numeric', 'min:0'],
            'display_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $symbol = strtoupper($validated['symbol']);

        $exists = DB::connection('mongodb')
            ->table('admin_markets')
            ->where('symbol', $symbol)
            ->first();

        if ($exists) {
            return back()->with('error', 'Market already exists.');
        }

        DB::connection('mongodb')
            ->table('admin_markets')
            ->insert([
                'symbol' => $symbol,
                'base_asset' => strtoupper($validated['base_asset']),
                'quote_asset' => strtoupper($validated['quote_asset']),
                'status' => 'active',
                'futures_enabled' => $request->boolean('futures_enabled', true),
                'options_enabled' => $request->boolean('options_enabled', true),
                'maintenance_mode' => $request->boolean('maintenance_mode'),
                'min_qty' => (float) $validated['min_qty'],
                'max_leverage' => (int) $validated['max_leverage'],
                'maker_fee' => (float) $validated['maker_fee'],
                'taker_fee' => (float) $validated['taker_fee'],
                'display_order' => (int) ($validated['display_order'] ?? 99),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        AuditLogger::log(
            'create',
            'markets',
            'Market created: ' . $symbol,
            [
                'symbol' => $symbol,
                'base_asset' => strtoupper($validated['base_asset']),
                'quote_asset' => strtoupper($validated['quote_asset']),
            ]
        );

        return back()->with('success', 'Market added successfully.');
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:active,inactive'],
            'futures_enabled' => ['nullable'],
            'options_enabled' => ['nullable'],
            'maintenance_mode' => ['nullable'],
            'min_qty' => ['required', 'numeric', 'min:0'],
            'max_leverage' => ['required', 'integer', 'min:1', 'max:200'],
            'maker_fee' => ['required', 'numeric', 'min:0'],
            'taker_fee' => ['required', 'numeric', 'min:0'],
            'display_order' => ['required', 'integer', 'min:1'],
        ]);

        $market = DB::connection('mongodb')
            ->table('admin_markets')
            ->where('_id', $id)
            ->first();

        DB::connection('mongodb')
            ->table('admin_markets')
            ->where('_id', $id)
            ->update([
                'status' => $validated['status'],
                'futures_enabled' => $request->boolean('futures_enabled'),
                'options_enabled' => $request->boolean('options_enabled'),
                'maintenance_mode' => $request->boolean('maintenance_mode'),
                'min_qty' => (float) $validated['min_qty'],
                'max_leverage' => (int) $validated['max_leverage'],
                'maker_fee' => (float) $validated['maker_fee'],
                'taker_fee' => (float) $validated['taker_fee'],
                'display_order' => (int) $validated['display_order'],
                'updated_at' => now(),
            ]);

        AuditLogger::log(
            'update',
            'markets',
            'Market settings updated: ' . ($market->symbol ?? $id),
            [
                'market_id' => $id,
                'symbol' => $market->symbol ?? null,
                'status' => $validated['status'],
                'futures_enabled' => $request->boolean('futures_enabled'),
                'options_enabled' => $request->boolean('options_enabled'),
                'maintenance_mode' => $request->boolean('maintenance_mode'),
            ]
        );

        return back()->with('success', 'Market updated successfully.');
    }

    public function toggle(string $id)
    {
        $market = DB::connection('mongodb')
            ->table('admin_markets')
            ->where('_id', $id)
            ->first();

        if (!$market) {
            return back()->with('error', 'Market not found.');
        }

        $newStatus = ($market->status ?? 'active') === 'active' ? 'inactive' : 'active';

        DB::connection('mongodb')
            ->table('admin_markets')
            ->where('_id', $id)
            ->update([
                'status' => $newStatus,
                'updated_at' => now(),
            ]);

        AuditLogger::log(
            'update',
            'markets',
            'Market status toggled: ' . ($market->symbol ?? $id) . ' to ' . $newStatus,
            [
                'market_id' => $id,
                'symbol' => $market->symbol ?? null,
                'status' => $newStatus,
            ]
        );

        return back()->with('success', 'Market status updated.');
    }
}