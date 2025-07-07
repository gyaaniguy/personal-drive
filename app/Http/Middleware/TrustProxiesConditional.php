<?php

namespace App\Http\Middleware;


use Closure;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustProxiesConditional extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('custom.trusted_proxies')) {
            $this->trustProxies(
                at: explode(',', env('TRUSTED_PROXIES', '*')), // '*' or comma-separated IPs
                headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB
            );
        }
        return $next($request);
    }
}
