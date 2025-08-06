<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileMoveException;
use App\Models\LocalFile;

class FileMoveService
{
    protected PathService $pathService;
    protected LocalFileStatsService $localFileStatsService;
    protected FileOperationsService $fileOperationsService;

    public function __construct(
        PathService $pathService,
        LocalFileStatsService $localFileStatsService,
        FileOperationsService $fileOperationsService,
    ) {
        $this->pathService = $pathService;
        $this->localFileStatsService = $localFileStatsService;
        $this->fileOperationsService = $fileOperationsService;
    }

    public function moveFiles(array $fileKeyArray, string $destinationInputPath): bool
    {
        $successfulUploads = [];
        $localFiles = LocalFile::getByIds($fileKeyArray)->get();
        if (!$localFiles->count()) {
            throw FileMoveException::noValidFiles();
        }
        $desPublicPath = $this->pathService->cleanDrivePublicPath($destinationInputPath);
        $destinationPrivatePath = $this->pathService->genPrivatePathFromPublic($desPublicPath);

        if (!$destinationPrivatePath || !file_exists($destinationPrivatePath) || !is_dir($destinationPrivatePath)) {
            throw FileMoveException::invalidDestinationPath();
        }

        foreach ($localFiles as $localFile) {
            $this->moveSingleFileOrDirectory(
                $localFile,
                $desPublicPath,
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
        string $desPublicPath,
        array &$successfulUploads
    ): void {
        $itemPathName =  $localFile->getFullPathFromContentRoot();
        $itemPublicDestPathName = $localFile->getFullPathFromContentRoot('', $desPublicPath);CONTENT_SUBDIR . DS . ($desPublicPath ? $desPublicPath . DS : '') . $localFile->filename;

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
        $dirPublicPathname = $localFile->getPublicPathPlusName();
        $dirSubFilesIds = LocalFile::getIdsByLikePublicPath($dirPublicPathname);
        $this->fileOperationsService->move($itemPathName, $itemPublicDestPathName);
        if ($this->fileOperationsService->directoryExists($itemPublicDestPathName)) {
            $successfulUploads[] = $localFile->id;
            $successfulUploads = array_merge($dirSubFilesIds, $successfulUploads);
        }
    }
}
