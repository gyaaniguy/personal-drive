<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreFavoriteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'local_file_ids' => ['required', 'array', 'min:1'],
            'local_file_ids.*' => CommonRequest::localFileIdRules(),
        ];
    }
}
