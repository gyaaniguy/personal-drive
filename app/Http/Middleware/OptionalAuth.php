<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class OptionalAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('app.disable_auth') && Session::has('system_auth')) {
            auth()->logout();
            Session::forget('system_auth');
            return $next($request);
        }

        if (config('app.disable_auth') && !auth()->check()) {
            $systemUser = User::where('is_admin', true)->first();
            if ($systemUser) {
                auth()->login($systemUser);
                Session::put('system_auth', true);
            }
        }

        return $next($request);
    }
}
