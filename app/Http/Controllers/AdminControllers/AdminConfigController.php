<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequests\AdminConfigRequest;
use App\Models\LocalFile;
use App\Models\Setting;
use App\Services\AdminConfigService;
use App\Services\LocalFileStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminConfigController extends Controller
{
    protected AdminConfigService $adminConfigService;

    protected LocalFileStatsService $localFileStatsService;

    public function __construct(AdminConfigService $adminConfigService, LocalFileStatsService $localFileStatsService)
    {
        $this->adminConfigService = $adminConfigService;
        $this->localFileStatsService = $localFileStatsService;
    }

    public function index(Request $request): Response
    {
        $setupMode = (bool) $request->query('setupMode');
        $storagePath = Setting::getStoragePath();;

        return Inertia::render('Admin/Config', [
            'storage_path' => $storagePath,
            'php_max_upload_size' => $this->adminConfigService->getPhpUploadMaxFilesize(),
            'php_post_max_size' => $this->adminConfigService->getPhpPostMaxSize(),
            'php_max_file_uploads' => $this->adminConfigService->getPhpMaxFileUploads(),
            'setupMode' => $setupMode,
        ]);
    }

    public function update(AdminConfigRequest $request): RedirectResponse
    {
        $storagePath = $request->validated('storage_path');
        $storagePath = trim(rtrim($storagePath, '/'));
        $updateStoragePathRes = $this->adminConfigService->updateStoragePath($storagePath);
        session()->flash('message', $updateStoragePathRes['message']);
        session()->flash('status', $updateStoragePathRes['status']);
        if ($updateStoragePathRes['status']) {
            LocalFile::clearTable();
            $this->localFileStatsService->generateStats();

            return redirect()->route('drive');
        }

        return redirect()->back();
    }
}
