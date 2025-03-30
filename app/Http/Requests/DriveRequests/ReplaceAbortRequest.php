<?php

namespace App\Http\Requests\DriveRequests;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceAbortRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'action' => 'required|string',
        ];
    }
}
