<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Pago;
use Illuminate\Support\Facades\DB;

class MetricasCobroService
{
    /**
     * Obtiene las métricas generales del dashboard de cobros.
     */
    public function dashboardMetricas(): array
    {
        $creditosActivos = Credito::activos();

        return [
            'cartera_total' => Credito::activos()->sum('saldo_actual'),
            'cartera_vigente' => Credito::vigentes()->sum('saldo_actual'),
            'cartera_vencida' => Credito::vencidos()->sum('saldo_actual'),
            'cartera_incobrable' => Credito::incobrables()->sum('saldo_actual'),
            'total_creditos_activos' => Credito::activos()->count(),
            'total_creditos_vencidos' => Credito::vencidos()->count(),
            'porcentaje_mora' => $this->porcentajeMora(),
            'cobros_mes_actual' => $this->cobrosDelPeriodo(
                now()->startOfMonth(),
                now()->endOfMonth()
            ),
            'cobros_hoy' => $this->cobrosDelPeriodo(
                now()->startOfDay(),
                now()->endOfDay()
            ),
        ];
    }

    /**
     * Métricas por cartera.
     */
    public function metricasPorCartera(): array
    {
        return Credito::activos()
            ->join('carteras', 'creditos.cartera_id', '=', 'carteras.id')
            ->select(
                'carteras.id',
                'carteras.nombre',
                DB::raw('COUNT(creditos.id) as total_creditos'),
                DB::raw('SUM(creditos.saldo_actual) as saldo_total'),
                DB::raw('SUM(CASE WHEN creditos.estado = \'vencido\' THEN creditos.saldo_actual ELSE 0 END) as saldo_vencido'),
                DB::raw('AVG(creditos.dias_mora) as promedio_dias_mora')
            )
            ->groupBy('carteras.id', 'carteras.nombre')
            ->get()
            ->toArray();
    }

    /**
     * Métricas por zona geográfica.
     */
    public function metricasPorZona(): array
    {
        return Credito::activos()
            ->join('clientes', 'creditos.cliente_id', '=', 'clientes.id')
            ->join('zonas', 'clientes.zona_id', '=', 'zonas.id')
            ->select(
                'zonas.id',
                'zonas.nombre',
                DB::raw('COUNT(DISTINCT clientes.id) as total_clientes'),
                DB::raw('COUNT(creditos.id) as total_creditos'),
                DB::raw('SUM(creditos.saldo_actual) as saldo_total'),
                DB::raw('SUM(CASE WHEN creditos.estado = \'vencido\' THEN 1 ELSE 0 END) as creditos_vencidos')
            )
            ->groupBy('zonas.id', 'zonas.nombre')
            ->get()
            ->toArray();
    }

    /**
     * Métricas por tipo de cliente.
     */
    public function metricasPorTipoCliente(): array
    {
        return Credito::activos()
            ->join('clientes', 'creditos.cliente_id', '=', 'clientes.id')
            ->select(
                'clientes.tipo',
                DB::raw('COUNT(DISTINCT clientes.id) as total_clientes'),
                DB::raw('COUNT(creditos.id) as total_creditos'),
                DB::raw('SUM(creditos.saldo_actual) as saldo_total'),
                DB::raw('AVG(creditos.saldo_actual) as saldo_promedio')
            )
            ->groupBy('clientes.tipo')
            ->get()
            ->toArray();
    }

    /**
     * Análisis de morosidad por rango de días.
     */
    public function analisisMorosidad(): array
    {
        $rangos = config('erp.cobros.clasificacion');
        $resultado = [];

        foreach ($rangos as $letra => $config) {
            $query = Credito::activos();

            if ($config['max_dias'] !== null) {
                $query->whereBetween('dias_mora', [$config['min_dias'], $config['max_dias']]);
            } else {
                $query->where('dias_mora', '>=', $config['min_dias']);
            }

            $resultado[$letra] = [
                'label' => $config['label'],
                'color' => $config['color'],
                'rango' => $config['max_dias']
                    ? "{$config['min_dias']}-{$config['max_dias']} días"
                    : "{$config['min_dias']}+ días",
                'total_creditos' => $query->count(),
                'saldo_total' => $query->sum('saldo_actual'),
            ];
        }

        return $resultado;
    }

    /**
     * Rendimiento por vendedor en un período.
     */
    public function rendimientoPorVendedor($fechaInicio, $fechaFin): array
    {
        return Pago::whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->join('creditos', 'pagos.credito_id', '=', 'creditos.id')
            ->join('vendedores', 'creditos.vendedor_id', '=', 'vendedores.id')
            ->select(
                'vendedores.id',
                'vendedores.nombre',
                'vendedores.meta_mensual',
                DB::raw('COUNT(pagos.id) as total_pagos'),
                DB::raw('SUM(pagos.monto_total) as total_cobrado'),
                DB::raw('SUM(pagos.abono_capital) as total_capital'),
                DB::raw('SUM(pagos.pago_interes) as total_interes')
            )
            ->groupBy('vendedores.id', 'vendedores.nombre', 'vendedores.meta_mensual')
            ->get()
            ->map(function ($v) {
                $v->porcentaje_meta = $v->meta_mensual > 0
                    ? round(($v->total_cobrado / $v->meta_mensual) * 100, 2)
                    : 0;
                return $v;
            })
            ->toArray();
    }

    /**
     * Total de cobros en un período.
     */
    public function cobrosDelPeriodo($fechaInicio, $fechaFin): float
    {
        return (float) Pago::whereBetween('fecha_pago', [$fechaInicio, $fechaFin])
            ->sum('monto_total');
    }

    /**
     * Porcentaje de cartera en mora.
     */
    private function porcentajeMora(): float
    {
        $totalActiva = (float) Credito::activos()->sum('saldo_actual');
        $totalVencida = (float) Credito::vencidos()->sum('saldo_actual');

        return $totalActiva > 0 ? round(($totalVencida / $totalActiva) * 100, 2) : 0;
    }
}
