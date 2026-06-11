@extends('layouts.app')
@section('title','Account | DearYou')
@section('content')
<div class="admin-page-header">
    <div><p class="eyebrow">SECURITY</p><h1 class="mb-0">Account settings</h1></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <form method="post" action="{{ route('admin.account.profile') }}" class="form-card h-100">
            @csrf @method('PUT')
            <h2 class="h4">Profile</h2>
            <p class="text-secondary">Update the details used for your private admin account.</p>
            <label class="form-label">Name</label>
            <input class="form-control mb-3" name="name" value="{{ old('name', auth()->user()->name) }}" required>
            <label class="form-label">Email</label>
            <input class="form-control mb-3" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required>
            <label class="form-label">Current password</label>
            <input class="form-control" type="password" name="current_password" required autocomplete="current-password">
            @if($errors->hasAny(['name','email','current_password']))<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
            <button class="btn btn-dearyou mt-4"><i class="bi bi-person-check"></i> Update profile</button>
        </form>
    </div>
    <div class="col-lg-6">
        <form method="post" action="{{ route('admin.account.password') }}" class="form-card h-100">
            @csrf @method('PUT')
            <h2 class="h4">Change password</h2>
            <p class="text-secondary">Use at least 10 characters with uppercase, lowercase, and a number.</p>
            <label class="form-label">Current password</label>
            <input class="form-control mb-3" type="password" name="current_password" required autocomplete="current-password">
            <label class="form-label">New password</label>
            <input class="form-control mb-3" type="password" name="password" required autocomplete="new-password">
            <label class="form-label">Confirm new password</label>
            <input class="form-control" type="password" name="password_confirmation" required autocomplete="new-password">
            @if($errors->hasAny(['password','password_confirmation']))<div class="alert alert-danger mt-3">{{ $errors->first() }}</div>@endif
            <button class="btn btn-dearyou mt-4"><i class="bi bi-shield-lock"></i> Change password</button>
        </form>
    </div>
</div>
<details class="form-card advanced-card mt-4" @if(session('new_api_token')) open @endif>
    <summary>
        <span><strong>Advanced: API access</strong><small>Only needed for Postman or another external application.</small></span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </summary>
    <div class="advanced-card-content">
    <p class="text-secondary">API tokens are secret keys that let another application access your DearYou account. You do not need one for normal website use.</p>
    @if(session('new_api_token'))<div class="alert alert-warning"><strong>Copy this token now:</strong><div class="input-group mt-2"><input id="new-api-token" class="form-control font-monospace" readonly value="{{ session('new_api_token') }}"><button class="btn btn-outline-dark" type="button" data-copy="#new-api-token"><i class="bi bi-copy"></i> Copy</button></div></div>@endif
    <form method="post" action="{{ route('admin.account.tokens.store') }}" class="row g-2 align-items-end">@csrf
        <div class="col-md-6"><label class="form-label">Token name</label><input class="form-control" name="token_name" placeholder="Postman on my laptop" required></div>
        <div class="col-md-3"><label class="form-label">Access</label><select class="form-select" name="access"><option value="read">Read only</option><option value="write">Read and write</option></select></div>
        <div class="col-md-3"><button class="btn btn-dearyou w-100"><i class="bi bi-key"></i> Create token</button></div>
    </form>
    <div class="table-responsive mt-4"><table class="table align-middle"><thead><tr><th>Name</th><th>Abilities</th><th>Last used</th><th></th></tr></thead><tbody>
    @forelse(auth()->user()->tokens()->latest()->get() as $token)<tr><td>{{ $token->name }}</td><td><small>{{ implode(', ', $token->abilities) }}</small></td><td>{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td><td class="text-end"><form method="post" action="{{ route('admin.account.tokens.destroy',$token) }}" onsubmit="return confirm('Revoke this token?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-circle"></i> Revoke</button></form></td></tr>@empty<tr><td colspan="4" class="text-secondary">No API tokens yet.</td></tr>@endforelse
    </tbody></table></div>
    </div>
</details>
@endsection
