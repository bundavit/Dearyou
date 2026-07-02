<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectToCanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonicalUrl = rtrim((string) config('app.url'), '/');
        $canonicalHost = parse_url($canonicalUrl, PHP_URL_HOST);

        if (! $canonicalHost || $request->getHost() === $canonicalHost) {
            return $next($request);
        }

        if ($request->getHost() === 'www.'.$canonicalHost) {
            return redirect()->away($canonicalUrl.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
