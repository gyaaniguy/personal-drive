<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'username',
        'is_admin',
        'password',
        'secret',
        'google2fa_secret',
        'google2fa_enabled'
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getTwoFactorStatus(): string
    {
        return $this->google2fa_enabled;
    }

    public function getTwoFactorSecret(): string
    {
        return $this->google2fa_secret ?? '';
    }

    public function setTwoFactorSecret(string $secret): bool
    {
        return $this->update(['google2fa_secret' => $secret]);
    }

    public function setTwoFactorStatus(bool $status): bool
    {
        return $this->update(['google2fa_enabled' => $status]);
    }
}
