<x-app-layout>
    <x-slot name="title">Registrar Crédito Comercial</x-slot>

    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Registrar Crédito Comercial</h1>
            <p class="page-subtitle">
                Vincula una factura al crédito con su plan de pagos. Los créditos se generan
                automáticamente desde el Punto de Venta; usa este formulario solo si necesitas
                crear uno manualmente.
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('creditos.index') }}" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                </svg>
                <span>Volver a Créditos</span>
            </a>
        </div>
    </div>

@php
    $ventaInfoJson = $ventaPreseleccionada ? json_encode([
        'numero_control' => $ventaPreseleccionada->numero_control,
        'total_pagar'    => $ventaPreseleccionada->total_pagar,
        'cliente'        => $ventaPreseleccionada->cliente?->nombre ?? '—',
        'fecha_emision'  => $ventaPreseleccionada->fecha_emision?->format('d/m/Y'),
    ]) : 'null';
    $ventaTotalFactura = $ventaPreseleccionada?->total_pagar ?? 0;
    $ventaPreselId     = $ventaPreseleccionada?->id ?? '';
    $tienePresel       = $ventaPreseleccionada ? 'true' : 'false';
@endphp

    <div class="card" style="max-width: 900px;" x-data="{
        ventaSeleccionada: {{ $tienePresel }},
        ventaId: '{{ $ventaPreselId }}',
        ventaInfo: {{ $ventaInfoJson }},
        condicionFrecuencia: 'mensual',
        numeroCuotas: 1,
        cuotaEstimada: 0,
        totalFactura: {{ $ventaTotalFactura }},

        calcularCuota() {
            if (this.numeroCuotas > 0 && this.totalFactura > 0) {
                this.cuotaEstimada = (this.totalFactura / this.numeroCuotas).toFixed(2);
            }
        },
        seleccionarVenta(id, numeroControl, total, cliente, fecha) {
            this.ventaId = id;
            this.totalFactura = parseFloat(total);
            this.ventaInfo = { numero_control: numeroControl, total_pagar: total, cliente: cliente, fecha_emision: fecha };
            this.ventaSeleccionada = true;
            this.calcularCuota();
        }
    }">
        <form action="{{ route('creditos.store') }}" method="POST">
            @csrf
            <input type="hidden" name="venta_id" x-model="ventaId">

            <div class="card-body" style="display: flex; flex-direction: column; gap: 1.5rem;">

                {{-- SECCIÓN 1: Factura de origen --}}
                <div>
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        1. Factura de Origen del Crédito
                    </h3>
                    <p style="font-size: 0.875rem; color: var(--neutral-500); margin-bottom: 1rem;">
                        El monto del crédito es igual al total de la factura. Selecciona la factura emitida al crédito.
                    </p>

                    {{-- Resumen de venta seleccionada --}}
                    <div x-show="ventaSeleccionada" class="alert alert-info" style="margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong style="font-family: monospace;" x-text="ventaInfo?.numero_control"></strong>
                                — <span x-text="ventaInfo?.cliente"></span>
                                — Emitida: <span x-text="ventaInfo?.fecha_emision"></span>
                            </div>
                            <div style="font-size: 1.25rem; font-weight: 700; color: var(--primary-700);">
                                $<span x-text="parseFloat(ventaInfo?.total_pagar ?? 0).toFixed(2)"></span>
                            </div>
                        </div>
                        <button type="button" @click="ventaSeleccionada=false;ventaId='';ventaInfo=null;totalFactura=0;cuotaEstimada=0"
                            style="margin-top: 0.5rem; font-size: 0.8rem; color: var(--danger-600); background: none; border: none; cursor: pointer;">
                            ✕ Cambiar factura
                        </button>
                    </div>

                    {{-- Tabla de facturas sin crédito --}}
                    <div x-show="!ventaSeleccionada">
                        @error('venta_id')<div class="alert alert-danger">{{ $message }}</div>@enderror

                        @if($ventasSinCredito->isEmpty())
                            <div class="alert alert-warning">
                                No hay facturas al crédito pendientes de asignar. Las facturas al crédito se generan desde el
                                <a href="{{ route('facturacion.pos') }}">Punto de Venta</a>.
                            </div>
                        @else
                        <div class="data-table-wrapper" style="max-height: 280px;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Factura</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th class="text-right">Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($ventasSinCredito as $v)
                                    <tr>
                                        <td style="font-family: monospace; font-size: 0.8rem;">{{ $v->numero_control }}</td>
                                        <td>{{ $v->fecha_emision?->format('d/m/Y') }}</td>
                                        <td>{{ $v->cliente?->nombre ?? 'Consumidor Final' }}</td>
                                        <td style="text-align: right; font-weight: 600;">${{ number_format($v->total_pagar, 2) }}</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary"
                                                @click="seleccionarVenta(
                                                    '{{ $v->id }}',
                                                    '{{ $v->numero_control }}',
                                                    '{{ $v->total_pagar }}',
                                                    '{{ addslashes($v->cliente?->nombre ?? 'Consumidor Final') }}',
                                                    '{{ $v->fecha_emision?->format('d/m/Y') }}'
                                                )">
                                                Seleccionar
                                            </button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- SECCIÓN 2: Condiciones del crédito --}}
                <div style="padding-top: 1rem; border-top: 1px solid var(--neutral-200);">
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        2. Condiciones del Crédito
                    </h3>
                    <div class="grid-2">
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Condición de Crédito <span class="required">*</span></label>
                            <select name="condicion_credito_id" class="form-select @error('condicion_credito_id') error @enderror"
                                required @change="condicionFrecuencia = $event.target.selectedOptions[0]?.dataset.frecuencia ?? 'mensual'; calcularCuota()">
                                <option value="">Seleccione las condiciones...</option>
                                @foreach($condiciones as $cond)
                                    <option value="{{ $cond->id }}"
                                        data-frecuencia="{{ $cond->frecuencia_pago }}"
                                        {{ old('condicion_credito_id') == $cond->id ? 'selected' : '' }}>
                                        {{ $cond->nombre }}
                                        ({{ $cond->frecuencia_label }}, mora: {{ number_format($cond->tasa_mora_mensual, 1) }}%/mes{{ $cond->tasa_interes_anual > 0 ? ', interés: ' . number_format($cond->tasa_interes_anual, 2) . '% anual' : '' }})
                                    </option>
                                @endforeach
                            </select>
                            @error('condicion_credito_id')<span class="form-error">{{ $message }}</span>@enderror
                            <span class="form-hint">
                                El interés (si aplica) y la mora están regulados por la Ley de Protección al Consumidor (D.776) de El Salvador.
                            </span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Número de Cuotas <span class="required">*</span></label>
                            <input type="number" name="numero_cuotas" x-model.number="numeroCuotas"
                                @input="calcularCuota()"
                                class="form-input @error('numero_cuotas') error @enderror"
                                required min="1" max="120" value="{{ old('numero_cuotas', 1) }}">
                            @error('numero_cuotas')<span class="form-error">{{ $message }}</span>@enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cuota Estimada</label>
                            <div class="form-input" style="background: var(--neutral-50); font-weight: 700; font-size: 1.1rem; color: var(--primary-700);">
                                $<span x-text="cuotaEstimada"></span>
                                <span style="font-size: 0.78rem; font-weight: 400; color: var(--neutral-500);" x-text="' / ' + condicionFrecuencia"></span>
                            </div>
                            <span class="form-hint">Estimado sin interés. El monto real depende de la condición seleccionada.</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Fecha de Primera Cuota <span class="required">*</span></label>
                            <input type="date" name="fecha_primera_cuota"
                                class="form-input @error('fecha_primera_cuota') error @enderror"
                                value="{{ old('fecha_primera_cuota', now()->addMonth()->toDateString()) }}"
                                required min="{{ now()->addDay()->toDateString() }}">
                            @error('fecha_primera_cuota')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN 3: Asignación y documentación --}}
                <div style="padding-top: 1rem; border-top: 1px solid var(--neutral-200);">
                    <h3 style="font-family: var(--font-heading); font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem; color: var(--neutral-900);">
                        3. Asignación y Documentación
                    </h3>
                    <div class="grid-2">
                        <div class="form-group">
                            <label class="form-label">Gestor / Asesor</label>
                            <select name="vendedor_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($vendedores as $v)
                                    <option value="{{ $v->id }}" {{ old('vendedor_id') == $v->id ? 'selected' : '' }}>{{ $v->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cartera</label>
                            <select name="cartera_id" class="form-select">
                                <option value="">(Sin asignar)</option>
                                @foreach($carteras as $c)
                                    <option value="{{ $c->id }}" {{ old('cartera_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Documentación requerida por LPC El Salvador --}}
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Documentación Verificada</label>
                            <div style="display: flex; gap: 2rem; flex-wrap: wrap; margin-top: 0.5rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="dui_verificado" value="1" {{ old('dui_verificado') ? 'checked' : '' }}>
                                    <span>DUI verificado <span style="font-size: 0.75rem; color: var(--neutral-500);">(requerido por Ley AML)</span></span>
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                                    <input type="checkbox" name="referencia_verificada" value="1" {{ old('referencia_verificada') ? 'checked' : '' }}>
                                    <span>Referencias comerciales verificadas</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-input" rows="2"
                                placeholder="Garantías, condiciones especiales, etc.">{{ old('observaciones') }}</textarea>
                        </div>
                    </div>
                </div>

            </div>

            <div class="card-footer" style="display: flex; justify-content: flex-end; gap: 1rem;">
                <a href="{{ route('creditos.index') }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary" :disabled="!ventaSeleccionada">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Crear Crédito Comercial</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
