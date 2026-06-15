<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DearYou Admin')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('dearyou-admin-mark.svg') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/dearyou/app.css') }}?v={{ filemtime(public_path('assets/dearyou/app.css')) }}">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="sidebar">
        <a class="brand d-flex align-items-center gap-2 mb-4" href="{{ route('admin.dashboard') }}"><img class="sidebar-brand-logo" src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt=""><span>DearYou</span></a>
        <nav class="nav flex-column gap-2">
            @if(auth()->user()->isAdmin())
                <a class="nav-link {{ request()->routeIs('admin.platform') ? 'active' : '' }}" href="{{ route('admin.platform') }}"><i class="bi bi-bar-chart"></i> Platform</a>
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><i class="bi bi-people"></i> Users</a>
                <a class="nav-link {{ request()->routeIs('admin.moderation.*') ? 'active' : '' }}" href="{{ route('admin.moderation.index') }}"><i class="bi bi-shield-exclamation"></i> Moderation</a>
                <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}"><i class="bi bi-sliders"></i> Settings</a>
                <a class="nav-link {{ request()->routeIs('admin.feedback.*') ? 'active' : '' }}" href="{{ route('admin.feedback.index') }}"><i class="bi bi-chat-heart"></i> Feedback</a>
                <a class="nav-link {{ request()->routeIs('admin.audit') ? 'active' : '' }}" href="{{ route('admin.audit') }}"><i class="bi bi-journal-check"></i> Audit log</a>
            @endif
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}"><i class="bi bi-grid"></i> Dashboard</a>
            <a class="nav-link {{ request()->routeIs('admin.letters.*') ? 'active' : '' }}" href="{{ route('admin.letters.index') }}"><i class="bi bi-envelope-paper-heart"></i> Letters</a>
            @php($navUnread = \App\Models\Response::whereNull('read_at')->whereHas('letter', fn($query) => $query->where('user_id', auth()->id()))->count())
            <a class="nav-link {{ request()->routeIs('admin.inbox') || request()->routeIs('admin.responses.*') ? 'active' : '' }}" href="{{ route('admin.inbox') }}"><i class="bi bi-inbox"></i> Inbox @if($navUnread)<span class="nav-count">{{ $navUnread }}</span>@endif</a>
            <a class="nav-link {{ request()->routeIs('admin.account.*') ? 'active' : '' }}" href="{{ route('admin.account.edit') }}"><i class="bi bi-person-gear"></i> Account</a>
        </nav>
        <form action="{{ route('logout') }}" method="post" class="mt-auto">@csrf<button class="btn btn-outline-light w-100"><i class="bi bi-box-arrow-left"></i> Log out</button></form>
    </aside>
    <main class="admin-main">
        @if(session('success'))<div class="alert alert-success auto-dismiss-alert" role="status" data-auto-dismiss-alert>{{ session('success') }}</div>@endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('vendor/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/dearyou/app.js') }}?v={{ filemtime(public_path('assets/dearyou/app.js')) }}"></script>
</body>
</html>
