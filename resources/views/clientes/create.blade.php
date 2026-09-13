<x-app-layout>
    <x-slot name="title">Nuevo Cliente</x-slot>

    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Registrar Nuevo Cliente</h1>
            <p class="page-subtitle">Alta de cliente natural o jurídico con perfil crediticio y capacidad de pago.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('clientes.index') }}" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                <span>Volver al Directorio</span>
            </a>
        </div>
    </div>

    <div class="card" style="max-width: 960px;" x-data="{ tipoCliente: '{{ old('tipo', 'natural') }}' }">
        <form action="{{ route('clientes.store') }}" method="POST">
            @csrf

            <div class="card-body" style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                {{-- Selector Tipo de Persona --}}
                <div style="background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: var(--radius-md); padding: 1.25rem;">
                    <label class="form-label" style="font-size: 0.9375rem; font-weight: 700; margin-bottom: 0.75rem;">
                        Tipo de Persona / Entidad <span class="required">*</span>
                    </label>
                    <div style="display: flex; gap: 2rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                            <input type="radio" name="tipo" value="natural" x-model="tipoCliente" style="width: 18px; height: 18px; accent-color: var(--primary-600);">
                            <span>Persona Natural</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                            <input type="radio" name="tipo" value="juridica" x-model="tipoCliente" style="width: 18px; height: 18px; accent-color: var(--primary-600);">
                            <span>Persona Jurídica (Empresa)</span>
                        </label>
                    </div>
                </div>

                {{-- Datos Generales --}}
                <div>
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        Información de Identificación
                    </h3>
                    <div class="grid-2">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">
                                <span x-text="tipoCliente === 'natural' ? 'Nombre Completo' : 'Razón Social'"></span>
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="nombre" class="form-input @error('nombre') error @enderror" value="{{ old('nombre') }}" required placeholder="Ej: Juan Carlos Pérez o Corporación Industrial S.A. de C.V.">
                            @error('nombre') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group" x-show="tipoCliente === 'natural'">
                            <label class="form-label">DUI (El Salvador)</label>
                            <input type="text" name="dui" class="form-input @error('dui') error @enderror" value="{{ old('dui') }}" placeholder="00000000-0">
                            @error('dui') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">NIT</label>
                            <input type="text" name="nit" class="form-input @error('nit') error @enderror" value="{{ old('nit') }}" placeholder="0000-000000-000-0">
                            @error('nit') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group" x-show="tipoCliente === 'juridica'">
                            <label class="form-label">NRC (Registro de Contribuyente)</label>
                            <input type="text" name="nrc" class="form-input @error('nrc') error @enderror" value="{{ old('nrc') }}" placeholder="Ej: 123456-7">
                            @error('nrc') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group" x-show="tipoCliente === 'natural'">
                            <label class="form-label">Estado Civil</label>
                            <select name="estado_civil" class="form-select">
                                <option value="">Seleccione...</option>
                                <option value="soltero" {{ old('estado_civil') == 'soltero' ? 'selected' : '' }}>Soltero(a)</option>
                                <option value="casado" {{ old('estado_civil') == 'casado' ? 'selected' : '' }}>Casado(a)</option>
                                <option value="divorciado" {{ old('estado_civil') == 'divorciado' ? 'selected' : '' }}>Divorciado(a)</option>
                                <option value="viudo" {{ old('estado_civil') == 'viudo' ? 'selected' : '' }}>Viudo(a)</option>
                                <option value="union_libre" {{ old('estado_civil') == 'union_libre' ? 'selected' : '' }}>Unión Libre</option>
                            </select>
                        </div>

                        <div class="form-group" x-show="tipoCliente === 'natural'">
                            <label class="form-label">Lugar de Trabajo / Actividad</label>
                            <input type="text" name="lugar_trabajo" class="form-input" value="{{ old('lugar_trabajo') }}" placeholder="Empresa o Negocio propio">
                        </div>
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
                            <input type="text" name="telefono" class="form-input" value="{{ old('telefono') }}" placeholder="2200-0000">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Celular</label>
                            <input type="text" name="celular" class="form-input" value="{{ old('celular') }}" placeholder="7000-0000">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" name="email" class="form-input" value="{{ old('email') }}" placeholder="cliente@ejemplo.com">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Departamento</label>
                            <input type="text" name="departamento" class="form-input" value="{{ old('departamento', 'San Salvador') }}">
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Dirección Completa</label>
                            <textarea name="direccion" class="form-input" rows="2" placeholder="Calle, colonia, número de casa, punto de referencia...">{{ old('direccion') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Datos Jurídicos Adicionales --}}
                <div x-show="tipoCliente === 'juridica'" style="padding-top: 1rem; border-top: 1px solid var(--neutral-200);">
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        Información Corporativa y Representación Legal
                    </h3>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Nombre Comercial</label>
                            <input type="text" name="nombre_comercial" class="form-input" value="{{ old('nombre_comercial') }}" placeholder="Ej: Industrias del Café">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Giro Económico</label>
                            <input type="text" name="giro" class="form-input" value="{{ old('giro') }}" placeholder="Ej: Venta de materiales de construcción">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Representante Legal</label>
                            <input type="text" name="representante_legal" class="form-input" value="{{ old('representante_legal') }}" placeholder="Nombre del apoderado o representante">
                        </div>

                        <div class="form-group">
                            <label class="form-label">DUI del Representante</label>
                            <input type="text" name="dui_representante" class="form-input" value="{{ old('dui_representante') }}" placeholder="00000000-0">
                        </div>
                    </div>
                </div>

                {{-- Perfil Financiero y Asignación --}}
                <div style="padding-top: 1rem; border-top: 1px solid var(--neutral-200);">
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        Capacidad de Pago y Cartera
                    </h3>
                    <div class="grid-3">
                        <div class="form-group">
                            <label class="form-label">Ingresos Mensuales ($)</label>
                            <input type="number" step="0.01" name="ingresos" class="form-input" value="{{ old('ingresos', 0) }}" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Egresos Mensuales ($)</label>
                            <input type="number" step="0.01" name="egresos" class="form-input" value="{{ old('egresos', 0) }}" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Zona Geográfica</label>
                            <select name="zona_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($zonas as $z)
                                    <option value="{{ $z->id }}" {{ old('zona_id') == $z->id ? 'selected' : '' }}>{{ $z->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Gestor / Asesor</label>
                            <select name="vendedor_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($vendedores as $v)
                                    <option value="{{ $v->id }}" {{ old('vendedor_id') == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cartera</label>
                            <select name="cartera_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($carteras as $c)
                                    <option value="{{ $c->id }}" {{ old('cartera_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

            </div>

            <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="{{ route('clientes.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                        <polyline points="17 21 17 13 7 13 7 21"/>
                        <polyline points="7 3 7 8 15 8"/>
                    </svg>
                    <span>Guardar Cliente</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
