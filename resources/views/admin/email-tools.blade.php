@extends('layouts.app')
@section('title', 'Email tools | DearYou')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">DELIVERY</p>
        <h1>Email tools</h1>
        <p class="dashboard-subtitle">Check verification delivery, failed queue jobs, and local test codes without digging through noisy logs.</p>
    </div>
    <a href="{{ route('admin.health') }}" class="btn btn-outline-secondary"><i class="bi bi-activity"></i> Health check</a>
</div>

<div class="email-tools-grid">
    <section class="dashboard-panel email-tool-card">
        <div class="dashboard-panel-header">
            <div><p class="eyebrow">CONFIG</p><h2>Mail delivery</h2></div>
            <span class="health-status {{ $mail['resend_key_loaded'] ? 'is-ok' : 'is-bad' }}">{{ $mail['resend_key_loaded'] ? 'Ready' : 'Missing key' }}</span>
        </div>
        <dl class="email-tool-list">
            <div><dt>Mailer</dt><dd>{{ $mail['mailer'] }}</dd></div>
            <div><dt>Queue</dt><dd>{{ $mail['queue'] }}</dd></div>
            <div><dt>From address</dt><dd>{{ $mail['from'] }}</dd></div>
            <div><dt>Feedback notice</dt><dd>{{ $mail['feedback_notify_email'] }}</dd></div>
        </dl>
        <p class="email-tool-note">For local testing, set <code>MAIL_MAILER=log</code> if Windows SSL blocks Resend.</p>
    </section>

    <section class="dashboard-panel email-tool-card">
        <div class="dashboard-panel-header">
            <div><p class="eyebrow">ACCOUNTS</p><h2>Unverified users</h2></div>
            <a href="{{ route('admin.users.index') }}">View users</a>
        </div>
        <div class="dashboard-list compact-list">
            @forelse($unverifiedUsers as $user)
                <div class="dashboard-list-item">
                    <span class="dashboard-item-icon"><i class="bi bi-person-exclamation"></i></span>
                    <span class="dashboard-item-copy">
                        <strong>{{ $user->name }}</strong>
                        <small>{{ $user->email }}</small>
                    </span>
                    <span class="dashboard-item-side email-tool-actions">
                        <form method="post" action="{{ route('admin.users.verification.send', $user) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-send"></i> Send</button>
                        </form>
                        <form method="post" action="{{ route('admin.users.verification.verify', $user) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-sm btn-outline-success"><i class="bi bi-check2-circle"></i> Verify</button>
                        </form>
                    </span>
                </div>
            @empty
                <div class="dashboard-empty"><i class="bi bi-envelope-check"></i><p>Every recent account is verified.</p></div>
            @endforelse
        </div>
    </section>

    <section class="dashboard-panel email-tool-card">
        <div class="dashboard-panel-header">
            <div><p class="eyebrow">QUEUE</p><h2>Failed email jobs</h2></div>
            <code>queue:retry all</code>
        </div>
        <div class="dashboard-list compact-list">
            @forelse($failedJobs as $job)
                <div class="dashboard-list-item">
                    <span class="dashboard-item-icon"><i class="bi bi-exclamation-triangle"></i></span>
                    <span class="dashboard-item-copy">
                        <strong>{{ $job['name'] }}</strong>
                        <small>{{ $job['error'] }}</small>
                    </span>
                    <span class="dashboard-item-side"><small>{{ $job['failed_at'] }}</small></span>
                </div>
            @empty
                <div class="dashboard-empty"><i class="bi bi-check2-circle"></i><p>No failed email jobs found.</p></div>
            @endforelse
        </div>
    </section>

    <section class="dashboard-panel email-tool-card">
        <div class="dashboard-panel-header">
            <div><p class="eyebrow">LOCAL TESTING</p><h2>Recent verification codes</h2></div>
            <code>storage/logs/dearyou-codes.log</code>
        </div>
        <div class="email-code-log">
            @forelse($recentCodes as $line)
                <code>{{ $line }}</code>
            @empty
                <p class="text-secondary mb-0">No local code log yet. Register or resend verification with local logging enabled.</p>
            @endforelse
        </div>
    </section>
</div>
@endsection
