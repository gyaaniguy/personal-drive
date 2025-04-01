<?php

namespace App\Services;

use App\Models\Setting;

class LPathService
{
    protected UUIDService $uuidService;

    public function __construct(UUIDService $uuidService)
    {
        $this->uuidService = $uuidService;
    }

    public function cleanDrivePublicPath(string $path): string
    {
        return preg_replace('#^/drive(/|$)#', '', $path);
    }

    public function getStorageDirPath(): string
    {
        $storagePath = Setting::getSettingByKeyName(Setting::$storagePath);
        $uuid = $this->uuidService->getStorageFilesUUID();
        if (! $storagePath || ! $uuid) {
            return '';
        }

        return $storagePath.DIRECTORY_SEPARATOR.$uuid;
    }
    public function getTempStorageDirPath(): string
    {
        $storagePath = Setting::getSettingByKeyName(Setting::$storagePath);
        if (! $storagePath) {
            return '';
        }

        return $storagePath.DIRECTORY_SEPARATOR. "temp_storage";
    }

    public function getThumbnailDirPath(): string
    {
        $storagePath = Setting::getSettingByKeyName(Setting::$storagePath);
        $uuid = $this->uuidService->getThumbnailsUUID();
        if (! $storagePath || ! $uuid) {
            return '';
        }

        return $storagePath.DIRECTORY_SEPARATOR.$uuid;
    }

    public function genPrivatePathFromPublic(string $publicPath = ''): string
    {
        $privateRoot = $this->getStorageDirPath();
        if (! $privateRoot) {
            return '';
        }

        if ($publicPath === '') {
            return $privateRoot.DIRECTORY_SEPARATOR;
        }
        $publicPath = $this->cleanDrivePublicPath($publicPath);
        $privatePath = $privateRoot.DIRECTORY_SEPARATOR.$publicPath.DIRECTORY_SEPARATOR;

        if (file_exists($privatePath)) {
            return $privatePath;
        }

        return '';
    }
}
