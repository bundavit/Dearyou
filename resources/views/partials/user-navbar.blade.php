@php
    $navUser = auth()->user();
    $isAdmin = $navUser?->isAdmin() ?? false;
    $lettersRoute = $isAdmin ? 'admin.letters.index' : 'letters.index';
    $inboxRoute = $isAdmin ? 'admin.inbox' : 'inbox';
    $createRoute = $isAdmin ? 'admin.letters.create' : 'letters.create';
    $accountRoute = $isAdmin ? 'admin.account.edit' : 'account.edit';
    $lettersActive = request()->routeIs('letters.*') || request()->routeIs('admin.letters.*');
    $inboxActive = request()->routeIs('inbox', 'responses.*', 'admin.inbox', 'admin.responses.*');
@endphp
<header class="creator-header user-site-header" data-user-navbar>
    <a class="creator-brand" href="{{ route('home') }}" aria-label="DearYou home">
        <img src="{{ asset('assets/dearyou/dearyou-floral-envelope-logo-web.webp') }}" alt="">
        <span>
            <strong>DearYou</strong>
            @auth
                <small>{{ $isAdmin ? 'Platform Admin' : 'My DearYou' }}</small>
            @endauth
        </span>
    </a>

    <nav class="creator-nav" aria-label="DearYou navigation">
        <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
            <i class="bi bi-house" aria-hidden="true"></i> Home
        </a>
        @auth
            <a class="{{ $lettersActive ? 'active' : '' }}" href="{{ route($lettersRoute) }}">
                <i class="bi bi-envelope-paper-heart" aria-hidden="true"></i> My Letters
            </a>
            <a class="{{ $inboxActive ? 'active' : '' }}" href="{{ route($inboxRoute) }}">
                <i class="bi bi-inbox" aria-hidden="true"></i> Inbox
                @if($navUnread)
                    <span class="home-unread-count" aria-label="{{ $navUnread }} unread responses">{{ $navUnread }}</span>
                @endif
            </a>
        @endauth
        <a href="{{ route('home') }}#about"><i class="bi bi-info-circle" aria-hidden="true"></i> About</a>
    </nav>

    <div class="creator-actions">
        <a class="home-nav-button" href="{{ auth()->check() ? route($createRoute) : route('register') }}">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <span>{{ auth()->check() ? 'Create Letter' : 'Start Creating' }}</span>
        </a>
        <details class="home-profile-menu">
            <summary aria-label="Open account menu">
                @if($navUser?->avatar_path)
                    <img class="user-nav-avatar" src="{{ Storage::url($navUser->avatar_path) }}" alt="">
                @elseif($navUser)
                    <span class="user-nav-avatar user-nav-initial" aria-hidden="true">{{ Str::upper(Str::substr($navUser->name, 0, 1)) }}</span>
                @else
                    <i class="bi bi-person-fill" aria-hidden="true"></i>
                @endif
            </summary>
            <div class="home-profile-dropdown">
                @auth
                    <div class="home-profile-summary">
                        <strong>{{ $navUser->name }}</strong>
                        <span>{{ $navUser->email }}</span>
                    </div>
                    <a href="{{ route($accountRoute) }}"><i class="bi bi-person-circle"></i> Profile & settings</a>
                    @if($isAdmin)
                        <a href="{{ route('admin.platform') }}"><i class="bi bi-bar-chart"></i> Admin dashboard</a>
                    @endif
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button type="submit"><i class="bi bi-box-arrow-right"></i> Log out</button>
                    </form>
                @else
                    <a href="{{ route('login') }}"><i class="bi bi-box-arrow-in-right"></i> Log in</a>
                    <a href="{{ route('register') }}"><i class="bi bi-person-plus"></i> Create account</a>
                @endauth
            </div>
        </details>
        <details class="creator-mobile-menu">
            <summary aria-label="Open navigation menu"><i class="bi bi-list"></i></summary>
            <div class="home-mobile-dropdown">
                <a href="{{ route('home') }}"><i class="bi bi-house"></i> Home</a>
                @auth
                    <a href="{{ route($lettersRoute) }}"><i class="bi bi-envelope-paper-heart"></i> My Letters</a>
                    <a href="{{ route($inboxRoute) }}"><i class="bi bi-inbox"></i> Inbox @if($navUnread)<span>{{ $navUnread }}</span>@endif</a>
                @endauth
                <a href="{{ route('home') }}#about"><i class="bi bi-info-circle"></i> About</a>
            </div>
        </details>
    </div>
</header>
