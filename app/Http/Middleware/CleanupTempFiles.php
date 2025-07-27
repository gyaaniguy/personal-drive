<?php

namespace App\Http\Middleware;

use App\Services\FileOperationsService;
use App\Services\UploadService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CleanupTempFiles
{
    protected UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->uploadService->cleanOldTempFiles();

        return $next($request);
    }
}
