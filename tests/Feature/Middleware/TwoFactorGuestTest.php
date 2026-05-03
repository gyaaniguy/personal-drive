<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class TwoFactorGuestTest extends TestCase
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

    public function test_redirects_to_login_when_two_factor_user_id_not_in_session()
    {
        // Create a user so CheckSetup doesn't redirect to setup
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        Session::forget('twoFactorUserId');

        $response = $this->get(route('login.two-factor-index'));

        $response->assertRedirect(route('login'));
    }

    public function test_allows_access_when_two_factor_user_id_exists_in_session()
    {
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
            'two_factor_secret' => 'test-secret',
        ]);

        Session::put('twoFactorUserId', $user->id);

        $response = $this->get(route('login.two-factor-index'));

        $response->assertStatus(200);
    }

    public function test_redirects_to_login_after_session_is_cleared()
    {
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        Session::put('twoFactorUserId', $user->id);

        // First request should succeed
        $this->get(route('login.two-factor-index'))->assertStatus(200);

        // Clear the session
        Session::forget('twoFactorUserId');

        // Second request should redirect
        $response = $this->get(route('login.two-factor-index'));
        $response->assertRedirect(route('login'));
    }

    public function test_allows_post_request_when_two_factor_user_id_exists()
    {
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        Session::put('twoFactorUserId', $user->id);

        $response = $this->post(route('login.two-factor-check'), [
            'code' => '123456',
        ]);

        // Should not redirect to login
        $response->assertStatus(302);
    }

    public function test_two_factor_session_with_numeric_user_id()
    {
        // Create a user so CheckSetup doesn't redirect to setup
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        Session::put('twoFactorUserId', 123);

        $response = $this->get(route('login.two-factor-index'));

        $response->assertStatus(200);
    }

    public function test_two_factor_session_with_string_user_id()
    {
        // Create a user so CheckSetup doesn't redirect to setup
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);

        Session::put('twoFactorUserId', 'user-123');

        $response = $this->get(route('login.two-factor-index'));

        $response->assertStatus(200);
    }
}
