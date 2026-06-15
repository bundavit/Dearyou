<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verify your email | DearYou</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}">
</head>
<body class="login-page">
<main class="login-card">
    <img class="login-logo" src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt="DearYou floral envelope">
    <p class="login-eyebrow">ONE LAST STEP</p>
    <h1>Enter your code</h1>
    <p class="text-secondary">We sent a six-digit verification code to <strong>{{ auth()->user()->email }}</strong>. It expires in 10 minutes.</p>
    @if(session('status') === 'verification-code-sent')<div class="alert alert-success auto-dismiss-alert mt-3" role="status" data-auto-dismiss-alert>A new verification code was sent.</div>@endif
    <form method="post" action="{{ route('verification.verify') }}" class="text-start mt-4">
        @csrf
        <label class="form-label" for="code">Six-digit code</label>
        <input class="form-control text-center fs-4 mb-3" id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" required autofocus>
        @error('code')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
        <button class="btn btn-dearyou w-100"><i class="bi bi-patch-check"></i> Verify email</button>
    </form>
    <form method="post" action="{{ route('verification.send') }}" class="mt-3">@csrf<button class="btn btn-link"><i class="bi bi-send"></i> Send a new code</button></form>
    <form method="post" action="{{ route('logout') }}" class="mt-2">@csrf<button class="btn btn-link">Sign out</button></form>
</main>
<script src="{{ asset('assets/dearyou/app.js') }}?v={{ filemtime(public_path('assets/dearyou/app.js')) }}" defer></script>
</body>
</html>
