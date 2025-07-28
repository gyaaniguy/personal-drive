<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Services\UploadService;
use App\Services\UUIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\Helpers\SetupSite;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;
    use SetupSite;

    protected UUIDService $uuidService;
    protected string $storageFilesUUID;
    private string $fileName = 'dummy.txt';

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

        $this->create_upload_file_success($testPath, $fileName);
        $this->create_upload_file_success($testPath2, $fileName2);
        $this->create_upload_file_success($testPath3, $fileName3);
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
        Storage::disk('local')->assertExists($this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR. $this->fileName);
        Storage::disk('local')->assertExists($this->storageFilesUUID . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR. $testFileName2);
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

    public function test_create_upload_duplicates_detected()
    {
        $file = UploadedFile::fake()->create($this->fileName, 100);
        $testPath = 'foo/bar';
        $response = $this->postUpload([$file], $testPath);
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Files uploaded'));

        $file = UploadedFile::fake()->create($this->fileName, 100);
        $response = $this->postUpload([$file], $testPath);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('more_info', ['replaceAbort' => true]);
        $response->assertSessionHas('message', fn($value) => str_contains($value, 'Duplicates Detected'));

        $uploadService = app(UploadService::class);

        Storage::disk('local')->assertExists(
            $uploadService->getTempStorageDir() . DIRECTORY_SEPARATOR . $testPath . DIRECTORY_SEPARATOR . $this->fileName
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeUserUsingSetup();
        $response = $this->setupStoragePathPost();
        $this->uuidService = app(UUIDService::class);
        $this->storageFilesUUID = $this->uuidService->getStorageFilesUUID();

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Storage path updated successfully');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
