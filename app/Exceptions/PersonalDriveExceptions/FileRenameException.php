<?php

namespace App\Exceptions\PersonalDriveExceptions;

class FileRenameException extends PersonalDriveException
{
    public static function couldNotRename(): FileRenameException
    {
        return new self('Could not rename file. File with same name exists');
    }

    public static function couldNotUpdateIndex(): FileRenameException
    {
        return new self('Error! File renamed. But index not updated');
    }
}
