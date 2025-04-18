<?php

namespace App\Http\Requests\AdminRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class SetupAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'regex:/^[0-9a-z\_]+$/'],
            'password' => ['required', Password::min(8)],
        ];
    }
}
