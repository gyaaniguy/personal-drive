<?php

namespace App\Services;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

class FileSystemService
{
    private LPathService $pathService;

    public function __construct(LPathService $pathService)
    {
        $this->pathService = $pathService;
    }

    public function directoryExists(?string $path): bool
    {
        try {
            $fs = $this->getFilesystem();
            return $fs && $path && $fs->directoryExists($path);
        } catch (Throwable) {
            return false;
        }
    }

    public function getFilesystem(): ?Filesystem
    {
        $basepath = $this->pathService->getStorageFolderPath();

        if (!$basepath) {
            return null;
        }

        return new Filesystem(new LocalFilesystemAdapter($basepath));
    }

    public function isWritable(?string $path): bool
    {
        return $path && is_writable($path);
    }
}
