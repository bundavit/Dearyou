@extends('layouts.app')
@section('title', 'Review Feedback - DearYou')
@section('content')
<a class="btn btn-link admin-back-link" href="{{ route('admin.feedback.index') }}"><i class="bi bi-arrow-left"></i> Back to feedback</a>
<div class="admin-page-header">
    <div><p class="eyebrow">{{ strtoupper(\App\Models\Feedback::CATEGORIES[$feedback->category]) }}</p><h1>Feedback details</h1><p class="dashboard-subtitle">Received {{ $feedback->created_at->format('F j, Y g:i A') }}</p></div>
    @if($feedback->rating)<span class="feedback-rating">{{ $feedback->rating }} / 5 <i class="bi bi-star-fill"></i></span>@endif
</div>
<section class="form-card response-detail">
    <dl class="letter-detail-list">
        <div><dt>From</dt><dd>{{ $feedback->user?->name ?? 'Guest visitor' }}</dd></div>
        <div><dt>Email</dt><dd>{{ $feedback->email ?: 'Not provided' }}</dd></div>
        <div><dt>Source page</dt><dd>{{ $feedback->source_page ?: 'Unknown' }}</dd></div>
    </dl>
    <div class="response-message">{{ $feedback->message }}</div>
    @if($feedback->status === 'spam' || $feedback->spam_reasons)
        <div class="alert alert-warning" role="note">
            <h2 class="h6">Spam review</h2>
            <p class="mb-2">{{ $feedback->spam_reasons ? 'Automatic checks flagged this feedback for review.' : 'This feedback was marked as potential spam.' }} Review it before deciding; legitimate feedback can be restored to New.</p>
            @if($feedback->spam_reasons)
                <ul class="mb-0">@foreach($feedback->spam_reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul>
            @endif
        </div>
    @endif
    <div class="d-flex flex-wrap gap-2">
        <form method="post" action="{{ route('admin.feedback.update', $feedback) }}" class="d-flex gap-2">@csrf @method('PATCH')
            <select class="form-select" name="status" aria-label="Feedback status">@foreach(\App\Models\Feedback::STATUSES as $value => $label)<option value="{{ $value }}" @selected($feedback->status === $value)>{{ $label }}</option>@endforeach</select>
            <button class="btn btn-dearyou"><i class="bi bi-check2"></i> Update</button>
        </form>
        <form method="post" action="{{ route('admin.feedback.update', $feedback) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $feedback->status === 'spam' ? 'new' : 'spam' }}">
            <button class="btn btn-outline-secondary">{{ $feedback->status === 'spam' ? 'Restore to New' : 'Mark as potential spam' }}</button>
        </form>
        <form method="post" action="{{ route('admin.feedback.destroy', $feedback) }}" onsubmit="return confirm('Delete this feedback permanently?')">@csrf @method('DELETE')
            <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
        </form>
    </div>
</section>
@endsection
