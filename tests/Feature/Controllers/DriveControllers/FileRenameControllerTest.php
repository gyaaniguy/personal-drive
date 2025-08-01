<?php

namespace Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use App\Services\FileOperationsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class FileRenameControllerTest extends BaseFeatureTest
{
    public function test_rename_folder_with_path_success()
    {
        $testPath = 'some/path';
        $this->rename_folder_with_path($testPath);
    }

    public function rename_folder_with_path(string $testPath = ''): void
    {
        $name = 'old_folder/old_sub_folder/file.txt';
        $this->uploadFile($testPath, $name);
        $this->assertAuthenticated();
        $oldFolderModel = LocalFile::where('filename', 'old_sub_folder')->get()->first();
        $this->postRename($oldFolderModel->id, 'new_sub_folder');

        Storage::disk('local')->assertExists(
            $this->storageFilesUUID . DIRECTORY_SEPARATOR . ($testPath ? $testPath . DIRECTORY_SEPARATOR : '') . 'old_folder/new_sub_folder/file.txt'
        );

        $fileObj = LocalFile::where('filename', 'file.txt')->first();
        $this->assertEquals(
            ($testPath ? $testPath . DIRECTORY_SEPARATOR : '') . 'old_folder/new_sub_folder',
            $fileObj->public_path
        );
        $this->assertEquals(false, $fileObj->is_dir);

        $fileObj = LocalFile::where('filename', 'new_sub_folder')->first();
        $this->assertEquals(($testPath ? $testPath . DIRECTORY_SEPARATOR : '') . 'old_folder', $fileObj->public_path);
        $this->assertEquals(true, $fileObj->is_dir);
    }

    public function postRename(string $ulid, string $filename): TestResponse
    {
        return $this->post(route('drive.rename'), [
            '_token' => csrf_token(),
            'id' => $ulid,
            'filename' => $filename,
        ]);
    }

    public function test_rename_folder_success()
    {
        $this->rename_folder_with_path();
    }

    public function test_rename_success()
    {
        $testPath = 'foo/bar';
        $fileName = 'new_filename.txt';

        $this->uploadFile($testPath, 'file.txt', 100);

        $this->assertAuthenticated();
        $firstFile = LocalFile::first();
        $response = $this->postRename($firstFile->id, $fileName);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Renamed to '. $fileName);

        Storage::disk('local')->assertExists(
            $this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR . $fileName
        );
    }

    public function test_rename_file_exist_fail()
    {
        $testPath = 'foo/bar';
        $fileName = 'new_filename.txt';
        $this->fileOptsMock->shouldReceive('fileExists')->with($this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR . $fileName)->andReturn(true);
        $this->uploadFile($testPath, 'file.txt');
        $firstFile = LocalFile::first();
        $response = $this->postRename($firstFile->id, $fileName);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Could not rename file. File with same name exists');
    }

    public function test_rename_fake_ulid()
    {
        $ulid = (string) Str::ulid();
        $response = $this->postRename($ulid, 'new_filename1.txt');
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Could not find file');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();

        $this->fileOptsMock = Mockery::mock(FileOperationsService::class)->makePartial();
        $this->app->instance(FileOperationsService::class, $this->fileOptsMock);
        $this->setupStoragePathPost();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
