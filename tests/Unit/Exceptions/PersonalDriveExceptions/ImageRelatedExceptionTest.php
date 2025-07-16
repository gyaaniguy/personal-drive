<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\ImageRelatedException;
use Tests\TestCase;

class ImageRelatedExceptionTest extends TestCase
{
    public function test_invalidImageDriver_method_returns_correct_message()
    {
        $exception = ImageRelatedException::invalidImageDriver();
        $this->assertInstanceOf(ImageRelatedException::class, $exception);
        $this->assertEquals('Could not generate thumbnail. Missing PHP extension: GD', $exception->getMessage());
    }
}
