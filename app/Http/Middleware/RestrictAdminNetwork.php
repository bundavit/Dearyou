<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class RestrictAdminNetwork
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = array_filter(config('dearyou.admin_allowed_ips', []));

        if ($allowedIps !== [] && ! IpUtils::checkIp($request->ip(), $allowedIps)) {
            abort(404);
        }

        return $next($request);
    }
}
