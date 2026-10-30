<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\PagoCuota;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\CondicionCredito;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use InvalidArgumentException;
use App\Rules\{DuiSalvador, TelefonoSalvador};
use App\Support\DatosSalvador;
use Illuminate\Support\Facades\Validator;

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
 * - Cliente FRECUENTE + monto ≤ límite auto-aprobado + requisitos cumplidos → aprobación automática.
 * - Cliente NUEVO o monto mayor o documentación incompleta → requiere revisión manual (pendiente_aprobacion).
 * - Documentación requerida: DUI (Ley AML) y fiador cuando la condición lo exija.
 */
class CreditoService
{
    /**
     * Valida las reglas de elegibilidad del crédito comercial.
     * Centraliza las reglas compartidas entre creación manual y punto de venta.
     *
     * @param Cliente $cliente
     * @param CondicionCredito $condicion
     * @param int $numeroCuotas
     * @param Carbon $fechaPrimeraCuota
     * @param array $documentacion ['dui_verificado' => bool, 'tiene_fiador' => bool, 'referencia_verificada' => bool]
     * @param bool $validarRequisitosParaCreacion Si es false, permite guardar solicitudes pendientes con requisitos incompletos.
     * @throws InvalidArgumentException
     */
    public function validarElegibilidadCredito(
        Cliente $cliente,
        CondicionCredito $condicion,
        int $numeroCuotas,
        Carbon $fechaPrimeraCuota,
        array $documentacion = [],
        bool $validarRequisitosParaCreacion = false
    ): void {
        if (!$condicion->activa) {
            throw new InvalidArgumentException("La condición de crédito '{$condicion->nombre}' está inactiva y no admite nuevos créditos.");
        }

        if (!$cliente->activo) {
            throw new InvalidArgumentException("El cliente '{$cliente->nombre}' no está habilitado para operaciones comerciales.");
        }

        if ($numeroCuotas < 1) {
            throw new InvalidArgumentException("El número de cuotas debe ser al menos 1.");
        }
        if ($numeroCuotas > 120) {
            throw new InvalidArgumentException('El número de cuotas supera el límite de 120 de la aplicación.');
        }

        if ($numeroCuotas > $condicion->plazo_maximo_cuotas) {
            throw new InvalidArgumentException("El número de cuotas ({$numeroCuotas}) supera el límite máximo permitido ({$condicion->plazo_maximo_cuotas}) para la condición '{$condicion->nombre}'.");
        }

        if ($fechaPrimeraCuota->isPast() && !$fechaPrimeraCuota->isToday()) {
            throw new InvalidArgumentException("La primera fecha de pago debe ser hoy o una fecha futura.");
        }

        if ($validarRequisitosParaCreacion) {
            $this->validarRequisitosCompletos($cliente, $condicion, $documentacion);
        }
    }

    /**
     * Valida que se cumplan estrictamente los requisitos documentales (DUI y Fiador).
     */
    public function validarRequisitosCompletos(
        Cliente $cliente,
        CondicionCredito $condicion,
        array $documentacion = []
    ): void {
        $duiVerificado = !empty($documentacion['dui_verificado']);
        $tieneFiador = !empty($documentacion['tiene_fiador']);

        if ($condicion->requiere_dui) {
            $duiPresente = DatosSalvador::duiValido($cliente->dui);
            if (!$duiPresente || !$duiVerificado) {
                throw new InvalidArgumentException("La condición de crédito exige presentar y verificar el DUI del cliente.");
            }
        }

        if ($condicion->requiere_fiador) {
            if (!$tieneFiador) {
                throw new InvalidArgumentException("La condición de crédito exige registrar al menos un fiador.");
            }
        }
    }

    /**
     * Verifica si el crédito cumple los requisitos para ser aprobado.
     */
    public function cumpleRequisitosAprobacion(Credito $credito): bool
    {
        $condicion = $credito->condicionCredito;
        if (!$condicion) {
            return false;
        }

        if ($condicion->requiere_dui) {
            $cliente = $credito->cliente;
            $duiPresente = $cliente && DatosSalvador::duiValido($cliente->dui);
            if (!$duiPresente || !$credito->dui_verificado) {
                return false;
            }
        }

        if ($condicion->requiere_fiador) {
            if ($credito->fiadores()->count() === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtiene el listado de requisitos faltantes para aprobar un crédito.
     */
    public function obtenerRequisitosFaltantes(Credito $credito): array
    {
        $faltantes = [];
        $condicion = $credito->condicionCredito;
        if (!$condicion) {
            $faltantes[] = 'No tiene condición de crédito asignada.';
            return $faltantes;
        }

        if ($condicion->requiere_dui) {
            $cliente = $credito->cliente;
            if (!$cliente || !DatosSalvador::duiValido($cliente->dui)) {
                $faltantes[] = 'Número de DUI no registrado o con formato/verificador inválido.';
            }
            if (!$credito->dui_verificado) {
                $faltantes[] = 'Verificación del documento DUI.';
            }
        }

        if ($condicion->requiere_fiador) {
            if ($credito->fiadores()->count() === 0) {
                $faltantes[] = 'Registro de fiador requerido por la condición.';
            }
        }

        return $faltantes;
    }

    /**
     * Crea un crédito comercial a partir de una venta al crédito.
     *
     * @param Venta $venta Venta con condicion_operacion='2'
     * @param array $condiciones
     * @return Credito
     */
    public function crearCreditoDesdeVenta(Venta $venta, array $condiciones): Credito
    {
        $condiciones = DatosSalvador::normalizar($condiciones);
        Validator::make($condiciones, [
            'fiador' => 'nullable|array:nombre,dui,telefono,direccion,observaciones',
            'fiador.nombre' => 'required_with:fiador|string|max:150|regex:/[\p{L}]/u',
            'fiador.dui' => ['bail', 'nullable', 'string', new DuiSalvador],
            'fiador.telefono' => ['bail', 'nullable', 'string', new TelefonoSalvador],
            'fiador.direccion' => 'nullable|string|max:250',
            'fiador.observaciones' => 'nullable|string|max:2000',
            'fiadores' => 'prohibited',
        ])->validate();
        return DB::transaction(function () use ($venta, $condiciones) {
            // Bloqueo pesimista para evitar carreras y créditos duplicados
            $ventaLocked = Venta::where('id', $venta->id)->lockForUpdate()->firstOrFail();

            if ($ventaLocked->condicion_operacion !== '2') {
                throw new InvalidArgumentException('Solo las ventas al crédito generan un crédito comercial.');
            }

            if ($ventaLocked->credito()->lockForUpdate()->exists()) {
                throw new InvalidArgumentException("La factura {$ventaLocked->numero_control} ya tiene un crédito asociado.");
            }

            if (empty($condiciones['condicion_credito_id'])) {
                throw new InvalidArgumentException("Debe seleccionar una condición de crédito válida.");
            }

            $condicion = CondicionCredito::findOrFail($condiciones['condicion_credito_id']);
            $cliente = Cliente::findOrFail($ventaLocked->cliente_id);
            $vendedorId = $condiciones['vendedor_id'] ?? $ventaLocked->vendedor_id ?? $cliente->vendedor_id;
            if ($vendedorId !== null && (!is_scalar($vendedorId) || !ctype_digit((string) $vendedorId)
                || !\App\Models\Vendedor::whereKey($vendedorId)->where('activo', true)->sharedLock()->first())) {
                throw new InvalidArgumentException('El responsable del crédito debe ser un vendedor activo.');
            }
            $carteraId = $condiciones['cartera_id'] ?? ((int) $cliente->vendedor_id === (int) $vendedorId ? $cliente->cartera_id : null);
            if ($carteraId !== null && !\App\Models\Cartera::whereKey($carteraId)->where('activa', true)
                ->where(fn ($q) => $q->whereNull('vendedor_id')->orWhere('vendedor_id', $vendedorId))->exists()) {
                throw new InvalidArgumentException('La cartera debe estar activa y corresponder al responsable del crédito.');
            }
            $numeroCuotas = (int) ($condiciones['numero_cuotas'] ?? 1);

            $fechaPrimeraCuota = isset($condiciones['fecha_primera_cuota'])
                ? Carbon::parse($condiciones['fecha_primera_cuota'])
                : now()->addMonth();

            $documentacion = [
                'dui_verificado' => !empty($condiciones['dui_verificado']),
                'tiene_fiador' => !empty($condiciones['fiador']) || !empty($condiciones['fiadores']),
                'referencia_verificada' => !empty($condiciones['referencia_verificada']),
                'dui_cliente' => $cliente->dui,
            ];

            // Validaciones compartidas: condición activa, límites de cuotas, cliente habilitado, fechas
            $this->validarElegibilidadCredito(
                $cliente,
                $condicion,
                $numeroCuotas,
                $fechaPrimeraCuota,
                $documentacion,
                validarRequisitosParaCreacion: false
            );

            // Requisitos cumplidos para evaluar auto-aprobación
            $requisitosCompletos = true;
            try {
                $this->validarRequisitosCompletos($cliente, $condicion, $documentacion);
            } catch (InvalidArgumentException $e) {
                $requisitosCompletos = false;
            }

            $montoFactura = (float) $ventaLocked->total_pagar;
            $esClienteFrecuente = $this->esClienteFrecuente($cliente, $condicion);

            $autoAprobado = $requisitosCompletos
                && $esClienteFrecuente
                && $montoFactura <= (float) $condicion->monto_auto_aprobado;

            $estado = $autoAprobado ? 'vigente' : 'pendiente_aprobacion';
            $fechaAprobacion = $autoAprobado ? now() : null;
            $plazoDias = $condicion->calcularPlazoDias($numeroCuotas);

            // La fecha de vencimiento del crédito coincide exactamente con el vencimiento de la última cuota
            $fechaVencimiento = $this->calcularFechaVencimiento(
                $fechaPrimeraCuota,
                $numeroCuotas,
                $condicion->frecuencia_pago,
                baseIsFirst: true
            );

            $credito = Credito::create([
                'numero'               => Credito::generarNumero(),
                'cliente_id'           => $ventaLocked->cliente_id,
                'venta_id'             => $ventaLocked->id,
                'condicion_credito_id' => $condicion->id,
                'vendedor_id'          => $vendedorId,
                'vendedor_origen_id'   => $ventaLocked->vendedor_id,
                'cartera_id'           => $carteraId,
                'politica_cobro_id'    => $condiciones['politica_cobro_id'] ?? null,
                'monto_original'       => $montoFactura,
                'saldo_actual'         => $montoFactura,
                'interes_acumulado'    => 0,
                'mora_acumulada'       => 0,
                'tasa_interes_anual'   => $condicion->tasa_interes_anual,
                'tasa_mora_mensual'    => $condicion->tasa_mora_mensual,
                'dias_gracia'          => $condicion->dias_gracia,
                'numero_cuotas'        => $numeroCuotas,
                'frecuencia_pago'      => $condicion->frecuencia_pago,
                'plazo_dias'           => $plazoDias,
                'fecha_solicitud'      => $ventaLocked->fecha_emision ?? now(),
                'fecha_aprobacion'     => $fechaAprobacion,
                'fecha_primera_cuota'  => $fechaPrimeraCuota,
                'fecha_vencimiento'    => $fechaVencimiento,
                'estado'               => $estado,
                'observaciones'        => $condiciones['observaciones'] ?? null,
                'aprobado_por'         => $autoAprobado ? auth()->id() : null,
                'motivo_aprobacion'    => $autoAprobado
                    ? 'Aprobación automática — cliente frecuente, requisitos completos y monto dentro del límite.'
                    : null,
                'dui_verificado'       => !empty($condiciones['dui_verificado']),
                'referencia_verificada' => !empty($condiciones['referencia_verificada']),
            ]);

            // Guardar fiador si viene en los datos
            if (!empty($condiciones['fiador']) && is_array($condiciones['fiador'])) {
                $credito->fiadores()->create($condiciones['fiador']);
            }

            if ($autoAprobado) {
                $this->generarPlanPago($credito);
            }

            if ($credito->vendedor) app(VendedorService::class)->auditar($credito->vendedor, 'credito_registrado', 'creditos', $credito->id, null,
                $credito->only(['numero', 'venta_id', 'vendedor_id', 'vendedor_origen_id', 'monto_original', 'estado']), 'Asignación inicial del crédito comercial', auth()->user());

            return $credito;
        });
    }

    /**
     * Calcula la tasa de interés efectiva por período según la frecuencia.
     * Convención institucional:
     * - Anual base: 360 días comerciales o períodos equivalentes.
     * - Mensual (12 períodos al año): tasa_anual / 12
     * - Quincenal (24 períodos al año): tasa_anual / 24
     * - Semanal (52 períodos al año): tasa_anual / 52
     */
    public function calcularTasaPeriodo(float $tasaAnualPorcentaje, string $frecuencia): float
    {
        $tasaDecimal = $tasaAnualPorcentaje / 100;
        return match ($frecuencia) {
            'semanal'   => $tasaDecimal / 52,
            'quincenal' => $tasaDecimal / 24,
            'mensual'   => $tasaDecimal / 12,
            default     => $tasaDecimal / 12,
        };
    }

    /**
     * Genera el plan de cuotas del crédito comercial.
     *
     * Si tasa_interes_anual = 0 → cuotas iguales sin interés (lo más común en crédito comercial).
     * Si tasa_interes_anual > 0 → sistema francés adaptado al período de pago.
     *
     * Se asegura que la suma de capitales sea exactamente igual al monto original
     * y el saldo final de la última cuota sea 0.00.
     */
    public function generarPlanPago(Credito $credito): void
    {
        $credito->cuotas()->delete();

        $monto        = round((float) $credito->monto_original, 2);
        $n            = $credito->numero_cuotas;
        $tasaPeriodo  = $this->calcularTasaPeriodo((float) $credito->tasa_interes_anual, $credito->frecuencia_pago);
        $fechaInicio  = $credito->fecha_primera_cuota ?? now()->addMonth();

        if ($tasaPeriodo > 0) {
            // Sistema francés: cuota fija = M * [r(1+r)^n] / [(1+r)^n - 1]
            $cuotaFija = $monto * ($tasaPeriodo * pow(1 + $tasaPeriodo, $n))
                / (pow(1 + $tasaPeriodo, $n) - 1);
            $cuotaFija = round($cuotaFija, 2);
        } else {
            // Sin interés: división simple redondeada a 2 decimales
            $cuotaFija = round($monto / $n, 2);
        }

        $saldoPendiente = $monto;
        $cuotas = [];
        $totalInteresAcumulado = 0.0;

        for ($i = 1; $i <= $n; $i++) {
            $interes = ($tasaPeriodo > 0) ? round($saldoPendiente * $tasaPeriodo, 2) : 0.00;
            $totalInteresAcumulado = round($totalInteresAcumulado + $interes, 2);

            if ($i === $n) {
                // Última cuota: el capital es exactamente el remanente para liquidar a 0
                $capital = $saldoPendiente;
                $saldoPendiente = 0.00;
            } else {
                $capital = ($tasaPeriodo > 0)
                    ? round($cuotaFija - $interes, 2)
                    : $cuotaFija;
                
                // Si la división dejó menos saldo que la cuota fija
                if ($capital > $saldoPendiente) {
                    $capital = $saldoPendiente;
                }
                $saldoPendiente = round($saldoPendiente - $capital, 2);
            }

            $totalCuota = round($capital + $interes, 2);

            $fechaVencimientoCuota = $this->calcularFechaVencimiento(
                $fechaInicio,
                $i,
                $credito->frecuencia_pago,
                baseIsFirst: true
            );

            $cuotas[] = [
                'credito_id'        => $credito->id,
                'numero_cuota'      => $i,
                'fecha_vencimiento' => $fechaVencimientoCuota->toDateString(),
                'capital'           => $capital,
                'interes'           => $interes,
                'total'             => $totalCuota,
                'mora'              => 0.00,
                'saldo_pendiente'   => $saldoPendiente,
                'monto_pagado'      => 0.00,
                'estado'            => 'pendiente',
                'created_at'        => now(),
                'updated_at'        => now(),
            ];
        }

        Cuota::insert($cuotas);

        // Actualizar interés acumulado en el crédito
        $credito->update([
            'interes_acumulado' => $totalInteresAcumulado,
        ]);
    }

    /**
     * Aprueba manualmente un crédito en estado pendiente_aprobacion.
     */
    public function aprobarCredito(Credito $credito, string $motivo, ?int $usuarioId = null): Credito
    {
        return DB::transaction(function () use ($credito, $motivo, $usuarioId) {
            $credito = Credito::where('id', $credito->id)->lockForUpdate()->firstOrFail();

            if ($credito->estado !== 'pendiente_aprobacion') {
                throw new InvalidArgumentException('Solo se pueden aprobar créditos en estado pendiente_aprobacion.');
            }

            $faltantes = $this->obtenerRequisitosFaltantes($credito);
            if (!empty($faltantes)) {
                $lista = implode(', ', $faltantes);
                throw new InvalidArgumentException("No se puede aprobar el crédito porque no cumple los requisitos: {$lista}");
            }

            $credito->update([
                'estado'            => 'vigente',
                'fecha_aprobacion'  => now(),
                'aprobado_por'      => $usuarioId ?? auth()->id(),
                'motivo_aprobacion' => $motivo,
            ]);

            $this->generarPlanPago($credito);

            return $credito->fresh();
        });
    }

    /**
     * Rechaza un crédito en estado pendiente_aprobacion.
     */
    public function rechazarCredito(Credito $credito, string $motivo, ?int $usuarioId = null): Credito
    {
        return DB::transaction(function () use ($credito, $motivo, $usuarioId) {
            $credito = Credito::where('id', $credito->id)->lockForUpdate()->firstOrFail();

            if ($credito->estado !== 'pendiente_aprobacion') {
                throw new InvalidArgumentException('Solo se pueden rechazar créditos pendientes de aprobación.');
            }

            $credito->update([
                'estado'            => 'rechazado',
                'aprobado_por'      => $usuarioId ?? auth()->id(),
                'motivo_aprobacion' => $motivo,
            ]);

            return $credito->fresh();
        });
    }

    /**
     * Procesa un pago sobre el crédito, distribuyéndolo entre las cuotas pendientes.
     * Orden estricto: mora → interés → capital sobre cuotas en orden de vencimiento.
     *
     * @param Credito $credito
     * @param array $datos ['monto', 'forma_pago', 'fecha_pago', 'referencia', 'observaciones', 'recibido_por']
     * @return Pago
     */
    public function procesarPago(Credito $credito, array $datos): Pago
    {
        return DB::transaction(function () use ($credito, $datos) {
            // Bloqueo pesimista del crédito y sus cuotas
            $credito = Credito::where('id', $credito->id)->lockForUpdate()->firstOrFail();

            if (!in_array($credito->estado, ['vigente', 'vencido'])) {
                throw new InvalidArgumentException("No se pueden registrar pagos en un crédito con estado '{$credito->estado}'.");
            }

            $montoPago = round((float) $datos['monto'], 2);
            if ($montoPago <= 0) {
                throw new InvalidArgumentException("El monto a pagar debe ser mayor a 0.");
            }

            app(ConciliacionPagosService::class)->conciliar($credito);

            // Obtener todas las cuotas pendientes ordenadas por fecha de vencimiento y número
            $cuotas = Cuota::where('credito_id', $credito->id)
                ->whereIn('estado', ['pendiente', 'vencida', 'parcial'])
                ->orderBy('fecha_vencimiento')
                ->orderBy('numero_cuota')
                ->lockForUpdate()
                ->get();

            // Calcular deuda total exigible (suma de conceptos pendientes de todas las cuotas)
            $totalDeudaPendiente = 0.0;
            $cuotasDetalle = [];

            foreach ($cuotas as $c) {
                $moraPagada = round((float) $c->aplicacionesPago()->sum('pago_mora'), 2);
                $interesPagado = round((float) $c->aplicacionesPago()->sum('pago_interes'), 2);
                $capitalPagado = round((float) $c->aplicacionesPago()->sum('abono_capital'), 2);

                $moraPendiente = max(0.0, round((float) $c->mora - $moraPagada, 2));
                $interesPendiente = max(0.0, round((float) $c->interes - $interesPagado, 2));
                $capitalPendiente = max(0.0, round((float) $c->capital - $capitalPagado, 2));
                $totalPendienteCuota = round($moraPendiente + $interesPendiente + $capitalPendiente, 2);

                $totalDeudaPendiente = round($totalDeudaPendiente + $totalPendienteCuota, 2);

                $cuotasDetalle[] = [
                    'model' => $c,
                    'mora_pendiente' => $moraPendiente,
                    'interes_pendiente' => $interesPendiente,
                    'capital_pendiente' => $capitalPendiente,
                    'mora_pagada_prev' => $moraPagada,
                    'interes_pagado_prev' => $interesPagado,
                    'capital_pagado_prev' => $capitalPagado,
                ];
            }

            // Si no hay cuotas pendientes y el saldo es 0
            if (empty($cuotasDetalle)) {
                throw new InvalidArgumentException("El crédito no posee cuotas pendientes de pago.");
            }

            // Rechazar importes superiores a la deuda pendiente mientras no exista una política explícita de saldo a favor
            if ($montoPago > $totalDeudaPendiente) {
                throw new InvalidArgumentException(sprintf(
                    "El monto ingresado ($%.2f) excede la deuda exigible pendiente ($%.2f).",
                    $montoPago,
                    $totalDeudaPendiente
                ));
            }

            $remPagar = $montoPago;
            $pagoTotalMora = 0.0;
            $pagoTotalInteres = 0.0;
            $pagoTotalCapital = 0.0;
            $aplicacionesPorCrear = [];
            $primeraCuotaId = $cuotasDetalle[0]['model']->id;

            foreach ($cuotasDetalle as $item) {
                if ($remPagar <= 0) {
                    break;
                }

                $cuotaModel = $item['model'];
                $moraPend = $item['mora_pendiente'];
                $intPend = $item['interes_pendiente'];
                $capPend = $item['capital_pendiente'];

                // 1. Aplicar a Mora
                $aplMora = min($remPagar, $moraPend);
                $remPagar = round($remPagar - $aplMora, 2);

                // 2. Aplicar a Interés
                $aplInteres = min($remPagar, $intPend);
                $remPagar = round($remPagar - $aplInteres, 2);

                // 3. Aplicar a Capital
                $aplCapital = min($remPagar, $capPend);
                $remPagar = round($remPagar - $aplCapital, 2);

                $totalCuotaAplicado = round($aplMora + $aplInteres + $aplCapital, 2);

                if ($totalCuotaAplicado > 0) {
                    $pagoTotalMora = round($pagoTotalMora + $aplMora, 2);
                    $pagoTotalInteres = round($pagoTotalInteres + $aplInteres, 2);
                    $pagoTotalCapital = round($pagoTotalCapital + $aplCapital, 2);

                    $aplicacionesPorCrear[] = [
                        'cuota' => $cuotaModel,
                        'monto_total' => $totalCuotaAplicado,
                        'abono_capital' => $aplCapital,
                        'pago_interes' => $aplInteres,
                        'pago_mora' => $aplMora,
                        'monto_pagado_total_cuota' => round($item['capital_pagado_prev'] + $item['interes_pagado_prev'] + $item['mora_pagada_prev'] + $totalCuotaAplicado, 2),
                        'deuda_total_cuota' => round((float) $cuotaModel->total + (float) $cuotaModel->mora, 2),
                    ];
                }
            }

            // Actualizar saldo del crédito
            $nuevoSaldo = max(0.0, round((float) $credito->saldo_actual - $pagoTotalCapital, 2));

            // Crear el registro de Pago (Recibo)
            $pago = Pago::create([
                'credito_id'     => $credito->id,
                'cuota_id'       => $primeraCuotaId,
                'numero_recibo'  => Pago::generarNumeroRecibo(),
                'fecha_pago'     => $datos['fecha_pago'] ?? now()->toDateString(),
                'monto_total'    => $montoPago,
                'abono_capital'  => $pagoTotalCapital,
                'pago_interes'   => $pagoTotalInteres,
                'pago_mora'      => $pagoTotalMora,
                'saldo_despues'  => $nuevoSaldo,
                'forma_pago'     => $datos['forma_pago'] ?? 'efectivo',
                'referencia'     => $datos['referencia'] ?? null,
                'observaciones'  => $datos['observaciones'] ?? null,
                'recibido_por'   => $datos['recibido_por'] ?? auth()->id(),
                'vendedor_id'    => $credito->vendedor_id,
                'vendedor_reconstruido' => false,
            ]);

            // Guardar aplicaciones individuales y actualizar cuotas
            foreach ($aplicacionesPorCrear as $apl) {
                PagoCuota::create([
                    'pago_id'       => $pago->id,
                    'cuota_id'      => $apl['cuota']->id,
                    'monto_total'   => $apl['monto_total'],
                    'abono_capital' => $apl['abono_capital'],
                    'pago_interes'  => $apl['pago_interes'],
                    'pago_mora'     => $apl['pago_mora'],
                ]);

                $estaPagada = $apl['monto_pagado_total_cuota'] >= $apl['deuda_total_cuota'];
                $apl['cuota']->update([
                    'monto_pagado' => $apl['monto_pagado_total_cuota'],
                    'estado'       => $estaPagada ? 'pagada' : 'parcial',
                    'fecha_pago'   => $estaPagada ? ($datos['fecha_pago'] ?? now()->toDateString()) : null,
                ]);
            }

            // Verificar si quedan cuotas pendientes con saldo
            $cuotasRestantes = Cuota::where('credito_id', $credito->id)
                ->whereIn('estado', ['pendiente', 'vencida', 'parcial'])
                ->count();

            // Actualizar crédito
            $datosCreditoUpdate = [
                'saldo_actual' => $nuevoSaldo,
            ];

            if ($nuevoSaldo <= 0 && $cuotasRestantes === 0) {
                $datosCreditoUpdate['estado'] = 'cancelado';
                $datosCreditoUpdate['fecha_cancelacion'] = $datos['fecha_pago'] ?? now()->toDateString();
            }

            $credito->update($datosCreditoUpdate);

            return $pago;
        });
    }

    /**
     * Calcula y actualiza la mora sobre cuotas vencidas.
     */
    public function calcularMora(Credito $credito): array
    {
        $cuotasVencidas = $credito->cuotas()
            ->where('estado', '!=', 'pagada')
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->get();

        $totalMora = 0.0;
        $diasMoraMax = 0;

        foreach ($cuotasVencidas as $cuota) {
            $diasMora = $cuota->actualizarDiasMora();
            $diasMoraMax = max($diasMoraMax, $diasMora);

            if ($diasMora > 0) {
                $tasaDiaria = ((float) $credito->tasa_mora_mensual / 100) / 30;
                $mora = round((float) $cuota->restante * $tasaDiaria * $diasMora, 2);
                $cuota->update(['mora' => $mora]);
                $totalMora = round($totalMora + $mora, 2);
            }
        }

        $credito->update([
            'dias_mora'      => $diasMoraMax,
            'mora_acumulada' => $totalMora,
        ]);

        return [
            'total_mora'      => $totalMora,
            'dias_mora'       => $diasMoraMax,
            'cuotas_vencidas' => $cuotasVencidas->count(),
        ];
    }

    /**
     * Determina si el cliente es "frecuente" según la condición de crédito.
     */
    public function esClienteFrecuente(Cliente $cliente, CondicionCredito $condicion): bool
    {
        $comprasPagadas = Credito::where('cliente_id', $cliente->id)
            ->where('estado', 'cancelado')
            ->count();

        return $comprasPagadas >= $condicion->compras_minimas_cliente_frecuente;
    }

    /**
     * Calcula la fecha de vencimiento según la frecuencia de pago.
     * Soporta semanal, quincenal y mensual con manejo de fin de mes y años bisiestos.
     *
     * @param Carbon $base         Fecha base (primera cuota)
     * @param int    $n            Número de cuota (1-based si baseIsFirst=true)
     * @param string $frecuencia   semanal|quincenal|mensual
     * @param bool   $baseIsFirst  Si true, cuota 1 es la fecha base y cuota N está a (N-1) períodos
     */
    public function calcularFechaVencimiento(
        Carbon $base,
        int    $n,
        string $frecuencia,
        bool   $baseIsFirst = false
    ): Carbon {
        $periodos = $baseIsFirst ? ($n - 1) : $n;
        if ($periodos <= 0) {
            return $base->copy();
        }

        if ($frecuencia === 'semanal') {
            return $base->copy()->addWeeks($periodos);
        }

        if ($frecuencia === 'quincenal') {
            // Convención comercial estándar: quincenas de 15 días calendario
            return $base->copy()->addDays($periodos * 15);
        }

        if ($frecuencia === 'mensual') {
            // Manejo de fin de mes y bisiestos:
            // Si la fecha base cae en el último día de su mes (ej. 31 de enero, 28/29 de feb, 30 de abr),
            // las siguientes cuotas mensuales deben ajustarse al último día del mes correspondiente.
            // Si no era fin de mes, Carbon addMonthsNoOverflow evita desbordes (ej. 30 de enero -> 28 feb).
            $esFinDeMes = $base->copy()->endOfMonth()->day === $base->day;

            if ($esFinDeMes) {
                return $base->copy()->addMonthsNoOverflow($periodos)->endOfMonth();
            } else {
                return $base->copy()->addMonthsNoOverflow($periodos);
            }
        }

        return $base->copy()->addDays($periodos * 30);
    }
}
