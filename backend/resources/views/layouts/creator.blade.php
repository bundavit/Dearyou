<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My DearYou')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}">
</head>
<body class="admin-body creator-body">
<div class="creator-shell">
    @include('partials.user-navbar')
    <main class="admin-main creator-main">
        <div class="creator-content">
            <section class="creator-storage" aria-label="Media storage usage">
                <div class="creator-storage-copy">
                    <span><i class="bi bi-cloud"></i> Media storage</span>
                    <strong>{{ $creatorStorage['used_label'] }} of {{ $creatorStorage['limit_label'] }}</strong>
                </div>
                <div class="creator-storage-track" role="progressbar" aria-valuenow="{{ $creatorStorage['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: {{ $creatorStorage['percentage'] }}%"></span>
                </div>
                <small>{{ $creatorStorage['percentage'] }}% used</small>
            </section>
            @if(auth()->user()->storage_cleanup_due_at)
                <div class="storage-warning" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        <strong>Media storage needs attention.</strong>
                        <span>You are over the {{ $creatorStorage['limit_label'] }} allowance. Remove media before {{ auth()->user()->storage_cleanup_due_at->format('F j, Y g:i A') }}. After that, DearYou may remove media from your oldest expired letters.</span>
                    </div>
                </div>
            @endif
            @if(session('success'))<div class="alert alert-success auto-dismiss-alert" role="status" data-auto-dismiss-alert>{{ session('success') }}</div>@endif
            @yield('content')
        </div>
    </main>
</div>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/dearyou/app.js') }}?v={{ filemtime(public_path('assets/dearyou/app.js')) }}"></script>
</body>
</html>
