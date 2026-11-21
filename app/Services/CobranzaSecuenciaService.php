<?php

namespace App\Services;

use App\Models\CobranzaSecuencia;
use App\Models\Credito;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Servicio del Plan de Secuencia de Cobranza de la Clase.
 *
 * Secuencia oficial:
 * 1. Notificación escrita  (1 - 15 días de atraso o preventivo)
 * 2. Llamada telefónica     (16 - 30 días de atraso)
 * 3. Visita presencial      (31 - 60 días de atraso)
 * 4. Agencia de cobranza    (61 - 90 días de atraso)
 * 5. Recurso legal          (Más de 90 días de atraso / proceso judicial)
 *
 * "La política y el caso determinan cuándo escalar."
 */
class CobranzaSecuenciaService
{
    /**
     * Obtiene o inicializa la secuencia de cobranza de un crédito.
     */
    public function obtenerOSincronizar(Credito $credito): CobranzaSecuencia
    {
        $secuencia = CobranzaSecuencia::where('credito_id', $credito->id)->first();

        if (! $secuencia) {
            $etapaInicial = CobranzaSecuencia::etapaSugeridaPorMora($credito->dias_mora);

            $secuencia = CobranzaSecuencia::create([
                'credito_id' => $credito->id,
                'cliente_id' => $credito->cliente_id,
                'etapa_actual' => $etapaInicial,
                'fecha_ingreso_etapa' => now()->toDateString(),
                'dias_mora_al_escalar' => $credito->dias_mora,
                'estado' => $credito->estado === 'incobrable' ? 'incobrable' : 'en_gestion',
                'motivo_escalamiento' => 'Inicialización de secuencia según política de morosidad.',
            ]);
        }

        return $secuencia;
    }

    /**
     * Escala un crédito a una nueva etapa de la secuencia de cobranza.
     */
    public function escalar(
        Credito $credito,
        string $nuevaEtapa,
        ?string $motivo = null,
        ?User $actor = null,
        ?string $agenciaNombre = null,
        ?string $expedienteLegal = null
    ): CobranzaSecuencia {
        if (! array_key_exists($nuevaEtapa, CobranzaSecuencia::ETAPAS)) {
            throw new InvalidArgumentException("La etapa '{$nuevaEtapa}' no es válida en la secuencia de cobranza.");
        }

        return DB::transaction(function () use ($credito, $nuevaEtapa, $motivo, $actor, $agenciaNombre, $expedienteLegal) {
            $secuencia = $this->obtenerOSincronizar($credito);

            $estado = match ($nuevaEtapa) {
                'agencia_cobranza' => 'escalado_externo',
                'recurso_legal' => 'en_demanda',
                default => 'en_gestion',
            };

            $secuencia->update([
                'etapa_actual' => $nuevaEtapa,
                'fecha_ingreso_etapa' => now()->toDateString(),
                'dias_mora_al_escalar' => $credito->dias_mora,
                'motivo_escalamiento' => $motivo ?? "Escalamiento a etapa {$nuevaEtapa}",
                'escalado_por' => $actor?->id,
                'agencia_nombre' => $agenciaNombre ?? $secuencia->agencia_nombre,
                'expediente_legal_referencia' => $expedienteLegal ?? $secuencia->expediente_legal_referencia,
                'estado' => $estado,
            ]);

            return $secuencia->fresh();
        });
    }

    /**
     * Obtiene el tablero del pipeline de la secuencia de cobranza.
     */
    public function tableroSecuencia(): array
    {
        // Sincronizar créditos activos sin secuencia
        $creditosActivos = Credito::whereIn('estado', ['vigente', 'vencido', 'incobrable'])
            ->with(['cliente', 'vendedor', 'secuenciaCobranza'])
            ->get();

        foreach ($creditosActivos as $credito) {
            if (! $credito->secuenciaCobranza) {
                $this->obtenerOSincronizar($credito);
            }
        }

        // Recargar con la secuencia actualizada
        $creditos = Credito::whereIn('estado', ['vigente', 'vencido', 'incobrable'])
            ->with(['cliente', 'vendedor', 'secuenciaCobranza'])
            ->get();

        $tablero = [];
        foreach (CobranzaSecuencia::ETAPAS as $key => $info) {
            $enEtapa = $creditos->filter(function ($c) use ($key) {
                return $c->secuenciaCobranza && $c->secuenciaCobranza->etapa_actual === $key;
            });

            $tablero[$key] = [
                'info' => $info,
                'total_creditos' => $enEtapa->count(),
                'saldo_total' => (float) $enEtapa->sum('saldo_actual'),
                'saldo_vencido' => (float) $enEtapa->where('estado', 'vencido')->sum('saldo_actual'),
                'creditos' => $enEtapa->values(),
            ];
        }

        return $tablero;
    }
}
