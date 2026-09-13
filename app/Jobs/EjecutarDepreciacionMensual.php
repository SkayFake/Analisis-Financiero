<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ActivoFijoService;
use Illuminate\Support\Facades\Log;

class EjecutarDepreciacionMensual implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(ActivoFijoService $service): void
    {
        Log::info('Iniciando cálculo automático de depreciación mensual (LISR)...');
        
        try {
            $cantidad = $service->calcularDepreciacionMasiva();
            Log::info("Depreciación completada exitosamente. {$cantidad} activos procesados.");
        } catch (\Exception $e) {
            Log::error('Error al ejecutar depreciación mensual: ' . $e->getMessage());
        }
    }
}
