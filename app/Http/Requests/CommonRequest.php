<?php

namespace App\Http\Requests;

use App\Rules\ValidPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CommonRequest extends FormRequest
{
    public static function slugRules(): array
    {
        return ['required', 'string', self::slugRegex(), 'max:20'];
    }

    public static function slugRegex(): string
    {
        return 'regex:/^[a-zA-Z0-9\-\_]{1,20}$/';
    }

    public static function pathRules(): array
    {
        return ['nullable', 'string', new ValidPath(), 'max:256'];
    }

    public static function passwordRules(): array
    {
        return ['required', 'string', Password::defaults()];
    }

    public static function fileListRules(): array
    {
        return [
            'fileList' => 'required|array',
            'fileList.*' => 'ulid',
        ];
    }

    public static function usernameRules(): array
    {
        return ['required', 'string', 'regex:/^[0-9a-z\_]+$/'];
    }

    public static function itemNameRule(): array
    {
        return [
            'required',
            'string',
            'max:255',
            // Block file system dangerous chars and ALL control characters
            'regex:/^[^\/<>:"|?*\x00-\x1f\x7f-\x9f\\\\]+$/u',
            // Prevent Windows reserved names
            'not_regex:/^(CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(\.|$)/i',        ];
    }

    public static function localFileIdRules(): array
    {
        return ['required', 'string', 'ulid'];
    }

    public static function sharePasswordRules(): array
    {
        return ['nullable', 'string', Password::min(6)];
    }
}
