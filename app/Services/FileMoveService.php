<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\MoveFileException;
use App\Helpers\FileOperationsHelper;
use App\Models\LocalFile;

class FileMoveService
{
    protected LPathService $pathService;
    protected LocalFileStatsService $localFileStatsService;
    protected FileOperationsHelper $fileOperationsHelper;

    public function __construct(
        LPathService $pathService,
        LocalFileStatsService $localFileStatsService,
        FileOperationsHelper $fileOperationsHelper
    ) {
        $this->pathService = $pathService;
        $this->localFileStatsService = $localFileStatsService;
        $this->fileOperationsHelper = $fileOperationsHelper;
    }

    public function moveFiles(array $fileKeyArray, string $destinationInputPath): bool
    {
        $localFiles = LocalFile::getByIds($fileKeyArray)->get();

        if (!$localFiles->count()) {
            throw MoveFileException::noValidFiles();
        }

        $destinationPublicPath = $this->pathService->cleanDrivePublicPath($destinationInputPath);
        $destinationPrivatePath = $this->pathService->genPrivatePathFromPublic($destinationPublicPath);

        if (!$destinationPrivatePath || !file_exists($destinationPrivatePath) || !is_dir($destinationPrivatePath)) {
            throw MoveFileException::invalidDestinationPath();
        }

        $successfulUploads = [];

        foreach ($localFiles as $localFile) {
            $itemPublicDestPathName = $destinationPublicPath . DIRECTORY_SEPARATOR . $localFile->filename;
            $itemPrivateDestPathName = $this->pathService->getStorageDirPath() . DIRECTORY_SEPARATOR . $itemPublicDestPathName;

            $this->moveSingleFileOrDirectory($localFile, $itemPublicDestPathName, $itemPrivateDestPathName, $successfulUploads);
        }

        if ($successfulUploads) {
            LocalFile::getByIds($successfulUploads)->delete();
            $this->localFileStatsService->generateStats($destinationPublicPath);
            return true;
        }
        return false;
    }

    private function moveSingleFileOrDirectory(LocalFile $localFile, string $itemPublicDestPathName, string $itemPrivateDestPathName, array &$successfulUploads): void
    {
        $itemPathName = $localFile->getPublicPathname();

        if (!$localFile->fileExists()) {
            return;
        }

        if ($localFile->isValidFile()) {
            $this->fileOperationsHelper->move($itemPathName, $itemPublicDestPathName);
            if (file_exists($itemPrivateDestPathName)) {
                $successfulUploads[] = $localFile->id;
            }
        }

        if ($localFile->isValidDir()) {
            $this->moveDirectory($localFile, $itemPathName, $itemPublicDestPathName, $itemPrivateDestPathName, $successfulUploads);
        }
    }

    private function moveDirectory(LocalFile $localFile, string $itemPathName, string $itemPublicDestPathName, string $itemPrivateDestPathName, array &$successfulUploads): void
    {
        $dirPublicPathname = ltrim($localFile->getPublicPathname(), '/');
        $dirSubFilesIds = LocalFile::getIdsByLikePublicPath($dirPublicPathname);

        $this->fileOperationsHelper->move($itemPathName, $itemPublicDestPathName);
        if (file_exists($itemPrivateDestPathName)) {
            $successfulUploads[] = $localFile->id;
            $successfulUploads = array_merge($dirSubFilesIds, $successfulUploads);
        }
    }
}
