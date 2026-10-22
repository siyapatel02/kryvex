<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

class PageController extends Controller
{
    public function home()
    {
        return view('web.home');
    }

    public function markets()
    {
        return view('web.markets');
    }

    public function futures(?string $symbol = null)
    {
        return view('web.futures', [
            'symbol' => strtoupper($symbol ?? 'BTCUSDT'),
        ]);
    }

    public function spot(?string $symbol = null)
    {
        $rawSymbol = strtoupper(str_replace(['/', '-', '_', ' '], '', $symbol ?? 'BTCUSDT'));
        $pairs = \App\Services\SpotTradingService::SUPPORTED_PAIRS;

        if (!isset($pairs[$rawSymbol])) {
            if (str_ends_with($rawSymbol, 'USDT') && strlen($rawSymbol) > 4) {
                $base = substr($rawSymbol, 0, -4);
                $pairs[$rawSymbol] = [
                    'base' => $base,
                    'quote' => 'USDT',
                    'name' => $base . '/USDT',
                    'price_precision' => 4,
                    'amount_precision' => 2,
                    'min_amount' => 0.01,
                    'min_cost' => 5,
                ];
                $symbol = $rawSymbol;
            } else {
                $symbol = 'BTCUSDT';
            }
        } else {
            $symbol = $rawSymbol;
        }

        return view('web.spot', [
            'symbol' => $symbol,
            'pairs' => $pairs,
            'pairConfig' => $pairs[$symbol] ?? ['base' => 'BTC', 'quote' => 'USDT', 'price_precision' => 2, 'amount_precision' => 4],
        ]);
    }

    public function login()
    {
        return view('web.auth.login');
    }

    public function register()
    {
        return view('web.auth.register');
    }
    public function options(?string $symbol = null)
    {
        $symbol = strtoupper($symbol ?: 'BTCUSDT');

        $allowedSymbols = ['BTCUSDT', 'ETHUSDT', 'SOLUSDT', 'XAUUSDT'];

        if (! in_array($symbol, $allowedSymbols, true)) {
            return redirect()->route('options', ['symbol' => 'btcusdt']);
        }

        return view('web.options', [
            'selectedSymbol' => $symbol,
            'allowedSymbols' => $allowedSymbols,
        ]);
    }

    public function dashboard()
    {
        return view('web.dashboard');
    }

    public function settings()
    {
        return view('web.settings');
    }

    public function learn()
    {
        return view('web.learn');
    }

    public function wallet()
    {
        return view('web.wallet');
    }

    public function calendar()
    {
        return view('web.calendar');
    }

    public function research(?string $id = null)
    {
        return view('web.research', [
            'selectedId' => $id,
        ]);
    }

    public function news(?string $id = null)
    {
        return view('web.news', [
            'selectedId' => $id,
        ]);
    }

    public function mobileSelfie(string $token)
    {
        return view('web.mobile_selfie', [
            'token' => $token,
        ]);
    }

    public function kyc()
    {
        return view('web.kyc');
    }

    public function portfolio()
    {
        return view('web.portfolio');
    }

    public function terms()
    {
        return view('web.terms');
    }

    public function privacy()
    {
        return view('web.privacy');
    }

    public function invite()
    {
        return view('web.invite');
    }
}
