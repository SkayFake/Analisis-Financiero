<?php

namespace App\Http\Controllers;

use App\Models\Credito;
use App\Models\Cliente;
use App\Services\CreditoService;
use Illuminate\Http\Request;

class CreditoController extends Controller
{
    public function __construct(
        private CreditoService $creditoService
    ) {}

    public function index(Request $request)
    {
        $query = Credito::with(['cliente', 'productoCredito', 'vendedor']);

        if ($request->filled('estado')) {
            $query->porEstado($request->estado);
        }

        if ($request->filled('buscar')) {
            $query->where(function ($q) use ($request) {
                $q->where('numero', 'ilike', "%{$request->buscar}%")
                  ->orWhereHas('cliente', fn($cq) => $cq->buscar($request->buscar));
            });
        }

        $creditos = $query->orderByDesc('created_at')->paginate(15);

        return view('creditos.index', compact('creditos'));
    }

    public function create()
    {
        $clientes = Cliente::activos()->orderBy('nombre')->get();
        $productos = \App\Models\ProductoCredito::activos()->get();
        $vendedores = \App\Models\Vendedor::activos()->get();
        $carteras = \App\Models\Cartera::activas()->get();
        $politicas = \App\Models\PoliticaCobro::activas()->get();

        return view('creditos.create', compact('clientes', 'productos', 'vendedores', 'carteras', 'politicas'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'producto_credito_id' => 'required|exists:productos_credito,id',
            'monto' => 'required|numeric|min:100',
            'numero_cuotas' => 'required|integer|min:1|max:120',
            'plazo_dias' => 'nullable|integer',
            'tasa_interes' => 'nullable|numeric|min:0',
            'comision' => 'nullable|numeric|min:0',
            'vendedor_id' => 'nullable|exists:vendedores,id',
            'cartera_id' => 'nullable|exists:carteras,id',
            'politica_cobro_id' => 'nullable|exists:politicas_cobro,id',
            'fecha_desembolso' => 'nullable|date',
            'tipo_venta' => 'required|in:contado,credito',
            'observaciones' => 'nullable|string',
        ]);

        if (empty($validated['plazo_dias']) || (int)$validated['plazo_dias'] <= 0) {
            $validated['plazo_dias'] = (int)$validated['numero_cuotas'] * 30;
        }

        $credito = $this->creditoService->crearCredito($validated);

        return redirect()->route('creditos.show', $credito)
            ->with('success', "Crédito {$credito->numero} creado exitosamente.");
    }

    public function show(Credito $credito)
    {
        $credito->load([
            'cliente', 'productoCredito', 'vendedor', 'cartera',
            'cuotas', 'pagos.recibidoPor', 'fiadores',
            'embargos', 'refinanciamientoOriginal', 'refinanciamientoNuevo',
        ]);

        return view('creditos.show', compact('credito'));
    }

    public function registrarPago(Request $request, Credito $credito)
    {
        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'forma_pago' => 'required|in:efectivo,transferencia,cheque,tarjeta',
            'fecha_pago' => 'nullable|date',
            'referencia' => 'nullable|string',
            'observaciones' => 'nullable|string',
        ]);

        $validated['recibido_por'] = auth()->id();

        $pago = $this->creditoService->procesarPago($credito, $validated);

        return redirect()->route('creditos.show', $credito)
            ->with('success', "Pago registrado. Recibo: {$pago->numero_recibo}");
    }
}
