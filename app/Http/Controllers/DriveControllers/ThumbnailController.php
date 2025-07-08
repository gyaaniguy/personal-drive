<?php

namespace App\Http\Controllers\DriveControllers;

use App\Http\Requests\DriveRequests\GenThumbnailRequest;
use App\Models\LocalFile;
use App\Services\ThumbnailService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;
use App\Http\Controllers\Controller;

class ThumbnailController extends Controller
{
    use FlashMessages;

    private ThumbnailService $thumbnailService;

    public function __construct(ThumbnailService $thumbnailService)
    {
        $this->thumbnailService = $thumbnailService;
    }

    public function update(GenThumbnailRequest $request): RedirectResponse
    {
        $fileIds = $request->validated('ids');
        $publicPath = $request->validated('path') ?? '';
        $publicPath = preg_replace('#^/drive/?#', '', $publicPath);
        if (!$fileIds) {
            session()->flash('message', 'Could not generate thumbnails');
        }
        LocalFile::setHasThumbnail($fileIds);
        $thumbsGenerated = $this->thumbnailService->genThumbnailsForFileIds($fileIds);
        if ($thumbsGenerated === 0) {
            session()->flash('message', 'No thumbnails generated. No valid files found');
        }
        return redirect()->route('drive', ['path' => $publicPath]);
    }
}
