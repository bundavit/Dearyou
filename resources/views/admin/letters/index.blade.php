@extends(auth()->user()->isAdmin() ? 'layouts.app' : 'layouts.creator')
@section('title','My Letters | DearYou')
@section('content')
<div class="admin-page-header">
    <div><p class="eyebrow">LIBRARY</p><h1 class="mb-0">Letters</h1></div>
    <a href="{{ route(\App\Support\CreatorRoute::name('letters.create')) }}" class="btn btn-dearyou"><i class="bi bi-plus-lg"></i> New letter</a>
</div>
<form method="get" class="filter-card mb-4" data-auto-filter>
    <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search title or name" aria-label="Search letters" data-auto-filter-search>
    <select class="form-select" name="status" aria-label="Filter by status" data-auto-filter-change><option value="">All statuses</option>@foreach(['draft','published','unpublished'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
    <select class="form-select" name="category" aria-label="Filter by occasion" data-auto-filter-change><option value="">All occasions</option>@foreach(\App\Support\PlatformSettings::CATEGORY_OPTIONS as $category => $label)<option value="{{ $category }}" @selected(request('category')===$category)>{{ $label }}</option>@endforeach</select>
    <button class="btn btn-outline-secondary auto-filter-submit"><i class="bi bi-funnel"></i> Filter</button>
    @if(request()->hasAny(['search','status','category']))<a class="btn btn-link" href="{{ route(\App\Support\CreatorRoute::name('letters.index')) }}"><i class="bi bi-x-lg"></i> Clear</a>@endif
</form>
<div class="row g-3">
@forelse($letters as $letter)
<div class="col-md-6 col-xl-4"><article class="letter-card letter-card-clickable h-100">
    <a class="letter-card-link" href="{{ route(\App\Support\CreatorRoute::name('letters.show'),$letter) }}" aria-label="View {{ $letter->title }}"></a>
    @php($linkState = $letter->linkState())
    <div class="d-flex justify-content-between"><span class="category">{{ ucfirst($letter->category) }}</span><span class="link-state link-state-{{ $linkState }}">{{ ucfirst($linkState) }}</span></div>
    <h2>{{ $letter->title }}</h2><p>For {{ $letter->recipientLabel() }}, from {{ $letter->senderLabel() }}</p>
    @if($letter->media_cleaned_at)<p class="media-cleaned-note"><i class="bi bi-cloud-check"></i> Expired media cleaned {{ $letter->media_cleaned_at->diffForHumans() }}. Letter text and responses remain.</p>@endif
    <div class="letter-card-footer">
        <div class="letter-meta"><span title="Total link opens"><i class="bi bi-eye"></i> {{ $letter->open_count }}</span><span><i class="bi bi-chat-heart"></i> {{ $letter->responses()->count() }}</span>@if($linkState === 'active' && $letter->expires_at)<span><i class="bi bi-clock"></i> <span data-link-countdown="{{ $letter->expires_at->toIso8601String() }}">{{ $letter->expires_at->diffForHumans() }}</span></span>@elseif($linkState === 'expired')<span><i class="bi bi-hourglass-bottom"></i> Link expired</span>@endif</div>
        <div class="letter-card-actions">
            <a href="{{ route(\App\Support\CreatorRoute::name('letters.show'),$letter) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
            <a href="{{ route(\App\Support\CreatorRoute::name('letters.edit'),$letter) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i> Edit</a>
            <form method="post" action="{{ route(\App\Support\CreatorRoute::name('letters.destroy'), $letter) }}" onsubmit="return confirm('Permanently delete this letter and all of its responses and memories?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Delete {{ $letter->title }}"><i class="bi bi-trash"></i> Delete</button>
            </form>
        </div>
    </div>
</article></div>
@empty
<div class="col-12"><div class="empty-card"><h2>No matching letters</h2><p>Try clearing the filters or create a new letter.</p><a href="{{ route(\App\Support\CreatorRoute::name('letters.create')) }}" class="btn btn-dearyou"><i class="bi bi-envelope-plus"></i> Create a letter</a></div></div>
@endforelse
</div>
<div class="mt-4">{{ $letters->links() }}</div>
@endsection
