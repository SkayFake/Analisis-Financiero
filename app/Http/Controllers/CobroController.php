<?php

namespace App\Http\Controllers;

use App\Models\CobranzaNotificacion;
use App\Models\CobranzaSecuencia;
use App\Models\Credito;
use App\Services\CobranzaNotificacionService;
use App\Services\CobranzaSecuenciaService;
use App\Services\IncobrableService;
use App\Services\MetricasCobroService;
use App\Support\Acceso;
use Illuminate\Http\Request;

class CobroController extends Controller
{
    public function dashboard(
        MetricasCobroService $metricasService,
        CobranzaSecuenciaService $secuenciaService,
        CobranzaNotificacionService $notificacionService
    ) {
        Acceso::exigir('cobros.view');
        $metricas = $metricasService->dashboardMetricas();
        $morosidad = $metricasService->analisisMorosidad();
        $rendimiento = $metricasService->rendimientoPorVendedor(
            now()->startOfMonth(),
            now()->endOfMonth()
        );
        $tableroSecuencia = $secuenciaService->tableroSecuencia();
        $metricasNotificaciones = $notificacionService->metricasNotificaciones();

        return view('cobros.dashboard', compact(
            'metricas',
            'morosidad',
            'rendimiento',
            'tableroSecuencia',
            'metricasNotificaciones'
        ));
    }

    public function reclasificacionMasiva(IncobrableService $incobrableService)
    {
        Acceso::exigir('cobros.manage');
        $resultado = $incobrableService->reclasificacionMasiva(auth()->id());

        return redirect()->back()
            ->with('success', "Reclasificación masiva completada. Se procesaron {$resultado['clientes_procesados']} clientes y hubo {$resultado['cambios']} cambios.");
    }

    /**
     * Vista interactiva del Plan de Secuencia de Cobranza de la Clase.
     * Etapas: Notificación escrita -> llamada -> visita -> agencia de cobranza -> recurso legal.
     */
    public function secuencia(Request $request, CobranzaSecuenciaService $secuenciaService)
    {
        Acceso::exigir('cobros.view');
        $tablero = $secuenciaService->tableroSecuencia();
        $etapas = CobranzaSecuencia::ETAPAS;

        return view('cobros.secuencia', compact('tablero', 'etapas'));
    }

    /**
     * Escalar un crédito a una nueva etapa de la secuencia de cobranza.
     */
    public function escalarSecuencia(Request $request, Credito $credito, CobranzaSecuenciaService $secuenciaService)
    {
        Acceso::exigir('cobros.manage');

        $request->validate([
            'etapa_destino' => 'required|string|in:notificacion_escrita,llamada,visita,agencia_cobranza,recurso_legal',
            'motivo' => 'required|string|min:10|max:2000',
            'agencia_nombre' => 'nullable|string|max:150',
            'expediente_legal_referencia' => 'nullable|string|max:150',
        ]);

        $secuenciaService->escalar(
            $credito,
            $request->etapa_destino,
            $request->motivo,
            $request->user(),
            $request->agencia_nombre,
            $request->expediente_legal_referencia
        );

        return redirect()->back()->with(
            'success',
            "Crédito {$credito->numero} escalado exitosamente a la etapa: " . CobranzaSecuencia::ETAPAS[$request->etapa_destino]['nombre'] . '.'
        );
    }

    /**
     * Vista de Seguimiento de Cobros: Notificaciones a Clientes y Control de Respuestas.
     */
    public function notificaciones(Request $request, CobranzaNotificacionService $notificacionService)
    {
        Acceso::exigir('cobros.view');

        $criterio = $request->query('criterio', 'vencidos');
        $buscar = $request->query('buscar');
        $destinatarios = $notificacionService->destinatariosElegibles([
            'criterio' => $criterio,
            'buscar' => $buscar,
        ]);

        $historial = $notificacionService->historial($request->only([
            'cliente_id', 'canal', 'estado_respuesta', 'tipo_notificacion',
        ]));

        $metricas = $notificacionService->metricasNotificaciones();

        return view('cobros.notificaciones', compact(
            'destinatarios',
            'historial',
            'metricas',
            'criterio',
            'buscar'
        ));
    }

    /**
     * Envío y registro de una notificación (correo, llamada, carta o visita).
     */
    public function enviarNotificacion(Request $request, CobranzaNotificacionService $notificacionService)
    {
        Acceso::exigir('cobros.manage');

        $notificacionService->registrarNotificacion($request->all(), $request->user());

        return redirect()->back()->with('success', 'Notificación registrada y procesada correctamente.');
    }

    /**
     * Actualiza el estado de respuesta del cliente (llamada respondida, correo contestado, promesa de pago, etc.).
     */
    public function actualizarRespuesta(Request $request, CobranzaNotificacion $notificacion, CobranzaNotificacionService $notificacionService)
    {
        Acceso::exigir('cobros.manage');

        $notificacionService->actualizarRespuesta($notificacion, $request->all(), $request->user());

        return redirect()->back()->with('success', 'Respuesta y seguimiento del cliente actualizados exitosamente.');
    }
}
