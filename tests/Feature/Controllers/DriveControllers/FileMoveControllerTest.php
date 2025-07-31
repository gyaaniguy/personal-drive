<?php

namespace Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use App\Services\PathService;
use Tests\Feature\BaseFeatureTest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;

class FileMoveControllerTest extends BaseFeatureTest
{
    public string $targetDir;
    public mixed $pathService;
    protected mixed $uploadService;

    public function test_move_file_non_existent()
    {
        $testPath = 'bar';
        $response = $this->post(route('drive.move-files'), [
            '_token' => csrf_token(),
            'fileList' => [(string) Str::ulid()],
            'path' => $testPath
        ]);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Error: Could not move files');
    }

    public function test_move_file_success()
    {
        $testPath = '';
        $fileNames = [
            'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt', 'foo/bar/2.txt',
        ];

        $this->uploadMultipleFiles($testPath, $fileNames);
        $firstFile = LocalFile::where('filename', '1.txt')->where('public_path', 'bar')->first();
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/1.txt');

        $response = $this->post(route('drive.move-files'), [
            '_token' => csrf_token(),
            'fileList' => [$firstFile->id],
            'path' => 'foo'
        ]);
        $response->assertSessionHas('status', true);
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/foo/1.txt');
        Storage::disk('local')->assertMissing($this->storageFilesUUID . $testPath . '/bar/1.txt');
    }

    public function test_move_file_directory_success()
    {
        $testPath = '';
        $fileNames = [
            'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt', 'foo/bar/2.txt',
        ];

        $this->uploadMultipleFiles($testPath, $fileNames);
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/1.txt');
        $firstFile = LocalFile::where('filename', 'foo')->where('is_dir', '1')->first();

        $response = $this->post(route('drive.move-files'), [
            '_token' => csrf_token(),
            'fileList' => [$firstFile->id],
            'path' => 'bar'
        ]);
        $response->assertSessionHas('status', true);

        Storage::disk('local')->assertMissing($this->storageFilesUUID . $testPath . '/foo/1.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/1.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/foo/ace.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/foo/bar/1.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/foo/bar/2.txt');
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }

}
