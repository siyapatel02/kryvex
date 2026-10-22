<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountStatusService;
use Illuminate\Http\Request;

class AccountStatusController extends Controller
{
    /**
     * Return current user account status for frontend pages.
     *
     * JWT middleware stores the authenticated user in request attribute `auth_user`,
     * so this controller reads from that project-specific location instead of Auth::user().
     */
    public function show(Request $request)
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

        $status = AccountStatusService::syncByUid($userUid);

        return response()->json([
            'success' => true,
            'account_status' => $status,
            'kyc_status' => is_array($authUser) ? ($authUser['kyc_status'] ?? 'not_submitted') : ($authUser->kyc_status ?? 'not_submitted'),
            'kyc_verified_at' => is_array($authUser) ? ($authUser['kyc_verified_at'] ?? null) : ($authUser->kyc_verified_at ?? null),
        ]);
    }
}
