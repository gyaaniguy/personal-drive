<?php

namespace App\Services;

use App\Models\LocalFile;
use App\Models\Setting;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;

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
        $storagePath = Setting::getSettingByKeyName(Setting::$storagePath);

        $tempStorageDir = $this->getTempStorageDir();
        if (!$tempStorageDir) {
            return '';
        }
        return $storagePath . DIRECTORY_SEPARATOR . $tempStorageDir;
    }

    public function getTempStorageDir(): string
    {
        $tempUuid = Session::get($this->tempUuid);
        if (!$tempUuid) {
            return '';
        }

        return "temp_storage" . DIRECTORY_SEPARATOR . $tempUuid;
    }

    public function syncTempToStorage(): bool
    {
        $tempDir = $this->getTempStorageDirFull();
        $storageDir = $this->pathService->getStorageFolderPath();

        if (!$this->isValidDirectory($storageDir) || !$this->isValidDirectory($tempDir)) {
            return false;
        }

        foreach ($this->filesystem->allFiles($tempDir) as $file) {
            $this->syncFileToStorage($file, $tempDir, $storageDir);
        }

        return true;
    }

    private function isValidDirectory(string $path = ''): bool
    {
        return $path &&
            $this->filesystem->exists($path) &&
            $this->filesystem->isDirectory($path);
    }

    public function syncFileToStorage(SplFileInfo $tempFileSplInfo, string $sourceRoot, string $targetRoot): void
    {
        $targetPath = str_replace($sourceRoot, $targetRoot, $tempFileSplInfo->getPathname());

        if (
            $this->filesystem->exists($targetPath) &&
            $this->isFileFolderMisMatch($tempFileSplInfo->getPathname(), $targetPath)
        ) {
            return;
        }

        $this->filesystem->ensureDirectoryExists(dirname($targetPath));
        $existingFile = LocalFile::getForFileObj($tempFileSplInfo);

        $this->filesystem->move($tempFileSplInfo->getPathname(), $targetPath);
        $newFile = new SplFileInfo($targetPath, dirname($targetPath), basename($targetPath));

        if (!$existingFile) {
            $dirSize = [];
            $itemDetails = $this->localFileStatsService->getFileItemDetails($newFile, $dirSize);
            $existingFile = $this->updateOrCreateLocalFile($itemDetails, ['filename', 'public_path']);
        } else {
            $this->localFileStatsService->updateFileStats($existingFile, $newFile);
        }

        $this->thumbnailService->genThumbnailsForFileIds([$existingFile->id]);
    }

    public function isFileFolderMisMatch(string $file, string $directory): bool
    {
        return (
            ($this->filesystem->isFile($file) && $this->filesystem->isDirectory($directory)) ||
            ($this->filesystem->isDirectory($file) && $this->filesystem->isFile($directory))
        );
    }


    public function updateOrCreateLocalFile(array $attributes, array $values): LocalFile
    {
        return LocalFile::updateOrCreate($attributes, $values);
    }

    public function cleanOldTempFiles(): bool
    {
        $tempDirFullPath = $this->getTempStorageDirFull();
        if (!$tempDirFullPath) {
            return true;
        }
        if (
            $this->filesystem->exists($tempDirFullPath) &&
            $this->filesystem->isDirectory($tempDirFullPath)
        ) {
            Session::forget($this->tempUuid);
            Session::forget($this->tempUuidTime);
            return $this->filesystem->deleteDirectory($tempDirFullPath);
        }
        return false;
    }

    public function getTempUuid(): string
    {
        return $this->tempUuid;
    }
}
