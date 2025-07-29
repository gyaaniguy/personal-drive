<?php

namespace Tests\Unit\Exceptions\PersonalDriveExceptions;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use Tests\TestCase;

class FetchFileExceptionTest extends TestCase
{
    public function test_notFoundStream_method_returns_correct_message()
    {
        $exception = FetchFileException::notFoundStream();
        $this->assertInstanceOf(FetchFileException::class, $exception);
        $this->assertEquals('Could not find file to stream', $exception->getMessage());
    }

    public function test_notFoundDownload_method_returns_correct_message()
    {
        $exception = FetchFileException::notFoundDownload();
        $this->assertInstanceOf(FetchFileException::class, $exception);
        $this->assertEquals('Could not find file to download', $exception->getMessage());
    }

    public function test_couldNotZip_method_returns_correct_message()
    {
        $exception = FetchFileException::couldNotZip();
        $this->assertInstanceOf(FetchFileException::class, $exception);
        $this->assertEquals(
            'Could not generate zip to download. Too large or empty folders ?',
            $exception->getMessage()
        );
    }
}
