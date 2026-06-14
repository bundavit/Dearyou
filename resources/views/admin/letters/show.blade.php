@extends(auth()->user()->isAdmin() ? 'layouts.app' : 'layouts.creator')
@section('title', $letter->title.' | DearYou')
@section('content')
<a class="btn btn-link admin-back-link" href="{{ route(\App\Support\CreatorRoute::name('letters.index')) }}"><i class="bi bi-arrow-left"></i> Back to letters</a>

<div class="admin-page-header">
    <div>
        <p class="eyebrow">{{ strtoupper($letter->category) }}</p>
        <h1>{{ $letter->title }}</h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="{{ route(\App\Support\CreatorRoute::name('letters.preview'), $letter) }}"><i class="bi bi-eye"></i> Preview</a>
        <a class="btn btn-dearyou" href="{{ route(\App\Support\CreatorRoute::name('letters.edit'), $letter) }}"><i class="bi bi-pencil-square"></i> Edit letter</a>
        <form method="post" action="{{ route(\App\Support\CreatorRoute::name('letters.destroy'), $letter) }}" onsubmit="return confirm('Permanently delete this letter and all of its responses and memories?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash"></i> Delete letter</button>
        </form>
    </div>
</div>

<div class="letter-detail-grid">
    <article class="form-card letter-detail-message">
        @if($letter->media_cleaned_at)
            <div class="media-cleaned-note mb-3"><i class="bi bi-cloud-check"></i> Media was removed after this letter expired to reduce storage. The message and responses were preserved.</div>
        @endif
        <div class="letter-detail-heading">
            <div>
                @php($linkState = $letter->linkState())
                <span class="link-state link-state-{{ $linkState }}">{{ ucfirst($linkState) }}</span>
                <p class="mt-3 mb-1 text-secondary">For</p>
                <h2>{{ $letter->recipientLabel() }}</h2>
            </div>
            <div class="letter-detail-theme" style="--detail-primary: {{ $letter->primary_color }}; --detail-paper: {{ $letter->secondary_color }}">
                <span title="Primary color"></span><span title="Paper color"></span>
            </div>
        </div>

        @if($letter->image_path)
            <figure class="letter-detail-image">
                @if(\App\Models\Letter::isVideoMediaPath($letter->image_path))<video src="{{ Storage::url($letter->image_path) }}" preload="metadata" muted loop playsinline data-autoplay-when-visible aria-label="{{ $letter->image_alt ?: 'Letter video' }}"></video>@else<img src="{{ Storage::url($letter->image_path) }}" alt="{{ $letter->image_alt ?: 'Letter image' }}" decoding="async">@endif
                @if($letter->image_alt)<figcaption>{{ $letter->image_alt }}</figcaption>@endif
            </figure>
        @endif

        <div class="letter-detail-body">{{ $letter->body }}</div>
        <p class="letter-detail-signoff">From,<br><strong>{{ $letter->senderLabel() }}</strong></p>
    </article>

    <aside class="letter-detail-sidebar">
        <section class="form-card">
            <h2 class="h5">Letter details</h2>
            <dl class="letter-detail-list">
                <div><dt>Occasion</dt><dd>{{ ucfirst($letter->category) }}</dd></div>
                <div><dt>Theme</dt><dd>{{ ucfirst($letter->theme) }}</dd></div>
                <div><dt>Font</dt><dd>{{ ucfirst($letter->font_style ?: 'classic') }}</dd></div>
                <div><dt>Envelope</dt><dd>{{ ucfirst($letter->envelope_style ?: 'classic') }}</dd></div>
                <div><dt>Seal</dt><dd>{{ ucfirst($letter->seal_style ?: 'round') }}</dd></div>
                <div><dt>Decorations</dt><dd>{{ ucfirst($letter->decoration_type) }}</dd></div>
                <div><dt>Link opens</dt><dd>{{ number_format($letter->open_count) }}</dd></div>
                <div><dt>Responses</dt><dd>{{ $letter->responses_count }}</dd></div>
                <div><dt>First opened</dt><dd>{{ $letter->opened_at?->format('M j, Y g:i A') ?? 'Not opened yet' }}</dd></div>
                <div><dt>Created</dt><dd>{{ $letter->created_at->format('M j, Y') }}</dd></div>
                <div><dt>Link duration</dt><dd>{{ $letter->expiryDurationLabel() }}</dd></div>
                <div><dt>Expires</dt><dd>@if($letter->linkState() === 'active' && $letter->expires_at)<span data-link-countdown="{{ $letter->expires_at->toIso8601String() }}">{{ $letter->expires_at->diffForHumans() }}</span><br><small>{{ $letter->expires_at->format('M j, Y g:i A') }}</small>@elseif($letter->linkState() === 'expired')Expired {{ $letter->expires_at?->diffForHumans() }}@else Not currently active @endif</dd></div>
            </dl>
        </section>

        <section class="form-card">
            <h2 class="h5">Response settings</h2>
            <dl class="letter-detail-list">
                <div><dt>Allowed</dt><dd>{{ $letter->allow_response ? 'Yes' : 'No' }}</dd></div>
                <div><dt>Mode</dt><dd>{{ ucfirst(str_replace('_', ' ', $letter->response_mode)) }}</dd></div>
                @if($letter->question_text)<div><dt>Question</dt><dd>{{ $letter->question_text }}</dd></div>@endif
            </dl>
        </section>

        <section class="form-card">
            <h2 class="h5">Sharing</h2>
            @if($letter->link)
                <p class="mb-2">@if($letter->linkState() === 'active')The private link is active for <strong data-link-countdown="{{ $letter->expires_at->toIso8601String() }}">{{ $letter->expires_at->diffForHumans() }}</strong>.@elseif($letter->linkState() === 'expired')This private link has expired. Regenerate it to share again.@else This private link is disabled.@endif</p>
                <div class="input-group">
                    <input id="detail-share-link" readonly class="form-control" value="{{ route('letters.public', $letter->link->token) }}">
                    <button class="btn btn-outline-secondary" type="button" data-copy="#detail-share-link"><i class="bi bi-copy"></i> Copy</button>
                </div>
            @else
                <p class="text-secondary mb-0">Publish this letter to create its private link.</p>
            @endif
        </section>
    </aside>
</div>

@if($letter->memories->isNotEmpty())
<section class="form-card mt-4">
    <div class="d-flex justify-content-between align-items-center gap-3">
        <div><p class="eyebrow">TIMELINE</p><h2 class="h4 mb-0">Memories</h2></div>
        <span class="badge text-bg-light">{{ $letter->memories->count() }}</span>
    </div>
    <div class="letter-detail-memories">
        @foreach($letter->memories as $memory)
            <article class="memory-card">
                @if($memory->images->isNotEmpty())<div class="memory-gallery memory-gallery-{{ min($memory->images->count(), 4) }}">@foreach($memory->images as $image)@if(\App\Models\Letter::isVideoMediaPath($image->image_path))<video src="{{ Storage::url($image->image_path) }}" preload="metadata" muted loop playsinline data-autoplay-when-visible aria-label="{{ $memory->title }} memory {{ $loop->iteration }}"></video>@else<img src="{{ Storage::url($image->image_path) }}" alt="{{ $memory->title }} memory {{ $loop->iteration }}" loading="lazy" decoding="async">@endif @endforeach</div>@endif
                @if($memory->memory_date)<time datetime="{{ $memory->memory_date->format('Y-m-d') }}">{{ $memory->memory_date->format('F j, Y') }}</time>@endif
                <h3>{{ $memory->title }}</h3>
                @if($memory->caption)<p>{{ $memory->caption }}</p>@endif
            </article>
        @endforeach
    </div>
</section>
@endif
@endsection
