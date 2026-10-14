<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    public const CATEGORIES = [
        'suggestion' => 'Suggestion',
        'bug' => 'Problem or bug',
        'design' => 'Design feedback',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'new' => 'New',
        'reviewed' => 'Reviewed',
        'resolved' => 'Resolved',
        'spam' => 'Potential spam',
    ];

    protected $table = 'feedback';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'reviewed_at' => 'datetime',
            'spam_score' => 'integer',
            'spam_reasons' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
