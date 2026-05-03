<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure manifest exists so EnsureFrontendBuilt passes
        if (!file_exists(public_path('build/manifest.json'))) {
            if (!file_exists(public_path('build'))) {
                mkdir(public_path('build'), 0777, true);
            }
            file_put_contents(public_path('build/manifest.json'), '{}');
        }
    }

    public function test_middleware_allows_request_without_proxy_configuration()
    {
        Config::set('app.proxy_ips', null);

        $response = $this->get('/drive');

        // Should redirect to setup since no user exists
        $response->assertRedirect('/setup/account');
    }

    public function test_middleware_with_custom_proxy_headers()
    {
        Config::set('app.proxy_ips', '192.168.1.1');
        Config::set(
            'app.proxy_headers',
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST
        );

        $this->get('/drive')->assertStatus(302);
    }

    public function test_middleware_with_multiple_proxy_ips()
    {
        Config::set('app.proxy_ips', ['192.168.1.1', '10.0.0.1', '172.16.0.1']);

        $this->get('/drive')->assertStatus(302);
    }

    public function test_middleware_with_default_headers_when_not_configured()
    {
        Config::set('app.proxy_ips', '192.168.1.1');
        Config::set('app.proxy_headers', null);

        $this->get('/drive')->assertStatus(302);
    }

    public function test_middleware_allows_request_through_for_authenticated_user()
    {
        $user = $this->createUser();

        $this->actingAs($user)->get('/drive')->assertStatus(200);
    }

    protected function createUser()
    {
        return \App\Models\User::create([
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'is_admin' => true,
        ]);
    }
}
