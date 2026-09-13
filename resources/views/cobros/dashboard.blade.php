<x-app-layout>
    <x-slot name="title">Dashboard de Cobros y Recuperación</x-slot>

    {{-- Encabezado de Página --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Cobros y Recuperación de Cartera</h1>
            <p class="page-subtitle">Monitoreo de morosidad, metas de cobranza y reclasificación de riesgo crediticio.</p>
        </div>
        <div class="page-actions" x-data="{ modalReclasificar: false }">
            {{-- Botón con Modal de Confirmación Moderno --}}
            <button type="button" @click="modalReclasificar = true" class="btn btn-warning">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                </svg>
                <span>Reclasificación Masiva</span>
            </button>

            <a href="{{ route('creditos.index', ['estado' => 'vencido']) }}" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <span>Ver Créditos en Mora</span>
            </a>

            {{-- Modal de Confirmación para Reclasificación --}}
            <div class="modal-overlay" x-show="modalReclasificar" x-cloak style="display: flex;" @keydown.escape.window="modalReclasificar = false">
                <div class="modal" @click.outside="modalReclasificar = false" style="max-width: 480px;">
                    <div class="modal-header">
                        <div>
                            <h3 class="modal-title">Reclasificación Masiva</h3>
                            <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                                Ajuste automático según días de mora acumulados.
                            </p>
                        </div>
                        <button type="button" class="modal-close" @click="modalReclasificar = false">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <form action="{{ route('cobros.reclasificacion') }}" method="POST">
                        @csrf
                        <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                            <div class="alert alert-warning" style="margin-bottom: 0;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/>
                                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <span>Esta operación evaluará todos los créditos activos y reclasificará el perfil de riesgo (A, B, C, D, E) de los clientes en base a sus cuotas impagas.</span>
                            </div>
                            <p style="font-size: 0.875rem; color: var(--neutral-600);">
                                ¿Deseas ejecutar el proceso ahora?
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" @click="modalReclasificar = false">Cancelar</button>
                            <button type="submit" class="btn btn-warning">
                                Sí, Ejecutar Reclasificación
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- KPIs Generales de Cartera y Cobranza --}}
    @if(isset($metricas))
    <div class="grid-stats" style="margin-bottom: 1.5rem;">
        {{-- Cartera Total --}}
        <div class="stat-card" style="--stat-color: var(--primary-600); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Cartera Activa Total</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">${{ number_format($metricas['cartera_total'] ?? 0, 2) }}</div>
            <div class="stat-card-change" style="background: var(--neutral-100); color: var(--neutral-700);">
                {{ $metricas['total_creditos_activos'] ?? 0 }} créditos vigentes/en mora
            </div>
        </div>

        {{-- Cobros Mes Actual --}}
        <div class="stat-card" style="--stat-color: var(--success-500); --stat-bg: var(--success-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Recuperado Mes Actual</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--success-600);">
                ${{ number_format($metricas['cobros_mes_actual']['total_cobrado'] ?? 0, 2) }}
            </div>
            <div class="stat-card-change up">
                Hoy: ${{ number_format($metricas['cobros_hoy']['total_cobrado'] ?? 0, 2) }}
            </div>
        </div>

        {{-- Cartera Vencida --}}
        <div class="stat-card" style="--stat-color: var(--warning-500); --stat-bg: var(--warning-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Saldo en Mora</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--warning-600);">
                ${{ number_format($metricas['cartera_vencida'] ?? 0, 2) }}
            </div>
            <div class="stat-card-change down">
                {{ $metricas['total_creditos_vencidos'] ?? 0 }} créditos vencidos
            </div>
        </div>

        {{-- Índice de Morosidad --}}
        <div class="stat-card" style="--stat-color: {{ ($metricas['porcentaje_mora'] ?? 0) > 10 ? 'var(--danger-500)' : 'var(--primary-500)' }}; --stat-bg: {{ ($metricas['porcentaje_mora'] ?? 0) > 10 ? 'var(--danger-50)' : 'var(--primary-50)' }};">
            <div class="stat-card-top">
                <div class="stat-card-label">Índice de Morosidad</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="20" x2="18" y2="10"/>
                        <line x1="12" y1="20" x2="12" y2="4"/>
                        <line x1="6" y1="20" x2="6" y2="14"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: {{ ($metricas['porcentaje_mora'] ?? 0) > 10 ? 'var(--danger-600)' : 'var(--neutral-900)' }};">
                {{ number_format($metricas['porcentaje_mora'] ?? 0, 2) }}%
            </div>
            <div class="stat-card-change {{ ($metricas['porcentaje_mora'] ?? 0) > 10 ? 'down' : 'up' }}">
                {{ ($metricas['porcentaje_mora'] ?? 0) > 10 ? 'Requiere atención' : 'Dentro del margen' }}
            </div>
        </div>
    </div>
    @endif

    {{-- Matriz de Clasificación de Cartera --}}
    <div class="card mb-4" style="margin-bottom: 1.5rem;">
        <div class="card-header">
            <div>
                <h3 class="card-title">Matriz de Calificación de Cartera por Días de Mora</h3>
                <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                    Segmentación estandarizada según política institucional de riesgo.
                </p>
            </div>
        </div>
        <div class="card-body">
            <div class="grid-stats">
                @foreach($morosidad as $letra => $data)
                <div class="stat-card" style="--stat-color: var(--{{ $data['color'] }}-500); --stat-bg: var(--{{ $data['color'] }}-50);">
                    <div class="stat-card-top">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="badge badge-{{ $data['color'] }}" style="font-size: 0.8125rem; font-weight: 800;">
                                    Cat. {{ $letra }}
                                </span>
                                <span style="font-size: 0.8125rem; font-weight: 600; color: var(--neutral-700);">
                                    {{ $data['label'] }}
                                </span>
                            </div>
                            <div style="font-size: 0.75rem; color: var(--neutral-500); margin-top: 0.25rem;">
                                {{ $data['rango'] }}
                            </div>
                        </div>
                    </div>
                    <div class="stat-card-value" style="margin-top: 0.5rem;">
                        ${{ number_format($data['saldo_total'], 2) }}
                    </div>
                    <div class="stat-card-change" style="margin-top: 0.5rem; background: var(--{{ $data['color'] }}-50); color: var(--{{ $data['color'] }}-700);">
                        <strong>{{ $data['total_creditos'] }}</strong> créditos en tramo
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Rendimiento de Gestores --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Rendimiento de Gestores y Cobranza (Mes Actual)</h3>
                <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                    Comparativa de cumplimiento de metas y recuperación de capital e intereses.
                </p>
            </div>
            <div style="font-size: 0.8125rem; color: var(--neutral-500);">
                Total Gestores: <strong>{{ count($rendimiento) }}</strong>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="min-width: 200px;">Gestor / Vendedor</th>
                            <th class="text-right" style="min-width: 120px; text-align: right;">Meta Mensual</th>
                            <th class="text-right" style="min-width: 130px; text-align: right;">Total Recuperado</th>
                            <th class="text-right" style="min-width: 120px; text-align: right;">Abono Capital</th>
                            <th class="text-right" style="min-width: 120px; text-align: right;">Intereses / Mora</th>
                            <th style="min-width: 180px;">% Cumplimiento Meta</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rendimiento as $v)
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: var(--neutral-900); font-size: 0.9375rem;">
                                    {{ $v['nombre'] }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--neutral-400);">
                                    {{ $v['total_pagos'] }} pagos gestionados
                                </div>
                            </td>
                            <td style="text-align: right; font-weight: 500; color: var(--neutral-700);">
                                ${{ number_format($v['meta_mensual'], 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 700; color: var(--success-600); font-size: 1rem;">
                                ${{ number_format($v['total_cobrado'], 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 500; color: var(--neutral-800);">
                                ${{ number_format($v['total_capital'], 2) }}
                            </td>
                            <td style="text-align: right; font-weight: 500; color: var(--neutral-800);">
                                ${{ number_format($v['total_interes'], 2) }}
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <div style="flex: 1; height: 10px; background: var(--neutral-100); border: 1px solid var(--neutral-200); border-radius: var(--radius-full); overflow: hidden;">
                                        <div style="height: 100%; width: {{ min(100, $v['porcentaje_meta']) }}%; background: var(--{{ $v['porcentaje_meta'] >= 100 ? 'success' : ($v['porcentaje_meta'] >= 75 ? 'primary' : 'warning') }}-500); border-radius: var(--radius-full); transition: width 0.4s ease;"></div>
                                    </div>
                                    <span style="font-size: 0.8125rem; font-weight: 700; min-width: 48px; text-align: right; color: var(--{{ $v['porcentaje_meta'] >= 100 ? 'success' : ($v['porcentaje_meta'] >= 75 ? 'primary' : 'warning') }}-600);">
                                        {{ number_format($v['porcentaje_meta'], 1) }}%
                                    </span>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <line x1="19" y1="8" x2="19" y2="14"/>
                                        <line x1="22" y1="11" x2="16" y2="11"/>
                                    </svg>
                                    <div class="empty-state-title">No hay pagos registrados este mes</div>
                                    <div class="empty-state-text">Aún no se han recibido pagos de cuotas para calcular el rendimiento de los gestores.</div>
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

