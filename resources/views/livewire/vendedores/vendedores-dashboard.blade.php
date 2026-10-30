<div>
    <div class="page-header">
        <div><h1 class="page-title">Control de vendedores</h1><p class="page-subtitle">Ventas, metas mensuales y responsabilidad de cobro en una misma ficha.</p></div>
        <div class="page-actions">
            @can('export', \App\Models\Vendedor::class)
                <a class="btn btn-secondary" href="{{ route('vendedores.exportar', ['mes' => $periodo->format('Y-m')]) }}">Exportar todos CSV</a>
            @endcan
            @can('manage', \App\Models\Vendedor::class)<button wire:click="create" class="btn btn-primary">Nuevo vendedor</button>@endcan
        </div>
    </div>
    @if(!$showModal) @include('components.errores-validacion') @endif
    <form wire:submit="filtrar" class="card" style="margin-bottom:1.25rem;">
        <div class="card-body" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">
            <div><label class="form-label" for="mes-vendedores">Mes de ventas y cobros</label><input id="mes-vendedores" type="month" min="2000-01" max="2100-12" wire:model="mes" class="form-input" required></div>
            <div><label class="form-label" for="buscar-vendedores">Buscar</label><input id="buscar-vendedores" wire:model="buscar" maxlength="150" class="form-input" placeholder="Código o nombre"></div>
            <div><label class="form-label" for="estado-vendedores">Estado</label><select id="estado-vendedores" wire:model="estado" class="form-select"><option value="todos">Todos</option><option value="activos">Activos</option><option value="inactivos">Inactivos</option></select></div>
            <div><label class="form-label" for="zona-vendedores">Zona</label><select id="zona-vendedores" wire:model="zona" class="form-select"><option value="">Todas</option>@foreach($zonas as $z)<option value="{{ $z->id }}">{{ $z->nombre }}</option>@endforeach</select></div>
            <button type="submit" class="btn btn-primary">Aplicar</button>
        </div>
    </form>
    <div class="grid-stats" style="margin-bottom:1.25rem;">
        <div class="stat-card"><div class="stat-card-label">Ventas sin IVA · {{ $periodo->format('m/Y') }}</div><div class="stat-card-value">${{ number_format($resumenes->sum('ventas'),2) }}</div><div>{{ $resumenes->sum('documentos') }} documentos · vendedores filtrados</div></div>
        <div class="stat-card"><div class="stat-card-label">Cobros de créditos · {{ $periodo->format('m/Y') }}</div><div class="stat-card-value">${{ number_format($resumenes->sum('cobros'),2) }}</div><div>Recibos del mes, sin sumar ventas al contado</div></div>
        <div class="stat-card"><div class="stat-card-label">Cuotas vencidas actuales</div><div class="stat-card-value">${{ number_format($resumenes->sum('vencido'),2) }}</div><div>Capital, interés y mora pendientes en cuotas vencidas</div></div>
        <div class="stat-card"><div class="stat-card-label">Capital incobrable actual</div><div class="stat-card-value">${{ number_format($resumenes->sum('incobrable'),2) }}</div><div>Incluido en la cartera, conserva su deuda</div></div>
    </div>
    @if($incidencias['ventas_sin_vendedor']+$incidencias['cobros_sin_vendedor']+$incidencias['cartera_sin_responsable']+$incidencias['cobros_pendientes_revision']>0)
    <div class="alert alert-warning">
        <strong>Datos pendientes de revisión:</strong> {{ $incidencias['ventas_sin_vendedor'] }} ventas y {{ $incidencias['cobros_sin_vendedor'] }} recibos del mes sin vendedor; {{ $incidencias['cartera_sin_responsable'] }} créditos abiertos sin responsable.
        {{ $incidencias['cobros_pendientes_revision'] }} atribuciones históricas de recibos del mes siguen pendientes de revisión.
        <a href="{{ route('vendedores.pendientes', ['mes' => $periodo->format('Y-m')]) }}">Revisar asignaciones</a>
    </div>
    @endif
    <div class="card"><div class="data-table-wrapper">
        <table class="data-table" aria-label="Rendimiento de vendedores">
            <thead><tr><th>Vendedor</th><th>Ventas / meta del mes</th><th>Cobros / meta del mes</th><th>Cartera actual</th><th>Riesgo actual</th><th>Acciones</th></tr></thead>
            <tbody>
            @forelse($vendedores as $vendedor)
                @php($r=$resumenes->get($vendedor->id))
                <tr>
                    <td><a href="{{ route('vendedores.show', [$vendedor, 'mes' => $periodo->format('Y-m')]) }}"><strong>{{ $vendedor->nombre }}</strong></a><div>{{ $vendedor->codigo }} · {{ $vendedor->zona?->nombre ?? 'Sin zona' }}</div><span class="badge {{ $vendedor->activo ? 'badge-success' : 'badge-neutral' }}">{{ $vendedor->activo ? 'Activo' : 'Inactivo' }}</span></td>
                    <td>${{ number_format($r['ventas'],2) }} / ${{ number_format($r['meta_ventas'],2) }}<div>{{ $r['avance_ventas'] !== null ? number_format($r['avance_ventas'],1).'% · '.($r['avance_ventas'] >= 100 ? 'Meta cumplida' : 'Pendiente') : ($r['meta_definida'] ? 'Sin objetivo de ventas' : 'Sin meta registrada') }}</div>@if($r['avance_ventas'] !== null)<progress max="100" value="{{ min(100,$r['avance_ventas']) }}" aria-label="Cumplimiento de ventas de {{ $vendedor->nombre }}"></progress>@endif</td>
                    <td>${{ number_format($r['cobros'],2) }} / ${{ number_format($r['meta_cobros'],2) }}<div>{{ $r['avance_cobros'] !== null ? number_format($r['avance_cobros'],1).'% · '.($r['avance_cobros'] >= 100 ? 'Meta cumplida' : 'Pendiente') : 'Sin objetivo de cobros' }}</div></td>
                    <td>Capital: ${{ number_format($r['cartera_capital'],2) }}<div>{{ $r['creditos'] }} créditos abiertos</div></td>
                    <td>Vencido: ${{ number_format($r['vencido'],2) }}<div>Incobrable (capital): ${{ number_format($r['incobrable'],2) }}</div><div>{{ $r['clientes_morosos'] }} clientes con atraso</div></td>
                    <td><a class="btn btn-sm btn-primary" href="{{ route('vendedores.show', [$vendedor, 'mes' => $periodo->format('Y-m')]) }}">Ver control</a>@can('manage', \App\Models\Vendedor::class)<button wire:click="edit({{ $vendedor->id }})" class="btn btn-sm btn-secondary">Editar / desactivar</button>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="6">No hay vendedores que coincidan con los filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div><div class="card-body">{{ $vendedores->links() }}</div></div>
    <p style="margin-top:1rem;">Las ventas del mes corresponden a documentos comerciales válidos sin IVA. Los cobros son recibos de créditos atribuidos al responsable al recibirlos. La cartera corresponde a {{ now()->format('d/m/Y H:i') }}, aunque consulte un mes anterior. Los indicadores incluyen vendedores inactivos cuando coinciden con los filtros.</p>
    @if($showModal)
    <div class="modal-overlay" wire:keydown.escape="closeModal">
        <div class="modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3 class="modal-title">{{ $modalMode === 'create' ? 'Nuevo Vendedor' : 'Editar Vendedor' }}</h3>
                <button type="button" class="modal-close" wire:click="closeModal" title="Cerrar" aria-label="Cerrar">&times;</button>
            </div>
            <form wire:submit.prevent="save">
                <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                    @include('components.errores-validacion')
                    <div>
                        <label class="form-label">Código</label>
                        <input type="text" wire:model="codigo" class="form-input" required maxlength="20">
                        @error('codigo') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div>
                        <label class="form-label">Nombre Completo</label>
                        <input type="text" wire:model="nombre" class="form-input" required maxlength="255">
                        @error('nombre') <span class="text-danger text-sm">{{ $message }}</span> @enderror
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div>
                            <label class="form-label">Teléfono</label>
                            <input type="tel" wire:model="telefono" class="form-input" inputmode="tel" maxlength="14" pattern="(?:\+503[ \-]?)?[267][0-9]{3}[ \-]?[0-9]{4}" title="8 dígitos de El Salvador; opcional +503. Comienza con 2, 6 o 7.">
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" wire:model="email" class="form-input" maxlength="255">
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
                        @if($modalMode === 'create')<div>
                            <label class="form-label">Meta de ventas del mes actual ($ sin IVA)</label>
                            <input type="number" step="0.01" wire:model="meta_mensual" class="form-input" min="0" max="999999999999.99">
                        </div>@endif
                    </div>
                    @if($modalMode === 'edit')
                    <div><label class="form-label" for="motivo-vendedor">Motivo del cambio</label><textarea id="motivo-vendedor" wire:model="motivo" class="form-textarea" required minlength="10" maxlength="2000" rows="3"></textarea><p>Desactivar impide nuevas asignaciones; conserva ventas, créditos y cobros anteriores. Las metas se modifican en la ficha del vendedor.</p></div>
                    @endif
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
