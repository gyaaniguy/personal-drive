<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPath implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (preg_match('/(\.\.\/|\.\.\\\\)/', $value)) {
            $fail('The :attribute contains directory traversal sequences.');
            return;
        }

        if (!preg_match('/^[:\. a-zA-Z0-9_\-\/\\\\]+$/', $value)) {
            $fail('The :attribute contains invalid characters.');
        }
    }
}
