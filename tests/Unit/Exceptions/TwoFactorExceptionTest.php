<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\PersonalDriveExceptions\TwoFactorException;
use Tests\TestCase;

class TwoFactorExceptionTest extends TestCase
{
    public function test_could_not_validate_creates_exception_with_message()
    {
        $message = 'Invalid code provided';
        $exception = TwoFactorException::couldNotValidate($message);

        $this->assertInstanceOf(TwoFactorException::class, $exception);
        $this->assertEquals('Error: ' . $message, $exception->getMessage());
    }

    public function test_could_not_validate_with_empty_message()
    {
        $exception = TwoFactorException::couldNotValidate('');

        $this->assertInstanceOf(TwoFactorException::class, $exception);
        $this->assertEquals('Error: ', $exception->getMessage());
    }

    public function test_could_not_validate_with_special_characters()
    {
        $message = 'Code "123456" expired at 12:30';
        $exception = TwoFactorException::couldNotValidate($message);

        $this->assertInstanceOf(TwoFactorException::class, $exception);
        $this->assertEquals('Error: ' . $message, $exception->getMessage());
    }

    public function test_exception_extends_personal_drive_exception()
    {
        $exception = TwoFactorException::couldNotValidate('test');

        $this->assertInstanceOf(\App\Exceptions\PersonalDriveExceptions\PersonalDriveException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function test_exception_can_be_thrown_and_caught()
    {
        $this->expectException(TwoFactorException::class);
        $this->expectExceptionMessage('Error: test message');

        throw TwoFactorException::couldNotValidate('test message');
    }
}
