<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Forgot password | DearYou</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}">
</head>
<body class="login-page">
<main class="login-card">
    <img class="login-logo" src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt="DearYou floral envelope">
    <p class="login-eyebrow">ACCOUNT RECOVERY</p>
    <h1>Reset your password</h1>
    <p class="text-secondary">Enter your account email and we will send a six-digit reset code.</p>
    @if(session('status'))<div class="alert alert-success auto-dismiss-alert mt-3" role="status" data-auto-dismiss-alert>{{ session('status') }}</div>@endif
    <form method="post" action="{{ route('password.email') }}" class="text-start mt-4">
        @csrf
        <label class="form-label" for="email">Email</label>
        <input class="form-control mb-3" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
        <button class="btn btn-dearyou w-100"><i class="bi bi-envelope-arrow-up"></i> Send reset code</button>
    </form>
    <p class="auth-switch"><a href="{{ route('login') }}"><i class="bi bi-arrow-left"></i> Back to sign in</a></p>
</main>
<script src="{{ asset('assets/dearyou/app.js') }}?v={{ filemtime(public_path('assets/dearyou/app.js')) }}" defer></script>
</body>
</html>
