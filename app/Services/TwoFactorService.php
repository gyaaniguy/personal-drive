<?php

namespace App\Services;

use App\Exceptions\PersonalDriveExceptions\TwoFactorException;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Exceptions\IncompatibleWithGoogleAuthenticatorException;
use PragmaRX\Google2FA\Exceptions\InvalidCharactersException;
use PragmaRX\Google2FA\Exceptions\SecretKeyTooShortException;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorService
{
    private Google2FA $totp;


    public function __construct(
        Google2FA $totp
    ) {
        $this->totp = $totp;
    }
    public function twoFactorCodeCheck(string $code, string $secret): bool
    {
        try {
            return $this->totp->verify($code, $secret) === true ;
        } catch (Exception $e) {
            Log::error('Failed to validate two-factor code', ['exception' => $e]);

            throw TwoFactorException::couldNotValidate();
        }
    }
    public function generateTwoFactorSecret(): string
    {
        return $this->totp->generateSecretKey(32);
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
