<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\CondicionCredito;
use App\Services\CreditoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class CreditoController extends Controller
{
    public function __construct(
        private CreditoService $creditoService
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Credito::class);

        $query = Credito::with(['cliente', 'venta', 'condicionCredito', 'vendedor']);

        if ($request->filled('estado')) {
            $query->porEstado($request->estado);
        }

        if ($request->filled('buscar')) {
            $query->where(function ($q) use ($request) {
                $q->where('numero', 'ilike', "%{$request->buscar}%")
                  ->orWhereHas('cliente', fn($cq) => $cq->buscar($request->buscar))
                  ->orWhereHas('venta', fn($vq) => $vq->where('numero_control', 'ilike', "%{$request->buscar}%"));
            });
        }

        $creditos = $query->orderByDesc('created_at')->paginate(15);

        return view('creditos.index', compact('creditos'));
    }

    /**
     * Formulario para crear crédito manualmente vinculado a una factura existente.
     * Útil cuando el crédito no se generó automáticamente en el POS o para regularizaciones.
     */
    public function create(Request $request)
    {
        Gate::authorize('create', Credito::class);

        $clientes    = Cliente::activos()->orderBy('nombre')->get();
        $condiciones = CondicionCredito::activas()->get();
        $vendedores  = \App\Models\Vendedor::activos()->get();
        $carteras    = \App\Models\Cartera::activas()->get();
        $politicas   = \App\Models\PoliticaCobro::activas()->get();

        // Facturas al crédito sin crédito asignado aún
        $ventasSinCredito = Venta::sinCredito()
            ->with('cliente')
            ->orderByDesc('fecha_emision')
            ->take(50)
            ->get();

        // Si se pre-seleccionó una venta desde el listado
        $ventaPreseleccionada = $request->filled('venta_id')
            ? Venta::with(['cliente', 'detalles.producto'])->find($request->venta_id)
            : null;

        return view('creditos.create', compact(
            'clientes',
            'condiciones',
            'vendedores',
            'carteras',
            'politicas',
            'ventasSinCredito',
            'ventaPreseleccionada'
        ));
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Credito::class);

        $validated = $request->validate([
            'venta_id'              => 'required|exists:ventas,id',
            'condicion_credito_id'  => 'required|exists:condiciones_credito,id',
            'numero_cuotas'         => 'required|integer|min:1',
            'fecha_primera_cuota'   => 'required|date|after_or_equal:today',
            'vendedor_id'           => 'nullable|exists:vendedores,id',
            'cartera_id'            => 'nullable|exists:carteras,id',
            'politica_cobro_id'     => 'nullable|exists:politicas_cobro,id',
            'dui_verificado'        => 'boolean',
            'referencia_verificada' => 'boolean',
            'fiador_nombre'         => 'nullable|string|max:150',
            'fiador_dui'            => 'nullable|string|max:20',
            'fiador_telefono'       => 'nullable|string|max:20',
            'fiador_direccion'      => 'nullable|string|max:250',
            'observaciones'         => 'nullable|string',
        ], [
            'venta_id.required'              => 'Debe seleccionar la factura de origen del crédito.',
            'condicion_credito_id.required'  => 'Debe seleccionar las condiciones de crédito.',
            'numero_cuotas.required'         => 'Indique el número de cuotas.',
            'fecha_primera_cuota.required'   => 'Indique la fecha de la primera cuota.',
            'fecha_primera_cuota.after_or_equal' => 'La primera cuota debe ser hoy o una fecha futura.',
        ]);

        $venta = Venta::with(['cliente', 'detalles'])->findOrFail($validated['venta_id']);

        if ($venta->condicion_operacion !== '2') {
            return back()->withErrors(['venta_id' => 'Solo se puede crear crédito sobre facturas emitidas al crédito.']);
        }

        if ($venta->tiene_credito) {
            return back()->withErrors(['venta_id' => "La factura {$venta->numero_control} ya tiene un crédito asociado."]);
        }

        $condicion = CondicionCredito::findOrFail($validated['condicion_credito_id']);
        if (!$condicion->activa) {
            return back()->withErrors(['condicion_credito_id' => "La condición '{$condicion->nombre}' está inactiva y no admite nuevos créditos."]);
        }

        if ((int) $validated['numero_cuotas'] > $condicion->plazo_maximo_cuotas) {
            return back()->withErrors(['numero_cuotas' => "El número de cuotas supera el límite permitido ({$condicion->plazo_maximo_cuotas}) para la condición seleccionada."]);
        }

        $condicionesData = $validated;
        if (!empty($validated['fiador_nombre'])) {
            $condicionesData['fiador'] = [
                'nombre'    => $validated['fiador_nombre'],
                'dui'       => $validated['fiador_dui'] ?? null,
                'telefono'  => $validated['fiador_telefono'] ?? null,
                'direccion' => $validated['fiador_direccion'] ?? null,
            ];
        }

        try {
            $credito = $this->creditoService->crearCreditoDesdeVenta($venta, $condicionesData);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $mensaje = $credito->estado === 'vigente'
            ? "Crédito {$credito->numero} creado y aprobado automáticamente."
            : "Crédito {$credito->numero} creado. Requiere aprobación manual.";

        return redirect()->route('creditos.show', $credito)->with('success', $mensaje);
    }

    public function show(Credito $credito)
    {
        Gate::authorize('view', $credito);

        $credito->load([
            'cliente',
            'venta.detalles.producto',
            'condicionCredito',
            'vendedor',
            'cartera',
            'cuotas.aplicacionesPago',
            'pagos.recibidoPor',
            'pagos.aplicaciones.cuota',
            'fiadores',
            'embargos',
            'refinanciamientoOriginal',
            'refinanciamientoNuevo',
        ]);

        $requisitosFaltantes = $credito->estado === 'pendiente_aprobacion'
            ? $this->creditoService->obtenerRequisitosFaltantes($credito)
            : [];

        return view('creditos.show', compact('credito', 'requisitosFaltantes'));
    }

    /**
     * Registra un pago sobre un crédito vigente o vencido.
     */
    public function storePago(Request $request, Credito $credito)
    {
        Gate::authorize('processPayment', $credito);

        $validated = $request->validate([
            'monto'         => 'required|numeric|min:0.01',
            'forma_pago'    => 'required|in:efectivo,transferencia,cheque,tarjeta',
            'fecha_pago'    => 'nullable|date',
            'referencia'    => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
        ]);

        $validated['recibido_por'] = auth()->id();

        try {
            $pago = $this->creditoService->procesarPago($credito, $validated);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['pago_error' => $e->getMessage()]);
        }

        return redirect()->route('creditos.show', $credito)
            ->with('success', "Pago registrado correctamente. Recibo: {$pago->numero_recibo}");
    }

    /**
     * Aprueba manualmente un crédito pendiente.
     */
    public function aprobar(Request $request, Credito $credito)
    {
        Gate::authorize('approve', $credito);

        $request->validate([
            'motivo' => 'required|string|min:10',
        ], [
            'motivo.required' => 'Debe indicar el motivo de aprobación.',
            'motivo.min'      => 'El motivo debe tener al menos 10 caracteres.',
        ]);

        try {
            $this->creditoService->aprobarCredito($credito, $request->motivo);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['aprobacion_error' => $e->getMessage()]);
        }

        return redirect()->route('creditos.show', $credito)
            ->with('success', "Crédito {$credito->numero} aprobado y plan de cuotas generado.");
    }

    /**
     * Rechaza un crédito pendiente de aprobación.
     */
    public function rechazar(Request $request, Credito $credito)
    {
        Gate::authorize('reject', $credito);

        $request->validate([
            'motivo' => 'required|string|min:10',
        ], [
            'motivo.required' => 'Debe indicar el motivo de rechazo.',
            'motivo.min'      => 'El motivo debe tener al menos 10 caracteres.',
        ]);

        try {
            $this->creditoService->rechazarCredito($credito, $request->motivo);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['rechazo_error' => $e->getMessage()]);
        }

        return redirect()->route('creditos.index')
            ->with('info', "Crédito {$credito->numero} rechazado.");
    }
}
