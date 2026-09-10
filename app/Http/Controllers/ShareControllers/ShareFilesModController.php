<?php

namespace App\Http\Controllers\ShareControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\DriveRequests\ShareFilesModRequest;
use App\Services\ShareService;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;

class ShareFilesModController extends Controller
{
    use FlashMessages;

    public function __construct(private ShareService $shareService)
    {
    }

    public function delete(ShareFilesModRequest $request): RedirectResponse
    {
        $result = $this->shareService->delete($request->validated('id'));

        return $result['success']
            ? $this->success('Successfully deleted share')
            : $this->error('Error! could not delete share');
    }

    public function pause(ShareFilesModRequest $request): RedirectResponse
    {
        $result = $this->shareService->toggle($request->validated('id'));

        if (!$result['success']) {
            return $this->error('Error! could not find share');
        }

        return $this->success($result['share']->enabled ? 'Enabled' : 'Paused');
    }
}
