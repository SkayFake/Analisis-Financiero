<div>
    <div class="page-header" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Directorio de Vendedores</h1>
            <p class="page-description">Administre los vendedores asignados a las zonas y carteras.</p>
        </div>
        <button wire:click="create" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo Vendedor
        </button>
    </div>

    <div class="card">
        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Zona</th>
                            <th>Meta Mensual</th>
                            <th>Estado</th>
                            <th class="text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($vendedores as $vendedor)
                        <tr>
                            <td>{{ $vendedor->codigo }}</td>
                            <td>{{ $vendedor->nombre }}</td>
                            <td>{{ $vendedor->telefono ?? 'N/A' }}</td>
                            <td>{{ $vendedor->zona?->nombre ?? 'Sin Zona' }}</td>
                            <td>${{ number_format($vendedor->meta_mensual, 2) }}</td>
                            <td>
                                <span class="badge {{ $vendedor->activo ? 'badge-success' : 'badge-danger' }}">
                                    {{ $vendedor->activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-right">
                                <button wire:click="edit({{ $vendedor->id }})" class="btn btn-sm btn-secondary" title="Editar">Editar</button>
                                <button wire:click="delete({{ $vendedor->id }})" wire:confirm="¿Está seguro de eliminar este vendedor?" class="btn btn-sm btn-danger" style="color: var(--danger-600);" title="Eliminar">Eliminar</button>
                            </td>
                        </tr>
                        @endforeach
                        
                        @if($vendedores->isEmpty())
                        <tr>
                            <td colspan="7" class="text-center">No hay vendedores registrados.</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($showModal)
    <div class="modal-overlay" wire:keydown.escape="closeModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title">{{ $modalMode === 'create' ? 'Nuevo Vendedor' : 'Editar Vendedor' }}</h3>
                <button type="button" class="modal-close" wire:click="closeModal" title="Cerrar">&times;</button>
            </div>
            <form wire:submit.prevent="save">
                <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">Código</label>
                        <input type="text" wire:model="codigo" class="form-input" required>
                        @error('codigo') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" wire:model="nombre" class="form-input" required>
                        @error('nombre') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">Teléfono</label>
                            <input type="text" wire:model="telefono" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" wire:model="email" class="form-input">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">Zona</label>
                            <select wire:model="zona_id" class="form-select">
                                <option value="">Ninguna</option>
                                @foreach($zonas as $zona)
                                    <option value="{{ $zona->id }}">{{ $zona->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Meta Mensual ($)</label>
                            <input type="number" step="0.01" wire:model="meta_mensual" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                            <input type="checkbox" wire:model="activo">
                            Vendedor Activo
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
