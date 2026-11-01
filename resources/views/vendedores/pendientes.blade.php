<x-app-layout>
    <x-slot name="title">Revisión de asignaciones</x-slot>
    <div class="page-header"><div><h1 class="page-title">Revisión de asignaciones</h1><p class="page-subtitle">Documente el origen con evidencia. Regularizar no cambia importes ni registra pagos.</p>
</div>
<a href="{{ route('vendedores.index',['mes'=>$periodo->format('Y-m')]) }}" class="btn btn-secondary">Volver al control</a></div>
    @include('components.errores-validacion')
    <form method="GET" style="display:flex;gap:1rem;align-items:end;margin-bottom:1rem;"><div><label class="form-label" for="mes-pendientes">Mes de documentos y recibos</label>
<input id="mes-pendientes" name="mes" type="month" min="2000-01" max="2100-12" value="{{ $periodo->format('Y-m') }}" class="form-input" required></div>
<button class="btn btn-primary" type="submit">Consultar</button></form>
    <p>Los recibos reconstruidos conservan una marca de origen histórico después de revisarlos; se registra la revisión y salen de esta lista. La reasignación de un crédito no cambia sus recibos anteriores ni el vendedor de la venta.</p>
    @foreach(['ventas'=>$ventas,'pagos'=>$pagos,'creditos'=>$creditos] as $tipo=>$registros)
        <div class="card" style="margin-bottom:1.5rem;"><div class="card-header"><h2 class="card-title">{{ ['ventas'=>'Ventas del mes sin vendedor','pagos'=>'Recibos del mes sin atribución o reconstruidos','creditos'=>'Créditos actuales sin responsable'][$tipo] }}</h2></div>
            <div class="data-table-wrapper" tabindex="0" role="region" aria-label="Tabla de datos; desplaza horizontalmente para ver todas las columnas"><table class="data-table"><thead><tr><th>Documento</th>
<th>Cliente / estado</th>
<th>Atribución actual</th>
<th>Regularización</th>
</tr>
</thead>
<tbody>
            @forelse($registros as $registro)
                <tr><td>{{ $tipo==='ventas' ? $registro->numero_control : ($tipo==='pagos' ? $registro->numero_recibo : $registro->numero) }}</td>
                    <td>{{ $tipo==='pagos' ? $registro->credito?->cliente?->nombre : $registro->cliente?->nombre }}<div>{{ $registro->estado ?? $registro->estado_dte ?? '' }}</div>
</td>
                    <td>{{ $registro->vendedor?->nombre ?? 'Sin asignar' }}@if($tipo==='pagos' && $registro->vendedor_reconstruido)<div>Reconstruida desde datos anteriores</div>@endif</td>
                    <td style="min-width:280px;white-space:normal;">
                    @can('assign',\App\Models\Vendedor::class)
                        <details><summary>{{ $tipo==='creditos' ? 'Asignar responsable activo' : 'Documentar atribución histórica' }}</summary>
                            <form method="POST" action="{{ $tipo==='ventas' ? route('ventas.vendedor',$registro) : ($tipo==='pagos' ? route('pagos.vendedor',$registro) : route('creditos.responsable',$registro)) }}">@csrf
                                <label class="form-label" for="vendedor-{{ $tipo }}-{{ $registro->id }}">Vendedor</label>
<select class="form-select" name="vendedor_id" id="vendedor-{{ $tipo }}-{{ $registro->id }}" required><option value="">Seleccione según evidencia</option>@foreach($vendedores as $v)@if($tipo!=='creditos' || $v->activo)<option value="{{ $v->id }}">{{ $v->codigo }} · {{ $v->nombre }}{{ !$v->activo ? ' (inactivo)' : '' }}</option>@endif @endforeach</select>
                                <label class="form-label" for="motivo-{{ $tipo }}-{{ $registro->id }}">Evidencia y motivo</label>
<textarea id="motivo-{{ $tipo }}-{{ $registro->id }}" name="motivo" class="form-textarea" rows="3" required minlength="10" maxlength="2000"></textarea>
                                <button class="btn btn-sm btn-primary" type="submit">Confirmar con registro en bitácora</button>
                            </form>
                        </details>
                    @else Se requiere permiso de asignación. @endcan
                    </td>
</tr>
            @empty<tr><td colspan="4">No hay registros en esta sección.</td>
</tr>@endforelse
            </tbody>
</table>
</div>
<div class="card-body">{{ $registros->links() }}</div>
        </div>
    @endforeach
</x-app-layout>
