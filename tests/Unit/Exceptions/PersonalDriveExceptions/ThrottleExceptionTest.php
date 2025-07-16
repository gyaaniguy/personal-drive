<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\ThrottleException;
use Tests\TestCase;

class ThrottleExceptionTest extends TestCase
{
    public function test_toomany_method_returns_throttle_exception_with_correct_message()
    {
        $exception = ThrottleException::toomany();

        $this->assertInstanceOf(ThrottleException::class, $exception);
        $this->assertEquals('Too Many requests. Please try again later', $exception->getMessage());
    }
}
