<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SaveFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'content' => 'required|string|max:1000000',
        ];
    }
}
