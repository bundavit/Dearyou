<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SessionSecurity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSessionIsCurrent
{
    public function __construct(private readonly SessionSecurity $security) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        $user = $guard->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        // This is a fresh authentication only after SessionGuard has validated
        // the remember token. A session user must pass the version check below.
        if ($guard->viaRemember()) {
            $this->security->establish($request, $user);
        }

        if (! $this->security->isCurrent($request, $user)) {
            // Ordinary logout rotates the shared remember token. A replayed old
            // session must not revoke a newly issued cookie on another browser.
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session expired. Please sign in again.'], 401);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Your session expired. Please sign in again.',
            ]);
        }

        if (! $request->session()->has(SessionSecurity::SESSION_KEY)) {
            $this->security->establish($request, $user);
        }

        return $next($request);
    }
}
