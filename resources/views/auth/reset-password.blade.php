<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Choose a new password | DearYou</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}">
</head>
<body class="login-page">
<main class="login-card">
    <img class="login-logo" src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt="DearYou floral envelope">
    <p class="login-eyebrow">ACCOUNT RECOVERY</p>
    <h1>Choose a new password</h1>
    <p class="text-secondary">Use at least 10 characters with uppercase, lowercase, and a number.</p>
    <form method="post" action="{{ route('password.update') }}" class="text-start mt-4">
        @csrf
        <label class="form-label" for="email">Email</label>
        <input class="form-control mb-3" id="email" type="email" value="{{ $email }}" readonly>
        <label class="form-label" for="password">New password</label>
        <input class="form-control mb-3" id="password" type="password" name="password" required autofocus>
        <label class="form-label" for="password_confirmation">Confirm new password</label>
        <input class="form-control mb-3" id="password_confirmation" type="password" name="password_confirmation" required>
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
        <button class="btn btn-dearyou w-100"><i class="bi bi-shield-check"></i> Reset password</button>
    </form>
</main>
</body>
</html>
