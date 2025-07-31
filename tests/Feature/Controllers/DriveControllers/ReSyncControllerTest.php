<?php

namespace Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use App\Services\PathService;
use Tests\Feature\BaseFeatureTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;

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


        $response = $this->post(route('resync'),[
            '_token' => csrf_token(),
        ]);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Sync successful. Found : 8 files');
        $allFiles = LocalFile::all();

        $this->assertCount(8, $allFiles);
        $files = $this->getFilesForFileNames($fileNames);
        $this->assertFilesExist($files, $testPath);
    }

    public function test_no_files_sync()
    {
        $allFiles = LocalFile::all();
        $this->assertCount(0, $allFiles);

        $response = $this->post(route('resync'),[
            '_token' => csrf_token(),
        ]);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'No files found');

        $allFiles = LocalFile::all();
        $this->assertCount(0, $allFiles);
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
