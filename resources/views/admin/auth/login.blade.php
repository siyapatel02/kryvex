@extends('admin.layouts.auth')

@section('title', 'Admin Portal - Kryvex')

@push('styles')
    @vite(['resources/css/admin/login.css', 'resources/css/pages/auth.css'])
@endpush

@push('scripts')
    @vite('resources/js/admin/auth.js')
@endpush

@section('content')
@php
    $activeTab = $initialTab ?? (request()->is('admin/forgot-password') ? 'forgot' : (request()->is('admin/register') || request()->query('tab') === 'register' ? 'register' : 'login'));
@endphp

<div class="min-h-screen bg-zinc-950 text-white selection:bg-amber-400 selection:text-black relative flex items-center justify-center p-4 lg:p-8 overflow-hidden">
    
    <!-- Ambient Glowing Background Elements -->
    <div class="absolute top-1/4 -left-32 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-10 right-10 w-96 h-96 bg-yellow-500/15 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute top-10 right-1/3 w-80 h-80 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <!-- Main Split Screen Container -->
    <div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-12 bg-zinc-900/90 backdrop-blur-2xl border border-zinc-800/80 rounded-3xl shadow-2xl shadow-black/90 overflow-hidden z-10 my-auto">
        
        <!-- LEFT SECTION: Branding & Feature Highlights -->
        <div class="lg:col-span-6 xl:col-span-7 hidden lg:flex flex-col justify-between p-10 xl:p-14 bg-gradient-to-br from-zinc-900/95 via-zinc-900/50 to-black/90 border-r border-zinc-800/60 relative overflow-hidden">
            <!-- Subtle Grid Overlay -->
            <div class="absolute inset-0 bg-[linear-gradient(to_right,#18181b_1px,transparent_1px),linear-gradient(to_bottom,#18181b_1px,transparent_1px)] bg-[size:3rem_3rem] [mask-image:radial-gradient(ellipse_60%_50%_at_50%_0%,#000_70%,transparent_100%)] opacity-20 pointer-events-none"></div>

            <!-- Top Header & Brand -->
            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-8">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-amber-500 via-yellow-400 to-amber-300 text-zinc-950 font-black flex items-center justify-center text-2xl shadow-lg shadow-amber-500/20">
                        K
                    </div>
                    <div>
                        <span class="text-xl font-extrabold tracking-tight text-white block">KRYVEX</span>
                        <span class="text-xs font-semibold tracking-widest text-amber-400 uppercase">Admin Control Center</span>
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs font-medium mb-6">
                    <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                    Secure System Node 2.4 Active
                </div>

                <h1 class="text-3xl xl:text-4xl font-extrabold tracking-tight text-white leading-tight mb-4">
                    Empowering Next-Gen <br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-yellow-300 to-amber-500">
                        Financial Operations
                    </span>
                </h1>
                
                <p class="text-zinc-400 text-sm xl:text-base leading-relaxed max-w-lg">
                    Access the unified administration console for liquidity management, multi-currency wallets, user compliance audits, and real-time trade execution controls.
                </p>
            </div>

            <!-- Middle Feature Cards -->
            <div class="grid grid-cols-2 gap-4 my-8 relative z-10">
                <div class="p-4 rounded-2xl bg-zinc-950/60 border border-zinc-800/80 backdrop-blur-md">
                    <div class="w-8 h-8 rounded-xl bg-amber-400/10 text-amber-400 flex items-center justify-center mb-3 font-semibold text-lg">
                        ⚡
                    </div>
                    <h3 class="text-sm font-bold text-white mb-1">Real-time Auditing</h3>
                    <p class="text-xs text-zinc-400 leading-relaxed">Instant ledger checks and automated fraud detection triggers.</p>
                </div>

                <div class="p-4 rounded-2xl bg-zinc-950/60 border border-zinc-800/80 backdrop-blur-md">
                    <div class="w-8 h-8 rounded-xl bg-amber-400/10 text-amber-400 flex items-center justify-center mb-3 font-semibold text-lg">
                        🛡️
                    </div>
                    <h3 class="text-sm font-bold text-white mb-1">Bank-Grade Shield</h3>
                    <p class="text-xs text-zinc-400 leading-relaxed">Encrypted admin sessions with multi-factor authentication locks.</p>
                </div>
            </div>

            <!-- Bottom Live Stats Bar -->
            <div class="pt-6 border-t border-zinc-800/80 flex items-center justify-between text-xs text-zinc-400 relative z-10">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span>System Status: <strong class="text-zinc-200">100% Operational</strong></span>
                </div>
            </div>
        </div>

        <!-- RIGHT SECTION: Admin Auth Forms & Tab Switcher -->
        <div class="lg:col-span-6 xl:col-span-5 p-6 sm:p-10 xl:p-12 flex flex-col justify-center bg-zinc-900/70 relative">
            
            <!-- Mobile Brand Header -->
            <div class="lg:hidden flex items-center gap-3 mb-6">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-300 text-zinc-950 font-black flex items-center justify-center text-xl shadow-md shadow-amber-500/20">
                    K
                </div>
                <div>
                    <span class="text-lg font-extrabold text-white block">KRYVEX</span>
                    <span class="text-xs font-semibold text-amber-400 uppercase">Admin Portal</span>
                </div>
            </div>

            <!-- Tab Switcher Navigation Links (Login vs Register) -->
            <div id="authTabHeader" class="flex items-center p-1 bg-zinc-950/80 border border-zinc-800/80 rounded-2xl mb-6" style="{{ $activeTab === 'forgot' ? 'display: none !important;' : '' }}">
                <a href="{{ route('admin.login') }}" id="tabLoginBtn" data-auth-tab="login" 
                   class="flex-1 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all text-center {{ $activeTab === 'login' ? 'bg-amber-400 text-zinc-950 shadow-md shadow-amber-400/20' : 'text-zinc-400 hover:text-white' }}">
                    Login
                </a>
                <a href="{{ route('admin.register') }}" id="tabRegisterBtn" data-auth-tab="register" 
                   class="flex-1 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all text-center {{ $activeTab === 'register' ? 'bg-amber-400 text-zinc-950 shadow-md shadow-amber-400/20' : 'text-zinc-400 hover:text-white' }}">
                    Create Account
                </a>
            </div>

            <!-- Dynamic Toast / Alert Container -->
            <div id="authAlert" class="auth-alert hidden mb-4"></div>

            <!-- 1. LOGIN FORM -->
            <form id="loginForm" class="auth-form flex flex-col gap-3.5" style="{{ $activeTab === 'login' ? '' : 'display: none !important;' }}">
                <div class="mb-2">
                    <p class="text-xs font-bold text-amber-400 uppercase tracking-widest mb-1">Welcome Back</p>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Admin Login</h2>
                    <p class="text-xs sm:text-sm text-zinc-400 mt-1">Access secure control center, wallets & audit logs.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">
                        Email Address
                    </label>
                    <input type="email" name="email" placeholder="admin@kryvex.com" required 
                           class="w-full h-12 px-4 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">
                        Password
                    </label>
                    <div class="password-field relative flex items-center w-full" style="position: relative !important; width: 100% !important; display: flex !important; align-items: center !important;">
                        <input type="password" name="password" placeholder="••••••••••••" required 
                               class="w-full h-12 pl-4 pr-12 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                        <button type="button" class="password-toggle text-zinc-400 hover:text-amber-400 transition-colors cursor-pointer bg-transparent border-0 outline-none p-0" 
                                style="position: absolute !important; right: 14px !important; top: 50% !important; transform: translateY(-50%) !important; margin: 0 !important; height: 24px !important; width: 24px !important; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 30 !important;" 
                                data-password-toggle data-show="false" aria-label="Toggle password visibility">
                            <svg class="icon-eye w-5 h-5 text-zinc-400 hover:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg class="icon-eye-off hidden w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Forgot Password Link (Below Password Input Box & Above Login Button) -->
                <div class="flex justify-end pt-0.5 pb-1">
                    <a href="{{ route('admin.forgot-password') }}" class="text-xs font-semibold text-amber-400 hover:text-yellow-300 hover:underline transition-colors cursor-pointer" data-open-forgot-password>
                        Forgot password?
                    </a>
                </div>

                <button type="submit" class="kv-btn kv-btn-primary auth-submit w-full h-12 rounded-xl bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-500 hover:from-amber-400 hover:to-yellow-300 text-zinc-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition-all transform active:scale-[0.99] flex items-center justify-center gap-2">
                    <span>Login to Console</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                </button>
            </form>

            <!-- 2. REGISTER FORM -->
            <form id="registerForm" class="auth-form flex flex-col gap-3.5" style="{{ $activeTab === 'register' ? '' : 'display: none !important;' }}">
                <div class="mb-1">
                    <p class="text-xs font-bold text-amber-400 uppercase tracking-widest mb-1">New Administrator</p>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Create Account</h2>
                    <p class="text-xs sm:text-sm text-zinc-400 mt-1">Register a new secure admin control account.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">
                        Full Name
                    </label>
                    <input type="text" name="name" placeholder="John Doe" required minlength="2" maxlength="80"
                           class="w-full h-11 px-4 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">
                        Email Address
                    </label>
                    <input type="email" name="email" placeholder="admin@kryvex.com" required
                           class="w-full h-11 px-4 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">
                        Mobile Number
                    </label>
                    <input type="text" name="phone" placeholder="10 digit mobile number" required maxlength="10" inputmode="numeric"
                           class="w-full h-11 px-4 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">
                        Password
                    </label>
                    <div class="password-field relative flex items-center w-full" style="position: relative !important; width: 100% !important; display: flex !important; align-items: center !important;">
                        <input type="password" name="password" placeholder="Password@123" required
                               class="w-full h-11 pl-4 pr-12 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                        <button type="button" class="password-toggle text-zinc-400 hover:text-amber-400 transition-colors cursor-pointer bg-transparent border-0 outline-none p-0" 
                                style="position: absolute !important; right: 14px !important; top: 50% !important; transform: translateY(-50%) !important; margin: 0 !important; height: 24px !important; width: 24px !important; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 30 !important;" 
                                data-password-toggle data-show="false" aria-label="Toggle password visibility">
                            <svg class="icon-eye w-5 h-5 text-zinc-400 hover:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg class="icon-eye-off hidden w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">
                        Confirm Password
                    </label>
                    <div class="password-field relative flex items-center w-full" style="position: relative !important; width: 100% !important; display: flex !important; align-items: center !important;">
                        <input type="password" name="password_confirmation" placeholder="Confirm password" required
                               class="w-full h-11 pl-4 pr-12 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                        <button type="button" class="password-toggle text-zinc-400 hover:text-amber-400 transition-colors cursor-pointer bg-transparent border-0 outline-none p-0" 
                                style="position: absolute !important; right: 14px !important; top: 50% !important; transform: translateY(-50%) !important; margin: 0 !important; height: 24px !important; width: 24px !important; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 30 !important;" 
                                data-password-toggle data-show="false" aria-label="Toggle password visibility">
                            <svg class="icon-eye w-5 h-5 text-zinc-400 hover:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <svg class="icon-eye-off hidden w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 py-1">
                    <input type="checkbox" name="terms" id="termsCheck" value="1" required class="terms-checkbox w-4 h-4 rounded border-zinc-800 bg-zinc-950 text-amber-400 focus:ring-amber-400 cursor-pointer">
                    <label for="termsCheck" class="text-xs text-zinc-400 cursor-pointer select-none leading-tight">
                        I accept Terms, Risk Disclosure & Admin Code of Conduct.
                    </label>
                </div>

                <button type="submit" class="kv-btn kv-btn-primary auth-submit w-full h-11 rounded-xl bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-500 hover:from-amber-400 hover:to-yellow-300 text-zinc-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition-all flex items-center justify-center gap-2">
                    Send Verification Code &rarr;
                </button>
            </form>

            <!-- 3. VERIFY REGISTER OTP FORM -->
            <form id="verifyRegisterForm" class="auth-form flex flex-col gap-4" style="display: none !important;">
                <input type="hidden" name="email" id="verifyEmailInput">

                <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-zinc-300 text-xs">
                    <div class="auth-otp-meta flex items-center justify-between">
                        <span>Code expires in <strong id="registerOtpTimer" class="text-amber-400 font-mono text-sm">120s</strong></span>
                        <button type="button" id="resendRegisterCodeButton" class="text-amber-400 hover:underline font-bold">Resend code</button>
                    </div>
                    <p id="registerResendHelp" class="text-[11px] text-zinc-400 mt-1.5 leading-normal">
                        You can resend instantly 2 times. After that, resend will be locked for 10 minutes.
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">
                        6-Digit Verification Code
                    </label>
                    <input type="text" name="code" placeholder="123456" required maxlength="6" inputmode="numeric"
                           class="w-full h-12 px-4 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white tracking-widest text-center text-lg font-mono placeholder-zinc-600 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                </div>

                <button type="submit" class="kv-btn kv-btn-primary auth-submit w-full h-12 rounded-xl bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-500 hover:from-amber-400 hover:to-yellow-300 text-zinc-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition-all">
                    Verify & Create Account
                </button>

                <a href="{{ route('admin.register') }}" id="backToRegisterButton" class="kv-btn kv-btn-outline auth-submit w-full h-10 rounded-xl bg-transparent hover:bg-zinc-800/60 text-zinc-400 hover:text-white text-xs font-semibold transition-all flex items-center justify-center" data-back-to-register>
                    ← Edit Registration Details
                </a>
            </form>

            <!-- 4. FORGOT PASSWORD STEP-BY-STEP FORM -->
            <form id="forgotPasswordForm" class="auth-form auth-secondary-form flex flex-col gap-4" style="{{ $activeTab === 'forgot' ? 'display: flex !important;' : 'display: none !important;' }}" novalidate>
                <div class="auth-form-title p-3.5 rounded-xl bg-zinc-950/90 border border-zinc-800">
                    <strong class="text-sm font-bold text-white block">Reset Admin Password</strong>
                    <span id="forgotStepSubtitle" class="text-xs text-zinc-400 block mt-0.5">Enter your email to receive a 6 digit reset code.</span>
                </div>

                <!-- Step 1: Email Section -->
                <div id="forgotEmailSection" class="auth-reset-step is-active flex flex-col gap-4" data-reset-step="email">
                    <div>
                        <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">
                            Registered Email
                        </label>
                        <input type="email" name="email" placeholder="admin@kryvex.com" required
                               class="w-full h-12 px-4 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                    </div>

                    <button type="button" id="forgotSendCodeButton" class="kv-btn kv-btn-primary auth-submit w-full h-12 rounded-xl bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-500 hover:from-amber-400 hover:to-yellow-300 text-zinc-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition-all">
                        Send Reset Code
                    </button>
                </div>

                <!-- Step 2: Code Verification Section -->
                <div id="forgotCodeSection" class="auth-reset-step auth-otp-section flex flex-col gap-4" style="display: none !important;" data-reset-step="code">
                    <div class="auth-otp-meta flex items-center justify-between text-xs p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-zinc-300">
                        <span>Code expires in <strong id="forgotOtpTimer" class="text-amber-400 font-mono">120s</strong></span>
                        <button type="button" id="forgotResendButton" class="text-amber-400 hover:underline font-semibold">Resend code</button>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">
                            6-Digit Reset Code
                        </label>
                        <input type="text" name="code" placeholder="123456" maxlength="6" inputmode="numeric"
                               class="w-full h-12 px-4 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white tracking-widest text-center text-lg font-mono placeholder-zinc-600 focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                    </div>

                    <button type="button" id="forgotVerifyCodeButton" class="kv-btn kv-btn-outline auth-submit w-full h-12 rounded-xl bg-zinc-800 hover:bg-zinc-700 text-white font-bold text-sm border border-zinc-700 transition-all">
                        Verify Reset Code
                    </button>
                </div>

                <!-- Step 3: New Password Section -->
                <div id="forgotPasswordSection" class="auth-reset-step auth-otp-section flex flex-col gap-4" style="display: none !important;" data-reset-step="password">
                    <input type="hidden" name="reset_token" id="forgotResetToken">

                    <p class="auth-tiny-note text-xs text-emerald-400 font-medium flex items-center gap-1.5">
                        <span>✓</span> Code verified. Set your new password below.
                    </p>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">
                            New Password
                        </label>
                        <div class="password-field relative flex items-center w-full" style="position: relative !important; width: 100% !important; display: flex !important; align-items: center !important;">
                            <input type="password" name="password" placeholder="New Password@123"
                                   class="w-full h-12 pl-4 pr-12 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                            <button type="button" class="password-toggle text-zinc-400 hover:text-amber-400 transition-colors cursor-pointer bg-transparent border-0 outline-none p-0" 
                                    style="position: absolute !important; right: 14px !important; top: 50% !important; transform: translateY(-50%) !important; margin: 0 !important; height: 24px !important; width: 24px !important; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 30 !important;" 
                                    data-password-toggle data-show="false" aria-label="Toggle password visibility">
                                <svg class="icon-eye w-5 h-5 text-zinc-400 hover:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <svg class="icon-eye-off hidden w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-2">
                            Confirm New Password
                        </label>
                        <div class="password-field relative flex items-center w-full" style="position: relative !important; width: 100% !important; display: flex !important; align-items: center !important;">
                            <input type="password" name="password_confirmation" placeholder="Confirm new password"
                                   class="w-full h-12 pl-4 pr-12 rounded-xl bg-zinc-950/90 border border-zinc-800 text-white placeholder-zinc-500 text-sm focus:outline-none focus:border-amber-400 focus:ring-1 focus:ring-amber-400 transition-all">
                            <button type="button" class="password-toggle text-zinc-400 hover:text-amber-400 transition-colors cursor-pointer bg-transparent border-0 outline-none p-0" 
                                    style="position: absolute !important; right: 14px !important; top: 50% !important; transform: translateY(-50%) !important; margin: 0 !important; height: 24px !important; width: 24px !important; display: flex !important; align-items: center !important; justify-content: center !important; z-index: 30 !important;" 
                                    data-password-toggle data-show="false" aria-label="Toggle password visibility">
                                <svg class="icon-eye w-5 h-5 text-zinc-400 hover:text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <svg class="icon-eye-off hidden w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="kv-btn kv-btn-primary auth-submit w-full h-12 rounded-xl bg-gradient-to-r from-amber-500 via-yellow-400 to-amber-500 hover:from-amber-400 hover:to-yellow-300 text-zinc-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition-all">
                        Reset & Update Password
                    </button>
                </div>

                <a href="{{ route('admin.login') }}" class="kv-btn kv-btn-outline auth-submit w-full h-10 rounded-xl bg-transparent hover:bg-zinc-800/60 text-zinc-400 hover:text-white text-xs font-semibold transition-all mt-1 flex items-center justify-center cursor-pointer" data-back-to-login>
                    ← Back to Login
                </a>
            </form>

        </div>
    </div>
</div>
@endsection
