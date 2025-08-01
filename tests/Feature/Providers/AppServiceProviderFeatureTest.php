<?php

namespace Tests\Unit\Providers;

use App\Models\Setting;
use App\Providers\AppServiceProvider;
use App\Services\UUIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\Feature\BaseFeatureTest;
use Tests\TestCase;

class AppServiceProviderFeatureTest extends BaseFeatureTest
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }

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
            $this->get(route('shared.password',['slug' => 'slug']));
        }
        $response = $this->get(route('shared.password',['slug' => 'slug']));
        $response->assertSessionHas('status', false);
        $response->assertRedirect(route('rejected', ['message' => 'Too Many requests. Please try again later']));
    }
}
