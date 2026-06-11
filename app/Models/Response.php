<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Response extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'read_at' => 'datetime'];
    }

    public function letter()
    {
        return $this->belongsTo(Letter::class);
    }

    public function letterLink()
    {
        return $this->belongsTo(LetterLink::class);
    }
}
