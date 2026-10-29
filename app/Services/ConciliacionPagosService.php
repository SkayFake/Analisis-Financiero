<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\PagoCuota;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConciliacionPagosService
{
    /** Reconstruct only unambiguous legacy applications, without changing receipts or balances. */
    public function conciliar(Credito $credito): int
    {
        return DB::transaction(function () use ($credito) {
            $credito = Credito::withTrashed()->whereKey($credito->id)->lockForUpdate()->firstOrFail();
            $pagos = $credito->pagos()->with('aplicaciones')->orderBy('id')->lockForUpdate()->get();
            $historicos = $pagos->filter(fn ($pago) => $pago->aplicaciones->isEmpty());
            if ($historicos->isEmpty()) {
                return 0;
            }

            $cuotas = $credito->cuotas()->lockForUpdate()->get()->keyBy('id');
            $sumas = [];
            $pendientes = [];
            $capitalTotal = 0;
            $error = function () use ($credito): never {
                throw new InvalidArgumentException("El crédito {$credito->numero} requiere conciliación de pagos históricos antes de registrar nuevos abonos. No se modificaron recibos ni saldos.");
            };

            foreach ($pagos as $pago) {
                $conceptos = ['abono_capital', 'pago_interes', 'pago_mora'];
                $capitalTotal += $this->centavos($pago->abono_capital);
                $aplicaciones = $pago->aplicaciones->toArray();
                if ($pago->aplicaciones->isEmpty()) {
                    if (! $pago->cuota_id || ! $cuotas->has($pago->cuota_id) || $this->centavos($pago->pago_comision) !== 0) {
                        $error();
                    }
                    $aplicaciones = [array_merge($pago->only(['monto_total', ...$conceptos]), [
                        'pago_id' => $pago->id,
                        'cuota_id' => $pago->cuota_id,
                    ])];
                    $pendientes[] = $aplicaciones[0];
                }

                foreach (['monto_total', ...$conceptos] as $campo) {
                    if (array_sum(array_map(fn ($a) => $this->centavos($a[$campo]), $aplicaciones)) !== $this->centavos($pago->$campo)) {
                        $error();
                    }
                }

                foreach ($aplicaciones as $aplicacion) {
                    $id = $aplicacion['cuota_id'];
                    if (! $cuotas->has($id)) {
                        $error();
                    }
                    $total = 0;
                    foreach ($conceptos as $campo) {
                        $valor = $this->centavos($aplicacion[$campo]);
                        if ($valor < 0) {
                            $error();
                        }
                        $sumas[$id][$campo] = ($sumas[$id][$campo] ?? 0) + $valor;
                        $total += $valor;
                    }
                    if ($total !== $this->centavos($aplicacion['monto_total'])) {
                        $error();
                    }
                }
            }

            if ($this->centavos($credito->monto_original) - $capitalTotal !== $this->centavos($credito->saldo_actual)) {
                $error();
            }
            foreach ($cuotas as $id => $cuota) {
                $suma = $sumas[$id] ?? [];
                if (array_sum($suma) !== $this->centavos($cuota->monto_pagado)) {
                    $error();
                }
                foreach (['abono_capital' => 'capital', 'pago_interes' => 'interes', 'pago_mora' => 'mora'] as $campo => $limite) {
                    if (($suma[$campo] ?? 0) > $this->centavos($cuota->$limite)) {
                        $error();
                    }
                }
            }

            foreach ($pendientes as $aplicacion) {
                PagoCuota::create($aplicacion);
            }

            return count($pendientes);
        });
    }

    private function centavos(mixed $valor): int
    {
        return (int) round((float) $valor * 100);
    }
}
