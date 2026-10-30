<?php

namespace App\Services;

use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\VendedorMeta;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class VendedorMetricasService
{
    public function periodo(string $mes): Carbon
    {
        Validator::make(['mes' => $mes], ['mes' => 'required|date_format:Y-m|after_or_equal:2000-01|before_or_equal:2100-12'])->validate();

        return Carbon::createFromFormat('!Y-m', $mes)->startOfMonth();
    }

    /** Ventas comerciales emitidas: sin sumar préstamos ni recibos otra vez. */
    public function ventas(Carbon $periodo): Builder
    {
        return Venta::whereBetween('fecha_emision', [$periodo->toDateString(), $periodo->copy()->endOfMonth()->toDateString()])
            ->whereIn('tipo_documento', ['01', '03', '11'])->whereIn('estado_dte', ['local', 'procesado'])
            ->where(fn ($q) => $q->where('condicion_operacion', '1')->orWhere(fn ($q) => $q
                ->where('condicion_operacion', '2')->whereHas('credito', fn ($c) => $c->withTrashed()
                ->whereIn('estado', ['vigente', 'vencido', 'incobrable', 'cancelado', 'refinanciado']))));
    }

    public function cobros(Carbon $periodo): Builder
    {
        return Pago::whereBetween('fecha_pago', [$periodo->toDateString(), $periodo->copy()->endOfMonth()->toDateString()]);
    }

    /** La cartera siempre representa el saldo actual, no un corte histórico ficticio. */
    public function cartera(?int $vendedorId = null): Builder
    {
        $hoy = today()->toDateString();
        $cuotas = Cuota::select('credito_id')->selectRaw('SUM(GREATEST(total + mora - monto_pagado, 0)) AS pendiente')
            ->selectRaw('SUM(CASE WHEN fecha_vencimiento < ? THEN GREATEST(total + mora - monto_pagado, 0) ELSE 0 END) AS vencido', [$hoy])
            ->selectRaw('MIN(CASE WHEN fecha_vencimiento < ? AND total + mora - monto_pagado > 0 THEN fecha_vencimiento END) AS primer_vencimiento', [$hoy])
            ->groupBy('credito_id');

        return Credito::query()->leftJoinSub($cuotas, 'saldo_cuotas', 'saldo_cuotas.credito_id', '=', 'creditos.id')
            ->select('creditos.*')->selectRaw('GREATEST(COALESCE(saldo_cuotas.pendiente, 0), creditos.saldo_actual) AS deuda_total')
            ->selectRaw('COALESCE(saldo_cuotas.vencido, 0) AS deuda_vencida, saldo_cuotas.primer_vencimiento')
            ->whereIn('creditos.estado', ['vigente', 'vencido', 'incobrable'])
            ->when($vendedorId !== null, fn ($q) => $q->where('creditos.vendedor_id', $vendedorId));
    }

    public function resumenes(Collection $vendedores, Carbon $periodo): Collection
    {
        $ids = $vendedores->pluck('id');
        $ventas = $this->ventas($periodo)->whereIn('vendedor_id', $ids)->select('vendedor_id')
            ->selectRaw('COUNT(*) AS cantidad, SUM(total_pagar - total_iva) AS sin_iva, SUM(total_pagar) AS total')
            ->selectRaw("SUM(CASE WHEN condicion_operacion = '1' THEN total_pagar ELSE 0 END) AS contado, SUM(CASE WHEN condicion_operacion = '2' THEN total_pagar ELSE 0 END) AS credito")
            ->groupBy('vendedor_id')->get()->keyBy('vendedor_id');
        $cobros = $this->cobros($periodo)->whereIn('vendedor_id', $ids)->select('vendedor_id')
            ->selectRaw('COUNT(*) AS cantidad, SUM(monto_total) AS total, SUM(abono_capital) AS capital, SUM(pago_interes) AS interes, SUM(pago_mora) AS mora')
            ->groupBy('vendedor_id')->get()->keyBy('vendedor_id');
        $cartera = $this->cartera()->whereIn('creditos.vendedor_id', $ids)->get()->groupBy('vendedor_id');
        $metas = VendedorMeta::whereIn('vendedor_id', $ids)->whereDate('periodo', $periodo->toDateString())->get()->keyBy('vendedor_id');

        return $vendedores->mapWithKeys(function ($vendedor) use ($ventas, $cobros, $cartera, $metas) {
            $v = $ventas->get($vendedor->id);
            $c = $cobros->get($vendedor->id);
            $deudas = $cartera->get($vendedor->id, collect());
            $meta = $metas->get($vendedor->id);
            $venta = round((float) ($v?->sin_iva ?? 0), 2);
            $cobro = round((float) ($c?->total ?? 0), 2);
            $vencidos = $deudas->filter(fn ($d) => (float) $d->deuda_vencida > 0);

            return [$vendedor->id => [
                'vendedor' => $vendedor, 'meta_definida' => $meta !== null,
                'meta_ventas' => (float) ($meta?->meta_ventas ?? 0), 'meta_cobros' => (float) ($meta?->meta_cobros ?? 0),
                'ventas' => $venta, 'documentos' => (int) ($v?->cantidad ?? 0), 'facturado' => (float) ($v?->total ?? 0),
                'contado' => (float) ($v?->contado ?? 0), 'credito' => (float) ($v?->credito ?? 0),
                'cobros' => $cobro, 'recibos' => (int) ($c?->cantidad ?? 0), 'capital_cobrado' => (float) ($c?->capital ?? 0),
                'interes_cobrado' => (float) ($c?->interes ?? 0), 'mora_cobrada' => (float) ($c?->mora ?? 0),
                'avance_ventas' => $meta && (float) $meta->meta_ventas > 0 ? round($venta / (float) $meta->meta_ventas * 100, 2) : null,
                'avance_cobros' => $meta && (float) $meta->meta_cobros > 0 ? round($cobro / (float) $meta->meta_cobros * 100, 2) : null,
                'cartera_capital' => round($deudas->sum('saldo_actual'), 2), 'cartera_total' => round($deudas->sum('deuda_total'), 2),
                'vencido' => round($deudas->sum('deuda_vencida'), 2),
                'incobrable' => round($deudas->where('estado', 'incobrable')->sum('saldo_actual'), 2),
                'clientes_morosos' => $vencidos->pluck('cliente_id')->unique()->count(), 'creditos' => $deudas->count(),
            ]];
        });
    }

    public function productos(int $vendedorId, Carbon $periodo): Collection
    {
        return DB::table('detalle_ventas')->joinSub($this->ventas($periodo)->where('vendedor_id', $vendedorId)->select('ventas.id'), 'ventas_validas', 'ventas_validas.id', '=', 'detalle_ventas.venta_id')
            ->leftJoin('productos', 'productos.id', '=', 'detalle_ventas.producto_id')
            ->select('productos.id', 'productos.codigo', 'productos.nombre')
            ->selectRaw('SUM(detalle_ventas.cantidad) AS unidades, SUM(detalle_ventas.venta_gravada) AS importe')
            ->groupBy('productos.id', 'productos.codigo', 'productos.nombre')->orderByDesc('importe')->limit(20)->get();
    }

    public function incidencias(Carbon $periodo): array
    {
        return [
            'ventas_sin_vendedor' => $this->ventas($periodo)->whereNull('vendedor_id')->count(),
            'cobros_sin_vendedor' => $this->cobros($periodo)->whereNull('vendedor_id')->count(),
            'cartera_sin_responsable' => Credito::whereIn('estado', ['vigente', 'vencido', 'incobrable'])->whereNull('vendedor_id')->count(),
            'cobros_reconstruidos' => $this->cobros($periodo)->where('vendedor_reconstruido', true)->count(),
            'cobros_pendientes_revision' => $this->cobros($periodo)->where('vendedor_reconstruido', true)->whereNull('vendedor_revisado_at')->count(),
        ];
    }
}
