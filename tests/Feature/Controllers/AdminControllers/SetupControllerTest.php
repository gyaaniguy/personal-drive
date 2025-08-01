<?php

namespace Tests\Feature\Controllers\AdminControllers;

use App\Http\Middleware\PreventSetupAccess;
use App\Models\User;
use App\Services\FileOperationsService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\BaseFeatureTest;
use Tests\TestCase;

class SetupControllerTest extends BaseFeatureTest
{
    public function test_show_renders_setup_inertia_component()
    {
        $response = $this->withoutMiddleware(PreventSetupAccess::class)->get(route('setup.account'));

        $response->assertOk();
        $response->assertInertia(fn(AssertableInertia $page) => $page->component('Admin/Setup'));
    }

    public function test_update_storage_path_success()
    {
        $this->makeUserUsingSetup();
        $response = $this->setupStoragePathPost('/tmp/path');
        $response->assertRedirect(route('drive'));
    }

    public function test_update_storage_path_fail()
    {
        $this->makeUserUsingSetup();

        $this->partialMock(FileOperationsService::class, function ($mock) {
            $mock->shouldReceive('isWritable')->andReturn(false);
        });
        $response = $this->setStoragePath('/asdf/tmp/sdf');
        $response->assertSessionHas('status', false);
        $response->assertSessionHas(
            'message',
            fn($value) => str_contains($value, 'Unable to create storage directory. Check Permissions')
        );
        $response->assertRedirect(route('admin-config', ['setupMode' => true]));
    }

    public function test_update_creates_user_and_redirects_on_success()
    {
        $this->makeUserUsingSetup();

        $this->post(route('logout'), [
            '_token' => csrf_token(),
        ]);

        $response = $this->post(route('login'), [
            '_token' => csrf_token(),
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('drive'));
    }

    public function test_update_fails_if_user_creation_fails()
    {
        $this->withoutMiddleware([
            ValidateCsrfToken::class,
            PreventSetupAccess::class,
        ]);
        $response = $this->post(route('setup.account'), [
            'username' => '',
            'password' => '$2y$12$DLhiW11mI9/afaOrf5tYROW2YG6VOP4F4THjoPQD8kTCzW9aelKMK',
        ]);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Please check the form for errors.');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure no user exists before each test that creates a user
        User::query()->delete();
    }
}
