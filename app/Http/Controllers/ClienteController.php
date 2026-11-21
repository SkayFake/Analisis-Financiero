<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClienteRequest;
use App\Models\Cartera;
use App\Models\Cliente;
use App\Models\ClienteJuridico;
use App\Models\Vendedor;
use App\Models\Zona;
use App\Services\VendedorService;
use App\Support\Acceso;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        Acceso::exigir('clientes.view');
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
        Acceso::exigir('clientes.create');
        $zonas = Zona::activas()->get();
        $vendedores = Vendedor::activos()->get();
        $carteras = Cartera::activas()->get();

        return view('clientes.create', compact('zonas', 'vendedores', 'carteras'));
    }

    public function store(ClienteRequest $request)
    {
        $validated = $request->validated();
        $cliente = DB::transaction(function () use ($validated) {
            $datos = Arr::except($validated, [...ClienteRequest::JURIDICOS, 'motivo_asignacion']);
            $datos['codigo'] = Cliente::generarCodigo($datos['tipo']);
            $cliente = Cliente::create($datos);
            if ($cliente->vendedor) {
                app(VendedorService::class)->auditar($cliente->vendedor, 'asignacion_cliente', 'clientes', $cliente->id, null,
                    ['vendedor_id' => $cliente->vendedor_id], 'Asignación inicial al registrar el cliente', auth()->user());
            }
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
        Acceso::exigir('clientes.view');
        $cliente->load([
            'datosJuridicos', 'zona', 'vendedor', 'cartera',
            'creditos' => fn ($q) => $q->orderByDesc('created_at')->limit(10),
            'creditos.condicionCredito',
            'historialClasificaciones' => fn ($q) => $q->orderByDesc('created_at')->limit(10),
        ]);

        return view('clientes.show', compact('cliente'));
    }

    public function auxiliar(Cliente $cliente)
    {
        Acceso::exigir('clientes.view');
        $auxiliar = $cliente->obtenerAuxiliar();

        return view('clientes.auxiliar', compact('cliente', 'auxiliar'));
    }

    public function edit(Cliente $cliente)
    {
        Acceso::exigir('clientes.edit');
        $cliente->load('datosJuridicos');
        $zonas = Zona::activas()->get();
        $vendedores = Vendedor::activos()->get();
        $carteras = Cartera::activas()->get();

        return view('clientes.edit', compact('cliente', 'zonas', 'vendedores', 'carteras'));
    }

    public function update(ClienteRequest $request, Cliente $cliente)
    {
        $validated = $request->validated();
        DB::transaction(function () use ($validated, $cliente) {
            $cliente = Cliente::whereKey($cliente->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('vendedor_id', $validated) && (int) $validated['vendedor_id'] !== (int) $cliente->vendedor_id) {
                Gate::authorize('assign', Vendedor::class);
                app(VendedorService::class)->asignarCliente($cliente, $validated['vendedor_id'] ? (int) $validated['vendedor_id'] : null,
                    $validated['motivo_asignacion'] ?? '', auth()->user(), isset($validated['cartera_id']) ? (int) $validated['cartera_id'] : null);
            }
            $cliente->update(Arr::except($validated, [...ClienteRequest::JURIDICOS, 'vendedor_id', 'motivo_asignacion']));
            if ($cliente->tipo === 'juridica') {
                $cliente->datosJuridicos()->updateOrCreate([], Arr::only($validated, ClienteRequest::JURIDICOS));
            }
        });

        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Cliente actualizado exitosamente.');
    }

    public function destroy(Cliente $cliente)
    {
        Acceso::exigir('clientes.delete');
        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente eliminado exitosamente.');
    }
}
