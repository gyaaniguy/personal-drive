<?php

use Illuminate\Support\Facades\Session;
use PHPUnit\Framework\TestCase;
use App\Services\UploadService;
use Illuminate\Filesystem\Filesystem;
use App\Services\LPathService;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;

class UploadServiceTest extends TestCase
{
    private $filesystem;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filesystem = Mockery::mock(Filesystem::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function makeService(): UploadService
    {
        return new UploadService(
            Mockery::mock(LPathService::class),
            Mockery::mock(LocalFileStatsService::class),
            Mockery::mock(ThumbnailService::class),
            $this->filesystem
        );
    }

    public function testIsFileFolderMisMatchTrue()
    {

        $this->filesystem->shouldReceive('isFile')->with('aFile')->andReturn(true);
        $this->filesystem->shouldReceive('isDirectory')->with('aDir')->andReturn(true);

        $service = $this->makeService();
        $this->assertTrue($service->isFileFolderMisMatch('aFile', 'aDir'));
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

//    public function testGetTempStorageDirFullReturnsEmptyIfUuidMissing()
//    {
//        $sessionMock = Mockery::mock();
//        $sessionMock->shouldReceive('get')->with('temp_replace_dir_uuid')->andReturn(null);
//        Session::swap($sessionMock);
//
//        $service = $this->makeService();
//        $this->assertSame('', $service->getTempStorageDirFull());
//    }

//    public function testGetTempStorageDirFullReturnsCorrectPath()
//    {
//        $uuid = 'abc123';
//        $basePath = '/tmp/storage';
//
//        $sessionMock = Mockery::mock();
//        $sessionMock->shouldReceive('get')->with('temp_replace_dir_uuid')->andReturn($uuid);
//        Session::swap($sessionMock);
//
//        $pathService = Mockery::mock(LPathService::class);
//        $pathService->shouldReceive('getTempStorageDirPath')->andReturn($basePath);
//
//        $service = new UploadService(
//            $pathService,
//            Mockery::mock(LocalFileStatsService::class),
//            Mockery::mock(ThumbnailService::class),
//            $this->filesystem
//        );
//
//        $this->assertSame($basePath . DIRECTORY_SEPARATOR . $uuid, $service->getTempStorageDirFull());
//    }


}
