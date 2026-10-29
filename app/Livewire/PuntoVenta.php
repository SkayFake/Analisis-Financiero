<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Bodega;
use App\Models\Venta;
use App\Models\CondicionCredito;
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
    public $frecuencia_pago = 'mensual';
    public $fecha_primera_cuota = '';
    public $dui_verificado = false;
    public $referencia_verificada = false;

    // Fiador (opcional o requerido si la condición lo pide)
    public $fiador_nombre = '';
    public $fiador_dui = '';
    public $fiador_telefono = '';
    public $fiador_direccion = '';

    // Search
    public $searchProducto = '';

    public function mount()
    {
        $primeraBodega = Bodega::where('activa', true)->first();
        if ($primeraBodega) {
            $this->bodega_id = $primeraBodega->id;
        }

        $primeraCondicion = CondicionCredito::activas()->first();
        if ($primeraCondicion) {
            $this->condicion_credito_id = $primeraCondicion->id;
            $this->frecuencia_pago = $primeraCondicion->frecuencia_pago;
        }

        $this->fecha_primera_cuota = now()->addMonth()->toDateString();
    }

    public function updatedCondicionCreditoId($value)
    {
        if ($value) {
            $condicion = CondicionCredito::find($value);
            if ($condicion) {
                $this->frecuencia_pago = $condicion->frecuencia_pago;
                if ($this->numero_cuotas > $condicion->plazo_maximo_cuotas) {
                    $this->numero_cuotas = $condicion->plazo_maximo_cuotas;
                }
            }
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

    public function getCondicionesCreditoProperty()
    {
        return CondicionCredito::activas()->orderBy('nombre')->get();
    }

    public function getCondicionSeleccionadaProperty()
    {
        if (!$this->condicion_credito_id) return null;
        return CondicionCredito::find($this->condicion_credito_id);
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

        if ($stockBodega <= 0) {
            $stockBodega = $producto->stock_total;
        }

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
        $this->detalles = array_values($this->detalles);
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
        $rules = [
            'tipo_documento'      => 'required|in:01,03,11,05,06',
            'condicion_operacion' => 'required|in:1,2',
            'detalles'            => 'required|array|min:1',
            'cliente_id'          => 'required_if:tipo_documento,03|required_if:condicion_operacion,2',
        ];

        $messages = [
            'detalles.required'      => 'Debe agregar al menos un producto a la factura.',
            'detalles.min'           => 'Debe agregar al menos un producto a la factura.',
            'cliente_id.required_if' => 'El cliente es obligatorio para Créditos Fiscales (CCF) y Ventas al Crédito.',
        ];

        if ($this->condicion_operacion === '2') {
            // Comprobar autorización para crear créditos si es venta al crédito
            $user = Auth::user();
            if ($user && !$user->can('creditos.create') && !$user->hasRole('super-admin')) {
                abort(403, 'No tiene permisos para autorizar o emitir ventas al crédito comercial.');
            }

            $rules['condicion_credito_id'] = 'required|exists:condiciones_credito,id';
            $rules['numero_cuotas']        = 'required|integer|min:1';
            $rules['fecha_primera_cuota']  = 'required|date|after_or_equal:today';

            $messages['condicion_credito_id.required'] = 'Seleccione una condición de crédito para la venta al crédito.';
            $messages['numero_cuotas.required']        = 'Indique el número de cuotas para el crédito.';
            $messages['fecha_primera_cuota.required']  = 'Indique la fecha de la primera cuota.';
            $messages['fecha_primera_cuota.after_or_equal'] = 'La fecha de la primera cuota debe ser hoy o una fecha futura.';
        }

        $this->validate($rules, $messages);

        if ($this->condicion_operacion === '2') {
            $condicion = CondicionCredito::find($this->condicion_credito_id);
            if (!$condicion || !$condicion->activa) {
                $this->addError('condicion_credito_id', 'La condición seleccionada está inactiva y no admite nuevos créditos.');
                return;
            }

            if ((int) $this->numero_cuotas > $condicion->plazo_maximo_cuotas) {
                $this->addError('numero_cuotas', "El número de cuotas supera el máximo permitido ({$condicion->plazo_maximo_cuotas}) para la condición elegida.");
                return;
            }

            $cliente = Cliente::find($this->cliente_id);
            if (!$cliente || !$cliente->activo) {
                $this->addError('cliente_id', 'El cliente seleccionado no se encuentra activo o habilitado para crédito.');
                return;
            }
        }

        try {
            $service = app(FacturacionService::class);
            $usuarioId = Auth::id() ?? 1;

            $condicionesCredito = [];
            if ($this->condicion_operacion === '2') {
                $condicionesCredito = [
                    'condicion_credito_id'  => $this->condicion_credito_id,
                    'numero_cuotas'         => (int) $this->numero_cuotas,
                    'fecha_primera_cuota'   => $this->fecha_primera_cuota,
                    'dui_verificado'        => (bool) $this->dui_verificado,
                    'referencia_verificada' => (bool) $this->referencia_verificada,
                ];

                if (!empty($this->fiador_nombre)) {
                    $condicionesCredito['fiador'] = [
                        'nombre'    => $this->fiador_nombre,
                        'dui'       => $this->fiador_dui,
                        'telefono'  => $this->fiador_telefono,
                        'direccion' => $this->fiador_direccion,
                    ];
                }
            }
            
            $datosVenta = [
                'cliente_id'          => $this->cliente_id ?: null,
                'tipo_documento'      => $this->tipo_documento,
                'condicion_operacion' => $this->condicion_operacion,
                'bodega_id'           => $this->bodega_id ?: 1,
                'condiciones_credito' => $condicionesCredito,
            ];

            $venta = $service->procesarVenta($datosVenta, $this->detalles, $usuarioId);

            $this->detalles = [];
            $this->calcularTotales();

            $msg = "¡Documento DTE {$venta->numero_control} emitido con éxito!";
            if ($venta->credito) {
                $msg .= " Crédito asociado: {$venta->credito->numero} (" . strtoupper($venta->credito->estado) . ").";
            }

            session()->flash('success', $msg);
            
            return redirect()->route('facturacion.pdf', $venta->id);
            
        } catch (Exception $e) {
            session()->flash('error', 'Error al procesar la venta: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $ventasHoy = Venta::whereDate('fecha_emision', now()->toDateString())->get();
        $totalHoy = $ventasHoy->sum('total_pagar');
        $docsHoy = $ventasHoy->count();
        $docsContingencia = $ventasHoy->where('estado_dte', 'contingencia')->count();
        $docsTransmitidos = $ventasHoy->where('estado_dte', 'procesado')->count();

        $clienteSeleccionado = $this->cliente_id ? Cliente::find($this->cliente_id) : null;

        return view('livewire.punto-venta', [
            'totalHoy' => $totalHoy,
            'docsHoy' => $docsHoy,
            'docsContingencia' => $docsContingencia,
            'docsTransmitidos' => $docsTransmitidos,
            'clienteSeleccionado' => $clienteSeleccionado,
            'condiciones' => $this->condicionesCredito,
            'condicionSeleccionada' => $this->condicionSeleccionada,
        ])->layout('layouts.app', ['title' => 'Punto de Venta DTE']);
    }
}
