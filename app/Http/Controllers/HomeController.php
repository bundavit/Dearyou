<?php

namespace App\Http\Controllers;

use App\Models\SiteMetric;
use App\Models\SiteMetricEvent;
use App\Support\PlatformSettings;
use Illuminate\View\View;
use Throwable;

class HomeController extends Controller
{
    public function __invoke(PlatformSettings $settings): View
    {
        SiteMetric::query()
            ->firstOrCreate(
                ['key' => SiteMetric::HOMEPAGE_VIEWS],
                ['value' => 0],
            )
            ->increment('value');

        try {
            SiteMetricEvent::query()->create([
                'key' => SiteMetric::HOMEPAGE_VIEWS,
                'occurred_at' => now(),
            ]);
        } catch (Throwable) {
            // Metrics should never block the public homepage during deployment.
        }

        return view('welcome', [
            'announcement' => $settings->homepageAnnouncement(),
        ]);
    }
}
