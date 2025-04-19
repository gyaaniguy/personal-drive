<?php

namespace App\Services;

use App\Models\LocalFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;

class FileDeleteService
{
    public function deleteFiles(Builder $filesInDB, string $rootStoragePath): int
    {
        $filesDeleted = 0;

        foreach ($filesInDB->get() as $file) {
            $privateFilePathName = $file->getPrivatePathNameForFile();
            if (!file_exists($privateFilePathName)) {
                continue;
            }

            if ($this->handleDirectoryDeletion($file, $privateFilePathName, $rootStoragePath)) {
                $filesDeleted++;
            }

            // Handle file deletion
            if ($this->isDeletableFile($file) && unlink($privateFilePathName)) {
                $filesDeleted++;
            }
        }

        return $filesDeleted;
    }

    protected function handleDirectoryDeletion(LocalFile $file, string $privateFilePathName, string $rootStoragePath): bool
    {
        if ($this->isDeletableDirectory($file, $privateFilePathName) &&
            $this->isDirSubDirOfStorage($privateFilePathName, $rootStoragePath)) {
            File::deleteDirectory($privateFilePathName);
            $file->deleteUsingPublicPath();
            return true;
        }
        return false;
    }

    public function isDeletableDirectory(LocalFile $file, string $privateFilePathName): bool
    {
        return $file->is_dir === 1 && file_exists($privateFilePathName) && is_dir($privateFilePathName);
    }

    public function isDirSubDirOfStorage(string $privateFilePathName, string $rootStoragePath): string|false
    {
        return strstr($privateFilePathName, $rootStoragePath);
    }

    public function isDeletableFile(LocalFile $file): bool
    {
        return $file->is_dir === 0;
    }
}
