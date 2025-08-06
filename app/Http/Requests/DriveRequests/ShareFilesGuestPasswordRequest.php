<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class ShareFilesGuestPasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'slug' => CommonRequest::slugRules(),
            'password' => ['required', 'string', 'max:50'],
        ];
    }
}
