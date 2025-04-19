<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class GenThumbnailRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => CommonRequest::localFileIdRules(),
            'path' => CommonRequest::pathRules()
        ];
    }
}
