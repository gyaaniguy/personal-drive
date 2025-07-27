<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminConfigService
{
    use RefreshDatabase;
    protected FileOperationsService $fileOperationsService;

    private UUIDService $uuidService;
    private Setting $setting;

    public function __construct(UUIDService $uuidService, FileOperationsService $fileOperationsService, Setting $setting)
    {
        $this->uuidService = $uuidService;
        $this->fileOperationsService = $fileOperationsService;
        $this->setting = $setting;
    }

    public function updateStoragePath(string $storagePath): array
    {
        try {
            $paths = $this->preparePaths($storagePath);

            if (
                $this->fileOperationsService->directoryExists($storagePath) &&
                !$this->fileOperationsService->isWritable($storagePath)
            ) {
                return ['status' => false, 'message' => 'Storage directory exists but is not writable'];
            }

            if (!$this->setting->updateSetting('storage_path', $storagePath)) {
                return ['status' => false, 'message' => 'Failed to save storage path setting'];
            }


            if (!$this->ensureDirectoryExists($paths['thumbnails'])) {
                return [
                    'status' => false,
                    'message' => 'Unable to create or write to thumbnail directory. Check Permissions'
                ];
            }

            if (!$this->ensureDirectoryExists($paths['storageFiles'])) {
                return [
                    'status' => false, 'message' => 'Unable to create or write to storage directory. Check Permissions'
                ];
            }


            return ['status' => true, 'message' => 'Storage path updated successfully'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()];
        }
    }

    private function preparePaths(string $storagePath): array
    {
        return [
            'storageFiles' =>  $this->uuidService->getStorageFilesUUID(),
            'thumbnails' =>  $this->uuidService->getThumbnailsUUID(),
        ];
        return [
            'storageFiles' => $storagePath . DIRECTORY_SEPARATOR . $this->uuidService->getStorageFilesUUID(),
            'thumbnails' => $storagePath . DIRECTORY_SEPARATOR . $this->uuidService->getThumbnailsUUID(),
        ];
    }

    protected function ensureDirectoryExists(string $path): bool
    {
        if ($this->fileOperationsService->directoryExists($path)) {
            return $this->fileOperationsService->isWritable($path);
        }

        return $this->fileOperationsService->makeFolder($path) && $this->fileOperationsService->isWritable($path);
    }

    public function getPhpUploadMaxFilesize(): string
    {
        return (string) ini_get('upload_max_filesize');
    }

    public function getPhpPostMaxSize(): string
    {
        return (string) ini_get('post_max_size');
    }

    public function getPhpMaxFileUploads(): string
    {
        return (string) ini_get('max_file_uploads');
    }
}
