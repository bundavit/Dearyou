<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Letter extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'allow_response' => 'boolean',
            'expires_at' => 'datetime',
            'published_at' => 'datetime',
            'opened_at' => 'datetime',
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

    public function isPubliclyAvailable(): bool
    {
        return $this->status === 'published'
            && (! $this->expires_at || $this->expires_at->isFuture())
            && $this->link?->is_active
            && (! $this->link?->expires_at || $this->link->expires_at->isFuture());
    }
}
