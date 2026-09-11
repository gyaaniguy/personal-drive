<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class ListFavoritesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'per_page' => CommonRequest::perPageRules(),
        ];
    }
}
