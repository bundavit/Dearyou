<?php

namespace App\Support;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PlatformSettings
{
    public const DEFAULT_EXPIRY_OPTIONS = [15, 30, 60, 120];

    public const MAX_EXPIRY_MINUTES = 43200;

    public const CATEGORY_OPTIONS = [
        'confession' => 'Confession',
        'apology' => 'Apology',
        'birthday' => 'Birthday',
        'anniversary' => 'Anniversary',
        'valentine' => 'Valentine',
        'congratulations' => 'Congratulations',
        'thank-you' => 'Thank you',
        'friendship' => 'Friendship',
        'graduation' => 'Graduation',
        'celebration' => 'Celebration',
        'encouragement' => 'Encouragement',
        'missing-you' => 'Missing you',
        'good-luck' => 'Good luck',
        'custom' => 'Custom',
    ];

    public function expiryOptions(): array
    {
        $minutes = collect($this->value('allowed_expiry_minutes', self::DEFAULT_EXPIRY_OPTIONS))
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->sort()
            ->values();

        return $minutes->mapWithKeys(fn (int $value) => [$value => $this->durationLabel($value)])->all();
    }

    public function defaultExpiryMinutes(): int
    {
        $default = (int) $this->value('default_expiry_minutes', 60);

        return array_key_exists($default, $this->expiryOptions())
            ? $default
            : (int) array_key_first($this->expiryOptions());
    }

    public function storageLimitMb(): int
    {
        return max(1, (int) $this->value(
            'storage_limit_mb',
            (int) config('dearyou.storage_limit_mb', 250),
        ));
    }

    public function cleanupGraceDays(): int
    {
        return max(1, (int) $this->value(
            'cleanup_grace_days',
            (int) config('dearyou.storage_cleanup_grace_days', 7),
        ));
    }

    public function cleanupEnabled(): bool
    {
        return (bool) $this->value('cleanup_enabled', true);
    }

    public function cleanupPolicy(): string
    {
        return (string) $this->value('cleanup_policy', 'oldest_expired');
    }

    public function enabledCategories(): array
    {
        $enabled = collect($this->value('enabled_categories', array_keys(self::CATEGORY_OPTIONS)))
            ->filter(fn ($category) => is_string($category) && array_key_exists($category, self::CATEGORY_OPTIONS))
            ->unique()
            ->values()
            ->all();

        return $enabled ?: ['custom'];
    }

    public function categoryOptions(?string $include = null): array
    {
        $enabled = $this->enabledCategories();
        if ($include && array_key_exists($include, self::CATEGORY_OPTIONS) && ! in_array($include, $enabled, true)) {
            $enabled[] = $include;
        }

        return collect(self::CATEGORY_OPTIONS)->only($enabled)->all();
    }

    public function letterMediaLimitMb(): int
    {
        return $this->boundedLimit('letter_media_limit_mb', 10, 1, 100);
    }

    public function audioLimitMb(): int
    {
        return $this->boundedLimit('audio_limit_mb', 25, 1, 200);
    }

    public function profileImageLimitMb(): int
    {
        return $this->boundedLimit('profile_image_limit_mb', 10, 1, 50);
    }

    public function memoryFilesPerUpload(): int
    {
        return $this->boundedLimit('memory_files_per_upload', 10, 1, 20);
    }

    public function feedbackNotifyEmail(): ?string
    {
        $email = trim((string) $this->value(
            'feedback_notify_email',
            config('dearyou.feedback_notify_email'),
        ));

        return $email !== '' ? $email : null;
    }

    public function homepageAnnouncementEnabled(): bool
    {
        return (bool) $this->value('homepage_announcement_enabled', false);
    }

    public function homepageAnnouncementText(): ?string
    {
        $text = trim((string) $this->value('homepage_announcement_text', ''));

        return $text !== '' ? $text : null;
    }

    public function homepageAnnouncement(): ?string
    {
        return $this->homepageAnnouncementEnabled()
            ? $this->homepageAnnouncementText()
            : null;
    }

    public function homepageAnnouncementHistory(): array
    {
        $history = $this->value('homepage_announcement_history', []);

        if (! is_array($history)) {
            return [];
        }

        return collect($history)
            ->filter(fn ($item) => is_array($item) && filled($item['text'] ?? null))
            ->take(8)
            ->values()
            ->all();
    }

    public function recordHomepageAnnouncement(?string $text, bool $enabled, ?User $admin = null): void
    {
        $text = trim((string) $text);

        if ($text === '') {
            return;
        }

        $history = collect($this->homepageAnnouncementHistory())
            ->reject(fn (array $item) => ($item['text'] ?? null) === $text)
            ->prepend([
                'text' => $text,
                'enabled' => $enabled,
                'admin' => $admin?->name ?: $admin?->email,
                'created_at' => Carbon::now()->toDateTimeString(),
            ])
            ->take(8)
            ->values()
            ->all();

        $this->update(['homepage_announcement_history' => $history]);
    }

    public function kilobytes(int $megabytes): int
    {
        return $megabytes * 1024;
    }

    public function update(array $settings): void
    {
        foreach ($settings as $key => $value) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value],
            );
        }
    }

    public function all(): array
    {
        return [
            'allowed_expiry_minutes' => array_keys($this->expiryOptions()),
            'default_expiry_minutes' => $this->defaultExpiryMinutes(),
            'storage_limit_mb' => $this->storageLimitMb(),
            'cleanup_grace_days' => $this->cleanupGraceDays(),
            'cleanup_enabled' => $this->cleanupEnabled(),
            'cleanup_policy' => $this->cleanupPolicy(),
            'enabled_categories' => $this->enabledCategories(),
            'letter_media_limit_mb' => $this->letterMediaLimitMb(),
            'audio_limit_mb' => $this->audioLimitMb(),
            'profile_image_limit_mb' => $this->profileImageLimitMb(),
            'memory_files_per_upload' => $this->memoryFilesPerUpload(),
            'feedback_notify_email' => $this->feedbackNotifyEmail(),
            'homepage_announcement_enabled' => $this->homepageAnnouncementEnabled(),
            'homepage_announcement_text' => $this->homepageAnnouncementText(),
            'homepage_announcement_history' => $this->homepageAnnouncementHistory(),
        ];
    }

    public function durationLabel(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes === 1 ? '1 minute' : "{$minutes} minutes";
        }

        if ($minutes % 1440 === 0) {
            $days = intdiv($minutes, 1440);

            return $days === 1 ? '1 day' : "{$days} days";
        }

        if ($minutes % 60 === 0) {
            $hours = intdiv($minutes, 60);

            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return "{$minutes} minutes";
    }

    private function value(string $key, mixed $fallback): mixed
    {
        if (! Schema::hasTable('platform_settings')) {
            return $fallback;
        }

        return PlatformSetting::query()->find($key)?->value ?? $fallback;
    }

    private function boundedLimit(string $key, int $fallback, int $minimum, int $maximum): int
    {
        return min($maximum, max($minimum, (int) $this->value($key, $fallback)));
    }
}
