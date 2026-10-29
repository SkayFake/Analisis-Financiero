<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class TelefonoSalvador implements ValidationRule
{
    public function __construct(private bool $soloCelular = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $prefijos = $this->soloCelular ? '67' : '267';
        if (! is_string($value) || ! preg_match('/^(?:\+503[ -]?)?['.$prefijos.'][0-9]{3}[ -]?[0-9]{4}$/D', $value)) {
            $fail($this->soloCelular
                ? 'El celular debe tener 8 dígitos y comenzar con 6 o 7. Ejemplo: 7123-4567; puede incluir +503.'
                : 'El teléfono debe tener 8 dígitos y comenzar con 2, 6 o 7. Ejemplo: 2234-5678; puede incluir +503.');
        }
    }
}
