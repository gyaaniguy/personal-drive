<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Requests\DriveRequests\FileRenameRequest;
use App\Models\LocalFile;
use App\Services\FileRenameService;
use App\Traits\FlashMessages;
use Exception;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;

class FileRenameController extends Controller
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
            return $this->error('Could not find file');
        }
        $this->fileRenameService->renameFile($file, $filename);


        return $this->success('Renamed to ' . $filename);
    }
}
