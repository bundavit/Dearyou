<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Letter;
use App\Models\ModerationAudit;
use App\Models\Response;
use App\Models\StorageCleanupLog;
use App\Models\User;
use App\Support\CreatorStorage;

class PlatformDashboardController extends Controller
{
    public function __invoke(CreatorStorage $storage)
    {
        $storageBytes = User::query()->get()->sum(fn (User $user) => $storage->usedBytes($user));

        return view('admin.platform-dashboard', [
            'stats' => [
                'users' => User::where('role', User::ROLE_USER)->count(),
                'admins' => User::where('role', User::ROLE_ADMIN)->count(),
                'deleted' => User::onlyTrashed()->count(),
                'letters' => Letter::count(),
                'published' => Letter::where('status', 'published')->count(),
                'responses' => Response::count(),
                'opens' => Letter::sum('open_count'),
                'storage' => $storage->formatBytes($storageBytes),
                'cleanups' => StorageCleanupLog::count(),
                'moderated' => Letter::whereNotNull('moderation_disabled_at')->count(),
                'audits' => ModerationAudit::count(),
            ],
            'recentUsers' => User::where('role', User::ROLE_USER)->latest()->limit(6)->get(),
        ]);
    }
}
