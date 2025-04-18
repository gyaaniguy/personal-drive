<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ShareFilesGenRequest extends FormRequest
{
    public function rules(): array
    {
        return array_merge(CommonRequest::fileListRules(), [
            'slug' =>  ['nullable', 'unique:shares', 'string', CommonRequest::slugRegex()],
            'password' => ['nullable', Password::min(6)],
            'expiry' => 'nullable|integer',
        ]);
    }
}
