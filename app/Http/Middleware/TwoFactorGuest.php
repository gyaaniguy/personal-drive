<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorGuest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('twoFactorUserId')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
