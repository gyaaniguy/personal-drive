<?php

namespace Feature\Controllers\AdminControllers;

use App\Models\Setting;
use App\Services\FileOperationsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\AdminConfigService;
use App\Services\UUIDService;
use Mockery;
use Tests\Helpers\SetupSite;
use Tests\TestCase;

class AdminConfigControllerTest extends TestCase
{
    use RefreshDatabase;
    use SetupSite;

    private $user;
    private AdminConfigService $adminConfigService;
    private FileOperationsService $fileService;
    private UUIDService $uuidService;
    private Setting $mockSetting;

    public function test_index_returns_correct_view_with_data()
    {
        $response = $this->actingAs($this->user)->get(route('admin-config'));

        $response->assertOk();
        $response->assertInertia(
            fn($page) => $page
                ->component('Admin/Config')
                ->hasAll([
                    'storage_path',
                    'php_max_upload_size',
                    'php_post_max_size',
                    'php_max_file_uploads',
                    'setupMode',
                ])
        );
    }

    public function test_update_with_valid_data_redirects_to_drive()
    {
        $this->fileService->shouldReceive('directoryExists')->withAnyArgs()->andReturn(true);
        $this->fileService->shouldReceive('isWritable')->withAnyArgs()->andReturn(true);
        $this->mockSetting->shouldReceive('updateSetting')
            ->andReturn(true);
        $result = $this->adminConfigService->updateStoragePath($this->storagePath);

        $this->assertTrue($result['status']);
        $this->assertEquals('Storage path updated successfully', $result['message']);
    }

    public function test_storage_directory_exists_but_not_writable()
    {
        $this->fileService->shouldReceive('directoryExists')->with($this->storagePath)->once()->andReturn(true);
        $this->fileService->shouldReceive('isWritable')->with($this->storagePath)->once()->andReturn(false);

        $result = $this->adminConfigService->updateStoragePath($this->storagePath);

        $this->assertFalse($result['status']);
        $this->assertEquals('Storage directory exists but is not writable', $result['message']);
    }

    public function test_setting_update_fails()
    {
        $this->fileService->shouldReceive('directoryExists')->with($this->storagePath)->once()->andReturn(true);
        $this->fileService->shouldReceive('isWritable')->with($this->storagePath)->once()->andReturn(true);

        $this->mockSetting->shouldReceive('updateSetting')
            ->andReturn(false);

        $result = $this->adminConfigService->updateStoragePath($this->storagePath);

        $this->assertFalse($result['status']);
        $this->assertEquals('Failed to save storage path setting', $result['message']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->makeUser();

        $this->mockSetting = Mockery::mock('App\Models\Setting');
        $this->mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForStorageFiles')
            ->andReturn('test_storage_uuid');
        $this->mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForThumbnails')
            ->andReturn('test_thumbnails_uuid');
        $this->fileService = Mockery::mock(FileOperationsService::class);
        $this->uuidService = Mockery::mock(UUIDService::class);

        $this->adminConfigService = new AdminConfigService($this->uuidService, $this->fileService, $this->mockSetting);

        $this->storagePath = '/tmp/storage';
        $this->storageUUID = 'files-uuid';
        $this->thumbUUID = 'thumbs-uuid';

        $this->storageDir = $this->storagePath . '/' . $this->storageUUID;
        $this->thumbDir = $this->storagePath . '/' . $this->thumbUUID;

        $this->uuidService->shouldReceive('getStorageFilesUUID')->andReturn($this->storageUUID);
        $this->uuidService->shouldReceive('getThumbnailsUUID')->andReturn($this->thumbUUID);

    }


    public function test_unable_to_create_storage_directory()
    {
        $this->fileService->shouldReceive('isWritable')->with($this->storagePath)->once()->andReturn(true);

        $this->fileService->shouldReceive('makeFolder')->with($this->storageDir)->andReturn(false);
        $this->fileService->shouldReceive('directoryExists')->with($this->storageDir)->andReturn(true);
        $this->fileService->shouldReceive('directoryExists')->with($this->storagePath)->andReturn(true);
        $this->fileService->shouldReceive('directoryExists')->with($this->storageUUID)->andReturn(false);
        $this->fileService->shouldReceive('directoryExists')->with($this->thumbUUID)->andReturn(true);
        $this->fileService->shouldReceive('makeFolder')->with($this->storageUUID)->andReturn(false);
        $this->fileService->shouldReceive('makeFolder')->with($this->thumbUUID)->andReturn(true);
        $this->fileService->shouldReceive('isWritable')->with($this->storageUUID)->andReturn(true);
        $this->fileService->shouldReceive('isWritable')->with($this->thumbUUID)->andReturn(true);

        $this->mockSetting->shouldReceive('updateSetting')
            ->andReturn(true);

        $result = $this->adminConfigService->updateStoragePath($this->storagePath);

        $this->assertFalse($result['status']);
        $this->assertEquals('Unable to create or write to storage directory. Check Permissions', $result['message']);
    }

    public function test_unable_to_create_thumbnail_directory()
    {

        $this->mockSetting->shouldReceive('updateSetting')
            ->andReturn(true);
        $this->fileService->shouldReceive('directoryExists')->with($this->storagePath)->once()->andReturn(true);
        $this->fileService->shouldReceive('isWritable')->with($this->storagePath)->once()->andReturn(true);

        $this->fileService->shouldReceive('isWritable')->with($this->thumbUUID)->andReturn(true);

        $this->fileService->shouldReceive('makeFolder')->with($this->thumbUUID)->andReturn(false);
        $this->fileService->shouldReceive('directoryExists')->with($this->thumbUUID)->andReturn(false);


        $result = $this->adminConfigService->updateStoragePath($this->storagePath);

        $this->assertFalse($result['status']);
        $this->assertEquals('Unable to create or write to thumbnail directory. Check Permissions', $result['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
