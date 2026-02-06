<?php

namespace App\Http\Controllers\AuthControllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminRequests\TwoFactorCodeCheckRequest;
use App\Services\AdminConfigService;
use App\Services\LocalFileStatsService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    protected TwoFactorService $twoFactorService;

    protected LocalFileStatsService $localFileStatsService;

    public function __construct(
        TwoFactorService $twoFactorService
    ) {
        $this->twoFactorService = $twoFactorService;
    }
    public function index(Request $request)
    {
        return Inertia::render('Auth/TwoFactor');
    }

    public function store(TwoFactorCodeCheckRequest $request)
    {
        $userId = $request->session()->get('twoFactorUserId');
        $user = User::findOrFail($userId);

        $twoFactorCode = $request->validated('code');
        $twoFactorSecret = $user->getTwoFactorSecret();
        $isVerified = $this->twoFactorService->twoFactorCodeCheck($twoFactorCode, $twoFactorSecret);

        if ($isVerified) {
            $request->session()->forget('twoFactorUserId');
            Auth::login($user);
            $request->session()->regenerate();
            return redirect()->intended(route('drive', absolute: false));
        }

        return back()->withErrors(['code' => 'The provided two-factor code was invalid.']);
    }
}
