<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureFrontendBuiltTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_to_error_if_frontend_not_built()
    {
        // Remove manifest file if it exists
        if (file_exists(public_path('build/manifest.json'))) {
            $manifestContent = file_get_contents(public_path('build/manifest.json'));
            unlink(public_path('build/manifest.json'));
        }

        $response = $this->get(route('setup.account'));

        $response->assertRedirect(route('error', [
            'message' => 'Frontend not built. Ensure node, npm are installed Run "npm install && npm run build"'
        ]));

        // Restore manifest file for other tests
        if (isset($manifestContent)) {
            file_put_contents(public_path('build/manifest.json'), $manifestContent);
        }
    }

    public function test_redirects_with_correct_error_message()
    {
        // Remove manifest file
        if (file_exists(public_path('build/manifest.json'))) {
            $manifestContent = file_get_contents(public_path('build/manifest.json'));
            unlink(public_path('build/manifest.json'));
        }

        $response = $this->get(route('setup.account'));

        $response->assertRedirect(route('error', [
            'message' => 'Frontend not built. Ensure node, npm are installed Run "npm install && npm run build"'
        ]));

        // Restore manifest file for other tests
        if (isset($manifestContent)) {
            file_put_contents(public_path('build/manifest.json'), $manifestContent);
        }
    }

    public function test_does_not_redirect_to_error_when_manifest_exists()
    {
        if (!file_exists(public_path('build/manifest.json'))) {
            file_put_contents(public_path('build/manifest.json'), '{}');
        }

        $response = $this->get(route('setup.account'));

        // Should not redirect to error
        if ($response->isRedirect()) {
            $location = $response->headers->get('Location');
            $this->assertStringNotContainsString('/error', $location);
        } else {
            // If not a redirect, the test passes (middleware allowed the request)
            $this->assertTrue(true);
        }
    }

    public function test_post_does_not_redirect_to_error_when_manifest_exists()
    {
        if (!file_exists(public_path('build/manifest.json'))) {
            file_put_contents(public_path('build/manifest.json'), '{}');
        }

        $response = $this->post(route('setup.account'), [
            'username' => 'testuser',
            'password' => 'password',
        ]);

        // Should not redirect to error
        if ($response->isRedirect()) {
            $location = $response->headers->get('Location');
            $this->assertStringNotContainsString('/error', $location);
        } else {
            // If not a redirect, the test passes (middleware allowed the request)
            $this->assertTrue(true);
        }
    }

    public function test_redirects_to_error_for_get_when_manifest_missing()
    {
        // Remove manifest file if it exists
        if (file_exists(public_path('build/manifest.json'))) {
            $manifestContent = file_get_contents(public_path('build/manifest.json'));
            unlink(public_path('build/manifest.json'));
        }

        $response = $this->get(route('setup.account'));

        $response->assertRedirect(route('error', [
            'message' => 'Frontend not built. Ensure node, npm are installed Run "npm install && npm run build"'
        ]));

        // Restore manifest file for other tests
        if (isset($manifestContent)) {
            file_put_contents(public_path('build/manifest.json'), $manifestContent);
        }
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
