@extends('layouts.app')

@section('title', 'Register - Kryvex')

@push('styles')
    @vite('resources/css/pages/auth.css')
@endpush

@push('scripts')
    @vite('resources/js/pages/auth.js')
@endpush

@section('content')
<div class="auth-split-wrapper min-h-screen relative flex items-center justify-center p-4 lg:p-8 overflow-hidden">
    
    <!-- Ambient Glowing Background Elements -->
    <div class="absolute top-1/4 -left-32 w-96 h-96 bg-purple-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-10 right-10 w-96 h-96 bg-indigo-600/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-10 right-1/3 w-80 h-80 bg-purple-900/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Main Split Screen Container -->
    <div class="w-full max-w-5xl grid grid-cols-1 lg:grid-cols-12 bg-[#0c1017]/95 backdrop-blur-2xl border border-slate-800/90 rounded-3xl shadow-2xl shadow-black/80 overflow-hidden z-10 my-auto">
        
        <!-- LEFT SECTION: Branding & Feature Highlights -->
        <div class="lg:col-span-6 hidden lg:flex flex-col justify-between p-10 xl:p-12 bg-gradient-to-br from-[#0e131f] via-[#0b0e14] to-black border-r border-slate-800/80 relative overflow-hidden">
            <!-- Subtle Grid Overlay -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#1e293b_1px,transparent_1px),linear-gradient(to_bottom,#1e293b_1px,transparent_1px)] bg-[size:2.5rem_2.5rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-20 pointer-events-none"></div>

            <!-- Top Header & Brand -->
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-purple-600 via-purple-500 to-indigo-400 text-white font-black flex items-center justify-center text-xl shadow-lg shadow-purple-500/25">
                        K
                    </div>
                    <div>
                        <span class="text-xl font-extrabold tracking-tight text-white block">KRYVEX</span>
                        <span class="text-[10px] font-bold tracking-widest text-purple-400 uppercase">Crypto Futures &amp; Options</span>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-purple-500/10 border border-purple-500/25 text-purple-300 text-xs font-semibold mb-6">
                    <span class="w-2 h-2 rounded-full bg-purple-400 animate-pulse"></span>
                    Get Started in Minutes
                </div>

                <h1 class="text-3xl font-extrabold tracking-tight text-white leading-tight mb-4">
                    Join Kryvex &amp; Unlock <br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-purple-400 via-indigo-300 to-purple-400">
                        Pro Trading Power
                    </span>
                </h1>
                
                <p class="text-slate-400 text-sm leading-relaxed max-w-md">
                    Create your free account today and start trading crypto futures with 100x leverage, live Binance feeds, and deep liquidity.
                </p>
            </div>

            <!-- Middle Feature Cards -->
            <div class="grid grid-cols-2 gap-3.5 my-8 relative z-10">
                <div class="p-4 rounded-2xl bg-[#080b11]/80 border border-slate-800/80 backdrop-blur-md">
                    <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center mb-2.5 font-bold text-base">
                        🚀
                    </div>
                    <h3 class="text-xs font-bold text-white mb-1">Instant Activation</h3>
                    <p class="text-[11px] text-slate-400 leading-relaxed">Quick email OTP onboarding and instant demo trading wallet.</p>
                </div>

                <div class="p-4 rounded-2xl bg-[#080b11]/80 border border-slate-800/80 backdrop-blur-md">
                    <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-400 flex items-center justify-center mb-2.5 font-bold text-base">
                        💼
                    </div>
                    <h3 class="text-xs font-bold text-white mb-1">Dual Currency</h3>
                    <p class="text-[11px] text-slate-400 leading-relaxed">Seamless INR &amp; USDT fund management and live tracking.</p>
                </div>
            </div>

            <!-- Bottom Live Stats Bar -->
            <div class="pt-5 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400 relative z-10">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Platform Status: <strong class="text-slate-200">100% Operational</strong></span>
                </div>
                <span class="text-slate-500">Free Account</span>
            </div>
        </div>

        <!-- RIGHT SECTION: Interactive Auth Forms -->
        <div class="lg:col-span-6 p-6 sm:p-10 xl:p-12 flex flex-col justify-center bg-[#0c1017]/80 relative">
            
            <!-- Mobile Brand Header -->
            <div class="lg:hidden flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-400 text-white font-black flex items-center justify-center text-xl shadow-md shadow-purple-500/20">
                    K
                </div>
                <div>
                    <span class="text-lg font-extrabold text-white block">KRYVEX</span>
                    <span class="text-[10px] font-semibold text-purple-400 uppercase">Crypto Futures &amp; Options</span>
                </div>
            </div>

            <div class="auth-head mb-6">
                <p class="text-xs font-bold text-purple-400 tracking-wider uppercase mb-1">Create account</p>
                <h2 class="text-2xl sm:text-3xl font-black text-white">Start with Kryvex</h2>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">Register with email verification or continue with Google.</p>
            </div>

            <div id="authAlert" class="auth-alert"></div>

            <a href="{{ route('auth.google.redirect') }}" class="google-auth-btn">
                <svg width="18" height="18" viewBox="0 0 24 24" class="google-icon" style="flex-shrink:0;"><path fill="#EA4335" d="M12 5c1.54 0 2.93.53 4.02 1.57l3.01-3.01C17.21 1.8 14.8 1 12 1 7.51 1 3.66 3.56 1.76 7.28l3.66 2.84C6.3 7.4 8.91 5 12 5z"/><path fill="#4285F4" d="M23.49 12.28c0-.82-.07-1.6-.2-2.28H12v4.56h6.48c-.28 1.48-1.11 2.74-2.37 3.58l3.68 2.85c2.15-1.98 3.7-4.9 3.7-8.71z"/><path fill="#FBBC05" d="M5.42 14.88c-.23-.68-.36-1.41-.36-2.16s.13-1.48.36-2.16L1.76 7.72C.64 9.96 0 12.44 0 15.08s.64 5.12 1.76 7.36l3.66-2.84z"/><path fill="#34A853" d="M12 23c3.24 0 5.95-1.07 7.94-2.91l-3.68-2.85c-1.08.72-2.45 1.16-4.26 1.16-3.09 0-5.7-2.4-6.58-5.12L1.76 16.12C3.66 19.84 7.51 23 12 23z"/></svg>
                <span>Sign up with Google</span>
            </a>

            <div class="auth-divider">
                <span>or create account with email</span>
            </div>

            <!-- Registration form sends OTP with 120 second validity. -->
            <form id="registerForm" class="auth-form">
                <label>
                    Email
                    <input type="email" name="email" placeholder="Enter email address" required>
                </label>

                <label>
                    Mobile Number
                    <input type="text" name="phone" placeholder="10 digit mobile number" required maxlength="10" inputmode="numeric">
                </label>

                <label>
                    Password
                    <div class="password-field">
                        <input type="password" name="password" placeholder="Password@123" required>
                        <button type="button" class="password-toggle text-slate-400 hover:text-purple-400 transition-colors cursor-pointer bg-transparent border-0 outline-none p-0" data-password-toggle data-show="false" aria-label="Toggle password visibility">
                            <svg class="icon-eye w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg class="icon-eye-off hidden w-5 h-5 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                <line x1="2" x2="22" y1="2" y2="22" />
                            </svg>
                        </button>
                    </div>
                </label>

                <label>
                    Confirm Password
                    <div class="password-field">
                        <input type="password" name="password_confirmation" placeholder="Confirm password" required>
                        <button type="button" class="password-toggle text-slate-400 hover:text-purple-400 transition-colors cursor-pointer bg-transparent border-0 outline-none p-0" data-password-toggle data-show="false" aria-label="Toggle password visibility">
                            <svg class="icon-eye w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <svg class="icon-eye-off hidden w-5 h-5 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                <line x1="2" x2="22" y1="2" y2="22" />
                            </svg>
                        </button>
                    </div>
                </label>

                <label>
                    Referral Code <span class="text-xs text-slate-500 font-normal">(Optional)</span>
                    <input type="text" name="referral_code" id="registerReferralCode" placeholder="Enter referral code (e.g. 641921)">
                </label>

                <label class="auth-checkbox">
                    <input type="checkbox" name="terms" value="1" required>
                    <span>I accept Terms and Risk Disclosure.</span>
                </label>

                <button type="submit" class="kv-btn kv-btn-primary auth-submit">
                    Send Verification Code
                </button>
            </form>

            <!-- Registration OTP verification. User can resend 2 times instantly, then must wait 10 minutes. -->
            <form id="verifyRegisterForm" class="auth-form otp-form" style="display: none;">
                <input type="hidden" name="email" id="verifyEmailInput">

                <div class="auth-otp-meta">
                    <span>Code expires in <strong id="registerOtpTimer">120s</strong></span>
                    <button type="button" id="resendRegisterCodeButton">Resend code</button>
                </div>

                <p class="auth-tiny-note" id="registerResendHelp">
                    You can resend instantly 2 times. After that, resend will be locked for 10 minutes.
                </p>

                <label>
                    Email Verification Code
                    <input type="text" name="code" placeholder="Enter 6 digit code" required maxlength="6" inputmode="numeric">
                </label>

                <button type="submit" class="kv-btn kv-btn-primary auth-submit">
                    Verify &amp; Create Account
                </button>

                <button type="button" id="backToRegisterButton" class="kv-btn kv-btn-outline auth-submit">
                    Edit Registration Details
                </button>
            </form>

            <!-- Forgot password flow:
                 Step 1 email → Step 2 OTP verify within 120 seconds → Step 3 set password. -->
            <form id="forgotPasswordForm" class="auth-form auth-secondary-form" style="display: none;" novalidate>
                <div class="auth-form-title">
                    <strong>Reset password</strong>
                    <span id="forgotStepSubtitle">Enter your email to receive a 6 digit reset code.</span>
                </div>

                <div id="forgotEmailSection" class="auth-reset-step is-active" data-reset-step="email">
                    <label>
                        Email
                        <input type="email" name="email" placeholder="Enter registered email" required>
                    </label>

                    <button type="button" id="forgotSendCodeButton" class="kv-btn kv-btn-primary auth-submit">
                        Send Reset Code
                    </button>
                </div>

                <div id="forgotCodeSection" class="auth-reset-step auth-otp-section" style="display: none;" data-reset-step="code">
                    <div class="auth-otp-meta">
                        <span>Code expires in <strong id="forgotOtpTimer">120s</strong></span>
                        <button type="button" id="forgotResendButton">Resend code</button>
                    </div>

                    <label>
                        Reset Code
                        <input type="text" name="code" placeholder="Enter 6 digit code" maxlength="6" inputmode="numeric">
                    </label>

                    <button type="button" id="forgotVerifyCodeButton" class="kv-btn kv-btn-outline auth-submit">
                        Verify Code
                    </button>
                </div>

                <div id="forgotPasswordSection" class="auth-reset-step auth-otp-section" style="display: none;" data-reset-step="password">
                    <input type="hidden" name="reset_token" id="forgotResetToken">

                    <p class="auth-tiny-note">Code verified. Now set your new password.</p>

                    <label>
                        New Password
                        <div class="password-field">
                            <input type="password" name="password" placeholder="New Password@123">
                            <button type="button" class="password-toggle text-slate-400 hover:text-purple-400 transition-colors cursor-pointer bg-transparent border-0 outline-none p-0" data-password-toggle data-show="false" aria-label="Toggle password visibility">
                                <svg class="icon-eye w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg class="icon-eye-off hidden w-5 h-5 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                    <line x1="2" x2="22" y1="2" y2="22" />
                                </svg>
                            </button>
                        </div>
                    </label>

                    <label>
                        Confirm New Password
                        <div class="password-field">
                            <input type="password" name="password_confirmation" placeholder="Confirm new password">
                            <button type="button" class="password-toggle text-slate-400 hover:text-purple-400 transition-colors cursor-pointer bg-transparent border-0 outline-none p-0" data-password-toggle data-show="false" aria-label="Toggle password visibility">
                                <svg class="icon-eye w-5 h-5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                                <svg class="icon-eye-off hidden w-5 h-5 text-purple-400" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
                                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
                                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
                                    <line x1="2" x2="22" y1="2" y2="22" />
                                </svg>
                            </button>
                        </div>
                    </label>

                    <button type="submit" class="kv-btn kv-btn-primary auth-submit">
                        Reset Password
                    </button>
                </div>

                <button type="button" class="kv-btn kv-btn-outline auth-submit" data-back-to-register>
                    Back to Register
                </button>
            </form>

            <p class="auth-bottom">
                Already have account?
                <a href="{{ url('/login') }}">Login</a>
                <span class="auth-dot-separator">•</span>
                <button type="button" data-open-forgot-password>Forgot Password?</button>
            </p>
        </div>
    </div>
</div>
@endsection
