<?php

namespace App\Livewire\Configuracion;

use Livewire\Component;
use App\Models\Bodega;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\Institucion;
use App\Models\Unidad;

class CatalogosDashboard extends Component
{
    public $tab = 'bodegas';
    public $showModal = false;
    public $modalMode = 'create';
    public $itemId;

    // Form states
    public $nombre, $direccion, $activa = true, $descripcion, $abreviatura, $nit, $telefono, $dias_credito;
    public $codigo, $institucion_id; // For Institucion and Unidad

    public function setTab($tabName)
    {
        $this->tab = $tabName;
        $this->showModal = false;
    }

    public function create()
    {
        $this->reset(['itemId', 'nombre', 'direccion', 'descripcion', 'abreviatura', 'nit', 'telefono', 'dias_credito', 'codigo', 'institucion_id']);
        $this->activa = true;
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $this->itemId = $id;
        $this->modalMode = 'edit';
        $this->showModal = true;

        switch ($this->tab) {
            case 'bodegas':
                $item = Bodega::findOrFail($id);
                $this->nombre = $item->nombre;
                $this->direccion = $item->direccion;
                $this->activa = (bool) $item->activa;
                break;
            case 'categorias':
                $item = Categoria::findOrFail($id);
                $this->nombre = $item->nombre;
                $this->descripcion = $item->descripcion;
                $this->activa = (bool) $item->activa;
                break;
            case 'marcas':
                $item = Marca::findOrFail($id);
                $this->nombre = $item->nombre;
                $this->activa = (bool) $item->activa;
                break;
            case 'unidades':
                $item = UnidadMedida::findOrFail($id);
                $this->nombre = $item->nombre;
                $this->abreviatura = $item->abreviatura;
                break;
            case 'proveedores':
                $item = Proveedor::findOrFail($id);
                $this->nombre = $item->nombre;
                $this->nit = $item->nit;
                $this->telefono = $item->telefono;
                $this->dias_credito = $item->dias_credito;
                break;
            case 'instituciones':
                $item = Institucion::findOrFail($id);
                $this->nombre = $item->nombre;
                $this->codigo = $item->codigo;
                break;
            case 'unidades_organizativas':
                $item = Unidad::findOrFail($id);
                $this->nombre = $item->nombre;
                $this->codigo = $item->codigo;
                $this->institucion_id = $item->institucion_id;
                break;
        }
    }

    public function save()
    {
        switch ($this->tab) {
            case 'bodegas':
                $this->validate([
                    'nombre' => 'required|string|max:255',
                    'direccion' => 'nullable|string',
                    'activa' => 'boolean',
                ]);
                Bodega::updateOrCreate(['id' => $this->itemId], [
                    'nombre' => $this->nombre,
                    'direccion' => $this->direccion,
                    'activa' => $this->activa,
                ]);
                break;
            case 'categorias':
                $this->validate([
                    'nombre' => 'required|string|max:255',
                    'descripcion' => 'nullable|string',
                    'activa' => 'boolean',
                ]);
                Categoria::updateOrCreate(['id' => $this->itemId], [
                    'nombre' => $this->nombre,
                    'descripcion' => $this->descripcion,
                    'activa' => $this->activa,
                ]);
                break;
            case 'marcas':
                $this->validate([
                    'nombre' => 'required|string|max:255',
                    'activa' => 'boolean',
                ]);
                Marca::updateOrCreate(['id' => $this->itemId], [
                    'nombre' => $this->nombre,
                    'activa' => $this->activa,
                ]);
                break;
            case 'unidades':
                $this->validate([
                    'nombre' => 'required|string|max:255',
                    'abreviatura' => 'required|string|max:10',
                ]);
                UnidadMedida::updateOrCreate(['id' => $this->itemId], [
                    'nombre' => $this->nombre,
                    'abreviatura' => $this->abreviatura,
                ]);
                break;
            case 'proveedores':
                $this->validate([
                    'nombre' => 'required|string|max:255',
                    'nit' => 'nullable|string|max:50',
                    'telefono' => 'nullable|string|max:20',
                    'dias_credito' => 'required|integer|min:0',
                ]);
                Proveedor::updateOrCreate(['id' => $this->itemId], [
                    'nombre' => $this->nombre,
                    'nit' => $this->nit,
                    'telefono' => $this->telefono,
                    'dias_credito' => $this->dias_credito,
                ]);
                break;
            case 'instituciones':
                $this->validate([
                    'nombre' => 'required|string|max:255',
                    'codigo' => 'required|string|max:4|unique:instituciones,codigo,' . $this->itemId,
                ]);
                Institucion::updateOrCreate(['id' => $this->itemId], [
                    'nombre' => $this->nombre,
                    'codigo' => $this->codigo,
                ]);
                break;
            case 'unidades_organizativas':
                $this->validate([
                    'nombre' => 'required|string|max:255',
                    'codigo' => 'required|string|max:4',
                    'institucion_id' => 'required|exists:instituciones,id',
                ]);
                // TODO: handle unique constraint (institucion_id, codigo) properly if needed
                Unidad::updateOrCreate(['id' => $this->itemId], [
                    'nombre' => $this->nombre,
                    'codigo' => $this->codigo,
                    'institucion_id' => $this->institucion_id,
                ]);
                break;
        }

        session()->flash('success', 'Registro guardado exitosamente.');
        $this->showModal = false;
    }

    public function delete($id)
    {
        switch ($this->tab) {
            case 'bodegas': Bodega::findOrFail($id)->delete(); break;
            case 'categorias': Categoria::findOrFail($id)->delete(); break;
            case 'marcas': Marca::findOrFail($id)->delete(); break;
            case 'unidades': UnidadMedida::findOrFail($id)->delete(); break;
            case 'proveedores': Proveedor::findOrFail($id)->delete(); break;
            case 'instituciones': Institucion::findOrFail($id)->delete(); break;
            case 'unidades_organizativas': Unidad::findOrFail($id)->delete(); break;
        }
        session()->flash('success', 'Registro eliminado exitosamente.');
    }

    public function closeModal()
    {
        $this->showModal = false;
    }

    public function render()
    {
        return view('livewire.configuracion.catalogos-dashboard', [
            'bodegas' => Bodega::all(),
            'categorias' => Categoria::all(),
            'marcas' => Marca::all(),
            'proveedores' => Proveedor::all(),
            'unidades' => UnidadMedida::all(),
            'instituciones' => Institucion::all(),
            'unidades_organizativas' => Unidad::with('institucion')->get(),
        ])->layout('layouts.app', ['title' => 'Configuración de Catálogos']);
    }
}
