<?php

namespace App\Exceptions\PersonalDriveExceptions;

class UploadFileException extends PersonalDriveException
{
    public static function outofmemory(): UploadFileException
    {
        return new self('Memory exhausted while uploading. Increase PHP allocated memory');
    }

    public static function nonewdir(string $itemType): UploadFileException
    {
        return new self('Could not create new '. $itemType);
    }
    public static function invalidPath(): UploadFileException
    {
        return new self('The upload path or dir contains invalid characters');
    }
}
