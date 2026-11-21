<?php

namespace App\Services;

use App\Mail\NotificacionCobroMail;
use App\Models\Cliente;
use App\Models\CobranzaNotificacion;
use App\Models\Credito;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class CobranzaNotificacionService
{
    /**
     * Obtiene los clientes destinatarios elegibles según el filtro de cobranza.
     */
    public function destinatariosElegibles(array $filtros = []): Collection
    {
        $criterio = $filtros['criterio'] ?? 'vencidos';
        $buscar = $filtros['buscar'] ?? null;

        $query = Cliente::activos()
            ->with(['creditos' => function ($q) {
                $q->whereIn('estado', ['vigente', 'vencido', 'incobrable'])
                  ->orderByDesc('dias_mora');
            }, 'creditos.secuenciaCobranza']);

        if ($buscar) {
            $query->buscar($buscar);
        }

        if ($criterio === 'vencidos') {
            $query->whereHas('creditos', function ($q) {
                $q->where('estado', 'vencido')->orWhere('dias_mora', '>', 0);
            });
        } elseif ($criterio === 'por_vencer') {
            $query->whereHas('creditos.cuotas', function ($q) {
                $q->whereIn('estado', ['pendiente', 'parcial'])
                  ->whereBetween('fecha_vencimiento', [today()->toDateString(), today()->addDays(7)->toDateString()]);
            });
        } elseif ($criterio === 'pendientes') {
            $query->whereHas('creditos', function ($q) {
                $q->whereIn('estado', ['vigente', 'vencido'])
                  ->where('saldo_actual', '>', 0);
            });
        } elseif ($criterio === 'realizados') {
            $query->whereHas('creditos.pagos', function ($q) {
                $q->where('fecha_pago', '>=', today()->subDays(30)->toDateString());
            });
        }

        return $query->orderBy('nombre')->limit(50)->get();
    }

    /**
     * Registra y envía una notificación de cobro (correo, llamada o notificación escrita).
     */
    public function registrarNotificacion(array $datos, User $actor): CobranzaNotificacion
    {
        $validador = Validator::make($datos, [
            'cliente_id' => 'required|integer|exists:clientes,id',
            'credito_id' => 'nullable|integer|exists:creditos,id',
            'pago_id' => 'nullable|integer|exists:pagos,id',
            'tipo_notificacion' => 'required|string|in:cobro_pendiente,cobro_vencido,pago_realizado,aviso_secuencia',
            'canal' => 'required|string|in:correo,llamada,notificacion_escrita,visita',
            'etapa_secuencia' => 'nullable|string|in:notificacion_escrita,llamada,visita,agencia_cobranza,recurso_legal',
            'destinatario_contacto' => 'required|string|max:150',
            'asunto' => 'required|string|max:200',
            'mensaje' => 'required|string|min:10|max:5000',
            'estado_respuesta' => 'nullable|string|in:pendiente_respuesta,contesto_llamada,respondio_correo,no_contesto,promesa_pago,solicita_prorroga,se_niega_pagar,contacto_invalido',
            'fecha_compromiso' => 'nullable|required_if:estado_respuesta,promesa_pago|date_format:Y-m-d|after_or_equal:today',
            'monto_compromiso' => 'nullable|required_if:estado_respuesta,promesa_pago|numeric|min:0.01',
            'notas_respuesta' => 'nullable|string|max:2000',
        ]);

        if ($validador->fails()) {
            throw new InvalidArgumentException($validador->errors()->first());
        }

        $validated = $validador->validated();
        $cliente = Cliente::findOrFail($validated['cliente_id']);
        $credito = ! empty($validated['credito_id']) ? Credito::find($validated['credito_id']) : null;
        $pago = ! empty($validated['pago_id']) ? Pago::find($validated['pago_id']) : null;

        $estadoEnvio = 'enviado';

        // Si el canal es correo electrónico, despachar el Mailable
        if ($validated['canal'] === 'correo') {
            try {
                Mail::to($validated['destinatario_contacto'])->send(new NotificacionCobroMail(
                    cliente: $cliente,
                    asuntoMensaje: $validated['asunto'],
                    cuerpoMensaje: $validated['mensaje'],
                    tipoNotificacion: $validated['tipo_notificacion'],
                    credito: $credito,
                    pago: $pago,
                    etapaSecuencia: $validated['etapa_secuencia'] ?? null
                ));
            } catch (\Throwable $e) {
                Log::warning("Error al enviar correo de cobranza a {$validated['destinatario_contacto']}: " . $e->getMessage());
                $estadoEnvio = 'fallido';
            }
        } elseif ($validated['canal'] === 'llamada') {
            $estadoEnvio = 'realizada';
        }

        $estadoRespuesta = $validated['estado_respuesta'] ?? 'pendiente_respuesta';
        $fechaRespuesta = ($estadoRespuesta !== 'pendiente_respuesta') ? now() : null;

        return DB::transaction(function () use ($validated, $cliente, $credito, $pago, $actor, $estadoEnvio, $estadoRespuesta, $fechaRespuesta) {
            $notificacion = CobranzaNotificacion::create([
                'cliente_id' => $cliente->id,
                'credito_id' => $credito?->id,
                'pago_id' => $pago?->id,
                'tipo_notificacion' => $validated['tipo_notificacion'],
                'canal' => $validated['canal'],
                'etapa_secuencia' => $validated['etapa_secuencia'] ?? null,
                'destinatario_contacto' => $validated['destinatario_contacto'],
                'asunto' => $validated['asunto'],
                'mensaje' => $validated['mensaje'],
                'estado_envio' => $estadoEnvio,
                'fecha_envio' => now(),
                'usuario_id' => $actor->id,
                'usuario_nombre' => $actor->name,
                'estado_respuesta' => $estadoRespuesta,
                'fecha_respuesta' => $fechaRespuesta,
                'notas_respuesta' => $validated['notas_respuesta'] ?? null,
                'fecha_compromiso' => $validated['fecha_compromiso'] ?? null,
                'monto_compromiso' => $validated['monto_compromiso'] ?? null,
            ]);

            // Sincronizar etapa de secuencia si viene especificada
            if ($credito && ! empty($validated['etapa_secuencia'])) {
                app(CobranzaSecuenciaService::class)->escalar(
                    $credito,
                    $validated['etapa_secuencia'],
                    "Notificación emitida vía {$validated['canal']}: {$validated['asunto']}",
                    $actor
                );
            }

            return $notificacion;
        });
    }

    /**
     * Actualiza la respuesta o resultado del cliente a una notificación o llamada.
     */
    public function actualizarRespuesta(CobranzaNotificacion $notificacion, array $datos, User $actor): CobranzaNotificacion
    {
        $validador = Validator::make($datos, [
            'estado_respuesta' => 'required|string|in:pendiente_respuesta,contesto_llamada,respondio_correo,no_contesto,promesa_pago,solicita_prorroga,se_niega_pagar,contacto_invalido',
            'notas_respuesta' => 'nullable|string|max:2000',
            'fecha_compromiso' => 'nullable|required_if:estado_respuesta,promesa_pago|date_format:Y-m-d|after_or_equal:today',
            'monto_compromiso' => 'nullable|required_if:estado_respuesta,promesa_pago|numeric|min:0.01',
        ]);

        if ($validador->fails()) {
            throw new InvalidArgumentException($validador->errors()->first());
        }

        $validated = $validador->validated();

        $notificacion->update([
            'estado_respuesta' => $validated['estado_respuesta'],
            'fecha_respuesta' => now(),
            'notas_respuesta' => $validated['notas_respuesta'] ?? $notificacion->notas_respuesta,
            'fecha_compromiso' => $validated['fecha_compromiso'] ?? null,
            'monto_compromiso' => $validated['monto_compromiso'] ?? null,
        ]);

        return $notificacion->fresh();
    }

    /**
     * Consulta el historial paginado de notificaciones.
     */
    public function historial(array $filtros = []): LengthAwarePaginator
    {
        $query = CobranzaNotificacion::with(['cliente', 'credito', 'usuario'])
            ->orderByDesc('fecha_envio');

        if (! empty($filtros['cliente_id'])) {
            $query->where('cliente_id', $filtros['cliente_id']);
        }
        if (! empty($filtros['canal'])) {
            $query->where('canal', $filtros['canal']);
        }
        if (! empty($filtros['estado_respuesta'])) {
            $query->where('estado_respuesta', $filtros['estado_respuesta']);
        }
        if (! empty($filtros['tipo_notificacion'])) {
            $query->where('tipo_notificacion', $filtros['tipo_notificacion']);
        }

        return $query->paginate(15)->withQueryString();
    }

    /**
     * Obtiene métricas del control de notificaciones y respuestas de clientes.
     */
    public function metricasNotificaciones(): array
    {
        $total = CobranzaNotificacion::count();
        $correos = CobranzaNotificacion::where('canal', 'correo')->count();
        $llamadas = CobranzaNotificacion::where('canal', 'llamada')->count();
        $respondidas = CobranzaNotificacion::where('estado_respuesta', '!=', 'pendiente_respuesta')->count();
        $promesas = CobranzaNotificacion::where('estado_respuesta', 'promesa_pago')->count();
        $sinRespuesta = CobranzaNotificacion::where('estado_respuesta', 'pendiente_respuesta')->count();

        $tasaRespuesta = $total > 0 ? round(($respondidas / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'correos' => $correos,
            'llamadas' => $llamadas,
            'respondidas' => $respondidas,
            'sin_respuesta' => $sinRespuesta,
            'promesas' => $promesas,
            'tasa_respuesta' => $tasaRespuesta,
        ];
    }
}
