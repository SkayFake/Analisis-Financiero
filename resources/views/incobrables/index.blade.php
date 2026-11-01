<x-app-layout>
    <x-slot name="title">Cartera de cuentas incobrables</x-slot>
    <div class="page-header">
        <div><h1 class="page-title">Cartera de cuentas incobrables</h1>
            <p class="page-subtitle">Registro de traslados y reactivaciones. Los saldos se conservan; trasladar una cuenta no elimina su deuda.</p></div>
    </div>
    @include('components.errores-validacion')
    <div class="card" style="margin-bottom: 1.5rem;">
        <div class="card-body" style="display: flex; gap: 2rem; flex-wrap: wrap;">
            <div><strong>Cuentas abiertas</strong><div>{{ $resumen['cuentas'] }}</div></div>
            <div><strong>Capital en cartera</strong><div>${{ number_format($resumen['capital'], 2) }}</div></div>
            <div><strong>Total registrado al traslado</strong><div>${{ number_format($resumen['total'], 2) }}</div></div>
        </div>
    </div>
    <form method="GET" action="{{ route('incobrables.index') }}" style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
        <div class="form-group"><label class="form-label" for="buscar">Cliente o crédito</label>
            <input class="form-input" id="buscar" name="buscar" maxlength="150" value="{{ request('buscar') }}" placeholder="Nombre, DUI o número de crédito"></div>
        <div class="form-group"><label class="form-label" for="estado">Registros</label>
            <select class="form-select" id="estado" name="estado">
                <option value="abiertas" @selected(request('estado', 'abiertas') === 'abiertas')>Cartera abierta</option>
                <option value="reactivadas" @selected(request('estado') === 'reactivadas')>Reactivadas (historial)</option>
                <option value="todas" @selected(request('estado') === 'todas')>Todo el historial</option>
            </select></div>
        <div style="align-self: center;"><button class="btn btn-primary" type="submit">Filtrar</button></div>
    </form>
    <div class="card"><div class="data-table-wrapper" tabindex="0" role="region" aria-label="Tabla de datos; desplaza horizontalmente para ver todas las columnas">
        <table class="data-table" aria-label="Cartera de cuentas incobrables">
            <thead><tr><th>Crédito / cliente</th><th>Declaración</th><th>Capital al traslado</th><th>Total al traslado</th><th>Registro y motivo</th></tr></thead>
            <tbody>
            @forelse($cuentas as $cuenta)
                <tr>
                    <td>
                        @can('view', $cuenta->credito)
                            @if(!$cuenta->credito->trashed())
                                <a href="{{ route('creditos.show', $cuenta->credito) }}">{{ $cuenta->credito->numero }}</a>
                            @else
                                {{ $cuenta->credito->numero }} (archivado)
                            @endif
                        @else
                            {{ $cuenta->credito->numero }}
                        @endcan
                        <div>{{ $cuenta->credito->cliente?->nombre ?? 'Cliente no disponible' }}</div>
                        <span class="badge badge-{{ $cuenta->abierta ? 'danger' : 'info' }}">{{ $cuenta->abierta ? 'Incobrable' : 'Reactivada' }}</span>
                    </td>
                    <td>{{ $cuenta->declarado_at?->format('d/m/Y H:i') ?? 'Fecha original no disponible' }}
                        <div>{{ $cuenta->declaradoPor?->name ?? 'Responsable no disponible' }}</div>
                        <small>{{ $cuenta->dias_mora }} días de mora registrados</small></td>
                    <td>${{ number_format($cuenta->saldo_capital, 2) }}</td>
                    <td>${{ number_format($cuenta->saldo_total, 2) }}</td>
                    <td style="min-width: 250px; white-space: normal;">
                        <p>{{ $cuenta->motivo }}</p>
                        @if($cuenta->reactivado_at)
                            <p><strong>Reactivación:</strong> {{ $cuenta->reactivado_at->format('d/m/Y H:i') }} — {{ $cuenta->reactivadoPor?->name ?? 'Responsable no disponible' }}</p>
                            <p>{{ $cuenta->motivo_reactivacion }}</p>
                        @elseif(!$cuenta->credito->trashed())
                            @can('reactivate', $cuenta->credito)
                                <details><summary style="cursor: pointer;">Reactivar cobro</summary>
                                    <form action="{{ route('creditos.reactivar', $cuenta->credito) }}" method="POST">
                                        @csrf
                                        <label class="form-label" for="motivo-{{ $cuenta->id }}">Motivo de reactivación</label>
                                        <textarea class="form-textarea" id="motivo-{{ $cuenta->id }}" name="motivo" required minlength="10" maxlength="2000" rows="3"></textarea>
                                        <p>Se retoma el mismo saldo y plan de cuotas. El registro permanece en el historial.</p>
                                        <button type="submit" class="btn btn-primary btn-sm">Confirmar reactivación</button>
                                    </form>
                                </details>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No hay cuentas incobrables que coincidan con los filtros.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div><div class="card-body">{{ $cuentas->links() }}</div></div>
    <p style="margin-top: 1rem;">El total incluye los importes pendientes del plan y la mora registrada al declarar la cuenta. Esta clasificación interna no registra una condonación ni un asiento contable de castigo.</p>
</x-app-layout>
