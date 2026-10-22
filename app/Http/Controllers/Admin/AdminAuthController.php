<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AuditLogger;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminAuthController extends Controller
{
    public function login()
    {
        return view('admin.auth.login', ['initialTab' => 'login']);
    }

    public function register()
    {
        return view('admin.auth.login', ['initialTab' => 'register']);
    }

    public function forgotPassword()
    {
        return view('admin.auth.login', ['initialTab' => 'forgot']);
    }

    public function loginSubmit(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'min:6'],
        ]);

        if (
            $validated['email'] === 'admin@kryvex.com' &&
            $validated['password'] === 'admin123'
        ) {
            session([
                'admin_logged_in' => true,
                'admin_email' => $validated['email'],
            ]);

            AuditLogger::log('login', 'auth', 'Admin logged in successfully.');

            return redirect()->route('admin.dashboard');
        }

        return back()
            ->withErrors(['email' => 'Invalid admin email or password.'])
            ->withInput();
    }

    public function apiLogin(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'min:6'],
        ]);

        $email = strtolower(trim($validated['email']));
        $password = $validated['password'];

        if ($email === 'admin@kryvex.com' && $password === 'admin123') {
            session([
                'admin_logged_in' => true,
                'admin_email' => $email,
                'admin_uid' => 'default-admin-uid',
                'admin_name' => 'Default Admin',
                'admin_role' => 'admin',
            ]);

            AuditLogger::log('login', 'auth', 'Admin logged in via hardcoded credentials.');

            return response()->json([
                'success' => true,
                'message' => 'Logged in successfully.',
                'data' => [
                    'tokens' => [
                        'access_token' => 'default-admin-token',
                        'refresh_token' => 'default-admin-refresh-token',
                    ],
                    'user' => [
                        'name' => 'Default Admin',
                        'email' => $email,
                        'role' => 'admin',
                    ]
                ]
            ]);
        }

        $user = DB::connection('mongodb')
            ->table('users')
            ->where('email', $email)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 422);
        }

        $userArray = (array) $user;

        if (!\Illuminate\Support\Facades\Hash::check($password, $userArray['password'] ?? '')) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password.',
            ], 422);
        }

        $role = strtolower((string)($userArray['role'] ?? ''));
        if ($role !== 'admin' && $role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only administrators are allowed.',
            ], 403);
        }

        if (($userArray['status'] ?? 'active') !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your admin account has been suspended or blocked.',
            ], 403);
        }

        session([
            'admin_logged_in' => true,
            'admin_email' => $userArray['email'],
            'admin_uid' => $userArray['uid'] ?? (string) $userArray['_id'],
            'admin_name' => $userArray['name'] ?? 'Admin',
            'admin_role' => $role,
        ]);

        AuditLogger::log('login', 'auth', 'Admin logged in successfully.');

        return response()->json([
            'success' => true,
            'message' => 'Logged in successfully.',
            'data' => [
                'tokens' => [
                    'access_token' => 'db-admin-token',
                    'refresh_token' => 'db-admin-refresh-token',
                ],
                'user' => [
                    'name' => $userArray['name'] ?? 'Admin',
                    'email' => $userArray['email'],
                    'role' => $role,
                ]
            ]
        ]);
    }

    public function apiRegister(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['required', 'regex:/^[0-9]{10}$/'],
            'password' => [
                'required',
                'confirmed',
                \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'terms' => ['accepted'],
        ], [
            'name.required' => 'Full name is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Enter a valid email address.',
            'phone.required' => 'Mobile number is required.',
            'phone.regex' => 'Mobile number must be exactly 10 digits.',
            'password.confirmed' => 'Password confirmation does not match.',
            'terms.accepted' => 'Please accept terms and admin code of conduct.',
        ]);

        $email = strtolower(trim($validated['email']));

        $existingUser = DB::connection('mongodb')
            ->table('users')
            ->where('email', $email)
            ->first();

        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'This email is already registered. Please login instead.',
            ], 422);
        }

        $code = (string) random_int(100000, 999999);

        DB::connection('mongodb')
            ->table('pending_admin_registrations')
            ->where('email', $email)
            ->delete();

        DB::connection('mongodb')
            ->table('pending_admin_registrations')
            ->insert([
                'pending_uid' => (string) \Illuminate\Support\Str::uuid(),
                'name' => trim($validated['name']),
                'email' => $email,
                'phone' => $validated['phone'],
                'password_hash' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                'verification_code_hash' => \Illuminate\Support\Facades\Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds(120),
                'resend_count' => 0,
                'resend_cooldown_until' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $this->sendVerificationEmail($email, $validated['name'], $code);

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

    public function apiVerifyRegister(Request $request)
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
            ->table('pending_admin_registrations')
            ->where('email', $email)
            ->first();

        if (! $pending) {
            return response()->json([
                'success' => false,
                'message' => 'Verification request not found. Please register again.',
            ], 422);
        }

        $pendingArray = (array) $pending;

        if (now()->greaterThan(\Carbon\Carbon::parse($pendingArray['expires_at']))) {
            DB::connection('mongodb')
                ->table('pending_admin_registrations')
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

        if (! \Illuminate\Support\Facades\Hash::check($validated['code'], $pendingArray['verification_code_hash'])) {
            DB::connection('mongodb')
                ->table('pending_admin_registrations')
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

        $existingUser = DB::connection('mongodb')
            ->table('users')
            ->where('email', $email)
            ->first();

        if ($existingUser) {
            DB::connection('mongodb')
                ->table('pending_admin_registrations')
                ->where('email', $email)
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'This email is already registered. Please login.',
            ], 422);
        }

        $uid = (string) \Illuminate\Support\Str::uuid();
        $user = [
            'uid' => $uid,
            'name' => $pendingArray['name'],
            'email' => $pendingArray['email'],
            'phone' => $pendingArray['phone'],
            'password' => $pendingArray['password_hash'],
            'google_id' => null,
            'avatar' => null,
            'auth_provider' => 'email',
            'role' => 'admin',
            'status' => 'active',
            'email_verified' => true,
            'email_verified_at' => now(),
            'kyc_status' => 'verified',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::connection('mongodb')
            ->table('users')
            ->insert($user);

        DB::connection('mongodb')
            ->table('pending_admin_registrations')
            ->where('email', $email)
            ->delete();

        session([
            'admin_logged_in' => true,
            'admin_email' => $pendingArray['email'],
            'admin_uid' => $uid,
            'admin_name' => $pendingArray['name'],
            'admin_role' => 'admin',
        ]);

        AuditLogger::log('register', 'auth', 'Admin registered and logged in successfully.');

        return response()->json([
            'success' => true,
            'message' => 'Admin account created successfully.',
            'data' => [
                'tokens' => [
                    'access_token' => 'db-admin-token',
                    'refresh_token' => 'db-admin-refresh-token',
                ],
                'user' => [
                    'name' => $pendingArray['name'],
                    'email' => $pendingArray['email'],
                    'role' => 'admin',
                ]
            ]
        ]);
    }

    public function apiResendRegisterCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));

        $pending = DB::connection('mongodb')
            ->table('pending_admin_registrations')
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

        if ($cooldownUntil && now()->lessThan(\Carbon\Carbon::parse($cooldownUntil))) {
            return response()->json([
                'success' => false,
                'message' => 'Resend limit reached. Please wait before requesting a new code.',
                'data' => [
                    'cooldown_seconds' => now()->diffInSeconds(\Carbon\Carbon::parse($cooldownUntil)),
                ],
            ], 429);
        }

        $currentResendCount = (int) ($pendingArray['resend_count'] ?? 0);

        if ($currentResendCount >= 2) {
            $cooldownUntil = now()->addMinutes(10);

            DB::connection('mongodb')
                ->table('pending_admin_registrations')
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
            ->table('pending_admin_registrations')
            ->where('email', $email)
            ->update([
                'verification_code_hash' => \Illuminate\Support\Facades\Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addSeconds(120),
                'resend_count' => $currentResendCount + 1,
                'updated_at' => now(),
            ]);

        $this->sendVerificationEmail($email, $pendingArray['name'] ?? 'Kryvex Admin', $code);

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

    public function apiSendForgotPasswordCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));

        $user = DB::connection('mongodb')
            ->table('users')
            ->where('email', $email)
            ->first();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'No admin account found with this email.',
            ], 422);
        }

        $userArray = (array) $user;
        $role = strtolower((string)($userArray['role'] ?? ''));
        if ($role !== 'admin' && $role !== 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => 'No admin account found with this email.',
            ], 422);
        }

        $code = (string) random_int(100000, 999999);

        DB::connection('mongodb')
            ->table('password_resets')
            ->where('email', $email)
            ->delete();

        DB::connection('mongodb')
            ->table('password_resets')
            ->insert([
                'email' => $email,
                'token' => \Illuminate\Support\Facades\Hash::make($code),
                'created_at' => now(),
                'expires_at' => now()->addSeconds(120),
                'attempts' => 0,
            ]);

        $this->sendPasswordResetEmail($email, $userArray['name'] ?? 'Admin', $code);

        return response()->json([
            'success' => true,
            'message' => 'Password reset code sent to your email. It is valid for 120 seconds.',
            'data' => [
                'email' => $email,
                'expires_in_seconds' => 120,
            ],
        ]);
    }

    public function apiVerifyForgotPasswordCode(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($validated['email']));

        $reset = DB::connection('mongodb')
            ->table('password_resets')
            ->where('email', $email)
            ->first();

        if (! $reset) {
            return response()->json([
                'success' => false,
                'message' => 'Password reset request expired or not found. Please try again.',
            ], 422);
        }

        $resetArray = (array) $reset;

        if (now()->greaterThan(\Carbon\Carbon::parse($resetArray['expires_at']))) {
            DB::connection('mongodb')
                ->table('password_resets')
                ->where('email', $email)
                ->delete();

            return response()->json([
                'success' => false,
                'message' => 'Reset code expired. Please request a new code.',
            ], 422);
        }

        if (! \Illuminate\Support\Facades\Hash::check($validated['code'], $resetArray['token'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset code.',
            ], 422);
        }

        $tempToken = \Illuminate\Support\Str::random(60);

        DB::connection('mongodb')
            ->table('password_resets')
            ->where('email', $email)
            ->update([
                'temp_token' => $tempToken,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Reset code verified successfully. Set your new password.',
            'data' => [
                'reset_token' => $tempToken,
            ],
        ]);
    }

    public function apiResetForgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'reset_token' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                \Illuminate\Validation\Rules\Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
        ]);

        $email = strtolower(trim($validated['email']));

        $reset = DB::connection('mongodb')
            ->table('password_resets')
            ->where('email', $email)
            ->where('temp_token', $validated['reset_token'])
            ->first();

        if (! $reset) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid reset token. Please try again.',
            ], 422);
        }

        DB::connection('mongodb')
            ->table('users')
            ->where('email', $email)
            ->update([
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                'updated_at' => now(),
            ]);

        DB::connection('mongodb')
            ->table('password_resets')
            ->where('email', $email)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully. You can now login.',
        ]);
    }

    private function sendVerificationEmail(string $email, string $name, string $code): void
    {
        $subject = 'Your Kryvex Admin verification code';

        $html = '
            <div style="font-family: Arial, sans-serif; background:#f6f8fb; padding:24px;">
                <div style="max-width:520px; margin:auto; background:#ffffff; border-radius:12px; padding:24px; border:1px solid #e5e7eb;">
                    <h2 style="margin:0 0 12px; color:#111827;">Verify your Kryvex Admin account</h2>
                    <p style="color:#374151;">Hello ' . e($name) . ',</p>
                    <p style="color:#374151;">Use this verification code to complete your admin registration:</p>
                    <div style="font-size:32px; font-weight:800; letter-spacing:6px; color:#111827; background:#f3f4f6; padding:16px; text-align:center; border-radius:10px;">
                        ' . e($code) . '
                    </div>
                    <p style="color:#6b7280; margin-top:18px;">This code will expire in 120 seconds.</p>
                </div>
            </div>
        ';

        \Illuminate\Support\Facades\Mail::html($html, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }

    private function sendPasswordResetEmail(string $email, string $name, string $code): void
    {
        $subject = 'Your Kryvex Admin password reset code';

        $html = '
            <div style="font-family: Arial, sans-serif; background:#f6f8fb; padding:24px;">
                <div style="max-width:520px; margin:auto; background:#ffffff; border-radius:12px; padding:24px; border:1px solid #e5e7eb;">
                    <h2 style="margin:0 0 12px; color:#111827;">Reset your Kryvex Admin password</h2>
                    <p style="color:#374151;">Hello ' . e($name) . ',</p>
                    <p style="color:#374151;">Use this reset code to set a new password:</p>
                    <div style="font-size:32px; font-weight:800; letter-spacing:6px; color:#111827; background:#f3f4f6; padding:16px; text-align:center; border-radius:10px;">
                        ' . e($code) . '
                    </div>
                    <p style="color:#6b7280; margin-top:18px;">This code will expire in 120 seconds.</p>
                </div>
            </div>
        ';

        \Illuminate\Support\Facades\Mail::html($html, function ($message) use ($email, $subject) {
            $message->to($email)->subject($subject);
        });
    }

    public function dashboard()
    {
        $users = $this->safeCollection('users');
        $wallets = $this->safeCollection('wallets');
        $futuresOrders = $this->safeCollection('futures_orders');
        $optionsOrders = $this->safeCollection('options_orders');
        $legacyOptionsOrders = $this->safeCollection('option_orders');
        $futuresPositions = $this->safeCollection('futures_positions');
        $optionsPositions = $this->safeCollection('options_positions');
        $futuresTrades = $this->safeCollection('futures_trades');
        $optionsTrades = $this->safeCollection('options_trades');
        $walletLedgers = $this->safeCollection('wallet_ledgers');
        $withdrawalRequests = $this->safeCollection('wallet_withdrawal_requests');
        $platformRevenueLedgers = $this->safeCollection('platform_revenue_ledgers');

        $userMap = $this->usersMap($users);

        $availableWallet = $wallets->sum(fn ($wallet) => (float) ($wallet->demo_available_balance ?? 0));
        $lockedWallet = $wallets->sum(fn ($wallet) => (float) ($wallet->demo_locked_balance ?? 0));

        $depositLedgers = $walletLedgers->filter(fn ($row) => $this->isDepositLedger($row));
        $withdrawalLedgers = $walletLedgers->filter(fn ($row) => $this->isWithdrawalLedger($row));

        $openPositions = $futuresPositions
            ->merge($optionsPositions)
            ->filter(fn ($position) => strtolower((string) ($position->status ?? '')) === 'open');

        $totalOrders = $futuresOrders->count() + $optionsOrders->count() + $legacyOptionsOrders->count();
        $totalTrades = $futuresTrades->count() + $optionsTrades->count();

        $platformRevenue = $platformRevenueLedgers
            ->filter(fn ($row) => strtolower((string) ($row->status ?? 'completed')) === 'completed')
            ->sum(fn ($row) => (float) ($row->amount ?? 0));

        // Fallback for old data copies where platform_revenue_ledgers does not exist yet.
        if ($platformRevenue <= 0) {
            $platformRevenue = $walletLedgers
                ->filter(fn ($row) => $this->isRevenueLedger($row))
                ->sum(fn ($row) => (float) ($row->amount ?? 0));
        }

        $recentUsers = $users
            ->sortByDesc(fn ($user) => strtotime((string) ($user->created_at ?? $user->updated_at ?? now())))
            ->take(5)
            ->values();

        $recentDeposits = $depositLedgers
            ->sortByDesc(fn ($row) => strtotime((string) ($row->created_at ?? $row->updated_at ?? now())))
            ->take(5)
            ->map(fn ($row) => $this->attachUser($row, $userMap))
            ->values();

        $recentWithdrawals = $withdrawalRequests
            ->sortByDesc(fn ($row) => strtotime((string) ($row->created_at ?? $row->requested_at ?? $row->updated_at ?? now())))
            ->take(5)
            ->map(function ($row) use ($userMap) {
                $row->withdrawal_id = (string) ($row->_id ?? $row->id ?? '');
                return $this->attachUser($row, $userMap);
            })
            ->values();

        $stats = [
            'total_users' => $users->count(),
            'wallet_balance' => round($availableWallet + $lockedWallet, 2),
            'open_positions' => $openPositions->count(),
            'total_orders' => $totalOrders,
            'total_deposits_amount' => round($depositLedgers->sum(fn ($row) => (float) ($row->amount ?? 0)), 2),
            'total_deposits_count' => $depositLedgers->count(),
            'total_withdrawals_amount' => round($withdrawalRequests->sum(fn ($row) => (float) ($row->amount ?? 0)), 2),
            'total_withdrawals_count' => $withdrawalRequests->count(),
            'pending_withdrawals' => $withdrawalRequests->filter(fn ($row) => strtolower((string) ($row->status ?? 'pending')) === 'pending')->count(),
            'total_trades' => $totalTrades,
            'platform_revenue' => round($platformRevenue, 2),
        ];

        return view('admin.dashboard', compact(
            'stats',
            'recentUsers',
            'recentDeposits',
            'recentWithdrawals'
        ));
    }

    public function logout()
    {
        AuditLogger::log('logout', 'auth', 'Admin logged out.');

        session()->forget(['admin_logged_in', 'admin_email', 'admin_id']);

        return redirect()->route('admin.login');
    }

    /**
     * Safely read a MongoDB collection so dashboard does not break if an optional module collection is missing.
     */
    private function safeCollection(string $collection, int $limit = 5000): Collection
    {
        try {
            return DB::connection('mongodb')
                ->table($collection)
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get();
        } catch (\Throwable $exception) {
            return collect();
        }
    }

    /**
     * Build a user lookup map using both uid and Mongo _id because old records may reference either value.
     */
    private function usersMap(Collection $users): Collection
    {
        $map = collect();

        foreach ($users as $user) {
            if (!empty($user->uid)) {
                $map->put((string) $user->uid, $user);
            }

            if (!empty($user->_id)) {
                $map->put((string) $user->_id, $user);
            }
        }

        return $map;
    }

    private function attachUser(object $row, Collection $userMap): object
    {
        $uid = (string) ($row->user_uid ?? $row->uid ?? '');
        $user = $userMap->get($uid);

        $row->display_user_name = $row->user_name ?? $row->display_user_name ?? ($user->name ?? 'Unknown User');
        $row->display_user_email = $row->user_email ?? $row->display_user_email ?? ($user->email ?? '-');

        return $row;
    }

    private function isDepositLedger(object $row): bool
    {
        $type = strtolower((string) ($row->type ?? ''));
        $direction = strtolower((string) ($row->direction ?? ''));

        return str_contains($type, 'deposit')
            || str_contains($type, 'demo_credit')
            || str_contains($type, 'fund')
            || ($direction === 'credit' && str_contains($type, 'wallet'));
    }

    private function isWithdrawalLedger(object $row): bool
    {
        $type = strtolower((string) ($row->type ?? ''));
        $category = strtolower((string) ($row->category ?? ''));

        return str_contains($type, 'withdraw')
            || str_contains($category, 'withdraw');
    }

    private function isRevenueLedger(object $row): bool
    {
        $type = strtolower((string) ($row->type ?? ''));
        $category = strtolower((string) ($row->category ?? ''));
        $status = strtolower((string) ($row->status ?? 'completed'));

        if ($status !== 'completed') {
            return false;
        }

        return str_contains($type, 'trading_fee')
            || str_contains($type, 'withdrawal_fee')
            || str_contains($type, 'platform_charge')
            || str_contains($category, 'revenue')
            || str_contains($category, 'fee');
    }
}
