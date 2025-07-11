<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\HandleAuthOrGuestMiddleware;
use App\Http\Middleware\HandleGuestShareMiddleware;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class HandleAuthOrGuestMiddlewareTest extends TestCase
{
    public function test_allows_authenticated_users_to_pass()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $middleware = new HandleAuthOrGuestMiddleware(app(Authenticate::class));

        $request = Request::create('/', 'GET');
        $next = function ($request) {
            return response('OK', 200);
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_delegates_to_handle_guest_share_middleware_for_unauthenticated_users()
    {
        Auth::shouldReceive('guard')->andReturnSelf();
        Auth::shouldReceive('check')->andReturn(false);

        $mockGuestShareMiddleware = Mockery::mock(HandleGuestShareMiddleware::class);
        $mockGuestShareMiddleware->shouldReceive('handle')
            ->once()
            ->andReturn(response('Guest Handled', 200));

        $this->app->instance(HandleGuestShareMiddleware::class, $mockGuestShareMiddleware);

        $middleware = new HandleAuthOrGuestMiddleware(app(Authenticate::class));

        $request = Request::create('/', 'GET');
        $next = function ($request) {
            return response('OK', 200);
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Guest Handled', $response->getContent());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate'); // Ensure database is migrated for each test
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
