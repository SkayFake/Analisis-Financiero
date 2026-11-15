<?php

namespace App\Console\Commands;

use App\Models\ActivoFijo;
use App\Models\Credito;
use App\Models\Existencia;
use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerificarIntegridad extends Command
{
    protected $signature = 'erp:verificar-integridad';

    protected $description = 'Diagnóstico sin modificar saldos, archivos ni credenciales';

    public function handle(): int
    {
        $problemas = [];
        if (config('erp.dte.enabled')) {
            $problemas[] = 'DTE habilitado, pero el emisor fiscal sigue pendiente de integración y validación.';
        }
        if (Existencia::where('cantidad', '<', 0)->exists()) {
            $problemas[] = 'Existen cantidades negativas de inventario.';
        }
        if (Existencia::select('producto_id', 'bodega_id', 'lote')->groupBy('producto_id', 'bodega_id', 'lote')->havingRaw('COUNT(*)>1')->exists()) {
            $problemas[] = 'Existen filas de inventario duplicadas.';
        }
        foreach (Producto::where('metodo_costeo', 'PEPS')->get() as $p) {
            $stock = (float) $p->stock_total;
            $capas = (float) DB::table('capas_inventario')->where('producto_id', $p->id)->sum('cantidad');
            if (abs($stock - $capas) > 0.0001) {
                $problemas[] = 'Capas PEPS pendientes de conciliar: '.$p->codigo;
            }
        }
        $activos = ActivoFijo::where('estado', 'activo')->where(fn ($q) => $q->where('base_fiscal_verificada', false)->orWhereNull('fecha_inicio_uso'))->count();
        if ($activos) {
            $problemas[] = "Activos pendientes de revisar fecha de uso/base fiscal: {$activos}.";
        }
        $consultaHistoricos = Credito::whereIn('estado', ['vigente', 'vencido'])->where('calculo_interes', 'historico')->where('tasa_interes_anual', '>', 0);
        if (config('erp.modo_academico')) {
            $qa = (clone $consultaHistoricos)->where('observaciones', 'like', 'QA-%')->count();
            if ($qa) {
                $this->info('Escenarios QA históricos conservados para demostrar conciliación: '.$qa.'.');
            }
            $consultaHistoricos->where(fn ($q) => $q->whereNull('observaciones')->orWhere('observaciones', 'not like', 'QA-%'));
        }
        $historicos = $consultaHistoricos->count();
        if ($historicos) {
            $problemas[] = "Créditos históricos con interés pendientes de revisar anticipos: {$historicos}.";
        }
        if (! config('erp.modo_academico') && ! config('erp.creditos.limites_legales')) {
            $problemas[] = 'Faltan topes BCR verificados para originar créditos con interés o mora.';
        }
        foreach ($problemas as $p) {
            $this->warn($p);
        }
        $this->info('Diagnóstico técnico terminado. No certifica cumplimiento ISO, tributario ni legal.');

        return $problemas ? self::FAILURE : self::SUCCESS;
    }
}
