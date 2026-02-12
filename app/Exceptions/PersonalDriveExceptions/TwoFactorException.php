<?php

namespace App\Exceptions\PersonalDriveExceptions;

class TwoFactorException extends PersonalDriveException
{
    public static function couldNotValidate(string $msg): TwoFactorException
    {
        return new self('Error: '. $msg);
    }

}
