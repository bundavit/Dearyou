<?php

namespace App\Models;

use App\Support\PlatformSettings;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letter extends Model
{
    use HasFactory, SoftDeletes;

    public const EXPIRY_OPTIONS = [
        15 => '15 minutes',
        30 => '30 minutes',
        60 => '1 hour',
        120 => '2 hours',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allow_response' => 'boolean',
            'expires_at' => 'datetime',
            'media_cleaned_at' => 'datetime',
            'moderation_disabled_at' => 'datetime',
            'published_at' => 'datetime',
            'opened_at' => 'datetime',
            'open_count' => 'integer',
            'expiry_minutes' => 'integer',
            'relationship_started_at' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function link()
    {
        return $this->hasOne(LetterLink::class);
    }

    public function responses()
    {
        return $this->hasMany(Response::class);
    }

    public function memories()
    {
        return $this->hasMany(LetterMemory::class)->orderBy('sort_order')->orderBy('memory_date');
    }

    public function recipientLabel(): string
    {
        return $this->recipient_name ?: 'Someone special';
    }

    public function senderLabel(): string
    {
        return $this->sender_name ?: 'Anonymous';
    }

    public function isPubliclyAvailable(): bool
    {
        return $this->status === 'published'
            && $this->user
            && ! $this->user->disabled_at
            && ! $this->moderation_disabled_at
            && (! $this->expires_at || $this->expires_at->isFuture())
            && $this->link?->is_active
            && (! $this->link?->expires_at || $this->link->expires_at->isFuture());
    }

    public function linkState(): string
    {
        if ($this->status !== 'published') {
            return $this->status;
        }

        if ($this->moderation_disabled_at) {
            return 'moderated';
        }

        if ($this->expires_at?->isPast() || $this->link?->expires_at?->isPast()) {
            return 'expired';
        }

        if (! $this->link?->is_active) {
            return 'disabled';
        }

        return 'active';
    }

    public function expiryDurationLabel(): string
    {
        return app(PlatformSettings::class)->durationLabel($this->expiry_minutes);
    }

    public static function isVideoMediaPath(?string $path): bool
    {
        return $path !== null
            && in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['mp4', 'webm'], true);
    }
}
