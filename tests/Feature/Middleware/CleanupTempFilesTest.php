<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\CleanupTempFiles;
use App\Services\FileOperationsService;
use App\Services\UploadService;
use Closure;
use Illuminate\Http\Request;
use Mockery;
use Tests\Feature\BaseFeatureTest;
use Tests\TestCase;

class CleanupTempFilesTest extends BaseFeatureTest
{
    public function test_calls_clean_old_temp_files_on_upload_service()
    {
        $uploadService = Mockery::mock(UploadService::class);
        $uploadService->shouldReceive('cleanOldTempFiles')
            ->once();

        $middleware = new CleanupTempFiles($uploadService);

        $request = Request::create('/', 'GET');
        $next = function ($request) {
            return response('OK', 200);
        };

        $response = $middleware->handle($request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
