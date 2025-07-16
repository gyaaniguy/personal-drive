<?php

namespace Tests\Unit\Services;

use App\Exceptions\PersonalDriveExceptions\MoveFileException;
use App\Helpers\FileOperationsHelper;
use App\Models\LocalFile;
use App\Models\User;
use App\Services\FileMoveService;
use App\Services\LocalFileStatsService;
use App\Services\LPathService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FileMoveServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $lPathService;
    protected $localFileStatsService;
    protected $fileOperationsHelper;
    protected $fileMoveService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lPathService = Mockery::mock(LPathService::class);
        $this->localFileStatsService = Mockery::mock(LocalFileStatsService::class);
        $this->fileOperationsHelper = Mockery::mock(FileOperationsHelper::class);
        $this->fileMoveService = new FileMoveService(
            $this->lPathService,
            $this->localFileStatsService,
            $this->fileOperationsHelper
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_move_files_throws_exception_if_no_valid_files()
    {
        $this->expectException(MoveFileException::class);
        $this->expectExceptionMessage('Could not find any valid files to move');

        $this->fileMoveService->moveFiles(['non-existent-id'], '/some/path');
    }

    public function test_move_files_throws_exception_if_invalid_destination_path()
    {
        $this->expectException(MoveFileException::class);
        $this->expectExceptionMessage('Destination path is invalid');

        $user = User::factory()->create();
        $localFile = LocalFile::factory()->create(['user_id' => $user->id]);

        $this->lPathService->shouldReceive('cleanDrivePublicPath')
            ->andReturn('invalid/path');
        $this->lPathService->shouldReceive('genPrivatePathFromPublic')
            ->andReturn(false);

        $this->fileMoveService->moveFiles([$localFile->id], '/invalid/path');
    }

}
