<x-app-layout>
    <x-slot name="title">Editar Cliente: {{ $cliente->nombre }}</x-slot>

    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Editar Cliente: {{ $cliente->nombre }}</h1>
            <p class="page-subtitle">Actualización de expediente, datos de contacto y condiciones financieras.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('clientes.show', $cliente) }}" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                <span>Volver al Perfil</span>
            </a>
        </div>
    </div>

    <div class="card" style="max-width: 960px;">
        <form action="{{ route('clientes.update', $cliente) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card-body" style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                {{-- Código y Tipo --}}
                <div style="background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: var(--radius-md); padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <span style="font-size: 0.8125rem; color: var(--neutral-500);">Código Único Institucional:</span>
                        <span style="font-family: monospace; font-weight: 700; font-size: 1rem; color: var(--primary-700); margin-left: 0.5rem;">
                            {{ $cliente->codigo }}
                        </span>
                    </div>
                    <div>
                        <span class="badge badge-{{ $cliente->tipo === 'natural' ? 'info' : 'warning' }}">
                            {{ strtoupper($cliente->tipo) }}
                        </span>
                    </div>
                </div>

                {{-- Datos Generales --}}
                <div>
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        Identificación
                    </h3>
                    <div class="grid-2">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Nombre Completo o Razón Social <span class="required">*</span></label>
                            <input type="text" name="nombre" class="form-input @error('nombre') error @enderror" value="{{ old('nombre', $cliente->nombre) }}" required>
                            @error('nombre') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        @if($cliente->tipo === 'natural')
                        <div class="form-group">
                            <label class="form-label">DUI</label>
                            <input type="text" name="dui" class="form-input @error('dui') error @enderror" value="{{ old('dui', $cliente->dui) }}">
                            @error('dui') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                        @endif

                        <div class="form-group">
                            <label class="form-label">NIT</label>
                            <input type="text" name="nit" class="form-input @error('nit') error @enderror" value="{{ old('nit', $cliente->nit) }}">
                            @error('nit') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        @if($cliente->tipo === 'natural')
                        <div class="form-group">
                            <label class="form-label">Estado Civil</label>
                            <select name="estado_civil" class="form-select">
                                <option value="">Seleccione...</option>
                                <option value="soltero" {{ old('estado_civil', $cliente->estado_civil) == 'soltero' ? 'selected' : '' }}>Soltero(a)</option>
                                <option value="casado" {{ old('estado_civil', $cliente->estado_civil) == 'casado' ? 'selected' : '' }}>Casado(a)</option>
                                <option value="divorciado" {{ old('estado_civil', $cliente->estado_civil) == 'divorciado' ? 'selected' : '' }}>Divorciado(a)</option>
                                <option value="viudo" {{ old('estado_civil', $cliente->estado_civil) == 'viudo' ? 'selected' : '' }}>Viudo(a)</option>
                                <option value="union_libre" {{ old('estado_civil', $cliente->estado_civil) == 'union_libre' ? 'selected' : '' }}>Unión Libre</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Lugar de Trabajo / Actividad</label>
                            <input type="text" name="lugar_trabajo" class="form-input" value="{{ old('lugar_trabajo', $cliente->lugar_trabajo) }}">
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Contacto y Ubicación --}}
                <div style="padding-top: 1rem; border-top: 1px solid var(--neutral-200);">
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        Contacto y Ubicación
                    </h3>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Teléfono Fijo</label>
                            <input type="text" name="telefono" class="form-input" value="{{ old('telefono', $cliente->telefono) }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Celular</label>
                            <input type="text" name="celular" class="form-input" value="{{ old('celular', $cliente->celular) }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" name="email" class="form-input" value="{{ old('email', $cliente->email) }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Departamento</label>
                            <input type="text" name="departamento" class="form-input" value="{{ old('departamento', $cliente->departamento) }}">
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Dirección Completa</label>
                            <textarea name="direccion" class="form-input" rows="2">{{ old('direccion', $cliente->direccion) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Perfil Financiero y Asignación --}}
                <div style="padding-top: 1rem; border-top: 1px solid var(--neutral-200);">
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        Evaluación y Cartera
                    </h3>
                    <div class="grid-3">
                        <div class="form-group">
                            <label class="form-label">Ingresos Mensuales ($)</label>
                            <input type="number" step="0.01" name="ingresos" class="form-input" value="{{ old('ingresos', $cliente->ingresos) }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Egresos Mensuales ($)</label>
                            <input type="number" step="0.01" name="egresos" class="form-input" value="{{ old('egresos', $cliente->egresos) }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Zona Geográfica</label>
                            <select name="zona_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($zonas as $z)
                                    <option value="{{ $z->id }}" {{ old('zona_id', $cliente->zona_id) == $z->id ? 'selected' : '' }}>{{ $z->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Gestor / Asesor</label>
                            <select name="vendedor_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($vendedores as $v)
                                    <option value="{{ $v->id }}" {{ old('vendedor_id', $cliente->vendedor_id) == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cartera</label>
                            <select name="cartera_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($carteras as $c)
                                    <option value="{{ $c->id }}" {{ old('cartera_id', $cliente->cartera_id) == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

            </div>

            <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="{{ route('clientes.show', $cliente) }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    <span>Guardar Cambios</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
