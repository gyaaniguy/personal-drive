<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class EnsureFrontendBuilt
{
    public function handle(Request $request, Closure $next)
    {
        if (!file_exists(public_path('build/manifest.json'))) {
            return Redirect::route(
                'error',
                ['message' => 'Frontend not built. Ensure node, npm are installed Run "npm install && npm run build"']
            );
        }

        return $next($request);
    }
}
