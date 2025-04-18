<?php

namespace App\Http\Requests\DriveRequests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\CommonRequest;

class MoveFilesRequest extends FormRequest
{
    public function rules(): array
    {
        return array_merge(CommonRequest::fileListRules(), ['path' => CommonRequest::pathRules()]);
    }
}
