<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Models\CuentaIncobrable;
use App\Services\IncobrableService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class IncobrableController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewIncobrables', Credito::class);
        $datos = $request->validate([
            'buscar' => 'nullable|string|max:150',
            'estado' => 'nullable|string|in:abiertas,reactivadas,todas',
        ]);
        $query = CuentaIncobrable::with(['credito.cliente' => fn ($q) => $q->withTrashed(), 'declaradoPor', 'reactivadoPor']);
        $estado = $datos['estado'] ?? 'abiertas';
        if ($estado === 'abiertas') {
            $query->where('abierta', true);
        } elseif ($estado === 'reactivadas') {
            $query->whereNull('abierta');
        }
        if (! empty($datos['buscar'])) {
            $buscar = $datos['buscar'];
            $query->whereHas('credito', fn ($q) => $q->where(fn ($q) => $q
                ->where('numero', 'ilike', "%{$buscar}%")
                ->orWhereHas('cliente', fn ($q) => $q->withTrashed()->buscar($buscar))));
        }
        $resumen = [
            'cuentas' => CuentaIncobrable::where('abierta', true)->count(),
            'capital' => CuentaIncobrable::where('abierta', true)->sum('saldo_capital'),
            'total' => CuentaIncobrable::where('abierta', true)->sum('saldo_total'),
        ];
        $cuentas = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('incobrables.index', compact('cuentas', 'resumen'));
    }

    public function store(Request $request, Credito $credito, IncobrableService $service)
    {
        Gate::authorize('declareUncollectible', $credito);
        $motivo = $this->motivo($request);
        try {
            $service->declararIncobrable($credito, $motivo, auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['incobrable_error' => $e->getMessage()]);
        }

        return back()->with('success', "Crédito {$credito->numero} trasladado a la cartera de incobrables.");
    }

    public function reactivar(Request $request, Credito $credito, IncobrableService $service)
    {
        Gate::authorize('reactivate', $credito);
        $motivo = $this->motivo($request);
        try {
            $service->reactivarCredito($credito, $motivo, auth()->id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['incobrable_error' => $e->getMessage()]);
        }

        return back()->with('success', "Crédito {$credito->numero} reactivado para continuar sus cobros. Se conservó el historial.");
    }

    private function motivo(Request $request): string
    {
        $request->merge(['motivo' => is_string($request->motivo) ? trim($request->motivo) : $request->motivo]);

        return $request->validate(['motivo' => 'required|string|min:10|max:2000'], [
            'motivo.required' => 'Debe indicar el motivo de esta operación.',
            'motivo.min' => 'Explique el motivo con al menos 10 caracteres.',
        ])['motivo'];
    }
}
