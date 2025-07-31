<?php

namespace App\Exceptions\PersonalDriveExceptions;

class UUIDException extends PersonalDriveException
{
    public static function noUUID(): UUIDException
    {
        return new self('application not installed properly. Try reinstalling');
    }
}
