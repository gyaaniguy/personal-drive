<?php

namespace App\Helpers;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use Illuminate\Support\Facades\File;

class UploadFileHelper
{
    public static function getUploadedFileFullPath($fileIndex): string
    {
        //ltrim -> coz different environments
        $fullPath = ltrim($_FILES['files']['full_path'][$fileIndex], '.');
        return self::sanitizePath($fullPath);

    }
    private static function sanitizePath(string $path): string
    {
        if (str_contains($path, '..')) {
            UploadFileException::invalidPath();
        }
        return $path;
    }

    public static function makeFolder(string $path, int $permission = 0750): bool
    {
        if (is_dir($path)) {
            return true;
        }

        if (! mkdir($path, $permission, true) && ! is_dir($path)) {
            return false;
        }

        return true;
    }

    public static function deleteFolder(string $dir): bool
    {

        if (File::exists($dir)) {
            return File::deleteDirectory($dir); // Delete everything inside UUID dir
        }

        return true;
    }

}
