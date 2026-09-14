<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Bodega;
use App\Models\Venta;
use App\Services\FacturacionService;
use Illuminate\Support\Facades\Auth;
use Exception;

class PuntoVenta extends Component
{
    // Cart Data
    public $detalles = [];
    public $subtotal = 0;
    public $iva = 0;
    public $total = 0;
    
    // Header Data
    public $cliente_id = '';
    public $bodega_id = 1;
    public $tipo_documento = '01'; // 01 FCF, 03 CCF, 11 Ticket, 05 NC, 06 ND
    public $condicion_operacion = '1'; // 1 Contado, 2 Crédito

    // Plan de crédito (solo cuando condicion_operacion = '2')
    public $condicion_credito_id = '';
    public $numero_cuotas = 1;
    public $frecuencia_pago = 'mensual'; // se toma de la condicion_credito, solo referencial
    public $fecha_primera_cuota = '';
    public $dui_verificado = false;
    public $referencia_verificada = false;

    // Search
    public $searchProducto = '';

    public function mount()
    {
        $primeraBodega = Bodega::where('activa', true)->first();
        if ($primeraBodega) {
            $this->bodega_id = $primeraBodega->id;
        }
    }
    
    public function getProductosProperty()
    {
        if (strlen($this->searchProducto) < 2) return collect();

        return Producto::where('activo', true)
            ->where(function ($q) {
                $q->where('nombre', 'ilike', '%' . $this->searchProducto . '%')
                  ->orWhere('codigo', 'ilike', '%' . $this->searchProducto . '%');
            })
            ->with(['existencias' => function ($q) {
                if ($this->bodega_id) {
                    $q->where('bodega_id', $this->bodega_id);
                }
            }])
            ->take(6)
            ->get();
    }

    public function getCondicionesCreditorProperty()
    {
        return \App\Models\CondicionCredito::activas()->get();
    }

    public function getClientesProperty()
    {
        return Cliente::where('activo', true)->orderBy('nombre')->get();
    }

    public function getBodegasProperty()
    {
        return Bodega::where('activa', true)->orderBy('nombre')->get();
    }

    public function agregarProducto($productoId)
    {
        $producto = Producto::find($productoId);
        if (!$producto) return;

        // Stock disponible en la bodega actual
        $stockBodega = $producto->existencias()
            ->where('bodega_id', $this->bodega_id)
            ->sum('cantidad');

        // Si no hay en esa bodega pero sí en total, tomamos el total
        if ($stockBodega <= 0) {
            $stockBodega = $producto->stock_total;
        }

        // Verificar si ya está en el carrito
        $index = collect($this->detalles)->search(fn($item) => $item['producto_id'] == $producto->id);

        if ($index !== false) {
            $this->detalles[$index]['cantidad']++;
        } else {
            $this->detalles[] = [
                'producto_id' => $producto->id,
                'codigo' => $producto->codigo,
                'nombre' => $producto->nombre,
                'precio_unitario' => (float) $producto->precio_venta,
                'cantidad' => 1,
                'stock' => (float) $stockBodega,
            ];
        }

        $this->searchProducto = '';
        $this->calcularTotales();
    }

    public function cambiarCantidad($index, $delta)
    {
        if (!isset($this->detalles[$index])) return;

        $nueva = $this->detalles[$index]['cantidad'] + $delta;
        if ($nueva <= 0) {
            $this->removerProducto($index);
            return;
        }

        $this->detalles[$index]['cantidad'] = round($nueva, 2);
        $this->calcularTotales();
    }

    public function removerProducto($index)
    {
        unset($this->detalles[$index]);
        $this->detalles = array_values($this->detalles); // Re-indexar
        $this->calcularTotales();
    }

    public function limpiarCarrito()
    {
        $this->detalles = [];
        $this->calcularTotales();
    }

    public function updatedDetalles()
    {
        $this->calcularTotales();
    }

    public function updatedTipoDocumento()
    {
        $this->calcularTotales();
    }

    public function calcularTotales()
    {
        $this->subtotal = 0;
        foreach ($this->detalles as $det) {
            $cant = floatval($det['cantidad'] ?? 0);
            $pu = floatval($det['precio_unitario'] ?? 0);
            $this->subtotal += ($cant * $pu);
        }
        
        // En El Salvador:
        // Si es CCF (03) el IVA (13%) se desglosa y se adiciona al total de la operación.
        // Si es FCF (01) o Ticket (11), el IVA ya va incluido en el precio al consumidor final.
        if ($this->tipo_documento === '03') {
            $this->iva = round($this->subtotal * 0.13, 2);
            $this->total = $this->subtotal + $this->iva;
        } else {
            $this->iva = 0;
            $this->total = $this->subtotal;
        }
    }

    public function facturar()
    {
        $this->validate([
            'tipo_documento' => 'required|in:01,03,11,05,06',
            'condicion_operacion' => 'required|in:1,2',
            'detalles' => 'required|array|min:1',
            'cliente_id' => 'required_if:tipo_documento,03|required_if:condicion_operacion,2',
        ], [
            'detalles.required' => 'Debe agregar al menos un producto a la factura.',
            'detalles.min' => 'Debe agregar al menos un producto a la factura.',
            'cliente_id.required_if' => 'El cliente es obligatorio para Créditos Fiscales (CCF) y Ventas al Crédito.',
        ]);

        try {
            $service = app(FacturacionService::class);
            $usuarioId = Auth::id() ?? 1;
            
            $datosVenta = [
                'cliente_id'          => $this->cliente_id ?: null,
                'tipo_documento'      => $this->tipo_documento,
                'condicion_operacion' => $this->condicion_operacion,
                'bodega_id'           => $this->bodega_id ?: 1,
                // Plan de crédito (se pasa al FacturacionService para crear el crédito automáticamente)
                'condiciones_credito' => $this->condicion_operacion === '2' ? [
                    'condicion_credito_id'  => $this->condicion_credito_id ?: null,
                    'numero_cuotas'         => (int) $this->numero_cuotas,
                    'fecha_primera_cuota'   => $this->fecha_primera_cuota ?: now()->addMonth()->toDateString(),
                    'dui_verificado'        => $this->dui_verificado,
                    'referencia_verificada' => $this->referencia_verificada,
                ] : [],
            ];

            $venta = $service->procesarVenta($datosVenta, $this->detalles, $usuarioId);

            $this->detalles = [];
            $this->calcularTotales();

            session()->flash('success', "¡Documento DTE {$venta->numero_control} emitido con éxito! (Estado: " . strtoupper($venta->estado_dte) . ")");
            
            // Redirigir al PDF de la factura
            return redirect()->route('facturacion.pdf', $venta->id);
            
        } catch (Exception $e) {
            session()->flash('error', 'Error al procesar la venta: ' . $e->getMessage());
        }
    }

    public function render()
    {
        // Métricas rápidas del día
        $ventasHoy = Venta::whereDate('fecha_emision', now()->toDateString())->get();
        $totalHoy = $ventasHoy->sum('total_pagar');
        $docsHoy = $ventasHoy->count();
        $docsContingencia = $ventasHoy->where('estado_dte', 'contingencia')->count();
        $docsTransmitidos = $ventasHoy->where('estado_dte', 'procesado')->count();

        // Cliente seleccionado
        $clienteSeleccionado = $this->cliente_id ? Cliente::find($this->cliente_id) : null;

        return view('livewire.punto-venta', [
            'totalHoy' => $totalHoy,
            'docsHoy' => $docsHoy,
            'docsContingencia' => $docsContingencia,
            'docsTransmitidos' => $docsTransmitidos,
            'clienteSeleccionado' => $clienteSeleccionado,
        ])->layout('layouts.app', ['title' => 'Punto de Venta DTE']);
    }
}
