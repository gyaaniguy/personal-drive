<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\ImageRelatedException;
use App\Exceptions\PersonalDriveExceptions\ThumbnailException;
use App\Helpers\UploadFileHelper;
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
    private const IMAGESIZE = 210;

    private LPathService $pathService;

    private string $imageExt = '.jpeg';

    public function __construct(LPathService $pathService)
    {
        $this->pathService = $pathService;
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

            return $this->imageResize($fullFileThumbnailPath, $fullFileThumbnailPath, self::IMAGESIZE);
        } catch (ExecutableNotFoundException $e) {
            throw ThumbnailException::noffmpeg();
        }
    }

    public function getFullFileThumbnailPath(LocalFile $file): string
    {
        $thumbnailPathDir = $this->pathService->getThumbnailDirPath();
        $fileThumbnailDirPath = $thumbnailPathDir .
            ($file->public_path ? DIRECTORY_SEPARATOR . $file->public_path : '');

        if (!file_exists($fileThumbnailDirPath)) {
            UploadFileHelper::makeFolder($fileThumbnailDirPath);
        }
        $imageExt = $file->file_type === 'video' ? $this->imageExt : '';

        return $thumbnailPathDir .
            ($file->public_path ? DIRECTORY_SEPARATOR : '') . $file->getPublicPathname() . $imageExt;
    }


    private function imageResize(string $privateFilePath, string $fullFileThumbnailPath, int $size): bool
    {
        try {
            Image::useImageDriver(ImageDriver::Gd)->loadFile($privateFilePath)
                ->width($size)
                ->height($size)
                ->save($fullFileThumbnailPath);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    private function generateImageThumbnail(LocalFile $file): bool
    {
        $privateFilePath = $file->getPrivatePathNameForFile();
        if (!file_exists($privateFilePath)) {
            return false;
        }
        $fullFileThumbnailPath = $this->getFullFileThumbnailPath($file);

        return $this->imageResize($privateFilePath, $fullFileThumbnailPath, self::IMAGESIZE);
    }

    private function setHasThumbnail($file): void
    {
        // deliberately has_thumbnail true, to prevent repeated thumb gen
        $file->has_thumbnail = true;
        $file->save();
    }
}
