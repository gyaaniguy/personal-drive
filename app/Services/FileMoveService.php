<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileMoveException;
use App\Models\LocalFile;

class FileMoveService
{
    protected PathService $pathService;
    protected LocalFileStatsService $localFileStatsService;
    protected FileOperationsService $fileOperationsService;
    protected UUIDService $uuidService;
    private string $storageFilesUuid;

    public function __construct(
        PathService $pathService,
        LocalFileStatsService $localFileStatsService,
        FileOperationsService $fileOperationsService,
        UUIDService $uuidService
    ) {
        $this->pathService = $pathService;
        $this->localFileStatsService = $localFileStatsService;
        $this->fileOperationsService = $fileOperationsService;
        $this->uuidService = $uuidService;
        $this->storageFilesUuid = $this->uuidService->getStorageFilesUUID();
    }

    public function moveFiles(array $fileKeyArray, string $destinationInputPath): bool
    {
        $localFiles = LocalFile::getByIds($fileKeyArray)->get();

        if (!$localFiles->count()) {
            throw FileMoveException::noValidFiles();
        }

        $desPublicPath = $this->pathService->cleanDrivePublicPath($destinationInputPath);
        $destinationPrivatePath = $this->pathService->genPrivatePathFromPublic($desPublicPath);

        if (!$destinationPrivatePath || !file_exists($destinationPrivatePath) || !is_dir($destinationPrivatePath)) {
            throw FileMoveException::invalidDestinationPath();
        }

        $successfulUploads = [];

        foreach ($localFiles as $localFile) {
            $itemPublicDestPathName = $this->storageFilesUuid . DS . ($desPublicPath ? $desPublicPath . DS : '') . $localFile->filename;

            $this->moveSingleFileOrDirectory(
                $localFile,
                $itemPublicDestPathName,
                $successfulUploads
            );
        }

        if ($successfulUploads) {
            LocalFile::getByIds($successfulUploads)->delete();
            $this->localFileStatsService->generateStats($desPublicPath);
            return true;
        }
        return false;
    }

    private function moveSingleFileOrDirectory(
        LocalFile $localFile,
        string $itemPublicDestPathName,
        array &$successfulUploads
    ): void {
        $itemPathName = $this->storageFilesUuid . DS . $localFile->getPublicPathname();

        if (!$localFile->fileExists()) {
            return;
        }

        if ($localFile->isValidFile()) {
            $this->fileOperationsService->move($itemPathName, $itemPublicDestPathName);
            if ($this->fileOperationsService->fileExists($itemPublicDestPathName)) {
                $successfulUploads[] = $localFile->id;
            }
        }

        if ($localFile->isValidDir()) {
            $this->moveDirectory(
                $localFile,
                $itemPathName,
                $itemPublicDestPathName,
                $successfulUploads
            );
        }
    }

    private function moveDirectory(
        LocalFile $localFile,
        string $itemPathName,
        string $itemPublicDestPathName,
        array &$successfulUploads
    ): void {
        $dirPublicPathname = $localFile->getPublicPathname();
        $dirSubFilesIds = LocalFile::getIdsByLikePublicPath($dirPublicPathname);

        $this->fileOperationsService->move($itemPathName, $itemPublicDestPathName);
        if ($this->fileOperationsService->directoryExists($itemPublicDestPathName)) {
            $successfulUploads[] = $localFile->id;
            $successfulUploads = array_merge($dirSubFilesIds, $successfulUploads);
        }
    }
}
