<?php

namespace App\Http\Requests\DriveRequests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\CommonRequest;

class FileRenameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => CommonRequest::localFileIdRules(),
            'filename' => CommonRequest::itemNameRule()
        ];
    }
}
