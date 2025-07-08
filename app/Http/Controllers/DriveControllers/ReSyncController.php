<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Models\LocalFile;
use App\Services\LocalFileStatsService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;

class ReSyncController extends Controller
{
    use FlashMessages;

    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        LocalFileStatsService $localFileStatsService
    ) {
        $this->localFileStatsService = $localFileStatsService;
    }

    public function index(): RedirectResponse
    {
        LocalFile::clearTable();
        $filesUpdated = $this->localFileStatsService->generateStats();
        if ($filesUpdated > 0) {
            return $this->success('Sync successful. Found : ' . $filesUpdated . ' files');
        }

        return $this->error('No files found !');
    }
}
