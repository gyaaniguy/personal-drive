<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Requests\DriveRequests\FileRenameRequest;
use App\Models\LocalFile;
use App\Services\FileRenameService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;

class FileRenameController
{
    use FlashMessages;



    protected FileRenameService $fileRenameService;

    public function __construct(
        FileRenameService $fileRenameService
    ) {
        $this->fileRenameService = $fileRenameService;
    }

    public function index(FileRenameRequest $request): RedirectResponse
    {
        $id = $request->validated('id');
        $filename = $request->validated('filename');

        $file = LocalFile::getById($id);
        if (!$file || !$file->getPrivatePathNameForFile()) {
            return $this->error('Could not find file!');
        }
        try {
            $this->fileRenameService->renameFile($file, $filename);
        } catch (\Exception $e) {
            return $this->error('Could not rename file. File with same name exists? Also Check permissions. ');
        }

        return $this->success('Renamed to '. $filename);
    }

}
