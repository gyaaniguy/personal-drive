<?php

namespace App\Helpers;

use Illuminate\Support\Facades\File;

class UploadFileHelper
{
    public static function getUploadedFileFullPath($fileIndex): string
    {
        return $_FILES['files']['full_path'][$fileIndex];
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
