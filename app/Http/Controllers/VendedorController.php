<?php

namespace App\Http\Controllers;

use App\Models\Cartera;
use App\Models\Credito;
use App\Models\GestionCobro;
use App\Models\Pago;
use App\Models\Vendedor;
use App\Models\VendedorMeta;
use App\Models\Venta;
use App\Services\VendedorMetricasService;
use App\Services\VendedorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class VendedorController extends Controller
{
    public function pendientes(Request $request, VendedorMetricasService $metricas)
    {
        Gate::authorize('viewAny', Vendedor::class);
        $request->validate(['mes' => 'nullable|string|date_format:Y-m', 'recibo' => 'nullable|integer|exists:pagos,id']);
        $periodo = $metricas->periodo($request->input('mes') ?: now()->format('Y-m'));
        $ventas = $metricas->ventas($periodo)->whereNull('vendedor_id')->with(['cliente' => fn ($q) => $q->withTrashed()])->orderBy('id')->paginate(15, ['*'], 'ventas_page')->withQueryString();
        $pagosConsulta = $metricas->cobros($periodo);
        if ($request->filled('recibo')) {
            $pagosConsulta->whereKey($request->integer('recibo'))->where(fn ($q) => $q->whereNull('vendedor_id')->orWhere('vendedor_reconstruido', true));
        } else {
            $pagosConsulta->where(fn ($q) => $q->whereNull('vendedor_id')->orWhere(fn ($q) => $q->where('vendedor_reconstruido', true)->whereNull('vendedor_revisado_at')));
        }
        $pagos = $pagosConsulta->with('vendedor', 'credito.cliente')->orderBy('id')->paginate(15, ['*'], 'pagos_page')->withQueryString();
        $creditos = Credito::whereIn('estado', ['vigente', 'vencido', 'incobrable'])->whereNull('vendedor_id')->with(['cliente' => fn ($q) => $q->withTrashed()])->orderBy('id')->paginate(15, ['*'], 'creditos_page')->withQueryString();
        $vendedores = Vendedor::orderBy('nombre')->get();

        return view('vendedores.pendientes', compact('periodo', 'ventas', 'pagos', 'creditos', 'vendedores'));
    }

    public function regularizarVenta(Request $request, Venta $venta, VendedorService $service)
    {
        Gate::authorize('assign', Vendedor::class);
        $datos = $request->validate(['vendedor_id' => 'required|integer|exists:vendedores,id', 'motivo' => 'required|string|min:10|max:2000']);
        $service->regularizarVenta($venta, Vendedor::findOrFail($datos['vendedor_id']), $datos['motivo'], $request->user());

        return back()->with('success', 'Origen de venta documentado. El responsable de cobro y los recibos anteriores se conservan.');
    }

    public function regularizarPago(Request $request, Pago $pago, VendedorService $service)
    {
        Gate::authorize('assign', Vendedor::class);
        $datos = $request->validate(['vendedor_id' => 'required|integer|exists:vendedores,id', 'motivo' => 'required|string|min:10|max:2000']);
        $service->regularizarPago($pago, Vendedor::findOrFail($datos['vendedor_id']), $datos['motivo'], $request->user());

        return back()->with('success', 'Atribución histórica documentada; los importes y el saldo del recibo no cambiaron.');
    }

    public function show(Request $request, Vendedor $vendedor, VendedorMetricasService $metricas)
    {
        Gate::authorize('view', $vendedor);
        $request->validate(['mes' => 'nullable|string|date_format:Y-m', 'cartera_filtro' => 'nullable|in:todas,mora,incobrables']);
        $periodo = $metricas->periodo($request->input('mes') ?: now()->format('Y-m'));
        $vendedor->load('zona');
        $resumen = $metricas->resumenes(collect([$vendedor]), $periodo)->get($vendedor->id);
        $consulta = $metricas->cartera($vendedor->id)->with(['cliente' => fn ($q) => $q->withTrashed(), 'vendedorOrigen'])
            ->withMax('pagos as ultimo_pago', 'fecha_pago');
        if ($request->cartera_filtro === 'mora') {
            $consulta->whereRaw('COALESCE(saldo_cuotas.vencido, 0) > 0');
        }
        if ($request->cartera_filtro === 'incobrables') {
            $consulta->where('creditos.estado', 'incobrable');
        }
        $creditos = $consulta->orderByRaw('COALESCE(saldo_cuotas.vencido, 0) DESC')->orderBy('creditos.id')->paginate(15, ['*'], 'cartera_page')->withQueryString();
        $ventas = $metricas->ventas($periodo)->where('vendedor_id', $vendedor->id)->with(['cliente' => fn ($q) => $q->withTrashed()])->orderByDesc('id')->paginate(15, ['*'], 'ventas_page')->withQueryString();
        $pagos = $metricas->cobros($periodo)->where('vendedor_id', $vendedor->id)->with(['credito' => fn ($q) => $q->withTrashed()->with(['cliente' => fn ($c) => $c->withTrashed()]), 'recibidoPor'])->orderByDesc('id')->paginate(15, ['*'], 'pagos_page')->withQueryString();
        $productos = $metricas->productos($vendedor->id, $periodo);
        $metas = VendedorMeta::where('vendedor_id', $vendedor->id)->orderByDesc('periodo')->paginate(12, ['*'], 'metas_page')->withQueryString();
        $gestiones = GestionCobro::where(fn ($q) => $q->where('vendedor_id', $vendedor->id)
            ->orWhereHas('credito', fn ($c) => $c->where('vendedor_id', $vendedor->id)))
            ->with('credito.cliente')->orderByDesc('created_at')->orderByDesc('id')->paginate(15, ['*'], 'gestiones_page')->withQueryString();
        $auditorias = Gate::allows('audit', Vendedor::class)
            ? $vendedor->auditorias()->orderByDesc('id')->paginate(15, ['*'], 'auditoria_page')->withQueryString() : null;
        $destinos = Gate::allows('assign', Vendedor::class) ? Vendedor::activos()->orderBy('nombre')->get() : collect();
        $carteras = Gate::allows('assign', Vendedor::class) ? Cartera::activas()->get() : collect();

        return view('vendedores.show', compact('vendedor', 'periodo', 'resumen', 'creditos', 'ventas', 'pagos', 'productos', 'metas', 'gestiones', 'auditorias', 'destinos', 'carteras'));
    }

    public function meta(Request $request, Vendedor $vendedor, VendedorService $service)
    {
        Gate::authorize('metas', Vendedor::class);
        $datos = $request->validate(['periodo' => 'required|string|date_format:Y-m', 'meta_ventas' => 'required|numeric', 'meta_cobros' => 'required|numeric', 'motivo' => 'required|string|min:10|max:2000']);
        $service->meta($vendedor, $datos['periodo'], $datos, $datos['motivo'], $request->user());

        return back()->with('success', 'Metas del mes registradas con su historial de cambios.');
    }

    public function reasignar(Request $request, Credito $credito, VendedorService $service)
    {
        Gate::authorize('assign', Vendedor::class);
        $datos = $request->validate(['vendedor_id' => 'required|integer|exists:vendedores,id', 'cartera_id' => 'nullable|integer|exists:carteras,id', 'motivo' => 'required|string|min:10|max:2000']);
        $service->reasignarCredito($credito, Vendedor::findOrFail($datos['vendedor_id']), $datos['cartera_id'] ?? null, $datos['motivo'], $request->user());

        return back()->with('success', 'Responsable del crédito actualizado. Se conservaron la venta original y la atribución de recibos anteriores.');
    }

    public function gestion(Request $request, Credito $credito, VendedorService $service)
    {
        Gate::authorize('gestionar', Vendedor::class);
        $service->registrarGestion($credito, $request->only(['medio', 'resultado', 'notas', 'fecha_compromiso', 'monto_compromiso']), $request->user());

        return back()->with('success', 'Gestión registrada. No modifica el saldo ni genera un pago.');
    }

    public function exportar(Request $request, VendedorMetricasService $metricas, ?Vendedor $vendedor = null)
    {
        Gate::authorize('export', Vendedor::class);
        $datos = $request->validate(['mes' => 'nullable|string|date_format:Y-m', 'tipo' => 'nullable|in:resumen,cartera,ventas,cobros,auditoria', 'formato' => 'nullable|in:csv,pdf']);
        $periodo = $metricas->periodo($datos['mes'] ?? now()->format('Y-m'));
        $tipo = $datos['tipo'] ?? 'resumen';
        abort_if(! $vendedor && $tipo !== 'resumen', 422);
        abort_if(($datos['formato'] ?? 'csv') === 'pdf' && $tipo !== 'resumen', 422);
        if ($tipo === 'auditoria') {
            Gate::authorize('audit', Vendedor::class);
        }
        $corte = now();
        $informe = DB::transaction(function () use ($vendedor, $metricas, $periodo, $tipo, $request, $corte) {
            if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $vendedores = $vendedor ? collect([$vendedor]) : Vendedor::orderBy('codigo')->get();
            $resumenes = $metricas->resumenes($vendedores, $periodo);
            $cartera = $vendedor ? $metricas->cartera($vendedor->id)->with(['cliente' => fn ($q) => $q->withTrashed(), 'vendedorOrigen'])->orderBy('creditos.id')->get() : collect();
            $filas = [];
            $cabecera = [];
            if ($tipo === 'resumen') {
                $cabecera = ['Código', 'Vendedor', 'Mes', 'Ventas sin IVA', 'Meta ventas', '% ventas', 'Cobros de crédito', 'Meta cobros', '% cobros', 'Capital cartera actual', 'Cuotas vencidas actuales', 'Capital incobrable actual', 'Clientes morosos actuales'];
                foreach ($resumenes as $r) {
                    $filas[] = [$r['vendedor']->codigo, $r['vendedor']->nombre, $periodo->format('Y-m'), $r['ventas'], $r['meta_ventas'], $r['avance_ventas'] ?? 'Sin objetivo', $r['cobros'], $r['meta_cobros'], $r['avance_cobros'] ?? 'Sin objetivo', $r['cartera_capital'], $r['vencido'], $r['incobrable'], $r['clientes_morosos']];
                }
            } elseif ($tipo === 'cartera') {
                $cabecera = ['Crédito', 'Cliente', 'Teléfono', 'Estado', 'Vendedor de origen', 'Responsable actual', 'Capital', 'Deuda total', 'Cuotas vencidas', 'Primera cuota vencida'];
                foreach ($cartera as $c) {
                    $filas[] = [$c->numero, $c->cliente?->nombre, $c->cliente?->celular ?? $c->cliente?->telefono, $c->estado, $c->vendedorOrigen?->nombre ?? 'No documentado', $vendedor->nombre, $c->saldo_actual, $c->deuda_total, $c->deuda_vencida, $c->primer_vencimiento];
                }
            } elseif ($tipo === 'ventas') {
                $cabecera = ['Documento', 'Fecha', 'Cliente', 'Condición', 'Estado documento', 'Total sin IVA', 'Total documento'];
                foreach ($metricas->ventas($periodo)->where('vendedor_id', $vendedor->id)->with(['cliente' => fn ($q) => $q->withTrashed()])->orderBy('id')->get() as $v) {
                    $filas[] = [$v->numero_control, $v->fecha_emision->toDateString(), $v->cliente?->nombre, $v->condicion_operacion, $v->estado_dte, round((float) $v->total_pagar - (float) $v->total_iva, 2), $v->total_pagar];
                }
            } elseif ($tipo === 'cobros') {
                $cabecera = ['Recibo', 'Fecha', 'Crédito', 'Cliente', 'Capital', 'Interés', 'Mora', 'Total', 'Saldo después', 'Recibido por', 'Atribución histórica reconstruida', 'Revisión de atribución'];
                foreach ($metricas->cobros($periodo)->where('vendedor_id', $vendedor->id)->with(['credito' => fn ($q) => $q->withTrashed()->with(['cliente' => fn ($c) => $c->withTrashed()]), 'recibidoPor'])->orderBy('id')->get() as $p) {
                    $filas[] = [$p->numero_recibo, $p->fecha_pago->toDateString(), $p->credito?->numero, $p->credito?->cliente?->nombre, $p->abono_capital, $p->pago_interes, $p->pago_mora, $p->monto_total, $p->saldo_despues, $p->recibidoPor?->name, $p->vendedor_reconstruido ? 'Sí' : 'No', $p->vendedor_revisado_at?->toDateTimeString()];
                }
            } else {
                $cabecera = ['ID', 'Fecha', 'Acción', 'Entidad', 'ID entidad', 'Antes', 'Después', 'Motivo', 'Responsable'];
                foreach ($vendedor->auditorias()->orderBy('id')->get() as $a) {
                    $filas[] = [$a->id, $a->created_at->toDateTimeString(), $a->accion, $a->entidad, $a->entidad_id, json_encode($a->antes, JSON_UNESCAPED_UNICODE), json_encode($a->despues, JSON_UNESCAPED_UNICODE), $a->motivo, $a->usuario_nombre];
                }
            }
            foreach ($vendedores as $v) {
                app(VendedorService::class)->auditar($v, 'exportacion', 'vendedores', $v->id, null,
                    ['mes' => $periodo->format('Y-m'), 'tipo' => $tipo, 'corte' => $corte->toDateTimeString(), 'resumen' => collect($resumenes->get($v->id))->except('vendedor')->all()],
                    'Exportación de informe de control de vendedores', $request->user());
            }

            return compact('cabecera', 'filas', 'resumenes', 'cartera');
        });
        if (($datos['formato'] ?? 'csv') === 'pdf') {
            abort_unless($tipo === 'resumen', 422);

            return Pdf::loadView('vendedores.reporte-pdf', $informe + compact('periodo', 'corte'))->setPaper('a4', 'landscape')->stream('vendedores-'.$periodo->format('Y-m').'.pdf');
        }

        return response()->streamDownload(function () use ($informe, $periodo, $corte) {
            $archivo = fopen('php://output', 'w');
            fwrite($archivo, "\xEF\xBB\xBF");
            fputcsv($archivo, ['Mes de ventas/cobros', $periodo->format('Y-m'), 'Cartera actual al', $corte->toDateTimeString(), config('app.timezone')], ';', '"', '');
            fputcsv($archivo, $informe['cabecera'], ';', '"', '');
            foreach ($informe['filas'] as $fila) {
                $fila = array_map(fn ($valor) => is_string($valor) && preg_match('/^(?:[\s\p{Z}]*[=+@\-]|[\t\r\n])/u', $valor) ? "'".$valor : $valor, $fila);
                fputcsv($archivo, $fila, ';', '"', '');
            }
            fclose($archivo);
        }, 'vendedores-'.$tipo.'-'.$periodo->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
