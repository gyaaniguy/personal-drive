<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileRenameException;
use App\Models\LocalFile;

class FileRenameService
{
    public function renameFile(LocalFile $file, string $filename): void
    {
        if (!rename($file->getPrivatePathNameForFile(), $file->private_path . DIRECTORY_SEPARATOR . $filename)) {
            throw FileRenameException::couldNotRename();
        }

        $updated = $file->update(['filename' => $filename]);

        if (!$updated) {
            throw FileRenameException::couldNotUpdateIndex();
        }
    }
}
