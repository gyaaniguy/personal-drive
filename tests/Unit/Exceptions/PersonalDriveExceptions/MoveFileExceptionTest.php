<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\MoveFileException;
use Tests\TestCase;

class MoveFileExceptionTest extends TestCase
{
    public function test_noValidFiles_method_returns_correct_message()
    {
        $exception = MoveFileException::noValidFiles();
        $this->assertInstanceOf(MoveFileException::class, $exception);
        $this->assertEquals('Could not find any valid files to move', $exception->getMessage());
    }

    public function test_invalidDestinationPath_method_returns_correct_message()
    {
        $exception = MoveFileException::invalidDestinationPath();
        $this->assertInstanceOf(MoveFileException::class, $exception);
        $this->assertEquals('Destination path is invalid', $exception->getMessage());
    }
}
