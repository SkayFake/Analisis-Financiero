<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\CondicionCredito;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Servicio de Crédito Comercial.
 *
 * El crédito comercial en El Salvador funciona así:
 * - El cliente compra mercadería y paga a plazo (cuotas).
 * - El monto del crédito = total de la factura (no hay desembolso de dinero).
 * - Puede tener o no interés sobre el saldo (configurable por la institución).
 * - Se aplica mora si la cuota no se paga dentro de los días de gracia.
 *
 * Proceso de aprobación (basado en LPC El Salvador):
 * - Cliente FRECUENTE + monto ≤ límite auto-aprobado → aprobación automática.
 * - Cliente NUEVO o monto mayor → requiere revisión manual (pendiente_aprobacion).
 * - Documentación mínima requerida: DUI (Ley AML) y referencias comerciales.
 */
class CreditoService
{
    /**
     * Crea un crédito comercial a partir de una venta al crédito.
     * Este es el flujo principal: Venta → Crédito.
     *
     * @param Venta $venta Venta con condicion_operacion='2'
     * @param array $condiciones ['numero_cuotas', 'frecuencia_pago', 'fecha_primera_cuota',
     *                            'condicion_credito_id', 'vendedor_id', 'cartera_id',
     *                            'politica_cobro_id', 'observaciones']
     * @return Credito
     */
    public function crearCreditoDesdeVenta(Venta $venta, array $condiciones): Credito
    {
        return DB::transaction(function () use ($venta, $condiciones) {

            // Validaciones básicas
            if ($venta->condicion_operacion !== '2') {
                throw new \InvalidArgumentException('Solo las ventas al crédito generan un crédito comercial.');
            }
            if ($venta->tiene_credito) {
                throw new \InvalidArgumentException("La factura {$venta->numero_control} ya tiene un crédito asociado.");
            }

            $cliente   = Cliente::findOrFail($venta->cliente_id);
            $condicion = CondicionCredito::findOrFail($condiciones['condicion_credito_id']);
            $numeroCuotas = (int) $condiciones['numero_cuotas'];

            // Determinar si aprobación automática o manual
            $esClienteFrecuente = $this->esClienteFrecuente($cliente, $condicion);
            $montoFactura       = (float) $venta->total_pagar;
            $autoAprobado       = $esClienteFrecuente
                && $montoFactura <= (float) $condicion->monto_auto_aprobado;

            $estado          = $autoAprobado ? 'vigente' : 'pendiente_aprobacion';
            $fechaAprobacion = $autoAprobado ? now() : null;

            // Calcular plazo
            $plazoDias = $condicion->calcularPlazoDias($numeroCuotas);

            // Fecha de primera cuota
            $fechaPrimeraCuota = isset($condiciones['fecha_primera_cuota'])
                ? Carbon::parse($condiciones['fecha_primera_cuota'])
                : now()->addMonth();

            // Fecha de vencimiento (última cuota)
            $fechaVencimiento = $this->calcularFechaVencimiento(
                $fechaPrimeraCuota,
                $numeroCuotas,
                $condicion->frecuencia_pago
            );

            $credito = Credito::create([
                'numero'               => Credito::generarNumero(),
                'cliente_id'           => $venta->cliente_id,
                'venta_id'             => $venta->id,
                'condicion_credito_id' => $condicion->id,
                'vendedor_id'          => $condiciones['vendedor_id'] ?? $venta->vendedor_id ?? null,
                'cartera_id'           => $condiciones['cartera_id'] ?? null,
                'politica_cobro_id'    => $condiciones['politica_cobro_id'] ?? null,
                'monto_original'       => $montoFactura,
                'saldo_actual'         => $montoFactura,
                'interes_acumulado'    => 0,
                'mora_acumulada'       => 0,
                // Copiar tasas de la condición para audit trail
                'tasa_interes_anual'   => $condicion->tasa_interes_anual,
                'tasa_mora_mensual'    => $condicion->tasa_mora_mensual,
                'dias_gracia'          => $condicion->dias_gracia,
                'numero_cuotas'        => $numeroCuotas,
                'frecuencia_pago'      => $condicion->frecuencia_pago,
                'plazo_dias'           => $plazoDias,
                'fecha_solicitud'      => $venta->fecha_emision ?? now(),
                'fecha_aprobacion'     => $fechaAprobacion,
                'fecha_primera_cuota'  => $fechaPrimeraCuota,
                'fecha_vencimiento'    => $fechaVencimiento,
                'estado'               => $estado,
                'observaciones'        => $condiciones['observaciones'] ?? null,
                'aprobado_por'         => $autoAprobado ? auth()->id() : null,
                'motivo_aprobacion'    => $autoAprobado
                    ? 'Aprobación automática — cliente frecuente, monto dentro del límite.'
                    : null,
                'dui_verificado'       => $condiciones['dui_verificado'] ?? false,
                'referencia_verificada' => $condiciones['referencia_verificada'] ?? false,
            ]);

            // Generar plan de cuotas si está aprobado automáticamente
            if ($autoAprobado) {
                $this->generarPlanPago($credito);
            }

            return $credito;
        });
    }

    /**
     * Genera el plan de cuotas del crédito comercial.
     *
     * Si tasa_interes_anual = 0 → cuotas iguales sin interés (lo más común).
     * Si tasa_interes_anual > 0 → sistema francés (cuota fija con interés compuesto).
     */
    public function generarPlanPago(Credito $credito): void
    {
        // Eliminar cuotas existentes si las hay
        $credito->cuotas()->delete();

        $monto        = (float) $credito->monto_original;
        $n            = $credito->numero_cuotas;
        $tasaMensual  = ((float) $credito->tasa_interes_anual / 100) / 12;
        $fechaInicio  = $credito->fecha_primera_cuota ?? now()->addMonth();

        if ($tasaMensual > 0) {
            // Sistema francés: cuota fija = M * [r(1+r)^n] / [(1+r)^n - 1]
            $cuotaFija = $monto * ($tasaMensual * pow(1 + $tasaMensual, $n))
                / (pow(1 + $tasaMensual, $n) - 1);
        } else {
            // Sin interés: división simple del total de la factura
            $cuotaFija = $monto / $n;
        }

        $saldoPendiente = $monto;
        $cuotas = [];

        for ($i = 1; $i <= $n; $i++) {
            $interes = ($tasaMensual > 0) ? round($saldoPendiente * $tasaMensual, 2) : 0;
            $capital = ($i === $n)
                ? $saldoPendiente                      // última cuota = saldo exacto
                : round($cuotaFija - $interes, 2);

            $totalCuota = round($capital + $interes, 2);
            $saldoPendiente = round($saldoPendiente - $capital, 2);
            if ($saldoPendiente < 0) $saldoPendiente = 0;

            $cuotas[] = [
                'credito_id'       => $credito->id,
                'numero_cuota'     => $i,
                'fecha_vencimiento' => $this->calcularFechaVencimiento(
                    $fechaInicio, $i, $credito->frecuencia_pago, baseIsFirst: true
                ),
                'capital'          => $capital,
                'interes'          => $interes,
                'total'            => $totalCuota,
                'mora'             => 0,
                'saldo_pendiente'  => $saldoPendiente,
                'monto_pagado'     => 0,
                'estado'           => 'pendiente',
                'created_at'       => now(),
                'updated_at'       => now(),
            ];
        }

        Cuota::insert($cuotas);
    }

    /**
     * Aprueba manualmente un crédito en estado pendiente_aprobacion.
     * Registra el responsable, fecha y genera el plan de cuotas.
     */
    public function aprobarCredito(Credito $credito, string $motivo, ?int $usuarioId = null): Credito
    {
        return DB::transaction(function () use ($credito, $motivo, $usuarioId) {
            if ($credito->estado !== 'pendiente_aprobacion') {
                throw new \InvalidArgumentException('Solo se pueden aprobar créditos en estado pendiente_aprobacion.');
            }

            $credito->update([
                'estado'            => 'vigente',
                'fecha_aprobacion'  => now(),
                'aprobado_por'      => $usuarioId ?? auth()->id(),
                'motivo_aprobacion' => $motivo,
            ]);

            $this->generarPlanPago($credito->fresh());

            return $credito->fresh();
        });
    }

    /**
     * Rechaza un crédito en estado pendiente_aprobacion.
     */
    public function rechazarCredito(Credito $credito, string $motivo, ?int $usuarioId = null): Credito
    {
        if ($credito->estado !== 'pendiente_aprobacion') {
            throw new \InvalidArgumentException('Solo se pueden rechazar créditos pendientes de aprobación.');
        }

        $credito->update([
            'estado'            => 'rechazado',
            'aprobado_por'      => $usuarioId ?? auth()->id(),
            'motivo_aprobacion' => $motivo,
        ]);

        return $credito->fresh();
    }

    /**
     * Procesa un pago sobre el crédito.
     * Orden de aplicación: mora → interés → capital (prioritario en comercio).
     */
    public function procesarPago(Credito $credito, array $datos): Pago
    {
        return DB::transaction(function () use ($credito, $datos) {
            $montoTotal  = (float) $datos['monto'];
            $cuota       = $credito->cuota_pendiente;

            $pagoMora    = 0;
            $pagoInteres = 0;
            $abonoCapital = 0;

            if ($cuota) {
                // 1. Primero aplicar mora si existe
                $moraActual = (float) $cuota->mora;
                if ($moraActual > 0) {
                    $pagoMora   = min($moraActual, $montoTotal);
                }
                $restante = $montoTotal - $pagoMora;

                // 2. Luego interés (si el crédito tiene tasa > 0)
                $pagoInteres = min((float) $cuota->interes, $restante);
                $restante   -= $pagoInteres;

                // 3. El resto va a capital
                $abonoCapital = $restante;
            } else {
                $abonoCapital = $montoTotal;
            }

            // Actualizar saldo del crédito
            $nuevoSaldo = max(0, (float) $credito->saldo_actual - $abonoCapital);
            $credito->update(['saldo_actual' => $nuevoSaldo]);

            // Crear recibo
            $pago = Pago::create([
                'credito_id'     => $credito->id,
                'cuota_id'       => $cuota?->id,
                'numero_recibo'  => Pago::generarNumeroRecibo(),
                'fecha_pago'     => $datos['fecha_pago'] ?? now(),
                'monto_total'    => $montoTotal,
                'abono_capital'  => $abonoCapital,
                'pago_interes'   => $pagoInteres,
                'pago_mora'      => $pagoMora,
                'saldo_despues'  => $nuevoSaldo,
                'forma_pago'     => $datos['forma_pago'] ?? 'efectivo',
                'referencia'     => $datos['referencia'] ?? null,
                'observaciones'  => $datos['observaciones'] ?? null,
                'recibido_por'   => $datos['recibido_por'] ?? null,
            ]);

            // Actualizar estado de la cuota
            if ($cuota) {
                $montoPagadoCuota = (float) $cuota->monto_pagado + $montoTotal;
                $totalConMora = (float) $cuota->total + (float) $cuota->mora;
                $estadoCuota = $montoPagadoCuota >= $totalConMora ? 'pagada' : 'parcial';

                $cuota->update([
                    'monto_pagado' => $montoPagadoCuota,
                    'estado'       => $estadoCuota,
                    'fecha_pago'   => $estadoCuota === 'pagada' ? now() : null,
                ]);
            }

            // Si el saldo es 0, cancelar el crédito
            if ($nuevoSaldo <= 0) {
                $credito->update([
                    'estado'            => 'cancelado',
                    'fecha_cancelacion' => now(),
                    'saldo_actual'      => 0,
                ]);
            }

            return $pago;
        });
    }

    /**
     * Calcula y actualiza la mora sobre cuotas vencidas.
     * Mora = tasa_mora_mensual / 30 * días_mora * saldo_cuota (diaria).
     * No se cobra mora dentro de los días de gracia configurados.
     */
    public function calcularMora(Credito $credito): array
    {
        $cuotasVencidas = $credito->cuotas()
            ->where('estado', '!=', 'pagada')
            ->where('fecha_vencimiento', '<', now())
            ->get();

        $totalMora   = 0;
        $diasMoraMax = 0;

        foreach ($cuotasVencidas as $cuota) {
            $diasMora = $cuota->actualizarDiasMora();
            $diasMoraMax = max($diasMoraMax, $diasMora);

            if ($diasMora > 0) {
                // Mora diaria = (tasa_mora_mensual% / 30) * saldo_restante * dias_mora
                $tasaDiaria = ((float) $credito->tasa_mora_mensual / 100) / 30;
                $mora = round((float) $cuota->restante * $tasaDiaria * $diasMora, 2);
                $cuota->update(['mora' => $mora]);
                $totalMora += $mora;
            }
        }

        $credito->update([
            'dias_mora'      => $diasMoraMax,
            'mora_acumulada' => $totalMora,
        ]);

        return [
            'total_mora'     => $totalMora,
            'dias_mora'      => $diasMoraMax,
            'cuotas_vencidas' => $cuotasVencidas->count(),
        ];
    }

    /**
     * Refinancia el saldo pendiente de un crédito en una nueva factura a crédito.
     */
    public function refinanciar(Credito $creditoOriginal, array $datos): Credito
    {
        return DB::transaction(function () use ($creditoOriginal, $datos) {
            $saldoAnterior      = (float) $creditoOriginal->saldo_actual;
            $interesesPendientes = $creditoOriginal->cuotas()->pendientes()->sum('interes');
            $nuevoMonto          = $datos['nuevo_monto'] ?? ($saldoAnterior + $interesesPendientes);

            $nuevoCredito = $this->crearCredito(array_merge($datos, [
                'cliente_id'           => $creditoOriginal->cliente_id,
                'monto'                => $nuevoMonto,
                'condicion_credito_id' => $datos['condicion_credito_id'] ?? $creditoOriginal->condicion_credito_id,
            ]));

            $creditoOriginal->update(['estado' => 'refinanciado']);

            \App\Models\Refinanciamiento::create([
                'credito_original_id'  => $creditoOriginal->id,
                'credito_nuevo_id'     => $nuevoCredito->id,
                'saldo_anterior'       => $saldoAnterior,
                'nuevo_monto'          => $nuevoMonto,
                'intereses_pendientes' => $interesesPendientes,
                'motivo'               => $datos['motivo'] ?? 'Refinanciamiento',
                'fecha'                => now(),
                'aprobado_por'         => $datos['aprobado_por'] ?? auth()->id(),
            ]);

            return $nuevoCredito;
        });
    }

    /**
     * Registra un embargo sobre un crédito incobrable.
     */
    public function registrarEmbargo(Credito $credito, array $datos): \App\Models\Embargo
    {
        return DB::transaction(function () use ($credito, $datos) {
            $credito->update(['estado' => 'embargado']);

            return \App\Models\Embargo::create([
                'credito_id'        => $credito->id,
                'fecha_embargo'     => $datos['fecha_embargo'] ?? now(),
                'descripcion_bienes' => $datos['descripcion_bienes'],
                'valor_estimado'    => $datos['valor_estimado'],
                'estado'            => 'activo',
                'observaciones'     => $datos['observaciones'] ?? null,
                'registrado_por'    => $datos['registrado_por'] ?? auth()->id(),
            ]);
        });
    }

    // ─── Métodos privados ────────────────────────────────────────────────

    /**
     * Determina si el cliente es "frecuente" según la condición de crédito.
     * Criterios El Salvador LPC: historial de pago, compras anteriores.
     */
    private function esClienteFrecuente(Cliente $cliente, CondicionCredito $condicion): bool
    {
        // Contar créditos pagados/cancelados del cliente
        $comprasPagadas = Credito::where('cliente_id', $cliente->id)
            ->where('estado', 'cancelado')
            ->count();

        return $comprasPagadas >= $condicion->compras_minimas_cliente_frecuente;
    }

    /**
     * Calcula la fecha de vencimiento según la frecuencia de pago.
     *
     * @param Carbon $base         Fecha base (primera cuota o fecha inicio)
     * @param int    $n            Número de cuotas/período
     * @param string $frecuencia   semanal|quincenal|mensual
     * @param bool   $baseIsFirst  Si true, la base es la primera cuota y el período se cuenta desde allí
     */
    private function calcularFechaVencimiento(
        Carbon $base,
        int    $n,
        string $frecuencia,
        bool   $baseIsFirst = false
    ): Carbon {
        $intervalo = match ($frecuencia) {
            'semanal'   => $n * 7,
            'quincenal' => $n * 15,
            'mensual'   => null, // Usamos addMonths para precisión
            default     => $n * 30,
        };

        if ($baseIsFirst) {
            // Contamos desde la primera cuota: cuota N está (N-1) intervalos después
            $periodos = $n - 1;
        } else {
            $periodos = $n;
        }

        if ($frecuencia === 'mensual') {
            return $base->copy()->addMonths($periodos);
        }

        $dias = match ($frecuencia) {
            'semanal'   => $periodos * 7,
            'quincenal' => $periodos * 15,
            default     => $periodos * 30,
        };

        return $base->copy()->addDays($dias);
    }

    /**
     * Método helper para crear créditos sin venta (para refinanciamientos internos).
     */
    private function crearCredito(array $datos): Credito
    {
        $condicion    = CondicionCredito::findOrFail($datos['condicion_credito_id']);
        $numeroCuotas = (int) ($datos['numero_cuotas'] ?? 1);
        $plazoDias    = $condicion->calcularPlazoDias($numeroCuotas);

        $fechaPrimeraCuota = now()->addMonth();
        $fechaVencimiento  = $this->calcularFechaVencimiento(
            $fechaPrimeraCuota,
            $numeroCuotas,
            $condicion->frecuencia_pago
        );

        $credito = Credito::create([
            'numero'               => Credito::generarNumero(),
            'cliente_id'           => $datos['cliente_id'],
            'condicion_credito_id' => $condicion->id,
            'vendedor_id'          => $datos['vendedor_id'] ?? null,
            'cartera_id'           => $datos['cartera_id'] ?? null,
            'monto_original'       => $datos['monto'],
            'saldo_actual'         => $datos['monto'],
            'tasa_interes_anual'   => $condicion->tasa_interes_anual,
            'tasa_mora_mensual'    => $condicion->tasa_mora_mensual,
            'dias_gracia'          => $condicion->dias_gracia,
            'numero_cuotas'        => $numeroCuotas,
            'frecuencia_pago'      => $condicion->frecuencia_pago,
            'plazo_dias'           => $plazoDias,
            'fecha_solicitud'      => now(),
            'fecha_aprobacion'     => now(),
            'fecha_primera_cuota'  => $fechaPrimeraCuota,
            'fecha_vencimiento'    => $fechaVencimiento,
            'estado'               => 'vigente',
            'observaciones'        => $datos['observaciones'] ?? null,
            'aprobado_por'         => auth()->id(),
        ]);

        $this->generarPlanPago($credito);

        return $credito;
    }
}
