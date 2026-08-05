<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'expires_at' => 'datetime', 'last_regenerated_at' => 'datetime'];
    }

    public function letter()
    {
        return $this->belongsTo(Letter::class);
    }
}
