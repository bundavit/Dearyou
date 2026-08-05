<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteMetricEvent extends Model
{
    protected $fillable = [
        'key',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }
}
