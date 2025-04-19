<?php

namespace App\Http\Middleware;

use App\Models\Share;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class HandleGuestShareMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('slug') ?? $request->input('slug');
        if (!$slug) {
            return redirect()->route('rejected');
        }
        $share = Share::whereBySlug($slug)->first();

        if (! $share || ! $share->enabled || ($share->expiry && $share->created_at->addDays($share->expiry)->lt(now()))) {

            return redirect()->route('login', ['slug' => $slug]);
        }
        if ($this->isNeedsPassword($share, $slug)) {
            return redirect()->route('shared.password', ['slug' => $slug]);
        }

        return $next($request);
    }

    public function isNeedsPassword($share, mixed $slug): bool
    {
        return $share->password && ! Session::get("shared_{$slug}_authenticated");
    }
}
