<?php

namespace Tests\Unit\Services;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Services\UploadService;
use App\Services\LPathService;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Symfony\Component\Finder\SplFileInfo;

class UploadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_replace_from_temp_returns_false_on_invalid_paths()
    {
        $fs = Mockery::mock(Filesystem::class);
        $fs->shouldReceive('exists')->andReturn(false);

        $pathService = Mockery::mock(LPathService::class);
        $pathService->shouldReceive('getTempStorageDirPath')->andReturn('/tmp/temp');
        $pathService->shouldReceive('getStorageDirPath')->andReturn('/storage');

        $service = new UploadService(
            $pathService,
            Mockery::mock(LocalFileStatsService::class),
            Mockery::mock(ThumbnailService::class),
            $fs
        );

        Session::put('temp_replace_dir_uuid', 'abc');
        $this->assertFalse($service->replaceFromTemp());
    }

    public function test_is_file_folder_mismatch_cases()
    {
        $fs = Mockery::mock(Filesystem::class);
        $fs->shouldReceive('isDirectory')->andReturn(true); // simulate mismatch
        $fs->shouldReceive('isFile')->andReturn(true); // simulate mismatch

        $service = new UploadService(
            Mockery::mock(LPathService::class),
            Mockery::mock(LocalFileStatsService::class),
            Mockery::mock(ThumbnailService::class),
            $fs
        );

        $file = Mockery::mock(SplFileInfo::class);
        $this->assertTrue($service->isFileFolderMisMatch($file, '/some/path'));
    }

    public function test_creates_folder_with_specified_permissions()
    {
        $path =  '/tmp/test/test_folder';
        $fs = Mockery::mock(Filesystem::class);
        $fs->shouldReceive('isDirectory')->andReturn(true); // simulate mismatch
        $fs->shouldReceive('isFile')->andReturn(true); // simulate mismatch

        $service = new UploadService(
            Mockery::mock(LPathService::class),
            Mockery::mock(LocalFileStatsService::class),
            Mockery::mock(ThumbnailService::class),
            $fs
        );

        $result = $service->makeFolder($path, 0750);

        $this->assertTrue($result);
        $this->assertTrue(is_dir($path));
        $this->assertEquals('750', decoct(fileperms($path) & 0777));
    }

    public function test_throws_exception_if_folder_already_exists()
    {
        $this->expectException(UploadFileException::class);
        $this->expectExceptionMessage('Could not create new folder');

        $path =  '/tmp/test/test_folder2';
        $fs = Mockery::mock(Filesystem::class);
        $fs->shouldReceive('isDirectory')->andReturn(true); // simulate mismatch
        $fs->shouldReceive('isFile')->andReturn(true); // simulate mismatch

        $service = new UploadService(
            Mockery::mock(LPathService::class),
            Mockery::mock(LocalFileStatsService::class),
            Mockery::mock(ThumbnailService::class),
            $fs
        );
        $result = $service->makeFolder($path, 0750);
        $this->expectException(UploadFileException::class);

        $result = $service->makeFolder($path, 0750);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $paths = [
            '/tmp/test/test_folder',
            '/tmp/test/test_folder2',
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                exec("rm -rf {$path}");
            } elseif (is_file($path)) {
                unlink($path);
            }
        }

        Mockery::close();
    }


//    public function test_clean_old_temp_files_success()
//    {
//        Session::put('temp_replace_dir_uuid', 'abc');
//
//        $fs = Mockery::mock(Filesystem::class);
//        $fs->shouldReceive('exists')->andReturn(true);
//        $fs->shouldReceive('isDirectory')->andReturn(true);
//
//        $pathService = Mockery::mock(LPathService::class);
//        $pathService->shouldReceive('getTempStorageDirPath')->andReturn('/tmp/temp');
//
//        $upload = new UploadService(
//            $pathService,
//            Mockery::mock(LocalFileStatsService::class),
//            Mockery::mock(ThumbnailService::class),
//            $fs
//        );
//
//        \App\Helpers\UploadFileHelper::shouldReceive('deleteFolder')
//            ->once()
//            ->andReturn(true);
//
//        $this->assertTrue($upload->cleanOldTempFiles());
//    }
}
