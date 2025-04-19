<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class FetchFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'id' => CommonRequest::localFileIdRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }
}
