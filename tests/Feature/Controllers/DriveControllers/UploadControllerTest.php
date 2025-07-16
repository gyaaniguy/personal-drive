<?php

namespace Tests\Feature\Controllers\DriveControllers;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::shouldReceive('call')
            ->once()
            ->with('migrate:fresh', ['--force' => true]);

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->post(route('setup.account'), [
            'username' => 'testuser',
            'password' => 'password',
        ]);
    }
    public function test_store_returns_error_when_no_files_uploaded()
    {
        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $response = $this->post(route('drive.upload'), [
            'path' => '/some/path',
        ]);
        $response->assertSessionHasErrors(['files' => 'The files field is required.']);
    }


}
