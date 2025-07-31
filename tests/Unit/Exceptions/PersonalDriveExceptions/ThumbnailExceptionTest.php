<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\ThumbnailException;
use Tests\TestCase;

class ThumbnailExceptionTest extends TestCase
{
    public function test_noffmpeg_method_returns_correct_message()
    {
        $exception = ThumbnailException::noFfmpeg();
        $this->assertInstanceOf(ThumbnailException::class, $exception);
        $this->assertEquals('FFMpeg not found ! Install for video thumbnails', $exception->getMessage());
    }
}
