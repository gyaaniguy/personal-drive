<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\FileRenameException;
use Tests\TestCase;

class FileRenameExceptionTest extends TestCase
{
    public function test_couldNotRename_method_returns_correct_message()
    {
        $exception = FileRenameException::couldNotRename();
        $this->assertInstanceOf(FileRenameException::class, $exception);
        $this->assertEquals('Could not rename file. File with same name exists', $exception->getMessage());
    }

    public function test_couldNotUpdateIndex_method_returns_correct_message()
    {
        $exception = FileRenameException::couldNotUpdateIndex();
        $this->assertInstanceOf(FileRenameException::class, $exception);
        $this->assertEquals('Error! File renamed. But index not updated', $exception->getMessage());
    }
}
