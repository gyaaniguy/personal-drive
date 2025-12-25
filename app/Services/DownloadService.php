<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Models\Share;
use App\Models\SharedFile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class DownloadService
{
    /**
     * @throws FetchFileException
     */
    public function generateDownloadPath(Collection $localFiles): string
    {
        if ($this->isSingleFile($localFiles)) {
            return $localFiles[0]->getPrivatePathNameForFile();
        }
        return $this->createZipFile($localFiles);
    }

    public function isSingleFile(Collection $localFiles): bool
    {
        return count($localFiles) === 1 && !$localFiles[0]->is_dir;
    }

    public function createZipFile(Collection $localFiles): string
    {
        $outputZipPath = '/tmp' . DS .
            'personal_drive_' . Str::random(4) . '_' . now()->format('Y_m_d') . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($outputZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw FetchFileException::couldNotZip();
        }

        foreach ($localFiles as $localFile) {
            $pathName = $localFile->getPrivatePathNameForFile();
            if (!file_exists($pathName)) {
                continue;
            }

            if (is_dir($pathName)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($pathName),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $file) {
                    if ($file->isDir()) {
                        continue;
                    }

                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen(dirname($pathName)) + 1);

                    $zip->addFile($filePath, $relativePath);
                }
            } else {
                $zip->addFile($pathName, basename($pathName));
            }
        }

        $zip->close();

        return $outputZipPath;
    }

    public function hasGuestShareFileIdPermissions(int $shareId, array $fileIds): bool
    {
        $noFileIdsInRootDir = SharedFile::hasFileIdsInShare($shareId, $fileIds);
        $filesInPath = Share::getFilenamesByIds($shareId, $fileIds);

        if (!$noFileIdsInRootDir && $filesInPath->isEmpty()) {
            return false;
        }
        return true;
    }
}
