<?php

namespace Tests\Helpers;

use App\Services\UploadService;
use App\Services\LPathService;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;
use Illuminate\Filesystem\Filesystem;
use Mockery;

trait CreatesUploadService
{
    protected function makeUploadService(): UploadService
    {
        return new UploadService(
            Mockery::mock(LPathService::class),
            Mockery::mock(LocalFileStatsService::class),
            Mockery::mock(ThumbnailService::class),
            Mockery::mock(Filesystem::class)
        );
    }
}
