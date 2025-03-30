<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaMiddlware extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $flashA = [
            'message' => $request->session()->get('message'),
            'status' => $request->session()->get('status'),
        ];
        $sharedLink = $request->session()->get('shared_link');
        if ($sharedLink) {
            $flashA['shared_link'] = $sharedLink;
        }

        $moreInfo = $request->session()->get('more_info');
        if ($moreInfo) {
            $flashA['more_info'] = $moreInfo;
        }

        return [
            ...parent::share($request),
            'flash' => $flashA,
        ];
    }
}
