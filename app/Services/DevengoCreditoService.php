<?php

namespace App\Services;

use App\Models\Credito;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Interés simple sobre capital diario; nunca sobre intereses, comisiones o mora. */
class DevengoCreditoService
{
    public function actualizar(Credito $credito, ?string $fecha = null): array
    {
        return DB::transaction(function () use ($credito, $fecha) {
            $credito = Credito::whereKey($credito->id)->lockForUpdate()->firstOrFail();
            $corte = Carbon::parse($fecha ?? today()->toDateString())->startOfDay();
            $inicio = ($credito->fecha_aprobacion ?? $credito->fecha_solicitud)->copy()->startOfDay();
            if ($corte->lt($inicio) || $corte->gt(today())) {
                throw new InvalidArgumentException('Fecha de cálculo fuera de la vigencia del crédito.');
            }
            $cuotas = $credito->cuotas()->with(['aplicacionesPago.pago'])->lockForUpdate()->get();
            $totalMora = 0;
            $totalInteres = 0;
            $maxDias = 0;
            foreach ($cuotas as $cuota) {
                $eventos = $cuota->aplicacionesPago->sortBy(fn ($a) => $a->pago->fecha_pago->format('Y-m-d'));
                $interes = 0;
                $mora = 0;
                $capital = (float) $cuota->capital;
                $cursor = $inicio->copy();
                $inicioMora = $cuota->fecha_vencimiento->copy()->addDays($credito->dias_gracia)->startOfDay();
                foreach ($eventos as $evento) {
                    $dia = $evento->pago->fecha_pago->copy()->startOfDay();
                    if ($dia->gt($corte)) {
                        throw new InvalidArgumentException('No puede recalcular una fecha anterior a un pago registrado.');
                    }
                    if ($dia->lt($inicio)) {
                        throw new InvalidArgumentException('El historial contiene pagos anteriores al inicio: requiere conciliación.');
                    }
                    $interes += $this->importe($capital, (float) $credito->tasa_interes_anual, $cursor, $dia);
                    $mora += $this->importe($capital, (float) $credito->tasa_mora_mensual * 12, $cursor->copy()->max($inicioMora), $dia);
                    $capital = max(0, round($capital - (float) $evento->abono_capital, 2));
                    $cursor = $dia;
                }
                $interes += $this->importe($capital, (float) $credito->tasa_interes_anual, $cursor, $corte);
                $mora += $this->importe($capital, (float) $credito->tasa_mora_mensual * 12, $cursor->copy()->max($inicioMora), $corte);
                // Lo ya cobrado se conserva como evidencia; diferencias históricas requieren ajuste documentado.
                $mora = max(round($mora, 2), (float) $eventos->sum('pago_mora'));
                $interes = $credito->calculo_interes === 'diario'
                    ? max(round($interes, 2), (float) $eventos->sum('pago_interes')) : (float) $cuota->interes;
                $dias = $capital > 0 && $inicioMora->lt($corte) ? (int) $inicioMora->diffInDays($corte) : 0;
                $pagado = (float) $cuota->monto_pagado;
                $total = round((float) $cuota->capital + $interes, 2);
                $pendiente = max(0, round($total + $mora - $pagado, 2));
                $estado = $pendiente <= 0 ? 'pagada' : ($pagado > 0 ? 'parcial' : ($dias > 0 ? 'vencida' : 'pendiente'));
                $cuota->update(['interes' => $interes, 'total' => $total, 'mora' => $mora, 'dias_mora' => $dias, 'estado' => $estado]);
                $totalMora += max(0, $mora - (float) $eventos->sum('pago_mora'));
                $totalInteres += max(0, $interes - (float) $eventos->sum('pago_interes'));
                $maxDias = max($maxDias, $dias);
            }
            $datos = ['fecha_ultimo_devengo' => $corte->toDateString(), 'mora_acumulada' => round($totalMora, 2), 'interes_acumulado' => round($totalInteres, 2), 'dias_mora' => $maxDias];
            if (in_array($credito->estado, ['vigente', 'vencido'])) {
                $datos['estado'] = $maxDias > 0 ? 'vencido' : 'vigente';
            }
            $credito->update($datos);

            return ['total_mora' => round($totalMora, 2), 'dias_mora' => $maxDias, 'cuotas_vencidas' => $cuotas->where('dias_mora', '>', 0)->count()];
        });
    }

    private function importe(float $capital, float $tasa, Carbon $inicio, Carbon $fin): float
    {
        $total = 0;
        while ($capital > 0 && $tasa > 0 && $inicio->lt($fin)) {
            $hasta = $inicio->copy()->addYear()->startOfYear()->min($fin);
            $dias = (int) $inicio->diffInDays($hasta);
            $total += $capital * $tasa / 100 * $dias / $inicio->daysInYear;
            $inicio = $hasta;
        }

        return $total;
    }
}
