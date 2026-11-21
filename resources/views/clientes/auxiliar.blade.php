<x-app-layout>
    <x-slot name="title">Auxiliar por Cliente: {{ $cliente->nombre }}</x-slot>

    {{-- Encabezado de Página --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Auxiliar Contable por Cliente</h1>
            <div class="page-subtitle" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <span>Cliente: <strong>{{ $cliente->nombre }}</strong></span>
                <span>•</span>
                <span style="font-family: monospace;">{{ $cliente->codigo }}</span>
                <span>•</span>
                <span class="badge badge-primary">Categoría {{ $cliente->clasificacion_cobro }}</span>
            </div>
        </div>
        <div class="page-actions">
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                <span>Imprimir / Exportar</span>
            </button>
            <a href="{{ route('clientes.show', $cliente) }}" class="btn btn-ghost">
                <span>Volver a la Ficha</span>
            </a>
        </div>
    </div>

    {{-- Resumen de Saldos del Auxiliar --}}
    @php
        $totalCargos = $auxiliar->sum('cargo');
        $totalAbonos = $auxiliar->sum('abono');
        $saldoFinal = $totalCargos - $totalAbonos;
    @endphp

    <div class="grid-stats" style="margin-bottom: 1.5rem;">
        <div class="stat-card" style="--stat-color: var(--primary-600); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Total Cargos (Ventas a Crédito)</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">${{ number_format($totalCargos, 2) }}</div>
            <div class="stat-card-change" style="background: var(--neutral-100); color: var(--neutral-700);">Facturación a plazo</div>
        </div>

        <div class="stat-card" style="--stat-color: var(--success-600); --stat-bg: var(--success-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Total Abonos (Pagos y Recibos)</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--success-600);">${{ number_format($totalAbonos, 2) }}</div>
            <div class="stat-card-change up">Recuperación efectiva</div>
        </div>

        <div class="stat-card" style="--stat-color: var(--{{ $saldoFinal > 0 ? 'warning' : 'neutral' }}-600); --stat-bg: var(--{{ $saldoFinal > 0 ? 'warning' : 'neutral' }}-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Saldo Acumulado Actual</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--{{ $saldoFinal > 0 ? 'warning' : 'neutral' }}-700);">${{ number_format($saldoFinal, 2) }}</div>
            <div class="stat-card-change {{ $saldoFinal > 0 ? 'down' : 'up' }}">
                {{ $saldoFinal > 0 ? 'Deuda viva pendiente' : 'Al día' }}
            </div>
        </div>
    </div>

    {{-- Tabla Cronológica del Auxiliar --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Libro Auxiliar Cronológico de Ventas y Pagos</h3>
                <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                    Registro de movimientos, ventas, intereses devengados y aplicaciones de pago con saldo progresivo.
                </p>
            </div>
            <span style="font-size: 0.8125rem; color: var(--neutral-500);">
                Total movimientos: <strong>{{ $auxiliar->count() }}</strong>
            </span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper" tabindex="0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="min-width: 100px;">Fecha</th>
                            <th style="min-width: 130px;">Tipo Operación</th>
                            <th style="min-width: 140px;">Referencia</th>
                            <th style="min-width: 250px;">Concepto / Detalle</th>
                            <th class="text-right" style="min-width: 120px; text-align: right;">Cargos (Debe)</th>
                            <th class="text-right" style="min-width: 120px; text-align: right;">Abonos (Haber)</th>
                            <th class="text-right" style="min-width: 130px; text-align: right;">Saldo Acumulado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($auxiliar as $item)
                            <tr>
                                <td style="font-size: 0.8125rem; white-space: nowrap;">
                                    {{ \Carbon\Carbon::parse($item['fecha'])->format('d/m/Y') }}
                                </td>
                                <td>
                                    @php
                                        $badgeTipo = match($item['tipo']) {
                                            'VENTA_CREDITO' => 'info',
                                            'INTERES_CARGO' => 'warning',
                                            'PAGO_RECIBO'   => 'success',
                                            default         => 'neutral',
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $badgeTipo }}" style="font-size: 0.75rem;">
                                        {{ str_replace('_', ' ', $item['tipo']) }}
                                    </span>
                                </td>
                                <td style="font-family: monospace; font-weight: 600; font-size: 0.875rem;">
                                    {{ $item['referencia'] }}
                                </td>
                                <td style="font-size: 0.8125rem;">
                                    {{ $item['concepto'] }}
                                    @if(isset($item['detalle']))
                                        <div style="font-size: 0.75rem; color: var(--neutral-500); margin-top: 0.2rem;">
                                            Cap: ${{ number_format($item['detalle']['capital'], 2) }} • Int: ${{ number_format($item['detalle']['interes'], 2) }} • Mora: ${{ number_format($item['detalle']['mora'], 2) }}
                                        </div>
                                    @endif
                                </td>
                                <td style="text-align: right; font-weight: 600; color: var(--danger-600);">
                                    {{ $item['cargo'] > 0 ? '$'.number_format($item['cargo'], 2) : '-' }}
                                </td>
                                <td style="text-align: right; font-weight: 600; color: var(--success-600);">
                                    {{ $item['abono'] > 0 ? '$'.number_format($item['abono'], 2) : '-' }}
                                </td>
                                <td style="text-align: right; font-weight: 700; font-size: 0.9375rem; color: var(--neutral-900);">
                                    ${{ number_format($item['saldo_acumulado'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 3rem 1rem; color: var(--neutral-400);">
                                    No hay registros en el auxiliar contable para este cliente.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
