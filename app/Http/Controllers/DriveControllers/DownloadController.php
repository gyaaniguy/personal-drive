<?php

namespace App\Http\Controllers\DriveControllers;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Helpers\ResponseHelper;
use App\Http\Requests\DriveRequests\DownloadRequest;
use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Services\LPathService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController
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

    public function index(DownloadRequest $request): BinaryFileResponse|JsonResponse
    {
        $fileKeyArray = $request->validated('fileList');
        $localFiles = LocalFile::getByIds($fileKeyArray)->get();
        if (!$localFiles || count($localFiles) === 0) {
            throw FetchFileException::notFoundDownload();
        }
        try {
            $downloadFilePath = $this->downloadService->generateDownloadPath($localFiles);
            if (!file_exists($downloadFilePath)) {
                return ResponseHelper::json('Perhaps trying to download empty dir ? ', false);
            }

            return $this->getDownloadResponse($downloadFilePath);
        } catch (Exception $e) {
            return ResponseHelper::json($e->getMessage(), false);
        }
    }

    private function getDownloadResponse(string $downloadFilePath): BinaryFileResponse
    {
        return Response::download(
            $downloadFilePath,
            basename($downloadFilePath),
            ['Content-Disposition' => 'attachment; filename="' . basename($downloadFilePath) . '"']
        );
    }
}
