<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\FileMoveException;
use Tests\TestCase;

class MoveFileExceptionTest extends TestCase
{
    public function test_noValidFiles_method_returns_correct_message()
    {
        $exception = FileMoveException::noValidFiles();
        $this->assertInstanceOf(FileMoveException::class, $exception);
        $this->assertEquals('Could not find any valid files to move', $exception->getMessage());
    }

    public function test_invalidDestinationPath_method_returns_correct_message()
    {
        $exception = FileMoveException::invalidDestinationPath();
        $this->assertInstanceOf(FileMoveException::class, $exception);
        $this->assertEquals('Destination path is invalid', $exception->getMessage());
    }
}
