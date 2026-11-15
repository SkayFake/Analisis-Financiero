<?php

namespace App\Livewire;

use App\Models\ActivoFijo;
use App\Models\CategoriaActivo;
use App\Models\Unidad;
use App\Services\ActivoFijoService;
use App\Support\Acceso;
use Livewire\Component;

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

    public $form_fecha_inicio_uso = '';

    public $form_clase_fiscal = '';

    public $form_valor_bien_nuevo = null;

    public $form_valor_aduana = null;

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
        Acceso::exigir('activos.view');
        $this->categorias = CategoriaActivo::all();
        $this->unidades = Unidad::with('institucion')->get();
        $this->form_fecha_adquisicion = now()->toDateString();
        $this->form_fecha_inicio_uso = now()->toDateString();
        $this->cargarDatos();
    }

    public function cargarDatos()
    {
        Acceso::exigir('activos.view');
        $this->activos = ActivoFijo::with(['unidad.institucion', 'categoria'])->orderBy('id', 'desc')->get();

        $this->totalAdquisicion = $this->activos->whereIn('estado', ['activo', 'depreciado'])->sum('valor_adquisicion');
        $this->totalSujetoDepreciacion = $this->activos->whereIn('estado', ['activo', 'depreciado'])->sum('valor_sujeto_depreciacion');
        $this->totalDepreciacionAcumulada = $this->activos->whereIn('estado', ['activo', 'depreciado'])->sum('depreciacion_acumulada');
        $this->valorNetoLibros = $this->totalAdquisicion - $this->totalDepreciacionAcumulada;
    }

    public function abrirModalRegistro()
    {
        Acceso::exigir('activos.create');
        $this->reset('form_clase_fiscal', 'form_valor_bien_nuevo', 'form_valor_aduana', 'form_unidad_id', 'form_categoria_id', 'form_nombre', 'form_valor_adquisicion', 'form_valor_residual', 'form_es_usado', 'form_anios_uso', 'form_maquinaria_importada');
        $this->form_fecha_adquisicion = now()->toDateString();
        $this->form_fecha_inicio_uso = now()->toDateString();
        $this->mostrarModalRegistro = true;
    }

    public function registrarActivo()
    {
        Acceso::exigir('activos.create');
        if (! $this->form_es_usado) {
            $this->form_anios_uso = 0;
        }
        $this->validate([
            'form_unidad_id' => 'required|integer|exists:unidades,id',
            'form_categoria_id' => 'required|integer|exists:categoria_activos,id',
            'form_nombre' => 'required|string|max:255|regex:/[\p{L}\p{N}]/u',
            'form_fecha_adquisicion' => 'required|date_format:Y-m-d|before_or_equal:today',
            'form_clase_fiscal' => 'required|in:edificaciones,maquinaria,vehiculos,otros_muebles',
            'form_fecha_inicio_uso' => 'required|date_format:Y-m-d|after_or_equal:form_fecha_adquisicion|before_or_equal:today',
            'form_valor_bien_nuevo' => 'required_if:form_es_usado,1|nullable|numeric|min:0.01',
            'form_valor_aduana' => 'required_if:form_maquinaria_importada,1|nullable|numeric|min:0.01',
            'form_valor_adquisicion' => 'required|numeric|min:1|max:9999999999.99|decimal:0,2',
            'form_valor_residual' => 'required|numeric|min:0|lte:form_valor_adquisicion|decimal:0,2',
            'form_es_usado' => 'required|boolean',
            'form_maquinaria_importada' => 'required|boolean',
            'form_anios_uso' => 'required|integer|max:150|min:'.($this->form_es_usado ? '1' : '0'),
        ]);

        try {
            $service = app(ActivoFijoService::class);
            $service->registrarActivo([
                'unidad_id' => $this->form_unidad_id,
                'categoria_id' => $this->form_categoria_id,
                'nombre' => $this->form_nombre,
                'fecha_adquisicion' => $this->form_fecha_adquisicion,
                'fecha_inicio_uso' => $this->form_fecha_inicio_uso,
                'clase_fiscal' => $this->form_clase_fiscal,
                'valor_bien_nuevo' => $this->form_valor_bien_nuevo,
                'valor_aduana' => $this->form_valor_aduana,
                'valor_adquisicion' => $this->form_valor_adquisicion,
                'valor_residual' => $this->form_valor_residual,
                'es_usado' => $this->form_es_usado,
                'anios_uso_previo' => $this->form_anios_uso,
                'maquinaria_importada_exenta' => $this->form_maquinaria_importada,
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
        Acceso::exigir('activos.edit');
        try {
            $service = app(ActivoFijoService::class);
            $cantidad = $service->calcularDepreciacionMasiva(today()->subMonthNoOverflow()->endOfMonth()); // Asume fin de mes actual
            $this->cargarDatos();
            session()->flash('success', "Depreciación de {$cantidad} activos calculada exitosamente.");
        } catch (\Exception $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function abrirModalBaja($id)
    {
        Acceso::exigir('activos.baja');
        $this->activoSeleccionadoId = $id;
        $this->mostrarModalBaja = true;
    }

    public function confirmarBaja()
    {
        Acceso::exigir('activos.baja');
        $this->validate([
            'activoSeleccionadoId' => 'required|integer|exists:activos_fijos,id',
            'baja_motivo' => 'required|in:vendido,donado,botado',
            'baja_detalles' => 'nullable|string|max:2000',
        ]);
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
        Acceso::exigir('activos.view');

        return view('livewire.activo-fijo-dashboard')->layout('layouts.app', ['title' => 'Activo Fijo']);
    }
}
