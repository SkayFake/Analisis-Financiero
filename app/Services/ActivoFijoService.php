<?php

namespace App\Services;

use App\Models\ActivoFijo;
use App\Models\CategoriaActivo;
use App\Models\Unidad;
use App\Models\Depreciacion;
use Carbon\Carbon;
use Exception;

class ActivoFijoService
{
    /**
     * Registra un nuevo activo fijo, aplica LISR para usados y genera código.
     */
    public function registrarActivo(array $datos)
    {
        $unidad = Unidad::with('institucion')->findOrFail($datos['unidad_id']);
        $categoria = CategoriaActivo::findOrFail($datos['categoria_id']);
        
        // 1. Cálculo de LISR para bienes usados o maquinaria importada (Art 30)
        $valorBase = $datos['valor_adquisicion'] - ($datos['valor_residual'] ?? 0);
        $valorSujetoDepreciacion = $valorBase;

        if (!empty($datos['es_usado']) && $datos['es_usado']) {
            $aniosUso = (int)$datos['anios_uso_previo'];
            if ($aniosUso == 1) $factor = 0.80; // 80%
            elseif ($aniosUso == 2) $factor = 0.60; // 60%
            elseif ($aniosUso == 3) $factor = 0.40; // 40%
            elseif ($aniosUso >= 4) $factor = 0.20; // 20%
            else $factor = 1.0;

            $valorSujetoDepreciacion = $valorBase * $factor;
        }

        // Si es maquinaria importada con exención de impuestos, la ley dice que 
        // debe tomarse el valor de aduana. Asumimos que "valor_adquisicion" ya trae el ajuste si se marca esta bandera.
        // Aquí se pueden agregar validaciones más complejas según el requerimiento.
        
        // 2. Generación del Código Institucional Compuesto
        // Institucion(4) - Unidad(4) - Categoria(4) - Correlativo(4)
        $correlativoNum = ActivoFijo::where('unidad_id', $unidad->id)
            ->where('categoria_id', $categoria->id)
            ->count() + 1;
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
            'valor_adquisicion' => $datos['valor_adquisicion'],
            'valor_residual' => $datos['valor_residual'] ?? 0,
            'es_usado' => $datos['es_usado'] ?? false,
            'anios_uso_previo' => $datos['anios_uso_previo'] ?? 0,
            'maquinaria_importada_exenta' => $datos['maquinaria_importada_exenta'] ?? false,
            'valor_sujeto_depreciacion' => $valorSujetoDepreciacion,
            'estado' => 'activo'
        ]);
    }

    /**
     * Calcula y registra la depreciación mensual para todos los activos
     */
    public function calcularDepreciacionMasiva($fechaCalculo = null)
    {
        $fechaCalculo = $fechaCalculo ? Carbon::parse($fechaCalculo)->endOfMonth() : Carbon::now()->endOfMonth();
        $activos = ActivoFijo::where('estado', 'activo')->get();

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
        // Ya depreciado?
        if ($activo->depreciacion_acumulada >= $activo->valor_sujeto_depreciacion) {
            $activo->update(['estado' => 'depreciado']);
            return false;
        }

        // Si se adquirió después de la fecha de cálculo
        $fechaAdq = Carbon::parse($activo->fecha_adquisicion);
        if ($fechaAdq->gt($fechaCalculo)) {
            return false;
        }

        // ¿Ya se calculó este mes?
        $existe = Depreciacion::where('activo_fijo_id', $activo->id)
            ->where('anio', $fechaCalculo->year)
            ->where('mes', $fechaCalculo->month)
            ->exists();
        
        if ($existe) return false;

        $porcentajeAnual = $activo->categoria->porcentaje_depreciacion / 100; // Ej: 20% -> 0.20
        $depreciacionAnualCompleta = $activo->valor_sujeto_depreciacion * $porcentajeAnual;
        
        // 1er Año LISR - Proporcional en días
        if ($fechaAdq->year === $fechaCalculo->year && $fechaAdq->month === $fechaCalculo->month) {
            // Primer mes de uso: se calculan los días desde adquisición hasta fin de mes
            $diasEnMes = $fechaCalculo->daysInMonth;
            $diasUso = $diasEnMes - $fechaAdq->day + 1;
            
            // LISR permite un cálculo base de 365 días
            $cuotaDiaria = $depreciacionAnualCompleta / 365;
            $montoMes = $cuotaDiaria * $diasUso;
        } else {
            // Depreciación normal mensual (1/12)
            $montoMes = $depreciacionAnualCompleta / 12;
            $diasUso = $fechaCalculo->daysInMonth;
        }

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
            'valor_en_libros' => $valorLibros
        ]);

        $activo->depreciacion_acumulada = $nuevaAcumulada;
        if ($nuevaAcumulada >= $activo->valor_sujeto_depreciacion) {
            $activo->estado = 'depreciado';
        }
        $activo->save();

        return true;
    }

    /**
     * Desincorpora un Activo (Vendido, Donado, Botado)
     */
    public function desincorporarActivo($id, $motivoElegido, $detalles = '')
    {
        if (!in_array($motivoElegido, ['vendido', 'donado', 'botado'])) {
            throw new Exception("Motivo de baja no válido según LISR.");
        }

        $activo = ActivoFijo::findOrFail($id);
        $activo->update([
            'estado' => $motivoElegido,
            'fecha_baja' => now()->toDateString(),
            'motivo_baja' => $detalles
        ]);

        return $activo;
    }
}
