<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Services\FileOperationsService;
use Mockery;
use Tests\Feature\BaseFeatureTest;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\UploadService;
use App\Services\PathService;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;
use Symfony\Component\Finder\SplFileInfo;

class UploadServiceFeatureTest extends BaseFeatureTest
{
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
        $this->statsService->shouldReceive('getFileItemDetails')->andReturn(
            [
            'filename' => 'test.txt', 'public_path' => 'upload-storage', 'private_path' => 'upload-storage',
            'file_type' => 'text', 'is_dir' => '0', 'size' => '11', 'user_id' => 1,
            ]
        );
        $this->statsService->shouldReceive('updateFileStats')->andReturnNull();
        $this->thumbService->shouldReceive('genThumbnailsForFileIds')->andReturn(1);


        $this->uploadService->syncTempToStorage();
        //        $this->uploadService->syncFileToStorage($file, $this->tempRootDir, $this->targetDir);

        $this->assertFileExists($this->targetDir . '/sub/test.txt');
        $this->assertFileDoesNotExist($filePath);
    }

    public function testSyncFileToStorageReplacesOnlyFirstSourceRootOccurrence()
    {
        $relativePath = ltrim($this->tempRootDir, DS) . DS . 'test.txt';
        $filePath = $this->tempRootDir . DS . $relativePath;
        mkdir(dirname($filePath), 0777, true);
        file_put_contents($filePath, 'dummy');

        $this->statsService->shouldReceive('getFileItemDetails')->andReturn(
            [
            'filename' => 'test.txt', 'public_path' => $relativePath, 'private_path' => dirname($filePath),
            'file_type' => 'text', 'is_dir' => '0', 'size' => '5', 'user_id' => 1,
            ]
        );
        $this->thumbService->shouldReceive('genThumbnailsForFileIds')->andReturn(1);

        $file = new SplFileInfo($filePath, dirname($filePath), basename($filePath));
        $this->uploadService->syncFileToStorage($file, $this->tempRootDir, $this->targetDir, $this->pathService->getRootPathLen());

        $this->assertFileExists($this->targetDir . DS . $relativePath);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeUser();
        $this->filesystem = new Filesystem();

        $this->pathService = app(PathService::class);

        $this->statsService = Mockery::mock(LocalFileStatsService::class);
        $this->thumbService = Mockery::mock(ThumbnailService::class);
        $this->fileOperationsService = app(FileOperationsService::class);
        $this->uploadService = new UploadService(
            $this->pathService,
            $this->statsService,
            $this->fileOperationsService,
            $this->thumbService,
            $this->filesystem
        );
        $targetDir = sys_get_temp_dir() . '/upload-storage';
        Setting::updateStoragePath($targetDir);

        $this->tempRootDir = $this->uploadService->setTempStorageDirAbs();
        $this->targetDir = $this->pathService->getStorageFolderPath();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
        $this->filesystem->deleteDirectory($this->tempRootDir);
        $this->filesystem->deleteDirectory($this->targetDir);
    }
}
