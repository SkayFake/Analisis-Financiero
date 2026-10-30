<form method="POST" action="{{ $fiador ? route('expedientes.fiador.actualizar', [$credito, $fiador]) : route('expedientes.fiador.crear', $credito) }}" style="margin-top:1rem">
    @csrf @if($fiador) @method('PUT') @endif
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem">
        <label>Nombre completo *<input class="form-input" name="nombre" value="{{ $fiador?->nombre }}" required maxlength="150"></label>
        <label>DUI *<input class="form-input" name="dui" value="{{ $fiador?->dui }}" required maxlength="10" placeholder="00000000-0" inputmode="numeric" data-mask="dui"></label>
        <label>NIT, si corresponde<input class="form-input" name="nit" value="{{ $fiador?->nit }}" maxlength="17" placeholder="DUI homologado o NIT de 14 dígitos" inputmode="numeric"></label>
        <label>Teléfono de El Salvador *<input class="form-input" name="telefono" value="{{ $fiador?->telefono }}" required maxlength="14" placeholder="7123-4567" inputmode="tel"></label>
        <label>Dirección *<input class="form-input" name="direccion" value="{{ $fiador?->direccion }}" required maxlength="250"></label>
        <label>Lugar de trabajo / actividad económica<input class="form-input" name="lugar_trabajo" value="{{ $fiador?->lugar_trabajo }}" maxlength="255"></label>
        <label>Ingresos mensuales USD *<input class="form-input" type="number" name="ingresos" value="{{ $fiador?->ingresos ?? '0.00' }}" required min="0" max="999999999999.99" step="0.01"></label>
        <label>Egresos mensuales USD *<input class="form-input" type="number" name="egresos" value="{{ $fiador?->egresos ?? '0.00' }}" required min="0" max="999999999999.99" step="0.01"></label>
    </div>
    <label>Observaciones<textarea class="form-input" name="observaciones" maxlength="2000" rows="2">{{ $fiador?->observaciones }}</textarea></label>
    <label>Motivo del registro / cambio *<input class="form-input" name="motivo" required minlength="8" maxlength="2000"></label>
    <p>Los datos se guardan con historial. Si cambia identidad, domicilio o datos de ingresos, presente nuevamente los comprobantes afectados.</p>
    <button class="btn btn-primary" type="submit">Guardar datos del fiador</button>
</form>
