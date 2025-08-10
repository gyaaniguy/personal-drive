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


    public static function baseNameRule(): array
    {
        return [
            'string',
            // Block filesystem dangerous chars and control characters
            'regex:/^[^\x00-\x1f\x7f-\x9f<>\"|?*\x{200B}\x{200C}\x{200D}]+$/u',
            // Prevent just  . or space
            'not_regex:/^[\/\. ]+$/u',
            // Prevent directory traversal (`..` as a segment or the whole name)
            'not_regex:/(^|[\/\\\\])\.\.([\/\\\\]|$)/',
        ];
    }

    public static function itemNameRule(): array
    {
        return [
            'required',
            'max:255',
            ...self::baseNameRule(),
            // Block colon, slash, backslash
            'not_regex:/[:\/\\\\]/u',
        ];
    }

    public static function pathRules(): array
    {
        return [
            'nullable',
            ...self::baseNameRule(),
            'max:512',
        ];
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
