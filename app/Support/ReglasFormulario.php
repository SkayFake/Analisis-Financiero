<?php

namespace App\Support;

use App\Rules\DuiSalvador;
use App\Rules\TelefonoSalvador;

class ReglasFormulario
{
    public const DINERO = 'numeric|min:0|max:999999999999.99|decimal:0,2';

    public const CODIGO = 'string|regex:/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/D';

    public static function fiador(): array
    {
        return [
            'fiador_nombre' => 'nullable|required_with:fiador_dui,fiador_telefono,fiador_direccion|string|max:150|regex:/[\p{L}]/u',
            'fiador_dui' => ['bail', 'nullable', 'string', new DuiSalvador],
            'fiador_telefono' => ['bail', 'nullable', 'string', new TelefonoSalvador],
            'fiador_direccion' => 'nullable|string|max:250',
        ];
    }
}
