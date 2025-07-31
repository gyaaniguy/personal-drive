<?php

namespace Tests\Unit\Providers;

use App\Exceptions\PersonalDriveExceptions\ThrottleException;
use App\Models\Setting;
use App\Providers\AppServiceProvider;
use App\Services\UUIDService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
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

    public function test_rate_limiters_are_registered()
    {
        RateLimiter::clear('login');
        RateLimiter::clear('shared');

        (new AppServiceProvider($this->app))->boot();

        $loginLimit = RateLimiter::limiter('login');
        $sharedLimit = RateLimiter::limiter('shared');

        $this->assertNotNull($loginLimit);
        $this->assertNotNull($sharedLimit);

        $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $this->expectException(ThrottleException::class);
        $limit = $loginLimit($request);
        call_user_func($limit->responseCallback, $request, []);

        // Repeat for shared
        $this->expectException(ThrottleException::class);
        $limit = $sharedLimit($request);
        call_user_func($limit->responseCallback, $request, []);
    }
}
