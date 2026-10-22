@extends('layouts.app')

@section('title', 'Admin Register - Kryvex')

@push('styles')
    @vite('resources/css/pages/auth.css')
@endpush

@push('scripts')
    @vite('resources/js/admin/auth.js')
@endpush

@section('content')
<div class="auth-page">
    <div class="auth-card kv-card">
        <div class="auth-head">
            <p class="kv-eyebrow">Create Admin account</p>
            <h1>Start with Kryvex Admin</h1>
            <p>Register a secure admin control center account.</p>
        </div>

        <div id="authAlert" class="auth-alert"></div>

        <!-- Registration form sends OTP with 120 second validity. -->
        <form id="registerForm" class="auth-form">
            <label>
                Full Name
                <input type="text" name="name" placeholder="Enter full name" required minlength="2" maxlength="80">
            </label>

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
                    <button type="button" class="password-toggle" data-password-toggle>👁</button>
                </div>
            </label>

            <label>
                Confirm Password
                <div class="password-field">
                    <input type="password" name="password_confirmation" placeholder="Confirm password" required>
                    <button type="button" class="password-toggle" data-password-toggle>👁</button>
                </div>
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
                Verify & Create Account
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
                        <button type="button" class="password-toggle" data-password-toggle>👁</button>
                    </div>
                </label>

                <label>
                    Confirm New Password
                    <div class="password-field">
                        <input type="password" name="password_confirmation" placeholder="Confirm new password">
                        <button type="button" class="password-toggle" data-password-toggle>👁</button>
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
            <a href="{{ url('/admin/login') }}">Login</a>
            <span class="auth-dot-separator">•</span>
            <button type="button" data-open-forgot-password>Forgot Password?</button>
        </p>
    </div>
</div>
@endsection
