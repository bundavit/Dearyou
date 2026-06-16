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

<form method="get" class="filter-card moderation-filter mb-4" data-auto-filter>
    <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search title, creator, or email" data-auto-filter-search>
    <select class="form-select" name="state" data-auto-filter-change>
        <option value="">All states</option>
        <option value="published" @selected(request('state') === 'published')>Published</option>
        <option value="moderated" @selected(request('state') === 'moderated')>Moderated</option>
        <option value="deleted" @selected(request('state') === 'deleted')>Deleted</option>
    </select>
    <select class="form-select" name="category" data-auto-filter-change>
        <option value="">All occasions</option>
        @foreach(\App\Support\PlatformSettings::CATEGORY_OPTIONS as $category => $label)
            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $label }}</option>
        @endforeach
    </select>
    <button class="btn btn-outline-secondary auto-filter-submit"><i class="bi bi-funnel"></i> Filter</button>
    <a class="btn btn-link auto-filter-clear" href="{{ route('admin.moderation.index') }}"><i class="bi bi-x-lg"></i> Clear</a>
</form>

<section class="dashboard-panel">
    <div class="admin-record-list">
        @forelse($letters as $letter)
            @php
                $moderationState = $letter->trashed() ? 'deleted' : ($letter->moderation_disabled_at ? 'moderated' : $letter->status);
            @endphp
            <a class="admin-record-card moderation-record-card" href="{{ route('admin.moderation.show', $letter->id) }}">
                <span class="admin-record-icon moderation-record-icon"><i class="bi bi-envelope-exclamation"></i></span>
                <span class="admin-record-content">
                    <span class="admin-record-heading">
                        <strong>{{ $letter->title }}</strong>
                        <span class="moderation-category">{{ \App\Support\PlatformSettings::CATEGORY_OPTIONS[$letter->category] ?? ucfirst($letter->category) }}</span>
                    </span>
                    <span class="admin-record-meta">
                        <span><i class="bi bi-person"></i> {{ $letter->user->name }}</span>
                        <span><i class="bi bi-envelope"></i> {{ $letter->user->email }}</span>
                    </span>
                    @if($letter->moderation_disabled_at)
                        <span class="moderation-note"><i class="bi bi-shield-exclamation"></i> Public access disabled by moderation</span>
                    @endif
                </span>
                <span class="admin-record-side">
                    <span class="admin-record-status status-{{ $moderationState }}">{{ ucfirst($moderationState) }}</span>
                    <span class="moderation-metrics">
                        <small><i class="bi bi-eye"></i> {{ $letter->open_count }} opens</small>
                        <small><i class="bi bi-chat-heart"></i> {{ $letter->responses_count }} replies</small>
                    </span>
                    <span class="admin-record-open">Review <i class="bi bi-arrow-right"></i></span>
                </span>
            </a>
        @empty
            <div class="dashboard-empty"><i class="bi bi-shield-check"></i><p>No letters match these filters.</p></div>
        @endforelse
    </div>
</section>
<div class="mt-4">{{ $letters->links() }}</div>
@endsection
