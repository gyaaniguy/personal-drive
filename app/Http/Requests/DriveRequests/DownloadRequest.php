<?php

namespace App\Http\Requests\DriveRequests;

use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\CommonRequest;

class DownloadRequest extends FormRequest
{
    public function rules(): array
    {
        return CommonRequest::fileListRules();
    }
}
