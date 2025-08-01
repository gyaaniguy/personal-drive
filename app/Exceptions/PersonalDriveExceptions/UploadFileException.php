<?php

namespace App\Exceptions\PersonalDriveExceptions;

class UploadFileException extends PersonalDriveException
{
    public static function outOfMemory(): UploadFileException
    {
        return new self('Memory exhausted while uploading. Increase PHP allocated memory');
    }

    public static function noNewDir(string $itemType): UploadFileException
    {
        return new self('Could not create new ' . $itemType);
    }

    public static function invalidPath(): UploadFileException
    {
        return new self('The upload path or dir contains invalid characters');
    }

    public static function fileExists(): self
    {
        return new self('File already exists');
    }
}
