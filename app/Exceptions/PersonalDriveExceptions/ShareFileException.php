<?php

namespace App\Exceptions\PersonalDriveExceptions;

class ShareFileException extends PersonalDriveException
{
    public static function couldNotShare(): ShareFileException
    {
        return new self('No valid files to share. Try a Resync');
    }

    public static function shareWrongPassword(): ShareFileException
    {
        return new self('Wrong password');
    }

    public static function directNeedsSingleFile(): ShareFileException
    {
        return new self('Direct links work only for a single file');
    }
}
