<?php

namespace Tests\Unit\Services;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Models\Setting;
use App\Services\FileOperationsService;
use App\Services\PathService;
use Exception;
use League\Flysystem\Filesystem;
use Mockery;
use Tests\TestCase;

class FileOperationsServiceTest extends TestCase
{
    private $fs;
    private FileOperationsService $service;

    public function testMakeFolderThrowsIfExists()
    {
        $this->fs->shouldReceive('directoryExists')
            ->with('dir')->andReturn(true);
        $this->expectException(UploadFileException::class);
        $this->service->makeFolder('dir');
    }

    public function testMakeFolderReturnsFalseOnFailure()
    {
        $this->fs->shouldReceive('directoryExists')
            ->once()->with('dir')->andReturn(false);

        $this->fs->shouldReceive('createDirectory')
            ->once()->with('dir', ['visibility' => 'private'])
            ->andThrow(new Exception());

        $this->assertFalse($this->service->makeFolder('dir'));
    }

    public function testMakeFolderReturnsTrueOnSuccess()
    {
        $this->fs->shouldReceive('directoryExists')->with('dir')->andReturn(false);

        $this->fs->shouldReceive('createDirectory')
            ->with('dir', ['visibility' => 'private']);

        $this->assertTrue($this->service->makeFolder('dir'));
    }

    public function testMakeFolderThrowsCreateFails()
    {
        $this->fs->shouldReceive('directoryExists')->with('dir')->andReturn(false);

        $this->fs->shouldReceive('createDirectory')
            ->with('dir', ['visibility' => 'private'])->andThrow(new Exception());

        $this->assertFalse($this->service->makeFolder('dir'));
    }

    public function testMoveReturnsSilentlyWhenMakeFileSystemFails(): void
    {
        $service = $this->createServiceWithNoFilesystem();

        // Should return void without throwing — kills line 45 FalseToTrue + RemoveEarlyReturn
        $service->move('src.txt', 'dest.txt');
        $this->assertTrue(true);
    }

    public function testMakeFileReturnsFalseWhenMakeFileSystemFails(): void
    {
        $service = $this->createServiceWithNoFilesystem();

        // Should return false — kills line 64 FalseToTrue + RemoveEarlyReturn
        $this->assertFalse($service->makeFile('test.txt'));
    }

    public function testMakeFolderReturnsFalseWhenMakeFileSystemFails(): void
    {
        $service = $this->createServiceWithNoFilesystem();

        // Clear any queries from migration and enable query logging
        \Illuminate\Support\Facades\DB::disableQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();

        // Should return false — kills line 81 FalseToTrue
        $this->assertFalse($service->makeFolder('testdir'));

        // Original code: makeFileSystem() called once → 1 Setting query, then returns early
        // Mutant (RemoveEarlyReturn): makeFileSystem() returns false but continues,
        //   then directoryExists() calls makeFileSystem() again → 2 Setting queries
        // Asserting exactly 1 query kills the RemoveEarlyReturn mutation
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        $this->assertCount(1, $queries);
    }

    public function testMakeFileCreatesEmptyFileContent(): void
    {
        $tempDir = sys_get_temp_dir() . '/pd_test_' . uniqid();
        mkdir($tempDir, 0755, true);

        try {
            $this->createServiceWithNoFilesystem();

            // Update the storage_path setting (migration already inserted it)
            Setting::where('key', Setting::$storagePath)
                ->update(['value' => $tempDir]);

            // Fresh service — makeFileSystem() builds a real local filesystem
            $service = new FileOperationsService();

            $result = $service->makeFile('empty_test.txt');
            $this->assertTrue($result);

            // Verify the file content is exactly empty string
            // This kills line 69 EmptyStringToNotEmpty mutation
            $content = file_get_contents($tempDir . DIRECTORY_SEPARATOR . 'empty_test.txt');
            $this->assertSame('', $content);
        } finally {
            @unlink($tempDir . DIRECTORY_SEPARATOR . 'empty_test.txt');
            @rmdir($tempDir);
        }
    }

    /**
     * Creates a FileOperationsService where makeFileSystem() will return false.
     * Bootstraps a fresh Laravel app with in-memory SQLite and runs migrations.
     * No storage_path setting value exists, so getStoragePath() returns ''.
     */
    private function createServiceWithNoFilesystem(): FileOperationsService
    {
        if (!$this->app) {
            $this->refreshApplication();
        }
        $this->artisan('migrate', ['--force' => true]);

        return new FileOperationsService();
    }

    protected function setUp(): void
    {
        $this->fs = Mockery::mock(Filesystem::class);

        $pathService = Mockery::mock(PathService::class);
        $pathService->shouldReceive('getStorageFolderPath')->andReturn('');

        $this->service = new FileOperationsService($pathService);
        $this->service->setFilesystem($this->fs);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        // Clean up Laravel app if it was bootstrapped by a test
        if ($this->app) {
            // Restore error handlers that Laravel's bootstrapping added
            @restore_error_handler();
            @restore_exception_handler();
        }
    }
}
