<?php

namespace Tests\Unit\Services;

use App\Models\LocalFile;
use App\Services\FileDeleteService;
use PHPUnit\Framework\TestCase;

class FileDeleteServiceTest extends TestCase
{
    protected FileDeleteService $fileDeleteService;
    protected string $tempDir;

    public function testIsDeletableDirectory()
    {
        $file = $this->createMock(LocalFile::class);
        $file->method('__get')->with('is_dir')->willReturn(1);

        $result = $this->fileDeleteService->isDeletableDirectory($file, $this->tempDir);

        $this->assertTrue($result);
    }

    public function testIsDirSubDirOfStorage()
    {
        $result = $this->fileDeleteService->isDirSubDirOfStorage('/path/to/dir', '/path/to');
        $this->assertNotFalse($result);
    }

    public function testIsDeletableFile()
    {
        $file = $this->createMock(LocalFile::class);
        $file->method('__get')->with('is_dir')->willReturn(0);

        $result = $this->fileDeleteService->isDeletableFile($file);

        $this->assertTrue($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileDeleteService = new FileDeleteService();
        $this->tempDir = sys_get_temp_dir() . '/testDir';
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }

        parent::tearDown();
    }
}
