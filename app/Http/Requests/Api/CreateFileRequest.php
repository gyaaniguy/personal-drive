<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class CreateFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => CommonRequest::itemNameRule(),
            'type' => 'required|in:file,folder',
            'path' => CommonRequest::pathRules(),
        ];
    }
}
