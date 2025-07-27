<?php

namespace Tests\Feature\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Helpers\SetupSite;
use Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionControllerTest extends TestCase
{
    use RefreshDatabase;
    use SetupSite;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->withoutMiddleware()->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn($page) => $page->component('Auth/Login'));
    }


    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = $this->makeUser();

        $this->post('/logout');

        $response = $this->post('/login', [
            'username' => 'testuser',
            'password' => 'wronggpassword',
        ]);
        $this->assertGuest();
        $response->assertSessionHas('message', 'Please check the form for errors.');

//        $response->assertRedirect('/login');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Auth::logout(); // Ensure no user is authenticated at the start of each test
    }
}
