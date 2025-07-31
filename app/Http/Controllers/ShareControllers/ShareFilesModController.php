<?php

namespace App\Http\Controllers\ShareControllers;

use App\Http\Requests\DriveRequests\ShareFilesModRequest;
use App\Models\Share;
use App\Traits\FlashMessages;
use Illuminate\Http\RedirectResponse;

class ShareFilesModController
{
    use FlashMessages;

    public function delete(ShareFilesModRequest $request): RedirectResponse
    {
        $shareId = $request->validated('id');
        if (Share::whereById($shareId)->delete()) {
            return $this->success('Successfully deleted share');
        }

        return $this->error('Error! could not delete share');
    }

    public function pause(ShareFilesModRequest $request): RedirectResponse
    {
        $shareId = $request->validated('id');
        $share = Share::whereById($shareId)->first();

        if (!$share) {
            return $this->error('Error! could not find share');
        }

        $update = Share::whereById($shareId)->update(['enabled' => !$share->enabled]);
        if ($update) {
            return $this->success($share->enabled ? 'Paused' : 'Enabled');
        }

        return $this->error('Error! could not pause share');
    }
}
