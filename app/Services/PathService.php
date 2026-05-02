<?php

namespace App\Services;

use App\Models\Setting;

class PathService
{
    public function getPlusContentRoot(string $publicPath, string $fileName = ''): string
    {
        return CONTENT_SUBDIR . DS . ($publicPath ? $publicPath . DS : '') . ( $fileName ?: '');
    }

    public function getThumbnailAbsPath(): string
    {
        return Setting::getStoragePath() . DS . THUMBS_SUBDIR;
    }

    public function genPrivatePathFromPublic(string $publicPath = ''): string
    {
        $privateRoot = $this->getStorageFolderPath();

        if (!$privateRoot) {
            return '';
        }

        if ($publicPath === '') {
            return $privateRoot . DS;
        }
        $publicPath = $this->cleanDrivePublicPath($publicPath);
        return $privateRoot . DS . $publicPath . DS;
    }

    public function getStorageFolderPath(): string
    {
        return Setting::getStoragePath() . DS . CONTENT_SUBDIR;
    }

    public function cleanDrivePublicPath(string $path): string
    {

        if ($path === '/drive') {
            return '';
        }
        if (str_starts_with($path, '/drive/')) {
            return substr($path, 7); // remove "/drive/"
        }
        return $path;
    }
}
