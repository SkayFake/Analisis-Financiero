<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Producto;
use App\Models\Existencia;
use App\Models\Bodega;

class InventarioDashboard extends Component
{
    use WithPagination;

    public $bodega_id = '';
    public $buscar = '';
    public $mostrarAgotados = false;

    public function updatingBuscar()
    {
        $this->resetPage();
    }

    public function updatingBodegaId()
    {
        $this->resetPage();
    }

    public function updated($property): void
    {
        if (in_array($property, ['buscar', 'bodega_id', 'mostrarAgotados'])) {
            $this->validateOnly($property, [
                'buscar' => 'nullable|string|max:150',
                'bodega_id' => 'nullable|integer|exists:bodegas,id',
                'mostrarAgotados' => 'required|boolean',
            ]);
        }
    }

    // Reactividad: Si se recarga o un evento dice que se actualizó el stock
    protected $listeners = ['stockActualizado' => '$refresh'];

    public function render()
    {
        $busqueda = is_string($this->buscar) ? mb_substr($this->buscar, 0, 150) : '';
        $query = Producto::with(['existencias.bodega', 'categoria', 'marca']);

        if ($busqueda !== '') {
            $query->where(function($q) use ($busqueda) {
                $q->where('nombre', 'ilike', '%' . $busqueda . '%')
                  ->orWhere('codigo', 'ilike', '%' . $busqueda . '%');
            });
        }

        if ($this->mostrarAgotados === true || $this->mostrarAgotados === '1' || $this->mostrarAgotados === 1) {
            // Filtrar productos donde el stock total es 0 o menor al mínimo
            $query->whereRaw('COALESCE((SELECT SUM(cantidad) FROM existencias WHERE existencias.producto_id = productos.id), 0) <= 0');
        }

        $productos = $query->paginate(15);
        $bodegas = Bodega::where('activa', true)->get();

        // Calcular Valoración Total
        $valoracionTotal = Producto::all()->sum(function($p) {
            return $p->stock_total * $p->costo_unitario;
        });

        // Contar alertas
        $alertasStock = Producto::all()->filter(function($p) {
            return $p->stock_total <= $p->stock_minimo;
        })->count();

        // Caducidades próximas (30 días) o vencidas
        $alertasCaducidad = Existencia::whereNotNull('fecha_vencimiento')
            ->where('cantidad', '>', 0)
            ->where('fecha_vencimiento', '<=', now()->addDays(30))
            ->count();

        return view('livewire.inventario-dashboard', [
            'productos' => $productos,
            'bodegas' => $bodegas,
            'valoracionTotal' => $valoracionTotal,
            'alertasStock' => $alertasStock,
            'alertasCaducidad' => $alertasCaducidad,
        ])->layout('layouts.app');
    }
}
