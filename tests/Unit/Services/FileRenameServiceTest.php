<?php

namespace Tests\Unit\Services;

use App\Models\LocalFile;
use App\Services\FileRenameService;
use App\Services\LPathService;
use App\Helpers\FileOperationsHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FileRenameServiceTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\File::shouldReceive('exists')
            ->andReturn(true);
    }

    public function test_renames_regular_file()
    {
        $file = LocalFile::factory()->create([
            'is_dir' => '0',
            'filename' => 'old.txt',
            'private_path' => 'dir',
            'public_path' => '/storage/dir'
        ]);

        $expectedSrc = '/storage/dir/old.txt';
        $expectedDest = '/storage/dir/new.txt';

        $pathService = Mockery::mock(LPathService::class);
        $pathService->shouldReceive('getStorageDirPath')
            ->andReturn('/storage');

        $fileOps = Mockery::mock(FileOperationsHelper::class);
        $fileOps->shouldReceive('move')
            ->once()
            ->with($expectedSrc, $expectedDest);

        $service = new FileRenameService($pathService, $fileOps);
        $service->renameFile($file, 'new.txt');

        $this->assertDatabaseHas('local_files', [
            'id' => $file->id,
            'filename' => 'new.txt',
            'private_path' => 'dir',
            'public_path' => '/storage/dir'
        ]);
    }

    public function test_renames_directory_and_updates_children()
    {
        $file = LocalFile::factory()->create([
            'is_dir' => '1',
            'filename' => 'old',
            'private_path' => 'dir/old',
            'public_path' => '/storage/dir/old'
        ]);

        $expectedSrc = '/storage/dir/old/old';
        $expectedDest = '/storage/dir/old/new';

        $pathService = Mockery::mock(LPathService::class);
        $pathService->shouldReceive('getStorageDirPath')
            ->andReturn('/storage');

        $fileOps = Mockery::mock(FileOperationsHelper::class);
        $fileOps->shouldReceive('move')
            ->once()
            ->with($expectedSrc, $expectedDest);

        $service = Mockery::mock(FileRenameService::class, [$pathService, $fileOps])->makePartial();
        $service->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('updateDirChildrenRecursively')
            ->once()
            ->with($file, 'new');

        $service->renameFile($file, 'new');

        $this->assertDatabaseHas('local_files', [
            'id' => $file->id,
            'filename' => 'new',
            'private_path' => 'dir/old',
            'public_path' => '/storage/dir/old'
        ]);
    }
}
