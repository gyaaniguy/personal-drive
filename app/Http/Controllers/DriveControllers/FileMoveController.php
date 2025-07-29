<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Requests\DriveRequests\MoveFilesRequest;
use App\Services\FileMoveService;
use App\Traits\FlashMessages;
use App\Http\Controllers\Controller;
use Exception;

class FileMoveController extends Controller
{
    use FlashMessages;

    protected FileMoveService $fileMoveService;

    public function __construct(FileMoveService $fileMoveService)
    {
        $this->fileMoveService = $fileMoveService;
    }

    public function update(MoveFilesRequest $request)
    {
        $fileKeyArray = $request->validated('fileList');
        $destinationPath = $request->validated('path');
        try {
            $res = $this->fileMoveService->moveFiles($fileKeyArray, $destinationPath);
        } catch (Exception $e) {
            return $this->error('Error: Could not move files');
        }

        return $res ? $this->success('Files moved successfully.') : $this->error('Error: Could not move files');
    }
}
