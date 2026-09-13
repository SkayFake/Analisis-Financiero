<?php

namespace App\Http\Controllers;

use App\Services\MetricasCobroService;

class DashboardController extends Controller
{
    public function __invoke(MetricasCobroService $metricasService)
    {
        $metricas = $metricasService->dashboardMetricas();
        $morosidad = $metricasService->analisisMorosidad();
        $metricasZona = $metricasService->metricasPorZona();
        $metricasTipo = $metricasService->metricasPorTipoCliente();

        return view('dashboard', compact('metricas', 'morosidad', 'metricasZona', 'metricasTipo'));
    }
}
