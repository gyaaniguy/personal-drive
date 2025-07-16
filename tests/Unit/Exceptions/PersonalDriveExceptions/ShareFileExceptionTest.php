<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\ShareFileException;
use Tests\TestCase;

class ShareFileExceptionTest extends TestCase
{
    public function test_couldNotShare_method_returns_correct_message()
    {
        $exception = ShareFileException::couldNotShare();
        $this->assertInstanceOf(ShareFileException::class, $exception);
        $this->assertEquals('No valid files to share. Database issue ? Try a Resync', $exception->getMessage());
    }

    public function test_shareWrongPassword_method_returns_correct_message()
    {
        $exception = ShareFileException::shareWrongPassword();
        $this->assertInstanceOf(ShareFileException::class, $exception);
        $this->assertEquals('Wrong password', $exception->getMessage());
    }
}
