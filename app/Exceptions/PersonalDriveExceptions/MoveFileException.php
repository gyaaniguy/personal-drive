<?php

namespace App\Exceptions\PersonalDriveExceptions;

class MoveFileException extends PersonalDriveException
{
    public static function noValidFiles(): self
    {
        return new self('Could not find any valid files to move');
    }
    public static function invalidDestinationPath(): self
    {
        return new self('Destination path is invalid');
    }

}
