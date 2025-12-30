<?php

namespace Tests\Unit\Services;

use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Helpers\DownloadHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\TestCase;

class DownloadServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $downloadService;

    public function testGenerateDownloadPathSingleFile()
    {
        $file = $this->createMock(LocalFile::class);
        $file->is_dir = false;
        $file->method('getPrivatePathNameForFile')->willReturn('/path/to/file');

        $localFiles = new Collection([$file]);

        $result = $this->downloadService->generateDownloadPath($localFiles);

        $this->assertEquals('/path/to/file', $result);
    }

    public function testGenerateDownloadPathSingleDir()
    {
        $file = $this->createMock(LocalFile::class);
        $file->method('getPrivatePathNameForFile')->willReturn('/path/to/aDir');
        $file->method('__get')->with('is_dir')->willReturn(true);


        $localFiles = new Collection([$file]);
        $result = $this->downloadService->generateDownloadPath($localFiles);

        $this->assertStringContainsString('/tmp/personal_drive_', $result);
        $this->assertStringEndsWith('.zip', $result);
    }

    public function testIsSingleFile()
    {
        $file = $this->createMock(LocalFile::class);
        $file->is_dir = false;

        $localFiles = new Collection([$file]);

        $result = $this->downloadService->isSingleFile($localFiles);

        $this->assertTrue($result);
    }

    public function testIsNotSingleFile()
    {
        $file1 = $this->createMock(LocalFile::class);
        $file1->is_dir = false;

        $file2 = $this->createMock(LocalFile::class);
        $file2->is_dir = false;

        $localFiles = new Collection([$file1, $file2]);

        $result = $this->downloadService->isSingleFile($localFiles);

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->downloadService = new DownloadService();
    }
}
