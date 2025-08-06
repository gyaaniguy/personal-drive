<?php

namespace App\Exceptions\PersonalDriveExceptions;

class FetchFileException extends PersonalDriveException
{
    public static function notFoundStream(): FetchFileException
    {
        return new self('Could not find file to send');
    }

    public static function notFoundDownload(): FetchFileException
    {
        return new self('Could not find file to download');
    }

    public static function couldNotZip(): FetchFileException
    {
        return new self('Could not generate zip to download. Too large or empty folders ?');
    }

    public static function notFoundThumb(): FetchFileException
    {
        return new self('Could not find thumbnail. Try Resync in settings');
    }
}
