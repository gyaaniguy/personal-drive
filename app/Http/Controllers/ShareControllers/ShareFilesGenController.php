<?php

namespace App\Http\Controllers\ShareControllers;

use App\Exceptions\PersonalDriveExceptions\ShareFileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\ShareFilesGenRequest;
use App\Services\ShareService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;

class ShareFilesGenController extends Controller
{
    use FlashMessages;

    public function __construct(private ShareService $shareService)
    {
    }

    public function index(ShareFilesGenRequest $request): RedirectResponse
    {
        $fileList = $request->validated('fileList');
        if ($request->validated('direct', false) && !$this->shareService->isSingleFile($fileList)) {
            throw ShareFileException::directNeedsSingleFile();
        }

        $result = $this->shareService->create(
            $request->validated('fileList'),
            $request->validated('slug', ''),
            $request->validated('password', ''),
            $request->validated('expiry', ''),
            $request->validated('direct', false),
        );

        if (!$result['success']) {
            return $this->error('No valid files to share. Try a Resync');
        }

        session()->flash('shared_link', $result['url']);
        return $this->success('Share created');
    }
}
