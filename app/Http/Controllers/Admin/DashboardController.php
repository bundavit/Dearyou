<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Response;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $letters = $user->letters();

        return view('admin.dashboard', [
            'stats' => [
                'total' => (clone $letters)->count(),
                'published' => (clone $letters)->where('status', 'published')->count(),
                'drafts' => (clone $letters)->where('status', 'draft')->count(),
                'expired' => (clone $letters)->where('expires_at', '<', now())->count(),
                'responses' => Response::whereHas('letter', fn ($query) => $query->where('user_id', $user->id))->count(),
                'unread' => Response::whereNull('read_at')->whereHas('letter', fn ($query) => $query->where('user_id', $user->id))->count(),
            ],
            'recentLetters' => $user->letters()
                ->withCount('responses')
                ->latest('updated_at')
                ->limit(4)
                ->get(),
            'recentResponses' => Response::with('letter')
                ->whereHas('letter', fn ($query) => $query->where('user_id', $user->id))
                ->latest('submitted_at')
                ->limit(4)
                ->get(),
        ]);
    }
}
