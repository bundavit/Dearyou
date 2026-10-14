@extends('layouts.app')
@section('title', 'Email tools | DearYou')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">DELIVERY</p>
        <h1>Email tools</h1>
        <p class="dashboard-subtitle">Check the sending configuration, waiting jobs and failed notifications, then test the feedback inbox.</p>
    </div>
    <a href="{{ route('admin.health') }}" class="btn btn-outline-secondary"><i class="bi bi-activity"></i> Health check</a>
</div>

@error('email_tools')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
@error('verification')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
@error('jobs')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror
<div class="email-tools-grid">
    <section class="dashboard-panel email-tool-card">
        <div class="dashboard-panel-header">
            <div><p class="eyebrow">CONFIG</p><h2>Mail delivery</h2></div>
            <span class="health-status {{ $mail['mailer'] === 'resend' && ! $mail['resend_key_loaded'] ? 'is-bad' : '' }}">{{ $mail['mailer'] === 'log' ? 'Log only' : ($mail['mailer'] === 'resend' ? ($mail['resend_key_loaded'] ? 'Key configured' : 'Missing key') : 'Check mailer') }}</span>
        </div>
        <dl class="email-tool-list">
            <div><dt>Mailer</dt><dd>{{ $mail['mailer'] }}</dd></div>
            <div><dt>Queue</dt><dd>{{ $mail['queue'] }}</dd></div>
            <div><dt>From address</dt><dd>{{ $mail['from'] }}</dd></div>
            <div><dt>Feedback notice</dt><dd>{{ $mail['feedback_notify_email'] }}</dd></div>
        </dl>
        <p class="email-tool-note">A configured key does not establish that it is valid or that an email arrived. Verify receipt in the inbox and inspect Resend events for delivery results.</p>
        <form method="post" action="{{ route('admin.email-tools.feedback-test') }}">
            @csrf
            <button class="btn btn-dearyou"><i class="bi bi-send" aria-hidden="true"></i> Send feedback test</button>
        </form>
        <p class="email-tool-note mt-2">Uses the saved feedback recipient and the application's notification queue. Log mode writes to the private mail log and sends no inbox email. No feedback record is created.</p>
    </section>

    <section class="dashboard-panel email-tool-card">
        <div class="dashboard-panel-header"><div><p class="eyebrow">QUEUE</p><h2>Waiting for processing</h2></div></div>
        @if($pendingQueue['available'])
            <dl class="email-tool-list">
                <div><dt>Queue name</dt><dd>{{ $pendingQueue['queue'] }}</dd></div>
                <div><dt>Waiting jobs</dt><dd>{{ $pendingQueue['waiting'] }}</dd></div>
                <div><dt>Reserved jobs</dt><dd>{{ $pendingQueue['reserved'] }}</dd></div>
                <div><dt>Delayed within waiting</dt><dd>{{ $pendingQueue['delayed'] }}</dd></div>
                <div><dt>Oldest waiting job created</dt><dd>{{ $pendingQueue['oldest'] ?? 'None' }}</dd></div>
            </dl>
            <p class="email-tool-note">Counts include all jobs in this queue. Reserved jobs may be running or awaiting recovery after interruption. Refresh to see whether waiting jobs decrease.</p>
        @else
            <p class="email-tool-note">{{ $pendingQueue['note'] }}</p>
        @endif
        <p class="email-tool-note">Zero failures does not prove the worker is running. This page does not establish worker health or inbox delivery.</p>
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
            @if(count($failedJobs))
                <form method="post" action="{{ route('admin.email-tools.retry-failed') }}">
                    @csrf
                    @foreach($failedJobs as $job)<input type="hidden" name="jobs[]" value="{{ $job['id'] }}">@endforeach
                    <button class="btn btn-sm btn-dearyou"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Retry listed emails</button>
                </form>
            @endif
        </div>
        <div class="dashboard-list compact-list">
            @forelse($failedJobs as $job)
                <div class="dashboard-list-item">
                    <span class="dashboard-item-icon"><i class="bi bi-exclamation-triangle"></i></span>
                    <span class="dashboard-item-copy">
                        <strong>{{ $job['name'] }}</strong>
                        <small>{{ $job['error'] }}</small>
                        <small>Job: {{ $job['id'] }}</small>
                    </span>
                    <span class="dashboard-item-side"><small>{{ $job['failed_at'] }}</small></span>
                </div>
            @empty
                <div class="dashboard-empty"><i class="bi bi-envelope" aria-hidden="true"></i><p>{{ $failedJobsAvailable ? 'No failed email jobs found.' : 'Failed job details are unavailable for the configured storage. Check the server logs.' }}</p></div>
            @endforelse
        </div>
    </section>

    @if(app()->environment('local'))
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
    @endif
</div>
@endsection
