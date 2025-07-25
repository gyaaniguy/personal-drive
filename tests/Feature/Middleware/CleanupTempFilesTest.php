<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\CleanupTempFiles;
use App\Services\UploadService;
use Closure;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class CleanupTempFilesTest extends TestCase
{
    public function test_calls_clean_old_temp_files_on_upload_service()
    {
        $mockUploadService = Mockery::mock(UploadService::class);
        $mockUploadService->shouldReceive('cleanOldTempFiles')
            ->once();

        $middleware = new CleanupTempFiles($mockUploadService);

        $request = Request::create('/', 'GET');
        $next = function ($request) {
            return response('OK', 200);
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
