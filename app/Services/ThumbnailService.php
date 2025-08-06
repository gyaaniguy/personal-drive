<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\ImageRelatedException;
use App\Exceptions\PersonalDriveExceptions\ThumbnailException;
use App\Models\LocalFile;
use Exception;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Exception\ExecutableNotFoundException;
use FFMpeg\FFMpeg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

class ThumbnailService
{
    private const IMAGE_SIZE = 210;
    private const IMAGE_EXT = '.jpeg';
    private const VIDEO_TYPE = 'video';
    private const IMAGE_TYPE = 'image';
    protected FileOperationsService $fileOperationsService;
    private PathService $pathService;

    public function __construct(PathService $pathService, FileOperationsService $fileOperationsService)
    {
        $this->pathService = $pathService;
        $this->fileOperationsService = $fileOperationsService;
    }

    public function genThumbnailsForFileIds(array $fileIds): int
    {
        $filesToGenerateFor = $this->getGeneratableFiles($fileIds)->get();
        return $this->generateThumbnails($filesToGenerateFor);
    }

    public function getGeneratableFiles(array $fileIds): Builder
    {
        return LocalFile::getByIds($fileIds)->whereIn('file_type', ['video', 'image']);
    }

    public function generateThumbnails(Collection $files): int
    {
        $this->ensureImageDriverLoaded();

        return $files->reduce(
            fn(int $count, LocalFile $file) => $count + $this->generateThumbnail($file),
            0
        );
    }

    /**
     * @return void
     * @throws ImageRelatedException
     */
    public function ensureImageDriverLoaded(): void
    {
        if (!extension_loaded('gd')) {
            throw ImageRelatedException::invalidImageDriver();
        }
    }

    public function generateThumbnail(LocalFile $file): int
    {
        return match ($file->file_type) {
            self::VIDEO_TYPE => $this->handleVideo($file),
            self::IMAGE_TYPE => $this->handleImage($file),
            default => 0,
        };
    }

    /**
     * @throws ThumbnailException
     */
    private function handleVideo(LocalFile $file): bool
    {
        $privateFilePath = $file->getPrivatePathNameForFile();

        if (!file_exists($privateFilePath)) {
            return false;
        }

        $fullFileThumbnailPath = $this->getFullFileThumbnailPath($file);
        try {
            $ffmpeg = FFMpeg::create();
            $video = $ffmpeg->open($privateFilePath);
            $video->frame(TimeCode::fromSeconds(1))->save($fullFileThumbnailPath);
            return $this->resizeImage($fullFileThumbnailPath, $fullFileThumbnailPath);
        } catch (ExecutableNotFoundException $e) {
            throw ThumbnailException::noFfmpeg();
        }
    }

    public function getFullFileThumbnailPath(LocalFile $file): string
    {
        $fileThumbnailDirPath = THUMBS_SUBDIR . DS . $file->getPublicPath();

        if (!$this->fileOperationsService->directoryExists($fileThumbnailDirPath)) {
            $this->fileOperationsService->makeFolder($fileThumbnailDirPath);
        }

        return $this->pathService->getThumbnailAbsPath()
            . DS
            . $file->getPublicPathPlusName()
            . ($file->file_type === 'video' ? self::IMAGE_EXT : '');
    }


    private function resizeImage(string $privateFilePath, string $fullFileThumbnailPath): bool
    {
        try {
            Image::useImageDriver(ImageDriver::Gd)->loadFile($privateFilePath)
                ->width(self::IMAGE_SIZE)
                ->height(self::IMAGE_SIZE)
                ->save($fullFileThumbnailPath);
        } catch (Exception) {
            return false;
        }

        return true;
    }

    protected function handleImage(LocalFile $file): bool
    {
        $privateFilePath = $file->getPrivatePathNameForFile();
        if (!file_exists($privateFilePath)) {
            return false;
        }
        $fullFileThumbnailPath = $this->getFullFileThumbnailPath($file);

        return $this->resizeImage($privateFilePath, $fullFileThumbnailPath);
    }
}
