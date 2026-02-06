<?php

namespace App\Http\Requests\AdminRequests;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorCodeCheckRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'numeric']
        ];
    }

}
