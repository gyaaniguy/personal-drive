<?php

namespace App\Http\Requests\AdminRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class SetupAccountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => CommonRequest::usernameRules(),
            'password' => CommonRequest::passwordRules(),
        ];
    }
}
