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
    <h1>Check your email</h1>
    <p class="text-secondary">We sent a verification link to <strong>{{ auth()->user()->email }}</strong>. Open it before creating or managing letters.</p>
    @if(session('status') === 'verification-link-sent')<div class="alert alert-success auto-dismiss-alert mt-3" role="status" data-auto-dismiss-alert>A new verification link was sent.</div>@endif
    <form method="post" action="{{ route('verification.send') }}" class="mt-4">@csrf<button class="btn btn-dearyou w-100"><i class="bi bi-send"></i> Resend verification email</button></form>
    <form method="post" action="{{ route('logout') }}" class="mt-2">@csrf<button class="btn btn-link">Sign out</button></form>
</main>
<script src="{{ asset('assets/dearyou/app.js') }}?v={{ filemtime(public_path('assets/dearyou/app.js')) }}" defer></script>
</body>
</html>
