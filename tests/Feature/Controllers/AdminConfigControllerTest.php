<?php

namespace Tests\Feature\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\AdminConfigService;
use App\Services\UUIDService;
use Mockery;
use Tests\TestCase;

class AdminConfigControllerTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private AdminConfigService $adminConfigService;

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
        $result = $this->adminConfigService->updateStoragePath(storage_path('app/private'));

        $this->assertTrue($result['status']);
        $this->assertEquals('Storage path updated successfully', $result['message']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['is_admin' => true]);

        $mockSetting = Mockery::mock('App\Models\Setting');
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForStorageFiles')
            ->andReturn('test_storage_uuid');
        $mockSetting->shouldReceive('getSettingByKeyName')
            ->with('uuidForThumbnails')
            ->andReturn('test_thumbnails_uuid');

        $this->adminConfigService = new AdminConfigService(new UUIDService($mockSetting));
    }

}
