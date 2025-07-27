<?php

namespace Tests\Helpers;

use App\Services\FileOperationsService;
use App\Services\LPathService;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;
use Illuminate\Filesystem\Filesystem;
use Mockery;

trait CreatesUploadService
{
    protected function makeUploadService(): FileOperationsService
    {
        return new FileOperationsService(
            Mockery::mock(LPathService::class)
        );
    }
}
