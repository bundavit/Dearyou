@extends('layouts.app')
@section('title', $managedUser->name.' - DearYou User')
@section('content')
<a class="btn btn-link admin-back-link" href="{{ route('admin.users.index') }}"><i class="bi bi-arrow-left"></i> All accounts</a>
<div class="admin-page-header">
    <div>
        <p class="eyebrow">ACCOUNT DETAILS</p>
        <h1>{{ $managedUser->name }}</h1>
        <p class="dashboard-subtitle">{{ $managedUser->email }} &middot; Joined {{ $managedUser->created_at->format('F j, Y') }}</p>
    </div>
    <div class="platform-user-badges">
        <span class="badge text-bg-{{ $managedUser->isAdmin() ? 'primary' : 'secondary' }}">{{ $managedUser->role }}</span>
        @if($managedUser->trashed())
            <span class="badge text-bg-dark">deleted</span>
        @elseif($managedUser->disabled_at)
            <span class="badge text-bg-danger">disabled</span>
        @else
            <span class="badge text-bg-success">active</span>
        @endif
    </div>
</div>

@php($statCards = [
    'letters' => ['Letters', 'bi-envelope-paper-heart'],
    'published' => ['Published', 'bi-send-check'],
    'responses' => ['Responses', 'bi-chat-heart'],
    'opens' => ['Link opens', 'bi-eye'],
    'storage' => ['Media storage', 'bi-cloud'],
])
@php($stats['storage'] = $storageUsage['used_label'])
<div class="dashboard-stats">
    @foreach($statCards as $key => [$label, $icon])
        <div class="stat-card dashboard-stat"><span class="dashboard-stat-icon"><i class="bi {{ $icon }}"></i></span><span>{{ $label }}</span><strong>{{ $stats[$key] }}</strong></div>
    @endforeach
</div>

<section class="form-card mt-4">
    <div class="d-flex flex-wrap justify-content-between gap-2">
        <div><p class="eyebrow">STORAGE</p><h2 class="h5 mb-1">{{ $storageUsage['used_label'] }} of {{ $storageUsage['limit_label'] }} used</h2></div>
        <strong>{{ $storageUsage['percentage'] }}%</strong>
    </div>
    <div class="creator-storage-track mt-3" role="progressbar" aria-valuenow="{{ $storageUsage['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
        <span style="width: {{ $storageUsage['percentage'] }}%"></span>
    </div>
    @if($managedUser->storage_cleanup_due_at)
        <p class="storage-admin-warning"><i class="bi bi-exclamation-triangle"></i> Cleanup becomes eligible {{ $managedUser->storage_cleanup_due_at->diffForHumans() }}.</p>
    @endif
</section>

@if($cleanupLogs->isNotEmpty())
<section class="dashboard-panel mt-4">
    <div class="dashboard-panel-header"><div><p class="eyebrow">STORAGE HISTORY</p><h2>Automatic cleanup</h2></div></div>
    <div class="dashboard-list">
        @foreach($cleanupLogs as $log)
            <div class="dashboard-list-item">
                <span class="dashboard-item-icon"><i class="bi bi-images"></i></span>
                <span class="dashboard-item-copy"><strong>{{ $log->letter_title }}</strong><small>{{ $log->files_removed }} media file(s) removed</small></span>
                <span class="dashboard-item-side"><strong>{{ app(\App\Support\CreatorStorage::class)->formatBytes($log->bytes_freed) }}</strong><small>{{ $log->created_at->diffForHumans() }}</small></span>
            </div>
        @endforeach
    </div>
</section>
@endif

<div class="platform-user-detail-grid mt-4">
    <section class="dashboard-panel">
        <div class="dashboard-panel-header"><div><p class="eyebrow">RECENT ACTIVITY</p><h2>Letter metadata</h2></div></div>
        <p class="platform-privacy-note"><i class="bi bi-shield-lock"></i> Titles and activity are shown for support. Private message content is not exposed here.</p>
        <div class="dashboard-list">
            @forelse($recentLetters as $letter)
                <a class="dashboard-list-item" href="{{ route('admin.moderation.show', $letter->id) }}">
                    <span class="dashboard-item-icon"><i class="bi bi-envelope"></i></span>
                    <span class="dashboard-item-copy"><strong>{{ $letter->title }}</strong><small>{{ ucfirst($letter->category) }} &middot; Updated {{ $letter->updated_at->diffForHumans() }}</small></span>
                    <span class="dashboard-item-side"><span class="badge text-bg-{{ $letter->status === 'published' ? 'success' : 'secondary' }}">{{ $letter->status }}</span><small>{{ $letter->open_count }} opens &middot; {{ $letter->responses_count }} replies</small></span>
                </a>
            @empty
                <div class="dashboard-empty"><i class="bi bi-envelope"></i><p>This account has no letters yet.</p></div>
            @endforelse
        </div>
    </section>

    <aside class="platform-user-controls">
        <section class="form-card">
            <h2>Role</h2>
            <p>Admins can access platform statistics and account management.</p>
            <form method="post" action="{{ route('admin.users.role', $managedUser) }}">
                @csrf @method('patch')
                <select class="form-select mb-3" name="role" @disabled(auth()->user()->is($managedUser) || $managedUser->trashed())>
                    <option value="user" @selected($managedUser->role === 'user')>User</option>
                    <option value="admin" @selected($managedUser->role === 'admin')>Admin</option>
                </select>
                @error('role')<p class="text-danger small">{{ $message }}</p>@enderror
                <button class="btn btn-outline-primary w-100" @disabled(auth()->user()->is($managedUser) || $managedUser->trashed())><i class="bi bi-shield-check"></i> Update role</button>
            </form>
        </section>

        <section class="form-card">
            <h2>Account access</h2>
            @if($managedUser->trashed())
                <p>Restore this account before changing sign-in access.</p>
                <button class="btn btn-outline-secondary w-100" disabled>Account deleted</button>
            @elseif(auth()->user()->is($managedUser))
                <p>You cannot disable your own administrator account.</p>
                <button class="btn btn-outline-secondary w-100" disabled>Current account</button>
            @elseif($managedUser->disabled_at)
                <p>Reactivating allows this person to sign in and use API tokens again.</p>
                <form method="post" action="{{ route('admin.users.status', $managedUser) }}">@csrf @method('patch')<input type="hidden" name="status" value="active"><button class="btn btn-success w-100"><i class="bi bi-person-check"></i> Reactivate account</button></form>
            @else
                <p>Disabling blocks sign-in and existing API access while preserving their letters.</p>
                <form method="post" action="{{ route('admin.users.status', $managedUser) }}" onsubmit="return confirm('Disable this account? Their letters will be preserved.')">@csrf @method('patch')<input type="hidden" name="status" value="disabled"><button class="btn btn-outline-danger w-100"><i class="bi bi-person-slash"></i> Disable account</button></form>
            @endif
        </section>

        <section class="form-card">
            <h2>{{ $managedUser->trashed() ? 'Restore account' : 'Delete account' }}</h2>
            @if(auth()->user()->is($managedUser))
                <p>Your current administrator account cannot be deleted here.</p>
                <button class="btn btn-outline-secondary w-100" disabled>Current account</button>
            @elseif($managedUser->trashed())
                <p>Restoring returns the account, letters, replies, and media. The creator can sign in again.</p>
                <form method="post" action="{{ route('admin.users.restore', $managedUser->id) }}">
                    @csrf @method('patch')
                    <textarea class="form-control mb-2" name="reason" required minlength="5" maxlength="500" placeholder="Reason for restoration"></textarea>
                    <button class="btn btn-outline-success w-100"><i class="bi bi-arrow-counterclockwise"></i> Restore account</button>
                </form>
            @else
                <p>Soft deletion blocks login and public links while preserving all account data for restoration.</p>
                <form method="post" action="{{ route('admin.users.destroy', $managedUser->id) }}" onsubmit="return confirm('Delete this account? Its data will be preserved for restoration.')">
                    @csrf @method('delete')
                    <textarea class="form-control mb-2" name="reason" required minlength="5" maxlength="500" placeholder="Reason for deletion"></textarea>
                    <button class="btn btn-outline-danger w-100"><i class="bi bi-person-x"></i> Delete account</button>
                </form>
            @endif
        </section>
    </aside>
</div>
@endsection
