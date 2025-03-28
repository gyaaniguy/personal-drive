<?php

namespace App\Traits;

use Illuminate\Http\RedirectResponse;

trait FlashMessages
{
    public function success(string $message): RedirectResponse
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
