<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Exceptions\PersonalDriveExceptions\TwoFactorException;
use App\Http\Middleware\HandleGuestShareMiddleware;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Mockery;
use PragmaRX\Google2FAQRCode\Google2FA;
use RuntimeException;
use Tests\Feature\BaseFeatureTest;
use const true;

class TwoFactorControllerTest extends BaseFeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
        $this->uploadMultipleFiles('');
    }

    private function getQrPost(): TestResponse
    {
        $response = $this->post(
            route('admin-config.two-factor-qr'),
            [
                '_token' => csrf_token(),
            ]
        );
        return $response;
    }

    private function twoFactorGenMockStub()
    {
        $response = $this->getQrPost();
        $response->assertJsonFragment(['status' => true]);
        $this->assertStringContainsString(
            '<svg',
            $response->json('message')
        );
    }


    private function postEnableTwoFactor($code): TestResponse
    {
        $response = $this->post(
            route('admin-config.two-factor-code-enable'),
            [
                '_token' => csrf_token(),
                'code' => $code,
            ]
        );
        return $response;
    }

    private function mockTwoFactor()
    {
        $google2FA = Mockery::mock(Google2FA::class);

        $google2FA->shouldReceive('verify')
            ->with('123456', Mockery::any())
            ->andReturn(true);

        $google2FA->shouldReceive('verify')
            ->with( '000000', Mockery::any() )
            ->andReturn(false);

        return $google2FA;
    }

    public function test_enable_two_factor_auth_enable_fail()
    {
        $this->twoFactorGenMockStub();

        $google2FA = $this->mockTwoFactor();
        $this->app->instance(Google2FA::class, $google2FA);

        $response = $this->postEnableTwoFactor('000000');
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Incorrect OTP. Please try again');
    }

    public function test_two_factor_validation_does_not_expose_verification_exception_message(): void
    {
        $google2FA = Mockery::mock(Google2FA::class);
        $google2FA
            ->shouldReceive('verify')
            ->once()
            ->with('123456', 'internal-secret')
            ->andThrow(new RuntimeException('Secret at /srv/private/two-factor.key is unreadable'));
        $service = new TwoFactorService($google2FA);

        try {
            $service->twoFactorCodeCheck('123456', 'internal-secret');
            $this->fail('Expected two-factor validation to throw');
        } catch (TwoFactorException $exception) {
            $this->assertSame('Could not validate two-factor code', $exception->getMessage());
        }
    }

    public function test_enable_two_factor_auth_enable()
    {
        $this->twoFactorGenMockStub();

        $google2FA = $this->mockTwoFactor();
        $this->app->instance(Google2FA::class, $google2FA);
        $response = $this->postEnableTwoFactor('123456');
        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Two Factor Authentication Enabled');
        $response = $this->postEnableTwoFactor('123456');
        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Two Factor is already enabled');
        $this->post(
            route('logout'),
            [
                '_token' => csrf_token(),
            ]
        );
        $this->assertGuest();
        $response = $this->post(
            '/login',
            [
                'username' => 'testuser',
                'password' => 'password',
            ]
        );
        $response->assertRedirect(route('login.two-factor-index'));
        $response = $this->post(
            route('login.two-factor-check'),
            [ 'code' => '000000']
        );

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Incorrect OTP. Please try again');

        $response = $this->post(
            route('login.two-factor-check'),
            [ 'code' => '123456']
        );

        $response->assertRedirect(route('drive'));
        $this->twoFactorGenMockStub();
        $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.10']);
        $response = $this->post(
            route('admin-config.two-factor-code-disable'),
            [
                '_token' => csrf_token(),
                'code' => '000000',
            ]
        );

        $response->assertSessionHas('status', false);
        $response->assertSessionHas('message', 'Incorrect OTP. Please try again');
        $response = $this->post(
            route('admin-config.two-factor-code-disable'),
            [
                '_token' => csrf_token(),
                'code' => '123456',
            ]
        );

        $response->assertSessionHas('status', true);
        $response->assertSessionHas('message', 'Two Factor Authentication Disabled');

        $this->post(
            route('logout'),
            [
                '_token' => csrf_token(),
            ]
        );
        $this->assertGuest();

        $response = $this->post(
            '/login',
            [
                'username' => 'testuser',
                'password' => 'password',
            ]
        );
        $response->assertRedirect(route('drive'));

    }

    public function test_four_wrong_codes_then_correct_code_still_succeeds()
    {
        $this->twoFactorGenMockStub();

        $google2FA = $this->mockTwoFactor();
        $this->app->instance(Google2FA::class, $google2FA);
        $this->postEnableTwoFactor('123456');

        $user = User::first();
        RateLimiter::clear('two-factor:' . $user->id);
        Auth::logout();
        $this->withSession(['twoFactorUserId' => $user->id]);

        for ($i = 0; $i < 4; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.' . $i]);
            $response = $this->post(
                route('login.two-factor-check'),
                ['code' => '000000']
            );
            $response->assertSessionHas('status', false);
            $response->assertSessionHas('message', 'Incorrect OTP. Please try again');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99']);
        $response = $this->post(
            route('login.two-factor-check'),
            ['code' => '123456']
        );
        $response->assertRedirect(route('drive'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_locked_user_cannot_authenticate_with_correct_code(): void
    {
        $this->twoFactorGenMockStub();
        $this->app->instance(Google2FA::class, $this->mockTwoFactor());
        $this->postEnableTwoFactor('123456');

        $user = User::first();
        RateLimiter::clear('two-factor:' . $user->id);
        Auth::logout();
        $this->withSession(['twoFactorUserId' => $user->id]);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.3.0.' . $i]);
            $this->post(route('login.two-factor-check'), ['code' => '000000']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.3.0.99']);
        $response = $this->post(route('login.two-factor-check'), ['code' => '123456']);

        $response->assertSessionHas('status', false);
        $response->assertSessionHas(
            'message',
            fn($message) => str_contains($message, 'Too many attempts')
        );
        $this->assertGuest();
    }

    public function test_sixth_wrong_code_within_window_returns_throttled_error()
    {
        $this->twoFactorGenMockStub();

        $google2FA = $this->mockTwoFactor();
        $this->app->instance(Google2FA::class, $google2FA);
        $this->postEnableTwoFactor('123456');

        $user = User::first();
        $this->withSession(['twoFactorUserId' => $user->id]);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.' . $i]);
            $response = $this->post(
                route('login.two-factor-check'),
                ['code' => '000000']
            );
            $response->assertSessionHas('status', false);
            $response->assertSessionHas('message', 'Incorrect OTP. Please try again');
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.99']);
        $response = $this->post(
            route('login.two-factor-check'),
            ['code' => '000000']
        );
        $response->assertSessionHas('status', false);
        $response->assertSessionHas(
            'message',
            fn($message) => str_contains($message, 'Too many attempts')
        );
    }

    public function test_another_users_failed_attempts_do_not_lock_out_this_user()
    {
        $google2FA = $this->mockTwoFactor();
        $this->app->instance(Google2FA::class, $google2FA);

        $firstUser = User::first();
        $secondUser = User::create(
            [
            'username' => 'seconduser',
            'is_admin' => 0,
            'password' => 'password',
            ]
        );

        foreach ([$firstUser, $secondUser] as $user) {
            $user->setTwoFactorSecret('secret');
            $user->setTwoFactorStatus(true);
        }

        // burn all five wrong attempts for the first user
        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['twoFactorUserId' => $firstUser->id]);
            $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.' . $i]);
            $response = $this->post(
                route('login.two-factor-check'),
                ['code' => '000000']
            );
            $response->assertSessionHas('status', false);
            $response->assertSessionHas('message', 'Incorrect OTP. Please try again');
        }

        // the second user's correct code is not affected (separate keys)
        $this->withSession(['twoFactorUserId' => $secondUser->id]);
        $this->withServerVariables(['REMOTE_ADDR' => '10.2.0.99']);
        $response = $this->post(
            route('login.two-factor-check'),
            ['code' => '123456']
        );
        $response->assertRedirect(route('drive'));
        $this->assertAuthenticatedAs($secondUser);
    }

    public function test_two_factor_index_page_is_not_throttled()
    {
        $user = User::first();
        $this->withSession(['twoFactorUserId' => $user->id]);

        // The 2FA *page* is a plain GET render; only the OTP-check POST is
        // throttled. Refresh the page repeatedly without being locked out.
        for ($i = 0; $i < 8; $i++) {
            $response = $this->get(route('login.two-factor-index'));
            $response->assertOk();
        }
    }
}
