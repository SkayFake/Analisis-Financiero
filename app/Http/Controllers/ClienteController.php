<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ClienteJuridico;
use App\Services\RatioFinancieroService;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo' => 'required|in:natural,juridica',
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string',
            'departamento' => 'nullable|string|max:50',
            'municipio' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'dui' => 'nullable|string|max:12|unique:clientes,dui',
            'nit' => 'nullable|string|max:20',
            'nrc' => 'nullable|string|max:20',
            'estado_civil' => 'nullable|in:soltero,casado,divorciado,viudo,union_libre',
            'lugar_trabajo' => 'nullable|string',
            'ingresos' => 'nullable|numeric|min:0',
            'egresos' => 'nullable|numeric|min:0',
            'zona_id' => 'nullable|exists:zonas,id',
            'cartera_id' => 'nullable|exists:carteras,id',
            'vendedor_id' => 'nullable|exists:vendedores,id',
        ]);

        $validated['codigo'] = Cliente::generarCodigo($validated['tipo']);
        $cliente = Cliente::create($validated);

        // Si es persona jurídica, crear datos adicionales
        if ($validated['tipo'] === 'juridica') {
            ClienteJuridico::create([
                'cliente_id' => $cliente->id,
                'nombre_comercial' => $request->input('nombre_comercial'),
                'giro' => $request->input('giro'),
                'representante_legal' => $request->input('representante_legal'),
                'dui_representante' => $request->input('dui_representante'),
                'balance_general' => $request->input('balance_general', ClienteJuridico::estructuraBalanceGeneral()),
                'estado_resultados' => $request->input('estado_resultados', ClienteJuridico::estructuraEstadoResultados()),
                'fecha_balance' => $request->input('fecha_balance'),
                'numero_empleados' => $request->input('numero_empleados'),
                'fecha_constitucion' => $request->input('fecha_constitucion'),
            ]);
        }

        return redirect()->route('clientes.show', $cliente)
            ->with('success', "Cliente {$cliente->nombre} creado exitosamente.");
    }

    public function show(Cliente $cliente)
    {
        $cliente->load([
            'datosJuridicos', 'zona', 'vendedor', 'cartera',
            'creditos' => fn($q) => $q->orderByDesc('created_at')->limit(10),
            'creditos.productoCredito',
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

    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string',
            'departamento' => 'nullable|string|max:50',
            'telefono' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'dui' => 'nullable|string|max:12|unique:clientes,dui,' . $cliente->id,
            'nit' => 'nullable|string|max:20',
            'estado_civil' => 'nullable|in:soltero,casado,divorciado,viudo,union_libre',
            'lugar_trabajo' => 'nullable|string',
            'ingresos' => 'nullable|numeric|min:0',
            'egresos' => 'nullable|numeric|min:0',
            'zona_id' => 'nullable|exists:zonas,id',
            'cartera_id' => 'nullable|exists:carteras,id',
            'vendedor_id' => 'nullable|exists:vendedores,id',
        ]);

        $cliente->update($validated);

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
