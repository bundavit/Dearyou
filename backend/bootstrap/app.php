<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureSessionIsCurrent;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\RestrictAdminNetwork;
use App\Http\Middleware\SerializeMediaWrites;
use App\Http\Middleware\StartWebSession;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Session\Middleware\StartSession;

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
            EnsureSessionIsCurrent::class,
        ], replace: [
            StartSession::class => StartWebSession::class,
        ]);

        $middleware->prependToPriorityList(
            AuthenticatesRequests::class,
            EnsureSessionIsCurrent::class,
        );

        $middleware->redirectUsersTo(fn ($request) => $request->user()?->isAdmin() ? '/admin/platform' : '/');
        $middleware->alias([
            'active' => EnsureAccountIsActive::class,
            'admin.network' => RestrictAdminNetwork::class,
            'role' => EnsureUserHasRole::class,
            'media.write' => SerializeMediaWrites::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
