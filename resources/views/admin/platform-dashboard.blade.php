@extends('layouts.app')
@section('title', 'DearYou Platform')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">PLATFORM ADMIN</p>
        <h1>DearYou at a glance</h1>
        <p class="dashboard-subtitle">See how the whole platform is being used without opening anyone's private letter content.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.users.index') }}" class="btn btn-dearyou"><i class="bi bi-people"></i> Manage users</a>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary"><i class="bi bi-envelope-heart"></i> My letters</a>
    </div>
</div>

@php($statCards = [
    'users' => ['Users', 'bi-people'],
    'admins' => ['Admins', 'bi-shield-check'],
    'deleted' => ['Deleted accounts', 'bi-person-x'],
    'letters' => ['Letters', 'bi-envelope-paper-heart'],
    'published' => ['Published', 'bi-send-check'],
    'responses' => ['Responses', 'bi-chat-heart'],
    'opens' => ['Link opens', 'bi-eye'],
    'homepage_visits' => ['Homepage visits', 'bi-globe2'],
    'storage' => ['Media storage', 'bi-cloud'],
    'cleanups' => ['Cleanup records', 'bi-clock-history'],
    'moderated' => ['Moderated', 'bi-shield-exclamation'],
    'audits' => ['Audit actions', 'bi-journal-check'],
    'feedback' => ['Feedback', 'bi-chat-heart'],
    'new_feedback' => ['New feedback', 'bi-chat-dots'],
    'feedback_rating' => ['Average rating', 'bi-star-fill'],
])
<div class="dashboard-stats">
    @foreach($statCards as $key => [$label, $icon])
        <div class="stat-card dashboard-stat">
            <span class="dashboard-stat-icon"><i class="bi {{ $icon }}"></i></span>
            <span>{{ $label }}</span>
            <strong>{{ $stats[$key] }}</strong>
        </div>
    @endforeach
</div>

<section class="dashboard-panel analytics-panel mt-4">
    <div class="dashboard-panel-header">
        <div>
            <p class="eyebrow">HOMEPAGE ACTIVITY</p>
            <h2>Homepage visits over time</h2>
        </div>
        <span class="analytics-total">{{ $stats['homepage_visits'] }} total visit{{ $stats['homepage_visits'] === 1 ? '' : 's' }}</span>
    </div>

    <div class="analytics-grid">
        <div class="analytics-card">
            <h3>Last 7 days</h3>
            <div class="analytics-bars">
                @foreach($homepageVisitsByDay as $point)
                    <div class="analytics-bar-row">
                        <span>{{ $point['label'] }}</span>
                        <span class="analytics-bar-track"><span class="analytics-bar-fill" style="--bar-size: {{ $point['percent'] }}%"></span></span>
                        <strong>{{ $point['count'] }}</strong>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="analytics-card">
            <h3>Last 4 weeks</h3>
            <div class="analytics-bars">
                @foreach($homepageVisitsByWeek as $point)
                    <div class="analytics-bar-row">
                        <span>{{ $point['label'] }}</span>
                        <span class="analytics-bar-track"><span class="analytics-bar-fill" style="--bar-size: {{ $point['percent'] }}%"></span></span>
                        <strong>{{ $point['count'] }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>

<div class="platform-dashboard-grid mt-4">
    <section class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div><p class="eyebrow">NEW ACCOUNTS</p><h2>Recent users</h2></div>
            <a href="{{ route('admin.users.index') }}">View all</a>
        </div>
        <div class="dashboard-list">
            @forelse($recentUsers as $user)
                <a class="dashboard-list-item" href="{{ route('admin.users.show', $user) }}">
                    <span class="dashboard-item-icon"><i class="bi bi-person"></i></span>
                    <span class="dashboard-item-copy">
                        <strong>{{ $user->name }}</strong>
                        <small>{{ $user->email }}</small>
                    </span>
                    <span class="dashboard-item-side"><small>{{ $user->created_at->diffForHumans() }}</small></span>
                </a>
            @empty
                <div class="dashboard-empty"><i class="bi bi-person-plus"></i><p>New registered users will appear here.</p></div>
            @endforelse
        </div>
    </section>

    <section class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div><p class="eyebrow">COMMUNITY</p><h2>Recent feedback</h2></div>
            <a href="{{ route('admin.feedback.index') }}">View all</a>
        </div>
        <div class="dashboard-list">
            @forelse($recentFeedback as $item)
                <a class="dashboard-list-item" href="{{ route('admin.feedback.show', $item) }}">
                    <span class="dashboard-item-icon"><i class="bi bi-chat-heart"></i></span>
                    <span class="dashboard-item-copy">
                        <strong>{{ \App\Models\Feedback::CATEGORIES[$item->category] }}</strong>
                        <small>{{ Str::limit($item->message, 56) }}</small>
                    </span>
                    <span class="dashboard-item-side">
                        @if($item->rating)<small><i class="bi bi-star-fill"></i> {{ $item->rating }}</small>@endif
                        <small>{{ $item->created_at->diffForHumans() }}</small>
                    </span>
                </a>
            @empty
                <div class="dashboard-empty"><i class="bi bi-chat"></i><p>New visitor feedback will appear here.</p></div>
            @endforelse
        </div>
    </section>
</div>
@endsection
