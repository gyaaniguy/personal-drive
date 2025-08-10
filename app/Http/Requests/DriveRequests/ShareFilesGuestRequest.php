<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class ShareFilesGuestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'slug' => ['required', ...CommonRequest::shareSlugRules()],
            'path' => CommonRequest::pathRules()
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->input('slug', '') ?: $this->route('slug', ''),
            'path' => $this->input('path', '') ?: $this->route('path', ''),
        ]);
    }
}
