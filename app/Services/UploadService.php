<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Models\LocalFile;
use App\Models\Setting;
use Error;
use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class UploadService
{
    protected LocalFileStatsService $localFileStatsService;
    private PathService $pathService;
    private FileOperationsService $fileOperationsService;
    private ThumbnailService $thumbnailService;
    private Filesystem $filesystem;

    private string $tempUuid = 'temp_replace_dir_uuid';
    private string $tempUuidTime = 'temp_replace_dir_uuid_time';

    public function __construct(
        PathService $pathService,
        LocalFileStatsService $localFileStatsService,
        FileOperationsService $fileOperationsService,
        ThumbnailService $thumbnailService,
        Filesystem $filesystem
    ) {
        $this->pathService = $pathService;
        $this->localFileStatsService = $localFileStatsService;
        $this->fileOperationsService = $fileOperationsService;
        $this->thumbnailService = $thumbnailService;
        $this->filesystem = $filesystem;
    }

    public function setTempStorageDirAbs(): string
    {
        $tempStorageUuid = Str::uuid()->toString();
        Session::put($this->tempUuid, $tempStorageUuid);
        Session::put($this->tempUuidTime, now());

        return $this->getTempStorageDirAbs();
    }

    public function getTempStorageDirAbs(): string
    {
        $storagePath = Setting::getStoragePath();

        $tempStorageDir = $this->getTempStorageDir();
        if (!$tempStorageDir) {
            return '';
        }
        return $storagePath . DS . $tempStorageDir;
    }

    public function getTempStorageDir(): string
    {
        $tempUuid = Session::get($this->tempUuid);
        if (!$tempUuid) {
            return '';
        }

        return TEMP_SUBDIR . DS . $tempUuid;
    }

    public function syncTempToStorage(): bool
    {
        $tempDir = $this->getTempStorageDirAbs();
        $storageDir = $this->pathService->getStorageFolderPath();

        if (!$this->isValidDirectory($storageDir) || !$this->isValidDirectory($tempDir)) {
            return false;
        }
        $rootPathLen = $this->pathService->getRootPathLen();
        foreach ($this->filesystem->allFiles($tempDir) as $file) {
            if (!$this->syncFileToStorage($file, $tempDir, $storageDir, $rootPathLen)) {
                return false;
            }
        }

        return true;
    }

    private function isValidDirectory(string $path = ''): bool
    {
        return $path &&
            $this->filesystem->exists($path) &&
            $this->filesystem->isDirectory($path);
    }

    public function syncFileToStorage(SplFileInfo $tempFileSplInfo, string $sourceRoot, string $targetRoot, int $rootPathLen): bool
    {
        $targetPath = Str::replaceFirst($sourceRoot, $targetRoot, $tempFileSplInfo->getPathname());

        if (!$this->pathService->isWithinStorageRoot($targetPath)) {
            return false;
        }

        if (
            $this->filesystem->exists($targetPath)
            && $this->isFileFolderMisMatch($tempFileSplInfo->getPathname(), $targetPath)
        ) {
            return true;
        }

        $this->filesystem->ensureDirectoryExists(dirname($targetPath));
        $existingFile = LocalFile::getForFileObj($tempFileSplInfo);

        $this->filesystem->move($tempFileSplInfo->getPathname(), $targetPath);
        $newFile = new SplFileInfo($targetPath, dirname($targetPath), basename($targetPath));

        if (!$existingFile) {
            $itemDetails = $this->localFileStatsService->getFileItemDetails($newFile, $rootPathLen);
            $existingFile = LocalFile::updateOrCreate($itemDetails);
        } else {
            $this->localFileStatsService->updateFileStats($existingFile, $newFile);
        }

        $this->thumbnailService->genThumbnailsForFileIds([$existingFile->id]);

        return true;
    }

    public function isFileFolderMisMatch(string $file, string $directory): bool
    {
        return (
            ($this->filesystem->isFile($file) && $this->filesystem->isDirectory($directory)) ||
            ($this->filesystem->isDirectory($file) && $this->filesystem->isFile($directory))
        );
    }


    public function uploadToDir(string $destinationDir, mixed $file, string $publicPath): void
    {
        if (! $this->pathService->isWithinStorageRoot($destinationDir)) {
            throw UploadFileException::pathOutsideStorageRoot();
        }
        if (! $this->fileOperationsService->directoryExists($publicPath)) {
            $this->fileOperationsService->makeFolder($publicPath);
        }
        $name = $this->pathService->sanitizeFileName($file->getClientOriginalName());
        try {
            if ($file->move($destinationDir, $name)) {
                chmod($destinationDir . DS . $name, 0640);
                return;
            }
        } catch (FileException) {
            throw UploadFileException::pathTooLong();
        } catch (Error) {
            throw UploadFileException::outOfMemory();
        }
    }

    /**
     * @return array{successful: int, duplicates: int, conflicts: array<string>}
     */
    public function processFileUpload(array $files, string $privatePath, string $publicPath, bool $useTempForConflicts = false, bool $swallowErrors = false, bool $overwrite = true): array
    {
        $conflicts = [];
        $successful = $duplicates = 0;
        $tempReady = $useTempForConflicts ? $this->setTempStorageDirAbs() : '';

        foreach ($files as $file) {
            $result = $this->processSingleFile($file, $privatePath, $publicPath, $tempReady, $swallowErrors, $overwrite);

            if ($result === 'skip') {
                continue;
            }
            if ($result === 'conflict') {
                $conflicts[] = $this->pathService->sanitizeUploadPath($file->getClientOriginalPath());
                continue;
            }
            if ($result === 'duplicate') {
                $duplicates++;
                continue;
            }
            $successful++;
        }

        return ['successful' => $successful, 'duplicates' => $duplicates, 'conflicts' => $conflicts];
    }

    /**
     * @return string 'ok'|'skip'|'conflict'|'duplicate'
     */
    private function processSingleFile(mixed $file, string $privatePath, string $publicPath, string $tempReady, bool $swallowErrors, bool $overwrite = true): string
    {
        $sanitizedPath = $this->pathService->sanitizeUploadPath($file->getClientOriginalPath());
        $sanitizedName = $this->pathService->sanitizeFileName($file->getClientOriginalName());

        if ($sanitizedPath === '' || $sanitizedName === '') {
            return 'skip';
        }

        $relativeBasePath = $this->pathService->getPlusContentRoot($publicPath);
        $relativeDestinationPath = $relativeBasePath . $sanitizedPath;

        if ($this->isPathConflict($relativeDestinationPath, $relativeBasePath, $sanitizedPath)) {
            if ($tempReady) {
                $this->uploadToTemp($sanitizedPath, $file, $publicPath);
            }
            return 'conflict';
        }

        // Existing same-name file: stage to temp for the web confirm-flow, or
        // skip it when the caller opted out of overwriting (stateless API default).
        $destinationFullPath = $privatePath . $sanitizedPath;
        if (file_exists($destinationFullPath) && ($tempReady || !$overwrite)) {
            if ($tempReady) {
                $this->uploadToTemp($sanitizedPath, $file, $publicPath);
            }
            return 'duplicate';
        }

        if ($swallowErrors) {
            try {
                $this->uploadToDir(dirname($destinationFullPath), $file, dirname($relativeDestinationPath));
            } catch (Exception) {
                return 'skip';
            }
        } else {
            $this->uploadToDir(dirname($destinationFullPath), $file, dirname($relativeDestinationPath));
        }
        return 'ok';
    }

    private function isPathConflict(string $relativeDestinationPath, string $relativeBasePath, string $sanitizedPath): bool
    {
        return $this->fileOperationsService->directoryExists($relativeDestinationPath)
            || $this->fileOperationsService->pathExistsAsFile($relativeBasePath, dirname($sanitizedPath));
    }

    private function uploadToTemp(string $sanitizedPath, mixed $file, string $publicPath): void
    {
        $tempDir = dirname(
            $this->getTempStorageDirAbs() . DS . ($publicPath ? $publicPath . DS : '') . $sanitizedPath
        );
        $tempRelativePath = $this->getTempStorageDir() . DS . $publicPath;
        try {
            $this->uploadToDir($tempDir, $file, $tempRelativePath);
        } catch (Exception) {
            // skip failed temp uploads
        }
    }

    /**
     * @param array<string> $conflicts
     */
    public function summarizeConflicts(array $conflicts): string
    {
        return implode(', ', array_slice($conflicts, 0, 3));
    }

    /**
     * @param array<string> $conflicts
     */
    public function conflictRemainder(array $conflicts): string
    {
        $remaining = count($conflicts) - 3;

        return $remaining > 0 ? ' (+' . $remaining . ' more)' : '';
    }

    public function cleanOldTempFiles(): bool
    {
        $tempDirFullPath = $this->getTempStorageDirAbs();
        if (!$tempDirFullPath) {
            return true;
        }
        if (
            $this->filesystem->exists($tempDirFullPath)
            && $this->filesystem->isDirectory($tempDirFullPath)
        ) {
            Session::forget($this->tempUuid);
            Session::forget($this->tempUuidTime);
            return $this->filesystem->deleteDirectory($tempDirFullPath);
        }
        return false;
    }
}
