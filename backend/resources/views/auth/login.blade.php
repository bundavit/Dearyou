<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in to DearYou</title><link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}"><link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}"><link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}"></head>
<body class="login-page"><main class="login-card"><img class="login-logo" src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt="DearYou floral envelope"><p class="login-eyebrow">{{ $adminLogin ? 'PLATFORM ADMINISTRATION' : 'YOUR PRIVATE SPACE' }}</p><h1>{{ $adminLogin ? 'Admin sign in' : 'Welcome back' }}</h1><p class="text-secondary">{{ $adminLogin ? 'Authorized DearYou administrators only.' : 'Your private letters are waiting.' }}</p>
<form method="post" action="{{ request()->routeIs('login.legacy') ? route('login.legacy.store') : route('login.store') }}" class="text-start mt-4">@csrf
<label class="form-label">Email</label><input class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" required autofocus>
<div class="d-flex justify-content-between align-items-center"><label class="form-label" for="login-password">Password</label><a class="auth-inline-link" href="{{ route('password.request') }}">Forgot password?</a></div><div class="password-field mb-3"><input class="form-control" id="login-password" type="password" name="password" required autocomplete="current-password" data-password-input><button type="button" aria-label="Show password" aria-pressed="false" data-password-toggle><i class="bi bi-eye"></i></button></div>
@if(session('status'))<div class="alert alert-success auto-dismiss-alert mb-3" role="status" data-auto-dismiss-alert>{{ session('status') }}</div>@endif
@error('email')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
<label class="d-flex gap-2 mb-4"><input type="checkbox" name="remember" value="1"> Remember me</label>
<button class="btn btn-dearyou w-100"><i class="bi bi-box-arrow-in-right"></i> Sign in</button></form>
@if($adminLogin)
    <p class="auth-switch"><a href="{{ route('home') }}"><i class="bi bi-arrow-left"></i> Return to DearYou</a></p>
@else
    <p class="auth-switch">New to DearYou? <a href="{{ route('register') }}">Create an account</a></p>
@endif
</main><script src="{{ asset('assets/dearyou/app.js') }}?v={{ filemtime(public_path('assets/dearyou/app.js')) }}" defer></script></body></html>
