<?php

namespace App\Http\Middleware;

use App\Services\JwtTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->query('token') ?: $request->input('token');

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Authorization token is required.',
            ], 401);
        }

        try {
            $payload = app(JwtTokenService::class)->decodeAccessToken($token);

            if (($payload->type ?? null) !== 'access') {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token type.',
                ], 401);
            }

            $user = DB::connection('mongodb')
                ->table('users')
                ->where('uid', $payload->sub)
                ->first();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found.',
                ], 401);
            }

            $userArr = (array) $user;
            $userStatus = strtolower((string) ($userArr['status'] ?? 'active'));
            $deletionStatus = strtolower((string) ($userArr['deletion_status'] ?? 'none'));

            // If account is deleted or approved for deletion, block all authenticated requests
            // except allow /api/account/status so frontend monitor gets the exact state
            $isAccountStatusRoute = $request->is('api/account/status');

            if (($userStatus === 'deleted' || $deletionStatus === 'approved') && ! $isAccountStatusRoute) {
                return response()->json([
                    'success' => false,
                    'account_deleted' => true,
                    'message' => 'Your account has been deleted / closed by administration.',
                ], 401);
            }

            $request->attributes->set('auth_user', $userArr);

            return $next($request);
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.',
            ], 401);
        }
    }
}