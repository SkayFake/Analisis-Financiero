<?php

namespace App\Console\Commands;

use App\Models\ActivoFijo;
use App\Services\ActivoFijoService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class DepreciarActivos extends Command
{
    protected $signature = 'activos:depreciar {mes? : Mes cerrado en formato YYYY-MM}';

    protected $description = 'Depreciación de activos con fecha de uso y base fiscal revisadas';

    public function handle(ActivoFijoService $service): int
    {
        $mes = $this->argument('mes') ?? today()->subMonthNoOverflow()->format('Y-m');
        Validator::make(['mes' => $mes], ['mes' => 'required|date_format:Y-m|before:'.today()->startOfMonth()->toDateString()])->validate();
        $fecha = Carbon::createFromFormat('!Y-m', $mes)->endOfMonth();
        $n = $service->calcularDepreciacionMasiva($fecha);
        $pendientes = ActivoFijo::where('estado', 'activo')->where(fn ($q) => $q->where('base_fiscal_verificada', false)->orWhereNull('fecha_inicio_uso'))->count();
        $this->info("Activos procesados: {$n}. Activos históricos pendientes de revisión: {$pendientes}.");

        return $pendientes ? self::FAILURE : self::SUCCESS;
    }
}
