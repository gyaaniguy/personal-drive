<?php

namespace App\Http\Controllers\ShareControllers;

use App\Models\Share;
use App\Services\LocalFileStatsService;
use App\Services\LPathService;
use App\Traits\FlashMessages;
use Inertia\Inertia;
use Inertia\Response;

class ShareListController
{
    use FlashMessages;

    protected LPathService $pathService;

    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        LPathService $pathService,
        LocalFileStatsService $localFileStatsService
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->pathService = $pathService;
    }

    public function index(): Response
    {
        $shares = Share::getAllUnExpired();

        return Inertia::render('Drive/Shares/AllShares', ['shares' => $shares]);
    }
}
