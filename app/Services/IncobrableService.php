<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\HistorialClasificacion;
use Illuminate\Support\Facades\DB;

class IncobrableService
{
    /**
     * Clasifica un cliente según sus días de mora.
     */
    public function clasificarCliente(Cliente $cliente, ?int $userId = null): string
    {
        $maxDiasMora = $cliente->creditos()
            ->activos()
            ->max('dias_mora') ?? 0;

        $clasificaciones = config('erp.cobros.clasificacion');
        $nuevaClasificacion = 'A';

        foreach ($clasificaciones as $letra => $rango) {
            if ($maxDiasMora >= $rango['min_dias'] &&
                ($rango['max_dias'] === null || $maxDiasMora <= $rango['max_dias'])) {
                $nuevaClasificacion = $letra;
            }
        }

        if ($cliente->clasificacion_cobro !== $nuevaClasificacion) {
            $this->registrarCambio(
                $cliente,
                $nuevaClasificacion,
                "Reclasificación automática por {$maxDiasMora} días de mora",
                $maxDiasMora,
                false,
                $userId
            );
        }

        return $nuevaClasificacion;
    }

    /**
     * Declara un crédito como incobrable.
     */
    public function declararIncobrable(Credito $credito, ?int $userId = null): void
    {
        DB::transaction(function () use ($credito, $userId) {
            $credito->update(['estado' => 'incobrable']);

            $cliente = $credito->cliente;
            $this->registrarCambio(
                $cliente,
                'D',
                "Crédito {$credito->numero} declarado incobrable",
                $credito->dias_mora,
                false,
                $userId
            );
        });
    }

    /**
     * Reactiva un cliente previamente declarado incobrable.
     * Recalcula automáticamente intereses, comisiones y abonos pendientes.
     */
    public function reactivarCliente(Cliente $cliente, ?int $userId = null): array
    {
        return DB::transaction(function () use ($cliente, $userId) {
            $creditosIncobrables = $cliente->creditos()
                ->where('estado', 'incobrable')
                ->get();

            $resumen = [
                'creditos_reactivados' => 0,
                'total_intereses_recalculados' => 0,
                'total_comisiones_recalculadas' => 0,
                'total_saldo_pendiente' => 0,
            ];

            $creditoService = app(CreditoService::class);

            foreach ($creditosIncobrables as $credito) {
                // Recalcular mora acumulada
                $mora = $creditoService->calcularMora($credito);

                // Cambiar estado a vencido (para que pueda ser cobrado)
                $credito->update(['estado' => 'vencido']);

                // Calcular intereses y comisiones pendientes
                $interesesPendientes = $credito->cuotas()
                    ->pendientes()
                    ->sum('interes');
                $comisionesPendientes = $credito->cuotas()
                    ->pendientes()
                    ->sum('comision');

                $resumen['creditos_reactivados']++;
                $resumen['total_intereses_recalculados'] += $interesesPendientes + $mora['total_mora'];
                $resumen['total_comisiones_recalculadas'] += $comisionesPendientes;
                $resumen['total_saldo_pendiente'] += (float) $credito->saldo_actual;
            }

            // Reclasificar cliente
            $this->registrarCambio(
                $cliente,
                'B', // Subnormal tras reactivación
                'Reactivación de cliente previamente incobrable',
                0,
                true,
                $userId
            );

            return $resumen;
        });
    }

    /**
     * Obtiene el historial completo de clasificaciones de un cliente.
     */
    public function obtenerHistorial(Cliente $cliente)
    {
        return $cliente->historialClasificaciones()
            ->with('realizadoPor')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Reclasifica masivamente todos los clientes según sus días de mora actuales.
     */
    public function reclasificacionMasiva(?int $userId = null): array
    {
        $clientes = Cliente::activos()->with('creditos')->get();
        $cambios = 0;

        foreach ($clientes as $cliente) {
            $clasificacionAnterior = $cliente->clasificacion_cobro;
            $nuevaClasificacion = $this->clasificarCliente($cliente, $userId);

            if ($clasificacionAnterior !== $nuevaClasificacion) {
                $cambios++;
            }
        }

        return ['clientes_procesados' => $clientes->count(), 'cambios' => $cambios];
    }

    /**
     * Registra un cambio de clasificación en el historial.
     */
    private function registrarCambio(
        Cliente $cliente,
        string $nuevaClasificacion,
        string $motivo,
        int $diasMora,
        bool $reactivacion,
        ?int $userId
    ): void {
        HistorialClasificacion::create([
            'cliente_id' => $cliente->id,
            'clasificacion_anterior' => $cliente->clasificacion_cobro,
            'clasificacion_nueva' => $nuevaClasificacion,
            'motivo' => $motivo,
            'dias_mora' => $diasMora,
            'reactivacion' => $reactivacion,
            'realizado_por' => $userId,
        ]);

        $cliente->update(['clasificacion_cobro' => $nuevaClasificacion]);
    }
}
