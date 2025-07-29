<?php

namespace Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class FileRenameControllerTest extends BaseFeatureTest
{
    use RefreshDatabase;


    public function test_rename_folder_with_path_success()
    {
        $testPath = 'some/path';
        $this->rename_folder_with_path($testPath);
    }

    /**
     * @param  string  $testPath
     * @return void
     */
    public function rename_folder_with_path(string $testPath = ''): void
    {
        $name = 'old_folder/old_sub_folder/file.txt';

        $this->upload_file($testPath, $name, 100);

        $this->assertAuthenticated();
        $oldFolderModel = LocalFile::where('filename', 'old_sub_folder')->get()->first();

        $response = $this->post(route('drive.rename'), [
            '_token' => csrf_token(),
            'id' => $oldFolderModel->id,
            'filename' => 'new_sub_folder',
        ]);

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

    public function test_rename_folder_success()
    {
        $this->rename_folder_with_path();
    }

    public function test_rename_success()
    {
        $testPath = 'foo/bar';
        $fileName = 'new_filename.txt';

        $this->upload_file($testPath, 100, 100);

        $this->assertAuthenticated();
        $firstFile = LocalFile::first();

        $response = $this->post(route('drive.rename'), [
            '_token' => csrf_token(),
            'id' => $firstFile->id,
            'filename' => 'new_filename.txt',
        ]);

        Storage::disk('local')->assertExists(
            $this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR . $fileName
        );
    }

    public function test_rename_fake_ulid()
    {
        $ulid = (string) Str::ulid();
        $response = $this->post(route('drive.rename'), [
            '_token' => csrf_token(),
            'id' => $ulid,
            'filename' => 'new_filename1.txt',
        ]);
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Could not find file');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $response = $this->setupStoragePathPost();
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Storage path updated successfully');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
