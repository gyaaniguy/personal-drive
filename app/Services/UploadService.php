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
        return $tempStorageParentPath.DIRECTORY_SEPARATOR.$tempUuid;
    }

    public function replaceFromTemp(): bool
    {
        $tempDirFullPath = $this->getTempStorageDirFull();

        if ($tempDirFullPath && file_exists($tempDirFullPath) && is_dir($tempDirFullPath)) {
            $storageDirPathRoot = $this->pathService->getStorageDirPath();
            if ($storageDirPathRoot && file_exists($storageDirPathRoot) && is_dir($storageDirPathRoot)) {
                foreach (File::allFiles($tempDirFullPath) as $file) {
                    $target = str_replace($tempDirFullPath, $storageDirPathRoot, $file->getPathname());

                    if (file_exists($target) &&  $this->isFileFolderMisMatch($file, $target)) {
                        continue;
                    }

                    File::ensureDirectoryExists(dirname($target));
                    $localFile = LocalFile::getForFileObj($file);
                    File::move($file, $target);
                    $file = new SplFileInfo($target);
                    if (!$localFile) {
                        $dirSize = [];
                        $itemDetails = $this->localFileStatsService->getFileItemDetails($file, $dirSize);
                        $localFile = LocalFile::updateOrCreate($itemDetails, ['filename', 'public_path']);
                    } else {
                        $this->localFileStatsService->updateFileStats($localFile, $file);
                    }

                    $this->thumbnailService->genThumbnailsForFileIds([$localFile->id]);
                }
                $this->cleanOldTempFiles();
            }
        }
        return false;
    }

    public function isFileFolderMisMatch(\Symfony\Component\Finder\SplFileInfo $file, array|string $target): bool
    {
        return ((is_file($file) && is_dir($target)) || (is_dir($file) && is_file($target)));
    }

    public function cleanOldTempFiles(): bool
    {
        $tempDirFull = $this->getTempStorageDirFull();
        if ($tempDirFull && file_exists($tempDirFull) && is_dir($tempDirFull)) {
            Session::forget($this->tempUuid);
            Session::forget($this->tempUuidTime);
            return UploadFileHelper::deleteFolder($tempDirFull);
        }
        return false;
    }


}