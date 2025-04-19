<?php

namespace App\Http\Requests\DriveRequests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\CommonRequest;

class FileSaveRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => CommonRequest::localFileIdRules(),
            'content' => 'required|string|max:1000000',
        ];
    }
}
