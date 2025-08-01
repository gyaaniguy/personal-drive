<?php

namespace App\Helpers;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;

class UploadFileHelper
{
    private static function sanitizePath(string $path): string
    {
        if (str_contains($path, '..')) {
            throw UploadFileException::invalidPath();
        }
        return $path;
    }
}
