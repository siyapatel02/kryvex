<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\JwtTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;

class GoogleAuthController extends Controller
{
    public function redirect()
    {
        // Safety fallback: frontend normally shows the maintenance popup before this link opens.
        // This backend check prevents Google auth from starting if JavaScript is bypassed.
        if ($this->isMaintenanceMode()) {
            return redirect()
                ->route('login')
                ->with('error', 'Kryvex is currently under maintenance. Login is temporarily disabled.');
        }

        /** @var AbstractProvider $provider */
        $provider = Socialite::driver('google')->stateless();

        if (! filter_var(env('GOOGLE_OAUTH_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN)) {
            $provider->setHttpClient(new \GuzzleHttp\Client([
                'verify' => false,
            ]));
        }

        return $provider->redirect();
    }

    public function callback(JwtTokenService $jwtTokenService)
    {
        try {
            /** @var AbstractProvider $provider */
            $provider = Socialite::driver('google')->stateless();

            if (! filter_var(env('GOOGLE_OAUTH_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN)) {
                $provider->setHttpClient(new \GuzzleHttp\Client([
                    'verify' => false,
                ]));
            }

            try {
                $googleUser = $provider->user();
            } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
                // Primary fallback: retry stateless user resolution if state/session cookie mismatch occurred on fresh browser launch
                $googleUser = Socialite::driver('google')->stateless();
                if (! filter_var(env('GOOGLE_OAUTH_SSL_VERIFY', true), FILTER_VALIDATE_BOOLEAN)) {
                    $googleUser->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
                }
                $googleUser = $googleUser->user();
            }

            $email = strtolower((string) $googleUser->getEmail());

            if (! $email) {
                return view('web.auth.google-callback', [
                    'success' => false,
                    'message' => 'Google account email was not received.',
                    'payload' => null,
                ]);
            }

            $existingUser = DB::connection('mongodb')
                ->table('users')
                ->where('email', $email)
                ->first();

            if ($existingUser) {
                $user = (array) $existingUser;

                DB::connection('mongodb')
                    ->table('users')
                    ->where('uid', $user['uid'])
                    ->update([
                        'google_id' => (string) $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                        'email_verified' => true,
                        'email_verified_at' => now(),
                        'auth_provider' => $user['auth_provider'] ?? 'google',
                        'updated_at' => now(),
                    ]);

                $user = (array) DB::connection('mongodb')
                    ->table('users')
                    ->where('uid', $user['uid'])
                    ->first();
            } else {
                $user = [
                    'uid' => (string) Str::uuid(),
                    'client_id' => \App\Http\Controllers\Api\AuthController::generateUniqueClientId(),
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Google User',
                    'email' => $email,
                    'phone' => null,
                    'password' => null,
                    'google_id' => (string) $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'auth_provider' => 'google',
                    'role' => 'user',
                    'status' => 'active',
                    'email_verified' => true,
                    'email_verified_at' => now(),
                    'kyc_status' => 'not_submitted',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                DB::connection('mongodb')
                    ->table('users')
                    ->insert($user);

                // Send welcome email only for first-time Google registration.
                // Existing Google users do not receive this email on normal login.
                $this->sendGoogleRegistrationWelcomeEmail($user);
            }

            if (($user['status'] ?? 'active') !== 'active') {
                return view('web.auth.google-callback', [
                    'success' => false,
                    'message' => 'Your account is not active.',
                    'payload' => null,
                ]);
            }

            $tokens = $this->issueTokens($user, $jwtTokenService);
            $isNewUser = !$existingUser;

            return view('web.auth.google-callback', [
                'success' => true,
                'message' => 'Google login successful.',
                'is_new_user' => $isNewUser,
                'payload' => [
                    'user' => $this->safeUser($user),
                    'tokens' => $tokens,
                    'is_new_user' => $isNewUser,
                ],
            ]);
        } catch (\Throwable $exception) {
            Log::error('Google login failed', [
                'message' => $exception->getMessage(),
                'class' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return view('web.auth.google-callback', [
                'success' => false,
                'message' => app()->environment('local')
                    ? 'Google login failed: ' . $exception->getMessage()
                    : 'Google login failed. Please try again.',
                'payload' => null,
            ]);
        }
    }

    /**
     * Sends a one-time welcome email when a new user signs up with Google.
     * This is intentionally not called for existing Google login sessions.
     */
    private function sendGoogleRegistrationWelcomeEmail(array $user): void
    {
        $email = $user['email'] ?? null;
        $name = $user['name'] ?? 'Kryvex User';

        if (! $email) {
            return;
        }

        try {
            $subject = 'Welcome to Kryvex';

            $html = '
                <div style="font-family: Arial, sans-serif; background:#f6f8fb; padding:24px;">
                    <div style="max-width:560px; margin:auto; background:#ffffff; border-radius:14px; padding:26px; border:1px solid #e5e7eb;">
                        <h2 style="margin:0 0 12px; color:#111827;">Welcome to Kryvex</h2>
                        <p style="color:#374151; margin:0 0 12px;">Hello ' . e($name) . ',</p>
                        <p style="color:#374151; line-height:1.55; margin:0 0 14px;">
                            Your Kryvex account has been registered successfully using Google sign-in.
                        </p>
                        <div style="background:#f3f4f6; border-radius:12px; padding:14px; color:#111827; margin:16px 0;">
                            <strong>Login method:</strong> Google<br>
                            <strong>Email:</strong> ' . e($email) . '
                        </div>
                        <p style="color:#6b7280; font-size:13px; line-height:1.5; margin:0;">
                            You can now access your Kryvex dashboard, wallet, futures and options demo trading features.
                            If this was not you, please contact support immediately.
                        </p>
                    </div>
                </div>
            ';

            Mail::html($html, function ($message) use ($email, $subject) {
                $message->to($email)->subject($subject);
            });
        } catch (\Throwable $exception) {
            Log::warning('Google registration welcome email failed', [
                'email' => $email,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function isMaintenanceMode(): bool
    {
        try {
            $settingsArray = \Illuminate\Support\Facades\Cache::remember('platform_settings', 300, function () {
                $settings = DB::connection('mongodb')
                    ->table('admin_settings')
                    ->where('key', 'platform')
                    ->first();
                return $settings ? json_decode(json_encode($settings), true) : null;
            });
            return (bool) ($settingsArray['maintenance_mode'] ?? false);
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function issueTokens(array $user, JwtTokenService $jwtTokenService): array
    {
        $accessToken = $jwtTokenService->createAccessToken($user);
        $refreshToken = $jwtTokenService->createRefreshToken();

        DB::connection('mongodb')
            ->table('api_refresh_tokens')
            ->insert([
                'token_uid' => (string) Str::uuid(),
                'user_uid' => $user['uid'],
                'token_hash' => $jwtTokenService->hashRefreshToken($refreshToken),
                'expires_at' => now()->addDays((int) env('JWT_REFRESH_TTL_DAYS', 30)),
                'revoked_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in_minutes' => (int) env('JWT_ACCESS_TTL_MINUTES', 1440),
        ];
    }

    private function safeUser(array $user): array
    {
        if (empty($user['client_id']) && !empty($user['uid'])) {
            $clientId = \App\Http\Controllers\Api\AuthController::generateUniqueClientId();
            DB::connection('mongodb')->table('users')->where('uid', $user['uid'])->update(['client_id' => $clientId]);
            $user['client_id'] = $clientId;
        }

        return [
            'uid' => $user['uid'] ?? null,
            'client_id' => $user['client_id'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'phone' => $user['phone'] ?? null,
            'role' => $user['role'] ?? 'user',
            'status' => $user['status'] ?? 'active',
            'email_verified' => (bool) ($user['email_verified'] ?? false),
            'kyc_status' => $user['kyc_status'] ?? 'not_submitted',
            'auth_provider' => $user['auth_provider'] ?? 'google',
            'avatar' => $user['avatar'] ?? null,
            'base_currency' => $user['base_currency'] ?? null,
            'is_2fa_enabled' => (bool) ($user['is_2fa_enabled'] ?? false),
        ];
    }
}
