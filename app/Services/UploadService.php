<?php

namespace App\Services;

use App\Helpers\UploadFileHelper;
use App\Models\LocalFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use SplFileInfo;

class UploadService
{
    protected LocalFileStatsService $localFileStatsService;
    private LPathService $pathService;
    private string $tempUuid = 'temp_replace_dir_uuid';
    private string $tempUuidTime = 'temp_replace_dir_uuid_time';
    private ThumbnailService $thumbnailService;

    public function __construct(
        LPathService $pathService,
        LocalFileStatsService $localFileStatsService,
        ThumbnailService $thumbnailService
    ) {
        $this->pathService = $pathService;
        $this->localFileStatsService = $localFileStatsService;
        $this->thumbnailService = $thumbnailService;
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

        if (!$storageDirPathRoot || !file_exists($storageDirPathRoot) || !is_dir($storageDirPathRoot)) {
            return false;
        }
        if (!$tempDirFullPath || !file_exists($tempDirFullPath) || !is_dir($tempDirFullPath)) {
            return false;
        }
        foreach (File::allFiles($tempDirFullPath) as $file) {
            $targetPathName = str_replace($tempDirFullPath, $storageDirPathRoot, $file->getPathname());

            if (file_exists($targetPathName) && $this->isFileFolderMisMatch($file, $targetPathName)) {
                continue;
            }

            File::ensureDirectoryExists(dirname($targetPathName));
            $existingFile = LocalFile::getForFileObj($file);
            File::move($file, $targetPathName);
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
        return ((is_file($file) && is_dir($target)) || (is_dir($file) && is_file($target)));
    }

    public function cleanOldTempFiles(): bool
    {
        $tempDirFullPath = $this->getTempStorageDirFull();
        if ($tempDirFullPath && file_exists($tempDirFullPath) && is_dir($tempDirFullPath)) {
            Session::forget($this->tempUuid);
            Session::forget($this->tempUuidTime);
            return UploadFileHelper::deleteFolder($tempDirFullPath);
        }
        return false;
    }
}
