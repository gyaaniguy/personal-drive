<?php

namespace Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use App\Services\PathService;
use Illuminate\Testing\TestResponse;
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
        $response->assertSessionHas('message', 'Could not find any valid files to move');
    }


    public function test_move_folders_exists_fail()
    {
        $testPath = $this->setupUploadBeforeMove();
        $firstFile = LocalFile::where('filename', 'bar')->where('public_path', '')->first();

        $response = $this->postMoveFiles([$firstFile->id], 'foo');
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Could not move: Same name Directory exists');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/1.txt');
        Storage::disk('local')->assertMissing($this->storageFilesUUID . $testPath . 'foo/bar/1.txt');
    }

    public function test_move_file_success()
    {
        $testPath = $this->setupUploadBeforeMove();
        $firstFile = LocalFile::where('filename', '1.txt')->where('public_path', 'bar')->first();

        $response = $this->postMoveFiles([$firstFile->id], 'foo');
        $response->assertSessionHas('status', true);
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/foo/1.txt');
        Storage::disk('local')->assertMissing($this->storageFilesUUID . $testPath . '/bar/1.txt');
    }

    public function test_move_directory_success()
    {
        $testPath = $this->setupUploadBeforeMove();
        $firstFile = LocalFile::where('filename', 'foo')->where('is_dir', '1')->first();

        $response = $this->postMoveFiles([$firstFile->id], 'bar');
        $response->assertSessionHas('status', true);

        Storage::disk('local')->assertMissing($this->storageFilesUUID . $testPath . '/foo/1.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/1.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/foo/ace.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/foo/bar/1.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/foo/bar/2.txt');
    }

    public function test_move_multiple_success()
    {
        $testPath = $this->setupUploadBeforeMove();
        $firstFile = LocalFile::where('filename', 'ace.txt')->first();
        $secondFile = LocalFile::where('filename', 'bar')->where('public_path', 'foo')->first();

        $response = $this->postMoveFiles([$firstFile->id, $secondFile->id], 'bar');
        $response->assertSessionHas('status', true);

        Storage::disk('local')->assertMissing($this->storageFilesUUID . $testPath . '/foo/ace.txt');
        Storage::disk('local')->assertMissing($this->storageFilesUUID . $testPath . '/foo/bar/1.txt');
        Storage::disk('local')->assertMissing($this->storageFilesUUID . $testPath . '/foo/bar/2.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/1.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/ace.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/bar/1.txt');
        Storage::disk('local')->assertExists($this->storageFilesUUID . $testPath . '/bar/bar/2.txt');
    }

    public function postMoveFiles(array $fileIds, string $path): TestResponse
    {
        return $this->post(route('drive.move-files'), [
            '_token' => csrf_token(),
            'fileList' => $fileIds,
            'path' => $path
        ]);
    }

    public function setupUploadBeforeMove(): string
    {
        $testPath = '';
        $fileNames = [
            'bar/1.txt', 'foo/ace.txt', 'foo/b.txt', 'foo/bar/1.txt', 'foo/bar/2.txt',
        ];

        $this->uploadMultipleFiles($testPath, $fileNames);
        Storage::disk('local')->assertExists($this->storageFilesUUID.$testPath.'/bar/1.txt');
        return $testPath;
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
