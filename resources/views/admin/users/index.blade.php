@extends('layouts.app')
@section('title', 'DearYou Users')
@section('content')
<a class="btn btn-link admin-back-link" href="{{ route('admin.platform') }}"><i class="bi bi-arrow-left"></i> Platform overview</a>
<div class="admin-page-header">
    <div>
        <p class="eyebrow">PLATFORM USERS</p>
        <h1>Accounts</h1>
        <p class="dashboard-subtitle">Manage access and roles without opening private letter content.</p>
    </div>
</div>

<form method="get" class="platform-user-filter mb-4">
    <input class="form-control" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name or email">
    <select class="form-select" name="role">
        <option value="">All roles</option>
        <option value="user" @selected(($filters['role'] ?? '') === 'user')>Users</option>
        <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admins</option>
    </select>
    <select class="form-select" name="status">
        <option value="">All statuses</option>
        <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
        <option value="disabled" @selected(($filters['status'] ?? '') === 'disabled')>Disabled</option>
        <option value="deleted" @selected(($filters['status'] ?? '') === 'deleted')>Deleted</option>
    </select>
    <button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button>
    @if(array_filter($filters))<a class="btn btn-link" href="{{ route('admin.users.index') }}">Clear</a>@endif
</form>

<div class="platform-user-list">
    @forelse($users as $user)
        <article class="platform-user-card">
            <a class="platform-user-card-link" href="{{ route('admin.users.show', $user) }}" aria-label="View {{ $user->name }}"></a>
            <span class="platform-user-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            <div class="platform-user-copy">
                <div class="platform-user-heading">
                    <strong>{{ $user->name }}</strong>
                    <span class="badge text-bg-{{ $user->isAdmin() ? 'primary' : 'secondary' }}">{{ $user->role }}</span>
                    @if($user->trashed())
                        <span class="badge text-bg-dark">deleted</span>
                    @elseif($user->disabled_at)
                        <span class="badge text-bg-danger">disabled</span>
                    @else
                        <span class="badge text-bg-success">active</span>
                    @endif
                    @if(auth()->user()->is($user))<span class="badge text-bg-light">you</span>@endif
                </div>
                <span>{{ $user->email }}</span>
                <small>Joined {{ $user->created_at->format('M j, Y') }}</small>
            </div>
            <div class="platform-user-metrics">
                <span><strong>{{ $user->letters_count }}</strong> letters</span>
                <span><strong>{{ $user->letters_sum_open_count ?? 0 }}</strong> opens</span>
                <span><strong>{{ $user->storage_usage['used_label'] }}</strong> media</span>
                <i class="bi bi-chevron-right"></i>
            </div>
        </article>
    @empty
        <div class="empty-card"><i class="bi bi-people"></i><h2>No matching accounts</h2><p>Try clearing or changing the filters.</p></div>
    @endforelse
</div>

{{ $users->links() }}
@endsection
