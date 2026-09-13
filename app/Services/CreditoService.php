<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\ProductoCredito;
use Illuminate\Support\Facades\DB;

class CreditoService
{
    /**
     * Crea un nuevo crédito con su tabla de amortización.
     */
    public function crearCredito(array $datos): Credito
    {
        return DB::transaction(function () use ($datos) {
            $producto = ProductoCredito::findOrFail($datos['producto_credito_id']);

            $credito = Credito::create([
                'numero' => Credito::generarNumero(),
                'cliente_id' => $datos['cliente_id'],
                'producto_credito_id' => $producto->id,
                'vendedor_id' => $datos['vendedor_id'] ?? null,
                'cartera_id' => $datos['cartera_id'] ?? null,
                'politica_cobro_id' => $datos['politica_cobro_id'] ?? null,
                'monto_original' => $datos['monto'],
                'saldo_actual' => $datos['monto'],
                'tasa_interes' => $datos['tasa_interes'] ?? $producto->tasa_interes,
                'comision' => $datos['comision'] ?? $producto->comision,
                'plazo_dias' => (int) $datos['plazo_dias'],
                'numero_cuotas' => (int) $datos['numero_cuotas'],
                'fecha_solicitud' => $datos['fecha_solicitud'] ?? now(),
                'fecha_desembolso' => $datos['fecha_desembolso'] ?? null,
                'fecha_vencimiento' => now()->addDays((int) $datos['plazo_dias']),
                'estado' => 'solicitado',
                'tipo_venta' => $datos['tipo_venta'] ?? 'credito',
                'interes_moratorio' => $producto->interes_moratorio,
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            // Generar tabla de amortización
            if ($datos['fecha_desembolso'] ?? false) {
                $this->generarTablaAmortizacion($credito);
            }

            return $credito;
        });
    }

    /**
     * Genera tabla de amortización con sistema francés.
     * Cuota fija = M * [r(1+r)^n] / [(1+r)^n - 1]
     */
    public function generarTablaAmortizacion(Credito $credito): void
    {
        // Eliminar cuotas existentes si las hay
        $credito->cuotas()->delete();

        $monto = (float) $credito->monto_original;
        $tasaMensual = ((float) $credito->tasa_interes / 100) / 12;
        $comisionMensual = ((float) $credito->comision / 100) / 12;
        $n = $credito->numero_cuotas;
        $fechaInicio = $credito->fecha_desembolso ?? now();

        // Cuota fija (sistema francés)
        if ($tasaMensual > 0) {
            $cuotaFija = $monto * ($tasaMensual * pow(1 + $tasaMensual, $n))
                / (pow(1 + $tasaMensual, $n) - 1);
        } else {
            $cuotaFija = $monto / $n;
        }

        $saldoPendiente = $monto;
        $cuotas = [];

        for ($i = 1; $i <= $n; $i++) {
            $interes = round($saldoPendiente * $tasaMensual, 2);
            $comision = round($saldoPendiente * $comisionMensual, 2);
            $capital = round($cuotaFija - $interes, 2);

            // Ajuste para la última cuota
            if ($i === $n) {
                $capital = $saldoPendiente;
                $cuotaFija = $capital + $interes;
            }

            $saldoPendiente = round($saldoPendiente - $capital, 2);
            if ($saldoPendiente < 0) $saldoPendiente = 0;

            $cuotas[] = [
                'credito_id' => $credito->id,
                'numero_cuota' => $i,
                'fecha_vencimiento' => $fechaInicio->copy()->addMonths($i),
                'capital' => $capital,
                'interes' => $interes,
                'comision' => $comision,
                'total' => round($capital + $interes + $comision, 2),
                'saldo_pendiente' => $saldoPendiente,
                'estado' => 'pendiente',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Cuota::insert($cuotas);
    }

    /**
     * Procesa un pago y genera el recibo con desglose.
     */
    public function procesarPago(Credito $credito, array $datos): Pago
    {
        return DB::transaction(function () use ($credito, $datos) {
            $montoTotal = (float) $datos['monto'];
            $cuota = $credito->cuota_pendiente;

            // Calcular desglose
            $pagoMora = 0;
            $pagoInteres = 0;
            $pagoComision = 0;
            $abonoCapital = 0;

            if ($cuota) {
                // Primero se paga mora (si aplica)
                if ($cuota->dias_mora > 0) {
                    $tasaMoraDiaria = ((float) $credito->interes_moratorio / 100) / 365;
                    $pagoMora = min(
                        round($cuota->saldo_pendiente * $tasaMoraDiaria * $cuota->dias_mora, 2),
                        $montoTotal
                    );
                }

                $restante = $montoTotal - $pagoMora;

                // Luego interés
                $pagoInteres = min($cuota->interes, $restante);
                $restante -= $pagoInteres;

                // Luego comisión
                $pagoComision = min($cuota->comision, $restante);
                $restante -= $pagoComision;

                // El resto va a capital
                $abonoCapital = $restante;
            } else {
                // Pago directo a capital
                $abonoCapital = $montoTotal;
            }

            // Actualizar saldo del crédito
            $nuevoSaldo = max(0, (float) $credito->saldo_actual - $abonoCapital);
            $credito->update(['saldo_actual' => $nuevoSaldo]);

            // Crear registro de pago
            $pago = Pago::create([
                'credito_id' => $credito->id,
                'cuota_id' => $cuota?->id,
                'numero_recibo' => Pago::generarNumeroRecibo(),
                'fecha_pago' => $datos['fecha_pago'] ?? now(),
                'monto_total' => $montoTotal,
                'abono_capital' => $abonoCapital,
                'pago_interes' => $pagoInteres,
                'pago_comision' => $pagoComision,
                'pago_mora' => $pagoMora,
                'saldo_despues' => $nuevoSaldo,
                'forma_pago' => $datos['forma_pago'] ?? 'efectivo',
                'referencia' => $datos['referencia'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'recibido_por' => $datos['recibido_por'] ?? null,
            ]);

            // Actualizar estado de la cuota
            if ($cuota) {
                $montoPagadoCuota = (float) $cuota->monto_pagado + $montoTotal;
                $estadoCuota = $montoPagadoCuota >= (float) $cuota->total ? 'pagada' : 'parcial';

                $cuota->update([
                    'monto_pagado' => $montoPagadoCuota,
                    'estado' => $estadoCuota,
                    'fecha_pago' => $estadoCuota === 'pagada' ? now() : null,
                ]);
            }

            // Si el saldo es 0, cancelar el crédito
            if ($nuevoSaldo <= 0) {
                $credito->update([
                    'estado' => 'cancelado',
                    'fecha_cancelacion' => now(),
                    'saldo_actual' => 0,
                ]);
            }

            return $pago;
        });
    }

    /**
     * Calcula interés moratorio por días vencidos.
     */
    public function calcularMora(Credito $credito): array
    {
        $cuotasVencidas = $credito->cuotas()
            ->where('estado', '!=', 'pagada')
            ->where('fecha_vencimiento', '<', now())
            ->get();

        $totalMora = 0;
        $diasMoraMax = 0;

        foreach ($cuotasVencidas as $cuota) {
            $dias = $cuota->actualizarDiasMora();
            $diasMoraMax = max($diasMoraMax, $dias);

            $tasaDiaria = ((float) $credito->interes_moratorio / 100) / 365;
            $totalMora += round((float) $cuota->restante * $tasaDiaria * $dias, 2);
        }

        // Actualizar días mora del crédito
        $credito->update(['dias_mora' => $diasMoraMax]);

        return [
            'total_mora' => $totalMora,
            'dias_mora' => $diasMoraMax,
            'cuotas_vencidas' => $cuotasVencidas->count(),
        ];
    }

    /**
     * Refinancia un crédito existente.
     */
    public function refinanciar(Credito $creditoOriginal, array $datos): Credito
    {
        return DB::transaction(function () use ($creditoOriginal, $datos) {
            $saldoAnterior = (float) $creditoOriginal->saldo_actual;

            // Calcular intereses y comisiones pendientes
            $interesesPendientes = $creditoOriginal->cuotas()
                ->pendientes()
                ->sum('interes');
            $comisionesPendientes = $creditoOriginal->cuotas()
                ->pendientes()
                ->sum('comision');

            $nuevoMonto = $datos['nuevo_monto'] ?? ($saldoAnterior + $interesesPendientes);

            // Crear nuevo crédito
            $nuevoCredito = $this->crearCredito(array_merge($datos, [
                'cliente_id' => $creditoOriginal->cliente_id,
                'monto' => $nuevoMonto,
                'fecha_desembolso' => now(),
            ]));

            // Marcar crédito original como refinanciado
            $creditoOriginal->update(['estado' => 'refinanciado']);

            // Registrar refinanciamiento
            \App\Models\Refinanciamiento::create([
                'credito_original_id' => $creditoOriginal->id,
                'credito_nuevo_id' => $nuevoCredito->id,
                'saldo_anterior' => $saldoAnterior,
                'nuevo_monto' => $nuevoMonto,
                'intereses_pendientes' => $interesesPendientes,
                'comisiones_pendientes' => $comisionesPendientes,
                'motivo' => $datos['motivo'] ?? 'Refinanciamiento',
                'fecha' => now(),
                'aprobado_por' => $datos['aprobado_por'] ?? null,
            ]);

            return $nuevoCredito;
        });
    }

    /**
     * Registra un embargo sobre un crédito.
     */
    public function registrarEmbargo(Credito $credito, array $datos): \App\Models\Embargo
    {
        return DB::transaction(function () use ($credito, $datos) {
            $credito->update(['estado' => 'embargado']);

            return \App\Models\Embargo::create([
                'credito_id' => $credito->id,
                'fecha_embargo' => $datos['fecha_embargo'] ?? now(),
                'descripcion_bienes' => $datos['descripcion_bienes'],
                'valor_estimado' => $datos['valor_estimado'],
                'estado' => 'activo',
                'observaciones' => $datos['observaciones'] ?? null,
                'registrado_por' => $datos['registrado_por'] ?? null,
            ]);
        });
    }
}
