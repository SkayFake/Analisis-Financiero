<x-app-layout>
    <x-slot name="title">Control de {{ $vendedor->nombre }}</x-slot>
    <div class="page-header"><div><h1 class="page-title">{{ $vendedor->nombre }}</h1><p class="page-subtitle">{{ $vendedor->codigo }} · {{ $vendedor->zona?->nombre ?? 'Sin zona' }} · {{ $vendedor->activo ? 'Activo' : 'Inactivo' }}</p>
</div>
        <div class="page-actions"><a href="{{ route('vendedores.index', ['mes'=>$periodo->format('Y-m')]) }}" class="btn btn-secondary">Vendedores</a>
            @can('export', \App\Models\Vendedor::class)<a class="btn btn-primary" target="_blank" rel="noopener" href="{{ route('vendedores.reporte', [$vendedor,'mes'=>$periodo->format('Y-m'),'formato'=>'pdf']) }}">Imprimir informe</a>@endcan
        </div>
    </div>
    @include('components.errores-validacion')
    <form method="GET" class="card" style="margin-bottom:1rem;"><div class="card-body" style="display:flex;gap:1rem;align-items:end;flex-wrap:wrap;">
        <div><label for="mes-control" class="form-label">Mes de ventas y cobros</label>
<input id="mes-control" type="month" name="mes" class="form-input" min="2000-01" max="2100-12" required value="{{ $periodo->format('Y-m') }}"></div>
        <div><label for="filtro-cartera" class="form-label">Cartera actual</label>
<select id="filtro-cartera" name="cartera_filtro" class="form-select"><option value="todas">Toda la cartera</option><option value="mora" @selected(request('cartera_filtro')==='mora')>Con cuotas vencidas</option><option value="incobrables" @selected(request('cartera_filtro')==='incobrables')>Incobrables</option></select>
</div>
        <button type="submit" class="btn btn-primary">Consultar</button>
    </div>
</form>
    <div class="grid-stats" style="margin-bottom:1rem;">
        <div class="stat-card"><div class="stat-card-label">Ventas sin IVA · {{ $periodo->format('m/Y') }}</div>
<div class="stat-card-value">${{ number_format($resumen['ventas'],2) }}</div>
<p>Meta: ${{ number_format($resumen['meta_ventas'],2) }} · {{ $resumen['avance_ventas'] !== null ? number_format($resumen['avance_ventas'],1).'%' : 'Sin objetivo' }}</p>
<p>{{ $resumen['documentos'] }} documentos · {{ !$resumen['meta_definida'] ? 'Meta no registrada para este mes' : ($resumen['avance_ventas'] !== null && $resumen['avance_ventas'] >= 100 ? 'Meta cumplida' : 'Pendiente de meta') }}</p>
</div>
        <div class="stat-card"><div class="stat-card-label">Cobros de crédito · {{ $periodo->format('m/Y') }}</div>
<div class="stat-card-value">${{ number_format($resumen['cobros'],2) }}</div>
<p>Meta: ${{ number_format($resumen['meta_cobros'],2) }} · {{ $resumen['avance_cobros'] !== null ? number_format($resumen['avance_cobros'],1).'%' : 'Sin objetivo' }}</p>
<p>{{ $resumen['recibos'] }} recibos · Capital: ${{ number_format($resumen['capital_cobrado'],2) }}</p>
</div>
        <div class="stat-card"><div class="stat-card-label">Cartera actual · capital</div>
<div class="stat-card-value">${{ number_format($resumen['cartera_capital'],2) }}</div>
<p>Total con interés y mora: ${{ number_format($resumen['cartera_total'],2) }}</p>
<p>{{ $resumen['creditos'] }} créditos abiertos</p>
</div>
        <div class="stat-card"><div class="stat-card-label">Cuotas vencidas actuales</div>
<div class="stat-card-value">${{ number_format($resumen['vencido'],2) }}</div>
<p>{{ $resumen['clientes_morosos'] }} clientes con atraso</p>
<p>Capital incobrable: ${{ number_format($resumen['incobrable'],2) }}</p>
</div>
    </div>
    <p>La cartera es actual al {{ now()->format('d/m/Y H:i') }}; el mes filtra ventas y recibos. Ventas de contado con impuestos: ${{ number_format($resumen['contado'],2) }}; ventas a crédito con impuestos: ${{ number_format($resumen['credito'],2) }}. Los cobros corresponden al responsable del crédito al registrarse cada recibo y no identifican necesariamente a la persona que recibió el dinero.</p>

    <div class="card" style="margin:1.5rem 0;"><div class="card-header"><h2 class="card-title">Clientes y créditos a cargo: seguimiento de cobro</h2>
        @can('export', \App\Models\Vendedor::class)<a href="{{ route('vendedores.reporte',[$vendedor,'mes'=>$periodo->format('Y-m'),'tipo'=>'cartera']) }}">Descargar cartera CSV</a>@endcan</div>
        <div class="data-table-wrapper" tabindex="0" role="region" aria-label="Tabla de datos; desplaza horizontalmente para ver todas las columnas"><table class="data-table" aria-label="Cartera actual del vendedor"><thead><tr><th>Cliente / crédito</th>
<th>Origen y contacto</th>
<th>Capital / deuda total</th>
<th>Vencido / último pago</th>
<th>Gestión y responsable</th>
</tr>
</thead>
<tbody>
        @forelse($creditos as $c)
            <tr><td>{{ $c->cliente?->nombre ?? 'Cliente archivado' }}<div>@can('view',$c)<a href="{{ route('creditos.show',$c) }}">{{ $c->numero }}</a>@else {{ $c->numero }} @endcan</div>
<span class="badge badge-{{ $c->estado==='incobrable' ? 'danger' : ((float)$c->deuda_vencida>0 ? 'warning':'neutral') }}">{{ ucfirst($c->estado) }}</span></td>
                <td>Venta original: {{ $c->vendedorOrigen?->nombre ?? 'No documentado' }}@if($c->vendedor_origen_reconstruido)<small> (reconstruido)</small>@endif<div>{{ $c->cliente?->celular ?? $c->cliente?->telefono ?? 'Sin teléfono' }}</div>
</td>
                <td>Capital: ${{ number_format($c->saldo_actual,2) }}<div>Total: ${{ number_format($c->deuda_total,2) }}</div>
</td>
                <td>Vencido: ${{ number_format($c->deuda_vencida,2) }}@if($c->primer_vencimiento)<div>{{ (int)\Carbon\Carbon::parse($c->primer_vencimiento)->diffInDays(today()) }} días desde primera cuota impaga vencida</div>@endif<div>Último pago: {{ $c->ultimo_pago ? \Carbon\Carbon::parse($c->ultimo_pago)->format('d/m/Y') : 'Sin pagos' }}</div>
</td>
                <td style="min-width:260px;white-space:normal;">
                    @can('gestionar',\App\Models\Vendedor::class)
                    <details><summary>Registrar gestión</summary><form method="POST" action="{{ route('creditos.gestiones',$c) }}" style="margin-top:.5rem;">@csrf
                        <label for="medio-{{ $c->id }}" class="form-label">Medio</label>
<select id="medio-{{ $c->id }}" name="medio" class="form-select" required>@foreach(['telefono'=>'Teléfono','visita'=>'Visita','mensaje'=>'Mensaje','correo'=>'Correo','oficina'=>'Oficina'] as $valor=>$texto)<option value="{{ $valor }}">{{ $texto }}</option>@endforeach</select>
                        <label for="resultado-{{ $c->id }}" class="form-label">Resultado</label>
<select id="resultado-{{ $c->id }}" name="resultado" class="form-select" required>@foreach(['sin_respuesta'=>'Sin respuesta','contactado'=>'Contactado','promesa_pago'=>'Promesa de pago','se_niega'=>'Se niega a pagar','datos_incorrectos'=>'Datos incorrectos'] as $valor=>$texto)<option value="{{ $valor }}">{{ $texto }}</option>@endforeach</select>
                        <label for="notas-{{ $c->id }}" class="form-label">Detalle de la gestión</label>
<textarea id="notas-{{ $c->id }}" name="notas" class="form-textarea" rows="3" minlength="10" maxlength="2000" required></textarea>
                        <p>Solo para promesas: indique fecha e importe. Las gestiones no modifican la deuda.</p>
                        <label for="fecha-{{ $c->id }}" class="form-label">Fecha del compromiso</label>
<input id="fecha-{{ $c->id }}" name="fecha_compromiso" type="date" min="{{ today()->toDateString() }}" class="form-input">
                        <label for="monto-{{ $c->id }}" class="form-label">Importe comprometido</label>
<input id="monto-{{ $c->id }}" name="monto_compromiso" type="number" min="0.01" max="{{ $c->deuda_total }}" step="0.01" class="form-input">
                        <button type="submit" class="btn btn-sm btn-primary">Guardar gestión</button>
                    </form>
</details>
                    @endcan
                    @can('assign',\App\Models\Vendedor::class)
                    <details><summary>Reasignar crédito</summary><form method="POST" action="{{ route('creditos.responsable',$c) }}">@csrf
                        <label for="destino-{{ $c->id }}" class="form-label">Nuevo responsable</label>
<select id="destino-{{ $c->id }}" name="vendedor_id" class="form-select" required><option value="">Seleccione</option>@foreach($destinos as $destino)@if($destino->id!==$vendedor->id)<option value="{{ $destino->id }}">{{ $destino->nombre }}</option>@endif @endforeach</select>
                        <label for="cartera-{{ $c->id }}" class="form-label">Cartera del destino</label>
<select id="cartera-{{ $c->id }}" name="cartera_id" class="form-select"><option value="">Sin cartera (retirar asignación anterior)</option>@foreach($carteras as $cartera)<option value="{{ $cartera->id }}">{{ $cartera->nombre }}</option>@endforeach</select>
                        <label for="reasignacion-{{ $c->id }}" class="form-label">Motivo de reasignación</label>
<textarea id="reasignacion-{{ $c->id }}" name="motivo" class="form-textarea" rows="2" required minlength="10" maxlength="2000"></textarea>
                        <button type="submit" class="btn btn-sm btn-secondary">Confirmar reasignación</button>
                    </form>
</details>
                    @endcan
                </td>
</tr>
        @empty<tr><td colspan="5">No hay créditos abiertos que coincidan con este filtro.</td>
</tr>@endforelse
        </tbody>
</table>
</div>
<div class="card-body">{{ $creditos->links() }}</div>
    </div>

    <div class="card" style="margin-bottom:1.5rem;"><div class="card-header"><h2 class="card-title">Qué vendió en {{ $periodo->format('m/Y') }} · principales 20 productos</h2></div>
<div class="data-table-wrapper" tabindex="0" role="region" aria-label="Tabla de datos; desplaza horizontalmente para ver todas las columnas"><table class="data-table"><thead><tr><th>Código</th>
<th>Producto</th>
<th>Unidades</th>
<th>Importe de líneas</th>
</tr>
</thead>
<tbody>@forelse($productos as $p)<tr><td>{{ $p->codigo }}</td>
<td>{{ $p->nombre ?? 'Producto no disponible' }}</td>
<td>{{ $p->unidades }}</td>
<td>${{ number_format($p->importe,2) }}</td>
</tr>@empty<tr><td colspan="4">No hay productos vendidos en el período.</td>
</tr>@endforelse</tbody>
</table>
</div>
</div>

    <div class="card" style="margin-bottom:1.5rem;"><div class="card-header"><h2 class="card-title">Ventas del mes · documentos comerciales válidos</h2>@can('export',\App\Models\Vendedor::class)<a href="{{ route('vendedores.reporte',[$vendedor,'mes'=>$periodo->format('Y-m'),'tipo'=>'ventas']) }}">CSV de ventas</a>@endcan</div>
<div class="data-table-wrapper" tabindex="0" role="region" aria-label="Tabla de datos; desplaza horizontalmente para ver todas las columnas"><table class="data-table"><thead><tr><th>Documento</th>
<th>Fecha / cliente</th>
<th>Condición</th>
<th>Total sin IVA</th>
<th>Total documento</th>
</tr>
</thead>
<tbody>@forelse($ventas as $v)<tr><td>@can('view',$v)<a href="{{ route('facturacion.pdf',$v) }}" target="_blank" rel="noopener">{{ $v->numero_control }}</a>@else {{ $v->numero_control }} @endcan</td>
<td>{{ $v->fecha_emision->format('d/m/Y') }}<div>{{ $v->cliente?->nombre ?? 'Consumidor final' }}</div>
</td>
<td>{{ $v->condicion_operacion==='1'?'Contado':'Crédito' }}</td>
<td>${{ number_format((float)$v->total_pagar-(float)$v->total_iva,2) }}</td>
<td>${{ number_format($v->total_pagar,2) }}</td>
</tr>@empty<tr><td colspan="5">No hay ventas computables en este mes.</td>
</tr>@endforelse</tbody>
</table>
</div>
<div class="card-body">{{ $ventas->links() }}</div>
</div>

    <div class="card" style="margin-bottom:1.5rem;"><div class="card-header"><h2 class="card-title">Cobros del mes atribuidos al vendedor</h2>@can('export',\App\Models\Vendedor::class)<a href="{{ route('vendedores.reporte',[$vendedor,'mes'=>$periodo->format('Y-m'),'tipo'=>'cobros']) }}">CSV de recibos</a>@endcan</div>
<div class="data-table-wrapper" tabindex="0" role="region" aria-label="Tabla de datos; desplaza horizontalmente para ver todas las columnas"><table class="data-table"><thead><tr><th>Recibo / fecha</th>
<th>Cliente / crédito</th>
<th>Capital</th>
<th>Interés / mora</th>
<th>Total</th>
<th>Recibido por</th>
</tr>
</thead>
<tbody>@forelse($pagos as $p)<tr><td>@if($p->credito && !$p->credito->trashed())@can('view',$p->credito)<a target="_blank" rel="noopener" href="{{ route('creditos.pagos.recibo',[$p->credito,$p]) }}">{{ $p->numero_recibo }}</a>@else {{ $p->numero_recibo }} @endcan @else {{ $p->numero_recibo }} @endif<div>{{ $p->fecha_pago->format('d/m/Y') }}</div>@if($p->vendedor_reconstruido)<small>Atribución histórica reconstruida{{ $p->vendedor_revisado_at ? ' · Revisada '.$p->vendedor_revisado_at->format('d/m/Y H:i') : '' }}</small>@can('assign',\App\Models\Vendedor::class)<div><a href="{{ route('vendedores.pendientes',['mes'=>$periodo->format('Y-m'),'recibo'=>$p->id]) }}">Revisar atribución</a></div>@endcan
@endif</td>
<td>{{ $p->credito?->cliente?->nombre }}<div>{{ $p->credito?->numero }}</div>
</td>
<td>${{ number_format($p->abono_capital,2) }}</td>
<td>${{ number_format((float)$p->pago_interes+(float)$p->pago_mora,2) }}</td>
<td>${{ number_format($p->monto_total,2) }}</td>
<td>{{ $p->recibidoPor?->name ?? 'No documentado' }}</td>
</tr>@empty<tr><td colspan="6">No hay cobros atribuidos en el mes.</td>
</tr>@endforelse</tbody>
</table>
</div>
<div class="card-body">{{ $pagos->links() }}</div>
</div>

    <div class="card" style="margin-bottom:1.5rem;"><div class="card-header"><h2 class="card-title">Gestiones y compromisos de pago</h2></div>
<div class="data-table-wrapper" tabindex="0" role="region" aria-label="Tabla de datos; desplaza horizontalmente para ver todas las columnas"><table class="data-table"><thead><tr><th>Registro / responsable</th>
<th>Crédito</th>
<th>Gestión</th>
<th>Compromiso</th>
</tr>
</thead>
<tbody>@forelse($gestiones as $g)<tr><td>{{ $g->created_at->format('d/m/Y H:i') }}<div>{{ $g->usuario_nombre }}</div>
</td>
<td>{{ $g->credito?->numero }}</td>
<td>{{ ucfirst(str_replace('_',' ',$g->medio)) }} · {{ ucfirst(str_replace('_',' ',$g->resultado)) }}<div style="white-space:normal;max-width:380px;">{{ $g->notas }}</div>
</td>
<td>{{ $g->fecha_compromiso?->format('d/m/Y') }}@if($g->monto_compromiso) · ${{ number_format($g->monto_compromiso,2) }} @endif<div>{{ $g->estado_compromiso }}</div>
</td>
</tr>@empty<tr><td colspan="4">Aún no hay gestiones registradas. Puede registrar una desde la cartera.</td>
</tr>@endforelse</tbody>
</table>
</div>
<div class="card-body">{{ $gestiones->links() }}</div>
</div>

    <div class="card" style="margin-bottom:1.5rem;"><div class="card-header"><h2 class="card-title">Metas por mes</h2></div>
<div class="card-body">
        @can('metas',\App\Models\Vendedor::class)<form action="{{ route('vendedores.metas',$vendedor) }}" method="POST" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:end;">@csrf
            <div><label class="form-label" for="periodo-meta">Mes</label>
<input id="periodo-meta" name="periodo" type="month" min="2000-01" max="2100-12" value="{{ old('periodo',$periodo->format('Y-m')) }}" class="form-input" required></div>
            <div><label class="form-label" for="meta-ventas">Meta ventas sin IVA ($)</label>
<input id="meta-ventas" name="meta_ventas" type="number" min="0" max="999999999999.99" step="0.01" value="{{ old('meta_ventas',$resumen['meta_ventas']) }}" class="form-input" required></div>
            <div><label class="form-label" for="meta-cobros">Meta cobros de créditos ($)</label>
<input id="meta-cobros" name="meta_cobros" type="number" min="0" max="999999999999.99" step="0.01" value="{{ old('meta_cobros',$resumen['meta_cobros']) }}" class="form-input" required></div>
            <div><label class="form-label" for="motivo-meta">Motivo de registro o cambio</label>
<textarea id="motivo-meta" name="motivo" rows="2" minlength="10" maxlength="2000" class="form-textarea" required></textarea></div>
            <button type="submit" class="btn btn-primary">Guardar metas con historial</button>
        </form>
<p>Importe cero significa sin objetivo para ese concepto. Cada mes se registra de forma independiente.</p>@endcan
        <table class="data-table"><thead><tr><th>Mes</th>
<th>Meta ventas</th>
<th>Meta cobros</th>
<th>Último registro</th>
</tr>
</thead>
<tbody>@forelse($metas as $m)<tr><td>{{ $m->periodo->format('m/Y') }}</td>
<td>${{ number_format($m->meta_ventas,2) }}</td>
<td>${{ number_format($m->meta_cobros,2) }}</td>
<td>{{ $m->updated_at->format('d/m/Y H:i') }}</td>
</tr>@empty<tr><td colspan="4">No hay metas mensuales registradas.</td>
</tr>@endforelse</tbody>
</table>{{ $metas->links() }}
    </div>
</div>

    @if($auditorias)
    <div class="card"><div class="card-header"><h2 class="card-title">Bitácora de control</h2>@can('export',\App\Models\Vendedor::class)<a href="{{ route('vendedores.reporte',[$vendedor,'mes'=>$periodo->format('Y-m'),'tipo'=>'auditoria']) }}">CSV de bitácora</a>@endcan</div>
<div class="data-table-wrapper" tabindex="0" role="region" aria-label="Tabla de datos; desplaza horizontalmente para ver todas las columnas"><table class="data-table"><thead><tr><th>ID / fecha</th>
<th>Acción / entidad</th>
<th>Responsable / motivo</th>
<th>Valores registrados</th>
</tr>
</thead>
<tbody>@forelse($auditorias as $a)<tr><td>#{{ $a->id }}<div>{{ $a->created_at->format('d/m/Y H:i:s') }}</div>
</td>
<td>{{ $a->accion }}<div>{{ $a->entidad }} #{{ $a->entidad_id }}</div>
</td>
<td style="white-space:normal;max-width:300px;">{{ $a->usuario_nombre }}<div>{{ $a->motivo }}</div>
</td>
<td><details><summary>Antes y después</summary><pre style="white-space:pre-wrap;max-width:400px;">Antes: {{ json_encode($a->antes,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}
Después: {{ json_encode($a->despues,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
</td>
</tr>@empty<tr><td colspan="4">No hay eventos registrados.</td>
</tr>@endforelse</tbody>
</table>
</div>
<div class="card-body">{{ $auditorias->links() }}</div>
</div>
    @endif
</x-app-layout>
