<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class MoveFilesRequest extends FormRequest
{
    public function rules(): array
    {
        return array_merge(CommonRequest::fileListRules(), [
            'destination' => ['required', ...CommonRequest::pathRules(allowSlash: true)],
        ]);
    }
}
