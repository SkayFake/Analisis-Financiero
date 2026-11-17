<?php

namespace App\Services;

use App\Models\ActivoFijo;
use App\Models\CategoriaActivo;
use App\Models\Depreciacion;
use App\Models\Unidad;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ActivoFijoService
{
    /**
     * Registra un nuevo activo fijo, aplica LISR para usados y genera código.
     */
    public function registrarActivo(array $datos)
    {
        Validator::make($datos, [
            'clase_fiscal' => 'required|in:edificaciones,maquinaria,vehiculos,otros_muebles',
            'valor_adquisicion' => 'required|numeric|min:0.01', 'valor_residual' => 'nullable|numeric|min:0|lte:valor_adquisicion',
            'fecha_adquisicion' => 'required|date_format:Y-m-d|before_or_equal:today',
            'fecha_inicio_uso' => 'required|date_format:Y-m-d|after_or_equal:fecha_adquisicion|before_or_equal:today',
            'valor_bien_nuevo' => 'required_if:es_usado,1|nullable|numeric|min:0.01',
            'valor_aduana' => 'required_if:maquinaria_importada_exenta,1|nullable|numeric|min:0.01',
            'anios_uso_previo' => 'required_if:es_usado,1|nullable|integer|min:0|max:150',
        ])->validate();

        return DB::transaction(function () use ($datos) {
            $unidad = Unidad::with('institucion')->findOrFail($datos['unidad_id']);
            $categoria = CategoriaActivo::findOrFail($datos['categoria_id']);
            $maximo = config('erp.activo_fijo.depreciacion.'.$datos['clase_fiscal']);
            if ($categoria->porcentaje_depreciacion <= 0 || $categoria->porcentaje_depreciacion > $maximo) {
                throw ValidationException::withMessages(['categoria_id' => 'El porcentaje de la categoría supera el máximo de la clase fiscal seleccionada.']);
            }

            // 1. Cálculo de LISR para bienes usados o maquinaria importada (Art 30)
            $valorBase = (float) $datos['valor_adquisicion'];
            $valorSujetoDepreciacion = $valorBase;

            if (! empty($datos['es_usado']) && $datos['es_usado']) {
                $aniosUso = (int) $datos['anios_uso_previo'];
                if ($aniosUso == 1) {
                    $factor = 0.80;
                } // 80%
                elseif ($aniosUso == 2) {
                    $factor = 0.60;
                } // 60%
                elseif ($aniosUso == 3) {
                    $factor = 0.40;
                } // 40%
                elseif ($aniosUso >= 4) {
                    $factor = 0.20;
                } // 20%
                else {
                    $factor = 1.0;
                }

                $valorSujetoDepreciacion = min($valorBase, (float) $datos['valor_bien_nuevo'] * $factor);
            }

            if (! empty($datos['maquinaria_importada_exenta'])) {
                $valorSujetoDepreciacion = min($valorSujetoDepreciacion, (float) $datos['valor_aduana']);
            }
            $valorSujetoDepreciacion = max(0, $valorSujetoDepreciacion - (float) ($datos['valor_residual'] ?? 0));
            $max = (int) ActivoFijo::where('unidad_id', $unidad->id)->where('categoria_id', $categoria->id)->max('correlativo');
            $correlativoNum = app(CorrelativoService::class)->siguiente('activo:'.$unidad->id.':'.$categoria->id, $max);
            $correlativoStr = str_pad($correlativoNum, 4, '0', STR_PAD_LEFT);

            $codigoInventario = "{$unidad->institucion->codigo}-{$unidad->codigo}-{$categoria->codigo}-{$correlativoStr}";

            // 3. Crear Activo
            return ActivoFijo::create([
                'unidad_id' => $unidad->id,
                'categoria_id' => $categoria->id,
                'correlativo' => $correlativoStr,
                'codigo_inventario' => $codigoInventario,
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
                'fecha_adquisicion' => $datos['fecha_adquisicion'],
                'fecha_inicio_uso' => $datos['fecha_inicio_uso'],
                'clase_fiscal' => $datos['clase_fiscal'],
                'porcentaje_fiscal' => $categoria->porcentaje_depreciacion,
                'valor_bien_nuevo' => $datos['valor_bien_nuevo'] ?? null,
                'valor_aduana' => $datos['valor_aduana'] ?? null,
                'base_fiscal_verificada' => true,
                'valor_adquisicion' => $datos['valor_adquisicion'],
                'valor_residual' => $datos['valor_residual'] ?? 0,
                'es_usado' => $datos['es_usado'] ?? false,
                'anios_uso_previo' => $datos['anios_uso_previo'] ?? 0,
                'maquinaria_importada_exenta' => $datos['maquinaria_importada_exenta'] ?? false,
                'valor_sujeto_depreciacion' => $valorSujetoDepreciacion,
                'estado' => 'activo',
            ]);
        });
    }

    /**
     * Calcula la depreciación mensual; una lista de IDs limita el cálculo a esos activos.
     */
    public function calcularDepreciacionMasiva($fechaCalculo = null, ?array $activosIds = null)
    {
        $fechaCalculo = $fechaCalculo ? Carbon::parse($fechaCalculo)->endOfMonth() : Carbon::now()->subMonthNoOverflow()->endOfMonth();
        $query = ActivoFijo::where('estado', 'activo')->where('base_fiscal_verificada', true)->whereNotNull('fecha_inicio_uso');
        if ($activosIds !== null) {
            $query->whereIn('id', $activosIds);
        }
        $activos = $query->get();

        $depreciados = 0;
        foreach ($activos as $activo) {
            if ($this->procesarDepreciacionActivo($activo, $fechaCalculo)) {
                $depreciados++;
            }
        }

        return $depreciados;
    }

    private function procesarDepreciacionActivo(ActivoFijo $activo, Carbon $fechaCalculo)
    {
        return DB::transaction(function () use ($activo, $fechaCalculo) {
            $activo = ActivoFijo::whereKey($activo->id)->lockForUpdate()->firstOrFail();
            if ($activo->estado !== 'activo') {
                return false;
            }
            // Ya depreciado?
            if ($activo->depreciacion_acumulada >= $activo->valor_sujeto_depreciacion) {
                $activo->update(['estado' => 'depreciado']);

                return false;
            }

            // Si se adquirió después de la fecha de cálculo
            $fechaAdq = Carbon::parse($activo->fecha_inicio_uso);
            if ($fechaAdq->gt($fechaCalculo)) {
                return false;
            }

            if (Depreciacion::where('activo_fijo_id', $activo->id)->where('fecha_calculo', '>', $fechaCalculo->toDateString())->exists()) {
                throw new Exception('Procese las depreciaciones en orden cronológico; un ajuste anterior requiere conciliación.');
            }

            // ¿Ya se calculó este mes?
            $existe = Depreciacion::where('activo_fijo_id', $activo->id)
                ->where('anio', $fechaCalculo->year)
                ->where('mes', $fechaCalculo->month)
                ->exists();

            if ($existe) {
                return false;
            }

            $porcentajeAnual = $activo->porcentaje_fiscal / 100; // Ej: 20% -> 0.20
            $depreciacionAnualCompleta = $activo->valor_sujeto_depreciacion * $porcentajeAnual;

            $inicio = $fechaCalculo->copy()->startOfMonth()->max($fechaAdq);
            $diasUso = (int) $inicio->diffInDays($fechaCalculo->copy()->startOfDay()) + 1;
            $montoMes = round($depreciacionAnualCompleta * $diasUso / $fechaCalculo->daysInYear, 4);

            // Ajustar si la cuota supera lo que falta por depreciar
            $saldoPorDepreciar = $activo->valor_sujeto_depreciacion - $activo->depreciacion_acumulada;
            if ($montoMes > $saldoPorDepreciar) {
                $montoMes = $saldoPorDepreciar;
            }

            // Registrar
            $nuevaAcumulada = $activo->depreciacion_acumulada + $montoMes;
            $valorLibros = $activo->valor_adquisicion - $nuevaAcumulada;

            Depreciacion::create([
                'activo_fijo_id' => $activo->id,
                'fecha_calculo' => $fechaCalculo->toDateString(),
                'anio' => $fechaCalculo->year,
                'mes' => $fechaCalculo->month,
                'dias_depreciados' => $diasUso,
                'monto_depreciado' => $montoMes,
                'depreciacion_acumulada_historica' => $nuevaAcumulada,
                'valor_en_libros' => $valorLibros,
            ]);

            $activo->depreciacion_acumulada = $nuevaAcumulada;
            if ($nuevaAcumulada >= $activo->valor_sujeto_depreciacion) {
                $activo->estado = 'depreciado';
            }
            $activo->save();

            return true;
        });
    }

    /**
     * Desincorpora un Activo (Vendido, Donado, Botado)
     */
    public function desincorporarActivo($id, $motivoElegido, $detalles = '')
    {
        if (! in_array($motivoElegido, ['vendido', 'donado', 'botado'])) {
            throw new Exception('Motivo de baja no válido según LISR.');
        }

        return DB::transaction(function () use ($id, $motivoElegido, $detalles) {
            $activo = ActivoFijo::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! in_array($activo->estado, ['activo', 'depreciado'])) {
                throw new Exception('El activo ya fue dado de baja.');
            }
            $activo->update([
                'estado' => $motivoElegido,
                'fecha_baja' => now()->toDateString(),
                'motivo_baja' => $detalles,
            ]);

            return $activo;
        });
    }
}
