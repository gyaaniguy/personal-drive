<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use Tests\TestCase;

class UploadFileExceptionTest extends TestCase
{
    public function test_outofmemory_method_returns_correct_message()
    {
        $exception = UploadFileException::outOfMemory();
        $this->assertInstanceOf(UploadFileException::class, $exception);
        $this->assertEquals(
            'Memory exhausted while uploading. Increase PHP allocated memory',
            $exception->getMessage()
        );
    }

    public function test_nonewdir_method_returns_correct_message()
    {
        $exception = UploadFileException::noNewDir('folder');
        $this->assertInstanceOf(UploadFileException::class, $exception);
        $this->assertEquals('Could not create new folder', $exception->getMessage());
    }

    public function test_invalidPath_method_returns_correct_message()
    {
        $exception = UploadFileException::invalidPath();
        $this->assertInstanceOf(UploadFileException::class, $exception);
        $this->assertEquals('The upload path or dir contains invalid characters', $exception->getMessage());
    }
}
