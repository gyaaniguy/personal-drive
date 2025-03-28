<?php

namespace App\Http\Requests\DriveRequests;

use Illuminate\Foundation\Http\FormRequest;

class FileRenameRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required|ulid',
            'filename' => [
                'required',
                'string',
                'not_regex:/[<>:;,?"*|\/]/',
            ],
        ];
    }
}
