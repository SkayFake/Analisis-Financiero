<x-app-layout>
    <x-slot name="title">Gestión de Créditos</x-slot>

    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Gestión de Créditos</h1>
            <p class="page-subtitle">Listado y administración de cartera de préstamos.</p>
        </div>
        <div class="page-actions">
            <a href="{{ route('creditos.create') }}" class="btn btn-primary">Nuevo Crédito</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <form action="{{ route('creditos.index') }}" method="GET" class="w-full" style="display: flex; gap: 1rem; align-items: center;">
                <input type="text" name="buscar" class="form-input" placeholder="Buscar por número o cliente..." value="{{ request('buscar') }}" style="max-width: 400px;">
                <select name="estado" class="form-select" style="max-width: 200px;">
                    <option value="">Todos los Estados</option>
                    <option value="solicitado" {{ request('estado') == 'solicitado' ? 'selected' : '' }}>Solicitado</option>
                    <option value="vigente" {{ request('estado') == 'vigente' ? 'selected' : '' }}>Vigente</option>
                    <option value="vencido" {{ request('estado') == 'vencido' ? 'selected' : '' }}>Vencido</option>
                    <option value="incobrable" {{ request('estado') == 'incobrable' ? 'selected' : '' }}>Incobrable</option>
                    <option value="cancelado" {{ request('estado') == 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                </select>
                <button type="submit" class="btn btn-secondary">Filtrar</button>
            </form>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Producto</th>
                            <th class="text-right">Monto Original</th>
                            <th class="text-right">Saldo Actual</th>
                            <th>Estado</th>
                            <th>Mora</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($creditos as $credito)
                        <tr>
                            <td style="font-family: monospace; font-weight: 600;">{{ $credito->numero }}</td>
                            <td>{{ $credito->cliente->nombre }}</td>
                            <td>{{ $credito->productoCredito->nombre }}</td>
                            <td style="text-align: right;">${{ number_format($credito->monto_original, 2) }}</td>
                            <td style="text-align: right; font-weight: 600;">${{ number_format($credito->saldo_actual, 2) }}</td>
                            <td>
                                @php
                                    $colors = [
                                        'solicitado' => 'neutral',
                                        'vigente' => 'success',
                                        'vencido' => 'warning',
                                        'incobrable' => 'danger',
                                        'cancelado' => 'info',
                                    ];
                                @endphp
                                <span class="badge badge-{{ $colors[$credito->estado] ?? 'neutral' }}">{{ ucfirst($credito->estado) }}</span>
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
                            <td colspan="8">
                                <div class="empty-state">
                                    <h3 class="empty-state-title">No hay créditos registrados</h3>
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
