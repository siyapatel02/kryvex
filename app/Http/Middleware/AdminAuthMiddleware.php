<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $adminUid = session('admin_uid');

        if (! $adminUid) {
            return redirect()->route('admin.login');
        }

        $admin = DB::connection('mongodb')
            ->table('admins')
            ->where('uid', $adminUid)
            ->first();

        if (! $admin || ($admin->status ?? 'inactive') !== 'active') {
            session()->forget([
                'admin_uid',
                'admin_name',
                'admin_email',
                'admin_role',
            ]);

            return redirect()->route('admin.login')
                ->with('error', 'Admin session expired. Please login again.');
        }

        $request->attributes->set('auth_admin', (array) $admin);

        return $next($request);
    }
}