<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\Favorite;
use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use App\Services\LocalFileStatsService;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Feature\BaseFeatureTest;
use UnexpectedValueException;

class ReSyncControllerTest extends BaseFeatureTest
{
    public function test_resync_files()
    {
        $testPath = '';
        $fileNames = [
            'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt', 'foo/bar/2.txt',
        ];

        $this->uploadMultipleFiles($testPath, $fileNames);
        $allFiles = LocalFile::all();
        $this->assertCount(8, $allFiles);
        LocalFile::clearTable();
        $allFiles = LocalFile::all();
        $this->assertCount(0, $allFiles);

        $response = $this->post(
            route('resync'),
            [
                '_token' => csrf_token(),
            ]
        );
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Sync successful. Found : 8 files');
        $allFiles = LocalFile::all();

        $this->assertCount(8, $allFiles);
        $files = $this->getFilesForFileNames($fileNames);
        $this->assertFilesExist($files, $testPath);
    }

    public function test_resync_preserves_favorites_by_repointing_to_new_rows(): void
    {
        $this->uploadMultipleFiles('', ['bar/1.txt', 'foo/ace.txt']);
        $favFile = LocalFile::where('filename', 'ace.txt')->firstOrFail();
        Favorite::create(['user_id' => auth()->id(), 'local_file_id' => $favFile->id]);

        $this->post(route('resync'), ['_token' => csrf_token()])
            ->assertSessionHas('status', true);

        $newFavFile = LocalFile::where('filename', 'ace.txt')->firstOrFail();
        $this->assertDatabaseHas('favorites', ['local_file_id' => $newFavFile->id]);
        $this->assertDatabaseMissing('favorites', ['local_file_id' => $favFile->id]);
    }

    public function test_no_files_sync()
    {
        $allFiles = LocalFile::all();
        $this->assertCount(0, $allFiles);

        $response = $this->post(
            route('resync'),
            [
                '_token' => csrf_token(),
            ]
        );
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'No files found');

        $allFiles = LocalFile::all();
        $this->assertCount(0, $allFiles);
    }

    public function test_resync_preserves_index_and_shares_when_scan_fails(): void
    {
        $this->uploadFile('', 'preserved.txt');
        $file = LocalFile::firstOrFail();
        $share = Share::factory()->create();
        SharedFile::factory()->create([
            'share_id' => $share->id,
            'file_id' => $file->id,
        ]);

        $this->mock(LocalFileStatsService::class)
            ->shouldReceive('generateStats')
            ->once()
            ->andThrow(new UnexpectedValueException('unreadable directory'));

        $response = $this->post(route('resync'), ['_token' => csrf_token()]);

        $response->assertSessionHas('status', false);
        $this->assertDatabaseHas('local_files', ['id' => $file->id]);
        $this->assertDatabaseHas('shares', ['id' => $share->id]);
        $this->assertDatabaseHas('shared_files', [
            'share_id' => $share->id,
            'file_id' => $file->id,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
