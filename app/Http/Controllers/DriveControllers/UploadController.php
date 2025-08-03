<?php

namespace App\Http\Controllers\DriveControllers;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\CreateItemRequest;
use App\Http\Requests\DriveRequests\ReplaceAbortRequest;
use App\Http\Requests\DriveRequests\UploadRequest;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
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

    protected PathService $pathService;
    protected FileOperationsService $fileOperationsService;
    protected UploadService $uploadService;
    protected LocalFileStatsService $localFileStatsService;
    protected UUIDService $uuidService;

    public function __construct(
        PathService $pathService,
        LocalFileStatsService $localFileStatsService,
        FileOperationsService $fileOperationsService,
        UploadService $uploadService,
        UUIDService $uuidService,
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->pathService = $pathService;
        $this->fileOperationsService = $fileOperationsService;
        $this->uploadService = $uploadService;
        $this->uuidService = $uuidService;
    }

    public function store(UploadRequest $request): RedirectResponse
    {
        $conflictsMessage = '';
        $files = $request->validated('files') ?? [];
        $publicPath = $request->validated('path') ?? '';
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->pathService->genPrivatePathFromPublic($publicPath);

        if (!$files) {
            return $this->error('File upload failed. No files uploaded');
        }
        if (!$privatePath) {
            return $this->error('File upload failed. Could not find storage path');
        }
        [$successfulUploads, $duplicatesDetected, $conflictsDetected] = $this->processFiles(
            $files,
            $privatePath,
            $publicPath
        );
        if ($conflictsDetected > 0) {
            $conflictsMessage = 'Conflicts: ' . $conflictsDetected . ' Files cannot overwrite folders';
        }

        if ($duplicatesDetected > 0) {
            $this->localFileStatsService->generateStats($publicPath);
            return $this->success('Duplicates Detected', ['replaceAbort' => true]);
        }

        if ($successfulUploads > 0) {
            $this->localFileStatsService->generateStats($publicPath);
            return $this->success('Files uploaded: ' . $successfulUploads . ' out of ' . count($files) . ($conflictsDetected > 0 ? ' (' . $conflictsMessage . ')' : ''));
        }

        return $this->error('Some/All Files upload failed' . ($conflictsDetected > 0 ? ' (' . $conflictsMessage . ')' : ''));
    }

    private function processFiles(array $files, string $privatePath, string $publicPath): array
    {
        //Temp storage in case we need to abort
        $tempStorageDirFull = $this->uploadService->setTempStorageDirFull();

        $conflictsDetected = $successfulUploads = $duplicatesDetected = 0;
        foreach ($files as $file) {
            $fileNameWithPath = $file->getClientOriginalPath();
            $destinationFullPath = $privatePath . $fileNameWithPath;
            $tempDirFullPath = dirname(
                $this->uploadService->getTempStorageDirFull() . DS . ($publicPath ? $publicPath . DS : '') . $fileNameWithPath
            );
            $tempDirRelativePath = $this->uploadService->getTempStorageDir() . DS . $publicPath;
            $relativeBasePath = $this->uuidService->getStorageFilesUUID() . DS . ($publicPath ? $publicPath . DS : '');
            $relativeDestinationPath = $relativeBasePath . $fileNameWithPath;

            if (
                $this->fileOperationsService->directoryExists($relativeDestinationPath) || $this->fileOperationsService->pathExistsAsFile(
                    $relativeBasePath,
                    dirname($fileNameWithPath)
                )
            ) {
                $conflictsDetected++;
            } elseif (file_exists($destinationFullPath) && $tempStorageDirFull) {
                $duplicatesDetected++;

                $this->uploadToDir(
                    $tempDirFullPath,
                    $file,
                    $tempDirRelativePath
                );
            } else {
                $successfulUploads += $this->uploadToDir(
                    dirname($destinationFullPath),
                    $file,
                    dirname($relativeDestinationPath)
                );
            }
        }

        return [$successfulUploads, $duplicatesDetected, $conflictsDetected];
    }

    private function uploadToDir(string $destinationDir, mixed $file, string $publicPath): int
    {
        $successfulUploads = 0;
        if (!$this->fileOperationsService->directoryExists($publicPath)) {
            $this->fileOperationsService->makeFolder($publicPath);
        }
        try {
            if ($file->move($destinationDir, $file->getClientOriginalName())) {
                chmod($destinationDir . DS . $file->getClientOriginalName(), 0640);
                $successfulUploads++;
            }
        } catch (Error $e) {
            throw UploadFileException::outOfMemory();
        }
        return $successfulUploads;
    }

    public function createItem(CreateItemRequest $request): RedirectResponse
    {
        $publicPath = $request->validated('path') ?? '';
        $itemName = $request->validated('itemName');
        $isFile = $request->validated('isFile');
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->pathService->genPrivatePathFromPublic($publicPath);
        $storageFilesUUID = $this->uuidService->getStorageFilesUUID();
        if (
            $isFile &&
            !$this->fileOperationsService->makeFile(
                $storageFilesUUID . DS . ($publicPath ? $publicPath . DS : '') . $itemName
            )
        ) {
            return $this->error('Create file failed');
        }
        if (!$isFile && !$this->fileOperationsService->makeFolder($storageFilesUUID . DS . ($publicPath ? $publicPath . DS : '') . $itemName)) {
            return $this->error('Create folder failed');
        }

        $this->localFileStatsService->addItemPathStat($itemName, $privatePath, $publicPath, !$isFile);
        return $this->success('Created ' . ($isFile ? 'file' : 'folder') . ' successfully');
    }


    public function abortReplace(ReplaceAbortRequest $request): RedirectResponse
    {
        if ($request->action === 'abort') {
            $this->uploadService->cleanOldTempFiles();
            return $this->success('Aborted Overwrite');
        }
        if ($request->action === 'overwrite') {
            $res = $this->uploadService->syncTempToStorage();
            if (!$res) {
                return $this->error('overwriting failed !');
            }

            return $this->success('Overwritten successfully');
        }
        return Redirect::back();
    }
}
