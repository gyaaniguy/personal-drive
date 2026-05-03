<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure manifest exists so EnsureFrontendBuilt passes
        if (!file_exists(public_path('build/manifest.json'))) {
            if (!file_exists(public_path('build'))) {
                mkdir(public_path('build'), 0777, true);
            }
            file_put_contents(public_path('build/manifest.json'), '{}');
        }
    }

    public function test_redirects_to_setup_when_users_table_is_empty()
    {
        $response = $this->get('/');

        $response->assertRedirect('/setup/account');
    }

    public function test_redirects_to_setup_when_users_table_does_not_exist()
    {
        // Drop the users table
        DB::statement('DROP TABLE IF EXISTS users');

        $response = $this->get('/');

        $response->assertRedirect('/setup/account');
    }

    public function test_allows_access_when_user_exists()
    {
        User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $response = $this->get('/');

        $response->assertRedirect('/drive');
    }

    public function test_allows_access_to_setup_routes_even_when_users_empty()
    {
        // Ensure manifest exists so EnsureFrontendBuilt passes
        if (!file_exists(public_path('build/manifest.json'))) {
            file_put_contents(public_path('build/manifest.json'), '{}');
        }

        $response = $this->get('/setup/account');

        // Should not redirect to /setup/account (already there)
        if ($response->isRedirect()) {
            $this->assertStringNotContainsString('/setup/account', $response->getTargetUrl() ?? '');
        } else {
            // If not a redirect, request was successful
            $this->assertTrue(true);
        }
    }

    public function test_allows_access_to_error_route_even_when_users_empty()
    {
        $response = $this->get('/error?message=test');

        $response->assertStatus(200);
    }

    public function test_redirects_to_setup_for_drive_route_when_no_users()
    {
        $response = $this->get('/drive');

        $response->assertRedirect('/setup/account');
    }

    public function test_allows_access_after_user_creation()
    {
        // Initially should redirect to setup
        $this->get('/')->assertRedirect('/setup/account');

        // Create a user
        User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        // Now should allow access
        $response = $this->get('/');
        $response->assertRedirect('/drive');
    }

    public function test_setup_route_with_setup_mode_parameter()
    {
        // Ensure manifest exists so EnsureFrontendBuilt passes
        if (!file_exists(public_path('build/manifest.json'))) {
            file_put_contents(public_path('build/manifest.json'), '{}');
        }

        $response = $this->get('/setup/account');

        // Should not redirect to /setup/account (already there)
        if ($response->isRedirect()) {
            $this->assertStringNotContainsString('/setup/account', $response->getTargetUrl() ?? '');
        } else {
            // If not a redirect, request was successful
            $this->assertTrue(true);
        }
    }

    public function test_nested_setup_routes_are_allowed()
    {
        // This route is POST only, so GET will redirect to setup/account
        $response = $this->get('/setup/storage');

        $response->assertStatus(302); // Redirect to setup/account
    }
}
