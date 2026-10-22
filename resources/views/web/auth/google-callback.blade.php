@extends('layouts.app')

@section('title', 'Google Login - Kryvex')

@push('styles')
    @vite('resources/css/pages/auth.css')
@endpush

@section('content')
<div class="auth-page">
    <div class="auth-card kv-card">
        <div class="auth-head">
            <p class="kv-eyebrow">Google authentication</p>
            <h1 id="googleAuthTitle">Processing...</h1>
            <p id="googleAuthMessage">Please wait while we complete your login.</p>
        </div>
    </div>
</div>

<script>
    const googleAuthSuccess = @json($success);
    const googleAuthMessage = @json($message);
    const googleAuthPayload = @json($payload);

    const title = document.getElementById('googleAuthTitle');
    const message = document.getElementById('googleAuthMessage');

    if (googleAuthSuccess && googleAuthPayload) {
        localStorage.setItem('kryvex_access_token', googleAuthPayload.tokens.access_token);
        localStorage.setItem('kryvex_refresh_token', googleAuthPayload.tokens.refresh_token);
        localStorage.setItem('kryvex_user', JSON.stringify(googleAuthPayload.user));

        if (googleAuthPayload.is_new_user) {
            localStorage.setItem('kryvex_new_user_signup', 'true');
        }

        title.textContent = 'Login successful';
        message.textContent = 'Redirecting to dashboard...';

        setTimeout(() => {
            window.location.href = '/'; 
        }, 600);
    } else {
        title.textContent = 'Google login failed';
        message.textContent = googleAuthMessage || 'Unable to login with Google.';

        setTimeout(() => {
            window.location.href = '/login';
        }, 1600);
    }
</script>
@endsection