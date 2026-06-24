<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ModerationAudit;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformSettingsController extends Controller
{
    public function edit(PlatformSettings $settings)
    {
        return view('admin.settings', [
            'settings' => $settings->all(),
            'expiryOptions' => $settings->expiryOptions(),
        ]);
    }

    public function update(Request $request, PlatformSettings $settings)
    {
        $validated = $request->validate([
            'allowed_expiry_minutes' => ['nullable', 'array'],
            'allowed_expiry_minutes.*' => ['required', 'integer', Rule::in(PlatformSettings::DEFAULT_EXPIRY_OPTIONS)],
            'custom_expiry_minutes' => ['nullable', 'string', 'max:500', 'regex:/^\s*\d+(?:\s*,\s*\d+)*\s*$/'],
            'default_expiry_minutes' => ['required', 'integer'],
            'storage_limit_mb' => ['required', 'integer', 'min:1', 'max:10240'],
            'cleanup_grace_days' => ['required', 'integer', 'min:1', 'max:90'],
            'cleanup_policy' => ['required', Rule::in(['oldest_expired'])],
            'enabled_categories' => ['required', 'array', 'min:1'],
            'enabled_categories.*' => ['required', 'string', Rule::in(array_keys(PlatformSettings::CATEGORY_OPTIONS))],
            'letter_media_limit_mb' => ['required', 'integer', 'min:1', 'max:100'],
            'audio_limit_mb' => ['required', 'integer', 'min:1', 'max:200'],
            'profile_image_limit_mb' => ['required', 'integer', 'min:1', 'max:50'],
            'memory_files_per_upload' => ['required', 'integer', 'min:1', 'max:20'],
            'feedback_notify_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);

        $presetExpiryMinutes = array_map('intval', $validated['allowed_expiry_minutes'] ?? []);
        $customExpiryMinutes = collect(explode(',', $validated['custom_expiry_minutes'] ?? ''))
            ->map(fn (string $value) => (int) trim($value))
            ->filter()
            ->all();
        $allowedExpiryMinutes = collect([...$presetExpiryMinutes, ...$customExpiryMinutes])
            ->filter(fn (int $minutes) => $minutes >= 1 && $minutes <= PlatformSettings::MAX_EXPIRY_MINUTES)
            ->unique()
            ->sort()
            ->values()
            ->all();
        $defaultExpiryMinutes = (int) $validated['default_expiry_minutes'];

        if ($allowedExpiryMinutes === []) {
            return back()->withErrors([
                'custom_expiry_minutes' => 'Enable a preset or add at least one custom duration.',
            ])->withInput();
        }

        if (count($customExpiryMinutes) !== count(array_filter(
            $customExpiryMinutes,
            fn (int $minutes) => $minutes >= 1 && $minutes <= PlatformSettings::MAX_EXPIRY_MINUTES,
        ))) {
            return back()->withErrors([
                'custom_expiry_minutes' => 'Custom durations must be between 1 minute and 30 days.',
            ])->withInput();
        }

        if (! in_array($defaultExpiryMinutes, $allowedExpiryMinutes, true)) {
            return back()->withErrors([
                'default_expiry_minutes' => 'The default duration must also be enabled.',
            ])->withInput();
        }

        unset($validated['custom_expiry_minutes']);

        $settings->update([
            ...$validated,
            'allowed_expiry_minutes' => $allowedExpiryMinutes,
            'default_expiry_minutes' => $defaultExpiryMinutes,
            'cleanup_enabled' => $request->boolean('cleanup_enabled'),
            'feedback_notify_email' => $validated['feedback_notify_email'] ?? '',
        ]);

        ModerationAudit::create([
            'admin_user_id' => $request->user()->id,
            'action' => 'platform_settings_updated',
            'reason' => 'Platform settings updated.',
            'metadata' => $settings->all(),
        ]);

        return back()->with('success', 'Platform settings updated.');
    }
}
