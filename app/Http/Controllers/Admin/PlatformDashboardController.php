<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\Letter;
use App\Models\ModerationAudit;
use App\Models\Response;
use App\Models\SiteMetric;
use App\Models\SiteMetricEvent;
use App\Models\StorageCleanupLog;
use App\Models\User;
use App\Support\CreatorStorage;
use Illuminate\Support\Carbon;

class PlatformDashboardController extends Controller
{
    public function __invoke(CreatorStorage $storage)
    {
        $storageBytes = User::query()->get()->sum(fn (User $user) => $storage->usedBytes($user));
        $averageFeedbackRating = Feedback::query()->whereNotNull('rating')->avg('rating');

        return view('admin.platform-dashboard', [
            'stats' => [
                'users' => User::where('role', User::ROLE_USER)->count(),
                'admins' => User::where('role', User::ROLE_ADMIN)->count(),
                'deleted' => User::onlyTrashed()->count(),
                'letters' => Letter::count(),
                'published' => Letter::where('status', 'published')->count(),
                'responses' => Response::count(),
                'opens' => Letter::sum('open_count'),
                'homepage_visits' => (int) SiteMetric::query()
                    ->where('key', SiteMetric::HOMEPAGE_VIEWS)
                    ->value('value'),
                'storage' => $storage->formatBytes($storageBytes),
                'cleanups' => StorageCleanupLog::count(),
                'moderated' => Letter::whereNotNull('moderation_disabled_at')->count(),
                'audits' => ModerationAudit::count(),
                'feedback' => Feedback::count(),
                'new_feedback' => Feedback::where('status', 'new')->count(),
                'feedback_rating' => $averageFeedbackRating
                    ? number_format((float) $averageFeedbackRating, 1)
                    : '—',
            ],
            'homepageVisitsByDay' => $this->homepageVisitsByDay(),
            'homepageVisitsByWeek' => $this->homepageVisitsByWeek(),
            'recentUsers' => User::where('role', User::ROLE_USER)->latest()->limit(6)->get(),
            'recentFeedback' => Feedback::query()->with('user')->latest()->limit(6)->get(),
        ]);
    }

    private function homepageVisitsByDay(): array
    {
        $points = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = now()->subDays($daysAgo);

            return [
                'label' => $date->format('M j'),
                'count' => $this->visitCountBetween($date->copy()->startOfDay(), $date->copy()->endOfDay()),
            ];
        });

        return $this->withPercent($points);
    }

    private function homepageVisitsByWeek(): array
    {
        $points = collect(range(3, 0))->map(function (int $weeksAgo) {
            $start = now()->subWeeks($weeksAgo)->startOfWeek();
            $end = $start->copy()->endOfWeek();

            return [
                'label' => $weeksAgo === 0 ? 'This week' : $start->format('M j'),
                'count' => $this->visitCountBetween($start, $end),
            ];
        });

        return $this->withPercent($points);
    }

    private function visitCountBetween(Carbon $start, Carbon $end): int
    {
        return SiteMetricEvent::query()
            ->where('key', SiteMetric::HOMEPAGE_VIEWS)
            ->whereBetween('occurred_at', [$start, $end])
            ->count();
    }

    private function withPercent($points): array
    {
        $max = max(1, (int) $points->max('count'));

        return $points
            ->map(fn (array $point) => $point + [
                'percent' => $point['count'] > 0 ? max(8, (int) round(($point['count'] / $max) * 100)) : 0,
            ])
            ->all();
    }
}
