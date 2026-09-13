<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\ActivoFijo;
use App\Models\CategoriaActivo;
use App\Models\Unidad;
use App\Services\ActivoFijoService;

class ActivoFijoDashboard extends Component
{
    public $activos = [];
    public $categorias = [];
    public $unidades = [];

    public $totalAdquisicion = 0;
    public $totalSujetoDepreciacion = 0;
    public $totalDepreciacionAcumulada = 0;
    public $valorNetoLibros = 0;

    // Modals
    public $mostrarModalRegistro = false;
    public $mostrarModalBaja = false;
    public $activoSeleccionadoId = null;

    // Formulario Registro
    public $form_unidad_id = '';
    public $form_categoria_id = '';
    public $form_nombre = '';
    public $form_fecha_adquisicion = '';
    public $form_valor_adquisicion = 0;
    public $form_valor_residual = 0;
    public $form_es_usado = false;
    public $form_anios_uso = 0;
    public $form_maquinaria_importada = false;

    // Formulario Baja
    public $baja_motivo = 'vendido';
    public $baja_detalles = '';

    public function mount()
    {
        $this->categorias = CategoriaActivo::all();
        $this->unidades = Unidad::with('institucion')->get();
        $this->form_fecha_adquisicion = now()->toDateString();
        $this->cargarDatos();
    }

    public function cargarDatos()
    {
        $this->activos = ActivoFijo::with(['unidad.institucion', 'categoria'])->orderBy('id', 'desc')->get();
        
        $this->totalAdquisicion = $this->activos->where('estado', '!=', 'donado')->where('estado', '!=', 'botado')->sum('valor_adquisicion');
        $this->totalSujetoDepreciacion = $this->activos->where('estado', '!=', 'donado')->where('estado', '!=', 'botado')->sum('valor_sujeto_depreciacion');
        $this->totalDepreciacionAcumulada = $this->activos->where('estado', '!=', 'donado')->where('estado', '!=', 'botado')->sum('depreciacion_acumulada');
        $this->valorNetoLibros = $this->totalAdquisicion - $this->totalDepreciacionAcumulada;
    }

    public function abrirModalRegistro()
    {
        $this->reset('form_unidad_id', 'form_categoria_id', 'form_nombre', 'form_valor_adquisicion', 'form_valor_residual', 'form_es_usado', 'form_anios_uso', 'form_maquinaria_importada');
        $this->form_fecha_adquisicion = now()->toDateString();
        $this->mostrarModalRegistro = true;
    }

    public function registrarActivo()
    {
        $this->validate([
            'form_unidad_id' => 'required',
            'form_categoria_id' => 'required',
            'form_nombre' => 'required',
            'form_fecha_adquisicion' => 'required|date',
            'form_valor_adquisicion' => 'required|numeric|min:1',
            'form_anios_uso' => 'required_if:form_es_usado,true,1,"1"'
        ]);

        try {
            $service = app(ActivoFijoService::class);
            $service->registrarActivo([
                'unidad_id' => $this->form_unidad_id,
                'categoria_id' => $this->form_categoria_id,
                'nombre' => $this->form_nombre,
                'fecha_adquisicion' => $this->form_fecha_adquisicion,
                'valor_adquisicion' => $this->form_valor_adquisicion,
                'valor_residual' => $this->form_valor_residual,
                'es_usado' => $this->form_es_usado,
                'anios_uso_previo' => $this->form_anios_uso,
                'maquinaria_importada_exenta' => $this->form_maquinaria_importada
            ]);

            $this->mostrarModalRegistro = false;
            $this->cargarDatos();
            session()->flash('success', 'Activo Fijo registrado con éxito. Código generado automáticamente.');

        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function simularMes()
    {
        try {
            $service = app(ActivoFijoService::class);
            $cantidad = $service->calcularDepreciacionMasiva(); // Asume fin de mes actual
            $this->cargarDatos();
            session()->flash('success', "Depreciación de {$cantidad} activos calculada exitosamente.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function abrirModalBaja($id)
    {
        $this->activoSeleccionadoId = $id;
        $this->mostrarModalBaja = true;
    }

    public function confirmarBaja()
    {
        try {
            $service = app(ActivoFijoService::class);
            $service->desincorporarActivo($this->activoSeleccionadoId, $this->baja_motivo, $this->baja_detalles);
            
            $this->mostrarModalBaja = false;
            $this->cargarDatos();
            session()->flash('success', 'El activo fue dado de baja correctamente.');
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.activo-fijo-dashboard')->layout('layouts.app', ['title' => 'Activo Fijo']);
    }
}
