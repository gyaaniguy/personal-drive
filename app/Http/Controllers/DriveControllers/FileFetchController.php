<?php

namespace App\Http\Controllers\DriveControllers;

use App\Exceptions\PersonalDriveExceptions\FetchFileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\FetchFileRequest;
use App\Models\LocalFile;
use App\Services\LocalFileStatsService;
use App\Services\ShareAuthorizationService;
use App\Services\ThumbnailService;
use App\Traits\FlashMessages;
use App\Traits\GuestResourceAuthorize;
use Illuminate\Support\Facades\Session;
use Iman\Streamer\VideoStreamer;

class FileFetchController extends Controller
{
    use FlashMessages;
    use GuestResourceAuthorize;

    protected LocalFileStatsService $localFileStatsService;

    private ThumbnailService $thumbnailService;

    private ShareAuthorizationService $shareAuthorizationService;

    public function __construct(
        LocalFileStatsService $localFileStatsService,
        ThumbnailService $thumbnailService,
        ShareAuthorizationService $shareAuthorizationService
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->thumbnailService = $thumbnailService;
        $this->shareAuthorizationService = $shareAuthorizationService;
    }

    /**
     * @throws FetchFileException
     */
    public function index(FetchFileRequest $request)
    {
        $fileId = $request->validated('id');

        if (Session::get('share_id') && ! $this->guestVerified([$fileId], $this->shareAuthorizationService)) {
            throw FetchFileException::notFoundStream();
        }
        $file = $this->handleHashRequest($request);
        $filePrivatePathName = $file->getPrivatePathNameForFile();
        if (!is_file($filePrivatePathName) || !is_readable($filePrivatePathName)) {
            throw FetchFileException::notFoundStream();
        }

        if ($file->file_type === 'text') {
            return response()->stream(
                function () use ($filePrivatePathName) {
                    readfile($filePrivatePathName);
                },
                200,
                [
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma' => 'no-cache',
                    'Content-Type' => 'text/plain',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            );
        }

        $mimeType = mime_content_type($filePrivatePathName) ?: '';
        $streamInline = in_array($file->file_type, ['video', 'audio', 'pdf', 'html'], true)
            || ($file->file_type === 'image' && $mimeType !== 'image/svg+xml');

        $headers = ['X-Content-Type-Options' => 'nosniff'];
        if (! $streamInline) {
            $headers['Content-Disposition'] = 'attachment; filename="'
                . $this->sanitizeFilenameForHeader($file->filename)
                . '"';
        }
        if ($file->file_type === 'html') {
            // Render inline but neutralize scripts/same-origin access.
            $headers['Content-Security-Policy'] = 'sandbox';
        }

        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }

        $this->streamFile($filePrivatePathName);

        // Unreachable in production: VideoStreamer::streamFile() exits after
        // streaming. Only reached when streamFile() is stubbed in tests, where
        // it exposes the headers that were applied before streaming.
        return response()->make('', 200, $headers);
    }

    /**
     * Strip characters that could break the Content-Disposition header
     * (quote + CR/LF header injection) from the outgoing filename.
     */
    private function sanitizeFilenameForHeader(string $filename): string
    {
        return str_replace(['"', "\r", "\n"], '', $filename);
    }

    /**
     * @throws FetchFileException
     */
    private function handleHashRequest(FetchFileRequest $request): LocalFile
    {
        $fileId = $request->validated('id');

        $file = LocalFile::getById($fileId);
        if (! $file || ! $file->file_type) {
            throw FetchFileException::notFoundStream();
        }

        return $file;
    }

    /**
     * @throws FetchFileException
     */
    public function getThumb(FetchFileRequest $request): void
    {
        $fileId = $request->validated('id');

        if (Session::get('share_id') && ! $this->guestVerified([$fileId], $this->shareAuthorizationService)) {
            throw FetchFileException::notFoundStream();
        }
        $file = $this->handleHashRequest($request);
        if (! $file->has_thumbnail) {
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
