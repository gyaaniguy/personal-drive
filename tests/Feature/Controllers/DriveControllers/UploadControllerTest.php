<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Models\LocalFile;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Feature\BaseFeatureTest;

class UploadControllerTest extends BaseFeatureTest
{
    protected mixed $uploadService;
    private string $fileName = 'dummy.txt';
    private $tempRootDir;

    public function test_store_returns_error_when_no_files_uploaded()
    {
        $this->assertAuthenticated();
        $response = $this->post(route('drive.upload'), [
            '_token' => csrf_token(),
            'path' => '/some/path',
        ]);
        $response->assertSessionHasErrors(['files' => 'The files field is required.']);
    }

    public function test_create_upload_different_path_file_success()
    {
        $fileName = 'file.txt';
        $fileName2 = 'file2.txt';
        $fileName3 = 'file3.txt';

        $testPath = '';
        $testPath2 = 'foo/bar';
        $testPath3 = 'foo/bar/foo';

        $this->uploadFile($testPath, $fileName, 100);
        $this->uploadFile($testPath2, $fileName2, 100);
        $this->uploadFile($testPath3, $fileName3, 100);
    }

    public function test_create_upload_mulitple_files_success()
    {
        $testFileName2 = 'bar2/dummy2.txt';
        $file = UploadedFile::fake()->create($this->fileName, 100);
        $file2 = UploadedFile::fake()->create($testFileName2, 10);

        $testPath = 'foo/bar';
        $response = $this->postUpload([$file, $file2], $testPath);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Files uploaded: 2'));
        Storage::disk('local')->assertExists($this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR . $this->fileName);
        Storage::disk('local')->assertExists($this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR . $testFileName2);
    }

    public function test_create_file_successfully()
    {
        $testPath = '';
        $response = $this->post(route('drive.create-item'), [
            '_token' => csrf_token(),
            'itemName' => $this->fileName,
            'path' => $testPath,
            'isFile' => true,
        ]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Created file successfully');
        Storage::disk('local')->assertExists($this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . $this->fileName);
    }

    public function test_create_folder_successfully()
    {
        $testPath = '';
        $testFolder = 'TestFolder';
        $response = $this->post(route('drive.create-item'), [
            '_token' => csrf_token(),
            'itemName' => $testFolder,
            'path' => $testPath,
            'isFile' => false,
        ]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Created folder successfully');
        Storage::disk('local')->assertExists($this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . $testFolder);
    }

    public function test_create_upload_folder_file_conflict_fail()
    {
        $testPath = 'some/path';
        $fileName1 = 'foo/bar';
        $fileName2 = 'foo/bar1';
        $fileName3 = 'foo/bar/more/path/file1';
        $this->uploadMultipleFiles($testPath, [$fileName1, $fileName2]);
        $files[] = UploadedFile::fake()->create($fileName3, 100);

        $response = $this->post(route('drive.upload'), [
            '_token' => csrf_token(),
            'files' => $files,
            'path' => $testPath
        ]);

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Conflicts'));
    }

    public function test_create_upload_file_folder_conflict_fail()
    {
        $testPath = 'some/path';
        $fileName1 = 'foo/bar/file1';
        $fileName2 = 'foo/bar/file2';
        $fileName3 = 'foo/bar';
        $this->uploadMultipleFiles($testPath, [$fileName1, $fileName2]);
        $files[] = UploadedFile::fake()->create($fileName3, 100);

        $response = $this->post(route('drive.upload'), [
            '_token' => csrf_token(),
            'files' => $files,
            'path' => $testPath
        ]);

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Conflicts'));
    }

    public function test_create_upload_folder_conflict_partial_success()
    {
        $testPath = 'some/path';
        $fileName1 = 'foo/bar/file1';
        $fileName2 = 'foo/bar/file2';
        $fileName3 = 'foo/bar';
        $fileName4 = 'foo/bar/file3';
        $this->uploadMultipleFiles($testPath, [$fileName1, $fileName2]);
        $response = $this->uploadMultipleFiles($testPath, [$fileName3, $fileName4]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Conflicts'));
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Files uploaded: 1 out of 2'));
    }

    public function test_create_upload_folder_duplicates_partial()
    {
        $this->uploadDuplicates();

        $response = $this->post(route('drive.abort-replace'), [
            '_token' => csrf_token(),
            'action' => 'overwrite'
        ]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Overwritten successfully');
    }


    public function uploadDuplicates()
    {
        $testPath = 'some/path';
        $files = ['foo/bar/file1', 'foo/bar/file2', 'foo/file3'];
        $this->uploadMultipleFiles($testPath, ['foo/bar/file1', 'foo/bar/file2', 'foo/file3']);
        $files1 = ['foo/bar/file3', 'foo/bar/file2', 'foo/file1', 'foo/file3'];
        $response = $this->uploadMultipleFiles($testPath, $files1);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Duplicates Detected'));
        $this->tempRootDir = $this->uploadService->getTempStorageDir();

        $this->assertTrue(collect(array_merge($files, $files1))->every(fn($file) => Storage::disk('local')->exists(
            $this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR . $file
        )));
        $this->assertTrue(collect(array_intersect($files1, $files))->every(fn($file) => Storage::disk('local')->exists(
            $this->tempRootDir . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR . $file
        )));
        return $response;
    }

    public function test_create_upload_folder_duplicates_abort()
    {
        $this->uploadDuplicates();

        $response = $this->post(route('drive.abort-replace'), [
            '_token' => csrf_token(),
            'action' => 'abort'
        ]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Aborted Overwrite');
        $this->assertDirectoryDoesNotExist($this->tempRootDir);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadService = app(UploadService::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Storage::disk('local')->deleteDirectory('');
        parent::tearDown();
    }
}
