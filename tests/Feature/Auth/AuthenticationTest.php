<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        auth()->logout();
        $this->flushSession();

        $response = $this->get('/login');

        $response->assertStatus(302);
    }
}
