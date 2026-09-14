<div>
    <div class="page-header" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Configuración de Catálogos</h1>
            <p class="page-description">Administre los catálogos base para el módulo de inventarios.</p>
        </div>
        <button wire:click="create" class="btn btn-primary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
            Nuevo Registro
        </button>
    </div>

    <div class="card">
        <div class="card-header" style="border-bottom: 1px solid var(--neutral-200); padding: 0;">
            <ul style="display: flex; list-style: none; margin: 0; padding: 0;">
                <li style="margin-right: 1rem;">
                    <button wire:click="setTab('bodegas')" 
                            style="padding: 1rem; border: none; background: transparent; cursor: pointer; font-weight: {{ $tab === 'bodegas' ? '600' : '400' }}; border-bottom: 2px solid {{ $tab === 'bodegas' ? 'var(--primary-600)' : 'transparent' }}; color: {{ $tab === 'bodegas' ? 'var(--primary-700)' : 'var(--neutral-600)' }};">
                        Bodegas
                    </button>
                </li>
                <li style="margin-right: 1rem;">
                    <button wire:click="setTab('categorias')" 
                            style="padding: 1rem; border: none; background: transparent; cursor: pointer; font-weight: {{ $tab === 'categorias' ? '600' : '400' }}; border-bottom: 2px solid {{ $tab === 'categorias' ? 'var(--primary-600)' : 'transparent' }}; color: {{ $tab === 'categorias' ? 'var(--primary-700)' : 'var(--neutral-600)' }};">
                        Categorías
                    </button>
                </li>
                <li style="margin-right: 1rem;">
                    <button wire:click="setTab('marcas')" 
                            style="padding: 1rem; border: none; background: transparent; cursor: pointer; font-weight: {{ $tab === 'marcas' ? '600' : '400' }}; border-bottom: 2px solid {{ $tab === 'marcas' ? 'var(--primary-600)' : 'transparent' }}; color: {{ $tab === 'marcas' ? 'var(--primary-700)' : 'var(--neutral-600)' }};">
                        Marcas
                    </button>
                </li>
                <li style="margin-right: 1rem;">
                    <button wire:click="setTab('unidades')" 
                            style="padding: 1rem; border: none; background: transparent; cursor: pointer; font-weight: {{ $tab === 'unidades' ? '600' : '400' }}; border-bottom: 2px solid {{ $tab === 'unidades' ? 'var(--primary-600)' : 'transparent' }}; color: {{ $tab === 'unidades' ? 'var(--primary-700)' : 'var(--neutral-600)' }};">
                        Unidades de Medida
                    </button>
                </li>
                <li>
                    <button wire:click="setTab('proveedores')" 
                            style="padding: 1rem; border: none; background: transparent; cursor: pointer; font-weight: {{ $tab === 'proveedores' ? '600' : '400' }}; border-bottom: 2px solid {{ $tab === 'proveedores' ? 'var(--primary-600)' : 'transparent' }}; color: {{ $tab === 'proveedores' ? 'var(--primary-700)' : 'var(--neutral-600)' }};">
                        Proveedores
                    </button>
                </li>
                <li>
                    <button wire:click="setTab('instituciones')" 
                            style="padding: 1rem; border: none; background: transparent; cursor: pointer; font-weight: {{ $tab === 'instituciones' ? '600' : '400' }}; border-bottom: 2px solid {{ $tab === 'instituciones' ? 'var(--primary-600)' : 'transparent' }}; color: {{ $tab === 'instituciones' ? 'var(--primary-700)' : 'var(--neutral-600)' }};">
                        Instituciones
                    </button>
                </li>
                <li>
                    <button wire:click="setTab('unidades_organizativas')" 
                            style="padding: 1rem; border: none; background: transparent; cursor: pointer; font-weight: {{ $tab === 'unidades_organizativas' ? '600' : '400' }}; border-bottom: 2px solid {{ $tab === 'unidades_organizativas' ? 'var(--primary-600)' : 'transparent' }}; color: {{ $tab === 'unidades_organizativas' ? 'var(--primary-700)' : 'var(--neutral-600)' }};">
                        Departamentos (Unidades)
                    </button>
                </li>
            </ul>
        </div>
        
        <div class="card-body" style="padding: 0;">
            @if($tab === 'bodegas')
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Dirección</th>
                                <th>Estado</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bodegas as $item)
                            <tr>
                                <td>{{ $item->nombre }}</td>
                                <td>{{ $item->direccion }}</td>
                                <td>
                                    <span class="badge {{ $item->activa ? 'badge-success' : 'badge-danger' }}">
                                        {{ $item->activa ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <button wire:click="edit({{ $item->id }})" class="btn btn-sm btn-secondary">Editar</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="¿Seguro de eliminar?" class="btn btn-sm btn-danger" style="color: var(--danger-600);">Eliminar</button>
                                </td>
                            </tr>
                            @endforeach
                            @if($bodegas->isEmpty())<tr><td colspan="4" class="text-center">No hay registros.</td></tr>@endif
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'categorias')
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($categorias as $item)
                            <tr>
                                <td>{{ $item->nombre }}</td>
                                <td>{{ $item->descripcion }}</td>
                                <td>
                                    <span class="badge {{ $item->activa ? 'badge-success' : 'badge-danger' }}">
                                        {{ $item->activa ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <button wire:click="edit({{ $item->id }})" class="btn btn-sm btn-secondary">Editar</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="¿Seguro de eliminar?" class="btn btn-sm btn-danger" style="color: var(--danger-600);">Eliminar</button>
                                </td>
                            </tr>
                            @endforeach
                            @if($categorias->isEmpty())<tr><td colspan="4" class="text-center">No hay registros.</td></tr>@endif
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'marcas')
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Estado</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($marcas as $item)
                            <tr>
                                <td>{{ $item->nombre }}</td>
                                <td>
                                    <span class="badge {{ $item->activa ? 'badge-success' : 'badge-danger' }}">
                                        {{ $item->activa ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <button wire:click="edit({{ $item->id }})" class="btn btn-sm btn-secondary">Editar</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="¿Seguro de eliminar?" class="btn btn-sm btn-danger" style="color: var(--danger-600);">Eliminar</button>
                                </td>
                            </tr>
                            @endforeach
                            @if($marcas->isEmpty())<tr><td colspan="3" class="text-center">No hay registros.</td></tr>@endif
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'unidades')
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Abreviatura</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unidades as $item)
                            <tr>
                                <td>{{ $item->nombre }}</td>
                                <td>{{ $item->abreviatura }}</td>
                                <td class="text-right">
                                    <button wire:click="edit({{ $item->id }})" class="btn btn-sm btn-secondary">Editar</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="¿Seguro de eliminar?" class="btn btn-sm btn-danger" style="color: var(--danger-600);">Eliminar</button>
                                </td>
                            </tr>
                            @endforeach
                            @if($unidades->isEmpty())<tr><td colspan="3" class="text-center">No hay registros.</td></tr>@endif
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'proveedores')
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>NIT</th>
                                <th>Teléfono</th>
                                <th>Días Crédito</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($proveedores as $item)
                            <tr>
                                <td>{{ $item->nombre }}</td>
                                <td>{{ $item->nit }}</td>
                                <td>{{ $item->telefono }}</td>
                                <td>{{ $item->dias_credito }}</td>
                                <td class="text-right">
                                    <button wire:click="edit({{ $item->id }})" class="btn btn-sm btn-secondary">Editar</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="¿Seguro de eliminar?" class="btn btn-sm btn-danger" style="color: var(--danger-600);">Eliminar</button>
                                </td>
                            </tr>
                            @endforeach
                            @if($proveedores->isEmpty())<tr><td colspan="5" class="text-center">No hay registros.</td></tr>@endif
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'instituciones')
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($instituciones as $item)
                            <tr>
                                <td>{{ $item->codigo }}</td>
                                <td>{{ $item->nombre }}</td>
                                <td class="text-right">
                                    <button wire:click="edit({{ $item->id }})" class="btn btn-sm btn-secondary">Editar</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="¿Seguro de eliminar?" class="btn btn-sm btn-danger" style="color: var(--danger-600);">Eliminar</button>
                                </td>
                            </tr>
                            @endforeach
                            @if($instituciones->isEmpty())<tr><td colspan="3" class="text-center">No hay registros.</td></tr>@endif
                        </tbody>
                    </table>
                </div>
            @endif

            @if($tab === 'unidades_organizativas')
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Institución</th>
                                <th>Código Dpto.</th>
                                <th>Nombre</th>
                                <th class="text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($unidades_organizativas as $item)
                            <tr>
                                <td>{{ $item->institucion->nombre ?? 'N/A' }}</td>
                                <td>{{ $item->codigo }}</td>
                                <td>{{ $item->nombre }}</td>
                                <td class="text-right">
                                    <button wire:click="edit({{ $item->id }})" class="btn btn-sm btn-secondary">Editar</button>
                                    <button wire:click="delete({{ $item->id }})" wire:confirm="¿Seguro de eliminar?" class="btn btn-sm btn-danger" style="color: var(--danger-600);">Eliminar</button>
                                </td>
                            </tr>
                            @endforeach
                            @if($unidades_organizativas->isEmpty())<tr><td colspan="4" class="text-center">No hay registros.</td></tr>@endif
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- MODAL DINÁMICO --}}
    @if($showModal)
    <div class="modal-overlay" wire:keydown.escape="closeModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    {{ $modalMode === 'create' ? 'Nuevo' : 'Editar' }} Registro
                </h3>
                <button type="button" class="modal-close" wire:click="closeModal" title="Cerrar">&times;</button>
            </div>
            <form wire:submit.prevent="save">
                <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                    
                    {{-- Campos comunes --}}
                    <div>
                        <label class="form-label">Nombre</label>
                        <input type="text" wire:model="nombre" class="form-input" required>
                        @error('nombre') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                    </div>

                    {{-- Campos específicos por pestaña --}}
                    @if($tab === 'bodegas')
                        <div>
                            <label class="form-label">Dirección</label>
                            <input type="text" wire:model="direccion" class="form-input">
                            @error('direccion') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if($tab === 'categorias')
                        <div>
                            <label class="form-label">Descripción</label>
                            <input type="text" wire:model="descripcion" class="form-input">
                            @error('descripcion') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if($tab === 'unidades')
                        <div>
                            <label class="form-label">Abreviatura (Ej: u, kg, m)</label>
                            <input type="text" wire:model="abreviatura" class="form-input" required>
                            @error('abreviatura') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if($tab === 'proveedores')
                        <div>
                            <label class="form-label">NIT / Documento</label>
                            <input type="text" wire:model="nit" class="form-input">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label class="form-label">Teléfono</label>
                                <input type="text" wire:model="telefono" class="form-input">
                            </div>
                            <div>
                                <label class="form-label">Días de Crédito</label>
                                <input type="number" min="0" wire:model="dias_credito" class="form-input" required>
                            </div>
                        </div>
                    @endif

                    @if($tab === 'instituciones')
                        <div>
                            <label class="form-label">Código Institucional (Ej: 2322)</label>
                            <input type="text" wire:model="codigo" class="form-input" required maxlength="4">
                            @error('codigo') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    @if($tab === 'unidades_organizativas')
                        <div>
                            <label class="form-label">Institución Padre</label>
                            <select wire:model="institucion_id" class="form-select" required>
                                <option value="">Seleccione...</option>
                                @foreach($instituciones as $inst)
                                    <option value="{{ $inst->id }}">{{ $inst->nombre }}</option>
                                @endforeach
                            </select>
                            @error('institucion_id') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="form-label">Código de Departamento (Ej: 5676)</label>
                            <input type="text" wire:model="codigo" class="form-input" required maxlength="4">
                            @error('codigo') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    {{-- Estado Activo/Inactivo --}}
                    @if(in_array($tab, ['bodegas', 'categorias', 'marcas']))
                        <div>
                            <label class="form-label" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                                <input type="checkbox" wire:model="activa">
                                Registro Activo
                            </label>
                        </div>
                    @endif
                    
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
