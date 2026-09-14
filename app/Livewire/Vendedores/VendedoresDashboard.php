<?php

namespace App\Livewire\Vendedores;

use Livewire\Component;
use App\Models\Vendedor;
use App\Models\Zona;

class VendedoresDashboard extends Component
{
    public $showModal = false;
    public $modalMode = 'create';
    public $vendedorId;

    public $codigo, $nombre, $telefono, $email, $zona_id, $meta_mensual, $activo = true;

    protected $rules = [
        'codigo' => 'required|string|max:20',
        'nombre' => 'required|string|max:255',
        'telefono' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255',
        'zona_id' => 'nullable|exists:zonas,id',
        'meta_mensual' => 'numeric|min:0',
        'activo' => 'boolean',
    ];

    public function create()
    {
        $this->reset(['vendedorId', 'codigo', 'nombre', 'telefono', 'email', 'zona_id', 'meta_mensual']);
        $this->activo = true;
        $this->modalMode = 'create';
        $this->showModal = true;
    }

    public function edit($id)
    {
        $this->resetValidation();
        $vendedor = Vendedor::findOrFail($id);
        $this->vendedorId = $vendedor->id;
        $this->codigo = $vendedor->codigo;
        $this->nombre = $vendedor->nombre;
        $this->telefono = $vendedor->telefono;
        $this->email = $vendedor->email;
        $this->zona_id = $vendedor->zona_id;
        $this->meta_mensual = $vendedor->meta_mensual;
        $this->activo = (bool) $vendedor->activo;
        
        $this->modalMode = 'edit';
        $this->showModal = true;
    }

    public function save()
    {
        $this->validate();

        if ($this->modalMode === 'create') {
            Vendedor::create([
                'codigo' => $this->codigo,
                'nombre' => $this->nombre,
                'telefono' => $this->telefono,
                'email' => $this->email,
                'zona_id' => $this->zona_id ?: null,
                'meta_mensual' => $this->meta_mensual ?: 0,
                'activo' => $this->activo,
            ]);
            session()->flash('success', 'Vendedor creado exitosamente.');
        } else {
            $vendedor = Vendedor::findOrFail($this->vendedorId);
            $vendedor->update([
                'codigo' => $this->codigo,
                'nombre' => $this->nombre,
                'telefono' => $this->telefono,
                'email' => $this->email,
                'zona_id' => $this->zona_id ?: null,
                'meta_mensual' => $this->meta_mensual ?: 0,
                'activo' => $this->activo,
            ]);
            session()->flash('success', 'Vendedor actualizado exitosamente.');
        }

        $this->showModal = false;
    }

    public function delete($id)
    {
        Vendedor::findOrFail($id)->delete();
        session()->flash('success', 'Vendedor eliminado exitosamente.');
    }

    public function closeModal()
    {
        $this->showModal = false;
    }

    public function render()
    {
        return view('livewire.vendedores.vendedores-dashboard', [
            'vendedores' => Vendedor::with('zona')->get(),
            'zonas' => Zona::all(),
        ])->layout('layouts.app', ['title' => 'Directorio de Vendedores']);
    }
}
