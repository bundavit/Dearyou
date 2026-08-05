<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SiteMetric extends Model
{
    public const HOMEPAGE_VIEWS = 'homepage_views';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
        ];
    }
}
