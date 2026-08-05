@extends('layouts.app')
@section('title', 'Deployment Health - DearYou')
@section('content')
<div class="admin-page-header">
    <div>
        <p class="eyebrow">DEPLOYMENT HEALTH</p>
        <h1>Email and worker health</h1>
        <p class="dashboard-subtitle">Check the production pieces that usually matter after a deploy: URL, mail, queues, storage, and scheduled jobs.</p>
    </div>
    <a href="{{ route('admin.platform') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Back to platform</a>
</div>

<div class="health-grid">
    <div class="stat-card dashboard-stat">
        <span class="dashboard-stat-icon"><i class="bi bi-check2-circle"></i></span>
        <span>Healthy</span>
        <strong>{{ $summary['ok'] }}</strong>
    </div>
    <div class="stat-card dashboard-stat">
        <span class="dashboard-stat-icon"><i class="bi bi-exclamation-triangle"></i></span>
        <span>Warnings</span>
        <strong>{{ $summary['warning'] }}</strong>
    </div>
    <div class="stat-card dashboard-stat">
        <span class="dashboard-stat-icon"><i class="bi bi-x-circle"></i></span>
        <span>Needs attention</span>
        <strong>{{ $summary['bad'] }}</strong>
    </div>
</div>

<section class="dashboard-panel mt-4">
    <div class="dashboard-panel-header">
        <div>
            <p class="eyebrow">CHECKLIST</p>
            <h2>Current app status</h2>
        </div>
    </div>

    <div class="health-checks">
        @foreach($checks as $check)
            @php($icon = ['ok' => 'bi-check2-circle', 'warning' => 'bi-exclamation-triangle', 'bad' => 'bi-x-circle'][$check['state']])
            <div class="health-check-row">
                <span class="health-check-icon is-{{ $check['state'] }}"><i class="bi {{ $icon }}"></i></span>
                <span class="health-check-copy">
                    <strong>{{ $check['label'] }}</strong>
                    <small>{{ $check['detail'] }}</small>
                </span>
                <span class="health-status is-{{ $check['state'] }}">{{ ucfirst($check['state']) }}</span>
            </div>
        @endforeach
    </div>
</section>

<section class="dashboard-panel mt-3">
    <div class="dashboard-panel-header">
        <div>
            <p class="eyebrow">SERVER CONFIRMATION</p>
            <h2>DigitalOcean commands</h2>
        </div>
    </div>
    <p class="text-secondary">The web page can read Laravel config, but systemd services still need a server-side check.</p>
    <pre class="health-command"><code>sudo systemctl status dearyou-worker
sudo systemctl status dearyou-scheduler.timer
sudo -u deploy php artisan dearyou:check-production --strict</code></pre>
</section>
@endsection
