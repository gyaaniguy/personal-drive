<?php

namespace App\Traits;

use App\Services\DownloadService;
use Illuminate\Support\Facades\Session;

trait GuestResourceAuthorize
{
    protected function guestVerified(array $fileIds, DownloadService $downloadService): bool
    {
        $shareId = Session::get('share_id');
        if (!$shareId) {
            return false;
        }

        if (!$downloadService->hasGuestShareFileIdPermissions($shareId, $fileIds)) {
            return false;
        }
        return true;
    }
}
