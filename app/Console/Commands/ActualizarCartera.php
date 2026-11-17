<?php

namespace App\Console\Commands;

use App\Models\Credito;
use App\Services\CreditoService;
use App\Services\IncobrableService;
use Illuminate\Console\Command;

class ActualizarCartera extends Command
{
    protected $signature = 'creditos:actualizar-cartera';

    protected $description = 'Actualiza devengos y clasificación de créditos vigentes/vencidos con historial conciliado';

    public function handle(CreditoService $service, IncobrableService $incobrables): int
    {
        $errores = 0;
        $procesados = 0;
        Credito::whereIn('estado', ['vigente', 'vencido'])->orderBy('id')->chunkById(100, function ($creditos) use ($service, $incobrables, &$errores, &$procesados) {
            foreach ($creditos as $c) {
                try {
                    $service->calcularMora($c);
                    $incobrables->clasificarCliente($c->cliente);
                    $procesados++;
                } catch (\Throwable $e) {
                    $errores++;
                    $this->error('Crédito '.$c->numero.': '.$e->getMessage());
                }
            }
        });
        $this->info("Procesados: {$procesados}; pendientes de revisión: {$errores}.");

        return $errores ? self::FAILURE : self::SUCCESS;
    }
}
