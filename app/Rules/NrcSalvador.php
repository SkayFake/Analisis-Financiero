<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NrcSalvador implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(?:[0-9]{1,8}|[0-9]{1,7}-[0-9])$/D', $value) || (int) str_replace('-', '', $value) === 0) {
            $fail('El NRC debe contener de 1 a 8 dígitos; se admite el guion antes del último dígito.');
        }
    }
}
