<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as TrustProxiesParent;
use Illuminate\Http\Request;

class TrustProxies extends TrustProxiesParent
{
    protected $proxies;
    protected $headers;

    public function __construct()
    {
        // Now config() works because middleware runs after bootstrap
        if (config('app.proxy_ips')) {
            $this->proxies = config('app.proxy_ips');
            $this->headers = config('app.proxy_headers',
                Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO
            );
        }
    }
}