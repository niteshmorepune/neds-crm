<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates an Indian GSTIN: 15 chars — 2-digit state code, 5 letters (PAN
 * entity), 4 digits, 1 letter, 1 entity/check char, fixed 'Z', 1 check char.
 * Regex per CLAUDE.md.
 */
class Gstin implements ValidationRule
{
    public const PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('The :attribute must be a valid 15-character GSTIN.');
        }
    }
}
