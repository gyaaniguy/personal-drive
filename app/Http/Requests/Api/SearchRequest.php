<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'max:255'],
            'per_page' => CommonRequest::perPageRules(),
        ];
    }
}
