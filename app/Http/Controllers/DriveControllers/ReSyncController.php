<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\Share;
use App\Services\LocalFileStatsService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

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
        try {
            $filesUpdated = DB::transaction(
                function (): int {
                    $favorites = Favorite::snapshotPaths();
                    LocalFile::clearTable();
                    Share::truncate();
                    $count = $this->localFileStatsService->generateStats();
                    Favorite::restorePaths($favorites);

                    return $count;
                }
            );
        } catch (UnexpectedValueException) {
            return $this->error(
                'Scan failed. Check permissions.'
            );
        }
        if ($filesUpdated > 0) {
            return $this->success('Sync successful. Found : ' . $filesUpdated . ' files');
        }

        return $this->error('No files found');
    }
}
