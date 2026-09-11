<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileMoveException;
use App\Models\LocalFile;

class FileMoveService
{
    public function __construct(
        protected PathService $pathService,
        protected LocalFileStatsService $localFileStatsService,
        protected FileOperationsService $fileOperationsService,
    ) {
    }

    public function moveFiles(array $fileKeyArray, string $destinationInputPath): bool
    {
        $localFiles = LocalFile::getByIds($fileKeyArray)->get();
        $localFiles = $localFiles->sortBy(
            fn (LocalFile $file) => strlen($file->getPublicPathPlusName())
        );
        if (!$localFiles->count()) {
            throw FileMoveException::noValidFiles();
        }
        $desPublicPath = $this->pathService->cleanDrivePublicPath($destinationInputPath);
        $destinationPrivatePath = $this->pathService->genPrivatePathFromPublic($desPublicPath);

        if (!$destinationPrivatePath || !file_exists($destinationPrivatePath) || !is_dir($destinationPrivatePath)) {
            throw FileMoveException::invalidDestinationPath();
        }
        $destinations = [];
        $conflicts = [];
        foreach ($localFiles as $localFile) {
            $source = $localFile->getFullPathFromContentRoot();
            $destination = $localFile->getFullPathFromContentRoot('', $desPublicPath);
            if ($localFile->isValidDir() && str_starts_with($destination, rtrim($source, DS) . DS)) {
                throw FileMoveException::cannotMoveIntoItself();
            }
            if (
                isset($destinations[$destination])
                || $this->fileOperationsService->fileExists($destination)
                || $this->fileOperationsService->directoryExists($destination)
            ) {
                $conflicts[] = $localFile->filename;
            } else {
                $destinations[$destination] = true;
            }
        }
        if ($conflicts) {
            $message = 'Move cancelled. Conflicts: ' . implode(', ', array_slice($conflicts, 0, 3))
                . ' already exist at destination';
            $remaining = count($conflicts) - 3;
            if ($remaining > 0) {
                $message .= ' (+' . $remaining . ' more)';
            }
            throw new FileMoveException($message);
        }

        $movedAny = false;
        foreach ($localFiles as $localFile) {
            if ($this->moveSingleFileOrDirectory($localFile, $desPublicPath)) {
                $movedAny = true;
            }
        }

        if ($movedAny) {
            $this->localFileStatsService->generateStats($desPublicPath);
            return true;
        }
        return false;
    }

    private function moveSingleFileOrDirectory(LocalFile $localFile, string $desPublicPath): bool
    {
        $itemPathName = $localFile->getFullPathFromContentRoot();
        $itemPublicDestPathName = $localFile->getFullPathFromContentRoot('', $desPublicPath);

        if (
            !$this->fileOperationsService->fileExists($itemPathName)
            && !$this->fileOperationsService->directoryExists($itemPathName)
        ) {
            return false;
        }
        if ($localFile->isValidFile()) {
            $this->fileOperationsService->move($itemPathName, $itemPublicDestPathName);
            if ($this->fileOperationsService->fileExists($itemPublicDestPathName)) {
                $localFile->update([
                    'public_path' => $desPublicPath,
                    'private_path' => $this->destPrivateDir($desPublicPath),
                ]);
                return true;
            }
            return false;
        }
        if ($localFile->isValidDir()) {
            return $this->moveDirectory($localFile, $itemPathName, $itemPublicDestPathName, $desPublicPath);
        }
        return false;
    }

    private function moveDirectory(
        LocalFile $localFile,
        string $itemPathName,
        string $itemPublicDestPathName,
        string $desPublicPath
    ): bool {
        $oldDirPublicPathName = $localFile->getPublicPathPlusName();
        $this->fileOperationsService->move($itemPathName, $itemPublicDestPathName);
        if (!$this->fileOperationsService->directoryExists($itemPublicDestPathName)) {
            return false;
        }

        $newDirPublicPathName = $localFile->getPublicPathPlusName('', $desPublicPath);
        $storageContent = $this->pathService->getStorageFolderPath();
        $oldPrivPrefix = $storageContent . DS . $oldDirPublicPathName;
        $newPrivPrefix = $storageContent . DS . $newDirPublicPathName;

        // ponytail: rewrites each descendant row in a loop; fine for normal trees, batch if a move ever spans huge subtrees
        $children = LocalFile::getByPublicPathLikeSearch($oldDirPublicPathName)->get();
        foreach ($children as $child) {
            $child->update([
                'public_path' => $newDirPublicPathName . substr($child->public_path, strlen($oldDirPublicPathName)),
                'private_path' => $newPrivPrefix . substr($child->private_path, strlen($oldPrivPrefix)),
            ]);
        }

        $localFile->update([
            'public_path' => $desPublicPath,
            'private_path' => $this->destPrivateDir($desPublicPath),
        ]);
        return true;
    }

    private function destPrivateDir(string $publicPath): string
    {
        return rtrim($this->pathService->genPrivatePathFromPublic($publicPath), DS);
    }
}
