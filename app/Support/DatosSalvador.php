<?php

namespace App\Support;

use Illuminate\Support\Str;

class DatosSalvador
{
    public const DEPARTAMENTOS = ['Ahuachapán', 'Santa Ana', 'Sonsonate', 'Chalatenango', 'La Libertad', 'San Salvador', 'Cuscatlán', 'La Paz', 'Cabañas', 'San Vicente', 'Usulután', 'San Miguel', 'Morazán', 'La Unión'];

    public static function normalizar(array $datos): array
    {
        foreach ($datos as $campo => $valor) {
            $datos[$campo] = is_array($valor) ? self::normalizar($valor) : self::campo((string) $campo, $valor);
        }

        return $datos;
    }

    public static function campo(string $campo, mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (in_array($campo, ['telefono', 'celular', 'fiador_telefono'])) {
            if (preg_match('/^(?:\+503[ -]?)?[267][0-9]{3}[ -]?[0-9]{4}$/D', $valor)) {
                $digitos = preg_replace('/[^0-9]/', '', $valor);
                $digitos = substr($digitos, -8);

                return substr($digitos, 0, 4).'-'.substr($digitos, 4);
            }
        }
        if (in_array($campo, ['dui', 'dui_representante', 'fiador_dui', 'nit'])) {
            if (preg_match('/^[0-9]{8}-?[0-9]$/D', $valor)) {
                $digitos = str_replace('-', '', $valor);

                return substr($digitos, 0, 8).'-'.substr($digitos, 8);
            }
            if ($campo === 'nit' && preg_match('/^(?:[0-9]{14}|[0-9]{4}-[0-9]{6}-[0-9]{3}-[0-9])$/D', $valor)) {
                $digitos = str_replace('-', '', $valor);

                return substr($digitos, 0, 4).'-'.substr($digitos, 4, 6).'-'.substr($digitos, 10, 3).'-'.substr($digitos, 13);
            }
        }
        if ($campo === 'nrc' && preg_match('/^[0-9]{1,7}-?[0-9]$|^[0-9]$/D', $valor)) {
            return str_replace('-', '', $valor);
        }
        if ($campo === 'departamento') {
            foreach (self::DEPARTAMENTOS as $departamento) {
                if (mb_strtolower(Str::ascii($valor)) === mb_strtolower(Str::ascii($departamento))) {
                    return $departamento;
                }
            }
        }

        return $valor;
    }

    public static function duiValido(mixed $valor): bool
    {
        if (! is_string($valor) || ! preg_match('/^[0-9]{8}-?[0-9]$/D', $valor)) {
            return false;
        }
        $digitos = str_replace('-', '', $valor);
        $suma = 0;
        for ($i = 0; $i < 8; $i++) {
            $suma += (int) $digitos[$i] * (9 - $i);
        }

        return $suma > 0 && (10 - $suma % 10) % 10 === (int) $digitos[8];
    }

    public static function nitValido(mixed $valor, bool $permiteDui = true): bool
    {
        if ($permiteDui && self::duiValido($valor)) {
            return true;
        }
        if (! is_string($valor) || ! preg_match('/^(?:[0-9]{14}|[0-9]{4}-[0-9]{6}-[0-9]{3}-[0-9])$/D', $valor)) {
            return false;
        }
        $digitos = str_replace('-', '', $valor);
        if (substr($digitos, 0, 4) === '0000' || (int) substr($digitos, 10, 3) === 0) {
            return false;
        }
        $dia = (int) substr($digitos, 4, 2);
        $mes = (int) substr($digitos, 6, 2);
        $anio = (int) substr($digitos, 8, 2);
        if (! checkdate($mes, $dia, 1900 + $anio) && ! checkdate($mes, $dia, 2000 + $anio)) {
            return false;
        }
        $antiguo = (int) substr($digitos, 10, 3) <= 100;
        $pesos = $antiguo ? range(14, 2) : [2, 7, 6, 5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        foreach ($pesos as $i => $peso) {
            $suma += (int) $digitos[$i] * $peso;
        }
        $residuo = $suma % 11;
        $control = $antiguo ? ($residuo === 10 ? 0 : $residuo) : ($residuo > 1 ? 11 - $residuo : 0);

        return $control === (int) $digitos[13];
    }
}
