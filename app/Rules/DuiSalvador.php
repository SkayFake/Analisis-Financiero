<?php

namespace App\Rules;

use App\Support\DatosSalvador;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DuiSalvador implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! DatosSalvador::duiValido($value)) {
            $fail('El DUI debe tener 9 dígitos, formato 01234567-8 y un dígito verificador correcto.');
        }
    }
}
