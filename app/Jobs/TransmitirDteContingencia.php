<?php

namespace App\Jobs;

use App\Models\Venta;
use App\Services\DteApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TransmitirDteContingencia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $ventaId;

    /**
     * Create a new job instance.
     */
    public function __construct($ventaId)
    {
        $this->ventaId = $ventaId;
    }

    /**
     * Execute the job.
     */
    public function handle(DteApiService $dteApiService): void
    {
        $venta = Venta::find($this->ventaId);

        if (!$venta || $venta->estado_dte !== 'contingencia') {
            return;
        }

        try {
            // Intentar re-transmitir el JSON Firmado al MH
            // Asumimos que si está en contingencia, al menos fue firmado, si no, habría que firmarlo aquí.
            if ($venta->json_firmado) {
                $respuestaMH = $dteApiService->transmitirDTE($venta->json_firmado, $venta->tipo_documento, $venta->codigo_generacion);
                
                if (isset($respuestaMH['estado']) && $respuestaMH['estado'] == 'PROCESADO') {
                    $venta->estado_dte = 'procesado';
                    $venta->sello_recepcion = $respuestaMH['selloRecibido'];
                    $venta->mensaje_mh = 'Procesado tras contingencia';
                    $venta->save();
                } else {
                    // Volver a encolar o dejar en rechazado según la lógica de reintentos
                    throw new \Exception("Rechazado por MH: " . json_encode($respuestaMH));
                }
            } else {
                // Lógica para firmar si falló antes de firmar...
                Log::warning("Venta {$this->ventaId} en contingencia pero sin JSON firmado.");
            }

        } catch (\Exception $e) {
            Log::error("Fallo al transmitir DTE en contingencia: " . $e->getMessage());
            // Si falla, el job se puede reintentar (si se configura retries en el Queue)
            $this->release(300); // Reintentar en 5 minutos
        }
    }
}
