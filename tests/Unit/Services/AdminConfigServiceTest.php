<?php

namespace Tests\Unit\Services;

use App\Models\Setting;
use App\Services\AdminConfigService;
use App\Services\FileOperationsService;
use App\Services\UUIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Helpers\CreatesUploadService;
use Tests\TestCase;

class AdminConfigServiceTest extends TestCase
{

    protected $uuidService;
    protected $uploadService;
    protected $adminConfigService;
    protected $setting;
    protected $storagePath = '/tmp/test_storage';

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

    protected function setUp(): void
    {
        parent::setUp();
        $this->uuidService = Mockery::mock(UUIDService::class);
        $this->uploadService = Mockery::mock(FileOperationsService::class);
        $this->setting = Mockery::mock(Setting::class);
        $this->adminConfigService = Mockery::mock(
            AdminConfigService::class,
            [$this->uuidService, $this->uploadService, $this->setting]
        )
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
}
