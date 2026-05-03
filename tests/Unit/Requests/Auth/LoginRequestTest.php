<?php

namespace Tests\Unit\Requests\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRequestTest extends TestCase
{
    use RefreshDatabase;

    protected LoginRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new LoginRequest();
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_returns_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('username', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertIsArray($rules['username']);
        $this->assertIsArray($rules['password']);
    }

    public function test_authenticate_with_valid_credentials()
    {
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $this->request->merge([
            'username' => 'testuser',
            'password' => 'password123',
        ]);

        $this->request->authenticate();

        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticate_with_invalid_credentials()
    {
        User::create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $this->request->merge([
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        $this->expectException(ValidationException::class);

        $this->request->authenticate();

        $this->assertGuest();
    }

    public function test_authenticate_with_nonexistent_user()
    {
        $this->request->merge([
            'username' => 'nonexistent',
            'password' => 'password',
        ]);

        $this->expectException(ValidationException::class);

        $this->request->authenticate();
    }

    public function test_authenticate_with_remember_me()
    {
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $this->request->merge([
            'username' => 'testuser',
            'password' => 'password123',
            'remember' => true,
        ]);

        $this->request->authenticate();

        $this->assertAuthenticatedAs($user);
    }

    public function test_ensure_is_not_rate_limited_when_below_threshold()
    {
        $this->request->merge([
            'username' => 'testuser',
        ]);
        $this->request->server->set('REMOTE_ADDR', '127.0.0.1');

        // Should not throw exception
        $this->request->ensureIsNotRateLimited();
        $this->assertTrue(true); // Assert that we got here without exception
    }

    public function test_ensure_is_not_rate_limited_when_threshold_exceeded()
    {
        Event::fake();

        $this->request->merge([
            'username' => 'testuser',
        ]);
        $this->request->server->set('REMOTE_ADDR', '127.0.0.1');

        // Hit rate limit
        $throttleKey = $this->request->throttleKey();
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($throttleKey);
        }

        $this->expectException(ValidationException::class);

        $this->request->ensureIsNotRateLimited();

        Event::assertDispatched(Lockout::class);
    }

    public function test_throttle_key_format()
    {
        $this->request->merge([
            'username' => 'TestUser',
        ]);
        $this->request->server->set('REMOTE_ADDR', '127.0.0.1');

        $throttleKey = $this->request->throttleKey();

        $this->assertStringContainsString('testuser|127.0.0.1', $throttleKey);
    }

    public function test_throttle_key_with_special_characters()
    {
        $this->request->merge([
            'username' => 'Usér_123',
        ]);
        $this->request->server->set('REMOTE_ADDR', '192.168.1.1');

        $throttleKey = $this->request->throttleKey();

        $this->assertStringContainsString('192.168.1.1', $throttleKey);
    }

    public function test_throttle_key_is_lowercase()
    {
        $this->request->merge([
            'username' => 'UPPERCASE',
        ]);
        $this->request->server->set('REMOTE_ADDR', '127.0.0.1');

        $throttleKey = $this->request->throttleKey();

        $this->assertEquals(strtolower('UPPERCASE') . '|127.0.0.1', $throttleKey);
    }

    public function test_rate_limiter_cleared_on_successful_login()
    {
        $user = User::create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $this->request->merge([
            'username' => 'testuser',
            'password' => 'password123',
        ]);
        $this->request->server->set('REMOTE_ADDR', '127.0.0.1');

        // Hit rate limiter a few times
        $throttleKey = $this->request->throttleKey();
        RateLimiter::hit($throttleKey);
        RateLimiter::hit($throttleKey);

        $this->assertEquals(2, RateLimiter::attempts($throttleKey));

        // Authenticate should clear the rate limiter
        $this->request->authenticate();

        $this->assertEquals(0, RateLimiter::attempts($throttleKey));
    }

    public function test_rate_limiter_increments_on_failed_login()
    {
        User::create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $this->request->merge([
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);
        $this->request->server->set('REMOTE_ADDR', '127.0.0.1');

        $throttleKey = $this->request->throttleKey();

        try {
            $this->request->authenticate();
        } catch (ValidationException $e) {
            // Expected
        }

        $this->assertEquals(1, RateLimiter::attempts($throttleKey));
    }

    public function test_validation_exception_message_for_failed_auth()
    {
        User::create([
            'username' => 'testuser',
            'password' => bcrypt('password123'),
            'is_admin' => true,
        ]);

        $this->request->merge([
            'username' => 'testuser',
            'password' => 'wrongpassword',
        ]);

        try {
            $this->request->authenticate();
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertArrayHasKey('username', $errors);
        }
    }

    public function test_throttle_key_with_ipv6()
    {
        $this->request->merge([
            'username' => 'testuser',
        ]);
        $this->request->server->set('REMOTE_ADDR', '::1');

        $throttleKey = $this->request->throttleKey();

        $this->assertStringContainsString('::1', $throttleKey);
    }
}
