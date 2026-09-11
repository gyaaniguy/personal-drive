<?php

namespace Tests\Unit\Services;

use App\Exceptions\PersonalDriveExceptions\FileMoveException;
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

        // Should return void without throwing - kills line 45 FalseToTrue + RemoveEarlyReturn
        $this->expectNotToPerformAssertions();
        $service->move('src.txt', 'dest.txt');
    }

    public function testMakeFileReturnsFalseWhenMakeFileSystemFails(): void
    {
        $service = $this->createServiceWithNoFilesystem();

        // Should return false - kills line 64 FalseToTrue + RemoveEarlyReturn
        $this->assertFalse($service->makeFile('test.txt'));
    }

    public function testMakeFolderReturnsFalseWhenMakeFileSystemFails(): void
    {
        $service = $this->createServiceWithNoFilesystem();

        // Clear any queries from migration and enable query logging
        \Illuminate\Support\Facades\DB::disableQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();

        // Should return false - kills line 81 FalseToTrue
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

            // Fresh service - makeFileSystem() builds a real local filesystem
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

    public function testMoveWithinRealStorageRootStillWorks(): void
    {
        $root = sys_get_temp_dir() . DS . 'pd_root_' . uniqid();
        mkdir($root . DS . 'sub', 0755, true);

        try {
            file_put_contents($root . DS . 'src.txt', 'payload');
            $service = $this->makeServiceWithStoragePath($root);

            $service->move('src.txt', 'sub/dest.txt');

            $this->assertFileExists($root . DS . 'sub' . DS . 'dest.txt');
            $this->assertSame('payload', file_get_contents($root . DS . 'sub' . DS . 'dest.txt'));
            $this->assertFileDoesNotExist($root . DS . 'src.txt');
        } finally {
            $this->removeDirRecursively($root);
        }
    }

    public function testMoveOntoExistingFileIsRejectedWithoutOverwriting(): void
    {
        $root = sys_get_temp_dir() . DS . 'pd_root_' . uniqid();
        mkdir($root, 0755, true);

        try {
            file_put_contents($root . DS . 'src.txt', 'payload');
            file_put_contents($root . DS . 'dest.txt', 'precious');
            $service = $this->makeServiceWithStoragePath($root);

            try {
                $service->move('src.txt', 'dest.txt');
                $this->fail('Expected FileMoveException to be thrown');
            } catch (FileMoveException $e) {
                $this->assertStringContainsString('exists', $e->getMessage());
            }

            $this->assertSame('precious', file_get_contents($root . DS . 'dest.txt'));
            $this->assertFileExists($root . DS . 'src.txt');
        } finally {
            $this->removeDirRecursively($root);
        }
    }

    public function testMoveIntoSymlinkEscapingStorageRootFailsAndWritesNothingOutside(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink() is not available on this host');
        }
        [$root, $outside] = $this->makeRootWithEscapeSymlink();

        try {
            file_put_contents($root . DS . 'src.txt', 'payload');
            $service = $this->makeServiceWithStoragePath($root);

            try {
                $service->move('src.txt', 'escape/evil.txt');
                $this->fail('Expected FileMoveException to be thrown');
            } catch (FileMoveException $e) {
                $this->assertStringContainsString('invalid', $e->getMessage());
            }

            // Nothing may be written outside the root, and the source stays put.
            $this->assertFileDoesNotExist($outside . DS . 'evil.txt');
            $this->assertFileExists($root . DS . 'src.txt');
        } finally {
            $this->removeDirRecursively($root);
            $this->removeDirRecursively($outside);
        }
    }

    public function testMoveWithSourceTraversingSymlinkEscapingStorageRootFails(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink() is not available on this host');
        }
        [$root, $outside] = $this->makeRootWithEscapeSymlink();

        try {
            file_put_contents($outside . DS . 'outside.txt', 'secret');
            $service = $this->makeServiceWithStoragePath($root);

            try {
                $service->move('escape/outside.txt', 'dest.txt');
                $this->fail('Expected FileMoveException to be thrown');
            } catch (FileMoveException $e) {
                // Expected; the post-conditions below prove nothing escaped.
            }

            // The outside file must remain untouched and nothing lands in the root.
            $this->assertFileExists($outside . DS . 'outside.txt');
            $this->assertFileDoesNotExist($root . DS . 'dest.txt');
        } finally {
            $this->removeDirRecursively($root);
            $this->removeDirRecursively($outside);
        }
    }

    public function testMakeFolderUnderSymlinkEscapingStorageRootFails(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink() is not available on this host');
        }
        [$root, $outside] = $this->makeRootWithEscapeSymlink();

        try {
            $service = $this->makeServiceWithStoragePath($root);

            try {
                $service->makeFolder('escape/evil');
                $this->fail('Expected UploadFileException to be thrown');
            } catch (UploadFileException $e) {
                $this->assertStringContainsString('outside the storage root', $e->getMessage());
            }

            $this->assertDirectoryDoesNotExist($outside . DS . 'evil');
        } finally {
            $this->removeDirRecursively($root);
            $this->removeDirRecursively($outside);
        }
    }

    public function testMakeFileUnderSymlinkEscapingStorageRootFails(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('symlink() is not available on this host');
        }
        [$root, $outside] = $this->makeRootWithEscapeSymlink();

        try {
            $service = $this->makeServiceWithStoragePath($root);

            try {
                $service->makeFile('escape/evil.txt');
                $this->fail('Expected UploadFileException to be thrown');
            } catch (UploadFileException $e) {
                $this->assertStringContainsString('outside the storage root', $e->getMessage());
            }

            $this->assertFileDoesNotExist($outside . DS . 'evil.txt');
        } finally {
            $this->removeDirRecursively($root);
            $this->removeDirRecursively($outside);
        }
    }

    public function testMakeFolderAtNormalPathStillWorksWithRealFilesystem(): void
    {
        $root = sys_get_temp_dir() . DS . 'pd_root_' . uniqid();
        mkdir($root, 0755, true);

        try {
            $service = $this->makeServiceWithStoragePath($root);

            $this->assertTrue($service->makeFolder('normal'));
            $this->assertDirectoryExists($root . DS . 'normal');
        } finally {
            $this->removeDirRecursively($root);
        }
    }

    /**
     * Bootstraps the app with migrations and points the storage_path setting
     * at $storageRoot, then returns a service backed by a real local filesystem.
     */
    private function makeServiceWithStoragePath(string $storageRoot): FileOperationsService
    {
        $this->createServiceWithNoFilesystem();
        Setting::where('key', Setting::$storagePath)
            ->update(['value' => $storageRoot]);

        return new FileOperationsService();
    }

    /**
     * Creates a storage root containing a symlink ("escape") that points at a
     * directory outside the root. Returns [root, outside]. Skips the test when
     * symlinks cannot be created on this host.
     */
    private function makeRootWithEscapeSymlink(): array
    {
        $root = sys_get_temp_dir() . DS . 'pd_root_' . uniqid();
        $outside = sys_get_temp_dir() . DS . 'pd_outside_' . uniqid();
        mkdir($root, 0755, true);
        mkdir($outside, 0755, true);

        if (!@symlink($outside, $root . DS . 'escape')) {
            $this->removeDirRecursively($root);
            $this->removeDirRecursively($outside);
            $this->markTestSkipped(
                'Cannot create symlink on this host (missing privileges or unsupported filesystem)'
            );
        }

        return [$root, $outside];
    }

    private function removeDirRecursively(string $dir): void
    {
        if (!file_exists($dir) && !is_link($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_link($path) || is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                $this->removeDirRecursively($path);
            }
        }
        @rmdir($dir);
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
        parent::setUp();

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
