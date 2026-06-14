@extends(auth()->user()->isAdmin() ? 'layouts.app' : 'layouts.creator')
@section('title','Response | DearYou')
@section('content')
<a class="btn btn-link px-0 mb-3" href="{{ route(\App\Support\CreatorRoute::name('inbox')) }}"><i class="bi bi-arrow-left"></i> Back to inbox</a>
<article class="form-card response-detail">
    <div class="d-flex flex-wrap justify-content-between gap-3">
        <div><p class="eyebrow">{{ strtoupper($response->letter->category) }}</p><h1>Response from {{ $response->letter->recipientLabel() }}</h1><p>For <a href="{{ route(\App\Support\CreatorRoute::name('letters.edit'),$response->letter) }}">{{ $response->letter->title }}</a></p></div>
        <span class="response-value align-self-start">{{ ucfirst($response->response_value) }}</span>
    </div>
    @if($response->message)<blockquote class="response-message">{{ $response->message }}</blockquote>@else<p class="text-secondary">No additional message was included.</p>@endif
    <p class="text-secondary">Received {{ $response->submitted_at->format('F j, Y \a\t g:i A') }}</p>
    <div class="d-flex gap-2">
        <form method="post" action="{{ route(\App\Support\CreatorRoute::name('responses.unread'),$response) }}">@csrf @method('PATCH')<button class="btn btn-outline-secondary"><i class="bi bi-envelope"></i> Mark unread</button></form>
        <form method="post" action="{{ route(\App\Support\CreatorRoute::name('responses.destroy'),$response) }}" onsubmit="return confirm('Delete this response?')">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button></form>
    </div>
</article>
@endsection
