<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Credito;
use App\Models\CuentaIncobrable;
use App\Models\HistorialClasificacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class IncobrableService
{
    /**
     * Clasifica un cliente según sus días de mora.
     */
    public function clasificarCliente(Cliente $cliente, ?int $userId = null): string
    {
        return DB::transaction(function () use ($cliente, $userId) {
            $cliente = Cliente::withTrashed()->whereKey($cliente->id)->lockForUpdate()->firstOrFail();
            [$nuevaClasificacion, $maxDiasMora] = $this->clasificacionActual($cliente);

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
        });
    }

    private function clasificacionActual(Cliente $cliente): array
    {
        $diasMora = (int) ($cliente->creditos()->whereIn('estado', ['vigente', 'vencido', 'incobrable'])->max('dias_mora') ?? 0);
        if ($cliente->creditos()->incobrables()->exists()) {
            return ['D', $diasMora];
        }
        foreach (config('erp.cobros.clasificacion') as $letra => $rango) {
            if ($diasMora >= $rango['min_dias'] && ($rango['max_dias'] === null || $diasMora <= $rango['max_dias'])) {
                return [$letra, $diasMora];
            }
        }

        return ['A', $diasMora];
    }

    /**
     * Declara un crédito como incobrable.
     */
    public function declararIncobrable(Credito $credito, string $motivo, ?int $userId = null): void
    {
        $motivo = $this->validarMotivo($motivo);
        DB::transaction(function () use ($credito, $motivo, $userId) {
            $credito = Credito::whereKey($credito->id)->lockForUpdate()->firstOrFail();
            if (! in_array($credito->estado, ['vigente', 'vencido'])) {
                throw new InvalidArgumentException('Solo puede trasladar a incobrables un crédito vigente o vencido.');
            }
            $cliente = Cliente::withTrashed()->whereKey($credito->cliente_id)->lockForUpdate()->firstOrFail();
            $cuotas = $credito->cuotas()->lockForUpdate()->get();
            $pendiente = round($cuotas->sum('saldo_por_pagar'), 2);
            if ($pendiente <= 0 && (float) $credito->saldo_actual <= 0) {
                throw new InvalidArgumentException('El crédito no tiene saldo pendiente para trasladarlo a incobrables.');
            }
            CuentaIncobrable::create([
                'credito_id' => $credito->id, 'abierta' => true, 'estado_anterior' => $credito->estado,
                'saldo_capital' => $credito->saldo_actual,
                'saldo_total' => max((float) $credito->saldo_actual, $pendiente),
                'dias_mora' => max(0, $credito->dias_mora), 'motivo' => $motivo,
                'declarado_at' => now(), 'declarado_por' => $userId,
            ]);

            $credito->update(['estado' => 'incobrable']);
            $this->registrarCambio(
                $cliente,
                'D',
                "Crédito {$credito->numero} declarado incobrable: {$motivo}",
                $credito->dias_mora,
                false,
                $userId,
                $credito
            );
        });
    }

    /** Retoma el mismo plan y saldo; no crea deuda ni aplica condonaciones. */
    public function reactivarCredito(Credito $credito, string $motivo, ?int $userId = null): void
    {
        $motivo = $this->validarMotivo($motivo);
        DB::transaction(function () use ($credito, $motivo, $userId) {
            $credito = Credito::whereKey($credito->id)->lockForUpdate()->firstOrFail();
            if ($credito->estado !== 'incobrable') {
                throw new InvalidArgumentException('Solo puede reactivar un crédito incobrable.');
            }
            $cliente = Cliente::withTrashed()->whereKey($credito->cliente_id)->lockForUpdate()->firstOrFail();
            $cuotas = $credito->cuotas()->lockForUpdate()->get();
            if ($cuotas->sum('saldo_por_pagar') <= 0 && (float) $credito->saldo_actual <= 0) {
                throw new InvalidArgumentException('El crédito no tiene saldo pendiente para reactivar el cobro.');
            }
            $registro = $credito->cuentasIncobrables()->where('abierta', true)->lockForUpdate()->first();
            if (! $registro) {
                throw new InvalidArgumentException('No existe un registro abierto en la cartera; debe conciliar el historial antes de reactivar.');
            }
            $vencido = $cuotas->contains(fn ($cuota) => $cuota->saldo_por_pagar > 0
                && $cuota->fecha_vencimiento->lt(today()));
            $registro->update([
                'abierta' => null, 'reactivado_at' => now(), 'reactivado_por' => $userId,
                'motivo_reactivacion' => $motivo,
            ]);
            $credito->update(['estado' => $vencido ? 'vencido' : 'vigente']);
            // Mantener D si hay otros créditos incobrables del mismo cliente.
            [$clasificacion, $diasMora] = $this->clasificacionActual($cliente);
            $this->registrarCambio($cliente, $clasificacion,
                "Crédito {$credito->numero} reactivado: {$motivo}", $diasMora, true, $userId, $credito);
        });
    }

    private function validarMotivo(string $motivo): string
    {
        $motivo = trim($motivo);
        Validator::make(['motivo' => $motivo], ['motivo' => 'required|string|min:10|max:2000'])->validate();

        return $motivo;
    }

    /** Reactiva los créditos del cliente conservando un registro por cada uno. */
    public function reactivarCliente(Cliente $cliente, ?int $userId = null): array
    {
        return DB::transaction(function () use ($cliente, $userId) {
            $resumen = [
                'creditos_reactivados' => 0,
                'total_intereses_recalculados' => 0,
                'total_comisiones_recalculadas' => 0,
                'total_saldo_pendiente' => 0,
            ];
            foreach ($cliente->creditos()->incobrables()->orderBy('id')->lockForUpdate()->get() as $credito) {
                $this->reactivarCredito($credito, 'Reactivación conjunta solicitada para el cliente', $userId);
                $resumen['creditos_reactivados']++;
                $resumen['total_saldo_pendiente'] += (float) $credito->saldo_actual;
            }

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
        ?int $userId,
        ?Credito $credito = null
    ): void {
        HistorialClasificacion::create([
            'cliente_id' => $cliente->id,
            'credito_id' => $credito?->id,
            'clasificacion_anterior' => $cliente->clasificacion_cobro,
            'clasificacion_nueva' => $nuevaClasificacion,
            'motivo' => $motivo,
            'dias_mora' => $diasMora,
            'reactivacion' => $reactivacion,
            'realizado_por' => $userId,
            'registrado_por' => $userId,
            'fecha' => now()->toDateString(),
            'saldo_momento' => $cliente->creditos()->whereIn('estado', ['vigente', 'vencido', 'incobrable'])->sum('saldo_actual'),
        ]);

        $cliente->update(['clasificacion_cobro' => $nuevaClasificacion]);
    }
}
