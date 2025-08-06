<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleAuthOrGuestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $authMiddleware = app(Authenticate::class);
        try {
            return $authMiddleware->handle($request, $next);
        } catch (Exception) {
            // If not authenticated, delegate to the HandleGuestShareRequests middleware
            return app(HandleGuestShareMiddleware::class)->handle($request, $next);
        }
    }
}
