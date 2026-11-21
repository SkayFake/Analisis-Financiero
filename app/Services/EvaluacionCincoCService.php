<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Credito;

/**
 * Evaluación de Crédito basada en el modelo de "Las Cinco C del Crédito"
 * y los criterios ampliados expuestos por el docente (Cobertura, Consistencia, Categoría).
 */
class EvaluacionCincoCService
{
    public function evaluar(Cliente $cliente, ?Credito $credito = null): array
    {
        $cliente->loadMissing(['fiadores', 'creditos', 'datosJuridicos', 'zona']);

        // 1. CARÁCTER: Voluntad de cumplir y comportamiento de pago
        $creditosPrevios = $cliente->creditos->where('id', '!=', $credito?->id);
        $totalCreditos = $creditosPrevios->count();
        $cancelados = $creditosPrevios->where('estado', 'cancelado')->count();
        $morosos = $creditosPrevios->where('dias_mora', '>', 0)->count();

        $puntosCaracter = 70;
        if ($totalCreditos > 0) {
            $puntosCaracter += min(20, $cancelados * 10);
            $puntosCaracter -= min(40, $morosos * 15);
        }
        $puntosCaracter = max(20, min(100, $puntosCaracter));

        $diagnosticoCaracter = match (true) {
            $puntosCaracter >= 80 => 'Excelente historial de cumplimiento y récord intachable en la institución.',
            $puntosCaracter >= 60 => 'Comportamiento de pago regular o cliente nuevo sin mora documentada.',
            default => 'Presenta antecedentes de atrasos o reincidencia en mora.',
        };

        // 2. CAPACIDAD: Posibilidad de pagar con ingresos o flujo de efectivo
        $ingresos = (float) $cliente->ingresos;
        $egresos = (float) $cliente->egresos;
        $capacidad = (float) $cliente->capacidad_pago;

        $puntosCapacidad = 50;
        if ($ingresos > 0) {
            $ratioDisponible = ($capacidad / $ingresos) * 100;
            if ($ratioDisponible >= 40) {
                $puntosCapacidad = 90;
            } elseif ($ratioDisponible >= 20) {
                $puntosCapacidad = 70;
            } else {
                $puntosCapacidad = 40;
            }
        }

        $diagnosticoCapacidad = match (true) {
            $capacidad > 500 => "Capacidad holgada (\${$capacidad}/mes disponible para amortización).",
            $capacidad > 150 => "Capacidad moderada (\${$capacidad}/mes libre).",
            $capacidad > 0   => "Margen estrecho de cobertura mensual (\${$capacidad}/mes).",
            default          => "Capacidad deficiente o egresos superan los ingresos declarados.",
        };

        // 3. CAPITAL: Solidez y posición financiera
        $esJuridica = $cliente->tipo === 'juridica';
        $puntosCapital = $esJuridica ? 80 : 70;
        $diagnosticoCapital = $esJuridica
            ? 'Entidad jurídica con estructura contable y solvencia mercantil.'
            : 'Persona natural con ingresos y actividad comercial/laboral declarada.';

        // 4. COLATERAL: Activos ofrecidos como garantía o fiadores
        $fiadoresCount = $credito ? $credito->fiadores()->count() : $cliente->fiadores()->count();
        $puntosColateral = $fiadoresCount > 0 ? 85 : 50;
        $diagnosticoColateral = $fiadoresCount > 0
            ? "Cuenta con respaldo solidario ({$fiadoresCount} fiador(es) registrado(s) con DUI)."
            : "Sin garantías prendarias ni fiadores solidarios registrados.";

        // 5. CONDICIONES: Entorno económico y condiciones del negocio
        $zona = $cliente->zona?->nombre ?? 'Zona Central';
        $puntosCondiciones = 75;
        $diagnosticoCondiciones = "Operación en {$zona}, actividad comercial en entorno económico habitual.";

        // CRITERIOS AMPLIADOS DEL DOCENTE:
        // - Cobertura: seguros o garantías accesorias
        // - Consistencia: estabilidad laboral / actividad continua
        // - Categoría: clasificación del cliente (A, B, C, D, E)
        $clasificacion = $cliente->clasificacion_cobro ?? 'A';
        $infoCategoria = config("erp.cobros.clasificacion.{$clasificacion}", [
            'label' => 'Normal',
            'color' => 'success',
        ]);

        $scoreTotal = round(($puntosCaracter * 0.25) + ($puntosCapacidad * 0.30) + ($puntosCapital * 0.15) + ($puntosColateral * 0.15) + ($puntosCondiciones * 0.15));

        $semaforo = match (true) {
            $scoreTotal >= 75 => ['label' => 'Riesgo Bajo / Sujeto de Crédito', 'color' => 'success'],
            $scoreTotal >= 60 => ['label' => 'Riesgo Moderado / Aprobación Condicionada', 'color' => 'warning'],
            default => ['label' => 'Riesgo Alto / Requiere Garantías', 'color' => 'danger'],
        };

        return [
            'cinco_c' => [
                'caracter' => [
                    'nombre' => 'Carácter',
                    'pregunta' => 'Voluntad de cumplir y comportamiento de pago',
                    'score' => $puntosCaracter,
                    'diagnostico' => $diagnosticoCaracter,
                    'icono' => 'user-check',
                ],
                'capacidad' => [
                    'nombre' => 'Capacidad',
                    'pregunta' => 'Posibilidad de pagar con ingresos o flujo de efectivo',
                    'score' => $puntosCapacidad,
                    'diagnostico' => $diagnosticoCapacidad,
                    'icono' => 'dollar-sign',
                ],
                'capital' => [
                    'nombre' => 'Capital',
                    'pregunta' => 'Solidez y posición financiera del cliente',
                    'score' => $puntosCapital,
                    'diagnostico' => $diagnosticoCapital,
                    'icono' => 'trending-up',
                ],
                'colateral' => [
                    'nombre' => 'Colateral',
                    'pregunta' => 'Activos ofrecidos como garantía y fiadores solidarios',
                    'score' => $puntosColateral,
                    'diagnostico' => $diagnosticoColateral,
                    'icono' => 'shield',
                ],
                'condiciones' => [
                    'nombre' => 'Condiciones',
                    'pregunta' => 'Entorno económico y condiciones del negocio',
                    'score' => $puntosCondiciones,
                    'diagnostico' => $diagnosticoCondiciones,
                    'icono' => 'activity',
                ],
            ],
            'criterios_ampliados' => [
                'cobertura' => [
                    'nombre' => 'Cobertura (Seguros)',
                    'descripcion' => 'Mecanismos de protección ante siniestro, desempleo o incapacidad.',
                    'estado' => $fiadoresCount > 0 ? 'Fianza solidaria activa' : 'Cobertura estándar sin póliza',
                ],
                'consistencia' => [
                    'nombre' => 'Consistencia',
                    'descripcion' => 'Estabilidad laboral o antigüedad en la actividad productiva.',
                    'estado' => $cliente->lugar_trabajo ? "Actividad en {$cliente->lugar_trabajo}" : 'Estabilidad documentada en expediente',
                ],
                'categoria' => [
                    'nombre' => 'Categoría',
                    'descripcion' => 'Calificación del cliente en la cartera según días de mora acumulados.',
                    'estado' => "Categoría {$clasificacion} ({$infoCategoria['label']})",
                    'color' => $infoCategoria['color'],
                ],
            ],
            'score_total' => $scoreTotal,
            'semaforo' => $semaforo,
        ];
    }
}
