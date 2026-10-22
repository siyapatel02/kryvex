<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class PlatformStatusController extends Controller
{
    /**
     * Public platform status endpoint for user-side UI.
     *
     * The frontend uses this to know if admin enabled maintenance mode.
     * This endpoint stays public because visitors must see the maintenance
     * notice before trying login, register, wallet, futures or options actions.
     */
    public function show()
    {
        $maintenanceMode = false;

        try {
            $settings = DB::connection('mongodb')
                ->table('admin_settings')
                ->where('key', 'platform')
                ->first();

            if ($settings) {
                $settingsArr = (array) $settings;
                $maintenanceMode = (bool) ($settingsArr['maintenance_mode'] ?? false);
            }
        } catch (\Throwable $e) {
            // Fallback gracefully if database is unreachable
            $maintenanceMode = false;
        }

        return response()->json([
            'success' => true,
            'maintenance_mode' => $maintenanceMode,
            'message' => $maintenanceMode
                ? 'Kryvex is currently under maintenance. You can browse the website, but login, wallet and trading actions are temporarily disabled.'
                : 'Kryvex is operating normally.',
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
