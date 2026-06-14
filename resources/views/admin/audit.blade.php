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

<section class="dashboard-panel">
    <div class="dashboard-list">
        @forelse($audits as $audit)
            <div class="dashboard-list-item">
                <span class="dashboard-item-icon"><i class="bi bi-journal-check"></i></span>
                <span class="dashboard-item-copy">
                    <strong>{{ str($audit->action)->replace('_', ' ')->title() }}</strong>
                    <small>{{ $audit->administrator?->name ?? 'Deleted administrator' }}@if($audit->targetUser) &middot; {{ $audit->targetUser->email }}@endif</small>
                    @if($audit->reason)<small>{{ $audit->reason }}</small>@endif
                </span>
                <span class="dashboard-item-side">
                    @if($audit->letter)<a href="{{ route('admin.moderation.show', $audit->letter->id) }}">{{ $audit->letter->title }}</a>@endif
                    <small>{{ $audit->created_at->format('M j, Y g:i A') }}</small>
                </span>
            </div>
        @empty
            <div class="dashboard-empty"><i class="bi bi-journal"></i><p>Moderation actions will appear here.</p></div>
        @endforelse
    </div>
</section>
<div class="mt-4">{{ $audits->links() }}</div>
@endsection
