<?php

namespace App\Exceptions\PersonalDriveExceptions;

class TwoFactorException extends PersonalDriveException
{
    public static function couldNotValidate(): TwoFactorException
    {
        return new self('Could not validate two-factor code');
    }
}
