<?php

namespace Tests\Unit\Providers;

use App\Models\Setting;
use App\Providers\AppServiceProvider;
use App\Services\UUIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Mockery;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_uuid_service_is_bound()
    {
        $settingMock = Mockery::mock(Setting::class);
        $settingMock->shouldReceive('getSettingByKeyName')
            ->with('uuidForStorageFiles')->andReturn('uuid-1');
        $settingMock->shouldReceive('getSettingByKeyName')
            ->with('uuidForThumbnails')->andReturn('uuid-2');

        $this->app->instance(Setting::class, $settingMock);

        (new AppServiceProvider($this->app))->register();

        $resolved = $this->app->make(UUIDService::class);
        $this->assertInstanceOf(UUIDService::class, $resolved);
    }

    public function test_login_and_shared_rate_limiting_enforced()
    {
        $ip = '127.0.0.1';
        $loginKey = 'login|' . $ip;
        $sharedKey = 'shared|' . $ip;

        // Hit login limit
        $this->testThrottle($loginKey);
        $this->testThrottle($sharedKey);

        // Travel forward to simulate decay
        Carbon::setTestNow(now()->addSeconds(61));
        $this->assertFalse(RateLimiter::tooManyAttempts($loginKey, 7));
        $this->assertFalse(RateLimiter::tooManyAttempts($sharedKey, 20));

        RateLimiter::clear($loginKey);
        RateLimiter::clear($sharedKey);
        Carbon::setTestNow();
    }

    private function testThrottle(string $key)
    {
        for ($i = 0; $i < 9; $i++) {
            RateLimiter::hit($key);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 7));
    }
}
