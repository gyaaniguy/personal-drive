<?php

namespace Tests\Unit\Services;

use App\Services\AdminConfigService;
use App\Services\UploadService;
use App\Services\UUIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Helpers\CreatesUploadService;
use Tests\TestCase;

class AdminConfigServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesUploadService;

    protected $uuidService;
    protected $uploadService;
    protected $adminConfigService;
    protected $storagePath = '/tmp/test_storage';


    protected function setUp(): void
    {
        parent::setUp();
        $this->uuidService = Mockery::mock(UUIDService::class);
        $this->uploadService = Mockery::mock(UploadService::class);
        $this->adminConfigService = Mockery::mock(AdminConfigService::class, [$this->uuidService, $this->uploadService])
            ->makePartial();
        $storageFilesUUID = 'uuid_storage';
        $thumbnailsUUID = 'uuid_thumbnails';

        $this->uuidService->shouldReceive('getStorageFilesUUID')
            ->andReturn($storageFilesUUID);
        $this->uuidService->shouldReceive('getThumbnailsUUID')
            ->andReturn($thumbnailsUUID);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_update_storage_path_success()
    {
        Mockery::mock('alias:file_exists')
            ->shouldReceive('file_exists')
            ->andReturn(false);

        Mockery::mock('alias:is_writable')
            ->shouldReceive('is_writable')
            ->andReturn(true);

        $this->adminConfigService
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('ensureDirectoryExists')
            ->andReturn(true);
        $result = $this->adminConfigService->updateStoragePath($this->storagePath);

        $this->assertTrue($result['status']);
        $this->assertEquals('Storage path updated successfully', $result['message']);
    }


    public function test_update_storage_path_cannot_create_storage_files_directory()
    {
        $storageFilesUUID = 'uuid_storage';
        $thumbnailsUUID = 'uuid_thumbnails';

        $this->uuidService->shouldReceive('getStorageFilesUUID')
            ->andReturn($storageFilesUUID);
        $this->uuidService->shouldReceive('getThumbnailsUUID')
            ->andReturn($thumbnailsUUID);

        Mockery::mock('alias:file_exists')
            ->shouldReceive('file_exists')
            ->andReturn(false);

        Mockery::mock('alias:is_writable')
            ->shouldReceive('is_writable')
            ->andReturn(true);

        $this->adminConfigService
            ->shouldAllowMockingProtectedMethods()
            ->shouldReceive('ensureDirectoryExists')
            ->andReturn(false);

        $result = $this->adminConfigService->updateStoragePath($this->storagePath);

        $this->assertFalse($result['status']);
        $this->assertEquals('Unable to create or write to storage directory. Check Permissions', $result['message']);
    }

    public function test_get_php_upload_max_filesize()
    {
        $this->assertEquals(ini_get('upload_max_filesize'), $this->adminConfigService->getPhpUploadMaxFilesize());
    }

    public function test_get_php_post_max_size()
    {
        $this->assertEquals(ini_get('post_max_size'), $this->adminConfigService->getPhpPostMaxSize());
    }

    public function test_get_php_max_file_uploads()
    {
        $this->assertEquals(ini_get('max_file_uploads'), $this->adminConfigService->getPhpMaxFileUploads());
    }
}