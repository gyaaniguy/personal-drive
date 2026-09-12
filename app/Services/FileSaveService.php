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

    /**
     * @return array{success: bool, message: string, code: int, file?: LocalFile}
     */
    public function save(string $id, string $content): array
    {
        $localFile = LocalFile::getById($id);
        if (!$localFile) {
            return ['success' => false, 'message' => 'Could not find file', 'code' => 404];
        }

        if ($localFile->file_type !== 'text' && $localFile->file_type !== 'empty') {
            return ['success' => false, 'message' => 'File is not a text file', 'code' => 422];
        }

        $privatePathFile = $localFile->getPrivatePathNameForFile();
        if (!$privatePathFile) {
            return ['success' => false, 'message' => 'Could not find file', 'code' => 404];
        }

        if (!is_file($privatePathFile) || !is_writable($privatePathFile)) {
            return ['success' => false, 'message' => 'Could not save file', 'code' => 422];
        }

        try {
            if (file_put_contents($privatePathFile, $content, LOCK_EX) === false) {
                return ['success' => false, 'message' => 'Could not save file', 'code' => 422];
            }

            $this->localFileStatsService->updateFileStats($localFile, new SplFileInfo($privatePathFile));

            return ['success' => true, 'message' => 'File saved successfully', 'code' => 200, 'file' => $localFile];
        } catch (Exception $e) {
            Log::error('Failed to save file', ['exception' => $e, 'file_id' => $id]);

            return ['success' => false, 'message' => 'Could not save file', 'code' => 422];
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
