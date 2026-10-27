<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Tests\Feature\BaseFeatureTest;

class SetupThrottleTest extends BaseFeatureTest
{
    public function test_setup_account_post_is_throttled_after_ten_attempts(): void
    {
        User::query()->delete();

        // 10 attempts pass the limiter but fail validation (short password),
        // so the controller - and its migrate:fresh - never runs.
        $payload = [
            '_token' => csrf_token(),
            'username' => 'attacker',
            'password' => 'short',
        ];

        for ($i = 0; $i < 10; $i++) {
            $this->post(route('setup.account'), $payload);
        }

        $response = $this->post(route('setup.account'), $payload);

        // With no users, CheckSetup would redirect /rejected onward to setup,
        // so assert the throttle redirect target directly instead of following.
        $response->assertRedirect(
            route('rejected', ['message' => 'Too Many requests. Please try again later'])
        );
        $this->assertDatabaseCount('users', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure no user exists before each test that creates a user
        User::query()->delete();
    }
}
