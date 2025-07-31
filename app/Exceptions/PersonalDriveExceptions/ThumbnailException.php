<?php

namespace App\Exceptions\PersonalDriveExceptions;

class ThumbnailException extends PersonalDriveException
{
    public static function noFfmpeg(): ThumbnailException
    {
        return new self('FFMpeg not found ! Install for video thumbnails');
    }
}
