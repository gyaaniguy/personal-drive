<?php

namespace Tests\Unit\Providers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\BaseFeatureTest;

class AppServiceProviderFeatureTest extends BaseFeatureTest
{
    use RefreshDatabase;

    public function test_shared_throttle_limit_enforced_via_route_post()
    {
        // Send 20 allowed POST requests
        for ($i = 0; $i < 7; $i++) {
            $this->get(route('login'));
        }
        $response = $this->get(route('login'));
        $response->assertSessionHas('status', false);
        $response->assertRedirect(route('rejected', ['message' => 'Too Many requests. Please try again later']));

        RateLimiter::clear('login|' . '127.0.0.1');
        // Send 20 allowed POST requests
        for ($i = 0; $i < 20; $i++) {
            $this->get(route('shared.password', ['slug' => 'slug']));
        }
        $response = $this->get(route('shared.password', ['slug' => 'slug']));
        $response->assertSessionHas('status', false);
        $response->assertRedirect(route('rejected', ['message' => 'Too Many requests. Please try again later']));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
