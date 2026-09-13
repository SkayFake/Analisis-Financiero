<?php

namespace App\Services;

class RatioFinancieroService
{
    /**
     * Calcula todos los ratios financieros a partir de un Balance General
     * y Estado de Resultados.
     */
    public function calcular(array $balanceGeneral, array $estadoResultados): array
    {
        $ratios = [];

        // ─── Totales del Balance ─────────────────────────────
        $activoCorriente = array_sum($balanceGeneral['activo_corriente'] ?? []);
        $activoNoCorriente = array_sum($balanceGeneral['activo_no_corriente'] ?? []);
        $activoTotal = $activoCorriente + $activoNoCorriente;

        $pasivoCorriente = array_sum($balanceGeneral['pasivo_corriente'] ?? []);
        $pasivoNoCorriente = array_sum($balanceGeneral['pasivo_no_corriente'] ?? []);
        $pasivoTotal = $pasivoCorriente + $pasivoNoCorriente;

        $patrimonio = array_sum($balanceGeneral['patrimonio'] ?? []);

        $inventarios = $balanceGeneral['activo_corriente']['inventarios'] ?? 0;
        $cuentasPorCobrar = $balanceGeneral['activo_corriente']['cuentas_por_cobrar'] ?? 0;

        // ─── Totales del Estado de Resultados ─────────────────
        $ingresos = $estadoResultados['ingresos_operacionales'] ?? 0;
        $costoVentas = $estadoResultados['costo_ventas'] ?? 0;
        $gastosOperacionales = $estadoResultados['gastos_operacionales'] ?? 0;
        $gastosAdministrativos = $estadoResultados['gastos_administrativos'] ?? 0;
        $gastosFinancieros = $estadoResultados['gastos_financieros'] ?? 0;
        $otrosIngresos = $estadoResultados['otros_ingresos'] ?? 0;
        $otrosGastos = $estadoResultados['otros_gastos'] ?? 0;
        $impuestoRenta = $estadoResultados['impuesto_renta'] ?? 0;

        $utilidadBruta = $ingresos - $costoVentas;
        $utilidadOperacional = $utilidadBruta - $gastosOperacionales - $gastosAdministrativos;
        $utilidadNeta = $utilidadOperacional - $gastosFinancieros + $otrosIngresos - $otrosGastos - $impuestoRenta;

        // ─── Ratios de Liquidez ──────────────────────────────
        $ratios['liquidez'] = [
            'razon_corriente' => $pasivoCorriente > 0
                ? round($activoCorriente / $pasivoCorriente, 4) : null,
            'prueba_acida' => $pasivoCorriente > 0
                ? round(($activoCorriente - $inventarios) / $pasivoCorriente, 4) : null,
            'capital_trabajo' => round($activoCorriente - $pasivoCorriente, 2),
        ];

        // ─── Ratios de Endeudamiento ─────────────────────────
        $ratios['endeudamiento'] = [
            'deuda_activo' => $activoTotal > 0
                ? round($pasivoTotal / $activoTotal, 4) : null,
            'deuda_patrimonio' => $patrimonio > 0
                ? round($pasivoTotal / $patrimonio, 4) : null,
            'cobertura_intereses' => $gastosFinancieros > 0
                ? round($utilidadOperacional / $gastosFinancieros, 4) : null,
            'apalancamiento' => $patrimonio > 0
                ? round($activoTotal / $patrimonio, 4) : null,
        ];

        // ─── Ratios de Rentabilidad ──────────────────────────
        $ratios['rentabilidad'] = [
            'margen_bruto' => $ingresos > 0
                ? round(($utilidadBruta / $ingresos) * 100, 2) : null,
            'margen_operacional' => $ingresos > 0
                ? round(($utilidadOperacional / $ingresos) * 100, 2) : null,
            'margen_neto' => $ingresos > 0
                ? round(($utilidadNeta / $ingresos) * 100, 2) : null,
            'roa' => $activoTotal > 0
                ? round(($utilidadNeta / $activoTotal) * 100, 2) : null,
            'roe' => $patrimonio > 0
                ? round(($utilidadNeta / $patrimonio) * 100, 2) : null,
        ];

        // ─── Ratios de Actividad ─────────────────────────────
        $ratios['actividad'] = [
            'rotacion_cuentas_cobrar' => $cuentasPorCobrar > 0
                ? round($ingresos / $cuentasPorCobrar, 2) : null,
            'periodo_cobro_dias' => ($cuentasPorCobrar > 0 && $ingresos > 0)
                ? round(($cuentasPorCobrar / $ingresos) * 365, 0) : null,
            'rotacion_inventarios' => $inventarios > 0
                ? round($costoVentas / $inventarios, 2) : null,
            'dias_inventario' => ($inventarios > 0 && $costoVentas > 0)
                ? round(($inventarios / $costoVentas) * 365, 0) : null,
            'rotacion_activos' => $activoTotal > 0
                ? round($ingresos / $activoTotal, 2) : null,
        ];

        // ─── Resumen ─────────────────────────────────────────
        $ratios['resumen'] = [
            'activo_total' => round($activoTotal, 2),
            'pasivo_total' => round($pasivoTotal, 2),
            'patrimonio' => round($patrimonio, 2),
            'utilidad_bruta' => round($utilidadBruta, 2),
            'utilidad_operacional' => round($utilidadOperacional, 2),
            'utilidad_neta' => round($utilidadNeta, 2),
        ];

        return $ratios;
    }

    /**
     * Evalúa la salud financiera general basándose en los ratios.
     */
    public function evaluarSaludFinanciera(array $ratios): array
    {
        $evaluacion = [];

        // Liquidez
        $razonCorriente = $ratios['liquidez']['razon_corriente'] ?? 0;
        if ($razonCorriente >= 2) {
            $evaluacion['liquidez'] = ['estado' => 'buena', 'color' => 'green'];
        } elseif ($razonCorriente >= 1) {
            $evaluacion['liquidez'] = ['estado' => 'aceptable', 'color' => 'yellow'];
        } else {
            $evaluacion['liquidez'] = ['estado' => 'riesgo', 'color' => 'red'];
        }

        // Endeudamiento
        $deudaActivo = $ratios['endeudamiento']['deuda_activo'] ?? 0;
        if ($deudaActivo <= 0.4) {
            $evaluacion['endeudamiento'] = ['estado' => 'bajo', 'color' => 'green'];
        } elseif ($deudaActivo <= 0.6) {
            $evaluacion['endeudamiento'] = ['estado' => 'moderado', 'color' => 'yellow'];
        } else {
            $evaluacion['endeudamiento'] = ['estado' => 'alto', 'color' => 'red'];
        }

        // Rentabilidad
        $margenNeto = $ratios['rentabilidad']['margen_neto'] ?? 0;
        if ($margenNeto >= 10) {
            $evaluacion['rentabilidad'] = ['estado' => 'buena', 'color' => 'green'];
        } elseif ($margenNeto >= 0) {
            $evaluacion['rentabilidad'] = ['estado' => 'aceptable', 'color' => 'yellow'];
        } else {
            $evaluacion['rentabilidad'] = ['estado' => 'pérdida', 'color' => 'red'];
        }

        return $evaluacion;
    }
}
