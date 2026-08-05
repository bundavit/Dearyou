<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LetterMemoryImage extends Model
{
    protected $guarded = [];

    public function memory()
    {
        return $this->belongsTo(LetterMemory::class, 'letter_memory_id');
    }
}
