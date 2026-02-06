<?php

namespace Tests\Feature\Controllers\DriveControllers;

use App\Http\Middleware\HandleGuestShareMiddleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use Mockery;
use PragmaRX\Google2FAQRCode\Google2FA;
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
            ->with('000000', Mockery::any())
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
}
