<?php

namespace App\Http\Controllers\DriveControllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\DriveRequests\DownloadRequest;
use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Services\PathService;
use App\Traits\GuestResourceAuthorize;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController
{
    use GuestResourceAuthorize;

    protected PathService $pathService;

    protected DownloadService $downloadService;

    public function __construct(
        PathService $pathService,
        DownloadService $downloadService
    ) {
        $this->pathService = $pathService;
        $this->downloadService = $downloadService;
    }

    public function index(DownloadRequest $request): BinaryFileResponse|JsonResponse
    {
        $fileKeyArray = $request->validated('fileList');
        $localFiles = LocalFile::getByIds($fileKeyArray)->get();
        if ($localFiles->isEmpty()) {
            return ResponseHelper::json('Could not find files to download', false);
        }
        if (Session::get('share_id') && !$this->guestVerified($fileKeyArray, $this->downloadService)) {
            return ResponseHelper::json('Error: authorization issue', false);
        }
        return $this->downloadValidFiles($localFiles);
    }

    public function downloadValidFiles(Collection $localFiles): JsonResponse|BinaryFileResponse
    {
        try {
            $downloadFilePath = $this->downloadService->generateDownloadPath($localFiles);
            if (!file_exists($downloadFilePath)) {
                return ResponseHelper::json('Perhaps trying to download empty dir ? ', false);
            }

            return Response::download(
                $downloadFilePath,
                basename($downloadFilePath),
                ['Content-Disposition' => 'attachment; filename="' . basename($downloadFilePath) . '"']
            );
        } catch (Exception $e) {
            return ResponseHelper::json($e->getMessage(), false);
        }
    }
}
