<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileRenameException;
use App\Models\LocalFile;
use Illuminate\Support\Facades\DB;
use App\Helpers\FileOperationsHelper;
use Illuminate\Support\Facades\File;

class FileRenameService
{
    protected FileOperationsHelper $fileOperationsHelper;
    private LPathService $pathService;

    public function __construct(
        LPathService $pathService,
        FileOperationsHelper $fileOperationsHelper
    ) {
        $this->pathService = $pathService;
        $this->fileOperationsHelper = $fileOperationsHelper;
    }

    public function renameFile(LocalFile $file, string $newFilename): void
    {
        $itemPathName = $file->getPublicPathname();
        $itemPublicDestPathName = $file->public_path . DIRECTORY_SEPARATOR . $newFilename;
        $this->fileOperationsHelper->move($itemPathName, $itemPublicDestPathName);
        $itemPrivateDestPathName = $this->pathService->getStorageDirPath() .
            DIRECTORY_SEPARATOR . $itemPublicDestPathName;

        if (!File::exists($itemPrivateDestPathName)) {
            throw FileRenameException::couldNotRename();
        }

        if ($file->is_dir) {
            $this->updateDirChildrenRecursively($file, $newFilename);
        }

        $updated = $file->update(['filename' => $newFilename]);

        if (!$updated) {
            throw FileRenameException::couldNotUpdateIndex();
        }
    }

    public function updateDirChildrenRecursively(LocalFile $file, string $newFilename): void
    {
        $dirPublicPathname = ltrim($file->getPublicPathname(), '/');
        $newFolderPublicPath = ltrim($file->public_path .
            DIRECTORY_SEPARATOR . $newFilename, '/');
        LocalFile::getByPublicPathLikeSearch($dirPublicPathname)
            ->chunk(
                100,
                function ($childFiles) use ($dirPublicPathname, $newFolderPublicPath) {
                    $updates = [];
                    foreach ($childFiles as $childFile) {
                        $newPublicPath = $newFolderPublicPath . substr(
                            $childFile->public_path,
                            strlen($dirPublicPathname)
                        );
                        $newPrivatePath = $this->pathService->genPrivatePathFromPublic($newPublicPath);
                        $updates [] = [
                            'id' => $childFile->id,
                            'public_path' => $newPublicPath,
                            'private_path' => $newPrivatePath
                        ];
                    }

                    foreach ($updates as $update) {
                        DB::table('local_files')
                            ->where('id', $update['id'])
                            ->update([
                                'public_path' => $update['public_path'], 'private_path' => $update['private_path']
                            ]);
                    }
                }
            );
    }
}
