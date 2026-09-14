<x-app-layout>
    <x-slot name="title">Créditos Comerciales</x-slot>

    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Cartera de Créditos Comerciales</h1>
            <p class="page-subtitle">Gestión de ventas a crédito y cuentas por cobrar.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('creditos.create') }}" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Registrar Crédito Manual</span>
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <form action="{{ route('creditos.index') }}" method="GET" class="w-full" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                <input type="text" name="buscar" class="form-input"
                    placeholder="Buscar por número, cliente o factura..."
                    value="{{ request('buscar') }}" style="max-width: 380px;">
                <select name="estado" class="form-select" style="max-width: 220px;">
                    <option value="">Todos los Estados</option>
                    <option value="pendiente_aprobacion" {{ request('estado') == 'pendiente_aprobacion' ? 'selected' : '' }}>Pendiente Aprobación</option>
                    <option value="vigente"    {{ request('estado') == 'vigente'    ? 'selected' : '' }}>Vigente</option>
                    <option value="vencido"    {{ request('estado') == 'vencido'    ? 'selected' : '' }}>Vencido</option>
                    <option value="incobrable" {{ request('estado') == 'incobrable' ? 'selected' : '' }}>Incobrable</option>
                    <option value="cancelado"  {{ request('estado') == 'cancelado'  ? 'selected' : '' }}>Cancelado</option>
                    <option value="rechazado"  {{ request('estado') == 'rechazado'  ? 'selected' : '' }}>Rechazado</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filtrar</button>
            </form>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No. Crédito</th>
                            <th>Factura</th>
                            <th>Cliente</th>
                            <th>Condición</th>
                            <th class="text-right">Valor Mercadería</th>
                            <th class="text-right">Saldo Pendiente</th>
                            <th>Cuotas</th>
                            <th>Estado</th>
                            <th>Mora</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($creditos as $credito)
                        <tr>
                            <td style="font-family: monospace; font-weight: 600; font-size: 0.8rem;">
                                {{ $credito->numero }}
                            </td>
                            <td>
                                @if($credito->venta)
                                    <span style="font-family: monospace; font-size: 0.78rem; color: var(--primary-600);">
                                        {{ $credito->venta->numero_control }}
                                    </span>
                                @else
                                    <span style="color: var(--neutral-400); font-size: 0.8rem;">—</span>
                                @endif
                            </td>
                            <td>{{ $credito->cliente->nombre }}</td>
                            <td style="font-size: 0.82rem;">{{ $credito->condicionCredito?->nombre ?? '—' }}</td>
                            <td style="text-align: right;">${{ number_format($credito->monto_original, 2) }}</td>
                            <td style="text-align: right; font-weight: 600;">${{ number_format($credito->saldo_actual, 2) }}</td>
                            <td style="font-size: 0.82rem; color: var(--neutral-600);">
                                {{ $credito->numero_cuotas }} × {{ $credito->frecuencia_label }}
                            </td>
                            <td>
                                @php
                                    $colors = [
                                        'pendiente_aprobacion' => 'warning',
                                        'aprobado'   => 'info',
                                        'vigente'    => 'success',
                                        'vencido'    => 'warning',
                                        'incobrable' => 'danger',
                                        'cancelado'  => 'neutral',
                                        'rechazado'  => 'danger',
                                        'refinanciado' => 'info',
                                    ];
                                    $labels = [
                                        'pendiente_aprobacion' => 'Pendiente',
                                        'aprobado'   => 'Aprobado',
                                        'vigente'    => 'Vigente',
                                        'vencido'    => 'Vencido',
                                        'incobrable' => 'Incobrable',
                                        'cancelado'  => 'Cancelado',
                                        'rechazado'  => 'Rechazado',
                                        'refinanciado' => 'Refinanciado',
                                    ];
                                @endphp
                                <span class="badge badge-{{ $colors[$credito->estado] ?? 'neutral' }}">
                                    {{ $labels[$credito->estado] ?? $credito->estado }}
                                </span>
                            </td>
                            <td>
                                @if($credito->dias_mora > 0)
                                    <span style="color: var(--danger-600); font-weight: 600;">{{ $credito->dias_mora }} días</span>
                                @else
                                    <span style="color: var(--neutral-400);">Al día</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('creditos.show', $credito) }}" class="btn btn-sm btn-secondary">Detalle</a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10">
                                <div class="empty-state">
                                    <h3 class="empty-state-title">No hay créditos comerciales registrados</h3>
                                    <p class="empty-state-text">Los créditos se generan automáticamente al emitir una factura al crédito desde el Punto de Venta.</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $creditos->links('pagination::bootstrap-5') }}
        </div>
    </div>
</x-app-layout>
