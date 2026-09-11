<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class RenameFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => CommonRequest::itemNameRule(),
        ];
    }
}
