<?php

namespace App\Helpers;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use App\Services\LPathService;

class FileOperationsHelper
{
    private LPathService $pathService;
    private Filesystem $filesystem;

    public function __construct(LPathService $pathService)
    {
        $this->pathService = $pathService;

        $basePath = $this->pathService->getStorageDirPath();
        $adapter = new LocalFilesystemAdapter($basePath);
        $this->filesystem = new Filesystem($adapter);
    }

    public function move(string $src, string $dest)
    {
        return $this->filesystem->move($src, $dest);
    }

}
