<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use Illuminate\Database\Eloquent\Collection;
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
        return count($localFiles) === 1 && ! $localFiles[0]->is_dir;
    }

    public function createZipFile(Collection $localFiles): string
    {
        $outputZipPath = '/tmp' . DS .
            'personal_drive_' . uniqid('', true) . '_' . now()->format('Y_m_d') . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($outputZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw FetchFileException::couldNotZip();
        }

        foreach ($localFiles as $localFile) {
            $pathName = $localFile->getPrivatePathNameForFile();
            if (!is_readable($pathName)) {
                throw $this->missingFileException($zip, $outputZipPath);
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
                    if ($filePath === false || !is_readable($filePath)) {
                        throw $this->missingFileException($zip, $outputZipPath);
                    }

                    $relativePath = substr($filePath, strlen(dirname($pathName)) + 1);

                    $zip->addFile($filePath, $relativePath);
                }
            } else {
                $archivePath = str_replace(DS, '/', $localFile->getPublicPathPlusName());
                $zip->addFile($pathName, $archivePath);
            }
        }

        $zip->close();

        return $outputZipPath;
    }

    private function missingFileException(ZipArchive $zip, string $outputZipPath): FetchFileException
    {
        $zip->close();
        @unlink($outputZipPath);

        return FetchFileException::notFoundDownload();
    }
}
