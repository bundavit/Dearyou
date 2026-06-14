<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorageCleanupLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['bytes_freed' => 'integer', 'files_removed' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function letter()
    {
        return $this->belongsTo(Letter::class);
    }
}
