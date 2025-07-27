<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Services\AdminConfigService;
use App\Services\UUIDService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Tests\Helpers\SetupSite;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;
    use SetupSite;

    protected UUIDService $uuidService;
    protected string $storageFilesDirPath;
    private string $baseStoragePath = 'app/private/';
    private string $testPath = '';
    private string $testFileName = 'dummy.txt';

    public function test_store_returns_error_when_no_files_uploaded()
    {
        $this->assertAuthenticated();
        $response = $this->post(route('drive.upload'), [
            'path' => '/some/path',
        ]);
        $response->assertSessionHasErrors(['files' => 'The files field is required.']);
    }

    public function test_create_item_creates_file_successfully()
    {
        $response = $this->postCreateItem([
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

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->makeUser();
        Storage::fake('local');
        $this->adminConfigService = app(AdminConfigService::class);
        $this->baseStoragePath = Storage::disk('local')->path('');
        $this->baseStoragePath = substr($this->baseStoragePath, 0, strlen($this->baseStoragePath) - 1);
        $result = $this->adminConfigService->updateStoragePath($this->baseStoragePath);

        $this->uuidService = app(UUIDService::class);
        $this->storageFilesDirPath = $this->uuidService->getStorageFilesUUID();

        $this->assertTrue($result['status']);
        $this->assertEquals('Storage path updated successfully', $result['message']);
    }
}
