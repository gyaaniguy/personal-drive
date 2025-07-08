<?php

namespace App\Traits;

use Illuminate\Http\RedirectResponse;

trait FlashMessages
{
    public function success(string $message, array $moreInfo = []): RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('status');
        if ($moreInfo) {
            session()->flash('more_info', $moreInfo);
        }

        return redirect()->back();
    }

    public function warn(string $message): RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('status');
        return redirect()->back();
    }

    public function error(string $message): RedirectResponse
    {
        session()->flash('message', $message);
        session()->flash('status', false);

        return redirect()->back();
    }
}
