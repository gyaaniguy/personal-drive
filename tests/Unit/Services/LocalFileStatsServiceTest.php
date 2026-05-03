<?php

namespace Tests\Unit\Services;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Models\LocalFile;
use App\Models\User;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Mockery;
use SplFileInfo;
use Tests\TestCase;

class LocalFileStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LocalFileStatsService $service;
    protected $pathServiceMock;
    protected string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pathServiceMock = Mockery::mock(PathService::class);
        $this->app->instance(PathService::class, $this->pathServiceMock);
        $this->service = new LocalFileStatsService($this->pathServiceMock);

        // Create a temp directory for real file operations
        $this->tmpDir = sys_get_temp_dir() . '/localfilestats_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tmpDir)) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->tmpDir);
        }
        Mockery::close();
        parent::tearDown();
    }

    // ── getSplFileStats: kills mutation line 25 (ConcatRemoveRight), line 30 (TernaryNegated) ──

    public function test_getSplFileStats_returns_correct_filename_from_concat(): void
    {
        // Create a real file so SplFileInfo works
        $filePath = $this->tmpDir . '/testfile.txt';
        file_put_contents($filePath, 'hello');

        $file = new SplFileInfo($filePath);
        $user = User::factory()->create();

        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->getSplFileStats(
            'testfile.txt',
            false,
            'public',
            $this->tmpDir . '/',
            $file
        );

        // filename must be exactly the itemName passed in — proves concat didn't lose right side
        $this->assertEquals('testfile.txt', $result['filename']);
        $this->assertEquals('public', $result['public_path']);
        $this->assertEquals($this->tmpDir . '/', $result['private_path']);
    }

    public function test_getSplFileStats_is_dir_true_returns_1(): void
    {
        $dirPath = $this->tmpDir . '/subdir';
        mkdir($dirPath, 0777, true);

        $file = new SplFileInfo($dirPath);
        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->getSplFileStats(
            'subdir',
            true,
            '',
            $this->tmpDir . '/',
            $file
        );

        // Kills TernaryNegated mutation: is_dir must be 1 when true
        $this->assertEquals(1, $result['is_dir']);
        // Size should be empty string for directories
        $this->assertEquals('', $result['size']);
    }

    public function test_getSplFileStats_is_dir_false_returns_0(): void
    {
        $filePath = $this->tmpDir . '/somefile.txt';
        file_put_contents($filePath, 'content');

        $file = new SplFileInfo($filePath);
        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->getSplFileStats(
            'somefile.txt',
            false,
            '',
            $this->tmpDir . '/',
            $file
        );

        // Kills TernaryNegated mutation: is_dir must be 0 when false
        $this->assertEquals(0, $result['is_dir']);
        $this->assertEquals(strlen('content'), $result['size']);
    }

    // ── addItemPathStat: kills mutation line 25 (ConcatRemoveRight), line 28 (RemoveMethodCall) ──

    public function test_addItemPathStat_creates_local_file_with_concatenated_path(): void
    {
        $filePath = $this->tmpDir . '/myfile.txt';
        file_put_contents($filePath, 'data');

        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        // The method creates SplFileInfo from $privatePath . $itemName
        // If concat right side is removed, SplFileInfo points to just $privatePath (a dir)
        // which would cause wrong size/is_dir or crash
        $this->service->addItemPathStat('myfile.txt', $this->tmpDir . '/', 'pub', false);

        $localFile = LocalFile::first();
        $this->assertNotNull($localFile);
        // filename must be exactly the item name — confirms create() was called with correct data
        $this->assertEquals('myfile.txt', $localFile->filename);
        $this->assertEquals(0, $localFile->is_dir);
        $this->assertEquals('pub', $localFile->public_path);

        // Kills ConcatRemoveRight (line 25): if $privatePath . $itemName loses right side,
        // SplFileInfo points to directory → size would be '' instead of actual file size
        $this->assertEquals(strlen('data'), $localFile->size);
    }

    public function test_addItemPathStat_calls_create_and_persists_to_db(): void
    {
        $filePath = $this->tmpDir . '/persist.txt';
        file_put_contents($filePath, 'abc');

        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $this->assertEquals(0, LocalFile::count());

        $this->service->addItemPathStat('persist.txt', $this->tmpDir . '/', 'pub', false);

        // Kills RemoveMethodCall on line 28: create() must have been called
        $this->assertEquals(1, LocalFile::count());
        $this->assertEquals('persist.txt', LocalFile::first()->filename);
    }

    public function test_addItemPath_stat_throws_upload_exception_on_db_failure(): void
    {
        $filePath = $this->tmpDir . '/fail.txt';
        file_put_contents($filePath, 'x');

        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        // Force a DB failure by making the table not exist or causing constraint violation
        // Use a spy: mock the LocalFile model's create method via the Eloquent facade approach
        // Since this is a static call, we'll use a different strategy:
        // Create a partial mock of the service that makes getSplFileStats return invalid data
        $serviceMock = Mockery::mock(LocalFileStatsService::class, [$this->pathServiceMock])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $serviceMock->shouldReceive('getSplFileStats')
            ->andReturn(['invalid' => 'data', 'filename' => str_repeat('a', 300)]);

        // This will cause a QueryException because fillable fields are missing
        $this->expectException(UploadFileException::class);
        $this->expectExceptionMessage('Could not create new file');

        $serviceMock->addItemPathStat('fail.txt', $this->tmpDir . '/', 'pub', false);
    }

    public function test_addItemPath_stat_throws_folder_message_for_dir(): void
    {
        $dirPath = $this->tmpDir . '/myfolder';
        mkdir($dirPath, 0777, true);

        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $serviceMock = Mockery::mock(LocalFileStatsService::class, [$this->pathServiceMock])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $serviceMock->shouldReceive('getSplFileStats')
            ->andReturn(['invalid' => 'data', 'filename' => str_repeat('a', 300)]);

        $this->expectException(UploadFileException::class);
        $this->expectExceptionMessage('Could not create new folder');

        $serviceMock->addItemPathStat('myfolder', $this->tmpDir . '/', 'pub', true);
    }

    // ── generateStats: kills mutations line 84 (DecrementInteger, IncrementInteger, RemoveEarlyReturn) ──

    public function test_generateStats_returns_zero_when_no_private_path(): void
    {
        $this->pathServiceMock->shouldReceive('genPrivatePathFromPublic')
            ->with('')
            ->andReturn('');

        $result = $this->service->generateStats('');

        // Kills DecrementInteger: return 0 → return -1
        // Kills IncrementInteger: return 0 → return 1
        // Kills RemoveEarlyReturn: return 0 removed → falls through to populateLocalFileWithStats('')
        $this->assertSame(0, $result);
    }

    // ── getFileItemDetails: kills mutations line 84 (DecrementInteger, IncrementInteger, RemoveEarlyReturn) ──

    public function test_getFileItemDetails_computes_public_path_using_root_offset(): void
    {
        // Use the tmpDir as the storage root to avoid permission issues
        $storagePath = $this->tmpDir . '/storage_root';
        mkdir($storagePath, 0777, true);

        $privatePath = $storagePath . '/subdir/deep';
        mkdir($privatePath, 0777, true);

        $realFile = $privatePath . '/doc.txt';
        file_put_contents($realFile, 'content');

        $item = new SplFileInfo($realFile);

        // getStorageFolderPath returns the root, so rootPathLen = strlen(that) + 1
        // publicPath = substr($privatePath, rootPathLen) = 'subdir/deep'
        $this->pathServiceMock->shouldReceive('getStorageFolderPath')
            ->andReturn($storagePath);

        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->getFileItemDetails($item);

        // Kills DecrementInteger (+0): would give 'ubdir/deep' instead of 'subdir/deep'
        // Kills IncrementInteger (+2): would give 'bdir/deep' instead of 'subdir/deep'
        // Kills RemoveEarlyReturn (strlen removed): would give wrong offset
        $this->assertEquals('subdir/deep', $result['public_path']);
        $this->assertEquals('doc.txt', $result['filename']);
        $this->assertEquals($privatePath, $result['private_path']);
    }

    public function test_getFileItem_details_with_top_level_file(): void
    {
        $storagePath = $this->tmpDir . '/storage_root';
        mkdir($storagePath, 0777, true);

        $realFile = $storagePath . '/toplevel.txt';
        file_put_contents($realFile, 'top');

        $item = new SplFileInfo($realFile);

        $this->pathServiceMock->shouldReceive('getStorageFolderPath')
            ->andReturn($storagePath);

        $user = User::factory()->create();
        Auth::shouldReceive('user')->andReturn($user);

        $result = $this->service->getFileItemDetails($item);

        // Top-level file: private_path == storagePath, substr from rootPathLen gives ''
        $this->assertEquals('', $result['public_path']);
        $this->assertEquals('toplevel.txt', $result['filename']);
    }

    // ── updateFileStats: kills mutations lines 121-125 (RemoveMethodCall, RemoveArrayItem x3) ──

    public function test_updateFileStats_calls_update_with_all_three_fields(): void
    {
        $filePath = $this->tmpDir . '/update_me.txt';
        file_put_contents($filePath, 'updated content here');

        $file = new SplFileInfo($filePath);

        // Use a partial mock to spy on update() call
        $localFile = $this->createPartialMock(LocalFile::class, ['update']);
        $localFile->exists = true;

        $capturedArgs = null;
        $localFile->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $args) use (&$capturedArgs) {
                $capturedArgs = $args;
                return true;
            });

        $this->service->updateFileStats($localFile, $file);

        // Kills RemoveMethodCall (line 121): update() must be called exactly once
        $this->assertNotNull($capturedArgs, 'update() was not called');

        // Kills RemoveArrayItem 'size' (line 123): size key must exist with correct value
        $this->assertArrayHasKey('size', $capturedArgs);
        $this->assertEquals(strlen('updated content here'), $capturedArgs['size']);

        // Kills RemoveArrayItem 'is_dir' (line 124): is_dir key must exist with false
        $this->assertArrayHasKey('is_dir', $capturedArgs);
        $this->assertFalse($capturedArgs['is_dir']);

        // Kills RemoveArrayItem 'file_type' (line 125): file_type key must exist
        $this->assertArrayHasKey('file_type', $capturedArgs);
        $this->assertEquals('text', $capturedArgs['file_type']);
    }

    public function test_updateFileStats_sets_correct_values_for_directory(): void
    {
        $dirPath = $this->tmpDir . '/mydir';
        mkdir($dirPath, 0777, true);

        $file = new SplFileInfo($dirPath);

        $localFile = $this->createPartialMock(LocalFile::class, ['update']);
        $localFile->exists = true;

        $capturedArgs = null;
        $localFile->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $args) use (&$capturedArgs) {
                $capturedArgs = $args;
                return true;
            });

        $this->service->updateFileStats($localFile, $file);

        $this->assertNotNull($capturedArgs);
        $this->assertArrayHasKey('is_dir', $capturedArgs);
        $this->assertTrue($capturedArgs['is_dir']);
        $this->assertArrayHasKey('file_type', $capturedArgs);
        $this->assertEquals('folder', $capturedArgs['file_type']);
    }

    public function test_updateFileStats_with_pdf_file(): void
    {
        $filePath = $this->tmpDir . '/document.pdf';
        // Minimal valid PDF bytes for mime detection
        file_put_contents($filePath, "%PDF-1.4\n%fake pdf content");

        $file = new SplFileInfo($filePath);

        $localFile = $this->createPartialMock(LocalFile::class, ['update']);
        $localFile->exists = true;

        $capturedArgs = null;
        $localFile->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $args) use (&$capturedArgs) {
                $capturedArgs = $args;
                return true;
            });

        $this->service->updateFileStats($localFile, $file);

        $this->assertNotNull($capturedArgs);
        $this->assertArrayHasKey('file_type', $capturedArgs);
        $this->assertContains($capturedArgs['file_type'], ['pdf', 'application']);
    }
}
