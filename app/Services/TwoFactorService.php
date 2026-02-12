<?php

namespace App\Services;

use App\Models\Setting;
use Exception;
use Illuminate\Support\Facades\Auth;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorService
{
    private Setting $setting;
    private Google2FA $totp;


    public function __construct(
        Setting $setting,
        Google2FA $totp
    ) {
        $this->setting = $setting;
        $this->totp = $totp;
    }
    public function twoFactorCodeCheck(string $code, string $secret): bool
    {
        return $this->totp->verify($secret, $code);
    }
    public function generateTwoFactorSecret(): string
    {
        return $this->totp->generateSecretKey();
    }
    public function generateQr(string $secret): string
    {
        return $this->totp->getQRCodeInline(
            config('app.name'),
            Auth::user()->username,
            $secret
        );
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->getStatus();
    }

    public function setStatus(bool $status): bool
    {
        return Auth::user()->setTwoFactorStatus($status);
    }
    public function getStatus(): bool
    {
        return Auth::user()->getTwoFactorStatus();
    }
    public function getSecret(): string
    {
        return Auth::user()->getTwoFactorSecret();
    }

    public function setSecret(string $secret): bool
    {
        return Auth::user()->setTwoFactorSecret($secret);
    }
}
