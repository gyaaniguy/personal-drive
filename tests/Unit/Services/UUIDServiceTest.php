<?php

namespace Tests\Unit\Services;

use App\Exceptions\PersonalDriveExceptions\UUIDException;
use App\Models\Setting;
use App\Services\UUIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class UUIDServiceTest extends TestCase
{
    public function test_constructor_throws_exception_if_storage_uuid_is_missing()
    {
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForStorageFiles')
            ->andReturn('');
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForThumbnails')
            ->andReturn('some-thumbnail-uuid');

        $this->expectException(UUIDException::class);
        new UUIDService($mockSetting);
    }

    public function test_constructor_throws_exception_if_thumbnails_uuid_is_missing()
    {
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForStorageFiles')
            ->andReturn('some-storage-uuid');
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForThumbnails')
            ->andReturn('');

        $this->expectException(UUIDException::class);
        new UUIDService($mockSetting);
    }

    public function test_get_storage_files_uuid_returns_correct_uuid()
    {
        $expectedUuid = 'test-storage-uuid';
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForStorageFiles')
            ->andReturn($expectedUuid);
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForThumbnails')
            ->andReturn('test-thumbnail-uuid');

        $service = new UUIDService($mockSetting);
        $this->assertEquals($expectedUuid, $service->getStorageFilesUUID());
    }

    public function test_get_thumbnails_uuid_returns_correct_uuid()
    {
        $expectedUuid = 'test-thumbnail-uuid';
        $mockSetting = Mockery::mock(Setting::class);
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForStorageFiles')
            ->andReturn('test-storage-uuid');
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForThumbnails')
            ->andReturn($expectedUuid);

        $service = new UUIDService($mockSetting);
        $this->assertEquals($expectedUuid, $service->getThumbnailsUUID());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
