<?php

namespace App\Http\Requests\AdminRequests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleTwoFactorRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'twoFactorStatus' => "present",
        ];
    }

}
