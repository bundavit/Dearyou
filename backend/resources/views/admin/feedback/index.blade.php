@extends('layouts.app')
@section('title', 'Feedback - DearYou')
@section('content')
<div class="admin-page-header">
    <div><p class="eyebrow">COMMUNITY</p><h1>Feedback</h1><p class="dashboard-subtitle">Private suggestions and reports sent by DearYou visitors.</p></div>
</div>
<form class="filter-card feedback-filter mb-4" method="get" data-auto-filter>
    <select class="form-select" name="status" data-auto-filter-change>
        <option value="">All statuses</option>
        @foreach(\App\Models\Feedback::STATUSES as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach
    </select>
    <select class="form-select" name="category" data-auto-filter-change>
        <option value="">All categories</option>
        @foreach(\App\Models\Feedback::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>@endforeach
    </select>
    <button class="btn btn-outline-secondary auto-filter-submit"><i class="bi bi-funnel"></i> Filter</button>
    <a class="btn btn-link auto-filter-clear" href="{{ route('admin.feedback.index') }}"><i class="bi bi-x-lg"></i> Clear</a>
</form>
<section class="dashboard-panel">
    <div class="admin-record-list">
        @forelse($feedback as $item)
            @php
                $categoryIcons = [
                    'suggestion' => 'bi-lightbulb',
                    'bug' => 'bi-bug',
                    'design' => 'bi-palette',
                    'other' => 'bi-chat-dots',
                ];
            @endphp
            <a class="admin-record-card feedback-admin-item {{ $item->status === 'new' ? 'is-new' : '' }}" href="{{ route('admin.feedback.show', $item) }}">
                <span class="admin-record-icon"><i class="bi {{ $categoryIcons[$item->category] ?? 'bi-chat-heart' }}"></i></span>
                <span class="admin-record-content">
                    <span class="admin-record-heading">
                        <strong>{{ \App\Models\Feedback::CATEGORIES[$item->category] }}</strong>
                        @if($item->rating)
                            <span class="admin-record-rating" aria-label="{{ $item->rating }} out of 5 stars"><i class="bi bi-star-fill"></i> {{ $item->rating }}</span>
                        @endif
                    </span>
                    <span class="admin-record-message">{{ Str::limit($item->message, 150) }}</span>
                    <span class="admin-record-meta">
                        <span><i class="bi bi-person"></i> {{ $item->user?->name ?? 'Guest visitor' }}</span>
                        @if($item->email)<span><i class="bi bi-envelope"></i> {{ $item->email }}</span>@endif
                    </span>
                </span>
                <span class="admin-record-side">
                    <span class="admin-record-status status-{{ $item->status }}">{{ \App\Models\Feedback::STATUSES[$item->status] }}</span>
                    <small><i class="bi bi-clock"></i> {{ $item->created_at->diffForHumans() }}</small>
                    <span class="admin-record-open">Review <i class="bi bi-arrow-right"></i></span>
                </span>
            </a>
        @empty
            <div class="dashboard-empty"><i class="bi bi-chat"></i><p>No feedback matches these filters.</p></div>
        @endforelse
    </div>
</section>
<div class="mt-4">{{ $feedback->links() }}</div>
@endsection
