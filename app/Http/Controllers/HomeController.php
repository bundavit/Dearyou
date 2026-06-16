<?php

namespace App\Http\Controllers;

use App\Models\SiteMetric;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        SiteMetric::query()
            ->firstOrCreate(
                ['key' => SiteMetric::HOMEPAGE_VIEWS],
                ['value' => 0],
            )
            ->increment('value');

        return view('welcome');
    }
}
