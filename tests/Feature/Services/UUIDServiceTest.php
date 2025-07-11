<?php

use App\Models\Setting;
use App\Services\UUIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UUIDServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $settingMock;

    public function test_initializes_with_valid_uuids()
    {
        $service = new UUIDService(app(Setting::class));

        $this->assertEquals('storage-123', $service->getStorageFilesUUID());
        $this->assertEquals('thumb-456', $service->getThumbnailsUUID());
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingMock = Mockery::mock(Setting::class);

        $this->settingMock->shouldReceive('getSettingByKeyName')
            ->with('uuidForStorageFiles')
            ->andReturn('storage-123');

        $this->settingMock->shouldReceive('getSettingByKeyName')
            ->with('uuidForThumbnails')
            ->andReturn('thumb-456');

        $this->app->instance(Setting::class, $this->settingMock);
    }
}
