<?php

namespace App\Http\Requests\DriveRequests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\CommonRequest;

class FileManagerRequest extends FormRequest
{
    public function rules(): array
    {
        return ['path' => ['nullable', ...CommonRequest::pathRules()]];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(
            [
            'path' => $this->route('path'),
            ]
        );
    }
}
