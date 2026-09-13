<x-app-layout>
    <x-slot name="title">Directorio de Clientes</x-slot>

    {{-- Encabezado de Página --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Directorio de Clientes</h1>
            <p class="page-subtitle">Padrón de personas naturales y jurídicas, calificación de riesgo y créditos activos.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('clientes.create') }}" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Nuevo Cliente</span>
            </a>
        </div>
    </div>

    {{-- KPIs Resumen de Clientes --}}
    <div class="grid-stats" style="margin-bottom: 1.5rem;">
        {{-- Total Clientes --}}
        <div class="stat-card" style="--stat-color: var(--primary-600); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Total Clientes Registrados</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">{{ $clientes->total() }}</div>
            <div class="stat-card-change up">
                Expedientes activos en el sistema
            </div>
        </div>

        {{-- Clientes Naturales --}}
        <div class="stat-card" style="--stat-color: var(--info-500); --stat-bg: var(--info-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Personas Naturales</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--info-600);">
                {{ \App\Models\Cliente::where('tipo', 'natural')->count() }}
            </div>
            <div class="stat-card-change" style="background: var(--info-50); color: var(--info-700);">
                Asalariados e independientes
            </div>
        </div>

        {{-- Clientes Jurídicos --}}
        <div class="stat-card" style="--stat-color: var(--accent-500); --stat-bg: var(--warning-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Personas Jurídicas (Empresas)</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--accent-600);">
                {{ \App\Models\Cliente::where('tipo', 'juridica')->count() }}
            </div>
            <div class="stat-card-change" style="background: var(--warning-50); color: var(--warning-700);">
                Sociedades y corporativos
            </div>
        </div>
    </div>

    {{-- Filtros y Búsqueda --}}
    <div class="card mb-4" style="margin-bottom: 1.5rem;">
        <div class="card-body" style="padding: 1.25rem 1.5rem;">
            <form action="{{ route('clientes.index') }}" method="GET" style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                <div style="display: flex; gap: 1rem; align-items: center; flex: 1; min-width: 280px;">
                    <div style="position: relative; flex: 1; max-width: 420px;">
                        <input type="text"
                               name="buscar"
                               class="form-input"
                               placeholder="Buscar por nombre, código o DUI..."
                               value="{{ request('buscar') }}"
                               style="padding-left: 2.25rem;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                             style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: var(--neutral-400); pointer-events: none;">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </div>

                    <select name="tipo" class="form-select" style="max-width: 180px;">
                        <option value="">Todos los Tipos</option>
                        <option value="natural" {{ request('tipo') == 'natural' ? 'selected' : '' }}>Natural</option>
                        <option value="juridica" {{ request('tipo') == 'juridica' ? 'selected' : '' }}>Jurídica</option>
                    </select>

                    <select name="clasificacion" class="form-select" style="max-width: 180px;">
                        <option value="">Clasificación (A-E)</option>
                        @foreach(['A', 'B', 'C', 'D', 'E'] as $c)
                            <option value="{{ $c }}" {{ request('clasificacion') == $c ? 'selected' : '' }}>Categoría {{ $c }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <button type="submit" class="btn btn-secondary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <span>Filtrar</span>
                    </button>

                    @if(request()->anyFilled(['buscar', 'tipo', 'clasificacion']))
                        <a href="{{ route('clientes.index') }}" class="btn btn-ghost" style="color: var(--neutral-500);">Limpiar</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla de Clientes --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Listado de Expedientes</h3>
            <div style="font-size: 0.8125rem; color: var(--neutral-500);">
                Total: <strong>{{ $clientes->total() }}</strong> clientes
            </div>
        </div>

        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="min-width: 140px;">Código</th>
                            <th style="min-width: 240px;">Cliente / Razón Social</th>
                            <th style="min-width: 110px;">Tipo</th>
                            <th style="min-width: 140px;">Zona Asignada</th>
                            <th style="min-width: 130px;">Calificación</th>
                            <th class="text-center" style="min-width: 120px; text-align: center;">Créditos Activos</th>
                            <th class="text-center" style="width: 140px; text-align: center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clientes as $cliente)
                        <tr>
                            <td>
                                <span style="font-family: monospace; font-weight: 700; font-size: 0.875rem; color: var(--neutral-800); background: var(--neutral-100); padding: 0.2rem 0.5rem; border-radius: var(--radius-sm); letter-spacing: 0.03em;">
                                    {{ $cliente->codigo }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--neutral-900); font-size: 0.9375rem;">
                                    {{ $cliente->nombre }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--neutral-400); margin-top: 0.15rem;">
                                    {{ $cliente->dui ? 'DUI: ' . $cliente->dui : ($cliente->nit ? 'NIT: ' . $cliente->nit : 'Sin documento') }}
                                    @if($cliente->telefono) • Tel: {{ $cliente->telefono }} @endif
                                </div>
                            </td>
                            <td>
                                @if($cliente->tipo == 'natural')
                                    <span class="badge badge-info">NATURAL</span>
                                @else
                                    <span class="badge badge-warning">JURÍDICA</span>
                                @endif
                            </td>
                            <td style="color: var(--neutral-600); font-size: 0.875rem;">
                                {{ $cliente->zona?->nombre ?? 'Sin Zona' }}
                            </td>
                            <td>
                                @php
                                    $clasificaciones = config('erp.cobros.clasificacion');
                                    $info = $clasificaciones[$cliente->clasificacion_cobro] ?? ['color' => 'neutral'];
                                @endphp
                                <span class="badge badge-{{ $info['color'] }}" style="font-weight: 700;">
                                    Cat. {{ $cliente->clasificacion_cobro }}
                                </span>
                            </td>
                            <td style="text-align: center;">
                                @if($cliente->creditos_activos_count > 0)
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: var(--primary-100); color: var(--primary-700); font-weight: 700; font-size: 0.8125rem;">
                                        {{ $cliente->creditos_activos_count }}
                                    </span>
                                @else
                                    <span style="color: var(--neutral-400); font-size: 0.8125rem;">0</span>
                                @endif
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
                                    <a href="{{ route('clientes.show', $cliente) }}" class="btn btn-sm btn-secondary" title="Ver Expediente">
                                        Ver
                                    </a>
                                    <a href="{{ route('creditos.create', ['cliente_id' => $cliente->id]) }}" class="btn btn-sm btn-ghost" title="Otorgar Crédito" style="color: var(--success-600);">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <line x1="12" y1="5" x2="12" y2="19"/>
                                            <line x1="5" y1="12" x2="19" y2="12"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                    </svg>
                                    <div class="empty-state-title">No se encontraron clientes</div>
                                    <div class="empty-state-text">No hay expedientes que coincidan con los criterios de búsqueda.</div>
                                    <a href="{{ route('clientes.create') }}" class="btn btn-primary">
                                        + Registrar Primer Cliente
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($clientes->hasPages())
        <div class="card-footer" style="padding: 1rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
            <div style="font-size: 0.8125rem; color: var(--neutral-500);">
                Mostrando {{ $clientes->firstItem() ?? 0 }} a {{ $clientes->lastItem() ?? 0 }} de {{ $clientes->total() }} registros
            </div>
            <div>
                {{ $clientes->links() }}
            </div>
        </div>
        @endif
    </div>
</x-app-layout>

