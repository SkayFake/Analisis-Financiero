<?php

namespace App\Http\Controllers;

use App\Services\MetricasCobroService;
use App\Services\IncobrableService;
use Illuminate\Http\Request;

class CobroController extends Controller
{
    public function dashboard(MetricasCobroService $metricasService)
    {
        $metricas = $metricasService->dashboardMetricas();
        $morosidad = $metricasService->analisisMorosidad();
        $rendimiento = $metricasService->rendimientoPorVendedor(
            now()->startOfMonth(),
            now()->endOfMonth()
        );

        return view('cobros.dashboard', compact('metricas', 'morosidad', 'rendimiento'));
    }

    public function reclasificacionMasiva(IncobrableService $incobrableService)
    {
        $resultado = $incobrableService->reclasificacionMasiva(auth()->id());

        return redirect()->back()
            ->with('success', "Reclasificación masiva completada. Se procesaron {$resultado['clientes_procesados']} clientes y hubo {$resultado['cambios']} cambios.");
    }
}
