<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class CreateItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'itemName' => [CommonRequest::itemNameRule()],
            'path' => CommonRequest::pathRules(),
            'isFile' => 'boolean',
        ];
    }
}
