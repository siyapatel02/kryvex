@extends('layouts.app')

@section('title', 'Profile - Kryvex')

@push('styles')
    @vite('resources/css/pages/auth.css')
@endpush

@push('scripts')
    @vite('resources/js/pages/dashboard.js')
@endpush

@section('content')
<div class="dashboard-page profile-page">
    <div class="kv-container">
        <div class="profile-hero kv-card kv-card-pad">
            <div class="profile-avatar" id="profileAvatar">U</div>
            <div class="profile-main">
                <p class="kv-eyebrow">User Profile</p>
                <h1 id="profileName">Loading profile...</h1>
                <p id="profileEmail">Please wait while we load your Kryvex account details.</p>
                <div class="profile-tags">
                    <span id="profileStatusTag">Status: --</span>
                    <span id="profileKycTag">KYC: --</span>
                    <span id="profileEmailTag">Email: --</span>
                </div>
            </div>
            <div class="profile-actions">
                <a href="{{ url('/wallet') }}" class="kv-btn kv-btn-outline">Wallet</a>
                <a href="{{ url('/futures') }}" class="kv-btn kv-btn-primary">Start Trading</a>
            </div>
        </div>

        <div id="dashboardAlert" class="auth-alert"></div>

        <section class="profile-grid">
            <div class="kv-card kv-card-pad profile-info-card profile-name-card">
                <span>Full Name</span>
                <div class="name-display-row" id="nameDisplayRow">
                    <strong id="dashName">--</strong>
                    <button type="button" id="editNameButton" class="edit-name-btn" title="Edit username"> Edit</button>
                </div>
                <div class="name-edit-row" id="nameEditRow" style="display: none;">
                    <input type="text" id="editNameInput" class="name-edit-input" placeholder="Enter new username" maxlength="50">
                    <div class="name-edit-actions">
                        <button type="button" id="saveNameButton" class="save-name-btn">Save</button>
                        <button type="button" id="cancelNameButton" class="cancel-name-btn">Cancel</button>
                    </div>
                </div>
            </div>

            <div class="kv-card kv-card-pad profile-info-card">
                <span>Email Address</span>
                <strong id="dashEmail">--</strong>
            </div>

            <div class="kv-card kv-card-pad profile-info-card">
                <span>KYC Status</span>
                <strong id="dashKyc">--</strong>
            </div>

            <div class="kv-card kv-card-pad profile-info-card">
                <span>Email Verified</span>
                <strong id="dashEmailVerified">--</strong>
            </div>

            <div class="kv-card kv-card-pad profile-info-card">
                <span>User UID</span>
                <strong id="dashUid">--</strong>
            </div>

            <div class="kv-card kv-card-pad profile-info-card">
                <span>Login Type</span>
                <strong id="dashLoginType">--</strong>
            </div>
        </section>

        <section class="profile-detail-row">

            <div class="kv-card kv-card-pad profile-panel profile-password-panel">
                <h2>Change Password</h2>
                <p>Enter your current password first, then set a new password.</p>

                <!-- Manual password change. This does not use OTP or countdown. -->
                <form id="changePasswordForm" class="auth-form profile-password-form">
                    <label>
                        Current Password
                        <div class="password-field">
                            <input type="password" name="current_password" placeholder="Current password" required>
                            <button type="button" class="password-toggle" data-password-toggle>👁</button>
                        </div>
                    </label>

                    <label>
                        New Password
                        <div class="password-field">
                            <input type="password" name="password" placeholder="New Password@123" required>
                            <button type="button" class="password-toggle" data-password-toggle>👁</button>
                        </div>
                    </label>

                    <label>
                        Confirm New Password
                        <div class="password-field">
                            <input type="password" name="password_confirmation" placeholder="Confirm new password" required>
                            <button type="button" class="password-toggle" data-password-toggle>👁</button>
                        </div>
                    </label>

                    <button type="submit" class="kv-btn kv-btn-primary auth-submit">
                        Change Password
                    </button>
                </form>

                <div class="profile-forgot-link-row">
                    <button type="button" id="openProfileForgotPassword" class="profile-forgot-link">
                        Forgot your password?
                    </button>
                </div>

                <!-- Forgot password flow from profile.
                     Step 1 email → Step 2 OTP verify within 120 seconds → Step 3 set password. -->
                <form id="profileForgotPasswordForm" class="auth-form profile-password-form profile-forgot-form" style="display: none;" novalidate>
                    <div class="auth-form-title">
                        <strong>Reset password by email</strong>
                        <span id="profileForgotStepSubtitle">Enter your email to receive a 6 digit reset code.</span>
                    </div>

                    <div id="profileForgotEmailSection" class="auth-reset-step is-active" data-reset-step="email">
                        <label>
                            Email
                            <input type="email" name="email" id="profileForgotEmail" placeholder="Your registered email" required>
                        </label>

                        <button type="button" id="profileForgotSendCodeButton" class="kv-btn kv-btn-primary auth-submit">
                            Send Reset Code
                        </button>
                    </div>

                    <div id="profileForgotCodeSection" class="auth-reset-step auth-otp-section" style="display: none;" data-reset-step="code">
                        <div class="auth-otp-meta">
                            <span>Code expires in <strong id="profileForgotOtpTimer">120s</strong></span>
                            <button type="button" id="profileForgotResendButton">Resend code</button>
                        </div>

                        <label>
                            Reset Code
                            <input type="text" name="code" placeholder="Enter 6 digit code" maxlength="6" inputmode="numeric">
                        </label>

                        <button type="button" id="profileForgotVerifyCodeButton" class="kv-btn kv-btn-outline auth-submit">
                            Verify Code
                        </button>
                    </div>

                    <div id="profileForgotPasswordSection" class="auth-reset-step auth-otp-section" style="display: none;" data-reset-step="password">
                        <input type="hidden" name="reset_token" id="profileForgotResetToken">

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
                </form>
            </div>

            <div class="kv-card kv-card-pad profile-panel">
                <h2>Quick Actions</h2>
                <p>Use these shortcuts instead of going back to the home page every time.</p>
                <div class="profile-quick-actions">
                    <a href="{{ url('/wallet') }}" class="kv-btn kv-btn-outline">Open Wallet</a>
                    <a href="{{ url('/futures') }}" class="kv-btn kv-btn-outline">Futures</a>
                    <a href="{{ url('/options') }}" class="kv-btn kv-btn-outline">Options</a>
                </div>
            </div>
        </section>

    </div>
</div>
@endsection
