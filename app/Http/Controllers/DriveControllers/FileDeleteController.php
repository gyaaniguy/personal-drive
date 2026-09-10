<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\FileDeleteRequest;
use App\Models\LocalFile;
use App\Services\FileDeleteService;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;

class FileDeleteController extends Controller
{
    use FlashMessages;

    protected LocalFileStatsService $localFileStatsService;

    protected PathService $pathService;

    protected FileDeleteService $fileDeleteService;

    public function __construct(
        LocalFileStatsService $localFileStatsService,
        PathService $pathService,
        FileDeleteService $fileDeleteService
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->pathService = $pathService;
        $this->fileDeleteService = $fileDeleteService;
    }

    public function deleteFiles(FileDeleteRequest $request): RedirectResponse
    {
        $fileKeyArray = $request->validated('fileList');
        $rootPath = $this->pathService->getStorageFolderPath();
        $localFiles = LocalFile::getByIds($fileKeyArray);
        if (!$localFiles->count()) {
            return $this->error('No valid files in database. Try a ReSync first');
        }

        $result = $this->fileDeleteService->deleteFiles($localFiles, $rootPath);

        if (!$result['deleted']) {
            return $this->error($this->buildDelFailureMessage($result));
        }

        $message = "Deleted " . count($result['deleted']) . " files";
        if (count($result['unreadable']) || count($result['readonly'])) {
            $message .= '. ' . $this->buildDelFailureMessage($result);
        }

        return $this->success($message);
    }
}
