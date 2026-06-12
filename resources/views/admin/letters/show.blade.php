@extends('layouts.app')
@section('title', $letter->title.' | DearYou Admin')
@section('content')
<a class="btn btn-link admin-back-link" href="{{ route('admin.letters.index') }}"><i class="bi bi-arrow-left"></i> Back to letters</a>

<div class="admin-page-header">
    <div>
        <p class="eyebrow">{{ strtoupper($letter->category) }}</p>
        <h1>{{ $letter->title }}</h1>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('admin.letters.preview', $letter) }}"><i class="bi bi-eye"></i> Preview</a>
        <a class="btn btn-dearyou" href="{{ route('admin.letters.edit', $letter) }}"><i class="bi bi-pencil-square"></i> Edit letter</a>
    </div>
</div>

<div class="letter-detail-grid">
    <article class="form-card letter-detail-message">
        <div class="letter-detail-heading">
            <div>
                <span class="badge text-bg-{{ $letter->status === 'published' ? 'success' : ($letter->status === 'draft' ? 'secondary' : 'warning') }}">{{ ucfirst($letter->status) }}</span>
                <p class="mt-3 mb-1 text-secondary">For</p>
                <h2>{{ $letter->recipientLabel() }}</h2>
            </div>
            <div class="letter-detail-theme" style="--detail-primary: {{ $letter->primary_color }}; --detail-paper: {{ $letter->secondary_color }}">
                <span title="Primary color"></span><span title="Paper color"></span>
            </div>
        </div>

        @if($letter->image_path)
            <figure class="letter-detail-image">
                @if(str_ends_with(strtolower($letter->image_path), '.mp4'))<video src="{{ Storage::url($letter->image_path) }}" autoplay muted loop playsinline aria-label="{{ $letter->image_alt ?: 'Letter video' }}"></video>@else<img src="{{ Storage::url($letter->image_path) }}" alt="{{ $letter->image_alt ?: 'Letter image' }}">@endif
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
                <div><dt>Decorations</dt><dd>{{ ucfirst($letter->decoration_type) }}</dd></div>
                <div><dt>Responses</dt><dd>{{ $letter->responses_count }}</dd></div>
                <div><dt>Created</dt><dd>{{ $letter->created_at->format('M j, Y') }}</dd></div>
                <div><dt>Expires</dt><dd>{{ $letter->expires_at?->format('M j, Y g:i A') ?? 'Never' }}</dd></div>
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
                <p class="mb-2">{{ $letter->link->is_active ? 'The private link is active.' : 'The private link is disabled.' }}</p>
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
                @if($memory->images->isNotEmpty())<div class="memory-gallery memory-gallery-{{ min($memory->images->count(), 4) }}">@foreach($memory->images as $image)@if(str_ends_with(strtolower($image->image_path), '.mp4'))<video src="{{ Storage::url($image->image_path) }}" autoplay muted loop playsinline aria-label="{{ $memory->title }} memory {{ $loop->iteration }}"></video>@else<img src="{{ Storage::url($image->image_path) }}" alt="{{ $memory->title }} memory {{ $loop->iteration }}">@endif @endforeach</div>@endif
                @if($memory->memory_date)<time datetime="{{ $memory->memory_date->format('Y-m-d') }}">{{ $memory->memory_date->format('F j, Y') }}</time>@endif
                <h3>{{ $memory->title }}</h3>
                @if($memory->caption)<p>{{ $memory->caption }}</p>@endif
            </article>
        @endforeach
    </div>
</section>
@endif
@endsection
