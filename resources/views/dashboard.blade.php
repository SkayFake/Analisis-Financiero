<x-app-layout>
    <x-slot name="title">Dashboard Principal</x-slot>

    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Dashboard Principal</h1>
            <p class="page-subtitle">Resumen general del estado de la cartera y operaciones.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('creditos.create') }}" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                Nuevo Crédito
            </a>
        </div>
    </div>

    {{-- KPIs Principales --}}
    <div class="grid-stats">
        <div class="stat-card" style="--stat-color: var(--primary-500); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
            </div>
            <div class="stat-card-label">Cartera Total Activa</div>
            <div class="stat-card-value">${{ number_format($metricas['cartera_total'], 2) }}</div>
            <div class="stat-card-change up">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                {{ $metricas['total_creditos_activos'] }} créditos
            </div>
        </div>

        <div class="stat-card" style="--stat-color: var(--success-500); --stat-bg: var(--success-50);">
            <div class="stat-card-top">
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14M22 4L12 14.01l-3-3"/></svg>
                </div>
            </div>
            <div class="stat-card-label">Cartera Vigente</div>
            <div class="stat-card-value">${{ number_format($metricas['cartera_vigente'], 2) }}</div>
        </div>

        <div class="stat-card" style="--stat-color: var(--danger-500); --stat-bg: var(--danger-50);">
            <div class="stat-card-top">
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0zM12 9v4M12 17h.01"/></svg>
                </div>
            </div>
            <div class="stat-card-label">Cartera en Mora</div>
            <div class="stat-card-value">${{ number_format($metricas['cartera_vencida'], 2) }}</div>
            <div class="stat-card-change down">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                {{ $metricas['porcentaje_mora'] }}% de mora
            </div>
        </div>

        <div class="stat-card" style="--stat-color: var(--info-500); --stat-bg: var(--info-50);">
            <div class="stat-card-top">
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4M3 5v14a2 2 0 0 0 2 2h16v-5M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>
                </div>
            </div>
            <div class="stat-card-label">Cobros del Mes</div>
            <div class="stat-card-value">${{ number_format($metricas['cobros_mes_actual'], 2) }}</div>
        </div>
    </div>

    <div class="grid-2">
        {{-- Distribución por Zona Geográfica --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Distribución por Zona Geográfica</h3>
            </div>
            <div class="card-body">
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Zona</th>
                                <th>Clientes</th>
                                <th>Créditos</th>
                                <th class="text-right">Saldo Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($metricasZona as $zona)
                            <tr>
                                <td>{{ $zona['nombre'] }}</td>
                                <td>{{ $zona['total_clientes'] }}</td>
                                <td>{{ $zona['total_creditos'] }}</td>
                                <td style="text-align: right; font-weight: 600;">${{ number_format($zona['saldo_total'], 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Análisis de Morosidad --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Análisis de Morosidad</h3>
                <a href="{{ route('cobros.dashboard') }}" class="btn btn-sm btn-ghost">Ver Detalles</a>
            </div>
            <div class="card-body">
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Clasificación</th>
                                <th>Rango</th>
                                <th>Créditos</th>
                                <th class="text-right">Saldo en Riesgo</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($morosidad as $letra => $data)
                            <tr>
                                <td>
                                    <span class="badge badge-{{ $data['color'] }}">{{ $letra }} - {{ $data['label'] }}</span>
                                </td>
                                <td>{{ $data['rango'] }}</td>
                                <td>{{ $data['total_creditos'] }}</td>
                                <td style="text-align: right; font-weight: 600;">${{ number_format($data['saldo_total'], 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
