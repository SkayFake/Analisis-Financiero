<x-app-layout>
    <x-slot name="title">Plan de Secuencia de Cobranza</x-slot>

    {{-- Encabezado de Página --}}
    <div class="page-header">
        <div class="page-header-left">
            <h1 class="page-title">Plan de Secuencia de Cobranza</h1>
            <p class="page-subtitle">
                Secuencia oficial de la clase: <strong>Notificación escrita → Llamada → Visita → Agencia de cobranza → Recurso legal</strong>. La política institucional y el caso particular determinan cuándo escalar.
            </p>
        </div>
        <div class="page-actions">
            <a href="{{ route('cobros.notificaciones') }}" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 17H2a3 3 0 0 0 3-3V9a7 7 0 0 1 14 0v5a3 3 0 0 0 3 3zm-8.27 4a2 2 0 0 1-3.46 0"/>
                </svg>
                <span>Centro de Notificaciones</span>
            </a>
            <a href="{{ route('cobros.dashboard') }}" class="btn btn-secondary">
                <span>Dashboard de Cobros</span>
            </a>
        </div>
    </div>

    {{-- Banner de Etapas Oficiales de la Secuencia --}}
    <div class="card mb-4" style="margin-bottom: 1.5rem; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff;">
        <div class="card-body" style="padding: 1.5rem;">
            <div style="font-size: 0.8125rem; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; margin-bottom: 0.75rem; font-weight: 700;">
                Flujo de Escalamiento Progresivo según Política
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; align-items: stretch;">
                @foreach($etapas as $key => $info)
                    <div style="background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: var(--radius-md); padding: 1rem; position: relative;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.75rem; font-weight: 800; background: rgba(255, 255, 255, 0.15); padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                Etapa {{ $info['orden'] }}
                            </span>
                            <span style="font-size: 0.75rem; color: #cbd5e1;">
                                {{ $info['dias_mora_sugeridos'] }}
                            </span>
                        </div>
                        <div style="font-weight: 700; font-size: 1rem; color: #ffffff; margin-bottom: 0.25rem;">
                            {{ $info['nombre'] }}
                        </div>
                        <div style="font-size: 0.75rem; color: #94a3b8; line-height: 1.4;">
                            {{ $info['descripcion'] }}
                        </div>
                        <div style="margin-top: 0.75rem; font-size: 0.8125rem; font-weight: 700; color: #38bdf8;">
                            {{ $tablero[$key]['total_creditos'] ?? 0 }} créditos en etapa
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Tablero Kanban / Columnas de Etapas --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; align-items: start;">
        @foreach($etapas as $key => $info)
            @php
                $columna = $tablero[$key] ?? ['total_creditos' => 0, 'saldo_total' => 0, 'creditos' => collect()];
            @endphp
            <div class="card" style="border-top: 4px solid var(--{{ $info['badge'] }}-500); height: 100%;">
                <div class="card-header" style="padding: 1rem 1.25rem;">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span class="badge badge-{{ $info['badge'] }}" style="font-weight: 800;">
                                Etapa {{ $info['orden'] }}
                            </span>
                            <h3 class="card-title" style="font-size: 1rem; margin: 0;">{{ $info['nombre'] }}</h3>
                        </div>
                        <div style="font-size: 0.75rem; color: var(--neutral-500); margin-top: 0.25rem;">
                            {{ $info['dias_mora_sugeridos'] }} • ${{ number_format($columna['saldo_total'], 2) }} saldo
                        </div>
                    </div>
                    <span class="badge badge-neutral" style="font-size: 0.8125rem;">
                        {{ $columna['total_creditos'] }}
                    </span>
                </div>

                <div class="card-body" style="padding: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; max-height: 650px; overflow-y: auto;">
                    @forelse($columna['creditos'] as $credito)
                        @php
                            $etapaSugerida = \App\Models\CobranzaSecuencia::etapaSugeridaPorMora($credito->dias_mora);
                            $requiereEscalar = $etapaSugerida !== $key && $credito->dias_mora > 0;
                        @endphp
                        <div style="background: var(--neutral-50); border: 1px solid var(--neutral-200); border-radius: var(--radius-md); padding: 0.875rem;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <div>
                                    <a href="{{ route('creditos.show', $credito) }}" style="font-weight: 700; color: var(--primary-700); font-size: 0.9375rem;">
                                        {{ $credito->numero }}
                                    </a>
                                    <div style="font-size: 0.8125rem; font-weight: 600; color: var(--neutral-800);">
                                        <a href="{{ route('clientes.show', $credito->cliente) }}">
                                            {{ $credito->cliente?->nombre }}
                                        </a>
                                    </div>
                                </div>
                                <span class="badge badge-{{ $credito->dias_mora > 0 ? 'warning' : 'success' }}" style="font-size: 0.75rem;">
                                    {{ $credito->dias_mora }} d. mora
                                </span>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.75rem; color: var(--neutral-600); margin-bottom: 0.75rem;">
                                <div>
                                    <span>Saldo actual:</span><br>
                                    <strong style="font-size: 0.875rem; color: var(--neutral-900);">${{ number_format($credito->saldo_actual, 2) }}</strong>
                                </div>
                                <div>
                                    <span>Teléfono:</span><br>
                                    <strong>{{ $credito->cliente?->celular ?? $credito->cliente?->telefono ?? 'Sin teléfono' }}</strong>
                                </div>
                            </div>

                            @if($requiereEscalar)
                                <div class="alert alert-warning" style="padding: 0.4rem 0.6rem; font-size: 0.75rem; margin-bottom: 0.75rem;">
                                    Por días de mora ({{ $credito->dias_mora }} d.), la política sugiere: <strong>{{ $etapas[$etapaSugerida]['nombre'] }}</strong>.
                                </div>
                            @endif

                            <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: space-between; border-top: 1px solid var(--neutral-200); padding-top: 0.625rem;">
                                <a href="{{ route('cobros.notificaciones', ['buscar' => $credito->numero, 'criterio' => 'pendientes']) }}" class="btn btn-ghost" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                    Notificar
                                </a>

                                {{-- Botón para abrir modal de escalamiento --}}
                                <button type="button" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.5rem;" onclick="abrirModalEscalar('{{ $credito->id }}', '{{ $credito->numero }}', '{{ $credito->cliente?->nombre }}', '{{ $key }}', '{{ $credito->dias_mora }}')">
                                    Escalar Etapa →
                                </button>
                            </div>
                        </div>
                    @empty
                        <div style="text-align: center; padding: 2rem 1rem; color: var(--neutral-400); font-size: 0.875rem;">
                            No hay créditos en esta etapa
                        </div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    {{-- Modal para Escalar Etapa en la Secuencia --}}
    <div id="modalEscalar" class="modal-overlay" style="display: none; align-items: center; justify-content: center;">
        <div class="modal" style="max-width: 520px; width: 100%;" @click.outside="cerrarModalEscalar()">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Escalar Etapa de Cobranza</h3>
                    <p id="modalSubtitulo" style="font-size: 0.8125rem; color: var(--neutral-500); margin-top: 0.2rem;"></p>
                </div>
                <button type="button" class="modal-close" onclick="cerrarModalEscalar()">✕</button>
            </div>

            <form id="formEscalar" method="POST" action="">
                @csrf
                <div class="modal-body" style="display: flex; flex-direction: column; gap: 1rem;">
                    <div>
                        <label class="form-label">Nueva Etapa en la Secuencia</label>
                        <select name="etapa_destino" id="selectEtapaDestino" class="form-control" required onchange="ajustarCamposEtapa(this.value)">
                            @foreach($etapas as $etapaKey => $etapaInfo)
                                <option value="{{ $etapaKey }}">
                                    Etapa {{ $etapaInfo['orden'] }}: {{ $etapaInfo['nombre'] }} ({{ $etapaInfo['dias_mora_sugeridos'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="campoAgencia" style="display: none;">
                        <label class="form-label">Nombre de la Agencia de Cobranza Externa</label>
                        <input type="text" name="agencia_nombre" class="form-control" placeholder="Ej: Cobranzas y Recuperaciones de El Salvador S.A.">
                    </div>

                    <div id="campoLegal" style="display: none;">
                        <label class="form-label">Referencia / Expediente Legal / Juzgado</label>
                        <input type="text" name="expediente_legal_referencia" class="form-control" placeholder="Ej: Juicio Ejecutivo Mercantil 142-2026 / Juzgado 2° de lo Mercantil">
                    </div>

                    <div>
                        <label class="form-label">Motivo y Justificación del Escalamiento</label>
                        <textarea name="motivo" class="form-control" rows="3" required placeholder="Explique las gestiones previas no atendidas, justificación de política o circunstancias del caso particular (mínimo 10 caracteres)..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="cerrarModalEscalar()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Confirmar Escalamiento</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalEscalar(creditoId, creditoNumero, clienteNombre, etapaActual, diasMora) {
            const form = document.getElementById('formEscalar');
            form.action = '/cobros/secuencia/' + creditoId + '/escalar';
            document.getElementById('modalSubtitulo').innerText = 'Crédito ' + creditoNumero + ' — ' + clienteNombre + ' (' + diasMora + ' días mora)';

            // Seleccionar por defecto la siguiente etapa en el orden
            const orden = ['notificacion_escrita', 'llamada', 'visita', 'agencia_cobranza', 'recurso_legal'];
            const idx = orden.indexOf(etapaActual);
            const siguiente = idx < orden.length - 1 ? orden[idx + 1] : etapaActual;
            document.getElementById('selectEtapaDestino').value = siguiente;
            ajustarCamposEtapa(siguiente);

            document.getElementById('modalEscalar').style.display = 'flex';
        }

        function cerrarModalEscalar() {
            document.getElementById('modalEscalar').style.display = 'none';
        }

        function ajustarCamposEtapa(etapa) {
            document.getElementById('campoAgencia').style.display = (etapa === 'agencia_cobranza') ? 'block' : 'none';
            document.getElementById('campoLegal').style.display = (etapa === 'recurso_legal') ? 'block' : 'none';
        }
    </script>
</x-app-layout>
