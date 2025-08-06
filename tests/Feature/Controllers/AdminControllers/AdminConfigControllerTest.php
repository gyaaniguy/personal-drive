<?php

namespace Feature\Controllers\AdminControllers;

use App\Models\Setting;
use App\Services\FileOperationsService;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\Feature\BaseFeatureTest;

use const false;

class AdminConfigControllerTest extends BaseFeatureTest
{
    private string $newStoragePath = '';
    private $fileOptsMock;
    private $settingMock;

    public function test_index_returns_correct_view_with_data()
    {
        $response = $this->get(route('admin-config'));
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

    public function test_update_setting_success()
    {
        $this->postNewStoragePath();
    }

    public function test_update_setting_same_success()
    {
        $this->postNewStoragePath();
        $this->postNewStoragePath();
    }

    protected function assertSessionHas($response, string $message): void
    {
        $response->assertSessionHas(
            'message',
            fn($value) => str_contains($value, $message)
        );
    }

    public function test_update_setting_fail()
    {
        $this->settingMock->shouldReceive('updateSetting')->withAnyArgs()->andReturn(false);
        $response = $this->updateStoragePost(false);
        $this->assertSessionHas($response, 'Failed to save storage path setting');
    }

    public function updateStoragePost($status = true): TestResponse
    {
        $originalStoragePath = Setting::getStoragePath();
        $response = $this->setStoragePath($this->newStoragePath);
        $response->assertSessionHas('status', $status);
        $response->assertRedirect(route('admin-config', ['setupMode' => true]));
        $this->assertEquals($originalStoragePath, Setting::getStoragePath());
        return $response;
    }

    public function test_update_storage_not_writable_fail()
    {
        $this->fileOptsMock->shouldReceive('isWritable')->with(CONTENT_SUBDIR)->andReturn(false);
        $response = $this->updateStoragePost(false);
        $this->assertSessionHas($response, 'Unable to create storage directory. Check Permissions');
    }

    public function test_update_thumbnail_not_writable_fail()
    {
        $this->fileOptsMock->shouldReceive('isWritable')->with(THUMBS_SUBDIR)->andReturn(false);
        $response = $this->updateStoragePost(false);
        $this->assertSessionHas($response, 'Unable to create thumbnail directory. Check Permissions');
    }

    public function postNewStoragePath(): void
    {
        $response = $this->setStoragePath($this->newStoragePath);
        $response->assertSessionHas('status', true);
        $response->assertRedirect(route('drive'));
        $this->assertEquals($this->getFakeLocalStoragePath($this->newStoragePath), Setting::getStoragePath());
        $this->assertSessionHas($response, 'Storage path updated successfully');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->newStoragePath = '/foo/bar';
        $this->fileOptsMock = Mockery::mock(FileOperationsService::class)->makePartial();
        $this->app->instance(FileOperationsService::class, $this->fileOptsMock);
        $this->settingMock = Mockery::mock(Setting::class)->makePartial();
        $this->app->instance(Setting::class, $this->settingMock);
        $this->setupStoragePathPost();
    }
}
