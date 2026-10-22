<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public static function log(string $action, string $module, string $message, array $meta = []): void
    {
        DB::connection('mongodb')
            ->table('audit_logs')
            ->insert([
                'admin_id' => session('admin_id'),
                'admin_email' => session('admin_email') ?? 'admin@kryvex.com',
                'action' => $action,
                'module' => $module,
                'message' => $message,
                'meta' => $meta,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
    }
}