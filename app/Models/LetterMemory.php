<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterMemory extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['memory_date' => 'date'];
    }

    public function letter()
    {
        return $this->belongsTo(Letter::class);
    }

    public function images()
    {
        return $this->hasMany(LetterMemoryImage::class)->orderBy('sort_order')->orderBy('id');
    }
}
