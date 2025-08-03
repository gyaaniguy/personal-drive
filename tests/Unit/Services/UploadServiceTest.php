<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\TestCase;
use App\Services\UploadService;
use Illuminate\Filesystem\Filesystem;
use App\Services\PathService;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;

class UploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private $filesystem;

    public function testIsFileFolderMisMatchTrue()
    {
        $this->filesystem->shouldReceive('isFile')->with('aFile')->andReturn(true);
        $this->filesystem->shouldReceive('isDirectory')->with('aDir')->andReturn(true);

        $service = $this->makeService();
        $this->assertTrue($service->isFileFolderMisMatch('aFile', 'aDir'));
    }

    private function makeService(): UploadService
    {
        return new UploadService(
            Mockery::mock(PathService::class),
            Mockery::mock(LocalFileStatsService::class),
            Mockery::mock(ThumbnailService::class),
            $this->filesystem
        );
    }

    public function testIsFileFolderMisMatchFalse()
    {
        $this->filesystem->shouldReceive('isFile')->withAnyArgs()->andReturn(true);
        $this->filesystem->shouldReceive('isDirectory')->withAnyArgs()->andReturn(false);
        $service = $this->makeService();
        $this->assertFalse($service->isFileFolderMisMatch('aFile', 'aFile2'));


        $this->filesystem->shouldReceive('isFile')->withAnyArgs()->andReturn(false);
        $this->filesystem->shouldReceive('isDirectory')->withAnyArgs()->andReturn(true);
        $service = $this->makeService();
        $this->assertFalse($service->isFileFolderMisMatch('aFile', 'aFile2'));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = Mockery::mock(Filesystem::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

}
