<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Models\LocalFile;
use Exception;
use FilesystemIterator;
use Illuminate\Support\Facades\Auth;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class LocalFileStatsService
{
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
        } catch (Exception $e) {
            throw UploadFileException::noNewDir($isDir ? 'folder' : 'file');
        }
    }

    private function getFileType(SplFileInfo $item): string
    {
        if ($item->isDir()) {
            return 'folder';
        }
        $mimeType = mime_content_type($item->getPathname());

        if (str_starts_with($mimeType, 'image/')) {
            $fileType = 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            $fileType = 'video';
        } elseif ($mimeType === 'application/pdf') {
            $fileType = 'pdf';
        } elseif (str_starts_with($mimeType, 'text/')) {
            $fileType = 'text';
        } elseif (str_contains($mimeType, 'x-empty')) {
            $fileType = 'empty';
        } else {
            $fileType = $item->getExtension();
        }

        return $fileType;
    }

    public function generateStats(string $path = ''): int
    {
        $privatePath = $this->pathService->genPrivatePathFromPublic($path);
        if (!$privatePath) {
            return false;
        }

        return $this->populateLocalFileWithStats($privatePath);
    }

    private function populateLocalFileWithStats(string $privatePath): int
    {
        $insertArr = [];
        $iterator = $this->createFileIterator($privatePath);
        $filesUpdated = 0;
        foreach ($iterator as $item) {
            $insertArr[] = $this->getFileItemDetails($item);
            // Insert in chunks of 100
            if (count($insertArr) === 100) {
                $filesUpdated += LocalFile::insertRows($insertArr);
                $insertArr = []; // Clear the array for the next chunk
            }
        }
        // Insert remaining items if any
        if (!empty($insertArr)) {
            $filesUpdated += LocalFile::insertRows($insertArr);
        }

        return $filesUpdated;
    }

    private function createFileIterator(string $path): RecursiveIteratorIterator
    {
        $directoryIterator = new RecursiveDirectoryIterator(
            $path,
            FilesystemIterator::SKIP_DOTS
        );

        return new RecursiveIteratorIterator(
            $directoryIterator,
            RecursiveIteratorIterator::CHILD_FIRST
        );
    }

    public function getFileItemDetails(SplFileInfo $item): array
    {
        $rootPathLen = strlen($this->pathService->getStorageFolderPath()) + 1;
        $privatePath = $item->getPath();
        $publicPath = substr($privatePath, $rootPathLen);
        return $this->getSplFileStats($item->getFilename(), $item->isDir(), $publicPath, $privatePath, $item);
    }

    public function updateFileStats(LocalFile $localFile, SplFileInfo $file): void
    {
        $localFile->update([
            'size' => $file->getSize(),
            'is_dir' => $file->isDir(),
            'file_type' => $this->getFileType($file),
        ]);
    }

    public function getSplFileStats(
        string $itemName,
        bool $isDir,
        string $publicPath,
        string $privatePath,
        SplFileInfo $file
    ): array {
        return [
            'filename' => $itemName,
            'is_dir' => $isDir ? 1 : 0,
            'public_path' => $publicPath,
            'private_path' => $privatePath,
            'size' => $file->isDir() ? '' : $file->getSize(),
            'user_id' => Auth::user()->id ?? 1,
            'file_type' => $this->getFileType($file)
        ];
    }
}
