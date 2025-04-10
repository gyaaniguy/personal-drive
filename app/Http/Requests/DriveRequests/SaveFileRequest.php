<?php

namespace App\Http\Requests\DriveRequests;

use Illuminate\Foundation\Http\FormRequest;

class SaveFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => 'required|string|ulid',
            'content' => 'required|string|max:1000000',
        ];
    }
}
