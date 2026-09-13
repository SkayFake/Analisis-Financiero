<x-app-layout>
    <x-slot name="title">Detalle Crédito {{ $credito->numero }}</x-slot>

    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Crédito {{ $credito->numero }}</h1>
            <div class="page-subtitle" style="display: flex; align-items: center; gap: 1rem;">
                <span style="font-weight: 600;">{{ $credito->productoCredito->nombre }}</span>
                <span>•</span>
                <a href="{{ route('clientes.show', $credito->cliente) }}" style="font-weight: 600; color: var(--primary-600);">
                    {{ $credito->cliente->nombre }}
                </a>
                @php
                    $colors = ['solicitado' => 'neutral', 'vigente' => 'success', 'vencido' => 'warning', 'incobrable' => 'danger', 'cancelado' => 'info'];
                @endphp
                <span class="badge badge-{{ $colors[$credito->estado] ?? 'neutral' }}">{{ strtoupper($credito->estado) }}</span>
            </div>
        </div>
        <div class="page-actions">
            @if(in_array($credito->estado, ['vigente', 'vencido']))
                <button onclick="document.getElementById('modalPago').style.display='flex'" class="btn btn-success">
                    Registrar Pago
                </button>
            @endif
        </div>
    </div>

    <div class="grid-stats">
        <div class="stat-card">
            <div class="stat-card-label">Monto Otorgado</div>
            <div class="stat-card-value">${{ number_format($credito->monto_original, 2) }}</div>
            <div class="stat-card-change" style="margin-top: 0.5rem; background: var(--neutral-100); color: var(--neutral-600);">
                Tasa: {{ number_format($credito->tasa_interes, 2) }}% anual
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Saldo Actual</div>
            <div class="stat-card-value">${{ number_format($credito->saldo_actual, 2) }}</div>
            <div class="stat-card-change" style="margin-top: 0.5rem; background: var(--neutral-100); color: var(--neutral-600);">
                Cuota mensual aprox.
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-card-label">Plazo</div>
            <div class="stat-card-value">{{ $credito->numero_cuotas }} meses</div>
            <div class="stat-card-change" style="margin-top: 0.5rem; background: var(--neutral-100); color: var(--neutral-600);">
                Vence: {{ $credito->fecha_vencimiento->format('d/m/Y') }}
            </div>
        </div>
        <div class="stat-card" style="--stat-color: {{ $credito->dias_mora > 0 ? 'var(--danger-500)' : 'var(--success-500)' }};">
            <div class="stat-card-label">Días en Mora</div>
            <div class="stat-card-value" style="color: {{ $credito->dias_mora > 0 ? 'var(--danger-600)' : 'var(--success-600)' }}">{{ $credito->dias_mora }}</div>
            @if($credito->cuota_pendiente)
            <div class="stat-card-change down" style="margin-top: 0.5rem;">
                Próx. pago: {{ $credito->cuota_pendiente->fecha_vencimiento->format('d/m/Y') }}
            </div>
            @endif
        </div>
    </div>

    <div class="grid-2" style="margin-top: 1.5rem;">
        {{-- Plan de Amortización --}}
        <div class="card" style="grid-column: span 2;">
            <div class="card-header">
                <h3 class="card-title">Tabla de Amortización (Primeras Cuotas)</h3>
            </div>
            <div class="card-body" style="padding: 0;">
                <div class="data-table-wrapper" style="max-height: 400px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Vencimiento</th>
                                <th class="text-right">Capital</th>
                                <th class="text-right">Interés</th>
                                <th class="text-right">Comisión</th>
                                <th class="text-right">Total Cuota</th>
                                <th class="text-right">Saldo Restante</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($credito->cuotas->take(12) as $cuota)
                            <tr>
                                <td>{{ $cuota->numero_cuota }}</td>
                                <td>{{ $cuota->fecha_vencimiento->format('d/m/Y') }}</td>
                                <td style="text-align: right;">${{ number_format($cuota->capital, 2) }}</td>
                                <td style="text-align: right;">${{ number_format($cuota->interes, 2) }}</td>
                                <td style="text-align: right;">${{ number_format($cuota->comision, 2) }}</td>
                                <td style="text-align: right; font-weight: 600;">${{ number_format($cuota->total, 2) }}</td>
                                <td style="text-align: right;">${{ number_format($cuota->saldo_pendiente, 2) }}</td>
                                <td>
                                    @php
                                        $cEstado = $cuota->estado === 'pagada' ? 'success' : ($cuota->fecha_vencimiento < now() && $cuota->estado !== 'pagada' ? 'danger' : 'neutral');
                                    @endphp
                                    <span class="badge badge-{{ $cEstado }}">{{ ucfirst($cuota->estado) }}</span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
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
                                <th class="text-right">Interés+Comisión</th>
                                <th class="text-right">Mora</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($credito->pagos as $pago)
                            <tr>
                                <td style="font-family: monospace;">{{ $pago->numero_recibo }}</td>
                                <td>{{ $pago->fecha_pago->format('d/m/Y H:i') }}</td>
                                <td>{{ ucfirst($pago->forma_pago) }}</td>
                                <td style="text-align: right;">${{ number_format($pago->abono_capital, 2) }}</td>
                                <td style="text-align: right;">${{ number_format($pago->pago_interes + $pago->pago_comision, 2) }}</td>
                                <td style="text-align: right; color: var(--danger-600);">${{ number_format($pago->pago_mora, 2) }}</td>
                                <td style="text-align: right; font-weight: 600; color: var(--success-600);">${{ number_format($pago->monto_total, 2) }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state" style="padding: 1.5rem;">
                                        <p class="empty-state-text" style="margin-bottom: 0;">No se han registrado pagos para este crédito.</p>
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

    {{-- Modal Registrar Pago --}}
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
                    <div class="alert alert-info">
                        <strong>Cuota Sugerida:</strong> ${{ number_format($credito->cuota_pendiente?->total ?? 0, 2) }}<br>
                        <strong>Mora Acumulada Aprox:</strong> ${{ number_format(app(\App\Services\CreditoService::class)->calcularMora($credito)['total_mora'], 2) }}
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Monto a Recibir ($)</label>
                        <input type="number" step="0.01" name="monto" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pago</label>
                        <select name="forma_pago" class="form-select" required>
                            <option value="efectivo">Efectivo</option>
                            <option value="transferencia">Transferencia Bancaria</option>
                            <option value="cheque">Cheque</option>
                            <option value="tarjeta">Tarjeta de Crédito/Débito</option>
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
</x-app-layout>
