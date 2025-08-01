<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileMoveException;
use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Models\Setting;
use Exception;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

class FileOperationsService
{
    private ?Filesystem $filesystem = null;
    private string $basePath;


    public function setFilesystem(?Filesystem $filesystem): void
    {
        $this->filesystem = $filesystem;
    }

    public function move(string $src, string $dest): void
    {
        if (!$this->makeFileSystem()) {
            return;
        }
        try {
            $this->filesystem->move($src, $dest);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Directory not empty')) {
                throw FileMoveException::directoryExists();
            }
            throw FileMoveException::couldNotMove();
        }
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

    public function makeFile(string $path): bool
    {
        if (!$this->makeFileSystem()) {
            return false;
        }
        if ($this->filesystem->fileExists($path)) {
            throw UploadFileException::fileExists();
        }
        if (
            file_put_contents(
                $this->basePath . DIRECTORY_SEPARATOR . $path,
                ''
            ) === false && $this->filesystem->fileExists($path) === false
        ) {
            return false;
        }

        return true;
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

        try {
            $visibility = ($permission & 0007) === 0 ? 'private' : 'public';
            $this->filesystem->createDirectory($path, ['visibility' => $visibility]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function directoryExists(string $path): bool
    {
        return $this->makeFileSystem() && $this->filesystem->directoryExists($path);
    }

    public function isWritable(string $path): bool
    {
        return $this->makeFileSystem() && is_writable($this->basePath . DIRECTORY_SEPARATOR . $path);
    }

    public function pathExistsAsFile(string $base, string $path): bool
    {
        while ($path !== '' && $path !== '.' && $path !== DIRECTORY_SEPARATOR) {
            if ($this->fileExists($base . $path)) {
                return true;
            }
            $path = dirname($path);
        }
        return false;
    }
}
