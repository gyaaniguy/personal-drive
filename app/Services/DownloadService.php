<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Helpers\DownloadHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class DownloadService
{
    protected DownloadHelper $downloadHelper;

    public function __construct(DownloadHelper $downloadHelper)
    {
        $this->downloadHelper = $downloadHelper;
    }

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

    /**
     * @throws FetchFileException
     */
    public function createZipFile(Collection $localFiles): string
    {
        $outputZipPath = '/tmp' . DS .
            'personal_drive_' . Str::random(4) . '_' . now()->format('Y_m_d') . '.zip';
        $this->downloadHelper->createZipArchive($localFiles, $outputZipPath);
        return $outputZipPath;
    }
}
