<?php

namespace App\Providers;

use App\Support\CreatorStorage;
use App\Models\Response;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by(
            Str::lower((string) $request->input('email')).'|'.$request->ip(),
        ));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(6)->by(
            (string) ($request->user()?->id ?? $request->ip()),
        ));
        RateLimiter::for('publishing', fn (Request $request) => Limit::perMinute(10)->by(
            (string) ($request->user()?->id ?? $request->ip()),
        ));
        RateLimiter::for('recipient-responses', fn (Request $request) => Limit::perMinute(10)->by(
            (string) $request->route('token').'|'.$request->ip(),
        ));

        View::composer('layouts.creator', function ($view) {
            $view->with('creatorStorage', app(CreatorStorage::class)->usage(auth()->user()));
        });

        View::composer('partials.user-navbar', function ($view) {
            $user = auth()->user();
            $unread = $user
                ? Response::query()
                    ->whereNull('read_at')
                    ->whereHas('letter', fn ($query) => $query->where('user_id', $user->id))
                    ->count()
                : 0;

            $view->with('navUnread', $unread);
        });
    }
}
