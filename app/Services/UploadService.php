<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Helpers\UploadFileHelper;
use App\Models\LocalFile;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use SplFileInfo;

class UploadService
{
    protected LocalFileStatsService $localFileStatsService;
    private LPathService $pathService;
    private ThumbnailService $thumbnailService;
    private Filesystem $filesystem;

    private string $tempUuid = 'temp_replace_dir_uuid';
    private string $tempUuidTime = 'temp_replace_dir_uuid_time';

    public function __construct(
        LPathService $pathService,
        LocalFileStatsService $localFileStatsService,
        ThumbnailService $thumbnailService,
        Filesystem $filesystem
    ) {
        $this->pathService = $pathService;
        $this->localFileStatsService = $localFileStatsService;
        $this->thumbnailService = $thumbnailService;
        $this->filesystem = $filesystem;
    }

    public function setTempStorageDirFull(): string
    {
        $tempStorageUuid = Str::uuid()->toString();
        Session::put($this->tempUuid, $tempStorageUuid);
        Session::put($this->tempUuidTime, now());

        return $this->getTempStorageDirFull();
    }

    public function getTempStorageDirFull(): string
    {
        $tempUuid = Session::get($this->tempUuid);
        if (!$tempUuid) {
            return '';
        }
        $tempStorageParentPath = $this->pathService->getTempStorageDirPath();
        return $tempStorageParentPath . DIRECTORY_SEPARATOR . $tempUuid;
    }

    public function replaceFromTemp(): bool
    {
        $tempDirFullPath = $this->getTempStorageDirFull();
        $storageDirPathRoot = $this->pathService->getStorageDirPath();

        if (
            !$storageDirPathRoot ||
            !$this->filesystem->exists($storageDirPathRoot) ||
            !$this->filesystem->isDirectory($storageDirPathRoot)
        ) {
            return false;
        }

        if (
            !$tempDirFullPath ||
            !$this->filesystem->exists($tempDirFullPath) ||
            !$this->filesystem->isDirectory($tempDirFullPath)
        ) {
            return false;
        }

        foreach ($this->filesystem->allFiles($tempDirFullPath) as $file) {
            $targetPathName = str_replace($tempDirFullPath, $storageDirPathRoot, $file->getPathname());

            if (
                $this->filesystem->exists($targetPathName) &&
                $this->isFileFolderMisMatch($file, $targetPathName)
            ) {
                continue;
            }

            $this->filesystem->ensureDirectoryExists(dirname($targetPathName));

            $existingFile = LocalFile::getForFileObj($file);
            $this->filesystem->move($file->getPathname(), $targetPathName);
            $file = new SplFileInfo($targetPathName);

            if (!$existingFile) {
                $dirSize = [];
                $itemDetails = $this->localFileStatsService->getFileItemDetails($file, $dirSize);
                $existingFile = LocalFile::updateOrCreate($itemDetails, ['filename', 'public_path']);
            } else {
                $this->localFileStatsService->updateFileStats($existingFile, $file);
            }

            $this->thumbnailService->genThumbnailsForFileIds([$existingFile->id]);
        }

        return $this->cleanOldTempFiles();
    }

    public function isFileFolderMisMatch(\Symfony\Component\Finder\SplFileInfo $file, array|string $target): bool
    {
        return (
            ($this->filesystem->isFile($file) && $this->filesystem->isDirectory($target)) ||
            ($this->filesystem->isDirectory($file) && $this->filesystem->isFile($target))
        );
    }

    public function cleanOldTempFiles(): bool
    {
        $tempDirFullPath = $this->getTempStorageDirFull();
        if (
            $tempDirFullPath &&
            $this->filesystem->exists($tempDirFullPath) &&
            $this->filesystem->isDirectory($tempDirFullPath)
        ) {
            Session::forget($this->tempUuid);
            Session::forget($this->tempUuidTime);
            return UploadFileHelper::deleteFolder($tempDirFullPath);
        }
        return false;
    }

    public function makeFolder(string $path, int $permission = 0750): bool
    {
        if (file_exists($path)) {
            throw UploadFileException::nonewdir('folder');
        }
        if (!mkdir($path, $permission, true) && !is_dir($path)) {
            return false;
        }

        return true;
    }

}
