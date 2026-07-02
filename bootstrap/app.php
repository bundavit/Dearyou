<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\RestrictAdminNetwork;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            RedirectToCanonicalHost::class,
        ]);

        $middleware->redirectUsersTo(fn ($request) => $request->user()?->isAdmin() ? '/admin/platform' : '/');
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'admin.network' => RestrictAdminNetwork::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
