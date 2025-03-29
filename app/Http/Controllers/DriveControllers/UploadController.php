<?php

namespace App\Http\Controllers\DriveControllers;

use App\Exceptions\PersonalDriveExceptions\UploadFileException;
use App\Helpers\UploadFileHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\CreateFolderRequest;
use App\Http\Requests\DriveRequests\UploadRequest;
use App\Services\LocalFileStatsService;
use App\Services\LPathService;
use App\Traits\FlashMessages;
use Error;
use Illuminate\Http\RedirectResponse;

class UploadController extends Controller
{
    use FlashMessages;

    protected LPathService $lPathService;

    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        LPathService $lPathService,
        LocalFileStatsService $localFileStatsService
    ) {
        $this->localFileStatsService = $localFileStatsService;
        $this->lPathService = $lPathService;
    }

    public function store(UploadRequest $request): RedirectResponse
    {
        $files = $request->validated('files') ?? [];
        $publicPath = $request->validated('path') ?? '';
        $publicPath = $this->lPathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->lPathService->genPrivatePathWithPublic($publicPath);

        if (! $files) {
            return $this->error('File upload failed. No files uploaded');
        }
        if (! $privatePath) {
            return $this->error('File upload failed. Could not find storage path');
        }
        $successfulUploads = $this->processFiles($files, $privatePath);

        if ($successfulUploads > 0) {
            $this->localFileStatsService->generateStats($publicPath);

            return $this->success('Files uploaded: '.$successfulUploads.' out of '.count($files));
        }

        return $this->error('Some/All Files upload failed');
    }

    private function processFiles(array $files, string $privatePath): int
    {
        $successfulUploads = 0;
        foreach ($files as $index => $file) {
            $fileNameWithDir = UploadFileHelper::getUploadedFileFullPath($index);
            $filesDirectory = dirname($privatePath.$fileNameWithDir);
            if (! file_exists($filesDirectory)) {
                UploadFileHelper::makeFolder($filesDirectory);
            }
            try {
                if ($file->move($filesDirectory, $file->getClientOriginalName())) {
                    chmod($filesDirectory . '/' . $file->getClientOriginalName(), 0640);
                    $successfulUploads++;
                }
            } catch (Error $e) {
                throw UploadFileException::outofmemory();
            }
        }

        return $successfulUploads;
    }

    public function createFolder(CreateFolderRequest $request): RedirectResponse
    {
        $publicPath = $request->validated('path') ?? '';
        $folderName = $request->validated('folderName');
        $publicPath = $this->lPathService->cleanDrivePublicPath($publicPath);
        $privatePath = $this->lPathService->genPrivatePathWithPublic($publicPath);
        $makeFolderRes = UploadFileHelper::makeFolder($privatePath.$folderName);
        if (! $makeFolderRes) {
            return $this->error('Create folder failed');
        }
        $this->localFileStatsService->addFolderPathStat($folderName, $publicPath);

        return $this->success('Created folder successfully');
    }
}
