<?php

namespace Feature\Services;

use App\Models\Setting;
use App\Services\FileOperationsService;
use Tests\Helpers\SetupSite;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use App\Services\UploadService;
use App\Services\LPathService;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;
use Symfony\Component\Finder\SplFileInfo;
use Tests\TestCase;

class UploadServiceFeatureTest extends TestCase
{
    use RefreshDatabase;
    use SetupSite;

    private $uploadService;
    private $filesystem;
    private $pathService;
    private $statsService;
    private $thumbService;
    private $tempRootDir;
    private $targetDir;

    public function testSyncFileToStorageMovesFile()
    {
        $this->filesystem->deleteDirectory($this->targetDir);
        mkdir($this->targetDir . '/sub', 0777, true);
        mkdir($this->tempRootDir . '/sub', 0777, true);


        $filePath = $this->tempRootDir . '/sub/test.txt';
        file_put_contents($filePath, 'dummy');

//        $this->pathService->shouldReceive('getTempStorageDirPath')->andReturn($this->tempRootDir);
//        $this->pathService->shouldReceive('getStorageFolderPath')->andReturn($this->targetDir);
        $this->statsService->shouldReceive('getFileItemDetails')->andReturn([
            'filename' => 'test.txt', 'public_path' => 'upload-storage', 'private_path' => 'upload-storage',
            'file_type' => 'text', 'is_dir' => '0', 'size' => '11', 'user_id' => 1,
        ]);
        $this->statsService->shouldReceive('updateFileStats')->andReturnNull();
        $this->thumbService->shouldReceive('genThumbnailsForFileIds')->andReturn(1);


        $this->uploadService->syncTempToStorage();
//        $this->uploadService->syncFileToStorage($file, $this->tempRootDir, $this->targetDir);

        $this->assertFileExists($this->targetDir . '/sub/test.txt');
        $this->assertFileDoesNotExist($filePath);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeUser();
        $this->filesystem = new Filesystem();

        $this->pathService = app(LPathService::class);

        $this->statsService = \Mockery::mock(LocalFileStatsService::class);
        $this->thumbService = \Mockery::mock(ThumbnailService::class);
        $this->fileOperationsService = app(FileOperationsService::class);
        $this->uploadService = new UploadService(
            $this->pathService,
            $this->statsService,
            $this->thumbService,
            $this->filesystem
        );
        $targetDir = sys_get_temp_dir() . '/upload-storage';
        Setting::updateSetting('storage_path', $targetDir);

        $this->tempRootDir  = $this->uploadService->setTempStorageDirFull();
        $this->targetDir = $this->pathService->getStorageFolderPath();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
        $this->filesystem->deleteDirectory($this->tempRootDir);
        $this->filesystem->deleteDirectory($this->targetDir);
    }
}
