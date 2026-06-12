@extends('layouts.app')
@section('title','DearYou Admin Letters')
@section('content')
<div class="admin-page-header">
    <div><p class="eyebrow">LIBRARY</p><h1 class="mb-0">Letters</h1></div>
    <a href="{{ route('admin.letters.create') }}" class="btn btn-dearyou"><i class="bi bi-plus-lg"></i> New letter</a>
</div>
<form method="get" class="filter-card mb-4" data-auto-filter>
    <input class="form-control" type="search" name="search" value="{{ request('search') }}" placeholder="Search title or name" aria-label="Search letters" data-auto-filter-search>
    <select class="form-select" name="status" aria-label="Filter by status" data-auto-filter-change><option value="">All statuses</option>@foreach(['draft','published','unpublished'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select>
    <select class="form-select" name="category" aria-label="Filter by occasion" data-auto-filter-change><option value="">All occasions</option>@foreach(['confession','apology','birthday','anniversary','valentine','congratulations','thank-you','friendship','graduation','celebration','custom'] as $category)<option value="{{ $category }}" @selected(request('category')===$category)>{{ ucfirst($category) }}</option>@endforeach</select>
    <button class="btn btn-outline-secondary auto-filter-submit"><i class="bi bi-funnel"></i> Filter</button>
    @if(request()->hasAny(['search','status','category']))<a class="btn btn-link" href="{{ route('admin.letters.index') }}"><i class="bi bi-x-lg"></i> Clear</a>@endif
</form>
<div class="row g-3">
@forelse($letters as $letter)
<div class="col-md-6 col-xl-4"><article class="letter-card letter-card-clickable h-100">
    <a class="letter-card-link" href="{{ route('admin.letters.show',$letter) }}" aria-label="View {{ $letter->title }}"></a>
    <div class="d-flex justify-content-between"><span class="category">{{ ucfirst($letter->category) }}</span><span class="badge text-bg-{{ $letter->status === 'published' ? 'success' : ($letter->status === 'draft' ? 'secondary' : 'warning') }}">{{ $letter->status }}</span></div>
    <h2>{{ $letter->title }}</h2><p>For {{ $letter->recipientLabel() }}, from {{ $letter->senderLabel() }}</p>
    <div class="letter-card-footer">
        <div class="letter-meta"><span title="Total link opens"><i class="bi bi-eye"></i> {{ $letter->open_count }}</span><span><i class="bi bi-chat-heart"></i> {{ $letter->responses()->count() }}</span>@if($letter->expires_at)<span><i class="bi bi-clock"></i> {{ $letter->expires_at->isPast() ? 'Expired' : $letter->expires_at->diffForHumans() }}</span>@endif</div>
        <div class="letter-card-actions">
            <a href="{{ route('admin.letters.show',$letter) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
            <a href="{{ route('admin.letters.edit',$letter) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil-square"></i> Edit</a>
        </div>
    </div>
</article></div>
@empty
<div class="col-12"><div class="empty-card"><h2>No matching letters</h2><p>Try clearing the filters or create a new letter.</p><a href="{{ route('admin.letters.create') }}" class="btn btn-dearyou"><i class="bi bi-envelope-plus"></i> Create a letter</a></div></div>
@endforelse
</div>
<div class="mt-4">{{ $letters->links() }}</div>
@endsection
