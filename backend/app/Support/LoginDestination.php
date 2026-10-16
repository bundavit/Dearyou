<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Throwable;

class LoginDestination
{
    public function resolve(Request $request, User $user): string
    {
        $fallback = route($user->isAdmin() ? 'admin.platform' : 'letters.index');
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended)) {
            return $fallback;
        }

        $parts = parse_url($fallback);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (! str_starts_with($intended, $origin.'/')
            && (! str_starts_with($intended, '/') || str_starts_with($intended, '//'))) {
            return $fallback;
        }

        try {
            $route = app(Router::class)->getRoutes()->match(Request::create($intended, 'GET'));
            $middleware = $route->gatherMiddleware();
            if (in_array($user->isAdmin() ? 'role:user' : 'role:admin', $middleware, true)
                || in_array('guest', $middleware, true)
                || str_starts_with((string) $route->getName(), 'api.')) {
                return $fallback;
            }
        } catch (Throwable) {
            return $fallback;
        }

        return $intended;
    }
}
