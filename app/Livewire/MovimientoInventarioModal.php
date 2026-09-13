<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Bodega;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\InventarioService;
use Exception;

class MovimientoInventarioModal extends Component
{
    public $isOpen = false;
    public $tipo = 'entrada'; // entrada, salida, transferencia

    // Form inputs
    public $bodega_id = '';
    public $bodega_destino_id = '';
    public $proveedor_id = '';
    public $referencia = '';
    public $observaciones = '';
    
    // Detalle
    public $producto_id = '';
    public $cantidad = 1;
    public $costo_unitario = 0;
    public $lote = '';
    public $fecha_vencimiento = '';

    // Colecciones para los selects
    public $bodegas = [];
    public $productos = [];
    public $proveedores = [];

    protected $listeners = ['abrirModalMovimiento' => 'openModal'];

    public function mount()
    {
        $this->bodegas = Bodega::where('activa', true)->get();
        $this->productos = Producto::where('activo', true)->get();
        $this->proveedores = Proveedor::where('activo', true)->get();
    }

    #[\Livewire\Attributes\On('abrirModalMovimiento')]
    public function openModal($params = [])
    {
        $this->reset(['bodega_id', 'bodega_destino_id', 'proveedor_id', 'referencia', 'observaciones', 'producto_id', 'cantidad', 'costo_unitario', 'lote', 'fecha_vencimiento']);
        
        // Si viene envuelto en { params: [...] }
        if (is_array($params) && isset($params['params']) && is_array($params['params'])) {
            $params = $params['params'];
        }

        if (is_array($params)) {
            $this->tipo = $params['tipo'] ?? 'entrada';
            if (!empty($params['producto_id'])) {
                $this->producto_id = $params['producto_id'];
                $this->updatedProductoId($this->producto_id);
            }
        } elseif (is_string($params)) {
            $this->tipo = $params;
        } else {
            $this->tipo = 'entrada';
        }

        $this->isOpen = true;
    }

    public function closeModal()
    {
        $this->isOpen = false;
    }

    public function updatedProductoId($value)
    {
        if ($value && $this->tipo == 'entrada') {
            $producto = Producto::find($value);
            $this->costo_unitario = $producto ? $producto->costo_unitario : 0;
        }
    }

    public function guardar()
    {
        $this->validate([
            'bodega_id' => 'required',
            'producto_id' => 'required',
            'cantidad' => 'required|numeric|min:0.01',
        ]);

        if ($this->tipo == 'entrada') {
            $this->validate(['costo_unitario' => 'required|numeric|min:0']);
        }

        if ($this->tipo == 'transferencia') {
            $this->validate(['bodega_destino_id' => 'required|different:bodega_id']);
        }

        try {
            $service = app(InventarioService::class);
            
            $datos = [
                'bodega_id' => $this->bodega_id,
                'proveedor_id' => $this->proveedor_id ?: null,
                'bodega_origen_id' => $this->bodega_id,
                'bodega_destino_id' => $this->bodega_destino_id,
                'referencia' => $this->referencia,
                'observaciones' => $this->observaciones,
                'detalles' => [
                    [
                        'producto_id' => $this->producto_id,
                        'cantidad' => $this->cantidad,
                        'costo_unitario' => $this->costo_unitario,
                        'lote' => $this->lote ?: null,
                        'fecha_vencimiento' => $this->fecha_vencimiento ?: null,
                    ]
                ]
            ];

            // 1 como user id por ahora (debería ser auth()->id())
            $userId = auth()->id() ?? 1;

            if ($this->tipo == 'entrada') {
                $service->registrarEntrada($datos, $userId);
            } elseif ($this->tipo == 'salida') {
                $service->registrarSalida($datos, $userId);
            } elseif ($this->tipo == 'transferencia') {
                $service->transferirBodega($datos, $userId);
            }

            // Notifica al dashboard para que actualice automáticamente sin recargar
            $this->dispatch('stockActualizado');
            
            $this->closeModal();
            session()->flash('message', 'Movimiento registrado con éxito.');
            
        } catch (Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.movimiento-inventario-modal');
    }
}
