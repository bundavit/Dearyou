@extends('layouts.app')
@section('title', 'Letter Moderation - DearYou')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">PRIVACY-FIRST REVIEW</p>
        <h1>Letter moderation</h1>
        <p class="dashboard-subtitle">Review account and delivery metadata without opening private messages.</p>
    </div>
</div>

<form method="get" class="filter-card mb-4">
    <div class="row g-2">
        <div class="col-lg"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search title, creator, or email"></div>
        <div class="col-lg-3">
            <select class="form-select" name="state">
                <option value="">All states</option>
                <option value="published" @selected(request('state') === 'published')>Published</option>
                <option value="moderated" @selected(request('state') === 'moderated')>Moderated</option>
                <option value="deleted" @selected(request('state') === 'deleted')>Deleted</option>
            </select>
        </div>
        <div class="col-auto"><button class="btn btn-outline-secondary"><i class="bi bi-funnel"></i> Filter</button></div>
    </div>
</form>

<section class="dashboard-panel">
    <div class="dashboard-list">
        @forelse($letters as $letter)
            <a class="dashboard-list-item" href="{{ route('admin.moderation.show', $letter->id) }}">
                <span class="dashboard-item-icon"><i class="bi bi-envelope-exclamation"></i></span>
                <span class="dashboard-item-copy">
                    <strong>{{ $letter->title }}</strong>
                    <small>{{ $letter->user->name }} &middot; {{ $letter->user->email }} &middot; {{ ucfirst($letter->category) }}</small>
                </span>
                <span class="dashboard-item-side">
                    @if($letter->trashed())
                        <span class="badge text-bg-danger">deleted</span>
                    @elseif($letter->moderation_disabled_at)
                        <span class="badge text-bg-warning">moderated</span>
                    @else
                        <span class="badge text-bg-{{ $letter->status === 'published' ? 'success' : 'secondary' }}">{{ $letter->status }}</span>
                    @endif
                    <small>{{ $letter->open_count }} opens &middot; {{ $letter->responses_count }} replies</small>
                </span>
            </a>
        @empty
            <div class="dashboard-empty"><i class="bi bi-shield-check"></i><p>No letters match these filters.</p></div>
        @endforelse
    </div>
</section>
<div class="mt-4">{{ $letters->links() }}</div>
@endsection
