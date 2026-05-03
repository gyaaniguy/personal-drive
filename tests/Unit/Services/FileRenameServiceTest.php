<?php

namespace Tests\Unit\Services;

use App\Exceptions\PersonalDriveExceptions\FileRenameException;
use App\Models\LocalFile;
use App\Services\FileOperationsService;
use App\Services\FileRenameService;
use App\Services\PathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FileRenameServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FileRenameService $renameService;
    protected $fileOpsMock;
    protected $pathServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileOpsMock = Mockery::mock(FileOperationsService::class);
        $this->pathServiceMock = Mockery::mock(PathService::class);
        $this->app->instance(FileOperationsService::class, $this->fileOpsMock);
        $this->app->instance(PathService::class, $this->pathServiceMock);
        $this->renameService = new FileRenameService(
            $this->pathServiceMock,
            $this->fileOpsMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_rename_file_throws_when_dest_exists(): void
    {
        $file = LocalFile::factory()->create([
            'filename' => 'old.txt',
            'public_path' => 'docs',
            'is_dir' => false,
        ]);

        $this->fileOpsMock->shouldReceive('fileExists')
            ->once()
            ->andReturn(true);

        $this->expectException(FileRenameException::class);
        $this->expectExceptionMessage('Could not rename file. File with same name exists');

        $this->renameService->renameFile($file, 'new.txt');
    }

    public function test_rename_file_moves_and_updates_filename(): void
    {
        $file = LocalFile::factory()->create([
            'filename' => 'old.txt',
            'public_path' => 'docs',
            'is_dir' => false,
        ]);

        $oldPath = $file->getFullPathFromContentRoot();
        $newPath = $file->getFullPathFromContentRoot('new.txt');

        $this->fileOpsMock->shouldReceive('fileExists')
            ->once()
            ->with($newPath)
            ->andReturn(false);

        $this->fileOpsMock->shouldReceive('move')
            ->once()
            ->with($oldPath, $newPath);

        $this->renameService->renameFile($file, 'new.txt');

        $this->assertEquals('new.txt', $file->fresh()->filename);
    }

    public function test_rename_file_throws_when_db_update_fails(): void
    {
        $file = LocalFile::factory()->make([
            'filename' => 'old.txt',
            'public_path' => 'docs',
            'is_dir' => false,
        ]);
        // exists=false makes update() return false
        $file->exists = false;

        $this->fileOpsMock->shouldReceive('fileExists')->andReturn(false);
        $this->fileOpsMock->shouldReceive('move');

        $this->expectException(FileRenameException::class);
        $this->expectExceptionMessage('Error! File renamed. But index not updated');

        $this->renameService->renameFile($file, 'new.txt');
    }

    public function test_update_dir_children_recursively_updates_child_paths(): void
    {
        $parentDir = LocalFile::factory()->create([
            'filename' => 'old_folder',
            'public_path' => 'parent',
            'is_dir' => true,
        ]);

        $childFile = LocalFile::factory()->create([
            'filename' => 'nested_file.txt',
            'public_path' => 'parent/old_folder/sub',
            'is_dir' => false,
        ]);

        $deepChild = LocalFile::factory()->create([
            'filename' => 'deep.txt',
            'public_path' => 'parent/old_folder/sub/deep',
            'is_dir' => false,
        ]);

        $this->pathServiceMock->shouldReceive('genPrivatePathFromPublic')
            ->with('parent/new_folder/sub')
            ->andReturn('/tmp/parent/new_folder/sub');

        $this->pathServiceMock->shouldReceive('genPrivatePathFromPublic')
            ->with('parent/new_folder/sub/deep')
            ->andReturn('/tmp/parent/new_folder/sub/deep');

        $this->renameService->updateDirChildrenRecursively($parentDir, 'new_folder');

        // Verify child public_path was updated with correct concatenation
        $childFile->refresh();
        $this->assertEquals('parent/new_folder/sub', $childFile->public_path);
        $this->assertEquals('/tmp/parent/new_folder/sub', $childFile->private_path);

        // Verify deep child also updated
        $deepChild->refresh();
        $this->assertEquals('parent/new_folder/sub/deep', $deepChild->public_path);
        $this->assertEquals('/tmp/parent/new_folder/sub/deep', $deepChild->private_path);
    }

    public function test_update_dir_children_does_not_touch_unrelated_files(): void
    {
        $parentDir = LocalFile::factory()->create([
            'filename' => 'my_folder',
            'public_path' => '',
            'is_dir' => true,
        ]);

        $childFile = LocalFile::factory()->create([
            'filename' => 'inside.txt',
            'public_path' => 'my_folder',
            'is_dir' => false,
        ]);

        $unrelatedFile = LocalFile::factory()->create([
            'filename' => 'other.txt',
            'public_path' => 'other_folder',
            'private_path' => '/tmp/other_folder',
            'is_dir' => false,
        ]);

        $this->pathServiceMock->shouldReceive('genPrivatePathFromPublic')
            ->with('renamed_folder')
            ->andReturn('/tmp/renamed_folder');

        $this->renameService->updateDirChildrenRecursively($parentDir, 'renamed_folder');

        $childFile->refresh();
        $this->assertEquals('renamed_folder', $childFile->public_path);
        $this->assertEquals('/tmp/renamed_folder', $childFile->private_path);

        $unrelatedFile->refresh();
        $this->assertEquals('other_folder', $unrelatedFile->public_path);
        $this->assertEquals('/tmp/other_folder', $unrelatedFile->private_path);
    }

    public function test_rename_folder_calls_update_dir_children(): void
    {
        $folder = LocalFile::factory()->create([
            'filename' => 'old_folder',
            'public_path' => 'docs',
            'is_dir' => true,
        ]);

        // Create a child so updateDirChildrenRecursively has something to process
        LocalFile::factory()->create([
            'filename' => 'child.txt',
            'public_path' => 'docs/old_folder',
            'is_dir' => false,
        ]);

        $this->fileOpsMock->shouldReceive('fileExists')->andReturn(false);
        $this->fileOpsMock->shouldReceive('move');

        $this->pathServiceMock->shouldReceive('genPrivatePathFromPublic')
            ->with('docs/new_folder')
            ->andReturn('/tmp/docs/new_folder');

        $this->renameService->renameFile($folder, 'new_folder');

        $this->assertEquals('new_folder', $folder->fresh()->filename);

        // Verify child path was updated (proves updateDirChildrenRecursively ran)
        $child = LocalFile::where('filename', 'child.txt')->first();
        $this->assertEquals('docs/new_folder', $child->public_path);
        $this->assertEquals('/tmp/docs/new_folder', $child->private_path);
    }

    public function test_rename_file_with_no_dir_skips_children_update(): void
    {
        $file = LocalFile::factory()->create([
            'filename' => 'plain.txt',
            'public_path' => 'docs',
            'is_dir' => false,
        ]);

        $this->fileOpsMock->shouldReceive('fileExists')->andReturn(false);
        $this->fileOpsMock->shouldReceive('move');

        // pathService should NOT be called since is_dir=false
        $this->pathServiceMock->shouldNotReceive('genPrivatePathFromPublic');

        $this->renameService->renameFile($file, 'renamed.txt');

        $this->assertEquals('renamed.txt', $file->fresh()->filename);
    }
}
