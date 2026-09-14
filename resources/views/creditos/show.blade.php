<x-app-layout>
    <x-slot name="title">Crédito {{ $credito->numero }}</x-slot>

    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">{{ $credito->numero }}</h1>
            <div class="page-subtitle" style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                <span style="font-weight: 600;">{{ $credito->condicionCredito?->nombre ?? 'Sin condición' }}</span>
                <span>•</span>
                <a href="{{ route('clientes.show', $credito->cliente) }}" style="font-weight: 600; color: var(--primary-600);">
                    {{ $credito->cliente->nombre }}
                </a>
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
                        'pendiente_aprobacion' => 'PENDIENTE APROBACIÓN',
                        'aprobado'   => 'APROBADO',
                        'vigente'    => 'VIGENTE',
                        'vencido'    => 'VENCIDO',
                        'incobrable' => 'INCOBRABLE',
                        'cancelado'  => 'CANCELADO',
                        'rechazado'  => 'RECHAZADO',
                        'refinanciado' => 'REFINANCIADO',
                    ];
                @endphp
                <span class="badge badge-{{ $colors[$credito->estado] ?? 'neutral' }}">
                    {{ $labels[$credito->estado] ?? strtoupper($credito->estado) }}
                </span>
            </div>
        </div>
        <div class="page-actions" style="display: flex; gap: 0.75rem;">
            @if(in_array($credito->estado, ['vigente', 'vencido']))
                <button onclick="document.getElementById('modalPago').style.display='flex'" class="btn btn-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Registrar Pago
                </button>
            @endif
            @if($credito->estado === 'pendiente_aprobacion')
                <button onclick="document.getElementById('modalAprobar').style.display='flex'" class="btn btn-primary">
                    ✓ Aprobar Crédito
                </button>
                <button onclick="document.getElementById('modalRechazar').style.display='flex'" class="btn btn-secondary" style="color: var(--danger-600);">
                    ✕ Rechazar
                </button>
            @endif
        </div>
    </div>

    {{-- Alerta de pendiente de aprobación --}}
    @if($credito->estado === 'pendiente_aprobacion')
    <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
        <strong>⚠ Este crédito requiere aprobación manual.</strong>
        El cliente no cumple los criterios de aprobación automática
        (historial de compras insuficiente o monto supera el límite auto-aprobado).
        Revisa la documentación del cliente antes de aprobar.
    </div>
    @endif

    {{-- Resumen de la factura de origen --}}
    @if($credito->venta)
    <div class="card" style="margin-bottom: 1.5rem; border-left: 4px solid var(--primary-500);">
        <div class="card-body" style="display: flex; align-items: center; gap: 2rem; flex-wrap: wrap;">
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">FACTURA DE ORIGEN</div>
                <div style="font-family: monospace; font-weight: 700; font-size: 1rem; color: var(--primary-700);">
                    {{ $credito->venta->numero_control }}
                </div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">FECHA EMISIÓN</div>
                <div style="font-weight: 600;">{{ $credito->venta->fecha_emision?->format('d/m/Y') }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">TIPO DOCUMENTO</div>
                <div style="font-weight: 600;">
                    @php $tiposDoc = ['01'=>'FCF','03'=>'CCF','11'=>'Ticket','05'=>'NC','06'=>'ND']; @endphp
                    {{ $tiposDoc[$credito->venta->tipo_documento] ?? $credito->venta->tipo_documento }}
                </div>
            </div>
            <div style="margin-left: auto;">
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">TOTAL FACTURA</div>
                <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-700);">
                    ${{ number_format($credito->venta->total_pagar, 2) }}
                </div>
            </div>
            {{-- Mercadería comprada --}}
            @if($credito->venta->detalles->count() > 0)
            <div style="width: 100%; border-top: 1px solid var(--neutral-200); padding-top: 0.75rem; margin-top: 0.25rem;">
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 0.05em;">
                    Mercadería comprada a crédito
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    @foreach($credito->venta->detalles as $det)
                    <div style="background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: 0.5rem; padding: 0.4rem 0.75rem; font-size: 0.82rem;">
                        <strong>{{ $det->cantidad }} ×</strong> {{ $det->producto?->nombre ?? 'Producto' }}
                        <span style="color: var(--neutral-500);">(${{ number_format($det->precio_unitario, 2) }} c/u)</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>
    @endif

    {{-- Stats cards --}}
    <div class="grid-stats">
        <div class="stat-card">
            <div class="stat-card-label">Valor Mercadería a Crédito</div>
            <div class="stat-card-value">${{ number_format($credito->monto_original, 2) }}</div>
            <div class="stat-card-change" style="margin-top: 0.5rem; background: var(--neutral-100); color: var(--neutral-600);">
                {{ $credito->tipo_credito_label }}
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Saldo Pendiente</div>
            <div class="stat-card-value">${{ number_format($credito->saldo_actual, 2) }}</div>
            <div class="stat-card-change" style="margin-top: 0.5rem; background: var(--neutral-100); color: var(--neutral-600);">
                {{ number_format($credito->porcentaje_pagado, 1) }}% pagado
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Plan de Pago</div>
            <div class="stat-card-value">{{ $credito->numero_cuotas }} cuotas</div>
            <div class="stat-card-change" style="margin-top: 0.5rem; background: var(--neutral-100); color: var(--neutral-600);">
                {{ $credito->frecuencia_label }} · Vence: {{ $credito->fecha_vencimiento?->format('d/m/Y') }}
            </div>
        </div>
        <div class="stat-card" style="--stat-color: {{ $credito->dias_mora > 0 ? 'var(--danger-500)' : 'var(--success-500)' }};">
            <div class="stat-card-label">Mora</div>
            <div class="stat-card-value" style="color: {{ $credito->dias_mora > 0 ? 'var(--danger-600)' : 'var(--success-600)' }}">
                @if($credito->dias_mora > 0)
                    {{ $credito->dias_mora }} días · ${{ number_format($credito->mora_acumulada, 2) }}
                @else
                    Al día
                @endif
            </div>
            @if($credito->cuota_pendiente)
            <div class="stat-card-change {{ $credito->dias_mora > 0 ? 'down' : '' }}" style="margin-top: 0.5rem;">
                Próx. pago: {{ $credito->cuota_pendiente->fecha_vencimiento?->format('d/m/Y') }}
                (${{ number_format($credito->cuota_pendiente->total, 2) }})
            </div>
            @endif
        </div>
    </div>

    {{-- Condiciones del crédito --}}
    @if($credito->condicionCredito)
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header">
            <h3 class="card-title">Condiciones del Crédito</h3>
        </div>
        <div class="card-body" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem;">
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">CONDICIÓN</div>
                <div style="font-weight: 600;">{{ $credito->condicionCredito->nombre }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">INTERÉS ANUAL</div>
                <div style="font-weight: 600;">
                    @if((float)$credito->tasa_interes_anual > 0)
                        {{ number_format($credito->tasa_interes_anual, 2) }}%
                    @else
                        <span style="color: var(--success-600);">Sin interés</span>
                    @endif
                </div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">MORA MENSUAL</div>
                <div style="font-weight: 600;">{{ number_format($credito->tasa_mora_mensual, 2) }}%</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">DÍAS DE GRACIA</div>
                <div style="font-weight: 600;">{{ $credito->dias_gracia }} días</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">FRECUENCIA PAGO</div>
                <div style="font-weight: 600;">{{ $credito->frecuencia_label }}</div>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">PRIMERA CUOTA</div>
                <div style="font-weight: 600;">{{ $credito->fecha_primera_cuota?->format('d/m/Y') }}</div>
            </div>
            @if($credito->aprobado_por)
            <div>
                <div style="font-size: 0.75rem; color: var(--neutral-500); margin-bottom: 0.25rem;">APROBADO POR</div>
                <div style="font-weight: 600;">{{ $credito->aprobador?->name ?? '—' }}</div>
                <div style="font-size: 0.75rem; color: var(--neutral-400);">{{ $credito->fecha_aprobacion?->format('d/m/Y') }}</div>
            </div>
            @endif
        </div>
        @if($credito->motivo_aprobacion)
        <div class="card-footer" style="font-size: 0.82rem; color: var(--neutral-600);">
            <strong>Nota de aprobación:</strong> {{ $credito->motivo_aprobacion }}
        </div>
        @endif
    </div>
    @endif

    <div class="grid-2" style="margin-top: 1.5rem;">
        {{-- Plan de Cuotas --}}
        <div class="card" style="grid-column: span 2;">
            <div class="card-header">
                <h3 class="card-title">Plan de Cuotas</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                @if($credito->cuotas->isEmpty())
                    <div class="empty-state" style="padding: 2rem;">
                        @if($credito->estado === 'pendiente_aprobacion')
                            <p class="empty-state-text">El plan de cuotas se generará al aprobar el crédito.</p>
                        @else
                            <p class="empty-state-text">No hay cuotas generadas para este crédito.</p>
                        @endif
                    </div>
                @else
                <div class="data-table-wrapper" style="max-height: 400px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Vencimiento</th>
                                <th class="text-right">Capital</th>
                                @if($credito->tiene_interes)
                                <th class="text-right">Interés</th>
                                @endif
                                <th class="text-right">Total Cuota</th>
                                <th class="text-right">Mora</th>
                                <th class="text-right">Saldo Restante</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($credito->cuotas as $cuota)
                            <tr style="{{ $cuota->esta_vencida ? 'background: rgba(239,68,68,0.04);' : '' }}">
                                <td>{{ $cuota->numero_cuota }}</td>
                                <td>{{ $cuota->fecha_vencimiento?->format('d/m/Y') }}</td>
                                <td style="text-align: right;">${{ number_format($cuota->capital, 2) }}</td>
                                @if($credito->tiene_interes)
                                <td style="text-align: right;">${{ number_format($cuota->interes, 2) }}</td>
                                @endif
                                <td style="text-align: right; font-weight: 600;">${{ number_format($cuota->total, 2) }}</td>
                                <td style="text-align: right; color: {{ $cuota->mora > 0 ? 'var(--danger-600)' : 'var(--neutral-400)' }};">
                                    @if($cuota->mora > 0)
                                        ${{ number_format($cuota->mora, 2) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td style="text-align: right;">${{ number_format($cuota->saldo_pendiente, 2) }}</td>
                                <td>
                                    @php
                                        $cEstado = match($cuota->estado) {
                                            'pagada'   => 'success',
                                            'vencida'  => 'danger',
                                            'parcial'  => 'warning',
                                            default    => 'neutral',
                                        };
                                    @endphp
                                    <span class="badge badge-{{ $cEstado }}">{{ ucfirst($cuota->estado) }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        {{-- Historial de Pagos --}}
        <div class="card" style="grid-column: span 2;">
            <div class="card-header">
                <h3 class="card-title">Historial de Pagos</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Recibo</th>
                                <th>Fecha</th>
                                <th>Forma Pago</th>
                                <th class="text-right">Capital</th>
                                <th class="text-right">Interés</th>
                                <th class="text-right">Mora</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Saldo Después</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($credito->pagos as $pago)
                            <tr>
                                <td style="font-family: monospace; font-size: 0.8rem;">{{ $pago->numero_recibo }}</td>
                                <td>{{ $pago->fecha_pago?->format('d/m/Y') }}</td>
                                <td>{{ ucfirst($pago->forma_pago) }}</td>
                                <td style="text-align: right;">${{ number_format($pago->abono_capital, 2) }}</td>
                                <td style="text-align: right;">${{ number_format($pago->pago_interes, 2) }}</td>
                                <td style="text-align: right; color: var(--danger-600);">${{ number_format($pago->pago_mora, 2) }}</td>
                                <td style="text-align: right; font-weight: 600; color: var(--success-600);">${{ number_format($pago->monto_total, 2) }}</td>
                                <td style="text-align: right; color: var(--neutral-600);">${{ number_format($pago->saldo_despues, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8">
                                    <div class="empty-state" style="padding: 1.5rem;">
                                        <p class="empty-state-text" style="margin-bottom: 0;">No se han registrado pagos.</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal: Registrar Pago --}}
    <div id="modalPago" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Registrar Pago</h3>
                <button type="button" class="modal-close" onclick="document.getElementById('modalPago').style.display='none'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form action="{{ route('creditos.pagos.store', $credito) }}" method="POST">
                @csrf
                <div class="modal-body">
                    @if($credito->cuota_pendiente)
                    <div class="alert alert-info">
                        <strong>Cuota #{{ $credito->cuota_pendiente->numero_cuota }} — Sugerido:</strong>
                        ${{ number_format($credito->cuota_pendiente->total_con_mora, 2) }}
                        @if((float)$credito->cuota_pendiente->mora > 0)
                            <br><span style="color: var(--danger-600);">
                                Incluye mora: ${{ number_format($credito->cuota_pendiente->mora, 2) }}
                                ({{ $credito->cuota_pendiente->dias_mora }} días × {{ number_format($credito->tasa_mora_mensual, 2) }}%/mes)
                            </span>
                        @endif
                    </div>
                    @endif

                    <div class="form-group">
                        <label class="form-label">Monto a Recibir ($)</label>
                        <input type="number" step="0.01" name="monto" class="form-input" required
                            value="{{ $credito->cuota_pendiente?->total_con_mora }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pago</label>
                        <select name="forma_pago" class="form-select" required>
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia Bancaria</option>
                            <option value="cheque">Cheque</option>
                            <option value="tarjeta">Tarjeta</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Referencia / Comprobante (Opcional)</label>
                        <input type="text" name="referencia" class="form-input" placeholder="Ej. REF-409182">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalPago').style.display='none'">Cancelar</button>
                    <button type="submit" class="btn btn-success">Procesar Pago</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal: Aprobar Crédito --}}
    @if($credito->estado === 'pendiente_aprobacion')
    <div id="modalAprobar" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Aprobar Crédito {{ $credito->numero }}</h3>
                <button type="button" class="modal-close" onclick="document.getElementById('modalAprobar').style.display='none'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form action="{{ route('creditos.aprobar', $credito) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="alert alert-info" style="margin-bottom: 1rem;">
                        Al aprobar, se generará automáticamente el plan de cuotas y el crédito quedará vigente.
                        La aprobación queda registrada según <strong>Ley de Protección al Consumidor (D.776) El Salvador</strong>.
                    </div>
                    <div class="form-group">
                        <label class="form-label">Motivo de Aprobación <span class="required">*</span></label>
                        <textarea name="motivo" class="form-input" rows="3" required minlength="10"
                            placeholder="Ej: Cliente presentó DUI, referencias verificadas, historial de pagos favorable..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalAprobar').style.display='none'">Cancelar</button>
                    <button type="submit" class="btn btn-primary">✓ Confirmar Aprobación</button>
                </div>
            </form>
        </div>
    </div>

    <div id="modalRechazar" class="modal-overlay" style="display: none;">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Rechazar Crédito {{ $credito->numero }}</h3>
                <button type="button" class="modal-close" onclick="document.getElementById('modalRechazar').style.display='none'">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form action="{{ route('creditos.rechazar', $credito) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Motivo de Rechazo <span class="required">*</span></label>
                        <textarea name="motivo" class="form-input" rows="3" required minlength="10"
                            placeholder="Ej: Documentación incompleta, referencias negativas, capacidad de pago insuficiente..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" onclick="document.getElementById('modalRechazar').style.display='none'">Cancelar</button>
                    <button type="submit" class="btn btn-secondary" style="color: var(--danger-600);">✕ Confirmar Rechazo</button>
                </div>
            </form>
        </div>
    </div>
    @endif

</x-app-layout>
