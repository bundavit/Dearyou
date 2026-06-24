@extends('layouts.app')
@section('title', 'Platform Settings - DearYou')
@section('content')
@php
    $presetExpiryOptions = collect(\App\Support\PlatformSettings::DEFAULT_EXPIRY_OPTIONS)
        ->mapWithKeys(fn ($minutes) => [$minutes => app(\App\Support\PlatformSettings::class)->durationLabel($minutes)])
        ->all();
    $customExpiryValue = collect($settings['allowed_expiry_minutes'])
        ->reject(fn ($minutes) => in_array($minutes, \App\Support\PlatformSettings::DEFAULT_EXPIRY_OPTIONS, true))
        ->implode(', ');
@endphp
<div class="admin-page-header">
    <div>
        <p class="eyebrow">PLATFORM ADMIN</p>
        <h1>Platform settings</h1>
        <p class="dashboard-subtitle">Control creator limits and publishing choices across DearYou.</p>
    </div>
</div>

<form method="post" action="{{ route('admin.settings.update') }}" class="form-card platform-settings-form" data-platform-settings-form>
    @csrf @method('put')
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Settings were not saved.</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <div class="platform-settings-section">
    <div class="row g-4">
        <div class="col-lg-6">
            <h2 class="h4">Publishing windows</h2>
            <p class="text-secondary">Choose which private-link durations creators may use.</p>
            <div class="d-flex flex-wrap gap-3 mb-3">
                @foreach($presetExpiryOptions as $minutes => $label)
                    <label class="form-check platform-setting-choice">
                        <input class="form-check-input" type="checkbox" name="allowed_expiry_minutes[]" value="{{ $minutes }}" data-expiry-choice @checked(in_array($minutes, old('allowed_expiry_minutes', $settings['allowed_expiry_minutes'])))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('allowed_expiry_minutes')<p class="text-danger small">{{ $message }}</p>@enderror
            <label class="form-label" for="custom_expiry_value">Add custom time</label>
            <div class="custom-expiry-builder">
                <input
                    class="form-control"
                    id="custom_expiry_value"
                    type="number"
                    min="1"
                    max="43200"
                    inputmode="numeric"
                    placeholder="3"
                    data-custom-expiry-value
                >
                <select class="form-select" aria-label="Custom time unit" data-custom-expiry-unit>
                    <option value="minutes">Minutes</option>
                    <option value="hours" selected>Hours</option>
                    <option value="days">Days</option>
                </select>
                <button class="btn btn-outline-dearyou" type="button" data-add-custom-expiry>
                    <i class="bi bi-plus-lg"></i> Add
                </button>
            </div>
            <div class="custom-expiry-quick-actions" aria-label="Quick custom time presets">
                <button class="btn btn-sm btn-outline-dearyou" type="button" data-quick-expiry="15">+15 minutes</button>
                <button class="btn btn-sm btn-outline-dearyou" type="button" data-quick-expiry="60">+1 hour</button>
                <button class="btn btn-sm btn-outline-dearyou" type="button" data-quick-expiry="1440">+1 day</button>
            </div>
            <input
                id="custom_expiry_minutes"
                name="custom_expiry_minutes"
                type="hidden"
                value="{{ old('custom_expiry_minutes', $customExpiryValue) }}"
                data-custom-expiry
            >
            <div class="custom-expiry-list" data-custom-expiry-list></div>
            <div class="form-text mb-3">Custom times can be from 1 minute up to 30 days. Remove a custom time by clicking its chip.</div>
            @error('custom_expiry_minutes')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
            <label class="form-label" for="default_expiry_minutes">Default duration</label>
            <select class="form-select" id="default_expiry_minutes" name="default_expiry_minutes" data-default-expiry>
                @foreach($expiryOptions as $minutes => $label)
                    <option value="{{ $minutes }}" @selected((int) old('default_expiry_minutes', $settings['default_expiry_minutes']) === $minutes)>{{ $label }}</option>
                @endforeach
            </select>
            @error('default_expiry_minutes')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="col-lg-6">
            <h2 class="h4">Storage protection</h2>
            <p class="text-secondary">Text is never removed. Cleanup only targets media on expired letters.</p>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label" for="storage_limit_mb">Allowance per creator (MB)</label>
                    <input class="form-control" id="storage_limit_mb" name="storage_limit_mb" type="number" min="1" max="10240" value="{{ old('storage_limit_mb', $settings['storage_limit_mb']) }}">
                    @error('storage_limit_mb')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="cleanup_grace_days">Warning period (days)</label>
                    <input class="form-control" id="cleanup_grace_days" name="cleanup_grace_days" type="number" min="1" max="90" value="{{ old('cleanup_grace_days', $settings['cleanup_grace_days']) }}">
                    @error('cleanup_grace_days')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
            <input type="hidden" name="cleanup_policy" value="oldest_expired">
            <label class="form-check form-switch mt-4">
                <input class="form-check-input" type="checkbox" name="cleanup_enabled" value="1" @checked(old('cleanup_enabled', $settings['cleanup_enabled']))>
                <span class="form-check-label"><strong>Enable automatic media cleanup</strong><br><small class="text-secondary">Remove oldest expired-letter media after the warning period.</small></span>
            </label>
        </div>
    </div>
    </div>

    <div class="platform-settings-section">
    <div class="row g-4">
        <div class="col-lg-7">
            <h2 class="h4">Letter occasions</h2>
            <p class="text-secondary">Choose which starting categories creators can select for new letters. Existing letters remain editable.</p>
            <div class="platform-category-grid">
                @foreach(\App\Support\PlatformSettings::CATEGORY_OPTIONS as $category => $label)
                    <label class="form-check platform-setting-choice">
                        <input class="form-check-input" type="checkbox" name="enabled_categories[]" value="{{ $category }}" @checked(in_array($category, old('enabled_categories', $settings['enabled_categories'])))>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @error('enabled_categories')<p class="text-danger small mt-2">{{ $message }}</p>@enderror
        </div>

        <div class="col-lg-5">
            <h2 class="h4">Creation upload limits</h2>
            <p class="text-secondary">These are per-file limits. The creator's total storage allowance still applies.</p>
            <div class="row g-3">
                <div class="col-sm-6">
                    <label class="form-label" for="letter_media_limit_mb">Picture, GIF, or video (MB)</label>
                    <input class="form-control" id="letter_media_limit_mb" name="letter_media_limit_mb" type="number" min="1" max="100" value="{{ old('letter_media_limit_mb', $settings['letter_media_limit_mb']) }}">
                    @error('letter_media_limit_mb')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="audio_limit_mb">Audio file (MB)</label>
                    <input class="form-control" id="audio_limit_mb" name="audio_limit_mb" type="number" min="1" max="200" value="{{ old('audio_limit_mb', $settings['audio_limit_mb']) }}">
                    @error('audio_limit_mb')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="profile_image_limit_mb">Profile picture (MB)</label>
                    <input class="form-control" id="profile_image_limit_mb" name="profile_image_limit_mb" type="number" min="1" max="50" value="{{ old('profile_image_limit_mb', $settings['profile_image_limit_mb']) }}">
                    @error('profile_image_limit_mb')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
                </div>
                <div class="col-sm-6">
                    <label class="form-label" for="memory_files_per_upload">Memory files at once</label>
                    <input class="form-control" id="memory_files_per_upload" name="memory_files_per_upload" type="number" min="1" max="20" value="{{ old('memory_files_per_upload', $settings['memory_files_per_upload']) }}">
                    @error('memory_files_per_upload')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
    </div>
    </div>

    <div class="platform-settings-section">
        <div class="row g-4 align-items-end">
            <div class="col-lg-7">
                <h2 class="h4">Admin notifications</h2>
                <p class="text-secondary">Choose where private website feedback should be sent.</p>
                <label class="form-label" for="feedback_notify_email">Feedback notification email</label>
                <input
                    class="form-control"
                    id="feedback_notify_email"
                    name="feedback_notify_email"
                    type="email"
                    value="{{ old('feedback_notify_email', $settings['feedback_notify_email']) }}"
                    placeholder="admin@dearyous.app"
                    autocomplete="email"
                >
                <div class="form-text">Leave blank to disable email alerts. Feedback still appears in the admin feedback page.</div>
                @error('feedback_notify_email')<p class="text-danger small mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="col-lg-5">
                <div class="admin-inline-note">
                    <strong>Useful for launch:</strong>
                    you can route all feedback to your admin inbox without opening the server `.env` file.
                </div>
            </div>
        </div>
    </div>

    <div class="platform-settings-actions">
        <button class="btn btn-dearyou" type="submit"><i class="bi bi-check2-circle"></i> Save platform settings</button>
    </div>
</form>
@endsection
