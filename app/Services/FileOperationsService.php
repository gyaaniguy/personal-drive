<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileMoveException;
use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Cache;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

class FileOperationsService
{
    private ?Filesystem $filesystem = null;
    private string $basePath = '';
    private ?PathService $pathService = null;


    public function __construct(?PathService $pathService = null)
    {
        $this->pathService = $pathService ?? new PathService();
    }

    public function setFilesystem(?Filesystem $filesystem): void
    {
        $this->filesystem = $filesystem;
    }

    public function move(string $src, string $dest): void
    {
        if (!$this->makeFileSystem()) {
            return;
        }
        // $src/$dest are relative to the adapter root ($this->basePath).
        // Never let a move resolve outside the storage root through a symlink.
        if (
            !$this->isPathWithinStorageRoot($this->basePath . DS . $dest)
            || !$this->isPathWithinStorageRoot($this->basePath . DS . $src)
        ) {
            throw FileMoveException::invalidDestinationPath();
        }
        // One-at-a-time gate per destination: 2 close requests can pass,  second rename() overwrites the first
        $lock = Cache::lock('pd-move-' . md5($this->basePath . DS . $dest), 10);
        if (!$lock->get()) {
            throw FileMoveException::couldNotMove();
        }
        if ($this->fileExists($dest) || $this->directoryExists($dest)) {
            throw FileMoveException::directoryExists();
        }
        try {
            $this->filesystem->move($src, $dest);
        } catch (Exception) {
            throw FileMoveException::couldNotMove();
        } finally {
            $lock->release();
        }
    }


    private function isPathWithinStorageRoot(string $absolutePath): bool
    {
        if ($this->basePath === '' || !is_dir($this->basePath) || $this->pathService === null) {
            return true;
        }

        return $this->pathService->isWithinStorageRoot($absolutePath);
    }

    private function makeFileSystem(): bool
    {
        if (!$this->filesystem) {
            $this->basePath = Setting::getStoragePath();
            if (!$this->basePath) {
                return false;
            }

            $adapter = new LocalFilesystemAdapter($this->basePath);
            $this->filesystem = new Filesystem($adapter);
            return true;
        }

        return true;
    }

    public function directoryExists(string $path): bool
    {
        return $this->makeFileSystem() && $this->filesystem->directoryExists($path);
    }

    public function makeFile(string $path): bool
    {
        if (!$this->makeFileSystem()) {
            return false;
        }
        if ($this->filesystem->fileExists($path)) {
            throw UploadFileException::fileExists();
        }
        if (!$this->isPathWithinStorageRoot($this->basePath . DS . $path)) {
            throw UploadFileException::pathOutsideStorageRoot();
        }
        file_put_contents($this->basePath . DS . $path, '');
        return $this->filesystem->fileExists($path);
    }

    public function fileExists(string $path): bool
    {
        return $this->makeFileSystem() && $this->filesystem->fileExists($path);
    }

    public function makeFolder(string $path, int $permission = 0750): bool
    {
        if (!$this->makeFileSystem()) {
            return false;
        }
        if ($this->directoryExists($path)) {
            throw UploadFileException::noNewDir('folder');
        }
        if (!$this->isPathWithinStorageRoot($this->basePath . DS . $path)) {
            throw UploadFileException::pathOutsideStorageRoot();
        }

        try {
            $visibility = ($permission & 0007) === 0 ? 'private' : 'public';
            $this->filesystem->createDirectory($path, ['visibility' => $visibility]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function isWritable(string $path): bool
    {
        return $this->makeFileSystem() && is_writable($this->basePath . DS . $path);
    }

    public function pathExistsAsFile(string $base, string $path): bool
    {
        while ($path !== '' && $path !== '.' && $path !== DS) {
            if ($this->fileExists($base . $path)) {
                return true;
            }
            $path = dirname($path);
        }
        return false;
    }
}
