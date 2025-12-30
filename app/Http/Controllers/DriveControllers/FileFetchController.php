<?php

namespace App\Http\Controllers\DriveControllers;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\FetchFileRequest;
use App\Models\LocalFile;
use App\Services\DownloadService;
use App\Services\LocalFileStatsService;
use App\Services\ThumbnailService;
use App\Traits\FlashMessages;
use App\Traits\GuestResourceAuthorize;
use Iman\Streamer\VideoStreamer;
use Illuminate\Support\Facades\Session;

class FileFetchController extends Controller
{
    use FlashMessages;
    use GuestResourceAuthorize;

    protected LocalFileStatsService $localFileStatsService;
    private ThumbnailService $thumbnailService;
    private DownloadService $downloadService;

    public function __construct(
        LocalFileStatsService $localFileStatsService,
        ThumbnailService $thumbnailService,
        DownloadService $downloadService
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->thumbnailService = $thumbnailService;
        $this->downloadService = $downloadService;
    }

    /**
     * @throws FetchFileException
     */
    public function index(FetchFileRequest $request): void
    {
        $fileId = $request->validated('id');

        if (Session::get('share_id') && !$this->guestVerified([$fileId], $this->downloadService)) {
            throw FetchFileException::notFoundStream();
        }
        $file = $this->handleHashRequest($request);
        $filePrivatePathName = $file->getPrivatePathNameForFile();
        if ($file->file_type === 'text') {
            response()->stream(function () use ($filePrivatePathName) {
                readfile($filePrivatePathName);
            }, 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Content-Type' => 'text/plain',
            ])->send();
        } else {
            $this->streamFile($filePrivatePathName);
        }
    }

    /**
     * @throws FetchFileException
     */
    private function handleHashRequest(FetchFileRequest $request): LocalFile
    {
        $fileId = $request->validated('id');

        $file = LocalFile::find($fileId);
        if (!$file || !$file->file_type) {
            throw FetchFileException::notFoundStream();
        }

        return $file;
    }

    /**
     * @throws FetchFileException
     */
    public function getThumb(FetchFileRequest $request): void
    {
        $file = $this->handleHashRequest($request);
        if (!$file->has_thumbnail) {
            throw FetchFileException::notFoundThumb();
        }
        $filePrivatePathName = $this->thumbnailService->getFullFileThumbnailPath($file);
        if (file_exists($filePrivatePathName)) {
            $this->streamFile($filePrivatePathName);
        }
    }

    public function streamFile(string $filePrivatePathName): void
    {
        VideoStreamer::streamFile($filePrivatePathName);
    }
}
