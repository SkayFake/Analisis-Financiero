<?php

namespace App\Rules;

use App\Support\DatosSalvador;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NitSalvador implements ValidationRule
{
    public function __construct(private bool $permiteDui = true) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! DatosSalvador::nitValido($value, $this->permiteDui)) {
            $fail('El NIT debe tener 14 dígitos, fecha válida y dígito verificador correcto.'.($this->permiteDui ? ' Para persona natural también se acepta el DUI homologado.' : ' Para persona jurídica no se admite un DUI como NIT.'));
        }
    }
}
