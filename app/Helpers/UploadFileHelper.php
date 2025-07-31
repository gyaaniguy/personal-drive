<?php

namespace App\Helpers;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use Illuminate\Support\Facades\File;

class UploadFileHelper
{
    /**
     * Get the full path of the uploaded file. Currently there was a bug in laravel, so the full path has to be fetched from $_FILES
     */
    public static function getUploadedFileFullPath($fileIndex): string
    {
        //ltrim -> coz different environments
        $fullPath = ltrim($_FILES['files']['full_path'][$fileIndex], '.');
        return self::sanitizePath($fullPath);
    }

    private static function sanitizePath(string $path): string
    {
        if (str_contains($path, '..')) {
            throw UploadFileException::invalidPath();
        }
        return $path;
    }
}
