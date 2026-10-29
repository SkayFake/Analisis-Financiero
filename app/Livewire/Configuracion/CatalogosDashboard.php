<?php

namespace App\Livewire\Configuracion;

use Livewire\Component;
use Livewire\Attributes\Locked;
use App\Livewire\Concerns\NormalizaDatosSalvador;
use App\Rules\NitSalvador;
use App\Rules\TelefonoSalvador;
use App\Support\ReglasFormulario;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Bodega;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Proveedor;
use App\Models\UnidadMedida;
use App\Models\Institucion;
use App\Models\Unidad;

class CatalogosDashboard extends Component
{
    use NormalizaDatosSalvador;

    private const MODELOS = ['bodegas' => Bodega::class, 'categorias' => Categoria::class, 'marcas' => Marca::class, 'unidades' => UnidadMedida::class, 'proveedores' => Proveedor::class, 'instituciones' => Institucion::class, 'unidades_organizativas' => Unidad::class];

    #[Locked]
    public $tab = 'bodegas';
    public $showModal = false;
    public $modalMode = 'create';
    #[Locked]
    public $itemId;

    // Form states
    public $nombre, $direccion, $activa = true, $descripcion, $abreviatura, $nit, $telefono, $dias_credito;
    public $codigo, $institucion_id; // For Institucion and Unidad

    public function setTab($tabName)
    {
        Validator::make(['tab' => $tabName], ['tab' => ['required', Rule::in(array_keys(self::MODELOS))]])->validate();
        $this->tab = $tabName;
        $this->showModal = false;
    }

    public function create()
    {
        $this->reset(['itemId', 'nombre', 'direccion', 'descripcion', 'abreviatura', 'nit', 'telefono', 'dias_credito', 'codigo', 'institucion_id']);
        $this->activa = true;
        $this->dias_credito = 0;
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
        Validator::make(['tab' => $this->tab], ['tab' => ['required', Rule::in(array_keys(self::MODELOS))]])->validate();
        $this->normalizarCampos(['nombre', 'direccion', 'descripcion', 'abreviatura', 'nit', 'telefono', 'codigo']);
        $rules = ['nombre' => 'required|string|max:255|regex:/[\p{L}\p{N}]/u'];
        $rules += match ($this->tab) {
            'bodegas' => ['direccion' => 'nullable|string|max:255', 'activa' => 'required|boolean'],
            'categorias' => ['descripcion' => 'nullable|string|max:2000', 'activa' => 'required|boolean'],
            'marcas' => ['activa' => 'required|boolean'],
            'unidades' => ['abreviatura' => 'required|string|max:10|regex:/^[\p{L}0-9.]+$/u'],
            'proveedores' => [
                'nit' => ['bail', 'nullable', 'string', new NitSalvador],
                'telefono' => ['bail', 'nullable', 'string', new TelefonoSalvador],
                'dias_credito' => 'required|integer|min:0|max:3650',
            ],
            'instituciones' => ['codigo' => ['required', ...explode('|', ReglasFormulario::CODIGO), 'max:4', Rule::unique('instituciones', 'codigo')->ignore($this->itemId)]],
            'unidades_organizativas' => [
                'codigo' => ['required', ...explode('|', ReglasFormulario::CODIGO), 'max:4', Rule::unique('unidades', 'codigo')->where('institucion_id', $this->institucion_id)->ignore($this->itemId)],
                'institucion_id' => 'required|integer|exists:instituciones,id',
            ],
        };
        $datos = $this->validate($rules);
        $modelo = self::MODELOS[$this->tab];
        $modelo::updateOrCreate(['id' => $this->itemId], $datos);
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
