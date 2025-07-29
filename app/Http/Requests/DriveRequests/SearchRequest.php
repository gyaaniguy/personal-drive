<?php

namespace App\Http\Requests\DriveRequests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:25', 'regex:/^[a-zA-Z0-9\s\-_\.]+$/'],
        ];
    }
}
