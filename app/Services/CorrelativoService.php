<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CorrelativoService
{
    public function siguiente(string $clave, int $minimo = 0): int
    {
        return DB::transaction(function () use ($clave, $minimo) {
            DB::table('correlativos')->insertOrIgnore(['clave' => $clave, 'valor' => $minimo]);
            $fila = DB::table('correlativos')->where('clave', $clave)->lockForUpdate()->first();
            $n = max((int) $fila->valor, $minimo) + 1;
            DB::table('correlativos')->where('clave', $clave)->update(['valor' => $n]);

            return $n;
        });
    }
}
