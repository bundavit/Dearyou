@extends('layouts.app')
@section('title','DearYou Admin Dashboard')
@section('content')
<div class="admin-page-header">
    <div><p class="eyebrow">OVERVIEW</p><h1>Your thoughtful corner</h1><p class="dashboard-subtitle">Manage your private letters and see what needs your attention.</p></div>
    <a href="{{ route('admin.letters.create') }}" class="btn btn-dearyou"><i class="bi bi-plus-lg"></i> New letter</a>
</div>
@php($statCards = [
    'total' => ['Total letters', 'bi-envelope-paper-heart', route('admin.letters.index')],
    'published' => ['Published', 'bi-send-check', route('admin.letters.index', ['status' => 'published'])],
    'drafts' => ['Drafts', 'bi-pencil-square', route('admin.letters.index', ['status' => 'draft'])],
    'expired' => ['Expired', 'bi-clock-history', route('admin.letters.index')],
    'responses' => ['Responses', 'bi-chat-heart', route('admin.inbox')],
    'unread' => ['Unread', 'bi-envelope-exclamation', route('admin.inbox', ['status' => 'unread'])],
])
<div class="dashboard-stats">
    @foreach($statCards as $key => [$label, $icon, $url])
        <a class="stat-card dashboard-stat" href="{{ $url }}">
            <span class="dashboard-stat-icon"><i class="bi {{ $icon }}"></i></span>
            <span>{{ $label }}</span>
            <strong>{{ $stats[$key] }}</strong>
        </a>
    @endforeach
</div>

<div class="dashboard-grid">
    <section class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div><p class="eyebrow">RECENTLY UPDATED</p><h2>Your letters</h2></div>
            <a href="{{ route('admin.letters.index') }}">View all <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="dashboard-list">
            @forelse($recentLetters as $letter)
                <a class="dashboard-list-item" href="{{ route('admin.letters.show', $letter) }}">
                    <span class="dashboard-item-icon"><i class="bi bi-envelope-heart"></i></span>
                    <span class="dashboard-item-copy">
                        <strong>{{ $letter->title }}</strong>
                        <small>For {{ $letter->recipient_name }} · {{ $letter->updated_at->diffForHumans() }}</small>
                    </span>
                    <span class="dashboard-item-side">
                        <span class="badge text-bg-{{ $letter->status === 'published' ? 'success' : ($letter->status === 'draft' ? 'secondary' : 'warning') }}">{{ $letter->status }}</span>
                        <small><i class="bi bi-chat-heart"></i> {{ $letter->responses_count }}</small>
                    </span>
                </a>
            @empty
                <div class="dashboard-empty"><i class="bi bi-envelope-plus"></i><p>Your first letter will appear here.</p></div>
            @endforelse
        </div>
    </section>

    <section class="dashboard-panel">
        <div class="dashboard-panel-header">
            <div><p class="eyebrow">PRIVATE REPLIES</p><h2>Recent responses</h2></div>
            <a href="{{ route('admin.inbox') }}">Open inbox <i class="bi bi-arrow-right"></i></a>
        </div>
        <div class="dashboard-list">
            @forelse($recentResponses as $response)
                <a class="dashboard-list-item" href="{{ route('admin.responses.show', $response) }}">
                    <span class="dashboard-item-icon {{ $response->read_at ? '' : 'is-unread' }}"><i class="bi bi-chat-heart"></i></span>
                    <span class="dashboard-item-copy">
                        <strong>{{ $response->letter->recipient_name }}</strong>
                        <small>{{ Str::limit($response->message ?: ucfirst($response->response_value), 58) }}</small>
                    </span>
                    <span class="dashboard-item-side"><small>{{ $response->submitted_at->diffForHumans() }}</small></span>
                </a>
            @empty
                <div class="dashboard-empty"><i class="bi bi-inbox"></i><p>New private responses will appear here.</p></div>
            @endforelse
        </div>
    </section>
</div>

<section class="dashboard-cta">
    <div><span class="dashboard-cta-icon"><i class="bi bi-envelope-paper-heart"></i></span><div><h2>Ready to make someone’s day?</h2><p>Create a private message and share only its secure link.</p></div></div>
    <a href="{{ route('admin.letters.create') }}" class="btn btn-dearyou"><i class="bi bi-envelope-plus"></i> Create a letter</a>
</section>
@endsection
