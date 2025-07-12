<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureFrontendBuiltTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_request_if_frontend_is_built()
    {
        // Simulate manifest file existence
        if (!file_exists(public_path('build/manifest.json'))) {
            file_put_contents(public_path('build/manifest.json'), '{}');
        }

        $response = $this->get(route('setup.account'));

        $response->assertOk();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the build directory exists for tests
        if (!file_exists(public_path('build'))) {
            mkdir(public_path('build'), 0777, true);
        }
    }
}
