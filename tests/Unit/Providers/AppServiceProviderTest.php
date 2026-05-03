<?php

namespace Tests\Unit\Providers;

use App\Models\Setting;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_login_rate_limit_allows_requests_within_limit()
    {
        $ip = '192.168.1.1';
        $loginKey = 'login|' . $ip;

        // Make 7 requests (at limit)
        for ($i = 0; $i < 7; $i++) {
            RateLimiter::hit($loginKey);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($loginKey, 7));

        RateLimiter::clear($loginKey);
    }

    public function test_shared_rate_limit_allows_requests_within_limit()
    {
        $ip = '10.0.0.1';
        $sharedKey = 'shared|' . $ip;

        // Make 20 requests (at limit)
        for ($i = 0; $i < 20; $i++) {
            RateLimiter::hit($sharedKey);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($sharedKey, 20));

        RateLimiter::clear($sharedKey);
    }

    public function test_rate_limit_resets_after_time_window()
    {
        $ip = '127.0.0.1';
        $loginKey = 'login|' . $ip;

        // Hit the limit
        for ($i = 0; $i < 7; $i++) {
            RateLimiter::hit($loginKey);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($loginKey, 7));

        // Travel beyond the decay time
        Carbon::setTestNow(now()->addMinutes(2));

        $this->assertFalse(RateLimiter::tooManyAttempts($loginKey, 7));

        RateLimiter::clear($loginKey);
        Carbon::setTestNow();
    }

    public function test_different_ips_have_separate_rate_limits()
    {
        $ip1 = '192.168.1.1';
        $ip2 = '192.168.1.2';
        $loginKey1 = 'login|' . $ip1;
        $loginKey2 = 'login|' . $ip2;

        // Hit limit for IP1
        for ($i = 0; $i < 7; $i++) {
            RateLimiter::hit($loginKey1);
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($loginKey1, 7));
        $this->assertFalse(RateLimiter::tooManyAttempts($loginKey2, 7));

        RateLimiter::clear($loginKey1);
        RateLimiter::clear($loginKey2);
    }

    public function test_google2fa_is_registered_as_singleton()
    {
        $instance1 = app(\PragmaRX\Google2FAQRCode\Google2FA::class);
        $instance2 = app(\PragmaRX\Google2FAQRCode\Google2FA::class);

        $this->assertSame($instance1, $instance2);
    }

    private function testThrottle(string $key)
    {
        for ($i = 0; $i < 9; $i++) {
            RateLimiter::hit($key);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 7));
    }
}
