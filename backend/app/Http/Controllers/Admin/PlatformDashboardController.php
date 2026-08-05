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
use Illuminate\Support\Facades\DB;

class PlatformDashboardController extends Controller
{
    public function __invoke(CreatorStorage $storage)
    {
        $storageBytes = User::query()->get()->sum(fn (User $user) => $storage->usedBytes($user));
        $averageFeedbackRating = Feedback::query()->whereNotNull('rating')->avg('rating');
        $letterCount = Letter::count();
        $responseCount = Response::count();

        return view('admin.platform-dashboard', [
            'stats' => [
                'users' => User::where('role', User::ROLE_USER)->count(),
                'admins' => User::where('role', User::ROLE_ADMIN)->count(),
                'deleted' => User::onlyTrashed()->count(),
                'letters' => $letterCount,
                'published' => Letter::where('status', 'published')->count(),
                'responses' => $responseCount,
                'response_rate' => $letterCount > 0 ? round(($responseCount / $letterCount) * 100).'%' : '0%',
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
                    : '-',
            ],
            'homepageVisitsByDay' => $this->homepageVisitsByDay(),
            'homepageVisitsByWeek' => $this->homepageVisitsByWeek(),
            'letterStatusBreakdown' => $this->breakdown(Letter::query(), 'status'),
            'occasionBreakdown' => $this->breakdown(Letter::query(), 'category'),
            'feedbackByCategory' => $this->breakdown(Feedback::query(), 'category', Feedback::CATEGORIES),
            'topOpenedLetters' => Letter::query()
                ->with('user')
                ->where('open_count', '>', 0)
                ->orderByDesc('open_count')
                ->limit(5)
                ->get(),
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

    private function breakdown($query, string $column, array $labels = []): array
    {
        $points = $query
            ->select($column, DB::raw('count(*) as count'))
            ->groupBy($column)
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(function ($row) use ($column, $labels) {
                $value = (string) $row->{$column};

                return [
                    'label' => $labels[$value] ?? ucfirst(str_replace(['-', '_'], ' ', $value)),
                    'count' => (int) $row->count,
                ];
            });

        return $this->withPercent($points);
    }
}
