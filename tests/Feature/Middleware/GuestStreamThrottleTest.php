<?php

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\RateLimiter;
use Tests\Feature\BaseFeatureTest;

class GuestStreamThrottleTest extends BaseFeatureTest
{
    public function test_fetch_file_is_throttled_after_sixty_attempts(): void
    {
        RateLimiter::clear('setup|' . '127.0.0.1');

        // 60 attempts pass the limiter; each is rejected downstream (no
        // share session) but still counts against the guest bucket.
        for ($i = 0; $i < 60; $i++) {
            $this->get(route('drive.fetch-file', ['id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'slug' => 'nosuch']));
        }

        $response = $this->get(route('drive.fetch-file', ['id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'slug' => 'nosuch']));

        $response->assertRedirect(
            route('rejected', ['message' => 'Too Many requests. Please try again later'])
        );
    }

    public function test_download_files_is_throttled_after_sixty_attempts(): void
    {
        RateLimiter::clear('setup|' . '127.0.0.1');
        RateLimiter::clear(md5('guest' . '127.0.0.1'));

        for ($i = 0; $i < 60; $i++) {
            $this->post('/download-files', ['_token' => csrf_token(), 'fileList' => []]);
        }

        $response = $this->post('/download-files', ['_token' => csrf_token(), 'fileList' => []]);

        $response->assertRedirect(
            route('rejected', ['message' => 'Too Many requests. Please try again later'])
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeUserUsingSetup();
        $this->setupStoragePathPost();
    }
}
