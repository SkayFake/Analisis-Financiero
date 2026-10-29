<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Http\Requests\ClienteRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Models\ClienteJuridico;
use App\Services\RatioFinancieroService;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'buscar' => 'nullable|string|max:150',
            'tipo' => 'nullable|string|in:natural,juridica',
            'clasificacion' => 'nullable|string|in:A,B,C,D',
            'zona_id' => 'nullable|integer|exists:zonas,id',
        ]);
        $query = Cliente::with(['zona', 'vendedor', 'cartera'])
            ->withCount(['creditos as creditos_activos_count' => function ($q) {
                $q->whereIn('estado', ['vigente', 'vencido']);
            }]);

        // Filtros
        if ($request->filled('buscar')) {
            $query->buscar($request->buscar);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('clasificacion')) {
            $query->clasificacion($request->clasificacion);
        }
        if ($request->filled('zona_id')) {
            $query->porZona($request->zona_id);
        }

        $clientes = $query->orderBy('nombre')->paginate(15);

        return view('clientes.index', compact('clientes'));
    }

    public function create()
    {
        $zonas = \App\Models\Zona::activas()->get();
        $vendedores = \App\Models\Vendedor::activos()->get();
        $carteras = \App\Models\Cartera::activas()->get();

        return view('clientes.create', compact('zonas', 'vendedores', 'carteras'));
    }

    public function store(ClienteRequest $request)
    {
        $validated = $request->validated();
        $cliente = DB::transaction(function () use ($validated) {
            $datos = Arr::except($validated, ClienteRequest::JURIDICOS);
            $datos['codigo'] = Cliente::generarCodigo($datos['tipo']);
            $cliente = Cliente::create($datos);
            if ($cliente->tipo === 'juridica') {
                $cliente->datosJuridicos()->create(array_merge([
                    'balance_general' => ClienteJuridico::estructuraBalanceGeneral(),
                    'estado_resultados' => ClienteJuridico::estructuraEstadoResultados(),
                ], Arr::only($validated, ClienteRequest::JURIDICOS)));
            }
            return $cliente;
        });
        return redirect()->route('clientes.show', $cliente)
            ->with('success', "Cliente {$cliente->nombre} creado exitosamente.");
    }

    public function show(Cliente $cliente)
    {
        $cliente->load([
            'datosJuridicos', 'zona', 'vendedor', 'cartera',
            'creditos' => fn($q) => $q->orderByDesc('created_at')->limit(10),
            'creditos.condicionCredito',
            'historialClasificaciones' => fn($q) => $q->orderByDesc('created_at')->limit(10),
        ]);

        return view('clientes.show', compact('cliente'));
    }

    public function edit(Cliente $cliente)
    {
        $cliente->load('datosJuridicos');
        $zonas = \App\Models\Zona::activas()->get();
        $vendedores = \App\Models\Vendedor::activos()->get();
        $carteras = \App\Models\Cartera::activas()->get();

        return view('clientes.edit', compact('cliente', 'zonas', 'vendedores', 'carteras'));
    }

    public function update(ClienteRequest $request, Cliente $cliente)
    {
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $cliente) {
            $cliente->update(Arr::except($validated, ClienteRequest::JURIDICOS));
            if ($cliente->tipo === 'juridica') {
                $cliente->datosJuridicos()->updateOrCreate([], Arr::only($validated, ClienteRequest::JURIDICOS));
            }
        });
        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente eliminado exitosamente.');
    }
}
