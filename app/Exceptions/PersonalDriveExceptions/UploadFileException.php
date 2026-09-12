<?php

namespace App\Exceptions\PersonalDriveExceptions;

class UploadFileException extends PersonalDriveException
{
    public static function noStoragePath(): self
    {
        return new self('Could not find storage path');
    }

    public static function outOfMemory(): UploadFileException
    {
        return new self('Memory exhausted while uploading. Increase PHP allocated memory');
    }

    public static function noNewDir(string $itemType): UploadFileException
    {
        return new self('Could not create new ' . $itemType);
    }

    public static function pathOutsideStorageRoot(): UploadFileException
    {
        return new self('The path resolves outside the storage root');
    }

    public static function pathTooLong(): UploadFileException
    {
        return new self('Upload path is too long. Shorten folder names or upload into a higher-level folder.');
    }

    public static function fileExists(): self
    {
        return new self('File already exists');
    }
}
