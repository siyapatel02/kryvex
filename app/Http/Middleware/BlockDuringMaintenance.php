<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class BlockDuringMaintenance
{
    /**
     * Blocks user write/action API requests when admin enables maintenance mode.
     *
     * Viewing pages and public market data remains allowed. This middleware is
     * only attached to login/register/password/wallet/trading action routes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = DB::connection('mongodb')
            ->table('admin_settings')
            ->where('key', 'platform')
            ->first();

        if (!empty($settings->maintenance_mode)) {
            return response()->json([
                'success' => false,
                'maintenance_mode' => true,
                'message' => 'Kryvex is currently under maintenance. You can browse pages, but this action is temporarily disabled.',
            ], 503);
        }

        return $next($request);
    }
}
