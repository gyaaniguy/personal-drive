<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class ShareFilesGenRequest extends FormRequest
{
    public function rules(): array
    {
        return array_merge(
            CommonRequest::fileListRules(), [
            'slug' => ['nullable', 'unique:shares', CommonRequest::shareSlugRules()],
            'password' => CommonRequest::sharePasswordRules(),
            'expiry' => 'nullable|integer',
            ]
        );
    }
}
