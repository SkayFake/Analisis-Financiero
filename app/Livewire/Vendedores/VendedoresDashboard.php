<?php

namespace App\Livewire\Vendedores;

use App\Livewire\Concerns\NormalizaDatosSalvador;
use App\Models\Vendedor;
use App\Models\Zona;
use App\Rules\TelefonoSalvador;
use App\Services\VendedorMetricasService;
use App\Services\VendedorService;
use App\Support\ReglasFormulario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class VendedoresDashboard extends Component
{
    use NormalizaDatosSalvador, WithPagination;

    #[Url]
    public $mes = '';

    #[Url]
    public $buscar = '';

    #[Url]
    public $estado = 'todos';

    #[Url]
    public $zona = '';

    public $showModal = false;

    #[Locked]
    public $modalMode = 'create';

    #[Locked]
    public $vendedorId;

    public $codigo;

    public $nombre;

    public $telefono;

    public $email;

    public $zona_id;

    public $meta_mensual;

    public $activo = true;

    public $motivo = '';

    public function mount(): void
    {
        Gate::authorize('viewAny', Vendedor::class);
        if ($this->mes === '') {
            $this->mes = now()->format('Y-m');
        }
    }

    protected function rules(): array
    {
        return [
            'codigo' => ['required', ...explode('|', ReglasFormulario::CODIGO), 'max:20', Rule::unique('vendedores', 'codigo')->ignore($this->vendedorId)],
            'nombre' => 'required|string|max:255|regex:/[\p{L}]/u',
            'telefono' => ['nullable', 'string', new TelefonoSalvador],
            'email' => 'nullable|string|email:rfc|max:255',
            'zona_id' => ['nullable', 'integer', Rule::exists('zonas', 'id')->where('activa', true)],
            'meta_mensual' => 'nullable|'.ReglasFormulario::DINERO,
            'activo' => 'required|boolean',
            'motivo' => $this->modalMode === 'edit' ? 'required|string|min:10|max:2000' : 'nullable|string|max:2000',
        ];
    }

    public function filtrar(): void
    {
        $this->validate([
            'mes' => 'required|date_format:Y-m|after_or_equal:2000-01|before_or_equal:2100-12',
            'buscar' => 'nullable|string|max:150', 'estado' => 'required|in:todos,activos,inactivos',
            'zona' => 'nullable|integer|exists:zonas,id',
        ]);
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('manage', Vendedor::class);
        $this->resetValidation();
        $this->reset(['vendedorId', 'codigo', 'nombre', 'telefono', 'email', 'zona_id', 'meta_mensual', 'motivo']);
        $this->activo = true;
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function edit($id): void
    {
        Gate::authorize('manage', Vendedor::class);
        $this->resetValidation();
        $vendedor = Vendedor::findOrFail($id);
        $this->vendedorId = $vendedor->id;
        foreach (['codigo', 'nombre', 'telefono', 'email', 'zona_id', 'meta_mensual', 'activo'] as $campo) {
            $this->$campo = $vendedor->$campo;
        }
        $this->motivo = '';
        $this->modalMode = 'edit';
        $this->showModal = true;
    }

    public function save(): void
    {
        Gate::authorize('manage', Vendedor::class);
        $this->normalizarCampos(['codigo', 'nombre', 'telefono', 'email', 'motivo']);
        $datos = $this->validate();
        $creacion = $this->modalMode === 'create';
        if ($creacion && (float) ($this->meta_mensual ?? 0) > 0) {
            Gate::authorize('metas', Vendedor::class);
        }
        DB::transaction(function () use ($datos, $creacion) {
            $service = app(VendedorService::class);
            $vendedor = $service->guardar($creacion ? null : $this->vendedorId,
                collect($datos)->only(['codigo', 'nombre', 'telefono', 'email', 'zona_id', 'activo'])->all(),
                $creacion ? 'Alta inicial del vendedor' : $this->motivo, auth()->user());
            if ($creacion) {
                $service->meta($vendedor, now()->format('Y-m'),
                    ['meta_ventas' => $this->meta_mensual ?: 0, 'meta_cobros' => 0], 'Meta inicial del mes de alta', auth()->user());
            }
        });
        $this->showModal = false;
        session()->flash('success', $creacion ? 'Vendedor creado con su meta inicial e historial.' : 'Vendedor actualizado. Los movimientos históricos se conservaron.');
    }

    public function delete($id): void
    {
        Gate::authorize('manage', Vendedor::class);
        throw ValidationException::withMessages(['vendedor' => 'Desactive al vendedor desde Editar e indique el motivo. No se elimina su historial.']);
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function render()
    {
        Gate::authorize('viewAny', Vendedor::class);
        $valido = Validator::make(['mes' => $this->mes], ['mes' => 'required|date_format:Y-m|after_or_equal:2000-01|before_or_equal:2100-12'])->passes();
        $periodo = app(VendedorMetricasService::class)->periodo($valido ? $this->mes : now()->format('Y-m'));
        $query = Vendedor::with('zona');
        if (is_string($this->buscar) && $this->buscar !== '' && mb_strlen($this->buscar) <= 150) {
            $buscar = $this->buscar;
            $query->where(fn ($q) => $q->where('nombre', 'ilike', '%'.$buscar.'%')->orWhere('codigo', 'ilike', '%'.$buscar.'%'));
        }
        if ($this->estado === 'activos') {
            $query->where('activo', true);
        }
        if ($this->estado === 'inactivos') {
            $query->where('activo', false);
        }
        if (is_scalar($this->zona) && ctype_digit((string) $this->zona)) {
            $query->where('zona_id', $this->zona);
        }
        $todos = (clone $query)->orderBy('nombre')->get();
        $resumenes = app(VendedorMetricasService::class)->resumenes($todos, $periodo);
        $vendedores = $query->orderBy('nombre')->paginate(15);
        $incidencias = app(VendedorMetricasService::class)->incidencias($periodo);

        return view('livewire.vendedores.vendedores-dashboard', [
            'vendedores' => $vendedores, 'zonas' => Zona::activas()->get(), 'resumenes' => $resumenes,
            'periodo' => $periodo, 'incidencias' => $incidencias,
        ])->layout('layouts.app', ['title' => 'Control de vendedores']);
    }
}
