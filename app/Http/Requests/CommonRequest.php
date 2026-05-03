<?php

namespace App\Http\Requests;

use App\Rules\ValidPath;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CommonRequest extends FormRequest
{
    // Block filesystem dangerous chars and control characters
    public const SAFE_CHARS_REGEX = '/^[^\x00-\x1f\x7f-\x9f<>\"|?*\x{200B}\x{200C}\x{200D}]+$/u';
    // Prevent just . or space
    public const DOTS_OR_SPACES_REGEX = '/^[\/\. ]+$/u';
    // Prevent directory traversal (`..` as a segment or the whole name)
    public const TRAVERSAL_REGEX = '/(^|[\/\\\\])\.\.([\/\\\\]|$)/';

    public static function shareSlugRules(): array
    {
        return [
              ...self::baseNameRule(), 'not_regex:/[ :\/\\\\]/u', 'max:20'
        ];
    }

    public static function baseNameRule(): array
    {
        return [
            'string',
            'regex:' . self::SAFE_CHARS_REGEX,
            'not_regex:' . self::DOTS_OR_SPACES_REGEX,
            'not_regex:' . self::TRAVERSAL_REGEX,
        ];
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
