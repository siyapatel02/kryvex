<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminRiskController extends Controller
{
    public function index(Request $request)
    {
        $search = strtolower(trim((string) $request->query('search')));
        $symbol = strtoupper(trim((string) $request->query('symbol')));
        $risk = $request->query('risk', 'all');

        $rawPositions = DB::connection('mongodb')
            ->table('futures_positions')
            ->where('status', 'open')
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get();

        $users = DB::connection('mongodb')
            ->table('users')
            ->get()
            ->keyBy(function ($user) {
                return (string)($user->uid ?? $user->_id ?? '');
            });

        $positions = $rawPositions->map(function ($position) use ($users) {
            $uid = (string)($position->user_uid ?? '');
            $user = $users->get($uid);

            $position->display_user_name = $position->user_name ?? ($user->name ?? 'Unknown User');
            $position->display_user_email = $position->user_email ?? ($user->email ?? '-');

            $margin = (float)($position->margin ?? 0);
            $notional = (float)($position->notional_value ?? 0);
            $leverage = (float)($position->leverage ?? 0);

            $position->risk_score = 0;

            if ($leverage >= 20) {
                $position->risk_score += 40;
            } elseif ($leverage >= 10) {
                $position->risk_score += 25;
            }

            if ($notional >= 100000) {
                $position->risk_score += 35;
            } elseif ($notional >= 25000) {
                $position->risk_score += 20;
            }

            if ($margin >= 10000) {
                $position->risk_score += 25;
            } elseif ($margin >= 2000) {
                $position->risk_score += 15;
            }

            if ($position->risk_score >= 70) {
                $position->risk_level = 'high';
            } elseif ($position->risk_score >= 35) {
                $position->risk_level = 'medium';
            } else {
                $position->risk_level = 'low';
            }

            $position->search_text = strtolower(implode(' ', [
                $position->display_user_name,
                $position->display_user_email,
                $position->user_uid ?? '',
                $position->position_uid ?? '',
                $position->symbol ?? '',
                $position->side ?? '',
                $position->risk_level,
                $position->mode ?? '',
            ]));

            return $position;
        })->filter(function ($position) use ($search, $symbol, $risk) {
            if ($symbol !== '' && !str_contains(strtoupper($position->symbol ?? ''), $symbol)) {
                return false;
            }

            if ($risk !== 'all' && ($position->risk_level ?? '') !== $risk) {
                return false;
            }

            if ($search !== '' && !str_contains($position->search_text, $search)) {
                return false;
            }

            return true;
        })->values();

        $stats = [
            'open_positions' => $positions->count(),
            'high_risk' => $positions->where('risk_level', 'high')->count(),
            'medium_risk' => $positions->where('risk_level', 'medium')->count(),
            'total_exposure' => round($positions->sum('notional_value'), 2),
        ];

        return view('admin.risk.index', compact(
            'positions',
            'stats',
            'search',
            'symbol',
            'risk'
        ));
    }
}