<?php

namespace App\Services;

use App\Models\CondicionCredito;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LimiteCreditoService
{
    public function validar(CondicionCredito $condicion, float $monto): ?array
    {
        Validator::make($condicion->only(['tasa_interes_anual', 'tasa_mora_mensual']), [
            'tasa_interes_anual' => 'required|numeric|min:0|max:10000',
            'tasa_mora_mensual' => 'required|numeric|min:0|max:10000',
        ])->validate();
        if ((float) $condicion->tasa_interes_anual <= 0 && (float) $condicion->tasa_mora_mensual <= 0) {
            return null;
        }
        if (config('erp.modo_academico')) {
            if (app()->isProduction()) {
                throw ValidationException::withMessages(['condicion_credito_id' => 'El modo académico requiere un entorno local o de pruebas y datos ficticios.']);
            }
            $limite = [
                'modo' => 'simulacion_academica', 'condicion_credito_id' => $condicion->id,
                'tasa_maxima_efectiva' => config('erp.tope_academico_efectivo'),
                'fuente' => 'Ejemplo didáctico. No corresponde a un tope legal del BCR.',
            ];
        } else {
            $limite = collect(config('erp.creditos.limites_legales', []))->first(fn ($l) => ($l['condicion_credito_id'] ?? null) === $condicion->id
              && ($l['desde'] ?? '') <= today()->toDateString() && ($l['hasta'] ?? '') >= today()->toDateString()
              && $monto >= ($l['monto_minimo'] ?? INF) && $monto <= ($l['monto_maximo'] ?? -1) && ! empty($l['fuente']));
            if (! $limite) {
                throw ValidationException::withMessages(['condicion_credito_id' => 'Configure el límite legal vigente y su fuente para esta condición y monto antes de otorgar crédito con intereses o mora.']);
            }
        }
        $anual = (float) $condicion->tasa_interes_anual + (float) $condicion->tasa_mora_mensual * 12;
        $cota = (pow(1 + $anual / 100 / 365, 366) - 1) * 100;
        if (! is_numeric($limite['tasa_maxima_efectiva'] ?? null) || (float) $limite['tasa_maxima_efectiva'] <= 0) {
            throw ValidationException::withMessages(['condicion_credito_id' => 'El tope efectivo debe ser numérico y positivo.']);
        }
        if ($cota > (float) $limite['tasa_maxima_efectiva']) {
            throw ValidationException::withMessages(['condicion_credito_id' => 'Las tasas configuradas exceden la cota efectiva permitida por el límite legal registrado.']);
        }

        return $limite;
    }
}
