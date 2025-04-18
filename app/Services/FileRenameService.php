<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\FileRenameException;
use App\Models\LocalFile;
use Illuminate\Support\Facades\DB;

class FileRenameService
{
    private LPathService $pathService;

    public function __construct(LPathService $pathService)
    {
        $this->pathService = $pathService;
    }
    public function renameFile(LocalFile $file, string $newFilename): void
    {
        if ($file->is_dir) {
            $dirPublicPathname = ltrim($file->getPublicPathname(), '/');
            $newFolderPublicPath = ltrim($file->public_path. DIRECTORY_SEPARATOR . $newFilename, '/');

            LocalFile::where("public_path", "like", $dirPublicPathname."%")
                ->chunk(
                    100,
                    function ($childFiles) use ($dirPublicPathname, $newFolderPublicPath) {
                        $updates = [];

                        foreach ($childFiles as $childFile) {
                            $newPublicPath = $newFolderPublicPath . substr($childFile->public_path, strlen($dirPublicPathname));
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
                                ->update(['public_path' => $update['public_path'], 'private_path' => $update['private_path']]);
                        }
                    }
                );
        }
        if (!rename($file->getPrivatePathNameForFile(), $file->private_path . DIRECTORY_SEPARATOR . $newFilename)) {
            throw FileRenameException::couldNotRename();
        }

        $updated = $file->update(['filename' => $newFilename]);

        if (!$updated) {
            throw FileRenameException::couldNotUpdateIndex();
        }
    }
}
