<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckSetup
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if ((!Schema::hasTable('users') 
            || DB::table('users')->count() === 0) 
            && !$request->is('setup*', 'error')
        ) {
            //            config(['session.driver' => 'array']);
            return redirect('/setup/account');
        }

        return $next($request);
    }
}
