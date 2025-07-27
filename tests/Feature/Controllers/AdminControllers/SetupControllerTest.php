<?php

namespace Tests\Feature\Controllers\AdminControllers;

use App\Http\Middleware\CheckSetup;
use App\Http\Middleware\PreventSetupAccess;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SetupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_renders_setup_inertia_component()
    {
        $response = $this->withoutMiddleware(PreventSetupAccess::class)->get(route('setup.account'));

        $response->assertOk();
        $response->assertInertia(fn(AssertableInertia $page) => $page->component('Admin/Setup'));
    }

    public function test_update_creates_user_and_redirects_on_success()
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh', ['--force' => true]);

        $this->withMiddleware(\Illuminate\Session\Middleware\StartSession::class);
        $response = $this->get('/setup.account');

        $response = $this->post(route('setup.account'), [
            '_token' => csrf_token(),
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $this->assertDatabaseHas('users', [
            'username' => 'testuser',
            'is_admin' => 1,
        ]);


        $response->assertRedirect(route('admin-config', ['setupMode' => true]));
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Created User successfully');

        $this->post(route('logout'),[
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
        $this->withSession([]);
        // Ensure no user exists before each test that creates a user
        User::query()->delete();
    }
}
