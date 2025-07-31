<?php

namespace App\Http\Controllers\ShareControllers;

use App\Exceptions\PersonalDriveExceptions\ShareFileException;
use App\Http\Requests\DriveRequests\ShareFilesGenRequest;
use App\Models\LocalFile;
use App\Models\Share;
use App\Models\SharedFile;
use App\Services\LocalFileStatsService;
use App\Services\PathService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareFilesGenController
{
    use FlashMessages;

    protected PathService $pathService;

    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        PathService $pathService,
        LocalFileStatsService $localFileStatsService
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->pathService = $pathService;
    }

    public function index(ShareFilesGenRequest $request): RedirectResponse
    {
        $fileKeyArray = $request->validated('fileList');
        $slug = $request->validated('slug');
        $password = $request->validated('password');
        $expiry = $request->validated('expiry');
        $localFiles = LocalFile::getByIds($fileKeyArray)->get();

        $slug = $slug ?: Str::random(10);

        if (!$localFiles->count()) {
            throw ShareFileException::couldNotShare();
        }
        $hashedPassword = $password ? Hash::make($password) : null;

        $share = Share::add($slug, $hashedPassword, $expiry, $localFiles[0]->public_path);

        if (!$share) {
            throw ShareFileException::couldNotShare();
        }

        $sharedFiles = SharedFile::addArray($localFiles, $share->id);
        if (!$sharedFiles) {
            throw ShareFileException::couldNotShare();
        }

        $sharedLink = url('/') . '/shared/' . $slug;

        return redirect()->back()->with('shared_link', $sharedLink);
    }
}
