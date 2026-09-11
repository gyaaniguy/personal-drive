<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class UploadFilesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'files' => 'required|array',
            'files.*' => 'required|file',
            'path' => CommonRequest::pathRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.uploaded' => 'The :attribute failed to upload. Check settings. Configure upload limits.',
        ];
    }
}
