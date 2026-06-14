<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Create your DearYou account</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}">
</head>
<body class="login-page">
<main class="login-card">
    <img class="login-logo" src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt="DearYou floral envelope">
    <p class="login-eyebrow">CREATE YOUR SPACE</p>
    <h1>Start writing</h1>
    <p class="text-secondary">Create private letters and share them with only the people you choose.</p>

    <form method="post" action="{{ route('register.store') }}" class="text-start mt-4">
        @csrf
        <label class="form-label" for="name">Your name</label>
        <input class="form-control mb-3" id="name" type="text" name="name" value="{{ old('name') }}" maxlength="100" required autofocus>
        @error('name')<div class="text-danger small mb-3">{{ $message }}</div>@enderror

        <label class="form-label" for="email">Email</label>
        <input class="form-control mb-3" id="email" type="email" name="email" value="{{ old('email') }}" required>
        @error('email')<div class="text-danger small mb-3">{{ $message }}</div>@enderror

        <label class="form-label" for="password">Password</label>
        <input class="form-control mb-2" id="password" type="password" name="password" required>
        <p class="small text-secondary mb-3">Use at least 10 characters with uppercase, lowercase, and a number.</p>
        @error('password')<div class="text-danger small mb-3">{{ $message }}</div>@enderror

        <label class="form-label" for="password_confirmation">Confirm password</label>
        <input class="form-control mb-4" id="password_confirmation" type="password" name="password_confirmation" required>

        <button class="btn btn-dearyou w-100"><i class="bi bi-envelope-plus"></i> Create account</button>
    </form>
    <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
</main>
</body>
</html>
