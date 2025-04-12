<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class CreateItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'itemName' => 'required|string|max:255|regex:/^[a-zA-Z0-9_\- \.]+$/',
            'path' => CommonRequest::pathRules(),
            'isFile' => 'boolean',
        ];
    }
}
