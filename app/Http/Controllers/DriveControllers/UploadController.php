<?php

namespace App\Http\Controllers\DriveControllers;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Helpers\UploadFileHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\CreateFolderRequest;
use App\Http\Requests\DriveRequests\ReplaceAbortRequest;
use App\Http\Requests\DriveRequests\UploadRequest;
use App\Services\LocalFileStatsService;
use App\Services\LPathService;
use App\Services\UploadService;
use App\Traits\FlashMessages;
use Error;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;

class UploadController extends Controller
{
    use FlashMessages;

    protected LPathService $lPathService;
    protected UploadService $uploadService;
    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        LPathService $lPathService,
        LocalFileStatsService $localFileStatsService,
        UploadService $uploadService
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->lPathService = $lPathService;
        $this->uploadService = $uploadService;
    }

    public function store(UploadRequest $request): RedirectResponse
    {
        $files = $request->validated('files') ?? [];
        $publicPath = $request->validated('path') ?? '';
        $publicPath = $this->lPathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->lPathService->genPrivatePathFromPublic($publicPath);

        if (!$files) {
            return $this->error('File upload failed. No files uploaded');
        }
        if (!$privatePath) {
            return $this->error('File upload failed. Could not find storage path');
        }
        [$successfulUploads, $duplicatesDetected] = $this->processFiles($files, $privatePath, $publicPath);

        if ($duplicatesDetected > 0) {
            $this->localFileStatsService->generateStats($publicPath);
            return $this->success('Duplicates Detected2 !  '.$successfulUploads.' out of '.count($files),
                ['replaceAbort' => true]);
        }

        if ($successfulUploads > 0) {
            $this->localFileStatsService->generateStats($publicPath);
            return $this->success('Files uploaded: '.$successfulUploads.' out of '.count($files));
        }

        return $this->error('Some/All Files upload failed');
    }

    private function processFiles(array $files, string $privatePath, string $publicPath): array
    {
        //Temp storage in case we need to abort
        $tempStorageDirFull = $this->uploadService->setTempStorageDirFull();

        $successfulUploads = 0;
        $duplicatesDetected = 0;
        foreach ($files as $index => $file) {
            $fileNameWithDir = UploadFileHelper::getUploadedFileFullPath($index);
            $destinationFullPath = $privatePath.$fileNameWithDir;
            if (file_exists($destinationFullPath) && $tempStorageDirFull) {
                Log::error($tempStorageDirFull);
                Log::error($fileNameWithDir);
                Log::error($tempStorageDirFull.($publicPath ? '/'.$publicPath : '').$fileNameWithDir);
                $duplicatesDetected++;
                $this->uploadToDir($tempStorageDirFull.($publicPath ? '/'.$publicPath : '').$fileNameWithDir, $file);
            } else {
                $successfulUploads += $this->uploadToDir($destinationFullPath, $file);
            }
        }

        return [$successfulUploads, $duplicatesDetected];
    }

    private function uploadToDir(string $destinationFullPath, mixed $file): int
    {
        $successfulUploads = 0;
        $filesDirectory = dirname($destinationFullPath);
        if (!file_exists($filesDirectory)) {
            UploadFileHelper::makeFolder($filesDirectory);
        }
        try {
            if ($file->move($filesDirectory, $file->getClientOriginalName())) {
                chmod($filesDirectory.'/'.$file->getClientOriginalName(), 0640);
                $successfulUploads++;
            }
        } catch (Error $e) {
            throw UploadFileException::outofmemory();
        }
        return $successfulUploads;
    }

    public function createFolder(CreateFolderRequest $request): RedirectResponse
    {
        $publicPath = $request->validated('path') ?? '';
        $folderName = $request->validated('folderName');
        $publicPath = $this->lPathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->lPathService->genPrivatePathFromPublic($publicPath);
        $makeFolderRes = UploadFileHelper::makeFolder($privatePath.$folderName);
        if (!$makeFolderRes) {
            return $this->error('Create folder failed');
        }
        $this->localFileStatsService->addFolderPathStat($folderName, $publicPath);
        return $this->success('Created folder successfully');
    }

    public function abortReplace(ReplaceAbortRequest $request): RedirectResponse
    {
        Log::error(__LINE__, Session::all());

        if ($request->action === 'abort') {
            $this->uploadService->cleanOldTempFiles();
            return $this->success('Aborted Overwrite');
        }
        if ($request->action === 'overwrite') {
            $res = $this->uploadService->replaceFromTemp();
            if (!$res) {
                return $this->error('overwriting failed !');
            }

            return $this->success('Overwritten successfully');
        }
        return Redirect::back();
    }


}
