<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\UUIDException;
use Tests\TestCase;

class UUIDExceptionTest extends TestCase
{
    public function test_nouuid_method_returns_correct_message()
    {
        $exception = UUIDException::nouuid();
        $this->assertInstanceOf(UUIDException::class, $exception);
        $this->assertEquals('application not installed properly. Try reinstalling', $exception->getMessage());
    }
}
