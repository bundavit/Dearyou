<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModerationAudit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function administrator()
    {
        return $this->belongsTo(User::class, 'admin_user_id')->withTrashed();
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id')->withTrashed();
    }

    public function letter()
    {
        return $this->belongsTo(Letter::class)->withTrashed();
    }
}
