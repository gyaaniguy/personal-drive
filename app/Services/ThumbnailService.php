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
    protected FileOperationsService $fileOperationsService;
    private PathService $pathService;
    private string $imageExt = '.jpeg';

    public function __construct(PathService $pathService, FileOperationsService $fileOperationsService)
    {
        $this->pathService = $pathService;
        $this->fileOperationsService = $fileOperationsService;
    }

    public function genThumbnailsForFileIds(array $fileIds): int
    {
        $filesToGenerateFor = $this->getGeneratableFiles($fileIds)->get();
        return $this->generateThumbnailsForFiles($filesToGenerateFor);
    }

    public function getGeneratableFiles(array $fileIds): Builder
    {
        return LocalFile::getByIds($fileIds)->whereIn('file_type', ['video', 'image']);
    }

    public function generateThumbnailsForFiles(Collection $files): int
    {
        if (!extension_loaded('gd')) {
            throw ImageRelatedException::invalidImageDriver();
        }
        $thumbsGenerated = 0;
        foreach ($files as $file) {
            switch ($file->file_type) {
                case 'video':
                    $thumbsGenerated += $this->generateVideoThumbnail($file) ? 1 : 0;
                    break;
                case 'image':
                    $thumbsGenerated += $this->generateImageThumbnail($file) ? 1 : 0;
                    break;
            }
        }

        return $thumbsGenerated;
    }

    /**
     * @throws ThumbnailException
     */
    private function generateVideoThumbnail(LocalFile $file): bool
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

            return $this->imageResize($fullFileThumbnailPath, $fullFileThumbnailPath);
        } catch (ExecutableNotFoundException $e) {
            throw ThumbnailException::noFfmpeg();
        }
    }

    public function getFullFileThumbnailPath(LocalFile $file): string
    {
        $thumbnailPathDir = $this->pathService->getThumbnailDirPath();
        $fileThumbnailDirPath = THUMBS_SUBDIR . DS . $file->getPublicPath();

        if (!$this->fileOperationsService->directoryExists($fileThumbnailDirPath)) {
            $this->fileOperationsService->makeFolder($fileThumbnailDirPath);
        }
        $imageExt = $file->file_type === 'video' ? $this->imageExt : '';

        return $thumbnailPathDir . DS . $file->getPublicPathname() . $imageExt;
    }


    private function imageResize(string $privateFilePath, string $fullFileThumbnailPath): bool
    {
        try {
            Image::useImageDriver(ImageDriver::Gd)->loadFile($privateFilePath)
                ->width(self::IMAGE_SIZE)
                ->height(self::IMAGE_SIZE)
                ->save($fullFileThumbnailPath);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    protected function generateImageThumbnail(LocalFile $file): bool
    {
        $privateFilePath = $file->getPrivatePathNameForFile();
        if (!file_exists($privateFilePath)) {
            return false;
        }
        $fullFileThumbnailPath = $this->getFullFileThumbnailPath($file);

        return $this->imageResize($privateFilePath, $fullFileThumbnailPath);
    }
}
