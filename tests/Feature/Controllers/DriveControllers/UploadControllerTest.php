<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Services\UUIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Helpers\SetupSite;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;
    use SetupSite;

    protected UUIDService $uuidService;
    protected string $storageFilesDirPath;
    private string $testPath = '';
    private string $testFileName = 'dummy.txt';

    public function test_store_returns_error_when_no_files_uploaded()
    {
        $this->assertAuthenticated();
        $response = $this->post(route('drive.upload'), [
            '_token' => csrf_token(),
            'path' => '/some/path',
        ]);
        $response->assertSessionHasErrors(['files' => 'The files field is required.']);
    }

    public function test_create_item_creates_file_successfully()
    {
        $response = $this->postCreateItem([
            '_token' => csrf_token(),
            'itemName' => $this->testFileName,
            'path' => $this->testPath,
            'isFile' => true,
        ]);

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Created file successfully');
        Storage::disk('local')->assertExists($this->storageFilesDirPath . DIRECTORY_SEPARATOR . $this->testPath . $this->testFileName);
    }

    private function postCreateItem(array $data)
    {
        return $this->post(route('drive.create-item'), $data);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeUserUsingSetup();
        $response = $this->setupStoragePathPost();
        $this->uuidService = app(UUIDService::class);
        $this->storageFilesDirPath = $this->uuidService->getStorageFilesUUID();

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Storage path updated successfully');
    }
}
