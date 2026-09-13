<div>
    {{-- Encabezado de Página --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Inventario y Existencias</h1>
            <p class="page-subtitle">Control de stock multialmacén, costeo ponderado y alertas de reposición.</p>
        </div>
        <div class="page-actions">
            <button type="button"
                    wire:click="$dispatch('abrirModalMovimiento', { params: { tipo: 'entrada' } })"
                    class="btn btn-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Registrar Entrada</span>
            </button>

            <button type="button"
                    wire:click="$dispatch('abrirModalMovimiento', { params: { tipo: 'salida' } })"
                    class="btn btn-danger">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Registrar Salida</span>
            </button>

            <button type="button"
                    wire:click="$dispatch('abrirModalMovimiento', { params: { tipo: 'transferencia' } })"
                    class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="17 1 21 5 17 9"/>
                    <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                    <polyline points="7 23 3 19 7 15"/>
                    <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                </svg>
                <span>Transferencia</span>
            </button>
        </div>

    </div>

    {{-- KPIs Estadísticas --}}
    <div class="grid-stats">
        {{-- Valoración Total --}}
        <div class="stat-card" style="--stat-color: var(--primary-600); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Valoración Total del Stock</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">${{ number_format($valoracionTotal, 2) }}</div>
            <div class="stat-card-change up">
                Costo ponderado de existencias
            </div>
        </div>

        {{-- Alertas de Stock Bajo --}}
        <div class="stat-card" style="--stat-color: {{ $alertasStock > 0 ? 'var(--warning-500)' : 'var(--success-500)' }}; --stat-bg: {{ $alertasStock > 0 ? 'var(--warning-50)' : 'var(--success-50)' }};">
            <div class="stat-card-top">
                <div class="stat-card-label">Alertas de Stock Mínimo</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/>
                        <line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: {{ $alertasStock > 0 ? 'var(--warning-600)' : 'var(--success-600)' }};">
                {{ $alertasStock }}
            </div>
            <div class="stat-card-change {{ $alertasStock > 0 ? 'down' : 'up' }}">
                {{ $alertasStock > 0 ? 'Productos requieren reorden' : 'Existencias saludables' }}
            </div>
        </div>

        {{-- Caducidad Próxima --}}
        <div class="stat-card" style="--stat-color: {{ $alertasCaducidad > 0 ? 'var(--danger-500)' : 'var(--success-500)' }}; --stat-bg: {{ $alertasCaducidad > 0 ? 'var(--danger-50)' : 'var(--success-50)' }};">
            <div class="stat-card-top">
                <div class="stat-card-label">Vencimientos Próximos (30 días)</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: {{ $alertasCaducidad > 0 ? 'var(--danger-600)' : 'var(--success-600)' }};">
                {{ $alertasCaducidad }}
            </div>
            <div class="stat-card-change {{ $alertasCaducidad > 0 ? 'down' : 'up' }}">
                {{ $alertasCaducidad > 0 ? 'Lotes por expirar o vencidos' : 'Sin lotes en riesgo' }}
            </div>
        </div>
    </div>

    {{-- Barra de Búsqueda y Filtros --}}
    <div class="card mb-4" style="margin-bottom: 1.5rem;">
        <div class="card-body" style="padding: 1.25rem 1.5rem;">
            <div style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                <div style="display: flex; gap: 1rem; align-items: center; flex: 1; min-width: 280px;">
                    <div style="position: relative; flex: 1; max-width: 420px;">
                        <input type="text"
                               wire:model.live.debounce.300ms="buscar"
                               class="form-input"
                               placeholder="Buscar por código, nombre de producto..."
                               style="padding-left: 2.25rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--neutral-400); pointer-events: none;">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </div>

                    <select wire:model.live="bodega_id" class="form-select" style="max-width: 240px;">
                        <option value="">Todas las Bodegas</option>
                        @foreach($bodegas as $b)
                            <option value="{{ $b->id }}">{{ $b->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 1rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; color: var(--neutral-700);">
                        <input type="checkbox"
                               wire:model.live="mostrarAgotados"
                               style="width: 18px; height: 18px; accent-color: var(--primary-600);">
                        <span>Solo Agotados / Bajo Mínimo</span>
                    </label>

                    @if($buscar || $bodega_id || $mostrarAgotados)
                        <button type="button"
                                wire:click="$set('buscar', ''); $set('bodega_id', ''); $set('mostrarAgotados', false)"
                                class="btn btn-sm btn-ghost"
                                style="color: var(--neutral-500);">
                            Limpiar Filtros
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Tabla de Productos --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Catálogo de Productos y Existencias</h3>
            <div style="font-size: 0.8125rem; color: var(--neutral-500);">
                Total: <strong>{{ $productos->total() }}</strong> artículos
            </div>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="min-width: 140px;">Código</th>
                            <th style="min-width: 240px;">Producto y Detalles</th>
                            <th class="text-right" style="min-width: 130px; text-align: right;">Stock Actual</th>
                            <th class="text-center" style="min-width: 110px; text-align: center;">Mín / Máx</th>
                            <th class="text-right" style="min-width: 130px; text-align: right;">Costo Promedio</th>
                            <th class="text-right" style="min-width: 130px; text-align: right;">Valor Inventario</th>
                            <th style="min-width: 120px;">Estado</th>
                            <th class="text-center" style="width: 80px; text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($productos as $producto)
                        <tr wire:key="prod-{{ $producto->id }}">
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span style="font-family: monospace; font-weight: 700; font-size: 0.875rem; color: var(--neutral-800); background: var(--neutral-100); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); letter-spacing: 0.03em;">
                                        {{ $producto->codigo }}
                                    </span>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--neutral-900); font-size: 0.9375rem;">
                                    {{ $producto->nombre }}
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.2rem; font-size: 0.775rem; color: var(--neutral-500);">
                                    <span>{{ $producto->categoria?->nombre ?? 'Sin Categoría' }}</span>
                                    @if($producto->marca)
                                        <span>•</span>
                                        <span>{{ $producto->marca->nombre }}</span>
                                    @endif
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div style="font-weight: 700; font-size: 1rem; color: {{ $producto->stock_total <= 0 ? 'var(--danger-600)' : ($producto->stock_total <= $producto->stock_minimo ? 'var(--warning-600)' : 'var(--neutral-900)') }};">
                                    {{ number_format($producto->stock_total, 2) }}
                                    <span style="font-size: 0.75rem; font-weight: 500; color: var(--neutral-500);">{{ $producto->unidadMedida->abreviatura ?? 'u' }}</span>
                                </div>
                            </td>
                            <td style="text-align: center; font-size: 0.8125rem; color: var(--neutral-600);">
                                <span style="font-weight: 600;">{{ number_format($producto->stock_minimo, 0) }}</span>
                                <span style="color: var(--neutral-400);">/</span>
                                <span>{{ $producto->stock_maximo ? number_format($producto->stock_maximo, 0) : '∞' }}</span>
                            </td>
                            <td style="text-align: right; font-weight: 500; color: var(--neutral-700);">
                                ${{ number_format($producto->costo_unitario, 4) }}
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--primary-700);">
                                ${{ number_format($producto->stock_total * $producto->costo_unitario, 2) }}
                            </td>
                            <td>
                                @if($producto->stock_total <= 0)
                                    <span class="badge badge-danger">SIN STOCK</span>
                                @elseif($producto->stock_total <= $producto->stock_minimo)
                                    <span class="badge badge-warning">BAJO MÍNIMO</span>
                                @else
                                    <span class="badge badge-success">DISPONIBLE</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                <button type="button"
                                        wire:click="$dispatch('abrirModalMovimiento', { params: { tipo: 'entrada', producto_id: {{ $producto->id }} } })"
                                        class="btn btn-sm btn-ghost"
                                        title="Registrar entrada rápida de este producto">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <line x1="12" y1="5" x2="12" y2="19"/>
                                        <line x1="5" y1="12" x2="19" y2="12"/>
                                    </svg>
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="m7.5 4.27 9 5.15"/>
                                        <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                        <path d="m3.3 7 8.7 5 8.7-5"/>
                                        <path d="M12 22V12"/>
                                    </svg>
                                    <div class="empty-state-title">No se encontraron productos</div>
                                    <div class="empty-state-text">No hay coincidencias con los filtros aplicados o no hay productos dados de alta.</div>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($productos->hasPages())
        <div class="card-footer" style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.8125rem; color: var(--neutral-500);">
                Mostrando {{ $productos->firstItem() ?? 0 }} a {{ $productos->lastItem() ?? 0 }} de {{ $productos->total() }} registros
            </div>
            <div>
                {{ $productos->links() }}
            </div>
        </div>
        @endif
    </div>

    {{-- Modal Reactivo de Movimientos --}}
    @livewire('movimiento-inventario-modal')
</div>

