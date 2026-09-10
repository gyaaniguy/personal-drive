<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Models\LocalFile;
use Exception;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class LocalFileStatsService
{
    private ?int $userId = null;
    private PathService $pathService;

    public function __construct(PathService $pathService)
    {
        $this->pathService = $pathService;
    }

    public function addItemPathStat(string $itemName, string $privatePath, string $publicPath, bool $isDir): void
    {
        $file = new SplFileInfo($privatePath . $itemName);

        try {
            LocalFile::create($this->getSplFileStats($itemName, $isDir, $publicPath, $privatePath, $file));
        } catch (Exception) {
            throw UploadFileException::noNewDir($isDir ? 'folder' : 'file');
        }
    }

    public function getSplFileStats(
        string      $itemName,
        bool        $isDir,
        string      $publicPath,
        string      $privatePath,
        SplFileInfo $file
    ): array {
        return [
            'filename' => $itemName,
            'is_dir' => $isDir ? 1 : 0,
            'public_path' => $publicPath,
            'private_path' => $privatePath,
            'size' => $isDir ? '' : $file->getSize(),
            'user_id' => $this->userId ??= auth()->user()->id,
            'file_type' => $this->getFileType($file, $isDir)
        ];
    }

    private function getFileType(SplFileInfo $item, bool $isDir): string
    {
        if ($isDir) {
            return 'folder';
        }
        $mimeType = mime_content_type($item->getPathname()) ?: '';

        if (str_starts_with($mimeType, 'image/')) {
            $fileType = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $fileType = 'video';
        } elseif ($mimeType === 'application/pdf') {
            $fileType = 'pdf';
        } elseif ($mimeType === 'text/html') {
            $fileType = 'html';
        } elseif (str_starts_with($mimeType, 'text/')) {
            $fileType = 'text';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            $fileType = 'audio';
        } elseif (str_contains($mimeType, 'x-empty')) {
            $fileType = 'empty';
        } else {
            $fileType = $item->getExtension();
        }

        return $fileType;
    }

    public function generateStats(string $path = '', array $files = []): int
    {
        $privatePath = $this->pathService->genPrivatePathFromPublic($path);
        if (!$privatePath) {
            return 0;
        }

        return $this->populateLocalFileWithStats($privatePath, $files);
    }

    private function populateLocalFileWithStats(string $privatePath, array $files = []): int
    {
        $destinationFullPaths = [];
        $batchSize = 100;
        foreach ($files as $file) {
            $fileNameWithUploadedPath = $this->pathService->sanitizeUploadPath($file->getClientOriginalPath());
            $parts = explode('/', $fileNameWithUploadedPath);
            $folderPath = '';
            for ($i = 0; $i < count($parts) - 1; $i++) {
                $folderPath .= $parts[$i] . '/';
                $destinationFullPaths[] = realpath($privatePath . $folderPath);
            }
            $destinationFullPaths[] = realpath($privatePath . $fileNameWithUploadedPath);
        }

        $items = collect($this->createFileIterator($privatePath));
        if ($destinationFullPaths) {
            $items = $items->filter(
                function ($item) use ($destinationFullPaths) {
                    return in_array($item->getPathname(), $destinationFullPaths);
                }
            );
        }
        $rootPathLen = $this->pathService->getRootPathLen();
        return $items
            ->map(fn($item) => $this->getFileItemDetails($item, $rootPathLen))
            ->chunk($batchSize)
            ->sum(fn($chunk) => LocalFile::insertRows($chunk->all()));
    }

    private function createFileIterator(string $path): RecursiveIteratorIterator
    {
        $directoryIterator = new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS
        );

        return new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::SELF_FIRST,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
    }

    public function getFileItemDetails(SplFileInfo $item, int $rootPathLen): array
    {
        $privatePath = $item->getPath();
        $publicPath = substr($privatePath, $rootPathLen);
        return $this->getSplFileStats($item->getFilename(), $item->isDir(), $publicPath, $privatePath, $item);
    }

    public function updateFileStats(LocalFile $localFile, SplFileInfo $file): void
    {
        $localFile->update(
            [
                'size' => $file->getSize(),
                'is_dir' => $file->isDir(),
                'file_type' => $this->getFileType($file, $file->isDir()),
            ]
        );
    }
}
