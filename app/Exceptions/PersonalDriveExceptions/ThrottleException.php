<?php

namespace App\Exceptions\PersonalDriveExceptions;

class ThrottleException extends PersonalDriveException
{
    public static function toomany(): ThrottleException
    {
        return new self('Too Many requests. Please try again later');
    }
}
