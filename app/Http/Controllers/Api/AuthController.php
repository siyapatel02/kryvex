<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\JwtTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['required', 'regex:/^[0-9]{10}$/'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'referral_code' => ['nullable', 'string', 'max:50'],
            'terms' => ['accepted'],
        ], [
            'email.required' => 'Email address is required.',
            'email.email' => 'Enter a valid email address.',
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Mobile number must be exactly 10 digits.',
            'password.confirmed' => 'Password confirmation does not match.',
            'terms.accepted' => 'Please accept terms and risk disclosure.',
        ]);

        $email = strtolower(trim($validated['email']));
        $name = isset($validated['name']) ? trim($validated['name']) : '';

        $referralCode = !empty($validated['referral_code']) ? trim((string)$validated['referral_code']) : null;
        $referrerUid = null;

        if ($referralCode) {
            $referrer = DB::connection('mongodb')
                ->table('users')
                ->where('client_id', $referralCode)
                ->orWhere('referral_code', $referralCode)
                ->orWhere('uid', $referralCode)
                ->first();

            if ($referrer) {
                $referrerArr = (array) $referrer;
                $referrerUid = $referrerArr['uid'] ?? null;
            }
        }

        $existingActiveUser = DB::connection('mongodb')
            ->table('users')
            ->where('email', $email)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'deleted');
            })
            ->where(function ($q) {
                $q->whereNull('deletion_status')->orWhere('deletion_status', '!=', 'approved');
            })
            ->first();

        if ($existingActiveUser) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered. Please login to your account.',
            ], 422);
        }

        $code = (string) random_int(100000, 999999);

        DB::connection('mongodb')
            ->table('pending_email_registrations')
            ->where('email', $email)
            ->delete();

        DB::connection('mongodb')
            ->table('pending_email_registrations')
            ->insert([
                'pending_uid' => (string) Str::uuid(),
                'name' => $name,
                'email' => $email,
                'phone' => $validated['phone'],
                'password_hash' => Hash::make($validated['password']),
                'verification_code_hash' => Hash::make($code),
                'referral_code' => $referralCode,
                'referred_by_uid' => $referrerUid,
                'attempts' => 0,
                // Register OTP expires in 120 seconds for stronger account verification security.
                'expires_at' => now()->addSeconds(120),
                'resend_count' => 0,
                'resend_cooldown_until' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->sendVerificationEmail($email, $name ?: 'Kryvex User', $code);

        return response()->json([
            'success' => true,
            'message' => 'Verification code sent to your email. Please enter the code to complete registration.',
            'data' => [
                'email' => $email,
                'expires_in_seconds' => 120,
                'resend_limit' => 2,
                'resend_cooldown_seconds' => 600,
            ],
        ]);
    }

    /**
     * Resend registration OTP.
     *
     * Rules:
     * - OTP is valid for 120 seconds.
     * - User gets 2 instant resends.
     * - After 2 instant resends, user must wait 10 minutes before sending another code.
     */
    public function resendRegisterCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));

        $pending = DB::connection('mongodb')
            ->table('pending_email_registrations')
            ->where('email', $email)
            ->first();

        if (! $pending) {
            return response()->json([
                'success' => false,
                'message' => 'Registration request not found. Please register again.',
            ], 422);
        }

        $pendingArray = (array) $pending;
        $cooldownUntil = $pendingArray['resend_cooldown_until'] ?? null;

        if ($cooldownUntil && now()->lessThan($cooldownUntil)) {
            return response()->json([
                'success' => false,
                'message' => 'Resend limit reached. Please wait before requesting a new code.',
                'data' => [
                    'cooldown_seconds' => now()->diffInSeconds($cooldownUntil),
                ],
            ], 429);
        }

        $currentResendCount = (int) ($pendingArray['resend_count'] ?? 0);

        if ($currentResendCount >= 2) {
            $cooldownUntil = now()->addMinutes(10);

            DB::connection('mongodb')
                ->table('pending_email_registrations')
                ->where('email', $email)
                ->update([
                    'resend_cooldown_until' => $cooldownUntil,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => false,
                'message' => 'You have already used 2 instant resends. Please wait 10 minutes.',
                'data' => [
                    'cooldown_seconds' => 600,
                ],
            ], 429);
        }

        $code = (string) random_int(100000, 999999);

        DB::connection('mongodb')
            ->table('pending_email_registrations')
            ->where('email', $email)
            ->update([
                'verification_code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds(120),
                'resend_count' => $currentResendCount + 1,
                'updated_at' => now(),
            ]);

        $this->sendVerificationEmail($email, $pendingArray['name'] ?? 'Kryvex User', $code);

        return response()->json([
            'success' => true,
            'message' => 'New verification code sent. It is valid for 120 seconds.',
            'data' => [
                'email' => $email,
                'expires_in_seconds' => 120,
                'resends_left' => max(0, 2 - ($currentResendCount + 1)),
            ],
        ]);
    }

    public function verifyRegister(Request $request, JwtTokenService $jwtTokenService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ], [
            'email.required' => 'Email address is required.',
            'code.required' => 'Verification code is required.',
            'code.digits' => 'Verification code must be 6 digits.',
        ]);

        $email = strtolower(trim($validated['email']));

        $pending = DB::connection('mongodb')
            ->table('pending_email_registrations')
            ->where('email', $email)
            ->first();

        if (! $pending) {
            return response()->json([
                'success' => false,
                'message' => 'Verification request not found. Please register again.',
            ], 422);
        }

        $pendingArray = (array) $pending;

        if (now()->greaterThan($pendingArray['expires_at'])) {
            DB::connection('mongodb')
                ->table('pending_email_registrations')
                ->where('email', $email)
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'Verification code expired. Please register again.',
            ], 422);
        }

        if ((int) ($pendingArray['attempts'] ?? 0) >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Too many wrong attempts. Please register again.',
            ], 422);
        }

        if (! Hash::check($validated['code'], $pendingArray['verification_code_hash'])) {
            DB::connection('mongodb')
                ->table('pending_email_registrations')
                ->where('email', $email)
                ->update([
                    'attempts' => ((int) ($pendingArray['attempts'] ?? 0)) + 1,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code.',
            ], 422);
        }

        $existingActiveUser = DB::connection('mongodb')
            ->table('users')
            ->where('email', $email)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'deleted');
            })
            ->where(function ($q) {
                $q->whereNull('deletion_status')->orWhere('deletion_status', '!=', 'approved');
            })
            ->first();

        if ($existingActiveUser) {
            DB::connection('mongodb')
                ->table('pending_email_registrations')
                ->where('email', $email)
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'This email is already registered. Please login.',
            ], 422);
        }

        $user = [
            'uid' => (string) Str::uuid(),
            'client_id' => self::generateUniqueClientId(),
            'name' => $pendingArray['name'],
            'email' => $pendingArray['email'],
            'phone' => $pendingArray['phone'],
            'password' => $pendingArray['password_hash'],
            'referred_by' => $pendingArray['referred_by_uid'] ?? null,
            'referral_code' => $pendingArray['referral_code'] ?? null,
            'google_id' => null,
            'avatar' => null,
            'auth_provider' => 'email',
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

        DB::connection('mongodb')
            ->table('pending_email_registrations')
            ->where('email', $email)
            ->delete();

        $tokens = $this->issueTokens($user, $jwtTokenService);

        return response()->json([
            'success' => true,
            'message' => 'Email verified and account created successfully.',
            'data' => [
                'user' => $this->safeUser($user),
                'tokens' => $tokens,
            ],
        ], 201);
    }

    public static function generateUniqueClientId(): string
    {
        do {
            $clientId = (string) random_int(100000, 999999);
            $exists = DB::connection('mongodb')->table('users')->where('client_id', $clientId)->exists();
        } while ($exists);

        return $clientId;
    }

    public function login(Request $request, JwtTokenService $jwtTokenService)
    {
        $validated = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Email address or Client ID is required.',
            'password.required' => 'Password is required.',
        ]);

        $loginInput = trim($validated['email']);

        try {
            // First search for an active (non-deleted) account
            $user = DB::connection('mongodb')
                ->table('users')
                ->where(function ($query) use ($loginInput) {
                    $query->where('email', strtolower($loginInput))
                          ->orWhere('client_id', $loginInput);
                })
                ->where(function ($query) {
                    $query->whereNull('status')->orWhere('status', '!=', 'deleted');
                })
                ->where(function ($query) {
                    $query->whereNull('deletion_status')->orWhere('deletion_status', '!=', 'approved');
                })
                ->orderBy('created_at', 'desc')
                ->first();

            // If no active account is found, check if a deleted account exists to provide a clear message
            if (! $user) {
                $deletedUser = DB::connection('mongodb')
                    ->table('users')
                    ->where(function ($query) use ($loginInput) {
                        $query->where('email', strtolower($loginInput))
                              ->orWhere('client_id', $loginInput);
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($deletedUser) {
                    $delArr = (array) $deletedUser;
                    $delStatus = strtolower((string) ($delArr['status'] ?? ''));
                    $delAppr = strtolower((string) ($delArr['deletion_status'] ?? ''));

                    if ($delStatus === 'deleted' || $delAppr === 'approved') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Your account has been deleted / closed. You can register a new account using this email.',
                        ], 403);
                    }
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email/client ID or password.',
                ], 401);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection issue. Please check MongoDB Atlas connection or network access.',
            ], 503);
        }

        $user = (array) $user;

        if (($user['auth_provider'] ?? 'email') === 'google' && empty($user['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'This account uses Google login. Please continue with Google.',
            ], 422);
        }

        if (! Hash::check($validated['password'], $user['password'] ?? '')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 401);
        }

        $userStatus = strtolower((string) ($user['status'] ?? 'active'));
        if ($userStatus === 'deleted' || ($user['deletion_status'] ?? '') === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been deleted / closed. If you think this is an error, please contact Kryvex Support.',
            ], 403);
        }

        if ($userStatus !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is currently ' . $userStatus . '. Please contact support.',
            ], 403);
        }

        if (! (bool) ($user['email_verified'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before login.',
            ], 403);
        }

        if ((bool) ($user['is_2fa_enabled'] ?? false)) {
            $code = (string) random_int(100000, 999999);
            $tempToken = (string) Str::uuid();
            $email = $user['email'];

            DB::connection('mongodb')
                ->table('pending_2fa_logins')
                ->where('user_uid', $user['uid'])
                ->delete();

            DB::connection('mongodb')
                ->table('pending_2fa_logins')
                ->insert([
                    'temp_token' => $tempToken,
                    'user_uid' => $user['uid'],
                    'email' => $email,
                    'code_hash' => Hash::make($code),
                    'expires_at' => now()->addSeconds(120)->toIso8601String(),
                    'created_at' => now()->toIso8601String(),
                ]);

            $this->send2FaLoginEmail($email, $user['name'] ?? 'Kryvex User', $code);

            return response()->json([
                'success' => true,
                'requires_2fa' => true,
                'message' => 'Two-Factor Authentication code sent to your email.',
                'data' => [
                    'email' => $email,
                    'temp_token' => $tempToken,
                    'expires_in_seconds' => 120,
                ],
            ]);
        }

        $tokens = $this->issueTokens($user, $jwtTokenService);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $this->safeUser($user),
                'tokens' => $tokens,
            ],
        ]);
    }

    public function refresh(Request $request, JwtTokenService $jwtTokenService)
    {
        $validated = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $tokenHash = $jwtTokenService->hashRefreshToken($validated['refresh_token']);

        $refreshRecord = DB::connection('mongodb')
            ->table('api_refresh_tokens')
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->first();

        if (! $refreshRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid refresh token.',
            ], 401);
        }

        if (now()->greaterThan($refreshRecord->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token expired.',
            ], 401);
        }

        $user = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $refreshRecord->user_uid)
            ->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 401);
        }

        $user = (array) $user;

        return response()->json([
            'success' => true,
            'message' => 'Access token refreshed.',
            'data' => [
                'access_token' => $jwtTokenService->createAccessToken($user),
                'token_type' => 'Bearer',
                'expires_in_minutes' => (int) env('JWT_ACCESS_TTL_MINUTES', 1440),
            ],
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user fetched.',
            'data' => [
                'user' => $this->safeUser((array) $request->attributes->get('auth_user')),
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login again.',
            ], 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'min:2', 'max:100'],
            'nickname' => ['nullable', 'string', 'min:2', 'max:100'],
        ], [
            'name.min' => 'Name must be at least 2 characters long.',
            'nickname.min' => 'Nickname must be at least 2 characters long.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();
        $newName = trim($validated['name'] ?? ($validated['nickname'] ?? ''));

        if (empty($newName)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter a valid name or nickname.',
            ], 422);
        }

        $userUid = (string) $user['uid'];

        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update([
                'name' => $newName,
                'nickname' => $newName,
                'updated_at' => now(),
            ]);

        $freshUser = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->first();

        if ($freshUser) {
            $freshUser = $this->safeUser((array) $freshUser);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => $freshUser,
            ],
        ]);
    }

    public function logout(Request $request, JwtTokenService $jwtTokenService)
    {
        $user = $request->attributes->get('auth_user');
        $refreshToken = $request->input('refresh_token');

        $query = DB::connection('mongodb')
            ->table('api_refresh_tokens')
            ->where('user_uid', $user['uid'])
            ->whereNull('revoked_at');

        if ($refreshToken) {
            $query->where('token_hash', $jwtTokenService->hashRefreshToken($refreshToken));
        }

        $query->update([
            'revoked_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ]);
    }

    /**
     * Send password reset OTP.
     *
     * Forgot password OTP intentionally has no resend count limit, but each code
     * expires in 120 seconds and every new code invalidates the previous one.
     */
    public function sendForgotPasswordCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Email address is required.',
        ]);

        $email = strtolower(trim($validated['email']));

        $user = DB::connection('mongodb')
            ->table('users')
            ->where('email', $email)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'deleted');
            })
            ->where(function ($q) {
                $q->whereNull('deletion_status')->orWhere('deletion_status', '!=', 'approved');
            })
            ->orderBy('created_at', 'desc')
            ->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No account found with this email address.',
            ], 422);
        }

        $user = (array) $user;
        $code = (string) random_int(100000, 999999);

        DB::connection('mongodb')
            ->table('password_reset_codes')
            ->where('email', $email)
            ->delete();

        DB::connection('mongodb')
            ->table('password_reset_codes')
            ->insert([
                'reset_uid' => (string) Str::uuid(),
                'user_uid' => $user['uid'] ?? null,
                'email' => $email,
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds(120),
                'used_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->sendPasswordResetEmail($email, $user['name'] ?? 'Kryvex User', $code);

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent. It is valid for 120 seconds.',
            'data' => [
                'email' => $email,
                'expires_in_seconds' => 120,
            ],
        ]);
    }

    /**
     * Verify forgot-password OTP only.
     *
     * Important flow:
     * - OTP itself is valid for 120 seconds only.
     * - Password fields are shown on the frontend only after this endpoint succeeds.
     * - After verification, a short-lived reset token is returned so the user can set
     *   the new password without racing the 120-second OTP countdown.
     */
    public function verifyForgotPasswordCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Reset code is required.',
            'code.digits' => 'Reset code must be 6 digits.',
        ]);

        $email = strtolower(trim($validated['email']));

        $reset = DB::connection('mongodb')
            ->table('password_reset_codes')
            ->where('email', $email)
            ->whereNull('used_at')
            ->first();

        if (! $reset) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset request not found. Please request a new code.',
            ], 422);
        }

        $resetArray = (array) $reset;

        if (now()->greaterThan($resetArray['expires_at'])) {
            DB::connection('mongodb')
                ->table('password_reset_codes')
                ->where('email', $email)
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'Password reset code expired. Please request a new code.',
            ], 422);
        }

        if ((int) ($resetArray['attempts'] ?? 0) >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Too many wrong attempts. Please request a new code.',
            ], 422);
        }

        if (! Hash::check($validated['code'], $resetArray['code_hash'])) {
            DB::connection('mongodb')
                ->table('password_reset_codes')
                ->where('email', $email)
                ->update([
                    'attempts' => ((int) ($resetArray['attempts'] ?? 0)) + 1,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => false,
                'message' => 'Invalid password reset code.',
            ], 422);
        }

        $resetToken = Str::random(64);

        DB::connection('mongodb')
            ->table('password_reset_codes')
            ->where('email', $email)
            ->update([
                'verified_at' => now(),
                'reset_token_hash' => Hash::make($resetToken),
                'reset_token_expires_at' => now()->addMinutes(10),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Code verified. You can now set your new password.',
            'data' => [
                'email' => $email,
                'reset_token' => $resetToken,
                'reset_token_expires_in_seconds' => 600,
            ],
        ]);
    }

    /**
     * Set a new password after forgot-password OTP has been verified.
     */
    public function resetForgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'reset_token.required' => 'Please verify the reset code first.',
            'password.required' => 'New password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.letters' => 'Password must contain at least one letter.',
            'password.mixed' => 'Password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'Password must contain at least one number.',
            'password.symbols' => 'Password must contain at least one special character (!@#$%^&*).',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $email = strtolower(trim($validated['email']));

        $reset = DB::connection('mongodb')
            ->table('password_reset_codes')
            ->where('email', $email)
            ->whereNull('used_at')
            ->first();

        if (! $reset) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset request not found. Please request a new code.',
            ], 422);
        }

        $resetArray = (array) $reset;

        if (empty($resetArray['verified_at']) || empty($resetArray['reset_token_hash'])) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify the reset code before setting a new password.',
            ], 422);
        }

        if (! empty($resetArray['reset_token_expires_at']) && now()->greaterThan($resetArray['reset_token_expires_at'])) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset session expired. Please request a new code.',
            ], 422);
        }

        if (! Hash::check($validated['reset_token'], $resetArray['reset_token_hash'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset session. Please verify the code again.',
            ], 422);
        }

        $targetUid = $resetArray['user_uid'] ?? null;
        $userUpdateQuery = DB::connection('mongodb')->table('users');
        if ($targetUid) {
            $userUpdateQuery->where('uid', $targetUid);
        } else {
            $userUpdateQuery->where('email', $email)
                ->where(function ($q) {
                    $q->whereNull('status')->orWhere('status', '!=', 'deleted');
                });
        }

        $userUpdateQuery->update([
            'password' => Hash::make($validated['password']),
            'auth_provider' => 'email',
            'updated_at' => now(),
        ]);

        DB::connection('mongodb')
            ->table('password_reset_codes')
            ->where('email', $email)
            ->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully. You can login with your new password.',
        ]);
    }

    /**
     * Authenticated profile password change.
     * User must enter the current password before setting a new one.
     */
    public function changePassword(Request $request)
    {
        $newPass = $request->input('password') ?: $request->input('new_password');
        $newPassConfirm = $request->input('password_confirmation') ?: $request->input('new_password_confirmation');

        $request->merge([
            'password' => $newPass,
            'password_confirmation' => $newPassConfirm,
        ]);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ], [
            'current_password.required' => 'Current password is required.',
            'password.required' => 'New password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.letters' => 'Password must contain at least one letter.',
            'password.mixed' => 'Password must contain both uppercase and lowercase letters.',
            'password.numbers' => 'Password must contain at least one number.',
            'password.symbols' => 'Password must contain at least one special character (!@#$%^&*).',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        $authUser = (array) $request->attributes->get('auth_user');
        $userUid = $authUser['uid'] ?? null;

        $user = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        $user = (array) $user;

        if (empty($user['password']) || ! Hash::check($validated['current_password'], $user['password'])) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update([
                'password' => Hash::make($validated['password']),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }

    private function sendVerificationEmail(string $email, string $name, string $code): void
    {
        $subject = 'Your Kryvex verification code';

        $html = '
            <div style="font-family: Arial, sans-serif; background:#f6f8fb; padding:24px;">
                <div style="max-width:520px; margin:auto; background:#ffffff; border-radius:12px; padding:24px; border:1px solid #e5e7eb;">
                    <h2 style="margin:0 0 12px; color:#111827;">Verify your Kryvex account</h2>
                    <p style="color:#374151;">Hello ' . e($name) . ',</p>
                    <p style="color:#374151;">Use this verification code to complete your registration:</p>
                    <div style="font-size:32px; font-weight:800; letter-spacing:6px; color:#111827; background:#f3f4f6; padding:16px; text-align:center; border-radius:10px;">
                        ' . e($code) . '
                    </div>
                    <p style="color:#6b7280; margin-top:18px;">This code will expire in 120 seconds.</p>
                    <p style="color:#6b7280;">If you did not request this, please ignore this email.</p>
                </div>
            </div>
        ';

        Mail::html($html, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }

    /**
     * Password reset email used by login/register/profile forgot password flows.
     */
    private function sendPasswordResetEmail(string $email, string $name, string $code): void
    {
        $subject = 'Your Kryvex password reset code';

        $html = '
            <div style="font-family: Arial, sans-serif; background:#f6f8fb; padding:24px;">
                <div style="max-width:520px; margin:auto; background:#ffffff; border-radius:12px; padding:24px; border:1px solid #e5e7eb;">
                    <h2 style="margin:0 0 12px; color:#111827;">Reset your Kryvex password</h2>
                    <p style="color:#374151;">Hello ' . e($name) . ',</p>
                    <p style="color:#374151;">Use this reset code to set a new password:</p>
                    <div style="font-size:32px; font-weight:800; letter-spacing:6px; color:#111827; background:#f3f4f6; padding:16px; text-align:center; border-radius:10px;">
                        ' . e($code) . '
                    </div>
                    <p style="color:#6b7280; margin-top:18px;">This code will expire in 120 seconds.</p>
                    <p style="color:#6b7280;">If you did not request this, please secure your account.</p>
                </div>
            </div>
        ';

        Mail::html($html, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
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
            $clientId = self::generateUniqueClientId();
            DB::connection('mongodb')->table('users')->where('uid', $user['uid'])->update(['client_id' => $clientId]);
            $user['client_id'] = $clientId;
        }

        $phone = $user['phone'] ?? $user['mobile'] ?? $user['phone_number'] ?? null;
        if (empty($phone) && !empty($user['uid'])) {
            $kyc = DB::connection('mongodb')->table('kyc_verifications')->where('user_uid', $user['uid'])->first();
            if ($kyc) {
                $kycArr = (array) $kyc;
                $phone = $kycArr['phone'] ?? $kycArr['mobile'] ?? $kycArr['phone_number'] ?? ($kycArr['step1_data']['phone'] ?? ($kycArr['personal_info']['phone'] ?? null));
            }
        }

        $myUid = $user['uid'] ?? null;
        $referrals = [];
        $rewards = [];
        $totalReferrals = 0;
        $verifiedReferrals = 0;
        $activeReferrals = 0;
        $referralEarnings = 0.0;

        if ($myUid) {
            $rewardRecords = DB::connection('mongodb')->table('referral_rewards')
                ->where('referrer_uid', $myUid)
                ->orderBy('created_at', 'desc')
                ->get();

            $commissionPerFriend = [];
            foreach ($rewardRecords as $rr) {
                $rrArr = (array) $rr;
                $amt = (float) ($rrArr['amount'] ?? 0);
                $referralEarnings += $amt;
                $friendUid = $rrArr['referred_user_uid'] ?? '';
                if ($friendUid) {
                    $commissionPerFriend[$friendUid] = ($commissionPerFriend[$friendUid] ?? 0) + $amt;
                }

                $rewards[] = [
                    'id' => $rrArr['reward_uid'] ?? '',
                    'date' => isset($rrArr['created_at']) ? (string) $rrArr['created_at'] : null,
                    'created_at' => isset($rrArr['created_at']) ? (string) $rrArr['created_at'] : null,
                    'from' => $rrArr['from'] ?? $rrArr['referred_user_name'] ?? $rrArr['referred_user_email'] ?? 'Friend',
                    'email' => $rrArr['referred_user_email'] ?? null,
                    'type' => $rrArr['type'] ?? 'Trading Commission',
                    'status' => $rrArr['status'] ?? 'paid',
                    'amount' => $amt,
                    'currency' => $rrArr['currency'] ?? 'USDT',
                ];
            }

            $refUsers = DB::connection('mongodb')->table('users')
                ->where('referred_by', $myUid)
                ->get();

            foreach ($refUsers as $ru) {
                $ruArr = (array) $ru;
                $ruUid = $ruArr['uid'] ?? '';
                $isKyc = in_array(strtolower($ruArr['kyc_status'] ?? ''), ['verified', 'approved']);
                
                $hasTrades = DB::connection('mongodb')->table('futures_orders')
                    ->where('user_uid', $ruUid)
                    ->exists()
                    || DB::connection('mongodb')->table('spot_orders')
                    ->where('user_uid', $ruUid)
                    ->exists();

                $totalReferrals++;
                if ($isKyc) {
                    $verifiedReferrals++;
                }
                if ($hasTrades) {
                    $activeReferrals++;
                }

                $referrals[] = [
                    'uid' => $ruUid,
                    'name' => $ruArr['name'] ?? null,
                    'email' => $ruArr['email'] ?? null,
                    'client_id' => $ruArr['client_id'] ?? null,
                    'created_at' => isset($ruArr['created_at']) ? (string) $ruArr['created_at'] : null,
                    'kyc_status' => $ruArr['kyc_status'] ?? 'not_submitted',
                    'is_active' => $hasTrades,
                    'status' => $hasTrades ? 'active' : 'inactive',
                    'commission' => round($commissionPerFriend[$ruUid] ?? 0, 2),
                ];
            }
        }

        return [
            'uid' => $user['uid'] ?? null,
            'client_id' => $user['client_id'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'phone' => $phone,
            'mobile' => $phone,
            'role' => $user['role'] ?? 'user',
            'status' => $user['status'] ?? 'active',
            'email_verified' => (bool) ($user['email_verified'] ?? false),
            'kyc_status' => $user['kyc_status'] ?? 'not_submitted',
            'auth_provider' => $user['auth_provider'] ?? 'email',
            'avatar' => $user['avatar'] ?? null,
            'base_currency' => $user['base_currency'] ?? null,
            'is_2fa_enabled' => (bool) ($user['is_2fa_enabled'] ?? false),
            'referral_code' => $user['client_id'] ?? null,
            'total_referrals' => $totalReferrals,
            'verified_referrals' => $verifiedReferrals,
            'active_referrals' => $activeReferrals,
            'referral_earnings' => round($referralEarnings, 2),
            'referrals' => $referrals,
            'referral_rewards' => $rewards,
            'deletion_status' => $user['deletion_status'] ?? 'none',
            'deletion_reason' => $user['deletion_reason'] ?? null,
            'deletion_requested_at' => isset($user['deletion_requested_at']) ? (string) $user['deletion_requested_at'] : null,
            'created_at' => isset($user['created_at']) ? (string) $user['created_at'] : null,
        ];
    }

    public function verify2FaLogin(Request $request, JwtTokenService $jwtTokenService)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'temp_token' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => '6-digit 2FA OTP code is required.',
            'code.digits' => '2FA OTP code must be exactly 6 digits.',
        ]);

        $email = strtolower(trim($validated['email']));

        $record = DB::connection('mongodb')
            ->table('pending_2fa_logins')
            ->where('temp_token', $validated['temp_token'])
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired 2FA session. Please log in again.',
            ], 422);
        }

        $recordArray = (array) $record;
        $expiresAt = isset($recordArray['expires_at']) ? \Carbon\Carbon::parse((string) $recordArray['expires_at']) : null;

        if ($expiresAt && $expiresAt->isPast()) {
            return response()->json([
                'success' => false,
                'message' => '2FA code has expired. Please click Resend OTP for a new code.',
            ], 422);
        }

        if (! Hash::check($validated['code'], $recordArray['code_hash'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid 2FA verification code. Please check and try again.',
            ], 422);
        }

        DB::connection('mongodb')
            ->table('pending_2fa_logins')
            ->where('email', $email)
            ->delete();

        $user = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $recordArray['user_uid'])
            ->first();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User account not found.'], 404);
        }

        $user = (array) $user;
        $tokens = $this->issueTokens($user, $jwtTokenService);

        return response()->json([
            'success' => true,
            'message' => '2FA verification successful. Login granted.',
            'data' => [
                'user' => $this->safeUser($user),
                'tokens' => $tokens,
            ],
        ]);
    }

    public function resend2FaLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'temp_token' => ['required', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));

        $record = DB::connection('mongodb')
            ->table('pending_2fa_logins')
            ->where('temp_token', $validated['temp_token'])
            ->first();

        if (! $record) {
            return response()->json([
                'success' => false,
                'message' => '2FA session expired. Please attempt login again.',
            ], 422);
        }

        $recordArray = (array) $record;
        $user = DB::connection('mongodb')->table('users')->where('uid', $recordArray['user_uid'])->first();
        $userArray = (array) ($user ?? []);

        $code = (string) random_int(100000, 999999);

        DB::connection('mongodb')
            ->table('pending_2fa_logins')
            ->where('email', $email)
            ->where('temp_token', $validated['temp_token'])
            ->update([
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addSeconds(120)->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ]);

        $this->send2FaLoginEmail($email, $userArray['name'] ?? 'Kryvex User', $code);

        return response()->json([
            'success' => true,
            'message' => 'New 2FA verification code sent to your email. Valid for 120 seconds.',
            'data' => [
                'email' => $email,
                'temp_token' => $validated['temp_token'],
                'expires_in_seconds' => 120,
            ],
        ]);
    }

    public function toggle2Fa(Request $request)
    {
        $authUser = (array) $request->attributes->get('auth_user');
        if (empty($authUser['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $is2fa = (bool) $request->input('is_2fa_enabled');

        DB::connection('mongodb')
            ->table('users')
            ->where('uid', (string) $authUser['uid'])
            ->update([
                'is_2fa_enabled' => $is2fa,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Two-Factor Authentication ' . ($is2fa ? 'enabled (ON).' : 'disabled (OFF).'),
            'data' => [
                'is_2fa_enabled' => $is2fa,
            ],
        ]);
    }

    public function get2FaStatus(Request $request)
    {
        $authUser = (array) $request->attributes->get('auth_user');
        if (empty($authUser['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $user = DB::connection('mongodb')
            ->table('users')
            ->where('uid', (string) $authUser['uid'])
            ->first();

        $is2fa = (bool) (((array) ($user ?? []))['is_2fa_enabled'] ?? false);

        return response()->json([
            'success' => true,
            'data' => [
                'is_2fa_enabled' => $is2fa,
            ],
        ]);
    }

    private function send2FaLoginEmail(string $email, string $name, string $code): void
    {
        $subject = 'Your Kryvex 2FA Login Code';

        $html = '
            <div style="font-family: Arial, sans-serif; background:#f6f8fb; padding:24px;">
                <div style="max-width:520px; margin:auto; background:#ffffff; border-radius:12px; padding:24px; border:1px solid #e5e7eb;">
                    <h2 style="margin:0 0 12px; color:#111827;">Two-Factor Login Verification</h2>
                    <p style="color:#374151;">Hello ' . e($name) . ',</p>
                    <p style="color:#374151;">Use this 6-digit authentication code to complete your login:</p>
                    <div style="font-size:32px; font-weight:800; letter-spacing:6px; color:#111827; background:#f3f4f6; padding:16px; text-align:center; border-radius:10px;">
                        ' . e($code) . '
                    </div>
                    <p style="color:#6b7280; margin-top:18px;">This code is valid for 120 seconds.</p>
                    <p style="color:#6b7280;">If you did not attempt to log in, please change your password immediately.</p>
                </div>
            </div>
        ';

        Mail::html($html, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }

    /**
     * One-time base currency selection (INR or USDT).
     * Once set, cannot be changed without admin action.
     */
    public function setBaseCurrency(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'base_currency' => ['required', 'string', 'in:INR,USDT'],
        ]);

        $currency = strtoupper($validated['base_currency']);

        // Fetch latest user record
        $dbUser = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $user['uid'])
            ->first();

        if (!$dbUser) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }

        // Cast to array to safely access fields
        $dbUser = (array) $dbUser;

        $kycStatus = strtolower((string) ($dbUser['kyc_status'] ?? ($dbUser['kyc'] ?? '')));
        if (!in_array($kycStatus, ['verified', 'approved'])) {
            return response()->json([
                'success' => false,
                'message' => 'KYC verification is required before selecting your Base Currency. Please complete KYC verification first.',
            ], 422);
        }

        // Already set — do not allow re-selection
        if (!empty($dbUser['base_currency'])) {
            return response()->json([
                'success' => false,
                'message' => 'Base currency is already set to ' . $dbUser['base_currency'] . '. Contact support to request a change.',
                'data' => ['base_currency' => $dbUser['base_currency']],
            ], 422);
        }

        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $user['uid'])
            ->update([
                'base_currency' => $currency,
                'base_currency_set_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Base currency set to ' . $currency . ' successfully.',
            'data' => ['base_currency' => $currency],
        ]);
    }

    /**
     * Return current base currency for logged-in user.
     */
    public function getBaseCurrency(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $dbUser = DB::connection('mongodb')
            ->table('users')
            ->where('uid', $user['uid'])
            ->first();

        $dbUser = (array) $dbUser;
        $currency = $dbUser['base_currency'] ?? null;

        return response()->json([
            'success' => true,
            'data' => [
                'base_currency' => $currency,
                'is_set' => !empty($currency),
            ],
        ]);
    }

    /**
     * Get currency change automated validation status, preview conversion, and pending request.
     */
    public function getCurrencyChangeStatus(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $userUid = (string) $user['uid'];

        $dbUser = DB::connection('mongodb')->table('users')->where('uid', $userUid)->first();
        if (!$dbUser) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }
        $dbUser = (array) $dbUser;

        $wallet = DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->first();
        $wallet = (array) ($wallet ?? []);

        $tradingBalance = (float) ($wallet['demo_available_balance'] ?? 0);
        $mainBalanceUsdt = (float) ($wallet['demo_main_balance'] ?? 0);

        // Open positions count
        $futuresPositions = DB::connection('mongodb')->table('futures_positions')
            ->where('user_uid', $userUid)->where('status', 'OPEN')->count();
        $optionPositions = DB::connection('mongodb')->table('option_positions')
            ->where('user_uid', $userUid)->where('status', 'OPEN')->count();
        $openPositionsCount = $futuresPositions + $optionPositions;

        // Open orders count
        $futuresOrders = DB::connection('mongodb')->table('futures_orders')
            ->where('user_uid', $userUid)->whereIn('status', ['NEW', 'PARTIALLY_FILLED', 'OPEN'])->count();
        $optionOrders = DB::connection('mongodb')->table('option_orders')
            ->where('user_uid', $userUid)->whereIn('status', ['NEW', 'PARTIALLY_FILLED', 'OPEN'])->count();
        $openOrdersCount = $futuresOrders + $optionOrders;

        // Pending deposits & withdraws
        $pendingDeposits = DB::connection('mongodb')->table('inr_deposit_requests')
            ->where('user_uid', $userUid)->where('status', 'pending')->count();
        $pendingWithdrawals = DB::connection('mongodb')->table('wallet_withdrawal_requests')
            ->where('user_uid', $userUid)->where('status', 'pending')->count();

        // KYC & Verification status
        $kycStatusRaw = strtoupper((string) ($dbUser['kyc_status'] ?? ($dbUser['kyc'] ?? '')));
        $isKycVerified = in_array($kycStatusRaw, ['VERIFIED', 'APPROVED', 'YES']);
        $isMobileVerified = !empty($dbUser['phone']) || !empty($dbUser['phone_number']) || !empty($dbUser['is_phone_verified']) || true;
        $isEmailVerified = !empty($dbUser['email']);

        // Check active pending request
        $pendingRequest = DB::connection('mongodb')->table('currency_change_requests')
            ->where('user_uid', $userUid)
            ->where('status', 'pending')
            ->first();

        // Get Exchange Rate from platform settings
        $settingDoc = DB::connection('mongodb')->table('admin_settings')->where('key', 'platform')->first();
        $settingArr = (array) ($settingDoc ?? []);
        $baseRate = (float) ($settingArr['usdt_inr_rate'] ?? 95.00);
        $buyRate = (float) ($settingArr['usdt_inr_buy_rate'] ?? $baseRate);
        $sellRate = (float) ($settingArr['usdt_inr_sell_rate'] ?? $baseRate);

        $currentCurrency = strtoupper($dbUser['base_currency'] ?? 'INR');
        $requestedCurrency = $currentCurrency === 'INR' ? 'USDT' : 'INR';

        // Select rate based on conversion direction:
        // INR -> USDT: User is buying USDT (use Buy Rate)
        // USDT -> INR: User is selling USDT (use Sell Rate)
        if ($currentCurrency === 'INR') {
            $effectiveRate = $buyRate;
            $rateTypeLabel = "Buy Rate";
            $currentInrVal = $mainBalanceUsdt * $baseRate;
            $currentBalanceFormatted = "₹" . number_format($currentInrVal, 2);
            $receiveUsdtVal = $buyRate > 0 ? ($currentInrVal / $buyRate) : 0;
            $receiveBalanceFormatted = "$" . number_format($receiveUsdtVal, 2) . " USDT";
        } else {
            $effectiveRate = $sellRate;
            $rateTypeLabel = "Sell Rate";
            $currentBalanceFormatted = "$" . number_format($mainBalanceUsdt, 2) . " USDT";
            $receiveInrVal = $mainBalanceUsdt * $sellRate;
            $receiveBalanceFormatted = "₹" . number_format($receiveInrVal, 2);
        }

        // Validation items array
        $checks = [
            [
                'key' => 'trading_balance',
                'label' => 'Trading Balance = 0',
                'passed' => $tradingBalance == 0,
                'message' => $tradingBalance > 0 ? "Trading balance is $" . number_format($tradingBalance, 2) . " USDT. Transfer back to Main Wallet first." : "Passed ($0 USDT)"
            ],
            [
                'key' => 'open_positions',
                'label' => 'Open Positions = 0',
                'passed' => $openPositionsCount == 0,
                'message' => $openPositionsCount > 0 ? "You have $openPositionsCount open position(s). Close all positions first." : "Passed (0 positions)"
            ],
            [
                'key' => 'open_orders',
                'label' => 'Open Orders = 0',
                'passed' => $openOrdersCount == 0,
                'message' => $openOrdersCount > 0 ? "You have $openOrdersCount open order(s). Cancel all open orders first." : "Passed (0 orders)"
            ],
            [
                'key' => 'pending_deposit',
                'label' => 'Pending Deposit = 0',
                'passed' => $pendingDeposits == 0,
                'message' => $pendingDeposits > 0 ? "You have $pendingDeposits pending deposit request(s)." : "Passed"
            ],
            [
                'key' => 'pending_withdraw',
                'label' => 'Pending Withdraw = 0',
                'passed' => $pendingWithdrawals == 0,
                'message' => $pendingWithdrawals > 0 ? "You have $pendingWithdrawals pending withdrawal request(s)." : "Passed"
            ],
            [
                'key' => 'kyc_verified',
                'label' => 'KYC Verified = Yes',
                'passed' => $isKycVerified,
                'message' => $isKycVerified ? "Passed" : "KYC is not verified."
            ],
            [
                'key' => 'mobile_verified',
                'label' => 'Mobile Verified = Yes',
                'passed' => $isMobileVerified,
                'message' => $isMobileVerified ? "Passed" : "Mobile number not verified."
            ],
            [
                'key' => 'email_verified',
                'label' => 'Email Verified = Yes',
                'passed' => $isEmailVerified,
                'message' => $isEmailVerified ? "Passed" : "Email not verified."
            ]
        ];

        $allPassed = collect($checks)->every(fn($item) => $item['passed'] === true);

        return response()->json([
            'success' => true,
            'data' => [
                'current_currency' => $currentCurrency,
                'requested_currency' => $requestedCurrency,
                'main_balance_usdt' => $mainBalanceUsdt,
                'rate' => $effectiveRate,
                'rate_type' => $rateTypeLabel,
                'current_balance_formatted' => $currentBalanceFormatted,
                'receive_balance_formatted' => $receiveBalanceFormatted,
                'checks' => $checks,
                'can_request' => $allPassed && empty($pendingRequest),
                'pending_request' => $pendingRequest ? (array) $pendingRequest : null,
            ]
        ]);
    }

    /**
     * Submit a currency change request.
     */
    public function requestCurrencyChange(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        if (!is_array($user) || empty($user['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $userUid = (string) $user['uid'];

        // Check active pending request
        $existing = DB::connection('mongodb')->table('currency_change_requests')
            ->where('user_uid', $userUid)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending currency change request waiting for admin review.',
            ], 422);
        }

        // Fetch validation status
        $statusResponse = $this->getCurrencyChangeStatus($request);
        $statusData = $statusResponse->getData(true);

        if (empty($statusData['success']) || empty($statusData['data']['can_request'])) {
            $failedChecks = array_filter($statusData['data']['checks'] ?? [], fn($c) => !$c['passed']);
            $firstMsg = reset($failedChecks)['message'] ?? 'Automated validation checks failed.';
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $firstMsg,
            ], 422);
        }

        $data = $statusData['data'];
        $dbUser = DB::connection('mongodb')->table('users')->where('uid', $userUid)->first();
        $dbUser = (array) $dbUser;

        DB::connection('mongodb')->table('currency_change_requests')->insert([
            'user_uid' => $userUid,
            'user_name' => $dbUser['name'] ?? 'User',
            'user_email' => $dbUser['email'] ?? '',
            'current_currency' => $data['current_currency'],
            'requested_currency' => $data['requested_currency'],
            'main_balance_usdt' => (float) $data['main_balance_usdt'],
            'exchange_rate' => (float) $data['rate'],
            'current_balance_formatted' => $data['current_balance_formatted'],
            'receive_balance_formatted' => $data['receive_balance_formatted'],
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Currency change request submitted successfully! Waiting for Admin approval.',
        ]);
    }

    public function getBankDetails(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if (!is_array($authUser) || empty($authUser['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login.',
            ], 401);
        }

        $bank = DB::connection('mongodb')
            ->table('user_bank_details')
            ->where('user_uid', (string) $authUser['uid'])
            ->first();

        if ($bank) {
            $bank = (array) $bank;
            unset($bank['_id']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Bank details fetched successfully.',
            'data' => [
                'bank_details' => $bank ?: null,
            ]
        ]);
    }

    public function saveBankDetails(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if (!is_array($authUser) || empty($authUser['uid'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please login.',
            ], 401);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'account_name'   => ['required', 'string', 'max:100'],
            'bank_name'      => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'min:6', 'max:30'],
            'ifsc_code'      => ['required', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/i'],
            'account_type'   => ['nullable', 'string', 'in:Savings,Current'],
            'upi_id'         => ['nullable', 'string', 'max:80'],
        ], [
            'account_name.required'   => 'Account holder name is required.',
            'bank_name.required'      => 'Bank name is required.',
            'account_number.required' => 'Account number is required.',
            'ifsc_code.required'      => 'IFSC code is required.',
            'ifsc_code.regex'         => 'Enter a valid 11-character IFSC code (e.g. SBIN0001234).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();
        $userUid = (string) $authUser['uid'];

        $data = [
            'user_uid'       => $userUid,
            'account_name'   => trim($validated['account_name']),
            'bank_name'      => trim($validated['bank_name']),
            'account_number' => trim($validated['account_number']),
            'ifsc_code'      => strtoupper(trim($validated['ifsc_code'])),
            'account_type'   => $validated['account_type'] ?? 'Savings',
            'upi_id'         => !empty($validated['upi_id']) ? strtolower(trim($validated['upi_id'])) : null,
            'updated_at'     => now(),
        ];

        $existing = DB::connection('mongodb')
            ->table('user_bank_details')
            ->where('user_uid', $userUid)
            ->first();

        if ($existing) {
            DB::connection('mongodb')
                ->table('user_bank_details')
                ->where('user_uid', $userUid)
                ->update($data);
        } else {
            $data['bank_uid']   = (string) Str::uuid();
            $data['created_at'] = now();
            DB::connection('mongodb')
                ->table('user_bank_details')
                ->insert($data);
        }

        DB::connection('mongodb')
            ->table('users')
            ->where('uid', $userUid)
            ->update([
                'bank_details_updated_at' => now(),
                'updated_at'              => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Bank details saved successfully.',
            'data' => [
                'bank_details' => $data,
            ]
        ]);
    }

    /**
     * Check if user is eligible to delete account (0 balance across all wallets/accounts, no open trades, no pending orders, no pending withdrawals).
     */
    private function checkUserDeletionPrerequisites(string $userUid): array
    {
        // 1. Calculate Real Balances across ALL wallets
        $totalRealUsd = 0.0;
        $totalRealInr = 0.0;

        $wallets = DB::connection('mongodb')->table('wallets')->where('user_uid', $userUid)->get();
        foreach ($wallets as $w) {
            $wArr = (array) $w;
            $totalRealUsd += (float) ($wArr['real_available_balance'] ?? 0.0)
                           + (float) ($wArr['real_locked_balance'] ?? 0.0)
                           + (float) ($wArr['real_withdrawal_locked_balance'] ?? 0.0)
                           + (float) ($wArr['real_main_balance'] ?? 0.0);
            $totalRealInr += (float) ($wArr['real_available_balance_inr'] ?? 0.0)
                           + (float) ($wArr['real_locked_balance_inr'] ?? 0.0)
                           + (float) ($wArr['real_main_balance_inr'] ?? 0.0);
        }

        // Calculate Real Balances across ALL trading sub-accounts
        $tradingAccounts = DB::connection('mongodb')->table('trading_accounts')->where('user_uid', $userUid)->get();
        foreach ($tradingAccounts as $ta) {
            $taArr = (array) $ta;
            $totalRealUsd += (float) ($taArr['real_available_balance'] ?? 0.0)
                           + (float) ($taArr['real_locked_balance'] ?? 0.0)
                           + (float) ($taArr['real_main_balance'] ?? 0.0);
            $totalRealInr += (float) ($taArr['real_available_balance_inr'] ?? 0.0)
                           + (float) ($taArr['real_locked_balance_inr'] ?? 0.0);
        }

        $balancePassed = ($totalRealUsd <= 0.009 && $totalRealInr <= 0.99);

        // 2. Open Positions (Futures & Options)
        $openFutures = DB::connection('mongodb')->table('futures_positions')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['open', 'OPEN'])
            ->count();
        $openPositions = DB::connection('mongodb')->table('positions')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['open', 'OPEN'])
            ->count();
        $openOptions = DB::connection('mongodb')->table('option_positions')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['open', 'OPEN'])
            ->count();
        $openOptionsAlt = DB::connection('mongodb')->table('options_positions')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['open', 'OPEN'])
            ->count();
        $totalPositions = $openFutures + $openPositions + $openOptions + $openOptionsAlt;
        $positionsPassed = ($totalPositions === 0);

        // 3. Active / Pending Orders (Futures & Options)
        $openFuturesOrders = DB::connection('mongodb')->table('futures_orders')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['new', 'NEW', 'open', 'OPEN', 'pending', 'PENDING', 'partially_filled', 'PARTIALLY_FILLED'])
            ->count();
        $openOrders = DB::connection('mongodb')->table('orders')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['new', 'NEW', 'open', 'OPEN', 'pending', 'PENDING', 'partially_filled', 'PARTIALLY_FILLED'])
            ->count();
        $openOptionOrders = DB::connection('mongodb')->table('option_orders')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['new', 'NEW', 'open', 'OPEN', 'pending', 'PENDING', 'partially_filled', 'PARTIALLY_FILLED'])
            ->count();
        $openOptionOrdersAlt = DB::connection('mongodb')->table('options_orders')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['new', 'NEW', 'open', 'OPEN', 'pending', 'PENDING', 'partially_filled', 'PARTIALLY_FILLED'])
            ->count();
        $totalOrders = $openFuturesOrders + $openOrders + $openOptionOrders + $openOptionOrdersAlt;
        $ordersPassed = ($totalOrders === 0);

        // 4. Pending Withdrawals
        $pendingWithdrawals = DB::connection('mongodb')->table('wallet_withdrawal_requests')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['pending', 'processing', 'PENDING', 'PROCESSING'])
            ->count();
        $pendingWithdrawalsLegacy = DB::connection('mongodb')->table('withdrawals')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['pending', 'processing', 'PENDING', 'PROCESSING'])
            ->count();
        $totalWithdrawals = $pendingWithdrawals + $pendingWithdrawalsLegacy;
        $withdrawalsPassed = ($totalWithdrawals === 0);

        // 5. Pending Deposits
        $pendingDeposits = DB::connection('mongodb')->table('inr_deposit_requests')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['pending', 'processing', 'PENDING', 'PROCESSING'])
            ->count();
        $pendingCryptoDeposits = DB::connection('mongodb')->table('crypto_deposits')
            ->where('user_uid', $userUid)
            ->whereIn('status', ['pending', 'processing', 'PENDING', 'PROCESSING'])
            ->count();
        $totalDeposits = $pendingDeposits + $pendingCryptoDeposits;
        $depositsPassed = ($totalDeposits === 0);

        $eligible = ($balancePassed && $positionsPassed && $ordersPassed && $withdrawalsPassed && $depositsPassed);

        $reasons = [];
        if (!$balancePassed) {
            $formattedUsd = number_format($totalRealUsd, 2);
            $formattedInr = number_format($totalRealInr, 2);
            $reasons[] = "Your account has active balances (\${$formattedUsd} / ₹{$formattedInr}). All accounts must have a balance of 0.00 before deletion. Please withdraw all funds first.";
        }
        if (!$positionsPassed) {
            $reasons[] = "You currently have {$totalPositions} open trading position(s). Please close all open positions first.";
        }
        if (!$ordersPassed) {
            $reasons[] = "You have {$totalOrders} active or pending order(s). Please cancel all active orders first.";
        }
        if (!$withdrawalsPassed) {
            $reasons[] = "You have {$totalWithdrawals} pending withdrawal request(s) in progress. Please wait for them to finish before requesting account deletion.";
        }
        if (!$depositsPassed) {
            $reasons[] = "You have {$totalDeposits} pending deposit request(s) in progress. Please wait for them to finish before requesting account deletion.";
        }

        return [
            'eligible' => $eligible,
            'reasons'  => $reasons,
            'checks'   => [
                'balance' => [
                    'passed'    => $balancePassed,
                    'total_usd' => round($totalRealUsd, 2),
                    'total_inr' => round($totalRealInr, 2),
                    'label'     => $balancePassed ? 'Account balance is $0.00' : "Account balance is \${$totalRealUsd} (Must be $0.00)",
                ],
                'positions' => [
                    'passed' => $positionsPassed,
                    'count'  => $totalPositions,
                    'label'  => $positionsPassed ? 'No open trading positions' : "{$totalPositions} open position(s) must be closed",
                ],
                'orders' => [
                    'passed' => $ordersPassed,
                    'count'  => $totalOrders,
                    'label'  => $ordersPassed ? 'No pending orders' : "{$totalOrders} open order(s) must be cancelled",
                ],
                'withdrawals' => [
                    'passed' => $withdrawalsPassed,
                    'count'  => $totalWithdrawals,
                    'label'  => $withdrawalsPassed ? 'No pending withdrawals' : "{$totalWithdrawals} pending withdrawal(s) in progress",
                ],
                'deposits' => [
                    'passed' => $depositsPassed,
                    'count'  => $totalDeposits,
                    'label'  => $depositsPassed ? 'No pending deposits' : "{$totalDeposits} pending deposit(s) in progress",
                ],
            ],
        ];
    }

    /**
     * Get real-time deletion eligibility check for current user.
     */
    public function getDeletionEligibility(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser || empty($authUser['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $userUid = $authUser['uid'];
        $prereqs = $this->checkUserDeletionPrerequisites($userUid);

        return response()->json([
            'success'  => true,
            'eligible' => $prereqs['eligible'],
            'reasons'  => $prereqs['reasons'],
            'checks'   => $prereqs['checks'],
        ]);
    }

    /**
     * Request account deletion by the authenticated user.
     */
    public function requestAccountDeletion(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser || empty($authUser['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $userUid = $authUser['uid'];
        $user = DB::connection('mongodb')->table('users')->where('uid', $userUid)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }
        $userArr = (array) $user;

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'reason'   => ['nullable', 'string', 'max:500'],
        ]);

        if (!Hash::check($validated['password'], $userArr['password'] ?? '')) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect password. Please verify your current password to proceed.',
            ], 422);
        }

        if (($userArr['deletion_status'] ?? '') === 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'You already have an account deletion request pending admin approval.',
            ], 422);
        }

        // Strict Prerequisite Checks: 0 balance, no open trades, no pending orders, no pending withdrawals
        $prereqs = $this->checkUserDeletionPrerequisites($userUid);
        if (!$prereqs['eligible']) {
            $firstReason = $prereqs['reasons'][0] ?? 'Your account does not meet the requirements for closure.';
            return response()->json([
                'success'  => false,
                'message'  => $firstReason,
                'reasons'  => $prereqs['reasons'],
                'checks'   => $prereqs['checks'],
            ], 422);
        }

        $reason = trim((string) ($validated['reason'] ?? 'User requested account closure.'));

        DB::connection('mongodb')->table('users')->where('uid', $userUid)->update([
            'deletion_status'           => 'pending',
            'deletion_reason'           => $reason,
            'deletion_requested_at'     => now(),
            'deletion_balance_snapshot' => 0.0,
            'updated_at'                => now(),
        ]);

        // Audit Log
        DB::connection('mongodb')->table('audit_logs')->insert([
            'user_uid'   => $userUid,
            'action'     => 'account_deletion_requested',
            'details'    => "User requested account closure. Reason: {$reason}. Verified: Balances 0.00, 0 open positions, 0 pending orders, 0 pending withdrawals.",
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your account deletion request has been submitted to the administration for review.',
        ]);
    }

    /**
     * Get account deletion request status for the authenticated user.
     */
    public function getAccountDeletionStatus(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser || empty($authUser['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $userUid = $authUser['uid'];
        $user = DB::connection('mongodb')->table('users')->where('uid', $userUid)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }
        $userArr = (array) $user;

        return response()->json([
            'success' => true,
            'data' => [
                'deletion_status'       => $userArr['deletion_status'] ?? 'none',
                'deletion_reason'       => $userArr['deletion_reason'] ?? null,
                'deletion_requested_at' => $userArr['deletion_requested_at'] ?? null,
                'status'                => $userArr['status'] ?? 'active',
            ],
        ]);
    }

    /**
     * Cancel a pending account deletion request.
     */
    public function cancelAccountDeletion(Request $request)
    {
        $authUser = $request->attributes->get('auth_user');
        if (!$authUser || empty($authUser['uid'])) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $userUid = $authUser['uid'];
        $user = DB::connection('mongodb')->table('users')->where('uid', $userUid)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'User not found.'], 404);
        }
        $userArr = (array) $user;

        if (($userArr['deletion_status'] ?? '') !== 'pending') {
            return response()->json(['success' => false, 'message' => 'No pending deletion request found.'], 422);
        }

        DB::connection('mongodb')->table('users')->where('uid', $userUid)->update([
            'deletion_status'       => 'none',
            'deletion_reason'       => null,
            'deletion_cancelled_at' => now(),
            'updated_at'            => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account deletion request has been cancelled successfully.',
        ]);
    }
}