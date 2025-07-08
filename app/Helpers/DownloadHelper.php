<?php

namespace App\Helpers;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use Illuminate\Database\Eloquent\Collection;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class DownloadHelper
{
    /**
     * @throws FetchFileException
     */
    public function createZipArchive(Collection $localFiles, string $outputZipPath): ZipArchive
    {
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

        return $zip;
    }
}
