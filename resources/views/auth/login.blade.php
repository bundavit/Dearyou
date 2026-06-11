<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>DearYou Admin Login</title><link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}"><link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}"><link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}"><link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}"></head>
<body class="login-page"><main class="login-card"><img class="login-logo" src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt="DearYou floral envelope"><p class="login-eyebrow">DEARYOU ADMIN</p><h1>Welcome back</h1><p class="text-secondary">Your private letters are waiting.</p>
<form method="post" action="{{ route('login.store') }}" class="text-start mt-4">@csrf
<label class="form-label">Email</label><input class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" required autofocus>
<label class="form-label">Password</label><input class="form-control mb-3" type="password" name="password" required>
@error('email')<div class="text-danger small mb-3">{{ $message }}</div>@enderror
<label class="d-flex gap-2 mb-4"><input type="checkbox" name="remember" value="1"> Remember me</label>
<button class="btn btn-dearyou w-100"><i class="bi bi-box-arrow-in-right"></i> Sign in</button></form></main></body></html>
