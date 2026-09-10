<?php

namespace App\Http\Controllers\ShareControllers;

use App\Http\Controllers\Controller;
use App\Models\Share;
use App\Traits\FlashMessages;
use Inertia\Inertia;
use Inertia\Response;

class ShareListController extends Controller
{
    use FlashMessages;

    public function index(): Response
    {
        $shares = Share::getAllUnExpired();

        return Inertia::render(
            'Drive/Shares/AllShares',
            [
            'shares' => $shares,
            'totalShares' => $shares->count(),
            ]
        );
    }
}
