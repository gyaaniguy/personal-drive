<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidPath implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Disallow directory traversal (../ or ..\)
        if (preg_match('/(\.\.\/|\.\.\\\\)/', $value)) {
            $fail('The :attribute contains directory traversal sequences.');
            return;
        }

        // Allow only valid path characters
        if (!preg_match('/^[\. a-zA-Z0-9_\-\/\\\]+$/', $value)) {
            $fail('The :attribute contains invalid characters.');
        }
    }
}
