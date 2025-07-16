<?php

namespace App\Services;

use App\Helpers\UploadFileHelper;
use App\Models\Setting;
use Exception;

class AdminConfigService
{
    private UUIDService $uuidService;
    protected UploadService $uploadService;
    public function __construct(UUIDService $uuidService, UploadService $uploadService)
    {
        $this->uuidService = $uuidService;
        $this->uploadService = $uploadService;
    }

    public function updateStoragePath(string $storagePath): array
    {
        try {
            $paths = $this->preparePaths($storagePath);

            if (file_exists($storagePath) && !is_writable($storagePath)) {
                return ['status' => false, 'message' => 'Storage directory exists but is not writable'];
            }

            if (!$this->ensureDirectoryExists($paths['storageFiles'])) {
                return [
                    'status' => false, 'message' => 'Unable to create or write to storage directory. Check Permissions'
                ];
            }

            if (!$this->ensureDirectoryExists($paths['thumbnails'])) {
                return [
                    'status' => false,
                    'message' => 'Unable to create or write to thumbnail directory. Check Permissions'
                ];
            }

            if (!Setting::updateSetting('storage_path', $storagePath)) {
                return ['status' => false, 'message' => 'Failed to save storage path setting'];
            }

            return ['status' => true, 'message' => 'Storage path updated successfully'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()];
        }
    }

    private function preparePaths(string $storagePath): array
    {
        return [
            'storageFiles' => $storagePath . DIRECTORY_SEPARATOR . $this->uuidService->getStorageFilesUUID(),
            'thumbnails' => $storagePath . DIRECTORY_SEPARATOR . $this->uuidService->getThumbnailsUUID(),
        ];
    }

    protected function ensureDirectoryExists(string $path): bool
    {
        if (file_exists($path)) {
            return is_writable($path);
        }

        return $this->uploadService->makeFolder($path) && is_writable($path);
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
