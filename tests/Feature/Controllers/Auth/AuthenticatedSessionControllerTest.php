<?php

namespace Tests\Feature\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->withoutMiddleware()->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page->component('Auth/Login'));
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh', ['--force' => true]);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post(route('setup.account'), [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $this->post('/logout');

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('drive'));
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create(['username' => 'testuser', 'password' => 'password']);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->post(route('setup.account'), [
            'username' => 'testuser',
            'password' => 'password',
        ]);
        $this->actingAs($user);

        $response = $this->post('/logout');
        $response->assertRedirect('/login');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Auth::logout(); // Ensure no user is authenticated at the start of each test
    }
}
