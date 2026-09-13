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

    // Reactividad: Si se recarga o un evento dice que se actualizó el stock
    protected $listeners = ['stockActualizado' => '$refresh'];

    public function render()
    {
        $query = Producto::with(['existencias.bodega', 'categoria', 'marca']);

        if ($this->buscar) {
            $query->where(function($q) {
                $q->where('nombre', 'ilike', '%' . $this->buscar . '%')
                  ->orWhere('codigo', 'ilike', '%' . $this->buscar . '%');
            });
        }

        if ($this->mostrarAgotados) {
            // Filtrar productos donde el stock total es 0 o menor al mínimo
            $query->whereHas('existencias', function($q) {
                $q->havingRaw('SUM(cantidad) <= 0');
            })->orWhereDoesntHave('existencias');
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
