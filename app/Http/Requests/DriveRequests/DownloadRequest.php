<?php

namespace App\Http\Requests\DriveRequests;

use App\Http\Requests\CommonRequest;
use Illuminate\Foundation\Http\FormRequest;

class DownloadRequest extends FormRequest
{
    public function rules(): array
    {
        return CommonRequest::fileListRules();
    }
}
