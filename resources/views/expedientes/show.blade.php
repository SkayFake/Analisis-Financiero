<x-app-layout>
    <x-slot name="title">Expediente documental</x-slot>
    @include('components.errores-validacion')
    <div class="page-header">
        <div><h1 class="page-title">Expediente documental</h1><p>{{ $cliente->nombre }} @if($credito) · {{ $credito->numero }} @endif</p></div>
        <a class="btn btn-secondary" href="{{ $credito ? route('creditos.show', $credito) : route('clientes.show', $cliente) }}">Volver a la ficha</a>
    </div>
    <div class="card" style="margin-bottom:1.5rem"><div class="card-body">
        <p><strong>Requisitos según política interna.</strong> La documentación depende del tipo de cliente y del crédito. Esta lista debe ajustarse a la actividad del negocio y a la normativa aplicable; no representa requisitos legales universales.</p>
        <p>Los comprobantes del cliente se reutilizan en sus solicitudes mientras sigan válidos. El contrato y la solicitud pertenecen a este crédito; los documentos de cada fiador pertenecen a su garantía en este crédito.</p>
        <p>Adjunte PDF, JPG, PNG o WebP de hasta {{ number_format(config('expedientes.max_kb') / 1024, 0) }} MB por archivo. Para el DUI incluya ambos lados en un archivo o presente frente y reverso por separado. @if($cliente->tipo === 'juridica') El DUI corresponde al representante legal registrado en la ficha. @endif</p>
        <p>La carga queda pendiente de revisión. La revisión humana confirma correspondencia y legibilidad; el sistema no certifica autenticidad ni consulta registros externos. Adjunte solo información pertinente para evaluar el crédito.</p>
        @unless($credito)
            <p><strong>Comprobantes previstos para este cliente:</strong> {{ collect(config('expedientes.requisitos.'.$cliente->tipo))->map(fn($g) => config('expedientes.grupos.'.$g.'.nombre'))->implode('; ') }}.</p>
            <p>Abra el expediente desde cada crédito para registrar sus contratos y fiadores.</p>
        @endunless
    </div></div>
    @if($credito)
        <div class="card" style="margin-bottom:1.5rem"><div class="card-body">
            <h2>Control previo a aprobación</h2>
            @if(isset($credito->requisitos_expediente['tipo_cliente']) && $credito->requisitos_expediente['tipo_cliente'] !== $cliente->tipo)
                <p class="alert alert-danger">El tipo de cliente cambió desde la solicitud. Registre una nueva solicitud con los requisitos correspondientes.</p>
            @endif
            @if($credito->expediente_requerido)
                <p>La aprobación exige todos los requisitos completos, documentos revisados, vigentes y disponibles.</p>
            @else
                <p>Crédito registrado sin control documental obligatorio: la lista sirve para completar su expediente. No cambia retroactivamente la aprobación ni impide registrar pagos.</p>
            @endif
            <ul style="padding-left:1.4rem">@foreach($lista as $requisito)
                <li style="margin:.5rem 0"><span class="badge badge-{{ $requisito['completo'] ? 'success' : 'warning' }}">{{ $requisito['completo'] ? 'Completo' : 'Falta revisar / presentar' }}</span> {{ $requisito['titulo'] }} — {{ $requisito['requisito'] }}</li>
            @endforeach</ul>
            @if($credito->condicionCredito?->requiere_fiador && $credito->fiadores->isEmpty())<p class="text-danger">La condición exige registrar al menos un fiador.</p>@endif
        </div></div>
    @endif

    @foreach($secciones as $seccion)
        <section class="card" style="margin-bottom:1.5rem">
            <div class="card-body">
                <h2>{{ $seccion['titulo'] }}</h2>
                @if($seccion['fiador'])
                    <p>DUI: {{ $seccion['fiador']->dui ?: 'No registrado' }} · Teléfono: {{ $seccion['fiador']->telefono ?: 'No registrado' }} · Ingresos: ${{ number_format($seccion['fiador']->ingresos, 2) }} · Egresos: ${{ number_format($seccion['fiador']->egresos, 2) }}</p>
                    @can('expedientes.manage')@if($credito->estado === 'pendiente_aprobacion')
                        <details style="margin-bottom:1rem"><summary>Completar / corregir datos del fiador</summary>
                            @include('expedientes.fiador', ['fiador' => $seccion['fiador']])
                        </details>
                    @endif
@endcan
                @endif
                @can('expedientes.upload')
                    <details style="margin:1rem 0"><summary class="btn btn-secondary">Presentar comprobante o nueva versión</summary>
                        <form method="POST" enctype="multipart/form-data" action="{{ $seccion['fiador'] ? route('expedientes.fiador.subir', [$credito, $seccion['fiador']]) : ($seccion['credito'] ? route('expedientes.credito.subir', $credito) : route('expedientes.cliente.subir', $cliente)) }}" style="margin-top:1rem">
                            @csrf
                            <div class="form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
                                <label>Categoría *<select name="categoria" class="form-input" required><option value="">Seleccione</option>@foreach($seccion['categorias'] as $clave => $nombre)<option value="{{ $clave }}">{{ $nombre }}</option>@endforeach</select></label>
                                <label>Archivo *<input class="form-input" type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.webp" required></label>
                                <label>Fecha de emisión (si consta)<input class="form-input" type="date" name="fecha_emision" max="{{ today()->toDateString() }}"></label>
                                <label>Vencimiento (si consta)<input class="form-input" type="date" name="fecha_vencimiento"></label>
                                <label>Reemplazar versión<select class="form-input" name="reemplaza_id"><option value="">Es un comprobante adicional</option>@foreach($seccion['documentos']->where('vigente', true) as $d)<option value="{{ $d->id }}">#{{ $d->id }} · {{ $seccion['categorias'][$d->categoria] ?? $d->categoria }} · v{{ $d->version }}</option>@endforeach</select></label>
                                <label>Motivo del reemplazo (obligatorio al reemplazar)<input class="form-input" name="motivo" minlength="8" maxlength="2000"></label>
                            </div>
                            <label>Observaciones de recepción<textarea class="form-input" name="notas" maxlength="2000" rows="2"></textarea></label>
                            <p>Si reemplaza un documento, seleccione su misma categoría. La versión anterior se conserva en el historial.</p>
                            <button class="btn btn-primary" type="submit">Guardar comprobante pendiente</button>
                        </form>
                    </details>
                @endcan
                <div style="overflow-x:auto"><table class="data-table"><thead><tr><th>Comprobante</th><th>Presentación</th><th>Estado y revisión</th><th>Archivo / acciones</th></tr></thead><tbody>
                    @forelse($seccion['documentos'] as $d)
                        <tr>
                            <td><strong>{{ config('expedientes.categorias.'.$d->categoria, $d->categoria) }}</strong><br>#{{ $d->id }} · versión {{ $d->version }} {{ $d->vigente ? '(actual)' : '(sustituida)' }}<br>{{ $d->nombre_original }}<br><small>Titular al presentar: {{ $d->titular }}</small></td>
                            <td>{{ $d->created_at->format('d/m/Y H:i') }}<br>{{ $d->autor?->name ?? 'Sistema' }}<br><small>Emisión: {{ $d->fecha_emision?->format('d/m/Y') ?? 'No consta' }}<br>Vence: {{ $d->fecha_vencimiento?->format('d/m/Y') ?? 'No consta' }}</small></td>
                            <td><span class="badge badge-{{ $d->aceptable ? 'success' : 'warning' }}">{{ ucfirst($d->estado) }}</span>
                                @if($d->estado === 'validado' && !$d->aceptable)<p>No utilizable actualmente: versión sustituida, vencimiento, datos del titular modificados o archivo ausente/alterado.</p>@endif
                                <br>{{ $d->revisor?->name }} {{ $d->revisado_at?->format('d/m/Y H:i') }}<p>{{ $d->motivo_revision }}</p><small>{{ $d->notas }}</small>
                            </td>
                            <td><a class="btn btn-secondary" href="{{ route('expedientes.descargar', [$cliente, $d]) }}">Descargar {{ $d->mime === 'application/pdf' ? 'PDF' : 'imagen' }}</a>
                                <details><summary>Datos del archivo</summary><small>{{ number_format($d->bytes / 1024, 1) }} KB · {{ $d->mime }}<br>SHA-256:</small><code style="overflow-wrap:anywhere">{{ $d->sha256 }}</code></details>
                                @can('expedientes.review')@if($d->vigente && $d->estado !== 'anulado')
                                    <details style="margin-top:.5rem"><summary>Registrar revisión</summary>
                                        <form action="{{ route('expedientes.revisar', [$cliente, $d]) }}" method="POST">@csrf
                                            <label>Resultado<select name="estado" class="form-input" required><option value="">Seleccione</option><option value="validado">Validado: corresponde y es legible</option><option value="rechazado">Rechazado: requiere corrección</option><option value="anulado">Anulado: conservar sin utilizar</option></select></label>
                                            <label>Motivo / comprobación realizada<textarea name="motivo" class="form-input" required minlength="8" maxlength="2000" rows="2"></textarea></label>
                                            <button class="btn btn-primary" type="submit">Registrar revisión</button>
                                        </form>
                                    </details>
                                @endif
@endcan
                            </td>
                        </tr>
                    @empty<tr><td colspan="4">Todavía no se han presentado comprobantes en este apartado.</td></tr>@endforelse
                </tbody></table></div>
            </div>
        </section>
    @endforeach

    @if($credito && $credito->estado === 'pendiente_aprobacion')@can('expedientes.manage')
        <div class="card" style="margin-bottom:1.5rem"><div class="card-body"><details><summary class="btn btn-secondary">Registrar otro fiador</summary>@include('expedientes.fiador', ['fiador' => null])</details></div></div>
    @endcan
@endif
    <div class="card"><div class="card-body"><h2>Historial de presentaciones, revisiones y accesos</h2>
        <div style="overflow-x:auto"><table class="data-table"><thead><tr><th>Fecha / responsable</th><th>Acción</th><th>Motivo y evidencia</th></tr></thead><tbody>
            @forelse($eventos as $evento)<tr><td>{{ $evento->created_at->format('d/m/Y H:i:s') }}<br>{{ $evento->usuario_nombre }}</td><td>{{ $evento->accion }}<br>@if($evento->documento_id) Comprobante #{{ $evento->documento_id }} @endif @if($evento->fiador_id) Fiador #{{ $evento->fiador_id }} @endif</td><td>{{ $evento->motivo }}<details><summary>Ver registro conservado</summary><pre style="white-space:pre-wrap;overflow-wrap:anywhere">{{ json_encode(['antes' => $evento->antes, 'despues' => $evento->despues], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></details></td></tr>
            @empty<tr><td colspan="3">Sin movimientos documentales.</td></tr>@endforelse
        </tbody></table></div>{{ $eventos->links() }}
    </div></div>
</x-app-layout>
