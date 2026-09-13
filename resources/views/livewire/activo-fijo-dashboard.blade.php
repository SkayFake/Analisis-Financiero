<div>
    {{-- Breadcrumbs & Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Activo Fijo (LISR)</h1>
            <p class="page-subtitle">Control patrimonial, asignación institucional y depreciación fiscal Art. 30 LISR.</p>
        </div>
        <div class="page-actions">
            <button wire:click="simularMes"
                    wire:loading.attr="disabled"
                    class="btn btn-secondary"
                    title="Ejecutar cálculo mensual de depreciación fiscal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" wire:loading.class="animate-spin">
                    <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                    <path d="M21 3v5h-5"/>
                </svg>
                <span wire:loading.remove wire:target="simularMes">Correr Depreciación</span>
                <span wire:loading wire:target="simularMes">Calculando...</span>
            </button>

            <button wire:click="abrirModalRegistro"
                    class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Nuevo Activo</span>
            </button>
        </div>
    </div>

    {{-- KPIs Summary Cards --}}
    <div class="grid-stats">
        {{-- Total Adquisición --}}
        <div class="stat-card" style="--stat-color: var(--primary-600); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Valor Histórico (Adquisición)</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">${{ number_format($totalAdquisicion, 2) }}</div>
            <div class="stat-card-change" style="background: var(--neutral-100); color: var(--neutral-600);">
                Total bienes registrados
            </div>
        </div>

        {{-- Depreciación Acumulada --}}
        <div class="stat-card" style="--stat-color: var(--danger-500); --stat-bg: var(--danger-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Depreciación Acumulada</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
                        <polyline points="17 18 23 18 23 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--danger-600);">- ${{ number_format($totalDepreciacionAcumulada, 2) }}</div>
            <div class="stat-card-change down">
                Deducción fiscal acumulada
            </div>
        </div>

        {{-- Valor Neto en Libros --}}
        <div class="stat-card" style="--stat-color: var(--success-500); --stat-bg: var(--success-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Valor Neto en Libros</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--primary-700);">${{ number_format($valorNetoLibros, 2) }}</div>
            <div class="stat-card-change up">
                Valor residual contable actual
            </div>
        </div>
    </div>

    {{-- Data Table Card --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Inventario de Bienes y Activos</h3>
            <div style="font-size: 0.8125rem; color: var(--neutral-500);">
                Total: <strong>{{ count($activos) }}</strong> activos
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="min-width: 170px;">Código Institucional</th>
                            <th style="min-width: 240px;">Descripción y Categoría</th>
                            <th class="text-right" style="min-width: 130px; text-align: right;">Costo Adquisición</th>
                            <th class="text-right" style="min-width: 130px; text-align: right;">Depr. Acumulada</th>
                            <th class="text-right" style="min-width: 130px; text-align: right;">Valor en Libros</th>
                            <th style="min-width: 110px;">Estado</th>
                            <th class="text-center" style="width: 100px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activos as $a)
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--primary-500);"></div>
                                    <span style="font-family: monospace; font-weight: 700; font-size: 0.875rem; color: var(--neutral-800); letter-spacing: 0.04em;">
                                        {{ $a->codigo_inventario }}
                                    </span>
                                </div>
                                <div style="font-size: 0.75rem; color: var(--neutral-400); margin-left: 1rem;">
                                    {{ $a->unidad?->institucion?->nombre ?? 'Institución' }} - {{ $a->unidad?->nombre ?? 'Unidad' }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--neutral-800); font-size: 0.9375rem;">
                                    {{ $a->nombre }}
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                                    <span style="font-size: 0.775rem; color: var(--neutral-500);">
                                        {{ $a->categoria->nombre }} ({{ number_format($a->categoria->porcentaje_depreciacion, 0) }}% anual)
                                    </span>
                                    @if($a->es_usado)
                                        <span class="badge badge-warning" style="font-size: 0.65rem;">
                                            Usado ({{ $a->anios_uso_previo }} {{ $a->anios_uso_previo == 1 ? 'año' : 'años' }})
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 600; color: var(--neutral-800);">
                                ${{ number_format($a->valor_adquisicion, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 600; color: var(--danger-600);">
                                -${{ number_format($a->depreciacion_acumulada, 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--primary-700);">
                                ${{ number_format($a->valor_adquisicion - $a->depreciacion_acumulada, 2) }}
                            </td>
                            <td>
                                @php
                                    $estadoMap = [
                                        'activo' => ['class' => 'badge-success', 'label' => 'ACTIVO'],
                                        'depreciado' => ['class' => 'badge-warning', 'label' => 'DEPRECIADO'],
                                        'vendido' => ['class' => 'badge-neutral', 'label' => 'VENDIDO'],
                                        'donado' => ['class' => 'badge-info', 'label' => 'DONADO'],
                                        'botado' => ['class' => 'badge-danger', 'label' => 'BOTADO'],
                                    ];
                                    $estadoInfo = $estadoMap[$a->estado] ?? ['class' => 'badge-neutral', 'label' => strtoupper($a->estado)];
                                @endphp
                                <span class="badge {{ $estadoInfo['class'] }}">
                                    {{ $estadoInfo['label'] }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                @if(in_array($a->estado, ['activo', 'depreciado']))
                                    <button wire:click="abrirModalBaja({{ $a->id }})"
                                            class="btn btn-sm btn-ghost"
                                            style="color: var(--danger-600);"
                                            title="Desincorporar o dar de baja">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="3 6 5 6 21 6"/>
                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        </svg>
                                        <span>Baja</span>
                                    </button>
                                @else
                                    <span style="font-size: 0.75rem; color: var(--neutral-400); font-style: italic;">
                                        {{ ucfirst($a->motivo_baja ?? 'Inactivo') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M2 20a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8l-7 5V8l-7 5V4a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                                    </svg>
                                    <div class="empty-state-title">No hay activos registrados</div>
                                    <div class="empty-state-text">Comienza registrando tu primer bien para el control patrimonial y cálculo de depreciación fiscal.</div>
                                    <button wire:click="abrirModalRegistro" class="btn btn-primary">
                                        + Registrar Primer Activo
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════
         MODAL DE REGISTRO DE ACTIVO FIJO
         ═══════════════════════════════════════════════════════════ --}}
    @if($mostrarModalRegistro)
    <div class="modal-overlay" wire:keydown.escape="$set('mostrarModalRegistro', false)">
        <div class="modal" style="max-width: 640px;">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Registrar Nuevo Activo Fijo</h3>
                    <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                        Generación automática de código institucional y cálculo de depreciación LISR.
                    </p>
                </div>
                <button type="button" class="modal-close" wire:click="$set('mostrarModalRegistro', false)" title="Cerrar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <form wire:submit.prevent="registrarActivo">
                <div class="modal-body" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    
                    {{-- Grid 1: Unidad & Categoría --}}
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Unidad Institucional <span class="required">*</span></label>
                            <select wire:model="form_unidad_id" class="form-select" required>
                                <option value="">Seleccione Unidad...</option>
                                @foreach($unidades as $u)
                                    <option value="{{ $u->id }}">
                                        {{ $u->institucion->codigo }}-{{ $u->codigo }} — {{ $u->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            @error('form_unidad_id') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Categoría LISR (Art. 30) <span class="required">*</span></label>
                            <select wire:model="form_categoria_id" class="form-select" required>
                                <option value="">Seleccione Categoría...</option>
                                @foreach($categorias as $c)
                                    <option value="{{ $c->id }}">
                                        {{ $c->nombre }} ({{ number_format($c->porcentaje_depreciacion, 0) }}% anual)
                                    </option>
                                @endforeach
                            </select>
                            @error('form_categoria_id') <span class="form-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Nombre del Bien --}}
                    <div class="form-group">
                        <label class="form-label">Descripción / Nombre del Bien <span class="required">*</span></label>
                        <input type="text"
                               wire:model="form_nombre"
                               class="form-input"
                               placeholder="Ej: Computadora Laptop Dell Latitude 5420 o Maquinaria CNC..."
                               required>
                        @error('form_nombre') <span class="form-error">{{ $message }}</span> @enderror
                    </div>

                    {{-- Grid 2: Valores & Fechas --}}
                    <div class="grid-3">
                        <div class="form-group">
                            <label class="form-label">Fecha Adquisición <span class="required">*</span></label>
                            <input type="date"
                                   wire:model="form_fecha_adquisicion"
                                   class="form-input"
                                   required>
                            @error('form_fecha_adquisicion') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Valor Compra ($) <span class="required">*</span></label>
                            <input type="number"
                                   wire:model="form_valor_adquisicion"
                                   class="form-input"
                                   step="0.01"
                                   min="0.01"
                                   placeholder="0.00"
                                   required>
                            @error('form_valor_adquisicion') <span class="form-error">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Valor Desecho ($)</label>
                            <input type="number"
                                   wire:model="form_valor_residual"
                                   class="form-input"
                                   step="0.01"
                                   min="0"
                                   placeholder="0.00">
                        </div>
                    </div>

                    {{-- Bloque LISR: Bien Usado --}}
                    <div style="background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: var(--radius-md); padding: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.625rem; font-weight: 600; cursor: pointer; color: var(--neutral-800);">
                            <input type="checkbox"
                                   wire:model.live="form_es_usado"
                                   style="width: 18px; height: 18px; accent-color: var(--primary-600);">
                            <span>¿Es un bien adquirido usado? (Regulación Art. 30 LISR)</span>
                        </label>
                        <p style="font-size: 0.775rem; color: var(--neutral-500); margin-top: 0.35rem; margin-left: 1.75rem;">
                            La ley salvadoreña limita la base depreciable según los años de uso previo comprobables.
                        </p>

                        @if($form_es_usado)
                            <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--neutral-200);">
                                <label class="form-label">Años de uso previo comprobable:</label>
                                <select wire:model="form_anios_uso" class="form-select" required>
                                    <option value="">Seleccione rango de años...</option>
                                    <option value="1">1 año de uso previo (Sujeto al 80% del valor)</option>
                                    <option value="2">2 años de uso previo (Sujeto al 60% del valor)</option>
                                    <option value="3">3 años de uso previo (Sujeto al 40% del valor)</option>
                                    <option value="4">4 años o más de uso previo (Sujeto al 20% del valor)</option>
                                </select>
                                @error('form_anios_uso') <span class="form-error">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button"
                            class="btn btn-secondary"
                            wire:click="$set('mostrarModalRegistro', false)">
                        Cancelar
                    </button>
                    <button type="submit"
                            wire:loading.attr="disabled"
                            class="btn btn-primary">
                        <span wire:loading.remove wire:target="registrarActivo">Registrar y Asignar Código</span>
                        <span wire:loading wire:target="registrarActivo">Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════
         MODAL DE DESINCORPORACIÓN / BAJA
         ═══════════════════════════════════════════════════════════ --}}
    @if($mostrarModalBaja)
    <div class="modal-overlay" wire:keydown.escape="$set('mostrarModalBaja', false)">
        <div class="modal" style="max-width: 480px;">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" style="color: var(--danger-600);">Desincorporar Activo</h3>
                    <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                        Registro legal de baja según Art. 30 LISR.
                    </p>
                </div>
                <button type="button" class="modal-close" wire:click="$set('mostrarModalBaja', false)" title="Cerrar">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div class="alert alert-warning" style="margin-bottom: 0.5rem;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span>Esta acción cesará las depreciaciones fiscales futuras del activo y afectará la cuenta patrimonial.</span>
                </div>

                <div class="form-group">
                    <label class="form-label">Motivo Legal de Desincorporación <span class="required">*</span></label>
                    <select wire:model="baja_motivo" class="form-select" required>
                        <option value="vendido">Venta del Bien (Transferencia a terceros)</option>
                        <option value="donado">Donación Institucional</option>
                        <option value="botado">Destrucción / Pérdida / Obsoleto (Botado)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Observaciones y Justificación Técnica</label>
                    <textarea wire:model="baja_detalles"
                              class="form-input"
                              rows="3"
                              placeholder="Número de acta, comprador, motivo de daño irreparable, etc."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-secondary"
                        wire:click="$set('mostrarModalBaja', false)">
                    Cancelar
                </button>
                <button wire:click="confirmarBaja"
                        wire:loading.attr="disabled"
                        type="button"
                        class="btn btn-danger">
                    <span wire:loading.remove wire:target="confirmarBaja">Confirmar Desincorporación</span>
                    <span wire:loading wire:target="confirmarBaja">Procesando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>

