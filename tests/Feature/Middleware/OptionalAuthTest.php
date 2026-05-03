<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class OptionalAuthTest extends TestCase
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

    public function test_guest_can_access_when_auth_disabled()
    {
        Config::set('app.disable_auth', true);

        // Create admin user
        $admin = User::create([
            'username' => 'admin',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $this->get('/drive');

        // System user should be logged in
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(Session::has('system_auth'));
    }

    public function test_no_user_logged_in_when_no_admin_exists_and_auth_disabled()
    {
        Config::set('app.disable_auth', true);

        $this->get('/drive');

        // No user should be logged in since no admin exists
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_when_auth_enabled()
    {
        Config::set('app.disable_auth', false);

        // Create a user so CheckSetup doesn't redirect to setup
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $response = $this->get('/drive');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_remains_logged_in_when_auth_enabled()
    {
        Config::set('app.disable_auth', false);

        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $this->actingAs($user)->get('/drive');

        $this->assertAuthenticatedAs($user);
    }

    public function test_system_auth_logs_out_user_when_auth_enabled_and_session_exists()
    {
        Config::set('app.disable_auth', false);

        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        Session::put('system_auth', true);
        $this->actingAs($user)->get('/drive');

        // User should be logged out
        $this->assertGuest();
        $this->assertFalse(Session::has('system_auth'));
    }

    public function test_admin_user_is_logged_in_automatically_when_auth_disabled()
    {
        Config::set('app.disable_auth', true);

        $admin = User::create([
            'username' => 'admin',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $response = $this->get('/drive');

        // Admin should be logged in
        $this->assertAuthenticatedAs($admin);
        $this->assertTrue(Session::has('system_auth'));
    }

    public function test_first_admin_user_is_used_when_multiple_exist_and_auth_disabled()
    {
        Config::set('app.disable_auth', true);

        $admin1 = User::create([
            'username' => 'admin1',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        User::create([
            'username' => 'admin2',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $this->get('/drive');

        // Should authenticate as the first admin found
        $this->assertAuthenticatedAs($admin1);
    }

    public function test_regular_user_is_not_logged_in_when_auth_disabled()
    {
        Config::set('app.disable_auth', true);

        $admin = User::create([
            'username' => 'admin',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        $regular = User::create([
            'username' => 'regular',
            'password' => bcrypt('password'),
            'is_admin' => false,
        ]);

        $this->get('/drive');

        // Should be admin, not regular user
        $this->assertAuthenticatedAs($admin);
        $this->assertAuthenticated();
        // Verify it's the admin user, not the regular user
        $this->assertEquals('admin', auth()->user()->username);
    }
}
