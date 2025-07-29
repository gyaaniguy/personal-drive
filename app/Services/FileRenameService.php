<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileRenameException;
use App\Models\LocalFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FileRenameService
{
    protected FileOperationsService $fileOperationsService;
    protected UUIDService $uuidService;
    private LPathService $pathService;

    public function __construct(
        LPathService $pathService,
        FileOperationsService $fileOperationsService,
        UUIDService $uuidService,
    ) {
        $this->pathService = $pathService;
        $this->fileOperationsService = $fileOperationsService;
        $this->uuidService = $uuidService;
    }

    public function renameFile(LocalFile $file, string $newFilename): void
    {
        $storageFolderName = $this->uuidService->getStorageFilesUUID();

        $itemPathName = $file->getPublicPathname();
        $itemPublicDestPathName = $file->getPublicPath() . $newFilename;
        $this->fileOperationsService->move(
            $storageFolderName . DIRECTORY_SEPARATOR . $itemPathName,
            $storageFolderName . DIRECTORY_SEPARATOR . $itemPublicDestPathName
        );
        $itemFileSystemDestPathName = $this->pathService->getStorageFolderPath() .
            DIRECTORY_SEPARATOR . $itemPublicDestPathName;

        if (!File::exists($itemFileSystemDestPathName)) {
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
        $dirPublicPathname = $file->getPublicPathname();
        $newFolderPublicPath = $file->getPublicPath()  . $newFilename;
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
