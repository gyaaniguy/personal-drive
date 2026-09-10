<?php

namespace App\Services;

use App\Models\LocalFile;
use Exception;
use Illuminate\Support\Facades\Log;
use SplFileInfo;

class FileSaveService
{
    public function __construct(
        private LocalFileStatsService $localFileStatsService,
        private PathService $pathService,
        private FileOperationsService $fileOperationsService,
    ) {
    }

    public function save(string $id, string $content): array
    {
        $localFile = LocalFile::getById($id);
        if (!$localFile) {
            return ['success' => false, 'message' => 'Could not find file'];
        }

        if ($localFile->file_type !== 'text' && $localFile->file_type !== 'empty') {
            return ['success' => false, 'message' => 'File is not a text file'];
        }

        $privatePathFile = $localFile->getPrivatePathNameForFile();
        if (!$privatePathFile) {
            return ['success' => false, 'message' => 'Could not find file'];
        }

        if (!is_file($privatePathFile) || !is_writable($privatePathFile)) {
            return ['success' => false, 'message' => 'Could not save file'];
        }

        try {
            if (file_put_contents($privatePathFile, $content, LOCK_EX) === false) {
                return ['success' => false, 'message' => 'Could not save file'];
            }

            $this->localFileStatsService->updateFileStats($localFile, new SplFileInfo($privatePathFile));

            return ['success' => true, 'message' => 'File saved successfully', 'file' => $localFile];
        } catch (Exception $e) {
            Log::error('Failed to save file', ['exception' => $e, 'file_id' => $id]);

            return ['success' => false, 'message' => 'Could not save file'];
        }
    }

    public function createItem(string $itemName, string $publicPath, bool $isFile): array
    {
        $privatePath = $this->pathService->genPrivatePathFromPublic($publicPath);

        $pathPlusRoot = $this->pathService->getPlusContentRoot($publicPath, $itemName);
        $created = $isFile
            ? $this->fileOperationsService->makeFile($pathPlusRoot)
            : $this->fileOperationsService->makeFolder($pathPlusRoot);

        if (!$created) {
            return ['success' => false, 'message' => 'Create ' . ($isFile ? 'file' : 'folder') . ' failed'];
        }

        $this->localFileStatsService->addItemPathStat($itemName, $privatePath, $publicPath, !$isFile);

        return ['success' => true, 'message' => 'Created ' . ($isFile ? 'file' : 'folder') . ' successfully'];
    }
}
