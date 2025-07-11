<?php

namespace Tests\Feature\Middleware;

use App\Models\Share;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class HandleGuestShareMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_to_rejected_if_no_slug_is_provided()
    {
        $response = $this->get('/shared'); // No slug in the URL

        $response->assertRedirect(route('rejected'));
    }

    public function test_redirects_to_login_if_share_not_found()
    {
        $response = $this->get(route('shared', ['slug' => 'nonexistent-slug']));

        $response->assertRedirect(route('login', ['slug' => 'nonexistent-slug']));
    }

    public function test_redirects_to_login_if_share_is_disabled()
    {
        $share = Share::factory()->create(['enabled' => false, 'slug' => 'disabled-slug']);

        $response = $this->get(route('shared', ['slug' => 'disabled-slug']));
        $response->assertRedirect(route('login', ['slug' => 'disabled-slug']));
    }

    public function test_redirects_to_login_if_share_is_expired()
    {
        $share = Share::factory()->create([
            'enabled' => true,
            'expiry' => 1, // 1 day expiry
            'created_at' => now()->subDays(2), // Created 2 days ago, so expired
            'slug' => 'expired-slug',
        ]);

        $response = $this->get(route('shared', ['slug' => 'expired-slug']));
        $response->assertRedirect(route('login', ['slug' => 'expired-slug']));
    }

    public function test_redirects_to_password_page_if_share_requires_password_and_not_authenticated()
    {
        $share = Share::factory()->create([
            'password' => 'password',
            'slug' => 'test-slug',
            'created_at' => now(),
            'expiry' => 1,
        ]);

        $response = $this->get(route('shared', ['slug' => 'test-slug']));

        $response->assertRedirect(route('shared.password', ['slug' => 'test-slug']));
    }

    public function test_allows_request_if_share_does_not_require_password()
    {
        $share = Share::factory()->create([
            'password' => null, // No password required
            'slug' => 'test-slug',
            'created_at' => now(),
            'expiry' => 1,
        ]);

        $response = $this->get(route('shared', ['slug' => 'test-slug']));

        $response->assertOk();
    }

    public function test_allows_request_if_share_requires_password_and_is_authenticated()
    {
        $share = Share::factory()->create([
            'password' => 'password',
            'slug' => 'test-slug',
            'created_at' => now(),
            'expiry' => 1,
        ]);

        Session::put('shared_test-slug_authenticated', true);

        $response = $this->get(route('shared', ['slug' => 'test-slug']));

        $response->assertOk();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the build directory exists for tests
        if (!file_exists(public_path('build'))) {
            mkdir(public_path('build'), 0777, true);
        }
        // Ensure manifest.json exists so EnsureFrontendBuilt middleware passes
        file_put_contents(public_path('build/manifest.json'), '{}');
        // Create a user to bypass PreventSetupAccess middleware
        User::factory()->create();
    }

    protected function tearDown(): void
    {
        // Clean up the manifest file after each test
        if (file_exists(public_path('build/manifest.json'))) {
            unlink(public_path('build/manifest.json'));
        }
        parent::tearDown();
    }
}
