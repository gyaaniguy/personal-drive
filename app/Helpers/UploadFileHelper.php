<?php

namespace App\Helpers;

use ErrorException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class UploadFileHelper
{
    public static function getUploadedFileFullPath($fileIndex): string
    {
        return $_FILES['files']['full_path'][$fileIndex];
    }

    public static function makeFolder(string $path, int $permission = 0755): bool
    {
        try {
            if (!file_exists($path)) {
                if (!mkdir($path, $permission, true) && !is_dir($path)) {
                    Log::error('Failed to create directory check permissions ');
                    return false;
                }
            }
            return true;
        } catch (ErrorException $e) {
            Log::error('Failed to create directory: ' . $e->getMessage());
            return false;
        }
    }
}
