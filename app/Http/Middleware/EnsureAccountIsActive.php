<?php

namespace App\Http\Middleware;

use App\Services\AccountStatusService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    /**
     * Block wallet/trading write actions for suspended or blocked users.
     *
     * Read-only API routes remain available so user can still see status/reason.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->attributes->get('auth_user');

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userUid = is_array($authUser)
            ? ($authUser['uid'] ?? $authUser['_id'] ?? null)
            : ($authUser->uid ?? $authUser->_id ?? null);

        $accountStatus = AccountStatusService::syncByUid($userUid);

        if ($accountStatus && in_array($accountStatus['state'], ['suspended', 'blocked', 'deletion_pending', 'deleted'], true)) {
            $msg = 'Your account is currently under review for deletion. Trading and wallet actions are disabled.';
            if ($accountStatus['state'] === 'deleted') {
                $msg = 'Your account has been closed/deleted.';
            } elseif ($accountStatus['state'] === 'blocked') {
                $msg = 'Your account is blocked.';
            } elseif ($accountStatus['state'] === 'suspended') {
                $msg = 'Your account is suspended until ' . ($accountStatus['until'] ?? '-');
            }

            return response()->json([
                'success' => false,
                'message' => $msg,
                'account_status' => $accountStatus,
            ], 423);
        }

        return $next($request);
    }
}
