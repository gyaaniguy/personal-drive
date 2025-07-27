<?php

namespace Tests\Helpers;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

trait SetupSite
{
    protected function makeUser(bool $isAdmin = true): User
    {
        $user = User::create([
            'username' => 'testuser',
            'is_admin' => $isAdmin,
            'password' => 'password',
        ]);
        $this->actingAs($user);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        return $user;
    }
}
