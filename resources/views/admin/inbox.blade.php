@extends(auth()->user()->isAdmin() ? 'layouts.app' : 'layouts.creator')
@section('title','Inbox | DearYou')
@section('content')
<div class="admin-page-header">
    <div><p class="eyebrow">PRIVATE</p><h1 class="mb-0">Response inbox</h1><p class="text-secondary mb-0">{{ $unreadCount }} unread response(s)</p></div>
    <form method="get" class="inbox-filter" data-auto-filter>
        <select class="form-select" name="status" aria-label="Filter by read status" data-auto-filter-change>
            <option value="">All responses</option>
            <option value="unread" @selected(request('status') === 'unread')>Unread</option>
            <option value="read" @selected(request('status') === 'read')>Read</option>
        </select>
        <select class="form-select" name="letter" aria-label="Filter by letter" data-auto-filter-change>
            <option value="">All letters</option>
            @foreach($letters as $letter)<option value="{{ $letter->id }}" @selected((string) request('letter') === (string) $letter->id)>{{ $letter->title }}</option>@endforeach
        </select>
        <button class="btn btn-outline-secondary auto-filter-submit"><i class="bi bi-funnel"></i> Filter</button>
    </form>
</div>

@if($responses->isNotEmpty())
<form method="post" action="{{ route(\App\Support\CreatorRoute::name('inbox.bulk')) }}">
    @csrf
    <div class="bulk-bar mb-3">
        <label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-select-all> Select all</label>
        <select class="form-select form-select-sm" name="action" required>
            <option value="">Bulk action</option><option value="read">Mark read</option><option value="unread">Mark unread</option><option value="delete">Delete</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2"></i> Apply</button>
    </div>
    @foreach($responses as $response)
        <article class="response-card {{ $response->read_at ? '' : 'is-unread' }}">
            <input class="form-check-input mt-1" type="checkbox" name="response_ids[]" value="{{ $response->id }}" aria-label="Select response from {{ $response->letter->recipientLabel() }}">
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @unless($response->read_at)<span class="unread-dot" aria-label="Unread"></span>@endunless
                    <strong>{{ $response->letter->recipientLabel() }}</strong>
                    <span>replied to</span>
                    <a href="{{ route(\App\Support\CreatorRoute::name('letters.edit'),$response->letter) }}">{{ $response->letter->title }}</a>
                </div>
                <p class="response-value">{{ ucfirst($response->response_value) }}</p>
                @if($response->message)<p class="response-preview">{{ Str::limit($response->message, 140) }}</p>@endif
                <small>{{ $response->submitted_at->diffForHumans() }}</small>
            </div>
            <a class="btn btn-sm {{ $response->read_at ? 'btn-outline-secondary' : 'btn-dearyou' }}" href="{{ route(\App\Support\CreatorRoute::name('responses.show'),$response) }}"><i class="bi bi-eye"></i> View</a>
        </article>
    @endforeach
</form>
@else
<div class="empty-card"><h2>No matching responses</h2><p>New private replies will appear here.</p></div>
@endif
<div class="mt-4">{{ $responses->links() }}</div>
@endsection
