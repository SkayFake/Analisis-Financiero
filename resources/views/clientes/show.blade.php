<x-app-layout>
    <x-slot name="title">Expediente: {{ $cliente->nombre }}</x-slot>

    {{-- Encabezado de Página --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">{{ $cliente->nombre }}</h1>
            <div class="page-subtitle" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span style="font-family: monospace; font-weight: 700; color: var(--primary-700); background: var(--primary-50); padding: 0.2rem 0.6rem; border-radius: var(--radius-sm);">
                    {{ $cliente->codigo }}
                </span>
                <span class="badge badge-{{ $cliente->tipo === 'natural' ? 'info' : 'warning' }}">
                    {{ strtoupper($cliente->tipo) }}
                </span>
                @php
                    $clasificaciones = config('erp.cobros.clasificacion');
                    $info = $clasificaciones[$cliente->clasificacion_cobro] ?? ['color' => 'neutral'];
                @endphp
                <span class="badge badge-{{ $info['color'] }}" style="font-weight: 700;">
                    Categoría {{ $cliente->clasificacion_cobro }}
                </span>
            </div>
        </div>
        <div class="page-actions">
            <a href="{{ route('creditos.create', ['cliente_id' => $cliente->id]) }}" class="btn btn-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"/>
                    <line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                <span>Nuevo Crédito</span>
            </a>

            <a href="{{ route('clientes.edit', $cliente) }}" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
                <span>Editar</span>
            </a>

            <a href="{{ route('clientes.index') }}" class="btn btn-ghost" style="color: var(--neutral-600);">
                <span>Volver</span>
            </a>
        </div>
    </div>

    {{-- Resumen Rápido de Finanzas del Cliente --}}
    <div class="grid-stats" style="margin-bottom: 1.5rem;">
        <div class="stat-card" style="--stat-color: var(--success-500); --stat-bg: var(--success-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Ingresos Mensuales</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--success-600);">${{ number_format($cliente->ingresos ?? 0, 2) }}</div>
            <div class="stat-card-change up">Ingresos comprobables</div>
        </div>

        <div class="stat-card" style="--stat-color: var(--danger-500); --stat-bg: var(--danger-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Egresos Declarados</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/>
                        <polyline points="17 18 23 18 23 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--danger-600);">${{ number_format($cliente->egresos ?? 0, 2) }}</div>
            <div class="stat-card-change down">Compromisos fijos</div>
        </div>

        <div class="stat-card" style="--stat-color: var(--primary-600); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Capacidad de Pago Mensual</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                        <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--primary-700);">${{ number_format($cliente->capacidad_pago ?? 0, 2) }}</div>
            <div class="stat-card-change" style="background: var(--neutral-100); color: var(--neutral-700);">Margen para nuevas cuotas</div>
        </div>

        <div class="stat-card" style="--stat-color: var(--accent-500); --stat-bg: var(--warning-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Créditos Registrados</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
                        <polyline points="14 2 14 8 20 8"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">{{ count($cliente->creditos) }}</div>
            <div class="stat-card-change" style="background: var(--warning-50); color: var(--warning-700);">Historial en cartera</div>
        </div>
    </div>

    {{-- Ficha del Expediente --}}
    <div class="grid-3" style="align-items: start; margin-bottom: 1.5rem;">
        {{-- Información de Contacto e Identificación --}}
        <div class="card" style="grid-column: span 2;">
            <div class="card-header">
                <h3 class="card-title">Expediente General</h3>
            </div>
            <div class="card-body">
                <div class="grid-2">
                    <div>
                        <div class="form-label" style="color: var(--neutral-400);">DUI (Documento Único)</div>
                        <div style="font-weight: 600; color: var(--neutral-800); font-size: 0.9375rem;">{{ $cliente->dui ?? 'No registrado' }}</div>
                    </div>
                    <div>
                        <div class="form-label" style="color: var(--neutral-400);">NIT</div>
                        <div style="font-weight: 600; color: var(--neutral-800); font-size: 0.9375rem;">{{ $cliente->nit ?? 'No registrado' }}</div>
                    </div>
                    <div>
                        <div class="form-label" style="color: var(--neutral-400);">Teléfono Fijo</div>
                        <div style="font-weight: 600; color: var(--neutral-800); font-size: 0.9375rem;">{{ $cliente->telefono ?? 'N/D' }}</div>
                    </div>
                    <div>
                        <div class="form-label" style="color: var(--neutral-400);">Celular</div>
                        <div style="font-weight: 600; color: var(--neutral-800); font-size: 0.9375rem;">{{ $cliente->celular ?? 'N/D' }}</div>
                    </div>
                    <div style="grid-column: span 2;">
                        <div class="form-label" style="color: var(--neutral-400);">Correo Electrónico</div>
                        <div style="font-weight: 600; color: var(--neutral-800); font-size: 0.9375rem;">{{ $cliente->email ?? 'No registrado' }}</div>
                    </div>
                    <div style="grid-column: span 2;">
                        <div class="form-label" style="color: var(--neutral-400);">Dirección Domiciliar</div>
                        <div style="font-weight: 500; color: var(--neutral-800);">{{ $cliente->direccion ?? 'Sin dirección registrada' }}</div>
                    </div>
                </div>

                @if($cliente->tipo === 'juridica' && $cliente->datosJuridicos)
                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--neutral-100);">
                    <h4 style="font-family: var(--font-heading); font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        Datos Corporativos y Estados Financieros
                    </h4>
                    <div class="grid-2">
                        <div>
                            <div class="form-label" style="color: var(--neutral-400);">Nombre Comercial</div>
                            <div style="font-weight: 600; color: var(--neutral-800);">{{ $cliente->datosJuridicos->nombre_comercial ?? 'N/D' }}</div>
                        </div>
                        <div>
                            <div class="form-label" style="color: var(--neutral-400);">Giro Comercial</div>
                            <div style="font-weight: 600; color: var(--neutral-800);">{{ $cliente->datosJuridicos->giro ?? 'N/D' }}</div>
                        </div>
                        <div>
                            <div class="form-label" style="color: var(--neutral-400);">Representante Legal</div>
                            <div style="font-weight: 600; color: var(--neutral-800);">{{ $cliente->datosJuridicos->representante_legal ?? 'N/D' }}</div>
                        </div>
                        <div>
                            <div class="form-label" style="color: var(--neutral-400);">DUI Representante</div>
                            <div style="font-weight: 600; color: var(--neutral-800);">{{ $cliente->datosJuridicos->dui_representante ?? 'N/D' }}</div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Asignación Operativa --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Asignación de Cartera</h3>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <div class="form-label" style="color: var(--neutral-400);">Zona Geográfica</div>
                    <div style="font-weight: 600; color: var(--neutral-900); font-size: 0.9375rem;">
                        {{ $cliente->zona?->nombre ?? 'Sin Zona Asignada' }}
                    </div>
                </div>
                <div>
                    <div class="form-label" style="color: var(--neutral-400);">Gestor / Vendedor Responsable</div>
                    <div style="font-weight: 600; color: var(--neutral-900); font-size: 0.9375rem;">
                        {{ $cliente->vendedor?->nombre ?? 'Sin Asesor Asignado' }}
                    </div>
                </div>
                <div>
                    <div class="form-label" style="color: var(--neutral-400);">Cartera de Crédito</div>
                    <div style="font-weight: 600; color: var(--neutral-900); font-size: 0.9375rem;">
                        {{ $cliente->cartera?->nombre ?? 'Cartera General' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Historial de Créditos --}}
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Historial de Créditos y Préstamos</h3>
            <div style="font-size: 0.8125rem; color: var(--neutral-500);">
                Total: <strong>{{ count($cliente->creditos) }}</strong> operaciones
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Número de Crédito</th>
                            <th>Producto Financiero</th>
                            <th class="text-right">Monto Original</th>
                            <th class="text-right">Saldo Actual</th>
                            <th>Estado</th>
                            <th>Fecha Desembolso</th>
                            <th class="text-center" style="width: 120px; text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cliente->creditos as $credito)
                        <tr>
                            <td>
                                <span style="font-family: monospace; font-weight: 700; font-size: 0.875rem; color: var(--neutral-800);">
                                    {{ $credito->numero }}
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--neutral-900);">{{ $credito->productoCredito->nombre }}</div>
                                <div style="font-size: 0.75rem; color: var(--neutral-500);">Tasa: {{ number_format($credito->tasa_interes, 2) }}% anual</div>
                            </td>
                            <td style="text-align: right; font-weight: 500;">${{ number_format($credito->monto_original, 2) }}</td>
                            <td style="text-align: right; font-weight: 700; color: var(--primary-700);">${{ number_format($credito->saldo_actual, 2) }}</td>
                            <td>
                                @php
                                    $colors = [
                                        'solicitado' => 'neutral',
                                        'vigente' => 'success',
                                        'vencido' => 'warning',
                                        'incobrable' => 'danger',
                                        'cancelado' => 'info'
                                    ];
                                @endphp
                                <span class="badge badge-{{ $colors[$credito->estado] ?? 'neutral' }}" style="font-weight: 700;">
                                    {{ strtoupper($credito->estado) }}
                                </span>
                            </td>
                            <td>{{ $credito->fecha_desembolso?->format('d/m/Y') ?? 'N/D' }}</td>
                            <td style="text-align: center;">
                                <a href="{{ route('creditos.show', $credito) }}" class="btn btn-sm btn-secondary">
                                    Ver Detalle
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <rect x="2" y="5" width="20" height="14" rx="2"/>
                                        <line x1="2" y1="10" x2="22" y2="10"/>
                                    </svg>
                                    <div class="empty-state-title">Sin créditos asociados</div>
                                    <div class="empty-state-text">Este cliente no cuenta con préstamos registrados hasta el momento.</div>
                                    <a href="{{ route('creditos.create', ['cliente_id' => $cliente->id]) }}" class="btn btn-success">
                                        + Otorgar Primer Crédito
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

