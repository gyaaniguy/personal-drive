<?php

namespace App\Http\Controllers\DriveControllers;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Helpers\ResponseHelper;
use App\Http\Requests\DriveRequests\SaveFileRequest;
use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Services\LPathService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileSaveController
{
    protected LPathService $pathService;

    protected DownloadService $downloadService;

    public function __construct(
        LPathService $pathService,
        DownloadService $downloadService
    ) {
        $this->pathService = $pathService;
        $this->downloadService = $downloadService;
    }

    public function update(SaveFileRequest $request): BinaryFileResponse|JsonResponse
    {
        $id = $request->validated('id');
        $content = $request->validated('content');
        $file = LocalFile::getById($id);
        if (!$file) {
            return ResponseHelper::json( 'Could not find file ', false);
        }
        if ($file->file_type !== 'text') {
            return ResponseHelper::json( 'File is not a text file', false);
        }

        $privatePathFile = $file->getPrivatePathNameForFile();
        if (!$privatePathFile) {
            return ResponseHelper::json( 'Could not find file', false);
        }
        try {
            file_put_contents($privatePathFile, $content);
            return ResponseHelper::json('File saved successfully', true);
        } catch (Exception $e) {
            return ResponseHelper::json($e->getMessage(), false);
        }
    }
}
