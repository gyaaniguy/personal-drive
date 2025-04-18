<?php

namespace App\Http\Controllers\DriveControllers;

use App\Services\LocalFileStatsService;
use App\Helpers\ResponseHelper;
use App\Http\Requests\DriveRequests\FileSaveRequest;
use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Services\LPathService;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Http\Controllers\Controller;

class FileSaveController extends Controller
{
    protected LPathService $pathService;

    protected DownloadService $downloadService;
    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        LPathService $pathService,
        DownloadService $downloadService,
        LocalFileStatsService $localFileStatsService
    ) {
        $this->pathService = $pathService;
        $this->downloadService = $downloadService;
        $this->localFileStatsService = $localFileStatsService;
    }

    public function update(FileSaveRequest $request): BinaryFileResponse|JsonResponse
    {
        $id = $request->validated('id');
        $content = $request->validated('content');
        $localFile = LocalFile::getById($id);
        if (!$localFile) {
            return ResponseHelper::json('Could not find file ', false);
        }
        if ($localFile->file_type !== 'text' && $localFile->file_type !== 'empty') {
            return ResponseHelper::json('File is not a text file', false);
        }

        $privatePathFile = $localFile->getPrivatePathNameForFile();
        if (!$privatePathFile) {
            return ResponseHelper::json('Could not find file', false);
        }
        try {
            file_put_contents($privatePathFile, $content);
            $file = new \SplFileInfo($privatePathFile);
            $this->localFileStatsService->updateFileStats($localFile, $file);

            return ResponseHelper::json('File saved successfully', true);
        } catch (Exception $e) {
            return ResponseHelper::json($e->getMessage(), false);
        }
    }
}
