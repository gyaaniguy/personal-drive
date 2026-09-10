<?php

namespace App\Http\Controllers\AdminControllers;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequests\AdminConfigRequest;
use App\Http\Requests\AdminRequests\TwoFactorCodeCheckRequest;
use App\Models\Setting;
use App\Services\AdminConfigService;
use App\Services\TwoFactorService;
use App\Traits\FlashMessages;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminConfigController extends Controller
{
    use FlashMessages;

    protected AdminConfigService $adminConfigService;
    protected TwoFactorService $twoFactorService;

    public function __construct(
        AdminConfigService $adminConfigService,
        TwoFactorService $twoFactorService
    ) {
        $this->adminConfigService = $adminConfigService;
        $this->twoFactorService = $twoFactorService;
    }

    public function index(Request $request): Response
    {
        $setupMode = (bool)$request->query('setupMode');
        $storagePath = Setting::getStoragePath();
        $twoFactorStatus = $this->twoFactorService->getStatus();
        $show_two_factor_option = !config('app.disable_auth');

        return Inertia::render(
            'Admin/Settings',
            [
                'storage_path' => $storagePath,
                'php_max_upload_size' => $this->adminConfigService->getPhpUploadMaxFilesize(),
                'php_post_max_size' => $this->adminConfigService->getPhpPostMaxSize(),
                'php_max_file_uploads' => $this->adminConfigService->getPhpMaxFileUploads(),
                'setupMode' => $setupMode,
                'twoFactorStatus' => $twoFactorStatus,
                'show_two_factor_option' => $show_two_factor_option,
                'tokens' => Auth::user()->getApiTokens(),
                'server_configs' => config('api_docs.server_configs'),
                'api_sections' => config('api_docs.api_sections'),
            ]
        );
    }

    public function update(AdminConfigRequest $request): RedirectResponse
    {
        $result = $this->adminConfigService->updateStoragePath(
            $request->validated('storage_path')
        );

        return $result['status']
            ? $this->successTo('drive', $result['message'])
            : $this->error($result['message']);
    }

    public function twoFactorGetQr(): JsonResponse
    {
        $twoFactorSecret = $this->twoFactorService->getSecret();
        $qrCodeSvgStr = $this->twoFactorService->generateQr($twoFactorSecret);
        return ResponseHelper::json($qrCodeSvgStr);
    }

    public function twoFactorCodeEnable(TwoFactorCodeCheckRequest $request): RedirectResponse
    {
        if ($this->twoFactorService->isTwoFactorEnabled()) {
            return $this->error('Two Factor is already enabled');
        }
        $twoFactorCode = $request->validated('code');
        $twoFactorSecret = $this->twoFactorService->getSecret();
        $isVerified = $this->twoFactorService->twoFactorCodeCheck($twoFactorCode, $twoFactorSecret);
        if ($isVerified) {
            $this->twoFactorService->setSecret($twoFactorSecret);
            $this->twoFactorService->setStatus(true);
            return $this->success('Two Factor Authentication Enabled');
        }
        return $this->error('Incorrect OTP. Please try again');
    }

    public function twoFactorCodeDisable(TwoFactorCodeCheckRequest $request): RedirectResponse
    {
        if (!$this->twoFactorService->isTwoFactorEnabled()) {
            return $this->error('Two Factor is already disabled');
        }
        $twoFactorCode = $request->validated('code');
        $twoFactorSecret = $this->twoFactorService->getSecret();
        $isVerified = $this->twoFactorService->twoFactorCodeCheck($twoFactorCode, $twoFactorSecret);
        if ($isVerified) {
            $this->twoFactorService->setStatus(false);
            return $this->success('Two Factor Authentication Disabled');
        }
        return $this->error('Incorrect OTP. Please try again');
    }
}
