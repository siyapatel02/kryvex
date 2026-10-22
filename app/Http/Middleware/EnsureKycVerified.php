<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKycVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->attributes->get('auth_user') ?? auth()->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Convert MongoDB document object to array if needed
        $status = is_array($user) ? ($user['kyc_status'] ?? 'unverified') : ($user->kyc_status ?? 'unverified');

        if (!in_array(strtolower($status), ['verified', 'approved'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'KYC verification is required for this action.',
            ], 403);
        }

        return $next($request);
    }
}
