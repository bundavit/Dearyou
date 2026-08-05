<?php

namespace App\Support;

use App\Models\Letter;
use Illuminate\Support\Str;

class LetterPublisher
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function publish(Letter $letter): Letter
    {
        $expiryMinutes = array_key_exists($letter->expiry_minutes, $this->settings->expiryOptions())
            ? $letter->expiry_minutes
            : $this->settings->defaultExpiryMinutes();
        $expiresAt = now()->addMinutes($expiryMinutes);

        $letter->update([
            'status' => 'published',
            'expiry_minutes' => $expiryMinutes,
            'published_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $letter->link()->updateOrCreate([], [
            'token' => Str::random(64),
            'is_active' => true,
            'expires_at' => $expiresAt,
            'last_regenerated_at' => now(),
        ]);

        return $letter->refresh()->load('link');
    }

    public function unpublish(Letter $letter): Letter
    {
        $letter->update(['status' => 'unpublished']);
        $letter->link?->update(['is_active' => false]);

        return $letter->refresh()->load('link');
    }

    public function regenerate(Letter $letter): Letter
    {
        return $this->publish($letter);
    }

    public function disable(Letter $letter): Letter
    {
        $letter->link?->update(['is_active' => false]);

        return $letter->refresh()->load('link');
    }
}
