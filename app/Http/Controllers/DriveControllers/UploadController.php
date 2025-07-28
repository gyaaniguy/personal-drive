<?php

namespace App\Http\Controllers\DriveControllers;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Helpers\UploadFileHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\CreateItemRequest;
use App\Http\Requests\DriveRequests\ReplaceAbortRequest;
use App\Http\Requests\DriveRequests\UploadRequest;
use App\Services\LocalFileStatsService;
use App\Services\LPathService;
use App\Services\FileOperationsService;
use App\Services\UploadService;
use App\Services\UUIDService;
use App\Traits\FlashMessages;
use Error;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class UploadController extends Controller
{
    use FlashMessages;

    protected LPathService $lPathService;
    protected FileOperationsService $fileOperationsService;
    protected UploadService $uploadService;
    protected LocalFileStatsService $localFileStatsService;
    protected UUIDService $uuidService;

    public function __construct(
        LPathService $lPathService,
        LocalFileStatsService $localFileStatsService,
        FileOperationsService $fileOperationsService,
        UploadService $uploadService,
        UUIDService $uuidService,
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->lPathService = $lPathService;
        $this->fileOperationsService = $fileOperationsService;
        $this->uploadService = $uploadService;
        $this->uuidService = $uuidService;
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
            return $this->success('Duplicates Detected', ['replaceAbort' => true]);
        }

        if ($successfulUploads > 0) {
            $this->localFileStatsService->generateStats($publicPath);
            return $this->success('Files uploaded: ' . $successfulUploads . ' out of ' . count($files));
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
            $fileName = $file->getClientOriginalPath();
            $destinationFullPath = $privatePath . $fileName;
            if (file_exists($destinationFullPath) && $tempStorageDirFull) {
                $duplicatesDetected++;
                $this->uploadToDir(
                    $this->uploadService->getTempStorageDirFull() . DIRECTORY_SEPARATOR . $publicPath ,
                    $file,
                    $this->uploadService->getTempStorageDir() . DIRECTORY_SEPARATOR . $publicPath
                );
            } else {
                $successfulUploads += $this->uploadToDir(
                    dirname($destinationFullPath),
                    $file,
                    $this->uuidService->getStorageFilesUUID() . DIRECTORY_SEPARATOR . $publicPath
                );
            }
        }

        return [$successfulUploads, $duplicatesDetected];
    }

    private function uploadToDir(string $destinationDir, mixed $file, string $publicPath): int
    {
        $successfulUploads = 0;
        if (!$this->fileOperationsService->directoryExists($publicPath)) {
            $this->fileOperationsService->makeFolder($publicPath);
        }
        try {
            if ($file->move($destinationDir, $file->getClientOriginalName())) {
                chmod($destinationDir . DIRECTORY_SEPARATOR . $file->getClientOriginalName(), 0640);
                $successfulUploads++;
            }
        } catch (Error $e) {
            throw UploadFileException::outofmemory();
        }
        return $successfulUploads;
    }

    public function createItem(CreateItemRequest $request): RedirectResponse
    {
        $publicPath = $request->validated('path') ?? '';
        $itemName = $request->validated('itemName');
        $isFile = $request->validated('isFile');
        $publicPath = $this->lPathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->lPathService->genPrivatePathFromPublic($publicPath);
        $storageFilesUUID = $this->uuidService->getStorageFilesUUID();
        if (
            $isFile &&
            !$this->fileOperationsService->makeFile(
                $storageFilesUUID . DIRECTORY_SEPARATOR . ($publicPath ? $publicPath . DIRECTORY_SEPARATOR : '') . $itemName
            )
        ) {
            return $this->error('Create file failed');
        }
        if (!$isFile && !$this->fileOperationsService->makeFolder($storageFilesUUID . DIRECTORY_SEPARATOR . ($publicPath ? $publicPath . DIRECTORY_SEPARATOR : '') . $itemName)) {
            return $this->error('Create folder failed');
        }

        $this->localFileStatsService->addItemPathStat($itemName, $privatePath, $publicPath, !$isFile);
        return $this->success('Created ' . ($isFile ? 'file' : 'folder') . ' successfully');
    }


    public function abortReplace(ReplaceAbortRequest $request): RedirectResponse
    {
        if ($request->action === 'abort') {
            $this->fileOperationsService->cleanOldTempFiles();
            return $this->success('Aborted Overwrite');
        }
        if ($request->action === 'overwrite') {
            $res = $this->fileOperationsService->syncTempToStorage();
            if (!$res) {
                return $this->error('overwriting failed !');
            }

            return $this->success('Overwritten successfully');
        }
        return Redirect::back();
    }
}
