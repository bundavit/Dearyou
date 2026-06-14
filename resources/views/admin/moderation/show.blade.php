@extends('layouts.app')
@section('title', 'Moderate '.$letter->title.' - DearYou')
@section('content')
<a class="btn btn-link admin-back-link" href="{{ route('admin.moderation.index') }}"><i class="bi bi-arrow-left"></i> Letter moderation</a>
<div class="admin-page-header">
    <div>
        <p class="eyebrow">LETTER METADATA</p>
        <h1>{{ $letter->title }}</h1>
        <p class="dashboard-subtitle">Created by <a href="{{ route('admin.users.show', $letter->user) }}">{{ $letter->user->name }}</a> on {{ $letter->created_at->format('F j, Y') }}.</p>
    </div>
    <span class="badge fs-6 text-bg-{{ $letter->trashed() || $letter->moderation_disabled_at ? 'danger' : 'secondary' }}">
        {{ $letter->trashed() ? 'deleted' : ($letter->moderation_disabled_at ? 'moderated' : $letter->status) }}
    </span>
</div>

<div class="dashboard-stats">
    <div class="stat-card dashboard-stat"><span>Category</span><strong>{{ ucfirst($letter->category) }}</strong></div>
    <div class="stat-card dashboard-stat"><span>Link opens</span><strong>{{ $letter->open_count }}</strong></div>
    <div class="stat-card dashboard-stat"><span>Replies</span><strong>{{ $letter->responses_count }}</strong></div>
    <div class="stat-card dashboard-stat"><span>Expires</span><strong class="fs-6">{{ $letter->expires_at?->format('M j, Y g:i A') ?? 'Not set' }}</strong></div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-8">
        <section class="form-card">
            <h2>Private content</h2>
            @if($contentRevealed)
                <div class="alert alert-warning"><i class="bi bi-eye"></i> This one-time view was recorded in the audit log.</div>
                <dl class="moderation-content">
                    <dt>Recipient</dt><dd>{{ $letter->recipientLabel() }}</dd>
                    <dt>Sender</dt><dd>{{ $letter->senderLabel() }}</dd>
                    <dt>Message</dt><dd class="white-space-pre-line">{{ $letter->body }}</dd>
                </dl>
            @else
                <p class="platform-privacy-note"><i class="bi bi-shield-lock"></i> Message text and personal names are hidden. Recipient response content is never available to platform administrators.</p>
                <form method="post" action="{{ route('admin.moderation.reveal', $letter->id) }}">
                    @csrf
                    <label class="form-label" for="reveal_reason">Reason for revealing content</label>
                    <textarea class="form-control" id="reveal_reason" name="reason" rows="3" required minlength="5" placeholder="For example: investigating a reported safety concern"></textarea>
                    @error('reason')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
                    <button class="btn btn-outline-danger mt-3"><i class="bi bi-eye"></i> Reveal once and record action</button>
                </form>
            @endif
        </section>
    </div>

    <div class="col-xl-4">
        <section class="form-card mb-4">
            <h2>Public access</h2>
            @if($letter->moderation_disabled_at)
                <p>The moderation block is active. Re-enabling does not automatically republish the creator's link.</p>
                <form method="post" action="{{ route('admin.moderation.enable', $letter->id) }}">@csrf @method('patch')
                    <textarea class="form-control mb-2" name="reason" required minlength="5" placeholder="Reason"></textarea>
                    <button class="btn btn-success w-100"><i class="bi bi-shield-check"></i> Remove moderation block</button>
                </form>
            @else
                <p>Immediately make the public letter unavailable.</p>
                <form method="post" action="{{ route('admin.moderation.disable', $letter->id) }}">@csrf @method('patch')
                    <textarea class="form-control mb-2" name="reason" required minlength="5" placeholder="Reason"></textarea>
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-slash-circle"></i> Disable public access</button>
                </form>
            @endif
        </section>

        <section class="form-card mb-4">
            <h2>Override expiration</h2>
            <form method="post" action="{{ route('admin.moderation.expiry', $letter->id) }}">@csrf @method('patch')
                <input class="form-control mb-2" type="datetime-local" name="expires_at" required>
                <textarea class="form-control mb-2" name="reason" required minlength="5" placeholder="Reason"></textarea>
                <button class="btn btn-outline-secondary w-100"><i class="bi bi-clock-history"></i> Update expiration</button>
            </form>
        </section>

        <section class="form-card">
            <h2>{{ $letter->trashed() ? 'Restore letter' : 'Moderated deletion' }}</h2>
            @if($letter->trashed())
                <form method="post" action="{{ route('admin.moderation.restore', $letter->id) }}">@csrf @method('patch')
                    <textarea class="form-control mb-2" name="reason" required minlength="5" placeholder="Reason"></textarea>
                    <button class="btn btn-outline-success w-100"><i class="bi bi-arrow-counterclockwise"></i> Restore letter</button>
                </form>
            @else
                <p>Soft deletion preserves the record and media for restoration.</p>
                <form method="post" action="{{ route('admin.moderation.destroy', $letter->id) }}">@csrf @method('delete')
                    <textarea class="form-control mb-2" name="reason" required minlength="5" placeholder="Reason"></textarea>
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-trash"></i> Soft-delete letter</button>
                </form>
            @endif
        </section>
    </div>
</div>
@endsection
