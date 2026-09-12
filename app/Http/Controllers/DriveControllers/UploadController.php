<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\CreateItemRequest;
use App\Http\Requests\DriveRequests\ReplaceAbortRequest;
use App\Http\Requests\DriveRequests\UploadRequest;
use App\Services\PathService;
use App\Services\FileSaveService;
use App\Services\UploadService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class UploadController extends Controller
{
    use FlashMessages;

    public function __construct(
        protected PathService $pathService,
        protected UploadService $uploadService,
        protected FileSaveService $fileSaveService,
    ) {
    }

    public function store(UploadRequest $request): RedirectResponse
    {
        $files = $request->validated('files');
        $result = $this->uploadService->upload($files, $request->validated('path') ?? '', useTempForConflicts: true, swallowErrors: false, overwrite: true);
        $conflictsMessage = $this->uploadService->conflictsMessage($result['conflicts']);

        if ($result['duplicates'] > 0) {
            session([
                'new_file_copied_num' => $result['successful'],
                'duplicate_files_num' => $result['duplicates'],
            ]);
            return $this->success('Duplicates Detected' . $conflictsMessage, ['replaceAbort' => true]);
        }

        if ($result['successful'] > 0) {
            return $this->success('Files uploaded: ' . $result['successful'] . ' out of ' . count($files) . $conflictsMessage);
        }

        return $this->error('Some/All Files upload failed' . $conflictsMessage);
    }

    public function createItem(CreateItemRequest $request): RedirectResponse
    {
        $publicPath = $request->validated('path') ?? '';
        $itemName = $request->validated('itemName');
        $isFile = $request->validated('isFile');
        $publicPath = $this->pathService->cleanDrivePublicPath($publicPath);

        $result = $this->fileSaveService->createItem($itemName, $publicPath, (bool) $isFile);

        return $result['success']
            ? $this->success($result['message'])
            : $this->error($result['message']);
    }


    public function abortReplace(ReplaceAbortRequest $request): RedirectResponse
    {
        if ($request->validated('action') === 'abort') {
            $this->uploadService->cleanOldTempFiles();
            $new_file_copied_num = session()->pull('new_file_copied_num') ?? 0;
            $duplicate_files_num = session()->pull('duplicate_files_num') ?? 0;

            return $this->success(
                'New files copied: ' . $new_file_copied_num . '. Files skipped: ' . $duplicate_files_num
            );
        }
        if ($request->validated('action') === 'overwrite') {
            $res = $this->uploadService->syncTempToStorage();
            if (!$res) {
                return $this->error('overwriting failed !');
            }

            $new_file_copied_num = session()->pull('new_file_copied_num') ?? 0;
            $duplicate_files_num = session()->pull('duplicate_files_num') ?? 0;

            return $this->success(
                'New files copied: ' . $new_file_copied_num . '. Files overwritten: ' . $duplicate_files_num
            );
        }
        return Redirect::back();
    }
}
