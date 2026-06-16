@extends('layouts.app')
@section('title', 'Audit Log - DearYou')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">ACCOUNTABILITY</p>
        <h1>Moderation audit log</h1>
        <p class="dashboard-subtitle">A permanent history of privacy-sensitive and administrative actions.</p>
    </div>
</div>

<form method="get" class="filter-card audit-filter mb-4" data-auto-filter>
    <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search action, administrator, user, letter, or reason" data-auto-filter-search>
    <select class="form-select" name="action" data-auto-filter-change>
        <option value="">All actions</option>
        @foreach($actions as $action)
            <option value="{{ $action }}" @selected(request('action') === $action)>{{ str($action)->replace('_', ' ')->title() }}</option>
        @endforeach
    </select>
    <button class="btn btn-outline-secondary auto-filter-submit"><i class="bi bi-funnel"></i> Filter</button>
    <a class="btn btn-link auto-filter-clear" href="{{ route('admin.audit') }}"><i class="bi bi-x-lg"></i> Clear</a>
</form>

<section class="dashboard-panel">
    <div class="admin-record-list audit-record-list">
        @forelse($audits as $audit)
            <article class="admin-record-card audit-record-card">
                <span class="admin-record-icon audit-record-icon"><i class="bi bi-shield-check"></i></span>
                <span class="admin-record-content">
                    <span class="admin-record-heading">
                        <strong>{{ str($audit->action)->replace('_', ' ')->title() }}</strong>
                        <span class="audit-action-badge">Recorded</span>
                    </span>
                    <span class="admin-record-meta">
                        <span><i class="bi bi-person-check"></i> {{ $audit->administrator?->name ?? 'Deleted administrator' }}</span>
                        @if($audit->targetUser)<span><i class="bi bi-person"></i> {{ $audit->targetUser->email }}</span>@endif
                    </span>
                    @if($audit->reason)
                        <span class="audit-reason"><i class="bi bi-chat-left-text"></i> {{ $audit->reason }}</span>
                    @endif
                </span>
                <span class="admin-record-side">
                    @if($audit->letter)
                        <a class="audit-letter-link" href="{{ route('admin.moderation.show', $audit->letter->id) }}"><i class="bi bi-envelope-paper"></i> {{ Str::limit($audit->letter->title, 32) }}</a>
                    @endif
                    <small><i class="bi bi-calendar3"></i> {{ $audit->created_at->format('M j, Y') }}</small>
                    <small><i class="bi bi-clock"></i> {{ $audit->created_at->format('g:i A') }}</small>
                </span>
            </article>
        @empty
            <div class="dashboard-empty"><i class="bi bi-journal"></i><p>Moderation actions will appear here.</p></div>
        @endforelse
    </div>
</section>
<div class="mt-4">{{ $audits->links() }}</div>
@endsection
