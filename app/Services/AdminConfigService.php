<?php

namespace App\Services;

use App\Models\Setting;
use Exception;

class AdminConfigService
{
    protected FileOperationsService $fileOperationsService;

    private UUIDService $uuidService;
    private Setting $setting;

    public function __construct(
        UUIDService $uuidService,
        FileOperationsService $fileOperationsService,
        Setting $setting
    ) {
        $this->uuidService = $uuidService;
        $this->fileOperationsService = $fileOperationsService;
        $this->setting = $setting;
    }

    public function updateStoragePath(string $storagePath): array
    {
        try {
            if (!$this->updateSetting($storagePath)) {
                return ['status' => false, 'message' => 'Failed to save storage path setting'];
            }
            if (!$this->ensureDirectoryExists($this->uuidService->getStorageFilesUUID())) {
                $this->revertSetting();
                return [
                    'status' => false, 'message' => 'Unable to create storage directory. Check Permissions'
                ];
            }

            if (!$this->ensureDirectoryExists($this->uuidService->getThumbnailsUUID())) {
                $this->revertSetting();
                return [
                    'status' => false,
                    'message' => 'Unable to create thumbnail directory. Check Permissions'
                ];
            }

            return ['status' => true, 'message' => 'Storage path updated successfully'];
        } catch (Exception $e) {
            return ['status' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()];
        }
    }

    public function updateSetting(string $storagePath): bool
    {
        $res = $this->setting->updateSetting('storage_path', $storagePath);
        if ($res) {
            $this->fileOperationsService->setFilesystem(null);
        }
        return $res;
    }

    protected function ensureDirectoryExists(string $path): bool
    {
        if ($this->fileOperationsService->directoryExists($path)) {
            return $this->fileOperationsService->isWritable($path);
        }

        return $this->fileOperationsService->makeFolder($path) && $this->fileOperationsService->isWritable($path);
    }

    private function revertSetting(): bool
    {
        return $this->setting->revertStoragePath();
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
