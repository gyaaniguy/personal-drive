<?php

namespace App\Services;

use App\Helpers\UploadFileHelper;
use App\Models\LocalFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
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
        Log::error(__LINE__, Session::all());

        return $this->getTempStorageDirFull();
    }

    public function getTempStorageDirFull(): string
    {
        Log::error(__LINE__, Session::all());

        $tempUuid = Session::get($this->tempUuid);
        if (!$tempUuid) {
            return '';
        }
        $tempStorageParentPath = $this->pathService->getTempStorageDirPath();
        return $tempStorageParentPath.DIRECTORY_SEPARATOR.$tempUuid;
    }

    public function replaceFromTemp(): bool
    {
        Log::error(__LINE__, Session::all());

        $tempDirFullPath = $this->getTempStorageDirFull();

        if ($tempDirFullPath && file_exists($tempDirFullPath) && is_dir($tempDirFullPath)) {
            $storageDirPathRoot = $this->pathService->getStorageDirPath();
            Log::error(__LINE__);
            if ($storageDirPathRoot && file_exists($storageDirPathRoot) && is_dir($storageDirPathRoot)) {
                foreach (File::allFiles($tempDirFullPath) as $file) {
                    Log::error(__LINE__);
                    $target = str_replace($tempDirFullPath, $storageDirPathRoot, $file->getPathname());

                    if (file_exists($target) &&  $this->isFileFolderMisMatch($file, $target)) {
                        continue;
                    }

                    Log::error(__LINE__);
                    File::ensureDirectoryExists(dirname($target));
                    $localFile = LocalFile::getForFileObj($file);
                    File::move($file, $target);
                    $file = new SplFileInfo($target);
                    Log::error(__LINE__);
                    if (!$localFile) {
                        Log::error(__LINE__);
                        $dirSize = [];
                        $itemDetails = $this->localFileStatsService->getFileItemDetails($file, $dirSize);
                        $localFile = LocalFile::updateOrCreate($itemDetails, ['filename', 'public_path']);
                    } else {
                        Log::error(__LINE__);
                        $this->localFileStatsService->updateFileStats($localFile, $file);
                    }

                    Log::error(__LINE__);
                    $this->thumbnailService->genThumbnailsForFileIds([$localFile->id]);
                }
                return $this->cleanOldTempFiles();

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

        Log::error('cleanoldtemp');
        $tempDirFull = $this->getTempStorageDirFull();
        Log::error($tempDirFull);
        if ($tempDirFull && file_exists($tempDirFull) && is_dir($tempDirFull)) {
            Log::error(__LINE__);
            Session::forget($this->tempUuid);
            Session::forget($this->tempUuidTime);
            return UploadFileHelper::deleteFolder($tempDirFull);
        }
        return false;
    }


}
