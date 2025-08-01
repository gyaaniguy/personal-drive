<?php

namespace App\Services;

use App\Models\Setting;

class PathService
{
    protected UUIDService $uuidService;

    public function __construct(UUIDService $uuidService)
    {
        $this->uuidService = $uuidService;
    }

//    public function getTempStorageDirPath(): string
//    {
//        $storagePath = Setting::getStoragePath();;
//        if (!$storagePath) {
//            return '';
//        }
//
//        return $storagePath . DIRECTORY_SEPARATOR . "temp_storage";
//    }

    public function getThumbnailDirPath(): string
    {
        $storagePath = Setting::getStoragePath();;
        $uuid = $this->uuidService->getThumbnailsUUID();
        if (!$storagePath || !$uuid) {
            return '';
        }

        return $storagePath . DIRECTORY_SEPARATOR . $uuid;
    }

    public function genPrivatePathFromPublic(string $publicPath = ''): string
    {
        $privateRoot = $this->getStorageFolderPath();

        if (!$privateRoot) {
            return '';
        }

        if ($publicPath === '') {
            return $privateRoot . DIRECTORY_SEPARATOR;
        }
        $publicPath = $this->cleanDrivePublicPath($publicPath);
        return $privateRoot . DIRECTORY_SEPARATOR . $publicPath . DIRECTORY_SEPARATOR;
    }

    public function getStorageFolderPath(): string
    {
        $storagePath = Setting::getStoragePath();;
        $uuid = $this->uuidService->getStorageFilesUUID();
        if (!$storagePath || !$uuid) {
            return '';
        }

        return $storagePath . DIRECTORY_SEPARATOR . $uuid;
    }

    public function cleanDrivePublicPath(string $path): string
    {
        return preg_replace('#^/drive(/|$)#', '', $path);
    }
}
