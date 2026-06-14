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
    'storage' => ['Media storage', 'bi-cloud'],
    'cleanups' => ['Cleanup records', 'bi-clock-history'],
    'moderated' => ['Moderated', 'bi-shield-exclamation'],
    'audits' => ['Audit actions', 'bi-journal-check'],
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

<section class="dashboard-panel mt-4">
    <div class="dashboard-panel-header">
        <div><p class="eyebrow">NEW ACCOUNTS</p><h2>Recent users</h2></div>
    </div>
    <div class="dashboard-list">
        @forelse($recentUsers as $user)
            <a class="dashboard-list-item" href="{{ route('admin.users.show', $user) }}">
                <span class="dashboard-item-icon"><i class="bi bi-person"></i></span>
                <span class="dashboard-item-copy">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->email }}</small>
                </span>
                <span class="dashboard-item-side"><small>Joined {{ $user->created_at->diffForHumans() }}</small></span>
            </a>
        @empty
            <div class="dashboard-empty"><i class="bi bi-person-plus"></i><p>New registered users will appear here.</p></div>
        @endforelse
    </div>
</section>
@endsection
