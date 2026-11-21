<x-app-layout>
    <x-slot name="title">Centro de Notificaciones y Control de Respuestas</x-slot>

    {{-- Encabezado de Página --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Notificaciones de Cobro y Control de Respuestas</h1>
            <p class="page-subtitle">
                Seguimiento de cobros pendientes y realizados vía correo electrónico o llamadas, con control estricto de destinatarios y registro de respuestas del cliente.
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('cobros.secuencia') }}" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
                <span>Plan de Secuencia</span>
            </a>
            <a href="{{ route('cobros.dashboard') }}" class="btn btn-secondary">
                <span>Dashboard de Cobros</span>
            </a>
        </div>
    </div>

    {{-- KPIs de Notificaciones y Respuestas --}}
    <div class="grid-stats" style="margin-bottom: 1.5rem;">
        <div class="stat-card" style="--stat-color: var(--primary-600); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Total Notificaciones</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value">{{ $metricas['total'] }}</div>
            <div class="stat-card-change" style="background: var(--neutral-100); color: var(--neutral-700);">
                {{ $metricas['correos'] }} correos • {{ $metricas['llamadas'] }} llamadas
            </div>
        </div>

        <div class="stat-card" style="--stat-color: var(--success-600); --stat-bg: var(--success-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Tasa de Respuesta</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--success-600);">{{ $metricas['tasa_respuesta'] }}%</div>
            <div class="stat-card-change up">
                {{ $metricas['respondidas'] }} clientes respondieron
            </div>
        </div>

        <div class="stat-card" style="--stat-color: var(--warning-600); --stat-bg: var(--warning-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">En Espera de Respuesta</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--warning-600);">{{ $metricas['sin_respuesta'] }}</div>
            <div class="stat-card-change down">
                Requieren nuevo intento o escalamiento
            </div>
        </div>

        <div class="stat-card" style="--stat-color: var(--primary-500); --stat-bg: var(--primary-50);">
            <div class="stat-card-top">
                <div class="stat-card-label">Promesas de Pago</div>
                <div class="stat-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>
            <div class="stat-card-value" style="color: var(--primary-700);">{{ $metricas['promesas'] }}</div>
            <div class="stat-card-change" style="background: var(--neutral-100); color: var(--neutral-700);">
                Compromisos registrados
            </div>
        </div>
    </div>

    {{-- Grid: Control de Destinatarios y Formulario de Envío --}}
    <div class="grid-2" style="margin-bottom: 2rem; align-items: start;">
        {{-- Bloque Izquierdo: Selección y Control de Destinatarios --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">1. Control de Clientes Destinatarios</h3>
                    <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                        Filtre y seleccione al cliente al cual se enviará la notificación o se registrará la llamada.
                    </p>
                </div>
            </div>
            <div class="card-body">
                {{-- Filtros de Criterio --}}
                <form method="GET" action="{{ route('cobros.notificaciones') }}" style="display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <a href="{{ route('cobros.notificaciones', ['criterio' => 'vencidos']) }}" class="btn {{ $criterio === 'vencidos' ? 'btn-primary' : 'btn-secondary' }}" style="font-size: 0.8125rem;">
                            En Mora
                        </a>
                        <a href="{{ route('cobros.notificaciones', ['criterio' => 'por_vencer']) }}" class="btn {{ $criterio === 'por_vencer' ? 'btn-primary' : 'btn-secondary' }}" style="font-size: 0.8125rem;">
                            Por Vencer (Preventivo)
                        </a>
                        <a href="{{ route('cobros.notificaciones', ['criterio' => 'pendientes']) }}" class="btn {{ $criterio === 'pendientes' ? 'btn-primary' : 'btn-secondary' }}" style="font-size: 0.8125rem;">
                            Todos los Saldos
                        </a>
                        <a href="{{ route('cobros.notificaciones', ['criterio' => 'realizados']) }}" class="btn {{ $criterio === 'realizados' ? 'btn-primary' : 'btn-secondary' }}" style="font-size: 0.8125rem;">
                            Pagos Realizados
                        </a>
                    </div>

                    <div style="display: flex; gap: 0.5rem;">
                        <input type="hidden" name="criterio" value="{{ $criterio }}">
                        <input type="text" name="buscar" value="{{ $buscar }}" class="form-control" placeholder="Buscar cliente por nombre, código o DUI...">
                        <button type="submit" class="btn btn-secondary">Buscar</button>
                    </div>
                </form>

                {{-- Lista de Destinatarios Disponibles --}}
                <div style="max-height: 420px; overflow-y: auto; display: flex; flex-direction: column; gap: 0.5rem; border: 1px solid var(--neutral-200); border-radius: var(--radius-md); padding: 0.5rem;">
                    @forelse($destinatarios as $cli)
                        @php
                            $creditoActivo = $cli->creditos->first();
                            $contactoEmail = $cli->email ?? '';
                            $contactoTel = $cli->celular ?? $cli->telefono ?? '';
                            $saldoTotal = $cli->creditos->sum('saldo_actual');
                            $diasMoraMax = $cli->creditos->max('dias_mora') ?? 0;
                        @endphp
                        <div style="background: #ffffff; border: 1px solid var(--neutral-200); border-radius: var(--radius-sm); padding: 0.75rem; display: flex; justify-content: space-between; align-items: center; gap: 0.75rem;">
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 700; color: var(--neutral-900); font-size: 0.875rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    {{ $cli->nombre }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--neutral-500); display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.2rem;">
                                    <span>{{ $cli->codigo }}</span>
                                    <span>•</span>
                                    <span>Email: {{ $contactoEmail ?: 'Sin email' }}</span>
                                    <span>•</span>
                                    <span>Tel: {{ $contactoTel ?: 'Sin tel.' }}</span>
                                </div>
                                <div style="font-size: 0.75rem; margin-top: 0.25rem;">
                                    <strong style="color: var(--neutral-900);">Saldo: ${{ number_format($saldoTotal, 2) }}</strong>
                                    @if($diasMoraMax > 0)
                                        <span class="badge badge-warning" style="font-size: 0.7rem; margin-left: 0.5rem;">
                                            {{ $diasMoraMax }} d. mora
                                        </span>
                                    @endif
                                </div>
                            </div>
                            <button type="button" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; white-space: nowrap;"
                                onclick="seleccionarCliente('{{ $cli->id }}', '{{ addslashes($cli->nombre) }}', '{{ $creditoActivo?->id ?? '' }}', '{{ $creditoActivo?->numero ?? '' }}', '{{ $contactoEmail }}', '{{ $contactoTel }}', '{{ $saldoTotal }}', '{{ $diasMoraMax }}')">
                                Seleccionar →
                            </button>
                        </div>
                    @empty
                        <div style="text-align: center; padding: 2rem 1rem; color: var(--neutral-400); font-size: 0.875rem;">
                            No se encontraron clientes bajo este criterio.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Bloque Derecho: Formulario de Emisión de Notificación / Llamada --}}
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title">2. Emitir Notificación o Registrar Llamada</h3>
                    <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                        Envío de cobros pendientes y realizados por correo o registro de llamada de cobranza.
                    </p>
                </div>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('cobros.notificaciones.enviar') }}" id="formNotificacion">
                    @csrf
                    <input type="hidden" name="cliente_id" id="inputClienteId" required>
                    <input type="hidden" name="credito_id" id="inputCreditoId">

                    {{-- Indicador de Cliente Seleccionado --}}
                    <div id="boxClienteSeleccionado" style="background: var(--primary-50); border: 1px solid var(--primary-200); border-radius: var(--radius-md); padding: 0.75rem 1rem; margin-bottom: 1rem; display: none;">
                        <div style="font-size: 0.75rem; font-weight: 700; color: var(--primary-800); text-transform: uppercase;">Cliente Seleccionado:</div>
                        <div style="font-size: 1rem; font-weight: 700; color: var(--primary-900);" id="lblClienteNombre"></div>
                        <div style="font-size: 0.8125rem; color: var(--primary-700);" id="lblClienteDetalle"></div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label class="form-label">Canal de Contacto</label>
                            <select name="canal" id="selectCanal" class="form-control" required onchange="cambiarCanal(this.value)">
                                <option value="correo">📧 Correo Electrónico</option>
                                <option value="llamada">📞 Llamada Telefónica</option>
                                <option value="notificacion_escrita">📄 Notificación Escrita / Carta</option>
                                <option value="visita">🚶 Visita Presencial</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Tipo de Notificación</label>
                            <select name="tipo_notificacion" id="selectTipo" class="form-control" required onchange="sugerirMensaje()">
                                <option value="cobro_vencido">Cobro Vencido (Mora)</option>
                                <option value="cobro_pendiente">Cobro Pendiente / Preventivo</option>
                                <option value="pago_realizado">Confirmación de Pago Realizado</option>
                                <option value="aviso_secuencia">Aviso Formal de Secuencia</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label class="form-label" id="lblDestinatario">Correo o Teléfono de Destino</label>
                            <input type="text" name="destinatario_contacto" id="inputDestinatario" class="form-control" required placeholder="correo@ejemplo.com o 7000-0000">
                        </div>

                        <div>
                            <label class="form-label">Etapa de Secuencia (Opcional)</label>
                            <select name="etapa_secuencia" class="form-control">
                                <option value="">Conservar etapa actual</option>
                                <option value="notificacion_escrita">1. Notificación Escrita</option>
                                <option value="llamada">2. Llamada Telefónica</option>
                                <option value="visita">3. Visita Presencial</option>
                                <option value="agencia_cobranza">4. Agencia de Cobranza</option>
                                <option value="recurso_legal">5. Recurso Legal</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label class="form-label">Asunto / Título del Mensaje</label>
                        <input type="text" name="asunto" id="inputAsunto" class="form-control" required placeholder="Ej: Recordatorio oficial de cobro...">
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label class="form-label">Mensaje o Guion de Llamada</label>
                        <textarea name="mensaje" id="textMensaje" rows="4" class="form-control" required placeholder="Redacte el mensaje de notificación o las notas/guion de la llamada realizada..."></textarea>
                    </div>

                    {{-- Control de Respuesta Inmediata (especialmente para llamadas) --}}
                    <div style="background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: var(--radius-md); padding: 1rem; margin-bottom: 1.25rem;">
                        <div style="font-size: 0.8125rem; font-weight: 700; color: var(--neutral-800); margin-bottom: 0.5rem;">
                            ¿El cliente respondió en esta llamada o gestión? (Control de Respuesta)
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <label class="form-label">Estado de Respuesta del Cliente</label>
                                <select name="estado_respuesta" id="selectRespuestaInmediata" class="form-control" onchange="togglePromesa(this.value)">
                                    <option value="pendiente_respuesta">Pendiente de respuesta (En espera)</option>
                                    <option value="contesto_llamada">Contestó la llamada</option>
                                    <option value="respondio_correo">Respondió al correo</option>
                                    <option value="promesa_pago">Promesa de Pago (Acuerdo)</option>
                                    <option value="solicita_prorroga">Solicitó prórroga de plazo</option>
                                    <option value="no_contesto">No contestó / Buzón de voz</option>
                                    <option value="se_niega_pagar">Se niega rotundamente a pagar</option>
                                    <option value="contacto_invalido">Teléfono o correo inválido</option>
                                </select>
                            </div>

                            <div id="boxPromesa" style="display: none;">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                    <div>
                                        <label class="form-label">Fecha Compromiso</label>
                                        <input type="date" name="fecha_compromiso" id="inputFechaCompromiso" class="form-control" min="{{ date('Y-m-d') }}">
                                    </div>
                                    <div>
                                        <label class="form-label">Monto ($)</label>
                                        <input type="number" step="0.01" name="monto_compromiso" id="inputMontoCompromiso" class="form-control" placeholder="0.00">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 0.75rem;">
                            <label class="form-label">Observaciones de lo manifestado por el cliente</label>
                            <input type="text" name="notas_respuesta" class="form-control" placeholder="Notas sobre la respuesta, justificación dada por el cliente o acuerdos verbales...">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary" id="btnEnviarNotificacion">
                            Enviar Notificación / Guardar Gestión
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Sección 3: Historial y Control de Respuesta de Clientes --}}
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">3. Historial de Notificaciones y Control de Respuesta</h3>
                <p style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;">
                    Registro de a qué clientes se les mandó notificación y si respondieron las llamadas o correos.
                </p>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="data-table-wrapper" tabindex="0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Canal</th>
                            <th>Tipo Notificación</th>
                            <th>Contacto Destino</th>
                            <th>Gestor</th>
                            <th>Estado de Respuesta</th>
                            <th>Compromiso / Notas</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($historial as $notif)
                            <tr>
                                <td style="white-space: nowrap; font-size: 0.8125rem;">
                                    {{ $notif->fecha_envio->format('d/m/Y H:i') }}
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--neutral-900);">
                                        <a href="{{ route('clientes.show', $notif->cliente) }}">
                                            {{ $notif->cliente?->nombre }}
                                        </a>
                                    </div>
                                    @if($notif->credito)
                                        <div style="font-size: 0.75rem; color: var(--primary-600);">
                                            Crédito: {{ $notif->credito->numero }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge badge-{{ $notif->canal_badge }}">
                                        {{ $notif->canal_label }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $notif->tipo_badge }}">
                                        {{ $notif->tipo_label }}
                                    </span>
                                </td>
                                <td style="font-size: 0.8125rem; font-family: monospace;">
                                    {{ $notif->destinatario_contacto }}
                                </td>
                                <td style="font-size: 0.8125rem;">
                                    {{ $notif->usuario_nombre }}
                                </td>
                                <td>
                                    <span class="badge badge-{{ $notif->estado_respuesta_badge }}" style="font-weight: 700;">
                                        {{ $notif->estado_respuesta_label }}
                                    </span>
                                    @if($notif->fecha_respuesta)
                                        <div style="font-size: 0.7rem; color: var(--neutral-400); margin-top: 0.2rem;">
                                            {{ $notif->fecha_respuesta->format('d/m/Y H:i') }}
                                        </div>
                                    @endif
                                </td>
                                <td style="font-size: 0.8125rem; max-width: 250px;">
                                    @if($notif->estado_respuesta === 'promesa_pago')
                                        <strong style="color: var(--success-700);">
                                            ${{ number_format($notif->monto_compromiso, 2) }} el {{ $notif->fecha_compromiso?->format('d/m/Y') }}
                                        </strong><br>
                                    @endif
                                    <span style="color: var(--neutral-600);">
                                        {{ $notif->notas_respuesta ?? $notif->asunto }}
                                    </span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.3rem 0.6rem;"
                                        onclick="abrirModalRespuesta('{{ $notif->id }}', '{{ addslashes($notif->cliente?->nombre) }}', '{{ $notif->estado_respuesta }}', '{{ addslashes($notif->notas_respuesta ?? '') }}', '{{ $notif->fecha_compromiso?->format('Y-m-d') ?? '' }}', '{{ $notif->monto_compromiso ?? '' }}')">
                                        Registrar Respuesta
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 3rem 1rem; color: var(--neutral-400);">
                                    No hay registros de notificaciones emitidas aún.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($historial->hasPages())
                <div style="padding: 1rem;">
                    {{ $historial->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Modal para Actualizar la Respuesta del Cliente --}}
    <div id="modalRespuesta" class="modal-overlay" style="display: none; align-items: center; justify-content: center;">
        <div class="modal" style="max-width: 500px; width: 100%;">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Control de Respuesta del Cliente</h3>
                    <p id="modalRespuestaCliente" style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;"></p>
                </div>
                <button type="button" class="modal-close" onclick="cerrarModalRespuesta()">✕</button>
            </div>

            <form id="formRespuesta" method="POST" action="">
                @csrf
                <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">¿Cómo respondió el cliente a la notificación o llamada?</label>
                        <select name="estado_respuesta" id="modalSelectEstado" class="form-control" required onchange="toggleModalPromesa(this.value)">
                            <option value="contesto_llamada">Contestó la llamada / Se contactó</option>
                            <option value="respondio_correo">Respondió al correo electrónico</option>
                            <option value="promesa_pago">Promesa de pago (Acuerdo de pago)</option>
                            <option value="solicita_prorroga">Solicita prórroga de fecha</option>
                            <option value="no_contesto">No contestó / Dejó en visto</option>
                            <option value="se_niega_pagar">Se niega a pagar</option>
                            <option value="contacto_invalido">Contacto inválido / Rebotado</option>
                            <option value="pendiente_respuesta">Sin respuesta aún</option>
                        </select>
                    </div>

                    <div id="modalBoxPromesa" style="display: none; background: var(--neutral-50); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--neutral-200);">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div>
                                <label class="form-label">Fecha de Promesa</label>
                                <input type="date" name="fecha_compromiso" id="modalInputFecha" class="form-control" min="{{ date('Y-m-d') }}">
                            </div>
                            <div>
                                <label class="form-label">Monto Acordado ($)</label>
                                <input type="number" step="0.01" name="monto_compromiso" id="modalInputMonto" class="form-control" placeholder="0.00">
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Detalle de la Respuesta / Observaciones</label>
                        <textarea name="notas_respuesta" id="modalTextNotas" rows="3" class="form-control" placeholder="Detalle lo conversado con el cliente, motivo de atraso expuesto o aclaraciones acordadas..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalRespuesta()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Respuesta</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let clienteActual = null;

        function seleccionarCliente(id, nombre, creditoId, creditoNumero, email, tel, saldo, diasMora) {
            clienteActual = { id, nombre, creditoId, creditoNumero, email, tel, saldo, diasMora };

            document.getElementById('inputClienteId').value = id;
            document.getElementById('inputCreditoId').value = creditoId;

            document.getElementById('lblClienteNombre').innerText = nombre;
            let infoCredito = creditoNumero ? 'Crédito ' + creditoNumero + ' • Saldo: $' + parseFloat(saldo).toFixed(2) + ' • Mora: ' + diasMora + ' días' : 'Saldo: $' + parseFloat(saldo).toFixed(2);
            document.getElementById('lblClienteDetalle').innerText = infoCredito;
            document.getElementById('boxClienteSeleccionado').style.display = 'block';

            cambiarCanal(document.getElementById('selectCanal').value);
            sugerirMensaje();
        }

        function cambiarCanal(canal) {
            if (!clienteActual) return;
            const inputDest = document.getElementById('inputDestinatario');
            const lblDest = document.getElementById('lblDestinatario');

            if (canal === 'correo') {
                lblDest.innerText = 'Correo Electrónico de Destino';
                inputDest.value = clienteActual.email || '';
                inputDest.placeholder = 'ejemplo@correo.com';
            } else {
                lblDest.innerText = 'Número Telefónico de Destino';
                inputDest.value = clienteActual.tel || '';
                inputDest.placeholder = '7000-0000';
            }
        }

        function sugerirMensaje() {
            if (!clienteActual) return;
            const tipo = document.getElementById('selectTipo').value;
            const canal = document.getElementById('selectCanal').value;
            const asunto = document.getElementById('inputAsunto');
            const mensaje = document.getElementById('textMensaje');

            if (tipo === 'cobro_vencido') {
                asunto.value = 'Aviso de Cobro en Mora — Crédito ' + (clienteActual.creditoNumero || '');
                if (canal === 'llamada') {
                    mensaje.value = 'Buenos días/tardes, le saluda del departamento de cobros de El Tablazo. Nos comunicamos con respecto a su crédito ' + (clienteActual.creditoNumero || '') + ' que presenta un atraso de ' + clienteActual.diasMora + ' días y saldo de $' + parseFloat(clienteActual.saldo).toFixed(2) + '. Deseamos coordinar su pago a la brevedad.';
                } else {
                    mensaje.value = 'Le informamos que su crédito comercial ' + (clienteActual.creditoNumero || '') + ' registra cuotas vencidas con un atraso de ' + clienteActual.diasMora + ' días y un saldo adeudado de $' + parseFloat(clienteActual.saldo).toFixed(2) + '. Le solicitamos ponerse al día para evitar recargos y afectación a su récord crediticio.';
                }
            } else if (tipo === 'cobro_pendiente') {
                asunto.value = 'Recordatorio Preventivo de Pago — Crédito ' + (clienteActual.creditoNumero || '');
                if (canal === 'llamada') {
                    mensaje.value = 'Hola estimado(a) ' + clienteActual.nombre + ', le saludamos cordialmente de El Tablazo para recordarle la próxima fecha de vencimiento de su cuota comercial. ¿Podemos apoyarle en algo respecto a su método de pago?';
                } else {
                    mensaje.value = 'Le recordamos cordialmente que su próxima cuota de crédito está próxima a vencer. Recuerde que mantener sus pagos al día le permite gozar de mayores límites y beneficios comerciales en El Tablazo.';
                }
            } else if (tipo === 'pago_realizado') {
                asunto.value = 'Confirmación de Pago Realizado — El Tablazo';
                mensaje.value = 'Agradecemos el abono efectuado a su cuenta. Su pago ha sido registrado satisfactoriamente en nuestro sistema contable y aplicado a su saldo. ¡Gracias por su puntualidad!';
            } else if (tipo === 'aviso_secuencia') {
                asunto.value = 'Notificación Formal de Secuencia de Cobranza';
                mensaje.value = 'Por medio de la presente le notificamos formalmente el estado de su cuenta, la cual ha sido escalada en nuestro plan de cobranza institucional. Agradecemos su pronta comparecencia.';
            }
        }

        function togglePromesa(val) {
            document.getElementById('boxPromesa').style.display = (val === 'promesa_pago') ? 'block' : 'none';
        }

        function toggleModalPromesa(val) {
            document.getElementById('modalBoxPromesa').style.display = (val === 'promesa_pago') ? 'block' : 'none';
        }

        function abrirModalRespuesta(id, nombreCliente, estado, notas, fechaCompromiso, montoCompromiso) {
            const form = document.getElementById('formRespuesta');
            form.action = '/cobros/notificaciones/' + id + '/respuesta';
            document.getElementById('modalRespuestaCliente').innerText = 'Cliente: ' + nombreCliente;
            document.getElementById('modalSelectEstado').value = estado;
            document.getElementById('modalTextNotas').value = notas || '';
            document.getElementById('modalInputFecha').value = fechaCompromiso || '';
            document.getElementById('modalInputMonto').value = montoCompromiso || '';

            toggleModalPromesa(estado);
            document.getElementById('modalRespuesta').style.display = 'flex';
        }

        function cerrarModalRespuesta() {
            document.getElementById('modalRespuesta').style.display = 'none';
        }
    </script>
</x-app-layout>
