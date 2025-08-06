<?php

namespace App\Http\Controllers\AdminControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequests\SetupAccountRequest;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SetupController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Admin/Setup');
    }

    public function update(SetupAccountRequest $request): RedirectResponse
    {
        Artisan::call('migrate:fresh', ['--force' => true]);
        try {
            $user = User::create([
                'username' => $request->username,
                'is_admin' => 1,
                'password' => bcrypt($request->password),
            ]);
            $message = 'Created User successfully';
            $status = true;
//            $request->session()->invalidate();
//            config(['session.driver' => 'database']);
            Auth::login($user, true);
//            $request->session()->regenerate();
        }
        catch (Exception) {
            $status = false;
            $message = 'Error. could not create user. Try re-installing, checking permissions for storage folder';
        }
        $request->session()->flash('status', $status);
        $request->session()->flash('message', $message);
        return redirect()->route('admin-config', ['setupMode' => true]);
    }
}
