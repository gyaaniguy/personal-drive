<?php

namespace App\Services;

use App\Models\Setting;

class PathService
{

//    public function getTempStorageDirPath(): string
//    {
//        $storagePath = Setting::getStoragePath();;
//        if (!$storagePath) {
//            return '';
//        }
//
//        return $storagePath . DS . "temp_storage";
//    }

    public function getThumbnailDirPath(): string
    {
        return Setting::getStoragePath(). DS . THUMBS_SUBDIR;
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
        return preg_replace('#^/drive(/|$)#', '', $path);
    }
}
